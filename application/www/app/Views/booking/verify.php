<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<?php
$start = date('Ymd', strtotime($b['perm_date'])) . 'T' . str_replace(':', '', substr($b['start_time'], 0, 5)) . '00';
$end   = date('Ymd', strtotime($b['perm_date'])) . 'T' . str_replace(':', '', substr($b['end_time'], 0, 5)) . '00';
$cal   = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . urlencode($b['title']) . '&dates=' . $start . '/' . $end . ($b['location'] ? '&location=' . urlencode($b['location']) : '');
?>
<?php if ($confirmed || $b['status'] === 'confirmed'): ?>
<h1 class="text-3xl"><?= $confirmed ? 'Inscription confirmée' : 'Inscription déjà confirmée' ?></h1>
<p class="mt-3 text-lg" role="status">Votre inscription est validée. Nous vous attendons.</p>
<?php elseif ($b['status'] === 'cancelled'): ?>
<h1 class="text-3xl">Inscription annulée</h1>
<p class="mt-3 text-lg">Cette inscription a été annulée. Pour un nouveau rendez-vous, utilisez le lien ou le QR code de la permanence.</p>
<?php elseif ($b['status'] === 'email_sent'): ?>
<h1 class="text-3xl">Confirmez votre inscription</h1>
<p class="mt-3 text-lg">Votre demande a été acceptée. Il reste à la confirmer.</p>
<?php else: ?>
<h1 class="text-3xl">Demande en attente</h1>
<p class="mt-3 text-lg">Votre demande attend encore la validation du secrétaire.</p>
<?php endif ?>

<div class="card mt-6">
    <dl class="grid gap-3">
        <div><dt class="text-muted">Permanence</dt><dd class="m-0 text-lg font-bold"><?= esc($b['title']) ?></dd></div>
        <div><dt class="text-muted">Date</dt><dd class="m-0 text-lg font-bold"><?= esc(ucfirst(date_fr($b['perm_date'], 'EEEE d MMMM y'))) ?></dd></div>
        <div><dt class="text-muted">Créneau</dt><dd class="m-0 text-lg font-bold"><?= substr($b['start_time'], 0, 5) ?> à <?= substr($b['end_time'], 0, 5) ?></dd></div>
        <?php if ($b['location']): ?><div><dt class="text-muted">Lieu</dt><dd class="m-0 text-lg font-bold"><?= esc($b['location']) ?></dd></div><?php endif ?>
        <div><dt class="text-muted">Au nom de</dt><dd class="m-0 text-lg font-bold"><?= esc($b['name']) ?></dd></div>
    </dl>
    <?php if ($b['status'] === 'email_sent' && ! $confirmed): ?>
    <form method="post" action="<?= current_url() ?>" class="mt-5">
        <input type="hidden" name="v" value="<?= esc($v, 'attr') ?>">
        <button class="btn btn-primary w-full text-lg">Confirmer mon inscription</button>
    </form>
    <?php elseif ($confirmed || $b['status'] === 'confirmed'): ?>
    <p class="mt-5"><a class="btn btn-secondary" href="<?= esc($cal, 'attr') ?>" rel="noopener" target="_blank">Ajouter à Google Agenda <span class="sr-only">(nouvelle fenêtre)</span></a></p>
    <?php endif ?>
</div>
<p class="mt-10 text-center text-sm text-muted"><?= esc($appName) ?> · <?= esc($b['site_name']) ?></p>
<?= $this->endSection() ?>
