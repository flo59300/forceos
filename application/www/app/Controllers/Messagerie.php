<?php

namespace App\Controllers;

use App\Domain\Messages;

/**
 * Messagerie interne : conversations à gauche, fil à droite (un seul panneau sur téléphone).
 */
class Messagerie extends BaseController
{
    private function msg(): Messages
    {
        return new Messages($this->db, (int) $this->auth->siteId(), (int) $this->auth->id());
    }

    public function index()
    {
        return $this->page('messages/index', ['title' => 'Messages', 'conversations' => $this->msg()->conversations(), 'contacts' => $this->msg()->contacts(), 'other' => null, 'thread' => []]);
    }

    public function thread(int $userId)
    {
        $m     = $this->msg();
        $other = $m->contact($userId) ?? throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $thread = $m->thread($userId);

        return $this->page('messages/index', [
            'title' => 'Messages avec ' . $other['name'], 'conversations' => $m->conversations(), 'contacts' => [],
            'other' => $other, 'thread' => $thread, 'canDelete' => $this->auth->canConfigure(),
        ]);
    }

    public function send(int $userId)
    {
        $m     = $this->msg();
        $other = $m->contact($userId) ?? throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $body  = trim((string) $this->request->getPost('body'));
        if ($body === '' || mb_strlen($body) > Messages::MAX_LENGTH) {
            return redirect()->to(site_url('messages/' . $userId) . '#nouveau')->withInput()->with('errors', ['body' => $body === '' ? 'Écrivez un message.' : 'Le message est trop long.']);
        }
        $id = $m->send($userId, $body);
        $this->notify($userId, 'Message de ' . session('user_name'), mb_substr($body, 0, 120), '/messages/' . $this->auth->id(), '💬');

        return redirect()->to(site_url('messages/' . $userId) . '#message-' . $id);
    }

    public function start()
    {
        $to = (int) $this->request->getPost('to');

        return $this->msg()->contact($to) ? redirect()->to(site_url('messages/' . $to) . '#nouveau') : redirect()->to(site_url('messages'))->with('error', 'Choisissez un destinataire.');
    }

    public function delete(int $id)
    {
        if (! $this->auth->canConfigure()) {
            return $this->response->setStatusCode(403)->setBody(view('errors/forbidden'));
        }
        $this->msg()->delete($id);
        $back = (int) $this->request->getPost('back');

        return redirect()->to(site_url('messages' . ($back ? '/' . $back : '')))->with('success', 'Message supprimé.');
    }
}
