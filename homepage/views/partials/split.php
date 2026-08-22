<?php
/**
 * Image beside copy.
 * @var string      $img    file name under assets/img
 * @var string      $alt
 * @var string      $eyebrow
 * @var string      $title
 * @var string      $body
 * @var array|null  $list   check-list items
 * @var array|null  $button [label, href]
 * @var bool|null   $flip
 */
?>
<div class="split<?= !empty($flip) ? ' split--flip' : '' ?>" data-reveal>
  <div class="split__media">
    <img src="<?= asset('assets/img/' . $img) ?>" alt="<?= e($alt) ?>" width="640" height="480" loading="lazy">
  </div>
  <div class="split__body">
    <span class="eyebrow"><?= e($eyebrow) ?></span>
    <h2><?= e($title) ?></h2>
    <p><?= e($body) ?></p>
    <?php if (!empty($list)) { part('check-list', ['items' => $list]); } ?>
    <?php if (!empty($button)): ?>
      <a class="btn" href="<?= e(url($button[1])) ?>"><?= e($button[0]) ?></a>
    <?php endif ?>
  </div>
</div>
