<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
use App\Domain\ElectoralLists;

$errors = session('errors') ?? [];
$cols   = $pap['colleges'] ?? [];
$v      = static fn (string $f, $d = '') => old($f) ?? $d;
$genre  = static fn (?string $g) => $g === 'F' ? 'Femme' : ($g === 'H' ? 'Homme' : '–');
$noDate = (int) ($stats['no_date'] ?? 0);
$noBirth = (int) ($stats['no_birth'] ?? 0);
?>
<p><a href="<?= site_url('elections') ?>">Élections CSE</a></p>
<h1 class="text-3xl">Listes électorales</h1>
<p class="mt-1 text-lg text-muted">Protocole d’accord préélectoral, électeurs et éligibles parmi les adhérents, règle de parité.</p>

<nav class="tabs mt-5 max-w-2xl" aria-label="Étapes des listes électorales">
    <a class="tab" href="<?= site_url('listes-electorales') ?>" <?= $tab === 'pap' ? 'aria-current="page"' : '' ?>>1. Protocole</a>
    <a class="tab" href="<?= site_url('listes-electorales') ?>?onglet=generer" <?= $tab === 'generer' ? 'aria-current="page"' : '' ?>>2. Générer</a>
    <a class="tab" href="<?= site_url('listes-electorales') ?>?onglet=listes" <?= $tab === 'listes' ? 'aria-current="page"' : '' ?>>3. Listes et parité</a>
</nav>

<?php if ($tab === 'pap'): ?>
<section class="card max-w-4xl" aria-labelledby="t-pap" x-data="{ n: <?= (int) $v('nb_colleges', $pap['nb_colleges'] ?? 3) ?> }">
    <div class="flex flex-wrap items-center gap-2"><h2 id="t-pap" class="section-title m-0">Protocole d’accord préélectoral</h2><?php if ($pap): ?><span class="badge <?= $pap['status'] === 'actif' ? 'badge-success' : 'badge-warning' ?>"><?= $pap['status'] === 'actif' ? 'Actif' : 'Brouillon' ?></span><?php endif ?></div>
    <?= view('partials/form_errors') ?>
    <form method="post" action="<?= site_url('listes-electorales/protocole') ?>" class="mt-4" novalidate>
        <?= csrf_field() ?>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,14rem),1fr))]">
            <div class="field"><label class="label" for="pap_name">Nom</label><input class="input" type="text" id="pap_name" name="pap_name" maxlength="255" value="<?= esc($v('pap_name', $pap['name'] ?? 'PAP CSE ' . date('Y')), 'attr') ?>"></div>
            <div class="field"><label class="label" for="pap_status">Statut</label><select class="input" id="pap_status" name="pap_status"><?php foreach (['brouillon' => 'Brouillon', 'actif' => 'Actif'] as $k => $l): ?><option value="<?= $k ?>" <?= $v('pap_status', $pap['status'] ?? 'brouillon') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
            <div class="field"><label class="label" for="election_date">Date du premier tour</label><input class="input" type="date"<?= field_attrs('election_date', $errors, 'aide-date') ?> value="<?= esc($v('election_date', $pap['election_date'] ?? ''), 'attr') ?>"><span class="hint mt-1" id="aide-date">Date de référence pour l’ancienneté et l’âge.</span><?= field_error('election_date', $errors) ?></div>
            <div class="field"><label class="label" for="election_date_t2">Date du second tour <span class="font-normal text-muted">(éventuel)</span></label><input class="input" type="date"<?= field_attrs('election_date_t2', $errors) ?> value="<?= esc($v('election_date_t2', $pap['election_date_t2'] ?? ''), 'attr') ?>"><?= field_error('election_date_t2', $errors) ?></div>
        </div>
        <fieldset class="field rounded-xl border border-line p-4">
            <legend class="label px-1">Conditions d’électorat et d’éligibilité</legend>
            <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,11rem),1fr))]">
                <div class="field"><label class="label" for="anciennete_electeur">Ancienneté pour voter (mois)</label><input class="input" type="number" min="0" id="anciennete_electeur" name="anciennete_electeur" value="<?= (int) $v('anciennete_electeur', $pap['anciennete_electeur_mois'] ?? 3) ?>"></div>
                <div class="field"><label class="label" for="anciennete_eligible">Ancienneté pour être élu (mois)</label><input class="input" type="number" min="0" id="anciennete_eligible" name="anciennete_eligible" value="<?= (int) $v('anciennete_eligible', $pap['anciennete_eligible_mois'] ?? 12) ?>"></div>
                <div class="field"><label class="label" for="age_electeur">Âge minimum pour voter</label><input class="input" type="number" min="16" id="age_electeur" name="age_electeur" value="<?= (int) $v('age_electeur', $pap['age_min_electeur'] ?? 16) ?>"></div>
                <div class="field"><label class="label" for="age_eligible">Âge minimum pour être élu</label><input class="input" type="number" min="18" id="age_eligible" name="age_eligible" value="<?= (int) $v('age_eligible', $pap['age_min_eligible'] ?? 18) ?>"></div>
            </div>
            <p class="m-0 text-sm text-muted">Par défaut : 3 mois et 16 ans pour voter (art. L2314-18), 12 mois et 18 ans pour être élu (art. L2314-19).</p>
        </fieldset>
        <div class="field"><label class="label" for="nb_colleges">Nombre de collèges</label><select class="input max-w-40" id="nb_colleges" name="nb_colleges" x-model.number="n"><?php for ($i = 1; $i <= 3; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor ?></select></div>
        <?php for ($i = 0; $i < 3; $i++): $c = $cols[$i] ?? []; ?>
        <fieldset class="field rounded-xl border border-line p-4" x-show="n > <?= $i ?>">
            <legend class="label px-1"><?= $i + 1 ?><?= $i === 0 ? 'er' : 'e' ?> collège</legend>
            <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,11rem),1fr))]">
                <div class="field"><label class="label" for="college_name_<?= $i ?>">Nom</label><input class="input" type="text" maxlength="100" id="college_name_<?= $i ?>" name="college_name_<?= $i ?>" value="<?= esc($v("college_name_{$i}", $c['name'] ?? ($i + 1) . ($i === 0 ? 'er' : 'e') . ' collège'), 'attr') ?>"></div>
                <div class="field"><label class="label" for="college_subtitle_<?= $i ?>">Catégories de personnel</label><input class="input" type="text" maxlength="100" id="college_subtitle_<?= $i ?>" name="college_subtitle_<?= $i ?>" placeholder="Ouvriers et employés" value="<?= esc($v("college_subtitle_{$i}", $c['subtitle'] ?? ''), 'attr') ?>"></div>
                <div class="field"><label class="label" for="college_seats_tit_<?= $i ?>">Sièges titulaires</label><input class="input" type="number" min="0" id="college_seats_tit_<?= $i ?>" name="college_seats_tit_<?= $i ?>" value="<?= (int) $v("college_seats_tit_{$i}", $c['seats_tit'] ?? 0) ?>"></div>
                <div class="field"><label class="label" for="college_seats_sup_<?= $i ?>">Sièges suppléants</label><input class="input" type="number" min="0" id="college_seats_sup_<?= $i ?>" name="college_seats_sup_<?= $i ?>" value="<?= (int) $v("college_seats_sup_{$i}", $c['seats_sup'] ?? 0) ?>"></div>
                <div class="field"><label class="label" for="college_part_femmes_<?= $i ?>">Part de femmes sur la liste électorale (%)</label><input class="input" type="text" inputmode="decimal"<?= field_attrs("college_part_femmes_{$i}", $errors, "aide-part-{$i}") ?> value="<?= esc((string) $v("college_part_femmes_{$i}", isset($c['part_femmes']) ? str_replace('.', ',', (string) $c['part_femmes']) : ''), 'attr') ?>"><span class="hint mt-1" id="aide-part-<?= $i ?>">Chiffre du protocole, pour tout le personnel du collège. Vide : calculée sur vos adhérents.</span><?= field_error("college_part_femmes_{$i}", $errors) ?></div>
            </div>
            <fieldset><legend class="font-bold">Fiches adhérents rattachées à ce collège</legend>
                <div class="mt-1 flex flex-wrap gap-2">
                    <?php $checked = (array) $v("college_categories_{$i}", $c['categories'] ?? []); foreach (ElectoralLists::CATEGORIES as $k => $l): ?>
                    <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border-2 border-line px-3 has-[:checked]:border-brand has-[:checked]:bg-brand-soft"><input type="checkbox" class="size-5" name="college_categories_<?= $i ?>[]" value="<?= $k ?>" <?= in_array($k, $checked, true) ? 'checked' : '' ?>> <?= esc($l) ?></label>
                    <?php endforeach ?>
                </div>
            </fieldset>
        </fieldset>
        <?php endfor ?>
        <div class="field"><label class="label" for="syndicats">Syndicats signataires <span class="font-normal text-muted">(séparés par des virgules)</span></label><input class="input" type="text" id="syndicats" name="syndicats" placeholder="FO, CGT, CFDT, CFE-CGC" value="<?= esc($v('syndicats', implode(', ', $pap['syndicats'] ?? [])), 'attr') ?>"></div>
        <div class="field"><label class="label" for="regles">Règles complémentaires <span class="font-normal text-muted">(facultatif)</span></label><textarea class="input min-h-24" id="regles" name="regles"><?= esc($v('regles', $pap['regles_complementaires'] ?? '')) ?></textarea></div>
        <button class="btn btn-primary">Enregistrer le protocole</button>
    </form>
    <?php if ($pap): ?>
    <form method="post" action="<?= site_url('listes-electorales/protocole/supprimer') ?>" class="mt-4" onsubmit="return confirm('Supprimer le protocole et les listes générées ?')"><?= csrf_field() ?><button class="btn btn-ghost text-danger">Supprimer le protocole et les listes</button></form>
    <?php endif ?>
</section>

<?php elseif ($tab === 'generer'): ?>
<section class="card max-w-3xl" aria-labelledby="t-gen">
    <h2 id="t-gen" class="section-title">Générer les listes</h2>
    <?php if (! $pap): ?>
    <p class="m-0">Enregistrez d’abord le <a href="<?= site_url('listes-electorales') ?>">protocole préélectoral</a>.</p>
    <?php else: ?>
    <dl class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,10rem),1fr))]">
        <div><dt class="text-muted">Adhérents</dt><dd class="m-0 text-2xl font-bold"><?= (int) $stats['total'] ?></dd></div>
        <div><dt class="text-muted">Sans date d’entrée</dt><dd class="m-0 text-2xl font-bold <?= $noDate ? 'text-warning' : '' ?>"><?= $noDate ?></dd></div>
        <div><dt class="text-muted">Sans date de naissance</dt><dd class="m-0 text-2xl font-bold <?= $noBirth ? 'text-warning' : '' ?>"><?= $noBirth ?></dd></div>
        <div><dt class="text-muted">Sans collège</dt><dd class="m-0 text-2xl font-bold <?= $stats['no_cat'] ? 'text-warning' : '' ?>"><?= (int) $stats['no_cat'] ?></dd></div>
        <div><dt class="text-muted">Sans genre</dt><dd class="m-0 text-2xl font-bold <?= $stats['no_genre'] ? 'text-warning' : '' ?>"><?= (int) $stats['no_genre'] ?></dd></div>
    </dl>
    <?php if ($noDate || $noBirth || $stats['no_cat'] || $stats['no_genre']): ?>
    <div class="alert alert-info mt-4"><?= icon('alert', 'size-6 shrink-0') ?><div>
        <p class="m-0">Des fiches sont incomplètes. Les adhérents sans date d’entrée ou de naissance restent sur la liste avec la mention « à vérifier » ; ceux sans collège sont rattachés au dernier collège ; la parité est calculée sans ceux dont le genre manque.</p>
        <p class="m-0 mt-1"><a href="<?= site_url('adherents') ?>">Compléter les fiches adhérents</a></p>
    </div></div>
    <?php endif ?>
    <form method="post" action="<?= site_url('listes-electorales/generer') ?>" class="mt-4" onsubmit="return confirm('Générer les listes ? Les listes existantes seront remplacées.')">
        <?= csrf_field() ?>
        <p>Référence : <?= $pap['election_date'] ? 'premier tour du ' . esc(date_fr($pap['election_date'], 'd MMMM y')) : 'date du jour (aucune date de premier tour)' ?>.</p>
        <button class="btn btn-primary">Générer les listes</button>
    </form>
    <?php endif ?>
</section>

<?php else: ?>
<?php if (! $rows): ?>
<div class="card"><p class="m-0">Aucune liste générée. <a href="<?= site_url('listes-electorales') ?>?onglet=generer">Générer les listes</a></p></div>
<?php else: ?>
<div class="flex flex-wrap gap-2">
    <a class="btn btn-secondary" href="<?= site_url('listes-electorales/export/electeurs') ?>">Électeurs (CSV)</a>
    <a class="btn btn-secondary" href="<?= site_url('listes-electorales/export/eligibles') ?>">Éligibles (CSV)</a>
    <button type="button" class="btn btn-ghost" onclick="window.print()">Imprimer</button>
</div>

<?php if ($parity): ?>
<section class="card mt-6" aria-labelledby="t-parite">
    <h2 id="t-parite" class="section-title">Parité femmes et hommes (art. L2314-30)</h2>
    <p class="mt-0">Chaque liste comporte autant de femmes et d’hommes que leur part sur la liste électorale du collège, arrondie à l’entier le plus proche, en alternant jusqu’à épuisement d’un des deux sexes. Si l’arrondi donne zéro pour un sexe, la liste peut tout de même comporter une candidate ou un candidat de ce sexe.</p>
    <div class="overflow-x-auto" role="region" aria-label="Liste des électeurs (défilement horizontal possible)" tabindex="0">
    <table class="w-full min-w-[40rem] border-collapse">
        <caption class="sr-only">Répartition et candidatures exigées par collège</caption>
        <thead><tr class="border-b-2 border-line text-left"><th scope="col" class="p-2">Collège</th><th scope="col" class="p-2 text-right">Électeurs adhérents</th><th scope="col" class="p-2 text-right">Femmes / hommes</th><th scope="col" class="p-2">Part de femmes retenue</th><th scope="col" class="p-2">Liste titulaires</th><th scope="col" class="p-2">Liste suppléants</th></tr></thead>
        <tbody>
        <?php foreach ($parity as $p): ?>
            <tr class="border-b border-line align-top">
                <th scope="row" class="p-2 text-left"><?= esc($p['name']) ?></th>
                <td class="p-2 text-right"><?= $p['total'] ?></td>
                <td class="p-2 text-right"><?= $p['f'] ?> / <?= $p['h'] ?><?= $p['inconnu'] ? '<br><span class="text-sm text-warning">' . esc(plural($p['inconnu'], 'genre inconnu', 'genres inconnus')) . '</span>' : '' ?></td>
                <td class="p-2"><strong><?= str_replace('.', ',', (string) $p['pctF']) ?>&nbsp;%</strong><br><span class="text-sm <?= $p['fromPap'] ? 'text-muted' : 'text-warning' ?>"><?= $p['fromPap'] ? 'selon le protocole' : 'calculée sur les adhérents : indiquez le chiffre du protocole' ?></span></td>
                <td class="p-2"><?= $p['seats_tit'] ? esc(plural($p['tit']['f'], 'femme')) . ', ' . esc(plural($p['tit']['h'], 'homme')) : '–' ?></td>
                <td class="p-2"><?= $p['seats_sup'] ? esc(plural($p['sup']['f'], 'femme')) . ', ' . esc(plural($p['sup']['h'], 'homme')) : '–' ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
    <p class="mt-2 text-sm text-muted">Calcul pour des listes complètes (autant de candidats que de sièges).</p>
</section>
<?php endif ?>

<?php $groups = []; foreach ($rows as $r) { $groups[$r['college_index']]['name'] = $r['college_name']; $groups[$r['college_index']][$r['type']][] = $r; } ksort($groups); ?>
<?php foreach ($groups as $gi => $g): ?>
<section class="card mt-6" aria-labelledby="t-col-<?= $gi ?>">
    <h2 id="t-col-<?= $gi ?>" class="section-title"><?= esc($g['name']) ?></h2>
    <?php foreach (['electeur' => 'Électeurs', 'eligible' => 'Éligibles'] as $type => $label): $list = $g[$type] ?? []; if (! $list) continue;
        $ok = count(array_filter($list, static fn ($r) => (int) $r['is_eligible'] === 1)); $ver = count(array_filter($list, static fn ($r) => str_starts_with((string) $r['exclusion_motif'], 'À vérifier'))); ?>
    <h3 class="mt-4 text-lg"><?= $label ?> : <?= $ok ?><?= $type === 'electeur' && $ok < count($list) ? ', ' . esc(plural(count($list) - $ok, 'exclu')) : '' ?><?= $ver ? ', dont ' . $ver . ' à vérifier' : '' ?></h3>
    <div class="overflow-x-auto" role="region" aria-label="Liste des éligibles (défilement horizontal possible)" tabindex="0">
    <table class="mt-2 w-full min-w-[40rem] border-collapse text-base">
        <caption class="sr-only"><?= esc($label . ', ' . $g['name']) ?></caption>
        <thead><tr class="border-b-2 border-line text-left"><th scope="col" class="p-2">Nom</th><th scope="col" class="p-2">Matricule</th><th scope="col" class="p-2">Genre</th><th scope="col" class="p-2">Secteur</th><th scope="col" class="p-2">Entrée</th><th scope="col" class="p-2 text-right">Ancienneté</th><th scope="col" class="p-2">Situation</th></tr></thead>
        <tbody>
        <?php foreach ($list as $r): $verif = str_starts_with((string) $r['exclusion_motif'], 'À vérifier'); ?>
            <tr class="border-b border-line">
                <th scope="row" class="p-2 text-left"><?= esc($r['member_name']) ?></th>
                <td class="p-2"><?= esc($r['member_matricule'] ?: '–') ?></td>
                <td class="p-2"><?= $genre($r['member_genre']) ?></td>
                <td class="p-2"><?= esc($r['member_secteur'] ?: '–') ?></td>
                <td class="p-2"><?= $r['date_entry'] ? esc(date_fr($r['date_entry'], 'dd/MM/y')) : '–' ?></td>
                <td class="p-2 text-right"><?= $r['date_entry'] ? (int) $r['anciennete_mois'] . '&nbsp;mois' : '–' ?></td>
                <td class="p-2"><?php if (! (int) $r['is_eligible']): ?><span class="badge badge-danger">Exclu</span> <span class="text-sm"><?= esc($r['exclusion_motif']) ?></span><?php elseif ($verif): ?><span class="badge badge-warning">À vérifier</span> <span class="text-sm"><?= esc(mb_substr($r['exclusion_motif'], 13)) ?></span><?php else: ?><span class="badge badge-success"><?= $type === 'electeur' ? 'Inscrit' : 'Éligible' ?></span><?php endif ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
    <?php endforeach ?>
</section>
<?php endforeach ?>
<?php endif ?>
<?php endif ?>
<?= $this->endSection() ?>
