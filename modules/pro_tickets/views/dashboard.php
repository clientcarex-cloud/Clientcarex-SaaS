<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="ptk-wrap">

            <?php $active = 'dashboard'; include __DIR__ . '/_nav.php'; ?>

            <?php $kpi = $dashboard['kpi']; ?>

            <!-- ── KPI strip ── -->
            <div class="ptk-kpis">
                <a class="ptk-kpi" href="<?= admin_url('pro_tickets/tickets'); ?>">
                    <div class="ptk-kpi-value"><?= (int) $kpi['open']; ?></div>
                    <div class="ptk-kpi-label"><?= _l('pro_tickets_kpi_open'); ?></div>
                    <i class="fa-solid fa-inbox ptk-kpi-icon"></i>
                </a>
                <a class="ptk-kpi <?= $kpi['unassigned'] ? 'ptk-kpi-warn' : ''; ?>" href="<?= admin_url('pro_tickets/tickets?assigned=-1'); ?>">
                    <div class="ptk-kpi-value"><?= (int) $kpi['unassigned']; ?></div>
                    <div class="ptk-kpi-label"><?= _l('pro_tickets_kpi_unassigned'); ?></div>
                    <i class="fa-solid fa-user-slash ptk-kpi-icon"></i>
                </a>
                <a class="ptk-kpi <?= $kpi['overdue'] ? 'ptk-kpi-danger' : ''; ?>" href="<?= admin_url('pro_tickets/tickets?sla=breached'); ?>">
                    <div class="ptk-kpi-value"><?= (int) $kpi['overdue']; ?></div>
                    <div class="ptk-kpi-label"><?= _l('pro_tickets_kpi_overdue'); ?></div>
                    <i class="fa-solid fa-fire ptk-kpi-icon"></i>
                </a>
                <a class="ptk-kpi <?= $kpi['at_risk'] ? 'ptk-kpi-warn' : ''; ?>" href="<?= admin_url('pro_tickets/tickets?sla=at_risk'); ?>">
                    <div class="ptk-kpi-value"><?= (int) $kpi['at_risk']; ?></div>
                    <div class="ptk-kpi-label"><?= _l('pro_tickets_kpi_at_risk'); ?></div>
                    <i class="fa-solid fa-hourglass-half ptk-kpi-icon"></i>
                </a>
                <div class="ptk-kpi">
                    <div class="ptk-kpi-value"><?= (int) $kpi['solved_7d']; ?></div>
                    <div class="ptk-kpi-label"><?= _l('pro_tickets_kpi_solved_7d'); ?></div>
                    <i class="fa-solid fa-circle-check ptk-kpi-icon"></i>
                </div>
                <div class="ptk-kpi">
                    <div class="ptk-kpi-value"><?= html_escape($kpi['avg_frt']); ?></div>
                    <div class="ptk-kpi-label"><?= _l('pro_tickets_kpi_avg_frt'); ?> <span class="ptk-muted">(<?= _l('pro_tickets_last_30d'); ?>)</span></div>
                    <i class="fa-solid fa-bolt ptk-kpi-icon"></i>
                </div>
                <div class="ptk-kpi">
                    <div class="ptk-kpi-value"><?= html_escape($kpi['avg_res']); ?></div>
                    <div class="ptk-kpi-label"><?= _l('pro_tickets_kpi_avg_res'); ?> <span class="ptk-muted">(<?= _l('pro_tickets_last_30d'); ?>)</span></div>
                    <i class="fa-solid fa-flag-checkered ptk-kpi-icon"></i>
                </div>
                <div class="ptk-kpi <?= $kpi['sla_pct'] !== null && $kpi['sla_pct'] < 90 ? 'ptk-kpi-warn' : ''; ?>">
                    <div class="ptk-kpi-value"><?= $kpi['sla_pct'] !== null ? (int) $kpi['sla_pct'] . '%' : '—'; ?></div>
                    <div class="ptk-kpi-label"><?= _l('pro_tickets_kpi_sla_pct'); ?> <span class="ptk-muted">(<?= _l('pro_tickets_last_30d'); ?>)</span></div>
                    <i class="fa-solid fa-shield-halved ptk-kpi-icon"></i>
                </div>
                <?php $csat = $dashboard['csat']; ?>
                <a class="ptk-kpi <?= $csat['avg'] !== null ? ($csat['avg'] >= 4 ? 'ptk-kpi-good' : ($csat['avg'] < 3 ? 'ptk-kpi-danger' : 'ptk-kpi-warn')) : ''; ?>" href="<?= admin_url('pro_tickets/tickets?feedback=rated'); ?>">
                    <div class="ptk-kpi-value"><?= $csat['avg'] !== null ? html_escape(number_format($csat['avg'], 1)) . '<span class="ptk-kpi-unit">/5</span>' : '—'; ?></div>
                    <div class="ptk-kpi-label"><?= _l('pro_tickets_kpi_csat'); ?> <span class="ptk-muted">(<?= _l('pro_tickets_last_30d'); ?>)</span></div>
                    <i class="fa-solid fa-face-smile ptk-kpi-icon"></i>
                </a>
                <a class="ptk-kpi <?= $csat['pct'] !== null && $csat['pct'] < 70 ? 'ptk-kpi-warn' : ''; ?>" href="<?= admin_url('pro_tickets/tickets?feedback=positive'); ?>">
                    <div class="ptk-kpi-value"><?= $csat['pct'] !== null ? (int) $csat['pct'] . '%' : '—'; ?></div>
                    <div class="ptk-kpi-label"><?= _l('pro_tickets_kpi_csat_positive'); ?> <span class="ptk-muted">(<?= (int) $csat['responses']; ?> <?= _l('pro_tickets_kpi_csat_responses'); ?>)</span></div>
                    <i class="fa-solid fa-thumbs-up ptk-kpi-icon"></i>
                </a>
                <a class="ptk-kpi <?= $csat['negative'] ? 'ptk-kpi-danger' : ''; ?>" href="<?= admin_url('pro_tickets/tickets?feedback=negative'); ?>">
                    <div class="ptk-kpi-value"><?= (int) $csat['negative']; ?></div>
                    <div class="ptk-kpi-label"><?= _l('pro_tickets_kpi_csat_negative'); ?> <span class="ptk-muted">(<?= _l('pro_tickets_last_30d'); ?>)</span></div>
                    <i class="fa-solid fa-face-frown ptk-kpi-icon"></i>
                </a>
                <a class="ptk-kpi" href="<?= admin_url('pro_tickets/tickets?feedback=unrated'); ?>">
                    <div class="ptk-kpi-value"><?= $csat['resp_rate'] !== null ? (int) $csat['resp_rate'] . '%' : '—'; ?></div>
                    <div class="ptk-kpi-label"><?= _l('pro_tickets_kpi_csat_resp_rate'); ?> <span class="ptk-muted">(<?= _l('pro_tickets_last_30d'); ?>)</span></div>
                    <i class="fa-solid fa-comment-dots ptk-kpi-icon"></i>
                </a>
            </div>

            <!-- ── Charts row 1: trend + status ── -->
            <div class="ptk-grid ptk-grid-2-1">
                <div class="ptk-card">
                    <h4 class="ptk-card-title"><i class="fa-solid fa-chart-line"></i> <?= _l('pro_tickets_trend_title'); ?></h4>
                    <div class="ptk-chart-box"><canvas id="ptk-chart-trend"></canvas></div>
                </div>
                <div class="ptk-card">
                    <h4 class="ptk-card-title"><i class="fa-solid fa-chart-pie"></i> <?= _l('pro_tickets_by_status'); ?></h4>
                    <div class="ptk-chart-box"><canvas id="ptk-chart-status"></canvas></div>
                </div>
            </div>

            <!-- ── Charts row 2: priority + department ── -->
            <div class="ptk-grid ptk-grid-1-1">
                <div class="ptk-card">
                    <h4 class="ptk-card-title"><i class="fa-solid fa-layer-group"></i> <?= _l('pro_tickets_by_priority'); ?></h4>
                    <div class="ptk-chart-box"><canvas id="ptk-chart-priority"></canvas></div>
                </div>
                <div class="ptk-card">
                    <h4 class="ptk-card-title"><i class="fa-solid fa-building"></i> <?= _l('pro_tickets_by_department'); ?></h4>
                    <div class="ptk-chart-box"><canvas id="ptk-chart-department"></canvas></div>
                </div>
            </div>

            <!-- ── Charts row 3: CSAT distribution + latest ratings ── -->
            <div class="ptk-grid ptk-grid-1-1">
                <div class="ptk-card">
                    <h4 class="ptk-card-title"><i class="fa-solid fa-star"></i> <?= _l('pro_tickets_csat_dist'); ?> <span class="ptk-muted ptk-small">(<?= _l('pro_tickets_last_30d'); ?>)</span></h4>
                    <?php if ((int) $csat['responses'] === 0): ?>
                        <p class="ptk-muted"><?= _l('pro_tickets_csat_none'); ?></p>
                    <?php else: ?>
                        <div class="ptk-chart-box"><canvas id="ptk-chart-csat"></canvas></div>
                    <?php endif; ?>
                </div>
                <div class="ptk-card">
                    <h4 class="ptk-card-title"><i class="fa-solid fa-comments"></i> <?= _l('pro_tickets_csat_recent'); ?></h4>
                    <?php if (empty($csat['recent'])): ?>
                        <p class="ptk-muted"><?= _l('pro_tickets_csat_none'); ?></p>
                    <?php else: ?>
                        <div class="ptk-feed">
                            <?php foreach ($csat['recent'] as $fb): ?>
                                <a class="ptk-feed-item" href="<?= admin_url('pro_tickets/ticket/' . (int) $fb->ticket_id); ?>">
                                    <span class="ptk-feed-body">
                                        <span class="ptk-feed-text">
                                            <?= pro_tickets_rating_stars($fb->rating); ?>
                                            <?php if (trim((string) $fb->comment) !== ''): ?>
                                                <span class="ptk-csat-comment"><?= html_escape($fb->comment); ?></span>
                                            <?php else: ?>
                                                <span class="ptk-muted"><?= html_escape(pro_tickets_rating_label($fb->rating)); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="ptk-feed-meta">#<?= (int) $fb->ticket_id; ?> · <?= html_escape($fb->subject); ?><?php if (trim((string) $fb->agent_name) !== ''): ?> · <?= html_escape($fb->agent_name); ?><?php endif; ?> · <?= html_escape(time_ago($fb->created_at)); ?></span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Agents + activity feed ── -->
            <div class="ptk-grid ptk-grid-1-1">
                <div class="ptk-card">
                    <h4 class="ptk-card-title"><i class="fa-solid fa-user-group"></i> <?= _l('pro_tickets_agents'); ?></h4>
                    <?php if (empty($dashboard['agents'])): ?>
                        <p class="ptk-muted"><?= _l('pro_tickets_no_tickets'); ?></p>
                    <?php else: ?>
                        <table class="ptk-table">
                            <thead>
                                <tr>
                                    <th><?= _l('pro_tickets_agent'); ?></th>
                                    <th class="text-right"><?= _l('pro_tickets_agent_open'); ?></th>
                                    <th class="text-right"><?= _l('pro_tickets_agent_solved_30d'); ?></th>
                                    <th class="text-right"><?= _l('pro_tickets_agent_avg_frt'); ?></th>
                                    <th class="text-right"><?= _l('pro_tickets_agent_csat'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dashboard['agents'] as $agent): ?>
                                    <tr>
                                        <td><?= html_escape($agent->firstname . ' ' . $agent->lastname); ?></td>
                                        <td class="text-right"><span class="ptk-pill"><?= (int) $agent->open_cnt; ?></span></td>
                                        <td class="text-right"><?= (int) $agent->solved_30d; ?></td>
                                        <td class="text-right"><?= $agent->avg_frt !== null ? html_escape(pro_tickets_human_duration($agent->avg_frt)) : '—'; ?></td>
                                        <td class="text-right">
                                            <?php if ((int) $agent->csat_cnt > 0): ?>
                                                <span class="ptk-csat-score ptk-stars-<?= pro_tickets_rating_tone(round($agent->csat_avg)); ?>">
                                                    <i class="fa-solid fa-star"></i> <?= html_escape(number_format((float) $agent->csat_avg, 1)); ?>
                                                    <span class="ptk-muted ptk-small">(<?= (int) $agent->csat_cnt; ?>)</span>
                                                </span>
                                            <?php else: ?>
                                                <span class="ptk-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <div class="ptk-card">
                    <h4 class="ptk-card-title"><i class="fa-solid fa-wave-square"></i> <?= _l('pro_tickets_recent_activity'); ?></h4>
                    <div class="ptk-feed">
                        <?php if (empty($dashboard['feed'])): ?>
                            <p class="ptk-muted"><?= _l('pro_tickets_no_tickets'); ?></p>
                        <?php endif; ?>
                        <?php foreach ($dashboard['feed'] as $item): ?>
                            <a class="ptk-feed-item" href="<?= admin_url('pro_tickets/ticket/' . (int) $item->ticket_id); ?>">
                                <span class="ptk-feed-dot ptk-act-<?= html_escape($item->type); ?>"></span>
                                <span class="ptk-feed-body">
                                    <span class="ptk-feed-text">
                                        <?= html_escape(pro_tickets_activity_line($item)); ?>
                                    </span>
                                    <span class="ptk-feed-meta">#<?= (int) $item->ticket_id; ?> · <?= html_escape($item->subject); ?> · <?= html_escape(time_ago($item->created_at)); ?></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="<?= base_url('assets/plugins/Chart.js/Chart.min.js'); ?>"></script>
<script>
    window.PTK_DASH = <?= json_encode([
        'trend'         => $dashboard['trend'],
        'by_status'     => array_map(function ($r) {
            return ['name' => $r->name, 'color' => $r->statuscolor, 'cnt' => (int) $r->cnt];
        }, $dashboard['by_status']),
        'by_priority'   => array_map(function ($r) {
            return ['name' => $r->name, 'cnt' => (int) $r->cnt];
        }, $dashboard['by_priority']),
        'by_department' => array_map(function ($r) {
            return ['name' => $r->name, 'cnt' => (int) $r->cnt];
        }, $dashboard['by_department']),
        'csat'          => [
            'labels' => [
                _l('pro_tickets_fb_r1'), _l('pro_tickets_fb_r2'), _l('pro_tickets_fb_r3'),
                _l('pro_tickets_fb_r4'), _l('pro_tickets_fb_r5'),
            ],
            'counts' => array_values($dashboard['csat']['dist']),
        ],
    ]); ?>;
</script>
<?php init_tail(); ?>
</body>
</html>
