<?php

namespace App\Controllers;

use App\Domain\Members;
use App\Domain\MemberStatus;
use App\Domain\Site;
use App\Domain\Stamps;

/**
 * Adhérents et encaissement des timbres.
 * Collecteur : ses adhérents. Secrétaire : tout le site, plus radiation, transfert et actions groupées.
 */
class Adherents extends BaseController
{
    private function members(): Members
    {
        return new Members($this->db, (int) $this->auth->siteId(), (int) $this->auth->id(), (string) $this->auth->effectiveRole());
    }

    private function site(): Site
    {
        return new Site($this->db);
    }

    private function year(): int
    {
        return $this->site()->currentYear((int) $this->auth->siteId());
    }

    private function isSecretary(): bool
    {
        return $this->auth->effectiveRole() === 'secretaire';
    }

    private function notFound(): never
    {
        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    public function index()
    {
        $f    = array_map('strval', array_intersect_key($this->request->getGet() ?? [], array_flip(['q', 'statut', 'secteur', 'tournee', 'collecteur', 'paiement', 'college'])));
        $page = max(1, (int) $this->request->getGet('page'));
        $year = $this->year();
        $res  = $this->members()->search($f, $page, $year);

        return $this->page('adherents/index', [
            'title' => 'Adhérents', 'filters' => $f, 'page' => $page, 'year' => $year,
            'rows' => $res['rows'], 'total' => $res['total'], 'statuses' => $res['statuses'],
            'pages' => max(1, (int) ceil($res['total'] / Members::PER_PAGE)),
            'lists' => $this->site()->lists((int) $this->auth->siteId()),
            'isSecretary' => $this->isSecretary(),
            'activeFilters' => count(array_filter(array_diff_key($f, ['q' => 1]), static fn ($v) => $v !== '')),
        ]);
    }

    public function show(int $id)
    {
        $m = $this->members()->find($id) ?? $this->notFound();
        $year   = $this->year();
        $status = (new MemberStatus($this->db))->forSite((int) $this->auth->siteId(), $year, [$id])[$id];
        $stamps = new Stamps($this->db);

        return $this->page('adherents/show', [
            'title' => $m['first_name'] . ' ' . $m['last_name'], 'm' => $m, 'year' => $year, 'status' => $status,
            'card' => $stamps->card($id, $year), 'history' => $stamps->history($id),
            'tarifs' => $this->site()->tarifs((int) $this->auth->siteId(), $year),
            'isSecretary' => $this->isSecretary(),
            'people' => $this->isSecretary() ? $this->site()->lists((int) $this->auth->siteId())['collecteurs'] : [],
        ]);
    }

    public function new()
    {
        return $this->page('adherents/form', ['title' => 'Nouvel adhérent', 'm' => null, 'lists' => $this->site()->lists((int) $this->auth->siteId()), 'isSecretary' => $this->isSecretary()]);
    }

    public function create()
    {
        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $members = $this->members();
        $id      = $members->create($members->clean($this->request->getPost(), true), $this->year());

        return redirect()->to(site_url('adherents/' . $id))->with('success', 'Adhérent ajouté. Vous pouvez encaisser ses premiers timbres.');
    }

    public function edit(int $id)
    {
        $m = $this->members()->find($id) ?? $this->notFound();

        return $this->page('adherents/form', ['title' => 'Modifier la fiche', 'm' => $m, 'lists' => $this->site()->lists((int) $this->auth->siteId()), 'isSecretary' => $this->isSecretary()]);
    }

    public function update(int $id)
    {
        $members = $this->members();
        $members->find($id) ?? $this->notFound();
        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $members->update($id, $members->clean($this->request->getPost(), false));

        return redirect()->to(site_url('adherents/' . $id))->with('success', 'Fiche enregistrée.');
    }

    /** Encaissement de un ou plusieurs mois (carte facturée au premier timbre). */
    public function collect(int $id)
    {
        $m = $this->members()->find($id) ?? $this->notFound();
        if (! $this->auth->canManageStamps()) {
            return $this->response->setStatusCode(403)->setBody(view('errors/forbidden'));
        }
        $months = (array) ($this->request->getPost('months') ?? []);
        if ($months === []) {
            return redirect()->to(site_url('adherents/' . $id) . '#encaisser')->with('error', 'Choisissez au moins un mois à encaisser.');
        }
        $year = $this->year();
        $res  = (new Stamps($this->db))->collect($m, $months, (string) $this->request->getPost('method'), (int) $this->auth->id(), $year, $this->site()->tarifs((int) $m['site_id'], $year));

        if (! $res['added']) {
            return redirect()->to(site_url('adherents/' . $id) . '#encaisser')->with('error', 'Ces mois étaient déjà payés : rien n’a été encaissé.');
        }
        $msg = plural(count($res['added']), 'timbre encaissé', 'timbres encaissés') . ' (' . Stamps::monthList($res['added']) . ')';
        if ($res['card']) {
            $msg .= ', avec la carte ' . $year;
        }
        $msg .= ' : ' . money($res['total']) . '.';
        if ($res['skipped']) {
            $msg .= ' Déjà payé, donc ignoré : ' . Stamps::monthList($res['skipped']) . '.';
        }

        return redirect()->to(site_url('adherents/' . $id))->with('success', $msg);
    }

    public function removeStamp(int $id)
    {
        $this->members()->find($id) ?? $this->notFound();
        if (! $this->auth->canManageStamps()) {
            return $this->response->setStatusCode(403)->setBody(view('errors/forbidden'));
        }
        $month = (int) $this->request->getPost('month');
        $r     = (new Stamps($this->db))->remove($id, $month, $this->year());
        $name  = Stamps::MONTHS[$month] ?? '';

        return match ($r) {
            'ok'       => redirect()->to(site_url('adherents/' . $id))->with('success', 'Le timbre de ' . $name . ' a été retiré.'),
            'reversed' => redirect()->to(site_url('adherents/' . $id))->with('error', 'Le timbre de ' . $name . ' a déjà été reversé à la trésorerie : il ne peut plus être retiré. Adressez-vous au trésorier.'),
            default    => redirect()->to(site_url('adherents/' . $id))->with('error', 'Ce timbre n’existe pas.'),
        };
    }

    public function radiate(int $id)
    {
        $m = $this->members()->find($id) ?? $this->notFound();
        if (! $this->members()->radiate($id, trim((string) $this->request->getPost('motif')))) {
            return $this->response->setStatusCode(403)->setBody(view('errors/forbidden'));
        }

        return redirect()->to(site_url('adherents'))->with('success', $m['first_name'] . ' ' . $m['last_name'] . ' a été radié(e).');
    }

    public function transfer(int $id)
    {
        $m  = $this->members()->find($id) ?? $this->notFound();
        $to = (int) $this->request->getPost('user_id');
        $r  = $this->members()->transfer($id, $to);
        if ($r === 'ok') {
            $this->notify($to, 'Nouvel adhérent attribué', $m['first_name'] . ' ' . $m['last_name'] . ' vous a été attribué(e).', '/adherents/' . $id, '👥');

            return redirect()->to(site_url('adherents/' . $id))->with('success', 'Adhérent transféré. Le nouveau collecteur est prévenu.');
        }

        return redirect()->to(site_url('adherents/' . $id))->with('error', $r === 'same' ? 'L’adhérent est déjà suivi par cette personne.' : 'Transfert impossible.');
    }

    public function bulk()
    {
        $ids = (array) ($this->request->getPost('ids') ?? []);
        $op  = (string) $this->request->getPost('operation');
        $n   = $this->members()->bulk($ids, $op, trim((string) $this->request->getPost('value_' . $op)));
        $back = (string) $this->request->getPost('back');
        $back = str_starts_with($back, '/adherents') ? $back : '/adherents';

        return redirect()->to(site_url($back))->with($n < 0 ? 'error' : 'success', $n < 0 ? 'Choisissez des adhérents, une action et sa valeur.' : plural($n, 'adhérent modifié', 'adhérents modifiés') . '.');
    }

    /** Radiés (secrétaire) et réintégration. */
    public function radiated()
    {
        if (! $this->isSecretary()) {
            return $this->response->setStatusCode(403)->setBody(view('errors/forbidden'));
        }
        $rows = $this->db->table('members m')
            ->select("m.id, m.first_name, m.last_name, m.radiation_date, m.radiation_motif, CONCAT(u.first_name, ' ', u.last_name) AS by_name")
            ->join('users u', 'u.id = m.radiation_by', 'left')
            ->where(['m.site_id' => $this->auth->siteId(), 'm.is_radiated' => 1])
            ->orderBy('m.radiation_date', 'DESC')->get()->getResultArray();

        return $this->page('adherents/radies', ['title' => 'Adhérents radiés', 'rows' => $rows]);
    }

    public function reinstate(int $id)
    {
        if (! $this->isSecretary()) {
            return $this->response->setStatusCode(403)->setBody(view('errors/forbidden'));
        }
        $this->db->table('members')->where(['id' => $id, 'site_id' => $this->auth->siteId(), 'is_radiated' => 1])
            ->update(['is_radiated' => 0, 'radiation_date' => null, 'radiation_motif' => null, 'radiation_by' => null]);

        return redirect()->to(site_url('adherents/' . $id))->with('success', 'Adhérent réintégré.');
    }

    private function rules(): array
    {
        $date = static fn (string $l) => ['rules' => 'permit_empty|valid_date[Y-m-d]', 'errors' => ['valid_date' => "La {$l} n’est pas valide."]];

        return [
            'last_name'   => ['rules' => 'required|max_length[100]', 'errors' => ['required' => 'Indiquez le nom.', 'max_length' => 'Le nom est trop long.']],
            'first_name'  => ['rules' => 'required|max_length[100]', 'errors' => ['required' => 'Indiquez le prénom.', 'max_length' => 'Le prénom est trop long.']],
            'email'       => ['rules' => 'permit_empty|valid_email|max_length[255]', 'errors' => ['valid_email' => 'L’adresse e-mail n’est pas valide.']],
            'phone'       => ['rules' => 'permit_empty|max_length[20]', 'errors' => ['max_length' => 'Le numéro est trop long.']],
            'postal_code' => ['rules' => 'permit_empty|max_length[10]', 'errors' => ['max_length' => 'Le code postal est trop long.']],
            'matricule'   => ['rules' => 'permit_empty|max_length[50]', 'errors' => ['max_length' => 'Le matricule est trop long.']],
            'date_birth'  => $date('date de naissance'),
            'date_entry'  => $date('date d’entrée'),
            'date_adhesion' => $date('date d’adhésion'),
        ];
    }
}
