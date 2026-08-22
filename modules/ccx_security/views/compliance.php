<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                    <h4 class="tw-font-bold tw-text-lg tw-flex tw-items-center tw-gap-2">
                        <i class="fa fa-check-square" style="color:#0ea5e9;"></i>
                        <?php echo _l('ccx_security_compliance'); ?>
                        <span class="label" style="background:<?php echo (!empty($is_tenant)) ? '#6366f1' : '#10b981'; ?>;color:#fff;font-size:11px;padding:4px 12px;border-radius:20px;margin-left:8px;font-weight:500;">
                            <i class="fa <?php echo (!empty($is_tenant)) ? 'fa-building' : 'fa-server'; ?>" style="margin-right:4px;"></i>
                            <?php echo htmlspecialchars($tenant_name); ?>
                        </span>
                    </h4>
                    <div>
                        <a href="<?php echo admin_url('ccx_security'); ?>" class="btn btn-default btn-sm">
                            <i class="fa fa-arrow-left"></i> Dashboard
                        </a>
                        <button onclick="window.print()" class="btn btn-primary btn-sm">
                            <i class="fa fa-print"></i> Print Report
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Overall Score + Category Breakdown ─── -->
        <div class="row">
            <div class="col-md-3">
                <div class="panel_s" style="text-align:center; padding:30px; border-top:3px solid <?php echo $score_info['color']; ?>;">
                    <div style="margin:0 auto 15px;">
                        <svg viewBox="0 0 120 120" width="100" height="100">
                            <circle cx="60" cy="60" r="52" fill="none" stroke="#e5e7eb" stroke-width="10"/>
                            <circle cx="60" cy="60" r="52" fill="none" stroke="<?php echo $score_info['color']; ?>" stroke-width="10"
                                stroke-dasharray="<?php echo (326.7 * $score / 100); ?> 326.7"
                                stroke-linecap="round" transform="rotate(-90 60 60)"
                                style="transition: stroke-dasharray 1s ease;"/>
                            <text x="60" y="55" text-anchor="middle" font-size="28" font-weight="700" fill="<?php echo $score_info['color']; ?>"><?php echo $score; ?></text>
                            <text x="60" y="72" text-anchor="middle" font-size="11" fill="#64748b">/ 100</text>
                        </svg>
                    </div>
                    <h5 class="tw-font-semibold" style="color:<?php echo $score_info['color']; ?>;">
                        <?php echo $score_info['label']; ?>
                    </h5>
                    <small class="text-muted">Overall Security Score</small>
                </div>
            </div>

            <?php
            $cat_icons = [
                'OWASP Top 10' => ['fa-bug', '#ef4444'],
                'SOC 2'        => ['fa-building', '#3b82f6'],
                'HIPAA'        => ['fa-heartbeat', '#10b981'],
            ];
            foreach ($categories as $cat_name => $cat_data):
                $pct = $cat_data['total'] > 0 ? round(($cat_data['passed'] / $cat_data['total']) * 100) : 0;
                $cat_color = ($pct >= 80) ? '#10b981' : (($pct >= 50) ? '#f59e0b' : '#ef4444');
                $icon_data = $cat_icons[$cat_name] ?? ['fa-shield', '#64748b'];
            ?>
            <div class="col-md-3">
                <div class="panel_s" style="padding:25px; border-top:3px solid <?php echo $icon_data[1]; ?>;">
                    <div class="tw-flex tw-items-center tw-gap-3 tw-mb-3">
                        <div style="width:44px;height:44px;border-radius:12px;background:<?php echo $icon_data[1]; ?>15;display:flex;align-items:center;justify-content:center;">
                            <i class="fa <?php echo $icon_data[0]; ?>" style="font-size:20px;color:<?php echo $icon_data[1]; ?>;"></i>
                        </div>
                        <div>
                            <h5 class="tw-font-semibold tw-mb-0" style="font-size:14px;"><?php echo $cat_name; ?></h5>
                            <small class="text-muted"><?php echo $cat_data['passed']; ?>/<?php echo $cat_data['total']; ?> controls</small>
                        </div>
                    </div>
                    <div style="background:#f1f5f9;border-radius:6px;height:8px;overflow:hidden;">
                        <div style="height:100%;width:<?php echo $pct; ?>%;background:<?php echo $cat_color; ?>;border-radius:6px;transition:width 0.8s ease;"></div>
                    </div>
                    <div style="text-align:right;margin-top:4px;">
                        <small style="font-weight:600;color:<?php echo $cat_color; ?>;"><?php echo $pct; ?>%</small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ─── Checklist by Category ─── -->
        <?php
        $grouped = [];
        foreach ($checklist as $item) {
            $grouped[$item['category']][] = $item;
        }
        foreach ($grouped as $category => $items):
            $icon_data = $cat_icons[$category] ?? ['fa-shield', '#64748b'];
        ?>
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-font-semibold tw-mb-4" style="border-bottom:2px solid <?php echo $icon_data[1]; ?>;padding-bottom:10px;">
                            <i class="fa <?php echo $icon_data[0]; ?>" style="color:<?php echo $icon_data[1]; ?>;"></i>
                            <?php echo $category; ?> Compliance
                        </h4>
                        <div class="table-responsive">
                            <table class="table" style="font-size:13px; margin-bottom:0;">
                                <thead>
                                    <tr style="background:#f8fafc;">
                                        <th width="60">Status</th>
                                        <th width="120">ID</th>
                                        <th>Control</th>
                                        <th>Description</th>
                                        <th width="160">Required Features</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr style="<?php echo $item['passed'] ? '' : 'background:#fff7ed;'; ?>">
                                        <td>
                                            <?php if ($item['passed']): ?>
                                                <span style="display:inline-flex;width:28px;height:28px;border-radius:50%;background:#dcfce7;align-items:center;justify-content:center;">
                                                    <i class="fa fa-check" style="color:#16a34a;font-size:14px;"></i>
                                                </span>
                                            <?php else: ?>
                                                <span style="display:inline-flex;width:28px;height:28px;border-radius:50%;background:#fee2e2;align-items:center;justify-content:center;">
                                                    <i class="fa fa-times" style="color:#dc2626;font-size:14px;"></i>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <code style="font-size:11px;background:#f1f5f9;padding:2px 8px;border-radius:4px;font-weight:600;">
                                                <?php echo htmlspecialchars($item['id']); ?>
                                            </code>
                                        </td>
                                        <td class="tw-font-semibold" style="font-size:13px;">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </td>
                                        <td class="text-muted"><?php echo htmlspecialchars($item['desc']); ?></td>
                                        <td>
                                            <?php foreach ($item['features'] as $f): ?>
                                                <?php
                                                $is_on = ccx_security_is_enabled($f);
                                                $badge_style = $is_on
                                                    ? 'background:#dcfce7;color:#166534;'
                                                    : 'background:#fee2e2;color:#991b1b;';
                                                ?>
                                                <span class="label" style="<?php echo $badge_style; ?>font-size:10px;display:inline-block;margin:1px 0;">
                                                    <i class="fa <?php echo $is_on ? 'fa-check' : 'fa-times'; ?>"></i>
                                                    <?php echo str_replace('_', ' ', $f); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ─── Report Footer ─── -->
        <div class="row">
            <div class="col-md-12">
                <div style="text-align:center;padding:20px;color:#94a3b8;font-size:12px;">
                    <i class="fa fa-shield"></i> CCX Security Compliance Report — Generated <?php echo date('F j, Y \a\t g:i A'); ?>
                    <br/>This is an automated assessment. For complete compliance certification, consult a qualified auditor.
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .navbar, .btn, .onoffswitch, #settingsModal { display: none !important; }
    #wrapper { margin-left: 0 !important; }
    .panel_s { break-inside: avoid; box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
}
</style>

<?php init_tail(); ?>
