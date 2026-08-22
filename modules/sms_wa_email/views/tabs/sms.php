<?php if (!isset($channel_visible) || $channel_visible): ?>
    <div role="tabpanel" class="tab-pane <?= (isset($is_default_tab) && $is_default_tab) ? 'active' : ''; ?>" id="sms">

        <!-- Balance Cards -->
        <?php if (isset($allocations) && $allocations): ?>
            <?php
            $sms_promo_active = isset($allocations->sms_promo_active) ? (int) $allocations->sms_promo_active : 1;
            $sms_trans_active = isset($allocations->sms_trans_active) ? (int) $allocations->sms_trans_active : 1;
            ?>
            <div class="ccx-balance-row">
                <div class="ccx-balance-card bc-indigo <?= !$sms_promo_active ? 'bc-inactive' : ''; ?>">
                    <div class="bc-label">Promotional Balance</div>
                    <div class="bc-value"><?= number_format($allocations->sms_promo_count); ?></div>
                    <div class="bc-expiry">
                        <?php if (!$sms_promo_active): ?>
                            <span class="bc-inactive-badge"><i class="fa fa-ban"></i> Not Activated</span>
                        <?php else: ?>
                            <?php
                            $days_left = 0;
                            $is_expired = false;
                            if ($allocations->sms_promo_expiry) {
                                $diff = strtotime($allocations->sms_promo_expiry) - time();
                                if ($diff < 0) {
                                    $is_expired = true;
                                } else {
                                    $days_left = ceil($diff / 86400);
                                }
                            }
                            ?>
                            <?php if ($allocations->sms_promo_expiry): ?>
                                <?php if ($is_expired): ?>
                                    <span class="bc-badge expired">Expired</span>
                                <?php else: ?>
                                    Expires: <?= _d($allocations->sms_promo_expiry); ?>
                                    <span class="bc-badge"><?= $days_left; ?> days left</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="bc-badge no-expiry">No Expiry</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php $this->load->view('tabs/_channel_switch', ['switch_channel' => 'sms', 'switch_subtype' => 'promo']); ?>
                </div>
                <div class="ccx-balance-card bc-indigo <?= !$sms_trans_active ? 'bc-inactive' : ''; ?>"
                    style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);">
                    <div class="bc-label">Transactional Balance</div>
                    <div class="bc-value"><?= number_format($allocations->sms_trans_count); ?></div>
                    <div class="bc-expiry">
                        <?php if (!$sms_trans_active): ?>
                            <span class="bc-inactive-badge"><i class="fa fa-ban"></i> Not Activated</span>
                        <?php else: ?>
                            <?php
                            $days_left_t = 0;
                            $is_expired_t = false;
                            if ($allocations->sms_trans_expiry) {
                                $diff_t = strtotime($allocations->sms_trans_expiry) - time();
                                if ($diff_t < 0) {
                                    $is_expired_t = true;
                                } else {
                                    $days_left_t = ceil($diff_t / 86400);
                                }
                            }
                            ?>
                            <?php if ($allocations->sms_trans_expiry): ?>
                                <?php if ($is_expired_t): ?>
                                    <span class="bc-badge expired">Expired</span>
                                <?php else: ?>
                                    Expires: <?= _d($allocations->sms_trans_expiry); ?>
                                    <span class="bc-badge"><?= $days_left_t; ?> days left</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="bc-badge no-expiry">No Expiry</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php $this->load->view('tabs/_channel_switch', ['switch_channel' => 'sms', 'switch_subtype' => 'trans']); ?>
                </div>
            </div>
        <?php else: ?>
            <?php $this->load->view('tabs/_channel_switch_cards', ['switch_channel' => 'sms']); ?>
        <?php endif; ?>

        <!-- Channel Stats (removed per request) -->

        <!-- Info Alert -->
        <div class="ccx-info-alert info-sms">
            <i class="fa fa-info-circle"></i>
            Configure the message templates that will be used when sending SMS notifications.
        </div>

        <?php // Feature-wise permissions — the Hooks panel disappears without
              // the `hooks` capability, and Templates falls back to read-only.
              $show_hooks = !empty($can_hooks); ?>

        <!-- Sub-Tab Bar -->
        <div class="ccx-sub-pill-bar">
            <?php if ($show_hooks): ?>
            <a href="#" class="ccx-sub-pill active" data-sub-target="hooks">
                <i class="fa fa-plug"></i> Hooks
            </a>
            <?php endif; ?>
            <a href="#" class="ccx-sub-pill <?= $show_hooks ? '' : 'active'; ?>" data-sub-target="templates">
                <i class="fa fa-list-alt"></i> Templates
            </a>
        </div>

        <!-- Sub-Panel: Templates -->
        <div class="ccx-sub-panel" data-sub-panel="templates" <?= $show_hooks ? 'style="display:none;"' : ''; ?>>
            <div class="ccx-templates-section">
                <div class="ccx-templates-header">
                    <h5><i class="fa fa-list-alt" style="margin-right:8px; color:#6366f1;"></i>Templates</h5>
                    <div class="ccx-templates-tools">
                    <?php $this->load->view('tabs/_templates_search', ['search_type' => 'sms']); ?>
                    <?php if (!empty($can_templates)): ?>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <?php // Bulk import of a DLT approval sheet — reads only
                              // message / sender_id / approved_message and skips
                              // anything already stored. See import_preview(). ?>
                        <button type="button" class="ccx-add-btn import-templates-btn" data-type="sms"
                            style="background: linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%);"
                            title="Import approved templates from an Excel or CSV sheet">
                            <i class="fa fa-file-excel-o"></i> Import from Excel
                        </button>
                        <button type="button" class="ccx-add-btn btn-sms add-template-btn" data-type="sms">
                            <i class="fa fa-plus"></i> Add Template
                        </button>
                    </div>
                    <?php endif; ?>
                    </div>
                </div>
                <div class="ccx-table-wrap">
                    <table class="table" id="sms-templates-table">
                        <thead>
                            <tr>
                                <th width="20%">Title</th>
                                <th width="35%">Content</th>
                                <th class="text-center" width="12%">Type</th>
                                <th class="text-center" width="12%">Active</th>
                                <th class="text-center" width="12%">Default</th>
                                <th width="9%">Action</th>
                            </tr>
                        </thead>
                        <tbody id="sms-templates-wrapper">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sub-Panel: Hooks -->
        <?php if ($show_hooks): ?>
        <div class="ccx-sub-panel" data-sub-panel="hooks">
            <?php $this->load->view('tabs/_hooks_panel', ['hooks_channel' => 'sms', 'can_logs' => !empty($can_logs)]); ?>
        </div>
        <?php endif; ?>

    </div>
<?php endif; ?>