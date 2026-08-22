<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                    <h4 class="tw-font-bold tw-text-lg tw-flex tw-items-center tw-gap-2">
                        <i class="fa fa-desktop" style="color:#7c3aed;"></i>
                        <?php echo _l('ccx_security_active_sessions'); ?>
                        <span class="badge" style="background:#7c3aed; font-size:12px;"><?php echo count($sessions); ?></span>
                        <span class="label" style="background:<?php echo (!empty($is_tenant)) ? '#6366f1' : '#10b981'; ?>;color:#fff;font-size:11px;padding:4px 12px;border-radius:20px;margin-left:8px;font-weight:500;">
                            <i class="fa <?php echo (!empty($is_tenant)) ? 'fa-building' : 'fa-server'; ?>" style="margin-right:4px;"></i>
                            <?php echo htmlspecialchars($tenant_name); ?>
                        </span>
                    </h4>
                    <a href="<?php echo admin_url('ccx_security'); ?>" class="btn btn-default btn-sm">
                        <i class="fa fa-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- ─── Sessions Grid ─── -->
        <div class="row">
            <?php if (!empty($sessions)): ?>
                <?php foreach ($sessions as $s): ?>
                <?php
                $is_current = ($s->session_id === $current_session);
                $mins_ago = round((time() - strtotime($s->last_activity)) / 60);
                $is_idle = $mins_ago > 30;
                $border_color = $is_current ? '#10b981' : ($is_idle ? '#f59e0b' : '#7c3aed');

                // Build location string
                $loc_parts = [];
                if (!empty($s->geo_city))    $loc_parts[] = $s->geo_city;
                if (!empty($s->geo_region))  $loc_parts[] = $s->geo_region;
                if (!empty($s->geo_country)) $loc_parts[] = $s->geo_country;
                $location_str = !empty($loc_parts) ? implode(', ', $loc_parts) : '';
                $isp_str = $s->geo_isp ?? '';
                ?>
                <div class="col-md-4 col-sm-6" style="margin-bottom:20px;">
                    <div class="panel_s" style="border-top:3px solid <?php echo $border_color; ?>; margin-bottom:0;">
                        <div class="panel-body" style="padding:20px;">
                            <div class="tw-flex tw-items-start tw-justify-between tw-mb-3">
                                <div class="tw-flex tw-items-center tw-gap-3">
                                    <div style="width:44px;height:44px;border-radius:12px;background:<?php echo $border_color; ?>15;display:flex;align-items:center;justify-content:center;">
                                        <?php
                                        $device_icon = 'fa-desktop';
                                        if (stripos($s->device_info ?? '', 'iOS') !== false || stripos($s->device_info ?? '', 'Android') !== false) {
                                            $device_icon = 'fa-mobile';
                                        }
                                        ?>
                                        <i class="fa <?php echo $device_icon; ?>" style="font-size:20px;color:<?php echo $border_color; ?>;"></i>
                                    </div>
                                    <div>
                                        <h5 class="tw-font-semibold tw-mb-0" style="font-size:14px;">
                                            <?php echo htmlspecialchars($s->staff_name ?? 'Unknown'); ?>
                                        </h5>
                                        <small class="text-muted"><?php echo htmlspecialchars($s->staff_email ?? ''); ?></small>
                                    </div>
                                </div>
                                <?php if ($is_current): ?>
                                    <span class="label label-success" style="font-size:10px;">Current</span>
                                <?php elseif ($is_idle): ?>
                                    <span class="label label-warning" style="font-size:10px;">Idle</span>
                                <?php else: ?>
                                    <span class="label" style="background:#7c3aed;color:#fff;font-size:10px;">Active</span>
                                <?php endif; ?>
                            </div>

                            <div style="background:#f8fafc;border-radius:8px;padding:12px;font-size:12px;margin-bottom:12px;">
                                <!-- Device -->
                                <div class="tw-flex tw-items-center tw-gap-2 tw-mb-1">
                                    <i class="fa fa-laptop text-muted" style="width:14px;text-align:center;"></i>
                                    <span class="text-muted"><?php echo htmlspecialchars($s->device_info ?? 'Unknown Device'); ?></span>
                                </div>
                                <!-- IP -->
                                <div class="tw-flex tw-items-center tw-gap-2 tw-mb-1">
                                    <i class="fa fa-wifi text-muted" style="width:14px;text-align:center;"></i>
                                    <code style="font-size:11px;"><?php echo htmlspecialchars($s->ip_address ?? ''); ?></code>
                                </div>
                                <!-- Location -->
                                <?php if (!empty($location_str)): ?>
                                <div class="tw-flex tw-items-center tw-gap-2 tw-mb-1">
                                    <i class="fa fa-map-marker" style="width:14px;text-align:center;color:#ef4444;"></i>
                                    <span style="font-weight:500;color:#1e293b;"><?php echo htmlspecialchars($location_str); ?></span>
                                </div>
                                <?php endif; ?>
                                <!-- ISP -->
                                <?php if (!empty($isp_str)): ?>
                                <div class="tw-flex tw-items-center tw-gap-2 tw-mb-1">
                                    <i class="fa fa-building text-muted" style="width:14px;text-align:center;"></i>
                                    <span class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($isp_str); ?></span>
                                </div>
                                <?php endif; ?>
                                <!-- Last activity -->
                                <div class="tw-flex tw-items-center tw-gap-2">
                                    <i class="fa fa-clock-o text-muted" style="width:14px;text-align:center;"></i>
                                    <span class="text-muted">
                                        <?php if ($mins_ago < 1): ?>
                                            Just now
                                        <?php elseif ($mins_ago < 60): ?>
                                            <?php echo $mins_ago; ?> min ago
                                        <?php else: ?>
                                            <?php echo round($mins_ago / 60); ?>h ago
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="tw-flex tw-items-center tw-justify-between" style="font-size:11px;">
                                <span class="text-muted">
                                    Started: <?php echo date('M d, H:i', strtotime($s->created_at)); ?>
                                </span>
                                <?php if (!$is_current): ?>
                                <a href="<?php echo admin_url('ccx_security/kill_session/' . $s->id); ?>"
                                    class="btn btn-danger btn-xs"
                                    onclick="return confirm('Terminate this session? The user will be logged out.');">
                                    <i class="fa fa-power-off"></i> Kill
                                </a>
                                <?php else: ?>
                                <span class="text-success" style="font-size:11px;"><i class="fa fa-check-circle"></i> You</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-md-12">
                    <div class="panel_s">
                        <div class="panel-body text-center" style="padding:50px;">
                            <i class="fa fa-desktop" style="font-size:50px; color:#7c3aed; margin-bottom:15px; display:block;"></i>
                            <h4 class="text-muted">No Active Sessions Tracked</h4>
                            <p class="text-muted">Enable "Session Tracking" on the dashboard to start monitoring active sessions.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php init_tail(); ?>
