<?php

namespace App\Controllers;

use App\Domain\Feeds;

/**
 * Fil d'actualité : lecture pour tous, choix des flux pour le secrétaire.
 */
class Actualites extends BaseController
{
    private function feeds(): Feeds
    {
        return new Feeds($this->db, (int) $this->auth->siteId());
    }

    public function index()
    {
        $manage = $this->auth->canConfigure();
        $tab    = $manage && $this->request->getGet('onglet') === 'flux' ? 'flux' : 'articles';
        $only   = preg_match('/^[a-f0-9]{32}$/', (string) $this->request->getGet('flux')) ? (string) $this->request->getGet('flux') : null;
        $feeds  = $this->feeds()->all();

        return $this->page('actualites/index', [
            'title' => 'Fil d’actualité', 'tab' => $tab, 'canManage' => $manage, 'feeds' => $feeds, 'only' => $only,
            'data' => $tab === 'articles' ? $this->feeds()->items($only) : ['items' => [], 'errors' => []],
        ]);
    }

    public function add()
    {
        $title = trim((string) $this->request->getPost('feed_title'));
        $url   = trim((string) $this->request->getPost('feed_url'));
        $back  = site_url('actualites') . '?onglet=flux';
        $errors = [];
        if ($title === '' || mb_strlen($title) > 255) {
            $errors['feed_title'] = 'Indiquez un nom pour ce flux (255 caractères au plus).';
        }
        if (! Feeds::validUrl($url)) {
            $errors['feed_url'] = 'L’adresse doit commencer par http:// ou https://.';
        }
        if ($errors) {
            return redirect()->to($back)->withInput()->with('errors', $errors);
        }
        $this->feeds()->add($title, $url);

        return redirect()->to($back)->with('success', 'Flux « ' . $title . ' » ajouté.');
    }

    public function toggle(int $id)
    {
        $s = $this->feeds()->toggle($id);
        if ($s === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return redirect()->to(site_url('actualites') . '?onglet=flux')->with('success', $s ? 'Flux réactivé.' : 'Flux mis en pause.');
    }

    public function delete(int $id)
    {
        if (! $this->feeds()->delete($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return redirect()->to(site_url('actualites') . '?onglet=flux')->with('success', 'Flux supprimé.');
    }
}
