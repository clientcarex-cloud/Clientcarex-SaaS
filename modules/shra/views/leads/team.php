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
    $tot = ['assigned' => 0, 'calls' => 0, 'visits_booked' => 0, 'visited' => 0, 'won' => 0, 'renewals' => 0, 'revenue' => 0, 'collected' => 0, 'advance' => 0, 'advance_count' => 0, 'advance_others' => 0, 'advance_others_count' => 0, 'overdue_now' => 0, 'open_now' => 0, 'lost' => 0];
    foreach ($rows as $r) { foreach ($tot as $k => $v) { $tot[$k] += (float) $r->$k; } }
    // One link per agent into the payments screen, already filtered to their advances in this period.
    $adv_link = function ($name) use ($from, $to) {
        return admin_url('shra/payments?' . http_build_query(['view' => 'advances', 'range' => 'custom', 'from' => $from, 'to' => $to, 'q' => $name]));
    };
    ?>
    <div class="shra-stats shra-stats-6">
        <div class="shra-stat"><div class="shra-stat-label">Leads</div><div class="shra-stat-value"><?php echo (int) $tot['assigned']; ?></div><div class="shra-stat-sub"><?php echo (int) $tot['open_now']; ?> open now</div><div class="shra-stat-icon"><i class="fa fa-phone-volume"></i></div></div>
        <div class="shra-stat"><div class="shra-stat-label">Calls</div><div class="shra-stat-value"><?php echo (int) $tot['calls']; ?></div><div class="shra-stat-sub"><?php echo $tot['assigned'] ? round($tot['calls'] / $tot['assigned'], 1) : 0; ?> per lead</div><div class="shra-stat-icon"><i class="fa fa-phone"></i></div></div>
        <div class="shra-stat"><div class="shra-stat-label">Visited</div><div class="shra-stat-value"><?php echo (int) $tot['visited']; ?></div><div class="shra-stat-sub"><?php echo (int) $tot['visits_booked']; ?> booked · <?php echo $tot['visits_booked'] ? round($tot['visited'] / $tot['visits_booked'] * 100) : 0; ?>% show</div><div class="shra-stat-icon"><i class="fa fa-calendar-check"></i></div></div>
        <div class="shra-stat"><div class="shra-stat-label">Joined</div><div class="shra-stat-value"><?php echo (int) $tot['won']; ?></div><div class="shra-stat-sub"><?php echo $tot['assigned'] ? round($tot['won'] / $tot['assigned'] * 100) : 0; ?>% conversion · <?php echo (int) $tot['lost']; ?> lost</div><div class="shra-stat-icon"><i class="fa fa-trophy"></i></div></div>
        <div class="shra-stat"><div class="shra-stat-label">Collected</div><div class="shra-stat-value" style="font-size:22px;color:var(--green)"><?php echo shra_money($tot['advance']); ?></div><div class="shra-stat-sub"><?php echo (int) $tot['advance_count']; ?> advances taken on calls</div><div class="shra-stat-icon"><i class="fa fa-hand-holding-dollar"></i></div></div>
        <div class="shra-stat"><div class="shra-stat-label">Revenue billed</div><div class="shra-stat-value" style="font-size:22px"><?php echo shra_money($tot['revenue']); ?></div><div class="shra-stat-sub"><?php echo shra_money($tot['collected']); ?> paid on bills · <?php echo (int) $tot['renewals']; ?> renewals</div><div class="shra-stat-icon"><i class="fa fa-indian-rupee-sign"></i></div></div>
    </div>

    <div class="shra-card shra-lb" id="shra-lb">
        <div class="shra-card-head">
            <h4><i class="fa fa-ranking-star" style="color:var(--gold)"></i> Leaderboard <span class="thin">· ranked by revenue billed</span></h4>
            <label class="shra-lb-toggle"><input type="checkbox" id="shra-lb-more"> Show pipeline detail</label>
        </div>
        <div class="shra-lb-guide">
            <span><i class="fa fa-hand-holding-dollar" style="color:var(--green)"></i> <b>Collected by agent</b> — advances the agent took on calls themselves (screenshot money, before any bill)</span>
            <span><i class="fa fa-people-arrows" style="color:var(--ink-2)"></i> <b>Collected by others</b> — advances taken on this agent's leads by another employee (a colleague or the counter)</span>
            <span><i class="fa fa-file-invoice" style="color:var(--gold)"></i> <b>Revenue billed</b> — bills raised for riders this agent brought in; credit stays with the agent even if the lead is reassigned later</span>
            <span><i class="fa fa-circle-check" style="color:var(--muted)"></i> <b>Paid on bills</b> — how much of that billed revenue has actually come in</span>
        </div>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead>
                <tr>
                    <th rowspan="2">#</th><th rowspan="2">Agent</th>
                    <th colspan="3" class="grp">Work done</th>
                    <th colspan="2" class="grp">Results</th>
                    <th colspan="4" class="grp grp-money">Money</th>
                    <th rowspan="2">Target</th>
                    <th colspan="6" class="grp lb-x">Pipeline detail</th>
                </tr>
                <tr>
                    <th class="num">Leads</th><th class="num">Calls</th><th class="num">Visited</th>
                    <th class="num">Joined</th><th class="num">Win %</th>
                    <th class="num">Advances by agent</th><th class="num">Advances by others</th><th class="num">Revenue in</th><th class="num">Billed</th>
                    <th class="num lb-x">Reached</th><th class="num lb-x">Confirmed</th><th class="num lb-x">Days to win</th><th class="num lb-x">Open</th><th class="num lb-x">Overdue</th><th class="num lb-x">Stale</th>
                </tr>
            </thead>
            <tbody>
            <?php $i = 0; foreach ($rows as $r) { if (!$r->assigned && !$r->calls && !$r->revenue && !$r->open_now && !$r->advance && !$r->advance_others) { continue; } $i++; ?>
                <tr>
                    <td><?php if ($i <= 3 && ($r->revenue > 0 || $r->advance > 0 || $r->advance_others > 0)) { ?><span class="shra-lb-rank r<?php echo $i; ?>"><?php echo $i; ?></span><?php } else { ?><span class="shra-lb-rank"><?php echo $i; ?></span><?php } ?></td>
                    <td><a href="<?php echo admin_url('shra/shra_leads?agent=' . $r->staffid); ?>" style="font-weight:600"><?php echo html_escape($r->name); ?></a><?php if (!$r->active) { ?> <span class="shra-badge shra-badge-muted">inactive</span><?php } ?></td>
                    <td class="num"><?php echo (int) $r->assigned; ?></td>
                    <td class="num"><?php echo (int) $r->calls; ?><?php if ($r->calls_target) { ?><span class="sub">of <?php echo (int) $r->calls_target; ?> target</span><?php } ?></td>
                    <td class="num"><?php echo (int) $r->visited; ?><span class="sub"><?php echo (int) $r->visits_booked; ?> booked<?php if ($r->visits_target) { ?> · target <?php echo (int) $r->visits_target; } ?></span></td>
                    <td class="num"><b><?php echo (int) $r->won; ?></b><?php if ($r->renewals) { ?><span class="sub">+<?php echo (int) $r->renewals; ?> renewals</span><?php } ?></td>
                    <td class="num"><?php echo (int) $r->win_rate; ?>%</td>
                    <td class="num money cash"><?php if ($r->advance > 0) { ?><a href="<?php echo $adv_link($r->name); ?>" title="See these payments"><?php echo shra_money($r->advance); ?></a><span class="sub"><?php echo (int) $r->advance_count; ?> payment<?php echo $r->advance_count == 1 ? '' : 's'; ?></span><?php } else { ?><span class="shra-muted" style="font-weight:400">—</span><?php } ?></td>
                    <td class="num money others"><?php if ($r->advance_others > 0) { echo shra_money($r->advance_others); ?><span class="sub"><?php echo (int) $r->advance_others_count; ?> payment<?php echo $r->advance_others_count == 1 ? '' : 's'; ?> on their leads</span><?php } else { ?><span class="shra-muted" style="font-weight:400">—</span><?php } ?></td>
                    <td class="num money"><?php if ($r->revenue > 0) { echo shra_money($r->revenue); ?><span class="sub"><?php echo shra_money($r->rev_calls); ?> calls · <?php echo shra_money($r->rev_counter); ?> counter</span><?php } else { ?><span class="shra-muted" style="font-weight:400">—</span><?php } ?></td>
                    <?php // Revenue is money in; this column is what was billed on the same leads, so the pair reads "collected X of Y". ?>
                    <td class="num"><?php if ($r->billed > 0) { echo shra_money($r->billed); ?><span class="sub"><?php echo round($r->revenue / $r->billed * 100); ?>% collected</span><?php } else { ?><span class="shra-muted">—</span><?php } ?></td>
                    <td style="min-width:120px"><?php if ($r->revenue_target > 0) { $pc = min(100, round($r->revenue / $r->revenue_target * 100)); ?><div class="shra-progress"><span style="width:<?php echo $pc; ?>%;background:<?php echo $pc >= 100 ? 'var(--green)' : 'var(--gold)'; ?>"></span></div><span class="sub"><?php echo $pc; ?>% of <?php echo shra_money($r->revenue_target); ?></span><?php } else { ?><span class="shra-muted">no target</span><?php } ?></td>
                    <td class="num lb-x"><?php echo (int) $r->contacted; ?><span class="sub"><?php echo (int) $r->contact_rate; ?>% of leads</span></td>
                    <td class="num lb-x"><?php echo (int) $r->confirmed; ?></td>
                    <td class="num lb-x"><?php echo $r->avg_days !== null ? $r->avg_days : '—'; ?></td>
                    <td class="num lb-x"><?php echo (int) $r->open_now; ?></td>
                    <td class="num lb-x"><?php echo $r->overdue_now ? '<span class="shra-badge shra-badge-red">' . (int) $r->overdue_now . '</span>' : '0'; ?></td>
                    <td class="num lb-x"><?php echo (int) $r->stale_now; ?></td>
                </tr>
            <?php } ?>
            <?php if (!$i) { ?><tr><td colspan="18" class="shra-muted" style="text-align:center;padding:26px">No agent activity in this period.</td></tr><?php } ?>
            </tbody>
            <?php if ($i) { ?>
            <tfoot>
                <tr>
                    <td></td><td>Team total</td>
                    <td class="num"><?php echo (int) $tot['assigned']; ?></td>
                    <td class="num"><?php echo (int) $tot['calls']; ?></td>
                    <td class="num"><?php echo (int) $tot['visited']; ?><span class="sub"><?php echo (int) $tot['visits_booked']; ?> booked</span></td>
                    <td class="num"><?php echo (int) $tot['won']; ?><?php if ($tot['renewals']) { ?><span class="sub">+<?php echo (int) $tot['renewals']; ?> renewals</span><?php } ?></td>
                    <td class="num"><?php echo $tot['assigned'] ? round($tot['won'] / $tot['assigned'] * 100) : 0; ?>%</td>
                    <td class="num money cash"><?php echo shra_money($tot['advance']); ?><span class="sub"><?php echo (int) $tot['advance_count']; ?> payments</span></td>
                    <td class="num money others"><?php echo shra_money($tot['advance_others']); ?><span class="sub"><?php echo (int) $tot['advance_others_count']; ?> payments</span></td>
                    <td class="num money"><?php echo shra_money($tot['revenue']); ?></td>
                    <td class="num"><?php echo shra_money($tot['collected']); ?><?php if ($tot['revenue'] > 0) { ?><span class="sub"><?php echo round($tot['collected'] / $tot['revenue'] * 100); ?>% of billed</span><?php } ?></td>
                    <td></td>
                    <td class="num lb-x"></td><td class="num lb-x"></td><td class="num lb-x"></td>
                    <td class="num lb-x"><?php echo (int) $tot['open_now']; ?></td>
                    <td class="num lb-x"><?php echo (int) $tot['overdue_now']; ?></td>
                    <td class="num lb-x"></td>
                </tr>
            </tfoot>
            <?php } ?>
        </table></div>
    </div>
    <script>
    (function () {
        var card = document.getElementById('shra-lb'), box = document.getElementById('shra-lb-more'), key = 'shra_lb_more';
        if (!card || !box) { return; }
        try { if (localStorage.getItem(key) === '1') { box.checked = true; card.classList.add('more'); } } catch (e) {}
        box.addEventListener('change', function () {
            card.classList.toggle('more', box.checked);
            try { localStorage.setItem(key, box.checked ? '1' : '0'); } catch (e) {}
        });
    })();
    </script>

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
                    <a href="<?php echo admin_url('shra/shra_leads?scope=all&stage=' . $k); ?>" class="shra-funnel-row"><span><?php echo $d[0]; ?></span><div class="shra-progress" style="flex:1"><span style="width:<?php echo round($n / $max * 100); ?>%;background:<?php echo $d[2]; ?>"></span></div><b><?php echo $n; ?></b></a>
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
