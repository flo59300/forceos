<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<?php
$back  = $series ? current_url() . '?s=' . esc($series, 'url') : null;
$dateL = ucfirst(date_fr($p['perm_date'], 'EEEE d MMMM y'));
$opSr  = $form['op'] === '+' ? 'plus' : 'multiplié par';
?>
<h1 class="text-3xl"><?= esc($p['title']) ?></h1>
<dl class="mt-3 grid gap-1 text-lg">
    <div class="flex gap-2"><dt class="sr-only">Date</dt><dd class="m-0 font-bold"><?= esc($dateL) ?></dd></div>
    <?php if ($p['location']): ?><div class="flex gap-2"><dt class="text-muted">Lieu :</dt><dd class="m-0 font-bold"><?= esc($p['location']) ?></dd></div><?php endif ?>
</dl>
<?php if ($p['description']): ?><p class="mt-2 text-muted"><?= esc($p['description']) ?></p><?php endif ?>
<?php if ($back): ?><p class="mt-3"><a href="<?= $back ?>">Voir les autres dates</a></p><?php endif ?>

<?php if ($done): ?>
<section class="card mt-6 border-2 border-success" role="status" aria-labelledby="t-ok">
    <h2 id="t-ok" class="section-title">Demande enregistrée</h2>
    <p>Votre demande pour le <strong><?= esc(date_fr($p['perm_date'], 'EEEE d MMMM')) ?></strong> a bien été reçue. La suite :</p>
    <ol class="m-0 grid gap-2 pl-6">
        <li>le secrétaire du syndicat valide votre demande ;</li>
        <li>vous recevez un courriel avec un lien ;</li>
        <li>vous ouvrez ce lien et confirmez votre inscription.</li>
    </ol>
</section>
<?php elseif ($past): ?>
<div class="alert alert-info mt-6" role="status"><p class="m-0">Cette permanence a eu lieu le <?= esc(date_fr($p['perm_date'], 'd MMMM y')) ?>. Les réservations sont fermées.</p></div>
<?php elseif ($p['quota_reached']): ?>
<div class="alert alert-info mt-6" role="status"><p class="m-0">Le nombre maximum de participants (<?= (int) $p['max_participants'] ?>) est atteint. Les réservations sont fermées<?= $back ? ' pour cette date' : '' ?>.</p></div>
<?php elseif ($limited): ?>
<div class="alert alert-error mt-6" role="alert"><p class="m-0">Trop de réservations depuis cette connexion. Réessayez dans une heure.</p></div>
<?php else: ?>

<?php if ($p['max_participants']): $left = (int) $p['max_participants'] - $p['total_bookings']; ?>
<p class="mt-4"><?= esc($p['total_bookings'] . ' inscrits sur ' . $p['max_participants']) ?><?= $left > 0 && $left <= 5 ? ', <strong>plus que ' . esc(plural($left, 'place')) . '</strong>' : '' ?>.</p>
<?php endif ?>

<section class="card mt-6" aria-labelledby="t-form">
    <h2 id="t-form" class="section-title">Réserver un créneau</h2>
    <?php if ($error): ?>
    <div class="alert alert-error" role="alert" tabindex="-1" id="erreurs"><?= icon('alert', 'size-6 shrink-0') ?><p class="m-0 font-bold"><?= esc($error) ?></p></div>
    <?php endif ?>
    <form method="post" action="<?= current_url() . '?' . esc($series ? 's=' . $series . '&d=' . $p['perm_date'] : 't=' . $p['token'], 'attr') ?>" id="reservation">
        <input type="hidden" name="_token" value="<?= esc($form['token'], 'attr') ?>">
        <input type="hidden" name="_ts" value="<?= (int) $form['ts'] ?>">
        <input type="hidden" name="_ch" value="<?= esc($form['ch'], 'attr') ?>">
        <div class="absolute -left-[9999px] top-0" aria-hidden="true"><label>Ne pas remplir <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <fieldset class="field">
            <legend class="label">Créneau <span class="font-normal text-muted">(obligatoire)</span></legend>
            <div class="grid gap-2 grid-cols-[repeat(auto-fill,minmax(9rem,1fr))]">
                <?php foreach ($p['slots'] as $s):
                    $full  = (int) $s['nb_bookings'] >= (int) $s['max_bookings'];
                    $avail = (int) $s['max_bookings'] - (int) $s['nb_bookings']; ?>
                <label class="flex min-h-14 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-line p-2 text-center has-[:checked]:border-brand has-[:checked]:bg-brand-soft has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-[var(--focus)] <?= $full ? 'cursor-not-allowed bg-surface-2' : '' ?>">
                    <input type="radio" name="slot_id" value="<?= $s['id'] ?>" class="sr-only peer" required <?= $full ? 'disabled' : '' ?> <?= (string) ($old['slot_id'] ?? '') === (string) $s['id'] ? 'checked' : '' ?>>
                    <span class="font-bold"><?= substr($s['start_time'], 0, 5) ?> à <?= substr($s['end_time'], 0, 5) ?></span>
                    <span class="text-sm <?= $full ? 'text-muted' : 'text-success' ?>"><?= $full ? 'Complet' : esc(plural($avail, 'place')) ?></span>
                </label>
                <?php endforeach ?>
            </div>
        </fieldset>
        <div class="field">
            <label class="label" for="name">Nom et prénom <span class="font-normal text-muted">(obligatoire)</span></label>
            <input class="input" type="text" id="name" name="name" required minlength="3" maxlength="100" autocomplete="name" value="<?= esc($old['name'] ?? '', 'attr') ?>">
        </div>
        <div class="field">
            <label class="label" for="email">Adresse e-mail <span class="font-normal text-muted">(obligatoire)</span></label>
            <span class="hint" id="aide-email">Le lien de confirmation vous sera envoyé à cette adresse.</span>
            <input class="input" type="email" id="email" name="email" required autocomplete="email" aria-describedby="aide-email" value="<?= esc($old['email'] ?? '', 'attr') ?>">
        </div>
        <div class="field">
            <label class="label" for="phone">Téléphone <span class="font-normal text-muted">(facultatif)</span></label>
            <input class="input" type="tel" id="phone" name="phone" maxlength="50" autocomplete="tel" value="<?= esc($old['phone'] ?? '', 'attr') ?>">
        </div>
        <div class="field">
            <label class="label" for="subject">Motif de la visite <span class="font-normal text-muted">(facultatif)</span></label>
            <input class="input" type="text" id="subject" name="subject" maxlength="255" placeholder="Question de cotisation, problème de paie…" value="<?= esc($old['subject'] ?? '', 'attr') ?>">
        </div>
        <div class="field rounded-xl bg-surface-2 p-4">
            <label class="label" for="captcha">Vérification anti-robot : combien font <span aria-hidden="true"><?= $form['a'] ?> <?= $form['op'] ?> <?= $form['b'] ?></span><span class="sr-only"><?= $form['a'] ?> <?= $opSr ?> <?= $form['b'] ?></span> ?</label>
            <input class="input max-w-32 text-center text-xl font-bold" type="text" inputmode="numeric" pattern="[0-9]*" id="captcha" name="_captcha" required autocomplete="off">
        </div>
        <button type="submit" class="btn btn-primary w-full text-lg" id="envoyer">Réserver mon créneau</button>
    </form>
</section>
<script>
(function () {
    var f = document.getElementById('reservation'), b = document.getElementById('envoyer'), e = document.getElementById('erreurs');
    if (e) e.focus();
    if (f) f.addEventListener('submit', function (ev) { if (b.dataset.sent) { ev.preventDefault(); return; } b.dataset.sent = '1'; b.textContent = 'Envoi en cours…'; });
})();
</script>
<?php endif ?>
<p class="mt-10 text-center text-sm text-muted"><?= esc($appName) ?> · Force Ouvrière · <?= esc($p['site_name']) ?></p>
<?= $this->endSection() ?>
