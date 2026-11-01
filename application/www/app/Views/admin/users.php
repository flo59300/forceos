<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
use App\Domain\Members;
use App\Domain\Users;

$active   = array_filter($users, static fn ($u) => (int) $u['active']);
$inactive = array_filter($users, static fn ($u) => ! (int) $u['active']);
$myId     = (int) ($me['id'] ?? 0);
?>
<p><a href="<?= site_url('administration') ?>">Administration</a></p>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div><h1 class="text-3xl">Utilisateurs</h1><p class="mt-1 text-lg text-muted"><?= esc(plural(count($active), 'compte actif', 'comptes actifs')) ?><?= $inactive ? ', ' . esc(plural(count($inactive), 'désactivé', 'désactivés')) : '' ?></p></div>
    <a href="<?= site_url('administration/utilisateurs/nouveau') ?>" class="btn btn-primary"><?= icon('plus') ?> Nouvel utilisateur</a>
</div>

<?php foreach (['Comptes actifs' => $active, 'Comptes désactivés' => $inactive] as $title => $list): if (! $list) continue; ?>
<section class="mt-8" aria-labelledby="t-<?= url_title($title, '-', true) ?>">
    <h2 id="t-<?= url_title($title, '-', true) ?>" class="section-title"><?= esc($title) ?></h2>
    <ul class="grid gap-3" role="list">
        <?php foreach ($list as $u): $isMe = (int) $u['id'] === $myId; ?>
        <li id="utilisateur-<?= $u['id'] ?>" class="card">
            <div class="flex flex-wrap items-start gap-3">
                <div class="min-w-0 flex-1 basis-[16rem]">
                    <p class="m-0 text-lg font-bold"><?= esc($u['first_name'] . ' ' . $u['last_name']) ?><?= $isMe ? ' <span class="font-normal text-muted">(vous)</span>' : '' ?></p>
                    <p class="m-0 flex flex-wrap gap-2"><span class="badge badge-info"><?= esc(Users::ROLES[$u['role']] ?? $u['role']) ?></span>
                        <?php if ($u['member_college']): ?><span class="badge badge-neutral">Adhérent, <?= esc(Members::COLLEGES[$u['member_college']] ?? '') ?></span><?php endif ?>
                        <?php foreach ($u['functions'] as $f): ?><span class="badge badge-muted"><?= esc($f['label']) ?></span><?php endforeach ?></p>
                    <p class="m-0 mt-1 break-words text-muted"><a href="mailto:<?= esc($u['email'], 'attr') ?>"><?= esc($u['email']) ?></a><?= $u['phone'] ? ' · ' . esc($u['phone']) : '' ?> · <?= $u['role'] === 'collecteur' ? esc(plural((int) $u['nb_members'], 'adhérent suivi', 'adhérents suivis')) . ' · ' : '' ?><?= $u['last_login'] ? 'dernière connexion le ' . esc(date_fr($u['last_login'], 'd MMM y')) : 'jamais connecté' ?></p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="<?= site_url('administration/utilisateurs/' . $u['id'] . '/modifier') ?>" class="btn btn-secondary"><?= icon('edit') ?> Modifier<span class="sr-only"> le compte de <?= esc($u['first_name'] . ' ' . $u['last_name']) ?></span></a>
                    <?php if (! $isMe): ?>
                    <form method="post" action="<?= site_url('administration/utilisateurs/' . $u['id'] . '/activation') ?>"><?= csrf_field() ?><input type="hidden" name="active" value="<?= (int) $u['active'] ? 0 : 1 ?>"><button class="btn btn-ghost"><?= (int) $u['active'] ? 'Désactiver' : 'Réactiver' ?><span class="sr-only"> <?= esc($u['first_name'] . ' ' . $u['last_name']) ?></span></button></form>
                    <?php endif ?>
                </div>
            </div>
            <?php if ($functions): ?>
            <details class="mt-3">
                <summary class="btn btn-ghost px-0">Fonctions syndicales</summary>
                <form method="post" action="<?= site_url('administration/utilisateurs/' . $u['id'] . '/fonctions') ?>" class="mt-2">
                    <?= csrf_field() ?>
                    <fieldset><legend class="sr-only">Fonctions de <?= esc($u['first_name'] . ' ' . $u['last_name']) ?></legend>
                        <div class="flex flex-wrap gap-2">
                            <?php $mine = array_column($u['functions'], 'id'); foreach ($functions as $f): ?>
                            <label class="flex min-h-11 items-center gap-2 rounded-xl border-2 border-line px-3 has-[:checked]:border-brand has-[:checked]:bg-brand-soft"><input type="checkbox" name="functions[]" value="<?= $f['id'] ?>" class="size-5" <?= in_array($f['id'], $mine) ? 'checked' : '' ?>> <?= esc($f['label']) ?></label>
                            <?php endforeach ?>
                        </div>
                    </fieldset>
                    <button class="btn btn-secondary mt-2">Enregistrer les fonctions</button>
                </form>
            </details>
            <?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
</section>
<?php endforeach ?>

<div class="mt-8 grid gap-6 grid-cols-[repeat(auto-fit,minmax(min(100%,20rem),1fr))]">
    <section class="card" aria-labelledby="t-fonctions" id="fonctions">
        <h2 id="t-fonctions" class="section-title">Fonctions syndicales du site</h2>
        <?php if ($functions): ?>
        <ul class="mb-4 grid gap-2" role="list">
            <?php foreach ($functions as $f): ?>
            <li class="flex items-center gap-2"><span class="min-w-0 flex-1"><?= esc($f['label']) ?> <span class="text-muted">(<?= esc($f['abbreviation']) ?>)</span></span>
                <form method="post" action="<?= site_url('administration/utilisateurs/fonctions/' . $f['id'] . '/supprimer') ?>" onsubmit="return confirm('Supprimer cette fonction ?')"><?= csrf_field() ?><button class="btn btn-ghost text-danger">Supprimer<span class="sr-only"> <?= esc($f['label']) ?></span></button></form></li>
            <?php endforeach ?>
        </ul>
        <?php endif ?>
        <form method="post" action="<?= site_url('administration/utilisateurs/fonctions') ?>">
            <?= csrf_field() ?>
            <div class="field"><label class="label" for="f-label">Nouvelle fonction</label><input class="input" type="text" id="f-label" name="label" maxlength="100" required placeholder="Délégué syndical, élu CSE…"></div>
            <div class="grid grid-cols-2 gap-3">
                <div class="field"><label class="label" for="f-abbr">Abréviation</label><input class="input" type="text" id="f-abbr" name="abbreviation" maxlength="20"></div>
                <div class="field"><label class="label" for="f-color">Couleur</label><input class="input h-11 p-1" type="color" id="f-color" name="color" value="#002b7f"></div>
            </div>
            <button class="btn btn-secondary">Ajouter la fonction</button>
        </form>
    </section>
    <section class="card" aria-labelledby="t-import" id="import">
        <h2 id="t-import" class="section-title">Importer des collecteurs</h2>
        <p class="text-muted">Fichier CSV avec les colonnes prénom, nom, e-mail, et facultativement téléphone et mot de passe.</p>
        <form method="post" action="<?= site_url('administration/utilisateurs/import') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="field"><label class="label" for="csv">Fichier CSV</label><input class="input" type="file" id="csv" name="csv" accept=".csv,text/csv" required></div>
            <div class="field"><label class="label" for="default_password">Mot de passe par défaut</label><span class="hint" id="aide-pw">Pour les lignes sans mot de passe : 8 caractères, une majuscule, une minuscule, un chiffre.</span><input class="input" type="text" id="default_password" name="default_password" required aria-describedby="aide-pw" autocomplete="off"></div>
            <button class="btn btn-secondary">Importer</button>
        </form>
    </section>
</div>
<?= $this->endSection() ?>
