<?php defined('BASEPATH') or exit('No direct script access allowed');

$status_colors = ['planned' => 'default', 'ongoing' => 'warning', 'completed' => 'success', 'cancelled' => 'danger'];
$att_colors    = ['invited' => 'default', 'confirmed' => 'info', 'attended' => 'success', 'absent' => 'danger', 'completed' => 'success'];
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 style="margin-top:6px;font-weight:800;"><i class="fa fa-book"></i> My Trainings &amp; Development</h4>
                        <hr class="hr-panel-heading" />
                        <table class="table table-striped">
                            <thead><tr><th>Training</th><th>Trainer</th><th>Dates</th><th>Status</th><th>My Status</th><th>Score</th></tr></thead>
                            <tbody>
                                <?php if (!count($trainings)) { ?>
                                    <tr><td colspan="6" class="text-muted">You are not enrolled in any trainings yet.</td></tr>
                                <?php }
                                foreach ($trainings as $t) { ?>
                                    <tr>
                                        <td>
                                            <span class="bold"><?php echo html_escape($t['title']); ?></span>
                                            <?php if (!empty($t['category'])) { ?><br><small class="text-muted"><?php echo html_escape($t['category']); ?></small><?php } ?>
                                            <?php if (!empty($t['venue'])) { ?><br><small class="text-muted"><i class="fa fa-map-marker"></i> <?php echo html_escape($t['venue']); ?></small><?php } ?>
                                        </td>
                                        <td><?php echo html_escape($t['trainer'] ?: '—'); ?></td>
                                        <td>
                                            <?php echo $t['start_date'] ? _d($t['start_date']) : '—'; ?>
                                            <?php if (!empty($t['end_date']) && $t['end_date'] != $t['start_date']) { ?> &rarr; <?php echo _d($t['end_date']); ?><?php } ?>
                                        </td>
                                        <td><span class="label label-<?php echo $status_colors[$t['status']] ?? 'default'; ?>"><?php echo ucfirst($t['status']); ?></span></td>
                                        <td><span class="label label-<?php echo $att_colors[$t['attendee_status']] ?? 'default'; ?>"><?php echo ucfirst($t['attendee_status'] ?: 'invited'); ?></span></td>
                                        <td><?php echo ($t['score'] !== null && $t['score'] !== '') ? html_escape($t['score']) : '—'; ?></td>
                                    </tr>
                                    <?php if (!empty($t['remarks'])) { ?>
                                        <tr><td colspan="6" class="text-muted" style="font-size:12px;padding-top:0;"><i class="fa fa-comment-o"></i> <?php echo html_escape($t['remarks']); ?></td></tr>
                                    <?php } ?>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
