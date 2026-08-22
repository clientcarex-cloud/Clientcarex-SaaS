<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
    .ccx-comm-page { font-family: 'Inter', sans-serif; }
    .ccx-page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
    .ccx-page-header-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; box-shadow: 0 4px 14px rgba(0,0,0,0.15); }
    .ccx-page-header h4 { margin: 0; font-weight: 700; font-size: 22px; color: #1e1b4b; letter-spacing: -0.3px; }
    .ccx-page-header p { margin: 2px 0 0; font-size: 13px; color: #6b7280; font-weight: 400; }
    .ccx-main-card { background: #ffffff; border-radius: 16px; border: 1px solid rgba(229, 231, 235, 0.7); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04), 0 10px 30px -5px rgba(0,0,0,0.06); padding: 24px; margin-bottom: 24px; }
    .stat-card { text-align: center; padding: 20px 12px; border-radius: 12px; }
    .stat-card .stat-number { font-size: 32px; font-weight: 800; line-height: 1; margin-bottom: 4px; }
    .stat-card .stat-label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .stat-total { background: #f5f3ff; color: #7c3aed; }
    .stat-success { background: #f0fdf4; color: #15803d; }
    .stat-failed { background: #fef2f2; color: #dc2626; }
    .stat-remaining { background: #fffbeb; color: #d97706; }
    .stat-targets { background: #eff6ff; color: #1d4ed8; }
    .info-row { display: flex; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
    .info-row:last-child { border-bottom: none; }
    .info-label { width: 160px; font-weight: 600; color: #6b7280; font-size: 13px; }
    .info-value { flex: 1; color: #1f2937; font-size: 13px; }
    .time-slot-badge { display: inline-block; background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; margin: 2px; }
    .log-status-success { color: #15803d; font-weight: 600; }
    .log-status-failed { color: #dc2626; font-weight: 600; }
    .toggle-active-btn { cursor: pointer; transition: all 0.2s; }
    .toggle-active-btn:hover { transform: scale(1.1); }
</style>

<div id="wrapper">
    <div class="content ccx-comm-page">
        <div class="row">
            <div class="col-md-12">
                <div class="ccx-page-header" style="justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <a href="<?php echo admin_url('sms_wa_email/auto_schedulers'); ?>" class="btn btn-default" style="border-radius: 10px; padding: 12px 16px;"><i class="fa fa-arrow-left"></i></a>
                        <?php
                            $icon_bg = $scheduler->is_active ? 'linear-gradient(135deg, #8b5cf6, #6d28d9)' : 'linear-gradient(135deg, #9ca3af, #6b7280)';
                        ?>
                        <div class="ccx-page-header-icon" style="background: <?php echo $icon_bg; ?>">
                            <i class="fa fa-refresh"></i>
                        </div>
                        <div>
                            <h4><?php echo htmlspecialchars($scheduler->name); ?></h4>
                            <p>
                                Auto Scheduler #<?php echo $scheduler->id; ?> &middot;
                                <?php if ($scheduler->is_active): ?>
                                    <span class="label label-success"><i class="fa fa-check"></i> Active</span>
                                <?php else: ?>
                                    <span class="label label-default"><i class="fa fa-pause"></i> Paused</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <a href="<?php echo admin_url('sms_wa_email/auto_schedulers/edit/' . $scheduler->id); ?>" class="btn btn-default" style="border-radius: 10px; padding: 9px 18px; font-weight: 600;">
                            <i class="fa fa-pencil"></i> Edit
                        </a>
                        <button type="button" class="btn btn-default toggle-active-btn" id="btn-toggle-active" style="border-radius: 10px; padding: 9px 18px; font-weight: 600;"
                            data-id="<?php echo $scheduler->id; ?>" data-active="<?php echo $scheduler->is_active; ?>">
                            <?php if ($scheduler->is_active): ?>
                                <i class="fa fa-pause"></i> Pause
                            <?php else: ?>
                                <i class="fa fa-play"></i> Activate
                            <?php endif; ?>
                        </button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row" style="margin-bottom: 24px;">
                    <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:12px;">
                        <div class="stat-card stat-targets">
                            <div class="stat-number"><?php echo $total_targets; ?></div>
                            <div class="stat-label">Total Targets</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:12px;">
                        <div class="stat-card stat-total">
                            <div class="stat-number"><?php echo $stats['total']; ?></div>
                            <div class="stat-label">Sent Today</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:12px;">
                        <div class="stat-card stat-success">
                            <div class="stat-number"><?php echo $stats['success']; ?></div>
                            <div class="stat-label">Success</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:12px;">
                        <div class="stat-card stat-failed">
                            <div class="stat-number"><?php echo $stats['failed']; ?></div>
                            <div class="stat-label">Failed</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:12px;">
                        <div class="stat-card stat-remaining">
                            <div class="stat-number"><?php echo $remaining; ?></div>
                            <div class="stat-label">Remaining</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:12px;">
                        <?php $rate = ($sent_today > 0) ? round(($stats['success'] / $sent_today) * 100) : 0; ?>
                        <div class="stat-card" style="background: #f0f9ff; color: #0369a1;">
                            <div class="stat-number"><?php echo $rate; ?>%</div>
                            <div class="stat-label">Success Rate</div>
                        </div>
                    </div>
                </div>

                <!-- Details + Upcoming Send -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="ccx-main-card">
                            <h4 style="margin-top:0; font-weight:700; margin-bottom:16px;"><i class="fa fa-info-circle"></i> Scheduler Details</h4>
                            <div class="info-row">
                                <div class="info-label">Channel</div>
                                <div class="info-value">
                                    <?php
                                        $ch = $scheduler->channel;
                                        if ($ch == 'sms') echo '<span class="label label-primary">SMS</span>';
                                        elseif (strpos($ch, 'whatsapp') !== false) echo '<span class="label label-success">WHATSAPP</span>';
                                        elseif ($ch == 'email') echo '<span class="label label-warning">EMAIL</span>';
                                        else echo '<span class="label label-default">' . strtoupper($ch) . '</span>';
                                    ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Template</div>
                                <div class="info-value"><?php echo $template ? htmlspecialchars($template->title) : '<span class="text-danger">Deleted / Not Found</span>'; ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Time Cycles</div>
                                <div class="info-value">
                                    <?php if (!empty($time_slots)): ?>
                                        <?php foreach ($time_slots as $slot): ?>
                                            <span class="time-slot-badge"><?php echo date('g:i A', strtotime('2000-01-01 ' . $slot)); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No time slots configured</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Last Run</div>
                                <div class="info-value">
                                    <?php echo $scheduler->last_run_at ? date('d M Y, h:i A', strtotime($scheduler->last_run_at)) : '<span class="text-muted">Never</span>'; ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Created By</div>
                                <div class="info-value"><?php echo get_staff_full_name($scheduler->created_by); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Created At</div>
                                <div class="info-value"><?php echo date('d M Y, h:i A', strtotime($scheduler->created_at)); ?></div>
                            </div>

                            <?php
                                // Build filter tags (same pattern as campaigns)
                                $filter_tags = [];
                                if (isset($filters['status']) && $filters['status'] !== '') {
                                    $status_map = ['1' => 'Active Only', '0' => 'Inactive Only'];
                                    $filter_tags[] = ['label' => 'Status', 'value' => isset($status_map[$filters['status']]) ? $status_map[$filters['status']] : $filters['status'], 'color' => '#6366f1'];
                                }
                                if (!empty($filters['gender'])) {
                                    $filter_tags[] = ['label' => 'Gender', 'value' => $filters['gender'], 'color' => '#ec4899'];
                                }
                                if (!empty($filters['age_from']) || !empty($filters['age_to'])) {
                                    $age_str = '';
                                    if (!empty($filters['age_from']) && !empty($filters['age_to'])) $age_str = $filters['age_from'] . ' – ' . $filters['age_to'] . ' yrs';
                                    elseif (!empty($filters['age_from'])) $age_str = $filters['age_from'] . '+ yrs';
                                    else $age_str = 'Up to ' . $filters['age_to'] . ' yrs';
                                    $filter_tags[] = ['label' => 'Age', 'value' => $age_str, 'color' => '#f59e0b'];
                                }
                                if (!empty($filters['items']) && is_array($filters['items'])) {
                                    $item_names = [];
                                    foreach ($filters['items'] as $item_id) {
                                        $item_names[] = isset($items_lookup[$item_id]) ? $items_lookup[$item_id] : '#' . $item_id;
                                    }
                                    $filter_tags[] = ['label' => 'Items', 'value' => implode(', ', $item_names), 'color' => '#10b981'];
                                }
                                if (!empty($filters['item_statuses']) && is_array($filters['item_statuses'])) {
                                    $filter_tags[] = ['label' => 'Item Status', 'value' => implode(', ', $filters['item_statuses']), 'color' => '#f97316'];
                                }
                                if (!empty($filters['payment_statuses']) && is_array($filters['payment_statuses'])) {
                                    $pay_map = ['1' => 'Unpaid', '2' => 'Paid', '3' => 'Partially Paid', '4' => 'Overdue', '5' => 'Cancelled'];
                                    $pay_names = [];
                                    foreach ($filters['payment_statuses'] as $ps) {
                                        $pay_names[] = isset($pay_map[$ps]) ? $pay_map[$ps] : $ps;
                                    }
                                    $filter_tags[] = ['label' => 'Payment', 'value' => implode(', ', $pay_names), 'color' => '#ef4444'];
                                }
                            ?>
                            <?php if (!empty($filter_tags)): ?>
                            <div class="info-row" style="flex-wrap: wrap;">
                                <div class="info-label">Filters</div>
                                <div class="info-value" style="display: flex; flex-wrap: wrap; gap: 6px;">
                                    <?php foreach ($filter_tags as $tag): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; background: <?php echo $tag['color']; ?>15; color: <?php echo $tag['color']; ?>; border: 1px solid <?php echo $tag['color']; ?>30; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                            <strong><?php echo $tag['label']; ?>:</strong> <?php echo htmlspecialchars($tag['value']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="ccx-main-card" style="position: relative; overflow: hidden;">
                            <?php if ($next_slot && $scheduler->is_active): ?>
                                <?php
                                    $next_formatted = date('g:i A', strtotime('2000-01-01 ' . $next_slot));
                                    $diff_hrs = floor($next_slot_diff_mins / 60);
                                    $diff_mins_rem = $next_slot_diff_mins % 60;
                                    if ($diff_hrs > 0) {
                                        $time_left_str = $diff_hrs . 'h ' . $diff_mins_rem . 'm';
                                    } else {
                                        $time_left_str = $diff_mins_rem . ' min';
                                    }
                                ?>
                                <!-- Decorative pulse dot -->
                                <div style="position: absolute; top: 20px; right: 20px; width: 12px; height: 12px; background: #22c55e; border-radius: 50%; animation: pulse 2s infinite;"></div>
                                <style>@keyframes pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(1.3); } }</style>

                                <h4 style="margin-top:0; font-weight:700; margin-bottom:4px;">
                                    <i class="fa fa-paper-plane" style="color: #8b5cf6;"></i> Upcoming Send
                                </h4>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
                                    <span style="font-size: 26px; font-weight: 800; color: #1e1b4b;"><?php echo $next_formatted; ?></span>
                                    <span id="countdown-badge" style="background: linear-gradient(135deg, #f97316, #ea580c); color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; animation: pulse 2s infinite;">
                                        <i class="fa fa-clock-o"></i> <?php echo $time_left_str; ?> left
                                    </span>
                                </div>

                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 16px; margin-bottom: 12px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span style="font-size: 13px; font-weight: 600; color: #475569;">
                                            <i class="fa fa-users" style="color: #8b5cf6; margin-right: 4px;"></i> Patients to receive
                                        </span>
                                        <span style="font-size: 22px; font-weight: 800; color: #7c3aed;"><?php echo count($upcoming_patients); ?></span>
                                    </div>
                                </div>

                                <?php if (!empty($upcoming_patients)): ?>
                                <div style="max-height: 260px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 10px;">
                                    <table class="table table-condensed" style="margin: 0;">
                                        <thead>
                                            <tr style="background: #f9fafb;">
                                                <th style="padding: 8px 12px; font-size: 11px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px;">#</th>
                                                <th style="padding: 8px 12px; font-size: 11px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px;">Patient Name</th>
                                                <th style="padding: 8px 12px; font-size: 11px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px;">Phone / Email</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcoming_patients as $idx => $p): ?>
                                            <tr>
                                                <td style="padding: 7px 12px; font-size: 12px; color: #9ca3af;"><?php echo ($idx + 1); ?></td>
                                                <td style="padding: 7px 12px; font-size: 13px; font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($p['name'] ?: 'Unknown'); ?></td>
                                                <td style="padding: 7px 12px; font-size: 13px; color: #475569;">
                                                    <i class="fa fa-phone" style="color: #10b981; font-size: 11px; margin-right: 3px;"></i>
                                                    <?php echo htmlspecialchars($p['phone'] ?: '—'); ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center" style="padding: 30px; color: #9ca3af;">
                                    <i class="fa fa-check-circle fa-2x" style="color: #22c55e; margin-bottom: 8px;"></i>
                                    <p style="margin: 0; font-size: 13px; font-weight: 500;">All matching patients already received messages today!</p>
                                </div>
                                <?php endif; ?>

                            <?php elseif (!$scheduler->is_active): ?>
                                <h4 style="margin-top:0; font-weight:700; margin-bottom:16px;">
                                    <i class="fa fa-paper-plane" style="color: #9ca3af;"></i> Upcoming Send
                                </h4>
                                <div class="text-center" style="padding: 40px; color: #9ca3af;">
                                    <i class="fa fa-pause-circle fa-3x" style="margin-bottom: 12px;"></i>
                                    <p style="margin: 0; font-weight: 600;">Scheduler is paused</p>
                                    <p style="margin: 4px 0 0; font-size: 12px;">Activate it to resume daily sends.</p>
                                </div>

                            <?php else: ?>
                                <h4 style="margin-top:0; font-weight:700; margin-bottom:16px;">
                                    <i class="fa fa-paper-plane" style="color: #22c55e;"></i> Upcoming Send
                                </h4>
                                <div class="text-center" style="padding: 40px; color: #9ca3af;">
                                    <i class="fa fa-check-circle fa-3x" style="color: #22c55e; margin-bottom: 12px;"></i>
                                    <p style="margin: 0; font-weight: 600; color: #15803d;">All cycles complete for today!</p>
                                    <p style="margin: 4px 0 0; font-size: 12px;">The scheduler will resume tomorrow with fresh cycles.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Logs Table -->
                <div class="ccx-main-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h4 style="margin:0; font-weight:700;">
                            <i class="fa fa-list-alt"></i> Send Logs
                            <small class="text-muted" style="font-weight:400;"> — <?php echo date('d M Y', strtotime($view_date)); ?></small>
                        </h4>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="input-group date" style="width: 180px;">
                                <input type="text" id="log-date-picker" class="form-control datepicker" value="<?php echo $view_date; ?>" style="border-radius: 8px 0 0 8px;">
                                <div class="input-group-addon"><i class="fa fa-calendar"></i></div>
                            </div>
                            <button type="button" class="btn btn-primary" id="btn-view-date" style="border-radius: 8px; font-weight: 600;">
                                <i class="fa fa-search"></i> View
                            </button>
                        </div>
                    </div>

                    <?php if (!empty($logs)): ?>
                    <div style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-condensed table-striped" style="margin:0;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Patient ID</th>
                                    <th>Recipient</th>
                                    <th>Time Slot</th>
                                    <th>Status</th>
                                    <th>Error</th>
                                    <th>Sent At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $i => $log): ?>
                                <tr>
                                    <td><?php echo ($i + 1); ?></td>
                                    <td><?php echo $log['patient_id']; ?></td>
                                    <td><?php echo htmlspecialchars($log['recipient'] ?: '—'); ?></td>
                                    <td>
                                        <span class="label label-info" style="font-size:11px;">
                                            <?php echo date('g:i A', strtotime('2000-01-01 ' . $log['time_slot'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($log['status'] === 'success'): ?>
                                            <span class="log-status-success"><i class="fa fa-check-circle"></i> Success</span>
                                        <?php else: ?>
                                            <span class="log-status-failed"><i class="fa fa-times-circle"></i> Failed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($log['error_message'] ?: '—'); ?></small></td>
                                    <td><small><?php echo isset($log['created_at']) ? date('d M Y, h:i A', strtotime($log['created_at'])) : '—'; ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted" style="padding: 40px;">
                        <i class="fa fa-inbox fa-3x" style="color: #d1d5db; margin-bottom: 12px;"></i>
                        <p style="font-size: 14px;">No logs found for <?php echo date('d M Y', strtotime($view_date)); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
    $(function(){
        // Date picker navigation
        $('#btn-view-date').on('click', function(){
            var dt = $('#log-date-picker').val();
            if (dt) {
                window.location.href = admin_url + 'sms_wa_email/auto_schedulers/view/<?php echo $scheduler->id; ?>?date=' + dt;
            }
        });

        // Toggle Active
        $('#btn-toggle-active').on('click', function(){
            var btn = $(this);
            var id = btn.data('id');
            var currentActive = btn.data('active');
            var newActive = currentActive == 1 ? 0 : 1;

            var postData = { is_active: newActive };
            if (typeof csrfData !== 'undefined') {
                postData[csrfData.token_name] = csrfData.hash;
            }

            $.post(admin_url + 'sms_wa_email/auto_schedulers/toggle_active/' + id, postData, function(res){
                try {
                    var data = JSON.parse(res);
                    if (data.success) {
                        location.reload();
                    }
                } catch(e) {}
            });
        });

        // ── Live Countdown Timer ──
        <?php if ($next_slot && $scheduler->is_active): ?>
        (function(){
            var diffSecs = <?php echo $next_slot_diff_mins; ?> * 60;
            var $badge = $('#countdown-badge');
            if (diffSecs <= 0) return;

            function updateCountdown(){
                if (diffSecs <= 0) {
                    $badge.html('<i class="fa fa-check"></i> Sending now...');
                    clearInterval(timer);
                    // Trigger processing then reload
                    triggerProcess(function(){ location.reload(); });
                    return;
                }
                var h = Math.floor(diffSecs / 3600);
                var m = Math.floor((diffSecs % 3600) / 60);
                var s = diffSecs % 60;
                var txt = '';
                if (h > 0) txt += h + 'h ';
                txt += m + 'm ' + s + 's left';
                $badge.html('<i class="fa fa-clock-o"></i> ' + txt);
                diffSecs--;
            }
            updateCountdown();
            var timer = setInterval(updateCountdown, 1000);
        })();
        <?php endif; ?>

        // ── Auto-Trigger Processing (every 60s while page is open) ──
        <?php if ($scheduler->is_active): ?>
        function triggerProcess(callback) {
            var postData = {};
            if (typeof csrfData !== 'undefined') {
                postData[csrfData.token_name] = csrfData.hash;
            }
            $.post(admin_url + 'sms_wa_email/auto_schedulers/process', postData, function(res){
                if (typeof callback === 'function') callback();
            });
        }
        setInterval(function(){ triggerProcess(); }, 60000);
        // Also trigger once on page load to catch any missed slots
        triggerProcess();
        <?php endif; ?>
    });
</script>
</body>
</html>
