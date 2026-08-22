<?php
/**
 * Phone + email cards. Used on the homepage and the contact page.
 * @var string|null $style
 */
$cards = [
    ['phone', 'Phone', PHONE, 'tel:' . PHONE_HREF],
    ['mail', 'Email', EMAIL, 'mailto:' . EMAIL],
];
?>
<div class="contact-strip" data-reveal<?= isset($style) ? ' style="' . e($style) . '"' : '' ?>>
  <?php foreach ($cards as [$ico, $label, $value, $href]): ?>
    <a class="contact-card" href="<?= e($href) ?>">
      <span class="contact-card__icon"><?= icon($ico, 20) ?></span>
      <span>
        <h3><?= e($label) ?></h3>
        <p><?= e($value) ?></p>
      </span>
    </a>
  <?php endforeach ?>
</div>
