<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="ptk-wrap">

            <?php $active = 'settings'; include __DIR__ . '/_nav.php'; ?>

            <div class="ptk-grid ptk-grid-1-1 ptk-grid-settings">

                <!-- ── Automation settings ── -->
                <div class="ptk-card">
                    <h4 class="ptk-card-title"><i class="fa fa-robot"></i> <?= _l('pro_tickets_settings_automation'); ?></h4>
                    <?= form_open(admin_url('pro_tickets/settings')); ?>

                        <div class="ptk-form-field">
                            <label><?= _l('pro_tickets_set_auto_assign'); ?></label>
                            <?php $auto_assign = get_option('pro_tickets_auto_assign'); ?>
                            <select name="auto_assign" class="ptk-select">
                                <option value="off" <?= $auto_assign === 'off' ? 'selected' : ''; ?>><?= _l('pro_tickets_set_auto_assign_off'); ?></option>
                                <option value="round_robin" <?= $auto_assign === 'round_robin' ? 'selected' : ''; ?>><?= _l('pro_tickets_set_auto_assign_rr'); ?></option>
                                <option value="least_busy" <?= $auto_assign === 'least_busy' ? 'selected' : ''; ?>><?= _l('pro_tickets_set_auto_assign_lb'); ?></option>
                            </select>
                        </div>

                        <div class="ptk-form-field">
                            <label><?= _l('pro_tickets_set_auto_assign_role'); ?></label>
                            <?php $auto_assign_role = (int) get_option('pro_tickets_auto_assign_role'); ?>
                            <select name="auto_assign_role" class="ptk-select">
                                <option value="0"><?= _l('pro_tickets_set_auto_assign_role_any'); ?></option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int) $role->roleid; ?>" <?= $auto_assign_role === (int) $role->roleid ? 'selected' : ''; ?>><?= html_escape($role->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="ptk-muted ptk-small"><?= _l('pro_tickets_set_auto_assign_role_hint'); ?></span>
                        </div>

                        <div class="ptk-form-field ptk-form-inline">
                            <label class="ptk-check">
                                <input type="checkbox" name="auto_close_enabled" value="1" <?= get_option('pro_tickets_auto_close_enabled') == '1' ? 'checked' : ''; ?>>
                                <?= _l('pro_tickets_set_auto_close'); ?>
                            </label>
                            <input type="number" name="auto_close_days" class="ptk-input ptk-input-xs" min="1" max="365" value="<?= (int) get_option('pro_tickets_auto_close_days'); ?>">
                            <span class="ptk-muted ptk-small"><?= _l('pro_tickets_set_auto_close_days'); ?></span>
                        </div>

                        <div class="ptk-form-field">
                            <label><?= _l('pro_tickets_set_warning_pct'); ?></label>
                            <input type="number" name="sla_warning_pct" class="ptk-input ptk-input-xs" min="1" max="99" value="<?= (int) get_option('pro_tickets_sla_warning_pct'); ?>">
                        </div>

                        <div class="ptk-form-field">
                            <label class="ptk-check">
                                <input type="checkbox" name="bump_priority_on_breach" value="1" <?= get_option('pro_tickets_bump_priority_on_breach') == '1' ? 'checked' : ''; ?>>
                                <?= _l('pro_tickets_set_bump_priority'); ?>
                            </label>
                        </div>

                        <div class="ptk-form-field">
                            <label class="ptk-check">
                                <input type="checkbox" name="notify_watchers" value="1" <?= get_option('pro_tickets_notify_watchers') == '1' ? 'checked' : ''; ?>>
                                <?= _l('pro_tickets_set_notify_watchers'); ?>
                            </label>
                        </div>

                        <div class="ptk-form-field">
                            <label class="ptk-check">
                                <input type="checkbox" name="smart_admin_enabled" value="1" <?= pro_tickets_smart_admin_enabled() ? 'checked' : ''; ?>>
                                <?= _l('pro_tickets_set_smart_admin'); ?>
                            </label>
                            <span class="ptk-muted ptk-small"><?= _l('pro_tickets_set_smart_admin_hint'); ?></span>
                        </div>

                        <div class="ptk-form-field">
                            <label><?= _l('pro_tickets_set_new_template'); ?></label>
                            <?php $new_ticket_template = (int) get_option('pro_tickets_new_ticket_template'); ?>
                            <select name="new_ticket_template" class="ptk-select">
                                <option value="0"><?= _l('pro_tickets_set_new_template_none'); ?></option>
                                <?php foreach ($canned as $cr): ?>
                                    <option value="<?= (int) $cr->id; ?>" <?= $new_ticket_template === (int) $cr->id ? 'selected' : ''; ?>><?= html_escape($cr->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="ptk-muted ptk-small"><?= _l('pro_tickets_set_new_template_hint'); ?></span>
                        </div>

                        <div class="ptk-form-actions">
                            <button type="submit" class="ptk-btn ptk-btn-primary"><i class="fa fa-check"></i> <?= _l('submit'); ?></button>
                            <button type="button" class="ptk-btn ptk-btn-light" id="ptk-run-automation" data-url="<?= admin_url('pro_tickets/run_automation'); ?>" data-done="<?= _l('pro_tickets_automation_ran'); ?>">
                                <i class="fa fa-bolt"></i> <?= _l('pro_tickets_run_automation_now'); ?>
                            </button>
                        </div>

                    <?= form_close(); ?>
                </div>

                <!-- ── SLA policies ── -->
                <div class="ptk-card">
                    <h4 class="ptk-card-title"><i class="fa fa-shield-halved"></i> <?= _l('pro_tickets_sla_policies'); ?></h4>
                    <p class="ptk-muted ptk-small"><?= _l('pro_tickets_sla_policies_hint'); ?></p>

                    <div class="ptk-table-scroll">
                        <table class="ptk-table">
                            <thead>
                                <tr>
                                    <th><?= _l('pro_tickets_sla_name'); ?></th>
                                    <th><?= _l('pro_tickets_sla_department'); ?></th>
                                    <th><?= _l('pro_tickets_sla_priority'); ?></th>
                                    <th class="text-right"><?= _l('pro_tickets_first_response'); ?></th>
                                    <th class="text-right"><?= _l('pro_tickets_resolution'); ?></th>
                                    <th><?= _l('pro_tickets_sla_escalate_to'); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    $dep_names = [];
                                    foreach ($departments as $dep) {
                                        $dep_names[$dep->departmentid] = $dep->name;
                                    }
                                    $pr_names = [];
                                    foreach ($priorities as $pr) {
                                        $pr_names[$pr->priorityid] = $pr->name;
                                    }
                                ?>
                                <?php foreach ($slas as $sla): ?>
                                    <tr class="<?= $sla->active ? '' : 'ptk-row-inactive'; ?>">
                                        <td><?= html_escape($sla->name); ?></td>
                                        <td><?= $sla->department_id ? html_escape($dep_names[$sla->department_id] ?? '?') : '<span class="ptk-muted">' . _l('pro_tickets_sla_any') . '</span>'; ?></td>
                                        <td><?= $sla->priority_id ? html_escape($pr_names[$sla->priority_id] ?? '?') : '<span class="ptk-muted">' . _l('pro_tickets_sla_any') . '</span>'; ?></td>
                                        <td class="text-right"><?= html_escape(pro_tickets_human_duration($sla->frt_minutes)); ?></td>
                                        <td class="text-right"><?= html_escape(pro_tickets_human_duration($sla->res_minutes)); ?></td>
                                        <td><?= $sla->escalate_to ? html_escape(get_staff_full_name($sla->escalate_to)) : '—'; ?></td>
                                        <td class="text-right ptk-nowrap">
                                            <button type="button" class="ptk-icon-btn ptk-sla-edit"
                                                data-sla='<?= html_escape(json_encode([
                                                    'id'            => (int) $sla->id,
                                                    'name'          => $sla->name,
                                                    'department_id' => (int) $sla->department_id,
                                                    'priority_id'   => (int) $sla->priority_id,
                                                    'frt_hours'     => round($sla->frt_minutes / 60, 2),
                                                    'res_hours'     => round($sla->res_minutes / 60, 2),
                                                    'escalate_to'   => (int) $sla->escalate_to,
                                                    'active'        => (int) $sla->active,
                                                    'sort_order'    => (int) $sla->sort_order,
                                                ])); ?>'><i class="fa fa-pen"></i></button>
                                            <a class="ptk-icon-btn ptk-icon-danger" href="<?= admin_url('pro_tickets/sla_delete/' . (int) $sla->id); ?>" onclick="return confirm('<?= _l('pro_tickets_sla_delete_confirm'); ?>');"><i class="fa fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- SLA form -->
                    <?= form_open(admin_url('pro_tickets/sla_save'), ['id' => 'ptk-sla-form']); ?>
                        <input type="hidden" name="id" id="ptk-sla-id" value="">
                        <h5 class="ptk-form-subtitle" id="ptk-sla-form-title" data-edit-label="<?= _l('pro_tickets_sla_edit'); ?>"><?= _l('pro_tickets_sla_add'); ?></h5>
                        <div class="ptk-form-row">
                            <div class="ptk-form-field">
                                <label><?= _l('pro_tickets_sla_name'); ?> *</label>
                                <input type="text" name="name" id="ptk-sla-name" class="ptk-input" required>
                            </div>
                            <div class="ptk-form-field">
                                <label><?= _l('pro_tickets_sla_department'); ?></label>
                                <select name="department_id" id="ptk-sla-department" class="ptk-select">
                                    <option value="0"><?= _l('pro_tickets_sla_any'); ?></option>
                                    <?php foreach ($departments as $dep): ?>
                                        <option value="<?= (int) $dep->departmentid; ?>"><?= html_escape($dep->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="ptk-form-field">
                                <label><?= _l('pro_tickets_sla_priority'); ?></label>
                                <select name="priority_id" id="ptk-sla-priority" class="ptk-select">
                                    <option value="0"><?= _l('pro_tickets_sla_any'); ?></option>
                                    <?php foreach ($priorities as $pr): ?>
                                        <option value="<?= (int) $pr->priorityid; ?>"><?= html_escape($pr->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="ptk-form-row">
                            <div class="ptk-form-field">
                                <label><?= _l('pro_tickets_sla_frt_hours'); ?> *</label>
                                <input type="number" name="frt_hours" id="ptk-sla-frt" class="ptk-input" min="0.25" step="0.25" required>
                            </div>
                            <div class="ptk-form-field">
                                <label><?= _l('pro_tickets_sla_res_hours'); ?> *</label>
                                <input type="number" name="res_hours" id="ptk-sla-res" class="ptk-input" min="0.25" step="0.25" required>
                            </div>
                            <div class="ptk-form-field">
                                <label><?= _l('pro_tickets_sla_escalate_to'); ?></label>
                                <select name="escalate_to" id="ptk-sla-escalate" class="ptk-select">
                                    <option value="0"><?= _l('pro_tickets_sla_escalate_none'); ?></option>
                                    <?php foreach ($staff as $member): ?>
                                        <option value="<?= (int) $member->staffid; ?>"><?= html_escape($member->firstname . ' ' . $member->lastname); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="ptk-form-field ptk-form-field-xs">
                                <label>&nbsp;</label>
                                <label class="ptk-check">
                                    <input type="checkbox" name="active" id="ptk-sla-active" value="1" checked> <?= _l('pro_tickets_sla_active'); ?>
                                </label>
                            </div>
                        </div>
                        <input type="hidden" name="sort_order" id="ptk-sla-sort" value="0">
                        <div class="ptk-form-actions">
                            <button type="submit" class="ptk-btn ptk-btn-primary"><i class="fa fa-check"></i> <?= _l('pro_tickets_sla_save'); ?></button>
                            <button type="button" class="ptk-btn ptk-btn-light" id="ptk-sla-reset" style="display:none"><i class="fa fa-xmark"></i></button>
                        </div>
                    <?= form_close(); ?>
                </div>

            </div>

            <!-- ── Department agents ── -->
            <div class="ptk-card">
                <h4 class="ptk-card-title"><i class="fa fa-users-gear"></i> <?= _l('pro_tickets_dept_agents'); ?></h4>
                <p class="ptk-muted ptk-small"><?= _l('pro_tickets_dept_agents_hint'); ?></p>

                <?= form_open(admin_url('pro_tickets/save_dept_agents')); ?>
                    <input type="hidden" name="dept_agents_submit" value="1">
                    <div class="ptk-table-scroll">
                        <table class="ptk-table ptk-dept-agents-table">
                            <thead>
                                <tr>
                                    <th style="width:32%"><?= _l('pro_tickets_sla_department'); ?></th>
                                    <th><?= _l('pro_tickets_dept_agents_members'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($departments)): ?>
                                    <tr><td colspan="2" class="ptk-muted"><?= _l('pro_tickets_dept_agents_no_departments'); ?></td></tr>
                                <?php else: ?>
                                    <?php foreach ($departments as $dep): ?>
                                        <?php $assigned = $dept_agents[(int) $dep->departmentid] ?? []; ?>
                                        <tr>
                                            <td><strong><?= html_escape($dep->name); ?></strong></td>
                                            <td>
                                                <select name="dept_agents[<?= (int) $dep->departmentid; ?>][]" class="ptk-select ptk-dept-agents-select" multiple size="4" data-placeholder="<?= _l('pro_tickets_dept_agents_members'); ?>">
                                                    <?php foreach ($staff as $member): ?>
                                                        <option value="<?= (int) $member->staffid; ?>" <?= in_array((int) $member->staffid, $assigned) ? 'selected' : ''; ?>><?= html_escape($member->firstname . ' ' . $member->lastname); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="ptk-form-actions">
                        <button type="submit" class="ptk-btn ptk-btn-primary"><i class="fa fa-check"></i> <?= _l('submit'); ?></button>
                    </div>
                <?= form_close(); ?>
            </div>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
