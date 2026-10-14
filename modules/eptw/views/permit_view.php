<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$p    = $permit;
$act  = function ($name) use ($p) { return admin_url('eptw/act/' . $p->id . '/' . $name); };
$csrf = form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash());
$dtl  = function ($value) { return $value ? date('Y-m-d\TH:i', strtotime($value)) : ''; };
$camera = eptw_camera_mode($project);
$steps = [
    ['Draft', in_array($p->status, ['draft'], true), !in_array($p->status, ['draft'], true)],
    ['Number requested', in_array($p->status, ['number_requested', 'returned'], true), !in_array($p->status, ['draft', 'number_requested', 'returned'], true)],
    ['Under review', $p->status === 'under_review', in_array($p->status, array_merge(eptw_live_statuses(), eptw_closed_statuses()), true)],
    ['Issued', $p->status === 'issued' || $p->status === 'on_hold_simops', in_array($p->status, array_merge(['active', 'active_extended', 'suspended', 'on_hold', 'expired'], eptw_closed_statuses()), true)],
    ['Active', in_array($p->status, ['active', 'active_extended', 'suspended', 'on_hold', 'expired'], true), in_array($p->status, eptw_closed_statuses(), true)],
    ['Closed', in_array($p->status, ['closed', 'closed_docs_pending'], true), $p->status === 'archived'],
];
$auth      = $p->work_auth;
$auth_cls  = $auth['ok'] ? 'ok' : (in_array($p->status, eptw_live_statuses(), true) ? 'no' : 'idle');
$risk_why  = eptw_risk_reason_text($p);
$closing   = eptw_closure_items();
$me_name   = eptw_staff_name(eptw_me()['staff_id']);
$tbt_mode  = eptw_opt('eptw_toolbox_mode');
$tbt_docs  = array_values(array_filter($documents, function ($d) { return $d->doc_type === 'toolbox_talk'; }));
?>
<datalist id="eptw-people"><?php foreach ($people as $pe) { ?><option value="<?= html_escape($pe->name); ?>" data-badge="<?= html_escape($pe->badge_no); ?>"><?= html_escape(trim($pe->position . ' · ' . ($pe->contractor_name ?: $pe->company) . ($pe->badge_no ? ' · ' . $pe->badge_no : ''), ' ·')); ?></option><?php } ?></datalist>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'register'; include __DIR__ . '/_nav.php'; ?>

            <!-- ── Hero ─────────────────────────────────────────────────────── -->
            <div class="eptw-hero">
                <div style="min-width:0">
                    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
                        <h1 class="<?= $p->permit_no ? '' : 'draft'; ?>"><?= html_escape($p->permit_no ?: 'No permit number yet'); ?></h1>
                        <?= eptw_status_badge($p->status, 'lg'); ?>
                        <?php if ($p->permit_no) { ?><button type="button" class="eptw-btn eptw-btn-sm" data-eptw-copy="<?= html_escape($p->permit_no); ?>" title="Copy permit number"><i class="fa-regular fa-copy"></i></button><?php } ?>
                    </div>
                    <div class="eptw-hero-sub"><b><?= html_escape($p->work_title); ?></b> — <?= html_escape($type ? $type->name : ''); ?></div>
                    <div class="eptw-hero-meta">
                        <span><i class="fa-solid fa-diagram-project"></i> <b><?= html_escape($p->project_name); ?></b> / <?= html_escape($p->area_name ?: '—'); ?><?= $p->location ? ' · ' . html_escape($p->location) : ''; ?></span>
                        <span><i class="fa-solid fa-calendar"></i> <b><?= eptw_dt($p->start_at); ?></b> → <b><?= eptw_dt($p->end_at); ?></b> (<?= eptw_hours_between($p->start_at, $p->end_at); ?> h<?= $p->extension_count ? ', ' . (int) $p->extension_count . ' ext.' : ''; ?>)</span>
                        <span><i class="fa-solid fa-helmet-safety"></i> <?= html_escape($p->engineer_name); ?><?= $p->contractor_name ? ' · ' . html_escape($p->contractor_name) : ''; ?></span>
                        <span title="<?= html_escape($risk_why); ?>"><?= eptw_risk_badge($p->risk_level); ?><?= $p->high_risk ? ' <span class="eptw-badge bad"><i class="fa-solid fa-fire"></i> ' . ($p->type_high_risk ? 'High-risk type' : 'Assessed high risk') . '</span>' : ''; ?></span>
                    </div>
                    <?php if (count($p->auto_flags)) { ?>
                        <div class="eptw-flags"><?php foreach ($p->auto_flags as $f) { ?><span class="eptw-badge <?= $f[0]; ?>"><i class="fa-solid fa-bolt"></i> <?= html_escape($f[1]); ?></span><?php } ?></div>
                    <?php } ?>
                </div>
                <div class="eptw-hero-actions">
                    <div class="row-btns">
                        <?php if ($can['edit']) { ?><a href="<?= admin_url('eptw/permit/' . $p->id); ?>" class="eptw-btn"><i class="fa fa-pen"></i> Edit</a><?php } ?>
                        <?php if ($can['submit']) { ?><button class="eptw-btn eptw-btn-primary" data-eptw-modal="m-submit"><i class="fa-solid fa-paper-plane"></i> Request permit number</button><?php } ?>
                        <?php foreach ($sign_steps as $s) { ?><button class="eptw-btn eptw-btn-primary" data-eptw-modal="m-sign-<?= $s; ?>"><i class="fa-solid fa-pen-nib"></i> Sign as <?= html_escape($this->permits->step_label($s)); ?></button><?php } ?>
                        <?php if ($can['issue']) { ?><button class="eptw-btn eptw-btn-primary" data-eptw-modal="m-issue"><i class="fa-solid fa-stamp"></i> Issue permit number</button><?php } ?>
                        <?php if ($can['activate']) { ?><button class="eptw-btn eptw-btn-ok" data-eptw-modal="m-activate"><i class="fa-solid fa-play"></i> Confirm start of work</button><?php } ?>
                        <?php if ($can['resume']) { ?><button class="eptw-btn eptw-btn-ok" data-eptw-modal="m-resume"><i class="fa-solid fa-play"></i> <?= $p->status === 'on_hold_simops' ? 'Resolve SIMOPS & resume' : 'Resume'; ?></button><?php } ?>
                        <?php if ($can['extend']) { ?><button class="eptw-btn" data-eptw-modal="m-extend"><i class="fa-solid fa-clock-rotate-left"></i> <?= eptw_can('extend_approve') ? 'Extend' : 'Request extension'; ?></button><?php } ?>
                        <?php if ($can['simops_ack'] && !$p->simops_ack_at) { ?><button class="eptw-btn eptw-btn-warn" data-eptw-modal="m-simops-ack"><i class="fa-solid fa-diagram-project"></i> Acknowledge SIMOPS</button><?php } ?>
                        <?php if ($can['suspend']) { ?><button class="eptw-btn eptw-btn-warn" data-eptw-modal="m-suspend"><i class="fa-solid fa-pause"></i> Suspend</button><?php } ?>
                        <?php if ($can['hold']) { ?><button class="eptw-btn" data-eptw-modal="m-hold"><i class="fa-solid fa-hand"></i> Hold</button><?php } ?>
                        <?php if ($can['close']) { ?><button class="eptw-btn eptw-btn-dark" data-eptw-modal="m-close"><i class="fa-solid fa-circle-check"></i> Close permit</button><?php } ?>
                    </div>
                    <div class="row-btns">
                        <?php if ($can['return']) { ?><button class="eptw-btn eptw-btn-sm" data-eptw-modal="m-return"><i class="fa-solid fa-rotate-left"></i> Return</button><?php } ?>
                        <a href="<?= admin_url('eptw/print_permit/' . $p->id); ?>" target="_blank" class="eptw-btn eptw-btn-sm"><i class="fa-solid fa-print"></i> Print</a>
                        <a href="<?= admin_url('eptw/pdf/' . $p->id); ?>" target="_blank" class="eptw-btn eptw-btn-sm"><i class="fa-solid fa-file-pdf"></i> PDF</a>
                        <?php if ($can['archive']) { ?><button class="eptw-btn eptw-btn-sm" data-eptw-act="<?= $act('archive'); ?>" data-confirm="Archive this permit?"><i class="fa-solid fa-box-archive"></i> Archive</button><?php } ?>
                        <?php if ($can['cancel']) { ?><button class="eptw-btn eptw-btn-sm eptw-btn-danger" data-eptw-modal="m-cancel"><i class="fa-solid fa-ban"></i> Cancel</button><?php } ?>
                        <?php if ($can['delete']) { ?><button class="eptw-btn eptw-btn-sm eptw-btn-danger" data-eptw-act="<?= $act('delete'); ?>" data-confirm="Delete this permit and everything attached to it? This cannot be undone."><i class="fa fa-trash"></i></button><?php } ?>
                    </div>
                </div>
            </div>

            <!-- ── Work authorization: kept apart from the administrative status ── -->
            <div class="eptw-auth <?= $auth_cls; ?>">
                <div class="ic"><i class="fa-solid <?= $auth['ok'] ? 'fa-person-digging' : 'fa-ban'; ?>"></i></div>
                <div style="min-width:0">
                    <div class="t"><?= $auth['ok'] ? 'Work authorized' : 'Work not authorized'; ?></div>
                    <div class="d"><?= html_escape($auth['detail']); ?> <span class="eptw-muted">· Permit status: <?= html_escape(eptw_status_label($p->status)); ?></span></div>
                </div>
                <?php if (!$auth['ok'] && in_array($p->status, eptw_live_statuses(), true)) { ?>
                    <div class="acts">
                        <?php if ($can['activate']) { ?><button class="eptw-btn eptw-btn-sm eptw-btn-ok" data-eptw-modal="m-activate"><i class="fa-solid fa-play"></i> Confirm start of work</button><?php } ?>
                        <?php if ($can['resume']) { ?><button class="eptw-btn eptw-btn-sm eptw-btn-ok" data-eptw-modal="m-resume"><i class="fa-solid fa-play"></i> Resume</button><?php } ?>
                        <?php if ($p->status === 'expired' && $can['extend']) { ?><button class="eptw-btn eptw-btn-sm eptw-btn-primary" data-eptw-modal="m-extend"><i class="fa-solid fa-clock-rotate-left"></i> <?= eptw_can('extend_approve') ? 'Extend / revalidate' : 'Request extension'; ?></button><?php } ?>
                        <?php if ($p->status === 'expired' && (int) $p->pending_extensions) { ?><a href="#extensions" class="eptw-btn eptw-btn-sm" data-pane="extensions"><i class="fa-solid fa-hourglass-half"></i> Extension awaiting approval</a><?php } ?>
                        <?php if ($can['close']) { ?><button class="eptw-btn eptw-btn-sm eptw-btn-dark" data-eptw-modal="m-close"><i class="fa-solid fa-circle-check"></i> Close permit</button><?php } ?>
                    </div>
                <?php } ?>
            </div>

            <?php if (count($dependents)) { ?>
                <div class="eptw-note info"><i class="fa-solid fa-lock"></i> <b>Isolation in use by <?= count($dependents); ?> work permit(s):</b>
                    <?php foreach ($dependents as $d) { ?><a href="<?= admin_url('eptw/view/' . $d->id); ?>"><?= html_escape($d->permit_no ?: 'draft #' . $d->id); ?></a> (<?= html_escape(eptw_status_label($d->status)); ?>) <?php } ?>
                    — they must be closed before this isolation is removed and the permit closed.</div>
            <?php } ?>
            <?php if ((int) $p->isolation_permit_id) { ?>
                <div class="eptw-note info"><i class="fa-solid fa-link"></i> <b>Relies on isolation permit</b> <a href="<?= admin_url('eptw/view/' . (int) $p->isolation_permit_id); ?>"><?= html_escape($p->isolation_permit_no ?: 'draft'); ?></a> (<?= html_escape(eptw_status_label($p->isolation_permit_status)); ?>).</div>
            <?php } ?>

            <?php if ($p->status === 'on_hold_simops') { ?>
                <div class="eptw-note bad"><i class="fa-solid fa-triangle-exclamation"></i> <b>On hold — SIMOPS conflict.</b> The permit number is issued but work must not start until the coordinator resolves the conflict below and resumes the permit.<br><span style="white-space:pre-line"><?= html_escape($p->simops_notes); ?></span></div>
            <?php } elseif (count($conflicts)) { ?>
                <div class="eptw-note <?= array_filter($conflicts, function ($c) { return $c['severity'] === 'block'; }) ? 'bad' : 'warn'; ?>">
                    <i class="fa-solid fa-diagram-project"></i> <b>SIMOPS check:</b> <?= count($conflicts); ?> other open permit(s) in <?= html_escape($p->area_name); ?> overlap this window.
                    <?php if ($p->simops_ack_at) { ?><span class="eptw-badge ok"><i class="fa fa-check"></i> Acknowledged by <?= html_escape(eptw_staff_name($p->simops_ack_by)); ?> <?= eptw_time_ago($p->simops_ack_at); ?></span><?php } elseif ($can['simops_ack']) { ?><button class="eptw-btn eptw-btn-sm eptw-btn-warn" data-eptw-modal="m-simops-ack">Acknowledge</button><?php } ?>
                    <ul><?php foreach ($conflicts as $c) { ?><li><?= html_escape($c['description']); ?> — <a href="<?= admin_url('eptw/view/' . $c['permit_id']); ?>"><?= html_escape($c['permit_no'] ?: 'draft'); ?></a> (<?= html_escape($c['type_name']); ?>, <?= eptw_dt($c['start_at']); ?> → <?= eptw_dt($c['end_at']); ?>) <span class="eptw-badge <?= $c['severity'] === 'block' ? 'bad' : 'warn'; ?>"><?= $c['severity'] === 'block' ? 'blocks' : 'warning'; ?></span></li><?php } ?></ul>
                </div>
            <?php } ?>
            <?php if ($p->status === 'returned' && $p->return_reason) { ?>
                <div class="eptw-note warn"><i class="fa-solid fa-rotate-left"></i> <b>Returned for correction:</b> <?= html_escape($p->return_reason); ?> <?php if ($can['edit']) { ?>— <a href="<?= admin_url('eptw/permit/' . $p->id); ?>">edit and resubmit</a><?php } ?></div>
            <?php } ?>
            <?php if ($p->status === 'suspended') { ?>
                <div class="eptw-note bad"><i class="fa-solid fa-circle-pause"></i> <b>Suspended <?= eptw_time_ago($p->suspended_at); ?>:</b> <?= html_escape($p->suspend_reason); ?>. Work must stop until the coordinator resumes the permit.</div>
            <?php } ?>
            <?php if ($p->status === 'closed_docs_pending') { ?>
                <div class="eptw-note warn"><i class="fa-solid fa-folder-open"></i> <b>Closed — documents pending upload<?= eptw_opt('eptw_docs_review') === '1' ? ' / verification' : ''; ?>:</b> <?= html_escape(implode(', ', $missing_docs)); ?>. The permit record is not complete. Upload them under <a href="#documents" class="eptw-subtab-link" data-pane="documents">Documents</a> and the permit closes fully by itself.</div>
            <?php } ?>
            <?php if ((int) $p->pending_extensions && $can['extend_approve']) { ?>
                <div class="eptw-note info"><i class="fa-solid fa-clock-rotate-left"></i> <b>An extension request is waiting for your decision</b> — revalidate the conditions under <a href="#extensions" class="eptw-subtab-link" data-pane="extensions">Extensions &amp; shifts</a>.</div>
            <?php } ?>

            <div class="eptw-card">
                <div class="eptw-card-body" style="padding:10px 18px">
                    <div class="eptw-steps">
                        <?php foreach ($steps as $s) { ?><div class="eptw-step <?= $s[2] ? 'done' : ($s[1] ? 'now' : ''); ?> <?= in_array($p->status, ['cancelled'], true) ? 'bad' : ''; ?>"><div class="dot"></div><?= $s[0]; ?></div><?php } ?>
                    </div>
                </div>
            </div>

            <!-- ── Tabs ─────────────────────────────────────────────────────── -->
            <div class="eptw-subtabs">
                <a class="eptw-subtab active" data-pane="overview">Overview</a>
                <a class="eptw-subtab" data-pane="approvals">Approvals <span class="eptw-count"><?= count(array_filter($approvals, function ($a) { return $a->decision === 'approved'; })); ?>/<?= count($approvals); ?></span></a>
                <a class="eptw-subtab" data-pane="gas">Gas tests <span class="eptw-count"><?= count($gas_tests); ?></span></a>
                <a class="eptw-subtab" data-pane="extensions">Extensions &amp; shifts <span class="eptw-count"><?= count($extensions) + count($revalidations); ?></span></a>
                <a class="eptw-subtab" data-pane="toolbox">Toolbox talk <span class="eptw-count"><?= count($p->toolbox['attendees'] ?? []); ?></span></a>
                <a class="eptw-subtab" data-pane="documents">Documents <span class="eptw-count"><?= count($documents); ?></span></a>
                <a class="eptw-subtab" data-pane="activity">Activity <span class="eptw-count"><?= count($events); ?></span></a>
            </div>

            <!-- Overview -->
            <div class="eptw-pane active" id="pane-overview">
                <div class="eptw-split">
                    <div>
                        <div class="eptw-card">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-file-lines"></i> Work</h3></div>
                            <div class="eptw-card-body">
                                <p style="white-space:pre-line;margin-bottom:14px"><?= html_escape($p->work_description ?: '—'); ?></p>
                                <dl class="eptw-dl">
                                    <div><dt>Work order</dt><dd class="<?= $p->work_order ? '' : 'empty'; ?>"><?= html_escape($p->work_order ?: '—'); ?></dd></div>
                                    <div><dt>Equipment tag</dt><dd class="<?= $p->equipment_tag ? '' : 'empty'; ?>"><?= html_escape($p->equipment_tag ?: '—'); ?></dd></div>
                                    <div><dt>Shift</dt><dd><?= html_escape(eptw_shifts()[$p->shift] ?? $p->shift); ?></dd></div>
                                    <div><dt>Workers</dt><dd><?= (int) $p->workers_count ?: '—'; ?></dd></div>
                                    <div><dt>Subcontractor</dt><dd class="<?= $p->subcontractor ? '' : 'empty'; ?>"><?= html_escape($p->subcontractor ?: '—'); ?></dd></div>
                                    <div><dt>RA / JSA ref</dt><dd class="<?= $p->ra_ref ? '' : 'empty'; ?>"><?= html_escape($p->ra_ref ?: '—'); ?></dd></div>
                                    <div><dt>Weather</dt><dd class="<?= $p->weather ? '' : 'empty'; ?>"><?= html_escape($p->weather ?: '—'); ?></dd></div>
                                    <div><dt>Source</dt><dd><?= ucfirst($p->source); ?></dd></div>
                                </dl>
                                <?php
                                $general = []; $personnel = [];
                                foreach (($type ? $type->extra_fields : []) as $f) { if (($f['group'] ?? '') === 'personnel') { $personnel[] = $f; } else { $general[] = $f; } }
                                $fmt = function ($f, $val) {
                                    if ($f['type'] === 'checkboxes') { return count((array) $val) ? html_escape(implode(', ', (array) $val)) : '—'; }
                                    if ($f['type'] === 'detect') {
                                        $out = '';
                                        foreach ((array) $val as $k => $v) { $out .= '<span class="eptw-badge ' . ($v === 'detected' ? 'bad' : 'ok') . '" style="margin:2px 2px 2px 0">' . html_escape($k) . ': ' . ($v === 'detected' ? 'detected' : 'not detected') . '</span>'; }
                                        return $out ?: '—';
                                    }
                                    if ($f['type'] === 'yesno') { return $val === 'yes' ? '<span class="eptw-badge ok">Yes</span>' : ($val === 'no' ? '<span class="eptw-badge muted">No</span>' : '—'); }
                                    return $val !== '' ? nl2br(html_escape($val)) : '—';
                                };
                                ?>
                                <?php if (count($general)) { ?>
                                    <div class="eptw-sec"><?= html_escape($type->code); ?>-specific details</div>
                                    <dl class="eptw-dl"><?php foreach ($general as $f) { $val = $p->extra[$f['key']] ?? ''; ?><div style="<?= in_array($f['type'], ['detect', 'textarea', 'checkboxes'], true) ? 'grid-column:1/-1' : ''; ?>"><dt><?= html_escape($f['label']); ?></dt><dd class="<?= $val === '' || $val === [] ? 'empty' : ''; ?>"><?= $fmt($f, $val); ?></dd></div><?php } ?></dl>
                                <?php } ?>
                                <?php $imported = array_filter($p->extra, function ($v, $k) { return strpos($k, 'imported_') === 0 && $v !== ''; }, ARRAY_FILTER_USE_BOTH); if (count($imported)) { ?>
                                    <div class="eptw-sec">From the Excel register</div>
                                    <dl class="eptw-dl"><?php foreach ($imported as $k => $v) { ?><div><dt><?= html_escape(ucfirst(str_replace(['imported_', '_'], ['', ' '], $k))); ?></dt><dd><?= html_escape($v); ?></dd></div><?php } ?></dl>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="eptw-card">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-triangle-exclamation"></i> Hazards</h3></div>
                            <div class="eptw-card-body">
                                <?php $yes = array_keys(array_filter($p->hazards, function ($v) { return $v === 'yes'; })); $yes = array_merge($yes, $p->extra_hazards); ?>
                                <?php if (count($yes)) { ?>
                                    <div class="eptw-doc-req"><?php foreach ($yes as $h) { ?><span class="eptw-badge bad"><i class="fa-solid fa-triangle-exclamation"></i> <?= html_escape($h); ?></span><?php } ?></div>
                                <?php } else { ?><div class="eptw-muted">No hazard ticked.</div><?php } ?>
                                <?php if (count($p->ppe)) { ?>
                                    <div class="eptw-sec">PPE required</div>
                                    <div class="eptw-doc-req"><?php foreach ($p->ppe as $h) { ?><span class="eptw-badge ok"><i class="fa-solid fa-vest"></i> <?= html_escape($h); ?></span><?php } ?></div>
                                <?php } ?>
                            </div>
                        </div>

                        <?php if ($type) { foreach ($type->controls as $section) { ?>
                            <div class="eptw-card">
                                <div class="eptw-card-head"><h3><i class="fa-solid fa-list-check"></i> <?= html_escape($section['title']); ?></h3>
                                    <?php $done = 0; foreach ($section['items'] as $item) { if (($p->controls[$item]['v'] ?? '') === 'yes') { $done++; } } ?>
                                    <div class="eptw-card-actions"><span class="eptw-badge <?= $done === count($section['items']) ? 'ok' : 'warn'; ?>"><?= $done; ?> / <?= count($section['items']); ?> in place</span></div></div>
                                <div class="eptw-card-body">
                                    <div class="eptw-yesno">
                                        <?php foreach ($section['items'] as $item) { $c = $p->controls[$item] ?? ['v' => '', 'r' => '']; ?>
                                            <div class="item"><span><?= html_escape($item); ?></span><span class="v <?= $c['v'] ?: 'blank'; ?>"><?= $c['v'] === 'na' ? 'N/A' : ($c['v'] ? strtoupper($c['v']) : '—'); ?></span><?php if ($c['r'] !== '') { ?><span class="r"><?= html_escape($c['r']); ?></span><?php } ?></div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        <?php } } ?>
                    </div>

                    <div>
                        <div class="eptw-card">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-users"></i> People</h3></div>
                            <div class="eptw-card-body">
                                <dl class="eptw-dl" style="grid-template-columns:1fr">
                                    <div><dt>Initiator / engineer</dt><dd><?= html_escape($p->engineer_name ?: '—'); ?></dd></div>
                                    <div><dt>Area Authority</dt><dd class="<?= $p->area_authority_name ? '' : 'empty'; ?>"><?= html_escape($p->area_authority_name ?: 'not assigned'); ?></dd></div>
                                    <div><dt>HSE Officer</dt><dd class="<?= $p->hse_officer_name ? '' : 'empty'; ?>"><?= html_escape($p->hse_officer_name ?: 'not assigned'); ?></dd></div>
                                    <div><dt>Permit issuer</dt><dd class="<?= $p->coordinator_name ? '' : 'empty'; ?>"><?= html_escape($p->coordinator_name ?: '—'); ?></dd></div>
                                    <div><dt>Permit holder</dt><dd class="<?= $p->permit_holder ? '' : 'empty'; ?>"><?= html_escape($p->permit_holder ?: '—'); ?></dd></div>
                                    <div><dt>Supervisor</dt><dd class="<?= $p->supervisor ? '' : 'empty'; ?>"><?= html_escape($p->supervisor ?: '—'); ?></dd></div>
                                    <div><dt>Contact</dt><dd class="<?= $p->contact_no ? '' : 'empty'; ?>"><?= html_escape($p->contact_no ?: '—'); ?></dd></div>
                                    <?php foreach ($personnel as $f) { $val = $p->extra[$f['key']] ?? ''; ?><div><dt><?= html_escape($f['label']); ?></dt><dd class="<?= $val === '' ? 'empty' : ''; ?>"><?= html_escape($val ?: '—'); ?></dd></div><?php } ?>
                                </dl>
                            </div>
                        </div>

                        <div class="eptw-card">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-lock"></i> Isolation / gas</h3></div>
                            <div class="eptw-card-body">
                                <dl class="eptw-dl" style="grid-template-columns:1fr 1fr">
                                    <div><dt>Isolation applicable</dt><dd><?= ['yes' => '<span class="eptw-badge info">Yes</span>', 'no' => 'No', 'na' => 'N/A'][$p->isolation_applicable] ?? ($p->isolation_required ? '<span class="eptw-badge info">Yes</span>' : '<span class="eptw-badge warn">Not answered</span>'); ?></dd></div>
                                    <div><dt>Linked isolation permit</dt><dd class="<?= (int) $p->isolation_permit_id ? '' : 'empty'; ?>"><?= (int) $p->isolation_permit_id ? '<a href="' . admin_url('eptw/view/' . (int) $p->isolation_permit_id) . '">' . html_escape($p->isolation_permit_no ?: 'draft') . '</a>' : '—'; ?></dd></div>
                                    <div><dt>LOTO applied</dt><dd><?= $p->loto_applied ? '<span class="eptw-badge ok">Yes</span>' : 'No'; ?></dd></div>
                                    <div><dt>Zero energy verified</dt><dd><?= $p->zero_energy_verified ? '<span class="eptw-badge ok">Yes</span>' : 'No'; ?></dd></div>
                                    <div><dt>Gas test required</dt><dd><?= $p->gas_test_required ? '<span class="eptw-badge warn">Yes</span>' : 'No'; ?></dd></div>
                                    <div><dt>Isolation type</dt><dd class="<?= $p->isolation_type ? '' : 'empty'; ?>"><?= html_escape($p->isolation_type ?: '—'); ?></dd></div>
                                    <div><dt>Certificate no.</dt><dd class="<?= $p->isolation_cert_no ? '' : 'empty'; ?>"><?= html_escape($p->isolation_cert_no ?: '—'); ?></dd></div>
                                    <div><dt>Isolation authority</dt><dd class="<?= $p->isolation_authority ? '' : 'empty'; ?>"><?= html_escape($p->isolation_authority ?: '—'); ?></dd></div>
                                    <div><dt>Lock / tag numbers</dt><dd class="<?= $p->lock_tag_numbers ? '' : 'empty'; ?>"><?= html_escape($p->lock_tag_numbers ?: '—'); ?></dd></div>
                                </dl>
                            </div>
                        </div>

                        <div class="eptw-card">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-comment"></i> Remarks</h3>
                                <?php if ($can['remark']) { ?><div class="eptw-card-actions"><button class="eptw-btn eptw-btn-sm" data-eptw-modal="m-remark"><i class="fa fa-plus"></i> Add remark</button></div><?php } ?></div>
                            <div class="eptw-card-body">
                                <?php if ($p->remarks) { ?><p style="white-space:pre-line"><?= html_escape($p->remarks); ?></p><?php } ?>
                                <?php $remarks = array_filter($events, function ($e) { return $e->event === 'remark'; }); ?>
                                <?php if (count($remarks)) { ?>
                                    <ul class="eptw-timeline">
                                        <?php foreach ($remarks as $e) { ?>
                                            <li><div class="ev-dot"><?= html_escape(eptw_initials($e->staff_name)); ?></div><div><div class="ev-meta"><?= html_escape($e->staff_name ?: 'System'); ?> · <?= eptw_time_ago($e->created_at); ?></div><div class="ev-note"><?= html_escape($e->note); ?></div></div></li>
                                        <?php } ?>
                                    </ul>
                                <?php } elseif (!$p->remarks) { ?><div class="eptw-muted eptw-small">No remarks.</div><?php } ?>
                            </div>
                        </div>

                        <?php if (in_array($p->status, ['closed', 'closed_docs_pending', 'archived'], true)) { ?>
                            <div class="eptw-card">
                                <div class="eptw-card-head"><h3><i class="fa-solid fa-circle-check"></i> Closure</h3></div>
                                <div class="eptw-card-body">
                                    <dl class="eptw-dl" style="grid-template-columns:1fr 1fr">
                                        <div><dt>Closed at</dt><dd><?= eptw_dt($p->closed_at); ?></dd></div>
                                        <div><dt>Closed by</dt><dd><?= html_escape(eptw_staff_name($p->closed_by) ?: ($p->closure['closed_by_name'] ?? '—')); ?></dd></div>
                                        <?php foreach ($closing as $k => $l) { $cv = eptw_closure_value($p->closure, $k); $ci = is_array($p->closure[$k] ?? null) ? $p->closure[$k] : []; ?>
                                            <div><dt><?= $l; ?></dt><dd><span class="eptw-badge <?= ['yes' => 'ok', 'no' => 'bad', 'na' => 'muted'][$cv] ?? 'muted'; ?>"><?= ['yes' => 'Yes', 'no' => 'No', 'na' => 'N/A'][$cv] ?? '—'; ?></span>
                                                <?php if (!empty($ci['by'])) { ?><div class="eptw-small eptw-muted"><?= html_escape($ci['by']); ?><?= !empty($ci['at']) ? ' · ' . eptw_dt($ci['at']) : ''; ?></div><?php } ?>
                                                <?php if (!empty($ci['r'])) { ?><div class="eptw-small"><?= html_escape($ci['r']); ?></div><?php } ?></dd></div>
                                        <?php } ?>
                                    </dl>
                                    <?php if (!empty($p->closure['final_remarks'])) { ?><p style="margin:12px 0 0;white-space:pre-line"><?= html_escape($p->closure['final_remarks']); ?></p><?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Approvals -->
            <div class="eptw-pane" id="pane-approvals">
                <div class="eptw-card">
                    <div class="eptw-card-head"><h3><i class="fa-solid fa-pen-nib"></i> Approval matrix</h3>
                        <div class="eptw-card-actions eptw-small eptw-muted">Reviews are signed in the system or recorded from paper by the coordinator.</div></div>
                    <div class="eptw-card-body">
                        <?php if (!count($approvals)) { ?>
                            <div class="eptw-empty" style="padding:22px"><i class="fa-regular fa-file-lines"></i><h4>Not submitted yet</h4><p>The approval steps appear once the permit number is requested. This type needs: <?php foreach (($type ? $type->approvals : []) as $s) { echo '<span class="eptw-badge muted">' . html_escape($this->permits->step_label($s)) . '</span> '; } ?></p></div>
                        <?php } else { ?>
                            <div class="eptw-approvals">
                                <?php foreach ($approvals as $a) { $mine = in_array($a->step, $sign_steps, true); ?>
                                    <div class="eptw-approval <?= $a->decision; ?> <?= $mine ? 'mine' : ''; ?>">
                                        <div class="role"><?= html_escape($this->permits->step_label($a->step)); ?></div>
                                        <div class="who"><?= html_escape($a->name ?: $a->staff_name ?: ($a->decision === 'pending' ? 'Waiting…' : '—')); ?></div>
                                        <div class="when"><?= $a->decided_at ? ucfirst($a->decision) . ' · ' . eptw_dt($a->decided_at) : ($a->step === 'coordinator' ? 'Signs by issuing the number' : 'Pending'); ?></div>
                                        <?php if ($a->signature) { ?><div class="sig"><img src="<?= html_escape($a->signature); ?>" alt="signature"></div><?php } ?>
                                        <?php if ($a->remarks) { ?><div class="remarks"><?= html_escape($a->remarks); ?></div><?php } ?>
                                        <?php if ($mine) { ?><div style="margin-top:10px"><button class="eptw-btn eptw-btn-sm eptw-btn-primary" data-eptw-modal="m-sign-<?= $a->step; ?>"><i class="fa-solid fa-pen-nib"></i> Sign</button></div><?php } ?>
                                        <?php if ($can['paper'] && $a->decision === 'pending' && $a->step !== 'coordinator') { ?><div style="margin-top:10px"><button class="eptw-btn eptw-btn-sm" data-eptw-modal="m-paper" data-fill-step="<?= $a->step; ?>"><i class="fa-solid fa-file-signature"></i> Record paper signature</button></div><?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Gas tests -->
            <div class="eptw-pane" id="pane-gas">
                <div class="eptw-card">
                    <div class="eptw-card-head"><h3><i class="fa-solid fa-vial"></i> Atmospheric testing</h3>
                        <div class="eptw-card-actions"><span class="eptw-small eptw-muted">Limits: O₂ 19.5–23.5 % · LEL &lt; 10 % · H₂S &lt; 10 ppm · CO &lt; 25 ppm</span>
                            <?php if ($can['gas_test']) { ?><button class="eptw-btn eptw-btn-sm eptw-btn-primary" data-eptw-modal="m-gas"><i class="fa fa-plus"></i> Record test</button><?php } ?></div></div>
                    <?php if (!count($gas_tests)) { ?>
                        <div class="eptw-empty" style="padding:26px"><i class="fa-solid fa-vial"></i><h4>No gas test recorded</h4><p><?= $p->gas_test_required ? 'This permit requires gas testing before and during the work.' : 'Gas testing is not flagged as required on this permit.'; ?></p></div>
                    <?php } else { ?>
                        <div class="eptw-table-scroll"><table class="eptw-table">
                            <thead><tr><th>Time</th><th class="eptw-num">O₂ %</th><th class="eptw-num">LEL %</th><th class="eptw-num">H₂S ppm</th><th class="eptw-num">CO ppm</th><th class="eptw-num">SO₂</th><th class="eptw-num">NH₃</th><th>Tester</th><th>Result</th><th>Remarks</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($gas_tests as $g) { $u = $g->result === 'unsafe'; ?>
                                <tr class="eptw-gas-row <?= $u ? 'unsafe' : ''; ?>">
                                    <td class="eptw-small"><?= eptw_dt($g->tested_at); ?></td>
                                    <td class="eptw-num eptw-gas-val <?= $g->o2 !== null && ($g->o2 < 19.5 || $g->o2 > 23.5) ? 'bad' : ''; ?>"><?= $g->o2 ?? '—'; ?></td>
                                    <td class="eptw-num eptw-gas-val <?= $g->lel !== null && $g->lel >= 10 ? 'bad' : ''; ?>"><?= $g->lel ?? '—'; ?></td>
                                    <td class="eptw-num eptw-gas-val <?= $g->h2s !== null && $g->h2s >= 10 ? 'bad' : ''; ?>"><?= $g->h2s ?? '—'; ?></td>
                                    <td class="eptw-num eptw-gas-val <?= $g->co !== null && $g->co >= 25 ? 'bad' : ''; ?>"><?= $g->co ?? '—'; ?></td>
                                    <td class="eptw-num eptw-gas-val"><?= $g->so2 ?? '—'; ?></td>
                                    <td class="eptw-num eptw-gas-val"><?= $g->nh3 ?? '—'; ?></td>
                                    <td class="eptw-small"><?= html_escape($g->tester); ?></td>
                                    <td><span class="eptw-badge <?= $u ? 'bad' : 'ok'; ?>"><?= $u ? 'UNSAFE' : 'Safe'; ?></span></td>
                                    <td class="eptw-small eptw-muted"><?= html_escape($g->remarks); ?></td>
                                    <td class="eptw-actions"><?php if (eptw_can('status')) { ?><button class="eptw-btn eptw-btn-sm eptw-btn-ghost" data-eptw-act="<?= $act('gas_test_delete'); ?>" data-field-gas_test_id="<?= $g->id; ?>" data-confirm="Remove this gas test?"><i class="fa fa-trash"></i></button><?php } ?></td>
                                </tr>
                            <?php } ?>
                            </tbody></table></div>
                    <?php } ?>
                </div>
            </div>

            <!-- Extensions & revalidation -->
            <div class="eptw-pane" id="pane-extensions">
                <div class="eptw-split-even">
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> Extensions</h3>
                            <div class="eptw-card-actions"><span class="eptw-badge muted"><?= (int) $p->extension_count; ?> / <?= (int) eptw_opt('eptw_max_extensions') ?: '∞'; ?></span>
                                <?php if ($can['extend']) { ?><button class="eptw-btn eptw-btn-sm eptw-btn-primary" data-eptw-modal="m-extend"><i class="fa fa-plus"></i> Request extension</button><?php } ?></div></div>
                        <div class="eptw-card-body">
                            <div class="eptw-small eptw-muted" style="margin-bottom:10px">Original end: <b><?= eptw_dt($p->original_end_at); ?></b> · current end: <b><?= eptw_dt($p->end_at); ?></b></div>
                            <?php if (!count($extensions)) { ?><div class="eptw-muted eptw-small">No extension requested.</div><?php } else { ?>
                                <ul class="eptw-timeline">
                                    <?php foreach ($extensions as $x) { ?>
                                        <li>
                                            <div class="ev-dot <?= $x->status === 'approved' ? 'ok' : ($x->status === 'rejected' ? 'bad' : 'warn'); ?>"><i class="fa-solid <?= $x->status === 'approved' ? 'fa-check' : ($x->status === 'rejected' ? 'fa-times' : 'fa-hourglass-half'); ?>"></i></div>
                                            <div>
                                                <div class="ev-title">Until <?= eptw_dt($x->new_end_at); ?> <span class="eptw-badge <?= $x->status === 'approved' ? 'ok' : ($x->status === 'rejected' ? 'bad' : 'warn'); ?>"><?= ucfirst($x->status); ?></span></div>
                                                <div class="ev-meta">Requested by <?= html_escape($x->requested_name); ?> · <?= eptw_time_ago($x->created_at); ?><?= $x->decided_at ? ' · decided by ' . html_escape($x->decided_name) . ' ' . eptw_time_ago($x->decided_at) : ''; ?></div>
                                                <div class="ev-meta">From <?= eptw_dt($x->old_end_at); ?> → <?= eptw_dt($x->new_end_at); ?></div>
                                                <div class="ev-note"><?= html_escape($x->reason); ?><?= $x->decision_note ? "\n— " . html_escape($x->decision_note) : ''; ?></div>
                                                <?php if (!empty($x->checks)) { $xc = $x->checks; ?><div class="ev-meta"><i class="fa-solid fa-list-check"></i> Revalidated by <?= html_escape($xc['by'] ?? ''); ?>: conditions unchanged · controls in place · gas: <?= html_escape(['recorded' => 'recorded test, safe', 'paper' => 'paper sheet' . (!empty($xc['gas_ref']) ? ' ' . $xc['gas_ref'] : ''), 'not_required' => 'not required'][$xc['gas'] ?? ''] ?? '—'); ?></div><?php } ?>
                                                <?php if ($x->status === 'pending' && $can['extend_approve']) { ?>
                                                    <div style="margin-top:8px;display:flex;gap:6px">
                                                        <button class="eptw-btn eptw-btn-sm eptw-btn-ok" data-eptw-modal="m-approve-ext" data-fill-extension_id="<?= $x->id; ?>"><i class="fa fa-check"></i> Revalidate &amp; approve</button>
                                                        <button class="eptw-btn eptw-btn-sm eptw-btn-danger" data-eptw-modal="m-reject-ext" data-fill-extension_id="<?= $x->id; ?>"><i class="fa fa-times"></i> Reject</button>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-arrows-rotate"></i> Shift revalidation</h3>
                            <?php if ($can['revalidate']) { ?><div class="eptw-card-actions"><button class="eptw-btn eptw-btn-sm eptw-btn-primary" data-eptw-modal="m-reval"><i class="fa fa-plus"></i> Revalidate shift</button></div><?php } ?></div>
                        <?php if (!count($revalidations)) { ?><div class="eptw-card-body eptw-muted eptw-small">No shift revalidation recorded.</div><?php } else { ?>
                            <div class="eptw-table-scroll"><table class="eptw-table">
                                <thead><tr><th>Shift</th><th>From</th><th>To</th><th>Area Authority</th><th>Issuer</th><th>HSE</th><th>Conditions</th><th>Gas test</th><th>Notes</th></tr></thead>
                                <tbody><?php foreach ($revalidations as $r) { $g = $r->gas_test !== '' ? $r->gas_test : ($r->gas_test_ok ? 'done' : 'not_required'); ?>
                                    <tr><td><?= html_escape(eptw_shifts()[$r->shift] ?? $r->shift); ?></td><td class="eptw-small"><?= eptw_dt($r->from_at); ?></td><td class="eptw-small"><?= eptw_dt($r->to_at); ?></td><td class="eptw-small"><?= html_escape($r->area_authority); ?></td><td class="eptw-small"><?= html_escape($r->issuer); ?></td><td class="eptw-small"><?= html_escape($r->hse); ?></td>
                                        <td><span class="eptw-badge ok">Unchanged</span></td>
                                        <td><span class="eptw-badge <?= $g === 'not_required' ? 'muted' : 'ok'; ?>"><?= ['done' => 'Repeated', 'paper' => 'Paper sheet', 'not_required' => 'Not required'][$g] ?? $g; ?></span></td><td class="eptw-small eptw-muted"><?= html_escape($r->notes); ?></td></tr>
                                <?php } ?></tbody></table></div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Toolbox talk -->
            <div class="eptw-pane" id="pane-toolbox">
                <div class="eptw-split">
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-people-group"></i> Toolbox talk</h3>
                            <?php if ($can['toolbox'] && $tbt_mode !== 'upload') { ?><div class="eptw-card-actions"><button class="eptw-btn eptw-btn-sm eptw-btn-primary" data-eptw-modal="m-toolbox"><i class="fa fa-pen"></i> <?= count($p->toolbox['attendees'] ?? []) ? 'Update' : 'Record'; ?> digital attendance</button></div><?php } ?></div>
                        <div class="eptw-card-body">
                            <div class="eptw-small eptw-muted" style="margin-bottom:10px"><?= html_escape(eptw_toolbox_modes()[$tbt_mode] ?? ''); ?>.</div>
                            <?php if (!count($p->toolbox) && !count($tbt_docs)) { ?>
                                <div class="eptw-empty" style="padding:22px"><i class="fa-solid fa-people-group"></i><h4>Not recorded</h4><p>Hazards, controls, PPE and the emergency plan explained to the crew<?= $tbt_mode !== 'digital' ? ' — upload the signed TBT sheet, or record attendance digitally' : ', with the attendee list'; ?>.</p></div>
                            <?php } ?>
                            <?php if (count($p->toolbox)) { ?>
                                <dl class="eptw-dl">
                                    <div><dt>Held</dt><dd><?= eptw_dt($p->toolbox['held_at'] ?? ''); ?></dd></div>
                                    <div><dt>By</dt><dd><?= html_escape($p->toolbox['by'] ?? ''); ?></dd></div>
                                    <div><dt>Covered</dt><dd><?php foreach (['hazards' => 'Hazards', 'controls' => 'Controls', 'ppe' => 'PPE', 'emergency' => 'Emergency plan'] as $k => $l) { echo '<span class="eptw-badge ' . (in_array($k, $p->toolbox['topics'] ?? [], true) ? 'ok' : 'muted') . '" style="margin-right:4px">' . $l . '</span>'; } ?></dd></div>
                                </dl>
                                <?php if (count($p->toolbox['attendees'] ?? [])) { ?>
                                    <div class="eptw-table-scroll" style="margin-top:14px"><table class="eptw-table"><thead><tr><th>#</th><th>Worker</th><th>Badge / ID</th><th>Acknowledged</th></tr></thead><tbody>
                                        <?php foreach ($p->toolbox['attendees'] as $i => $w) { ?><tr><td class="eptw-muted"><?= $i + 1; ?></td><td class="eptw-strong"><?= html_escape($w['name']); ?></td><td class="eptw-mono eptw-small"><?= html_escape($w['id']); ?></td><td><?= !empty($w['ack']) ? '<span class="eptw-badge ok"><i class="fa fa-check"></i></span>' : '<span class="eptw-muted">—</span>'; ?></td></tr><?php } ?>
                                    </tbody></table></div>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>
                    <?php if ($tbt_mode !== 'digital') { ?>
                        <div class="eptw-card">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-file-signature"></i> Signed TBT sheet</h3></div>
                            <div class="eptw-card-body">
                                <?php if (count($tbt_docs)) { foreach ($tbt_docs as $d) { ?>
                                    <div class="eptw-small" style="margin-bottom:6px"><i class="fa-solid fa-paperclip"></i> <a href="<?= admin_url('eptw/document/' . $d->id); ?>" target="_blank"><?= html_escape($d->original_name); ?></a> <span class="eptw-muted">· <?= eptw_time_ago($d->created_at); ?></span></div>
                                <?php } } else { ?><div class="eptw-muted eptw-small" style="margin-bottom:10px">No signed sheet uploaded yet.</div><?php } ?>
                                <?php if ($can['upload']) { ?>
                                    <form method="post" action="<?= admin_url('eptw/upload/' . $p->id); ?>" enctype="multipart/form-data" data-eptw-ajax-upload>
                                        <?= $csrf; ?><input type="hidden" name="doc_type" value="toolbox_talk"><input type="hidden" name="note" value="Signed toolbox talk sheet">
                                        <div class="eptw-dropzone"><input type="file" name="files[]" accept=".pdf,.jpg,.jpeg,.png,.heic,.webp">
                                            <button type="button" class="eptw-btn eptw-btn-sm" onclick="this.closest('.eptw-dropzone').querySelector('input[type=file]').click()"><i class="fa-solid fa-folder-open"></i> Choose the scanned sheet</button>
                                            <div class="eptw-small eptw-strong" data-eptw-files style="margin-top:6px"></div></div>
                                        <button type="submit" class="eptw-btn eptw-btn-primary eptw-btn-block" style="margin-top:8px">Upload</button>
                                    </form>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Documents -->
            <div class="eptw-pane" id="pane-documents">
                <div class="eptw-split">
                    <div class="eptw-card">
                        <div class="eptw-card-head"><h3><i class="fa-solid fa-paperclip"></i> Documents</h3>
                            <div class="eptw-card-actions eptw-small">
                                <?php foreach (eptw_required_doc_types() as $rt) { $have = false; foreach ($documents as $d) { if ($d->doc_type === $rt) { $have = true; break; } } ?>
                                    <span class="eptw-badge <?= $have ? 'ok' : 'warn'; ?>" title="Required for closure"><i class="fa-solid <?= $have ? 'fa-check' : 'fa-clock'; ?>"></i> <?= html_escape(eptw_document_types()[$rt]); ?></span>
                                <?php } ?>
                            </div></div>
                        <div class="eptw-card-body">
                            <?php if (!count($documents)) { ?>
                                <div class="eptw-empty" style="padding:22px"><i class="fa-regular fa-folder-open"></i><h4>Nothing attached yet</h4><p>Scanned permit, RA/JSA, method statement, toolbox talk, gas test records and site photos live here. Required closure documents are marked above.</p></div>
                            <?php } else { ?>
                                <div class="eptw-docs">
                                    <?php foreach ($documents as $d) { $ext = strtolower(pathinfo($d->file_name, PATHINFO_EXTENSION)); $ic = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic'], true) ? 'fa-image' : ($ext === 'pdf' ? 'fa-file-pdf' : 'fa-file-lines'); ?>
                                        <div class="eptw-doc">
                                            <div class="ic"><i class="fa-solid <?= $ic; ?>"></i></div>
                                            <div style="min-width:0;flex:1">
                                                <a href="<?= admin_url('eptw/document/' . $d->id); ?>" target="_blank" class="nm"><?= html_escape($d->original_name); ?></a>
                                                <div class="mt"><span class="eptw-badge muted"><?= html_escape(eptw_document_types()[$d->doc_type] ?? $d->doc_type); ?></span> <?= eptw_human_size($d->size); ?> · <?= html_escape($d->uploaded_name); ?> · <?= eptw_time_ago($d->created_at); ?>
                                                    <?php if (eptw_opt('eptw_docs_review') === '1') { ?> <span class="eptw-badge <?= ['verified' => 'ok', 'rejected' => 'bad'][$d->review_status] ?? 'warn'; ?>" title="<?= html_escape($d->review_note); ?>"><?= ['verified' => 'Verified', 'rejected' => 'Rejected'][$d->review_status] ?? 'Awaiting review'; ?></span><?php } ?></div>
                                                <?php if ($d->note) { ?><div class="mt"><?= html_escape($d->note); ?></div><?php } ?>
                                                <div style="margin-top:6px;display:flex;gap:4px">
                                                    <a href="<?= admin_url('eptw/document/' . $d->id . '?dl=1'); ?>" class="eptw-btn eptw-btn-sm eptw-btn-ghost"><i class="fa fa-download"></i></a>
                                                    <?php if ($can['doc_review'] && $d->review_status !== 'verified') { ?><button class="eptw-btn eptw-btn-sm eptw-btn-ok" data-eptw-act="<?= $act('doc_review'); ?>" data-field-document_id="<?= $d->id; ?>" data-field-decision="verified" data-confirm="Mark this document as verified?"><i class="fa fa-check"></i> Verify</button><?php } ?>
                                                    <?php if ($can['doc_review'] && $d->review_status !== 'rejected') { ?><button class="eptw-btn eptw-btn-sm eptw-btn-danger" data-eptw-modal="m-doc-reject" data-fill-document_id="<?= $d->id; ?>"><i class="fa fa-times"></i> Reject</button><?php } ?>
                                                    <?php if ($can['upload'] && (eptw_can('status') || (int) $d->uploaded_by === (int) eptw_me()['staff_id'])) { ?><button class="eptw-btn eptw-btn-sm eptw-btn-ghost" data-eptw-act="<?= $act('document_delete'); ?>" data-field-document_id="<?= $d->id; ?>" data-confirm="Remove this document?"><i class="fa fa-trash"></i></button><?php } ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    <?php if ($can['upload']) { ?>
                        <div class="eptw-card">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-cloud-arrow-up"></i> Upload</h3></div>
                            <div class="eptw-card-body">
                                <form method="post" action="<?= admin_url('eptw/upload/' . $p->id); ?>" enctype="multipart/form-data" data-eptw-ajax-upload>
                                    <?= $csrf; ?>
                                    <div class="eptw-field">
                                        <label class="eptw-label">Document type</label>
                                        <select name="doc_type" class="eptw-select"><?php foreach (eptw_document_types() as $k => $l) { ?><option value="<?= $k; ?>"><?= html_escape($l); ?><?= in_array($k, eptw_required_doc_types(), true) ? ' (required for closure)' : ''; ?></option><?php } ?></select>
                                    </div>
                                    <div class="eptw-field">
                                        <div class="eptw-dropzone">
                                            <input type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.heic,.doc,.docx,.xls,.xlsx,.txt,.csv" <?= $camera === 'disabled' ? '' : ''; ?>>
                                            <i class="fa-solid fa-file-arrow-up" style="font-size:22px"></i>
                                            <div class="eptw-small" style="margin-top:6px">Drop files here, or</div>
                                            <button type="button" class="eptw-btn eptw-btn-sm" onclick="this.closest('.eptw-dropzone').querySelector('input[type=file]').click()"><i class="fa-solid fa-folder-open"></i> Choose from storage</button>
                                            <?php if ($camera === 'allowed') { ?>
                                                <input type="file" name="files[]" accept="image/*" capture="environment" style="display:none" id="eptw-cam-<?= $p->id; ?>">
                                                <button type="button" class="eptw-btn eptw-btn-sm" onclick="document.getElementById('eptw-cam-<?= $p->id; ?>').click()"><i class="fa-solid fa-camera"></i> Take photo</button>
                                            <?php } ?>
                                            <div class="eptw-small eptw-strong" data-eptw-files style="margin-top:6px"></div>
                                        </div>
                                        <div class="eptw-hint">Up to <?= (int) eptw_opt('eptw_max_upload_mb'); ?> MB per file. PDF, images, Office documents.<?= $camera === 'restricted' ? ' Camera capture is restricted on this project.' : ($camera === 'disabled' ? ' Camera is disabled on this project.' : ''); ?></div>
                                    </div>
                                    <div class="eptw-field"><input name="note" class="eptw-input" placeholder="Note (optional)"></div>
                                    <button type="submit" class="eptw-btn eptw-btn-primary eptw-btn-block">Upload</button>
                                </form>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Activity -->
            <div class="eptw-pane" id="pane-activity">
                <div class="eptw-card">
                    <div class="eptw-card-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> Audit trail</h3><div class="eptw-card-actions eptw-small eptw-muted">Every change, who made it, the previous and new value, and when. Entries are never edited or removed.</div></div>
                    <div class="eptw-card-body">
                        <ul class="eptw-timeline">
                            <?php foreach ($events as $e) {
                                $cls = ['issued' => 'ok', 'approved' => 'ok', 'activated' => 'ok', 'auto_activated' => 'ok', 'extended' => 'ok', 'resumed' => 'ok', 'closed' => 'ok', 'simops_cleared' => 'ok', 'simops_ack' => 'ok', 'doc_verified' => 'ok',
                                        'rejected' => 'bad', 'returned' => 'bad', 'suspended' => 'bad', 'simops_hold' => 'bad', 'cancelled' => 'bad', 'expired' => 'bad', 'extension_rejected' => 'bad', 'sod_override' => 'bad', 'simops_flagged' => 'bad', 'doc_rejected' => 'bad',
                                        'hold' => 'warn', 'extension_requested' => 'warn', 'number_requested' => 'info', 'document' => 'info', 'gas_test' => 'info', 'revalidated' => 'info', 'toolbox' => 'info'][$e->event] ?? '';
                                $icon = ['issued' => 'fa-stamp', 'approved' => 'fa-check', 'rejected' => 'fa-times', 'returned' => 'fa-rotate-left', 'activated' => 'fa-play', 'auto_activated' => 'fa-play', 'extended' => 'fa-clock-rotate-left', 'extension_requested' => 'fa-clock-rotate-left', 'extension_rejected' => 'fa-times',
                                         'suspended' => 'fa-pause', 'hold' => 'fa-hand', 'resumed' => 'fa-play', 'closed' => 'fa-circle-check', 'cancelled' => 'fa-ban', 'archived' => 'fa-box-archive', 'document' => 'fa-paperclip', 'document_removed' => 'fa-trash',
                                         'gas_test' => 'fa-vial', 'revalidated' => 'fa-arrows-rotate', 'remark' => 'fa-comment', 'expired' => 'fa-triangle-exclamation', 'created' => 'fa-plus', 'edited' => 'fa-pen', 'number_requested' => 'fa-paper-plane',
                                         'simops_hold' => 'fa-diagram-project', 'simops_cleared' => 'fa-diagram-project', 'imported' => 'fa-file-import', 'toolbox' => 'fa-people-group'][$e->event] ?? 'fa-circle';
                            ?>
                                <li>
                                    <div class="ev-dot <?= $cls; ?>"><i class="fa-solid <?= $icon; ?>"></i></div>
                                    <div>
                                        <div class="ev-title"><?= html_escape($this->permits->event_label($e->event)); ?><?php if ($e->from_status !== $e->to_status && $e->to_status !== '') { ?> <span class="eptw-small eptw-muted">→ <?= html_escape(eptw_status_label($e->to_status)); ?></span><?php } ?></div>
                                        <div class="ev-meta"><?= html_escape($e->staff_name ?: 'System'); ?> · <?= eptw_dt($e->created_at); ?> (<?= eptw_time_ago($e->created_at); ?>)</div>
                                        <?php if ($e->note) { ?><div class="ev-note"><?= html_escape($e->note); ?></div><?php } ?>
                                        <?php if (!empty($e->data['changes'])) { ?>
                                            <table class="eptw-diff"><thead><tr><th>Field</th><th>Previous value</th><th>New value</th></tr></thead><tbody>
                                                <?php foreach ($e->data['changes'] as $ch) { ?><tr><td><?= html_escape($ch['field']); ?></td><td class="from"><?= html_escape($ch['from'] !== '' ? $ch['from'] : '—'); ?></td><td class="to"><?= html_escape($ch['to'] !== '' ? $ch['to'] : '—'); ?></td></tr><?php } ?>
                                            </tbody></table>
                                        <?php } ?>
                                        <?php if (!empty($e->data['new_end_at'])) { ?><div class="ev-meta">End time <?= eptw_dt($e->data['old_end_at'] ?? ''); ?> → <?= eptw_dt($e->data['new_end_at']); ?> · original end <?= eptw_dt($e->data['original_end_at'] ?? ''); ?></div><?php } ?>
                                    </div>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ── Modal templates ──────────────────────────────────────────── -->
            <?php
            $modal = function ($id, $title, $action, $body, $submit = 'Confirm', $submit_class = 'eptw-btn-primary', $wide = false) use ($csrf, $act) {
                echo '<script type="text/template" id="' . $id . '"><div class="eptw-modal' . ($wide ? ' wide' : '') . '"><form data-eptw-ajax action="' . $act($action) . '" method="post">' . $csrf
                    . '<div class="eptw-modal-head"><h3>' . $title . '</h3><button type="button" class="x" data-eptw-close>&times;</button></div>'
                    . '<div class="eptw-modal-body">' . $body . '</div>'
                    . '<div class="eptw-modal-foot"><button type="button" class="eptw-btn" data-eptw-close>Cancel</button><button type="submit" class="eptw-btn ' . $submit_class . '">' . $submit . '</button></div></form></div></script>';
            };
            $sigpad = '<div class="eptw-field"><label class="eptw-label">Signature (optional)</label><div class="eptw-sigpad"><canvas data-eptw-sig-canvas></canvas><button type="button" class="eptw-btn eptw-btn-sm clear" data-eptw-sig-clear>Clear</button></div></div>';
            ?>

            <?php $modal('m-submit', 'Request a permit number', 'submit',
                '<p>The permit goes to the ' . implode(', ', array_map([$this->permits, 'step_label'], array_filter($type ? $type->approvals : [], function ($s) { return $s !== 'coordinator'; }))) . ' for review, and the PTW Coordinator issues the number.</p>'
                . '<div class="eptw-field"><label class="eptw-label">Note to the reviewers</label><textarea name="note" class="eptw-textarea" style="min-height:70px"></textarea></div>', 'Request number'); ?>

            <?php foreach ($sign_steps as $s) {
                $modal('m-sign-' . $s, 'Sign as ' . html_escape($this->permits->step_label($s)), 'decide',
                    '<input type="hidden" name="step" value="' . $s . '">'
                    . '<div class="eptw-field"><label class="eptw-label">Decision</label><div class="eptw-seg"><label class="yes on"><input type="radio" name="decision" value="approved" checked>Approve</label><label class="no"><input type="radio" name="decision" value="rejected">Return for correction</label></div></div>'
                    . '<div class="eptw-field"><label class="eptw-label">Remarks</label><textarea name="remarks" class="eptw-textarea" style="min-height:70px" placeholder="Safety remarks, conditions, or what must be corrected"></textarea></div>'
                    . $sigpad, 'Sign');
            } ?>

            <?php if ($can['paper']) { $modal('m-paper', 'Record a paper signature', 'paper_approval',
                '<p class="eptw-small eptw-muted">For permits approved on paper at site. The coordinator records who signed; the paper copy is attached under Documents.</p>'
                . '<div class="eptw-field"><label class="eptw-label">Step</label><select name="step" class="eptw-select">' . implode('', array_map(function ($a) { return $a->decision === 'pending' && $a->step !== 'coordinator' ? '<option value="' . $a->step . '">' . html_escape($this->permits->step_label($a->step)) . '</option>' : ''; }, $approvals)) . '</select></div>'
                . '<div class="eptw-field"><label class="eptw-label">Signed by (name)</label><input name="name" class="eptw-input" required></div>'
                . '<div class="eptw-field"><label class="eptw-label">Remarks</label><input name="remarks" class="eptw-input"></div>', 'Record'); } ?>

            <?php if ($can['issue']) { $modal('m-issue', 'Issue the permit number', 'issue',
                '<p>The system generates the next number in the configured format' . ($p->area_id ? '' : ' — <b>set the area first</b>') . '. SIMOPS is checked at the same time.</p>'
                . (!$this->permits->reviews_complete($p->id) && count($approvals) ? '<div class="eptw-note warn"><b>Reviews are still pending.</b> To issue anyway, state why (e.g. "all approvals signed on paper, scan attached").</div>' : '')
                . '<div class="eptw-field"><label class="eptw-label">Note' . (!$this->permits->reviews_complete($p->id) && count($approvals) ? ' <span class="req">*</span>' : '') . '</label><textarea name="note" class="eptw-textarea" style="min-height:60px"></textarea></div>'
                . $sigpad, 'Issue number', 'eptw-btn-dark'); } ?>

            <?php $modal('m-return', 'Return for correction', 'return', '<div class="eptw-field"><label class="eptw-label">What must be corrected? <span class="req">*</span></label><textarea name="reason" class="eptw-textarea" required></textarea></div>', 'Return', 'eptw-btn-warn'); ?>

            <?php $modal('m-suspend', 'Suspend the permit', 'suspend',
                '<div class="eptw-field"><label class="eptw-label">Reason <span class="req">*</span></label><select name="reason" class="eptw-select">' . implode('', array_map(function ($r) { return '<option>' . html_escape($r) . '</option>'; }, eptw_suspension_reasons())) . '</select></div>'
                . '<div class="eptw-field"><label class="eptw-label">Details</label><textarea name="note" class="eptw-textarea" style="min-height:70px"></textarea></div>'
                . '<div class="eptw-note bad">Work under this permit must stop immediately. Everyone on the permit is notified.</div>', 'Suspend', 'eptw-btn-danger'); ?>

            <?php $modal('m-hold', 'Put on hold', 'hold', '<div class="eptw-field"><label class="eptw-label">Why?</label><textarea name="note" class="eptw-textarea" style="min-height:70px"></textarea></div>', 'Hold', 'eptw-btn-warn'); ?>

            <?php $modal('m-resume', $p->status === 'on_hold_simops' ? 'Resolve the SIMOPS conflict' : 'Resume the permit', 'resume',
                ($p->status === 'on_hold_simops' ? '<div class="eptw-note bad" style="white-space:pre-line">' . html_escape($p->simops_notes) . '</div><div class="eptw-field"><label class="eptw-label">How was it resolved? (SIMOPS approval) <span class="req">*</span></label><textarea name="note" class="eptw-textarea" required placeholder="e.g. Radiography rescheduled to night shift; exclusion zone agreed with both crews"></textarea></div>'
                    : '<div class="eptw-field"><label class="eptw-label">Note</label><textarea name="note" class="eptw-textarea" style="min-height:70px" placeholder="Conditions verified, gas test repeated…"></textarea></div>'), 'Resume', 'eptw-btn-ok'); ?>

            <?php
            $seg = function ($name, $options, $cur = '') {
                $h = '<div class="eptw-seg">';
                foreach ($options as $v => $l) { $h .= '<label class="' . $v . '"><input type="radio" name="' . $name . '" value="' . $v . '"' . ($cur === $v ? ' checked' : '') . '>' . $l . '</label>'; }
                return $h . '</div>';
            };
            $gas_choice = function ($required) {
                return '<div class="eptw-field"><label class="eptw-label">Gas test' . ($required ? ' <span class="req">*</span>' : '') . '</label>'
                    . '<select name="checks[gas]" class="eptw-select">' . ($required ? '<option value="">— confirm the repeated gas test —</option>' : '<option value="not_required">Not required for this permit</option>')
                    . '<option value="recorded">Repeated and recorded under Gas tests (latest result safe)</option><option value="paper">Repeated on the paper gas-test sheet</option></select></div>'
                    . '<div class="eptw-field"><label class="eptw-label">Gas test sheet reference (if on paper)</label><input name="checks[gas_ref]" class="eptw-input" placeholder="e.g. GT-0412, attached under Documents"></div>';
            };
            $revalidate = '<div class="eptw-sec" style="margin-top:6px">Revalidation of conditions</div>'
                . '<label class="eptw-check"><input type="checkbox" name="checks[conditions_ok]" value="1"> <span>Site conditions re-checked and <b>unchanged</b> since the permit was issued</span></label>'
                . '<label class="eptw-check"><input type="checkbox" name="checks[controls_ok]" value="1"> <span>All control measures (and isolation, where applied) are <b>still in place</b></span></label>'
                . $gas_choice((bool) $p->gas_test_required);
            $ext_default = date('Y-m-d H:i', max(time(), strtotime($p->end_at)) + 4 * 3600);
            if ($can['extend']) {
                $modal('m-extend', eptw_can('extend_approve') ? 'Extend the permit' : 'Request an extension', 'extend',
                    ($p->status === 'expired' ? '<div class="eptw-note bad"><b>This permit has expired.</b> Work stays not authorized until the extension is approved after the conditions are revalidated.</div>' : '')
                    . '<div class="eptw-small eptw-muted" style="margin-bottom:10px">Original end: <b>' . eptw_dt($p->original_end_at) . '</b> · current end: <b>' . eptw_dt($p->end_at) . '</b> · extensions so far: ' . (int) $p->extension_count . ((int) eptw_opt('eptw_max_extensions') ? ' of ' . (int) eptw_opt('eptw_max_extensions') : '') . '</div>'
                    . '<div class="eptw-field"><label class="eptw-label">New end date &amp; time <span class="req">*</span></label><input type="datetime-local" name="new_end_at" class="eptw-input" value="' . $dtl($ext_default) . '" required></div>'
                    . '<div class="eptw-field"><label class="eptw-label">Reason <span class="req">*</span></label><textarea name="reason" class="eptw-textarea" required></textarea></div>'
                    . (eptw_can('extend_approve') && !eptw_sod('own_extension')
                        ? '<label class="eptw-check" style="margin-top:6px"><input type="checkbox" name="checks[approve_now]" value="1" checked data-eptw-toggle="#eptw-ext-checks"> <span><b>Approve now</b> as the permit issuer (conditions revalidated below). Untick to leave it as a request for another issuer.</span></label><div id="eptw-ext-checks">' . $revalidate . '</div>'
                        : '<div class="eptw-hint">The permit issuer revalidates the conditions' . ($p->gas_test_required ? ' (including a repeated gas test)' : '') . ' before approving.</div>'),
                    eptw_can('extend_approve') && !eptw_sod('own_extension') ? 'Extend' : 'Request');
            }
            ?>

            <?php $modal('m-approve-ext', 'Revalidate and approve the extension', 'decide_extension',
                '<input type="hidden" name="extension_id" value=""><input type="hidden" name="decision" value="approved">'
                . '<p class="eptw-small eptw-muted">The new end time takes effect only after these checks. The original end time and this decision stay on record.</p>'
                . $revalidate
                . '<div class="eptw-field"><label class="eptw-label">Note' . (eptw_sod('own_extension') ? ' (required to override segregation of duties)' : '') . '</label><input name="note" class="eptw-input"></div>', 'Approve extension', 'eptw-btn-ok'); ?>

            <?php $modal('m-reject-ext', 'Reject the extension', 'decide_extension', '<input type="hidden" name="extension_id" value=""><input type="hidden" name="decision" value="rejected"><div class="eptw-field"><label class="eptw-label">Why?</label><textarea name="note" class="eptw-textarea"></textarea></div>', 'Reject', 'eptw-btn-danger'); ?>

            <?php
            $iso_on    = $p->isolation_applicable === 'yes';
            $close_rows = '';
            foreach ($closing as $ck => $cl) {
                $default = $ck === 'isolation_removed' ? ($iso_on ? '' : 'na') : '';
                $mand    = $ck === 'work_completed' || ($ck === 'isolation_removed' && $iso_on);
                $opts    = $ck === 'work_completed' ? ['yes' => 'Yes', 'no' => 'No'] : ['yes' => 'Yes', 'no' => 'No', 'na' => 'N/A'];
                $close_rows .= '<div class="eptw-close-row"><span><b>' . $cl . '</b>' . ($mand ? ' <span class="req">*</span>' : '') . ($ck === 'isolation_removed' && $iso_on ? '<div class="eptw-small eptw-muted">Isolation applied on this permit — de-isolation must be confirmed by the isolation authority.</div>' : '') . '</span>'
                    . $seg('closure[' . $ck . ']', $opts, $default)
                    . '<div class="extra"><input name="closure[' . $ck . '_by]" class="eptw-input" list="eptw-people" placeholder="Confirmed by' . (in_array($ck, ['isolation_removed', 'area_restored'], true) ? ' (name)' : '') . '" value="' . ($ck === 'isolation_removed' ? html_escape($p->isolation_authority) : html_escape($me_name)) . '">'
                    . '<input name="closure[' . $ck . '_remark]" class="eptw-input" placeholder="Remark (required when No)"></div></div>';
            }
            $modal('m-close', 'Close the permit', 'close',
                '<p class="eptw-small eptw-muted">Answer each close-out check — Yes, No (with the reason) or N/A — and who confirmed it. Required closure documents: ' . html_escape(implode(', ', array_map(function ($k) { return eptw_document_types()[$k]; }, eptw_required_doc_types()))) . (count($missing_docs) ? ' — <b>still missing: ' . html_escape(implode(', ', $missing_docs)) . '</b> (the permit closes as "Closed – documents pending upload").' : ' — all present.') . '</p>'
                . (count($dependents) ? '<div class="eptw-note bad">This isolation is still used by open work permits — they must be closed first.</div>' : '')
                . $close_rows
                . '<div class="eptw-field" style="margin-top:10px"><label class="eptw-label">Final remarks</label><textarea name="closure[final_remarks]" class="eptw-textarea" style="min-height:60px"></textarea></div>', 'Close permit', 'eptw-btn-dark', true);
            ?>

            <?php $modal('m-cancel', 'Cancel the permit', 'cancel', '<div class="eptw-field"><label class="eptw-label">Reason <span class="req">*</span></label><textarea name="reason" class="eptw-textarea" required></textarea></div>', 'Cancel permit', 'eptw-btn-danger'); ?>

            <?php $modal('m-remark', 'Add a remark', 'remark', '<div class="eptw-field"><textarea name="text" class="eptw-textarea" required placeholder="Safety remark, condition, observation…"></textarea></div>', 'Add'); ?>

            <?php $modal('m-gas', 'Record a gas test', 'gas_test',
                '<div class="eptw-grid-2"><div class="eptw-field"><label class="eptw-label">Time</label><input type="datetime-local" name="tested_at" class="eptw-input" value="' . date('Y-m-d\TH:i') . '"></div>'
                . '<div class="eptw-field"><label class="eptw-label">Tester</label><input name="tester" class="eptw-input" value="' . html_escape(eptw_staff_name(eptw_me()['staff_id'])) . '"></div></div>'
                . '<div class="eptw-grid-3"><div class="eptw-field"><label class="eptw-label">O₂ %</label><input type="number" step="0.1" name="o2" class="eptw-input" placeholder="20.9"></div>'
                . '<div class="eptw-field"><label class="eptw-label">LEL %</label><input type="number" step="0.1" name="lel" class="eptw-input" placeholder="0"></div>'
                . '<div class="eptw-field"><label class="eptw-label">H₂S ppm</label><input type="number" step="0.1" name="h2s" class="eptw-input" placeholder="0"></div>'
                . '<div class="eptw-field"><label class="eptw-label">CO ppm</label><input type="number" step="0.1" name="co" class="eptw-input" placeholder="0"></div>'
                . '<div class="eptw-field"><label class="eptw-label">SO₂</label><input type="number" step="0.1" name="so2" class="eptw-input"></div>'
                . '<div class="eptw-field"><label class="eptw-label">NH₃</label><input type="number" step="0.1" name="nh3" class="eptw-input"></div></div>'
                . '<div class="eptw-field"><label class="eptw-label">Remarks</label><input name="remarks" class="eptw-input"></div>'
                . '<div class="eptw-hint">Safe limits: O₂ 19.5–23.5 % · LEL below 10 % · H₂S below 10 ppm · CO below 25 ppm. Readings outside are flagged UNSAFE and the coordinator, HSE and Area Authority are alerted.</div>', 'Record'); ?>

            <?php $modal('m-reval', 'Revalidate a shift', 'revalidate',
                '<div class="eptw-grid-3"><div class="eptw-field"><label class="eptw-label">Shift</label><select name="shift" class="eptw-select">' . implode('', array_map(function ($k, $l) { return '<option value="' . $k . '">' . $l . '</option>'; }, array_keys(eptw_shifts()), eptw_shifts())) . '</select></div>'
                . '<div class="eptw-field"><label class="eptw-label">From</label><input type="datetime-local" name="from_at" class="eptw-input" value="' . date('Y-m-d\TH:i') . '"></div>'
                . '<div class="eptw-field"><label class="eptw-label">To</label><input type="datetime-local" name="to_at" class="eptw-input" value="' . date('Y-m-d\TH:i', min(strtotime($p->end_at), time() + 12 * 3600)) . '"></div></div>'
                . '<div class="eptw-grid-3"><div class="eptw-field"><label class="eptw-label">Area Authority</label><input name="area_authority" class="eptw-input" list="eptw-people" value="' . html_escape($p->area_authority_name) . '"></div>'
                . '<div class="eptw-field"><label class="eptw-label">Permit issuer</label><input name="issuer" class="eptw-input" list="eptw-people" value="' . html_escape($p->coordinator_name) . '"></div>'
                . '<div class="eptw-field"><label class="eptw-label">HSE</label><input name="hse" class="eptw-input" list="eptw-people" value="' . html_escape($p->hse_officer_name) . '"></div></div>'
                . (eptw_sod('reval_distinct') ? '<div class="eptw-hint" style="margin-top:-6px">This site requires three different people for these roles.</div>' : '')
                . '<div class="eptw-field"><label class="eptw-label">Site conditions unchanged since the last shift? <span class="req">*</span></label>' . $seg('conditions_ok', ['yes' => 'Yes — unchanged', 'no' => 'No — changed']) . '</div>'
                . '<div class="eptw-field"><label class="eptw-label">Gas test for this shift' . ($p->gas_test_required ? ' <span class="req">*</span>' : '') . '</label>'
                . $seg('gas_test', $p->gas_test_required ? ['done' => 'Repeated', 'paper' => 'On paper sheet'] : ['done' => 'Repeated', 'paper' => 'On paper sheet', 'not_required' => 'Not required'], $p->gas_test_required ? '' : 'not_required') . '</div>'
                . '<div class="eptw-field"><label class="eptw-label">Gas test sheet (optional upload)</label><input type="file" name="gas_sheet" class="eptw-input" accept=".pdf,.jpg,.jpeg,.png,.heic,.webp"></div>'
                . '<div class="eptw-field"><label class="eptw-label">Notes</label><input name="notes" class="eptw-input"></div>', 'Revalidate'); ?>

            <?php
            $rows = '';
            $wi = 0;
            $worker_row = function ($i, $w) {
                return '<tr><td><input class="eptw-input" name="worker_name[' . $i . ']" list="eptw-people" data-eptw-person value="' . html_escape($w['name'] ?? '') . '" placeholder="Worker name"></td>'
                    . '<td><input class="eptw-input" name="worker_id[' . $i . ']" value="' . html_escape($w['id'] ?? '') . '" placeholder="ID / badge no."></td>'
                    . '<td style="text-align:center"><input type="checkbox" name="worker_ack[' . $i . ']" value="1"' . (!empty($w['ack']) ? ' checked' : '') . ' title="Briefed and acknowledged"></td></tr>';
            };
            foreach (($p->toolbox['attendees'] ?? []) as $w) { $rows .= $worker_row($wi++, $w); }
            for ($i = $wi; $i < 3; $i++) { $rows .= $worker_row($wi++, []); }
            $topics = $p->toolbox['topics'] ?? ['hazards', 'controls', 'ppe', 'emergency'];
            $modal('m-toolbox', 'Toolbox talk', 'toolbox',
                '<div class="eptw-grid-2"><div class="eptw-field"><label class="eptw-label">Held at</label><input type="datetime-local" name="held_at" class="eptw-input" value="' . $dtl($p->toolbox['held_at'] ?? date('Y-m-d H:i')) . '"></div>'
                . '<div class="eptw-field"><label class="eptw-label">Delivered by</label><input name="held_by" class="eptw-input" value="' . html_escape($p->toolbox['by'] ?? eptw_staff_name(eptw_me()['staff_id'])) . '"></div></div>'
                . '<div class="eptw-field"><label class="eptw-label">Explained</label>'
                . implode('', array_map(function ($k, $l) use ($topics) { return '<label class="eptw-check" style="display:inline-flex;margin-right:14px"><input type="checkbox" name="topics[]" value="' . $k . '" ' . (in_array($k, $topics, true) ? 'checked' : '') . '> <span>' . $l . '</span></label>'; }, ['hazards', 'controls', 'ppe', 'emergency'], ['Hazards', 'Controls', 'PPE', 'Emergency plan'])) . '</div>'
                . '<div class="eptw-table-scroll"><table class="eptw-table"><thead><tr><th>Worker</th><th>Badge / ID</th><th>Ack.</th></tr></thead><tbody id="eptw-workers" data-next="' . $wi . '">' . $rows . '</tbody></table></div>'
                . '<button type="button" class="eptw-btn eptw-btn-sm" style="margin-top:8px" data-eptw-add-worker><i class="fa fa-plus"></i> Add attendee</button>', 'Save', 'eptw-btn-primary', true);
            ?>

            <?php if ($can['activate']) {
                $body = '<p>Confirming the start of work turns this permit into <b>authorization to work</b> until ' . eptw_dt($p->end_at) . '. Do it at site, once the crew is briefed and the controls are in place.</p>';
                if ($p->isolation_applicable === 'yes') {
                    $body .= '<div class="eptw-sec">Isolation / LOTO</div>'
                        . '<label class="eptw-check"><input type="checkbox" name="loto_applied" value="1"' . ($p->loto_applied ? ' checked' : '') . '> <span>LOTO applied' . ($p->isolation_cert_no ? ' (certificate ' . html_escape($p->isolation_cert_no) . ')' : '') . ($p->isolation_permit_no ? ' under ' . html_escape($p->isolation_permit_no) : '') . ' <span class="req">*</span></span></label>'
                        . '<label class="eptw-check"><input type="checkbox" name="zero_energy_verified" value="1"' . ($p->zero_energy_verified ? ' checked' : '') . '> <span>Zero energy verified (try-out / test before touch) <span class="req">*</span></span></label>'
                        . '<div class="eptw-field"><label class="eptw-label">Lock / tag numbers</label><input name="lock_tag_numbers" class="eptw-input" value="' . html_escape($p->lock_tag_numbers) . '"></div>';
                }
                if ($p->gas_test_required) {
                    $body .= '<div class="eptw-sec">Pre-work gas test</div><div class="eptw-field"><select name="gas_check" class="eptw-select"><option value="">— confirm the gas test —</option><option value="recorded">Recorded under Gas tests — latest result safe</option><option value="paper">Done on the paper gas-test sheet — acceptable</option></select></div>';
                }
                $modal('m-activate', 'Confirm start of work', 'activate', $body . '<div class="eptw-field"><label class="eptw-label">Note</label><input name="note" class="eptw-input" placeholder="e.g. TBT done, fire watch in position"></div>', 'Confirm — work authorized', 'eptw-btn-ok');
            } ?>

            <?php if ($can['simops_ack']) { $modal('m-simops-ack', 'Acknowledge the SIMOPS conflict', 'simops_ack',
                '<div class="eptw-note warn" style="white-space:pre-line">' . html_escape((string) $p->simops_notes) . '</div>'
                . '<div class="eptw-field"><label class="eptw-label">How is the simultaneous work controlled? <span class="req">*</span></label><textarea name="note" class="eptw-textarea" required placeholder="e.g. Exclusion zone agreed, work sequenced, joint toolbox talk held"></textarea></div>', 'Acknowledge', 'eptw-btn-warn'); } ?>

            <?php if ($can['doc_review']) { $modal('m-doc-reject', 'Reject the document', 'doc_review', '<input type="hidden" name="document_id" value=""><input type="hidden" name="decision" value="rejected"><div class="eptw-field"><label class="eptw-label">Why? <span class="req">*</span></label><textarea name="note" class="eptw-textarea" required placeholder="e.g. Unsigned, wrong permit, illegible"></textarea></div>', 'Reject', 'eptw-btn-danger'); } ?>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
