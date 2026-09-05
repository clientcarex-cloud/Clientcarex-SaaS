<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_edit = has_permission('hr_attendance', '', 'edit') || is_admin();
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="input-group">
                                    <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                    <input type="date" id="att_date" class="form-control" value="<?php echo $date; ?>">
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="input-group" style="max-width:260px;">
                                    <span class="input-group-addon"><i class="fa fa-filter"></i></span>
                                    <select id="att_status_filter" class="form-control">
                                        <option value="">All statuses</option>
                                        <option value="present">Present (incl. half day)</option>
                                        <option value="absent">Absent</option>
                                        <option value="half_day">Half Day</option>
                                        <option value="leave">On Leave</option>
                                        <option value="holiday">Holiday</option>
                                        <option value="week_off">Week Off</option>
                                    </select>
                                </div>
                                <?php if ($holiday) { ?>
                                    <span class="label label-info" style="font-size:13px;padding:8px 14px;display:inline-block;margin-top:6px;">
                                        <i class="fa fa-star"></i> <?php echo html_escape($holiday['name']); ?> (Holiday)
                                    </span>
                                <?php } ?>
                            </div>
                            <div class="col-md-4 text-right">
                                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default" data-toggle="tooltip" title="Back to HR Dashboard"><i class="fa fa-arrow-left"></i></a>
                                <a href="<?php echo admin_url('hr/attendance_register'); ?>" class="btn btn-default"><i class="fa fa-table"></i> Monthly Register</a>
                                <?php if (hr_field_enabled() && (is_admin() || has_permission('hr_field_attendance', '', 'view'))) { ?>
                                    <a href="<?php echo admin_url('hr/field_attendance?from=' . $date . '&to=' . $date); ?>" class="btn btn-default" data-toggle="tooltip" title="Field (remote) punches for this day"><i class="fa fa-location-dot"></i> Field</a>
                                <?php } ?>
                                <?php if (!empty($etime_enabled)) { ?>
                                    <a href="<?php echo admin_url('hr/punches'); ?>" class="btn btn-default" data-toggle="tooltip" title="Machine Punch Log"><i class="fa fa-list"></i></a>
                                    <?php if ($can_edit) { ?>
                                        <button type="button" class="btn btn-info" id="sync_machine"><i class="fa fa-fingerprint"></i> Sync from Machine</button>
                                    <?php } ?>
                                <?php } ?>
                                <?php if ($can_edit) { ?>
                                    <button type="button" class="btn btn-default" id="mark_all_present">Mark All Present</button>
                                    <?php if ($holiday) { ?>
                                        <button type="button" class="btn btn-default" id="mark_all_holiday">Mark All Holiday</button>
                                    <?php } ?>
                                    <button type="button" class="btn btn-primary" id="save_attendance"><i class="fa fa-check"></i> Save Attendance</button>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="clearfix"></div>
                        <hr class="hr-panel-heading" />

                        <div class="table-responsive">
                            <table class="table table-striped" id="attendance_table">
                                <thead>
                                    <tr>
                                        <th style="min-width:200px;">Employee</th>
                                        <th>Shift</th>
                                        <th style="min-width:340px;">Status</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th style="min-width:150px;">Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!count($employees)) { ?>
                                        <tr><td colspan="6" class="text-muted">No active employees. Use Employees &rarr; Sync from Staff first.</td></tr>
                                    <?php }
                                    foreach ($employees as $emp) {
                                        $att = $attendance[$emp['staffid']] ?? null;
                                        $shift = $shift_map[$emp['staffid']] ?? null;
                                        // role-wise weekly off: preselect WO for unmarked employees
                                        $is_wo_day = in_array(date('w', strtotime($date)), hr_week_off_days($emp['role'])); ?>
                                        <tr class="att-row" data-staff="<?php echo $emp['staffid']; ?>">
                                            <td>
                                                <div style="display:flex;align-items:center;">
                                                    <div style="margin-right:8px;"><?php echo staff_profile_image($emp['staffid'], ['staff-profile-image-small']); ?></div>
                                                    <div>
                                                        <a href="<?php echo admin_url('hr/employee/' . $emp['staffid']); ?>" class="bold"><?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname']); ?></a>
                                                        <div class="text-muted" style="font-size:11px;"><?php echo html_escape($emp['employee_code']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($shift) { ?>
                                                    <span style="font-size:12px;"><?php echo html_escape($shift['name']); ?><br>
                                                        <span class="text-muted"><?php echo date('g:ia', strtotime($shift['start_time'])) . '-' . date('g:ia', strtotime($shift['end_time'])); ?></span></span>
                                                <?php } else { ?>
                                                    <span class="text-muted">-</span>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" data-toggle="buttons">
                                                    <?php foreach ($statuses as $key => $st) {
                                                        $checked = $att ? ($att['status'] === $key) : ($is_wo_day && $key === 'week_off'); ?>
                                                        <label class="btn btn-default btn-xs <?php echo $checked ? 'active' : ''; ?>" style="<?php echo $checked ? 'background:' . $st['color'] . ';color:#fff;border-color:' . $st['color'] . ';' : ''; ?>" data-color="<?php echo $st['color']; ?>" data-toggle="tooltip" data-container="body" title="<?php echo html_escape($st['label']); ?>">
                                                            <input type="radio" name="status_<?php echo $emp['staffid']; ?>" value="<?php echo $key; ?>" <?php echo $checked ? 'checked' : ''; ?> <?php echo $can_edit ? '' : 'disabled'; ?>> <?php echo $st['short']; ?>
                                                        </label>
                                                    <?php } ?>
                                                </div>
                                                <?php if ($att && $att['is_late']) { ?>
                                                    <span class="label label-warning">Late</span>
                                                <?php } ?>
                                                <?php if ($att && ($att['source'] ?? '') === 'machine') { ?>
                                                    <span class="label label-default" data-toggle="tooltip" data-container="body" title="Imported from attendance machine"><i class="fa fa-fingerprint"></i></span>
                                                <?php } elseif ($att && ($att['source'] ?? '') === 'field') { ?>
                                                    <a href="<?php echo admin_url('hr/field_attendance?from=' . $date . '&to=' . $date . '&staff_id=' . (int) $emp['staffid']); ?>"
                                                       class="label label-default" data-toggle="tooltip" data-container="body" title="Built from a field (remote) punch"><i class="fa fa-location-dot"></i></a>
                                                <?php } ?>
                                            </td>
                                            <td><input type="time" class="form-control input-sm att-in" value="<?php echo $att ? $att['check_in'] : ''; ?>" <?php echo $can_edit ? '' : 'readonly'; ?>></td>
                                            <td><input type="time" class="form-control input-sm att-out" value="<?php echo $att ? $att['check_out'] : ''; ?>" <?php echo $can_edit ? '' : 'readonly'; ?>></td>
                                            <td><input type="text" class="form-control input-sm att-note" maxlength="255" value="<?php echo $att ? html_escape($att['note']) : ''; ?>" <?php echo $can_edit ? '' : 'readonly'; ?>></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if (!empty($etime_enabled) && $can_edit) { ?>
<div class="modal fade" id="sync_machine_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-fingerprint"></i> Sync from Attendance Machine</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted">Pull punches from e-timeoffice and rebuild attendance for the selected dates. This is idempotent — re-running it will not create duplicates. Manually-marked days are preserved.</p>
                <div class="row">
                    <div class="col-md-6"><div class="form-group"><label>From date</label>
                        <input type="date" id="sync_from" class="form-control" value="<?php echo $date; ?>"></div></div>
                    <div class="col-md-6"><div class="form-group"><label>To date</label>
                        <input type="date" id="sync_to" class="form-control" value="<?php echo $date; ?>"></div></div>
                </div>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" id="sync_incremental">
                    <label for="sync_incremental">Incremental pull only (fetch just new punches since the last sync, ignore dates above)</label>
                </div>
                <div id="sync_machine_msg" style="display:none;" class="alert" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" id="sync_machine_go"><i class="fa fa-download"></i> Sync Now</button>
            </div>
        </div>
    </div>
</div>
<?php } ?>
<?php init_tail(); ?>
<script>
    $(function () {
        $('#attendance_table [data-toggle="tooltip"]').tooltip();

        // ---- Sync from biometric machine ----
        $('#sync_machine').on('click', function () {
            $('#sync_machine_msg').hide();
            $('#sync_machine_modal').modal('show');
        });
        $('#sync_machine_go').on('click', function () {
            var btn = $(this).prop('disabled', true);
            var msg = $('#sync_machine_msg').removeClass('alert-success alert-danger')
                .addClass('alert-info').html('<i class="fa fa-spinner fa-spin"></i> Syncing…').show();
            $.post('<?php echo admin_url('hr/etime_sync'); ?>', {
                mode: $('#sync_incremental').is(':checked') ? 'incremental' : 'range',
                from: $('#sync_from').val(),
                to: $('#sync_to').val()
            }).done(function (r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                msg.removeClass('alert-info alert-success alert-danger')
                    .addClass(r.success ? 'alert-success' : 'alert-danger')
                    .text(r.message);
                if (r.success) {
                    setTimeout(function () { window.location.reload(); }, 1400);
                } else {
                    btn.prop('disabled', false);
                }
            }).fail(function () {
                msg.removeClass('alert-info alert-success').addClass('alert-danger').text('Sync request failed.');
                btn.prop('disabled', false);
            });
        });

        var attDate = '<?php echo $date; ?>';
        $('#att_date').on('change', function () {
            window.location.href = '<?php echo admin_url('hr/attendance'); ?>?date=' + this.value;
        });

        // ---- Status filter (also driven by dashboard card deep-links) ----
        var STATUS_SETS = {
            present: ['present', 'half_day'],
            absent: ['absent'],
            half_day: ['half_day'],
            leave: ['leave'],
            holiday: ['holiday'],
            week_off: ['week_off']
        };
        function applyAttFilter(val) {
            var set = STATUS_SETS[val];
            $('.att-row').each(function () {
                if (!set) { $(this).show(); return; }
                var cur = $(this).find('input[type=radio]:checked').val();
                $(this).toggle($.inArray(cur, set) !== -1);
            });
        }
        $('#att_status_filter').on('change', function () {
            var val = this.value;
            applyAttFilter(val);
            // keep the URL shareable/refresh-safe without reloading the grid
            var base = '<?php echo admin_url('hr/attendance'); ?>?date=' + attDate;
            history.replaceState(null, '', val ? base + '&status=' + val : base);
        });

        var initialStatus = '<?php echo html_escape($active_status); ?>';
        if (initialStatus) {
            $('#att_status_filter').val(initialStatus);
            applyAttFilter(initialStatus);
        }

        // colorize selected status buttons
        $(document).on('change', '#attendance_table input[type=radio]', function () {
            var group = $(this).closest('.btn-group');
            group.find('label').each(function () {
                $(this).css({background: '', color: '', 'border-color': ''});
            });
            var lbl = $(this).closest('label');
            lbl.css({background: lbl.data('color'), color: '#fff', 'border-color': lbl.data('color')});
        });

        $('#mark_all_present').on('click', function () {
            $('.att-row').each(function () {
                $(this).find('input[type=radio][value=present]').prop('checked', true).trigger('change').closest('label').addClass('active').siblings().removeClass('active');
            });
        });

        $('#mark_all_holiday').on('click', function () {
            $('.att-row').each(function () {
                $(this).find('input[type=radio][value=holiday]').prop('checked', true).trigger('change').closest('label').addClass('active').siblings().removeClass('active');
            });
        });

        $('#save_attendance').on('click', function () {
            var btn = $(this).prop('disabled', true);
            var rows = [];
            $('.att-row').each(function () {
                var staffId = $(this).data('staff');
                var status = $(this).find('input[type=radio]:checked').val();
                if (!status) { return; }
                rows.push({
                    staff_id: staffId,
                    status: status,
                    check_in: $(this).find('.att-in').val(),
                    check_out: $(this).find('.att-out').val(),
                    note: $(this).find('.att-note').val()
                });
            });
            if (!rows.length) {
                alert_float('warning', 'Nothing to save - select a status for at least one employee');
                btn.prop('disabled', false);
                return;
            }
            $.post('<?php echo admin_url('hr/save_attendance'); ?>', {
                date: '<?php echo $date; ?>',
                rows: rows
            }).done(function (response) {
                response = typeof response === 'string' ? JSON.parse(response) : response;
                alert_float(response.success ? 'success' : 'danger', response.message);
                setTimeout(function () { window.location.reload(); }, 800);
            }).fail(function () {
                alert_float('danger', 'Failed to save attendance');
                btn.prop('disabled', false);
            });
        });
    });
</script>
