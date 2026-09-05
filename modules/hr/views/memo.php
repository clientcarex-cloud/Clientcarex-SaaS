<?php defined('BASEPATH') or exit('No direct script access allowed');

$types      = hr_memo_types();
$severities = hr_memo_severities();
$statuses   = hr_memo_statuses();
$t   = $types[$memo['memo_type']] ?? $types['other'];
$sev = $severities[$memo['severity']] ?? $severities['medium'];
$st  = $statuses[$memo['status']] ?? $statuses['issued'];
$can_delete = has_permission('hr_memos', '', 'delete') || is_admin();
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                            <div>
                                <span class="label label-<?php echo $sev['class']; ?>" style="font-size:12px;"><?php echo html_escape($sev['label']); ?> severity</span>
                                <span class="label label-<?php echo $st['class']; ?>" style="font-size:12px;"><?php echo html_escape($st['label']); ?></span>
                                <h3 style="margin:8px 0 2px;"><i class="fa <?php echo $t['icon']; ?>" style="color:<?php echo $t['color']; ?>;"></i> <?php echo html_escape($memo['subject']); ?></h3>
                                <div class="text-muted"><?php echo html_escape($t['label']); ?></div>
                            </div>
                            <div>
                                <a href="<?php echo admin_url('hr/memos'); ?>" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> <?php echo _l('back'); ?></a>
                                <?php if ($can_delete) { ?>
                                    <a href="<?php echo admin_url('hr/delete_memo/' . $memo['id']); ?>" class="btn btn-danger btn-sm _delete"><i class="fa fa-remove"></i></a>
                                <?php } ?>
                            </div>
                        </div>

                        <hr class="hr-panel-heading" />

                        <div class="row">
                            <div class="col-sm-6"><p><strong>Employee:</strong> <a href="<?php echo admin_url('hr/employee/' . $memo['staff_id']); ?>"><?php echo html_escape(trim($memo['firstname'] . ' ' . $memo['lastname'])); ?></a> <?php echo $memo['employee_code'] ? '<span class="text-muted">(' . html_escape($memo['employee_code']) . ')</span>' : ''; ?></p></div>
                            <div class="col-sm-6"><p><strong>Issued by:</strong> <?php echo html_escape(trim(($memo['issuer_first'] ?? '') . ' ' . ($memo['issuer_last'] ?? '')) ?: '—'); ?></p></div>
                            <div class="col-sm-6"><p><strong>Incident date:</strong> <?php echo $memo['incident_date'] ? _d($memo['incident_date']) : '—'; ?></p></div>
                            <div class="col-sm-6"><p><strong>Issued on:</strong> <?php echo $memo['created_at'] ? _dt($memo['created_at']) : '—'; ?></p></div>
                            <?php if ((float) $memo['penalty_amount'] > 0) { ?>
                                <div class="col-sm-6"><p><strong>Penalty / fine:</strong> <?php echo app_format_money($memo['penalty_amount'], get_base_currency()); ?></p></div>
                            <?php } ?>
                            <?php if ($memo['memo_type'] === 'suspension' && ($memo['suspension_from'] || $memo['suspension_to'])) { ?>
                                <div class="col-sm-6"><p><strong>Suspension:</strong> <?php echo $memo['suspension_from'] ? _d($memo['suspension_from']) : '?'; ?> &rarr; <?php echo $memo['suspension_to'] ? _d($memo['suspension_to']) : '?'; ?></p></div>
                            <?php } ?>
                        </div>

                        <?php if (!empty($memo['description'])) { ?>
                            <div class="form-group"><label class="text-muted">Details</label>
                                <div style="white-space:pre-wrap;"><?php echo nl2br(html_escape($memo['description'])); ?></div></div>
                        <?php } ?>
                        <?php if (!empty($memo['action_taken'])) { ?>
                            <div class="form-group"><label class="text-muted">Action taken</label>
                                <div style="white-space:pre-wrap;"><?php echo nl2br(html_escape($memo['action_taken'])); ?></div></div>
                        <?php } ?>
                        <?php if (!empty($memo['attachment'])) { ?>
                            <p><a href="<?php echo admin_url('hr/memo_attachment/' . $memo['id']); ?>" target="_blank" class="btn btn-default btn-sm"><i class="fa fa-paperclip"></i> View attachment</a></p>
                        <?php } ?>

                        <hr class="hr-panel-heading" />
                        <h4 class="bold"><i class="fa fa-file-signature"></i> Acknowledgement Receipt</h4>
                        <?php if ($memo['status'] === 'issued') { ?>
                            <div class="alert alert-warning" style="margin-bottom:0;">
                                <i class="fa fa-clock"></i> Awaiting the employee's acknowledgement. They have been notified and can sign it from <strong>My HR &rarr; My Memos</strong>.
                            </div>
                        <?php } else {
                            $agree = (int) $memo['ack_agree'] === 1;
                        ?>
                            <div class="panel-body" style="background:<?php echo $agree ? '#f0fdf4' : '#fef2f2'; ?>;border:1px solid <?php echo $agree ? '#bbf7d0' : '#fecaca'; ?>;border-radius:8px;">
                                <p style="margin:0 0 6px;">
                                    <i class="fa <?php echo $agree ? 'fa-circle-check text-success' : 'fa-triangle-exclamation text-danger'; ?>"></i>
                                    <strong><?php echo $agree ? 'Acknowledged' : 'Acknowledged with disagreement'; ?></strong>
                                    on <?php echo _dt($memo['acknowledged_at']); ?>
                                </p>
                                <p style="margin:0;"><strong>Signed:</strong> <?php echo html_escape($memo['ack_signature']); ?></p>
                                <?php if (!empty($memo['ack_note'])) { ?>
                                    <p style="margin:6px 0 0;"><strong>Employee note:</strong> <span style="white-space:pre-wrap;"><?php echo nl2br(html_escape($memo['ack_note'])); ?></span></p>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
