<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_create = has_permission('hr_onboarding', '', 'create') || is_admin();
$can_edit   = has_permission('hr_onboarding', '', 'edit') || is_admin();
$can_delete = has_permission('hr_onboarding', '', 'delete') || is_admin();

$ob_status = [
    'in_progress' => ['label' => 'In Progress', 'class' => 'warning'],
    'completed'   => ['label' => 'Completed',   'class' => 'success'],
    'cancelled'   => ['label' => 'Cancelled',   'class' => 'default'],
];
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                            <h4 class="bold" style="margin:0;"><i class="fa fa-clipboard-check text-success"></i> <?php echo _l('hr_onboarding'); ?></h4>
                            <div>
                                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> <?php echo _l('back'); ?></a>
                                <?php if ($can_edit) { ?>
                                    <a href="<?php echo admin_url('hr/onboarding_templates'); ?>" class="btn btn-default"><i class="fa fa-list-check"></i> Templates</a>
                                <?php } ?>
                                <?php if ($can_create) { ?>
                                    <button type="button" class="btn btn-success" onclick="$('#start_onb_modal').modal('show');"><i class="fa fa-plus"></i> Start Onboarding</button>
                                <?php } ?>
                            </div>
                        </div>
                        <p class="text-muted" style="margin:6px 0 0;">Track each new joiner through a structured onboarding checklist.</p>
                        <hr class="hr-panel-heading" />

                        <table class="table table-striped dt-table">
                            <thead><tr><th>Employee</th><th>Template</th><th>Progress</th><th>Started</th><th>Target</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($onboardings as $o) {
                                    $total = (int) $o['total_items'];
                                    $done  = (int) $o['done_items'];
                                    $pct   = $total ? round($done / $total * 100) : 0;
                                    $st    = $ob_status[$o['status']] ?? $ob_status['in_progress'];
                                ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo admin_url('hr/onboarding_board/' . $o['id']); ?>" class="bold"><?php echo html_escape(trim($o['firstname'] . ' ' . $o['lastname'])); ?></a>
                                            <?php if ($o['employee_code']) { ?><div class="text-muted" style="font-size:11px;"><?php echo html_escape($o['employee_code']); ?></div><?php } ?>
                                        </td>
                                        <td><?php echo html_escape($o['template_name'] ?: '—'); ?></td>
                                        <td style="min-width:160px;">
                                            <div class="progress" style="margin-bottom:4px;height:16px;">
                                                <div class="progress-bar progress-bar-success" style="width:<?php echo $pct; ?>%;"><?php echo $pct; ?>%</div>
                                            </div>
                                            <small class="text-muted"><?php echo $done; ?> / <?php echo $total; ?> tasks</small>
                                        </td>
                                        <td style="font-size:12px;"><?php echo $o['start_date'] ? _d($o['start_date']) : '—'; ?></td>
                                        <td style="font-size:12px;"><?php echo $o['target_date'] ? _d($o['target_date']) : '—'; ?></td>
                                        <td><span class="label label-<?php echo $st['class']; ?>"><?php echo html_escape($st['label']); ?></span></td>
                                        <td class="text-right">
                                            <a href="<?php echo admin_url('hr/onboarding_board/' . $o['id']); ?>" class="btn btn-default btn-icon" title="Open board"><i class="fa fa-arrow-right"></i></a>
                                            <?php if ($can_delete) { ?>
                                                <a href="<?php echo admin_url('hr/delete_onboarding/' . $o['id']); ?>" class="btn btn-danger btn-icon _delete" title="Delete"><i class="fa fa-remove"></i></a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($can_create) { ?>
    <div class="modal fade" id="start_onb_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <?php echo form_open(admin_url('hr/start_onboarding')); ?>
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title"><i class="fa fa-clipboard-check text-success"></i> Start Onboarding</h4>
                </div>
                <div class="modal-body">
                    <?php if (!count($employees)) { ?>
                        <p class="text-muted">All active employees already have an onboarding record. Add a new employee first, or delete an existing onboarding to restart.</p>
                    <?php } ?>
                    <div class="form-group"><label>Employee <small class="req text-danger">*</small></label>
                        <select name="staff_id" class="selectpicker" data-width="100%" data-live-search="true" required>
                            <option value=""></option>
                            <?php foreach ($employees as $e) { ?>
                                <option value="<?php echo (int) $e['staffid']; ?>"><?php echo html_escape(trim($e['firstname'] . ' ' . $e['lastname']) . ($e['employee_code'] ? ' (' . $e['employee_code'] . ')' : '')); ?></option>
                            <?php } ?>
                        </select></div>
                    <div class="form-group"><label>Checklist template <small class="req text-danger">*</small></label>
                        <select name="template_id" class="form-control" required>
                            <?php foreach ($templates as $t) { ?>
                                <option value="<?php echo (int) $t['id']; ?>" <?php echo !empty($t['is_default']) ? 'selected' : ''; ?>><?php echo html_escape($t['name']); ?> (<?php echo (int) $t['item_count']; ?> tasks)</option>
                            <?php } ?>
                        </select></div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Start date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo _d(date('Y-m-d')) ? date('Y-m-d') : ''; ?>">
                            <small class="text-muted">Task due dates are calculated from this.</small></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Target completion</label>
                            <input type="date" name="target_date" class="form-control"></div></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-success" <?php echo count($employees) && count($templates) ? '' : 'disabled'; ?>>Start</button>
                </div>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
    <?php } ?>
</div>
<?php init_tail(); ?>
