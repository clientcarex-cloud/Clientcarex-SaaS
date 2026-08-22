<?php /** @var array $items  rows of [icon|null, title, list] */ ?>
<div class="grid grid--3" data-reveal>
  <?php foreach ($items as [$ico, $title, $list]): ?>
    <article class="module">
      <h3><?= $ico ? icon($ico, 20) : '' ?><?= e($title) ?></h3>
      <ul>
        <?php foreach ($list as $line): ?><li><?= e($line) ?></li><?php endforeach ?>
      </ul>
    </article>
  <?php endforeach ?>
</div>
