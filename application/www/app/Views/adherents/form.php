<?= $this->extend('layouts/hub') ?>
<?= $this->section('content') ?>
<?php
use App\Domain\Members;

$errors = session('errors') ?? [];
$v      = static fn (string $f, $d = '') => old($f, $m[$f] ?? $d);
$opt    = static fn (string $f, $val) => (string) $v($f) === (string) $val ? 'selected' : '';
$action = $m ? site_url('adherents/' . $m['id']) : site_url('adherents');
$input  = static function (string $name, string $label, string $type = 'text', array $o = []) use ($errors, $v) {
    $hint = isset($o['hint']) ? '<span class="hint" id="aide-' . $name . '">' . esc($o['hint']) . '</span>' : '';

    return '<div class="field"><label class="label" for="' . $name . '">' . esc($label) . (! empty($o['required']) ? ' <span class="font-normal text-muted">(obligatoire)</span>' : '') . '</label>' . $hint
        . '<input class="input" type="' . $type . '"' . field_attrs($name, $errors, isset($o['hint']) ? 'aide-' . $name : '')
        . ' value="' . esc((string) $v($name), 'attr') . '"' . (! empty($o['required']) ? ' required' : '') . (isset($o['autocomplete']) ? ' autocomplete="' . $o['autocomplete'] . '"' : '') . (isset($o['inputmode']) ? ' inputmode="' . $o['inputmode'] . '"' : '') . '>'
        . field_error($name, $errors) . '</div>';
};
?>
<p><a href="<?= $m ? site_url('adherents/' . $m['id']) : site_url('adherents') ?>">Retour</a></p>
<h1 class="text-3xl"><?= esc($title) ?></h1>
<div class="card mt-6 max-w-3xl">
    <?= view('partials/form_errors') ?>
    <form method="post" action="<?= $action ?>" novalidate>
        <?= csrf_field() ?>
        <fieldset class="mb-4">
            <legend class="section-title">Identité</legend>
            <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,15rem),1fr))]">
                <?= $input('last_name', 'Nom', 'text', ['required' => true, 'autocomplete' => 'off']) ?>
                <?= $input('first_name', 'Prénom', 'text', ['required' => true, 'autocomplete' => 'off']) ?>
                <div class="field">
                    <label class="label" for="genre">Genre <span class="font-normal text-muted">(pour la parité des listes)</span></label>
                    <select class="input" id="genre" name="genre"><option value="">Non renseigné</option><option value="F" <?= $opt('genre', 'F') ?>>Femme</option><option value="H" <?= $opt('genre', 'H') ?>>Homme</option></select>
                </div>
                <?= $input('date_birth', 'Date de naissance', 'date') ?>
                <?= $input('matricule', 'Matricule', 'text', ['autocomplete' => 'off']) ?>
            </div>
        </fieldset>
        <fieldset class="mb-4">
            <legend class="section-title">Contact</legend>
            <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,15rem),1fr))]">
                <?= $input('phone', 'Téléphone', 'tel', ['autocomplete' => 'off', 'inputmode' => 'tel']) ?>
                <?= $input('email', 'E-mail', 'email', ['autocomplete' => 'off']) ?>
                <?= $input('address', 'Adresse') ?>
                <?= $input('postal_code', 'Code postal', 'text', ['inputmode' => 'numeric']) ?>
                <?= $input('city', 'Ville') ?>
            </div>
        </fieldset>
        <fieldset class="mb-4">
            <legend class="section-title">Dans l’entreprise</legend>
            <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,15rem),1fr))]">
                <div class="field">
                    <label class="label" for="secteur_id">Secteur</label>
                    <select class="input" id="secteur_id" name="secteur_id"><option value="">Non renseigné</option>
                        <?php foreach ($lists['secteurs'] as $s): ?><option value="<?= $s['id'] ?>" <?= $opt('secteur_id', $s['id']) ?>><?= str_repeat("\u{00A0}\u{00A0}\u{00A0}", $s['depth']) . esc($s['name']) ?></option><?php endforeach ?>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="metier_id">Métier</label>
                    <select class="input" id="metier_id" name="metier_id"><option value="">Non renseigné</option>
                        <?php foreach ($lists['metiers'] as $x): ?><option value="<?= $x['id'] ?>" <?= $opt('metier_id', $x['id']) ?>><?= esc($x['name']) ?></option><?php endforeach ?>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="categorie">Collège électoral</label>
                    <select class="input" id="categorie" name="categorie"><option value="">Non renseigné</option>
                        <?php foreach (Members::COLLEGES as $k => $l): ?><option value="<?= $k ?>" <?= $opt('categorie', $k) ?>><?= esc($l) ?></option><?php endforeach ?>
                    </select>
                </div>
                <?php if ($lists['tournees']): ?>
                <div class="field">
                    <label class="label" for="tournee_id">Tournée</label>
                    <select class="input" id="tournee_id" name="tournee_id"><option value="">Aucune</option>
                        <?php foreach ($lists['tournees'] as $t): ?><option value="<?= $t['id'] ?>" <?= $opt('tournee_id', $t['id']) ?>><?= esc($t['name']) ?></option><?php endforeach ?>
                    </select>
                </div>
                <?php endif ?>
                <?= $input('date_entry', 'Date d’entrée', 'date') ?>
            </div>
        </fieldset>
        <fieldset class="mb-4">
            <legend class="section-title">Cotisation</legend>
            <div class="grid gap-x-4 grid-cols-[repeat(auto-fit,minmax(min(100%,15rem),1fr))]">
                <div class="field">
                    <label class="label" for="payment_mode">Mode de paiement</label>
                    <select class="input" id="payment_mode" name="payment_mode">
                        <?php foreach (Members::PAYMENT_MODES as $k => $l): ?><option value="<?= $k ?>" <?= $opt('payment_mode', $k) ?>><?= esc($l) ?></option><?php endforeach ?>
                    </select>
                </div>
                <?php if ($isSecretary): ?>
                <div class="field">
                    <label class="label" for="assigned_user_id">Suivi par</label>
                    <select class="input" id="assigned_user_id" name="assigned_user_id"><option value="">Personne</option>
                        <?php foreach ($lists['collecteurs'] as $c): ?><option value="<?= $c['id'] ?>" <?= $opt('assigned_user_id', $c['id']) ?>><?= esc($c['name']) ?></option><?php endforeach ?>
                    </select>
                </div>
                <?php endif ?>
                <?php if (! $m): ?><?= $input('date_adhesion', 'Date d’adhésion', 'date', ['hint' => 'Laissez vide pour aujourd’hui.']) ?><?php endif ?>
            </div>
        </fieldset>
        <div class="field">
            <label class="label" for="notes">Notes <span class="font-normal text-muted">(visibles par le collecteur et le secrétaire)</span></label>
            <textarea class="input min-h-24" id="notes" name="notes"><?= esc((string) $v('notes')) ?></textarea>
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary"><?= $m ? 'Enregistrer la fiche' : 'Ajouter l’adhérent' ?></button>
            <a href="<?= $m ? site_url('adherents/' . $m['id']) : site_url('adherents') ?>" class="btn btn-ghost">Annuler</a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
