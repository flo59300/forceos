<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Libraries\Navigation;
use App\Libraries\Settings;
use CodeIgniter\Controller;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    protected $helpers = ['form', 'url', 'fo'];
    protected Auth $auth;
    protected BaseConnection $db;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->auth = service('auth');
        $this->db   = Database::connect();
    }

    /** Requête envoyée par htmx (fragment de page attendu). */
    protected function isHtmx(): bool
    {
        return $this->request->hasHeader('HX-Request') && ! $this->request->hasHeader('HX-Boosted');
    }

    /**
     * Rend une page dans la mise en page du hub, avec la navigation du rôle.
     */
    protected function page(string $view, array $data = []): string
    {
        $role = (string) $this->auth->effectiveRole();
        $path = $this->request->getUri()->getPath();

        return view($view, $data + [
            'auth'      => $this->auth,
            'role'      => $role,
            'me'        => $this->auth->user(),
            'appName'   => Settings::appName(),
            'nav'       => Navigation::main($role),
            'mobileNav' => Navigation::mobile($role),
            'quick'     => Navigation::quickActions($role),
            'active'    => Navigation::activeKey($path),
            'unread'    => $this->unreadCounts(),
        ]);
    }

    /** Compteurs de la barre : messages et notifications non lus. */
    protected function unreadCounts(): array
    {
        $uid = $this->auth->id();
        if (! $uid) {
            return ['messages' => 0, 'notifications' => 0];
        }

        return [
            'messages'      => $this->db->table('messages')->where(['recipient_id' => $uid, 'is_read' => 0])->countAllResults(),
            'notifications' => $this->db->table('notifications')->where(['user_id' => $uid, 'is_read' => 0])->countAllResults(),
        ];
    }

    /** Type de notification et préférence push déduits de l'écran visé (comme les « tags » de la 2.x). */
    private static function kind(string $url): array
    {
        return match (true) {
            str_starts_with($url, '/messages') => ['message', 'messages', 'message'],
            str_starts_with($url, '/heures') => ['delegation', 'delegation', 'delegation'],
            str_starts_with($url, '/agenda') => ['agenda', 'agenda', 'agenda'],
            default => ['system', null, 'general'],
        };
    }

    /** Notifie un compte : centre de notifications (partagé avec la 2.x) et push sur ses appareils. */
    protected function notify(int $userId, string $title, string $body, string $url, string $icon = '🔔'): void
    {
        [$type, $pref, $tag] = self::kind($url);
        (new \App\Libraries\Notifier($this->db))->notify([$userId], $title, $body, $url, $type, $icon, $pref, $tag . '-' . $userId);
    }

    /**
     * Notifie tous les comptes actifs du site (hors Super Admin), comme send_push_to_site de la 2.x.
     * L'auteur de l'action n'est pas notifié de sa propre publication.
     */
    protected function notifySite(string $title, string $body, string $url, string $type = 'system', string $icon = '🔔'): void
    {
        $n = new \App\Libraries\Notifier($this->db);
        [, $pref, $tag] = self::kind($url);
        $n->notify($n->siteUsers((int) $this->auth->siteId(), (int) $this->auth->id()), $title, $body, $url, $type, $icon, $pref, $tag . '-' . md5($url));
    }
}
