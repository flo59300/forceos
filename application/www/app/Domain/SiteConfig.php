<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Configuration d'un site : informations, tarifs, seuil, effectif, secteurs, tournées, métiers, années.
 *
 * Corrections de la 2.x : un secteur parent doit appartenir au site ; l'année courante
 * ne peut être choisie que parmi les années du site (la 2.x pouvait laisser le site sans année courante).
 */
class SiteConfig
{
    public function __construct(private BaseConnection $db, private int $siteId)
    {
    }

    public function site(): array
    {
        return $this->db->table('sites')->where('id', $this->siteId)->get()->getRowArray() ?? [];
    }

    public function updateInfo(string $name, string $address, bool $board): void
    {
        $this->db->table('sites')->where('id', $this->siteId)->update(['name' => $name, 'address' => $address !== '' ? $address : null, 'bulletin_board_enabled' => $board ? 1 : 0]);
    }

    /** Tarifs du site et de l'année courante (comme la 2.x). */
    public function updateTarifs(float $timbre, float $carte): void
    {
        $this->db->table('sites')->where('id', $this->siteId)->update(['tarif_timbre' => $timbre, 'tarif_carte' => $carte]);
        $this->db->table('years')->where(['site_id' => $this->siteId, 'is_current' => 1])->update(['tarif_timbre' => $timbre, 'tarif_carte' => $carte]);
    }

    public function updateSeuil(?int $seuil): void
    {
        $this->db->table('sites')->where('id', $this->siteId)->update(['seuil_timbres_annuel' => $seuil && $seuil > 0 ? $seuil : null]);
    }

    public function updateEffectif(?int $n): void
    {
        $this->db->table('sites')->where('id', $this->siteId)->update(['effectif_site' => $n && $n > 0 ? $n : null]);
    }

    // --- Secteurs (arborescence) ---

    public function secteurs(): array
    {
        return Site::flattenTree($this->db->table('secteurs')->select('id, name, parent_id')
            ->where(['site_id' => $this->siteId, 'active' => 1])->orderBy('sort_order')->orderBy('name')->get()->getResultArray());
    }

    public function addSecteur(string $name, ?int $parentId): bool
    {
        if ($parentId !== null && ! $this->owns('secteurs', $parentId)) {
            return false;
        }
        $this->db->table('secteurs')->insert(['site_id' => $this->siteId, 'name' => $name, 'parent_id' => $parentId]);

        return true;
    }

    public function renameSecteur(int $id, string $name): bool
    {
        $this->db->table('secteurs')->where(['id' => $id, 'site_id' => $this->siteId])->update(['name' => $name]);

        return $this->db->affectedRows() > 0;
    }

    /** Désactive le secteur et toute sa descendance (la 2.x s'arrêtait aux enfants directs). */
    public function deleteSecteur(int $id): int
    {
        if (! $this->owns('secteurs', $id)) {
            return 0;
        }
        $ids   = [$id];
        $front = [$id];
        while ($front) {
            $front = array_values(array_diff(array_map('intval', array_column($this->db->table('secteurs')->select('id')->where('site_id', $this->siteId)->whereIn('parent_id', $front)->get()->getResultArray(), 'id')), $ids));
            $ids   = array_merge($ids, $front);
        }
        $this->db->table('secteurs')->whereIn('id', $ids)->where('site_id', $this->siteId)->update(['active' => 0]);

        return count($ids);
    }

    // --- Tournées et métiers (listes simples) ---

    public function items(string $table): array
    {
        return $this->db->table($table)->where(['site_id' => $this->siteId, 'active' => 1])->orderBy('name')->get()->getResultArray();
    }

    public function addItem(string $table, string $name, ?string $desc = null): void
    {
        $row = ['site_id' => $this->siteId, 'name' => $name];
        if ($table === 'tournees') {
            $row['description'] = $desc ?: null;
        }
        $this->db->table($table)->insert($row);
    }

    public function editItem(string $table, int $id, string $name, ?string $desc = null): bool
    {
        $row = ['name' => $name];
        if ($table === 'tournees') {
            $row['description'] = $desc ?: null;
        }
        $this->db->table($table)->where(['id' => $id, 'site_id' => $this->siteId])->update($row);

        return $this->db->affectedRows() > 0;
    }

    public function deleteItem(string $table, int $id): bool
    {
        $this->db->table($table)->where(['id' => $id, 'site_id' => $this->siteId])->update(['active' => 0]);

        return $this->db->affectedRows() > 0;
    }

    // --- Années de cotisation ---

    public function years(): array
    {
        return $this->db->table('years')->where('site_id', $this->siteId)->orderBy('year', 'DESC')->get()->getResultArray();
    }

    /** @return string 'ok' | 'exists' | 'invalid' */
    public function addYear(int $year): string
    {
        if ($year < 2020 || $year > 2100) {
            return 'invalid';
        }
        if ($this->db->table('years')->where(['site_id' => $this->siteId, 'year' => $year])->countAllResults()) {
            return 'exists';
        }
        $s = $this->site();
        $this->db->table('years')->insert(['site_id' => $this->siteId, 'year' => $year, 'is_current' => 0, 'tarif_timbre' => $s['tarif_timbre'], 'tarif_carte' => $s['tarif_carte']]);

        return 'ok';
    }

    public function setCurrentYear(int $yearId): bool
    {
        if (! $this->owns('years', $yearId)) {
            return false;
        }
        $this->db->transStart();
        $this->db->table('years')->where('site_id', $this->siteId)->update(['is_current' => 0]);
        $this->db->table('years')->where(['id' => $yearId, 'site_id' => $this->siteId])->update(['is_current' => 1]);
        $this->db->transComplete();

        return true;
    }

    private function owns(string $table, int $id): bool
    {
        return $this->db->table($table)->where(['id' => $id, 'site_id' => $this->siteId])->countAllResults() > 0;
    }
}
