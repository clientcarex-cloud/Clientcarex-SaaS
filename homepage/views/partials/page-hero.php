<?php
/**
 * Hero band at the top of an inner page.
 * @var string      $title
 * @var string      $lede
 * @var string|null $crumb    trailing breadcrumb label
 * @var string|null $eyebrow
 * @var array|null  $buttons
 */
?>
<section class="page-hero">
  <div class="container page-hero__inner">
    <?php if (!empty($crumb)): ?>
      <ul class="breadcrumb">
        <li><a href="<?= url() ?>">Home</a></li>
        <li aria-hidden="true">/</li>
        <li><?= e($crumb) ?></li>
      </ul>
    <?php endif ?>
    <?php if (!empty($eyebrow)): ?>
      <span class="eyebrow"><?= e($eyebrow) ?></span>
    <?php endif ?>
    <h1><?= e($title) ?></h1>
    <p><?= e($lede) ?></p>
    <?php if (!empty($buttons)) {
        part('buttons', ['items' => $buttons, 'class' => 'btn-row btn-row--center', 'style' => 'margin-top:2rem']);
    } ?>
  </div>
</section>
