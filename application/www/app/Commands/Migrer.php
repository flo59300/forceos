<?php

namespace App\Commands;

use App\Libraries\Updater;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Applique les migrations de la version 3 en attente (après une copie des fichiers par FTP, par exemple).
 *   php spark fo:migrer            sauvegarde la base puis applique
 *   php spark fo:migrer --etat     affiche seulement l'état
 */
class Migrer extends BaseCommand
{
    protected $group       = 'FO Gestion';
    protected $name        = 'fo:migrer';
    protected $description = 'Applique les migrations de la base propres à la version 3.';
    protected $usage       = 'fo:migrer [--etat]';

    public function run(array $params)
    {
        helper(['fo']);
        $u = new Updater(Database::connect());
        foreach ($u->status() as $m) {
            CLI::write(str_pad($m['state'], 9) . ' ' . $m['file']);
        }
        if (CLI::getOption('etat') !== null) {
            return;
        }
        if ($u->pendingCount() === 0) {
            CLI::write('Rien à appliquer.', 'green');

            return;
        }
        $b = $u->backupDatabase();
        if (! $b['ok']) {
            CLI::error('Sauvegarde impossible : ' . $b['error']);

            return EXIT_ERROR;
        }
        CLI::write('Sauvegarde : writable/backups/' . $b['file']);
        foreach ($u->runPending() as $r) {
            CLI::write(($r['ok'] ? 'OK     ' : 'ÉCHEC  ') . $r['file'] . ($r['error'] ? ' : ' . $r['error'] : ''), $r['ok'] ? 'green' : 'red');
            if (! $r['ok']) {
                return EXIT_ERROR;
            }
        }
    }
}
