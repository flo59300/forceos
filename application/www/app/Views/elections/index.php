<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-3xl">Élections CSE</h1>
        <p class="mt-1 text-lg text-muted">Quorum, représentativité et répartition des sièges.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-primary" href="<?= site_url('elections/nouvelle') ?>"><?= icon('plus') ?> Nouvelle simulation</a>
        <a class="btn btn-secondary" href="<?= site_url('elections/nouvelle') ?>?officiel=1">Saisir un résultat officiel</a>
        <a class="btn btn-ghost" href="<?= site_url('listes-electorales') ?>">Listes électorales</a>
    </div>
</div>

<?php foreach (['Résultats officiels' => $officials, 'Simulations' => $sims] as $label => $items): $id = url_title($label, '-', true); ?>
<section class="mt-8" aria-labelledby="t-<?= $id ?>">
    <h2 id="t-<?= $id ?>" class="section-title"><?= esc($label) ?></h2>
    <?php if (! $items): ?>
    <div class="card"><p class="m-0 text-muted"><?= $label === 'Simulations' ? 'Aucune simulation enregistrée.' : 'Aucun résultat officiel saisi.' ?></p></div>
    <?php else: ?>
    <ul class="grid gap-3" role="list">
        <?php foreach ($items as $s): ?>
        <li><a href="<?= site_url('elections/' . $s['id']) ?>" class="todo-card">
            <span class="min-w-0 flex-1"><span class="block text-lg font-bold"><?= esc($s['name']) ?></span>
            <span class="block text-muted"><?= $s['election_date'] ? esc(date_fr($s['election_date'], 'd MMMM y')) . ' · ' : 'Modifiée le ' . esc(date_fr($s['updated_at'], 'd MMMM y')) . ' · ' ?><?= esc(plural((int) $s['nb_colleges'], 'collège')) ?><?= $s['total_seats'] ? ', ' . esc(plural((int) $s['total_seats'], 'siège titulaire', 'sièges titulaires')) : '' ?><?= $s['inscrits'] ? ', participation ' . number_format($s['votants'] / max(1, $s['inscrits']) * 100, 1, ',', '') . '&nbsp;%' : '' ?></span></span>
            <?= icon('arrow', 'size-5 shrink-0 self-center text-brand') ?>
        </a></li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>
<?php endforeach ?>
<?= $this->endSection() ?>
