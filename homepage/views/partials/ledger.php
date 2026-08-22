<?php
/**
 * Two facing lists — what we carry against what you carry, or where the model
 * fits against where it does not.
 *
 * @var array $cols  rows of [tone, title, lede, items]
 *                   tone: 'ours' | 'yours' | 'no'
 */
$mark = ['ours' => 'check', 'yours' => 'arrow', 'no' => 'cross'];
?>
<div class="ledger" data-reveal>
  <?php foreach ($cols as [$tone, $title, $lede, $items]): ?>
    <section class="ledger__col ledger__col--<?= $tone ?>">
      <h3><?= e($title) ?></h3>
      <?php if ($lede !== null): ?><p class="ledger__lede"><?= e($lede) ?></p><?php endif ?>
      <ul>
        <?php foreach ($items as $item): ?>
          <li><span class="ledger__mark"><?= icon($mark[$tone], 14, '3') ?></span><?= e($item) ?></li>
        <?php endforeach ?>
      </ul>
    </section>
  <?php endforeach ?>
</div>
