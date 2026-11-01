<?php

namespace App\Controllers;

use App\Domain\MemberImport;
use App\Domain\Users;
use App\Libraries\Mailer;
use App\Libraries\Settings;
use App\Libraries\Updater;
use App\Libraries\WebPush;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

/**
 * Espace Super Admin (pages/sites.php, users.php, settings.php, export.php et updates.php de la 2.x).
 *
 * Les réglages vivent dans app_settings, partagée avec la 2.x : un changement ici vaut pour les deux versions.
 * Les fichiers déposés (logos) sont écrits dans la version 3 et, si fo.legacyPath est renseigné, dans la 2.x.
 */
class Platform extends BaseController
{
    private function updater(): Updater
    {
        return new Updater($this->db);
    }

    private function back(string $to, string $type, string $msg)
    {
        return redirect()->to(site_url('plateforme/' . ltrim($to, '/')))->with($type, $msg);
    }

    /** Mot de passe aléatoire lisible, conforme aux règles (majuscule, minuscule, chiffre, 12 caractères). */
    public static function randomPassword(): string
    {
        $alpha = 'abcdefghjkmnpqrstuvwxyz';
        $pw    = strtoupper($alpha[random_int(0, 22)]);
        for ($i = 0; $i < 7; $i++) {
            $pw .= $alpha[random_int(0, 22)];
        }

        return $pw . '-' . random_int(1000, 9999);
    }

    /** Copie un fichier déposé dans la version 3 et, si elle est accessible, dans la 2.x (même chemin relatif). */
    private function storeShared(string $tmp, string $rel): bool
    {
        $ok = false;
        foreach (array_filter([rtrim(FCPATH, '/'), (string) env('fo.legacyPath', '')]) as $root) {
            $dest = $root . '/' . ltrim($rel, '/');
            if (! is_dir(dirname($dest))) {
                @mkdir(dirname($dest), 0755, true);
            }
            $ok = @copy($tmp, $dest) || $ok;
        }

        return $ok;
    }

    /** Image déposée : PNG, JPEG ou WebP de 2 Mo au plus (le SVG est refusé : il peut contenir du script). */
    private function image(string $field): array|string|null
    {
        $f = $this->request->getFile($field);
        if (! $f || $f->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (! $f->isValid() || $f->getSize() > 2 * 1024 * 1024) {
            return 'L’image doit faire 2 Mo au plus.';
        }
        $info = @getimagesize($f->getTempName());
        $ext  = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
        if (! $ext) {
            return 'Formats acceptés : PNG, JPEG ou WebP.';
        }

        return ['tmp' => $f->getTempName(), 'ext' => $ext];
    }

    // ------------------------------------------------------------------
    // Tableau de bord
    // ------------------------------------------------------------------

    public function index()
    {
        $u     = $this->updater();
        $year  = (int) date('Y');
        $sites = $this->db->query("SELECT s.id, s.name, s.active, s.logo_path,
                (SELECT COUNT(*) FROM users u WHERE u.site_id = s.id AND u.active = 1) AS nb_users,
                (SELECT COUNT(*) FROM members m WHERE m.site_id = s.id AND m.is_radiated = 0) AS nb_members,
                (SELECT COALESCE(SUM(amount), 0) FROM sales x WHERE x.site_id = s.id AND x.year = ?) AS total,
                (SELECT MAX(u2.last_login) FROM users u2 WHERE u2.site_id = s.id) AS last_login
             FROM sites s ORDER BY s.active DESC, s.name", [$year])->getResultArray();
        $backups = $u->listBackups();
        $alerts  = [];
        if (is_file(FCPATH . 'installer.php')) {
            $alerts[] = ['installer.php est encore présent sur le serveur : supprimez-le depuis « Mises à jour ».', 'plateforme/mises-a-jour'];
        }
        if (Settings::get('maintenance_mode') === '1') {
            $alerts[] = ['Le mode maintenance est actif : seuls les Super Admin peuvent se connecter.', 'plateforme/reglages?onglet=avance'];
        }
        if (Settings::get('smtp_host') === '') {
            $alerts[] = ['Aucun serveur d’envoi des courriels n’est réglé : les rappels partent par la fonction d’envoi de l’hébergeur.', 'plateforme/reglages?onglet=courriels'];
        }
        if (WebPush::fromConfig() === null) {
            $alerts[] = ['Les notifications sur téléphone ne sont pas configurées (clés VAPID absentes).', 'plateforme/reglages?onglet=notifications'];
        }
        if (($p = $u->pendingCount()) > 0) {
            $alerts[] = [plural($p, 'migration en attente', 'migrations en attente') . ' sur la base.', 'plateforme/mises-a-jour'];
        }
        if (! $backups || $backups[0]['date'] < time() - 30 * 86400) {
            $alerts[] = ['Aucune sauvegarde de la base depuis plus d’un mois.', 'plateforme/sauvegardes'];
        }

        return $this->page('platform/index', [
            'title' => 'Tableau de bord', 'sites' => $sites, 'year' => $year, 'alerts' => $alerts,
            'counts' => [
                'sites' => count(array_filter($sites, static fn ($s) => $s['active'])),
                'users' => array_sum(array_column($sites, 'nb_users')),
                'members' => array_sum(array_column($sites, 'nb_members')),
                'total' => array_sum(array_column($sites, 'total')),
            ],
            'standalone' => Settings::isStandalone(), 'lastBackup' => $backups[0] ?? null,
        ]);
    }

    // ------------------------------------------------------------------
    // Sites
    // ------------------------------------------------------------------

    public function sites()
    {
        $edit = ($id = (int) $this->request->getGet('modifier')) ? $this->db->table('sites')->where('id', $id)->get()->getRowArray() : null;

        return $this->page('platform/sites', [
            'title' => 'Sites', 'edit' => $edit, 'credentials' => session('credentials'),
            'sites' => $this->db->query('SELECT s.*, (SELECT COUNT(*) FROM users u WHERE u.site_id = s.id) AS nb_users,
                (SELECT COUNT(*) FROM members m WHERE m.site_id = s.id AND m.is_radiated = 0) AS nb_members FROM sites s ORDER BY s.name')->getResultArray(),
        ]);
    }

    private function siteRules(): array
    {
        return [
            'name' => ['rules' => 'required|max_length[255]', 'errors' => ['required' => 'Indiquez le nom du site.', 'max_length' => 'Le nom est trop long.']],
            'address' => ['rules' => 'permit_empty|max_length[500]', 'errors' => ['max_length' => 'L’adresse est trop longue.']],
            'tarif_timbre' => ['rules' => 'required|decimal|greater_than_equal_to[0]|less_than[1000]', 'errors' => ['required' => 'Indiquez le tarif du timbre.', 'decimal' => 'Le tarif du timbre doit être un montant.', 'greater_than_equal_to' => 'Le tarif ne peut pas être négatif.', 'less_than' => 'Tarif trop élevé.']],
            'tarif_carte' => ['rules' => 'required|decimal|greater_than_equal_to[0]|less_than[1000]', 'errors' => ['required' => 'Indiquez le tarif de la carte.', 'decimal' => 'Le tarif de la carte doit être un montant.', 'greater_than_equal_to' => 'Le tarif ne peut pas être négatif.', 'less_than' => 'Tarif trop élevé.']],
        ];
    }

    public function createSite()
    {
        if (! $this->validate($this->siteRules())) {
            return redirect()->to(site_url('plateforme/sites') . '#formulaire')->withInput()->with('errors', $this->validator->getErrors());
        }
        $name  = trim((string) $this->request->getPost('name'));
        $tt    = (float) $this->request->getPost('tarif_timbre');
        $tc    = (float) $this->request->getPost('tarif_carte');
        $base  = substr(strtoupper(trim((string) preg_replace('/[^a-z0-9]+/i', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '_')), 0, 45) ?: 'SITE';
        $code  = $base;
        for ($i = 1; $this->db->table('sites')->where('code', $code)->countAllResults(); $i++) {
            $code = substr($base, 0, 42) . '_' . $i;
        }
        $this->db->transStart();
        $this->db->table('sites')->insert(['name' => $name, 'code' => $code, 'address' => trim((string) $this->request->getPost('address')), 'tarif_timbre' => $tt, 'tarif_carte' => $tc]);
        $siteId = (int) $this->db->insertID();
        $this->db->table('years')->insert(['site_id' => $siteId, 'year' => (int) date('Y'), 'is_current' => 1, 'tarif_timbre' => $tt, 'tarif_carte' => $tc]);
        $creds = [];
        $slug  = strtolower(str_replace('_', '-', $code));
        foreach (['secretaire' => 'Secrétaire', 'tresorier' => 'Trésorier'] as $role => $label) {
            $email = "{$role}-{$slug}@stellantis.com";
            for ($i = 2; $this->db->table('users')->where('email', $email)->countAllResults(); $i++) {
                $email = "{$role}-{$slug}-{$i}@stellantis.com";
            }
            $pw = self::randomPassword();
            $this->db->table('users')->insert(['site_id' => $siteId, 'first_name' => $label, 'last_name' => $name, 'email' => $email, 'password' => password_hash($pw, PASSWORD_DEFAULT), 'role' => $role, 'active' => 1]);
            $creds[] = ['label' => $label, 'email' => $email, 'password' => $pw];
        }
        $this->db->transComplete();
        log_message('notice', 'Site {name} créé par le Super Admin {u}', ['name' => $name, 'u' => $this->auth->id()]);

        return redirect()->to(site_url('plateforme/sites') . '#identifiants')->with('success', "Site « {$name} » créé.")->with('credentials', $creds);
    }

    public function updateSite(int $id)
    {
        $site = $this->db->table('sites')->where('id', $id)->get()->getRowArray() ?? throw PageNotFoundException::forPageNotFound();
        if (! $this->validate($this->siteRules())) {
            return redirect()->to(site_url('plateforme/sites') . '?modifier=' . $id . '#formulaire')->withInput()->with('errors', $this->validator->getErrors());
        }
        $active = (bool) $this->request->getPost('active');
        if (! $active && Settings::isStandalone() && Settings::standaloneSiteId() === $id && $this->db->table('sites')->where('active', 1)->countAllResults() === 1) {
            return redirect()->to(site_url('plateforme/sites') . '?modifier=' . $id . '#formulaire')->with('error', 'En mode autonome, le seul site actif ne peut pas être désactivé.');
        }
        $data = [
            'name' => trim((string) $this->request->getPost('name')), 'address' => trim((string) $this->request->getPost('address')),
            'tarif_timbre' => (float) $this->request->getPost('tarif_timbre'), 'tarif_carte' => (float) $this->request->getPost('tarif_carte'), 'active' => $active ? 1 : 0,
        ];
        $img = $this->image('site_logo');
        if (is_string($img)) {
            return redirect()->to(site_url('plateforme/sites') . '?modifier=' . $id . '#formulaire')->withInput()->with('errors', ['site_logo' => $img]);
        }
        if ($img) {
            $rel = '/uploads/logos/site-logo-' . $id . '.' . $img['ext'];
            if ($this->storeShared($img['tmp'], $rel)) {
                $data['logo_path'] = $rel;
            }
        }
        if ($this->request->getPost('remove_logo')) {
            $data['logo_path'] = null;
        }
        $this->db->table('sites')->where('id', $id)->update($data);

        return $this->back('sites#site-' . $id, 'success', 'Site « ' . $data['name'] . ' » enregistré.');
    }

    // ------------------------------------------------------------------
    // Comptes (Super Admin et secrétaires de tous les sites)
    // ------------------------------------------------------------------

    public function accounts()
    {
        return $this->page('platform/accounts', [
            'title' => 'Comptes', 'credentials' => session('credentials'),
            'sites' => $this->db->table('sites')->select('id, name')->orderBy('name')->get()->getResultArray(),
            'accounts' => $this->db->query("SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.active, u.last_login, s.name AS site_name
                FROM users u LEFT JOIN sites s ON s.id = u.site_id WHERE u.role IN ('super_admin', 'secretaire') ORDER BY u.role = 'super_admin' DESC, s.name, u.last_name")->getResultArray(),
        ]);
    }

    public function createAccount()
    {
        $role  = (string) $this->request->getPost('role') === 'super_admin' ? 'super_admin' : 'secretaire';
        $rules = [
            'first_name' => ['rules' => 'required|max_length[100]', 'errors' => ['required' => 'Indiquez le prénom.', 'max_length' => 'Prénom trop long.']],
            'last_name' => ['rules' => 'required|max_length[100]', 'errors' => ['required' => 'Indiquez le nom.', 'max_length' => 'Nom trop long.']],
            'email' => ['rules' => 'required|valid_email|max_length[255]|is_unique[users.email]', 'errors' => ['required' => 'Indiquez l’adresse e-mail.', 'valid_email' => 'L’adresse e-mail n’est pas valide.', 'max_length' => 'Adresse trop longue.', 'is_unique' => 'Un compte utilise déjà cette adresse.']],
        ];
        if ($role === 'secretaire') {
            $rules['site_id'] = ['rules' => 'required|is_not_unique[sites.id]', 'errors' => ['required' => 'Choisissez le site.', 'is_not_unique' => 'Choisissez le site.']];
        }
        if (! $this->validate($rules)) {
            return redirect()->to(site_url('plateforme/comptes') . '#formulaire')->withInput()->with('errors', $this->validator->getErrors());
        }
        $pw = self::randomPassword();
        $d  = [
            'first_name' => trim((string) $this->request->getPost('first_name')), 'last_name' => trim((string) $this->request->getPost('last_name')),
            'email' => mb_strtolower(trim((string) $this->request->getPost('email'))), 'role' => $role, 'active' => 1,
            'site_id' => $role === 'secretaire' ? (int) $this->request->getPost('site_id') : null, 'password' => password_hash($pw, PASSWORD_DEFAULT),
        ];
        $this->db->table('users')->insert($d);
        log_message('notice', 'Compte {role} {email} créé par le Super Admin {u}', ['role' => $role, 'email' => $d['email'], 'u' => $this->auth->id()]);

        return redirect()->to(site_url('plateforme/comptes') . '#identifiants')->with('success', 'Compte créé.')
            ->with('credentials', [['label' => $d['first_name'] . ' ' . $d['last_name'], 'email' => $d['email'], 'password' => $pw]]);
    }

    private function account(int $id): array
    {
        return $this->db->table('users')->where('id', $id)->whereIn('role', ['super_admin', 'secretaire'])->get()->getRowArray() ?? throw PageNotFoundException::forPageNotFound();
    }

    public function resetPassword(int $id)
    {
        $u  = $this->account($id);
        $pw = self::randomPassword();
        $this->db->table('users')->where('id', $id)->update(['password' => password_hash($pw, PASSWORD_DEFAULT), 'remember_token' => null]);
        log_message('notice', 'Mot de passe du compte {id} réinitialisé par le Super Admin {u}', ['id' => $id, 'u' => $this->auth->id()]);

        return redirect()->to(site_url('plateforme/comptes') . '#identifiants')->with('success', 'Nouveau mot de passe créé.')
            ->with('credentials', [['label' => $u['first_name'] . ' ' . $u['last_name'], 'email' => $u['email'], 'password' => $pw]]);
    }

    public function toggleAccount(int $id)
    {
        $u = $this->account($id);
        if ($id === (int) $this->auth->id()) {
            return $this->back('comptes', 'error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }
        if ($u['role'] === 'super_admin' && $u['active'] && $this->db->table('users')->where(['role' => 'super_admin', 'active' => 1])->countAllResults() <= 1) {
            return $this->back('comptes', 'error', 'Il doit rester au moins un Super Admin actif.');
        }
        $this->db->table('users')->where('id', $id)->update(['active' => $u['active'] ? 0 : 1, 'remember_token' => null]);

        return $this->back('comptes#compte-' . $id, 'success', $u['active'] ? 'Compte désactivé.' : 'Compte réactivé.');
    }

    // ------------------------------------------------------------------
    // Réglages
    // ------------------------------------------------------------------

    public const TABS = ['general' => 'Général', 'application' => 'Application installée', 'courriels' => 'Courriels', 'notifications' => 'Notifications', 'legal' => 'Textes légaux', 'avance' => 'Avancé'];

    public function settings()
    {
        $tab = (string) $this->request->getGet('onglet');
        $tab = isset(self::TABS[$tab]) ? $tab : 'general';
        $cfg = config('Fo');

        return $this->page('platform/settings', [
            'title' => 'Réglages', 'tab' => $tab, 's' => static fn (string $k, string $d = '') => Settings::get($k, $d),
            'vapidFromEnv' => $cfg->vapidPublic !== '', 'vapidPublic' => WebPush::publicKeyConfigured(),
            'subscriptions' => $this->db->tableExists('push_subscriptions') ? $this->db->table('push_subscriptions')->countAllResults() : 0,
            'legacyPath' => (string) env('fo.legacyPath', ''),
        ]);
    }

    public function saveSettings(string $tab)
    {
        $p   = fn (string $k, string $d = '') => trim((string) ($this->request->getPost($k) ?? $d));
        $msg = 'Réglages enregistrés.';
        switch ($tab) {
            case 'general':
                if ($p('app_name') === '') {
                    return redirect()->to(site_url('plateforme/reglages?onglet=general'))->withInput()->with('errors', ['app_name' => 'Indiquez le nom de l’application.']);
                }
                foreach (['app_name' => 120, 'app_description' => 500, 'footer_copyright' => 120, 'footer_text' => 500] as $k => $max) {
                    Settings::set($k, mb_substr($p($k), 0, $max));
                }
                break;

            case 'application':
                $color = static fn (string $v, string $d) => preg_match('/^#[0-9a-f]{6}$/i', $v) ? strtolower($v) : $d;
                Settings::set('pwa_short_name', mb_substr($p('pwa_short_name') ?: Settings::appName(), 0, 30));
                Settings::set('pwa_theme_color', $color($p('pwa_theme_color'), '#ffffff'));
                Settings::set('pwa_background_color', $color($p('pwa_background_color'), '#f1f5f9'));
                Settings::set('pwa_display', in_array($p('pwa_display'), ['standalone', 'fullscreen', 'minimal-ui', 'browser'], true) ? $p('pwa_display') : 'standalone');
                foreach (['pwa_logo' => ['pwa_logo_path', 'logo-custom', '/assets/icons/fostellantis.png'], 'favicon' => ['pwa_favicon_path', 'favicon-custom', '/assets/icons/favicon.svg']] as $field => [$key, $file, $default]) {
                    if ($this->request->getPost('reset_' . $field)) {
                        Settings::set($key, $default);

                        continue;
                    }
                    $img = $this->image($field . '_file');
                    if (is_string($img)) {
                        return redirect()->to(site_url('plateforme/reglages?onglet=application'))->withInput()->with('errors', [$field . '_file' => $img]);
                    }
                    if ($img && $this->storeShared($img['tmp'], '/assets/icons/' . $file . '.' . $img['ext'])) {
                        Settings::set($key, '/assets/icons/' . $file . '.' . $img['ext']);
                    }
                }
                break;

            case 'courriels':
                if ($p('smtp_from') !== '' && ! filter_var($p('smtp_from'), FILTER_VALIDATE_EMAIL)) {
                    return redirect()->to(site_url('plateforme/reglages?onglet=courriels'))->withInput()->with('errors', ['smtp_from' => 'L’adresse d’expédition n’est pas valide.']);
                }
                Settings::set('smtp_host', preg_replace('/[^a-z0-9.\-]/i', '', $p('smtp_host')));
                Settings::set('smtp_port', (string) max(1, min(65535, (int) ($p('smtp_port') ?: 587))));
                Settings::set('smtp_user', $p('smtp_user'));
                if ((string) $this->request->getPost('smtp_pass') !== '') {
                    Settings::set('smtp_pass', (string) $this->request->getPost('smtp_pass'));
                }
                Settings::set('smtp_from', $p('smtp_from'));
                Settings::set('smtp_from_name', str_replace(["\r", "\n"], '', $p('smtp_from_name')));
                Settings::set('smtp_encryption', in_array($p('smtp_encryption'), ['tls', 'ssl', 'none'], true) ? $p('smtp_encryption') : 'tls');
                break;

            case 'notifications':
                if ($this->request->getPost('generate') && config('Fo')->vapidPublic === '') {
                    $k = WebPush::generateKeys();
                    Settings::set('vapid_public_key', $k['publicKey']);
                    Settings::set('vapid_private_key', $k['privateKey']);
                    $msg = 'Nouvelles clés créées. Chaque téléphone doit réactiver les notifications depuis « Mon profil ».';
                    $this->db->table('push_subscriptions')->truncate();
                }
                $sub = $p('vapid_subject');
                if ($sub !== '' && ! preg_match('#^(mailto:[^@\s]+@[^@\s]+|https://\S+)$#', $sub)) {
                    return redirect()->to(site_url('plateforme/reglages?onglet=notifications'))->withInput()->with('errors', ['vapid_subject' => 'Indiquez « mailto: » suivi d’une adresse e-mail, ou une adresse https.']);
                }
                Settings::set('vapid_subject', $sub);
                break;

            case 'legal':
                foreach (['legal_mentions', 'legal_rgpd', 'legal_cgu'] as $k) {
                    Settings::set($k, mb_substr((string) $this->request->getPost($k), 0, 20000));
                }
                break;

            case 'avance':
                $mode = $p('app_mode') === 'standalone' ? 'standalone' : 'saas';
                if ($mode === 'standalone' && ! Settings::standaloneSiteId()) {
                    return redirect()->to(site_url('plateforme/reglages?onglet=avance'))->with('error', 'Le mode autonome demande au moins un site actif.');
                }
                Settings::set('app_mode', $mode);
                Settings::set('maintenance_mode', $this->request->getPost('maintenance_mode') ? '1' : '0');
                Settings::set('kb_shared', $this->request->getPost('kb_shared') ? '1' : '0');
                $msg = 'Réglages avancés enregistrés' . ($mode === 'standalone' ? ' : mode autonome, vous agissez comme secrétaire du site.' : '.');
                break;

            default:
                throw PageNotFoundException::forPageNotFound();
        }

        return redirect()->to(site_url('plateforme/reglages?onglet=' . $tab))->with('success', $msg);
    }

    public function testMail()
    {
        $to = trim((string) $this->request->getPost('smtp_test_email'));
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return redirect()->to(site_url('plateforme/reglages?onglet=courriels') . '#essai')->withInput()->with('errors', ['smtp_test_email' => 'L’adresse e-mail n’est pas valide.']);
        }
        $ok = Mailer::send($to, Settings::appName() . ' : essai d’envoi', 'Essai envoyé le ' . date('d/m/Y à H:i:s') . ".\nSi vous lisez ce message, l’envoi des courriels fonctionne.\n");

        return redirect()->to(site_url('plateforme/reglages?onglet=courriels') . '#essai')->with($ok ? 'success' : 'error', $ok ? "Courriel d’essai envoyé à {$to}." : 'L’envoi a échoué. Vérifiez le serveur, le port, le chiffrement et l’identifiant.');
    }

    // ------------------------------------------------------------------
    // Sauvegardes et exports
    // ------------------------------------------------------------------

    private function tables(): array
    {
        $out = [];
        foreach ($this->db->query("SELECT TABLE_NAME AS t, TABLE_ROWS AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->getResultArray() as $r) {
            $out[$r['t']] = (int) $r['n'];
        }

        return $out;
    }

    public function backups()
    {
        return $this->page('platform/backups', ['title' => 'Sauvegardes', 'tables' => $this->tables(), 'backups' => $this->updater()->listBackups(), 'importReport' => session('import_report')]);
    }

    public function exportSql()
    {
        $wanted = array_values(array_intersect(array_keys($this->tables()), (array) $this->request->getPost('tables')));
        if (! $wanted) {
            return $this->back('sauvegardes', 'error', 'Cochez au moins une table.');
        }
        $tmp = tempnam(WRITEPATH . 'cache', 'sql');
        $fh  = fopen($tmp, 'wb');
        $this->updater()->dumpSql(static function (string $s) use ($fh) { fwrite($fh, $s); }, $wanted);
        fclose($fh);
        register_shutdown_function(static fn () => @unlink($tmp));
        log_message('notice', 'Export SQL ({n} tables) par le Super Admin {u}', ['n' => count($wanted), 'u' => $this->auth->id()]);

        return $this->response->download($tmp, null)->setFileName('fo_gestion_export_' . date('Y-m-d_H-i') . '.sql')->setContentType('application/sql');
    }

    public function exportCsv()
    {
        $table = (string) $this->request->getPost('csv_table');
        if ($table === '__comptes') {
            $rows = (new MemberImport($this->db, 0))->users(null);

            return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')->setHeader('Content-Disposition', 'attachment; filename="utilisateurs_tous_sites_' . date('Y-m-d') . '.csv"')->setBody(MemberImport::csv($rows));
        }
        if (! array_key_exists($table, $this->tables())) {
            return $this->back('sauvegardes', 'error', 'Table inconnue.');
        }
        $rows = $this->db->query('SELECT * FROM `' . $table . '`')->getResultArray();
        $out  = $rows ? [array_keys($rows[0])] : [];
        foreach ($rows as $r) {
            $out[] = array_map(static fn ($v) => csv_safe((string) $v), array_values($r));
        }

        return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')->setHeader('Cache-Control', 'no-store')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $table . '_' . date('Y-m-d_H-i') . '.csv"')->setBody(MemberImport::csv($out));
    }

    public function backupNow()
    {
        $b = $this->updater()->backupDatabase();
        $this->updater()->pruneBackups(10);

        return $this->back('sauvegardes#sauvegardes', $b['ok'] ? 'success' : 'error', $b['ok'] ? "Sauvegarde créée : {$b['file']} (" . plural($b['rows'], 'ligne', 'lignes') . ').' : $b['error']);
    }

    public function downloadBackup(string $name)
    {
        $p = $this->updater()->backupPath($name) ?? throw PageNotFoundException::forPageNotFound();

        return $this->response->download($p, null)->setFileName($name)->setContentType('application/octet-stream');
    }

    public function deleteBackup()
    {
        $p = $this->updater()->backupPath((string) $this->request->getPost('file'));
        if ($p) {
            @unlink($p);
        }

        return $this->back('sauvegardes#sauvegardes', 'success', 'Sauvegarde supprimée.');
    }

    /**
     * Import SQL (restauration). Protégé : mot de passe du Super Admin, mot « RESTAURER » à recopier,
     * sauvegarde automatique avant exécution. La 2.x découpait le fichier à chaque « ; », y compris dans les textes.
     */
    public function importSql()
    {
        $me = $this->db->table('users')->select('password')->where('id', $this->auth->id())->get()->getRowArray();
        if (! $me || ! password_verify((string) $this->request->getPost('password'), $me['password'])) {
            return redirect()->to(site_url('plateforme/sauvegardes') . '#restauration')->with('errors', ['password' => 'Mot de passe incorrect.']);
        }
        if (trim((string) $this->request->getPost('confirm')) !== 'RESTAURER') {
            return redirect()->to(site_url('plateforme/sauvegardes') . '#restauration')->with('errors', ['confirm' => 'Recopiez le mot RESTAURER en majuscules.']);
        }
        $f = $this->request->getFile('sql_file');
        if (! $f || ! $f->isValid() || $f->getSize() > 50 * 1024 * 1024) {
            return redirect()->to(site_url('plateforme/sauvegardes') . '#restauration')->with('errors', ['sql_file' => 'Choisissez un fichier .sql ou .sql.gz de 50 Mo au plus.']);
        }
        $name = strtolower($f->getClientName());
        $sql  = str_ends_with($name, '.gz') ? @gzdecode((string) file_get_contents($f->getTempName())) : (str_ends_with($name, '.sql') ? file_get_contents($f->getTempName()) : false);
        if (! is_string($sql) || trim($sql) === '') {
            return redirect()->to(site_url('plateforme/sauvegardes') . '#restauration')->with('errors', ['sql_file' => 'Fichier vide ou illisible (formats acceptés : .sql, .sql.gz).']);
        }
        $u = $this->updater();
        $b = $u->backupDatabase();
        if (! $b['ok']) {
            return $this->back('sauvegardes', 'error', 'Sauvegarde préalable impossible, import annulé : ' . $b['error']);
        }
        $my     = $this->db->connID;
        $done   = 0;
        $errors = [];
        foreach (Updater::splitSql($sql) as $stmt) {
            try {
                $ok = $my->query($stmt);
            } catch (Throwable) {
                $ok = false;
            }
            if ($ok === false) {
                $errors[] = mb_substr($my->error . ' | ' . preg_replace('/\s+/', ' ', mb_substr($stmt, 0, 120)), 0, 220);
            } else {
                $done++;
            }
        }
        log_message('notice', 'Import SQL par le Super Admin {u} : {n} instructions, {e} erreurs', ['u' => $this->auth->id(), 'n' => $done, 'e' => count($errors)]);

        return redirect()->to(site_url('plateforme/sauvegardes') . '#restauration')
            ->with('import_report', ['done' => $done, 'errors' => array_slice($errors, 0, 20), 'errorCount' => count($errors), 'backup' => $b['file']])
            ->with($errors ? 'error' : 'success', plural($done, 'instruction exécutée', 'instructions exécutées') . ($errors ? ', ' . plural(count($errors), 'erreur', 'erreurs') : '') . '.');
    }

    // ------------------------------------------------------------------
    // Mises à jour
    // ------------------------------------------------------------------

    public function updates()
    {
        $u = $this->updater();

        return $this->page('platform/updates', [
            'title' => 'Mises à jour', 'status' => $u->status(), 'reqs' => $u->requirements(), 'report' => session('update_report'),
            'integrity' => session('integrity'), 'installer' => is_file(FCPATH . 'installer.php'), 'maxUpload' => ini_get('upload_max_filesize'),
            'dbVersion' => (string) ($this->db->query('SELECT VERSION() AS v')->getRow()->v ?? ''),
            'build' => (json_decode((string) @file_get_contents(ROOTPATH . 'manifest-files.json'), true)['build'] ?? ''),
        ]);
    }

    public function install()
    {
        $f = $this->request->getFile('package');
        if (! $f || ! $f->isValid()) {
            $why = $f && in_array($f->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'fichier trop volumineux pour le serveur (limite : ' . ini_get('upload_max_filesize') . ')' : 'aucun fichier reçu';

            return $this->back('mises-a-jour', 'error', 'Envoi impossible : ' . $why . '.');
        }
        if (strtolower($f->getClientExtension()) !== 'zip') {
            return $this->back('mises-a-jour', 'error', 'Le fichier doit être un .zip.');
        }
        $r = $this->updater()->fullUpdate($f->getTempName(), (bool) $this->request->getPost('backup_db'), (bool) $this->request->getPost('backup_files'));
        log_message('notice', 'Mise à jour par le Super Admin {u} : {r}', ['u' => $this->auth->id(), 'r' => $r['ok'] ? 'réussie' : ($r['error'] ?? 'échec')]);
        $files = $r['steps']['files'] ?? null;
        $sum   = [
            'ok' => $r['ok'], 'error' => $r['error'] ?? null, 'version' => $files['version'] ?? null,
            'added' => count($files['added'] ?? []), 'updated' => count($files['updated'] ?? []), 'removed' => count($files['removed'] ?? []),
            'unchanged' => $files['unchanged'] ?? 0, 'fileErrors' => array_slice($files['errors'] ?? [], 0, 20),
            'migrations' => array_map(static fn ($m) => ['file' => $m['file'], 'ok' => $m['ok'], 'error' => $m['error']], $r['steps']['migrations'] ?? []),
            'backups' => array_values(array_filter([$r['steps']['backup_db']['file'] ?? null, $r['steps']['backup_files']['file'] ?? null])),
        ];

        return redirect()->to(site_url('plateforme/mises-a-jour') . '#rapport')->with('update_report', $sum)
            ->with($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Mise à jour installée : version ' . $sum['version'] . '.' : 'Mise à jour interrompue : ' . ($r['error'] ?? 'erreur inconnue'));
    }

    public function migrate()
    {
        $u    = $this->updater();
        $lock = $u->lock();
        if (! $lock) {
            return $this->back('mises-a-jour', 'error', 'Une mise à jour est déjà en cours.');
        }

        try {
            $b   = $u->backupDatabase();
            $res = $b['ok'] ? $u->runPending() : [];
        } finally {
            $u->unlock($lock);
        }
        if (! $b['ok']) {
            return $this->back('mises-a-jour', 'error', 'Sauvegarde préalable impossible : ' . $b['error']);
        }
        $failed = array_filter($res, static fn ($m) => ! $m['ok']);

        return $this->back('mises-a-jour#migrations', $failed ? 'error' : 'success', $failed ? 'Migration en échec : ' . reset($failed)['file'] . ' : ' . reset($failed)['error'] : plural(count($res), 'migration appliquée', 'migrations appliquées') . '.');
    }

    public function integrity()
    {
        return redirect()->to(site_url('plateforme/mises-a-jour') . '#integrite')->with('integrity', $this->updater()->integrity());
    }

    public function cleanup()
    {
        $ok = is_file(FCPATH . 'installer.php') && is_file(ROOTPATH . '.env') && @unlink(FCPATH . 'installer.php');

        return $this->back('mises-a-jour', $ok ? 'success' : 'error', $ok ? 'installer.php supprimé.' : 'Suppression impossible : supprimez public/installer.php par FTP.');
    }
}
