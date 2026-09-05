<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_create = has_permission('hr_memos', '', 'create') || is_admin();
$can_edit   = has_permission('hr_memos', '', 'edit') || is_admin();
$can_delete = has_permission('hr_memos', '', 'delete') || is_admin();

$types      = hr_memo_types();
$severities = hr_memo_severities();
$statuses   = hr_memo_statuses();
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                            <h4 class="bold" style="margin:0;"><i class="fa fa-file-signature text-danger"></i> <?php echo _l('hr_memos'); ?></h4>
                            <div>
                                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> <?php echo _l('back'); ?></a>
                                <?php if ($can_create) { ?>
                                    <button type="button" class="btn btn-danger" onclick="openMemoModal()"><i class="fa fa-plus"></i> Issue Memo</button>
                                <?php } ?>
                            </div>
                        </div>
                        <p class="text-muted" style="margin:6px 0 0;">Record disciplinary actions, warnings and other memos. The employee is notified and files a signed acknowledgement receipt.</p>

                        <div style="display:flex;gap:8px;margin:12px 0;flex-wrap:wrap;">
                            <?php
                            $chip = function ($label, $count, $key, $active) {
                                $url = admin_url('hr/memos' . ($key ? '?status=' . $key : ''));
                                echo '<a href="' . $url . '" class="label ' . ($active ? 'label-primary' : 'label-default') . '" style="padding:6px 12px;font-size:13px;">' . html_escape($label) . ' <b>' . (int) $count . '</b></a>';
                            };
                            $total = array_sum($counts);
                            $chip('All', $total, '', $active_status === '' || $active_status === null);
                            foreach ($statuses as $sk => $sm) {
                                $chip($sm['label'], $counts[$sk] ?? 0, $sk, $active_status === $sk);
                            }
                            ?>
                        </div>

                        <hr class="hr-panel-heading" />
                        <table class="table table-striped dt-table">
                            <thead><tr><th>Employee</th><th>Type</th><th>Subject</th><th>Severity</th><th>Incident</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($memos as $m) {
                                    $t = $types[$m['memo_type']] ?? $types['other'];
                                    $sev = $severities[$m['severity']] ?? $severities['medium'];
                                    $st = $statuses[$m['status']] ?? $statuses['issued'];
                                ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo admin_url('hr/employee/' . $m['staff_id']); ?>" class="bold"><?php echo html_escape(trim($m['firstname'] . ' ' . $m['lastname'])); ?></a>
                                            <?php if ($m['employee_code']) { ?><div class="text-muted" style="font-size:11px;"><?php echo html_escape($m['employee_code']); ?></div><?php } ?>
                                        </td>
                                        <td><span style="color:<?php echo $t['color']; ?>;"><i class="fa <?php echo $t['icon']; ?>"></i> <?php echo html_escape($t['label']); ?></span></td>
                                        <td>
                                            <a href="<?php echo admin_url('hr/memo/' . $m['id']); ?>"><?php echo html_escape($m['subject']); ?></a>
                                        </td>
                                        <td><span class="label label-<?php echo $sev['class']; ?>"><?php echo html_escape($sev['label']); ?></span></td>
                                        <td style="font-size:12px;"><?php echo $m['incident_date'] ? _d($m['incident_date']) : '<span class="text-muted">-</span>'; ?></td>
                                        <td><span class="label label-<?php echo $st['class']; ?>"><?php echo html_escape($st['label']); ?></span></td>
                                        <td class="text-right">
                                            <a href="<?php echo admin_url('hr/memo/' . $m['id']); ?>" class="btn btn-default btn-icon" title="View"><i class="fa fa-eye"></i></a>
                                            <?php if ($can_delete) { ?>
                                                <a href="<?php echo admin_url('hr/delete_memo/' . $m['id']); ?>" class="btn btn-danger btn-icon _delete" title="Delete"><i class="fa fa-remove"></i></a>
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
    <div class="modal fade" id="memo_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <?php echo form_open_multipart(admin_url('hr/save_memo'), ['id' => 'memo_form']); ?>
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title"><i class="fa fa-file-signature text-danger"></i> Issue Memo</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label>Employee <small class="req text-danger">*</small></label>
                        <select name="staff_id" id="memo_staff" class="selectpicker" data-width="100%" data-live-search="true" required>
                            <option value=""></option>
                            <?php foreach ($employees as $e) { ?>
                                <option value="<?php echo (int) $e['staffid']; ?>"><?php echo html_escape(trim($e['firstname'] . ' ' . $e['lastname']) . ($e['employee_code'] ? ' (' . $e['employee_code'] . ')' : '')); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Type <small class="req text-danger">*</small></label>
                            <select name="memo_type" id="memo_type" class="form-control">
                                <?php foreach ($types as $tk => $tm) { ?>
                                    <option value="<?php echo $tk; ?>"><?php echo html_escape($tm['label']); ?></option>
                                <?php } ?>
                            </select></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Severity</label>
                            <select name="severity" class="form-control">
                                <?php foreach ($severities as $sk => $sm) { ?>
                                    <option value="<?php echo $sk; ?>" <?php echo $sk === 'medium' ? 'selected' : ''; ?>><?php echo html_escape($sm['label']); ?></option>
                                <?php } ?>
                            </select></div></div>
                    </div>
                    <div class="form-group"><label>Subject <small class="req text-danger">*</small></label>
                        <input type="text" name="subject" class="form-control" maxlength="191" required></div>
                    <div class="form-group"><label>Description / Details</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Describe the incident, dates, witnesses..."></textarea></div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Incident Date</label>
                            <input type="date" name="incident_date" class="form-control"></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Penalty / Fine Amount</label>
                            <input type="number" step="0.01" min="0" name="penalty_amount" class="form-control" value="0"></div></div>
                    </div>
                    <div class="form-group"><label>Action Taken</label>
                        <textarea name="action_taken" class="form-control" rows="2" placeholder="e.g. Verbal warning, salary deduction, suspension..."></textarea></div>
                    <div class="row" id="memo_suspension_wrap" style="display:none;">
                        <div class="col-md-6"><div class="form-group"><label>Suspension From</label>
                            <input type="date" name="suspension_from" class="form-control"></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Suspension To</label>
                            <input type="date" name="suspension_to" class="form-control"></div></div>
                    </div>
                    <div class="form-group"><label>Attachment (evidence / signed letter)</label>
                        <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                        <small class="text-muted">PDF, image or Word. Optional.</small></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-danger">Issue &amp; Notify Employee</button>
                </div>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
    <?php } ?>
</div>
<?php init_tail(); ?>
<script>
    function openMemoModal() {
        var f = $('#memo_form')[0];
        f.reset();
        $('#memo_staff').selectpicker('val', '');
        memoToggleSuspension();
        $('#memo_modal').modal('show');
    }
    function memoToggleSuspension() {
        $('#memo_suspension_wrap').toggle($('#memo_type').val() === 'suspension');
    }
    $(function () {
        $('#memo_type').on('change', memoToggleSuspension);
    });
</script>
