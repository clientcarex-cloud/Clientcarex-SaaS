<?php /** @var string $nav  key of the active nav item */ ?>
<header class="site-header">
  <div class="container site-header__inner">
    <a class="brand" href="<?= url() ?>" aria-label="<?= SITE_NAME ?> home">
      <img src="<?= asset('assets/img/ClientcareX-Logo.png') ?>" alt="<?= SITE_NAME ?>" width="500" height="100">
    </a>

    <button class="nav-toggle" type="button" aria-expanded="false"
            aria-controls="primary-nav" aria-label="Toggle navigation">
      <span class="nav-toggle__bar"></span>
    </button>

    <nav class="nav" id="primary-nav" data-open="false" aria-label="Primary">
      <ul class="nav__list">
        <?php foreach (NAV as $key => [$label, $href, $hot]): ?>
          <li><a class="nav__link<?= $hot ? ' nav__link--hot' : '' ?>" href="<?= url($href) ?>"<?= active($key === $nav) ?>><?= e($label) ?></a></li>
        <?php endforeach ?>
      </ul>
      <?php part('auth-buttons') ?>
    </nav>

    <?php part('auth-buttons', ['class' => 'header-actions', 'compact' => true]) ?>
  </div>
</header>
