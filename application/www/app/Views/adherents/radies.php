<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<p><a href="<?= site_url('adherents') ?>">Retour aux adhérents</a></p>
<h1 class="text-3xl">Adhérents radiés</h1>
<p class="mt-1 text-lg text-muted"><?= esc(plural(count($rows), 'adhérent radié', 'adhérents radiés')) ?>. L’historique de leurs paiements est conservé.</p>
<?php if (! $rows): ?>
<p class="card mt-6">Aucun adhérent radié.</p>
<?php else: ?>
<ul class="mt-6 grid gap-2" role="list">
    <?php foreach ($rows as $r): ?>
    <li class="flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface p-3">
        <div class="min-w-0 flex-1">
            <p class="m-0 text-lg font-bold"><?= esc(mb_strtoupper($r['last_name']) . ' ' . $r['first_name']) ?></p>
            <p class="m-0 text-muted">Radié<?= $r['radiation_date'] ? ' le ' . esc(date_fr($r['radiation_date'])) : '' ?><?= $r['by_name'] ? ' par ' . esc($r['by_name']) : '' ?><?= $r['radiation_motif'] ? ' · ' . esc($r['radiation_motif']) : '' ?></p>
        </div>
        <form method="post" action="<?= site_url('adherents/' . $r['id'] . '/reintegrer') ?>">
            <?= csrf_field() ?><button class="btn btn-secondary">Réintégrer<span class="sr-only"> <?= esc($r['first_name'] . ' ' . $r['last_name']) ?></span></button>
        </form>
    </li>
    <?php endforeach ?>
</ul>
<?php endif ?>
<?= $this->endSection() ?>
