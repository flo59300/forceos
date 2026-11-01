<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Heures de délégation : une seule règle de calcul pour tout le site.
 *
 * Crédit du mois  = quota + reliquat reporté (le reliquat inclut les heures reçues par transfert accepté)
 * Heures utilisées = saisies du mois, hors motif « convocation_direction »
 * Heures cédées    = transferts acceptés vers un autre délégué
 * Reste            = crédit − utilisées − cédées (négatif = dépassement)
 *
 * Corrige l'écart de la 2.x où le tableau de bord lisait le chrono de tournée (tour_logs).
 */
class Delegation
{
    public const NOT_COUNTED = 'convocation_direction';

    /** Motifs de la 2.x (même ordre, mêmes clés en base). */
    public const MOTIFS = [
        'reunion_cse'           => 'Réunion CSE',
        'reunion_cssct'         => 'Réunion CSSCT',
        'terrain'               => 'Terrain ou tournée',
        'formation'             => 'Formation',
        'negociation'           => 'Négociation',
        'info_salaries'         => 'Information des salariés',
        'permanence'            => 'Permanence syndicale',
        'convocation_direction' => 'Convocation de la direction (hors quota)',
        'autre'                 => 'Autre',
    ];

    public function __construct(private BaseConnection $db)
    {
    }

    /**
     * @return array{quota:int, carry:int, credit:int, used:int, given:int, remaining:int, available:int, pct:int}
     */
    public function month(int $userId, int $year, int $month): array
    {
        $h = $this->db->table('delegation_hours')->select('quota_minutes, carry_over_minutes')
            ->where(['user_id' => $userId, 'year' => $year, 'month' => $month])->get()->getRowArray();
        $quota = (int) ($h['quota_minutes'] ?? 0);
        $carry = (int) ($h['carry_over_minutes'] ?? 0);

        $used = (int) ($this->db->table('delegation_entries')->selectSum('duration_minutes', 'm')
            ->where('user_id', $userId)
            ->where('YEAR(entry_date)', $year, false)->where('MONTH(entry_date)', $month, false)
            ->where('motif !=', self::NOT_COUNTED)
            ->get()->getRow()->m ?? 0);

        $given = (int) ($this->db->table('delegation_transfers')->selectSum('minutes', 'm')
            ->where(['from_user_id' => $userId, 'year' => $year, 'month' => $month, 'status' => 'accepted'])
            ->get()->getRow()->m ?? 0);

        $credit    = $quota + $carry;
        $remaining = $credit - $used - $given;

        return [
            'quota' => $quota, 'carry' => $carry, 'credit' => $credit, 'used' => $used, 'given' => $given,
            'remaining' => $remaining, 'available' => max(0, $remaining),
            'pct' => $credit > 0 ? (int) round(($used + $given) / $credit * 100) : 0,
        ];
    }

    /** Transferts reçus en attente de réponse. */
    public function pendingIncoming(int $userId): array
    {
        return $this->db->table('delegation_transfers t')
            ->select("t.id, t.minutes, t.month, t.year, t.motif, CONCAT(u.first_name, ' ', u.last_name) AS from_name")
            ->join('users u', 'u.id = t.from_user_id')
            ->where(['t.to_user_id' => $userId, 't.status' => 'pending'])
            ->orderBy('t.created_at', 'DESC')->get()->getResultArray();
    }

    public static function format(int $minutes): string
    {
        $sign = $minutes < 0 ? '−' : '';
        $m    = abs($minutes);

        return $sign . intdiv($m, 60) . ' h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
    }

    /** Saisies d'un mois, de la plus récente à la plus ancienne. */
    public function entries(int $userId, int $year, int $month): array
    {
        return $this->db->table('delegation_entries')
            ->where('user_id', $userId)
            ->where('YEAR(entry_date)', $year, false)->where('MONTH(entry_date)', $month, false)
            ->orderBy('entry_date', 'DESC')->orderBy('start_time', 'DESC')->get()->getResultArray();
    }

    /**
     * Durée d'une saisie : plage horaire si elle est valide, sinon heures + minutes.
     * @return array{0:int, 1:?string, 2:?string} durée, début, fin
     */
    public static function duration(?string $start, ?string $end, int $hours, int $minutes): array
    {
        if ($start && $end && preg_match('/^\d{2}:\d{2}$/', $start) && preg_match('/^\d{2}:\d{2}$/', $end) && $start < $end) {
            [$sh, $sm] = array_map('intval', explode(':', $start));
            [$eh, $em] = array_map('intval', explode(':', $end));

            return [($eh * 60 + $em) - ($sh * 60 + $sm), $start, $end];
        }

        return [max(0, $hours) * 60 + max(0, min(59, $minutes)), null, null];
    }

    public function addEntry(int $userId, string $date, int $duration, string $motif, ?string $start, ?string $end, ?string $desc): int
    {
        $this->db->table('delegation_entries')->insert([
            'user_id' => $userId, 'entry_date' => $date, 'start_time' => $start, 'end_time' => $end,
            'duration_minutes' => $duration, 'motif' => $motif, 'description' => $desc ?: null, 'is_auto' => 0,
        ]);

        return (int) $this->db->insertID();
    }

    public function deleteEntry(int $userId, int $id): bool
    {
        $this->db->table('delegation_entries')->where(['id' => $id, 'user_id' => $userId])->delete();

        return $this->db->affectedRows() > 0;
    }

    /** Quota d'un mois, ou des douze mois de l'année si $month = 0. */
    public function setQuota(int $userId, int $year, int $month, int $minutes): void
    {
        foreach ($month === 0 ? range(1, 12) : [$month] as $m) {
            $row = $this->db->table('delegation_hours')->where(['user_id' => $userId, 'year' => $year, 'month' => $m])->get()->getRow();
            if ($row) {
                $this->db->table('delegation_hours')->where('id', $row->id)->update(['quota_minutes' => $minutes]);
            } else {
                $this->db->table('delegation_hours')->insert(['user_id' => $userId, 'year' => $year, 'month' => $m, 'quota_minutes' => $minutes, 'carry_over_minutes' => 0]);
            }
        }
    }

    /**
     * Propose des heures à un autre délégué du site (en attente de sa réponse).
     * @return string 'ok' | 'insufficient' | 'invalid'
     */
    public function propose(int $siteId, int $fromId, int $toId, int $year, int $month, int $minutes, ?string $motif): string
    {
        $ok = $toId !== $fromId && $minutes > 0
            && $this->db->table('users')->where(['id' => $toId, 'site_id' => $siteId, 'active' => 1])->where('role !=', 'super_admin')->countAllResults();
        if (! $ok) {
            return 'invalid';
        }
        if ($minutes > $this->month($fromId, $year, $month)['available']) {
            return 'insufficient';
        }
        $this->db->table('delegation_transfers')->insert([
            'site_id' => $siteId, 'from_user_id' => $fromId, 'to_user_id' => $toId,
            'year' => $year, 'month' => $month, 'minutes' => $minutes, 'motif' => $motif ?: null,
        ]);

        return 'ok';
    }

    /** Acceptation ou refus d'un transfert reçu. Les heures acceptées s'ajoutent au reliquat du mois. */
    public function respond(int $userId, int $transferId, bool $accept): ?array
    {
        $t = $this->db->table('delegation_transfers')->where(['id' => $transferId, 'to_user_id' => $userId, 'status' => 'pending'])->get()->getRowArray();
        if (! $t) {
            return null;
        }
        $this->db->transStart();
        $this->db->table('delegation_transfers')->where('id', $transferId)->update(['status' => $accept ? 'accepted' : 'rejected', 'responded_at' => date('Y-m-d H:i:s')]);
        if ($accept) {
            $row = $this->db->table('delegation_hours')->where(['user_id' => $userId, 'year' => $t['year'], 'month' => $t['month']])->get()->getRow();
            if ($row) {
                $this->db->table('delegation_hours')->where('id', $row->id)->set('carry_over_minutes', 'carry_over_minutes + ' . (int) $t['minutes'], false)->update();
            } else {
                $this->db->table('delegation_hours')->insert(['user_id' => $userId, 'year' => $t['year'], 'month' => $t['month'], 'quota_minutes' => 0, 'carry_over_minutes' => (int) $t['minutes']]);
            }
        }
        $this->db->transComplete();

        return $t;
    }

    /**
     * Reporte le reste d'un mois sur le suivant.
     * Correction de la 2.x : le reliquat du mois suivant = reste reporté + heures reçues par transfert
     * pour ce mois (la 2.x écrasait les heures reçues), et le reste déduit les heures cédées.
     *
     * @return int minutes reportées
     */
    public function carryOver(int $userId, int $year, int $month): int
    {
        $remaining = $this->month($userId, $year, $month)['available'];
        [$ty, $tm] = $month === 12 ? [$year + 1, 1] : [$year, $month + 1];
        $received  = (int) ($this->db->table('delegation_transfers')->selectSum('minutes', 'm')
            ->where(['to_user_id' => $userId, 'year' => $ty, 'month' => $tm, 'status' => 'accepted'])->get()->getRow()->m ?? 0);

        $row = $this->db->table('delegation_hours')->where(['user_id' => $userId, 'year' => $ty, 'month' => $tm])->get()->getRow();
        if ($row) {
            $this->db->table('delegation_hours')->where('id', $row->id)->update(['carry_over_minutes' => $remaining + $received]);
        } else {
            $this->db->table('delegation_hours')->insert(['user_id' => $userId, 'year' => $ty, 'month' => $tm, 'quota_minutes' => 0, 'carry_over_minutes' => $remaining + $received]);
        }

        return $remaining;
    }

    /** Totaux par motif pour un mois. */
    public function byMotif(int $userId, int $year, int $month): array
    {
        return $this->db->table('delegation_entries')->select('motif, SUM(duration_minutes) AS total')
            ->where('user_id', $userId)->where('YEAR(entry_date)', $year, false)->where('MONTH(entry_date)', $month, false)
            ->groupBy('motif')->orderBy('total', 'DESC')->get()->getResultArray();
    }

    /** Transferts envoyés par l'utilisateur pour un mois. */
    public function sent(int $userId, int $year, int $month): array
    {
        return $this->db->table('delegation_transfers t')
            ->select("t.*, CONCAT(u.first_name, ' ', u.last_name) AS to_name")
            ->join('users u', 'u.id = t.to_user_id')
            ->where(['t.from_user_id' => $userId, 't.year' => $year, 't.month' => $month])
            ->orderBy('t.created_at', 'DESC')->get()->getResultArray();
    }
}
