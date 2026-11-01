<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php use App\Domain\Stamps; ?>
<p class="print:hidden"><a href="<?= site_url('administration') ?>">Administration</a></p>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div><h1 class="text-3xl">Rapport <?= $year ?></h1><p class="mt-1 text-lg text-muted"><?= esc($site['name'] ?? '') ?> · établi le <?= esc(date_fr(date('Y-m-d'))) ?></p></div>
    <button type="button" class="btn btn-primary print:hidden" onclick="window.print()">Imprimer</button>
</div>

<section class="card mt-6 break-inside-avoid" aria-labelledby="t-adh">
    <h2 id="t-adh" class="section-title">Adhésions</h2>
    <dl class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,9rem),1fr))]">
        <div><dt class="text-muted">Adhérents actifs</dt><dd class="m-0 text-2xl font-bold"><?= (int) $members ?></dd></div>
        <div><dt class="text-muted">À jour</dt><dd class="m-0 text-2xl font-bold"><?= $count['a_jour'] ?></dd></div>
        <div><dt class="text-muted">En attente</dt><dd class="m-0 text-2xl font-bold"><?= $count['en_attente'] ?></dd></div>
        <div><dt class="text-muted">Sympathisants</dt><dd class="m-0 text-2xl font-bold"><?= $count['sympathisant'] ?></dd></div>
    </dl>
</section>

<section class="card mt-6 break-inside-avoid" aria-labelledby="t-fin">
    <h2 id="t-fin" class="section-title">Finances</h2>
    <dl class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,9rem),1fr))]">
        <div><dt class="text-muted">Encaissé</dt><dd class="m-0 text-2xl font-bold"><?= money((float) $totals['total']) ?></dd></div>
        <div><dt class="text-muted">Timbres</dt><dd class="m-0 text-2xl font-bold"><?= (int) $totals['timbres'] ?></dd></div>
        <div><dt class="text-muted">Cartes</dt><dd class="m-0 text-2xl font-bold"><?= (int) $totals['cartes'] ?></dd></div>
        <div><dt class="text-muted">Reversé</dt><dd class="m-0 text-2xl font-bold"><?= money($reversed) ?></dd></div>
    </dl>
</section>

<section class="card mt-6 break-inside-avoid" aria-labelledby="t-mois">
    <h2 id="t-mois" class="section-title">Timbres par mois</h2>
    <table class="w-full text-left tabular-nums">
        <caption class="sr-only">Nombre et montant des timbres par mois</caption>
        <thead class="text-muted"><tr><th scope="col" class="py-1 pr-3">Mois</th><th scope="col" class="py-1 pr-3 text-right">Timbres</th><th scope="col" class="py-1 text-right">Montant</th></tr></thead>
        <tbody><?php foreach (Stamps::MONTHS as $n => $l): $r = $byMonth[$n] ?? null; ?><tr class="border-t border-line"><th scope="row" class="py-1 pr-3 font-normal"><?= esc(ucfirst($l)) ?></th><td class="py-1 pr-3 text-right"><?= (int) ($r['nb'] ?? 0) ?></td><td class="py-1 text-right"><?= money((float) ($r['total'] ?? 0)) ?></td></tr><?php endforeach ?></tbody>
    </table>
</section>

<section class="card mt-6 break-inside-avoid" aria-labelledby="t-coll">
    <h2 id="t-coll" class="section-title">Détail par personne</h2>
    <?php if (! $bySeller): ?><p class="m-0">Aucune vente.</p><?php else: ?>
    <div class="overflow-x-auto" role="region" aria-label="Détail par personne (défilement horizontal possible)" tabindex="0">
    <table class="w-full min-w-[30rem] text-left tabular-nums">
        <caption class="sr-only">Ventes et sommes non reversées par personne</caption>
        <thead class="text-muted"><tr><th scope="col" class="py-1 pr-3">Personne</th><th scope="col" class="py-1 pr-3 text-right">Timbres</th><th scope="col" class="py-1 pr-3 text-right">Cartes</th><th scope="col" class="py-1 pr-3 text-right">Total</th><th scope="col" class="py-1 text-right">Non reversé</th></tr></thead>
        <tbody><?php foreach ($bySeller as $s): ?><tr class="border-t border-line"><th scope="row" class="py-1 pr-3 font-normal"><?= esc($s['name']) ?></th><td class="py-1 pr-3 text-right"><?= (int) $s['nb_timbres'] ?></td><td class="py-1 pr-3 text-right"><?= (int) $s['nb_cartes'] ?></td><td class="py-1 pr-3 text-right"><?= money((float) $s['total']) ?></td><td class="py-1 text-right"><?= money((float) $s['non_reverse']) ?></td></tr><?php endforeach ?></tbody>
    </table>
    </div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
