<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$t = $type;
$v = function ($key, $default = '') use ($t) { return html_escape($t ? (string) $t->$key : $default); };
$extra_lines = [];
foreach (($t ? $t->extra_fields : []) as $f) {
    $line = $f['label'] . ' | ' . (($f['group'] ?? '') === 'personnel' ? 'personnel' : $f['type']);
    if (!empty($f['options'])) { $line .= ' | ' . implode(', ', $f['options']); }
    $extra_lines[] = $line;
}
?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'setup'; include __DIR__ . '/../_nav.php'; ?>
            <?php $setup_active = 'types'; include __DIR__ . '/_setup_nav.php'; ?>

            <?= form_open(admin_url('eptw/eptw_setup/type' . ($t ? '/' . $t->id : ''))); ?>
            <div class="eptw-split">
                <div>
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-file-shield"></i> <?= $t ? html_escape($t->name) : 'New permit type'; ?></h3></div>
                        <div class="eptw-card-body">
                            <div class="eptw-grid-3">
                                <div class="eptw-field" style="grid-column:span 2"><label class="eptw-label">Name <span class="req">*</span></label><input name="name" class="eptw-input" value="<?= $v('name'); ?>" required></div>
                                <div class="eptw-field"><label class="eptw-label">Code <span class="req">*</span></label><input name="code" class="eptw-input eptw-mono" value="<?= $v('code'); ?>" maxlength="10" required><div class="eptw-hint">Used in the permit number.</div></div>
                            </div>
                            <div class="eptw-field"><label class="eptw-label">Description</label><input name="description" class="eptw-input" value="<?= $v('description'); ?>"></div>
                            <div class="eptw-grid-4">
                                <div class="eptw-field"><label class="eptw-label">Icon (Font Awesome)</label><input name="icon" class="eptw-input" value="<?= $v('icon', 'fa-solid fa-file-shield'); ?>"></div>
                                <div class="eptw-field"><label class="eptw-label">Colour</label><input type="color" name="color" class="eptw-input" value="<?= $v('color', '#2563eb'); ?>" style="height:38px;padding:3px"></div>
                                <div class="eptw-field"><label class="eptw-label">Default validity (hours)</label><input type="number" min="1" name="default_validity_hours" class="eptw-input" value="<?= $v('default_validity_hours', '12'); ?>"></div>
                                <div class="eptw-field"><label class="eptw-label">Sort order</label><input type="number" name="sort_order" class="eptw-input" value="<?= $v('sort_order', '999'); ?>"></div>
                            </div>
                            <label class="eptw-check"><input type="checkbox" name="high_risk" value="1" <?= $t && $t->high_risk ? 'checked' : ''; ?>> <span><b>High-risk permit type</b> — shows on the dashboard high-risk panel and raises the risk score.</span></label>
                            <label class="eptw-check"><input type="checkbox" name="gas_test_required" value="1" <?= $t && $t->gas_test_required ? 'checked' : ''; ?>> <span>Gas testing required by default</span></label>
                            <label class="eptw-check"><input type="checkbox" name="isolation_required" value="1" <?= $t && $t->isolation_required ? 'checked' : ''; ?>> <span>Isolation / LOTO required by default</span></label>
                            <label class="eptw-check"><input type="checkbox" name="active" value="1" <?= !$t || $t->active ? 'checked' : ''; ?>> <span>Active (available for new permits)</span></label>

                            <div class="eptw-sec">Approval steps (before the coordinator issues the number)</div>
                            <?php foreach (['area_authority' => 'Area Authority', 'hse' => 'HSE Officer', 'manager' => 'Manager'] as $k => $l) { ?>
                                <label class="eptw-check" style="display:inline-flex;margin-right:16px"><input type="checkbox" name="approvals[]" value="<?= $k; ?>" <?= !$t || in_array($k, $t->approvals, true) ? 'checked' : ''; ?>> <span><?= $l; ?></span></label>
                            <?php } ?>
                            <input type="hidden" name="approvals[]" value="coordinator">
                            <div class="eptw-hint">The PTW Coordinator always signs last, by issuing the permit number.</div>

                            <div class="eptw-sec">Hazard identification (one per line)</div>
                            <textarea name="hazards_text" class="eptw-textarea" style="min-height:150px"><?= html_escape(implode("\n", $t ? $t->hazards : [])); ?></textarea>

                            <div class="eptw-sec">Control measure sections <button type="button" class="eptw-btn eptw-btn-sm" id="eptw-add-section"><i class="fa fa-plus"></i> Add section</button></div>
                            <div class="eptw-ctrl-sections" id="eptw-ctrl-sections">
                                <?php foreach (($t ? $t->controls : [['title' => 'Control measures', 'items' => []]]) as $s) { ?>
                                    <div class="eptw-ctrl-section">
                                        <input class="eptw-input" name="control_title[]" value="<?= html_escape($s['title']); ?>" placeholder="Section title">
                                        <textarea class="eptw-textarea" name="control_items[]" placeholder="One control item per line"><?= html_escape(implode("\n", $s['items'] ?? [])); ?></textarea>
                                        <button type="button" class="eptw-btn eptw-btn-sm eptw-btn-danger" data-eptw-remove-section><i class="fa fa-times"></i> Remove section</button>
                                    </div>
                                <?php } ?>
                            </div>

                            <div class="eptw-sec">Type-specific fields</div>
                            <textarea name="extra_fields_text" class="eptw-textarea mono" style="min-height:150px" placeholder="Label | type | options"><?= html_escape(implode("\n", $extra_lines)); ?></textarea>
                            <div class="eptw-hint">One per line: <code>Label | type | options</code>. Types: <b>text</b>, <b>number</b>, <b>yesno</b>, <b>select</b> (options comma-separated), <b>checkboxes</b> (options), <b>detect</b> (detected / not detected per option), <b>textarea</b>, <b>personnel</b> (a named person, shown under People).<br>Example: <code>Lift type | select | Routine, Critical, Tandem</code></div>

                            <div class="eptw-sec">PPE (one per line) &amp; keywords</div>
                            <div class="eptw-grid-2">
                                <div class="eptw-field"><textarea name="ppe_text" class="eptw-textarea" style="min-height:90px"><?= html_escape(implode("\n", $t ? $t->ppe : [])); ?></textarea></div>
                                <div class="eptw-field"><input name="keywords_text" class="eptw-input" value="<?= html_escape(implode(', ', $t ? $t->keywords : [])); ?>" placeholder="weld, cut, grind"><div class="eptw-hint">Comma-separated words that identify this work in a description.</div></div>
                            </div>

                            <div class="eptw-formbar">
                                <span class="spacer"></span>
                                <a href="<?= admin_url('eptw/eptw_setup/types'); ?>" class="eptw-btn eptw-btn-ghost">Cancel</a>
                                <button type="submit" class="eptw-btn eptw-btn-primary"><i class="fa fa-check"></i> Save permit type</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-circle-info"></i> Template tips</h3></div>
                        <div class="eptw-card-body eptw-small">
                            <p>Hazards become Yes / No toggles on the permit. Controls become Yes / No / N/A rows with a remark box. Both are also what the smart-suggestion engine pre-ticks from the work description.</p>
                            <p class="eptw-muted" style="margin:0">Changing a template only affects new permits; permits already raised keep the checklist they were raised with.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?= form_close(); ?>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
