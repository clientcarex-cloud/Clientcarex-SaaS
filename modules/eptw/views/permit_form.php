<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'new'; include __DIR__ . '/_nav.php'; ?>

            <?php if (!$type) { ?>
                <!-- ── Step 1: pick the permit type ─────────────────────────────── -->
                <div class="eptw-card">
                    <div class="eptw-card-head"><h3><i class="fa-solid fa-wand-magic-sparkles"></i> What kind of work is it?</h3>
                        <div class="eptw-card-actions eptw-small eptw-muted">The form adapts to the permit type — hazards, controls and approvals come from the V3 template.</div></div>
                    <div class="eptw-card-body">
                        <?php if (!count($types)) { ?>
                            <div class="eptw-note warn">No permit types are active. <a href="<?= admin_url('eptw/eptw_setup/types'); ?>">Set them up</a> first.</div>
                        <?php } ?>
                        <div class="eptw-type-grid">
                            <?php foreach ($types as $t) { ?>
                                <a href="<?= admin_url('eptw/permit?type=' . $t->id); ?>" class="eptw-type-card">
                                    <span class="eptw-type-dot" style="background:<?= html_escape($t->color); ?>"><i class="<?= html_escape($t->icon); ?>"></i></span>
                                    <span style="min-width:0">
                                        <h4><?= html_escape($t->name); ?></h4>
                                        <p><?= html_escape($t->description); ?></p>
                                        <span class="eptw-type-flags">
                                            <?php if ($t->high_risk) { ?><span class="eptw-badge bad">High risk</span><?php } ?>
                                            <?php if ($t->gas_test_required) { ?><span class="eptw-badge warn">Gas test</span><?php } ?>
                                            <?php if ($t->isolation_required) { ?><span class="eptw-badge info">Isolation</span><?php } ?>
                                            <span class="eptw-badge muted"><?= (int) $t->default_validity_hours; ?> h</span>
                                        </span>
                                    </span>
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } else {
                $p          = $permit;
                $v          = function ($key, $default = '') use ($p) { return html_escape($p ? (string) $p->$key : $default); };
                $dtl        = function ($value) { return $value ? date('Y-m-d\TH:i', strtotime($value)) : ''; };
                $hz         = $p ? $p->hazards : [];
                $ct         = $p ? $p->controls : [];
                $ex         = $p ? $p->extra : [];
                $is_coord   = in_array(eptw_role(), ['admin', 'coordinator'], true);
                $project_id = $p ? (int) $p->project_id : (int) ($this->input->get('project') ?: (count($projects) ? $projects[0]->id : 0));
                $camera     = eptw_camera_mode($project_id ? $this->setup->project($project_id) : null);
            ?>
                <form id="eptw-form" method="post" action="<?= admin_url('eptw/permit' . ($p ? '/' . $p->id : '')); ?>" data-type-id="<?= (int) $type->id; ?>" data-validity-hours="<?= (int) $type->default_validity_hours; ?>" data-permit-id="<?= $p ? (int) $p->id : 0; ?>" autocomplete="off">
                    <?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
                    <input type="hidden" name="permit_type_id" value="<?= (int) $type->id; ?>">
                    <input type="hidden" name="after" value="save">

                    <?php if ($p && $p->status === 'returned' && $p->return_reason) { ?>
                        <div class="eptw-note bad"><i class="fa-solid fa-rotate-left"></i> <b>Returned for correction:</b> <?= html_escape($p->return_reason); ?></div>
                    <?php } ?>

                    <div class="eptw-split">
                        <div>
                            <div class="eptw-card">
                                <div class="eptw-card-head">
                                    <h3><span class="eptw-type-dot" style="background:<?= html_escape($type->color); ?>"><i class="<?= html_escape($type->icon); ?>"></i></span> <?= html_escape($type->name); ?></h3>
                                    <div class="eptw-card-actions">
                                        <?php if ($type->high_risk) { ?><span class="eptw-badge bad">High risk</span><?php } ?>
                                        <?php if (!$p) { ?><a href="<?= admin_url('eptw/permit'); ?>" class="eptw-btn eptw-btn-sm eptw-btn-ghost">Change type</a><?php } ?>
                                    </div>
                                </div>
                                <div class="eptw-card-body">

                                    <div class="eptw-sec">1 · Permit header</div>
                                    <div class="eptw-grid-3">
                                        <div class="eptw-field">
                                            <label class="eptw-label">Project <span class="req">*</span></label>
                                            <select name="project_id" class="eptw-select" required>
                                                <?php foreach ($projects as $pr) { if (!eptw_in_scope($pr->id)) { continue; } ?>
                                                    <option value="<?= $pr->id; ?>" <?= $project_id === (int) $pr->id ? 'selected' : ''; ?>><?= html_escape($pr->name . ' (' . $pr->code . ')'); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">Area / zone <span class="req">*</span></label>
                                            <select name="area_id" class="eptw-select" required>
                                                <option value="">— choose area / zone —</option>
                                                <?php foreach ($areas as $a) { ?>
                                                    <option value="<?= $a->id; ?>" <?= $p && (int) $p->area_id === (int) $a->id ? 'selected' : ''; ?>><?= html_escape($a->name . ' (' . $a->code . ')' . ((int) $a->project_id === 0 ? ' · shared' : '')); ?></option>
                                                <?php } ?>
                                            </select>
                                            <div class="eptw-hint">Part of the permit number and of SIMOPS detection.</div>
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">Exact location</label>
                                            <input name="location" class="eptw-input" value="<?= $v('location'); ?>" placeholder="e.g. Pipe rack R-12, level 2">
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">Contractor / company</label>
                                            <select name="contractor_id" class="eptw-select">
                                                <option value="">— none —</option>
                                                <?php foreach ($contractors as $c) { ?>
                                                    <option value="<?= $c->id; ?>" <?= $p && (int) $p->contractor_id === (int) $c->id ? 'selected' : ''; ?>><?= html_escape($c->name); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">Subcontractor</label>
                                            <input name="subcontractor" class="eptw-input" value="<?= $v('subcontractor'); ?>">
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">Work order no.</label>
                                            <input name="work_order" class="eptw-input" value="<?= $v('work_order'); ?>">
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">Equipment tag</label>
                                            <input name="equipment_tag" class="eptw-input" value="<?= $v('equipment_tag'); ?>">
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">RA / JSA reference</label>
                                            <input name="ra_ref" class="eptw-input" value="<?= $v('ra_ref'); ?>" placeholder="RA-2026-014">
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">Weather condition</label>
                                            <input name="weather" class="eptw-input" value="<?= $v('weather'); ?>" placeholder="Clear, 38 °C, wind 12 km/h">
                                        </div>
                                    </div>

                                    <div class="eptw-sec">2 · Work description</div>
                                    <div class="eptw-field">
                                        <label class="eptw-label">Work title <span class="req">*</span></label>
                                        <input name="work_title" class="eptw-input" value="<?= $v('work_title'); ?>" placeholder="Short title that will appear in the register" required>
                                    </div>
                                    <div class="eptw-field">
                                        <label class="eptw-label">Detailed description</label>
                                        <textarea name="work_description" class="eptw-textarea" placeholder="What exactly is being done, with what, by whom. The hazard engine reads this as you type."><?= $v('work_description'); ?></textarea>
                                        <div id="eptw-suggest" class="eptw-suggest-bar" style="display:none;margin-top:10px"></div>
                                    </div>

                                    <div class="eptw-sec">3 · Job details</div>
                                    <div class="eptw-grid-4">
                                        <div class="eptw-field">
                                            <label class="eptw-label">Start <span class="req">*</span></label>
                                            <input type="datetime-local" name="start_at" class="eptw-input" value="<?= $p ? $dtl($p->start_at) : ''; ?>" required>
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">End</label>
                                            <input type="datetime-local" name="end_at" class="eptw-input" value="<?= $p ? $dtl($p->end_at) : ''; ?>">
                                            <div id="eptw-duration" class="eptw-hint">Defaults to <?= (int) $type->default_validity_hours; ?> h after the start.</div>
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">Shift</label>
                                            <select name="shift" class="eptw-select">
                                                <?php foreach (eptw_shifts() as $k => $l) { ?><option value="<?= $k; ?>" <?= $p && $p->shift === $k ? 'selected' : ''; ?>><?= $l; ?></option><?php } ?>
                                            </select>
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">No. of workers</label>
                                            <input type="number" min="0" name="workers_count" class="eptw-input" value="<?= $p ? (int) $p->workers_count : ''; ?>">
                                        </div>
                                    </div>
                                    <div id="eptw-simops-preview"></div>

                                    <div class="eptw-sec">4 · People</div>
                                    <div class="eptw-grid-3">
                                        <?php if ($is_coord) { ?>
                                            <div class="eptw-field">
                                                <label class="eptw-label">Initiator / engineer</label>
                                                <select name="engineer_id" class="eptw-select">
                                                    <?php $eng_id = $p ? (int) $p->engineer_id : (int) eptw_me()['staff_id']; $all = $engineers + [(int) eptw_me()['staff_id'] => eptw_staff_name(eptw_me()['staff_id'])] + ($eng_id ? [$eng_id => eptw_staff_name($eng_id)] : []); ?>
                                                    <?php foreach ($all as $sid => $name) { ?><option value="<?= $sid; ?>" <?= $eng_id === (int) $sid ? 'selected' : ''; ?>><?= html_escape($name); ?></option><?php } ?>
                                                </select>
                                                <div class="eptw-hint">Who is performing authority for this permit.</div>
                                            </div>
                                        <?php } else { ?>
                                            <div class="eptw-field">
                                                <label class="eptw-label">Initiator / engineer</label>
                                                <input class="eptw-input" value="<?= html_escape($p ? $p->engineer_name : eptw_staff_name(eptw_me()['staff_id'])); ?>" disabled>
                                            </div>
                                        <?php } ?>
                                        <div class="eptw-field">
                                            <label class="eptw-label">Area Authority</label>
                                            <select name="area_authority_id" class="eptw-select">
                                                <option value="">— to be assigned —</option>
                                                <?php foreach ($staff_aa as $sid => $name) { ?><option value="<?= $sid; ?>" <?= $p && (int) $p->area_authority_id === (int) $sid ? 'selected' : ''; ?>><?= html_escape($name); ?></option><?php } ?>
                                            </select>
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">HSE Officer</label>
                                            <select name="hse_officer_id" class="eptw-select">
                                                <option value="">— to be assigned —</option>
                                                <?php foreach ($staff_hse as $sid => $name) { ?><option value="<?= $sid; ?>" <?= $p && (int) $p->hse_officer_id === (int) $sid ? 'selected' : ''; ?>><?= html_escape($name); ?></option><?php } ?>
                                            </select>
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">Permit holder</label>
                                            <input name="permit_holder" class="eptw-input" value="<?= $v('permit_holder'); ?>" placeholder="Person holding the permit at site">
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">Site supervisor</label>
                                            <input name="supervisor" class="eptw-input" value="<?= $v('supervisor'); ?>">
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">Contact no.</label>
                                            <input name="contact_no" class="eptw-input" value="<?= $v('contact_no'); ?>">
                                        </div>
                                    </div>

                                    <?php
                                    $general = []; $personnel = [];
                                    foreach ($type->extra_fields as $f) { if (($f['group'] ?? '') === 'personnel') { $personnel[] = $f; } else { $general[] = $f; } }
                                    $render_field = function ($f) use ($ex, $type) {
                                        $key = $f['key']; $val = $ex[$key] ?? '';
                                        echo '<div class="eptw-field' . (in_array($f['type'], ['checkboxes', 'detect', 'textarea'], true) ? ' eptw-field-wide" style="grid-column:1/-1' : '') . '">';
                                        echo '<label class="eptw-label">' . html_escape($f['label']) . '</label>';
                                        switch ($f['type']) {
                                            case 'number':
                                                echo '<input type="number" step="any" name="extra[' . $key . ']" class="eptw-input" value="' . html_escape($val) . '">';
                                                break;
                                            case 'yesno':
                                                echo '<div class="eptw-seg">';
                                                foreach (['yes' => 'Yes', 'no' => 'No'] as $ov => $ol) {
                                                    echo '<label class="' . $ov . '"><input type="radio" name="extra[' . $key . ']" value="' . $ov . '" ' . ($val === $ov ? 'checked' : '') . '>' . $ol . '</label>';
                                                }
                                                echo '</div>';
                                                break;
                                            case 'select':
                                                echo '<select name="extra[' . $key . ']" class="eptw-select"><option value="">—</option>';
                                                foreach (($f['options'] ?? []) as $o) { echo '<option ' . ($val === $o ? 'selected' : '') . '>' . html_escape($o) . '</option>'; }
                                                echo '</select>';
                                                break;
                                            case 'checkboxes':
                                                echo '<div class="eptw-hazard-grid">';
                                                foreach (($f['options'] ?? []) as $o) {
                                                    echo '<label class="eptw-check" style="margin:0;padding:6px 10px;border:1px solid var(--e-line);border-radius:10px"><input type="checkbox" name="extra[' . $key . '][]" value="' . html_escape($o) . '" ' . (in_array($o, (array) $val, true) ? 'checked' : '') . '> <span>' . html_escape($o) . '</span></label>';
                                                }
                                                echo '</div>';
                                                break;
                                            case 'detect':
                                                echo '<div class="eptw-checklist">';
                                                foreach (($f['options'] ?? []) as $o) {
                                                    $ok = get_instance()->permits->hkey($o); $cur = $val[$o] ?? '';
                                                    echo '<div class="eptw-check-row"><span class="eptw-check-name">' . html_escape($o) . '</span><div class="eptw-seg">';
                                                    echo '<label class="detected"><input type="radio" name="extra[' . $key . '][' . $ok . ']" value="detected" ' . ($cur === 'detected' ? 'checked' : '') . '>Detected</label>';
                                                    echo '<label class="not_detected"><input type="radio" name="extra[' . $key . '][' . $ok . ']" value="not_detected" ' . ($cur === 'not_detected' ? 'checked' : '') . '>Not detected</label>';
                                                    echo '</div></div>';
                                                }
                                                echo '</div>';
                                                break;
                                            case 'textarea':
                                                echo '<textarea name="extra[' . $key . ']" class="eptw-textarea" style="min-height:70px">' . html_escape($val) . '</textarea>';
                                                break;
                                            default:
                                                echo '<input name="extra[' . $key . ']" class="eptw-input" value="' . html_escape($val) . '">';
                                        }
                                        echo '</div>';
                                    };
                                    ?>
                                    <?php if (count($personnel)) { ?>
                                        <div class="eptw-grid-3"><?php foreach ($personnel as $f) { $render_field($f); } ?></div>
                                    <?php } ?>

                                    <?php if (count($general)) { ?>
                                        <div class="eptw-sec">5 · <?= html_escape($type->code); ?>-specific details</div>
                                        <div class="eptw-grid-3"><?php foreach ($general as $f) { $render_field($f); } ?></div>
                                    <?php } ?>

                                    <div class="eptw-sec">6 · Hazard identification</div>
                                    <div class="eptw-hazard-grid">
                                        <?php foreach ($type->hazards as $h) { $k = $this->permits->hkey($h); $cur = $hz[$h] ?? ''; ?>
                                            <div class="eptw-hazard" data-hazard-key="<?= $k; ?>">
                                                <span><?= html_escape($h); ?></span>
                                                <div class="eptw-seg">
                                                    <label class="yes"><input type="radio" name="hazard[<?= $k; ?>]" value="yes" <?= $cur === 'yes' ? 'checked' : ''; ?>>Yes</label>
                                                    <label class="no"><input type="radio" name="hazard[<?= $k; ?>]" value="no" <?= $cur === 'no' || $cur === '' ? 'checked' : ''; ?>>No</label>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                    <div class="eptw-field" style="margin-top:10px">
                                        <label class="eptw-label">Additional hazards (one per line)</label>
                                        <textarea name="extra_hazards" class="eptw-textarea" style="min-height:60px" placeholder="Anything not in the list above"><?= html_escape(implode("\n", $p ? $p->extra_hazards : [])); ?></textarea>
                                    </div>

                                    <?php $n = 7; foreach ($type->controls as $section) { ?>
                                        <div class="eptw-sec"><?= $n++; ?> · <?= html_escape($section['title']); ?>
                                            <a href="#" class="eptw-btn eptw-btn-sm eptw-btn-ghost" data-eptw-all-controls="yes" title="Mark all as Yes"><i class="fa fa-check-double"></i> All yes</a>
                                        </div>
                                        <div class="eptw-checklist">
                                            <?php foreach ($section['items'] as $item) { $k = $this->permits->hkey($item); $cur = $ct[$item]['v'] ?? ''; $rem = $ct[$item]['r'] ?? ''; ?>
                                                <div class="eptw-check-row <?= $cur !== '' && $cur !== 'yes' ? 'show-remark' : ''; ?>" data-control-key="<?= $k; ?>">
                                                    <span class="eptw-check-name"><?= html_escape($item); ?></span>
                                                    <div class="eptw-seg">
                                                        <label class="yes"><input type="radio" name="control[<?= $k; ?>]" value="yes" <?= $cur === 'yes' ? 'checked' : ''; ?>>Yes</label>
                                                        <label class="no"><input type="radio" name="control[<?= $k; ?>]" value="no" <?= $cur === 'no' ? 'checked' : ''; ?>>No</label>
                                                        <label class="na"><input type="radio" name="control[<?= $k; ?>]" value="na" <?= $cur === 'na' ? 'checked' : ''; ?>>N/A</label>
                                                    </div>
                                                    <div class="eptw-check-remark"><input name="control_remark[<?= $k; ?>]" class="eptw-input" value="<?= html_escape($rem); ?>" placeholder="Remark — why not / what instead"></div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>

                                    <div class="eptw-sec"><?= $n++; ?> · Isolation / LOTO <?php if ($type->isolation_required) { ?><span class="eptw-badge info" style="margin-left:6px">required for this type</span><?php } ?></div>
                                    <div class="eptw-grid-3">
                                        <div class="eptw-field" style="grid-column:1/-1">
                                            <label class="eptw-check"><input type="checkbox" name="isolation_required" value="1" <?= ($p ? $p->isolation_required : $type->isolation_required) ? 'checked' : ''; ?>> <span><b>Isolation required</b></span></label>
                                            <label class="eptw-check"><input type="checkbox" name="loto_applied" value="1" <?= $p && $p->loto_applied ? 'checked' : ''; ?>> <span>LOTO applied</span></label>
                                            <label class="eptw-check"><input type="checkbox" name="zero_energy_verified" value="1" <?= $p && $p->zero_energy_verified ? 'checked' : ''; ?>> <span>Zero energy verified</span></label>
                                            <label class="eptw-check"><input type="checkbox" name="gas_test_required" value="1" <?= ($p ? $p->gas_test_required : $type->gas_test_required) ? 'checked' : ''; ?>> <span><b>Gas testing required</b> before and during work</span></label>
                                        </div>
                                        <div class="eptw-field"><label class="eptw-label">Isolation type</label><input name="isolation_type" class="eptw-input" value="<?= $v('isolation_type'); ?>" placeholder="Electrical / mechanical / process"></div>
                                        <div class="eptw-field"><label class="eptw-label">Isolation certificate no.</label><input name="isolation_cert_no" class="eptw-input" value="<?= $v('isolation_cert_no'); ?>"></div>
                                        <div class="eptw-field"><label class="eptw-label">Isolation authority</label><input name="isolation_authority" class="eptw-input" value="<?= $v('isolation_authority'); ?>"></div>
                                        <div class="eptw-field" style="grid-column:1/-1"><label class="eptw-label">Lock / tag numbers</label><input name="lock_tag_numbers" class="eptw-input" value="<?= $v('lock_tag_numbers'); ?>" placeholder="L-014, L-015 / T-221"></div>
                                    </div>

                                    <div class="eptw-sec"><?= $n++; ?> · PPE & remarks</div>
                                    <div class="eptw-grid-2">
                                        <div class="eptw-field">
                                            <label class="eptw-label">PPE required (one per line)</label>
                                            <textarea name="ppe" class="eptw-textarea" style="min-height:90px"><?= html_escape(implode("\n", $p ? $p->ppe : $type->ppe)); ?></textarea>
                                        </div>
                                        <div class="eptw-field">
                                            <label class="eptw-label">Remarks</label>
                                            <textarea name="remarks" class="eptw-textarea" style="min-height:90px"><?= $v('remarks'); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="eptw-formbar">
                                        <?php if (!$p && $is_coord) { ?>
                                            <label class="eptw-check" style="margin:0"><input type="checkbox" name="source" value="paper"> <span class="eptw-small">Recording a <b>paper permit</b></span></label>
                                        <?php } ?>
                                        <span class="spacer"></span>
                                        <a href="<?= $p ? admin_url('eptw/view/' . $p->id) : admin_url('eptw/register'); ?>" class="eptw-btn eptw-btn-ghost">Cancel</a>
                                        <button type="submit" class="eptw-btn" data-after="save"><i class="fa fa-save"></i> Save draft</button>
                                        <?php if (eptw_can('request_number')) { ?>
                                            <button type="submit" class="eptw-btn eptw-btn-primary" data-after="submit"><i class="fa-solid fa-paper-plane"></i> Save &amp; request permit number</button>
                                        <?php } ?>
                                        <?php if (eptw_can('issue')) { ?>
                                            <button type="submit" class="eptw-btn eptw-btn-dark" data-after="issue" title="Coordinator direct issue — approvals recorded on paper"><i class="fa-solid fa-stamp"></i> Save &amp; issue number</button>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="eptw-card">
                                <div class="eptw-card-head"><h3><i class="fa-solid fa-circle-info"></i> How this works</h3></div>
                                <div class="eptw-card-body eptw-small">
                                    <p><b>Nothing is valid on site without a permit number.</b> Saving creates a draft. "Request permit number" sends it to the Area Authority and HSE for their signatures; the PTW Coordinator then issues the number.</p>
                                    <p>The hazards and controls come from the <b><?= html_escape($type->name); ?> V3</b> template. As you type the description, matching hazards are pre-ticked and the relevant controls highlighted — check them, don't trust them blindly.</p>
                                    <p class="eptw-muted" style="margin-bottom:0">Default validity for this type: <b><?= (int) $type->default_validity_hours; ?> h</b>. Approval steps: <?php foreach ($type->approvals as $s) { echo '<span class="eptw-badge muted">' . html_escape($this->permits->step_label($s)) . '</span> '; } ?></p>
                                </div>
                            </div>
                            <div class="eptw-card">
                                <div class="eptw-card-head"><h3><i class="fa-solid fa-camera"></i> Site evidence</h3></div>
                                <div class="eptw-card-body eptw-small">
                                    <?php if ($camera === 'disabled') { ?>
                                        <p class="eptw-muted" style="margin:0">Camera capture is <b>disabled</b> for this project. Documents and photos are added from device storage on the permit page after saving.</p>
                                    <?php } elseif ($camera === 'restricted') { ?>
                                        <p class="eptw-muted" style="margin:0">Camera is <b>restricted</b> on this project — upload scanned documents from storage on the permit page after saving.</p>
                                    <?php } else { ?>
                                        <p class="eptw-muted" style="margin:0">Photos, scans and the RA/JSA are attached on the permit page after saving. On a phone you can take the photo directly.</p>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            <?php } ?>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
