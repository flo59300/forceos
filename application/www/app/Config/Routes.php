<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Connexion
$routes->get('connexion', 'AuthController::login', ['as' => 'login']);
$routes->post('connexion', 'AuthController::attempt');
$routes->post('deconnexion', 'AuthController::logout', ['filter' => 'auth']);

// Pages publiques des permanences : mêmes adresses que la 2.x (QR codes et liens déjà imprimés ou envoyés)
$routes->match(['GET', 'POST'], 'booking.php', 'Booking::reserve');
$routes->match(['GET', 'POST'], 'verify.php', 'Booking::verify');

// Tâches planifiées appelées par adresse (même adresse que la 2.x, clé fo.cronKey)
$routes->get('cron_(rappels|notifications|push).php', 'Cron::run/$1');

// Application installable et anciennes adresses de la 2.x
$routes->get('manifest.webmanifest', 'Pwa::manifest');
$routes->get('manifest.php', 'Pwa::manifest');
$routes->get('pages/(:segment)', 'Pwa::legacy/$1');

// Pages légales : publiques (liens de la page de connexion)
$routes->get('(mentions-legales|cgu|confidentialite)', 'Guide::legal/$1');
$routes->get('(mentions-legales|cgu|confidentialite).php', 'Guide::legal/$1');

// Profil, notifications, notes personnelles et guide : tout compte connecté
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $r) {
    $r->get('guide', 'Guide::index');
    $r->get('notes', 'Notes::index');
    $r->post('notes', 'Notes::create');
    $r->post('notes/(:num)', 'Notes::update/$1');
    $r->post('notes/(:num)/epingler', 'Notes::pin/$1');
    $r->post('notes/(:num)/supprimer', 'Notes::delete/$1');
    $r->get('profil', 'Profil::index');
    $r->post('profil', 'Profil::update');
    $r->post('profil/mot-de-passe', 'Profil::password');
    $r->post('profil/preferences', 'Profil::preferences');
    $r->post('profil/push/abonnement', 'Profil::subscribe');
    $r->post('profil/push/desabonnement', 'Profil::unsubscribe');
    $r->post('profil/push/essai', 'Profil::test');
    $r->get('notifications', 'Notifications::index');
    $r->get('notifications/(:num)', 'Notifications::open/$1');
    $r->post('notifications/tout-lire', 'Notifications::readAll');
    $r->post('notifications/purger', 'Notifications::clear');
});

// Super Admin (hors mode autonome)
$routes->group('plateforme', ['filter' => ['auth', 'role:super_admin']], static function (RouteCollection $p) {
    $p->get('/', 'Platform::index');
    $p->get('sites', 'Platform::sites');
    $p->post('sites', 'Platform::createSite');
    $p->post('sites/(:num)', 'Platform::updateSite/$1');
    $p->get('comptes', 'Platform::accounts');
    $p->post('comptes', 'Platform::createAccount');
    $p->post('comptes/(:num)/mot-de-passe', 'Platform::resetPassword/$1');
    $p->post('comptes/(:num)/activation', 'Platform::toggleAccount/$1');
    $p->get('reglages', 'Platform::settings');
    $p->post('reglages/essai-courriel', 'Platform::testMail');
    $p->post('reglages/(general|application|courriels|notifications|legal|avance)', 'Platform::saveSettings/$1');
    $p->get('sauvegardes', 'Platform::backups');
    $p->post('sauvegardes/sql', 'Platform::exportSql');
    $p->post('sauvegardes/csv', 'Platform::exportCsv');
    $p->post('sauvegardes/maintenant', 'Platform::backupNow');
    $p->get('sauvegardes/fichier/(:segment)', 'Platform::downloadBackup/$1');
    $p->post('sauvegardes/supprimer', 'Platform::deleteBackup');
    $p->post('sauvegardes/restaurer', 'Platform::importSql');
    $p->get('mises-a-jour', 'Platform::updates');
    $p->post('mises-a-jour', 'Platform::install');
    $p->post('mises-a-jour/migrations', 'Platform::migrate');
    $p->post('mises-a-jour/integrite', 'Platform::integrity');
    $p->post('mises-a-jour/nettoyage', 'Platform::cleanup');
});

// Hub des utilisateurs d'un site
$routes->group('', ['filter' => ['auth', 'role:site']], static function (RouteCollection $r) {
    $r->get('/', 'Hub::index');
    $r->get('plus', 'Hub::more');

    $r->get('taches', 'Taches::index');
    $r->get('taches/nouvelle', 'Taches::new');
    $r->post('taches', 'Taches::create');
    $r->get('taches/(todo|perso)/(:num)/modifier', 'Taches::edit/$1/$2');
    $r->post('taches/(todo|perso)/(:num)', 'Taches::update/$1/$2');
    $r->post('taches/(todo|perso)/(:num)/cocher', 'Taches::toggle/$1/$2');
    $r->post('taches/(todo|perso)/(:num)/supprimer', 'Taches::delete/$1/$2');

    $r->get('questions', 'QuestionsController::index');
    $r->get('questions/nouvelle', 'QuestionsController::new');
    $r->post('questions', 'QuestionsController::create');
    $r->post('questions/(:num)/reponse', 'QuestionsController::respond/$1');
    $r->post('questions/(:num)/statut', 'QuestionsController::status/$1');
    $r->post('questions/(:num)/supprimer', 'QuestionsController::delete/$1');

    // Adhérents et encaissement (secrétaire et collecteur)
    $r->group('adherents', ['filter' => 'role:secretaire,collecteur'], static function (RouteCollection $a) {
        $a->get('/', 'Adherents::index');
        $a->get('nouveau', 'Adherents::new');
        $a->post('/', 'Adherents::create');
        $a->post('groupe', 'Adherents::bulk');
        $a->get('radies', 'Adherents::radiated');
        $a->get('(:num)', 'Adherents::show/$1');
        $a->get('(:num)/modifier', 'Adherents::edit/$1');
        $a->post('(:num)', 'Adherents::update/$1');
        $a->post('(:num)/encaisser', 'Adherents::collect/$1');
        $a->post('(:num)/retirer', 'Adherents::removeStamp/$1');
        $a->post('(:num)/radier', 'Adherents::radiate/$1');
        $a->post('(:num)/transferer', 'Adherents::transfer/$1');
        $a->post('(:num)/reintegrer', 'Adherents::reinstate/$1');
    });

    // Heures de délégation
    $r->get('heures', 'Heures::index');
    $r->post('heures', 'Heures::add');
    $r->post('heures/(:num)/supprimer', 'Heures::delete/$1');
    $r->post('heures/credit', 'Heures::quota');
    $r->post('heures/transfert', 'Heures::transfer');
    $r->post('heures/transfert/(:num)', 'Heures::respond/$1');
    $r->post('heures/report', 'Heures::carry');

    // Messagerie
    $r->get('messages', 'Messagerie::index');
    $r->post('messages/nouveau', 'Messagerie::start');
    $r->get('messages/(:num)', 'Messagerie::thread/$1');
    $r->post('messages/(:num)', 'Messagerie::send/$1');
    $r->post('messages/supprimer/(:num)', 'Messagerie::delete/$1');

    // Finances
    $r->get('finances', 'Finances::index');
    $r->get('finances/reversements', 'Finances::reversements');
    $r->post('finances/reversements', 'Finances::createReversement');
    $r->post('finances/reversements/(:num)/valider', 'Finances::validateReversement/$1');
    $r->group('finances', ['filter' => 'role:secretaire,tresorier'], static function (RouteCollection $f) {
        $f->get('prelevements', 'Finances::prelevements');
        $f->post('prelevements/generer', 'Finances::generate');
        $f->post('prelevements/(:num)/annuler', 'Finances::reject/$1');
        $f->post('prelevements/semestre', 'Finances::validateSemester');
        $f->get('tresorerie', 'Finances::tresorerie');
        $f->get('tresorerie/export', 'Finances::export');
    });

    // Agenda : consultation et export pour tous, gestion pour le secrétaire
    $r->get('agenda', 'AgendaController::index');
    $r->get('agenda/export.ics', 'AgendaController::export');
    $r->get('agenda/(:num).ics', 'AgendaController::exportOne/$1');
    $r->get('agenda/(:num)', 'AgendaController::show/$1');
    $r->group('agenda', ['filter' => 'role:secretaire'], static function (RouteCollection $a) {
        $a->get('nouveau', 'AgendaController::new');
        $a->post('/', 'AgendaController::create');
        $a->post('import', 'AgendaController::import');
        $a->get('(:num)/modifier', 'AgendaController::edit/$1');
        $a->post('(:num)', 'AgendaController::update/$1');
        $a->post('(:num)/dupliquer', 'AgendaController::duplicate/$1');
        $a->post('(:num)/supprimer', 'AgendaController::delete/$1');
    });

    // Annonces (panneau d'affichage)
    $r->get('annonces', 'Annonces::index');
    $r->group('annonces', ['filter' => 'role:secretaire'], static function (RouteCollection $a) {
        $a->post('panneau', 'Annonces::board');
        $a->post('/', 'Annonces::create');
        $a->post('(:num)', 'Annonces::update/$1');
        $a->post('(:num)/affichage', 'Annonces::toggle/$1');
        $a->post('(:num)/supprimer', 'Annonces::delete/$1');
    });

    // Permanences
    $r->get('permanences', 'PermanencesController::index');
    $r->group('permanences', ['filter' => 'role:secretaire'], static function (RouteCollection $p) {
        $p->get('nouvelle', 'PermanencesController::new');
        $p->post('/', 'PermanencesController::create');
        $p->get('(:num)/modifier', 'PermanencesController::edit/$1');
        $p->post('(:num)', 'PermanencesController::update/$1');
        $p->post('(:num)/dupliquer', 'PermanencesController::duplicate/$1');
        $p->post('(:num)/activation', 'PermanencesController::toggle/$1');
        $p->post('(:num)/supprimer', 'PermanencesController::delete/$1');
        $p->post('(:num)/creneaux', 'PermanencesController::addSlot/$1');
        $p->post('creneaux/(:num)/supprimer', 'PermanencesController::deleteSlot/$1');
        $p->post('(:num)/inscriptions', 'PermanencesController::manual/$1');
        $p->post('(:num)/tout-valider', 'PermanencesController::validateAll/$1');
        $p->post('inscriptions/(:num)/(valider|refuser|annuler|presence|note)', 'PermanencesController::booking/$1/$2');
        $p->get('(:num)/export.csv', 'PermanencesController::export/$1');
    });
    $r->get('permanences/(:num)', 'PermanencesController::show/$1');
    $r->get('permanences/(:num)/affiche', 'PermanencesController::poster/$1');

    // Base documentaire
    $r->get('documents', 'Documents::index');
    $r->get('documents/(:num)/telecharger', 'Documents::download/$1');
    $r->group('documents', ['filter' => 'role:secretaire'], static function (RouteCollection $d) {
        $d->post('/', 'Documents::upload');
        $d->post('reindexer', 'Documents::reindex');
        $d->post('(:num)/supprimer', 'Documents::delete/$1');
    });

    // Fil d'actualité
    $r->get('actualites', 'Actualites::index');
    $r->group('actualites', ['filter' => 'role:secretaire'], static function (RouteCollection $a) {
        $a->post('flux', 'Actualites::add');
        $a->post('flux/(:num)/activation', 'Actualites::toggle/$1');
        $a->post('flux/(:num)/supprimer', 'Actualites::delete/$1');
    });

    // Baromètre social
    $r->get('barometre', 'BarometreController::index');
    $r->post('barometre', 'BarometreController::add');
    $r->group('barometre', ['filter' => 'role:secretaire'], static function (RouteCollection $b) {
        $b->post('(:num)/supprimer', 'BarometreController::delete/$1');
    });

    // Bourse
    $r->get('bourse', 'Bourse::index');
    $r->get('bourse/cours', 'Bourse::quotes');
    $r->group('bourse', ['filter' => 'role:secretaire'], static function (RouteCollection $b) {
        $b->post('/', 'Bourse::save');
    });

    // Élections CSE (secrétaire)
    $r->group('elections', ['filter' => 'role:secretaire'], static function (RouteCollection $e) {
        $e->get('/', 'Elections::index');
        $e->get('nouvelle', 'Elections::editor');
        $e->post('calcul', 'Elections::compute');
        $e->post('/', 'Elections::save');
        $e->get('(:num)', 'Elections::editor/$1');
        $e->post('(:num)', 'Elections::save/$1');
        $e->post('(:num)/supprimer', 'Elections::delete/$1');
        $e->get('(:num)/imprimer', 'Elections::print/$1');
    });

    $r->group('listes-electorales', ['filter' => 'role:secretaire'], static function (RouteCollection $l) {
        $l->get('/', 'ListesElectorales::index');
        $l->post('protocole', 'ListesElectorales::savePap');
        $l->post('protocole/supprimer', 'ListesElectorales::deletePap');
        $l->post('generer', 'ListesElectorales::generate');
        $l->get('export/(electeurs|eligibles)', 'ListesElectorales::export/$1');
    });

    // Administration : tarifs pour le trésorier, le reste pour le secrétaire
    $r->group('administration', ['filter' => 'role:secretaire,tresorier'], static function (RouteCollection $a) {
        $a->get('configuration', 'Administration::configuration');
        $a->post('configuration/tarifs', 'Administration::saveTarifs');
    });
    $r->group('administration', ['filter' => 'role:secretaire'], static function (RouteCollection $a) {
        $a->get('/', 'Administration::index');
        $a->get('stock', 'Administration::stock');
        $a->post('stock', 'Administration::addStock');
        $a->get('rapport', 'Administration::rapport');
        $a->post('configuration/site', 'Administration::saveSite');
        $a->post('configuration/secteurs/(ajouter|renommer|supprimer)', 'Administration::secteur/$1');
        $a->post('configuration/(tournees|metiers)/(ajouter|modifier|supprimer)', 'Administration::item/$1/$2');
        $a->post('configuration/annees/(ajouter|courante)', 'Administration::year/$1');

        $a->get('utilisateurs', 'Utilisateurs::index');
        $a->get('utilisateurs/nouveau', 'Utilisateurs::new');
        $a->post('utilisateurs', 'Utilisateurs::create');
        $a->get('utilisateurs/(:num)/modifier', 'Utilisateurs::edit/$1');
        $a->post('utilisateurs/(:num)', 'Utilisateurs::update/$1');
        $a->post('utilisateurs/(:num)/activation', 'Utilisateurs::toggle/$1');
        $a->post('utilisateurs/(:num)/supprimer', 'Utilisateurs::delete/$1');
        $a->post('utilisateurs/(:num)/fonctions', 'Utilisateurs::functions/$1');
        $a->post('utilisateurs/fonctions', 'Utilisateurs::addFunction');
        $a->post('utilisateurs/fonctions/(:num)/supprimer', 'Utilisateurs::deleteFunction/$1');
        $a->post('utilisateurs/import', 'Utilisateurs::import');

        $a->get('rappels', 'Rappels::index');
        $a->post('rappels', 'Rappels::save');
        $a->post('rappels/essai', 'Rappels::test');

        $a->get('import', 'Import::index');
        $a->post('import', 'Import::import');
        $a->get('import/modele', 'Import::model');
        $a->get('import/export/(adherents|ventes|utilisateurs)', 'Import::export/$1');
    });
});

// ForceOS : points d'entrée internes, réservés au processus principal d'Electron
$routes->group('interne', ['filter' => 'interne', 'namespace' => 'App\Controllers\Interne'], static function (RouteCollection $i) {
    $i->post('migrer', 'Maintenance::migrer');
    $i->get('etat', 'Maintenance::etat');
    $i->post('premiere-configuration', 'Maintenance::premiereConfiguration');
    $i->post('instantane/restaurer', 'Maintenance::restaurerInstantane');
    $i->post('kb/synchroniser', 'BaseDocumentaire::synchroniser');
    $i->post('kb/rechercher', 'BaseDocumentaire::rechercher');
    $i->get('sauvegarde/export', 'Sauvegarde::exporter');
    $i->post('restauration/(debut|lot|fin|annuler)', 'Restauration::etape/$1');
});

// ForceOS : sauvegarde chiffrée (secrétaire et trésorier)
$routes->group('forceos', ['filter' => ['auth', 'role:secretaire,tresorier']], static function (RouteCollection $f) {
    $f->get('sauvegarde', 'Forceos::sauvegarde');
    $f->post('ticket', 'Forceos::ticket');
});
