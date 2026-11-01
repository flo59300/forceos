<nav class="tabs" aria-label="Tâches et questions">
    <a class="tab" href="<?= site_url('taches') ?>" <?= $tab === 'taches' ? 'aria-current="page"' : '' ?>>Tâches</a>
    <a class="tab" href="<?= site_url('questions') ?>" <?= $tab === 'questions' ? 'aria-current="page"' : '' ?>>Questions au secrétaire</a>
</nav>
