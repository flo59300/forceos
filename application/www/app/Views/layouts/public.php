<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#002b7f">
    <title><?= esc($title ?? 'Réservation') ?> · <?= esc($appName ?? 'FO Gestion') ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/app.css?v=' . FO_ASSET_VERSION) ?>">
    <link rel="icon" href="<?= base_url('assets/icon.svg') ?>" type="image/svg+xml">
</head>
<body class="min-h-dvh">
<a class="skip-link" href="#contenu">Aller au contenu</a>
<header class="border-b border-line bg-surface">
    <div class="mx-auto flex max-w-xl items-center gap-3 px-4 py-3">
        <span class="grid size-10 place-items-center rounded-xl bg-[#002b7f] text-lg font-bold text-white" aria-hidden="true">FO</span>
        <p class="m-0 leading-tight"><span class="block font-bold">Force Ouvrière</span><span class="block text-sm text-muted"><?= esc($siteName ?? $appName ?? '') ?></span></p>
    </div>
</header>
<main id="contenu" tabindex="-1" class="mx-auto max-w-xl px-4 pb-16 pt-6">
    <?= $this->renderSection('content') ?>
</main>
</body>
</html>
