<?php

namespace App\Controllers;

use App\Domain\Permanences;
use App\Libraries\Mailer;
use App\Libraries\Settings;

/**
 * Permanences : tout le site consulte, le secrétaire organise (créneaux, inscriptions, présence, QR codes).
 */
class PermanencesController extends BaseController
{
    private function perms(): Permanences
    {
        return new Permanences($this->db, (int) $this->auth->siteId());
    }

    private function isSecretary(): bool
    {
        return $this->auth->canConfigure();
    }

    private function found(?array $p): array
    {
        if (! $p) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $p;
    }

    private function to(int $permId, string $anchor = ''): string
    {
        return site_url('permanences/' . $permId) . $anchor;
    }

    public function index()
    {
        $all      = $this->perms()->all();
        $today    = date('Y-m-d');
        $week     = date('Y-m-d', strtotime('+7 days'));
        $upcoming = array_values(array_filter($all, static fn ($p) => $p['perm_date'] >= $today));
        $past     = array_values(array_filter($all, static fn ($p) => $p['perm_date'] < $today));
        usort($upcoming, static fn ($a, $b) => strcmp($a['perm_date'], $b['perm_date']) ?: $a['id'] <=> $b['id']);

        $secretary = $this->isSecretary();
        if (! $secretary) {
            $upcoming = array_values(array_filter($upcoming, static fn ($p) => (int) $p['active'] === 1));
        }
        $view = (string) $this->request->getGet('vue');
        $q    = mb_strtolower(trim((string) $this->request->getGet('q')));
        $list = array_values(array_filter($upcoming, static function ($p) use ($view, $q, $today, $week) {
            $ok = match ($view) {
                'aujourdhui' => $p['perm_date'] === $today,
                'semaine'    => $p['perm_date'] <= $week,
                'attente'    => $p['pending_count'] > 0,
                default      => true,
            };
            if ($ok && $q !== '') {
                $ok = str_contains(mb_strtolower($p['title'] . ' ' . $p['location'] . ' ' . date('d/m/Y', strtotime($p['perm_date']))), $q);
            }

            return $ok;
        }));
        $conf     = array_sum(array_column($past, 'confirmed_count'));
        $attended = array_sum(array_column($past, 'attended_count'));

        return $this->page('permanences/index', [
            'title' => 'Permanences', 'secretary' => $secretary, 'list' => $list, 'upcomingCount' => count($upcoming),
            'stats' => Permanences::stats($upcoming), 'past' => $past, 'view' => $view, 'q' => $q,
            'pastStats' => ['confirmed' => $conf, 'attended' => $attended, 'rate' => $conf ? (int) round($attended / $conf * 100) : 0],
        ]);
    }

    public function show(int $id)
    {
        $p = $this->found($this->perms()->find($id));
        if (! $p['active'] && ! $this->isSecretary()) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->page('permanences/show', [
            'title' => $p['title'], 'p' => $p, 'secretary' => $this->isSecretary(),
            'link' => public_url('booking.php?t=' . $p['token']),
            'seriesLink' => $p['series_token'] ? public_url('booking.php?s=' . $p['series_token']) : null,
        ]);
    }

    public function poster(int $id)
    {
        $p      = $this->found($this->perms()->find($id));
        $series = $this->request->getGet('serie') && $p['series_token'];

        return view('permanences/poster', [
            'title' => $p['title'], 'p' => $p, 'appName' => Settings::appName(), 'series' => $series,
            'link' => public_url('booking.php?' . ($series ? 's=' . $p['series_token'] : 't=' . $p['token'])),
        ]);
    }

    // ------------------------------------------------------------------
    // Création et modification
    // ------------------------------------------------------------------

    public function new()
    {
        return $this->page('permanences/form', ['title' => 'Nouvelle permanence', 'p' => null]);
    }

    public function edit(int $id)
    {
        return $this->page('permanences/form', ['title' => 'Modifier la permanence', 'p' => $this->found($this->perms()->find($id))]);
    }

    /** Champs communs à la création et à la modification. */
    private function common(): array
    {
        $quota = (bool) $this->request->getPost('has_quota');

        return [
            'title'       => trim((string) $this->request->getPost('title')),
            'location'    => trim((string) $this->request->getPost('location')),
            'description' => trim((string) $this->request->getPost('description')),
            'max_participants' => $quota ? max(1, min(500, (int) $this->request->getPost('max_participants'))) : null,
        ];
    }

    private function commonErrors(array $d): array
    {
        $e = [];
        if ($d['title'] === '') {
            $e['title'] = 'Indiquez un titre.';
        } elseif (mb_strlen($d['title']) > 255) {
            $e['title'] = 'Le titre est trop long (255 caractères au plus).';
        }
        if (mb_strlen($d['location']) > 255) {
            $e['location'] = 'Le lieu est trop long (255 caractères au plus).';
        }
        if (mb_strlen($d['description']) > 2000) {
            $e['description'] = 'La description est trop longue (2 000 caractères au plus).';
        }

        return $e;
    }

    public function create()
    {
        $d      = $this->common();
        $errors = $this->commonErrors($d);
        $today  = date('Y-m-d');
        $dates  = array_values(array_unique(array_filter(array_map('trim', (array) $this->request->getPost('dates')))));
        sort($dates);
        $bad = array_filter($dates, static fn ($x) => ! Permanences::validDate($x));
        if (! $dates) {
            $errors['dates'] = 'Indiquez au moins une date.';
        } elseif ($bad) {
            $errors['dates'] = 'Une des dates n’est pas valide.';
        } elseif (array_filter($dates, static fn ($x) => $x < $today)) {
            $errors['dates'] = 'Une date est déjà passée : les permanences se créent à partir d’aujourd’hui.';
        } elseif (count($dates) > Permanences::MAX_DATES) {
            $errors['dates'] = 'Soixante dates au plus à la fois.';
        }
        $slots = [];
        $starts = (array) $this->request->getPost('slot_start');
        $ends   = (array) $this->request->getPost('slot_end');
        foreach ($starts as $i => $s) {
            $s = trim((string) $s);
            $e = trim((string) ($ends[$i] ?? ''));
            if ($s === '' && $e === '') {
                continue;
            }
            if (! Permanences::validTime($s) || ! Permanences::validTime($e) || $e <= $s) {
                $errors['slot_start'] = 'Chaque créneau doit avoir une heure de début et une heure de fin, la fin après le début.';

                break;
            }
            $slots[] = [$s, $e];
        }
        if (! $slots && ! isset($errors['slot_start'])) {
            $errors['slot_start'] = 'Ajoutez au moins un créneau.';
        }
        $d['max_bookings'] = max(1, min(50, (int) $this->request->getPost('max_bookings')));
        if ($errors) {
            return redirect()->to(site_url('permanences/nouvelle'))->withInput()->with('errors', $errors);
        }
        $r = $this->perms()->create($d, $dates, $slots, (int) $this->auth->id());

        return redirect()->to($r['created'] === 1 ? $this->to($r['ids'][0]) : site_url('permanences'))
            ->with('success', $r['created'] === 1 ? 'Permanence créée. Son lien et son QR code sont prêts.'
                : plural($r['created'], 'permanence créée', 'permanences créées') . ', avec un calendrier commun pour la série.');
    }

    public function update(int $id)
    {
        $this->found($this->perms()->find($id));
        $d      = $this->common();
        $errors = $this->commonErrors($d);
        $d['perm_date'] = trim((string) $this->request->getPost('perm_date'));
        if (! Permanences::validDate($d['perm_date'])) {
            $errors['perm_date'] = 'La date n’est pas valide.';
        }
        if ($errors) {
            return redirect()->to(site_url("permanences/{$id}/modifier"))->withInput()->with('errors', $errors);
        }
        $this->perms()->update($id, $d);

        return redirect()->to($this->to($id))->with('success', 'Permanence modifiée.');
    }

    public function duplicate(int $id)
    {
        $date = trim((string) $this->request->getPost('new_date'));
        if (! Permanences::validDate($date)) {
            return redirect()->to($this->to($id, '#dupliquer'))->with('error', 'Indiquez une date valide pour la copie.');
        }
        $new = $this->perms()->duplicate($id, $date, (int) $this->auth->id());
        if (! $new) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return redirect()->to($this->to($new))->with('success', 'Permanence dupliquée au ' . date_fr($date, 'd MMMM y') . ', avec ses créneaux.');
    }

    public function toggle(int $id)
    {
        $s = $this->perms()->toggle($id);
        if ($s === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return redirect()->to($this->to($id))->with('success', $s ? 'Permanence réactivée : les réservations sont ouvertes.' : 'Permanence désactivée : le lien public ne fonctionne plus.');
    }

    public function delete(int $id)
    {
        if (! $this->perms()->delete($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return redirect()->to(site_url('permanences'))->with('success', 'Permanence supprimée, avec ses créneaux et ses inscriptions.');
    }

    // ------------------------------------------------------------------
    // Créneaux
    // ------------------------------------------------------------------

    public function addSlot(int $id)
    {
        $s = trim((string) $this->request->getPost('new_slot_start'));
        $e = trim((string) $this->request->getPost('new_slot_end'));
        if (! Permanences::validTime($s) || ! Permanences::validTime($e) || $e <= $s) {
            return redirect()->to($this->to($id, '#creneaux'))->with('error', 'L’heure de fin doit suivre l’heure de début.');
        }
        if (! $this->perms()->addSlot($id, $s, $e, max(1, min(50, (int) $this->request->getPost('new_slot_max'))))) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return redirect()->to($this->to($id, '#creneaux'))->with('success', "Créneau de {$s} à {$e} ajouté.");
    }

    public function deleteSlot(int $slotId)
    {
        $slot = $this->perms()->slot($slotId);
        $r    = $this->perms()->deleteSlot($slotId);
        if ($r === 'missing') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return redirect()->to($this->to((int) $slot['perm_id'], '#creneaux'))->with(
            $r === 'ok' ? 'success' : 'error',
            $r === 'ok' ? 'Créneau supprimé.' : 'Ce créneau a des inscrits : annulez-les avant de le supprimer.'
        );
    }

    // ------------------------------------------------------------------
    // Inscriptions
    // ------------------------------------------------------------------

    public function manual(int $id)
    {
        $d = [
            'name' => trim((string) $this->request->getPost('booking_name')), 'phone' => trim((string) $this->request->getPost('booking_phone')),
            'email' => trim((string) $this->request->getPost('booking_email')), 'subject' => trim((string) $this->request->getPost('booking_subject')),
            'notes' => trim((string) $this->request->getPost('booking_notes')),
        ];
        $slotId = (int) $this->request->getPost('slot_id');
        $errors = [];
        if (! $slotId) {
            $errors['slot_id'] = 'Choisissez un créneau.';
        }
        if ($d['name'] === '' || mb_strlen($d['name']) > 255) {
            $errors['booking_name'] = 'Indiquez le nom de la personne.';
        }
        if ($d['email'] !== '' && ! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['booking_email'] = 'L’adresse e-mail n’est pas valide.';
        }
        $slot = $slotId ? $this->perms()->slot($slotId) : null;
        if ($slotId && (! $slot || (int) $slot['perm_id'] !== $id)) {
            $errors['slot_id'] = 'Choisissez un créneau de cette permanence.';
        }
        if ($errors) {
            return redirect()->to($this->to($id, '#inscrire'))->withInput()->with('errors', $errors);
        }
        $r = $this->perms()->manualBooking($slotId, $d + ['phone' => mb_substr($d['phone'], 0, 50), 'subject' => mb_substr($d['subject'], 0, 255)], (int) $this->auth->id());

        return $r === 'ok'
            ? redirect()->to($this->to($id, '#inscrits'))->with('success', 'Inscription de ' . $d['name'] . ' confirmée.')
            : redirect()->to($this->to($id, '#inscrire'))->withInput()->with('errors', ['slot_id' => 'Ce créneau, ou le quota de la permanence, est complet.']);
    }

    /** Envoie le lien de confirmation ; si l'envoi échoue, la demande est confirmée directement. */
    private function sendConfirmation(array $b, string $token): bool
    {
        $app  = Settings::appName();
        $body = 'Bonjour ' . $b['name'] . ",\n\n"
            . 'Votre inscription à « ' . $b['title'] . ' » du ' . date('d/m/Y', strtotime($b['perm_date']))
            . ' (' . substr($b['start_time'], 0, 5) . ' à ' . substr($b['end_time'], 0, 5) . ") a été acceptée.\n\n"
            . "Confirmez-la en ouvrant ce lien :\n" . public_url('verify.php?v=' . $token) . "\n\n"
            . ($b['location'] ? 'Lieu : ' . $b['location'] . "\n\n" : '') . $app;
        if (Mailer::send($b['email'], $app . ' : confirmez votre inscription', $body)) {
            return true;
        }
        $this->perms()->confirmDirectly((int) $b['id']);

        return false;
    }

    public function booking(int $bookingId, string $action)
    {
        $p = $this->perms();
        $b = $this->found($p->booking($bookingId));
        $to = $this->to((int) $b['perm_id'], '#inscription-' . $bookingId);

        switch ($action) {
            case 'valider':
                $r = $p->validate($bookingId);
                if (! $r) {
                    return redirect()->to($to)->with('error', 'Cette demande a déjà été traitée.');
                }
                if (! $r['token']) {
                    return redirect()->to($to)->with('success', 'Demande validée et confirmée (pas d’adresse e-mail).');
                }

                return $this->sendConfirmation($r['booking'], $r['token'])
                    ? redirect()->to($to)->with('success', 'Demande validée : le lien de confirmation a été envoyé à ' . $b['email'] . '.')
                    : redirect()->to($to)->with('info', 'Le courriel n’a pas pu partir : la demande est confirmée directement. Prévenez la personne vous-même.');
            case 'refuser':
                return $p->cancel($bookingId, true)
                    ? redirect()->to($to)->with('success', 'Demande refusée.')
                    : redirect()->to($to)->with('error', 'Cette demande a déjà été traitée.');
            case 'annuler':
                return $p->cancel($bookingId, false)
                    ? redirect()->to($this->to((int) $b['perm_id'], '#inscrits'))->with('success', 'Inscription de ' . $b['name'] . ' annulée.')
                    : redirect()->to($to)->with('error', 'Cette inscription est déjà annulée.');
            case 'presence':
                $v = (string) $this->request->getPost('attended');
                $ok = $p->attendance($bookingId, $v === '' ? null : $v === '1');

                return redirect()->to($to)->with($ok ? 'success' : 'error', $ok ? ($v === '1' ? $b['name'] . ' : présent.' : ($v === '0' ? $b['name'] . ' : absent.' : 'Présence effacée.')) : 'Seule une inscription confirmée peut être pointée.');
            case 'note':
                $p->note($bookingId, mb_substr(trim((string) $this->request->getPost('notes')), 0, 1000));

                return redirect()->to($to)->with('success', 'Note enregistrée.');
        }
        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    public function validateAll(int $id)
    {
        $p    = $this->perms();
        $this->found($p->find($id));
        $sent = $direct = 0;
        foreach ($p->pendingOf($id) as $bid) {
            $r = $p->validate((int) $bid);
            if (! $r) {
                continue;
            }
            if ($r['token'] && $this->sendConfirmation($r['booking'], $r['token'])) {
                $sent++;
            } else {
                $direct++;
            }
        }
        $msg = trim(($sent ? plural($sent, 'lien de confirmation envoyé', 'liens de confirmation envoyés') . '. ' : '') . ($direct ? plural($direct, 'demande confirmée', 'demandes confirmées') . ' directement.' : ''));

        return redirect()->to($this->to($id, '#inscrits'))->with('success', $msg ?: 'Aucune demande en attente.');
    }

    public function export(int $id)
    {
        $p    = $this->found($this->perms()->find($id));
        $rows = $this->perms()->csvRows($id);
        $out  = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($out, $r, ';');
        }
        rewind($out);

        return $this->response->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="permanence-' . $p['perm_date'] . '.csv"')
            ->setBody(stream_get_contents($out));
    }
}
