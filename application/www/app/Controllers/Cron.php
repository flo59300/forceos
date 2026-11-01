<?php

namespace App\Controllers;

use App\Domain\ScheduledTasks;

/**
 * Tâches planifiées appelées par adresse, pour les hébergements qui ne lancent que des URL.
 * Mêmes adresses que la 2.x (cron_rappels.php, cron_notifications.php, cron_push.php) avec ?key=…
 * La clé est celle de fo.cronKey dans .env ; sans clé configurée, l'appel par adresse est refusé.
 */
class Cron extends BaseController
{
    public function run(string $task)
    {
        $key = (string) env('fo.cronKey', '');
        if ($key === '' || ! hash_equals($key, (string) $this->request->getGet('key'))) {
            return $this->response->setStatusCode(403)->setBody('Accès interdit');
        }
        $t = new ScheduledTasks($this->db);
        match ($task) {
            'rappels' => $t->reminders(),
            'notifications' => $t->recap((string) ($this->request->getGet('mode') ?: 'weekly')),
            'push' => $t->push(),
        };

        return $this->response->setHeader('Content-Type', 'text/plain; charset=utf-8')->setBody(implode("\n", $t->log) . "\n");
    }
}
