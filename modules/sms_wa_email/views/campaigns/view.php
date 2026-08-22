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
    .stat-total { background: #eff6ff; color: #1d4ed8; }
    .stat-success { background: #f0fdf4; color: #15803d; }
    .stat-failed { background: #fef2f2; color: #dc2626; }
    .stat-remaining { background: #fffbeb; color: #d97706; }
    .stat-processed { background: #f5f3ff; color: #7c3aed; }
    .info-row { display: flex; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
    .info-row:last-child { border-bottom: none; }
    .info-label { width: 160px; font-weight: 600; color: #6b7280; font-size: 13px; }
    .info-value { flex: 1; color: #1f2937; font-size: 13px; }
    .patient-table { max-height: 400px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; }
    .patient-table table { margin: 0; }
    .log-status-success { color: #15803d; font-weight: 600; }
    .log-status-failed { color: #dc2626; font-weight: 600; }
</style>

<div id="wrapper">
    <div class="content ccx-comm-page">
        <div class="row">
            <div class="col-md-12">
                <div class="ccx-page-header">
                    <a href="<?php echo admin_url('sms_wa_email/campaigns'); ?>" class="btn btn-default" style="border-radius: 10px; padding: 12px 16px;"><i class="fa fa-arrow-left"></i></a>
                    <?php
                        $icon_bg = 'linear-gradient(135deg, #f59e0b, #d97706)';
                        if ($campaign->status == 'completed') $icon_bg = 'linear-gradient(135deg, #10b981, #059669)';
                        elseif ($campaign->status == 'failed') $icon_bg = 'linear-gradient(135deg, #ef4444, #dc2626)';
                        elseif ($campaign->status == 'processing') $icon_bg = 'linear-gradient(135deg, #3b82f6, #2563eb)';
                    ?>
                    <div class="ccx-page-header-icon" style="background: <?php echo $icon_bg; ?>">
                        <i class="fa fa-bullhorn"></i>
                    </div>
                    <div>
                        <h4><?php echo htmlspecialchars($campaign->name); ?></h4>
                        <p>Campaign #<?php echo $campaign->id; ?> &middot;
                            <?php
                                $status_labels = [
                                    'pending' => '<span class="label label-warning">Pending</span>',
                                    'processing' => '<span class="label label-info">Processing</span>',
                                    'completed' => '<span class="label label-success">Completed</span>',
                                    'failed' => '<span class="label label-danger">Failed</span>',
                                    'cancelled' => '<span class="label label-default">Cancelled</span>',
                                ];
                                echo isset($status_labels[$campaign->status]) ? $status_labels[$campaign->status] : $campaign->status;
                            ?>
                        </p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row" style="margin-bottom: 24px;">
                    <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:12px;">
                        <div class="stat-card stat-total">
                            <div class="stat-number"><?php echo $stats['total']; ?></div>
                            <div class="stat-label">Total Targets</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:12px;">
                        <div class="stat-card stat-processed">
                            <div class="stat-number"><?php echo $stats['processed']; ?></div>
                            <div class="stat-label">Processed</div>
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
                            <div class="stat-number"><?php echo $stats['remaining']; ?></div>
                            <div class="stat-label">Remaining</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:12px;">
                        <?php
                            $rate = ($stats['total'] > 0) ? round(($stats['success'] / $stats['total']) * 100) : 0;
                        ?>
                        <div class="stat-card" style="background: #f0f9ff; color: #0369a1;">
                            <div class="stat-number"><?php echo $rate; ?>%</div>
                            <div class="stat-label">Success Rate</div>
                        </div>
                    </div>
                </div>

                <!-- Campaign Info -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="ccx-main-card">
                            <h4 style="margin-top:0; font-weight:700; margin-bottom:16px;"><i class="fa fa-info-circle"></i> Campaign Details</h4>
                            <div class="info-row">
                                <div class="info-label">Channel</div>
                                <div class="info-value">
                                    <?php
                                        $ch = $campaign->channel;
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
                                <div class="info-label">Scheduled</div>
                                <div class="info-value">
                                    <?php echo date('d M Y, h:i A', strtotime($campaign->schedule_date)); ?>
                                    <?php
                                        $sts = strtotime($campaign->schedule_date);
                                        if ($campaign->status == 'pending') {
                                            if ($sts > time()) {
                                                $diff = $sts - time();
                                                if ($diff < 60) $tl = $diff . 's';
                                                elseif ($diff < 3600) $tl = round($diff / 60) . 'm';
                                                elseif ($diff < 86400) $tl = round($diff / 3600, 1) . 'h';
                                                else $tl = round($diff / 86400) . 'd';
                                                echo ' <span class="label label-info"><i class="fa fa-clock-o"></i> ' . $tl . ' left</span>';
                                            } else {
                                                echo ' <span class="label label-warning"><i class="fa fa-exclamation-triangle"></i> Overdue</span>';
                                            }
                                        }
                                    ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Created By</div>
                                <div class="info-value"><?php echo get_staff_full_name($campaign->created_by); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Created At</div>
                                <div class="info-value"><?php echo date('d M Y, h:i A', strtotime($campaign->created_at)); ?></div>
                            </div>
                            <?php
                                $filters = isset($filters) ? $filters : json_decode($campaign->filters_json, true);
                                if (!is_array($filters)) $filters = [];
                                
                                // Build human-readable filter tags
                                $filter_tags = [];
                                
                                // Patient Status
                                if (isset($filters['status']) && $filters['status'] !== '') {
                                    $status_map = ['1' => 'Active Only', '0' => 'Inactive Only'];
                                    $filter_tags[] = ['label' => 'Status', 'value' => isset($status_map[$filters['status']]) ? $status_map[$filters['status']] : $filters['status'], 'color' => '#6366f1'];
                                }
                                
                                // Gender
                                if (!empty($filters['gender'])) {
                                    $filter_tags[] = ['label' => 'Gender', 'value' => $filters['gender'], 'color' => '#ec4899'];
                                }
                                
                                // Age Range
                                if (!empty($filters['age_from']) || !empty($filters['age_to'])) {
                                    $age_str = '';
                                    if (!empty($filters['age_from']) && !empty($filters['age_to'])) {
                                        $age_str = $filters['age_from'] . ' – ' . $filters['age_to'] . ' yrs';
                                    } elseif (!empty($filters['age_from'])) {
                                        $age_str = $filters['age_from'] . '+ yrs';
                                    } else {
                                        $age_str = 'Up to ' . $filters['age_to'] . ' yrs';
                                    }
                                    $filter_tags[] = ['label' => 'Age', 'value' => $age_str, 'color' => '#f59e0b'];
                                }
                                
                                // Registration Date
                                if (!empty($filters['registered_from']) || !empty($filters['registered_to'])) {
                                    $reg_str = '';
                                    if (!empty($filters['registered_from']) && !empty($filters['registered_to'])) {
                                        $reg_str = $filters['registered_from'] . ' to ' . $filters['registered_to'];
                                    } elseif (!empty($filters['registered_from'])) {
                                        $reg_str = 'From ' . $filters['registered_from'];
                                    } else {
                                        $reg_str = 'Until ' . $filters['registered_to'];
                                    }
                                    $filter_tags[] = ['label' => 'Registered', 'value' => $reg_str, 'color' => '#0ea5e9'];
                                }
                                
                                // Visit Date
                                if (!empty($filters['visit_date_from']) || !empty($filters['visit_date_to'])) {
                                    $vd_str = '';
                                    if (!empty($filters['visit_date_from']) && !empty($filters['visit_date_to'])) {
                                        $vd_str = $filters['visit_date_from'] . ' to ' . $filters['visit_date_to'];
                                    } elseif (!empty($filters['visit_date_from'])) {
                                        $vd_str = 'From ' . $filters['visit_date_from'];
                                    } else {
                                        $vd_str = 'Until ' . $filters['visit_date_to'];
                                    }
                                    $filter_tags[] = ['label' => 'Visit Date', 'value' => $vd_str, 'color' => '#14b8a6'];
                                }
                                
                                // Visit Count
                                if (!empty($filters['visits_min']) || !empty($filters['visits_max'])) {
                                    $vc_str = '';
                                    if (!empty($filters['visits_min']) && !empty($filters['visits_max'])) {
                                        $vc_str = $filters['visits_min'] . ' – ' . $filters['visits_max'] . ' visits';
                                    } elseif (!empty($filters['visits_min'])) {
                                        $vc_str = $filters['visits_min'] . '+ visits';
                                    } else {
                                        $vc_str = 'Up to ' . $filters['visits_max'] . ' visits';
                                    }
                                    $filter_tags[] = ['label' => 'Visits', 'value' => $vc_str, 'color' => '#8b5cf6'];
                                }
                                
                                // Items / Tests
                                if (!empty($filters['items']) && is_array($filters['items'])) {
                                    $item_names = [];
                                    foreach ($filters['items'] as $item_id) {
                                        $item_names[] = isset($items_lookup[$item_id]) ? $items_lookup[$item_id] : '#' . $item_id;
                                    }
                                    $filter_tags[] = ['label' => 'Items', 'value' => implode(', ', $item_names), 'color' => '#10b981'];
                                }
                                
                                // Item Statuses
                                if (!empty($filters['item_statuses']) && is_array($filters['item_statuses'])) {
                                    $filter_tags[] = ['label' => 'Item Status', 'value' => implode(', ', $filters['item_statuses']), 'color' => '#f97316'];
                                }
                                
                                // Payment Statuses
                                if (!empty($filters['payment_statuses']) && is_array($filters['payment_statuses'])) {
                                    $pay_map = ['1' => 'Unpaid', '2' => 'Paid', '3' => 'Partially Paid', '4' => 'Overdue', '5' => 'Cancelled'];
                                    $pay_names = [];
                                    foreach ($filters['payment_statuses'] as $ps) {
                                        $pay_names[] = isset($pay_map[$ps]) ? $pay_map[$ps] : $ps;
                                    }
                                    $filter_tags[] = ['label' => 'Payment', 'value' => implode(', ', $pay_names), 'color' => '#ef4444'];
                                }
                                
                                // Excluded Patients
                                if (!empty($filters['excluded_patients']) && is_array($filters['excluded_patients'])) {
                                    $filter_tags[] = ['label' => 'Excluded', 'value' => count($filters['excluded_patients']) . ' patient(s)', 'color' => '#64748b'];
                                }
                            ?>
                            <?php if (!empty($filter_tags)): ?>
                            <div class="info-row" style="flex-wrap: wrap;">
                                <div class="info-label">Filters Applied</div>
                                <div class="info-value" style="display: flex; flex-wrap: wrap; gap: 6px;">
                                    <?php foreach ($filter_tags as $tag): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; background: <?php echo $tag['color']; ?>15; color: <?php echo $tag['color']; ?>; border: 1px solid <?php echo $tag['color']; ?>30; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                            <strong><?php echo $tag['label']; ?>:</strong> <?php echo htmlspecialchars($tag['value']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="info-row">
                                <div class="info-label">Filters Applied</div>
                                <div class="info-value"><span class="text-muted">Default (Active patients only)</span></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Target Patients -->
                    <div class="col-md-6">
                        <div class="ccx-main-card">
                            <h4 style="margin-top:0; font-weight:700; margin-bottom:16px;"><i class="fa fa-users"></i> Target Patients <span class="badge"><?php echo count($target_patients); ?></span></h4>
                            <div class="patient-table">
                                <table class="table table-condensed table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Patient Name</th>
                                            <th>MR No</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Registered</th>
                                            <th>Last Visit</th>
                                            <th>Visits</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($target_patients)): ?>
                                            <tr><td colspan="9" class="text-center text-muted">No patients found with current filters.</td></tr>
                                        <?php else: ?>
                                            <?php
                                                // Build a lookup from logs for quick status check
                                                $log_lookup = [];
                                                foreach ($logs as $log) {
                                                    $log_lookup[$log['patient_id']] = $log;
                                                }
                                            ?>
                                            <?php foreach ($target_patients as $i => $p): ?>
                                                <?php
                                                    $send_status = '—';
                                                    if (isset($log_lookup[$p['id']])) {
                                                        $ls = $log_lookup[$p['id']]['status'];
                                                        if ($ls === 'success') {
                                                            $send_status = '<span class="log-status-success"><i class="fa fa-check-circle"></i> Sent</span>';
                                                        } else {
                                                            $send_status = '<span class="log-status-failed"><i class="fa fa-times-circle"></i> Failed</span>';
                                                        }
                                                    } elseif ($campaign->status == 'pending') {
                                                        $send_status = '<span class="text-muted"><i class="fa fa-clock-o"></i> Queued</span>';
                                                    }
                                                ?>
                                                <tr>
                                                    <td><?php echo ($i + 1); ?></td>
                                                    <td><?php echo htmlspecialchars($p['name'] ?: 'Unknown'); ?></td>
                                                    <td><span class="label label-default"><?php echo htmlspecialchars(isset($p['mr_no']) && $p['mr_no'] ? $p['mr_no'] : '—'); ?></span></td>
                                                    <td><?php echo htmlspecialchars(isset($p['email']) && $p['email'] ? $p['email'] : '—'); ?></td>
                                                    <td><?php echo htmlspecialchars($p['phone'] ?: '—'); ?></td>
                                                    <td><small class="text-muted"><?php echo isset($p['datecreated']) && $p['datecreated'] ? time_ago($p['datecreated']) : '—'; ?></small></td>
                                                    <td><small class="text-muted"><?php echo isset($p['last_visit_date']) && $p['last_visit_date'] ? time_ago($p['last_visit_date']) : 'No visits'; ?></small></td>
                                                    <td><span class="badge"><?php echo isset($p['total_visits']) ? $p['total_visits'] : '0'; ?></span></td>
                                                    <td><?php echo $send_status; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logs Table -->
                <?php if (!empty($logs)): ?>
                <div class="ccx-main-card">
                    <h4 style="margin-top:0; font-weight:700; margin-bottom:16px;"><i class="fa fa-list-alt"></i> Send Logs</h4>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-condensed table-striped" style="margin:0;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Patient ID</th>
                                    <th>Recipient</th>
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
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
</body>
</html>
