<?php

namespace App\Controllers;

use App\Domain\ElectoralLists;

/**
 * Listes électorales (secrétaire) : protocole d'accord préélectoral, génération, contrôle de parité, exports.
 */
class ListesElectorales extends BaseController
{
    private function el(): ElectoralLists
    {
        return new ElectoralLists($this->db, (int) $this->auth->siteId());
    }

    public function index()
    {
        $tab = in_array($this->request->getGet('onglet'), ['generer', 'listes'], true) ? $this->request->getGet('onglet') : 'pap';
        $el  = $this->el();
        $pap = $el->pap();

        return $this->page('listes/index', [
            'title' => 'Listes électorales', 'tab' => $tab, 'pap' => $pap, 'stats' => $el->memberStats(),
            'rows' => $pap && $tab === 'listes' ? $el->rows((int) $pap['id']) : [],
            'parity' => $pap ? $el->parity($pap) : [],
        ]);
    }

    public function savePap()
    {
        $p      = $this->request->getPost();
        $n      = max(1, min(3, (int) ($p['nb_colleges'] ?? 3)));
        $errors = [];
        $cols   = [];
        for ($i = 0; $i < $n; $i++) {
            $part = trim((string) ($p["college_part_femmes_{$i}"] ?? ''));
            if ($part !== '' && (! is_numeric(str_replace(',', '.', $part)) || (float) str_replace(',', '.', $part) < 0 || (float) str_replace(',', '.', $part) > 100)) {
                $errors["college_part_femmes_{$i}"] = 'La part de femmes du collège ' . ($i + 1) . ' doit être un pourcentage entre 0 et 100.';
            }
            $cols[] = [
                'name' => mb_substr(trim((string) ($p["college_name_{$i}"] ?? '')), 0, 100) ?: ($i + 1) . ($i === 0 ? 'er' : 'e') . ' collège',
                'subtitle' => mb_substr(trim((string) ($p["college_subtitle_{$i}"] ?? '')), 0, 100),
                'categories' => array_values(array_intersect((array) ($p["college_categories_{$i}"] ?? []), array_keys(ElectoralLists::CATEGORIES))),
                'seats_tit' => max(0, min(100, (int) ($p["college_seats_tit_{$i}"] ?? 0))),
                'seats_sup' => max(0, min(100, (int) ($p["college_seats_sup_{$i}"] ?? 0))),
                'part_femmes' => $part === '' ? null : round((float) str_replace(',', '.', $part), 1),
            ];
        }
        foreach (['election_date', 'election_date_t2'] as $f) {
            if (($p[$f] ?? '') !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $p[$f])) {
                $errors[$f] = 'La date n’est pas valide.';
            }
        }
        if ($errors) {
            return redirect()->to(site_url('listes-electorales'))->withInput()->with('errors', $errors);
        }
        $syn = trim((string) ($p['syndicats'] ?? ''));
        $this->el()->savePap([
            'name' => mb_substr(trim((string) ($p['pap_name'] ?? '')), 0, 255) ?: 'PAP CSE', 'election_date' => (string) ($p['election_date'] ?? ''),
            'election_date_t2' => (string) ($p['election_date_t2'] ?? ''),
            'anc_electeur' => max(0, (int) ($p['anciennete_electeur'] ?? 3)), 'anc_eligible' => max(0, (int) ($p['anciennete_eligible'] ?? 12)),
            'age_electeur' => max(16, (int) ($p['age_electeur'] ?? 16)), 'age_eligible' => max(18, (int) ($p['age_eligible'] ?? 18)),
            'colleges' => $cols, 'syndicats' => $syn !== '' ? array_values(array_filter(array_map('trim', explode(',', $syn)))) : [],
            'regles' => trim((string) ($p['regles'] ?? '')), 'status' => ($p['pap_status'] ?? '') === 'actif' ? 'actif' : 'brouillon',
        ], (int) $this->auth->id());

        return redirect()->to(site_url('listes-electorales'))->with('success', 'Protocole préélectoral enregistré.');
    }

    public function deletePap()
    {
        $this->el()->deletePap();

        return redirect()->to(site_url('listes-electorales'))->with('success', 'Protocole et listes supprimés.');
    }

    public function generate()
    {
        $pap = $this->el()->pap();
        if (! $pap) {
            return redirect()->to(site_url('listes-electorales'))->with('error', 'Enregistrez d’abord le protocole préélectoral.');
        }
        $r   = $this->el()->generate($pap);
        $msg = 'Listes générées : ' . plural($r['electeurs'], 'électeur') . ', ' . plural($r['eligibles'], 'éligible') . ($r['exclus'] ? ', ' . plural($r['exclus'], 'exclu') : '') . '.';
        if ($r['a_verifier']) {
            $msg .= ' ' . plural($r['a_verifier'], 'fiche est', 'fiches sont') . ' à vérifier (dates manquantes).';
        }

        return redirect()->to(site_url('listes-electorales') . '?onglet=listes')->with('success', $msg);
    }

    public function export(string $type)
    {
        $pap = $this->el()->pap();
        if (! $pap) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($this->el()->csv((int) $pap['id'], $type === 'eligibles' ? 'eligible' : 'electeur') as $r) {
            fputcsv($out, $r, ';');
        }
        rewind($out);

        return $this->response->setHeader('Content-Type', 'text/csv; charset=utf-8')->setHeader('Cache-Control', 'no-store')
            ->setHeader('Content-Disposition', 'attachment; filename="liste_' . ($type === 'eligibles' ? 'eligibles' : 'electeurs') . '_' . date('Y-m-d') . '.csv"')
            ->setBody(stream_get_contents($out));
    }
}
