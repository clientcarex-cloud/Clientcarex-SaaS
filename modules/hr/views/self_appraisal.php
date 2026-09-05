<?php defined('BASEPATH') or exit('No direct script access allowed');

$ratings = json_decode($appraisal['ratings'], true) ?: [];
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                            <h4 style="margin:0;font-weight:800;"><i class="fa fa-star"></i> Appraisal #<?php echo (int) $appraisal['id']; ?></h4>
                            <a href="<?php echo admin_url('hr/myhr/appraisals'); ?>" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back</a>
                        </div>
                        <p class="text-muted" style="margin-top:6px;">
                            Period <?php echo _d($appraisal['period_from']); ?> &rarr; <?php echo _d($appraisal['period_to']); ?>
                            <?php if ($reviewer) { ?> &middot; Reviewed by <?php echo html_escape($reviewer); ?><?php } ?>
                        </p>

                        <div class="text-center" style="background:#f8fafc;border-radius:12px;padding:18px;margin:10px 0 20px;">
                            <div style="font-size:34px;font-weight:800;color:#1e293b;"><?php echo (float) $appraisal['overall_rating']; ?> <span style="font-size:18px;color:#94a3b8;">/ 5</span></div>
                            <?php $stars = round($appraisal['overall_rating']); for ($i = 1; $i <= 5; $i++) { ?>
                                <i class="fa fa-star<?php echo $i <= $stars ? '' : '-o'; ?>" style="color:#f59e0b;font-size:20px;"></i>
                            <?php } ?>
                            <div class="text-muted" style="margin-top:4px;">Overall Rating</div>
                        </div>

                        <?php if (count($ratings)) { ?>
                            <table class="table">
                                <thead><tr><th>Criterion</th><th class="text-right">Rating</th></tr></thead>
                                <tbody>
                                    <?php foreach ($ratings as $criterion => $score) { ?>
                                        <tr>
                                            <td><?php echo html_escape($criterion); ?></td>
                                            <td class="text-right">
                                                <?php for ($i = 1; $i <= 5; $i++) { ?>
                                                    <i class="fa fa-star<?php echo $i <= $score ? '' : '-o'; ?>" style="color:#f59e0b;"></i>
                                                <?php } ?>
                                                <span class="text-muted">(<?php echo (float) $score; ?>)</span>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php } ?>

                        <?php
                        $blocks = [
                            'Strengths'         => $appraisal['strengths'],
                            'Areas to Improve'  => $appraisal['improvements'],
                            'Goals for Next Period' => $appraisal['goals'],
                        ];
                        foreach ($blocks as $label => $text) {
                            if (trim((string) $text) === '') { continue; } ?>
                            <div style="margin-top:16px;">
                                <div class="bold" style="text-transform:uppercase;font-size:12px;color:#64748b;letter-spacing:.4px;"><?php echo $label; ?></div>
                                <div style="white-space:pre-wrap;margin-top:4px;"><?php echo html_escape($text); ?></div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
