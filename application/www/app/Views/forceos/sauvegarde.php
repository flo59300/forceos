<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php /* ForceOS : sauvegarde chiffrée. Conçu et développé par Florian Bigotte pour Force Ouvrière. */ ?>
<h1 class="text-3xl">Sauvegarde chiffrée</h1>
<p class="mt-1 text-lg text-muted">Toutes les données de FO Gestion dans un seul fichier, chiffré par une phrase de passe, à conserver sur une clé USB autorisée.</p>

<div class="alert alert-error mt-6" id="fo-erreur" role="alert" hidden></div>
<div class="alert alert-success mt-6" id="fo-succes" role="status" hidden></div>

<section class="card mt-6 max-w-3xl" aria-labelledby="t-export">
    <h2 id="t-export" class="section-title">Créer une sauvegarde</h2>
    <p class="text-muted">Choisissez une phrase de passe d’au moins 12 caractères et notez-la en lieu sûr : sans elle, la sauvegarde est définitivement illisible, y compris pour Florian Bigotte. Elle permet de restaurer les données sur un autre poste ForceOS.</p>
    <form id="fo-export" novalidate>
        <div class="field">
            <label class="label" for="phrase-export">Phrase de passe</label>
            <input class="input" type="password" id="phrase-export" minlength="12" autocomplete="new-password" required>
        </div>
        <div class="field">
            <label class="label" for="phrase-confirmation">Confirmation</label>
            <input class="input" type="password" id="phrase-confirmation" minlength="12" autocomplete="new-password" required>
        </div>
        <button class="btn btn-primary" type="submit">Chiffrer et enregistrer</button>
    </form>
</section>

<?php if ($peutRestaurer): ?>
<section class="card mt-6 max-w-3xl" aria-labelledby="t-restauration">
    <h2 id="t-restauration" class="section-title">Restaurer une sauvegarde</h2>
    <p class="text-muted">Toutes les données actuelles seront remplacées par celles de la sauvegarde. Un instantané chiffré de l’état actuel est créé automatiquement avant l’opération<?= $instantanes ? ' (' . esc((string) $instantanes) . ' instantané(s) conservé(s) sur ce poste)' : '' ?>. FO Gestion redémarre ensuite.</p>
    <form id="fo-restauration" novalidate>
        <div class="field">
            <label class="label" for="phrase-restauration">Phrase de passe de la sauvegarde</label>
            <input class="input" type="password" id="phrase-restauration" autocomplete="off" required>
        </div>
        <button class="btn btn-danger" type="submit">Choisir le fichier et restaurer</button>
    </form>
</section>
<?php endif ?>

<p class="mt-6 text-sm text-muted">Sauvegarde ForceOS : AES-256-GCM, clé dérivée par scrypt. Conçu et développé par Florian Bigotte.</p>
<script src="<?= base_url('modules/forceos/sauvegarde.js') ?>" defer></script>
<?= $this->endSection() ?>
