<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use FilesystemIterator;
use mysqli;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

/**
 * Mise à jour par paquet zip et migrations, sur le modèle de FOUpdater (includes/updater.php de la 2.x),
 * adapté à la structure de la version 3 (app/, public/, system/, writable/, .env).
 *
 * Principes repris de la 2.x :
 *  - le paquet est refusé en entier au moindre fichier manquant, altéré (empreinte du manifeste) ou chemin suspect ;
 *  - chaque fichier est écrit à côté puis renommé : jamais de fichier à moitié copié ;
 *  - sauvegarde de la base et des fichiers avant toute écriture, page de maintenance pendant l'opération, verrou ;
 *  - migrations tracées dans la table schema_migrations, partagée avec la 2.x.
 * Propres à la version 3 :
 *  - .env n'est jamais remplacé ; writable/ et public/uploads/ ne reçoivent que des fichiers absents ;
 *  - les fichiers retirés d'une version à l'autre sont supprimés, s'ils n'ont pas été modifiés sur place ;
 *  - les migrations de la version 3 sont nommées « fo3/… » pour ne jamais se mêler à celles de la 2.x ;
 *  - un retour à une version plus ancienne est refusé.
 */
class Updater
{
    public const TABLE = 'schema_migrations';
    public const MIGRATIONS_DIR = 'app/Database/Updates';
    private const TOLERATED = [1050, 1060, 1061, 1091, 1826, 1022, 1068];
    private const PRESERVE_PREFIXES = ['writable/', 'public/uploads/'];
    private const SKIP_PREFIXES = ['.git/', 'node_modules/', '__MACOSX/', 'build/'];
    /** Historique de la 2.x : jamais rejoué sur une base existante (certaines migrations sont destructives). */
    private const LEGACY_MAX = 36;

    private string $root;

    public function __construct(private ?BaseConnection $db, ?string $root = null)
    {
        $this->root = rtrim($root ?? ROOTPATH, '/\\');
    }

    public static function currentVersion(): string
    {
        return FO_VERSION;
    }

    // ------------------------------------------------------------------
    // Paquet
    // ------------------------------------------------------------------

    private static function safePath(string $rel): bool
    {
        return $rel !== '' && $rel[0] !== '/' && ! str_contains($rel, '\\') && ! str_contains($rel, ':')
            && ! str_contains($rel, "\0") && ! preg_match('#(^|/)\.\.(/|$)#', $rel) && ! preg_match('/[\x00-\x1f]/', $rel);
    }

    /** Analyse un paquet : dossier racine, version, empreintes. */
    public static function packageInfo(string $zipPath): array
    {
        if (! class_exists(ZipArchive::class)) {
            return ['ok' => false, 'error' => 'L’extension PHP ZipArchive n’est pas disponible sur le serveur.'];
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'error' => 'Impossible d’ouvrir le fichier zip.'];
        }
        $fail = static function (string $msg) use ($zip) {
            $zip->close();

            return ['ok' => false, 'error' => $msg];
        };
        $prefix = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = (string) $zip->getNameIndex($i);
            if (preg_match('#^((?:[^/]+/)?)app/Config/Constants\.php$#', $n, $m) && $zip->locateName($m[1] . 'spark') !== false) {
                $prefix = $m[1];
                break;
            }
            if (preg_match('#^((?:[^/]+/)?)includes/updater\.php$#', $n)) {
                return $fail('Ce zip est un paquet de la version 2.x : il s’installe depuis la version 2.x, pas ici.');
            }
        }
        if ($prefix === null) {
            return $fail('Ce zip ne ressemble pas à un paquet FO Gestion 3 (app/Config/Constants.php ou spark introuvable).');
        }
        $c = (string) $zip->getFromName($prefix . 'app/Config/Constants.php');
        if (! preg_match("/define\\('FO_VERSION',\\s*'([^']+)'\\)/", $c, $m)) {
            return $fail('Paquet invalide : version introuvable.');
        }
        $version = $m[1];
        $mf      = json_decode((string) $zip->getFromName($prefix . 'manifest-files.json'), true);
        if (! is_array($mf) || empty($mf['files']) || ($mf['version'] ?? '') !== $version) {
            return $fail('Paquet invalide : manifeste absent ou ne correspondant pas à la version.');
        }
        foreach ($mf['files'] as $rel => $hash) {
            $data = $zip->getFromName($prefix . $rel);
            if ($data === false) {
                return $fail("Paquet incomplet : {$rel} manquant. Le téléchargement a peut-être été interrompu.");
            }
            if (sha1($data) !== $hash) {
                return $fail("Paquet corrompu : {$rel} ne correspond pas à son empreinte.");
            }
        }
        $count = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = (string) $zip->getNameIndex($i);
            if (str_ends_with($n, '/') || str_starts_with($n, '__MACOSX/')) {
                continue;
            }
            $rel = substr($n, strlen($prefix));
            if (($prefix !== '' && ! str_starts_with($n, $prefix)) || ! self::safePath($rel)) {
                return $fail("Paquet refusé : chemin non autorisé ({$n}).");
            }
            $count++;
        }
        $zip->close();

        return ['ok' => true, 'prefix' => $prefix, 'version' => $version, 'build' => (string) ($mf['build'] ?? ''), 'files' => $count, 'manifest' => $mf['files']];
    }

    /** Copie les fichiers du paquet. */
    public function installPackage(string $zipPath, ?array $info = null): array
    {
        $info ??= self::packageInfo($zipPath);
        if (! $info['ok']) {
            return $info;
        }
        $prefix = $info['prefix'];
        $old    = $this->manifest();
        $zip    = new ZipArchive();
        $zip->open($zipPath);
        $report = ['ok' => true, 'added' => [], 'updated' => [], 'removed' => [], 'unchanged' => 0, 'preserved' => [], 'errors' => [], 'version' => $info['version']];
        $hasEnv = is_file($this->root . '/.env');

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_ends_with($name, '/') || ($prefix !== '' && ! str_starts_with($name, $prefix))) {
                continue;
            }
            $rel = substr($name, strlen($prefix));
            if (! self::safePath($rel)) {
                $report['errors'][] = "Chemin refusé : {$rel}";

                continue;
            }
            foreach (self::SKIP_PREFIXES as $sp) {
                if (str_starts_with($rel, $sp)) {
                    continue 2;
                }
            }
            $dest = $this->root . '/' . $rel;
            if ($rel === '.env' || ($rel === 'public/installer.php' && $hasEnv)) {
                continue;
            }
            foreach (self::PRESERVE_PREFIXES as $pp) {
                if (str_starts_with($rel, $pp) && file_exists($dest)) {
                    $report['preserved'][] = $rel;

                    continue 2;
                }
            }
            $content = $zip->getFromIndex($i);
            if ($content === false) {
                $report['errors'][] = "Lecture impossible : {$rel}";

                continue;
            }
            $exists = is_file($dest);
            if ($exists && sha1_file($dest) === sha1($content)) {
                $report['unchanged']++;

                continue;
            }
            if (! is_dir(dirname($dest)) && ! @mkdir(dirname($dest), 0755, true)) {
                $report['errors'][] = 'Dossier non créé : ' . dirname($rel);

                continue;
            }
            $tmp = $dest . '.fo-new';
            if (@file_put_contents($tmp, $content) === false || ! @rename($tmp, $dest)) {
                @unlink($tmp);
                $report['errors'][] = "Écriture impossible : {$rel}";

                continue;
            }
            if (function_exists('opcache_invalidate') && str_ends_with($rel, '.php')) {
                @opcache_invalidate($dest, true);
            }
            $report[$exists ? 'updated' : 'added'][] = $rel;
        }
        $zip->close();

        // Fichiers de l'ancienne version absents de la nouvelle : supprimés s'ils sont intacts
        foreach ($old as $rel => $hash) {
            if (isset($info['manifest'][$rel]) || ! self::safePath($rel) || str_starts_with($rel, 'writable/') || $rel === '.env') {
                continue;
            }
            $p = $this->root . '/' . $rel;
            if (is_file($p) && sha1_file($p) === $hash && @unlink($p)) {
                $report['removed'][] = $rel;
            }
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        $this->clearCache();
        $report['ok'] = $report['errors'] === [];

        return $report;
    }

    private function clearCache(): void
    {
        foreach (glob($this->root . '/writable/cache/*') ?: [] as $f) {
            if (is_file($f) && basename($f) !== 'index.html') {
                @unlink($f);
            }
        }
    }

    public function manifest(): array
    {
        $d = json_decode((string) @file_get_contents($this->root . '/manifest-files.json'), true);

        return is_array($d['files'] ?? null) ? $d['files'] : [];
    }

    /** Fichiers installés comparés au manifeste. */
    public function integrity(): array
    {
        $files = $this->manifest();
        if (! $files) {
            return ['ok' => false, 'error' => 'Manifeste absent (manifest-files.json) : vérification impossible.'];
        }
        $missing = $modified = [];
        foreach ($files as $rel => $hash) {
            $p = $this->root . '/' . $rel;
            if (! is_file($p)) {
                if (! ($rel === 'public/installer.php' || str_starts_with($rel, 'writable/'))) {
                    $missing[] = $rel;
                }
            } elseif (! str_starts_with($rel, 'writable/') && sha1_file($p) !== $hash) {
                $modified[] = $rel;
            }
        }

        return ['ok' => true, 'total' => count($files), 'missing' => $missing, 'modified' => $modified];
    }

    // ------------------------------------------------------------------
    // Migrations
    // ------------------------------------------------------------------

    /** Fichiers SQL de la version 3, triés. Nom dans schema_migrations : « fo3/<fichier> ». */
    public function migrationFiles(): array
    {
        $out = [];
        foreach (glob($this->root . '/' . self::MIGRATIONS_DIR . '/*.sql') ?: [] as $p) {
            $out['fo3/' . basename($p)] = $p;
        }
        ksort($out, SORT_NATURAL);

        return $out;
    }

    private function mysqli(): mysqli
    {
        $this->db->initialize();

        return $this->db->connID;
    }

    public function ensureTable(): void
    {
        $this->db->query('CREATE TABLE IF NOT EXISTS `' . self::TABLE . "` (
            `migration` VARCHAR(100) NOT NULL PRIMARY KEY,
            `mode` ENUM('run','baseline') NOT NULL DEFAULT 'run',
            `success` TINYINT(1) NOT NULL DEFAULT 1,
            `statements` INT UNSIGNED NOT NULL DEFAULT 0,
            `warnings` TEXT NULL,
            `error` TEXT NULL,
            `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // Table vide sur une base existante : on inscrit l'historique de la 2.x comme la 2.x le ferait,
        // sinon elle rejouerait ses anciennes migrations (dont certaines suppriment des données) à sa prochaine mise à jour.
        if ((int) $this->db->table(self::TABLE)->countAllResults() === 0 && $this->db->tableExists('users')) {
            $rows = [['migration' => 'UPDATE.sql', 'mode' => 'baseline', 'success' => 1]];
            for ($v = 2; $v <= self::LEGACY_MAX; $v++) {
                $rows[] = ['migration' => "UPDATE-v{$v}.sql", 'mode' => 'baseline', 'success' => 1];
            }
            $this->db->table(self::TABLE)->insertBatch($rows);
        }
    }

    public function status(): array
    {
        $this->ensureTable();
        $applied = [];
        foreach ($this->db->table(self::TABLE)->like('migration', 'fo3/', 'after')->get()->getResultArray() as $r) {
            $applied[$r['migration']] = $r;
        }
        $out = [];
        foreach ($this->migrationFiles() as $name => $path) {
            $row   = $applied[$name] ?? null;
            $state = ! $row ? 'pending' : (! (int) $row['success'] ? 'failed' : ($row['mode'] === 'baseline' ? 'baseline' : 'applied'));
            $out[] = ['name' => $name, 'file' => basename($path), 'path' => $path, 'state' => $state, 'row' => $row];
        }

        return $out;
    }

    public function pendingCount(): int
    {
        return count(array_filter($this->status(), static fn ($m) => in_array($m['state'], ['pending', 'failed'], true)));
    }

    public function runPending(): array
    {
        $res = [];
        foreach ($this->status() as $m) {
            if (! in_array($m['state'], ['pending', 'failed'], true)) {
                continue;
            }
            $r     = $this->runMigration($m['name'], $m['path']);
            $res[] = ['file' => $m['file']] + $r;
            if (! $r['ok']) {
                break;
            }
        }

        return $res;
    }

    public function runMigration(string $name, string $path): array
    {
        $statements = self::splitSql((string) file_get_contents($path));
        $my         = $this->mysqli();
        $t0         = microtime(true);
        $warnings   = [];
        $error      = null;
        $done       = 0;
        foreach ($statements as $stmt) {
            try {
                $ok = $my->query($stmt);
            } catch (Throwable) {
                $ok = false;
            }
            if ($ok !== false) {
                $done++;

                continue;
            }
            if (in_array($my->errno, self::TOLERATED, true)) {
                $warnings[] = "[{$my->errno}] " . mb_substr($my->error, 0, 200);
                $done++;

                continue;
            }
            $error = $my->error . ' | ' . mb_substr((string) preg_replace('/\s+/', ' ', $stmt), 0, 160);
            break;
        }
        $this->db->query('REPLACE INTO `' . self::TABLE . "` (migration, mode, success, statements, warnings, error, duration_ms, applied_at) VALUES (?, 'run', ?, ?, ?, ?, ?, NOW())",
            [$name, $error ? 0 : 1, $done, $warnings ? implode("\n", $warnings) : null, $error, (int) round((microtime(true) - $t0) * 1000)]);

        return ['ok' => $error === null, 'statements' => $done, 'total' => count($statements), 'warnings' => $warnings, 'error' => $error];
    }

    /** Découpe un script SQL en instructions (chaînes, identifiants et commentaires compris), comme la 2.x. */
    public static function splitSql(string $sql): array
    {
        $out   = [];
        $buf   = '';
        $len   = strlen($sql);
        $quote = null;
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            $n = $i + 1 < $len ? $sql[$i + 1] : '';
            if ($quote !== null) {
                $buf .= $c;
                if ($c === '\\' && $quote !== '`') {
                    $buf .= $n;
                    $i++;

                    continue;
                }
                if ($c === $quote) {
                    if ($n === $quote) {
                        $buf .= $n;
                        $i++;

                        continue;
                    }
                    $quote = null;
                }

                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
                $buf .= $c;

                continue;
            }
            if ($c === '#' || ($c === '-' && $n === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2])))) {
                $eol = strpos($sql, "\n", $i);
                $i   = $eol === false ? $len : $eol;
                $buf .= "\n";

                continue;
            }
            if ($c === '/' && $n === '*' && ($sql[$i + 2] ?? '') !== '!') {
                $end = strpos($sql, '*/', $i + 2);
                $i   = $end === false ? $len : $end + 1;
                $buf .= ' ';

                continue;
            }
            if ($c === ';') {
                if (trim($buf) !== '') {
                    $out[] = trim($buf);
                }
                $buf = '';

                continue;
            }
            $buf .= $c;
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Sauvegardes
    // ------------------------------------------------------------------

    public function backupDir(): string
    {
        $dir = $this->root . '/writable/backups';
        if (! is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (! is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        }
        if (! is_file($dir . '/index.html')) {
            @file_put_contents($dir . '/index.html', '');
        }

        return $dir;
    }

    /** Écrit un export SQL (structure et données) dans un flux : sauvegarde .sql.gz ou téléchargement. */
    public function dumpSql(callable $write, ?array $tables = null): array
    {
        $my  = $this->mysqli();
        $all = array_map(static fn ($r) => $r[0], $my->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all());
        $tables = $tables === null ? $all : array_values(array_intersect($all, $tables));
        $write('-- ' . Settings::appName() . ' : export SQL du ' . date('d/m/Y H:i:s') . ' (version ' . FO_VERSION . ")\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
        $rows = 0;
        foreach ($tables as $t) {
            $create = $my->query('SHOW CREATE TABLE `' . str_replace('`', '``', $t) . '`')->fetch_row()[1];
            $write("-- Table {$t}\nDROP TABLE IF EXISTS `{$t}`;\n{$create};\n\n");
            $res   = $my->query('SELECT * FROM `' . str_replace('`', '``', $t) . '`', MYSQLI_USE_RESULT);
            $batch = [];
            $cols  = null;
            while ($row = $res->fetch_assoc()) {
                $cols ??= '`' . implode('`,`', array_keys($row)) . '`';
                $batch[] = '(' . implode(',', array_map(static fn ($v) => $v === null ? 'NULL' : "'" . $my->real_escape_string((string) $v) . "'", $row)) . ')';
                $rows++;
                if (count($batch) >= 200) {
                    $write("INSERT INTO `{$t}` ({$cols}) VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            $res->free();
            if ($batch) {
                $write("INSERT INTO `{$t}` ({$cols}) VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            $write("\n");
        }
        $write("SET FOREIGN_KEY_CHECKS = 1;\n");

        return ['tables' => count($tables), 'rows' => $rows];
    }

    public function backupDatabase(): array
    {
        if (! function_exists('gzopen')) {
            return ['ok' => false, 'error' => 'L’extension zlib n’est pas disponible.'];
        }
        $path = $this->backupDir() . '/db-' . date('Ymd-His') . '.sql.gz';
        $gz   = @gzopen($path, 'wb6');
        if (! $gz) {
            return ['ok' => false, 'error' => 'Impossible de créer le fichier de sauvegarde.'];
        }

        try {
            $r = $this->dumpSql(static function (string $s) use ($gz) { gzwrite($gz, $s); });
        } catch (Throwable $e) {
            gzclose($gz);
            @unlink($path);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
        gzclose($gz);

        return ['ok' => true, 'file' => basename($path), 'size' => filesize($path)] + $r;
    }

    /** Sauvegarde du code (sans writable/ ni .env, qui ne sont jamais remplacés par une mise à jour). */
    public function backupFiles(): array
    {
        if (! class_exists(ZipArchive::class)) {
            return ['ok' => false, 'error' => 'ZipArchive indisponible.'];
        }
        $path = $this->backupDir() . '/files-' . date('Ymd-His') . '.zip';
        $zip  = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['ok' => false, 'error' => 'Création du zip impossible.'];
        }
        $n   = 0;
        $len = strlen($this->root) + 1;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS)) as $f) {
            if (! $f->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($f->getPathname(), $len));
            if ($rel === '.env' || preg_match('#^(writable|public/uploads|build|node_modules|\.git)/#', $rel) || preg_match('/\.zip$/i', $rel)) {
                continue;
            }
            $zip->addFile($f->getPathname(), $rel);
            $n++;
        }
        $zip->close();

        return ['ok' => true, 'file' => basename($path), 'size' => (int) @filesize($path), 'files' => $n];
    }

    public function listBackups(): array
    {
        $out = [];
        foreach (array_merge(glob($this->backupDir() . '/db-*') ?: [], glob($this->backupDir() . '/files-*') ?: []) as $p) {
            $out[] = ['file' => basename($p), 'size' => filesize($p), 'date' => filemtime($p)];
        }
        usort($out, static fn ($a, $b) => $b['date'] <=> $a['date']);

        return $out;
    }

    public function backupPath(string $name): ?string
    {
        if (! preg_match('/^(db|files)-\d{8}-\d{6}\.(sql\.gz|zip)$/', $name)) {
            return null;
        }
        $p = $this->backupDir() . '/' . $name;

        return is_file($p) ? $p : null;
    }

    public function pruneBackups(int $keep = 5): void
    {
        foreach (['db-', 'files-'] as $type) {
            $files = array_values(array_filter($this->listBackups(), static fn ($b) => str_starts_with($b['file'], $type)));
            foreach (array_slice($files, $keep) as $o) {
                @unlink($this->backupDir() . '/' . $o['file']);
            }
        }
    }

    // ------------------------------------------------------------------
    // Maintenance, verrou, procédure complète
    // ------------------------------------------------------------------

    public function setMaintenance(bool $on): void
    {
        $f = $this->root . '/.maintenance';
        $on ? @file_put_contents($f, (string) time()) : @unlink($f);
    }

    /** @return resource|null */
    public function lock()
    {
        $h = @fopen($this->backupDir() . '/.update.lock', 'c');
        if (! $h || ! flock($h, LOCK_EX | LOCK_NB)) {
            return null;
        }

        return $h;
    }

    public function unlock($h): void
    {
        if ($h) {
            flock($h, LOCK_UN);
            fclose($h);
        }
    }

    public function requirements(): array
    {
        return [
            ['PHP 8.2 ou supérieur (' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.2.0', '>=')],
            ['Extension ZipArchive', class_exists(ZipArchive::class)],
            ['Extension zlib (sauvegardes compressées)', function_exists('gzopen')],
            ['Dossier de l’application accessible en écriture', is_writable($this->root) && is_writable($this->root . '/app') && is_writable($this->root . '/public')],
            ['Dossier writable/ accessible en écriture', is_writable($this->root . '/writable')],
        ];
    }

    public function fullUpdate(string $zipPath, bool $backupDb = true, bool $backupFiles = true): array
    {
        $res  = ['ok' => false, 'steps' => []];
        $info = self::packageInfo($zipPath);
        if (! $info['ok']) {
            return $res + ['error' => $info['error']];
        }
        if (version_compare($info['version'], self::currentVersion(), '<')) {
            return $res + ['error' => "Ce paquet contient la version {$info['version']}, plus ancienne que la version installée (" . self::currentVersion() . '). Retour en arrière refusé.'];
        }
        $lock = $this->lock();
        if (! $lock) {
            return $res + ['error' => 'Une mise à jour est déjà en cours.'];
        }
        @set_time_limit(300);
        @ignore_user_abort(true);

        try {
            if ($backupDb && $this->db) {
                $b                         = $this->backupDatabase();
                $res['steps']['backup_db'] = $b;
                if (! $b['ok']) {
                    return $res + ['error' => 'Sauvegarde de la base impossible : ' . $b['error']];
                }
            }
            if ($backupFiles) {
                $b                            = $this->backupFiles();
                $res['steps']['backup_files'] = $b;
                if (! $b['ok']) {
                    return $res + ['error' => 'Sauvegarde des fichiers impossible : ' . $b['error']];
                }
            }
            $this->setMaintenance(true);
            $inst                  = $this->installPackage($zipPath, $info);
            $res['steps']['files'] = $inst;
            if ($this->db) {
                $res['steps']['migrations'] = $this->runPending();
                foreach ($res['steps']['migrations'] as $m) {
                    if (! $m['ok']) {
                        return $res + ['error' => "Migration {$m['file']} en échec : {$m['error']}"];
                    }
                }
            }
            $this->pruneBackups(5);
            $res['ok'] = $inst['ok'];
            if (! $inst['ok']) {
                $res['error'] = plural(count($inst['errors']), 'fichier non copié', 'fichiers non copiés') . '.';
            }

            return $res;
        } finally {
            $this->setMaintenance(false);
            $this->unlock($lock);
        }
    }
}
