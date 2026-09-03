<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'dashboard'; include __DIR__ . '/_nav.php'; ?>

            <?php
            $tiles = [
                ['today',        'Permits today',          'fa-solid fa-calendar-day',       'info', 'today'],
                ['active',       'Active permits',         'fa-solid fa-circle-play',        'live', 'active'],
                ['pending',      'Pending approvals',      'fa-solid fa-hourglass-half',     'warn', 'pending'],
                ['suspended',    'Suspended / on hold',    'fa-solid fa-circle-pause',       'bad',  'suspended'],
                ['expiring',     'Expiring soon',          'fa-solid fa-stopwatch',          'warn', 'expiring'],
                ['expired',      'Expired, still active',  'fa-solid fa-triangle-exclamation', 'bad', 'expired'],
                ['docs_pending', 'Documents pending',      'fa-solid fa-folder-open',        'warn', 'docs_pending'],
                ['extensions',   'Extensions to decide',   'fa-solid fa-clock-rotate-left',  'info', 'extensions'],
            ];
            ?>
            <div class="eptw-tiles">
                <?php foreach ($tiles as $t) { $n = (int) $cards[$t[0]]; ?>
                    <a href="<?= admin_url('eptw/register?view=' . $t[4]); ?>" class="eptw-tile <?= $n ? $t[3] : ''; ?>">
                        <div class="eptw-tile-icon"><i class="<?= $t[2]; ?>"></i></div>
                        <div class="eptw-tile-value"><?= $n; ?></div>
                        <div class="eptw-tile-label"><?= $t[1]; ?></div>
                    </a>
                <?php } ?>
            </div>

            <?php if (count($queue)) { ?>
                <div class="eptw-card warn">
                    <div class="eptw-card-head">
                        <h3><i class="fa-solid fa-pen-nib"></i> Waiting for you</h3>
                        <div class="eptw-card-actions"><a href="<?= admin_url('eptw/register?view=pending'); ?>" class="eptw-btn eptw-btn-sm">All pending</a></div>
                    </div>
                    <div class="eptw-table-scroll">
                        <table class="eptw-table">
                            <tbody>
                            <?php foreach ($queue as $r) { ?>
                                <tr>
                                    <td>
                                        <a href="<?= admin_url('eptw/view/' . $r->id); ?>" class="eptw-strong"><?= html_escape($r->work_title); ?></a>
                                        <div class="eptw-small eptw-muted"><?= html_escape($r->type_name); ?> · <?= html_escape($r->project_name . ' / ' . $r->area_name); ?> · by <?= html_escape($r->engineer_name); ?></div>
                                    </td>
                                    <td class="eptw-small"><?= eptw_status_badge($r->status); ?></td>
                                    <td class="eptw-small eptw-muted">Submitted <?= eptw_time_ago($r->number_requested_at); ?></td>
                                    <td class="eptw-small"><?= eptw_risk_badge($r->risk_level); ?></td>
                                    <td class="eptw-actions"><a href="<?= admin_url('eptw/view/' . $r->id); ?>#approvals" class="eptw-btn eptw-btn-sm eptw-btn-primary">Review</a></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php } ?>

            <div class="eptw-dash-grid">
                <div class="col-8">
                    <?php if (count($expired) || count($expiring)) { ?>
                        <div class="eptw-card <?= count($expired) ? 'danger' : 'warn'; ?>">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-stopwatch"></i> Expiry watch</h3>
                                <div class="eptw-card-actions"><span class="eptw-small eptw-muted">Warning window: <?= (int) eptw_opt('eptw_expiring_hours'); ?> h</span></div>
                            </div>
                            <div class="eptw-table-scroll">
                                <table class="eptw-table">
                                    <tbody>
                                    <?php foreach (array_merge($expired, $expiring) as $r) { ?>
                                        <tr class="<?= $r->is_expired ? 'is-expired' : ''; ?>">
                                            <td><a href="<?= admin_url('eptw/view/' . $r->id); ?>" class="eptw-permit-no"><?= html_escape($r->permit_no); ?></a>
                                                <div class="eptw-small eptw-muted"><?= html_escape($r->work_title); ?></div></td>
                                            <td class="eptw-small"><?= html_escape($r->type_name); ?><div class="eptw-muted"><?= html_escape($r->area_name); ?></div></td>
                                            <td class="eptw-small"><span class="eptw-badge <?= $r->is_expired ? 'bad' : 'warn'; ?>"><?= $r->is_expired ? 'Expired ' . eptw_time_ago($r->end_at) : 'Expires ' . eptw_time_until($r->end_at); ?></span></td>
                                            <td class="eptw-actions"><a href="<?= admin_url('eptw/view/' . $r->id); ?>" class="eptw-btn eptw-btn-sm">Open</a></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-chart-line"></i> Issued vs closed — last 30 days</h3></div>
                        <div class="eptw-card-body">
                            <?php
                            $labels = []; $issued = []; $closed = [];
                            foreach ($charts['trend'] as $d => $v) { $labels[] = date('d M', strtotime($d)); $issued[] = $v['issued']; $closed[] = $v['closed']; }
                            ?>
                            <div class="eptw-chart"><canvas data-eptw-chart='<?= html_escape(json_encode(['type' => 'line', 'labels' => $labels, 'series' => [['label' => 'Issued', 'data' => $issued], ['label' => 'Closed', 'data' => $closed]]])); ?>'></canvas></div>
                        </div>
                    </div>

                    <div class="eptw-split-even">
                        <div class="eptw-card">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-layer-group"></i> Permits by type</h3></div>
                            <div class="eptw-card-body">
                                <?php if (count($charts['by_type'])) { ?>
                                    <div class="eptw-chart"><canvas data-eptw-chart='<?= html_escape(json_encode(['type' => 'doughnut', 'labels' => array_map(function ($r) { return $r->label; }, $charts['by_type']), 'data' => array_map(function ($r) { return (int) $r->n; }, $charts['by_type']), 'colors' => array_map(function ($r) { return $r->color ?: '#64748b'; }, $charts['by_type'])])); ?>'></canvas></div>
                                <?php } else { ?><div class="eptw-empty eptw-small">No permits in the last 30 days.</div><?php } ?>
                            </div>
                        </div>
                        <div class="eptw-card">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-flag"></i> Status overview</h3></div>
                            <div class="eptw-card-body">
                                <?php if (count($charts['by_status'])) { ?>
                                    <div class="eptw-chart"><canvas data-eptw-chart='<?= html_escape(json_encode(['type' => 'horizontalBar', 'labels' => array_map(function ($r) { return eptw_status_label($r->label); }, $charts['by_status']), 'data' => array_map(function ($r) { return (int) $r->n; }, $charts['by_status'])])); ?>'></canvas></div>
                                <?php } else { ?><div class="eptw-empty eptw-small">Nothing yet.</div><?php } ?>
                            </div>
                        </div>
                        <div class="eptw-card">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-helmet-safety"></i> Permits by contractor</h3></div>
                            <div class="eptw-card-body">
                                <?php if (count($charts['by_contractor'])) { ?>
                                    <div class="eptw-chart"><canvas data-eptw-chart='<?= html_escape(json_encode(['type' => 'bar', 'labels' => array_map(function ($r) { return $r->label; }, $charts['by_contractor']), 'data' => array_map(function ($r) { return (int) $r->n; }, $charts['by_contractor'])])); ?>'></canvas></div>
                                <?php } else { ?><div class="eptw-empty eptw-small">Nothing yet.</div><?php } ?>
                            </div>
                        </div>
                        <div class="eptw-card">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-map-location-dot"></i> Permits by area</h3></div>
                            <div class="eptw-card-body">
                                <?php if (count($charts['by_area'])) { ?>
                                    <div class="eptw-chart"><canvas data-eptw-chart='<?= html_escape(json_encode(['type' => 'horizontalBar', 'labels' => array_map(function ($r) { return $r->label; }, $charts['by_area']), 'data' => array_map(function ($r) { return (int) $r->n; }, $charts['by_area'])])); ?>'></canvas></div>
                                <?php } else { ?><div class="eptw-empty eptw-small">Nothing yet.</div><?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-4">
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-fire"></i> High-risk permits live</h3>
                            <div class="eptw-card-actions"><span class="eptw-badge <?= $cards['high_risk'] ? 'bad' : 'muted'; ?>"><?= (int) $cards['high_risk']; ?></span></div></div>
                        <div class="eptw-card-body">
                            <?php if (count($high_risk)) { ?>
                                <div class="eptw-risk-list">
                                    <?php foreach ($high_risk as $h) { ?>
                                        <a href="<?= admin_url('eptw/register?view=high_risk&type=' . $h->id); ?>" class="eptw-risk-item">
                                            <span class="eptw-type-dot" style="background:<?= html_escape($h->color); ?>"><i class="<?= html_escape($h->icon); ?>"></i></span>
                                            <span><?= html_escape($h->name); ?><span class="w"> · <?= (int) $h->working; ?> working</span></span>
                                            <span class="n"><?= (int) $h->n; ?></span>
                                        </a>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                <div class="eptw-empty eptw-small" style="padding:18px"><i class="fa-solid fa-shield-heart"></i>No high-risk permit is live right now.</div>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="eptw-card <?= count($simops) ? 'danger' : ''; ?>">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-diagram-project"></i> SIMOPS conflicts</h3>
                            <div class="eptw-card-actions"><span class="eptw-badge <?= count($simops) ? 'bad' : 'muted'; ?>"><?= count($simops); ?></span></div></div>
                        <div class="eptw-card-body">
                            <?php if (count($simops)) { ?>
                                <ul class="eptw-mini-list">
                                    <?php foreach ($simops as $s) { ?>
                                        <li>
                                            <span class="eptw-type-dot" style="background:<?= html_escape($s->type_color); ?>"><i class="fa-solid fa-triangle-exclamation"></i></span>
                                            <span class="t"><a href="<?= admin_url('eptw/view/' . $s->id); ?>" class="eptw-permit-no"><?= html_escape($s->permit_no); ?></a>
                                                <span class="s"><?= html_escape($s->type_name . ' · ' . $s->area_name); ?></span>
                                                <span class="s" title="<?= html_escape($s->simops_notes); ?>"><?= html_escape(mb_substr((string) $s->simops_notes, 0, 90)); ?></span></span>
                                            <?= eptw_status_badge($s->status); ?>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } else { ?>
                                <div class="eptw-empty eptw-small" style="padding:18px"><i class="fa-solid fa-circle-check"></i>No simultaneous-operations conflict detected.</div>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-user"></i> My permits</h3>
                            <div class="eptw-card-actions"><a href="<?= admin_url('eptw/register?view=mine'); ?>" class="eptw-btn eptw-btn-sm">All</a></div></div>
                        <div class="eptw-card-body">
                            <?php if (count($mine)) { ?>
                                <ul class="eptw-mini-list">
                                    <?php foreach ($mine as $r) { ?>
                                        <li>
                                            <span class="t"><a href="<?= admin_url('eptw/view/' . $r->id); ?>" class="<?= $r->permit_no ? 'eptw-permit-no' : 'eptw-strong'; ?>"><?= html_escape($r->permit_no ?: $r->work_title); ?></a>
                                                <span class="s"><?= html_escape($r->type_name); ?> · <?= eptw_dt($r->start_at, false); ?></span></span>
                                            <?= eptw_status_badge($r->status); ?>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } else { ?>
                                <div class="eptw-empty eptw-small" style="padding:18px"><i class="fa-regular fa-file"></i>You have not raised any permits yet.
                                    <?php if (eptw_can('create')) { ?><br><a href="<?= admin_url('eptw/permit'); ?>" class="eptw-btn eptw-btn-sm eptw-btn-primary" style="margin-top:10px"><i class="fa fa-plus"></i> New permit</a><?php } ?></div>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> Recently created</h3></div>
                        <div class="eptw-card-body">
                            <?php if (count($recent)) { ?>
                                <ul class="eptw-mini-list">
                                    <?php foreach ($recent as $r) { ?>
                                        <li>
                                            <span class="eptw-avatar" title="<?= html_escape($r->engineer_name); ?>"><?= html_escape(eptw_initials($r->engineer_name)); ?></span>
                                            <span class="t"><a href="<?= admin_url('eptw/view/' . $r->id); ?>" class="eptw-strong"><?= html_escape($r->work_title); ?></a>
                                                <span class="s"><?= html_escape($r->type_name); ?> · <?= eptw_time_ago($r->created_at); ?></span></span>
                                            <?= eptw_status_badge($r->status); ?>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } else { ?>
                                <div class="eptw-empty eptw-small" style="padding:18px"><i class="fa-regular fa-folder-open"></i>No permits yet.</div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
