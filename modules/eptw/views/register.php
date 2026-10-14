<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'register'; include __DIR__ . '/_nav.php'; ?>

            <?php
            $views = [
                ''             => 'All',
                'pending'      => 'Pending approvals',
                'issued'       => 'Issued — not started',
                'active'       => 'Work authorized',
                'expiring'     => 'Expiring soon',
                'expired'      => 'Expired',
                'on_hold'      => 'On hold',
                'suspended'    => 'Suspended',
                'extensions'   => 'Extension requests',
                'high_risk'    => 'High risk',
                'simops'       => 'SIMOPS',
                'closed'       => 'Closed',
                'docs_pending' => 'Closed – docs pending',
                'drafts'       => 'Drafts',
                'mine'         => 'Mine',
            ];
            if (!isset($views[$filters['view']]) && $filters['view'] !== '') {
                $views[$filters['view']] = ['ready' => 'Ready to issue', 'live' => 'Open on site', 'latest' => 'Last 7 days', 'today' => 'Today'][$filters['view']] ?? ucfirst($filters['view']);
            }
            if (!eptw_perm('eptw_register')) {
                $views = ['pending' => $views['pending']]; // "Pending approvals" menu only
            }
            $qs = function (array $override) use ($filters) {
                return admin_url('eptw/register?' . http_build_query(array_filter(array_merge($filters, $override), function ($v) { return $v !== '' && $v !== null; })));
            };
            ?>
            <div class="eptw-views">
                <?php foreach ($views as $key => $label) { ?>
                    <a href="<?= $qs(['view' => $key, 'page' => '']); ?>" class="<?= $filters['view'] === $key ? 'active' : ''; ?>"><?= $label; ?></a>
                <?php } ?>
            </div>

            <div class="eptw-card">
                <div class="eptw-card-head">
                    <form id="eptw-filters" method="get" action="<?= admin_url('eptw/register'); ?>" class="eptw-filters" style="flex:1">
                        <input type="hidden" name="view" value="<?= html_escape($filters['view']); ?>">
                        <input type="search" name="q" class="eptw-input q" placeholder="Search permit no, work, location, tag, WO…" value="<?= html_escape($filters['q']); ?>">
                        <select name="project" class="eptw-select"><option value="">All projects</option>
                            <?php foreach (eptw_projects(false) as $p) { ?><option value="<?= $p->id; ?>" <?= (int) $filters['project'] === (int) $p->id ? 'selected' : ''; ?>><?= html_escape($p->name); ?></option><?php } ?>
                        </select>
                        <select name="area" class="eptw-select"><option value="">All areas</option>
                            <?php foreach (eptw_areas((int) $filters['project'], false) as $a) { ?><option value="<?= $a->id; ?>" <?= (int) $filters['area'] === (int) $a->id ? 'selected' : ''; ?>><?= html_escape($a->name); ?></option><?php } ?>
                        </select>
                        <select name="type" class="eptw-select"><option value="">All types</option>
                            <?php foreach (eptw_permit_types(false) as $t) { ?><option value="<?= $t->id; ?>" <?= (int) $filters['type'] === (int) $t->id ? 'selected' : ''; ?>><?= html_escape($t->name); ?></option><?php } ?>
                        </select>
                        <select name="contractor" class="eptw-select"><option value="">All contractors</option>
                            <?php foreach (eptw_contractors(false) as $c) { ?><option value="<?= $c->id; ?>" <?= (int) $filters['contractor'] === (int) $c->id ? 'selected' : ''; ?>><?= html_escape($c->name); ?></option><?php } ?>
                        </select>
                        <select name="status" class="eptw-select"><option value="">Any status</option>
                            <?php if ($filters['status'] !== '' && !isset(eptw_statuses()[$filters['status']])) { ?><option value="<?= html_escape($filters['status']); ?>" selected><?= html_escape(implode(' + ', array_map('eptw_status_label', explode(',', $filters['status'])))); ?></option><?php } ?>
                            <?php foreach (eptw_statuses() as $k => $st) { ?><option value="<?= $k; ?>" <?= $filters['status'] === $k ? 'selected' : ''; ?>><?= html_escape($st['label']); ?></option><?php } ?>
                        </select>
                        <select name="shift" class="eptw-select"><option value="">Any shift</option>
                            <?php foreach (eptw_shifts() as $k => $l) { ?><option value="<?= $k; ?>" <?= $filters['shift'] === $k ? 'selected' : ''; ?>><?= $l; ?></option><?php } ?>
                        </select>
                        <select name="risk" class="eptw-select"><option value="">Any risk</option>
                            <?php foreach (eptw_risk_levels() as $k => $l) { ?><option value="<?= $k; ?>" <?= $filters['risk'] === $k ? 'selected' : ''; ?>><?= $l; ?> risk</option><?php } ?>
                        </select>
                        <input type="date" name="from" class="eptw-input" value="<?= html_escape($filters['from']); ?>" title="Start date from">
                        <input type="date" name="to" class="eptw-input" value="<?= html_escape($filters['to']); ?>" title="Start date to">
                        <button type="submit" class="eptw-btn"><i class="fa fa-search"></i></button>
                        <?php if (array_filter($filters)) { ?><a href="<?= admin_url('eptw/register'); ?>" class="eptw-btn eptw-btn-ghost eptw-btn-sm" title="Clear filters"><i class="fa fa-times"></i> Clear</a><?php } ?>
                    </form>
                    <div class="eptw-card-actions">
                        <div class="eptw-cols" data-eptw-cols="register">
                            <button type="button" class="eptw-btn eptw-btn-sm" data-eptw-cols-toggle><i class="fa-solid fa-table-columns"></i> Columns</button>
                            <div class="eptw-cols-menu"></div>
                        </div>
                        <?php if (eptw_can('register')) { ?><a href="<?= admin_url('eptw/export?' . http_build_query(array_filter($filters))); ?>" class="eptw-btn eptw-btn-sm" title="Download the register for Excel"><i class="fa-solid fa-file-excel"></i> Export</a><?php } ?>
                        <?php if (eptw_can('import')) { ?><a href="<?= admin_url('eptw/eptw_setup/import'); ?>" class="eptw-btn eptw-btn-sm"><i class="fa-solid fa-file-import"></i> Import</a><?php } ?>
                    </div>
                </div>

                <?php if (!count($rows)) { ?>
                    <div class="eptw-empty">
                        <i class="fa-solid fa-table-list"></i>
                        <h4>No permits here</h4>
                        <p class="eptw-muted"><?= array_filter($filters) ? 'Nothing matches these filters.' : 'The register fills up as permits are raised. Every issue, extension, suspension and closure lands here automatically.'; ?></p>
                        <?php if (eptw_can('create') && !array_filter($filters)) { ?><a href="<?= admin_url('eptw/permit'); ?>" class="eptw-btn eptw-btn-primary"><i class="fa fa-plus"></i> Raise the first permit</a><?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="eptw-table-scroll">
                        <table class="eptw-table" data-eptw-cols-table="register">
                            <thead>
                                <tr>
                                    <th data-col="permit" data-fixed>Permit</th>
                                    <th data-col="type">Type</th>
                                    <th data-col="project">Project</th>
                                    <th data-col="area">Area</th>
                                    <th data-col="contractor">Contractor</th>
                                    <th data-col="engineer">Engineer</th>
                                    <th data-col="aa">Area Authority</th>
                                    <th data-col="hse">HSE Officer</th>
                                    <th data-col="start">Start</th>
                                    <th data-col="end">End</th>
                                    <th data-col="status" data-fixed>Status</th>
                                    <th data-col="ext" class="eptw-num">Ext.</th>
                                    <th data-col="high">High risk</th>
                                    <th data-col="simops">SIMOPS</th>
                                    <th data-col="closed">Closure date</th>
                                    <th data-col="docs">Attachments</th>
                                    <th data-col="flags" data-off>Other flags</th>
                                    <th data-fixed></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $r) { ?>
                                <tr class="<?= $r->is_expired ? 'is-expired' : ''; ?>">
                                    <td data-col="permit">
                                        <a href="<?= admin_url('eptw/view/' . $r->id); ?>" class="eptw-permit-no <?= $r->permit_no ? '' : 'draft'; ?>"><?= html_escape($r->permit_no ?: 'no number yet'); ?></a>
                                        <div class="eptw-small"><a href="<?= admin_url('eptw/view/' . $r->id); ?>" class="eptw-strong"><?= html_escape(mb_substr($r->work_title, 0, 70)); ?></a></div>
                                        <div class="eptw-small eptw-muted"><?= html_escape(implode(' · ', array_filter([$r->work_order ? 'WO ' . $r->work_order : '', $r->location, $r->equipment_tag]))); ?></div>
                                    </td>
                                    <td data-col="type">
                                        <span class="eptw-type-chip" title="<?= html_escape($r->type_name); ?>"><span class="eptw-type-dot" style="background:<?= html_escape($r->type_color); ?>"><i class="<?= html_escape($r->type_icon); ?>"></i></span> <?= html_escape($r->type_code); ?></span>
                                        <div class="eptw-small"><?= eptw_risk_badge($r->risk_level); ?></div>
                                    </td>
                                    <td data-col="project" class="eptw-small eptw-strong"><?= html_escape($r->project_name); ?></td>
                                    <td data-col="area" class="eptw-small"><?= html_escape($r->area_name ?: '—'); ?></td>
                                    <td data-col="contractor" class="eptw-small"><?= html_escape($r->contractor_name ?: '—'); ?><?= $r->subcontractor ? '<div class="eptw-muted">' . html_escape($r->subcontractor) . '</div>' : ''; ?></td>
                                    <td data-col="engineer" class="eptw-small"><?= html_escape($r->engineer_name ?: '—'); ?></td>
                                    <td data-col="aa" class="eptw-small"><?= html_escape($r->area_authority_name ?: '—'); ?></td>
                                    <td data-col="hse" class="eptw-small"><?= html_escape($r->hse_officer_name ?: '—'); ?></td>
                                    <td data-col="start" class="eptw-small" style="white-space:nowrap"><?= eptw_dt($r->start_at); ?></td>
                                    <td data-col="end" class="eptw-small" style="white-space:nowrap"><?= eptw_dt($r->end_at); ?>
                                        <?php if ($r->expiring_soon || $r->is_expired) { ?><div><span class="eptw-badge <?= $r->is_expired ? 'bad' : 'warn'; ?>"><?= $r->is_expired ? 'expired' : eptw_time_until($r->end_at); ?></span></div><?php } ?>
                                    </td>
                                    <td data-col="status"><?= eptw_status_badge($r->status); ?>
                                        <?php if (in_array($r->status, eptw_live_statuses(), true)) { ?><div style="margin-top:4px"><span class="eptw-auth-chip <?= $r->work_auth['ok'] ? 'ok' : 'no'; ?>" title="<?= html_escape($r->work_auth['detail']); ?>"><i class="fa-solid <?= $r->work_auth['ok'] ? 'fa-check' : 'fa-ban'; ?>"></i> <?= $r->work_auth['ok'] ? 'Work authorized' : 'Not authorized'; ?></span></div><?php } ?>
                                    </td>
                                    <td data-col="ext" class="eptw-num"><?= (int) $r->extension_count ?: '<span class="eptw-muted">0</span>'; ?><?= (int) $r->pending_extensions ? ' <span class="eptw-badge info" title="Extension awaiting approval"><i class="fa-solid fa-clock-rotate-left"></i></span>' : ''; ?></td>
                                    <td data-col="high" class="eptw-small"><?= $r->high_risk ? '<span class="eptw-badge bad" title="' . html_escape(eptw_risk_reason_text($r)) . '"><i class="fa-solid fa-fire"></i> Yes</span>' : '<span class="eptw-muted">No</span>'; ?></td>
                                    <td data-col="simops" class="eptw-small"><?= $r->simops_flag ? '<span class="eptw-badge bad" title="' . html_escape((string) $r->simops_notes) . '"><i class="fa-solid fa-diagram-project"></i> Yes</span>' : '<span class="eptw-muted">No</span>'; ?></td>
                                    <td data-col="closed" class="eptw-small" style="white-space:nowrap"><?= $r->closed_at ? eptw_dt($r->closed_at, false) : '<span class="eptw-muted">—</span>'; ?></td>
                                    <td data-col="docs" class="eptw-small" style="white-space:nowrap">
                                        <span title="<?= (int) $r->doc_count; ?> file(s) attached"><i class="fa-solid fa-paperclip eptw-muted"></i> <?= (int) $r->doc_count; ?></span>
                                        <?php if ($r->req_docs_total) { $complete = (int) $r->req_docs_have >= (int) $r->req_docs_total; ?>
                                            <span class="eptw-badge <?= $complete ? 'ok' : (in_array($r->status, ['closed_docs_pending'], true) ? 'bad' : 'muted'); ?>" title="Required closure documents<?= eptw_opt('eptw_docs_review') === '1' ? ' (verified)' : ''; ?>"><?= (int) $r->req_docs_have; ?>/<?= (int) $r->req_docs_total; ?> req.</span>
                                        <?php } ?>
                                    </td>
                                    <td data-col="flags" class="eptw-small">
                                        <?php if ($r->gas_test_required && !(int) $r->gas_test_count && in_array($r->status, eptw_live_statuses(), true)) { ?><span class="eptw-badge warn" title="Gas test required — none recorded"><i class="fa-solid fa-vial"></i></span> <?php } ?>
                                        <?php if ($r->isolation_permit_no) { ?><span class="eptw-badge info" title="Isolation under <?= html_escape($r->isolation_permit_no); ?>"><i class="fa-solid fa-lock"></i></span><?php } ?>
                                    </td>
                                    <td class="eptw-actions">
                                        <a href="<?= admin_url('eptw/view/' . $r->id); ?>" class="eptw-btn eptw-btn-sm">Open</a>
                                        <?php if ($r->permit_no) { ?><a href="<?= admin_url('eptw/pdf/' . $r->id); ?>" target="_blank" class="eptw-btn eptw-btn-sm eptw-btn-ghost" title="PDF"><i class="fa-solid fa-file-pdf"></i></a><?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="eptw-pager">
                        <span><?= (int) $total; ?> permit(s)</span>
                        <?php if ($pages > 1) { ?>
                            <?php if ($page > 1) { ?><a href="<?= $qs(['page' => $page - 1]); ?>" class="eptw-btn eptw-btn-sm">‹ Prev</a><?php } ?>
                            <span>Page <?= (int) $page; ?> / <?= (int) $pages; ?></span>
                            <?php if ($page < $pages) { ?><a href="<?= $qs(['page' => $page + 1]); ?>" class="eptw-btn eptw-btn-sm">Next ›</a><?php } ?>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
