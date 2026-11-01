<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$size   = static fn (int $b) => $b >= 1048576 ? number_format($b / 1048576, 1, ',', ' ') . ' Mo' : max(1, (int) round($b / 1024)) . ' Ko';
?>
<h1 class="text-3xl">Sauvegardes</h1>
<p class="mt-1 text-lg text-muted">Copies de la base, exports et restauration. Les fichiers contiennent des données personnelles : gardez-les en lieu sûr.</p>

<section class="card mt-6 max-w-3xl" aria-labelledby="t-sauv" id="sauvegardes">
    <h2 id="t-sauv" class="section-title">Sauvegardes sur le serveur</h2>
    <p class="mt-0">Une sauvegarde complète est faite avant chaque mise à jour ; les dix plus récentes sont gardées, hors de portée du web.</p>
    <form method="post" action="<?= site_url('plateforme/sauvegardes/maintenant') ?>"><?= csrf_field() ?><button class="btn btn-primary"><?= icon('download') ?> Sauvegarder la base maintenant</button></form>
    <?php if ($backups): ?>
    <ul class="mt-4 grid gap-2" role="list">
        <?php foreach ($backups as $b): ?>
        <li class="flex flex-wrap items-center gap-2 border-t border-line pt-2">
            <span class="min-w-0 flex-1"><span class="block break-all font-bold"><?= esc($b['file']) ?></span><span class="block text-sm text-muted"><?= str_starts_with($b['file'], 'db-') ? 'Base de données' : 'Fichiers de l’application' ?>, <?= esc($size((int) $b['size'])) ?>, le <?= esc(date_fr(date('Y-m-d H:i:s', $b['date']), "d MMMM y 'à' HH:mm")) ?></span></span>
            <a class="btn btn-secondary" href="<?= site_url('plateforme/sauvegardes/fichier/' . $b['file']) ?>"><?= icon('download') ?> Télécharger<span class="sr-only"> <?= esc($b['file']) ?></span></a>
            <form method="post" action="<?= site_url('plateforme/sauvegardes/supprimer') ?>" onsubmit="return confirm('Supprimer cette sauvegarde ?')"><?= csrf_field() ?><input type="hidden" name="file" value="<?= esc($b['file'], 'attr') ?>"><button class="btn btn-ghost text-danger"><?= icon('trash') ?> Supprimer<span class="sr-only"> <?= esc($b['file']) ?></span></button></form>
        </li>
        <?php endforeach ?>
    </ul>
    <?php else: ?><p class="mt-3 text-muted">Aucune sauvegarde pour le moment.</p><?php endif ?>
</section>

<section class="card mt-6 max-w-3xl" aria-labelledby="t-sql" x-data>
    <h2 id="t-sql" class="section-title">Export SQL</h2>
    <p class="mt-0">Structure et données des tables cochées, dans un fichier .sql réimportable.</p>
    <form method="post" action="<?= site_url('plateforme/sauvegardes/sql') ?>" x-ref="f">
        <?= csrf_field() ?>
        <fieldset>
            <legend class="sr-only">Tables à exporter</legend>
            <div class="mb-3 flex flex-wrap gap-2">
                <button type="button" class="btn btn-ghost" @click="$refs.f.querySelectorAll('[name=\'tables[]\']').forEach(c => c.checked = true)">Tout cocher</button>
                <button type="button" class="btn btn-ghost" @click="$refs.f.querySelectorAll('[name=\'tables[]\']').forEach(c => c.checked = false)">Tout décocher</button>
            </div>
            <ul class="grid gap-1 grid-cols-[repeat(auto-fill,minmax(min(100%,15rem),1fr))]" role="list">
                <?php foreach ($tables as $t => $n): ?>
                <li><label class="flex min-h-11 cursor-pointer items-center gap-2"><input type="checkbox" class="size-5" name="tables[]" value="<?= esc($t, 'attr') ?>" checked> <span class="min-w-0 break-all"><?= esc($t) ?> <span class="text-sm text-muted">(<?= esc(plural($n, 'ligne', 'lignes')) ?> environ)</span></span></label></li>
                <?php endforeach ?>
            </ul>
        </fieldset>
        <button class="btn btn-primary mt-3"><?= icon('download') ?> Télécharger le fichier .sql</button>
    </form>
</section>

<section class="card mt-6 max-w-3xl" aria-labelledby="t-csv">
    <h2 id="t-csv" class="section-title">Export CSV</h2>
    <form method="post" action="<?= site_url('plateforme/sauvegardes/csv') ?>" class="flex flex-wrap items-end gap-2">
        <?= csrf_field() ?>
        <div class="min-w-0 flex-1 basis-64"><label class="label" for="csv_table">Contenu</label>
            <select class="input" id="csv_table" name="csv_table">
                <option value="__comptes">Comptes de tous les sites (14 colonnes, avec les fonctions)</option>
                <?php foreach ($tables as $t => $n): ?><option value="<?= esc($t, 'attr') ?>">Table <?= esc($t) ?></option><?php endforeach ?>
            </select></div>
        <button class="btn btn-secondary"><?= icon('download') ?> Télécharger</button>
    </form>
</section>

<section class="card mt-6 max-w-3xl border-danger" aria-labelledby="t-rest" id="restauration">
    <h2 id="t-rest" class="section-title">Restaurer depuis un fichier SQL</h2>
    <p class="mt-0">Exécute un fichier .sql ou .sql.gz sur la base partagée avec la version 2.x : les tables présentes dans le fichier sont <strong>remplacées</strong>. Une sauvegarde complète est faite juste avant.</p>
    <?= view('partials/form_errors') ?>
    <?php if ($importReport): ?>
    <div class="alert <?= $importReport['errorCount'] ? 'alert-error' : 'alert-success' ?> block" role="status">
        <p class="m-0 font-bold"><?= esc(plural($importReport['done'], 'instruction exécutée', 'instructions exécutées')) ?>, <?= esc(plural($importReport['errorCount'], 'erreur', 'erreurs')) ?>. Sauvegarde préalable : <?= esc($importReport['backup']) ?>.</p>
        <?php if ($importReport['errors']): ?><ul class="mt-2 ml-6 list-disc break-words"><?php foreach ($importReport['errors'] as $e): ?><li><?= esc($e) ?></li><?php endforeach ?></ul><?php endif ?>
    </div>
    <?php endif ?>
    <form method="post" action="<?= site_url('plateforme/sauvegardes/restaurer') ?>" enctype="multipart/form-data" novalidate onsubmit="return confirm('Restaurer ce fichier sur la base ? Les tables qu’il contient seront remplacées.')">
        <?= csrf_field() ?>
        <div class="field"><label class="label" for="sql_file">Fichier <span class="font-normal text-muted">(.sql ou .sql.gz, 50 Mo au plus)</span></label><input class="input" type="file" accept=".sql,.gz"<?= field_attrs('sql_file', $errors) ?>><?= field_error('sql_file', $errors) ?></div>
        <div class="field"><label class="label" for="password">Votre mot de passe</label><input class="input" type="password" autocomplete="current-password"<?= field_attrs('password', $errors) ?>><?= field_error('password', $errors) ?></div>
        <div class="field"><label class="label" for="confirm">Recopiez le mot RESTAURER</label><input class="input w-48" type="text" autocomplete="off" autocapitalize="characters"<?= field_attrs('confirm', $errors) ?>><?= field_error('confirm', $errors) ?></div>
        <button class="btn btn-danger">Restaurer</button>
    </form>
</section>
<?= $this->endSection() ?>
