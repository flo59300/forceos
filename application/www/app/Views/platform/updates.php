<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
$label   = ['applied' => ['Appliquée', 'badge-success'], 'baseline' => ['Déjà présente', 'badge-neutral'], 'pending' => ['En attente', 'badge-warning'], 'failed' => ['Échec', 'badge-danger']];
$pending = array_filter($status, static fn ($m) => in_array($m['state'], ['pending', 'failed'], true));
$reqOk   = ! in_array(false, array_column($reqs, 1), true);
?>
<h1 class="text-3xl">Mises à jour</h1>
<p class="mt-1 text-lg text-muted">Version installée : <strong><?= esc(FO_VERSION) ?></strong><?= $build ? ', paquet du ' . esc($build) : '' ?> · base <?= esc($dbVersion) ?> · PHP <?= esc(PHP_VERSION) ?>.</p>

<?php if ($report): ?>
<section class="card mt-6 max-w-3xl <?= $report['ok'] ? 'border-success' : 'border-danger' ?>" aria-labelledby="t-rapport" id="rapport" tabindex="-1">
    <h2 id="t-rapport" class="section-title"><?= $report['ok'] ? 'Mise à jour réussie' : 'Mise à jour interrompue' ?></h2>
    <?php if ($report['error']): ?><p class="mt-0 font-bold text-danger"><?= esc($report['error']) ?></p><?php endif ?>
    <?php if ($report['version']): ?>
    <p class="mt-0">Version <?= esc($report['version']) ?> : <?= esc(plural($report['added'], 'fichier ajouté', 'fichiers ajoutés')) ?>, <?= esc(plural($report['updated'], 'remplacé', 'remplacés')) ?>, <?= esc(plural($report['removed'], 'retiré', 'retirés')) ?>, <?= esc(plural($report['unchanged'], 'inchangé', 'inchangés')) ?>. Le fichier .env et les données (writable/) n’ont pas été touchés.</p>
    <?php endif ?>
    <?php if ($report['fileErrors']): ?><ul class="ml-6 list-disc break-words"><?php foreach ($report['fileErrors'] as $e): ?><li><?= esc($e) ?></li><?php endforeach ?></ul><?php endif ?>
    <?php if ($report['migrations']): ?><p>Migrations : <?php foreach ($report['migrations'] as $m): ?><span class="badge <?= $m['ok'] ? 'badge-success' : 'badge-danger' ?>"><?= esc($m['file']) ?></span> <?php endforeach ?></p><?php endif ?>
    <?php if ($report['backups']): ?><p class="mb-0 text-sm text-muted">Sauvegardes faites avant l’opération : <?= esc(implode(', ', $report['backups'])) ?> (<a href="<?= site_url('plateforme/sauvegardes') ?>#sauvegardes">voir</a>).</p><?php endif ?>
</section>
<?php endif ?>

<?php if ($installer): ?>
<div class="alert alert-error mt-6" role="alert"><?= icon('alert', 'size-6 shrink-0') ?>
    <div><p class="m-0 font-bold">Le fichier d’installation est encore sur le serveur.</p>
    <form method="post" action="<?= site_url('plateforme/mises-a-jour/nettoyage') ?>" class="mt-2"><?= csrf_field() ?><button class="btn btn-secondary">Supprimer installer.php</button></form></div>
</div>
<?php endif ?>

<section class="card mt-6 max-w-3xl" aria-labelledby="t-paquet">
    <h2 id="t-paquet" class="section-title">Installer une nouvelle version</h2>
    <p class="mt-0">Déposez le zip de la nouvelle version tel que vous l’avez reçu. Avant toute écriture, son contenu est contrôlé fichier par fichier ; la base et les fichiers sont sauvegardés ; une page de maintenance s’affiche pendant l’opération. Le fichier .env, les documents déposés et les journaux sont conservés.</p>
    <ul class="mb-4 grid gap-1" role="list">
        <?php foreach ($reqs as [$l, $ok]): ?><li class="flex items-center gap-2"><?= icon($ok ? 'check' : 'alert', 'size-5 shrink-0 ' . ($ok ? 'text-success' : 'text-danger')) ?><span><?= esc($l) ?><span class="sr-only"> : <?= $ok ? 'oui' : 'non' ?></span></span></li><?php endforeach ?>
    </ul>
    <form method="post" action="<?= site_url('plateforme/mises-a-jour') ?>" enctype="multipart/form-data" onsubmit="this.querySelector('button').disabled = true; this.querySelector('[role=status]').hidden = false">
        <?= csrf_field() ?>
        <div class="field"><label class="label" for="package">Paquet zip <span class="font-normal text-muted">(limite du serveur : <?= esc($maxUpload) ?>)</span></label><input class="input" type="file" id="package" name="package" accept=".zip,application/zip" required></div>
        <label class="flex min-h-11 cursor-pointer items-center gap-2"><input type="checkbox" class="size-5" name="backup_db" value="1" checked> Sauvegarder la base avant</label>
        <label class="mb-4 flex min-h-11 cursor-pointer items-center gap-2"><input type="checkbox" class="size-5" name="backup_files" value="1" checked> Sauvegarder les fichiers avant</label>
        <button class="btn btn-primary" <?= $reqOk ? '' : 'disabled' ?>>Installer la mise à jour</button>
        <p role="status" class="mt-3 font-bold" hidden>Installation en cours, ne fermez pas la page…</p>
    </form>
</section>

<section class="card mt-6 max-w-3xl" aria-labelledby="t-mig" id="migrations">
    <h2 id="t-mig" class="section-title">Migrations de la base</h2>
    <p class="mt-0">Changements de structure propres à la version 3, appliqués une fois chacun et notés dans la table partagée avec la version 2.x.</p>
    <?php if (! $status): ?><p class="text-muted">Aucune migration dans cette version.</p><?php else: ?>
    <ul class="grid gap-2" role="list">
        <?php foreach ($status as $m): [$l, $b] = $label[$m['state']]; ?>
        <li class="border-t border-line pt-2"><span class="badge <?= $b ?>"><?= $l ?></span> <span class="break-all font-mono text-sm"><?= esc($m['file']) ?></span>
            <?php if ($m['row'] && $m['row']['error']): ?><p class="m-0 break-words text-sm text-danger"><?= esc($m['row']['error']) ?></p><?php endif ?>
            <?php if ($m['row'] && $m['row']['applied_at'] && $m['state'] === 'applied'): ?><span class="text-sm text-muted">le <?= esc(date_fr($m['row']['applied_at'], 'd MMM y')) ?></span><?php endif ?></li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
    <?php if ($pending): ?>
    <form method="post" action="<?= site_url('plateforme/mises-a-jour/migrations') ?>" class="mt-4"><?= csrf_field() ?><button class="btn btn-primary">Appliquer <?= esc(plural(count($pending), 'migration', 'migrations')) ?></button></form>
    <?php endif ?>
    <p class="mb-0 mt-4 text-sm text-muted">En ligne de commande : <code>php spark fo:migrer</code>.</p>
</section>

<section class="card mt-6 max-w-3xl" aria-labelledby="t-int" id="integrite">
    <h2 id="t-int" class="section-title">Intégrité des fichiers</h2>
    <p class="mt-0">Compare les fichiers installés aux empreintes du paquet : utile après une copie par FTP ou une modification manuelle.</p>
    <?php if ($integrity): ?>
        <?php if (! $integrity['ok']): ?><p class="font-bold text-danger"><?= esc($integrity['error']) ?></p>
        <?php elseif (! $integrity['missing'] && ! $integrity['modified']): ?><p class="font-bold text-success"><?= esc(plural($integrity['total'], 'fichier vérifié', 'fichiers vérifiés')) ?> : tout est conforme.</p>
        <?php else: ?>
        <p class="font-bold"><?= esc(plural(count($integrity['missing']), 'fichier manquant', 'fichiers manquants')) ?>, <?= esc(plural(count($integrity['modified']), 'modifié', 'modifiés')) ?> sur <?= $integrity['total'] ?>. Réinstallez le paquet pour corriger.</p>
        <ul class="ml-6 list-disc break-all text-sm"><?php foreach (array_slice(array_merge(array_map(static fn ($f) => 'manquant : ' . $f, $integrity['missing']), array_map(static fn ($f) => 'modifié : ' . $f, $integrity['modified'])), 0, 50) as $l): ?><li><?= esc($l) ?></li><?php endforeach ?></ul>
        <?php endif ?>
    <?php endif ?>
    <form method="post" action="<?= site_url('plateforme/mises-a-jour/integrite') ?>"><?= csrf_field() ?><button class="btn btn-secondary">Vérifier maintenant</button></form>
</section>
<?= $this->endSection() ?>
