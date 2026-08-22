<?php /** @var string $label @var string $href @var bool|null $arrow */ ?>
<a class="link-arrow" href="<?= e(url($href)) ?>"><?= e($label) ?><?= ($arrow ?? true) ? icon('arrow', 15) : '' ?></a>
