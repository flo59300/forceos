<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php $unread = count(array_filter($items, static fn ($n) => ! (int) $n['is_read'])); ?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-3xl">Notifications</h1>
        <p class="mt-1 text-lg text-muted"><?= $total ? esc(plural($total, 'notification')) : 'Aucune notification' ?><?= $unread ? ', dont ' . esc(plural($unread, 'non lue', 'non lues')) . ' sur cette page' : '' ?>.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php if ($unread): ?><form method="post" action="<?= site_url('notifications/tout-lire') ?>"><?= csrf_field() ?><button class="btn btn-secondary">Tout marquer comme lu</button></form><?php endif ?>
        <form method="post" action="<?= site_url('notifications/purger') ?>"><?= csrf_field() ?><button class="btn btn-ghost">Supprimer celles de plus de 30 jours</button></form>
        <a class="btn btn-ghost" href="<?= site_url('profil') ?>#notifications">Réglages</a>
    </div>
</div>
<?php if ($items): ?>
<ul class="mt-6 grid gap-2" role="list">
    <?php foreach ($items as $n): $new = ! (int) $n['is_read']; ?>
    <li><a href="<?= site_url('notifications/' . $n['id']) ?>" class="todo-card <?= $new ? 'border-2 border-brand' : '' ?>">
        <span class="text-2xl leading-none" aria-hidden="true"><?= esc($n['icon']) ?></span>
        <span class="min-w-0 flex-1">
            <span class="block text-lg font-bold"><?= $new ? '<span class="badge badge-info mr-1">Nouveau</span>' : '' ?><?= esc($n['title']) ?></span>
            <?php if ($n['body']): ?><span class="block"><?= esc(mb_strimwidth((string) $n['body'], 0, 200, '…')) ?></span><?php endif ?>
            <span class="block text-sm text-muted"><?= esc(date_fr($n['created_at'], "EEEE d MMMM 'à' HH:mm")) ?></span>
        </span>
        <?= icon('arrow', 'size-5 shrink-0 self-center text-brand') ?>
    </a></li>
    <?php endforeach ?>
</ul>
<?= pager($page, $pages, [], 'notifications') ?>
<?php endif ?>
<?= $this->endSection() ?>
