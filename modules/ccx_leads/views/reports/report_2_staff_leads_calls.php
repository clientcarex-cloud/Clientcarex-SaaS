<?php
// ─── KPI Calculations ───
$total_new = array_sum(array_column($report_data, 'new_leads'));
$total_calls = array_sum(array_column($report_data, 'call_logs'));
$total_activity = array_sum(array_column($report_data, 'total'));
$overall_ratio = $total_new > 0 ? round($total_calls / $total_new, 2) : 0;
$staff_count = count($report_data);

// Most active staff
$max_activity = 0;
$most_active = '-';
foreach ($report_data as $rd) {
    if ($rd['total'] > $max_activity) {
        $max_activity = $rd['total'];
        $most_active = $rd['staff_name'];
    }
}
?>

<!-- KPI Cards -->
<div class="ccx-kpi-grid">
    <div class="ccx-kpi-card">
        <span class="ccx-kpi-val"><?php echo $total_new; ?></span>
        <span class="ccx-kpi-lbl">Total New Leads</span>
    </div>
    <div class="ccx-kpi-card kpi-green">
        <span class="ccx-kpi-val"><?php echo $total_calls; ?></span>
        <span class="ccx-kpi-lbl">Total Call Logs</span>
    </div>
    <div class="ccx-kpi-card kpi-amber">
        <span class="ccx-kpi-val"><?php echo $overall_ratio; ?></span>
        <span class="ccx-kpi-lbl">Calls / Lead Ratio</span>
        <span class="ccx-kpi-sub">Overall average</span>
    </div>
    <div class="ccx-kpi-card kpi-purple">
        <span class="ccx-kpi-val"><?php echo $total_activity; ?></span>
        <span class="ccx-kpi-lbl">Total Activity</span>
    </div>
    <?php if ($staff_count > 0) { ?>
        <div class="ccx-kpi-card kpi-indigo">
            <span class="ccx-kpi-val"><?php echo $max_activity; ?></span>
            <span class="ccx-kpi-lbl">Most Active</span>
            <span class="ccx-kpi-sub"><?php echo $most_active; ?></span>
        </div>
    <?php } ?>
</div>

<!-- Data Table -->
<table class="ccx-rpt-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Staff Name</th>
            <th>New Leads</th>
            <th>% Leads</th>
            <th>Call Logs</th>
            <th>% Calls</th>
            <th>Calls/Lead</th>
            <th>Total Activity</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1;
        foreach ($report_data as $r) {
            $pct_leads = $total_new > 0 ? round(($r['new_leads'] / $total_new) * 100, 1) : 0;
            $pct_calls = $total_calls > 0 ? round(($r['call_logs'] / $total_calls) * 100, 1) : 0;
            $ratio = $r['new_leads'] > 0 ? round($r['call_logs'] / $r['new_leads'], 2) : ($r['call_logs'] > 0 ? '∞' : '0');
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo $r['staff_name']; ?></td>
                <td><?php echo $r['new_leads']; ?></td>
                <td>
                    <div class="ccx-pct-bar">
                        <div class="ccx-pct-track">
                            <div class="ccx-pct-fill" style="width:<?php echo $pct_leads; ?>%;background:#3b82f6;"></div>
                        </div>
                        <span class="ccx-pct-text"><?php echo $pct_leads; ?>%</span>
                    </div>
                </td>
                <td><?php echo $r['call_logs']; ?></td>
                <td>
                    <div class="ccx-pct-bar">
                        <div class="ccx-pct-track">
                            <div class="ccx-pct-fill" style="width:<?php echo $pct_calls; ?>%;background:#059669;"></div>
                        </div>
                        <span class="ccx-pct-text"><?php echo $pct_calls; ?>%</span>
                    </div>
                </td>
                <td><strong><?php echo $ratio; ?></strong></td>
                <td><strong><?php echo $r['total']; ?></strong></td>
            </tr>
        <?php } ?>
        <?php if ($staff_count > 0) { ?>
            <tr class="totals-row">
                <td colspan="2" style="text-align:right;">Grand Total</td>
                <td><?php echo $total_new; ?></td>
                <td>100%</td>
                <td><?php echo $total_calls; ?></td>
                <td>100%</td>
                <td><strong><?php echo $overall_ratio; ?></strong></td>
                <td><strong><?php echo $total_activity; ?></strong></td>
            </tr>
        <?php } ?>
    </tbody>
</table>