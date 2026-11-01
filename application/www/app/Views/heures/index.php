<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
use App\Domain\Delegation;

$errors = session('errors') ?? [];
$fmt    = static fn (int $m) => Delegation::format($m);
$period = sprintf('%d-%02d', $year, $month);
$label  = ucfirst(date_fr(sprintf('%d-%02d-01', $year, $month), 'MMMM y'));
$tone   = $sum['remaining'] < 0 ? 'text-danger' : ($sum['pct'] >= 90 ? 'text-warning' : '');
$defaultDate = $isCurrent ? date('Y-m-d') : sprintf('%d-%02d-01', $year, $month);
?>
<h1 class="text-3xl">Heures de délégation</h1>

<nav class="mt-4 flex flex-wrap items-center gap-2" aria-label="Choisir le mois">
    <a class="btn btn-secondary" href="<?= site_url('heures') . '?mois=' . sprintf('%d-%02d', $prev[0], $prev[1]) ?>"><span aria-hidden="true">‹</span> Mois précédent</a>
    <p class="m-0 min-w-40 text-center text-xl font-bold" aria-live="polite"><?= esc($label) ?></p>
    <a class="btn btn-secondary" href="<?= site_url('heures') . '?mois=' . sprintf('%d-%02d', $next[0], $next[1]) ?>">Mois suivant <span aria-hidden="true">›</span></a>
    <?php if (! $isCurrent): ?><a class="btn btn-ghost" href="<?= site_url('heures') ?>">Revenir au mois en cours</a><?php endif ?>
</nav>

<!-- Bilan du mois -->
<section class="card mt-6" aria-labelledby="titre-bilan">
    <h2 id="titre-bilan" class="section-title">Bilan de <?= esc(date_fr(sprintf('%d-%02d-01', $year, $month), 'MMMM')) ?></h2>
    <p class="m-0 text-4xl font-bold <?= $tone ?>"><?= esc($fmt($sum['remaining'])) ?></p>
    <p class="m-0 text-lg <?= $tone ?: 'text-muted' ?>"><?= $sum['remaining'] < 0 ? 'en dépassement' : 'restantes' ?></p>
    <?php if ($sum['credit'] > 0): ?>
    <div class="meter h-3" role="img" aria-label="<?= min(100, $sum['pct']) ?> pour cent du crédit utilisé"><span class="<?= $sum['remaining'] < 0 ? 'bg-danger!' : '' ?>" style="width: <?= min(100, $sum['pct']) ?>%"></span></div>
    <?php endif ?>
    <dl class="mt-4 grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,10rem),1fr))]">
        <div><dt class="text-muted">Crédit du mois</dt><dd class="m-0 font-bold"><?= esc($fmt($sum['quota'])) ?></dd></div>
        <div><dt class="text-muted">Reliquat et heures reçues</dt><dd class="m-0 font-bold"><?= esc($fmt($sum['carry'])) ?></dd></div>
        <div><dt class="text-muted">Utilisées</dt><dd class="m-0 font-bold"><?= esc($fmt($sum['used'])) ?></dd></div>
        <div><dt class="text-muted">Cédées</dt><dd class="m-0 font-bold"><?= esc($fmt($sum['given'])) ?></dd></div>
    </dl>
    <p class="mt-3 text-muted">Les convocations de la direction ne sont pas décomptées.</p>
</section>

<?php if ($incoming): ?>
<section class="card mt-6 border-2 border-info" aria-labelledby="titre-recus" id="transferts">
    <h2 id="titre-recus" class="section-title">Heures proposées par d’autres délégués</h2>
    <ul class="grid gap-3" role="list">
        <?php foreach ($incoming as $t): ?>
        <li class="flex flex-wrap items-center gap-3">
            <p class="m-0 min-w-0 flex-1"><strong><?= esc($t['from_name']) ?></strong> vous propose <strong><?= esc($fmt((int) $t['minutes'])) ?></strong> pour <?= esc(date_fr(sprintf('%d-%02d-01', $t['year'], $t['month']), 'MMMM y')) ?><?= $t['motif'] ? ' : ' . esc($t['motif']) : '' ?></p>
            <form method="post" action="<?= site_url('heures/transfert/' . $t['id']) ?>" class="flex gap-2">
                <?= csrf_field() ?>
                <button class="btn btn-primary" name="reponse" value="accepter">Accepter</button>
                <button class="btn btn-ghost" name="reponse" value="refuser">Refuser</button>
            </form>
        </li>
        <?php endforeach ?>
    </ul>
</section>
<?php endif ?>

<!-- Saisie -->
<section class="card mt-6" aria-labelledby="titre-saisie" id="saisie" x-data="{ mode: '<?= old('hours') !== null && old('start_time') === '' ? 'duree' : 'plage' ?>' }">
    <h2 id="titre-saisie" class="section-title">Saisir des heures</h2>
    <?= view('partials/form_errors') ?>
    <form method="post" action="<?= site_url('heures') ?>" novalidate>
        <?= csrf_field() ?>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,14rem),1fr))]">
            <div class="field">
                <label class="label" for="entry_date">Date</label>
                <input class="input" type="date"<?= field_attrs('entry_date', $errors) ?> value="<?= esc(old('entry_date', $defaultDate), 'attr') ?>" required>
                <?= field_error('entry_date', $errors) ?>
            </div>
            <div class="field">
                <label class="label" for="motif">Motif</label>
                <select class="input"<?= field_attrs('motif', $errors) ?>>
                    <?php foreach (Delegation::MOTIFS as $k => $l): ?><option value="<?= $k ?>" <?= old('motif', 'terrain') === $k ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?>
                </select>
                <?= field_error('motif', $errors) ?>
            </div>
        </div>
        <fieldset class="field">
            <legend class="label">Comment indiquer le temps ?</legend>
            <div class="flex flex-wrap gap-2">
                <label class="flex min-h-11 items-center gap-2 rounded-xl border-2 border-line px-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft"><input type="radio" value="plage" x-model="mode" class="size-5"> Heure de début et de fin</label>
                <label class="flex min-h-11 items-center gap-2 rounded-xl border-2 border-line px-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft"><input type="radio" value="duree" x-model="mode" class="size-5"> Une durée</label>
            </div>
        </fieldset>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,10rem),1fr))]" x-show="mode === 'plage'">
            <div class="field">
                <label class="label" for="start_time">Début</label>
                <input class="input" type="time" step="300"<?= field_attrs('start_time', $errors) ?> value="<?= esc(old('start_time', ''), 'attr') ?>" :disabled="mode !== 'plage'">
                <?= field_error('start_time', $errors) ?>
            </div>
            <div class="field">
                <label class="label" for="end_time">Fin</label>
                <input class="input" type="time" step="300" id="end_time" name="end_time" value="<?= esc(old('end_time', ''), 'attr') ?>" :disabled="mode !== 'plage'">
            </div>
        </div>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,8rem),1fr))]" x-show="mode === 'duree'" x-cloak>
            <div class="field"><label class="label" for="hours">Heures</label><input class="input" type="number" min="0" max="24" id="hours" name="hours" value="<?= esc(old('hours', '0'), 'attr') ?>" inputmode="numeric" :disabled="mode !== 'duree'"></div>
            <div class="field"><label class="label" for="minutes">Minutes</label><input class="input" type="number" min="0" max="59" step="5" id="minutes" name="minutes" value="<?= esc(old('minutes', '0'), 'attr') ?>" inputmode="numeric" :disabled="mode !== 'duree'"></div>
        </div>
        <div class="field">
            <label class="label" for="description">Détail <span class="font-normal text-muted">(facultatif)</span></label>
            <input class="input" type="text" maxlength="500"<?= field_attrs('description', $errors) ?> value="<?= esc(old('description', ''), 'attr') ?>">
            <?= field_error('description', $errors) ?>
        </div>
        <button type="submit" class="btn btn-primary"><?= icon('plus') ?> Enregistrer la saisie</button>
    </form>
</section>

<!-- Saisies du mois -->
<section class="card mt-6" aria-labelledby="titre-saisies">
    <h2 id="titre-saisies" class="section-title">Saisies de <?= esc(date_fr(sprintf('%d-%02d-01', $year, $month), 'MMMM')) ?> <span class="text-muted">(<?= count($entries) ?>)</span></h2>
    <?php if ($byMotif): ?>
    <p class="text-muted"><?= esc(implode(' · ', array_map(static fn ($b) => (Delegation::MOTIFS[$b['motif']] ?? $b['motif']) . ' ' . $fmt((int) $b['total']), $byMotif))) ?></p>
    <?php endif ?>
    <?php if (! $entries): ?>
    <p>Aucune saisie ce mois-ci.</p>
    <?php else: ?>
    <ul class="grid gap-2" role="list">
        <?php foreach ($entries as $e): $off = $e['motif'] === Delegation::NOT_COUNTED; ?>
        <li class="flex flex-wrap items-center gap-3 rounded-xl border border-line p-3">
            <div class="min-w-0 flex-1">
                <p class="m-0 font-bold"><?= esc(ucfirst(date_fr($e['entry_date'], 'EEEE d MMMM'))) ?> · <?= esc($fmt((int) $e['duration_minutes'])) ?><?= $e['start_time'] ? ' <span class="font-normal text-muted">(' . esc(substr($e['start_time'], 0, 5)) . ' à ' . esc(substr($e['end_time'], 0, 5)) . ')</span>' : '' ?></p>
                <p class="m-0 text-muted"><?= esc(Delegation::MOTIFS[$e['motif']] ?? $e['motif']) ?><?= $e['description'] ? ' · ' . esc($e['description']) : '' ?><?= (int) $e['is_auto'] ? ' · chrono de tournée' : '' ?></p>
            </div>
            <?php if ($off): ?><span class="badge badge-info">Hors quota</span><?php endif ?>
            <form method="post" action="<?= site_url('heures/' . $e['id'] . '/supprimer') ?>" onsubmit="return confirm('Supprimer cette saisie ?')">
                <?= csrf_field() ?><input type="hidden" name="mois" value="<?= $period ?>">
                <button class="grid size-11 place-items-center rounded-xl text-danger hover:bg-danger-soft"><?= icon('trash') ?><span class="sr-only">Supprimer la saisie du <?= esc(date_fr($e['entry_date'], 'd MMMM')) ?></span></button>
            </form>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>

<!-- Réglages -->
<div class="mt-6 grid gap-6 grid-cols-[repeat(auto-fit,minmax(min(100%,20rem),1fr))]">
    <section class="card" aria-labelledby="titre-transfert" id="transfert">
        <h2 id="titre-transfert" class="section-title">Céder des heures</h2>
        <p class="text-muted">Disponible ce mois-ci : <strong><?= esc($fmt($sum['available'])) ?></strong>. Les heures ne partent qu’après acceptation.</p>
        <form method="post" action="<?= site_url('heures/transfert') ?>">
            <?= csrf_field() ?><input type="hidden" name="mois" value="<?= $period ?>">
            <div class="field"><label class="label" for="to">À qui ?</label>
                <select class="input" id="to" name="to"><option value="">Choisir un délégué</option><?php foreach ($people as $p): ?><option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option><?php endforeach ?></select></div>
            <div class="grid grid-cols-2 gap-3">
                <div class="field"><label class="label" for="t-hours">Heures</label><input class="input" type="number" min="0" id="t-hours" name="hours" value="0" inputmode="numeric"></div>
                <div class="field"><label class="label" for="t-minutes">Minutes</label><input class="input" type="number" min="0" max="59" step="5" id="t-minutes" name="minutes" value="0" inputmode="numeric"></div>
            </div>
            <div class="field"><label class="label" for="t-motif">Motif <span class="font-normal text-muted">(facultatif)</span></label><input class="input" type="text" id="t-motif" name="motif" maxlength="255"></div>
            <button class="btn btn-secondary"><?= icon('swap') ?> Proposer</button>
        </form>
        <?php if ($sent): ?>
        <h3 class="mt-5 text-lg">Propositions envoyées</h3>
        <ul class="m-0 mt-2 grid gap-1 p-0" role="list">
            <?php foreach ($sent as $s): ?>
            <li><?= esc($fmt((int) $s['minutes'])) ?> à <?= esc($s['to_name']) ?> : <span class="badge <?= ['pending' => 'badge-warning', 'accepted' => 'badge-success', 'rejected' => 'badge-muted'][$s['status']] ?>"><?= ['pending' => 'En attente', 'accepted' => 'Acceptée', 'rejected' => 'Refusée'][$s['status']] ?></span></li>
            <?php endforeach ?>
        </ul>
        <?php endif ?>
    </section>

    <section class="card" aria-labelledby="titre-credit">
        <h2 id="titre-credit" class="section-title">Mon crédit d’heures</h2>
        <form method="post" action="<?= site_url('heures/credit') ?>">
            <?= csrf_field() ?><input type="hidden" name="mois" value="<?= $period ?>">
            <div class="grid grid-cols-2 gap-3">
                <div class="field"><label class="label" for="q-hours">Heures</label><input class="input" type="number" min="0" id="q-hours" name="hours" value="<?= intdiv($sum['quota'], 60) ?>" inputmode="numeric"></div>
                <div class="field"><label class="label" for="q-minutes">Minutes</label><input class="input" type="number" min="0" max="59" step="5" id="q-minutes" name="minutes" value="<?= $sum['quota'] % 60 ?>" inputmode="numeric"></div>
            </div>
            <fieldset class="field">
                <legend class="label">Appliquer</legend>
                <label class="flex min-h-11 items-center gap-2"><input type="radio" name="scope" value="mois" class="size-5" checked> À <?= esc(date_fr(sprintf('%d-%02d-01', $year, $month), 'MMMM')) ?> seulement</label>
                <label class="flex min-h-11 items-center gap-2"><input type="radio" name="scope" value="annee" class="size-5"> Aux douze mois de <?= $year ?></label>
            </fieldset>
            <button class="btn btn-secondary">Enregistrer le crédit</button>
        </form>
        <form method="post" action="<?= site_url('heures/report') ?>" class="mt-5 border-t border-line pt-4" onsubmit="return confirm('Reporter le reste de ce mois sur le mois suivant ?')">
            <?= csrf_field() ?><input type="hidden" name="mois" value="<?= $period ?>">
            <p class="m-0 mb-2 text-muted">Reste reportable : <strong><?= esc($fmt($sum['available'])) ?></strong></p>
            <button class="btn btn-ghost px-0" <?= $sum['available'] > 0 ? '' : 'disabled' ?>>Reporter le reste sur le mois suivant</button>
        </form>
    </section>
</div>
<?= $this->endSection() ?>
