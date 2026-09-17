<?php
// ─── KPI Calculations ───
$total_missed = count($report_data);
$now = time();

// Age calculations
$ages = [];
$unassigned = 0;
$staff_missed = [];
foreach ($report_data as $r) {
    if (!empty($r['dateadded'])) {
        $days = floor(($now - strtotime($r['dateadded'])) / 86400);
        $ages[] = $days;
    }
    if (empty($r['staff_name'])) {
        $unassigned++;
    }
    $sn = !empty($r['staff_name']) ? $r['staff_name'] : 'Unassigned';
    if (!isset($staff_missed[$sn]))
        $staff_missed[$sn] = 0;
    $staff_missed[$sn]++;
}
arsort($staff_missed);

$avg_age = count($ages) > 0 ? round(array_sum($ages) / count($ages), 1) : 0;
$max_age = count($ages) > 0 ? max($ages) : 0;
?>

<!-- KPI Cards -->
<div class="ccx-kpi-grid">
    <div class="ccx-kpi-card kpi-red">
        <span class="ccx-kpi-val"><?php echo $total_missed; ?></span>
        <span class="ccx-kpi-lbl">Total Missed</span>
    </div>
    <div class="ccx-kpi-card kpi-amber">
        <span class="ccx-kpi-val"><?php echo $avg_age; ?> <small style="font-size:13px;">days</small></span>
        <span class="ccx-kpi-lbl">Avg Age</span>
        <span class="ccx-kpi-sub">Since lead created</span>
    </div>
    <div class="ccx-kpi-card kpi-red">
        <span class="ccx-kpi-val"><?php echo $max_age; ?> <small style="font-size:13px;">days</small></span>
        <span class="ccx-kpi-lbl">Oldest Uncontacted</span>
    </div>
    <div class="ccx-kpi-card kpi-purple">
        <span class="ccx-kpi-val"><?php echo $unassigned; ?></span>
        <span class="ccx-kpi-lbl">Unassigned</span>
        <span class="ccx-kpi-sub"><?php echo $total_missed > 0 ? round(($unassigned / $total_missed) * 100, 1) : 0; ?>%
            of total</span>
    </div>
</div>

<!-- Staff-wise Missed Summary -->
<?php if (count($staff_missed) > 0) { ?>
    <div class="ccx-mini-summary">
        <div class="mini-title"><i class="fa fa-users"></i> Staff-wise Missed Breakdown</div>
        <table>
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Missed Count</th>
                    <th>% of Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff_missed as $sname => $cnt) {
                    $pct = $total_missed > 0 ? round(($cnt / $total_missed) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><?php echo $sname; ?></td>
                        <td><strong><?php echo $cnt; ?></strong></td>
                        <td>
                            <div class="ccx-pct-bar">
                                <div class="ccx-pct-track">
                                    <div class="ccx-pct-fill" style="width:<?php echo $pct; ?>%;background:#dc2626;"></div>
                                </div>
                                <span class="ccx-pct-text"><?php echo $pct; ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<!-- Data Table -->
<table class="ccx-rpt-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Lead ID</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Assigned To</th>
            <th>Status</th>
            <th>Created</th>
            <th>Age (Days)</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1;
        foreach ($report_data as $r) {
            $days = !empty($r['dateadded']) ? floor(($now - strtotime($r['dateadded'])) / 86400) : 0;
            if ($days <= 3) {
                $age_class = 'ccx-age-green';
            } elseif ($days <= 7) {
                $age_class = 'ccx-age-amber';
            } else {
                $age_class = 'ccx-age-red';
            }
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo $r['id']; ?></td>
                <td><?php echo $r['name'] ?: '-'; ?></td>
                <td><?php echo $r['phonenumber'] ?: '-'; ?></td>
                <td><?php echo $r['email'] ?: '-'; ?></td>
                <td><?php echo isset($r['staff_name']) && $r['staff_name'] ? $r['staff_name'] : '<span style="color:#9ca3af;">Unassigned</span>'; ?>
                </td>
                <td>
                    <?php if (isset($r['status_name']) && $r['status_name']) { ?>
                        <span class="status-badge"
                            style="background:<?php echo isset($r['status_color']) && $r['status_color'] ? $r['status_color'] : '#6b7280'; ?>;">
                            <?php echo $r['status_name']; ?>
                        </span>
                    <?php } else {
                        echo '-';
                    } ?>
                </td>
                <td><?php echo isset($r['dateadded']) && $r['dateadded'] ? date('d M Y H:i', strtotime($r['dateadded'])) : '-'; ?>
                </td>
                <td><span class="<?php echo $age_class; ?>"><?php echo $days; ?>d</span></td>
            </tr>
        <?php } ?>
    </tbody>
</table>