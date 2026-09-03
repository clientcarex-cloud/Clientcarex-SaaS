<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'reports'; include __DIR__ . '/_nav.php'; ?>

            <div class="eptw-card">
                <div class="eptw-card-head">
                    <form id="eptw-filters" method="get" action="<?= admin_url('eptw/reports'); ?>" class="eptw-filters" style="flex:1">
                        <select name="report" class="eptw-select" style="min-width:240px">
                            <?php foreach ($names as $k => $l) { ?><option value="<?= $k; ?>" <?= $report === $k ? 'selected' : ''; ?>><?= html_escape($l); ?></option><?php } ?>
                        </select>
                        <input type="date" name="from" class="eptw-input" value="<?= html_escape($from); ?>">
                        <input type="date" name="to" class="eptw-input" value="<?= html_escape($to); ?>">
                        <button type="submit" class="eptw-btn"><i class="fa fa-search"></i> Run</button>
                    </form>
                    <div class="eptw-card-actions">
                        <a href="<?= admin_url('eptw/reports?' . http_build_query(['report' => $report, 'from' => $from, 'to' => $to, 'export' => 1])); ?>" class="eptw-btn eptw-btn-sm"><i class="fa-solid fa-file-excel"></i> Export</a>
                        <button class="eptw-btn eptw-btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
                    </div>
                </div>

                <?php $rows = $result['rows']; ?>
                <?php if (!count($rows)) { ?>
                    <div class="eptw-empty"><i class="fa-solid fa-chart-column"></i><h4>Nothing to report</h4><p>No permits match this report and period.</p></div>
                <?php } elseif ($result['kind'] === 'list') { ?>
                    <div class="eptw-table-scroll"><table class="eptw-table">
                        <thead><tr><th>Permit</th><th>Type</th><th>Project / area</th><th>Contractor</th><th>Engineer</th><th>Window</th><th>Status</th><th>Risk</th><th class="eptw-num">Ext.</th></tr></thead>
                        <tbody><?php foreach ($rows as $r) { ?>
                            <tr>
                                <td><a href="<?= admin_url('eptw/view/' . $r->id); ?>" class="eptw-permit-no <?= $r->permit_no ? '' : 'draft'; ?>"><?= html_escape($r->permit_no ?: 'draft'); ?></a><div class="eptw-small eptw-muted"><?= html_escape(mb_substr($r->work_title, 0, 60)); ?></div></td>
                                <td class="eptw-small"><span class="eptw-type-dot" style="background:<?= html_escape($r->type_color); ?>;width:10px;height:10px;border-radius:3px;display:inline-block;vertical-align:middle"></span> <?= html_escape($r->type_name); ?></td>
                                <td class="eptw-small"><?= html_escape($r->project_name); ?><div class="eptw-muted"><?= html_escape($r->area_name); ?></div></td>
                                <td class="eptw-small"><?= html_escape($r->contractor_name ?: '—'); ?></td>
                                <td class="eptw-small"><?= html_escape($r->engineer_name); ?></td>
                                <td class="eptw-small" style="white-space:nowrap"><?= eptw_dt($r->start_at); ?><div class="eptw-muted">→ <?= eptw_dt($r->end_at); ?></div></td>
                                <td><?= eptw_status_badge($r->status); ?></td>
                                <td><?= eptw_risk_badge($r->risk_level); ?><?= $r->simops_flag ? ' <span class="eptw-badge bad">SIMOPS</span>' : ''; ?></td>
                                <td class="eptw-num"><?= (int) $r->extension_count; ?></td>
                            </tr>
                        <?php } ?></tbody></table></div>
                <?php } elseif ($result['kind'] === 'monthly') { ?>
                    <div class="eptw-card-body">
                        <div class="eptw-chart" style="height:220px"><canvas data-eptw-chart='<?= html_escape(json_encode(['type' => 'line', 'labels' => array_reverse(array_map(function ($r) { return date('M Y', strtotime($r->label . '-01')); }, $rows)), 'series' => [['label' => 'Created', 'data' => array_reverse(array_map(function ($r) { return (int) $r->total; }, $rows))], ['label' => 'Issued', 'data' => array_reverse(array_map(function ($r) { return (int) $r->issued; }, $rows))], ['label' => 'Closed', 'data' => array_reverse(array_map(function ($r) { return (int) $r->closed; }, $rows))]]])); ?>'></canvas></div>
                    </div>
                    <div class="eptw-table-scroll"><table class="eptw-table">
                        <thead><tr><th>Month</th><th class="eptw-num">Created</th><th class="eptw-num">Issued</th><th class="eptw-num">Closed</th><th class="eptw-num">Cancelled</th><th class="eptw-num">Suspended</th><th class="eptw-num">High risk</th><th class="eptw-num">Extensions</th><th class="eptw-num">SIMOPS</th></tr></thead>
                        <tbody><?php foreach ($rows as $r) { ?>
                            <tr><td class="eptw-strong"><?= date('F Y', strtotime($r->label . '-01')); ?></td><td class="eptw-num"><?= (int) $r->total; ?></td><td class="eptw-num"><?= (int) $r->issued; ?></td><td class="eptw-num"><?= (int) $r->closed; ?></td><td class="eptw-num"><?= (int) $r->cancelled; ?></td><td class="eptw-num"><?= (int) $r->suspended; ?></td><td class="eptw-num"><?= (int) $r->high_risk; ?></td><td class="eptw-num"><?= (int) $r->extensions; ?></td><td class="eptw-num"><?= (int) $r->simops; ?></td></tr>
                        <?php } ?></tbody></table></div>
                <?php } else { ?>
                    <div class="eptw-card-body">
                        <div class="eptw-chart" style="height:<?= max(160, 28 * count($rows)); ?>px"><canvas data-eptw-chart='<?= html_escape(json_encode(['type' => 'horizontalBar', 'labels' => array_map(function ($r) { return $r->label; }, $rows), 'data' => array_map(function ($r) { return (int) $r->total; }, $rows)])); ?>'></canvas></div>
                    </div>
                    <div class="eptw-table-scroll"><table class="eptw-table">
                        <thead><tr><th>Name</th><th class="eptw-num">Total</th><th class="eptw-num">Active</th><th class="eptw-num">Suspended</th><th class="eptw-num">Closed</th><th class="eptw-num">High risk</th><th class="eptw-num">Extensions</th><th class="eptw-num">SIMOPS</th></tr></thead>
                        <tbody><?php foreach ($rows as $r) { ?>
                            <tr><td class="eptw-strong"><?= html_escape($r->label); ?></td><td class="eptw-num"><?= (int) $r->total; ?></td><td class="eptw-num"><?= (int) $r->active; ?></td><td class="eptw-num"><?= (int) $r->suspended; ?></td><td class="eptw-num"><?= (int) $r->closed; ?></td><td class="eptw-num"><?= (int) $r->high_risk; ?></td><td class="eptw-num"><?= (int) $r->extensions; ?></td><td class="eptw-num"><?= (int) $r->simops; ?></td></tr>
                        <?php } ?></tbody></table></div>
                <?php } ?>
            </div>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
