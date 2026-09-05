<?php defined('BASEPATH') or exit('No direct script access allowed');

$chain      = isset($chain) ? $chain : [];
$role_names = isset($role_names) ? $role_names : [];
$level_label = function ($lvl) use ($chain, $role_names) {
    if (!isset($chain[$lvl - 1])) {
        return 'Level ' . (int) $lvl;
    }
    return hr_leave_level_label($chain[$lvl - 1], $role_names);
};
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="bold" style="margin-top:0;"><i class="fa fa-check-square-o text-info"></i> Leave Approvals — Awaiting You</h4>
                        <p class="text-muted">Leave requests waiting for your approval level. Approving passes it to the next level; the final level confirms the leave.</p>
                        <hr class="hr-panel-heading" />

                        <?php if (!count($pending)) { ?>
                            <p class="text-muted" style="padding:20px 0;"><i class="fa fa-check-circle text-success"></i> Nothing awaiting your approval right now.</p>
                        <?php } ?>

                        <?php foreach ($pending as $r) { ?>
                            <div style="border:1px solid #e2e8f0;border-radius:10px;padding:14px;margin-bottom:12px;">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
                                    <div>
                                        <span class="bold" style="font-size:14px;"><?php echo html_escape($r['firstname'] . ' ' . $r['lastname']); ?></span>
                                        <span class="label" style="background:<?php echo html_escape($r['type_color']); ?>;color:#fff;"><?php echo html_escape($r['type_code']); ?></span>
                                        <span><?php echo html_escape($r['type_name']); ?></span>
                                        <div class="text-muted" style="font-size:12px;margin-top:3px;">
                                            <i class="fa fa-calendar"></i> <?php echo _d($r['from_date']); ?> &rarr; <?php echo _d($r['to_date']); ?>
                                            · <?php echo (float) $r['days']; ?> day(s)<?php echo $r['is_half_day'] ? ' (half)' : ''; ?>
                                            · applied <?php echo !empty($r['created_at']) ? time_ago($r['created_at']) : ''; ?>
                                        </div>
                                        <?php if (!empty($r['reason'])) { ?>
                                            <div style="font-size:13px;color:#334155;margin-top:6px;"><?php echo nl2br(html_escape($r['reason'])); ?></div>
                                        <?php } ?>
                                        <?php if (!empty($r['proof_file'])) { ?>
                                            <div style="margin-top:5px;"><a href="<?php echo admin_url('hr/myhr/leave_proof/' . $r['id']); ?>" target="_blank" style="font-size:12px;"><i class="fa fa-paperclip"></i> Proof of reason</a></div>
                                        <?php } ?>
                                    </div>
                                    <div style="text-align:right;">
                                        <span class="label label-warning">Level <?php echo (int) $r['current_level']; ?>/<?php echo count($chain); ?> — <?php echo html_escape($level_label((int) $r['current_level'])); ?></span>
                                        <div style="margin-top:10px;">
                                            <a href="#" class="btn btn-success btn-sm leave-review-link"
                                               data-url="<?php echo admin_url('hr/myhr/leave_review/' . $r['id']); ?>">
                                                <i class="fa fa-clipboard"></i> Review &amp; Decide
                                            </a>
                                        </div>
                                        <div style="margin-top:6px;font-size:11px;color:#94a3b8;">
                                            Workload &amp; team cover shown before you decide
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave review (workload + department cover) modal -->
    <div class="modal fade" id="leave_review_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content" id="leave_review_content">
                <div class="modal-body text-center" style="padding:40px;">
                    <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function () {
        var spinner = '<div class="modal-body text-center" style="padding:40px;">'
            + '<i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>';

        $(document).on('click', '.leave-review-link', function (e) {
            e.preventDefault();
            var url = $(this).data('url');
            $('#leave_review_content').html(spinner);
            $('#leave_review_modal').modal('show');
            $.get(url)
                .done(function (html) { $('#leave_review_content').html(html); })
                .fail(function () {
                    $('#leave_review_content').html(
                        '<div class="modal-body"><p class="text-danger" style="padding:20px;">'
                        + 'Could not load the review. Please try again.</p>'
                        + '<div class="text-right"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div></div>'
                    );
                });
        });
    });
</script>
