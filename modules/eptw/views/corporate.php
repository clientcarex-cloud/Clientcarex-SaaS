<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$cols = [
    'live'         => ['Open on site', ['view' => 'live']],
    'active'       => ['Work authorized', ['view' => 'active']],
    'issued'       => ['Issued, not started', ['view' => 'issued']],
    'on_hold'      => ['On hold', ['view' => 'on_hold']],
    'suspended'    => ['Suspended', ['view' => 'suspended']],
    'expired'      => ['Expired', ['view' => 'expired']],
    'pending'      => ['Awaiting approval', ['view' => 'pending']],
    'high_risk'    => ['High risk open', ['view' => 'high_risk']],
    'simops'       => ['SIMOPS', ['view' => 'simops']],
    'docs_pending' => ['Docs missing', ['view' => 'docs_pending']],
    'issued_month' => ['Issued this month', null],
    'closed_month' => ['Closed this month', null],
];
$bad   = ['expired', 'suspended', 'simops', 'docs_pending'];
$total = array_fill_keys(array_keys($cols), 0);
foreach ($rows as $r) { foreach ($cols as $k => $c) { $total[$k] += (int) $r->$k; } }
$chart_rows = array_values(array_filter($rows, function ($r) { return (int) $r->total > 0; }));
?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'corporate'; include __DIR__ . '/_nav.php'; ?>

            <div class="eptw-scope">
                <div class="eptw-scope-line" style="flex-basis:auto">
                    <span><i class="fa-solid fa-building"></i> <b>Corporate roll-up</b> — every project<?= eptw_scope() !== null ? ' in your scope' : ''; ?>, live state as of <b><?= date('d M Y, H:i', strtotime($refreshed)); ?></b>. Month figures from <?= date('1 M Y'); ?>.</span>
                </div>
                <span class="eptw-refresh"><a href="<?= admin_url('eptw/corporate'); ?>" class="eptw-btn eptw-btn-sm"><i class="fa-solid fa-rotate"></i> Refresh</a></span>
            </div>

            <div class="eptw-tiles">
                <?php foreach (['live' => 'info', 'active' => 'live', 'on_hold' => 'warn', 'suspended' => 'bad', 'expired' => 'bad', 'high_risk' => 'bad'] as $k => $cls) { ?>
                    <a href="<?= admin_url('eptw/register?' . http_build_query($cols[$k][1])); ?>" class="eptw-tile <?= $total[$k] ? $cls : ''; ?>">
                        <div class="eptw-tile-value"><?= (int) $total[$k]; ?></div>
                        <div class="eptw-tile-label"><?= $cols[$k][0]; ?></div>
                        <div class="eptw-tile-def">All projects</div>
                    </a>
                <?php } ?>
            </div>

            <div class="eptw-card">
                <div class="eptw-card-head"><h3><i class="fa-solid fa-table"></i> Projects compared</h3><div class="eptw-card-actions eptw-small eptw-muted">Click a project for its dashboard, a number for its permits</div></div>
                <div class="eptw-table-scroll">
                    <table class="eptw-table eptw-corp">
                        <thead><tr><th>Project</th><?php foreach ($cols as $c) { ?><th class="eptw-num"><?= $c[0]; ?></th><?php } ?></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r) { ?>
                            <tr>
                                <td><a href="<?= admin_url('eptw?project=' . $r->id); ?>" class="eptw-strong"><?= html_escape($r->name); ?></a> <span class="eptw-mono eptw-muted eptw-small"><?= html_escape($r->code); ?></span>
                                    <?= $r->active ? '' : '<span class="eptw-badge muted">inactive</span>'; ?><?php if ($r->client_name) { ?><div class="eptw-small eptw-muted"><?= html_escape($r->client_name); ?></div><?php } ?></td>
                                <?php foreach ($cols as $k => $c) { $n = (int) $r->$k; ?>
                                    <td class="eptw-num"><?php if ($n && $c[1]) { ?><a href="<?= admin_url('eptw/register?' . http_build_query(array_merge($c[1], ['project' => $r->id]))); ?>" class="<?= in_array($k, $bad, true) ? 'text-danger' : ''; ?>"><?= $n; ?></a><?php } else { ?><span class="<?= $n ? '' : 'eptw-muted'; ?>"><?= $n; ?></span><?php } ?></td>
                                <?php } ?>
                            </tr>
                        <?php } ?>
                        </tbody>
                        <tfoot><tr><td>All projects</td><?php foreach ($cols as $k => $c) { ?><td class="eptw-num"><?= (int) $total[$k]; ?></td><?php } ?></tr></tfoot>
                    </table>
                </div>
            </div>

            <?php if (count($chart_rows)) { ?>
                <div class="eptw-card">
                    <div class="eptw-card-head"><h3><i class="fa-solid fa-chart-column"></i> Open permits by project and state</h3></div>
                    <div class="eptw-card-body">
                        <div class="eptw-chart" style="height:<?= max(180, 50 + 38 * count($chart_rows)); ?>px"><canvas data-eptw-chart='<?= html_escape(json_encode([
                            'type'    => 'stackedBar', 'wrap' => true,
                            'labels'  => array_map(function ($r) { return $r->name; }, $chart_rows),
                            'series'  => [
                                ['label' => 'Work authorized', 'data' => array_map(function ($r) { return (int) $r->active; }, $chart_rows), 'color' => '#0284c7'],
                                ['label' => 'Issued, not started', 'data' => array_map(function ($r) { return (int) $r->issued; }, $chart_rows), 'color' => '#7c3aed'],
                                ['label' => 'On hold', 'data' => array_map(function ($r) { return (int) $r->on_hold; }, $chart_rows), 'color' => '#d97706'],
                                ['label' => 'Suspended', 'data' => array_map(function ($r) { return (int) $r->suspended; }, $chart_rows), 'color' => '#dc2626'],
                                ['label' => 'Expired', 'data' => array_map(function ($r) { return (int) $r->expired; }, $chart_rows), 'color' => '#7f1d1d'],
                            ],
                            'links'   => array_map(function ($r) { return admin_url('eptw?project=' . $r->id); }, $chart_rows),
                        ])); ?>'></canvas></div>
                    </div>
                </div>
            <?php } ?>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
