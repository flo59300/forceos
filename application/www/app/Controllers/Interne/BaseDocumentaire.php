<?php
/**
 * ForceOS : pont entre le dossier Documents_IA, la base documentaire de
 * FO Gestion et l'assistant IA local (appelé par le processus principal)
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 *   POST /interne/kb/synchroniser  {documents: [{empreinte, titre, texte}]}
 *        Ajoute les documents nouveaux ou modifiés, retire ceux qui ont
 *        disparu du dossier. Les documents déposés par le secrétaire dans
 *        l'application ne sont jamais touchés.
 *   POST /interne/kb/rechercher    {question, nombre}
 *        Recherche de FO Gestion (plein texte puis similarité) : les
 *        passages trouvés sont transmis à Qwen par Electron.
 */
declare(strict_types=1);

namespace App\Controllers\Interne;

use App\Domain\KnowledgeBase;
use App\Libraries\Settings;
use CodeIgniter\Controller;
use RuntimeException;
use Throwable;

final class BaseDocumentaire extends Controller
{
    /** Préfixe des documents synchronisés depuis Documents_IA. */
    private const PREFIXE = 'documents-ia-';

    private function json(array $d, int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON($d)->setHeader('Cache-Control', 'no-store');
    }

    private function base(): KnowledgeBase
    {
        $site = Settings::standaloneSiteId();
        if (! $site) {
            throw new RuntimeException('Poste non configuré.');
        }
        return new KnowledgeBase(db_connect(), $site);
    }

    public function synchroniser()
    {
        set_time_limit(0);
        try {
            $docs = (array) ($this->request->getJSON(true)['documents'] ?? []);
            $kb   = $this->base();
            $db   = db_connect();
            $auteur = (int) ($db->table('users')->select('id')->where('role', 'super_admin')
                ->orderBy('id')->get()->getRow()->id ?? 0) ?: null;

            // Documents synchronisés existants, par empreinte
            $existants = [];
            foreach ($db->table('kb_documents')->select('id, filename')
                ->like('filename', self::PREFIXE, 'after')->get()->getResultArray() as $r) {
                $existants[substr(pathinfo($r['filename'], PATHINFO_FILENAME), strlen(self::PREFIXE))] = (int) $r['id'];
            }

            $voulus = [];
            $ajoutes = 0;
            $erreurs = [];
            $tmp = rtrim((string) getenv('FORCEOS_DONNEES'), '/') . '/tmp';
            if (! is_dir($tmp) && ! mkdir($tmp, 0700, true) && ! is_dir($tmp)) {
                throw new RuntimeException('Dossier temporaire inaccessible.');
            }
            foreach ($docs as $d) {
                $emp   = (string) ($d['empreinte'] ?? '');
                $titre = trim((string) ($d['titre'] ?? ''));
                $texte = (string) ($d['texte'] ?? '');
                if (! preg_match('/^[a-f0-9]{64}$/', $emp) || $titre === '' || trim($texte) === '') {
                    continue;
                }
                $voulus[$emp] = true;
                if (isset($existants[$emp])) {
                    continue;   // déjà indexé, contenu identique
                }
                $fichier = $tmp . '/' . self::PREFIXE . $emp . '.txt';
                file_put_contents($fichier, $texte);
                try {
                    if ($auteur === null) {
                        throw new RuntimeException('Poste non configuré.');
                    }
                    $r = $kb->ingest($auteur, $titre, $fichier, self::PREFIXE . $emp . '.txt', 'general');
                    if ($r['success']) {
                        $ajoutes++;
                    } else {
                        $erreurs[] = "{$titre} : {$r['error']}";
                    }
                } finally {
                    @unlink($fichier);
                }
            }

            $retires = 0;
            foreach ($existants as $emp => $id) {
                if (! isset($voulus[$emp]) && $kb->delete($id)) {
                    $retires++;
                }
            }
            return $this->json(['ok' => true, 'ajoutes' => $ajoutes, 'retires' => $retires,
                                'erreurs' => $erreurs, 'stats' => $kb->stats()]);
        } catch (Throwable $e) {
            log_message('error', '[ForceOS] Synchronisation Documents_IA : ' . $e->getMessage());
            return $this->json(['erreur' => $e->getMessage()], 500);
        }
    }

    public function rechercher()
    {
        try {
            $d        = (array) $this->request->getJSON(true);
            $question = mb_substr(trim((string) ($d['question'] ?? '')), 0, 2000);
            $nombre   = max(1, min(8, (int) ($d['nombre'] ?? 5)));
            $resultats = array_map(static fn (array $c) => [
                'titre'   => $c['doc_title'],
                'source'  => str_starts_with((string) $c['doc_filename'], self::PREFIXE) ? 'Documents_IA' : 'FO Gestion',
                'passage' => $c['chunk_index'] + 1,
                'texte'   => $c['chunk_text'],
                'score'   => round((float) $c['final_score'], 3),
            ], $this->base()->search($question, $nombre));
            return $this->json(['resultats' => $resultats]);
        } catch (Throwable $e) {
            return $this->json(['erreur' => $e->getMessage()], 500);
        }
    }
}
