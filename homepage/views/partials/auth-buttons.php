<?php
/**
 * Header call to action. The full pair goes in the mobile drawer; the desktop
 * bar takes the compact form so the five nav items keep their room.
 * @var string|null $class
 * @var bool|null   $compact
 */
?>
<div class="<?= $class ?? 'nav__actions' ?>">
  <?php if (empty($compact)): ?>
    <a class="btn btn--ghost btn--sm" href="<?= APP_LOGIN ?>">Client Login</a>
  <?php endif ?>
  <a class="btn btn--sm" href="<?= url('contact') ?>">Get a Proposal</a>
</div>
