<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php use App\Domain\Stamps; $solde = $t['recettes'] - $t['reversements']; ?>
<?= view('finances/_tabs', ['financeTabs' => $financeTabs, 'financeTab' => $financeTab]) ?>

<div class="flex flex-wrap items-end justify-between gap-3">
    <form method="get" action="<?= site_url('finances/tresorerie') ?>" class="flex items-end gap-2">
        <div><label class="label" for="annee">Année</label><select class="input" id="annee" name="annee"><?php for ($y = (int) date('Y') + 1; $y >= 2024; $y--): ?><option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option><?php endfor ?></select></div>
        <button class="btn btn-secondary">Afficher</button>
    </form>
    <a class="btn btn-primary" href="<?= site_url('finances/tresorerie/export?annee=' . $year) ?>">Télécharger l’export comptable (CSV)</a>
</div>

<ul class="mt-6 grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,11rem),1fr))]" role="list">
    <li class="card"><p class="m-0 text-3xl font-bold"><?= money($t['recettes']) ?></p><p class="m-0 text-muted">recettes <?= $year ?></p></li>
    <li class="card"><p class="m-0 text-3xl font-bold"><?= money($t['reversements']) ?></p><p class="m-0 text-muted">reversé à la trésorerie</p></li>
    <li class="card"><p class="m-0 text-3xl font-bold <?= $solde > 0 ? 'text-warning' : '' ?>"><?= money($solde) ?></p><p class="m-0 text-muted">encore chez les collecteurs</p></li>
    <li class="card"><p class="m-0 text-3xl font-bold"><?= (int) $t['timbres'] ?></p><p class="m-0 text-muted">timbres, <?= (int) $t['cartes'] ?> cartes</p></li>
</ul>

<section class="card mt-6" aria-labelledby="t-mois">
    <h2 id="t-mois" class="section-title">Mois par mois</h2>
    <div class="overflow-x-auto" role="region" aria-label="Tableau mensuel de trésorerie (défilement horizontal possible)" tabindex="0">
        <table class="w-full min-w-[36rem] text-left tabular-nums">
            <caption class="sr-only">Recettes, reversements et solde par mois en <?= $year ?></caption>
            <thead class="text-muted"><tr><th scope="col" class="py-2 pr-3">Mois</th><th scope="col" class="py-2 pr-3 text-right">Timbres</th><th scope="col" class="py-2 pr-3 text-right">Cartes</th><th scope="col" class="py-2 pr-3 text-right">Recettes</th><th scope="col" class="py-2 pr-3 text-right">Reversé</th><th scope="col" class="py-2 text-right">Solde</th></tr></thead>
            <tbody>
            <?php foreach ($t['months'] as $n => $m): ?>
            <tr class="border-t border-line">
                <th scope="row" class="py-2 pr-3 font-normal"><?= esc(ucfirst(Stamps::MONTHS[$n])) ?></th>
                <td class="py-2 pr-3 text-right"><?= $m['timbres_nb'] ?: '' ?></td>
                <td class="py-2 pr-3 text-right"><?= $m['cartes_nb'] ?: '' ?></td>
                <td class="py-2 pr-3 text-right"><?= $m['recettes'] ? money($m['recettes']) : '' ?></td>
                <td class="py-2 pr-3 text-right"><?= $m['rev_total'] ? money($m['rev_total']) : '' ?></td>
                <td class="py-2 text-right"><?= ($m['recettes'] || $m['rev_total']) ? money($m['solde']) : '' ?></td>
            </tr>
            <?php endforeach ?>
            </tbody>
            <tfoot><tr class="border-t-2 border-ink font-bold"><th scope="row" class="py-2 pr-3">Total</th><td class="py-2 pr-3 text-right"><?= (int) $t['timbres'] ?></td><td class="py-2 pr-3 text-right"><?= (int) $t['cartes'] ?></td><td class="py-2 pr-3 text-right"><?= money($t['recettes']) ?></td><td class="py-2 pr-3 text-right"><?= money($t['reversements']) ?></td><td class="py-2 text-right"><?= money($solde) ?></td></tr></tfoot>
        </table>
    </div>
</section>

<div class="mt-6 grid gap-6 grid-cols-[repeat(auto-fit,minmax(min(100%,20rem),1fr))]">
    <section class="card" aria-labelledby="t-soldes">
        <h2 id="t-soldes" class="section-title">Soldes par personne</h2>
        <?php if (! $t['collectors']): ?><p class="m-0">Aucune vente cette année.</p><?php else: ?>
        <ul class="grid gap-3" role="list">
            <?php foreach ($t['collectors'] as $c): $d = (float) $c['encaisse'] - (float) $c['reverse']; ?>
            <li><p class="m-0 flex justify-between gap-2"><span class="font-bold"><?= esc($c['name']) ?></span><span class="<?= $d > 0 ? 'font-bold text-warning' : 'text-success' ?>"><?= $d > 0 ? money($d) . ' à reverser' : 'à jour' ?></span></p>
                <p class="m-0 text-muted">Encaissé <?= money((float) $c['encaisse']) ?> · reversé <?= money((float) $c['reverse']) ?></p></li>
            <?php endforeach ?>
        </ul>
        <?php endif ?>
    </section>
    <section class="card" aria-labelledby="t-modes">
        <h2 id="t-modes" class="section-title">Par mode de paiement</h2>
        <?php if (! $t['byPayment']): ?><p class="m-0">Aucune vente cette année.</p><?php else: ?>
        <ul class="grid gap-3" role="list">
            <?php foreach ($t['byPayment'] as $p): $pct = $t['recettes'] > 0 ? (int) round($p['total'] / $t['recettes'] * 100) : 0; ?>
            <li><p class="m-0 flex justify-between"><span><?= esc(Stamps::METHODS[$p['payment_method']] ?? $p['payment_method']) ?></span><span class="font-bold"><?= money((float) $p['total']) ?> (<?= $pct ?> %)</span></p>
                <div class="meter" aria-hidden="true"><span style="width: <?= $pct ?>%"></span></div></li>
            <?php endforeach ?>
        </ul>
        <?php endif ?>
    </section>
</div>
<?= $this->endSection() ?>
