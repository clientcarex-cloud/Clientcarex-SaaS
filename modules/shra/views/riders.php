<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'riders'; include __DIR__ . '/_nav.php'; ?>

    <form method="get" class="shra-toolbar">
        <div class="shra-search grow"><i class="fa fa-search"></i><input type="text" name="q" class="form-control" placeholder="Search name, mobile, rider or membership no." value="<?php echo html_escape($filters['q']); ?>"></div>
        <select name="type" class="form-control" style="width:auto"><option value="">All types</option><option value="learner" <?php echo $filters['type'] === 'learner' ? 'selected' : ''; ?>>Learners</option><option value="guest" <?php echo $filters['type'] === 'guest' ? 'selected' : ''; ?>>Guest riders</option></select>
        <select name="level" class="form-control" style="width:auto"><option value="">All levels</option><?php foreach ($levels as $l) { ?><option value="<?php echo html_escape($l); ?>" <?php echo $filters['level'] === $l ? 'selected' : ''; ?>><?php echo html_escape($l); ?></option><?php } ?></select>
        <select name="status" class="form-control" style="width:auto"><option value="">Any status</option><option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select>
        <button class="shra-btn shra-btn-outline">Filter</button>
    </form>

    <div class="shra-card">
        <?php if (!count($riders)) { ?>
            <div class="shra-empty"><i class="fa-solid fa-horse-head"></i>No riders match.<br><a href="<?php echo admin_url('shra/rider_form'); ?>">Register one</a> or share the <a href="<?php echo admin_url('shra/qr'); ?>">QR code</a>.</div>
        <?php } else { ?>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>Rider</th><th>Contact</th><th>Age · Level</th><th>Type</th><th>Sessions left</th><th>Last session</th><th>Registered</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($riders as $r) { ?>
                <tr>
                    <td><div class="shra-person"><span class="shra-avatar"><?php echo strtoupper(mb_substr($r->full_name, 0, 1)); ?></span><div><a href="<?php echo admin_url('shra/rider/' . $r->id); ?>" class="name"><?php echo html_escape($r->full_name); ?></a><div class="meta"><?php echo html_escape($r->rider_no); ?><?php echo $r->membership_no ? ' · ' . html_escape($r->membership_no) : ''; ?></div></div></div></td>
                    <td><?php echo html_escape($r->mobile); ?><span class="sub"><?php echo html_escape($r->email ?: ($r->guardian_name ? 'Guardian: ' . $r->guardian_name : '')); ?></span></td>
                    <td><?php echo $r->age !== null ? $r->age . ' yrs' : '—'; ?><span class="sub"><?php echo html_escape($r->riding_level); ?><?php echo $r->is_minor ? ' · minor' : ''; ?></span></td>
                    <td><?php echo $r->rider_type === 'guest' ? '<span class="shra-badge shra-badge-gold">Guest</span>' : '<span class="shra-badge shra-badge-ink">Learner</span>'; ?></td>
                    <td><?php echo $r->sessions_left > 0 ? '<span class="shra-badge shra-badge-green">' . (int) $r->sessions_left . '</span>' : '<span class="shra-muted">0</span>'; ?></td>
                    <td><?php echo $r->last_session ? _d($r->last_session) : '—'; ?></td>
                    <td><?php echo _d($r->created_at); ?><span class="sub"><?php echo $r->source === 'self' ? 'Self (QR)' : 'Desk'; ?></span></td>
                    <td style="white-space:nowrap;text-align:right">
                        <?php if (shra_can_billing()) { ?><a href="<?php echo admin_url('shra/billing?rider=' . $r->id); ?>" class="shra-btn shra-btn-outline shra-btn-sm" title="Bill"><i class="fa-solid fa-cash-register"></i></a><?php } ?>
                        <?php if (shra_can_attendance()) { ?><a href="<?php echo admin_url('shra/attendance?rider=' . $r->id); ?>" class="shra-btn shra-btn-outline shra-btn-sm" title="Mark session"><i class="fa-solid fa-clipboard-check"></i></a><?php } ?>
                        <?php if ($r->rider_type === 'learner') { ?><a href="<?php echo admin_url('shra/membership_pdf/' . $r->id); ?>" target="_blank" class="shra-btn shra-btn-outline shra-btn-sm" title="Membership PDF"><i class="fa-solid fa-id-card"></i></a><?php } ?>
                    </td>
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
