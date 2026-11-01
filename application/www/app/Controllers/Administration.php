<?php

namespace App\Controllers;

use App\Domain\MemberStatus;
use App\Domain\Site;
use App\Domain\SiteConfig;
use App\Domain\Stock;

/**
 * Pilotage du site : stock, rapports, configuration (site, tarifs, secteurs, tournées, métiers, années).
 * Le trésorier n'accède qu'aux tarifs.
 */
class Administration extends BaseController
{
    private function cfg(): SiteConfig
    {
        return new SiteConfig($this->db, (int) $this->auth->siteId());
    }

    private function isSecretary(): bool
    {
        return $this->auth->effectiveRole() === 'secretaire';
    }

    private function denied()
    {
        return $this->response->setStatusCode(403)->setBody(view('errors/forbidden'));
    }

    private function back(string $tab, string $type = 'success', string $msg = '')
    {
        return redirect()->to(site_url('administration/configuration?onglet=' . $tab))->with($type, $msg);
    }

    public function index()
    {
        return $this->page('admin/index', ['title' => 'Administration', 'isSecretary' => $this->isSecretary()]);
    }

    // --- Stock ---

    public function stock()
    {
        $s = new Stock($this->db, (int) $this->auth->siteId());

        return $this->page('admin/stock', ['title' => 'Stock de timbres', 'levels' => ['timbre' => $s->level('timbre'), 'carte' => $s->level('carte')], 'movements' => $s->movements()]);
    }

    public function addStock()
    {
        $ok = (new Stock($this->db, (int) $this->auth->siteId()))->add(
            (string) $this->request->getPost('type'), (string) $this->request->getPost('movement'),
            (int) $this->request->getPost('quantity'), trim((string) $this->request->getPost('reason')), (int) $this->auth->id()
        );

        return redirect()->to(site_url('administration/stock'))->with($ok ? 'success' : 'error', $ok ? 'Mouvement de stock enregistré.' : 'Indiquez un type, un sens et une quantité positive.');
    }

    // --- Rapports (mêmes calculs que rapport.php) ---

    public function rapport()
    {
        $site  = (int) $this->auth->siteId();
        $year  = (new Site($this->db))->currentYear($site);
        $count = ['a_jour' => 0, 'en_attente' => 0, 'sympathisant' => 0];
        foreach ((new MemberStatus($this->db))->forSite($site, $year) as $s) {
            $count[$s['status']]++;
        }
        $q = fn (string $sql) => $this->db->query($sql, [$site, $year]);

        return $this->page('admin/rapport', [
            'title' => 'Rapport ' . $year, 'year' => $year, 'site' => $this->cfg()->site(),
            'members' => $this->db->table('members')->where(['site_id' => $site, 'is_radiated' => 0])->countAllResults(),
            'count' => $count,
            'totals' => $q("SELECT COALESCE(SUM(amount), 0) AS total, SUM(type = 'timbre') AS timbres, SUM(type = 'carte') AS cartes FROM sales WHERE site_id = ? AND year = ?")->getRowArray(),
            'reversed' => (float) $q('SELECT COALESCE(SUM(amount), 0) AS t FROM reversements WHERE site_id = ? AND YEAR(created_at) = ?')->getRow()->t,
            'bySeller' => $q("SELECT CONCAT(u.first_name, ' ', u.last_name) AS name, SUM(s.type = 'timbre') AS nb_timbres, SUM(s.type = 'carte') AS nb_cartes, SUM(s.amount) AS total, SUM(CASE WHEN s.is_reversed = 0 THEN s.amount ELSE 0 END) AS non_reverse FROM sales s JOIN users u ON s.sold_by = u.id WHERE s.site_id = ? AND s.year = ? GROUP BY s.sold_by ORDER BY total DESC")->getResultArray(),
            'byMonth' => array_column($q("SELECT month, COUNT(*) AS nb, SUM(amount) AS total FROM sales WHERE site_id = ? AND year = ? AND type = 'timbre' GROUP BY month")->getResultArray(), null, 'month'),
        ]);
    }

    // --- Configuration ---

    public function configuration()
    {
        $tabs = $this->isSecretary()
            ? ['site' => 'Site et tarifs', 'secteurs' => 'Secteurs', 'tournees' => 'Tournées', 'metiers' => 'Métiers', 'annees' => 'Années']
            : ['site' => 'Tarifs'];
        $tab = (string) $this->request->getGet('onglet');
        $tab = isset($tabs[$tab]) ? $tab : 'site';
        $c   = $this->cfg();

        return $this->page('admin/configuration', [
            'title' => 'Configuration', 'tabs' => $tabs, 'tab' => $tab, 'isSecretary' => $this->isSecretary(),
            'site' => $c->site(), 'secteurs' => $tab === 'secteurs' ? $c->secteurs() : [],
            'tournees' => $tab === 'tournees' ? $c->items('tournees') : [], 'metiers' => $tab === 'metiers' ? $c->items('metiers') : [],
            'years' => $tab === 'annees' ? $c->years() : [],
            'currentYear' => (new Site($this->db))->currentYear((int) $this->auth->siteId()),
        ]);
    }

    public function saveTarifs()
    {
        $t = str_replace(',', '.', (string) $this->request->getPost('tarif_timbre'));
        $c = str_replace(',', '.', (string) $this->request->getPost('tarif_carte'));
        if (! is_numeric($t) || ! is_numeric($c) || (float) $t < 0 || (float) $c < 0 || (float) $t > 1000 || (float) $c > 1000) {
            return $this->back('site', 'error', 'Indiquez des tarifs valides, par exemple 5,00.');
        }
        $this->cfg()->updateTarifs(round((float) $t, 2), round((float) $c, 2));

        return $this->back('site', 'success', 'Tarifs enregistrés : timbre ' . money((float) $t) . ', carte ' . money((float) $c) . '.');
    }

    public function saveSite()
    {
        if (! $this->isSecretary()) {
            return $this->denied();
        }
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return $this->back('site', 'error', 'Le nom du site est obligatoire.');
        }
        $c = $this->cfg();
        $c->updateInfo($name, trim((string) $this->request->getPost('address')), (bool) $this->request->getPost('bulletin_board_enabled'));
        $seuil = trim((string) $this->request->getPost('seuil'));
        $c->updateSeuil($seuil === '' ? null : (int) $seuil);
        $eff = trim((string) $this->request->getPost('effectif'));
        $c->updateEffectif($eff === '' ? null : (int) $eff);

        return $this->back('site', 'success', 'Informations du site enregistrées.');
    }

    public function secteur(string $op)
    {
        if (! $this->isSecretary()) {
            return $this->denied();
        }
        $c    = $this->cfg();
        $name = trim((string) $this->request->getPost('name'));
        $id   = (int) $this->request->getPost('id');

        return match ($op) {
            'ajouter'   => $name === '' ? $this->back('secteurs', 'error', 'Indiquez un nom de secteur.')
                : ($c->addSecteur($name, ((int) $this->request->getPost('parent_id')) ?: null) ? $this->back('secteurs', 'success', 'Secteur « ' . $name . ' » ajouté.') : $this->back('secteurs', 'error', 'Secteur parent invalide.')),
            'renommer'  => $name !== '' && $c->renameSecteur($id, $name) ? $this->back('secteurs', 'success', 'Secteur renommé.') : $this->back('secteurs', 'error', 'Renommage impossible.'),
            'supprimer' => ($n = $c->deleteSecteur($id)) ? $this->back('secteurs', 'success', $n > 1 ? "Secteur et ses " . ($n - 1) . " sous-secteur(s) retirés." : 'Secteur retiré.') : $this->back('secteurs', 'error', 'Secteur introuvable.'),
            default     => $this->denied(),
        };
    }

    /** Tournées et métiers : ajouter, modifier, retirer. */
    public function item(string $kind, string $op)
    {
        if (! $this->isSecretary() || ! in_array($kind, ['tournees', 'metiers'], true)) {
            return $this->denied();
        }
        $c     = $this->cfg();
        $name  = trim((string) $this->request->getPost('name'));
        $desc  = trim((string) $this->request->getPost('description'));
        $label = $kind === 'tournees' ? 'Tournée' : 'Métier';
        if (in_array($op, ['ajouter', 'modifier'], true) && $name === '') {
            return $this->back($kind, 'error', 'Indiquez un nom.');
        }

        return match ($op) {
            'ajouter'   => (function () use ($c, $kind, $name, $desc, $label) { $c->addItem($kind, $name, $desc); return $this->back($kind, 'success', "{$label} « {$name} » ajouté" . ($kind === 'tournees' ? 'e.' : '.')); })(),
            'modifier'  => $c->editItem($kind, (int) $this->request->getPost('id'), $name, $desc) ? $this->back($kind, 'success', 'Modification enregistrée.') : $this->back($kind, 'error', 'Aucune modification.'),
            'supprimer' => $c->deleteItem($kind, (int) $this->request->getPost('id')) ? $this->back($kind, 'success', "{$label} retiré" . ($kind === 'tournees' ? 'e.' : '.')) : $this->back($kind, 'error', 'Introuvable.'),
            default     => $this->denied(),
        };
    }

    public function year(string $op)
    {
        if (! $this->isSecretary()) {
            return $this->denied();
        }
        if ($op === 'ajouter') {
            $y = (int) $this->request->getPost('year');
            $r = $this->cfg()->addYear($y);

            return $this->back('annees', $r === 'ok' ? 'success' : 'error', ['ok' => "Année {$y} créée avec les tarifs actuels.", 'exists' => 'Cette année existe déjà.', 'invalid' => 'Année invalide.'][$r]);
        }
        $ok = $this->cfg()->setCurrentYear((int) $this->request->getPost('id'));

        return $this->back('annees', $ok ? 'success' : 'error', $ok ? 'Année courante modifiée : les encaissements se font désormais sur cette année.' : 'Année introuvable.');
    }
}
