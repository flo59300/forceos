<?php

namespace App\Domain;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;

/**
 * Adhérents : recherche, fiche, création, modification, radiation, transfert, actions groupées.
 *
 * Périmètre (corrige un écart de la 2.x) : un collecteur ne voit ET ne modifie que ses adhérents,
 * y compris par requête directe ; le secrétaire agit sur tout son site.
 */
class Members
{
    public const COLLEGES = ['college_1' => '1er collège', 'college_2' => '2e collège', 'college_3' => '3e collège'];
    public const PAYMENT_MODES = ['manuel' => 'Paiement manuel', 'prelevement' => 'Prélèvement'];
    public const PER_PAGE = 25;

    /** Champs modifiables d'une fiche. */
    public const FIELDS = ['first_name', 'last_name', 'matricule', 'email', 'phone', 'address', 'city', 'postal_code',
        'date_birth', 'date_entry', 'date_adhesion', 'secteur_id', 'metier_id', 'categorie', 'genre', 'tournee_id',
        'payment_mode', 'assigned_user_id', 'notes'];

    public function __construct(private BaseConnection $db, private int $siteId, private int $userId, private string $role)
    {
    }

    public function isSecretary(): bool
    {
        return $this->role === 'secretaire';
    }

    /** Requête de base limitée au périmètre de l'utilisateur. */
    private function scoped(bool $radiated = false): BaseBuilder
    {
        $q = $this->db->table('members m')->where('m.site_id', $this->siteId)->where('m.is_radiated', $radiated ? 1 : 0);
        if ($this->role === 'collecteur') {
            $q->where('m.assigned_user_id', $this->userId);
        }

        return $q;
    }

    /**
     * Recherche paginée. Le filtre de statut est appliqué après calcul (le statut n'est jamais stocké).
     *
     * @return array{rows: array, total: int, statuses: array}
     */
    public function search(array $f, int $page, int $year): array
    {
        $q = $this->scoped()
            ->select("m.id, m.first_name, m.last_name, m.matricule, m.has_card, m.payment_mode, m.categorie, m.assigned_user_id, s.name AS secteur_name, t.name AS tournee_name, CONCAT(u.first_name, ' ', u.last_name) AS collecteur_name")
            ->join('secteurs s', 's.id = m.secteur_id', 'left')
            ->join('tournees t', 't.id = m.tournee_id', 'left')
            ->join('users u', 'u.id = m.assigned_user_id', 'left');

        if (($term = trim((string) ($f['q'] ?? ''))) !== '') {
            // Valeur échappée par la base (aucune concaténation de texte saisi)
            $pattern = $this->db->escape('%' . $this->db->escapeLikeString($term) . '%');
            $q->groupStart()
                ->like('m.first_name', $term)->orLike('m.last_name', $term)->orLike('m.matricule', $term)
                ->orWhere("CONCAT(m.first_name, ' ', m.last_name) LIKE {$pattern}", null, false)
                ->orWhere("CONCAT(m.last_name, ' ', m.first_name) LIKE {$pattern}", null, false)
                ->groupEnd();
        }
        foreach (['secteur' => 'm.secteur_id', 'tournee' => 'm.tournee_id', 'collecteur' => 'm.assigned_user_id'] as $k => $col) {
            if (! empty($f[$k])) {
                $q->where($col, (int) $f[$k]);
            }
        }
        if (isset(self::PAYMENT_MODES[$f['paiement'] ?? ''])) {
            $q->where('m.payment_mode', $f['paiement']);
        }
        if (isset(self::COLLEGES[$f['college'] ?? ''])) {
            $q->where('m.categorie', $f['college']);
        }
        $q->orderBy('m.last_name')->orderBy('m.first_name');

        $all      = $q->get()->getResultArray();
        $statuses = (new MemberStatus($this->db))->forSite($this->siteId, $year, array_column($all, 'id'));
        if (isset(MemberStatus::LABELS[$f['statut'] ?? ''])) {
            $all = array_values(array_filter($all, static fn ($m) => ($statuses[(int) $m['id']]['status'] ?? '') === $f['statut']));
        }

        return [
            'rows'     => array_slice($all, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'total'    => count($all),
            'statuses' => $statuses,
        ];
    }

    /** Fiche d'un adhérent dans le périmètre, ou null. */
    public function find(int $id, bool $includeRadiated = false): ?array
    {
        $q = $this->db->table('members m')
            ->select("m.*, s.name AS secteur_name, me.name AS metier_name, t.name AS tournee_name, CONCAT(u.first_name, ' ', u.last_name) AS collecteur_name")
            ->join('secteurs s', 's.id = m.secteur_id', 'left')
            ->join('metiers me', 'me.id = m.metier_id', 'left')
            ->join('tournees t', 't.id = m.tournee_id', 'left')
            ->join('users u', 'u.id = m.assigned_user_id', 'left')
            ->where(['m.id' => $id, 'm.site_id' => $this->siteId]);
        if (! $includeRadiated) {
            $q->where('m.is_radiated', 0);
        }
        if ($this->role === 'collecteur') {
            $q->where('m.assigned_user_id', $this->userId);
        }

        return \App\Libraries\Forceos\ChampsChiffres::dechiffrer($q->get()->getRowArray());
    }

    /** Nettoie les données d'un formulaire de fiche selon le rôle et le site. */
    public function clean(array $in, bool $creating): array
    {
        $d = [];
        foreach (self::FIELDS as $k) {
            $v = is_string($in[$k] ?? null) ? trim($in[$k]) : ($in[$k] ?? null);
            $d[$k] = ($v === '' ? null : $v);
        }
        $d['genre']        = in_array($d['genre'], ['H', 'F'], true) ? $d['genre'] : null;
        $d['categorie']    = isset(self::COLLEGES[$d['categorie'] ?? '']) ? $d['categorie'] : null;
        $d['payment_mode'] = isset(self::PAYMENT_MODES[$d['payment_mode'] ?? '']) ? $d['payment_mode'] : 'manuel';
        foreach (['secteur_id' => 'secteurs', 'metier_id' => 'metiers', 'tournee_id' => 'tournees'] as $k => $table) {
            $d[$k] = $d[$k] !== null && $this->db->table($table)->where(['id' => (int) $d[$k], 'site_id' => $this->siteId])->countAllResults() ? (int) $d[$k] : null;
        }
        // Le collecteur garde ses adhérents ; le secrétaire choisit parmi les utilisateurs actifs du site
        if ($this->role === 'collecteur') {
            $d['assigned_user_id'] = $this->userId;
        } elseif ($d['assigned_user_id'] !== null) {
            $ok = $this->db->table('users')->where(['id' => (int) $d['assigned_user_id'], 'site_id' => $this->siteId, 'active' => 1])->where('role !=', 'super_admin')->countAllResults();
            $d['assigned_user_id'] = $ok ? (int) $d['assigned_user_id'] : null;
        }
        if ($creating) {
            $d['date_adhesion'] ??= date('Y-m-d');
        } else {
            unset($d['date_adhesion']);
        }

        return $d;
    }

    public function create(array $data, int $year): int
    {
        $this->db->table('members')->insert(\App\Libraries\Forceos\ChampsChiffres::chiffrer($data) + ['site_id' => $this->siteId, 'year' => $year]);

        return (int) $this->db->insertID();
    }

    public function update(int $id, array $data): bool
    {
        if (! $this->find($id)) {
            return false;
        }
        $this->db->table('members')->where(['id' => $id, 'site_id' => $this->siteId])->update(\App\Libraries\Forceos\ChampsChiffres::chiffrer($data));

        return true;
    }

    // ------------------------------------------------------------------
    // Réservé au secrétaire
    // ------------------------------------------------------------------

    public function radiate(int $id, string $motif): bool
    {
        if (! $this->isSecretary() || ! $this->find($id)) {
            return false;
        }
        $this->db->table('members')->where(['id' => $id, 'site_id' => $this->siteId])->update([
            'is_radiated' => 1, 'radiation_date' => date('Y-m-d'),
            'radiation_motif' => $motif !== '' ? $motif : null, 'radiation_by' => $this->userId,
        ]);

        return true;
    }

    /** @return string 'ok' | 'same' | 'invalid' */
    public function transfer(int $id, int $toUserId): string
    {
        $m = $this->isSecretary() ? $this->find($id) : null;
        $u = $this->db->table('users')->where(['id' => $toUserId, 'site_id' => $this->siteId, 'active' => 1])->where('role !=', 'super_admin')->get()->getRowArray();
        if (! $m || ! $u) {
            return 'invalid';
        }
        if ((int) $m['assigned_user_id'] === $toUserId) {
            return 'same';
        }
        $this->db->table('members')->where('id', $id)->update(['assigned_user_id' => $toUserId]);

        return 'ok';
    }

    /**
     * Actions groupées (mêmes opérations que la 2.x), limitées aux adhérents actifs du site.
     *
     * @return int nombre d'adhérents modifiés, -1 si l'opération est invalide
     */
    public function bulk(array $ids, string $op, string $value): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (! $this->isSecretary() || $ids === []) {
            return -1;
        }
        $set = match ($op) {
            'collecteur' => $this->db->table('users')->where(['id' => (int) $value, 'site_id' => $this->siteId, 'active' => 1])->where('role !=', 'super_admin')->countAllResults() ? ['assigned_user_id' => (int) $value] : null,
            'secteur'    => $this->db->table('secteurs')->where(['id' => (int) $value, 'site_id' => $this->siteId])->countAllResults() ? ['secteur_id' => (int) $value] : null,
            'tournee'    => $this->db->table('tournees')->where(['id' => (int) $value, 'site_id' => $this->siteId])->countAllResults() ? ['tournee_id' => (int) $value] : null,
            'paiement'   => isset(self::PAYMENT_MODES[$value]) ? ['payment_mode' => $value] : null,
            'genre'      => in_array($value, ['H', 'F'], true) ? ['genre' => $value] : null,
            'college'    => isset(self::COLLEGES[$value]) ? ['categorie' => $value] : null,
            'radier'     => ['is_radiated' => 1, 'radiation_date' => date('Y-m-d'), 'radiation_motif' => $value !== '' ? $value : 'Radiation groupée', 'radiation_by' => $this->userId],
            default      => null,
        };
        if ($set === null) {
            return -1;
        }
        $this->db->table('members')->whereIn('id', $ids)->where(['site_id' => $this->siteId, 'is_radiated' => 0])->update($set);

        return $this->db->affectedRows();
    }
}
