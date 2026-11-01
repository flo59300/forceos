<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
use App\Controllers\Notes;

$errors = session('errors') ?? [];
$v      = static fn (string $f, $d = '') => old($f, $edit[$f] ?? $d);
$tone   = ['terrain' => 'badge-danger', 'suivi' => 'badge-info', 'personnel' => 'badge-neutral', 'reunion' => 'badge-success'];
?>
<h1 class="text-3xl">Mes notes</h1>
<p class="mt-1 text-lg text-muted">Un carnet personnel : vous seul voyez ces notes. <?= esc(plural($total, 'note', 'notes')) ?> en tout.</p>

<section class="card mt-6 max-w-3xl" aria-labelledby="t-form" id="formulaire">
    <h2 id="t-form" class="section-title"><?= $edit ? 'Modifier la note' : 'Nouvelle note' ?></h2>
    <?= view('partials/form_errors') ?>
    <form method="post" action="<?= $edit ? site_url('notes/' . $edit['id']) : site_url('notes') ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="title">Titre <span class="font-normal text-muted">(obligatoire)</span></label>
            <input class="input" type="text" maxlength="255" required<?= field_attrs('title', $errors) ?> value="<?= esc($v('title'), 'attr') ?>">
            <?= field_error('title', $errors) ?>
        </div>
        <div class="field">
            <label class="label" for="content">Contenu</label>
            <textarea class="input min-h-32"<?= field_attrs('content', $errors) ?>><?= esc($v('content')) ?></textarea>
            <?= field_error('content', $errors) ?>
        </div>
        <fieldset class="field">
            <legend class="label">Catégorie</legend>
            <div class="flex flex-wrap gap-2">
                <?php foreach (Notes::CATEGORIES as $k => $l): ?>
                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border-2 border-line px-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft"><input type="radio" name="category" value="<?= $k ?>" class="size-5" <?= $v('category', 'terrain') === $k ? 'checked' : '' ?>> <?= esc($l) ?></label>
                <?php endforeach ?>
            </div>
        </fieldset>
        <div class="flex flex-wrap gap-3">
            <button class="btn btn-primary"><?= $edit ? 'Enregistrer' : 'Ajouter la note' ?></button>
            <?php if ($edit): ?><a href="<?= site_url('notes') ?>" class="btn btn-ghost">Annuler</a><?php endif ?>
        </div>
    </form>
</section>

<section class="mt-8" aria-labelledby="t-liste">
    <h2 id="t-liste" class="section-title">Mes notes</h2>
    <?php if ($total): ?>
    <form method="get" action="<?= site_url('notes') ?>" class="mb-4 flex flex-wrap items-end gap-2" role="search">
        <div class="min-w-0 flex-1 basis-56"><label class="label" for="q">Rechercher</label><input class="input" type="search" id="q" name="q" value="<?= esc($q, 'attr') ?>"></div>
        <div><label class="label" for="categorie">Catégorie</label>
            <select class="input" id="categorie" name="categorie"><option value="">Toutes</option>
                <?php foreach (Notes::CATEGORIES as $k => $l): ?><option value="<?= $k ?>" <?= $cat === $k ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach ?>
            </select></div>
        <button class="btn btn-secondary">Filtrer</button>
        <?php if ($q !== '' || $cat !== ''): ?><a class="btn btn-ghost" href="<?= site_url('notes') ?>">Tout afficher</a><?php endif ?>
    </form>
    <?php endif ?>
    <?php if (! $notes): ?>
    <div class="card"><p class="m-0 text-muted"><?= $total ? 'Aucune note ne correspond.' : 'Aucune note. Commencez par en écrire une ci-dessus.' ?></p></div>
    <?php else: ?>
    <ul class="grid gap-4 grid-cols-[repeat(auto-fill,minmax(min(100%,20rem),1fr))]" role="list">
        <?php foreach ($notes as $n): ?>
        <li id="note-<?= $n['id'] ?>" class="card scroll-mt-24 flex flex-col <?= $n['pinned'] ? 'border-l-4 border-brand' : '' ?>">
            <div class="flex flex-wrap items-start gap-2">
                <h3 class="m-0 min-w-0 flex-1 break-words text-xl"><?= esc($n['title']) ?></h3>
                <?php if ($n['pinned']): ?><span class="badge badge-info">Épinglée</span><?php endif ?>
                <span class="badge <?= $tone[$n['category']] ?? 'badge-neutral' ?>"><?= esc(Notes::CATEGORIES[$n['category']] ?? $n['category']) ?></span>
            </div>
            <?php if ($n['content']): ?><p class="mt-2 whitespace-pre-line break-words"><?= esc($n['content']) ?></p><?php endif ?>
            <p class="mt-auto pt-2 text-sm text-muted">Modifiée le <?= esc(date_fr($n['updated_at'], "d MMMM y 'à' HH:mm")) ?></p>
            <div class="mt-2 flex flex-wrap gap-2">
                <a class="btn btn-secondary" href="<?= site_url('notes') . '?modifier=' . $n['id'] ?>#formulaire"><?= icon('edit') ?> Modifier<span class="sr-only"> « <?= esc($n['title']) ?> »</span></a>
                <form method="post" action="<?= site_url('notes/' . $n['id'] . '/epingler') ?>"><?= csrf_field() ?><button class="btn btn-ghost"><?= $n['pinned'] ? 'Désépingler' : 'Épingler' ?><span class="sr-only"> « <?= esc($n['title']) ?> »</span></button></form>
                <form method="post" action="<?= site_url('notes/' . $n['id'] . '/supprimer') ?>" onsubmit="return confirm('Supprimer cette note ?')"><?= csrf_field() ?><button class="btn btn-ghost text-danger"><?= icon('trash') ?> Supprimer<span class="sr-only"> « <?= esc($n['title']) ?> »</span></button></form>
            </div>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
