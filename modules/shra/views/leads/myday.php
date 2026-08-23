<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'leads'; include __DIR__ . '/../_nav.php'; ?>

    <?php
    $m      = $month;
    $total  = count($queues['overdue']) + count($queues['today']) + count($queues['upcoming']) + count($queues['later']) + count($queues['unset']);
    $rev    = $m ? (float) $m->revenue : 0;
    $rev_t  = $m && $m->revenue_target ? (float) $m->revenue_target : 0;
    ?>
    <div class="shra-toolbar" style="justify-content:space-between">
        <h4 class="shra-title" style="margin:0">My Day <span class="thin">· <?php echo date('l, d M'); ?></span></h4>
        <?php if ($can_all) { ?>
        <form method="get" class="shra-toolbar" style="margin:0">
            <select name="agent" class="form-control" style="width:auto" onchange="this.form.submit()">
                <?php foreach ($agents as $a) { ?><option value="<?php echo $a->staffid; ?>" <?php echo $a->staffid == $agent ? 'selected' : ''; ?>><?php echo html_escape($a->full_name); ?><?php echo $a->staffid == get_staff_user_id() ? ' (me)' : ''; ?></option><?php } ?>
            </select>
        </form>
        <?php } ?>
    </div>

    <div class="shra-stats shra-stats-6">
        <div class="shra-stat"><div class="shra-stat-label">Overdue</div><div class="shra-stat-value" style="color:<?php echo count($queues['overdue']) ? 'var(--red)' : 'inherit'; ?>"><?php echo count($queues['overdue']); ?></div><div class="shra-stat-sub">call these first</div><div class="shra-stat-icon"><i class="fa fa-exclamation"></i></div></div>
        <div class="shra-stat"><div class="shra-stat-label">Due today</div><div class="shra-stat-value"><?php echo count($queues['today']); ?></div><div class="shra-stat-sub"><?php echo $total; ?> open in total</div><div class="shra-stat-icon"><i class="fa fa-sun"></i></div></div>
        <div class="shra-stat"><div class="shra-stat-label">Calls this month</div><div class="shra-stat-value"><?php echo $m ? (int) $m->calls : 0; ?></div><div class="shra-stat-sub"><?php echo $m && $m->calls_target ? 'target ' . (int) $m->calls_target : ($m ? (int) $m->contact_rate . '% reached' : ''); ?></div><div class="shra-stat-icon"><i class="fa fa-phone"></i></div></div>
        <div class="shra-stat"><div class="shra-stat-label">Visits booked</div><div class="shra-stat-value"><?php echo $m ? (int) $m->visits_booked : 0; ?></div><div class="shra-stat-sub"><?php echo $m ? (int) $m->visited . ' visited · ' . (int) $m->show_rate . '% show' : ''; ?></div><div class="shra-stat-icon"><i class="fa fa-calendar-check"></i></div></div>
        <div class="shra-stat"><div class="shra-stat-label">Joined</div><div class="shra-stat-value"><?php echo $m ? (int) $m->won : 0; ?></div><div class="shra-stat-sub"><?php echo $m ? (int) $m->win_rate . '% of assigned' : ''; ?></div><div class="shra-stat-icon"><i class="fa fa-trophy"></i></div></div>
        <div class="shra-stat"><div class="shra-stat-label">Revenue credited</div><div class="shra-stat-value" style="font-size:22px"><?php echo shra_money($rev); ?></div><div class="shra-stat-sub"><?php if ($rev_t > 0) { ?><div class="shra-progress" style="margin-top:4px"><span style="width:<?php echo min(100, round($rev / $rev_t * 100)); ?>%"></span></div><?php echo min(999, round($rev / $rev_t * 100)); ?>% of <?php echo shra_money($rev_t); ?><?php } else { echo 'this month'; } ?></div><div class="shra-stat-icon"><i class="fa fa-indian-rupee-sign"></i></div></div>
    </div>

    <?php if (count($queues['unset'])) { ?>
    <div class="shra-alert shra-alert-bad" style="margin-bottom:14px"><i class="fa fa-triangle-exclamation"></i> <b><?php echo count($queues['unset']); ?></b> lead<?php echo count($queues['unset']) == 1 ? ' has' : 's have'; ?> no follow-up date — fix now, nothing may sit without a next action.</div>
    <?php } ?>

    <?php
    $sections = [
        ['unset', 'No follow-up set', 'fa-triangle-exclamation', 'var(--red)'],
        ['overdue', 'Overdue', 'fa-exclamation-circle', 'var(--red)'],
        ['today', 'Today', 'fa-sun', 'var(--gold)'],
        ['upcoming', 'Next 7 days', 'fa-calendar', 'var(--brown)'],
        ['later', 'Later', 'fa-clock', 'var(--muted)'],
    ];
    foreach ($sections as $sec) {
        [$key, $label, $icon, $color] = $sec;
        $rows = $queues[$key];
        if (!count($rows) && in_array($key, ['unset', 'later'])) { continue; }
    ?>
    <div class="shra-card" style="margin-bottom:16px">
        <div class="shra-card-head"><h4><i class="fa <?php echo $icon; ?>" style="color:<?php echo $color; ?>"></i> <?php echo $label; ?></h4><span class="shra-pill"><?php echo count($rows); ?></span></div>
        <?php if (!count($rows)) { ?>
            <div class="shra-empty" style="padding:24px"><i class="fa fa-check" style="color:var(--green)"></i><?php echo $key === 'overdue' ? 'Nothing overdue — great discipline.' : ($key === 'today' ? 'Nothing more due today.' : 'Nothing scheduled.'); ?></div>
        <?php } else { ?>
            <div class="shra-lead-list"><?php foreach ($rows as $l) { include __DIR__ . '/partials/lead_card.php'; } ?></div>
        <?php } ?>
    </div>
    <?php } ?>

    <?php if ($can_all && count($no_shows)) { ?>
    <div class="shra-card" style="margin-bottom:16px">
        <div class="shra-card-head"><h4><i class="fa fa-user-slash" style="color:var(--red)"></i> Scheduled visits that never arrived</h4><span class="shra-pill"><?php echo count($no_shows); ?></span></div>
        <div class="shra-lead-list"><?php foreach ($no_shows as $l) { include __DIR__ . '/partials/lead_card.php'; } ?></div>
    </div>
    <?php } ?>

    <div class="shra-card shra-card-cream">
        <div class="shra-card-body" style="display:flex;gap:18px;flex-wrap:wrap;align-items:center">
            <b style="font-size:12px;letter-spacing:1px;text-transform:uppercase;color:var(--muted)">Funnel</b>
            <?php foreach (shra_lead_stage_defs() as $k => $d) { if (in_array($k, ['junk'])) { continue; } ?>
                <a href="<?php echo admin_url('shra/shra_leads/pipeline?stage=' . $k . (!$can_all ? '' : '')); ?>" class="shra-funnel-item"><span style="background:<?php echo $d[2]; ?>"></span><?php echo $d[0]; ?> <b><?php echo (int) ($funnel[$k] ?? 0); ?></b></a>
            <?php } ?>
        </div>
    </div>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php include __DIR__ . '/partials/modals.php'; ?>
<?php init_tail(); ?>
</body>
</html>
