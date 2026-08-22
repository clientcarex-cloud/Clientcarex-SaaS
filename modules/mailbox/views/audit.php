<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Mailbox — audit trail.
 *
 * Every read, send, assignment, label, deletion and export, with the actor and
 * their IP. Filterable and exportable to CSV for a compliance review.
 */
init_head();
?>
<div id="wrapper">
    <div class="content">
        <div class="mbx-wrap" id="mbx-audit">

            <div class="mbx-header">
                <div class="mbx-title"><i class="fa fa-shield-halved"></i> <?= _l('mailbox_audit_trail'); ?></div>
                <div class="mbx-right">
                    <a href="<?= admin_url('mailbox'); ?>" class="mbx-btn mbx-btn-light"><i class="fa fa-arrow-left"></i> <?= _l('mailbox_back_to_mailbox'); ?></a>
                    <button class="mbx-btn mbx-btn-primary" id="mbx-audit-export"><i class="fa fa-file-csv"></i> <?= _l('mailbox_export_csv'); ?></button>
                </div>
            </div>

            <?php if ((int) mailbox_opt('mailbox_audit_enabled', '1') !== 1) : ?>
                <div class="mbx-banner mbx-banner-warning">
                    <i class="fa fa-triangle-exclamation"></i> <?= _l('mailbox_audit_disabled'); ?>
                </div>
            <?php endif; ?>

            <div class="mbx-filters">
                <div class="mbx-filter">
                    <label><?= _l('mailbox_accounts'); ?></label>
                    <select id="mbx-f-account">
                        <option value=""><?= _l('mailbox_filter_all'); ?></option>
                        <?php foreach ($accounts as $account) : ?>
                            <option value="<?= (int) $account->id; ?>"><?= html_escape($account->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mbx-filter">
                    <label><?= _l('mailbox_audit_who'); ?></label>
                    <select id="mbx-f-staff">
                        <option value=""><?= _l('mailbox_filter_all'); ?></option>
                        <?php foreach ($staff as $member) : ?>
                            <option value="<?= (int) $member->staffid; ?>"><?= html_escape($member->full_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mbx-filter">
                    <label><?= _l('mailbox_audit_action'); ?></label>
                    <select id="mbx-f-action">
                        <option value=""><?= _l('mailbox_filter_all'); ?></option>
                        <?php foreach ($actions as $action) : ?>
                            <option value="<?= html_escape($action); ?>"><?= html_escape(ucwords(str_replace('_', ' ', $action))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mbx-filter">
                    <label><?= _l('mailbox_date_from'); ?></label>
                    <input type="date" id="mbx-f-from">
                </div>
                <div class="mbx-filter">
                    <label><?= _l('mailbox_date_to'); ?></label>
                    <input type="date" id="mbx-f-to">
                </div>
                <div class="mbx-filter">
                    <label><?= _l('mailbox_search_placeholder'); ?></label>
                    <input type="text" id="mbx-f-search" placeholder="<?= _l('mailbox_audit_details'); ?>">
                </div>
                <button class="mbx-btn mbx-btn-primary" id="mbx-f-apply"><i class="fa fa-filter"></i> <?= _l('mailbox_apply_filters'); ?></button>
                <button class="mbx-btn mbx-btn-light" id="mbx-f-reset"><?= _l('mailbox_reset'); ?></button>
            </div>

            <div class="mbx-card">
                <div class="mbx-table-wrap">
                    <table class="mbx-table" id="mbx-audit-table">
                        <thead>
                            <tr>
                                <th><?= _l('mailbox_audit_when'); ?></th>
                                <th><?= _l('mailbox_audit_who'); ?></th>
                                <th><?= _l('mailbox_audit_action'); ?></th>
                                <th><?= _l('mailbox_accounts'); ?></th>
                                <th><?= _l('mailbox_subject'); ?></th>
                                <th><?= _l('mailbox_audit_details'); ?></th>
                                <th><?= _l('mailbox_audit_ip'); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="mbx-pager" style="justify-content:flex-end;padding:12px">
                    <span id="mbx-audit-pager"></span>
                    <button class="mbx-icon-btn" id="mbx-audit-prev"><i class="fa fa-chevron-left"></i></button>
                    <button class="mbx-icon-btn" id="mbx-audit-next"><i class="fa fa-chevron-right"></i></button>
                </div>
            </div>

            <div class="mbx-toast" id="mbx-toast" style="display:none;"></div>
        </div>
    </div>
</div>

<script>
window.MBX_AUDIT_BOOT = {
    urls: {
        data:   '<?= admin_url('mailbox/audit_data'); ?>',
        csv:    '<?= admin_url('mailbox/audit_export'); ?>'
    },
    i18n: {
        empty:     "<?= _l('mailbox_audit_empty'); ?>",
        system:    "<?= _l('mailbox_audit_system'); ?>",
        loadError: "<?= _l('mailbox_load_error'); ?>"
    }
};
</script>

<?php init_tail(); ?>
</body>
</html>
