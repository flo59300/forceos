<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Security\Exceptions\SecurityException;

/**
 * Protection CSRF avec des réponses compréhensibles :
 *  - formulaire classique : retour à la page avec un message en français ;
 *  - requête htmx : erreur 403 et rechargement de la page (jeton périmé), sans fragment parasite.
 */
class CsrfFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }
        try {
            service('security')->verify($request);
        } catch (SecurityException) {
            if ($request->hasHeader('HX-Request')) {
                return service('response')->setStatusCode(403)->setHeader('HX-Refresh', 'true');
            }

            return redirect()->back()->withInput()->with('error', 'La page avait expiré. Vérifiez votre saisie et validez à nouveau.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
