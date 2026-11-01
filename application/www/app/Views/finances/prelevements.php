<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php use App\Domain\Stamps; ?>
<?= view('finances/_tabs', ['financeTabs' => $financeTabs, 'financeTab' => $financeTab]) ?>
<p class="text-lg"><?= esc(plural($nbMembers, 'adhérent payant', 'adhérents payant')) ?> par prélèvement · timbre à <?= money($tarifs['timbre']) ?> · cotisations <?= $year ?></p>

<section class="card mt-4" aria-labelledby="t-generer">
    <h2 id="t-generer" class="section-title">Générer les timbres d’un mois</h2>
    <p class="text-muted">Crée un timbre pour chaque adhérent en prélèvement qui n’en a pas encore pour ce mois, carte comprise au premier timbre. Relancer un mois déjà généré ne crée pas de doublon.</p>
    <form method="post" action="<?= site_url('finances/prelevements/generer') ?>" class="flex flex-wrap items-end gap-3">
        <?= csrf_field() ?>
        <div><label class="label" for="month">Mois</label>
            <select class="input" id="month" name="month">
                <?php foreach (Stamps::MONTHS as $n => $l): ?><option value="<?= $n ?>" <?= $n === (int) date('n') ? 'selected' : '' ?>><?= esc(ucfirst($l)) ?><?= in_array($n, $generated, true) ? ' (déjà généré)' : '' ?></option><?php endforeach ?>
            </select></div>
        <button class="btn btn-primary" <?= $nbMembers ? '' : 'disabled' ?>>Générer</button>
    </form>
    <p class="mt-3 m-0 text-muted">Mois déjà générés : <?= $generated ? esc(Stamps::monthList(array_values(array_unique($generated)))) : 'aucun' ?>.</p>
</section>

<section class="card mt-6" aria-labelledby="t-refus">
    <h2 id="t-refus" class="section-title">Prélèvements refusés par la banque</h2>
    <form method="get" action="<?= site_url('finances/prelevements') ?>" class="mb-4 flex flex-wrap items-end gap-3">
        <div><label class="label" for="mois-refus">Mois</label><select class="input" id="mois-refus" name="mois"><?php foreach (Stamps::MONTHS as $n => $l): ?><option value="<?= $n ?>" <?= $n === $month ? 'selected' : '' ?>><?= esc(ucfirst($l)) ?></option><?php endforeach ?></select></div>
        <button class="btn btn-secondary">Afficher</button>
    </form>
    <?php if (! $ofMonth): ?>
    <p class="m-0">Aucun prélèvement en <?= esc(Stamps::MONTHS[$month]) ?>.</p>
    <?php else: ?>
    <ul class="grid gap-2" role="list">
        <?php foreach ($ofMonth as $p): ?>
        <li class="flex flex-wrap items-center gap-3">
            <span class="min-w-0 flex-1"><?= esc($p['last_name'] . ' ' . $p['first_name']) ?> · <?= money((float) $p['amount']) ?></span>
            <?php if ((int) $p['is_reversed']): ?>
            <span class="badge badge-muted">Reversé</span>
            <?php else: ?>
            <form method="post" action="<?= site_url('finances/prelevements/' . $p['id'] . '/annuler') ?>" onsubmit="return confirm('Annuler ce prélèvement refusé ?')"><?= csrf_field() ?><input type="hidden" name="mois" value="<?= $month ?>"><button class="btn btn-danger">Annuler<span class="sr-only"> le prélèvement de <?= esc($p['first_name'] . ' ' . $p['last_name']) ?></span></button></form>
            <?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>

<section class="mt-6" aria-labelledby="t-semestres">
    <h2 id="t-semestres" class="section-title">Contrôle semestriel</h2>
    <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(min(100%,20rem),1fr))]">
        <?php foreach ([1, 2] as $sem): $c = $checks[$sem]; $v = $validations[$sem] ?? null; ?>
        <div class="card">
            <h3 class="text-lg"><?= $sem === 1 ? '1er semestre (janvier à juin)' : '2e semestre (juillet à décembre)' ?></h3>
            <dl class="mt-2 grid grid-cols-2 gap-2">
                <div><dt class="text-muted">Timbres attendus</dt><dd class="m-0 font-bold"><?= (int) $c['nb_timbres_attendus'] ?></dd></div>
                <div><dt class="text-muted">Présents</dt><dd class="m-0 font-bold"><?= (int) $c['nb_timbres_presents'] ?></dd></div>
                <div><dt class="text-muted">Manquants</dt><dd class="m-0 font-bold <?= $c['nb_manquants'] ? 'text-danger' : 'text-success' ?>"><?= (int) $c['nb_manquants'] ?></dd></div>
                <div><dt class="text-muted">Montant présent</dt><dd class="m-0 font-bold"><?= money((float) $c['montant_present']) ?></dd></div>
            </dl>
            <?php if ($v): ?>
            <p class="mt-3"><span class="badge <?= ['conforme' => 'badge-success', 'anomalies' => 'badge-warning', 'force' => 'badge-info'][$v['status']] ?>"><?= ['conforme' => 'Conforme', 'anomalies' => 'Anomalies', 'force' => 'Validé malgré les anomalies'][$v['status']] ?></span> le <?= esc(date_fr($v['validated_at'], 'd MMMM y')) ?><?= $v['by_name'] ? ' par ' . esc($v['by_name']) : '' ?></p>
            <?php endif ?>
            <form method="post" action="<?= site_url('finances/prelevements/semestre') ?>" class="mt-3">
                <?= csrf_field() ?><input type="hidden" name="semester" value="<?= $sem ?>">
                <div class="field"><label class="label" for="notes-<?= $sem ?>">Note <span class="font-normal text-muted">(facultatif)</span></label><input class="input" type="text" id="notes-<?= $sem ?>" name="notes" maxlength="500"></div>
                <?php if ($c['nb_manquants']): ?><label class="mb-3 flex min-h-11 items-center gap-2"><input type="checkbox" name="force" value="1" class="size-5"> Valider malgré les timbres manquants</label><?php endif ?>
                <button class="btn btn-secondary">Enregistrer le contrôle</button>
            </form>
        </div>
        <?php endforeach ?>
    </div>
</section>
<?= $this->endSection() ?>
