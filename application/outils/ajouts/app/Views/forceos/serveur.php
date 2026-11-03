<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php /* ForceOS : mode serveur pour les téléphones. Conçu et développé par Florian Bigotte pour Force Ouvrière. */ ?>
<h1 class="text-3xl">Mode serveur pour les téléphones</h1>
<p class="mt-1 text-lg text-muted">Le temps choisi, les militants du syndicat FO ouvrent FO Gestion sur leur téléphone, chacun avec son propre compte, et travaillent sur les données de ce PC.</p>

<div class="alert alert-error mt-6" id="fo-erreur" role="alert" hidden></div>

<?php if (! $autorise): ?>
<div class="alert alert-info mt-6" role="status"><p class="m-0">Seul le secrétaire peut ouvrir le mode serveur.</p></div>
<?php else: ?>
<section class="card mt-6 max-w-3xl" id="fo-preparation" aria-labelledby="t-ouvrir">
    <h2 id="t-ouvrir" class="section-title">Ouvrir le mode serveur</h2>
    <form id="fo-ouvrir" novalidate>
        <fieldset class="field">
            <legend class="label">Réseau utilisé par les téléphones</legend>
            <label class="flex items-start gap-3 py-1"><input type="radio" name="mode" value="wifi-pc" checked> <span><b>Wi-Fi créé par ce PC</b><br><span class="text-muted">Rien ne passe par le réseau de l’entreprise. Le PC perd sa propre connexion Wi-Fi pendant ce temps.</span></span></label>
            <label class="flex items-start gap-3 py-1"><input type="radio" name="mode" value="wifi-existant"> <span><b>Wi-Fi déjà en place</b> (local du syndicat, maison)<br><span class="text-muted">Le PC et les téléphones doivent être sur le même Wi-Fi. Souvent bloqué sur les Wi-Fi d’entreprise et publics.</span></span></label>
        </fieldset>
        <div class="field">
            <label class="label" for="fo-duree">Durée</label>
            <select class="input" id="fo-duree">
                <option value="30">30 minutes</option>
                <option value="60" selected>1 heure</option>
                <option value="120">2 heures</option>
                <option value="240">4 heures</option>
            </select>
        </div>
        <p class="text-muted">Le mot de passe administrateur sera demandé. Tout s’arrête à la fin de la durée, quand l’écran du PC se verrouille, au bouton d’urgence ou à la fermeture de FO Gestion.</p>
        <button class="btn btn-primary" type="submit">Ouvrir aux téléphones</button>
    </form>
</section>

<section class="card mt-6 max-w-4xl" id="fo-actif" aria-labelledby="t-actif" hidden>
    <h2 id="t-actif" class="section-title">Mode serveur ouvert jusqu’à <span id="fo-fin"></span></h2>
    <div class="grid gap-6 grid-cols-[repeat(auto-fit,minmax(min(100%,15rem),1fr))]">
        <div id="fo-bloc-wifi">
            <p class="font-bold">1. Rejoindre le Wi-Fi du PC</p>
            <img id="fo-qr-wifi" alt="QR code du Wi-Fi" class="rounded-lg border border-line bg-white p-2" style="width:14rem;height:14rem">
            <p class="text-muted">Réseau <b id="fo-ssid"></b>, mot de passe <b id="fo-mdp"></b></p>
        </div>
        <div>
            <p class="font-bold"><span id="fo-etape-adresse">2.</span> Ouvrir FO Gestion</p>
            <img id="fo-qr-adresse" alt="QR code de l’adresse de FO Gestion" class="rounded-lg border border-line bg-white p-2" style="width:14rem;height:14rem">
            <p class="text-muted break-all">Adresse <b id="fo-adresse"></b></p>
        </div>
    </div>
    <p class="mt-4 text-muted">À la première connexion, le téléphone affiche un avertissement de sécurité : le certificat est propre à ce PC. Vérifiez que l’empreinte se termine par <b id="fo-empreinte"></b>, puis acceptez. Chacun se connecte ensuite avec son compte FO Gestion.</p>
    <button class="btn btn-danger mt-2" type="button" id="fo-fermer">Fermer le mode serveur</button>
</section>
<?php endif ?>

<p class="mt-6 text-sm text-muted">Liaison chiffrée (HTTPS), essais de connexion limités, sauvegarde et assistant IA réservés au PC. Conçu et développé par Florian Bigotte.</p>
<script src="<?= base_url('modules/forceos/serveur.js') ?>" defer></script>
<?= $this->endSection() ?>
