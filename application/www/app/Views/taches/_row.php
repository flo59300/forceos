<?php
/** @var array $t Tâche normalisée @var bool $showUser @var string|null $announce */
$done    = $t['status'] === 'fait';
$late    = \App\Domain\Tasks::isOverdue($t);
$key     = $t['source'] . '-' . $t['id'];
$base    = site_url('taches/' . $t['source'] . '/' . $t['id']);
?>
<li id="tache-<?= esc($key, 'attr') ?>" class="flex items-start gap-3 rounded-xl border bg-surface p-3 sm:p-4 <?= $late ? 'border-2 border-danger' : 'border-line' ?>">
    <form method="post" action="<?= $base ?>/cocher" hx-post="<?= $base ?>/cocher" hx-target="closest li" hx-swap="outerHTML" class="shrink-0">
        <?= csrf_field() ?>
        <button type="submit" id="cocher-<?= esc($key, 'attr') ?>" aria-pressed="<?= $done ? 'true' : 'false' ?>"
                class="grid size-11 place-items-center rounded-xl border-2 <?= $done ? 'border-success bg-success text-white' : 'border-line bg-surface text-transparent hover:border-brand' ?>">
            <?= icon('check', 'size-7') ?>
            <span class="sr-only"><?= $done ? 'Fait' : 'À faire' ?> : <?= esc($t['title']) ?>. <?= $done ? 'Remettre à faire' : 'Marquer comme fait' ?></span>
        </button>
    </form>
    <div class="min-w-0 flex-1">
        <p class="m-0 text-lg font-bold <?= $done ? 'text-muted line-through' : '' ?>"><?= esc($t['title']) ?></p>
        <?php if (! empty($t['description'])): ?><p class="m-0 text-muted"><?= esc($t['description']) ?></p><?php endif ?>
        <p class="m-0 mt-1 flex flex-wrap items-center gap-2 text-base">
            <?php if (! $done && $t['priority'] !== 'normale'): ?><?= priority_badge($t['priority']) ?><?php endif ?>
            <?php if ($late): ?><span class="badge badge-danger">En retard</span><?php endif ?>
            <?php if (! empty($t['due_date']) && ! $done): ?><span class="<?= $late ? 'font-bold text-danger' : 'text-muted' ?>">Échéance <?= esc(due_label($t['due_date'])) ?></span><?php endif ?>
            <?php if ($done && ! empty($t['done_at'])): ?><span class="text-muted">Faite le <?= esc(date_fr($t['done_at'], 'd MMMM')) ?></span><?php endif ?>
            <?php if (! empty($showUser) && ! empty($t['user_name'])): ?><span class="badge badge-info"><?= esc($t['user_name']) ?></span><?php endif ?>
        </p>
    </div>
    <div class="flex shrink-0 gap-1">
        <a href="<?= $base ?>/modifier" class="grid size-11 place-items-center rounded-xl text-brand hover:bg-surface-2"><?= icon('edit') ?><span class="sr-only">Modifier « <?= esc($t['title']) ?> »</span></a>
        <form method="post" action="<?= $base ?>/supprimer" onsubmit="return confirm('Supprimer cette tâche ?')">
            <?= csrf_field() ?>
            <button type="submit" class="grid size-11 place-items-center rounded-xl text-danger hover:bg-danger-soft"><?= icon('trash') ?><span class="sr-only">Supprimer « <?= esc($t['title']) ?> »</span></button>
        </form>
    </div>
    <?php if (! empty($announce)): ?><span hx-swap-oob="innerHTML:#annonce"><?= esc($announce) ?></span><?php endif ?>
</li>
