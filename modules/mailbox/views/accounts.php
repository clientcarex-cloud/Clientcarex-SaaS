<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="mbx-wrap" id="mbx-accounts">

            <div class="mbx-header">
                <h4 class="mbx-title"><i class="fa-solid fa-envelope-open-text"></i> <?= _l('mailbox_accounts'); ?></h4>
                <div class="mbx-right">
                    <a href="<?= admin_url('mailbox'); ?>" class="mbx-btn mbx-btn-light">
                        <i class="fa fa-inbox"></i> <?= _l('mailbox_open_webmail'); ?>
                    </a>
                    <button type="button" class="mbx-btn mbx-btn-primary" id="mbx-wizard-open">
                        <i class="fa fa-plug"></i> <?= _l('mailbox_connect_account'); ?>
                    </button>
                </div>
            </div>

            <?php if (!$imap_ext) : ?>
                <div class="mbx-banner mbx-banner-warning">
                    <i class="fa fa-triangle-exclamation"></i> <?= _l('mailbox_imap_ext_missing'); ?>
                </div>
            <?php endif; ?>

            <!-- ── How it works ── -->
            <div class="mbx-card mbx-howto">
                <h4 class="mbx-card-title"><i class="fa fa-circle-info"></i> <?= _l('mailbox_how_it_works'); ?></h4>
                <ol class="mbx-howto-steps">
                    <li><span class="mbx-howto-num">1</span> <?= _l('mailbox_how_1'); ?></li>
                    <li><span class="mbx-howto-num">2</span> <?= _l('mailbox_how_2'); ?></li>
                    <li><span class="mbx-howto-num">3</span> <?= _l('mailbox_how_3'); ?></li>
                </ol>
            </div>

            <!-- ── Account cards ── -->
            <div class="mbx-account-grid" id="mbx-account-grid">
                <?php foreach ($accounts as $a) : ?>
                    <div class="mbx-card mbx-account-card <?= $a->active ? '' : 'mbx-account-off'; ?>" data-id="<?= $a->id; ?>">
                        <div class="mbx-account-card-head">
                            <div class="mbx-account-avatar"><?= strtoupper(mb_substr($a->name, 0, 1)); ?></div>
                            <div class="mbx-account-id">
                                <strong><?= html_escape($a->name); ?></strong>
                                <span class="mbx-muted"><?= html_escape($a->email); ?></span>
                            </div>
                            <span class="mbx-chip <?= $a->active ? 'mbx-chip-success' : 'mbx-chip-muted'; ?>">
                                <?= $a->active ? _l('mailbox_active') : _l('mailbox_inactive'); ?>
                            </span>
                        </div>

                        <div class="mbx-account-stats">
                            <span class="mbx-chip <?= $a->smtp_ok ? 'mbx-chip-success' : 'mbx-chip-warning'; ?>" title="SMTP">
                                <i class="fa fa-paper-plane"></i> SMTP <?= $a->smtp_ok ? '✓' : '?'; ?>
                            </span>
                            <span class="mbx-chip <?= $a->imap_ok ? 'mbx-chip-success' : 'mbx-chip-warning'; ?>" title="IMAP">
                                <i class="fa fa-inbox"></i> IMAP <?= $a->imap_ok ? '✓' : '?'; ?>
                            </span>
                            <span class="mbx-chip mbx-chip-muted">
                                <i class="fa fa-envelope"></i> <?= (int) $a->message_count; ?> <?= _l('mailbox_messages_count'); ?>
                            </span>
                            <?php if ((int) $a->unread_count > 0) : ?>
                                <span class="mbx-chip mbx-chip-primary"><?= (int) $a->unread_count; ?> <?= _l('mailbox_unread_count'); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="mbx-account-users">
                            <span class="mbx-muted mbx-small"><?= _l('mailbox_assigned_users'); ?>:</span>
                            <?php if (count($a->assigned)) : ?>
                                <?php foreach ($a->assigned as $u) : ?>
                                    <span class="mbx-user-pill" title="<?= html_escape($u->email); ?>"><?= html_escape($u->full_name); ?></span>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <span class="mbx-muted mbx-small"><i><?= _l('mailbox_nobody_assigned'); ?></i></span>
                            <?php endif; ?>
                        </div>

                        <div class="mbx-account-meta mbx-muted mbx-small">
                            <?= _l('mailbox_last_sync'); ?>:
                            <?= $a->last_sync_at ? html_escape($a->last_sync_at) : _l('mailbox_never_synced'); ?>
                            <?php if ($a->last_sync_error) : ?>
                                <span class="mbx-chip mbx-chip-danger" title="<?= html_escape($a->last_sync_error); ?>">
                                    <i class="fa fa-triangle-exclamation"></i> <?= _l('mailbox_sync_error'); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="mbx-account-actions">
                            <button class="mbx-btn mbx-btn-light mbx-btn-sm" data-act="edit"><i class="fa fa-pen"></i> <?= _l('mailbox_edit_account'); ?></button>
                            <button class="mbx-btn mbx-btn-light mbx-btn-sm" data-act="sync"><i class="fa fa-rotate"></i> <?= _l('mailbox_sync_now'); ?></button>
                            <button class="mbx-btn mbx-btn-danger-light mbx-btn-sm mbx-right" data-act="delete"><i class="fa fa-trash-can"></i></button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (!count($accounts)) : ?>
                    <div class="mbx-empty-page">
                        <div class="mbx-empty-icon"><i class="fa fa-plug"></i></div>
                        <h3><?= _l('mailbox_no_accounts_admin_sub'); ?></h3>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── Connect wizard ── -->
            <div class="mbx-modal-backdrop" id="mbx-wizard" style="display:none;">
                <div class="mbx-modal mbx-wizard">
                    <div class="mbx-modal-head">
                        <span id="mbx-wizard-title"><?= _l('mailbox_connect_account'); ?></span>
                        <button class="mbx-icon-btn" id="mbx-wizard-close"><i class="fa fa-xmark"></i></button>
                    </div>

                    <div class="mbx-wizard-steps">
                        <span class="mbx-wstep active" data-step="1"><i>1</i> <?= _l('mailbox_wizard_step_provider'); ?></span>
                        <span class="mbx-wstep" data-step="2"><i>2</i> <?= _l('mailbox_wizard_step_identity'); ?></span>
                        <span class="mbx-wstep" data-step="3"><i>3</i> <?= _l('mailbox_wizard_step_servers'); ?></span>
                        <span class="mbx-wstep" data-step="4"><i>4</i> <?= _l('mailbox_wizard_step_test'); ?></span>
                        <span class="mbx-wstep" data-step="5"><i>5</i> <?= _l('mailbox_wizard_step_assign'); ?></span>
                    </div>

                    <div class="mbx-modal-body">
                        <form id="mbx-wizard-form" onsubmit="return false;">
                        <input type="hidden" name="id" value="0">
                        <input type="hidden" name="provider" value="">

                        <!-- Step 1: provider -->
                        <div class="mbx-wpage" data-page="1">
                            <p class="mbx-muted"><?= _l('mailbox_wizard_pick_provider'); ?></p>
                            <div class="mbx-provider-grid">
                                <?php foreach ($presets as $key => $preset) : ?>
                                    <button type="button" class="mbx-provider" data-provider="<?= $key; ?>">
                                        <i class="<?= $preset['icon']; ?>"></i>
                                        <span><?= html_escape($preset['label']); ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div class="mbx-guide" id="mbx-guide" style="display:none;">
                                <h5><i class="fa fa-lightbulb"></i> <?= _l('mailbox_wizard_guide_title'); ?></h5>
                                <ol id="mbx-guide-steps"></ol>
                                <a href="#" target="_blank" rel="noopener" class="mbx-btn mbx-btn-light mbx-btn-sm" id="mbx-guide-apppw" style="display:none;">
                                    <i class="fa fa-key"></i> <?= _l('mailbox_wizard_open_app_pw'); ?>
                                </a>
                            </div>
                        </div>

                        <!-- Step 2: identity -->
                        <div class="mbx-wpage" data-page="2" style="display:none;">
                            <div class="mbx-form-grid">
                                <div class="mbx-form-field">
                                    <label><?= _l('mailbox_wizard_email'); ?> *</label>
                                    <input type="email" name="email" class="mbx-input" placeholder="support@yourcompany.com">
                                </div>
                                <div class="mbx-form-field">
                                    <label><?= _l('mailbox_wizard_name'); ?></label>
                                    <input type="text" name="name" class="mbx-input" placeholder="Support">
                                    <small class="mbx-muted"><?= _l('mailbox_wizard_name_hint'); ?></small>
                                </div>
                                <div class="mbx-form-field">
                                    <label><?= _l('mailbox_wizard_from_name'); ?></label>
                                    <input type="text" name="from_name" class="mbx-input">
                                    <small class="mbx-muted"><?= _l('mailbox_wizard_from_name_hint'); ?></small>
                                </div>
                                <div class="mbx-form-field mbx-form-field-full">
                                    <label><?= _l('mailbox_wizard_signature'); ?></label>
                                    <textarea name="signature" class="mbx-input" rows="3" placeholder="--&#10;Best regards,&#10;Support Team"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: servers -->
                        <div class="mbx-wpage" data-page="3" style="display:none;">
                            <div class="mbx-server-cols">
                                <div class="mbx-server-col">
                                    <h5><i class="fa fa-paper-plane"></i> <?= _l('mailbox_smtp_settings'); ?></h5>
                                    <div class="mbx-form-field">
                                        <label><?= _l('mailbox_host'); ?></label>
                                        <input type="text" name="smtp_host" class="mbx-input">
                                    </div>
                                    <div class="mbx-form-2col">
                                        <div class="mbx-form-field">
                                            <label><?= _l('mailbox_port'); ?></label>
                                            <input type="number" name="smtp_port" class="mbx-input" value="465">
                                        </div>
                                        <div class="mbx-form-field">
                                            <label><?= _l('mailbox_encryption'); ?></label>
                                            <select name="smtp_encryption" class="mbx-input">
                                                <option value="ssl">SSL</option>
                                                <option value="tls">TLS (STARTTLS)</option>
                                                <option value="none">None</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mbx-form-field">
                                        <label><?= _l('mailbox_username'); ?></label>
                                        <input type="text" name="smtp_username" class="mbx-input" placeholder="<?= _l('mailbox_username_hint'); ?>">
                                    </div>
                                    <div class="mbx-form-field">
                                        <label><?= _l('mailbox_password'); ?></label>
                                        <div class="mbx-pw-wrap">
                                            <input type="password" name="smtp_password" class="mbx-input" autocomplete="new-password">
                                            <button type="button" class="mbx-pw-toggle" tabindex="-1"><i class="fa fa-eye"></i></button>
                                        </div>
                                        <small class="mbx-muted mbx-pw-keep" style="display:none;"><?= _l('mailbox_password_keep'); ?></small>
                                    </div>
                                </div>
                                <div class="mbx-server-col">
                                    <h5><i class="fa fa-inbox"></i> <?= _l('mailbox_imap_settings'); ?></h5>
                                    <div class="mbx-form-field">
                                        <label><?= _l('mailbox_host'); ?></label>
                                        <input type="text" name="imap_host" class="mbx-input">
                                    </div>
                                    <div class="mbx-form-2col">
                                        <div class="mbx-form-field">
                                            <label><?= _l('mailbox_port'); ?></label>
                                            <input type="number" name="imap_port" class="mbx-input" value="993">
                                        </div>
                                        <div class="mbx-form-field">
                                            <label><?= _l('mailbox_encryption'); ?></label>
                                            <select name="imap_encryption" class="mbx-input">
                                                <option value="ssl">SSL</option>
                                                <option value="tls">TLS</option>
                                                <option value="starttls">STARTTLS</option>
                                                <option value="none">None</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mbx-form-field">
                                        <label><?= _l('mailbox_username'); ?></label>
                                        <input type="text" name="imap_username" class="mbx-input" placeholder="<?= _l('mailbox_username_hint'); ?>">
                                    </div>
                                    <div class="mbx-form-field">
                                        <label><?= _l('mailbox_password'); ?></label>
                                        <div class="mbx-pw-wrap">
                                            <input type="password" name="imap_password" class="mbx-input" autocomplete="new-password" placeholder="<?= _l('mailbox_imap_same_password'); ?>">
                                            <button type="button" class="mbx-pw-toggle" tabindex="-1"><i class="fa fa-eye"></i></button>
                                        </div>
                                    </div>
                                    <div class="mbx-form-2col">
                                        <div class="mbx-form-field">
                                            <label><?= _l('mailbox_imap_folder'); ?></label>
                                            <input type="text" name="imap_folder" class="mbx-input" value="INBOX">
                                        </div>
                                        <div class="mbx-form-field">
                                            <label><?= _l('mailbox_imap_sent_folder'); ?></label>
                                            <input type="text" name="imap_sent_folder" class="mbx-input" list="mbx-folder-options">
                                            <datalist id="mbx-folder-options"></datalist>
                                            <small class="mbx-muted"><?= _l('mailbox_imap_sent_folder_hint'); ?></small>
                                        </div>
                                    </div>
                                    <label class="mbx-check">
                                        <input type="checkbox" name="imap_validate_cert" value="1">
                                        <?= _l('mailbox_validate_cert'); ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: test -->
                        <div class="mbx-wpage" data-page="4" style="display:none;">
                            <p class="mbx-muted"><?= _l('mailbox_test_hint'); ?></p>
                            <div class="mbx-test-grid">
                                <div class="mbx-test-card">
                                    <button type="button" class="mbx-btn mbx-btn-light" id="mbx-test-smtp">
                                        <i class="fa fa-paper-plane"></i> <?= _l('mailbox_test_smtp'); ?>
                                    </button>
                                    <div class="mbx-test-result" id="mbx-test-smtp-result"></div>
                                </div>
                                <div class="mbx-test-card">
                                    <button type="button" class="mbx-btn mbx-btn-light" id="mbx-test-imap">
                                        <i class="fa fa-inbox"></i> <?= _l('mailbox_test_imap'); ?>
                                    </button>
                                    <div class="mbx-test-result" id="mbx-test-imap-result"></div>
                                </div>
                            </div>
                            <div id="mbx-folder-list" class="mbx-folder-chips"></div>
                        </div>

                        <!-- Step 5: assign -->
                        <div class="mbx-wpage" data-page="5" style="display:none;">
                            <p class="mbx-muted"><?= _l('mailbox_assign_hint'); ?></p>
                            <input type="text" id="mbx-staff-search" class="mbx-input" placeholder="<?= _l('mailbox_search_staff'); ?>">
                            <div class="mbx-staff-list" id="mbx-staff-list">
                                <?php foreach ($staff as $member) : ?>
                                    <label class="mbx-staff-item" data-name="<?= html_escape(mb_strtolower($member->full_name . ' ' . $member->email)); ?>">
                                        <input type="checkbox" name="staff_ids[]" value="<?= $member->staffid; ?>">
                                        <span class="mbx-account-avatar mbx-avatar-sm"><?= strtoupper(mb_substr($member->full_name, 0, 1)); ?></span>
                                        <span>
                                            <strong><?= html_escape($member->full_name); ?></strong>
                                            <?php if ($member->admin) : ?><span class="mbx-chip mbx-chip-primary mbx-chip-xs">admin</span><?php endif; ?>
                                            <br><small class="mbx-muted"><?= html_escape($member->email); ?></small>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <label class="mbx-check mbx-active-toggle">
                                <input type="checkbox" name="active" value="1" checked>
                                <?= _l('mailbox_active'); ?>
                                <small class="mbx-muted"> — <?= _l('mailbox_account_active_hint'); ?></small>
                            </label>
                        </div>
                        </form>
                    </div>

                    <div class="mbx-modal-foot">
                        <button type="button" class="mbx-btn mbx-btn-light" id="mbx-wizard-back" style="display:none;">
                            <i class="fa fa-arrow-left"></i> <?= _l('mailbox_wizard_back'); ?>
                        </button>
                        <span class="mbx-composer-status" id="mbx-wizard-status"></span>
                        <button type="button" class="mbx-btn mbx-btn-primary mbx-right" id="mbx-wizard-next">
                            <?= _l('mailbox_wizard_next'); ?> <i class="fa fa-arrow-right"></i>
                        </button>
                        <button type="button" class="mbx-btn mbx-btn-primary mbx-right" id="mbx-wizard-finish" style="display:none;">
                            <i class="fa fa-check"></i> <?= _l('mailbox_wizard_finish'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="mbx-toast" id="mbx-toast" style="display:none;"></div>
        </div>
    </div>
</div>

<script>
window.MBX_ADMIN_BOOT = {
    urls: {
        save:       '<?= admin_url('mailbox/account_save'); ?>',
        get:        '<?= admin_url('mailbox/account_get'); ?>',
        del:        '<?= admin_url('mailbox/account_delete'); ?>',
        testSmtp:   '<?= admin_url('mailbox/test_smtp'); ?>',
        testImap:   '<?= admin_url('mailbox/test_imap'); ?>',
        sync:       '<?= admin_url('mailbox/sync'); ?>'
    },
    presets: <?= json_encode(array_map(function ($p) {
        return [
            'label'            => $p['label'],
            'smtp'             => $p['smtp'],
            'imap'             => $p['imap'],
            'sent_folder'      => $p['sent_folder'],
            'app_password_url' => $p['app_password_url'],
            'steps'            => $p['steps'],
        ];
    }, $presets)); ?>,
    i18n: {
        testing:       "<?= _l('mailbox_testing'); ?>",
        testOk:        "<?= _l('mailbox_test_ok'); ?>",
        testFailed:    "<?= _l('mailbox_test_failed'); ?>",
        testDebug:     "<?= _l('mailbox_test_debug'); ?>",
        foldersFound:  "<?= _l('mailbox_folders_found'); ?>",
        saved:         "<?= _l('mailbox_account_saved'); ?>",
        deleteConfirm: "<?= _l('mailbox_delete_account_confirm'); ?>",
        connectTitle:  "<?= _l('mailbox_connect_account'); ?>",
        editTitle:     "<?= _l('mailbox_edit_account'); ?>",
        syncing:       "<?= _l('mailbox_syncing'); ?>",
        synced:        "<?= _l('mailbox_synced'); ?>",
        loadError:     "<?= _l('mailbox_load_error'); ?>"
    }
};
</script>

<?php init_tail(); ?>
</body>
</html>
