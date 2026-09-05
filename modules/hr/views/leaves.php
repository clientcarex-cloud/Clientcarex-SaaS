<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_edit      = has_permission('hr_leaves', '', 'edit') || is_admin();
$can_create    = has_permission('hr_leaves', '', 'create') || is_admin();
$can_delete    = has_permission('hr_leaves', '', 'delete') || is_admin();
$status_labels = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'default'];

$chain      = isset($chain) ? $chain : [];
$role_names = isset($role_names) ? $role_names : [];
$level_label = function ($lvl) use ($chain, $role_names) {
    if (!isset($chain[$lvl - 1])) {
        return 'Level ' . (int) $lvl;
    }
    return hr_leave_level_label($chain[$lvl - 1], $role_names);
};
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="btn-group">
                                    <?php
                                    $tabs = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];
                                    foreach ($tabs as $key => $label) { ?>
                                        <a href="<?php echo admin_url('hr/leaves?status=' . $key); ?>"
                                           class="btn btn-<?php echo $status === $key ? 'primary' : 'default'; ?>"><?php echo $label; ?></a>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="col-md-6 text-right">
                                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default" data-toggle="tooltip" title="Back to HR Dashboard"><i class="fa fa-arrow-left"></i></a>
                                <a href="<?php echo admin_url('hr/allocations'); ?>" class="btn btn-default"><i class="fa fa-sliders"></i> Allocations</a>
                                <?php if ($can_edit) { ?>
                                    <button type="button" class="btn btn-default" data-toggle="modal" data-target="#leave_types_modal"><i class="fa fa-tags"></i> Leave Types</button>
                                <?php } ?>
                                <?php if ($can_create) { ?>
                                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#apply_leave_modal"><i class="fa fa-plus"></i> Apply Leave</button>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="clearfix"></div>
                        <hr class="hr-panel-heading" />

                        <table class="table table-striped dt-table">
                            <thead><tr><th>Employee</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Applied</th><th>Status</th><th>Actioned By</th><th class="text-right">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($requests as $r) { ?>
                                    <tr>
                                        <td><a href="<?php echo admin_url('hr/employee/' . $r['staff_id']); ?>"><?php echo html_escape($r['firstname'] . ' ' . $r['lastname']); ?></a></td>
                                        <td><span class="label" style="background:<?php echo html_escape($r['type_color']); ?>;color:#fff;"><?php echo html_escape($r['type_code']); ?></span>
                                            <?php echo html_escape($r['type_name']); ?><?php echo $r['is_paid'] ? '' : ' <span class="text-muted">(unpaid)</span>'; ?></td>
                                        <td data-order="<?php echo $r['from_date']; ?>"><?php echo _d($r['from_date']); ?></td>
                                        <td data-order="<?php echo $r['to_date']; ?>"><?php echo _d($r['to_date']); ?><?php echo $r['is_half_day'] ? ' <span class="label label-default">Half</span>' : ''; ?></td>
                                        <td><?php echo (float) $r['days']; ?></td>
                                        <td style="max-width:280px;white-space:normal;word-break:break-word;">
                                            <?php
                                            $reason_full = trim((string) ($r['reason'] ?? ''));
                                            if ($reason_full === '') {
                                                echo '<span class="text-muted">-</span>';
                                            } elseif (mb_strlen($reason_full) <= 60) {
                                                echo nl2br(html_escape($reason_full));
                                            } else { ?>
                                                <span class="leave-reason-short"><?php echo html_escape(mb_substr($reason_full, 0, 60)); ?>&hellip;</span>
                                                <span class="leave-reason-full" style="display:none;"><?php echo nl2br(html_escape($reason_full)); ?></span>
                                                <a href="#" class="leave-reason-toggle" style="font-size:12px;white-space:nowrap;">more</a>
                                            <?php } ?>
                                            <?php if (!empty($r['proof_file'])) {
                                                $pext = strtolower(pathinfo($r['proof_file'], PATHINFO_EXTENSION)); ?>
                                                <br><a href="#" class="leave-proof-link" style="font-size:12px;"
                                                       data-url="<?php echo admin_url('hr/leave_proof/' . $r['id']); ?>"
                                                       data-ext="<?php echo $pext; ?>"
                                                       data-name="<?php echo html_escape($r['firstname'] . ' ' . $r['lastname']); ?>">
                                                    <i class="fa fa-paperclip"></i> Proof of reason
                                                </a>
                                            <?php } ?>
                                        </td>
                                        <td data-order="<?php echo !empty($r['created_at']) ? strtotime($r['created_at']) : 0; ?>">
                                            <?php if (!empty($r['created_at'])) { ?>
                                                <span data-toggle="tooltip" title="<?php echo _dt($r['created_at']); ?>"><?php echo time_ago($r['created_at']); ?></span>
                                            <?php } else { echo '<span class="text-muted">-</span>'; } ?>
                                        </td>
                                        <td><span class="label label-<?php echo $status_labels[$r['status']] ?? 'default'; ?>"><?php echo ucfirst($r['status']); ?></span>
                                            <?php if ($r['action_note']) { ?><i class="fa fa-comment-o" data-toggle="tooltip" title="<?php echo html_escape($r['action_note']); ?>"></i><?php } ?>
                                            <?php if (!empty($chain) && $r['status'] === 'pending' && (int) $r['current_level'] >= 1) { ?>
                                                <div class="text-muted" style="font-size:11px;margin-top:3px;">
                                                    <i class="fa fa-hourglass-half"></i> Level <?php echo (int) $r['current_level']; ?>/<?php echo count($chain); ?> — awaiting <strong><?php echo html_escape($level_label((int) $r['current_level'])); ?></strong>
                                                </div>
                                            <?php } elseif (!empty($chain) && $r['status'] === 'approved') { ?>
                                                <div class="text-muted" style="font-size:11px;margin-top:3px;"><i class="fa fa-check text-success"></i> All <?php echo count($chain); ?> level(s) cleared</div>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo $r['approved_by'] ? html_escape(get_staff_full_name($r['approved_by'])) : '-'; ?></td>
                                        <td class="text-right" style="min-width:110px;">
                                            <?php $can_act = hr_can_action_leave_level($r, get_staff_user_id()); ?>
                                            <?php if ($r['status'] === 'pending') { ?>
                                                <?php if ($can_act) { ?>
                                                    <a href="#" class="btn btn-success leave-review-link"
                                                       data-url="<?php echo admin_url('hr/leave_review/' . $r['id']); ?>"
                                                       data-toggle="tooltip" title="Review the employee's workload and department cover, then approve or reject">
                                                        <i class="fa fa-check"></i> Review
                                                    </a>
                                                <?php } else { ?>
                                                    <span class="text-muted" data-toggle="tooltip" title="Waiting on this level's approver">—</span>
                                                <?php } ?>
                                            <?php } elseif ($can_edit && $r['status'] === 'approved') { ?>
                                                <a href="<?php echo admin_url('hr/leave_action/' . $r['id'] . '/cancelled'); ?>" class="btn btn-default btn-icon _delete" data-toggle="tooltip" title="Cancel (removes attendance entries)"><i class="fa fa-undo"></i></a>
                                            <?php } ?>
                                            <?php if ($can_delete) { ?>
                                                <a href="<?php echo admin_url('hr/delete_leave/' . $r['id']); ?>" class="btn btn-danger btn-icon _delete" data-toggle="tooltip" title="Delete this leave request permanently"><i class="fa fa-remove"></i></a>
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

    <!-- Apply leave modal -->
    <div class="modal fade" id="apply_leave_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <?php echo form_open_multipart(admin_url('hr/apply_leave')); ?>
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Apply Leave</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label>Employee</label>
                        <select name="staff_id" class="selectpicker" data-width="100%" data-live-search="true" required>
                            <option value=""></option>
                            <?php foreach ($employees as $emp) { ?>
                                <option value="<?php echo $emp['staffid']; ?>"><?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname'] . ' (' . $emp['employee_code'] . ')'); ?></option>
                            <?php } ?>
                        </select></div>
                    <div class="form-group"><label>Leave Type</label>
                        <select name="leave_type_id" class="selectpicker" data-width="100%" required>
                            <?php foreach ($leave_types as $lt) {
                                if (!$lt['is_active']) { continue; } ?>
                                <option value="<?php echo $lt['id']; ?>"><?php echo html_escape($lt['name'] . ' (' . $lt['code'] . ')'); ?><?php echo $lt['is_paid'] ? '' : ' - unpaid'; ?></option>
                            <?php } ?>
                        </select></div>
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="is_half_day" id="lr_half" value="1"><label for="lr_half">Half day</label>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>From</label>
                            <input type="date" name="from_date" id="lr_from" class="form-control" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>To</label>
                            <input type="date" name="to_date" id="lr_to" class="form-control" required></div></div>
                    </div>
                    <div class="form-group"><label>Reason</label>
                        <textarea name="reason" class="form-control" rows="2"></textarea></div>
                    <div class="form-group">
                        <label>Proof of reason</label>
                        <input type="file" name="proof" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.doc,.docx">
                        <small class="text-muted">Attach supporting document (medical certificate, etc.). PDF, image or Word.</small>
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

    <!-- Leave types modal -->
    <div class="modal fade" id="leave_types_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Leave Types</h4>
                </div>
                <div class="modal-body">
                    <?php echo form_open(admin_url('hr/save_leave_type'), ['id' => 'leave_type_form']); ?>
                    <input type="hidden" name="id" id="lt_id">
                    <div class="row">
                        <div class="col-md-4"><label class="control-label" style="font-size:11px;">Name</label><input type="text" name="name" id="lt_name" class="form-control" placeholder="e.g. Casual Leave" required></div>
                        <div class="col-md-2"><label class="control-label" style="font-size:11px;">Code</label><input type="text" name="code" id="lt_code" class="form-control" placeholder="CL" maxlength="10" required></div>
                        <div class="col-md-2"><label class="control-label" style="font-size:11px;">Days / year</label><input type="number" step="0.5" min="0" name="days_per_year" id="lt_days" class="form-control" placeholder="12"></div>
                        <div class="col-md-3">
                            <label class="control-label" style="font-size:11px;">Icon <span class="text-muted">(FontAwesome, optional)</span></label>
                            <div class="input-group">
                                <span class="input-group-addon"><i id="lt_icon_preview" class="fa fa-calendar-o"></i></span>
                                <input type="text" name="icon" id="lt_icon" class="form-control" placeholder="fa-coffee (auto if blank)">
                            </div>
                        </div>
                        <div class="col-md-1"><label class="control-label" style="font-size:11px;">Colour</label><input type="color" name="color" id="lt_color" class="form-control" value="#7c3aed"></div>
                    </div>
                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-3">
                            <label class="control-label" style="font-size:11px;">Carry-forward cap (days)</label>
                            <input type="number" step="0.5" min="0" name="carry_forward_max" id="lt_cf_max" class="form-control" placeholder="0 = no cap">
                        </div>
                        <div class="col-md-6" style="padding-top:26px;">
                            <label class="checkbox-inline"><input type="checkbox" name="is_paid" id="lt_paid" value="1" checked> Paid</label>
                            <label class="checkbox-inline"><input type="checkbox" name="carry_forward" id="lt_cf" value="1"> Carry Fwd</label>
                            <label class="checkbox-inline" title="Employees may apply for this type on the same day, bypassing the advance-notice rule (e.g. medical emergency)"><input type="checkbox" name="is_emergency" id="lt_emergency" value="1"> Emergency (same-day)</label>
                            <label class="checkbox-inline"><input type="checkbox" name="is_active" id="lt_active" value="1" checked> Active</label>
                        </div>
                        <div class="col-md-3" style="padding-top:20px;"><button type="submit" class="btn btn-primary btn-block">Save Leave Type</button></div>
                    </div>
                    <?php echo form_close(); ?>
                    <hr>
                    <table class="table table-striped">
                        <thead><tr><th>Name</th><th>Code</th><th>Days/Year</th><th>Paid</th><th>Carry Fwd</th><th>Active</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($leave_types as $lt) { ?>
                                <tr>
                                    <td><i class="fa <?php echo hr_leave_type_icon($lt); ?>" style="color:<?php echo html_escape($lt['color']); ?>;"></i> <?php echo html_escape($lt['name']); ?></td>
                                    <td><?php echo html_escape($lt['code']); ?><?php echo !empty($lt['is_emergency']) ? ' <span class="label label-danger" title="Emergency — same-day allowed">SOS</span>' : ''; ?></td>
                                    <td><?php echo (float) $lt['days_per_year']; ?></td>
                                    <td><?php echo $lt['is_paid'] ? 'Yes' : '<span class="text-danger">No</span>'; ?></td>
                                    <td><?php echo $lt['carry_forward'] ? ('Yes' . ((float) ($lt['carry_forward_max'] ?? 0) > 0 ? ' (max ' . (float) $lt['carry_forward_max'] . ')' : '')) : 'No'; ?></td>
                                    <td><?php echo $lt['is_active'] ? 'Yes' : 'No'; ?></td>
                                    <td class="text-right">
                                        <button type="button" class="btn btn-default btn-icon" onclick='editLeaveType(<?php echo json_encode($lt, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG); ?>)'><i class="fa fa-pencil"></i></button>
                                        <?php if (has_permission('hr_leaves', '', 'delete') || is_admin()) { ?>
                                            <a href="<?php echo admin_url('hr/delete_leave_type/' . $lt['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
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

    <!-- Leave review (workload + department cover) modal -->
    <div class="modal fade" id="leave_review_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content" id="leave_review_content">
                <div class="modal-body text-center" style="padding:40px;">
                    <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Proof-of-reason preview modal -->
    <div class="modal fade" id="leave_proof_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title"><i class="fa fa-paperclip"></i> Proof of Reason <small id="leave_proof_who" class="text-muted"></small></h4>
                </div>
                <div class="modal-body text-center" id="leave_proof_body" style="min-height:200px;">
                </div>
                <div class="modal-footer">
                    <a href="#" id="leave_proof_download" class="btn btn-default pull-left" target="_blank"><i class="fa fa-download"></i> Open / Download</a>
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function () {
        // ---- Expand / collapse a long leave reason ----
        $(document).on('click', '.leave-reason-toggle', function (e) {
            e.preventDefault();
            var $td      = $(this).closest('td');
            var expanded = $td.find('.leave-reason-full').is(':visible');
            $td.find('.leave-reason-full').toggle(!expanded);
            $td.find('.leave-reason-short').toggle(expanded);
            $(this).text(expanded ? 'more' : 'less');
        });

        // ---- Review before approving ----
        var leaveReviewSpinner = '<div class="modal-body text-center" style="padding:40px;">'
            + '<i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>';

        $(document).on('click', '.leave-review-link', function (e) {
            e.preventDefault();
            var url = $(this).data('url');
            $('#leave_review_content').html(leaveReviewSpinner);
            $('#leave_review_modal').modal('show');
            $.get(url)
                .done(function (html) { $('#leave_review_content').html(html); })
                .fail(function () {
                    $('#leave_review_content').html(
                        '<div class="modal-body"><p class="text-danger" style="padding:20px;">'
                        + 'Could not load the review. Please try again.</p>'
                        + '<div class="text-right"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div></div>'
                    );
                });
        });

        // ---- Proof-of-reason preview ----
        $(document).on('click', '.leave-proof-link', function (e) {
            e.preventDefault();
            var url = $(this).data('url');
            var ext = String($(this).data('ext') || '').toLowerCase();
            var who = $(this).data('name') || '';
            $('#leave_proof_who').text(who ? '— ' + who : '');
            $('#leave_proof_download').attr('href', url);
            var $body = $('#leave_proof_body');
            if (['jpg', 'jpeg', 'png', 'webp', 'gif'].indexOf(ext) !== -1) {
                $body.html('<img src="' + url + '" style="max-width:100%;max-height:70vh;" alt="Proof">');
            } else if (ext === 'pdf') {
                $body.html('<iframe src="' + url + '" style="width:100%;height:70vh;border:0;"></iframe>');
            } else {
                $body.html('<p class="text-muted" style="padding:30px;">This file type has no inline preview. Use <strong>Open / Download</strong> below to view it.</p>');
            }
            $('#leave_proof_modal').modal('show');
        });

        // Block pasting / drag-drop into the date fields.
        $('#lr_from, #lr_to').on('paste drop', function (e) { e.preventDefault(); return false; });

        $('#lr_half').on('change', function () {
            if (this.checked) {
                $('#lr_to').val($('#lr_from').val()).prop('readonly', true);
            } else {
                $('#lr_to').prop('readonly', false);
            }
        });
        $('#lr_from').on('change', function () {
            if ($('#lr_half').is(':checked')) { $('#lr_to').val(this.value); }
        });
    });
    function editLeaveType(lt) {
        $('#lt_id').val(lt.id);
        $('#lt_name').val(lt.name);
        $('#lt_code').val(lt.code);
        $('#lt_days').val(lt.days_per_year);
        $('#lt_color').val(lt.color);
        $('#lt_icon').val(lt.icon || '');
        $('#lt_cf_max').val(lt.carry_forward_max || '');
        $('#lt_paid').prop('checked', lt.is_paid == 1);
        $('#lt_cf').prop('checked', lt.carry_forward == 1);
        $('#lt_emergency').prop('checked', lt.is_emergency == 1);
        $('#lt_active').prop('checked', lt.is_active == 1);
        updateLtIconPreview();
    }
    function updateLtIconPreview() {
        var ic = ($('#lt_icon').val() || '').trim() || 'fa-calendar-o';
        $('#lt_icon_preview').attr('class', 'fa ' + ic);
    }
    $(function () { $('#lt_icon').on('keyup change', updateLtIconPreview); });
</script>
