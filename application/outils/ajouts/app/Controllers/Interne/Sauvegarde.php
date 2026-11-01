<?php
/**
 * ForceOS : export SQL en flux pour la sauvegarde chiffrée
 * (appelé uniquement par le processus principal d'Electron)
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Le dump réutilise celui de FO Gestion (Updater::dumpSql). Il commence par
 * la clé maîtresse du poste, chiffrée ensuite avec tout le reste par la phrase
 * de passe côté Electron, et se termine par un marqueur : sans lui, Electron
 * annule la sauvegarde.
 */
declare(strict_types=1);

namespace App\Controllers\Interne;

use App\Libraries\Updater;
use CodeIgniter\Controller;
use Throwable;

final class Sauvegarde extends Controller
{
    public function exporter()
    {
        $titulaire = forceos_consommer_ticket($this->request->getHeaderLine('X-ForceOS-Ticket'), 'export');
        if ($titulaire === null) {
            return $this->response->setStatusCode(403)->setJSON(['erreur' => 'Ticket invalide ou expiré.']);
        }
        set_time_limit(0);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/sql; charset=utf-8');
        header('Cache-Control: no-store');

        $ecrire = static function (string $s): void {
            echo $s;
            flush();
        };
        try {
            $ecrire("-- Sauvegarde ForceOS : FO Gestion " . FO_VERSION . ", " . date('c') . "\n");
            $ecrire("-- Logiciel conçu et développé par Florian Bigotte\n");
            $ecrire('-- FORCEOS-CLE-MAITRESSE: ' . getenv('FORCEOS_CLE_MAITRESSE') . "\n");
            $stats = (new Updater(db_connect()))->dumpSql($ecrire);
            $ecrire("-- FIN DE SAUVEGARDE FORCEOS\n");
            log_message('notice', '[ForceOS] Sauvegarde exportée par l’utilisateur {u} : {t} tables, {l} lignes',
                ['u' => $titulaire, 't' => $stats['tables'], 'l' => $stats['rows']]);
        } catch (Throwable $e) {
            // Sans marqueur de fin, Electron annule la sauvegarde
            log_message('error', '[ForceOS] Échec de la sauvegarde : ' . $e->getMessage());
        }
        exit;
    }
}
