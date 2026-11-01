<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Throwable;

/**
 * Notifications : centre de notifications (table notifications) et envoi push aux appareils abonnés.
 * Mêmes règles que includes/push_helpers.php (2.x) : la notification est toujours enregistrée ;
 * le push respecte la préférence de l'utilisateur (messages, délégation, agenda) quand elle s'applique.
 */
class Notifier
{
    public const PREFS = ['messages' => 'push_notif_messages', 'delegation' => 'push_notif_delegation', 'agenda' => 'push_notif_agenda'];

    /** Pour les tests : messages push préparés au lieu d'être envoyés. */
    public static ?array $sent = null;

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= Database::connect();
    }

    /**
     * @param list<int> $userIds
     * @param string $type message | delegation | agenda | transfer | system
     * @param ?string $pref clé de PREFS à respecter pour le push, ou null
     */
    public function notify(array $userIds, string $title, string $body, string $url, string $type = 'system', string $icon = '🔔', ?string $pref = null, string $tag = 'general'): int
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (! $userIds) {
            return 0;
        }
        try {
            $sites = array_column($this->db->table('users')->select('id, site_id')->whereIn('id', $userIds)->get()->getResultArray(), 'site_id', 'id');
            $rows  = [];
            foreach ($userIds as $u) {
                $rows[] = ['user_id' => $u, 'site_id' => $sites[$u] ?? null, 'title' => mb_substr($title, 0, 255), 'body' => $body, 'url' => mb_substr($url, 0, 500), 'type' => $type, 'icon' => $icon];
            }
            $this->db->table('notifications')->insertBatch($rows);
        } catch (Throwable $e) {
            log_message('error', 'Notifications non enregistrées : ' . $e->getMessage());
        }

        return $this->push($userIds, $title, $body, $url, $tag, $pref);
    }

    /** Envoi push seul (sans enregistrement), pour les rappels planifiés et le test. */
    public function push(array $userIds, string $title, string $body, string $url, string $tag = 'general', ?string $pref = null): int
    {
        try {
            $b = $this->db->table('push_subscriptions ps')->select('ps.user_id, ps.endpoint, ps.p256dh, ps.auth')
                ->join('users u', 'u.id = ps.user_id')->whereIn('ps.user_id', $userIds)->where('u.active', 1);
            if ($pref && isset(self::PREFS[$pref])) {
                $b->where('u.' . self::PREFS[$pref], 1);
            }
            $subs = $b->get()->getResultArray();
        } catch (Throwable) {
            return 0;
        }
        if (! $subs) {
            return 0;
        }
        $payload = json_encode([
            'title' => $title, 'body' => $body, 'url' => site_url(ltrim($url, '/')), 'tag' => $tag,
            'icon' => base_url('assets/icons/fostellantis.png'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (self::$sent !== null) {
            foreach ($subs as $s) {
                self::$sent[] = ['user_id' => (int) $s['user_id'], 'payload' => $payload];
            }

            return count($subs);
        }
        $wp = WebPush::fromConfig();
        if (! $wp) {
            return 0;
        }
        $ok = 0;
        foreach ($wp->sendBatch($subs, $payload) as $i => $r) {
            if ($r['success']) {
                $ok++;
                $this->db->table('push_subscriptions')->where('endpoint', $r['endpoint'])->update(['last_used_at' => date('Y-m-d H:i:s')]);
            } elseif (in_array($r['statusCode'], [404, 410], true)) {
                // Abonnement expiré : supprimé, comme la 2.x
                $this->db->table('push_subscriptions')->where(['user_id' => $subs[$i]['user_id'], 'endpoint' => $r['endpoint']])->delete();
            }
        }

        return $ok;
    }

    /** Tous les comptes actifs d'un site (hors Super Admin), sauf l'auteur. */
    public function siteUsers(int $siteId, int $except = 0): array
    {
        return array_map('intval', array_column($this->db->table('users')->select('id')->where(['site_id' => $siteId, 'active' => 1])
            ->where('role !=', 'super_admin')->where('id !=', $except)->get()->getResultArray(), 'id'));
    }
}
