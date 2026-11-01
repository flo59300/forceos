<?php

namespace App\Controllers;

/**
 * Centre de notifications (table notifications, partagée avec la 2.x).
 */
class Notifications extends BaseController
{
    /** Adresses enregistrées par la 2.x, traduites vers les écrans du hub. */
    private const LEGACY = [
        '/pages/agenda.php' => '/agenda', '/pages/messages.php' => '/messages', '/pages/outils.php' => '/heures',
        '/pages/delegues.php' => '/taches', '/pages/annonces.php' => '/annonces', '/pages/permanences.php' => '/permanences',
        '/pages/members.php' => '/adherents', '/pages/reversements.php' => '/finances/reversements', '/index.php' => '/',
    ];

    public static function target(?string $url): string
    {
        $url = (string) $url;
        if ($url === '' || ! str_starts_with($url, '/') || str_starts_with($url, '//') || preg_match('/[\r\n\\\\]/', $url)) {
            return '/';
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (isset(self::LEGACY[$path])) {
            return self::LEGACY[$path];
        }

        return $url; // anciennes adresses /pages/… : redirigées par Pwa::legacy
    }

    public function index()
    {
        $uid   = (int) $this->auth->id();
        $page  = max(1, (int) $this->request->getGet('page'));
        $total = $this->db->table('notifications')->where('user_id', $uid)->countAllResults();
        $items = $this->db->table('notifications')->where('user_id', $uid)->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')
            ->limit(30, ($page - 1) * 30)->get()->getResultArray();

        return $this->page('notifications/index', ['title' => 'Notifications', 'items' => $items, 'page' => $page, 'pages' => max(1, (int) ceil($total / 30)), 'total' => $total]);
    }

    public function open(int $id)
    {
        $n = $this->db->table('notifications')->where(['id' => $id, 'user_id' => $this->auth->id()])->get()->getRowArray();
        if (! $n) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $this->db->table('notifications')->where('id', $id)->update(['is_read' => 1]);

        return redirect()->to(site_url(ltrim(self::target($n['url']), '/')));
    }

    public function readAll()
    {
        $this->db->table('notifications')->where(['user_id' => $this->auth->id(), 'is_read' => 0])->update(['is_read' => 1]);

        return redirect()->to(site_url('notifications'))->with('success', 'Toutes les notifications sont marquées comme lues.');
    }

    public function clear()
    {
        $this->db->table('notifications')->where('user_id', $this->auth->id())->where('created_at <', date('Y-m-d H:i:s', strtotime('-30 days')))->delete();

        return redirect()->to(site_url('notifications'))->with('success', 'Notifications de plus de 30 jours supprimées.');
    }
}
