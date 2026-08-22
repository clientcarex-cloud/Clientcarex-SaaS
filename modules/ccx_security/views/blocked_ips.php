<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                    <h4 class="tw-font-bold tw-text-lg tw-flex tw-items-center tw-gap-2">
                        <i class="fa fa-ban text-danger"></i>
                        <?php echo _l('ccx_security_blocked_ips'); ?>
                        <span class="badge" style="background:#ef4444; font-size:12px;"><?php echo count($blocked); ?></span>
                        <span class="label" style="background:<?php echo (!empty($is_tenant)) ? '#6366f1' : '#10b981'; ?>;color:#fff;font-size:11px;padding:4px 12px;border-radius:20px;margin-left:8px;font-weight:500;">
                            <i class="fa <?php echo (!empty($is_tenant)) ? 'fa-building' : 'fa-server'; ?>" style="margin-right:4px;"></i>
                            <?php echo htmlspecialchars($tenant_name); ?>
                        </span>
                    </h4>
                    <div>
                        <a href="<?php echo admin_url('ccx_security'); ?>" class="btn btn-default btn-sm">
                            <i class="fa fa-arrow-left"></i> Dashboard
                        </a>
                        <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#blockIpModal">
                            <i class="fa fa-plus"></i> <?php echo _l('ccx_security_add_block'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Blocked IPs Table ─── -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" style="font-size:13px;">
                                <thead>
                                    <tr style="background:#f8fafc;">
                                        <th width="50">#</th>
                                        <th width="160"><?php echo _l('ccx_security_ip_address'); ?></th>
                                        <th><?php echo _l('ccx_security_block_reason'); ?></th>
                                        <th width="180"><?php echo _l('ccx_security_blocked_until'); ?></th>
                                        <th width="120">Type</th>
                                        <th width="160">Created</th>
                                        <th width="100">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($blocked)): ?>
                                        <?php foreach ($blocked as $i => $b): ?>
                                        <tr>
                                            <td><?php echo $i + 1; ?></td>
                                            <td>
                                                <code style="font-size:13px; font-weight:600; color:#1e293b;">
                                                    <?php echo htmlspecialchars($b->ip_address); ?>
                                                </code>
                                            </td>
                                            <td><?php echo htmlspecialchars($b->reason); ?></td>
                                            <td>
                                                <?php if ($b->is_permanent): ?>
                                                    <span class="label label-danger" style="font-size:11px;">
                                                        <i class="fa fa-infinity"></i> <?php echo _l('ccx_security_block_permanent'); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <?php
                                                    $until = strtotime($b->blocked_until);
                                                    $expired = $until < time();
                                                    ?>
                                                    <span class="<?php echo $expired ? 'text-muted' : 'text-danger'; ?>" style="font-size:12px;">
                                                        <?php echo date('M d, Y H:i', $until); ?>
                                                        <?php if ($expired): ?>
                                                            <span class="label label-default" style="font-size:10px; margin-left:5px;">Expired</span>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($b->is_permanent): ?>
                                                    <span class="label" style="background:#7c3aed;color:#fff;font-size:11px;">Manual</span>
                                                <?php else: ?>
                                                    <span class="label" style="background:#f59e0b;color:#fff;font-size:11px;">Auto</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted" style="font-size:12px;">
                                                <?php echo date('M d, Y H:i', strtotime($b->created_at)); ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo admin_url('ccx_security/unblock_ip/' . $b->id); ?>"
                                                    class="btn btn-success btn-xs"
                                                    onclick="return confirm('Unblock this IP address?');">
                                                    <i class="fa fa-unlock"></i> <?php echo _l('ccx_security_unblock'); ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted" style="padding:40px;">
                                                <i class="fa fa-check-circle" style="font-size:40px; color:#10b981; margin-bottom:10px; display:block;"></i>
                                                No blocked IP addresses. Your system is clean!
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
    </div>
</div>

<!-- ─── Block IP Modal ─── -->
<div class="modal fade" id="blockIpModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <?php echo form_open(admin_url('ccx_security/block_ip')); ?>
            <div class="modal-header" style="border-bottom:2px solid #ef4444;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-ban text-danger"></i> <?php echo _l('ccx_security_block_ip'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <?php echo render_input('ip_address', _l('ccx_security_ip_address'), '', 'text', ['placeholder' => 'e.g., 192.168.1.100']); ?>
                </div>
                <div class="form-group">
                    <?php echo render_input('reason', _l('ccx_security_block_reason'), 'Manual block by admin'); ?>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Duration (minutes)</label>
                            <input type="number" name="duration_minutes" class="form-control" value="60" min="1" id="block_duration_input">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group" style="margin-top:28px;">
                            <div class="checkbox checkbox-danger">
                                <input type="checkbox" name="permanent" id="permanent_block" value="1">
                                <label for="permanent_block"><?php echo _l('ccx_security_block_permanent'); ?></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fa fa-ban"></i> <?php echo _l('ccx_security_block_ip'); ?>
                </button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
$(function() {
    // Toggle duration input when permanent is checked
    $('#permanent_block').on('change', function() {
        $('#block_duration_input').prop('disabled', $(this).is(':checked'));
    });
});
</script>
