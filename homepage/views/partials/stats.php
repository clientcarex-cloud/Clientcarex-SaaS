<?php /** @var array $items  rows of [value, label] */ ?>
<div class="stats" data-reveal>
  <?php foreach ($items as [$value, $label]): ?>
    <div class="stat">
      <span class="stat__value"><?= e($value) ?></span>
      <span class="stat__label"><?= e($label) ?></span>
    </div>
  <?php endforeach ?>
</div>
