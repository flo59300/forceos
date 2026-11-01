<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
$back = site_url('agenda') . '?mois=' . substr($e['event_date'], 0, 7) . '#jour-' . $e['event_date'];
$participants = array_filter(array_map('trim', explode(',', (string) $e['participants'])));
?>
<p><a href="<?= $back ?>">Retour à l’agenda</a></p>
<div class="flex flex-wrap items-center gap-3">
    <h1 class="text-3xl"><?= esc($e['title']) ?></h1>
    <?= view('agenda/_type', ['type' => $e['event_type']]) ?>
</div>
<div class="card mt-6 max-w-3xl">
    <dl class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(min(100%,14rem),1fr))]">
        <div><dt class="text-muted">Date</dt><dd class="m-0 text-lg font-bold"><?= esc(ucfirst(date_fr($e['event_date'], 'EEEE d MMMM y'))) ?></dd></div>
        <div><dt class="text-muted">Horaire</dt><dd class="m-0 text-lg font-bold"><?= $e['start_time'] ? esc(substr($e['start_time'], 0, 5) . ($e['end_time'] ? ' à ' . substr($e['end_time'], 0, 5) : '')) : 'Toute la journée' ?></dd></div>
        <?php if ($e['location']): ?><div><dt class="text-muted">Lieu</dt><dd class="m-0 text-lg font-bold"><?= esc($e['location']) ?></dd></div><?php endif ?>
    </dl>
    <?php if ($e['description']): ?>
    <h2 class="mt-6 text-xl">Détails</h2>
    <p class="mt-2 whitespace-pre-line"><?= esc($e['description']) ?></p>
    <?php endif ?>
    <?php if ($participants): ?>
    <h2 class="mt-6 text-xl">Participants</h2>
    <ul class="mt-2 flex flex-wrap gap-2" role="list"><?php foreach ($participants as $p): ?><li class="badge badge-neutral"><?= esc($p) ?></li><?php endforeach ?></ul>
    <?php endif ?>
    <p class="mt-6 text-muted">Ajouté par <?= esc($e['creator_name'] ?: 'un ancien compte') ?> le <?= esc(date_fr($e['created_at'], 'd MMMM y')) ?>.</p>
    <div class="mt-4 flex flex-wrap gap-2">
        <a href="<?= site_url('agenda/' . $e['id'] . '.ics') ?>" class="btn btn-secondary">Ajouter à mon agenda <span class="sr-only">(fichier iCalendar)</span></a>
        <?php if ($canEdit): ?>
        <a href="<?= site_url('agenda/' . $e['id'] . '/modifier') ?>" class="btn btn-secondary"><?= icon('edit') ?> Modifier</a>
        <form method="post" action="<?= site_url('agenda/' . $e['id'] . '/dupliquer') ?>"><?= csrf_field() ?><button class="btn btn-ghost">Dupliquer</button></form>
        <form method="post" action="<?= site_url('agenda/' . $e['id'] . '/supprimer') ?>" onsubmit="return confirm('Supprimer cet événement ?')"><?= csrf_field() ?><button class="btn btn-danger"><?= icon('trash') ?> Supprimer</button></form>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
