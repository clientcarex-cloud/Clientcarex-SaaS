<?php
/**
 * A row of buttons. Each item is [label, href, modifier].
 * @var array  $items
 * @var string $class
 * @var string $style
 */
?>
<div class="<?= $class ?? 'btn-row' ?>"<?= isset($style) ? ' style="' . e($style) . '"' : '' ?>>
  <?php foreach ($items as [$label, $href, $mod]): ?>
    <a class="btn<?= $mod === '' ? '' : ' ' . $mod ?>" href="<?= e(url($href)) ?>"><?= e($label) ?></a>
  <?php endforeach ?>
</div>
