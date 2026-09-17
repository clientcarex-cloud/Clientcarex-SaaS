<?php
// ─── KPI Calculations ───
$total_leads = count($report_data);
$tat_values = [];
$no_call = 0;
$within_30 = 0;
$within_60 = 0;
$staff_tat = []; // staff_name => [sum_tat, count, within_30]

foreach ($report_data as $r) {
    $sn = $r['staff_name'] ?: 'Unassigned';
    if (!isset($staff_tat[$sn]))
        $staff_tat[$sn] = ['sum' => 0, 'count' => 0, 'total' => 0, 'within_30' => 0, 'no_call' => 0];
    $staff_tat[$sn]['total']++;

    if ($r['tat_minutes'] === null || $r['first_call'] === null) {
        $no_call++;
        $staff_tat[$sn]['no_call']++;
    } else {
        $tat_values[] = (int) $r['tat_minutes'];
        $staff_tat[$sn]['sum'] += (int) $r['tat_minutes'];
        $staff_tat[$sn]['count']++;
        if ($r['tat_minutes'] <= 30) {
            $within_30++;
            $staff_tat[$sn]['within_30']++;
        }
        if ($r['tat_minutes'] <= 60) {
            $within_60++;
        }
    }
}

$called_count = count($tat_values);
$avg_tat = $called_count > 0 ? round(array_sum($tat_values) / $called_count) : 0;
sort($tat_values);
$median_tat = 0;
if ($called_count > 0) {
    $mid = floor($called_count / 2);
    $median_tat = ($called_count % 2 === 0) ? round(($tat_values[$mid - 1] + $tat_values[$mid]) / 2) : $tat_values[$mid];
}
$sla_30_pct = $called_count > 0 ? round(($within_30 / $called_count) * 100, 1) : 0;
$sla_60_pct = $called_count > 0 ? round(($within_60 / $called_count) * 100, 1) : 0;

// Format minutes helper
function _fmt_tat_mins($m)
{
    if ($m < 60)
        return $m . ' min';
    $h = floor($m / 60);
    $rm = $m % 60;
    if ($h >= 24) {
        $d = floor($h / 24);
        $h = $h % 24;
        return $d . 'd ' . $h . 'h';
    }
    return $h . 'h ' . $rm . 'm';
}
?>

<!-- KPI Cards -->
<div class="ccx-kpi-grid">
    <div class="ccx-kpi-card">
        <span class="ccx-kpi-val"><?php echo _fmt_tat_mins($avg_tat); ?></span>
        <span class="ccx-kpi-lbl">Average TAT</span>
        <span class="ccx-kpi-sub">Mean turnaround time</span>
    </div>
    <div class="ccx-kpi-card kpi-purple">
        <span class="ccx-kpi-val"><?php echo _fmt_tat_mins($median_tat); ?></span>
        <span class="ccx-kpi-lbl">Median TAT</span>
    </div>
    <div class="ccx-kpi-card kpi-green">
        <span class="ccx-kpi-val"><?php echo $sla_30_pct; ?>%</span>
        <span class="ccx-kpi-lbl">Within 30 Min</span>
        <span class="ccx-kpi-sub"><?php echo $within_30; ?> of <?php echo $called_count; ?> calls</span>
    </div>
    <div class="ccx-kpi-card kpi-amber">
        <span class="ccx-kpi-val"><?php echo $sla_60_pct; ?>%</span>
        <span class="ccx-kpi-lbl">Within 1 Hour</span>
        <span class="ccx-kpi-sub"><?php echo $within_60; ?> of <?php echo $called_count; ?> calls</span>
    </div>
    <div class="ccx-kpi-card kpi-red">
        <span class="ccx-kpi-val"><?php echo $no_call; ?></span>
        <span class="ccx-kpi-lbl">No Call Yet</span>
        <span class="ccx-kpi-sub"><?php echo $total_leads > 0 ? round(($no_call / $total_leads) * 100, 1) : 0; ?>% of
            leads</span>
    </div>
</div>

<!-- Staff-wise TAT Summary -->
<?php if (count($staff_tat) > 0) { ?>
    <div class="ccx-mini-summary">
        <div class="mini-title"><i class="fa fa-chart-line"></i> Staff-wise TAT Summary</div>
        <table>
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Total Leads</th>
                    <th>Called</th>
                    <th>No Call</th>
                    <th>Avg TAT</th>
                    <th>SLA ≤30m</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff_tat as $sname => $sd) {
                    $s_avg = $sd['count'] > 0 ? round($sd['sum'] / $sd['count']) : 0;
                    $s_sla = $sd['count'] > 0 ? round(($sd['within_30'] / $sd['count']) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><?php echo $sname; ?></td>
                        <td><?php echo $sd['total']; ?></td>
                        <td><?php echo $sd['count']; ?></td>
                        <td><?php echo $sd['no_call']; ?></td>
                        <td><strong><?php echo $sd['count'] > 0 ? _fmt_tat_mins($s_avg) : '-'; ?></strong></td>
                        <td>
                            <div class="ccx-pct-bar">
                                <div class="ccx-pct-track">
                                    <div class="ccx-pct-fill"
                                        style="width:<?php echo $s_sla; ?>%;background:<?php echo $s_sla >= 80 ? '#059669' : ($s_sla >= 50 ? '#d97706' : '#dc2626'); ?>;">
                                    </div>
                                </div>
                                <span class="ccx-pct-text"><?php echo $s_sla; ?>%</span>
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
            <th>Lead Name</th>
            <th>Phone</th>
            <th>Assigned To</th>
            <th>Lead Created</th>
            <th>First Call</th>
            <th>TAT</th>
            <th>SLA ≤30m</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1;
        foreach ($report_data as $r) {
            $tat = $r['tat_minutes'];
            if ($tat === null || $r['first_call'] === null) {
                $tat_display = '<span class="ccx-rpt-tat-na">No Call</span>';
                $sla = '<span class="ccx-sla-fail">—</span>';
            } elseif ($tat <= 30) {
                $tat_display = '<span class="ccx-rpt-tat-good">' . $tat . ' min</span>';
                $sla = '<span class="ccx-sla-pass">✓</span>';
            } elseif ($tat <= 120) {
                $h = floor($tat / 60);
                $m = $tat % 60;
                $tat_display = '<span class="ccx-rpt-tat-avg">' . ($h > 0 ? $h . 'h ' : '') . $m . 'min</span>';
                $sla = '<span class="ccx-sla-fail">✗</span>';
            } else {
                $h = floor($tat / 60);
                $m = $tat % 60;
                if ($h >= 24) {
                    $d = floor($h / 24);
                    $h = $h % 24;
                    $tat_display = '<span class="ccx-rpt-tat-bad">' . $d . 'd ' . $h . 'h ' . $m . 'm</span>';
                } else {
                    $tat_display = '<span class="ccx-rpt-tat-bad">' . $h . 'h ' . $m . 'm</span>';
                }
                $sla = '<span class="ccx-sla-fail">✗</span>';
            }
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo $r['id']; ?></td>
                <td><?php echo $r['name']; ?></td>
                <td><?php echo $r['phonenumber']; ?></td>
                <td><?php echo $r['staff_name'] ?: '<span style="color:#9ca3af;">Unassigned</span>'; ?></td>
                <td><?php echo date('d M Y H:i', strtotime($r['lead_created'])); ?></td>
                <td><?php echo $r['first_call'] ? date('d M Y H:i', strtotime($r['first_call'])) : '-'; ?></td>
                <td><?php echo $tat_display; ?></td>
                <td><?php echo $sla; ?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>