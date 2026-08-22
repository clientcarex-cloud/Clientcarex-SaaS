<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Shared "Generate document" popup for Smart PDF.
 *
 * Self-contained: markup + styles + JS. Include it on any admin page and call
 * the exposed global to open the fill-in popup straight away (no redirect):
 *
 *     window.smartPdfGenerate(templateId, {
 *         employee: staffId,        // pre-select + autofill an HR employee
 *         patient:  clientId,       // pre-select + autofill a patient
 *         prefillValues: {tag:val}, // seed tag inputs (history reuse)
 *         prefillPatientId: id
 *     });
 *
 * The AJAX endpoints (get_fields / employee_tags / patient_tags / generate)
 * live in the Smart PDF controller, so the popup works identically wherever
 * it is embedded. Guarded so it is only rendered once per page.
 */
if (defined('SMART_PDF_GENERATE_MODAL_LOADED')) {
    return;
}
define('SMART_PDF_GENERATE_MODAL_LOADED', true);
$spdf_can_generate = has_permission('smart_pdf', '', 'generate') || is_admin();

// The document is stamped with whoever generates it ({{staff_name}},
// {{staff_photo}} / {{staff_photo_url}}), so the popup shows that identity.
$CI_spdf_modal = &get_instance();
$CI_spdf_modal->load->model('smart_pdf/smart_pdf_model');
$spdf_staff_photo = $CI_spdf_modal->smart_pdf_model->staff_photo_url(get_staff_user_id());
$spdf_staff_name  = get_staff_full_name(get_staff_user_id());
?>
<style>
    /* Patient autofill (generate popup) */
    #spdf-patient-results {
        position: absolute; z-index: 1060; left: 0; right: 0;
        background: #fff; border: 1px solid #d3d8dd; border-radius: 4px;
        max-height: 220px; overflow-y: auto;
        box-shadow: 0 4px 12px rgba(0,0,0,.12);
        display: none;
    }
    #spdf-patient-results .spdf-patient-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f2f4; }
    #spdf-patient-results .spdf-patient-item:last-child { border-bottom: 0; }
    #spdf-patient-results .spdf-patient-item:hover { background: #f4f8fb; }
    #spdf-patient-results .spdf-patient-meta { font-size: 11px; color: #7a8089; }
    .spdf-patient-box { position: relative; background: #f7f9fb; border: 1px dashed #c9d2da; border-radius: 6px; padding: 12px; margin-bottom: 15px; }
    .spdf-selected-patient { margin-top: 8px; }

    /* Employee autofill (generate popup) - mirrors the patient box */
    #spdf-employee-results {
        position: absolute; z-index: 1060; left: 0; right: 0;
        background: #fff; border: 1px solid #d3d8dd; border-radius: 4px;
        max-height: 220px; overflow-y: auto;
        box-shadow: 0 4px 12px rgba(0,0,0,.12);
        display: none;
    }
    #spdf-employee-results .spdf-employee-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f2f4; }
    #spdf-employee-results .spdf-employee-item:last-child { border-bottom: 0; }
    #spdf-employee-results .spdf-employee-item:hover { background: #f6f4fb; }
    #spdf-employee-results .spdf-employee-meta { font-size: 11px; color: #7a8089; }
    .spdf-employee-box { position: relative; background: #f8f7fb; border: 1px dashed #d3cde6; border-radius: 6px; padding: 12px; margin-bottom: 15px; }
    .spdf-selected-employee { margin-top: 8px; }

    /* Who is generating - the identity stamped onto the document */
    .spdf-generated-by { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; font-size: 12px; color: #7a8089; }
    .spdf-generated-by img { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid #e4e7ea; }
    .spdf-generated-by .spdf-gen-initial {
        width: 28px; height: 28px; border-radius: 50%; background: #03a9f4; color: #fff;
        display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;
    }
    .spdf-generated-by b { color: #55606b; font-weight: 600; }

    /* Fields pre-filled from the agent / hospital details */
    .spdf-autofilled { border-color: #84c529; background: #fbfff5; }
    .spdf-auto-badge {
        font-size: 10px; color: #5c8a1e; background: #eef7e2; border: 1px solid #cfe6ae;
        border-radius: 9px; padding: 1px 6px; margin-left: 4px; font-weight: 400;
    }
</style>

<!-- Generate popup: user fills tag values, then previews or prints -->
<div class="modal fade" id="smart-pdf-generate-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <?php echo form_open(admin_url('smart_pdf/generate'), ['id' => 'smart-pdf-generate-form', 'target' => '_blank']); ?>
        <input type="hidden" name="template_id" id="smart-pdf-template-id" value="">
        <input type="hidden" name="mode" id="smart-pdf-mode" value="print">
        <input type="hidden" name="patient_id" id="smart-pdf-patient-id" value="">
        <input type="hidden" name="employee_id" id="smart-pdf-employee-id" value="">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <i class="fa fa-file-pdf-o"></i>
                    <span id="smart-pdf-modal-title"><?php echo _l('smart_pdf_fill_details'); ?></span>
                </h4>
            </div>
            <div class="modal-body">
                <p class="text-muted" id="smart-pdf-modal-help"><?php echo _l('smart_pdf_fill_details_help'); ?></p>

                <div class="spdf-generated-by">
                    <?php if ($spdf_staff_photo !== '') { ?>
                        <img src="<?php echo $spdf_staff_photo; ?>" alt="">
                    <?php } else { ?>
                        <span class="spdf-gen-initial"><?php echo html_escape(mb_substr($spdf_staff_name, 0, 1)); ?></span>
                    <?php } ?>
                    <span><?php echo _l('smart_pdf_generated_by_you'); ?> <b><?php echo html_escape($spdf_staff_name); ?></b></span>
                </div>

                <div class="spdf-patient-box hide" id="spdf-patient-box">
                    <label class="control-label"><i class="fa fa-user-md"></i> <?php echo _l('smart_pdf_patient_autofill'); ?></label>
                    <input type="text" class="form-control" id="spdf-patient-search"
                        placeholder="<?php echo _l('smart_pdf_patient_search_placeholder'); ?>" autocomplete="off">
                    <div id="spdf-patient-results"></div>
                    <div class="spdf-selected-patient hide" id="spdf-selected-patient">
                        <span class="label label-success" id="spdf-selected-patient-name"></span>
                        <a href="#" id="spdf-clear-patient" class="text-danger mleft5"><i class="fa fa-times"></i></a>
                    </div>
                </div>

                <div class="spdf-employee-box hide" id="spdf-employee-box">
                    <label class="control-label"><i class="fa fa-id-card-o"></i> <?php echo _l('smart_pdf_employee_autofill'); ?></label>
                    <input type="text" class="form-control" id="spdf-employee-search"
                        placeholder="<?php echo _l('smart_pdf_employee_search_placeholder'); ?>" autocomplete="off">
                    <div id="spdf-employee-results"></div>
                    <div class="spdf-selected-employee hide" id="spdf-selected-employee">
                        <span class="label label-success" id="spdf-selected-employee-name"></span>
                        <a href="#" id="spdf-clear-employee" class="text-danger mleft5"><i class="fa fa-times"></i></a>
                    </div>
                </div>

                <div id="smart-pdf-fields" class="row"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default pull-left" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-default" id="smart-pdf-preview-btn">
                    <i class="fa fa-eye"></i> <?php echo _l('smart_pdf_preview'); ?>
                </button>
                <button type="submit" class="btn btn-info" id="smart-pdf-print-btn">
                    <i class="fa fa-print"></i> <?php echo _l('smart_pdf_print'); ?>
                </button>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>
</div>

<script>
    $(function () {
        var spdfCanGenerate = <?php echo $spdf_can_generate ? 'true' : 'false'; ?>;
        var currentTemplateId = null;

        // Tag identity (mirrors Smart_pdf_model::tag_key): a template may spell
        // a tag any way it likes ({{Patient Name}} / {{patient_name}}); the
        // auto-fill matches on the canonical key so it works either way.
        function spdfTagKey(tag) {
            return String(tag == null ? '' : tag).toLowerCase().replace(/[^a-z0-9]/g, '');
        }

        // Push a tag => value map into the open popup's inputs. Empty values
        // never overwrite what is already there.
        function spdfFillTagInputs(values) {
            var byKey = {};
            $.each(values, function (tag, value) { byKey[spdfTagKey(tag)] = value; });

            $('.spdf-tag-input').each(function () {
                // attr(), not data(): jQuery coerces literal-looking values.
                var key = spdfTagKey($(this).attr('data-tag'));
                if (byKey.hasOwnProperty(key) && byKey[key] !== '') {
                    $(this).val(byKey[key]);
                }
            });
        }

        // ------- Open the generate popup (exposed globally) -------
        // opts: { employee, patient, prefillValues, prefillPatientId }
        window.smartPdfGenerate = function (id, opts) {
            if (!spdfCanGenerate || !id) { return; }
            opts = opts || {};
            $.get(admin_url + 'smart_pdf/get_fields/' + id, function (response) {
                if (!response.success) {
                    alert_float('danger', response.message || 'Error');
                    return;
                }

                currentTemplateId = response.id;
                $('#smart-pdf-template-id').val(response.id);
                $('#smart-pdf-modal-title').text(response.name);
                $('#smart-pdf-patient-id').val(opts.prefillPatientId || opts.patient || '');

                // Patient autofill box only when the template uses patient_* tags
                $('#spdf-patient-box').toggleClass('hide', !response.has_patient_tags);
                $('#spdf-patient-search').val('');
                $('#spdf-patient-results').hide().empty();
                $('#spdf-selected-patient').addClass('hide');

                // Employee autofill box only when the template uses employee_* tags
                $('#spdf-employee-box').toggleClass('hide', !response.has_employee_tags);
                $('#spdf-employee-search').val('');
                $('#spdf-employee-results').hide().empty();
                $('#spdf-selected-employee').addClass('hide');
                $('#smart-pdf-employee-id').val('');

                var $container = $('#smart-pdf-fields').empty();

                // Reused history values are keyed by whatever spelling the
                // template used back then - match them by canonical key.
                var prefillByKey = {};
                if (opts.prefillValues) {
                    $.each(opts.prefillValues, function (tag, value) { prefillByKey[spdfTagKey(tag)] = value; });
                }

                if (!response.fields.length) {
                    $container.append(
                        $('<p class="text-muted col-md-12"></p>').text("<?php echo _l('smart_pdf_no_tags_generate'); ?>")
                    );
                }

                $.each(response.fields, function (i, field) {
                    var $col = $('<div></div>').addClass(field.type === 'textarea' ? 'col-md-12' : 'col-md-6');
                    var $group = $('<div class="form-group"></div>');
                    var inputId = 'smart-pdf-tag-' + i;
                    var inputName = 'tags[' + field.tag + ']';

                    var $label = $('<label></label>').attr('for', inputId).text(field.label);
                    if (field.required == 1) {
                        $label.append(' <small class="req text-danger">*</small>');
                    }
                    $group.append($label);

                    // Value priority: reused history value > server auto-fill
                    // (agent / hospital details) > the template's default.
                    var fieldKey = spdfTagKey(field.tag);
                    var auto = false;
                    var value;
                    if (prefillByKey.hasOwnProperty(fieldKey)) {
                        value = prefillByKey[fieldKey];
                    } else if (field.auto_value) {
                        value = field.auto_value;
                        auto = true;
                    } else {
                        value = field.default;
                    }

                    var $input;
                    if (field.type === 'textarea') {
                        $input = $('<textarea class="form-control" rows="3"></textarea>').val(value);
                    } else if (field.type === 'select') {
                        $input = $('<select class="form-control"></select>');
                        if (field.required != 1) {
                            $input.append($('<option value=""></option>'));
                        }
                        $.each((field.options || '').split(','), function (j, option) {
                            option = option.trim();
                            if (option !== '') {
                                $input.append($('<option></option>').attr('value', option).text(option));
                            }
                        });
                        if (value) {
                            $input.val(value);
                        }
                    } else {
                        var types = ['number', 'date', 'time', 'email'];
                        var type = types.indexOf(field.type) !== -1 ? field.type : 'text';
                        $input = $('<input class="form-control">').attr('type', type).val(value);
                    }

                    $input.attr({ id: inputId, name: inputName }).addClass('spdf-tag-input').attr('data-tag', field.tag);
                    if (field.required == 1) {
                        $input.attr('required', true);
                    }

                    // Flag it as auto-filled only when the value actually stuck
                    // (a select with no matching option, or a date/number input
                    // fed free text, silently rejects it).
                    if (auto && String($input.val()) !== String(value)) {
                        $input.val(field.default);
                        auto = false;
                    }
                    if (auto) {
                        $input.addClass('spdf-autofilled')
                            .attr('title', "<?php echo _l('smart_pdf_autofilled'); ?>");
                        $label.append(' <span class="spdf-auto-badge"><i class="fa fa-magic"></i> <?php echo _l('smart_pdf_autofilled_badge'); ?></span>');
                    }

                    $group.append($input);
                    $col.append($group);
                    $container.append($col);
                });

                // Pre-select an employee (e.g. opened from the HR employee page)
                // and auto-fill all employee_* fields.
                if (opts.employee && response.has_employee_tags) {
                    selectEmployee(opts.employee);
                }
                // Pre-select a patient and auto-fill all patient_* fields.
                if (opts.patient && response.has_patient_tags) {
                    selectPatient(opts.patient);
                }

                $('#smart-pdf-generate-modal').modal('show');
            }, 'json');
        };

        // ------- Patient autofill -------
        function selectPatient(patientId, label) {
            $('#smart-pdf-patient-id').val(patientId);
            $.get(admin_url + 'smart_pdf/patient_tags/' + patientId, function (values) {
                spdfFillTagInputs(values);
                var name = label || values.patient_name || ('Patient #' + patientId);
                $('#spdf-selected-patient-name').text(name);
                $('#spdf-selected-patient').removeClass('hide');
                $('#spdf-patient-search').val('');
                $('#spdf-patient-results').hide().empty();
            }, 'json');
        }

        var searchTimer = null;
        $('#spdf-patient-search').on('keyup', function () {
            var q = $(this).val().trim();
            clearTimeout(searchTimer);
            if (q.length < 2) {
                $('#spdf-patient-results').hide().empty();
                return;
            }
            searchTimer = setTimeout(function () {
                $.post(admin_url + 'smart_pdf/search_patient', { q: q }, function (patients) {
                    var $results = $('#spdf-patient-results').empty();
                    if (!patients.length) {
                        $results.append('<div class="spdf-patient-item text-muted"><?php echo _l('smart_pdf_patient_no_results'); ?></div>');
                    }
                    $.each(patients, function (i, p) {
                        var meta = [];
                        if (p.mr_number) { meta.push('MR: ' + p.mr_number); }
                        if (p.phonenumber) { meta.push(p.phonenumber); }
                        if (p.age) { meta.push(p.age); }
                        if (p.gender) { meta.push(p.gender); }
                        var $item = $('<div class="spdf-patient-item"></div>')
                            .append($('<div></div>').text(p.name))
                            .append($('<div class="spdf-patient-meta"></div>').text(meta.join(' | ')))
                            .data('patient', p);
                        $results.append($item);
                    });
                    $results.show();
                }, 'json');
            }, 300);
        });

        $(document).on('click', '#spdf-patient-results .spdf-patient-item', function () {
            var patient = $(this).data('patient');
            if (!patient) { return; }
            selectPatient(patient.userid, patient.name + (patient.mr_number ? ' (' + patient.mr_number + ')' : ''));
        });

        $('#spdf-clear-patient').on('click', function (e) {
            e.preventDefault();
            $('#smart-pdf-patient-id').val('');
            $('#spdf-selected-patient').addClass('hide');
        });

        // Hide the results dropdown when clicking elsewhere
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#spdf-patient-box').length) {
                $('#spdf-patient-results').hide();
            }
        });

        // ------- Employee autofill (HR) -------
        // Fill every employee_* field from one staff member. Shared by the
        // search-result click and the auto-open-from-HR flow.
        function selectEmployee(staffId) {
            $('#smart-pdf-employee-id').val(staffId);
            $.get(admin_url + 'smart_pdf/employee_tags/' + staffId, function (values) {
                spdfFillTagInputs(values);
                var label = values.employee_name || '';
                if (values.employee_code) { label += ' (' + values.employee_code + ')'; }
                $('#spdf-selected-employee-name').text(label || 'Employee #' + staffId);
                $('#spdf-selected-employee').removeClass('hide');
                $('#spdf-employee-search').val('');
                $('#spdf-employee-results').hide().empty();
            }, 'json');
        }

        var empSearchTimer = null;
        $('#spdf-employee-search').on('keyup', function () {
            var q = $(this).val().trim();
            clearTimeout(empSearchTimer);
            if (q.length < 2) {
                $('#spdf-employee-results').hide().empty();
                return;
            }
            empSearchTimer = setTimeout(function () {
                $.post(admin_url + 'smart_pdf/search_employee', { q: q }, function (employees) {
                    var $results = $('#spdf-employee-results').empty();
                    if (!employees.length) {
                        $results.append('<div class="spdf-employee-item text-muted"><?php echo _l('smart_pdf_employee_no_results'); ?></div>');
                    }
                    $.each(employees, function (i, emp) {
                        var meta = [];
                        if (emp.employee_code) { meta.push(emp.employee_code); }
                        if (emp.designation) { meta.push(emp.designation); }
                        if (emp.email) { meta.push(emp.email); }
                        var $item = $('<div class="spdf-employee-item"></div>')
                            .append($('<div></div>').text(emp.name))
                            .append($('<div class="spdf-employee-meta"></div>').text(meta.join(' | ')))
                            .data('staffid', emp.staffid);
                        $results.append($item);
                    });
                    $results.show();
                }, 'json');
            }, 300);
        });

        $(document).on('click', '#spdf-employee-results .spdf-employee-item', function () {
            var staffId = $(this).data('staffid');
            if (!staffId) { return; }
            selectEmployee(staffId);
        });

        $('#spdf-clear-employee').on('click', function (e) {
            e.preventDefault();
            $('#smart-pdf-employee-id').val('');
            $('#spdf-selected-employee').addClass('hide');
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#spdf-employee-box').length) {
                $('#spdf-employee-results').hide();
            }
        });

        // Submit buttons - same form, different output mode
        $('#smart-pdf-preview-btn').on('click', function () {
            $('#smart-pdf-mode').val('preview');
        });
        $('#smart-pdf-print-btn').on('click', function () {
            $('#smart-pdf-mode').val('print');
        });

        // The form targets a new tab; keep the popup open for Preview so the
        // user can adjust values and generate the final document afterwards.
        $('#smart-pdf-generate-form').on('submit', function () {
            if ($('#smart-pdf-mode').val() !== 'preview') {
                var $count = $('#spdf-gen-count-' + currentTemplateId);
                if ($count.length) {
                    $count.text(parseInt($count.text(), 10) + 1);
                }
                setTimeout(function () {
                    $('#smart-pdf-generate-modal').modal('hide');
                }, 300);
            }
        });
    });
</script>
