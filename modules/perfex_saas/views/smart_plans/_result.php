<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Import result summary.
 *
 * @var array $import_result Summary from Smart_plans_trait::smart_plans_apply_import()
 */
$r        = $import_result;
$lines    = $r['lines'] ?? [];
$badges   = [
    'updated'   => ['class' => 'success', 'label' => _l('perfex_saas_smart_plans_res_updated')],
    'unchanged' => ['class' => 'default', 'label' => _l('perfex_saas_smart_plans_res_unchanged')],
    'skipped'   => ['class' => 'warning', 'label' => _l('perfex_saas_smart_plans_res_skipped')],
    'error'     => ['class' => 'danger',  'label' => _l('perfex_saas_smart_plans_res_error')],
];
?>
<div class="panel_s tw-mb-4">
    <div class="panel-body">
        <h5 class="tw-mt-0 tw-mb-3 tw-font-semibold">
            <i class="fa fa-check-circle tw-mr-1 tw-text-success-600"></i><?= _l('perfex_saas_smart_plans_result_title'); ?>
        </h5>

        <div class="tw-flex tw-flex-wrap tw-gap-3 tw-mb-3">
            <div class="tw-flex-1 tw-rounded tw-border tw-border-neutral-200 tw-p-3 tw-text-center" style="min-width:120px">
                <div class="tw-text-2xl tw-font-bold tw-text-success-600"><?= (int)$r['updated']; ?></div>
                <div class="tw-text-xs tw-text-neutral-500" style="text-transform:uppercase"><?= _l('perfex_saas_smart_plans_res_updated'); ?></div>
            </div>
            <div class="tw-flex-1 tw-rounded tw-border tw-border-neutral-200 tw-p-3 tw-text-center" style="min-width:120px">
                <div class="tw-text-2xl tw-font-bold tw-text-neutral-600"><?= (int)$r['unchanged']; ?></div>
                <div class="tw-text-xs tw-text-neutral-500" style="text-transform:uppercase"><?= _l('perfex_saas_smart_plans_res_unchanged'); ?></div>
            </div>
            <div class="tw-flex-1 tw-rounded tw-border tw-border-neutral-200 tw-p-3 tw-text-center" style="min-width:120px">
                <div class="tw-text-2xl tw-font-bold tw-text-warning-500"><?= (int)$r['skipped']; ?></div>
                <div class="tw-text-xs tw-text-neutral-500" style="text-transform:uppercase"><?= _l('perfex_saas_smart_plans_res_skipped'); ?></div>
            </div>
            <div class="tw-flex-1 tw-rounded tw-border tw-border-neutral-200 tw-p-3 tw-text-center" style="min-width:120px">
                <div class="tw-text-2xl tw-font-bold tw-text-danger-600"><?= (int)$r['errors']; ?></div>
                <div class="tw-text-xs tw-text-neutral-500" style="text-transform:uppercase"><?= _l('perfex_saas_smart_plans_res_error'); ?></div>
            </div>
        </div>

        <?php if (!empty($lines)) : ?>
        <div class="table-responsive">
            <table class="table table-bordered tw-mb-0">
                <thead>
                    <tr class="tw-bg-neutral-50">
                        <th style="width:80px"><?= _l('perfex_saas_smart_plans_res_column'); ?></th>
                        <th style="width:70px"><?= _l('perfex_saas_smart_plans_col_id'); ?></th>
                        <th><?= _l('perfex_saas_smart_plans_col_name'); ?></th>
                        <th style="width:110px"><?= _l('perfex_saas_smart_plans_res_status'); ?></th>
                        <th><?= _l('perfex_saas_smart_plans_res_changes'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line) :
                        $badge   = $badges[$line['status']] ?? $badges['unchanged'];
                        $changes = $line['changes'] ?? []; ?>
                    <tr>
                        <td><?= e($line['ref'] ?? ''); ?></td>
                        <td><?= e($line['id']); ?></td>
                        <td><?= e($line['name']); ?></td>
                        <td><span class="label label-<?= $badge['class']; ?>"><?= $badge['label']; ?></span></td>
                        <td class="tw-text-sm tw-text-neutral-600">
                            <?php if (!empty($changes)) : ?>
                            <ul class="tw-m-0 tw-pl-4" style="list-style:disc">
                                <?php foreach ($changes as $change) : ?>
                                <li><?= e($change); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                            <?php if (!empty($line['note'])) : ?>
                            <div class="tw-text-danger-600<?= !empty($changes) ? ' tw-mt-1' : ''; ?>"><?= e($line['note']); ?></div>
                            <?php elseif (empty($changes)) : ?>
                            <span class="tw-text-neutral-400"><?= _l('perfex_saas_smart_plans_no_changes'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
