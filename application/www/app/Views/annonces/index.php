<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
use App\Domain\Announcements;

$errors = session('errors') ?? [];
$v      = static fn (string $f, $d = '') => old($f, $edit[$f] ?? $d);
$tone   = ['normale' => 'border-line', 'importante' => 'border-warning', 'urgente' => 'border-danger'];
$badge  = ['normale' => 'badge-neutral', 'importante' => 'badge-warning', 'urgente' => 'badge-danger'];
?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-3xl">Annonces</h1>
        <p class="mt-1 text-lg text-muted">Le panneau d’affichage du site<?= $enabled ? '' : ', actuellement désactivé' ?>.</p>
    </div>
    <?php if ($canManage): ?>
    <form method="post" action="<?= site_url('annonces/panneau') ?>">
        <?= csrf_field() ?><input type="hidden" name="enabled" value="<?= $enabled ? 0 : 1 ?>">
        <button class="btn <?= $enabled ? 'btn-ghost' : 'btn-primary' ?>"><?= $enabled ? 'Désactiver le panneau' : 'Activer le panneau' ?></button>
    </form>
    <?php endif ?>
</div>

<?php if (! $enabled): ?>
<div class="alert alert-info mt-6" role="status"><?= icon('alert', 'size-6 shrink-0') ?><p class="m-0"><?= $canManage
    ? 'Le panneau est désactivé : personne d’autre que vous ne voit les annonces. Activez-le pour les afficher sur « Ma journée » de chaque compte du site.'
    : 'Le panneau d’affichage n’est pas activé sur votre site.' ?></p></div>
<?php endif ?>

<?php if ($canManage && ($enabled || $edit)): ?>
<section class="card mt-6 max-w-3xl" aria-labelledby="t-form" id="formulaire">
    <h2 id="t-form" class="section-title"><?= $edit ? 'Modifier l’annonce' : 'Nouvelle annonce' ?></h2>
    <?= view('partials/form_errors') ?>
    <?php if (! $edit): ?><p class="mt-0 text-muted">Tous les comptes du site seront prévenus dans leurs notifications.</p><?php endif ?>
    <form method="post" action="<?= $edit ? site_url('annonces/' . $edit['id']) : site_url('annonces') ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="title">Titre <span class="font-normal text-muted">(obligatoire)</span></label>
            <input class="input" type="text" maxlength="255" required placeholder="Assemblée générale le 15 mars"<?= field_attrs('title', $errors) ?> value="<?= esc($v('title'), 'attr') ?>">
            <?= field_error('title', $errors) ?>
        </div>
        <div class="field">
            <label class="label" for="body">Contenu <span class="font-normal text-muted">(obligatoire)</span></label>
            <textarea class="input min-h-32" maxlength="5000" required<?= field_attrs('body', $errors) ?>><?= esc($v('body')) ?></textarea>
            <?= field_error('body', $errors) ?>
        </div>
        <fieldset class="field">
            <legend class="label">Priorité</legend>
            <div class="flex flex-wrap gap-2">
                <?php foreach (Announcements::PRIORITIES as $k => $l): ?>
                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border-2 border-line px-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft"><input type="radio" name="priority" value="<?= $k ?>" class="size-5" <?= $v('priority', 'normale') === $k ? 'checked' : '' ?>> <?= esc($l) ?></label>
                <?php endforeach ?>
            </div>
        </fieldset>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,14rem),1fr))]">
            <div class="field">
                <label class="label" for="expires_at">Retirer automatiquement après le <span class="font-normal text-muted">(facultatif)</span></label>
                <input class="input" type="date"<?= field_attrs('expires_at', $errors) ?> value="<?= esc($v('expires_at'), 'attr') ?>">
                <?= field_error('expires_at', $errors) ?>
            </div>
            <div class="field flex items-end">
                <label class="flex min-h-11 cursor-pointer items-center gap-2"><input type="checkbox" name="pinned" value="1" class="size-5" <?= $v('pinned') ? 'checked' : '' ?>> Épingler en haut du panneau</label>
            </div>
        </div>
        <div class="flex flex-wrap gap-3">
            <button class="btn btn-primary"><?= $edit ? 'Enregistrer' : 'Publier l’annonce' ?></button>
            <?php if ($edit): ?><a href="<?= site_url('annonces') ?>" class="btn btn-ghost">Annuler</a><?php endif ?>
        </div>
    </form>
</section>
<?php endif ?>

<?php if ($enabled || $canManage): ?>
<section class="mt-8" aria-labelledby="t-liste">
    <h2 id="t-liste" class="section-title"><?= $canManage ? 'Toutes les annonces' : 'Annonces en cours' ?></h2>
    <?php if (! $items): ?>
    <div class="card"><p class="m-0 text-muted">Aucune annonce pour le moment.</p></div>
    <?php else: ?>
    <ul class="grid gap-4" role="list">
        <?php foreach ($items as $a):
            $expired = Announcements::isExpired($a);
            $hidden  = ! $a['active'] || $expired; ?>
        <li id="annonce-<?= $a['id'] ?>" class="card scroll-mt-24 border-l-4 <?= $tone[$a['priority']] ?? 'border-line' ?> <?= $hidden ? 'border-dashed bg-surface-2' : '' ?>">
            <div class="flex flex-wrap items-start gap-2">
                <h3 class="m-0 min-w-0 flex-1 text-xl"><?= esc($a['title']) ?></h3>
                <?php if ($a['pinned']): ?><span class="badge badge-info">Épinglée</span><?php endif ?>
                <?php if ($a['priority'] !== 'normale'): ?><span class="badge <?= $badge[$a['priority']] ?>"><?= esc(Announcements::PRIORITIES[$a['priority']]) ?></span><?php endif ?>
                <?php if ($canManage && $hidden): ?><span class="badge badge-muted"><?= $expired ? 'Expirée' : 'Masquée' ?></span><?php endif ?>
            </div>
            <p class="mt-1 text-muted"><?= esc($a['author_name'] ?: 'Ancien compte') ?>, le <?= esc(date_fr($a['created_at'], "d MMMM y 'à' HH:mm")) ?><?= $a['expires_at'] ? ' · retirée après le ' . esc(date_fr($a['expires_at'], 'd MMMM')) : '' ?></p>
            <p class="mt-3 whitespace-pre-line"><?= esc($a['body']) ?></p>
            <?php if ($canManage): ?>
            <div class="mt-3 flex flex-wrap gap-2">
                <a class="btn btn-secondary" href="<?= site_url('annonces') . '?modifier=' . $a['id'] ?>#formulaire"><?= icon('edit') ?> Modifier<span class="sr-only"> « <?= esc($a['title']) ?> »</span></a>
                <form method="post" action="<?= site_url('annonces/' . $a['id'] . '/affichage') ?>"><?= csrf_field() ?><button class="btn btn-ghost"><?= $a['active'] ? 'Masquer' : 'Afficher' ?><span class="sr-only"> « <?= esc($a['title']) ?> »</span></button></form>
                <form method="post" action="<?= site_url('annonces/' . $a['id'] . '/supprimer') ?>" onsubmit="return confirm('Supprimer cette annonce ?')"><?= csrf_field() ?><button class="btn btn-ghost text-danger"><?= icon('trash') ?> Supprimer<span class="sr-only"> « <?= esc($a['title']) ?> »</span></button></form>
            </div>
            <?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>
<?php endif ?>
<?= $this->endSection() ?>
