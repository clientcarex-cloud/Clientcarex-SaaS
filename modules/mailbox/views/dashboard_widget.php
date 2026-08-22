<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php $this->load->view('mailbox/_hpw_base'); ?>

<!-- ── Mailbox dashboard widget ── -->
<div class="col-md-12 hpw-col">
    <div class="hpw-card">
        <div class="hpw-head">
            <span class="hpw-ico" style="background:linear-gradient(135deg,#f59e0b,#ea580c);"><i class="fa-solid fa-envelope-open-text"></i></span>
            <h4 class="hpw-title"><?php echo _l('mailbox'); ?> <span class="hpw-sub">Team Mail</span></h4>
            <div class="hpw-head-badges">
                <?php if ((int) ($totals->unread ?? 0) > 0): ?>
                    <span class="hpw-badge hpw-badge--warn"><i class="fa fa-envelope"></i> <?php echo (int) $totals->unread; ?> unread</span>
                <?php else: ?>
                    <span class="hpw-badge hpw-badge--ok"><i class="fa fa-check"></i> Inbox zero</span>
                <?php endif; ?>
                <?php if (count($sync_errors) > 0): ?>
                    <span class="hpw-badge hpw-badge--crit"><i class="fa fa-plug-circle-xmark"></i> <?php echo count($sync_errors); ?> sync <?php echo count($sync_errors) === 1 ? 'error' : 'errors'; ?></span>
                <?php endif; ?>
            </div>
            <a class="hpw-open" href="<?php echo admin_url('mailbox'); ?>">Open Mailbox <i class="fa fa-arrow-right"></i></a>
        </div>
        <div class="hpw-body">
            <div class="hpw-kpis">
                <a class="hpw-kpi <?php echo (int) ($totals->unread ?? 0) > 0 ? 'hpw-kpi--warn' : ''; ?>" href="<?php echo admin_url('mailbox'); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) ($totals->unread ?? 0); ?></div>
                    <div class="hpw-kpi-lbl">Unread</div>
                </a>
                <a class="hpw-kpi" href="<?php echo admin_url('mailbox'); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) ($totals->received_today ?? 0); ?></div>
                    <div class="hpw-kpi-lbl">Received Today</div>
                </a>
                <a class="hpw-kpi" href="<?php echo admin_url('mailbox'); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) ($totals->sent_today ?? 0); ?></div>
                    <div class="hpw-kpi-lbl">Sent Today</div>
                </a>
                <a class="hpw-kpi" href="<?php echo admin_url('mailbox'); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) ($totals->starred ?? 0); ?></div>
                    <div class="hpw-kpi-lbl">Starred</div>
                </a>
                <a class="hpw-kpi" href="<?php echo admin_url('mailbox'); ?>">
                    <div class="hpw-kpi-val"><?php echo count($accounts); ?></div>
                    <div class="hpw-kpi-lbl">Accounts</div>
                </a>
                <a class="hpw-kpi <?php echo count($sync_errors) > 0 ? 'hpw-kpi--crit' : 'hpw-kpi--good'; ?>" href="<?php echo admin_url('mailbox' . (mailbox_staff_is_super() ? '/accounts' : '')); ?>">
                    <div class="hpw-kpi-val"><?php echo count($sync_errors); ?></div>
                    <div class="hpw-kpi-lbl">Sync Errors</div>
                </a>
            </div>
            <div class="hpw-charts">
                <div class="hpw-chart" style="flex:1.35;">
                    <h5 class="hpw-sec-title">Mail Volume — 7 Days</h5>
                    <div class="hpw-canvas"><canvas id="hpw-mbx-volume"></canvas></div>
                </div>
                <div class="hpw-chart">
                    <h5 class="hpw-sec-title">Account Health</h5>
                    <div class="hpw-rows" style="max-height:190px;">
                        <?php foreach ($accounts as $a):
                            $err    = trim((string) ($a->last_sync_error ?? '')) !== '';
                            $unread = $unread_by_account[(int) $a->id] ?? 0;
                        ?>
                        <a class="hpw-row" href="<?php echo admin_url('mailbox'); ?>" title="<?php echo $err ? html_escape($a->last_sync_error) : ''; ?>">
                            <span class="hpw-dot" style="background:<?php echo $err ? '#d03b3b' : ((int) $a->active === 1 ? '#0ca30c' : '#c3c2b7'); ?>;"></span>
                            <span class="hpw-row-main">
                                <span class="hpw-row-title"><?php echo html_escape($a->name); ?></span>
                                <span class="hpw-row-meta">
                                    <?php if ($err): ?>
                                        Sync failing<?php echo $a->last_sync_at ? ' · last OK ' . time_ago($a->last_sync_at) : ''; ?>
                                    <?php elseif ($a->last_sync_at): ?>
                                        Synced <?php echo time_ago($a->last_sync_at); ?>
                                    <?php else: ?>
                                        Not synced yet
                                    <?php endif; ?>
                                </span>
                            </span>
                            <?php if ($err): ?>
                                <span class="hpw-chip hpw-chip--crit">Error</span>
                            <?php elseif ($unread > 0): ?>
                                <span class="hpw-chip hpw-chip--info"><?php echo $unread; ?> unread</span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="hpw-list">
                <h5 class="hpw-sec-title">Latest Unread</h5>
                <?php if (count($unread_messages) === 0): ?>
                    <div class="hpw-empty"><i class="fa fa-circle-check"></i> No unread mail — inbox is clear.</div>
                <?php else: ?>
                    <div class="hpw-rows">
                        <?php foreach ($unread_messages as $m):
                            $from = trim((string) $m->from_name) !== '' ? $m->from_name : $m->from_email;
                        ?>
                        <a class="hpw-row" href="<?php echo admin_url('mailbox'); ?>">
                            <span class="hpw-dot" style="background:#2a78d6;"></span>
                            <span class="hpw-row-main">
                                <span class="hpw-row-title"><?php echo html_escape($m->subject !== '' ? $m->subject : '(no subject)'); ?></span>
                                <span class="hpw-row-meta"><?php echo html_escape($from); ?> · <?php echo html_escape($m->account_name); ?></span>
                            </span>
                            <span class="hpw-chip hpw-chip--muted"><?php echo $m->message_date ? time_ago($m->message_date) : ''; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var data = <?php echo json_encode([
        'labels'   => $vol_labels,
        'received' => $vol_received,
        'sent'     => $vol_sent,
    ]); ?>;

    window.hpwChart.ready(function (Chart) {
        var H = window.hpwChart, C = H.C;

        var el = document.getElementById('hpw-mbx-volume');
        if (!el) { return; }

        var opts = H.base();
        opts.scales = H.axes();
        opts.plugins.legend = H.legend();
        opts.plugins.tooltip.displayColors = true;
        opts.interaction = { mode: 'index', intersect: false };
        new Chart(el, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    { label: 'Received', data: data.received, backgroundColor: C.blue,
                      borderRadius: { topLeft: 4, topRight: 4 },
                      barPercentage: 0.6, categoryPercentage: 0.7, maxBarThickness: 20 },
                    { label: 'Sent', data: data.sent, backgroundColor: C.aqua,
                      borderRadius: { topLeft: 4, topRight: 4 },
                      barPercentage: 0.6, categoryPercentage: 0.7, maxBarThickness: 20 }
                ]
            },
            options: opts
        });
    });
})();
</script>
