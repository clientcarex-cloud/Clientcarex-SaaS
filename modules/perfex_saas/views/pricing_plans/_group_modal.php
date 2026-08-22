<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Manage plan groups (synced with core customer groups).
 *
 * @var array $plan_groups [ ['id'=>.., 'name'=>..], ... ]
 */
?>
<div class="modal fade" id="pp_groups_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('perfex_saas_pricing_plans_manage_groups'); ?></h4>
            </div>
            <div class="modal-body">
                <p class="text-muted"><?= _l('perfex_saas_pricing_plans_groups_sync_hint'); ?></p>
                <?= form_open(admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans/save_group'), ['id' => 'pp_group_form']); ?>
                <input type="hidden" name="id" value="" />
                <div class="form-group">
                    <label class="control-label"><?= _l('perfex_saas_pricing_plans_group_name'); ?></label>
                    <div class="input-group">
                        <input type="text" name="name" class="form-control" autocomplete="off" />
                        <span class="input-group-btn">
                            <button type="submit" class="btn btn-primary"><?= _l('submit'); ?></button>
                            <button type="button" class="btn btn-default" id="pp_group_reset"><?= _l('perfex_saas_pricing_plans_group_new'); ?></button>
                        </span>
                    </div>
                </div>
                <?= form_close(); ?>
                <hr />
                <table class="table tw-w-full">
                    <tbody id="pp_group_list">
                        <?php if (empty($plan_groups)) : ?>
                        <tr id="pp_group_empty">
                            <td colspan="2" class="text-muted"><?= _l('perfex_saas_pricing_plans_no_groups'); ?></td>
                        </tr>
                        <?php else : foreach ($plan_groups as $g) : ?>
                        <tr data-id="<?= (int)$g['id']; ?>">
                            <td class="pp-group-name"><?= e($g['name']); ?></td>
                            <td class="text-right">
                                <button type="button" class="btn btn-default btn-xs pp-group-edit"
                                    data-id="<?= (int)$g['id']; ?>" data-name="<?= e($g['name']); ?>">
                                    <i class="fa fa-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-danger btn-xs pp-group-delete"
                                    data-id="<?= (int)$g['id']; ?>">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
            </div>
        </div>
    </div>
</div>
