<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Réglages propres à FO Gestion.
 * Chaque valeur peut être surchargée dans le fichier .env (ex. : fo.idleTimeout = 1800).
 */
class Fo extends BaseConfig
{
    public string $appName = 'FO Gestion';

    /** Déconnexion après inactivité, en secondes (identique à la version 2.x). */
    public int $idleTimeout = 1800;

    /** Verrouillage après N échecs de connexion, pour une durée en secondes. */
    public int $maxLoginAttempts = 5;
    public int $lockoutSeconds = 900;

    /** Cookie « se souvenir de moi » : même nom et même format que la version 2.x. */
    public string $rememberCookie = 'remember_me';
    public int $rememberDays = 30;

    /** Clés VAPID des notifications push (reprendre celles de config.php). */
    public string $vapidPublic = '';
    public string $vapidPrivate = '';
    public string $vapidSubject = '';
}
