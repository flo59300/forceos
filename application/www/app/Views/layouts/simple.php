<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#002b7f">
    <title><?= esc($title ?? 'FO Gestion') ?> · <?= esc($appName ?? 'FO Gestion') ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/app.css?v=' . FO_ASSET_VERSION) ?>">
    <link rel="stylesheet" href="<?= base_url('modules/forceos/verre.css?v=' . FO_ASSET_VERSION) ?>">
    <link rel="icon" href="<?= base_url('assets/icon.svg') ?>" type="image/svg+xml">
</head>
<body class="min-h-dvh">
<a class="skip-link" href="#contenu">Aller au contenu</a>
<main id="contenu" tabindex="-1" class="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-4 py-10">
    <?= $this->renderSection('content') ?>
</main>
</body>
</html>
