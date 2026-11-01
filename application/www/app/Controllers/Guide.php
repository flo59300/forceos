<?php

namespace App\Controllers;

use App\Libraries\Settings;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Guide d'utilisation et pages légales (pages/guide.php, cgu.php, mentions-legales.php, confidentialite.php de la 2.x).
 * Les pages légales sont publiques (liens de la page de connexion) ; les compléments viennent des réglages legal_*.
 */
class Guide extends BaseController
{
    public function index()
    {
        return $this->page('guide/index', ['title' => 'Guide d’utilisation', 'role' => $this->auth->effectiveRole()]);
    }

    public function legal(string $page)
    {
        $titles = ['mentions-legales' => 'Mentions légales', 'cgu' => 'Conditions générales d’utilisation', 'confidentialite' => 'Politique de confidentialité'];
        if (! isset($titles[$page])) {
            throw PageNotFoundException::forPageNotFound();
        }
        $data = [
            'title' => $titles[$page], 'pageKey' => $page, 'appName' => Settings::appName(),
            'custom' => match ($page) {
                'cgu' => Settings::get('legal_cgu'),
                'mentions-legales' => Settings::get('legal_mentions'),
                default => '',
            },
            'rgpd' => $page === 'mentions-legales' ? Settings::get('legal_rgpd') : '',
        ];

        return $this->auth->id() ? $this->page('guide/legal', $data + ['layout' => 'layouts/hub']) : view('guide/legal', $data + ['layout' => 'layouts/public']);
    }
}
