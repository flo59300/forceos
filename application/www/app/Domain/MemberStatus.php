<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Statut de cotisation d'un adhérent : port exact de compute_statuses_bulk() (2.x).
 *
 *  - aucun timbre de l'année : « sympathisant » ;
 *  - seuil annuel du site atteint (s'il est défini) : « a_jour » ;
 *  - sinon « a_jour » si chaque mois de janvier au mois de référence est payé
 *    (tous les mois pour une année passée, aucun pour une année future), sinon « en_attente ».
 *
 * Le statut est toujours calculé, jamais stocké.
 */
class MemberStatus
{
    public const LABELS = [
        'a_jour'       => 'À jour',
        'en_attente'   => 'En attente',
        'sympathisant' => 'Sympathisant',
        'radie'        => 'Radié',
    ];

    public function __construct(private BaseConnection $db)
    {
    }

    /**
     * @param int[]|null $memberIds null = tous les adhérents actifs du site
     *
     * @return array<int, array{status:string, paid_months:int[], nb_paid:int}>
     */
    public function forSite(int $siteId, int $year, ?array $memberIds = null, ?DateTimeInterface $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $seuil = (int) ($this->db->table('sites')->select('seuil_timbres_annuel')->where('id', $siteId)->get()->getRow()->seuil_timbres_annuel ?? 0);
        $upTo  = self::monthsDue($year, $now);

        $q = $this->db->table('sales s')
            ->select('s.member_id, GROUP_CONCAT(DISTINCT s.month ORDER BY s.month) AS months')
            ->where(['s.year' => $year, 's.type' => 'timbre'])
            ->groupBy('s.member_id');
        if ($memberIds !== null) {
            if ($memberIds === []) {
                return [];
            }
            $q->whereIn('s.member_id', array_map('intval', $memberIds));
        } else {
            $q->join('members m', 'm.id = s.member_id')->where(['m.site_id' => $siteId, 'm.is_radiated' => 0]);
        }
        $paidMap = [];
        foreach ($q->get()->getResultArray() as $r) {
            $paidMap[(int) $r['member_id']] = $r['months'] ? array_map('intval', explode(',', $r['months'])) : [];
        }

        $ids = $memberIds ?? array_column(
            $this->db->table('members')->select('id')->where(['site_id' => $siteId, 'is_radiated' => 0])->get()->getResultArray(),
            'id'
        );

        $out = [];
        foreach ($ids as $id) {
            $id   = (int) $id;
            $paid = $paidMap[$id] ?? [];
            $out[$id] = ['status' => self::statusFor($paid, $seuil, $upTo), 'paid_months' => $paid, 'nb_paid' => count($paid)];
        }

        return $out;
    }

    /** Règle pure, testable sans base. */
    public static function statusFor(array $paidMonths, int $seuil, int $monthsDue): string
    {
        $nb = count($paidMonths);
        if ($nb === 0) {
            return 'sympathisant';
        }
        if ($seuil > 0 && $nb >= $seuil) {
            return 'a_jour';
        }
        for ($m = 1; $m <= $monthsDue; $m++) {
            if (! in_array($m, $paidMonths, true)) {
                return 'en_attente';
            }
        }

        return 'a_jour';
    }

    /** Nombre de mois exigibles pour l'année donnée. */
    public static function monthsDue(int $year, DateTimeInterface $now): int
    {
        $cy = (int) $now->format('Y');

        return $year > $cy ? 0 : ($year === $cy ? (int) $now->format('n') : 12);
    }
}
