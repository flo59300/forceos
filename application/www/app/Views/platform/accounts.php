<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php $errors = session('errors') ?? []; $meId = (int) ($me['id'] ?? 0); ?>
<h1 class="text-3xl">Comptes</h1>
<p class="mt-1 text-lg text-muted">Super Admin et secrétaires de tous les sites. Chaque secrétaire gère ensuite les comptes de son site.</p>
<?= view('platform/_credentials', ['credentials' => $credentials]) ?>

<section class="card mt-6 max-w-3xl" aria-labelledby="t-form" id="formulaire" x-data="{ role: '<?= esc(old('role') ?? 'secretaire', 'js') ?>' }">
    <h2 id="t-form" class="section-title">Nouveau compte</h2>
    <?= view('partials/form_errors') ?>
    <form method="post" action="<?= site_url('plateforme/comptes') ?>" novalidate>
        <?= csrf_field() ?>
        <fieldset class="field">
            <legend class="label">Rôle</legend>
            <div class="flex flex-wrap gap-2">
                <?php foreach (['secretaire' => 'Secrétaire d’un site', 'super_admin' => 'Super Admin'] as $k => $l): ?>
                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border-2 border-line px-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft"><input type="radio" name="role" value="<?= $k ?>" class="size-5" x-model="role" <?= (old('role') ?? 'secretaire') === $k ? 'checked' : '' ?>> <?= esc($l) ?></label>
                <?php endforeach ?>
            </div>
        </fieldset>
        <div class="field" x-show="role === 'secretaire'">
            <label class="label" for="site_id">Site</label>
            <select class="input"<?= field_attrs('site_id', $errors) ?>><option value="">Choisir…</option>
                <?php foreach ($sites as $s): ?><option value="<?= $s['id'] ?>" <?= (string) old('site_id') === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach ?>
            </select><?= field_error('site_id', $errors) ?>
        </div>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,14rem),1fr))]">
            <div class="field"><label class="label" for="first_name">Prénom</label><input class="input" type="text" maxlength="100" autocomplete="off"<?= field_attrs('first_name', $errors) ?> value="<?= esc(old('first_name') ?? '', 'attr') ?>"><?= field_error('first_name', $errors) ?></div>
            <div class="field"><label class="label" for="last_name">Nom</label><input class="input" type="text" maxlength="100" autocomplete="off"<?= field_attrs('last_name', $errors) ?> value="<?= esc(old('last_name') ?? '', 'attr') ?>"><?= field_error('last_name', $errors) ?></div>
        </div>
        <div class="field"><label class="label" for="email">Adresse e-mail (identifiant)</label><input class="input" type="email" autocomplete="off"<?= field_attrs('email', $errors) ?> value="<?= esc(old('email') ?? '', 'attr') ?>"><?= field_error('email', $errors) ?></div>
        <p class="text-muted">Un mot de passe est créé au hasard et affiché une seule fois.</p>
        <button class="btn btn-primary">Créer le compte</button>
    </form>
</section>

<section class="mt-8" aria-labelledby="t-liste">
    <h2 id="t-liste" class="section-title">Comptes existants</h2>
    <ul class="grid gap-3" role="list">
        <?php foreach ($accounts as $a): $name = trim($a['first_name'] . ' ' . $a['last_name']); ?>
        <li id="compte-<?= $a['id'] ?>" class="card scroll-mt-24 <?= $a['active'] ? '' : 'border-dashed bg-surface-2' ?>">
            <div class="flex flex-wrap items-start gap-2">
                <div class="min-w-0 flex-1">
                    <h3 class="m-0 break-words text-lg"><?= esc($name) ?><?= (int) $a['id'] === $meId ? ' <span class="text-base font-normal text-muted">(vous)</span>' : '' ?></h3>
                    <p class="m-0 break-words text-muted"><?= esc($a['email']) ?> · <?= $a['role'] === 'super_admin' ? 'Super Admin' : 'Secrétaire, ' . esc($a['site_name'] ?? 'site supprimé') ?> · <?= $a['last_login'] ? 'connecté le ' . esc(date_fr($a['last_login'], 'd MMM y')) : 'jamais connecté' ?></p>
                </div>
                <?php if (! $a['active']): ?><span class="badge badge-muted">Désactivé</span><?php endif ?>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <form method="post" action="<?= site_url('plateforme/comptes/' . $a['id'] . '/mot-de-passe') ?>" onsubmit="return confirm('Créer un nouveau mot de passe pour ce compte ? L’ancien ne fonctionnera plus.')"><?= csrf_field() ?><button class="btn btn-secondary">Nouveau mot de passe<span class="sr-only"> pour <?= esc($name) ?></span></button></form>
                <?php if ((int) $a['id'] !== $meId): ?>
                <form method="post" action="<?= site_url('plateforme/comptes/' . $a['id'] . '/activation') ?>"><?= csrf_field() ?><button class="btn btn-ghost"><?= $a['active'] ? 'Désactiver' : 'Réactiver' ?><span class="sr-only"> le compte de <?= esc($name) ?></span></button></form>
                <?php endif ?>
            </div>
        </li>
        <?php endforeach ?>
    </ul>
</section>
<?= $this->endSection() ?>
