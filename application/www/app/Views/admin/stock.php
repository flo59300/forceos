<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php use App\Domain\Stock; ?>
<p><a href="<?= site_url('administration') ?>">Administration</a></p>
<h1 class="text-3xl">Stock de timbres</h1>
<ul class="mt-6 grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))]" role="list">
    <?php foreach ($levels as $type => $n): ?>
    <li class="card"><p class="m-0 text-4xl font-bold <?= $n < 0 ? 'text-danger' : ($n < 50 ? 'text-warning' : '') ?>"><?= $n ?></p><p class="m-0 text-muted"><?= esc(mb_strtolower(Stock::TYPES[$type])) ?> en stock<?= $n < 50 ? ' : à commander' : '' ?></p></li>
    <?php endforeach ?>
</ul>
<p class="mt-2 text-muted">Niveau = entrées − sorties − ventes enregistrées.</p>

<section class="card mt-6" aria-labelledby="t-mouvement">
    <h2 id="t-mouvement" class="section-title">Enregistrer un mouvement</h2>
    <form method="post" action="<?= site_url('administration/stock') ?>">
        <?= csrf_field() ?>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))]">
            <fieldset class="field"><legend class="label">Article</legend>
                <?php foreach (Stock::TYPES as $k => $l): ?><label class="flex min-h-11 items-center gap-2"><input type="radio" name="type" value="<?= $k ?>" class="size-5" <?= $k === 'timbre' ? 'checked' : '' ?>> <?= esc($l) ?></label><?php endforeach ?></fieldset>
            <fieldset class="field"><legend class="label">Sens</legend>
                <label class="flex min-h-11 items-center gap-2"><input type="radio" name="movement" value="in" class="size-5" checked> Entrée (réception)</label>
                <label class="flex min-h-11 items-center gap-2"><input type="radio" name="movement" value="out" class="size-5"> Sortie (perte, retour…)</label></fieldset>
            <div class="field"><label class="label" for="quantity">Quantité</label><input class="input" type="number" min="1" id="quantity" name="quantity" required inputmode="numeric"></div>
        </div>
        <div class="field"><label class="label" for="reason">Motif <span class="font-normal text-muted">(facultatif)</span></label><input class="input" type="text" id="reason" name="reason" maxlength="255"></div>
        <button class="btn btn-primary">Enregistrer</button>
    </form>
</section>

<section class="card mt-6" aria-labelledby="t-histo">
    <h2 id="t-histo" class="section-title">Derniers mouvements</h2>
    <?php if (! $movements): ?><p class="m-0">Aucun mouvement enregistré.</p><?php else: ?>
    <ul class="grid gap-2" role="list">
        <?php foreach ($movements as $m): ?>
        <li class="flex flex-wrap gap-x-3"><strong><?= $m['movement'] === 'in' ? '+' : '−' ?><?= (int) $m['quantity'] ?> <?= esc(mb_strtolower(Stock::TYPES[$m['type']] ?? $m['type'])) ?></strong><span class="text-muted"><?= esc(date_fr($m['created_at'], 'd MMM y')) ?><?= $m['user_name'] ? ' · ' . esc($m['user_name']) : '' ?><?= $m['reason'] ? ' · ' . esc($m['reason']) : '' ?></span></li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
