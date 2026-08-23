<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'membership'; include __DIR__ . '/_nav.php'; ?>

    <?php $c = ['all' => 0, 'active' => 0, 'pending' => 0, 'none' => 0]; ?>
    <form method="get" class="shra-toolbar">
        <div class="shra-seg" style="margin:0">
            <?php foreach (['all' => 'All members', 'active' => 'Active package', 'pending' => 'Registered · not billed', 'none' => 'Package finished'] as $k => $l) { ?>
                <label><input type="radio" name="show" value="<?php echo $k; ?>" <?php echo $filters['show'] === $k ? 'checked' : ''; ?> onchange="this.form.submit()"><span><?php echo $l; ?></span></label>
            <?php } ?>
        </div>
        <div class="shra-search grow"><i class="fa fa-search"></i><input type="text" name="q" class="form-control" placeholder="Name, mobile, membership or rider no." value="<?php echo html_escape($filters['q']); ?>"></div>
        <button class="shra-btn shra-btn-outline">Search</button>
    </form>

    <?php $n = count($rows); $left_sum = 0; $due_sum = 0; $pending = 0; $active = 0; foreach ($rows as $r) { $left_sum += (int) $r->sessions_left; $due_sum += (float) $r->total_due; $pending += $r->stage === 'pending' ? 1 : 0; $active += $r->stage === 'active' ? 1 : 0; } ?>
    <div class="shra-stats" style="margin-bottom:16px">
        <div class="shra-stat"><div class="shra-stat-icon"><i class="fa-solid fa-id-card"></i></div><div class="shra-stat-label">Members</div><div class="shra-stat-value"><?php echo $n; ?></div><div class="shra-stat-sub"><?php echo $active; ?> with an active package</div></div>
        <div class="shra-stat"><div class="shra-stat-icon"><i class="fa-solid fa-hourglass-half"></i></div><div class="shra-stat-label">Registered, not billed</div><div class="shra-stat-value"><?php echo $pending; ?></div><div class="shra-stat-sub">chose a plan on the QR form</div></div>
        <div class="shra-stat"><div class="shra-stat-icon"><i class="fa-solid fa-ticket"></i></div><div class="shra-stat-label">Sessions remaining</div><div class="shra-stat-value"><?php echo $left_sum; ?></div><div class="shra-stat-sub">across all members</div></div>
        <div class="shra-stat"><div class="shra-stat-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div><div class="shra-stat-label">Balance due</div><div class="shra-stat-value" style="<?php echo $due_sum > 0.009 ? 'color:var(--red)' : ''; ?>"><?php echo shra_money($due_sum); ?></div><div class="shra-stat-sub"><?php echo $due_sum > 0.009 ? 'collect at the desk' : 'all paid'; ?></div></div>
    </div>

    <div class="shra-card">
        <?php if (!$n) { ?>
            <div class="shra-empty"><i class="fa-solid fa-id-card"></i>No members match.<br><a href="<?php echo admin_url('shra/rider_form'); ?>">Register a learner</a> or share the <a href="<?php echo admin_url('shra/qr'); ?>">QR code</a>.</div>
        <?php } else { ?>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>Member</th><th>Contact</th><th>Plan chosen</th><th>Package bought</th><th>Sessions</th><th>Payment</th><th>Member since</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r) { $e = $r->current ?: $r->last; $due = round((float) $r->total_due, 2); $left = (int) $r->sessions_left; $att = (int) $r->attended_today > 0;
                $pp = $r->preferred_package; ?>
                <tr class="<?php echo $att ? 'shra-row-attended' : ''; ?>">
                    <td><div class="shra-person"><span class="shra-avatar"><?php echo strtoupper(mb_substr($r->full_name, 0, 1)); ?></span><div><a href="<?php echo admin_url('shra/rider/' . $r->id); ?>" class="name"><?php echo html_escape($r->full_name); ?></a><div class="meta"><b><?php echo html_escape($r->membership_no ?: '—'); ?></b> · <?php echo html_escape($r->rider_no); ?><?php echo $r->age !== null ? ' · ' . $r->age . ' yrs' : ''; ?> · <?php echo html_escape($r->riding_level); ?></div></div></div></td>
                    <td><?php echo html_escape($r->mobile); ?><span class="sub"><?php echo html_escape($r->email ?: ($r->guardian_name ? 'Guardian: ' . $r->guardian_name : '')); ?></span></td>
                    <td><?php if ($pp) { $q = $this->shra_model->quote($pp); ?><?php echo html_escape($pp->name); ?> <span class="shra-muted">· <?php echo $pp->audience; ?></span><span class="sub"><?php echo shra_money($q['total']); ?> · <?php echo (int) $pp->sessions; ?> sessions<?php echo $r->stage === 'pending' ? ' · <b style="color:var(--red)">pay at desk</b>' : ''; ?></span><?php } else { ?><span class="shra-muted">—</span><?php } ?></td>
                    <td><?php if ($e) { ?><span class="strong"><?php echo html_escape($e->package_name); ?></span> <span class="shra-muted">· <?php echo $e->audience; ?></span><span class="sub"><?php echo _d($e->created_at); ?> · <?php echo html_escape($e->enrollment_no); ?><?php echo $e->expires_at ? ' · till ' . _d($e->expires_at) : ''; ?> · <?php echo shra_status_badge($e->status); ?></span><?php } else { ?><span class="shra-badge shra-badge-gold">Not billed yet</span><?php } ?></td>
                    <td style="min-width:140px"><?php if ($e) { $pct = $e->sessions_total ? round($e->sessions_used / $e->sessions_total * 100) : 0; ?><?php echo (int) $e->sessions_used; ?> / <?php echo (int) $e->sessions_total; ?> <?php echo $left > 0 ? '<span class="shra-badge shra-badge-green">' . $left . ' left</span>' : ''; ?><div class="shra-progress" style="margin-top:5px"><span style="width:<?php echo $pct; ?>%"></span></div><?php } else { ?><span class="shra-muted">—</span><?php } ?></td>
                    <td><?php if ((int) $r->bills === 0) { ?><span class="shra-muted">No bill</span><?php } elseif ($due > 0.009) { ?><span class="shra-badge shra-badge-red">Due <?php echo shra_money($due); ?></span><?php } else { ?><span class="shra-badge shra-badge-green"><i class="fa fa-check"></i> Paid</span><?php } ?></td>
                    <td><?php echo _d($r->membership_issued_at ?: $r->created_at); ?><span class="sub"><?php echo $r->source === 'self' ? 'Self (QR)' : 'Desk'; ?></span></td>
                    <td style="white-space:nowrap;text-align:right">
                        <?php if ($att) { ?><span class="shra-badge shra-badge-green"><i class="fa fa-check"></i> Attended today</span>
                        <?php } elseif ($left > 0 && shra_can_attendance()) { ?><a href="<?php echo admin_url('shra/attendance?rider=' . $r->id); ?>" class="shra-btn shra-btn-primary shra-btn-sm"><i class="fa-solid fa-clipboard-check"></i> Mark session</a>
                        <?php } elseif ($left === 0 && shra_can_billing()) { ?><a href="<?php echo admin_url('shra/billing?rider=' . $r->id); ?>" class="shra-btn shra-btn-primary shra-btn-sm"><i class="fa-solid fa-cash-register"></i> <?php echo $r->stage === 'pending' ? 'Bill chosen plan' : 'Bill'; ?></a><?php } ?>
                        <?php if ($due > 0.009 && $r->due_enrollment_id && shra_can_billing()) { ?><button type="button" class="shra-btn shra-btn-gold shra-btn-sm shra-collect" data-id="<?php echo (int) $r->due_enrollment_id; ?>" data-due="<?php echo $due; ?>" data-name="<?php echo html_escape($r->full_name); ?>"><i class="fa-solid fa-hand-holding-dollar"></i> Collect</button><?php } ?>
                        <?php if ($e) { ?><a href="<?php echo admin_url('shra/receipt_pdf/' . $e->id); ?>" target="_blank" class="shra-btn shra-btn-outline shra-btn-sm" title="Receipt"><i class="fa-solid fa-receipt"></i></a><?php } ?>
                        <a href="<?php echo admin_url('shra/membership_pdf/' . $r->id); ?>" target="_blank" class="shra-btn shra-btn-outline shra-btn-sm" title="Membership PDF"><i class="fa-solid fa-id-card"></i></a>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table></div>
        <?php } ?>
    </div>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php include __DIR__ . '/partials/collect_modal.php'; ?>
<?php init_tail(); ?>
<script>$(function () { SHRA.collectInit({ url: <?php echo json_encode(admin_url('shra/collect')); ?> }); });</script>
</body>
</html>
