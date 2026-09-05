<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_edit = has_permission('hr_appraisals', '', 'edit') || is_admin();
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <h4 class="bold" style="margin:0;">Performance Appraisals</h4>
                            <div>
                                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>
                                <?php if ($can_edit) { ?>
                                    <a href="<?php echo admin_url('hr/appraisal'); ?>" class="btn btn-primary"><i class="fa fa-plus"></i> New Appraisal</a>
                                <?php } ?>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />
                        <table class="table table-striped dt-table">
                            <thead><tr><th>Employee</th><th>Period</th><th>Reviewer</th><th>Overall Rating</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($appraisals as $ap) { ?>
                                    <tr>
                                        <td><a href="<?php echo admin_url('hr/employee/' . $ap['staff_id'] . '?tab=appraisals'); ?>"><?php echo html_escape($ap['firstname'] . ' ' . $ap['lastname']); ?></a></td>
                                        <td><?php echo ($ap['period_from'] ? _d($ap['period_from']) : '?') . ' - ' . ($ap['period_to'] ? _d($ap['period_to']) : '?'); ?></td>
                                        <td><?php echo html_escape(trim(($ap['rev_first'] ?? '') . ' ' . ($ap['rev_last'] ?? ''))) ?: '-'; ?></td>
                                        <td data-order="<?php echo $ap['overall_rating']; ?>">
                                            <?php $r = (float) $ap['overall_rating'];
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo '<i class="fa fa-star' . ($i <= round($r) ? '' : '-o') . '" style="color:#f59e0b;"></i>';
                                            } ?>
                                            <span class="bold mleft5"><?php echo $r; ?></span>
                                        </td>
                                        <td><span class="label label-<?php echo $ap['status'] === 'completed' ? 'success' : 'default'; ?>"><?php echo ucfirst($ap['status']); ?></span></td>
                                        <td class="text-right">
                                            <a href="<?php echo admin_url('hr/appraisal/' . $ap['id']); ?>" class="btn btn-default btn-icon"><i class="fa fa-pencil"></i></a>
                                            <?php if (has_permission('hr_appraisals', '', 'delete') || is_admin()) { ?>
                                                <a href="<?php echo admin_url('hr/delete_appraisal/' . $ap['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
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
</div>
<?php init_tail(); ?>
