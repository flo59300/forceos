<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
use App\Domain\Permanences;

$errors = session('errors') ?? [];
$past   = $p['perm_date'] < date('Y-m-d');
$isDay  = $p['perm_date'] <= date('Y-m-d');
$stBadge = ['pending' => 'badge-warning', 'email_sent' => 'badge-info', 'confirmed' => 'badge-success'];
?>
<p><a href="<?= site_url('permanences') ?>">Toutes les permanences</a></p>
<div class="flex flex-wrap items-center gap-3">
    <h1 class="text-3xl"><?= esc($p['title']) ?></h1>
    <?php if (! $p['active']): ?><span class="badge badge-muted">Désactivée</span><?php endif ?>
    <?php if ($p['quota_reached']): ?><span class="badge badge-danger">Complet</span><?php endif ?>
</div>
<p class="mt-1 text-lg"><strong><?= esc(ucfirst(date_fr($p['perm_date'], 'EEEE d MMMM y'))) ?></strong><?= $p['location'] ? ' · ' . esc($p['location']) : '' ?></p>
<?php if ($p['description']): ?><p class="mt-2 whitespace-pre-line text-muted"><?= esc($p['description']) ?></p><?php endif ?>

<div class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem]">
<div class="min-w-0">

<!-- Créneaux -->
<section class="card" aria-labelledby="t-creneaux" id="creneaux">
    <h2 id="t-creneaux" class="section-title">Créneaux : <?= $p['total_bookings'] ?> inscrits sur <?= $p['capacity'] ?> places<?= $p['max_participants'] ? ' (quota global)' : '' ?></h2>
    <div class="meter mb-4 h-3" role="img" aria-label="Remplie à <?= min(100, $p['fill_pct']) ?> pour cent"><span class="<?= $p['fill_pct'] >= 90 ? 'bg-danger!' : '' ?>" style="width: <?= min(100, $p['fill_pct']) ?>%"></span></div>
    <?php if (! $p['slots']): ?><p class="text-muted">Aucun créneau.</p><?php endif ?>
    <ul class="grid gap-2 grid-cols-[repeat(auto-fill,minmax(min(100%,11rem),1fr))]" role="list">
        <?php foreach ($p['slots'] as $s): $full = (int) $s['nb_bookings'] >= (int) $s['max_bookings']; ?>
        <li class="rounded-xl border-2 p-3 <?= $full ? 'border-danger bg-danger-soft' : 'border-line' ?>">
            <p class="m-0 font-bold"><?= substr($s['start_time'], 0, 5) ?> à <?= substr($s['end_time'], 0, 5) ?></p>
            <p class="m-0 text-muted"><?= $s['nb_bookings'] ?> sur <?= $s['max_bookings'] ?><?= $full ? ', complet' : '' ?></p>
            <?php if ($secretary && ! (int) $s['nb_bookings']): ?>
            <form method="post" action="<?= site_url('permanences/creneaux/' . $s['id'] . '/supprimer') ?>" onsubmit="return confirm('Supprimer ce créneau ?')"><?= csrf_field() ?><button class="btn btn-ghost min-h-9 px-0 text-danger">Supprimer<span class="sr-only"> le créneau de <?= substr($s['start_time'], 0, 5) ?></span></button></form>
            <?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
    <?php if ($secretary): ?>
    <details class="mt-4">
        <summary class="btn btn-secondary">Ajouter un créneau</summary>
        <form method="post" action="<?= site_url('permanences/' . $p['id'] . '/creneaux') ?>" class="mt-3 flex flex-wrap items-end gap-3">
            <?= csrf_field() ?>
            <div><label class="label" for="new_slot_start">Début</label><input class="input" type="time" step="900" id="new_slot_start" name="new_slot_start" required></div>
            <div><label class="label" for="new_slot_end">Fin</label><input class="input" type="time" step="900" id="new_slot_end" name="new_slot_end" required></div>
            <div><label class="label" for="new_slot_max">Places</label><input class="input w-24" type="number" min="1" max="50" value="3" id="new_slot_max" name="new_slot_max"></div>
            <button class="btn btn-primary">Ajouter</button>
        </form>
    </details>
    <?php endif ?>
</section>

<!-- Inscrits -->
<section class="card mt-6" aria-labelledby="t-inscrits" id="inscrits">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="t-inscrits" class="section-title m-0">Inscriptions (<?= $p['total_bookings'] ?>)</h2>
        <?php if ($secretary && $p['pending_count']): ?>
        <form method="post" action="<?= site_url('permanences/' . $p['id'] . '/tout-valider') ?>" onsubmit="return confirm('Valider les <?= $p['pending_count'] ?> demandes en attente ?')"><?= csrf_field() ?><button class="btn btn-primary">Tout valider (<?= $p['pending_count'] ?>)</button></form>
        <?php endif ?>
    </div>
    <?php if (! $p['bookings']): ?>
    <p class="mt-3 text-muted">Personne n’est encore inscrit.</p>
    <?php else: ?>
    <ul class="mt-3 grid gap-3" role="list">
        <?php foreach ($p['bookings'] as $b): ?>
        <li id="inscription-<?= $b['id'] ?>" class="scroll-mt-24 rounded-xl border border-line p-3 <?= $b['status'] === 'pending' ? 'border-2 border-warning' : '' ?>">
            <div class="flex flex-wrap items-start gap-2">
                <p class="m-0 min-w-0 flex-1 basis-60"><span class="font-bold"><?= substr($b['start_time'], 0, 5) ?> à <?= substr($b['end_time'], 0, 5) ?></span> · <strong><?= esc($b['name']) ?></strong><?= $b['subject'] ? '<br><span class="text-muted">Motif : ' . esc($b['subject']) . '</span>' : '' ?></p>
                <span class="badge <?= $stBadge[$b['status']] ?? 'badge-neutral' ?>"><?= esc(Permanences::STATUS[$b['status']]) ?></span>
                <?php if ($b['booked_by']): ?><span class="badge badge-muted">Saisie par <?= esc($b['booked_by_name'] ?: 'le secrétariat') ?></span><?php endif ?>
                <?php if ((string) $b['attended'] === '1'): ?><span class="badge badge-success">Présent</span><?php elseif ((string) $b['attended'] === '0'): ?><span class="badge badge-danger">Absent</span><?php endif ?>
            </div>
            <?php if ($secretary): ?>
            <?php if ($b['phone'] || $b['email']): ?><p class="m-0 mt-1 break-words text-muted"><?= $b['phone'] ? '<a href="tel:' . esc(preg_replace('/[^\d+]/', '', $b['phone']), 'attr') . '">' . esc($b['phone']) . '</a>' : '' ?><?= $b['phone'] && $b['email'] ? ' · ' : '' ?><?= $b['email'] ? '<a href="mailto:' . esc($b['email'], 'attr') . '">' . esc($b['email']) . '</a>' : '' ?></p><?php endif ?>
            <?php if ($b['notes']): ?><p class="m-0 mt-1 italic">Note : <?= esc($b['notes']) ?></p><?php endif ?>
            <div class="mt-2 flex flex-wrap gap-2">
                <?php $u = site_url('permanences/inscriptions/' . $b['id']); $who = '<span class="sr-only"> ' . esc($b['name']) . '</span>'; ?>
                <?php if ($b['status'] === 'pending'): ?>
                <form method="post" action="<?= $u ?>/valider"><?= csrf_field() ?><button class="btn btn-primary min-h-10">Valider<?= $who ?></button></form>
                <form method="post" action="<?= $u ?>/refuser" onsubmit="return confirm('Refuser cette demande ?')"><?= csrf_field() ?><button class="btn btn-ghost min-h-10 text-danger">Refuser<?= $who ?></button></form>
                <?php else: ?>
                <?php if ($b['status'] === 'confirmed' && $isDay): ?>
                <form method="post" action="<?= $u ?>/presence"><?= csrf_field() ?><input type="hidden" name="attended" value="1"><button class="btn btn-secondary min-h-10" aria-pressed="<?= (string) $b['attended'] === '1' ? 'true' : 'false' ?>">Présent<?= $who ?></button></form>
                <form method="post" action="<?= $u ?>/presence"><?= csrf_field() ?><input type="hidden" name="attended" value="0"><button class="btn btn-secondary min-h-10" aria-pressed="<?= (string) $b['attended'] === '0' ? 'true' : 'false' ?>">Absent<?= $who ?></button></form>
                <?php endif ?>
                <form method="post" action="<?= $u ?>/annuler" onsubmit="return confirm('Annuler cette inscription ?')"><?= csrf_field() ?><button class="btn btn-ghost min-h-10 text-danger">Annuler l’inscription<?= $who ?></button></form>
                <?php endif ?>
                <details class="w-full">
                    <summary class="btn btn-ghost min-h-10 px-0"><?= $b['notes'] ? 'Modifier la note' : 'Ajouter une note' ?><?= $who ?></summary>
                    <form method="post" action="<?= $u ?>/note" class="mt-2 flex flex-wrap items-end gap-2">
                        <?= csrf_field() ?>
                        <div class="min-w-0 flex-1 basis-60"><label class="label" for="note-<?= $b['id'] ?>">Note interne (visible du secrétariat)</label><input class="input" type="text" maxlength="1000" id="note-<?= $b['id'] ?>" name="notes" value="<?= esc($b['notes'] ?? '', 'attr') ?>"></div>
                        <button class="btn btn-secondary">Enregistrer la note</button>
                    </form>
                </details>
            </div>
            <?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</section>

<?php if ($secretary && $p['slots']): ?>
<section class="card mt-6" aria-labelledby="t-inscrire" id="inscrire">
    <h2 id="t-inscrire" class="section-title">Inscrire quelqu’un</h2>
    <p class="mt-0 text-muted">Pour une demande reçue par téléphone ou au local : l’inscription est confirmée d’office.</p>
    <?= view('partials/form_errors') ?>
    <form method="post" action="<?= site_url('permanences/' . $p['id'] . '/inscriptions') ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="slot_id">Créneau</label>
            <select class="input"<?= field_attrs('slot_id', $errors) ?> required>
                <option value="">Choisir un créneau</option>
                <?php foreach ($p['slots'] as $s): $left = (int) $s['max_bookings'] - (int) $s['nb_bookings']; ?>
                <option value="<?= $s['id'] ?>" <?= $left <= 0 ? 'disabled' : '' ?> <?= (string) old('slot_id') === (string) $s['id'] ? 'selected' : '' ?>><?= substr($s['start_time'], 0, 5) ?> à <?= substr($s['end_time'], 0, 5) ?> (<?= $left > 0 ? plural($left, 'place libre', 'places libres') : 'complet' ?>)</option>
                <?php endforeach ?>
            </select>
            <?= field_error('slot_id', $errors) ?>
        </div>
        <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,14rem),1fr))]">
            <div class="field"><label class="label" for="booking_name">Nom et prénom</label><input class="input" type="text" maxlength="255" required<?= field_attrs('booking_name', $errors) ?> value="<?= esc(old('booking_name') ?? '', 'attr') ?>"><?= field_error('booking_name', $errors) ?></div>
            <div class="field"><label class="label" for="booking_phone">Téléphone <span class="font-normal text-muted">(facultatif)</span></label><input class="input" type="tel" maxlength="50"<?= field_attrs('booking_phone', $errors) ?> value="<?= esc(old('booking_phone') ?? '', 'attr') ?>"></div>
            <div class="field"><label class="label" for="booking_email">E-mail <span class="font-normal text-muted">(facultatif)</span></label><input class="input" type="email" maxlength="255"<?= field_attrs('booking_email', $errors) ?> value="<?= esc(old('booking_email') ?? '', 'attr') ?>"><?= field_error('booking_email', $errors) ?></div>
            <div class="field"><label class="label" for="booking_subject">Motif <span class="font-normal text-muted">(facultatif)</span></label><input class="input" type="text" maxlength="255"<?= field_attrs('booking_subject', $errors) ?> value="<?= esc(old('booking_subject') ?? '', 'attr') ?>"></div>
        </div>
        <div class="field"><label class="label" for="booking_notes">Note interne <span class="font-normal text-muted">(facultatif)</span></label><input class="input" type="text" maxlength="1000"<?= field_attrs('booking_notes', $errors) ?> value="<?= esc(old('booking_notes') ?? '', 'attr') ?>"></div>
        <button class="btn btn-primary">Inscrire</button>
    </form>
</section>
<?php endif ?>
</div>

<!-- Colonne d'actions -->
<aside class="grid content-start gap-6" aria-label="Partage et gestion">
    <?php if ($p['active'] && ! $past): ?>
    <section class="card" aria-labelledby="t-partage" x-data="{ copie: false }">
        <h2 id="t-partage" class="section-title">Réservation en ligne</h2>
        <div class="qr mx-auto w-48 rounded-xl bg-white p-2" data-qr="<?= esc($link, 'attr') ?>" role="img" aria-label="QR code menant au formulaire de réservation"></div>
        <p class="mt-3 break-all text-sm"><a href="<?= esc($link, 'attr') ?>"><?= esc($link) ?></a></p>
        <div class="mt-3 grid gap-2">
            <button type="button" class="btn btn-secondary" @click="navigator.clipboard.writeText(<?= esc(json_encode($link), 'attr') ?>).then(() => { copie = true; setTimeout(() => copie = false, 3000) })">Copier le lien</button>
            <p class="sr-only" role="status" x-text="copie ? 'Lien copié.' : ''"></p>
            <a class="btn btn-secondary" href="<?= site_url('permanences/' . $p['id'] . '/affiche') ?>" target="_blank">Imprimer l’affiche <span class="sr-only">(nouvelle fenêtre)</span></a>
            <?php if ($seriesLink): ?>
            <a class="btn btn-ghost" href="<?= site_url('permanences/' . $p['id'] . '/affiche?serie=1') ?>" target="_blank">Affiche de toute la série <span class="sr-only">(nouvelle fenêtre)</span></a>
            <?php endif ?>
        </div>
    </section>
    <?php endif ?>
    <?php if ($secretary): ?>
    <section class="card" aria-labelledby="t-gerer">
        <h2 id="t-gerer" class="section-title">Gérer</h2>
        <div class="grid gap-2">
            <a class="btn btn-secondary" href="<?= site_url('permanences/' . $p['id'] . '/modifier') ?>"><?= icon('edit') ?> Modifier</a>
            <a class="btn btn-secondary" href="<?= site_url('permanences/' . $p['id'] . '/export.csv') ?>">Liste des inscrits (CSV)</a>
            <form method="post" action="<?= site_url('permanences/' . $p['id'] . '/activation') ?>" class="grid"><?= csrf_field() ?><button class="btn btn-ghost"><?= $p['active'] ? 'Désactiver (fermer les réservations)' : 'Réactiver' ?></button></form>
        </div>
        <form method="post" action="<?= site_url('permanences/' . $p['id'] . '/dupliquer') ?>" class="mt-4" id="dupliquer">
            <?= csrf_field() ?>
            <label class="label" for="new_date">Dupliquer à une autre date</label>
            <span class="hint" id="aide-dup">Mêmes créneaux et même quota.</span>
            <div class="flex flex-wrap gap-2"><input class="input min-w-0 flex-1" type="date" id="new_date" name="new_date" required aria-describedby="aide-dup" value="<?= date('Y-m-d', strtotime($p['perm_date'] . ' +7 days')) ?>"><button class="btn btn-secondary">Dupliquer</button></div>
        </form>
        <form method="post" action="<?= site_url('permanences/' . $p['id'] . '/supprimer') ?>" class="mt-4" onsubmit="return confirm('Supprimer cette permanence, ses créneaux et ses <?= $p['total_bookings'] ?> inscriptions ?')"><?= csrf_field() ?><button class="btn btn-danger w-full"><?= icon('trash') ?> Supprimer</button></form>
        <p class="mt-3 text-sm text-muted">Créée par <?= esc($p['creator_name'] ?: 'un ancien compte') ?>.</p>
    </section>
    <?php endif ?>
</aside>
</div>
<script src="<?= base_url('assets/js/qrcode.js?v=' . FO_ASSET_VERSION) ?>"></script>
<script src="<?= base_url('assets/js/qr.js?v=' . FO_ASSET_VERSION) ?>"></script>
<?= $this->endSection() ?>
