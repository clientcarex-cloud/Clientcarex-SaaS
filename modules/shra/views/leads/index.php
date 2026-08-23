<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'leads'; include __DIR__ . '/../_nav.php'; ?>

    <?php
    $m     = $month;
    $rev   = $m ? (float) $m->revenue : 0;
    $rev_t = $m && $m->revenue_target ? (float) $m->revenue_target : 0;

    // Flatten the queues into one work list — the order IS the priority order.
    $order = ['unset', 'overdue', 'today', 'upcoming', 'later'];
    $rows  = [];
    $seen  = [];
    foreach ($order as $k) {
        foreach ($queues[$k] as $l) {
            $rows[]          = $l;
            $seen[$l->id]    = true;
        }
    }
    $ns = [];
    foreach ($no_shows as $l) {
        if (!isset($seen[$l->id])) { $ns[] = $l; }
    }
    $counts = [
        'all'      => count($rows),
        'unset'    => count($queues['unset']),
        'overdue'  => count($queues['overdue']),
        'today'    => count($queues['today']),
        'upcoming' => count($queues['upcoming']),
        'later'    => count($queues['later']),
        'noshow'   => count($ns),
    ];
    $tabs = [
        ['overdue',  'Overdue',   'red'],
        ['today',    'Today',     'gold'],
        ['unset',    'No date',   'red'],
        ['upcoming', 'Next 7 days', ''],
        ['later',    'Later',     ''],
        ['noshow',   'No-show',   'red'],
        ['all',      'All open',  ''],
    ];
    $start = $counts['overdue'] ? 'overdue' : ($counts['today'] ? 'today' : ($counts['unset'] ? 'unset' : 'all'));
    ?>

    <!-- ── Funnel + numbers ───────────────────────────────────────── -->
    <div class="shra-hd">
        <div class="shra-funnel-bar">
            <?php foreach (shra_lead_stage_defs() as $k => $d) { if ($k === 'junk') { continue; } $c = (int) ($funnel[$k] ?? 0); ?>
                <a href="<?php echo admin_url('shra/shra_leads/pipeline?view=list&stage=' . $k . ($can_all && $agent ? '&agent=' . (int) $agent : '')); ?>"
                   class="shra-fn<?php echo $c ? '' : ' zero'; ?>" title="<?php echo $d[0]; ?> — open in pipeline">
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
    <div class="shra-fl" id="shra-filters" data-start="<?php echo $start; ?>">
        <div class="shra-tabs">
            <?php foreach ($tabs as $t) { if (!$counts[$t[0]] && in_array($t[0], ['unset', 'later', 'noshow'])) { continue; } ?>
                <button type="button" class="shra-tab<?php echo $t[2] ? ' t-' . $t[2] : ''; ?>" data-bucket="<?php echo $t[0]; ?>"><?php echo $t[1]; ?> <b><?php echo $counts[$t[0]]; ?></b></button>
            <?php } ?>
        </div>
        <div class="shra-fl-right">
            <div class="shra-search"><i class="fa fa-search"></i><input type="text" id="shra-q" class="form-control" placeholder="Search name, phone, city…  (/)" autocomplete="off"></div>
            <select id="shra-f-stage" class="form-control"><option value="">All stages</option><?php foreach (shra_lead_stage_defs() as $k => $d) { if (in_array($k, ['won', 'lost', 'junk'])) { continue; } ?><option value="<?php echo $k; ?>"><?php echo $d[0]; ?></option><?php } ?></select>
            <select id="shra-f-source" class="form-control"><option value="">All sources</option><?php foreach ($sources as $s) { ?><option value="<?php echo $s->id; ?>"><?php echo html_escape($s->name); ?></option><?php } ?></select>
            <?php if ($can_all) { ?>
            <form method="get" style="margin:0">
                <select name="agent" class="form-control" onchange="this.form.submit()" title="Whose day">
                    <?php foreach ($agents as $a) { ?><option value="<?php echo $a->staffid; ?>" <?php echo $a->staffid == $agent ? 'selected' : ''; ?>><?php echo html_escape($a->full_name); ?><?php echo $a->staffid == get_staff_user_id() ? ' (me)' : ''; ?></option><?php } ?>
                </select>
            </form>
            <?php } ?>
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
        <div class="shra-empty" id="shra-none" hidden><i class="fa-solid fa-check" style="color:var(--green)"></i>Nothing here — pick another tab or clear the search.</div>
        <div class="shra-work-foot"><span id="shra-count"></span><?php if ($can_manage) { ?><a href="<?php echo admin_url('shra/shra_leads/export?agent=' . (int) $agent); ?>" class="shra-btn shra-btn-outline shra-btn-xs"><i class="fa fa-download"></i> Export</a><?php } ?></div>
    </div>

    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php include __DIR__ . '/partials/modals.php'; ?>
<?php init_tail(); ?>
</body>
</html>
