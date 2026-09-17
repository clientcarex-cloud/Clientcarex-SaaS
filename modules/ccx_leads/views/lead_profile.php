<?php defined('BASEPATH') or exit('No direct script access allowed');
// Field settings helpers
$_fs = isset($field_settings) ? $field_settings : [];
if (!function_exists('ccx_field_active')) {
    function ccx_field_active($slug)
    {
        global $_fs;
        return !isset($_fs[$slug]) || $_fs[$slug]['active'] == 1;
    }
}
if (!function_exists('ccx_field_label')) {
    function ccx_field_label($slug, $default = '')
    {
        global $_fs;
        return isset($_fs[$slug]) && !empty($_fs[$slug]['label']) ? $_fs[$slug]['label'] : $default;
    }
}
if (!function_exists('ccx_field_required')) {
    function ccx_field_required($slug)
    {
        global $_fs;
        return isset($_fs[$slug]) && $_fs[$slug]['required'] == 1;
    }
}
if (!function_exists('ccx_field_order')) {
    function ccx_field_order($slug)
    {
        global $_fs;
        return isset($_fs[$slug]) && isset($_fs[$slug]['field_order']) ? (int) $_fs[$slug]['field_order'] : 999;
    }
}
?>
<div
    class="lead-wrapper<?= $openEdit == true ? ' open-edit' : ''; ?><?= isset($lead) && ($lead->junk == 1 || $lead->lost == 1) ? ' lead-is-junk-or-lost' : ''; ?>">

    <?php if (isset($lead)) { ?>
        <div class="tw-flex tw-items-center tw-justify-end tw-space-x-1.5">


            <div class="<?= $lead_locked == true ? ' hide' : ''; ?>">
                <a href="#" ccx-lead-edit data-toggle="tooltip" data-title="<?= _l('edit'); ?>"
                    class="btn btn-default lead-top-btn !tw-px-3">

                    <i class="fa-regular fa-pen-to-square"></i>
                </a>
            </div>

            <div class="btn-group" id="lead-more-btn">
                <a href="#" class="btn btn-default dropdown-toggle lead-top-btn" data-toggle="dropdown" aria-haspopup="true"
                    aria-expanded="false">
                    <?= _l('more'); ?>
                    <span class="caret"></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-right" id="lead-more-dropdown">
                    <?php if ($lead->junk == 0) {
                        if ($lead->lost == 0 && (total_rows(db_prefix() . 'clients', ['leadid' => $lead->id]) == 0)) { ?>
                            <li>
                                <a href="#" onclick="lead_mark_as_lost(<?= e($lead->id); ?>); return false;">
                                    <i class="fa fa-mars"></i>
                                    <?= _l('lead_mark_as_lost'); ?>
                                </a>
                            </li>
                        <?php } elseif ($lead->lost == 1) { ?>
                            <li>
                                <a href="#" onclick="lead_unmark_as_lost(<?= e($lead->id); ?>); return false;">
                                    <i class="fa fa-smile-o"></i>
                                    <?= _l('lead_unmark_as_lost'); ?>
                                </a>
                            </li>
                        <?php } ?>
                    <?php } ?>
                    <!-- mark as junk -->
                    <?php if ($lead->lost == 0) {
                        if ($lead->junk == 0 && (total_rows(db_prefix() . 'clients', ['leadid' => $lead->id]) == 0)) { ?>
                            <li>
                                <a href="#" onclick="lead_mark_as_junk(<?= e($lead->id); ?>); return false;">
                                    <i class="fa fa fa-times"></i>
                                    <?= _l('lead_mark_as_junk'); ?>
                                </a>
                            </li>
                        <?php } elseif ($lead->junk == 1) { ?>
                            <li>
                                <a href="#" onclick="lead_unmark_as_junk(<?= e($lead->id); ?>); return false;">
                                    <i class="fa fa-smile-o"></i>
                                    <?= _l('lead_unmark_as_junk'); ?>
                                </a>
                            </li>
                        <?php } ?>
                    <?php } ?>
                    <?php if ((staff_can('delete', 'leads') && $lead_locked == false) || is_admin()) { ?>
                        <li>
                            <a href="<?= admin_url('leads/delete/' . $lead->id); ?>" class="text-danger delete-text _delete"
                                data-toggle="tooltip" title="">
                                <i class="fa-regular fa-trash-can"></i>
                                <?= _l('lead_edit_delete_tooltip'); ?>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    <?php } ?>

    <div class="clearfix no-margin"></div>

    <?php if (isset($lead)) { ?>

        <div class="row mbot15" style="margin-top:12px;">
            <hr class="no-margin" />
        </div>

        <div class="alert alert-warning hide mtop20" role="alert" id="lead_proposal_warning">
            <?= _l('proposal_warning_email_change', [_l('lead_lowercase'), _l('lead_lowercase'), _l('lead_lowercase')]); ?>
            <hr />
            <a href="#" onclick="update_all_proposal_emails_linked_to_lead(<?= e($lead->id); ?>); return false;"
                class="alert-link">
                <?= _l('update_proposal_email_yes'); ?>
            </a>
            <br />
            <a href="#" onclick="init_lead_modal_data(<?= e($lead->id); ?>); return false;" class="alert-link">
                <?= _l('update_proposal_email_no'); ?>
            </a>
        </div>
    <?php } ?>
    <?= form_open((isset($lead) ? admin_url('leads/lead/' . $lead->id) : admin_url('leads/lead')), ['id' => 'lead_form']); ?>
    <div class="row">
        <div class="lead-view<?= !isset($lead) ? ' hide' : ''; ?>" id="leadViewWrapper">
            <div class="col-md-4 col-xs-12 lead-information-col">
                <div class="lead-info-heading">
                    <h4>
                        <?= _l('lead_info'); ?>
                    </h4>
                </div>
                <dl>
                    <?php if (ccx_field_active('name')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('name', _l('lead_add_edit_name')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1 lead-name">
                            <?= isset($lead) && $lead->name != '' ? e($lead->name) : '-' ?>
                        </dd>
                    <?php } ?>
                    <?php if (ccx_field_active('title')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('title', _l('lead_title')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1">
                            <?= isset($lead) && $lead->title != '' ? e($lead->title) : '-' ?>
                        </dd>
                    <?php } ?>
                    <?php if (ccx_field_active('email')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('email', _l('lead_add_edit_email')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1">
                            <?= isset($lead) && $lead->email != '' ? '<a href="mailto:' . e($lead->email) . '">' . e($lead->email) . '</a>' : '-' ?>
                        </dd>
                    <?php } ?>
                    <?php if (ccx_field_active('website')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('website', _l('lead_website')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1">
                            <?= isset($lead) && $lead->website != '' ? '<a href="' . e(maybe_add_http($lead->website)) . '" target="_blank">' . e($lead->website) . '</a>' : '-' ?>
                        </dd>
                    <?php } ?>
                    <?php if (ccx_field_active('phonenumber')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('phonenumber', _l('lead_add_edit_phonenumber')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1">
                            <?= isset($lead) && $lead->phonenumber != '' ? '<a href="tel:' . e($lead->phonenumber) . '">' . e($lead->phonenumber) . '</a>' : '-' ?>
                        </dd>
                    <?php } ?>
                    <?php if (ccx_field_active('lead_value')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('lead_value', _l('lead_value')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1">
                            <?= isset($lead) && $lead->lead_value != 0 ? e(app_format_money($lead->lead_value, $base_currency->id)) : '-' ?>
                        </dd>
                    <?php } ?>
                    <?php if (ccx_field_active('company')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('company', _l('lead_company')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1">
                            <?= isset($lead) && $lead->company != '' ? e($lead->company) : '-' ?>
                        </dd>
                    <?php } ?>
                    <?php if (ccx_field_active('address')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('address', _l('lead_address')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1 tw-whitespace-pre-line">
                            <?= isset($lead) && $lead->address != '' ? e(clear_textarea_breaks($lead->address)) : '-' ?>
                        </dd>
                    <?php } ?>
                    <?php if (ccx_field_active('city')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('city', _l('lead_city')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1">
                            <?= isset($lead) && $lead->city != '' ? e($lead->city) : '-' ?>
                        </dd>
                    <?php } ?>
                    <?php if (ccx_field_active('state')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('state', _l('lead_state')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1">
                            <?= isset($lead) && $lead->state != '' ? e($lead->state) : '-' ?>
                        </dd>
                    <?php } ?>
                    <?php if (ccx_field_active('country')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('country', _l('lead_country')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1">
                            <?= isset($lead) && $lead->country != 0 ? e(get_country($lead->country)->short_name) : '-' ?>
                        </dd>
                    <?php } ?>
                    <?php if (ccx_field_active('zip')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('zip', _l('lead_zip')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1">
                            <?= isset($lead) && $lead->zip != '' ? e($lead->zip) : '-' ?>
                        </dd>
                    <?php } ?>
                </dl>
            </div>
            <div class="col-md-4 col-xs-12 lead-information-col">
                <div class="lead-info-heading">
                    <h4>
                        <?= _l('lead_general_info'); ?>
                    </h4>
                </div>
                <dl>
                    <?php if (ccx_field_active('status')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500 no-mtop">
                            <?= ccx_field_label('status', _l('lead_add_edit_status')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-2 mbot15">
                            <?php if (isset($lead)) {
                                echo $lead->status_name != '' ? ('<span class="lead-status-' . e($lead->status) . ' label' . (empty($lead->color) ? ' label-default' : '') . '" style="color:' . e($lead->color) . ';border:1px solid ' . adjust_hex_brightness($lead->color, 0.4) . ';background: ' . adjust_hex_brightness($lead->color, 0.04) . ';">' . e($lead->status_name) . '</span>') : '-';
                            } else {
                                echo '-';
                            } ?>
                        </dd>
                    <?php } ?>
                    <?php if (ccx_field_active('source')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('source', _l('lead_add_edit_source')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1 mbot15">
                            <?= isset($lead) && $lead->source_name != '' ? e($lead->source_name) : '-' ?>
                        </dd>
                    <?php } ?>
                    <?php if (!is_language_disabled()) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= _l('localization_default_language'); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1 mbot15">
                            <?= isset($lead) && $lead->default_language != '' ? e(ucfirst($lead->default_language)) : _l('system_default_string') ?>
                        </dd>
                    <?php } ?>
                    <?php if (ccx_field_active('assigned')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('assigned', _l('lead_add_edit_assigned')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1 mbot15">
                            <?= isset($lead) && $lead->assigned != 0 ? e(get_staff_full_name($lead->assigned)) : '-' ?>
                        </dd>
                    <?php } ?>

                    <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                        <?= _l('leads_dt_datecreated'); ?>
                    </dt>
                    <dd class="tw-text-neutral-900 tw-mt-1">
                        <?= isset($lead) && $lead->dateadded != '' ? '<span class="text-has-action" data-toggle="tooltip" data-title="' . e(_dt($lead->dateadded)) . '">' . e(time_ago($lead->dateadded)) . '</span>' : '-' ?>
                    </dd>
                    <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                        <?= _l('leads_dt_last_contact'); ?>
                    </dt>
                    <dd class="tw-text-neutral-900 tw-mt-1">
                        <?= isset($lead) && $lead->lastcontact != '' ? '<span class="text-has-action" data-toggle="tooltip" data-title="' . e(_dt($lead->lastcontact)) . '">' . e(time_ago($lead->lastcontact)) . '</span>' : '-' ?>
                    </dd>
                    <?php if (ccx_field_active('is_public')) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('is_public', _l('lead_public')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1 mbot15">
                            <?php if (isset($lead)) {
                                if ($lead->is_public == 1) {
                                    echo _l('lead_is_public_yes');
                                } else {
                                    echo _l('lead_is_public_no');
                                }
                            } else {
                                echo '-';
                            } ?>
                        </dd>
                    <?php } ?>
                    <?php if (isset($lead) && $lead->from_form_id != 0) { ?>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= _l('web_to_lead_form'); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1 mbot15">
                            <?= e($lead->form_data->name); ?>
                        </dd>
                    <?php } ?>
                </dl>
            </div>
            <div class="col-md-4 col-xs-12 lead-information-col">
                <?php if (total_rows(db_prefix() . 'customfields', ['fieldto' => 'leads', 'active' => 1]) > 0 && isset($lead)) { ?>
                    <div class="lead-info-heading">
                        <h4>
                            <?= _l('custom_fields'); ?>
                        </h4>
                    </div>
                    <dl>
                        <?php foreach (get_custom_fields('leads') as $field) { ?>
                            <?php $value = get_custom_field_value($lead->id, $field['id'], 'leads'); ?>
                            <dt class="lead-field-heading tw-font-normal tw-text-neutral-500 no-mtop">
                                <?= e($field['name']); ?>
                            </dt>
                            <dd class="tw-text-neutral-900 tw-mt-1 tw-break-words">
                                <?= $value != '' ? $value : '-' ?>
                            </dd>
                        <?php } ?>
                    <?php } ?>
                </dl>
            </div>
            <div class="clearfix"></div>
            <div class="col-md-12">
                <?php if (ccx_field_active('description')) { ?>
                    <dl>
                        <dt class="lead-field-heading tw-font-normal tw-text-neutral-500">
                            <?= ccx_field_label('description', _l('lead_description')); ?>
                        </dt>
                        <dd class="tw-text-neutral-900 tw-mt-1">
                            <?= process_text_content_for_display((isset($lead) && $lead->description != '' ? $lead->description : '-')); ?>
                        </dd>
                    </dl>
                <?php } ?>
            </div>
        </div>
        <div class="clearfix"></div>
        <div class="lead-edit<?= isset($lead) ? ' hide' : ''; ?>">
            <?php
            // 3-column layout from saved settings
            $_layout = isset($field_layout) ? $field_layout : ['1' => [], '2' => [], '3' => []];

            // Helper to get standard field slugs for a column
            function ccx_col_edit_slugs($layout, $col)
            {
                $slugs = [];
                if (isset($layout[$col])) {
                    foreach ($layout[$col] as $fi) {
                        if ($fi['type'] === 'standard')
                            $slugs[] = $fi['slug'];
                    }
                }
                return $slugs;
            }

            // Helper to check if column has custom fields
            function ccx_col_edit_has_custom($layout, $col)
            {
                if (isset($layout[$col])) {
                    foreach ($layout[$col] as $fi) {
                        if ($fi['type'] === 'custom')
                            return true;
                    }
                }
                return false;
            }

            // Helper to render a standard edit field
            function ccx_render_edit_field($slug, $lead, $statuses, $sources, $members, $base_currency, $status_id = null)
            {
                global $_fs;
                if (!ccx_field_active($slug))
                    return;

                switch ($slug) {
                    case 'status':
                        $selected = '';
                        if (isset($lead))
                            $selected = $lead->status;
                        elseif (isset($status_id))
                            $selected = $status_id;
                        echo render_leads_status_select($statuses, $selected, 'lead_add_edit_status');
                        break;
                    case 'source':
                        echo render_leads_source_select($sources, (isset($lead) ? $lead->source : get_option('leads_default_source')), 'lead_add_edit_source');
                        break;
                    case 'assigned':
                        $assigned_attrs = [];
                        $selected = (isset($lead) ? $lead->assigned : get_staff_user_id());
                        if (isset($lead) && $lead->assigned == get_staff_user_id() && $lead->addedfrom != get_staff_user_id() && !is_admin($lead->assigned) && staff_cant('view', 'leads')) {
                            $assigned_attrs['disabled'] = true;
                        }
                        echo render_select('assigned', $members, ['staffid', ['firstname', 'lastname']], 'lead_add_edit_assigned', $selected, $assigned_attrs);
                        break;
                    case 'website':
                        if ((isset($lead) && empty($lead->website)) || !isset($lead)) {
                            echo render_input('website', ccx_field_label('website', 'lead_website'), (isset($lead) ? $lead->website : ''));
                        } else { ?>
                            <div class="form-group">
                                <label for="website"><?= ccx_field_label('website', _l('lead_website')); ?></label>
                                <div class="input-group">
                                    <input type="text" name="website" id="website" value="<?= e($lead->website); ?>"
                                        class="form-control">
                                    <div class="input-group-addon"><a href="<?= e(maybe_add_http($lead->website)); ?>" target="_blank"
                                            tabindex="-1"><i class="fa fa-globe"></i></a></div>
                                </div>
                            </div>
                        <?php }
                        break;
                    case 'lead_value': ?>
                        <div class="form-group">
                            <label for="lead_value"><?= ccx_field_label('lead_value', _l('lead_value')); ?></label>
                            <div class="input-group" data-toggle="tooltip" title="<?= _l('lead_value_tooltip'); ?>">
                                <input type="number" class="form-control" name="lead_value"
                                    value="<?= isset($lead) ? $lead->lead_value : ''; ?>">
                                <div class="input-group-addon"><?= e($base_currency->symbol); ?></div>
                            </div>
                        </div>
                        <?php break;
                    case 'address':
                        echo render_textarea('address', ccx_field_label('address', 'lead_address'), (isset($lead) ? $lead->address : ''), ['rows' => 1, 'style' => 'height:36px;font-size:100%;']);
                        break;
                    case 'country':
                        $countries = get_all_countries();
                        $customer_default_country = get_option('customer_default_country');
                        $selected = (isset($lead) ? $lead->country : $customer_default_country);
                        echo render_select('country', $countries, ['country_id', ['short_name']], ccx_field_label('country', 'lead_country'), $selected, ['data-none-selected-text' => _l('dropdown_non_selected_tex')]);
                        break;
                    case 'description':
                        echo render_textarea('description', ccx_field_label('description', 'lead_description'), (isset($lead) ? $lead->description : ''));
                        break;
                    case 'is_public': ?>
                        <div
                            class="checkbox-inline checkbox<?= isset($lead) ? ' hide' : ''; ?><?= isset($lead) && (is_lead_creator($lead->id) || staff_can('edit', 'leads')) ? ' lead-edit' : ''; ?>">
                            <input type="checkbox" name="is_public" <?= isset($lead) && $lead->is_public ? 'checked' : ''; ?>
                                id="lead_public">
                            <label for="lead_public"><?= ccx_field_label('is_public', _l('lead_public')); ?></label>
                        </div>
                        <?php break;
                    default:
                        // Text fields: name, title, email, phonenumber, city, state, zip, company
                        $labels = [
                            'name' => 'lead_add_edit_name',
                            'title' => 'lead_title',
                            'email' => 'lead_add_edit_email',
                            'phonenumber' => 'lead_add_edit_phonenumber',
                            'city' => 'lead_city',
                            'state' => 'lead_state',
                            'zip' => 'lead_zip',
                            'company' => 'lead_company',
                        ];
                        $lbl = isset($labels[$slug]) ? $labels[$slug] : $slug;
                        $val = (isset($lead) && isset($lead->$slug)) ? $lead->$slug : '';
                        echo render_input($slug, ccx_field_label($slug, $lbl), $val, 'text', ccx_field_required($slug) ? ['required' => true] : []);
                        break;
                }
            }

            // ==================== COLUMN 1 — Top Row ====================
            $col1 = ccx_col_edit_slugs($_layout, '1');
            if (!empty($col1)) { ?>
                <div class="row">
                    <?php foreach ($col1 as $slug) { ?>
                        <div class="col-md-4">
                            <?php ccx_render_edit_field($slug, $lead ?? null, $statuses, $sources, $members, $base_currency, $status_id ?? null); ?>
                        </div>
                    <?php } ?>
                </div>
                <div class="clearfix"></div>
            <?php } ?>

            <?php $rel_id = (isset($lead) ? $lead->id : false); ?>
            <div class="row">
                <?php
                // ==================== COLUMN 2 — Left ====================
                $col2 = ccx_col_edit_slugs($_layout, '2');
                ?>
                <div class="col-md-6">
                    <?php foreach ($col2 as $slug) {
                        ccx_render_edit_field($slug, $lead ?? null, $statuses, $sources, $members, $base_currency, $status_id ?? null);
                    } ?>
                    <?php if (ccx_col_edit_has_custom($_layout, '2')) {
                        echo render_custom_fields('leads', $rel_id);
                    } ?>
                </div>

                <?php
                // ==================== COLUMN 3 — Right ====================
                $col3 = ccx_col_edit_slugs($_layout, '3');
                ?>
                <div class="col-md-6">
                    <?php foreach ($col3 as $slug) {
                        ccx_render_edit_field($slug, $lead ?? null, $statuses, $sources, $members, $base_currency, $status_id ?? null);
                    } ?>
                    <?php if (ccx_col_edit_has_custom($_layout, '3') && !ccx_col_edit_has_custom($_layout, '2')) {
                        echo render_custom_fields('leads', $rel_id);
                    } ?>
                </div>
            </div>

            <?php
            // Custom fields at bottom if not in col 2 or 3
            if (!ccx_col_edit_has_custom($_layout, '2') && !ccx_col_edit_has_custom($_layout, '3')) {
                $cf_html = render_custom_fields('leads', $rel_id);
                if (trim($cf_html) != '') {
                    echo '<div class="row"><div class="col-md-12">' . $cf_html . '</div></div>';
                }
            }
            ?>

            <?php if (!is_language_disabled()) { ?>
                <div class="form-group">
                    <label for="default_language" class="control-label"><?= _l('localization_default_language'); ?></label>
                    <select name="default_language" data-live-search="true" id="default_language"
                        class="form-control selectpicker" data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                        <option value=""><?= _l('system_default_string'); ?></option>
                        <?php foreach ($this->app->get_available_languages() as $availableLanguage) {
                            $selected = '';
                            if (isset($lead) && $lead->default_language == $availableLanguage) {
                                $selected = 'selected';
                            } ?>
                            <option value="<?= e($availableLanguage); ?>" <?= e($selected); ?>>
                                <?= e(ucfirst($availableLanguage)); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            <?php } ?>

            <div class="col-md-12">
                <div class="row">
                    <div class="col-md-12">
                        <?php if (!isset($lead)) { ?>
                            <div class="lead-select-date-contacted hide">
                                <?= render_datetime_input('custom_contact_date', 'lead_add_edit_datecontacted', '', ['data-date-end-date' => date('Y-m-d')]); ?>
                            </div>
                        <?php } else { ?>
                            <?= render_datetime_input('lastcontact', 'leads_dt_last_contact', _dt($lead->lastcontact), ['data-date-end-date' => date('Y-m-d')]); ?>
                        <?php } ?>
                        <?php if (!isset($lead)) { ?>
                            <div class="checkbox-inline checkbox checkbox-primary">
                                <input type="checkbox" name="contacted_today" id="contacted_today" checked>
                                <label for="contacted_today"><?= _l('lead_add_edit_contacted_today'); ?></label>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="clearfix"></div>
        </div>
    </div>
    <?php if (isset($lead)) { ?>
        <div class="lead-latest-activity tw-mb-3 lead-view">
            <div class="lead-info-heading">
                <h4><?= _l('lead_latest_activity'); ?>
                </h4>
            </div>
            <div id="lead-latest-activity" class="pleft5"></div>
        </div>
    <?php } ?>
    <?php if ($lead_locked == false) { ?>
        <div class="lead-edit<?= isset($lead) ? ' hide' : ''; ?>">
            <hr class="-tw-mx-5 tw-border-neutral-200" />
            <button type="submit" class="btn btn-primary pull-right lead-save-btn" id="lead-form-submit">
                <?= _l('submit'); ?>
            </button>
            <button type=" button" class="btn btn-default pull-right mright5" data-dismiss="modal">
                <?= _l('close'); ?>
            </button>
        </div>
    <?php } ?>
    <div class="clearfix"></div>
    <?= form_close(); ?>
</div>
<?php if (isset($lead) && $lead_locked == true) { ?>
    <script>
        $(function () {
            // Set all fields to disabled if lead is locked
            $.each($('.lead-wrapper').find('input, select, textarea'), function () {
                $(this).attr('disabled', true);
                if ($(this).is('select')) {
                    $(this).selectpicker('refresh');
                }
            });
        });
    </script>
<?php } ?>