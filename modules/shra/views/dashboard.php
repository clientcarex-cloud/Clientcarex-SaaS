<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'dashboard'; include __DIR__ . '/_nav.php'; ?>

    <?php if ($offer['active']) { ?>
        <div class="shra-offer" style="margin-bottom:18px"><span class="stamp"><?php echo $offer['percent'] + 0; ?>% OFF</span> <?php echo html_escape($offer['label'] ?: 'Offer'); ?> is live on all packages<?php echo $offer['ends'] ? ' until ' . _d($offer['ends']) : ''; ?>. <a href="<?php echo admin_url('shra/settings'); ?>" style="margin-left:auto">Manage</a></div>
    <?php } ?>

    <div class="shra-stats">
        <div class="shra-stat"><div class="shra-stat-icon"><i class="fa-solid fa-clipboard-check"></i></div><div class="shra-stat-label">Sessions today</div><div class="shra-stat-value"><?php echo (int) $summary['sessions_today']; ?></div><div class="shra-stat-sub"><?php echo (int) $summary['sessions_month']; ?> this month</div></div>
        <div class="shra-stat"><div class="shra-stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div><div class="shra-stat-label">Collected today</div><div class="shra-stat-value"><?php echo shra_money($summary['revenue_today']); ?></div><div class="shra-stat-sub"><?php echo shra_money($summary['revenue_month']); ?> this month<?php if ((float) $summary['total_due'] > 0.009) { ?> · <a href="<?php echo admin_url('shra/riders?view=due'); ?>" style="color:var(--red);font-weight:600"><?php echo shra_money($summary['total_due']); ?> due</a><?php } ?></div></div>
        <div class="shra-stat"><div class="shra-stat-icon"><i class="fa-solid fa-users"></i></div><div class="shra-stat-label">Riders</div><div class="shra-stat-value"><?php echo (int) $summary['riders']; ?></div><div class="shra-stat-sub"><?php echo (int) $summary['learners']; ?> learners · <?php echo (int) $summary['riders_month']; ?> new this month</div></div>
        <div class="shra-stat"><div class="shra-stat-icon"><i class="fa-solid fa-ticket"></i></div><div class="shra-stat-label">Active packages</div><div class="shra-stat-value"><?php echo (int) $summary['active_enrollments']; ?></div><div class="shra-stat-sub"><?php echo (int) $summary['ending_soon']; ?> ending soon · <?php echo (int) $summary['certificates']; ?> certificates</div></div>
    </div>

    <div class="row">
        <div class="col-md-7">
            <div class="shra-card">
                <div class="shra-card-head"><h4>Sessions — last 14 days</h4><a href="<?php echo admin_url('shra/attendance_log'); ?>" class="shra-btn shra-btn-outline shra-btn-sm">Full log</a></div>
                <div class="shra-card-body">
                    <?php $max = max(1, max(array_column($spark, 'count'))); ?>
                    <div class="shra-spark">
                        <?php foreach ($spark as $d) { ?>
                            <span style="height:<?php echo max(5, round($d['count'] / $max * 100)); ?>%" class="<?php echo $d['date'] === date('Y-m-d') ? 'today' : ''; ?>" data-t="<?php echo date('d M', strtotime($d['date'])) . ': ' . $d['count']; ?>"></span>
                        <?php } ?>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-top:6px"><span><?php echo date('d M', strtotime($spark[0]['date'])); ?></span><span>Today</span></div>
                </div>
            </div>

            <div class="shra-card shra-mt">
                <div class="shra-card-head"><h4>Today's sessions</h4><a href="<?php echo admin_url('shra/attendance'); ?>" class="shra-btn shra-btn-primary shra-btn-sm"><i class="fa-solid fa-plus"></i> Mark session</a></div>
                <?php if (!count($today)) { ?>
                    <div class="shra-empty" style="padding:30px"><i class="fa-solid fa-horse"></i>No sessions marked yet today.</div>
                <?php } else { ?>
                    <div class="shra-table-wrap"><table class="shra-table">
                        <thead><tr><th>Rider</th><th>Package</th><th>Session</th><th>Trainer</th><th>Time</th></tr></thead>
                        <tbody>
                        <?php foreach ($today as $a) { ?>
                            <tr>
                                <td><a href="<?php echo admin_url('shra/rider/' . $a->rider_id); ?>" class="strong"><?php echo html_escape($a->full_name); ?></a><span class="sub"><?php echo html_escape($a->rider_no); ?></span></td>
                                <td><?php echo html_escape($a->package_name); ?></td>
                                <td><?php echo (int) $a->session_no; ?> / <?php echo (int) $a->sessions_total; ?></td>
                                <td><?php echo html_escape($a->trainer_name ?: '—'); ?></td>
                                <td><?php echo $a->session_time ? date('h:i A', strtotime($a->session_time)) : '—'; ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table></div>
                <?php } ?>
            </div>
        </div>

        <div class="col-md-5">
            <div class="shra-card">
                <div class="shra-card-head"><h4>Recent bills</h4><a href="<?php echo admin_url('shra/membership'); ?>" class="shra-btn shra-btn-outline shra-btn-sm">Membership</a></div>
                <?php if (!count($recent)) { ?>
                    <div class="shra-empty" style="padding:30px"><i class="fa-solid fa-receipt"></i>No bills yet.</div>
                <?php } else { ?>
                    <div class="shra-table-wrap"><table class="shra-table">
                        <tbody>
                        <?php foreach ($recent as $e) { ?>
                            <tr>
                                <td><a href="<?php echo admin_url('shra/rider/' . $e->rider_id); ?>" class="strong"><?php echo html_escape($e->full_name); ?></a><span class="sub"><?php echo html_escape($e->package_name); ?> · <?php echo _dt($e->created_at); ?></span></td>
                                <td class="num strong"><?php echo shra_money($e->total); ?><span class="sub"><?php echo shra_status_badge($e->status); ?></span></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table></div>
                <?php } ?>
            </div>

            <div class="shra-card shra-mt">
                <div class="shra-card-head"><h4>Newest riders</h4><a href="<?php echo admin_url('shra/riders'); ?>" class="shra-btn shra-btn-outline shra-btn-sm">All</a></div>
                <?php if (!count($new_riders)) { ?>
                    <div class="shra-empty" style="padding:30px"><i class="fa-solid fa-user-plus"></i>No riders yet. Print the <a href="<?php echo admin_url('shra/qr'); ?>">registration QR</a>.</div>
                <?php } else { ?>
                    <div class="shra-card-body" style="padding-top:8px;padding-bottom:8px">
                    <?php foreach ($new_riders as $r) { ?>
                        <div class="shra-person" style="padding:9px 0;border-bottom:1px solid #f0e9d8">
                            <span class="shra-avatar"><?php echo strtoupper(mb_substr($r->full_name, 0, 1)); ?></span>
                            <div style="flex:1"><a href="<?php echo admin_url('shra/rider/' . $r->id); ?>" class="name"><?php echo html_escape($r->full_name); ?></a><div class="meta"><?php echo html_escape($r->rider_no); ?> · <?php echo $r->age !== null ? $r->age . ' yrs · ' : ''; ?><?php echo html_escape($r->riding_level); ?> · <?php echo $r->source === 'self' ? 'QR' : 'Desk'; ?></div></div>
                            <?php if ($r->sessions_left > 0) { ?><span class="shra-badge shra-badge-green"><?php echo (int) $r->sessions_left; ?> left</span><?php } else { ?><a href="<?php echo admin_url('shra/billing?rider=' . $r->id); ?>" class="shra-btn shra-btn-outline shra-btn-sm">Bill</a><?php } ?>
                        </div>
                    <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
