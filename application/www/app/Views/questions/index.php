<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
$stBadge = ['ouverte' => 'badge-warning', 'en_cours' => 'badge-info', 'resolue' => 'badge-success', 'fermee' => 'badge-muted'];
$errors  = session('errors') ?? [];
$openId  = (int) session('open_reply');
?>
<h1 class="text-3xl">Tâches et questions</h1>
<div class="mt-5"><?= view('partials/tabs_taches', ['tab' => $tab]) ?></div>

<div class="mb-5 flex flex-wrap items-center gap-3">
    <?php if (! $isSecretary): ?>
    <a href="<?= site_url('questions/nouvelle') ?>" class="btn btn-primary"><?= icon('chat') ?> Poser une question</a>
    <?php endif ?>
    <nav aria-label="Filtrer les questions" class="flex flex-wrap gap-2">
        <?php foreach ([null => 'Toutes', 'ouvertes' => 'En attente (' . $counts['open'] . ')', 'repondues' => 'Traitées'] as $k => $lbl): ?>
        <a href="<?= site_url('questions' . ($k ? '?statut=' . $k : '')) ?>" class="btn <?= $filter === ($k ?: null) ? 'btn-secondary' : 'btn-ghost' ?>" <?= $filter === ($k ?: null) ? 'aria-current="page"' : '' ?>><?= esc($lbl) ?></a>
        <?php endforeach ?>
    </nav>
</div>

<?php if (! $questions): ?>
<p class="card"><?= $isSecretary ? 'Aucune question des délégués dans cette liste.' : 'Vous n’avez pas encore posé de question. Le secrétaire vous répondra ici, et vous serez prévenu.' ?></p>
<?php else: ?>
<ul class="grid gap-4" role="list">
<?php foreach ($questions as $q): ?>
    <li id="question-<?= $q['id'] ?>" class="card <?= $q['priority'] === 'urgente' && in_array($q['status'], \App\Domain\Questions::OPEN, true) ? 'border-2 border-danger' : '' ?>">
        <article aria-labelledby="q-titre-<?= $q['id'] ?>">
            <p class="m-0 flex flex-wrap gap-2">
                <span class="badge <?= $stBadge[$q['status']] ?>"><?= esc(\App\Domain\Questions::STATUSES[$q['status']]) ?></span>
                <?php if ($q['priority'] === 'urgente'): ?><span class="badge badge-danger">Urgente</span><?php endif ?>
            </p>
            <h2 id="q-titre-<?= $q['id'] ?>" class="mt-2 text-xl"><?= esc($q['subject']) ?></h2>
            <p class="m-0 text-muted"><?= $isSecretary ? esc($q['author_name']) . ', ' : '' ?>le <?= esc(date_fr($q['created_at'], "d MMMM 'à' HH:mm")) ?></p>
            <p class="mt-3 whitespace-pre-line"><?= esc($q['body']) ?></p>

            <?php if ($q['response']): ?>
            <div class="mt-4 rounded-xl border-l-4 border-success bg-success-soft p-4">
                <p class="m-0 font-bold">Réponse<?= $q['responder_name'] ? ' de ' . esc($q['responder_name']) : '' ?><?= $q['responded_at'] ? ', le ' . esc(date_fr($q['responded_at'], 'd MMMM')) : '' ?></p>
                <p class="m-0 mt-1 whitespace-pre-line"><?= esc($q['response']) ?></p>
            </div>
            <?php endif ?>

            <div class="mt-4 flex flex-wrap items-start gap-2">
                <?php if ($isSecretary && $q['status'] !== 'fermee'): ?>
                <details class="w-full" <?= $openId === (int) $q['id'] ? 'open' : '' ?>>
                    <summary class="btn btn-primary"><?= $q['response'] ? 'Modifier la réponse' : 'Répondre' ?></summary>
                    <form method="post" action="<?= site_url('questions/' . $q['id'] . '/reponse') ?>" class="mt-3" novalidate>
                        <?= csrf_field() ?>
                        <div class="field">
                            <label class="label" for="response-<?= $q['id'] ?>">Votre réponse</label>
                            <textarea class="input min-h-32" id="response-<?= $q['id'] ?>" name="response" <?= $openId === (int) $q['id'] && isset($errors['response']) ? 'aria-invalid="true" aria-describedby="err-response-' . $q['id'] . '"' : '' ?>><?= esc($openId === (int) $q['id'] ? old('response') : (string) $q['response']) ?></textarea>
                            <?php if ($openId === (int) $q['id'] && isset($errors['response'])): ?><p id="err-response-<?= $q['id'] ?>" class="error-text"><?= esc($errors['response']) ?></p><?php endif ?>
                        </div>
                        <fieldset class="field">
                            <legend class="label">Après envoi, la question est</legend>
                            <div class="flex flex-wrap gap-2">
                                <label class="flex min-h-11 items-center gap-2 rounded-xl border-2 border-line px-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft"><input type="radio" name="status" value="resolue" class="size-5" checked> Résolue</label>
                                <label class="flex min-h-11 items-center gap-2 rounded-xl border-2 border-line px-4 has-[:checked]:border-brand has-[:checked]:bg-brand-soft"><input type="radio" name="status" value="en_cours" class="size-5"> En cours, suite à venir</label>
                            </div>
                        </fieldset>
                        <button type="submit" class="btn btn-primary">Envoyer la réponse</button>
                    </form>
                </details>
                <?php if ($q['status'] === 'ouverte'): ?>
                <form method="post" action="<?= site_url('questions/' . $q['id'] . '/statut') ?>"><?= csrf_field() ?><input type="hidden" name="status" value="en_cours"><button class="btn btn-secondary">Je m’en occupe</button></form>
                <?php endif ?>
                <form method="post" action="<?= site_url('questions/' . $q['id'] . '/statut') ?>"><?= csrf_field() ?><input type="hidden" name="status" value="fermee"><button class="btn btn-ghost">Fermer la question</button></form>
                <?php endif ?>
                <form method="post" action="<?= site_url('questions/' . $q['id'] . '/supprimer') ?>" onsubmit="return confirm('Supprimer cette question ?')" class="ml-auto">
                    <?= csrf_field() ?><button class="btn btn-danger"><?= icon('trash') ?> Supprimer<span class="sr-only"> la question « <?= esc($q['subject']) ?> »</span></button>
                </form>
            </div>
        </article>
    </li>
<?php endforeach ?>
</ul>
<?php endif ?>
<?= $this->endSection() ?>
