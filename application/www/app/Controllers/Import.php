<?php

namespace App\Controllers;

use App\Domain\MemberImport;
use App\Domain\Site;

/**
 * Import et export CSV (pages/import.php de la 2.x). Secrétaire du site ; le Super Admin exporte les comptes
 * de toute la plateforme depuis son espace.
 */
class Import extends BaseController
{
    private function tool(): MemberImport
    {
        return new MemberImport($this->db, (int) $this->auth->siteId());
    }

    private function year(): int
    {
        return (new Site($this->db))->currentYear((int) $this->auth->siteId());
    }

    public function index()
    {
        return $this->page('admin/import', ['title' => 'Import et export', 'year' => $this->year(), 'report' => session('import_report')]);
    }

    public function export(string $type)
    {
        $t    = $this->tool();
        $rows = match ($type) {
            'adherents'    => $t->members(),
            'ventes'       => $t->sales($this->year()),
            'utilisateurs' => $t->users($this->auth->isSuperAdmin() && ! $this->auth->siteId() ? null : (int) $this->auth->siteId()),
        };
        $name = ['adherents' => 'adherents', 'ventes' => 'ventes_' . $this->year(), 'utilisateurs' => 'utilisateurs'][$type];

        return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')->setHeader('Cache-Control', 'no-store')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '_' . date('Y-m-d') . '.csv"')->setBody(MemberImport::csv($rows));
    }

    public function model()
    {
        $rows = [MemberImport::COLUMNS, ['12345', 'DUPONT', 'Marie', 'marie.dupont@exemple.fr', '0601020304', '1 rue des Forges', 'Valenciennes', '59300', '14/03/1980', '01/09/2024', '', '', '', '', 'manuel', '02/05/2005', 'F', '1']];

        return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="modele_import_adherents.csv"')->setBody(MemberImport::csv($rows));
    }

    public function import()
    {
        $f = $this->request->getFile('csv_file');
        if (! $f || ! $f->isValid()) {
            return redirect()->to(site_url('administration/import'))->with('error', 'Choisissez un fichier CSV.');
        }
        if (! in_array(strtolower($f->getClientExtension()), ['csv', 'txt'], true) || $f->getSize() > 10 * 1024 * 1024) {
            return redirect()->to(site_url('administration/import'))->with('error', 'Le fichier doit être un CSV de 10 Mo au plus.');
        }
        $r = $this->tool()->import($f->getTempName(), $this->year());
        log_message('info', 'Import CSV : {n} adhérents par l’utilisateur {u}', ['n' => $r['imported'], 'u' => $this->auth->id()]);

        return redirect()->to(site_url('administration/import') . '#rapport')->with('import_report', $r)
            ->with($r['imported'] ? 'success' : 'error', plural($r['imported'], 'adhérent importé', 'adhérents importés') . '.');
    }
}
