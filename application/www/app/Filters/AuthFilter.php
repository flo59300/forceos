<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Exige une session valide. Une requête htmx reçoit un en-tête de redirection
 * au lieu d'une page de connexion insérée dans un fragment.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (service('auth')->check()) {
            return null;
        }
        session()->set('_intended', current_url(true)->getPath());
        if ($request->hasHeader('HX-Request')) {
            return service('response')->setStatusCode(401)->setHeader('HX-Redirect', site_url('connexion'));
        }

        return redirect()->to(site_url('connexion'))->with('info', 'Votre session a expiré. Reconnectez-vous.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
