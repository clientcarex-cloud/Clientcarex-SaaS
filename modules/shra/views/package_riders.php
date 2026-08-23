<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'package_riders'; include __DIR__ . '/_nav.php'; ?>

    <form method="get" class="shra-toolbar">
        <div class="shra-seg" style="margin:0">
            <?php foreach (['active' => 'Active packages', 'completed' => 'Completed', 'expired' => 'Expired', 'all' => 'All'] as $k => $l) { ?>
                <label><input type="radio" name="status" value="<?php echo $k; ?>" <?php echo $filters['status'] === $k ? 'checked' : ''; ?> onchange="this.form.submit()"><span><?php echo $l; ?></span></label>
            <?php } ?>
        </div>
        <div class="shra-search grow"><i class="fa fa-search"></i><input type="text" name="q" class="form-control" placeholder="Rider, mobile, rider or enrollment no." value="<?php echo html_escape($filters['q']); ?>"></div>
        <select name="aud" class="form-control" style="width:auto" onchange="this.form.submit()"><option value="">Children &amp; adults</option><option value="children" <?php echo $filters['aud'] === 'children' ? 'selected' : ''; ?>>Children</option><option value="adults" <?php echo $filters['aud'] === 'adults' ? 'selected' : ''; ?>>Adults</option></select>
        <button class="shra-btn shra-btn-outline">Search</button>
    </form>

    <?php $n = count($rows); $left_sum = 0; $due_sum = 0; $attended = 0; foreach ($rows as $e) { $left_sum += max(0, $e->sessions_total - $e->sessions_used); $due_sum += $e->due; $attended += $e->attended_today ? 1 : 0; } ?>
    <div class="shra-stats" style="margin-bottom:16px">
        <div class="shra-stat"><div class="shra-stat-icon"><i class="fa-solid fa-id-badge"></i></div><div class="shra-stat-label">Packages</div><div class="shra-stat-value"><?php echo $n; ?></div><div class="shra-stat-sub"><?php echo ucfirst($filters['status']); ?></div></div>
        <div class="shra-stat"><div class="shra-stat-icon"><i class="fa-solid fa-ticket"></i></div><div class="shra-stat-label">Sessions remaining</div><div class="shra-stat-value"><?php echo $left_sum; ?></div><div class="shra-stat-sub">across listed packages</div></div>
        <div class="shra-stat"><div class="shra-stat-icon"><i class="fa-solid fa-clipboard-check"></i></div><div class="shra-stat-label">Rode today</div><div class="shra-stat-value"><?php echo $attended; ?></div><div class="shra-stat-sub">of <?php echo $n; ?></div></div>
        <div class="shra-stat"><div class="shra-stat-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div><div class="shra-stat-label">Balance due</div><div class="shra-stat-value" style="<?php echo $due_sum > 0.009 ? 'color:var(--red)' : ''; ?>"><?php echo shra_money($due_sum); ?></div><div class="shra-stat-sub"><?php echo $due_sum > 0.009 ? 'collect at the desk' : 'all paid'; ?></div></div>
    </div>

    <div class="shra-card">
        <?php if (!$n) { ?>
            <div class="shra-empty"><i class="fa-solid fa-id-badge"></i>No riders with <?php echo $filters['status'] === 'all' ? 'a' : $filters['status']; ?> package<?php echo $filters['q'] ? ' match "' . html_escape($filters['q']) . '"' : ''; ?>.<br><?php if (shra_can_billing()) { ?><a href="<?php echo admin_url('shra/billing'); ?>">Bill a package</a><?php } ?></div>
        <?php } else { ?>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>Rider</th><th>Package</th><th>Sessions</th><th>Valid till</th><th>Payment</th><th>Last session</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $e) { $left = max(0, $e->sessions_total - $e->sessions_used); $pct = $e->sessions_total ? round($e->sessions_used / $e->sessions_total * 100) : 0;
                $expiring = $e->expires_at && $e->status === 'active' && strtotime($e->expires_at) < strtotime('+7 days'); ?>
                <tr class="<?php echo $e->attended_today ? 'shra-row-attended' : ''; ?>">
                    <td><div class="shra-person"><span class="shra-avatar"><?php echo strtoupper(mb_substr($e->full_name, 0, 1)); ?></span><div><a href="<?php echo admin_url('shra/rider/' . $e->rider_id); ?>" class="name"><?php echo html_escape($e->full_name); ?></a><div class="meta"><?php echo html_escape($e->rider_no); ?><?php echo $e->membership_no ? ' · ' . html_escape($e->membership_no) : ''; ?> · <?php echo html_escape($e->mobile); ?></div></div></div></td>
                    <td class="strong"><?php echo html_escape($e->package_name); ?> <?php echo $e->is_guest ? '<span class="shra-badge shra-badge-gold">Guest</span>' : ''; ?><span class="sub"><?php echo ucfirst($e->audience); ?> · <?php echo (int) $e->duration_min; ?> min · bought <?php echo _d($e->created_at); ?> · <?php echo html_escape($e->enrollment_no); ?></span></td>
                    <td style="min-width:150px"><?php echo (int) $e->sessions_used; ?> / <?php echo (int) $e->sessions_total; ?> <?php echo $left > 0 ? '<span class="shra-badge shra-badge-green">' . $left . ' left</span>' : ''; ?><div class="shra-progress" style="margin-top:5px"><span style="width:<?php echo $pct; ?>%"></span></div></td>
                    <td><?php echo $e->expires_at ? '<span style="' . ($expiring ? 'color:var(--red);font-weight:600' : '') . '">' . _d($e->expires_at) . '</span>' : '<span class="shra-muted">No expiry</span>'; ?></td>
                    <td><?php echo shra_pay_badge($e); ?><span class="sub"><?php echo shra_money($e->paid_real); ?> of <?php echo shra_money($e->total); ?></span></td>
                    <td><?php $ls = $this->db->select_max('session_date')->where('enrollment_id', $e->id)->get(db_prefix() . 'shra_attendance')->row()->session_date; echo $ls ? _d($ls) : '—'; ?></td>
                    <td><?php echo shra_status_badge($e->status); ?><?php echo $e->certificate_no ? '<span class="sub">' . html_escape($e->certificate_no) . '</span>' : ''; ?></td>
                    <td style="white-space:nowrap;text-align:right">
                        <?php if ($e->attended_today) { ?><span class="shra-badge shra-badge-green"><i class="fa fa-check"></i> Attended today</span>
                        <?php } elseif ($e->status === 'active' && $left > 0 && shra_can_attendance()) { ?><a href="<?php echo admin_url('shra/attendance?rider=' . $e->rider_id); ?>" class="shra-btn shra-btn-primary shra-btn-sm"><i class="fa-solid fa-clipboard-check"></i> Mark session</a><?php } ?>
                        <?php if ($e->due > 0.009 && $e->invoice_id && $e->status !== 'cancelled' && shra_can_billing()) { ?><button type="button" class="shra-btn shra-btn-gold shra-btn-sm shra-collect" data-id="<?php echo $e->id; ?>" data-due="<?php echo $e->due; ?>" data-name="<?php echo html_escape($e->full_name); ?>"><i class="fa-solid fa-hand-holding-dollar"></i> Collect</button><?php } ?>
                        <a href="<?php echo admin_url('shra/receipt_pdf/' . $e->id); ?>" target="_blank" class="shra-btn shra-btn-outline shra-btn-sm" title="Receipt"><i class="fa-solid fa-receipt"></i></a>
                        <?php if ($e->certificate_no) { ?><a href="<?php echo admin_url('shra/certificate_pdf/' . $e->id); ?>" target="_blank" class="shra-btn shra-btn-gold shra-btn-sm" title="Certificate"><i class="fa-solid fa-award"></i></a><?php } ?>
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
