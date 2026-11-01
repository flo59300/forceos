<?php

namespace App\Controllers;

use App\Domain\Stocks;

/**
 * Bourse : cours de Stellantis, des constructeurs suivis et des grands indices.
 * La page se charge sans attendre Yahoo ; les cours arrivent ensuite et se rafraîchissent chaque minute.
 */
class Bourse extends BaseController
{
    private function stocks(): Stocks
    {
        return new Stocks((int) $this->auth->siteId());
    }

    public function index()
    {
        return $this->page('bourse/index', [
            'title' => 'Bourse', 'companies' => $this->stocks()->companies(), 'canManage' => $this->auth->canConfigure(),
        ]);
    }

    /** Cours au format JSON (même structure que stock_api.php de la 2.x). */
    public function quotes()
    {
        $g = (string) $this->request->getGet('groupe');
        if (! in_array($g, ['stellantis', 'companies', 'indices'], true)) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'Groupe inconnu']);
        }

        return $this->response->setHeader('Cache-Control', 'private, max-age=60')
            ->setJSON(['ok' => true, 'data' => $this->stocks()->quotes($g), 'cached_at' => time()]);
    }

    public function save()
    {
        if ($this->request->getPost('defaut')) {
            $this->stocks()->save([]);

            return redirect()->to(site_url('bourse') . '#suivi')->with('success', 'Liste par défaut rétablie.');
        }
        $syms  = (array) $this->request->getPost('symbol');
        $names = (array) $this->request->getPost('name');
        $list  = [];
        $bad   = [];
        foreach ($syms as $i => $s) {
            $s = strtoupper(trim((string) $s));
            $n = trim((string) ($names[$i] ?? ''));
            if ($s === '' && $n === '') {
                continue;
            }
            if (! Stocks::validSymbol($s) || $n === '') {
                $bad[] = $s !== '' ? $s : '(sans symbole)';

                continue;
            }
            $list[$s] = mb_substr($n, 0, 60);
        }
        if ($bad) {
            return redirect()->to(site_url('bourse') . '#suivi')->with('error', 'Ligne incomplète ou symbole non valide : ' . implode(', ', $bad) . '. Rien n’a été modifié.');
        }
        $this->stocks()->save(array_slice($list, 0, 12, true));

        return redirect()->to(site_url('bourse') . '#suivi')->with('success', plural(count($list), 'entreprise suivie', 'entreprises suivies') . '.');
    }
}
