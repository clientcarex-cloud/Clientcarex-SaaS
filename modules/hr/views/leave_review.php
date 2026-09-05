<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Leave review popup — loaded over AJAX into a .modal-content, so it renders
 * the header / body / footer itself and never calls init_head().
 *
 * Expects: $request, $review, $can_act, $action_url (prefix ending in "/"),
 *          $chain, $role_names
 */
$review      = isset($review) ? $review : [];
$chain       = isset($chain) ? $chain : [];
$role_names  = isset($role_names) ? $role_names : [];
$can_act     = isset($can_act) ? $can_act : false;
$cov         = $review['coverage'] ?? null;
$badge       = ['success' => '#16a34a', 'info' => '#0284c7', 'warning' => '#d97706', 'danger' => '#dc2626'];
$badge_bg    = ['success' => '#f0fdf4', 'info' => '#f0f9ff', 'warning' => '#fffbeb', 'danger' => '#fef2f2'];
$prio_label  = [1 => 'Low', 2 => 'Medium', 3 => 'High', 4 => 'Urgent'];
$card        = 'border:1px solid #e2e8f0;border-radius:10px;padding:12px;height:100%;';
$muted       = 'color:#64748b;font-size:12px;';
// Pro Tickets replaces the core ticket screen when it is active.
$CI            = &get_instance();
$pro_tickets   = $CI->app_modules->is_active('pro_tickets');
$ticket_module = $pro_tickets ? 'pro_tickets' : 'tickets';
?>
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    <h4 class="modal-title">
        <i class="fa fa-clipboard"></i> Review Leave —
        <?php echo html_escape($request['firstname'] . ' ' . $request['lastname']); ?>
    </h4>
</div>
<div class="modal-body" style="max-height:70vh;overflow-y:auto;">

    <!-- ------------------------------------------------- request summary -->
    <div style="<?php echo $card; ?>background:#f8fafc;margin-bottom:14px;">
        <div class="row">
            <div class="col-md-8">
                <div style="font-size:14px;">
                    <span class="label" style="background:<?php echo html_escape($request['type_color'] ?? '#64748b'); ?>;color:#fff;"><?php echo html_escape($request['type_code'] ?? ''); ?></span>
                    <strong><?php echo html_escape($request['type_name']); ?></strong>
                    <?php echo empty($request['is_paid']) ? ' <span class="text-muted">(unpaid)</span>' : ''; ?>
                </div>
                <div style="<?php echo $muted; ?>margin-top:5px;">
                    <i class="fa fa-calendar"></i>
                    <?php echo _d($request['from_date']); ?> &rarr; <?php echo _d($request['to_date']); ?>
                    · <?php echo (float) $request['days']; ?> day(s)<?php echo !empty($request['is_half_day']) ? ' (half day)' : ''; ?>
                    <?php if (!empty($request['created_at'])) { ?>
                        · applied <?php echo time_ago($request['created_at']); ?>
                    <?php } ?>
                </div>
                <?php if (!empty($request['reason'])) { ?>
                    <div style="margin-top:8px;font-size:13px;color:#334155;">
                        <span style="<?php echo $muted; ?>">Reason:</span><br>
                        <?php echo nl2br(html_escape($request['reason'])); ?>
                    </div>
                <?php } ?>
                <?php if (!empty($request['proof_file'])) { ?>
                    <div style="margin-top:6px;">
                        <a href="<?php echo html_escape($proof_url); ?>" target="_blank" style="font-size:12px;">
                            <i class="fa fa-paperclip"></i> Proof of reason
                        </a>
                    </div>
                <?php } ?>
            </div>
            <div class="col-md-4 text-right">
                <?php if (!empty($chain) && (int) $request['current_level'] >= 1) { ?>
                    <span class="label label-warning">
                        Level <?php echo (int) $request['current_level']; ?>/<?php echo count($chain); ?>
                        — <?php echo html_escape(hr_leave_level_label($chain[(int) $request['current_level'] - 1] ?? '', $role_names)); ?>
                    </span>
                <?php } ?>
                <?php if (!empty($review['shift'])) { ?>
                    <div style="<?php echo $muted; ?>margin-top:8px;">
                        <i class="fa fa-clock-o"></i> Shift: <?php echo html_escape($review['shift']['name']); ?>
                        (<?php echo date('h:i A', strtotime($review['shift']['start_time'])); ?>
                        – <?php echo date('h:i A', strtotime($review['shift']['end_time'])); ?>)
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- --------------------------------------------------------- alerts -->
    <?php foreach ($review['alerts'] ?? [] as $a) {
        $lvl = $a['level']; ?>
        <div style="border-left:4px solid <?php echo $badge[$lvl] ?? '#64748b'; ?>;background:<?php echo $badge_bg[$lvl] ?? '#f8fafc'; ?>;padding:9px 12px;border-radius:6px;margin-bottom:8px;font-size:13px;">
            <i class="fa <?php echo html_escape($a['icon']); ?>" style="color:<?php echo $badge[$lvl] ?? '#64748b'; ?>;"></i>
            <?php echo $a['text']; // built in the helper, already escaped where needed ?>
        </div>
    <?php } ?>

    <!-- ------------------------------------------------ workload figures -->
    <div class="row" style="margin-top:12px;">
        <div class="col-md-3" style="margin-bottom:12px;">
            <div style="<?php echo $card; ?>">
                <div style="<?php echo $muted; ?>"><i class="fa fa-check-square-o"></i> TODO TASKS</div>
                <?php if ($review['todo'] === null) { ?>
                    <div class="text-muted" style="font-size:12px;margin-top:6px;">Module not available</div>
                <?php } else { ?>
                    <div style="font-size:24px;font-weight:600;"><?php echo (int) $review['todo']['total']; ?></div>
                    <div style="<?php echo $muted; ?>">
                        <?php echo (int) $review['todo']['in_progress']; ?> in progress ·
                        <span style="color:#dc2626;"><?php echo (int) $review['todo']['overdue']; ?> overdue</span><br>
                        <?php echo (int) $review['todo']['due_in_window']; ?> due during leave
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="col-md-3" style="margin-bottom:12px;">
            <div style="<?php echo $card; ?>">
                <div style="<?php echo $muted; ?>"><i class="fa fa-life-ring"></i> OPEN TICKETS</div>
                <?php if ($review['tickets'] === null) { ?>
                    <div class="text-muted" style="font-size:12px;margin-top:6px;">Module not available</div>
                <?php } else { ?>
                    <div style="font-size:24px;font-weight:600;"><?php echo (int) $review['tickets']['total']; ?></div>
                    <div style="<?php echo $muted; ?>">
                        <?php echo (int) $review['tickets']['high']; ?> high priority ·
                        <span style="color:#dc2626;"><?php echo (int) $review['tickets']['breached']; ?> SLA breached</span><br>
                        <?php echo (int) $review['tickets']['due_in_window']; ?> due during leave
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="col-md-3" style="margin-bottom:12px;">
            <div style="<?php echo $card; ?>">
                <div style="<?php echo $muted; ?>"><i class="fa fa-handshake-o"></i> SALES MEETINGS</div>
                <?php if ($review['meetings'] === null) { ?>
                    <div class="text-muted" style="font-size:12px;margin-top:6px;">Module not available</div>
                <?php } else { ?>
                    <div style="font-size:24px;font-weight:600;<?php echo count($review['meetings']['in_window']) ? 'color:#dc2626;' : ''; ?>">
                        <?php echo count($review['meetings']['in_window']); ?>
                    </div>
                    <div style="<?php echo $muted; ?>">
                        booked inside the leave<br>
                        <?php echo (int) $review['meetings']['upcoming']; ?> upcoming overall
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="col-md-3" style="margin-bottom:12px;">
            <div style="<?php echo $card; ?>">
                <div style="<?php echo $muted; ?>"><i class="fa fa-battery-three-quarters"></i> LEAVE BALANCE</div>
                <?php if (empty($review['balance'])) { ?>
                    <div class="text-muted" style="font-size:12px;margin-top:6px;">-</div>
                <?php } else { $b = $review['balance']; ?>
                    <div style="font-size:24px;font-weight:600;<?php echo $b['after'] < 0 ? 'color:#dc2626;' : ''; ?>">
                        <?php echo (float) $b['remaining']; ?>
                    </div>
                    <div style="<?php echo $muted; ?>">
                        day(s) left of <?php echo (float) ($b['allocated'] + $b['carried']); ?> (<?php echo (int) $b['year']; ?>)<br>
                        <?php echo (float) $b['after']; ?> after this request
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------- department coverage -->
    <?php if ($cov) { ?>
        <div style="<?php echo $card; ?>margin-bottom:14px;">
            <div style="font-weight:600;margin-bottom:8px;">
                <i class="fa fa-users"></i> Department coverage —
                <?php echo html_escape($cov['department_name']); ?>
                <span style="<?php echo $muted; ?>">(<?php echo (int) $cov['strength']; ?> active employee(s))</span>
            </div>
            <div class="table-responsive">
                <table class="table table-condensed" style="margin-bottom:0;font-size:12.5px;">
                    <thead>
                        <tr><th>Date</th><th style="width:120px;">Out / Strength</th><th>Also off that day</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cov['per_date'] as $date => $info) {
                            $hot = $info['percent'] >= 50 || count($info['off']) >= 2; ?>
                            <tr<?php echo $hot ? ' style="background:#fef2f2;"' : ''; ?>>
                                <td><?php echo _d($date); ?> <span style="<?php echo $muted; ?>"><?php echo date('D', strtotime($date)); ?></span></td>
                                <td>
                                    <span class="label label-<?php echo $hot ? 'danger' : ($info['out'] > 1 ? 'warning' : 'success'); ?>">
                                        <?php echo (int) $info['out']; ?>/<?php echo (int) $cov['strength']; ?>
                                        <?php echo $cov['strength'] ? ' · ' . (int) $info['percent'] . '%' : ''; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!count($info['off'])) { ?>
                                        <span class="text-muted">Only this employee</span>
                                    <?php } else {
                                        $bits = [];
                                        foreach ($info['off'] as $o) {
                                            $bits[] = '<strong>' . html_escape($o['name']) . '</strong> <span style="' . $muted . '">('
                                                . html_escape($o['type'])
                                                . ($o['status'] === 'pending' ? ', pending' : '') . ')</span>';
                                        }
                                        echo implode(', ', $bits);
                                    } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php } else { ?>
        <div style="<?php echo $muted; ?>margin-bottom:14px;">
            <i class="fa fa-info-circle"></i> No department is set on this employee's HR profile, so overlap with colleagues could not be checked.
        </div>
    <?php } ?>

    <!-- ------------------------------------------------ work in flight -->
    <div class="row">
        <?php if (!empty($review['todo']) && count($review['todo']['items'])) { ?>
            <div class="col-md-6" style="margin-bottom:12px;">
                <div style="<?php echo $card; ?>">
                    <div style="font-weight:600;margin-bottom:8px;"><i class="fa fa-check-square-o"></i> Open Todo tasks</div>
                    <?php foreach ($review['todo']['items'] as $t) { ?>
                        <div style="padding:5px 0;border-bottom:1px dashed #eef2f7;font-size:12.5px;">
                            <span class="label label-<?php echo (int) $t['priority'] >= 4 ? 'danger' : ((int) $t['priority'] === 3 ? 'warning' : 'default'); ?>" style="font-size:10px;">
                                <?php echo $prio_label[(int) $t['priority']] ?? 'Low'; ?>
                            </span>
                            <?php echo html_escape($t['title']); ?>
                            <?php if (!empty($t['category'])) { ?><span style="<?php echo $muted; ?>">· <?php echo html_escape($t['category']); ?></span><?php } ?>
                            <?php if (!empty($t['due_date'])) { ?>
                                <span style="<?php echo $muted; ?><?php echo !empty($t['is_overdue']) ? 'color:#dc2626;' : (!empty($t['is_in_window']) ? 'color:#d97706;' : ''); ?>">
                                    · due <?php echo _d($t['due_date']); ?><?php echo !empty($t['is_overdue']) ? ' (overdue)' : (!empty($t['is_in_window']) ? ' (during leave)' : ''); ?>
                                </span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <div style="margin-top:8px;"><a href="<?php echo admin_url('todo'); ?>" target="_blank" style="font-size:12px;">Open Todo <i class="fa fa-external-link"></i></a></div>
                </div>
            </div>
        <?php } ?>

        <?php if (!empty($review['tickets']) && count($review['tickets']['items'])) { ?>
            <div class="col-md-6" style="margin-bottom:12px;">
                <div style="<?php echo $card; ?>">
                    <div style="font-weight:600;margin-bottom:8px;"><i class="fa fa-life-ring"></i> Assigned tickets</div>
                    <?php foreach ($review['tickets']['items'] as $t) { ?>
                        <div style="padding:5px 0;border-bottom:1px dashed #eef2f7;font-size:12.5px;">
                            <a href="<?php echo admin_url($ticket_module . '/ticket/' . (int) $t['ticketid']); ?>" target="_blank">#<?php echo (int) $t['ticketid']; ?></a>
                            <?php echo html_escape($t['subject']); ?>
                            <span style="<?php echo $muted; ?>">· <?php echo html_escape($t['status_label']); ?><?php echo !empty($t['priority_name']) ? ' · ' . html_escape($t['priority_name']) : ''; ?></span>
                            <?php if (!empty($t['is_breached'])) { ?><span class="label label-danger" style="font-size:10px;">SLA breached</span><?php } ?>
                            <?php if (!empty($t['is_in_window'])) { ?><span class="label label-warning" style="font-size:10px;">Due <?php echo _dt($t['res_due']); ?></span><?php } ?>
                        </div>
                    <?php } ?>
                    <div style="margin-top:8px;"><a href="<?php echo admin_url($ticket_module); ?>" target="_blank" style="font-size:12px;">Open <?php echo $pro_tickets ? 'Pro Tickets' : 'Tickets'; ?> <i class="fa fa-external-link"></i></a></div>
                </div>
            </div>
        <?php } ?>

        <?php if (!empty($review['meetings']['in_window'])) { ?>
            <div class="col-md-6" style="margin-bottom:12px;">
                <div style="<?php echo $card; ?>border-color:#fecaca;">
                    <div style="font-weight:600;margin-bottom:8px;color:#dc2626;"><i class="fa fa-handshake-o"></i> Meetings inside the leave</div>
                    <?php foreach ($review['meetings']['in_window'] as $m) { ?>
                        <div style="padding:5px 0;border-bottom:1px dashed #eef2f7;font-size:12.5px;">
                            <strong><?php echo _dt($m['start_time']); ?></strong>
                            — <?php echo html_escape($m['subject']); ?>
                            <span style="<?php echo $muted; ?>">
                                <?php echo html_escape(trim($m['contact_name'] . ($m['company'] ? ' · ' . $m['company'] : ''))); ?>
                            </span>
                        </div>
                    <?php } ?>
                    <div style="margin-top:8px;"><a href="<?php echo admin_url('pro_sales'); ?>" target="_blank" style="font-size:12px;">Open Pro Sales <i class="fa fa-external-link"></i></a></div>
                </div>
            </div>
        <?php } ?>

        <?php if (count($review['trainings']) || count($review['interviews'])) { ?>
            <div class="col-md-6" style="margin-bottom:12px;">
                <div style="<?php echo $card; ?>">
                    <div style="font-weight:600;margin-bottom:8px;"><i class="fa fa-calendar-check-o"></i> Other commitments</div>
                    <?php foreach ($review['trainings'] as $t) { ?>
                        <div style="padding:5px 0;border-bottom:1px dashed #eef2f7;font-size:12.5px;">
                            <i class="fa fa-graduation-cap text-muted"></i> <?php echo html_escape($t['title']); ?>
                            <span style="<?php echo $muted; ?>">· <?php echo _d($t['start_date']); ?><?php echo $t['end_date'] && $t['end_date'] !== $t['start_date'] ? ' → ' . _d($t['end_date']) : ''; ?></span>
                        </div>
                    <?php } ?>
                    <?php foreach ($review['interviews'] as $iv) { ?>
                        <div style="padding:5px 0;border-bottom:1px dashed #eef2f7;font-size:12.5px;">
                            <i class="fa fa-user-plus text-muted"></i> Interview — <?php echo html_escape($iv['candidate_name']); ?>
                            <span style="<?php echo $muted; ?>">· <?php echo _dt($iv['scheduled_at']); ?><?php echo !empty($iv['position']) ? ' · ' . html_escape($iv['position']) : ''; ?></span>
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<?php echo form_open($action_url . (int) $request['id'] . '/approved', ['id' => 'leave_review_form']); ?>
<div class="modal-footer" style="text-align:left;">
    <?php if ($can_act && $request['status'] === 'pending') { ?>
        <div class="form-group" style="margin-bottom:10px;">
            <label style="font-weight:400;font-size:12px;color:#64748b;">Note to the employee (optional)</label>
            <input type="text" name="note" class="form-control" maxlength="255" placeholder="e.g. approved — hand over tickets #120, #133 to Ramesh first">
        </div>
        <div class="text-right">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-danger"
                    formaction="<?php echo $action_url . (int) $request['id'] . '/rejected'; ?>"
                    onclick="return confirm('Reject this leave request?');">
                <i class="fa fa-times"></i> Reject
            </button>
            <button type="submit" class="btn btn-success">
                <i class="fa fa-check"></i> Approve<?php echo !empty($chain) ? ' this level' : ''; ?>
            </button>
        </div>
    <?php } else { ?>
        <div class="text-right">
            <span class="text-muted pull-left" style="font-size:12px;padding-top:7px;">
                <?php echo $request['status'] === 'pending' ? 'This level is waiting on another approver.' : 'This request is already ' . html_escape($request['status']) . '.'; ?>
            </span>
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        </div>
    <?php } ?>
</div>
<?php echo form_close(); ?>
