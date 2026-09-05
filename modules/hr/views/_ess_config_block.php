<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Reusable ESS override editor. Rendered for a role (prefix "ess_role") or an
 * employee (prefix "ess_emp"). Field names are namespaced by $prefix and $id so
 * many blocks can post in one form and be collected by Hr::collect_ess_overrides.
 *
 * Expects: $prefix (string), $id (int), $cfg (array|null current override).
 */
$cfg          = isset($cfg) && is_array($cfg) ? $cfg : [];
$sel_fields   = array_flip(hr_ess_parse_fields($cfg['editable_fields'] ?? ''));
$checklist_on = !isset($cfg['checklist_enabled']) || (string) $cfg['checklist_enabled'] === '1';
$uid          = $prefix . '_' . $id;
?>
<label class="bold" style="display:block;margin-bottom:6px;">Self-editable profile fields</label>
<div class="row">
    <?php foreach (hr_self_field_options() as $slug => $label) { ?>
        <div class="col-md-4 col-sm-6">
            <div class="checkbox checkbox-primary">
                <input type="checkbox" id="<?php echo $uid . '_' . $slug; ?>" name="<?php echo $prefix; ?>_fields[<?php echo $id; ?>][]" value="<?php echo $slug; ?>" <?php echo isset($sel_fields[$slug]) ? 'checked' : ''; ?>>
                <label for="<?php echo $uid . '_' . $slug; ?>"><?php echo html_escape($label); ?></label>
            </div>
        </div>
    <?php } ?>
</div>
<div class="checkbox checkbox-primary" style="margin-top:6px;">
    <input type="checkbox" id="<?php echo $uid; ?>_cl" name="<?php echo $prefix; ?>_checklist[<?php echo $id; ?>]" value="1" <?php echo $checklist_on ? 'checked' : ''; ?>>
    <label for="<?php echo $uid; ?>_cl">Enable required-documents checklist</label>
</div>
<div class="form-group" style="margin-top:6px;">
    <label>Required documents (one per line)</label>
    <textarea name="<?php echo $prefix; ?>_docs[<?php echo $id; ?>]" class="form-control" rows="6"><?php echo html_escape($cfg['required_documents'] ?? ''); ?></textarea>
    <small class="text-muted">Start a line with <code>*</code> to make that document <span style="color:#dc2626;font-weight:700;">mandatory</span> (red / compulsory in ESS).</small>
</div>
