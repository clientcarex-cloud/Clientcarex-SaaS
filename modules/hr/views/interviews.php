<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_create = has_permission('hr_interviews', '', 'create') || is_admin();
$can_delete = has_permission('hr_interviews', '', 'delete') || is_admin();

$platforms = hr_interview_platforms();
$statuses  = hr_interview_statuses();
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                            <h4 class="bold" style="margin:0;"><i class="fa fa-video text-primary"></i> <?php echo _l('hr_interviews'); ?></h4>
                            <div>
                                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> <?php echo _l('back'); ?></a>
                                <?php if ($can_create) { ?>
                                    <button type="button" class="btn btn-primary" onclick="openIvModal()"><i class="fa fa-plus"></i> Schedule Interview</button>
                                <?php } ?>
                            </div>
                        </div>

                        <div style="margin:8px 0 0;">
                            <?php if ($zoom_ready) { ?><span class="label label-success"><i class="fa fa-check"></i> Zoom connected</span><?php } else { ?><span class="label label-default">Zoom not configured</span><?php } ?>
                            <?php if ($meet_ready) { ?><span class="label label-success"><i class="fa fa-check"></i> Google Meet connected</span><?php } else { ?><span class="label label-default">Google Meet not configured</span><?php } ?>
                            <a href="<?php echo admin_url('hr/settings'); ?>#interview-integrations" class="text-muted" style="font-size:12px;margin-left:6px;"><i class="fa fa-gear"></i> Configure</a>
                        </div>

                        <div style="display:flex;gap:8px;margin:12px 0;flex-wrap:wrap;">
                            <?php
                            $chip = function ($label, $count, $key, $active) {
                                $url = admin_url('hr/interviews' . ($key ? '?status=' . $key : ''));
                                echo '<a href="' . $url . '" class="label ' . ($active ? 'label-primary' : 'label-default') . '" style="padding:6px 12px;font-size:13px;">' . html_escape($label) . ' <b>' . (int) $count . '</b></a>';
                            };
                            $chip('All', array_sum($counts), '', $active_status === '' || $active_status === null);
                            foreach ($statuses as $sk => $sm) {
                                $chip($sm['label'], $counts[$sk] ?? 0, $sk, $active_status === $sk);
                            }
                            ?>
                        </div>

                        <hr class="hr-panel-heading" />
                        <table class="table table-striped dt-table">
                            <thead><tr><th>Candidate</th><th>Position</th><th>Round</th><th>When</th><th>Mode</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($interviews as $iv) {
                                    $pl = $platforms[$iv['platform']] ?? ['label' => $iv['platform'], 'icon' => 'fa-video'];
                                    $st = $statuses[$iv['status']] ?? $statuses['scheduled'];
                                ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo admin_url('hr/interview/' . $iv['id']); ?>" class="bold"><?php echo html_escape($iv['candidate_name']); ?></a>
                                            <?php if ($iv['candidate_email']) { ?><div class="text-muted" style="font-size:11px;"><?php echo html_escape($iv['candidate_email']); ?></div><?php } ?>
                                        </td>
                                        <td><?php echo html_escape($iv['position'] ?: ($iv['designation_name'] ?: '—')); ?></td>
                                        <td>#<?php echo (int) $iv['round_no']; ?><?php echo $iv['round_name'] ? ' <span class="text-muted">' . html_escape($iv['round_name']) . '</span>' : ''; ?></td>
                                        <td style="font-size:12px;"><?php echo $iv['scheduled_at'] ? _dt($iv['scheduled_at']) : '<span class="text-muted">TBD</span>'; ?></td>
                                        <td><i class="fa <?php echo $pl['icon']; ?>"></i> <?php echo html_escape($pl['label']); ?></td>
                                        <td><span class="label label-<?php echo $st['class']; ?>"><?php echo html_escape($st['label']); ?></span></td>
                                        <td class="text-right">
                                            <?php if (!empty($iv['meeting_url']) && $iv['status'] === 'scheduled') { ?>
                                                <a href="<?php echo html_escape($iv['meeting_url']); ?>" target="_blank" class="btn btn-success btn-icon" title="Join"><i class="fa fa-video"></i></a>
                                            <?php } ?>
                                            <a href="<?php echo admin_url('hr/interview/' . $iv['id']); ?>" class="btn btn-default btn-icon" title="View"><i class="fa fa-eye"></i></a>
                                            <?php if ($can_delete) { ?>
                                                <a href="<?php echo admin_url('hr/delete_interview/' . $iv['id']); ?>" class="btn btn-danger btn-icon _delete" title="Delete"><i class="fa fa-remove"></i></a>
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

    <?php if ($can_create) { ?>
    <div class="modal fade" id="iv_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <?php echo form_open_multipart(admin_url('hr/save_interview'), ['id' => 'iv_form']); ?>
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title"><i class="fa fa-video text-primary"></i> Schedule Interview</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Candidate name <small class="req text-danger">*</small></label>
                            <input type="text" name="candidate_name" class="form-control" maxlength="150" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Position applied for</label>
                            <input type="text" name="position" class="form-control" maxlength="150"></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Candidate email</label>
                            <input type="email" name="candidate_email" class="form-control" maxlength="150" placeholder="Used to email the invite"></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Candidate phone</label>
                            <input type="text" name="candidate_phone" class="form-control" maxlength="30"></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Department</label>
                            <select name="department_id" class="form-control">
                                <option value="">—</option>
                                <?php foreach ($departments as $d) { ?>
                                    <option value="<?php echo (int) $d['departmentid']; ?>"><?php echo html_escape($d['name']); ?></option>
                                <?php } ?>
                            </select></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Designation</label>
                            <select name="designation_id" class="form-control">
                                <option value="">—</option>
                                <?php foreach ($designations as $d) { ?>
                                    <option value="<?php echo (int) $d['id']; ?>"><?php echo html_escape($d['name']); ?></option>
                                <?php } ?>
                            </select></div></div>
                    </div>

                    <div class="row">
                        <div class="col-md-3"><div class="form-group"><label>Round #</label>
                            <input type="number" name="round_no" class="form-control" value="1" min="1"></div></div>
                        <div class="col-md-9"><div class="form-group"><label>Round name</label>
                            <input type="text" name="round_name" class="form-control" maxlength="100" placeholder="e.g. Technical, HR, Panel"></div></div>
                    </div>

                    <div class="form-group"><label>Interviewer(s)</label>
                        <select name="interviewer_ids[]" class="selectpicker" data-width="100%" data-live-search="true" multiple data-none-selected-text="Select interviewers">
                            <?php foreach ($employees as $e) { ?>
                                <option value="<?php echo (int) $e['staffid']; ?>"><?php echo html_escape(trim($e['firstname'] . ' ' . $e['lastname'])); ?></option>
                            <?php } ?>
                        </select></div>

                    <div class="row">
                        <div class="col-md-4"><div class="form-group"><label>Date <small class="req text-danger">*</small></label>
                            <input type="date" name="scheduled_date" class="form-control" required></div></div>
                        <div class="col-md-4"><div class="form-group"><label>Time <small class="req text-danger">*</small></label>
                            <input type="time" name="scheduled_time" class="form-control" required></div></div>
                        <div class="col-md-4"><div class="form-group"><label>Duration (min)</label>
                            <input type="number" name="duration_minutes" class="form-control" value="30" min="5" step="5"></div></div>
                    </div>

                    <div class="form-group"><label>Mode</label>
                        <select name="platform" id="iv_platform" class="form-control">
                            <?php foreach ($platforms as $pk => $pm) {
                                $note = '';
                                if ($pk === 'zoom') { $note = $zoom_ready ? ' — auto-generates link' : ' — not configured (paste link)'; }
                                if ($pk === 'google_meet') { $note = $meet_ready ? ' — auto-generates link' : ' — not configured (paste link)'; }
                            ?>
                                <option value="<?php echo $pk; ?>"><?php echo html_escape($pm['label'] . $note); ?></option>
                            <?php } ?>
                        </select></div>

                    <div class="form-group" id="iv_link_wrap">
                        <label>Meeting link <span class="text-muted" id="iv_link_hint" style="font-size:12px;"></span></label>
                        <input type="url" name="meeting_url" class="form-control" placeholder="https://...">
                    </div>
                    <div class="form-group" id="iv_location_wrap" style="display:none;">
                        <label>Location / room</label>
                        <input type="text" name="location" class="form-control" maxlength="191" placeholder="e.g. HR Cabin, 2nd floor">
                    </div>

                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Candidate resume</label>
                            <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Notes</label>
                            <input type="text" name="notes" class="form-control" placeholder="Internal notes"></div></div>
                    </div>
                    <input type="hidden" name="timezone" value="<?php echo html_escape(date_default_timezone_get()); ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-primary">Schedule &amp; Send Invite</button>
                </div>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
    <?php } ?>
</div>
<?php init_tail(); ?>
<script>
    var ivReady = { zoom: <?php echo $zoom_ready ? 'true' : 'false'; ?>, google_meet: <?php echo $meet_ready ? 'true' : 'false'; ?> };
    function ivToggle() {
        var p = $('#iv_platform').val();
        var online = (p === 'zoom' || p === 'google_meet');
        $('#iv_location_wrap').toggle(p === 'in_person');
        $('#iv_link_wrap').toggle(p !== 'phone');
        var hint = '';
        if (online) {
            hint = ivReady[p] ? '(leave blank to auto-generate)' : '(not configured — paste a link)';
        } else if (p === 'in_person') {
            hint = '(optional)';
        }
        $('#iv_link_hint').text(hint);
    }
    function openIvModal() {
        var f = $('#iv_form')[0];
        f.reset();
        $('#iv_form').find('.selectpicker').selectpicker('deselectAll').selectpicker('refresh');
        ivToggle();
        $('#iv_modal').modal('show');
    }
    $(function () { $('#iv_platform').on('change', ivToggle); });
</script>
