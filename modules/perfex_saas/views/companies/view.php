<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $is_admin_and_not_impersonating_client = is_admin() && !is_client_logged_in(); ?>

<div class="row">
    <div class="col-md-12">
        <h4 class="company-view-heading tw-mt-0 tw-font-semibold tw-text-lg tw-text-neutral-700 tw-flex tw-items-center tw-space-x-2">
            <?= _l('perfex_saas_company_view_heading'); ?>
        </h4>
        <div class="panel_s">
            <div class="panel-body">

                <?php if ($is_admin_and_not_impersonating_client && !empty($company->metadata->pending_custom_domain)) : ?>
                    <div class="alert alert-warning text-center">
                        <div>
                            <?= _l('perfex_saas_pending_domain_request', [$company->name, $company->metadata->pending_custom_domain]); ?>
                        </div>
                        <div class="tw-mt-4"><strong><?= _l('perfex_saas_pending_domain_request_hint'); ?></strong></div>
                        <div class="tw-text-2xl tw-mt-4">
                            <strong><?= $company->metadata->pending_custom_domain; ?></strong>
                        </div>
                        <div class="tw-flex tw-mt-4 tw-w-full">
                            <?php echo form_open(admin_url(PERFEX_SAAS_ROUTE_NAME . '/companies/custom_domain'), ['id' => 'custom_domain', 'class' => 'tw-w-full']); ?>
                            <?= form_hidden('id', $company->id); ?>
                            <textarea name="extra_note" class="form-control" cols="2" rows="2" placeholder="<?= _l('perfex_saas_extra_email_note'); ?>"></textarea>
                            <div class="text-left tw-flex tw-justify-between tw-w-full">
                                <input name="cancel" type="submit" data-loading-text="<?= _l('perfex_saas_saving...'); ?>" data-form="#custom_domain_form" class="btn btn-danger mtop15 mbot15" value="<?= _l('perfex_saas_cancel'); ?>" onclick="return confirm('<?= perfex_saas_ecape_js_attr(_l('perfex_saas_confirm_action', _l('perfex_saas_cancel'))); ?>');" />
                                <input name="approve" type="submit" data-loading-text="<?= _l('perfex_saas_saving...'); ?>" data-form="#custom_domain_form" class="btn btn-success mtop15 mbot15" value="<?= _l('perfex_saas_approve'); ?>" onclick="return confirm('<?= perfex_saas_ecape_js_attr(_l('perfex_saas_confirm_action', _l('perfex_saas_approve'))); ?>');" />
                            </div>
                            <?php echo form_close(); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="company-status text-center tw-mb-4">
                    <span class="badge badge-success <?= $company->status == 'active' ? 'bg-success' : 'bg-danger'; ?>">
                        <?= perfex_saas_company_status_label($company); ?>
                        <?= perfex_saas_company_status_can_deploy($company) ? '<i class="fa fa-spin fa-spinner"></i>' : ''; ?>
                    </span>
                </div>

                <div class="form-group">
                    <label for="slug"><?= _l('perfex_saas_company_name'); ?></label>
                    <p class="form-control-static"><?= $company->name; ?></p>
                </div>
                <div class="form-group">
                    <label for="slug"><?= _l('perfex_saas_slug'); ?></label>
                    <p class="form-control-static"><?= $company->slug; ?></p>
                </div>
                <div class="form-group">
                    <label for="slug"><?= _l('perfex_saas_custom_domain'); ?></label>
                    <p class="form-control-static"><?= $company->custom_domain ?? '-'; ?></p>
                </div>
                <div class="form-group">
                    <label for="company-name"><?= _l('perfex_saas_company_accessible_links'); ?></label>
                    <p class="form-control-static tw-flex tw-flex-col">
                        <?php foreach (perfex_saas_tenant_base_url($company, '', 'all') as $key => $value) : if (!$value) continue; ?>
                            <em><?= _l('perfex_saas_url_scheme_' . $key); ?>:</em>
                            <span class="tw-mb-2 tw-flex tw-flex-col">
                                <a href="<?= $value; ?>" target="_blank" data-toggle="tooltip" data-title="<?= _l('perfex_saas_customer_link'); ?>"><?= $value; ?></a>
                                <a href="<?= $value . 'admin'; ?>" target="_blank" data-toggle="tooltip" data-title="<?= _l('perfex_saas_admin_link'); ?>"><?= $value . 'admin'; ?></a>
                            </span>
                        <?php endforeach; ?>
                    </p>
                </div>

                <div class="form-group">
                    <label for="company-name"><?= _l('perfex_saas_date_created'); ?></label>
                    <p class="form-control-static"><?= time_ago($company->created_at); ?></p>
                </div>

                <?php if ($is_admin_and_not_impersonating_client) : ?>
                    <div class="form-group">
                        <label for="perfex_saas_data_location"><?= _l('perfex_saas_data_location'); ?></label>
                        <p class="form-control-static">
                            <?php
                            $dsn = $company->dsn;
                            if (!empty($dsn)) {
                                $dsn = perfex_saas_parse_dsn($dsn);
                                $dsn_string = $dsn['host'] . ':<b>' . $dsn['dbname'] . '</b>';
                            } else {
                                $dsn_string = '-';
                            }
                            echo $dsn_string;
                            ?>
                            <button class="btn btn-xs btn-primary btn-circle rounded tw-ml-2" data-toggle="modal" data-target="#dsn_modal"><i class="fa fa-edit" title="<?= _l('perfex_saas_update_datacenter'); ?>" data-toggle="tooltip"></i></button>
                        </p>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <?php if ($is_admin_and_not_impersonating_client) : ?>
            <?php
            // Current subscription + admin package-change panel
            $subscription_invoice = null;
            try {
                $subscription_invoice = !empty($company->clientid) ? $this->perfex_saas_model->get_company_invoice($company->clientid) : null;
            } catch (\Throwable $e) {
                log_message('error', 'SaaS companies view: could not load subscription invoice: ' . $e->getMessage());
            }
            $current_package_id = $subscription_invoice->{perfex_saas_column('packageid')} ?? '';
            $on_trial           = $subscription_invoice && function_exists('perfex_saas_invoice_is_on_trial') && perfex_saas_invoice_is_on_trial($subscription_invoice);

            $available_packages = [];
            if (!empty($company->clientid)) {
                $available_packages = (array) $this->perfex_saas_model->packages_filter_by_assigned_client((array) $this->perfex_saas_model->packages(), $company->clientid, $current_package_id);
            }

            $subscription_change_log = array_reverse(array_values((array) ($company->metadata->subscription_change_log ?? [])));
            ?>
            <div class="panel_s">
                <div class="panel-body">
                    <h4 class="tw-mt-0 tw-font-semibold tw-text-base tw-text-neutral-700"><?= _l('perfex_saas_subscription'); ?></h4>

                    <?php if ($subscription_invoice) : ?>
                        <div class="form-group">
                            <label><?= _l('perfex_saas_subscription_current_package'); ?></label>
                            <p class="form-control-static">
                                <strong><?= html_escape($subscription_invoice->name ?? '-'); ?></strong>
                                — <?= app_format_money((float) ($subscription_invoice->price ?? 0), get_base_currency()); ?>
                                <?php if ($on_trial) : ?>
                                    <span class="label label-info tw-ml-2"><?= _l('perfex_saas_subscription_on_trial'); ?></span>
                                <?php elseif (isset($subscription_invoice->status) && function_exists('format_invoice_status')) : ?>
                                    <span class="tw-ml-2"><?= format_invoice_status((int) $subscription_invoice->status); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($subscription_invoice->id) && empty($subscription_invoice->is_mock)) : ?>
                                    <a href="<?= admin_url('invoices/list_invoices/' . $subscription_invoice->id); ?>" class="tw-ml-2" target="_blank"><?= format_invoice_number($subscription_invoice->id); ?></a>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else : ?>
                        <p class="text-muted"><?= _l('perfex_saas_subscription_none'); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($company->clientid)) : ?>
                        <button type="button" class="btn btn-info" data-toggle="modal" data-target="#subscription_change_modal">
                            <?= _l('perfex_saas_change_subscription'); ?>
                        </button>
                    <?php endif; ?>

                    <?php if (!empty($subscription_change_log)) : ?>
                        <hr />
                        <label><?= _l('perfex_saas_subscription_change_log'); ?></label>
                        <div class="tw-max-h-64 tw-overflow-y-auto">
                            <table class="table table-condensed tw-text-xs">
                                <thead>
                                    <tr>
                                        <th><?= _l('perfex_saas_date_created'); ?></th>
                                        <th><?= _l('perfex_saas_subscription_from'); ?></th>
                                        <th><?= _l('perfex_saas_subscription_to'); ?></th>
                                        <th><?= _l('perfex_saas_subscription_changed_by'); ?></th>
                                        <th><?= _l('invoice'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscription_change_log as $entry) : $entry = (object) $entry; ?>
                                        <tr>
                                            <td><?= html_escape($entry->date ?? '-'); ?></td>
                                            <td><?= html_escape($entry->from_package ?? '-'); ?></td>
                                            <td>
                                                <?= html_escape($entry->to_package ?? '-'); ?>
                                                <?php if (!empty($entry->pending_payment)) : ?>
                                                    <span class="label label-warning"><?= _l('perfex_saas_subscription_pending_payment_label'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= html_escape($entry->staff_name ?? '-'); ?></td>
                                            <td>
                                                <?php if (!empty($entry->invoice_id)) : ?>
                                                    <a href="<?= admin_url('invoices/list_invoices/' . (int) $entry->invoice_id); ?>" target="_blank"><?= format_invoice_number((int) $entry->invoice_id); ?></a>
                                                <?php else : ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php if (!empty($entry->note)) : ?>
                                            <tr>
                                                <td colspan="5" class="text-muted tw-italic"><?= html_escape($entry->note); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Change subscription modal -->
            <div class="modal fade" id="subscription_change_modal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <?php echo form_open(admin_url(PERFEX_SAAS_ROUTE_NAME . '/companies/change_subscription'), ['id' => 'subscription_change_form', 'onsubmit' => "return confirm('" . perfex_saas_ecape_js_attr(_l('perfex_saas_subscription_change_confirm')) . "');"]); ?>
                        <?php echo form_hidden('company_id', $company->id); ?>
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title"><?= _l('perfex_saas_change_subscription'); ?></h4>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <?= _l('perfex_saas_subscription_change_warning'); ?>
                            </div>
                            <div class="form-group select-placeholder">
                                <label for="packageid"><?= _l('perfex_saas_subscription_new_package'); ?></label>
                                <select name="packageid" id="packageid" class="selectpicker" data-live-search="true" data-width="100%" required
                                    data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                                    <option value=""></option>
                                    <?php foreach ($available_packages as $p) : ?>
                                        <?php if ((isset($p->status) && $p->status != '1') || $p->id == $current_package_id) continue; ?>
                                        <option value="<?= $p->id; ?>">
                                            <?= html_escape($p->name); ?> — <?= app_format_money((float) $p->price, get_base_currency()); ?><?= $p->is_private == '1' ? ' (' . _l('perfex_saas_private') . ')' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="change_note"><?= _l('perfex_saas_subscription_change_note'); ?></label>
                                <textarea name="change_note" id="change_note" class="form-control" rows="2" maxlength="500"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                            <button type="submit" class="btn btn-info" data-loading-text="<?= _l('perfex_saas_saving...'); ?>" data-form="#subscription_change_form"><?= _l('perfex_saas_change_subscription'); ?></button>
                        </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($is_admin_and_not_impersonating_client) : ?>
    <div class="modal fade" id="dsn_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><?= _l('perfex_saas_update_datacenter'); ?></h4>
                </div>
                <div class="modal-body">

                    <div class="alert alert-warning">
                        <?= _l('perfex_saas_update_datacenter_warning'); ?>
                    </div>
                    <?php echo form_hidden('company_id', $company->id); ?>
                    <?php
                    $_dsn = $dsn;
                    if (empty($_dsn['user']) && !empty($_dsn['host'])) {
                        $_dsn['user'] = perfex_saas_master_dsn()['user'];
                    }
                    require('partials/db_pool.php');
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="button" class="btn btn-primary update-datacenter" onclick="saasUpdateDatacenter()"><?= _l('perfex_saas_update_datacenter'); ?></button>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->

    <script>
        function saasUpdateDatacenter() {
            const button = $("button.update-datacenter");
            button.addClass("disabled");

            let modal = $("#dsn_modal");

            let data = {};
            modal
                .find("input, select")
                .each(function() {
                    let thisInput = $(this);
                    data[thisInput.attr("name")] = thisInput.val();
                });

            // Send AJAX request to test the database connection
            try {
                $.post(admin_url + SAAS_MODULE_NAME + "/companies/update_dsn", data)
                    .done(function(response) {
                        response = JSON.parse(response);
                        if (response.status) {
                            alert_float(response.status, response.message);
                        }
                        if (response.status == 'success') {
                            $("button[data-dismiss='modal']").click();
                            setTimeout(() => {
                                window.location.reload();
                            }, 3000);
                        }
                    }).always(function() {
                        button.removeClass("disabled");
                    });
            } catch (error) {
                console.trace(error)
            }
        }
    </script>
<?php endif; ?>