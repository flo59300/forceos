<?php
/**
 * ForceOS : restauration d'une sauvegarde par lots
 * (appelé uniquement par le processus principal d'Electron)
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 *   POST /interne/restauration/debut    ticket vérifié, instantané chiffré, base vidée
 *   POST /interne/restauration/lot      instructions SQL (liste blanche des dumps)
 *   POST /interne/restauration/fin      contrôle du marqueur, migrations en attente
 *   POST /interne/restauration/annuler  retour à l'instantané
 */
declare(strict_types=1);

namespace App\Controllers\Interne;

use App\Libraries\Forceos\Instantanes;
use App\Libraries\Updater;
use CodeIgniter\Controller;
use RuntimeException;
use Throwable;

final class Restauration extends Controller
{
    private function fichierSession(): string
    {
        return rtrim((string) getenv('FORCEOS_DONNEES'), '/') . '/tmp/restauration.json';
    }

    private function json(array $d, int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON($d)->setHeader('Cache-Control', 'no-store');
    }

    /** Session de restauration en cours, authentifiée par son propre jeton. */
    private function session(): array
    {
        $s = json_decode((string) @file_get_contents($this->fichierSession()), true);
        $jeton = $this->request->getHeaderLine('X-ForceOS-Restauration');
        if (! is_array($s) || $jeton === '' || ! hash_equals((string) $s['jeton'], hash('sha256', $jeton))) {
            throw new RuntimeException('Session de restauration invalide.');
        }
        return $s;
    }

    private function enregistrer(array $s): void
    {
        file_put_contents($this->fichierSession(), json_encode($s), LOCK_EX);
        chmod($this->fichierSession(), 0600);
    }

    public function etape(string $etape)
    {
        set_time_limit(0);
        try {
            $pdo = forceos_pdo();
            switch ($etape) {
                case 'debut':
                    $titulaire = forceos_consommer_ticket($this->request->getHeaderLine('X-ForceOS-Ticket'), 'restauration');
                    if ($titulaire === null) {
                        return $this->json(['erreur' => 'Restauration refusée : ticket expiré ou droits insuffisants.'], 403);
                    }
                    if (is_file($this->fichierSession()) && filemtime($this->fichierSession()) > time() - 3600) {
                        throw new RuntimeException('Une restauration est déjà en cours.');
                    }
                    $instantane = (new Instantanes(db_connect(), $pdo))->creer('avant-restauration');
                    forceos_vider_base($pdo);
                    $jeton = bin2hex(random_bytes(32));
                    $this->enregistrer(['jeton' => hash('sha256', $jeton), 'instantane' => $instantane,
                                        'titulaire' => $titulaire, 'fin' => false]);
                    return $this->json(['jeton' => $jeton]);

                case 'lot':
                    $s = $this->session();
                    forceos_preparer_session_import($pdo);
                    $r = forceos_executer_lignes($pdo, explode("\n", (string) $this->request->getBody()), FORCEOS_INSTRUCTIONS_DUMP);
                    if ($r['fin']) {
                        $s['fin'] = true;
                        $this->enregistrer($s);
                    }
                    return $this->json(['instructions' => $r['instructions']]);

                case 'fin':
                    $s = $this->session();
                    if (! $s['fin']) {
                        throw new RuntimeException('Sauvegarde incomplète : marqueur de fin absent.');
                    }
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                    // Une sauvegarde ancienne est mise au niveau du schéma actuel
                    $faites = [];
                    foreach ((new Updater(db_connect()))->runPending() as $r) {
                        if (! $r['ok']) {
                            throw new RuntimeException("Migration {$r['file']} : {$r['error']}");
                        }
                        $faites[] = 'fo3/' . $r['file'];
                    }
                    $faites = array_merge($faites, forceos_migrations_forceos($pdo));
                    // Les comptes viennent d'être remplacés : toutes les sessions ouvertes
                    // sont fermées, sinon un utilisateur connecté pourrait se retrouver dans
                    // le compte restauré qui porte le même numéro
                    foreach (glob(rtrim((string) getenv('FORCEOS_DONNEES'), '/') . '/writable/session/*') ?: [] as $f) {
                        if (is_file($f) && basename($f) !== 'index.html') {
                            @unlink($f);
                        }
                    }
                    @unlink($this->fichierSession());
                    log_message('notice', '[ForceOS] Sauvegarde restaurée par l’utilisateur {u}', ['u' => $s['titulaire']]);
                    return $this->json(['ok' => true, 'migrations' => $faites]);

                case 'annuler':
                    $s = $this->session();
                    if ($s['instantane']) {
                        (new Instantanes(db_connect(), $pdo))->restaurer($s['instantane']);
                    } else {
                        forceos_vider_base($pdo);
                    }
                    @unlink($this->fichierSession());
                    return $this->json(['ok' => true]);
            }
            return $this->json(['erreur' => 'Étape inconnue.'], 404);
        } catch (Throwable $e) {
            log_message('error', '[ForceOS] Restauration : ' . $e->getMessage());
            return $this->json(['erreur' => $e->getMessage()], 500);
        }
    }
}
