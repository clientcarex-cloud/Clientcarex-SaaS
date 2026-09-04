<?php defined('BASEPATH') or exit('No direct script access allowed'); $R = $report; $k = $R['kpi'];
/* ── tiny chart helpers (inline SVG, no libraries) ── */
$bars = function (array $rows, $key, $val, $fmt = null, $h = 120, $color = 'var(--gold)') {
    $n = count($rows);
    if (!$n) { return '<div class="shra-empty" style="padding:26px"><i class="fa-solid fa-chart-simple"></i>No data in this range.</div>'; }
    $max = 0; foreach ($rows as $r) { $max = max($max, (float) $r->$val); }
    $max = $max ?: 1;
    $w = max(320, $n * 28); $bw = max(6, min(34, ($w / $n) - 6));
    $svg = '<svg viewBox="0 0 ' . $w . ' ' . ($h + 26) . '" width="100%" height="' . ($h + 26) . '" preserveAspectRatio="none" style="max-height:' . ($h + 26) . 'px">';
    foreach ($rows as $i => $r) {
        $v = (float) $r->$val; $bh = round($v / $max * $h, 1); $x = $i * ($w / $n) + (($w / $n) - $bw) / 2;
        $label = $r->$key; $title = $label . ': ' . ($fmt ? $fmt($v) : $v);
        $svg .= '<rect x="' . $x . '" y="' . ($h - $bh) . '" width="' . $bw . '" height="' . $bh . '" rx="3" fill="' . $color . '" opacity=".9"><title>' . html_escape($title) . '</title></rect>';
        if ($n <= 31) { $svg .= '<text x="' . ($x + $bw / 2) . '" y="' . ($h + 16) . '" font-size="9" fill="#7a6f5e" text-anchor="middle">' . html_escape(mb_strlen($label) > 6 ? mb_substr($label, 0, 6) : $label) . '</text>'; }
    }
    return $svg . '</svg>';
};
$hbars = function (array $rows, $key, $val, $fmt = null, $color = 'var(--gold)', $total = false) {
    if (!count($rows)) { return '<div class="shra-empty" style="padding:26px"><i class="fa-solid fa-chart-simple"></i>No data in this range.</div>'; }
    $max = 0; $sum = 0; foreach ($rows as $r) { $max = max($max, (float) $r->$val); $sum += (float) $r->$val; } $max = $max ?: 1;
    $o = '<div class="shra-hbars">';
    foreach ($rows as $r) { $v = (float) $r->$val; $o .= '<div class="hb"><span class="lbl">' . html_escape($r->$key) . '</span><span class="bar"><span style="width:' . round($v / $max * 100) . '%;background:' . $color . '"></span></span><span class="val">' . ($fmt ? $fmt($v) : (int) $v) . '</span></div>'; }
    if ($total) { $o .= '<div class="hb total"><span class="lbl">Total</span><span></span><span class="val">' . ($fmt ? $fmt($sum) : (int) $sum) . '</span></div>'; }
    return $o . '</div>';
};
$money = 'shra_money';
$pct = function ($a, $b) { return $b > 0 ? round($a / $b * 100) : 0; };
$days = max(1, (strtotime($to) - strtotime($from)) / 86400 + 1);
$dow  = [1 => 'Sun', 2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat'];
$wk = []; foreach ($dow as $i => $l) { $wk[$i] = (object) ['k' => $l, 'n' => 0]; } foreach ($R['by_weekday'] as $r) { $wk[(int) $r->dow]->n = (int) $r->n; }
$byday = []; foreach ($R['by_day'] as $r) { $byday[] = (object) ['k' => date('d M', strtotime($r->d)), 'v' => $r->amount]; }
$sess  = []; foreach ($R['sessions_by_day'] as $r) { $sess[] = (object) ['k' => date('d M', strtotime($r->d)), 'v' => $r->n]; }
$hours = []; foreach ($R['by_hour'] as $r) { $hours[] = (object) ['k' => date('g A', strtotime($r->h . ':00')), 'v' => $r->n]; }
?>
<?php init_head(); ?>
<style>
.shra-rep-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
@media(max-width:900px){.shra-rep-grid{grid-template-columns:1fr}}
.shra-rep-grid .shra-card{margin:0}
.shra-hbars .hb{display:grid;grid-template-columns:130px 1fr 90px;gap:10px;align-items:center;padding:6px 0;border-bottom:1px solid var(--line);font-size:13px}
.shra-hbars .hb:last-child{border-bottom:0}
.shra-hbars .lbl{font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.shra-hbars .bar{display:block;height:10px;background:var(--cream);border-radius:999px;overflow:hidden}
.shra-hbars .bar span{display:block;height:100%;border-radius:999px}
.shra-hbars .val{text-align:right;font-weight:700;font-variant-numeric:tabular-nums}
.shra-modes .lbl small{display:block;font-size:10.5px;font-weight:500;color:var(--muted)}
.shra-modes .hb.zero .lbl,.shra-modes .hb.zero .val{color:var(--muted);font-weight:500}
.shra-hbars .hb.total{border-top:2px solid var(--line);border-bottom:0;margin-top:2px;padding-top:9px}
.shra-hbars .hb.total .val{font-size:15px}
.shra-modes .pct{display:flex;flex-wrap:wrap;gap:4px 10px;font-size:11px;color:var(--muted)}
.shra-modes .pct em{font-style:normal;white-space:nowrap}
.shra-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}
@media(max-width:900px){.shra-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
.shra-kpi{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px}
.shra-kpi .l{font-size:10.5px;letter-spacing:1px;text-transform:uppercase;color:var(--muted);font-weight:700}
.shra-kpi .v{font-size:24px;font-weight:700;margin-top:4px;font-variant-numeric:tabular-nums}
.shra-kpi .s{font-size:12px;color:var(--muted);margin-top:2px}
.shra-rep-title{font-size:11px;letter-spacing:1.4px;text-transform:uppercase;color:var(--gold);font-weight:700;margin:22px 0 10px;display:flex;align-items:center;gap:10px}
.shra-rep-title:after{content:'';flex:1;height:1px;background:var(--line)}
.shra-split{display:flex;gap:6px;margin-top:8px}
.shra-split span{height:10px;border-radius:999px;min-width:4px}
@media print{.shra-nav,.shra-head>div:last-child,.shra-toolbar,#header,#sidebar,.shra-footer{display:none!important}.shra .content{padding:0}.shra-card{break-inside:avoid}}
</style>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'reports'; include __DIR__ . '/_nav.php'; ?>

    <form method="get" class="shra-toolbar" id="shra-rep-form">
        <div class="shra-seg" style="margin:0;flex-wrap:wrap">
            <?php foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'last_month' => 'Last month', 'quarter' => '3 months', 'year' => 'This year', 'all' => 'All time'] as $kk => $l) { ?>
                <label><input type="radio" name="range" value="<?php echo $kk; ?>" <?php echo $range === $kk ? 'checked' : ''; ?> onchange="this.form.submit()"><span><?php echo $l; ?></span></label>
            <?php } ?>
            <label><input type="radio" name="range" value="custom" <?php echo $range === 'custom' ? 'checked' : ''; ?>><span>Custom</span></label>
        </div>
        <input type="date" name="from" class="form-control" style="width:auto" value="<?php echo $from; ?>" onchange="document.querySelector('#shra-rep-form [value=custom]').checked=true">
        <span class="shra-muted">to</span>
        <input type="date" name="to" class="form-control" style="width:auto" value="<?php echo $to; ?>" onchange="document.querySelector('#shra-rep-form [value=custom]').checked=true">
        <button class="shra-btn shra-btn-primary shra-btn-sm">Apply</button>
        <button type="button" class="shra-btn shra-btn-outline shra-btn-sm" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
        <span class="shra-pill" style="margin-left:auto"><?php echo _d($from); ?> – <?php echo _d($to); ?> · <?php echo (int) $days; ?> day<?php echo $days == 1 ? '' : 's'; ?></span>
    </form>

    <div class="shra-rep-title" style="margin-top:4px">Finance</div>
    <div class="shra-kpis">
        <div class="shra-kpi"><div class="l">Collected</div><div class="v" style="color:var(--green)"><?php echo $money($k['collected']); ?></div><div class="s"><?php echo (int) $k['payments']; ?> payment<?php echo $k['payments'] == 1 ? '' : 's'; ?> · <?php echo $money($k['collected'] / $days); ?> / day</div></div>
        <div class="shra-kpi"><div class="l">Billed</div><div class="v"><?php echo $money($k['billed']); ?></div><div class="s"><?php echo (int) $k['packages_sold']; ?> package<?php echo $k['packages_sold'] == 1 ? '' : 's'; ?> · avg <?php echo $money($k['packages_sold'] ? $k['billed'] / $k['packages_sold'] : 0); ?></div></div>
        <div class="shra-kpi"><div class="l">Discounts given</div><div class="v" style="color:var(--red)"><?php echo $money($k['discounts']); ?></div><div class="s"><?php echo $pct($k['discounts'], $k['list_value']); ?>% of <?php echo $money($k['list_value']); ?> list value</div></div>
        <div class="shra-kpi"><div class="l">Balance due</div><div class="v" style="<?php echo $k['due_total'] > 0.009 ? 'color:var(--red)' : ''; ?>"><?php echo $money($k['due_total']); ?></div><div class="s"><?php echo $money($k['due_in_range']); ?> from bills in this range · <a href="<?php echo admin_url('shra/riders?view=due'); ?>">collect</a></div></div>
    </div>

    <div class="shra-rep-grid">
        <div class="shra-card"><div class="shra-card-head"><h4>Collections by day</h4><span class="shra-pill"><?php echo $money($k['collected']); ?></span></div><div class="shra-card-body"><?php echo $bars($byday, 'k', 'v', $money, 130, 'var(--green)'); ?></div></div>
        <div class="shra-card"><div class="shra-card-head"><h4>Collections by payment mode</h4><span class="shra-pill"><?php echo $money($k['collected']); ?></span></div><div class="shra-card-body">
            <?php if (!count($R['by_mode'])) { ?><div class="shra-empty" style="padding:26px"><i class="fa-solid fa-chart-simple"></i>No payment modes configured.</div><?php } else {
                $mmax = 0; $mtot = 0; $mcnt = 0; foreach ($R['by_mode'] as $m) { $mmax = max($mmax, (float) $m->amount); $mtot += (float) $m->amount; $mcnt += (int) $m->n; } $mmax = $mmax ?: 1; ?>
            <div class="shra-hbars shra-modes">
                <?php foreach ($R['by_mode'] as $m) { $v = (float) $m->amount; ?>
                <div class="hb<?php echo $v > 0 ? '' : ' zero'; ?>"><span class="lbl" title="<?php echo html_escape($m->mode); ?>"><?php echo html_escape($m->mode); ?><small><?php echo (int) $m->n; ?> payment<?php echo (int) $m->n == 1 ? '' : 's'; ?></small></span><span class="bar"><span style="width:<?php echo round($v / $mmax * 100); ?>%;background:var(--green)"></span></span><span class="val"><?php echo $v > 0 ? $money($v) : '—'; ?></span></div>
                <?php } ?>
                <div class="hb total"><span class="lbl">Total<small><?php echo $mcnt; ?> payment<?php echo $mcnt == 1 ? '' : 's'; ?></small></span><span class="pct"><?php foreach ($R['by_mode'] as $m) { if ((float) $m->amount <= 0) { continue; } ?><em><?php echo html_escape($m->mode); ?> <?php echo $pct($m->amount, $mtot); ?>%</em><?php } ?></span><span class="val"><?php echo $money($mtot); ?></span></div>
            </div>
            <?php $ci = 0; ?><div class="shra-split"><?php foreach ($R['by_mode'] as $m) { if ((float) $m->amount <= 0) { continue; } ?><span style="flex:<?php echo max(1, (float) $m->amount); ?>;background:<?php echo ['var(--green)', 'var(--gold)', 'var(--brown)', 'var(--ink)', 'var(--muted)'][$ci++ % 5]; ?>" title="<?php echo html_escape($m->mode . ' · ' . $money($m->amount)); ?>"></span><?php } ?></div>
            <?php } ?>
        </div></div>
    </div>

    <div class="shra-card" style="margin-bottom:16px">
        <div class="shra-card-head"><h4>Package sales</h4><span class="shra-pill"><?php echo (int) $k['packages_sold']; ?> sold · <?php echo (int) $k['sessions_sold']; ?> sessions</span></div>
        <?php if (!count($R['by_package'])) { ?><div class="shra-empty" style="padding:26px"><i class="fa-solid fa-tags"></i>No packages billed in this range.</div><?php } else { ?>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>Package</th><th>Audience</th><th class="num">Sold</th><th class="num">Sessions</th><th class="num">List value</th><th class="num">Discounts</th><th class="num">Billed</th><th class="num">Collected</th><th class="num">Due</th><th style="min-width:120px">Share</th></tr></thead>
            <tbody><?php foreach ($R['by_package'] as $r) { ?>
                <tr><td class="strong"><?php echo html_escape($r->package_name); ?> <?php echo $r->is_guest ? '<span class="shra-badge shra-badge-gold">Guest</span>' : ''; ?></td><td><?php echo ucfirst($r->audience); ?></td><td class="num"><?php echo (int) $r->n; ?></td><td class="num"><?php echo (int) $r->sessions; ?></td><td class="num"><?php echo $money($r->list_value); ?></td><td class="num" style="color:var(--red)"><?php echo $money($r->discounts); ?></td><td class="num strong"><?php echo $money($r->billed); ?></td><td class="num" style="color:var(--green)"><?php echo $money($r->collected); ?></td><td class="num"><?php echo $r->billed - $r->collected > 0.009 ? '<span style="color:var(--red)">' . $money($r->billed - $r->collected) . '</span>' : '—'; ?></td><td><div class="shra-progress"><span style="width:<?php echo $pct($r->billed, $k['billed']); ?>%"></span></div><span class="sub"><?php echo $pct($r->billed, $k['billed']); ?>% of billed</span></td></tr>
            <?php } ?></tbody>
            <tfoot><tr><th colspan="2" style="border-radius:0">Total</th><th class="num" style="border-radius:0"><?php echo (int) $k['packages_sold']; ?></th><th class="num" style="border-radius:0"><?php echo (int) $k['sessions_sold']; ?></th><th class="num" style="border-radius:0"><?php echo $money($k['list_value']); ?></th><th class="num" style="border-radius:0"><?php echo $money($k['discounts']); ?></th><th class="num" style="border-radius:0"><?php echo $money($k['billed']); ?></th><th class="num" style="border-radius:0"><?php echo $money($k['billed'] - $k['due_in_range']); ?></th><th class="num" style="border-radius:0"><?php echo $money($k['due_in_range']); ?></th><th style="border-radius:0"></th></tr></tfoot>
        </table></div><?php } ?>
    </div>

    <div class="shra-rep-grid">
        <div class="shra-card"><div class="shra-card-head"><h4>Children vs adults</h4></div><div class="shra-card-body"><?php $aud = array_map(function ($r) { $r->k = ucfirst($r->k) . ' (' . (int) $r->n . ')'; return $r; }, $R['by_audience']); echo $hbars($aud, 'k', 'billed', $money); ?></div></div>
        <div class="shra-card"><div class="shra-card-head"><h4>Learner packages vs guest rides</h4></div><div class="shra-card-body"><?php $typ = array_map(function ($r) { $r->k = ucfirst($r->k) . ' (' . (int) $r->n . ')'; return $r; }, $R['by_type']); echo $hbars($typ, 'k', 'billed', $money, 'var(--brown)'); ?></div></div>
    </div>

    <?php if (!empty($lead_agents)) { ?>
    <div class="shra-rep-title">Revenue by calling agent <span class="thin" style="font-weight:500;font-size:12px">· credited at billing to the agent who owned the lead · <a href="<?php echo admin_url('shra/shra_leads/team?range=custom&from=' . $from . '&to=' . $to); ?>">full team report</a></span></div>
    <div class="shra-rep-grid">
        <div class="shra-card"><div class="shra-card-head"><h4>By agent</h4></div><div class="shra-card-body"><?php $ag = array_map(function ($r) { $r->k = $r->name . ' (' . (int) $r->won . ' joined)'; return $r; }, array_values(array_filter($lead_agents, function ($r) { return $r->revenue > 0; }))); echo count($ag) ? $hbars($ag, 'k', 'revenue', $money, 'var(--green)', true) : '<div class="shra-muted">No lead revenue in this period.</div>'; ?></div></div>
        <div class="shra-card"><div class="shra-card-head"><h4>By lead source</h4></div><div class="shra-card-body"><?php $sr = array_map(function ($r) { $r->k = $r->name . ' (' . (int) $r->leads . ' leads)'; return $r; }, array_values(array_filter($lead_sources, function ($r) { return $r->revenue > 0; }))); echo count($sr) ? $hbars($sr, 'k', 'revenue', $money, 'var(--brown)', true) : '<div class="shra-muted">No lead revenue in this period.</div>'; ?></div></div>
    </div>
    <?php } ?>

    <div class="shra-rep-title">Academy activity</div>
    <div class="shra-kpis">
        <div class="shra-kpi"><div class="l">Sessions held</div><div class="v"><?php echo (int) $k['sessions_held']; ?></div><div class="s"><?php echo $k['riding_days'] ? round($k['sessions_held'] / $k['riding_days'], 1) : 0; ?> per riding day · <?php echo (int) $k['riding_days']; ?> day<?php echo $k['riding_days'] == 1 ? '' : 's'; ?> with rides</div></div>
        <div class="shra-kpi"><div class="l">Riders who rode</div><div class="v"><?php echo (int) $k['riders_rode']; ?></div><div class="s"><?php echo $k['riders_rode'] ? round($k['sessions_held'] / $k['riders_rode'], 1) : 0; ?> sessions each on average</div></div>
        <div class="shra-kpi"><div class="l">New riders</div><div class="v"><?php echo (int) $k['new_riders']; ?></div><div class="s"><?php echo (int) $k['new_learners']; ?> learners · <?php echo (int) $k['new_self']; ?> via QR self-registration</div></div>
        <div class="shra-kpi"><div class="l">Courses completed</div><div class="v"><?php echo (int) $k['completed']; ?></div><div class="s"><?php echo (int) $k['certificates']; ?> certificate<?php echo $k['certificates'] == 1 ? '' : 's'; ?> issued</div></div>
    </div>

    <div class="shra-rep-grid">
        <div class="shra-card"><div class="shra-card-head"><h4>Sessions by day</h4><span class="shra-pill"><?php echo (int) $k['sessions_held']; ?></span></div><div class="shra-card-body"><?php echo $bars($sess, 'k', 'v', null, 130); ?></div></div>
        <div class="shra-card"><div class="shra-card-head"><h4>Busiest weekdays</h4></div><div class="shra-card-body"><?php echo $hbars(array_values($wk), 'k', 'n'); ?></div></div>
        <div class="shra-card"><div class="shra-card-head"><h4>Trainer workload</h4></div><div class="shra-card-body">
            <?php if (!count($R['by_trainer'])) { ?><div class="shra-empty" style="padding:26px"><i class="fa-solid fa-user-tie"></i>No sessions in this range.</div><?php } else { ?>
            <table class="shra-table"><thead><tr><th>Trainer</th><th class="num">Sessions</th><th class="num">Riders</th><th class="num">Days</th><th style="min-width:110px">Share</th></tr></thead><tbody>
            <?php foreach ($R['by_trainer'] as $r) { ?><tr><td class="strong"><?php echo html_escape($r->trainer); ?></td><td class="num"><?php echo (int) $r->n; ?></td><td class="num"><?php echo (int) $r->riders; ?></td><td class="num"><?php echo (int) $r->days; ?></td><td><div class="shra-progress"><span style="width:<?php echo $pct($r->n, $k['sessions_held']); ?>%"></span></div></td></tr><?php } ?>
            </tbody></table><?php } ?>
        </div></div>
        <div class="shra-card"><div class="shra-card-head"><h4>Sessions by hour</h4></div><div class="shra-card-body"><?php echo $bars($hours, 'k', 'v', null, 110, 'var(--brown)'); ?></div></div>
        <div class="shra-card"><div class="shra-card-head"><h4>Most active riders</h4></div><div class="shra-card-body">
            <?php if (!count($R['top_riders'])) { ?><div class="shra-empty" style="padding:26px"><i class="fa-solid fa-horse"></i>No sessions in this range.</div><?php } else { ?>
            <table class="shra-table"><thead><tr><th>#</th><th>Rider</th><th class="num">Sessions</th><th>Last ride</th></tr></thead><tbody>
            <?php foreach ($R['top_riders'] as $i => $r) { ?><tr><td class="shra-muted"><?php echo $i + 1; ?></td><td><a href="<?php echo admin_url('shra/rider/' . $r->id); ?>" class="strong"><?php echo html_escape($r->full_name); ?></a><span class="sub"><?php echo html_escape($r->rider_no); ?> · <?php echo $r->rider_type === 'guest' ? 'Guest' : 'Learner'; ?></span></td><td class="num"><?php echo (int) $r->n; ?></td><td><?php echo _d($r->last_session); ?></td></tr><?php } ?>
            </tbody></table><?php } ?>
        </div></div>
        <div class="shra-card"><div class="shra-card-head"><h4>Horses ridden</h4></div><div class="shra-card-body"><?php echo count($R['top_horses']) ? $hbars($R['top_horses'], 'horse_name', 'n', null, 'var(--brown)') : '<div class="shra-empty" style="padding:26px"><i class="fa-solid fa-horse"></i>No horse names recorded on sessions yet.</div>'; ?></div></div>
    </div>

    <div class="shra-rep-title">Riders</div>
    <div class="shra-rep-grid">
        <div class="shra-card"><div class="shra-card-head"><h4>New registrations by source</h4><span class="shra-pill"><?php echo (int) $k['new_riders']; ?> in range</span></div><div class="shra-card-body"><?php $src = array_map(function ($r) { $r->k = ($r->k === 'self' ? 'QR self-registration' : 'Front desk') . ' · ' . ucfirst($r->rider_type); return $r; }, $R['riders_by_source']); echo $hbars($src, 'k', 'n'); ?></div></div>
        <div class="shra-card"><div class="shra-card-head"><h4>Age groups</h4><span class="shra-pill">all <?php echo (int) $k['riders_total']; ?> riders</span></div><div class="shra-card-body"><?php echo $hbars($R['age_bands'], 'k', 'n', null, 'var(--ink)'); ?></div></div>
        <div class="shra-card"><div class="shra-card-head"><h4>Learners by riding level</h4></div><div class="shra-card-body"><?php echo $hbars($R['riders_by_level'], 'k', 'n'); ?></div></div>
        <div class="shra-card"><div class="shra-card-head"><h4>Packages expiring within 30 days</h4><span class="shra-pill"><?php echo count($R['expiring']); ?></span></div><div class="shra-card-body" style="padding:0">
            <?php if (!count($R['expiring'])) { ?><div class="shra-empty" style="padding:26px"><i class="fa fa-check" style="color:var(--green)"></i>Nothing expiring soon.</div><?php } else { ?>
            <table class="shra-table"><thead><tr><th>Rider</th><th>Package</th><th class="num">Left</th><th>Expires</th></tr></thead><tbody>
            <?php foreach ($R['expiring'] as $r) { ?><tr><td><a href="<?php echo admin_url('shra/rider/' . $r->rider_id); ?>" class="strong"><?php echo html_escape($r->full_name); ?></a><span class="sub"><?php echo html_escape($r->rider_no); ?></span></td><td><?php echo html_escape($r->package_name); ?></td><td class="num"><?php echo (int) $r->left_n; ?></td><td style="<?php echo strtotime($r->expires_at) < time() ? 'color:var(--red);font-weight:600' : ''; ?>"><?php echo _d($r->expires_at); ?></td></tr><?php } ?>
            </tbody></table><?php } ?>
        </div></div>
    </div>

    <div class="shra-rep-title">Outstanding balances <span class="thin" style="letter-spacing:0;text-transform:none;font-weight:500;color:var(--muted)">· all time</span></div>
    <div class="shra-card" style="margin-bottom:16px">
        <?php if (!count($R['dues'])) { ?><div class="shra-empty" style="padding:26px"><i class="fa fa-check" style="color:var(--green)"></i>Every bill is fully paid.</div><?php } else { $ds = 0; ?>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>Rider</th><th>Mobile</th><th>Package</th><th>Billed on</th><th class="num">Bill total</th><th class="num">Due</th><th></th></tr></thead>
            <tbody><?php foreach ($R['dues'] as $r) { $ds += $r->due; ?>
                <tr><td><a href="<?php echo admin_url('shra/rider/' . $r->rider_id); ?>" class="strong"><?php echo html_escape($r->full_name); ?></a><span class="sub"><?php echo html_escape($r->rider_no); ?></span></td><td><?php echo html_escape($r->mobile); ?></td><td><?php echo html_escape($r->package_name); ?><span class="sub"><?php echo html_escape($r->enrollment_no); ?></span></td><td><?php echo _d($r->created_at); ?></td><td class="num"><?php echo $money($r->total); ?></td><td class="num strong" style="color:var(--red)"><?php echo $money($r->due); ?></td><td style="text-align:right"><?php if (shra_can_billing()) { ?><a href="<?php echo admin_url('shra/rider/' . $r->rider_id); ?>" class="shra-btn shra-btn-gold shra-btn-sm"><i class="fa-solid fa-hand-holding-dollar"></i> Collect</a><?php } ?></td></tr>
            <?php } ?></tbody>
            <tfoot><tr><th colspan="5" style="border-radius:0">Total outstanding</th><th class="num" style="border-radius:0;color:var(--red)"><?php echo $money($ds); ?></th><th style="border-radius:0"></th></tr></tfoot>
        </table></div><?php } ?>
    </div>

    <div class="shra-card">
        <div class="shra-card-head"><h4>Payments received</h4><span class="shra-pill"><?php echo count($R['payments']); ?> · <?php echo $money($k['collected']); ?></span></div>
        <?php if (!count($R['payments'])) { ?><div class="shra-empty" style="padding:26px"><i class="fa-solid fa-indian-rupee-sign"></i>No payments in this range.</div><?php } else { ?>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>Date</th><th>Rider</th><th>Package</th><th>Mode</th><th>Reference</th><th class="num">Amount</th><th></th></tr></thead>
            <tbody><?php foreach ($R['payments'] as $r) { ?>
                <tr><td><?php echo _d($r->date); ?></td><td><a href="<?php echo admin_url('shra/rider/' . $r->rider_id); ?>" class="strong"><?php echo html_escape($r->full_name); ?></a><span class="sub"><?php echo html_escape($r->rider_no); ?></span></td><td><?php echo html_escape($r->package_name); ?><span class="sub"><?php echo html_escape($r->enrollment_no); ?></span></td><td><?php echo html_escape($r->mode ?: '—'); ?></td><td class="shra-muted"><?php echo html_escape($r->transactionid ?: '—'); ?></td><td class="num strong" style="color:var(--green)"><?php echo $money($r->amount); ?></td><td style="text-align:right"><a href="<?php echo admin_url('payments/payment/' . $r->id); ?>" target="_blank" class="shra-btn shra-btn-outline shra-btn-sm" title="Open payment"><i class="fa fa-external-link"></i></a></td></tr>
            <?php } ?></tbody>
        </table></div><?php } ?>
    </div>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
