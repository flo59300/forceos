<?php

namespace App\Controllers;

use App\Domain\Announcements;

/**
 * Panneau d'affichage : tout le site lit, le secrétaire publie et gère.
 */
class Annonces extends BaseController
{
    private function ann(): Announcements
    {
        return new Announcements($this->db, (int) $this->auth->siteId());
    }

    private function canManage(): bool
    {
        return $this->auth->canConfigure();
    }

    public function index()
    {
        $a      = $this->ann();
        $manage = $this->canManage();
        $edit   = null;
        if ($manage && ($id = (int) $this->request->getGet('modifier'))) {
            $edit = $a->find($id);
        }

        return $this->page('annonces/index', [
            'title' => 'Annonces', 'enabled' => $a->enabled(), 'canManage' => $manage,
            'items' => $a->list($manage), 'edit' => $edit,
        ]);
    }

    public function board()
    {
        $on = (bool) $this->request->getPost('enabled');
        $this->ann()->setEnabled($on);

        return redirect()->to(site_url('annonces'))->with('success', $on
            ? 'Panneau d’affichage activé : les annonces sont visibles par tout le site.'
            : 'Panneau d’affichage désactivé : les annonces ne sont plus visibles.');
    }

    public function create()
    {
        return $this->save(0);
    }

    public function update(int $id)
    {
        if (! $this->ann()->find($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->save($id);
    }

    private function save(int $id)
    {
        $rules = [
            'title'      => ['rules' => 'required|max_length[255]', 'errors' => ['required' => 'Indiquez un titre.', 'max_length' => 'Le titre est trop long (255 caractères au plus).']],
            'body'       => ['rules' => 'required|max_length[5000]', 'errors' => ['required' => 'Rédigez le contenu de l’annonce.', 'max_length' => 'Le contenu est trop long (5 000 caractères au plus).']],
            'priority'   => ['rules' => 'required|in_list[normale,importante,urgente]', 'errors' => ['required' => 'Choisissez une priorité.', 'in_list' => 'Choisissez une priorité.']],
            'expires_at' => ['rules' => 'permit_empty|valid_date[Y-m-d]', 'errors' => ['valid_date' => 'La date d’expiration n’est pas valide.']],
        ];
        $back = site_url('annonces') . ($id ? '?modifier=' . $id : '') . '#formulaire';
        if (! $this->validate($rules)) {
            return redirect()->to($back)->withInput()->with('errors', $this->validator->getErrors());
        }
        $d = [
            'title' => trim((string) $this->request->getPost('title')), 'body' => trim((string) $this->request->getPost('body')),
            'priority' => (string) $this->request->getPost('priority'), 'pinned' => (bool) $this->request->getPost('pinned'),
            'expires_at' => (string) $this->request->getPost('expires_at'),
        ];
        $a = $this->ann();
        if (! $id && ! $a->enabled()) {
            return redirect()->to(site_url('annonces'))->with('error', 'Activez d’abord le panneau d’affichage.');
        }
        $newId = $a->save($d, (int) $this->auth->id(), $id);
        if (! $id) {
            $this->notifySite(
                ($d['priority'] === 'urgente' ? 'Annonce urgente : ' : 'Annonce : ') . mb_substr($d['title'], 0, 60),
                mb_substr($d['body'], 0, 100), '/annonces#annonce-' . $newId, 'system', $d['priority'] === 'urgente' ? '🚨' : '📢'
            );
        }

        return redirect()->to(site_url('annonces') . '#annonce-' . $newId)->with('success', $id ? 'Annonce modifiée.' : 'Annonce publiée.');
    }

    public function toggle(int $id)
    {
        $state = $this->ann()->toggle($id);
        if ($state === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return redirect()->to(site_url('annonces') . '#annonce-' . $id)->with('success', $state ? 'Annonce de nouveau affichée.' : 'Annonce masquée.');
    }

    public function delete(int $id)
    {
        if (! $this->ann()->delete($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return redirect()->to(site_url('annonces'))->with('success', 'Annonce supprimée.');
    }
}
