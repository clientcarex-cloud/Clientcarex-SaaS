<?php defined('BASEPATH') or exit('No direct script access allowed');

$day_names  = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$fmt_off    = function ($csv) use ($day_names) {
    $offs = array_filter(explode(',', (string) $csv), 'strlen');
    if (!count($offs)) { return '—'; }
    return implode(', ', array_map(function ($d) use ($day_names) { return substr($day_names[(int) $d] ?? '?', 0, 3); }, $offs));
};

// Index shifts + find the default.
$shift_by_id   = [];
$default_shift = null;
foreach ($shifts as $s) {
    $shift_by_id[(int) $s['id']] = $s;
    if (!empty($s['is_default'])) { $default_shift = $s; }
}

// Group employees under their current shift (assignment, else default shift).
$groups     = [];
$unassigned = [];
foreach ($employees as $emp) {
    $a = $shift_map[$emp['staffid']] ?? null;
    if ($a) {
        $sid = (int) $a['shift_id'];
        if (!isset($groups[$sid])) {
            $groups[$sid] = ['shift' => $shift_by_id[$sid] ?? $a, 'emps' => []];
        }
        $groups[$sid]['emps'][] = ['emp' => $emp, 'since' => $a['effective_from']];
    } elseif ($default_shift) {
        $sid = (int) $default_shift['id'];
        if (!isset($groups[$sid])) { $groups[$sid] = ['shift' => $default_shift, 'emps' => []]; }
        $groups[$sid]['emps'][] = ['emp' => $emp, 'since' => null];
    } else {
        $unassigned[] = $emp;
    }
}
// Order groups by shift start time.
uasort($groups, function ($a, $b) {
    return strcmp($a['shift']['start_time'] ?? '', $b['shift']['start_time'] ?? '');
});
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="text-right hidden-print" style="margin-bottom:12px;">
                            <a href="<?php echo admin_url('hr/shifts'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>
                            <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fa fa-print"></i> Print / Save PDF</button>
                        </div>

                        <div id="shiftsheet_area">
                            <div class="ss-head">
                                <?php if ($logo_url) { ?><img src="<?php echo $logo_url; ?>" class="ss-logo" alt="<?php echo html_escape($company); ?>"><?php } ?>
                                <div class="ss-company"><?php echo html_escape($company); ?></div>
                                <div class="ss-title">Shift Duty Roster</div>
                                <div class="ss-meta">As on <?php echo _d(date('Y-m-d')); ?> &middot; <?php echo count($employees); ?> employee(s)</div>
                            </div>

                            <?php $sn = 0; foreach ($groups as $g) { $shift = $g['shift']; $sn++; ?>
                                <div class="ss-group">
                                    <div class="ss-group-head">
                                        <div class="ss-shift-name"><span class="ss-dot"></span><?php echo html_escape($shift['name']); ?>
                                            <?php if (!empty($shift['is_default'])) { ?><span class="ss-badge">Default</span><?php } ?>
                                        </div>
                                        <div class="ss-shift-time">
                                            <?php echo date('g:i A', strtotime($shift['start_time'])) . ' – ' . date('g:i A', strtotime($shift['end_time'])); ?>
                                            <?php if (isset($shift['break_minutes'])) { ?><span class="ss-sub">&middot; Break <?php echo (int) $shift['break_minutes']; ?>m &middot; Grace <?php echo (int) $shift['grace_minutes']; ?>m</span><?php } ?>
                                            <span class="ss-sub">&middot; Week off: <?php echo html_escape($fmt_off($shift['week_off_days'] ?? '')); ?></span>
                                        </div>
                                    </div>
                                    <table class="ss-table">
                                        <thead>
                                            <tr><th style="width:36px;">#</th><th>Employee</th><th style="width:130px;">Code</th><th style="width:180px;">Department</th><th style="width:110px;">Since</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php $i = 0; foreach ($g['emps'] as $row) { $emp = $row['emp']; $i++; ?>
                                                <tr>
                                                    <td class="ss-num"><?php echo $i; ?></td>
                                                    <td class="ss-emp"><?php echo html_escape(trim($emp['firstname'] . ' ' . $emp['lastname'])); ?></td>
                                                    <td><?php echo html_escape($emp['employee_code'] ?: '—'); ?></td>
                                                    <td><?php echo html_escape($dept_names[(int) ($emp['department_id'] ?? 0)] ?? '—'); ?></td>
                                                    <td><?php echo $row['since'] ? _d($row['since']) : '<span class="ss-muted">Default</span>'; ?></td>
                                                </tr>
                                            <?php } ?>
                                            <?php if (!count($g['emps'])) { ?>
                                                <tr><td colspan="5" class="ss-muted">No employees on this shift.</td></tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                    <div class="ss-count"><?php echo count($g['emps']); ?> on duty</div>
                                </div>
                            <?php } ?>

                            <?php if (!empty($unassigned)) { ?>
                                <div class="ss-group">
                                    <div class="ss-group-head"><div class="ss-shift-name"><span class="ss-dot" style="background:#94a3b8;"></span>Unassigned</div>
                                        <div class="ss-shift-time"><span class="ss-sub">No shift assigned and no default shift configured</span></div>
                                    </div>
                                    <table class="ss-table">
                                        <thead><tr><th style="width:36px;">#</th><th>Employee</th><th style="width:130px;">Code</th><th style="width:180px;">Department</th><th style="width:110px;">Since</th></tr></thead>
                                        <tbody>
                                            <?php $i = 0; foreach ($unassigned as $emp) { $i++; ?>
                                                <tr>
                                                    <td class="ss-num"><?php echo $i; ?></td>
                                                    <td class="ss-emp"><?php echo html_escape(trim($emp['firstname'] . ' ' . $emp['lastname'])); ?></td>
                                                    <td><?php echo html_escape($emp['employee_code'] ?: '—'); ?></td>
                                                    <td><?php echo html_escape($dept_names[(int) ($emp['department_id'] ?? 0)] ?? '—'); ?></td>
                                                    <td><span class="ss-muted">—</span></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } ?>

                            <div class="ss-foot">
                                <div>Prepared by: <?php echo html_escape(get_staff_full_name(get_staff_user_id())); ?></div>
                                <div>System-generated on <?php echo _dt(date('Y-m-d H:i:s')); ?></div>
                            </div>
                            <div class="ss-sign">
                                <div class="ss-sign-box">Prepared By</div>
                                <div class="ss-sign-box">Verified By</div>
                                <div class="ss-sign-box">Approved By</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    #shiftsheet_area { color:#1e293b; font-size:13px; }
    #shiftsheet_area .ss-head { text-align:center; padding-bottom:16px; margin-bottom:20px; border-bottom:3px double #334155; }
    #shiftsheet_area .ss-logo { max-height:64px; max-width:240px; margin-bottom:8px; }
    #shiftsheet_area .ss-company { font-size:22px; font-weight:800; letter-spacing:.3px; }
    #shiftsheet_area .ss-title { font-size:15px; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:2px; margin-top:2px; }
    #shiftsheet_area .ss-meta { font-size:12px; color:#64748b; margin-top:4px; }
    #shiftsheet_area .ss-group { margin-bottom:22px; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; page-break-inside:avoid; }
    #shiftsheet_area .ss-group-head { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; padding:10px 14px; background:linear-gradient(90deg,#f8fafc,#eef2ff); border-bottom:1px solid #e2e8f0; }
    #shiftsheet_area .ss-shift-name { font-size:15px; font-weight:800; display:flex; align-items:center; gap:8px; }
    #shiftsheet_area .ss-dot { width:10px; height:10px; border-radius:50%; background:#4f46e5; display:inline-block; }
    #shiftsheet_area .ss-badge { font-size:10px; font-weight:700; background:#dbeafe; color:#1d4ed8; padding:2px 8px; border-radius:10px; }
    #shiftsheet_area .ss-shift-time { font-size:12px; color:#334155; font-weight:600; }
    #shiftsheet_area .ss-sub { color:#64748b; font-weight:400; }
    #shiftsheet_area .ss-table { width:100%; border-collapse:collapse; }
    #shiftsheet_area .ss-table th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.4px; color:#64748b; padding:8px 14px; background:#fff; border-bottom:1px solid #e2e8f0; }
    #shiftsheet_area .ss-table td { padding:7px 14px; border-bottom:1px solid #f1f5f9; }
    #shiftsheet_area .ss-table tr:last-child td { border-bottom:none; }
    #shiftsheet_area .ss-num { color:#94a3b8; }
    #shiftsheet_area .ss-emp { font-weight:700; }
    #shiftsheet_area .ss-muted { color:#94a3b8; }
    #shiftsheet_area .ss-count { text-align:right; font-size:11px; font-weight:700; color:#475569; padding:6px 14px; background:#f8fafc; border-top:1px solid #e2e8f0; }
    #shiftsheet_area .ss-foot { display:flex; justify-content:space-between; font-size:11px; color:#64748b; margin-top:18px; }
    #shiftsheet_area .ss-sign { display:flex; justify-content:space-between; gap:20px; margin-top:46px; }
    #shiftsheet_area .ss-sign-box { flex:1; text-align:center; border-top:1px solid #94a3b8; padding-top:6px; font-size:12px; color:#475569; }

    @media print {
        body * { visibility: hidden !important; }
        #shiftsheet_area, #shiftsheet_area * { visibility: visible !important; }
        .hidden-print { display: none !important; }
        #shiftsheet_area { position: absolute; left: 0; top: 0; width: 100%; }
        #wrapper, .content, .panel_s, .panel-body, .row, [class*="col-"] {
            margin: 0 !important; padding: 0 !important; border: none !important; box-shadow: none !important; width: auto !important; float: none !important;
        }
        #shiftsheet_area * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        @page { margin: 12mm; }
    }
</style>
<?php init_tail(); ?>
