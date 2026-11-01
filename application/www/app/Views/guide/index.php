<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
$all = ['secretaire', 'tresorier', 'collecteur', 'super_admin'];
$s   = ['secretaire'];
$sections = [
    ['demarrer', 'Bien démarrer', $all, [
        ['Ma journée', 'La page d’accueil rassemble ce qui vous attend aujourd’hui : tâches, questions, messages non lus, annonces, rendez-vous des sept prochains jours et demandes de permanence. Les actions rapides en haut mènent aux gestes les plus fréquents.'],
        ['Se déplacer', 'Sur ordinateur, le menu de gauche donne les grandes rubriques ; « Tout le reste » regroupe les autres écrans. Sur téléphone, la barre du bas propose l’accueil, les adhérents ou les finances, le bouton « Saisir », les messages et « Plus ». Chaque page commence par un lien d’évitement pour aller droit au contenu au clavier.'],
        ['Installer l’application', 'Sur Android, ouvrez le menu du navigateur et choisissez « Installer l’application ». Sur iPhone, dans Safari, touchez Partager puis « Sur l’écran d’accueil ». Les pages déjà consultées restent lisibles sans réseau ; une bannière signale la perte de connexion.'],
        ['Notifications', 'La cloche en haut affiche les notifications du site. Dans « Mon profil », vous pouvez activer les notifications sur ce téléphone et choisir lesquelles recevoir (messages, heures de délégation, agenda).'],
    ]],
    ['adherents', 'Adhérents et timbres', ['secretaire', 'collecteur'], [
        ['Trouver un adhérent', 'La recherche accepte le nom, le prénom ou le matricule. Les filtres par tournée, secteur et état de cotisation se combinent.'],
        ['Encaisser', 'Ouvrez la fiche, puis la carte de timbres : choisissez les mois, le mode de paiement, et validez. Le reçu et le suivi des reversements se mettent à jour seuls.'],
        ['Radier', 'La radiation garde l’historique et demande un motif. Un adhérent radié peut être réintégré depuis la liste des radiés.'],
    ]],
    ['finances', 'Finances', ['secretaire', 'tresorier', 'collecteur'], [
        ['Reversements', 'Le collecteur remet ses encaissements ; le trésorier ou le secrétaire les valide. Les soldes de chacun sont visibles dans la trésorerie.'],
        ['Prélèvements', 'Chaque mois, générez les prélèvements des adhérents concernés. Tous les six mois, le contrôle semestriel est obligatoire avant de continuer.'],
        ['Export comptable', 'La trésorerie s’exporte en CSV pour le tableur ou l’expert-comptable.'],
    ]],
    ['terrain', 'Travail de terrain', $all, [
        ['Heures de délégation', 'Saisissez vos heures au fil de l’eau ; la jauge indique le quota consommé sur le mois. Le secrétaire voit la synthèse de l’équipe.'],
        ['Tâches et questions', 'Les tâches assignées apparaissent sur « Ma journée ». Une question posée au secrétaire reçoit sa réponse dans le même fil.'],
        ['Mes notes', 'Un carnet personnel, classé par catégorie (terrain, suivi d’un salarié, personnel, réunion). Les notes épinglées restent en tête. Personne d’autre ne les voit.'],
        ['Baromètre social', 'Notez une remontée du terrain par secteur et par thème ; le tableau de bord montre les tendances sur un, trois, six ou douze mois.'],
    ]],
    ['communication', 'Communication', $all, [
        ['Messages', 'La messagerie interne échange avec un collègue ou tout le site. Une pastille signale les messages non lus.'],
        ['Agenda et permanences', 'L’agenda s’importe et s’exporte au format ICS. Les permanences publient des créneaux réservables par les salariés, par lien ou par QR code à afficher.'],
        ['Annonces', 'Le secrétaire publie sur le panneau d’affichage ; chaque compte est prévenu par une notification.'],
        ['Base documentaire et actualités', 'Recherchez dans les accords et notes déposés, et suivez les flux d’actualité choisis par le secrétaire.'],
    ]],
    ['elections', 'Élections professionnelles', $s, [
        ['Simulateur CSE', 'Saisissez collèges, inscrits, votants, blancs et nuls, puis les voix de chaque liste : le quorum, la représentativité et la répartition des sièges se calculent à chaque frappe. Le quorum se juge sur les suffrages valablement exprimés, les sièges au quotient puis à la plus forte moyenne, et la représentativité d’un syndicat catégoriel sur ses seuls collèges.'],
        ['Listes électorales', 'Enregistrez le protocole d’accord préélectoral, générez les listes d’électeurs et d’éligibles, contrôlez la parité par collège et exportez en CSV. Une fiche sans date d’entrée ou de naissance reste sur la liste avec la mention « À vérifier ».'],
    ]],
    ['administration', 'Administration du site', $s, [
        ['Configuration', 'Tarifs, secteurs, tournées, métiers et années de cotisation. Le changement d’année se fait une fois par an.'],
        ['Utilisateurs', 'Créez les comptes, choisissez le rôle et les fonctions syndicales. Un compte désactivé ne peut plus se connecter mais garde son historique.'],
        ['Rappels aux collecteurs', 'Activez l’envoi automatique de la liste des adhérents en retard à chaque collecteur, et réglez la fréquence.'],
        ['Import et export', 'Importez des adhérents depuis un tableur (modèle fourni) et exportez adhérents, ventes et utilisateurs.'],
    ]],
    ['plateforme', 'Espace Super Admin', ['super_admin'], [
        ['Sites', 'Créez un site : un code, l’année en cours et deux comptes (secrétaire et trésorier) dont les mots de passe s’affichent une seule fois.'],
        ['Réglages', 'Nom de l’application, apparence de l’application installée, envoi des courriels, notifications, textes légaux, maintenance et mode autonome.'],
        ['Sauvegardes et mises à jour', 'Exportez la base en SQL ou en CSV. Une mise à jour se dépose en zip : le fichier .env est conservé et les migrations s’appliquent seules.'],
    ]],
];
$sections = array_values(array_filter($sections, static fn ($x) => in_array($role, $x[2], true)));
?>
<h1 class="text-3xl">Guide d’utilisation</h1>
<p class="mt-1 text-lg text-muted">Les écrans que vous utilisez, expliqués en quelques lignes.</p>

<nav class="card mt-6 max-w-3xl" aria-labelledby="t-sommaire">
    <h2 id="t-sommaire" class="section-title">Sommaire</h2>
    <ol class="ml-6 list-decimal">
        <?php foreach ($sections as [$id, $h]): ?><li><a href="#<?= $id ?>"><?= esc($h) ?></a></li><?php endforeach ?>
    </ol>
</nav>

<?php foreach ($sections as [$id, $h, , $items]): ?>
<section id="<?= $id ?>" class="card mt-6 max-w-3xl scroll-mt-24" aria-labelledby="t-<?= $id ?>">
    <h2 id="t-<?= $id ?>" class="section-title"><?= esc($h) ?></h2>
    <?php foreach ($items as [$t, $p]): ?>
    <h3 class="mt-4 text-lg first-of-type:mt-0"><?= esc($t) ?></h3>
    <p class="mt-1"><?= esc($p) ?></p>
    <?php endforeach ?>
</section>
<?php endforeach ?>

<p class="mt-8 max-w-3xl text-muted">Une question qui n’est pas traitée ici ? Posez-la à votre secrétaire depuis « Tâches et questions ». Voir aussi les <a href="<?= site_url('mentions-legales') ?>">mentions légales</a>, les <a href="<?= site_url('cgu') ?>">conditions d’utilisation</a> et la <a href="<?= site_url('confidentialite') ?>">politique de confidentialité</a>.</p>
<?= $this->endSection() ?>
