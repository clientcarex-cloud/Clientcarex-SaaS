<?php defined('BASEPATH') or exit('No direct script access allowed');

$status_labels = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'default'];
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <style>
            .hr-card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.05);border:1px solid rgba(0,0,0,.03);padding:20px;margin-bottom:20px}
            .bal-tile{border:1px solid #eef2f7;border-radius:10px;padding:12px 14px;margin-bottom:10px}
            .bal-tile .big{font-size:22px;font-weight:800;color:#1e293b}
        </style>

        <div class="row">
            <div class="col-md-4">
                <div class="hr-card">
                    <h4 style="margin:0 0 12px;font-weight:800;"><i class="fa fa-plane" style="color:#7c3aed;"></i> Leave Balance</h4>
                    <?php if (!count($balances)) { echo '<p class="text-muted">No leave allocated yet.</p>'; }
                    foreach ($balances as $b) { ?>
                        <div class="bal-tile">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span><i class="fa <?php echo isset($b['icon']) ? $b['icon'] : 'fa-calendar-o'; ?>" style="color:<?php echo html_escape($b['color'] ?: '#7c3aed'); ?>;"></i> <span style="font-weight:600;"><?php echo html_escape($b['name']); ?></span></span>
                                <span class="big"><?php echo (float) $b['remaining']; ?></span>
                            </div>
                            <div class="text-muted" style="font-size:12px;margin-top:4px;">
                                <?php echo (float) $b['used']; ?> used / <?php echo (float) $b['allocated']; ?> allotted<?php if (!empty($b['carried']) && $b['carried'] > 0) { ?> · <span style="color:#0891b2;">+<?php echo (float) $b['carried']; ?> carried</span><?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="col-md-8">
                <div class="hr-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <h4 style="margin:0;font-weight:800;"><i class="fa fa-list-ul"></i> My Leave Requests</h4>
                        <?php if ($can_apply) { ?>
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#apply_leave_modal"><i class="fa fa-plus"></i> Apply Leave</button>
                        <?php } ?>
                    </div>
                    <hr class="hr-panel-heading" />
                    <table class="table table-striped">
                        <thead><tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Applied</th><th>Proof</th><th>Status</th><th>Remark</th></tr></thead>
                        <tbody>
                            <?php if (!count($requests)) { ?>
                                <tr><td colspan="8" class="text-muted">You have not applied for any leave yet.</td></tr>
                            <?php }
                            foreach ($requests as $r) { ?>
                                <tr>
                                    <td><span class="label" style="background:<?php echo html_escape($r['type_color']); ?>;color:#fff;"><?php echo html_escape($r['type_code']); ?></span> <?php echo html_escape($r['type_name']); ?></td>
                                    <td><?php echo _d($r['from_date']); ?></td>
                                    <td><?php echo _d($r['to_date']); ?></td>
                                    <td><?php echo (float) $r['days']; ?><?php echo $r['is_half_day'] ? ' (½)' : ''; ?></td>
                                    <td><?php echo !empty($r['created_at']) ? '<span data-toggle="tooltip" title="' . _dt($r['created_at']) . '">' . time_ago($r['created_at']) . '</span>' : '<span class="text-muted">-</span>'; ?></td>
                                    <td><?php if (!empty($r['proof_file'])) { ?><a href="<?php echo admin_url('hr/myhr/leave_proof/' . $r['id']); ?>" target="_blank" data-toggle="tooltip" title="View your uploaded proof"><i class="fa fa-paperclip"></i></a><?php } else { echo '<span class="text-muted">—</span>'; } ?></td>
                                    <td><span class="label label-<?php echo $status_labels[$r['status']] ?? 'default'; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                                    <td><?php echo html_escape($r['action_note'] ?: '—'); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if ($can_apply) { ?>
    <div class="modal fade" id="apply_leave_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <?php echo form_open_multipart(admin_url('hr/myhr/apply_leave')); ?>
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Apply Leave</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Leave Type</label>
                        <select name="leave_type_id" id="ess_leave_type" class="form-control" required>
                            <?php foreach ($leave_types as $t) { ?>
                                <option value="<?php echo $t['id']; ?>" data-emergency="<?php echo !empty($t['is_emergency']) ? 1 : 0; ?>"><?php echo html_escape($t['name']); ?> (<?php echo html_escape($t['code']); ?>)<?php echo !empty($t['is_emergency']) ? ' — emergency' : ''; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="is_half_day" id="ess_half_day" value="1"><label for="ess_half_day">Half day</label>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group"><label>From</label>
                            <input type="date" name="from_date" id="ess_from" class="form-control" required></div>
                        <div class="col-md-6 form-group" id="ess_to_wrap"><label>To</label>
                            <input type="date" name="to_date" id="ess_to" class="form-control"></div>
                    </div>
                    <p class="text-muted" id="ess_rule_hint" style="font-size:12px;margin-top:-6px;"></p>
                    <div class="form-group"><label>Reason</label>
                        <textarea name="reason" class="form-control" rows="3"></textarea></div>
                    <div class="form-group">
                        <label>Proof of reason</label>
                        <input type="file" name="proof" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.doc,.docx">
                        <small class="text-muted">Attach a supporting document — medical certificate, appointment letter, etc. (PDF, image or Word.)</small>
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
    <?php } ?>
</div>
<?php init_tail(); ?>
<script>
    $(function () {
        var minNotice = <?php echo (int) hr_leave_min_notice_days(); ?>;
        var allowBack = <?php echo get_option('hr_leave_allow_backdated') === '1' ? 'true' : 'false'; ?>;

        function ymd(d) {
            return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
        }
        function addDays(n) {
            var d = new Date();
            d.setHours(0, 0, 0, 0);
            d.setDate(d.getDate() + n);
            return d;
        }

        // Block pasting (and drag-drop) into the date fields.
        $('#ess_from, #ess_to').on('paste drop', function (e) { e.preventDefault(); return false; });

        // Apply the min-date rule based on the selected leave type.
        function applyLeaveRule() {
            var emergency = $('#ess_leave_type option:selected').data('emergency') == 1;
            var minFrom;
            if (emergency) {
                minFrom = allowBack ? '' : ymd(addDays(0));      // today
            } else {
                minFrom = ymd(addDays(minNotice));               // today + notice
            }
            $('#ess_from').attr('min', minFrom);
            $('#ess_to').attr('min', minFrom);
            // keep From/To consistent with the new minimum
            ['#ess_from', '#ess_to'].forEach(function (sel) {
                if (minFrom && $(sel).val() && $(sel).val() < minFrom) { $(sel).val(''); }
            });

            var hint = '';
            if (emergency) {
                hint = '⚡ Emergency leave — can be applied for today.';
            } else if (minNotice >= 1) {
                hint = 'Same-day leave is not allowed for this type. Earliest start date: ' + minFrom + '. For an emergency, pick an emergency leave type.';
            } else {
                hint = 'Leave can be applied from today.';
            }
            $('#ess_rule_hint').text(hint);
        }

        $('#ess_leave_type').on('change', applyLeaveRule);
        applyLeaveRule();

        $('#ess_half_day').on('change', function () {
            var half = $(this).is(':checked');
            $('#ess_to_wrap').toggle(!half);
            $('#ess_to').prop('required', !half);
        });
    });
</script>
