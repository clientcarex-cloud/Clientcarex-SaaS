<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="lsy-wrap">

            <?php $active = 'logs'; include __DIR__ . '/_nav.php'; ?>

            <div class="lsy-card">
                <div class="lsy-card-head">
                    <h3>Sync history</h3>
                    <div class="lsy-card-actions">
                        <select class="lsy-select" onchange="window.location = this.value;" style="min-width:220px">
                            <option value="<?= admin_url('lead_sync/logs'); ?>">All connections</option>
                            <?php foreach ($connections as $connection) { ?>
                                <option value="<?= admin_url('lead_sync/logs/' . $connection->id); ?>" <?= $filter === (int) $connection->id ? 'selected' : ''; ?>>
                                    <?= html_escape($connection->name); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <?php if (!count($runs)) { ?>
                    <div class="lsy-empty">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <h4>Nothing has run yet</h4>
                        <p class="lsy-muted">Runs appear here as soon as the cron picks up a connection, or you press “Sync now”.</p>
                    </div>
                <?php } else { ?>
                    <div class="lsy-table-scroll">
                        <table class="lsy-table">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Connection</th>
                                    <th>Trigger</th>
                                    <th class="lsy-num">Rows</th>
                                    <th class="lsy-num">New</th>
                                    <th class="lsy-num">Duplicate</th>
                                    <th class="lsy-num">Skipped</th>
                                    <th class="lsy-num">Failed</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($runs as $run) {
                                $badge = ['ok' => 'ok', 'partial' => 'warn', 'error' => 'bad'][$run->status] ?? 'info';
                            ?>
                                <tr>
                                    <td class="lsy-small">
                                        <span class="lsy-strong"><?= html_escape(lead_sync_time_ago($run->started_at)); ?></span>
                                        <div class="lsy-muted"><?= html_escape($run->started_at); ?></div>
                                    </td>
                                    <td class="lsy-small lsy-strong"><?= html_escape((string) $run->connection_name); ?></td>
                                    <td><span class="lsy-badge info"><?= html_escape($run->trigger_type); ?></span></td>
                                    <td class="lsy-num"><?= (int) $run->rows_read; ?></td>
                                    <td class="lsy-num lsy-strong"><?= (int) $run->created; ?></td>
                                    <td class="lsy-num lsy-muted"><?= (int) $run->duplicates; ?></td>
                                    <td class="lsy-num lsy-muted"><?= (int) $run->skipped; ?></td>
                                    <td class="lsy-num <?= $run->failed ? 'lsy-strong' : 'lsy-muted'; ?>"><?= (int) $run->failed; ?></td>
                                    <td class="lsy-small">
                                        <span class="lsy-badge <?= $badge; ?>"><?= html_escape(lead_sync_status_label($run->status)); ?></span>
                                        <div class="lsy-muted"><?= html_escape((string) $run->message); ?></div>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
