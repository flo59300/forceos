<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Stock de timbres et de cartes : entrées − sorties − ventes (toutes années), comme la 2.x.
 */
class Stock
{
    public const TYPES = ['timbre' => 'Timbres', 'carte' => 'Cartes'];

    public function __construct(private BaseConnection $db, private int $siteId)
    {
    }

    public function level(string $type): int
    {
        $sum = fn (string $mv) => (int) ($this->db->table('stock_movements')->selectSum('quantity', 'q')
            ->where(['site_id' => $this->siteId, 'type' => $type, 'movement' => $mv])->get()->getRow()->q ?? 0);
        $sold = $this->db->table('sales')->where(['site_id' => $this->siteId, 'type' => $type])->countAllResults();

        return $sum('in') - $sum('out') - $sold;
    }

    public function movements(int $limit = 50): array
    {
        return $this->db->table('stock_movements sm')->select("sm.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name")
            ->join('users u', 'u.id = sm.user_id', 'left')->where('sm.site_id', $this->siteId)
            ->orderBy('sm.created_at', 'DESC')->orderBy('sm.id', 'DESC')->limit($limit)->get()->getResultArray();
    }

    public function add(string $type, string $movement, int $qty, string $reason, int $userId): bool
    {
        if (! isset(self::TYPES[$type]) || ! in_array($movement, ['in', 'out'], true) || $qty <= 0) {
            return false;
        }
        $this->db->table('stock_movements')->insert(['site_id' => $this->siteId, 'type' => $type, 'movement' => $movement, 'quantity' => $qty, 'reason' => $reason !== '' ? $reason : null, 'user_id' => $userId]);

        return true;
    }
}
