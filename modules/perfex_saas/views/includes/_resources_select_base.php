<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
if (isset($multiple)) echo '<input name="' . $name . '" value="" type="hidden" />';
if (!is_array($value)) $value = [$value];
?>
<select name="<?= $name; ?>" class="form-control selectpicker <?= $class ?? ''; ?>" <?= isset($id) ? "id='$id'" : ""; ?>
    <?= isset($multiple) ? "multiple='$multiple'" : ""; ?>>
    <option value=""></option>
    <?php
    $name_field = isset($name_field) ? $name_field : 'name';
    $identifier_field = isset($identifier_field) ? $identifier_field :  'slug';

    foreach ($resources as $resource) {
        $identifier = $resource->{$identifier_field} ?? '';
        $selected = in_array($identifier, $value) ? 'selected' : '';
        echo '<option value="' . $identifier . '" ' . $selected . '>' . ($resource->{$name_field} ?? '') . ' (' . $identifier . ')</option>';
    } ?>
</select>