<?php /** @var string $eyebrow @var string $title @var string|null $lede */ ?>
<div class="section-head" data-reveal>
  <span class="eyebrow"><?= e($eyebrow) ?></span>
  <h2><?= e($title) ?></h2>
  <?php if (!empty($lede)): ?><p><?= e($lede) ?></p><?php endif ?>
</div>
