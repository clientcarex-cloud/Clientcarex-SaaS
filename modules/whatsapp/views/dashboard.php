<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Tab-wise permissions: every tab has its own staff capability, so a
 * non-admin only ever sees the sections their role was granted. `$wapi_tab`
 * is the map, `$wapi_first_tab` is the tab that opens by default (the first
 * permitted one — Overview may well be hidden for a restricted role).
 */
$wapi_tab = [];
foreach (array_keys(whatsapp_tab_capabilities()) as $wapi_t) {
    $wapi_tab[$wapi_t] = whatsapp_can_tab($wapi_t);
}

/**
 * On the provider's shared number the WhatsApp Business Account is not this
 * account's to change, so the tabs that only edit the WABA are dropped
 * regardless of capability. The Templates tab stays — it becomes a read-only
 * view of what the provider allowed — and the Inbox stays because outgoing
 * history is still this account's, but its composer is removed further down
 * (inbound replies land in the provider's inbox, not here).
 */
$wapi_shared_on = !empty($shared['enabled']);
if ($wapi_shared_on) {
    $wapi_tab['profile'] = false;
    $wapi_tab['bot']     = false;
}

$wapi_allowed   = array_keys(array_filter($wapi_tab));
$wapi_first_tab = $wapi_allowed ? reset($wapi_allowed) : '';
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content wapi-wrap">

        <!-- ═══════════ Header ═══════════ -->
        <div class="wapi-header">
            <div>
                <h2 class="wapi-title"><i class="fa-brands fa-whatsapp"></i> <?php echo _l('wapi_whatsapp'); ?></h2>
                <p class="wapi-subtitle"><?php echo _l('wapi_subtitle'); ?></p>
            </div>
            <div class="wapi-header-right">
                <?php if ($connection): ?>
                    <span class="wapi-conn-chip wapi-conn-ok"><i class="fa fa-circle-check"></i> <?php echo _l('wapi_connected'); ?>
                        <?php if (!empty($connection->waba_name)): ?>· <?php echo e($connection->waba_name); ?><?php endif; ?>
                    </span>
                    <?php if (($connection->credit_status ?? 'self') === 'shared'): ?>
                        <span class="wapi-conn-chip wapi-conn-billing" title="<?php echo _l('wapi_billing_provider_hint'); ?>">
                            <i class="fa fa-credit-card"></i> <?php echo _l('wapi_billing_provider'); ?>
                        </span>
                    <?php endif; ?>
                <?php elseif ($shared['enabled']): ?>
                    <span class="wapi-conn-chip <?php echo $shared['active'] ? 'wapi-conn-ok' : 'wapi-conn-warn'; ?>"
                          title="<?php echo e($shared['reason'] ?: _l('wapi_shared_chip_hint')); ?>">
                        <i class="fa fa-handshake-angle"></i>
                        <?php echo sprintf(_l('wapi_shared_chip'), e($shared['brand'])); ?>
                    </span>
                    <span class="wapi-conn-chip wapi-conn-billing">
                        <i class="fa fa-<?php echo $shared['billing_mode'] === 'free' ? 'gift' : 'coins'; ?>"></i>
                        <?php echo $shared['billing_mode'] === 'free' ? _l('wapi_shared_billing_free') : _l('wapi_shared_billing_credits'); ?>
                    </span>
                <?php elseif ($is_configured && $wapi_tab['settings']): ?>
                    <a href="<?php echo admin_url('whatsapp/connect'); ?>" class="wapi-btn wapi-btn-primary">
                        <i class="fa-brands fa-facebook"></i> <?php echo _l('wapi_connect_facebook'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══════════ Tabs ═══════════ -->
        <div class="wapi-tabs">
            <?php if ($wapi_tab['overview']): ?>
                <button class="wapi-tab<?php echo $wapi_first_tab === 'overview' ? ' active' : ''; ?>" data-tab="overview"><i class="fa fa-gauge"></i> <?php echo _l('wapi_tab_overview'); ?></button>
            <?php endif; ?>
            <?php if ($wapi_tab['inbox']): ?>
                <button class="wapi-tab<?php echo $wapi_first_tab === 'inbox' ? ' active' : ''; ?>" data-tab="inbox"><i class="fa fa-comments"></i> <?php echo _l('wapi_tab_inbox'); ?>
                    <?php if ($stats['unread'] > 0): ?><span class="wapi-count-badge" id="wapi-unread-badge"><?php echo (int) $stats['unread']; ?></span><?php endif; ?>
                </button>
            <?php endif; ?>
            <?php if ($wapi_tab['send']): ?>
                <button class="wapi-tab<?php echo $wapi_first_tab === 'send' ? ' active' : ''; ?>" data-tab="send"><i class="fa fa-paper-plane"></i> <?php echo _l('wapi_tab_send'); ?></button>
            <?php endif; ?>
            <?php if ($wapi_tab['bulk']): ?>
                <button class="wapi-tab<?php echo $wapi_first_tab === 'bulk' ? ' active' : ''; ?>" data-tab="bulk"><i class="fa fa-bullhorn"></i> <?php echo _l('wapi_tab_bulk'); ?></button>
            <?php endif; ?>
            <?php if ($wapi_tab['templates']): ?>
                <button class="wapi-tab<?php echo $wapi_first_tab === 'templates' ? ' active' : ''; ?>" data-tab="templates"><i class="fa fa-file-lines"></i> <?php echo _l('wapi_tab_templates'); ?></button>
            <?php endif; ?>
            <?php if ($wapi_tab['bot']): ?>
                <button class="wapi-tab<?php echo $wapi_first_tab === 'bot' ? ' active' : ''; ?>" data-tab="bot"><i class="fa fa-robot"></i> <?php echo _l('wapi_tab_bot'); ?></button>
            <?php endif; ?>
            <?php if ($wapi_tab['profile']): ?>
                <button class="wapi-tab<?php echo $wapi_first_tab === 'profile' ? ' active' : ''; ?>" data-tab="profile"><i class="fa fa-id-badge"></i> <?php echo _l('wapi_tab_profile'); ?></button>
            <?php endif; ?>
            <?php if ($wapi_tab['contacts']): ?>
                <button class="wapi-tab<?php echo $wapi_first_tab === 'contacts' ? ' active' : ''; ?>" data-tab="contacts"><i class="fa fa-address-book"></i> <?php echo _l('wapi_tab_contacts'); ?></button>
            <?php endif; ?>
            <?php if ($wapi_tab['settings']): ?>
                <button class="wapi-tab<?php echo $wapi_first_tab === 'settings' ? ' active' : ''; ?>" data-tab="settings"><i class="fa fa-gear"></i> <?php echo _l('wapi_tab_settings'); ?></button>
            <?php endif; ?>
        </div>

        <?php if ($wapi_first_tab === ''): ?>
            <div class="wapi-card"><div class="wapi-empty"><i class="fa fa-lock"></i><p><?php echo _l('wapi_no_tab_access'); ?></p></div></div>
        <?php endif; ?>

        <!-- ═══════════ Overview ═══════════ -->
        <?php if ($wapi_tab['overview']): ?>
        <div class="wapi-panel<?php echo $wapi_first_tab === 'overview' ? ' active' : ''; ?>" data-panel="overview">

            <div class="wapi-stats">
                <div class="wapi-stat"><span class="wapi-stat-num"><?php echo (int) $stats['messages_today']; ?></span><span class="wapi-stat-label"><?php echo _l('wapi_stat_messages_today'); ?></span></div>
                <div class="wapi-stat"><span class="wapi-stat-num"><?php echo (int) $stats['messages_total']; ?></span><span class="wapi-stat-label"><?php echo _l('wapi_stat_total_messages'); ?></span></div>
                <div class="wapi-stat"><span class="wapi-stat-num"><?php echo (int) $stats['unread']; ?></span><span class="wapi-stat-label"><?php echo _l('wapi_stat_unread'); ?></span></div>
                <div class="wapi-stat"><span class="wapi-stat-num"><?php echo (int) $stats['templates']; ?></span><span class="wapi-stat-label"><?php echo _l('wapi_stat_templates'); ?></span></div>
                <div class="wapi-stat"><span class="wapi-stat-num<?php echo (int) $stats['failed'] > 0 ? ' wapi-stat-danger' : ''; ?>"><?php echo (int) $stats['failed']; ?></span><span class="wapi-stat-label"><?php echo _l('wapi_stat_failed'); ?></span></div>
                <?php
                // Until a health check has run we genuinely do not know how many
                // numbers can send — show "—" rather than a scary 0.
                $checked_any  = (int) $stats['numbers_checked'] > 0;
                $active_short = $checked_any && (int) $stats['numbers_active'] < (int) $stats['numbers'];
                ?>
                <div class="wapi-stat">
                    <span class="wapi-stat-num<?php echo $active_short ? ' wapi-stat-danger' : ''; ?>" id="wapi-stat-active"><?php echo $checked_any ? (int) $stats['numbers_active'] : '—'; ?><small>/<?php echo (int) $stats['numbers']; ?></small></span>
                    <span class="wapi-stat-label"><?php echo _l('wapi_stat_active_numbers'); ?></span>
                </div>
            </div>

            <!-- Connection / numbers -->
            <div class="wapi-card">
                <div class="wapi-card-head">
                    <h3><i class="fa-brands fa-whatsapp"></i> <?php echo _l('wapi_business_account'); ?></h3>
                    <?php if ($connection): ?>
                        <div>
                            <button class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiCheckNumbers()"><i class="fa fa-heart-pulse"></i> <?php echo _l('wapi_check_status'); ?></button>
                            <?php if ($wapi_tab['settings']): ?>
                                <button class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiRefreshNumbers()"><i class="fa fa-rotate"></i> <?php echo _l('wapi_refresh_numbers'); ?></button>
                                <button class="wapi-btn wapi-btn-ghost wapi-btn-sm wapi-danger" onclick="wapiDisconnect()"><i class="fa fa-link-slash"></i> <?php echo _l('wapi_disconnect'); ?></button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!$connection && $shared['enabled']): ?>
                    <!--
                        Sending on the provider's number. Everything that would
                        change the WhatsApp Business Account is absent here on
                        purpose — it belongs to the provider, and the server
                        refuses those actions regardless of what is rendered.
                    -->
                    <?php $wapi_su = $shared['usage']; ?>
                    <div class="wapi-shared-panel">
                        <div class="wapi-shared-head">
                            <div>
                                <h4 class="wapi-h4"><?php echo sprintf(_l('wapi_shared_panel_title'), e($shared['brand'])); ?></h4>
                                <p class="wapi-muted"><?php echo sprintf(_l('wapi_shared_panel_lead'), e($shared['brand'])); ?></p>
                            </div>
                            <?php echo whatsapp_shared_mode_badge($shared['billing_mode']); ?>
                        </div>

                        <?php if ($shared['reason'] !== ''): ?>
                            <div class="wapi-alert wapi-alert-warn">
                                <i class="fa fa-circle-info"></i>
                                <div><p><?php echo e($shared['reason']); ?></p></div>
                            </div>
                        <?php endif; ?>

                        <div class="wapi-shared-facts">
                            <div class="wapi-shared-fact">
                                <span class="wapi-shared-fact-label"><?php echo _l('wapi_shared_fact_number'); ?></span>
                                <span class="wapi-shared-fact-value"><?php echo e($shared['number_label'] ?: '—'); ?></span>
                            </div>
                            <div class="wapi-shared-fact">
                                <span class="wapi-shared-fact-label"><?php echo _l('wapi_shared_fact_templates'); ?></span>
                                <span class="wapi-shared-fact-value"><?php echo (int) $shared['templates']; ?></span>
                            </div>
                            <div class="wapi-shared-fact">
                                <span class="wapi-shared-fact-label"><?php echo _l('wapi_shared_fact_today'); ?></span>
                                <span class="wapi-shared-fact-value">
                                    <?php echo number_format((int) $wapi_su['today']); ?>
                                    <?php if ($wapi_su['daily_limit'] > 0): ?><small class="wapi-muted">/ <?php echo number_format($wapi_su['daily_limit']); ?></small><?php endif; ?>
                                </span>
                            </div>
                            <div class="wapi-shared-fact">
                                <span class="wapi-shared-fact-label"><?php echo _l('wapi_shared_fact_month'); ?></span>
                                <span class="wapi-shared-fact-value">
                                    <?php echo number_format((int) $wapi_su['month']); ?>
                                    <?php if ($wapi_su['monthly_limit'] > 0): ?><small class="wapi-muted">/ <?php echo number_format($wapi_su['monthly_limit']); ?></small><?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <p class="wapi-muted wapi-shared-note">
                            <i class="fa fa-circle-info"></i>
                            <?php echo sprintf(_l('wapi_shared_rules_note'), e($shared['brand'])); ?>
                        </p>

                        <?php if ($wapi_tab['settings'] && $is_configured): ?>
                            <a href="<?php echo admin_url('whatsapp/connect'); ?>" class="wapi-btn wapi-btn-light wapi-btn-sm">
                                <i class="fa-brands fa-facebook"></i> <?php echo _l('wapi_shared_upgrade_own'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php elseif (!$connection): ?>
                    <div class="wapi-empty">
                        <i class="fa-brands fa-whatsapp"></i>
                        <?php if ($is_configured): ?>
                            <p><?php echo _l('wapi_not_connected'); ?></p>
                            <?php if ($wapi_tab['settings']): ?>
                                <a href="<?php echo admin_url('whatsapp/connect'); ?>" class="wapi-btn wapi-btn-primary"><i class="fa-brands fa-facebook"></i> <?php echo _l('wapi_connect_facebook'); ?></a>
                            <?php endif; ?>
                        <?php else: ?>
                            <p><?php echo _l('wapi_provider_not_ready'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php if ((string) ($connection->status ?? 'active') === 'stale'): ?>
                        <!-- Central Meta App changed: the stored token belongs to the
                             previous app, so everything below describes an account
                             that can no longer send or receive until it reconnects. -->
                        <div class="wapi-alert wapi-alert-danger">
                            <i class="fa fa-triangle-exclamation"></i>
                            <div>
                                <strong><?php echo _l('wapi_stale_connection_title'); ?></strong>
                                <p><?php echo _l('wapi_stale_connection_hint'); ?></p>
                                <?php if ($wapi_tab['settings'] && $is_configured): ?>
                                    <a href="<?php echo admin_url('whatsapp/connect'); ?>" class="wapi-btn wapi-btn-primary wapi-btn-sm">
                                        <i class="fa-brands fa-facebook"></i> <?php echo _l('wapi_reconnect_now'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($numbers)): ?>
                        <div class="wapi-empty"><i class="fa fa-phone"></i><p><?php echo _l('wapi_phone_numbers'); ?>: 0 — <?php echo _l('wapi_refresh_numbers'); ?></p></div>
                    <?php else: ?>
                        <?php
                        $unregistered = array_filter($numbers, 'whatsapp_number_unregistered');
                        ?>
                        <?php if (!empty($unregistered)): ?>
                            <div class="wapi-alert wapi-alert-danger">
                                <i class="fa fa-triangle-exclamation"></i>
                                <div>
                                    <strong><?php echo _l('wapi_not_registered_title'); ?></strong>
                                    <p><?php echo _l('wapi_not_registered_hint'); ?></p>
                                    <?php foreach (($wapi_tab['settings'] ? $unregistered : []) as $n): ?>
                                        <button class="wapi-btn wapi-btn-primary wapi-btn-sm"
                                                onclick="wapiOpenRegister('<?php echo e($n->phone_number_id); ?>', '<?php echo e($n->display_phone_number); ?>')">
                                            <i class="fa fa-key"></i> <?php echo _l('wapi_register_number'); ?><?php if (count($unregistered) > 1): ?> · <?php echo e($n->display_phone_number ?: $n->phone_number_id); ?><?php endif; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="wapi-table-scroll">
                        <table class="wapi-table wapi-numbers-table">
                            <thead><tr>
                                <th><?php echo _l('wapi_phone_numbers'); ?></th>
                                <th><?php echo _l('wapi_number_status'); ?></th>
                                <th><?php echo _l('wapi_can_send'); ?></th>
                                <th><?php echo _l('wapi_quality'); ?></th>
                                <th><?php echo _l('wapi_messaging_limit'); ?></th>
                                <th><?php echo _l('wapi_last_checked'); ?></th>
                                <th class="wapi-ta-r"><?php echo _l('wapi_actions'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($numbers as $n): ?>
                                <tr data-number-row="<?php echo e($n->phone_number_id); ?>">
                                    <td>
                                        <strong><?php echo e($n->display_phone_number ?: $n->phone_number_id); ?></strong>
                                        <?php if ((int) $n->is_default === 1): ?> <span class="wapi-badge wapi-badge-soft"><?php echo _l('wapi_default'); ?></span><?php endif; ?>
                                        <br><small class="wapi-muted"><?php echo e($n->verified_name ?: '—'); ?></small>
                                    </td>
                                    <td data-cell="status"><?php echo whatsapp_number_status_badge($n); ?></td>
                                    <td data-cell="health"><?php echo whatsapp_health_badge($n->health_can_send ?? ''); ?></td>
                                    <td data-cell="quality"><?php echo whatsapp_quality_badge($n->quality_rating ?? ''); ?></td>
                                    <td data-cell="tier"><?php echo e(whatsapp_tier_label($n->messaging_limit_tier ?? '')); ?></td>
                                    <td data-cell="checked" class="wapi-muted"><?php echo e(whatsapp_time_ago($n->last_checked_at ?? null)); ?></td>
                                    <td class="wapi-ta-r wapi-row-actions">
                                        <?php if ($wapi_tab['settings'] && whatsapp_number_unregistered($n)): ?>
                                            <button class="wapi-btn wapi-btn-primary wapi-btn-sm" title="<?php echo _l('wapi_register_number'); ?>"
                                                    onclick="wapiOpenRegister('<?php echo e($n->phone_number_id); ?>', '<?php echo e($n->display_phone_number); ?>')"><i class="fa fa-key"></i></button>
                                        <?php endif; ?>
                                        <?php if ($wapi_tab['settings'] && (int) $n->is_default !== 1): ?>
                                            <button class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiSetDefault('<?php echo e($n->phone_number_id); ?>')"><?php echo _l('wapi_make_default'); ?></button>
                                        <?php endif; ?>
                                        <button class="wapi-btn wapi-btn-light wapi-btn-sm" title="<?php echo _l('wapi_details'); ?>"
                                                onclick="wapiNumberDetails('<?php echo e($n->phone_number_id); ?>')"><i class="fa fa-eye"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($connection): ?>
            <!-- ═══════ Health & diagnostics ═══════ -->
            <details class="wapi-card wapi-details">
                <summary>
                    <span class="wapi-details-title"><i class="fa fa-stethoscope"></i> <?php echo _l('wapi_diagnostics'); ?></span>
                    <span class="wapi-details-right">
                        <span id="wapi-diag-summary" class="wapi-muted"><?php echo _l('wapi_running_checks'); ?></span>
                        <i class="fa fa-chevron-down wapi-chevron"></i>
                    </span>
                </summary>
                <div class="wapi-details-body">
                    <div class="wapi-details-actions">
                        <button class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiLoadDiagnostics(true)"><i class="fa fa-rotate"></i> <?php echo _l('wapi_run_checks'); ?></button>
                    </div>
                    <div id="wapi-diagnostics">
                        <div class="wapi-diag-loading"><i class="fa fa-circle-notch fa-spin"></i> <?php echo _l('wapi_running_checks'); ?></div>
                    </div>
                    <div id="wapi-failures"></div>
                </div>
            </details>

            <!-- ═══════ Message activity ═══════ -->
            <div class="wapi-card">
                <div class="wapi-card-head">
                    <h3><i class="fa fa-chart-column"></i> <?php echo _l('wapi_activity_title'); ?></h3>
                    <div class="wapi-chart-controls">
                        <div class="wapi-legend">
                            <span><i class="wapi-dot" style="background:#128c7e"></i> <?php echo _l('wapi_legend_out'); ?></span>
                            <span><i class="wapi-dot" style="background:#25d366"></i> <?php echo _l('wapi_legend_in'); ?></span>
                            <span><i class="wapi-dot" style="background:#ef4444"></i> <?php echo _l('wapi_legend_failed'); ?></span>
                        </div>
                        <select id="wapi-chart-range" class="wapi-inline-select">
                            <option value="7"><?php echo _l('wapi_range_7'); ?></option>
                            <option value="14" selected><?php echo _l('wapi_range_14'); ?></option>
                            <option value="30"><?php echo _l('wapi_range_30'); ?></option>
                            <option value="90"><?php echo _l('wapi_range_90'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="wapi-chart-kpis" id="wapi-chart-kpis"></div>

                <div class="wapi-chart-wrap">
                    <div class="wapi-chart-yaxis" id="wapi-chart-yaxis"></div>
                    <div class="wapi-chart-plot">
                        <div class="wapi-chart-grid" id="wapi-chart-grid"></div>
                        <div class="wapi-chart" id="wapi-chart"></div>
                    </div>
                    <div class="wapi-chart-tip" id="wapi-chart-tip"></div>
                </div>
                <div class="wapi-chart-xaxis" id="wapi-chart-xaxis"></div>
            </div>
            <?php endif; ?>

            <?php if ($is_master): ?>
            <!-- ═══════ Master: provider console ═══════ -->
            <div class="wapi-card wapi-master-card">
                <div class="wapi-card-head">
                    <h3><i class="fa fa-server"></i> <?php echo _l('wapi_master_console'); ?></h3>
                </div>

                <h4 class="wapi-h4"><?php echo _l('wapi_app_credentials'); ?></h4>
                <form id="wapi-credentials-form" onsubmit="return wapiSaveCredentials(event)">
                    <div class="wapi-grid-3">
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_app_id'); ?></label>
                            <input type="text" name="app_id" value="<?php echo e($app_id); ?>" autocomplete="off">
                        </div>
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_app_secret'); ?></label>
                            <input type="password" name="app_secret" value="<?php echo e($app_secret); ?>" autocomplete="new-password">
                        </div>
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_config_id'); ?></label>
                            <input type="text" name="config_id" value="<?php echo e($config_id); ?>" autocomplete="off">
                        </div>
                    </div>
                    <div class="wapi-details-actions">
                        <button type="submit" class="wapi-btn wapi-btn-primary wapi-btn-sm"><i class="fa fa-floppy-disk"></i> <?php echo _l('submit'); ?></button>
                        <button type="button" class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiResyncApp()">
                            <i class="fa fa-rotate"></i> <?php echo _l('wapi_resync_btn'); ?>
                        </button>
                        <?php if (!empty($last_resync)): ?>
                            <span class="wapi-muted"><?php echo _l('wapi_resync_last'); ?> <?php echo e(whatsapp_time_ago($last_resync)); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="wapi-hint"><?php echo _l('wapi_resync_hint'); ?></p>
                </form>

                <div id="wapi-resync-report" class="wapi-resync-report"></div>

                <div class="wapi-copy-rows">
                    <div class="wapi-copy-row">
                        <span class="wapi-copy-label"><?php echo _l('wapi_webhook_url'); ?></span>
                        <code class="wapi-code"><?php echo e($webhook_url); ?></code>
                        <button class="wapi-btn wapi-btn-ghost wapi-btn-sm" onclick="wapiCopy(this, '<?php echo e($webhook_url); ?>')"><i class="fa fa-copy"></i></button>
                    </div>
                    <div class="wapi-copy-row">
                        <span class="wapi-copy-label"><?php echo _l('wapi_oauth_redirect'); ?></span>
                        <code class="wapi-code"><?php echo e($callback_url); ?></code>
                        <button class="wapi-btn wapi-btn-ghost wapi-btn-sm" onclick="wapiCopy(this, '<?php echo e($callback_url); ?>')"><i class="fa fa-copy"></i></button>
                    </div>
                    <div class="wapi-copy-row">
                        <span class="wapi-copy-label"><?php echo _l('wapi_verify_token'); ?></span>
                        <code class="wapi-code"><?php echo e($verify_token); ?></code>
                        <button class="wapi-btn wapi-btn-ghost wapi-btn-sm" onclick="wapiCopy(this, '<?php echo e($verify_token); ?>')"><i class="fa fa-copy"></i></button>
                    </div>
                </div>

                <details class="wapi-details wapi-details-inline">
                    <summary>
                        <span class="wapi-details-title"><i class="fa fa-satellite-dish"></i> <?php echo _l('wapi_webhook_health'); ?></span>
                        <span class="wapi-details-right">
                            <span id="wapi-webhook-summary" class="wapi-muted"><?php echo _l('wapi_running_checks'); ?></span>
                            <i class="fa fa-chevron-down wapi-chevron"></i>
                        </span>
                    </summary>
                    <div class="wapi-details-body">
                        <p class="wapi-modal-lead"><?php echo _l('wapi_webhook_health_lead'); ?></p>
                        <div id="wapi-webhook-report">
                            <div class="wapi-diag-loading"><i class="fa fa-circle-notch fa-spin"></i> <?php echo _l('wapi_running_checks'); ?></div>
                        </div>
                        <div class="wapi-webhook-actions">
                            <button class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiWebhookCheck()"><i class="fa fa-stethoscope"></i> <?php echo _l('wapi_webhook_test'); ?></button>
                            <button class="wapi-btn wapi-btn-primary wapi-btn-sm" onclick="wapiWebhookFix()"><i class="fa fa-wrench"></i> <?php echo _l('wapi_webhook_fix'); ?></button>
                        </div>

                        <details class="wapi-guide wapi-guide-billing">
                    <summary><i class="fa fa-book-open"></i> <?php echo _l('wapi_webhook_guide'); ?><i class="fa fa-chevron-down wapi-chevron"></i></summary>

                    <p class="wapi-guide-note">
                        <i class="fa fa-circle-info"></i>
                        <span>One webhook carries everything inbound: customer messages, delivery/read receipts and template approvals.
                        If it is not wired up, sending still works but the Inbox stays empty, ticks never turn blue, and the 24-hour
                        reply window can never open. Fix the checks above in order — each one depends on the one before it.</span>
                    </p>

                    <h5 class="wapi-guide-step">A · Endpoint reachability failed</h5>
                    <p class="wapi-guide-sub">This server must answer Meta's verification challenge publicly. The check tells you which of these it is:</p>
                    <table class="wapi-table wapi-guide-table">
                        <thead><tr><th>Reported</th><th>Fix</th></tr></thead>
                        <tbody>
                            <tr><td>Could not reach its own URL</td><td>DNS, SSL certificate or an outbound firewall rule. Confirm the site loads over HTTPS from outside your network, and that the certificate is valid and not self-signed.</td></tr>
                            <tr><td>The URL redirects</td><td><strong>Meta does not follow redirects.</strong> Whatever the redirect lands on is the real URL — usually a www / non-www or http → https rule. Register the final address.</td></tr>
                            <tr><td>Answered 401 / 403</td><td>Something in front of the app is rejecting the POST: a WAF or security plugin, Cloudflare bot protection, HTTP basic auth, or an IP allow-list. Whitelist the webhook path.</td></tr>
                            <tr><td>Answered 404</td><td>The URL is wrong or the route is not reachable. Copy the Webhook URL shown above verbatim — it includes this account's custom admin prefix.</td></tr>
                            <tr><td>200 but no challenge echoed</td><td>A login page, error page, cache or CDN is being served instead of the module. Exclude the webhook path from page caching.</td></tr>
                        </tbody>
                    </table>

                    <h5 class="wapi-guide-step">B · No app subscription registered</h5>
                    <p class="wapi-guide-sub">
                        This is the common one and it means Meta has nowhere to send events. Once check A is green, just press
                        <strong>Register webhook</strong> — it sends the URL, verify token and both required fields to your Meta App,
                        and Meta verifies the URL as part of accepting it. To do it by hand instead:
                    </p>
                    <ol>
                        <li>Open your app at <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">developers.facebook.com</a> → <strong>WhatsApp → Configuration</strong>.</li>
                        <li>Under <strong>Webhook</strong>, click <em>Edit</em> and paste the <strong>Callback URL</strong> and <strong>Verify token</strong> exactly as shown above.</li>
                        <li>Click <strong>Verify and save</strong>. If it refuses, check A is the reason — not the token.</li>
                        <li>Click <strong>Manage</strong> and subscribe to <code>messages</code> and <code>message_template_status_update</code>.</li>
                    </ol>

                    <h5 class="wapi-guide-step">C · Callback URL does not match</h5>
                    <p class="wapi-guide-sub">
                        Meta is registered to POST somewhere else — often a staging domain or an older install. Events are being delivered,
                        just not here. Press <strong>Register webhook</strong> to repoint it, or edit it in WhatsApp → Configuration.
                        Remember the two URLs must match exactly, including the custom admin prefix.
                    </p>

                    <h5 class="wapi-guide-step">D · Subscribed fields missing</h5>
                    <p class="wapi-guide-sub">
                        A registered webhook with no <code>messages</code> field delivers nothing. <code>message_template_status_update</code>
                        keeps template approvals in sync. <strong>Register webhook</strong> sets both; manually it is WhatsApp → Configuration → Webhook fields → <em>Manage</em>.
                    </p>

                    <h5 class="wapi-guide-step">E · Reached us, but the signature failed</h5>
                    <p class="wapi-guide-sub">
                        Meta got through, but the payload signature did not verify — the <strong>App Secret</strong> stored in this console
                        belongs to a different Meta App than the one sending events. Copy the secret again from
                        <em>App settings → Basic</em> and save it above, then re-run the checks.
                    </p>

                    <h5 class="wapi-guide-step">F · Everything green but still nothing arrives</h5>
                    <ol>
                        <li>Each connected WhatsApp Business Account must also be subscribed. Reconnecting the account re-subscribes it automatically.</li>
                        <li>Message the business number from a real phone — Meta only sends events for genuine traffic.</li>
                        <li>Re-run the checks: <em>Delivery history</em> should now show a count and a timestamp.</li>
                        <li>Meta's own <em>WhatsApp → Configuration → Webhook</em> panel keeps a delivery-error log worth checking if it still fails.</li>
                    </ol>

                    <p class="wapi-guide-note wapi-guide-note-warn">
                        <i class="fa fa-triangle-exclamation"></i>
                        <span>Messages that arrived while the webhook was broken are <strong>gone</strong> — the Cloud API has no history
                        endpoint, so nothing can be backfilled. Only traffic from the moment it starts working will appear.</span>
                    </p>
                        </details>
                    </div>
                </details>

                <details class="wapi-guide">
                    <summary><i class="fa fa-circle-info"></i> <?php echo _l('wapi_setup_guide'); ?><i class="fa fa-chevron-down wapi-chevron"></i></summary>
                    <ol>
                        <li>Create a Meta App of type <strong>Business</strong> at developers.facebook.com and add the <strong>WhatsApp</strong> product.</li>
                        <li>Add <strong>Facebook Login for Business</strong>, set the OAuth Redirect URI above, and (recommended) create an <strong>Embedded Signup configuration</strong> — paste its Config ID here.</li>
                        <li>Under WhatsApp → Configuration, set the <strong>Webhook URL</strong> and <strong>Verify Token</strong> above and subscribe to the <code>messages</code> and <code>message_template_status_update</code> fields.</li>
                        <li>Request Advanced Access for <code>whatsapp_business_management</code> and <code>whatsapp_business_messaging</code>, then switch the app to <strong>Live</strong>.</li>
                        <li>Tenants now simply click <em>Connect with Facebook</em> on their WhatsApp page.</li>
                    </ol>
                    <p class="wapi-muted"><?php echo _l('wapi_guide_billing_pointer'); ?></p>
                </details>

                <h4 class="wapi-h4"><i class="fa fa-credit-card"></i> <?php echo _l('wapi_billing_title'); ?></h4>
                <p class="wapi-modal-lead"><?php echo _l('wapi_billing_lead'); ?></p>

                <details class="wapi-guide wapi-guide-billing">
                    <summary><i class="fa fa-book-open"></i> <?php echo _l('wapi_billing_guide'); ?><i class="fa fa-chevron-down wapi-chevron"></i></summary>

                    <p class="wapi-guide-note wapi-guide-note-warn">
                        <i class="fa fa-circle-exclamation"></i>
                        <span><strong>Why your tenants are asked for a card today.</strong>
                        Meta's rule is explicit: <em>Tech Providers do not have credit lines — clients onboarded by a Tech Provider
                        must provide their own payment method after onboarding.</em> Only <strong>Solution Partners</strong> hold a credit line
                        they can extend to the businesses they onboard. So this section stays inactive until you either become a
                        Solution Partner or run a Multi-Partner Solution with one (step 2). Steps 1–3 are Meta approvals,
                        not settings you can switch on.</span>
                    </p>

                    <h5 class="wapi-guide-step">Step 1 · Verify your business with Meta</h5>
                    <ol>
                        <li>Open <a href="https://business.facebook.com/settings/security" target="_blank" rel="noopener">Business settings → Security Centre</a> and start <strong>Business verification</strong>.</li>
                        <li>Submit your legal entity name, registered address, phone and incorporation documents. Meta usually takes a few days.</li>
                        <li>Wait until the status reads <strong>Verified</strong> — nothing below works without it.</li>
                    </ol>

                    <h5 class="wapi-guide-step">Step 2 · Choose your route to a credit line</h5>
                    <p class="wapi-guide-sub">A Tech Provider can never share a credit line. Pick one of these:</p>
                    <ol>
                        <li><strong>Route A — become a Solution Partner.</strong> Apply through
                            <a href="https://whatsappbusiness.com/partners/become-a-partner/" target="_blank" rel="noopener">Become a Partner</a>
                            and select the Solution Partner tier. Meta reviews your platform, expected message volume and support model,
                            then sets up an invoicing agreement. This is the tier Wati, AiSensy and Interakt sit on. Slowest route, but you own the billing relationship end to end.</li>
                        <li><strong>Route B — build a Multi-Partner Solution with an existing Solution Partner.</strong> You stay a Tech Provider;
                            a Solution Partner shares <em>their</em> credit line with the clients onboarded through your joint solution.
                            Far faster to arrange, at the cost of a revenue share and a dependency on that partner.</li>
                        <li>Either way, the outcome is the same for this module: a credit line becomes visible to your business portfolio,
                            and everything from step 3 onwards applies unchanged.</li>
                    </ol>

                    <h5 class="wapi-guide-step">Step 3 · Get the credit line allocated, then accept the API terms</h5>
                    <p class="wapi-guide-sub">
                        You cannot create a credit line yourself. <a href="https://business.facebook.com/billing_hub/accounts" target="_blank" rel="noopener">Billing &amp; payments → Credit lines</a>
                        currently reads <em>“No line of credit has been allocated”</em>, and Meta removed the self-serve request button from that page.
                        It is a <strong>monthly invoicing</strong> credit line and it reaches you one of two ways:
                    </p>
                    <ol>
                        <li><strong>Meta allocates one to you</strong> once your Solution Partner agreement is in place (Route A above).</li>
                        <li><strong>Another business portfolio grants you access to theirs</strong> (Route B above). They do it from
                            <em>Billing &amp; payments → Credit lines → select the credit line → Credit line access → Grant access</em> —
                            that is exactly the “grant access” link on your own empty Credit lines page. This module reads granted
                            credit lines as well as owned ones, so either way the picker below fills up.</li>
                        <li><strong>Then accept the Credit Allocation API terms</strong> in
                            <em>Business settings → Payments</em>. Sharing a credit line over the API is blocked until these terms are accepted —
                            it is easy to miss and looks like a permissions bug when skipped.</li>
                        <li>Until one of these lands, leave sharing switched off. Tenants keep paying Meta with their own card and every other part
                            of this module works exactly as it does today.</li>
                    </ol>

                    <h5 class="wapi-guide-step">Step 4 · Create a System User token</h5>
                    <ol>
                        <li>Go to <a href="https://business.facebook.com/settings/system-users" target="_blank" rel="noopener">Business settings → Users → System users</a> and click <strong>Add</strong>. Name it e.g. <code>WhatsApp Billing</code>.</li>
                        <li>Give that system user an <strong>Admin</strong> or <strong>Financial Editor</strong> role on your business portfolio — Meta requires one of those two before a token may share a credit line. A plain Employee role silently fails.</li>
                        <li>Click <strong>Add assets</strong> and give it <strong>Full control</strong> of your Meta App (the one whose App ID is above).</li>
                        <li>Click <strong>Generate new token</strong>, select that app, and tick <code>business_management</code>, <code>whatsapp_business_management</code> and <code>whatsapp_business_messaging</code>.</li>
                        <li>Set the expiry to <strong>Never</strong> and copy the token — Meta shows it only once.</li>
                    </ol>

                    <h5 class="wapi-guide-step">Step 5 · Find your Business (portfolio) ID</h5>
                    <ol>
                        <li>Open <a href="https://business.facebook.com/settings/info" target="_blank" rel="noopener">Business settings → Business info</a>.</li>
                        <li>Copy the numeric <strong>Business ID</strong> (it is also the <code>business_id=</code> value in that page's URL).</li>
                    </ol>

                    <h5 class="wapi-guide-step">Step 6 · Fill in the form below</h5>
                    <ol>
                        <li>Paste the <strong>Business ID</strong> and the <strong>System User token</strong>, and set the <strong>currency</strong> — it must match the currency your tenants' WhatsApp accounts are set to (e.g. <code>USD</code>, <code>INR</code>).</li>
                        <li>Click <strong>Load from Meta</strong>. Your credit line(s) appear in the picker. An empty list means Meta has not issued one yet — go back to step 3.</li>
                        <li>Select the credit line, switch <strong>Bill tenant messaging to our credit line</strong> on, and save.</li>
                        <li>The banner under the form should turn to <strong>Ready</strong>.</li>
                    </ol>

                    <h5 class="wapi-guide-step">Step 7 · Remove the card prompt from signup</h5>
                    <ol>
                        <li>In your Meta App, open <strong>Facebook Login for Business → Configurations</strong> and edit the Embedded Signup configuration whose Config ID is above.</li>
                        <li>Confirm the <strong>payment method</strong> step is not requested, so tenants are never shown a card screen.</li>
                        <li>Roll out to one tenant first and watch the flow end to end before announcing it.</li>
                    </ol>

                    <h5 class="wapi-guide-step">Step 8 · Attach tenants and verify</h5>
                    <ol>
                        <li><strong>New tenants</strong> are attached automatically the moment they finish <em>Connect with Facebook</em>.</li>
                        <li><strong>Existing tenants</strong> connected before this was enabled: click the <i class="fa fa-credit-card"></i> button on their row in <em>Connected Tenants</em> below.</li>
                        <li>Confirm the Billing column reads <strong>Provider billed</strong>. The tenant's own page will show a <em>Billing by provider</em> chip and a passing billing check under Health &amp; Diagnostics.</li>
                    </ol>

                    <h5 class="wapi-guide-step">If something fails</h5>
                    <table class="wapi-table wapi-guide-table">
                        <thead><tr><th>What you see</th><th>What it means</th></tr></thead>
                        <tbody>
                            <tr><td>Credit line picker comes back empty</td><td>No credit line is allocated to or shared with your portfolio. If you are still a Tech Provider this is expected — see step 2.</td></tr>
                            <tr><td>Credit line is visible, but attaching a tenant fails</td><td>Usually the <strong>Credit Allocation API terms</strong> have not been accepted (step 3), or the system user is not an Admin / Financial Editor (step 4).</td></tr>
                            <tr><td><code>(#100)</code> unsupported request / nonexistent field</td><td>The Business ID is wrong, or the token has no access to that business. Re-check steps 4 and 5.</td></tr>
                            <tr><td><code>(#200)</code> or <code>(#10)</code> permission error</td><td>The token is missing <code>business_management</code>, or the system user is not an Admin of the business.</td></tr>
                            <tr><td><code>(#190)</code> token expired</td><td>A short-lived token was pasted. Generate a system user token with expiry set to <strong>Never</strong>.</td></tr>
                            <tr><td>Tenant row shows <strong>Sharing failed</strong></td><td>Hover it for Meta's exact message. Usually the tenant's WABA was created outside your Embedded Signup (so your business has no partner access to it), or the currency does not match that account.</td></tr>
                            <tr><td>Tenant still sees a card prompt</td><td>Step 7 — the Embedded Signup configuration is still requesting a payment method.</td></tr>
                        </tbody>
                    </table>

                    <p class="wapi-guide-note wapi-guide-note-warn">
                        <i class="fa fa-triangle-exclamation"></i>
                        Once a tenant is on your credit line, <strong>Meta invoices you for every conversation they send</strong>.
                        Price your plans with that cost built in, and keep an eye on the tenants table so a single account cannot run up your bill unnoticed.
                    </p>
                </details>

                <form id="wapi-billing-form" onsubmit="return wapiSaveBilling(event)">
                    <div class="wapi-grid-3">
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_business_id'); ?></label>
                            <input type="text" name="business_id" value="<?php echo e($billing['business_id']); ?>" autocomplete="off" placeholder="1234567890">
                        </div>
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_system_user_token'); ?></label>
                            <input type="password" name="system_user_token" autocomplete="new-password"
                                   placeholder="<?php echo $billing['system_token'] !== '' ? _l('wapi_token_stored') : 'EAAG…'; ?>">
                        </div>
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_credit_currency'); ?></label>
                            <input type="text" name="credit_currency" value="<?php echo e($billing['currency']); ?>" maxlength="3" placeholder="USD">
                        </div>
                    </div>

                    <div class="wapi-field">
                        <label><?php echo _l('wapi_credit_line'); ?></label>
                        <div class="wapi-param-controls">
                            <select name="credit_line_id" id="wapi-credit-line-select">
                                <?php if ($billing['credit_line_id'] !== ''): ?>
                                    <option value="<?php echo e($billing['credit_line_id']); ?>" selected><?php echo e($billing['credit_line_id']); ?></option>
                                <?php else: ?>
                                    <option value=""><?php echo _l('wapi_credit_line_none'); ?></option>
                                <?php endif; ?>
                            </select>
                            <button type="button" class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiLoadCreditLines()">
                                <i class="fa fa-cloud-arrow-down"></i> <?php echo _l('wapi_load_credit_lines'); ?>
                            </button>
                        </div>
                    </div>

                    <span class="wapi-switch-label">
                        <label class="wapi-switch"><input type="checkbox" name="share_credit_line" value="1" <?php echo $billing['enabled'] ? 'checked' : ''; ?>><span class="wapi-slider"></span></label>
                        <?php echo _l('wapi_share_credit_line'); ?>
                    </span>

                    <div class="wapi-alert <?php echo $billing_ready ? 'wapi-alert-info' : 'wapi-alert-warn'; ?>" style="margin-top:12px">
                        <i class="fa <?php echo $billing_ready ? 'fa-circle-check' : 'fa-circle-info'; ?>"></i>
                        <div>
                            <strong><?php echo $billing_ready ? _l('wapi_billing_ready') : _l('wapi_billing_not_ready'); ?></strong>
                            <p><?php echo _l('wapi_billing_requirements'); ?></p>
                        </div>
                    </div>

                    <button type="submit" class="wapi-btn wapi-btn-primary wapi-btn-sm"><i class="fa fa-floppy-disk"></i> <?php echo _l('submit'); ?></button>
                </form>

                <div class="wapi-card-head" style="margin-top:18px">
                    <h4 class="wapi-h4" style="margin:0"><?php echo _l('wapi_connected_tenants'); ?></h4>
                    <div class="wapi-usage-controls">
                        <select id="wapi-usage-days" class="wapi-inline-select">
                            <option value="7"><?php echo _l('wapi_usage_7'); ?></option>
                            <option value="30" selected><?php echo _l('wapi_usage_30'); ?></option>
                            <option value="90"><?php echo _l('wapi_usage_90'); ?></option>
                        </select>
                        <button class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiRefreshUsage()"><i class="fa fa-coins"></i> <?php echo _l('wapi_refresh_usage'); ?></button>
                    </div>
                </div>
                <?php if (empty($connections)): ?>
                    <div class="wapi-empty wapi-empty-sm"><p><?php echo _l('wapi_no_tenants'); ?></p></div>
                <?php else: ?>
                    <?php
                    // Totals across every tenant — what the provider actually owes Meta.
                    $tot_msgs = 0;
                    $tot_cost = 0.0;
                    $cur      = '';
                    $synced   = null;
                    foreach ($connections as $c) {
                        $tot_msgs += (int) ($c->usage_messages ?? 0);
                        $tot_cost += (float) ($c->usage_cost ?? 0);
                        if ($cur === '' && !empty($c->usage_currency)) {
                            $cur = $c->usage_currency;
                        }
                        if (!empty($c->usage_synced_at) && ($synced === null || $c->usage_synced_at > $synced)) {
                            $synced = $c->usage_synced_at;
                        }
                    }
                    ?>
                    <table class="wapi-table">
                        <thead><tr>
                            <th>Tenant</th><th>WABA</th>
                            <th><?php echo _l('wapi_stat_active_numbers'); ?></th>
                            <th><?php echo _l('wapi_webhook'); ?></th>
                            <th><?php echo _l('wapi_billing_col'); ?></th>
                            <th class="wapi-ta-r"><?php echo _l('wapi_usage_messages'); ?></th>
                            <th class="wapi-ta-r"><?php echo _l('wapi_usage_cost'); ?></th>
                            <th>Updated</th><th class="wapi-ta-r"></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($connections as $c): ?>
                            <?php $active = (int) ($c->active_count ?? 0); $total = (int) $c->number_count; ?>
                            <tr>
                                <td><strong><?php echo e($c->tenant_slug); ?></strong>
                                    <?php if (!empty($c->fb_user_name)): ?><br><small class="wapi-muted"><?php echo e($c->fb_user_name); ?></small><?php endif; ?>
                                </td>
                                <td><?php echo e($c->waba_name ?: $c->waba_id); ?>
                                    <?php if ((string) ($c->status ?? 'active') === 'stale'): ?>
                                        <br><span title="<?php echo e($c->sync_error ?? ''); ?>"><?php echo whatsapp_badge('#ef4444', _l('wapi_reconnect_required')); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($total === 0): ?>
                                        <span class="wapi-muted">0</span>
                                    <?php else: ?>
                                        <?php echo whatsapp_badge($active === $total ? '#16a34a' : '#ef4444', $active . ' / ' . $total); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo (int) ($c->webhook_subscribed ?? 0) === 1
                                        ? whatsapp_badge('#16a34a', 'Subscribed')
                                        : '<span class="wapi-muted">—</span>'; ?>
                                </td>
                                <td title="<?php echo e($c->credit_error ?? ''); ?>"><?php echo whatsapp_credit_status_badge($c->credit_status ?? 'self'); ?></td>
                                <td class="wapi-ta-r">
                                    <?php if ($c->usage_synced_at ?? null): ?>
                                        <strong><?php echo number_format((int) ($c->usage_messages ?? 0)); ?></strong>
                                        <?php if (!empty($c->usage_days)): ?><br><small class="wapi-muted"><?php echo (int) $c->usage_days; ?>d</small><?php endif; ?>
                                    <?php else: ?>
                                        <span class="wapi-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="wapi-ta-r">
                                    <?php if (!empty($c->usage_error)): ?>
                                        <span class="wapi-danger-text" title="<?php echo e($c->usage_error); ?>"><i class="fa fa-triangle-exclamation"></i></span>
                                    <?php elseif ($c->usage_synced_at ?? null): ?>
                                        <strong><?php echo e($c->usage_currency); ?> <?php echo number_format((float) ($c->usage_cost ?? 0), 2); ?></strong>
                                        <?php if (!empty($c->usage_source)): ?>
                                            <br><small class="wapi-muted" title="<?php echo $c->usage_source === 'pricing' ? 'Per-message pricing' : 'Conversation-based pricing'; ?>"><?php echo $c->usage_source === 'pricing' ? 'per-msg' : 'per-conv'; ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="wapi-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e(whatsapp_time_ago($c->updated_at)); ?></td>
                                <td class="wapi-ta-r">
                                    <?php if ($billing_ready && ($c->credit_status ?? 'self') !== 'shared'): ?>
                                        <button class="wapi-btn wapi-btn-light wapi-btn-sm" title="<?php echo _l('wapi_share_credit_now'); ?>"
                                                onclick="wapiShareCredit('<?php echo e($c->tenant_slug); ?>')"><i class="fa fa-credit-card"></i></button>
                                    <?php endif; ?>
                                    <button class="wapi-btn wapi-btn-ghost wapi-btn-sm wapi-danger" onclick="wapiMasterDisconnect('<?php echo e($c->tenant_slug); ?>')"><i class="fa fa-link-slash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <?php if ($synced !== null): ?>
                        <tfoot>
                            <tr class="wapi-total-row">
                                <td colspan="5"><strong><?php echo _l('wapi_usage_total'); ?></strong>
                                    <small class="wapi-muted"> · <?php echo e(whatsapp_time_ago($synced)); ?></small>
                                </td>
                                <td class="wapi-ta-r"><strong><?php echo number_format($tot_msgs); ?></strong></td>
                                <td class="wapi-ta-r"><strong><?php echo e($cur); ?> <?php echo number_format($tot_cost, 2); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                    <p class="wapi-muted wapi-usage-note"><i class="fa fa-circle-info"></i> <?php echo _l('wapi_usage_note'); ?></p>
                <?php endif; ?>
            </div>

            <!-- ═══════ Master: lend OUR number to tenants ═══════ -->
            <div class="wapi-card wapi-master-card">
                <div class="wapi-card-head">
                    <h3><i class="fa fa-handshake-angle"></i> <?php echo _l('wapi_shared_console'); ?></h3>
                    <div>
                        <button class="wapi-btn wapi-btn-primary wapi-btn-sm" onclick="wapiSharedGrant('')">
                            <i class="fa fa-plus"></i> <?php echo _l('wapi_shared_add_tenant'); ?>
                        </button>
                    </div>
                </div>

                <p class="wapi-modal-lead"><?php echo _l('wapi_shared_console_lead'); ?></p>

                <form id="wapi-shared-settings-form" onsubmit="return wapiSaveSharedSettings(event)">
                    <div class="wapi-grid-2">
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_shared_brand_label'); ?></label>
                            <input type="text" name="shared_brand" value="<?php echo e($shared_settings['brand']); ?>"
                                   placeholder="<?php echo e(get_option('companyname')); ?>">
                            <small class="wapi-hint"><?php echo _l('wapi_shared_brand_hint'); ?></small>
                        </div>
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_shared_default_number_label'); ?></label>
                            <select name="shared_number">
                                <option value=""><?php echo _l('wapi_shared_use_default'); ?></option>
                                <?php foreach ($shared_numbers as $n): ?>
                                    <option value="<?php echo e($n->phone_number_id); ?>"
                                        <?php echo $shared_settings['number'] === (string) $n->phone_number_id ? 'selected' : ''; ?>>
                                        <?php echo e($n->display_phone_number ?: $n->phone_number_id); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="wapi-hint"><?php echo _l('wapi_shared_default_number_hint'); ?></small>
                        </div>
                    </div>

                    <span class="wapi-switch-label">
                        <label class="wapi-switch">
                            <input type="checkbox" name="shared_enabled" value="1" <?php echo $shared_settings['enabled'] ? 'checked' : ''; ?>>
                            <span class="wapi-slider"></span>
                        </label>
                        <?php echo _l('wapi_shared_master_switch'); ?>
                    </span>

                    <button type="submit" class="wapi-btn wapi-btn-primary wapi-btn-sm"><i class="fa fa-floppy-disk"></i> <?php echo _l('submit'); ?></button>
                </form>

                <?php if (!$shared_settings['enabled']): ?>
                    <div class="wapi-alert wapi-alert-info" style="margin-top:12px">
                        <i class="fa fa-circle-info"></i>
                        <div>
                            <strong><?php echo _l('wapi_shared_suspended_title'); ?></strong>
                            <p><?php echo _l('wapi_shared_suspended_body'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <div id="wapi-shared-table" style="margin-top:16px">
                    <?php $this->load->view('partials/shared_table', [
                        'shared_settings' => $shared_settings,
                        'shared_grants'   => $shared_grants,
                        'shared_tenants'  => $shared_tenants,
                        'shared_numbers'  => $shared_numbers,
                    ]); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; // overview ?>

        <!-- ═══════════ Inbox ═══════════ -->
        <?php if ($wapi_tab['inbox']): ?>
        <div class="wapi-panel<?php echo $wapi_first_tab === 'inbox' ? ' active' : ''; ?>" data-panel="inbox">
            <div class="wapi-chat">
                <div class="wapi-chat-side">
                    <div class="wapi-chat-toolbar">
                        <div class="wapi-search-wrap">
                            <i class="fa fa-magnifying-glass"></i>
                            <input type="text" id="wapi-thread-search" placeholder="<?php echo _l('wapi_search_chats'); ?>" autocomplete="off">
                        </div>
                        <select id="wapi-thread-scope" title="<?php echo _l('wapi_scope_hint'); ?>">
                            <option value="conversations"><?php echo _l('wapi_scope_conversations'); ?></option>
                            <option value="all"><?php echo _l('wapi_scope_all'); ?></option>
                        </select>
                        <button class="wapi-btn wapi-btn-ghost wapi-btn-sm" title="<?php echo _l('wapi_refresh'); ?>" onclick="wapiLoadThreads(true)"><i class="fa fa-rotate"></i></button>
                    </div>
                    <div class="wapi-chat-threads" id="wapi-chat-threads">
                        <div class="wapi-empty wapi-empty-sm"><p><i class="fa fa-circle-notch fa-spin"></i></p></div>
                    </div>
                </div>
                <div class="wapi-chat-main">
                    <div class="wapi-chat-header" id="wapi-chat-header" style="display:none">
                        <div>
                            <strong id="wapi-chat-name"></strong>
                            <span class="wapi-muted" id="wapi-chat-phone"></span>
                        </div>
                        <span id="wapi-chat-window"></span>
                    </div>
                    <div class="wapi-chat-messages" id="wapi-chat-messages">
                        <div class="wapi-chat-placeholder"><i class="fa-brands fa-whatsapp"></i><p><?php echo _l('wapi_select_conversation'); ?></p></div>
                    </div>
                    <?php if ($wapi_shared_on): ?>
                        <?php // Replies arrive in the provider's inbox, not here — there is no window to answer inside. ?>
                        <div class="wapi-chat-shared-note">
                            <i class="fa fa-circle-info"></i>
                            <?php echo sprintf(_l('wapi_shared_inbox_note'), e($shared['brand'])); ?>
                        </div>
                    <?php else: ?>
                    <div class="wapi-chat-input" id="wapi-chat-input" style="display:none">
                        <div class="wapi-attach-bar" id="wapi-chat-attach-bar" style="display:none">
                            <div class="wapi-attach-info">
                                <i class="fa fa-paperclip"></i>
                                <span id="wapi-chat-attach-name"></span>
                                <span class="wapi-muted" id="wapi-chat-attach-size"></span>
                            </div>
                            <div class="wapi-attach-controls">
                                <input type="text" id="wapi-chat-attach-caption" placeholder="<?php echo _l('wapi_attach_caption'); ?>">
                                <button class="wapi-btn wapi-btn-primary" id="wapi-chat-attach-send" title="<?php echo _l('wapi_send'); ?>" onclick="wapiSendChatMedia()"><i class="fa fa-paper-plane"></i></button>
                                <button class="wapi-btn wapi-btn-ghost" title="<?php echo _l('wapi_attach_cancel'); ?>" onclick="wapiCancelAttach()"><i class="fa fa-xmark"></i></button>
                            </div>
                        </div>
                        <div id="wapi-chat-text-row">
                            <input type="file" id="wapi-chat-file" style="display:none"
                                   accept="image/jpeg,image/png,image/webp,video/mp4,video/3gpp,audio/aac,audio/mp4,audio/mpeg,audio/amr,audio/ogg,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                            <button class="wapi-btn wapi-btn-ghost wapi-chat-icon-btn" title="<?php echo _l('wapi_attach_file'); ?>" onclick="$('#wapi-chat-file').click()"><i class="fa fa-paperclip"></i></button>
                            <textarea id="wapi-chat-textarea" rows="1" placeholder="<?php echo _l('wapi_type_message'); ?>"></textarea>
                            <button class="wapi-btn wapi-btn-ghost wapi-chat-icon-btn" id="wapi-chat-tpl-toggle" title="<?php echo _l('wapi_use_template'); ?>" onclick="wapiToggleChatTemplate()"><i class="fa fa-file-lines"></i></button>
                            <button class="wapi-btn wapi-btn-primary" onclick="wapiSendChat()"><i class="fa fa-paper-plane"></i></button>
                        </div>
                        <div id="wapi-chat-template-row" style="display:none">
                            <div class="wapi-window-note" id="wapi-window-note"><i class="fa fa-clock"></i> <span id="wapi-window-reason"><?php echo _l('wapi_window_closed'); ?></span></div>
                            <div class="wapi-chat-template-controls">
                                <select id="wapi-chat-template" class="wapi-template-select"></select>
                                <input type="text" id="wapi-chat-template-params" placeholder="<?php echo _l('wapi_template_params'); ?>" style="display:none">
                                <input type="text" id="wapi-chat-template-media" placeholder="<?php echo _l('wapi_header_media_url'); ?>" style="display:none">
                                <button class="wapi-btn wapi-btn-primary" id="wapi-chat-tpl-send" onclick="wapiSendChatTemplate()"><i class="fa fa-paper-plane"></i></button>
                            </div>
                            <div class="wapi-muted wapi-chat-template-preview" id="wapi-chat-template-preview"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; // inbox ?>

        <!-- ═══════════ Send ═══════════ -->
        <?php if ($wapi_tab['send']): ?>
        <div class="wapi-panel<?php echo $wapi_first_tab === 'send' ? ' active' : ''; ?>" data-panel="send">
            <div class="wapi-card wapi-card-narrow">
                <div class="wapi-card-head"><h3><i class="fa fa-paper-plane"></i> <?php echo _l('wapi_send_message'); ?></h3></div>
                <form id="wapi-send-form" onsubmit="return wapiSendSingle(event)">
                    <?php if (count($numbers) > 1): ?>
                    <div class="wapi-field">
                        <label><?php echo _l('wapi_phone_numbers'); ?></label>
                        <select name="phone_number_id">
                            <?php foreach ($numbers as $n): ?>
                                <option value="<?php echo e($n->phone_number_id); ?>" <?php echo (int) $n->is_default === 1 ? 'selected' : ''; ?>><?php echo e($n->display_phone_number . ' — ' . $n->verified_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="wapi-field">
                        <label><?php echo _l('wapi_recipient'); ?></label>
                        <input type="text" name="to" placeholder="e.g. 919876543210" required>
                    </div>
                    <?php if ($wapi_shared_on): ?>
                        <?php // Template-only on the provider's number: no free text, no mode switch. ?>
                        <input type="hidden" name="mode" value="template">
                    <?php else: ?>
                    <div class="wapi-field">
                        <label class="wapi-radio"><input type="radio" name="mode" value="text" checked onchange="wapiSendModeChanged()"> <?php echo _l('wapi_free_text'); ?></label>
                        <label class="wapi-radio"><input type="radio" name="mode" value="template" onchange="wapiSendModeChanged()"> <?php echo _l('wapi_template_message'); ?></label>
                    </div>
                    <div class="wapi-field" id="wapi-send-text-field">
                        <label><?php echo _l('wapi_message'); ?></label>
                        <textarea name="message" rows="4"></textarea>
                    </div>
                    <?php endif; ?>
                    <div id="wapi-send-template-fields"<?php echo $wapi_shared_on ? '' : ' style="display:none"'; ?>>
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_template'); ?></label>
                            <select name="template" class="wapi-template-select" onchange="wapiSendTemplateChanged(this)"></select>
                        </div>
                        <div class="wapi-field" id="wapi-send-params-field" style="display:none">
                            <label><?php echo _l('wapi_template_params'); ?></label>
                            <input type="text" name="params" placeholder="value 1, value 2">
                        </div>
                        <div class="wapi-field" id="wapi-send-header-media-field" style="display:none">
                            <label><?php echo _l('wapi_header_media_url'); ?></label>
                            <input type="text" name="header_media_url" placeholder="https://…">
                        </div>
                        <div class="wapi-template-preview wapi-muted" id="wapi-send-template-preview"></div>
                    </div>
                    <button type="submit" class="wapi-btn wapi-btn-primary"><i class="fa fa-paper-plane"></i> <?php echo _l('wapi_send'); ?></button>
                </form>
            </div>
        </div>
        <?php endif; // send ?>

        <!-- ═══════════ Bulk campaigns ═══════════ -->
        <?php if ($wapi_tab['bulk']): ?>
        <div class="wapi-panel<?php echo $wapi_first_tab === 'bulk' ? ' active' : ''; ?>" data-panel="bulk">
            <div class="wapi-card">
                <div class="wapi-card-head">
                    <h3><i class="fa fa-bullhorn"></i> <?php echo _l('wapi_tab_bulk'); ?></h3>
                    <div>
                        <button class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiRunQueue()"><i class="fa fa-forward"></i> <?php echo _l('wapi_process_queue'); ?></button>
                        <button class="wapi-btn wapi-btn-primary wapi-btn-sm" onclick="wapiOpenCampaignModal()"><i class="fa fa-plus"></i> <?php echo _l('wapi_new_campaign'); ?></button>
                    </div>
                </div>
                <?php if (empty($campaigns)): ?>
                    <div class="wapi-empty"><i class="fa fa-bullhorn"></i><p><?php echo _l('wapi_no_campaigns'); ?></p></div>
                <?php else: ?>
                    <table class="wapi-table">
                        <thead><tr>
                            <th><?php echo _l('wapi_campaign_name'); ?></th><th><?php echo _l('wapi_template'); ?></th>
                            <th><?php echo _l('wapi_progress'); ?></th><th>Delivered / Read / Failed</th>
                            <th>Status</th><th class="wapi-ta-r">Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($campaigns as $c): ?>
                            <?php $pct = $c->total_count > 0 ? round(($c->sent_count + $c->failed_count) / $c->total_count * 100) : 0; ?>
                            <tr data-campaign-row="<?php echo (int) $c->id; ?>"<?php echo in_array($c->status, ['running', 'scheduled', 'paused'], true) ? ' data-campaign-poll="1"' : ''; ?>>
                                <td><strong><?php echo e($c->name); ?></strong><br><small class="wapi-muted"><?php echo e(whatsapp_time_ago($c->created_at)); ?><?php if ($c->scheduled_at): ?> · <i class="fa fa-clock"></i> <?php echo e($c->scheduled_at); ?><?php endif; ?></small></td>
                                <td><code class="wapi-code"><?php echo e($c->template_name); ?></code></td>
                                <td>
                                    <div class="wapi-progress"><div class="wapi-progress-bar" style="width:<?php echo $pct; ?>%"></div></div>
                                    <small class="wapi-muted"><span data-campaign-sent><?php echo (int) $c->sent_count; ?></span>/<?php echo (int) $c->total_count; ?></small>
                                </td>
                                <td><small><span data-campaign-delivered><?php echo (int) $c->delivered_count; ?></span> / <span data-campaign-read><?php echo (int) $c->read_count; ?></span> / <span data-campaign-failed class="wapi-danger-text"><?php echo (int) $c->failed_count; ?></span></small></td>
                                <td data-campaign-status><?php echo whatsapp_campaign_status_badge($c->status); ?></td>
                                <td class="wapi-ta-r">
                                    <?php if (in_array($c->status, ['running', 'scheduled'], true)): ?>
                                        <button class="wapi-btn wapi-btn-light wapi-btn-sm" title="Pause" onclick="wapiCampaignAction(<?php echo (int) $c->id; ?>, 'pause')"><i class="fa fa-pause"></i></button>
                                    <?php elseif ($c->status === 'paused' || $c->status === 'draft'): ?>
                                        <button class="wapi-btn wapi-btn-light wapi-btn-sm" title="Start" onclick="wapiCampaignAction(<?php echo (int) $c->id; ?>, 'resume')"><i class="fa fa-play"></i></button>
                                    <?php endif; ?>
                                    <?php if (in_array($c->status, ['running', 'scheduled', 'paused'], true)): ?>
                                        <button class="wapi-btn wapi-btn-ghost wapi-btn-sm" title="Cancel" onclick="wapiCampaignAction(<?php echo (int) $c->id; ?>, 'cancel')"><i class="fa fa-stop"></i></button>
                                    <?php endif; ?>
                                    <button class="wapi-btn wapi-btn-light wapi-btn-sm" title="Details" onclick="wapiCampaignDetails(<?php echo (int) $c->id; ?>)"><i class="fa fa-eye"></i></button>
                                    <button class="wapi-btn wapi-btn-ghost wapi-btn-sm wapi-danger" title="Delete" onclick="wapiDeleteCampaign(<?php echo (int) $c->id; ?>)"><i class="fa fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; // bulk ?>

        <!-- ═══════════ Templates ═══════════ -->
        <?php if ($wapi_tab['templates']): ?>
        <div class="wapi-panel<?php echo $wapi_first_tab === 'templates' ? ' active' : ''; ?>" data-panel="templates">
            <div class="wapi-card">
                <div class="wapi-card-head">
                    <h3><i class="fa fa-file-lines"></i> <?php echo _l('wapi_tab_templates'); ?></h3>
                    <div>
                        <?php if ($wapi_shared_on): ?>
                            <button class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiSharedSync()"><i class="fa fa-rotate"></i> <?php echo _l('wapi_shared_refresh_templates'); ?></button>
                        <?php else: ?>
                            <button class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiSyncTemplates()"><i class="fa fa-rotate"></i> <?php echo _l('wapi_sync_templates'); ?></button>
                            <button class="wapi-btn wapi-btn-primary wapi-btn-sm" onclick="wapiOpenTemplateModal()"><i class="fa fa-plus"></i> <?php echo _l('wapi_new_template'); ?></button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($wapi_shared_on): ?>
                    <p class="wapi-modal-lead"><?php echo sprintf(_l('wapi_shared_templates_lead'), e($shared['brand'])); ?></p>
                <?php endif; ?>
                <?php if (empty($templates)): ?>
                    <div class="wapi-empty"><i class="fa fa-file-lines"></i><p><?php echo $wapi_shared_on ? _l('wapi_shared_no_templates_yet') : _l('wapi_no_templates'); ?></p></div>
                <?php else: ?>
                    <table class="wapi-table">
                        <thead><tr><th><?php echo _l('wapi_template_name'); ?></th><th><?php echo _l('wapi_template_language'); ?></th><th><?php echo _l('wapi_template_category'); ?></th><th><?php echo _l('wapi_variables'); ?></th><th>Status</th><th class="wapi-ta-r"></th></tr></thead>
                        <tbody>
                        <?php foreach ($templates as $t): ?>
                            <?php
                            $t_status   = strtoupper((string) $t->status);
                            $t_editable = whatsapp_template_editable($t_status);
                            $t_reason   = whatsapp_template_rejected_reason($t->rejected_reason ?? '');
                            ?>
                            <tr class="wapi-tpl-row<?php echo $t_status === 'REJECTED' ? ' wapi-row-danger' : ''; ?>" onclick="wapiTemplateDetails(<?php echo (int) $t->id; ?>)" title="<?php echo _l('wapi_template_view'); ?>">
                                <td>
                                    <strong><?php echo e($t->name); ?></strong>
                                    <br><small class="wapi-muted"><?php echo e(mb_strimwidth((string) $t->body_text, 0, 90, '…')); ?></small>
                                    <?php if ($t_status === 'REJECTED' && $t_reason['label'] !== ''): ?>
                                        <br><small class="wapi-danger-text"><i class="fa fa-circle-exclamation"></i> <?php echo e($t_reason['label']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($t->language); ?></td>
                                <td><?php echo e($t->category); ?></td>
                                <td><?php echo (int) $t->variables_count; ?><?php echo (int) $t->has_media_header === 1 ? ' <i class="fa fa-image wapi-muted" title="Media header"></i>' : ''; ?></td>
                                <td><?php echo whatsapp_template_status_badge($t->status); ?></td>
                                <td class="wapi-ta-r wapi-row-actions" onclick="event.stopPropagation()">
                                    <button class="wapi-btn wapi-btn-light wapi-btn-sm" title="<?php echo _l('wapi_template_view'); ?>" onclick="wapiTemplateDetails(<?php echo (int) $t->id; ?>)"><i class="fa fa-eye"></i></button>
                                    <?php if ((string) ($t->source ?? 'own') === 'shared'): ?>
                                        <?php // Provider-owned: this account may send it, never change it. ?>
                                        <span class="wapi-muted wapi-tpl-shared-tag" title="<?php echo e(sprintf(_l('wapi_shared_tpl_owned'), $shared['brand'])); ?>"><i class="fa fa-handshake-angle"></i></span>
                                    <?php elseif ($t_editable): ?>
                                        <button class="wapi-btn wapi-btn-light wapi-btn-sm" title="<?php echo $t_status === 'REJECTED' ? _l('wapi_template_fix_resubmit') : _l('wapi_edit_template'); ?>" onclick="wapiEditTemplate(<?php echo (int) $t->id; ?>)"><i class="fa fa-pen"></i></button>
                                    <?php else: ?>
                                        <button class="wapi-btn wapi-btn-light wapi-btn-sm" disabled title="<?php echo e(whatsapp_template_edit_block_reason($t_status)); ?>"><i class="fa fa-pen"></i></button>
                                    <?php endif; ?>
                                    <?php if ((string) ($t->source ?? 'own') !== 'shared'): ?>
                                        <button class="wapi-btn wapi-btn-ghost wapi-btn-sm wapi-danger" title="Delete" onclick="wapiDeleteTemplate('<?php echo e($t->name); ?>')"><i class="fa fa-trash"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; // templates ?>

        <!-- ═══════════ Bot ═══════════ -->
        <?php if ($wapi_tab['bot']): ?>
        <div class="wapi-panel<?php echo $wapi_first_tab === 'bot' ? ' active' : ''; ?>" data-panel="bot">
            <div class="wapi-card">
                <div class="wapi-card-head">
                    <h3><i class="fa fa-robot"></i> <?php echo _l('wapi_bot_rules'); ?></h3>
                    <button class="wapi-btn wapi-btn-primary wapi-btn-sm" onclick="wapiOpenRule()"><i class="fa fa-plus"></i> <?php echo _l('wapi_new_rule'); ?></button>
                </div>

                <form id="wapi-bot-settings-form" class="wapi-bot-settings" onsubmit="return wapiSaveBotSettings(event)">
                    <span class="wapi-switch-label">
                        <label class="wapi-switch"><input type="checkbox" name="enabled" value="1" <?php echo !empty($bot_settings['enabled']) ? 'checked' : ''; ?>><span class="wapi-slider"></span></label>
                        <?php echo _l('wapi_bot_enabled'); ?>
                    </span>
                    <div class="wapi-bot-hours">
                        <span class="wapi-switch-label">
                            <label class="wapi-switch"><input type="checkbox" name="business_hours" value="1" <?php echo !empty($bot_settings['business_hours']) ? 'checked' : ''; ?>><span class="wapi-slider"></span></label>
                            <?php echo _l('wapi_business_hours_only'); ?>
                        </span>
                        <span><?php echo _l('wapi_open_time'); ?> <input type="time" name="open_time" value="<?php echo e($bot_settings['open_time']); ?>"></span>
                        <span><?php echo _l('wapi_close_time'); ?> <input type="time" name="close_time" value="<?php echo e($bot_settings['close_time']); ?>"></span>
                        <span class="wapi-days">
                            <?php $day_labels = [1 => 'Mo', 2 => 'Tu', 3 => 'We', 4 => 'Th', 5 => 'Fr', 6 => 'Sa', 7 => 'Su']; ?>
                            <?php foreach ($day_labels as $d => $lbl): ?>
                                <label class="wapi-day"><input type="checkbox" name="days[]" value="<?php echo $d; ?>" <?php echo in_array($d, (array) $bot_settings['days'], true) ? 'checked' : ''; ?>> <?php echo $lbl; ?></label>
                            <?php endforeach; ?>
                        </span>
                        <input type="hidden" name="tz_offset" value="<?php echo e($bot_settings['tz_offset']); ?>">
                        <button type="submit" class="wapi-btn wapi-btn-light wapi-btn-sm"><i class="fa fa-floppy-disk"></i> <?php echo _l('submit'); ?></button>
                    </div>
                </form>

                <?php if (empty($rules)): ?>
                    <div class="wapi-empty"><i class="fa fa-robot"></i><p><?php echo _l('wapi_no_rules'); ?></p></div>
                <?php else: ?>
                    <table class="wapi-table">
                        <thead><tr><th><?php echo _l('wapi_rule_name'); ?></th><th><?php echo _l('wapi_rule_trigger'); ?></th><th><?php echo _l('wapi_keywords'); ?></th><th><?php echo _l('wapi_rule_hits'); ?></th><th><?php echo _l('wapi_rule_enabled'); ?></th><th class="wapi-ta-r">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($rules as $r): ?>
                            <tr>
                                <td><strong><?php echo e($r->name); ?></strong><br><small class="wapi-muted"><?php echo e(mb_strimwidth((string) $r->response_text, 0, 60, '…')); ?></small></td>
                                <td><span class="wapi-badge wapi-badge-soft"><?php echo e(ucfirst($r->trigger_type)); ?></span></td>
                                <td><small><?php echo e(mb_strimwidth((string) $r->keywords, 0, 40, '…')); ?></small></td>
                                <td><span class="wapi-badge wapi-badge-soft"><?php echo (int) $r->hits; ?></span></td>
                                <td>
                                    <label class="wapi-switch"><input type="checkbox" <?php echo (int) $r->enabled === 1 ? 'checked' : ''; ?> onchange="wapiToggleRule(<?php echo (int) $r->id; ?>, this.checked ? 1 : 0)"><span class="wapi-slider"></span></label>
                                </td>
                                <td class="wapi-ta-r">
                                    <button class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiOpenRule(<?php echo (int) $r->id; ?>)"><i class="fa fa-pen"></i></button>
                                    <button class="wapi-btn wapi-btn-ghost wapi-btn-sm wapi-danger" onclick="wapiDeleteRule(<?php echo (int) $r->id; ?>)"><i class="fa fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; // bot ?>

        <!-- ═══════════ Business profile / branding ═══════════ -->
        <?php if ($wapi_tab['profile']): ?>
        <div class="wapi-panel<?php echo $wapi_first_tab === 'profile' ? ' active' : ''; ?>" data-panel="profile">
            <?php if (empty($numbers)): ?>
                <div class="wapi-card"><div class="wapi-empty"><i class="fa fa-id-badge"></i><p><?php echo _l('wapi_not_connected'); ?></p></div></div>
            <?php else: ?>
            <div class="wapi-card">
                <div class="wapi-card-head">
                    <h3><i class="fa fa-id-badge"></i> <?php echo _l('wapi_profile_title'); ?></h3>
                    <div>
                        <?php if (count($numbers) > 1): ?>
                            <select id="wapi-profile-number" class="wapi-inline-select">
                                <?php foreach ($numbers as $n): ?>
                                    <option value="<?php echo e($n->phone_number_id); ?>" <?php echo (int) $n->is_default === 1 ? 'selected' : ''; ?>>
                                        <?php echo e($n->display_phone_number ?: $n->phone_number_id); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="hidden" id="wapi-profile-number" value="<?php echo e($numbers[0]->phone_number_id); ?>">
                        <?php endif; ?>
                        <button class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiLoadProfile()"><i class="fa fa-rotate"></i> <?php echo _l('wapi_reload_profile'); ?></button>
                    </div>
                </div>
                <p class="wapi-modal-lead"><?php echo _l('wapi_profile_lead'); ?></p>

                <form id="wapi-profile-form" onsubmit="return wapiSaveProfile(event)">
                    <div class="wapi-profile-grid">

                        <!-- Live preview of what a customer sees -->
                        <div class="wapi-profile-preview">
                            <div class="wapi-pp-card">
                                <div class="wapi-pp-avatar" id="wapi-pp-avatar"><i class="fa fa-building"></i></div>
                                <div class="wapi-pp-name" id="wapi-pp-name">—</div>
                                <div class="wapi-pp-phone" id="wapi-pp-phone">—</div>
                                <div class="wapi-pp-about" id="wapi-pp-about"></div>
                                <ul class="wapi-pp-meta">
                                    <li id="wapi-pp-row-desc"><i class="fa fa-align-left"></i> <span id="wapi-pp-desc"></span></li>
                                    <li id="wapi-pp-row-addr"><i class="fa fa-location-dot"></i> <span id="wapi-pp-addr"></span></li>
                                    <li id="wapi-pp-row-email"><i class="fa fa-envelope"></i> <span id="wapi-pp-email"></span></li>
                                    <li id="wapi-pp-row-web"><i class="fa fa-globe"></i> <span id="wapi-pp-web"></span></li>
                                    <li id="wapi-pp-row-vert"><i class="fa fa-tag"></i> <span id="wapi-pp-vert"></span></li>
                                </ul>
                            </div>

                            <div class="wapi-field wapi-pic-field">
                                <label><?php echo _l('wapi_profile_picture'); ?></label>
                                <input type="file" name="picture" id="wapi-profile-picture" accept="image/jpeg,image/png" onchange="wapiPreviewPicture(this)">
                                <small class="wapi-muted"><?php echo _l('wapi_profile_picture_hint'); ?></small>
                            </div>
                        </div>

                        <!-- Editable fields -->
                        <div class="wapi-profile-fields">
                            <input type="hidden" name="phone_number_id" value="">

                            <div class="wapi-field">
                                <label><?php echo _l('wapi_profile_about'); ?> <span class="wapi-counter" data-for="about">0/139</span></label>
                                <input type="text" name="about" maxlength="139" oninput="wapiProfileChanged()" placeholder="<?php echo _l('wapi_profile_about_ph'); ?>">
                                <small class="wapi-muted"><?php echo _l('wapi_profile_about_hint'); ?></small>
                            </div>

                            <div class="wapi-field">
                                <label><?php echo _l('wapi_profile_description'); ?> <span class="wapi-counter" data-for="description">0/512</span></label>
                                <textarea name="description" rows="3" maxlength="512" oninput="wapiProfileChanged()" placeholder="<?php echo _l('wapi_profile_description_ph'); ?>"></textarea>
                            </div>

                            <div class="wapi-grid-2">
                                <div class="wapi-field">
                                    <label><?php echo _l('wapi_profile_category'); ?></label>
                                    <select name="vertical" onchange="wapiProfileChanged()">
                                        <?php foreach (whatsapp_business_verticals() as $key => $label): ?>
                                            <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="wapi-field">
                                    <label><?php echo _l('wapi_profile_email'); ?> <span class="wapi-counter" data-for="email">0/128</span></label>
                                    <input type="email" name="email" maxlength="128" oninput="wapiProfileChanged()" placeholder="care@example.com">
                                </div>
                            </div>

                            <div class="wapi-field">
                                <label><?php echo _l('wapi_profile_address'); ?> <span class="wapi-counter" data-for="address">0/256</span></label>
                                <input type="text" name="address" maxlength="256" oninput="wapiProfileChanged()" placeholder="<?php echo _l('wapi_profile_address_ph'); ?>">
                            </div>

                            <div class="wapi-grid-2">
                                <div class="wapi-field">
                                    <label><?php echo _l('wapi_profile_website_1'); ?></label>
                                    <input type="text" name="website_1" maxlength="256" oninput="wapiProfileChanged()" placeholder="https://example.com">
                                </div>
                                <div class="wapi-field">
                                    <label><?php echo _l('wapi_profile_website_2'); ?></label>
                                    <input type="text" name="website_2" maxlength="256" oninput="wapiProfileChanged()" placeholder="https://example.com/contact">
                                </div>
                            </div>

                            <button type="submit" class="wapi-btn wapi-btn-primary"><i class="fa fa-floppy-disk"></i> <?php echo _l('wapi_save_profile'); ?></button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Display name (Meta-reviewed) -->
            <div class="wapi-card">
                <div class="wapi-card-head">
                    <h3><i class="fa fa-signature"></i> <?php echo _l('wapi_display_name'); ?></h3>
                    <span id="wapi-name-status"></span>
                </div>
                <p class="wapi-modal-lead"><?php echo _l('wapi_display_name_lead'); ?></p>
                <form id="wapi-name-form" onsubmit="return wapiRequestDisplayName(event)">
                    <div class="wapi-param-controls">
                        <input type="text" name="display_name" maxlength="75" placeholder="<?php echo _l('wapi_display_name_ph'); ?>">
                        <button type="submit" class="wapi-btn wapi-btn-primary"><i class="fa fa-paper-plane"></i> <?php echo _l('wapi_submit_for_review'); ?></button>
                    </div>
                    <div class="wapi-alert wapi-alert-info" style="margin-top:12px">
                        <i class="fa fa-circle-info"></i>
                        <div><?php echo _l('wapi_display_name_policy'); ?></div>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; // profile ?>

        <!-- ═══════════ Contacts ═══════════ -->
        <?php if ($wapi_tab['contacts']): ?>
        <div class="wapi-panel<?php echo $wapi_first_tab === 'contacts' ? ' active' : ''; ?>" data-panel="contacts">
            <div class="wapi-card">
                <div class="wapi-card-head"><h3><i class="fa fa-address-book"></i> <?php echo _l('wapi_tab_contacts'); ?></h3></div>
                <?php if (empty($contacts)): ?>
                    <div class="wapi-empty"><i class="fa fa-address-book"></i><p><?php echo _l('wapi_no_contacts'); ?></p></div>
                <?php else: ?>
                    <table class="wapi-table">
                        <thead><tr><th><?php echo _l('wapi_contact'); ?></th><th>Phone</th><th><?php echo _l('wapi_last_incoming'); ?></th><th><?php echo _l('wapi_last_outgoing'); ?></th><th><?php echo _l('wapi_opted_out'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($contacts as $ct): ?>
                            <tr>
                                <td><strong><?php echo e($ct->name ?: $ct->profile_name ?: '—'); ?></strong></td>
                                <td><code class="wapi-code"><?php echo e($ct->phone); ?></code></td>
                                <td><?php echo e(whatsapp_time_ago($ct->last_incoming_at)); ?></td>
                                <td><?php echo e(whatsapp_time_ago($ct->last_outgoing_at)); ?></td>
                                <td>
                                    <label class="wapi-switch"><input type="checkbox" <?php echo (int) $ct->opted_out === 1 ? 'checked' : ''; ?> onchange="wapiToggleOptout('<?php echo e($ct->phone); ?>', this.checked ? 1 : 0)"><span class="wapi-slider"></span></label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; // contacts ?>

        <!-- ═══════════ Settings ═══════════ -->
        <?php if ($wapi_tab['settings']): ?>
        <div class="wapi-panel<?php echo $wapi_first_tab === 'settings' ? ' active' : ''; ?>" data-panel="settings">
            <div class="wapi-card wapi-card-narrow">
                <div class="wapi-card-head"><h3><i class="fa fa-gear"></i> <?php echo _l('wapi_tab_settings'); ?></h3></div>
                <form onsubmit="return wapiSaveSettings(event)">
                    <div class="wapi-field">
                        <label><?php echo _l('wapi_default_country_code'); ?></label>
                        <input type="text" name="default_country_code" value="<?php echo e($country_code); ?>" style="max-width:120px">
                    </div>
                    <button type="submit" class="wapi-btn wapi-btn-primary wapi-btn-sm"><i class="fa fa-floppy-disk"></i> <?php echo _l('submit'); ?></button>
                </form>
            </div>

            <!-- Inbox arrival notifications -->
            <div class="wapi-card wapi-card-narrow">
                <div class="wapi-card-head">
                    <h3><i class="fa fa-bell"></i> <?php echo _l('wapi_notify_settings'); ?></h3>
                </div>
                <p class="wapi-muted wapi-notify-intro"><?php echo _l('wapi_notify_settings_hint'); ?></p>

                <form id="wapi-notify-form" onsubmit="return wapiSaveNotifySettings(event)">
                    <div class="wapi-notify-switches">
                        <span class="wapi-switch-label">
                            <label class="wapi-switch"><input type="checkbox" name="enabled" value="1" <?php echo !empty($notify['enabled']) ? 'checked' : ''; ?>><span class="wapi-slider"></span></label>
                            <?php echo _l('wapi_notify_enabled'); ?>
                        </span>
                        <span class="wapi-switch-label">
                            <label class="wapi-switch"><input type="checkbox" name="toast" value="1" <?php echo !empty($notify['toast']) ? 'checked' : ''; ?>><span class="wapi-slider"></span></label>
                            <?php echo _l('wapi_notify_toast'); ?>
                        </span>
                        <span class="wapi-switch-label">
                            <label class="wapi-switch"><input type="checkbox" name="desktop" value="1" <?php echo !empty($notify['desktop']) ? 'checked' : ''; ?>><span class="wapi-slider"></span></label>
                            <?php echo _l('wapi_notify_desktop'); ?>
                        </span>
                        <span class="wapi-switch-label">
                            <label class="wapi-switch"><input type="checkbox" name="sound" value="1" <?php echo !empty($notify['sound']) ? 'checked' : ''; ?>><span class="wapi-slider"></span></label>
                            <?php echo _l('wapi_notify_sound'); ?>
                        </span>
                        <span class="wapi-switch-label">
                            <label class="wapi-switch"><input type="checkbox" name="bell" value="1" <?php echo !empty($notify['bell']) ? 'checked' : ''; ?>><span class="wapi-slider"></span></label>
                            <?php echo _l('wapi_notify_bell'); ?>
                        </span>
                    </div>

                    <div class="wapi-grid-2">
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_notify_recipients'); ?></label>
                            <select name="recipients" onchange="wapiNotifyRecipientsChanged(this)">
                                <option value="inbox" <?php echo $notify['recipients'] === 'inbox' ? 'selected' : ''; ?>><?php echo _l('wapi_notify_recipients_all'); ?></option>
                                <option value="selected" <?php echo $notify['recipients'] === 'selected' ? 'selected' : ''; ?>><?php echo _l('wapi_notify_recipients_selected'); ?></option>
                            </select>
                        </div>
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_notify_throttle'); ?></label>
                            <input type="number" name="throttle" min="10" max="3600" value="<?php echo (int) $notify['throttle']; ?>">
                            <small class="wapi-muted"><?php echo _l('wapi_notify_throttle_hint'); ?></small>
                        </div>
                    </div>

                    <div class="wapi-field" id="wapi-notify-staff-field" style="<?php echo $notify['recipients'] === 'selected' ? '' : 'display:none'; ?>">
                        <label><?php echo _l('wapi_notify_staff'); ?></label>
                        <?php if (empty($notify_staff)): ?>
                            <p class="wapi-muted"><?php echo _l('wapi_notify_no_staff'); ?></p>
                        <?php else: ?>
                            <div class="wapi-notify-staff">
                                <?php foreach ($notify_staff as $st): ?>
                                    <label class="wapi-day"><input type="checkbox" name="staff[]" value="<?php echo (int) $st['id']; ?>" <?php echo in_array((int) $st['id'], (array) $notify['staff'], true) ? 'checked' : ''; ?>> <?php echo e($st['name']); ?></label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="wapi-preview-line">
                        <button type="submit" class="wapi-btn wapi-btn-primary wapi-btn-sm"><i class="fa fa-floppy-disk"></i> <?php echo _l('submit'); ?></button>
                        <button type="button" class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiTestNotification()"><i class="fa fa-bell"></i> <?php echo _l('wapi_notify_test'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; // settings ?>

        <!-- ═══════════ Campaign modal ═══════════ -->
        <?php if ($wapi_tab['bulk']): ?>
        <div class="wapi-modal" id="wapi-campaign-modal">
            <div class="wapi-modal-box wapi-modal-lg">
                <div class="wapi-modal-head">
                    <h3><?php echo _l('wapi_new_campaign'); ?></h3>
                    <button class="wapi-modal-close" onclick="wapiCloseModal('wapi-campaign-modal')">&times;</button>
                </div>
                <form id="wapi-campaign-form" onsubmit="return wapiCreateCampaign(event)">
                    <div class="wapi-grid-2">
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_campaign_name'); ?></label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_phone_numbers'); ?></label>
                            <select name="phone_number_id">
                                <?php if ($wapi_shared_on): ?>
                                    <?php // Only the provider's number is available, and it is not this account's to choose. ?>
                                    <option value=""><?php echo e($shared['number_label'] ?: $shared['brand']); ?></option>
                                <?php endif; ?>
                                <?php foreach ($numbers as $n): ?>
                                    <option value="<?php echo e($n->phone_number_id); ?>" <?php echo (int) $n->is_default === 1 ? 'selected' : ''; ?>><?php echo e($n->display_phone_number . ' — ' . $n->verified_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="wapi-field">
                        <label><?php echo _l('wapi_template'); ?></label>
                        <select name="template" class="wapi-template-select" onchange="wapiCampaignTemplateChanged(this)" required></select>
                        <div class="wapi-template-preview wapi-muted" id="wapi-campaign-template-preview"></div>
                    </div>
                    <div id="wapi-campaign-params"></div>
                    <div class="wapi-field" id="wapi-campaign-header-media" style="display:none">
                        <label><?php echo _l('wapi_header_media_url'); ?></label>
                        <input type="text" name="header_media_url" placeholder="https://…">
                    </div>
                    <div class="wapi-field">
                        <label><?php echo _l('wapi_campaign_source'); ?></label>
                        <select name="source" onchange="wapiCampaignSourceChanged(this)">
                            <option value="manual"><?php echo _l('wapi_source_manual'); ?></option>
                            <option value="leads"><?php echo _l('wapi_source_leads'); ?></option>
                            <option value="patients"><?php echo _l('wapi_source_patients'); ?></option>
                            <option value="contacts"><?php echo _l('wapi_source_contacts'); ?></option>
                        </select>
                    </div>
                    <div class="wapi-field" id="wapi-manual-numbers-field">
                        <label><?php echo _l('wapi_campaign_recipients'); ?></label>
                        <textarea name="manual_numbers" rows="5" placeholder="<?php echo _l('wapi_manual_numbers_hint'); ?>"></textarea>
                    </div>
                    <div class="wapi-preview-line">
                        <button type="button" class="wapi-btn wapi-btn-light wapi-btn-sm" onclick="wapiPreviewRecipients()"><i class="fa fa-users"></i> <?php echo _l('wapi_preview_count'); ?></button>
                        <span id="wapi-preview-result" class="wapi-muted"></span>
                    </div>
                    <div class="wapi-grid-2">
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_schedule_optional'); ?></label>
                            <input type="datetime-local" name="scheduled_at">
                        </div>
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_batch_size'); ?></label>
                            <input type="number" name="batch_size" value="20" min="5" max="200">
                        </div>
                    </div>
                    <div class="wapi-modal-foot">
                        <button type="button" class="wapi-btn wapi-btn-light" onclick="wapiCloseModal('wapi-campaign-modal')"><?php echo _l('cancel'); ?></button>
                        <button type="submit" class="wapi-btn wapi-btn-primary"><i class="fa fa-bullhorn"></i> <?php echo _l('submit'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; // campaign modal ?>

        <!-- ═══════════ Template modal ═══════════ -->
        <?php if ($wapi_tab['templates']): ?>
        <?php
        /**
         * Composer languages — Meta's full list is enormous, so this is the set
         * that actually gets used here, English and the Indian languages first.
         */
        $wapi_tpl_languages = [
            'en'    => 'English',
            'en_US' => 'English (US)',
            'en_GB' => 'English (UK)',
            'hi'    => 'Hindi',
            'mr'    => 'Marathi',
            'gu'    => 'Gujarati',
            'bn'    => 'Bengali',
            'ta'    => 'Tamil',
            'te'    => 'Telugu',
            'kn'    => 'Kannada',
            'ml'    => 'Malayalam',
            'pa'    => 'Punjabi',
            'ur'    => 'Urdu',
            'ar'    => 'Arabic',
            'es'    => 'Spanish',
            'fr'    => 'French',
            'pt_BR' => 'Portuguese (BR)',
            'id'    => 'Indonesian',
            'ru'    => 'Russian',
        ];
        // The preview chrome shows the number's own approved display name, so
        // what the composer draws is what the recipient really sees.
        $wapi_preview_name = '';
        foreach ($numbers as $wapi_n) {
            if (!empty($wapi_n->verified_name)) {
                $wapi_preview_name = $wapi_n->verified_name;
                break;
            }
        }
        // On the shared number the recipient sees the PROVIDER's approved
        // display name, never this account's company name.
        if ($wapi_preview_name === '' && $wapi_shared_on && !empty($shared['number'])) {
            $wapi_preview_name = (string) $shared['number']->verified_name;
        }
        if ($wapi_preview_name === '') {
            $wapi_preview_name = get_option('companyname') ?: _l('wapi_whatsapp');
        }
        ?>
        <div class="wapi-modal" id="wapi-template-modal">
            <div class="wapi-modal-box wapi-modal-xl">
                <div class="wapi-modal-head">
                    <h3 id="wapi-template-modal-title"><?php echo _l('wapi_new_template'); ?></h3>
                    <button class="wapi-modal-close" onclick="wapiCloseModal('wapi-template-modal')">&times;</button>
                </div>
                <form id="wapi-template-form" onsubmit="return wapiSaveTemplate(event)">
                    <input type="hidden" name="id" value="">
                    <div class="wapi-tplcompose">

                        <!-- ── Left: the form ── -->
                        <div class="wapi-tplcompose-main">
                            <div id="wapi-template-edit-note"></div>
                            <div class="wapi-grid-3">
                                <div class="wapi-field">
                                    <label><?php echo _l('wapi_template_name'); ?></label>
                                    <input type="text" name="name" placeholder="appointment_reminder" required autocomplete="off">
                                    <small class="wapi-field-hint" id="wapi-template-name-hint"><?php echo _l('wapi_template_name_hint'); ?></small>
                                </div>
                                <div class="wapi-field">
                                    <label><?php echo _l('wapi_template_language'); ?></label>
                                    <select name="language">
                                        <?php foreach ($wapi_tpl_languages as $wapi_code => $wapi_label): ?>
                                            <option value="<?php echo e($wapi_code); ?>"><?php echo e($wapi_label); ?> (<?php echo e($wapi_code); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="wapi-field">
                                    <label><?php echo _l('wapi_template_category'); ?></label>
                                    <select name="category">
                                        <option value="MARKETING">Marketing</option>
                                        <option value="UTILITY">Utility</option>
                                    </select>
                                    <small class="wapi-field-hint" id="wapi-template-category-hint"></small>
                                </div>
                            </div>

                            <div class="wapi-field">
                                <label><?php echo _l('wapi_template_header'); ?>
                                    <span class="wapi-count" id="wapi-count-header">0/60</span>
                                </label>
                                <input type="text" name="header_text" maxlength="60" placeholder="<?php echo _l('wapi_template_header_ph'); ?>" autocomplete="off">
                                <small class="wapi-muted" id="wapi-template-header-note" style="display:none"><?php echo _l('wapi_template_media_header_kept'); ?></small>
                            </div>

                            <div class="wapi-field">
                                <label><?php echo _l('wapi_template_body'); ?>
                                    <span class="wapi-count" id="wapi-count-body">0/1024</span>
                                </label>
                                <div class="wapi-tplbar">
                                    <button type="button" class="wapi-tplbar-btn" data-wrap="*" title="<?php echo _l('wapi_format_bold'); ?>"><i class="fa fa-bold"></i></button>
                                    <button type="button" class="wapi-tplbar-btn" data-wrap="_" title="<?php echo _l('wapi_format_italic'); ?>"><i class="fa fa-italic"></i></button>
                                    <button type="button" class="wapi-tplbar-btn" data-wrap="~" title="<?php echo _l('wapi_format_strike'); ?>"><i class="fa fa-strikethrough"></i></button>
                                    <button type="button" class="wapi-tplbar-btn" data-wrap="```" title="<?php echo _l('wapi_format_mono'); ?>"><i class="fa fa-code"></i></button>
                                    <span class="wapi-tplbar-sep"></span>
                                    <button type="button" class="wapi-tplbar-btn wapi-tplbar-var" id="wapi-template-add-var"><i class="fa fa-plus"></i> <?php echo _l('wapi_add_variable'); ?></button>
                                </div>
                                <textarea name="body_text" rows="7" maxlength="1024" required placeholder="<?php echo _l('wapi_template_body_hint'); ?>"></textarea>
                            </div>

                            <div class="wapi-field">
                                <label><?php echo _l('wapi_template_footer'); ?>
                                    <span class="wapi-count" id="wapi-count-footer">0/60</span>
                                </label>
                                <input type="text" name="footer_text" maxlength="60" placeholder="<?php echo _l('wapi_template_footer_ph'); ?>" autocomplete="off">
                            </div>

                            <!-- Sample values Meta reviews the template against -->
                            <div id="wapi-template-samples" class="wapi-tplsamples" style="display:none">
                                <div class="wapi-tplsamples-head">
                                    <strong><i class="fa fa-flask"></i> <?php echo _l('wapi_template_samples'); ?></strong>
                                    <small class="wapi-muted"><?php echo _l('wapi_template_samples_hint'); ?></small>
                                </div>
                                <div id="wapi-template-samples-rows"></div>
                            </div>
                        </div>

                        <!-- ── Right: live preview + pre-flight checks ── -->
                        <aside class="wapi-tplcompose-side">
                            <div class="wapi-tplphone">
                                <div class="wapi-tplphone-top">
                                    <span class="wapi-tplphone-avatar"><i class="fa fa-shop"></i></span>
                                    <div class="wapi-tplphone-who">
                                        <strong><?php echo e($wapi_preview_name); ?></strong>
                                        <small><?php echo _l('wapi_preview_business'); ?></small>
                                    </div>
                                    <i class="fa fa-ellipsis-vertical wapi-tplphone-dots"></i>
                                </div>
                                <div class="wapi-tplphone-screen">
                                    <div id="wapi-template-live-preview"></div>
                                </div>
                                <div class="wapi-tplphone-foot">
                                    <label class="wapi-tplphone-toggle">
                                        <input type="checkbox" id="wapi-template-preview-samples" checked>
                                        <?php echo _l('wapi_preview_with_samples'); ?>
                                    </label>
                                </div>
                            </div>
                            <div class="wapi-tpllint" id="wapi-template-lint"></div>
                        </aside>
                    </div>

                    <div class="wapi-modal-foot">
                        <span class="wapi-muted wapi-tplfoot-note" id="wapi-template-foot-note"></span>
                        <button type="button" class="wapi-btn wapi-btn-light" onclick="wapiCloseModal('wapi-template-modal')"><?php echo _l('cancel'); ?></button>
                        <button type="submit" class="wapi-btn wapi-btn-primary" id="wapi-template-submit"><i class="fa fa-cloud-arrow-up"></i> <span id="wapi-template-submit-label"><?php echo _l('submit'); ?></span></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ═══════════ Template details drawer ═══════════ -->
        <div class="wapi-modal" id="wapi-template-details-modal">
            <div class="wapi-modal-box wapi-modal-lg">
                <div class="wapi-modal-head">
                    <h3 id="wapi-template-details-title"></h3>
                    <button class="wapi-modal-close" onclick="wapiCloseModal('wapi-template-details-modal')">&times;</button>
                </div>
                <div id="wapi-template-details-body"></div>
                <div class="wapi-modal-foot">
                    <button type="button" class="wapi-btn wapi-btn-light" onclick="wapiCloseModal('wapi-template-details-modal')"><?php echo _l('close'); ?></button>
                    <button type="button" class="wapi-btn wapi-btn-primary" id="wapi-template-details-edit" onclick="wapiEditTemplateFromDetails()"><i class="fa fa-pen"></i> <span id="wapi-template-details-edit-label"><?php echo _l('wapi_edit_template'); ?></span></button>
                </div>
            </div>
        </div>

        <?php endif; // template modal ?>

        <!-- ═══════════ Rule modal ═══════════ -->
        <?php if ($wapi_tab['bot']): ?>
        <div class="wapi-modal" id="wapi-rule-modal">
            <div class="wapi-modal-box">
                <div class="wapi-modal-head">
                    <h3 id="wapi-rule-modal-title"><?php echo _l('wapi_new_rule'); ?></h3>
                    <button class="wapi-modal-close" onclick="wapiCloseModal('wapi-rule-modal')">&times;</button>
                </div>
                <form id="wapi-rule-form" onsubmit="return wapiSaveRule(event)">
                    <input type="hidden" name="id" value="">
                    <div class="wapi-grid-2">
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_rule_name'); ?></label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_rule_priority'); ?></label>
                            <input type="number" name="priority" value="0">
                        </div>
                    </div>
                    <div class="wapi-grid-2">
                        <div class="wapi-field">
                            <label><?php echo _l('wapi_rule_trigger'); ?></label>
                            <select name="trigger_type" onchange="wapiRuleTriggerChanged(this)">
                                <option value="welcome"><?php echo _l('wapi_trigger_welcome'); ?></option>
                                <option value="keyword" selected><?php echo _l('wapi_trigger_keyword'); ?></option>
                                <option value="away"><?php echo _l('wapi_trigger_away'); ?></option>
                                <option value="default"><?php echo _l('wapi_trigger_default'); ?></option>
                            </select>
                        </div>
                        <div class="wapi-field" id="wapi-rule-match-field">
                            <label><?php echo _l('wapi_match_type'); ?></label>
                            <select name="match_type">
                                <option value="contains"><?php echo _l('wapi_match_contains'); ?></option>
                                <option value="exact"><?php echo _l('wapi_match_exact'); ?></option>
                                <option value="starts_with"><?php echo _l('wapi_match_starts_with'); ?></option>
                                <option value="any"><?php echo _l('wapi_match_any'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="wapi-field" id="wapi-rule-keywords-field">
                        <label><?php echo _l('wapi_keywords'); ?></label>
                        <input type="text" name="keywords" placeholder="price, cost, fees">
                    </div>
                    <div class="wapi-field">
                        <label><?php echo _l('wapi_response'); ?></label>
                        <textarea name="response_text" rows="4" required></textarea>
                    </div>
                    <span class="wapi-switch-label">
                        <label class="wapi-switch"><input type="checkbox" name="enabled" value="1" checked><span class="wapi-slider"></span></label>
                        <?php echo _l('wapi_rule_enabled'); ?>
                    </span>
                    <div class="wapi-modal-foot">
                        <button type="button" class="wapi-btn wapi-btn-light" onclick="wapiCloseModal('wapi-rule-modal')"><?php echo _l('cancel'); ?></button>
                        <button type="submit" class="wapi-btn wapi-btn-primary"><i class="fa fa-floppy-disk"></i> <?php echo _l('submit'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <?php endif; // rule modal ?>

        <!-- ═══════════ Register number modal ═══════════ -->
        <?php if ($wapi_tab['settings']): ?>
        <div class="wapi-modal" id="wapi-register-modal">
            <div class="wapi-modal-box">
                <div class="wapi-modal-head">
                    <h3><i class="fa fa-key"></i> <?php echo _l('wapi_register_number'); ?></h3>
                    <button class="wapi-modal-close" onclick="wapiCloseModal('wapi-register-modal')">&times;</button>
                </div>
                <form id="wapi-register-form" onsubmit="return wapiRegisterNumber(event)">
                    <input type="hidden" name="phone_number_id" value="">
                    <p class="wapi-modal-lead">
                        <?php echo _l('wapi_register_lead'); ?>
                        <strong id="wapi-register-phone"></strong>
                    </p>
                    <div class="wapi-field">
                        <label><?php echo _l('wapi_register_pin'); ?></label>
                        <input type="text" name="pin" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                               placeholder="000000" autocomplete="off" required class="wapi-pin-input">
                        <small class="wapi-muted"><?php echo _l('wapi_register_pin_hint'); ?></small>
                    </div>
                    <div class="wapi-alert wapi-alert-info">
                        <i class="fa fa-circle-info"></i>
                        <div><?php echo _l('wapi_register_note'); ?></div>
                    </div>
                    <div class="wapi-modal-foot">
                        <button type="button" class="wapi-btn wapi-btn-light" onclick="wapiCloseModal('wapi-register-modal')"><?php echo _l('cancel'); ?></button>
                        <button type="submit" class="wapi-btn wapi-btn-primary"><i class="fa fa-key"></i> <?php echo _l('wapi_register_number'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <?php endif; // register modal ?>

        <!-- ═══════════ Shared-number grant modal (master) ═══════════ -->
        <?php if ($is_master): ?>
        <div class="wapi-modal" id="wapi-shared-modal">
            <div class="wapi-modal-box wapi-modal-lg">
                <div class="wapi-modal-head">
                    <h3><i class="fa fa-handshake-angle"></i> <?php echo _l('wapi_shared_grant_title'); ?></h3>
                    <button class="wapi-modal-close" onclick="wapiCloseModal('wapi-shared-modal')">&times;</button>
                </div>
                <div id="wapi-shared-modal-body"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══════════ Number details modal ═══════════ -->
        <?php if ($wapi_tab['overview']): ?>
        <div class="wapi-modal" id="wapi-number-modal">
            <div class="wapi-modal-box">
                <div class="wapi-modal-head">
                    <h3><i class="fa fa-mobile-screen"></i> <?php echo _l('wapi_number_details'); ?></h3>
                    <button class="wapi-modal-close" onclick="wapiCloseModal('wapi-number-modal')">&times;</button>
                </div>
                <div id="wapi-number-modal-body"></div>
            </div>
        </div>

        <?php endif; // number details modal ?>

        <!-- ═══════════ Campaign details modal ═══════════ -->
        <?php if ($wapi_tab['bulk']): ?>
        <div class="wapi-modal" id="wapi-campaign-details-modal">
            <div class="wapi-modal-box wapi-modal-lg">
                <div class="wapi-modal-head">
                    <h3 id="wapi-campaign-details-title"></h3>
                    <button class="wapi-modal-close" onclick="wapiCloseModal('wapi-campaign-details-modal')">&times;</button>
                </div>
                <div id="wapi-campaign-details-body"></div>
            </div>
        </div>
        <?php endif; // campaign details modal ?>

    </div>
</div>

<script>
/* Module config for assets/js/whatsapp.js (vanilla — jQuery loads in the footer). */
var WAPI = {
    urls: {
        base:               '<?php echo admin_url('whatsapp/'); ?>',
        send_single:        '<?php echo admin_url('whatsapp/send_single'); ?>',
        send_chat:          '<?php echo admin_url('whatsapp/send_chat'); ?>',
        send_chat_media:    '<?php echo admin_url('whatsapp/send_chat_media'); ?>',
        chat_threads:       '<?php echo admin_url('whatsapp/get_chat_threads'); ?>',
        chat_messages:      '<?php echo admin_url('whatsapp/get_chat_messages'); ?>',
        media:              '<?php echo admin_url('whatsapp/media/'); ?>',
        sync_templates:     '<?php echo admin_url('whatsapp/sync_templates'); ?>',
        create_template:    '<?php echo admin_url('whatsapp/create_template'); ?>',
        update_template:    '<?php echo admin_url('whatsapp/update_template'); ?>',
        template_details:   '<?php echo admin_url('whatsapp/template_details/'); ?>',
        delete_template:    '<?php echo admin_url('whatsapp/delete_template'); ?>',
        create_campaign:    '<?php echo admin_url('whatsapp/create_campaign'); ?>',
        campaign_action:    '<?php echo admin_url('whatsapp/campaign_action/'); ?>',
        campaign_status:    '<?php echo admin_url('whatsapp/campaign_status/'); ?>',
        campaign_details:   '<?php echo admin_url('whatsapp/campaign_details/'); ?>',
        delete_campaign:    '<?php echo admin_url('whatsapp/delete_campaign/'); ?>',
        recipients_preview: '<?php echo admin_url('whatsapp/recipients_preview'); ?>',
        run_queue:          '<?php echo admin_url('whatsapp/run_queue'); ?>',
        get_rule:           '<?php echo admin_url('whatsapp/get_rule/'); ?>',
        save_rule:          '<?php echo admin_url('whatsapp/save_rule'); ?>',
        toggle_rule:        '<?php echo admin_url('whatsapp/toggle_rule/'); ?>',
        delete_rule:        '<?php echo admin_url('whatsapp/delete_rule/'); ?>',
        save_bot_settings:  '<?php echo admin_url('whatsapp/save_bot_settings'); ?>',
        toggle_optout:      '<?php echo admin_url('whatsapp/toggle_optout'); ?>',
        save_settings:      '<?php echo admin_url('whatsapp/save_settings'); ?>',
        save_notify:        '<?php echo admin_url('whatsapp/save_notify_settings'); ?>',
        save_credentials:   '<?php echo admin_url('whatsapp/save_credentials'); ?>',
        resync_app:         '<?php echo admin_url('whatsapp/resync_app'); ?>',
        disconnect:         '<?php echo admin_url('whatsapp/disconnect'); ?>',
        master_disconnect:  '<?php echo admin_url('whatsapp/master_disconnect/'); ?>',
        shared_settings:    '<?php echo admin_url('whatsapp/save_shared_settings'); ?>',
        shared_console:     '<?php echo admin_url('whatsapp/shared_console'); ?>',
        shared_modal:       '<?php echo admin_url('whatsapp/shared_grant_modal/'); ?>',
        save_shared_grant:  '<?php echo admin_url('whatsapp/save_shared_grant'); ?>',
        toggle_shared:      '<?php echo admin_url('whatsapp/toggle_shared_grant/'); ?>',
        delete_shared:      '<?php echo admin_url('whatsapp/delete_shared_grant/'); ?>',
        shared_sync:        '<?php echo admin_url('whatsapp/shared_sync'); ?>',
        refresh_numbers:    '<?php echo admin_url('whatsapp/refresh_numbers'); ?>',
        set_default_number: '<?php echo admin_url('whatsapp/set_default_number'); ?>',
        check_numbers:      '<?php echo admin_url('whatsapp/check_numbers'); ?>',
        register_number:    '<?php echo admin_url('whatsapp/register_number'); ?>',
        deregister_number:  '<?php echo admin_url('whatsapp/deregister_number'); ?>',
        number_details:     '<?php echo admin_url('whatsapp/number_details/'); ?>',
        diagnostics:        '<?php echo admin_url('whatsapp/diagnostics'); ?>',
        activity:           '<?php echo admin_url('whatsapp/activity'); ?>',
        connect:            '<?php echo admin_url('whatsapp/connect'); ?>',
        webhook_check:      '<?php echo admin_url('whatsapp/webhook_check'); ?>',
        webhook_fix:        '<?php echo admin_url('whatsapp/webhook_fix'); ?>',
        profile:            '<?php echo admin_url('whatsapp/profile/'); ?>',
        save_profile:       '<?php echo admin_url('whatsapp/save_profile'); ?>',
        request_name:       '<?php echo admin_url('whatsapp/request_display_name'); ?>',
        save_billing:       '<?php echo admin_url('whatsapp/save_billing'); ?>',
        credit_lines:       '<?php echo admin_url('whatsapp/credit_lines'); ?>',
        share_credit:       '<?php echo admin_url('whatsapp/share_credit/'); ?>',
        refresh_usage:      '<?php echo admin_url('whatsapp/refresh_usage'); ?>'
    },
    hasConnection: <?php echo $connection ? 'true' : 'false'; ?>,
    healthStale:   <?php echo !empty($health_stale) ? 'true' : 'false'; ?>,
    series:        <?php echo json_encode($series, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    csrf: {
        name: '<?php echo $this->security->get_csrf_token_name(); ?>',
        hash: '<?php echo $this->security->get_csrf_hash(); ?>'
    },
    templates: <?php
        $tpl_data = [];
        foreach ($templates as $t) {
            if (strtoupper((string) $t->status) !== 'APPROVED') {
                continue;
            }
            $tpl_data[] = [
                'name'      => $t->name,
                'language'  => $t->language,
                'vars'      => (int) $t->variables_count,
                'media'     => (int) $t->has_media_header,
                'body'      => (string) $t->body_text,
            ];
        }
        echo json_encode($tpl_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>,
    /* Every name+language pair that already exists — the composer refuses to
       submit a duplicate, which Meta rejects anyway. */
    templateKeys: <?php
        $tpl_keys = [];
        foreach ($templates as $t) {
            $tpl_keys[] = strtolower($t->name . '|' . $t->language);
        }
        echo json_encode(array_values(array_unique($tpl_keys)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>,
    canSend: <?php echo $wapi_tab['send'] ? 'true' : 'false'; ?>,
    /* Tab-wise capabilities — the JS only auto-loads data for permitted tabs. */
    can: <?php echo json_encode(array_map('boolval', $wapi_tab)); ?>,
    firstTab: '<?php echo e($wapi_first_tab); ?>'
};
</script>

<?php init_tail(); ?>
