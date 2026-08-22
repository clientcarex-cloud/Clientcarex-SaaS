<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                    <h4 class="tw-font-bold tw-text-lg tw-flex tw-items-center tw-gap-2">
                        <i class="fa fa-list-alt text-primary"></i>
                        <?php echo _l('ccx_security_audit_log'); ?>
                        <span class="badge" style="background:#3b82f6; font-size:12px;"><?php echo $total; ?></span>
                        <span class="label" style="background:<?php echo (!empty($is_tenant)) ? '#6366f1' : '#10b981'; ?>;color:#fff;font-size:11px;padding:4px 12px;border-radius:20px;margin-left:8px;font-weight:500;">
                            <i class="fa <?php echo (!empty($is_tenant)) ? 'fa-building' : 'fa-server'; ?>" style="margin-right:4px;"></i>
                            <?php echo htmlspecialchars($tenant_name); ?>
                        </span>
                    </h4>
                    <div>
                        <a href="<?php echo admin_url('ccx_security'); ?>" class="btn btn-default btn-sm">
                            <i class="fa fa-arrow-left"></i> Dashboard
                        </a>
                        <?php
                        $export_params = array_filter([
                            'event_type' => $filters['event_type'] ?? '',
                            'severity'   => $filters['severity'] ?? '',
                            'date_from'  => $filters['date_from'] ?? '',
                            'date_to'    => $filters['date_to'] ?? '',
                            'search'     => $filters['search'] ?? '',
                        ]);
                        $export_qs = !empty($export_params) ? '&' . http_build_query($export_params) : '';
                        ?>
                        <a href="<?php echo admin_url('ccx_security/export_audit_log?format=csv' . $export_qs); ?>" class="btn btn-success btn-sm">
                            <i class="fa fa-download"></i> Export CSV
                        </a>
                        <a href="<?php echo admin_url('ccx_security/export_audit_log?format=html' . $export_qs); ?>" class="btn btn-info btn-sm">
                            <i class="fa fa-file-text"></i> Export Report
                        </a>
                        <a href="<?php echo admin_url('ccx_security/clear_audit_log'); ?>" class="btn btn-danger btn-sm"
                            onclick="return confirm('Are you sure you want to clear all audit logs?');">
                            <i class="fa fa-trash"></i> <?php echo _l('ccx_security_clear_log'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Filters ─── -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body" style="padding:15px 20px;">
                        <form method="GET" action="<?php echo admin_url('ccx_security/audit_log'); ?>" class="form-inline">
                            <div class="form-group" style="margin-right:12px;">
                                <select name="event_type" class="selectpicker" data-width="180px" data-live-search="true" title="All Event Types">
                                    <option value="">All Event Types</option>
                                    <?php foreach ($event_types as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo ($filters['event_type'] === $type) ? 'selected' : ''; ?>>
                                        <?php echo ucwords(str_replace('_', ' ', $type)); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-right:12px;">
                                <select name="severity" class="selectpicker" data-width="140px" title="All Severities">
                                    <option value="">All Severities</option>
                                    <option value="info" <?php echo ($filters['severity'] === 'info') ? 'selected' : ''; ?>>Info</option>
                                    <option value="warning" <?php echo ($filters['severity'] === 'warning') ? 'selected' : ''; ?>>Warning</option>
                                    <option value="critical" <?php echo ($filters['severity'] === 'critical') ? 'selected' : ''; ?>>Critical</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-right:12px;">
                                <input type="date" name="date_from" class="form-control" placeholder="From Date"
                                    value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>" style="width:150px;">
                            </div>
                            <div class="form-group" style="margin-right:12px;">
                                <input type="date" name="date_to" class="form-control" placeholder="To Date"
                                    value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>" style="width:150px;">
                            </div>
                            <div class="form-group" style="margin-right:12px;">
                                <input type="text" name="search" class="form-control" placeholder="Search..."
                                    value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>" style="width:200px;">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Filter</button>
                            <a href="<?php echo admin_url('ccx_security/audit_log'); ?>" class="btn btn-default btn-sm" style="margin-left:5px;">Clear</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Log Table ─── -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" style="font-size:13px;">
                                <thead>
                                    <tr style="background:#f8fafc;">
                                        <th width="90"><?php echo _l('ccx_security_event_severity'); ?></th>
                                        <th width="160"><?php echo _l('ccx_security_event_type'); ?></th>
                                        <th>Tenant</th>
                                        <th><?php echo _l('ccx_security_event_description'); ?></th>
                                        <th width="120"><?php echo _l('ccx_security_event_ip'); ?></th>
                                        <th width="120"><?php echo _l('ccx_security_event_user'); ?></th>
                                        <th width="150"><?php echo _l('ccx_security_event_uri'); ?></th>
                                        <th width="150"><?php echo _l('ccx_security_event_timestamp'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($logs)): ?>
                                        <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $sev_colors = ['info' => '#3b82f6', 'warning' => '#f59e0b', 'critical' => '#ef4444'];
                                                $sev_icons = ['info' => 'fa-info-circle', 'warning' => 'fa-exclamation-triangle', 'critical' => 'fa-times-circle'];
                                                $sc = $sev_colors[$log->severity] ?? '#64748b';
                                                $si = $sev_icons[$log->severity] ?? 'fa-circle';
                                                ?>
                                                <span class="label" style="background:<?php echo $sc; ?>;color:#fff;font-size:11px;padding:3px 8px;border-radius:4px;">
                                                    <i class="fa <?php echo $si; ?>"></i> <?php echo ucfirst($log->severity); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <code style="font-size:11px;background:#f1f5f9;padding:2px 6px;border-radius:3px;">
                                                    <?php echo htmlspecialchars($log->event_type); ?>
                                                </code>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($log->tenant_name ?? 'Master'); ?>
                                            </td>
                                            <td style="max-width:350px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($log->description); ?>">
                                                <?php echo htmlspecialchars($log->description); ?>
                                            </td>
                                            <td><code style="font-size:12px;"><?php echo htmlspecialchars($log->ip_address ?? ''); ?></code></td>
                                            <td><?php echo htmlspecialchars($log->staff_name ?? 'System'); ?></td>
                                            <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($log->request_uri ?? ''); ?>">
                                                <small class="text-muted"><?php echo htmlspecialchars($log->request_uri ?? ''); ?></small>
                                            </td>
                                            <td class="text-muted" style="font-size:12px;">
                                                <?php echo date('M d, Y H:i:s', strtotime($log->created_at)); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted" style="padding:40px;">
                                                <i class="fa fa-check-circle" style="font-size:40px; color:#10b981; margin-bottom:10px; display:block;"></i>
                                                No audit log entries found for the selected filters.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- ─── Pagination ─── -->
                        <?php if ($total > 50): ?>
                        <div class="text-center" style="margin-top:20px;">
                            <?php
                            $current_offset = (int)($filters['offset'] ?? 0);
                            $total_pages = ceil($total / 50);
                            $current_page = ($current_offset / 50) + 1;

                            // Build base URL with current filters
                            $base_params = $filters;
                            unset($base_params['offset']);
                            unset($base_params['limit']);
                            $base_url = admin_url('ccx_security/audit_log') . '?' . http_build_query(array_filter($base_params));
                            ?>
                            <ul class="pagination">
                                <?php if ($current_page > 1): ?>
                                <li><a href="<?php echo $base_url . '&offset=' . (($current_page - 2) * 50); ?>">&laquo; Prev</a></li>
                                <?php endif; ?>

                                <?php for ($p = max(1, $current_page - 2); $p <= min($total_pages, $current_page + 2); $p++): ?>
                                <li class="<?php echo $p === $current_page ? 'active' : ''; ?>">
                                    <a href="<?php echo $base_url . '&offset=' . (($p - 1) * 50); ?>"><?php echo $p; ?></a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($current_page < $total_pages): ?>
                                <li><a href="<?php echo $base_url . '&offset=' . ($current_page * 50); ?>">Next &raquo;</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
