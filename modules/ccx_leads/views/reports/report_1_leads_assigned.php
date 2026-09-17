<?php
// ─── KPI Calculations ───
$total_assigned = array_sum(array_column($report_data, 'total_assigned'));
$staff_count = count($report_data);
$avg_per_staff = $staff_count > 0 ? round($total_assigned / $staff_count, 1) : 0;
$max_row = !empty($report_data) ? $report_data[0] : null; // already sorted desc
$min_row = !empty($report_data) ? end($report_data) : null;
reset($report_data);
?>

<!-- KPI Cards -->
<div class="ccx-kpi-grid">
    <div class="ccx-kpi-card">
        <span class="ccx-kpi-val"><?php echo $total_assigned; ?></span>
        <span class="ccx-kpi-lbl">Total Leads Assigned</span>
    </div>
    <div class="ccx-kpi-card kpi-purple">
        <span class="ccx-kpi-val"><?php echo $staff_count; ?></span>
        <span class="ccx-kpi-lbl">Staff Members</span>
    </div>
    <div class="ccx-kpi-card kpi-amber">
        <span class="ccx-kpi-val"><?php echo $avg_per_staff; ?></span>
        <span class="ccx-kpi-lbl">Avg Leads / Staff</span>
    </div>
    <?php if ($max_row) { ?>
        <div class="ccx-kpi-card kpi-green">
            <span class="ccx-kpi-val"><?php echo $max_row['total_assigned']; ?></span>
            <span class="ccx-kpi-lbl">Top Performer</span>
            <span class="ccx-kpi-sub"><?php echo $max_row['staff_name']; ?></span>
        </div>
    <?php } ?>
    <?php if ($min_row && $staff_count > 1) { ?>
        <div class="ccx-kpi-card kpi-red">
            <span class="ccx-kpi-val"><?php echo $min_row['total_assigned']; ?></span>
            <span class="ccx-kpi-lbl">Lowest Assigned</span>
            <span class="ccx-kpi-sub"><?php echo $min_row['staff_name']; ?></span>
        </div>
    <?php } ?>
</div>

<!-- Data Table -->
<table class="ccx-rpt-table">
    <thead>
        <tr>
            <th>Rank</th>
            <th>Staff Name</th>
            <th>Leads Assigned</th>
            <th>% Share</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1;
        foreach ($report_data as $r) {
            $pct = $total_assigned > 0 ? round(($r['total_assigned'] / $total_assigned) * 100, 1) : 0;
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo $r['staff_name']; ?></td>
                <td><strong><?php echo $r['total_assigned']; ?></strong></td>
                <td>
                    <div class="ccx-pct-bar">
                        <div class="ccx-pct-track">
                            <div class="ccx-pct-fill" style="width:<?php echo $pct; ?>%;background:#3b82f6;"></div>
                        </div>
                        <span class="ccx-pct-text"><?php echo $pct; ?>%</span>
                    </div>
                </td>
            </tr>
        <?php } ?>
        <?php if ($staff_count > 0) { ?>
            <tr class="totals-row">
                <td colspan="2" style="text-align:right;">Grand Total</td>
                <td><strong><?php echo $total_assigned; ?></strong></td>
                <td>100%</td>
            </tr>
        <?php } ?>
    </tbody>
</table>