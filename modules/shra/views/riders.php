<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'riders'; include __DIR__ . '/_nav.php'; ?>

    <?php $view = $filters['view']; ?>
    <div class="shra-toolbar" style="margin-bottom:12px">
        <div class="shra-seg" style="margin:0">
            <label><input type="radio" name="shra-view" <?php echo $view === 'home' ? 'checked' : ''; ?> onclick="location.href='<?php echo admin_url('shra/riders'); ?>'"><span><i class="fa-solid fa-sun"></i> Today &amp; due</span></label>
            <label><input type="radio" name="shra-view" <?php echo $view === 'today' ? 'checked' : ''; ?> onclick="location.href='<?php echo admin_url('shra/riders?view=today'); ?>'"><span>Today</span></label>
            <label><input type="radio" name="shra-view" <?php echo $view === 'due' ? 'checked' : ''; ?> onclick="location.href='<?php echo admin_url('shra/riders?view=due'); ?>'"><span>Amount due</span></label>
            <label><input type="radio" name="shra-view" <?php echo $view === 'all' ? 'checked' : ''; ?> onclick="location.href='<?php echo admin_url('shra/riders?view=all'); ?>'"><span>All riders</span></label>
        </div>
        <form method="get" class="shra-toolbar grow" style="margin:0;flex:1">
            <input type="hidden" name="view" value="all">
            <div class="shra-search grow"><i class="fa fa-search"></i><input type="text" name="q" class="form-control" placeholder="Search name, mobile, rider or membership no." value="<?php echo html_escape($filters['q']); ?>"></div>
            <select name="type" class="form-control" style="width:auto"><option value="">All types</option><option value="learner" <?php echo $filters['type'] === 'learner' ? 'selected' : ''; ?>>Learners</option><option value="guest" <?php echo $filters['type'] === 'guest' ? 'selected' : ''; ?>>Guest riders</option></select>
            <select name="level" class="form-control" style="width:auto"><option value="">All levels</option><?php foreach ($levels as $l) { ?><option value="<?php echo html_escape($l); ?>" <?php echo $filters['level'] === $l ? 'selected' : ''; ?>><?php echo html_escape($l); ?></option><?php } ?></select>
            <select name="status" class="form-control" style="width:auto"><option value="">Any status</option><option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select>
            <button class="shra-btn shra-btn-outline">Filter</button>
        </form>
    </div>

    <?php if ($view === 'home') { ?>
        <?php $due_sum = 0; foreach ($due as $d) { $due_sum += (float) $d->total_due; } ?>
        <div class="shra-card" style="margin-bottom:18px">
            <div class="shra-card-head"><h4><i class="fa-solid fa-hand-holding-dollar" style="color:var(--red)"></i> Amount due</h4><span class="shra-pill" style="<?php echo $due_sum > 0 ? 'background:#f8e3e2;color:var(--red)' : ''; ?>"><?php echo count($due); ?> rider<?php echo count($due) == 1 ? '' : 's'; ?> · <?php echo shra_money($due_sum); ?></span></div>
            <?php if (!count($due)) { ?>
                <div class="shra-empty" style="padding:30px"><i class="fa fa-check" style="color:var(--green)"></i>No balance pending — every bill is fully paid.</div>
            <?php } else { $riders = $due; include __DIR__ . '/partials/rider_rows.php'; } ?>
        </div>
        <div class="shra-card">
            <div class="shra-card-head"><h4><i class="fa-solid fa-sun" style="color:var(--gold)"></i> Today's riders <span class="thin">· <?php echo date('d M Y'); ?></span></h4><span class="shra-pill"><?php echo count($today); ?> rider<?php echo count($today) == 1 ? '' : 's'; ?></span></div>
            <?php if (!count($today)) { ?>
                <div class="shra-empty" style="padding:30px"><i class="fa-solid fa-horse"></i>No one has been billed or marked today yet.<br><a href="<?php echo admin_url('shra/riders?view=all'); ?>">Open all riders</a> to bill or mark a session.</div>
            <?php } else { $riders = $today; include __DIR__ . '/partials/rider_rows.php'; } ?>
        </div>

    <?php } else { ?>
        <div class="shra-card">
            <?php if (!count($riders)) { ?>
                <div class="shra-empty"><i class="fa-solid fa-horse-head"></i>No riders match.<br><a href="<?php echo admin_url('shra/rider_form'); ?>">Register one</a> or share the <a href="<?php echo admin_url('shra/qr'); ?>">QR code</a>.</div>
            <?php } else { include __DIR__ . '/partials/rider_rows.php'; } ?>
        </div>
    <?php } ?>
    <?php if (is_admin()) { // superadmin only: bulk delete bar, shown once something is ticked ?>
    <div id="shra-bulkbar" class="shra-bulkbar" hidden
         data-url="<?php echo admin_url('shra/bulk_delete_riders'); ?>"
         data-confirm="Permanently delete {n} rider(s)? Their enrollments and attendance history are removed too. This cannot be undone.">
        <span><b class="shra-bulk-count">0</b> selected</span>
        <button type="button" class="shra-btn shra-btn-outline shra-btn-sm shra-bulk-clear">Clear</button>
        <button type="button" class="shra-btn shra-btn-danger shra-btn-sm shra-bulk-del"><i class="fa fa-trash"></i> Delete selected</button>
    </div>
    <?php } ?>

    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php include __DIR__ . '/partials/collect_modal.php'; ?>
<?php init_tail(); ?>
<script>$(function () { SHRA.collectInit({ url: <?php echo json_encode(admin_url('shra/collect')); ?> }); });</script>
</body>
</html>
