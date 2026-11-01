<?php

namespace App\Commands;

use App\Domain\ScheduledTasks;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Tâches planifiées. Sur alwaysdata (Avancé > Tâches planifiées), type « Commande » :
 *   php /chemin/vers/fo-gestion-3/spark fo:taches rappels          chaque jour à 8 h
 *   php /chemin/vers/fo-gestion-3/spark fo:taches recap weekly     le lundi à 8 h
 *   php /chemin/vers/fo-gestion-3/spark fo:taches recap monthly    le 1er du mois à 8 h
 *   php /chemin/vers/fo-gestion-3/spark fo:taches push             chaque jour à 18 h
 * Option --essai : affiche ce qui serait envoyé, sans rien envoyer.
 */
class Taches extends BaseCommand
{
    protected $group       = 'FO Gestion';
    protected $name        = 'fo:taches';
    protected $description = 'Rappels aux collecteurs, récapitulatifs par courriel, notifications push planifiées.';
    protected $usage       = 'fo:taches <rappels|recap|push> [daily|weekly|monthly] [--essai]';

    public function run(array $params)
    {
        $task = $params[0] ?? '';
        helper(['fo', 'url']);
        $t    = new ScheduledTasks(Database::connect(), CLI::getOption('essai') !== null);
        CLI::write('[' . date('Y-m-d H:i:s') . "] Tâche {$task}");
        match ($task) {
            'rappels' => $t->reminders(),
            'recap'   => $t->recap($params[1] ?? 'weekly'),
            'push'    => $t->push(),
            default   => CLI::error('Tâche inconnue. ' . $this->usage),
        };
        foreach ($t->log as $l) {
            CLI::write($l);
        }
    }
}
