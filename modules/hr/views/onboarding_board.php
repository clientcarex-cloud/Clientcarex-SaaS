<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_edit = has_permission('hr_onboarding', '', 'edit') || is_admin();

$total = count($items);
$done  = count(array_filter($items, function ($i) { return $i['status'] !== 'pending'; }));
$pct   = $total ? round($done / $total * 100) : 0;

$ob_status = [
    'in_progress' => ['label' => 'In Progress', 'class' => 'warning'],
    'completed'   => ['label' => 'Completed',   'class' => 'success'],
    'cancelled'   => ['label' => 'Cancelled',   'class' => 'default'],
];
$cur = $ob_status[$ob['status']] ?? $ob_status['in_progress'];

// Group items by phase.
$by_phase = [];
foreach ($items as $it) {
    $by_phase[$it['phase']][] = $it;
}
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                            <div>
                                <h4 class="bold" style="margin:0;"><i class="fa fa-clipboard-check text-success"></i> <?php echo html_escape(trim($ob['firstname'] . ' ' . $ob['lastname'])); ?></h4>
                                <div class="text-muted">
                                    <?php echo html_escape($ob['template_name'] ?: 'Onboarding'); ?>
                                    <?php echo $ob['employee_code'] ? ' · ' . html_escape($ob['employee_code']) : ''; ?>
                                    <?php echo $ob['start_date'] ? ' · Started ' . _d($ob['start_date']) : ''; ?>
                                    <?php echo $ob['target_date'] ? ' · Target ' . _d($ob['target_date']) : ''; ?>
                                </div>
                            </div>
                            <div>
                                <a href="<?php echo admin_url('hr/onboarding'); ?>" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> <?php echo _l('back'); ?></a>
                                <span class="label label-<?php echo $cur['class']; ?>" style="font-size:12px;"><?php echo html_escape($cur['label']); ?></span>
                            </div>
                        </div>

                        <div style="margin-top:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <div style="flex:1;min-width:220px;">
                                <div class="progress" style="height:22px;margin:0;">
                                    <div class="progress-bar progress-bar-success" style="width:<?php echo $pct; ?>%;font-size:12px;line-height:22px;"><?php echo $pct; ?>% (<?php echo $done; ?>/<?php echo $total; ?>)</div>
                                </div>
                            </div>
                            <?php if ($can_edit) { ?>
                                <div>
                                    <button type="button" class="btn btn-default btn-sm" onclick="openTaskModal()"><i class="fa fa-plus"></i> Add task</button>
                                    <?php echo form_open(admin_url('hr/update_onboarding_status/' . $ob['id']), ['style' => 'display:inline;']); ?>
                                        <select name="status" class="form-control input-sm" style="display:inline-block;width:auto;" onchange="this.form.submit()">
                                            <?php foreach ($ob_status as $sk => $sm) { ?>
                                                <option value="<?php echo $sk; ?>" <?php echo $ob['status'] === $sk ? 'selected' : ''; ?>><?php echo html_escape($sm['label']); ?></option>
                                            <?php } ?>
                                        </select>
                                    <?php echo form_close(); ?>
                                </div>
                            <?php } ?>
                        </div>

                        <hr class="hr-panel-heading" />

                        <?php if (!$total) { ?>
                            <p class="text-muted">No tasks in this checklist yet<?php echo $can_edit ? ' — add one above.' : '.'; ?></p>
                        <?php } ?>

                        <?php foreach ($phases as $pk => $pm) {
                            if (empty($by_phase[$pk])) {
                                continue;
                            }
                        ?>
                            <div style="margin-bottom:18px;">
                                <h5 style="font-weight:800;color:<?php echo $pm['color']; ?>;margin:0 0 8px;"><i class="fa <?php echo $pm['icon']; ?>"></i> <?php echo html_escape($pm['label']); ?></h5>
                                <table class="table" style="margin-bottom:0;">
                                    <tbody>
                                        <?php foreach ($by_phase[$pk] as $it) {
                                            $stm = $statuses[$it['status']] ?? $statuses['pending'];
                                            $overdue = $it['status'] === 'pending' && $it['due_date'] && strtotime($it['due_date']) < strtotime(date('Y-m-d'));
                                        ?>
                                            <tr>
                                                <td style="width:40px;text-align:center;">
                                                    <?php if ($can_edit) { ?>
                                                        <a href="#" class="onb-toggle" data-id="<?php echo (int) $it['id']; ?>" data-status="<?php echo $it['status']; ?>" title="Toggle done">
                                                            <i class="fa <?php echo $stm['icon']; ?> fa-lg" style="color:<?php echo $stm['color']; ?>;"></i>
                                                        </a>
                                                    <?php } else { ?>
                                                        <i class="fa <?php echo $stm['icon']; ?> fa-lg" style="color:<?php echo $stm['color']; ?>;"></i>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <span class="bold" style="<?php echo $it['status'] === 'done' ? 'text-decoration:line-through;color:#94a3b8;' : ''; ?>"><?php echo html_escape($it['title']); ?></span>
                                                    <?php if (!empty($it['description'])) { ?><div class="text-muted" style="font-size:12px;"><?php echo html_escape($it['description']); ?></div><?php } ?>
                                                    <div style="font-size:11px;margin-top:2px;">
                                                        <?php if ($it['due_date']) { ?>
                                                            <span class="<?php echo $overdue ? 'text-danger' : 'text-muted'; ?>"><i class="fa fa-calendar"></i> Due <?php echo _d($it['due_date']); ?><?php echo $overdue ? ' (overdue)' : ''; ?></span>
                                                        <?php } ?>
                                                        <?php if (!empty($it['asg_first'])) { ?>
                                                            <span class="text-muted" style="margin-left:8px;"><i class="fa fa-user"></i> <?php echo html_escape(trim($it['asg_first'] . ' ' . $it['asg_last'])); ?></span>
                                                        <?php } ?>
                                                        <?php if ($it['status'] === 'done' && $it['completed_at']) { ?>
                                                            <span class="text-success" style="margin-left:8px;"><i class="fa fa-check"></i> <?php echo _d($it['completed_at']); ?></span>
                                                        <?php } ?>
                                                    </div>
                                                </td>
                                                <td style="width:120px;text-align:right;">
                                                    <?php if ($can_edit) { ?>
                                                        <button type="button" class="btn btn-default btn-icon btn-sm" onclick='openTaskModal(<?php echo json_encode($it, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG); ?>)'><i class="fa fa-pencil"></i></button>
                                                        <a href="#" class="btn btn-default btn-icon btn-sm onb-na" data-id="<?php echo (int) $it['id']; ?>" title="Mark N/A"><i class="fa fa-ban"></i></a>
                                                        <a href="<?php echo admin_url('hr/delete_onboarding_item/' . $it['id']); ?>" class="btn btn-danger btn-icon btn-sm _delete"><i class="fa fa-remove"></i></a>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>

                        <?php if (!empty($ob['notes'])) { ?>
                            <div class="alert alert-info"><strong>Notes:</strong> <?php echo nl2br(html_escape($ob['notes'])); ?></div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($can_edit) { ?>
    <!-- Hidden forms to POST status changes -->
    <?php echo form_open(admin_url('hr/onboarding_item_status/0'), ['id' => 'onb_status_form', 'style' => 'display:none;']); ?>
        <input type="hidden" name="status" id="onb_status_val">
    <?php echo form_close(); ?>

    <div class="modal fade" id="task_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <?php echo form_open(admin_url('hr/save_onboarding_item'), ['id' => 'task_form']); ?>
                <input type="hidden" name="onboarding_id" value="<?php echo (int) $ob['id']; ?>">
                <input type="hidden" name="id" id="task_id">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Onboarding Task</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label>Title <small class="req text-danger">*</small></label>
                        <input type="text" name="title" id="task_title" class="form-control" maxlength="191" required></div>
                    <div class="form-group"><label>Description</label>
                        <input type="text" name="description" id="task_description" class="form-control" maxlength="255"></div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Phase</label>
                            <select name="phase" id="task_phase" class="form-control">
                                <?php foreach ($phases as $pk => $pm) { ?>
                                    <option value="<?php echo $pk; ?>"><?php echo html_escape($pm['label']); ?></option>
                                <?php } ?>
                            </select></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Due date</label>
                            <input type="date" name="due_date" id="task_due" class="form-control"></div></div>
                    </div>
                    <div class="form-group"><label>Assign to (responsible staff)</label>
                        <select name="assigned_to" id="task_assigned" class="selectpicker" data-width="100%" data-live-search="true" data-none-selected-text="Unassigned">
                            <option value="">Unassigned</option>
                            <?php foreach ($employees as $e) { ?>
                                <option value="<?php echo (int) $e['staffid']; ?>"><?php echo html_escape(trim($e['firstname'] . ' ' . $e['lastname'])); ?></option>
                            <?php } ?>
                        </select></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                </div>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
    <?php } ?>
</div>
<?php init_tail(); ?>
<script>
    var onbStatusBase = '<?php echo admin_url('hr/onboarding_item_status/'); ?>';
    function postStatus(id, status) {
        var f = $('#onb_status_form')[0];
        f.action = onbStatusBase + id;
        $('#onb_status_val').val(status);
        f.submit();
    }
    $(function () {
        $('.onb-toggle').on('click', function (e) {
            e.preventDefault();
            var id = $(this).data('id');
            var next = $(this).data('status') === 'done' ? 'pending' : 'done';
            postStatus(id, next);
        });
        $('.onb-na').on('click', function (e) {
            e.preventDefault();
            postStatus($(this).data('id'), 'na');
        });
    });
    function openTaskModal(it) {
        var f = $('#task_form')[0];
        f.reset();
        $('#task_id').val('');
        $('#task_assigned').selectpicker('val', '');
        if (it) {
            $('#task_id').val(it.id);
            $('#task_title').val(it.title);
            $('#task_description').val(it.description || '');
            $('#task_phase').val(it.phase);
            $('#task_due').val(it.due_date || '');
            $('#task_assigned').selectpicker('val', it.assigned_to || '');
        }
        $('#task_modal').modal('show');
    }
</script>
