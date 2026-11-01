<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
use App\Domain\Users;

$errors = session('errors') ?? [];
$v      = static fn (string $f) => old($f, $u[$f] ?? '');
$isSelf = $isSelf ?? false;
$labels = ['sales' => 'encaissements', 'reversements' => 'reversements', 'prelevement_validations' => 'contrôles de prélèvements', 'events' => 'événements', 'permanences' => 'permanences', 'election_simulations' => 'simulations d’élections', 'delegation_entries' => 'saisies d’heures', 'messages' => 'messages envoyés'];
?>
<p><a href="<?= site_url('administration/utilisateurs') ?>">Utilisateurs</a></p>
<h1 class="text-3xl"><?= esc($title) ?></h1>
<div class="card mt-6 max-w-3xl">
    <?= view('partials/form_errors') ?>
    <form method="post" action="<?= $u ? site_url('administration/utilisateurs/' . $u['id']) : site_url('administration/utilisateurs') ?>" novalidate>
        <?= csrf_field() ?>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,15rem),1fr))]">
            <?php foreach (['last_name' => 'Nom', 'first_name' => 'Prénom'] as $f => $l): ?>
            <div class="field"><label class="label" for="<?= $f ?>"><?= $l ?></label><input class="input" type="text"<?= field_attrs($f, $errors) ?> value="<?= esc($v($f), 'attr') ?>" required autocomplete="off"><?= field_error($f, $errors) ?></div>
            <?php endforeach ?>
            <div class="field"><label class="label" for="email">E-mail (identifiant de connexion)</label><input class="input" type="email"<?= field_attrs('email', $errors) ?> value="<?= esc($v('email'), 'attr') ?>" required autocomplete="off"><?= field_error('email', $errors) ?></div>
            <div class="field"><label class="label" for="phone">Téléphone</label><input class="input" type="tel"<?= field_attrs('phone', $errors) ?> value="<?= esc($v('phone'), 'attr') ?>" autocomplete="off"><?= field_error('phone', $errors) ?></div>
        </div>
        <fieldset class="field">
            <legend class="label">Rôle</legend>
            <?php if ($isSelf): ?>
            <p class="m-0"><?= esc(Users::ROLES[$u['role']] ?? $u['role']) ?> <span class="text-muted">(vous ne pouvez pas modifier votre propre rôle)</span></p><input type="hidden" name="role" value="<?= esc($u['role'], 'attr') ?>">
            <?php else: ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach (Users::ROLES as $k => $l): ?><label class="flex min-h-11 items-center gap-2 rounded-xl border-2 border-line px-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft"><input type="radio" name="role" value="<?= $k ?>" class="size-5" <?= ($v('role') ?: 'collecteur') === $k ? 'checked' : '' ?>> <?= esc($l) ?></label><?php endforeach ?>
            </div>
            <?= field_error('role', $errors) ?>
            <?php endif ?>
        </fieldset>
        <div class="field">
            <label class="label" for="member_id">Fiche adhérent liée <span class="font-normal text-muted">(facultatif)</span></label>
            <span class="hint" id="aide-member">Si la personne est elle-même adhérente : son collège apparaît dans la liste des comptes.</span>
            <select class="input" id="member_id" name="member_id" aria-describedby="aide-member"><option value="">Aucune</option><?php foreach ($members as $m): ?><option value="<?= $m['id'] ?>" <?= (string) $v('member_id') === (string) $m['id'] ? 'selected' : '' ?>><?= esc($m['name']) ?></option><?php endforeach ?></select>
        </div>
        <div class="field">
            <label class="label" for="password"><?= $u ? 'Nouveau mot de passe' : 'Mot de passe' ?> <?= $u ? '<span class="font-normal text-muted">(laisser vide pour ne pas le changer)</span>' : '' ?></label>
            <span class="hint" id="aide-password">8 caractères au moins, avec une majuscule, une minuscule et un chiffre.</span>
            <input class="input" type="text"<?= field_attrs('password', $errors, 'aide-password') ?> value="" autocomplete="new-password" <?= $u ? '' : 'required' ?>>
            <?= field_error('password', $errors) ?>
        </div>
        <div class="flex flex-wrap gap-3">
            <button class="btn btn-primary"><?= $u ? 'Enregistrer' : 'Créer le compte' ?></button>
            <a href="<?= site_url('administration/utilisateurs') ?>" class="btn btn-ghost">Annuler</a>
        </div>
    </form>
</div>

<?php if ($u && ! $isSelf): ?>
<section class="card mt-6 max-w-3xl" aria-labelledby="t-suppr">
    <h2 id="t-suppr" class="section-title">Supprimer le compte</h2>
    <?php if (! empty($history)): ?>
    <p>Ce compte ne peut pas être supprimé : cela effacerait aussi <?= esc(implode(', ', array_map(static fn ($t, $n) => $n . ' ' . ($labels[$t] ?? $t), array_keys($history), $history))) ?>. Désactivez-le plutôt : la personne ne pourra plus se connecter et l’historique est conservé.</p>
    <?php else: ?>
    <p>Ce compte n’a aucun historique. Sa suppression est définitive ; ses adhérents éventuels n’auront plus de collecteur.</p>
    <form method="post" action="<?= site_url('administration/utilisateurs/' . $u['id'] . '/supprimer') ?>" onsubmit="return confirm('Supprimer définitivement ce compte ?')"><?= csrf_field() ?><button class="btn btn-danger">Supprimer le compte</button></form>
    <?php endif ?>
</section>
<?php endif ?>
<?= $this->endSection() ?>
