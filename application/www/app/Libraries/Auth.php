<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Config\Fo;

/**
 * Authentification et droits.
 *
 * Reprend à l'identique les règles de FO Gestion 2.x (includes/Auth.php) :
 * mêmes empreintes bcrypt, même cookie « se souvenir de moi » (id:jeton),
 * inactivité de 30 minutes, empreinte de navigateur, quatre rôles.
 * Différence volontaire : le compteur d'échecs est conservé côté serveur
 * (cache) et non plus dans la session, qu'un attaquant pouvait simplement jeter.
 */
class Auth
{
    public const ROLES = ['super_admin', 'secretaire', 'tresorier', 'collecteur'];

    private BaseConnection $db;
    private Fo $cfg;
    private ?array $userCache = null;

    public function __construct(?BaseConnection $db = null, ?Fo $cfg = null)
    {
        $this->db  = $db ?? Database::connect();
        $this->cfg = $cfg ?? config(Fo::class);
    }

    // ------------------------------------------------------------------
    // Connexion
    // ------------------------------------------------------------------

    /**
     * @return string 'ok' | 'invalid' | 'locked'
     */
    public function attempt(string $email, string $password, bool $remember = false): string
    {
        if ($this->isLockedOut()) {
            return 'locked';
        }

        $user = $this->db->table('users u')
            ->select('u.*, s.name AS site_name')
            ->join('sites s', 's.id = u.site_id', 'left')
            ->where('u.email', trim($email))
            ->where('u.active', 1)
            ->get()->getRowArray();

        // Vérification factice si l'utilisateur n'existe pas : même durée de réponse
        $hash = $user['password'] ?? '$2y$10$dummyhashtopreventtimingattackxyz12345678';
        if (! password_verify($password, $hash) || ! $user) {
            $this->recordFailure();

            return 'invalid';
        }

        $this->db->table('users')->where('id', $user['id'])->update(['last_login' => date('Y-m-d H:i:s')]);
        $this->clearFailures();
        $this->startSession($user);

        if ($remember) {
            $this->issueRememberToken((int) $user['id']);
        }

        return 'ok';
    }

    /** Reconnexion automatique par le cookie « se souvenir de moi » (format 2.x : "id:jeton"). */
    public function loginFromRemember(): bool
    {
        $cookie = (string) (service('request')->getCookie($this->cfg->rememberCookie) ?? '');
        if (! str_contains($cookie, ':')) {
            return false;
        }
        [$id, $token] = explode(':', $cookie, 2);
        $id = (int) $id;
        if ($id <= 0 || $token === '') {
            return false;
        }

        $user = $this->db->table('users u')
            ->select('u.*, s.name AS site_name')
            ->join('sites s', 's.id = u.site_id', 'left')
            ->where(['u.id' => $id, 'u.active' => 1])
            ->where('u.remember_token IS NOT NULL')
            ->get()->getRowArray();

        if (! $user || ! password_verify($token, $user['remember_token'])) {
            $this->forgetRememberCookie();

            return false;
        }

        $this->db->table('users')->where('id', $id)->update(['last_login' => date('Y-m-d H:i:s')]);
        $this->startSession($user);
        $this->issueRememberToken($id); // rotation du jeton, comme en 2.x

        return true;
    }

    public function logout(): void
    {
        if ($this->id()) {
            $this->db->table('users')->where('id', $this->id())->update(['remember_token' => null]);
        }
        $this->forgetRememberCookie();
        session()->destroy();
        $this->userCache = null;
    }

    /**
     * Vérifie la session en cours : inactivité et empreinte du navigateur.
     * Tente la reconnexion par cookie si aucune session n'existe.
     */
    public function check(): bool
    {
        $s = session();
        if (! $s->get('user_id')) {
            return $this->loginFromRemember();
        }
        if ((time() - (int) $s->get('_last_activity')) > $this->cfg->idleTimeout) {
            $this->logout();

            return false;
        }
        if ($s->get('_fingerprint') !== $this->fingerprint()) {
            $this->logout();

            return false;
        }
        $s->set('_last_activity', time());

        return true;
    }

    // ------------------------------------------------------------------
    // Identité et droits (mêmes règles que Auth.php 2.x)
    // ------------------------------------------------------------------

    public function id(): ?int
    {
        $id = session('user_id');

        return $id ? (int) $id : null;
    }

    public function role(): ?string
    {
        return session('user_role');
    }

    public function user(): ?array
    {
        if ($this->userCache === null && $this->id()) {
            $this->userCache = $this->db->table('users u')
                ->select('u.id, u.email, u.first_name, u.last_name, u.role, u.site_id, u.member_id, s.name AS site_name, s.logo_path')
                ->join('sites s', 's.id = u.site_id', 'left')
                ->where('u.id', $this->id())->get()->getRowArray();
        }

        return $this->userCache;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role() === 'super_admin';
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role(), $roles, true);
    }

    /** Le Super Admin agit comme un secrétaire en mode autonome (version locale). */
    public function effectiveRole(): ?string
    {
        return ($this->isSuperAdmin() && Settings::isStandalone()) ? 'secretaire' : $this->role();
    }

    public function siteId(): ?int
    {
        $id = session('user_site_id');
        if ($id === null && $this->isSuperAdmin() && Settings::isStandalone()) {
            return Settings::standaloneSiteId();
        }

        return $id !== null ? (int) $id : null;
    }

    public function canManageMembers(): bool
    {
        return in_array($this->effectiveRole(), ['secretaire', 'collecteur'], true);
    }

    public function canManageStamps(): bool
    {
        return in_array($this->effectiveRole(), ['secretaire', 'collecteur'], true);
    }

    public function canManageReversements(): bool
    {
        return in_array($this->effectiveRole(), ['secretaire', 'tresorier'], true);
    }

    public function canConfigure(): bool
    {
        return $this->effectiveRole() === 'secretaire';
    }

    public function canManageUsers(): bool
    {
        return $this->hasRole('super_admin', 'secretaire');
    }

    // ------------------------------------------------------------------
    // Interne
    // ------------------------------------------------------------------

    private function startSession(array $user): void
    {
        $s = session();
        $s->regenerate(true);
        $s->set([
            'user_id'        => (int) $user['id'],
            'user_role'      => $user['role'],
            'user_site_id'   => $user['site_id'] !== null ? (int) $user['site_id'] : null,
            'user_name'      => trim($user['first_name'] . ' ' . $user['last_name']),
            'user_first'     => $user['first_name'],
            'site_name'      => $user['site_name'] ?? 'Tous les sites',
            '_last_activity' => time(),
            '_fingerprint'   => $this->fingerprint(),
        ]);
        $this->userCache = null;
    }

    private function fingerprint(): string
    {
        $r = service('request');

        return hash('sha256', $r->getUserAgent()->getAgentString() . $r->getHeaderLine('Accept-Language'));
    }

    private function issueRememberToken(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $this->db->table('users')->where('id', $userId)->update(['remember_token' => password_hash($token, PASSWORD_DEFAULT)]);
        service('response')->setCookie($this->cfg->rememberCookie, $userId . ':' . $token, $this->cfg->rememberDays * 86400, '', '/', '', null, true, 'Lax');
    }

    private function forgetRememberCookie(): void
    {
        service('response')->deleteCookie($this->cfg->rememberCookie, '', '/');
    }

    private function throttleKey(): string
    {
        return 'fo_login_' . sha1((string) service('request')->getIPAddress());
    }

    private function isLockedOut(): bool
    {
        $a = cache($this->throttleKey());

        return is_array($a) && $a['count'] >= $this->cfg->maxLoginAttempts && (time() - $a['first']) < $this->cfg->lockoutSeconds;
    }

    private function recordFailure(): void
    {
        $a = cache($this->throttleKey());
        if (! is_array($a) || (time() - $a['first']) >= $this->cfg->lockoutSeconds) {
            $a = ['count' => 0, 'first' => time()];
        }
        $a['count']++;
        cache()->save($this->throttleKey(), $a, $this->cfg->lockoutSeconds);
    }

    private function clearFailures(): void
    {
        cache()->delete($this->throttleKey());
    }
}
