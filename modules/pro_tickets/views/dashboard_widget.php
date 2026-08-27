<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php $this->load->view('pro_tickets/_hpw_base'); ?>

<!-- ── Pro Tickets dashboard widget ── -->
<div class="col-md-12 hpw-col">
    <div class="hpw-card">
        <div class="hpw-head">
            <span class="hpw-ico" style="background:linear-gradient(135deg,#0ea5e9,#2563eb);"><i class="fa-solid fa-headset"></i></span>
            <h4 class="hpw-title"><?php echo _l('pro_tickets'); ?> <span class="hpw-sub">Helpdesk</span></h4>
            <div class="hpw-head-badges">
                <?php if ((int) $dash['kpi']['overdue'] > 0): ?>
                    <span class="hpw-badge hpw-badge--crit"><i class="fa fa-triangle-exclamation"></i> <?php echo (int) $dash['kpi']['overdue']; ?> SLA overdue</span>
                <?php endif; ?>
                <?php if ((int) $dash['kpi']['at_risk'] > 0): ?>
                    <span class="hpw-badge hpw-badge--warn"><i class="fa fa-hourglass-half"></i> <?php echo (int) $dash['kpi']['at_risk']; ?> at risk</span>
                <?php endif; ?>
                <?php if ((int) $dash['kpi']['unassigned'] > 0): ?>
                    <span class="hpw-badge hpw-badge--warn"><i class="fa fa-user-slash"></i> <?php echo (int) $dash['kpi']['unassigned']; ?> unassigned</span>
                <?php endif; ?>
                <?php if ((int) $dash['kpi']['overdue'] === 0 && (int) $dash['kpi']['at_risk'] === 0 && (int) $dash['kpi']['unassigned'] === 0): ?>
                    <span class="hpw-badge hpw-badge--ok"><i class="fa fa-check"></i> All within SLA</span>
                <?php endif; ?>
            </div>
            <a class="hpw-open" href="<?php echo admin_url('pro_tickets'); ?>">Open Helpdesk <i class="fa fa-arrow-right"></i></a>
        </div>
        <div class="hpw-body">
            <div class="hpw-kpis">
                <a class="hpw-kpi" href="<?php echo admin_url('pro_tickets/tickets'); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) $dash['kpi']['open']; ?></div>
                    <div class="hpw-kpi-lbl">Open Tickets</div>
                </a>
                <a class="hpw-kpi" href="<?php echo admin_url('pro_tickets/tickets?assigned=' . get_staff_user_id()); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) $my_open; ?></div>
                    <div class="hpw-kpi-lbl">Assigned to Me</div>
                </a>
                <a class="hpw-kpi <?php echo (int) $dash['kpi']['unassigned'] > 0 ? 'hpw-kpi--warn' : ''; ?>" href="<?php echo admin_url('pro_tickets/tickets?assigned=-1'); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) $dash['kpi']['unassigned']; ?></div>
                    <div class="hpw-kpi-lbl">Unassigned</div>
                </a>
                <a class="hpw-kpi <?php echo (int) $dash['kpi']['overdue'] > 0 ? 'hpw-kpi--crit' : ''; ?>" href="<?php echo admin_url('pro_tickets/tickets?sla=breached'); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) $dash['kpi']['overdue']; ?></div>
                    <div class="hpw-kpi-lbl">SLA Overdue</div>
                </a>
                <a class="hpw-kpi <?php echo (int) $dash['kpi']['at_risk'] > 0 ? 'hpw-kpi--warn' : ''; ?>" href="<?php echo admin_url('pro_tickets/tickets?sla=at_risk'); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) $dash['kpi']['at_risk']; ?></div>
                    <div class="hpw-kpi-lbl">SLA At Risk</div>
                </a>
                <a class="hpw-kpi <?php echo $dash['kpi']['sla_pct'] !== null && (int) $dash['kpi']['sla_pct'] >= 90 ? 'hpw-kpi--good' : ''; ?>" href="<?php echo admin_url('pro_tickets'); ?>">
                    <div class="hpw-kpi-val"><?php echo $dash['kpi']['sla_pct'] !== null ? (int) $dash['kpi']['sla_pct'] . '%' : '—'; ?></div>
                    <div class="hpw-kpi-lbl">SLA Met · 30d</div>
                </a>
                <?php
                    $csatAvg  = $dash['kpi']['csat_avg'];
                    $csatTone = $csatAvg === null ? '' : ($csatAvg >= 4 ? 'hpw-kpi--good' : ($csatAvg < 3 ? 'hpw-kpi--crit' : 'hpw-kpi--warn'));
                ?>
                <a class="hpw-kpi <?php echo $csatTone; ?>" href="<?php echo admin_url('pro_tickets/tickets?feedback=rated'); ?>">
                    <div class="hpw-kpi-val"><?php echo $csatAvg !== null ? html_escape(number_format($csatAvg, 1)) . '/5' : '—'; ?></div>
                    <div class="hpw-kpi-lbl">CSAT · 30d</div>
                </a>
                <a class="hpw-kpi <?php echo (int) $dash['kpi']['csat_responses'] > 0 && (int) $dash['csat']['negative'] > 0 ? 'hpw-kpi--crit' : ''; ?>" href="<?php echo admin_url('pro_tickets/tickets?feedback=negative'); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) $dash['csat']['negative']; ?></div>
                    <div class="hpw-kpi-lbl">Unhappy · 30d</div>
                </a>
            </div>
            <div class="hpw-charts">
                <div class="hpw-chart" style="flex:1.4;">
                    <h5 class="hpw-sec-title">Opened vs Solved — 14 Days</h5>
                    <div class="hpw-canvas"><canvas id="hpw-ptk-trend"></canvas></div>
                </div>
                <div class="hpw-chart">
                    <h5 class="hpw-sec-title">Tickets by Status</h5>
                    <div class="hpw-canvas"><canvas id="hpw-ptk-status"></canvas></div>
                </div>
            </div>
            <div class="hpw-list">
                <h5 class="hpw-sec-title">Resolve Next — SLA Queue</h5>
                <?php if (count($attention) === 0): ?>
                    <div class="hpw-empty"><i class="fa fa-circle-check"></i> No tickets breaching or at risk of SLA.</div>
                <?php else: ?>
                    <div class="hpw-rows">
                        <?php foreach ($attention as $t):
                            $sla  = pro_tickets_sla_state($t, $t->status);
                            $chip = $sla['state'] === 'breach' ? 'crit' : ($sla['state'] === 'warn' ? 'warn' : 'info');
                        ?>
                        <a class="hpw-row" href="<?php echo admin_url('pro_tickets/ticket/' . (int) $t->ticketid); ?>">
                            <span class="hpw-dot" style="background:<?php echo html_escape($t->statuscolor ?: '#2a78d6'); ?>;"></span>
                            <span class="hpw-row-main">
                                <span class="hpw-row-title">#<?php echo (int) $t->ticketid; ?> — <?php echo html_escape($t->subject); ?></span>
                                <span class="hpw-row-meta"><?php echo trim((string) $t->assignee) !== '' ? html_escape($t->assignee) : 'Unassigned'; ?> · <?php echo html_escape($t->status_name ?: ''); ?></span>
                            </span>
                            <span class="hpw-chip hpw-chip--<?php echo $chip; ?>"><?php echo html_escape($sla['label']); ?></span>
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
        'trend'  => $dash['trend'],
        'status' => array_map(function ($r) {
            return ['name' => $r->name, 'color' => $r->statuscolor, 'cnt' => (int) $r->cnt];
        }, $dash['by_status']),
    ]); ?>;

    window.hpwChart.ready(function (Chart) {
        var H = window.hpwChart, C = H.C;

        var elTrend = document.getElementById('hpw-ptk-trend');
        if (elTrend) {
            var optsTrend = H.base();
            optsTrend.scales = H.axes();
            optsTrend.plugins.legend = H.legend();
            optsTrend.interaction = { mode: 'index', intersect: false };
            optsTrend.plugins.tooltip.displayColors = true;
            new Chart(elTrend, {
                type: 'line',
                data: {
                    labels: data.trend.labels,
                    datasets: [
                        { label: 'Opened', data: data.trend.opened, borderColor: C.blue,
                          backgroundColor: 'rgba(42,120,214,0.08)', fill: true, tension: 0.35,
                          borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, pointBackgroundColor: C.blue },
                        { label: 'Solved', data: data.trend.solved, borderColor: C.aqua,
                          backgroundColor: 'rgba(27,175,122,0.08)', fill: true, tension: 0.35,
                          borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, pointBackgroundColor: C.aqua }
                    ]
                },
                options: optsTrend
            });
        }

        var elStatus = document.getElementById('hpw-ptk-status');
        if (elStatus && data.status.length) {
            var fallback = [C.blue, C.aqua, C.yellow, C.violet, C.red, '#e87ba4', '#eb6834', '#008300'];
            var optsStatus = H.base();
            optsStatus.cutout = '68%';
            optsStatus.plugins.legend = H.legend();
            new Chart(elStatus, {
                type: 'doughnut',
                data: {
                    labels: data.status.map(function (r) { return r.name; }),
                    datasets: [{
                        data: data.status.map(function (r) { return r.cnt; }),
                        backgroundColor: data.status.map(function (r, i) { return r.color || fallback[i % fallback.length]; }),
                        borderColor: C.surface, borderWidth: 2, hoverOffset: 6
                    }]
                },
                options: optsStatus
            });
        }
    });
})();
</script>
