<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<p class="print:hidden"><a href="<?= site_url('elections/' . $sim['id']) ?>">Retour à la saisie</a></p>
<h1 class="text-3xl"><?= esc($sim['name']) ?></h1>
<p class="mt-1 text-lg text-muted"><?= $sim['is_official'] ? 'Résultat officiel' . ($sim['election_date'] ? ' du ' . esc(date_fr($sim['election_date'], 'd MMMM y')) : '') : 'Simulation' ?> · <?= esc(session('site_name')) ?></p>
<p class="print:hidden mt-3"><button type="button" class="btn btn-primary" onclick="window.print()">Imprimer</button></p>
<?= view('elections/_results', ['r' => $r, 'state' => $state]) ?>
<?= $this->endSection() ?>
