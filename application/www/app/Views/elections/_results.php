<?php
$n   = static fn ($v) => number_format((float) $v, 0, ',', "\u{202F}");
$pc  = static fn ($v) => number_format((float) $v, 2, ',', "\u{202F}") . "\u{00A0}%";
$syn = $state['syndicats'];
?>
<?php if (! $r['has']): ?>
<p class="card mt-4 text-muted">Saisissez les inscrits, les sièges, les votants et les voix : les résultats s’affichent ici.</p>
<?php else: ?>

<section class="card mt-4" aria-labelledby="t-repr">
    <h3 id="t-repr" class="section-title">Représentativité syndicale</h3>
    <div class="overflow-x-auto" role="region" aria-label="Représentativité (défilement horizontal possible)" tabindex="0">
    <table class="w-full min-w-[28rem] border-collapse">
        <caption class="sr-only">Audience de chaque syndicat au premier tour des titulaires</caption>
        <thead><tr class="border-b-2 border-line text-left"><th scope="col" class="p-2">Syndicat</th><th scope="col" class="p-2 text-right">Voix</th><th scope="col" class="p-2 text-right">Suffrages pris en compte</th><th scope="col" class="p-2 text-right">Audience</th><th scope="col" class="p-2">Seuil de 10&nbsp;%</th></tr></thead>
        <tbody>
        <?php foreach ($r['repr'] as $x): ?>
            <tr class="border-b border-line">
                <th scope="row" class="p-2 text-left"><?= esc($x['nom']) ?><?= $x['categoriel'] ? ' <span class="font-normal text-muted">(catégoriel)</span>' : '' ?></th>
                <td class="p-2 text-right"><?= $n($x['voix']) ?></td>
                <td class="p-2 text-right"><?= $n($x['base']) ?></td>
                <td class="p-2 text-right font-bold"><?= $pc($x['pct']) ?></td>
                <td class="p-2"><span class="badge <?= $x['representatif'] ? 'badge-success' : 'badge-muted' ?>"><?= $x['representatif'] ? 'Représentatif' : 'Non représentatif' ?></span></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
    <p class="mt-2 text-sm text-muted">Suffrages exprimés au premier tour des titulaires, quel que soit le nombre de votants (art. L2122-1). Pour un syndicat catégoriel, seuls comptent les collèges où il présente des candidats (art. L2122-2).</p>
</section>

<?php foreach ($r['types'] as $t => $T): if (! $T['has']) continue; $tot = $T['totaux']; ?>
<section class="card mt-6" aria-labelledby="t-res-<?= $t ?>">
    <h3 id="t-res-<?= $t ?>" class="section-title"><?= esc($T['label']) ?></h3>
    <dl class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,9rem),1fr))]">
        <div><dt class="text-muted">Inscrits</dt><dd class="m-0 text-2xl font-bold"><?= $n($tot['inscrits']) ?></dd></div>
        <div><dt class="text-muted">Votants</dt><dd class="m-0 text-2xl font-bold"><?= $n($tot['votants']) ?></dd></div>
        <div><dt class="text-muted">Participation</dt><dd class="m-0 text-2xl font-bold"><?= $pc($tot['participation']) ?></dd></div>
        <div><dt class="text-muted">Sièges</dt><dd class="m-0 text-2xl font-bold"><?= $n($tot['sieges']) ?></dd></div>
    </dl>
    <?php foreach ($T['colleges'] as $c): ?>
    <div class="mt-5 border-t border-line pt-4">
        <h4 class="m-0 flex flex-wrap items-center gap-2 text-lg"><?= esc($c['nom']) ?>
            <span class="badge <?= $c['quorum'] ? 'badge-success' : 'badge-warning' ?>"><?= $c['quorum'] ? 'Quorum atteint' : 'Quorum non atteint' ?></span></h4>
        <p class="m-0 mt-1 text-muted"><?= $n($c['exprimes']) ?> suffrages exprimés pour <?= $n($c['inscrits']) ?> inscrits (il en faut <?= $n((int) ceil($c['inscrits'] / 2)) ?>)<?= $c['sieges'] ? ', ' . esc(plural($c['sieges'], 'siège')) : '' ?>.</p>
        <?php if (! $c['quorum'] && $c['quorumVotants']): ?>
        <p class="alert alert-info mt-2"><?= icon('alert', 'size-6 shrink-0') ?><span>Les votants atteignent la moitié des inscrits, mais pas les suffrages exprimés : les blancs et nuls ne comptent pas pour le quorum. La version actuelle annonçait ici un quorum atteint.</span></p>
        <?php endif ?>
        <?php if (! $c['quorum']): ?>
        <p class="m-0 mt-2">Un second tour est nécessaire<?= $c['inscrits'] ? '' : ' (indiquez le nombre d’inscrits)' ?>. Les voix comptent tout de même pour la représentativité.</p>
        <?php endif ?>
        <?php if ($c['ecart'] && $c['exprimes'] > 0): ?>
        <p class="m-0 mt-2 font-bold text-warning">Vérifiez la saisie : la somme des voix (<?= $n($c['sumVotes']) ?>) ne correspond pas aux suffrages exprimés (<?= $n($c['exprimes']) ?>).</p>
        <?php endif ?>
        <div class="overflow-x-auto" role="region" aria-label="<?= esc($T['label'] . ', ' . $c['nom'], 'attr') ?> (défilement horizontal possible)" tabindex="0">
        <table class="mt-2 w-full min-w-[24rem] border-collapse">
            <caption class="sr-only"><?= esc($T['label'] . ', ' . $c['nom']) ?></caption>
            <thead><tr class="border-b-2 border-line text-left"><th scope="col" class="p-2">Syndicat</th><th scope="col" class="p-2 text-right">Voix</th><th scope="col" class="p-2 text-right">Part</th><th scope="col" class="p-2 text-right">Sièges</th></tr></thead>
            <tbody>
            <?php foreach ($c['rows'] as $row): if ($row['exclu']) continue; ?>
                <tr class="border-b border-line <?= $row['sieges'] ? 'font-bold' : '' ?>"><th scope="row" class="p-2 text-left <?= $row['sieges'] ? '' : 'font-normal' ?>"><?= esc($row['syndicat']) ?></th><td class="p-2 text-right"><?= $n($row['voix']) ?></td><td class="p-2 text-right"><?= $pc($row['pct']) ?></td><td class="p-2 text-right"><?= $c['quorum'] ? $row['sieges'] : '–' ?></td></tr>
            <?php endforeach ?>
            </tbody>
        </table>
        </div>
        <?php foreach ($c['ties'] as $tie): ?>
        <p class="alert alert-info mt-2"><?= icon('alert', 'size-6 shrink-0') ?><span>Siège n°&nbsp;<?= $tie['seat'] ?> : égalité parfaite (même moyenne, mêmes voix) entre <?= esc(implode(' et ', array_map(static fn ($i) => $syn[$i]['nom'], $tie['lists']))) ?>. Il revient au plus âgé des candidats susceptibles d’être élus (art. R2314-21) ; l’outil l’a attribué provisoirement à <?= esc($syn[$tie['lists'][0]]['nom']) ?>.</span></p>
        <?php endforeach ?>
    </div>
    <?php endforeach ?>
</section>
<?php endforeach ?>

<section class="card mt-6" aria-labelledby="t-synth">
    <h3 id="t-synth" class="section-title">Sièges obtenus</h3>
    <div class="overflow-x-auto" role="region" aria-label="Synthèse des sièges (défilement horizontal possible)" tabindex="0">
    <table class="w-full min-w-[20rem] border-collapse">
        <caption class="sr-only">Synthèse des sièges par syndicat</caption>
        <thead><tr class="border-b-2 border-line text-left"><th scope="col" class="p-2">Syndicat</th><th scope="col" class="p-2 text-right">Titulaires</th><th scope="col" class="p-2 text-right">Suppléants</th><th scope="col" class="p-2 text-right">Total</th></tr></thead>
        <tbody>
        <?php foreach ($syn as $si => $s): $a = $r['types']['tit']['totaux']['parSyndicat'][$si]; $b = $r['types']['sup']['totaux']['parSyndicat'][$si]; ?>
            <tr class="border-b border-line"><th scope="row" class="p-2 text-left"><?= esc($s['nom']) ?></th><td class="p-2 text-right"><?= $a ?></td><td class="p-2 text-right"><?= $b ?></td><td class="p-2 text-right text-lg font-bold"><?= $a + $b ?></td></tr>
        <?php endforeach ?>
        </tbody>
        <tfoot><tr class="font-bold"><th scope="row" class="p-2 text-left">Total</th><td class="p-2 text-right"><?= $r['types']['tit']['totaux']['sieges'] ?></td><td class="p-2 text-right"><?= $r['types']['sup']['totaux']['sieges'] ?></td><td class="p-2 text-right"><?= $r['types']['tit']['totaux']['sieges'] + $r['types']['sup']['totaux']['sieges'] ?></td></tr></tfoot>
    </table>
    </div>
</section>

<?php $withB = array_filter($state['colleges'], static fn ($c) => $c['bureaux']); if ($withB): ?>
<section class="card mt-6" aria-labelledby="t-bur">
    <h3 id="t-bur" class="section-title">Par bureau de vote</h3>
    <?php foreach ($withB as $c): ?>
    <div class="overflow-x-auto" role="region" aria-label="Bureaux, <?= esc($c['nom'], 'attr') ?> (défilement horizontal possible)" tabindex="0">
    <table class="mt-2 w-full min-w-[28rem] border-collapse">
        <caption class="p-2 text-left font-bold"><?= esc($c['nom']) ?></caption>
        <thead><tr class="border-b-2 border-line text-left"><th scope="col" class="p-2">Bureau</th><th scope="col" class="p-2 text-right">Votants titulaires</th><th scope="col" class="p-2 text-right">Blancs et nuls</th><th scope="col" class="p-2 text-right">Part des votants du collège</th></tr></thead>
        <tbody>
        <?php foreach ($c['bureaux'] as $b): ?>
            <tr class="border-b border-line"><th scope="row" class="p-2 text-left font-normal"><?= esc($b['name']) ?></th><td class="p-2 text-right"><?= $n($b['tit']['votants']) ?></td><td class="p-2 text-right"><?= $n($b['tit']['blancs']) ?></td><td class="p-2 text-right"><?= $c['tit']['votants'] ? $pc($b['tit']['votants'] / $c['tit']['votants'] * 100) : '–' ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
    <?php endforeach ?>
</section>
<?php endif ?>
<?php endif ?>
