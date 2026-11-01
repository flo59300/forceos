<?php

namespace App\Controllers;

use App\Domain\Delegation;
use App\Domain\MemberStatus;
use App\Domain\Questions;
use App\Domain\Tasks;
use App\Libraries\Navigation;

/**
 * « Ma journée » : la page d'arrivée du délégué.
 * Répond dans l'ordre à : que dois-je faire maintenant, que puis-je faire en un geste, où en suis-je.
 */
class Hub extends BaseController
{
    public function index()
    {
        $uid  = (int) $this->auth->id();
        $site = (int) $this->auth->siteId();
        $role = (string) $this->auth->effectiveRole();
        $now  = new \DateTimeImmutable();
        $year = (new \App\Domain\Site($this->db))->currentYear($site);

        $todo    = [];   // cartes « À faire maintenant », dans l'ordre d'urgence
        $figures = [];   // trois indicateurs au plus

        // Tâches échues ou du jour
        foreach ((new Tasks($this->db))->forUser($uid, false) as $t) {
            if (! $t['due_date'] || $t['due_date'] > $now->format('Y-m-d')) {
                continue;
            }
            $late   = Tasks::isOverdue($t);
            $todo[] = [
                'tone'  => $late ? 'danger' : 'warning',
                'tag'   => $late ? 'Tâche en retard' : "Tâche du jour",
                'title' => $t['title'],
                'meta'  => 'Échéance ' . due_label($t['due_date']),
                'url'   => '/taches#tache-' . $t['source'] . '-' . $t['id'],
            ];
        }

        // Transferts d'heures à accepter
        $deleg = new Delegation($this->db);
        foreach ($deleg->pendingIncoming($uid) as $tr) {
            $todo[] = [
                'tone' => 'info', 'tag' => 'Heures à accepter',
                'title' => $tr['from_name'] . ' vous propose ' . Delegation::format((int) $tr['minutes']),
                'meta' => 'Pour ' . date_fr(sprintf('%d-%02d-01', $tr['year'], $tr['month']), 'MMMM y'),
                'url' => '/heures#transferts',
            ];
        }

        $questions = new Questions($this->db);
        if ($role === 'secretaire') {
            $open = $questions->counts($site, null)['open'];
            if ($open > 0) {
                $todo[] = [
                    'tone' => 'warning', 'tag' => 'Questions des délégués',
                    'title' => plural($open, 'question attend', 'questions attendent') . ' une réponse',
                    'meta' => 'Les urgentes apparaissent en premier', 'url' => '/questions?statut=ouvertes',
                ];
            }
            $pendingBk = (new \App\Domain\Permanences($this->db, $site))->pendingCount();
            if ($pendingBk > 0) {
                $todo[] = [
                    'tone' => 'warning', 'tag' => 'Permanences',
                    'title' => plural($pendingBk, 'demande de rendez-vous', 'demandes de rendez-vous') . ' à valider',
                    'meta' => 'Réservées par QR code', 'url' => '/permanences?vue=attente',
                ];
            }
            $pendingRev = $this->db->table('reversements')->where(['site_id' => $site])->where('validated_at IS NULL')->countAllResults();
            if ($pendingRev > 0) {
                $todo[] = [
                    'tone' => 'info', 'tag' => 'Finances',
                    'title' => plural($pendingRev, 'reversement') . ' à valider',
                    'meta' => 'Déclarés par les collecteurs', 'url' => '/finances/reversements',
                ];
            }
        } else {
            foreach ($questions->recentAnswers($uid) as $q) {
                $todo[] = [
                    'tone' => 'success', 'tag' => 'Réponse reçue',
                    'title' => $q['subject'], 'meta' => 'Répondu ' . date_fr($q['responded_at'], "EEEE d MMMM 'à' HH:mm"),
                    'url' => '/questions#question-' . $q['id'],
                ];
            }
        }

        // Adhérents en retard (collecteur : les siens ; secrétaire : le site)
        if (in_array($role, ['collecteur', 'secretaire'], true)) {
            $ids = null;
            if ($role === 'collecteur') {
                $ids = array_column($this->db->table('members')->select('id')->where(['assigned_user_id' => $uid, 'is_radiated' => 0])->get()->getResultArray(), 'id');
            }
            $st     = (new MemberStatus($this->db))->forSite($site, $year, $ids);
            $total  = count($st);
            $ajour  = count(array_filter($st, static fn ($s) => $s['status'] === 'a_jour'));
            $late   = $total - $ajour;
            if ($late > 0) {
                $todo[] = [
                    'tone' => 'neutral', 'tag' => 'Cotisations',
                    'title' => plural($late, 'adhérent') . ' à relancer ce mois',
                    'meta' => $role === 'collecteur' ? 'Parmi vos adhérents' : 'Sur tout le site', 'url' => '/adherents',
                ];
            }
            $figures[] = ['value' => $ajour . "\u{202F}/\u{202F}" . $total, 'label' => 'adhérents à jour', 'pct' => $total ? (int) round($ajour / $total * 100) : null];
        }

        // Heures de délégation du mois (règle unifiée)
        $h = $deleg->month($uid, (int) $now->format('Y'), (int) $now->format('n'));
        if ($h['credit'] > 0 || $h['used'] > 0) {
            $figures[] = [
                'value' => Delegation::format($h['remaining']),
                'label' => $h['remaining'] < 0 ? 'heures en dépassement' : 'heures restantes ce mois',
                'pct' => $h['credit'] ? min(100, $h['pct']) : null,
                'tone' => $h['remaining'] < 0 ? 'danger' : ($h['pct'] >= 90 ? 'warning' : null),
            ];
        }

        // Troisième indicateur selon le rôle
        if ($role === 'collecteur') {
            $nbTimbres = $this->db->table('sales')->where(['sold_by' => $uid, 'year' => $year, 'type' => 'timbre'])->countAllResults();
            $nbMembers = count($ids ?? []);
            $objectif  = $nbMembers * (int) $now->format('n');
            if ($objectif > 0) {
                $pct       = min(100, (int) round($nbTimbres / $objectif * 100));
                $figures[] = ['value' => $pct . "\u{00A0}%", 'label' => 'objectif de timbres atteint', 'pct' => $pct];
            }
        } elseif ($role === 'secretaire') {
            $c         = $questions->counts($site, null);
            $figures[] = ['value' => (string) $c['open'], 'label' => 'questions en attente', 'pct' => null];
        } else {
            $sum       = (float) ($this->db->table('sales')->selectSum('amount', 's')->where(['site_id' => $site, 'year' => $year, 'is_reversed' => 0])->get()->getRow()->s ?? 0);
            $figures[] = ['value' => money($sum), 'label' => 'à reverser sur le site', 'pct' => null];
        }

        // Annonces du panneau et rendez-vous des sept prochains jours
        $week   = $now->modify('+7 days')->format('Y-m-d');
        $agenda = [];
        foreach ((new \App\Domain\Agenda($this->db, $site))->upcoming(4, $week) as $e) {
            $agenda[] = ['date' => $e['event_date'], 'time' => $e['start_time'] ? substr($e['start_time'], 0, 5) : null, 'title' => $e['title'], 'meta' => $e['location'], 'url' => '/agenda/' . $e['id']];
        }
        foreach ((new \App\Domain\Permanences($this->db, $site))->next($week, 3) as $p) {
            $agenda[] = ['date' => $p['perm_date'], 'time' => null, 'title' => $p['title'], 'meta' => 'Permanence' . ($p['location'] ? ', ' . $p['location'] : ''), 'url' => '/permanences/' . $p['id']];
        }
        usort($agenda, static fn ($a, $b) => strcmp($a['date'] . ($a['time'] ?? '00:00'), $b['date'] . ($b['time'] ?? '00:00')));

        return $this->page('hub/index', [
            'title'   => 'Ma journée',
            'news'    => (new \App\Domain\Announcements($this->db, $site))->forHub(3),
            'agenda'  => array_slice($agenda, 0, 5),
            'todo'    => array_slice($todo, 0, 6),
            'more'    => max(0, count($todo) - 6),
            'figures' => array_slice($figures, 0, 3),
            'today'   => date_fr($now->format('Y-m-d'), 'EEEE d MMMM'),
        ]);
    }

    /** « Tout le reste » : les écrans secondaires, regroupés et filtrés par rôle. */
    public function more()
    {
        return $this->page('hub/more', ['title' => 'Tout le reste', 'groups' => Navigation::more((string) $this->auth->effectiveRole())]);
    }

    /** Écran pas encore porté : page d'attente honnête, avec lien vers la version actuelle. */
    public function pending(string $key)
    {
        $item = null;
        foreach (Navigation::main((string) $this->auth->effectiveRole()) as $i) {
            if ($i['key'] === $key) {
                $item = $i;
            }
        }
        if (! $item) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->page('hub/pending', ['title' => $item['label'], 'item' => $item]);
    }
}
