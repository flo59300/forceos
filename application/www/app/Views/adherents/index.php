<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
use App\Domain\Members;
use App\Domain\MemberStatus;

$v   = static fn (string $k) => $filters[$k] ?? '';
$sel = static fn (string $k, $val) => (string) $v($k) === (string) $val ? 'selected' : '';
$query = $filters;
$back  = '/adherents' . ($filters ? '?' . http_build_query(array_filter($filters + ['page' => $page > 1 ? $page : null])) : '');
?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-3xl">Adhérents</h1>
        <p class="mt-1 text-lg text-muted"><?= $isSecretary ? 'Tout le site' : 'Les adhérents que vous suivez' ?> · cotisations <?= $year ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="<?= site_url('adherents/nouveau') ?>" class="btn btn-primary"><?= icon('plus') ?> Nouvel adhérent</a>
        <?php if ($isSecretary): ?><a href="<?= site_url('adherents/radies') ?>" class="btn btn-ghost">Adhérents radiés</a><?php endif ?>
    </div>
</div>

<form method="get" action="<?= site_url('adherents') ?>" class="card mt-6" role="search" aria-label="Rechercher et filtrer les adhérents">
    <label class="label" for="q">Rechercher un adhérent</label>
    <span class="hint" id="aide-q">Nom, prénom ou matricule.</span>
    <div class="flex gap-2">
        <input class="input" type="search" id="q" name="q" value="<?= esc($v('q'), 'attr') ?>" aria-describedby="aide-q" autocomplete="off">
        <button type="submit" class="btn btn-primary shrink-0"><?= icon('search') ?> Rechercher</button>
    </div>
    <details class="mt-4" <?= $activeFilters ? 'open' : '' ?>>
        <summary class="btn btn-ghost px-0">Filtres<?= $activeFilters ? ' (' . $activeFilters . ' actif' . ($activeFilters > 1 ? 's' : '') . ')' : '' ?></summary>
        <div class="mt-3 grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,14rem),1fr))]">
            <div class="field">
                <label class="label" for="statut">Cotisation</label>
                <select class="input" id="statut" name="statut">
                    <option value="">Tous</option>
                    <?php foreach (MemberStatus::LABELS as $k => $l): if ($k === 'radie') continue; ?><option value="<?= $k ?>" <?= $sel('statut', $k) ?>><?= esc($l) ?></option><?php endforeach ?>
                </select>
            </div>
            <div class="field">
                <label class="label" for="secteur">Secteur</label>
                <select class="input" id="secteur" name="secteur">
                    <option value="">Tous</option>
                    <?php foreach ($lists['secteurs'] as $s): ?><option value="<?= $s['id'] ?>" <?= $sel('secteur', $s['id']) ?>><?= str_repeat("\u{00A0}\u{00A0}\u{00A0}", $s['depth']) . esc($s['name']) ?></option><?php endforeach ?>
                </select>
            </div>
            <?php if ($lists['tournees']): ?>
            <div class="field">
                <label class="label" for="tournee">Tournée</label>
                <select class="input" id="tournee" name="tournee">
                    <option value="">Toutes</option>
                    <?php foreach ($lists['tournees'] as $t): ?><option value="<?= $t['id'] ?>" <?= $sel('tournee', $t['id']) ?>><?= esc($t['name']) ?></option><?php endforeach ?>
                </select>
            </div>
            <?php endif ?>
            <?php if ($isSecretary): ?>
            <div class="field">
                <label class="label" for="collecteur">Suivi par</label>
                <select class="input" id="collecteur" name="collecteur">
                    <option value="">Tout le monde</option>
                    <?php foreach ($lists['collecteurs'] as $c): ?><option value="<?= $c['id'] ?>" <?= $sel('collecteur', $c['id']) ?>><?= esc($c['name']) ?></option><?php endforeach ?>
                </select>
            </div>
            <?php endif ?>
            <div class="field">
                <label class="label" for="paiement">Mode de paiement</label>
                <select class="input" id="paiement" name="paiement">
                    <option value="">Tous</option>
                    <?php foreach (Members::PAYMENT_MODES as $k => $l): ?><option value="<?= $k ?>" <?= $sel('paiement', $k) ?>><?= esc($l) ?></option><?php endforeach ?>
                </select>
            </div>
            <div class="field">
                <label class="label" for="college">Collège</label>
                <select class="input" id="college" name="college">
                    <option value="">Tous</option>
                    <?php foreach (Members::COLLEGES as $k => $l): ?><option value="<?= $k ?>" <?= $sel('college', $k) ?>><?= esc($l) ?></option><?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="btn btn-secondary">Appliquer les filtres</button>
            <a href="<?= site_url('adherents') ?>" class="btn btn-ghost">Tout effacer</a>
        </div>
    </details>
</form>

<h2 class="mt-8 text-xl" id="resultats"><?= $total ? esc(plural($total, 'adhérent')) : 'Aucun adhérent' ?><?= ($v('q') !== '' || $activeFilters) ? ' trouvé' . ($total > 1 ? 's' : '') : '' ?></h2>

<?php if (! $rows): ?>
<div class="card fo-vide mt-3"><?= icon(($v('q') !== '' || $activeFilters) ? 'search' : 'users', 'size-8') ?><div><p class="m-0 text-lg font-bold"><?= ($v('q') !== '' || $activeFilters) ? 'Aucun adhérent ne correspond' : 'Aucun adhérent pour le moment' ?></p><p class="m-0 text-muted"><?= ($v('q') !== '' || $activeFilters) ? 'Essayez un autre nom ou retirez des filtres.' : 'Commencez par enregistrer le premier adhérent du syndicat FO.' ?></p><?php if ($v('q') === '' && ! $activeFilters): ?><a class="btn btn-primary mt-3" href="<?= site_url('adherents/nouveau') ?>"><?= icon('plus', 'size-5') ?> Ajouter le premier adhérent</a><?php endif ?></div></div>
<?php else: ?>
<form method="post" action="<?= site_url('adherents/groupe') ?>" id="form-groupe" x-data="{ n: 0, op: '' }" @change="n = $root.querySelectorAll('input[name=\'ids[]\']:checked').length">
    <?= csrf_field() ?>
    <input type="hidden" name="back" value="<?= esc($back, 'attr') ?>">
    <ul class="mt-3 grid gap-2" role="list" aria-labelledby="resultats">
        <?php foreach ($rows as $m): $st = $statuses[(int) $m['id']] ?? ['status' => 'sympathisant', 'nb_paid' => 0]; ?>
        <li class="flex items-center gap-3 rounded-xl border border-line bg-surface p-3">
            <?php if ($isSecretary): ?>
            <input type="checkbox" name="ids[]" value="<?= $m['id'] ?>" id="sel-<?= $m['id'] ?>" class="size-6 shrink-0">
            <label for="sel-<?= $m['id'] ?>" class="sr-only">Sélectionner <?= esc($m['first_name'] . ' ' . $m['last_name']) ?></label>
            <?php endif ?>
            <a href="<?= site_url('adherents/' . $m['id']) ?>" class="flex min-w-0 flex-1 flex-wrap items-center gap-x-4 gap-y-1 no-underline text-ink">
                <span class="min-w-48 flex-1">
                    <span class="block text-lg font-bold text-brand underline underline-offset-2"><?= esc(mb_strtoupper($m['last_name']) . ' ' . $m['first_name']) ?></span>
                    <span class="block text-muted"><?= esc(implode(' · ', array_filter([$m['secteur_name'], $isSecretary ? $m['collecteur_name'] : null, $m['payment_mode'] === 'prelevement' ? 'Prélèvement' : null]))) ?></span>
                </span>
                <span class="flex items-center gap-2"><?= status_badge($st['status']) ?><span class="text-muted"><?= (int) $st['nb_paid'] ?>/12 timbres</span></span>
            </a>
        </li>
        <?php endforeach ?>
    </ul>

    <?php if ($isSecretary): ?>
    <fieldset class="card mt-4">
        <legend class="sr-only">Action groupée</legend>
        <p class="m-0 mb-3 font-bold" aria-live="polite"><span x-text="n">0</span> adhérent(s) sélectionné(s) sur cette page</p>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="label" for="operation">Action groupée</label>
                <select class="input" id="operation" name="operation" x-model="op">
                    <option value="">Choisir une action</option>
                    <option value="collecteur">Confier à un collecteur</option>
                    <option value="secteur">Changer de secteur</option>
                    <?php if ($lists['tournees']): ?><option value="tournee">Changer de tournée</option><?php endif ?>
                    <option value="paiement">Changer le mode de paiement</option>
                    <option value="college">Changer de collège</option>
                    <option value="genre">Indiquer le genre</option>
                    <option value="radier">Radier</option>
                </select>
            </div>
            <div x-show="op === 'collecteur'" x-cloak><label class="label" for="value_collecteur">Collecteur</label><select class="input" id="value_collecteur" name="value_collecteur"><?php foreach ($lists['collecteurs'] as $c): ?><option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option><?php endforeach ?></select></div>
            <div x-show="op === 'secteur'" x-cloak><label class="label" for="value_secteur">Secteur</label><select class="input" id="value_secteur" name="value_secteur"><?php foreach ($lists['secteurs'] as $s): ?><option value="<?= $s['id'] ?>"><?= str_repeat("\u{00A0}\u{00A0}\u{00A0}", $s['depth']) . esc($s['name']) ?></option><?php endforeach ?></select></div>
            <div x-show="op === 'tournee'" x-cloak><label class="label" for="value_tournee">Tournée</label><select class="input" id="value_tournee" name="value_tournee"><?php foreach ($lists['tournees'] as $t): ?><option value="<?= $t['id'] ?>"><?= esc($t['name']) ?></option><?php endforeach ?></select></div>
            <div x-show="op === 'paiement'" x-cloak><label class="label" for="value_paiement">Mode</label><select class="input" id="value_paiement" name="value_paiement"><?php foreach (Members::PAYMENT_MODES as $k => $l): ?><option value="<?= $k ?>"><?= esc($l) ?></option><?php endforeach ?></select></div>
            <div x-show="op === 'college'" x-cloak><label class="label" for="value_college">Collège</label><select class="input" id="value_college" name="value_college"><?php foreach (Members::COLLEGES as $k => $l): ?><option value="<?= $k ?>"><?= esc($l) ?></option><?php endforeach ?></select></div>
            <div x-show="op === 'genre'" x-cloak><label class="label" for="value_genre">Genre</label><select class="input" id="value_genre" name="value_genre"><option value="F">Femme</option><option value="H">Homme</option></select></div>
            <div x-show="op === 'radier'" x-cloak><label class="label" for="value_radier">Motif de radiation</label><input class="input" id="value_radier" name="value_radier" type="text" maxlength="255"></div>
            <button type="submit" class="btn btn-secondary" onclick="return this.form.operation.value !== 'radier' || confirm('Radier les adhérents sélectionnés ?')">Appliquer</button>
        </div>
    </fieldset>
    <?php endif ?>
</form>
<?= pager($page, $pages, $query, 'adherents') ?>
<?php endif ?>
<?= $this->endSection() ?>
