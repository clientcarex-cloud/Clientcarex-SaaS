<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <style>
            .hr-stat-card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.05);padding:18px;display:flex;align-items:center;border:1px solid rgba(0,0,0,.03);margin-bottom:20px}
            .hr-stat-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;margin-right:14px;flex-shrink:0}
            .hr-stat-title{font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px}
            .hr-stat-number{font-size:26px;font-weight:800;color:#1e293b;line-height:1.2}
            .hr-card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.05);border:1px solid rgba(0,0,0,.03);padding:20px;margin-bottom:20px}
            .hr-card h4{margin:0 0 15px;font-size:14px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.5px}
            .hr-dept-bar{height:8px;border-radius:4px;background:#e2e8f0;overflow:hidden;margin-top:3px}
            .hr-dept-bar span{display:block;height:100%;background:linear-gradient(90deg,#667eea,#764ba2);border-radius:4px}
            .hr-list-item{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px}
            .hr-list-item:last-child{border-bottom:none}
        </style>

        <?php if (!empty($birthdays_today)) {
            // Public = celebratory cards; private = the same cards, muted, HR only.
            $this->load->view('hr/_birthday_banner', [
                'bd_rows'    => $birthdays_today,
                'bd_me'      => (int) get_staff_user_id(),
                'bd_link'    => true,
                'bd_private' => empty($birthday_wishes_enabled),
            ]);
        } ?>

        <?php if (!empty($payroll_reminder)) {
            $pr  = $payroll_reminder;
            $sym = get_base_currency()->symbol;
            $when = $pr['days_until'] === 0 ? 'today' : ($pr['days_until'] === 1 ? 'tomorrow' : 'in ' . $pr['days_until'] . ' days');
        ?>
            <div class="hr-card" style="border-left:4px solid #ca8a04;background:linear-gradient(120deg,#fefce8,#fff);">
                <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;">
                    <div style="font-size:30px;color:#ca8a04;line-height:1;"><i class="fa fa-money"></i></div>
                    <div style="flex:1;min-width:220px;">
                        <div class="bold" style="font-size:16px;color:#854d0e;">Salary payment due <?php echo $when; ?> — <?php echo _d($pr['pay_date']); ?></div>
                        <div class="text-muted" style="font-size:13px;margin-top:2px;">Projected payroll for <?php echo date('F Y'); ?> across <?php echo (int) $pr['with_salary']; ?> employee(s) with a salary set.</div>
                        <div style="display:flex;gap:26px;flex-wrap:wrap;margin-top:12px;">
                            <div>
                                <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Gross</div>
                                <div style="font-size:19px;font-weight:800;color:#1e293b;"><?php echo $sym . ' ' . number_format($pr['total_gross'], 2); ?></div>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Deductions</div>
                                <div style="font-size:19px;font-weight:800;color:#dc2626;">− <?php echo $sym . ' ' . number_format($pr['total_deductions'], 2); ?></div>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Net Payable</div>
                                <div style="font-size:19px;font-weight:800;color:#15803d;"><?php echo $sym . ' ' . number_format($pr['total_net'], 2); ?></div>
                            </div>
                        </div>
                    </div>
                    <div style="align-self:center;">
                        <a href="<?php echo admin_url('hr/payroll'); ?>" class="btn btn-warning"><i class="fa fa-play"></i> Run Payroll</a>
                    </div>
                </div>
                <p class="text-muted" style="font-size:11px;margin:10px 0 0;"><i class="fa fa-info-circle"></i> Projected full-month figures (not prorated for attendance); the finalized amounts come from the Payroll run.</p>
            </div>
        <?php } ?>

        <div class="row">
            <?php
            $cards = [
                ['t' => 'Total Employees', 'v' => $stats['total_employees'], 'i' => 'fa-users', 'bg' => '#eff6ff', 'c' => '#2563eb', 'href' => admin_url('hr/employees')],
                ['t' => 'Present Today', 'v' => $stats['present_today'], 'i' => 'fa-check-circle', 'bg' => '#f0fdf4', 'c' => '#16a34a', 'href' => admin_url('hr/attendance?status=present')],
                ['t' => 'Absent Today', 'v' => $stats['absent_today'], 'i' => 'fa-times-circle', 'bg' => '#fef2f2', 'c' => '#dc2626', 'href' => admin_url('hr/attendance?status=absent')],
                ['t' => 'On Leave Today', 'v' => $stats['on_leave_today'], 'i' => 'fa-plane', 'bg' => '#f5f3ff', 'c' => '#7c3aed', 'href' => admin_url('hr/attendance?status=leave')],
                ['t' => 'Pending Leave Requests', 'v' => $stats['pending_leaves'], 'i' => 'fa-hourglass-half', 'bg' => '#fefce8', 'c' => '#ca8a04', 'href' => admin_url('hr/leaves?status=pending')],
                ['t' => 'New Joiners (30d)', 'v' => $stats['new_joiners'], 'i' => 'fa-user-plus', 'bg' => '#ecfeff', 'c' => '#0891b2', 'href' => admin_url('hr/employees?filter=new_joiners')],
                ['t' => 'On Probation', 'v' => $stats['on_probation'], 'i' => 'fa-graduation-cap', 'bg' => '#fdf2f8', 'c' => '#db2777', 'href' => admin_url('hr/employees?filter=probation')],
                ['t' => 'Open Exits', 'v' => $stats['pending_exits'], 'i' => 'fa-sign-out', 'bg' => '#f8fafc', 'c' => '#64748b', 'href' => admin_url('hr/exits?status=open')],
            ];
            foreach ($cards as $card) { ?>
                <div class="col-md-3 col-sm-6">
                    <a href="<?php echo $card['href']; ?>" style="text-decoration:none;">
                        <div class="hr-stat-card">
                            <div class="hr-stat-icon" style="background:<?php echo $card['bg']; ?>;color:<?php echo $card['c']; ?>;"><i class="fa <?php echo $card['i']; ?>"></i></div>
                            <div>
                                <div class="hr-stat-title"><?php echo $card['t']; ?></div>
                                <div class="hr-stat-number"><?php echo (int) $card['v']; ?></div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php } ?>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="hr-card">
                    <h4><i class="fa fa-sitemap" style="color:#667eea;"></i> Department Headcount</h4>
                    <?php $max = 1;
                    foreach ($departments as $d) { $max = max($max, $d['cnt']); }
                    if (!count($departments)) {
                        echo '<p class="text-muted">No employees yet. Use <a href="' . admin_url('hr/employees') . '">Employees &rarr; Sync from Staff</a> to get started.</p>';
                    }
                    foreach ($departments as $d) { ?>
                        <div style="margin-bottom:10px;">
                            <div style="display:flex;justify-content:space-between;font-size:13px;">
                                <span><?php echo html_escape($d['name']); ?></span>
                                <strong><?php echo (int) $d['cnt']; ?></strong>
                            </div>
                            <div class="hr-dept-bar"><span style="width:<?php echo round($d['cnt'] / $max * 100); ?>%;"></span></div>
                        </div>
                    <?php } ?>
                </div>

                <div class="hr-card">
                    <h4><i class="fa fa-birthday-cake" style="color:#db2777;"></i> Birthdays This Month</h4>
                    <?php if (!count($birthdays)) { echo '<p class="text-muted">No birthdays this month.</p>'; }
                    foreach ($birthdays as $b) { ?>
                        <div class="hr-list-item">
                            <span><a href="<?php echo admin_url('hr/employee/' . $b['staffid']); ?>"><?php echo html_escape($b['firstname'] . ' ' . $b['lastname']); ?></a></span>
                            <span class="text-muted"><?php echo date('d M', strtotime($b['date_of_birth'])); ?></span>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="col-md-4">
                <div class="hr-card">
                    <h4><i class="fa fa-hourglass-half" style="color:#ca8a04;"></i> Pending Leave Approvals</h4>
                    <?php if (!count($pending_leaves)) { echo '<p class="text-muted">Nothing pending. All caught up!</p>'; }
                    foreach (array_slice($pending_leaves, 0, 8) as $lr) { ?>
                        <div class="hr-list-item">
                            <span>
                                <a href="<?php echo admin_url('hr/leaves?status=pending'); ?>"><?php echo html_escape($lr['firstname'] . ' ' . $lr['lastname']); ?></a>
                                <span class="label" style="background:<?php echo html_escape($lr['type_color']); ?>;color:#fff;"><?php echo html_escape($lr['type_code']); ?></span>
                            </span>
                            <span class="text-muted"><?php echo _d($lr['from_date']); ?> &middot; <?php echo (float) $lr['days']; ?>d</span>
                        </div>
                    <?php } ?>
                    <?php if (count($pending_leaves) > 8) { ?>
                        <a href="<?php echo admin_url('hr/leaves?status=pending'); ?>" class="btn btn-default btn-xs mtop10">View all (<?php echo count($pending_leaves); ?>)</a>
                    <?php } ?>
                </div>

                <div class="hr-card">
                    <h4><i class="fa fa-user-plus" style="color:#0891b2;"></i> Recent Joiners</h4>
                    <?php if (!count($recent_joiners)) { echo '<p class="text-muted">No joining dates recorded yet.</p>'; }
                    foreach ($recent_joiners as $j) { ?>
                        <div class="hr-list-item">
                            <span><a href="<?php echo admin_url('hr/employee/' . $j['staffid']); ?>"><?php echo html_escape($j['firstname'] . ' ' . $j['lastname']); ?></a>
                                <span class="text-muted"><?php echo html_escape($j['employee_code']); ?></span></span>
                            <span class="text-muted"><?php echo _d($j['date_of_joining']); ?></span>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="col-md-4">
                <div class="hr-card">
                    <h4><i class="fa fa-exclamation-triangle" style="color:#dc2626;"></i> Documents Expiring (next <?php echo (int) get_option('hr_doc_expiry_alert_days'); ?> days)</h4>
                    <?php if (!count($expiring_docs)) { echo '<p class="text-muted">No documents expiring soon.</p>'; }
                    foreach ($expiring_docs as $doc) { ?>
                        <div class="hr-list-item">
                            <span>
                                <a href="<?php echo admin_url('hr/employee/' . $doc['staff_id'] . '?tab=documents'); ?>"><?php echo html_escape($doc['firstname'] . ' ' . $doc['lastname']); ?></a><br>
                                <span class="text-muted"><?php echo html_escape($doc['title']); ?><?php echo $doc['doc_type'] ? ' (' . html_escape($doc['doc_type']) . ')' : ''; ?></span>
                            </span>
                            <span class="label label-danger"><?php echo _d($doc['expiry_date']); ?></span>
                        </div>
                    <?php } ?>
                </div>

                <div class="hr-card">
                    <h4><i class="fa fa-bolt" style="color:#667eea;"></i> Quick Actions</h4>
                    <a href="<?php echo admin_url('hr/attendance'); ?>" class="btn btn-default btn-block text-left"><i class="fa fa-calendar-check-o mright5"></i> Mark Today's Attendance</a>
                    <a href="<?php echo admin_url('hr/leaves'); ?>" class="btn btn-default btn-block text-left"><i class="fa fa-plane mright5"></i> Apply / Approve Leave</a>
                    <?php if (has_permission('hr_payroll', '', 'view') || is_admin()) { ?>
                        <a href="<?php echo admin_url('hr/payroll'); ?>" class="btn btn-default btn-block text-left"><i class="fa fa-money-bill mright5"></i> Run Payroll</a>
                    <?php } ?>
                    <a href="<?php echo admin_url('hr/reports'); ?>" class="btn btn-default btn-block text-left"><i class="fa fa-bar-chart mright5"></i> HR Reports</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
