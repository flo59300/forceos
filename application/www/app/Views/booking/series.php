<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<h1 class="text-3xl"><?= esc($first['title']) ?></h1>
<?php if ($first['location']): ?><p class="mt-1 text-lg text-muted"><?= esc($first['location']) ?></p><?php endif ?>
<section class="card mt-6" aria-labelledby="t-dates">
    <h2 id="t-dates" class="section-title">Choisissez une date</h2>
    <?php if (! $dates): ?>
    <p class="m-0 text-muted">Aucune date disponible pour le moment.</p>
    <?php else: ?>
    <ul class="grid gap-3 grid-cols-[repeat(auto-fill,minmax(9rem,1fr))]" role="list">
        <?php foreach ($dates as $d):
            $full  = $d['quota_reached'] || $d['total_bookings'] >= $d['total_capacity'];
            $avail = max(0, $d['capacity'] - $d['total_bookings']); ?>
        <li>
            <?php if ($full): ?>
            <div class="rounded-xl border-2 border-line bg-surface-2 p-3 text-center">
                <span class="block font-bold"><?= esc(ucfirst(date_fr($d['perm_date'], 'EEEE d MMMM'))) ?></span>
                <span class="badge badge-muted mt-1">Complet</span>
            </div>
            <?php else: ?>
            <a class="block rounded-xl border-2 border-line bg-surface p-3 text-center text-ink no-underline hover:border-brand" href="<?= current_url() . '?s=' . esc($series, 'url') . '&d=' . $d['perm_date'] ?>">
                <span class="block font-bold"><?= esc(ucfirst(date_fr($d['perm_date'], 'EEEE d MMMM'))) ?></span>
                <span class="badge badge-success mt-1"><?= esc(plural($avail, 'place')) ?></span>
            </a>
            <?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
