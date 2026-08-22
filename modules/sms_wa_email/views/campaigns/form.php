<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
    .ccx-comm-page { font-family: 'Inter', sans-serif; }
    .ccx-page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
    .ccx-page-header-icon { width: 48px; height: 48px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3); }
    .ccx-page-header h4 { margin: 0; font-weight: 700; font-size: 22px; color: #1e1b4b; letter-spacing: -0.3px; }
    .ccx-main-card { background: #ffffff; border-radius: 16px; border: 1px solid rgba(229, 231, 235, 0.7); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04), 0 10px 30px -5px rgba(0,0,0,0.06); padding: 24px; }
    .target-preview { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 16px; border-radius: 12px; margin-top: 24px; text-align: center; }
    .target-count { font-size: 32px; font-weight: 700; color: #166534; line-height: 1; margin-bottom: 5px; }
    .tpl-preview-box { background: linear-gradient(135deg, #f8fafc, #f1f5f9); border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-top: 16px; display: none; }
    .tpl-preview-box .tpl-preview-header { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; font-weight: 700; font-size: 14px; color: #475569; }
    .tpl-preview-box .tpl-preview-header i { color: #6366f1; }
    .tpl-preview-content { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; font-size: 13.5px; line-height: 1.7; color: #1e293b; white-space: pre-wrap; word-wrap: break-word; }
    .tpl-preview-content .tpl-var { background: #dbeafe; color: #1d4ed8; padding: 1px 6px; border-radius: 4px; font-weight: 600; font-size: 12px; }
    .tpl-preview-sample { margin-top: 12px; }
    .tpl-preview-sample .sample-header { font-size: 12px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
    .tpl-preview-sample .tpl-preview-content { background: #f0fdf4; border-color: #bbf7d0; }

    /* Send Mode Toggle */
    .send-mode-toggle { display: flex; gap: 12px; margin-bottom: 4px; }
    .send-mode-option { flex: 1; position: relative; cursor: pointer; }
    .send-mode-option input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
    .send-mode-option .mode-card { display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-radius: 12px; border: 2px solid #e5e7eb; background: #fff; transition: all 0.2s ease; }
    .send-mode-option input:checked + .mode-card { border-color: #6366f1; background: #eef2ff; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12); }
    .send-mode-option .mode-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; color: #fff; }
    .send-mode-option .mode-title { font-weight: 700; font-size: 14px; color: #1e1b4b; margin: 0; }
    .send-mode-option .mode-desc { font-size: 12px; color: #6b7280; margin: 2px 0 0; }
</style>

<div id="wrapper">
    <div class="content ccx-comm-page">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="ccx-page-header">
                    <a href="<?php echo admin_url('sms_wa_email/campaigns'); ?>" class="btn btn-default" style="border-radius: 10px; padding: 12px 16px;"><i class="fa fa-arrow-left"></i></a>
                    <div class="ccx-page-header-icon">
                        <i class="fa fa-bullhorn"></i>
                    </div>
                    <div>
                        <h4>New Campaign</h4>
                        <p>Configure filters to reach specific patients</p>
                    </div>
                </div>

                <div class="ccx-main-card panel_s">
                    <div class="panel-body">
                        <?php echo form_open(admin_url('sms_wa_email/campaigns/create'), ['id' => 'campaign-form']); ?>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo render_input('name', 'Campaign Name', '', 'text', ['required' => true]); ?>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom: 12px;">
                            <div class="col-md-12">
                                <label style="margin-bottom: 8px; font-weight: 600;">Delivery Mode</label>
                                <div class="send-mode-toggle">
                                    <label class="send-mode-option">
                                        <input type="radio" name="send_mode" value="now" checked>
                                        <div class="mode-card">
                                            <div class="mode-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                                                <i class="fa fa-paper-plane"></i>
                                            </div>
                                            <div>
                                                <p class="mode-title">Send Now</p>
                                                <p class="mode-desc">Send immediately after creation</p>
                                            </div>
                                        </div>
                                    </label>
                                    <label class="send-mode-option">
                                        <input type="radio" name="send_mode" value="schedule">
                                        <div class="mode-card">
                                            <div class="mode-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
                                                <i class="fa fa-calendar"></i>
                                            </div>
                                            <div>
                                                <p class="mode-title">Schedule</p>
                                                <p class="mode-desc">Pick a date & time to send</p>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6" id="schedule-date-wrapper" style="display: none;">
                                <?php echo render_datetime_input('schedule_date', 'Schedule Date & Time'); ?>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="channel">Sending Channel</label>
                                    <select name="channel" id="channel" class="selectpicker" data-width="100%" required>
                                        <?php $first = true; foreach($active_channels as $ch_key => $ch_label): ?>
                                            <option value="<?php echo $ch_key; ?>" <?php if($first) { echo 'selected'; $first = false; } ?>><?php echo $ch_label; ?></option>
                                        <?php endforeach; ?>
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
                                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['title']); ?></option>
                                        <?php endforeach; endif; ?>
                                    </select>
                                </div>
                                <?php $is_first_channel = false; endforeach; ?>
                                <!-- Hidden real template field -->
                                <input type="hidden" name="template_id" id="template_id" required>
                            </div>
                        </div>

                        <!-- Template Preview -->
                        <div class="tpl-preview-box" id="tpl-preview-box">
                            <div class="tpl-preview-header"><i class="fa fa-eye"></i> Template Preview</div>
                            <div>
                                <div class="tpl-preview-content" id="tpl-raw-content"></div>
                            </div>
                            <div class="tpl-preview-sample" id="tpl-sample-section" style="display:none;">
                                <div class="sample-header"><i class="fa fa-user"></i> Sample with first patient data</div>
                                <div class="tpl-preview-content" id="tpl-sample-content"></div>
                            </div>
                        </div>

                        <hr>
                        <h4 style="margin-bottom: 20px;"><i class="fa fa-filter"></i> Target Audience Filters</h4>

                        <!-- Row 1: Patient Status, Gender, Age Range -->
                        <div class="row" style="margin-bottom: 12px;">
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Patient Status</label>
                                    <select name="filters[status]" class="selectpicker filter-input" data-width="100%">
                                        <option value="1" selected>Active Only</option>
                                        <option value="0">Inactive Only</option>
                                        <option value="">All Patients</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Gender</label>
                                    <select name="filters[gender]" class="selectpicker filter-input" data-width="100%">
                                        <option value="">All</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Age From</label>
                                    <input type="number" name="filters[age_from]" class="form-control filter-input" placeholder="Min" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Age To</label>
                                    <input type="number" name="filters[age_to]" class="form-control filter-input" placeholder="Max" min="0">
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Registration Date Range + Visit Date Range -->
                        <div class="row" style="margin-bottom: 12px;">
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Registered From</label>
                                    <div class="input-group date">
                                        <input type="text" name="filters[registered_from]" class="form-control datepicker filter-input" value="">
                                        <div class="input-group-addon"><i class="fa fa-calendar calendar-icon"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Registered To</label>
                                    <div class="input-group date">
                                        <input type="text" name="filters[registered_to]" class="form-control datepicker filter-input" value="">
                                        <div class="input-group-addon"><i class="fa fa-calendar calendar-icon"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Visit Date From</label>
                                    <div class="input-group date">
                                        <input type="text" name="filters[visit_date_from]" class="form-control datepicker filter-input" value="">
                                        <div class="input-group-addon"><i class="fa fa-calendar calendar-icon"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Visit Date To</label>
                                    <div class="input-group date">
                                        <input type="text" name="filters[visit_date_to]" class="form-control datepicker filter-input" value="">
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
                                    <input type="number" name="filters[visits_min]" class="form-control filter-input" placeholder="0" min="0">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Max Visits</label>
                                    <input type="number" name="filters[visits_max]" class="form-control filter-input" placeholder="∞" min="0">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Item / Test</label>
                                    <select name="filters[items][]" id="filter-items-select" class="selectpicker filter-input" data-width="100%" data-live-search="true" data-actions-box="true" multiple data-none-selected-text="-- All Items --">
                                        <?php
                                        $current_group = '';
                                        foreach ($items_list as $item):
                                            $grp = $item['group_name'] ? $item['group_name'] : 'Ungrouped';
                                            if ($grp !== $current_group):
                                                if ($current_group !== '') echo '</optgroup>';
                                                $current_group = $grp;
                                                echo '<optgroup label="' . htmlspecialchars($current_group) . '">';
                                            endif;
                                        ?>
                                            <option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['description']); ?></option>
                                        <?php endforeach; ?>
                                        <?php if ($current_group !== '') echo '</optgroup>'; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Parameter Value Filter (Dynamic) -->
                        <div class="row" id="parameter-filter-row" style="display:none; margin-bottom: 12px;">
                            <div class="col-md-12">
                                <div style="display: flex; align-items: center; gap: 12px; background: #f8fafc; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                                    <div style="width: 80px; font-weight: 600; color: #475569;">Value :</div>
                                    <div class="checkbox checkbox-primary" style="margin: 0;">
                                        <input type="checkbox" id="enable_parameter_filter" name="filters[enable_parameter_filter]" value="1" class="filter-input">
                                        <label for="enable_parameter_filter"></label>
                                    </div>
                                    <div style="flex: 2;">
                                        <select name="filters[parameter_name]" id="parameter_name" class="selectpicker filter-input" data-width="100%" data-live-search="true" disabled>
                                            <option value="">-- Parameter --</option>
                                        </select>
                                    </div>
                                    <div style="flex: 1;">
                                        <input type="text" name="filters[parameter_value]" id="parameter_value" class="form-control filter-input" placeholder="0" disabled>
                                    </div>
                                    <div style="flex: 1;">
                                        <select name="filters[parameter_operator]" id="parameter_operator" class="selectpicker filter-input" data-width="100%" disabled>
                                            <option value="<">Lesser</option>
                                            <option value=">">Greater</option>
                                            <option value="=">Equals</option>
                                            <option value="<=">Lesser/Eq</option>
                                            <option value=">=">Greater/Eq</option>
                                            <option value="!=">Not Equals</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 4: Item Status + Payment Status + Customer Group + Apply -->
                        <div class="row" style="align-items: flex-end; display: flex; flex-wrap: wrap; margin-bottom: 12px;">
                            <div class="col-md-4">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Item Status</label>
                                    <select name="filters[item_statuses][]" class="selectpicker filter-input" data-width="100%" data-live-search="true" data-actions-box="true" multiple data-none-selected-text="-- All Statuses --">
                                        <?php foreach ($item_statuses as $st): ?>
                                            <option value="<?php echo htmlspecialchars($st['name']); ?>"><?php echo htmlspecialchars($st['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>Payment Status</label>
                                    <select name="filters[payment_statuses][]" class="selectpicker filter-input" data-width="100%" data-actions-box="true" multiple data-none-selected-text="-- All --">
                                        <option value="1">Unpaid</option>
                                        <option value="2">Paid</option>
                                        <option value="3">Partially Paid</option>
                                        <option value="4">Overdue</option>
                                        <option value="5">Cancelled</option>
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
                            <div style="color: #15803d; font-weight: 500; margin-bottom: 8px;">Patients match these filters</div>
                            <div style="display: flex; gap: 8px; justify-content: center; margin-bottom: 12px;">
                                <button type="button" class="btn btn-sm btn-warning" id="btn-uncheck-invalid-phones"><i class="fa fa-phone"></i> Uncheck Invalid Numbers</button>
                                <button type="button" class="btn btn-sm btn-danger" id="btn-uncheck-invalid-emails"><i class="fa fa-envelope"></i> Uncheck Invalid Emails</button>
                            </div>
                            <div id="target-patients-list" style="max-height: 250px; overflow-y: auto; text-align: left; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px;">
                                <!-- Patients will be listed here -->
                            </div>
                            <div id="excluded-patients-container"></div>
                        </div>

                        <hr>
                        <div class="text-right" style="display: flex; justify-content: flex-end; gap: 12px;">
                            <button type="submit" class="btn btn-lg btn-success" id="btn-send-now" style="font-weight: 700; padding: 12px 28px; border-radius: 10px;">
                                <i class="fa fa-paper-plane"></i> Send Now
                            </button>
                            <button type="submit" class="btn btn-lg btn-info" id="btn-schedule" style="display: none; font-weight: 700; padding: 12px 28px; border-radius: 10px;">
                                <i class="fa fa-clock-o"></i> Schedule Campaign
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
        appValidateForm($('#campaign-form'), {
            name: 'required',
            channel: 'required',
            template_id: 'required'
        });

        // Send Mode Toggle
        $('input[name="send_mode"]').on('change', function(){
            var mode = $(this).val();
            if (mode === 'schedule') {
                $('#schedule-date-wrapper').slideDown(200);
                $('#btn-send-now').hide();
                $('#btn-schedule').show();
            } else {
                $('#schedule-date-wrapper').slideUp(200);
                $('input[name="schedule_date"]').val('');
                $('#btn-send-now').show();
                $('#btn-schedule').hide();
            }
        });
        // Trigger initial state
        $('input[name="send_mode"]:checked').trigger('change');

        // Dynamic channel switching
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
        // Trigger initial channel state
        $('#channel').trigger('change');

        $('select.template-select').on('change', function(){
            updateRealTemplateId();
            loadTemplatePreview();
        });

        function updateRealTemplateId() {
            var channel = $('#channel').val();
            var id = $('select[name="template_id_' + channel + '"]').val();
            $('#template_id').val(id || '');
        }

        // Template Preview
        var cachedTemplates = {};
        var firstPatientData = null;

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
            $.post(admin_url + 'sms_wa_email/campaigns/get_template_content', postData, function(res) {
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
            // Highlight variables in raw view
            var rawHtml = content.replace(/\{([^}]+)\}/g, '<span class="tpl-var">{$1}</span>');
            $('#tpl-raw-content').html(rawHtml);
            $('#tpl-preview-box').slideDown(200);

            // If we have patient data, do sample replacement
            if (firstPatientData) {
                showSamplePreview(content, firstPatientData);
            } else {
                $('#tpl-sample-section').hide();
            }
        }

        function showSamplePreview(content, patient) {
            var sample = content;
            var replacements = {
                'patient_name': patient.name || 'Patient',
                'mobile_number': patient.phone || '9999999999',
                'company': '<?php echo addslashes(get_option("companyname")); ?>',
                'email': patient.email || 'patient@example.com'
            };
            for (var key in replacements) {
                sample = sample.split('{' + key + '}').join(replacements[key]);
            }
            // Highlight any remaining unresolved variables
            sample = sample.replace(/\{([^}]+)\}/g, '<span class="tpl-var">{$1}</span>');
            $('#tpl-sample-content').html(sample);
            $('#tpl-sample-section').show();
        }

        // Initialize template select based on default channel
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

        // Count patients logic
        var countRequest = null;
        function loadPatientCount() {
            if(countRequest) {
                countRequest.abort();
            }
            $('#target-count-display').html('<i class="fa fa-spinner fa-spin"></i>');
            $('#target-patients-list').html('<div class="text-center" style="padding: 20px;"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
            
            var formData = $('#campaign-form').find('.filter-input').serialize();
            // Append CSRF token to the serialized filter data
            if (typeof csrfData !== 'undefined') {
                formData += '&' + csrfData.token_name + '=' + encodeURIComponent(csrfData.hash);
            }

            countRequest = $.post(admin_url + 'sms_wa_email/campaigns/fetch_filtered_patients', formData, function(res) {
                var response = JSON.parse(res);
                if(response.success) {
                    $('#target-count-display').text(response.count);
                    
                    var html = '<table class="table table-condensed table-striped" style="margin: 0; font-size: 12.5px;">';
                    html += '<thead><tr><th width="40px"><div class="checkbox checkbox-primary" style="margin:0;"><input type="checkbox" id="select-all-patients" checked><label></label></div></th><th>Patient Name</th><th>MR No</th><th>Email</th><th>Phone</th><th>Registered</th><th>Last Visit</th><th>Visits</th></tr></thead><tbody>';
                    
                    if(response.patients.length === 0) {
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
                    $('#excluded-patients-container').empty(); // Reset exclusions on new filter

                    // Cache first patient for the template preview
                    if (response.patients.length > 0) {
                        firstPatientData = response.patients[0];
                        loadTemplatePreview(); // Re-render preview with sample data
                    }
                }
            });
        }

        $('body').on('change', '.patient-checkbox', function(){
            var patientId = $(this).val();
            if(!$(this).is(':checked')) {
                // Add hidden input for excluded patient
                $('#excluded-patients-container').append('<input type="hidden" name="filters[excluded_patients][]" value="' + patientId + '" id="excluded_' + patientId + '">');
            } else {
                // Remove hidden input target
                $('#excluded_' + patientId).remove();
            }
            // Update visual count
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
            // Strip country code prefix (91 for India)
            if (phone.length > 10 && phone.substring(0, 2) === '91') {
                phone = phone.substring(2);
            }
            // Must be exactly 10 digits
            if (phone.length !== 10) return false;
            // Must start with 6, 7, 8, or 9 (valid Indian mobile)
            if (!/^[6-9]/.test(phone)) return false;
            // Reject all same digits (e.g. 9999999999, 1111111111)
            if (/^(\d)\1{9}$/.test(phone)) return false;
            // Reject short repeating patterns (e.g. 2121212121, 9898989898)
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

        // Handle filter changes manually via button
        $('#btn-apply-filters').on('click', function(){
            loadPatientCount();
        });

        // Dynamic Parameter Filter Fetching
        $('#filter-items-select').on('change', function() {
            var items = $(this).val();
            if (items && items.length > 0) {
                $('#parameter-filter-row').slideDown(200);
                var postData = { items: items };
                if (typeof csrfData !== 'undefined') {
                    postData[csrfData.token_name] = csrfData.hash;
                }
                
                // Keep the current selected parameter before reloading
                var currentSelection = $('#parameter_name').val();

                $.post(admin_url + 'sms_wa_email/campaigns/get_test_parameters', postData, function(res) {
                    try {
                        var params = JSON.parse(res);
                        var html = '<option value="">-- Parameter --</option>';
                        $.each(params, function(i, p) {
                            var sel = (p.parameter_name === currentSelection) ? ' selected' : '';
                            html += '<option value="' + $('<div>').text(p.parameter_name).html() + '"' + sel + '>' + $('<div>').text(p.parameter_name).html() + '</option>';
                        });
                        $('#parameter_name').html(html).selectpicker('refresh');
                    } catch(e) {}
                });
            } else {
                $('#parameter-filter-row').slideUp(200);
                if($('#enable_parameter_filter').is(':checked')) {
                    $('#enable_parameter_filter').prop('checked', false).trigger('change');
                }
            }
        });

        // Handle Checkbox for Parameter Filter
        $('#enable_parameter_filter').on('change', function() {
            var isChecked = $(this).is(':checked');
            $('#parameter_name, #parameter_value, #parameter_operator').prop('disabled', !isChecked);
            $('.selectpicker').selectpicker('refresh');
            if(isChecked) {
                $('#parameter_name').selectpicker('toggle');
            }
        });

        // Try triggering it if items already selected on load (e.g. going back)
        if ($('#filter-items-select').val() && $('#filter-items-select').val().length > 0) {
            $('#filter-items-select').trigger('change');
        }

        // Load initial count
        loadPatientCount();
    });
</script>
</body>
</html>
