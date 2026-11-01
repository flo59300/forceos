<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Panneau d'affichage d'un site (table announcements et colonne sites.bulletin_board_enabled, partagées avec la 2.x).
 */
class Announcements
{
    public const PRIORITIES = ['normale' => 'Normale', 'importante' => 'Importante', 'urgente' => 'Urgente'];

    public function __construct(private BaseConnection $db, private int $siteId)
    {
    }

    public function enabled(): bool
    {
        return (bool) ($this->db->table('sites')->select('bulletin_board_enabled')->where('id', $this->siteId)->get()->getRow()->bulletin_board_enabled ?? false);
    }

    public function setEnabled(bool $on): void
    {
        $this->db->table('sites')->where('id', $this->siteId)->update(['bulletin_board_enabled' => $on ? 1 : 0]);
    }

    /**
     * Annonces visibles (actives et non expirées), ou toutes pour le secrétaire.
     * Même ordre que la 2.x : épinglées d'abord, puis les plus récentes.
     */
    public function list(bool $all, ?int $limit = null): array
    {
        $b = $this->db->table('announcements a')
            ->select("a.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name", false)
            ->join('users u', 'u.id = a.created_by', 'left')
            ->where('a.site_id', $this->siteId);
        if (! $all) {
            $b->where('a.active', 1)->where('(a.expires_at IS NULL OR a.expires_at >= CURDATE())', null, false);
        }
        $b->orderBy('a.pinned', 'DESC')->orderBy('a.created_at', 'DESC')->orderBy('a.id', 'DESC');
        if ($limit) {
            $b->limit($limit);
        }

        return $b->get()->getResultArray();
    }

    /** Pour « Ma journée » : rien si le panneau est désactivé (comme le widget de la 2.x). */
    public function forHub(int $limit = 3): array
    {
        return $this->enabled() ? $this->list(false, $limit) : [];
    }

    public function find(int $id): ?array
    {
        return $this->db->table('announcements')->where(['id' => $id, 'site_id' => $this->siteId])->get()->getRowArray();
    }

    public function save(array $d, int $userId, int $id = 0): int
    {
        $row = [
            'title' => $d['title'], 'body' => $d['body'], 'priority' => $d['priority'],
            'pinned' => $d['pinned'] ? 1 : 0, 'expires_at' => $d['expires_at'] ?: null,
        ];
        if ($id) {
            $this->db->table('announcements')->where(['id' => $id, 'site_id' => $this->siteId])->update($row);

            return $id;
        }
        $this->db->table('announcements')->insert($row + ['site_id' => $this->siteId, 'created_by' => $userId]);

        return (int) $this->db->insertID();
    }

    public function toggle(int $id): ?bool
    {
        $a = $this->find($id);
        if (! $a) {
            return null;
        }
        $this->db->table('announcements')->where('id', $id)->update(['active' => $a['active'] ? 0 : 1]);

        return ! $a['active'];
    }

    public function delete(int $id): bool
    {
        $this->db->table('announcements')->where(['id' => $id, 'site_id' => $this->siteId])->delete();

        return $this->db->affectedRows() > 0;
    }

    public static function isExpired(array $a): bool
    {
        return $a['expires_at'] && $a['expires_at'] < date('Y-m-d');
    }
}
