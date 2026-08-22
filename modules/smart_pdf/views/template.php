<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .spdf-tag-chip {
        display: inline-block;
        margin: 0 6px 6px 0;
        padding: 4px 10px;
        background: #f0f4f8;
        border: 1px solid #d3dce6;
        border-radius: 12px;
        font-family: Menlo, Consolas, monospace;
        font-size: 11px;
        cursor: pointer;
        transition: background .15s, border-color .15s;
    }
    .spdf-tag-chip:hover { background: #e1ecf7; border-color: #9fc3e8; }
    .spdf-tag-chip.spdf-chip-patient { background: #f0f8f0; border-color: #cde3cd; }
    .spdf-tag-chip.spdf-chip-patient:hover { background: #e0f2e0; border-color: #9fd09f; }
    .spdf-tag-chip.spdf-chip-employee { background: #f3f0fb; border-color: #d8cdec; }
    .spdf-tag-chip.spdf-chip-employee:hover { background: #e9e0f7; border-color: #bfa9e0; }
    .spdf-tag-chip.spdf-chip-agent { background: #fff7e8; border-color: #ecd9b5; }
    .spdf-tag-chip.spdf-chip-agent:hover { background: #fdefd4; border-color: #ddc189; }
    .spdf-chip-group-label { font-size: 11px; text-transform: uppercase; letter-spacing: .4px; color: #7a8089; margin: 8px 0 6px; }
    .spdf-ignored-chip { background: #fbeeee; border-color: #e6c9c9; color: #a94442; }
    .spdf-ignored-chip:hover { background: #f7e0e0; border-color: #d9a9a9; }
    .spdf-ignored-chip .fa { margin-left: 5px; opacity: .7; }
    #spdf-preview-frame { width: 100%; height: 70vh; border: 1px solid #d3d8dd; border-radius: 4px; background: #fff; }
    .spdf-move-btn { padding: 1px 5px; }
    /* Merged duplicate tags: one input serves every spelling */
    .spdf-variant-badge {
        display: inline-block; margin-left: 5px; padding: 1px 7px; border-radius: 9px;
        background: #eaf4ea; border: 1px solid #cde3cd; color: #4b7a4b;
        font-size: 10px; white-space: nowrap; cursor: help;
    }
    .spdf-occurrence-badge {
        display: inline-block; margin-left: 5px; padding: 1px 6px; border-radius: 9px;
        background: #f0f4f8; border: 1px solid #d3dce6; color: #7a8089;
        font-size: 10px; cursor: help;
    }
    .spdf-variant-list {
        margin-top: 4px; font-family: Menlo, Consolas, monospace;
        font-size: 10px; color: #8b949c; word-break: break-all;
    }
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <?php echo form_open($this->uri->uri_string(), ['id' => 'smart-pdf-template-form']); ?>
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo $title; ?>
                            <button type="button" class="btn btn-default btn-xs pull-right" id="smart-pdf-preview">
                                <i class="fa fa-eye"></i> <?php echo _l('smart_pdf_live_preview'); ?>
                            </button>
                        </h4>
                        <hr class="hr-panel-heading" />

                        <?php echo render_input('name', 'smart_pdf_name', isset($template) ? $template->name : '', 'text', ['required' => true]); ?>
                        <?php echo render_input('description', 'smart_pdf_description', isset($template) ? $template->description : ''); ?>

                        <div class="form-group">
                            <label><?php echo _l('smart_pdf_import_html'); ?></label>
                            <input type="file" id="smart-pdf-html-file" accept=".html,.htm,text/html" class="form-control" />
                            <small class="text-muted"><?php echo _l('smart_pdf_import_html_help'); ?></small>
                        </div>

                        <div class="form-group">
                            <label for="content"><?php echo _l('smart_pdf_content'); ?> <small class="req text-danger">*</small></label>
                            <textarea name="content" id="content" rows="18" class="form-control" required
                                style="font-family: Menlo, Consolas, monospace; font-size: 12px;"><?php echo isset($template) ? html_escape($template->content) : ''; ?></textarea>
                            <small class="text-muted"><?php echo _l('smart_pdf_content_help'); ?></small>
                        </div>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo _l('smart_pdf_tags'); ?>
                            <button type="button" class="btn btn-default btn-xs pull-right mleft5" id="smart-pdf-detect-tags">
                                <i class="fa fa-refresh"></i> <?php echo _l('smart_pdf_detect_tags'); ?>
                            </button>
                            <button type="button" class="btn btn-success btn-xs pull-right" id="smart-pdf-add-tag">
                                <i class="fa fa-plus"></i> <?php echo _l('smart_pdf_add_tag'); ?>
                            </button>
                        </h4>
                        <hr class="hr-panel-heading" />
                        <p class="text-muted"><?php echo _l('smart_pdf_tags_help'); ?></p>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="smart-pdf-tags-table">
                                <thead>
                                    <tr>
                                        <th width="17%"><?php echo _l('smart_pdf_tag'); ?></th>
                                        <th width="19%"><?php echo _l('smart_pdf_tag_label'); ?></th>
                                        <th width="12%"><?php echo _l('smart_pdf_tag_type'); ?></th>
                                        <th width="17%"><?php echo _l('smart_pdf_tag_options'); ?></th>
                                        <th width="14%"><?php echo _l('smart_pdf_tag_default'); ?></th>
                                        <th width="7%" class="text-center"><?php echo _l('smart_pdf_tag_required'); ?></th>
                                        <th width="14%" class="text-center"><?php echo _l('smart_pdf_tag_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="smart-pdf-tags-tbody">
                                    <tr class="smart-pdf-no-tags">
                                        <td colspan="7" class="text-center text-muted"><?php echo _l('smart_pdf_no_tags'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="smart-pdf-ignored-wrap" class="mtop15 hide">
                            <div class="spdf-chip-group-label"><i class="fa fa-ban"></i> <?php echo _l('smart_pdf_ignored_tags'); ?></div>
                            <p class="text-muted" style="font-size:11px;"><?php echo _l('smart_pdf_ignored_tags_help'); ?></p>
                            <div id="smart-pdf-ignored-list"></div>
                        </div>
                        <input type="hidden" name="fields_json" id="fields_json" value="" />
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo _l('smart_pdf_output_settings'); ?></h4>
                        <hr class="hr-panel-heading" />

                        <?php
                        $paper_sizes = ['A4', 'A5', 'A3', 'LETTER', 'LEGAL'];
                        $selected_paper = isset($template) ? $template->paper_size : 'A4';
                        ?>
                        <div class="form-group">
                            <label for="paper_size"><?php echo _l('smart_pdf_paper_size'); ?></label>
                            <select name="paper_size" id="paper_size" class="form-control selectpicker">
                                <?php foreach ($paper_sizes as $size) { ?>
                                    <option value="<?php echo $size; ?>" <?php echo $selected_paper == $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <?php $selected_orientation = isset($template) ? $template->orientation : 'P'; ?>
                        <div class="form-group">
                            <label for="orientation"><?php echo _l('smart_pdf_orientation'); ?></label>
                            <select name="orientation" id="orientation" class="form-control selectpicker">
                                <option value="P" <?php echo $selected_orientation == 'P' ? 'selected' : ''; ?>><?php echo _l('smart_pdf_portrait'); ?></option>
                                <option value="L" <?php echo $selected_orientation == 'L' ? 'selected' : ''; ?>><?php echo _l('smart_pdf_landscape'); ?></option>
                            </select>
                        </div>

                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="active" id="active" value="1" <?php echo (!isset($template) || $template->active == 1) ? 'checked' : ''; ?> />
                            <label for="active"><?php echo _l('smart_pdf_active'); ?></label>
                        </div>

                        <hr />
                        <button type="submit" class="btn btn-info btn-block"><?php echo _l('submit'); ?></button>
                        <a href="<?php echo admin_url('smart_pdf'); ?>" class="btn btn-default btn-block"><?php echo _l('go_back'); ?></a>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><i class="fa fa-magic"></i> <?php echo _l('smart_pdf_smart_tags'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <p class="text-muted"><?php echo _l('smart_pdf_smart_tags_help'); ?></p>

                        <div class="spdf-chip-group-label"><?php echo _l('smart_pdf_smart_tags_system'); ?></div>
                        <?php foreach ($system_tags as $tag) { ?>
                            <span class="spdf-tag-chip" data-tag="<?php echo $tag; ?>">{{<?php echo $tag; ?>}}</span>
                        <?php } ?>
                        <p class="text-muted mtop5" style="font-size:11px;"><?php echo _l('smart_pdf_smart_tags_system_note'); ?></p>

                        <div class="spdf-chip-group-label"><?php echo _l('smart_pdf_smart_tags_agent'); ?></div>
                        <?php foreach ($agent_tags as $tag) { ?>
                            <span class="spdf-tag-chip spdf-chip-agent" data-tag="<?php echo $tag; ?>">{{<?php echo $tag; ?>}}</span>
                        <?php } ?>
                        <p class="text-muted mtop5" style="font-size:11px;"><?php echo _l('smart_pdf_smart_tags_agent_note'); ?></p>

                        <div class="spdf-chip-group-label"><?php echo _l('smart_pdf_smart_tags_patient'); ?></div>
                        <?php foreach ($patient_tags as $tag) { ?>
                            <span class="spdf-tag-chip spdf-chip-patient" data-tag="<?php echo $tag; ?>">{{<?php echo $tag; ?>}}</span>
                        <?php } ?>
                        <p class="text-muted mtop5" style="font-size:11px;"><?php echo _l('smart_pdf_smart_tags_patient_note'); ?></p>

                        <div class="spdf-chip-group-label"><?php echo _l('smart_pdf_smart_tags_employee'); ?></div>
                        <?php foreach ($employee_tags as $tag) { ?>
                            <span class="spdf-tag-chip spdf-chip-employee" data-tag="<?php echo $tag; ?>">{{<?php echo $tag; ?>}}</span>
                        <?php } ?>
                        <p class="text-muted mtop5" style="font-size:11px;"><?php echo _l('smart_pdf_smart_tags_employee_note'); ?></p>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><i class="fa fa-question-circle"></i> <?php echo _l('smart_pdf_how_it_works'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <ol style="padding-left:18px;">
                            <li><?php echo _l('smart_pdf_how_step_1'); ?></li>
                            <li><?php echo _l('smart_pdf_how_step_2'); ?></li>
                            <li><?php echo _l('smart_pdf_how_step_3'); ?></li>
                            <li><?php echo _l('smart_pdf_how_step_4'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>

<!-- Live preview popup (client-side, sample values) -->
<div class="modal fade" id="smart-pdf-preview-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-eye"></i> <?php echo _l('smart_pdf_live_preview'); ?></h4>
            </div>
            <div class="modal-body">
                <p class="text-muted"><?php echo _l('smart_pdf_live_preview_help'); ?></p>
                <iframe id="spdf-preview-frame" name="spdf-preview-frame"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Posts the unsaved editor content to the server-side preview, rendered
     into the modal iframe so template scripts/fonts run outside the admin CSP -->
<?php echo form_open(admin_url('smart_pdf/preview_draft'), ['id' => 'spdf-preview-form', 'target' => 'spdf-preview-frame', 'class' => 'hide']); ?>
<input type="hidden" name="name" id="spdf-preview-name">
<input type="hidden" name="paper_size" id="spdf-preview-paper">
<input type="hidden" name="orientation" id="spdf-preview-orientation">
<input type="hidden" name="content" id="spdf-preview-content">
<input type="hidden" name="fields_json" id="spdf-preview-fields">
<?php echo form_close(); ?>

<?php init_tail(); ?>
<script>
    $(function () {
        var existingFields = <?php echo json_encode(isset($fields) ? $fields : []); ?>;
        var systemTags = <?php echo json_encode($system_tags); ?>;
        var patientTags = <?php echo json_encode($patient_tags); ?>;
        var employeeTags = <?php echo json_encode($employee_tags); ?>;
        var fieldTypes = ['text', 'textarea', 'number', 'date', 'time', 'email', 'select'];

        // ---- Tag identity (mirrors Smart_pdf_model::tag_key) ----
        // The same placeholder gets spelled in different ways across a design
        // ({{doctor_name}} / {{Doctor Name}} / {{DOCTOR-NAME}}). They all share
        // one canonical key, so they share ONE row here and ONE input at
        // generate time - and the merge fills every occurrence of every
        // spelling from that single value.
        function tagKey(tag) {
            return String(tag == null ? '' : tag).toLowerCase().replace(/[^a-z0-9]/g, '');
        }

        var systemKeys = {};
        $.each(systemTags, function (i, tag) { systemKeys[tagKey(tag)] = true; });

        // key => official name, so a variant snaps to the real smart tag and
        // keeps its patient / employee auto-fill behaviour.
        var canonicalTags = {};
        $.each([].concat(systemTags, patientTags, employeeTags), function (i, tag) {
            canonicalTags[tagKey(tag)] = tag;
        });

        // Tags the user has dismissed from the detected list. Kept as full
        // field configs (keyed by canonical key) so they survive save/reload
        // and can be restored, and so re-detection does not re-add them.
        var ignoredTags = {};
        $.each(existingFields, function (i, field) {
            if (field && field.ignored == 1) {
                ignoredTags[tagKey(field.tag)] = field;
            }
        });

        function humanize(tag) {
            return tag.replace(/[_-]+/g, ' ').replace(/\w\S*/g, function (word) {
                return word.charAt(0).toUpperCase() + word.substr(1);
            });
        }

        // Detected tags grouped by canonical key: one entry per real value,
        // carrying the other spellings found in the HTML and how many times
        // the group appears in the document.
        function extractGroups(content) {
            var re = /\{\{\s*([A-Za-z0-9_][A-Za-z0-9_\- ]*?)\s*\}\}/g;
            var groups = [];
            var byKey = {};
            var match;
            while ((match = re.exec(content)) !== null) {
                var raw = match[1];
                var key = tagKey(raw);
                if (key === '' || systemKeys[key] || ignoredTags.hasOwnProperty(key)) {
                    continue;
                }
                if (!byKey[key]) {
                    byKey[key] = {
                        key: key,
                        tag: canonicalTags[key] || raw,
                        aliases: [],
                        count: 0
                    };
                    groups.push(byKey[key]);
                }
                byKey[key].count++;
                if (raw !== byKey[key].tag && byKey[key].aliases.indexOf(raw) === -1) {
                    byKey[key].aliases.push(raw);
                }
            }
            return groups;
        }

        var TAG_RE = /^[A-Za-z0-9_][A-Za-z0-9_\- ]*$/;

        // Read the current rows so re-detection keeps whatever the user
        // already configured (including the row order) for tags that still exist.
        function collectFields() {
            var fields = [];
            $('#smart-pdf-tags-tbody tr.smart-pdf-tag-row').each(function () {
                var $row = $(this);
                var manual = $row.data('manual') == 1;
                // attr(), not data(): jQuery coerces data-tag values that look
                // like literals ({{true}}, {{123}}) into booleans/numbers, which
                // would change the tag's identity when it is saved.
                var tag = manual ? ($row.find('.field-tag-input').val() || '').trim() : String($row.attr('data-tag') || '');
                if (tag === '' || !TAG_RE.test(tag)) {
                    return; // skip unnamed / invalid manual rows
                }
                fields.push({
                    tag: tag,
                    label: $row.find('.field-label').val(),
                    type: $row.find('.field-type').val(),
                    options: $row.find('.field-options').val(),
                    default: $row.find('.field-default').val(),
                    required: $row.find('.field-required').is(':checked') ? 1 : 0,
                    manual: manual ? 1 : 0,
                    aliases: $row.data('aliases') || [],
                    occurrences: $row.data('occurrences') || 0
                });
            });
            // Persist dismissed tags so they stay hidden after save/reload.
            $.each(ignoredTags, function (key, field) {
                var entry = $.extend({}, field);
                entry.ignored = 1;
                entry.manual = 0;
                fields.push(entry);
            });
            return fields;
        }

        function insertIntoContent(text) {
            var textarea = document.getElementById('content');
            var start = textarea.selectionStart || 0;
            var value = textarea.value;
            textarea.value = value.substring(0, start) + text + value.substring(textarea.selectionEnd || start);
            textarea.selectionStart = textarea.selectionEnd = start + text.length;
            textarea.focus();
        }

        function buildRow(field) {
            var manual = field.manual == 1;
            var aliases = field.aliases || [];
            var $row = $('<tr class="smart-pdf-tag-row"></tr>')
                .attr('data-tag', field.tag)
                .attr('data-manual', manual ? 1 : 0)
                .data('aliases', aliases)
                .data('occurrences', field.occurrences || 0);

            if (manual) {
                $row.append($('<td></td>').append(
                    $('<input type="text" class="form-control input-sm field-tag-input">')
                        .val(field.tag)
                        .attr('placeholder', "<?php echo _l('smart_pdf_add_tag_placeholder'); ?>")
                ));
            } else {
                var $tagCell = $('<td></td>').append($('<code></code>').text('{{' + field.tag + '}}'));

                // Badges first (they belong on the tag line), then the list of
                // the other spellings this one input fills.
                var variantList = aliases.length
                    ? $.map(aliases, function (a) { return '{{' + a + '}}'; }).join('  ') : '';

                if (aliases.length) {
                    $tagCell.append(
                        $('<span class="spdf-variant-badge"></span>')
                            .attr('title', "<?php echo _l('smart_pdf_tag_variants_title'); ?> " + variantList)
                            .html('<i class="fa fa-compress"></i> ' + aliases.length + ' <?php echo _l('smart_pdf_tag_variants'); ?>')
                    );
                }

                if (field.occurrences > 1) {
                    $tagCell.append(
                        $('<span class="spdf-occurrence-badge"></span>')
                            .attr('title', "<?php echo _l('smart_pdf_tag_occurrences'); ?>")
                            .text('x' + field.occurrences)
                    );
                }

                if (variantList !== '') {
                    $tagCell.append($('<div class="spdf-variant-list"></div>').text(variantList));
                }

                $row.append($tagCell);
            }
            $row.append($('<td></td>').append(
                $('<input type="text" class="form-control input-sm field-label">').val(field.label)
            ));

            var $type = $('<select class="form-control input-sm field-type"></select>');
            $.each(fieldTypes, function (i, type) {
                $type.append($('<option></option>').attr('value', type).text(type));
            });
            $type.val(fieldTypes.indexOf(field.type) !== -1 ? field.type : 'text');
            $row.append($('<td></td>').append($type));

            var $options = $('<input type="text" class="form-control input-sm field-options">')
                .val(field.options)
                .attr('placeholder', "<?php echo _l('smart_pdf_tag_options_placeholder'); ?>");
            $row.append($('<td></td>').append($options));

            $row.append($('<td></td>').append(
                $('<input type="text" class="form-control input-sm field-default">').val(field.default)
            ));
            $row.append($('<td class="text-center"></td>').append(
                $('<input type="checkbox" class="field-required" value="1">').prop('checked', field.required == 1)
            ));

            var $actions = $('<td class="text-center"></td>');

            // Re-order: the row order defines the input order in the
            // generate popup.
            $actions.append(
                $('<button type="button" class="btn btn-default btn-xs spdf-move-btn" title="<?php echo _l('smart_pdf_move_up'); ?>"><i class="fa fa-chevron-up"></i></button>')
                    .on('click', function () {
                        var $prev = $row.prev('tr.smart-pdf-tag-row');
                        if ($prev.length) { $row.insertBefore($prev); }
                    })
            );
            $actions.append(' ');
            $actions.append(
                $('<button type="button" class="btn btn-default btn-xs spdf-move-btn" title="<?php echo _l('smart_pdf_move_down'); ?>"><i class="fa fa-chevron-down"></i></button>')
                    .on('click', function () {
                        var $next = $row.next('tr.smart-pdf-tag-row');
                        if ($next.length) { $row.insertAfter($next); }
                    })
            );

            if (!manual) {
                // Detected tag: let the user dismiss template artifacts
                // (e.g. {{true}}, {{showCost}}) so they are not asked at
                // generate time. Kept in ignoredTags so re-detection and
                // save/reload will not bring them back.
                $actions.append(' ');
                $actions.append(
                    $('<button type="button" class="btn btn-danger btn-xs" data-toggle="tooltip" title="<?php echo _l('smart_pdf_ignore_tag'); ?>"><i class="fa fa-ban"></i></button>')
                        .on('click', function () {
                            var cfg = collectFields();
                            var stored = null;
                            $.each(cfg, function (i, f) { if (tagKey(f.tag) === tagKey(field.tag)) { stored = f; } });
                            ignoredTags[tagKey(field.tag)] = stored || {
                                tag: field.tag, label: field.label, type: field.type,
                                options: field.options, default: field.default, required: field.required
                            };
                            $row.remove();
                            renderIgnored();
                            if (!$('#smart-pdf-tags-tbody tr.smart-pdf-tag-row').length) {
                                $('#smart-pdf-tags-tbody').append(
                                    '<tr class="smart-pdf-no-tags"><td colspan="7" class="text-center text-muted"><?php echo _l('smart_pdf_no_tags'); ?></td></tr>'
                                );
                            }
                        })
                );
            }

            if (manual) {
                $actions.append(' ');
                $actions.append(
                    $('<button type="button" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?php echo _l('smart_pdf_insert_tag'); ?>"><i class="fa fa-arrow-circle-up"></i></button>')
                        .on('click', function () {
                            var tag = ($row.find('.field-tag-input').val() || '').trim();
                            if (tag === '' || !TAG_RE.test(tag)) {
                                alert_float('warning', "<?php echo _l('smart_pdf_invalid_tag'); ?>");
                                return;
                            }
                            insertIntoContent('{{' + tag + '}}');
                        })
                );
                $actions.append(' ');
                $actions.append(
                    $('<button type="button" class="btn btn-danger btn-xs" data-toggle="tooltip" title="<?php echo _l('smart_pdf_remove_tag'); ?>"><i class="fa fa-remove"></i></button>')
                        .on('click', function () {
                            $row.remove();
                            if (!$('#smart-pdf-tags-tbody tr.smart-pdf-tag-row').length) {
                                $('#smart-pdf-tags-tbody').append(
                                    '<tr class="smart-pdf-no-tags"><td colspan="7" class="text-center text-muted"><?php echo _l('smart_pdf_no_tags'); ?></td></tr>'
                                );
                            }
                        })
                );
            }
            $row.append($actions);

            function toggleOptions() {
                $options.prop('disabled', $type.val() !== 'select');
            }
            $type.on('change', toggleOptions);
            toggleOptions();

            return $row;
        }

        // Render the dismissed-tags strip; clicking a chip restores the tag
        // (re-detection then re-adds it as a normal row).
        function renderIgnored() {
            var $list = $('#smart-pdf-ignored-list').empty();
            var keys = Object.keys(ignoredTags);
            if (!keys.length) {
                $('#smart-pdf-ignored-wrap').addClass('hide');
                return;
            }
            $('#smart-pdf-ignored-wrap').removeClass('hide');
            $.each(keys, function (i, key) {
                $('<span class="spdf-tag-chip spdf-ignored-chip" title="<?php echo _l('smart_pdf_restore_tag'); ?>"></span>')
                    .text('{{' + (ignoredTags[key].tag || key) + '}}')
                    .append($('<i class="fa fa-undo"></i>'))
                    .on('click', function () {
                        delete ignoredTags[key];
                        renderIgnored();
                        renderTags();
                    })
                    .appendTo($list);
            });
        }

        var tagsRendered = false;

        function renderTags() {
            var groups = extractGroups($('#content').val());
            var groupByKey = {};
            $.each(groups, function (i, group) { groupByKey[group.key] = group; });

            // Current rows (or the stored config on first render) define the
            // order; newly detected tags are appended at the end.
            var current = collectFields();
            if (!current.length && !tagsRendered) {
                current = existingFields.slice();
            }
            tagsRendered = true;

            var rows = [];
            var used = {};

            $.each(current, function (i, field) {
                var key = tagKey(field.tag);
                if (key === '' || used[key] || field.ignored == 1) { return; }
                if (groupByKey[key]) {
                    // Keep the configuration, adopt the group's primary name
                    // and refresh the merged-variant info from the HTML.
                    field.manual = 0;
                    field.tag = groupByKey[key].tag;
                    field.aliases = groupByKey[key].aliases;
                    field.occurrences = groupByKey[key].count;
                    rows.push(field);
                    used[key] = true;
                } else if (field.manual == 1) {
                    // Manual tags stay in the table even when not found in
                    // the HTML; once detected they become normal rows.
                    field.aliases = [];
                    field.occurrences = 0;
                    rows.push(field);
                    used[key] = true;
                }
            });

            $.each(groups, function (i, group) {
                if (!used[group.key]) {
                    rows.push({
                        tag: group.tag, label: humanize(group.tag), type: 'text', options: '', default: '',
                        required: 0, manual: 0, aliases: group.aliases, occurrences: group.count
                    });
                }
            });

            var $tbody = $('#smart-pdf-tags-tbody').empty();

            if (!rows.length) {
                $tbody.append(
                    '<tr class="smart-pdf-no-tags"><td colspan="7" class="text-center text-muted"><?php echo _l('smart_pdf_no_tags'); ?></td></tr>'
                );
                renderIgnored();
                return;
            }

            $.each(rows, function (i, field) {
                $tbody.append(buildRow(field));
            });

            renderIgnored();
        }

        // Import HTML file (read client-side, no upload needed)
        $('#smart-pdf-html-file').on('change', function () {
            var file = this.files[0];
            if (!file) { return; }
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#content').val(e.target.result);
                renderTags();
                alert_float('success', "<?php echo _l('smart_pdf_import_done'); ?>");
            };
            reader.readAsText(file);
            $(this).val('');
        });

        $('#smart-pdf-detect-tags').on('click', renderTags);
        $('#content').on('blur', renderTags);

        // Smart tag chips: click to insert at the cursor position
        $('.spdf-tag-chip').on('click', function () {
            insertIntoContent('{{' + $(this).data('tag') + '}}');
            renderTags();
        });

        // Manually add a tag the detector could not find (or one you plan
        // to place in the HTML afterwards via the insert button).
        $('#smart-pdf-add-tag').on('click', function () {
            $('#smart-pdf-tags-tbody tr.smart-pdf-no-tags').remove();
            var $row = buildRow({
                tag: '', label: '', type: 'text', options: '', default: '', required: 0, manual: 1
            });
            $('#smart-pdf-tags-tbody').append($row);
            $row.find('.field-tag-input').focus();
        });

        // Auto-fill the label from the tag name for manual rows
        $(document).on('blur', '.field-tag-input', function () {
            var $row = $(this).closest('tr');
            var tag = ($(this).val() || '').trim();
            if (tag !== '' && $row.find('.field-label').val().trim() === '') {
                $row.find('.field-label').val(humanize(tag));
            }
        });

        // Live preview: POST the unsaved content to the server-side sample
        // preview, rendered into the modal iframe (scripts/fonts work there).
        $('#smart-pdf-preview').on('click', function () {
            var content = $('#content').val();
            if (content.trim() === '') {
                alert_float('warning', "<?php echo _l('smart_pdf_preview_empty'); ?>");
                return;
            }

            $('#spdf-preview-name').val($('#name').val());
            $('#spdf-preview-paper').val($('#paper_size').val());
            $('#spdf-preview-orientation').val($('#orientation').val());
            $('#spdf-preview-content').val(content);
            $('#spdf-preview-fields').val(JSON.stringify(collectFields()));
            $('#spdf-preview-form').submit();

            $('#smart-pdf-preview-modal').modal('show');
        });

        $('#smart-pdf-template-form').on('submit', function () {
            $('#fields_json').val(JSON.stringify(collectFields()));
        });

        // Edit mode: show configured tags immediately
        if ($('#content').val().trim() !== '' || existingFields.length) {
            renderTags();
        }
    });
</script>
</body>

</html>
