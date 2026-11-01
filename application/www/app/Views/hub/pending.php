<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<h1 class="text-3xl"><?= esc($item['label']) ?></h1>
<div class="card mt-6 max-w-2xl">
    <p class="text-lg">Cet écran est en cours de migration vers le nouveau hub. En attendant, il reste disponible dans la version actuelle, avec les mêmes données.</p>
    <?php if (! empty($item['legacy']) && ($url = legacy_url($item['legacy']))): ?>
    <a href="<?= esc($url, 'attr') ?>" class="btn btn-primary mt-2"><?= icon('external') ?> Ouvrir dans la version actuelle</a>
    <?php endif ?>
    <a href="<?= site_url('/') ?>" class="btn btn-ghost mt-2">Revenir à Ma journée</a>
</div>
<?= $this->endSection() ?>
