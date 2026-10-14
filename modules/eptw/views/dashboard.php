<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
// Every count drills into the register with the dashboard scope kept.
$reg = function (array $extra = []) use ($scope) {
    return admin_url('eptw/register?' . http_build_query(array_filter(array_merge($scope, $extra), function ($v) { return $v !== '' && $v !== null; })));
};
$name_of = function ($list, $id, $field = 'name') {
    foreach ($list as $row) { if ((int) $row->id === (int) $id) { return $row->$field; } }
    return '';
};
$projects    = array_values(array_filter(eptw_projects(false), function ($p) { return eptw_in_scope($p->id); }));
$areas       = eptw_areas((int) $filters['project'], false);
$contractors = eptw_contractors(false);
$types       = eptw_permit_types(false);
$refresh     = (int) eptw_opt('eptw_dashboard_refresh');
$docs_label  = eptw_opt('eptw_docs_review') === '1' ? 'Closed — documents missing / awaiting review' : 'Closed — mandatory documents missing';
$scope_bits  = [];
foreach (['project' => [$projects, 'Project'], 'area' => [$areas, 'Area'], 'contractor' => [$contractors, 'Contractor'], 'type' => [$types, 'Type']] as $k => $meta) {
    if ($filters[$k] !== '') { $scope_bits[] = $meta[1] . ': <b>' . html_escape($name_of($meta[0], $filters[$k])) . '</b>'; }
}
if ($filters['shift'] !== '') { $scope_bits[] = 'Shift: <b>' . html_escape(eptw_shifts()[$filters['shift']] ?? $filters['shift']) . '</b>'; }
if ($filters['status'] !== '') { $scope_bits[] = 'Status (charts &amp; lists): <b>' . html_escape(eptw_status_label($filters['status'])) . '</b>'; }
$period = eptw_dt($charts['since'], false) . ' – ' . eptw_dt($charts['until'], false);
?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'dashboard'; include __DIR__ . '/_nav.php'; ?>

            <!-- ── Scope / filters (persist for the session) ───────────────── -->
            <form method="get" action="<?= admin_url('eptw'); ?>" class="eptw-scope" id="eptw-dash-filters">
                <select name="project" class="eptw-select"><option value="">All projects</option>
                    <?php foreach ($projects as $pr) { ?><option value="<?= $pr->id; ?>" <?= (int) $filters['project'] === (int) $pr->id ? 'selected' : ''; ?>><?= html_escape($pr->name); ?></option><?php } ?>
                </select>
                <select name="area" class="eptw-select"><option value="">All areas</option>
                    <?php foreach ($areas as $a) { ?><option value="<?= $a->id; ?>" <?= (int) $filters['area'] === (int) $a->id ? 'selected' : ''; ?>><?= html_escape($a->name); ?></option><?php } ?>
                </select>
                <select name="contractor" class="eptw-select"><option value="">All contractors</option>
                    <?php foreach ($contractors as $c) { ?><option value="<?= $c->id; ?>" <?= (int) $filters['contractor'] === (int) $c->id ? 'selected' : ''; ?>><?= html_escape($c->name); ?></option><?php } ?>
                </select>
                <select name="type" class="eptw-select"><option value="">All permit types</option>
                    <?php foreach ($types as $t) { ?><option value="<?= $t->id; ?>" <?= (int) $filters['type'] === (int) $t->id ? 'selected' : ''; ?>><?= html_escape($t->name); ?></option><?php } ?>
                </select>
                <select name="status" class="eptw-select" title="Applies to the charts and lists; the KPI cards are statuses themselves"><option value="">Any status</option>
                    <?php foreach (eptw_statuses() as $k => $st) { ?><option value="<?= $k; ?>" <?= $filters['status'] === $k ? 'selected' : ''; ?>><?= html_escape($st['label']); ?></option><?php } ?>
                </select>
                <select name="shift" class="eptw-select"><option value="">Any shift</option>
                    <?php foreach (eptw_shifts() as $k => $l) { ?><option value="<?= $k; ?>" <?= $filters['shift'] === $k ? 'selected' : ''; ?>><?= $l; ?></option><?php } ?>
                </select>
                <input type="date" name="from" class="eptw-input" value="<?= html_escape($filters['from']); ?>" title="Charts: permits created from">
                <input type="date" name="to" class="eptw-input" value="<?= html_escape($filters['to']); ?>" title="Charts: permits created until">
                <button type="submit" class="eptw-btn eptw-btn-sm eptw-btn-primary"><i class="fa fa-filter"></i> Apply</button>
                <?php if (array_filter($filters)) { ?><a href="<?= admin_url('eptw?reset=1'); ?>" class="eptw-btn eptw-btn-sm eptw-btn-ghost"><i class="fa fa-times"></i> Clear</a><?php } ?>
                <div class="eptw-scope-line">
                    <span><i class="fa-solid fa-crosshairs"></i> Scope: <?= count($scope_bits) ? implode(' · ', $scope_bits) : '<b>all permits you can see</b>' . (eptw_scope() !== null ? ' (your projects)' : ''); ?></span>
                    <span><i class="fa-solid fa-signal"></i> KPI cards &amp; panels: <b>live state now</b></span>
                    <span><i class="fa-solid fa-chart-column"></i> Charts: <b>permits created <?= $period; ?></b></span>
                    <span class="eptw-refresh" data-eptw-refresh="<?= $refresh; ?>">
                        <i class="fa-regular fa-clock"></i> Last updated <b><?= date('H:i:s', strtotime($refreshed)); ?></b>
                        <?php if ($refresh) { ?><span data-eptw-refresh-left>· auto-refresh every <?= $refresh >= 60 ? ($refresh / 60) . ' min' : $refresh . ' s'; ?></span><?php } ?>
                        <a href="<?= admin_url('eptw'); ?>" class="eptw-btn eptw-btn-sm" title="Refresh now"><i class="fa-solid fa-rotate"></i> Refresh</a>
                    </span>
                </div>
            </form>

            <?php
            $tile = function ($key, $label, $icon, $class, $link, $sub = '') use ($cards, $definitions) {
                $n = (int) $cards[$key];
                echo '<a href="' . $link . '" class="eptw-tile ' . ($n ? $class : '') . '" title="' . html_escape($definitions[$key] ?? '') . '">'
                    . '<div class="eptw-tile-icon"><i class="' . $icon . '"></i></div>'
                    . '<div class="eptw-tile-value">' . $n . '</div>'
                    . '<div class="eptw-tile-label">' . $label . '</div>'
                    . ($sub !== '' ? '<div class="eptw-tile-sub">' . $sub . '</div>' : '')
                    . '<div class="eptw-tile-def">' . html_escape($definitions[$key] ?? '') . '</div></a>';
            };
            ?>
            <div class="eptw-tiles-label">Live work control <span>— as of <?= date('H:i', strtotime($refreshed)); ?>. Only “Work authorized” permits allow work.</span></div>
            <div class="eptw-tiles">
                <?php
                $tile('active', 'Work authorized', 'fa-solid fa-circle-play', 'live', $reg(['view' => 'active']));
                $tile('issued', 'Issued — start not confirmed', 'fa-solid fa-stamp', 'info', $reg(['view' => 'issued']));
                $tile('on_hold', 'On hold', 'fa-solid fa-hand', 'warn', $reg(['view' => 'on_hold']), $cards['simops_hold'] ? 'incl. ' . (int) $cards['simops_hold'] . ' SIMOPS hold' : '');
                $tile('suspended', 'Suspended', 'fa-solid fa-circle-pause', 'bad', $reg(['view' => 'suspended']));
                $tile('expired', 'Expired — work not authorized', 'fa-solid fa-hourglass-end', 'bad', $reg(['view' => 'expired']), $cards['overdue'] ? (int) $cards['overdue'] . ' overdue &gt; ' . (int) eptw_opt('eptw_overdue_hours') . ' h' : '');
                $tile('expiring', 'Expiring soon', 'fa-solid fa-stopwatch', 'warn', $reg(['view' => 'expiring']));
                ?>
            </div>
            <div class="eptw-tiles-label">Pipeline &amp; records</div>
            <div class="eptw-tiles">
                <?php
                $tile('live', 'Open on site', 'fa-solid fa-layer-group', 'info', $reg(['view' => 'live']));
                $tile('pending', 'Awaiting approval', 'fa-solid fa-hourglass-half', 'warn', $reg(['view' => 'pending']));
                $tile('extensions', 'Extension requests awaiting approval', 'fa-solid fa-clock-rotate-left', 'info', $reg(['view' => 'extensions']));
                $tile('docs_pending', $docs_label, 'fa-solid fa-folder-open', 'warn', $reg(['view' => 'docs_pending']));
                $tile('drafts', 'Drafts &amp; returned', 'fa-regular fa-file', 'info', $reg(['view' => 'drafts']));
                ?>
            </div>

            <?php if (count($queue)) { ?>
                <div class="eptw-card warn">
                    <div class="eptw-card-head">
                        <h3><i class="fa-solid fa-list-check"></i> Your action queue</h3>
                        <div class="eptw-card-actions eptw-small eptw-muted">What <?= html_escape(eptw_role_label(eptw_role())); ?> needs to act on</div>
                    </div>
                    <div class="eptw-card-body">
                        <div class="eptw-queue">
                            <?php foreach ($queue as $qkey => $q) { ?>
                                <div class="eptw-queue-box">
                                    <h4><?= html_escape($q['label']); ?> <span class="eptw-badge warn"><?= (int) $q['total']; ?></span><a href="<?= admin_url('eptw/register?' . http_build_query(array_filter($q['filters']))); ?>">All ›</a></h4>
                                    <div class="hint"><?= html_escape($q['hint']); ?></div>
                                    <ul class="eptw-mini-list">
                                        <?php foreach ($q['rows'] as $r) { ?>
                                            <li>
                                                <span class="t"><a href="<?= admin_url('eptw/view/' . $r->id) . ($qkey === 'sign' ? '#approvals' : ''); ?>" class="<?= $r->permit_no ? 'eptw-permit-no' : 'eptw-strong'; ?>"><?= html_escape($r->permit_no ?: $r->work_title); ?></a>
                                                    <span class="s"><?= html_escape($r->type_name . ' · ' . $r->project_name . ' / ' . ($r->area_name ?: '—')); ?></span></span>
                                                <?= eptw_status_badge($r->status); ?>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <div class="eptw-dash-grid">
                <div class="col-8">
                    <?php if (count($expired)) { ?>
                        <div class="eptw-card danger">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-hourglass-end"></i> Expired — action required</h3>
                                <div class="eptw-card-actions"><span class="eptw-small eptw-muted">Work is not authorized on these permits</span> <a href="<?= $reg(['view' => 'expired']); ?>" class="eptw-btn eptw-btn-sm">All <?= (int) $cards['expired']; ?></a></div>
                            </div>
                            <div class="eptw-table-scroll">
                                <table class="eptw-table">
                                    <tbody>
                                    <?php $overdue_secs = max(1, (int) eptw_opt('eptw_overdue_hours')) * 3600; foreach ($expired as $r) { $late = (time() - strtotime($r->end_at)) > $overdue_secs; ?>
                                        <tr class="is-expired">
                                            <td><a href="<?= admin_url('eptw/view/' . $r->id); ?>" class="eptw-permit-no"><?= html_escape($r->permit_no); ?></a>
                                                <div class="eptw-small eptw-muted"><?= html_escape($r->work_title); ?></div></td>
                                            <td class="eptw-small"><?= html_escape($r->type_name); ?><div class="eptw-muted"><?= html_escape($r->project_name . ' / ' . $r->area_name); ?></div></td>
                                            <td class="eptw-small"><span class="eptw-badge bad">Expired <?= eptw_time_ago($r->end_at); ?></span> <?php if ($late) { ?><span class="eptw-overdue">Overdue</span><?php } ?>
                                                <div class="eptw-muted"><?= (int) $r->pending_extensions ? 'Extension awaiting approval' : 'No extension requested'; ?></div></td>
                                            <td class="eptw-actions">
                                                <?php if (eptw_can('status')) { ?><a href="<?= admin_url('eptw/view/' . $r->id . '?do=close'); ?>" class="eptw-btn eptw-btn-sm eptw-btn-dark">Close</a><?php } ?>
                                                <?php if (eptw_can('extend_request') && !(int) $r->pending_extensions) { ?><a href="<?= admin_url('eptw/view/' . $r->id . '?do=extend'); ?>" class="eptw-btn eptw-btn-sm">Extend</a><?php } ?>
                                                <?php if ((int) $r->pending_extensions && eptw_can('extend_approve')) { ?><a href="<?= admin_url('eptw/view/' . $r->id . '#extensions'); ?>" class="eptw-btn eptw-btn-sm eptw-btn-primary">Decide extension</a><?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php } ?>

                    <?php if (count($expiring)) { ?>
                        <div class="eptw-card warn">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-stopwatch"></i> Expiring soon</h3>
                                <div class="eptw-card-actions"><span class="eptw-small eptw-muted">Warning window: <?= (int) eptw_opt('eptw_expiring_hours'); ?> h (or the permit type's own window)</span></div>
                            </div>
                            <div class="eptw-table-scroll">
                                <table class="eptw-table">
                                    <tbody>
                                    <?php foreach ($expiring as $r) { ?>
                                        <tr>
                                            <td><a href="<?= admin_url('eptw/view/' . $r->id); ?>" class="eptw-permit-no"><?= html_escape($r->permit_no); ?></a>
                                                <div class="eptw-small eptw-muted"><?= html_escape($r->work_title); ?></div></td>
                                            <td class="eptw-small"><?= html_escape($r->type_name); ?><div class="eptw-muted"><?= html_escape($r->area_name); ?></div></td>
                                            <td class="eptw-small"><span class="eptw-badge warn">Expires <?= eptw_time_until($r->end_at); ?></span></td>
                                            <td class="eptw-actions">
                                                <?php if (eptw_can('extend_request') && !(int) $r->pending_extensions) { ?><a href="<?= admin_url('eptw/view/' . $r->id . '?do=extend'); ?>" class="eptw-btn eptw-btn-sm">Extend</a><?php } ?>
                                                <a href="<?= admin_url('eptw/view/' . $r->id); ?>" class="eptw-btn eptw-btn-sm eptw-btn-ghost">Open</a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-chart-line"></i> Issued vs closed</h3><div class="eptw-card-actions eptw-basis"><?= $period; ?></div></div>
                        <div class="eptw-card-body">
                            <?php
                            $labels = []; $issued = []; $closed = [];
                            foreach ($charts['trend'] as $d => $v) { $labels[] = date('d M', strtotime($d)); $issued[] = $v['issued']; $closed[] = $v['closed']; }
                            ?>
                            <div class="eptw-chart"><canvas data-eptw-chart='<?= html_escape(json_encode(['type' => 'line', 'labels' => $labels, 'series' => [['label' => 'Issued', 'data' => $issued], ['label' => 'Closed', 'data' => $closed]]])); ?>'></canvas></div>
                        </div>
                    </div>

                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-flag"></i> Status overview — full pipeline</h3><div class="eptw-card-actions eptw-basis">Current state · click a bar for the permits</div></div>
                        <div class="eptw-card-body">
                            <?php $pipe = $charts['by_status']; ?>
                            <div class="eptw-chart" style="height:<?= 40 + 24 * count($pipe); ?>px"><canvas data-eptw-chart='<?= html_escape(json_encode([
                                'type' => 'horizontalBar', 'wrap' => true,
                                'labels' => array_map(function ($r) { return $r->label; }, $pipe),
                                'data' => array_map(function ($r) { return (int) $r->n; }, $pipe),
                                'colors' => array_map(function ($r) { return ['active' => '#0284c7', 'active_extended' => '#0284c7', 'expired' => '#b91c1c', 'suspended' => '#dc2626', 'on_hold' => '#d97706', 'on_hold_simops' => '#b45309', 'issued' => '#7c3aed', 'closed_docs_pending' => '#ca8a04', 'extension_requested' => '#2563eb'][$r->key] ?? '#64748b'; }, $pipe),
                                'links' => array_map(function ($r) use ($reg) { return $r->key === 'extension_requested' ? $reg(['view' => 'extensions']) : $reg(['status' => $r->key]); }, $pipe),
                            ])); ?>'></canvas></div>
                        </div>
                    </div>

                    <div class="eptw-split-even">
                        <div class="eptw-card">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-layer-group"></i> Permits by type</h3><div class="eptw-card-actions eptw-basis">Created <?= $period; ?></div></div>
                            <div class="eptw-card-body">
                                <?php if (count($charts['by_type'])) { ?>
                                    <div class="eptw-chart"><canvas data-eptw-chart='<?= html_escape(json_encode(['type' => 'doughnut', 'labels' => array_map(function ($r) { return $r->label; }, $charts['by_type']), 'data' => array_map(function ($r) { return (int) $r->n; }, $charts['by_type']), 'colors' => array_map(function ($r) { return $r->color ?: '#64748b'; }, $charts['by_type']), 'links' => array_map(function ($r) use ($reg) { return $reg(['type' => $r->k]); }, $charts['by_type'])])); ?>'></canvas></div>
                                <?php } else { ?><div class="eptw-empty eptw-small">No permits created in this period.</div><?php } ?>
                            </div>
                        </div>
                        <div class="eptw-card">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-helmet-safety"></i> Permits by contractor</h3><div class="eptw-card-actions eptw-basis">Created <?= $period; ?></div></div>
                            <div class="eptw-card-body">
                                <?php if (count($charts['by_contractor'])) { ?>
                                    <div class="eptw-chart" style="height:<?= max(160, 40 + 34 * count($charts['by_contractor'])); ?>px"><canvas data-eptw-chart='<?= html_escape(json_encode(['type' => 'horizontalBar', 'wrap' => true, 'labels' => array_map(function ($r) { return $r->label; }, $charts['by_contractor']), 'data' => array_map(function ($r) { return (int) $r->n; }, $charts['by_contractor']), 'links' => array_map(function ($r) use ($reg) { return (int) $r->k ? $reg(['contractor' => $r->k]) : ''; }, $charts['by_contractor'])])); ?>'></canvas></div>
                                <?php } else { ?><div class="eptw-empty eptw-small">Nothing in this period.</div><?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-map-location-dot"></i> Permits by area</h3><div class="eptw-card-actions eptw-basis">Created <?= $period; ?> · project code · area</div></div>
                        <div class="eptw-card-body">
                            <?php if (count($charts['by_area'])) { ?>
                                <div class="eptw-chart" style="height:<?= max(160, 40 + 34 * count($charts['by_area'])); ?>px"><canvas data-eptw-chart='<?= html_escape(json_encode(['type' => 'horizontalBar', 'wrap' => true, 'labels' => array_map(function ($r) { return $r->label; }, $charts['by_area']), 'data' => array_map(function ($r) { return (int) $r->n; }, $charts['by_area']), 'links' => array_map(function ($r) use ($reg) { return (int) $r->k ? $reg(['project' => $r->pk, 'area' => $r->k]) : ''; }, $charts['by_area'])])); ?>'></canvas></div>
                            <?php } else { ?><div class="eptw-empty eptw-small">Nothing in this period.</div><?php } ?>
                        </div>
                    </div>
                </div>

                <div class="col-4">
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-fire"></i> High-risk permits open on site</h3>
                            <div class="eptw-card-actions"><a href="<?= $reg(['view' => 'high_risk']); ?>" class="eptw-badge <?= $cards['high_risk'] ? 'bad' : 'muted'; ?>"><?= (int) $cards['high_risk']; ?></a></div></div>
                        <div class="eptw-card-body">
                            <div class="eptw-small eptw-muted" style="margin-bottom:8px">Open on site = issued, active, on hold, suspended or expired. High risk = permit type classified high-risk, or the permit's own assessment scored high.</div>
                            <?php if (count($high_risk)) { ?>
                                <div class="eptw-risk-list">
                                    <?php foreach ($high_risk as $h) {
                                        $mix = array_filter([
                                            ['active,active_extended', 'live', (int) $h->s_active, 'active'],
                                            ['issued', '', (int) $h->s_issued, 'issued'],
                                            ['on_hold,on_hold_simops', 'warn', (int) $h->s_on_hold, 'on hold'],
                                            ['suspended', 'bad', (int) $h->s_suspended, 'suspended'],
                                            ['expired', 'bad', (int) $h->s_expired, 'expired'],
                                        ], function ($m) { return $m[2] > 0; }); ?>
                                        <div class="eptw-risk-item">
                                            <span class="eptw-type-dot" style="background:<?= html_escape($h->color); ?>"><i class="<?= html_escape($h->icon); ?>"></i></span>
                                            <span style="min-width:0;flex:1"><a href="<?= $reg(['view' => 'high_risk', 'type' => $h->id]); ?>" class="eptw-strong"><?= html_escape($h->name); ?></a>
                                                <span class="eptw-status-mix"><?php foreach ($mix as $m) { ?><a href="<?= $reg(['view' => 'high_risk', 'type' => $h->id, 'status' => $m[0]]); ?>" class="<?= $m[1]; ?>"><?= $m[2] . ' ' . $m[3]; ?></a><?php } ?></span></span>
                                            <a href="<?= $reg(['view' => 'high_risk', 'type' => $h->id]); ?>" class="n"><?= (int) $h->n; ?></a>
                                            <span class="why"><?= $h->type_high_risk ? 'Type classified high-risk' : 'Assessed high risk on the permit (hazards / shift / crew size)'; ?></span>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                <div class="eptw-empty eptw-small" style="padding:18px"><i class="fa-solid fa-shield-heart"></i>No high-risk permit is open right now.</div>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="eptw-card <?= count($simops) ? 'danger' : ''; ?>">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-diagram-project"></i> SIMOPS conflicts</h3>
                            <div class="eptw-card-actions"><a href="<?= $reg(['view' => 'simops']); ?>" class="eptw-badge <?= count($simops) ? 'bad' : 'muted'; ?>"><?= count($simops); ?></a></div></div>
                        <div class="eptw-card-body">
                            <?php if (eptw_opt('eptw_simops_enabled') !== '1') { ?>
                                <div class="eptw-note warn" style="margin:0">SIMOPS detection is switched off (Setup → General).</div>
                            <?php } elseif (count($simops)) { ?>
                                <ul class="eptw-mini-list">
                                    <?php foreach ($simops as $sm) { ?>
                                        <li>
                                            <span class="eptw-type-dot" style="background:<?= html_escape($sm->type_color); ?>"><i class="fa-solid fa-triangle-exclamation"></i></span>
                                            <span class="t"><a href="<?= admin_url('eptw/view/' . $sm->id); ?>" class="eptw-permit-no"><?= html_escape($sm->permit_no); ?></a>
                                                <span class="s"><?= html_escape($sm->type_name . ' · ' . $sm->area_name); ?><?= $sm->simops_ack_at ? ' · acknowledged' : ' · not acknowledged'; ?></span>
                                                <span class="s" title="<?= html_escape($sm->simops_notes); ?>"><?= html_escape(mb_substr((string) $sm->simops_notes, 0, 90)); ?></span></span>
                                            <?= eptw_status_badge($sm->status); ?>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } else { ?>
                                <div class="eptw-empty eptw-small" style="padding:18px"><i class="fa-solid fa-circle-check"></i>No simultaneous-operations conflict detected.</div>
                            <?php } ?>
                        </div>
                    </div>

                    <?php if (eptw_can('create')) { ?>
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
                                    <br><a href="<?= admin_url('eptw/permit'); ?>" class="eptw-btn eptw-btn-sm eptw-btn-primary" style="margin-top:10px"><i class="fa fa-plus"></i> New permit</a></div>
                            <?php } ?>
                        </div>
                    </div>
                    <?php } ?>

                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> Latest permits</h3><div class="eptw-card-actions eptw-basis">Created in the last 7 days</div></div>
                        <div class="eptw-card-body">
                            <?php if (count($latest)) { ?>
                                <ul class="eptw-mini-list">
                                    <?php foreach ($latest as $r) { ?>
                                        <li>
                                            <span class="eptw-avatar" title="<?= html_escape($r->engineer_name); ?>"><?= html_escape(eptw_initials($r->engineer_name)); ?></span>
                                            <span class="t"><a href="<?= admin_url('eptw/view/' . $r->id); ?>" class="eptw-strong"><?= html_escape($r->work_title); ?></a>
                                                <span class="s"><?= html_escape($r->type_name); ?> · <?= eptw_time_ago($r->created_at); ?></span></span>
                                            <?= eptw_status_badge($r->status); ?>
                                        </li>
                                    <?php } ?>
                                </ul>
                                <a href="<?= $reg(['view' => 'latest']); ?>" class="eptw-btn eptw-btn-sm eptw-btn-ghost" style="margin-top:8px">All created in the last 7 days ›</a>
                            <?php } else { ?>
                                <div class="eptw-empty eptw-small" style="padding:18px"><i class="fa-regular fa-folder-open"></i>No permit created in the last 7 days.</div>
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
