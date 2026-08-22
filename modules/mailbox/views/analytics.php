<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Mailbox — team analytics.
 *
 * Volume, first-response time, SLA compliance and workload per account and per
 * team member. Everything is drawn from JSON by mailbox_pro.js; this file is
 * only the shell.
 */
init_head();
?>
<div id="wrapper">
    <div class="content">
        <div class="mbx-wrap" id="mbx-analytics">

            <div class="mbx-header">
                <div class="mbx-title"><i class="fa fa-chart-line"></i> <?= _l('mailbox_analytics'); ?></div>
                <div class="mbx-right">
                    <a href="<?= admin_url('mailbox'); ?>" class="mbx-btn mbx-btn-light"><i class="fa fa-arrow-left"></i> <?= _l('mailbox_back_to_mailbox'); ?></a>
                </div>
            </div>

            <div class="mbx-filters">
                <div class="mbx-filter">
                    <label><?= _l('mailbox_accounts'); ?></label>
                    <select id="mbx-a-account">
                        <option value="all"><?= _l('mailbox_all_accounts'); ?></option>
                        <?php foreach ($accounts as $account) : ?>
                            <option value="<?= (int) $account->id; ?>"><?= html_escape($account->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mbx-filter">
                    <label><?= _l('mailbox_period'); ?></label>
                    <select id="mbx-a-period">
                        <option value="7"><?= _l('mailbox_last_7'); ?></option>
                        <option value="30" selected><?= _l('mailbox_last_30'); ?></option>
                        <option value="90"><?= _l('mailbox_last_90'); ?></option>
                        <option value="custom"><?= _l('mailbox_date_from'); ?>…</option>
                    </select>
                </div>
                <div class="mbx-filter" id="mbx-a-custom" style="display:none;">
                    <label><?= _l('mailbox_date_from'); ?></label>
                    <input type="date" id="mbx-a-from">
                </div>
                <div class="mbx-filter" id="mbx-a-custom2" style="display:none;">
                    <label><?= _l('mailbox_date_to'); ?></label>
                    <input type="date" id="mbx-a-to">
                </div>
                <button class="mbx-btn mbx-btn-primary" id="mbx-a-apply"><i class="fa fa-rotate"></i> <?= _l('mailbox_apply_filters'); ?></button>
            </div>

            <div id="mbx-a-stats" class="mbx-stat-grid"></div>

            <div class="mbx-card">
                <div class="mbx-card-title"><i class="fa fa-chart-column"></i> <?= _l('mailbox_volume'); ?></div>
                <div id="mbx-a-volume"></div>
            </div>

            <div class="mbx-card">
                <div class="mbx-card-title"><i class="fa fa-envelope"></i> <?= _l('mailbox_by_account'); ?></div>
                <div class="mbx-table-wrap"><table class="mbx-table" id="mbx-a-accounts"></table></div>
            </div>

            <div class="mbx-card">
                <div class="mbx-card-title"><i class="fa fa-users"></i> <?= _l('mailbox_by_staff'); ?></div>
                <div class="mbx-table-wrap"><table class="mbx-table" id="mbx-a-staff"></table></div>
            </div>

            <div class="mbx-card">
                <div class="mbx-card-title"><i class="fa fa-ranking-star"></i> <?= _l('mailbox_top_senders'); ?></div>
                <div class="mbx-table-wrap"><table class="mbx-table" id="mbx-a-senders"></table></div>
            </div>

            <div class="mbx-card">
                <div class="mbx-card-title"><i class="fa fa-clock"></i> <?= _l('mailbox_busiest_hours'); ?></div>
                <div id="mbx-a-hours"></div>
            </div>

            <div class="mbx-toast" id="mbx-toast" style="display:none;"></div>
        </div>
    </div>
</div>

<script>
window.MBX_ANALYTICS_BOOT = {
    url: '<?= admin_url('mailbox/analytics_data'); ?>',
    i18n: {
        received:      "<?= _l('mailbox_received'); ?>",
        sent:          "<?= _l('mailbox_sent'); ?>",
        answered:      "<?= _l('mailbox_answered'); ?>",
        unread:        "<?= _l('mailbox_unread_count'); ?>",
        openConv:      "<?= _l('mailbox_open_conversations'); ?>",
        unassigned:    "<?= _l('mailbox_unassigned'); ?>",
        avgResponse:   "<?= _l('mailbox_avg_response'); ?>",
        slaCompliance: "<?= _l('mailbox_sla_compliance'); ?>",
        slaBreached:   "<?= _l('mailbox_sla_breached'); ?>",
        scheduled:     "<?= _l('mailbox_scheduled'); ?>",
        closed:        "<?= _l('mailbox_closed_by'); ?>",
        assigned:      "<?= _l('mailbox_assigned_to'); ?>",
        noData:        "<?= _l('mailbox_no_data'); ?>",
        loadError:     "<?= _l('mailbox_load_error'); ?>",
        minutes:       "<?= _l('mailbox_minutes_short'); ?>",
        hours:         "<?= _l('mailbox_hours_short'); ?>",
        account:       "<?= _l('mailbox_accounts'); ?>",
        staff:         "<?= _l('mailbox_by_staff'); ?>",
        sender:        "<?= _l('mailbox_from'); ?>",
        count:         "<?= _l('mailbox_messages_count'); ?>"
    }
};
</script>

<?php init_tail(); ?>
</body>
</html>
