<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'pipeline'; include __DIR__ . '/../_nav.php'; ?>

    <form method="get" class="shra-toolbar" style="margin-bottom:14px">
        <input type="hidden" name="view" value="<?php echo $view; ?>">
        <div class="shra-search grow" style="flex:1;min-width:200px"><i class="fa fa-search"></i><input type="text" name="q" class="form-control" placeholder="Name, phone, city" value="<?php echo html_escape($filters['q']); ?>"></div>
        <?php if ($can_all) { ?>
        <select name="agent" class="form-control" style="width:auto"><option value="">All agents</option><?php foreach ($agents as $a) { ?><option value="<?php echo $a->staffid; ?>" <?php echo $filters['agent'] == $a->staffid ? 'selected' : ''; ?>><?php echo html_escape($a->full_name); ?></option><?php } ?></select>
        <?php } ?>
        <select name="source" class="form-control" style="width:auto"><option value="">All sources</option><?php foreach ($sources as $s) { ?><option value="<?php echo $s->id; ?>" <?php echo $filters['source'] == $s->id ? 'selected' : ''; ?>><?php echo html_escape($s->name); ?></option><?php } ?></select>
        <select name="stage" class="form-control" style="width:auto"><option value="">All stages</option><option value="open" <?php echo $filters['stage'] === 'open' ? 'selected' : ''; ?>>Open only</option><?php foreach (shra_lead_stage_defs() as $k => $d) { ?><option value="<?php echo $k; ?>" <?php echo $filters['stage'] === $k ? 'selected' : ''; ?>><?php echo $d[0]; ?></option><?php } ?></select>
        <input type="date" name="from" class="form-control" style="width:auto" value="<?php echo html_escape($filters['from']); ?>" title="Added from">
        <input type="date" name="to" class="form-control" style="width:auto" value="<?php echo html_escape($filters['to']); ?>" title="Added to">
        <label class="shra-pill" style="cursor:pointer"><input type="checkbox" name="overdue" value="1" <?php echo $filters['overdue'] ? 'checked' : ''; ?>> Overdue</label>
        <label class="shra-pill" style="cursor:pointer"><input type="checkbox" name="stale" value="1" <?php echo $filters['stale'] ? 'checked' : ''; ?>> Stale</label>
        <button class="shra-btn shra-btn-outline">Filter</button>
        <div class="shra-seg" style="margin:0">
            <label><input type="radio" name="view" value="board" <?php echo $view === 'board' ? 'checked' : ''; ?> onclick="this.form.submit()"><span><i class="fa fa-table-columns"></i></span></label>
            <label><input type="radio" name="view" value="list" <?php echo $view === 'list' ? 'checked' : ''; ?> onclick="this.form.submit()"><span><i class="fa fa-list"></i></span></label>
        </div>
        <?php if ($can_manage) { ?><a href="<?php echo admin_url('shra/shra_leads/export?' . http_build_query(array_filter($filters))); ?>" class="shra-btn shra-btn-outline shra-btn-sm" title="Export CSV"><i class="fa fa-download"></i></a><?php } ?>
    </form>

    <?php if ($view === 'board') { ?>
    <div class="shra-board">
        <?php foreach (shra_lead_stage_defs() as $k => $d) { $rows = $cols[$k]; ?>
        <div class="shra-col" data-stage="<?php echo $k; ?>">
            <div class="shra-col-head" style="border-top:3px solid <?php echo $d[2]; ?>"><span><?php echo $d[0]; ?></span><span class="shra-pill shra-col-count"><?php echo count($rows); ?></span></div>
            <div class="shra-col-body">
                <?php foreach ($rows as $l) { include __DIR__ . '/partials/lead_card.php'; } ?>
            </div>
        </div>
        <?php } ?>
    </div>
    <div class="help" style="margin-top:8px">Drag a card to change its stage. Visit / Lost / Confirm ask for details; <b>Customer</b> is set automatically by billing.</div>
    <?php } else { ?>
    <div class="shra-card">
        <?php if (!count($list)) { ?><div class="shra-empty"><i class="fa-solid fa-phone-volume"></i>No leads match.</div><?php } else { ?>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>Lead</th><th>Phone</th><th>For</th><th>Source</th><?php if ($can_all) { ?><th>Agent</th><?php } ?><th>Stage</th><th>Next action</th><th>Visit</th><th class="num">Calls</th><th class="num">Expected</th><th>Added</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($list as $l) { ?>
                <tr class="<?php echo $l->is_overdue ? 'shra-row-over' : ''; ?>">
                    <td><a href="<?php echo shra_lead_url($l->id); ?>" style="font-weight:600"><?php echo html_escape($l->name); ?></a><?php if ($l->city) { ?><div class="shra-muted" style="font-size:11px"><?php echo html_escape($l->city); ?></div><?php } ?></td>
                    <td><a href="<?php echo $l->tel_link; ?>"><?php echo html_escape($l->phonenumber); ?></a></td>
                    <td><?php echo ucfirst($l->rider_for); ?><?php echo $l->rider_age ? ' ' . $l->rider_age . 'y' : ''; ?></td>
                    <td><?php echo html_escape($l->source_name ?: '—'); ?></td>
                    <?php if ($can_all) { ?><td><?php echo html_escape($l->agent_name ?: '—'); ?></td><?php } ?>
                    <td><?php echo shra_lead_stage_badge($l->stage); ?></td>
                    <td><?php echo $l->is_open ? shra_lead_due_text($l->next_action_at) : '—'; ?></td>
                    <td><?php echo $l->visit_date ? date('D d M', strtotime($l->visit_date)) . ' · ' . html_escape($l->visit_slot) : '—'; ?></td>
                    <td class="num"><?php echo (int) $l->call_attempts; ?></td>
                    <td class="num"><?php echo $l->expected_value > 0 ? shra_money($l->expected_value) : '—'; ?></td>
                    <td class="shra-muted" style="font-size:12px"><?php echo date('d M', strtotime($l->dateadded)); ?></td>
                    <td><a href="<?php echo shra_lead_url($l->id); ?>" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa fa-arrow-right"></i></a></td>
                </tr>
            <?php } ?>
            </tbody>
        </table></div>
        <?php } ?>
    </div>
    <?php } ?>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php include __DIR__ . '/partials/modals.php'; ?>
<?php init_tail(); ?>
</body>
</html>
