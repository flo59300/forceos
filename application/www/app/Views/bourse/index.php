<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php $rows = []; foreach ($companies as $s => $n) { $rows[] = ['s' => $s, 'n' => $n]; } ?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-3xl">Bourse</h1>
        <p class="mt-1 text-lg text-muted">Données Yahoo Finance, à titre d’information.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="button" class="btn btn-secondary" id="bourse-maj">Actualiser</button>
        <button type="button" class="btn btn-ghost" id="bourse-auto" aria-pressed="true">Actualisation automatique : oui</button>
    </div>
</div>
<p class="mt-2 text-muted" id="bourse-etat">Chargement des cours…</p>

<section class="card mt-4 bg-[#0e1726] text-white" aria-labelledby="t-stla">
    <h2 id="t-stla" class="m-0 text-lg text-white">Stellantis <span class="font-normal text-[#c3cbe0]">(NYSE : STLA)</span></h2>
    <p class="m-0 mt-1 text-4xl font-bold" id="stla-prix">–</p>
    <p class="m-0 mt-1 text-lg font-bold" id="stla-var"></p>
    <p class="m-0 mt-1 text-[#c3cbe0]"><span id="stla-marche"></span> <span id="stla-heure"></span></p>
</section>

<section class="mt-6" aria-labelledby="t-cies">
    <h2 id="t-cies" class="section-title">Constructeurs suivis</h2>
    <ul class="grid gap-3 grid-cols-[repeat(auto-fill,minmax(min(100%,12rem),1fr))]" role="list" id="bourse-companies"></ul>
</section>
<section class="mt-6" aria-labelledby="t-idx">
    <h2 id="t-idx" class="section-title">Grands indices</h2>
    <ul class="grid gap-3 grid-cols-[repeat(auto-fill,minmax(min(100%,12rem),1fr))]" role="list" id="bourse-indices"></ul>
</section>

<?php if ($canManage): ?>
<section class="card mt-8 max-w-3xl" aria-labelledby="t-suivi" id="suivi" x-data="{ rows: <?= esc(json_encode($rows, JSON_UNESCAPED_UNICODE), 'attr') ?> }">
    <h2 id="t-suivi" class="section-title">Entreprises suivies par votre site</h2>
    <p class="mt-0 text-muted">Symboles Yahoo Finance, par exemple STLA, RNO.PA, TM, TSLA, VOW3.DE ou 7203.T. Douze au plus.</p>
    <form method="post" action="<?= site_url('bourse') ?>">
        <?= csrf_field() ?>
        <ul class="grid gap-2" role="list">
            <template x-for="(r, i) in rows" :key="i">
                <li class="flex flex-wrap items-end gap-2">
                    <div class="w-36"><label class="label text-base font-normal" :for="'sym-' + i" x-text="'Symbole ' + (i + 1)"></label><input class="input font-mono uppercase" type="text" maxlength="15" name="symbol[]" :id="'sym-' + i" x-model="r.s"></div>
                    <div class="min-w-0 flex-1 basis-48"><label class="label text-base font-normal" :for="'nom-' + i" x-text="'Nom affiché ' + (i + 1)"></label><input class="input" type="text" maxlength="60" name="name[]" :id="'nom-' + i" x-model="r.n"></div>
                    <button type="button" class="btn btn-ghost" @click="rows.splice(i, 1)">Retirer<span class="sr-only" x-text="' ' + (r.n || 'la ligne ' + (i + 1))"></span></button>
                </li>
            </template>
            <noscript><?php foreach ($rows as $i => $r): ?><li class="flex gap-2"><input class="input" name="symbol[]" value="<?= esc($r['s'], 'attr') ?>" aria-label="Symbole <?= $i + 1 ?>"><input class="input" name="name[]" value="<?= esc($r['n'], 'attr') ?>" aria-label="Nom <?= $i + 1 ?>"></li><?php endforeach ?></noscript>
        </ul>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="btn btn-secondary" x-cloak x-show="rows.length < 12" @click="rows.push({ s: '', n: '' })"><?= icon('plus') ?> Ajouter une entreprise</button>
            <button class="btn btn-primary">Enregistrer</button>
            <button class="btn btn-ghost" name="defaut" value="1" onclick="return confirm('Revenir à la liste par défaut (Stellantis, Renault, Toyota, BYD, Volkswagen) ?')">Liste par défaut</button>
        </div>
    </form>
</section>
<?php endif ?>
<script src="<?= base_url('assets/js/bourse.js?v=' . FO_ASSET_VERSION) ?>" data-api="<?= site_url('bourse/cours') ?>" id="bourse-script"></script>
<?= $this->endSection() ?>
