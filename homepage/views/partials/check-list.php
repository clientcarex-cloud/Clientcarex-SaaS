<?php /** @var array $items */ ?>
<ul class="check-list">
  <?php foreach ($items as $item): ?>
    <li><?= icon('check', 18) ?><?= e($item) ?></li>
  <?php endforeach ?>
</ul>
