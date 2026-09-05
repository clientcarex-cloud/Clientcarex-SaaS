<?php defined('BASEPATH') or exit('No direct script access allowed');

$first_dow   = (int) date('w', mktime(0, 0, 0, $month, 1, $year)); // 0 = Sun
$days_in_mon = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
$today       = date('Y-m-d');
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <style>
            .hr-card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.05);border:1px solid rgba(0,0,0,.03);padding:20px;margin-bottom:20px}
            .att-cal{width:100%;table-layout:fixed;border-collapse:separate;border-spacing:6px}
            .att-cal th{font-size:11px;color:#64748b;text-transform:uppercase;padding:4px;text-align:center}
            .att-cell{height:70px;border-radius:10px;background:#f8fafc;border:1px solid #eef2f7;padding:6px;vertical-align:top;position:relative}
            .att-cell .d{font-size:12px;font-weight:700;color:#475569}
            .att-badge{position:absolute;bottom:6px;left:6px;right:6px;font-size:10px;font-weight:700;color:#fff;border-radius:6px;padding:2px 4px;text-align:center;text-transform:uppercase}
            .att-today{outline:2px solid #6366f1}
            .att-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;font-size:13px;font-weight:600;margin:0 6px 6px 0;background:#f1f5f9}
            .att-chip b{font-size:15px}
        </style>

        <div class="hr-card">
            <div class="row">
                <div class="col-md-6">
                    <h4 style="margin:0;font-weight:800;"><i class="fa fa-calendar-check-o"></i> My Attendance — <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h4>
                </div>
                <div class="col-md-6 text-right">
                    <div class="btn-group">
                        <?php $pm = $month - 1; $py = $year; if ($pm < 1) { $pm = 12; $py--; }
                              $nm = $month + 1; $ny = $year; if ($nm > 12) { $nm = 1; $ny++; } ?>
                        <a href="<?php echo admin_url('hr/myhr/attendance?month=' . $pm . '&year=' . $py); ?>" class="btn btn-default"><i class="fa fa-chevron-left"></i></a>
                        <a href="<?php echo admin_url('hr/myhr/attendance?month=' . date('n') . '&year=' . date('Y')); ?>" class="btn btn-default">Today</a>
                        <a href="<?php echo admin_url('hr/myhr/attendance?month=' . $nm . '&year=' . $ny); ?>" class="btn btn-default"><i class="fa fa-chevron-right"></i></a>
                    </div>
                </div>
            </div>
            <hr class="hr-panel-heading" />

            <div style="margin-bottom:14px;">
                <?php foreach ($statuses as $key => $st) {
                    $c = (int) ($counts[$key] ?? 0);
                    if (!$c) { continue; } ?>
                    <span class="att-chip"><span style="width:12px;height:12px;border-radius:3px;background:<?php echo $st['color']; ?>;display:inline-block;"></span><?php echo $st['label']; ?> <b><?php echo $c; ?></b></span>
                <?php } ?>
            </div>

            <table class="att-cal">
                <thead><tr>
                    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d) { echo '<th>' . $d . '</th>'; } ?>
                </tr></thead>
                <tbody>
                    <tr>
                        <?php for ($i = 0; $i < $first_dow; $i++) { echo '<td></td>'; }
                        $col = $first_dow;
                        for ($day = 1; $day <= $days_in_mon; $day++) {
                            $row  = $days[$day] ?? null;
                            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $st   = $row && isset($statuses[$row['status']]) ? $statuses[$row['status']] : null;
                            $is_holiday = isset($holidays[$day]);
                            ?>
                            <td class="att-cell <?php echo $date === $today ? 'att-today' : ''; ?>" <?php echo $is_holiday ? 'style="background:#ecfeff;"' : ''; ?>
                                <?php if ($is_holiday) { ?>title="<?php echo html_escape($holidays[$day]); ?>"<?php } ?>>
                                <div class="d"><?php echo $day; ?></div>
                                <?php if ($st) { ?>
                                    <div class="att-badge" style="background:<?php echo $st['color']; ?>;" title="<?php echo html_escape(($row['note'] ?? '')); ?>"><?php echo $st['short']; ?></div>
                                <?php } elseif ($is_holiday) { ?>
                                    <div class="att-badge" style="background:#0891b2;">Holiday</div>
                                <?php } ?>
                            </td>
                            <?php
                            $col++;
                            if ($col % 7 == 0 && $day != $days_in_mon) { echo '</tr><tr>'; }
                        }
                        while ($col % 7 != 0) { echo '<td></td>'; $col++; }
                        ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php init_tail(); ?>
