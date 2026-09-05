<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * "KRA & KPI" tab of the employee profile.
 *
 * Key Result Areas (what the employee is accountable for) and the Key
 * Performance Indicators that measure them. Both are rows of the same table
 * (entry_type), so a KPI can point at the KRA it measures.
 *
 * Expects: $employee, $kras, $kpis, $kra_kpi_totals, $can_edit, $can_delete.
 *
 * Editing happens in ONE inline panel (not a modal): TinyMCE dialogs fight
 * Bootstrap 3's modal focus trap, and an inline panel also gives the rich-text
 * fields the full width of the page.
 */
$kk_freq  = hr_kra_kpi_frequencies();
$kk_stat  = hr_kra_kpi_statuses();

// KRA titles, so each KPI row can name the goal it measures.
$kra_titles = [];
foreach ($kras as $k) {
    $kra_titles[(int) $k['id']] = $k['title'];
}

// Weightage of each type should add up to 100%. Shown as a badge and never
// enforced — targets are usually entered over several sittings.
$kk_badge = function ($type) use ($kra_kpi_totals) {
    $t = $kra_kpi_totals[$type] ?? null;
    if (!$t || !$t['items']) {
        return '';
    }
    $ok  = abs(round($t['total'], 2) - 100) < 0.01;
    $out = '<span class="label label-' . ($ok ? 'success' : 'warning') . '" style="font-weight:600;">'
        . '<i class="fa ' . ($ok ? 'fa-check' : 'fa-exclamation-triangle') . '"></i> Weightage: '
        . rtrim(rtrim(number_format($t['total'], 2), '0'), '.') . '%</span>';
    if ($t['avg_rating'] !== null) {
        $out .= ' <span class="label label-info" style="font-weight:600;"><i class="fa fa-star"></i> Avg rating: '
            . number_format($t['avg_rating'], 2) . ' / 5</span>';
    }

    return $out;
};

// One row of either table. $type decides which of the KPI-only columns render.
$kk_row = function ($r, $type) use ($employee, $kk_freq, $kk_stat, $kra_titles, $can_edit, $can_delete) {
    $st   = $kk_stat[$r['status']] ?? $kk_stat['not_started'];
    $freq = $kk_freq[$r['frequency']] ?? $r['frequency'];
    $per  = '';
    if ($r['period_from'] || $r['period_to']) {
        $per = ($r['period_from'] ? _d($r['period_from']) : '?') . ' – ' . ($r['period_to'] ? _d($r['period_to']) : '?');
    }
    ?>
    <tr>
        <td>
            <span class="bold"><?php echo html_escape($r['title']); ?></span>
            <?php if (!empty($r['description'])) { ?>
                <div class="hr-kk-desc"><?php echo $r['description']; ?></div>
            <?php } ?>
            <?php if (!empty($r['review_remarks'])) { ?>
                <div class="hr-kk-remarks"><span class="bold">Review:</span> <?php echo $r['review_remarks']; ?></div>
            <?php } ?>
        </td>
        <?php if ($type === 'kpi') { ?>
            <td>
                <?php if (!empty($r['parent_id']) && isset($kra_titles[(int) $r['parent_id']])) { ?>
                    <span class="text-muted" style="font-size:12px;"><i class="fa fa-bullseye"></i> <?php echo html_escape($kra_titles[(int) $r['parent_id']]); ?></span>
                <?php } else { ?>
                    <span class="text-muted">—</span>
                <?php } ?>
            </td>
            <td><?php echo html_escape((string) $r['metric']) ?: '—'; ?></td>
            <td>
                <span class="bold"><?php echo html_escape((string) $r['target_value']) ?: '—'; ?></span>
                <?php if ($r['actual_value'] !== null && $r['actual_value'] !== '') { ?>
                    <span class="text-muted"> → </span><span class="bold text-info"><?php echo html_escape((string) $r['actual_value']); ?></span>
                <?php } ?>
            </td>
        <?php } ?>
        <td><?php echo rtrim(rtrim(number_format((float) $r['weightage'], 2), '0'), '.'); ?>%</td>
        <td style="font-size:12px;">
            <?php echo html_escape($freq); ?>
            <?php if ($per !== '') { ?><br><span class="text-muted"><?php echo $per; ?></span><?php } ?>
        </td>
        <td><span class="label label-<?php echo $st['class']; ?>"><?php echo html_escape($st['label']); ?></span></td>
        <td><?php echo $r['rating'] === null ? '<span class="text-muted">—</span>' : '<span class="bold">' . rtrim(rtrim(number_format((float) $r['rating'], 2), '0'), '.') . '</span> / 5'; ?></td>
        <?php if ($can_edit || $can_delete) { ?>
            <td class="text-right">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-default btn-icon hr-kk-edit" data-id="<?php echo (int) $r['id']; ?>" title="Edit"><i class="fa fa-pencil"></i></button>
                <?php } ?>
                <?php if ($can_delete) { ?>
                    <a href="<?php echo admin_url('hr/delete_kra_kpi/' . (int) $employee['staffid'] . '/' . (int) $r['id']); ?>" class="btn btn-danger btn-icon _delete" title="Delete"><i class="fa fa-remove"></i></a>
                <?php } ?>
            </td>
        <?php } ?>
    </tr>
    <?php
};
?>

<style>
    .hr-kk-desc, .hr-kk-remarks { font-size: 12px; color: #6b7280; margin-top: 4px; }
    .hr-kk-desc p, .hr-kk-remarks p { margin: 0 0 4px; }
    .hr-kk-desc ul, .hr-kk-desc ol { margin: 0 0 4px; padding-left: 18px; }
    .hr-kk-remarks { border-left: 3px solid #e3e8ee; padding-left: 8px; }
    .hr-kk-form-panel { border: 1px solid #dde3e8; border-radius: 6px; background: #f9fbfc; padding: 15px; margin-bottom: 20px; }
    .hr-kk-form-panel h4 { margin-top: 0; }
    .hr-kk-section-head { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin: 0 0 10px; }
    .hr-kk-section-head h4 { margin: 0; flex: 1 1 auto; }
</style>

<?php if ($can_edit) { ?>
    <!-- ------------------------------------------------- add / edit panel -->
    <div class="hr-kk-form-panel" id="hr-kk-form-panel" style="display:none;">
        <?php echo form_open(admin_url('hr/save_kra_kpi/' . (int) $employee['staffid']), ['id' => 'hr-kk-form']); ?>
        <input type="hidden" name="id" value="0">
        <input type="hidden" name="entry_type" value="kra">
        <h4 class="bold"><i class="fa fa-bullseye hr-kk-form-icon"></i> <span id="hr-kk-form-title">Add Key Result Area</span></h4>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group"><label>Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="hr-kk-title" class="form-control" maxlength="255" placeholder="e.g. Patient satisfaction &amp; service quality"></div>
            </div>
            <div class="col-md-2">
                <div class="form-group"><label>Weightage (%)</label>
                    <input type="number" name="weightage" class="form-control" min="0" max="100" step="0.01" value="0"></div>
            </div>
            <div class="col-md-2">
                <div class="form-group"><label>Frequency</label>
                    <select name="frequency" class="form-control">
                        <?php foreach ($kk_freq as $k => $label) { ?>
                            <option value="<?php echo $k; ?>" <?php echo $k === 'annual' ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
                        <?php } ?>
                    </select></div>
            </div>
            <div class="col-md-2">
                <div class="form-group"><label>Status</label>
                    <select name="status" class="form-control">
                        <?php foreach ($kk_stat as $k => $s) { ?>
                            <option value="<?php echo $k; ?>"><?php echo html_escape($s['label']); ?></option>
                        <?php } ?>
                    </select></div>
            </div>
        </div>

        <div class="row hr-kk-kpi-only" style="display:none;">
            <div class="col-md-3">
                <div class="form-group"><label>Measures which KRA</label>
                    <select name="parent_id" class="form-control">
                        <option value="0">— Not linked —</option>
                        <?php foreach ($kras as $k) { ?>
                            <option value="<?php echo (int) $k['id']; ?>"><?php echo html_escape($k['title']); ?></option>
                        <?php } ?>
                    </select></div>
            </div>
            <div class="col-md-3">
                <div class="form-group"><label>Metric / Unit</label>
                    <input type="text" name="metric" class="form-control" maxlength="150" placeholder="e.g. % of feedback forms rated 4+"></div>
            </div>
            <div class="col-md-3">
                <div class="form-group"><label>Target</label>
                    <input type="text" name="target_value" class="form-control" maxlength="100" placeholder="e.g. 90%"></div>
            </div>
            <div class="col-md-3">
                <div class="form-group"><label>Actual Achieved</label>
                    <input type="text" name="actual_value" class="form-control" maxlength="100" placeholder="e.g. 87%"></div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="form-group"><label>Period From</label>
                    <input type="date" name="period_from" class="form-control"></div>
            </div>
            <div class="col-md-3">
                <div class="form-group"><label>Period To</label>
                    <input type="date" name="period_to" class="form-control"></div>
            </div>
            <div class="col-md-3">
                <div class="form-group"><label>Rating <small class="text-muted">(out of 5)</small></label>
                    <input type="number" name="rating" class="form-control" min="0" max="5" step="0.1" placeholder="Not rated yet"></div>
            </div>
            <div class="col-md-3">
                <div class="form-group"><label>Display Order</label>
                    <input type="number" name="sort_order" class="form-control" step="1" value="0"></div>
            </div>
        </div>

        <div class="form-group">
            <label for="hr_kk_description">Description / Details</label>
            <textarea name="description" id="hr_kk_description" class="form-control" rows="6"></textarea>
        </div>

        <div class="form-group">
            <label for="hr_kk_review_remarks">Review Remarks <small class="text-muted">(appraisal notes, evidence, agreed actions)</small></label>
            <textarea name="review_remarks" id="hr_kk_review_remarks" class="form-control" rows="4"></textarea>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button>
        <button type="button" class="btn btn-default" id="hr-kk-cancel">Cancel</button>
        <?php echo form_close(); ?>
    </div>
<?php } ?>

<!-- ------------------------------------------------------------------ KRA -->
<div class="hr-kk-section-head">
    <h4 class="bold"><i class="fa fa-bullseye"></i> Key Result Areas (KRA)
        <small class="text-muted">— what this employee is accountable for</small>
    </h4>
    <?php echo $kk_badge('kra'); ?>
    <?php if ($can_edit) { ?>
        <button type="button" class="btn btn-primary btn-sm hr-kk-add" data-type="kra"><i class="fa fa-plus"></i> Add KRA</button>
    <?php } ?>
</div>
<table class="table table-striped">
    <thead>
        <tr>
            <th>Key Result Area</th><th>Weightage</th><th>Frequency / Period</th><th>Status</th><th>Rating</th>
            <?php if ($can_edit || $can_delete) { ?><th class="text-right">Actions</th><?php } ?>
        </tr>
    </thead>
    <tbody>
        <?php if (!count($kras)) { ?>
            <tr><td colspan="<?php echo ($can_edit || $can_delete) ? 6 : 5; ?>" class="text-muted">No KRA defined for this employee yet.</td></tr>
        <?php }
        foreach ($kras as $r) { $kk_row($r, 'kra'); } ?>
    </tbody>
</table>

<!-- ------------------------------------------------------------------ KPI -->
<div class="hr-kk-section-head mtop25">
    <h4 class="bold"><i class="fa fa-line-chart"></i> Key Performance Indicators (KPI)
        <small class="text-muted">— how each result area is measured</small>
    </h4>
    <?php echo $kk_badge('kpi'); ?>
    <?php if ($can_edit) { ?>
        <button type="button" class="btn btn-primary btn-sm hr-kk-add" data-type="kpi"><i class="fa fa-plus"></i> Add KPI</button>
    <?php } ?>
</div>
<table class="table table-striped">
    <thead>
        <tr>
            <th>Indicator</th><th>Measures</th><th>Metric / Unit</th><th>Target → Actual</th>
            <th>Weightage</th><th>Frequency / Period</th><th>Status</th><th>Rating</th>
            <?php if ($can_edit || $can_delete) { ?><th class="text-right">Actions</th><?php } ?>
        </tr>
    </thead>
    <tbody>
        <?php if (!count($kpis)) { ?>
            <tr><td colspan="<?php echo ($can_edit || $can_delete) ? 9 : 8; ?>" class="text-muted">No KPI defined for this employee yet.</td></tr>
        <?php }
        foreach ($kpis as $r) { $kk_row($r, 'kpi'); } ?>
    </tbody>
</table>

<?php if ($can_edit) { ?>
    <script>
        // KRA / KPI inline editor. Vanilla JS: jQuery, TinyMCE and init_editor()
        // all load in the admin footer, after this script runs.
        (function () {
            var rows = <?php echo json_encode(array_merge($kras, $kpis), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            var EDITORS = ['hr_kk_description', 'hr_kk_review_remarks'];
            var editorsRequested = false;

            var panel = document.getElementById('hr-kk-form-panel');
            var form  = document.getElementById('hr-kk-form');
            if (!panel || !form) { return; }

            function byId(id) {
                for (var i = 0; i < rows.length; i++) {
                    if (String(rows[i].id) === String(id)) { return rows[i]; }
                }
                return null;
            }

            // TinyMCE is initialised the first time the panel opens: the tab
            // pane is hidden at page load, where an auto-resizing editor would
            // measure a zero height. cb runs once every editor is ready (or
            // right away with a null editor when TinyMCE is unavailable).
            function withEditors(cb) {
                if (!window.tinymce || typeof init_editor !== 'function') { cb(false); return; }
                if (!editorsRequested) {
                    editorsRequested = true;
                    EDITORS.forEach(function (id) {
                        init_editor('#' + id, { height: 220, min_height: 220 });
                    });
                }
                var tries = 0;
                (function wait() {
                    var ready = EDITORS.every(function (id) {
                        var ed = tinymce.get(id);
                        return ed && ed.initialized;
                    });
                    if (ready) { cb(true); return; }
                    if (tries++ > 80) { cb(false); return; }
                    setTimeout(wait, 100);
                })();
            }

            function setField(name, value) {
                var field = form.querySelector('[name="' + name + '"]');
                if (!field) { return; }
                field.value = (value === null || value === undefined) ? '' : value;
            }

            function setEditorValue(id, html) {
                var area = document.getElementById(id);
                if (area) { area.value = html || ''; }
                withEditors(function (ok) {
                    if (!ok) { return; }
                    var ed = tinymce.get(id);
                    if (ed) { ed.setContent(html || ''); }
                });
            }

            // row = null opens the panel in "add" mode for the given type.
            function open(type, row) {
                form.reset();

                var isKpi = (type === 'kpi');
                document.querySelector('.hr-kk-form-icon').className = 'fa ' + (isKpi ? 'fa-line-chart' : 'fa-bullseye') + ' hr-kk-form-icon';
                document.getElementById('hr-kk-form-title').textContent =
                    (row ? 'Edit ' : 'Add ') + (isKpi ? 'Key Performance Indicator' : 'Key Result Area');
                Array.prototype.forEach.call(panel.querySelectorAll('.hr-kk-kpi-only'), function (el) {
                    el.style.display = isKpi ? '' : 'none';
                });

                setField('id', row ? row.id : 0);
                setField('entry_type', type);

                if (row) {
                    ['title', 'metric', 'target_value', 'actual_value', 'weightage', 'frequency',
                        'period_from', 'period_to', 'status', 'rating', 'sort_order'].forEach(function (k) {
                        setField(k, row[k]);
                    });
                    setField('parent_id', row.parent_id ? row.parent_id : 0);
                }
                setEditorValue('hr_kk_description', row ? row.description : '');
                setEditorValue('hr_kk_review_remarks', row ? row.review_remarks : '');

                panel.style.display = '';
                panel.scrollIntoView({ block: 'nearest' });
                var title = document.getElementById('hr-kk-title');
                if (title) { title.focus(); }
            }

            document.addEventListener('click', function (e) {
                if (!e.target.closest) { return; }
                var t;
                if ((t = e.target.closest('.hr-kk-add'))) {
                    open(t.getAttribute('data-type'), null);
                } else if ((t = e.target.closest('.hr-kk-edit'))) {
                    var row = byId(t.getAttribute('data-id'));
                    if (row) { open(row.entry_type, row); }
                } else if (e.target.closest('#hr-kk-cancel')) {
                    panel.style.display = 'none';
                }
            });

            // Push the editors back into their textareas before the POST.
            form.addEventListener('submit', function (e) {
                if (window.tinymce) { tinymce.triggerSave(); }
                var title = document.getElementById('hr-kk-title');
                if (title && !title.value.trim()) {
                    e.preventDefault();
                    title.focus();
                }
            });
        })();
    </script>
<?php } ?>
