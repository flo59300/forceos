<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<h1 class="text-3xl"><?= esc($title) ?></h1>
<p class="mt-3 text-lg"><?= esc($message) ?></p>
<?= $this->endSection() ?>
