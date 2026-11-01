<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Finances d'un site : paiements, prélèvements, reversements, trésorerie.
 * Règles de calcul identiques à la 2.x (paiements.php, reversements.php, tresorerie.php).
 *
 * Corrections :
 *  - l'annulation d'un prélèvement est réservée au secrétaire et au trésorier,
 *    et refusée si la vente a déjà été reversée ;
 *  - un reversement ne peut viser qu'un utilisateur actif du site ;
 *  - l'export comptable neutralise les formules (injection CSV).
 */
class Finance
{
    public const REV_METHODS = ['especes' => 'Espèces', 'cheque' => 'Chèque', 'virement' => 'Virement'];
    public const PER_PAGE    = 30;

    public function __construct(private BaseConnection $db, private int $siteId)
    {
    }

    // ------------------------------------------------------------------
    // Paiements
    // ------------------------------------------------------------------

    /** @return array{rows: array, total: int, amount: float} */
    public function payments(int $year, array $f, int $page, ?int $onlySeller = null): array
    {
        $q = $this->db->table('sales s')->join('members m', 'm.id = s.member_id')
            ->where(['s.site_id' => $this->siteId, 's.year' => $year]);
        if ($onlySeller !== null) {
            $q->where('s.sold_by', $onlySeller);
        } elseif (! empty($f['collecteur'])) {
            $q->where('s.sold_by', (int) $f['collecteur']);
        }
        if (! empty($f['mois'])) {
            $q->where('s.month', (int) $f['mois']);
        }
        if (isset(Stamps::METHODS[$f['mode'] ?? ''])) {
            $q->where('s.payment_method', $f['mode']);
        }
        if (($t = trim((string) ($f['q'] ?? ''))) !== '') {
            $q->groupStart()->like('m.first_name', $t)->orLike('m.last_name', $t)->groupEnd();
        }
        $agg  = (clone $q)->select('COUNT(*) AS n, COALESCE(SUM(s.amount), 0) AS total')->get()->getRow();
        $rows = $q->select("s.*, m.first_name AS m_first, m.last_name AS m_last, CONCAT(u.first_name, ' ', u.last_name) AS seller")
            ->join('users u', 'u.id = s.sold_by', 'left')
            ->orderBy('s.created_at', 'DESC')->orderBy('s.id', 'DESC')
            ->limit(self::PER_PAGE, ($page - 1) * self::PER_PAGE)->get()->getResultArray();

        return ['rows' => $rows, 'total' => (int) $agg->n, 'amount' => (float) $agg->total];
    }

    // ------------------------------------------------------------------
    // Prélèvements
    // ------------------------------------------------------------------

    /** Mois déjà générés pour l'année (au moins un timbre en prélèvement). */
    public function generatedMonths(int $year): array
    {
        return array_map('intval', array_column($this->db->table('sales')->distinct()->select('month')
            ->where(['site_id' => $this->siteId, 'year' => $year, 'type' => 'timbre', 'payment_method' => 'prelevement'])
            ->get()->getResultArray(), 'month'));
    }

    /** Adhérents actifs en prélèvement. */
    public function prelevMembers(): array
    {
        return $this->db->table('members')->select('id, has_card, site_id')
            ->where(['site_id' => $this->siteId, 'is_radiated' => 0, 'payment_mode' => 'prelevement'])->get()->getResultArray();
    }

    /** @return array{generated: int, skipped: int, cards: int} */
    public function generatePrelevements(int $year, int $month, int $actorId, array $tarifs): array
    {
        $done = array_flip(array_map('intval', array_column($this->db->table('sales')->select('member_id')
            ->where(['site_id' => $this->siteId, 'year' => $year, 'type' => 'timbre', 'month' => $month])->get()->getResultArray(), 'member_id')));
        $res = ['generated' => 0, 'skipped' => 0, 'cards' => 0];
        $this->db->transStart();
        foreach ($this->prelevMembers() as $pm) {
            if (isset($done[(int) $pm['id']])) {
                $res['skipped']++;

                continue;
            }
            $base = ['member_id' => (int) $pm['id'], 'site_id' => $this->siteId, 'year' => $year, 'sold_by' => $actorId, 'payment_method' => 'prelevement'];
            if (! (int) $pm['has_card']) {
                $this->db->table('sales')->insert($base + ['type' => 'carte', 'month' => null, 'amount' => $tarifs['carte']]);
                $this->db->table('members')->where('id', $pm['id'])->update(['has_card' => 1]);
                $res['cards']++;
            }
            $this->db->table('sales')->insert($base + ['type' => 'timbre', 'month' => $month, 'amount' => $tarifs['timbre']]);
            $res['generated']++;
        }
        $this->db->transComplete();

        return $res;
    }

    /** @return string 'ok' | 'absent' | 'reversed' */
    public function rejectPrelevement(int $saleId): string
    {
        $s = $this->db->table('sales')->where(['id' => $saleId, 'site_id' => $this->siteId, 'payment_method' => 'prelevement'])->get()->getRowArray();
        if (! $s) {
            return 'absent';
        }
        if ((int) $s['is_reversed']) {
            return 'reversed';
        }
        $this->db->table('sales')->where('id', $saleId)->delete();

        return 'ok';
    }

    /** Prélèvements d'un mois (pour annuler ceux refusés par la banque). */
    public function prelevementsOfMonth(int $year, int $month): array
    {
        return $this->db->table('sales s')->select('s.id, s.amount, s.is_reversed, m.first_name, m.last_name')
            ->join('members m', 'm.id = s.member_id')
            ->where(['s.site_id' => $this->siteId, 's.year' => $year, 's.month' => $month, 's.type' => 'timbre', 's.payment_method' => 'prelevement'])
            ->orderBy('m.last_name')->get()->getResultArray();
    }

    /** Contrôle d'un semestre (calcul de la 2.x), sans rien enregistrer. */
    public function semesterCheck(int $year, int $semester, float $tarifTimbre): array
    {
        $months    = $semester === 1 ? range(1, 6) : range(7, 12);
        $members   = $this->prelevMembers();
        $ids       = array_column($members, 'id');
        $present   = 0;
        if ($ids) {
            $present = $this->db->table('sales')->where(['site_id' => $this->siteId, 'year' => $year, 'type' => 'timbre', 'payment_method' => 'prelevement'])
                ->whereIn('month', $months)->whereIn('member_id', $ids)->countAllResults();
        }
        $expected = count($members) * 6;

        return [
            'nb_adherents' => count($members), 'nb_timbres_attendus' => $expected, 'nb_timbres_presents' => $present,
            'nb_manquants' => $expected - $present, 'montant_attendu' => $expected * $tarifTimbre, 'montant_present' => $present * $tarifTimbre,
        ];
    }

    public function validateSemester(int $year, int $semester, float $tarifTimbre, bool $force, string $notes, int $actorId): array
    {
        $c      = $this->semesterCheck($year, $semester, $tarifTimbre);
        $status = $c['nb_manquants'] === 0 ? 'conforme' : ($force ? 'force' : 'anomalies');
        $data   = $c + ['site_id' => $this->siteId, 'year' => $year, 'semester' => $semester, 'status' => $status,
            'notes' => $notes !== '' ? $notes : null, 'validated_by' => $actorId, 'validated_at' => date('Y-m-d H:i:s')];
        $id = $this->db->table('prelevement_validations')->select('id')->where(['site_id' => $this->siteId, 'year' => $year, 'semester' => $semester])->get()->getRow();
        if ($id) {
            $this->db->table('prelevement_validations')->where('id', $id->id)->update($data);
        } else {
            $this->db->table('prelevement_validations')->insert($data);
        }

        return $data;
    }

    public function validations(int $year): array
    {
        return $this->db->table('prelevement_validations v')->select("v.*, CONCAT(u.first_name, ' ', u.last_name) AS by_name")
            ->join('users u', 'u.id = v.validated_by', 'left')
            ->where(['v.site_id' => $this->siteId, 'v.year' => $year])->orderBy('v.semester')->get()->getResultArray();
    }

    // ------------------------------------------------------------------
    // Reversements
    // ------------------------------------------------------------------

    /** Sommes non reversées par personne pour l'année. */
    public function pendingBySeller(int $year, ?int $onlySeller = null): array
    {
        $q = $this->db->table('sales s')
            ->select("s.sold_by, CONCAT(u.first_name, ' ', u.last_name) AS name, COUNT(*) AS nb, SUM(s.amount) AS total, SUM(s.type = 'timbre') AS nb_timbres, SUM(s.type = 'carte') AS nb_cartes", false)
            ->join('users u', 'u.id = s.sold_by')
            ->where(['s.site_id' => $this->siteId, 's.year' => $year, 's.is_reversed' => 0])
            ->groupBy('s.sold_by')->orderBy('total', 'DESC');
        if ($onlySeller !== null) {
            $q->where('s.sold_by', $onlySeller);
        }

        return $q->get()->getResultArray();
    }

    /**
     * Déclare un reversement : toutes les ventes non reversées de l'année pour cette personne.
     * @return array{ok: bool, amount?: float, id?: int, reason?: string}
     */
    public function createReversement(int $year, int $userId, string $method, string $reference, string $notes): array
    {
        $isUser = $this->db->table('users')->where(['id' => $userId, 'site_id' => $this->siteId, 'active' => 1])->countAllResults();
        if (! $isUser) {
            return ['ok' => false, 'reason' => 'invalid'];
        }
        $sales = $this->db->table('sales')->select('id, amount, type')
            ->where(['sold_by' => $userId, 'year' => $year, 'is_reversed' => 0, 'site_id' => $this->siteId])->get()->getResultArray();
        if (! $sales) {
            return ['ok' => false, 'reason' => 'empty'];
        }
        $amount = array_sum(array_map('floatval', array_column($sales, 'amount')));
        $this->db->transStart();
        $this->db->table('reversements')->insert([
            'site_id' => $this->siteId, 'user_id' => $userId, 'amount' => $amount,
            'nb_timbres' => count(array_filter($sales, static fn ($s) => $s['type'] === 'timbre')),
            'nb_cartes' => count(array_filter($sales, static fn ($s) => $s['type'] === 'carte')),
            'payment_method' => isset(self::REV_METHODS[$method]) ? $method : 'especes',
            'reference' => $reference !== '' ? $reference : null, 'notes' => $notes !== '' ? $notes : null,
        ]);
        $id = (int) $this->db->insertID();
        $this->db->table('sales')->whereIn('id', array_column($sales, 'id'))->update(['is_reversed' => 1, 'reversement_id' => $id]);
        $this->db->transComplete();

        return ['ok' => true, 'amount' => $amount, 'id' => $id];
    }

    public function validateReversement(int $id, int $actorId): bool
    {
        $this->db->table('reversements')->where(['id' => $id, 'site_id' => $this->siteId])->where('validated_at IS NULL')
            ->update(['validated_by' => $actorId, 'validated_at' => date('Y-m-d H:i:s')]);

        return $this->db->affectedRows() > 0;
    }

    public function reversements(?int $onlyUser, int $page): array
    {
        $q = $this->db->table('reversements r')->where('r.site_id', $this->siteId);
        if ($onlyUser !== null) {
            $q->where('r.user_id', $onlyUser);
        }
        $total = (clone $q)->countAllResults();
        $rows  = $q->select("r.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name, CONCAT(v.first_name, ' ', v.last_name) AS validator_name")
            ->join('users u', 'u.id = r.user_id')->join('users v', 'v.id = r.validated_by', 'left')
            ->orderBy('r.created_at', 'DESC')->limit(20, ($page - 1) * 20)->get()->getResultArray();

        return ['rows' => $rows, 'total' => $total];
    }

    // ------------------------------------------------------------------
    // Trésorerie (année civile des reversements, année de cotisation des ventes : comme la 2.x)
    // ------------------------------------------------------------------

    public function treasury(int $year): array
    {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = ['timbres_nb' => 0, 'timbres_total' => 0.0, 'cartes_nb' => 0, 'cartes_total' => 0.0, 'rev_nb' => 0, 'rev_total' => 0.0];
        }
        $put = static function (array $rows, string $nb, string $tot) use (&$months) {
            foreach ($rows as $r) {
                if ($r['m'] !== null && isset($months[(int) $r['m']])) {
                    $months[(int) $r['m']][$nb]  = (int) $r['nb'];
                    $months[(int) $r['m']][$tot] = (float) $r['total'];
                }
            }
        };
        $put($this->db->query("SELECT month AS m, COUNT(*) AS nb, COALESCE(SUM(amount), 0) AS total FROM sales WHERE site_id = ? AND year = ? AND type = 'timbre' GROUP BY month", [$this->siteId, $year])->getResultArray(), 'timbres_nb', 'timbres_total');
        $put($this->db->query("SELECT MONTH(created_at) AS m, COUNT(*) AS nb, COALESCE(SUM(amount), 0) AS total FROM sales WHERE site_id = ? AND year = ? AND type = 'carte' GROUP BY MONTH(created_at)", [$this->siteId, $year])->getResultArray(), 'cartes_nb', 'cartes_total');
        $put($this->db->query('SELECT MONTH(created_at) AS m, COUNT(*) AS nb, COALESCE(SUM(amount), 0) AS total FROM reversements WHERE site_id = ? AND YEAR(created_at) = ? GROUP BY MONTH(created_at)', [$this->siteId, $year])->getResultArray(), 'rev_nb', 'rev_total');
        foreach ($months as &$mo) {
            $mo['recettes'] = $mo['timbres_total'] + $mo['cartes_total'];
            $mo['solde']    = $mo['recettes'] - $mo['rev_total'];
        }
        unset($mo);

        $collectors = $this->db->query(
            "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.role,
                    COALESCE(enc.total, 0) AS encaisse, COALESCE(enc.nb, 0) AS nb_ventes,
                    COALESCE(rev.total, 0) AS reverse, COALESCE(rev.nb, 0) AS nb_rev
             FROM users u
             LEFT JOIN (SELECT sold_by, SUM(amount) AS total, COUNT(*) AS nb FROM sales WHERE site_id = ? AND year = ? GROUP BY sold_by) enc ON u.id = enc.sold_by
             LEFT JOIN (SELECT user_id, SUM(amount) AS total, COUNT(*) AS nb FROM reversements WHERE site_id = ? AND YEAR(created_at) = ? GROUP BY user_id) rev ON u.id = rev.user_id
             WHERE u.site_id = ? AND u.active = 1 AND (enc.total > 0 OR rev.total > 0)
             ORDER BY (COALESCE(enc.total, 0) - COALESCE(rev.total, 0)) DESC",
            [$this->siteId, $year, $this->siteId, $year, $this->siteId]
        )->getResultArray();

        return [
            'months' => $months,
            'recettes' => array_sum(array_column($months, 'recettes')),
            'reversements' => array_sum(array_column($months, 'rev_total')),
            'timbres' => array_sum(array_column($months, 'timbres_nb')),
            'cartes' => array_sum(array_column($months, 'cartes_nb')),
            'byPayment' => $this->db->query('SELECT payment_method, COUNT(*) AS nb, COALESCE(SUM(amount), 0) AS total FROM sales WHERE site_id = ? AND year = ? GROUP BY payment_method ORDER BY total DESC', [$this->siteId, $year])->getResultArray(),
            'collectors' => $collectors,
        ];
    }

    /** Lignes de l'export comptable (recettes puis reversements), cellules neutralisées. */
    public function exportRows(int $year): array
    {
        $safe = static fn ($v) => is_string($v) && $v !== '' && str_contains('=+-@', $v[0]) ? "'" . $v : $v;
        $num  = static fn ($v) => number_format((float) $v, 2, ',', '');
        $out  = [['Date', 'Type', 'Mois', 'Année', 'Montant', 'Mode paiement', 'Adhérent', 'Collecteur', 'Débit', 'Crédit']];
        foreach ($this->db->query("SELECT s.created_at, s.type, s.month, s.year, s.amount, s.payment_method, CONCAT(m.last_name, ' ', m.first_name) AS adherent, CONCAT(u.last_name, ' ', u.first_name) AS collecteur
            FROM sales s LEFT JOIN members m ON s.member_id = m.id LEFT JOIN users u ON s.sold_by = u.id WHERE s.site_id = ? AND s.year = ? ORDER BY s.created_at", [$this->siteId, $year])->getResultArray() as $r) {
            $out[] = [date('d/m/Y', strtotime($r['created_at'])), $r['type'] === 'timbre' ? 'Timbre' : 'Carte', $r['month'] ? sprintf('%02d/%d', $r['month'], $r['year']) : '', $r['year'], $num($r['amount']), $r['payment_method'], $safe($r['adherent'] ?? ''), $safe($r['collecteur'] ?? ''), '', $num($r['amount'])];
        }
        foreach ($this->db->query("SELECT r.created_at, r.amount, r.payment_method, CONCAT(u.last_name, ' ', u.first_name) AS collecteur FROM reversements r LEFT JOIN users u ON r.user_id = u.id WHERE r.site_id = ? AND YEAR(r.created_at) = ? ORDER BY r.created_at", [$this->siteId, $year])->getResultArray() as $r) {
            $out[] = [date('d/m/Y', strtotime($r['created_at'])), 'Reversement', '', $year, $num($r['amount']), $r['payment_method'], '', $safe($r['collecteur'] ?? ''), $num($r['amount']), ''];
        }

        return $out;
    }
}
