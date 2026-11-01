<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Affiche : <?= esc($p['title']) ?> · <?= esc($appName) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/app.css?v=' . FO_ASSET_VERSION) ?>">
    <style>@page { size: A4; margin: 15mm; } @media print { body { background: #fff; color: #000; } }</style>
</head>
<body class="bg-white text-[#0e1726]">
<main class="mx-auto flex min-h-dvh max-w-2xl flex-col items-center px-6 py-10 text-center">
    <p class="m-0 flex items-center gap-3 text-xl font-bold"><span class="grid size-12 place-items-center rounded-xl bg-[#002b7f] text-xl text-white" aria-hidden="true">FO</span> Force Ouvrière</p>
    <h1 class="mt-8 text-5xl text-[#002b7f]"><?= esc($p['title']) ?></h1>
    <p class="mt-4 text-3xl font-bold"><?= $series ? 'Plusieurs dates : choisissez la vôtre' : esc(ucfirst(date_fr($p['perm_date'], 'EEEE d MMMM y'))) ?></p>
    <?php if (! $series && $p['slots']): ?><p class="mt-2 text-2xl">de <?= substr($p['slots'][0]['start_time'], 0, 5) ?> à <?= substr(end($p['slots'])['end_time'], 0, 5) ?></p><?php endif ?>
    <?php if ($p['location']): ?><p class="mt-2 text-2xl"><?= esc($p['location']) ?></p><?php endif ?>
    <div class="mt-10 w-80 rounded-2xl border-4 border-[#002b7f] bg-white p-4" data-qr="<?= esc($link, 'attr') ?>" role="img" aria-label="QR code de réservation"></div>
    <p class="mt-6 text-3xl font-bold">Scannez pour réserver votre créneau</p>
    <p class="mt-3 break-all text-lg"><?= esc($link) ?></p>
    <p class="mt-10 text-lg text-[#46536a]"><?= esc($appName) ?></p>
    <p class="mt-6 print:hidden"><button type="button" class="btn btn-primary" onclick="window.print()">Imprimer</button></p>
</main>
<script src="<?= base_url('assets/js/qrcode.js?v=' . FO_ASSET_VERSION) ?>"></script>
<script src="<?= base_url('assets/js/qr.js?v=' . FO_ASSET_VERSION) ?>"></script>
</body>
</html>
