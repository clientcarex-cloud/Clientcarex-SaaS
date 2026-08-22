<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
/**
 * Smart Plans — Excel/CSV export & import for SaaS packages.
 *
 * @var array      $columns       Column schema [['key','label','readonly','help'], ...]
 * @var int        $plans_count   Number of packages available to export
 * @var array|null $import_result Summary returned after an import, or null
 */
$export_url     = admin_url(PERFEX_SAAS_ROUTE_NAME . '/smart_plans/export');
$export_csv_url = admin_url(PERFEX_SAAS_ROUTE_NAME . '/smart_plans/export_csv');
$import_url     = admin_url(PERFEX_SAAS_ROUTE_NAME . '/smart_plans/import');
$stream_url     = admin_url(PERFEX_SAAS_ROUTE_NAME . '/smart_plans/import_stream');
$can_edit       = staff_can('edit', 'perfex_saas_packages');
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-4">
                    <div>
                        <h4 class="tw-mt-0 tw-mb-1 tw-font-semibold tw-text-lg tw-text-neutral-700">
                            <i class="fa fa-file-excel-o tw-mr-1 tw-text-success-600"></i>
                            <?= _l('perfex_saas_smart_plans'); ?>
                        </h4>
                        <p class="tw-text-neutral-500 tw-text-sm tw-mb-0"><?= _l('perfex_saas_smart_plans_subtitle'); ?></p>
                    </div>
                    <a href="<?= admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans'); ?>" class="btn btn-default">
                        <i class="fa fa-table tw-mr-1"></i><?= _l('perfex_saas_pricing_plans'); ?>
                    </a>
                </div>

                <?php if (!empty($import_result) && !empty($import_result['ok'])) : echo get_instance()->load->view('smart_plans/_result', ['import_result' => $import_result], true); endif; ?>

                <!-- Real-time import progress (populated live by JS) -->
                <div class="panel_s tw-mb-4" id="smart-plans-live" style="display:none">
                    <div class="panel-body">
                        <div class="tw-flex tw-items-center tw-justify-between tw-mb-2">
                            <h5 class="tw-mt-0 tw-mb-0 tw-font-semibold">
                                <i class="fa fa-bolt tw-mr-1 tw-text-warning-500"></i><?= _l('perfex_saas_smart_plans_live_title'); ?>
                            </h5>
                            <span id="sp-live-phase" class="tw-text-sm tw-text-neutral-500"></span>
                        </div>

                        <div class="tw-w-full tw-bg-neutral-100 tw-rounded tw-overflow-hidden tw-mb-3" style="height:10px">
                            <div id="sp-live-bar" class="tw-bg-success-500 tw-h-full" style="width:0;transition:width .2s ease"></div>
                        </div>

                        <div class="tw-flex tw-flex-wrap tw-gap-3 tw-mb-3">
                            <div class="tw-flex-1 tw-rounded tw-border tw-border-neutral-200 tw-p-2 tw-text-center" style="min-width:90px">
                                <div id="sp-live-updated" class="tw-text-xl tw-font-bold tw-text-success-600">0</div>
                                <div class="tw-text-xs tw-text-neutral-500" style="text-transform:uppercase"><?= _l('perfex_saas_smart_plans_res_updated'); ?></div>
                            </div>
                            <div class="tw-flex-1 tw-rounded tw-border tw-border-neutral-200 tw-p-2 tw-text-center" style="min-width:90px">
                                <div id="sp-live-unchanged" class="tw-text-xl tw-font-bold tw-text-neutral-600">0</div>
                                <div class="tw-text-xs tw-text-neutral-500" style="text-transform:uppercase"><?= _l('perfex_saas_smart_plans_res_unchanged'); ?></div>
                            </div>
                            <div class="tw-flex-1 tw-rounded tw-border tw-border-neutral-200 tw-p-2 tw-text-center" style="min-width:90px">
                                <div id="sp-live-skipped" class="tw-text-xl tw-font-bold tw-text-warning-500">0</div>
                                <div class="tw-text-xs tw-text-neutral-500" style="text-transform:uppercase"><?= _l('perfex_saas_smart_plans_res_skipped'); ?></div>
                            </div>
                            <div class="tw-flex-1 tw-rounded tw-border tw-border-neutral-200 tw-p-2 tw-text-center" style="min-width:90px">
                                <div id="sp-live-errors" class="tw-text-xl tw-font-bold tw-text-danger-600">0</div>
                                <div class="tw-text-xs tw-text-neutral-500" style="text-transform:uppercase"><?= _l('perfex_saas_smart_plans_res_error'); ?></div>
                            </div>
                        </div>

                        <div id="sp-live-alert"></div>

                        <div class="table-responsive">
                            <table class="table table-bordered tw-mb-0">
                                <thead>
                                    <tr class="tw-bg-neutral-50">
                                        <th style="width:70px"><?= _l('perfex_saas_smart_plans_res_column'); ?></th>
                                        <th style="width:60px"><?= _l('perfex_saas_smart_plans_col_id'); ?></th>
                                        <th style="width:160px"><?= _l('perfex_saas_smart_plans_col_name'); ?></th>
                                        <th style="width:100px"><?= _l('perfex_saas_smart_plans_res_status'); ?></th>
                                        <th><?= _l('perfex_saas_smart_plans_res_changes'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="sp-live-rows"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Step 1 — Export -->
                    <div class="col-md-4 tw-mb-4">
                        <div class="panel_s tw-h-full">
                            <div class="panel-body tw-flex tw-flex-col tw-h-full">
                                <div class="tw-flex tw-items-center tw-mb-2">
                                    <span class="tw-flex tw-items-center tw-justify-center tw-w-7 tw-h-7 tw-rounded-full tw-bg-primary-600 tw-text-white tw-text-sm tw-font-bold tw-mr-2">1</span>
                                    <h5 class="tw-m-0 tw-font-semibold"><?= _l('perfex_saas_smart_plans_step_export'); ?></h5>
                                </div>
                                <p class="tw-text-neutral-500 tw-text-sm tw-flex-1">
                                    <?= _l('perfex_saas_smart_plans_step_export_hint'); ?>
                                    <br><span class="tw-text-neutral-400"><?= _l('perfex_saas_smart_plans_plans_available', $plans_count); ?></span>
                                </p>
                                <div class="tw-flex tw-flex-wrap tw-gap-2 tw-mt-2">
                                    <a href="<?= $export_url; ?>" class="btn btn-primary">
                                        <i class="fa fa-download tw-mr-1"></i><?= _l('perfex_saas_smart_plans_download_excel'); ?>
                                    </a>
                                    <a href="<?= $export_csv_url; ?>" class="btn btn-default">
                                        <i class="fa fa-file-text-o tw-mr-1"></i><?= _l('perfex_saas_smart_plans_download_csv'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 — Edit -->
                    <div class="col-md-4 tw-mb-4">
                        <div class="panel_s tw-h-full">
                            <div class="panel-body tw-flex tw-flex-col tw-h-full">
                                <div class="tw-flex tw-items-center tw-mb-2">
                                    <span class="tw-flex tw-items-center tw-justify-center tw-w-7 tw-h-7 tw-rounded-full tw-bg-warning-500 tw-text-white tw-text-sm tw-font-bold tw-mr-2">2</span>
                                    <h5 class="tw-m-0 tw-font-semibold"><?= _l('perfex_saas_smart_plans_step_edit'); ?></h5>
                                </div>
                                <p class="tw-text-neutral-500 tw-text-sm tw-flex-1 tw-mb-0">
                                    <?= _l('perfex_saas_smart_plans_step_edit_hint'); ?>
                                </p>
                                <ul class="tw-text-neutral-500 tw-text-sm tw-mt-2 tw-mb-0 tw-pl-4" style="list-style:disc">
                                    <li><?= _l('perfex_saas_smart_plans_rule_layout'); ?></li>
                                    <li><?= _l('perfex_saas_smart_plans_rule_blank'); ?></li>
                                    <li><?= _l('perfex_saas_smart_plans_rule_id'); ?></li>
                                    <li><?= _l('perfex_saas_smart_plans_rule_unlimited'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 — Import -->
                    <div class="col-md-4 tw-mb-4">
                        <div class="panel_s tw-h-full">
                            <div class="panel-body tw-flex tw-flex-col tw-h-full">
                                <div class="tw-flex tw-items-center tw-mb-2">
                                    <span class="tw-flex tw-items-center tw-justify-center tw-w-7 tw-h-7 tw-rounded-full tw-bg-success-600 tw-text-white tw-text-sm tw-font-bold tw-mr-2">3</span>
                                    <h5 class="tw-m-0 tw-font-semibold"><?= _l('perfex_saas_smart_plans_step_import'); ?></h5>
                                </div>
                                <?php if ($can_edit) : ?>
                                <p class="tw-text-neutral-500 tw-text-sm tw-flex-1"><?= _l('perfex_saas_smart_plans_step_import_hint'); ?></p>
                                <?= form_open_multipart($import_url, ['id' => 'smart-plans-import-form']); ?>
                                <div class="form-group tw-mb-2">
                                    <input type="file" name="import_file" id="import_file" accept=".xlsx,.csv" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-success" id="smart-plans-import-btn">
                                    <i class="fa fa-upload tw-mr-1"></i><?= _l('perfex_saas_smart_plans_import_update'); ?>
                                </button>
                                <?= form_close(); ?>
                                <?php else : ?>
                                <p class="tw-text-neutral-500 tw-text-sm tw-flex-1 tw-mb-0"><?= _l('perfex_saas_smart_plans_no_edit_permission'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modules & website features instructions -->
                <div class="panel_s">
                    <div class="panel-body">
                        <h5 class="tw-mt-0 tw-mb-1 tw-font-semibold">
                            <i class="fa fa-cubes tw-mr-1 tw-text-primary-600"></i><?= _l('perfex_saas_smart_plans_modules_help_title'); ?>
                        </h5>
                        <p class="tw-text-neutral-500 tw-text-sm tw-mb-3"><?= _l('perfex_saas_smart_plans_modules_help_intro'); ?></p>

                        <div class="row">
                            <div class="col-md-6 tw-mb-3">
                                <div class="tw-rounded tw-border tw-border-neutral-200 tw-p-3 tw-h-full">
                                    <h6 class="tw-mt-0 tw-mb-2 tw-font-semibold tw-text-neutral-700">
                                        <i class="fa fa-check-square-o tw-mr-1 tw-text-success-600"></i><?= _l('perfex_saas_smart_plans_modules_help_enable_title'); ?>
                                    </h6>
                                    <ul class="tw-text-neutral-600 tw-text-sm tw-mb-0 tw-pl-4" style="list-style:disc">
                                        <li><?= _l('perfex_saas_smart_plans_modules_help_enable_1'); ?></li>
                                        <li><?= _l('perfex_saas_smart_plans_modules_help_enable_2'); ?></li>
                                        <li><?= _l('perfex_saas_smart_plans_modules_help_enable_3'); ?></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6 tw-mb-3">
                                <div class="tw-rounded tw-border tw-border-neutral-200 tw-p-3 tw-h-full">
                                    <h6 class="tw-mt-0 tw-mb-2 tw-font-semibold tw-text-neutral-700">
                                        <i class="fa fa-globe tw-mr-1 tw-text-primary-600"></i><?= _l('perfex_saas_smart_plans_modules_help_api_title'); ?>
                                    </h6>
                                    <ul class="tw-text-neutral-600 tw-text-sm tw-mb-0 tw-pl-4" style="list-style:disc">
                                        <li><strong><?= _l('perfex_saas_smart_plans_col_feature_name'); ?>:</strong> <?= _l('perfex_saas_smart_plans_modules_help_name'); ?></li>
                                        <li><strong><?= _l('perfex_saas_smart_plans_col_show'); ?>:</strong> <?= _l('perfex_saas_smart_plans_modules_help_show'); ?></li>
                                        <li><strong><?= _l('perfex_saas_smart_plans_col_order'); ?>:</strong> <?= _l('perfex_saas_smart_plans_modules_help_order'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <p class="tw-text-neutral-500 tw-text-sm tw-mb-3">
                            <i class="fa fa-info-circle tw-mr-1"></i><?= _l('perfex_saas_smart_plans_modules_help_global_note'); ?>
                        </p>

                        <div class="tw-rounded tw-border tw-border-neutral-200 tw-p-3">
                            <h6 class="tw-mt-0 tw-mb-2 tw-font-semibold tw-text-neutral-700">
                                <i class="fa fa-star-o tw-mr-1 tw-text-warning-600"></i><?= _l('perfex_saas_smart_plans_custom_features_help_title'); ?>
                            </h6>
                            <ul class="tw-text-neutral-600 tw-text-sm tw-mb-0 tw-pl-4" style="list-style:disc">
                                <li><?= _l('perfex_saas_smart_plans_custom_features_help_1'); ?></li>
                                <li><?= _l('perfex_saas_smart_plans_custom_features_help_2'); ?></li>
                                <li><?= _l('perfex_saas_smart_plans_custom_features_help_highlight'); ?></li>
                                <li><?= _l('perfex_saas_smart_plans_custom_features_help_3'); ?></li>
                                <li><?= _l('perfex_saas_smart_plans_custom_features_help_4'); ?></li>
                                <li><?= _l('perfex_saas_smart_plans_custom_features_help_5'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Field guide -->
                <div class="panel_s">
                    <div class="panel-body">
                        <h5 class="tw-mt-0 tw-mb-1 tw-font-semibold"><?= _l('perfex_saas_smart_plans_guide_title'); ?></h5>
                        <p class="tw-text-neutral-500 tw-text-sm"><?= _l('perfex_saas_smart_plans_guide_hint'); ?></p>
                        <div class="table-responsive">
                            <table class="table table-bordered tw-mb-0">
                                <thead>
                                    <tr class="tw-bg-neutral-50">
                                        <th style="width:25%"><?= _l('perfex_saas_smart_plans_guide_column'); ?></th>
                                        <th style="width:15%"><?= _l('perfex_saas_smart_plans_guide_editable'); ?></th>
                                        <th><?= _l('perfex_saas_smart_plans_guide_meaning'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($columns as $col) : ?>
                                    <tr>
                                        <td class="tw-font-medium tw-text-neutral-700"><?= e($col['label']); ?></td>
                                        <td>
                                            <?php if (!empty($col['readonly'])) : ?>
                                            <span class="label label-default"><?= _l('perfex_saas_smart_plans_readonly'); ?></span>
                                            <?php else : ?>
                                            <span class="label label-success"><?= _l('perfex_saas_smart_plans_editable'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="tw-text-neutral-600 tw-text-sm"><?= e($col['help']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function () {
        var streamUrl = "<?= $stream_url; ?>";
        var $form     = $('#smart-plans-import-form');
        if (!$form.length) {
            return;
        }

        var L = {
            updated:   "<?= _l('perfex_saas_smart_plans_res_updated'); ?>",
            unchanged: "<?= _l('perfex_saas_smart_plans_res_unchanged'); ?>",
            skipped:   "<?= _l('perfex_saas_smart_plans_res_skipped'); ?>",
            error:     "<?= _l('perfex_saas_smart_plans_res_error'); ?>",
            importing: "<?= _l('perfex_saas_smart_plans_importing'); ?>",
            update:    "<?= addslashes(_l('perfex_saas_smart_plans_import_update')); ?>",
            noChange:  "<?= _l('perfex_saas_smart_plans_no_changes'); ?>",
            failed:    "<?= _l('perfex_saas_smart_plans_stream_failed'); ?>"
        };

        var badge = {
            updated:   'success',
            unchanged: 'default',
            skipped:   'warning',
            error:     'danger'
        };

        // Browsers without streaming fetch fall back to the classic POST + redirect.
        var canStream = !!(window.fetch && window.ReadableStream && window.TextDecoder);

        var counts = { updated: 0, unchanged: 0, skipped: 0, errors: 0 };

        function esc(s) {
            return $('<div>').text(s == null ? '' : String(s)).html();
        }

        function resetLive() {
            counts = { updated: 0, unchanged: 0, skipped: 0, errors: 0 };
            $('#sp-live-updated').text(0);
            $('#sp-live-unchanged').text(0);
            $('#sp-live-skipped').text(0);
            $('#sp-live-errors').text(0);
            $('#sp-live-bar').css('width', '0');
            $('#sp-live-rows').empty();
            $('#sp-live-alert').empty();
            $('#sp-live-phase').text('');
            $('#smart-plans-live').show();
        }

        function renderChanges(line) {
            var changes = line.changes || [];
            if (changes.length) {
                return '<ul class="tw-m-0 tw-pl-4 tw-text-sm tw-text-neutral-600" style="list-style:disc">' +
                    changes.map(function (c) { return '<li>' + esc(c) + '</li>'; }).join('') + '</ul>';
            }
            if (line.note) {
                return '<span class="tw-text-sm tw-text-danger-600">' + esc(line.note) + '</span>';
            }
            return '<span class="tw-text-sm tw-text-neutral-400">' + esc(L.noChange) + '</span>';
        }

        function appendRow(line) {
            var cls  = badge[line.status] || 'default';
            var note = line.note ? '<div class="tw-text-xs tw-text-danger-600 tw-mt-1">' + esc(line.note) + '</div>' : '';
            var html = '<tr>' +
                '<td>' + esc(line.ref || '') + '</td>' +
                '<td>' + esc(line.id || '') + '</td>' +
                '<td>' + esc(line.name || '') + '</td>' +
                '<td><span class="label label-' + cls + '">' + esc(L[line.status] || line.status) + '</span></td>' +
                '<td>' + renderChanges(line) + note + '</td>' +
                '</tr>';
            $('#sp-live-rows').append(html);
        }

        function bumpCount(status) {
            if (status === 'updated')      { counts.updated++;   $('#sp-live-updated').text(counts.updated); }
            else if (status === 'error')   { counts.errors++;    $('#sp-live-errors').text(counts.errors); }
            else if (status === 'skipped') { counts.skipped++;   $('#sp-live-skipped').text(counts.skipped); }
            else                           { counts.unchanged++; $('#sp-live-unchanged').text(counts.unchanged); }
        }

        function setProgress(done, total) {
            var pct = total > 0 ? Math.round((done / total) * 100) : 0;
            $('#sp-live-bar').css('width', pct + '%');
            $('#sp-live-phase').text(done + ' / ' + total);
        }

        function fatal(msg) {
            $('#sp-live-alert').html(
                '<div class="alert alert-danger tw-mb-3"><i class="fa fa-exclamation-triangle tw-mr-1"></i>' + esc(msg) + '</div>'
            );
        }

        function handleEvent(evt) {
            switch (evt.event) {
                case 'phase':
                    $('#sp-live-phase').text(evt.message || '');
                    break;
                case 'start':
                    $('#sp-live-phase').text('0 / ' + evt.total);
                    break;
                case 'module':
                    appendRow(evt.line);
                    break;
                case 'progress':
                    bumpCount(evt.line.status);
                    appendRow(evt.line);
                    setProgress(evt.position, evt.total);
                    break;
                case 'done':
                    $('#sp-live-bar').css('width', '100%');
                    $('#sp-live-phase').text((evt.summary && evt.summary.message) || '');
                    if (evt.summary) {
                        var s = evt.summary;
                        var cls = s.errors > 0 ? 'warning' : 'success';
                        $('#sp-live-alert').html(
                            '<div class="alert alert-' + cls + ' tw-mb-3"><i class="fa fa-check-circle tw-mr-1"></i>' + esc(s.message) + '</div>'
                        );
                    }
                    break;
                case 'fatal':
                    fatal(evt.message);
                    break;
            }
        }

        function resetButton() {
            $('#smart-plans-import-btn').prop('disabled', false)
                .html('<i class="fa fa-upload tw-mr-1"></i>' + L.update);
        }

        $form.on('submit', function (e) {
            if (!confirm("<?= _l('perfex_saas_smart_plans_confirm_import'); ?>")) {
                return false;
            }

            $('#smart-plans-import-btn').prop('disabled', true)
                .html('<i class="fa fa-spinner fa-spin tw-mr-1"></i>' + L.importing);

            if (!canStream) {
                // Let the browser do the classic full-page POST.
                return true;
            }

            e.preventDefault();
            resetLive();
            $('html, body').animate({ scrollTop: $('#smart-plans-live').offset().top - 80 }, 250);

            var data = new FormData(this); // includes the file + CSRF token

            fetch(streamUrl, {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (resp) {
                if (!resp.ok || !resp.body) {
                    throw new Error('HTTP ' + resp.status);
                }
                var reader  = resp.body.getReader();
                var decoder = new TextDecoder('utf-8');
                var buffer  = '';

                function pump() {
                    return reader.read().then(function (res) {
                        if (res.done) {
                            if (buffer.trim() !== '') {
                                try { handleEvent(JSON.parse(buffer)); } catch (err) {}
                            }
                            resetButton();
                            return;
                        }
                        buffer += decoder.decode(res.value, { stream: true });
                        var lines = buffer.split('\n');
                        buffer = lines.pop(); // keep the trailing partial line
                        lines.forEach(function (ln) {
                            ln = ln.trim();
                            if (ln === '') { return; }
                            try { handleEvent(JSON.parse(ln)); } catch (err) {}
                        });
                        return pump();
                    });
                }
                return pump();
            }).catch(function (err) {
                fatal(L.failed + ' (' + (err && err.message ? err.message : err) + ')');
                resetButton();
            });

            return false;
        });
    });
</script>
</body>

</html>
