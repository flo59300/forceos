<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Messagerie interne d'un site (table messages, partagée avec la 2.x).
 * Le Super Admin n'apparaît jamais comme correspondant possible.
 */
class Messages
{
    public const MAX_LENGTH = 5000;

    public function __construct(private BaseConnection $db, private int $siteId, private int $userId)
    {
    }

    /** Conversations : dernier message et nombre de non-lus par correspondant. */
    public function conversations(): array
    {
        $uid  = $this->userId;
        $rows = $this->db->table('messages')
            ->select("IF(sender_id = {$uid}, recipient_id, sender_id) AS other_id, MAX(id) AS last_id, SUM(recipient_id = {$uid} AND is_read = 0) AS unread", false)
            ->where('site_id', $this->siteId)
            ->groupStart()->where('sender_id', $uid)->orWhere('recipient_id', $uid)->groupEnd()
            ->groupBy('other_id')->get()->getResultArray();
        if (! $rows) {
            return [];
        }
        $last  = array_column($this->db->table('messages')->select('id, body, sender_id, created_at')->whereIn('id', array_column($rows, 'last_id'))->get()->getResultArray(), null, 'id');
        $users = array_column($this->db->table('users')->select("id, CONCAT(first_name, ' ', last_name) AS name, role")
            ->whereIn('id', array_column($rows, 'other_id'))->where('active', 1)->where('role !=', 'super_admin')->get()->getResultArray(), null, 'id');
        $out = [];
        foreach ($rows as $r) {
            if (! isset($users[$r['other_id']])) {
                continue;
            }
            $m     = $last[$r['last_id']];
            $out[] = [
                'user_id' => (int) $r['other_id'], 'name' => $users[$r['other_id']]['name'], 'role' => $users[$r['other_id']]['role'],
                'unread' => (int) $r['unread'], 'body' => $m['body'], 'mine' => (int) $m['sender_id'] === $uid, 'at' => $m['created_at'],
            ];
        }
        usort($out, static fn ($a, $b) => strcmp($b['at'], $a['at']));

        return $out;
    }

    /** Correspondant valide : même site, actif, pas le Super Admin, pas soi-même. */
    public function contact(int $id): ?array
    {
        if ($id === $this->userId) {
            return null;
        }

        return $this->db->table('users')->select("id, first_name, last_name, CONCAT(first_name, ' ', last_name) AS name, role")
            ->where(['id' => $id, 'site_id' => $this->siteId, 'active' => 1])->where('role !=', 'super_admin')->get()->getRowArray();
    }

    public function contacts(): array
    {
        return $this->db->table('users')->select("id, CONCAT(first_name, ' ', last_name) AS name, role")
            ->where(['site_id' => $this->siteId, 'active' => 1])->where('id !=', $this->userId)->where('role !=', 'super_admin')
            ->orderBy('last_name')->get()->getResultArray();
    }

    /** Fil avec un correspondant (200 derniers messages) ; marque comme lus ceux reçus. */
    public function thread(int $otherId): array
    {
        $this->db->table('messages')->where(['site_id' => $this->siteId, 'sender_id' => $otherId, 'recipient_id' => $this->userId, 'is_read' => 0])->update(['is_read' => 1]);
        $rows = $this->db->table('messages')
            ->where('site_id', $this->siteId)
            ->groupStart()
                ->groupStart()->where('sender_id', $this->userId)->where('recipient_id', $otherId)->groupEnd()
                ->orGroupStart()->where('sender_id', $otherId)->where('recipient_id', $this->userId)->groupEnd()
            ->groupEnd()
            ->orderBy('id', 'DESC')->limit(200)->get()->getResultArray();

        return array_reverse($rows);
    }

    public function send(int $to, string $body): int
    {
        $this->db->table('messages')->insert(['site_id' => $this->siteId, 'sender_id' => $this->userId, 'recipient_id' => $to, 'body' => $body]);

        return (int) $this->db->insertID();
    }

    public function delete(int $id): bool
    {
        $this->db->table('messages')->where(['id' => $id, 'site_id' => $this->siteId])->delete();

        return $this->db->affectedRows() > 0;
    }
}
