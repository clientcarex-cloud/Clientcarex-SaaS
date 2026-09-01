<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="lsy-wrap">

            <?php $active = 'settings'; include __DIR__ . '/_nav.php'; ?>

            <?= form_open(admin_url('lead_sync/settings')); ?>
            <div class="lsy-split">
                <div>
                    <div class="lsy-card">
                        <div class="lsy-card-head"><h3>Syncing</h3></div>
                        <div class="lsy-card-body">
                            <div class="lsy-field">
                                <label class="lsy-check">
                                    <input type="checkbox" name="lead_sync_enabled" value="1" <?= lead_sync_opt('lead_sync_enabled') === '1' ? 'checked' : ''; ?>>
                                    <span><strong>Import leads automatically</strong><br>
                                        <span class="lsy-hint">Master switch. Turning this off stops every connection and the webhook, without losing any settings.</span>
                                    </span>
                                </label>
                            </div>

                            <div class="lsy-grid-2">
                                <div class="lsy-field">
                                    <label class="lsy-label">Rows to handle per run</label>
                                    <input type="number" name="lead_sync_max_rows_per_run" class="lsy-input" min="20" max="5000"
                                           value="<?= html_escape(lead_sync_opt('lead_sync_max_rows_per_run')); ?>">
                                    <div class="lsy-hint">A first sync of a very large sheet is spread over several runs so the cron cannot time out. Rows left over are picked up next time.</div>
                                </div>
                                <div class="lsy-field">
                                    <label class="lsy-label">Network timeout (seconds)</label>
                                    <input type="number" name="lead_sync_http_timeout" class="lsy-input" min="5" max="120"
                                           value="<?= html_escape(lead_sync_opt('lead_sync_http_timeout')); ?>">
                                </div>
                            </div>

                            <div class="lsy-field">
                                <label class="lsy-label">Keep run history for (days)</label>
                                <input type="number" name="lead_sync_log_retention_days" class="lsy-input" min="1" max="730"
                                       value="<?= html_escape(lead_sync_opt('lead_sync_log_retention_days')); ?>">
                                <div class="lsy-hint">Only the run log is trimmed. Imported leads and the row fingerprints that stop re-imports are kept forever.</div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="lsy-btn lsy-btn-primary"><i class="fa fa-check"></i> Save settings</button>
                </div>

                <div>
                    <div class="lsy-card">
                        <div class="lsy-card-head"><h3><i class="fa-solid fa-circle-info"></i> How it fits together</h3></div>
                        <div class="lsy-card-body lsy-small">
                            <p>Lead Sync creates ordinary CRM leads — the same rows the Leads screen shows and
                               the same ones any other module reacts to. Nothing here is specific to one
                               business, so the same connection setup works for any tenant.</p>
                            <p class="lsy-muted">Polling runs on the CRM's own cron, so if the cron is not
                               scheduled on this server nothing will import on a timer. Instant delivery
                               through a connection's webhook URL works regardless.</p>
                            <p class="lsy-muted" style="margin-bottom:0">
                                Last cron pass: <strong><?= html_escape(lead_sync_time_ago(get_option('lead_sync_last_cron'))); ?></strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?= form_close(); ?>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
