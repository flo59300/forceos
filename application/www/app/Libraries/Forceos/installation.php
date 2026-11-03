<?php
/**
 * ForceOS : installation de la base initiale et première configuration
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Au premier lancement, la base forceos_gestion est vide : on y applique la
 * base initiale de FO Gestion 3 (mode mono-site, sans compte), puis le
 * secrétaire crée la section et son compte depuis l'écran de première
 * configuration. Ensuite, les mises à jour de app/Database/Updates/ suivent
 * le circuit normal de l'application (table schema_migrations).
 */
declare(strict_types=1);

const FORCEOS_BASE_INITIALE = APPPATH . 'Database/ForceOS/forceos-base-3.0.0-alpha.5.sql';
const FORCEOS_MIGRATIONS    = APPPATH . 'Database/ForceOS/migrations';

/**
 * Applique les migrations propres à ForceOS (Database/ForceOS/migrations),
 * inscrites dans schema_migrations sous le nom « forceos/<fichier> », à côté
 * de celles de l'application (« fo3/… »), sans jamais les mélanger.
 * @return list<string> migrations appliquées
 */
function forceos_migrations_forceos(PDO $pdo, string $dossier = FORCEOS_MIGRATIONS): array
{
    $fichiers = glob($dossier . '/[0-9][0-9][0-9]-*.sql') ?: [];
    sort($fichiers, SORT_STRING);
    $faites = $pdo->query("SELECT migration FROM schema_migrations WHERE migration LIKE 'forceos/%'")
        ->fetchAll(PDO::FETCH_COLUMN);
    $appliquees = [];

    foreach ($fichiers as $f) {
        $nom = 'forceos/' . basename($f);
        if (in_array($nom, $faites, true)) {
            continue;
        }
        $debut = hrtime(true);
        $r = forceos_executer_lignes($pdo, file($f, FILE_IGNORE_NEW_LINES), null);
        $pdo->prepare('INSERT INTO schema_migrations (migration, mode, success, statements, duration_ms) VALUES (?, ?, 1, ?, ?)')
            ->execute([$nom, 'run', $r['instructions'], intdiv(hrtime(true) - $debut, 1_000_000)]);
        $appliquees[] = $nom;
    }
    return $appliquees;
}

function forceos_base_vide(PDO $pdo): bool
{
    return $pdo->query('SHOW TABLES')->fetchAll() === [];
}

/**
 * Applique la base initiale si, et seulement si, la base est entièrement vide.
 * @return array{installee: bool, tables?: int}
 */
function forceos_installer_base_initiale(PDO $pdo, string $fichier = FORCEOS_BASE_INITIALE): array
{
    if (! forceos_base_vide($pdo)) {
        return ['installee' => false];
    }
    $lignes = file($fichier, FILE_IGNORE_NEW_LINES);
    if ($lignes === false) {
        throw new RuntimeException('Base initiale introuvable.');
    }
    $attendues = preg_match_all('/^CREATE TABLE /m', implode("\n", $lignes));

    try {
        // Fichier livré dans le paquet signé : aucune liste blanche nécessaire
        forceos_executer_lignes($pdo, $lignes, null);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $tables = (int) $pdo->query(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
        )->fetchColumn();
        if ($tables !== $attendues) {
            throw new RuntimeException("Base initiale incomplète : {$tables} tables sur {$attendues}.");
        }
        forceos_migrations_forceos($pdo);
    } catch (Throwable $e) {
        // Une base à moitié créée bloquerait les lancements suivants
        forceos_vider_base($pdo);
        throw $e;
    }
    return ['installee' => true, 'tables' => $tables];
}

/** La première configuration est faite dès qu'un site et un compte existent. */
function forceos_est_configure(PDO $pdo): bool
{
    return (int) $pdo->query('SELECT COUNT(*) FROM sites')->fetchColumn() > 0
        && (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
}

/**
 * Crée la section (site unique) et le compte du secrétaire.
 * Le compte reçoit le rôle super_admin, rattaché à la section : en mode
 * mono-site, FO Gestion le traite comme le secrétaire du site (nom de la
 * section affiché), avec en plus l'accès aux réglages de l'application.
 *
 * @param array{section:string, prenom:string, nom:string, email:string, mot_de_passe:string,
 *              tarif_timbre?:float|string, tarif_carte?:float|string} $d
 * @return array{site_id:int, user_id:int, code:string}
 */
function forceos_premiere_configuration(PDO $pdo, array $d): array
{
    $section = trim((string) ($d['section'] ?? ''));
    $prenom  = trim((string) ($d['prenom'] ?? ''));
    $nom     = trim((string) ($d['nom'] ?? ''));
    $email   = mb_strtolower(trim((string) ($d['email'] ?? '')));
    $mdp     = (string) ($d['mot_de_passe'] ?? '');
    $timbre  = (float) str_replace(',', '.', (string) ($d['tarif_timbre'] ?? '5'));
    $carte   = (float) str_replace(',', '.', (string) ($d['tarif_carte'] ?? '2'));

    $erreurs = [];
    if (mb_strlen($section) < 2 || mb_strlen($section) > 255) {
        $erreurs[] = 'Le nom du syndicat doit comporter entre 2 et 255 caractères.';
    }
    if ($prenom === '' || mb_strlen($prenom) > 100 || $nom === '' || mb_strlen($nom) > 100) {
        $erreurs[] = 'Le prénom et le nom sont obligatoires (100 caractères au maximum).';
    }
    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) {
        $erreurs[] = 'L’adresse e-mail n’est pas valide.';
    }
    if (mb_strlen($mdp) < 12) {
        $erreurs[] = 'Le mot de passe doit comporter au moins 12 caractères.';
    }
    if ($timbre < 0 || $timbre > 9999 || $carte < 0 || $carte > 9999) {
        $erreurs[] = 'Les tarifs du timbre et de la carte sont invalides.';
    }
    if ($erreurs) {
        throw new InvalidArgumentException(implode(' ', $erreurs));
    }

    // Code du site : même règle que Platform::createSite()
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $section) ?: $section;
    $code  = substr(strtoupper(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $ascii), '_')), 0, 45) ?: 'SECTION';

    $pdo->beginTransaction();
    try {
        // Verrou : deux configurations simultanées sont impossibles
        if ((int) $pdo->query('SELECT COUNT(*) FROM users FOR UPDATE')->fetchColumn() > 0
            || (int) $pdo->query('SELECT COUNT(*) FROM sites FOR UPDATE')->fetchColumn() > 0) {
            throw new RuntimeException('Ce poste est déjà configuré.');
        }
        $pdo->prepare('INSERT INTO sites (name, code, tarif_timbre, tarif_carte, active) VALUES (?, ?, ?, ?, 1)')
            ->execute([$section, $code, $timbre, $carte]);
        $siteId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO years (site_id, year, is_current, tarif_timbre, tarif_carte) VALUES (?, ?, 1, ?, ?)')
            ->execute([$siteId, (int) date('Y'), $timbre, $carte]);

        $pdo->prepare("INSERT INTO users (site_id, email, password, first_name, last_name, role, active)
                       VALUES (?, ?, ?, ?, ?, 'super_admin', 1)")
            ->execute([$siteId, $email, password_hash($mdp, PASSWORD_DEFAULT), $prenom, $nom]);
        $userId = (int) $pdo->lastInsertId();

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['site_id' => $siteId, 'user_id' => $userId, 'code' => $code];
}

/**
 * Connexion PDO à la base ForceOS, construite à partir de la configuration
 * transmise par Electron (socket Unix, compte Linux, aucun mot de passe).
 */
function forceos_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $socket = (string) getenv('database.default.hostname');
    $base   = (string) getenv('database.default.database');
    if ($socket === '' || $socket[0] !== '/' || ! preg_match('/^[a-z0-9_]{1,64}$/', $base)) {
        throw new RuntimeException('Configuration de base ForceOS invalide.');
    }
    $pdo = new PDO(
        'mysql:unix_socket=' . $socket . ';dbname=' . $base . ';charset=utf8mb4',
        (string) getenv('database.default.username'),
        (string) getenv('database.default.password'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
         PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]
    );
    return $pdo;
}
