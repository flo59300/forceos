<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php use App\Domain\Stamps; $v = static fn ($k) => $filters[$k] ?? ''; ?>
<?= view('finances/_tabs', ['financeTabs' => $financeTabs, 'financeTab' => $financeTab]) ?>

<?php if ($pending): ?>
<section class="card" aria-labelledby="t-areverser">
    <h2 id="t-areverser" class="section-title"><?= $isManager ? 'À reverser par personne' : 'À reverser' ?> <span class="text-muted">(<?= $year ?>)</span></h2>
    <ul class="grid gap-2" role="list">
        <?php foreach ($pending as $p): ?>
        <li class="flex flex-wrap items-center justify-between gap-2"><span><?= esc($p['name']) ?> · <?= esc(plural((int) $p['nb'], 'vente')) ?></span><strong><?= money((float) $p['total']) ?></strong></li>
        <?php endforeach ?>
    </ul>
    <a class="btn btn-secondary mt-3" href="<?= site_url('finances/reversements') ?>">Déclarer un reversement</a>
</section>
<?php endif ?>

<form method="get" action="<?= site_url('finances') ?>" class="card mt-6" role="search" aria-label="Filtrer les paiements">
    <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))]">
        <div class="field"><label class="label" for="q">Adhérent</label><input class="input" type="search" id="q" name="q" value="<?= esc($v('q'), 'attr') ?>"></div>
        <div class="field"><label class="label" for="mois">Mois</label><select class="input" id="mois" name="mois"><option value="">Tous</option><?php foreach (Stamps::MONTHS as $n => $l): ?><option value="<?= $n ?>" <?= (string) $v('mois') === (string) $n ? 'selected' : '' ?>><?= esc(ucfirst($l)) ?></option><?php endforeach ?></select></div>
        <div class="field"><label class="label" for="mode">Mode</label><select class="input" id="mode" name="mode"><option value="">Tous</option><?php foreach (Stamps::METHODS as $k => $l): ?><option value="<?= $k ?>" <?= $v('mode') === $k ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?></select></div>
        <?php if ($isManager): ?>
        <div class="field"><label class="label" for="collecteur">Encaissé par</label><select class="input" id="collecteur" name="collecteur"><option value="">Tout le monde</option><?php foreach ($people as $p): ?><option value="<?= $p['id'] ?>" <?= (string) $v('collecteur') === (string) $p['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option><?php endforeach ?></select></div>
        <?php endif ?>
    </div>
    <div class="flex flex-wrap gap-2"><button class="btn btn-primary">Filtrer</button><a class="btn btn-ghost" href="<?= site_url('finances') ?>">Tout effacer</a></div>
</form>

<h2 class="mt-8 text-xl" id="t-paiements"><?= esc(plural($res['total'], 'paiement')) ?> en <?= $year ?> · <?= money($res['amount']) ?></h2>
<?php if (! $res['rows']): ?>
<p class="card mt-3">Aucun paiement ne correspond.</p>
<?php else: ?>
<ul class="mt-3 grid gap-2 md:hidden" role="list">
    <?php foreach ($res['rows'] as $s): ?>
    <li class="rounded-xl border border-line bg-surface p-3">
        <p class="m-0 font-bold"><?= esc($s['m_last'] . ' ' . $s['m_first']) ?> · <?= money((float) $s['amount']) ?></p>
        <p class="m-0 text-muted"><?= $s['type'] === 'carte' ? 'Carte' : 'Timbre de ' . esc(Stamps::MONTHS[(int) $s['month']] ?? '') ?> · <?= esc(Stamps::METHODS[$s['payment_method']] ?? '') ?> · <?= esc(date_fr($s['created_at'], 'd MMM')) ?><?= $isManager && $s['seller'] ? ' · ' . esc($s['seller']) : '' ?><?= (int) $s['is_reversed'] ? ' · reversé' : '' ?></p>
    </li>
    <?php endforeach ?>
</ul>
<div class="card mt-3 hidden overflow-x-auto md:block" role="region" aria-label="Tableau des paiements" tabindex="0">
    <table class="w-full text-left">
        <caption class="sr-only">Paiements <?= $year ?>, du plus récent au plus ancien</caption>
        <thead class="text-muted"><tr><th scope="col" class="py-2 pr-3">Date</th><th scope="col" class="py-2 pr-3">Adhérent</th><th scope="col" class="py-2 pr-3">Objet</th><th scope="col" class="py-2 pr-3 text-right">Montant</th><th scope="col" class="py-2 pr-3">Mode</th><?php if ($isManager): ?><th scope="col" class="py-2 pr-3">Encaissé par</th><?php endif ?><th scope="col" class="py-2">Reversé</th></tr></thead>
        <tbody>
        <?php foreach ($res['rows'] as $s): ?>
        <tr class="border-t border-line">
            <td class="py-2 pr-3"><?= esc(date_fr($s['created_at'], 'dd/MM/y')) ?></td>
            <td class="py-2 pr-3"><a href="<?= site_url('adherents/' . $s['member_id']) ?>"><?= esc($s['m_last'] . ' ' . $s['m_first']) ?></a></td>
            <td class="py-2 pr-3"><?= $s['type'] === 'carte' ? 'Carte' : 'Timbre de ' . esc(Stamps::MONTHS[(int) $s['month']] ?? '') ?></td>
            <td class="py-2 pr-3 text-right tabular-nums"><?= money((float) $s['amount']) ?></td>
            <td class="py-2 pr-3"><?= esc(Stamps::METHODS[$s['payment_method']] ?? '') ?></td>
            <?php if ($isManager): ?><td class="py-2 pr-3"><?= esc($s['seller'] ?? '') ?></td><?php endif ?>
            <td class="py-2"><?= (int) $s['is_reversed'] ? 'Oui' : 'Non' ?></td>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
<?= pager($page, $pages, $filters, 'finances') ?>
<?php endif ?>
<?= $this->endSection() ?>
