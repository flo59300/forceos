<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$active = array_values(array_filter($feeds, static fn ($f) => (int) $f['active'] === 1));
?>
<h1 class="text-3xl">Fil d’actualité</h1>
<p class="mt-1 text-lg text-muted">Les dernières publications des sites suivis par le syndicat.</p>

<?php if ($canManage): ?>
<nav class="tabs mt-5 max-w-md" aria-label="Rubriques du fil d’actualité">
    <a class="tab" href="<?= site_url('actualites') ?>" <?= $tab === 'articles' ? 'aria-current="page"' : '' ?>>Articles</a>
    <a class="tab" href="<?= site_url('actualites') ?>?onglet=flux" <?= $tab === 'flux' ? 'aria-current="page"' : '' ?>>Flux suivis (<?= count($feeds) ?>)</a>
</nav>
<?php endif ?>

<?php if ($tab === 'articles'): ?>
    <?php if (! $active): ?>
    <div class="card mt-6"><p class="m-0"><?= $canManage ? 'Aucun flux n’est suivi. Ajoutez-en un dans l’onglet « Flux suivis ».' : 'Aucun flux n’est encore suivi par le syndicat.' ?></p></div>
    <?php else: ?>
        <?php if (count($active) > 1): ?>
        <nav class="mt-6 flex flex-wrap gap-2" aria-label="Filtrer par source">
            <a class="btn <?= $only ? 'btn-ghost' : 'btn-primary' ?>" href="<?= site_url('actualites') ?>" <?= $only ? '' : 'aria-current="page"' ?>>Toutes les sources</a>
            <?php foreach ($active as $f): $h = md5($f['url']); ?>
            <a class="btn <?= $only === $h ? 'btn-primary' : 'btn-ghost' ?>" href="<?= site_url('actualites') . '?flux=' . $h ?>" <?= $only === $h ? 'aria-current="page"' : '' ?>><?= esc($f['title']) ?></a>
            <?php endforeach ?>
        </nav>
        <?php endif ?>
        <?php if ($data['errors']): ?>
        <div class="alert alert-info mt-6" role="status"><?= icon('alert', 'size-6 shrink-0') ?><p class="m-0">Momentanément inaccessible : <?= esc(implode(', ', $data['errors'])) ?>.</p></div>
        <?php endif ?>
        <?php if (! $data['items']): ?>
        <div class="card mt-6"><p class="m-0">Aucun article disponible pour le moment.</p></div>
        <?php else: ?>
        <ul class="mt-6 grid gap-3" role="list">
            <?php foreach ($data['items'] as $it): ?>
            <li class="card">
                <h2 class="m-0 text-lg"><?php if ($it['link']): ?><a href="<?= esc($it['link'], 'attr') ?>" rel="noopener noreferrer" target="_blank"><?= esc($it['title'] ?: 'Sans titre') ?><span class="sr-only"> (nouvelle fenêtre)</span></a><?php else: ?><?= esc($it['title'] ?: 'Sans titre') ?><?php endif ?></h2>
                <p class="m-0 mt-1 flex flex-wrap gap-2 text-muted"><span class="badge badge-info"><?= esc($it['source']) ?></span><?php if ($it['ts']): ?><span><?= esc(date_fr(date('Y-m-d H:i:s', $it['ts']), "d MMMM y 'à' HH:mm")) ?></span><?php endif ?></p>
                <?php if ($it['description']): ?><p class="m-0 mt-2"><?= esc($it['description']) ?><?= $it['truncated'] ? '…' : '' ?></p><?php endif ?>
            </li>
            <?php endforeach ?>
        </ul>
        <?php endif ?>
    <?php endif ?>
<?php else: ?>
<section class="card mt-6 max-w-3xl" aria-labelledby="t-ajout">
    <h2 id="t-ajout" class="section-title">Suivre un nouveau flux</h2>
    <?= view('partials/form_errors') ?>
    <form method="post" action="<?= site_url('actualites/flux') ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label class="label" for="feed_title">Nom affiché</label><input class="input" type="text" maxlength="255" required placeholder="FO Confédération"<?= field_attrs('feed_title', $errors) ?> value="<?= esc(old('feed_title') ?? '', 'attr') ?>"><?= field_error('feed_title', $errors) ?></div>
        <div class="field">
            <label class="label" for="feed_url">Adresse du flux RSS ou Atom</label>
            <span class="hint" id="aide-url">Par exemple https://aide.force-ouvriere.org/spip.php?page=backend</span>
            <input class="input" type="url" maxlength="500" required<?= field_attrs('feed_url', $errors, 'aide-url') ?> value="<?= esc(old('feed_url') ?? '', 'attr') ?>"><?= field_error('feed_url', $errors) ?>
        </div>
        <button class="btn btn-primary">Ajouter</button>
    </form>
</section>
<section class="mt-8" aria-labelledby="t-flux">
    <h2 id="t-flux" class="section-title">Flux suivis</h2>
    <?php if (! $feeds): ?>
    <div class="card"><p class="m-0 text-muted">Aucun flux pour le moment.</p></div>
    <?php else: ?>
    <ul class="grid gap-3" role="list">
        <?php foreach ($feeds as $f): ?>
        <li class="card <?= $f['active'] ? '' : 'border-dashed bg-surface-2' ?>">
            <div class="flex flex-wrap items-start gap-2"><h3 class="m-0 min-w-0 flex-1 text-lg"><?= esc($f['title']) ?></h3><span class="badge <?= $f['active'] ? 'badge-success' : 'badge-muted' ?>"><?= $f['active'] ? 'Actif' : 'En pause' ?></span></div>
            <p class="m-0 mt-1 break-all text-muted"><?= esc($f['url']) ?></p>
            <?php if ($f['last_fetched']): ?><p class="m-0 text-sm text-muted">Dernière lecture le <?= esc(date_fr($f['last_fetched'], "d MMMM 'à' HH:mm")) ?></p><?php endif ?>
            <div class="mt-3 flex flex-wrap gap-2">
                <form method="post" action="<?= site_url('actualites/flux/' . $f['id'] . '/activation') ?>"><?= csrf_field() ?><button class="btn btn-secondary"><?= $f['active'] ? 'Mettre en pause' : 'Réactiver' ?><span class="sr-only"> <?= esc($f['title']) ?></span></button></form>
                <form method="post" action="<?= site_url('actualites/flux/' . $f['id'] . '/supprimer') ?>" onsubmit="return confirm('Supprimer ce flux ?')"><?= csrf_field() ?><button class="btn btn-ghost text-danger"><?= icon('trash') ?> Supprimer<span class="sr-only"> <?= esc($f['title']) ?></span></button></form>
            </div>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>
<?php endif ?>
<?= $this->endSection() ?>
