<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
$errors  = session('errors') ?? [];
$roleLbl = ['secretaire' => 'Secrétaire', 'tresorier' => 'Trésorier', 'collecteur' => 'Délégué'];
$me      = (int) session('user_id');
?>
<h1 class="<?= $other ? 'sr-only lg:not-sr-only' : '' ?> text-3xl">Messages</h1>

<div class="mt-4 grid gap-6 lg:grid-cols-[18rem_1fr]">
    <!-- Conversations : masquées sur téléphone quand un fil est ouvert -->
    <section aria-labelledby="titre-conv" class="<?= $other ? 'hidden lg:block' : '' ?>">
        <h2 id="titre-conv" class="section-title">Conversations</h2>
        <?php if (! $other && $contacts): ?>
        <form method="post" action="<?= site_url('messages/nouveau') ?>" class="card mb-4">
            <?= csrf_field() ?>
            <label class="label" for="to">Écrire à</label>
            <div class="flex gap-2">
                <select class="input" id="to" name="to"><option value="">Choisir une personne</option><?php foreach ($contacts as $c): ?><option value="<?= $c['id'] ?>"><?= esc($c['name'] . ' (' . ($roleLbl[$c['role']] ?? '') . ')') ?></option><?php endforeach ?></select>
                <button class="btn btn-primary shrink-0">Écrire</button>
            </div>
        </form>
        <?php endif ?>
        <?php if (! $conversations): ?>
        <p class="card">Aucune conversation pour le moment.</p>
        <?php else: ?>
        <ul class="grid gap-2" role="list">
            <?php foreach ($conversations as $c): ?>
            <li>
                <a href="<?= site_url('messages/' . $c['user_id']) ?>" class="todo-card" <?= $other && (int) $other['id'] === $c['user_id'] ? 'aria-current="page"' : '' ?>>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2"><span class="font-bold"><?= esc($c['name']) ?></span><?php if ($c['unread']): ?><span class="badge badge-danger"><?= $c['unread'] ?><span class="sr-only"> non lu<?= $c['unread'] > 1 ? 's' : '' ?></span></span><?php endif ?></span>
                        <span class="block truncate text-muted"><?= $c['mine'] ? 'Vous : ' : '' ?><?= esc(mb_substr($c['body'], 0, 80)) ?></span>
                    </span>
                </a>
            </li>
            <?php endforeach ?>
        </ul>
        <?php endif ?>
    </section>

    <?php if ($other): ?>
    <section aria-labelledby="titre-fil" class="flex min-w-0 flex-col">
        <p class="lg:hidden"><a href="<?= site_url('messages') ?>">Toutes les conversations</a></p>
        <h2 id="titre-fil" class="section-title"><?= esc($other['name']) ?> <span class="text-base font-normal text-muted"><?= esc($roleLbl[$other['role']] ?? '') ?></span></h2>
        <div class="card">
            <?php if (! $thread): ?>
            <p class="text-muted">Aucun message. Écrivez le premier ci-dessous.</p>
            <?php else: ?>
            <ol class="grid gap-3" role="list" aria-label="Fil de la conversation">
                <?php foreach ($thread as $msg): $mine = (int) $msg['sender_id'] === $me; ?>
                <li id="message-<?= $msg['id'] ?>" class="flex <?= $mine ? 'justify-end' : '' ?>">
                    <div class="max-w-[85%] rounded-2xl px-4 py-2 <?= $mine ? 'bg-brand text-brand-ink' : 'bg-surface-2' ?>">
                        <p class="sr-only"><?= $mine ? 'Vous' : esc($other['first_name']) ?> :</p>
                        <p class="m-0 whitespace-pre-line"><?= esc($msg['body']) ?></p>
                        <p class="m-0 mt-1 text-sm opacity-80"><?= esc(date_fr($msg['created_at'], "d MMM 'à' HH:mm")) ?><?= $mine && (int) $msg['is_read'] ? ' · lu' : '' ?></p>
                        <?php if (! empty($canDelete)): ?>
                        <form method="post" action="<?= site_url('messages/supprimer/' . $msg['id']) ?>" onsubmit="return confirm('Supprimer ce message ?')">
                            <?= csrf_field() ?><input type="hidden" name="back" value="<?= (int) $other['id'] ?>">
                            <button class="text-sm underline">Supprimer<span class="sr-only"> ce message</span></button>
                        </form>
                        <?php endif ?>
                    </div>
                </li>
                <?php endforeach ?>
            </ol>
            <?php endif ?>
        </div>
        <form method="post" action="<?= site_url('messages/' . $other['id']) ?>" class="card mt-4" id="nouveau" novalidate>
            <?= csrf_field() ?>
            <label class="label" for="body">Votre message à <?= esc($other['first_name']) ?></label>
            <textarea class="input min-h-28"<?= field_attrs('body', $errors) ?> maxlength="5000"><?= esc(old('body', '')) ?></textarea>
            <?= field_error('body', $errors) ?>
            <button class="btn btn-primary mt-3">Envoyer</button>
        </form>
    </section>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
