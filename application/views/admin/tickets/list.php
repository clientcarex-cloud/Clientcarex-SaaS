<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div id="vueApp">
            <div class="row">
                <div class="col-md-12">
                    <div class="tw-block md:tw-hidden">
                        <?php $this->load->view('admin/tickets/summary', [
                            'hrefAttrs' => function ($status) use ($table) {
                                return '@click.prevent="extra.ticketsRules = ' . app\services\utilities\Js::from($table->findRule('status')->setValue([(int) $status['ticketstatusid']])) . '"';
                            },
                        ]); ?>
                    </div>

                    <div class="_buttons tw-mb-2">
                        <div class="md:tw-flex md:tw-items-center">
                            <a href="<?= admin_url('tickets/add'); ?>"
                                class="btn btn-primary display-block mright5">
                                <i class="fa-regular fa-plus tw-mr-1"></i>
                                <?= _l('new_ticket'); ?>
                            </a>



                            <div class="tw-grow md:tw-ml-6 rtl:md:tw-mr-6 tw-hidden md:tw-block">
                                <?php $this->load->view('admin/tickets/summary', [
                                    'hrefAttrs' => function ($status) use ($table) {
                                        return '@click.prevent="extra.ticketsRules = ' . app\services\utilities\Js::from($table->findRule('status')->setValue([(int) $status['ticketstatusid']])) . '"';
                                    },
                                ]); ?>
                            </div>
                            <div class="ltr:tw-ml-auto rtl:tw-mr-auto">
                                <app-filters
                                    id="<?= $table->id(); ?>"
                                    view="<?= $table->viewName(); ?>"
                                    :rules="extra.ticketsRules || <?= app\services\utilities\Js::from($chosen_ticket_status ? $table->findRule('status')->setValue([(int) $chosen_ticket_status]) : []); ?>"
                                    :saved-filters="<?= $table->filtersJs(); ?>"
                                    :available-rules="<?= $table->rulesJs(); ?>">
                                </app-filters>
                            </div>
                        </div>
                    </div>

                    <div class="panel_s">
                        <div class="panel-body">


                            <?php hooks()->do_action('before_render_tickets_list_table'); ?>

                            <a href="#" data-toggle="modal" data-target="#tickets_bulk_actions"
                                class="bulk-actions-btn table-btn hide"
                                data-table=".table-tickets"><?= _l('bulk_actions'); ?></a>
                            <div class="clearfix"></div>
                            <div class="panel-table-full">
                                <?= AdminTicketsTableStructure('', true); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade bulk_actions" id="tickets_bulk_actions" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <?= _l('bulk_actions'); ?>
                </h4>
            </div>
            <div class="modal-body">
                <div class="checkbox checkbox-primary merge_tickets_checkbox">
                    <input type="checkbox" name="merge_tickets" id="merge_tickets">
                    <label
                        for="merge_tickets"><?= _l('merge_tickets'); ?></label>
                </div>
                <?php if (can_staff_delete_ticket()) { ?>
                <div class="checkbox checkbox-danger mass_delete_checkbox">
                    <input type="checkbox" name="mass_delete" id="mass_delete">
                    <label
                        for="mass_delete"><?= _l('mass_delete'); ?></label>
                </div>
                <hr class="mass_delete_separator" />
                <?php } ?>
                <div id="bulk_change">
                    <?= render_select('move_to_status_tickets_bulk', $statuses, ['ticketstatusid', 'name'], 'ticket_single_change_status'); ?>
                    <?= render_select('move_to_department_tickets_bulk', $departments, ['departmentid', 'name'], 'department'); ?>
                    <?= render_select('move_to_priority_tickets_bulk', $priorities, ['priorityid', 'name'], 'ticket_priority'); ?>
                    <div class="form-group">
                        <?= '<p><b><i class="fa fa-tag" aria-hidden="true"></i> ' . _l('tags') . ':</b></p>'; ?>
                        <input type="text" class="tagsinput" id="tags_bulk" name="tags_bulk" value=""
                            data-role="tagsinput">
                    </div>
                    <?php if (get_option('services') == 1) { ?>
                    <?= render_select('move_to_service_tickets_bulk', $services, ['serviceid', 'name'], 'service'); ?>
                    <?php } ?>
                </div>
                <div id="merge_tickets_wrapper">
                    <div class="form-group">
                        <label for="primary_ticket_id">
                            <span class="text-danger">*</span>
                            <?= _l('primary_ticket'); ?>
                        </label>
                        <select id="primary_ticket_id" class="selectpicker" name="primary_ticket_id" data-width="100%"
                            data-live-search="true"
                            data-none-selected-text="<?= _l('dropdown_non_selected_tex') ?>"
                            required>
                            <option></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="primary_ticket_status">
                            <span class="text-danger">*</span>
                            <?= _l('primary_ticket_status'); ?>
                        </label>
                        <select id="primary_ticket_status" class="selectpicker" name="primary_ticket_status"
                            data-width="100%" data-live-search="true"
                            data-none-selected-text="<?= _l('dropdown_non_selected_tex') ?>"
                            required>
                            <?php foreach ($statuses as $status) { ?>
                            <option
                                value="<?= e($status['ticketstatusid']); ?>">
                                <?= e($status['name']); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default"
                    data-dismiss="modal"><?= _l('close'); ?></button>
                <a href="#" class="btn btn-primary"
                    onclick="tickets_bulk_action(this); return false;"><?= _l('confirm'); ?></a>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
<?php init_tail(); ?>

</body>

</html>