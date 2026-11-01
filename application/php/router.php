<?php
/**
 * ForceOS : routeur de FO Gestion, exécuté par php-cgi pour chaque requête
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Aucun serveur n'écoute : Electron remet les requêtes de sa fenêtre au
 * moteur PHP (lib/moteur-php.js), qui sert lui-même les fichiers statiques.
 * Les contrôles ci-dessous restent en place, en seconde barrière.
 *
 * Chaque requête passe ici avant d'atteindre FO Gestion (CodeIgniter 4).
 *   1. Le jeton secret injecté par Electron doit être présent et exact.
 *   2. L'en-tête Host doit valoir exactement 127.0.0.1:<port>
 *      (protection contre le rebond DNS depuis un navigateur).
 *   3. Les adresses internes exigent en plus le jeton interne.
 *   4. Aucun script PHP n'est exécutable directement : seul le
 *      contrôleur frontal de CodeIgniter exécute du PHP.
 */
declare(strict_types=1);

// --- 1 et 2 : jeton et hôte ---------------------------------------------
$jetonAttendu = getenv('FORCEOS_JETON') ?: '';
$jetonRecu    = $_SERVER['HTTP_X_FORCEOS_JETON'] ?? '';
$hoteRecu     = $_SERVER['HTTP_HOST'] ?? '';
$hoteAttendu  = '127.0.0.1:' . ($_SERVER['SERVER_PORT'] ?? '');

if ($jetonAttendu === ''
    || ! hash_equals($jetonAttendu, $jetonRecu)
    || $hoteRecu !== $hoteAttendu) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Accès refusé.';
    return true;
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$chemin = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// --- Point de santé utilisé par main.js au démarrage --------------------
if ($chemin === '/__sante') {
    header('Content-Type: application/json; charset=utf-8');
    $base = 'indisponible';
    try {
        $socket = (string) getenv('database.default.hostname');
        $pdo = new PDO('mysql:unix_socket=' . $socket . ';dbname=' . getenv('database.default.database'),
            (string) getenv('database.default.username'), '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->query('SELECT 1');
        $base = 'ok';
    } catch (Throwable) {
    }
    echo json_encode(['php' => 'ok', 'version' => PHP_VERSION, 'version_id' => PHP_VERSION_ID,
        'sapi' => PHP_SAPI, 'upload_progress' => (string) ini_get('session.upload_progress.enabled'), 'base' => $base]);
    return true;
}

// --- 3 : adresses internes, réservées au processus principal ------------
if (str_starts_with($chemin, '/interne/')) {
    $interneAttendu = getenv('FORCEOS_JETON_INTERNE') ?: '';
    $interneRecu    = $_SERVER['HTTP_X_FORCEOS_INTERNE'] ?? '';
    if ($interneAttendu === '' || ! hash_equals($interneAttendu, $interneRecu)) {
        http_response_code(403);
        return true;
    }
}

// --- 4 : fichiers cachés et scripts PHP directs refusés -----------------
if (preg_match('#/\.|\.php$#i', $chemin)) {
    http_response_code(404);
    return true;
}

// --- Fichiers statiques de public/ ----------------------------------------
$racine  = realpath($_SERVER['DOCUMENT_ROOT']);
$fichier = realpath($racine . $chemin);
if ($fichier !== false
    && str_starts_with($fichier, $racine . DIRECTORY_SEPARATOR)
    && is_file($fichier)) {
    // Les fichiers statiques sont servis par Electron, jamais par PHP
    http_response_code(404);
    return true;
}

// --- Tout le reste : contrôleur frontal de CodeIgniter 4 -----------------
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $racine . '/index.php';
chdir($racine);
require $racine . '/index.php';
return true;
