<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'team'; include __DIR__ . '/../_nav.php'; ?>

    <form method="get" class="shra-toolbar" style="justify-content:space-between">
        <div class="shra-seg" style="margin:0">
            <?php foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'last_month' => 'Last month', 'quarter' => '3 months', 'year' => 'This year', 'all' => 'All time'] as $k => $v) { ?>
                <label><input type="radio" name="range" value="<?php echo $k; ?>" <?php echo $preset === $k ? 'checked' : ''; ?> onclick="this.form.submit()"><span><?php echo $v; ?></span></label>
            <?php } ?>
        </div>
        <div class="shra-toolbar" style="margin:0">
            <input type="hidden" name="range" value="custom">
            <input type="date" name="from" class="form-control" style="width:auto" value="<?php echo $from; ?>">
            <input type="date" name="to" class="form-control" style="width:auto" value="<?php echo $to; ?>">
            <button class="shra-btn shra-btn-outline shra-btn-sm">Apply</button>
        </div>
    </form>
    <h4 class="shra-title" style="margin:4px 0 14px">Team performance <span class="thin">· <?php echo date('d M Y', strtotime($from)); ?> – <?php echo date('d M Y', strtotime($to)); ?></span></h4>

    <?php
    $tot = ['assigned' => 0, 'calls' => 0, 'visits_booked' => 0, 'visited' => 0, 'won' => 0, 'renewals' => 0, 'revenue' => 0, 'collected' => 0, 'overdue_now' => 0, 'open_now' => 0, 'lost' => 0];
    foreach ($rows as $r) { foreach ($tot as $k => $v) { $tot[$k] += (float) $r->$k; } }
    ?>
    <div class="shra-stats shra-stats-6">
        <div class="shra-stat"><div class="shra-stat-label">Leads</div><div class="shra-stat-value"><?php echo (int) $tot['assigned']; ?></div><div class="shra-stat-sub"><?php echo (int) $tot['open_now']; ?> open now</div><div class="shra-stat-icon"><i class="fa fa-phone-volume"></i></div></div>
        <div class="shra-stat"><div class="shra-stat-label">Calls</div><div class="shra-stat-value"><?php echo (int) $tot['calls']; ?></div><div class="shra-stat-sub"><?php echo $tot['assigned'] ? round($tot['calls'] / $tot['assigned'], 1) : 0; ?> per lead</div><div class="shra-stat-icon"><i class="fa fa-phone"></i></div></div>
        <div class="shra-stat"><div class="shra-stat-label">Visited</div><div class="shra-stat-value"><?php echo (int) $tot['visited']; ?></div><div class="shra-stat-sub"><?php echo (int) $tot['visits_booked']; ?> booked · <?php echo $tot['visits_booked'] ? round($tot['visited'] / $tot['visits_booked'] * 100) : 0; ?>% show</div><div class="shra-stat-icon"><i class="fa fa-calendar-check"></i></div></div>
        <div class="shra-stat"><div class="shra-stat-label">Joined</div><div class="shra-stat-value"><?php echo (int) $tot['won']; ?></div><div class="shra-stat-sub"><?php echo $tot['assigned'] ? round($tot['won'] / $tot['assigned'] * 100) : 0; ?>% conversion · <?php echo (int) $tot['lost']; ?> lost</div><div class="shra-stat-icon"><i class="fa fa-trophy"></i></div></div>
        <div class="shra-stat"><div class="shra-stat-label">Revenue from leads</div><div class="shra-stat-value" style="font-size:22px"><?php echo shra_money($tot['revenue']); ?></div><div class="shra-stat-sub"><?php echo shra_money($tot['collected']); ?> collected · <?php echo (int) $tot['renewals']; ?> renewals</div><div class="shra-stat-icon"><i class="fa fa-indian-rupee-sign"></i></div></div>
        <div class="shra-stat"><div class="shra-stat-label">Overdue now</div><div class="shra-stat-value" style="color:<?php echo $tot['overdue_now'] ? 'var(--red)' : 'inherit'; ?>"><?php echo (int) $tot['overdue_now']; ?></div><div class="shra-stat-sub">follow-ups past due</div><div class="shra-stat-icon"><i class="fa fa-exclamation"></i></div></div>
    </div>

    <div class="shra-card">
        <div class="shra-card-head"><h4><i class="fa fa-ranking-star" style="color:var(--gold)"></i> Leaderboard</h4><span class="help" style="margin:0">Revenue is frozen at billing time to the agent who owned the lead — reassignments never move past credit.</span></div>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>#</th><th>Agent</th><th class="num">Leads</th><th class="num">Calls</th><th class="num">Reached</th><th class="num">Visits booked</th><th class="num">Visited</th><th class="num">Show %</th><th class="num">Confirmed</th><th class="num">Joined</th><th class="num">Win %</th><th class="num">Days to win</th><th class="num">Revenue</th><th class="num">Collected</th><th class="num">Open</th><th class="num">Overdue</th><th class="num">Stale</th><th>Target</th></tr></thead>
            <tbody>
            <?php $i = 0; foreach ($rows as $r) { if (!$r->assigned && !$r->calls && !$r->revenue && !$r->open_now) { continue; } $i++; ?>
                <tr>
                    <td><?php echo $i <= 3 && $r->revenue > 0 ? '<span class="shra-badge shra-badge-gold">' . $i . '</span>' : $i; ?></td>
                    <td><a href="<?php echo admin_url('shra/shra_leads?agent=' . $r->staffid); ?>" style="font-weight:600"><?php echo html_escape($r->name); ?></a><?php if (!$r->active) { ?> <span class="shra-badge shra-badge-muted">inactive</span><?php } ?></td>
                    <td class="num"><?php echo (int) $r->assigned; ?></td>
                    <td class="num"><?php echo (int) $r->calls; ?><?php if ($r->calls_target) { ?><div class="shra-muted" style="font-size:10px">/ <?php echo (int) $r->calls_target; ?></div><?php } ?></td>
                    <td class="num"><?php echo (int) $r->contacted; ?> <span class="shra-muted" style="font-size:10px"><?php echo (int) $r->contact_rate; ?>%</span></td>
                    <td class="num"><?php echo (int) $r->visits_booked; ?><?php if ($r->visits_target) { ?><div class="shra-muted" style="font-size:10px">/ <?php echo (int) $r->visits_target; ?></div><?php } ?></td>
                    <td class="num"><?php echo (int) $r->visited; ?></td>
                    <td class="num"><?php echo (int) $r->show_rate; ?>%</td>
                    <td class="num"><?php echo (int) $r->confirmed; ?></td>
                    <td class="num"><b><?php echo (int) $r->won; ?></b><?php if ($r->renewals) { ?> <span class="shra-muted" style="font-size:10px">+<?php echo (int) $r->renewals; ?> renew</span><?php } ?></td>
                    <td class="num"><?php echo (int) $r->win_rate; ?>%</td>
                    <td class="num"><?php echo $r->avg_days !== null ? $r->avg_days : '—'; ?></td>
                    <td class="num"><b><?php echo shra_money($r->revenue); ?></b></td>
                    <td class="num"><?php echo shra_money($r->collected); ?></td>
                    <td class="num"><?php echo (int) $r->open_now; ?></td>
                    <td class="num"><?php echo $r->overdue_now ? '<span class="shra-badge shra-badge-red">' . (int) $r->overdue_now . '</span>' : '0'; ?></td>
                    <td class="num"><?php echo (int) $r->stale_now; ?></td>
                    <td style="min-width:110px"><?php if ($r->revenue_target > 0) { $pc = min(100, round($r->revenue / $r->revenue_target * 100)); ?><div class="shra-progress"><span style="width:<?php echo $pc; ?>%;background:<?php echo $pc >= 100 ? 'var(--green)' : 'var(--gold)'; ?>"></span></div><div class="shra-muted" style="font-size:10px"><?php echo $pc; ?>% of <?php echo shra_money($r->revenue_target); ?></div><?php } else { ?><span class="shra-muted">—</span><?php } ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table></div>
    </div>

    <div class="shra-profile shra-mt" style="grid-template-columns:minmax(0,1.4fr) minmax(0,1fr)">
        <div class="shra-card">
            <div class="shra-card-head"><h4><i class="fa fa-bullhorn" style="color:var(--gold)"></i> Sources &amp; ROI</h4><?php if ($can_manage) { ?><a href="<?php echo admin_url('shra/shra_leads/settings'); ?>" class="help" style="margin:0">Set monthly spend →</a><?php } ?></div>
            <div class="shra-table-wrap"><table class="shra-table">
                <thead><tr><th>Source</th><th class="num">Leads</th><th class="num">Visited</th><th class="num">Joined</th><th class="num">Conv %</th><th class="num">Lost</th><th class="num">Revenue</th><th class="num">Spend</th><th class="num">CPL</th><th class="num">ROI ×</th></tr></thead>
                <tbody><?php foreach ($sources_stats as $s) { if (!$s->leads && !$s->revenue) { continue; } ?>
                    <tr><td><?php echo html_escape($s->name); ?></td><td class="num"><?php echo (int) $s->leads; ?></td><td class="num"><?php echo (int) $s->visited; ?></td><td class="num"><b><?php echo (int) $s->won; ?></b></td><td class="num"><?php echo (int) $s->conv; ?>%</td><td class="num"><?php echo (int) $s->lost; ?></td><td class="num"><b><?php echo shra_money($s->revenue); ?></b></td><td class="num"><?php echo $s->cost > 0 ? shra_money($s->cost) : '—'; ?></td><td class="num"><?php echo $s->cpl !== null ? shra_money($s->cpl) : '—'; ?></td><td class="num"><?php echo $s->roi !== null ? $s->roi : '—'; ?></td></tr>
                <?php } ?></tbody>
            </table></div>
        </div>
        <div>
            <div class="shra-card">
                <div class="shra-card-head"><h4><i class="fa fa-filter" style="color:var(--gold)"></i> Funnel (now)</h4></div>
                <div class="shra-card-body">
                    <?php $max = max(1, max($funnel)); foreach (shra_lead_stage_defs() as $k => $d) { $n = (int) ($funnel[$k] ?? 0); ?>
                    <a href="<?php echo admin_url('shra/shra_leads/pipeline?stage=' . $k); ?>" class="shra-funnel-row"><span><?php echo $d[0]; ?></span><div class="shra-progress" style="flex:1"><span style="width:<?php echo round($n / $max * 100); ?>%;background:<?php echo $d[2]; ?>"></span></div><b><?php echo $n; ?></b></a>
                    <?php } ?>
                </div>
            </div>
            <div class="shra-card shra-mt">
                <div class="shra-card-head"><h4><i class="fa fa-xmark" style="color:var(--red)"></i> Why we lose</h4></div>
                <div class="shra-card-body">
                    <?php if (!count($lost)) { ?><div class="shra-muted">No lost leads in this period.</div><?php } else { $lm = max(1, max(array_map(function ($x) { return (int) $x->c; }, $lost))); foreach ($lost as $x) { ?>
                    <div class="shra-funnel-row"><span><?php echo html_escape($x->reason ?: 'Unspecified'); ?></span><div class="shra-progress" style="flex:1"><span style="width:<?php echo round($x->c / $lm * 100); ?>%;background:var(--red)"></span></div><b><?php echo (int) $x->c; ?></b></div>
                    <?php } } ?>
                </div>
            </div>
        </div>
    </div>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
