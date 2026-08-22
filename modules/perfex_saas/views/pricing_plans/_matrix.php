<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Group × plan matrix for a single recurring period.
 *
 * @var string $period   Period alias (tab) being rendered
 * @var array  $groups   [group_id => name] including 0 => Ungrouped
 * @var array  $grid     [group_id => [period_alias => [package, ...]]]
 * @var object $currency Base currency
 * @var array  $customer_counts
 */
$has_any = false;
foreach ($groups as $gid => $gname) {
    if (!empty($grid[$gid][$period])) { $has_any = true; break; }
}
?>
<div class="panel_s">
    <div class="panel-body panel-table-full">
        <?php if (!$has_any) : ?>
        <p class="text-muted tw-p-4 tw-mb-0"><?= _l('perfex_saas_pricing_plans_no_plans_period'); ?></p>
        <?php else : ?>
        <table class="table tw-w-full">
            <thead>
                <tr>
                    <th style="width:30px;"><input type="checkbox" class="pp-check-all-period" /></th>
                    <th><?= _l('perfex_saas_pricing_plans_group'); ?></th>
                    <th><?= _l('perfex_saas_plan'); ?></th>
                    <th style="width:170px;"><?= _l('perfex_saas_price'); ?> (<?= $currency->name; ?>)</th>
                    <th style="width:110px;"><?= _l('perfex_saas_status'); ?></th>
                    <th style="width:100px;"><?= _l('customers'); ?></th>
                    <th style="width:80px;"><?= _l('options'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $gid => $gname) : ?>
                <?php $plans = $grid[$gid][$period] ?? []; ?>
                <?php if (empty($plans)) continue; ?>
                <?php foreach ($plans as $i => $p) : ?>
                <tr data-group="<?= (int)$gid; ?>">
                    <td>
                        <input type="checkbox" class="pp-row-check" name="selected[]" value="<?= (int)$p->id; ?>" />
                    </td>
                    <td>
                        <span class="label <?= $gid ? 'label-info' : 'label-default'; ?>"><?= e($gname); ?></span>
                    </td>
                    <td>
                        <a href="<?= admin_url(PERFEX_SAAS_ROUTE_NAME . '/packages/edit/' . $p->id); ?>"><?= e($p->name); ?></a>
                        <?php if (!empty($p->is_private)) : ?>
                        <span class="label label-warning tw-ml-1"><?= _l('perfex_saas_private'); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($p->is_default)) : ?>
                        <span class="label label-success tw-ml-1"><?= _l('perfex_saas_default'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" class="form-control input-sm"
                            name="plans[<?= (int)$p->id; ?>][price]" value="<?= e($p->price); ?>" />
                    </td>
                    <td>
                        <div class="onoffswitch">
                            <input type="checkbox" class="onoffswitch-checkbox" id="pp_status_<?= (int)$p->id; ?>"
                                name="plans[<?= (int)$p->id; ?>][status]" value="1" <?= !empty($p->status) ? 'checked' : ''; ?> />
                            <label class="onoffswitch-label" for="pp_status_<?= (int)$p->id; ?>"></label>
                        </div>
                    </td>
                    <td>
                        <span class="label label-default tw-text-sm"><?= $customer_counts[$p->id] ?? 0; ?></span>
                    </td>
                    <td>
                        <a href="<?= admin_url(PERFEX_SAAS_ROUTE_NAME . '/packages/edit/' . $p->id); ?>"
                            class="btn btn-default btn-icon" data-toggle="tooltip" title="<?= _l('edit'); ?>">
                            <i class="fa fa-pencil-square-o"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
