<?php defined('BASEPATH') or exit('No direct script access allowed');

$types      = hr_memo_types();
$severities = hr_memo_severities();
$statuses   = hr_memo_statuses();
$pending    = array_values(array_filter($memos, function ($m) { return $m['status'] === 'issued'; }));
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 style="margin-top:6px;font-weight:800;"><i class="fa fa-file-signature"></i> My Memos</h4>
                        <p class="text-muted">Memos issued to you. Please review each one and file your acknowledgement receipt.</p>
                        <?php if (count($pending)) { ?>
                            <div class="alert alert-warning"><i class="fa fa-clock"></i> You have <strong><?php echo count($pending); ?></strong> memo(s) awaiting your acknowledgement.</div>
                        <?php } ?>
                        <hr class="hr-panel-heading" />

                        <?php if (!count($memos)) { ?>
                            <p class="text-muted">You have no memos. 🎉</p>
                        <?php }
                        foreach ($memos as $m) {
                            $t   = $types[$m['memo_type']] ?? $types['other'];
                            $sev = $severities[$m['severity']] ?? $severities['medium'];
                            $st  = $statuses[$m['status']] ?? $statuses['issued'];
                        ?>
                            <div class="panel-body" style="border:1px solid #e5e7eb;border-left:4px solid <?php echo $t['color']; ?>;border-radius:8px;margin-bottom:12px;">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                                    <div>
                                        <span class="label label-<?php echo $sev['class']; ?>"><?php echo html_escape($sev['label']); ?></span>
                                        <span class="label label-<?php echo $st['class']; ?>"><?php echo html_escape($st['label']); ?></span>
                                        <div style="margin-top:6px;"><i class="fa <?php echo $t['icon']; ?>" style="color:<?php echo $t['color']; ?>;"></i> <strong><?php echo html_escape($m['subject']); ?></strong> <span class="text-muted">— <?php echo html_escape($t['label']); ?></span></div>
                                        <div class="text-muted" style="font-size:12px;">Issued <?php echo $m['created_at'] ? _dt($m['created_at']) : ''; ?><?php echo $m['incident_date'] ? ' · Incident ' . _d($m['incident_date']) : ''; ?></div>
                                    </div>
                                    <div>
                                        <?php if ($m['status'] === 'issued') { ?>
                                            <button type="button" class="btn btn-primary btn-sm" onclick='openAckModal(<?php echo json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG); ?>)'><i class="fa fa-pen-nib"></i> Acknowledge</button>
                                        <?php } else { ?>
                                            <span class="text-success"><i class="fa fa-circle-check"></i> Acknowledged <?php echo _dt($m['acknowledged_at']); ?></span>
                                        <?php } ?>
                                    </div>
                                </div>
                                <?php if (!empty($m['description'])) { ?>
                                    <div style="margin-top:8px;white-space:pre-wrap;"><?php echo nl2br(html_escape($m['description'])); ?></div>
                                <?php } ?>
                                <?php if (!empty($m['action_taken'])) { ?>
                                    <div style="margin-top:6px;"><span class="text-muted">Action taken:</span> <?php echo nl2br(html_escape($m['action_taken'])); ?></div>
                                <?php } ?>
                                <?php if (!empty($m['attachment'])) { ?>
                                    <div style="margin-top:6px;"><a href="<?php echo admin_url('hr/myhr/memo_attachment/' . $m['id']); ?>" target="_blank"><i class="fa fa-paperclip"></i> View attachment</a></div>
                                <?php } ?>
                                <?php if ($m['status'] !== 'issued' && !empty($m['ack_note'])) { ?>
                                    <div style="margin-top:6px;font-size:12px;" class="text-muted"><i class="fa fa-reply"></i> Your note: <?php echo html_escape($m['ack_note']); ?></div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ack_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <?php echo form_open('', ['id' => 'ack_form']); ?>
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title"><i class="fa fa-file-signature"></i> Acknowledgement Receipt</h4>
                </div>
                <div class="modal-body">
                    <p>You are acknowledging receipt of the memo: <strong id="ack_subject"></strong>.</p>
                    <p class="text-muted" style="font-size:12px;">Acknowledging confirms you have received and read this memo. It does not necessarily mean you agree with it — use the option below to record a disagreement.</p>

                    <div class="form-group"><label>Your response</label>
                        <select name="ack_agree" class="form-control">
                            <option value="agree">I acknowledge receipt of this memo</option>
                            <option value="dispute">I acknowledge receipt but disagree with its contents</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Comments (optional)</label>
                        <textarea name="ack_note" class="form-control" rows="3" placeholder="Add any comments or your side of the matter..."></textarea></div>
                    <div class="form-group"><label>Sign (type your full name) <small class="req text-danger">*</small></label>
                        <input type="text" name="ack_signature" class="form-control" maxlength="191" placeholder="Your full name" required></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-primary">Submit Acknowledgement</button>
                </div>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    function openAckModal(m) {
        var f = $('#ack_form')[0];
        f.reset();
        f.action = '<?php echo admin_url('hr/myhr/acknowledge_memo/'); ?>' + m.id;
        $('#ack_subject').text(m.subject);
        $('#ack_modal').modal('show');
    }
</script>
