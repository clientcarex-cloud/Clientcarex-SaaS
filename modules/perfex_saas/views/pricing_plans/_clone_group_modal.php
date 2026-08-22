<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Clone every plan in a source group into a target group / period.
 *
 * @var array $matrix_groups  [group_id => name] including 0 => Ungrouped
 * @var array $period_presets [key => ['label'=>..,]]
 */
?>
<div class="modal fade" id="pp_clone_group_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <?= form_open(admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans/clone_group'), ['id' => 'pp_clone_group_form']); ?>
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('perfex_saas_pricing_plans_clone_group'); ?></h4>
            </div>
            <div class="modal-body">
                <p class="text-muted"><?= _l('perfex_saas_pricing_plans_clone_group_hint'); ?></p>

                <div class="form-group">
                    <label class="control-label"><?= _l('perfex_saas_pricing_plans_source_group'); ?></label>
                    <select name="source_group_id" class="form-control selectpicker" data-width="100%">
                        <?php foreach ($matrix_groups as $gid => $gname) : ?>
                        <option value="<?= (int)$gid; ?>"><?= e($gname); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="control-label"><?= _l('perfex_saas_pricing_plans_target_group'); ?></label>
                    <select name="target_group_id" class="form-control selectpicker" data-width="100%">
                        <?php foreach ($matrix_groups as $gid => $gname) : if (!$gid) continue; ?>
                        <option value="<?= (int)$gid; ?>"><?= e($gname); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="control-label"><?= _l('perfex_saas_pricing_plans_target_period'); ?></label>
                    <select name="target_period" class="form-control selectpicker" data-width="100%">
                        <option value=""><?= _l('perfex_saas_pricing_plans_keep_period'); ?></option>
                        <?php foreach ($period_presets as $key => $preset) : ?>
                        <option value="<?= e($key); ?>"><?= e($preset['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?= _l('perfex_saas_pricing_plans_clone_group'); ?></button>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>
