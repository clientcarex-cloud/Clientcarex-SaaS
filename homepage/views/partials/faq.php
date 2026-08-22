<?php /** Accordion, driven by FAQS. */ ?>
<div class="faq" data-reveal>
  <?php foreach (FAQS as $item): ?>
    <details class="faq__item">
      <summary class="faq__q">
        <?= e($item['q']) ?>
        <span class="faq__icon" aria-hidden="true"><?= icon('plus', 13, '3') ?></span>
      </summary>
      <div class="faq__a"><?= $item['a'] ?></div>
    </details>
  <?php endforeach ?>
</div>
