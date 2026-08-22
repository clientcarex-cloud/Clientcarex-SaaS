<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Mailbox — governance settings.
 *
 * Superadmin only. Seven tabs: general behaviour, SLA, automation rules,
 * canned responses, labels, compliance and the per-account policies
 * (out-of-office, SLA override, default owner).
 */

/** One switch row. Guarded: a view can in theory be rendered twice. */
if (!function_exists('mbx_switch_row')) :
function mbx_switch_row($key, $label, $hint, array $options)
{
    $on = (string) ($options[$key] ?? '0') === '1';
    ?>
    <div class="mbx-setting">
        <div class="mbx-setting-main">
            <label for="<?= $key; ?>"><?= $label; ?></label>
            <?php if ($hint !== '') : ?><div class="mbx-setting-hint"><?= $hint; ?></div><?php endif; ?>
        </div>
        <label class="mbx-switch">
            <input type="checkbox" id="<?= $key; ?>" name="<?= $key; ?>" value="1" <?= $on ? 'checked' : ''; ?>>
            <span></span>
        </label>
    </div>
    <?php
}
endif;

/** One input row (text / number / email / textarea / select). */
if (!function_exists('mbx_input_row')) :
function mbx_input_row($key, $label, $hint, array $options, $type = 'text', array $choices = [])
{
    $value = (string) ($options[$key] ?? '');
    ?>
    <div class="mbx-setting">
        <div class="mbx-setting-main">
            <label for="<?= $key; ?>"><?= $label; ?></label>
            <?php if ($hint !== '') : ?><div class="mbx-setting-hint"><?= $hint; ?></div><?php endif; ?>
        </div>
        <div class="mbx-setting-control">
            <?php if ($type === 'textarea') : ?>
                <textarea id="<?= $key; ?>" name="<?= $key; ?>"><?= html_escape($value); ?></textarea>
            <?php elseif ($type === 'select') : ?>
                <select id="<?= $key; ?>" name="<?= $key; ?>">
                    <?php foreach ($choices as $choice_value => $choice_label) : ?>
                        <option value="<?= html_escape($choice_value); ?>" <?= $value === (string) $choice_value ? 'selected' : ''; ?>>
                            <?= html_escape($choice_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else : ?>
                <input type="<?= $type; ?>" id="<?= $key; ?>" name="<?= $key; ?>" value="<?= html_escape($value); ?>">
            <?php endif; ?>
        </div>
    </div>
    <?php
}
endif;

init_head();
?>
<div id="wrapper">
    <div class="content">
        <div class="mbx-wrap" id="mbx-settings">

            <div class="mbx-header">
                <div class="mbx-title"><i class="fa fa-gear"></i> <?= _l('mailbox_settings'); ?></div>
                <div class="mbx-right">
                    <a href="<?= admin_url('mailbox'); ?>" class="mbx-btn mbx-btn-light"><i class="fa fa-arrow-left"></i> <?= _l('mailbox_back_to_mailbox'); ?></a>
                    <button class="mbx-btn mbx-btn-primary" id="mbx-settings-save"><i class="fa fa-floppy-disk"></i> <?= _l('mailbox_save'); ?></button>
                </div>
            </div>

            <div class="mbx-tabs" id="mbx-tabs">
                <button class="mbx-tab active" data-tab="general"><i class="fa fa-sliders"></i> <?= _l('mailbox_general'); ?></button>
                <button class="mbx-tab" data-tab="sla"><i class="fa fa-stopwatch"></i> <?= _l('mailbox_sla'); ?></button>
                <button class="mbx-tab" data-tab="rules"><i class="fa fa-wand-magic-sparkles"></i> <?= _l('mailbox_rules'); ?></button>
                <button class="mbx-tab" data-tab="templates"><i class="fa fa-bolt"></i> <?= _l('mailbox_templates'); ?></button>
                <button class="mbx-tab" data-tab="labels"><i class="fa fa-tags"></i> <?= _l('mailbox_labels'); ?></button>
                <button class="mbx-tab" data-tab="compliance"><i class="fa fa-shield-halved"></i> <?= _l('mailbox_compliance'); ?></button>
                <button class="mbx-tab" data-tab="accounts"><i class="fa fa-envelope-circle-check"></i> <?= _l('mailbox_account_policies'); ?></button>
            </div>

            <form id="mbx-settings-form">

            <!-- ══════════════ General ══════════════ -->
            <div class="mbx-tabpage active" data-page="general">
                <div class="mbx-card">
                    <div class="mbx-card-title"><i class="fa fa-users"></i> <?= _l('mailbox_shared_inbox'); ?></div>
                    <?php
                    mbx_switch_row('mailbox_status_enabled', _l('mailbox_status_enable'), '', $options);
                    mbx_switch_row('mailbox_presence_enabled', _l('mailbox_presence_enable'), '', $options);
                    ?>
                </div>

                <div class="mbx-card">
                    <div class="mbx-card-title"><i class="fa fa-paper-plane"></i> <?= _l('mailbox_send'); ?></div>
                    <?php
                    mbx_input_row('mailbox_undo_send_seconds', _l('mailbox_undo_seconds'), _l('mailbox_undo_seconds_hint'), $options, 'number');
                    mbx_switch_row('mailbox_schedule_enabled', _l('mailbox_schedule_enable'), '', $options);
                    mbx_switch_row('mailbox_rules_enabled', _l('mailbox_rules_enable'), '', $options);
                    mbx_switch_row('mailbox_autoreply_enabled', _l('mailbox_autoreply_enable'), '', $options);
                    ?>
                </div>

                <div class="mbx-card">
                    <div class="mbx-card-title"><i class="fa fa-rotate"></i> <?= _l('mailbox_sync_settings'); ?></div>
                    <?php
                    mbx_input_row('mailbox_sync_interval', _l('mailbox_sync_interval'), '', $options, 'number');
                    mbx_input_row('mailbox_initial_days', _l('mailbox_initial_days'), '', $options, 'number');
                    mbx_input_row('mailbox_sync_batch', _l('mailbox_sync_batch'), '', $options, 'number');
                    ?>
                </div>
            </div>

            <!-- ══════════════ SLA ══════════════ -->
            <div class="mbx-tabpage" data-page="sla">
                <div class="mbx-card">
                    <div class="mbx-card-title"><i class="fa fa-stopwatch"></i> <?= _l('mailbox_sla'); ?></div>
                    <?php
                    mbx_switch_row('mailbox_sla_enabled', _l('mailbox_sla_enable'), '', $options);
                    mbx_input_row('mailbox_sla_hours', _l('mailbox_sla_hours'), _l('mailbox_sla_hours_hint'), $options, 'number');
                    mbx_switch_row('mailbox_sla_notify_admins', _l('mailbox_sla_notify_admins'), '', $options);
                    ?>
                </div>
            </div>

            <!-- ══════════════ Compliance ══════════════ -->
            <div class="mbx-tabpage" data-page="compliance">
                <div class="mbx-card">
                    <div class="mbx-card-title"><i class="fa fa-box-archive"></i> <?= _l('mailbox_compliance'); ?></div>
                    <?php
                    mbx_input_row('mailbox_compliance_bcc', _l('mailbox_compliance_bcc'), _l('mailbox_compliance_bcc_hint'), $options, 'email');
                    mbx_switch_row('mailbox_audit_enabled', _l('mailbox_audit_enable'), '', $options);
                    mbx_input_row('mailbox_audit_retention_days', _l('mailbox_audit_retention'), '', $options, 'number');
                    ?>
                </div>

                <div class="mbx-card">
                    <div class="mbx-card-title"><i class="fa fa-shield-halved"></i> <?= _l('mailbox_dlp'); ?></div>
                    <?php
                    mbx_switch_row('mailbox_dlp_enabled', _l('mailbox_dlp_enable'), '', $options);
                    mbx_input_row('mailbox_dlp_mode', _l('mailbox_dlp_mode'), '', $options, 'select', [
                        'warn'  => _l('mailbox_dlp_mode_warn'),
                        'block' => _l('mailbox_dlp_mode_block'),
                    ]);
                    mbx_input_row('mailbox_dlp_keywords', _l('mailbox_dlp_keywords'), _l('mailbox_dlp_keywords_hint'), $options, 'textarea');
                    mbx_switch_row('mailbox_dlp_detect_cards', _l('mailbox_dlp_cards'), '', $options);
                    ?>
                </div>

                <div class="mbx-card">
                    <div class="mbx-card-title"><i class="fa fa-trash-can"></i> <?= _l('mailbox_retention'); ?></div>
                    <div class="mbx-banner mbx-banner-warning">
                        <i class="fa fa-triangle-exclamation"></i> <?= _l('mailbox_retention_warning'); ?>
                    </div>
                    <?php
                    mbx_input_row('mailbox_retention_days', _l('mailbox_retention_days'), '', $options, 'number');
                    $folders = array_map('trim', explode(',', (string) ($options['mailbox_retention_folders'] ?? 'trash')));
                    ?>
                    <div class="mbx-setting">
                        <div class="mbx-setting-main">
                            <label><?= _l('mailbox_retention_folders'); ?></label>
                        </div>
                        <div class="mbx-setting-control">
                            <?php foreach (['inbox', 'sent', 'drafts', 'archive', 'trash'] as $folder) : ?>
                                <label class="mbx-check-row">
                                    <input type="checkbox" name="mailbox_retention_folders[]" value="<?= $folder; ?>"
                                        <?= in_array($folder, $folders, true) ? 'checked' : ''; ?>>
                                    <?= _l('mailbox_folder_' . $folder); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            </form>

            <!-- ══════════════ Rules ══════════════ -->
            <div class="mbx-tabpage" data-page="rules">
                <div class="mbx-card">
                    <div class="mbx-card-title">
                        <i class="fa fa-wand-magic-sparkles"></i> <?= _l('mailbox_rules'); ?>
                        <button class="mbx-btn mbx-btn-primary mbx-btn-sm mbx-right" id="mbx-rule-new">
                            <i class="fa fa-plus"></i> <?= _l('mailbox_new_rule'); ?>
                        </button>
                    </div>
                    <div class="mbx-table-wrap">
                        <table class="mbx-table" id="mbx-rules-table">
                            <thead>
                                <tr>
                                    <th><?= _l('mailbox_rule_name'); ?></th>
                                    <th><?= _l('mailbox_rule_account'); ?></th>
                                    <th><?= _l('mailbox_rule_hits'); ?></th>
                                    <th><?= _l('mailbox_status'); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ══════════════ Templates ══════════════ -->
            <div class="mbx-tabpage" data-page="templates">
                <div class="mbx-card">
                    <div class="mbx-card-title">
                        <i class="fa fa-bolt"></i> <?= _l('mailbox_templates'); ?>
                        <button class="mbx-btn mbx-btn-primary mbx-btn-sm mbx-right" id="mbx-template-new">
                            <i class="fa fa-plus"></i> <?= _l('mailbox_new_template'); ?>
                        </button>
                    </div>
                    <div class="mbx-code-hint" style="margin-bottom:10px">
                        <strong><?= _l('mailbox_placeholders'); ?>:</strong>
                        <?php foreach ($placeholders as $placeholder) : ?><code><?= html_escape($placeholder); ?></code><?php endforeach; ?>
                        <?= _l('mailbox_placeholders_hint'); ?>
                    </div>
                    <div class="mbx-table-wrap">
                        <table class="mbx-table" id="mbx-templates-table">
                            <thead>
                                <tr>
                                    <th><?= _l('mailbox_template_name'); ?></th>
                                    <th><?= _l('mailbox_subject'); ?></th>
                                    <th><?= _l('mailbox_used_times'); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ══════════════ Labels ══════════════ -->
            <div class="mbx-tabpage" data-page="labels">
                <div class="mbx-card">
                    <div class="mbx-card-title">
                        <i class="fa fa-tags"></i> <?= _l('mailbox_labels'); ?>
                        <button class="mbx-btn mbx-btn-primary mbx-btn-sm mbx-right" id="mbx-label-new-admin">
                            <i class="fa fa-plus"></i> <?= _l('mailbox_new_label'); ?>
                        </button>
                    </div>
                    <div class="mbx-table-wrap">
                        <table class="mbx-table" id="mbx-labels-table">
                            <thead>
                                <tr>
                                    <th><?= _l('mailbox_label_name'); ?></th>
                                    <th><?= _l('mailbox_label_scope'); ?></th>
                                    <th><?= _l('mailbox_messages_count'); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ══════════════ Per-account policies ══════════════ -->
            <div class="mbx-tabpage" data-page="accounts">
                <?php foreach ($accounts as $account) : ?>
                    <div class="mbx-card mbx-account-policy" data-account="<?= (int) $account->id; ?>">
                        <div class="mbx-card-title">
                            <i class="fa fa-envelope"></i> <?= html_escape($account->name); ?>
                            <span class="mbx-muted mbx-small">— <?= html_escape($account->email); ?></span>
                        </div>

                        <div class="mbx-setting">
                            <div class="mbx-setting-main">
                                <label><?= _l('mailbox_shared_inbox_enable'); ?></label>
                            </div>
                            <label class="mbx-switch">
                                <input type="checkbox" name="shared_inbox" <?= !empty($account->shared_inbox) ? 'checked' : ''; ?>>
                                <span></span>
                            </label>
                        </div>

                        <div class="mbx-setting">
                            <div class="mbx-setting-main">
                                <label><?= _l('mailbox_default_assignee'); ?></label>
                            </div>
                            <div class="mbx-setting-control">
                                <select name="default_assignee">
                                    <option value="0">— <?= _l('mailbox_unassigned'); ?> —</option>
                                    <?php foreach ($staff as $member) : ?>
                                        <option value="<?= (int) $member->staffid; ?>"
                                            <?= (int) ($account->default_assignee ?? 0) === (int) $member->staffid ? 'selected' : ''; ?>>
                                            <?= html_escape($member->full_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mbx-setting">
                            <div class="mbx-setting-main">
                                <label><?= _l('mailbox_account_sla_override'); ?></label>
                            </div>
                            <div class="mbx-setting-control">
                                <input type="number" name="sla_hours" min="0" value="<?= (int) ($account->sla_hours ?? 0); ?>">
                            </div>
                        </div>

                        <div class="mbx-setting">
                            <div class="mbx-setting-main">
                                <label><?= _l('mailbox_oo_enable'); ?></label>
                                <div class="mbx-setting-hint"><?= _l('mailbox_oo_hint'); ?></div>
                            </div>
                            <label class="mbx-switch">
                                <input type="checkbox" name="oo_enabled" <?= !empty($account->oo_enabled) ? 'checked' : ''; ?>>
                                <span></span>
                            </label>
                        </div>

                        <div class="mbx-setting">
                            <div class="mbx-setting-main">
                                <label><?= _l('mailbox_oo_subject'); ?></label>
                                <div class="mbx-setting-hint"><?= _l('mailbox_oo_subject_hint'); ?></div>
                            </div>
                            <div class="mbx-setting-control">
                                <input type="text" name="oo_subject" value="<?= html_escape($account->oo_subject ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mbx-setting">
                            <div class="mbx-setting-main">
                                <label><?= _l('mailbox_oo_body'); ?></label>
                            </div>
                            <div class="mbx-setting-control">
                                <textarea name="oo_body"><?= html_escape($account->oo_body ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="mbx-setting">
                            <div class="mbx-setting-main">
                                <label><?= _l('mailbox_oo_from'); ?> / <?= _l('mailbox_oo_to'); ?></label>
                            </div>
                            <div class="mbx-setting-control mbx-rule-row">
                                <input type="date" name="oo_from" value="<?= html_escape($account->oo_from ?? ''); ?>">
                                <input type="date" name="oo_to" value="<?= html_escape($account->oo_to ?? ''); ?>">
                            </div>
                        </div>

                        <button class="mbx-btn mbx-btn-primary mbx-btn-sm" data-save-policy>
                            <i class="fa fa-floppy-disk"></i> <?= _l('mailbox_save'); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mbx-modal-backdrop" id="mbx-editor" style="display:none;"></div>
            <div class="mbx-toast" id="mbx-toast" style="display:none;"></div>
        </div>
    </div>
</div>

<script>
window.MBX_SETTINGS_BOOT = {
    urls: {
        save:          '<?= admin_url('mailbox/settings_save'); ?>',
        policySave:    '<?= admin_url('mailbox/account_policy_save'); ?>',
        ruleGet:       '<?= admin_url('mailbox/rule_get'); ?>',
        ruleSave:      '<?= admin_url('mailbox/rule_save'); ?>',
        ruleDelete:    '<?= admin_url('mailbox/rule_delete'); ?>',
        ruleToggle:    '<?= admin_url('mailbox/rule_toggle'); ?>',
        ruleTest:      '<?= admin_url('mailbox/rule_test'); ?>',
        templateSave:  '<?= admin_url('mailbox/template_save'); ?>',
        templateDelete:'<?= admin_url('mailbox/template_delete'); ?>',
        labelSave:     '<?= admin_url('mailbox/label_save'); ?>',
        labelDelete:   '<?= admin_url('mailbox/label_delete'); ?>'
    },
    accounts:  <?= json_encode(array_map(function ($a) {
        return ['id' => (int) $a->id, 'name' => $a->name, 'email' => $a->email];
    }, $accounts)); ?>,
    staff:     <?= json_encode(array_map(function ($s) {
        return ['staffid' => (int) $s->staffid, 'full_name' => $s->full_name];
    }, $staff)); ?>,
    labels:    <?= json_encode($labels); ?>,
    templates: <?= json_encode($templates); ?>,
    rules:     <?= json_encode(array_map(function ($r) {
        return [
            'id'              => (int) $r->id,
            'account_id'      => (int) $r->account_id,
            'account_name'    => $r->account_name,
            'name'            => $r->name,
            'match_type'      => $r->match_type,
            'conditions'      => json_decode((string) $r->conditions, true) ?: [],
            'actions'         => json_decode((string) $r->actions, true) ?: [],
            'active'          => (int) $r->active,
            'stop_processing' => (int) $r->stop_processing,
            'hits'            => (int) $r->hits,
        ];
    }, $rules)); ?>,
    fields:    <?= json_encode($rule_fields); ?>,
    operators: <?= json_encode($rule_ops); ?>,
    i18n: {
        allAccounts:   "<?= _l('mailbox_label_all_accounts'); ?>",
        saved:         "<?= _l('mailbox_saved'); ?>",
        loadError:     "<?= _l('mailbox_load_error'); ?>",
        save:          "<?= _l('mailbox_save'); ?>",
        cancel:        "<?= _l('mailbox_cancel'); ?>",
        del:           "<?= _l('mailbox_delete'); ?>",
        edit:          "<?= _l('mailbox_edit'); ?>",
        enabled:       "<?= _l('mailbox_enabled'); ?>",
        disabled:      "<?= _l('mailbox_disabled'); ?>",
        newRule:       "<?= _l('mailbox_new_rule'); ?>",
        ruleName:      "<?= _l('mailbox_rule_name'); ?>",
        ruleAccount:   "<?= _l('mailbox_rule_account'); ?>",
        ruleMatch:     "<?= _l('mailbox_rule_match'); ?>",
        ruleMatchAll:  "<?= _l('mailbox_rule_match_all'); ?>",
        ruleMatchAny:  "<?= _l('mailbox_rule_match_any'); ?>",
        ruleConditions:"<?= _l('mailbox_rule_conditions'); ?>",
        ruleActions:   "<?= _l('mailbox_rule_actions'); ?>",
        ruleAddCond:   "<?= _l('mailbox_rule_add_condition'); ?>",
        ruleStop:      "<?= _l('mailbox_rule_stop'); ?>",
        ruleTest:      "<?= _l('mailbox_rule_test'); ?>",
        ruleTestResult:"<?= _l('mailbox_rule_test_result'); ?>",
        ruleDeleteConfirm: "<?= _l('mailbox_rule_delete_confirm'); ?>",
        noRules:       "<?= _l('mailbox_no_rules'); ?>",
        newTemplate:   "<?= _l('mailbox_new_template'); ?>",
        templateName:  "<?= _l('mailbox_template_name'); ?>",
        templateBody:  "<?= _l('mailbox_template_body'); ?>",
        templateShared:"<?= _l('mailbox_template_shared'); ?>",
        templateDeleteConfirm: "<?= _l('mailbox_template_delete_confirm'); ?>",
        noTemplates:   "<?= _l('mailbox_no_templates'); ?>",
        newLabel:      "<?= _l('mailbox_new_label'); ?>",
        labelName:     "<?= _l('mailbox_label_name'); ?>",
        labelColor:    "<?= _l('mailbox_label_color'); ?>",
        labelScope:    "<?= _l('mailbox_label_scope'); ?>",
        labelDeleteConfirm: "<?= _l('mailbox_label_delete_confirm'); ?>",
        noLabels:      "<?= _l('mailbox_no_labels'); ?>",
        subject:       "<?= _l('mailbox_subject'); ?>",
        status:        "<?= _l('mailbox_status'); ?>",
        statusOpen:    "<?= _l('mailbox_status_open'); ?>",
        statusPending: "<?= _l('mailbox_status_pending'); ?>",
        statusClosed:  "<?= _l('mailbox_status_closed'); ?>",
        unassigned:    "<?= _l('mailbox_unassigned'); ?>",
        actionLabel:   "<?= _l('mailbox_action_label'); ?>",
        actionAssign:  "<?= _l('mailbox_action_assign'); ?>",
        actionStatus:  "<?= _l('mailbox_action_status'); ?>",
        actionMarkRead:"<?= _l('mailbox_action_mark_read'); ?>",
        actionStar:    "<?= _l('mailbox_action_star'); ?>",
        actionArchive: "<?= _l('mailbox_action_archive'); ?>",
        actionTrash:   "<?= _l('mailbox_action_trash'); ?>",
        actionForward: "<?= _l('mailbox_action_forward'); ?>",
        actionNotify:  "<?= _l('mailbox_action_notify'); ?>",
        fields: {
            from_email: "<?= _l('mailbox_field_from_email'); ?>",
            from_name: "<?= _l('mailbox_field_from_name'); ?>",
            to: "<?= _l('mailbox_field_to'); ?>",
            cc: "<?= _l('mailbox_field_cc'); ?>",
            subject: "<?= _l('mailbox_field_subject'); ?>",
            body: "<?= _l('mailbox_field_body'); ?>",
            has_attachment: "<?= _l('mailbox_field_has_attachment'); ?>",
            any_recipient: "<?= _l('mailbox_field_any_recipient'); ?>"
        },
        ops: {
            contains: "<?= _l('mailbox_op_contains'); ?>",
            not_contains: "<?= _l('mailbox_op_not_contains'); ?>",
            equals: "<?= _l('mailbox_op_equals'); ?>",
            starts_with: "<?= _l('mailbox_op_starts_with'); ?>",
            ends_with: "<?= _l('mailbox_op_ends_with'); ?>",
            regex: "<?= _l('mailbox_op_regex'); ?>",
            is_true: "<?= _l('mailbox_op_is_true'); ?>",
            is_false: "<?= _l('mailbox_op_is_false'); ?>"
        }
    }
};
</script>

<?php init_tail(); ?>
</body>
</html>
