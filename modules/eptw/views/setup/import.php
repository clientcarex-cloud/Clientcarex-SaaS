<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $csrf = form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
<div id="wrapper">
    <div class="content">
        <div class="eptw-wrap" data-admin-url="<?= admin_url(); ?>">

            <?php $active = 'setup'; include __DIR__ . '/../_nav.php'; ?>
            <?php $setup_active = 'import'; include __DIR__ . '/_setup_nav.php'; ?>

            <?php if (!$preview) { ?>
                <div class="eptw-split">
                    <div>
                        <div class="eptw-card">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-file-import"></i> Import the Excel register</h3></div>
                            <div class="eptw-card-body">
                                <p>Upload the register you keep today (<b>.xlsx</b> or <b>.csv</b>). ePTW recognises the standard columns — Permit ID, Permit Type, Work Order, Project/Package, Company, Subcontractor, Work Description, Location, Equipment Tag, Start/End Date, Shift, Initiator, Area Authority, Permit Issuer, Holder, HSE, RA/JSA, Hazards, Controls, PPE, Isolation, LOTO, Gas test, Weather, SIMOPS, Remarks, Status, Extension count, Closure — and shows you what it found before anything is written.</p>
                                <form method="post" action="<?= admin_url('eptw/eptw_setup/import'); ?>" enctype="multipart/form-data">
                                    <?= $csrf; ?>
                                    <div class="eptw-dropzone">
                                        <input type="file" name="register" accept=".xlsx,.csv,.txt,.tsv" required>
                                        <i class="fa-solid fa-file-excel" style="font-size:26px"></i>
                                        <div class="eptw-small" style="margin-top:6px">Drop the register here, or</div>
                                        <button type="button" class="eptw-btn eptw-btn-sm" onclick="this.closest('.eptw-dropzone').querySelector('input[type=file]').click()"><i class="fa-solid fa-folder-open"></i> Choose file</button>
                                        <div class="eptw-small eptw-strong" data-eptw-files style="margin-top:6px"></div>
                                    </div>
                                    <div class="eptw-field" style="margin-top:12px"><label class="eptw-label">Project for rows without one</label><select name="default_project" class="eptw-select" style="max-width:320px"><?php foreach (eptw_projects(false) as $pr) { ?><option value="<?= $pr->id; ?>"><?= html_escape($pr->name); ?></option><?php } ?></select></div>
                                    <?php if (eptw_perm('eptw_setup_import', 'create')) { ?><button type="submit" class="eptw-btn eptw-btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Check the file (dry run — nothing is written)</button><?php } ?>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="eptw-card">
                            <div class="eptw-card-head"><h3><i class="fa-solid fa-circle-info"></i> What happens</h3></div>
                            <div class="eptw-card-body eptw-small">
                                <ul style="padding-left:18px;line-height:1.7;margin:0">
                                    <li>Projects, areas and contractors named in the sheet are created if missing.</li>
                                    <li>Permit types are matched by name ("Hydrotest" → Hydrostatic Testing, "Confined Space Entry" → CSE…).</li>
                                    <li>A <b>dry run</b> checks every row first: required fields, dates, permit type, project — with a message per row. Nothing is written until you confirm.</li>
                                    <li>Permit IDs are kept as the permit number; numbers already in the register, or repeated in the file, are skipped as duplicates.</li>
                                    <li>Status words like Open / Active / Closed / Suspended are mapped; unknown ones are flagged and become Closed for numbered permits.</li>
                                    <li>Each import is logged as a batch and every permit gets an audit entry. A batch can be <b>reversed</b>: permits nobody has changed since are removed.</li>
                                    <li>Only staff with the "Import Excel register — Run import" permission can import.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } else { ?>
                <div class="eptw-card">
                    <div class="eptw-card-head"><h3><i class="fa-solid fa-list-check"></i> Recognised <?= count($preview['map']); ?> of <?= count($preview['headers']); ?> columns · <?= (int) $preview['count']; ?> rows</h3></div>
                    <div class="eptw-card-body">
                        <div class="eptw-doc-req" style="margin-bottom:12px">
                            <?php foreach ($preview['headers'] as $i => $h) { $t = $preview['map'][$i] ?? ''; ?>
                                <span class="eptw-chip <?= $t ? 'ok' : ''; ?>" title="<?= $t ? 'Imported as ' . $t : 'Kept in remarks / ignored'; ?>"><i class="fa-solid <?= $t ? 'fa-check' : 'fa-minus'; ?>"></i> <?= html_escape($h); ?><?= $t ? ' → <b>' . html_escape(str_replace('_', ' ', $t)) . '</b>' : ''; ?></span>
                            <?php } ?>
                        </div>
                        <?php if (!isset(array_flip($preview['map'])['type'])) { ?><div class="eptw-note bad">No <b>Permit Type</b> column was recognised — every row would be rejected. Check the header row.</div><?php } ?>
                        <div class="eptw-table-scroll" style="max-height:320px;overflow:auto"><table class="eptw-table eptw-small">
                            <thead><tr><?php foreach ($preview['headers'] as $h) { ?><th><?= html_escape($h); ?></th><?php } ?></tr></thead>
                            <tbody><?php foreach ($preview['rows'] as $row) { ?><tr><?php foreach ($preview['headers'] as $i => $h) { ?><td style="white-space:nowrap;max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= html_escape((string) ($row[$i] ?? '')); ?></td><?php } ?></tr><?php } ?></tbody>
                        </table></div>
                        <?php $dry = $preview['dry']; if ($dry) { ?>
                            <div class="eptw-tiles" style="margin-top:16px">
                                <div class="eptw-tile <?= $dry['created'] ? 'live' : ''; ?>"><div class="eptw-tile-value"><?= (int) $dry['created']; ?></div><div class="eptw-tile-label">Will be imported</div><div class="eptw-tile-def"><?= (int) $dry['warnings']; ?> with warnings</div></div>
                                <div class="eptw-tile <?= $dry['skipped'] ? 'warn' : ''; ?>"><div class="eptw-tile-value"><?= (int) $dry['skipped']; ?></div><div class="eptw-tile-label">Duplicates skipped</div></div>
                                <div class="eptw-tile <?= $dry['failed'] ? 'bad' : ''; ?>"><div class="eptw-tile-value"><?= (int) $dry['failed']; ?></div><div class="eptw-tile-label">Rows rejected</div></div>
                            </div>
                            <?php $issues = array_values(array_filter($dry['rows'], function ($r) { return $r['result'] !== 'ok'; })); if (count($issues)) { ?>
                                <div class="eptw-table-scroll" style="max-height:340px;overflow:auto"><table class="eptw-table eptw-small">
                                    <thead><tr><th>Row</th><th>Permit no.</th><th>Result</th><th>Messages</th></tr></thead>
                                    <tbody><?php foreach (array_slice($issues, 0, 300) as $r) { ?>
                                        <tr><td class="eptw-num"><?= (int) $r['row']; ?></td><td class="eptw-mono"><?= html_escape($r['permit_no']); ?></td>
                                            <td><span class="eptw-badge <?= ['error' => 'bad', 'duplicate' => 'warn', 'warning' => 'info'][$r['result']] ?? 'muted'; ?>"><?= ucfirst($r['result']); ?></span></td>
                                            <td><?= html_escape(implode(' · ', $r['messages'])); ?></td></tr>
                                    <?php } ?></tbody></table></div>
                                <?php if (count($issues) > 300) { ?><div class="eptw-hint">Showing the first 300 of <?= count($issues); ?> rows with messages.</div><?php } ?>
                            <?php } else { ?><div class="eptw-note" style="margin-top:10px"><i class="fa-solid fa-circle-check"></i> Every row passed the checks.</div><?php } ?>
                        <?php } ?>
                        <form method="post" action="<?= admin_url('eptw/eptw_setup/import_commit'); ?>" style="margin-top:16px" class="eptw-inline-form">
                            <?= $csrf; ?>
                            <input type="hidden" name="token" value="<?= html_escape($preview['token']); ?>">
                            <input type="hidden" name="default_project" value="<?= (int) $preview['default_project']; ?>">
                            <span class="eptw-small eptw-muted">Rows without a project go to <b><?php foreach (eptw_projects(false) as $pr) { if ((int) $pr->id === (int) $preview['default_project']) { echo html_escape($pr->name); } } ?></b>.</span>
                            <a href="<?= admin_url('eptw/eptw_setup/import'); ?>" class="eptw-btn">Cancel</a>
                            <?php if (eptw_perm('eptw_setup_import', 'create') && $dry && $dry['created']) { ?><button type="submit" class="eptw-btn eptw-btn-primary" onclick="return confirm('Import <?= (int) $dry['created']; ?> permit(s) as one batch? Rejected and duplicate rows are left out.')"><i class="fa-solid fa-file-import"></i> Import <?= (int) $dry['created']; ?> permit(s)</button><?php } ?>
                        </form>
                    </div>
                </div>
            <?php } ?>

            <?php if (count($imports)) { $focus = (int) $this->input->get('batch'); ?>
                <div class="eptw-card" id="import-log">
                    <div class="eptw-card-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> Import log</h3></div>
                    <div class="eptw-table-scroll"><table class="eptw-table eptw-small">
                        <thead><tr><th>#</th><th>File</th><th>When / who</th><th class="eptw-num">Rows</th><th class="eptw-num">Imported</th><th class="eptw-num">Skipped</th><th class="eptw-num">Rejected</th><th>Messages</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($imports as $im) { $rep = json_decode((string) $im->report, true) ?: []; ?>
                            <tr style="<?= $focus === (int) $im->id ? 'background:#f0fdfa' : ''; ?>">
                                <td class="eptw-mono">#<?= (int) $im->id; ?></td>
                                <td><?= html_escape($im->file_name); ?></td>
                                <td><?= eptw_dt($im->created_at); ?><div class="eptw-muted"><?= html_escape($im->staff_name); ?></div></td>
                                <td class="eptw-num"><?= (int) $im->total_rows; ?></td>
                                <td class="eptw-num"><?= (int) $im->created; ?><?= $im->rolled_back_at ? '' : '<div class="eptw-muted">' . (int) $im->live_rows . ' in register</div>'; ?></td>
                                <td class="eptw-num"><?= (int) $im->skipped; ?></td>
                                <td class="eptw-num"><?= (int) $im->failed; ?></td>
                                <td><?php if (count($rep)) { ?><details><summary><?= count($rep); ?> row message(s)</summary><ul style="margin:6px 0 0;padding-left:16px"><?php foreach (array_slice($rep, 0, 100) as $r) { ?><li>Row <?= (int) $r['row']; ?><?= $r['permit_no'] !== '' ? ' (' . html_escape($r['permit_no']) . ')' : ''; ?> — <?= html_escape(ucfirst($r['result']) . ': ' . implode(' · ', $r['messages'])); ?></li><?php } ?></ul></details><?php } else { ?><span class="eptw-muted">—</span><?php } ?></td>
                                <td class="eptw-actions">
                                    <?php if ($im->rolled_back_at) { ?><span class="eptw-badge muted">Reversed <?= eptw_dt($im->rolled_back_at, false); ?></span>
                                    <?php } elseif ((int) $im->live_rows && eptw_perm('eptw_setup_import', 'create')) { ?><form method="post" action="<?= admin_url('eptw/eptw_setup/import_rollback/' . $im->id); ?>" style="display:inline" onsubmit="return confirm('Reverse import #<?= (int) $im->id; ?>? Its permits are removed unless they were changed after the import.')"><?= $csrf; ?><button class="eptw-btn eptw-btn-sm eptw-btn-danger"><i class="fa-solid fa-rotate-left"></i> Reverse</button></form><?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody></table></div>
                </div>
            <?php } ?>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
