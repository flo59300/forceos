<?php
/**
 * ForceOS : instantanés chiffrés de la base (avant migration ou restauration)
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * L'export réutilise celui de FO Gestion (Updater::dumpSql). Il est chiffré
 * au fil de l'eau par Sodium (XChaCha20-Poly1305, mode flux) : chaque bloc est
 * authentifié, ainsi que l'ordre et la complétude du fichier. Rien n'est
 * jamais écrit en clair sur le disque.
 *
 * Format : « FOSNAP02 » + en-tête secretstream + blocs [longueur | chiffré].
 */
declare(strict_types=1);

namespace App\Libraries\Forceos;

use App\Libraries\Updater;
use CodeIgniter\Database\BaseConnection;
use Generator;
use PDO;
use RuntimeException;
use Throwable;

final class Instantanes
{
    private const MAGIE      = 'FOSNAP02';
    private const CONSERVES  = 5;
    private const MOTIF_NOM  = '/^\d{8}-\d{6}-[a-z-]{1,40}\.sql\.chiffre$/';
    private const MARQUEUR   = '-- FIN DE SAUVEGARDE FORCEOS';
    private const CONTEXTE   = 'FOinstan';   // 8 caractères, exigé par Sodium
    private const BLOC_MAX   = 16 * 1024 * 1024;

    public function __construct(private BaseConnection $db, private PDO $pdo)
    {
    }

    public static function dossier(): string
    {
        $d = rtrim((string) getenv('FORCEOS_DONNEES'), '/') . '/instantanes';
        if (! is_dir($d) && ! mkdir($d, 0700, true) && ! is_dir($d)) {
            throw new RuntimeException('Dossier des instantanés inaccessible.');
        }
        return $d;
    }

    private static function cle(): string
    {
        $maitre = base64_decode((string) getenv('FORCEOS_CLE_MAITRESSE'), true);
        if ($maitre === false || strlen($maitre) !== SODIUM_CRYPTO_KDF_KEYBYTES) {
            throw new RuntimeException('Clé maîtresse ForceOS invalide.');
        }
        $cle = sodium_crypto_kdf_derive_from_key(
            SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, 3, self::CONTEXTE, $maitre
        );
        sodium_memzero($maitre);
        return $cle;
    }

    /** Crée un instantané chiffré. Renvoie son nom, ou null si la base est vide. */
    public function creer(string $motif): ?string
    {
        if ($this->pdo->query('SHOW TABLES')->fetchAll() === []) {
            return null;
        }
        $dossier = self::dossier();
        $nom     = date('Ymd-His') . '-' . preg_replace('/[^a-z-]/', '', $motif) . '.sql.chiffre';
        $partiel = "{$dossier}/{$nom}.partiel";

        $f = fopen($partiel, 'xb');
        chmod($partiel, 0600);
        $cle = self::cle();
        [$etat, $entete] = sodium_crypto_secretstream_xchacha20poly1305_init_push($cle);
        sodium_memzero($cle);
        fwrite($f, self::MAGIE . $entete);

        $ecrire = static function (string $clair, int $etiquette) use (&$etat, $f): void {
            $c = sodium_crypto_secretstream_xchacha20poly1305_push($etat, $clair, '', $etiquette);
            fwrite($f, pack('N', strlen($c)) . $c);
        };
        try {
            $tampon = '';
            (new Updater($this->db))->dumpSql(static function (string $s) use (&$tampon, $ecrire): void {
                $tampon .= $s;
                if (strlen($tampon) >= 1024 * 1024) {
                    $ecrire($tampon, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
                    $tampon = '';
                }
            });
            $ecrire($tampon . self::MARQUEUR . "\n", SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
            fclose($f);
            rename($partiel, "{$dossier}/{$nom}");
        } catch (Throwable $e) {
            fclose($f);
            @unlink($partiel);
            throw $e;
        }

        $tous = glob("{$dossier}/*.sql.chiffre") ?: [];
        rsort($tous);
        foreach (array_slice($tous, self::CONSERVES) as $ancien) {
            @unlink($ancien);
        }
        return $nom;
    }

    /** Lit un instantané ligne par ligne, en vérifiant chaque bloc. */
    private static function lignes(string $chemin): Generator
    {
        $f = fopen($chemin, 'rb');
        try {
            if (fread($f, 8) !== self::MAGIE) {
                throw new RuntimeException('Fichier d’instantané invalide.');
            }
            $cle  = self::cle();
            $etat = sodium_crypto_secretstream_xchacha20poly1305_init_pull(
                (string) fread($f, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES), $cle
            );
            sodium_memzero($cle);
            $reste = '';
            $final = false;
            while (! $final && strlen($l = (string) fread($f, 4)) === 4) {
                $longueur = unpack('N', $l)[1];
                if ($longueur > self::BLOC_MAX) {
                    throw new RuntimeException('Instantané corrompu.');
                }
                $r = sodium_crypto_secretstream_xchacha20poly1305_pull($etat, (string) fread($f, $longueur));
                if ($r === false) {
                    throw new RuntimeException('Instantané altéré ou clé incorrecte.');
                }
                [$clair, $etiquette] = $r;
                $final  = $etiquette === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
                $lignes = explode("\n", $reste . $clair);
                $reste  = array_pop($lignes);
                yield from $lignes;
            }
            if (! $final) {
                throw new RuntimeException('Instantané tronqué.');
            }
            if ($reste !== '') {
                yield $reste;
            }
        } finally {
            fclose($f);
        }
    }

    /** Remplace toute la base par le contenu d'un instantané. */
    public function restaurer(string $nom): void
    {
        if (! preg_match(self::MOTIF_NOM, $nom)) {
            throw new RuntimeException('Nom d’instantané invalide.');
        }
        $chemin = self::dossier() . '/' . $nom;
        if (! is_file($chemin)) {
            throw new RuntimeException('Instantané introuvable.');
        }
        // Lecture intégrale préalable : un fichier altéré est refusé avant de toucher à la base
        foreach (self::lignes($chemin) as $_) {
        }
        forceos_vider_base($this->pdo);
        forceos_preparer_session_import($this->pdo);
        $r = forceos_executer_lignes($this->pdo, self::lignes($chemin), FORCEOS_INSTRUCTIONS_DUMP);
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        if (! $r['fin']) {
            throw new RuntimeException('Instantané incomplet.');
        }
    }

    /** @return list<string> */
    public function liste(): array
    {
        $noms = array_map('basename', glob(self::dossier() . '/*.sql.chiffre') ?: []);
        rsort($noms);
        return $noms;
    }
}
