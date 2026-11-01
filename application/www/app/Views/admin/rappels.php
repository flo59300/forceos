<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php $errors = session('errors') ?? []; ?>
<p><a href="<?= site_url('administration') ?>">Administration</a></p>
<h1 class="text-3xl">Rappels aux collecteurs</h1>
<p class="mt-1 text-lg text-muted">Chaque collecteur reçoit par courriel la liste de ses adhérents en retard. Aujourd’hui : <?= esc(plural($late, 'adhérent en retard', 'adhérents en retard')) ?> sur le site.</p>
<?php if (! $smtp): ?>
<div class="alert alert-info mt-4"><?= icon('alert', 'size-6 shrink-0') ?><p class="m-0">Aucun serveur d’envoi n’est réglé : les courriels partent par la fonction d’envoi de l’hébergeur. En cas de souci, demandez au Super Admin de renseigner les réglages SMTP.</p></div>
<?php endif ?>

<section class="card mt-6 max-w-3xl" aria-labelledby="t-reglage">
    <h2 id="t-reglage" class="section-title">Réglage</h2>
    <form method="post" action="<?= site_url('administration/rappels') ?>">
        <?= csrf_field() ?>
        <label class="flex min-h-11 cursor-pointer items-center gap-2 font-bold"><input type="checkbox" class="size-5" name="notifications_enabled" value="1" <?= (int) $site['notifications_enabled'] ? 'checked' : '' ?>> Envoyer les rappels</label>
        <div class="field mt-3"><label class="label" for="reminder_frequency_days">Pas plus d’un rappel tous les (jours)</label><input class="input w-28" type="number" min="1" max="30" id="reminder_frequency_days" name="reminder_frequency_days" value="<?= max(1, (int) $site['reminder_frequency_days']) ?>"></div>
        <button class="btn btn-primary">Enregistrer</button>
    </form>
</section>

<section class="mt-8" aria-labelledby="t-coll">
    <h2 id="t-coll" class="section-title">Collecteurs concernés</h2>
    <?php if (! $collecteurs): ?>
    <div class="card"><p class="m-0 text-muted">Aucun collecteur actif.</p></div>
    <?php else: ?>
    <ul class="grid gap-2" role="list">
        <?php foreach ($collecteurs as $c): ?>
        <li class="card"><p class="m-0 font-bold"><?= esc($c['first_name'] . ' ' . $c['last_name']) ?></p>
            <p class="m-0 break-words text-muted"><?= esc($c['email']) ?> · <?= esc(plural((int) $c['nb'], 'adhérent suivi', 'adhérents suivis')) ?> · <?= $c['last_sent'] ? 'dernier rappel le ' . esc(date_fr($c['last_sent'], 'd MMMM y')) : 'aucun rappel envoyé' ?></p></li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>

<section class="card mt-8 max-w-3xl" aria-labelledby="t-essai" id="essai">
    <h2 id="t-essai" class="section-title">Essai d’envoi</h2>
    <?= view('partials/form_errors') ?>
    <form method="post" action="<?= site_url('administration/rappels/essai') ?>" class="flex flex-wrap items-end gap-2" novalidate>
        <?= csrf_field() ?>
        <div class="min-w-0 flex-1 basis-64"><label class="label" for="test_email">Adresse de test</label><input class="input" type="email"<?= field_attrs('test_email', $errors) ?> value="<?= esc(old('test_email') ?? '', 'attr') ?>"><?= field_error('test_email', $errors) ?></div>
        <button class="btn btn-secondary">Envoyer un essai</button>
    </form>
    <p class="mt-3 text-sm text-muted">L’envoi automatique est lancé chaque jour par une tâche planifiée de l’hébergeur.</p>
</section>
<?= $this->endSection() ?>
