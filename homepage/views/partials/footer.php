<footer class="site-footer">
  <div class="container">
    <div class="footer__top">
      <div class="footer__brand">
        <img src="<?= asset('assets/img/ClientcareX-Logo.png') ?>" alt="<?= SITE_NAME ?>" width="500" height="100">
        <p>
          A performance marketing and business automation agency. We fund and run
          the growth — ad spend, team, creative and tools — and charge a share of
          the revenue it generates. Automation is scoped, quoted and built to fit
          how your business actually runs.
        </p>
        <div class="social">
          <?php foreach (SOCIAL as [$name, $label, $href]): ?>
            <a href="<?= e($href) ?>" aria-label="<?= SITE_NAME ?> on <?= e($label) ?>"><?= icon($name, $name === 'x' ? 15 : 17) ?></a>
          <?php endforeach ?>
        </div>
      </div>

      <?php foreach (FOOTER_COLUMNS as $heading => $links): ?>
        <div class="footer__col">
          <h2><?= e($heading) ?></h2>
          <ul>
            <?php foreach ($links as [$label, $href]): ?>
              <li><a href="<?= e(url($href)) ?>"><?= e($label) ?></a></li>
            <?php endforeach ?>
          </ul>
        </div>
      <?php endforeach ?>
    </div>

    <div class="footer__bottom">
      <p>Copyright © <?= date('Y') ?> <?= COMPANY ?>. All Rights Reserved.</p>
      <ul class="footer__legal">
        <?php foreach (FOOTER_LEGAL as [$label, $href]): ?>
          <li><a href="<?= url($href) ?>"><?= e($label) ?></a></li>
        <?php endforeach ?>
      </ul>
    </div>
  </div>
</footer>
