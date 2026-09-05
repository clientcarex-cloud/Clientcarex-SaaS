<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 style="margin-top:6px;font-weight:800;"><i class="fa fa-star"></i> My Appraisals</h4>
                        <hr class="hr-panel-heading" />
                        <table class="table table-striped">
                            <thead><tr><th>Period</th><th>Overall Rating</th><th>Reviewer</th><th>Completed</th><th></th></tr></thead>
                            <tbody>
                                <?php if (!count($appraisals)) { ?>
                                    <tr><td colspan="5" class="text-muted">No completed appraisals yet.</td></tr>
                                <?php }
                                foreach ($appraisals as $a) { ?>
                                    <tr>
                                        <td><?php echo _d($a['period_from']); ?> &rarr; <?php echo _d($a['period_to']); ?></td>
                                        <td>
                                            <span class="bold" style="font-size:15px;"><?php echo (float) $a['overall_rating']; ?></span> / 5
                                            <?php $stars = round($a['overall_rating']); for ($i = 1; $i <= 5; $i++) { ?>
                                                <i class="fa fa-star<?php echo $i <= $stars ? '' : '-o'; ?>" style="color:#f59e0b;"></i>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo html_escape(trim(($a['rev_first'] ?? '') . ' ' . ($a['rev_last'] ?? '')) ?: '—'); ?></td>
                                        <td><?php echo $a['completed_at'] ? _d($a['completed_at']) : '—'; ?></td>
                                        <td class="text-right"><a href="<?php echo admin_url('hr/myhr/appraisal/' . $a['id']); ?>" class="btn btn-default btn-sm">View</a></td>
                                    </tr>
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
