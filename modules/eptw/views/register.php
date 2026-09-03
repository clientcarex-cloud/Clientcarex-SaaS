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
                'issued'       => 'Issued',
                'active'       => 'Active',
                'expiring'     => 'Expiring soon',
                'expired'      => 'Expired',
                'suspended'    => 'Suspended / on hold',
                'extensions'   => 'Extension requests',
                'high_risk'    => 'High risk',
                'simops'       => 'SIMOPS',
                'closed'       => 'Closed',
                'docs_pending' => 'Docs pending',
                'drafts'       => 'Drafts',
                'mine'         => 'Mine',
            ];
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
                            <?php foreach (eptw_statuses() as $k => $s) { ?><option value="<?= $k; ?>" <?= $filters['status'] === $k ? 'selected' : ''; ?>><?= html_escape($s['label']); ?></option><?php } ?>
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
                        <a href="<?= admin_url('eptw/export?' . http_build_query(array_filter($filters))); ?>" class="eptw-btn eptw-btn-sm" title="Download the register for Excel"><i class="fa-solid fa-file-excel"></i> Export</a>
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
                        <table class="eptw-table">
                            <thead>
                                <tr>
                                    <th>Permit</th>
                                    <th>Type</th>
                                    <th>Project / area</th>
                                    <th>Contractor</th>
                                    <th>Window</th>
                                    <th>People</th>
                                    <th>Status</th>
                                    <th class="eptw-num">Ext.</th>
                                    <th>Flags</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $r) { ?>
                                <tr class="<?= $r->is_expired ? 'is-expired' : ''; ?>">
                                    <td>
                                        <a href="<?= admin_url('eptw/view/' . $r->id); ?>" class="eptw-permit-no <?= $r->permit_no ? '' : 'draft'; ?>"><?= html_escape($r->permit_no ?: 'no number yet'); ?></a>
                                        <div class="eptw-small"><a href="<?= admin_url('eptw/view/' . $r->id); ?>" class="eptw-strong"><?= html_escape(mb_substr($r->work_title, 0, 70)); ?></a></div>
                                        <div class="eptw-small eptw-muted"><?= html_escape(implode(' · ', array_filter([$r->work_order ? 'WO ' . $r->work_order : '', $r->location, $r->equipment_tag]))); ?></div>
                                    </td>
                                    <td>
                                        <span class="eptw-type-chip"><span class="eptw-type-dot" style="background:<?= html_escape($r->type_color); ?>"><i class="<?= html_escape($r->type_icon); ?>"></i></span> <?= html_escape($r->type_code); ?></span>
                                        <div class="eptw-small"><?= eptw_risk_badge($r->risk_level); ?></div>
                                    </td>
                                    <td class="eptw-small"><span class="eptw-strong"><?= html_escape($r->project_name); ?></span><div class="eptw-muted"><?= html_escape($r->area_name ?: '—'); ?></div></td>
                                    <td class="eptw-small"><?= html_escape($r->contractor_name ?: '—'); ?><?= $r->subcontractor ? '<div class="eptw-muted">' . html_escape($r->subcontractor) . '</div>' : ''; ?></td>
                                    <td class="eptw-small" style="white-space:nowrap">
                                        <?= eptw_dt($r->start_at); ?><div class="eptw-muted">→ <?= eptw_dt($r->end_at); ?></div>
                                        <?php if ($r->expiring_soon || $r->is_expired) { ?><span class="eptw-badge <?= $r->is_expired ? 'bad' : 'warn'; ?>"><?= $r->is_expired ? 'expired' : eptw_time_until($r->end_at); ?></span><?php } ?>
                                    </td>
                                    <td class="eptw-small">
                                        <div title="Engineer"><i class="fa-solid fa-helmet-safety eptw-muted"></i> <?= html_escape($r->engineer_name ?: '—'); ?></div>
                                        <?php if ($r->area_authority_name) { ?><div class="eptw-muted" title="Area Authority"><i class="fa-solid fa-map-location-dot"></i> <?= html_escape($r->area_authority_name); ?></div><?php } ?>
                                        <?php if ($r->hse_officer_name) { ?><div class="eptw-muted" title="HSE"><i class="fa-solid fa-shield-halved"></i> <?= html_escape($r->hse_officer_name); ?></div><?php } ?>
                                    </td>
                                    <td><?= eptw_status_badge($r->status); ?></td>
                                    <td class="eptw-num"><?= (int) $r->extension_count ?: '<span class="eptw-muted">0</span>'; ?></td>
                                    <td class="eptw-small">
                                        <?php if ($r->high_risk) { ?><span class="eptw-badge bad" title="High risk"><i class="fa-solid fa-fire"></i></span> <?php } ?>
                                        <?php if ($r->simops_flag) { ?><span class="eptw-badge bad" title="SIMOPS conflict"><i class="fa-solid fa-diagram-project"></i></span> <?php } ?>
                                        <?php if ((int) $r->pending_extensions) { ?><span class="eptw-badge info" title="Extension pending"><i class="fa-solid fa-clock-rotate-left"></i></span> <?php } ?>
                                        <?php if ($r->gas_test_required && !(int) $r->gas_test_count && in_array($r->status, eptw_live_statuses(), true)) { ?><span class="eptw-badge warn" title="Gas test required — none recorded"><i class="fa-solid fa-vial"></i></span> <?php } ?>
                                        <?php if ((int) $r->doc_count) { ?><span class="eptw-badge muted" title="<?= (int) $r->doc_count; ?> document(s)"><i class="fa-solid fa-paperclip"></i> <?= (int) $r->doc_count; ?></span><?php } ?>
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
