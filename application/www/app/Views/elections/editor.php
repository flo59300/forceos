<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$action = $sim ? site_url('elections/' . $sim['id']) : site_url('elections');
?>
<p><a href="<?= site_url('elections') ?>">Élections CSE</a></p>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-3xl"><?= esc($title) ?></h1>
        <p class="mt-1 text-lg text-muted"><?= $official ? 'Résultat officiel : saisissez les chiffres du procès-verbal.' : 'Simulation : les résultats se mettent à jour à chaque saisie.' ?></p>
    </div>
    <?php if ($sim): ?>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-secondary" href="<?= site_url('elections/' . $sim['id'] . '/imprimer') ?>">Version imprimable</a>
        <a class="btn btn-ghost" href="<?= site_url('elections/nouvelle') . '?copie=' . $sim['id'] ?>">Copier en nouvelle simulation</a>
    </div>
    <?php endif ?>
</div>

<?= view('partials/form_errors') ?>

<form method="post" action="<?= $action ?>" id="cse" novalidate
      x-data="cseEditor(<?= esc(json_encode($state, JSON_UNESCAPED_UNICODE), 'attr') ?>)"
      x-effect="json = JSON.stringify(state); $nextTick(() => { if (window.htmx) htmx.trigger('#calcul', 'recalc') })">
    <?= csrf_field() ?>
    <input type="hidden" name="official" value="<?= $official ? 1 : 0 ?>">
    <input type="hidden" name="state" :value="json" value="<?= esc(json_encode($state, JSON_UNESCAPED_UNICODE), 'attr') ?>">

    <section class="card mt-6" aria-labelledby="t-id">
        <h2 id="t-id" class="section-title"><?= $official ? 'Le scrutin' : 'La simulation' ?></h2>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,16rem),1fr))]">
            <div class="field"><label class="label" for="name">Nom</label><input class="input" type="text" maxlength="255" required placeholder="<?= $official ? 'CSE 2027, premier tour' : 'Hypothèse haute 2027' ?>"<?= field_attrs('name', $errors) ?> value="<?= esc(old('name') ?? ($sim['name'] ?? ''), 'attr') ?>"><?= field_error('name', $errors) ?></div>
            <?php if ($official): ?>
            <div class="field"><label class="label" for="election_date">Date de l’élection</label><input class="input" type="date" required<?= field_attrs('election_date', $errors) ?> value="<?= esc(old('election_date') ?? ($sim['election_date'] ?? ''), 'attr') ?>"><?= field_error('election_date', $errors) ?></div>
            <?php endif ?>
        </div>
    </section>

    <!-- Syndicats -->
    <section class="card mt-6" aria-labelledby="t-syn" id="state">
        <h2 id="t-syn" class="section-title">Organisations syndicales</h2>
        <ul class="grid gap-3" role="list">
            <template x-for="(s, si) in state.syndicats" :key="si">
                <li class="rounded-xl border border-line p-3">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-0 flex-1 basis-48"><label class="label" :for="'syn-' + si" x-text="'Syndicat ' + (si + 1)"></label><input class="input" type="text" maxlength="50" :id="'syn-' + si" x-model="s.nom"></div>
                        <label class="flex min-h-11 cursor-pointer items-center gap-2"><input type="checkbox" class="size-5" x-model="s.categoriel"> Syndicat catégoriel</label>
                        <button type="button" class="btn btn-ghost text-danger" x-show="state.syndicats.length > 2" @click="removeSyndicat(si)">Retirer<span class="sr-only" x-text="' ' + s.nom"></span></button>
                    </div>
                    <fieldset class="mt-2" x-show="s.categoriel">
                        <legend class="font-bold">Collèges où il présente des candidats</legend>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <template x-for="(c, ci) in state.colleges" :key="ci">
                                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border-2 border-line px-3 has-[:checked]:border-brand has-[:checked]:bg-brand-soft"><input type="checkbox" class="size-5" :checked="!s.exclus.includes(ci)" @change="toggleExclu(s, ci, $event.target.checked)"> <span x-text="c.nom"></span></label>
                            </template>
                        </div>
                    </fieldset>
                </li>
            </template>
        </ul>
        <button type="button" class="btn btn-secondary mt-3" x-show="state.syndicats.length < <?= \App\Domain\Cse::MAX_SYNDICATS ?>" @click="addSyndicat()"><?= icon('plus') ?> Ajouter un syndicat</button>
    </section>

    <!-- Collèges -->
    <template x-for="(c, ci) in state.colleges" :key="ci">
        <section class="card mt-6" :aria-labelledby="'t-col-' + ci">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="section-title m-0" :id="'t-col-' + ci" x-text="c.nom + (c.subtitle ? ' (' + c.subtitle + ')' : '')"></h2>
                <button type="button" class="btn btn-ghost text-danger" x-show="state.colleges.length > 1" @click="if (confirm('Retirer ce collège ?')) removeCollege(ci)">Retirer ce collège</button>
            </div>
            <div class="mt-3 grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))]">
                <div class="field"><label class="label" :for="'cn-' + ci">Nom du collège</label><input class="input" type="text" maxlength="100" :id="'cn-' + ci" x-model="c.nom"></div>
                <div class="field"><label class="label" :for="'cs-' + ci">Catégories</label><input class="input" type="text" maxlength="100" :id="'cs-' + ci" x-model="c.subtitle"></div>
                <div class="field"><label class="label" :for="'ci-' + ci">Électeurs inscrits</label><input class="input" type="number" min="0" inputmode="numeric" :id="'ci-' + ci" x-model.number="c.inscrits"></div>
            </div>

            <div class="overflow-x-auto" role="region" :aria-label="'Chiffres du ' + c.nom" aria-label="Chiffres du collège" tabindex="0">
            <table class="w-full min-w-[22rem] border-collapse">
                <caption class="sr-only" x-text="'Chiffres du ' + c.nom"></caption>
                <thead><tr><td></td><th scope="col" class="p-2 text-left" :id="'h-tit-' + ci">Titulaires</th><th scope="col" class="p-2 text-left" :id="'h-sup-' + ci">Suppléants</th></tr></thead>
                <tbody>
                    <template x-for="f in [['sieges', 'Sièges à pourvoir'], ['votants', 'Votants'], ['blancs', 'Bulletins blancs et nuls']]" :key="f[0]">
                        <tr class="border-t border-line">
                            <th scope="row" class="p-2 text-left font-normal" :id="'r-' + f[0] + '-' + ci" x-text="f[1]"></th>
                            <template x-for="t in ['tit', 'sup']" :key="t">
                                <td class="p-2"><input class="input" type="number" min="0" inputmode="numeric" x-model.number="c[t][f[0]]" :readonly="f[0] !== 'sieges' && c.bureaux.length > 0" :aria-labelledby="'r-' + f[0] + '-' + ci + ' h-' + t + '-' + ci"></td>
                            </template>
                        </tr>
                    </template>
                    <tr><th colspan="3" scope="colgroup" class="p-2 pt-4 text-left text-lg">Voix obtenues</th></tr>
                    <template x-for="(s, si) in state.syndicats" :key="si">
                        <tr class="border-t border-line">
                            <th scope="row" class="p-2 text-left" :id="'r-s' + si + '-' + ci"><span x-text="s.nom"></span><span class="block text-sm font-normal text-muted" x-show="excl(s, ci)">pas de liste dans ce collège</span></th>
                            <template x-for="t in ['tit', 'sup']" :key="t">
                                <td class="p-2"><input class="input" type="number" min="0" inputmode="numeric" x-model.number="c[t].votes[si]" :disabled="excl(s, ci)" :readonly="c.bureaux.length > 0" :aria-labelledby="'r-s' + si + '-' + ci + ' h-' + t + '-' + ci"></td>
                            </template>
                        </tr>
                    </template>
                </tbody>
            </table>
            </div>
            <p class="mt-2 text-muted" x-show="c.bureaux.length > 0">Les votants, les blancs et les voix sont la somme des bureaux de vote ci-dessous.</p>

            <details class="mt-4" :open="c.bureaux.length > 0">
                <summary class="btn btn-ghost px-0">Saisie par bureau de vote (<span x-text="c.bureaux.length"></span>)</summary>
                <template x-for="(b, bi) in c.bureaux" :key="bi">
                    <div class="mt-3 rounded-xl border border-line p-3">
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="min-w-0 flex-1 basis-48"><label class="label" :for="'bn-' + ci + '-' + bi">Nom du bureau</label><input class="input" type="text" maxlength="60" :id="'bn-' + ci + '-' + bi" x-model="b.name"></div>
                            <button type="button" class="btn btn-ghost text-danger" @click="c.bureaux.splice(bi, 1); sync(c)">Retirer<span class="sr-only" x-text="' ' + b.name"></span></button>
                        </div>
                        <div class="overflow-x-auto" role="region" :aria-label="'Voix du bureau ' + b.name + ', collège ' + (ci + 1)" aria-label="Voix du bureau" tabindex="0">
                        <table class="mt-2 w-full min-w-[22rem] border-collapse">
                            <caption class="sr-only" x-text="b.name"></caption>
                            <thead><tr><td></td><th scope="col" class="p-2 text-left" :id="'bh-tit-' + ci + '-' + bi">Titulaires</th><th scope="col" class="p-2 text-left" :id="'bh-sup-' + ci + '-' + bi">Suppléants</th></tr></thead>
                            <tbody>
                                <template x-for="f in [['votants', 'Votants'], ['blancs', 'Blancs et nuls']]" :key="f[0]">
                                    <tr><th scope="row" class="p-2 text-left font-normal" :id="'br-' + f[0] + '-' + ci + '-' + bi" x-text="f[1]"></th>
                                        <template x-for="t in ['tit', 'sup']" :key="t"><td class="p-2"><input class="input" type="number" min="0" inputmode="numeric" x-model.number="b[t][f[0]]" @input="sync(c)" :aria-labelledby="'br-' + f[0] + '-' + ci + '-' + bi + ' bh-' + t + '-' + ci + '-' + bi"></td></template>
                                    </tr>
                                </template>
                                <template x-for="(s, si) in state.syndicats" :key="si">
                                    <tr x-show="!excl(s, ci)"><th scope="row" class="p-2 text-left font-normal" :id="'bs-' + si + '-' + ci + '-' + bi" x-text="'Voix ' + s.nom"></th>
                                        <template x-for="t in ['tit', 'sup']" :key="t"><td class="p-2"><input class="input" type="number" min="0" inputmode="numeric" x-model.number="b[t].votes[si]" @input="sync(c)" :aria-labelledby="'bs-' + si + '-' + ci + '-' + bi + ' bh-' + t + '-' + ci + '-' + bi"></td></template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </template>
                <button type="button" class="btn btn-secondary mt-3" @click="addBureau(c)"><?= icon('plus') ?> Ajouter un bureau de vote</button>
            </details>
        </section>
    </template>
    <button type="button" class="btn btn-secondary mt-4" x-show="state.colleges.length < <?= \App\Domain\Cse::MAX_COLLEGES ?>" @click="addCollege()"><?= icon('plus') ?> Ajouter un collège</button>

    <div class="mt-6 flex flex-wrap gap-3">
        <button class="btn btn-primary"><?= $sim ? 'Enregistrer les modifications' : 'Enregistrer' ?></button>
        <button type="button" class="btn btn-secondary" @click="htmx.trigger('#calcul', 'recalc'); document.getElementById('annonce').textContent = 'Résultats mis à jour.'">Mettre à jour les résultats</button>
    </div>
</form>

<div id="calcul" hx-post="<?= site_url('elections/calcul') ?>" hx-trigger="recalc delay:500ms" hx-include="#cse [name=state]" hx-target="#resultats" hx-swap="innerHTML"></div>
<section class="mt-10" aria-labelledby="t-resultats">
    <h2 id="t-resultats" class="text-2xl">Résultats</h2>
    <div id="resultats"><?= view('elections/_results', ['r' => $results, 'state' => $state]) ?></div>
</section>

<?php if ($sim): ?>
<form method="post" action="<?= site_url('elections/' . $sim['id'] . '/supprimer') ?>" class="mt-8" onsubmit="return confirm('Supprimer définitivement ?')"><?= csrf_field() ?><button class="btn btn-danger"><?= icon('trash') ?> Supprimer <?= $official ? 'ce résultat' : 'cette simulation' ?></button></form>
<?php endif ?>
<script src="<?= base_url('assets/js/cse.js?v=' . FO_ASSET_VERSION) ?>"></script>
<?= $this->endSection() ?>
