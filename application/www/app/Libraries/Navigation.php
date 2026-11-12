<?php

namespace App\Libraries;

/**
 * Navigation du hub, filtrée par rôle.
 *
 * Sept rubriques au plus sur ordinateur ; cinq entrées dans la barre du téléphone.
 * Chaque entrée : libellé, route, icône (nom du pictogramme), rôles autorisés, état de migration.
 * « ported » = écran disponible dans la nouvelle version ; sinon, page d'attente avec lien vers la 2.x.
 */
class Navigation
{
    private const SITE = ['secretaire', 'tresorier', 'collecteur'];

    /** Rubriques principales (menu latéral). */
    public static function main(string $role): array
    {
        if ($role === 'super_admin') {
            return self::platform();
        }
        $items = [
            ['key' => 'hub',       'label' => 'Ma journée',          'url' => '/',           'icon' => 'home',     'roles' => self::SITE, 'ported' => true],
            ['key' => 'adherents', 'label' => 'Adhérents',           'url' => '/adherents',  'icon' => 'users',    'roles' => ['secretaire', 'collecteur'], 'ported' => true, 'legacy' => 'pages/members.php'],
            ['key' => 'taches',    'label' => 'Tâches et questions', 'url' => '/taches',     'icon' => 'check',    'roles' => self::SITE, 'ported' => true],
            ['key' => 'heures',    'label' => 'Heures de délégation', 'url' => '/heures',    'icon' => 'clock',    'roles' => self::SITE, 'ported' => true, 'legacy' => 'pages/outils.php?tab=delegation'],
            ['key' => 'messages',  'label' => 'Messages',            'url' => '/messages',   'icon' => 'chat',     'roles' => self::SITE, 'ported' => true, 'legacy' => 'pages/messages.php'],
            ['key' => 'finances',  'label' => 'Finances',            'url' => '/finances',   'icon' => 'wallet',   'roles' => self::SITE, 'ported' => true, 'legacy' => 'pages/paiements.php'],
            ['key' => 'plus',      'label' => 'Tout le reste',       'url' => '/plus',       'icon' => 'grid',     'roles' => self::SITE, 'ported' => true],
        ];

        return array_values(array_filter($items, static fn ($i) => in_array($role, $i['roles'], true)));
    }

    /** Espace Super Admin (et, en mode autonome, rubrique « Plateforme » de « Tout le reste »). */
    public static function platform(): array
    {
        return [
            ['key' => 'plateforme', 'label' => 'Tableau de bord', 'url' => '/plateforme', 'icon' => 'home', 'desc' => 'Vue d’ensemble de la plateforme'],
            ['key' => 'sites', 'label' => 'Sites', 'url' => '/plateforme/sites', 'icon' => 'grid', 'desc' => 'Création, tarifs, logos'],
            ['key' => 'comptes', 'label' => 'Comptes', 'url' => '/plateforme/comptes', 'icon' => 'users', 'desc' => 'Secrétaires et Super Admin'],
            ['key' => 'reglages', 'label' => 'Réglages', 'url' => '/plateforme/reglages', 'icon' => 'edit', 'desc' => 'Application, courriels, notifications, textes légaux'],
            ['key' => 'sauvegardes', 'label' => 'Sauvegardes', 'url' => '/plateforme/sauvegardes', 'icon' => 'download', 'desc' => 'Export SQL et CSV de la base'],
            ['key' => 'mises-a-jour', 'label' => 'Mises à jour', 'url' => '/plateforme/mises-a-jour', 'icon' => 'shield', 'desc' => 'Paquet zip, migrations, intégrité'],
            ['key' => 'guide', 'label' => 'Guide', 'url' => '/guide', 'icon' => 'book', 'desc' => 'Aide'],
        ];
    }

    /** Barre du téléphone : Accueil, une rubrique métier, Saisir, Messages, Plus. */
    public static function mobile(string $role): array
    {
        if ($role === 'super_admin') {
            return array_map(static fn ($i) => $i + ['label' => $i['label']], array_slice(array_values(array_filter(self::platform(), static fn ($i) => in_array($i['key'], ['plateforme', 'sites', 'comptes', 'reglages', 'mises-a-jour'], true))), 0, 5));
        }
        $second = $role === 'tresorier'
            ? ['key' => 'finances', 'label' => 'Finances', 'url' => '/finances', 'icon' => 'wallet']
            : ['key' => 'adherents', 'label' => 'Adhérents', 'url' => '/adherents', 'icon' => 'users'];

        return [
            ['key' => 'hub', 'label' => 'Accueil', 'url' => '/', 'icon' => 'home'],
            $second,
            ['key' => 'saisir', 'label' => 'Saisir', 'url' => '#saisir', 'icon' => 'plus', 'action' => true],
            ['key' => 'messages', 'label' => 'Messages', 'url' => '/messages', 'icon' => 'chat'],
            ['key' => 'plus', 'label' => 'Plus', 'url' => '/plus', 'icon' => 'grid'],
        ];
    }

    /** Actions rapides : quatre au plus, la première mise en avant. */
    public static function quickActions(string $role): array
    {
        return match ($role) {
            'super_admin' => [],
            'secretaire' => [
                ['label' => 'Répondre aux questions', 'url' => '/questions?statut=ouvertes', 'icon' => 'chat'],
                ['label' => 'Assigner une tâche', 'url' => '/taches/nouvelle', 'icon' => 'check'],
                ['label' => 'Encaisser un timbre', 'url' => '/adherents', 'icon' => 'ticket'],
                ['label' => 'Saisir des heures', 'url' => '/heures', 'icon' => 'clock'],
            ],
            'tresorier' => [
                ['label' => 'Valider les reversements', 'url' => '/finances/reversements', 'icon' => 'wallet'],
                ['label' => 'Saisir des heures', 'url' => '/heures', 'icon' => 'clock'],
                ['label' => 'Poser une question', 'url' => '/questions/nouvelle', 'icon' => 'chat'],
                ['label' => 'Ajouter une tâche', 'url' => '/taches/nouvelle', 'icon' => 'check'],
            ],
            default => [
                ['label' => 'Encaisser un timbre', 'url' => '/adherents', 'icon' => 'ticket'],
                ['label' => 'Saisir des heures', 'url' => '/heures', 'icon' => 'clock'],
                ['label' => 'Poser une question', 'url' => '/questions/nouvelle', 'icon' => 'chat'],
                ['label' => 'Ajouter une tâche', 'url' => '/taches/nouvelle', 'icon' => 'check'],
            ],
        };
    }

    /** Écrans secondaires regroupés dans « Tout le reste ». */
    public static function more(string $role): array
    {
        $groups = [
            'Communication' => [
                ['label' => 'Agenda', 'url' => '/agenda', 'roles' => self::SITE, 'desc' => 'Réunions et événements'],
                ['label' => 'Permanences', 'url' => '/permanences', 'roles' => self::SITE, 'desc' => 'Créneaux, réservations, QR codes'],
                ['label' => 'Annonces', 'url' => '/annonces', 'roles' => self::SITE, 'desc' => 'Panneau d’affichage du site'],
            ],
            'Terrain' => [
                ['label' => 'Mes notes', 'url' => '/notes', 'roles' => self::SITE, 'desc' => 'Carnet personnel'],
                ['label' => 'Baromètre social', 'url' => '/barometre', 'roles' => self::SITE, 'desc' => 'Remontées par secteur'],
                ['label' => 'Bourse', 'url' => '/bourse', 'roles' => self::SITE, 'desc' => 'Cours Stellantis et constructeurs'],
            ],
            'Connaissances' => [
                ['label' => 'Base documentaire', 'url' => '/documents', 'roles' => self::SITE, 'desc' => 'Accords, conventions, notes'],
                ['label' => 'Fil d\'actualité', 'url' => '/actualites', 'roles' => self::SITE, 'desc' => 'Flux RSS suivis'],
                ['label' => 'Assistant IA', 'url' => '/modules/assistant/index.html', 'roles' => self::SITE, 'desc' => 'Accords, tracts, comptes rendus audio (hors ligne)'],
            ],
            'Élections' => [
                ['label' => 'Simulateur CSE', 'url' => '/elections', 'roles' => ['secretaire'], 'desc' => 'Quorum, représentativité, sièges'],
                ['label' => 'Listes électorales', 'url' => '/listes-electorales', 'roles' => ['secretaire'], 'desc' => 'PAP et parité'],
            ],
            'Administration' => [
                ['label' => 'Tableau d’administration', 'url' => '/administration', 'roles' => ['secretaire'], 'desc' => 'Tous les réglages du site'],
                ['label' => 'Trésorerie', 'url' => '/finances/tresorerie', 'roles' => ['secretaire', 'tresorier'], 'desc' => 'Synthèse et soldes'],
                ['label' => 'Stock de timbres', 'url' => '/administration/stock', 'roles' => ['secretaire'], 'desc' => 'Entrées et sorties'],
                ['label' => 'Rapport annuel', 'url' => '/administration/rapport', 'roles' => ['secretaire'], 'desc' => 'Synthèse imprimable'],
                ['label' => 'Utilisateurs', 'url' => '/administration/utilisateurs', 'roles' => ['secretaire'], 'desc' => 'Comptes et fonctions'],
                ['label' => 'Configuration du site', 'url' => '/administration/configuration', 'roles' => ['secretaire'], 'desc' => 'Tarifs, secteurs, tournées'],
                ['label' => 'Rappels aux collecteurs', 'url' => '/administration/rappels', 'roles' => ['secretaire'], 'desc' => 'Courriels des retards'],
                ['label' => 'Import et export', 'url' => '/administration/import', 'roles' => ['secretaire'], 'desc' => 'Fichiers CSV'],
                ['label' => 'Sauvegarde chiffrée', 'url' => '/forceos/sauvegarde', 'roles' => ['secretaire', 'tresorier'], 'desc' => 'Sauvegarder ou restaurer toutes les données'],
                ['label' => 'Tarifs', 'url' => '/administration/configuration', 'roles' => ['tresorier'], 'desc' => 'Prix du timbre et de la carte'],
            ],
            'Mon compte' => [
                ['label' => 'Mon profil', 'url' => '/profil', 'roles' => self::SITE, 'desc' => 'Coordonnées et mot de passe'],
                ['label' => 'Guide d\'utilisation', 'url' => '/guide', 'roles' => self::SITE, 'desc' => 'Aide pas à pas'],
            ],
        ];
        if (service('auth')->isSuperAdmin()) {
            $groups['Plateforme'] = array_map(static fn ($i) => $i + ['roles' => [$role]], array_slice(self::platform(), 1, 5));
        }
        $out = [];
        foreach ($groups as $name => $items) {
            $items = array_values(array_filter($items, static fn ($i) => in_array($role, $i['roles'], true)));
            if ($items) {
                $out[$name] = $items;
            }
        }

        return $out;
    }

    /** Rubrique active à partir du chemin demandé. */
    public static function activeKey(string $path): string
    {
        $seg = explode('/', trim($path, '/'))[0] ?? '';

        if ($seg === 'plateforme') {
            $sub = explode('/', trim($path, '/'))[1] ?? '';

            return $sub === '' ? 'plateforme' : $sub;
        }

        return match ($seg) {
            '' => 'hub',
            'questions' => 'taches',
            'administration', 'notes', 'guide', 'profil', 'notifications', 'elections', 'listes-electorales', 'agenda', 'permanences', 'annonces', 'barometre', 'bourse', 'documents', 'actualites' => 'plus',
            default => $seg,
        };
    }
}
