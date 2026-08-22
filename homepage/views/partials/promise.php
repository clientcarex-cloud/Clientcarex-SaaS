<?php
/**
 * Dark band: the one-line version of why the model is different, beside a
 * client pull quote.
 * @var array       $buttons
 * @var string|null $title
 * @var string|null $body
 */
?>
<section class="section section--dark">
  <div class="container riskfree">
    <div data-reveal>
      <span class="eyebrow">The model</span>
      <h2><?= e($title ?? 'If it does not produce revenue, it does not produce an invoice') ?></h2>
      <p>
        <?= e($body ?? 'We pay for the ads, the media buyers, the creative and the tools before you pay us anything. Our fee only exists once your revenue does — so there is no version of this where we get paid for activity that did not work.') ?>
      </p>
      <?php part('buttons', ['items' => $buttons, 'style' => 'margin-top:1.75rem']) ?>
    </div>

    <figure class="quote-card" data-reveal>
      <blockquote>&ldquo;<?= e(QUOTE['text']) ?>&rdquo;</blockquote>
      <figcaption>
        <span class="avatar" aria-hidden="true"><?= e(initials(QUOTE['name'])) ?></span>
        <span>
          <cite><?= e(QUOTE['name']) ?></cite>
          <span><?= e(QUOTE['role']) ?></span>
        </span>
      </figcaption>
    </figure>
  </div>
</section>
