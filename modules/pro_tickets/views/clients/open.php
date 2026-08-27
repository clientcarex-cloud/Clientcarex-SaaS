<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php include __DIR__ . '/_styles.php'; ?>
<div class="ptkc-wrap">

    <?php include __DIR__ . '/_smart_ticket_loader.php'; ?>

    <div class="ptkc-header">
        <h4 class="ptkc-title">
            <i class="fa-solid fa-pen-to-square"></i>
            <?= _l('clients_ticket_open_subject'); ?>
        </h4>
        <div class="ptkc-header-actions">
            <a href="<?= site_url('clients/pro_tickets'); ?>" class="ptkc-btn ptkc-btn-ghost">
                <i class="fa-solid fa-arrow-left"></i> <?= _l('pro_tickets_all_tickets'); ?>
            </a>
        </div>
    </div>

    <?php echo form_open_multipart('clients/pro_tickets/open', ['id' => 'open-new-ticket-form']); ?>
    <?php hooks()->do_action('before_client_open_ticket_form_start'); ?>
    <div class="ptkc-card">
        <div class="ptkc-card-body">

            <div class="ptkc-field open-ticket-subject-group">
                <label for="subject"><?= _l('customer_ticket_subject'); ?></label>
                <input type="text" class="form-control" name="subject" id="subject" value="<?= set_value('subject'); ?>">
                <?= form_error('subject'); ?>
            </div>

            <?php if (total_rows(db_prefix() . 'projects', ['clientid' => get_client_user_id()]) > 0 && has_contact_permission('projects')) { ?>
            <div class="ptkc-field open-ticket-project-group">
                <label for="project_id"><?= _l('project'); ?></label>
                <select data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>" name="project_id" id="project_id" class="form-control selectpicker">
                    <option value=""></option>
                    <?php foreach ($projects as $project) { ?>
                    <option value="<?= e($project['id']); ?>" <?= set_select('project_id', $project['id']); ?><?php if ($this->input->get('project_id') == $project['id']) {
                        echo ' selected';
                    } ?>><?= e($project['name']); ?></option>
                    <?php } ?>
                </select>
            </div>
            <?php } ?>

            <div class="ptkc-grid-2">
                <div class="ptkc-field open-ticket-department-group">
                    <label for="department"><?= _l('clients_ticket_open_departments'); ?></label>
                    <select data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>" name="department" id="department" class="form-control selectpicker">
                        <option value=""></option>
                        <?php foreach ($departments as $department) { ?>
                        <option value="<?= e($department['departmentid']); ?>" <?= set_select('department', $department['departmentid'], (count($departments) == 1 ? true : false)); ?>>
                            <?= e($department['name']); ?>
                        </option>
                        <?php } ?>
                    </select>
                    <?= form_error('department'); ?>
                </div>
                <div class="ptkc-field open-ticket-priority-group">
                    <label for="priority"><?= _l('clients_ticket_open_priority'); ?></label>
                    <select data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>" name="priority" id="priority" class="form-control selectpicker">
                        <option value=""></option>
                        <?php foreach ($priorities as $priority) { ?>
                        <option value="<?= e($priority['priorityid']); ?>" <?= set_select('priority', $priority['priorityid'], hooks()->apply_filters('new_ticket_priority_selected', 2) == $priority['priorityid']); ?>>
                            <?= e(ticket_priority_translate($priority['priorityid'])); ?>
                        </option>
                        <?php } ?>
                    </select>
                    <?= form_error('priority'); ?>
                </div>
            </div>

            <?php if (get_option('services') == 1 && count($services) > 0) { ?>
            <div class="ptkc-field open-ticket-service-group">
                <label for="service"><?= _l('clients_ticket_open_service'); ?></label>
                <select data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>" name="service" id="service" class="form-control selectpicker">
                    <option value=""></option>
                    <?php foreach ($services as $service) { ?>
                    <option value="<?= e($service['serviceid']); ?>" <?= set_select('service', $service['serviceid'], (count($services) == 1 ? true : false)); ?>>
                        <?= e($service['name']); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <?php } ?>

            <div class="custom-fields">
                <?= render_custom_fields('tickets', '', ['show_on_client_portal' => 1]); ?>
            </div>

            <div class="ptkc-field open-ticket-message-group">
                <label for="message"><?= _l('clients_ticket_open_body'); ?></label>
                <textarea name="message" id="message" class="form-control" placeholder="<?= _l('clients_ticket_open_body'); ?>" rows="8"><?= set_value('message'); ?></textarea>
            </div>

            <div class="attachments_area open-ticket-attachments-area">
                <div class="attachments">
                    <div class="attachment tw-max-w-md">
                        <div class="ptkc-field">
                            <label for="attachment" class="control-label"><?= _l('clients_ticket_attachments'); ?></label>
                            <div class="input-group">
                                <input type="file"
                                    extension="<?= str_replace(['.', ' '], '', get_option('ticket_attachments_file_extensions')); ?>"
                                    filesize="<?= file_upload_max_size(); ?>"
                                    class="form-control" name="attachments[0]"
                                    accept="<?= get_ticket_form_accepted_mimes(); ?>">
                                <span class="input-group-btn">
                                    <button class="btn btn-default add_more_attachments"
                                        data-max="<?= get_option('maximum_allowed_ticket_attachments'); ?>" type="button">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <div class="ptkc-card-head" style="border-top:1px solid var(--ptkc-border);border-bottom:0;text-align:right;">
            <button type="submit" class="ptkc-btn ptkc-btn-primary" data-form="#open-new-ticket-form" autocomplete="off"
                data-loading-text="<?= _l('wait_text'); ?>" style="float:right;">
                <i class="fa-solid fa-paper-plane"></i> <?= _l('submit'); ?>
            </button>
            <div style="clear:both;"></div>
        </div>
    </div>
    <?php echo form_close(); ?>

</div>
