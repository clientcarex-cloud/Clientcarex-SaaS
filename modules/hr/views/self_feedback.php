<?php defined('BASEPATH') or exit('No direct script access allowed');

$cats     = hr_feedback_categories();
$statuses = hr_feedback_statuses();
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="bold" style="margin-top:0;"><i class="fa fa-bullhorn text-info"></i> Share with Management</h4>
                        <p class="text-muted">Send a suggestion, feedback, concern or a note of appreciation straight to management. You can also submit anonymously.</p>
                        <hr class="hr-panel-heading" />
                        <?php echo form_open(admin_url('hr/myhr/submit_feedback')); ?>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" class="form-control">
                                <?php foreach ($cats as $slug => $c) { ?>
                                    <option value="<?php echo $slug; ?>"><?php echo html_escape($c['label']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Subject <small class="req text-danger">*</small></label>
                            <input type="text" name="subject" class="form-control" maxlength="191" required>
                        </div>
                        <div class="form-group">
                            <label>Message <small class="req text-danger">*</small></label>
                            <textarea name="message" class="form-control" rows="6" required></textarea>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="is_anonymous" id="fb_anon" value="1">
                            <label for="fb_anon">Submit anonymously <span class="text-muted">(your name will be hidden from management)</span></label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-paper-plane"></i> Send to Management</button>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="bold" style="margin-top:0;"><i class="fa fa-history"></i> My Submissions</h4>
                        <hr class="hr-panel-heading" />
                        <?php if (!count($feedback)) { ?>
                            <p class="text-muted">You have not submitted anything yet.</p>
                        <?php } ?>
                        <?php foreach ($feedback as $f) {
                            $c  = $cats[$f['category']] ?? $cats['other'];
                            $st = $statuses[$f['status']] ?? $statuses['new']; ?>
                            <div style="border:1px solid #e2e8f0;border-radius:10px;padding:14px;margin-bottom:12px;">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                                    <div>
                                        <span style="color:<?php echo $c['color']; ?>;font-weight:700;"><i class="fa <?php echo $c['icon']; ?>"></i> <?php echo html_escape($f['subject']); ?></span>
                                        <div class="text-muted" style="font-size:11px;margin-top:2px;">
                                            <?php echo html_escape($c['label']); ?>
                                            <?php if ($f['is_anonymous']) { ?> · <i class="fa fa-user-secret"></i> Anonymous<?php } ?>
                                            · <?php echo !empty($f['created_at']) ? time_ago($f['created_at']) : ''; ?>
                                        </div>
                                    </div>
                                    <span class="label label-<?php echo $st['class']; ?>"><?php echo $st['label']; ?></span>
                                </div>
                                <div style="margin-top:8px;color:#334155;font-size:13px;"><?php echo nl2br(html_escape($f['message'])); ?></div>
                                <?php if (!empty($f['admin_reply'])) { ?>
                                    <div style="margin-top:10px;background:#f0fdf4;border-left:3px solid #16a34a;border-radius:6px;padding:10px;">
                                        <div class="bold" style="font-size:12px;color:#15803d;"><i class="fa fa-reply"></i> Management response</div>
                                        <div style="font-size:13px;color:#334155;margin-top:3px;"><?php echo nl2br(html_escape($f['admin_reply'])); ?></div>
                                    </div>
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
