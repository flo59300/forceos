<?php

namespace App\Controllers;

use App\Domain\Users;
use App\Libraries\Notifier;
use App\Libraries\WebPush;

/**
 * Mon profil : coordonnées, mot de passe, notifications push (abonnement de l'appareil et préférences).
 */
class Profil extends BaseController
{
    public function index()
    {
        $u = $this->db->table('users')->where('id', $this->auth->id())->get()->getRowArray();

        return $this->page('profil/index', [
            'title' => 'Mon profil', 'u' => $u, 'vapid' => WebPush::publicKeyConfigured(),
            'devices' => $this->db->table('push_subscriptions')->where('user_id', $u['id'])->countAllResults(),
        ]);
    }

    public function update()
    {
        $uid    = (int) $this->auth->id();
        $first  = trim((string) $this->request->getPost('first_name'));
        $last   = trim((string) $this->request->getPost('last_name'));
        $email  = trim((string) $this->request->getPost('email'));
        $errors = [];
        if ($first === '' || mb_strlen($first) > 100) {
            $errors['first_name'] = 'Indiquez votre prénom.';
        }
        if ($last === '' || mb_strlen($last) > 100) {
            $errors['last_name'] = 'Indiquez votre nom.';
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'L’adresse e-mail n’est pas valide.';
        } elseif ($this->db->table('users')->where('email', $email)->where('id !=', $uid)->countAllResults()) {
            $errors['email'] = 'Cette adresse est déjà utilisée par un autre compte.';
        }
        if ($errors) {
            return redirect()->to(site_url('profil') . '#coordonnees')->withInput()->with('errors', $errors);
        }
        $this->db->table('users')->where('id', $uid)->update(['first_name' => $first, 'last_name' => $last, 'email' => $email]);
        session()->set(['user_name' => $first . ' ' . $last, 'user_first' => $first, 'user_email' => $email]);

        return redirect()->to(site_url('profil'))->with('success', 'Coordonnées enregistrées.');
    }

    public function password()
    {
        $uid  = (int) $this->auth->id();
        $u    = $this->db->table('users')->select('password')->where('id', $uid)->get()->getRow();
        $cur  = (string) $this->request->getPost('current_password');
        $new  = (string) $this->request->getPost('new_password');
        $conf = (string) $this->request->getPost('confirm_password');
        $errors = [];
        if (! password_verify($cur, $u->password)) {
            $errors['current_password'] = 'Le mot de passe actuel est incorrect.';
        }
        if ($e = Users::passwordErrors($new)) {
            $errors['new_password'] = 'Le nouveau mot de passe doit comporter ' . implode(', ', $e) . '.';
        } elseif ($new !== $conf) {
            $errors['confirm_password'] = 'Les deux saisies du nouveau mot de passe sont différentes.';
        }
        if ($errors) {
            return redirect()->to(site_url('profil') . '#mot-de-passe')->with('errors', $errors);
        }
        // Les autres appareils « se souvenir de moi » sont déconnectés (la 2.x les laissait connectés).
        $this->db->table('users')->where('id', $uid)->update(['password' => password_hash($new, PASSWORD_DEFAULT), 'remember_token' => null]);
        session()->regenerate(true);

        return redirect()->to(site_url('profil'))->with('success', 'Mot de passe modifié. Vos autres appareils devront se reconnecter.');
    }

    public function preferences()
    {
        $row = [];
        foreach (Notifier::PREFS as $k => $col) {
            $row[$col] = $this->request->getPost($k) ? 1 : 0;
        }
        $this->db->table('users')->where('id', $this->auth->id())->update($row);
        if ($this->isHtmx()) {
            return $this->response->setStatusCode(204);
        }

        return redirect()->to(site_url('profil') . '#notifications')->with('success', 'Préférences enregistrées.');
    }

    // --- API de l'abonnement push (JSON, jeton CSRF dans l'en-tête) ---

    public function subscribe()
    {
        $in  = (array) $this->request->getJSON(true);
        $end = (string) ($in['endpoint'] ?? '');
        $p   = (string) ($in['keys']['p256dh'] ?? '');
        $a   = (string) ($in['keys']['auth'] ?? '');
        if (! preg_match('#^https://#', $end) || strlen($end) > 500 || ! preg_match('/^[A-Za-z0-9_-]{80,100}$/', $p) || ! preg_match('/^[A-Za-z0-9_-]{16,30}$/', $a)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Abonnement invalide']);
        }
        $uid = (int) $this->auth->id();
        $row = ['p256dh' => $p, 'auth' => $a, 'user_agent' => mb_substr((string) $this->request->getUserAgent(), 0, 255), 'last_used_at' => date('Y-m-d H:i:s')];
        // Un appareil ne reste abonné qu'à un seul compte (la 2.x laissait l'ancien compte recevoir les notifications).
        $this->db->table('push_subscriptions')->where('endpoint', $end)->where('user_id !=', $uid)->delete();
        if ($this->db->table('push_subscriptions')->where(['user_id' => $uid, 'endpoint' => $end])->countAllResults()) {
            $this->db->table('push_subscriptions')->where(['user_id' => $uid, 'endpoint' => $end])->update($row);
        } else {
            $this->db->table('push_subscriptions')->insert($row + ['user_id' => $uid, 'endpoint' => $end]);
        }

        return $this->response->setJSON(['ok' => true, 'count' => $this->db->table('push_subscriptions')->where('user_id', $uid)->countAllResults()]);
    }

    public function unsubscribe()
    {
        $end = (string) (($this->request->getJSON(true) ?? [])['endpoint'] ?? '');
        $uid = (int) $this->auth->id();
        $this->db->table('push_subscriptions')->where(['user_id' => $uid, 'endpoint' => $end])->delete();

        return $this->response->setJSON(['ok' => true, 'count' => $this->db->table('push_subscriptions')->where('user_id', $uid)->countAllResults()]);
    }

    public function test()
    {
        $n = (new Notifier($this->db))->push([(int) $this->auth->id()], 'Test de notification', 'Les notifications fonctionnent sur cet appareil.', '/profil', 'test');

        return $this->response->setJSON(['ok' => $n > 0, 'sent' => $n]);
    }
}
