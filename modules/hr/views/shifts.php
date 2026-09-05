<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_edit  = has_permission('hr_shifts', '', 'edit') || is_admin();
$day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <h4 class="bold" style="margin:0;">Shifts</h4>
                            <div>
                                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back</a>
                                <a href="<?php echo admin_url('hr/shift_sheet'); ?>" target="_blank" class="btn btn-info btn-sm" data-toggle="tooltip" title="Open a printable duty roster grouped by shift"><i class="fa fa-print"></i> Shift Sheet</a>
                                <?php if ($can_edit) { ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="openShiftModal()"><i class="fa fa-plus"></i> New Shift</button>
                                <?php } ?>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />
                        <table class="table table-striped">
                            <thead><tr><th>Shift</th><th>Timing</th><th>Week Off</th><th class="text-right">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($shifts as $shift) { ?>
                                    <tr>
                                        <td>
                                            <span class="bold"><?php echo html_escape($shift['name']); ?></span>
                                            <?php if ($shift['is_default']) { ?><span class="label label-info">Default</span><?php } ?>
                                            <?php if (!$shift['is_active']) { ?><span class="label label-default">Inactive</span><?php } ?>
                                        </td>
                                        <td>
                                            <?php echo date('g:i A', strtotime($shift['start_time'])) . ' - ' . date('g:i A', strtotime($shift['end_time'])); ?><br>
                                            <span class="text-muted" style="font-size:11px;">Break <?php echo (int) $shift['break_minutes']; ?>m &middot; Grace <?php echo (int) $shift['grace_minutes']; ?>m</span>
                                        </td>
                                        <td>
                                            <?php
                                            $offs = array_filter(explode(',', $shift['week_off_days']), 'strlen');
                                            echo count($offs) ? html_escape(implode(', ', array_map(function ($d) use ($day_names) { return substr($day_names[(int) $d] ?? '?', 0, 3); }, $offs))) : '-';
                                            ?>
                                        </td>
                                        <td class="text-right">
                                            <?php if ($can_edit) { ?>
                                                <button type="button" class="btn btn-default btn-icon" onclick='openShiftModal(<?php echo json_encode($shift, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG); ?>)'><i class="fa fa-pencil"></i></button>
                                            <?php } ?>
                                            <?php if (has_permission('hr_shifts', '', 'delete') || is_admin()) { ?>
                                                <a href="<?php echo admin_url('hr/delete_shift/' . $shift['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($can_edit) { ?>
                    <div class="panel_s">
                        <div class="panel-body">
                            <h4 class="bold">Assign Shift</h4>
                            <hr class="hr-panel-heading" />
                            <?php echo form_open(admin_url('hr/assign_shift')); ?>
                            <div class="form-group">
                                <label>Employees</label>
                                <select name="staff_ids[]" class="selectpicker" data-width="100%" multiple data-live-search="true" data-actions-box="true" required>
                                    <?php foreach ($employees as $emp) { ?>
                                        <option value="<?php echo $emp['staffid']; ?>"><?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Shift</label>
                                        <select name="shift_id" class="selectpicker" data-width="100%" required>
                                            <?php foreach ($shifts as $shift) {
                                                if (!$shift['is_active']) { continue; } ?>
                                                <option value="<?php echo $shift['id']; ?>"><?php echo html_escape($shift['name']); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Effective From</label>
                                        <input type="date" name="effective_from" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Assign</button>
                            <?php echo form_close(); ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="bold">Current Roster</h4>
                        <hr class="hr-panel-heading" />
                        <table class="table table-striped dt-table">
                            <thead><tr><th>Employee</th><th>Current Shift</th><th>Since</th></tr></thead>
                            <tbody>
                                <?php foreach ($employees as $emp) {
                                    $assignment = $shift_map[$emp['staffid']] ?? null; ?>
                                    <tr>
                                        <td><a href="<?php echo admin_url('hr/employee/' . $emp['staffid']); ?>"><?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname']); ?></a>
                                            <span class="text-muted"><?php echo html_escape($emp['employee_code']); ?></span></td>
                                        <td>
                                            <?php if ($assignment) { ?>
                                                <span class="bold"><?php echo html_escape($assignment['name']); ?></span>
                                                <span class="text-muted"><?php echo date('g:ia', strtotime($assignment['start_time'])) . '-' . date('g:ia', strtotime($assignment['end_time'])); ?></span>
                                            <?php } else { ?>
                                                <span class="text-muted">Default shift</span>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo $assignment ? _d($assignment['effective_from']) : '-'; ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Shift modal -->
    <div class="modal fade" id="shift_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <?php echo form_open(admin_url('hr/save_shift'), ['id' => 'shift_form']); ?>
                <input type="hidden" name="id" id="shift_id">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title" id="shift_modal_title">New Shift</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label>Name</label>
                        <input type="text" name="name" id="shift_name" class="form-control" required></div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Start Time</label>
                            <input type="time" name="start_time" id="shift_start" class="form-control" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>End Time</label>
                            <input type="time" name="end_time" id="shift_end" class="form-control" required></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Break (minutes)</label>
                            <input type="number" name="break_minutes" id="shift_break" class="form-control" value="30" min="0"></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Late Grace (minutes)</label>
                            <input type="number" name="grace_minutes" id="shift_grace" class="form-control" value="10" min="0"></div></div>
                    </div>
                    <div class="form-group">
                        <label>Week Off Days</label><br>
                        <?php foreach ($day_names as $i => $day) { ?>
                            <label class="checkbox-inline"><input type="checkbox" name="week_off_days[]" value="<?php echo $i; ?>" class="shift-wo"> <?php echo substr($day, 0, 3); ?></label>
                        <?php } ?>
                    </div>
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="is_default" id="shift_default" value="1"><label for="shift_default">Default shift (applies when no shift is assigned)</label>
                    </div>
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="is_active" id="shift_active" value="1" checked><label for="shift_active">Active</label>
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
    function openShiftModal(shift) {
        $('#shift_form')[0].reset();
        $('#shift_id').val('');
        $('.shift-wo').prop('checked', false);
        $('#shift_active').prop('checked', true);
        $('#shift_modal_title').text('New Shift');
        if (shift) {
            $('#shift_modal_title').text('Edit Shift');
            $('#shift_id').val(shift.id);
            $('#shift_name').val(shift.name);
            $('#shift_start').val(shift.start_time.substring(0, 5));
            $('#shift_end').val(shift.end_time.substring(0, 5));
            $('#shift_break').val(shift.break_minutes);
            $('#shift_grace').val(shift.grace_minutes);
            $('#shift_default').prop('checked', shift.is_default == 1);
            $('#shift_active').prop('checked', shift.is_active == 1);
            String(shift.week_off_days || '').split(',').forEach(function (d) {
                if (d !== '') { $('.shift-wo[value="' + d + '"]').prop('checked', true); }
            });
        }
        $('#shift_modal').modal('show');
    }
</script>
