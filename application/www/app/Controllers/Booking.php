<?php

namespace App\Controllers;

use App\Domain\Permanences;
use App\Libraries\Settings;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Pages publiques des permanences, sans connexion : mêmes adresses que la 2.x
 * pour que les QR codes et les liens déjà imprimés ou envoyés restent valables.
 *   booking.php?t=<jeton>          une permanence
 *   booking.php?s=<série>[&d=date] calendrier d'une série
 *   verify.php?v=<jeton>           confirmation par courriel
 *
 * Protections reprises de la 2.x : champ piège, jeton signé et horodaté (3 s à 2 h), calcul anti-robot,
 * cinq réservations par heure et par connexion, adresse obligatoire, deux inscriptions par personne.
 * Ajouts : un formulaire ne peut servir qu'une fois (la 2.x laissait rejouer la même réponse au calcul),
 * et la confirmation par courriel demande un clic sur un bouton : les antivirus de messagerie qui ouvrent
 * les liens pour les analyser ne confirment plus l'inscription à la place de la personne.
 */
class Booking extends BaseController
{
    private const RATE_LIMIT = 5;

    private function render(string $view, array $data, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)
            ->setHeader('X-Robots-Tag', 'noindex, nofollow')
            ->setBody(view($view, $data + ['appName' => Settings::appName()]));
    }

    private function notFound(string $title, string $message): ResponseInterface
    {
        return $this->render('booking/message', ['title' => $title, 'message' => $message, 'tone' => 'info'], 404);
    }

    private static function hex32(string $v): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/', $v);
    }

    public function reserve()
    {
        $token  = trim((string) $this->request->getGet('t'));
        $series = trim((string) $this->request->getGet('s'));
        $date   = trim((string) $this->request->getGet('d'));
        $okT    = self::hex32($token);
        $okS    = self::hex32($series);
        if (! $okT && ! $okS) {
            return $this->notFound('Lien invalide', 'Ce lien de réservation n’est pas valide ou a expiré.');
        }

        if ($okS) {
            $all = $this->db->table('permanences p')->select('p.*, s.name AS site_name')->join('sites s', 's.id = p.site_id')
                ->where(['p.series_token' => $series, 'p.active' => 1])->orderBy('p.perm_date')->get()->getResultArray();
            if (! $all) {
                return $this->notFound('Aucune permanence disponible', 'Cette série n’existe plus ou est terminée.');
            }
            $match = null;
            foreach ($all as $p) {
                if ($date !== '' && $p['perm_date'] === $date) {
                    $match = $p;
                }
            }
            if (! $match) {
                $future = array_values(array_filter($all, static fn ($p) => $p['perm_date'] >= date('Y-m-d')));
                $site   = (int) $all[0]['site_id'];
                $hyd    = (new Permanences($this->db, $site))->hydrate($future);

                return $this->render('booking/series', ['title' => $all[0]['title'], 'siteName' => $all[0]['site_name'], 'first' => $all[0], 'dates' => $hyd, 'series' => $series]);
            }
            $token = $match['token'];
        }

        $perm = $this->db->table('permanences p')->select('p.*, s.name AS site_name')->join('sites s', 's.id = p.site_id')
            ->where(['p.token' => $token, 'p.active' => 1])->get()->getRowArray();
        if (! $perm) {
            return $this->notFound('Permanence introuvable', 'Cette permanence n’existe plus ou est désactivée.');
        }
        $secret = md5($token . ($perm['created_at'] ?? 'salt'));   // même clé que la 2.x
        $error  = null;
        $done   = false;
        $rateKey = 'booking_rate_' . md5((string) $this->request->getIPAddress());
        $hits    = array_values(array_filter((array) cache($rateKey), static fn ($t) => (int) $t > time() - 3600));
        $limited = count($hits) >= self::RATE_LIMIT;

        if ($this->request->is('post')) {
            $error = $this->check($perm, $token, $secret, $limited);
            if ($error === null) {
                $error = $this->book($perm);
                if ($error === null) {
                    $done   = true;
                    $hits[] = time();
                    cache()->save($rateKey, $hits, 3600);
                }
            }
        }

        $hyd = (new Permanences($this->db, (int) $perm['site_id']))->hydrate([$perm])[0];
        $ts  = time();
        [$a, $b, $op] = [random_int(2, 9), random_int(1, 9), random_int(0, 1) ? '+' : '×'];

        return $this->render('booking/page', [
            'title' => $perm['title'], 'siteName' => $perm['site_name'], 'p' => $hyd, 'done' => $done, 'error' => $error, 'limited' => $limited && ! $done,
            'past' => $perm['perm_date'] < date('Y-m-d'), 'series' => $okS ? $series : null,
            'form' => [
                'ts' => $ts, 'token' => hash_hmac('sha256', $token . '|' . $ts, $secret),
                'a' => $a, 'b' => $b, 'op' => $op,
                'ch' => hash_hmac('sha256', (string) ($op === '+' ? $a + $b : $a * $b), $secret . date('YmdH')),
            ],
            'old' => $done ? [] : $this->request->getPost(['name', 'phone', 'email', 'subject', 'slot_id']),
        ], $error && ! $done ? 422 : 200);
    }

    /** Contrôles anti-abus (ordre et messages de la 2.x). */
    private function check(array $perm, string $token, string $secret, bool $limited): ?string
    {
        $p = $this->request->getPost();
        if (trim((string) ($p['website'] ?? '')) !== '') {
            return 'Erreur de validation.';
        }
        $ts      = (int) ($p['_ts'] ?? 0);
        $tok     = (string) ($p['_token'] ?? '');
        $elapsed = time() - $ts;
        if (! hash_equals(hash_hmac('sha256', $token . '|' . $ts, $secret), $tok)) {
            return 'Formulaire expiré. Rechargez la page.';
        }
        if ($elapsed < 3) {
            return 'Soumission trop rapide. Veuillez patienter.';
        }
        if ($elapsed > 7200) {
            return 'Formulaire expiré (2 h). Rechargez la page.';
        }
        $answer = trim((string) ($p['_captcha'] ?? ''));
        $ch     = (string) ($p['_ch'] ?? '');
        if (! hash_equals(hash_hmac('sha256', $answer, $secret . date('YmdH')), $ch)
            && ! hash_equals(hash_hmac('sha256', $answer, $secret . date('YmdH', time() - 3600)), $ch)) {
            return 'Le résultat du calcul est incorrect. Recalculez, puis renvoyez le formulaire.';
        }
        if (cache('booking_used_' . $tok)) {
            return 'Ce formulaire a déjà été envoyé. Rechargez la page pour une nouvelle inscription.';
        }
        if ($limited) {
            return 'Trop de réservations depuis cette connexion. Réessayez dans une heure.';
        }
        $email = trim((string) ($p['email'] ?? ''));
        if ($email === '') {
            return 'L’adresse e-mail est obligatoire pour recevoir le lien de confirmation.';
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            return 'Adresse e-mail invalide.';
        }
        $name = trim((string) ($p['name'] ?? ''));
        if ($name === '' || ! (int) ($p['slot_id'] ?? 0)) {
            return 'Veuillez renseigner votre nom et choisir un créneau.';
        }
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
            return 'Nom invalide (3 à 100 caractères).';
        }
        if ($perm['perm_date'] < date('Y-m-d')) {
            return 'Cette permanence est passée.';
        }

        return null;
    }

    private function book(array $perm): ?string
    {
        $p      = $this->request->getPost();
        $slotId = (int) $p['slot_id'];
        $name   = trim((string) $p['name']);
        $permId = (int) $perm['id'];
        $db     = $this->db;
        $r = Permanences::reserve($db, $permId, $slotId, [
            'name' => $name, 'phone' => mb_substr(trim((string) ($p['phone'] ?? '')), 0, 50) ?: null,
            'email' => trim((string) $p['email']), 'subject' => mb_substr(trim((string) ($p['subject'] ?? '')), 0, 255) ?: null,
            'booked_by' => null, 'status' => 'pending',
        ], static function () use ($db, $permId, $slotId, $name): ?string {
            $mine = (int) $db->query("SELECT COUNT(*) AS n FROM permanence_bookings pb JOIN permanence_slots ps ON pb.slot_id = ps.id WHERE ps.permanence_id = ? AND LOWER(pb.name) = ? AND pb.status IN ('pending','email_sent','confirmed')", [$permId, mb_strtolower($name)])->getRow()->n;
            if ($mine >= 2) {
                return 'Vous avez déjà 2 inscriptions sur cette permanence.';
            }
            $dup = $db->query("SELECT id FROM permanence_bookings WHERE slot_id = ? AND name = ? AND status IN ('pending','email_sent','confirmed')", [$slotId, $name])->getRow();

            return $dup ? 'Vous êtes déjà inscrit(e) sur ce créneau.' : null;
        });
        if ($r === true) {
            cache()->save('booking_used_' . (string) $p['_token'], 1, 7300);

            return null;
        }

        return match ($r) {
            'quota' => 'Le nombre maximum de participants est atteint.',
            'slot'  => 'Ce créneau est complet. Choisissez-en un autre.',
            false   => 'Créneau invalide.',
            default => (string) $r,
        };
    }

    public function verify()
    {
        $v = trim((string) ($this->request->getPost('v') ?? $this->request->getGet('v')));
        if (! self::hex32($v)) {
            return $this->notFound('Lien invalide', 'Ce lien de vérification n’est pas valide.');
        }
        $b = $this->db->table('permanence_bookings pb')
            ->select('pb.*, ps.start_time, ps.end_time, p.title, p.perm_date, p.location, s.name AS site_name')
            ->join('permanence_slots ps', 'ps.id = pb.slot_id')->join('permanences p', 'p.id = ps.permanence_id')->join('sites s', 's.id = p.site_id')
            ->where('pb.verification_token', $v)->get()->getRowArray();
        if (! $b) {
            return $this->render('booking/message', ['title' => 'Lien expiré', 'message' => 'Ce lien a déjà été utilisé ou n’est plus valide. Si vous avez déjà confirmé, votre inscription est bien enregistrée.', 'tone' => 'info']);
        }
        $confirmed = false;
        if ($this->request->is('post') && $b['status'] === 'email_sent') {
            $this->db->table('permanence_bookings')->where(['id' => $b['id'], 'status' => 'email_sent'])
                ->update(['status' => 'confirmed', 'verification_token' => null]);
            $confirmed = $this->db->affectedRows() > 0;
            $b['status'] = 'confirmed';
        }

        return $this->render('booking/verify', ['title' => 'Confirmation : ' . $b['title'], 'siteName' => $b['site_name'], 'b' => $b, 'v' => $v, 'confirmed' => $confirmed]);
    }
}
