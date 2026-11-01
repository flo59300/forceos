<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Restreint une route à certains rôles : 'role:secretaire,tresorier'.
 * Le pseudo-rôle « site » désigne tout utilisateur rattaché à un site
 * (tous sauf le Super Admin hors mode autonome).
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth  = service('auth');
        $roles = $arguments ?? [];
        $role  = $auth->effectiveRole();

        // Le Super Admin garde son espace en mode autonome, où il agit par ailleurs comme secrétaire
        $ok = in_array($role, $roles, true) || (in_array('super_admin', $roles, true) && $auth->isSuperAdmin())
            || (in_array('site', $roles, true) && $auth->siteId() !== null && $role !== 'super_admin');

        if ($ok) {
            return null;
        }

        // Accueil du hub demandé par le Super Admin (hors mode autonome) : son tableau de bord
        if ($auth->isSuperAdmin() && trim($request->getUri()->getPath(), '/') === '') {
            return redirect()->to(site_url('plateforme'));
        }

        return service('response')->setStatusCode(403)->setBody(view('errors/forbidden'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
