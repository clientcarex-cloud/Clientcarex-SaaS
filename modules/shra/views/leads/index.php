<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'leads'; include __DIR__ . '/../_nav.php'; ?>

    <?php
    $m     = $month;
    $rev   = $m ? (float) $m->revenue : 0;
    $rev_t = $m && $m->revenue_target ? (float) $m->revenue_target : 0;
    $all   = $scope === 'all';

    // No-shows are appended as their own tab; skip any already in the list.
    $seen = [];
    foreach ($rows as $l) { $seen[$l->id] = true; }
    $ns = [];
    foreach ($no_shows as $l) {
        if (!isset($seen[$l->id])) { $ns[] = $l; }
    }
    // agent 0 = "All staff" — everyone's leads at once, both scopes. Admins land on it.
    $agent_param = (string) $this->input->get('agent');
    $sel_agent   = ($all && $agent_param === '') ? '' : ($agent ? (string) $agent : '');

    $qs = function (array $extra = []) use ($scope, $sel_agent, $filters) {
        $q = array_merge([
            'scope' => $scope,
            'agent' => $sel_agent,
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
        $pace = shra_lead_target_progress(1, 1); // day / days / % of the month gone
        $mx   = [
            ['Calls', 'fa-phone', $m ? (float) $m->calls : 0, $m ? (float) $m->calls_target : 0, false, 'this month', null],
            ['Visits booked', 'fa-calendar-check', $m ? (float) $m->visits_booked : 0, $m ? (float) $m->visits_target : 0, false,
                $m && $m->visits_booked ? (int) $m->show_rate . '% showed up' : 'this month', null],
            // Joined has no monthly target — its bar is the conversion rate instead.
            ['Joined', 'fa-trophy', $m ? (float) $m->won : 0, 0, false,
                $m && $m->assigned ? 'of ' . (int) $m->assigned . ' leads · ' . (int) $m->win_rate . '%' : 'this month',
                $m ? (int) $m->win_rate : 0],
            ['Revenue', 'fa-indian-rupee-sign', $rev, $rev_t, true, 'this month', null],
        ];
        $tg_any = ($m && ($m->calls_target > 0 || $m->visits_target > 0 || $rev_t > 0));
        ?>
        <div class="shra-mrow">
            <?php foreach ($mx as $x) {
                list($x_label, $x_icon, $x_val, $x_target, $x_money, $x_alt, $x_pct) = $x;
                $pr    = shra_lead_target_progress($x_val, $x_target);
                $state = $pr ? shra_lead_target_state($pr) : ($x_pct !== null ? 'plain' : 'none');
                $fmt   = function ($v) use ($x_money) { return $x_money ? shra_money($v) : number_format((float) $v); };
                $width = $pr ? min(100, $pr['pct']) : min(100, (int) $x_pct);
            ?>
            <div class="shra-mx <?php echo $state; ?><?php echo $x_money ? ' wide' : ''; ?>">
                <div class="shra-mx-hd">
                    <span><i class="fa <?php echo $x_icon; ?>"></i> <?php echo $x_label; ?></span>
                    <?php if ($pr) { ?><u><?php echo $pr['pct']; ?>%</u><?php } ?>
                </div>
                <div class="shra-mx-val">
                    <b><?php echo $fmt($x_val); ?><?php if ($pr) { ?><em> / <?php echo $fmt($x_target); ?></em><?php } ?></b>
                    <span<?php echo $pr && !$pr['done'] && $pr['days_left'] > 0
                        ? ' title="' . $fmt(ceil($pr['per_day'])) . ' a day for the ' . $pr['days_left'] . ' days left in ' . date('F') . '"' : ''; ?>><?php
                        if (!$pr) {
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
            </div>
            <?php } ?>

            <div class="shra-mx-aside">
                <span class="shra-mx-meta">
                    <b><i class="fa fa-bullseye"></i> <?php echo $tg_name ? html_escape($tg_name) : 'Target'; ?></b>
                    <?php echo date('M Y'); ?> &middot; day <?php echo $pace['day']; ?>/<?php echo $pace['days']; ?> &middot; <?php echo $pace['pace']; ?>% gone<?php if (!$tg_any && $can_manage) { ?>
                        &middot; <a href="<?php echo admin_url('shra/shra_leads/settings'); ?>">set targets</a>
                    <?php } ?>
                </span>
                <button type="button" class="shra-btn shra-btn-primary shra-btn-sm" data-shra-eod="<?php echo (int) $agent; ?>" title="End-of-day report, ready for WhatsApp">
                    <i class="fa-solid fa-file-lines"></i> EOD Report
                </button>
            </div>
        </div>
    </div>

    <!-- ── Filters ────────────────────────────────────────────────── -->
    <div class="shra-fl" id="shra-filters" data-scope="<?php echo $scope; ?>" data-bucket="<?php echo html_escape($filters['bucket']); ?>">
        <div class="shra-tabs" id="shra-tabs"><!-- filled from the rows below --></div>
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
                <?php if ($can_all) { ?>
                <select name="agent" class="form-control" onchange="this.form.submit()" title="Whose leads">
                    <option value="" <?php echo $sel_agent === '' ? 'selected' : ''; ?>>All staff</option>
                    <?php foreach ($agents as $a) { ?><option value="<?php echo $a->staffid; ?>" <?php echo (string) $a->staffid === $sel_agent ? 'selected' : ''; ?>><?php echo html_escape($a->full_name); ?><?php echo $a->staffid == get_staff_user_id() ? ' (me)' : ''; ?></option><?php } ?>
                </select>
                <?php } ?>
                <?php if ($all) { ?>
                    <input type="date" name="from" class="form-control" value="<?php echo html_escape($filters['from']); ?>" title="Added from" onchange="this.form.submit()">
                    <input type="date" name="to" class="form-control" value="<?php echo html_escape($filters['to']); ?>" title="Added to" onchange="this.form.submit()">
                <?php } ?>
            </form>
            <a href="<?php echo $all ? admin_url('shra/shra_leads') : $qs(['scope' => 'all']); ?>" class="shra-btn shra-btn-outline shra-btn-sm" title="<?php echo $all ? 'Back to my call queue' : 'Show every lead'; ?>">
                <i class="fa <?php echo $all ? 'fa-list-check' : 'fa-layer-group'; ?>"></i> <?php echo $all ? 'My queue' : 'All leads'; ?>
            </a>
            <button type="button" class="shra-btn shra-btn-outline shra-btn-sm" id="shra-f-clear" title="Clear filters"><i class="fa fa-rotate-left"></i></button>
        </div>
    </div>

    <!-- ── Work list ──────────────────────────────────────────────── -->
    <div class="shra-card shra-work">
        <table class="shra-table shra-wt">
            <thead>
                <tr>
                    <?php if (is_admin()) { ?><th class="shra-r-sel"><input type="checkbox" class="shra-bulk-all" title="Select every lead shown"></th><?php } ?>
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
                <?php foreach ($rows as $l) { include __DIR__ . '/partials/lead_row.php'; } ?>
                <?php foreach ($ns as $l) { $force_bucket = 'noshow'; include __DIR__ . '/partials/lead_row.php'; unset($force_bucket); } ?>
            </tbody>
        </table>
        <?php if (!count($rows) && !count($ns)) { ?>
            <div class="shra-empty"><i class="fa-solid fa-phone-volume"></i><?php echo $all ? 'No leads match these filters.' : 'Nothing in your queue — every lead is followed up.'; ?></div>
        <?php } ?>
        <div class="shra-empty" id="shra-none" hidden><i class="fa-solid fa-check" style="color:var(--green)"></i>Nothing here — pick another tab or clear the search.</div>
        <div class="shra-work-foot">
            <span id="shra-count"></span>
            <span>
                <?php if (!$all && count($rows) >= 800) { ?><span class="shra-badge shra-badge-muted" title="Queue is capped at 800">first 800</span><?php } ?>
                <?php if ($all && count($rows) >= 1500) { ?><span class="shra-badge shra-badge-muted" title="Narrow the date range to see more">first 1500</span><?php } ?>
                <?php if ($can_manage) { ?><a href="<?php echo admin_url('shra/shra_leads/export?' . http_build_query(array_filter(array_map('strval', ['agent' => $sel_agent, 'from' => $filters['from'], 'to' => $filters['to'], 'stage' => $filters['stage']])))); ?>" class="shra-btn shra-btn-outline shra-btn-xs"><i class="fa fa-download"></i> Export</a><?php } ?>
            </span>
        </div>
    </div>

    <?php if (is_admin()) { // superadmin only: bulk delete bar, shown once something is ticked ?>
    <div id="shra-bulkbar" class="shra-bulkbar" hidden
         data-url="<?php echo admin_url('shra/shra_leads/bulk_delete'); ?>"
         data-confirm="Permanently delete {n} lead(s)? Their call history, payments and revenue credit are removed too. This cannot be undone.">
        <span><b class="shra-bulk-count">0</b> selected</span>
        <button type="button" class="shra-btn shra-btn-outline shra-btn-sm shra-bulk-clear">Clear</button>
        <button type="button" class="shra-btn shra-btn-danger shra-btn-sm shra-bulk-del"><i class="fa fa-trash"></i> Delete selected</button>
    </div>
    <?php } ?>

    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
