<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="lsy-wrap">

            <?php $active = 'connections'; include __DIR__ . '/_nav.php'; ?>

            <div class="lsy-tiles">
                <div class="lsy-tile">
                    <div class="lsy-tile-value"><?= (int) $summary['active']; ?><span class="lsy-muted" style="font-size:15px"> / <?= (int) $summary['connections']; ?></span></div>
                    <div class="lsy-tile-label">Sheets syncing</div>
                </div>
                <div class="lsy-tile">
                    <div class="lsy-tile-value"><?= (int) $summary['today']; ?></div>
                    <div class="lsy-tile-label">Leads imported today</div>
                </div>
                <div class="lsy-tile">
                    <div class="lsy-tile-value"><?= (int) $summary['imported']; ?></div>
                    <div class="lsy-tile-label">Leads imported in total</div>
                </div>
            </div>

            <?php if (!count($connections)) { ?>
                <div class="lsy-card">
                    <div class="lsy-empty">
                        <i class="fa-solid fa-table-list"></i>
                        <h4>No sheets connected yet</h4>
                        <p class="lsy-muted" style="max-width:520px;margin:0 auto 18px">
                            Point Lead Sync at the Google Sheet your Meta / Instagram lead ads land in.
                            Every new row becomes a CRM lead — mapped to the right fields, checked against
                            the leads you already have, and handed to an agent.
                        </p>
                        <?php if (lead_sync_can('create')) { ?>
                            <a href="<?= admin_url('lead_sync/connection'); ?>" class="lsy-btn lsy-btn-primary">
                                <i class="fa fa-plus"></i> Connect a sheet
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } else { ?>
                <div class="lsy-card">
                    <div class="lsy-card-head"><h3>Connected sheets</h3></div>
                    <div class="lsy-table-scroll">
                        <table class="lsy-table">
                            <thead>
                                <tr>
                                    <th>Connection</th>
                                    <th>Delivery</th>
                                    <th>Last run</th>
                                    <th>Result</th>
                                    <th class="lsy-num">Imported</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($connections as $connection) {
                                $badge = ['ok' => 'ok', 'partial' => 'warn', 'error' => 'bad'][$connection->last_status] ?? 'info';
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?= admin_url('lead_sync/connection/' . $connection->id); ?>" class="lsy-strong"><?= html_escape($connection->name); ?></a>
                                        <div class="lsy-small lsy-muted">
                                            <?= $connection->active ? '<span class="lsy-badge ok">Active</span>' : '<span class="lsy-badge">Paused</span>'; ?>
                                            <?= html_escape($connection->tab_name !== '' ? ' · tab "' . $connection->tab_name . '"' : ''); ?>
                                        </div>
                                    </td>
                                    <td class="lsy-small">
                                        Every <?= (int) $connection->interval_minutes; ?> min
                                        <div class="lsy-muted">+ instant push</div>
                                    </td>
                                    <td class="lsy-small"><?= html_escape(lead_sync_time_ago($connection->last_run_at)); ?></td>
                                    <td class="lsy-small">
                                        <span class="lsy-badge <?= $badge; ?>"><?= html_escape(lead_sync_status_label($connection->last_status)); ?></span>
                                        <div class="lsy-muted"><?= html_escape($connection->last_message); ?></div>
                                    </td>
                                    <td class="lsy-num lsy-strong"><?= (int) $connection->total_imported; ?></td>
                                    <td class="text-right" style="white-space:nowrap">
                                        <?php if (lead_sync_can('edit')) { ?>
                                            <a href="<?= admin_url('lead_sync/sync/' . $connection->id); ?>" class="lsy-btn lsy-btn-sm" title="Read the sheet now">
                                                <i class="fa fa-rotate"></i> Sync now
                                            </a>
                                            <a href="<?= admin_url('lead_sync/toggle/' . $connection->id); ?>" class="lsy-btn lsy-btn-sm">
                                                <i class="fa <?= $connection->active ? 'fa-pause' : 'fa-play'; ?>"></i>
                                            </a>
                                        <?php } ?>
                                        <a href="<?= admin_url('lead_sync/connection/' . $connection->id); ?>" class="lsy-btn lsy-btn-sm"><i class="fa fa-cog"></i></a>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (count($recent)) { ?>
                    <div class="lsy-card">
                        <div class="lsy-card-head">
                            <h3>Recent activity</h3>
                            <div class="lsy-card-actions"><a href="<?= admin_url('lead_sync/logs'); ?>" class="lsy-btn lsy-btn-sm">Full history</a></div>
                        </div>
                        <div class="lsy-table-scroll">
                            <table class="lsy-table">
                                <tbody>
                                <?php foreach ($recent as $run) {
                                    $badge = ['ok' => 'ok', 'partial' => 'warn', 'error' => 'bad'][$run->status] ?? 'info';
                                ?>
                                    <tr>
                                        <td class="lsy-small" style="width:150px"><?= html_escape(lead_sync_time_ago($run->started_at)); ?></td>
                                        <td class="lsy-small lsy-strong"><?= html_escape((string) $run->connection_name); ?></td>
                                        <td class="lsy-small"><span class="lsy-badge info"><?= html_escape($run->trigger_type); ?></span></td>
                                        <td class="lsy-small"><span class="lsy-badge <?= $badge; ?>"><?= html_escape(lead_sync_status_label($run->status)); ?></span> <?= html_escape((string) $run->message); ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
