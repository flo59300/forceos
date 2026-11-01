<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<h1 class="text-3xl">Tout le reste</h1>
<p class="mt-1 text-lg text-muted">Les écrans moins fréquents, regroupés par thème.</p>
<?php $base = legacy_url(''); ?>
<?php if (! $base): ?>
<div class="alert alert-info mt-6" role="status"><p class="m-0">Ces écrans seront intégrés au hub au fil de la migration. L’adresse de la version actuelle n’est pas configurée.</p></div>
<?php endif ?>
<?php foreach ($groups as $name => $items): ?>
<section class="mt-8" aria-labelledby="g-<?= esc(url_title($name, '-', true), 'attr') ?>">
    <h2 id="g-<?= esc(url_title($name, '-', true), 'attr') ?>" class="section-title"><?= esc($name) ?></h2>
    <ul class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,18rem),1fr))]" role="list">
        <?php foreach ($items as $it): ?>
        <li>
            <?php if (! empty($it['url'])): ?>
            <a href="<?= site_url($it['url']) ?>" class="todo-card">
                <span class="min-w-0 flex-1"><span class="block text-lg font-bold"><?= esc($it['label']) ?></span><span class="block text-muted"><?= esc($it['desc']) ?></span></span>
                <?= icon('arrow', 'size-5 shrink-0 self-center text-brand') ?>
            </a>
            <?php elseif ($base): ?>
            <a href="<?= esc(legacy_url($it['legacy']), 'attr') ?>" class="todo-card">
                <span class="min-w-0 flex-1"><span class="block text-lg font-bold"><?= esc($it['label']) ?></span><span class="block text-muted"><?= esc($it['desc']) ?></span></span>
                <?= icon('external', 'size-5 shrink-0 self-center text-brand') ?><span class="sr-only">(version actuelle)</span>
            </a>
            <?php else: ?>
            <div class="todo-card"><span class="min-w-0 flex-1"><span class="block text-lg font-bold"><?= esc($it['label']) ?></span><span class="block text-muted"><?= esc($it['desc']) ?></span></span></div>
            <?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
</section>
<?php endforeach ?>
<?= $this->endSection() ?>
