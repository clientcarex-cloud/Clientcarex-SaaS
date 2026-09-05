<?php defined('BASEPATH') or exit('No direct script access allowed');

$tabs = [
    'attendance' => 'Attendance Summary',
    'leave'      => 'Leave Balance',
    'headcount'  => 'Headcount & Turnover',
];
if (has_permission('hr_payroll', '', 'view') || is_admin()) {
    $tabs['payroll'] = 'Payroll History';
}
$base_url = admin_url('hr/reports');
$currency = function_exists('get_base_currency') ? get_base_currency() : null;
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-8">
                                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default mright5" data-toggle="tooltip" title="Back to HR Dashboard"><i class="fa fa-arrow-left"></i></a>
                                <div class="btn-group">
                                    <?php foreach ($tabs as $key => $label) { ?>
                                        <a href="<?php echo $base_url . '?tab=' . $key . '&month=' . $month . '&year=' . $year; ?>"
                                           class="btn btn-<?php echo $tab === $key ? 'primary' : 'default'; ?>"><?php echo $label; ?></a>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php if (in_array($tab, ['attendance', 'leave'])) { ?>
                                <div class="col-md-4 text-right">
                                    <div style="display:inline-flex;gap:6px;">
                                        <?php if ($tab === 'attendance') { ?>
                                            <select id="rep_month" class="form-control" style="width:130px;">
                                                <?php for ($m = 1; $m <= 12; $m++) { ?>
                                                    <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                                                <?php } ?>
                                            </select>
                                        <?php } ?>
                                        <select id="rep_year" class="form-control" style="width:90px;">
                                            <?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 5; $y--) { ?>
                                                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>

                        <div class="clearfix"></div>
                        <hr class="hr-panel-heading" />

                        <?php if ($tab === 'attendance') { ?>
                            <h4 class="bold">Attendance Summary &mdash; <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h4>
                            <table class="table table-striped dt-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <?php foreach ($statuses as $st) { ?>
                                            <th class="text-center"><span class="label" style="background:<?php echo $st['color']; ?>;color:#fff;"><?php echo $st['short']; ?></span></th>
                                        <?php } ?>
                                        <th class="text-center">Late</th>
                                        <th class="text-center">Hours Worked</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employees as $emp) {
                                        $rows = $summary[$emp['staffid']] ?? [];
                                        $late = 0; $minutes = 0;
                                        foreach ($rows as $r) { $late += (int) $r['late_cnt']; $minutes += (int) $r['minutes']; } ?>
                                        <tr>
                                            <td><a href="<?php echo admin_url('hr/employee/' . $emp['staffid']); ?>"><?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname']); ?></a></td>
                                            <?php foreach ($statuses as $key => $st) { ?>
                                                <td class="text-center"><?php echo isset($rows[$key]) ? (int) $rows[$key]['cnt'] : 0; ?></td>
                                            <?php } ?>
                                            <td class="text-center"><?php echo $late; ?></td>
                                            <td class="text-center"><?php echo $minutes ? round($minutes / 60, 1) : 0; ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>

                        <?php } elseif ($tab === 'leave') { ?>
                            <h4 class="bold">Leave Balance &mdash; <?php echo $year; ?></h4>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <?php foreach ($leave_types as $lt) { ?>
                                            <th class="text-center" colspan="2"><span class="label" style="background:<?php echo html_escape($lt['color']); ?>;color:#fff;"><?php echo html_escape($lt['code']); ?></span></th>
                                        <?php } ?>
                                    </tr>
                                    <tr>
                                        <th></th>
                                        <?php foreach ($leave_types as $lt) { ?>
                                            <th class="text-center text-muted" style="font-weight:400;">Used</th>
                                            <th class="text-center text-muted" style="font-weight:400;">Balance</th>
                                        <?php } ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employees as $emp) { ?>
                                        <tr>
                                            <td><a href="<?php echo admin_url('hr/employee/' . $emp['staffid'] . '?tab=leave'); ?>"><?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname']); ?></a></td>
                                            <?php foreach ($leave_types as $lt) {
                                                $alloc = $allocations[$emp['staffid']][$lt['id']] ?? $lt['days_per_year'];
                                                $used  = $leave_used[$emp['staffid']][$lt['id']] ?? 0; ?>
                                                <td class="text-center"><?php echo (float) $used; ?></td>
                                                <td class="text-center bold"><?php echo (float) $alloc - (float) $used; ?></td>
                                            <?php } ?>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>

                        <?php } elseif ($tab === 'payroll') { ?>
                            <h4 class="bold">Payroll History</h4>
                            <table class="table table-striped">
                                <thead><tr><th>Period</th><th>Payslips</th><th>Total Net Pay</th><th>Status</th><th class="text-right">Open</th></tr></thead>
                                <tbody>
                                    <?php if (!count($runs)) { ?>
                                        <tr><td colspan="5" class="text-muted">No payroll runs.</td></tr>
                                    <?php }
                                    foreach ($runs as $run) { ?>
                                        <tr>
                                            <td class="bold"><?php echo date('F Y', mktime(0, 0, 0, $run['month'], 1, $run['year'])); ?></td>
                                            <td><?php echo (int) $run['slip_count']; ?></td>
                                            <td><?php echo app_format_money($run['total_net'], $currency); ?></td>
                                            <td><span class="label label-<?php echo $run['status'] === 'finalized' ? 'success' : 'warning'; ?>"><?php echo ucfirst($run['status']); ?></span></td>
                                            <td class="text-right"><a href="<?php echo admin_url('hr/payroll_run/' . $run['id']); ?>" class="btn btn-default btn-icon"><i class="fa fa-folder-open"></i></a></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>

                        <?php } else { ?>
                            <div class="row">
                                <div class="col-md-4">
                                    <h4 class="bold">By Department</h4>
                                    <table class="table table-striped">
                                        <tbody>
                                            <?php foreach ($departments as $d) { ?>
                                                <tr><td><?php echo html_escape($d['name']); ?></td><td class="bold text-right"><?php echo (int) $d['cnt']; ?></td></tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-3">
                                    <h4 class="bold">By Employment Type</h4>
                                    <table class="table table-striped">
                                        <tbody>
                                            <?php foreach ($by_type as $t) { ?>
                                                <tr><td><?php echo ucwords(str_replace('_', ' ', $t['type'])); ?></td><td class="bold text-right"><?php echo (int) $t['cnt']; ?></td></tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-5">
                                    <h4 class="bold">Joins vs Exits (last 12 months)</h4>
                                    <table class="table table-striped">
                                        <thead><tr><th>Month</th><th class="text-center">Joins</th><th class="text-center">Exits</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($joins_exits as $je) { ?>
                                                <tr>
                                                    <td><?php echo date('M Y', strtotime($je['month'] . '-01')); ?></td>
                                                    <td class="text-center"><?php echo $je['joins'] ? '<span class="text-success bold">+' . $je['joins'] . '</span>' : 0; ?></td>
                                                    <td class="text-center"><?php echo $je['exits'] ? '<span class="text-danger bold">-' . $je['exits'] . '</span>' : 0; ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function () {
        $('#rep_month, #rep_year').on('change', function () {
            var m = $('#rep_month').length ? $('#rep_month').val() : <?php echo $month; ?>;
            window.location.href = '<?php echo $base_url; ?>?tab=<?php echo $tab; ?>&month=' + m + '&year=' + $('#rep_year').val();
        });
    });
</script>
