<?php defined('BASEPATH') or exit('No direct script access allowed');

$name    = trim(($employee['firstname'] ?? '') . ' ' . ($employee['lastname'] ?? ''));
$present = (int) ($counts['present'] ?? 0) + (int) ($counts['half_day'] ?? 0);
$latest  = $payslips[0] ?? null;
?>
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
            .hr-list-item{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px}
            .hr-list-item:last-child{border-bottom:none}
            .hr-alert{display:flex;align-items:flex-start;padding:12px 14px;border-radius:10px;margin-bottom:10px;font-size:13px;border:1px solid transparent}
            .hr-alert i{margin-right:10px;margin-top:2px}
            .hr-alert-info{background:#eff6ff;border-color:#bfdbfe;color:#1e40af}
            .hr-alert-success{background:#f0fdf4;border-color:#bbf7d0;color:#166534}
            .hr-alert-warning{background:#fefce8;border-color:#fde68a;color:#92400e}
            .hr-alert-danger{background:#fef2f2;border-color:#fecaca;color:#991b1b}
            .hr-hello{font-size:20px;font-weight:800;color:#1e293b}
            .hr-bal-bar{height:8px;border-radius:4px;background:#e2e8f0;overflow:hidden;margin-top:4px}
            .hr-bal-bar span{display:block;height:100%;border-radius:4px}
        </style>

        <div class="hr-card" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;">
            <div>
                <div class="hr-hello">Hello, <?php echo html_escape($name ?: 'there'); ?> 👋</div>
                <div class="text-muted">
                    <?php echo html_escape($employee['employee_code'] ?? ''); ?>
                    <?php if (!empty($shift['name'])) { ?> &middot; Shift: <?php echo html_escape($shift['name']); ?><?php } ?>
                    &middot; <?php echo date('l, d M Y'); ?>
                </div>
            </div>
            <div>
                <a href="<?php echo admin_url('hr/myhr/attendance'); ?>" class="btn btn-default"><i class="fa fa-calendar-check-o"></i> My Attendance</a>
                <?php if (hr_field_enabled()) { ?>
                    <a href="<?php echo admin_url('hr/myhr/field_punch'); ?>" class="btn btn-info" data-toggle="tooltip" title="Punch in / out while working outside the office"><i class="fa fa-location-dot"></i> Field Punch</a>
                <?php } ?>
                <a href="<?php echo admin_url('hr/myhr/feedback'); ?>" class="btn btn-default" data-toggle="tooltip" title="Send a suggestion or feedback to management"><i class="fa fa-bullhorn"></i> Feedback</a>
                <a href="<?php echo admin_url('hr/myhr/leaves'); ?>" class="btn btn-primary"><i class="fa fa-plane"></i> Apply Leave</a>
            </div>
        </div>

        <div class="row">
            <?php
            $cards = [
                ['t' => 'Present This Month', 'v' => $present, 'i' => 'fa-check-circle', 'bg' => '#f0fdf4', 'c' => '#16a34a', 'href' => admin_url('hr/myhr/attendance')],
                ['t' => 'Absent This Month', 'v' => (int) ($counts['absent'] ?? 0), 'i' => 'fa-times-circle', 'bg' => '#fef2f2', 'c' => '#dc2626', 'href' => admin_url('hr/myhr/attendance')],
                ['t' => 'On Leave This Month', 'v' => (int) ($counts['leave'] ?? 0), 'i' => 'fa-plane', 'bg' => '#f5f3ff', 'c' => '#7c3aed', 'href' => admin_url('hr/myhr/leaves')],
                ['t' => 'My Trainings', 'v' => count($trainings), 'i' => 'fa-book', 'bg' => '#ecfeff', 'c' => '#0891b2', 'href' => admin_url('hr/myhr/trainings')],
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

        <?php if (!empty($benefits)) { ?>
            <style>
                .ben-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:16px}
                .ben-tile{border:1px solid #eef2f7;border-radius:12px;padding:16px;background:#fff;transition:box-shadow .2s;display:flex;flex-direction:column}
                .ben-tile:hover{box-shadow:0 6px 22px rgba(0,0,0,.07)}
                .ben-head{display:flex;align-items:center;gap:12px;margin-bottom:8px}
                .ben-ic{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;flex-shrink:0}
                .ben-name{font-size:15px;font-weight:800;color:#1e293b;line-height:1.2}
                .ben-val{font-size:12px;font-weight:700;color:#4338ca}
                .ben-desc{font-size:12.5px;color:#64748b;min-height:34px}
                .ben-prog-label{font-size:12px;font-weight:600;color:#334155;margin:10px 0 5px}
                .ben-bar{height:9px;border-radius:6px;background:#e2e8f0;overflow:hidden}
                .ben-bar span{display:block;height:100%;border-radius:6px;background:linear-gradient(90deg,#818cf8,#6366f1)}
                .ben-eligible{font-size:11.5px;color:#94a3b8;margin-top:5px}
                .ben-vested{font-size:12px;font-weight:700;color:#16a34a;margin-top:8px}
            </style>
            <div class="hr-card">
                <h4 style="margin:0 0 4px;"><i class="fa fa-gift" style="color:#6366f1;"></i> My Benefits</h4>
                <p class="text-muted" style="font-size:12.5px;margin-bottom:16px;">Perks and benefits available to you. Time-based benefits show your progress toward eligibility.</p>
                <div class="ben-grid">
                    <?php foreach ($benefits as $b) {
                        $p = $b['progress'] ?? null; ?>
                        <div class="ben-tile">
                            <div class="ben-head">
                                <div class="ben-ic" style="background:<?php echo html_escape($b['color']); ?>;"><i class="fa <?php echo html_escape(hr_normalize_icon($b['icon'])); ?>"></i></div>
                                <div>
                                    <div class="ben-name"><?php echo html_escape($b['name']); ?></div>
                                    <?php if (!empty($b['value_label'])) { ?><div class="ben-val"><?php echo html_escape($b['value_label']); ?></div><?php } ?>
                                </div>
                            </div>
                            <div class="ben-desc"><?php echo html_escape($b['description']); ?></div>
                            <?php if ($p) {
                                $pct = (int) round($p['progress']); ?>
                                <div style="margin-top:auto;">
                                    <?php if ($p['vested']) { ?>
                                        <div class="ben-vested"><i class="fa fa-check-circle"></i> Vested &middot; eligible since <?php echo _d($p['eligible_date']); ?></div>
                                        <div class="ben-bar"><span style="width:100%;background:linear-gradient(90deg,#22c55e,#16a34a);"></span></div>
                                    <?php } else { ?>
                                        <div class="ben-prog-label">
                                            <?php echo number_format($p['tenure_years'], 1); ?> / <?php echo (float) $b['vesting_years']; ?> yrs of service
                                            <span class="pull-right"><?php echo $pct; ?>%</span>
                                        </div>
                                        <div class="ben-bar"><span style="width:<?php echo max(3, $pct); ?>%;background:linear-gradient(90deg,<?php echo html_escape($b['color']); ?>aa,<?php echo html_escape($b['color']); ?>);"></span></div>
                                        <div class="ben-eligible"><i class="fa fa-hourglass-half"></i> Eligible on <?php echo _d($p['eligible_date']); ?> &middot; <?php echo number_format($p['remaining_years'], 1); ?> yr to go</div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <div class="row">
            <div class="col-md-5">
                <div class="hr-card">
                    <h4><i class="fa fa-bell" style="color:#ca8a04;"></i> Alerts &amp; Reminders</h4>
                    <?php if (!count($alerts)) { ?>
                        <div class="hr-alert hr-alert-success"><i class="fa fa-check-circle"></i> You're all caught up. Nothing needs your attention.</div>
                    <?php }
                    foreach ($alerts as $a) { ?>
                        <div class="hr-alert hr-alert-<?php echo $a['level']; ?>">
                            <i class="fa <?php echo $a['icon']; ?>"></i><span><?php echo html_escape($a['text']); ?></span>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="col-md-4">
                <div class="hr-card">
                    <h4><i class="fa fa-plane" style="color:#7c3aed;"></i> Leave Balance</h4>
                    <?php if (!count($balances)) { echo '<p class="text-muted">No leave allocated yet.</p>'; }
                    foreach ($balances as $b) {
                        $pct = $b['allocated'] > 0 ? max(0, min(100, round($b['remaining'] / $b['allocated'] * 100))) : 0; ?>
                        <div style="margin-bottom:12px;">
                            <div style="display:flex;justify-content:space-between;font-size:13px;">
                                <span><?php echo html_escape($b['name']); ?></span>
                                <strong><?php echo (float) $b['remaining']; ?> / <?php echo (float) $b['allocated']; ?></strong>
                            </div>
                            <div class="hr-bal-bar"><span style="width:<?php echo $pct; ?>%;background:<?php echo html_escape($b['color'] ?: '#7c3aed'); ?>;"></span></div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="col-md-3">
                <div class="hr-card">
                    <h4><i class="fa fa-money-bill" style="color:#16a34a;"></i> Latest Payslip</h4>
                    <?php if ($latest) { ?>
                        <div class="text-muted"><?php echo date('F Y', mktime(0, 0, 0, $latest['month'], 1, $latest['year'])); ?></div>
                        <div style="font-size:24px;font-weight:800;color:#166534;margin:6px 0;"><?php echo app_format_money($latest['net_pay'], get_base_currency()); ?></div>
                        <a href="<?php echo admin_url('hr/myhr/payslip/' . $latest['id']); ?>" class="btn btn-default btn-block btn-sm">View Payslip</a>
                    <?php } else { ?>
                        <p class="text-muted">No payslips available yet.</p>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
