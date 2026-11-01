<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Carte de cotisation : timbres mensuels et carte annuelle.
 *
 * Règles identiques à la 2.x :
 *  - la carte est facturée automatiquement au premier timbre si l'adhérent n'en a pas ;
 *  - un même mois ne peut pas être encaissé deux fois ;
 *  - tarifs de l'année, ou du site à défaut.
 * Correction : un timbre déjà reversé à la trésorerie ne peut plus être retiré.
 */
class Stamps
{
    public const METHODS = ['especes' => 'Espèces', 'cheque' => 'Chèque', 'virement' => 'Virement', 'prelevement' => 'Prélèvement'];
    public const MONTHS  = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    public function __construct(private BaseConnection $db)
    {
    }

    /**
     * État de la carte d'une année : un élément par mois (null si non payé).
     *
     * @return array{months: array<int, array|null>, card: array|null}
     */
    public function card(int $memberId, int $year): array
    {
        $rows = $this->db->table('sales s')
            ->select("s.id, s.type, s.month, s.amount, s.payment_method, s.is_reversed, s.created_at, CONCAT(u.first_name, ' ', u.last_name) AS seller")
            ->join('users u', 'u.id = s.sold_by', 'left')
            ->where(['s.member_id' => $memberId, 's.year' => $year])
            ->orderBy('s.created_at')->get()->getResultArray();
        $months = array_fill(1, 12, null);
        $card   = null;
        foreach ($rows as $r) {
            if ($r['type'] === 'carte') {
                $card ??= $r;
            } elseif ($months[(int) $r['month']] === null) {
                $months[(int) $r['month']] = $r;
            }
        }

        return ['months' => $months, 'card' => $card];
    }

    /** Historique complet, toutes années, du plus récent au plus ancien. */
    public function history(int $memberId): array
    {
        return $this->db->table('sales s')
            ->select("s.*, CONCAT(u.first_name, ' ', u.last_name) AS seller")
            ->join('users u', 'u.id = s.sold_by', 'left')
            ->where('s.member_id', $memberId)
            ->orderBy('s.year', 'DESC')->orderBy('s.created_at', 'DESC')->get()->getResultArray();
    }

    /**
     * Encaisse des mois pour un adhérent (en une transaction).
     *
     * @param array $member Ligne members (déjà contrôlée pour le périmètre)
     * @return array{added: int[], skipped: int[], card: bool, total: float}
     */
    public function collect(array $member, array $months, string $method, int $sellerId, int $year, array $tarifs): array
    {
        $months = array_values(array_unique(array_filter(array_map('intval', $months), static fn ($m) => $m >= 1 && $m <= 12)));
        sort($months);
        $method = isset(self::METHODS[$method]) ? $method : 'especes';
        $res    = ['added' => [], 'skipped' => [], 'card' => false, 'total' => 0.0];
        if ($months === []) {
            return $res;
        }

        $this->db->transStart();
        $paid = array_map('intval', array_column($this->db->table('sales')->select('month')
            ->where(['member_id' => $member['id'], 'year' => $year, 'type' => 'timbre'])->get()->getResultArray(), 'month'));
        $base = ['member_id' => (int) $member['id'], 'site_id' => (int) $member['site_id'], 'year' => $year, 'sold_by' => $sellerId, 'payment_method' => $method];

        $toAdd = array_values(array_diff($months, $paid));
        $res['skipped'] = array_values(array_intersect($months, $paid));
        if ($toAdd !== [] && ! (int) $member['has_card']) {
            $this->db->table('sales')->insert($base + ['type' => 'carte', 'month' => null, 'amount' => $tarifs['carte']]);
            $this->db->table('members')->where('id', $member['id'])->update(['has_card' => 1]);
            $res['card']   = true;
            $res['total'] += $tarifs['carte'];
        }
        foreach ($toAdd as $m) {
            $this->db->table('sales')->insert($base + ['type' => 'timbre', 'month' => $m, 'amount' => $tarifs['timbre']]);
            $res['added'][] = $m;
            $res['total']  += $tarifs['timbre'];
        }
        $this->db->transComplete();

        return $res;
    }

    /** @return string 'ok' | 'absent' | 'reversed' */
    public function remove(int $memberId, int $month, int $year): string
    {
        $sale = $this->db->table('sales')->where(['member_id' => $memberId, 'year' => $year, 'type' => 'timbre', 'month' => $month])->get()->getRowArray();
        if (! $sale) {
            return 'absent';
        }
        if ((int) $sale['is_reversed']) {
            return 'reversed';
        }
        $this->db->table('sales')->where('id', $sale['id'])->delete();

        return 'ok';
    }

    public static function monthList(array $months): string
    {
        $names = array_map(static fn ($m) => self::MONTHS[$m], $months);
        if (count($names) < 2) {
            return implode('', $names);
        }
        $last = array_pop($names);

        return implode(', ', $names) . ' et ' . $last;
    }
}
