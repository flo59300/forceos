<?php

namespace App\Controllers;

use App\Libraries\Settings;

class AuthController extends BaseController
{
    public function login()
    {
        if ($this->auth->check()) {
            return redirect()->to(site_url('/'));
        }

        $info = $this->request->getGet('au-revoir') ? 'Vous êtes déconnecté. À bientôt.' : null;

        return view('auth/login', ['appName' => Settings::appName(), 'email' => old('email'), 'info' => $info]);
    }

    public function attempt()
    {
        $email    = (string) $this->request->getPost('email');
        $password = (string) $this->request->getPost('password');
        $result   = $this->auth->attempt($email, $password, (bool) $this->request->getPost('remember'));

        if ($result === 'ok') {
            $to = session('_intended') ?: '/';
            session()->remove('_intended');
            if ($this->auth->isSuperAdmin() && ! Settings::isStandalone()) {
                $to = '/plateforme';
            }

            return redirect()->to(site_url($to))->withCookies();
        }

        $msg = $result === 'locked'
            ? 'Trop de tentatives. Pour votre sécurité, la connexion est bloquée pendant 15 minutes.'
            : 'Adresse e-mail ou mot de passe incorrect.';

        return redirect()->back()->withInput()->with('error', $msg);
    }

    public function logout()
    {
        $this->auth->logout();

        return redirect()->to(site_url('connexion?au-revoir=1'))->withCookies();
    }
}
