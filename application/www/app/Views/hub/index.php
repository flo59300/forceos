<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php $tones = ['danger' => 'badge-danger', 'warning' => 'badge-warning', 'success' => 'badge-success', 'info' => 'badge-info', 'neutral' => 'badge-neutral']; ?>

<h1 class="text-3xl">Bonjour <?= esc(session('user_first')) ?></h1>
<p class="mt-1 text-lg text-muted"><?= esc(ucfirst($today)) ?> · <?= esc(session('site_name')) ?></p>

<section class="mt-8" aria-labelledby="titre-a-faire">
    <h2 id="titre-a-faire" class="section-title">À faire maintenant</h2>
    <?php if (! $todo): ?>
    <p class="card flex items-center gap-3 text-lg"><?= icon('check', 'size-7 text-success shrink-0') ?> Rien d’urgent pour le moment. Bonne journée.</p>
    <?php else: ?>
    <ul class="grid gap-3" role="list">
        <?php foreach ($todo as $t): ?>
        <li>
            <a href="<?= site_url($t['url']) ?>" class="todo-card" data-tone="<?= esc($t['tone'], 'attr') ?>">
                <span class="min-w-0 flex-1">
                    <span class="badge <?= $tones[$t['tone']] ?>"><?= esc($t['tag']) ?></span>
                    <span class="mt-1 block text-lg font-bold"><?= esc($t['title']) ?></span>
                    <span class="block text-muted"><?= esc($t['meta']) ?></span>
                </span>
                <?= icon('arrow', 'size-6 shrink-0 self-center text-brand') ?>
            </a>
        </li>
        <?php endforeach ?>
    </ul>
    <?php if ($more): ?><p class="mt-3"><a href="<?= site_url('taches') ?>">Voir les <?= esc(plural($more, 'autre élément')) ?></a></p><?php endif ?>
    <?php endif ?>
</section>

<?php if ($news): ?>
<section class="mt-10" aria-labelledby="titre-annonces">
    <div class="flex flex-wrap items-baseline justify-between gap-2"><h2 id="titre-annonces" class="section-title">Annonces</h2><a href="<?= site_url('annonces') ?>">Toutes les annonces</a></div>
    <ul class="grid gap-3" role="list">
        <?php foreach ($news as $n): ?>
        <li class="card border-l-4 <?= $n['priority'] === 'urgente' ? 'border-danger' : ($n['priority'] === 'importante' ? 'border-warning' : 'border-brand') ?>">
            <h3 class="m-0 flex flex-wrap items-center gap-2 text-lg"><?= esc($n['title']) ?><?php if ($n['priority'] !== 'normale'): ?> <span class="badge <?= $n['priority'] === 'urgente' ? 'badge-danger' : 'badge-warning' ?>"><?= $n['priority'] === 'urgente' ? 'Urgente' : 'Importante' ?></span><?php endif ?></h3>
            <p class="m-0 mt-1 whitespace-pre-line"><?= esc(mb_strimwidth($n['body'], 0, 240, '…')) ?></p>
            <?php if (mb_strlen($n['body']) > 240): ?><a href="<?= site_url('annonces') ?>#annonce-<?= $n['id'] ?>">Lire la suite<span class="sr-only"> de « <?= esc($n['title']) ?> »</span></a><?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
</section>
<?php endif ?>

<?php if ($agenda): ?>
<section class="mt-10" aria-labelledby="titre-semaine">
    <div class="flex flex-wrap items-baseline justify-between gap-2"><h2 id="titre-semaine" class="section-title">Ces sept prochains jours</h2><a href="<?= site_url('agenda') ?>">Ouvrir l’agenda</a></div>
    <ul class="grid gap-2" role="list">
        <?php foreach ($agenda as $a): ?>
        <li><a href="<?= site_url($a['url']) ?>" class="todo-card">
            <span class="w-32 shrink-0 font-bold text-brand"><?= esc(ucfirst(due_label($a['date']))) ?><?= $a['time'] ? '<br>' . esc($a['time']) : '' ?></span>
            <span class="min-w-0 flex-1"><span class="block text-lg font-bold"><?= esc($a['title']) ?></span><?php if ($a['meta']): ?><span class="block text-muted"><?= esc($a['meta']) ?></span><?php endif ?></span>
            <?= icon('arrow', 'size-5 shrink-0 self-center text-brand') ?>
        </a></li>
        <?php endforeach ?>
    </ul>
</section>
<?php endif ?>

<section class="mt-10" aria-labelledby="titre-actions">
    <h2 id="titre-actions" class="section-title">Actions rapides</h2>
    <ul class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,15rem),1fr))]" role="list">
        <?php foreach ($quick as $i => $q): ?>
        <li><a href="<?= site_url($q['url']) ?>" class="quick-action <?= $i === 0 ? 'quick-action-main' : '' ?>"><?= icon($q['icon'], 'size-7 shrink-0') ?><span class="text-lg"><?= esc($q['label']) ?></span></a></li>
        <?php endforeach ?>
    </ul>
</section>

<?php if ($figures): ?>
<section class="mt-10" aria-labelledby="titre-chiffres">
    <h2 id="titre-chiffres" class="section-title">Mes chiffres</h2>
    <ul class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,11rem),1fr))]" role="list">
        <?php foreach ($figures as $f): ?>
        <li class="card">
            <p class="m-0 text-3xl font-bold break-words <?= ($f['tone'] ?? null) === 'danger' ? 'text-danger' : (($f['tone'] ?? null) === 'warning' ? 'text-warning' : '') ?>"><?= esc($f['value']) ?></p>
            <p class="m-0 text-muted"><?= esc($f['label']) ?></p>
            <?php if ($f['pct'] !== null): ?>
            <div class="meter" role="img" aria-label="<?= esc($f['pct'], 'attr') ?> pour cent"><span style="width: <?= (int) $f['pct'] ?>%"></span></div>
            <?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
</section>
<?php endif ?>
<?= $this->endSection() ?>
