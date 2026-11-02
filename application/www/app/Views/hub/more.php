<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
// ForceOS : chaque écran a son pictogramme dans une tuile de verre,
// chaque thème sa teinte (Florian Bigotte pour Force Ouvrière)
$pictos = [
    '/agenda' => 'calendar', '/permanences' => 'pin', '/annonces' => 'megaphone',
    '/notes' => 'notebook', '/barometre' => 'gauge', '/bourse' => 'swap',
    '/documents' => 'folder', '/actualites' => 'rss', '/modules/assistant/index.html' => 'sparkles',
    '/elections' => 'ballot', '/listes-electorales' => 'list',
    '/administration' => 'cog', '/finances/tresorerie' => 'coins', '/administration/stock' => 'box',
    '/administration/rapport' => 'report', '/administration/utilisateurs' => 'usercog',
    '/administration/configuration' => 'sliders', '/administration/rappels' => 'alarm',
    '/administration/import' => 'upload', '/forceos/sauvegarde' => 'lock',
    '/profil' => 'user', '/guide' => 'help',
    '/plateforme/sites' => 'layers', '/plateforme/comptes' => 'users', '/plateforme/reglages' => 'sliders',
    '/plateforme/sauvegardes' => 'download', '/plateforme/mises-a-jour' => 'shield',
];
$themes = [
    'Communication' => ['bleu', 'megaphone'], 'Terrain' => ['vert', 'pin'],
    'Connaissances' => ['indigo', 'book'], 'Élections' => ['rouge', 'ballot'],
    'Administration' => ['nuit', 'cog'], 'Mon compte' => ['bleu', 'user'], 'Plateforme' => ['nuit', 'layers'],
];
$tuile = static fn (string $picto, string $teinte, string $taille = 'size-6'): string =>
    '<span class="fo-verre" data-teinte="' . $teinte . '">' . icon($picto, $taille) . '</span>';
$base = legacy_url('');
?>
<header class="fo-entete">
    <?= $tuile('grid', 'bleu', 'size-8') ?>
    <div>
        <h1 class="text-3xl">Tout le reste</h1>
        <p class="mt-1 text-lg text-muted">Les écrans moins fréquents, regroupés par thème.</p>
    </div>
</header>
<?php if (! $base): ?>
<div class="alert alert-info mt-6" role="status"><p class="m-0">Ces écrans seront intégrés au hub au fil de la migration. L’adresse de la version actuelle n’est pas configurée.</p></div>
<?php endif ?>
<?php foreach ($groups as $name => $items): ?>
<?php [$teinte, $pictoTheme] = $themes[$name] ?? ['bleu', 'grid']; ?>
<section class="mt-10" aria-labelledby="g-<?= esc(url_title($name, '-', true), 'attr') ?>">
    <h2 id="g-<?= esc(url_title($name, '-', true), 'attr') ?>" class="section-title fo-titre-theme" data-teinte="<?= $teinte ?>"><?= icon($pictoTheme, 'size-5') ?><span><?= esc($name) ?></span></h2>
    <ul class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,18rem),1fr))]" role="list">
        <?php foreach ($items as $it): ?>
        <?php $picto = $pictos[$it['url'] ?? ''] ?? ($it['icon'] ?? 'grid'); ?>
        <li>
            <?php if (! empty($it['url'])): ?>
            <a href="<?= site_url($it['url']) ?>" class="todo-card fo-carte">
                <?= $tuile($picto, $teinte) ?>
                <span class="min-w-0 flex-1"><span class="block text-lg font-bold"><?= esc($it['label']) ?></span><span class="block text-muted"><?= esc($it['desc']) ?></span></span>
                <?= icon('arrow', 'fo-fleche size-5 shrink-0 self-center text-brand') ?>
            </a>
            <?php elseif ($base): ?>
            <a href="<?= esc(legacy_url($it['legacy']), 'attr') ?>" class="todo-card fo-carte">
                <?= $tuile($picto, $teinte) ?>
                <span class="min-w-0 flex-1"><span class="block text-lg font-bold"><?= esc($it['label']) ?></span><span class="block text-muted"><?= esc($it['desc']) ?></span></span>
                <?= icon('external', 'fo-fleche size-5 shrink-0 self-center text-brand') ?><span class="sr-only">(version actuelle)</span>
            </a>
            <?php else: ?>
            <div class="todo-card fo-carte"><?= $tuile($picto, $teinte) ?><span class="min-w-0 flex-1"><span class="block text-lg font-bold"><?= esc($it['label']) ?></span><span class="block text-muted"><?= esc($it['desc']) ?></span></span></div>
            <?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
</section>
<?php endforeach ?>
<?= $this->endSection() ?>
