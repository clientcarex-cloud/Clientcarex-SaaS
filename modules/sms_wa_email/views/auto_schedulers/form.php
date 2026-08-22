<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
    $is_edit = ($scheduler !== null);
    $edit_time_slots = [];
    $edit_channel = '';
    $edit_template_id = '';
    $edit_filters = [];
    if ($is_edit) {
        $edit_time_slots = json_decode($scheduler->time_slots, true);
        if (!is_array($edit_time_slots)) $edit_time_slots = [];
        $edit_channel = $scheduler->channel;
        $edit_template_id = $scheduler->template_id;
        $edit_filters = json_decode($scheduler->filters_json, true);
        if (!is_array($edit_filters)) $edit_filters = [];
    }
?>

<style>
    .ccx-comm-page { font-family: 'Inter', sans-serif; }
    .ccx-page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
    .ccx-page-header-icon { width: 48px; height: 48px; background: linear-gradient(135deg, #8b5cf6, #6d28d9); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; box-shadow: 0 4px 14px rgba(139, 92, 246, 0.3); }
    .ccx-page-header h4 { margin: 0; font-weight: 700; font-size: 22px; color: #1e1b4b; letter-spacing: -0.3px; }
    .ccx-main-card { background: #ffffff; border-radius: 16px; border: 1px solid rgba(229, 231, 235, 0.7); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04), 0 10px 30px -5px rgba(0,0,0,0.06); padding: 24px; }
    .target-preview { background: #f5f3ff; border: 1px solid #ddd6fe; padding: 16px; border-radius: 12px; margin-top: 24px; text-align: center; }
    .target-count { font-size: 32px; font-weight: 700; color: #5b21b6; line-height: 1; margin-bottom: 5px; }
    .tpl-preview-box { background: linear-gradient(135deg, #f8fafc, #f1f5f9); border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-top: 16px; display: none; }
    .tpl-preview-box .tpl-preview-header { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; font-weight: 700; font-size: 14px; color: #475569; }
    .tpl-preview-box .tpl-preview-header i { color: #8b5cf6; }
    .tpl-preview-content { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; font-size: 13.5px; line-height: 1.7; color: #1e293b; white-space: pre-wrap; word-wrap: break-word; }
    .tpl-preview-content .tpl-var { background: #ede9fe; color: #6d28d9; padding: 1px 6px; border-radius: 4px; font-weight: 600; font-size: 12px; }

    /* Time Slots Builder */
    .time-slots-builder { background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
    .time-slots-builder .slot-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
    .time-slots-builder .slot-row:last-child { margin-bottom: 0; }
    .time-slots-builder .slot-row input[type="time"] {
        padding: 8px 12px; border: 2px solid #d8b4fe; border-radius: 8px; font-size: 14px; font-weight: 600;
        background: #fff; color: #1e1b4b; outline: none; transition: border-color 0.2s;
    }
    .time-slots-builder .slot-row input[type="time"]:focus { border-color: #8b5cf6; }
    .time-slots-builder .btn-remove-slot { width: 32px; height: 32px; border-radius: 8px; border: none; background: #fee2e2; color: #dc2626; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
    .time-slots-builder .btn-remove-slot:hover { background: #fecaca; transform: scale(1.05); }
    .btn-add-slot { padding: 8px 16px; border-radius: 8px; border: 2px dashed #d8b4fe; background: transparent; color: #7c3aed; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.2s; }
    .btn-add-slot:hover { background: #f5f3ff; border-color: #8b5cf6; }
    .slot-label { font-size: 12px; color: #7c3aed; font-weight: 600; min-width: 70px; }
</style>

<div id="wrapper">
    <div class="content ccx-comm-page">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="ccx-page-header">
                    <a href="<?php echo admin_url('sms_wa_email/auto_schedulers'); ?>" class="btn btn-default" style="border-radius: 10px; padding: 12px 16px;"><i class="fa fa-arrow-left"></i></a>
                    <div class="ccx-page-header-icon">
                        <i class="fa fa-refresh"></i>
                    </div>
                    <div>
                        <h4><?php echo $is_edit ? 'Edit Auto Scheduler' : 'New Auto Scheduler'; ?></h4>
                        <p>Configure a daily recurring message schedule with time-based cycles</p>
                    </div>
                </div>

                <div class="ccx-main-card panel_s">
                    <div class="panel-body">
                        <?php
                            $form_url = $is_edit
                                ? admin_url('sms_wa_email/auto_schedulers/edit/' . $scheduler->id)
                                : admin_url('sms_wa_email/auto_schedulers/create');
                            echo form_open($form_url, ['id' => 'auto-scheduler-form']);
                        ?>

                        <div class="row">
                            <div class="col-md-12">
                                <?php echo render_input('name', 'Scheduler Name', $is_edit ? $scheduler->name : '', 'text', ['required' => true]); ?>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="channel">Sending Channel</label>
                                    <select name="channel" id="channel" class="selectpicker" data-width="100%" required>
                                        <?php $first = true; foreach($active_channels as $ch_key => $ch_label): ?>
                                            <option value="<?php echo $ch_key; ?>" <?php if($is_edit && $edit_channel == $ch_key) echo 'selected'; elseif(!$is_edit && $first) { echo 'selected'; $first = false; } ?>><?php echo $ch_label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Active</label>
                                    <select name="is_active" class="selectpicker" data-width="100%">
                                        <option value="1" <?php if(!$is_edit || $scheduler->is_active == 1) echo 'selected'; ?>>Active</option>
                                        <option value="0" <?php if($is_edit && $scheduler->is_active == 0) echo 'selected'; ?>>Paused</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <?php $is_first_channel = true; foreach($active_channels as $ch_key => $ch_label): ?>
                                <div class="form-group template-wrapper" id="wrapper-<?php echo $ch_key; ?>" style="<?php echo !$is_first_channel ? 'display:none;' : ''; ?>">
                                    <label><?php echo $ch_label; ?> Template</label>
                                    <select name="template_id_<?php echo $ch_key; ?>" class="selectpicker template-select" data-width="100%" data-channel="<?php echo $ch_key; ?>">
                                        <option value="">-- Select Template --</option>
                                        <?php if (isset($channel_templates[$ch_key])): foreach($channel_templates[$ch_key] as $t): ?>
                                            <option value="<?php echo $t['id']; ?>" <?php if($is_edit && $edit_template_id == $t['id']) echo 'selected'; ?>><?php echo htmlspecialchars($t['title']); ?></option>
                                        <?php endforeach; endif; ?>
                                    </select>
                                </div>
                                <?php $is_first_channel = false; endforeach; ?>
                                <input type="hidden" name="template_id" id="template_id" required>
                            </div>
                        </div>

                        <!-- Template Preview -->
                        <div class="tpl-preview-box" id="tpl-preview-box">
                            <div class="tpl-preview-header"><i class="fa fa-eye"></i> Template Preview</div>
                            <div class="tpl-preview-content" id="tpl-raw-content"></div>
                        </div>

                        <hr>
                        <h4 style="margin-bottom: 16px;"><i class="fa fa-clock-o" style="color: #8b5cf6;"></i> Time Cycles</h4>
                        <p style="font-size: 13px; color: #6b7280; margin-bottom: 12px;">
                            Add the times at which messages should be sent daily. At each cycle, only <strong>new patients</strong> (not yet messaged today) will receive the message.
                        </p>

                        <div class="time-slots-builder" id="time-slots-builder">
                            <?php if ($is_edit && !empty($edit_time_slots)): ?>
                                <?php foreach ($edit_time_slots as $i => $slot): ?>
                                <div class="slot-row">
                                    <span class="slot-label">Cycle <?php echo ($i + 1); ?></span>
                                    <input type="time" name="time_slots[]" value="<?php echo htmlspecialchars($slot); ?>" required>
                                    <button type="button" class="btn-remove-slot" onclick="removeSlot(this)"><i class="fa fa-times"></i></button>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="slot-row">
                                    <span class="slot-label">Cycle 1</span>
                                    <input type="time" name="time_slots[]" value="10:00" required>
                                    <button type="button" class="btn-remove-slot" onclick="removeSlot(this)"><i class="fa fa-times"></i></button>
                                </div>
                                <div class="slot-row">
                                    <span class="slot-label">Cycle 2</span>
                                    <input type="time" name="time_slots[]" value="13:00" required>
                                    <button type="button" class="btn-remove-slot" onclick="removeSlot(this)"><i class="fa fa-times"></i></button>
                                </div>
                                <div class="slot-row">
                                    <span class="slot-label">Cycle 3</span>
                                    <input type="time" name="time_slots[]" value="15:00" required>
                                    <button type="button" class="btn-remove-slot" onclick="removeSlot(this)"><i class="fa fa-times"></i></button>
                                </div>
                                <div class="slot-row">
                                    <span class="slot-label">Cycle 4</span>
                                    <input type="time" name="time_slots[]" value="20:00" required>
                                    <button type="button" class="btn-remove-slot" onclick="removeSlot(this)"><i class="fa fa-times"></i></button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn-add-slot" onclick="addSlot()"><i class="fa fa-plus"></i> Add Time Cycle</button>

                        <hr>
                        <h4 style="margin-bottom: 20px;"><i class="fa fa-filter"></i> Target Audience Filters</h4>

                        <!-- Row 1: Patient Status, Gender, Age Range -->
                        <div class="row" style="margin-bottom: 12px;">
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Patient Status</label>
                                    <select name="filters[status]" class="selectpicker filter-input" data-width="100%">
                                        <option value="1" <?php if(!$is_edit || (isset($edit_filters['status']) && $edit_filters['status'] == '1')) echo 'selected'; ?>>Active Only</option>
                                        <option value="0" <?php if($is_edit && isset($edit_filters['status']) && $edit_filters['status'] == '0') echo 'selected'; ?>>Inactive Only</option>
                                        <option value="" <?php if($is_edit && isset($edit_filters['status']) && $edit_filters['status'] === '') echo 'selected'; ?>>All Patients</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Gender</label>
                                    <select name="filters[gender]" class="selectpicker filter-input" data-width="100%">
                                        <option value="">All</option>
                                        <option value="Male" <?php if($is_edit && isset($edit_filters['gender']) && $edit_filters['gender'] == 'Male') echo 'selected'; ?>>Male</option>
                                        <option value="Female" <?php if($is_edit && isset($edit_filters['gender']) && $edit_filters['gender'] == 'Female') echo 'selected'; ?>>Female</option>
                                        <option value="Other" <?php if($is_edit && isset($edit_filters['gender']) && $edit_filters['gender'] == 'Other') echo 'selected'; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Age From</label>
                                    <input type="number" name="filters[age_from]" class="form-control filter-input" placeholder="Min" min="0" value="<?php echo $is_edit && isset($edit_filters['age_from']) ? $edit_filters['age_from'] : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Age To</label>
                                    <input type="number" name="filters[age_to]" class="form-control filter-input" placeholder="Max" min="0" value="<?php echo $is_edit && isset($edit_filters['age_to']) ? $edit_filters['age_to'] : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Registration Date Range + Visit Date Range -->
                        <div class="row" style="margin-bottom: 12px;">
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Registered From</label>
                                    <div class="input-group date">
                                        <input type="text" name="filters[registered_from]" class="form-control datepicker filter-input" value="<?php echo $is_edit && isset($edit_filters['registered_from']) ? $edit_filters['registered_from'] : ''; ?>">
                                        <div class="input-group-addon"><i class="fa fa-calendar calendar-icon"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Registered To</label>
                                    <div class="input-group date">
                                        <input type="text" name="filters[registered_to]" class="form-control datepicker filter-input" value="<?php echo $is_edit && isset($edit_filters['registered_to']) ? $edit_filters['registered_to'] : ''; ?>">
                                        <div class="input-group-addon"><i class="fa fa-calendar calendar-icon"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Visit Date From</label>
                                    <div class="input-group date">
                                        <input type="text" name="filters[visit_date_from]" class="form-control datepicker filter-input" value="<?php echo $is_edit && isset($edit_filters['visit_date_from']) ? $edit_filters['visit_date_from'] : ''; ?>">
                                        <div class="input-group-addon"><i class="fa fa-calendar calendar-icon"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Visit Date To</label>
                                    <div class="input-group date">
                                        <input type="text" name="filters[visit_date_to]" class="form-control datepicker filter-input" value="<?php echo $is_edit && isset($edit_filters['visit_date_to']) ? $edit_filters['visit_date_to'] : ''; ?>">
                                        <div class="input-group-addon"><i class="fa fa-calendar calendar-icon"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: Visit Count + Items Multi-Select -->
                        <div class="row" style="margin-bottom: 12px;">
                            <div class="col-md-2">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Min Visits</label>
                                    <input type="number" name="filters[visits_min]" class="form-control filter-input" placeholder="0" min="0" value="<?php echo $is_edit && isset($edit_filters['visits_min']) ? $edit_filters['visits_min'] : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Max Visits</label>
                                    <input type="number" name="filters[visits_max]" class="form-control filter-input" placeholder="∞" min="0" value="<?php echo $is_edit && isset($edit_filters['visits_max']) ? $edit_filters['visits_max'] : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Item / Test</label>
                                    <select name="filters[items][]" class="selectpicker filter-input" data-width="100%" data-live-search="true" data-actions-box="true" multiple data-none-selected-text="-- All Items --">
                                        <?php
                                        $current_group = '';
                                        foreach ($items_list as $item):
                                            $grp = $item['group_name'] ? $item['group_name'] : 'Ungrouped';
                                            if ($grp !== $current_group):
                                                if ($current_group !== '') echo '</optgroup>';
                                                $current_group = $grp;
                                                echo '<optgroup label="' . htmlspecialchars($current_group) . '">';
                                            endif;
                                            $sel = ($is_edit && isset($edit_filters['items']) && in_array($item['id'], $edit_filters['items'])) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $item['id']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($item['description']); ?></option>
                                        <?php endforeach; ?>
                                        <?php if ($current_group !== '') echo '</optgroup>'; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Row 4: Item Status + Payment Status + Apply -->
                        <div class="row" style="align-items: flex-end; display: flex; flex-wrap: wrap;">
                            <div class="col-md-4">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Item Status</label>
                                    <select name="filters[item_statuses][]" class="selectpicker filter-input" data-width="100%" data-live-search="true" data-actions-box="true" multiple data-none-selected-text="-- All Statuses --">
                                        <?php foreach ($item_statuses as $st): ?>
                                            <?php $sel = ($is_edit && isset($edit_filters['item_statuses']) && in_array($st['name'], $edit_filters['item_statuses'])) ? 'selected' : ''; ?>
                                            <option value="<?php echo htmlspecialchars($st['name']); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($st['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Payment Status</label>
                                    <select name="filters[payment_statuses][]" class="selectpicker filter-input" data-width="100%" data-actions-box="true" multiple data-none-selected-text="-- All --">
                                        <?php
                                        $pay_opts = ['1' => 'Unpaid', '2' => 'Paid', '3' => 'Partially Paid', '4' => 'Overdue', '5' => 'Cancelled'];
                                        foreach ($pay_opts as $pk => $pv):
                                            $sel = ($is_edit && isset($edit_filters['payment_statuses']) && in_array($pk, $edit_filters['payment_statuses'])) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $pk; ?>" <?php echo $sel; ?>><?php echo $pv; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-primary btn-block" id="btn-apply-filters" style="padding: 10px 16px; font-weight: 600;">
                                    <i class="fa fa-filter"></i> Apply Filters
                                </button>
                            </div>
                        </div>

                        <div class="target-preview">
                            <div class="target-count" id="target-count-display">0</div>
                            <div style="color: #5b21b6; font-weight: 500; margin-bottom: 8px;">Patients match these filters</div>
                            <div style="display: flex; gap: 8px; justify-content: center; margin-bottom: 12px;">
                                <button type="button" class="btn btn-sm btn-warning" id="btn-uncheck-invalid-phones"><i class="fa fa-phone"></i> Uncheck Invalid Numbers</button>
                                <button type="button" class="btn btn-sm btn-danger" id="btn-uncheck-invalid-emails" style="display:none;"><i class="fa fa-envelope"></i> Uncheck Invalid Emails</button>
                            </div>
                            <div id="target-patients-list" style="max-height: 250px; overflow-y: auto; text-align: left; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px;">
                            </div>
                            <div id="excluded-patients-container"></div>
                        </div>

                        <hr>
                        <div class="text-right" style="display: flex; justify-content: flex-end; gap: 12px;">
                            <a href="<?php echo admin_url('sms_wa_email/auto_schedulers'); ?>" class="btn btn-lg btn-default" style="font-weight: 700; padding: 12px 28px; border-radius: 10px;">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-lg" style="font-weight: 700; padding: 12px 28px; border-radius: 10px; background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: #fff; border: none;">
                                <i class="fa fa-check"></i> <?php echo $is_edit ? 'Update Scheduler' : 'Create Scheduler'; ?>
                            </button>
                        </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
    $(function(){
        appValidateForm($('#auto-scheduler-form'), {
            name: 'required',
            channel: 'required',
            template_id: 'required'
        });

        // ── Time Slots Builder ──
        window.addSlot = function() {
            var count = $('#time-slots-builder .slot-row').length + 1;
            var html = '<div class="slot-row">';
            html += '<span class="slot-label">Cycle ' + count + '</span>';
            html += '<input type="time" name="time_slots[]" value="" required>';
            html += '<button type="button" class="btn-remove-slot" onclick="removeSlot(this)"><i class="fa fa-times"></i></button>';
            html += '</div>';
            $('#time-slots-builder').append(html);
            renumberSlots();
        };

        window.removeSlot = function(btn) {
            if ($('#time-slots-builder .slot-row').length <= 1) {
                alert('At least one time cycle is required.');
                return;
            }
            $(btn).closest('.slot-row').remove();
            renumberSlots();
        };

        function renumberSlots() {
            $('#time-slots-builder .slot-row').each(function(i) {
                $(this).find('.slot-label').text('Cycle ' + (i + 1));
            });
        }

        // ── Channel / Template Switching ──
        $('#channel').on('change', function(){
            var val = $(this).val();
            $('.template-wrapper').hide();
            $('#wrapper-' + val).show();
            updateRealTemplateId();
            loadTemplatePreview();
            // Show/hide email validation button based on channel
            if (val === 'email') {
                $('#btn-uncheck-invalid-emails').show();
            } else {
                $('#btn-uncheck-invalid-emails').hide();
            }
        });

        $('select.template-select').on('change', function(){
            updateRealTemplateId();
            loadTemplatePreview();
        });

        function updateRealTemplateId() {
            var channel = $('#channel').val();
            var id = $('select[name="template_id_' + channel + '"]').val();
            $('#template_id').val(id || '');
        }

        // Show correct channel wrapper on edit
        <?php if ($is_edit): ?>
        var editCh = '<?php echo $edit_channel; ?>';
        if (editCh) {
            $('.template-wrapper').hide();
            $('#wrapper-' + editCh).show();
            $('#channel').selectpicker('val', editCh);
        }
        <?php endif; ?>

        // ── Template Preview ──
        var cachedTemplates = {};
        function loadTemplatePreview() {
            var tid = $('#template_id').val();
            if (!tid || tid === '') {
                $('#tpl-preview-box').slideUp(200);
                return;
            }
            if (cachedTemplates[tid]) {
                renderPreview(cachedTemplates[tid]);
                return;
            }
            $('#tpl-raw-content').html('<i class="fa fa-spinner fa-spin"></i> Loading...');
            $('#tpl-preview-box').slideDown(200);

            var postData = { template_id: tid };
            if (typeof csrfData !== 'undefined') {
                postData[csrfData.token_name] = csrfData.hash;
            }
            $.post(admin_url + 'sms_wa_email/auto_schedulers/get_template_content', postData, function(res) {
                try {
                    var data = JSON.parse(res);
                    if (data.success) {
                        cachedTemplates[tid] = data;
                        renderPreview(data);
                    } else {
                        $('#tpl-raw-content').html('<span class="text-danger">Template not found</span>');
                    }
                } catch(e) {
                    $('#tpl-raw-content').html('<span class="text-danger">Error loading template</span>');
                }
            });
        }

        function renderPreview(data) {
            var content = data.content || '';
            var rawHtml = content.replace(/\{([^}]+)\}/g, '<span class="tpl-var">{$1}</span>');
            $('#tpl-raw-content').html(rawHtml);
            $('#tpl-preview-box').slideDown(200);
        }

        updateRealTemplateId();
        loadTemplatePreview();

        // Time Ago helper
        function timeAgo(dateStr) {
            var now = new Date();
            var date = new Date(dateStr);
            var diffMs = now - date;
            var diffSec = Math.floor(diffMs / 1000);
            var diffMin = Math.floor(diffSec / 60);
            var diffHr  = Math.floor(diffMin / 60);
            var diffDay = Math.floor(diffHr / 24);
            var diffMon = Math.floor(diffDay / 30);
            var diffYr  = Math.floor(diffDay / 365);
            if (diffYr > 0) return diffYr + (diffYr === 1 ? ' year' : ' years') + ' ago';
            if (diffMon > 0) return diffMon + (diffMon === 1 ? ' month' : ' months') + ' ago';
            if (diffDay > 0) return diffDay + (diffDay === 1 ? ' day' : ' days') + ' ago';
            if (diffHr > 0) return diffHr + (diffHr === 1 ? ' hour' : ' hours') + ' ago';
            if (diffMin > 0) return diffMin + (diffMin === 1 ? ' min' : ' mins') + ' ago';
            return 'Just now';
        }

        // ── Patient Count ──
        var countRequest = null;
        function loadPatientCount() {
            if (countRequest) countRequest.abort();
            $('#target-count-display').html('<i class="fa fa-spinner fa-spin"></i>');
            $('#target-patients-list').html('<div class="text-center" style="padding: 20px;"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');

            var formData = $('#auto-scheduler-form').find('.filter-input').serialize();
            if (typeof csrfData !== 'undefined') {
                formData += '&' + csrfData.token_name + '=' + encodeURIComponent(csrfData.hash);
            }

            countRequest = $.post(admin_url + 'sms_wa_email/auto_schedulers/fetch_filtered_patients', formData, function(res) {
                var response = JSON.parse(res);
                if (response.success) {
                    $('#target-count-display').text(response.count);
                    var html = '<table class="table table-condensed table-striped" style="margin: 0; font-size: 12.5px;">';
                    html += '<thead><tr><th width="40px"><div class="checkbox checkbox-primary" style="margin:0;"><input type="checkbox" id="select-all-patients" checked><label></label></div></th><th>Patient Name</th><th>MR No</th><th>Email</th><th>Phone</th><th>Registered</th><th>Last Visit</th><th>Visits</th></tr></thead><tbody>';
                    if (response.patients.length === 0) {
                        html += '<tr><td colspan="8" class="text-center">No patients found matching these filters.</td></tr>';
                    } else {
                        $.each(response.patients, function(i, p) {
                            var pName = p.name ? p.name : 'Unknown';
                            var pMrNo = p.mr_no ? p.mr_no : '-';
                            var pEmail = p.email ? p.email : '-';
                            var pPhone = p.phone ? p.phone : 'Unknown';
                            var pRegistered = p.datecreated ? timeAgo(p.datecreated) : '-';
                            var pLastVisit = p.last_visit_date ? timeAgo(p.last_visit_date) : 'No visits';
                            var pTotalVisits = p.total_visits ? p.total_visits : '0';

                            html += '<tr data-phone="' + (p.phone || '') + '" data-email="' + (p.email || '') + '">';
                            html += '<td><div class="checkbox checkbox-primary" style="margin:0;"><input type="checkbox" class="patient-checkbox" value="' + p.id + '" checked><label></label></div></td>';
                            html += '<td>' + pName + '</td>';
                            html += '<td><span class="label label-default">' + pMrNo + '</span></td>';
                            html += '<td>' + pEmail + '</td>';
                            html += '<td>' + pPhone + '</td>';
                            html += '<td><small class="text-muted">' + pRegistered + '</small></td>';
                            html += '<td><small class="text-muted">' + pLastVisit + '</small></td>';
                            html += '<td><span class="badge">' + pTotalVisits + '</span></td>';
                            html += '</tr>';
                        });
                    }
                    html += '</tbody></table>';
                    $('#target-patients-list').html(html);
                    $('#excluded-patients-container').empty();
                }
            });
        }

        // Checkbox exclusion logic
        $('body').on('change', '.patient-checkbox', function(){
            var patientId = $(this).val();
            if(!$(this).is(':checked')) {
                $('#excluded-patients-container').append('<input type="hidden" name="filters[excluded_patients][]" value="' + patientId + '" id="excluded_' + patientId + '">');
            } else {
                $('#excluded_' + patientId).remove();
            }
            var currentCount = parseInt($('#target-count-display').text());
            if(!isNaN(currentCount)) {
                $('#target-count-display').text($(this).is(':checked') ? currentCount + 1 : currentCount - 1);
            }
        });

        // Select All / Deselect All
        $('body').on('change', '#select-all-patients', function(){
            var isChecked = $(this).is(':checked');
            $('.patient-checkbox').each(function(){
                if($(this).is(':checked') !== isChecked) {
                    $(this).prop('checked', isChecked).trigger('change');
                }
            });
        });

        // Smart Phone Number Validation
        function isValidPhone(raw) {
            var phone = raw.toString().replace(/[^0-9]/g, '');
            if (phone.length > 10 && phone.substring(0, 2) === '91') {
                phone = phone.substring(2);
            }
            if (phone.length !== 10) return false;
            if (!/^[6-9]/.test(phone)) return false;
            if (/^(\d)\1{9}$/.test(phone)) return false;
            for (var pLen = 1; pLen <= 4; pLen++) {
                var pat = phone.substring(0, pLen);
                var repeated = '';
                while (repeated.length < 10) repeated += pat;
                if (repeated.substring(0, 10) === phone) return false;
            }
            return true;
        }

        // Uncheck Invalid Phone Numbers
        $('#btn-uncheck-invalid-phones').on('click', function(){
            var unchecked = 0;
            $('#target-patients-list tr[data-phone]').each(function(){
                var phone = $(this).data('phone').toString();
                var cb = $(this).find('.patient-checkbox');
                if (cb.is(':checked') && !isValidPhone(phone)) {
                    cb.prop('checked', false).trigger('change');
                    unchecked++;
                }
            });
            if (unchecked > 0) {
                alert_float('warning', unchecked + ' patient(s) with invalid numbers unchecked.');
            } else {
                alert_float('success', 'All patients have valid phone numbers.');
            }
        });

        // Uncheck Invalid Email IDs
        $('#btn-uncheck-invalid-emails').on('click', function(){
            var unchecked = 0;
            $('#target-patients-list tr[data-email]').each(function(){
                var email = ($(this).data('email') || '').toString().trim();
                var cb = $(this).find('.patient-checkbox');
                if (cb.is(':checked') && (!email || email === '-' || email.indexOf('@') === -1)) {
                    cb.prop('checked', false).trigger('change');
                    unchecked++;
                }
            });
            if (unchecked > 0) {
                alert_float('warning', unchecked + ' patient(s) with invalid emails unchecked.');
            } else {
                alert_float('success', 'All patients have valid email IDs.');
            }
        });

        $('#btn-apply-filters').on('click', function(){ loadPatientCount(); });
        // Trigger initial channel state
        $('#channel').trigger('change');
        loadPatientCount();
    });
</script>
</body>
</html>
