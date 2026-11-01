<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<h1 class="text-3xl">Tâches et questions</h1>
<div class="mt-5"><?= view('partials/tabs_taches', ['tab' => $tab]) ?></div>

<div class="mb-5 flex flex-wrap items-center gap-3">
    <a href="<?= site_url('taches/nouvelle') ?>" class="btn btn-primary"><?= icon('plus') ?> <?= $isSecretary ? 'Ajouter ou assigner une tâche' : 'Ajouter une tâche' ?></a>
    <?php if ($isSecretary): ?>
    <nav aria-label="Périmètre" class="flex gap-2">
        <a href="<?= site_url('taches') ?>" class="btn <?= $vue === 'moi' ? 'btn-secondary' : 'btn-ghost' ?>" <?= $vue === 'moi' ? 'aria-current="page"' : '' ?>>Mes tâches</a>
        <a href="<?= site_url('taches?vue=site') ?>" class="btn <?= $vue === 'site' ? 'btn-secondary' : 'btn-ghost' ?>" <?= $vue === 'site' ? 'aria-current="page"' : '' ?>>Tâches des délégués</a>
    </nav>
    <?php endif ?>
</div>

<section aria-labelledby="titre-en-cours">
    <h2 id="titre-en-cours" class="section-title">À faire <span class="text-muted">(<?= count($open) ?>)</span></h2>
    <?php if (! $open): ?>
    <p class="card">Aucune tâche en cours.</p>
    <?php else: ?>
    <ul class="grid gap-3" role="list">
        <?php foreach ($open as $t): ?><?= view('taches/_row', ['t' => $t, 'showUser' => $vue === 'site']) ?><?php endforeach ?>
    </ul>
    <?php endif ?>
</section>

<?php if ($done): ?>
<details class="mt-8">
    <summary class="btn btn-ghost px-0 text-lg">Tâches faites (<?= count($done) ?>)</summary>
    <ul class="mt-3 grid gap-3" role="list">
        <?php foreach ($done as $t): ?><?= view('taches/_row', ['t' => $t, 'showUser' => $vue === 'site']) ?><?php endforeach ?>
    </ul>
</details>
<?php endif ?>
<?= $this->endSection() ?>
