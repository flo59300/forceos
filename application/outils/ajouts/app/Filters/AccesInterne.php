<?php
/**
 * ForceOS : filtre des points d'entrée internes (/interne/…)
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Seul le processus principal d'Electron connaît le jeton interne, tiré au
 * hasard à chaque lancement. Hors ForceOS (jeton absent), tout est refusé.
 */
declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class AccesInterne implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $attendu = (string) getenv('FORCEOS_JETON_INTERNE');
        $recu    = $request->getHeaderLine('X-ForceOS-Interne');
        if ($attendu === '' || ! hash_equals($attendu, $recu)) {
            return service('response')->setStatusCode(403)->setJSON(['erreur' => 'Accès refusé.']);
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
