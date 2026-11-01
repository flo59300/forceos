<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?php
$rich = static fn (string $t) => str_contains($t, '<') ? safe_html($t) : nl2br(esc($t));
$sections = [
    'cgu' => [
        ['Objet', 'Les présentes conditions définissent l’accès et l’utilisation de l’application ' . $appName . ', outil interne de gestion des adhésions de la section syndicale Force Ouvrière.'],
        ['Accès à l’application', 'L’accès est réservé aux personnes disposant d’identifiants fournis par le secrétaire de section. Chacun est responsable de la confidentialité de ses identifiants.'],
        ['Utilisation', 'L’application sert exclusivement à la gestion des adhésions et à l’activité syndicale. Toute utilisation à des fins personnelles, commerciales ou contraires aux intérêts de la section est interdite.'],
        ['Responsabilités de l’utilisateur', 'L’utilisateur s’engage à saisir des informations exactes et à jour, à ne pas divulguer les données des adhérents à des personnes non autorisées, et à signaler toute anomalie ou faille de sécurité au responsable technique.'],
        ['Rôles et permissions', 'L’accès aux fonctions dépend du rôle attribué à chaque compte. Les rôles sont définis et gérés par les secrétaires de section.'],
        ['Disponibilité et responsabilité', 'L’application est fournie en l’état. La section ne garantit pas un accès ininterrompu et peut effectuer des maintenances sans préavis.'],
        ['Modification et contact', 'Ces conditions peuvent être modifiées à tout moment. Pour toute question, contactez le secrétaire de section Force Ouvrière.'],
    ],
    'mentions-legales' => [
        ['Éditeur', 'Force Ouvrière, section syndicale Stellantis. Application de gestion interne des adhésions. Responsable de la publication : le secrétaire de section.'],
        ['Hébergement', 'Application hébergée par le responsable technique de la section. Contact : par les coordonnées internes de la section FO.'],
        ['Propriété intellectuelle', 'Le contenu de cette application (textes, images, structure) est la propriété de la section syndicale Force Ouvrière et protégé par le droit de la propriété intellectuelle.'],
        ['Données personnelles', 'Les données collectées servent exclusivement à la gestion des adhésions syndicales et ne sont jamais transmises à des tiers. Conformément au RGPD, chaque adhérent dispose d’un droit d’accès, de rectification et de suppression de ses données, à exercer auprès du secrétaire de section.'],
    ],
    'confidentialite' => [
        ['Responsable du traitement', 'La section syndicale Force Ouvrière de Stellantis est responsable du traitement des données personnelles collectées par cette application.'],
        ['Données collectées', 'Nom, prénom, matricule, adresse e-mail, téléphone, secteur, métier, tournée, informations de cotisation (timbres et cartes, montants, dates de paiement).'],
        ['Finalité du traitement', 'La gestion des adhésions, le suivi des cotisations, les rapports internes et la communication avec les adhérents.'],
        ['Base légale', 'L’intérêt légitime de l’organisation syndicale dans la gestion de ses adhérents (article 6.1.f du RGPD) et les obligations liées à l’activité syndicale.'],
        ['Durée de conservation', 'Les données des adhérents actifs sont conservées pendant toute la durée de leur adhésion ; celles des adhérents radiés, trois ans au plus après la radiation, sauf obligation légale contraire.'],
        ['Destinataires', 'Seuls les responsables habilités de la section (secrétaires, trésoriers, collecteurs) y accèdent, selon leur rôle. Aucune donnée n’est transmise à des tiers.'],
        ['Vos droits', 'Vous disposez des droits d’accès, de rectification, d’effacement, de limitation et de portabilité. Pour les exercer, contactez le secrétaire de section.'],
        ['Cookies', 'L’application n’utilise que des cookies de session indispensables (connexion, protection des formulaires). Aucun cookie publicitaire ni de mesure d’audience.'],
        ['Sécurité', 'Mots de passe chiffrés, accès limité selon le rôle, protection contre les injections et le détournement de formulaires.'],
    ],
][$pageKey];
?>
<h1 class="text-3xl"><?= esc($title) ?></h1>
<nav aria-label="Pages légales" class="mt-3">
    <ul class="flex flex-wrap gap-x-4 gap-y-1" role="list">
        <?php foreach (['mentions-legales' => 'Mentions légales', 'cgu' => 'Conditions d’utilisation', 'confidentialite' => 'Confidentialité'] as $k => $l): ?>
        <li><a href="<?= site_url($k) ?>" <?= $k === $pageKey ? 'aria-current="page" class="font-bold"' : '' ?>><?= esc($l) ?></a></li>
        <?php endforeach ?>
    </ul>
</nav>
<div class="card mt-6 max-w-3xl">
    <?php if ($custom !== ''): ?><div class="prose-legal"><?= $rich($custom) ?></div><?php endif ?>
    <?php foreach ($sections as [$h, $p]): ?>
    <h2 class="mt-5 text-xl first:mt-0"><?= esc($h) ?></h2>
    <p class="mt-1"><?= esc($p) ?></p>
    <?php endforeach ?>
    <?php if ($rgpd !== ''): ?><h2 class="mt-5 text-xl">Politique RGPD</h2><div class="prose-legal"><?= $rich($rgpd) ?></div><?php endif ?>
</div>
<?php if ($layout === 'layouts/public'): ?><p class="mt-6"><a href="<?= site_url('connexion') ?>">Retour à la connexion</a></p><?php endif ?>
<?= $this->endSection() ?>
