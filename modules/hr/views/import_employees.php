<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
/* Import preview grid. Scoped to .hrimp so nothing here leaks into the rest
   of the admin theme. */
.hrimp-head-actions .btn { margin-left: 5px; }
.hrimp-drop { border: 2px dashed #d5dde5; border-radius: 6px; padding: 18px; text-align: center; background: #fbfcfd; }
.hrimp-drop.dragover { border-color: #4c8bf5; background: #f0f6ff; }
.hrimp-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 10px; }
.hrimp-toolbar > * { margin-right: 6px; }
.hrimp-chip { display: inline-block; padding: 4px 10px; border-radius: 14px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid transparent; user-select: none; }
.hrimp-chip.active { box-shadow: 0 0 0 2px rgba(0, 0, 0, .12) inset; }
.hrimp-chip-all { background: #eef1f4; color: #4a5568; }
.hrimp-chip-created { background: #e6f7ec; color: #1e7e46; }
.hrimp-chip-updated { background: #e6f1fb; color: #1c5f9e; }
.hrimp-chip-skipped { background: #fdeaea; color: #b02a2a; }
.hrimp-chip-issues  { background: #fdf3e2; color: #96620d; }
.hrimp-status { font-size: 11px; color: #7a8698; min-width: 120px; }
.hrimp-status.busy { color: #b8860b; }
.hrimp-scroll { overflow: auto; max-height: 62vh; border: 1px solid #e4e8ee; border-radius: 4px; background: #fff; }
.hrimp-table { border-collapse: separate; border-spacing: 0; font-size: 12px; width: auto; min-width: 100%; }
.hrimp-table th, .hrimp-table td { border-bottom: 1px solid #edf0f4; border-right: 1px solid #edf0f4; padding: 3px 5px; white-space: nowrap; vertical-align: middle; background: #fff; }
.hrimp-table thead th { position: sticky; top: 0; z-index: 3; background: #f6f8fa; font-weight: 600; color: #4a5568; padding: 7px 6px; border-bottom: 2px solid #dfe4ea; }
.hrimp-table thead th small { display: block; font-weight: 400; color: #98a2b3; font-size: 10px; }
/* The pinned columns are positioned with fixed `left` offsets, so their
   widths must be pinned too — table auto-layout would otherwise shrink them
   and the last pinned column would overlap the first scrolling one. */
.hrimp-pin { position: sticky; z-index: 2; }
.hrimp-table thead th.hrimp-pin { z-index: 4; }
.hrimp-pin-1 { left: 0; width: 34px; min-width: 34px; max-width: 34px; text-align: center; }
.hrimp-pin-2 { left: 34px; width: 46px; min-width: 46px; max-width: 46px; text-align: center; color: #98a2b3; }
.hrimp-pin-3 { left: 80px; width: 88px; min-width: 88px; max-width: 88px; }
.hrimp-pin-4 { left: 168px; width: 160px; min-width: 160px; max-width: 160px; overflow: hidden; text-overflow: ellipsis; box-shadow: 2px 0 4px -2px rgba(0, 0, 0, .15); }
.hrimp-table td.hrimp-pin, .hrimp-table th.hrimp-pin { background: #fcfdfe; }
.hrimp-table tr.row-created td.hrimp-pin { background: #f3fbf6; }
.hrimp-table tr.row-updated td.hrimp-pin { background: #f2f8fd; }
.hrimp-table tr.row-skipped td.hrimp-pin { background: #fdf4f4; }
.hrimp-table tr.row-off { opacity: .45; }
.hrimp-table input.hrimp-in, .hrimp-table select.hrimp-in { width: 100%; border: 1px solid #dde3ea; border-radius: 3px; padding: 3px 5px; font-size: 12px; height: 26px; background: #fff; color: #2d3748; }
.hrimp-table input.hrimp-in:focus, .hrimp-table select.hrimp-in:focus { outline: none; border-color: #4c8bf5; box-shadow: 0 0 0 2px rgba(76, 139, 245, .15); }
.hrimp-table td.cell-error input.hrimp-in, .hrimp-table td.cell-error select.hrimp-in { border-color: #e74c3c; background: #fff6f6; }
.hrimp-table td.cell-warning input.hrimp-in, .hrimp-table td.cell-warning select.hrimp-in { border-color: #e6a23c; background: #fffaf2; }
.hrimp-table td.cell-info input.hrimp-in, .hrimp-table td.cell-info select.hrimp-in { border-color: #67b6e8; background: #f5fbff; }
.hrimp-label { display: inline-block; padding: 2px 7px; border-radius: 3px; font-size: 11px; font-weight: 600; }
.hrimp-label-created { background: #1e7e46; color: #fff; }
.hrimp-label-updated { background: #1c5f9e; color: #fff; }
.hrimp-label-skipped { background: #b02a2a; color: #fff; }
.hrimp-label-empty   { background: #b9c2cd; color: #fff; }
.hrimp-notes { white-space: normal !important; min-width: 280px; max-width: 380px; font-size: 11px; line-height: 1.45; }
/* A chatty row must not stretch the whole grid — scroll the notes instead. */
.hrimp-note-wrap { max-height: 52px; overflow-y: auto; }
.hrimp-note-error { color: #b02a2a; }
.hrimp-note-warning { color: #96620d; }
.hrimp-note-info { color: #1c5f9e; }
.hrimp-del { color: #b9c2cd; cursor: pointer; font-size: 14px; line-height: 1; }
.hrimp-del:hover { color: #b02a2a; }
.hrimp-pager { display: flex; align-items: center; gap: 8px; margin-top: 8px; font-size: 12px; color: #64748b; }
.hrimp-pager .btn { margin-right: 4px; }
.hrimp-bulk { background: #f8fafc; border: 1px solid #e4e8ee; border-radius: 4px; padding: 8px 10px; margin-bottom: 10px; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.hrimp-bulk label { margin: 0 4px 0 0; font-size: 12px; font-weight: 600; color: #4a5568; }
.hrimp-bulk select, .hrimp-bulk input { height: 30px; font-size: 12px; }
.hrimp-apply-bar { margin-top: 12px; padding-top: 12px; border-top: 1px solid #e4e8ee; display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
/* After the import has run the grid becomes a read-only record of it. */
.hrimp-locked .hrimp-scroll { opacity: .7; pointer-events: none; }
.hrimp-locked .hrimp-bulk { opacity: .6; }
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-7">
                                <h4 class="no-margin"><i class="fa fa-upload"></i> Import Employees</h4>
                                <p class="text-muted">Upload a sheet, review <strong>every column</strong> in the editable preview, fix anything that needs fixing &mdash; then import.</p>
                            </div>
                            <div class="col-md-5 text-right hrimp-head-actions">
                                <a href="<?php echo admin_url('hr/export_employees'); ?>" class="btn btn-default btn-sm"><i class="fa fa-download"></i> Export employees</a>
                                <a href="<?php echo admin_url('hr/import_employees_sample'); ?>" class="btn btn-default btn-sm"><i class="fa fa-file-o"></i> Sample file</a>
                                <a href="<?php echo admin_url('hr/employees'); ?>" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back</a>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />

                        <?php if (empty($rows)) { ?>
                            <div class="row">
                                <div class="col-md-5">
                                    <?php echo form_open_multipart(admin_url('hr/import_employees'), ['id' => 'hr_import_form']); ?>
                                    <div class="form-group">
                                        <label class="control-label">Excel / CSV file <small class="req text-danger">*</small></label>
                                        <div class="hrimp-drop" id="hrimp_drop">
                                            <i class="fa fa-file-excel-o fa-2x text-muted"></i>
                                            <p style="margin:8px 0 4px;">Drop your file here, or choose it below</p>
                                            <input type="file" name="import_file" id="hrimp_file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                                        </div>
                                        <p class="help-block">Excel workbook (<strong>.xlsx</strong>) or <strong>.csv</strong> &mdash; both work. Up to 2000 rows per file, max <?php echo html_escape(ini_get('upload_max_filesize')); ?> per upload.</p>
                                    </div>
                                    <?php // Filled in by JS: a base64 copy of the chosen file, so the
                                          // import still works on hosts where the multipart file part
                                          // is stripped before PHP sees it. ?>
                                    <input type="hidden" name="import_file_b64" id="hrimp_file_b64" value="">
                                    <input type="hidden" name="import_file_name" id="hrimp_file_name" value="">
                                    <button type="submit" class="btn btn-primary" id="hrimp_upload_btn"><i class="fa fa-eye"></i> Upload &amp; Preview</button>
                                    <p class="text-muted" style="font-size:12px;margin-top:8px;">Nothing is saved on upload. You get an editable preview first.</p>
                                    <?php echo form_close(); ?>
                                </div>
                                <div class="col-md-7">
                                    <div class="alert alert-info" style="margin-bottom:10px;">
                                        <strong>How it works</strong>
                                        <ul style="margin:8px 0 0; padding-left:18px;">
                                            <li><strong>To modify employees:</strong> export, edit the sheet, import it back. Rows are matched by <code>staff_id</code>, then <code>email</code>, then <code>employee_code</code> &mdash; matched rows are <strong>updated</strong>.</li>
                                            <li><strong>To add employees:</strong> append rows with a blank <code>staff_id</code>. Each new row needs at least <code>first_name</code> and a unique <code>email</code> &mdash; they are <strong>created</strong> as staff + HR profile (no welcome email; they can use &ldquo;Forgot password&rdquo; to log in).</li>
                                            <li><strong>Smart fill:</strong> a blank <code>last_name</code> is auto-split from a full name in <code>first_name</code> (&ldquo;Asha Nair&rdquo; &rarr; Asha + Nair); a blank <code>email</code> is auto-generated from the mobile number.</li>
                                            <li><strong>Blank cells are left unchanged</strong> on updates, so partial sheets never wipe data.</li>
                                            <li><code>department</code> / <code>designation</code> are matched by name and <strong>auto-created</strong> when new; <code>reporting_to_email</code> must be an existing staff email.</li>
                                            <li>Dates use <code>YYYY-MM-DD</code>. <code>basic_salary</code> applies only if you hold payroll edit permission.</li>
                                        </ul>
                                    </div>
                                    <p class="text-muted" style="font-size:12px;">Columns recognised: <?php echo implode(', ', array_map('html_escape', $columns)); ?>. Extra columns are ignored; column order does not matter.</p>
                                </div>
                            </div>
                        <?php } else { ?>
                            <div class="row" style="margin-bottom:10px;">
                                <div class="col-md-8">
                                    <p style="margin:0;">
                                        <i class="fa fa-check-circle text-success"></i>
                                        Read <strong><?php echo count($rows); ?></strong> row(s)<?php echo $filename !== '' ? ' from <strong>' . html_escape($filename) . '</strong>' : ''; ?>.
                                        <span class="text-muted">Nothing has been saved yet &mdash; edit anything below, then press Import.</span>
                                    </p>
                                </div>
                                <div class="col-md-4 text-right">
                                    <a href="<?php echo admin_url('hr/import_employees'); ?>" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Upload a different file</a>
                                </div>
                            </div>

                            <div class="hrimp" id="hrimp">
                                <div id="hrimp_result"></div>

                                <div class="hrimp-toolbar">
                                    <span class="hrimp-chip hrimp-chip-all active" data-filter="all">All <span data-count="all">0</span></span>
                                    <span class="hrimp-chip hrimp-chip-created" data-filter="created">Will create <span data-count="created">0</span></span>
                                    <span class="hrimp-chip hrimp-chip-updated" data-filter="updated">Will update <span data-count="updated">0</span></span>
                                    <span class="hrimp-chip hrimp-chip-skipped" data-filter="skipped">Will skip <span data-count="skipped">0</span></span>
                                    <span class="hrimp-chip hrimp-chip-issues" data-filter="issues">Needs attention <span data-count="issues">0</span></span>

                                    <input type="text" id="hrimp_search" class="form-control input-sm" placeholder="Search any cell…" style="width:190px;height:30px;">
                                    <select id="hrimp_colmode" class="form-control input-sm" style="width:165px;height:30px;">
                                        <option value="all">All columns</option>
                                        <option value="essential">Key columns only</option>
                                        <option value="issues">Only columns with issues</option>
                                    </select>
                                    <select id="hrimp_perpage" class="form-control input-sm" style="width:110px;height:30px;">
                                        <option value="25">25 / page</option>
                                        <option value="50" selected>50 / page</option>
                                        <option value="100">100 / page</option>
                                        <option value="0">Show all</option>
                                    </select>
                                    <span class="hrimp-status" id="hrimp_state">Validated</span>
                                </div>

                                <div class="hrimp-bulk">
                                    <label>Bulk fill</label>
                                    <select id="hrimp_bulk_col" class="form-control input-sm" style="width:190px;"></select>
                                    <span id="hrimp_bulk_value_wrap" style="width:200px;"></span>
                                    <button type="button" class="btn btn-default btn-sm" id="hrimp_bulk_apply" title="Sets this column on every row that matches the current filter and search — across all pages">Apply to matching rows</button>
                                    <button type="button" class="btn btn-default btn-sm" id="hrimp_bulk_blank" title="Same, but only fills the rows where this column is empty">…only where blank</button>
                                    <span style="flex:1"></span>
                                    <button type="button" class="btn btn-default btn-sm" id="hrimp_untick_bad"><i class="fa fa-ban"></i> Untick rows with errors</button>
                                    <button type="button" class="btn btn-default btn-sm" id="hrimp_tick_all"><i class="fa fa-check-square-o"></i> Tick all</button>
                                    <button type="button" class="btn btn-default btn-sm" id="hrimp_add_row"><i class="fa fa-plus"></i> Add row</button>
                                </div>

                                <div class="hrimp-scroll">
                                    <table class="hrimp-table">
                                        <thead id="hrimp_head"></thead>
                                        <tbody id="hrimp_body"></tbody>
                                    </table>
                                </div>

                                <div class="hrimp-pager">
                                    <button type="button" class="btn btn-default btn-sm" id="hrimp_prev">&laquo; Prev</button>
                                    <button type="button" class="btn btn-default btn-sm" id="hrimp_next">Next &raquo;</button>
                                    <span id="hrimp_pageinfo"></span>
                                </div>

                                <div class="hrimp-apply-bar">
                                    <button type="button" class="btn btn-info" id="hrimp_revalidate"><i class="fa fa-refresh"></i> Re-check now</button>
                                    <button type="button" class="btn btn-primary" id="hrimp_apply"><i class="fa fa-save"></i> Import ticked rows</button>
                                    <span id="hrimp_apply_hint" class="text-muted" style="font-size:12px;"></span>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function () {
    // Upload box niceties (present only on the upload step).
    var drop = document.getElementById('hrimp_drop'),
        file = document.getElementById('hrimp_file');
    if (drop && file) {
        ['dragenter', 'dragover'].forEach(function (e) {
            drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.add('dragover'); });
        });
        ['dragleave', 'drop'].forEach(function (e) {
            drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.remove('dragover'); });
        });
        drop.addEventListener('drop', function (ev) {
            if (ev.dataTransfer && ev.dataTransfer.files.length) { file.files = ev.dataTransfer.files; }
        });
        var form = document.getElementById('hr_import_form');
        if (form) {
            var busy = function () {
                var b = document.getElementById('hrimp_upload_btn');
                if (b) { b.disabled = true; b.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Reading the file…'; }
            };

            // The file also travels as base64 in a plain form field. Some hosts
            // strip the multipart file part before PHP sees it ($_POST arrives
            // full, $_FILES empty), and the server falls back to this copy. The
            // read is async, so hold the submit until it finishes.
            var sending = false;
            form.addEventListener('submit', function (ev) {
                if (sending || !file.files || !file.files.length || !window.FileReader) { busy(); return; }
                ev.preventDefault();
                busy();

                var reader = new FileReader();
                reader.onload = function () {
                    var s = String(reader.result || ''), c = s.indexOf(',');
                    document.getElementById('hrimp_file_b64').value  = c > -1 ? s.slice(c + 1) : '';
                    document.getElementById('hrimp_file_name').value = file.files[0].name;
                    sending = true;
                    form.submit();
                };
                // Reading failed — submit anyway and let the normal upload try.
                reader.onerror = function () { sending = true; form.submit(); };
                reader.readAsDataURL(file.files[0]);
            });
        }
    }
})();
</script>
<?php if (!empty($rows)) { ?>
<script>
/* The editable import grid.
   Rows live in a JS model (not the DOM) so filtering, paging and searching
   never lose an edit. Every change is re-validated by the SAME server code
   that performs the import, so the verdict shown here is exactly what the
   import will do. */
var HRIMP = {
    columns:   <?php echo json_encode($columns, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    meta:      <?php echo json_encode($meta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    lists:     <?php echo json_encode($datalists, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    rows:      [],
    urlCheck:  '<?php echo admin_url('hr/import_employees_validate'); ?>',
    urlApply:  '<?php echo admin_url('hr/import_employees_apply'); ?>',
    csrfName:  '<?php echo $this->security->get_csrf_token_name(); ?>',
    csrfHash:  '<?php echo $this->security->get_csrf_hash(); ?>',
    page: 1, perPage: 50, filter: 'all', search: '', colMode: 'all',
    busy: false, queued: false, timer: null, seq: 0, applied: false
};

(function (raw) {
    for (var i = 0; i < raw.length; i++) {
        HRIMP.rows.push({
            uid:      'r' + (HRIMP.seq++),
            line:     raw[i].row,
            values:   raw[i].values,
            action:   raw[i].action,
            messages: raw[i].messages || [],
            issues:   raw[i].issues || {},
            include:  true
        });
    }
})(<?php echo json_encode($rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);

$(function () {
    var $body = $('#hrimp_body'), $head = $('#hrimp_head');

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /* ------------------------------------------------- column visibility */
    function issueColumns() {
        var set = {};
        HRIMP.rows.forEach(function (r) { for (var k in r.issues) { set[k] = true; } });
        return set;
    }
    function visibleColumns() {
        if (HRIMP.colMode === 'essential') {
            return HRIMP.columns.filter(function (c) { return HRIMP.meta[c].essential; });
        }
        if (HRIMP.colMode === 'issues') {
            var bad = issueColumns(), keep = ['first_name', 'last_name', 'email'];
            var cols = HRIMP.columns.filter(function (c) { return bad[c] || keep.indexOf(c) !== -1; });
            return cols.length ? cols : HRIMP.columns.filter(function (c) { return HRIMP.meta[c].essential; });
        }
        return HRIMP.columns;
    }

    /* --------------------------------------------------------- filtering */
    function rowHasIssue(r, level) {
        for (var k in r.issues) {
            if (!level || r.issues[k].level === level) { return true; }
        }
        return false;
    }
    function matchesSearch(r) {
        if (!HRIMP.search) { return true; }
        var q = HRIMP.search.toLowerCase();
        for (var i = 0; i < HRIMP.columns.length; i++) {
            var v = r.values[HRIMP.columns[i]];
            if (v && String(v).toLowerCase().indexOf(q) !== -1) { return true; }
        }
        return String(r.line).indexOf(q) !== -1;
    }
    function filtered() {
        return HRIMP.rows.filter(function (r) {
            if (!matchesSearch(r)) { return false; }
            if (HRIMP.filter === 'all') { return true; }
            if (HRIMP.filter === 'issues') { return rowHasIssue(r); }
            return r.action === HRIMP.filter;
        });
    }

    /* ------------------------------------------------------------ render */
    function renderHead() {
        var cols = visibleColumns(), h = '<tr>';
        h += '<th class="hrimp-pin hrimp-pin-1" title="Tick the rows to import"><input type="checkbox" id="hrimp_all_chk" checked></th>';
        h += '<th class="hrimp-pin hrimp-pin-2">#</th>';
        h += '<th class="hrimp-pin hrimp-pin-3">Action</th>';
        h += '<th class="hrimp-pin hrimp-pin-4">Employee</th>';
        cols.forEach(function (c) {
            h += '<th style="min-width:' + HRIMP.meta[c].width + 'px;">' + esc(HRIMP.meta[c].label) + '<small>' + esc(c) + '</small></th>';
        });
        h += '<th class="hrimp-notes">Notes</th><th style="width:34px;"></th></tr>';
        $head.html(h);
    }

    function cellHtml(r, c, idx) {
        var m = HRIMP.meta[c], v = r.values[c] === undefined || r.values[c] === null ? '' : String(r.values[c]);
        var issue = r.issues[c];
        var cls   = issue ? ' cell-' + issue.level : '';
        var tip   = issue ? ' title="' + esc(issue.message) + '"' : '';
        var attrs = ' class="hrimp-in" data-uid="' + r.uid + '" data-col="' + c + '"';
        var inner;

        if (m.type === 'select') {
            var opts = '<option value=""></option>', found = false;
            m.options.forEach(function (o) {
                var sel = (String(v).toLowerCase() === String(o.v).toLowerCase());
                if (sel) { found = true; }
                opts += '<option value="' + esc(o.v) + '"' + (sel ? ' selected' : '') + '>' + esc(o.l) + '</option>';
            });
            if (v !== '' && !found) {
                // Keep whatever the sheet had so the user can see and correct it.
                opts += '<option value="' + esc(v) + '" selected>' + esc(v) + ' (not accepted)</option>';
            }
            inner = '<select' + attrs + '>' + opts + '</select>';
        } else {
            var type = (m.type === 'number') ? 'number' : 'text';
            var extra = '';
            if (m.type === 'date') { extra = ' placeholder="YYYY-MM-DD"'; }
            if (m.list) { extra += ' list="hrimp_list_' + m.list + '" autocomplete="off"'; }
            if (m.type === 'number') { extra += ' step="any"'; }
            inner = '<input type="' + type + '"' + attrs + extra + ' value="' + esc(v) + '">';
        }
        return '<td class="hrimp-cell' + cls + '"' + tip + ' data-col="' + c + '">' + inner + '</td>';
    }

    function notesHtml(r) {
        if (!r.messages.length) { return '<span class="text-muted">&mdash;</span>'; }
        return '<div class="hrimp-note-wrap">' + r.messages.map(function (m) {
            return '<div class="hrimp-note-' + esc(m.level) + '">' + esc(m.text) + '</div>';
        }).join('') + '</div>';
    }

    function renderBody() {
        var cols = visibleColumns(), list = filtered();
        var per  = HRIMP.perPage || list.length || 1;
        var pages = Math.max(1, Math.ceil(list.length / per));
        if (HRIMP.page > pages) { HRIMP.page = pages; }
        var start = (HRIMP.page - 1) * per, slice = list.slice(start, start + per);

        var html = '';
        slice.forEach(function (r) {
            var idx = HRIMP.rows.indexOf(r);
            html += '<tr data-uid="' + r.uid + '" class="row-' + r.action + (r.include ? '' : ' row-off') + '">';
            html += '<td class="hrimp-pin hrimp-pin-1"><input type="checkbox" class="hrimp-inc" data-uid="' + r.uid + '"' + (r.include ? ' checked' : '') + '></td>';
            html += '<td class="hrimp-pin hrimp-pin-2">' + r.line + '</td>';
            html += '<td class="hrimp-pin hrimp-pin-3"><span class="hrimp-label hrimp-label-' + r.action + '">' + labelFor(r.action) + '</span></td>';
            html += '<td class="hrimp-pin hrimp-pin-4">' + esc(((r.values.first_name || '') + ' ' + (r.values.last_name || '')).trim() || '—') + '</td>';
            cols.forEach(function (c) { html += cellHtml(r, c, idx); });
            html += '<td class="hrimp-notes">' + notesHtml(r) + '</td>';
            html += '<td><span class="hrimp-del" data-del="' + r.uid + '" title="Remove this row">&times;</span></td>';
            html += '</tr>';
        });
        if (!slice.length) {
            html = '<tr><td colspan="' + (cols.length + 6) + '" class="text-muted" style="padding:18px;text-align:center;">No rows match this view.</td></tr>';
        }
        $body.html(html);

        $('#hrimp_pageinfo').text(list.length + ' row(s)' + (pages > 1 ? ' · page ' + HRIMP.page + ' of ' + pages : ''));
        $('#hrimp_prev').prop('disabled', HRIMP.page <= 1);
        $('#hrimp_next').prop('disabled', HRIMP.page >= pages);
    }

    function labelFor(a) {
        return a === 'created' ? 'Create' : a === 'updated' ? 'Update' : a === 'skipped' ? 'Skip' : 'Blank';
    }

    function renderCounts() {
        var c = { all: HRIMP.rows.length, created: 0, updated: 0, skipped: 0, issues: 0 };
        HRIMP.rows.forEach(function (r) {
            if (c[r.action] !== undefined) { c[r.action]++; }
            if (rowHasIssue(r)) { c.issues++; }
        });
        for (var k in c) { $('[data-count="' + k + '"]').text(c[k]); }

        // Once the import has run the button is a status, not a control.
        if (HRIMP.applied) { return; }

        var ticked = HRIMP.rows.filter(function (r) { return r.include; });
        var bad    = ticked.filter(function (r) { return r.action === 'skipped'; }).length;
        $('#hrimp_apply').prop('disabled', ticked.length === 0)
            .html('<i class="fa fa-save"></i> Import ' + ticked.length + ' ticked row(s)');
        $('#hrimp_apply_hint').text(bad ? bad + ' of them still have errors and will be skipped.' : '');
    }

    function render() { renderHead(); renderBody(); renderCounts(); }

    /* ---------------------------------------------------- live validation */
    function setState(text, busy) {
        $('#hrimp_state').text(text).toggleClass('busy', !!busy);
    }
    function payload() {
        return HRIMP.rows.map(function (r) {
            var o = { _row: r.line, _uid: r.uid };
            HRIMP.columns.forEach(function (c) { o[c] = r.values[c] === undefined || r.values[c] === null ? '' : String(r.values[c]); });
            return o;
        });
    }
    function schedule() {
        setState('Editing…', true);
        clearTimeout(HRIMP.timer);
        HRIMP.timer = setTimeout(validateNow, 700);
    }
    function validateNow() {
        if (HRIMP.busy) { HRIMP.queued = true; return; }
        HRIMP.busy = true;
        setState('Checking…', true);

        var data = { rows: JSON.stringify(payload()) };
        data[HRIMP.csrfName] = HRIMP.csrfHash;

        $.post(HRIMP.urlCheck, data, function (res) {
            if (res && res[HRIMP.csrfName]) { HRIMP.csrfHash = res[HRIMP.csrfName]; }
            if (!res || !res.success) {
                setState(res && res.message ? res.message : 'Check failed', false);
                return;
            }
            // Same order as sent — map straight back onto the model.
            res.rows.forEach(function (out, i) {
                var r = HRIMP.rows[i];
                if (!r) { return; }
                r.action   = out.action === 'empty' ? 'empty' : out.action;
                r.messages = out.messages || [];
                r.issues   = out.issues || {};
                // Adopt the server's normalised values, but never yank the
                // field the user is typing in right now.
                var active = document.activeElement;
                HRIMP.columns.forEach(function (c) {
                    if (active && active.getAttribute && active.getAttribute('data-uid') === r.uid && active.getAttribute('data-col') === c) { return; }
                    r.values[c] = out.values[c];
                });
            });
            repaint();
            setState('All rows checked', false);
        }, 'json').fail(function () {
            setState('Could not reach the server', false);
        }).always(function () {
            HRIMP.busy = false;
            if (HRIMP.queued) { HRIMP.queued = false; validateNow(); }
        });
    }

    /* Refresh what is on screen without rebuilding inputs the user may be
       typing in (rebuilding would steal focus mid-edit). */
    function repaint() {
        var active = document.activeElement;
        var keepUid = active && active.getAttribute ? active.getAttribute('data-uid') : null;
        var keepCol = active && active.getAttribute ? active.getAttribute('data-col') : null;

        $body.find('tr[data-uid]').each(function () {
            var $tr = $(this), r = rowByUid($tr.data('uid'));
            if (!r) { return; }
            $tr.attr('class', 'row-' + r.action + (r.include ? '' : ' row-off'));
            $tr.find('.hrimp-pin-3').html('<span class="hrimp-label hrimp-label-' + r.action + '">' + labelFor(r.action) + '</span>');
            $tr.find('.hrimp-pin-4').text(((r.values.first_name || '') + ' ' + (r.values.last_name || '')).trim() || '—');
            $tr.find('td.hrimp-notes').html(notesHtml(r));
            $tr.find('td.hrimp-cell').each(function () {
                var $td = $(this), c = $td.data('col'), issue = r.issues[c];
                $td.removeClass('cell-error cell-warning cell-info');
                if (issue) { $td.addClass('cell-' + issue.level).attr('title', issue.message); }
                else { $td.removeAttr('title'); }
                var $in = $td.find('.hrimp-in');
                if ($in.length && !(keepUid === r.uid && keepCol === c)) {
                    var v = r.values[c] === undefined || r.values[c] === null ? '' : String(r.values[c]);
                    if ($in.is('select')) {
                        if (!$in.find('option').filter(function () { return this.value === v; }).length && v !== '') {
                            $in.append($('<option>').val(v).text(v + ' (not accepted)'));
                        }
                    }
                    if ($in.val() !== v) { $in.val(v); }
                }
            });
        });
        renderCounts();
    }

    function rowByUid(uid) {
        for (var i = 0; i < HRIMP.rows.length; i++) {
            if (HRIMP.rows[i].uid === uid) { return HRIMP.rows[i]; }
        }
        return null;
    }

    /* ----------------------------------------------------------- wiring */
    $body.on('input change', '.hrimp-in', function () {
        var r = rowByUid($(this).data('uid'));
        if (!r) { return; }
        r.values[$(this).data('col')] = $(this).val();
        schedule();
    });

    $body.on('change', '.hrimp-inc', function () {
        var r = rowByUid($(this).data('uid'));
        if (!r) { return; }
        r.include = this.checked;
        $(this).closest('tr').toggleClass('row-off', !r.include);
        renderCounts();
    });

    $body.on('click', '.hrimp-del', function () {
        var uid = $(this).data('del'), r = rowByUid(uid);
        if (!r) { return; }
        HRIMP.rows.splice(HRIMP.rows.indexOf(r), 1);
        renderBody(); renderCounts();
        schedule();
    });

    $(document).on('change', '#hrimp_all_chk', function () {
        var on = this.checked, shown = {};
        $body.find('tr[data-uid]').each(function () { shown[$(this).data('uid')] = true; });
        HRIMP.rows.forEach(function (r) { if (shown[r.uid]) { r.include = on; } });
        renderBody(); renderCounts();
    });

    $('.hrimp-chip').on('click', function () {
        $('.hrimp-chip').removeClass('active');
        $(this).addClass('active');
        HRIMP.filter = $(this).data('filter');
        HRIMP.page = 1;
        renderBody();
    });

    var searchTimer = null;
    $('#hrimp_search').on('input', function () {
        var v = this.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { HRIMP.search = v; HRIMP.page = 1; renderBody(); }, 200);
    });

    $('#hrimp_colmode').on('change', function () { HRIMP.colMode = this.value; render(); });
    $('#hrimp_perpage').on('change', function () { HRIMP.perPage = parseInt(this.value, 10); HRIMP.page = 1; renderBody(); });
    $('#hrimp_prev').on('click', function () { if (HRIMP.page > 1) { HRIMP.page--; renderBody(); } });
    $('#hrimp_next').on('click', function () { HRIMP.page++; renderBody(); });

    $('#hrimp_untick_bad').on('click', function () {
        HRIMP.rows.forEach(function (r) { if (r.action === 'skipped') { r.include = false; } });
        renderBody(); renderCounts();
    });
    $('#hrimp_tick_all').on('click', function () {
        HRIMP.rows.forEach(function (r) { r.include = true; });
        renderBody(); renderCounts();
    });

    $('#hrimp_add_row').on('click', function () {
        var max = 1;
        HRIMP.rows.forEach(function (r) { max = Math.max(max, r.line); });
        var values = {};
        HRIMP.columns.forEach(function (c) { values[c] = ''; });
        HRIMP.rows.push({ uid: 'r' + (HRIMP.seq++), line: max + 1, values: values, action: 'skipped', messages: [], issues: {}, include: true });
        HRIMP.filter = 'all';
        $('.hrimp-chip').removeClass('active');
        $('.hrimp-chip[data-filter="all"]').addClass('active');
        HRIMP.page = HRIMP.perPage ? Math.ceil(HRIMP.rows.length / HRIMP.perPage) : 1;
        renderBody(); renderCounts();
        schedule();
    });

    $('#hrimp_revalidate').on('click', function () { clearTimeout(HRIMP.timer); validateNow(); });

    /* ------------------------------------------------------- bulk filling */
    function buildBulkColumn() {
        var opts = '';
        HRIMP.columns.forEach(function (c) { opts += '<option value="' + esc(c) + '">' + esc(HRIMP.meta[c].label) + '</option>'; });
        $('#hrimp_bulk_col').html(opts);
        buildBulkValue();
    }
    function buildBulkValue() {
        var c = $('#hrimp_bulk_col').val(), m = HRIMP.meta[c], html;
        if (m.type === 'select') {
            html = '<select id="hrimp_bulk_value" class="form-control input-sm"><option value=""></option>';
            m.options.forEach(function (o) { html += '<option value="' + esc(o.v) + '">' + esc(o.l) + '</option>'; });
            html += '</select>';
        } else {
            var list = m.list ? ' list="hrimp_list_' + m.list + '"' : '';
            html = '<input type="' + (m.type === 'number' ? 'number' : 'text') + '" id="hrimp_bulk_value" class="form-control input-sm"' + list
                + (m.type === 'date' ? ' placeholder="YYYY-MM-DD"' : '') + '>';
        }
        $('#hrimp_bulk_value_wrap').html(html);
    }
    $('#hrimp_bulk_col').on('change', buildBulkValue);

    function bulkFill(onlyBlank) {
        var c = $('#hrimp_bulk_col').val(), v = $('#hrimp_bulk_value').val();
        var list = filtered();
        if (!list.length) { return; }
        list.forEach(function (r) {
            if (onlyBlank && String(r.values[c] || '').trim() !== '') { return; }
            r.values[c] = v;
        });
        renderBody();
        validateNow();
    }
    $('#hrimp_bulk_apply').on('click', function () { bulkFill(false); });
    $('#hrimp_bulk_blank').on('click', function () { bulkFill(true); });

    /* ------------------------------------------------------------- apply */
    $('#hrimp_apply').on('click', function () {
        var ticked = HRIMP.rows.filter(function (r) { return r.include; });
        if (!ticked.length) { return; }
        var creates = ticked.filter(function (r) { return r.action === 'created'; }).length,
            updates = ticked.filter(function (r) { return r.action === 'updated'; }).length,
            skips   = ticked.filter(function (r) { return r.action === 'skipped'; }).length;

        if (!confirm('Import now?\n\n' + creates + ' employee(s) will be created\n' + updates + ' will be updated\n'
            + skips + ' will be skipped (errors)\n\nThis writes to the database.')) { return; }

        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing…');
        var data = {
            rows: JSON.stringify(ticked.map(function (r) {
                var o = { _row: r.line, _uid: r.uid };
                HRIMP.columns.forEach(function (c) { o[c] = r.values[c] === undefined || r.values[c] === null ? '' : String(r.values[c]); });
                return o;
            }))
        };
        data[HRIMP.csrfName] = HRIMP.csrfHash;

        $.post(HRIMP.urlApply, data, function (res) {
            if (res && res[HRIMP.csrfName]) { HRIMP.csrfHash = res[HRIMP.csrfName]; }
            if (!res || !res.success) {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Import ticked rows');
                if (typeof alert_float === 'function') { alert_float('danger', (res && res.message) || 'The import could not be run.'); }
                return;
            }
            HRIMP.applied = true;
            showResult(res.summary);
        }, 'json').fail(function () {
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Import ticked rows');
            if (typeof alert_float === 'function') { alert_float('danger', 'The import could not be run. Please try again.'); }
        });
    });

    function showResult(s) {
        var html = '<div class="alert alert-success">'
            + '<strong><i class="fa fa-check"></i> Import finished.</strong> '
            + '<span class="label label-success">Created ' + s.created + '</span> '
            + '<span class="label label-info">Updated ' + s.updated + '</span> '
            + '<span class="label label-danger">Skipped ' + s.skipped + '</span>'
            + ' &nbsp; <a href="<?php echo admin_url('hr/employees'); ?>" class="btn btn-success btn-sm">Go to Employees</a>'
            + ' <a href="<?php echo admin_url('hr/import_employees'); ?>" class="btn btn-default btn-sm">Import another file</a>'
            + '</div>';

        var skipped = (s.results || []).filter(function (r) { return r.action === 'skipped'; });
        if (skipped.length) {
            html += '<div class="alert alert-warning" style="max-height:220px;overflow:auto;"><strong>Rows that were skipped</strong><ul style="margin:6px 0 0;padding-left:18px;">';
            skipped.forEach(function (r) {
                html += '<li>Row ' + r.row + ' &mdash; ' + esc(r.name) + ': ' + esc((r.messages || []).join('; ')) + '</li>';
            });
            html += '</ul></div>';
        }
        $('#hrimp_result').html(html);
        // Freeze the grid: the rows below have already been written, so it is
        // now a record of what happened, not something still editable.
        $('#hrimp').addClass('hrimp-locked');
        $('#hrimp_apply').prop('disabled', true).html('<i class="fa fa-check"></i> Import finished');
        $('#hrimp_revalidate, #hrimp_bulk_apply, #hrimp_bulk_blank, #hrimp_add_row, #hrimp_untick_bad, #hrimp_tick_all').prop('disabled', true);
        $('#hrimp_apply_hint').text('Re-upload the file if you need to run another pass.');
        setState('Imported', false);
        $('html, body').animate({ scrollTop: $('#hrimp_result').offset().top - 90 }, 250);
    }

    /* ------------------------------------------------------------ go */
    buildBulkColumn();
    render();
    setState('Ready — ' + HRIMP.rows.length + ' row(s) checked', false);
});
</script>
<datalist id="hrimp_list_departments"><?php foreach ($datalists['departments'] as $d) { ?><option value="<?php echo html_escape($d); ?>"></option><?php } ?></datalist>
<datalist id="hrimp_list_designations"><?php foreach ($datalists['designations'] as $d) { ?><option value="<?php echo html_escape($d); ?>"></option><?php } ?></datalist>
<datalist id="hrimp_list_managers"><?php foreach ($datalists['managers'] as $d) { ?><option value="<?php echo html_escape($d); ?>"></option><?php } ?></datalist>
<?php } ?>
