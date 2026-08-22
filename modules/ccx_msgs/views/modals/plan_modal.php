<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php echo form_open(admin_url('ccx_msgs/save_plan'), ['id' => 'plan-form']); ?>
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
            aria-hidden="true">&times;</span></button>
    <h4 class="modal-title">
        <?php echo isset($plan) ? _l('edit') . ' ' . _l('pricing_plan') : _l('new_pricing_plan'); ?>
    </h4>
</div>
<div class="modal-body">
    <div class="row">
        <div class="col-md-12">
            <?php if (isset($plan)) { ?>
                <input type="hidden" name="id" value="<?php echo $plan->id; ?>">
            <?php } else { ?>
                <input type="hidden" name="id" value="">
            <?php } ?>

            <?php $selected_type = isset($plan) ? $plan->message_type : ''; ?>
            <div class="form-group" app-field-wrapper="message_type">
                <label for="message_type" class="control-label"><?php echo _l('plan_message_type'); ?></label>
                <select id="message_type" name="message_type" class="selectpicker" data-width="100%"
                    data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" data-live-search="false">
                    <option value=""></option>
                    <option value="sms" <?php if ($selected_type == 'sms') {
                        echo 'selected';
                    } ?>>
                        <?php echo _l('ccx_msgs_sms'); ?>
                    </option>
                    <option value="whatsapp" <?php if ($selected_type == 'whatsapp') {
                        echo 'selected';
                    } ?>>
                        <?php echo _l('ccx_msgs_whatsapp'); ?>
                    </option>
                    <option value="email" <?php if ($selected_type == 'email') {
                        echo 'selected';
                    } ?>>
                        <?php echo _l('ccx_msgs_email'); ?>
                    </option>
                    <option value="aicall" <?php if ($selected_type == 'aicall') {
                        echo 'selected';
                    } ?>>
                        <?php echo _l('ccx_msgs_aicall'); ?>
                    </option>
                </select>
            </div>

            <?php $selected_subtype = isset($plan) ? $plan->message_subtype : 'promotional'; ?>
            <div class="form-group" app-field-wrapper="message_subtype">
                <label for="message_subtype" class="control-label"><?php echo _l('plan_message_subtype'); ?></label>
                <select id="message_subtype" name="message_subtype" class="selectpicker" data-width="100%"
                    data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" data-live-search="false">
                    <option value="promotional" <?php if ($selected_subtype == 'promotional') { echo 'selected'; } ?>>
                        <?php echo _l('ccx_msgs_promo'); ?>
                    </option>
                    <option value="transactional" <?php if ($selected_subtype == 'transactional') { echo 'selected'; } ?>>
                        <?php echo _l('ccx_msgs_trans'); ?>
                    </option>
                </select>
            </div>

            <?php $selected_cycle = isset($plan) ? $plan->billing_cycle : 'monthly'; ?>
            <div class="form-group" app-field-wrapper="billing_cycle">
                <label for="billing_cycle" class="control-label"><?php echo _l('billing_cycle'); ?></label>
                <select id="billing_cycle" name="billing_cycle" class="selectpicker" data-width="100%"
                    data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" data-live-search="false">
                    <option value="monthly" <?php if ($selected_cycle == 'monthly') {
                        echo 'selected';
                    } ?>>
                        <?php echo _l('billing_monthly'); ?>
                    </option>
                    <option value="quarterly" <?php if ($selected_cycle == 'quarterly') {
                        echo 'selected';
                    } ?>>
                        <?php echo _l('billing_quarterly'); ?>
                    </option>
                    <option value="yearly" <?php if ($selected_cycle == 'yearly') {
                        echo 'selected';
                    } ?>>
                        <?php echo _l('billing_yearly'); ?>
                    </option>
                </select>
            </div>

            <?php
                // Load currencies from Perfex CRM core
                $CI =& get_instance();
                $CI->load->model('currencies_model');
                $all_currencies = $CI->currencies_model->get();
                $base_currency = $CI->currencies_model->get_base_currency();
                $selected_currency = isset($plan) && !empty($plan->currency_id) ? $plan->currency_id : $base_currency->id;
            ?>
            <div class="form-group" app-field-wrapper="currency_id">
                <label for="currency_id" class="control-label"><?php echo _l('currency'); ?></label>
                <select id="currency_id" name="currency_id" class="selectpicker" data-width="100%"
                    data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" data-live-search="true">
                    <?php foreach ($all_currencies as $curr) { ?>
                        <option value="<?php echo $curr['id']; ?>"
                            data-symbol="<?php echo $curr['symbol']; ?>"
                            data-subtext="<?php echo $curr['symbol']; ?>"
                            <?php if ($selected_currency == $curr['id']) echo 'selected'; ?>>
                            <?php echo $curr['name']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <?php echo render_input('plan_name', 'plan_name', isset($plan) ? $plan->plan_name : ''); ?>

            <div class="row">
                <div class="col-md-6">
                    <?php echo render_input('price', 'plan_price', isset($plan) ? $plan->price : '0.00', 'number', ['step' => 'any']); ?>
                </div>
                <div class="col-md-6">
                    <?php echo render_input('message_count', 'plan_message_count', isset($plan) ? $plan->message_count : '0', 'number'); ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <?php echo render_input('expiry_days', 'plan_expiry_days', isset($plan) ? $plan->expiry_days : '365', 'number'); ?>
                </div>
                <div class="col-md-6">
                    <?php echo render_input('discount_percent', 'plan_discount_percent', isset($plan) ? $plan->discount_percent : '0.00', 'number', ['step' => 'any', 'max' => 100]); ?>
                </div>
            </div>

            <?php
                // Load taxes from Perfex CRM core
                $CI =& get_instance();
                $CI->load->model('taxes_model');
                $taxes = $CI->taxes_model->get();
                $selected_tax = isset($plan) ? $plan->tax_id : 0;
                $selected_tax_percent = isset($plan) ? $plan->tax_percent : '0.00';
            ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group" app-field-wrapper="tax_id">
                        <label for="tax_id" class="control-label">Tax (GST)</label>
                        <select id="tax_id" name="tax_id" class="selectpicker" data-width="100%"
                            data-none-selected-text="No Tax" data-live-search="false"
                            onchange="onTaxChange(this);">
                            <option value="0" data-taxrate="0" <?php if ($selected_tax == 0) echo 'selected'; ?>>No Tax</option>
                            <?php foreach ($taxes as $tax) { ?>
                                <option value="<?php echo $tax['id']; ?>"
                                    data-taxrate="<?php echo $tax['taxrate']; ?>"
                                    data-subtext="<?php echo $tax['taxrate']; ?>%"
                                    <?php if ($selected_tax == $tax['id']) echo 'selected'; ?>>
                                    <?php echo $tax['name']; ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <?php echo render_input('tax_percent', 'tax_rate_percent', $selected_tax_percent, 'number', ['step' => 'any', 'readonly' => true, 'disabled' => false]); ?>
                </div>
            </div>
            <input type="hidden" name="tax_percent" id="tax_percent_hidden" value="<?php echo $selected_tax_percent; ?>">

            <div class="form-group" app-field-wrapper="offer_description">
                <label for="offer_description" class="control-label"><?php echo _l('plan_offer_description'); ?></label>
                <div style="display: flex; gap: 8px; align-items: flex-start;">
                    <textarea id="offer_description" name="offer_description" class="form-control" rows="5" style="flex: 1;"><?php echo isset($plan) ? $plan->offer_description : ''; ?></textarea>
                    <button type="button" id="btn_generate_desc" class="btn btn-default" onclick="generateSmartDescription();" title="Auto-generate smart description from plan values" style="white-space: nowrap; border-radius: 6px; border: 1px solid #d0d5dd; padding: 8px 14px; font-size: 12px; font-weight: 600; color: #7c3aed; background: linear-gradient(135deg, #f5f3ff, #ede9fe); transition: all 0.25s ease;">
                        <i class="fa fa-magic" style="margin-right: 4px;"></i> Generate
                    </button>
                </div>
                <small class="text-muted" style="margin-top: 4px; display: block;">
                    <i class="fa fa-info-circle"></i> Click "Generate" to auto-create a detailed pricing breakdown from the values above.
                </small>
            </div>

            <div class="checkbox checkbox-primary">
                <input type="checkbox" name="active" id="active" <?php if (isset($plan)) {
                    if ($plan->active == 1) {
                        echo 'checked';
                    }
                } else {
                    echo 'checked';
                } ?>>
                <label for="active">
                    <?php echo _l('is_active'); ?>
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