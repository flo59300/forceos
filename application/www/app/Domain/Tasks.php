<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Liste de tâches unique du délégué.
 *
 * Fusionne les deux systèmes de la 2.x : les tâches personnelles de la Boîte à outils
 * (table user_tasks) et les tâches de l'Espace délégués (table delegate_todos).
 * Les nouvelles tâches sont créées dans delegate_todos ; les anciennes restent
 * lisibles et modifiables là où elles sont, jusqu'à la fusion des données à la bascule.
 */
class Tasks
{
    public const PRIORITIES = ['haute' => 'Haute', 'normale' => 'Normale', 'basse' => 'Basse'];
    public const STATUSES   = ['a_faire' => 'À faire', 'en_cours' => 'En cours', 'fait' => 'Fait'];

    public function __construct(private BaseConnection $db)
    {
    }

    /**
     * Tâches d'un utilisateur, triées : non faites d'abord, en retard, priorité, échéance.
     *
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId, bool $includeDone = true): array
    {
        $todos = $this->db->table('delegate_todos')
            ->select("'todo' AS source, id, title, description, priority, status, due_date, completed_at AS done_at, created_at")
            ->where('user_id', $userId)->get()->getResultArray();
        $perso = $this->db->table('user_tasks')
            ->select("'perso' AS source, id, title, NULL AS description, priority, IF(done = 1, 'fait', 'a_faire') AS status, due_date, done_at, created_at", false)
            ->where('user_id', $userId)->get()->getResultArray();

        $all = array_merge($todos, $perso);
        if (! $includeDone) {
            $all = array_values(array_filter($all, static fn ($t) => $t['status'] !== 'fait'));
        }

        return self::sort($all);
    }

    /** Tâches de tout un site (vue du secrétaire), avec le nom de la personne. */
    public function forSite(int $siteId): array
    {
        $rows = $this->db->table('delegate_todos t')
            ->select("'todo' AS source, t.id, t.title, t.description, t.priority, t.status, t.due_date, t.completed_at AS done_at, t.created_at, t.user_id, CONCAT(u.first_name, ' ', u.last_name) AS user_name")
            ->join('users u', 'u.id = t.user_id')
            ->where('t.site_id', $siteId)->where('u.role !=', 'super_admin')
            ->get()->getResultArray();

        return self::sort($rows);
    }

    public static function sort(array $tasks): array
    {
        $today = date('Y-m-d');
        $prio  = ['haute' => 0, 'normale' => 1, 'basse' => 2];
        usort($tasks, static function ($a, $b) use ($today, $prio) {
            $ad = $a['status'] === 'fait';
            $bd = $b['status'] === 'fait';
            if ($ad !== $bd) {
                return $ad <=> $bd;
            }
            $ao = ! $ad && $a['due_date'] && $a['due_date'] < $today;
            $bo = ! $bd && $b['due_date'] && $b['due_date'] < $today;
            if ($ao !== $bo) {
                return $bo <=> $ao;
            }
            if ($prio[$a['priority']] !== $prio[$b['priority']]) {
                return $prio[$a['priority']] <=> $prio[$b['priority']];
            }

            return [$a['due_date'] ?: '9999', $b['created_at']] <=> [$b['due_date'] ?: '9999', $a['created_at']];
        });

        return $tasks;
    }

    public static function isOverdue(array $t): bool
    {
        return $t['status'] !== 'fait' && ! empty($t['due_date']) && $t['due_date'] < date('Y-m-d');
    }

    /**
     * Trouve une tâche si l'utilisateur a le droit d'y toucher.
     * Le secrétaire peut agir sur toutes les tâches de son site (delegate_todos).
     */
    public function find(string $source, int $id, int $userId, ?int $secretarySiteId = null): ?array
    {
        if ($source === 'perso') {
            return $this->db->table('user_tasks')->where(['id' => $id, 'user_id' => $userId])->get()->getRowArray();
        }
        $q = $this->db->table('delegate_todos')->where('id', $id);
        $secretarySiteId !== null ? $q->where('site_id', $secretarySiteId) : $q->where('user_id', $userId);

        return $q->get()->getRowArray();
    }

    public function create(int $siteId, int $userId, array $data): int
    {
        $this->db->table('delegate_todos')->insert([
            'site_id'     => $siteId,
            'user_id'     => $userId,
            'title'       => $data['title'],
            'description' => $data['description'] ?: null,
            'priority'    => $data['priority'],
            'due_date'    => $data['due_date'] ?: null,
        ]);

        return (int) $this->db->insertID();
    }

    /** Fait / à faire. Les tâches « perso » n'ont que ces deux états. */
    public function setDone(string $source, int $id, bool $done): void
    {
        if ($source === 'perso') {
            $this->db->table('user_tasks')->where('id', $id)->update(['done' => $done ? 1 : 0, 'done_at' => $done ? date('Y-m-d H:i:s') : null]);

            return;
        }
        $this->db->table('delegate_todos')->where('id', $id)->update(['status' => $done ? 'fait' : 'a_faire', 'completed_at' => $done ? date('Y-m-d H:i:s') : null]);
    }

    public function update(string $source, int $id, array $data): void
    {
        $row = ['title' => $data['title'], 'priority' => $data['priority'], 'due_date' => $data['due_date'] ?: null];
        if ($source === 'perso') {
            $this->db->table('user_tasks')->where('id', $id)->update($row);

            return;
        }
        $row['description'] = $data['description'] ?: null;
        $this->db->table('delegate_todos')->where('id', $id)->update($row);
    }

    public function delete(string $source, int $id): void
    {
        $this->db->table($source === 'perso' ? 'user_tasks' : 'delegate_todos')->where('id', $id)->delete();
    }
}
