<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div id="contract_summary" class="tw-mb-6">
                    <h4 class="tw-mt-0 tw-mb-2 tw-font-bold tw-text-xl">
                        <?= _l('contracts'); ?>
                    </h4>
                    <div class="tw-grid tw-grid-cols-2 md:tw-grid-cols-3 lg:tw-grid-cols-5 tw-gap-2">
                        <div
                            class="tw-bg-white tw-border-neutral-300/80 tw-shadow-sm tw-text-sm tw-border tw-border-solid tw-rounded-lg tw-px-4 tw-py-3 text-sm tw-flex-1 tw-flex tw-items-center odd:last:tw-col-span-2 md:odd:last:tw-col-auto">
                            <span class="tw-font-semibold tw-mr-1 rtl:tw-ml-1">
                                <?= e($count_active); ?></span>
                            <span
                                class="text-info"><?= _l('contract_summary_active'); ?></span>
                        </div>
                        <div
                            class="tw-bg-white tw-border-neutral-300/80 tw-shadow-sm tw-text-sm tw-border tw-border-solid tw-rounded-lg tw-px-4 tw-py-3 text-sm tw-flex-1 tw-flex tw-items-center odd:last:tw-col-span-2 md:odd:last:tw-col-auto">
                            <span class="tw-font-semibold tw-mr-1 rtl:tw-ml-1">
                                <?= e($count_expired); ?></span>
                            <span
                                class="text-danger"><?= _l('contract_summary_expired'); ?></span>
                        </div>
                        <div
                            class="tw-bg-white tw-border-neutral-300/80 tw-shadow-sm tw-text-sm tw-border tw-border-solid tw-rounded-lg tw-px-4 tw-py-3 text-sm tw-flex-1 tw-flex tw-items-center odd:last:tw-col-span-2 md:odd:last:tw-col-auto">
                            <span class="tw-font-semibold tw-mr-1 rtl:tw-ml-1">
                                <?= count($expiring); ?>
                            </span>
                            <span
                                class="text-warning"><?= _l('contract_summary_about_to_expire'); ?></span>
                        </div>
                        <div
                            class="tw-bg-white tw-border-neutral-300/80 tw-shadow-sm tw-text-sm tw-border tw-border-solid tw-rounded-lg tw-px-4 tw-py-3 text-sm tw-flex-1 tw-flex tw-items-center odd:last:tw-col-span-2 md:odd:last:tw-col-auto">
                            <span class="tw-font-semibold tw-mr-1 rtl:tw-ml-1">
                                <?= e($count_recently_created); ?></span>
                            <span
                                class="text-success"><?= _l('contract_summary_recently_added'); ?></span>
                        </div>
                        <div
                            class="tw-bg-white tw-border-neutral-300/80 tw-shadow-sm tw-text-sm tw-border tw-border-solid tw-rounded-lg tw-px-4 tw-py-3 text-sm tw-flex-1 tw-flex tw-items-center odd:last:tw-col-span-2 md:odd:last:tw-col-auto">
                            <span class="tw-font-semibold tw-mr-1 rtl:tw-ml-1">
                                <?= e($count_trash); ?></span>
                            <span
                                class="text-muted"><?= _l('contract_summary_trash'); ?></span>
                        </div>
                    </div>
                </div>
                <div class="_buttons">
                    <?php if (staff_can('create', 'contracts')) { ?>
                    <a href="<?= admin_url('contracts/contract'); ?>"
                        class="btn btn-primary pull-left display-block">
                        <i class="fa-regular fa-plus tw-mr-1"></i>
                        <?= _l('new_contract'); ?>
                    </a>
                    <?php } ?>
                    <div id="vueApp" class="tw-inline pull-right tw-ml-0 sm:tw-ml-1.5 rtl:tw-mr-1.5 rtl:tw-ml-0">
                        <app-filters id="<?= $table->id(); ?>"
                            view="<?= $table->viewName(); ?>"
                            :saved-filters="<?= $table->filtersJs(); ?>"
                            :available-rules="<?= $table->rulesJs(); ?>">
                        </app-filters>
                    </div>
                    <div class="clearfix"></div>

                </div>




                <div class="panel_s tw-mt-2 sm:tw-mt-4">
                    <?= form_hidden('custom_view'); ?>
                    <div class="panel-body">

                        <div class="panel-table-full">
                            <?php $this->load->view('admin/contracts/table_html'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        initDataTable('.table-contracts', admin_url + 'contracts/table', undefined, undefined, {},
            <?= hooks()->apply_filters('contracts_table_default_order', json_encode([6, 'asc'])); ?>
        );
    });
</script>
</body>

</html>