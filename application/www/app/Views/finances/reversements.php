<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php use App\Domain\Finance; ?>
<?= view('finances/_tabs', ['financeTabs' => $financeTabs, 'financeTab' => $financeTab]) ?>

<section class="card" aria-labelledby="t-declarer">
    <h2 id="t-declarer" class="section-title">Déclarer un reversement <span class="text-muted">(<?= $year ?>)</span></h2>
    <?php if (! $pending): ?>
    <p class="m-0">Il n’y a rien à reverser : toutes les ventes <?= $year ?> sont déjà reversées.</p>
    <?php else: ?>
    <p class="text-muted">Le reversement regroupe toutes les ventes non encore reversées de la personne pour <?= $year ?>.</p>
    <form method="post" action="<?= site_url('finances/reversements') ?>" x-data="{ who: '<?= $isManager ? '' : (int) $pending[0]['sold_by'] ?>' }">
        <?= csrf_field() ?>
        <?php if ($isManager): ?>
        <fieldset class="field">
            <legend class="label">Pour qui ?</legend>
            <div class="grid gap-2">
                <?php foreach ($pending as $p): ?>
                <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border-2 border-line px-4 py-2 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                    <input type="radio" name="user_id" value="<?= $p['sold_by'] ?>" class="size-5" required x-model="who">
                    <span class="flex-1"><?= esc($p['name']) ?> <span class="text-muted">· <?= (int) $p['nb_timbres'] ?> timbres, <?= (int) $p['nb_cartes'] ?> cartes</span></span>
                    <strong><?= money((float) $p['total']) ?></strong>
                </label>
                <?php endforeach ?>
            </div>
        </fieldset>
        <?php else: ?>
        <p class="text-2xl font-bold"><?= money((float) $pending[0]['total']) ?> <span class="text-base font-normal text-muted">(<?= (int) $pending[0]['nb_timbres'] ?> timbres, <?= (int) $pending[0]['nb_cartes'] ?> cartes)</span></p>
        <?php endif ?>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,14rem),1fr))]">
            <div class="field"><label class="label" for="method">Remis en</label><select class="input" id="method" name="method"><?php foreach (Finance::REV_METHODS as $k => $l): ?><option value="<?= $k ?>"><?= esc($l) ?></option><?php endforeach ?></select></div>
            <div class="field"><label class="label" for="reference">Référence <span class="font-normal text-muted">(n° de chèque…)</span></label><input class="input" type="text" id="reference" name="reference" maxlength="100"></div>
        </div>
        <div class="field"><label class="label" for="notes">Note <span class="font-normal text-muted">(facultatif)</span></label><input class="input" type="text" id="notes" name="notes" maxlength="500"></div>
        <button class="btn btn-primary" onclick="return confirm('Enregistrer ce reversement ? Les ventes concernées seront marquées comme reversées.')">Enregistrer le reversement</button>
    </form>
    <?php endif ?>
</section>

<section class="mt-8" aria-labelledby="t-historique">
    <h2 id="t-historique" class="section-title">Reversements déclarés</h2>
    <?php if (! $list['rows']): ?>
    <p class="card">Aucun reversement pour le moment.</p>
    <?php else: ?>
    <ul class="grid gap-2" role="list">
        <?php foreach ($list['rows'] as $r): ?>
        <li class="flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface p-3">
            <div class="min-w-0 flex-1">
                <p class="m-0 font-bold"><?= money((float) $r['amount']) ?> · <?= esc($r['user_name']) ?></p>
                <p class="m-0 text-muted"><?= esc(date_fr($r['created_at'], 'd MMMM y')) ?> · <?= esc(Finance::REV_METHODS[$r['payment_method']] ?? $r['payment_method']) ?> · <?= (int) $r['nb_timbres'] ?> timbres, <?= (int) $r['nb_cartes'] ?> cartes<?= $r['reference'] ? ' · réf. ' . esc($r['reference']) : '' ?></p>
            </div>
            <?php if ($r['validated_at']): ?>
            <span class="badge badge-success">Validé<?= $r['validator_name'] ? ' par ' . esc($r['validator_name']) : '' ?></span>
            <?php elseif ($isManager): ?>
            <form method="post" action="<?= site_url('finances/reversements/' . $r['id'] . '/valider') ?>"><?= csrf_field() ?><button class="btn btn-primary">Valider la réception<span class="sr-only"> de <?= money((float) $r['amount']) ?> de <?= esc($r['user_name']) ?></span></button></form>
            <?php else: ?>
            <span class="badge badge-warning">En attente de validation</span>
            <?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
    <?= pager($page, $pages, [], 'finances/reversements') ?>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
