<?php
/**
 * Official WhatsApp channel tab.
 *
 * When the WhatsApp module is connected, this channel stops going through the
 * provider's gateway API row and sends from the tenant's own WhatsApp Cloud API
 * number — billed per 24-hour conversation instead of per message.
 */
$wa_cloud     = isset($wa_cloud) && is_array($wa_cloud) ? $wa_cloud : ['installed' => false, 'status' => null, 'stats' => null, 'recent' => [], 'templates' => [], 'module_url' => admin_url('whatsapp')];
$wa_status    = $wa_cloud['status'];
$wa_ready     = $wa_status && !empty($wa_status['ready']);
$wa_stats     = $wa_cloud['stats'];
$wa_sync      = isset($wa_cloud['autosync']) && is_array($wa_cloud['autosync']) ? $wa_cloud['autosync'] : null;
$wa_needs     = isset($wa_cloud['needs_params']) ? (int) $wa_cloud['needs_params'] : 0;
$wa_approved  = $wa_sync && isset($wa_sync['approved']) ? (int) $wa_sync['approved'] : count($wa_cloud['templates']);
$wa_skipped   = $wa_sync && isset($wa_sync['skipped']) ? (int) $wa_sync['skipped'] : 0;
?>
<?php if (!isset($channel_visible) || $channel_visible): ?>
    <div role="tabpanel" class="tab-pane <?= (isset($is_default_tab) && $is_default_tab) ? 'active' : ''; ?>"
        id="official_whatsapp">

        <style>
            .wa-link-card {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 14px 18px;
                border-radius: 12px;
                border: 1px solid #e5e7eb;
                background: #f9fafb;
                margin-bottom: 16px;
            }

            .wa-link-card.is-live {
                border-color: #bbf7d0;
                background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            }

            .wa-link-card.is-warning {
                border-color: #fde68a;
                background: #fffbeb;
            }

            .wa-link-icon {
                width: 42px;
                height: 42px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
                color: #fff;
                background: linear-gradient(135deg, #22c55e, #16a34a);
                flex: 0 0 42px;
            }

            .wa-link-card.is-warning .wa-link-icon {
                background: linear-gradient(135deg, #f59e0b, #d97706);
            }

            .wa-link-card:not(.is-live):not(.is-warning) .wa-link-icon {
                background: linear-gradient(135deg, #9ca3af, #6b7280);
            }

            .wa-link-body {
                flex: 1;
                min-width: 0;
            }

            .wa-link-title {
                font-size: 14px;
                font-weight: 600;
                color: #111827;
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            .wa-link-sub {
                font-size: 12px;
                color: #6b7280;
                margin-top: 3px;
            }

            .wa-tag {
                font-size: 10px;
                font-weight: 600;
                letter-spacing: .3px;
                text-transform: uppercase;
                padding: 3px 8px;
                border-radius: 20px;
            }

            .wa-tag-live {
                background: #dcfce7;
                color: #166534;
            }

            .wa-tag-mode {
                background: #dbeafe;
                color: #1e40af;
            }

            .wa-tag-off {
                background: #e5e7eb;
                color: #4b5563;
            }

            .wa-link-action {
                flex: 0 0 auto;
            }

            .wa-conv-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                gap: 12px;
                margin-bottom: 16px;
            }

            .wa-conv-card {
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                padding: 14px 16px;
                background: #fff;
            }

            .wa-conv-value {
                font-size: 22px;
                font-weight: 700;
                color: #111827;
                line-height: 1.1;
            }

            .wa-conv-label {
                font-size: 11px;
                color: #6b7280;
                margin-top: 4px;
                text-transform: uppercase;
                letter-spacing: .4px;
            }

            .wa-conv-card.accent-green .wa-conv-value {
                color: #16a34a;
            }

            .wa-conv-card.accent-amber .wa-conv-value {
                color: #d97706;
            }
        </style>

        <!-- ── Cloud API link state ─────────────────────────────────────── -->
        <?php if ($wa_ready): ?>
            <div class="wa-link-card is-live">
                <div class="wa-link-icon"><i class="fab fa-whatsapp"></i></div>
                <div class="wa-link-body">
                    <?php
                    // 'shared' = sending on the provider's own number under a
                    // grant, so the copy must not claim this is "your" number.
                    $wa_shared = ($wa_status['mode'] ?? 'own') === 'shared';
                    $wa_brand  = (string) ($wa_status['brand'] ?? '');
                    ?>
                    <div class="wa-link-title">
                        <?= $wa_shared ? 'Connected via ' . html_escape($wa_brand) . '&rsquo;s WhatsApp number' : 'Connected to WhatsApp Cloud API'; ?>
                        <span class="wa-tag wa-tag-live">Live</span>
                        <span class="wa-tag wa-tag-mode">
                            <?= ($wa_status['billing'] ?? '') === 'free' ? 'Free — included in your plan' : '24-hour conversation billing'; ?>
                        </span>
                    </div>
                    <div class="wa-link-sub">
                        Sending from <strong><?= html_escape($wa_status['number_label']); ?></strong>
                        <?php if (!empty($wa_status['waba_name'])): ?>
                            · <?= html_escape($wa_status['waba_name']); ?>
                        <?php endif; ?>
                        <?php if ($wa_shared): ?>
                            — hooks, campaigns and auto-schedulers on this channel go out over that number, using only the
                            approved templates <?= html_escape($wa_brand); ?> has shared with this account.
                        <?php else: ?>
                            — hooks, campaigns and auto-schedulers on this channel now go out over your own number.
                        <?php endif; ?>
                    </div>
                </div>
                <div class="wa-link-action">
                    <a href="<?= $wa_cloud['module_url']; ?>" class="ccx-add-btn btn-wa">
                        <i class="fa fa-cog"></i> Manage
                    </a>
                </div>
            </div>
        <?php elseif ($wa_cloud['installed']): ?>
            <div class="wa-link-card is-warning">
                <div class="wa-link-icon"><i class="fab fa-whatsapp"></i></div>
                <div class="wa-link-body">
                    <div class="wa-link-title">
                        WhatsApp Cloud API not connected yet
                        <span class="wa-tag wa-tag-off">Gateway mode</span>
                    </div>
                    <div class="wa-link-sub">
                        <?= html_escape($wa_status['reason'] ?: 'Connect your WhatsApp Business Account to send from your own number.'); ?>
                        Until then this channel keeps using the configured gateway API and is billed per message.
                    </div>
                </div>
                <div class="wa-link-action">
                    <a href="<?= $wa_cloud['module_url']; ?>" class="ccx-add-btn btn-wa">
                        <i class="fa fa-plug"></i> Connect
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="wa-link-card">
                <div class="wa-link-icon"><i class="fab fa-whatsapp"></i></div>
                <div class="wa-link-body">
                    <div class="wa-link-title">
                        Gateway mode
                        <span class="wa-tag wa-tag-off">Per-message billing</span>
                    </div>
                    <div class="wa-link-sub">
                        Official WhatsApp is delivered through the configured gateway API. Activate the WhatsApp
                        module to send from your own WhatsApp Business number and switch to 24-hour conversation billing.
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Balance Cards -->
        <?php if (isset($allocations) && $allocations): ?>
            <?php
            $wa_promo_active = isset($allocations->whatsapp_promo_active) ? (int) $allocations->whatsapp_promo_active : 1;
            $wa_trans_active = isset($allocations->whatsapp_trans_active) ? (int) $allocations->whatsapp_trans_active : 1;
            // With the Cloud API live, one credit buys one 24-hour conversation
            // (marketing → promotional, utility/authentication → transactional).
            $wa_unit_promo = $wa_ready ? 'Marketing conversations' : 'Promotional Balance';
            $wa_unit_trans = $wa_ready ? 'Utility conversations' : 'Transactional Balance';
            ?>
            <div class="ccx-balance-row">
                <div class="ccx-balance-card bc-green <?= !$wa_promo_active ? 'bc-inactive' : ''; ?>">
                    <div class="bc-label"><?= $wa_unit_promo; ?></div>
                    <div class="bc-value"><?= number_format($allocations->whatsapp_promo_count); ?></div>
                    <div class="bc-expiry">
                        <?php if (!$wa_promo_active): ?>
                            <span class="bc-inactive-badge"><i class="fa fa-ban"></i> Not Activated</span>
                        <?php else: ?>
                            <?php
                            $days_left = 0;
                            $is_expired = false;
                            if ($allocations->whatsapp_promo_expiry) {
                                $diff = strtotime($allocations->whatsapp_promo_expiry) - time();
                                if ($diff < 0) {
                                    $is_expired = true;
                                } else {
                                    $days_left = ceil($diff / 86400);
                                }
                            }
                            ?>
                            <?php if ($allocations->whatsapp_promo_expiry): ?>
                                <?php if ($is_expired): ?>
                                    <span class="bc-badge expired">Expired</span>
                                <?php else: ?>
                                    Expires: <?= _d($allocations->whatsapp_promo_expiry); ?>
                                    <span class="bc-badge"><?= $days_left; ?> days left</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="bc-badge no-expiry">No Expiry</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php $this->load->view('tabs/_channel_switch', ['switch_channel' => 'official_whatsapp', 'switch_subtype' => 'promo']); ?>
                </div>
                <div class="ccx-balance-card bc-green <?= !$wa_trans_active ? 'bc-inactive' : ''; ?>"
                    style="background: linear-gradient(135deg, #16a34a 0%, #166534 100%);">
                    <div class="bc-label"><?= $wa_unit_trans; ?></div>
                    <div class="bc-value"><?= number_format($allocations->whatsapp_trans_count); ?></div>
                    <div class="bc-expiry">
                        <?php if (!$wa_trans_active): ?>
                            <span class="bc-inactive-badge"><i class="fa fa-ban"></i> Not Activated</span>
                        <?php else: ?>
                            <?php
                            $days_left_t = 0;
                            $is_expired_t = false;
                            if ($allocations->whatsapp_trans_expiry) {
                                $diff_t = strtotime($allocations->whatsapp_trans_expiry) - time();
                                if ($diff_t < 0) {
                                    $is_expired_t = true;
                                } else {
                                    $days_left_t = ceil($diff_t / 86400);
                                }
                            }
                            ?>
                            <?php if ($allocations->whatsapp_trans_expiry): ?>
                                <?php if ($is_expired_t): ?>
                                    <span class="bc-badge expired">Expired</span>
                                <?php else: ?>
                                    Expires: <?= _d($allocations->whatsapp_trans_expiry); ?>
                                    <span class="bc-badge"><?= $days_left_t; ?> days left</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="bc-badge no-expiry">No Expiry</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php $this->load->view('tabs/_channel_switch', ['switch_channel' => 'official_whatsapp', 'switch_subtype' => 'trans']); ?>
                </div>
            </div>
        <?php else: ?>
            <?php $this->load->view('tabs/_channel_switch_cards', ['switch_channel' => 'official_whatsapp']); ?>
        <?php endif; ?>

        <!-- Info Alert -->
        <div class="ccx-info-alert info-wa">
            <i class="fa fa-info-circle"></i>
            <?php if ($wa_ready): ?>
                One credit opens one <strong>24-hour conversation</strong> with a contact — every further message to
                that contact inside the window is free, whether it comes from a hook, a campaign or the inbox.
                Marketing conversations draw on the promotional balance, utility and authentication on the
                transactional balance, and replies inside a customer-opened window cost nothing.
            <?php else: ?>
                Configure approved templates that will be sent via the Official WhatsApp API.
            <?php endif; ?>
        </div>

        <?php // Feature-wise permissions — see the SMS tab for the same pattern
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
            <?php if ($wa_ready): ?>
                <a href="#" class="ccx-sub-pill" data-sub-target="conversations">
                    <i class="fa fa-comments"></i> Conversations
                </a>
            <?php endif; ?>
        </div>

        <!-- Sub-Panel: Templates -->
        <div class="ccx-sub-panel" data-sub-panel="templates" <?= $show_hooks ? 'style="display:none;"' : ''; ?>>
            <div class="ccx-templates-section">
                <div class="ccx-templates-header">
                    <h5><i class="fa fa-list-alt" style="margin-right:8px; color:#22c55e;"></i>Templates</h5>
                    <div class="ccx-templates-tools">
                        <?php $this->load->view('tabs/_templates_search', ['search_type' => 'whatsapp']); ?>
                        <?php if ($wa_ready && !empty($can_templates)): ?>
                            <button type="button" class="ccx-add-btn btn-wa" id="waTemplateSyncBtn"
                                style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); border: none;">
                                <i class="fa fa-refresh"></i> Sync from WhatsApp
                            </button>
                        <?php endif; ?>
                        <?php if (!empty($can_templates)): ?>
                        <button type="button" class="ccx-add-btn btn-wa add-template-btn" data-type="whatsapp">
                            <i class="fa fa-plus"></i> Add Template
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($wa_ready): ?>
                    <!-- Auto-map state — every approved Meta template is mirrored here as a
                         usable Omni template, so the Hooks tab can pick it straight away. -->
                    <div class="ccx-info-alert info-wa" style="margin: 0 0 14px;" id="waAutoMapNote">
                        <i class="fa fa-magic"></i>
                        Your <strong><?= $wa_approved; ?></strong> approved Meta template<?= $wa_approved === 1 ? '' : 's'; ?>
                        <?= $wa_approved === 1 ? 'is' : 'are'; ?> mapped onto this channel automatically — pick them in
                        <strong>Hooks</strong>, campaigns or auto-schedulers like any other template. Editing one here
                        makes it yours and stops it being refreshed from Meta. A template with no Meta mapping goes out
                        as free-form text while the contact's 24-hour window is open, and falls back to the gateway API
                        once it closes.
                        <?php if ($wa_skipped > 0): ?>
                            <br><span style="color:#92400e;">
                                <?= $wa_skipped; ?> template<?= $wa_skipped === 1 ? ' was' : 's were'; ?> skipped —
                                authentication templates and templates with an image/video/document header need a per-send
                                asset, which a hook cannot supply.
                            </span>
                        <?php endif; ?>
                        <?php if ($wa_needs > 0): ?>
                            <br><span style="color:#b45309;">
                                <i class="fa fa-exclamation-triangle"></i>
                                <strong><?= $wa_needs; ?></strong> mapped template<?= $wa_needs === 1 ? '' : 's'; ?>
                                still need<?= $wa_needs === 1 ? 's' : ''; ?> <?= $wa_needs === 1 ? 'its' : 'their'; ?>
                                <code>{{1}}…{{n}}</code> variables matched to hook tags — open
                                <?= $wa_needs === 1 ? 'it' : 'them'; ?> and fill in <em>Template Variables</em>.
                            </span>
                        <?php endif; ?>
                        <?php if ($wa_sync && !empty($wa_sync['at'])): ?>
                            <br><span style="color:#6b7280; font-size:11.5px;">Last mapped <?= _dt($wa_sync['at']); ?>.</span>
                        <?php endif; ?>
                        <!-- Filled in by the Sync button so the counts above never read stale -->
                        <span id="waAutoMapDynamic" style="display:none;"></span>
                    </div>
                <?php endif; ?>
                <div class="ccx-table-wrap">
                    <table class="table" id="whatsapp-templates-table">
                        <thead>
                            <tr>
                                <th width="18%">Title</th>
                                <th width="30%">Content</th>
                                <th class="text-center" width="12%">Type</th>
                                <th class="text-center" width="10%">Attachment</th>
                                <th class="text-center" width="10%">Active</th>
                                <th class="text-center" width="10%">Default</th>
                                <th width="7%">Action</th>
                            </tr>
                        </thead>
                        <tbody id="whatsapp-templates-wrapper">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sub-Panel: Conversations (24-hour billing ledger) -->
        <?php if ($wa_ready): ?>
            <div class="ccx-sub-panel" data-sub-panel="conversations" style="display:none;">
                <div class="ccx-templates-section">
                    <div class="ccx-templates-header">
                        <h5><i class="fa fa-comments" style="margin-right:8px; color:#22c55e;"></i>24-Hour Conversations
                        </h5>
                        <button type="button" class="ccx-add-btn btn-wa" id="waConvRefresh">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                    </div>

                    <div class="wa-conv-grid" id="waConvStats">
                        <div class="wa-conv-card accent-green">
                            <div class="wa-conv-value" data-stat="open_now"><?= number_format($wa_stats['open_now']); ?></div>
                            <div class="wa-conv-label">Open right now</div>
                        </div>
                        <div class="wa-conv-card">
                            <div class="wa-conv-value" data-stat="opened_today"><?= number_format($wa_stats['opened_today']); ?></div>
                            <div class="wa-conv-label">Opened today</div>
                        </div>
                        <div class="wa-conv-card accent-amber">
                            <div class="wa-conv-value" data-stat="credits_today"><?= number_format($wa_stats['credits_today']); ?></div>
                            <div class="wa-conv-label">Credits used today</div>
                        </div>
                        <div class="wa-conv-card accent-amber">
                            <div class="wa-conv-value" data-stat="credits_period"><?= number_format($wa_stats['credits_period']); ?></div>
                            <div class="wa-conv-label">Credits used · 30 days</div>
                        </div>
                        <div class="wa-conv-card accent-green">
                            <div class="wa-conv-value" data-stat="saved_messages"><?= number_format($wa_stats['saved_messages']); ?></div>
                            <div class="wa-conv-label">Messages sent free inside windows</div>
                        </div>
                    </div>

                    <div class="ccx-table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th width="22%">Contact</th>
                                    <th width="18%">Category</th>
                                    <th class="text-center" width="12%">Messages</th>
                                    <th class="text-center" width="12%">Credit</th>
                                    <th width="18%">Opened</th>
                                    <th width="18%">Window</th>
                                </tr>
                            </thead>
                            <tbody id="waConvRows">
                                <?php if (empty($wa_cloud['recent'])): ?>
                                    <tr>
                                        <td colspan="6" class="text-center"
                                            style="padding:30px !important; color:#9ca3af; font-size:13px;">
                                            <i class="fa fa-comments-o" style="font-size:24px; display:block; margin-bottom:8px;"></i>
                                            No WhatsApp conversations yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($wa_cloud['recent'] as $conv): ?>
                                        <?php $conv_open = strtotime($conv->expires_at) > time(); ?>
                                        <tr>
                                            <td><strong><?= html_escape($conv->phone); ?></strong></td>
                                            <td>
                                                <span class="ccx-chip"
                                                    style="background:<?= $conv->bucket === 'promo' ? '#fef3c7' : ($conv->bucket === null || $conv->bucket === '' ? '#e0f2fe' : '#dbeafe'); ?>; color:<?= $conv->bucket === 'promo' ? '#92400e' : ($conv->bucket === null || $conv->bucket === '' ? '#075985' : '#1e40af'); ?>; font-size:11px;">
                                                    <?= html_escape(whatsapp_conversation_category_label($conv->category)); ?>
                                                </span>
                                            </td>
                                            <td class="text-center"><?= (int) $conv->messages_count; ?></td>
                                            <td class="text-center">
                                                <?php if ((int) $conv->credits_charged > 0): ?>
                                                    <span class="ccx-chip" style="background:#fee2e2; color:#991b1b; font-size:11px;">−<?= (int) $conv->credits_charged; ?></span>
                                                <?php else: ?>
                                                    <span class="ccx-chip" style="background:#dcfce7; color:#166534; font-size:11px;">Free</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="color:#6b7280; font-size:12px;"><?= _dt($conv->opened_at); ?></td>
                                            <td style="font-size:12px;">
                                                <?php if ($conv_open): ?>
                                                    <span style="color:#16a34a;">
                                                        <i class="fa fa-circle" style="font-size:8px;"></i>
                                                        Open · closes <?= _dt($conv->expires_at); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color:#9ca3af;">Closed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Sub-Panel: Hooks -->
        <?php if ($show_hooks): ?>
        <div class="ccx-sub-panel" data-sub-panel="hooks">
            <?php $this->load->view('tabs/_hooks_panel', ['hooks_channel' => 'whatsapp', 'can_logs' => !empty($can_logs)]); ?>
        </div>
        <?php endif; ?>

    </div>
<?php endif; ?>
