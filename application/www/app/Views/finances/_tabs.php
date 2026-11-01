<h1 class="text-3xl">Finances</h1>
<nav class="tabs mt-5 overflow-x-auto" aria-label="Rubriques des finances">
    <?php foreach ($financeTabs as $t): ?>
    <a class="tab whitespace-nowrap" href="<?= site_url($t['url']) ?>" <?= $financeTab === $t['key'] ? 'aria-current="page"' : '' ?>><?= esc($t['label']) ?></a>
    <?php endforeach ?>
</nav>
