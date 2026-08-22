<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php $this->load->view('todo/_hpw_base'); ?>

<!-- ── Todo dashboard widget ── -->
<div class="col-md-12 hpw-col">
    <div class="hpw-card">
        <div class="hpw-head">
            <span class="hpw-ico" style="background:linear-gradient(135deg,#6366f1,#4f46e5);"><i class="fa-solid fa-list-check"></i></span>
            <h4 class="hpw-title">Todo <span class="hpw-sub">My Tasks</span></h4>
            <div class="hpw-head-badges">
                <?php if ((int) $stats['overdue'] > 0): ?>
                    <span class="hpw-badge hpw-badge--crit"><i class="fa fa-triangle-exclamation"></i> <?php echo (int) $stats['overdue']; ?> overdue</span>
                <?php endif; ?>
                <?php if ((int) $stats['due_today'] > 0): ?>
                    <span class="hpw-badge hpw-badge--warn"><i class="fa fa-clock"></i> <?php echo (int) $stats['due_today']; ?> due today</span>
                <?php endif; ?>
                <?php if ((int) $stats['overdue'] === 0 && (int) $stats['due_today'] === 0): ?>
                    <span class="hpw-badge hpw-badge--ok"><i class="fa fa-check"></i> On track</span>
                <?php endif; ?>
            </div>
            <a class="hpw-open" href="<?php echo admin_url('todo'); ?>">Open Todo <i class="fa fa-arrow-right"></i></a>
        </div>
        <div class="hpw-body">
            <div class="hpw-kpis">
                <a class="hpw-kpi hpw-kpi--crit" href="<?php echo admin_url('todo'); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) $stats['overdue']; ?></div>
                    <div class="hpw-kpi-lbl">Overdue</div>
                </a>
                <a class="hpw-kpi hpw-kpi--warn" href="<?php echo admin_url('todo'); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) $stats['due_today']; ?></div>
                    <div class="hpw-kpi-lbl">Due Today</div>
                </a>
                <a class="hpw-kpi" href="<?php echo admin_url('todo'); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) $stats['pending']; ?></div>
                    <div class="hpw-kpi-lbl">Pending</div>
                </a>
                <a class="hpw-kpi" href="<?php echo admin_url('todo'); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) $stats['in_progress']; ?></div>
                    <div class="hpw-kpi-lbl">In Progress</div>
                </a>
                <a class="hpw-kpi hpw-kpi--good" href="<?php echo admin_url('todo'); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) $stats['completed']; ?></div>
                    <div class="hpw-kpi-lbl">Completed</div>
                </a>
                <a class="hpw-kpi" href="<?php echo admin_url('todo'); ?>">
                    <div class="hpw-kpi-val"><?php echo (int) $stats['total']; ?></div>
                    <div class="hpw-kpi-lbl">Total Tasks</div>
                </a>
            </div>
            <div class="hpw-charts">
                <div class="hpw-chart">
                    <h5 class="hpw-sec-title">Task Status</h5>
                    <div class="hpw-canvas"><canvas id="hpw-todo-status"></canvas></div>
                </div>
                <div class="hpw-chart">
                    <h5 class="hpw-sec-title">Due Load — Next 7 Days</h5>
                    <div class="hpw-canvas"><canvas id="hpw-todo-due"></canvas></div>
                </div>
            </div>
            <div class="hpw-list">
                <h5 class="hpw-sec-title">Needs Attention</h5>
                <?php if (count($attention) === 0): ?>
                    <div class="hpw-empty"><i class="fa fa-circle-check"></i> All caught up — nothing overdue or urgent.</div>
                <?php else: ?>
                    <div class="hpw-rows">
                        <?php foreach ($attention as $t):
                            $meta   = [];
                            $chip   = null;
                            if (!empty($t->category_name)) {
                                $meta[] = html_escape($t->category_name);
                            }
                            if (!empty($t->due_date)) {
                                $days = (int) floor((strtotime(date('Y-m-d')) - strtotime($t->due_date)) / 86400);
                                if ($days > 0) {
                                    $chip   = ['crit', $days . 'd overdue'];
                                } elseif ($days === 0) {
                                    $chip   = ['warn', 'Due today'];
                                } else {
                                    $meta[] = 'Due ' . _d($t->due_date);
                                }
                            }
                            if (!$chip && (int) $t->priority === 4) {
                                $chip = ['crit', 'Urgent'];
                            } elseif (!$chip && (int) $t->priority === 3) {
                                $chip = ['warn', 'High'];
                            }
                        ?>
                        <a class="hpw-row" href="<?php echo admin_url('todo'); ?>">
                            <span class="hpw-dot" style="background:<?php echo html_escape($t->category_color ?: '#6366f1'); ?>;"></span>
                            <span class="hpw-row-main">
                                <span class="hpw-row-title"><?php echo html_escape($t->title); ?></span>
                                <?php if (!empty($meta)): ?><span class="hpw-row-meta"><?php echo implode(' · ', $meta); ?></span><?php endif; ?>
                            </span>
                            <?php if ($chip): ?><span class="hpw-chip hpw-chip--<?php echo $chip[0]; ?>"><?php echo $chip[1]; ?></span><?php endif; ?>
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
        'status' => [
            'pending'     => (int) $stats['pending'],
            'in_progress' => (int) $stats['in_progress'],
            'completed'   => (int) $stats['completed'],
        ],
        'due' => ['labels' => $due_labels, 'counts' => $due_counts],
    ]); ?>;

    window.hpwChart.ready(function (Chart) {
        var H = window.hpwChart, C = H.C;

        var elStatus = document.getElementById('hpw-todo-status');
        if (elStatus) {
            var optsStatus = H.base();
            optsStatus.cutout = '68%';
            optsStatus.plugins.legend = H.legend();
            new Chart(elStatus, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'In Progress', 'Completed'],
                    datasets: [{
                        data: [data.status.pending, data.status.in_progress, data.status.completed],
                        backgroundColor: [C.yellow, C.blue, C.aqua],
                        borderColor: C.surface, borderWidth: 2, hoverOffset: 6
                    }]
                },
                options: optsStatus
            });
        }

        var elDue = document.getElementById('hpw-todo-due');
        if (elDue) {
            var optsDue = H.base();
            optsDue.scales = H.axes();
            var colors = data.due.labels.map(function (l, i) { return i === 0 ? C.crit : C.blue; });
            new Chart(elDue, {
                type: 'bar',
                data: {
                    labels: data.due.labels,
                    datasets: [{
                        label: 'Open tasks due',
                        data: data.due.counts,
                        backgroundColor: colors,
                        borderRadius: { topLeft: 4, topRight: 4 },
                        barPercentage: 0.55, categoryPercentage: 0.75, maxBarThickness: 26
                    }]
                },
                options: optsDue
            });
        }
    });
})();
</script>
