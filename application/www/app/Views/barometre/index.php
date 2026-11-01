<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
use App\Domain\Barometre;

$ambTone  = static fn (float $a) => $a < 2.5 ? 'text-danger' : ($a < 3.5 ? 'text-warning' : 'text-success');
$ambBadge = static fn (int $a) => $a <= 2 ? 'badge-danger' : ($a === 3 ? 'badge-warning' : 'badge-success');
$icons    = array_column($tags, 'icon', 'label');
$oldTags  = (array) (old('tags') ?? []);
?>
<h1 class="text-3xl">Baromètre social</h1>
<p class="mt-1 text-lg text-muted">L’ambiance du terrain et les sujets qui remontent, secteur par secteur.</p>

<nav class="tabs mt-5 max-w-xl" aria-label="Rubriques du baromètre">
    <a class="tab" href="<?= site_url('barometre') ?>" <?= $tab === 'saisie' ? 'aria-current="page"' : '' ?>>Nouvelle remontée</a>
    <a class="tab" href="<?= site_url('barometre') ?>?onglet=tableau&amp;mois=<?= $months ?>" <?= $tab === 'tableau' ? 'aria-current="page"' : '' ?>>Tableau de bord</a>
    <a class="tab" href="<?= site_url('barometre') ?>?onglet=historique&amp;mois=<?= $months ?>" <?= $tab === 'historique' ? 'aria-current="page"' : '' ?>>Historique (<?= $stats['total'] ?>)</a>
</nav>

<?php if ($tab === 'saisie'): ?>
<section class="card max-w-3xl" aria-labelledby="t-saisie">
    <h2 id="t-saisie" class="section-title">Ce que vous avez entendu sur le terrain</h2>
    <form method="post" action="<?= site_url('barometre') ?>">
        <?= csrf_field() ?>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,14rem),1fr))]">
            <div class="field"><label class="label" for="entry_date">Date</label><input class="input" type="date" id="entry_date" name="entry_date" max="<?= date('Y-m-d') ?>" value="<?= esc(old('entry_date') ?? date('Y-m-d'), 'attr') ?>" required></div>
            <div class="field"><label class="label" for="secteur_id">Secteur</label><select class="input" id="secteur_id" name="secteur_id"><option value="">Tout le site</option><?php foreach ($secteurs as $s): ?><option value="<?= $s['id'] ?>" <?= (string) old('secteur_id') === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach ?></select></div>
        </div>
        <fieldset class="field">
            <legend class="label">Ambiance générale</legend>
            <div class="flex flex-wrap gap-2">
                <?php foreach (Barometre::AMBIANCE as $k => $l): ?>
                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border-2 border-line px-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft"><input type="radio" name="ambiance" value="<?= $k ?>" class="size-5" <?= (int) (old('ambiance') ?? 3) === $k ? 'checked' : '' ?>> <?= $k ?>&nbsp;: <?= esc($l) ?></label>
                <?php endforeach ?>
            </div>
        </fieldset>
        <fieldset class="field">
            <legend class="label">Thèmes qui remontent <span class="font-normal text-muted">(plusieurs choix possibles)</span></legend>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($tags as $t): ?>
                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border-2 border-line px-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft"><input type="checkbox" name="tags[]" value="<?= esc($t['label'], 'attr') ?>" class="size-5" <?= in_array($t['label'], $oldTags, true) ? 'checked' : '' ?>> <span aria-hidden="true"><?= esc($t['icon']) ?></span> <?= esc($t['label']) ?></label>
                <?php endforeach ?>
            </div>
        </fieldset>
        <div class="field"><label class="label" for="comment">Commentaire <span class="font-normal text-muted">(facultatif)</span></label><textarea class="input min-h-24" id="comment" name="comment" maxlength="2000" placeholder="Situations observées, demandes des salariés…"><?= esc(old('comment') ?? '') ?></textarea></div>
        <button class="btn btn-primary">Enregistrer la remontée</button>
    </form>
</section>
<?php else: ?>
<form method="get" action="<?= site_url('barometre') ?>" class="mb-5 flex flex-wrap items-end gap-2">
    <input type="hidden" name="onglet" value="<?= esc($tab, 'attr') ?>">
    <div><label class="label" for="mois">Période</label><select class="input" id="mois" name="mois"><?php foreach (Barometre::PERIODS as $k => $l): ?><option value="<?= $k ?>" <?= $months === $k ? 'selected' : '' ?>><?= esc(ucfirst($l)) ?></option><?php endforeach ?></select></div>
    <button class="btn btn-secondary">Afficher</button>
</form>
<?php endif ?>

<?php if ($tab === 'tableau'): ?>
<h2 class="sr-only">Indicateurs sur <?= esc(Barometre::PERIODS[$months]) ?></h2>
<dl class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,10rem),1fr))]">
    <div class="card"><dt class="text-muted">Remontées</dt><dd class="m-0 text-3xl font-bold"><?= $stats['total'] ?></dd></div>
    <div class="card"><dt class="text-muted">Ambiance moyenne</dt><dd class="m-0 text-3xl font-bold <?= $stats['total'] ? $ambTone((float) $stats['avg_ambiance']) : '' ?>"><?= $stats['total'] ? str_replace('.', ',', (string) $stats['avg_ambiance']) . '&nbsp;/&nbsp;5' : '–' ?></dd></div>
    <div class="card"><dt class="text-muted">Secteurs couverts</dt><dd class="m-0 text-3xl font-bold"><?= count($stats['by_secteur']) ?></dd></div>
    <div class="card"><dt class="text-muted">Thèmes cités</dt><dd class="m-0 text-3xl font-bold"><?= count($stats['top_tags']) ?></dd></div>
</dl>
<?php if (! $stats['total']): ?>
<div class="card mt-6"><p class="m-0">Aucune remontée sur <?= esc(Barometre::PERIODS[$months]) ?>. <a href="<?= site_url('barometre') ?>">Faire une saisie</a></p></div>
<?php endif ?>
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <?php if ($stats['top_tags']): $max = max($stats['top_tags']); ?>
    <section class="card" aria-labelledby="t-themes">
        <h2 id="t-themes" class="section-title">Thèmes les plus cités</h2>
        <ul class="grid gap-3" role="list">
            <?php foreach ($stats['top_tags'] as $tag => $n): ?>
            <li>
                <p class="m-0 flex justify-between gap-2"><span class="font-bold"><span aria-hidden="true"><?= esc($icons[$tag] ?? '') ?></span> <?= esc($tag) ?></span><span><?= esc(plural($n, 'mention')) ?></span></p>
                <div class="meter" aria-hidden="true"><span style="width: <?= (int) round($n / $max * 100) ?>%"></span></div>
            </li>
            <?php endforeach ?>
        </ul>
    </section>
    <?php endif ?>
    <?php if ($stats['by_secteur']): ?>
    <section class="card" aria-labelledby="t-secteurs">
        <h2 id="t-secteurs" class="section-title">Ambiance par secteur <span class="text-base font-normal text-muted">(les plus tendus en premier)</span></h2>
        <ul class="grid gap-3" role="list">
            <?php foreach ($stats['by_secteur'] as $name => $sd): ?>
            <li>
                <p class="m-0 flex justify-between gap-2"><span class="min-w-0 font-bold"><?= esc($name) ?> <span class="font-normal text-muted">(<?= esc(plural($sd['count'], 'remontée')) ?>)</span></span><span class="font-bold <?= $ambTone((float) $sd['avg']) ?>"><?= str_replace('.', ',', (string) $sd['avg']) ?>&nbsp;/&nbsp;5</span></p>
                <div class="meter" aria-hidden="true"><span class="<?= $sd['avg'] < 2.5 ? 'bg-danger!' : ($sd['avg'] < 3.5 ? 'bg-warning!' : 'bg-success!') ?>" style="width: <?= (int) round($sd['avg'] / 5 * 100) ?>%"></span></div>
            </li>
            <?php endforeach ?>
        </ul>
    </section>
    <?php endif ?>
</div>
<?php endif ?>

<?php if ($tab === 'historique'): ?>
<section aria-labelledby="t-histo">
    <h2 id="t-histo" class="section-title">Remontées sur <?= esc(Barometre::PERIODS[$months]) ?></h2>
    <?php if (! $entries): ?>
    <div class="card"><p class="m-0">Aucune remontée. <a href="<?= site_url('barometre') ?>">Faire la première saisie</a></p></div>
    <?php else: ?>
    <ul class="grid gap-3" role="list">
        <?php foreach ($entries as $e): $et = array_filter(array_map('trim', explode(',', (string) $e['tags']))); ?>
        <li class="card">
            <div class="flex flex-wrap items-start gap-2">
                <p class="m-0 min-w-0 flex-1"><strong><?= esc($e['secteur_name'] ?: 'Tout le site') ?></strong> <span class="text-muted">· <?= esc(date_fr($e['entry_date'], 'd MMMM y')) ?> · <?= esc($e['user_name'] ?: 'Ancien compte') ?></span></p>
                <span class="badge <?= $ambBadge((int) $e['ambiance']) ?>">Ambiance <?= (int) $e['ambiance'] ?>/5 : <?= esc(Barometre::AMBIANCE[(int) $e['ambiance']] ?? '') ?></span>
            </div>
            <?php if ($e['comment']): ?><p class="m-0 mt-2 whitespace-pre-line"><?= esc($e['comment']) ?></p><?php endif ?>
            <?php if ($et): ?><ul class="mt-2 flex flex-wrap gap-2" role="list" aria-label="Thèmes"><?php foreach ($et as $t): ?><li class="badge badge-neutral"><?= esc($t) ?></li><?php endforeach ?></ul><?php endif ?>
            <?php if ($canManage): ?>
            <form method="post" action="<?= site_url('barometre/' . $e['id'] . '/supprimer') ?>" class="mt-2" onsubmit="return confirm('Supprimer cette remontée ?')"><?= csrf_field() ?><button class="btn btn-ghost px-0 text-danger">Supprimer<span class="sr-only"> la remontée du <?= esc(date_fr($e['entry_date'], 'd MMMM')) ?></span></button></form>
            <?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>
<?php endif ?>
<?= $this->endSection() ?>
