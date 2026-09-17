<?php
// ─── KPI Calculations ───
$total = count($report_data);
$now = time();
$notified = 0;
$overdue = 0;
$upcoming_7d = 0;
$staff_followups = [];

foreach ($report_data as $r) {
    if ($r['isnotified'])
        $notified++;
    $rd = strtotime($r['reminder_date']);
    if ($rd < $now && !$r['isnotified'])
        $overdue++;
    if ($rd > $now && $rd <= ($now + 7 * 86400))
        $upcoming_7d++;

    $sn = $r['staff_name'] ?: 'Unassigned';
    if (!isset($staff_followups[$sn]))
        $staff_followups[$sn] = ['total' => 0, 'notified' => 0, 'overdue' => 0];
    $staff_followups[$sn]['total']++;
    if ($r['isnotified'])
        $staff_followups[$sn]['notified']++;
    if ($rd < $now && !$r['isnotified'])
        $staff_followups[$sn]['overdue']++;
}

$notified_pct = $total > 0 ? round(($notified / $total) * 100, 1) : 0;
$pending = $total - $notified;
?>

<!-- KPI Cards -->
<div class="ccx-kpi-grid">
    <div class="ccx-kpi-card">
        <span class="ccx-kpi-val"><?php echo $total; ?></span>
        <span class="ccx-kpi-lbl">Total Follow-ups</span>
    </div>
    <div class="ccx-kpi-card kpi-green">
        <span class="ccx-kpi-val"><?php echo $notified_pct; ?>%</span>
        <span class="ccx-kpi-lbl">Notified</span>
        <span class="ccx-kpi-sub"><?php echo $notified; ?> of <?php echo $total; ?></span>
    </div>
    <div class="ccx-kpi-card kpi-red">
        <span class="ccx-kpi-val"><?php echo $overdue; ?></span>
        <span class="ccx-kpi-lbl">Overdue</span>
        <span class="ccx-kpi-sub"><?php echo $total > 0 ? round(($overdue / $total) * 100, 1) : 0; ?>% of total</span>
    </div>
    <div class="ccx-kpi-card kpi-amber">
        <span class="ccx-kpi-val"><?php echo $pending; ?></span>
        <span class="ccx-kpi-lbl">Pending</span>
    </div>
    <div class="ccx-kpi-card kpi-indigo">
        <span class="ccx-kpi-val"><?php echo $upcoming_7d; ?></span>
        <span class="ccx-kpi-lbl">Upcoming 7 Days</span>
    </div>
</div>

<!-- Staff-wise Summary -->
<?php if (count($staff_followups) > 0) { ?>
    <div class="ccx-mini-summary">
        <div class="mini-title"><i class="fa fa-users"></i> Staff-wise Follow-up Summary</div>
        <table>
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Total</th>
                    <th>Notified</th>
                    <th>Overdue</th>
                    <th>Completion %</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff_followups as $sname => $sd) {
                    $comp = $sd['total'] > 0 ? round(($sd['notified'] / $sd['total']) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><?php echo $sname; ?></td>
                        <td><?php echo $sd['total']; ?></td>
                        <td style="color:#059669;font-weight:600;"><?php echo $sd['notified']; ?></td>
                        <td style="color:#dc2626;font-weight:600;"><?php echo $sd['overdue']; ?></td>
                        <td>
                            <div class="ccx-pct-bar">
                                <div class="ccx-pct-track">
                                    <div class="ccx-pct-fill"
                                        style="width:<?php echo $comp; ?>%;background:<?php echo $comp >= 80 ? '#059669' : ($comp >= 50 ? '#d97706' : '#dc2626'); ?>;">
                                    </div>
                                </div>
                                <span class="ccx-pct-text"><?php echo $comp; ?>%</span>
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
            <th>Lead</th>
            <th>Phone</th>
            <th>Reminder</th>
            <th>Date</th>
            <th>Assigned To</th>
            <th>Created By</th>
            <th>Lead Status</th>
            <th>Reminder Status</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1;
        foreach ($report_data as $r) {
            $rd = strtotime($r['reminder_date']);
            if ($r['isnotified']) {
                $badge = '<span class="ccx-badge-done">✓ Completed</span>';
            } elseif ($rd < $now) {
                $badge = '<span class="ccx-badge-overdue">⚠ Overdue</span>';
            } else {
                $badge = '<span class="ccx-badge-upcoming">Upcoming</span>';
            }
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo $r['lead_name']; ?></td>
                <td><?php echo $r['phonenumber']; ?></td>
                <td><?php echo $r['reminder_desc']; ?></td>
                <td><?php echo date('d M Y H:i', strtotime($r['reminder_date'])); ?></td>
                <td><?php echo $r['staff_name'] ?: '-'; ?></td>
                <td><?php echo $r['created_by'] ?: '-'; ?></td>
                <td><?php echo $r['lead_status'] ?: '-'; ?></td>
                <td><?php echo $badge; ?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>