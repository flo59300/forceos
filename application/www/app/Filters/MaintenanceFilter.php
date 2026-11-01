<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Page de maintenance :
 *  - pendant une mise à jour (fichier .maintenance, 15 minutes au plus) ;
 *  - quand le Super Admin active le mode maintenance (réglage maintenance_mode, partagé avec la 2.x) :
 *    seuls la connexion et les comptes Super Admin passent.
 */
class MaintenanceFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $flag = ROOTPATH . '.maintenance';
        if (! is_file($flag)) {
            if (\App\Libraries\Settings::get('maintenance_mode', '0') !== '1' || is_cli()) {
                return null;
            }
            $path = trim($request->getUri()->getPath(), '/');
            if (in_array($path, ['connexion', 'deconnexion'], true) || str_starts_with($path, 'assets') || service('auth')->isSuperAdmin()) {
                return null;
            }

            return service('response')->setStatusCode(503)->setHeader('Retry-After', '600')->setBody(view('errors/maintenance'));
        }
        if ((int) @file_get_contents($flag) < time() - 900) {
            @unlink($flag);

            return null;
        }

        return service('response')->setStatusCode(503)->setHeader('Retry-After', '60')->setBody(view('errors/maintenance'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
