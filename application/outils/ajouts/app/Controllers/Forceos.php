<?php
/**
 * ForceOS : page « Sauvegarde chiffrée » de FO Gestion
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * La page délivre un ticket à usage unique (session et rôle vérifiés), que le
 * navigateur intégré remet au processus principal d'Electron. Lui seul peut
 * ensuite joindre les adresses internes d'export et de restauration.
 */
declare(strict_types=1);

namespace App\Controllers;

final class Forceos extends BaseController
{
    private function sousForceos(): bool
    {
        return (string) getenv('FORCEOS_DONNEES') !== '';
    }

    public function sauvegarde()
    {
        if (! $this->sousForceos()) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $role = (string) $this->auth->effectiveRole();
        return $this->page('forceos/sauvegarde', [
            'title'             => 'Sauvegarde chiffrée',
            'peutRestaurer'     => in_array($role, FORCEOS_ROLES_TICKETS['restauration'], true),
            'instantanes'       => count(glob(rtrim((string) getenv('FORCEOS_DONNEES'), '/') . '/instantanes/*.sql.chiffre') ?: []),
        ]);
    }

    /** Mode serveur : FO Gestion servie aux téléphones sur le réseau local (secrétaire). */
    public function serveur()
    {
        if (! $this->sousForceos()) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $role = (string) $this->auth->effectiveRole();
        return $this->page('forceos/serveur', [
            'title'     => 'Mode serveur pour les téléphones',
            'autorise'  => in_array($role, FORCEOS_ROLES_TICKETS['serveur'], true),
        ]);
    }

    public function ticket()
    {
        $usage = (string) $this->request->getPost('usage');
        $role  = (string) $this->auth->effectiveRole();
        if (! $this->sousForceos() || ! isset(FORCEOS_ROLES_TICKETS[$usage]) || ! in_array($role, FORCEOS_ROLES_TICKETS[$usage], true)) {
            return $this->response->setStatusCode(403)->setJSON(['erreur' => 'Vos droits ne permettent pas cette opération.']);
        }
        return $this->response->setHeader('Cache-Control', 'no-store')
            ->setJSON(['ticket' => forceos_creer_ticket((int) $this->auth->id(), $usage), 'csrf' => csrf_hash()]);
    }
}
