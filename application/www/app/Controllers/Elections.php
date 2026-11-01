<?php

namespace App\Controllers;

use App\Domain\Cse;

/**
 * Élections CSE (secrétaire) : simulations et résultats officiels, dans un même éditeur.
 */
class Elections extends BaseController
{
    private function cse(): Cse
    {
        return new Cse($this->db, (int) $this->auth->siteId());
    }

    public function index()
    {
        return $this->page('elections/index', [
            'title' => 'Élections CSE', 'sims' => $this->cse()->list(false), 'officials' => $this->cse()->list(true),
        ]);
    }

    /** Éditeur : nouvelle simulation, nouveau résultat officiel (?officiel=1) ou enregistrement existant. */
    public function editor(?int $id = null)
    {
        $official = (bool) $this->request->getGet('officiel');
        $sim      = null;
        $state    = Cse::blank();
        if ($id) {
            $l = $this->cse()->load($id);
            if (! $l) {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }
            [$sim, $state] = [$l['sim'], $l['state']];
            $official      = (bool) $sim['is_official'];
        }
        if ($copy = (int) $this->request->getGet('copie')) {
            $l = $this->cse()->load($copy);
            if ($l) {
                $state = $l['state'];
            }
        }
        $old = session('_ci_old_input')['post']['state'] ?? null;
        if ($old && is_array($j = json_decode((string) $old, true))) {
            $state = Cse::normalize($j);
        }

        return $this->page('elections/editor', [
            'title' => $sim ? $sim['name'] : ($official ? 'Nouveau résultat officiel' : 'Nouvelle simulation'),
            'sim' => $sim, 'official' => $official, 'state' => $state, 'results' => Cse::compute(Cse::normalize($state)),
        ]);
    }

    /** Résultats recalculés à la volée (fragment htmx). */
    public function compute()
    {
        $st = Cse::normalize((array) json_decode((string) $this->request->getPost('state'), true));

        return view('elections/_results', ['r' => Cse::compute($st), 'state' => $st]);
    }

    public function save(?int $id = null)
    {
        $official = (bool) $this->request->getPost('official');
        $name     = trim((string) $this->request->getPost('name'));
        $date     = trim((string) $this->request->getPost('election_date'));
        $raw      = json_decode((string) $this->request->getPost('state'), true);
        $back     = $id ? site_url("elections/{$id}") : site_url('elections/nouvelle') . ($official ? '?officiel=1' : '');
        $errors   = [];
        if ($name === '') {
            $errors['name'] = 'Donnez un nom à ' . ($official ? 'ce résultat.' : 'cette simulation.');
        }
        if ($official && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors['election_date'] = 'Indiquez la date de l’élection.';
        }
        if (! is_array($raw) || empty($raw['colleges']) || empty($raw['syndicats'])) {
            $errors['state'] = 'Il faut au moins un collège et un syndicat.';
        }
        if ($errors) {
            return redirect()->to($back)->withInput()->with('errors', $errors);
        }
        $newId = $this->cse()->save(Cse::normalize($raw), $name, $official, $official ? $date : null, (int) $this->auth->id(), (int) $id);
        if (! $newId) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return redirect()->to(site_url("elections/{$newId}"))->with('success', $official ? 'Résultat enregistré.' : 'Simulation enregistrée.');
    }

    public function delete(int $id)
    {
        if (! $this->cse()->delete($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return redirect()->to(site_url('elections'))->with('success', 'Supprimé.');
    }

    /** Version imprimable des résultats. */
    public function print(int $id)
    {
        $l = $this->cse()->load($id);
        if (! $l) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->page('elections/print', ['title' => $l['sim']['name'], 'sim' => $l['sim'], 'r' => Cse::compute($l['state']), 'state' => $l['state']]);
    }
}
