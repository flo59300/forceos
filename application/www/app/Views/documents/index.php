<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php use App\Domain\KnowledgeBase; ?>
<h1 class="text-3xl">Base documentaire</h1>
<p class="mt-1 text-lg text-muted">Accords, conventions et notes du site<?= $shared ? ', partagés entre tous les sites' : '' ?> : <?= esc(plural($stats['docs'], 'document')) ?>, <?= esc(plural($stats['chunks'], 'passage')) ?>.</p>

<?php if ($canManage): ?>
<nav class="tabs mt-5 max-w-md" aria-label="Rubriques de la base documentaire">
    <a class="tab" href="<?= site_url('documents') ?>" <?= $tab === 'recherche' ? 'aria-current="page"' : '' ?>>Recherche</a>
    <a class="tab" href="<?= site_url('documents') ?>?onglet=documents" <?= $tab === 'documents' ? 'aria-current="page"' : '' ?>>Documents (<?= $stats['docs'] ?>)</a>
</nav>
<?php endif ?>

<?php if ($tab === 'recherche'): ?>
<form method="get" action="<?= site_url('documents') ?>" role="search" class="card mt-6">
    <label class="label text-lg" for="q">Votre question ou des mots-clés</label>
    <div class="flex flex-wrap gap-2">
        <input class="input min-w-0 flex-1 basis-64 text-lg" type="search" id="q" name="q" maxlength="200" value="<?= esc($q, 'attr') ?>" placeholder="Prime d’ancienneté, congés pour enfant malade…">
        <button class="btn btn-primary"><?= icon('search') ?> Rechercher</button>
    </div>
</form>

<?php if ($q !== ''): ?>
<section class="mt-6" aria-labelledby="t-resultats">
    <h2 id="t-resultats" class="section-title" tabindex="-1"><?= $results ? esc(plural(count($results), 'passage trouvé', 'passages trouvés')) : 'Aucun résultat' ?> pour « <?= esc($q) ?> »</h2>
    <?php if ($answer): ?>
    <div class="card border-l-4 border-brand">
        <h3 class="m-0 text-lg">L’essentiel</h3>
        <p class="mt-2 text-lg"><?= esc($answer) ?></p>
        <p class="m-0 text-sm text-muted">Phrases extraites des documents ci-dessous, sans reformulation. Vérifiez toujours dans le document d’origine.</p>
    </div>
    <?php endif ?>
    <?php if (! $results): ?>
    <div class="card"><p class="m-0">Essayez d’autres mots, plus simples ou plus courts.<?= $stats['docs'] ? '' : ' La base ne contient encore aucun document.' ?></p></div>
    <?php else: ?>
    <ol class="mt-4 grid gap-3" role="list">
        <?php foreach ($results as $i => $r): $file = KnowledgeBase::locate($r['file_path']) !== null; ?>
        <li class="card" x-data="{ open: false }">
            <div class="flex flex-wrap items-start gap-2">
                <h3 class="m-0 min-w-0 flex-1 text-lg"><span class="text-muted"><?= $i + 1 ?>.</span> <?= esc($r['doc_title']) ?> <span class="font-normal text-muted">(passage <?= (int) $r['chunk_index'] + 1 ?>)</span></h3>
                <span class="badge badge-neutral"><?= esc(KnowledgeBase::CATEGORIES[$r['category']] ?? 'Général') ?></span>
                <span class="badge badge-success" title="Pertinence">Pertinence <?= (int) round($r['final_score'] * 100) ?>&nbsp;%</span>
            </div>
            <div class="kb-text mt-2 overflow-hidden" :class="open ? '' : 'max-h-40'" id="passage-<?= $i ?>"><?= KnowledgeBase::render($r['chunk_text'], $q) ?></div>
            <div class="mt-2 flex flex-wrap gap-2">
                <button type="button" class="btn btn-ghost px-0" x-cloak @click="open = !open" :aria-expanded="open.toString()" aria-controls="passage-<?= $i ?>" x-text="open ? 'Réduire le passage' : 'Lire tout le passage'"></button>
                <?php if ($file): ?><a class="btn btn-ghost" href="<?= site_url('documents/' . $r['doc_id'] . '/telecharger') ?>">Télécharger <?= esc($r['doc_filename']) ?></a><?php endif ?>
            </div>
        </li>
        <?php endforeach ?>
    </ol>
    <p class="mt-3 text-sm text-muted">Recherche effectuée en <?= $ms ?>&nbsp;ms.</p>
    <?php endif ?>
</section>
<?php elseif (! $stats['docs']): ?>
<div class="card mt-6"><p class="m-0"><?= $canManage ? 'La base est vide. Ajoutez vos premiers documents dans l’onglet « Documents ».' : 'La base est vide pour le moment.' ?></p></div>
<?php endif ?>
<?php endif ?>

<?php if ($tab === 'documents'): ?>
<section class="card mt-6 max-w-3xl" aria-labelledby="t-ajout">
    <h2 id="t-ajout" class="section-title">Ajouter des documents</h2>
    <form method="post" action="<?= site_url('documents') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="kb_files">Fichiers</label>
            <span class="hint" id="aide-fichiers">Word (.docx), texte (.txt, .md) ou tableau (.csv) ; cinq fichiers de 5 Mo au plus.</span>
            <input class="input" type="file" id="kb_files" name="kb_files[]" accept=".txt,.md,.docx,.csv" multiple required aria-describedby="aide-fichiers">
        </div>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,14rem),1fr))]">
            <div class="field"><label class="label" for="doc_title">Titre <span class="font-normal text-muted">(pour un seul fichier)</span></label><input class="input" type="text" id="doc_title" name="doc_title" maxlength="255" placeholder="Le nom du fichier par défaut"></div>
            <div class="field"><label class="label" for="doc_category">Catégorie</label><select class="input" id="doc_category" name="doc_category"><?php foreach (KnowledgeBase::CATEGORIES as $k => $l): ?><option value="<?= $k ?>"><?= esc($l) ?></option><?php endforeach ?></select></div>
        </div>
        <button class="btn btn-primary">Ajouter et indexer</button>
    </form>
</section>

<section class="mt-8" aria-labelledby="t-docs">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="t-docs" class="section-title m-0">Documents indexés</h2>
        <?php if ($documents): ?><form method="post" action="<?= site_url('documents/reindexer') ?>"><?= csrf_field() ?><button class="btn btn-ghost">Recalculer l’index</button></form><?php endif ?>
    </div>
    <?php if (! $documents): ?>
    <div class="card mt-3"><p class="m-0 text-muted">Aucun document pour le moment.</p></div>
    <?php else: ?>
    <ul class="mt-3 grid gap-3 grid-cols-[repeat(auto-fill,minmax(min(100%,20rem),1fr))]" role="list">
        <?php foreach ($documents as $d): $file = KnowledgeBase::locate($d['file_path']) !== null; ?>
        <li class="card">
            <h3 class="m-0 break-words text-lg"><?= esc($d['title']) ?></h3>
            <p class="m-0 break-words text-muted"><?= esc($d['filename']) ?> · <?= number_format($d['file_size'] / 1024, 1, ',', ' ') ?>&nbsp;Ko</p>
            <p class="m-0 mt-1 flex flex-wrap gap-2"><span class="badge badge-neutral"><?= esc(KnowledgeBase::CATEGORIES[$d['category']] ?? 'Général') ?></span><span class="badge badge-muted"><?= esc(plural((int) $d['total_chunks'], 'passage')) ?></span><?php if ($shared): ?><span class="badge badge-muted"><?= esc($d['site_name']) ?></span><?php endif ?></p>
            <p class="m-0 mt-1 text-sm text-muted">Ajouté par <?= esc($d['uploader'] ?: 'un ancien compte') ?> le <?= esc(date_fr($d['created_at'], 'd MMMM y')) ?></p>
            <div class="mt-3 flex flex-wrap gap-2">
                <?php if ($file): ?><a class="btn btn-secondary" href="<?= site_url('documents/' . $d['id'] . '/telecharger') ?>">Télécharger<span class="sr-only"> <?= esc($d['title']) ?></span></a><?php endif ?>
                <form method="post" action="<?= site_url('documents/' . $d['id'] . '/supprimer') ?>" onsubmit="return confirm('Supprimer ce document et ses passages ?')"><?= csrf_field() ?><button class="btn btn-ghost text-danger"><?= icon('trash') ?> Supprimer<span class="sr-only"> <?= esc($d['title']) ?></span></button></form>
            </div>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>
<?php endif ?>
<?= $this->endSection() ?>
