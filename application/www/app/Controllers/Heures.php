<?php

namespace App\Controllers;

use App\Domain\Delegation;

/**
 * Heures de délégation : crédit du mois, saisies, transferts entre délégués, report.
 */
class Heures extends BaseController
{
    private function deleg(): Delegation
    {
        return new Delegation($this->db);
    }

    /** Mois affiché (?mois=2026-10), par défaut le mois en cours. */
    private function period(): array
    {
        $p = (string) $this->request->getGetPost('mois');
        if (preg_match('/^(\d{4})-(\d{2})$/', $p, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [(int) date('Y'), (int) date('n')];
    }

    private function back(int $y, int $m, string $anchor = ''): string
    {
        return site_url('heures') . '?mois=' . sprintf('%d-%02d', $y, $m) . $anchor;
    }

    public function index()
    {
        [$y, $m] = $this->period();
        $uid     = (int) $this->auth->id();
        $d       = $this->deleg();
        $prev    = $m === 1 ? [$y - 1, 12] : [$y, $m - 1];
        $next    = $m === 12 ? [$y + 1, 1] : [$y, $m + 1];

        return $this->page('heures/index', [
            'title' => 'Heures de délégation', 'year' => $y, 'month' => $m,
            'sum' => $d->month($uid, $y, $m), 'entries' => $d->entries($uid, $y, $m), 'byMotif' => $d->byMotif($uid, $y, $m),
            'incoming' => $d->pendingIncoming($uid), 'sent' => $d->sent($uid, $y, $m),
            'prev' => $prev, 'next' => $next, 'isCurrent' => $y === (int) date('Y') && $m === (int) date('n'),
            'people' => $this->db->table('users')->select("id, CONCAT(first_name, ' ', last_name) AS name")
                ->where(['site_id' => $this->auth->siteId(), 'active' => 1])->where('id !=', $uid)->where('role !=', 'super_admin')
                ->orderBy('last_name')->get()->getResultArray(),
        ]);
    }

    public function add()
    {
        $rules = [
            'entry_date' => ['rules' => 'required|valid_date[Y-m-d]', 'errors' => ['required' => 'Indiquez la date.', 'valid_date' => 'La date n’est pas valide.']],
            'motif'      => ['rules' => 'required|in_list[' . implode(',', array_keys(Delegation::MOTIFS)) . ']', 'errors' => ['required' => 'Choisissez un motif.', 'in_list' => 'Choisissez un motif.']],
            'description' => ['rules' => 'permit_empty|max_length[500]', 'errors' => ['max_length' => 'Le détail est trop long (500 caractères au plus).']],
        ];
        $date = (string) $this->request->getPost('entry_date');
        [$y, $m] = preg_match('/^(\d{4})-(\d{2})/', $date, $mm) ? [(int) $mm[1], (int) $mm[2]] : $this->period();
        if (! $this->validate($rules)) {
            return redirect()->to($this->back($y, $m, '#saisie'))->withInput()->with('errors', $this->validator->getErrors());
        }
        [$dur, $start, $end] = Delegation::duration(
            $this->request->getPost('start_time'), $this->request->getPost('end_time'),
            (int) $this->request->getPost('hours'), (int) $this->request->getPost('minutes')
        );
        if ($dur <= 0) {
            return redirect()->to($this->back($y, $m, '#saisie'))->withInput()->with('errors', ['start_time' => 'Indiquez une plage horaire (début avant fin) ou une durée.']);
        }
        $motif = (string) $this->request->getPost('motif');
        $uid   = (int) $this->auth->id();
        $this->deleg()->addEntry($uid, $date, $dur, $motif, $start, $end, trim((string) $this->request->getPost('description')));

        // Alerte à 90 % et 100 % du crédit (seulement si la saisie est décomptée)
        if ($motif !== Delegation::NOT_COUNTED) {
            $s = $this->deleg()->month($uid, $y, $m);
            if ($s['credit'] > 0 && $s['pct'] >= 90) {
                $this->notify($uid, $s['remaining'] < 0 ? 'Crédit d’heures dépassé' : 'Plus de 90 % de vos heures utilisées', 'Reste : ' . Delegation::format($s['remaining']) . '.', '/heures', '⏰');
            }
        }
        $msg = 'Saisie enregistrée : ' . Delegation::format($dur) . ($motif === Delegation::NOT_COUNTED ? ', hors quota.' : '.');

        return redirect()->to($this->back($y, $m))->with('success', $msg);
    }

    public function delete(int $id)
    {
        [$y, $m] = $this->period();
        $ok = $this->deleg()->deleteEntry((int) $this->auth->id(), $id);

        return redirect()->to($this->back($y, $m))->with($ok ? 'success' : 'error', $ok ? 'Saisie supprimée.' : 'Saisie introuvable.');
    }

    public function quota()
    {
        [$y, $m] = $this->period();
        $minutes = max(0, (int) $this->request->getPost('hours')) * 60 + max(0, min(59, (int) $this->request->getPost('minutes')));
        $all     = $this->request->getPost('scope') === 'annee';
        $this->deleg()->setQuota((int) $this->auth->id(), $y, $all ? 0 : $m, $minutes);

        return redirect()->to($this->back($y, $m))->with('success', 'Crédit de ' . Delegation::format($minutes) . ($all ? ' appliqué aux douze mois de ' . $y : ' enregistré pour ce mois') . '.');
    }

    public function transfer()
    {
        [$y, $m] = $this->period();
        $to      = (int) $this->request->getPost('to');
        $minutes = max(0, (int) $this->request->getPost('hours')) * 60 + max(0, min(59, (int) $this->request->getPost('minutes')));
        $r = $this->deleg()->propose((int) $this->auth->siteId(), (int) $this->auth->id(), $to, $y, $m, $minutes, trim((string) $this->request->getPost('motif')));
        if ($r === 'ok') {
            $this->notify($to, 'Heures proposées', session('user_name') . ' vous propose ' . Delegation::format($minutes) . '.', '/heures', '🤝');

            return redirect()->to($this->back($y, $m))->with('success', 'Proposition envoyée. Les heures seront transférées quand elle sera acceptée.');
        }

        return redirect()->to($this->back($y, $m, '#transfert'))->with('error', $r === 'insufficient'
            ? 'Vous n’avez pas assez d’heures disponibles ce mois-ci : ' . Delegation::format($this->deleg()->month((int) $this->auth->id(), $y, $m)['available']) . '.'
            : 'Choisissez un délégué et une durée.');
    }

    public function respond(int $id)
    {
        $accept = $this->request->getPost('reponse') === 'accepter';
        $t      = $this->deleg()->respond((int) $this->auth->id(), $id, $accept);
        if (! $t) {
            return redirect()->to(site_url('heures'))->with('error', 'Cette proposition n’est plus en attente.');
        }
        $this->notify((int) $t['from_user_id'], $accept ? 'Heures acceptées' : 'Heures refusées', session('user_name') . ($accept ? ' a accepté ' : ' a refusé ') . Delegation::format((int) $t['minutes']) . '.', '/heures', $accept ? '✅' : '↩️');

        return redirect()->to($this->back((int) $t['year'], (int) $t['month']))->with('success', $accept ? Delegation::format((int) $t['minutes']) . ' ajoutées à votre crédit.' : 'Proposition refusée.');
    }

    public function carry()
    {
        [$y, $m] = $this->period();
        $n = $this->deleg()->carryOver((int) $this->auth->id(), $y, $m);

        return redirect()->to($this->back($y, $m))->with($n > 0 ? 'success' : 'error', $n > 0 ? Delegation::format($n) . ' reportées sur le mois suivant.' : 'Aucune heure restante à reporter.');
    }
}
