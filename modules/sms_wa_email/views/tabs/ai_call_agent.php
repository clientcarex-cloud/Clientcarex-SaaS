<?php if (!isset($channel_visible) || $channel_visible): ?>
    <div role="tabpanel" class="tab-pane <?= (isset($is_default_tab) && $is_default_tab) ? 'active' : ''; ?>"
        id="ai_call_agent">


        <!-- Balance Cards -->
        <?php if (isset($allocations) && $allocations): ?>
            <?php
            $ai_promo_active = isset($allocations->aicall_promo_active) ? (int) $allocations->aicall_promo_active : 1;
            $ai_trans_active = isset($allocations->aicall_trans_active) ? (int) $allocations->aicall_trans_active : 1;
            ?>
            <div class="ccx-balance-row">
                <div class="ccx-balance-card bc-purple <?= !$ai_promo_active ? 'bc-inactive' : ''; ?>">
                    <div class="bc-label">Promotional Balance</div>
                    <div class="bc-value"><?= number_format($allocations->aicall_promo_count); ?></div>
                    <div class="bc-expiry">
                        <?php if (!$ai_promo_active): ?>
                            <span class="bc-inactive-badge"><i class="fa fa-ban"></i> Not Activated</span>
                        <?php else: ?>
                            <?php
                            $days_left = 0;
                            $is_expired = false;
                            if ($allocations->aicall_promo_expiry) {
                                $diff = strtotime($allocations->aicall_promo_expiry) - time();
                                if ($diff < 0) {
                                    $is_expired = true;
                                } else {
                                    $days_left = ceil($diff / 86400);
                                }
                            }
                            ?>
                            <?php if ($allocations->aicall_promo_expiry): ?>
                                <?php if ($is_expired): ?>
                                    <span class="bc-badge expired">Expired</span>
                                <?php else: ?>
                                    Expires: <?= _d($allocations->aicall_promo_expiry); ?>
                                    <span class="bc-badge"><?= $days_left; ?> days left</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="bc-badge no-expiry">No Expiry</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php $this->load->view('tabs/_channel_switch', ['switch_channel' => 'ai_call_agent', 'switch_subtype' => 'promo']); ?>
                </div>
                <div class="ccx-balance-card bc-purple <?= !$ai_trans_active ? 'bc-inactive' : ''; ?>"
                    style="background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);">
                    <div class="bc-label">Transactional Balance</div>
                    <div class="bc-value"><?= number_format($allocations->aicall_trans_count); ?></div>
                    <div class="bc-expiry">
                        <?php if (!$ai_trans_active): ?>
                            <span class="bc-inactive-badge"><i class="fa fa-ban"></i> Not Activated</span>
                        <?php else: ?>
                            <?php
                            $days_left_t = 0;
                            $is_expired_t = false;
                            if ($allocations->aicall_trans_expiry) {
                                $diff_t = strtotime($allocations->aicall_trans_expiry) - time();
                                if ($diff_t < 0) {
                                    $is_expired_t = true;
                                } else {
                                    $days_left_t = ceil($diff_t / 86400);
                                }
                            }
                            ?>
                            <?php if ($allocations->aicall_trans_expiry): ?>
                                <?php if ($is_expired_t): ?>
                                    <span class="bc-badge expired">Expired</span>
                                <?php else: ?>
                                    Expires: <?= _d($allocations->aicall_trans_expiry); ?>
                                    <span class="bc-badge"><?= $days_left_t; ?> days left</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="bc-badge no-expiry">No Expiry</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php $this->load->view('tabs/_channel_switch', ['switch_channel' => 'ai_call_agent', 'switch_subtype' => 'trans']); ?>
                </div>
            </div>
        <?php else: ?>
            <?php $this->load->view('tabs/_channel_switch_cards', ['switch_channel' => 'ai_call_agent']); ?>
        <?php endif; ?>

        <!-- Channel Stats (removed per request) -->

        <!-- Info Alert -->
        <div class="ccx-info-alert info-ai">
            <i class="fa fa-info-circle"></i>
            Configure AI Call Agent templates with TTS or Voice Note options, and manage hooks.
        </div>

        <?php // Feature-wise permissions — see the SMS tab for the same pattern
              $show_hooks = !empty($can_hooks); ?>

        <!-- Sub-Tab Bar -->
        <div class="ccx-sub-pill-bar">
            <a href="#" class="ccx-sub-pill active" data-sub-target="templates">
                <i class="fa fa-list-alt"></i> Templates
            </a>
            <?php if ($show_hooks): ?>
            <a href="#" class="ccx-sub-pill" data-sub-target="hooks">
                <i class="fa fa-plug"></i> Hooks
            </a>
            <?php endif; ?>
        </div>

        <!-- Sub-Panel: Templates -->
        <div class="ccx-sub-panel" data-sub-panel="templates">
            <div class="ccx-templates-section">
                <div class="ccx-templates-header">
                    <h5><i class="fa fa-list-alt" style="margin-right:8px; color:#a855f7;"></i>Templates</h5>
                    <div class="ccx-templates-tools">
                        <?php $this->load->view('tabs/_templates_search', ['search_type' => 'ai_call_agent']); ?>
                        <?php if (!empty($can_templates)): ?>
                        <button type="button" class="ccx-add-btn add-template-btn" data-type="ai_call_agent"
                            style="background: linear-gradient(135deg, #a855f7, #7c3aed); color:#fff;">
                            <i class="fa fa-plus"></i> Add Template
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ccx-table-wrap">
                    <table class="table" id="ai_call_agent-templates-table">
                        <thead>
                            <tr>
                                <th width="18%">Title</th>
                                <th width="14%">Voice Type</th>
                                <th class="text-center" width="12%">Message Type</th>
                                <th class="text-center" width="10%">Retry</th>
                                <th class="text-center" width="10%">Active</th>
                                <th class="text-center" width="10%">Default</th>
                                <th width="9%">Action</th>
                            </tr>
                        </thead>
                        <tbody id="ai_call_agent-templates-wrapper">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sub-Panel: Hooks -->
        <?php if ($show_hooks): ?>
        <div class="ccx-sub-panel" data-sub-panel="hooks" style="display:none;">
            <?php $this->load->view('tabs/_hooks_panel', ['hooks_channel' => 'ai_call_agent', 'can_logs' => !empty($can_logs)]); ?>
        </div>
        <?php endif; ?>

    </div>
<?php endif; ?>