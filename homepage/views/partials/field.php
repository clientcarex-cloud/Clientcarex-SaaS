<?php
/**
 * One labelled input, with its validation message when there is one.
 * @var string $id @var string $label @var string $type
 * @var string $auto @var bool $req @var string $value @var array $errors
 */
?>
<div class="field">
  <label for="<?= $id ?>"><?= e($label) ?></label>
  <input type="<?= $type ?>" id="<?= $id ?>" name="<?= $id ?>" autocomplete="<?= $auto ?>"
         value="<?= $value ?>"<?= $req ? ' required' : '' ?><?= isset($errors[$id]) ? ' aria-invalid="true"' : '' ?>>
  <?php if (isset($errors[$id])): ?>
    <span class="field__hint" role="alert"><?= e($errors[$id]) ?></span>
  <?php endif ?>
</div>
