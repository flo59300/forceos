<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
$views = ['' => 'Toutes', 'aujourdhui' => 'Aujourd’hui', 'semaine' => '7 prochains jours', 'attente' => 'Demandes en attente'];
$today = date('Y-m-d');
?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-3xl">Permanences</h1>
        <p class="mt-1 text-lg text-muted">Créneaux d’accueil des salariés, réservables par QR code.</p>
    </div>
    <?php if ($secretary): ?><a href="<?= site_url('permanences/nouvelle') ?>" class="btn btn-primary"><?= icon('plus') ?> Nouvelle permanence</a><?php endif ?>
</div>

<?php if ($secretary): ?>
<section class="mt-6" aria-labelledby="t-chiffres">
    <h2 id="t-chiffres" class="sr-only">Chiffres des permanences à venir</h2>
    <dl class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,9rem),1fr))]">
        <div class="card"><dt class="text-muted">À venir</dt><dd class="m-0 text-3xl font-bold"><?= $stats['upcoming'] ?></dd></div>
        <div class="card"><dt class="text-muted">Demandes à valider</dt><dd class="m-0 text-3xl font-bold <?= $stats['pending'] ? 'text-warning' : '' ?>"><?= $stats['pending'] ?></dd></div>
        <div class="card"><dt class="text-muted">Confirmation envoyée</dt><dd class="m-0 text-3xl font-bold"><?= $stats['email_sent'] ?></dd></div>
        <div class="card"><dt class="text-muted">Confirmées</dt><dd class="m-0 text-3xl font-bold text-success"><?= $stats['confirmed'] ?></dd></div>
        <div class="card"><dt class="text-muted">Remplissage</dt><dd class="m-0 text-3xl font-bold"><?= $stats['fill'] ?>&nbsp;%</dd></div>
    </dl>
</section>
<?php endif ?>

<form method="get" action="<?= site_url('permanences') ?>" class="mt-6 flex flex-wrap items-end gap-3" role="search">
    <div class="min-w-0 flex-1 basis-60"><label class="label" for="q">Rechercher</label><input class="input" type="search" id="q" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Titre, lieu ou date"></div>
    <div><label class="label" for="vue">Afficher</label><select class="input" id="vue" name="vue"><?php foreach ($views as $k => $l): if ($k === 'attente' && ! $secretary) continue; ?><option value="<?= $k ?>" <?= $view === $k ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?></select></div>
    <button class="btn btn-secondary"><?= icon('search') ?> Filtrer</button>
</form>

<section class="mt-6" aria-labelledby="t-avenir">
    <h2 id="t-avenir" class="section-title">À venir<?= ($view || $q) ? ' : ' . esc(plural(count($list), 'résultat')) . ' sur ' . $upcomingCount : '' ?></h2>
    <?php if (! $list): ?>
    <div class="card"><p class="m-0 text-muted"><?= $upcomingCount ? 'Aucune permanence ne correspond à ce filtre.' : 'Aucune permanence à venir.' . ($secretary ? ' Utilisez « Nouvelle permanence » pour en créer une.' : '') ?></p></div>
    <?php else: ?>
    <ul class="grid gap-3" role="list">
        <?php foreach ($list as $p): ?>
        <li><a href="<?= site_url('permanences/' . $p['id']) ?>" class="todo-card <?= $p['perm_date'] === $today ? 'border-2 border-brand' : '' ?> <?= ! $p['active'] ? 'border-dashed bg-surface-2' : '' ?>">
            <span class="grid w-14 shrink-0 place-items-center rounded-xl bg-brand-soft py-1 text-brand" aria-hidden="true"><span class="text-sm font-bold uppercase"><?= esc(date_fr($p['perm_date'], 'EEE')) ?></span><span class="text-2xl font-bold leading-none"><?= date('j', strtotime($p['perm_date'])) ?></span><span class="text-sm"><?= esc(date_fr($p['perm_date'], 'MMM')) ?></span></span>
            <span class="min-w-0 flex-1">
                <span class="block text-lg font-bold"><?= esc($p['title']) ?><span class="sr-only">, <?= esc(date_fr($p['perm_date'], 'EEEE d MMMM')) ?></span></span>
                <span class="block text-muted"><?= esc(plural(count($p['slots']), 'créneau', 'créneaux')) ?><?= $p['slots'] ? ' de ' . substr($p['slots'][0]['start_time'], 0, 5) . ' à ' . substr(end($p['slots'])['end_time'], 0, 5) : '' ?><?= $p['location'] ? ' · ' . esc($p['location']) : '' ?></span>
                <span class="mt-1 flex flex-wrap gap-2">
                    <?php if ($p['perm_date'] === $today): ?><span class="badge badge-info">Aujourd’hui</span><?php endif ?>
                    <span class="badge badge-neutral"><?= $p['total_bookings'] ?>&nbsp;/&nbsp;<?= $p['capacity'] ?> inscrits</span>
                    <?php if ($secretary && $p['pending_count']): ?><span class="badge badge-warning"><?= esc(plural($p['pending_count'], 'demande', 'demandes')) ?> à valider</span><?php endif ?>
                    <?php if ($p['quota_reached']): ?><span class="badge badge-danger">Complet</span><?php endif ?>
                    <?php if ($p['series_token']): ?><span class="badge badge-muted">Série</span><?php endif ?>
                    <?php if (! $p['active']): ?><span class="badge badge-muted">Désactivée</span><?php endif ?>
                </span>
                <span class="meter" role="img" aria-label="Remplie à <?= min(100, $p['fill_pct']) ?> pour cent"><span class="<?= $p['fill_pct'] >= 90 ? 'bg-danger!' : '' ?>" style="width: <?= min(100, $p['fill_pct']) ?>%"></span></span>
            </span>
            <?= icon('arrow', 'size-5 shrink-0 self-center text-brand') ?>
        </a></li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>

<?php if ($past && $secretary): ?>
<details class="mt-8">
    <summary class="btn btn-ghost px-0 text-lg">Permanences passées (<?= count($past) ?>) : <?= esc(plural($pastStats['confirmed'], 'inscrit')) ?>, <?= esc(plural($pastStats['attended'], 'présent')) ?> (<?= $pastStats['rate'] ?>&nbsp;%)</summary>
    <ul class="mt-3 grid gap-2" role="list">
        <?php foreach ($past as $p):
            $att = $p['confirmed_count'] ? (int) round($p['attended_count'] / $p['confirmed_count'] * 100) : 0; ?>
        <li><a href="<?= site_url('permanences/' . $p['id']) ?>" class="todo-card">
            <span class="min-w-0 flex-1"><span class="block font-bold"><?= esc($p['title']) ?>, <?= esc(date_fr($p['perm_date'], 'd MMMM y')) ?></span>
                <span class="block text-muted"><?= $p['confirmed_count'] ?> confirmés sur <?= $p['total_capacity'] ?> places · <?= esc(plural($p['attended_count'], 'présent')) ?><?= $p['confirmed_count'] ? ' (' . $att . '&nbsp;%)' : '' ?></span></span>
            <?= icon('arrow', 'size-5 shrink-0 self-center text-brand') ?>
        </a></li>
        <?php endforeach ?>
    </ul>
</details>
<?php endif ?>
<?= $this->endSection() ?>
