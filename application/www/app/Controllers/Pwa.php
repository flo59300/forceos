<?php

namespace App\Controllers;

use App\Libraries\Settings;

/**
 * Application installable : manifeste (mêmes réglages que manifest.php de la 2.x) et anciennes adresses.
 */
class Pwa extends BaseController
{
    public function manifest()
    {
        $logo = Settings::get('pwa_logo_path', '/assets/icons/fostellantis.png');
        $fav  = Settings::get('pwa_favicon_path', '/assets/icons/favicon.svg');
        $src  = static function (string $p): string {
            $file = FCPATH . ltrim($p, '/');

            return base_url(ltrim($p, '/')) . (is_file($file) ? '?v=' . filemtime($file) : '');
        };
        $icons = [
            ['src' => $src($logo), 'sizes' => '512x512', 'type' => str_ends_with($logo, '.svg') ? 'image/svg+xml' : 'image/png', 'purpose' => 'any'],
            ['src' => $src($logo), 'sizes' => '512x512', 'type' => str_ends_with($logo, '.svg') ? 'image/svg+xml' : 'image/png', 'purpose' => 'maskable'],
        ];
        if (str_ends_with($fav, '.svg')) {
            $icons[] = ['src' => $src($fav), 'sizes' => 'any', 'type' => 'image/svg+xml'];
        }
        $m = [
            'name' => Settings::appName(), 'short_name' => Settings::get('pwa_short_name', Settings::appName()),
            'description' => Settings::get('app_description', 'Hub syndical Force Ouvrière'),
            'lang' => 'fr', 'dir' => 'ltr', 'id' => site_url('/'), 'start_url' => site_url('/'), 'scope' => site_url('/'),
            'display' => in_array(Settings::get('pwa_display', 'standalone'), ['standalone', 'fullscreen', 'minimal-ui', 'browser'], true) ? Settings::get('pwa_display', 'standalone') : 'standalone',
            'background_color' => Settings::get('pwa_background_color', '#f3f5fa'), 'theme_color' => Settings::get('pwa_theme_color', '#002b7f'),
            'orientation' => 'any', 'icons' => $icons,
            'shortcuts' => [
                ['name' => 'Encaisser un timbre', 'url' => site_url('adherents')],
                ['name' => 'Saisir des heures', 'url' => site_url('heures')],
                ['name' => 'Messages', 'url' => site_url('messages')],
            ],
        ];

        return $this->response->setHeader('Content-Type', 'application/manifest+json; charset=utf-8')->setHeader('Cache-Control', 'public, max-age=300')
            ->setBody(json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Anciennes adresses de la 2.x (favoris, raccourcis, notifications) : redirigées vers l'écran équivalent. */
    public function legacy(string $page = '')
    {
        $map = [
            'agenda.php' => 'agenda', 'messages.php' => 'messages', 'delegues.php' => 'taches', 'annonces.php' => 'annonces',
            'permanences.php' => 'permanences', 'members.php' => 'adherents', 'members_radies.php' => 'adherents/radies',
            'paiements.php' => 'finances', 'reversements.php' => 'finances/reversements', 'tresorerie.php' => 'finances/tresorerie',
            'stock.php' => 'administration/stock', 'rapport.php' => 'administration/rapport', 'configuration.php' => 'administration/configuration',
            'users.php' => 'administration/utilisateurs', 'knowledge.php' => 'documents', 'rss.php' => 'actualites', 'barometre.php' => 'barometre',
            'bourse.php' => 'bourse', 'elections.php' => 'elections', 'listes-electorales.php' => 'listes-electorales', 'profile.php' => 'profil',
            'outils.php' => ['delegation' => 'heures', 'taches' => 'taches'][(string) $this->request->getGet('tab')] ?? 'notes', 'notifications.php' => 'administration/rappels',
            'sites.php' => 'plateforme/sites', 'settings.php' => 'plateforme/reglages', 'export.php' => 'plateforme/export', 'updates.php' => 'plateforme/mises-a-jour',
            'import.php' => 'administration/import', 'historique.php' => ($id = (int) $this->request->getGet('id')) ? 'adherents/' . $id : 'adherents', 'guide.php' => 'guide',
            'cgu.php' => 'cgu', 'mentions-legales.php' => 'mentions-legales', 'confidentialite.php' => 'confidentialite',
        ];

        return redirect()->to(site_url($map[$page] ?? '/'), 301);
    }
}
