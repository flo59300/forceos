<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$attr   = static fn (string $f, string $hint = '') => ' id="' . $f . '" name="' . $f . '"' . (isset($errors[$f]) ? ' aria-invalid="true" aria-describedby="' . trim($hint . ' err-' . $f) . '"' : ($hint ? ' aria-describedby="' . $hint . '"' : ''));
$err    = static fn (string $f) => isset($errors[$f]) ? '<p id="err-' . $f . '" class="error-text">' . icon('alert', 'size-5 shrink-0 mt-0.5') . esc($errors[$f]) . '</p>' : '';
?>
<p><a href="<?= site_url('questions') ?>">Retour aux questions</a></p>
<h1 class="text-3xl">Poser une question au secrétaire</h1>
<p class="mt-1 text-lg text-muted">Le secrétaire est prévenu tout de suite. Vous serez prévenu à votre tour quand il aura répondu.</p>
<div class="card mt-6 max-w-2xl">
    <?= view('partials/form_errors') ?>
    <form method="post" action="<?= site_url('questions') ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="subject">Sujet <span class="font-normal text-muted">(obligatoire)</span></label>
            <span class="hint" id="aide-sujet">En quelques mots, par exemple : prime de nuit, congés d’été.</span>
            <input class="input" type="text" maxlength="200" required<?= $attr('subject', 'aide-sujet') ?> value="<?= esc(old('subject'), 'attr') ?>">
            <?= $err('subject') ?>
        </div>
        <div class="field">
            <label class="label" for="body">Votre question <span class="font-normal text-muted">(obligatoire)</span></label>
            <textarea class="input min-h-40" required<?= $attr('body') ?>><?= esc(old('body')) ?></textarea>
            <?= $err('body') ?>
        </div>
        <fieldset class="field">
            <legend class="label">Est-ce urgent ?</legend>
            <div class="flex flex-wrap gap-2">
                <label class="flex min-h-11 items-center gap-2 rounded-xl border-2 border-line px-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft"><input type="radio" name="priority" value="normale" class="size-5" <?= old('priority', 'normale') === 'normale' ? 'checked' : '' ?>> Non</label>
                <label class="flex min-h-11 items-center gap-2 rounded-xl border-2 border-line px-4 has-[:checked]:border-danger has-[:checked]:bg-danger-soft"><input type="radio" name="priority" value="urgente" class="size-5" <?= old('priority') === 'urgente' ? 'checked' : '' ?>> Oui, c’est urgent</label>
            </div>
            <?= $err('priority') ?>
        </fieldset>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary">Envoyer la question</button>
            <a href="<?= site_url('questions') ?>" class="btn btn-ghost">Annuler</a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
