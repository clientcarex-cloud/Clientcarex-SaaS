<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Auto-generate recurring-period variants from a base plan.
 *
 * @var array $packages       List of package objects
 * @var array $matrix_groups  [group_id => name] including 0 => Ungrouped
 * @var array $period_presets [key => ['label'=>..,]]
 */
?>
<div class="modal fade" id="pp_generate_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <?= form_open(admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans/generate_variants'), ['id' => 'pp_generate_form']); ?>
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('perfex_saas_pricing_plans_generate_variants'); ?></h4>
            </div>
            <div class="modal-body">
                <p class="text-muted"><?= _l('perfex_saas_pricing_plans_generate_hint'); ?></p>

                <div class="form-group">
                    <label class="control-label"><?= _l('perfex_saas_pricing_plans_base_plan'); ?></label>
                    <select name="base_id" class="form-control selectpicker" data-live-search="true" data-width="100%">
                        <?php foreach ($packages as $p) : ?>
                        <option value="<?= (int)$p->id; ?>"><?= e($p->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="control-label"><?= _l('perfex_saas_pricing_plans_assign_group'); ?></label>
                    <select name="group_id" class="form-control selectpicker" data-width="100%">
                        <option value=""><?= _l('perfex_saas_pricing_plans_keep_base_group'); ?></option>
                        <?php foreach ($matrix_groups as $gid => $gname) : if (!$gid) continue; ?>
                        <option value="<?= (int)$gid; ?>"><?= e($gname); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label class="control-label"><?= _l('perfex_saas_pricing_plans_periods'); ?></label>
                <table class="table tw-w-full">
                    <thead>
                        <tr>
                            <th style="width:40px;"></th>
                            <th><?= _l('perfex_saas_pricing_plans_period'); ?></th>
                            <th><?= _l('perfex_saas_pricing_plans_fixed_price'); ?></th>
                            <th><?= _l('perfex_saas_pricing_plans_multiplier'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($period_presets as $key => $preset) : ?>
                        <tr>
                            <td><input type="checkbox" name="periods[]" value="<?= e($key); ?>" /></td>
                            <td><?= e($preset['label']); ?></td>
                            <td><input type="number" step="0.01" min="0" class="form-control input-sm"
                                    name="price_map[<?= e($key); ?>]" placeholder="<?= _l('perfex_saas_pricing_plans_optional'); ?>" /></td>
                            <td><input type="number" step="0.01" min="0" class="form-control input-sm"
                                    name="multiplier_map[<?= e($key); ?>]" placeholder="<?= _l('perfex_saas_pricing_plans_eg_multiplier'); ?>" /></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="text-muted tw-text-xs"><?= _l('perfex_saas_pricing_plans_price_resolution_hint'); ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?= _l('perfex_saas_pricing_plans_generate'); ?></button>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>
