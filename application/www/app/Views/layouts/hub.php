<?php
/**
 * Mise en page du hub.
 * Repères : lien d'évitement, en-tête (banner), navigation principale, contenu (main), barre du téléphone.
 *
 * @var string $title @var string $appName @var array $nav @var array $mobileNav @var array $quick
 * @var string $active @var array $unread @var array|null $me @var string $role
 */
$roleLabel = ['secretaire' => 'Secrétaire', 'tresorier' => 'Trésorier', 'collecteur' => 'Délégué', 'super_admin' => 'Super Admin'][$role] ?? '';
$flashes   = array_filter(['success' => session('success'), 'error' => session('error'), 'info' => session('info')]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#002b7f">
    <meta name="<?= csrf_header() ?>" content="<?= csrf_hash() ?>" id="csrf-meta">
    <title><?= esc($title) ?> · <?= esc($appName) ?></title>
    <link rel="preload" href="<?= base_url('assets/fonts/carlito-latin-400-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= base_url('assets/app.css?v=' . FO_ASSET_VERSION) ?>">
    <link rel="stylesheet" href="<?= base_url('modules/forceos/verre.css?v=' . FO_ASSET_VERSION) ?>">
    <link rel="icon" href="<?= base_url('assets/icon.svg') ?>" type="image/svg+xml">
    <link rel="manifest" href="<?= site_url('manifest.webmanifest') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('assets/icons/fostellantis.png') ?>">
    <script src="<?= base_url('assets/js/htmx.min.js?v=' . FO_ASSET_VERSION) ?>" defer></script>
    <script src="<?= base_url('assets/js/app.js?v=' . FO_ASSET_VERSION) ?>" defer></script>
    <script src="<?= base_url('assets/js/alpine-focus.min.js?v=' . FO_ASSET_VERSION) ?>" defer></script>
    <script src="<?= base_url('assets/js/alpine.min.js?v=' . FO_ASSET_VERSION) ?>" defer></script>
</head>
<body class="min-h-dvh" x-data="{ saisir: false }" @keydown.escape.window="saisir = false">
<a class="skip-link" href="#contenu">Aller au contenu</a>

<header class="print:hidden sticky top-0 z-30 border-b border-line bg-surface/95 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-2">
        <a href="<?= site_url($role === 'super_admin' ? 'plateforme' : '/') ?>" class="flex min-h-11 min-w-0 items-center gap-3 no-underline text-ink">
            <span class="grid size-10 place-items-center rounded-xl bg-[#002b7f] text-lg font-bold text-white" aria-hidden="true">FO</span>
            <span class="min-w-0 leading-tight">
                <span class="block font-bold"><?= esc($appName) ?></span>
                <span class="block text-sm text-muted"><?= esc($role === 'super_admin' ? 'Espace Super Admin' : session('site_name')) ?></span>
            </span>
        </a>
        <div class="ml-auto flex items-center gap-1">
            <?php if ($notifUrl = site_url('notifications')): ?>
            <a href="<?= esc($notifUrl, 'attr') ?>" class="relative grid size-11 place-items-center rounded-xl text-ink hover:bg-surface-2" title="Notifications">
                <?= icon('bell', 'size-6') ?>
                <span class="sr-only">Notifications<?= $unread['notifications'] ? ', ' . esc(plural($unread['notifications'], 'non lue')) : ', aucune non lue' ?></span>
                <?php if ($unread['notifications']): ?><span class="absolute right-1 top-1 min-w-5 rounded-full bg-[#e2001a] px-1 text-center text-xs font-bold text-white" aria-hidden="true"><?= $unread['notifications'] > 99 ? '99+' : $unread['notifications'] ?></span><?php endif ?>
            </a>
            <?php endif ?>
            <div class="relative" x-data="{ open: false }" @keydown.escape="open = false; $refs.btn.focus()" @click.outside="open = false">
                <button type="button" x-ref="btn" class="flex min-h-11 items-center gap-2 rounded-xl px-2 hover:bg-surface-2" @click="open = !open" :aria-expanded="open.toString()" aria-expanded="false" aria-controls="menu-compte">
                    <span class="grid size-9 place-items-center rounded-full bg-brand-soft font-bold text-brand" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr((string) ($me['first_name'] ?? '?'), 0, 1) . mb_substr((string) ($me['last_name'] ?? ''), 0, 1))) ?></span>
                    <span class="hidden text-left leading-tight lg:block"><span class="block font-bold"><?= esc(session('user_name')) ?></span><span class="block text-sm text-muted"><?= esc($roleLabel) ?></span></span>
                    <span class="sr-only lg:hidden">Mon compte</span>
                </button>
                <div id="menu-compte" x-show="open" x-cloak x-transition.opacity class="absolute right-0 mt-2 w-60 rounded-xl border border-line bg-surface p-2 shadow-lg">
                    <p class="px-3 py-2 text-sm text-muted lg:hidden"><?= esc(session('user_name')) ?> · <?= esc($roleLabel) ?></p>
                    <a href="<?= site_url('profil') ?>" class="nav-link">Mon profil</a>
                    <a href="<?= site_url('notifications') ?>" class="nav-link">Notifications</a>
                    <form method="post" action="<?= site_url('deconnexion') ?>" onsubmit="try { if (navigator.serviceWorker && navigator.serviceWorker.controller) navigator.serviceWorker.controller.postMessage('logout'); if (window.caches) caches.delete('fo3-pages'); } catch (e) {}">
                        <?= csrf_field() ?>
                        <button type="submit" class="nav-link w-full text-left"><?= icon('logout') ?> Se déconnecter</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="mx-auto flex max-w-6xl gap-6 px-4">
    <nav class="print:hidden sticky top-[4.25rem] hidden h-fit w-64 shrink-0 py-6 lg:block" aria-label="Navigation principale">
        <ul class="space-y-1" role="list">
            <?php foreach ($nav as $item): ?>
            <li>
                <a href="<?= site_url($item['url']) ?>" class="nav-link" <?= $active === $item['key'] ? 'aria-current="page"' : '' ?>>
                    <?= icon($item['icon'], 'size-6 shrink-0') ?>
                    <span><?= esc($item['label']) ?></span>
                    <?php if ($item['key'] === 'messages' && $unread['messages']): ?>
                    <span class="badge badge-danger ml-auto"><?= $unread['messages'] ?><span class="sr-only"> non lus</span></span>
                    <?php endif ?>
                </a>
            </li>
            <?php endforeach ?>
        </ul>
    </nav>

    <main id="contenu" tabindex="-1" class="min-w-0 flex-1 pb-28 pt-6 lg:pb-12 print:p-0">
        <?php foreach ($flashes as $type => $msg): ?>
        <div class="alert alert-<?= $type ?>" <?= $type === 'error' ? 'role="alert"' : 'role="status"' ?>>
            <?= icon($type === 'error' ? 'alert' : 'check', 'size-6 shrink-0') ?>
            <p class="m-0 font-bold"><?= esc($msg) ?></p>
        </div>
        <?php endforeach ?>

        <?= $this->renderSection('content') ?>
    </main>
</div>

<!-- Annonces vocales des actions sans rechargement (htmx) -->
<div id="annonce" class="sr-only" role="status" aria-live="polite"></div>

<!-- Barre de navigation du téléphone -->
<nav class="print:hidden fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="Navigation rapide">
    <ul class="mx-auto flex max-w-lg items-stretch" role="list">
        <?php foreach ($mobileNav as $item): ?>
        <li class="flex flex-1">
            <?php if (! empty($item['action'])): ?>
            <button type="button" class="tabbar-link" @click="saisir = true" aria-haspopup="dialog" :aria-expanded="saisir.toString()" aria-expanded="false" aria-controls="panneau-saisir">
                <span class="grid size-10 place-items-center rounded-full bg-brand text-brand-ink"><?= icon('plus', 'size-6') ?></span>
                <span><?= esc($item['label']) ?></span>
            </button>
            <?php else: ?>
            <a href="<?= site_url($item['url']) ?>" class="tabbar-link" <?= $active === $item['key'] ? 'aria-current="page"' : '' ?>>
                <?= icon($item['icon'], 'size-6') ?>
                <span><?= esc($item['label']) ?></span>
            </a>
            <?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
</nav>

<!-- Panneau « Saisir » : fenêtre modale accessible (focus piégé, Échap pour fermer) -->
<div x-show="saisir" x-cloak class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-labelledby="titre-saisir" id="panneau-saisir">
    <div class="absolute inset-0 bg-black/50" @click="saisir = false" aria-hidden="true"></div>
    <div class="absolute inset-x-0 bottom-0 rounded-t-3xl bg-surface p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))]" x-trap.noscroll="saisir" x-transition:enter="transition" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
        <div class="mb-4 flex items-center justify-between">
            <h2 id="titre-saisir" class="text-xl">Que voulez-vous faire ?</h2>
            <button type="button" class="grid size-11 place-items-center rounded-xl hover:bg-surface-2" @click="saisir = false"><?= icon('x', 'size-6') ?><span class="sr-only">Fermer</span></button>
        </div>
        <ul class="grid gap-3" role="list">
            <?php foreach ($quick as $i => $q): ?>
            <li><a href="<?= site_url($q['url']) ?>" class="quick-action <?= $i === 0 ? 'quick-action-main' : '' ?>"><?= icon($q['icon'], 'size-6 shrink-0') ?><span><?= esc($q['label']) ?></span></a></li>
            <?php endforeach ?>
        </ul>
    </div>
</div>
</body>
</html>
