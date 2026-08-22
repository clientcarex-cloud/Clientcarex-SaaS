<?php if (!isset($channel_visible) || $channel_visible): ?>
    <div role="tabpanel" class="tab-pane <?= (isset($is_default_tab) && $is_default_tab) ? 'active' : ''; ?>" id="email">


        <!-- Balance Cards -->
        <?php if (isset($allocations) && $allocations): ?>
            <?php
            $email_promo_active = isset($allocations->email_promo_active) ? (int) $allocations->email_promo_active : 1;
            $email_trans_active = isset($allocations->email_trans_active) ? (int) $allocations->email_trans_active : 1;
            ?>
            <div class="ccx-balance-row">
                <div class="ccx-balance-card bc-amber <?= !$email_promo_active ? 'bc-inactive' : ''; ?>">
                    <div class="bc-label">Promotional Balance</div>
                    <div class="bc-value"><?= number_format($allocations->email_promo_count); ?></div>
                    <div class="bc-expiry">
                        <?php if (!$email_promo_active): ?>
                            <span class="bc-inactive-badge"><i class="fa fa-ban"></i> Not Activated</span>
                        <?php else: ?>
                            <?php
                            $days_left = 0;
                            $is_expired = false;
                            if ($allocations->email_promo_expiry) {
                                $diff = strtotime($allocations->email_promo_expiry) - time();
                                if ($diff < 0) {
                                    $is_expired = true;
                                } else {
                                    $days_left = ceil($diff / 86400);
                                }
                            }
                            ?>
                            <?php if ($allocations->email_promo_expiry): ?>
                                <?php if ($is_expired): ?>
                                    <span class="bc-badge expired">Expired</span>
                                <?php else: ?>
                                    Expires: <?= _d($allocations->email_promo_expiry); ?>
                                    <span class="bc-badge"><?= $days_left; ?> days left</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="bc-badge no-expiry">No Expiry</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php $this->load->view('tabs/_channel_switch', ['switch_channel' => 'email', 'switch_subtype' => 'promo']); ?>
                </div>
                <div class="ccx-balance-card bc-amber <?= !$email_trans_active ? 'bc-inactive' : ''; ?>"
                    style="background: linear-gradient(135deg, #d97706 0%, #92400e 100%);">
                    <div class="bc-label">Transactional Balance</div>
                    <div class="bc-value"><?= number_format($allocations->email_trans_count); ?></div>
                    <div class="bc-expiry">
                        <?php if (!$email_trans_active): ?>
                            <span class="bc-inactive-badge"><i class="fa fa-ban"></i> Not Activated</span>
                        <?php else: ?>
                            <?php
                            $days_left_t = 0;
                            $is_expired_t = false;
                            if ($allocations->email_trans_expiry) {
                                $diff_t = strtotime($allocations->email_trans_expiry) - time();
                                if ($diff_t < 0) {
                                    $is_expired_t = true;
                                } else {
                                    $days_left_t = ceil($diff_t / 86400);
                                }
                            }
                            ?>
                            <?php if ($allocations->email_trans_expiry): ?>
                                <?php if ($is_expired_t): ?>
                                    <span class="bc-badge expired">Expired</span>
                                <?php else: ?>
                                    Expires: <?= _d($allocations->email_trans_expiry); ?>
                                    <span class="bc-badge"><?= $days_left_t; ?> days left</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="bc-badge no-expiry">No Expiry</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php $this->load->view('tabs/_channel_switch', ['switch_channel' => 'email', 'switch_subtype' => 'trans']); ?>
                </div>
            </div>
        <?php else: ?>
            <?php $this->load->view('tabs/_channel_switch_cards', ['switch_channel' => 'email']); ?>
        <?php endif; ?>

        <!-- Channel Stats (removed per request) -->

        <!-- Info Alert -->
        <div class="ccx-info-alert info-email">
            <i class="fa fa-info-circle"></i>
            Set up the default email subject and message body templates.
        </div>

        <?php // Feature-wise permissions — see the SMS tab for the same pattern
              $show_hooks = !empty($can_hooks);
              // CRM routing is an installation-wide switch, so it lives behind
              // the same capability as the rest of the advanced settings.
              $crm        = (isset($crm_routing) && is_array($crm_routing)) ? $crm_routing : [];
              $show_crm   = !empty($can_settings) && !empty($crm['available']); ?>

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
            <?php if ($show_crm): ?>
            <a href="#" class="ccx-sub-pill" data-sub-target="crm_routing">
                <i class="fa fa-random"></i> CRM Routing
                <?php if (!empty($crm['enabled'])): ?>
                <span class="ccx-chip" style="background:#dcfce7; color:#166534; font-size:9px; margin-left:6px;">ON</span>
                <?php else: ?>
                <span class="ccx-chip" style="background:#fef2f2; color:#991b1b; font-size:9px; margin-left:6px;">OFF</span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
        </div>

        <!-- Sub-Panel: Templates -->
        <div class="ccx-sub-panel" data-sub-panel="templates" <?= $show_hooks ? 'style="display:none;"' : ''; ?>>
            <div class="ccx-templates-section">
                <div class="ccx-templates-header">
                    <h5><i class="fa fa-list-alt" style="margin-right:8px; color:#f59e0b;"></i>Templates</h5>
                    <div class="ccx-templates-tools">
                    <?php $this->load->view('tabs/_templates_search', ['search_type' => 'email']); ?>
                    <?php if (!empty($can_templates)): ?>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <?php // One ready-made, branded template per registered hook. Never
                              // overwrites anything and never switches a hook on by itself —
                              // see helpers/sms_wa_email_email_seeder_helper.php ?>
                        <button type="button" class="ccx-add-btn seed-hook-templates-btn"
                            style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none;"
                            data-loading-text="<i class='fa fa-spinner fa-spin'></i> Loading…"
                            title="Create a ready-made email template for every system hook that does not have one yet">
                            <i class="fa fa-magic"></i> Load Hook Templates
                        </button>
                        <button type="button" class="ccx-add-btn btn-email add-template-btn" data-type="email">
                            <i class="fa fa-plus"></i> Add Template
                        </button>
                    </div>
                    <?php endif; ?>
                    </div>
                </div>
                <div class="ccx-table-wrap">
                    <table class="table" id="email-templates-table">
                        <thead>
                            <tr>
                                <th width="18%">Subject / Title</th>
                                <th width="30%">Message Template</th>
                                <th class="text-center" width="12%">Type</th>
                                <th class="text-center" width="10%">Attachment</th>
                                <th class="text-center" width="10%">Active</th>
                                <th class="text-center" width="10%">Default</th>
                                <th width="7%">Action</th>
                            </tr>
                        </thead>
                        <tbody id="email-templates-wrapper">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sub-Panel: Hooks -->
        <?php if ($show_hooks): ?>
        <div class="ccx-sub-panel" data-sub-panel="hooks">
            <?php $this->load->view('tabs/_hooks_panel', ['hooks_channel' => 'email', 'can_logs' => !empty($can_logs)]); ?>
        </div>
        <?php endif; ?>

        <!-- Sub-Panel: CRM Routing -->
        <?php if ($show_crm): ?>
        <div class="ccx-sub-panel" data-sub-panel="crm_routing" style="display:none;">
            <div class="ccx-templates-section">
                <div class="ccx-templates-header">
                    <h5><i class="fa fa-random" style="margin-right:8px; color:#f59e0b;"></i>CRM Email Routing</h5>
                </div>

                <div class="ccx-info-alert info-email" style="margin-bottom:18px;">
                    <i class="fa fa-info-circle"></i>
                    While this is on, <strong>every</strong> email the CRM sends — invoices and their PDF,
                    tickets, estimates, contracts, password resets, staff notifications and anything drained
                    from the email queue — leaves through this channel: balance checked first, credit
                    deducted after, and one row in <strong>View Logs</strong> for each one.
                    Hook, campaign and auto-scheduler emails are billed by their own engine and pass
                    through untouched.
                </div>

                <!-- Counters -->
                <div class="ccx-balance-row" style="margin-bottom:18px;">
                    <div class="ccx-balance-card" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);">
                        <div class="bc-label">Routed Today</div>
                        <div class="bc-value"><?= number_format((int) $crm['stats']['today']); ?></div>
                        <div class="bc-expiry">
                            <?php if ((int) $crm['stats']['today_failed'] > 0): ?>
                            <span class="bc-badge expired"><?= number_format((int) $crm['stats']['today_failed']); ?> failed</span>
                            <?php else: ?>
                            <span class="bc-badge no-expiry">No failures</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="ccx-balance-card bc-amber" style="background: linear-gradient(135deg, #d97706 0%, #92400e 100%);">
                        <div class="bc-label">In The Log</div>
                        <div class="bc-value"><?= number_format((int) $crm['stats']['total']); ?></div>
                        <div class="bc-expiry">
                            <?php // Trigger logs are purged after 48 hours by cron. ?>
                            <span class="bc-badge no-expiry">Last 48 hours</span>
                        </div>
                    </div>
                    <div class="ccx-balance-card" style="background: linear-gradient(135deg, #b91c1c 0%, #7f1d1d 100%);">
                        <div class="bc-label">Blocked (no balance)</div>
                        <div class="bc-value"><?= number_format((int) $crm['stats']['blocked']); ?></div>
                        <div class="bc-expiry">
                            <span class="bc-badge no-expiry">Recharge to clear</span>
                        </div>
                    </div>
                </div>

                <!-- Sender -->
                <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px; margin-bottom:18px;">
                    <div style="font-size:12px; color:#6b7280; margin-bottom:4px;">
                        <i class="fa fa-paper-plane" style="margin-right:6px; color:#f59e0b;"></i>Emails will be sent from
                    </div>
                    <div style="font-size:14px; font-weight:600; color:#111827;">
                        <?= $crm['sender'] !== '' ? html_escape($crm['sender']) : '<span style="color:#b91c1c;">no sender configured</span>'; ?>
                    </div>
                    <div style="font-size:11px; color:#9ca3af; margin-top:4px;">
                        <?php if ($crm['sender_mode'] === 'api'): ?>
                        Email API<?= $crm['api_name'] !== '' ? ': ' . html_escape($crm['api_name']) : ''; ?> —
                        its SMTP sender replaces the CRM's for these sends, and the CRM's own address is kept as Reply-To.
                        <?php else: ?>
                        This installation's own Setup → Settings → Email SMTP. Credits are still counted and logged.
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Switches -->
                <form id="crm-routing-form">
                    <?php
                    $crm_switches = [
                        [
                            'name'  => 'sms_wa_email_route_crm_email',
                            'on'    => !empty($crm['enabled']),
                            'label' => 'Route CRM emails through Omni Messaging',
                            'help'  => 'Off means the CRM sends its email exactly as it did before this module — no credits, no logs.',
                        ],
                        [
                            'name'  => 'sms_wa_email_crm_email_use_api_smtp',
                            'on'    => !empty($crm['use_api_smtp']),
                            'label' => 'Deliver through the Email API sender',
                            'help'  => 'Off keeps this installation\'s own SMTP as the transport and only adds credits and logging.',
                        ],
                        [
                            'name'  => 'sms_wa_email_crm_email_block_no_balance',
                            'on'    => !empty($crm['block_no_balance']),
                            'label' => 'Block sending when the email balance is empty or expired',
                            'help'  => 'Off delivers anyway and marks the log row as unbilled — safer for password resets, weaker as a credit gate.',
                        ],
                        [
                            'name'  => 'sms_wa_email_crm_email_fallback_smtp',
                            'on'    => !empty($crm['fallback_smtp']),
                            'label' => 'Retry on this installation\'s SMTP if the API sender fails',
                            'help'  => 'One retry, still billed and logged — keeps a dead relay from taking system email down with it.',
                        ],
                        [
                            'name'  => 'sms_wa_email_crm_email_reply_to_original',
                            'on'    => !empty($crm['reply_to_original']),
                            'label' => 'Keep the CRM address as Reply-To',
                            'help'  => 'Applies when the API sender replaces the From address, so replies still reach this installation.',
                        ],
                    ];
                    ?>
                    <?php foreach ($crm_switches as $sw): ?>
                    <div style="display:flex; align-items:flex-start; gap:12px; padding:12px 0; border-bottom:1px solid #f3f4f6;">
                        <div class="onoffswitch" style="margin-top:2px;">
                            <input type="checkbox" class="onoffswitch-checkbox crm-routing-switch"
                                id="<?= $sw['name']; ?>" name="<?= $sw['name']; ?>" <?= $sw['on'] ? 'checked' : ''; ?>>
                            <label class="onoffswitch-label" for="<?= $sw['name']; ?>"></label>
                        </div>
                        <div>
                            <label for="<?= $sw['name']; ?>" style="font-size:13px; font-weight:600; color:#111827; margin:0; cursor:pointer;">
                                <?= $sw['label']; ?>
                            </label>
                            <div style="font-size:11px; color:#6b7280; margin-top:2px;"><?= $sw['help']; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div style="margin-top:18px; display:flex; align-items:center; gap:12px;">
                        <button type="submit" class="ccx-btn-save" id="crm-routing-save">
                            <i class="fa fa-check"></i> Save Routing Settings
                        </button>
                        <?php if (!empty($can_logs)): ?>
                        <button type="button" class="ccx-add-btn view-hook-logs-btn" data-channel="email"
                            style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none;">
                            <i class="fa fa-list-ul"></i> View Logs
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>
<?php endif; ?>