<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (! empty($refresh)): ?><meta http-equiv="refresh" content="<?= (int) $refresh ?>"><?php endif ?>
    <title><?= esc($title) ?> · FO Gestion</title>
    <link rel="stylesheet" href="<?= base_url('assets/app.css?v=' . FO_ASSET_VERSION) ?>">
</head>
<body class="min-h-dvh">
<main id="contenu" class="mx-auto flex min-h-dvh max-w-lg flex-col justify-center px-4">
    <p class="m-0 font-bold text-muted">Erreur <?= (int) $code ?></p>
    <h1 class="mt-2 text-3xl"><?= esc($title) ?></h1>
    <p class="mt-3 text-lg"><?= esc($message) ?></p>
    <?php if ($code !== 503): ?><p><a href="<?= site_url('/') ?>" class="btn btn-primary mt-4">Revenir à l’accueil</a></p><?php endif ?>
</main>
</body>
</html>
