<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php use App\Domain\MemberImport; ?>
<p><a href="<?= site_url('administration') ?>">Administration</a></p>
<h1 class="text-3xl">Import et export</h1>
<p class="mt-1 text-lg text-muted">Fichiers CSV à séparateur point-virgule, lisibles par Excel et LibreOffice.</p>

<section class="card mt-6 max-w-3xl" aria-labelledby="t-export">
    <h2 id="t-export" class="section-title">Exporter</h2>
    <ul class="grid gap-2" role="list">
        <li><a class="btn btn-secondary" href="<?= site_url('administration/import/export/adherents') ?>"><?= icon('download') ?> Adhérents <span class="font-normal">(27 colonnes)</span></a></li>
        <li><a class="btn btn-secondary" href="<?= site_url('administration/import/export/ventes') ?>"><?= icon('download') ?> Ventes de <?= $year ?> <span class="font-normal">(13 colonnes)</span></a></li>
        <li><a class="btn btn-secondary" href="<?= site_url('administration/import/export/utilisateurs') ?>"><?= icon('download') ?> Utilisateurs <span class="font-normal">(14 colonnes)</span></a></li>
    </ul>
    <p class="mt-3 text-sm text-muted">Les fichiers contiennent des données personnelles : conservez-les en lieu sûr et supprimez-les après usage.</p>
</section>

<section class="card mt-6 max-w-3xl" aria-labelledby="t-import">
    <h2 id="t-import" class="section-title">Importer des adhérents</h2>
    <p class="mt-0">Seuls le nom et le prénom sont obligatoires. Les colonnes, dans cet ordre :</p>
    <ol class="ml-6 list-decimal columns-1 sm:columns-2">
        <?php foreach (MemberImport::COLUMNS as $i => $c): ?><li><?= esc($c) ?><?= $i >= 15 ? ' <span class="text-muted">(facultative)</span>' : '' ?></li><?php endforeach ?>
    </ol>
    <p>Les dates s’écrivent 31/12/1980 ou 1980-12-31. Secteur, métier et tournée reprennent les noms exacts de la configuration ; le collecteur, son nom complet ou son adresse e-mail. Genre : H ou F. Collège : 1, 2 ou 3. Une ligne dont le matricule existe déjà est ignorée. Les trois dernières colonnes servent aux listes électorales.</p>
    <p><a href="<?= site_url('administration/import/modele') ?>"><?= icon('download') ?> Télécharger un modèle</a></p>
    <form method="post" action="<?= site_url('administration/import') ?>" enctype="multipart/form-data" class="mt-4" onsubmit="return confirm('Lancer l’import ?')">
        <?= csrf_field() ?>
        <div class="field"><label class="label" for="csv_file">Fichier CSV <span class="font-normal text-muted">(10 Mo au plus)</span></label><input class="input" type="file" id="csv_file" name="csv_file" accept=".csv,.txt,text/csv" required></div>
        <button class="btn btn-primary">Importer</button>
    </form>
</section>

<?php if ($report): ?>
<section class="card mt-6 max-w-3xl" aria-labelledby="t-rapport" id="rapport">
    <h2 id="t-rapport" class="section-title">Compte rendu du dernier import</h2>
    <p class="mt-0 text-lg"><?= esc(plural($report['imported'], 'adhérent importé', 'adhérents importés')) ?>, <?= esc(plural(count($report['errors']), 'remarque', 'remarques')) ?>.</p>
    <?php if ($report['errors']): ?>
    <ul class="ml-6 list-disc"><?php foreach (array_slice($report['errors'], 0, 200) as $e): ?><li><?= esc($e) ?></li><?php endforeach ?></ul>
    <?php if (count($report['errors']) > 200): ?><p class="text-muted">… et <?= count($report['errors']) - 200 ?> autres.</p><?php endif ?>
    <?php endif ?>
</section>
<?php endif ?>
<?= $this->endSection() ?>
