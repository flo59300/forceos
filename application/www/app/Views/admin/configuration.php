<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php $num = static fn ($v) => number_format((float) $v, 2, ',', ''); ?>
<?php if ($isSecretary): ?><p><a href="<?= site_url('administration') ?>">Administration</a></p><?php endif ?>
<h1 class="text-3xl"><?= $isSecretary ? 'Configuration du site' : 'Tarifs' ?></h1>

<?php if (count($tabs) > 1): ?>
<nav class="tabs mt-5 overflow-x-auto" aria-label="Rubriques de la configuration">
    <?php foreach ($tabs as $k => $l): ?><a class="tab whitespace-nowrap" href="<?= site_url('administration/configuration?onglet=' . $k) ?>" <?= $tab === $k ? 'aria-current="page"' : '' ?>><?= esc($l) ?></a><?php endforeach ?>
</nav>
<?php endif ?>

<?php if ($tab === 'site'): ?>
<section class="card mt-4" aria-labelledby="t-tarifs">
    <h2 id="t-tarifs" class="section-title">Tarifs</h2>
    <p class="text-muted">Appliqués au site et à l’année de cotisation courante (<?= $currentYear ?>). Les ventes déjà enregistrées ne changent pas.</p>
    <form method="post" action="<?= site_url('administration/configuration/tarifs') ?>" class="flex flex-wrap items-end gap-4">
        <?= csrf_field() ?>
        <div><label class="label" for="tarif_timbre">Timbre mensuel (€)</label><input class="input max-w-40" type="text" inputmode="decimal" id="tarif_timbre" name="tarif_timbre" value="<?= esc($num($site['tarif_timbre'] ?? 0), 'attr') ?>" required></div>
        <div><label class="label" for="tarif_carte">Carte annuelle (€)</label><input class="input max-w-40" type="text" inputmode="decimal" id="tarif_carte" name="tarif_carte" value="<?= esc($num($site['tarif_carte'] ?? 0), 'attr') ?>" required></div>
        <button class="btn btn-primary">Enregistrer les tarifs</button>
    </form>
</section>

<?php if ($isSecretary): ?>
<section class="card mt-6" aria-labelledby="t-site">
    <h2 id="t-site" class="section-title">Le site</h2>
    <form method="post" action="<?= site_url('administration/configuration/site') ?>">
        <?= csrf_field() ?>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,16rem),1fr))]">
            <div class="field"><label class="label" for="name">Nom du site</label><input class="input" type="text" id="name" name="name" value="<?= esc($site['name'] ?? '', 'attr') ?>" required maxlength="255"></div>
            <div class="field"><label class="label" for="address">Adresse</label><input class="input" type="text" id="address" name="address" value="<?= esc($site['address'] ?? '', 'attr') ?>"></div>
            <div class="field">
                <label class="label" for="seuil">Seuil annuel « à jour »</label>
                <span class="hint" id="aide-seuil">Nombre de timbres dans l’année à partir duquel un adhérent est à jour, quel que soit le mois. Vide : règle du mois courant.</span>
                <input class="input max-w-40" type="number" min="1" max="12" id="seuil" name="seuil" value="<?= esc((string) ($site['seuil_timbres_annuel'] ?? ''), 'attr') ?>" aria-describedby="aide-seuil">
            </div>
            <div class="field">
                <label class="label" for="effectif">Effectif du site</label>
                <span class="hint" id="aide-eff">Nombre de salariés, pour calculer la représentativité.</span>
                <input class="input max-w-40" type="number" min="1" id="effectif" name="effectif" value="<?= esc((string) ($site['effectif_site'] ?? ''), 'attr') ?>" aria-describedby="aide-eff">
            </div>
        </div>
        <label class="mb-5 flex min-h-11 items-center gap-3"><input type="checkbox" name="bulletin_board_enabled" value="1" class="size-5" <?= ! empty($site['bulletin_board_enabled']) ? 'checked' : '' ?>> Afficher le tableau d’annonces</label>
        <button class="btn btn-primary">Enregistrer</button>
    </form>
</section>
<?php endif ?>

<?php elseif ($tab === 'secteurs'): ?>
<section class="card mt-4" aria-labelledby="t-sect">
    <h2 id="t-sect" class="section-title">Arborescence des secteurs</h2>
    <form method="post" action="<?= site_url('administration/configuration/secteurs/ajouter') ?>" class="mb-6 flex flex-wrap items-end gap-3">
        <?= csrf_field() ?>
        <div class="min-w-56 flex-1"><label class="label" for="new-sect">Nouveau secteur</label><input class="input" type="text" id="new-sect" name="name" required maxlength="255"></div>
        <div class="min-w-56 flex-1"><label class="label" for="parent">Rattaché à</label><select class="input" id="parent" name="parent_id"><option value="">Aucun (secteur principal)</option><?php foreach ($secteurs as $s): ?><option value="<?= $s['id'] ?>"><?= str_repeat("\u{00A0}\u{00A0}\u{00A0}", $s['depth']) . esc($s['name']) ?></option><?php endforeach ?></select></div>
        <button class="btn btn-primary">Ajouter</button>
    </form>
    <?php if (! $secteurs): ?><p class="m-0">Aucun secteur.</p><?php else: ?>
    <ul class="grid gap-2" role="list" aria-label="Secteurs">
        <?php foreach ($secteurs as $s): ?>
        <li class="rounded-xl border border-line p-2" style="margin-left: <?= min(4, $s['depth']) * 1.25 ?>rem" x-data="{ edit: false }">
            <div class="flex flex-wrap items-center gap-2" x-show="!edit">
                <span class="min-w-0 flex-1 <?= $s['depth'] === 0 ? 'font-bold' : '' ?>"><?= $s['depth'] ? '<span class="sr-only">Sous-secteur de niveau ' . $s['depth'] . ' : </span>' : '' ?><?= esc($s['name']) ?></span>
                <button type="button" class="btn btn-ghost" @click="edit = true; $nextTick(() => $refs.input.focus())">Renommer<span class="sr-only"> <?= esc($s['name']) ?></span></button>
                <form method="post" action="<?= site_url('administration/configuration/secteurs/supprimer') ?>" onsubmit="return confirm('Retirer ce secteur et ses sous-secteurs ?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-danger">Retirer<span class="sr-only"> <?= esc($s['name']) ?></span></button></form>
            </div>
            <form method="post" action="<?= site_url('administration/configuration/secteurs/renommer') ?>" class="flex flex-wrap items-end gap-2" x-show="edit" x-cloak>
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>">
                <div class="min-w-48 flex-1"><label class="label" for="ren-<?= $s['id'] ?>">Nouveau nom</label><input class="input" x-ref="input" type="text" id="ren-<?= $s['id'] ?>" name="name" value="<?= esc($s['name'], 'attr') ?>" required></div>
                <button class="btn btn-primary">Enregistrer</button><button type="button" class="btn btn-ghost" @click="edit = false">Annuler</button>
            </form>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>

<?php elseif ($tab === 'tournees' || $tab === 'metiers'): $items = $tab === 'tournees' ? $tournees : $metiers; $one = $tab === 'tournees' ? 'tournée' : 'métier'; ?>
<section class="card mt-4" aria-labelledby="t-items">
    <h2 id="t-items" class="section-title"><?= $tab === 'tournees' ? 'Tournées' : 'Métiers' ?></h2>
    <form method="post" action="<?= site_url('administration/configuration/' . $tab . '/ajouter') ?>" class="mb-6 flex flex-wrap items-end gap-3">
        <?= csrf_field() ?>
        <div class="min-w-56 flex-1"><label class="label" for="new-item">Nouveau <?= $one === 'tournée' ? 'nom de tournée' : 'métier' ?></label><input class="input" type="text" id="new-item" name="name" required maxlength="255"></div>
        <?php if ($tab === 'tournees'): ?><div class="min-w-56 flex-1"><label class="label" for="new-desc">Description <span class="font-normal text-muted">(facultatif)</span></label><input class="input" type="text" id="new-desc" name="description" maxlength="255"></div><?php endif ?>
        <button class="btn btn-primary">Ajouter</button>
    </form>
    <?php if (! $items): ?><p class="m-0">Aucun <?= $one === 'tournée' ? 'tournée' : 'métier' ?> pour le moment.</p><?php else: ?>
    <ul class="grid gap-2" role="list">
        <?php foreach ($items as $it): ?>
        <li class="rounded-xl border border-line p-2" x-data="{ edit: false }">
            <div class="flex flex-wrap items-center gap-2" x-show="!edit">
                <span class="min-w-0 flex-1"><strong><?= esc($it['name']) ?></strong><?= ! empty($it['description']) ? ' <span class="text-muted">· ' . esc($it['description']) . '</span>' : '' ?></span>
                <button type="button" class="btn btn-ghost" @click="edit = true; $nextTick(() => $refs.input.focus())">Modifier<span class="sr-only"> <?= esc($it['name']) ?></span></button>
                <form method="post" action="<?= site_url('administration/configuration/' . $tab . '/supprimer') ?>" onsubmit="return confirm('Retirer cet élément ?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $it['id'] ?>"><button class="btn btn-danger">Retirer<span class="sr-only"> <?= esc($it['name']) ?></span></button></form>
            </div>
            <form method="post" action="<?= site_url('administration/configuration/' . $tab . '/modifier') ?>" class="flex flex-wrap items-end gap-2" x-show="edit" x-cloak>
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $it['id'] ?>">
                <div class="min-w-48 flex-1"><label class="label" for="ed-<?= $it['id'] ?>">Nom</label><input class="input" x-ref="input" type="text" id="ed-<?= $it['id'] ?>" name="name" value="<?= esc($it['name'], 'attr') ?>" required></div>
                <?php if ($tab === 'tournees'): ?><div class="min-w-48 flex-1"><label class="label" for="ed-d-<?= $it['id'] ?>">Description</label><input class="input" type="text" id="ed-d-<?= $it['id'] ?>" name="description" value="<?= esc($it['description'] ?? '', 'attr') ?>"></div><?php endif ?>
                <button class="btn btn-primary">Enregistrer</button><button type="button" class="btn btn-ghost" @click="edit = false">Annuler</button>
            </form>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>

<?php elseif ($tab === 'annees'): ?>
<section class="card mt-4" aria-labelledby="t-annees">
    <h2 id="t-annees" class="section-title">Années de cotisation</h2>
    <p class="text-muted">L’année courante est celle sur laquelle les timbres sont encaissés. Changez-la en début d’année, une fois l’année créée.</p>
    <form method="post" action="<?= site_url('administration/configuration/annees/ajouter') ?>" class="mb-6 flex flex-wrap items-end gap-3">
        <?= csrf_field() ?>
        <div><label class="label" for="new-year">Créer l’année</label><input class="input max-w-40" type="number" min="2020" max="2100" id="new-year" name="year" value="<?= (int) date('Y') + 1 ?>" required></div>
        <button class="btn btn-secondary">Créer</button>
    </form>
    <ul class="grid gap-2" role="list">
        <?php foreach ($years as $y): ?>
        <li class="flex flex-wrap items-center gap-3 rounded-xl border border-line p-3">
            <span class="min-w-0 flex-1"><strong><?= (int) $y['year'] ?></strong> <span class="text-muted">· timbre <?= money((float) $y['tarif_timbre']) ?>, carte <?= money((float) $y['tarif_carte']) ?></span></span>
            <?php if ((int) $y['is_current']): ?>
            <span class="badge badge-success">Année courante</span>
            <?php else: ?>
            <form method="post" action="<?= site_url('administration/configuration/annees/courante') ?>" onsubmit="return confirm('Faire de <?= (int) $y['year'] ?> l’année courante ?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $y['id'] ?>"><button class="btn btn-secondary">En faire l’année courante<span class="sr-only"> (<?= (int) $y['year'] ?>)</span></button></form>
            <?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
</section>
<?php endif ?>
<?= $this->endSection() ?>
