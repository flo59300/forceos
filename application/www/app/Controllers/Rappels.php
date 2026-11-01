<?php

namespace App\Controllers;

use App\Domain\MemberStatus;
use App\Domain\Site;
use App\Libraries\Mailer;
use App\Libraries\Settings;

/**
 * Rappels par courriel aux collecteurs (secrétaire) : activation, fréquence, essai d'envoi.
 */
class Rappels extends BaseController
{
    public function index()
    {
        $sid  = (int) $this->auth->siteId();
        $site = $this->db->table('sites')->where('id', $sid)->get()->getRowArray();
        $st   = (new MemberStatus($this->db))->forSite($sid, (new Site($this->db))->currentYear($sid));
        $cols = $this->db->query("SELECT u.id, u.first_name, u.last_name, u.email, (SELECT COUNT(*) FROM members m WHERE m.assigned_user_id = u.id AND m.is_radiated = 0) AS nb,
                (SELECT MAX(sent_at) FROM reminder_logs r WHERE r.user_id = u.id) AS last_sent
             FROM users u WHERE u.site_id = ? AND u.role = 'collecteur' AND u.active = 1 ORDER BY u.last_name", [$sid])->getResultArray();

        return $this->page('admin/rappels', [
            'title' => 'Rappels aux collecteurs', 'site' => $site, 'collecteurs' => $cols,
            'late' => count(array_filter($st, static fn ($s) => $s['status'] === 'en_attente')),
            'smtp' => Settings::get('smtp_host') !== '',
        ]);
    }

    public function save()
    {
        $on   = (bool) $this->request->getPost('notifications_enabled');
        $freq = max(1, min(30, (int) $this->request->getPost('reminder_frequency_days')));
        $this->db->table('sites')->where('id', $this->auth->siteId())->update(['notifications_enabled' => $on ? 1 : 0, 'reminder_frequency_days' => $freq]);

        return redirect()->to(site_url('administration/rappels'))->with('success', $on ? "Rappels activés, tous les {$freq} jours au plus." : 'Rappels désactivés.');
    }

    public function test()
    {
        $to = trim((string) $this->request->getPost('test_email'));
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return redirect()->to(site_url('administration/rappels') . '#essai')->withInput()->with('errors', ['test_email' => 'L’adresse e-mail n’est pas valide.']);
        }
        $site = (string) session('site_name');
        $ok   = Mailer::send($to, "[{$site}] Essai d’envoi", "Bonjour,\n\nCeci est un essai d’envoi depuis " . Settings::appName() . ".\nSite : {$site}\nDate : " . date('d/m/Y H:i') . "\nEnvoyé par : " . session('user_name') . "\n\nSi vous lisez ce message, les rappels par courriel fonctionnent.\n");

        return redirect()->to(site_url('administration/rappels') . '#essai')->with($ok ? 'success' : 'error', $ok ? "Courriel d’essai envoyé à {$to}." : "L’envoi a échoué. Vérifiez les réglages d’envoi des courriels (Super Admin).");
    }
}
