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
                                    <button type="submit" class="eptw-btn eptw-btn-primary" style="margin-top:12px"><i class="fa-solid fa-magnifying-glass"></i> Read the file</button>
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
                                    <li>Permit IDs are kept as the permit number; rows whose number already exists are skipped.</li>
                                    <li>Status words like Open / Active / Closed / Suspended are mapped; unknown ones become Closed for numbered permits.</li>
                                    <li>Imported permits are marked <b>source: import</b> and get an audit entry.</li>
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
                        <form method="post" action="<?= admin_url('eptw/eptw_setup/import_commit'); ?>" style="margin-top:16px" class="eptw-inline-form">
                            <?= $csrf; ?>
                            <input type="hidden" name="token" value="<?= html_escape($preview['token']); ?>">
                            <div class="eptw-field"><label class="eptw-label">Project for rows without one</label><select name="default_project" class="eptw-select"><?php foreach (eptw_projects(false) as $pr) { ?><option value="<?= $pr->id; ?>"><?= html_escape($pr->name); ?></option><?php } ?></select></div>
                            <a href="<?= admin_url('eptw/eptw_setup/import'); ?>" class="eptw-btn">Cancel</a>
                            <button type="submit" class="eptw-btn eptw-btn-primary" onclick="return confirm('Import <?= (int) $preview['count']; ?> rows into the register?')"><i class="fa-solid fa-file-import"></i> Import <?= (int) $preview['count']; ?> permits</button>
                        </form>
                    </div>
                </div>
            <?php } ?>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
