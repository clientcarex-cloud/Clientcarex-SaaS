<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Field attendance log (manager view): remote punches with location, photo and
 * approval state. Row actions post through the hidden forms at the bottom so
 * the bulk-selection form is never nested.
 */
$can_edit   = has_permission('hr_field_attendance', '', 'edit') || is_admin();
$can_delete = has_permission('hr_field_attendance', '', 'delete') || is_admin();
$current_url = admin_url('hr/field_attendance') . '?' . http_build_query([
    'from' => $from, 'to' => $to, 'staff_id' => $staff_id, 'status' => $status, 'punch_type' => $punch_type,
]);
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <style>
            .fa-stat{background:#fff;border:1px solid #eef2f7;border-radius:10px;padding:14px;text-align:center}
            .fa-stat b{display:block;font-size:22px;font-weight:800;line-height:1.2}
            .fa-stat span{font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:#64748b}
            .fp-pill{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;color:#fff}
            .fp-thumb{width:44px;height:44px;border-radius:8px;object-fit:cover;border:1px solid #e2e8f0;cursor:zoom-in}
            .fp-nophoto{width:44px;height:44px;border-radius:8px;background:#f1f5f9;display:inline-flex;align-items:center;justify-content:center;color:#94a3b8}
        </style>

        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                            <h4 class="bold" style="margin:0;"><i class="fa fa-location-dot text-info"></i> <?php echo _l('hr_field_attendance'); ?></h4>
                            <div>
                                <a href="<?php echo admin_url('hr/field_sites'); ?>" class="btn btn-default"><i class="fa fa-map-pin"></i> Work Locations</a>
                                <a href="<?php echo admin_url('hr/attendance'); ?>" class="btn btn-default"><i class="fa fa-calendar-check-o"></i> Attendance</a>
                                <a href="<?php echo admin_url('hr/settings'); ?>#field-attendance" class="btn btn-default"><i class="fa fa-cog"></i> Settings</a>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />

                        <?php if (!$cfg['enabled']) { ?>
                            <div class="alert alert-warning">
                                Field attendance is turned off — employees cannot punch remotely. Enable it in
                                <a href="<?php echo admin_url('hr/settings'); ?>#field-attendance">HR Settings &rarr; Field Attendance</a>.
                            </div>
                        <?php } ?>

                        <div class="row" style="margin-bottom:14px;">
                            <?php
                            $cards = [
                                ['label' => 'Punches',        'value' => $stats['total'],     'color' => '#0f172a'],
                                ['label' => 'Employees',      'value' => $stats['employees'], 'color' => '#0891b2'],
                                ['label' => 'Pending',        'value' => $stats['pending'],   'color' => '#d97706'],
                                ['label' => 'Approved',       'value' => $stats['approved'],  'color' => '#16a34a'],
                                ['label' => 'Rejected',       'value' => $stats['rejected'],  'color' => '#dc2626'],
                                ['label' => 'Out of range',   'value' => $stats['outside'],   'color' => '#7c3aed'],
                            ];
                            foreach ($cards as $c) { ?>
                                <div class="col-md-2 col-xs-6" style="margin-bottom:8px;">
                                    <div class="fa-stat">
                                        <b style="color:<?php echo $c['color']; ?>;"><?php echo (int) $c['value']; ?></b>
                                        <span><?php echo $c['label']; ?></span>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>

                        <form method="get" action="<?php echo admin_url('hr/field_attendance'); ?>" class="row" style="margin-bottom:6px;">
                            <div class="col-md-2"><div class="form-group"><label>From</label>
                                <input type="date" name="from" class="form-control" value="<?php echo html_escape($from); ?>"></div></div>
                            <div class="col-md-2"><div class="form-group"><label>To</label>
                                <input type="date" name="to" class="form-control" value="<?php echo html_escape($to); ?>"></div></div>
                            <div class="col-md-3"><div class="form-group"><label>Employee</label>
                                <select name="staff_id" class="selectpicker" data-width="100%" data-live-search="true">
                                    <option value="">All employees</option>
                                    <?php foreach ($employees as $emp) { ?>
                                        <option value="<?php echo $emp['staffid']; ?>" <?php echo ($staff_id == $emp['staffid']) ? 'selected' : ''; ?>>
                                            <?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname'] . ' (' . $emp['employee_code'] . ')'); ?>
                                        </option>
                                    <?php } ?>
                                </select></div></div>
                            <div class="col-md-2"><div class="form-group"><label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All</option>
                                    <?php foreach ($statuses as $k => $st) { ?>
                                        <option value="<?php echo $k; ?>" <?php echo $status === $k ? 'selected' : ''; ?>><?php echo html_escape($st['label']); ?></option>
                                    <?php } ?>
                                </select></div></div>
                            <div class="col-md-2"><div class="form-group"><label>Type</label>
                                <select name="punch_type" class="form-control">
                                    <option value="">All</option>
                                    <?php foreach ($types as $k => $t) { ?>
                                        <option value="<?php echo $k; ?>" <?php echo $punch_type === $k ? 'selected' : ''; ?>><?php echo html_escape($t['label']); ?></option>
                                    <?php } ?>
                                </select></div></div>
                            <div class="col-md-1" style="padding-top:26px;">
                                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i></button>
                            </div>
                        </form>

                        <?php echo form_open(admin_url('hr/field_punch_bulk'), ['id' => 'fp_bulk_form']); ?>
                        <input type="hidden" name="redirect_to" value="<?php echo html_escape($current_url); ?>">
                        <input type="hidden" name="bulk_status" id="fp_bulk_status" value="">

                        <?php if ($can_edit && count($punches)) { ?>
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
                                <span class="text-muted" style="font-size:12px;"><span id="fp_sel_count">0</span> selected</span>
                                <button type="button" class="btn btn-success btn-sm fp-bulk" data-status="approved"><i class="fa fa-check"></i> Approve selected</button>
                                <button type="button" class="btn btn-danger btn-sm fp-bulk" data-status="rejected"><i class="fa fa-times"></i> Reject selected</button>
                            </div>
                        <?php } ?>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <?php if ($can_edit) { ?><th style="width:28px;"><input type="checkbox" id="fp_check_all"></th><?php } ?>
                                        <th>Photo</th>
                                        <th>Employee</th>
                                        <th>Punch</th>
                                        <th>Location</th>
                                        <th>Purpose / Note</th>
                                        <th>Status</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!count($punches)) { ?>
                                        <tr><td colspan="<?php echo $can_edit ? 8 : 7; ?>" class="text-muted">No field punches for these filters.</td></tr>
                                    <?php }
                                    foreach ($punches as $p) {
                                        $t     = $types[$p['punch_type']] ?? $types['in'];
                                        $st    = $statuses[$p['status']] ?? $statuses['pending'];
                                        $photo = admin_url('hr/field_punch_photo/' . (int) $p['id']); ?>
                                        <tr>
                                            <?php if ($can_edit) { ?>
                                                <td><input type="checkbox" class="fp-check" name="punch_ids[]" value="<?php echo (int) $p['id']; ?>"></td>
                                            <?php } ?>
                                            <td>
                                                <?php if (!empty($p['photo'])) { ?>
                                                    <img class="fp-thumb fp-zoom" src="<?php echo $photo; ?>"
                                                         data-full="<?php echo $photo; ?>"
                                                         data-title="<?php echo html_escape(trim($p['firstname'] . ' ' . $p['lastname']) . ' — ' . $t['label'] . ' · ' . _dt($p['punch_at'])); ?>"
                                                         alt="Punch photo">
                                                    <br><span class="text-muted" style="font-size:10px;" data-toggle="tooltip" title="Captured live by the camera at punch time"><i class="fa fa-camera"></i> live</span>
                                                <?php } else { ?>
                                                    <span class="fp-nophoto"><i class="fa fa-user"></i></span>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo admin_url('hr/employee/' . (int) $p['staff_id']); ?>">
                                                    <?php echo html_escape(trim($p['firstname'] . ' ' . $p['lastname'])); ?>
                                                </a>
                                                <?php if (!empty($p['employee_code'])) { ?>
                                                    <br><span class="text-muted" style="font-size:11px;"><?php echo html_escape($p['employee_code']); ?></span>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <span class="fp-pill" style="background:<?php echo $t['color']; ?>;"><?php echo strtoupper($p['punch_type']); ?></span><br>
                                                <span style="font-size:12px;"><?php echo date('d M Y', strtotime($p['punch_at'])); ?></span><br>
                                                <strong><?php echo date('g:i A', strtotime($p['punch_at'])); ?></strong>
                                            </td>
                                            <td style="font-size:12px;">
                                                <?php if ($p['latitude'] !== null && $p['longitude'] !== null) { ?>
                                                    <a href="<?php echo hr_field_map_url($p['latitude'], $p['longitude']); ?>" target="_blank" rel="noopener">
                                                        <i class="fa fa-map-marker"></i> <?php echo html_escape(number_format((float) $p['latitude'], 5) . ', ' . number_format((float) $p['longitude'], 5)); ?>
                                                    </a>
                                                    <?php if ($p['accuracy_m']) { ?>
                                                        <br><span class="text-muted">±<?php echo (int) $p['accuracy_m']; ?> m</span>
                                                    <?php } ?>
                                                    <?php if (!empty($p['site_name'])) { ?>
                                                        <br><?php echo (int) $p['distance_m']; ?> m from <?php echo html_escape($p['site_name']); ?>
                                                        <?php if ((int) $p['within_geofence'] === 1) { ?>
                                                            <span class="fp-pill" style="background:#16a34a;">In range</span>
                                                        <?php } else { ?>
                                                            <span class="fp-pill" style="background:#d97706;">Out of range</span>
                                                        <?php } ?>
                                                    <?php } ?>
                                                    <?php if (!empty($p['address'])) { ?>
                                                        <br><span class="text-muted"><?php echo html_escape($p['address']); ?></span>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <span class="text-muted">No location captured</span>
                                                <?php } ?>
                                            </td>
                                            <td style="font-size:12px;">
                                                <?php echo html_escape($purposes[$p['purpose']] ?? '—'); ?>
                                                <?php if (!empty($p['note'])) { ?>
                                                    <br><span class="text-muted"><?php echo html_escape($p['note']); ?></span>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <span class="fp-pill" style="background:<?php echo $st['color']; ?>;"><?php echo html_escape($st['label']); ?></span>
                                                <?php if (!empty($p['reviewed_by'])) { ?>
                                                    <br><span class="text-muted" style="font-size:11px;">by <?php echo html_escape(get_staff_full_name($p['reviewed_by'])); ?></span>
                                                <?php } ?>
                                                <?php if (!empty($p['review_note'])) { ?>
                                                    <br><span class="text-muted" style="font-size:11px;"><?php echo html_escape($p['review_note']); ?></span>
                                                <?php } ?>
                                            </td>
                                            <td class="text-right" style="white-space:nowrap;">
                                                <?php if ($can_edit && $p['status'] !== 'approved') { ?>
                                                    <button type="button" class="btn btn-success btn-xs fp-act" data-id="<?php echo (int) $p['id']; ?>" data-action="approved" title="Approve"><i class="fa fa-check"></i></button>
                                                <?php } ?>
                                                <?php if ($can_edit && $p['status'] !== 'rejected') { ?>
                                                    <button type="button" class="btn btn-warning btn-xs fp-act" data-id="<?php echo (int) $p['id']; ?>" data-action="rejected" title="Reject"><i class="fa fa-times"></i></button>
                                                <?php } ?>
                                                <?php if ($can_delete) { ?>
                                                    <button type="button" class="btn btn-danger btn-xs fp-del" data-id="<?php echo (int) $p['id']; ?>" title="Delete"><i class="fa fa-trash"></i></button>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php echo form_close(); ?>

                        <p class="text-muted" style="font-size:11px;">
                            Showing up to 500 punches. Approved punches set the day's check-in / check-out on
                            <a href="<?php echo admin_url('hr/attendance'); ?>">Attendance</a>
                            <?php echo $cfg['overwrite_manual'] ? '' : '(hand-marked and biometric days are never overwritten)'; ?>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row actions live outside the bulk form so no form is nested. -->
<?php echo form_open(admin_url('hr/field_punch_action/0/approved'), ['id' => 'fp_act_form']); ?>
    <input type="hidden" name="redirect_to" value="<?php echo html_escape($current_url); ?>">
    <input type="hidden" name="review_note" id="fp_act_note" value="">
<?php echo form_close(); ?>
<?php echo form_open(admin_url('hr/delete_field_punch/0'), ['id' => 'fp_del_form']); ?>
    <input type="hidden" name="redirect_to" value="<?php echo html_escape($current_url); ?>">
<?php echo form_close(); ?>

<div class="modal fade" id="fp_photo_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title" id="fp_photo_title">Punch photo</h4>
            </div>
            <div class="modal-body text-center">
                <img id="fp_photo_full" src="" alt="Punch photo" style="max-width:100%;border-radius:8px;">
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function () {
        var actUrl = '<?php echo admin_url('hr/field_punch_action'); ?>';
        var delUrl = '<?php echo admin_url('hr/delete_field_punch'); ?>';

        function selCount() {
            $('#fp_sel_count').text($('.fp-check:checked').length);
        }
        $('#fp_check_all').on('change', function () {
            $('.fp-check').prop('checked', $(this).prop('checked'));
            selCount();
        });
        $('.fp-check').on('change', selCount);

        $('.fp-bulk').on('click', function () {
            if (!$('.fp-check:checked').length) {
                alert_float('warning', 'Select at least one punch first.');
                return;
            }
            $('#fp_bulk_status').val($(this).attr('data-status'));
            $('#fp_bulk_form').submit();
        });

        // Approve / reject one punch (a rejection asks for a short reason).
        $('.fp-act').on('click', function () {
            var id = $(this).attr('data-id'), action = $(this).attr('data-action');
            if (action === 'rejected') {
                var note = prompt('Reason for rejecting this punch (optional):', '');
                if (note === null) { return; }
                $('#fp_act_note').val(note);
            } else {
                $('#fp_act_note').val('');
            }
            $('#fp_act_form').attr('action', actUrl + '/' + id + '/' + action).submit();
        });

        $('.fp-del').on('click', function () {
            if (!confirm('Delete this punch and its photo? The day\'s attendance will be recalculated.')) { return; }
            $('#fp_del_form').attr('action', delUrl + '/' + $(this).attr('data-id')).submit();
        });

        $('.fp-zoom').on('click', function () {
            $('#fp_photo_full').attr('src', $(this).attr('data-full'));
            $('#fp_photo_title').text($(this).attr('data-title'));
            $('#fp_photo_modal').modal('show');
        });
    });
</script>
