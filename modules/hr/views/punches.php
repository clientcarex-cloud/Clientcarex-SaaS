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
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                            <h4 class="bold" style="margin:0;"><i class="fa fa-fingerprint text-info"></i> Machine Punch Log</h4>
                            <div>
                                <a href="<?php echo admin_url('hr/attendance'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Attendance</a>
                                <a href="<?php echo admin_url('hr/settings'); ?>#attendance-machine" class="btn btn-default"><i class="fa fa-cog"></i> Settings</a>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />

                        <?php if (!$etime_enabled) { ?>
                            <div class="alert alert-warning">
                                The biometric machine integration is turned off. Enable it in
                                <a href="<?php echo admin_url('hr/settings'); ?>#attendance-machine">HR Settings &rarr; Biometric Attendance Machine</a> to pull punches.
                            </div>
                        <?php } ?>

                        <?php if (!empty($last_sync)) { ?>
                            <p class="text-muted" style="font-size:12px;margin-top:-4px;">
                                <i class="fa fa-clock-o"></i> Last sync:
                                <strong><?php echo html_escape($last_sync['at'] ?? '-'); ?></strong>
                                (<?php echo html_escape($last_sync['mode'] ?? 'range'); ?>) —
                                <?php echo (int) ($last_sync['punches_added'] ?? 0); ?> punch(es) added,
                                <?php echo (int) ($last_sync['attendance_updated'] ?? 0); ?> day(s) updated<?php
                                if (!empty($last_sync['unmapped'])) {
                                    echo ', <span class="text-danger">' . (int) $last_sync['unmapped'] . ' unmapped</span>';
                                } ?>.
                            </p>
                        <?php } ?>

                        <?php if (!empty($unmapped_codes) && $can_edit) { ?>
                            <div class="alert alert-warning" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                                <span>
                                    <i class="fa fa-exclamation-triangle"></i>
                                    <strong><?php echo count($unmapped_codes); ?></strong> machine code(s) have punches that are not linked to any employee.
                                    Map them once and all their past &amp; future punches will follow that employee.
                                </span>
                                <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#map_codes_modal">
                                    <i class="fa fa-link"></i> Map to employees
                                </button>
                            </div>
                        <?php } ?>

                        <form method="get" action="<?php echo admin_url('hr/punches'); ?>" class="row" style="margin-bottom:6px;">
                            <div class="col-md-2"><div class="form-group"><label>From</label>
                                <input type="date" name="from" class="form-control" value="<?php echo html_escape($from); ?>"></div></div>
                            <div class="col-md-2"><div class="form-group"><label>To</label>
                                <input type="date" name="to" class="form-control" value="<?php echo html_escape($to); ?>"></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Employee</label>
                                <select name="staff_id" class="selectpicker" data-width="100%" data-live-search="true">
                                    <option value="">All employees</option>
                                    <?php foreach ($employees as $emp) { ?>
                                        <option value="<?php echo $emp['staffid']; ?>" <?php echo ($staff_id == $emp['staffid']) ? 'selected' : ''; ?>>
                                            <?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname'] . ' (' . $emp['employee_code'] . ')'); ?>
                                        </option>
                                    <?php } ?>
                                </select></div></div>
                            <div class="col-md-4" style="padding-top:26px;">
                                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
                                <?php if ($etime_enabled && $can_edit) { ?>
                                    <button type="button" class="btn btn-info" id="pl_sync"><i class="fa fa-download"></i> Sync range</button>
                                    <button type="button" class="btn btn-default" id="pl_incremental" data-toggle="tooltip" title="Fetch only new punches since the last sync"><i class="fa fa-bolt"></i> Pull new</button>
                                    <span id="pl_sync_msg" style="margin-left:6px;font-size:12px;"></span>
                                <?php } ?>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Punch Time</th>
                                        <th>Employee</th>
                                        <th>Machine Code</th>
                                        <th>Machine ID</th>
                                        <th>Mapping</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!count($punches)) { ?>
                                        <tr><td colspan="5" class="text-muted">No punches found for this range.</td></tr>
                                    <?php }
                                    foreach ($punches as $p) {
                                        $mapped = !empty($p['staff_id']); ?>
                                        <tr>
                                            <td><?php echo date('d M Y, g:i A', strtotime($p['punch_at'])); ?></td>
                                            <td>
                                                <?php if ($mapped) { ?>
                                                    <a href="<?php echo admin_url('hr/employee/' . (int) $p['staff_id']); ?>"><?php echo html_escape(trim($p['firstname'] . ' ' . $p['lastname'])); ?></a>
                                                <?php } else { ?>
                                                    <span class="text-muted"><?php echo $p['name'] ? html_escape($p['name']) : '&mdash;'; ?></span>
                                                <?php } ?>
                                            </td>
                                            <td><code><?php echo html_escape($p['empcode']); ?></code></td>
                                            <td><?php echo $p['mcid'] ? html_escape($p['mcid']) : '<span class="text-muted">-</span>'; ?></td>
                                            <td>
                                                <?php if ($mapped) { ?>
                                                    <span class="label label-success">Mapped</span>
                                                <?php } else { ?>
                                                    <span class="label label-danger" data-toggle="tooltip" title="No employee has this Machine/Employee Code">Unmapped</span>
                                                    <?php if ($can_edit && !empty($unmapped_codes)) { ?>
                                                        <button type="button" class="btn btn-default btn-xs mc-open-map" data-code="<?php echo html_escape($p['empcode']); ?>" style="margin-left:4px;">
                                                            <i class="fa fa-link"></i> Map
                                                        </button>
                                                    <?php } ?>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted" style="font-size:11px;">Showing up to 1000 most recent punches. Unmapped codes are stored — use <strong>Map to employees</strong> above for a one-time mapping; past punches attach immediately and attendance is rebuilt.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($unmapped_codes) && $can_edit) { ?>
<div class="modal fade" id="map_codes_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-link"></i> Map machine codes to employees</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="font-size:12px;">
                    A one-time mapping: the machine code is saved on the employee's profile, every stored punch with that
                    code is attached to them, and attendance for those days is rebuilt automatically. Future syncs resolve
                    the code on their own. Leave a row on <em>&mdash; Skip &mdash;</em> to decide later.
                </p>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Machine Code</th>
                                <th>Machine Name</th>
                                <th class="text-center">Punches</th>
                                <th>Last Punch</th>
                                <th style="min-width:240px;">Map to Employee</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unmapped_codes as $uc) { ?>
                                <tr data-map-row="<?php echo html_escape($uc['empcode']); ?>">
                                    <td><code><?php echo html_escape($uc['empcode']); ?></code></td>
                                    <td><?php echo $uc['name'] ? html_escape($uc['name']) : '<span class="text-muted">-</span>'; ?></td>
                                    <td class="text-center"><?php echo (int) $uc['punches']; ?></td>
                                    <td><?php echo date('d M Y, g:i A', strtotime($uc['last_at'])); ?></td>
                                    <td>
                                        <select class="selectpicker mc-map-select" data-width="100%" data-live-search="true" data-code="<?php echo html_escape($uc['empcode']); ?>">
                                            <option value="">&mdash; Skip &mdash;</option>
                                            <?php foreach ($employees as $emp) { ?>
                                                <option value="<?php echo $emp['staffid']; ?>">
                                                    <?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname'] . ' (' . $emp['employee_code'] . ')'); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <span id="mc_map_msg" style="margin-right:8px;font-size:12px;"></span>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="mc_map_save"><i class="fa fa-check"></i> Save mapping &amp; rebuild attendance</button>
            </div>
        </div>
    </div>
</div>
<?php } ?>
<?php init_tail(); ?>
<script>
    $(function () {
        $('[data-toggle="tooltip"]').tooltip();

        function runSync(mode) {
            var msg = $('#pl_sync_msg').css('color', '#64748b').html('<i class="fa fa-spinner fa-spin"></i> Syncing…');
            $('#pl_sync, #pl_incremental').prop('disabled', true);
            $.post('<?php echo admin_url('hr/etime_sync'); ?>', {
                mode: mode,
                from: $('input[name="from"]').val(),
                to: $('input[name="to"]').val()
            }).done(function (r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                msg.css('color', r.success ? '#16a34a' : '#dc2626').text(r.message);
                if (r.success) { setTimeout(function () { window.location.reload(); }, 1400); }
                else { $('#pl_sync, #pl_incremental').prop('disabled', false); }
            }).fail(function () {
                msg.css('color', '#dc2626').text('Sync request failed.');
                $('#pl_sync, #pl_incremental').prop('disabled', false);
            });
        }
        $('#pl_sync').on('click', function () { runSync('range'); });
        $('#pl_incremental').on('click', function () { runSync('incremental'); });

        // Row-level "Map" shortcut: open the modal and jump to that code's row.
        $('.mc-open-map').on('click', function () {
            var code = $(this).attr('data-code');
            $('#map_codes_modal').modal('show');
            setTimeout(function () {
                var row = $('#map_codes_modal tr[data-map-row="' + code.replace(/"/g, '\\"') + '"]');
                if (row.length) {
                    row.css('background', '#fef3c7');
                    row[0].scrollIntoView({ block: 'center' });
                }
            }, 350);
        });

        // One-time mapping: post {code: staff_id} pairs, then reload.
        $('#mc_map_save').on('click', function () {
            var map = {}, any = false;
            $('.mc-map-select').each(function () {
                var v = $(this).val();
                if (v) { map[$(this).attr('data-code')] = v; any = true; }
            });
            var msg = $('#mc_map_msg');
            if (!any) {
                msg.css('color', '#dc2626').text('Pick an employee for at least one machine code.');
                return;
            }
            msg.css('color', '#64748b').html('<i class="fa fa-spinner fa-spin"></i> Mapping & rebuilding attendance…');
            $('#mc_map_save').prop('disabled', true);
            $.post('<?php echo admin_url('hr/map_punch_codes'); ?>', { map_json: JSON.stringify(map) }).done(function (r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                msg.css('color', r.success ? '#16a34a' : '#dc2626').text(r.message);
                if (r.success) { setTimeout(function () { window.location.reload(); }, 1500); }
                else { $('#mc_map_save').prop('disabled', false); }
            }).fail(function () {
                msg.css('color', '#dc2626').text('Mapping request failed.');
                $('#mc_map_save').prop('disabled', false);
            });
        });
    });
</script>
