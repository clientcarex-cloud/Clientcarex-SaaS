<?php /** @var array $items  rows of [meta, title, body] */ ?>
<div class="steps" data-reveal>
  <?php foreach ($items as [$meta, $title, $body]): ?>
    <article class="step">
      <span class="step__num" aria-hidden="true"></span>
      <div>
        <span class="step__meta"><?= e($meta) ?></span>
        <h3><?= e($title) ?></h3>
        <p><?= e($body) ?></p>
      </div>
    </article>
  <?php endforeach ?>
</div>
