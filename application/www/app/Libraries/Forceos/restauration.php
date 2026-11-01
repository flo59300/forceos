<?php
/**
 * ForceOS : exécution contrôlée de fichiers SQL (base initiale, restauration)
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Convention : une instruction se termine par « ; » en fin de ligne.
 * Les dumps ForceOS et la base initiale respectent cette règle.
 */
declare(strict_types=1);

/** Instructions qu'un export ForceOS peut contenir, et rien d'autre. */
const FORCEOS_INSTRUCTIONS_DUMP = '/^(SET\s+(NAMES|FOREIGN_KEY_CHECKS|UNIQUE_CHECKS|SQL_MODE)\b'
    . '|DROP\s+(TABLE|VIEW)\s+IF\s+EXISTS\s'
    . '|CREATE\s+TABLE\s'
    . '|INSERT\s+INTO\s'
    . '|CREATE\s+(ALGORITHM=\w+\s+)?(SQL\s+SECURITY\s+\w+\s+)?VIEW\s'
    . '|COMMIT\b)/i';

if (! function_exists('forceos_ident')) {
    /** Identifiant SQL protégé par accents graves. */
    function forceos_ident(string $nom): string
    {
        return '`' . str_replace('`', '``', $nom) . '`';
    }
}

function forceos_preparer_session_import(PDO $pdo): void
{
    $pdo->exec('SET NAMES utf8mb4');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('SET UNIQUE_CHECKS = 0');
    $pdo->exec("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
}

/**
 * Exécute les instructions contenues dans une suite de lignes.
 * @param ?string $motifAutorise  null pour un fichier livré dans le paquet signé
 * @return array{instructions:int, fin:bool}
 */
function forceos_executer_lignes(PDO $pdo, iterable $lignes, ?string $motifAutorise): array
{
    $tampon = '';
    $nombre = 0;
    $fin = false;

    foreach ($lignes as $ligne) {
        if ($tampon === '') {
            if (str_starts_with($ligne, '-- FIN DE SAUVEGARDE FORCEOS')) {
                $fin = true;
                continue;
            }
            if (trim($ligne) === '' || str_starts_with(ltrim($ligne), '--')) {
                continue;
            }
        }
        $tampon .= $ligne . "\n";
        if (str_ends_with(rtrim($ligne), ';')) {
            $instruction = rtrim(trim($tampon), ';');
            $tampon = '';
            if ($motifAutorise !== null && ! preg_match($motifAutorise, $instruction)) {
                throw new RuntimeException('Instruction non autorisée : ' . mb_substr($instruction, 0, 60));
            }
            $pdo->exec($instruction);
            $nombre++;
        }
    }
    if (trim($tampon) !== '') {
        throw new RuntimeException('Instruction incomplète en fin de fichier.');
    }
    return ['instructions' => $nombre, 'fin' => $fin];
}

/** Supprime toutes les tables et vues de la base (après instantané). */
function forceos_vider_base(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as [$nom, $type]) {
        $pdo->exec(($type === 'VIEW' ? 'DROP VIEW IF EXISTS ' : 'DROP TABLE IF EXISTS ') . forceos_ident($nom));
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}
