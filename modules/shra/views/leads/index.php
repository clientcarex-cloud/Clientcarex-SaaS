<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'leads'; include __DIR__ . '/../_nav.php'; ?>

    <?php
    $m     = $month;
    $rev   = $m ? (float) $m->revenue : 0;
    // A monthly target only means something over a whole calendar month — any other
    // window shows the plain numbers rather than a percentage of the wrong thing.
    $tg_on = !empty($range['is_month']);
    $rev_t = $tg_on && $m && $m->revenue_target ? (float) $m->revenue_target : 0;
    // Advances taken on calls: real money in, before any bill exists. Without this the
    // header reads zero all month while agents are collecting. One agent's header also
    // counts payments a colleague keyed in on their leads — the lead still paid, and the
    // row already shows it. "All staff" must not, or that money is counted twice.
    $adv_own = $m && isset($m->advance) ? (float) $m->advance : 0;
    $adv_oth = $agent && $m && isset($m->advance_others) ? (float) $m->advance_others : 0;
    $adv     = $adv_own + $adv_oth;
    $adv_n   = ($m && isset($m->advance_count) ? (int) $m->advance_count : 0)
             + ($agent && $m && isset($m->advance_others_count) ? (int) $m->advance_others_count : 0);
    $all   = $scope === 'all';
    // What the numbers cover: the picked range, or this month when none is picked.
    $period = !empty($range['on']) ? $range['label'] : 'this month';

    // One list, in the order it should be worked: overdue first, then by how late each
    // one is. No-shows join it rather than sitting apart — skip any already in the list.
    $seen = [];
    $work = [];
    foreach ($rows as $l) { $seen[$l->id] = true; $work[] = [$l, shra_lead_bucket($l)]; }
    foreach ($no_shows as $l) {
        if (!isset($seen[$l->id])) { $work[] = [$l, 'noshow']; }
    }
    shra_lead_sort_work($work);
    // agent 0 = "All staff" — everyone's leads at once, both scopes. Admins land on it.
    $agent_param = (string) $this->input->get('agent');
    $sel_agent   = ($all && $agent_param === '') ? '' : ($agent ? (string) $agent : '');

    $qs = function (array $extra = []) use ($scope, $sel_agent, $filters) {
        $q = array_merge([
            'scope' => $scope,
            'agent' => $sel_agent,
            'range' => $filters['range'],
            'from'  => $filters['from'],
            'to'    => $filters['to'],
        ], $extra);

        return admin_url('shra/shra_leads?' . http_build_query(array_filter(array_map('strval', $q))));
    };
    ?>

    <?php
    // The training card lives on the dashboard — but calling agents never see the
    // dashboard (shra_home_url sends them straight here), so for them this list IS
    // their home screen and the card belongs on it. Anyone with dashboard access
    // has already seen it there, so it is not repeated.
    if (!shra_can('view')) { echo shra_training_card(); }
    ?>

    <!-- ── Funnel + numbers ───────────────────────────────────────── -->
    <div class="shra-hd">
        <div class="shra-funnel-bar">
            <?php foreach (shra_lead_stage_defs() as $k => $d) { if ($k === 'junk') { continue; } $c = (int) ($funnel[$k] ?? 0); ?>
                <a href="<?php echo $qs(['scope' => 'all', 'stage' => $k]); ?>"
                   class="shra-fn<?php echo $c ? '' : ' zero'; ?><?php echo $filters['stage'] === $k ? ' on' : ''; ?>" title="Show <?php echo $d[0]; ?> leads">
                    <i style="background:<?php echo $d[2]; ?>"></i><span><?php echo $d[0]; ?></span><b><?php echo $c; ?></b>
                </a>
            <?php } ?>
        </div>
        <?php
        // One strip: the month's numbers, how each tracks against target, and the
        // EOD button. The numbers follow the agent filter — one agent's month, or
        // the whole team's totals on "All staff" — and so do the targets under them.
        $tg_name = $agent ? '' : 'All staff';
        foreach ($agents as $a) {
            if ((int) $a->staffid === (int) $agent) { $tg_name = $a->full_name; }
        }
        $pace = shra_lead_target_progress(1, 1, $range['pace_date']); // day / days / % of the month gone
        // Each tile carries the key its drill-down uses, and a line saying what the
        // number actually counts — the two money tiles are read as one thing far too often.
        $mx   = [
            ['calls', 'Calls', 'fa-phone', $m ? (float) $m->calls : 0, $tg_on && $m ? (float) $m->calls_target : 0, false, $period, null,
                'Calls and WhatsApp logged in this period. Click to see those leads.'],
            ['visits', 'Visits booked', 'fa-calendar-check', $m ? (float) $m->visits_booked : 0, $tg_on && $m ? (float) $m->visits_target : 0, false,
                $m && $m->visits_booked ? (int) $m->show_rate . '% showed up' : $period, null,
                'Visits booked in this period. Click to see those leads.'],
            // Joined has no monthly target — its bar is the conversion rate instead.
            ['joined', 'Joined', 'fa-trophy', $m ? (float) $m->won : 0, 0, false,
                $m && $m->assigned ? 'of ' . (int) $m->assigned . ' leads · ' . (int) $m->win_rate . '%' : $period,
                $m ? (int) $m->win_rate : 0,
                'Leads billed for the first time in this period. Click to see them.'],
            // Collected = advances taken on calls (screenshot money). Revenue = what has
            // actually been billed. They are two different ledgers, so they get two tiles
            // rather than one sum that would double-count once the lead is billed.
            ['collected', 'Collected', 'fa-hand-holding-dollar', $adv, 0, true,
                $adv_n ? $adv_n . ' advance' . ($adv_n == 1 ? '' : 's') . ' on calls' . ($adv_oth > 0 ? ' · ' . shra_money($adv_oth) . ' by others' : '') : 'no advances yet', null,
                'Advances taken on calls, before any bill exists. Click to see those leads.'],
            // A zero here reads as a fault unless the screen says why, so at zero the tile
            // spells it out instead of showing the per-day pace it would need.
            ['revenue', 'Revenue', 'fa-indian-rupee-sign', $rev, $rev_t, true,
                $rev > 0 ? 'billed · ' . shra_money((float) $m->collected) . ' paid' : 'no bills raised yet', null,
                'Billed at the counter — it only moves when a bill is raised, so advances on calls do not count here. Click to see the billed leads.',
                $rev <= 0],
        ];
        $tg_any = ($tg_on && $m && ($m->calls_target > 0 || $m->visits_target > 0 || $rev_t > 0));
        ?>
        <div class="shra-mrow">
            <?php foreach ($mx as $x) {
                list($x_key, $x_label, $x_icon, $x_val, $x_target, $x_money, $x_alt, $x_pct, $x_help) = $x;
                $x_say = !empty($x[9]);   // show the note rather than the target pace
                $pr    = shra_lead_target_progress($x_val, $x_target, $range['pace_date']);
                $state = $pr ? shra_lead_target_state($pr) : ($x_pct !== null ? 'plain' : 'none');
                $fmt   = function ($v) use ($x_money) { return $x_money ? shra_money($v) : number_format((float) $v); };
                $width = $pr ? min(100, $pr['pct']) : min(100, (int) $x_pct);
                // A zero has nothing behind it, so it stays a plain tile rather than a dead link.
                $x_link = $x_val > 0 ? $qs(['scope' => 'all', 'metric' => $x_key]) : '';
            ?>
            <?php echo $x_link ? '<a href="' . $x_link . '"' : '<div'; ?> class="shra-mx <?php echo $state; ?><?php echo $x_money ? ' wide' : ''; ?><?php echo $metric === $x_key ? ' on' : ''; ?><?php echo $x_link ? ' link' : ''; ?>" title="<?php echo html_escape($x_help); ?>">
                <div class="shra-mx-hd">
                    <span><i class="fa <?php echo $x_icon; ?>"></i> <?php echo $x_label; ?></span>
                    <?php if ($pr) { ?><u><?php echo $pr['pct']; ?>%</u><?php } ?>
                </div>
                <div class="shra-mx-val">
                    <b><?php echo $fmt($x_val); ?><?php if ($pr) { ?><em> / <?php echo $fmt($x_target); ?></em><?php } ?></b>
                    <span<?php echo $pr && !$pr['done'] && $pr['days_left'] > 0
                        ? ' title="' . $fmt(ceil($pr['per_day'])) . ' a day for the ' . $pr['days_left'] . ' days left in ' . date('F') . '"' : ''; ?>><?php
                        if (!$pr || $x_say) {
                            echo $x_alt;
                        } elseif ($pr['done']) {
                            echo 'target hit';
                        } elseif ($pr['days_left'] > 0) {
                            echo $fmt(ceil($pr['per_day'])) . '/day';
                        } else {
                            echo $fmt($pr['left']) . ' short';
                        }
                    ?></span>
                </div>
                <div class="shra-mx-track">
                    <div class="shra-progress"><span style="width:<?php echo $width; ?>%"></span></div>
                    <?php if ($pr) { ?><i style="left:<?php echo min(100, $pr['pace']); ?>%" title="Where you should be on day <?php echo $pr['day']; ?>"></i><?php } ?>
                </div>
            <?php echo $x_link ? '</a>' : '</div>'; ?>
            <?php } ?>

            <div class="shra-mx-aside">
                <span class="shra-mx-meta">
                    <b><i class="fa fa-bullseye"></i> <?php echo $tg_name ? html_escape($tg_name) : 'Target'; ?></b>
                    <?php if ($tg_on) { ?>
                        <?php echo $range['on'] ? html_escape($range['label']) : date('M Y'); ?> &middot; day <?php echo $pace['day']; ?>/<?php echo $pace['days']; ?> &middot; <?php echo $pace['pace']; ?>% gone<?php if (!$tg_any && $can_manage) { ?>
                            &middot; <a href="<?php echo admin_url('shra/shra_leads/settings'); ?>">set targets</a>
                        <?php } ?>
                    <?php } else { ?>
                        <?php echo html_escape($range['label']); ?> &middot; no target for this range
                    <?php } ?>
                </span>
                <button type="button" class="shra-btn shra-btn-primary shra-btn-sm" data-shra-eod="<?php echo (int) $agent; ?>" title="End-of-day report, ready for WhatsApp">
                    <i class="fa-solid fa-file-lines"></i> EOD Report
                </button>
            </div>
        </div>
    </div>

    <?php if ($metric) {
        // A drill-down answers a question about a period, so say which one — the list
        // below is no longer "leads added", it is "leads behind that number".
        $mx_labels = Shra_leads_model::metrics();
        $mx_from   = $range['on'] ? $range['label'] : date('F Y');
    ?>
    <div class="shra-drill">
        <span><i class="fa fa-filter"></i> <b><?php echo html_escape($mx_labels[$metric]); ?></b> &middot; the leads behind that number
            &middot; <?php echo html_escape($tg_name ?: 'All staff'); ?> &middot; <?php echo html_escape($mx_from); ?></span>
        <a href="<?php echo $qs(['metric' => '']); ?>" class="shra-btn shra-btn-outline shra-btn-xs"><i class="fa fa-xmark"></i> Show all leads</a>
    </div>
    <?php } ?>

    <!-- ── Filters ────────────────────────────────────────────────── -->
    <div class="shra-fl" id="shra-filters" data-scope="<?php echo $scope; ?>">
        <div class="shra-fl-right">
            <div class="shra-search"><i class="fa fa-search"></i><input type="text" id="shra-q" class="form-control" placeholder="Search name, phone, city…  (/)" autocomplete="off" value="<?php echo html_escape($filters['q']); ?>"></div>
            <select id="shra-f-stage" class="form-control">
                <option value="">All stages</option>
                <?php foreach (shra_lead_stage_defs() as $k => $d) { ?><option value="<?php echo $k; ?>" <?php echo $filters['stage'] === $k ? 'selected' : ''; ?>><?php echo $d[0]; ?></option><?php } ?>
            </select>
            <select id="shra-f-source" class="form-control">
                <option value="">All sources</option>
                <?php foreach ($sources as $s) { ?><option value="<?php echo $s->id; ?>" <?php echo $filters['source'] == $s->id ? 'selected' : ''; ?>><?php echo html_escape($s->name); ?></option><?php } ?>
            </select>
            <form method="get" class="shra-scope">
                <input type="hidden" name="scope" value="<?php echo $scope; ?>">
                <?php if ($metric) { ?><input type="hidden" name="metric" value="<?php echo html_escape($metric); ?>"><?php } ?>
                <?php if ($can_all) { ?>
                <select name="agent" class="form-control" onchange="this.form.submit()" title="Whose leads">
                    <option value="" <?php echo $sel_agent === '' ? 'selected' : ''; ?>>All staff</option>
                    <?php foreach ($agents as $a) { ?><option value="<?php echo $a->staffid; ?>" <?php echo (string) $a->staffid === $sel_agent ? 'selected' : ''; ?>><?php echo html_escape($a->full_name); ?><?php echo $a->staffid == get_staff_user_id() ? ' (me)' : ''; ?></option><?php } ?>
                </select>
                <?php } ?>
                <select name="range" id="shra-f-range" class="form-control" title="When the lead came in">
                    <?php foreach (shra_lead_range_defs() as $rk => $rd) { ?><option value="<?php echo $rk; ?>" <?php echo $filters['range'] === $rk ? 'selected' : ''; ?>><?php echo $rd[0]; ?></option><?php } ?>
                </select>
                <span id="shra-f-dates" class="shra-fl-dates"<?php echo $filters['range'] === 'custom' ? '' : ' hidden'; ?>>
                    <input type="date" name="from" class="form-control" value="<?php echo html_escape($filters['from']); ?>" title="Added from">
                    <input type="date" name="to" class="form-control" value="<?php echo html_escape($filters['to']); ?>" title="Added to">
                </span>
            </form>
        </div>
    </div>

    <!-- ── Work list ──────────────────────────────────────────────── -->
    <div class="shra-card shra-work">
        <table class="shra-table shra-wt">
            <thead>
                <tr>
                    <?php if ($can_manage) { ?><th class="shra-r-sel"><input type="checkbox" class="shra-bulk-all" title="Select every lead on this page"></th><?php } ?>
                    <th class="shra-r-name">Lead</th>
                    <th class="shra-r-phone">Phone</th>
                    <th class="shra-r-stage">Stage</th>
                    <th class="shra-r-due">Next action</th>
                    <th class="shra-r-visit">Visit</th>
                    <th class="shra-r-calls num">Calls</th>
                    <th class="shra-r-paid num">Paid</th>
                    <th class="shra-r-flags"></th>
                    <th class="shra-r-src"><?php echo $can_all ? 'Source / agent' : 'Source'; ?></th>
                    <th class="shra-r-act">Action</th>
                </tr>
            </thead>
            <tbody id="shra-rows">
                <?php foreach ($work as $w) { list($l, $force_bucket) = $w; include __DIR__ . '/partials/lead_row.php'; } unset($force_bucket); ?>
            </tbody>
        </table>
        <?php if (!count($work)) { ?>
            <div class="shra-empty"><i class="fa-solid fa-phone-volume"></i><?php
                echo $metric ? 'Nothing behind that number in this period.'
                    : ($all || $range['on'] ? 'No leads match these filters.' : 'Nothing in your queue — every lead is followed up.'); ?></div>
        <?php } ?>
        <div class="shra-empty" id="shra-none" hidden><i class="fa-solid fa-check" style="color:var(--green)"></i>Nothing matches — widen the stage, source or search.</div>
        <div class="shra-work-foot">
            <span id="shra-count"></span>
            <span class="shra-pager" id="shra-pager" hidden>
                <button type="button" class="shra-pg" data-page="first" title="First page"><i class="fa fa-angles-left"></i></button>
                <button type="button" class="shra-pg" data-page="prev" title="Previous page"><i class="fa fa-angle-left"></i></button>
                <span class="shra-pg-at" id="shra-pager-at"></span>
                <button type="button" class="shra-pg" data-page="next" title="Next page"><i class="fa fa-angle-right"></i></button>
                <button type="button" class="shra-pg" data-page="last" title="Last page"><i class="fa fa-angles-right"></i></button>
                <select id="shra-per" class="form-control" title="Rows per page">
                    <option value="25">25</option>
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                    <option value="0">All</option>
                </select>
            </span>
            <span>
                <?php if (!$all && count($rows) >= 800) { ?><span class="shra-badge shra-badge-muted" title="Queue is capped at 800">first 800</span><?php } ?>
                <?php if ($all && count($rows) >= 1500) { ?><span class="shra-badge shra-badge-muted" title="Narrow the date range to see more">first 1500</span><?php } ?>
                <?php if ($can_manage) { ?><a href="<?php echo admin_url('shra/shra_leads/export?' . http_build_query(array_filter(array_map('strval', ['agent' => $sel_agent, 'metric' => $filters['metric'], 'range' => $filters['range'], 'from' => $filters['from'], 'to' => $filters['to'], 'stage' => $filters['stage']])))); ?>" class="shra-btn shra-btn-outline shra-btn-xs"><i class="fa fa-download"></i> Export</a><?php } ?>
            </span>
        </div>
    </div>

    <?php if ($can_manage) { // shown once something is ticked; deleting stays superadmin-only ?>
    <div id="shra-bulkbar" class="shra-bulkbar" hidden
         data-url="<?php echo admin_url('shra/shra_leads/bulk_delete'); ?>"
         data-confirm="Permanently delete {n} lead(s)? Their call history, payments and revenue credit are removed too. This cannot be undone.">
        <span><b class="shra-bulk-count">0</b> selected</span>
        <button type="button" class="shra-btn shra-btn-outline shra-btn-sm shra-bulk-clear">Clear</button>
        <button type="button" class="shra-btn shra-btn-gold shra-btn-sm shra-bulk-reassign"><i class="fa fa-headset"></i> Reassign</button>
        <?php if (is_admin()) { ?><button type="button" class="shra-btn shra-btn-danger shra-btn-sm shra-bulk-del"><i class="fa fa-trash"></i> Delete selected</button><?php } ?>
    </div>
    <?php } ?>

    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
