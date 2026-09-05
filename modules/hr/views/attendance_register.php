<?php defined('BASEPATH') or exit('No direct script access allowed');

$days_in_month = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
$week_offs     = array_filter(explode(',', get_option('hr_default_week_off')), 'strlen');
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-2">
                                <select id="reg_month" class="selectpicker" data-width="100%">
                                    <?php for ($m = 1; $m <= 12; $m++) { ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="reg_year" class="selectpicker" data-width="100%">
                                    <?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 5; $y--) { ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-8 text-right">
                                <?php foreach ($statuses as $st) { ?>
                                    <span class="label" style="background:<?php echo $st['color']; ?>;color:#fff;"><?php echo $st['short']; ?> = <?php echo $st['label']; ?></span>
                                <?php } ?>
                                <a href="<?php echo admin_url('hr/attendance'); ?>" class="btn btn-default mleft5"><i class="fa fa-arrow-left"></i> Back to Daily Attendance</a>
                            </div>
                        </div>

                        <div class="clearfix"></div>
                        <hr class="hr-panel-heading" />

                        <div class="table-responsive">
                            <table class="table table-bordered" style="font-size:11px;">
                                <thead>
                                    <tr>
                                        <th style="min-width:170px;position:sticky;left:0;background:#f8fafc;z-index:2;">Employee</th>
                                        <?php for ($d = 1; $d <= $days_in_month; $d++) {
                                            $w = date('w', mktime(0, 0, 0, $month, $d, $year));
                                            $is_off = in_array($w, $week_offs);
                                            $is_holiday = isset($holidays[$d]); ?>
                                            <th class="text-center" style="min-width:28px;<?php echo ($is_off || $is_holiday) ? 'background:#f1f5f9;' : ''; ?>"
                                                <?php echo $is_holiday ? 'title="' . html_escape($holidays[$d]) . '"' : ''; ?>>
                                                <?php echo $d; ?><br><span class="text-muted"><?php echo substr(date('D', mktime(0, 0, 0, $month, $d, $year)), 0, 2); ?></span>
                                            </th>
                                        <?php } ?>
                                        <th class="text-center" style="min-width:36px;background:#f0fdf4;">P</th>
                                        <th class="text-center" style="min-width:36px;background:#fef2f2;">A</th>
                                        <th class="text-center" style="min-width:36px;background:#f5f3ff;">L</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employees as $emp) {
                                        $p = $a = $l = 0; ?>
                                        <tr>
                                            <td style="position:sticky;left:0;background:#fff;z-index:1;">
                                                <a href="<?php echo admin_url('hr/employee/' . $emp['staffid']); ?>"><?php echo html_escape($emp['firstname'] . ' ' . $emp['lastname']); ?></a>
                                            </td>
                                            <?php for ($d = 1; $d <= $days_in_month; $d++) {
                                                $rec = $matrix[$emp['staffid']][$d] ?? null;
                                                if (!$rec) {
                                                    echo '<td class="text-center text-muted">-</td>';
                                                    continue;
                                                }
                                                $st = $statuses[$rec['status']] ?? null;
                                                if ($rec['status'] === 'present' || $rec['status'] === 'half_day') { $p++; }
                                                if ($rec['status'] === 'absent') { $a++; }
                                                if ($rec['status'] === 'leave') { $l++; }
                                                $title = trim(($rec['check_in'] ? 'In ' . $rec['check_in'] : '') . ($rec['check_out'] ? ' Out ' . $rec['check_out'] : '') . ($rec['note'] ? ' | ' . $rec['note'] : ''));
                                                echo '<td class="text-center" style="background:' . ($st ? $st['color'] : '#999') . '1a;color:' . ($st ? $st['color'] : '#333') . ';font-weight:700;" title="' . html_escape($title) . '">'
                                                    . ($st ? $st['short'] : '?')
                                                    . ($rec['is_late'] ? '<span style="color:#d97706;">*</span>' : '')
                                                    . '</td>';
                                            } ?>
                                            <td class="text-center bold"><?php echo $p; ?></td>
                                            <td class="text-center bold"><?php echo $a; ?></td>
                                            <td class="text-center bold"><?php echo $l; ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted">* = late arrival. Hover a cell for check-in/out times and notes.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function () {
        $('#reg_month, #reg_year').on('change', function () {
            window.location.href = '<?php echo admin_url('hr/attendance_register'); ?>?month=' + $('#reg_month').val() + '&year=' + $('#reg_year').val();
        });
    });
</script>
