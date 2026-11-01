<?php
$cls = ['reunion' => 'badge-info', 'permanence' => 'badge-success', 'formation' => 'badge-neutral', 'evenement' => 'badge-warning', 'election' => 'badge-danger', 'autre' => 'badge-muted'][$type] ?? 'badge-muted';
?>
<span class="badge <?= $cls ?> shrink-0 self-center"><?= esc(\App\Domain\Agenda::TYPES[$type] ?? 'Autre') ?></span>
