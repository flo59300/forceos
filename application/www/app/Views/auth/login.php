<?= $this->extend('layouts/simple') ?>
<?= $this->section('content') ?>
<div class="mb-8 flex items-center gap-3">
    <span class="grid size-14 place-items-center rounded-2xl bg-[#002b7f] text-2xl font-bold text-white" aria-hidden="true">FO</span>
    <div>
        <p class="m-0 text-xl font-bold"><?= esc($appName) ?></p>
        <p class="m-0 text-muted">Force Ouvrière</p>
    </div>
</div>
<h1 class="text-3xl">Connexion</h1>
<div class="card mt-6">
    <?php if ($m = session('error')): ?>
    <div class="alert alert-error" role="alert"><?= icon('alert', 'size-6 shrink-0') ?><p class="m-0 font-bold"><?= esc($m) ?></p></div>
    <?php elseif ($m = session('info') ?? $info): ?>
    <div class="alert alert-info" role="status"><p class="m-0 font-bold"><?= esc($m) ?></p></div>
    <?php endif ?>
    <form method="post" action="<?= site_url('connexion') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="email">Adresse e-mail</label>
            <input class="input" type="email" id="email" name="email" value="<?= esc($email, 'attr') ?>" autocomplete="username" inputmode="email" required <?= session('error') ? 'aria-invalid="true" aria-describedby="login-err"' : 'autofocus' ?>>
        </div>
        <div class="field" x-data="{ show: false }">
            <label class="label" for="password">Mot de passe</label>
            <div class="flex gap-2">
                <input class="input" :type="show ? 'text' : 'password'" type="password" id="password" name="password" autocomplete="current-password" required>
                <button type="button" class="btn btn-secondary shrink-0" @click="show = !show" :aria-pressed="show.toString()" aria-pressed="false" x-cloak><span x-text="show ? 'Masquer' : 'Afficher'">Afficher</span><span class="sr-only"> le mot de passe</span></button>
            </div>
        </div>
        <label class="mb-6 flex min-h-11 items-center gap-3">
            <input type="checkbox" name="remember" value="1" class="size-5"> Rester connecté sur cet appareil (30 jours)
        </label>
        <button type="submit" class="btn btn-primary w-full text-lg">Se connecter</button>
    </form>
    <?php if (session('error')): ?><p id="login-err" class="sr-only"><?= esc(session('error')) ?></p><?php endif ?>
</div>
<p class="mt-6 text-center text-muted">Mot de passe oublié : adressez-vous à votre secrétaire du syndicat.</p>
<p class="mt-2 text-center text-sm"><a href="<?= site_url('mentions-legales') ?>">Mentions légales</a> · <a href="<?= site_url('cgu') ?>">Conditions d’utilisation</a> · <a href="<?= site_url('confidentialite') ?>">Confidentialité</a></p>
<script src="<?= base_url('assets/js/alpine.min.js?v=' . FO_ASSET_VERSION) ?>" defer></script>
<script>
// Pages gardées pour la consultation hors ligne : effacées dès l'arrivée sur la connexion (autre compte possible).
try { if (window.caches) caches.delete('fo3-pages'); } catch (e) {}
</script>
<?= $this->endSection() ?>
