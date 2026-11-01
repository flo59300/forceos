<?php
/**
 * ForceOS : chiffrement des coordonnées personnelles des adhérents (RGPD)
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Les champs ci-dessous sont chiffrés par Sodium (XSalsa20-Poly1305) avant
 * d'être écrits dans la base, et déchiffrés à la lecture. Ils ne servent à
 * aucune recherche ni aucun calcul en SQL : seul leur caractère vide est
 * testé (« IS NULL »), ce qui reste possible puisqu'une valeur vide est
 * conservée telle quelle (NULL).
 *
 * Restent en clair, car l'application en a besoin en SQL : nom, prénom et
 * matricule (recherche), genre (parité des listes électorales), dates
 * d'entrée et d'adhésion, collège, secteur, métier, tournée.
 *
 * Sans clé maîtresse (FO Gestion utilisée hors ForceOS, sur hub-fo), la
 * classe laisse passer les valeurs sans les toucher.
 */
declare(strict_types=1);

namespace App\Libraries\Forceos;

use RuntimeException;

final class ChampsChiffres
{
    public const CHAMPS = ['email', 'phone', 'address', 'city', 'postal_code', 'date_birth'];
    private const PREFIXE = 'v1:';
    private const CONTEXTE = 'FOmembre';   // 8 caractères, exigé par Sodium
    private const USAGE = 1;

    private static ?string $cle = null;

    public static function actif(): bool
    {
        return (string) getenv('FORCEOS_CLE_MAITRESSE') !== '';
    }

    private static function cle(): string
    {
        if (self::$cle === null) {
            $maitre = base64_decode((string) getenv('FORCEOS_CLE_MAITRESSE'), true);
            if ($maitre === false || strlen($maitre) !== SODIUM_CRYPTO_KDF_KEYBYTES) {
                throw new RuntimeException('Clé maîtresse ForceOS invalide.');
            }
            self::$cle = sodium_crypto_kdf_derive_from_key(
                SODIUM_CRYPTO_SECRETBOX_KEYBYTES, self::USAGE, self::CONTEXTE, $maitre
            );
            sodium_memzero($maitre);
        }
        return self::$cle;
    }

    public static function chiffrerValeur(mixed $v): mixed
    {
        if ($v === null || $v === '' || ! self::actif()) {
            return $v === '' ? null : $v;
        }
        $v = (string) $v;
        if (str_starts_with($v, self::PREFIXE)) {
            return $v;   // déjà chiffrée
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return self::PREFIXE . base64_encode($nonce . sodium_crypto_secretbox($v, $nonce, self::cle()));
    }

    public static function dechiffrerValeur(mixed $v): mixed
    {
        if (! is_string($v) || ! str_starts_with($v, self::PREFIXE)) {
            return $v;   // valeur en clair (ancienne donnée ou hors ForceOS)
        }
        $brut = base64_decode(substr($v, strlen(self::PREFIXE)), true);
        if ($brut === false || strlen($brut) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Coordonnée chiffrée illisible.');
        }
        $clair = sodium_crypto_secretbox_open(
            substr($brut, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($brut, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::cle()
        );
        if ($clair === false) {
            throw new RuntimeException('Coordonnée chiffrée altérée ou clé maîtresse incorrecte.');
        }
        return $clair;
    }

    /** Chiffre les champs sensibles présents dans une ligne à écrire. */
    public static function chiffrer(array $ligne): array
    {
        foreach (self::CHAMPS as $c) {
            if (array_key_exists($c, $ligne)) {
                $ligne[$c] = self::chiffrerValeur($ligne[$c]);
            }
        }
        return $ligne;
    }

    /** Déchiffre les champs sensibles d'une ligne lue (null accepté). */
    public static function dechiffrer(?array $ligne): ?array
    {
        if ($ligne === null) {
            return null;
        }
        foreach (self::CHAMPS as $c) {
            if (array_key_exists($c, $ligne)) {
                $ligne[$c] = self::dechiffrerValeur($ligne[$c]);
            }
        }
        return $ligne;
    }

    /** @param list<array> $lignes */
    public static function dechiffrerTout(array $lignes): array
    {
        return array_map(static fn (array $l) => self::dechiffrer($l), $lignes);
    }
}
