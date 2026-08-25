<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Rider table rows — shared by the riders list views.
 * Expects $riders. Action buttons are "smart":
 *  - attended today      → ✓ badge, no mark button
 *  - sessions left       → Mark session
 *  - no sessions left    → Bill
 *  - balance outstanding → Collect (never a duplicate bill)
 */
?>
<div class="shra-table-wrap"><table class="shra-table">
    <thead><tr><?php if (is_admin()) { ?><th class="shra-r-sel"><input type="checkbox" class="shra-bulk-all" title="Select every rider in this list"></th><?php } ?><th>Rider</th><th>Contact</th><th>Age · Level</th><th>Type</th><th>Sessions left</th><th>Payment</th><th>Last session</th><th>Registered</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($riders as $r) {
        $rdue = round((float) $r->total_due, 2);
        $left = (int) $r->sessions_left;
        $att  = (int) $r->attended_today > 0; ?>
        <tr class="<?php echo $att ? 'shra-row-attended' : ''; ?>">
            <?php if (is_admin()) { ?><td class="shra-r-sel"><input type="checkbox" class="shra-bulk-cb" value="<?php echo (int) $r->id; ?>" title="Select for bulk delete"></td><?php } ?>
            <td><div class="shra-person"><span class="shra-avatar"><?php echo strtoupper(mb_substr($r->full_name, 0, 1)); ?></span><div><a href="<?php echo admin_url('shra/rider/' . $r->id); ?>" class="name"><?php echo html_escape($r->full_name); ?></a><div class="meta"><?php echo html_escape($r->rider_no); ?><?php echo $r->membership_no ? ' · ' . html_escape($r->membership_no) : ''; ?></div></div></div></td>
            <td><?php echo html_escape($r->mobile); ?><span class="sub"><?php echo html_escape($r->email ?: ($r->guardian_name ? 'Guardian: ' . $r->guardian_name : '')); ?></span></td>
            <td><?php echo $r->age !== null ? $r->age . ' yrs' : '—'; ?><span class="sub"><?php echo html_escape($r->riding_level); ?><?php echo $r->is_minor ? ' · minor' : ''; ?></span></td>
            <td><?php echo $r->rider_type === 'guest' ? '<span class="shra-badge shra-badge-gold">Guest</span>' : '<span class="shra-badge shra-badge-ink">Learner</span>'; ?><?php echo $r->schedule ? '<span class="sub">' . html_escape($r->schedule) . '</span>' : ''; ?></td>
            <td><?php echo $left > 0 ? '<span class="shra-badge shra-badge-green">' . $left . '</span>' : '<span class="shra-muted">0</span>'; ?></td>
            <td>
                <?php if ((int) $r->bills === 0) { ?>
                    <span class="shra-muted">No bill</span>
                <?php } elseif ($rdue > 0.009) { ?>
                    <span class="shra-badge shra-badge-red">Due <?php echo shra_money($rdue); ?></span>
                    <span class="sub"><?php echo (int) $r->billed_today ? 'Billed today · ' : ''; ?>balance pending</span>
                <?php } else { ?>
                    <span class="shra-badge shra-badge-green"><i class="fa fa-check"></i> Paid</span>
                    <?php if ((int) $r->billed_today) { ?><span class="sub">Billed today</span><?php } ?>
                <?php } ?>
            </td>
            <td><?php echo $r->last_session ? _d($r->last_session) : '—'; ?></td>
            <td><?php echo _d($r->created_at); ?><span class="sub"><?php echo $r->source === 'self' ? 'Self (QR)' : 'Desk'; ?></span></td>
            <td style="white-space:nowrap;text-align:right">
                <?php if ($att) { ?>
                    <span class="shra-badge shra-badge-green" title="Session marked today"><i class="fa fa-check"></i> Attended today</span>
                <?php } elseif ($left > 0 && shra_can_attendance()) { ?>
                    <a href="<?php echo admin_url('shra/attendance?rider=' . $r->id); ?>" class="shra-btn shra-btn-primary shra-btn-sm" title="Mark today's session"><i class="fa-solid fa-clipboard-check"></i> Mark session</a>
                <?php } elseif ($left === 0 && shra_can_billing()) { ?>
                    <a href="<?php echo admin_url('shra/billing?rider=' . $r->id); ?>" class="shra-btn shra-btn-outline shra-btn-sm" title="Bill a package"><i class="fa-solid fa-cash-register"></i> Bill</a>
                <?php } ?>
                <?php if ($rdue > 0.009 && $r->due_enrollment_id && shra_can_billing()) { ?>
                    <button type="button" class="shra-btn shra-btn-gold shra-btn-sm shra-collect" data-id="<?php echo (int) $r->due_enrollment_id; ?>" data-due="<?php echo $rdue; ?>" data-name="<?php echo html_escape($r->full_name); ?>" title="Collect balance"><i class="fa-solid fa-hand-holding-dollar"></i> Collect</button>
                <?php } ?>
                <?php if ($r->rider_type === 'learner') { ?><a href="<?php echo admin_url('shra/membership_pdf/' . $r->id); ?>" target="_blank" class="shra-btn shra-btn-outline shra-btn-sm" title="Membership PDF"><i class="fa-solid fa-id-card"></i></a><?php } ?>
            </td>
        </tr>
    <?php } ?>
    </tbody>
</table></div>
