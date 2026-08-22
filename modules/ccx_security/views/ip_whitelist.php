<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                    <h4 class="tw-font-bold tw-text-lg tw-flex tw-items-center tw-gap-2">
                        <i class="fa fa-map-marker" style="color:#14b8a6;"></i>
                        <?php echo _l('ccx_security_ip_whitelist'); ?>
                        <span class="badge" style="background:#14b8a6; font-size:12px;"><?php echo count($whitelist); ?></span>
                        <span class="label" style="background:<?php echo (!empty($is_tenant)) ? '#6366f1' : '#10b981'; ?>;color:#fff;font-size:11px;padding:4px 12px;border-radius:20px;margin-left:8px;font-weight:500;">
                            <i class="fa <?php echo (!empty($is_tenant)) ? 'fa-building' : 'fa-server'; ?>" style="margin-right:4px;"></i>
                            <?php echo htmlspecialchars($tenant_name); ?>
                        </span>
                    </h4>
                    <div>
                        <a href="<?php echo admin_url('ccx_security'); ?>" class="btn btn-default btn-sm">
                            <i class="fa fa-arrow-left"></i> Dashboard
                        </a>
                        <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#addWhitelistModal">
                            <i class="fa fa-plus"></i> <?php echo _l('ccx_security_add_whitelist'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Current IP Info ─── -->
        <div class="row">
            <div class="col-md-12">
                <div style="background:linear-gradient(135deg,#f0fdfa,#ccfbf1);padding:15px 20px;border-radius:10px;border:1px solid #99f6e4;margin-bottom:20px;">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <div style="width:40px;height:40px;border-radius:10px;background:#14b8a6;display:flex;align-items:center;justify-content:center;">
                            <i class="fa fa-info-circle" style="color:#fff;font-size:18px;"></i>
                        </div>
                        <div>
                            <strong style="color:#0f766e;">Your Current IP Address:</strong>
                            <code style="margin-left:8px;font-size:14px;font-weight:600;color:#115e59;"><?php echo htmlspecialchars($current_ip); ?></code>
                            <?php
                            $is_whitelisted = false;
                            foreach ($whitelist as $w) {
                                if ($w->ip_address === $current_ip) { $is_whitelisted = true; break; }
                            }
                            ?>
                            <?php if ($is_whitelisted): ?>
                                <span class="label label-success" style="margin-left:8px;font-size:11px;"><i class="fa fa-check"></i> Whitelisted</span>
                            <?php else: ?>
                                <span class="label label-warning" style="margin-left:8px;font-size:11px;"><i class="fa fa-exclamation"></i> Not Whitelisted</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Whitelist Table ─── -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" style="font-size:13px;">
                                <thead>
                                    <tr style="background:#f8fafc;">
                                        <th width="50">#</th>
                                        <th width="180">IP / CIDR Range</th>
                                        <th>Label</th>
                                        <th width="80">Type</th>
                                        <th width="140">Added By</th>
                                        <th width="160">Created</th>
                                        <th width="100">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($whitelist)): ?>
                                        <?php foreach ($whitelist as $i => $w): ?>
                                        <tr>
                                            <td><?php echo $i + 1; ?></td>
                                            <td>
                                                <code style="font-size:13px; font-weight:600; color:#0f766e;">
                                                    <?php echo htmlspecialchars($w->ip_address); ?>
                                                </code>
                                            </td>
                                            <td><?php echo htmlspecialchars($w->label ?: '—'); ?></td>
                                            <td>
                                                <?php if ($w->is_cidr): ?>
                                                    <span class="label" style="background:#8b5cf6;color:#fff;font-size:11px;">CIDR</span>
                                                <?php else: ?>
                                                    <span class="label" style="background:#0ea5e9;color:#fff;font-size:11px;">Single</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($w->added_by_name ?? 'System'); ?></td>
                                            <td class="text-muted" style="font-size:12px;">
                                                <?php echo date('M d, Y H:i', strtotime($w->created_at)); ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo admin_url('ccx_security/remove_whitelist_ip/' . $w->id); ?>"
                                                    class="btn btn-danger btn-xs"
                                                    onclick="return confirm('Remove this IP from the whitelist?');">
                                                    <i class="fa fa-trash"></i> Remove
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted" style="padding:40px;">
                                                <i class="fa fa-globe" style="font-size:40px; color:#14b8a6; margin-bottom:10px; display:block;"></i>
                                                No IPs whitelisted yet. All traffic is currently allowed.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Info Card ─── -->
        <div class="row">
            <div class="col-md-6">
                <div class="panel_s">
                    <div class="panel-body" style="padding:20px;">
                        <h5 class="tw-font-semibold"><i class="fa fa-info-circle text-primary"></i> How IP Whitelisting Works</h5>
                        <ul class="text-muted" style="font-size:13px; line-height:2;">
                            <li><strong>Empty whitelist = All traffic allowed</strong> (fail-open design)</li>
                            <li>Supports both <strong>single IPs</strong> and <strong>CIDR ranges</strong> (e.g., 10.0.0.0/8)</li>
                            <li>Non-whitelisted IPs will be <strong>denied access</strong> to the admin panel</li>
                            <li>Super-admin IPs should always be whitelisted to prevent self-lockout</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel_s">
                    <div class="panel-body" style="padding:20px;">
                        <h5 class="tw-font-semibold"><i class="fa fa-lightbulb-o text-warning"></i> Common CIDR Ranges</h5>
                        <table class="table table-condensed" style="font-size:12px; margin-bottom:0;">
                            <tr><td><code>10.0.0.0/8</code></td><td class="text-muted">Private network (Class A)</td></tr>
                            <tr><td><code>172.16.0.0/12</code></td><td class="text-muted">Private network (Class B)</td></tr>
                            <tr><td><code>192.168.0.0/16</code></td><td class="text-muted">Private network (Class C)</td></tr>
                            <tr><td><code>203.0.113.0/24</code></td><td class="text-muted">Office /24 subnet (256 IPs)</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ─── Add Whitelist Modal ─── -->
<div class="modal fade" id="addWhitelistModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <?php echo form_open(admin_url('ccx_security/add_whitelist_ip')); ?>
            <div class="modal-header" style="border-bottom:2px solid #14b8a6;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-plus" style="color:#14b8a6;"></i> <?php echo _l('ccx_security_add_whitelist'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <?php echo render_input('ip_address', 'IP Address or CIDR Range', '', 'text', ['placeholder' => 'e.g., 203.0.113.50 or 10.0.0.0/8']); ?>
                </div>
                <div class="form-group">
                    <?php echo render_input('label', 'Label (Optional)', '', 'text', ['placeholder' => 'e.g., Office Network, VPN Server']); ?>
                </div>
                <div style="padding:12px;background:#f0fdfa;border-radius:8px;border:1px solid #99f6e4;">
                    <small class="text-muted">
                        <i class="fa fa-info-circle"></i>
                        Your current IP is <strong><?php echo htmlspecialchars($current_ip); ?></strong>.
                        Make sure to whitelist it before enabling IP restrictions.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fa fa-check"></i> Add to Whitelist
                </button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>

<?php init_tail(); ?>
