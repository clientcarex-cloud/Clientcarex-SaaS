<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php echo form_open(admin_url('ccx_msgs/save_api'), ['id' => 'api-form']); ?>
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
            aria-hidden="true">&times;</span></button>
    <h4 class="modal-title">
        <i class="fa fa-plug" style="color: #03a9f4; margin-right: 6px;"></i>
        <?php echo isset($api) ? _l('edit') . ' API' : _l('ccx_msgs_new_api'); ?>
    </h4>
</div>
<div class="modal-body">
    <div class="row">
        <div class="col-md-12">
            <?php if (isset($api)) { ?>
                <input type="hidden" name="id" value="<?php echo $api->id; ?>">
            <?php } else { ?>
                <input type="hidden" name="id" value="">
            <?php } ?>

            <div class="row">
                <div class="col-md-6">
                    <?php $selected_type = isset($api) ? $api->message_type : ''; ?>
                    <div class="form-group" app-field-wrapper="message_type">
                        <label for="message_type" class="control-label">
                            <?php echo _l('plan_message_type'); ?>
                        </label>
                        <select id="message_type" name="message_type" class="selectpicker" data-width="100%"
                            data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                            <option value=""></option>
                            <option value="sms" <?php if ($selected_type == 'sms')
                                echo 'selected'; ?>>
                                <?php echo _l('ccx_msgs_sms'); ?>
                            </option>
                            <option value="whatsapp" <?php if ($selected_type == 'whatsapp')
                                echo 'selected'; ?>>
                                <?php echo _l('ccx_msgs_whatsapp'); ?>
                            </option>
                            <option value="email" <?php if ($selected_type == 'email')
                                echo 'selected'; ?>>
                                <?php echo _l('ccx_msgs_email'); ?>
                            </option>
                            <option value="aicall" <?php if ($selected_type == 'aicall')
                                echo 'selected'; ?>>
                                <?php echo _l('ccx_msgs_aicall'); ?>
                            </option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <?php $selected_subtype = isset($api) ? $api->message_subtype : 'promotional'; ?>
                    <div class="form-group" app-field-wrapper="message_subtype">
                        <label for="message_subtype" class="control-label">
                            <?php echo _l('ccx_msgs_subtype'); ?>
                        </label>
                        <select id="message_subtype" name="message_subtype" class="selectpicker" data-width="100%">
                            <option value="promotional" <?php if ($selected_subtype == 'promotional')
                                echo 'selected'; ?>>
                                <?php echo _l('ccx_msgs_promo'); ?>
                            </option>
                            <option value="transactional" <?php if ($selected_subtype == 'transactional')
                                echo 'selected'; ?>>
                                <?php echo _l('ccx_msgs_trans'); ?>
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- API Scope -->
            <?php $selected_scope = isset($api) ? (isset($api->api_scope) ? $api->api_scope : 'global') : 'global'; ?>
            <div class="row" style="margin-bottom: 10px;">
                <div class="col-md-4">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="control-label" style="display:block; margin-bottom: 6px;">API Scope</label>
                        <div class="btn-group btn-group-sm" data-toggle="buttons" style="width:100%;">
                            <label class="btn btn-default <?php echo ($selected_scope === 'global') ? 'active' : ''; ?>"
                                style="width:50%; border-radius:4px 0 0 4px;">
                                <input type="radio" name="api_scope" value="global" <?php echo ($selected_scope === 'global') ? 'checked' : ''; ?>>
                                <i class="fa fa-globe"></i> Global
                            </label>
                            <label class="btn btn-default <?php echo ($selected_scope === 'client') ? 'active' : ''; ?>"
                                style="width:50%; border-radius:0 4px 4px 0;">
                                <input type="radio" name="api_scope" value="client" <?php echo ($selected_scope === 'client') ? 'checked' : ''; ?>>
                                <i class="fa fa-user"></i> Client
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-md-8" id="client_id_group"
                    style="<?php echo ($selected_scope === 'client') ? '' : 'display:none;'; ?>">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="client_id" class="control-label">Select Client</label>
                        <select id="client_id" name="client_id" class="selectpicker" data-width="100%"
                            data-live-search="true" data-none-selected-text="— Select Client —">
                            <option value=""></option>
                            <?php if (isset($clients)): ?>
                                <?php foreach ($clients as $c): ?>
                                    <option value="<?php echo $c->userid; ?>" <?php echo (isset($api) && isset($api->client_id) && $api->client_id == $c->userid) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c->company); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>

            <?php echo render_input('api_name', 'ccx_msgs_api_name', isset($api) ? $api->api_name : ''); ?>

            <!-- ═══ SMTP Configuration (Email only) ═══ -->
            <?php
            $smtp_host = isset($api) ? (isset($api->smtp_host) ? $api->smtp_host : '') : '';
            $smtp_port = isset($api) ? (isset($api->smtp_port) ? $api->smtp_port : '') : '';
            $smtp_username = isset($api) ? (isset($api->smtp_username) ? $api->smtp_username : '') : '';
            $smtp_from_email = isset($api) ? (isset($api->smtp_from_email) ? $api->smtp_from_email : '') : '';
            $smtp_encryption = isset($api) ? (isset($api->smtp_encryption) ? $api->smtp_encryption : '') : '';

            // Decrypt password for display (same pattern as Perfex CRM core email settings)
            $smtp_password = '';
            if (isset($api) && !empty($api->smtp_password)) {
                $ps = $api->smtp_password;
                $decrypted = $this->encryption->decrypt($ps);
                $smtp_password = ($decrypted !== false) ? $decrypted : $ps;
            }
            ?>
            <div id="smtp_fields_group" style="<?php echo (isset($api) && $api->message_type === 'email') ? '' : 'display:none;'; ?>">
                <div style="margin: 10px 0 16px; padding: 16px 18px; background: linear-gradient(135deg, #fff7ed, #ffedd5); border: 1px solid #fed7aa; border-radius: 10px;">
                    <h5 style="margin: 0 0 14px; font-size: 14px; font-weight: 600; color: #9a3412;">
                        <i class="fa fa-envelope" style="margin-right: 6px; color: #ea580c;"></i><?php echo _l('settings_smtp_settings_heading'); ?>
                    </h5>

                    <!-- Use CRM Email Settings toggle -->
                    <?php $use_crm_smtp = isset($api) && !empty($api->use_crm_smtp); ?>
                    <div class="checkbox checkbox-primary" style="margin: 0 0 8px;">
                        <input type="checkbox" name="use_crm_smtp" id="use_crm_smtp" value="1" <?php echo $use_crm_smtp ? 'checked' : ''; ?>>
                        <label for="use_crm_smtp" style="font-weight:600;">
                            Use CRM Email Settings (Setup &rarr; Settings &rarr; Email)
                        </label>
                    </div>
                    <p id="use_crm_smtp_note" style="font-size:12px; color:#9a3412; margin: 0 0 14px; <?php echo $use_crm_smtp ? '' : 'display:none;'; ?>">
                        <i class="fa fa-info-circle" style="margin-right:4px;"></i>
                        Emails will be sent using the Perfex CRM email configuration of the installation
                        that is sending — on SaaS, each tenant uses its own Email settings
                        (host, port, credentials and From address are read at send time). Tenants that
                        have not configured their own SMTP automatically fall back to the master
                        installation's Email settings. The Email Header and Email Footer are also
                        taken from Setup &rarr; Settings &rarr; Email — 100% the same wrapping as
                        the CRM's own emails — and they follow the same source as the SMTP settings:
                        a send using the master's SMTP carries the master's header/footer, a send
                        using the tenant's own SMTP carries the tenant's. All manual fields below
                        (SMTP, signature, header, footer) are ignored while this is enabled.
                    </p>

                    <div id="manual_smtp_fields" style="<?php echo $use_crm_smtp ? 'opacity:.45; pointer-events:none;' : ''; ?>">
                    <!-- Encryption -->
                    <div class="form-group" app-field-wrapper="smtp_encryption">
                        <label for="smtp_encryption" class="control-label"><?php echo _l('smtp_encryption'); ?></label>
                        <select id="smtp_encryption" name="smtp_encryption" class="selectpicker" data-width="100%">
                            <option value="" <?php echo ($smtp_encryption == '') ? 'selected' : ''; ?>><?php echo _l('smtp_encryption_none'); ?></option>
                            <option value="ssl" <?php echo ($smtp_encryption == 'ssl') ? 'selected' : ''; ?>>SSL</option>
                            <option value="tls" <?php echo ($smtp_encryption == 'tls') ? 'selected' : ''; ?>>TLS</option>
                        </select>
                    </div>

                    <!-- Host + Port -->
                    <div class="row">
                        <div class="col-md-8">
                            <?php echo render_input('smtp_host', 'settings_email_host', $smtp_host); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo render_input('smtp_port', 'settings_email_port', $smtp_port); ?>
                        </div>
                    </div>

                    <!-- SMTP Email (From Email) -->
                    <?php echo render_input('smtp_from_email', 'settings_email', $smtp_from_email); ?>

                    <!-- Username + Password -->
                    <div class="row">
                        <div class="col-md-6">
                            <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-title="<?php echo _l('smtp_username_help'); ?>"></i>
                            <?php echo render_input('smtp_username', 'smtp_username', $smtp_username); ?>
                        </div>
                        <div class="col-md-6">
                            <!-- fake fields to prevent chrome autofill -->
                            <input type="text" class="fake-autofill-field" value="" tabindex="-1" style="position:absolute;opacity:0;height:0;" />
                            <input type="password" class="fake-autofill-field" value="" tabindex="-1" style="position:absolute;opacity:0;height:0;" />
                            <?php echo render_input('smtp_password', 'settings_email_password', $smtp_password, 'password', ['autocomplete' => 'off']); ?>
                        </div>
                    </div>
                    </div><!-- /#manual_smtp_fields -->

                    <hr style="margin: 12px 0;" />

                    <!-- Test SMTP Email -->
                    <h5 style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #9a3412;">
                        <?php echo _l('settings_send_test_email_heading'); ?>
                    </h5>
                    <p class="text-muted" style="font-size: 12px; margin-bottom: 8px;">
                        <?php echo _l('settings_send_test_email_subheading'); ?>
                    </p>
                    <div class="form-group" style="margin-bottom: 8px;">
                        <div class="input-group">
                            <input type="email" class="form-control" id="smtp_test_email" data-ays-ignore="true"
                                placeholder="<?php echo _l('settings_send_test_email_string'); ?>">
                            <div class="input-group-btn">
                                <button type="button" class="btn btn-warning" id="btn_test_smtp">
                                    <i class="fa fa-bolt" style="margin-right: 4px;"></i>Test
                                </button>
                            </div>
                        </div>
                    </div>
                    <span id="smtp_test_status" style="font-size: 12px;"></span>
                    <div id="smtp_debug_log" style="display:none; margin-top: 8px; max-height: 400px; overflow-y: auto; background: #1e293b; color: #e2e8f0; padding: 10px 12px; border-radius: 6px; font-family: monospace; font-size: 11px; white-space: pre-wrap; word-break: break-all;"></div>
                </div>
            </div>

            <!-- ═══ Email Template Fields (inside smtp_fields_group) ═══ -->
            <div id="email_template_fields" style="<?php echo (isset($api) && $api->message_type === 'email') ? '' : 'display:none;'; ?>">
                <div style="margin: 10px 0 16px; padding: 16px 18px; background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #86efac; border-radius: 10px;">
                    <h5 style="margin: 0 0 14px; font-size: 14px; font-weight: 600; color: #166534;">
                        <i class="fa fa-file-text" style="margin-right: 6px; color: #16a34a;"></i>Email Template
                    </h5>
                    <?php echo render_input('email_to_tpl', 'Send To Email', isset($api) && isset($api->email_to_tpl) ? $api->email_to_tpl : '{patient_email}', 'text', ['placeholder' => 'e.g. {patient_email} or recipient@example.com', 'id' => 'email_to_tpl']); ?>
                    <div class="row">
                        <div class="col-md-8">
                            <?php echo render_input('email_subject_tpl', 'Subject Template', isset($api) && isset($api->email_subject_tpl) ? $api->email_subject_tpl : '', 'text', ['placeholder' => 'e.g. Appointment for {patient_name}', 'id' => 'email_subject_tpl']); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo render_input('email_from_name_tpl', 'From Name', isset($api) && isset($api->email_from_name_tpl) ? $api->email_from_name_tpl : '', 'text', ['placeholder' => 'e.g. Clientcarex', 'id' => 'email_from_name_tpl']); ?>
                        </div>
                    </div>
                    <div class="form-group" app-field-wrapper="email_body_tpl">
                        <label for="email_body_tpl" class="control-label">
                            Email Body Template
                        </label>
                        <textarea id="email_body_tpl" name="email_body_tpl" class="form-control" rows="4"
                            placeholder="Use tags like {message}, {patient_name}, {to} etc."><?php echo isset($api) && isset($api->email_body_tpl) ? $api->email_body_tpl : '{message}'; ?></textarea>
                    </div>

                    <hr style="margin: 12px 0;" />

                    <!-- Charset + BCC -->
                    <div class="row">
                        <div class="col-md-4">
                            <?php echo render_input('smtp_email_charset', _l('settings_email_charset'), isset($api) && isset($api->smtp_email_charset) ? $api->smtp_email_charset : 'utf-8', 'text', ['placeholder' => 'utf-8']); ?>
                        </div>
                        <div class="col-md-8">
                            <?php echo render_input('bcc_emails', _l('bcc_all_emails'), isset($api) && isset($api->bcc_emails) ? $api->bcc_emails : '', 'text', ['placeholder' => 'e.g. admin@example.com, manager@example.com']); ?>
                        </div>
                    </div>

                    <!-- Email Signature -->
                    <?php echo render_textarea('email_signature', _l('settings_email_signature'), isset($api) && isset($api->email_signature) ? $api->email_signature : '', ['rows' => 3, 'placeholder' => 'Email signature appended to all emails']); ?>

                    <!-- Email Header -->
                    <?php echo render_textarea('email_header', _l('email_header'), isset($api) && isset($api->email_header) ? $api->email_header : '', ['rows' => 6, 'placeholder' => 'HTML email header template']); ?>

                    <!-- Email Footer -->
                    <?php echo render_textarea('email_footer', _l('email_footer'), isset($api) && isset($api->email_footer) ? $api->email_footer : '', ['rows' => 6, 'placeholder' => 'HTML email footer template']); ?>
                </div>
            </div>

            <!-- ═══ API Fields (non-email) ═══ -->
            <div id="api_fields_group" style="<?php echo (isset($api) && $api->message_type === 'email') ? 'display:none;' : ''; ?>">
            <!-- Provider & DLT Info -->
            <div class="row">
                <div class="col-md-3">
                    <?php echo render_input('api_provider', 'API Provider', isset($api) ? $api->api_provider : '', 'text', ['placeholder' => 'e.g. Fast2SMS']); ?>
                </div>
                <div class="col-md-3">
                    <?php echo render_input('header_id', 'Header ID', isset($api) ? $api->header_id : '', 'text', ['placeholder' => 'Sender ID']); ?>
                </div>
                <div class="col-md-3">
                    <?php echo render_input('entity_id', 'Entity ID', isset($api) ? $api->entity_id : '', 'text', ['placeholder' => 'DLT Entity ID']); ?>
                </div>
                <div class="col-md-3">
                    <?php echo render_input('source_provider', 'Source Provider', isset($api) ? $api->source_provider : '', 'text', ['placeholder' => 'Provider name']); ?>
                </div>
            </div>

            <?php echo render_input('api_url', 'ccx_msgs_api_url', isset($api) ? $api->api_url : '', 'text', ['placeholder' => 'https://api.example.com/send']); ?>

            <div class="row">
                <div class="col-md-6">
                    <?php $selected_method = isset($api) ? $api->request_method : 'POST'; ?>
                    <div class="form-group" app-field-wrapper="request_method">
                        <label for="request_method" class="control-label">
                            <?php echo _l('ccx_msgs_api_method'); ?>
                        </label>
                        <select id="request_method" name="request_method" class="selectpicker" data-width="100%">
                            <option value="GET" <?php if ($selected_method == 'GET')
                                echo 'selected'; ?>>GET</option>
                            <option value="POST" <?php if ($selected_method == 'POST')
                                echo 'selected'; ?>>POST</option>
                            <option value="PUT" <?php if ($selected_method == 'PUT')
                                echo 'selected'; ?>>PUT</option>
                            <option value="DELETE" <?php if ($selected_method == 'DELETE')
                                echo 'selected'; ?>>DELETE
                            </option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <?php $selected_auth = isset($api) ? $api->auth_type : 'none'; ?>
                    <div class="form-group" app-field-wrapper="auth_type">
                        <label for="auth_type" class="control-label">
                            <?php echo _l('ccx_msgs_api_auth'); ?>
                        </label>
                        <select id="auth_type" name="auth_type" class="selectpicker" data-width="100%">
                            <option value="none" <?php if ($selected_auth == 'none')
                                echo 'selected'; ?>>None</option>
                            <option value="bearer" <?php if ($selected_auth == 'bearer')
                                echo 'selected'; ?>>Bearer
                                Token</option>
                            <option value="api_key" <?php if ($selected_auth == 'api_key')
                                echo 'selected'; ?>>API Key
                            </option>
                            <option value="basic" <?php if ($selected_auth == 'basic')
                                echo 'selected'; ?>>Basic Auth
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group auth-credentials-group" id="auth_credentials_group"
                style="<?php echo (isset($api) && $api->auth_type != 'none') ? '' : 'display:none;'; ?>">
                <?php echo render_input('auth_credentials', 'ccx_msgs_api_auth_creds', isset($api) ? $api->auth_credentials : '', 'text', ['placeholder' => 'Token / Key / Base64 credentials']); ?>
            </div>

            <?php
            $is_get = isset($api) && strtoupper($api->request_method) === 'GET';
            $saved_get_mode = (isset($api) && !empty($api->overall_url)) ? 'overall_url' : 'overall_url';
            ?>

            <!-- GET Mode Toggle -->
            <div id="get_mode_toggle" style="<?php echo $is_get ? '' : 'display:none;'; ?>">
                <div class="form-group" style="margin-bottom: 10px;">
                    <label class="control-label" style="display:block; margin-bottom: 6px;">Request Mode</label>
                    <div class="btn-group btn-group-sm" data-toggle="buttons">
                        <label
                            class="btn btn-default <?php echo ($saved_get_mode === 'overall_url') ? 'active' : ''; ?>"
                            id="btn_mode_url">
                            <input type="radio" name="get_mode_radio" value="overall_url" <?php echo ($saved_get_mode === 'overall_url') ? 'checked' : ''; ?>>
                            <i class="fa fa-link"></i> Overall URL
                        </label>
                        <label
                            class="btn btn-default <?php echo ($saved_get_mode === 'headers_body') ? 'active' : ''; ?>"
                            id="btn_mode_fields">
                            <input type="radio" name="get_mode_radio" value="headers_body" <?php echo ($saved_get_mode === 'headers_body') ? 'checked' : ''; ?>>
                            <i class="fa fa-cogs"></i> Headers + Body
                        </label>
                    </div>
                </div>
            </div>
            <input type="hidden" name="get_mode" id="get_mode" value="<?php echo $saved_get_mode; ?>">

            <!-- Overall URL (GET - Overall URL mode) -->
            <div id="overall_url_group"
                style="<?php echo ($is_get && $saved_get_mode === 'overall_url') ? '' : 'display:none;'; ?>">
                <div class="form-group">
                    <label for="overall_url" class="control-label">
                        <i class="fa fa-link" style="margin-right:4px;"></i> Overall URL
                    </label>
                    <textarea id="overall_url" name="overall_url" class="form-control" rows="3"
                        placeholder="https://www.fast2sms.com/dev/bulkV2?authorization=xxx&route=dlt&sender_id=incras&message=176857&numbers=9876543210"><?php echo (isset($api) && !empty($api->overall_url)) ? $api->overall_url : ''; ?></textarea>
                </div>
            </div>

            <!-- Headers (Dynamic Key-Value Repeater) -->
            <div id="headers_group"
                style="<?php echo ($is_get && $saved_get_mode === 'overall_url') ? 'display:none;' : ''; ?>">
                <div class="form-group">
                    <label class="control-label">
                        <i class="fa fa-list" style="margin-right:4px;"></i>
                        <?php echo _l('ccx_msgs_api_headers'); ?>
                    </label>
                    <?php
                    $existing_headers = [];
                    if (isset($api) && !empty($api->headers)) {
                        $parsed = json_decode($api->headers, true);
                        if (is_array($parsed))
                            $existing_headers = $parsed;
                    }
                    ?>
                    <div id="headers_repeater">
                        <?php if (!empty($existing_headers)): ?>
                            <?php foreach ($existing_headers as $hk => $hv): ?>
                                <div class="header-row" style="display:flex; gap:8px; margin-bottom:6px; align-items:center;">
                                    <input type="text" class="form-control input-sm header-key" placeholder="Key"
                                        value="<?php echo htmlspecialchars($hk); ?>" style="flex:1;">
                                    <input type="text" class="form-control input-sm header-val" placeholder="Value"
                                        value="<?php echo htmlspecialchars($hv); ?>" style="flex:2;">
                                    <button type="button" class="btn btn-danger btn-xs btn-remove-header"
                                        style="min-width:28px;"><i class="fa fa-times"></i></button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="header-row" style="display:flex; gap:8px; margin-bottom:6px; align-items:center;">
                                <input type="text" class="form-control input-sm header-key"
                                    placeholder="Key (e.g. Content-Type)" style="flex:1;">
                                <input type="text" class="form-control input-sm header-val"
                                    placeholder="Value (e.g. application/json)" style="flex:2;">
                                <button type="button" class="btn btn-danger btn-xs btn-remove-header"
                                    style="min-width:28px;"><i class="fa fa-times"></i></button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-default btn-xs" id="btn_add_header" style="margin-top:4px;">
                        <i class="fa fa-plus"></i> Add Header
                    </button>
                    <input type="hidden" name="headers" id="headers_json"
                        value="<?php echo isset($api) ? htmlspecialchars($api->headers) : ''; ?>">
                </div>
            </div>

            <div id="body_template_group"
                style="<?php echo ($is_get && $saved_get_mode === 'overall_url') ? 'display:none;' : ''; ?>">
                <div class="form-group" app-field-wrapper="body_template">
                    <label for="body_template" class="control-label" id="body_template_label">
                        <?php echo _l('ccx_msgs_api_body'); ?>
                    </label>
                    <textarea id="body_template" name="body_template" class="form-control" rows="4"
                        placeholder='{"to": "{{phone}}", "message": "{{text}}"}'><?php echo isset($api) ? $api->body_template : ''; ?></textarea>
                </div>
            </div>
            </div><!-- /#api_fields_group -->

            <!-- ── Hook Variable Picker (shared, always visible) ── -->
            <?php
            $registered_hooks = function_exists('ccx_get_registered_hooks') ? ccx_get_registered_hooks() : [];
            $hooks_json = json_encode($registered_hooks);
            ?>
            <div
                style="margin: 16px 0; padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                <label class="control-label"
                    style="display:block; margin-bottom: 8px; font-size: 12.5px; font-weight: 600; color: #475569;">
                    <i class="fa fa-tags" style="margin-right: 5px; color: #6366f1;"></i>Available Tags (from Hooks)
                </label>

                <div class="row" style="margin-bottom: 8px;">
                    <div class="col-md-7">
                        <select id="api_hook_select" class="form-control input-sm"
                            style="font-size: 12px; border-radius: 6px;">
                            <option value="">— Select a hook to see available tags —</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <select id="api_tag_target" class="form-control input-sm"
                            style="font-size: 12px; border-radius: 6px;">
                            <!-- Non-email options -->
                            <option value="body_template" class="api-target-opt">Insert into → Body Template</option>
                            <option value="api_url" class="api-target-opt">Insert into → API URL</option>
                            <option value="overall_url" class="api-target-opt">Insert into → Overall URL</option>
                            <option value="headers" class="api-target-opt">Insert into → Headers</option>
                            <!-- Email options -->
                            <option value="email_to_tpl" class="email-target-opt" style="display:none;">Insert into → Send To Email</option>
                            <option value="email_subject_tpl" class="email-target-opt" style="display:none;">Insert into → Subject</option>
                            <option value="email_from_name_tpl" class="email-target-opt" style="display:none;">Insert into → From Name</option>
                            <option value="email_body_tpl" class="email-target-opt" style="display:none;">Insert into → Body Template</option>
                        </select>
                    </div>
                </div>

                <!-- System Tags (always available) -->
                <div style="margin-bottom: 8px;">
                    <div style="font-size: 10.5px; color: #64748b; margin-bottom: 5px; font-weight: 600;">
                        <i class="fa fa-cog" style="margin-right: 3px;"></i>System Tags (always available):
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                        <span class="api-var-tag" data-var="message"
                            style="cursor:pointer; display:inline-block; background:#dcfce7; color:#166534; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #86efac;"
                            onmouseover="this.style.background='#bbf7d0'" onmouseout="this.style.background='#dcfce7'"
                            title="The prepared template content (message body after tag replacement)">
                            {message}
                        </span>
                        <span class="api-var-tag" data-var="message_with_quotes"
                            style="cursor:pointer; display:inline-block; background:#dcfce7; color:#166534; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #86efac;"
                            onmouseover="this.style.background='#bbf7d0'" onmouseout="this.style.background='#dcfce7'"
                            title="Same as {message} but each comma-separated value is wrapped in double quotes">
                            {message_with_quotes}
                        </span>
                        <span class="api-var-tag" data-var="to"
                            style="cursor:pointer; display:inline-block; background:#dcfce7; color:#166534; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #86efac;"
                            onmouseover="this.style.background='#bbf7d0'" onmouseout="this.style.background='#dcfce7'"
                            title="Recipient phone number or email">
                            {to}
                        </span>
                        <span class="api-var-tag" data-var="subject"
                            style="cursor:pointer; display:inline-block; background:#dcfce7; color:#166534; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #86efac;"
                            onmouseover="this.style.background='#bbf7d0'" onmouseout="this.style.background='#dcfce7'"
                            title="Email subject from the linked template">
                            {subject}
                        </span>
                        <span class="api-var-tag" data-var="from_name"
                            style="cursor:pointer; display:inline-block; background:#dcfce7; color:#166534; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #86efac;"
                            onmouseover="this.style.background='#bbf7d0'" onmouseout="this.style.background='#dcfce7'"
                            title="From name from the linked template">
                            {from_name}
                        </span>
                        <span class="api-var-tag" data-var="patient_email"
                            style="cursor:pointer; display:inline-block; background:#dcfce7; color:#166534; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #86efac;"
                            onmouseover="this.style.background='#bbf7d0'" onmouseout="this.style.background='#dcfce7'"
                            title="Patient's email address from profile">
                            {patient_email}
                        </span>
                        <span class="api-var-tag" data-var="msg_template_id"
                            style="cursor:pointer; display:inline-block; background:#dcfce7; color:#166534; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #86efac;"
                            onmouseover="this.style.background='#bbf7d0'" onmouseout="this.style.background='#dcfce7'"
                            title="DLT / provider template ID from the linked template">
                            {msg_template_id}
                        </span>
                        <span class="api-var-tag" data-var="header_id"
                            style="cursor:pointer; display:inline-block; background:#dcfce7; color:#166534; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #86efac;"
                            onmouseover="this.style.background='#bbf7d0'" onmouseout="this.style.background='#dcfce7'"
                            title="Header ID from the linked template">
                            {header_id}
                        </span>
                        <span class="api-var-tag" data-var="all_recipients"
                            style="cursor:pointer; display:inline-block; background:#dcfce7; color:#166534; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #86efac;"
                            onmouseover="this.style.background='#bbf7d0'" onmouseout="this.style.background='#dcfce7'"
                            title="All recipient numbers (comma-separated)">
                            {all_recipients}
                        </span>
                    </div>
                    <!-- AI Call Agent Tags -->
                    <div style="font-size: 10.5px; color: #7c3aed; margin: 8px 0 5px; font-weight: 600;">
                        <i class="fa fa-microphone" style="margin-right: 3px;"></i>AI Call Agent Tags:
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                        <span class="api-var-tag" data-var="voice_type"
                            style="cursor:pointer; display:inline-block; background:#f3e8ff; color:#7c3aed; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #d8b4fe;"
                            onmouseover="this.style.background='#e9d5ff'" onmouseout="this.style.background='#f3e8ff'"
                            title="Voice type: tts or voice_note">
                            {voice_type}
                        </span>
                        <span class="api-var-tag" data-var="tts_text"
                            style="cursor:pointer; display:inline-block; background:#f3e8ff; color:#7c3aed; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #d8b4fe;"
                            onmouseover="this.style.background='#e9d5ff'" onmouseout="this.style.background='#f3e8ff'"
                            title="TTS text content from the AI call template">
                            {tts_text}
                        </span>
                        <span class="api-var-tag" data-var="voice_note_id"
                            style="cursor:pointer; display:inline-block; background:#f3e8ff; color:#7c3aed; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #d8b4fe;"
                            onmouseover="this.style.background='#e9d5ff'" onmouseout="this.style.background='#f3e8ff'"
                            title="Voice note identifier">
                            {voice_note_id}
                        </span>
                        <span class="api-var-tag" data-var="voice_note_file"
                            style="cursor:pointer; display:inline-block; background:#f3e8ff; color:#7c3aed; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #d8b4fe;"
                            onmouseover="this.style.background='#e9d5ff'" onmouseout="this.style.background='#f3e8ff'"
                            title="Uploaded voice note file path">
                            {voice_note_file}
                        </span>
                        <span class="api-var-tag" data-var="voice_type_id"
                            style="cursor:pointer; display:inline-block; background:#f3e8ff; color:#7c3aed; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #d8b4fe;"
                            onmouseover="this.style.background='#e9d5ff'" onmouseout="this.style.background='#f3e8ff'"
                            title="Voice type ID (e.g. 33-Tran, 34-Promo, 35-TTS)">
                            {voice_type_id}
                        </span>
                        <span class="api-var-tag" data-var="retry_count"
                            style="cursor:pointer; display:inline-block; background:#f3e8ff; color:#7c3aed; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #d8b4fe;"
                            onmouseover="this.style.background='#e9d5ff'" onmouseout="this.style.background='#f3e8ff'"
                            title="Number of retry attempts">
                            {retry_count}
                        </span>
                        <span class="api-var-tag" data-var="retry_interval"
                            style="cursor:pointer; display:inline-block; background:#f3e8ff; color:#7c3aed; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #d8b4fe;"
                            onmouseover="this.style.background='#e9d5ff'" onmouseout="this.style.background='#f3e8ff'"
                            title="Seconds between retries">
                            {retry_interval}
                        </span>
                        <span class="api-var-tag" data-var="language"
                            style="cursor:pointer; display:inline-block; background:#f3e8ff; color:#7c3aed; font-size:11px;
                            padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #d8b4fe;"
                            onmouseover="this.style.background='#e9d5ff'" onmouseout="this.style.background='#f3e8ff'"
                            title="Language for the AI call (english, hindi, telugu, marathi, hinglish)">
                            {language}
                        </span>
                    </div>
                </div>

                <div id="api_hook_variables" style="display: none;">
                    <div style="font-size: 10.5px; color: #94a3b8; margin-bottom: 6px;">
                        <i class="fa fa-plug" style="margin-right: 3px;"></i>Hook-specific tags (click to insert):
                    </div>
                    <div id="api_hook_var_tags" style="display: flex; flex-wrap: wrap; gap: 5px;"></div>
                </div>
            </div>

            <div class="checkbox checkbox-primary">
                <input type="checkbox" name="active" id="api_active" <?php if (isset($api)) {
                    if ($api->active == 1)
                        echo 'checked';
                } else {
                    echo 'checked';
                } ?>>
                <label for="api_active">
                    <?php echo _l('is_active'); ?>
                </label>
            </div>

            <div class="checkbox checkbox-success">
                <input type="checkbox" name="is_default" id="api_is_default" <?php if (isset($api) && $api->is_default == 1)
                    echo 'checked'; ?>>
                <label for="api_is_default">
                    <i class="fa fa-star" style="color: #f0ad4e; margin-right: 4px;"></i>
                    <?php echo _l('ccx_msgs_set_default'); ?>
                </label>
            </div>

        </div>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal">
        <?php echo _l('close'); ?>
    </button>
    <button type="submit" class="btn btn-info">
        <?php echo _l('submit'); ?>
    </button>
</div>
<?php echo form_close(); ?>

<script>
    $(function () {
        // ── Hook Variable Picker for API ──
        var apiHooks = <?= $hooks_json; ?>;

        // Populate hook dropdown
        var $hookSel = $('#api_hook_select');
        $.each(apiHooks, function (i, hook) {
            $hookSel.append('<option value="' + hook.hook_key + '">' + hook.label + ' (' + hook.module + ')</option>');
        });

        // Show variable tags when a hook is selected
        $('#api_hook_select').on('change', function () {
            var hookKey = $(this).val();
            var $vars = $('#api_hook_variables');
            var $tags = $('#api_hook_var_tags');
            $tags.empty();

            if (!hookKey) { $vars.slideUp(150); return; }

            var hook = apiHooks.find(function (h) { return h.hook_key === hookKey; });
            if (hook && hook.variables && hook.variables.length) {
                $.each(hook.variables, function (vi, v) {
                    $tags.append(
                        '<span class="api-var-tag" data-var="' + v + '" ' +
                        'style="cursor:pointer; display:inline-block; background:#eef2ff; color:#4338ca; font-size:11px; ' +
                        'padding:4px 10px; border-radius:20px; font-weight:500; transition:all 0.15s; border:1px solid #c7d2fe;" ' +
                        'onmouseover="this.style.background=\'#c7d2fe\'" onmouseout="this.style.background=\'#eef2ff\'">' +
                        '{' + v + '}</span>'
                    );
                });
                $vars.slideDown(150);
            } else {
                $vars.slideUp(150);
            }
        });

        // Auto-track last focused API field and update "Insert Into" dropdown
        var apiTargetFields = ['api_url', 'overall_url', 'body_template', 'headers', 'email_to_tpl', 'email_subject_tpl', 'email_from_name_tpl', 'email_body_tpl'];
        $(document).on('focus click', '#api_url, #overall_url, #body_template, #headers, #email_to_tpl, #email_subject_tpl, #email_from_name_tpl, #email_body_tpl', function () {
            var fieldId = $(this).attr('id');
            $('#api_tag_target').val(fieldId);
        });

        // Insert tag into the target field
        $(document).on('click', '.api-var-tag', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var varText = '{' + $(this).data('var') + '}';
            var targetId = $('#api_tag_target').val();
            var $field = $('#' + targetId);

            // If target field is hidden or not found, find the first visible one
            if (!$field.length || !$field.is(':visible')) {
                $field = null;
                for (var i = 0; i < apiTargetFields.length; i++) {
                    var $f = $('#' + apiTargetFields[i]);
                    if ($f.length && $f.is(':visible')) {
                        $field = $f;
                        targetId = apiTargetFields[i];
                        $('#api_tag_target').val(targetId);
                        break;
                    }
                }
            }

            if (!$field || !$field.length) return;

            // Append at end (most reliable for unfocused fields)
            var val = $field.val();
            $field.val(val + varText);
            $field.focus();

            // Place cursor at end
            try {
                var newPos = $field.val().length;
                $field[0].setSelectionRange(newPos, newPos);
            } catch (ex) { }

            // Brief flash effect
            var origBg = $(this).css('background-color');
            $(this).css('background', '#a5b4fc');
            var tag = this;
            setTimeout(function () { $(tag).css('background', origBg); }, 200);
        });

        // Toggle auth credentials visibility
        $('#auth_type').on('change changed.bs.select', function () {
            if ($(this).val() === 'none') {
                $('#auth_credentials_group').slideUp(200);
            } else {
                $('#auth_credentials_group').slideDown(200);
            }
        });

        // Toggle GET mode (Overall URL vs Headers + Body)
        function setGetMode(mode) {
            $('#get_mode').val(mode);
            if (mode === 'overall_url') {
                $('#overall_url_group').slideDown(200);
                $('#headers_group').slideUp(200);
                $('#body_template_group').slideUp(200);
            } else {
                $('#overall_url_group').slideUp(200);
                $('#headers_group').slideDown(200);
                $('#body_template_group').slideDown(200);
                $('#body_template_label').text('<?php echo _l("ccx_msgs_api_query_params"); ?>');
            }
        }

        $('input[name="get_mode_radio"]').on('change', function () {
            setGetMode($(this).val());
        });

        // Toggle fields based on request method
        function updateMethodFields() {
            var method = $('#request_method').val();
            if (method === 'GET') {
                $('#get_mode_toggle').slideDown(200);
                setGetMode($('#get_mode').val());
            } else {
                $('#get_mode_toggle').slideUp(200);
                $('#overall_url_group').slideUp(200);
                $('#headers_group').slideDown(200);
                $('#body_template_group').slideDown(200);
                $('#body_template_label').text('<?php echo _l("ccx_msgs_api_body"); ?>');
                $('#body_template').attr('placeholder', '{"to": "{{phone}}", "message": "{{text}}"}');
            }
        }
        $('#request_method').on('change changed.bs.select', updateMethodFields);

        // ── Headers Repeater ──
        var headerRowHtml = '<div class="header-row" style="display:flex; gap:8px; margin-bottom:6px; align-items:center;">' +
            '<input type="text" class="form-control input-sm header-key" placeholder="Key" style="flex:1;">' +
            '<input type="text" class="form-control input-sm header-val" placeholder="Value" style="flex:2;">' +
            '<button type="button" class="btn btn-danger btn-xs btn-remove-header" style="min-width:28px;"><i class="fa fa-times"></i></button>' +
            '</div>';

        $('#btn_add_header').on('click', function () {
            $('#headers_repeater').append(headerRowHtml);
        });

        $(document).on('click', '.btn-remove-header', function () {
            // Keep at least one row
            if ($('#headers_repeater .header-row').length > 1) {
                $(this).closest('.header-row').remove();
            } else {
                $(this).closest('.header-row').find('input').val('');
            }
        });

        // Serialize headers repeater rows to JSON before form submit
        $('#api-form').on('submit', function () {
            var obj = {};
            $('#headers_repeater .header-row').each(function () {
                var k = $(this).find('.header-key').val().trim();
                var v = $(this).find('.header-val').val().trim();
                if (k) obj[k] = v;
            });
            $('#headers_json').val(Object.keys(obj).length ? JSON.stringify(obj) : '');

            // Clear client_id when scope is global
            if ($('input[name="api_scope"]:checked').val() === 'global') {
                $('#client_id').val('');
            }
        });

        // ── API Scope Toggle ──
        $('input[name="api_scope"]').on('change', function () {
            if ($(this).val() === 'client') {
                $('#client_id_group').slideDown(200);
                setTimeout(function () { $('#client_id').selectpicker('refresh'); }, 250);
            } else {
                $('#client_id_group').slideUp(200);
            }
        });

        // ── Email SMTP / API fields toggle ──
        function toggleSmtpFields() {
            var msgType = $('#message_type').val();
            if (msgType === 'email') {
                $('#smtp_fields_group').slideDown(200);
                $('#email_template_fields').slideDown(200);
                $('#api_fields_group').slideUp(200);
                // Toggle dropdown options
                $('.api-target-opt').hide();
                $('.email-target-opt').show();
                $('#api_tag_target').val('email_to_tpl');
                // Relax validations for email
                $('#api-form').data('validator') && $('#api-form').data('validator').destroy();
                // Manual SMTP fields are only required when NOT riding on the
                // CRM's own email settings — otherwise validation blocks Save
                // on fields that are intentionally empty (and frozen by
                // pointer-events:none, so the error can never be corrected).
                var manualSmtpRequired = function () { return !$('#use_crm_smtp').is(':checked'); };
                appValidateForm($('#api-form'), {
                    message_type: 'required',
                    message_subtype: 'required',
                    api_name: 'required',
                    smtp_host: { required: manualSmtpRequired },
                    smtp_from_email: { required: manualSmtpRequired, email: true }
                });
            } else {
                $('#smtp_fields_group').slideUp(200);
                $('#email_template_fields').slideUp(200);
                $('#api_fields_group').slideDown(200);
                // Toggle dropdown options
                $('.api-target-opt').show();
                $('.email-target-opt').hide();
                $('#api_tag_target').val('body_template');
                // Restore normal validations
                $('#api-form').data('validator') && $('#api-form').data('validator').destroy();
                appValidateForm($('#api-form'), {
                    message_type: 'required',
                    message_subtype: 'required',
                    api_name: 'required',
                    api_url: 'required',
                    request_method: 'required'
                });
            }
            // Refresh selectpickers inside SMTP group
            setTimeout(function() { $('#smtp_encryption').selectpicker('refresh'); }, 250);
        }

        $('#message_type').on('change changed.bs.select', function () {
            toggleSmtpFields();
        });

        // Init on load (for edit mode)
        toggleSmtpFields();

        // ── Use CRM Email Settings: dim + disable manual SMTP fields ──
        $('#use_crm_smtp').on('change', function () {
            var on = $(this).is(':checked');
            $('#use_crm_smtp_note').toggle(on);
            $('#manual_smtp_fields').css(on
                ? { opacity: .45, 'pointer-events': 'none' }
                : { opacity: 1, 'pointer-events': 'auto' });
            // Re-validate the manual fields so stale "required" errors
            // don't linger (or appear) after the toggle changes the rules
            var validator = $('#api-form').data('validator');
            if (validator) {
                validator.element('input[name="smtp_host"]');
                validator.element('input[name="smtp_from_email"]');
            }
        });

        // ── Test SMTP Connection (matches Perfex CRM core pattern) ──
        $('#btn_test_smtp').on('click', function() {
            var testEmail = $('#smtp_test_email').val();
            if (!testEmail) {
                alert_float('warning', 'Please enter a test email address.');
                return;
            }

            var $btn = $(this);
            var $status = $('#smtp_test_status');
            var $debugLog = $('#smtp_debug_log');
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Testing...');
            $status.html('');
            $debugLog.hide().text('');

            $.ajax({
                url: admin_url + 'ccx_msgs/test_smtp',
                type: 'POST',
                data: {
                    test_email: testEmail,
                    use_crm_smtp: $('#use_crm_smtp').is(':checked') ? '1' : '0',
                    smtp_host: $('input[name="smtp_host"]').val(),
                    smtp_port: $('input[name="smtp_port"]').val(),
                    smtp_username: $('input[name="smtp_username"]').val(),
                    smtp_password: $('input[name="smtp_password"]').val(),
                    smtp_encryption: $('#smtp_encryption').val(),
                    smtp_from_email: $('input[name="smtp_from_email"]').val(),
                    <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
                },
                dataType: 'json',
                // Never let the button spin forever — the server aborts the
                // SMTP attempt after ~15s, this is the safety net on top
                timeout: 90000,
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color:#16a34a;"><i class="fa fa-check-circle"></i> ' + response.message + '</span>');
                        $debugLog.hide();
                    } else {
                        $status.html('<span style="color:#dc2626;"><i class="fa fa-times-circle"></i> Connection Failed</span>');
                        $debugLog.text(response.message).slideDown(200);
                    }
                },
                error: function(xhr, textStatus) {
                    $status.html('<span style="color:#dc2626;"><i class="fa fa-times-circle"></i> Request failed</span>');
                    var detail = textStatus === 'timeout'
                        ? 'The test request timed out after 90 seconds — the SMTP server is not responding (host/port unreachable from this server, or a firewall is blocking outbound SMTP).'
                        : 'HTTP ' + xhr.status + ': ' + xhr.responseText;
                    $debugLog.text(detail).slideDown(200);
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fa fa-bolt" style="margin-right:4px;"></i>Test');
                }
            });
        });

        // Add email fields to auto-track target
        var allTargetFields = ['api_url', 'overall_url', 'body_template', 'headers', 'email_subject_tpl', 'email_from_name_tpl', 'email_body_tpl'];
        $(document).on('focus click', '#email_subject_tpl, #email_from_name_tpl, #email_body_tpl', function () {
            var fieldId = $(this).attr('id');
            $('#api_tag_target').val(fieldId);
        });
    });
</script>