<?php defined('BASEPATH') or exit('No direct script access allowed');

$platforms = hr_interview_platforms();
$statuses  = hr_interview_statuses();
$recs      = hr_interview_recommendations();
$pl = $platforms[$iv['platform']] ?? ['label' => $iv['platform'], 'icon' => 'fa-video', 'online' => false];
$st = $statuses[$iv['status']] ?? $statuses['scheduled'];
$can_edit   = has_permission('hr_interviews', '', 'edit') || is_admin();
$can_delete = has_permission('hr_interviews', '', 'delete') || is_admin();
$online = !empty($pl['online']);
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                            <div>
                                <h3 style="margin:0 0 4px;"><?php echo html_escape($iv['candidate_name']); ?></h3>
                                <div class="text-muted"><?php echo html_escape($iv['position'] ?: ($iv['designation_name'] ?: '')); ?><?php echo $iv['department_name'] ? ' · ' . html_escape($iv['department_name']) : ''; ?></div>
                                <div style="margin-top:6px;">
                                    <span class="label label-<?php echo $st['class']; ?>"><?php echo html_escape($st['label']); ?></span>
                                    <span class="label label-default">Round #<?php echo (int) $iv['round_no']; ?><?php echo $iv['round_name'] ? ' · ' . html_escape($iv['round_name']) : ''; ?></span>
                                </div>
                            </div>
                            <div>
                                <a href="<?php echo admin_url('hr/interviews'); ?>" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> <?php echo _l('back'); ?></a>
                                <?php if ($can_delete) { ?>
                                    <a href="<?php echo admin_url('hr/delete_interview/' . $iv['id']); ?>" class="btn btn-danger btn-sm _delete"><i class="fa fa-remove"></i></a>
                                <?php } ?>
                            </div>
                        </div>

                        <hr class="hr-panel-heading" />

                        <div class="row">
                            <div class="col-sm-6"><p><strong>When:</strong> <?php echo $iv['scheduled_at'] ? _dt($iv['scheduled_at']) : 'TBD'; ?> <span class="text-muted">(<?php echo (int) $iv['duration_minutes']; ?> min)</span></p></div>
                            <div class="col-sm-6"><p><strong>Mode:</strong> <i class="fa <?php echo $pl['icon']; ?>"></i> <?php echo html_escape($pl['label']); ?></p></div>
                            <?php if ($iv['candidate_email']) { ?><div class="col-sm-6"><p><strong>Email:</strong> <?php echo html_escape($iv['candidate_email']); ?></p></div><?php } ?>
                            <?php if ($iv['candidate_phone']) { ?><div class="col-sm-6"><p><strong>Phone:</strong> <?php echo html_escape($iv['candidate_phone']); ?></p></div><?php } ?>
                            <?php if ($iv['platform'] === 'in_person' && $iv['location']) { ?><div class="col-sm-12"><p><strong>Location:</strong> <?php echo html_escape($iv['location']); ?></p></div><?php } ?>
                        </div>

                        <?php if ($online) { ?>
                            <div class="panel-body" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;">
                                <?php if (!empty($iv['meeting_url'])) { ?>
                                    <p style="margin:0 0 8px;"><strong><i class="fa <?php echo $pl['icon']; ?>"></i> <?php echo html_escape($pl['label']); ?> meeting</strong></p>
                                    <a href="<?php echo html_escape($iv['meeting_url']); ?>" target="_blank" class="btn btn-success btn-sm"><i class="fa fa-video"></i> Join meeting</a>
                                    <?php if (!empty($iv['provider_host_url'])) { ?>
                                        <a href="<?php echo html_escape($iv['provider_host_url']); ?>" target="_blank" class="btn btn-default btn-sm">Host / manage</a>
                                    <?php } ?>
                                    <div style="margin-top:8px;font-size:12px;word-break:break-all;">
                                        <span class="text-muted">Link:</span> <?php echo html_escape($iv['meeting_url']); ?>
                                        <?php if ($iv['meeting_id']) { ?><br><span class="text-muted">Meeting ID:</span> <?php echo html_escape($iv['meeting_id']); ?><?php } ?>
                                        <?php if ($iv['meeting_password']) { ?> · <span class="text-muted">Passcode:</span> <?php echo html_escape($iv['meeting_password']); ?><?php } ?>
                                    </div>
                                <?php } else { ?>
                                    <p class="text-muted" style="margin:0;"><i class="fa fa-triangle-exclamation"></i> No meeting link yet. Edit the interview to add one, or configure <?php echo html_escape($pl['label']); ?> in HR Settings to auto-generate.</p>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <div style="margin-top:12px;">
                            <p><strong>Interviewers:</strong>
                                <?php if (empty($interviewer_names)) { ?><span class="text-muted">—</span><?php }
                                echo implode(', ', array_map('html_escape', $interviewer_names)); ?>
                            </p>
                            <?php if (!empty($iv['resume_file'])) { ?>
                                <p><a href="<?php echo admin_url('hr/interview_resume/' . $iv['id']); ?>" class="btn btn-default btn-sm"><i class="fa fa-file"></i> Download resume</a></p>
                            <?php } ?>
                            <?php if (!empty($iv['notes'])) { ?>
                                <p><strong>Notes:</strong> <?php echo nl2br(html_escape($iv['notes'])); ?></p>
                            <?php } ?>
                        </div>

                        <?php if ($can_edit) { ?>
                            <div style="margin-top:8px;">
                                <a href="<?php echo admin_url('hr/resend_interview_invite/' . $iv['id']); ?>" class="btn btn-default btn-sm"><i class="fa fa-envelope"></i> <?php echo $iv['invite_sent'] ? 'Resend invite email' : 'Send invite email'; ?></a>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="bold" style="margin-top:0;"><i class="fa fa-star text-warning"></i> Outcome &amp; Feedback</h4>
                        <hr class="hr-panel-heading" />
                        <?php if ($can_edit) { ?>
                            <?php echo form_open(admin_url('hr/interview_feedback/' . $iv['id'])); ?>
                            <div class="form-group"><label>Status</label>
                                <select name="status" class="form-control">
                                    <?php foreach ($statuses as $sk => $sm) { ?>
                                        <option value="<?php echo $sk; ?>" <?php echo $iv['status'] === $sk ? 'selected' : ''; ?>><?php echo html_escape($sm['label']); ?></option>
                                    <?php } ?>
                                </select></div>
                            <div class="form-group"><label>Rating (0–5)</label>
                                <input type="number" name="rating" class="form-control" min="0" max="5" step="0.5" value="<?php echo (float) $iv['rating']; ?>"></div>
                            <div class="form-group"><label>Recommendation</label>
                                <select name="recommendation" class="form-control">
                                    <option value="">—</option>
                                    <?php foreach ($recs as $rk => $rm) { ?>
                                        <option value="<?php echo $rk; ?>" <?php echo $iv['recommendation'] === $rk ? 'selected' : ''; ?>><?php echo html_escape($rm['label']); ?></option>
                                    <?php } ?>
                                </select></div>
                            <div class="form-group"><label>Feedback</label>
                                <textarea name="feedback" class="form-control" rows="6"><?php echo html_escape($iv['feedback']); ?></textarea></div>
                            <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-save"></i> Save outcome</button>
                            <?php echo form_close(); ?>
                        <?php } else { ?>
                            <p><strong>Rating:</strong> <?php echo (float) $iv['rating']; ?>/5</p>
                            <?php if ($iv['recommendation'] && isset($recs[$iv['recommendation']])) { ?>
                                <p><span class="label label-<?php echo $recs[$iv['recommendation']]['class']; ?>"><?php echo html_escape($recs[$iv['recommendation']]['label']); ?></span></p>
                            <?php } ?>
                            <p style="white-space:pre-wrap;"><?php echo html_escape($iv['feedback'] ?: 'No feedback yet.'); ?></p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
