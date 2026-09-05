<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_edit       = has_permission('hr_trainings', '', 'edit') || is_admin();
$status_labels  = ['planned' => 'info', 'ongoing' => 'warning', 'completed' => 'success', 'cancelled' => 'default'];
$att_ids        = array_column($attendees, 'staff_id');
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <a href="<?php echo admin_url('hr/trainings'); ?>" class="btn btn-default btn-sm mbot15"><i class="fa fa-arrow-left"></i> All Trainings</a>
                        <h4 class="bold"><?php echo html_escape($training['title']); ?>
                            <span class="label label-<?php echo $status_labels[$training['status']] ?? 'default'; ?>"><?php echo ucfirst($training['status']); ?></span></h4>

                        <?php echo form_open(admin_url('hr/save_training')); ?>
                        <input type="hidden" name="id" value="<?php echo $training['id']; ?>">
                        <div class="form-group"><label>Title</label>
                            <input type="text" name="title" class="form-control" value="<?php echo html_escape($training['title']); ?>" required <?php echo $can_edit ? '' : 'readonly'; ?>></div>
                        <div class="row">
                            <div class="col-md-6"><div class="form-group"><label>Category</label>
                                <input type="text" name="category" class="form-control" value="<?php echo html_escape($training['category']); ?>" <?php echo $can_edit ? '' : 'readonly'; ?>></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Trainer</label>
                                <input type="text" name="trainer" class="form-control" value="<?php echo html_escape($training['trainer']); ?>" <?php echo $can_edit ? '' : 'readonly'; ?>></div></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6"><div class="form-group"><label>Start Date</label>
                                <input type="date" name="start_date" class="form-control" value="<?php echo $training['start_date']; ?>" <?php echo $can_edit ? '' : 'readonly'; ?>></div></div>
                            <div class="col-md-6"><div class="form-group"><label>End Date</label>
                                <input type="date" name="end_date" class="form-control" value="<?php echo $training['end_date']; ?>" <?php echo $can_edit ? '' : 'readonly'; ?>></div></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6"><div class="form-group"><label>Department</label>
                                <select name="department_id" class="form-control" <?php echo $can_edit ? '' : 'disabled'; ?>>
                                    <option value="">All departments</option>
                                    <?php foreach ($departments as $d) { ?>
                                        <option value="<?php echo $d['departmentid']; ?>" <?php echo $training['department_id'] == $d['departmentid'] ? 'selected' : ''; ?>><?php echo html_escape($d['name']); ?></option>
                                    <?php } ?>
                                </select></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Venue</label>
                                <input type="text" name="venue" class="form-control" value="<?php echo html_escape($training['venue']); ?>" <?php echo $can_edit ? '' : 'readonly'; ?>></div></div>
                        </div>
                        <div class="form-group"><label>Status</label>
                            <select name="status" class="form-control" <?php echo $can_edit ? '' : 'disabled'; ?>>
                                <?php foreach (['planned', 'ongoing', 'completed', 'cancelled'] as $st) { ?>
                                    <option value="<?php echo $st; ?>" <?php echo $training['status'] === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                                <?php } ?>
                            </select></div>
                        <div class="form-group"><label>Description</label>
                            <textarea name="description" class="form-control" rows="3" <?php echo $can_edit ? '' : 'readonly'; ?>><?php echo html_escape($training['description']); ?></textarea></div>
                        <?php if ($can_edit) { ?>
                            <button type="submit" class="btn btn-primary">Save Training</button>
                        <?php } ?>
                        <?php echo form_close(); ?>
                    </div>
                </div>

                <?php if ($can_edit) { ?>
                    <div class="panel_s">
                        <div class="panel-body">
                            <h4 class="bold">Enroll Attendees</h4>
                            <?php echo form_open(admin_url('hr/save_training_attendees/' . $training['id'])); ?>
                            <div class="form-group">
                                <select name="staff_ids[]" class="selectpicker" data-width="100%" multiple data-live-search="true" data-actions-box="true">
                                    <?php foreach ($employees as $emp) { ?>
                                        <option value="<?php echo $emp['staffid']; ?>" <?php echo in_array($emp['staffid'], $att_ids) ? 'selected' : ''; ?>><?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Attendee List</button>
                            <?php echo form_close(); ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="bold">Attendees (<?php echo count($attendees); ?>)</h4>
                        <hr class="hr-panel-heading" />
                        <table class="table table-striped">
                            <thead><tr><th>Employee</th><th>Status</th><th>Score</th><th>Remarks</th><th class="text-right"></th></tr></thead>
                            <tbody>
                                <?php if (!count($attendees)) { ?>
                                    <tr><td colspan="5" class="text-muted">No attendees enrolled yet.</td></tr>
                                <?php }
                                foreach ($attendees as $a) {
                                    $fid = 'attendee_form_' . $a['id']; ?>
                                    <tr>
                                        <td><a href="<?php echo admin_url('hr/employee/' . $a['staff_id']); ?>"><?php echo html_escape($a['firstname'] . ' ' . $a['lastname']); ?></a></td>
                                        <td style="max-width:130px;">
                                            <select name="attendee_status" form="<?php echo $fid; ?>" class="form-control input-sm" <?php echo $can_edit ? '' : 'disabled'; ?>>
                                                <?php foreach (['enrolled' => 'Enrolled', 'attended' => 'Attended', 'completed' => 'Completed', 'absent' => 'Absent'] as $k => $v) { ?>
                                                    <option value="<?php echo $k; ?>" <?php echo $a['attendee_status'] === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td style="max-width:90px;"><input type="text" name="score" form="<?php echo $fid; ?>" class="form-control input-sm" value="<?php echo html_escape($a['score']); ?>" placeholder="Score" <?php echo $can_edit ? '' : 'readonly'; ?>></td>
                                        <td><input type="text" name="remarks" form="<?php echo $fid; ?>" class="form-control input-sm" value="<?php echo html_escape($a['remarks']); ?>" <?php echo $can_edit ? '' : 'readonly'; ?>></td>
                                        <td class="text-right">
                                            <?php if ($can_edit) { ?>
                                                <button type="submit" form="<?php echo $fid; ?>" class="btn btn-default btn-icon" data-toggle="tooltip" title="Save row"><i class="fa fa-check"></i></button>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                        <?php foreach ($attendees as $a) { ?>
                            <?php echo form_open(admin_url('hr/update_training_attendee/' . $a['id']), ['id' => 'attendee_form_' . $a['id']]); ?>
                            <input type="hidden" name="training_id" value="<?php echo $training['id']; ?>">
                            <?php echo form_close(); ?>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
