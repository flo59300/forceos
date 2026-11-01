<?php if (! empty($credentials)): ?>
<section class="alert alert-success mt-6 block" aria-labelledby="t-identifiants" id="identifiants" tabindex="-1">
    <h2 id="t-identifiants" class="m-0 text-xl">Identifiants à transmettre</h2>
    <p class="mt-1">Ces mots de passe ne s’afficheront plus : notez-les ou transmettez-les maintenant, de vive voix de préférence. Chacun pourra changer le sien depuis « Mon profil ».</p>
    <dl class="mt-3 grid gap-3">
        <?php foreach ($credentials as $c): ?>
        <div class="rounded-xl border border-line bg-surface p-3 text-ink">
            <dt class="font-bold"><?= esc($c['label']) ?></dt>
            <dd class="m-0 break-words">Identifiant : <span class="font-mono"><?= esc($c['email']) ?></span></dd>
            <dd class="m-0">Mot de passe : <span class="font-mono text-lg font-bold"><?= esc($c['password']) ?></span></dd>
        </div>
        <?php endforeach ?>
    </dl>
</section>
<?php endif ?>
