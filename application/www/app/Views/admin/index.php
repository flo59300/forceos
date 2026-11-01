<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<h1 class="text-3xl">Administration</h1>
<p class="mt-1 text-lg text-muted">Les réglages et les outils de pilotage du site.</p>
<ul class="mt-6 grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,18rem),1fr))]" role="list">
    <?php foreach ([
        ['/finances/tresorerie', 'wallet', 'Trésorerie', 'Recettes, reversements, soldes, export comptable'],
        ['/finances/prelevements', 'ticket', 'Prélèvements', 'Génération mensuelle et contrôle semestriel'],
        ['/administration/utilisateurs', 'users', 'Utilisateurs', 'Comptes, rôles et fonctions syndicales'],
        ['/administration/configuration', 'edit', 'Configuration', 'Site, tarifs, secteurs, tournées, métiers, années'],
        ['/administration/stock', 'grid', 'Stock de timbres', 'Entrées, sorties et niveau actuel'],
        ['/administration/rapport', 'check', 'Rapport annuel', 'Synthèse à imprimer'],
        ['/administration/rappels', 'bell', 'Rappels aux collecteurs', 'Courriels automatiques des retards de cotisation'],
        ['/administration/import', 'download', 'Import et export', 'Fichiers CSV des adhérents, ventes et comptes'],
    ] as [$url, $ic, $label, $desc]): ?>
    <li><a href="<?= site_url($url) ?>" class="todo-card"><?= icon($ic, 'size-7 shrink-0 text-brand') ?><span class="min-w-0 flex-1"><span class="block text-lg font-bold"><?= esc($label) ?></span><span class="block text-muted"><?= esc($desc) ?></span></span></a></li>
    <?php endforeach ?>
</ul>
<?= $this->endSection() ?>
