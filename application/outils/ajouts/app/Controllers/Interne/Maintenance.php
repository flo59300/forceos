<?php
/**
 * ForceOS : points d'entrée internes appelés par le processus principal
 * d'Electron (jamais par la page). Protégés par le filtre AccesInterne.
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 *   POST /interne/migrer                  base initiale si vide, puis migrations
 *   GET  /interne/etat                    poste configuré ou non
 *   POST /interne/premiere-configuration  section et compte du secrétaire
 *   POST /interne/instantane/restaurer    retour à un instantané
 */
declare(strict_types=1);

namespace App\Controllers\Interne;

use App\Libraries\Forceos\Instantanes;
use App\Libraries\Updater;
use CodeIgniter\Controller;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class Maintenance extends Controller
{
    private function json(array $donnees, int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON($donnees)
            ->setHeader('Cache-Control', 'no-store');
    }

    public function migrer()
    {
        set_time_limit(0);
        $pdo = forceos_pdo();
        $db  = db_connect();
        if ((int) $pdo->query("SELECT GET_LOCK('forceos_migrations', 30)")->fetchColumn() !== 1) {
            return $this->json(['erreur' => 'Des migrations sont déjà en cours.'], 409);
        }
        $instantane = null;
        try {
            // 1. Premier lancement : base initiale
            $base = forceos_installer_base_initiale($pdo);

            // 2. Migrations en attente : application (fo3/…) puis ForceOS (forceos/…)
            $updater    = new Updater($db);
            $enAttente  = $updater->pendingCount();
            $forceos    = glob(FORCEOS_MIGRATIONS . '/[0-9][0-9][0-9]-*.sql') ?: [];
            $faites     = $pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE migration LIKE 'forceos/%'")->fetchColumn();
            if ($enAttente === 0 && count($forceos) <= (int) $faites) {
                return $this->json(['ok' => true, 'base_initiale' => $base['installee'], 'appliquees' => []]);
            }

            // 3. Les modifications de structure ne s'annulent pas : instantané d'abord
            $instantane = (new Instantanes($db, $pdo))->creer('avant-migration');
            $appliquees = [];
            foreach ($updater->runPending() as $r) {
                if (! $r['ok']) {
                    throw new RuntimeException("Migration {$r['file']} : {$r['error']}");
                }
                $appliquees[] = 'fo3/' . $r['file'];
            }
            $appliquees = array_merge($appliquees, forceos_migrations_forceos($pdo));
            log_message('notice', '[ForceOS] Migrations appliquées : {m}', ['m' => implode(', ', $appliquees)]);

            return $this->json(['ok' => true, 'base_initiale' => $base['installee'],
                                'appliquees' => $appliquees, 'instantane' => $instantane]);
        } catch (Throwable $e) {
            log_message('error', '[ForceOS] Migrations : ' . $e->getMessage());
            return $this->json(['erreur' => $e->getMessage(), 'instantane' => $instantane], 500);
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('forceos_migrations')");
        }
    }

    public function etat()
    {
        return $this->json(['configure' => forceos_est_configure(forceos_pdo()), 'version' => FO_VERSION]);
    }

    public function premiereConfiguration()
    {
        try {
            $r = forceos_premiere_configuration(forceos_pdo(), (array) $this->request->getJSON(true));
            log_message('notice', '[ForceOS] Première configuration : section {c}', ['c' => $r['code']]);
            return $this->json(['ok' => true] + $r);
        } catch (InvalidArgumentException $e) {
            return $this->json(['erreur' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->json(['erreur' => $e->getMessage()], 409);
        }
    }

    public function restaurerInstantane()
    {
        set_time_limit(0);
        try {
            $nom = (string) ($this->request->getJSON(true)['fichier'] ?? '');
            (new Instantanes(db_connect(), forceos_pdo()))->restaurer($nom);
            log_message('notice', '[ForceOS] Instantané restauré : {n}', ['n' => $nom]);
            return $this->json(['ok' => true]);
        } catch (Throwable $e) {
            return $this->json(['erreur' => $e->getMessage()], 500);
        }
    }
}
