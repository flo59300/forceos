<?php

namespace App\Controllers;

use App\Domain\Tasks;

/**
 * Tâches : une seule liste pour le délégué ; le secrétaire voit et assigne celles du site.
 */
class Taches extends BaseController
{
    private function tasks(): Tasks
    {
        return new Tasks($this->db);
    }

    private function isSecretary(): bool
    {
        return $this->auth->effectiveRole() === 'secretaire';
    }

    public function index()
    {
        $vue   = $this->isSecretary() && $this->request->getGet('vue') === 'site' ? 'site' : 'moi';
        $list  = $vue === 'site' ? $this->tasks()->forSite((int) $this->auth->siteId()) : $this->tasks()->forUser((int) $this->auth->id());
        $open  = array_values(array_filter($list, static fn ($t) => $t['status'] !== 'fait'));
        $done  = array_values(array_filter($list, static fn ($t) => $t['status'] === 'fait'));

        return $this->page('taches/index', [
            'title' => 'Tâches et questions', 'tab' => 'taches', 'vue' => $vue,
            'open' => $open, 'done' => $done, 'isSecretary' => $this->isSecretary(),
        ]);
    }

    public function new()
    {
        return $this->page('taches/form', [
            'title' => $this->isSecretary() ? 'Assigner une tâche' : 'Ajouter une tâche',
            'task' => null, 'people' => $this->people(), 'isSecretary' => $this->isSecretary(),
        ]);
    }

    public function create()
    {
        $data = $this->validated();
        if ($data === null) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $target = (int) $this->auth->id();
        $who    = (int) $this->request->getPost('user_id');
        if ($this->isSecretary() && $who > 0 && isset($this->people()[$who])) {
            $target = $who;
        }
        $id = $this->tasks()->create((int) $this->auth->siteId(), $target, $data);
        if ($target !== (int) $this->auth->id()) {
            $this->notify($target, 'Nouvelle tâche assignée', $data['title'], '/taches', '📋');
        }

        return redirect()->to(site_url('taches') . '#tache-todo-' . $id)->with('success', 'Tâche ajoutée.');
    }

    public function edit(string $source, int $id)
    {
        $task = $this->authorized($source, $id);

        return $this->page('taches/form', ['title' => 'Modifier la tâche', 'task' => $task + ['source' => $source], 'people' => [], 'isSecretary' => false]);
    }

    public function update(string $source, int $id)
    {
        $this->authorized($source, $id);
        $data = $this->validated();
        if ($data === null) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $this->tasks()->update($source, $id, $data);

        return redirect()->to(site_url('taches') . '#tache-' . $source . '-' . $id)->with('success', 'Tâche modifiée.');
    }

    /** Cocher / décocher. Avec htmx, renvoie seulement la ligne mise à jour. */
    public function toggle(string $source, int $id)
    {
        $task = $this->authorized($source, $id);
        $done = $source === 'perso' ? ! $task['done'] : $task['status'] !== 'fait';
        $this->tasks()->setDone($source, $id, $done);

        if ($this->isHtmx()) {
            $fresh = $this->tasks()->find($source, $id, (int) $this->auth->id(), $this->isSecretary() ? (int) $this->auth->siteId() : null);
            $row   = $source === 'perso'
                ? ['source' => 'perso', 'id' => $id, 'title' => $fresh['title'], 'description' => null, 'priority' => $fresh['priority'], 'status' => $fresh['done'] ? 'fait' : 'a_faire', 'due_date' => $fresh['due_date']]
                : ['source' => 'todo', 'description' => $fresh['description'], 'done_at' => $fresh['completed_at']] + $fresh;

            return view('taches/_row', ['t' => $row, 'announce' => $done ? 'Tâche marquée comme faite.' : 'Tâche remise à faire.', 'showUser' => false]);
        }

        return redirect()->to(site_url('taches'))->with('success', $done ? 'Tâche marquée comme faite.' : 'Tâche remise à faire.');
    }

    public function delete(string $source, int $id)
    {
        $this->authorized($source, $id);
        $this->tasks()->delete($source, $id);

        return redirect()->to(site_url('taches'))->with('success', 'Tâche supprimée.');
    }

    // ------------------------------------------------------------------

    private function authorized(string $source, int $id): array
    {
        if (! in_array($source, ['todo', 'perso'], true)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $task = $this->tasks()->find($source, $id, (int) $this->auth->id(), $this->isSecretary() ? (int) $this->auth->siteId() : null);
        if (! $task) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $task;
    }

    private function validated(): ?array
    {
        $rules = [
            'title'       => ['label' => 'Titre', 'rules' => 'required|max_length[255]', 'errors' => ['required' => 'Indiquez un titre.', 'max_length' => 'Le titre est trop long (255 caractères au plus).']],
            'description' => ['label' => 'Détail', 'rules' => 'permit_empty|max_length[500]', 'errors' => ['max_length' => 'Le détail est trop long (500 caractères au plus).']],
            'priority'    => ['label' => 'Priorité', 'rules' => 'required|in_list[haute,normale,basse]', 'errors' => ['in_list' => 'Choisissez une priorité.', 'required' => 'Choisissez une priorité.']],
            'due_date'    => ['label' => 'Échéance', 'rules' => 'permit_empty|valid_date[Y-m-d]', 'errors' => ['valid_date' => 'La date d’échéance n’est pas valide.']],
        ];
        if (! $this->validate($rules)) {
            return null;
        }

        return $this->validator->getValidated() + ['description' => '', 'due_date' => ''];
    }

    /** Personnes du site à qui le secrétaire peut assigner une tâche (Super Admin exclu). */
    private function people(): array
    {
        if (! $this->isSecretary()) {
            return [];
        }
        $out = [];
        foreach ($this->db->table('users')->select("id, CONCAT(first_name, ' ', last_name) AS name, role")
            ->where(['site_id' => $this->auth->siteId(), 'active' => 1])->where('role !=', 'super_admin')
            ->orderBy('last_name')->get()->getResultArray() as $u) {
            $out[(int) $u['id']] = $u['name'];
        }

        return $out;
    }
}
