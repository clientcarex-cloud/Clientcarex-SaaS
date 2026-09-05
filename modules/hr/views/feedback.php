<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_edit   = has_permission('hr_feedback', '', 'edit') || is_admin();
$can_delete = has_permission('hr_feedback', '', 'delete') || is_admin();
$cats       = hr_feedback_categories();
$fstatuses  = hr_feedback_statuses();
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                            <h4 class="bold" style="margin:0;"><i class="fa fa-comments-o text-info"></i> Suggestions &amp; Feedback</h4>
                            <div class="btn-group">
                                <?php
                                $tabs = array_merge(['' => 'All'], array_map(function ($s) { return $s['label']; }, $fstatuses));
                                foreach ($tabs as $key => $label) {
                                    $cnt = $key === '' ? array_sum($counts) : ($counts[$key] ?? 0); ?>
                                    <a href="<?php echo admin_url('hr/feedback' . ($key ? '?status=' . $key : '')); ?>"
                                       class="btn btn-<?php echo ($status ?: '') === $key ? 'primary' : 'default'; ?>">
                                        <?php echo $label; ?> <span class="badge"><?php echo (int) $cnt; ?></span>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />

                        <?php if (!count($feedback)) { ?>
                            <p class="text-muted">No submissions<?php echo $status ? ' in this status' : ' yet'; ?>.</p>
                        <?php } ?>

                        <div class="row">
                            <?php foreach ($feedback as $f) {
                                $c  = $cats[$f['category']] ?? $cats['other'];
                                $st = $fstatuses[$f['status']] ?? $fstatuses['new'];
                                $who = $f['is_anonymous']
                                    ? '<i class="fa fa-user-secret"></i> Anonymous'
                                    : html_escape(trim($f['firstname'] . ' ' . $f['lastname']));
                                $payload = [
                                    'id'      => $f['id'],
                                    'subject' => $f['subject'],
                                    'status'  => $f['status'],
                                    'reply'   => (string) ($f['admin_reply'] ?? ''),
                                ]; ?>
                                <div class="col-md-6">
                                    <div style="border:1px solid #e2e8f0;border-left:4px solid <?php echo $c['color']; ?>;border-radius:10px;padding:14px;margin-bottom:16px;">
                                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                                            <div>
                                                <span style="font-weight:700;font-size:14px;"><i class="fa <?php echo $c['icon']; ?>" style="color:<?php echo $c['color']; ?>;"></i> <?php echo html_escape($f['subject']); ?></span>
                                                <div class="text-muted" style="font-size:11px;margin-top:3px;">
                                                    <?php echo html_escape($c['label']); ?> · <?php echo $who; ?> · <?php echo !empty($f['created_at']) ? '<span data-toggle="tooltip" title="' . _dt($f['created_at']) . '">' . time_ago($f['created_at']) . '</span>' : ''; ?>
                                                </div>
                                            </div>
                                            <span class="label label-<?php echo $st['class']; ?>"><?php echo $st['label']; ?></span>
                                        </div>
                                        <div style="margin-top:9px;color:#334155;font-size:13px;white-space:pre-wrap;"><?php echo nl2br(html_escape($f['message'])); ?></div>

                                        <?php if (!empty($f['admin_reply'])) { ?>
                                            <div style="margin-top:10px;background:#f8fafc;border-left:3px solid #16a34a;border-radius:6px;padding:9px;">
                                                <div class="bold" style="font-size:11px;color:#15803d;"><i class="fa fa-reply"></i> Response<?php echo $f['replied_by'] ? ' — ' . html_escape(get_staff_full_name($f['replied_by'])) : ''; ?></div>
                                                <div style="font-size:12px;color:#334155;"><?php echo nl2br(html_escape($f['admin_reply'])); ?></div>
                                            </div>
                                        <?php } ?>

                                        <?php if ($can_edit || $can_delete) { ?>
                                            <div style="margin-top:10px;text-align:right;">
                                                <?php if ($can_edit) { ?>
                                                    <button type="button" class="btn btn-default btn-sm" onclick='openFeedbackModal(<?php echo json_encode($payload, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG); ?>)'><i class="fa fa-reply"></i> Respond / Status</button>
                                                <?php } ?>
                                                <?php if ($can_delete) { ?>
                                                    <a href="<?php echo admin_url('hr/delete_feedback/' . $f['id']); ?>" class="btn btn-danger btn-sm _delete"><i class="fa fa-remove"></i></a>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($can_edit) { ?>
    <div class="modal fade" id="feedback_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <?php echo form_open(admin_url('hr/reply_feedback')); ?>
                <input type="hidden" name="id" id="fb_id">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Respond — <span id="fb_subject"></span></h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="fb_status" class="form-control">
                            <?php foreach ($fstatuses as $slug => $s) { ?>
                                <option value="<?php echo $slug; ?>"><?php echo $s['label']; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Response to the employee <span class="text-muted">(visible to the submitter)</span></label>
                        <textarea name="admin_reply" id="fb_reply" class="form-control" rows="5"></textarea>
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
    function openFeedbackModal(f) {
        $('#fb_id').val(f.id);
        $('#fb_subject').text(f.subject);
        $('#fb_status').val(f.status);
        $('#fb_reply').val(f.reply || '');
        $('#feedback_modal').modal('show');
    }
</script>
