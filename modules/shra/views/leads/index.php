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
    // "All agents" only exists on the all-leads scope; the queue always belongs to somebody.
    $agent_param = (string) $this->input->get('agent');
    $sel_agent   = ($all && $agent_param === '') ? '' : (string) $agent;

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
        <div class="shra-kpi-bar">
            <div class="shra-kpi"><b><?php echo $m ? (int) $m->calls : 0; ?></b><span>calls<?php echo $m && $m->calls_target ? ' / ' . (int) $m->calls_target : ''; ?></span></div>
            <div class="shra-kpi"><b><?php echo $m ? (int) $m->visits_booked : 0; ?></b><span>visits<?php echo $m ? ' · ' . (int) $m->show_rate . '% show' : ''; ?></span></div>
            <div class="shra-kpi"><b><?php echo $m ? (int) $m->won : 0; ?></b><span>joined<?php echo $m ? ' · ' . (int) $m->win_rate . '%' : ''; ?></span></div>
            <div class="shra-kpi"><b><?php echo shra_money($rev); ?></b><span><?php echo $rev_t > 0 ? min(999, round($rev / $rev_t * 100)) . '% of ' . shra_money($rev_t) : 'this month'; ?></span></div>
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
                    <?php if ($all) { ?><option value="" <?php echo $sel_agent === '' ? 'selected' : ''; ?>>All agents</option><?php } ?>
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
                    <th>Lead</th>
                    <th>Phone</th>
                    <th>Stage</th>
                    <th>Next action</th>
                    <th>Visit</th>
                    <th class="num">Calls</th>
                    <th></th>
                    <th><?php echo $can_all ? 'Source / agent' : 'Source'; ?></th>
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

    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php include __DIR__ . '/partials/modals.php'; ?>
<?php init_tail(); ?>
</body>
</html>
