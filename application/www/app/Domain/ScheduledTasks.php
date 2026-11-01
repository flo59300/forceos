<?php

namespace App\Domain;

use App\Libraries\Mailer;
use App\Libraries\Notifier;
use App\Libraries\Settings;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Les trois tâches planifiées de la 2.x, réunies et corrigées :
 *  - rappels (cron_rappels.php) : liste des adhérents en retard envoyée à chaque collecteur, selon la fréquence du site ;
 *  - récapitulatif (cron_notifications.php) : bilan hebdomadaire ou mensuel des collecteurs, rapport mensuel du secrétaire ;
 *  - push (cron_push.php) : rappel des événements du lendemain, heures de délégation en fin de mois, transferts en attente.
 *
 * Dans la 2.x, les deux tâches de courriels s'arrêtaient sur une erreur avant le premier envoi
 * (fonction ou classe non chargée) : aucun rappel n'est jamais parti. Ici, les courriels passent par
 * les réglages SMTP de l'application, et les statuts viennent du même calcul que le reste du hub.
 */
class ScheduledTasks
{
    /** @var list<string> */
    public array $log = [];

    public function __construct(private BaseConnection $db, private bool $dryRun = false)
    {
    }

    private function say(string $line): void
    {
        $this->log[] = $line;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));
    }

    private function year(int $siteId): int
    {
        return (new Site($this->db))->currentYear($siteId);
    }

    private function mail(string $to, string $subject, string $body): bool
    {
        if ($this->dryRun) {
            $this->say("    (essai) courriel à {$to} : {$subject}");

            return true;
        }

        return Mailer::send($to, $subject, $body);
    }

    private function signature(string $siteName): string
    {
        return "\n" . Settings::appName() . ' · ' . $siteName . "\n" . site_url('/') . "\n";
    }

    /** Rappels aux collecteurs (fréquence réglée par site, journal reminder_logs comme la 2.x). */
    public function reminders(): int
    {
        $sent  = 0;
        $month = (int) $this->now()->format('n');
        $sites = $this->db->query('SELECT * FROM sites WHERE active = 1 AND notifications_enabled = 1 AND reminder_frequency_days > 0')->getResultArray();
        if (! $sites) {
            $this->say('Aucun site avec rappels activés.');
        }
        foreach ($sites as $site) {
            $sid  = (int) $site['id'];
            $freq = (int) $site['reminder_frequency_days'];
            $year = $this->year($sid);
            $this->say("Site {$site['name']} (tous les {$freq} jours)");
            $statuses = (new MemberStatus($this->db))->forSite($sid, $year);
            foreach ($this->db->query("SELECT id, first_name, last_name, email FROM users WHERE site_id = ? AND role = 'collecteur' AND active = 1", [$sid])->getResultArray() as $c) {
                $last = $this->db->query('SELECT sent_at FROM reminder_logs WHERE site_id = ? AND user_id = ? ORDER BY sent_at DESC LIMIT 1', [$sid, $c['id']])->getRow();
                if ($last && (int) ((strtotime(date('Y-m-d')) - strtotime(substr($last->sent_at, 0, 10))) / 86400) < $freq) {
                    $this->say("  {$c['first_name']} {$c['last_name']} : dernier rappel trop récent, ignoré");

                    continue;
                }
                $late = [];
                foreach ($this->db->query('SELECT m.id, m.first_name, m.last_name, s.name AS secteur FROM members m LEFT JOIN secteurs s ON s.id = m.secteur_id WHERE m.assigned_user_id = ? AND m.is_radiated = 0 ORDER BY m.last_name, m.first_name', [$c['id']])->getResultArray() as $m) {
                    $st = $statuses[$m['id']] ?? ['status' => 'sympathisant', 'nb_paid' => 0];
                    if ($st['status'] === 'en_attente' || ($st['status'] === 'sympathisant' && $month >= 2)) {
                        $late[] = $m + ['nb_paid' => $st['nb_paid'], 'label' => $st['status'] === 'en_attente' ? 'en retard' : 'aucun timbre'];
                    }
                }
                if (! $late) {
                    $this->say("  {$c['first_name']} {$c['last_name']} : aucun adhérent en retard");

                    continue;
                }
                $n    = count($late);
                $body = "Bonjour {$c['first_name']},\n\nVoici vos adhérents en retard de cotisation pour {$year} :\n\n";
                foreach ($late as $i => $m) {
                    $body .= ($i + 1) . ". {$m['last_name']} {$m['first_name']}" . ($m['secteur'] ? ", {$m['secteur']}" : '') . " ({$m['label']}, {$m['nb_paid']}/12 timbres)\n";
                }
                $body .= "\nPensez à reprendre contact avec eux pour régulariser leur situation.\n" . $this->signature($site['name'])
                    . "\nMessage automatique. Pour changer la fréquence de ces rappels, adressez-vous à votre secrétaire.\n";
                if ($this->mail((string) $c['email'], "[{$site['name']}] Rappel : " . plural($n, 'adhérent') . ' en retard', $body)) {
                    if (! $this->dryRun) {
                        $this->db->table('reminder_logs')->insert(['site_id' => $sid, 'user_id' => $c['id'], 'nb_members' => $n]);
                    }
                    $sent++;
                    $this->say("  Envoyé à {$c['first_name']} {$c['last_name']} : {$n} adhérent(s)");
                } else {
                    $this->say("  Échec de l'envoi à {$c['email']}");
                }
            }
        }
        $this->say("Terminé : {$sent} rappel(s) envoyé(s).");

        return $sent;
    }

    /** Récapitulatif des collecteurs (daily, weekly, monthly) et rapport mensuel des secrétaires. */
    public function recap(string $mode): int
    {
        $mode  = in_array($mode, ['daily', 'weekly', 'monthly'], true) ? $mode : 'weekly';
        $sent  = 0;
        $label = date_fr($this->now()->format('Y-m-d'), 'MMMM y');
        foreach ($this->db->query('SELECT * FROM sites WHERE active = 1')->getResultArray() as $site) {
            if (empty($site['notifications_enabled'])) {
                continue;
            }
            $sid      = (int) $site['id'];
            $year     = $this->year($sid);
            $statuses = (new MemberStatus($this->db))->forSite($sid, $year);
            $this->say("Site {$site['name']} ({$mode})");
            foreach ($this->db->query("SELECT id, first_name, last_name, email FROM users WHERE site_id = ? AND role = 'collecteur' AND active = 1 AND email IS NOT NULL AND email != ''", [$sid])->getResultArray() as $c) {
                $members = $this->db->query('SELECT id, first_name, last_name FROM members WHERE assigned_user_id = ? AND is_radiated = 0 ORDER BY last_name, first_name', [$c['id']])->getResultArray();
                if (! $members) {
                    continue;
                }
                $late = $symp = [];
                $ok   = 0;
                foreach ($members as $m) {
                    $st = $statuses[$m['id']] ?? ['status' => 'sympathisant', 'nb_paid' => 0];
                    match ($st['status']) {
                        'a_jour' => $ok++,
                        'en_attente' => $late[] = "{$m['last_name']} {$m['first_name']} ({$st['nb_paid']}/12)",
                        default => $symp[] = "{$m['last_name']} {$m['first_name']}",
                    };
                }
                if (! $late && ! $symp && $mode !== 'monthly') {
                    continue;
                }
                $subject = "[{$site['name']}] " . ($mode === 'monthly' ? "Récapitulatif de {$label}" : plural(count($late) + count($symp), 'adhérent demande', 'adhérents demandent') . ' votre attention');
                $body    = "Bonjour {$c['first_name']},\n\nVotre récapitulatif pour {$site['name']} :\n\n"
                    . 'Adhérents suivis : ' . count($members) . "\nÀ jour : {$ok}\nEn retard : " . count($late) . "\nSans timbre cette année : " . count($symp) . "\n";
                if ($late) {
                    $body .= "\nEn retard de cotisation :\n- " . implode("\n- ", $late) . "\n";
                }
                if ($symp && $mode !== 'daily') {
                    $body .= "\nSans timbre cette année :\n- " . implode("\n- ", $symp) . "\n";
                }
                $body .= $this->signature($site['name']);
                if ($this->mail((string) $c['email'], $subject, $body)) {
                    $sent++;
                    $this->say("  Envoyé à {$c['first_name']} {$c['last_name']}");
                }
            }
            if ($mode === 'monthly') {
                $nb  = (int) $this->db->query('SELECT COUNT(*) AS n FROM members WHERE site_id = ? AND is_radiated = 0', [$sid])->getRow()->n;
                $sum = (float) $this->db->query('SELECT COALESCE(SUM(amount), 0) AS s FROM sales WHERE site_id = ? AND year = ?', [$sid, $year])->getRow()->s;
                foreach ($this->db->query("SELECT email, first_name FROM users WHERE site_id = ? AND role = 'secretaire' AND active = 1 AND email IS NOT NULL AND email != ''", [$sid])->getResultArray() as $a) {
                    $body = "Bonjour {$a['first_name']},\n\nRapport mensuel de {$site['name']} :\n\nAdhérents actifs : {$nb}\nEncaissé en {$year} : " . money($sum) . "\n" . $this->signature($site['name']);
                    if ($this->mail($a['email'], "[{$site['name']}] Rapport mensuel du secrétaire, {$label}", $body)) {
                        $sent++;
                        $this->say("  Rapport envoyé à {$a['email']}");
                    }
                }
            }
        }
        $this->say("Terminé : {$sent} courriel(s) envoyé(s).");

        return $sent;
    }

    /** Notifications push planifiées (à lancer chaque jour en fin d'après-midi, comme la 2.x). */
    public function push(): int
    {
        $n     = new Notifier($this->db);
        $now   = $this->now();
        $tom   = $now->modify('+1 day')->format('Y-m-d');
        $total = 0;

        // 1. Événements de demain : un rappel par site, une seule fois par événement
        $events = $this->db->query(
            "SELECT e.*, s.name AS site_name FROM events e JOIN sites s ON e.site_id = s.id
             WHERE e.event_date = ? AND s.active = 1 AND e.id NOT IN (SELECT event_id FROM push_agenda_sent WHERE reminder_type = 'reminder_1d')
             ORDER BY e.site_id, e.start_time", [$tom]
        )->getResultArray();
        $bySite = [];
        foreach ($events as $e) {
            $bySite[$e['site_id']][] = $e;
        }
        foreach ($bySite as $sid => $list) {
            $e     = $list[0];
            $title = 'Rappel : ' . (count($list) > 1 ? count($list) . ' événements demain' : 'événement demain');
            $body  = $e['title'] . ($e['start_time'] ? ' à ' . substr($e['start_time'], 0, 5) : '') . ($e['location'] ? ', ' . $e['location'] : '') . (count($list) > 1 ? ' (et ' . (count($list) - 1) . ' autre' . (count($list) > 2 ? 's' : '') . ')' : '');
            $sent  = $this->dryRun ? 0 : $n->push($n->siteUsers((int) $sid), $title, $body, '/agenda?mois=' . substr($tom, 0, 7), 'agenda-rappel-' . $tom, 'agenda');
            if (! $this->dryRun) {
                foreach ($list as $ev) {
                    $this->db->query("INSERT IGNORE INTO push_agenda_sent (event_id, reminder_type) VALUES (?, 'reminder_1d')", [$ev['id']]);
                }
            }
            $total += $sent;
            $this->say("Agenda, {$e['site_name']} : " . count($list) . " événement(s), {$sent} notification(s)");
        }

        // 2. Heures de délégation : trois derniers jours du mois, plus de 30 % du crédit non utilisé (règle unifiée du hub)
        $day = (int) $now->format('j');
        $dim = (int) $now->format('t');
        if ($day >= $dim - 3) {
            $y = (int) $now->format('Y');
            $m = (int) $now->format('n');
            $d = new Delegation($this->db);
            foreach ($this->db->query('SELECT dh.user_id FROM delegation_hours dh JOIN users u ON u.id = dh.user_id WHERE dh.year = ? AND dh.month = ? AND dh.quota_minutes > 0 AND u.active = 1 AND u.push_notif_delegation = 1', [$y, $m])->getResultArray() as $r) {
                $s = $d->month((int) $r['user_id'], $y, $m);
                if ($s['credit'] > 0 && $s['remaining'] > 0 && $s['pct'] < 70) {
                    $sent = $this->dryRun ? 0 : $n->push([(int) $r['user_id']], 'Heures de délégation : ' . Delegation::format($s['remaining']) . ' restantes',
                        'Il reste ' . plural($dim - $day, 'jour') . ' pour utiliser vos heures de ' . date_fr($now->format('Y-m-d'), 'MMMM') . '.', '/heures', 'delegation-fin-de-mois', 'delegation');
                    $total += $sent;
                    $this->say("Délégation, utilisateur {$r['user_id']} : " . Delegation::format($s['remaining']) . " restantes, {$sent} notification(s)");
                }
            }
        }

        // 3. Transferts d'heures sans réponse depuis 48 à 72 heures
        foreach ($this->db->query("SELECT dt.*, u.first_name, u.last_name FROM delegation_transfers dt JOIN users u ON u.id = dt.from_user_id WHERE dt.status = 'pending' AND dt.created_at <= DATE_SUB(NOW(), INTERVAL 48 HOUR) AND dt.created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)")->getResultArray() as $t) {
            $sent = $this->dryRun ? 0 : $n->push([(int) $t['to_user_id']], 'Rappel : heures proposées', "{$t['first_name']} {$t['last_name']} attend votre réponse pour " . Delegation::format((int) $t['minutes']) . '.', '/heures#transferts', 'transfert-' . $t['id'], 'delegation');
            $total += $sent;
            $this->say("Transfert {$t['id']} : rappel, {$sent} notification(s)");
        }
        $this->say("Terminé : {$total} notification(s) envoyée(s).");

        return $total;
    }
}
