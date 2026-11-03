<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
use App\Domain\Agenda;

$errors = session('errors') ?? [];
$v      = static function (string $f) use ($e, $defaults) {
    $cur = $e[$f] ?? ($defaults[$f] ?? '');
    if (in_array($f, ['start_time', 'end_time'], true) && $cur) {
        $cur = substr($cur, 0, 5);
    }

    return old($f, $cur);
};
$action = $e ? site_url('agenda/' . $e['id']) : site_url('agenda');
?>
<p><a href="<?= $e ? site_url('agenda/' . $e['id']) : site_url('agenda') ?>"><?= $e ? 'Retour à l’événement' : 'Retour à l’agenda' ?></a></p>
<h1 class="text-3xl"><?= esc($title) ?></h1>
<div class="card mt-6 max-w-3xl">
    <?= view('partials/form_errors') ?>
    <?php if (! $e): ?><p class="mt-0 text-muted">Tous les comptes du site seront prévenus dans leurs notifications.</p><?php endif ?>
    <form method="post" action="<?= $action ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="title">Titre <span class="font-normal text-muted">(obligatoire)</span></label>
            <input class="input" type="text" maxlength="255" required autocomplete="off" placeholder="Réunion du syndicat"<?= field_attrs('title', $errors) ?> value="<?= esc($v('title'), 'attr') ?>">
            <?= field_error('title', $errors) ?>
        </div>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))]">
            <div class="field">
                <label class="label" for="event_date">Date <span class="font-normal text-muted">(obligatoire)</span></label>
                <input class="input" type="date" required<?= field_attrs('event_date', $errors) ?> value="<?= esc($v('event_date'), 'attr') ?>">
                <?= field_error('event_date', $errors) ?>
            </div>
            <div class="field">
                <label class="label" for="start_time">Début <span class="font-normal text-muted">(facultatif)</span></label>
                <input class="input" type="time" step="300"<?= field_attrs('start_time', $errors, 'aide-heure') ?> value="<?= esc($v('start_time'), 'attr') ?>">
                <?= field_error('start_time', $errors) ?>
            </div>
            <div class="field">
                <label class="label" for="end_time">Fin <span class="font-normal text-muted">(facultatif)</span></label>
                <input class="input" type="time" step="300"<?= field_attrs('end_time', $errors, 'aide-heure') ?> value="<?= esc($v('end_time'), 'attr') ?>">
                <?= field_error('end_time', $errors) ?>
            </div>
        </div>
        <p class="hint -mt-3" id="aide-heure">Sans heure de début, l’événement dure toute la journée.</p>
        <fieldset class="field">
            <legend class="label">Type</legend>
            <div class="flex flex-wrap gap-2">
                <?php foreach (Agenda::TYPES as $k => $l): ?>
                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border-2 border-line px-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                    <input type="radio" name="event_type" value="<?= $k ?>" class="size-5" <?= ($v('event_type') ?: 'reunion') === $k ? 'checked' : '' ?>> <?= esc($l) ?>
                </label>
                <?php endforeach ?>
            </div>
            <?= field_error('event_type', $errors) ?>
        </fieldset>
        <div class="field">
            <label class="label" for="location">Lieu <span class="font-normal text-muted">(facultatif)</span></label>
            <input class="input" type="text" maxlength="255" placeholder="Local syndical, bâtiment A"<?= field_attrs('location', $errors) ?> value="<?= esc($v('location'), 'attr') ?>">
            <?= field_error('location', $errors) ?>
        </div>
        <div class="field">
            <label class="label" for="description">Détails, ordre du jour <span class="font-normal text-muted">(facultatif)</span></label>
            <textarea class="input min-h-32" maxlength="2000"<?= field_attrs('description', $errors) ?>><?= esc($v('description')) ?></textarea>
            <?= field_error('description', $errors) ?>
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary"><?= $e ? 'Enregistrer' : 'Ajouter à l’agenda' ?></button>
            <a href="<?= $e ? site_url('agenda/' . $e['id']) : site_url('agenda') ?>" class="btn btn-ghost">Annuler</a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
