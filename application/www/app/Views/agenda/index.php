<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
use App\Domain\Agenda;

$first   = sprintf('%04d-%02d-01', $year, $month);
$label   = ucfirst(date_fr($first, 'MMMM y'));
$days    = (int) date('t', strtotime($first));
$offset  = (int) date('N', strtotime($first)) - 1;
$today   = date('Y-m-d');
$hours   = static fn (array $e) => $e['start_time'] ? substr($e['start_time'], 0, 5) . ($e['end_time'] ? ' à ' . substr($e['end_time'], 0, 5) : '') : 'Toute la journée';
?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-3xl">Agenda</h1>
        <p class="mt-1 text-lg text-muted">Réunions, formations et rendez-vous du site.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php if ($canEdit): ?><a href="<?= site_url('agenda/nouveau') ?>" class="btn btn-primary"><?= icon('plus') ?> Ajouter un événement</a><?php endif ?>
        <a href="<?= site_url('agenda/export.ics') ?>" class="btn btn-secondary">Exporter l’agenda <span class="sr-only">au format iCalendar</span><span aria-hidden="true">(.ics)</span></a>
    </div>
</div>

<?php if ($upcoming): ?>
<section class="card mt-6" aria-labelledby="t-prochains">
    <h2 id="t-prochains" class="section-title">Prochains rendez-vous</h2>
    <ul class="grid gap-2" role="list">
        <?php foreach ($upcoming as $e): ?>
        <li><a href="<?= site_url('agenda/' . $e['id']) ?>" class="todo-card">
            <span class="grid w-14 shrink-0 place-items-center rounded-xl bg-brand-soft py-1 text-brand" aria-hidden="true"><span class="text-sm font-bold uppercase"><?= esc(date_fr($e['event_date'], 'EEE')) ?></span><span class="text-2xl font-bold leading-none"><?= date('j', strtotime($e['event_date'])) ?></span><span class="text-sm"><?= esc(date_fr($e['event_date'], 'MMM')) ?></span></span>
            <span class="min-w-0 flex-1"><span class="block text-lg font-bold"><?= esc($e['title']) ?></span>
                <span class="block text-muted"><span class="sr-only"><?= esc(date_fr($e['event_date'], 'EEEE d MMMM')) ?>, </span><?= esc($hours($e)) ?><?= $e['location'] ? ' · ' . esc($e['location']) : '' ?></span></span>
            <?= view('agenda/_type', ['type' => $e['event_type']]) ?>
        </a></li>
        <?php endforeach ?>
    </ul>
</section>
<?php endif ?>

<nav class="mt-8 flex flex-wrap items-center gap-2" aria-label="Choisir le mois">
    <a class="btn btn-secondary" href="<?= site_url('agenda') . '?mois=' . $prev ?>"><span aria-hidden="true">‹</span> Mois précédent</a>
    <h2 class="m-0 min-w-44 text-center text-2xl" id="t-mois" aria-live="polite"><?= esc($label) ?></h2>
    <a class="btn btn-secondary" href="<?= site_url('agenda') . '?mois=' . $next ?>">Mois suivant <span aria-hidden="true">›</span></a>
    <?php if (! $isCurrent): ?><a class="btn btn-ghost" href="<?= site_url('agenda') ?>">Revenir à aujourd’hui</a><?php endif ?>
</nav>

<!-- Grille du mois (écrans larges). Sur téléphone, la liste ci-dessous suffit. -->
<table class="mt-4 hidden w-full table-fixed border-collapse overflow-hidden rounded-2xl border border-line bg-surface md:table">
    <caption class="sr-only">Calendrier de <?= esc($label) ?> : <?= esc(plural($count, 'événement')) ?></caption>
    <thead><tr>
        <?php foreach (['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'] as $d): ?>
        <th scope="col" class="border-b border-line bg-surface-2 py-2 text-sm font-bold uppercase text-muted"><abbr title="<?= $d ?>" class="no-underline"><?= mb_substr($d, 0, 3) ?></abbr></th>
        <?php endforeach ?>
    </tr></thead>
    <tbody>
    <?php for ($cell = 0; $cell < (int) ceil(($offset + $days) / 7) * 7; $cell++):
        $dnum = $cell - $offset + 1;
        if ($cell % 7 === 0) echo '<tr>';
        if ($dnum < 1 || $dnum > $days): ?>
        <td class="h-28 border border-line bg-surface-2/50"></td>
        <?php else: $date = sprintf('%04d-%02d-%02d', $year, $month, $dnum); $evs = $byDay[$date] ?? []; ?>
        <td class="h-28 border border-line p-1.5 align-top <?= $date === $today ? 'bg-brand-soft' : '' ?>">
            <span class="inline-grid size-7 place-items-center rounded-full text-sm font-bold <?= $date === $today ? 'bg-brand text-brand-ink' : '' ?>"><?= $dnum ?><?= $date === $today ? '<span class="sr-only">, aujourd’hui</span>' : '' ?></span>
            <?php if ($evs): ?>
            <ul class="mt-1 grid gap-1" role="list">
                <?php foreach (array_slice($evs, 0, 3) as $e): ?>
                <li><a href="<?= site_url('agenda/' . $e['id']) ?>" class="block truncate rounded-md bg-surface-2 px-1.5 py-0.5 text-sm font-bold text-ink no-underline hover:bg-brand-soft" title="<?= esc($e['title'], 'attr') ?>"><?= $e['start_time'] ? substr($e['start_time'], 0, 5) . ' ' : '' ?><?= esc($e['title']) ?></a></li>
                <?php endforeach ?>
                <?php if (count($evs) > 3): ?><li><a href="#jour-<?= $date ?>" class="text-sm">et <?= count($evs) - 3 ?> autre<?= count($evs) - 3 > 1 ? 's' : '' ?></a></li><?php endif ?>
            </ul>
            <?php elseif ($canEdit && $date >= $today): ?>
            <a href="<?= site_url('agenda/nouveau') . '?date=' . $date ?>" class="mt-1 flex min-h-8 items-center justify-center rounded-md text-lg text-muted no-underline hover:bg-surface-2"><span aria-hidden="true">+</span><span class="sr-only">Ajouter un événement le <?= esc(date_fr($date, 'd MMMM')) ?></span></a>
            <?php endif ?>
        </td>
        <?php endif;
        if ($cell % 7 === 6) echo '</tr>';
    endfor ?>
    </tbody>
</table>

<!-- Liste du mois -->
<section class="mt-6" aria-labelledby="t-liste">
    <h2 id="t-liste" class="section-title">Événements de <?= esc(date_fr($first, 'MMMM')) ?></h2>
    <?php if (! $byDay): ?>
    <div class="card"><p class="m-0 text-muted">Aucun événement ce mois-ci.<?= $canEdit ? ' Utilisez « Ajouter un événement » pour en créer un.' : '' ?></p></div>
    <?php else: ?>
    <ul class="grid gap-4" role="list">
        <?php foreach ($byDay as $date => $evs): ?>
        <li id="jour-<?= $date ?>" class="scroll-mt-24">
            <h3 class="mb-2 text-lg <?= $date === $today ? 'text-brand' : '' ?>"><?= esc(ucfirst(date_fr($date, 'EEEE d MMMM'))) ?><?= $date === $today ? ' (aujourd’hui)' : '' ?></h3>
            <ul class="grid gap-2" role="list">
                <?php foreach ($evs as $e): ?>
                <li><a href="<?= site_url('agenda/' . $e['id']) ?>" class="todo-card">
                    <span class="w-28 shrink-0 font-bold text-brand"><?= esc($hours($e)) ?></span>
                    <span class="min-w-0 flex-1"><span class="block text-lg font-bold"><?= esc($e['title']) ?></span><?php if ($e['location']): ?><span class="block text-muted"><?= esc($e['location']) ?></span><?php endif ?></span>
                    <?= view('agenda/_type', ['type' => $e['event_type']]) ?>
                </a></li>
                <?php endforeach ?>
            </ul>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>

<?php if ($canEdit): ?>
<section class="card mt-8 max-w-2xl" aria-labelledby="t-import" id="import">
    <h2 id="t-import" class="section-title">Importer un agenda</h2>
    <p class="text-muted">Fichier .ics exporté depuis Outlook, Google Agenda ou l’agenda du téléphone. Les doublons (même titre, même date, même heure) sont ignorés.</p>
    <form method="post" action="<?= site_url('agenda/import') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field"><label class="label" for="ics_file">Fichier iCalendar (.ics, 2 Mo au plus)</label><input class="input" type="file" id="ics_file" name="ics_file" accept=".ics,text/calendar" required></div>
        <button class="btn btn-secondary">Importer</button>
    </form>
</section>
<?php endif ?>
<?= $this->endSection() ?>
