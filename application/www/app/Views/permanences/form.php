<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$v      = static fn (string $f, $d = '') => old($f, $p[$f] ?? $d);
$quota  = old('has_quota', $p ? ($p['max_participants'] ? '1' : '') : '');
$dates  = old('dates') ?: [date('Y-m-d')];
$starts = old('slot_start') ?: ['09:00'];
$ends   = old('slot_end') ?: ['09:30'];
$slots  = array_map(null, (array) $starts, (array) $ends);
?>
<p><a href="<?= $p ? site_url('permanences/' . $p['id']) : site_url('permanences') ?>"><?= $p ? 'Retour à la permanence' : 'Toutes les permanences' ?></a></p>
<h1 class="text-3xl"><?= esc($title) ?></h1>
<div class="card mt-6 max-w-3xl">
    <?= view('partials/form_errors') ?>
    <form method="post" action="<?= $p ? site_url('permanences/' . $p['id']) : site_url('permanences') ?>" novalidate
          x-data="{ quota: <?= $quota ? 'true' : 'false' ?>, dates: <?= esc(json_encode(array_values((array) $dates)), 'attr') ?>, slots: <?= esc(json_encode(array_map(static fn ($s) => ['s' => (string) $s[0], 'e' => (string) $s[1]], $slots)), 'attr') ?> }">
        <?= csrf_field() ?>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,15rem),1fr))]">
            <div class="field"><label class="label" for="title">Titre <span class="font-normal text-muted">(obligatoire)</span></label><input class="input" type="text" maxlength="255" required placeholder="Permanence syndicale"<?= field_attrs('title', $errors) ?> value="<?= esc($v('title'), 'attr') ?>"><?= field_error('title', $errors) ?></div>
            <div class="field"><label class="label" for="location">Lieu <span class="font-normal text-muted">(facultatif)</span></label><input class="input" type="text" maxlength="255" placeholder="Local syndical"<?= field_attrs('location', $errors) ?> value="<?= esc($v('location'), 'attr') ?>"><?= field_error('location', $errors) ?></div>
        </div>

        <?php if ($p): ?>
        <div class="field"><label class="label" for="perm_date">Date</label><input class="input max-w-60" type="date" required<?= field_attrs('perm_date', $errors) ?> value="<?= esc($v('perm_date'), 'attr') ?>"><?= field_error('perm_date', $errors) ?></div>
        <?php else: ?>
        <fieldset class="field" id="dates" aria-describedby="aide-dates<?= isset($errors['dates']) ? ' err-dates' : '' ?>">
            <legend class="label">Date ou dates <span class="font-normal text-muted">(obligatoire)</span></legend>
            <span class="hint" id="aide-dates">Avec plusieurs dates, les salariés choisissent leur jour depuis un seul QR code.</span>
            <ul class="grid gap-2" role="list">
                <?php /* Sans JavaScript : une seule date. Avec : la liste est gérée par Alpine. */ ?>
                <template x-for="(d, i) in dates" :key="i">
                    <li class="flex flex-wrap items-center gap-2">
                        <label class="sr-only" :for="'date-' + i" x-text="'Date ' + (i + 1)"></label>
                        <input class="input max-w-60" type="date" name="dates[]" :id="'date-' + i" x-model="dates[i]" min="<?= date('Y-m-d') ?>" required>
                        <button type="button" class="btn btn-ghost" x-show="dates.length > 1" @click="dates.splice(i, 1)">Retirer<span class="sr-only" x-text="' la date ' + (i + 1)"></span></button>
                    </li>
                </template>
                <noscript><li><label class="sr-only" for="date-0">Date</label><input class="input max-w-60" type="date" name="dates[]" id="date-0" value="<?= esc((string) $dates[0], 'attr') ?>" required></li></noscript>
            </ul>
            <div class="mt-2 flex flex-wrap gap-2" x-cloak>
                <button type="button" class="btn btn-secondary" @click="dates.push('')"><?= icon('plus') ?> Ajouter une date</button>
                <button type="button" class="btn btn-ghost" @click="let l = dates.filter(Boolean).slice(-1)[0]; if (l) { let d = new Date(l + 'T12:00:00'); d.setDate(d.getDate() + 7); dates.push(d.toISOString().slice(0, 10)); }">Même jour la semaine suivante</button>
            </div>
            <?= field_error('dates', $errors) ?>
        </fieldset>

        <fieldset class="field" id="slot_start" aria-describedby="aide-slots<?= isset($errors['slot_start']) ? ' err-slot_start' : '' ?>">
            <legend class="label">Créneaux <span class="font-normal text-muted">(obligatoire)</span></legend>
            <span class="hint" id="aide-slots">Les mêmes créneaux sont créés pour chaque date.</span>
            <ul class="grid gap-2" role="list">
                <template x-for="(s, i) in slots" :key="i">
                    <li class="flex flex-wrap items-end gap-2">
                        <div><label class="label text-base font-normal" :for="'s-' + i" x-text="'Début du créneau ' + (i + 1)"></label><input class="input" type="time" step="900" name="slot_start[]" :id="'s-' + i" x-model="s.s" required></div>
                        <div><label class="label text-base font-normal" :for="'e-' + i" x-text="'Fin du créneau ' + (i + 1)"></label><input class="input" type="time" step="900" name="slot_end[]" :id="'e-' + i" x-model="s.e" required></div>
                        <button type="button" class="btn btn-ghost" x-show="slots.length > 1" @click="slots.splice(i, 1)">Retirer<span class="sr-only" x-text="' le créneau ' + (i + 1)"></span></button>
                    </li>
                </template>
                <noscript><li class="flex gap-2"><input class="input" type="time" name="slot_start[]" value="09:00" aria-label="Début"><input class="input" type="time" name="slot_end[]" value="09:30" aria-label="Fin"></li></noscript>
            </ul>
            <button type="button" class="btn btn-secondary mt-2" x-cloak @click="let l = slots[slots.length - 1]; slots.push({ s: l ? l.e : '', e: '' })"><?= icon('plus') ?> Ajouter un créneau</button>
            <?= field_error('slot_start', $errors) ?>
        </fieldset>
        <div class="field"><label class="label" for="max_bookings">Places par créneau</label><input class="input w-28" type="number" min="1" max="50" id="max_bookings" name="max_bookings" value="<?= esc((string) old('max_bookings', '3'), 'attr') ?>"></div>
        <?php endif ?>

        <div class="field">
            <label class="flex min-h-11 cursor-pointer items-center gap-2"><input type="checkbox" name="has_quota" value="1" class="size-5" x-model="quota" <?= $quota ? 'checked' : '' ?>> Limiter le nombre total d’inscrits</label>
            <div x-show="quota" class="mt-2"><label class="label" for="max_participants">Nombre maximum d’inscrits sur la journée</label><input class="input w-28" type="number" min="1" max="500" id="max_participants" name="max_participants" value="<?= esc((string) old('max_participants', $p['max_participants'] ?? 20), 'attr') ?>"></div>
        </div>
        <div class="field"><label class="label" for="description">Informations pour les salariés <span class="font-normal text-muted">(facultatif)</span></label><textarea class="input min-h-24" maxlength="2000"<?= field_attrs('description', $errors) ?>><?= esc($v('description')) ?></textarea><?= field_error('description', $errors) ?></div>
        <div class="flex flex-wrap gap-3">
            <button class="btn btn-primary"><?= $p ? 'Enregistrer' : 'Créer' ?></button>
            <a href="<?= $p ? site_url('permanences/' . $p['id']) : site_url('permanences') ?>" class="btn btn-ghost">Annuler</a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
