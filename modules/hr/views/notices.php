<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_create = has_permission('hr_notices', '', 'create') || is_admin();
$can_edit   = has_permission('hr_notices', '', 'edit') || is_admin();
$can_delete = has_permission('hr_notices', '', 'delete') || is_admin();

$role_names = [];
foreach ($roles as $r) { $role_names[(int) $r['roleid']] = $r['name']; }
$staff_names = [];
foreach ($employees as $e) { $staff_names[(int) $e['staffid']] = trim($e['firstname'] . ' ' . $e['lastname']); }

$audience_label = function ($n) use ($role_names, $staff_names) {
    if ($n['audience_type'] === 'all') {
        return '<span class="label label-success">Everyone</span>';
    }
    if ($n['audience_type'] === 'roles') {
        $out = [];
        foreach (array_filter(explode(',', (string) $n['role_ids'])) as $rid) {
            $out[] = html_escape($role_names[(int) $rid] ?? ('Role #' . (int) $rid));
        }
        return '<span class="text-muted">Roles:</span> ' . (implode(', ', $out) ?: '-');
    }
    $out = [];
    foreach (array_filter(explode(',', (string) $n['staff_ids'])) as $sid) {
        $out[] = html_escape($staff_names[(int) $sid] ?? ('Staff #' . (int) $sid));
    }
    return '<span class="text-muted">Employees:</span> ' . (implode(', ', $out) ?: '-');
};
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <h4 class="bold" style="margin:0;"><i class="fa fa-bullhorn text-info"></i> HR Notices</h4>
                            <div>
                                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>
                                <?php if ($can_create) { ?>
                                    <button type="button" class="btn btn-primary" onclick="openNoticeModal()"><i class="fa fa-plus"></i> New Notice</button>
                                <?php } ?>
                            </div>
                        </div>
                        <p class="text-muted" style="margin:6px 0 0;">Notices appear on the dashboard of every targeted user. Target everyone, specific roles, or hand-picked employees.</p>
                        <hr class="hr-panel-heading" />
                        <table class="table table-striped dt-table">
                            <thead><tr><th>Title</th><th>Audience</th><th>Priority</th><th>Window</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($notices as $n) { ?>
                                    <tr>
                                        <td>
                                            <span class="bold"><?php echo html_escape($n['title']); ?></span>
                                            <div class="text-muted" style="font-size:12px;max-width:420px;"><?php echo html_escape(mb_strimwidth((string) $n['message'], 0, 120, '…')); ?></div>
                                        </td>
                                        <td><?php echo $audience_label($n); ?></td>
                                        <td>
                                            <?php if ($n['priority'] === 'high') { ?>
                                                <span class="label label-danger">High</span>
                                            <?php } else { ?>
                                                <span class="label label-default">Normal</span>
                                            <?php } ?>
                                        </td>
                                        <td style="font-size:12px;">
                                            <?php echo $n['start_date'] ? _d($n['start_date']) : '<span class="text-muted">Now</span>'; ?>
                                            &rarr;
                                            <?php echo $n['end_date'] ? _d($n['end_date']) : '<span class="text-muted">No expiry</span>'; ?>
                                        </td>
                                        <td>
                                            <?php if ($n['active']) { ?>
                                                <span class="label label-success">Active</span>
                                            <?php } else { ?>
                                                <span class="label label-default">Inactive</span>
                                            <?php } ?>
                                        </td>
                                        <td class="text-right">
                                            <?php if ($can_edit) { ?>
                                                <button type="button" class="btn btn-default btn-icon" onclick='openNoticeModal(<?php echo json_encode($n, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG); ?>)'><i class="fa fa-pencil"></i></button>
                                            <?php } ?>
                                            <?php if ($can_delete) { ?>
                                                <a href="<?php echo admin_url('hr/delete_notice/' . $n['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
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

    <div class="modal fade" id="notice_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <?php echo form_open(admin_url('hr/save_notice'), ['id' => 'notice_form']); ?>
                <input type="hidden" name="id" id="notice_id">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Notice</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label>Title <small class="req text-danger">*</small></label>
                        <input type="text" name="title" id="notice_title" class="form-control" maxlength="191" required></div>
                    <div class="form-group"><label>Message <small class="req text-danger">*</small></label>
                        <textarea name="message" id="notice_message" class="form-control" rows="5" required></textarea></div>

                    <div class="form-group"><label>Show To</label>
                        <select name="audience_type" id="notice_audience" class="form-control">
                            <option value="all">Everyone</option>
                            <option value="roles">Specific role(s)</option>
                            <option value="employees">Selected employee(s)</option>
                        </select></div>

                    <div class="form-group" id="notice_roles_wrap" style="display:none;"><label>Role(s)</label>
                        <select name="role_ids[]" id="notice_roles" class="selectpicker" data-width="100%" data-live-search="true" multiple>
                            <?php foreach ($roles as $r) { ?>
                                <option value="<?php echo (int) $r['roleid']; ?>"><?php echo html_escape($r['name']); ?></option>
                            <?php } ?>
                        </select></div>

                    <div class="form-group" id="notice_staff_wrap" style="display:none;"><label>Employee(s)</label>
                        <select name="staff_ids[]" id="notice_staff" class="selectpicker" data-width="100%" data-live-search="true" multiple>
                            <?php foreach ($employees as $e) { ?>
                                <option value="<?php echo (int) $e['staffid']; ?>"><?php echo html_escape(trim($e['firstname'] . ' ' . $e['lastname']) . ($e['employee_code'] ? ' (' . $e['employee_code'] . ')' : '')); ?></option>
                            <?php } ?>
                        </select></div>

                    <div class="row">
                        <div class="col-md-4"><div class="form-group"><label>Priority</label>
                            <select name="priority" id="notice_priority" class="form-control">
                                <option value="normal">Normal</option>
                                <option value="high">High (highlighted)</option>
                            </select></div></div>
                        <div class="col-md-4"><div class="form-group"><label>Show From</label>
                            <input type="date" name="start_date" id="notice_start" class="form-control"></div></div>
                        <div class="col-md-4"><div class="form-group"><label>Show Until</label>
                            <input type="date" name="end_date" id="notice_end" class="form-control"></div></div>
                    </div>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="active" id="notice_active" value="1" checked>
                        <label for="notice_active">Active (uncheck to hide without deleting)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                </div>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    function noticeToggleAudience() {
        var v = $('#notice_audience').val();
        $('#notice_roles_wrap').toggle(v === 'roles');
        $('#notice_staff_wrap').toggle(v === 'employees');
    }

    function openNoticeModal(n) {
        var f = $('#notice_form')[0];
        f.reset();
        $('#notice_id').val('');
        $('#notice_roles').selectpicker('deselectAll');
        $('#notice_staff').selectpicker('deselectAll');
        $('#notice_active').prop('checked', true);
        if (n) {
            $('#notice_id').val(n.id);
            $('#notice_title').val(n.title);
            $('#notice_message').val(n.message);
            $('#notice_audience').val(n.audience_type);
            $('#notice_priority').val(n.priority);
            $('#notice_start').val(n.start_date || '');
            $('#notice_end').val(n.end_date || '');
            $('#notice_active').prop('checked', parseInt(n.active, 10) === 1);
            if (n.role_ids) { $('#notice_roles').selectpicker('val', String(n.role_ids).split(',')); }
            if (n.staff_ids) { $('#notice_staff').selectpicker('val', String(n.staff_ids).split(',')); }
        }
        noticeToggleAudience();
        $('#notice_roles, #notice_staff').selectpicker('refresh');
        $('#notice_modal').modal('show');
    }

    $(function () {
        $('#notice_audience').on('change', noticeToggleAudience);
    });
</script>
