<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <!-- ─── Header ─── -->
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                    <h4 class="tw-font-bold tw-text-lg tw-flex tw-items-center tw-gap-2">
                        <i class="fa fa-building" style="color:#6366f1;"></i>
                        <?php echo _l('ccx_security_tenants_security'); ?>
                        <span class="label" style="background:#10b981;color:#fff;font-size:11px;padding:4px 12px;border-radius:20px;margin-left:8px;font-weight:500;">
                            <i class="fa fa-server" style="margin-right:4px;"></i>
                            <?php echo htmlspecialchars($tenant_name); ?>
                        </span>
                    </h4>
                    <div class="tw-flex tw-items-center tw-gap-2">
                        <button type="button" class="btn btn-primary btn-sm" id="btnBulkApply" disabled>
                            <i class="fa fa-cloud-upload"></i> <?php echo _l('ccx_security_bulk_apply'); ?>
                            <span class="label" id="btnBulkApplyCount" style="background:#fff;color:#6366f1;margin-left:4px;display:none;">0</span>
                        </button>
                        <a href="<?php echo admin_url('ccx_security'); ?>" class="btn btn-default btn-sm">
                            <i class="fa fa-arrow-left"></i> Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Stats Cards ─── -->
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="panel_s" style="padding:20px; border-top:3px solid #6366f1;">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <div style="width:50px;height:50px;border-radius:12px;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;">
                            <i class="fa fa-building" style="font-size:22px;color:#6366f1;"></i>
                        </div>
                        <div>
                            <h3 class="tw-font-bold tw-mb-0" style="color:#1e293b;"><?php echo $stats['total_tenants']; ?></h3>
                            <small class="text-muted"><?php echo _l('ccx_security_total_tenants'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel_s" style="padding:20px; border-top:3px solid #10b981;">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <div style="width:50px;height:50px;border-radius:12px;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;">
                            <i class="fa fa-check-circle" style="font-size:22px;color:#10b981;"></i>
                        </div>
                        <div>
                            <h3 class="tw-font-bold tw-mb-0" style="color:#1e293b;"><?php echo $stats['active_tenants']; ?></h3>
                            <small class="text-muted"><?php echo _l('ccx_security_tenants_secured'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel_s" style="padding:20px; border-top:3px solid #7c3aed;">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <div style="width:50px;height:50px;border-radius:12px;background:rgba(124,58,237,0.1);display:flex;align-items:center;justify-content:center;">
                            <i class="fa fa-desktop" style="font-size:22px;color:#7c3aed;"></i>
                        </div>
                        <div>
                            <h3 class="tw-font-bold tw-mb-0" style="color:#1e293b;"><?php echo $stats['total_sessions']; ?></h3>
                            <small class="text-muted"><?php echo _l('ccx_security_total_tenant_sessions'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel_s" style="padding:20px; border-top:3px solid #f59e0b;">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <div style="width:50px;height:50px;border-radius:12px;background:rgba(245,158,11,0.1);display:flex;align-items:center;justify-content:center;">
                            <?php
                            $avg_score = $stats['avg_score'];
                            $avg_color = $avg_score >= 80 ? '#10b981' : ($avg_score >= 60 ? '#3b82f6' : ($avg_score >= 40 ? '#f59e0b' : '#ef4444'));
                            ?>
                            <svg viewBox="0 0 44 44" width="44" height="44">
                                <circle cx="22" cy="22" r="18" fill="none" stroke="#e5e7eb" stroke-width="4"/>
                                <circle cx="22" cy="22" r="18" fill="none" stroke="<?php echo $avg_color; ?>" stroke-width="4"
                                    stroke-dasharray="<?php echo (113.1 * $avg_score / 100); ?> 113.1"
                                    stroke-linecap="round" transform="rotate(-90 22 22)"/>
                                <text x="22" y="26" text-anchor="middle" font-size="12" font-weight="700" fill="<?php echo $avg_color; ?>"><?php echo $avg_score; ?></text>
                            </svg>
                        </div>
                        <div>
                            <h3 class="tw-font-bold tw-mb-0" style="color:#1e293b;"><?php echo $avg_score; ?>%</h3>
                            <small class="text-muted"><?php echo _l('ccx_security_avg_score'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Tenant Security Overview Table ─── -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                            <h4 class="tw-font-semibold tw-mb-0">
                                <i class="fa fa-th-list" style="color:#6366f1;"></i>
                                <?php echo _l('ccx_security_tenant_overview'); ?>
                            </h4>
                            <label style="font-size:12px;margin:0;font-weight:400;cursor:pointer;" class="text-muted">
                                <input type="checkbox" id="selectAllTenants" style="margin-right:5px;vertical-align:middle;">
                                Select All Active
                            </label>
                        </div>

                        <?php if (!empty($tenants)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover ccx-tenants-table" style="font-size:13px;" id="tenantsSecurityTable">
                                <thead>
                                    <tr style="background:#f8fafc;">
                                        <th width="40" class="text-center"></th>
                                        <th><?php echo _l('ccx_security_tenant_name'); ?></th>
                                        <th width="100"><?php echo _l('ccx_security_tenant_status'); ?></th>
                                        <th width="120"><?php echo _l('ccx_security_tenant_score'); ?></th>
                                        <th width="130"><?php echo _l('ccx_security_features_enabled'); ?></th>
                                        <th width="110"><?php echo _l('ccx_security_active_sessions'); ?></th>
                                        <th width="110"><?php echo _l('ccx_security_2fa_adoption'); ?></th>
                                        <th width="120">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tenants as $t):
                                        $is_active = isset($t->status) && $t->status === 'active';
                                        $score = $t->security_summary->score;
                                        $score_color = $score >= 80 ? '#10b981' : ($score >= 60 ? '#3b82f6' : ($score >= 40 ? '#f59e0b' : '#ef4444'));
                                        $score_label = $score >= 80 ? 'Excellent' : ($score >= 60 ? 'Good' : ($score >= 40 ? 'Fair' : 'Poor'));

                                        $status_colors = [
                                            'active'         => '#10b981',
                                            'inactive'       => '#94a3b8',
                                            'banned'         => '#ef4444',
                                            'pending'        => '#f59e0b',
                                            'deploying'      => '#3b82f6',
                                            'pending_delete' => '#e11d48',
                                        ];
                                        $s_color = $status_colors[$t->status ?? ''] ?? '#94a3b8';
                                    ?>
                                    <tr class="tenant-row" data-slug="<?php echo htmlspecialchars($t->slug ?? ''); ?>">
                                        <td class="text-center">
                                            <?php if ($is_active && !empty($t->dsn)): ?>
                                            <input type="checkbox" class="tenant-checkbox" value="<?php echo htmlspecialchars($t->slug ?? ''); ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="tw-flex tw-items-center tw-gap-2">
                                                <div style="width:36px;height:36px;border-radius:8px;background:<?php echo $s_color; ?>15;display:flex;align-items:center;justify-content:center;">
                                                    <i class="fa fa-building" style="color:<?php echo $s_color; ?>;font-size:14px;"></i>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($t->name ?? $t->slug ?? 'N/A'); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($t->slug ?? ''); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="label" style="background:<?php echo $s_color; ?>;color:#fff;font-size:10px;padding:3px 10px;border-radius:12px;">
                                                <?php echo ucfirst($t->status ?? 'unknown'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($is_active && empty($t->security_summary->error)): ?>
                                            <div class="tw-flex tw-items-center tw-gap-2">
                                                <svg viewBox="0 0 32 32" width="32" height="32">
                                                    <circle cx="16" cy="16" r="13" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                                                    <circle cx="16" cy="16" r="13" fill="none" stroke="<?php echo $score_color; ?>" stroke-width="3"
                                                        stroke-dasharray="<?php echo (81.7 * $score / 100); ?> 81.7"
                                                        stroke-linecap="round" transform="rotate(-90 16 16)"/>
                                                    <text x="16" y="20" text-anchor="middle" font-size="9" font-weight="700" fill="<?php echo $score_color; ?>"><?php echo $score; ?></text>
                                                </svg>
                                                <span class="label" style="background:<?php echo $score_color; ?>20;color:<?php echo $score_color; ?>;font-size:10px;padding:2px 6px;border-radius:4px;font-weight:600;">
                                                    <?php echo $score_label; ?>
                                                </span>
                                            </div>
                                            <?php elseif (!empty($t->security_summary->error)): ?>
                                            <span class="text-muted" style="font-size:11px;" title="<?php echo htmlspecialchars($t->security_summary->error); ?>">
                                                <i class="fa fa-exclamation-triangle text-warning"></i> N/A
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted" style="font-size:11px;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_active && empty($t->security_summary->error)): ?>
                                            <span style="font-weight:600;color:#1e293b;"><?php echo $t->security_summary->features_enabled; ?></span>
                                            <span class="text-muted">/ <?php echo $t->security_summary->features_total; ?></span>
                                            <?php else: ?>
                                            <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_active && !empty($t->dsn)): ?>
                                            <a href="javascript:void(0);" class="btn-view-sessions" data-slug="<?php echo htmlspecialchars($t->slug ?? ''); ?>"
                                               style="color:#7c3aed;font-weight:600;text-decoration:none;font-size:13px;">
                                                <i class="fa fa-desktop"></i> <?php echo $t->security_summary->active_sessions; ?>
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_active && empty($t->security_summary->error)): ?>
                                            <?php
                                            $twofa_pct = $t->security_summary->total_staff > 0
                                                ? round(($t->security_summary->staff_with_2fa / $t->security_summary->total_staff) * 100)
                                                : 0;
                                            $twofa_color = $twofa_pct >= 80 ? '#10b981' : ($twofa_pct >= 50 ? '#f59e0b' : '#ef4444');
                                            ?>
                                            <div style="font-size:12px;">
                                                <span style="font-weight:600;color:<?php echo $twofa_color; ?>;"><?php echo $t->security_summary->staff_with_2fa; ?></span>
                                                <span class="text-muted">/ <?php echo $t->security_summary->total_staff; ?></span>
                                                <div style="background:#e5e7eb;border-radius:4px;height:4px;margin-top:4px;overflow:hidden;">
                                                    <div style="background:<?php echo $twofa_color; ?>;width:<?php echo $twofa_pct; ?>%;height:100%;border-radius:4px;transition:width 0.3s;"></div>
                                                </div>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_active && !empty($t->dsn)): ?>
                                            <a href="javascript:void(0);" class="btn btn-default btn-xs btn-view-detail" data-slug="<?php echo htmlspecialchars($t->slug ?? ''); ?>" title="View Security Details">
                                                <i class="fa fa-info-circle"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <!-- Expandable session row -->
                                    <tr class="session-detail-row" id="sessions-<?php echo htmlspecialchars($t->slug ?? ''); ?>" style="display:none;">
                                        <td colspan="8" style="padding:0;background:#f8fafc;">
                                            <div class="session-content" style="padding:15px 20px;">
                                                <div class="text-center text-muted" style="padding:10px;">
                                                    <i class="fa fa-spinner fa-spin"></i> <?php echo _l('ccx_security_loading'); ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php else: ?>
                        <div class="text-center" style="padding:60px 20px;">
                            <i class="fa fa-building" style="font-size:50px;color:#cbd5e1;display:block;margin-bottom:15px;"></i>
                            <h4 class="text-muted"><?php echo _l('ccx_security_no_tenants'); ?></h4>
                            <p class="text-muted">No tenants have been provisioned yet. Create tenants via the SaaS module to manage their security.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ─── Security Detail Modal (with Toggles) ─── -->
<div class="modal fade" id="tenantDetailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:2px solid #6366f1;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-shield" style="color:#6366f1;"></i> <span id="detailTenantName"></span> — <?php echo _l('ccx_security_tenant_detail'); ?></h4>
            </div>
            <div class="modal-body" id="tenantDetailBody" style="max-height:70vh;overflow-y:auto;">
                <div class="text-center" style="padding:40px;">
                    <i class="fa fa-spinner fa-spin" style="font-size:24px;color:#6366f1;"></i>
                </div>
            </div>
            <div class="modal-footer" id="tenantDetailFooter" style="display:none;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveTenantSecurity">
                    <i class="fa fa-check"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ─── Bulk Apply Modal (explicit per-feature states) ─── -->
<div class="modal fade" id="bulkApplyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:2px solid #f59e0b;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-cloud-upload" style="color:#f59e0b;"></i> <?php echo _l('ccx_security_bulk_apply'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" style="border-radius:8px;margin-bottom:15px;">
                    <i class="fa fa-info-circle"></i> <?php echo _l('ccx_security_bulk_apply_confirm'); ?>
                </div>
                <p class="text-muted" style="font-size:13px;margin-bottom:12px;">
                    Applying to <strong id="bulkApplyCount">0</strong> selected tenant(s).
                    Only the features you <strong>check</strong> below will be written — everything else on each tenant is left untouched.
                </p>

                <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
                    <h5 class="tw-font-semibold tw-mb-0" style="font-size:13px;">
                        <i class="fa fa-sliders" style="color:#6366f1;"></i> Choose features &amp; desired state
                    </h5>
                    <label style="font-size:12px;margin:0;font-weight:400;cursor:pointer;" class="text-muted">
                        <input type="checkbox" id="bulkSelectAllFeatures" style="margin-right:4px;vertical-align:middle;"> Include all
                    </label>
                </div>

                <!-- Master module toggle -->
                <div class="bulk-feature-card master" data-key="enabled" style="background:#f0fdf4;border-color:#bbf7d0;margin-bottom:12px;">
                    <label class="bulk-include-label" title="Include this in the bulk apply">
                        <input type="checkbox" class="bulk-include-cb" data-key="enabled">
                    </label>
                    <i class="fa fa-power-off" style="color:#10b981;width:18px;text-align:center;"></i>
                    <span class="bulk-feature-name"><strong>Master Toggle</strong> (Enable/Disable Module)</span>
                    <div class="onoffswitch bulk-state-switch">
                        <input type="checkbox" class="onoffswitch-checkbox bulk-state-cb" id="bulk_state_enabled" data-key="enabled" disabled>
                        <label class="onoffswitch-label" for="bulk_state_enabled"></label>
                    </div>
                </div>

                <div class="row" id="bulkFeatureList">
                    <?php foreach ($feature_definitions as $key => $def): $uid = 'bulk_state_' . preg_replace('/[^a-z0-9_]/i', '_', $key); ?>
                    <div class="col-md-6" style="margin-bottom:8px;">
                        <div class="bulk-feature-card" data-key="<?php echo htmlspecialchars($key); ?>">
                            <label class="bulk-include-label" title="Include this in the bulk apply">
                                <input type="checkbox" class="bulk-include-cb" data-key="<?php echo htmlspecialchars($key); ?>">
                            </label>
                            <i class="fa <?php echo $def['icon']; ?>" style="color:<?php echo $def['color']; ?>;width:18px;text-align:center;"></i>
                            <span class="bulk-feature-name"><?php echo $def['name']; ?></span>
                            <div class="onoffswitch bulk-state-switch">
                                <input type="checkbox" class="onoffswitch-checkbox bulk-state-cb" id="<?php echo $uid; ?>" data-key="<?php echo htmlspecialchars($key); ?>" disabled>
                                <label class="onoffswitch-label" for="<?php echo $uid; ?>"></label>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmBulkApply" disabled>
                    <i class="fa fa-cloud-upload"></i> Apply to Selected Tenants
                </button>
            </div>
        </div>
    </div>
</div>



<style>
.ccx-tenants-table tbody tr.tenant-row {
    cursor: default;
    transition: background 0.15s;
}
.ccx-tenants-table tbody tr.tenant-row:hover {
    background: #f0f9ff !important;
}
.session-detail-row td {
    border-top: none !important;
}
.session-detail-row .session-content {
    border-left: 3px solid #7c3aed;
    margin: 0 10px;
    border-radius: 0 0 8px 8px;
    background: #fff;
}
.btn-view-sessions:hover {
    text-decoration: underline !important;
}
.tenant-feature-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 500;
    margin: 3px;
    border: 1px solid;
}
.tenant-feature-badge.enabled {
    border-color: #bbf7d0;
    background: #f0fdf4;
    color: #166534;
}
.tenant-feature-badge.disabled {
    border-color: #fecaca;
    background: #fef2f2;
    color: #991b1b;
}
/* Detail modal toggle cards */
.detail-feature-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    transition: all 0.15s;
    margin-bottom: 8px;
}
.detail-feature-card:hover {
    border-color: #c7d2fe;
    background: #f8faff;
}
.detail-feature-card .onoffswitch { min-width: 55px; }

/* ─── Bulk apply feature cards ─── */
.bulk-feature-card {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    transition: all 0.15s;
}
.bulk-feature-card .bulk-include-label {
    margin: 0;
    font-weight: 400;
    cursor: pointer;
    line-height: 1;
}
.bulk-feature-card .bulk-feature-name {
    font-size: 12px;
    font-weight: 500;
    flex: 1 1 auto;
}
.bulk-feature-card .bulk-state-switch {
    min-width: 55px;
    flex: 0 0 auto;
    margin-left: auto;
    opacity: 0.4;
    transition: opacity 0.15s;
}
.bulk-feature-card.included {
    border-color: #c7d2fe;
    background: #f8faff;
}
.bulk-feature-card.included.master {
    border-color: #86efac;
    background: #ecfdf5;
}
.bulk-feature-card.included .bulk-state-switch {
    opacity: 1;
}

</style>

<?php init_tail(); ?>

<script>
$(function() {
    var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
    var featureDefs = <?php echo json_encode($feature_definitions); ?>;
    var currentDetailSlug = '';

    // ═══════════════════════════════════════════════════════════
    // ─── BULK APPLY: tenant selection ───
    // ═══════════════════════════════════════════════════════════
    $('#selectAllTenants').on('change', function() {
        $('.tenant-checkbox').prop('checked', $(this).is(':checked'));
        updateBulkBtn();
    });
    $(document).on('change', '.tenant-checkbox', function() {
        // Keep "Select All" in sync
        var total = $('.tenant-checkbox').length;
        var checked = $('.tenant-checkbox:checked').length;
        $('#selectAllTenants').prop('checked', total > 0 && checked === total);
        updateBulkBtn();
    });
    function updateBulkBtn() {
        var count = $('.tenant-checkbox:checked').length;
        $('#btnBulkApply').prop('disabled', count === 0);
        if (count > 0) {
            $('#btnBulkApplyCount').text(count).show();
        } else {
            $('#btnBulkApplyCount').hide();
        }
    }

    // ─── Open the bulk modal ───
    $('#btnBulkApply').on('click', function() {
        var count = $('.tenant-checkbox:checked').length;
        if (count === 0) { return; }
        $('#bulkApplyCount').text(count);

        // Reset modal state on every open
        $('#bulkSelectAllFeatures').prop('checked', false);
        $('.bulk-include-cb').prop('checked', false);
        $('.bulk-state-cb').prop('checked', false).prop('disabled', true);
        $('.bulk-feature-card').removeClass('included');
        $('#confirmBulkApply').prop('disabled', true);

        $('#bulkApplyModal').modal('show');
    });

    // ─── Include-all features toggle ───
    $('#bulkSelectAllFeatures').on('change', function() {
        var on = $(this).is(':checked');
        $('.bulk-include-cb').prop('checked', on);
        $('.bulk-include-cb').each(function() { syncIncludeRow($(this)); });
        updateBulkConfirm();
    });

    // ─── Per-feature include checkbox: gates its state switch ───
    $(document).on('change', '.bulk-include-cb', function() {
        syncIncludeRow($(this));
        // Keep "Include all" in sync
        var total = $('.bulk-include-cb').length;
        var checked = $('.bulk-include-cb:checked').length;
        $('#bulkSelectAllFeatures').prop('checked', total > 0 && checked === total);
        updateBulkConfirm();
    });

    function syncIncludeRow($cb) {
        var included = $cb.is(':checked');
        var $card = $cb.closest('.bulk-feature-card');
        $card.toggleClass('included', included);
        // Enable/disable the matching state switch; reset to OFF when excluded
        var $state = $card.find('.bulk-state-cb');
        $state.prop('disabled', !included);
        if (!included) { $state.prop('checked', false); }
    }

    function updateBulkConfirm() {
        $('#confirmBulkApply').prop('disabled', $('.bulk-include-cb:checked').length === 0);
    }

    // ─── Confirm & apply ───
    $('#confirmBulkApply').on('click', function() {
        var $btn = $(this);

        // Collect selected tenants
        var slugs = [];
        $('.tenant-checkbox:checked').each(function() { slugs.push($(this).val()); });
        if (slugs.length === 0) {
            alert_float('warning', 'No tenants selected.');
            return;
        }

        // Collect ONLY the included features with their chosen state
        var settings = {};
        $('.bulk-include-cb:checked').each(function() {
            var key = $(this).data('key');
            var $state = $('.bulk-state-cb[data-key="' + key + '"]');
            settings['ccx_security_' + key] = $state.is(':checked') ? '1' : '0';
        });
        if ($.isEmptyObject(settings)) {
            alert_float('warning', 'Select at least one feature to apply.');
            return;
        }

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Applying...');

        var postData = {
            tenant_slugs: slugs,
            settings: settings
        };
        postData[csrfName] = csrfHash;

        $.post(admin_url + 'ccx_security/bulk_apply_security', postData, function(response) {
            var res = (typeof response === 'object') ? response : JSON.parse(response);
            if (res.csrf_hash) { csrfHash = res.csrf_hash; }
            if (res.success) {
                $('#bulkApplyModal').modal('hide');
                if (res.fail_count > 0) {
                    alert_float('warning', 'Applied to ' + res.success_count + ' tenant(s). Failed for ' + res.fail_count + ' (' + (res.failed_slugs || []).join(', ') + ').');
                } else {
                    alert_float('success', 'Security settings applied to ' + res.success_count + ' tenant(s).');
                }
                setTimeout(function() { location.reload(); }, 1200);
            } else {
                alert_float('danger', res.error || 'Failed to apply settings.');
                $btn.prop('disabled', false).html('<i class="fa fa-cloud-upload"></i> Apply to Selected Tenants');
            }
        }).fail(function() {
            alert_float('danger', 'Connection error. Please try again.');
            $btn.prop('disabled', false).html('<i class="fa fa-cloud-upload"></i> Apply to Selected Tenants');
        });
    });


    // ═══════════════════════════════════════════════════════════
    // ─── VIEW SESSIONS (Expandable Row) ───
    // ═══════════════════════════════════════════════════════════
    $(document).on('click', '.btn-view-sessions', function(e) {
        e.preventDefault();
        var slug = $(this).data('slug');
        var $row = $('#sessions-' + slug);
        if ($row.is(':visible')) { $row.slideUp(200); return; }
        $('.session-detail-row:visible').slideUp(200);
        $row.find('.session-content').html('<div class="text-center text-muted" style="padding:15px;"><i class="fa fa-spinner fa-spin"></i> <?php echo _l("ccx_security_loading"); ?></div>');
        $row.slideDown(200);

        $.get(admin_url + 'ccx_security/tenant_sessions_ajax', { slug: slug }, function(response) {
            var res = (typeof response === 'object') ? response : JSON.parse(response);
            if (res.success) {
                $row.find('.session-content').html(
                    '<div class="tw-flex tw-items-center tw-justify-between tw-mb-2">' +
                    '<h5 class="tw-font-semibold tw-mb-0" style="font-size:13px;"><i class="fa fa-desktop" style="color:#7c3aed;"></i> Active Sessions</h5>' +
                    '<span class="badge" style="background:#7c3aed;font-size:11px;">' + res.count + '</span></div>' + res.html
                );
            } else {
                $row.find('.session-content').html('<div class="text-center text-danger" style="padding:15px;"><i class="fa fa-exclamation-circle"></i> ' + (res.error || 'Failed') + '</div>');
            }
        }).fail(function() {
            $row.find('.session-content').html('<div class="text-center text-danger" style="padding:15px;"><i class="fa fa-exclamation-circle"></i> Connection error</div>');
        });
    });

    // ═══════════════════════════════════════════════════════════
    // ─── SECURITY DETAIL MODAL (with Toggle Switches) ───
    // ═══════════════════════════════════════════════════════════
    $(document).on('click', '.btn-view-detail', function(e) {
        e.preventDefault();
        var slug = $(this).data('slug');
        currentDetailSlug = slug;
        var tenantName = $(this).closest('tr').find('strong').first().text();

        $('#detailTenantName').text(tenantName);
        $('#tenantDetailBody').html('<div class="text-center" style="padding:40px;"><i class="fa fa-spinner fa-spin" style="font-size:24px;color:#6366f1;"></i></div>');
        $('#tenantDetailFooter').hide();
        $('#tenantDetailModal').modal('show');

        $.get(admin_url + 'ccx_security/tenant_security_detail', { slug: slug }, function(response) {
            var res = (typeof response === 'object') ? response : JSON.parse(response);
            if (res.success && res.options) {
                renderDetailModal(res.options);
                $('#tenantDetailFooter').show();
            } else {
                $('#tenantDetailBody').html('<div class="text-center text-danger" style="padding:30px;"><i class="fa fa-exclamation-circle"></i> Unable to fetch security details.</div>');
            }
        }).fail(function() {
            $('#tenantDetailBody').html('<div class="text-center text-danger" style="padding:30px;"><i class="fa fa-exclamation-circle"></i> Connection error</div>');
        });
    });

    function renderDetailModal(options) {
        var globalEnabled = options['ccx_security_enabled'] === '1';
        var html = '';

        // Master toggle
        html += '<div class="detail-feature-card" style="border-color:' + (globalEnabled ? '#bbf7d0' : '#fecaca') + ';background:' + (globalEnabled ? '#f0fdf4' : '#fef2f2') + ';margin-bottom:15px;">';
        html += '<div class="tw-flex tw-items-center tw-gap-2">';
        html += '<i class="fa fa-power-off" style="font-size:16px;color:' + (globalEnabled ? '#10b981' : '#ef4444') + ';"></i>';
        html += '<strong style="font-size:13px;">CCX Security Module</strong>';
        html += '</div>';
        html += '<div class="onoffswitch" style="min-width:55px;">';
        html += '<input type="checkbox" class="onoffswitch-checkbox detail-toggle" id="detail_enabled" data-key="enabled" ' + (globalEnabled ? 'checked' : '') + '>';
        html += '<label class="onoffswitch-label" for="detail_enabled"></label>';
        html += '</div></div>';

        // Feature toggles
        html += '<div class="row">';
        $.each(featureDefs, function(key, def) {
            var optKey = 'ccx_security_' + key;
            var isOn = options[optKey] === '1';
            var uid = 'detail_' + key.replace(/[^a-z0-9_]/g, '_');
            html += '<div class="col-md-6">';
            html += '<div class="detail-feature-card">';
            html += '<div class="tw-flex tw-items-center tw-gap-2">';
            html += '<i class="fa ' + def.icon + '" style="font-size:14px;color:' + def.color + ';width:18px;text-align:center;"></i>';
            html += '<span style="font-size:12px;font-weight:500;">' + def.name + '</span>';
            html += '</div>';
            html += '<div class="onoffswitch" style="min-width:55px;">';
            html += '<input type="checkbox" class="onoffswitch-checkbox detail-toggle" id="' + uid + '" data-key="' + key + '" ' + (isOn ? 'checked' : '') + '>';
            html += '<label class="onoffswitch-label" for="' + uid + '"></label>';
            html += '</div>';
            html += '</div></div>';
        });
        html += '</div>';

        $('#tenantDetailBody').html(html);
    }

    // ─── Save Tenant Security (AJAX) ───
    $('#saveTenantSecurity').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        // Collect toggle states
        var settings = {};
        $('.detail-toggle').each(function() {
            var key = $(this).data('key');
            var val = $(this).is(':checked') ? '1' : '0';
            settings['ccx_security_' + key] = val;
        });

        var postData = {
            slug: currentDetailSlug,
            settings: settings
        };
        postData[csrfName] = csrfHash;

        $.post(admin_url + 'ccx_security/save_tenant_security', postData, function(response) {
            var res = (typeof response === 'object') ? response : JSON.parse(response);
            if (res.csrf_hash) csrfHash = res.csrf_hash;
            if (res.success) {
                alert_float('success', 'Security settings saved for this tenant.');
                $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Save Changes');
                // Reload page after short delay to refresh table data
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                alert_float('danger', res.error || 'Failed to save settings.');
                $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Save Changes');
            }
        }).fail(function() {
            alert_float('danger', 'Connection error. Please try again.');
            $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Save Changes');
        });
    });
});
</script>
