<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$v      = static fn (string $f, $d = '') => old($f, $task[$f] ?? $d);
$aria   = static fn (string $f, string $hint = '') => ' id="' . $f . '" name="' . $f . '"' . (isset($errors[$f]) ? ' aria-invalid="true" aria-describedby="' . trim($hint . ' err-' . $f) . '"' : ($hint ? ' aria-describedby="' . $hint . '"' : ''));
$err    = static fn (string $f) => isset($errors[$f]) ? '<p id="err-' . $f . '" class="error-text">' . icon('alert', 'size-5 shrink-0 mt-0.5') . esc($errors[$f]) . '</p>' : '';
$action = $task ? site_url('taches/' . $task['source'] . '/' . $task['id']) : site_url('taches');
?>
<p><a href="<?= site_url('taches') ?>">Retour aux tâches</a></p>
<h1 class="text-3xl"><?= esc($title) ?></h1>
<div class="card mt-6 max-w-2xl">
    <?= view('partials/form_errors') ?>
    <form method="post" action="<?= $action ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="title">Titre de la tâche <span class="font-normal text-muted">(obligatoire)</span></label>
            <input class="input" type="text" maxlength="255" required autocomplete="off"<?= $aria('title') ?> value="<?= esc($v('title'), 'attr') ?>">
            <?= $err('title') ?>
        </div>
        <?php if (! $task || $task['source'] === 'todo'): ?>
        <div class="field">
            <label class="label" for="description">Détail <span class="font-normal text-muted">(facultatif)</span></label>
            <textarea class="input min-h-24" maxlength="500"<?= $aria('description') ?>><?= esc($v('description')) ?></textarea>
            <?= $err('description') ?>
        </div>
        <?php endif ?>
        <fieldset class="field">
            <legend class="label">Priorité</legend>
            <div class="flex flex-wrap gap-2">
                <?php foreach (\App\Domain\Tasks::PRIORITIES as $k => $lbl): ?>
                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border-2 border-line px-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                    <input type="radio" name="priority" value="<?= $k ?>" class="size-5 accent-[var(--brand)]" <?= $v('priority', 'normale') === $k ? 'checked' : '' ?>> <?= esc($lbl) ?>
                </label>
                <?php endforeach ?>
            </div>
            <?= $err('priority') ?>
        </fieldset>
        <div class="field">
            <label class="label" for="due_date">Échéance <span class="font-normal text-muted">(facultatif)</span></label>
            <span class="hint" id="aide-due">Laissez vide s’il n’y a pas de date limite.</span>
            <input class="input max-w-60" type="date"<?= $aria('due_date', 'aide-due') ?> value="<?= esc($v('due_date'), 'attr') ?>">
            <?= $err('due_date') ?>
        </div>
        <?php if ($isSecretary && $people): ?>
        <div class="field">
            <label class="label" for="user_id">Pour qui ?</label>
            <select class="input max-w-md" id="user_id" name="user_id">
                <option value="">Pour moi-même</option>
                <?php foreach ($people as $id => $name): ?>
                <option value="<?= $id ?>" <?= (string) old('user_id') === (string) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <?php endif ?>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary"><?= $task ? 'Enregistrer' : 'Ajouter la tâche' ?></button>
            <a href="<?= site_url('taches') ?>" class="btn btn-ghost">Annuler</a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
