<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_edit        = has_permission('hr_exits', '', 'edit') || is_admin();
$status_labels   = ['pending' => 'warning', 'cleared' => 'info', 'settled' => 'success'];
$clearance_items = ['ID Card & Access Returned', 'Assets / Equipment Returned', 'Handover Completed', 'Finance / Dues Cleared', 'Exit Interview Done'];
$currency        = get_base_currency();
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <h4 class="bold" style="margin:0;">Exit Management</h4>
                            <div>
                                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>
                                <?php if ($can_edit) { ?>
                                    <button type="button" class="btn btn-primary" onclick="openExitModal()"><i class="fa fa-plus"></i> New Exit / Resignation</button>
                                <?php } ?>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />
                        <?php if (!empty($active_status)) {
                            $status_filter_labels = ['open' => 'Open Exits (pending + clearance done)', 'pending' => 'Pending', 'cleared' => 'Clearance Done', 'settled' => 'Settled'];
                        ?>
                            <div class="alert alert-info" style="display:flex;align-items:center;justify-content:space-between;">
                                <span><i class="fa fa-filter"></i> Showing: <strong><?php echo html_escape($status_filter_labels[$active_status]); ?></strong></span>
                                <a href="<?php echo admin_url('hr/exits'); ?>" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Clear filter</a>
                            </div>
                        <?php } ?>
                        <table class="table table-striped dt-table">
                            <thead><tr><th>Employee</th><th>Type</th><th>Notice Date</th><th>Last Working Day</th><th>Clearance</th><th>Settlement</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($exits as $x) {
                                    $cleared = json_decode($x['clearance'], true) ?: []; ?>
                                    <tr>
                                        <td><a href="<?php echo admin_url('hr/employee/' . $x['staff_id']); ?>"><?php echo html_escape($x['firstname'] . ' ' . $x['lastname']); ?></a>
                                            <div class="text-muted" style="font-size:11px;"><?php echo html_escape($x['employee_code']); ?></div></td>
                                        <td><?php echo ucwords(str_replace('_', ' ', $x['exit_type'])); ?></td>
                                        <td><?php echo $x['notice_date'] ? _d($x['notice_date']) : '-'; ?></td>
                                        <td><?php echo $x['last_working_day'] ? _d($x['last_working_day']) : '-'; ?></td>
                                        <td><span class="bold"><?php echo count($cleared); ?>/<?php echo count($clearance_items); ?></span> items</td>
                                        <td><?php echo $x['settlement_amount'] > 0 ? app_format_money($x['settlement_amount'], $currency) : '-'; ?></td>
                                        <td><span class="label label-<?php echo $status_labels[$x['status']] ?? 'default'; ?>"><?php echo ucfirst($x['status']); ?></span></td>
                                        <td class="text-right">
                                            <?php if ($can_edit) { ?>
                                                <button type="button" class="btn btn-default btn-icon" onclick='openExitModal(<?php echo json_encode($x, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG); ?>)'><i class="fa fa-pencil"></i></button>
                                            <?php } ?>
                                            <?php if (has_permission('hr_exits', '', 'delete') || is_admin()) { ?>
                                                <a href="<?php echo admin_url('hr/delete_exit/' . $x['id']); ?>" class="btn btn-danger btn-icon _delete" data-toggle="tooltip" title="Delete (restores employee to active)"><i class="fa fa-remove"></i></a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                        <p class="text-muted">Creating an exit marks the employee <strong>On Notice</strong>. Setting status to <strong>Settled</strong> marks them Exited and deactivates their CRM login.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="exit_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <?php echo form_open(admin_url('hr/save_exit'), ['id' => 'exit_form']); ?>
                <input type="hidden" name="id" id="exit_id">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Exit Record</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label>Employee</label>
                        <select name="staff_id" id="exit_staff" class="selectpicker" data-width="100%" data-live-search="true" required>
                            <option value=""></option>
                            <?php foreach ($employees as $emp) { ?>
                                <option value="<?php echo $emp['staffid']; ?>"><?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname'] . ' (' . $emp['employee_code'] . ')'); ?></option>
                            <?php } ?>
                        </select></div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Exit Type</label>
                            <select name="exit_type" id="exit_type" class="form-control">
                                <?php foreach (['resignation' => 'Resignation', 'termination' => 'Termination', 'retirement' => 'Retirement', 'contract_end' => 'Contract End', 'other' => 'Other'] as $k => $v) { ?>
                                    <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                <?php } ?>
                            </select></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Status</label>
                            <select name="status" id="exit_status" class="form-control">
                                <option value="pending">Pending</option>
                                <option value="cleared">Clearance Done</option>
                                <option value="settled">Settled (final)</option>
                            </select></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Notice Date</label>
                            <input type="date" name="notice_date" id="exit_notice" class="form-control"></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Last Working Day</label>
                            <input type="date" name="last_working_day" id="exit_lwd" class="form-control"></div></div>
                    </div>
                    <div class="form-group"><label>Reason</label>
                        <textarea name="reason" id="exit_reason" class="form-control" rows="2"></textarea></div>
                    <div class="form-group">
                        <label>Clearance Checklist</label>
                        <?php foreach ($clearance_items as $item) { ?>
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" name="clearance[]" id="cl_<?php echo md5($item); ?>" value="<?php echo html_escape($item); ?>" class="exit-clearance">
                                <label for="cl_<?php echo md5($item); ?>"><?php echo $item; ?></label>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Final Settlement Amount</label>
                            <input type="number" step="0.01" min="0" name="settlement_amount" id="exit_amount" class="form-control" value="0"></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Settlement Note</label>
                            <input type="text" name="settlement_note" id="exit_note" class="form-control"></div></div>
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
    function openExitModal(x) {
        $('#exit_form')[0].reset();
        $('#exit_id').val('');
        $('.exit-clearance').prop('checked', false);
        $('#exit_staff').prop('disabled', false).selectpicker('refresh');
        if (x) {
            $('#exit_id').val(x.id);
            $('#exit_staff').val(x.staff_id).prop('disabled', true);
            $('#exit_type').val(x.exit_type);
            $('#exit_status').val(x.status);
            $('#exit_notice').val(x.notice_date || '');
            $('#exit_lwd').val(x.last_working_day || '');
            $('#exit_reason').val(x.reason || '');
            $('#exit_amount').val(x.settlement_amount);
            $('#exit_note').val(x.settlement_note || '');
            var cleared = [];
            try { cleared = JSON.parse(x.clearance || '[]'); } catch (e) {}
            cleared.forEach(function (item) {
                $('.exit-clearance').filter(function () { return this.value === item; }).prop('checked', true);
            });
        }
        $('#exit_staff').selectpicker('refresh');
        $('#exit_modal').modal('show');
    }
    // disabled selects don't submit - re-enable before submitting
    $('#exit_form').on('submit', function () {
        $('#exit_staff').prop('disabled', false);
    });
</script>
