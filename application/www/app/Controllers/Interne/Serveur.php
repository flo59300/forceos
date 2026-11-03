<?php
/**
 * ForceOS : contrôle du ticket d'ouverture du mode serveur
 * (appelé uniquement par le processus principal d'Electron)
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 */
declare(strict_types=1);

namespace App\Controllers\Interne;

use CodeIgniter\Controller;

final class Serveur extends Controller
{
    public function ticket()
    {
        $d = $this->request->getJSON(true) ?? [];
        $titulaire = forceos_consommer_ticket((string) ($d['ticket'] ?? ''), 'serveur');
        if ($titulaire === null) {
            return $this->response->setStatusCode(403)->setJSON(['erreur' => 'Ticket invalide ou expiré.']);
        }
        log_message('notice', '[ForceOS] Mode serveur ouvert par l\'utilisateur {u}', ['u' => $titulaire]);
        return $this->response->setJSON(['ok' => true]);
    }
}
