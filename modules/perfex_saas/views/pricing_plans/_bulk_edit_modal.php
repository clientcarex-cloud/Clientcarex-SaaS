<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Bulk update price / user (seat) limits / modules across the selected plans.
 *
 * @var array  $all_modules     [module_id => ['custom_name'=>.., 'system_name'=>..]]
 * @var array  $default_modules [ ['system_name'=>.., 'custom_name'=>..], ... ]
 * @var array  $limit_keys      list of limit keys (staff, clients, ...)
 * @var object $currency        Base currency
 */
?>
<div class="modal fade" id="pp_bulk_edit_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <?= form_open(admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans/bulk_update_fields'), ['id' => 'pp_bulk_edit_form']); ?>
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('perfex_saas_pricing_plans_bulk_edit'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <?= _l('perfex_saas_pricing_plans_bulk_edit_intro', '<b><span id="pp_bulk_edit_count">0</span></b>'); ?>
                </div>
                <!-- selected ids are injected here on modal open -->
                <div id="pp_bulk_edit_ids"></div>

                <!-- Price -->
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" name="set_price" value="1" id="pp_set_price" />
                    <label for="pp_set_price"><?= _l('perfex_saas_pricing_plans_update_price'); ?></label>
                </div>
                <div class="form-group">
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" class="form-control" name="price"
                            placeholder="<?= _l('perfex_saas_price'); ?>" />
                        <span class="input-group-addon"><?= $currency->name; ?></span>
                    </div>
                </div>
                <hr />

                <!-- Limits (users/seats etc.) -->
                <label class="control-label"><?= _l('perfex_saas_pricing_plans_update_limits'); ?></label>
                <p class="text-muted tw-text-xs"><?= _l('perfex_saas_pricing_plans_limits_hint'); ?></p>
                <div class="row">
                    <?php foreach ($limit_keys as $key) : ?>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label tw-text-xs"><?= _l('perfex_saas_limit_' . $key); ?></label>
                            <input type="number" step="1" min="-1" class="form-control input-sm"
                                name="limitations[<?= e($key); ?>]"
                                placeholder="<?= _l('perfex_saas_pricing_plans_leave_blank'); ?>" />
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <hr />

                <!-- Modules -->
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" name="set_modules" value="1" id="pp_set_modules" />
                    <label for="pp_set_modules"><?= _l('perfex_saas_pricing_plans_replace_modules'); ?></label>
                </div>
                <div class="form-group">
                    <select name="modules[]" multiple class="selectpicker" data-actions-box="true" data-width="100%"
                        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                        <?php foreach ($all_modules as $mid => $module) : ?>
                        <option value="<?= e($mid); ?>"><?= e($module['custom_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="checkbox checkbox-primary">
                    <input type="checkbox" name="set_disabled_default_modules" value="1" id="pp_set_disabled" />
                    <label for="pp_set_disabled"><?= _l('perfex_saas_pricing_plans_set_disabled_modules'); ?></label>
                </div>
                <div class="form-group">
                    <select name="disabled_default_modules[]" multiple class="selectpicker" data-actions-box="true"
                        data-width="100%" data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                        <?php foreach ($default_modules as $module) : ?>
                        <option value="<?= e($module['system_name']); ?>"><?= e($module['custom_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary" id="pp_bulk_edit_submit"><?= _l('submit'); ?></button>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>
