<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Notes personnelles (onglet « Notes » de pages/outils.php en 2.x) : visibles du seul auteur.
 * Même table user_notes, même ordre (épinglées puis dernières modifiées).
 */
class Notes extends BaseController
{
    public const CATEGORIES = ['terrain' => 'Terrain', 'suivi' => 'Suivi d’un salarié', 'personnel' => 'Personnel', 'reunion' => 'Réunion'];

    private function find(int $id): array
    {
        $n = $this->db->table('user_notes')->where(['id' => $id, 'user_id' => $this->auth->id()])->get()->getRowArray();
        if (! $n) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $n;
    }

    public function index()
    {
        $cat = (string) $this->request->getGet('categorie');
        $q   = trim((string) $this->request->getGet('q'));
        $b   = $this->db->table('user_notes')->where('user_id', $this->auth->id());
        if (isset(self::CATEGORIES[$cat])) {
            $b->where('category', $cat);
        }
        if ($q !== '') {
            $b->groupStart()->like('title', $q)->orLike('content', $q)->groupEnd();
        }
        $edit = ($id = (int) $this->request->getGet('modifier')) ? $this->find($id) : null;

        return $this->page('notes/index', [
            'title' => 'Mes notes', 'notes' => $b->orderBy('pinned', 'DESC')->orderBy('updated_at', 'DESC')->get()->getResultArray(),
            'edit' => $edit, 'cat' => $cat, 'q' => $q,
            'total' => $this->db->table('user_notes')->where('user_id', $this->auth->id())->countAllResults(),
        ]);
    }

    public function create()
    {
        return $this->save(0);
    }

    public function update(int $id)
    {
        $this->find($id);

        return $this->save($id);
    }

    private function save(int $id)
    {
        $rules = [
            'title'    => ['rules' => 'required|max_length[255]', 'errors' => ['required' => 'Indiquez un titre.', 'max_length' => 'Le titre est trop long (255 caractères au plus).']],
            'content'  => ['rules' => 'permit_empty|max_length[20000]', 'errors' => ['max_length' => 'La note est trop longue.']],
            'category' => ['rules' => 'required|in_list[' . implode(',', array_keys(self::CATEGORIES)) . ']', 'errors' => ['required' => 'Choisissez une catégorie.', 'in_list' => 'Choisissez une catégorie.']],
        ];
        if (! $this->validate($rules)) {
            return redirect()->to(site_url('notes') . ($id ? '?modifier=' . $id : '') . '#formulaire')->withInput()->with('errors', $this->validator->getErrors());
        }
        $d = [
            'title' => trim((string) $this->request->getPost('title')),
            'content' => trim((string) $this->request->getPost('content')) ?: null,
            'category' => (string) $this->request->getPost('category'),
        ];
        if ($id) {
            $this->db->table('user_notes')->where(['id' => $id, 'user_id' => $this->auth->id()])->set('updated_at', 'NOW()', false)->update($d);
        } else {
            $this->db->table('user_notes')->insert($d + ['user_id' => $this->auth->id()]);
            $id = (int) $this->db->insertID();
        }

        return redirect()->to(site_url('notes') . '#note-' . $id)->with('success', 'Note enregistrée.');
    }

    public function pin(int $id)
    {
        $n = $this->find($id);
        // la 2.x ne touche pas updated_at en épinglant : on garde ce comportement
        $this->db->query('UPDATE user_notes SET pinned = NOT pinned, updated_at = updated_at WHERE id = ?', [$id]);

        return redirect()->to(site_url('notes') . '#note-' . $id)->with('success', $n['pinned'] ? 'Note désépinglée.' : 'Note épinglée en haut.');
    }

    public function delete(int $id)
    {
        $this->find($id);
        $this->db->table('user_notes')->where(['id' => $id, 'user_id' => $this->auth->id()])->delete();

        return redirect()->to(site_url('notes'))->with('success', 'Note supprimée.');
    }
}
