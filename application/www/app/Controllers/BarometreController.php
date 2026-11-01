<?php

namespace App\Controllers;

use App\Domain\Barometre;

/**
 * Baromètre social : tout délégué saisit une remontée, tout le site consulte, le secrétaire peut supprimer.
 */
class BarometreController extends BaseController
{
    private function baro(): Barometre
    {
        return new Barometre($this->db, (int) $this->auth->siteId());
    }

    public function index()
    {
        $tab    = in_array($this->request->getGet('onglet'), ['tableau', 'historique'], true) ? $this->request->getGet('onglet') : 'saisie';
        $months = (int) $this->request->getGet('mois');
        $months = isset(Barometre::PERIODS[$months]) ? $months : 3;
        $b      = $this->baro();
        $entries = $b->entries($months);

        return $this->page('barometre/index', [
            'title' => 'Baromètre social', 'tab' => $tab, 'months' => $months, 'entries' => $entries,
            'stats' => Barometre::stats($entries), 'tags' => $b->tags(), 'secteurs' => $b->secteurs(),
            'canManage' => $this->auth->canConfigure(),
        ]);
    }

    public function add()
    {
        $r = $this->baro()->add(
            (int) $this->auth->id(),
            (int) $this->request->getPost('secteur_id') ?: null,
            (int) $this->request->getPost('ambiance'),
            (array) $this->request->getPost('tags'),
            mb_substr(trim((string) $this->request->getPost('comment')), 0, 2000),
            trim((string) $this->request->getPost('entry_date'))
        );
        if (is_string($r)) {
            return redirect()->to(site_url('barometre'))->withInput()->with('error', $r);
        }

        return redirect()->to(site_url('barometre'))->with('success', 'Remontée enregistrée. Merci.');
    }

    public function delete(int $id)
    {
        if (! $this->baro()->delete($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return redirect()->to(site_url('barometre') . '?onglet=historique')->with('success', 'Remontée supprimée.');
    }
}
