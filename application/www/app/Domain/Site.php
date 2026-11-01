<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Réglages d'un site : année de cotisation courante et tarifs (mêmes règles que la 2.x).
 */
class Site
{
    public function __construct(private BaseConnection $db)
    {
    }

    /** Année courante du site (table years, is_current = 1), sinon l'année civile. */
    public function currentYear(int $siteId): int
    {
        $y = $this->db->table('years')->select('year')->where(['site_id' => $siteId, 'is_current' => 1])->get()->getRow();

        return $y ? (int) $y->year : (int) date('Y');
    }

    /** Tarifs de l'année, ou du site à défaut. @return array{timbre: float, carte: float} */
    public function tarifs(int $siteId, int $year): array
    {
        $y = $this->db->table('years')->select('tarif_timbre, tarif_carte')->where(['site_id' => $siteId, 'year' => $year])->get()->getRowArray();
        $s = $this->db->table('sites')->select('tarif_timbre, tarif_carte')->where('id', $siteId)->get()->getRowArray();
        $src = $y ?: $s ?: ['tarif_timbre' => 0, 'tarif_carte' => 0];

        return ['timbre' => (float) $src['tarif_timbre'], 'carte' => (float) $src['tarif_carte']];
    }

    /** Listes de référence du site (secteurs en arbre aplati, métiers, tournées, collecteurs). */
    public function lists(int $siteId): array
    {
        $secteurs = $this->db->table('secteurs')->select('id, name, parent_id')->where(['site_id' => $siteId, 'active' => 1])->orderBy('sort_order')->orderBy('name')->get()->getResultArray();

        return [
            'secteurs'    => self::flattenTree($secteurs),
            'metiers'     => $this->db->table('metiers')->select('id, name')->where(['site_id' => $siteId, 'active' => 1])->orderBy('name')->get()->getResultArray(),
            'tournees'    => $this->db->table('tournees')->select('id, name')->where(['site_id' => $siteId, 'active' => 1])->orderBy('name')->get()->getResultArray(),
            'collecteurs' => $this->db->table('users')->select("id, CONCAT(first_name, ' ', last_name) AS name")
                ->where(['site_id' => $siteId, 'active' => 1])->whereIn('role', ['collecteur', 'secretaire'])
                ->orderBy('last_name')->get()->getResultArray(),
        ];
    }

    /** Arbre des secteurs aplati, avec profondeur pour l'indentation. */
    public static function flattenTree(array $rows, ?int $parent = null, int $depth = 0): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (($r['parent_id'] === null ? null : (int) $r['parent_id']) === $parent) {
                $out[] = ['id' => (int) $r['id'], 'name' => $r['name'], 'depth' => $depth];
                array_push($out, ...self::flattenTree($rows, (int) $r['id'], $depth + 1));
            }
        }

        return $out;
    }
}
