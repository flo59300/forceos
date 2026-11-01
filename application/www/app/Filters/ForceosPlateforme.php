<?php
/**
 * ForceOS : neutralisation des fonctions de la Plateforme qui contourneraient
 * la sécurité du poste
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Sur ForceOS :
 *  - les sauvegardes passent par la sauvegarde chiffrée (AES-256, phrase de
 *    passe) : l'export SQL ou CSV en clair et la restauration SQL de la
 *    Plateforme sont fermés ;
 *  - les mises à jour passent par forceos-update (paquet signé par Florian
 *    Bigotte) : l'installateur de la Plateforme est fermé, d'autant que le
 *    dossier de l'application est en lecture seule.
 * Le filtre n'agit que sous ForceOS : sur hub-fo, il ne fait rien.
 */
declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class ForceosPlateforme implements FilterInterface
{
    private const FERMES = [
        'plateforme/sauvegardes' => 'Sur ForceOS, utilisez la sauvegarde chiffrée de l’application : elle protège les données des adhérents par une phrase de passe.',
        'plateforme/mises-a-jour' => 'Sur ForceOS, les mises à jour s’installent avec « Mettre à jour ForceOS » depuis une clé USB signée par Florian Bigotte.',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        if ((string) getenv('FORCEOS_DONNEES') === '') {
            return null;
        }
        $chemin = trim($request->getUri()->getPath(), '/');
        foreach (self::FERMES as $prefixe => $message) {
            if ($chemin === $prefixe || str_starts_with($chemin, $prefixe . '/')) {
                log_message('notice', '[ForceOS] Accès refusé à {p}', ['p' => $chemin]);
                $cible = $prefixe === 'plateforme/sauvegardes' ? 'forceos/sauvegarde' : 'plateforme';
                return redirect()->to(site_url($cible))->with('error', $message);
            }
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
