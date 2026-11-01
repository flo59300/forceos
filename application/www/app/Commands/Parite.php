<?php

namespace App\Commands;

use App\Domain\Delegation;
use App\Domain\MemberStatus;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Exporte les calculs métier en JSON pour les comparer à la version 2.x (recette de parité).
 *   php spark fo:parite --site 1 --annee 2026
 */
class Parite extends BaseCommand
{
    protected $group       = 'FO Gestion';
    protected $name        = 'fo:parite';
    protected $description = 'Exporte statuts adhérents et heures de délégation en JSON (contrôle de parité avec la 2.x).';
    protected $options     = ['--site' => 'Identifiant du site', '--annee' => 'Année de cotisation', '--mois' => 'Mois pour les heures'];

    public function run(array $params)
    {
        $db    = Database::connect();
        $site  = (int) (CLI::getOption('site') ?? 1);
        $year  = (int) (CLI::getOption('annee') ?? date('Y'));
        $month = (int) (CLI::getOption('mois') ?? date('n'));

        $statuses = array_map(static fn ($s) => $s['status'], (new MemberStatus($db))->forSite($site, $year));
        $hours    = [];
        $deleg    = new Delegation($db);
        foreach ($db->table('users')->select('id')->where('site_id', $site)->get()->getResultArray() as $u) {
            $hours[(int) $u['id']] = $deleg->month((int) $u['id'], $year, $month);
        }
        ksort($statuses);
        CLI::write(json_encode(['statuses' => $statuses, 'hours' => $hours]));
    }
}
