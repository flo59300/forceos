<?php $errors = session('errors') ?? []; if ($errors): ?>
<div class="alert alert-error" role="alert" tabindex="-1" id="erreurs">
    <?= icon('alert', 'size-6 shrink-0') ?>
    <div>
        <p class="m-0 font-bold"><?= count($errors) > 1 ? 'Le formulaire contient ' . count($errors) . ' erreurs :' : 'Le formulaire contient une erreur :' ?></p>
        <ul class="m-0 mt-1 list-disc pl-5">
            <?php foreach ($errors as $field => $msg): ?><li><a href="#<?= esc($field, 'attr') ?>"><?= esc($msg) ?></a></li><?php endforeach ?>
        </ul>
    </div>
</div>
<?php endif ?>
