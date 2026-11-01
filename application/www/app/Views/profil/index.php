<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$roles  = ['secretaire' => 'Secrétaire', 'tresorier' => 'Trésorier', 'collecteur' => 'Délégué collecteur', 'super_admin' => 'Super Admin'];
?>
<h1 class="text-3xl">Mon profil</h1>
<p class="mt-1 text-lg text-muted"><?= esc($roles[$u['role']] ?? $u['role']) ?> · dernière connexion <?= $u['last_login'] ? 'le ' . esc(date_fr($u['last_login'], "d MMMM y 'à' HH:mm")) : 'inconnue' ?></p>
<?= view('partials/form_errors') ?>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="card" aria-labelledby="t-coord" id="coordonnees">
        <h2 id="t-coord" class="section-title">Coordonnées</h2>
        <form method="post" action="<?= site_url('profil') ?>" novalidate>
            <?= csrf_field() ?>
            <div class="field"><label class="label" for="first_name">Prénom</label><input class="input" type="text" maxlength="100" autocomplete="given-name"<?= field_attrs('first_name', $errors) ?> value="<?= esc(old('first_name') ?? $u['first_name'], 'attr') ?>"><?= field_error('first_name', $errors) ?></div>
            <div class="field"><label class="label" for="last_name">Nom</label><input class="input" type="text" maxlength="100" autocomplete="family-name"<?= field_attrs('last_name', $errors) ?> value="<?= esc(old('last_name') ?? $u['last_name'], 'attr') ?>"><?= field_error('last_name', $errors) ?></div>
            <div class="field"><label class="label" for="email">Adresse e-mail (identifiant de connexion)</label><input class="input" type="email" autocomplete="email"<?= field_attrs('email', $errors) ?> value="<?= esc(old('email') ?? $u['email'], 'attr') ?>"><?= field_error('email', $errors) ?></div>
            <button class="btn btn-primary">Enregistrer</button>
        </form>
    </section>

    <section class="card" aria-labelledby="t-mdp" id="mot-de-passe">
        <h2 id="t-mdp" class="section-title">Mot de passe</h2>
        <form method="post" action="<?= site_url('profil/mot-de-passe') ?>" novalidate>
            <?= csrf_field() ?>
            <input type="text" name="username" value="<?= esc($u['email'], 'attr') ?>" autocomplete="username" class="sr-only" tabindex="-1" aria-hidden="true">
            <div class="field"><label class="label" for="current_password">Mot de passe actuel</label><input class="input" type="password" autocomplete="current-password"<?= field_attrs('current_password', $errors) ?>><?= field_error('current_password', $errors) ?></div>
            <div class="field"><label class="label" for="new_password">Nouveau mot de passe</label><span class="hint" id="aide-mdp">8 caractères au moins, avec une majuscule, une minuscule et un chiffre.</span><input class="input" type="password" autocomplete="new-password"<?= field_attrs('new_password', $errors, 'aide-mdp') ?>><?= field_error('new_password', $errors) ?></div>
            <div class="field"><label class="label" for="confirm_password">Nouveau mot de passe, à nouveau</label><input class="input" type="password" autocomplete="new-password"<?= field_attrs('confirm_password', $errors) ?>><?= field_error('confirm_password', $errors) ?></div>
            <button class="btn btn-primary">Changer le mot de passe</button>
        </form>
    </section>
</div>

<section class="card mt-6 max-w-3xl" aria-labelledby="t-notif" id="notifications">
    <h2 id="t-notif" class="section-title">Notifications sur cet appareil</h2>
    <?php if (! $vapid): ?>
    <p class="m-0">Les notifications push ne sont pas configurées sur ce serveur (clés VAPID absentes).</p>
    <?php else: ?>
    <div id="push" data-key="<?= esc($vapid, 'attr') ?>" data-devices="<?= (int) $devices ?>">
        <p class="m-0" id="push-etat" role="status">Vérification…</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary" id="push-on" hidden>Activer sur cet appareil</button>
            <button type="button" class="btn btn-secondary" id="push-test" hidden>Envoyer un essai</button>
            <button type="button" class="btn btn-ghost" id="push-off" hidden>Désactiver sur cet appareil</button>
        </div>
    </div>
    <form method="post" action="<?= site_url('profil/preferences') ?>" class="mt-5">
        <?= csrf_field() ?>
        <fieldset>
            <legend class="font-bold">Me prévenir par notification push pour</legend>
            <div class="mt-2 grid gap-1">
                <?php foreach (['messages' => ['push_notif_messages', 'les nouveaux messages'], 'delegation' => ['push_notif_delegation', 'les heures de délégation (seuils, transferts, fin de mois)'], 'agenda' => ['push_notif_agenda', 'l’agenda (nouvel événement, rappel la veille)']] as $k => [$col, $l]): ?>
                <label class="flex min-h-11 cursor-pointer items-center gap-2"><input type="checkbox" class="size-5" name="<?= $k ?>" value="1" <?= (int) $u[$col] ? 'checked' : '' ?>> <?= esc($l) ?></label>
                <?php endforeach ?>
            </div>
        </fieldset>
        <p class="mt-2 text-sm text-muted">Tout reste visible dans le centre de notifications, même désactivé ici.</p>
        <button class="btn btn-secondary mt-2">Enregistrer mes préférences</button>
    </form>
    <?php endif ?>
</section>
<script src="<?= base_url('assets/js/push.js?v=' . FO_ASSET_VERSION) ?>" data-base="<?= rtrim(site_url('/'), '/') ?>" id="push-script"></script>
<?= $this->endSection() ?>
