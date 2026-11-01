<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
use App\Controllers\Platform;

$errors = session('errors') ?? [];
$val    = static fn (string $k, string $d = '') => old($k) ?? $s($k, $d);
$text   = static function (string $name, string $label, string $value, array $errors, string $extra = '', string $hint = '') {
    $h = $hint !== '' ? '<p class="hint" id="aide-' . $name . '">' . esc($hint) . '</p>' : '';

    return '<div class="field"><label class="label" for="' . $name . '">' . esc($label) . '</label>' . $h
        . '<input class="input" type="text"' . field_attrs($name, $errors, $hint !== '' ? 'aide-' . $name : '') . ' ' . $extra . ' value="' . esc($value, 'attr') . '">' . field_error($name, $errors) . '</div>';
};
$img = static fn (string $p) => is_file(FCPATH . ltrim($p, '/')) ? base_url(ltrim($p, '/')) : (legacy_url(ltrim($p, '/')) ?? base_url(ltrim($p, '/')));
?>
<h1 class="text-3xl">Réglages</h1>
<p class="mt-1 text-lg text-muted">Réglages communs à toute la plateforme, partagés avec la version 2.x tant qu’elle est en service.</p>

<nav class="tabs mt-6" aria-label="Rubriques des réglages">
    <?php foreach (Platform::TABS as $k => $l): ?>
    <a class="tab" href="<?= site_url('plateforme/reglages?onglet=' . $k) ?>" <?= $tab === $k ? 'aria-current="page"' : '' ?>><?= esc($l) ?></a>
    <?php endforeach ?>
</nav>

<section class="card mt-4 max-w-3xl" aria-labelledby="t-onglet">
    <h2 id="t-onglet" class="section-title"><?= esc(Platform::TABS[$tab]) ?></h2>
    <?= view('partials/form_errors') ?>

    <?php if ($tab === 'general'): ?>
    <form method="post" action="<?= site_url('plateforme/reglages/general') ?>" novalidate>
        <?= csrf_field() ?>
        <?= $text('app_name', 'Nom de l’application', $val('app_name', config('Fo')->appName), $errors, 'maxlength="120" required') ?>
        <div class="field"><label class="label" for="app_description">Description</label><textarea class="input min-h-24" maxlength="500"<?= field_attrs('app_description', $errors) ?>><?= esc($val('app_description')) ?></textarea></div>
        <?= $text('footer_copyright', 'Mention de bas de page', $val('footer_copyright', 'Force Ouvrière'), $errors, 'maxlength="120"') ?>
        <?= $text('footer_text', 'Texte libre de bas de page', $val('footer_text'), $errors, 'maxlength="500"') ?>
        <button class="btn btn-primary">Enregistrer</button>
    </form>

    <?php elseif ($tab === 'application'): ?>
    <p class="mt-0">Nom, couleurs et icône de l’application installée sur les téléphones. Un téléphone déjà équipé reprend les changements à la prochaine mise à jour de l’application par le système.</p>
    <form method="post" action="<?= site_url('plateforme/reglages/application') ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <?= $text('pwa_short_name', 'Nom court (sous l’icône, 12 caractères conseillés)', $val('pwa_short_name', $s('app_name', 'FO Gestion')), $errors, 'maxlength="30"') ?>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))]">
            <?php foreach (['pwa_theme_color' => ['Couleur de la barre', '#ffffff'], 'pwa_background_color' => ['Couleur de démarrage', '#f1f5f9']] as $k => [$l, $d]): ?>
            <div class="field"><label class="label" for="<?= $k ?>"><?= esc($l) ?></label><input class="input h-12 w-28 p-1" type="color" id="<?= $k ?>" name="<?= $k ?>" value="<?= esc($val($k, $d), 'attr') ?>"></div>
            <?php endforeach ?>
            <div class="field"><label class="label" for="pwa_display">Affichage</label>
                <select class="input" id="pwa_display" name="pwa_display">
                    <?php foreach (['standalone' => 'Comme une application', 'fullscreen' => 'Plein écran', 'minimal-ui' => 'Barre réduite', 'browser' => 'Navigateur'] as $k => $l): ?><option value="<?= $k ?>" <?= $val('pwa_display', 'standalone') === $k ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?>
                </select></div>
        </div>
        <?php foreach (['pwa_logo' => ['Icône de l’application (carrée, 512 pixels conseillés)', 'pwa_logo_path', '/assets/icons/fostellantis.png'], 'favicon' => ['Icône de l’onglet du navigateur', 'pwa_favicon_path', '/assets/icons/favicon.svg']] as $f => [$l, $key, $d]): ?>
        <fieldset class="field rounded-xl border border-line p-3">
            <legend class="label px-1"><?= esc($l) ?></legend>
            <img src="<?= esc($img($s($key, $d)), 'attr') ?>" alt="Icône actuelle" class="mb-2 size-16 rounded-xl border border-line object-contain">
            <label class="label" for="<?= $f ?>_file">Remplacer <span class="font-normal text-muted">(PNG, JPEG ou WebP, 2 Mo au plus)</span></label>
            <input class="input" type="file" accept="image/png,image/jpeg,image/webp"<?= field_attrs($f . '_file', $errors) ?>><?= field_error($f . '_file', $errors) ?>
            <label class="mt-2 flex min-h-11 cursor-pointer items-center gap-2"><input type="checkbox" class="size-5" name="reset_<?= $f ?>" value="1"> Revenir à l’icône d’origine</label>
        </fieldset>
        <?php endforeach ?>
        <?php if ($legacyPath === ''): ?><p class="hint">Les icônes déposées ici ne sont pas recopiées dans la version 2.x (son dossier n’est pas renseigné dans .env).</p><?php endif ?>
        <button class="btn btn-primary">Enregistrer</button>
    </form>

    <?php elseif ($tab === 'courriels'): ?>
    <p class="mt-0">Serveur d’envoi des rappels, récapitulatifs et essais. Sans serveur réglé, la fonction d’envoi de l’hébergeur est utilisée.</p>
    <form method="post" action="<?= site_url('plateforme/reglages/courriels') ?>" novalidate>
        <?= csrf_field() ?>
        <div class="grid gap-x-4 grid-cols-[2fr_1fr] max-sm:grid-cols-1">
            <?= $text('smtp_host', 'Serveur SMTP', $val('smtp_host'), $errors, 'autocomplete="off" placeholder="smtp-fo.alwaysdata.net"') ?>
            <div class="field"><label class="label" for="smtp_port">Port</label><input class="input" type="number" min="1" max="65535" id="smtp_port" name="smtp_port" value="<?= esc($val('smtp_port', '587'), 'attr') ?>"></div>
        </div>
        <div class="field"><label class="label" for="smtp_encryption">Chiffrement</label>
            <select class="input" id="smtp_encryption" name="smtp_encryption"><?php foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'Aucun'] as $k => $l): ?><option value="<?= $k ?>" <?= $val('smtp_encryption', 'tls') === $k ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?></select></div>
        <?= $text('smtp_user', 'Identifiant', $val('smtp_user'), $errors, 'autocomplete="off"') ?>
        <div class="field"><label class="label" for="smtp_pass">Mot de passe</label><p class="hint" id="aide-smtp-pass"><?= $s('smtp_pass') !== '' ? 'Un mot de passe est enregistré. Laissez vide pour le garder.' : 'Aucun mot de passe enregistré.' ?></p><input class="input" type="password" id="smtp_pass" name="smtp_pass" autocomplete="new-password" aria-describedby="aide-smtp-pass"></div>
        <?= $text('smtp_from', 'Adresse d’expédition', $val('smtp_from'), $errors, 'inputmode="email" autocomplete="off"') ?>
        <?= $text('smtp_from_name', 'Nom de l’expéditeur', $val('smtp_from_name'), $errors, 'maxlength="120"') ?>
        <button class="btn btn-primary">Enregistrer</button>
    </form>
    <form method="post" action="<?= site_url('plateforme/reglages/essai-courriel') ?>" class="mt-6 flex flex-wrap items-end gap-2 border-t border-line pt-4" id="essai" novalidate>
        <?= csrf_field() ?>
        <div class="min-w-0 flex-1 basis-64"><label class="label" for="smtp_test_email">Envoyer un essai à</label><input class="input" type="email"<?= field_attrs('smtp_test_email', $errors) ?> value="<?= esc(old('smtp_test_email') ?? (string) ($me['email'] ?? ''), 'attr') ?>"><?= field_error('smtp_test_email', $errors) ?></div>
        <button class="btn btn-secondary">Envoyer l’essai</button>
    </form>

    <?php elseif ($tab === 'notifications'): ?>
    <p class="mt-0">Les notifications sur téléphone reposent sur une paire de clés (VAPID). <?= esc(plural($subscriptions, 'appareil abonné', 'appareils abonnés')) ?> aujourd’hui.</p>
    <?php if ($vapidPublic !== ''): ?>
    <p class="font-bold">Clé publique en service</p>
    <p class="break-all rounded-xl bg-surface-2 p-3 font-mono text-sm"><?= esc($vapidPublic) ?></p>
    <?php else: ?>
    <div class="alert alert-info"><?= icon('alert', 'size-6 shrink-0') ?><p class="m-0">Aucune clé : les notifications sur téléphone sont inactives.</p></div>
    <?php endif ?>
    <?php if ($vapidFromEnv): ?><p>Les clés viennent du fichier .env (reprises de la version 2.x à l’installation) : les téléphones déjà abonnés continuent de recevoir les notifications sans rien faire.</p><?php endif ?>
    <form method="post" action="<?= site_url('plateforme/reglages/notifications') ?>" novalidate>
        <?= csrf_field() ?>
        <?= $text('vapid_subject', 'Contact de l’émetteur', $val('vapid_subject', 'mailto:'), $errors, 'autocomplete="off"', 'Exemple : mailto:secretaire@exemple.fr. Les services de notification l’utilisent en cas de problème.') ?>
        <?php if (! $vapidFromEnv): ?>
        <label class="mb-4 flex min-h-11 cursor-pointer items-start gap-2"><input type="checkbox" class="mt-1 size-5" name="generate" value="1"> <span><span class="font-bold"><?= $vapidPublic !== '' ? 'Remplacer les clés' : 'Créer les clés' ?></span><?= $vapidPublic !== '' ? '. Attention : tous les appareils abonnés devront réactiver les notifications depuis « Mon profil ».' : '' ?></span></label>
        <?php endif ?>
        <button class="btn btn-primary">Enregistrer</button>
    </form>

    <?php elseif ($tab === 'legal'): ?>
    <p class="mt-0">Compléments affichés en tête des pages <a href="<?= site_url('mentions-legales') ?>">Mentions légales</a> et <a href="<?= site_url('cgu') ?>">Conditions d’utilisation</a>. Texte simple ; la mise en forme HTML de base (paragraphes, gras, listes, liens) est acceptée.</p>
    <form method="post" action="<?= site_url('plateforme/reglages/legal') ?>" novalidate>
        <?= csrf_field() ?>
        <?php foreach (['legal_mentions' => ['Mentions légales', 'Éditeur, hébergeur, responsable de publication…'], 'legal_rgpd' => ['Politique RGPD (sous les mentions légales)', 'Responsable de traitement, données collectées, durée de conservation…'], 'legal_cgu' => ['Conditions propres à la section', '']] as $k => [$l, $ph]): ?>
        <div class="field"><label class="label" for="<?= $k ?>"><?= esc($l) ?></label><textarea class="input min-h-32" id="<?= $k ?>" name="<?= $k ?>" maxlength="20000" placeholder="<?= esc($ph, 'attr') ?>"><?= esc($val($k)) ?></textarea></div>
        <?php endforeach ?>
        <button class="btn btn-primary">Enregistrer</button>
    </form>

    <?php else: ?>
    <form method="post" action="<?= site_url('plateforme/reglages/avance') ?>" novalidate>
        <?= csrf_field() ?>
        <fieldset class="field">
            <legend class="label">Fonctionnement</legend>
            <?php foreach (['saas' => ['Plusieurs sites', 'Chaque site a son secrétaire ; le Super Admin administre la plateforme.'], 'standalone' => ['Mode autonome', 'Un seul site : le Super Admin y travaille comme secrétaire et garde l’accès à cet espace par « Tout le reste ».']] as $k => [$l, $d]): ?>
            <label class="mb-2 flex cursor-pointer items-start gap-3 rounded-xl border-2 border-line p-3 has-[:checked]:border-brand has-[:checked]:bg-brand-soft"><input type="radio" name="app_mode" value="<?= $k ?>" class="mt-1 size-5" <?= $s('app_mode', 'saas') === $k ? 'checked' : '' ?>><span><span class="block font-bold"><?= esc($l) ?></span><span class="block text-muted"><?= esc($d) ?></span></span></label>
            <?php endforeach ?>
        </fieldset>
        <label class="flex min-h-11 cursor-pointer items-start gap-3"><input type="checkbox" class="mt-1 size-5" name="kb_shared" value="1" <?= $s('kb_shared') === '1' ? 'checked' : '' ?>><span><span class="block font-bold">Base documentaire commune</span><span class="block text-muted">Tous les sites cherchent dans les mêmes documents.</span></span></label>
        <label class="mt-2 mb-4 flex min-h-11 cursor-pointer items-start gap-3"><input type="checkbox" class="mt-1 size-5" name="maintenance_mode" value="1" <?= $s('maintenance_mode') === '1' ? 'checked' : '' ?>><span><span class="block font-bold">Mode maintenance</span><span class="block text-muted">Seuls les Super Admin peuvent se connecter, dans les deux versions. Pensez à le retirer.</span></span></label>
        <button class="btn btn-primary">Enregistrer</button>
    </form>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
