<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-6">
                    <h4 class="tw-font-bold tw-text-lg tw-flex tw-items-center tw-gap-2">
                        <i class="fa fa-shield text-success"></i>
                        <?php echo _l('ccx_security_dashboard'); ?>
                        <span class="label" style="background:<?php echo (!empty($is_tenant)) ? '#6366f1' : '#10b981'; ?>;color:#fff;font-size:11px;padding:4px 12px;border-radius:20px;margin-left:8px;font-weight:500;">
                            <i class="fa <?php echo (!empty($is_tenant)) ? 'fa-building' : 'fa-server'; ?>" style="margin-right:4px;"></i>
                            <?php echo htmlspecialchars($tenant_name); ?>
                        </span>
                    </h4>
                    <button type="button" class="btn btn-default" data-toggle="modal" data-target="#settingsModal">
                        <i class="fa fa-cog"></i> <?php echo _l('ccx_security_settings'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- ─── Security Score + Stats Row ─── -->
        <div class="row">
            <!-- Score Circle -->
            <div class="col-md-3 col-sm-6">
                <div class="panel_s" style="text-align:center; padding:30px 20px; border-top:3px solid <?php echo $score_info['color']; ?>;">
                    <div class="ccx-sec-score-ring" style="margin:0 auto 15px;">
                        <svg viewBox="0 0 120 120" width="120" height="120">
                            <circle cx="60" cy="60" r="52" fill="none" stroke="#e5e7eb" stroke-width="10"/>
                            <circle cx="60" cy="60" r="52" fill="none" stroke="<?php echo $score_info['color']; ?>" stroke-width="10"
                                stroke-dasharray="<?php echo (326.7 * $score / 100); ?> 326.7"
                                stroke-linecap="round" transform="rotate(-90 60 60)"
                                style="transition: stroke-dasharray 1s ease;"/>
                            <text x="60" y="55" text-anchor="middle" font-size="28" font-weight="700" fill="<?php echo $score_info['color']; ?>"><?php echo $score; ?></text>
                            <text x="60" y="72" text-anchor="middle" font-size="11" fill="#64748b">/ 100</text>
                        </svg>
                    </div>
                    <h5 class="tw-font-semibold" style="color:<?php echo $score_info['color']; ?>; margin:0;">
                        <?php echo $score_info['label']; ?>
                    </h5>
                    <small class="text-muted"><?php echo _l('ccx_security_score'); ?></small>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="col-md-3 col-sm-6">
                <div class="panel_s" style="padding:25px 20px; border-top:3px solid #ef4444;">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <div style="width:50px;height:50px;border-radius:12px;background:rgba(239,68,68,0.1);display:flex;align-items:center;justify-content:center;">
                            <i class="fa fa-ban" style="font-size:22px;color:#ef4444;"></i>
                        </div>
                        <div>
                            <h3 class="tw-font-bold tw-mb-0" style="color:#1e293b;"><?php echo $stats['blocked_ips']; ?></h3>
                            <small class="text-muted"><?php echo _l('ccx_security_total_blocked'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel_s" style="padding:25px 20px; border-top:3px solid #f59e0b;">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <div style="width:50px;height:50px;border-radius:12px;background:rgba(245,158,11,0.1);display:flex;align-items:center;justify-content:center;">
                            <i class="fa fa-exclamation-triangle" style="font-size:22px;color:#f59e0b;"></i>
                        </div>
                        <div>
                            <h3 class="tw-font-bold tw-mb-0" style="color:#1e293b;"><?php echo $stats['events_24h']; ?></h3>
                            <small class="text-muted"><?php echo _l('ccx_security_total_events'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel_s" style="padding:25px 20px; border-top:3px solid #ec4899;">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <div style="width:50px;height:50px;border-radius:12px;background:rgba(236,72,153,0.1);display:flex;align-items:center;justify-content:center;">
                            <i class="fa fa-user-times" style="font-size:22px;color:#ec4899;"></i>
                        </div>
                        <div>
                            <h3 class="tw-font-bold tw-mb-0" style="color:#1e293b;"><?php echo $stats['failed_logins']; ?></h3>
                            <small class="text-muted"><?php echo _l('ccx_security_failed_logins'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Enterprise Stats Row ─── -->
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="panel_s" style="padding:20px; border-top:3px solid #7c3aed;">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <div style="width:44px;height:44px;border-radius:10px;background:rgba(124,58,237,0.1);display:flex;align-items:center;justify-content:center;">
                            <i class="fa fa-desktop" style="font-size:18px;color:#7c3aed;"></i>
                        </div>
                        <div>
                            <h4 class="tw-font-bold tw-mb-0"><?php echo $stats['active_sessions']; ?></h4>
                            <small class="text-muted">Active Sessions</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel_s" style="padding:20px; border-top:3px solid #0ea5e9;">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <div style="width:44px;height:44px;border-radius:10px;background:rgba(14,165,233,0.1);display:flex;align-items:center;justify-content:center;">
                            <i class="fa fa-shield" style="font-size:18px;color:#0ea5e9;"></i>
                        </div>
                        <div>
                            <h4 class="tw-font-bold tw-mb-0"><?php echo $stats['staff_with_2fa']; ?>/<?php echo $stats['total_staff']; ?></h4>
                            <small class="text-muted">2FA Adoption</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel_s" style="padding:20px; border-top:3px solid #14b8a6;">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <div style="width:44px;height:44px;border-radius:10px;background:rgba(20,184,166,0.1);display:flex;align-items:center;justify-content:center;">
                            <i class="fa fa-map-marker" style="font-size:18px;color:#14b8a6;"></i>
                        </div>
                        <div>
                            <h4 class="tw-font-bold tw-mb-0"><?php echo $stats['whitelisted_ips']; ?></h4>
                            <small class="text-muted">Whitelisted IPs</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel_s" style="padding:20px; border-top:3px solid #e11d48;">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <div style="width:44px;height:44px;border-radius:10px;background:rgba(225,29,72,0.1);display:flex;align-items:center;justify-content:center;">
                            <i class="fa fa-exclamation-circle" style="font-size:18px;color:#e11d48;"></i>
                        </div>
                        <div>
                            <h4 class="tw-font-bold tw-mb-0"><?php echo $stats['critical_7d']; ?></h4>
                            <small class="text-muted">Critical (7 days)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Feature Toggle Cards ─── -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-font-semibold tw-mb-4">
                            <i class="fa fa-sliders text-primary"></i>
                            <?php echo _l('ccx_security_enabled_features'); ?>
                            <small class="text-muted" style="font-weight:400;font-size:12px;margin-left:10px;">
                                <?php
                                $enabled_count = 0;
                                foreach ($features as $f) {
                                    if (ccx_security_is_enabled($f['key'])) $enabled_count++;
                                }
                                echo $enabled_count . '/' . count($features) . ' active';
                                ?>
                            </small>
                        </h4>
                        <div class="row">
                            <?php foreach ($features as $feature): ?>
                            <div class="col-md-4 col-sm-6" style="margin-bottom:20px;">
                                <div class="ccx-sec-feature-card" style="border-radius:12px; padding:20px; border:1px solid #e5e7eb; background:#fff; transition:all 0.2s; position:relative; overflow:hidden;">
                                    <div style="position:absolute;top:0;left:0;width:4px;height:100%;background:<?php echo $feature['color']; ?>;"></div>
                                    <div class="tw-flex tw-items-start tw-justify-between">
                                        <div class="tw-flex tw-items-center tw-gap-3">
                                            <div style="width:42px;height:42px;border-radius:10px;background:<?php echo $feature['color']; ?>15;display:flex;align-items:center;justify-content:center;">
                                                <i class="<?php echo $feature['icon']; ?>" style="font-size:18px;color:<?php echo $feature['color']; ?>;"></i>
                                            </div>
                                            <div>
                                                <h5 class="tw-font-semibold tw-mb-1" style="font-size:14px;"><?php echo $feature['name']; ?></h5>
                                                <p class="text-muted tw-mb-0" style="font-size:12px; line-height:1.4;"><?php echo $feature['desc']; ?></p>
                                            </div>
                                        </div>
                                        <div class="onoffswitch" style="margin-left:10px;">
                                            <input type="checkbox" class="onoffswitch-checkbox ccx-sec-toggle"
                                                id="toggle_<?php echo $feature['key']; ?>"
                                                data-key="<?php echo $feature['key']; ?>"
                                                <?php echo ccx_security_is_enabled($feature['key']) ? 'checked' : ''; ?>>
                                            <label class="onoffswitch-label" for="toggle_<?php echo $feature['key']; ?>"></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Quick Links ─── -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body" style="padding:15px 20px;">
                        <h4 class="tw-font-semibold tw-mb-3">
                            <i class="fa fa-th-large text-info"></i> Quick Access
                        </h4>
                        <div class="row">
                            <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:10px;">
                                <a href="<?php echo admin_url('ccx_security/audit_log'); ?>" class="ccx-quick-link">
                                    <i class="fa fa-list-alt" style="color:#3b82f6;"></i>
                                    <span>Audit Log</span>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:10px;">
                                <a href="<?php echo admin_url('ccx_security/blocked_ips'); ?>" class="ccx-quick-link">
                                    <i class="fa fa-ban" style="color:#ef4444;"></i>
                                    <span>Blocked IPs</span>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:10px;">
                                <a href="<?php echo admin_url('ccx_security/setup_2fa'); ?>" class="ccx-quick-link">
                                    <i class="fa fa-shield" style="color:#0ea5e9;"></i>
                                    <span>Setup 2FA</span>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:10px;">
                                <a href="<?php echo admin_url('ccx_security/ip_whitelist'); ?>" class="ccx-quick-link">
                                    <i class="fa fa-map-marker" style="color:#14b8a6;"></i>
                                    <span>IP Whitelist</span>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:10px;">
                                <a href="<?php echo admin_url('ccx_security/active_sessions'); ?>" class="ccx-quick-link">
                                    <i class="fa fa-desktop" style="color:#7c3aed;"></i>
                                    <span>Sessions</span>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:10px;">
                                <a href="<?php echo admin_url('ccx_security/compliance'); ?>" class="ccx-quick-link">
                                    <i class="fa fa-check-square" style="color:#10b981;"></i>
                                    <span>Compliance</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Recent Events ─── -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                            <h4 class="tw-font-semibold tw-mb-0">
                                <i class="fa fa-clock-o text-warning"></i>
                                <?php echo _l('ccx_security_recent_events'); ?>
                            </h4>
                            <div>
                                <a href="<?php echo admin_url('ccx_security/export_audit_log?format=csv'); ?>" class="btn btn-default btn-sm" title="Export CSV">
                                    <i class="fa fa-download"></i> CSV
                                </a>
                                <a href="<?php echo admin_url('ccx_security/export_audit_log?format=html'); ?>" class="btn btn-default btn-sm" title="Export Report">
                                    <i class="fa fa-file-text"></i> Report
                                </a>
                                <a href="<?php echo admin_url('ccx_security/audit_log'); ?>" class="btn btn-default btn-sm">
                                    View All <i class="fa fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" style="font-size:13px;">
                                <thead>
                                    <tr>
                                        <th width="100"><?php echo _l('ccx_security_event_severity'); ?></th>
                                        <th width="160"><?php echo _l('ccx_security_event_type'); ?></th>
                                        <th width="130">Tenant</th>
                                        <th><?php echo _l('ccx_security_event_description'); ?></th>
                                        <th width="130"><?php echo _l('ccx_security_event_ip'); ?></th>
                                        <th width="130"><?php echo _l('ccx_security_event_user'); ?></th>
                                        <th width="160"><?php echo _l('ccx_security_event_timestamp'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($stats['recent_events'])): ?>
                                        <?php foreach ($stats['recent_events'] as $event): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $sev_colors = ['info' => '#3b82f6', 'warning' => '#f59e0b', 'critical' => '#ef4444'];
                                                $sev_color = $sev_colors[$event->severity] ?? '#64748b';
                                                ?>
                                                <span class="label" style="background:<?php echo $sev_color; ?>; color:#fff; font-size:11px; padding:3px 8px; border-radius:4px;">
                                                    <?php echo ucfirst($event->severity); ?>
                                                </span>
                                            </td>
                                            <td><code style="font-size:12px;"><?php echo htmlspecialchars($event->event_type); ?></code></td>
                                            <td><?php echo htmlspecialchars($event->tenant_name ?? 'Master'); ?></td>
                                            <td style="max-width:350px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo htmlspecialchars($event->description); ?>">
                                                <?php echo htmlspecialchars($event->description); ?>
                                            </td>
                                            <td><code><?php echo htmlspecialchars($event->ip_address ?? ''); ?></code></td>
                                            <td><?php echo htmlspecialchars($event->staff_name ?? 'System'); ?></td>
                                            <td class="text-muted"><?php echo date('M d, Y H:i', strtotime($event->created_at)); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center text-muted" style="padding:30px;">No security events recorded yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ─── Settings Modal ─── -->
<div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <?php echo form_open(admin_url('ccx_security/save_settings')); ?>
            <div class="modal-header" style="border-bottom:2px solid #3b82f6;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-cog text-primary"></i> <?php echo _l('ccx_security_settings'); ?></h4>
            </div>
            <div class="modal-body" style="max-height:70vh; overflow-y:auto;">
                <!-- Global Enable -->
                <div class="form-group" style="background:#f0f9ff;padding:15px;border-radius:8px;border:1px solid #bae6fd;">
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="ccx_security_enabled" id="ccx_security_enabled" value="1"
                            <?php echo get_option('ccx_security_enabled') === '1' ? 'checked' : ''; ?>>
                        <label for="ccx_security_enabled"><strong>Enable CCX Security Module</strong></label>
                    </div>
                    <small class="text-muted">Master kill switch — disabling this turns off all security features.</small>
                </div>

                <hr>

                <!-- HTTP Headers -->
                <h5 class="tw-font-semibold"><i class="fa fa-globe text-primary"></i> HTTP Security Headers</h5>
                <div class="row">
                    <div class="col-md-6">
                        <?php echo render_select('ccx_security_x_frame_options', [
                            ['id' => 'SAMEORIGIN', 'name' => 'SAMEORIGIN'],
                            ['id' => 'DENY', 'name' => 'DENY'],
                        ], ['id', 'name'], _l('ccx_security_x_frame'), get_option('ccx_security_x_frame_options') ?: 'SAMEORIGIN'); ?>
                    </div>
                    <div class="col-md-6">
                        <?php echo render_select('ccx_security_referrer_policy', [
                            ['id' => 'strict-origin-when-cross-origin', 'name' => 'strict-origin-when-cross-origin'],
                            ['id' => 'no-referrer', 'name' => 'no-referrer'],
                            ['id' => 'same-origin', 'name' => 'same-origin'],
                            ['id' => 'origin', 'name' => 'origin'],
                        ], ['id', 'name'], _l('ccx_security_referrer_policy'), get_option('ccx_security_referrer_policy') ?: 'strict-origin-when-cross-origin'); ?>
                    </div>
                    <div class="col-md-6">
                        <?php echo render_select('ccx_security_csp_mode', [
                            ['id' => 'permissive', 'name' => _l('ccx_security_csp_permissive')],
                            ['id' => 'strict', 'name' => _l('ccx_security_csp_strict')],
                            ['id' => 'report_only', 'name' => _l('ccx_security_csp_report_only')],
                            ['id' => 'disabled', 'name' => _l('ccx_security_csp_disabled')],
                        ], ['id', 'name'], _l('ccx_security_csp_mode'), get_option('ccx_security_csp_mode') ?: 'permissive'); ?>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group" style="margin-top:26px;">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" name="ccx_security_hsts_enabled" id="ccx_security_hsts_enabled" value="1"
                                    <?php echo get_option('ccx_security_hsts_enabled') === '1' ? 'checked' : ''; ?>>
                                <label for="ccx_security_hsts_enabled"><?php echo _l('ccx_security_hsts'); ?></label>
                            </div>
                            <small class="text-danger">⚠ Only enable if your site uses HTTPS</small>
                        </div>
                    </div>
                </div>

                <hr>

                <!-- Brute Force -->
                <h5 class="tw-font-semibold"><i class="fa fa-user-secret text-danger"></i> Brute Force Protection</h5>
                <div class="row">
                    <div class="col-md-4">
                        <?php echo render_input('ccx_security_bf_max_attempts', _l('ccx_security_bf_max_attempts'),
                            get_option('ccx_security_bf_max_attempts') ?: '5', 'number'); ?>
                    </div>
                    <div class="col-md-4">
                        <?php echo render_input('ccx_security_bf_lockout_minutes', _l('ccx_security_bf_lockout_minutes'),
                            get_option('ccx_security_bf_lockout_minutes') ?: '15', 'number'); ?>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group" style="margin-top:26px;">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" name="ccx_security_bf_notify_admin" id="ccx_security_bf_notify_admin" value="1"
                                    <?php echo get_option('ccx_security_bf_notify_admin') === '1' ? 'checked' : ''; ?>>
                                <label for="ccx_security_bf_notify_admin"><?php echo _l('ccx_security_bf_notify_admin'); ?></label>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <!-- Password Policy (NEW) -->
                <h5 class="tw-font-semibold"><i class="fa fa-asterisk" style="color:#e11d48;"></i> Password Policy</h5>
                <div class="row">
                    <div class="col-md-4">
                        <?php echo render_input('ccx_security_pw_min_length', 'Minimum Length',
                            get_option('ccx_security_pw_min_length') ?: '12', 'number'); ?>
                    </div>
                    <div class="col-md-4">
                        <?php echo render_input('ccx_security_pw_expiry_days', 'Password Expiry (days)',
                            get_option('ccx_security_pw_expiry_days') ?: '90', 'number'); ?>
                    </div>
                    <div class="col-md-4">
                        <?php echo render_input('ccx_security_pw_history_count', 'Remember Last N Passwords',
                            get_option('ccx_security_pw_history_count') ?: '5', 'number'); ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="ccx_security_pw_require_upper" id="ccx_security_pw_require_upper" value="1"
                                <?php echo get_option('ccx_security_pw_require_upper') === '1' ? 'checked' : ''; ?>>
                            <label for="ccx_security_pw_require_upper">Uppercase</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="ccx_security_pw_require_lower" id="ccx_security_pw_require_lower" value="1"
                                <?php echo get_option('ccx_security_pw_require_lower') === '1' ? 'checked' : ''; ?>>
                            <label for="ccx_security_pw_require_lower">Lowercase</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="ccx_security_pw_require_number" id="ccx_security_pw_require_number" value="1"
                                <?php echo get_option('ccx_security_pw_require_number') === '1' ? 'checked' : ''; ?>>
                            <label for="ccx_security_pw_require_number">Numbers</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="ccx_security_pw_require_special" id="ccx_security_pw_require_special" value="1"
                                <?php echo get_option('ccx_security_pw_require_special') === '1' ? 'checked' : ''; ?>>
                            <label for="ccx_security_pw_require_special">Special Chars</label>
                        </div>
                    </div>
                </div>

                <hr>

                <!-- 2FA Settings (NEW) -->
                <h5 class="tw-font-semibold"><i class="fa fa-shield" style="color:#0ea5e9;"></i> Two-Factor Authentication</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="ccx_security_2fa_enforce_all" id="ccx_security_2fa_enforce_all" value="1"
                                <?php echo get_option('ccx_security_2fa_enforce_all') === '1' ? 'checked' : ''; ?>>
                            <label for="ccx_security_2fa_enforce_all">Enforce 2FA for superadmins</label>
                        </div>
                        <small class="text-muted">When enabled, superadmin (administrator) accounts must set up 2FA before accessing the admin panel. Regular staff can still enable 2FA voluntarily.</small>
                    </div>
                </div>

                <hr>

                <!-- Session Tracking (NEW) -->
                <h5 class="tw-font-semibold"><i class="fa fa-desktop" style="color:#7c3aed;"></i> Session Management</h5>
                <div class="row">
                    <div class="col-md-4">
                        <?php echo render_input('ccx_security_max_active_sessions', 'Max Concurrent Sessions',
                            get_option('ccx_security_max_active_sessions') ?: '3', 'number'); ?>
                    </div>
                    <div class="col-md-4">
                        <?php echo render_input('ccx_security_session_timeout_minutes', _l('ccx_security_session_timeout'),
                            get_option('ccx_security_session_timeout_minutes') ?: '480', 'number'); ?>
                    </div>
                </div>

                <hr>

                <!-- File Upload -->
                <h5 class="tw-font-semibold"><i class="fa fa-upload text-success"></i> File Upload Security</h5>
                <div class="row">
                    <div class="col-md-8">
                        <?php echo render_input('ccx_security_blocked_extensions', _l('ccx_security_blocked_extensions'),
                            get_option('ccx_security_blocked_extensions') ?: 'php,phtml,php3,php4,php5,php7,phar,sh,bash,exe,bat,cmd,cgi,pl,py,jsp,asp,aspx'); ?>
                    </div>
                    <div class="col-md-4">
                        <?php echo render_input('ccx_security_max_upload_mb', _l('ccx_security_max_upload_mb'),
                            get_option('ccx_security_max_upload_mb') ?: '10', 'number'); ?>
                    </div>
                </div>

                <hr>

                <!-- DevTools & Inspect -->
                <h5 class="tw-font-semibold"><i class="fa fa-code text-purple"></i> DevTools Protection</h5>
                <div class="row">
                    <div class="col-md-12">
                        <?php echo render_input('ccx_security_inspect_message', _l('ccx_security_inspect_message'),
                            get_option('ccx_security_inspect_message') ?: 'Developer tools are disabled for security reasons.'); ?>
                    </div>
                </div>

                <hr>

                <!-- Audit Log -->
                <h5 class="tw-font-semibold"><i class="fa fa-list-alt text-muted"></i> Audit Log</h5>
                <div class="row">
                    <div class="col-md-4">
                        <?php echo render_input('ccx_security_audit_retention_days', _l('ccx_security_audit_retention'),
                            get_option('ccx_security_audit_retention_days') ?: '90', 'number'); ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-check"></i> <?php echo _l('ccx_security_save_settings'); ?>
                </button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>

<style>
.ccx-sec-feature-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    transform: translateY(-2px);
    transition: all 0.2s;
}
.ccx-sec-feature-card .onoffswitch {
    min-width: 55px;
}
.ccx-quick-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 16px 10px;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    background: #fafafa;
    text-decoration: none;
    transition: all 0.2s;
    color: #475569;
    font-size: 12px;
    font-weight: 500;
}
.ccx-quick-link:hover {
    background: #f0f9ff;
    border-color: #bae6fd;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    color: #0369a1;
    text-decoration: none;
}
.ccx-quick-link i {
    font-size: 22px;
}
</style>

<?php init_tail(); ?>

<script>
$(function() {
    var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';

    // AJAX toggle for feature cards
    $('.ccx-sec-toggle').on('change', function() {
        var $toggle = $(this);
        var key = $toggle.data('key');
        var postData = { key: key };
        postData[csrfName] = csrfHash;

        $.post(admin_url + 'ccx_security/toggle_feature', postData, function(response) {
            var res = (typeof response === 'object') ? response : JSON.parse(response);
            if (res.success) {
                // Update CSRF hash for next request
                if (res.csrf_hash) {
                    csrfHash = res.csrf_hash;
                }
                alert_float('success', 'Feature updated successfully.');
                // Update score display after short delay
                if (res.score !== undefined) {
                    setTimeout(function() { location.reload(); }, 800);
                }
            }
        }).fail(function(xhr) {
            // Revert toggle on failure
            $toggle.prop('checked', !$toggle.prop('checked'));
            alert_float('danger', 'Failed to update feature. Please refresh and try again.');
        });
    });
});
</script>
