<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
use App\Domain\Members;
use App\Domain\Stamps;

$months   = $card['months'];
$unpaid   = array_keys(array_filter($months, static fn ($s) => $s === null));
$paid     = array_filter($months);
$hasCard  = (int) $m['has_card'] === 1;
$nowMonth = (int) date('n');
$dueUnpaid = array_values(array_filter($unpaid, static fn ($mo) => $mo <= $nowMonth || $year < (int) date('Y')));
?>
<p><a href="<?= site_url('adherents') ?>">Retour aux adhérents</a></p>

<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-3xl"><?= esc($m['first_name'] . ' ' . mb_strtoupper($m['last_name'])) ?></h1>
        <p class="mt-2 flex flex-wrap items-center gap-2"><?= status_badge($status['status']) ?>
            <span class="text-muted"><?= esc(implode(' · ', array_filter([$m['secteur_name'], $m['collecteur_name'] ? 'suivi par ' . $m['collecteur_name'] : 'aucun collecteur', $m['payment_mode'] === 'prelevement' ? 'prélèvement' : null]))) ?></span>
        </p>
    </div>
    <a href="<?= site_url('adherents/' . $m['id'] . '/modifier') ?>" class="btn btn-secondary"><?= icon('edit') ?> Modifier la fiche</a>
</div>

<!-- Carte de cotisation et encaissement -->
<section class="card mt-6" aria-labelledby="titre-carte" id="encaisser"
         x-data="{ sel: [], timbre: <?= json_encode($tarifs['timbre']) ?>, carte: <?= json_encode($hasCard ? 0 : $tarifs['carte']) ?>,
                   get total() { return this.sel.length ? this.sel.length * this.timbre + this.carte : 0 },
                   euros(v) { return v.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }) } }">
    <h2 id="titre-carte" class="section-title">Carte de cotisation <?= $year ?></h2>
    <p class="text-muted"><?= count($paid) ?> mois payé<?= count($paid) > 1 ? 's' : '' ?> sur 12 · timbre à <?= money($tarifs['timbre']) ?><?= $hasCard ? ' · carte déjà remise' : ' · la carte (' . money($tarifs['carte']) . ') sera ajoutée au premier timbre' ?></p>

    <form method="post" action="<?= site_url('adherents/' . $m['id'] . '/encaisser') ?>">
        <?= csrf_field() ?>
        <fieldset>
            <legend class="label">Mois à encaisser</legend>
            <ul class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,8.5rem),1fr))] gap-2" role="list">
                <?php foreach ($months as $mo => $sale): $name = ucfirst(Stamps::MONTHS[$mo]); ?>
                <li>
                    <?php if ($sale): ?>
                    <div class="flex min-h-16 flex-col justify-center rounded-xl border-2 border-success bg-success-soft px-3 py-2">
                        <span class="flex items-center gap-1 font-bold"><?= icon('check', 'size-5 text-success') ?><?= esc($name) ?></span>
                        <span class="text-sm">Payé le <?= esc(date_fr($sale['created_at'], 'd/MM')) ?><?= (int) $sale['is_reversed'] ? ', reversé' : '' ?></span>
                    </div>
                    <?php else: ?>
                    <label class="flex min-h-16 cursor-pointer items-center gap-2 rounded-xl border-2 border-dashed border-line px-3 py-2 has-[:checked]:border-solid has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                        <input type="checkbox" name="months[]" value="<?= $mo ?>" x-model.number="sel" class="size-5">
                        <span><span class="block font-bold"><?= esc($name) ?></span><span class="block text-sm text-muted"><?= $mo <= $nowMonth || $year < (int) date('Y') ? 'à payer' : 'à venir' ?></span></span>
                    </label>
                    <?php endif ?>
                </li>
                <?php endforeach ?>
            </ul>
        </fieldset>

        <?php if ($unpaid): ?>
        <div class="mt-3 flex flex-wrap gap-2">
            <?php if ($dueUnpaid): ?>
            <button type="button" class="btn btn-ghost px-0" x-cloak @click="sel = <?= esc(json_encode($dueUnpaid), 'attr') ?>">Cocher les mois en retard (<?= count($dueUnpaid) ?>)</button>
            <?php endif ?>
            <button type="button" class="btn btn-ghost" x-cloak x-show="sel.length" @click="sel = []">Tout décocher</button>
        </div>

        <fieldset class="field mt-4">
            <legend class="label">Mode de paiement</legend>
            <div class="flex flex-wrap gap-2">
                <?php foreach (Stamps::METHODS as $k => $l): ?>
                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border-2 border-line px-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                    <input type="radio" name="method" value="<?= $k ?>" class="size-5" <?= ($m['payment_mode'] === 'prelevement' ? 'prelevement' : 'especes') === $k ? 'checked' : '' ?>> <?= esc($l) ?>
                </label>
                <?php endforeach ?>
            </div>
        </fieldset>

        <div class="flex flex-wrap items-center gap-4 rounded-xl bg-surface-2 p-4">
            <p class="m-0 text-lg" aria-live="polite">
                <span x-show="!sel.length">Cochez les mois payés par l’adhérent.</span>
                <span x-show="sel.length" x-cloak>Total : <strong class="text-2xl" x-text="euros(total)"></strong>
                    <span class="text-muted" x-text="'(' + sel.length + ' timbre' + (sel.length > 1 ? 's' : '') + (carte ? ' + carte' : '') + ')'"></span></span>
            </p>
            <button type="submit" class="btn btn-primary ml-auto text-lg"><?= icon('ticket') ?> Encaisser</button>
        </div>
        <?php else: ?>
        <p class="alert alert-success mt-4"><?= icon('check', 'size-6 shrink-0') ?> Toute l’année <?= $year ?> est payée.</p>
        <?php endif ?>
    </form>

    <?php if ($paid): ?>
    <details class="mt-4">
        <summary class="btn btn-ghost px-0">Corriger une erreur d’encaissement</summary>
        <p class="text-muted">Un timbre déjà reversé à la trésorerie ne peut plus être retiré.</p>
        <ul class="grid gap-2" role="list">
            <?php foreach ($paid as $mo => $sale): ?>
            <li class="flex flex-wrap items-center gap-3">
                <span class="min-w-40"><?= esc(ucfirst(Stamps::MONTHS[$mo])) ?> · <?= money((float) $sale['amount']) ?> · <?= esc(Stamps::METHODS[$sale['payment_method']] ?? '') ?></span>
                <?php if ((int) $sale['is_reversed']): ?>
                <span class="badge badge-muted">Reversé</span>
                <?php else: ?>
                <form method="post" action="<?= site_url('adherents/' . $m['id'] . '/retirer') ?>" onsubmit="return confirm('Retirer le timbre de <?= esc(Stamps::MONTHS[$mo], 'js') ?> ?')">
                    <?= csrf_field() ?><input type="hidden" name="month" value="<?= $mo ?>">
                    <button class="btn btn-danger">Retirer<span class="sr-only"> le timbre de <?= esc(Stamps::MONTHS[$mo]) ?></span></button>
                </form>
                <?php endif ?>
            </li>
            <?php endforeach ?>
        </ul>
    </details>
    <?php endif ?>
</section>

<!-- Coordonnées -->
<section class="card mt-6" aria-labelledby="titre-fiche">
    <h2 id="titre-fiche" class="section-title">Fiche</h2>
    <dl class="grid gap-x-6 gap-y-3 grid-cols-[repeat(auto-fit,minmax(min(100%,15rem),1fr))]">
        <?php foreach ([
            'Téléphone' => $m['phone'] ? '<a href="tel:' . esc(preg_replace('/[^0-9+]/', '', $m['phone']), 'attr') . '">' . esc($m['phone']) . '</a>' : null,
            'E-mail' => $m['email'] ? '<a href="mailto:' . esc($m['email'], 'attr') . '">' . esc($m['email']) . '</a>' : null,
            'Adresse' => esc(trim(implode(' ', array_filter([$m['address'], $m['postal_code'], $m['city']])))) ?: null,
            'Matricule' => esc($m['matricule'] ?? '') ?: null,
            'Métier' => esc($m['metier_name'] ?? '') ?: null,
            'Collège' => esc(Members::COLLEGES[$m['categorie'] ?? ''] ?? '') ?: null,
            'Tournée' => esc($m['tournee_name'] ?? '') ?: null,
            'Adhésion' => $m['date_adhesion'] ? esc(date_fr($m['date_adhesion'])) : null,
            'Entrée dans l’entreprise' => $m['date_entry'] ? esc(date_fr($m['date_entry'])) : null,
        ] as $label => $value): if ($value === null) continue; ?>
        <div><dt class="text-muted"><?= esc($label) ?></dt><dd class="m-0 font-bold"><?= $value ?></dd></div>
        <?php endforeach ?>
    </dl>
    <?php if ($m['notes']): ?><p class="mt-4 whitespace-pre-line rounded-xl bg-surface-2 p-3"><?= esc($m['notes']) ?></p><?php endif ?>
</section>

<?php if ($isSecretary): ?>
<section class="card mt-6" aria-labelledby="titre-gestion">
    <h2 id="titre-gestion" class="section-title">Gestion</h2>
    <div class="grid gap-6 grid-cols-[repeat(auto-fit,minmax(min(100%,18rem),1fr))]">
        <form method="post" action="<?= site_url('adherents/' . $m['id'] . '/transferer') ?>">
            <?= csrf_field() ?>
            <label class="label" for="user_id">Confier à un autre collecteur</label>
            <select class="input" id="user_id" name="user_id">
                <?php foreach ($people as $p): ?><option value="<?= $p['id'] ?>" <?= (int) $p['id'] === (int) $m['assigned_user_id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option><?php endforeach ?>
            </select>
            <button class="btn btn-secondary mt-2"><?= icon('swap') ?> Transférer</button>
        </form>
        <form method="post" action="<?= site_url('adherents/' . $m['id'] . '/radier') ?>" onsubmit="return confirm('Radier cet adhérent ?')">
            <?= csrf_field() ?>
            <label class="label" for="motif">Radier l’adhérent</label>
            <span class="hint" id="aide-motif">Motif facultatif : départ, démission, décès…</span>
            <input class="input" type="text" id="motif" name="motif" maxlength="255" aria-describedby="aide-motif">
            <button class="btn btn-danger mt-2">Radier</button>
        </form>
    </div>
</section>
<?php endif ?>

<?php if ($history): ?>
<section class="card mt-6" aria-labelledby="titre-histo">
    <h2 id="titre-histo" class="section-title">Historique des paiements</h2>
    <ul class="grid gap-2 sm:hidden" role="list">
        <?php foreach (array_slice($history, 0, 60) as $h): ?>
        <li class="rounded-xl border border-line p-3">
            <p class="m-0 font-bold"><?= $h['type'] === 'carte' ? 'Carte ' . (int) $h['year'] : 'Timbre de ' . esc(Stamps::MONTHS[(int) $h['month']] ?? '') . ' ' . (int) $h['year'] ?> · <?= money((float) $h['amount']) ?></p>
            <p class="m-0 text-muted"><?= esc(Stamps::METHODS[$h['payment_method']] ?? '') ?><?= $h['seller'] ? ', par ' . esc($h['seller']) : '' ?><?= (int) $h['is_reversed'] ? ' · reversé' : ' · non reversé' ?></p>
        </li>
        <?php endforeach ?>
    </ul>
    <div class="hidden overflow-x-auto sm:block" role="region" aria-label="Tableau des paiements (défilement horizontal possible)" tabindex="0">
        <table class="w-full text-left">
            <caption class="sr-only">Tous les paiements de l’adhérent, du plus récent au plus ancien</caption>
            <thead class="text-muted"><tr><th scope="col" class="py-2 pr-4">Année</th><th scope="col" class="py-2 pr-4">Objet</th><th scope="col" class="py-2 pr-4">Montant</th><th scope="col" class="py-2 pr-4">Mode</th><th scope="col" class="py-2 pr-4">Encaissé par</th><th scope="col" class="py-2">Reversé</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($history, 0, 60) as $h): ?>
            <tr class="border-t border-line">
                <td class="py-2 pr-4"><?= (int) $h['year'] ?></td>
                <td class="py-2 pr-4"><?= $h['type'] === 'carte' ? 'Carte' : 'Timbre de ' . esc(Stamps::MONTHS[(int) $h['month']] ?? '') ?></td>
                <td class="py-2 pr-4"><?= money((float) $h['amount']) ?></td>
                <td class="py-2 pr-4"><?= esc(Stamps::METHODS[$h['payment_method']] ?? '') ?></td>
                <td class="py-2 pr-4"><?= esc($h['seller'] ?? '') ?></td>
                <td class="py-2"><?= (int) $h['is_reversed'] ? 'Oui' : 'Non' ?></td>
            </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif ?>
<?= $this->endSection() ?>
