<?php
// ─── KPI Calculations ───
$total_leads = 0;
$total_contacted = 0;
$total_not_contacted = 0;
$total_junk = 0;
$total_lost = 0;
$staff_count = count($report_data);

foreach ($report_data as $r) {
    $total_leads += (int) ($r['total_leads'] ?? 0);
    $total_contacted += (int) ($r['contacted'] ?? 0);
    $total_not_contacted += (int) ($r['not_contacted'] ?? 0);
    $total_junk += (int) ($r['junk'] ?? 0);
    $total_lost += (int) ($r['lost'] ?? 0);
}

$contact_rate = $total_leads > 0 ? round(($total_contacted / $total_leads) * 100, 1) : 0;
$junk_rate = $total_leads > 0 ? round(($total_junk / $total_leads) * 100, 1) : 0;
$lost_rate = $total_leads > 0 ? round(($total_lost / $total_leads) * 100, 1) : 0;
$avg_per_staff = $staff_count > 0 ? round($total_leads / $staff_count, 1) : 0;
?>

<!-- KPI Cards -->
<div class="ccx-kpi-grid">
    <div class="ccx-kpi-card">
        <span class="ccx-kpi-val"><?php echo $total_leads; ?></span>
        <span class="ccx-kpi-lbl">Total Leads</span>
        <span class="ccx-kpi-sub">Across <?php echo $staff_count; ?> staff (<?php echo $avg_per_staff; ?> avg)</span>
    </div>
    <div class="ccx-kpi-card kpi-green">
        <span class="ccx-kpi-val"><?php echo $contact_rate; ?>%</span>
        <span class="ccx-kpi-lbl">Contact Rate</span>
        <span class="ccx-kpi-sub"><?php echo $total_contacted; ?> contacted</span>
    </div>
    <div class="ccx-kpi-card kpi-red">
        <span class="ccx-kpi-val"><?php echo $total_not_contacted; ?></span>
        <span class="ccx-kpi-lbl">Not Contacted</span>
        <span
            class="ccx-kpi-sub"><?php echo $total_leads > 0 ? round(($total_not_contacted / $total_leads) * 100, 1) : 0; ?>%
            of total</span>
    </div>
    <div class="ccx-kpi-card kpi-amber">
        <span class="ccx-kpi-val"><?php echo $junk_rate; ?>%</span>
        <span class="ccx-kpi-lbl">Junk Rate</span>
        <span class="ccx-kpi-sub"><?php echo $total_junk; ?> junk leads</span>
    </div>
    <div class="ccx-kpi-card kpi-pink">
        <span class="ccx-kpi-val"><?php echo $lost_rate; ?>%</span>
        <span class="ccx-kpi-lbl">Lost Rate</span>
        <span class="ccx-kpi-sub"><?php echo $total_lost; ?> lost leads</span>
    </div>
</div>

<!-- Data Table -->
<table class="ccx-rpt-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Staff Name</th>
            <th>Total Leads</th>
            <th>Contacted</th>
            <th>Contact Rate</th>
            <th>Not Contacted</th>
            <th>Junk</th>
            <th>Junk %</th>
            <th>Lost</th>
            <th>Lost %</th>
            <th>Status Breakdown</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1;
        foreach ($report_data as $r) {
            $r_total = (int) ($r['total_leads'] ?? 0);
            $r_contacted = (int) ($r['contacted'] ?? 0);
            $r_nc = (int) ($r['not_contacted'] ?? 0);
            $r_junk = (int) ($r['junk'] ?? 0);
            $r_lost = (int) ($r['lost'] ?? 0);
            $r_contact_rate = $r_total > 0 ? round(($r_contacted / $r_total) * 100, 1) : 0;
            $r_junk_pct = $r_total > 0 ? round(($r_junk / $r_total) * 100, 1) : 0;
            $r_lost_pct = $r_total > 0 ? round(($r_lost / $r_total) * 100, 1) : 0;
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo $r['staff_name'] ?? '-'; ?></td>
                <td><strong><?php echo $r_total; ?></strong></td>
                <td style="color:#059669;"><?php echo $r_contacted; ?></td>
                <td>
                    <div class="ccx-pct-bar">
                        <div class="ccx-pct-track">
                            <div class="ccx-pct-fill"
                                style="width:<?php echo $r_contact_rate; ?>%;background:<?php echo $r_contact_rate >= 70 ? '#059669' : ($r_contact_rate >= 40 ? '#d97706' : '#dc2626'); ?>;">
                            </div>
                        </div>
                        <span class="ccx-pct-text"><?php echo $r_contact_rate; ?>%</span>
                    </div>
                </td>
                <td style="color:#dc2626;"><?php echo $r_nc; ?></td>
                <td><?php echo $r_junk; ?></td>
                <td><span
                        style="color:<?php echo $r_junk_pct > 30 ? '#dc2626' : '#6b7280'; ?>;"><?php echo $r_junk_pct; ?>%</span>
                </td>
                <td><?php echo $r_lost; ?></td>
                <td><span
                        style="color:<?php echo $r_lost_pct > 20 ? '#dc2626' : '#6b7280'; ?>;"><?php echo $r_lost_pct; ?>%</span>
                </td>
                <td>
                    <?php if (isset($r['statuses']) && is_array($r['statuses']) && count($r['statuses']) > 0) { ?>
                        <div class="ccx-rpt-status-bar">
                            <?php foreach ($r['statuses'] as $st) { ?>
                                <span class="ccx-rpt-status-chip"
                                    style="background:<?php echo isset($st['color']) && $st['color'] ? $st['color'] : '#6b7280'; ?>;">
                                    <?php echo isset($st['name']) ? $st['name'] : '?'; ?>:
                                    <?php echo isset($st['count']) ? $st['count'] : 0; ?>
                                </span>
                            <?php } ?>
                        </div>
                    <?php } else {
                        echo '<span style="color:#9ca3af;">—</span>';
                    } ?>
                </td>
            </tr>
        <?php } ?>
        <?php if ($staff_count > 0) { ?>
            <tr class="totals-row">
                <td colspan="2" style="text-align:right;">Grand Total</td>
                <td><strong><?php echo $total_leads; ?></strong></td>
                <td><?php echo $total_contacted; ?></td>
                <td><strong><?php echo $contact_rate; ?>%</strong></td>
                <td><?php echo $total_not_contacted; ?></td>
                <td><?php echo $total_junk; ?></td>
                <td><?php echo $junk_rate; ?>%</td>
                <td><?php echo $total_lost; ?></td>
                <td><?php echo $lost_rate; ?>%</td>
                <td></td>
            </tr>
        <?php } ?>
    </tbody>
</table>