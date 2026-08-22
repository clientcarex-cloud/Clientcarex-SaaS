<?php /** @var string $title @var string $lede @var array $buttons */ ?>
<section class="section">
  <div class="container">
    <div class="cta" data-reveal>
      <h2><?= e($title) ?></h2>
      <p><?= e($lede) ?></p>
      <?php part('buttons', ['items' => $buttons, 'class' => 'btn-row btn-row--center']) ?>
    </div>
  </div>
</section>
