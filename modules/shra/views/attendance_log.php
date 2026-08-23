<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'attendance'; include __DIR__ . '/_nav.php'; ?>
    <h4 class="shra-title">Attendance log</h4>
    <form method="get" class="shra-toolbar">
        <input type="date" name="from" class="form-control" style="width:auto" value="<?php echo html_escape($filters['from']); ?>">
        <input type="date" name="to" class="form-control" style="width:auto" value="<?php echo html_escape($filters['to']); ?>">
        <select name="trainer_id" class="form-control" style="width:auto"><option value="">All trainers</option><?php foreach ($trainers as $t) { ?><option value="<?php echo $t->staffid; ?>" <?php echo $filters['trainer_id'] == $t->staffid ? 'selected' : ''; ?>><?php echo html_escape($t->firstname . ' ' . $t->lastname); ?></option><?php } ?></select>
        <button class="shra-btn shra-btn-outline">Filter</button>
        <span class="shra-pill" style="margin-left:auto"><?php echo count($rows); ?> sessions</span>
    </form>
    <div class="shra-card">
        <?php if (!count($rows)) { ?>
            <div class="shra-empty"><i class="fa-solid fa-horse"></i>No sessions in this range.</div>
        <?php } else { ?>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>Date</th><th>Rider</th><th>Package</th><th>Session</th><th>Trainer</th><th>Horse</th><th>Notes</th><th>Marked by</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $a) { ?>
                <tr>
                    <td><?php echo _d($a->session_date); ?><span class="sub"><?php echo $a->session_time ? date('h:i A', strtotime($a->session_time)) : ''; ?></span></td>
                    <td><a href="<?php echo admin_url('shra/rider/' . $a->rider_id); ?>" class="strong"><?php echo html_escape($a->full_name); ?></a><span class="sub"><?php echo html_escape($a->rider_no); ?></span></td>
                    <td><?php echo html_escape($a->package_name); ?></td>
                    <td><?php echo (int) $a->session_no; ?> / <?php echo (int) $a->sessions_total; ?></td>
                    <td><?php echo html_escape($a->trainer_name ?: '—'); ?></td>
                    <td><?php echo html_escape($a->horse_name ?: '—'); ?></td>
                    <td class="shra-muted"><?php echo html_escape($a->notes ?: ''); ?></td>
                    <td class="shra-muted"><?php echo html_escape($a->marked_by_name ?: '—'); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table></div>
        <?php } ?>
    </div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
