<?php

namespace App\Controllers;

use App\Domain\Finance;
use App\Domain\Site;
use App\Domain\Stamps;

/**
 * Finances : paiements, reversements, prélèvements, trésorerie.
 * Collecteur : ses paiements et ses reversements. Secrétaire et trésorier : tout le site.
 */
class Finances extends BaseController
{
    private function fin(): Finance
    {
        return new Finance($this->db, (int) $this->auth->siteId());
    }

    private function year(): int
    {
        return (new Site($this->db))->currentYear((int) $this->auth->siteId());
    }

    private function isManager(): bool
    {
        return in_array($this->auth->effectiveRole(), ['secretaire', 'tresorier'], true);
    }

    private function denied()
    {
        return $this->response->setStatusCode(403)->setBody(view('errors/forbidden'));
    }

    private function tabs(string $active): array
    {
        $t = [['key' => 'paiements', 'label' => 'Paiements', 'url' => '/finances'], ['key' => 'reversements', 'label' => 'Reversements', 'url' => '/finances/reversements']];
        if ($this->isManager()) {
            $t[] = ['key' => 'prelevements', 'label' => 'Prélèvements', 'url' => '/finances/prelevements'];
            $t[] = ['key' => 'tresorerie', 'label' => 'Trésorerie', 'url' => '/finances/tresorerie'];
        }

        return ['financeTabs' => $t, 'financeTab' => $active];
    }

    public function index()
    {
        $f    = array_map('strval', array_intersect_key($this->request->getGet() ?? [], array_flip(['q', 'mois', 'mode', 'collecteur'])));
        $page = max(1, (int) $this->request->getGet('page'));
        $year = $this->year();
        $only = $this->isManager() ? null : (int) $this->auth->id();
        $res  = $this->fin()->payments($year, $f, $page, $only);

        return $this->page('finances/paiements', $this->tabs('paiements') + [
            'title' => 'Finances', 'year' => $year, 'filters' => $f, 'page' => $page, 'res' => $res,
            'pages' => max(1, (int) ceil($res['total'] / Finance::PER_PAGE)), 'isManager' => $this->isManager(),
            'people' => $this->isManager() ? (new Site($this->db))->lists((int) $this->auth->siteId())['collecteurs'] : [],
            'pending' => $this->fin()->pendingBySeller($year, $only),
        ]);
    }

    // --- Reversements ---

    public function reversements()
    {
        $year = $this->year();
        $only = $this->isManager() ? null : (int) $this->auth->id();
        $page = max(1, (int) $this->request->getGet('page'));
        $list = $this->fin()->reversements($only, $page);

        return $this->page('finances/reversements', $this->tabs('reversements') + [
            'title' => 'Finances', 'year' => $year, 'pending' => $this->fin()->pendingBySeller($year, $only),
            'list' => $list, 'page' => $page, 'pages' => max(1, (int) ceil($list['total'] / 20)), 'isManager' => $this->isManager(),
        ]);
    }

    public function createReversement()
    {
        $userId = $this->isManager() ? (int) $this->request->getPost('user_id') : (int) $this->auth->id();
        $r = $this->fin()->createReversement($this->year(), $userId, (string) $this->request->getPost('method'), trim((string) $this->request->getPost('reference')), trim((string) $this->request->getPost('notes')));
        if (! $r['ok']) {
            return redirect()->to(site_url('finances/reversements'))->with('error', $r['reason'] === 'empty' ? 'Il n’y a rien à reverser pour cette personne.' : 'Reversement impossible.');
        }
        if ($userId === (int) $this->auth->id() && ! $this->isManager()) {
            foreach ($this->db->table('users')->select('id')->where(['site_id' => $this->auth->siteId(), 'active' => 1])->whereIn('role', ['secretaire', 'tresorier'])->get()->getResultArray() as $u) {
                $this->notify((int) $u['id'], 'Reversement à valider', session('user_name') . ' a déclaré un reversement de ' . money($r['amount']) . '.', '/finances/reversements', '💶');
            }
        }

        return redirect()->to(site_url('finances/reversements'))->with('success', 'Reversement de ' . money($r['amount']) . ' enregistré. Il attend la validation du secrétaire ou du trésorier.');
    }

    public function validateReversement(int $id)
    {
        if (! $this->isManager()) {
            return $this->denied();
        }
        $ok = $this->fin()->validateReversement($id, (int) $this->auth->id());

        return redirect()->to(site_url('finances/reversements'))->with($ok ? 'success' : 'error', $ok ? 'Reversement validé.' : 'Ce reversement est introuvable ou déjà validé.');
    }

    // --- Prélèvements ---

    public function prelevements()
    {
        if (! $this->isManager()) {
            return $this->denied();
        }
        $year   = $this->year();
        $tarifs = (new Site($this->db))->tarifs((int) $this->auth->siteId(), $year);
        $month  = (int) ($this->request->getGet('mois') ?: date('n'));
        $month  = max(1, min(12, $month));

        return $this->page('finances/prelevements', $this->tabs('prelevements') + [
            'title' => 'Finances', 'year' => $year, 'month' => $month, 'tarifs' => $tarifs,
            'nbMembers' => count($this->fin()->prelevMembers()), 'generated' => $this->fin()->generatedMonths($year),
            'ofMonth' => $this->fin()->prelevementsOfMonth($year, $month),
            'checks' => [1 => $this->fin()->semesterCheck($year, 1, $tarifs['timbre']), 2 => $this->fin()->semesterCheck($year, 2, $tarifs['timbre'])],
            'validations' => array_column($this->fin()->validations($year), null, 'semester'),
        ]);
    }

    public function generate()
    {
        if (! $this->isManager()) {
            return $this->denied();
        }
        $year  = $this->year();
        $month = (int) $this->request->getPost('month');
        if ($month < 1 || $month > 12) {
            return redirect()->to(site_url('finances/prelevements'))->with('error', 'Choisissez un mois.');
        }
        $r   = $this->fin()->generatePrelevements($year, $month, (int) $this->auth->id(), (new Site($this->db))->tarifs((int) $this->auth->siteId(), $year));
        $msg = 'Prélèvements de ' . Stamps::MONTHS[$month] . ' : ' . plural($r['generated'], 'timbre généré', 'timbres générés')
            . ($r['cards'] ? ', dont ' . plural($r['cards'], 'première carte', 'premières cartes') : '')
            . ($r['skipped'] ? ', ' . plural($r['skipped'], 'déjà présent', 'déjà présents') : '') . '.';

        return redirect()->to(site_url('finances/prelevements?mois=' . $month))->with('success', $msg);
    }

    public function reject(int $saleId)
    {
        if (! $this->isManager()) {
            return $this->denied();
        }
        $r    = $this->fin()->rejectPrelevement($saleId);
        $back = site_url('finances/prelevements?mois=' . (int) $this->request->getPost('mois'));

        return match ($r) {
            'ok'       => redirect()->to($back)->with('success', 'Prélèvement annulé.'),
            'reversed' => redirect()->to($back)->with('error', 'Ce prélèvement a déjà été reversé : il ne peut plus être annulé.'),
            default    => redirect()->to($back)->with('error', 'Prélèvement introuvable.'),
        };
    }

    public function validateSemester()
    {
        if (! $this->isManager()) {
            return $this->denied();
        }
        $year = $this->year();
        $sem  = (int) $this->request->getPost('semester') === 2 ? 2 : 1;
        $d    = $this->fin()->validateSemester($year, $sem, (new Site($this->db))->tarifs((int) $this->auth->siteId(), $year)['timbre'], (bool) $this->request->getPost('force'), trim((string) $this->request->getPost('notes')), (int) $this->auth->id());
        $lbl  = ($sem === 1 ? '1er' : '2e') . ' semestre ' . $year;

        return $d['status'] === 'conforme'
            ? redirect()->to(site_url('finances/prelevements'))->with('success', "{$lbl} conforme : {$d['nb_timbres_presents']} timbres sur {$d['nb_timbres_attendus']}.")
            : redirect()->to(site_url('finances/prelevements'))->with('error', "{$lbl} : {$d['nb_manquants']} timbre(s) manquant(s) sur {$d['nb_timbres_attendus']}" . ($d['status'] === 'force' ? ', validé malgré tout.' : '. Validation enregistrée avec anomalies.'));
    }

    // --- Trésorerie ---

    private function treasuryYear(): int
    {
        $y = (int) $this->request->getGet('annee');

        return $y >= 2020 && $y <= 2100 ? $y : (int) date('Y');
    }

    public function tresorerie()
    {
        if (! $this->isManager()) {
            return $this->denied();
        }
        $y = $this->treasuryYear();

        return $this->page('finances/tresorerie', $this->tabs('tresorerie') + ['title' => 'Finances', 'year' => $y, 't' => $this->fin()->treasury($y)]);
    }

    public function export()
    {
        if (! $this->isManager()) {
            return $this->denied();
        }
        $y   = $this->treasuryYear();
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($this->fin()->exportRows($y) as $row) {
            fputcsv($out, $row, ';');
        }
        rewind($out);
        $csv = stream_get_contents($out);

        return $this->response->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="tresorerie-' . $y . '.csv"')->setBody($csv);
    }
}
