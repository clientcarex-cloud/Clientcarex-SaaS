<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    /* ===== Business Profile — scoped styles ===== */
    .bizp-hero {
        display: flex;
        align-items: center;
        gap: 16px;
        background: #fff;
        border: 1px solid #e4e7ea;
        border-radius: 12px;
        padding: 18px 22px;
        margin-bottom: 20px;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
    }

    .bizp-hero-icon {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        background: linear-gradient(135deg, #8b5cf6, #4f46e5);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
    }

    .bizp-hero-text h4 {
        margin: 0;
        font-weight: 600;
        font-size: 18px;
    }

    .bizp-hero-text p {
        margin: 3px 0 0;
        color: #748494;
        font-size: 13px;
    }

    .bizp-hero .bizp-save-top {
        margin-left: auto;
    }

    .bizp-card {
        border-radius: 12px;
    }

    .bizp-card .panel-body {
        padding: 22px;
    }

    .bizp-section-head {
        display: flex;
        gap: 12px;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 14px;
        border-bottom: 1px solid #f0f2f5;
    }

    .bizp-section-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }

    .bizp-ic-indigo { background: #eef2ff; color: #4f46e5; }
    .bizp-ic-teal   { background: #ecfdf5; color: #059669; }
    .bizp-ic-violet { background: #f5f3ff; color: #7c3aed; }
    .bizp-ic-amber  { background: #fffbeb; color: #d97706; }
    .bizp-ic-slate  { background: #f1f5f9; color: #475569; }

    .bizp-section-head h5 {
        margin: 0;
        font-weight: 600;
        font-size: 14.5px;
        color: #2d3844;
    }

    .bizp-section-head small {
        display: block;
        color: #8b98a7;
        font-size: 12px;
        margin-top: 2px;
    }

    /* Branding asset tiles */
    .bizp-asset {
        border: 1px dashed #d5dbe3;
        border-radius: 10px;
        padding: 14px;
        margin-bottom: 14px;
        background: #fafbfc;
    }

    .bizp-asset:last-child {
        margin-bottom: 0;
    }

    .bizp-asset-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }

    .bizp-asset-label {
        font-weight: 600;
        font-size: 13px;
        color: #33404e;
    }

    .bizp-asset-hint {
        font-size: 11.5px;
        color: #8b98a7;
        margin: 2px 0 10px;
    }

    .bizp-asset-remove {
        color: #dc2626;
        font-size: 13px;
        padding: 2px 6px;
        border-radius: 6px;
        flex-shrink: 0;
    }

    .bizp-asset-remove:hover {
        background: #fef2f2;
        color: #b91c1c;
    }

    .bizp-asset-preview {
        border: 1px solid #eceff3;
        border-radius: 8px;
        background: #fff;
        min-height: 64px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 12px;
        overflow: hidden;
    }

    .bizp-asset-preview.dark {
        background: #1f2937;
        border-color: #1f2937;
    }

    .bizp-asset-preview img {
        max-height: 60px;
        max-width: 100%;
    }

    .bizp-asset input[type="file"] {
        background: #fff;
    }

    /* Custom fields list */
    .bizp-cf-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .bizp-cf-list li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 14px;
        border: 1px solid #eceff3;
        border-radius: 8px;
        margin-bottom: 8px;
        background: #fafbfc;
        font-size: 13px;
    }

    .bizp-cf-list li:last-child {
        margin-bottom: 0;
    }

    .bizp-cf-tag {
        font-family: monospace;
        font-size: 12px;
        background: #eef2ff;
        color: #4f46e5;
        border-radius: 6px;
        padding: 2px 8px;
        white-space: nowrap;
    }

    @media (max-width: 767px) {
        .bizp-hero {
            flex-wrap: wrap;
        }

        .bizp-hero .bizp-save-top {
            margin-left: 0;
            width: 100%;
        }
    }
</style>
<div id="wrapper">
    <div class="content">
        <?php echo form_open_multipart($this->uri->uri_string(), ['id' => 'biz-profile-form']); ?>

        <!-- Page header -->
        <div class="bizp-hero">
            <div class="bizp-hero-icon"><i class="fa fa-building"></i></div>
            <div class="bizp-hero-text">
                <h4><?php echo $title; ?></h4>
                <p>Your organisation's identity, branding and contact details — all in one place.</p>
            </div>
            <button type="submit" class="btn btn-info bizp-save-top">
                <i class="fa fa-check"></i> <?php echo _l('settings_save'); ?>
            </button>
        </div>

        <div class="row">
            <!-- LEFT COLUMN -->
            <div class="col-md-8">

                <!-- Company Identity -->
                <div class="panel_s bizp-card">
                    <div class="panel-body">
                        <div class="bizp-section-head">
                            <span class="bizp-section-icon bizp-ic-indigo"><i class="fa fa-id-card"></i></span>
                            <div>
                                <h5>Company Identity</h5>
                                <small>Core details that identify your business across the system</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <?php $attrs = (get_option('companyname') != '' ? [] : ['autofocus' => true]); ?>
                                <?php echo render_input('settings[companyname]', 'settings_general_company_name', get_option('companyname'), 'text', $attrs); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo render_input('settings[main_domain]', 'settings_general_company_main_domain', get_option('main_domain')); ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo render_input('settings[clinic_registration_no]', 'Clinic Registration No', get_option('clinic_registration_no')); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo render_input('settings[invoice_company_name]', 'settings_sales_company_name', get_option('invoice_company_name')); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact & Address -->
                <div class="panel_s bizp-card">
                    <div class="panel-body">
                        <div class="bizp-section-head">
                            <span class="bizp-section-icon bizp-ic-teal"><i class="fa fa-map-marker"></i></span>
                            <div>
                                <h5>Contact &amp; Address</h5>
                                <small>Shown on invoices, receipts and printed documents</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo render_input('settings[invoice_company_phonenumber]', 'settings_sales_phonenumber', get_option('invoice_company_phonenumber')); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo render_input('settings[invoice_company_city]', 'settings_sales_city', get_option('invoice_company_city')); ?>
                            </div>
                        </div>
                        <?php echo render_textarea('settings[invoice_company_address]', 'settings_sales_address', get_option('invoice_company_address'), ['rows' => 2]); ?>
                        <div class="row">
                            <div class="col-md-4">
                                <?php echo render_input('settings[company_state]', 'billing_state', get_option('company_state')); ?>
                            </div>
                            <div class="col-md-4">
                                <?php echo render_input('settings[invoice_company_country_code]', 'settings_sales_country_code', get_option('invoice_company_country_code')); ?>
                            </div>
                            <div class="col-md-4">
                                <?php echo render_input('settings[invoice_company_postal_code]', 'settings_sales_postal_code', get_option('invoice_company_postal_code')); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php $custom_company_fields = get_company_custom_fields(); ?>
                <?php if (count($custom_company_fields) > 0) { ?>
                <!-- Custom Fields -->
                <div class="panel_s bizp-card">
                    <div class="panel-body">
                        <div class="bizp-section-head">
                            <span class="bizp-section-icon bizp-ic-amber"><i class="fa fa-tags"></i></span>
                            <div>
                                <h5><?php echo _l('custom_fields'); ?></h5>
                                <small>Merge fields available for the company information format</small>
                            </div>
                        </div>
                        <ul class="bizp-cf-list">
                            <?php foreach ($custom_company_fields as $field) { ?>
                            <li>
                                <span><?php echo html_escape($field['name']); ?></span>
                                <a href="#" class="settings-textarea-merge-field bizp-cf-tag"
                                    data-to="company_info_format">{cf_<?php echo $field['id']; ?>}</a>
                            </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
                <?php } ?>

                <?php if (!empty($biz_client_id)) { ?>
                <!-- Contacts -->
                <div class="panel_s bizp-card">
                    <div class="panel-body">
                        <div class="bizp-section-head">
                            <span class="bizp-section-icon bizp-ic-teal"><i class="fa fa-users"></i></span>
                            <div style="flex:1;">
                                <h5><?php echo _l('contacts'); ?></h5>
                                <small>People linked to your account — used for billing &amp; important notices</small>
                            </div>
                            <button type="button" class="btn btn-info btn-sm" data-toggle="modal"
                                data-target="#bizp-add-contact-modal">
                                <i class="fa fa-plus"></i> <?php echo _l('contact'); ?>
                            </button>
                        </div>

                        <?php if (empty($biz_contacts)) { ?>
                        <p class="text-muted no-margin">No contacts added yet.</p>
                        <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table bizp-contacts-table" style="margin-bottom:0;">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('client_firstname'); ?></th>
                                        <th><?php echo _l('client_email'); ?></th>
                                        <th><?php echo _l('client_phonenumber'); ?></th>
                                        <th><?php echo _l('contact_position'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($biz_contacts as $c) { ?>
                                    <tr>
                                        <td>
                                            <?php echo html_escape(trim($c->firstname . ' ' . $c->lastname)); ?>
                                            <?php if ((int) $c->is_primary === 1) { ?>
                                            <span class="label label-info" style="margin-left:4px;">Primary</span>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo html_escape($c->email); ?></td>
                                        <td><?php echo html_escape($c->phonenumber); ?></td>
                                        <td><?php echo html_escape($c->title); ?></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>

            </div>

            <!-- RIGHT COLUMN -->
            <div class="col-md-4">

                <!-- Branding -->
                <div class="panel_s bizp-card">
                    <div class="panel-body">
                        <div class="bizp-section-head">
                            <span class="bizp-section-icon bizp-ic-violet"><i class="fa fa-image"></i></span>
                            <div>
                                <h5>Branding</h5>
                                <small>Logos and icons used across the app and documents</small>
                            </div>
                        </div>

                        <?php
                        $brand_assets = [
                            [
                                'name'   => 'company_logo',
                                'label'  => _l('company_logo_light'),
                                'hint'   => 'Recommended: 250 × 50 px',
                                'value'  => get_option('company_logo'),
                                'remove' => admin_url('biz_profile/remove_company_logo'),
                                'dark'   => false,
                            ],
                            [
                                'name'   => 'company_logo_dark',
                                'label'  => _l('company_logo_dark'),
                                'hint'   => 'Recommended: 250 × 50 px — used on dark backgrounds',
                                'value'  => get_option('company_logo_dark'),
                                'remove' => admin_url('biz_profile/remove_company_logo/dark'),
                                'dark'   => true,
                            ],
                            [
                                'name'    => 'favicon',
                                'label'   => _l('settings_general_favicon'),
                                'hint'    => 'Recommended: 32 × 32 px (.png or .ico)',
                                'value'   => get_option('favicon'),
                                'remove'  => admin_url('biz_profile/remove_fv'),
                                'dark'    => false,
                                'default' => base_url('assets/images/favicon.png'),
                            ],
                            [
                                'name'   => 'company_letterhead',
                                'label'  => 'Letterhead',
                                'hint'   => 'Full-width image used on printed documents',
                                'value'  => get_option('company_letterhead'),
                                'remove' => admin_url('biz_profile/remove_letterhead'),
                                'dark'   => false,
                            ],
                        ];
                        ?>
                        <?php foreach ($brand_assets as $asset) { ?>
                        <div class="bizp-asset">
                            <div class="bizp-asset-top">
                                <div>
                                    <div class="bizp-asset-label"><?php echo $asset['label']; ?></div>
                                    <div class="bizp-asset-hint"><?php echo $asset['hint']; ?></div>
                                </div>
                                <?php if ($asset['value'] != '') { ?>
                                <a href="<?php echo $asset['remove']; ?>" class="_delete bizp-asset-remove"
                                    data-toggle="tooltip" title="Remove"><i class="fa fa-trash"></i></a>
                                <?php } ?>
                            </div>
                            <?php if ($asset['value'] != '') { ?>
                            <div class="bizp-asset-preview<?php echo $asset['dark'] ? ' dark' : ''; ?>">
                                <img src="<?php echo base_url('uploads/company/' . html_escape($asset['value'])); ?>"
                                    alt="<?php echo html_escape($asset['label']); ?>">
                            </div>
                            <?php } else { ?>
                            <?php if (!empty($asset['default'])) { ?>
                            <div class="bizp-asset-preview">
                                <img src="<?php echo $asset['default']; ?>"
                                    alt="<?php echo html_escape($asset['label']); ?>" style="max-height:32px;">
                            </div>
                            <div class="bizp-asset-hint mbot10">Default icon in use — upload to override</div>
                            <?php } ?>
                            <input type="file" name="<?php echo $asset['name']; ?>" class="form-control">
                            <?php } ?>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                <!-- System Preferences -->
                <div class="panel_s bizp-card">
                    <div class="panel-body">
                        <div class="bizp-section-head">
                            <span class="bizp-section-icon bizp-ic-slate"><i class="fa fa-sliders"></i></span>
                            <div>
                                <h5>System Preferences</h5>
                                <small>General behaviour options</small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="timezones" class="control-label"><?php echo _l('settings_localization_default_timezone'); ?></label>
                            <select name="settings[default_timezone]" id="timezones" class="form-control selectpicker"
                                data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>"
                                data-live-search="true">
                                <?php foreach (get_timezones_list() as $key => $timezones) { ?>
                                <optgroup label="<?php echo e($key); ?>">
                                    <?php foreach ($timezones as $timezone) { ?>
                                    <option value="<?php echo e($timezone); ?>" <?php if (get_option('default_timezone') == $timezone) {
                                        echo 'selected';
                                    } ?>><?php echo e($timezone); ?></option>
                                    <?php } ?>
                                </optgroup>
                                <?php } ?>
                            </select>
                        </div>
                        <?php echo render_yes_no_option('rtl_support_admin', 'settings_rtl_support_admin'); ?>
                        <?php echo render_input('settings[allowed_files]', 'settings_allowed_upload_file_types', get_option('allowed_files')); ?>
                    </div>
                </div>

            </div>
        </div>

        <div class="btn-bottom-toolbar text-right">
            <button type="submit" class="btn btn-info">
                <?php echo _l('settings_save'); ?>
            </button>
        </div>
        <div class="btn-bottom-pusher"></div>
        <?php echo form_close(); ?>

    </div>
</div>

<?php if (!empty($biz_client_id)) { ?>
<!-- Add Contact modal — kept outside the settings form so its own form is not nested -->
<div class="modal fade" id="bizp-add-contact-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <?php echo form_open(admin_url('biz_profile/add_contact'), ['id' => 'bizp-add-contact-form']); ?>
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title"><?php echo _l('new_contact'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <?php echo render_input('firstname', 'client_firstname', '', 'text', ['required' => true]); ?>
                    </div>
                    <div class="col-md-6">
                        <?php echo render_input('lastname', 'client_lastname'); ?>
                    </div>
                </div>
                <?php echo render_input('email', 'client_email', '', 'email', ['required' => true]); ?>
                <?php echo render_input('phonenumber', 'client_phonenumber', '', 'text', ['autocomplete' => 'off']); ?>
                <?php echo render_input('title', 'contact_position'); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>
<?php } ?>
<?php init_tail(); ?>
</body>

</html>
