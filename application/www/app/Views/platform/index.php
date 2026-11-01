<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<h1 class="text-3xl">Tableau de bord de la plateforme</h1>
<p class="mt-1 text-lg text-muted">Version <?= esc(FO_VERSION) ?> · <?= $standalone ? 'mode autonome (un seul site)' : 'plusieurs sites' ?><?= $lastBackup ? ' · dernière sauvegarde le ' . esc(date_fr(date('Y-m-d H:i:s', $lastBackup['date']), "d MMMM y 'à' HH:mm")) : '' ?>.</p>

<?php if ($alerts): ?>
<section class="mt-6" aria-labelledby="t-alertes">
    <h2 id="t-alertes" class="section-title">À traiter</h2>
    <ul class="grid gap-2" role="list">
        <?php foreach ($alerts as [$msg, $url]): ?>
        <li><a href="<?= site_url($url) ?>" class="todo-card"><?= icon('alert', 'size-6 shrink-0 text-warning') ?><span class="min-w-0 flex-1"><?= esc($msg) ?></span><?= icon('arrow', 'size-5 shrink-0') ?></a></li>
        <?php endforeach ?>
    </ul>
</section>
<?php endif ?>

<section class="card mt-6" aria-labelledby="t-chiffres">
    <h2 id="t-chiffres" class="section-title">En chiffres</h2>
    <dl class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(min(100%,10rem),1fr))]">
        <div><dt class="text-muted">Sites actifs</dt><dd class="m-0 text-3xl font-bold"><?= $counts['sites'] ?></dd></div>
        <div><dt class="text-muted">Comptes actifs</dt><dd class="m-0 text-3xl font-bold"><?= $counts['users'] ?></dd></div>
        <div><dt class="text-muted">Adhérents</dt><dd class="m-0 text-3xl font-bold"><?= $counts['members'] ?></dd></div>
        <div><dt class="text-muted">Encaissé en <?= $year ?></dt><dd class="m-0 text-3xl font-bold"><?= money((float) $counts['total']) ?></dd></div>
    </dl>
</section>

<section class="card mt-6" aria-labelledby="t-sites">
    <h2 id="t-sites" class="section-title">Sites</h2>
    <?php if (! $sites): ?><p class="m-0">Aucun site. <a href="<?= site_url('plateforme/sites') ?>#formulaire">Créer le premier site</a>.</p><?php else: ?>
    <div class="overflow-x-auto" role="region" aria-label="Sites (défilement horizontal possible)" tabindex="0">
    <table class="w-full min-w-[36rem] text-left tabular-nums">
        <caption class="sr-only">Activité de chaque site</caption>
        <thead class="text-muted"><tr><th scope="col" class="py-1 pr-3">Site</th><th scope="col" class="py-1 pr-3 text-right">Comptes</th><th scope="col" class="py-1 pr-3 text-right">Adhérents</th><th scope="col" class="py-1 pr-3 text-right">Encaissé <?= $year ?></th><th scope="col" class="py-1">Dernière connexion</th></tr></thead>
        <tbody>
        <?php foreach ($sites as $s): ?>
        <tr class="border-t border-line"><th scope="row" class="py-2 pr-3"><a href="<?= site_url('plateforme/sites') . '?modifier=' . $s['id'] ?>#formulaire"><?= esc($s['name']) ?></a><?= $s['active'] ? '' : ' <span class="badge badge-muted">Inactif</span>' ?></th>
            <td class="py-2 pr-3 text-right"><?= (int) $s['nb_users'] ?></td><td class="py-2 pr-3 text-right"><?= (int) $s['nb_members'] ?></td><td class="py-2 pr-3 text-right"><?= money((float) $s['total']) ?></td>
            <td class="py-2"><?= $s['last_login'] ? esc(date_fr($s['last_login'], 'd MMM y')) : 'jamais' ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
    <?php endif ?>
</section>

<ul class="mt-6 grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,18rem),1fr))]" role="list" aria-label="Rubriques de la plateforme">
    <?php foreach (array_slice(\App\Libraries\Navigation::platform(), 1, 5) as $i): ?>
    <li><a href="<?= site_url($i['url']) ?>" class="todo-card"><?= icon($i['icon'], 'size-7 shrink-0 text-brand') ?><span class="min-w-0 flex-1"><span class="block text-lg font-bold"><?= esc($i['label']) ?></span><span class="block text-muted"><?= esc($i['desc']) ?></span></span></a></li>
    <?php endforeach ?>
</ul>
<?= $this->endSection() ?>
