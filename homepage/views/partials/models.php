<?php
/**
 * The commercial models, driven by MODELS.
 * @var array|null $items  defaults to all three
 */
$items = $items ?? MODELS;
?>
<div class="plans" data-reveal>
  <?php foreach ($items as $model): ?>
    <article class="plan<?= !empty($model['featured']) ? ' plan--featured' : '' ?>">
      <span class="plan__badge"><?= e($model['badge']) ?></span>
      <h3 class="plan__name"><?= e($model['name']) ?></h3>
      <p class="plan__tagline"><?= e($model['tagline']) ?></p>
      <p class="plan__price">
        <span class="plan__amount<?= preg_match('/^[₹\d]/u', $model['price']) ? '' : ' plan__amount--word' ?>"><?= e($model['price']) ?></span>
      </p>
      <p class="plan__period"><?= e($model['period']) ?></p>
      <p class="plan__summary"><?= e($model['summary']) ?></p>
      <ul class="plan__features">
        <?php foreach ($model['features'] as $feature): ?>
          <li><?= icon('check', 15, '3') ?><?= e($feature) ?></li>
        <?php endforeach ?>
      </ul>
      <div class="plan__cta">
        <a class="btn<?= empty($model['featured']) ? ' btn--ghost' : '' ?> btn--block" href="<?= url($model['cta'][1]) ?>"><?= e($model['cta'][0]) ?></a>
        <p class="plan__terms"><?= e($model['terms']) ?></p>
        <p class="plan__link"><?php part('link-arrow', ['label' => $model['link'][0], 'href' => $model['link'][1]]) ?></p>
      </div>
    </article>
  <?php endforeach ?>
</div>
