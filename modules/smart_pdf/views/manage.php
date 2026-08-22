<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .spdf-stats-row { margin-bottom: 15px; }
    .spdf-stat-card {
        background: #fff;
        border: 1px solid #e4e7ea;
        border-radius: 6px;
        padding: 15px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 10px;
    }
    .spdf-stat-icon {
        width: 44px; height: 44px;
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; color: #fff;
        flex-shrink: 0;
    }
    .spdf-stat-icon.spdf-blue { background: #03a9f4; }
    .spdf-stat-icon.spdf-green { background: #84c529; }
    .spdf-stat-icon.spdf-purple { background: #7b1fa2; }
    .spdf-stat-icon.spdf-orange { background: #ff6f00; }
    .spdf-stat-value { font-size: 22px; font-weight: 600; line-height: 1; margin-bottom: 4px; }
    .spdf-stat-label { font-size: 12px; color: #7a8089; text-transform: uppercase; letter-spacing: .4px; }

    /* Toolbar */
    .spdf-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 18px; }
    .spdf-toolbar .spdf-search { flex: 1 1 220px; max-width: 340px; }
    .spdf-filter-chip {
        display: inline-block; padding: 5px 14px; border-radius: 15px;
        border: 1px solid #d3d8dd; background: #fff; color: #55606b;
        font-size: 12px; cursor: pointer; user-select: none;
        transition: all .15s;
    }
    .spdf-filter-chip:hover { border-color: #03a9f4; color: #03a9f4; }
    .spdf-filter-chip.active { background: #03a9f4; border-color: #03a9f4; color: #fff; }

    /* Card grid */
    .spdf-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(255px, 1fr));
        gap: 18px;
    }
    .spdf-card {
        background: #fff;
        border: 1px solid #e4e7ea;
        border-radius: 8px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: box-shadow .18s, transform .18s;
    }
    .spdf-card:hover { box-shadow: 0 8px 24px rgba(16, 42, 67, .12); transform: translateY(-2px); }
    .spdf-card.spdf-card-inactive { opacity: .75; }

    .spdf-thumb {
        position: relative;
        height: 230px;
        overflow: hidden;
        background: #eef1f4;
        border-bottom: 1px solid #e4e7ea;
        cursor: pointer;
    }
    .spdf-thumb iframe {
        position: absolute; top: 0; left: 0;
        border: 0; background: #fff;
        transform-origin: top left;
        pointer-events: none;
    }
    .spdf-thumb-overlay {
        position: absolute; inset: 0;
        display: flex; align-items: center; justify-content: center; gap: 8px;
        background: rgba(13, 42, 67, 0);
        opacity: 0; transition: all .18s;
    }
    .spdf-thumb:hover .spdf-thumb-overlay { opacity: 1; background: rgba(13, 42, 67, .45); }
    .spdf-thumb-overlay .btn { box-shadow: 0 2px 8px rgba(0,0,0,.25); }
    .spdf-status-badge {
        position: absolute; top: 8px; right: 8px; z-index: 2;
        font-size: 10px; padding: 3px 8px; border-radius: 10px; color: #fff;
        text-transform: uppercase; letter-spacing: .4px;
    }
    .spdf-status-badge.spdf-on { background: #84c529; }
    .spdf-status-badge.spdf-off { background: #98a4b0; }
    .spdf-paper-badge {
        position: absolute; top: 8px; left: 8px; z-index: 2;
        font-size: 10px; padding: 3px 8px; border-radius: 10px;
        background: rgba(13, 42, 67, .65); color: #fff;
    }

    .spdf-card-body { padding: 12px 14px 10px; flex: 1; display: flex; flex-direction: column; }
    .spdf-card-title { font-size: 14px; font-weight: 600; margin-bottom: 3px; }
    .spdf-card-title a { color: #29323a; }
    .spdf-card-title a:hover { color: #03a9f4; }
    .spdf-card-desc {
        font-size: 12px; color: #7a8089; margin-bottom: 8px; min-height: 16px;
        overflow: hidden; text-overflow: ellipsis; display: -webkit-box;
        -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    }
    .spdf-card-meta { font-size: 11px; color: #98a4b0; margin-bottom: 10px; margin-top: auto; }
    .spdf-card-meta i { margin-right: 3px; }
    .spdf-card-meta span { margin-right: 10px; white-space: nowrap; }
    .spdf-card-actions { display: flex; gap: 5px; border-top: 1px solid #eef1f4; padding-top: 10px; }
    .spdf-card-actions .btn { flex: 0 0 auto; }
    .spdf-card-actions .spdf-generate-main { flex: 1 1 auto; }

    .spdf-empty {
        text-align: center; padding: 60px 20px; color: #98a4b0;
        background: #fff; border: 1px dashed #d3d8dd; border-radius: 8px;
    }
    .spdf-empty i { font-size: 44px; margin-bottom: 12px; display: block; }

    /* Full preview modal */
    #spdf-card-preview-frame { width: 100%; height: 72vh; border: 1px solid #d3d8dd; border-radius: 4px; background: #fff; }

    /* History: who generated the document */
    .spdf-history-by { white-space: nowrap; }
    .spdf-history-by img {
        width: 24px; height: 24px; border-radius: 50%; object-fit: cover;
        border: 1px solid #e4e7ea; margin-right: 6px; vertical-align: middle;
    }

    /* Patient / employee autofill styles live in _generate_modal.php (shared partial) */
</style>
<div id="wrapper">
    <div class="content">
        <div class="row spdf-stats-row">
            <div class="col-md-3 col-sm-6">
                <div class="spdf-stat-card">
                    <div class="spdf-stat-icon spdf-blue"><i class="fa fa-file-text-o"></i></div>
                    <div>
                        <div class="spdf-stat-value"><?php echo $stats['total']; ?></div>
                        <div class="spdf-stat-label"><?php echo _l('smart_pdf_stat_templates'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="spdf-stat-card">
                    <div class="spdf-stat-icon spdf-green"><i class="fa fa-check-circle-o"></i></div>
                    <div>
                        <div class="spdf-stat-value"><?php echo $stats['active']; ?></div>
                        <div class="spdf-stat-label"><?php echo _l('smart_pdf_stat_active'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="spdf-stat-card">
                    <div class="spdf-stat-icon spdf-purple"><i class="fa fa-files-o"></i></div>
                    <div>
                        <div class="spdf-stat-value"><?php echo $stats['generations']; ?></div>
                        <div class="spdf-stat-label"><?php echo _l('smart_pdf_stat_generated'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="spdf-stat-card">
                    <div class="spdf-stat-icon spdf-orange"><i class="fa fa-calendar-o"></i></div>
                    <div>
                        <div class="spdf-stat-value"><?php echo $stats['generations_month']; ?></div>
                        <div class="spdf-stat-label"><?php echo _l('smart_pdf_stat_generated_month'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="spdf-toolbar">
                    <?php if (has_permission('smart_pdf', '', 'create')) { ?>
                        <a href="<?php echo admin_url('smart_pdf/template'); ?>" class="btn btn-info">
                            <i class="fa fa-plus"></i> <?php echo _l('new_smart_pdf_template'); ?>
                        </a>
                        <a href="<?php echo admin_url('smart_pdf/install_hr_templates'); ?>" class="btn btn-default"
                            onclick="return confirm('<?php echo _l('smart_pdf_install_hr_confirm'); ?>');">
                            <i class="fa fa-download"></i> <?php echo _l('smart_pdf_install_hr'); ?>
                        </a>
                        <?php if (has_permission('smart_pdf', '', 'edit')) { ?>
                            <a href="<?php echo admin_url('smart_pdf/install_hr_templates?overwrite=1'); ?>" class="btn btn-default"
                                onclick="return confirm('<?php echo _l('smart_pdf_reinstall_hr_confirm'); ?>');">
                                <i class="fa fa-refresh"></i> <?php echo _l('smart_pdf_reinstall_hr'); ?>
                            </a>
                        <?php } ?>
                    <?php } ?>
                    <a href="<?php echo admin_url('smart_pdf/branding'); ?>" class="btn btn-default">
                        <i class="fa fa-paint-brush"></i> <?php echo _l('smart_pdf_branding'); ?>
                    </a>
                    <div class="spdf-search">
                        <input type="text" class="form-control" id="spdf-card-search"
                            placeholder="<?php echo _l('smart_pdf_search_placeholder'); ?>">
                    </div>
                    <span class="spdf-filter-chip active" data-filter="all"><?php echo _l('smart_pdf_filter_all'); ?></span>
                    <span class="spdf-filter-chip" data-filter="1"><?php echo _l('smart_pdf_active'); ?></span>
                    <span class="spdf-filter-chip" data-filter="0"><?php echo _l('smart_pdf_inactive'); ?></span>
                </div>

                <?php if (count($templates) == 0) { ?>
                    <div class="spdf-empty">
                        <i class="fa fa-file-pdf-o"></i>
                        <p><?php echo _l('smart_pdf_empty'); ?></p>
                        <?php if (has_permission('smart_pdf', '', 'create')) { ?>
                            <a href="<?php echo admin_url('smart_pdf/template'); ?>" class="btn btn-info">
                                <i class="fa fa-plus"></i> <?php echo _l('new_smart_pdf_template'); ?>
                            </a>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="spdf-grid" id="spdf-grid">
                        <?php foreach ($templates as $t) {
                            $fields = json_decode($t['fields'], true);
                            $tags_count = is_array($fields) ? count($fields) : 0;
                        ?>
                            <div class="spdf-card <?php echo $t['active'] == 1 ? '' : 'spdf-card-inactive'; ?>"
                                data-search="<?php echo html_escape(mb_strtolower($t['name'] . ' ' . $t['description'])); ?>"
                                data-status="<?php echo (int) $t['active']; ?>">
                                <div class="spdf-thumb" data-id="<?php echo $t['id']; ?>">
                                    <span class="spdf-paper-badge"><?php echo html_escape($t['paper_size']); ?> &middot; <?php echo $t['orientation'] == 'L' ? _l('smart_pdf_landscape') : _l('smart_pdf_portrait'); ?></span>
                                    <span class="spdf-status-badge <?php echo $t['active'] == 1 ? 'spdf-on' : 'spdf-off'; ?>">
                                        <?php echo $t['active'] == 1 ? _l('smart_pdf_active') : _l('smart_pdf_inactive'); ?>
                                    </span>
                                    <iframe loading="lazy" src="<?php echo admin_url('smart_pdf/preview/' . $t['id']); ?>" title="<?php echo html_escape($t['name']); ?>"></iframe>
                                    <div class="spdf-thumb-overlay">
                                        <?php if (has_permission('smart_pdf', '', 'generate')) { ?>
                                            <button type="button" class="btn btn-info btn-sm spdf-thumb-generate" data-id="<?php echo $t['id']; ?>">
                                                <i class="fa fa-file-pdf-o"></i> <?php echo _l('smart_pdf_generate'); ?>
                                            </button>
                                        <?php } ?>
                                        <button type="button" class="btn btn-default btn-sm spdf-thumb-zoom" data-id="<?php echo $t['id']; ?>" data-name="<?php echo html_escape($t['name']); ?>">
                                            <i class="fa fa-search-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="spdf-card-body">
                                    <div class="spdf-card-title">
                                        <?php if (has_permission('smart_pdf', '', 'edit')) { ?>
                                            <a href="<?php echo admin_url('smart_pdf/template/' . $t['id']); ?>"><?php echo html_escape($t['name']); ?></a>
                                        <?php } else {
                                            echo html_escape($t['name']);
                                        } ?>
                                    </div>
                                    <div class="spdf-card-desc"><?php echo html_escape($t['description']); ?></div>
                                    <div class="spdf-card-meta">
                                        <span><i class="fa fa-tags"></i><?php echo $tags_count; ?> <?php echo _l('smart_pdf_meta_tags'); ?></span>
                                        <span><i class="fa fa-files-o"></i><span id="spdf-gen-count-<?php echo $t['id']; ?>"><?php echo (int) $t['generations_count']; ?></span> <?php echo _l('smart_pdf_meta_generated'); ?></span>
                                        <span><i class="fa fa-clock-o"></i><?php echo _d($t['created_at']); ?></span>
                                    </div>
                                    <div class="spdf-card-actions">
                                        <?php if (has_permission('smart_pdf', '', 'generate')) { ?>
                                            <button type="button" class="btn btn-info btn-sm spdf-generate-main smart-pdf-generate" data-id="<?php echo $t['id']; ?>">
                                                <i class="fa fa-file-pdf-o"></i> <?php echo _l('smart_pdf_generate'); ?>
                                            </button>
                                        <?php } ?>
                                        <button type="button" class="btn btn-default btn-sm smart-pdf-history" data-id="<?php echo $t['id']; ?>" data-name="<?php echo html_escape($t['name']); ?>" data-toggle="tooltip" title="<?php echo _l('smart_pdf_history'); ?>">
                                            <i class="fa fa-history"></i>
                                        </button>
                                        <?php
                                        $tpl_brand = json_decode($t['branding'] ?? '', true);
                                        $has_override = is_array($tpl_brand) && !empty($tpl_brand['enabled']);
                                        ?>
                                        <a href="<?php echo admin_url('smart_pdf/branding/' . $t['id']); ?>" class="btn btn-<?php echo $has_override ? 'info' : 'default'; ?> btn-sm" data-toggle="tooltip" title="<?php echo $has_override ? _l('smart_pdf_branding_tpl_on') : _l('smart_pdf_personalize'); ?>">
                                            <i class="fa fa-paint-brush"></i>
                                        </a>
                                        <?php if (has_permission('smart_pdf', '', 'edit')) { ?>
                                            <a href="<?php echo admin_url('smart_pdf/template/' . $t['id']); ?>" class="btn btn-default btn-sm" data-toggle="tooltip" title="<?php echo _l('edit'); ?>">
                                                <i class="fa fa-pencil"></i>
                                            </a>
                                        <?php } ?>
                                        <?php if (has_permission('smart_pdf', '', 'create')) { ?>
                                            <a href="<?php echo admin_url('smart_pdf/clone_template/' . $t['id']); ?>" class="btn btn-default btn-sm" data-toggle="tooltip" title="<?php echo _l('smart_pdf_clone'); ?>">
                                                <i class="fa fa-clone"></i>
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="spdf-empty hide" id="spdf-no-results">
                        <i class="fa fa-search"></i>
                        <p><?php echo _l('smart_pdf_no_results'); ?></p>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<!-- Generation history popup -->
<div class="modal fade" id="smart-pdf-history-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <i class="fa fa-history"></i>
                    <span id="smart-pdf-history-title"><?php echo _l('smart_pdf_history'); ?></span>
                </h4>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo _l('smart_pdf_history_doc_no'); ?></th>
                                <th><?php echo _l('smart_pdf_history_when'); ?></th>
                                <th><?php echo _l('smart_pdf_history_by'); ?></th>
                                <th><?php echo _l('smart_pdf_history_patient'); ?></th>
                                <th><?php echo _l('smart_pdf_history_mode'); ?></th>
                                <th class="text-right"><?php echo _l('smart_pdf_dt_options'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="smart-pdf-history-tbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Full-size template preview popup (sample values) -->
<div class="modal fade" id="spdf-card-preview-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-eye"></i> <span id="spdf-card-preview-title"><?php echo _l('smart_pdf_live_preview'); ?></span></h4>
            </div>
            <div class="modal-body">
                <p class="text-muted"><?php echo _l('smart_pdf_live_preview_help'); ?></p>
                <iframe id="spdf-card-preview-frame"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <?php if (has_permission('smart_pdf', '', 'generate')) { ?>
                    <button type="button" class="btn btn-info" id="spdf-card-preview-generate">
                        <i class="fa fa-file-pdf-o"></i> <?php echo _l('smart_pdf_generate'); ?>
                    </button>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<!-- Generate popup: shared partial (loaded AFTER init_tail so jQuery exists;
     it defines window.smartPdfGenerate before the manage script below uses it). -->
<?php $this->load->view('_generate_modal'); ?>
<script>
    $(function () {
        // id => orientation, only needed to scale the live thumbnails
        var spdfThumbMeta = <?php
            $thumb_data = [];
            foreach ($templates as $t) {
                $thumb_data[$t['id']] = $t['orientation'];
            }
            echo json_encode($thumb_data);
        ?>;

        // ------- Live thumbnails (iframes load smart_pdf/preview/<id>) -------
        function layoutThumbs() {
            $('.spdf-thumb').each(function () {
                var orientation = spdfThumbMeta[$(this).data('id')];
                if (!orientation) { return; }
                var iframe = this.querySelector('iframe');
                var baseW = orientation === 'L' ? 1123 : 794;
                var baseH = orientation === 'L' ? 794 : 1123;
                iframe.style.width = baseW + 'px';
                iframe.style.height = baseH + 'px';
                iframe.style.transform = 'scale(' + (this.offsetWidth / baseW) + ')';
            });
        }

        layoutThumbs();

        var resizeTimer = null;
        $(window).on('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(layoutThumbs, 150);
        });

        // Thumbnail click = generate; zoom button = full preview
        $(document).on('click', '.spdf-thumb', function (e) {
            // The overlay buttons have their own handlers
            if ($(e.target).closest('.spdf-thumb-zoom, .spdf-thumb-generate').length) { return; }
            window.smartPdfGenerate($(this).data('id'));
        });

        $(document).on('click', '.spdf-thumb-zoom', function (e) {
            e.stopPropagation();
            var id = $(this).data('id');
            $('#spdf-card-preview-title').text($(this).data('name') || '');
            $('#spdf-card-preview-generate').data('id', id);
            document.getElementById('spdf-card-preview-frame').setAttribute('src', admin_url + 'smart_pdf/preview/' + id);
            $('#spdf-card-preview-modal').modal('show');
        });

        $('#spdf-card-preview-generate').on('click', function () {
            $('#spdf-card-preview-modal').modal('hide');
            window.smartPdfGenerate($(this).data('id'));
        });

        // ------- Search + status filter -------
        function applyFilters() {
            var q = ($('#spdf-card-search').val() || '').toLowerCase().trim();
            var status = $('.spdf-filter-chip.active').data('filter');
            var visible = 0;

            $('#spdf-grid .spdf-card').each(function () {
                var matches = true;
                if (q !== '' && String($(this).data('search')).indexOf(q) === -1) {
                    matches = false;
                }
                if (matches && status !== 'all' && String($(this).data('status')) !== String(status)) {
                    matches = false;
                }
                $(this).toggle(matches);
                if (matches) { visible++; }
            });

            $('#spdf-no-results').toggleClass('hide', visible > 0 || !$('#spdf-grid').length);
        }

        $('#spdf-card-search').on('keyup', applyFilters);
        $('.spdf-filter-chip').on('click', function () {
            $('.spdf-filter-chip').removeClass('active');
            $(this).addClass('active');
            applyFilters();
        });

        // ------- Generate popup (modal + autofill live in _generate_modal.php) -------
        $(document).on('click', '.smart-pdf-generate, .spdf-thumb-generate', function (e) {
            e.preventDefault();
            window.smartPdfGenerate($(this).data('id'));
        });

        // ------- Generation history -------
        $(document).on('click', '.smart-pdf-history', function (e) {
            e.preventDefault();
            var id = $(this).data('id');
            var name = $(this).data('name') || '';

            $.get(admin_url + 'smart_pdf/history/' + id, function (rows) {
                $('#smart-pdf-history-title').text(name !== '' ? name + ' - ' + "<?php echo _l('smart_pdf_history'); ?>" : "<?php echo _l('smart_pdf_history'); ?>");
                var $tbody = $('#smart-pdf-history-tbody').empty();

                if (!rows.length) {
                    $tbody.append('<tr><td colspan="6" class="text-center text-muted"><?php echo _l('smart_pdf_history_empty'); ?></td></tr>');
                }

                $.each(rows, function (i, row) {
                    var $tr = $('<tr></tr>');
                    $tr.append($('<td></td>').text(row.document_no));
                    $tr.append($('<td></td>').text(row.created_at));
                    // Generated by - with the staff member's profile picture
                    var $by = $('<td></td>').addClass('spdf-history-by');
                    if (row.staff_photo) {
                        $by.append($('<img>').attr('src', row.staff_photo).attr('alt', ''));
                    }
                    $by.append($('<span></span>').text(row.staff_name || '-'));
                    $tr.append($by);
                    $tr.append($('<td></td>').text(row.patient_name || row.employee_name || '-'));
                    $tr.append($('<td></td>').append(
                        $('<span class="label"></span>')
                            .addClass(row.mode === 'pdf' ? 'label-info' : 'label-default')
                            .text(row.mode.toUpperCase())
                    ));
                    var $reuse = $('<button type="button" class="btn btn-default btn-xs"><i class="fa fa-refresh"></i> <?php echo _l('smart_pdf_history_reuse'); ?></button>')
                        .on('click', function () {
                            $.get(admin_url + 'smart_pdf/generation/' + row.id, function (generation) {
                                if (!generation.success) { return; }
                                $('#smart-pdf-history-modal').modal('hide');
                                window.smartPdfGenerate(generation.template_id, {
                                    prefillValues: generation.values,
                                    prefillPatientId: generation.patient_id
                                });
                            }, 'json');
                        });
                    $tr.append($('<td class="text-right"></td>').append($reuse));
                    $tbody.append($tr);
                });

                $('#smart-pdf-history-modal').modal('show');
            }, 'json');
        });

        // ------- Auto-open from a deep link (?generate=<id>&employee=<staff_id>) -------
        // Kept for backward compatibility with old links; the modal + submit
        // handling now live in _generate_modal.php.
        (function () {
            if (!window.URLSearchParams) { return; }
            var params = new URLSearchParams(window.location.search);
            var genId = params.get('generate');
            if (genId) {
                window.smartPdfGenerate(genId, { employee: params.get('employee') });
            }
        })();
    });
</script>
</body>

</html>
