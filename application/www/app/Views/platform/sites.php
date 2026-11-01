<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$v      = static fn (string $f, $d = '') => old($f, $edit[$f] ?? $d);
$logo   = static function (?string $p): ?string {
    if (! $p) {
        return null;
    }

    return is_file(FCPATH . ltrim($p, '/')) ? base_url(ltrim($p, '/')) : legacy_url(ltrim($p, '/'));
};
?>
<h1 class="text-3xl">Sites</h1>
<p class="mt-1 text-lg text-muted">Chaque site a ses adhérents, ses comptes et ses tarifs.</p>
<?= view('platform/_credentials', ['credentials' => $credentials]) ?>

<section class="card mt-6 max-w-3xl" aria-labelledby="t-form" id="formulaire">
    <h2 id="t-form" class="section-title"><?= $edit ? 'Modifier « ' . esc($edit['name']) . ' »' : 'Nouveau site' ?></h2>
    <?= view('partials/form_errors') ?>
    <?php if (! $edit): ?><p class="mt-0 text-muted">La création ouvre l’année <?= date('Y') ?> et deux comptes, secrétaire et trésorier, dont les mots de passe s’affichent une seule fois.</p><?php endif ?>
    <form method="post" action="<?= $edit ? site_url('plateforme/sites/' . $edit['id']) : site_url('plateforme/sites') ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label class="label" for="name">Nom du site <span class="font-normal text-muted">(obligatoire)</span></label><input class="input" type="text" maxlength="255" required<?= field_attrs('name', $errors) ?> value="<?= esc($v('name'), 'attr') ?>"><?= field_error('name', $errors) ?></div>
        <div class="field"><label class="label" for="address">Adresse</label><input class="input" type="text" maxlength="500"<?= field_attrs('address', $errors) ?> value="<?= esc($v('address'), 'attr') ?>" autocomplete="off"><?= field_error('address', $errors) ?></div>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))]">
            <div class="field"><label class="label" for="tarif_timbre">Tarif du timbre (€)</label><input class="input" type="number" step="0.01" min="0" inputmode="decimal"<?= field_attrs('tarif_timbre', $errors) ?> value="<?= esc($v('tarif_timbre', '5.00'), 'attr') ?>"><?= field_error('tarif_timbre', $errors) ?></div>
            <div class="field"><label class="label" for="tarif_carte">Tarif de la carte (€)</label><input class="input" type="number" step="0.01" min="0" inputmode="decimal"<?= field_attrs('tarif_carte', $errors) ?> value="<?= esc($v('tarif_carte', '5.00'), 'attr') ?>"><?= field_error('tarif_carte', $errors) ?></div>
        </div>
        <?php if ($edit): ?>
        <div class="field">
            <label class="label" for="site_logo">Logo du site <span class="font-normal text-muted">(PNG, JPEG ou WebP, 2 Mo au plus)</span></label>
            <?php if ($src = $logo($edit['logo_path'])): ?><img src="<?= esc($src, 'attr') ?>" alt="Logo actuel" class="mb-2 h-14 w-auto rounded-lg border border-line"><?php endif ?>
            <input class="input" type="file" accept="image/png,image/jpeg,image/webp"<?= field_attrs('site_logo', $errors) ?>><?= field_error('site_logo', $errors) ?>
            <?php if ($edit['logo_path']): ?><label class="mt-2 flex min-h-11 cursor-pointer items-center gap-2"><input type="checkbox" class="size-5" name="remove_logo" value="1"> Retirer le logo</label><?php endif ?>
        </div>
        <label class="mb-4 flex min-h-11 cursor-pointer items-center gap-2 font-bold"><input type="checkbox" class="size-5" name="active" value="1" <?= $v('active', 1) ? 'checked' : '' ?>> Site actif</label>
        <?php endif ?>
        <div class="flex flex-wrap gap-3">
            <button class="btn btn-primary"><?= $edit ? 'Enregistrer' : 'Créer le site' ?></button>
            <?php if ($edit): ?><a href="<?= site_url('plateforme/sites') ?>" class="btn btn-ghost">Annuler</a><?php endif ?>
        </div>
    </form>
</section>

<section class="mt-8" aria-labelledby="t-liste">
    <h2 id="t-liste" class="section-title">Tous les sites</h2>
    <?php if (! $sites): ?><div class="card"><p class="m-0 text-muted">Aucun site pour le moment.</p></div><?php else: ?>
    <ul class="grid gap-3 grid-cols-[repeat(auto-fill,minmax(min(100%,20rem),1fr))]" role="list">
        <?php foreach ($sites as $s): ?>
        <li id="site-<?= $s['id'] ?>" class="card scroll-mt-24 <?= $s['active'] ? '' : 'border-dashed bg-surface-2' ?>">
            <div class="flex items-start gap-3">
                <?php if ($src = $logo($s['logo_path'])): ?><img src="<?= esc($src, 'attr') ?>" alt="" class="size-12 shrink-0 rounded-lg border border-line object-contain"><?php endif ?>
                <div class="min-w-0 flex-1">
                    <h3 class="m-0 break-words text-xl"><?= esc($s['name']) ?></h3>
                    <p class="m-0 text-sm text-muted">Code <?= esc($s['code'] ?? '') ?><?= $s['active'] ? '' : ' · inactif' ?></p>
                </div>
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                <div><dt class="text-muted">Timbre</dt><dd class="m-0 font-bold"><?= money((float) $s['tarif_timbre']) ?></dd></div>
                <div><dt class="text-muted">Carte</dt><dd class="m-0 font-bold"><?= money((float) $s['tarif_carte']) ?></dd></div>
                <div><dt class="text-muted">Comptes</dt><dd class="m-0 font-bold"><?= (int) $s['nb_users'] ?></dd></div>
                <div><dt class="text-muted">Adhérents</dt><dd class="m-0 font-bold"><?= (int) $s['nb_members'] ?></dd></div>
            </dl>
            <?php if ($s['address']): ?><p class="mt-2 break-words text-sm"><?= esc($s['address']) ?></p><?php endif ?>
            <a class="btn btn-secondary mt-3" href="<?= site_url('plateforme/sites') . '?modifier=' . $s['id'] ?>#formulaire"><?= icon('edit') ?> Modifier<span class="sr-only"> le site <?= esc($s['name']) ?></span></a>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
