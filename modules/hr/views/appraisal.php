<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_edit = has_permission('hr_appraisals', '', 'edit') || is_admin();
$ratings  = $appraisal ? (json_decode($appraisal['ratings'], true) ?: []) : [];
$preselect_staff = $appraisal ? $appraisal['staff_id'] : (int) $this->input->get('staff_id');
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <a href="<?php echo admin_url('hr/appraisals'); ?>" class="btn btn-default btn-sm mbot15"><i class="fa fa-arrow-left"></i> All Appraisals</a>
                        <h4 class="bold"><?php echo $appraisal ? 'Appraisal #' . $appraisal['id'] : 'New Appraisal'; ?></h4>
                        <hr class="hr-panel-heading" />

                        <?php echo form_open(admin_url('hr/save_appraisal')); ?>
                        <?php if ($appraisal) { ?><input type="hidden" name="id" value="<?php echo $appraisal['id']; ?>"><?php } ?>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group"><label>Employee</label>
                                    <select name="staff_id" class="selectpicker" data-width="100%" data-live-search="true" required <?php echo $appraisal ? 'disabled' : ''; ?>>
                                        <option value=""></option>
                                        <?php foreach ($employees as $emp) { ?>
                                            <option value="<?php echo $emp['staffid']; ?>" <?php echo $preselect_staff == $emp['staffid'] ? 'selected' : ''; ?>><?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname'] . ' (' . $emp['employee_code'] . ')'); ?></option>
                                        <?php } ?>
                                    </select>
                                    <?php if ($appraisal) { ?><input type="hidden" name="staff_id" value="<?php echo $appraisal['staff_id']; ?>"><?php } ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group"><label>Reviewer</label>
                                    <select name="reviewer_id" class="selectpicker" data-width="100%" data-live-search="true">
                                        <option value=""></option>
                                        <?php foreach ($employees as $emp) { ?>
                                            <option value="<?php echo $emp['staffid']; ?>" <?php echo ($appraisal ? $appraisal['reviewer_id'] : get_staff_user_id()) == $emp['staffid'] ? 'selected' : ''; ?>><?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname']); ?></option>
                                        <?php } ?>
                                    </select></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6"><div class="form-group"><label>Period From</label>
                                <input type="date" name="period_from" class="form-control" value="<?php echo $appraisal ? $appraisal['period_from'] : date('Y-01-01'); ?>"></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Period To</label>
                                <input type="date" name="period_to" class="form-control" value="<?php echo $appraisal ? $appraisal['period_to'] : date('Y-m-d'); ?>"></div></div>
                        </div>

                        <h4 class="bold mtop15">Ratings <span class="text-muted" style="font-weight:400;font-size:12px;">(0 = not rated, 1 = poor, 5 = outstanding)</span></h4>
                        <table class="table table-striped">
                            <tbody>
                                <?php foreach ($criteria as $criterion) {
                                    $val = $ratings[$criterion] ?? 0; ?>
                                    <tr>
                                        <td style="width:50%;"><?php echo $criterion; ?></td>
                                        <td>
                                            <div class="btn-group" data-toggle="buttons">
                                                <?php for ($i = 1; $i <= 5; $i++) { ?>
                                                    <label class="btn btn-default btn-sm <?php echo $val == $i ? 'active btn-warning' : ''; ?>">
                                                        <input type="radio" name="ratings[<?php echo html_escape($criterion); ?>]" value="<?php echo $i; ?>" <?php echo $val == $i ? 'checked' : ''; ?> <?php echo $can_edit ? '' : 'disabled'; ?>> <?php echo $i; ?>
                                                    </label>
                                                <?php } ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>

                        <div class="row">
                            <div class="col-md-4"><div class="form-group"><label>Strengths</label>
                                <textarea name="strengths" class="form-control" rows="3" <?php echo $can_edit ? '' : 'readonly'; ?>><?php echo $appraisal ? html_escape($appraisal['strengths']) : ''; ?></textarea></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Areas of Improvement</label>
                                <textarea name="improvements" class="form-control" rows="3" <?php echo $can_edit ? '' : 'readonly'; ?>><?php echo $appraisal ? html_escape($appraisal['improvements']) : ''; ?></textarea></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Goals for Next Period</label>
                                <textarea name="goals" class="form-control" rows="3" <?php echo $can_edit ? '' : 'readonly'; ?>><?php echo $appraisal ? html_escape($appraisal['goals']) : ''; ?></textarea></div></div>
                        </div>

                        <?php if ($can_edit) { ?>
                            <button type="submit" name="status" value="draft" class="btn btn-default">Save as Draft</button>
                            <button type="submit" name="status" value="completed" class="btn btn-primary">Save &amp; Complete</button>
                        <?php } ?>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
