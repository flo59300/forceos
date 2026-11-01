<?php

namespace App\Controllers;

use App\Domain\Questions;

/**
 * Questions au secrétaire. Le délégué pose et suit ses questions ;
 * le secrétaire voit celles du site, répond et change leur statut.
 */
class QuestionsController extends BaseController
{
    private function q(): Questions
    {
        return new Questions($this->db);
    }

    private function isSecretary(): bool
    {
        return $this->auth->effectiveRole() === 'secretaire';
    }

    public function index()
    {
        $filter = in_array($this->request->getGet('statut'), ['ouvertes', 'repondues'], true) ? $this->request->getGet('statut') : null;
        $author = $this->isSecretary() ? null : (int) $this->auth->id();
        $site   = (int) $this->auth->siteId();

        return $this->page('questions/index', [
            'title' => 'Tâches et questions', 'tab' => 'questions', 'filter' => $filter,
            'questions' => $this->q()->list($site, $author, $filter),
            'counts' => $this->q()->counts($site, $author),
            'isSecretary' => $this->isSecretary(),
        ]);
    }

    public function new()
    {
        return $this->page('questions/form', ['title' => 'Poser une question']);
    }

    public function create()
    {
        $rules = [
            'subject'  => ['rules' => 'required|max_length[200]', 'errors' => ['required' => 'Indiquez le sujet de la question.', 'max_length' => 'Le sujet est trop long (200 caractères au plus).']],
            'body'     => ['rules' => 'required|max_length[5000]', 'errors' => ['required' => 'Détaillez votre question.', 'max_length' => 'La question est trop longue.']],
            'priority' => ['rules' => 'required|in_list[normale,urgente]', 'errors' => ['required' => 'Choisissez une priorité.', 'in_list' => 'Choisissez une priorité.']],
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $d    = $this->validator->getValidated();
        $site = (int) $this->auth->siteId();
        $this->db->table('delegate_questions')->insert([
            'site_id' => $site, 'author_id' => $this->auth->id(),
            'subject' => $d['subject'], 'body' => $d['body'], 'priority' => $d['priority'],
        ]);
        $id = (int) $this->db->insertID();

        $title = ($d['priority'] === 'urgente' ? 'Question urgente de ' : 'Question de ') . session('user_name');
        foreach ($this->db->table('users')->select('id')->where(['site_id' => $site, 'role' => 'secretaire', 'active' => 1])->where('id !=', $this->auth->id())->get()->getResultArray() as $s) {
            $this->notify((int) $s['id'], $title, mb_substr($d['subject'], 0, 120), '/questions?statut=ouvertes', $d['priority'] === 'urgente' ? '🔴' : '❓');
        }

        return redirect()->to(site_url('questions') . '#question-' . $id)->with('success', 'Votre question a été envoyée au secrétaire.');
    }

    public function respond(int $id)
    {
        if (! $this->isSecretary()) {
            return $this->response->setStatusCode(403)->setBody(view('errors/forbidden'));
        }
        $question = $this->q()->find($id, (int) $this->auth->siteId());
        if (! $question) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $rules = [
            'response' => ['rules' => 'required|max_length[5000]', 'errors' => ['required' => 'Rédigez une réponse.']],
            'status'   => ['rules' => 'required|in_list[en_cours,resolue]', 'errors' => ['in_list' => 'Choisissez un statut.', 'required' => 'Choisissez un statut.']],
        ];
        if (! $this->validate($rules)) {
            return redirect()->to(site_url('questions') . '#question-' . $id)->withInput()->with('errors', $this->validator->getErrors())->with('open_reply', $id);
        }
        $d = $this->validator->getValidated();
        $this->db->table('delegate_questions')->where('id', $id)->update([
            'response' => $d['response'], 'status' => $d['status'],
            'responded_by' => $this->auth->id(), 'responded_at' => date('Y-m-d H:i:s'),
        ]);
        if ((int) $question['author_id'] !== (int) $this->auth->id()) {
            $this->notify((int) $question['author_id'], 'Réponse à votre question', mb_substr($question['subject'], 0, 120), '/questions#question-' . $id, '✅');
        }

        return redirect()->to(site_url('questions') . '#question-' . $id)->with('success', 'Réponse envoyée au délégué.');
    }

    public function status(int $id)
    {
        if (! $this->isSecretary()) {
            return $this->response->setStatusCode(403)->setBody(view('errors/forbidden'));
        }
        $status = (string) $this->request->getPost('status');
        if ($this->q()->find($id, (int) $this->auth->siteId()) && isset(Questions::STATUSES[$status])) {
            $this->db->table('delegate_questions')->where('id', $id)->update(['status' => $status]);
        }

        return redirect()->to(site_url('questions') . '#question-' . $id)->with('success', 'Statut mis à jour.');
    }

    public function delete(int $id)
    {
        $q = $this->q()->find($id, (int) $this->auth->siteId());
        if ($q && ($this->isSecretary() || (int) $q['author_id'] === (int) $this->auth->id())) {
            $this->db->table('delegate_questions')->where('id', $id)->delete();
        }

        return redirect()->to(site_url('questions'))->with('success', 'Question supprimée.');
    }
}
