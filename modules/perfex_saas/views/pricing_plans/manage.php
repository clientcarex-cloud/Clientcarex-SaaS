<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
/**
 * Pricing Plans manager.
 *
 * @var array $matrix          ['groups'=>.., 'periods'=>.., 'matrix'=>..]
 * @var array $groups          raw plan groups [ ['id','name'], ... ]
 * @var array $packages        all package objects
 * @var array $all_modules
 * @var array $default_modules
 * @var array $limit_keys
 * @var array $period_presets
 */
$currency      = get_base_currency();
$matrix_groups = $matrix['groups'];   // [id => name] incl 0 => Ungrouped
$periods       = $matrix['periods'];
$grid          = $matrix['matrix'];
$plan_groups   = $groups;             // raw list from controller
$has_plans     = !empty($packages);

// Per-group plan counts for the group filter pills
$group_counts = [];
foreach ($matrix_groups as $gid => $gname) {
    $count = 0;
    foreach (($grid[$gid] ?? []) as $plans) {
        $count += count($plans);
    }
    $group_counts[$gid] = $count;
}
$total_plans = array_sum($group_counts);

$can_edit   = staff_can('edit', 'perfex_saas_packages');
$can_create = staff_can('create', 'perfex_saas_packages');
$can_delete = staff_can('delete', 'perfex_saas_packages');
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3">
                    <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-text-neutral-700">
                        <?= _l('perfex_saas_pricing_plans'); ?>
                    </h4>
                    <div class="tw-flex tw-flex-wrap tw-gap-2">
                        <a href="<?= admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans/overview'); ?>" class="btn btn-info">
                            <i class="fa fa-th-large tw-mr-1"></i><?= _l('perfex_saas_pricing_plans_overview'); ?>
                        </a>
                        <?php if ($can_create || $can_edit) : ?>
                        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#pp_groups_modal">
                            <i class="fa fa-object-group tw-mr-1"></i><?= _l('perfex_saas_pricing_plans_manage_groups'); ?>
                        </button>
                        <?php endif; ?>
                        <?php if ($can_create) : ?>
                        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#pp_generate_modal">
                            <i class="fa fa-bolt tw-mr-1"></i><?= _l('perfex_saas_pricing_plans_generate_variants'); ?>
                        </button>
                        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#pp_clone_group_modal">
                            <i class="fa fa-clone tw-mr-1"></i><?= _l('perfex_saas_pricing_plans_clone_group'); ?>
                        </button>
                        <a href="<?= admin_url(PERFEX_SAAS_ROUTE_NAME . '/packages/create'); ?>" class="btn btn-primary">
                            <i class="fa-regular fa-plus tw-mr-1"></i><?= _l('perfex_saas_new_package'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$has_plans) : ?>
                <div class="panel_s">
                    <div class="panel-body text-center">
                        <p class="tw-mb-4"><?= _l('perfex_saas_pricing_plans_empty'); ?></p>
                        <?php if ($can_create) : ?>
                        <a href="<?= admin_url(PERFEX_SAAS_ROUTE_NAME . '/packages/create'); ?>" class="btn btn-primary">
                            <i class="fa-regular fa-plus tw-mr-1"></i><?= _l('perfex_saas_new_package'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else : ?>

                <?= form_open(admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans/bulk_save'), ['id' => 'pricing-plans-form']); ?>
                <input type="hidden" name="bulk_action" id="pp_bulk_action_field" value="" />

                <div class="panel_s">
                    <div class="panel-body tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                        <span class="tw-text-sm tw-text-neutral-600">
                            <b><span id="pp_selected_count">0</span></b> <?= _l('perfex_saas_pricing_plans_selected'); ?>
                        </span>
                        <div class="tw-flex tw-flex-wrap tw-gap-2 tw-ml-auto">
                            <?php if ($can_edit) : ?>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-floppy-o tw-mr-1"></i><?= _l('perfex_saas_pricing_plans_save_changes'); ?>
                            </button>
                            <button type="button" class="btn btn-success pp-bulk" data-action="activate">
                                <?= _l('perfex_saas_pricing_plans_activate'); ?>
                            </button>
                            <button type="button" class="btn btn-warning pp-bulk" data-action="deactivate">
                                <?= _l('perfex_saas_pricing_plans_deactivate'); ?>
                            </button>
                            <div class="tw-flex tw-items-center tw-gap-1">
                                <select name="group" class="form-control input-sm" style="width:auto;">
                                    <option value="0"><?= _l('perfex_saas_pricing_plans_ungrouped'); ?></option>
                                    <?php foreach ($matrix_groups as $gid => $gname) : if (!$gid) continue; ?>
                                    <option value="<?= (int)$gid; ?>"><?= e($gname); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-default pp-bulk" data-action="set_group">
                                    <?= _l('perfex_saas_pricing_plans_assign_group'); ?>
                                </button>
                            </div>
                            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#pp_bulk_edit_modal">
                                <i class="fa fa-sliders tw-mr-1"></i><?= _l('perfex_saas_pricing_plans_bulk_edit'); ?>
                            </button>
                            <?php endif; ?>
                            <?php if ($can_create) : ?>
                            <button type="button" class="btn btn-default pp-bulk" data-action="clone">
                                <?= _l('perfex_saas_pricing_plans_clone_selected'); ?>
                            </button>
                            <?php endif; ?>
                            <?php if ($can_delete) : ?>
                            <button type="button" class="btn btn-danger pp-bulk" data-action="delete" data-confirm="1">
                                <i class="fa fa-trash tw-mr-1"></i><?= _l('delete'); ?>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="tw-mb-2">
                    <span class="tw-text-xs tw-text-neutral-500 tw-mr-2"><?= _l('perfex_saas_pricing_plans_filter_by_group'); ?>:</span>
                    <ul class="nav nav-pills pp-group-filter tw-inline-flex">
                        <li class="active">
                            <a href="#" data-group="all"><?= _l('perfex_saas_pricing_plans_all_groups'); ?>
                                <span class="badge"><?= (int)$total_plans; ?></span></a>
                        </li>
                        <?php foreach ($matrix_groups as $gid => $gname) : ?>
                        <li>
                            <a href="#" data-group="<?= (int)$gid; ?>"><?= e($gname); ?>
                                <span class="badge"><?= (int)($group_counts[$gid] ?? 0); ?></span></a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <ul class="nav nav-tabs" role="tablist">
                    <?php $first = true; foreach ($periods as $alias => $label) : $tab_id = 'pp_period_' . md5($alias); ?>
                    <li role="presentation" class="<?= $first ? 'active' : ''; ?>">
                        <a href="#<?= $tab_id; ?>" aria-controls="<?= $tab_id; ?>" role="tab" data-toggle="tab">
                            <?= e($label); ?>
                        </a>
                    </li>
                    <?php $first = false; endforeach; ?>
                </ul>

                <div class="tab-content tw-mt-3">
                    <?php $first = true; foreach ($periods as $alias => $label) : $tab_id = 'pp_period_' . md5($alias); ?>
                    <div role="tabpanel" class="tab-pane <?= $first ? 'active' : ''; ?>" id="<?= $tab_id; ?>">
                        <?php $this->load->view('pricing_plans/_matrix', [
                            'period'   => $alias,
                            'groups'   => $matrix_groups,
                            'grid'     => $grid,
                            'currency' => $currency,
                            'customer_counts' => $customer_counts ?? [],
                        ]); ?>
                    </div>
                    <?php $first = false; endforeach; ?>
                </div>

                <?= form_close(); ?>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php
$this->load->view('pricing_plans/_group_modal', ['plan_groups' => $plan_groups]);
$this->load->view('pricing_plans/_generate_modal', ['packages' => $packages, 'matrix_groups' => $matrix_groups, 'period_presets' => $period_presets]);
$this->load->view('pricing_plans/_clone_group_modal', ['matrix_groups' => $matrix_groups, 'period_presets' => $period_presets]);
$this->load->view('pricing_plans/_bulk_edit_modal', ['all_modules' => $all_modules, 'default_modules' => $default_modules, 'limit_keys' => $limit_keys, 'currency' => $currency]);
?>

<?php init_tail(); ?>
<script>
    "use strict";

    var PP_URL_BULK_ACTION = "<?= admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans/bulk_action'); ?>";
    var PP_URL_SAVE_GROUP  = "<?= admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans/save_group'); ?>";
    var PP_URL_DELETE_GROUP = "<?= admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans/delete_group'); ?>";
    var PP_MSG_NO_SELECTION = "<?= _l('perfex_saas_pricing_plans_no_selection'); ?>";
    var PP_MSG_CONFIRM_BULK = "<?= _l('perfex_saas_pricing_plans_confirm_bulk'); ?>";
    var PP_MSG_CONFIRM_DELETE_GROUP = "<?= _l('perfex_saas_pricing_plans_confirm_delete_group'); ?>";

    function ppCsrf(data) {
        data = data || {};
        if (typeof csrfData !== 'undefined') {
            data[csrfData.token_name] = csrfData.hash;
        }
        return data;
    }

    function ppSelectedIds() {
        return $('#pricing-plans-form .pp-row-check:checked').map(function () {
            return this.value;
        }).get();
    }

    function ppUpdateCount() {
        $('#pp_selected_count').text(ppSelectedIds().length);
    }

    $(function () {

        // selection helpers
        $(document).on('change', '.pp-row-check', ppUpdateCount);
        $(document).on('change', '.pp-check-all-period', function () {
            $(this).closest('table').find('.pp-row-check').prop('checked', $(this).prop('checked'));
            ppUpdateCount();
        });

        // Plan group filter (applies across all period tabs)
        $(document).on('click', '.pp-group-filter a', function (e) {
            e.preventDefault();
            var group = String($(this).data('group'));
            $('.pp-group-filter li').removeClass('active');
            $(this).closest('li').addClass('active');

            $('#pricing-plans-form tr[data-group]').each(function () {
                var show = (group === 'all' || String($(this).data('group')) === group);
                $(this).toggle(show);
                if (!show) $(this).find('.pp-row-check').prop('checked', false);
            });
            ppUpdateCount();
        });

        // bulk action buttons (activate / deactivate / clone / delete)
        $(document).on('click', '.pp-bulk', function () {
            var action = $(this).data('action');
            if (ppSelectedIds().length === 0) {
                alert_float('warning', PP_MSG_NO_SELECTION);
                return;
            }
            if ($(this).data('confirm') && !confirm(PP_MSG_CONFIRM_BULK)) {
                return;
            }
            var $form = $('#pricing-plans-form');
            $('#pp_bulk_action_field').val(action);
            $form.attr('action', PP_URL_BULK_ACTION);
            $form.trigger('submit');
        });

        // bulk edit modal — copy selected ids in, block when nothing selected
        $('#pp_bulk_edit_modal').on('show.bs.modal', function () {
            var ids = ppSelectedIds();
            var $c = $('#pp_bulk_edit_ids').empty();
            ids.forEach(function (id) {
                $c.append('<input type="hidden" name="selected[]" value="' + id + '">');
            });
            $('#pp_bulk_edit_count').text(ids.length);
        });
        $('#pp_bulk_edit_form').on('submit', function () {
            if (ppSelectedIds().length === 0) {
                alert_float('warning', PP_MSG_NO_SELECTION);
                return false;
            }
        });

        // ---- Plan group management (AJAX, synced with customer groups) ----
        $('#pp_group_form').on('submit', function (e) {
            e.preventDefault();
            var data = ppCsrf({
                id: $(this).find('input[name="id"]').val(),
                name: $(this).find('input[name="name"]').val()
            });
            $.post(PP_URL_SAVE_GROUP, data, function (resp) {
                if (typeof resp === 'string') resp = JSON.parse(resp);
                if (resp.success) {
                    location.reload();
                } else {
                    alert_float('warning', resp.message);
                }
            });
        });

        $('#pp_group_reset').on('click', function () {
            $('#pp_group_form input[name="id"]').val('');
            $('#pp_group_form input[name="name"]').val('').focus();
        });

        $(document).on('click', '.pp-group-edit', function () {
            $('#pp_group_form input[name="id"]').val($(this).data('id'));
            $('#pp_group_form input[name="name"]').val($(this).data('name')).focus();
        });

        $(document).on('click', '.pp-group-delete', function () {
            if (!confirm(PP_MSG_CONFIRM_DELETE_GROUP)) return;
            var data = ppCsrf({ id: $(this).data('id') });
            $.post(PP_URL_DELETE_GROUP, data, function (resp) {
                if (typeof resp === 'string') resp = JSON.parse(resp);
                if (resp.success) {
                    location.reload();
                } else {
                    alert_float('warning', resp.message);
                }
            });
        });

        ppUpdateCount();
    });
</script>
</body>

</html>
