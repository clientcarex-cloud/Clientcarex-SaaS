<?php
/**
 * One labelled dropdown.
 * @var string $id @var string $label @var array $options @var string $value
 */
?>
<div class="field">
  <label for="<?= $id ?>"><?= e($label) ?></label>
  <select id="<?= $id ?>" name="<?= $id ?>">
    <option value="">Select…</option>
    <?php foreach ($options as $option): ?>
      <option<?= $value === $option ? ' selected' : '' ?>><?= e($option) ?></option>
    <?php endforeach ?>
  </select>
</div>
