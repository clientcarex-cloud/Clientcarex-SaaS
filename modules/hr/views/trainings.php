<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_edit      = has_permission('hr_trainings', '', 'edit') || is_admin();
$status_labels = ['planned' => 'info', 'ongoing' => 'warning', 'completed' => 'success', 'cancelled' => 'default'];
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <h4 class="bold" style="margin:0;">Trainings &amp; CME Programs</h4>
                            <div>
                                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>
                                <?php if ($can_edit) { ?>
                                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#training_modal"><i class="fa fa-plus"></i> New Training</button>
                                <?php } ?>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />
                        <table class="table table-striped dt-table">
                            <thead><tr><th>Title</th><th>Category</th><th>Trainer</th><th>Department</th><th>Dates</th><th>Attendees</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($trainings as $t) { ?>
                                    <tr>
                                        <td><a href="<?php echo admin_url('hr/training/' . $t['id']); ?>" class="bold"><?php echo html_escape($t['title']); ?></a></td>
                                        <td><?php echo html_escape($t['category'] ?: '-'); ?></td>
                                        <td><?php echo html_escape($t['trainer'] ?: '-'); ?></td>
                                        <td><?php echo html_escape($t['department_name'] ?: 'All'); ?></td>
                                        <td><?php echo ($t['start_date'] ? _d($t['start_date']) : '?') . ($t['end_date'] ? ' - ' . _d($t['end_date']) : ''); ?></td>
                                        <td><?php echo (int) $t['attendee_count']; ?></td>
                                        <td><span class="label label-<?php echo $status_labels[$t['status']] ?? 'default'; ?>"><?php echo ucfirst($t['status']); ?></span></td>
                                        <td class="text-right">
                                            <a href="<?php echo admin_url('hr/training/' . $t['id']); ?>" class="btn btn-default btn-icon"><i class="fa fa-folder-open"></i></a>
                                            <?php if (has_permission('hr_trainings', '', 'delete') || is_admin()) { ?>
                                                <a href="<?php echo admin_url('hr/delete_training/' . $t['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
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

    <div class="modal fade" id="training_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <?php echo form_open(admin_url('hr/save_training')); ?>
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">New Training</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label>Title</label>
                        <input type="text" name="title" class="form-control" required></div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Category</label>
                            <select name="category" class="form-control">
                                <?php foreach (['Clinical Skills', 'CME', 'Nursing', 'Fire & Safety', 'Infection Control', 'BLS / ACLS', 'Soft Skills', 'NABH / Compliance', 'Equipment', 'Induction', 'Other'] as $cat) { ?>
                                    <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                                <?php } ?>
                            </select></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Trainer / Faculty</label>
                            <input type="text" name="trainer" class="form-control"></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Start Date</label>
                            <input type="date" name="start_date" class="form-control"></div></div>
                        <div class="col-md-6"><div class="form-group"><label>End Date</label>
                            <input type="date" name="end_date" class="form-control"></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Department</label>
                            <select name="department_id" class="form-control">
                                <option value="">All departments</option>
                                <?php foreach ($departments as $d) { ?>
                                    <option value="<?php echo $d['departmentid']; ?>"><?php echo html_escape($d['name']); ?></option>
                                <?php } ?>
                            </select></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Venue</label>
                            <input type="text" name="venue" class="form-control"></div></div>
                    </div>
                    <div class="form-group"><label>Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea></div>
                    <input type="hidden" name="status" value="planned">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                </div>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
