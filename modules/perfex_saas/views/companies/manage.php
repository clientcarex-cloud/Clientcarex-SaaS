<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo module_dir_url('perfex_saas', 'assets/css/ccx_companies.css'); ?>?v=<?php echo time(); ?>">
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <?php if (staff_can('create', 'perfex_saas_companies')) { ?>
                    <div class="tw-mb-4">
                        <a href="<?php echo admin_url(PERFEX_SAAS_ROUTE_NAME . '/companies/create'); ?>"
                            class="btn btn-primary">
                            <i class="fa-regular fa-plus tw-mr-1"></i>
                            <?php echo _l('perfex_saas_new_company'); ?>
                        </a>
                    </div>
                <?php } ?>

                <!-- Search Bar -->
                <div class="ccx-co-search-bar">
                    <form action="<?php echo admin_url(PERFEX_SAAS_ROUTE_NAME . '/companies'); ?>" method="GET" class="ccx-co-search-form">
                        <div class="ccx-co-search-wrap">
                            <i class="fa fa-search ccx-co-search-icon"></i>
                            <input type="text" name="q" id="ccx-table-search" class="ccx-co-search-input"
                                placeholder="<?php echo _l('perfex_saas_search_placeholder'); ?>"
                                autocomplete="off" value="<?php echo isset($search) ? e($search) : ''; ?>">
                            <?php if (!empty($search)) { ?>
                                <a href="<?php echo admin_url(PERFEX_SAAS_ROUTE_NAME . '/companies'); ?>" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #9ca3af;">
                                    <i class="fa fa-times"></i>
                                </a>
                            <?php } ?>
                        </div>
                    </form>
                    <div class="ccx-co-count">
                        <span id="ccx-visible-count"><?php echo count($companies); ?></span> companies
                    </div>
                </div>

                <!-- Companies Table -->
                <div class="ccx-co-table-card">
                    <?php if (count($companies) == 0) { ?>
                        <div class="ccx-co-empty">
                            <i class="fa fa-folder-open"></i>
                            <p><?php echo _l('perfex_saas_no_companies_found'); ?></p>
                        </div>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="ccx-co-table" id="ccx-companies-table">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('perfex_saas_name'); ?></th>
                                        <th><?php echo _l('perfex_saas_clients_list_company'); ?></th>
                                        <th><?php echo _l('perfex_saas_company_status'); ?></th>
                                        <th><?php echo _l('perfex_saas_subscription'); ?></th>
                                        <th><?php echo _l('perfex_saas_data_location'); ?></th>
                                        <th><?php echo _l('perfex_saas_modules'); ?></th>
                                        <th><?php echo _l('perfex_saas_date_created'); ?></th>
                                        <th class="ccx-co-th-right"><?php echo _l('perfex_saas_options'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($companies as $company) {
                                        $invoice = $company->invoice;
                                        $viewLink = perfex_saas_tenant_admin_url($company);
                                        $editLink = admin_url(PERFEX_SAAS_ROUTE_NAME . '/companies/edit/' . $company->id);

                                        $is_single_package = perfex_saas_is_single_package_mode();
                                        $package_id_col = perfex_saas_column('packageid');
                                        $packageLink = '';
                                        if (!empty($invoice) && !empty($invoice->{$package_id_col}))
                                            $packageLink = $is_single_package ? admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing') : admin_url(PERFEX_SAAS_ROUTE_NAME . '/packages/edit/' . $invoice->{$package_id_col});
                                        
                                        $statusClass = 'danger';
                                        if ($company->status == PERFEX_SAAS_STATUS_ACTIVE) {
                                            $statusClass = 'success';
                                        } elseif ($company->status == PERFEX_SAAS_STATUS_PENDING) {
                                            $statusClass = 'info';
                                        }
                                    ?>
                                        <tr class="ccx-co-row">
                                            <!-- Company Name -->
                                            <td>
                                                <a href="<?php echo $viewLink; ?>" target="_blank" class="ccx-co-company-link">
                                                    <span class="ccx-co-avatar"><?php echo strtoupper(substr($company->name, 0, 2)); ?></span>
                                                    <span><?php echo e($company->name); ?></span>
                                                    <i class="fa fa-external-link ccx-co-ext-icon"></i>
                                                </a>
                                            </td>

                                            <!-- Client -->
                                            <td>
                                                <a href="<?php echo admin_url('clients/client/' . $company->clientid); ?>" class="ccx-co-client-link">
                                                    <?php echo e($company->client_name); ?>
                                                </a>
                                            </td>

                                            <!-- Status -->
                                            <td>
                                                <span class="ccx-co-badge ccx-co-badge-<?php echo $statusClass; ?>">
                                                    <?php echo _l($company->status, '', false); ?>
                                                </span>
                                                <?php if ($company->status != PERFEX_SAAS_STATUS_ACTIVE && !empty($company->status_note)) { ?>
                                                    <span class='tw-ml-2' data-toggle='tooltip' data-title='<?php echo e($company->status_note); ?>'><i class='fa fa-warning tw-text-yellow-500'></i></span>
                                                <?php } ?>
                                            </td>

                                            <!-- Subscription -->
                                            <td>
                                                <?php if (!empty($invoice) && !empty($invoice->id)) { ?>
                                                    <?php if (!empty($invoice->name)) { ?>
                                                        <span class="ccx-co-badge ccx-co-badge-plan">
                                                            <i class="fa fa-cube"></i>
                                                            <a href="<?php echo $packageLink; ?>" target="_blank" style="color: inherit;"><?php echo _l($invoice->name, '', false); ?></a>
                                                        </span>
                                                    <?php } else { ?>
                                                        <span class="ccx-co-no-data">-</span>
                                                    <?php } ?>
                                                    <?php if (!isset($invoice->is_mock)) { ?>
                                                        <div class="tw-mt-1">
                                                            <a href="<?php echo admin_url('invoices/list_invoices/' . $invoice->id); ?>" target="_blank" class="tw-text-xs tw-text-gray-500 hover:tw-text-blue-600">
                                                                <?php echo _l('invoice'); ?> <i class="fa fa-external-link tw-ml-0.5"></i>
                                                            </a>
                                                        </div>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <span class="ccx-co-no-data">-</span>
                                                <?php } ?>
                                            </td>

                                            <!-- Data Location -->
                                            <td>
                                                <?php if (!empty($company->dsn)) { 
                                                    $_dsn = perfex_saas_parse_dsn($company->dsn);
                                                ?>
                                                    <div class="tw-flex tw-flex-col">
                                                        <span class="tw-text-xs tw-text-gray-400"><?php echo $_dsn['host']; ?></span>
                                                        <span class="tw-font-medium tw-text-gray-700"><?php echo $_dsn['dbname']; ?></span>
                                                    </div>
                                                <?php } else { ?>
                                                    <span class="ccx-co-no-data">-</span>
                                                <?php } ?>
                                            </td>

                                            <!-- Modules -->
                                            <td>
                                                <?php
                                                    $metadataObj = is_object($company->metadata) ? $company->metadata : new stdClass();
                                                    $modules_disabled = (array) ($metadataObj->disabled_modules ?? []);
                                                    $admin_disabled = (array) ($metadataObj->admin_disabled_modules ?? []);
                                                    $disabled_modules = trim(implode(', ', array_merge($modules_disabled, $admin_disabled)), ', ');
                                                    $admin_approved_modules = trim(implode(', ', (array) ($metadataObj->admin_approved_modules ?? [])), ', ');

                                                    if (!empty($disabled_modules)) {
                                                        echo '<div class="tw-mb-1"><span class="tw-bg-red-50 tw-text-red-700 tw-px-1.5 tw-py-0.5 tw-rounded tw-text-xs">' . _l('perfex_saas_disabled_modules') . '</span> <span class="tw-text-xs">' . e($disabled_modules) . '</span></div>';
                                                    }
                                                    if (!empty($admin_approved_modules)) {
                                                        echo '<div><span class="tw-bg-green-50 tw-text-green-700 tw-px-1.5 tw-py-0.5 tw-rounded tw-text-xs">' . _l('perfex_saas_admin_approved_modules') . '</span> <span class="tw-text-xs">' . e($admin_approved_modules) . '</span></div>';
                                                    }
                                                ?>
                                            </td>

                                            <!-- Created -->
                                            <td class="ccx-co-date">
                                                <?php echo _d($company->created_at); ?>
                                            </td>

                                            <!-- Actions -->
                                            <td class="ccx-co-actions">
                                                <div class="tw-flex tw-justify-end tw-items-center tw-space-x-2">
                                                    <?php
                                                        $can_view = is_admin() || staff_can('ccx_login_as_client', 'ccx_support');
                                                        $can_edit = is_admin() || staff_can('ccx_edit_as_client', 'ccx_support');
                                                    ?>
                                                    <?php if ($can_view) { ?>
                                                        <a href="<?php echo admin_url('clients/login_as_client/' . $company->clientid . '?mode=view'); ?>"
                                                            target="_blank" class="ccx-co-btn ccx-co-btn-view ccx-co-btn-sm"
                                                            data-toggle="tooltip" title="View Only — browse without making changes">
                                                            <i class="fa fa-eye"></i> View
                                                        </a>
                                                    <?php } ?>
                                                    <?php if ($can_edit) { ?>
                                                        <a href="<?php echo admin_url('clients/login_as_client/' . $company->clientid . '?mode=edit'); ?>"
                                                            target="_blank" class="ccx-co-btn ccx-co-btn-edit ccx-co-btn-sm"
                                                            data-toggle="tooltip" title="Full Access — login with edit permissions">
                                                            <i class="fa fa-pencil"></i> Edit
                                                        </a>
                                                    <?php } ?>
                                                </div>

                                                <div class="tw-flex tw-justify-end tw-items-center tw-space-x-3 tw-mt-3">
                                                    <?php
                                                    $notice = (empty($company->metadata) || !is_object($company->metadata) || empty($company->metadata->pending_custom_domain)) ? "" : "<span class='tw-mr-2' data-toggle='tooltip' data-title='" . strip_tags(_l("perfex_saas_pending_domain_request", [$company->name, $company->metadata->pending_custom_domain])) . "'><i class='fa fa-warning tw-text-yellow-500'></i></span>";
                                                    ?>
                                                    <a href="<?php echo $editLink; ?>"
                                                        class="tw-text-gray-400 hover:tw-text-gray-600 tw-transition-colors"
                                                        data-toggle="tooltip" title="<?php echo _l('view'); ?>">
                                                        <?php echo $notice; ?> <i class="fa fa-eye"></i>
                                                    </a>
                                                    <?php if (staff_can('edit', 'perfex_saas_companies')) { ?>
                                                        <a href="#"
                                                            class="tw-text-green-400 hover:tw-text-green-600 tw-transition-colors saas_company_set_password"
                                                            data-id="<?php echo $company->id; ?>"
                                                            data-toggle="tooltip" title="Set Superadmin Credentials">
                                                            <i class="fa-solid fa-key"></i>
                                                        </a>
                                                        <a href="<?php echo $editLink; ?>"
                                                            class="tw-text-blue-400 hover:tw-text-blue-600 tw-transition-colors"
                                                            data-toggle="tooltip" title="<?php echo _l('edit'); ?>">
                                                            <i class="fa-regular fa-pen-to-square"></i>
                                                        </a>
                                                    <?php } ?>
                                                    <?php if (staff_can('delete', 'perfex_saas_companies') && $company->status !== PERFEX_SAAS_STATUS_PENDING_DELETE) { ?>
                                                        <a href="<?php echo admin_url(PERFEX_SAAS_ROUTE_NAME . '/companies/delete/' . $company->id); ?>"
                                                            class="tw-text-red-400 hover:tw-text-red-600 tw-transition-colors saas_company_delete"
                                                            data-toggle="tooltip" title="<?php echo _l('delete'); ?>">
                                                            <i class="fa-regular fa-trash-can"></i>
                                                        </a>
                                                    <?php } ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1) { ?>
                        <div class="ccx-co-pagination">
                            <div class="ccx-co-page-info">
                                <?php echo _l('perfex_saas_showing_page', ['<strong>' . $current_page . '</strong>', '<strong>' . $total_pages . '</strong>']); ?>
                            </div>
                            <nav class="ccx-co-page-nav">
                                <?php if ($current_page > 1) { ?>
                                    <a href="<?php echo admin_url(PERFEX_SAAS_ROUTE_NAME . '/companies?page=' . ($current_page - 1) . (!empty($search) ? '&q=' . urlencode($search) : '')); ?>"
                                        class="ccx-co-page-btn">
                                        <i class="fa fa-chevron-left"></i>
                                    </a>
                                <?php } ?>
                                <span class="ccx-co-page-current"><?php echo $current_page; ?></span>
                                <?php if ($current_page < $total_pages) { ?>
                                    <a href="<?php echo admin_url(PERFEX_SAAS_ROUTE_NAME . '/companies?page=' . ($current_page + 1) . (!empty($search) ? '&q=' . urlencode($search) : '')); ?>"
                                        class="ccx-co-page-btn">
                                        <i class="fa fa-chevron-right"></i>
                                    </a>
                                <?php } ?>
                            </nav>
                        </div>
                    <?php } ?>
                </div>

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    "use strict";
    $(function () {
        // initDataTable('.table-companies', window.location.href, undefined, [5], undefined, [6, "desc"]);

        $(document).on('click', '.saas_company_delete', function (e) {
            e.preventDefault();
            var url = $(this).attr('href');
            var form = $(this).closest('form');
            var requiredPassword = "&*73hgh%g";

            var processDelete = function () {
                if (form.length > 0) {
                    form.submit();
                } else {
                    window.location.href = url;
                }
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: "Verify Password to Delete",
                    input: "password",
                    inputLabel: "Enter the verification password",
                    inputPlaceholder: "Enter password",
                    showCancelButton: true,
                    confirmButtonText: "Delete",
                    showLoaderOnConfirm: true,
                    preConfirm: async (password) => {
                        if (password !== requiredPassword) {
                            Swal.showValidationMessage("Incorrect password");
                            return false;
                        }
                        return true;
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed) {
                        processDelete();
                    }
                });
            } else {
                var password = prompt("Verify Password to Delete. Enter the verification password:");
                if (password === requiredPassword) {
                    processDelete();
                } else if (password !== null) {
                    alert("Incorrect password");
                }
            }
        });

        // Set Admin Credentials Handler
        $(document).on('click', '.saas_company_set_password', function (e) {
            e.preventDefault();
            var companyId = $(this).data('id');
            var btn = $(this);
            btn.addClass('disabled');
            
            $.get(admin_url + 'perfex_saas/companies/get_tenant_credentials/' + companyId).done(function(res) {
                btn.removeClass('disabled');
                if (typeof res === 'string') {
                    res = JSON.parse(res);
                }
                
                if (!res.success) {
                    alert_float('danger', res.message);
                    return;
                }
                
                var currentEmail = res.email;
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: "Update Credentials",
                        html:
                            '<div class="form-group text-left tw-mb-4"><label class="tw-block tw-mb-1 tw-text-sm tw-font-medium tw-text-gray-700">Email Address</label><input id="swal-input-email" class="form-control tw-w-full" placeholder="Email" value="' + currentEmail + '"></div>' +
                            '<div class="form-group text-left"><label class="tw-block tw-mb-1 tw-text-sm tw-font-medium tw-text-gray-700">New Password <small class="tw-text-gray-500">(leave blank to keep current)</small></label><input id="swal-input-password" class="form-control tw-w-full" type="password" placeholder="New Password"></div>',
                        focusConfirm: false,
                        showCancelButton: true,
                        confirmButtonText: "Update Credentials",
                        showLoaderOnConfirm: true,
                        preConfirm: async () => {
                            const email = document.getElementById('swal-input-email').value;
                            const password = document.getElementById('swal-input-password').value;
                            
                            if (!email) {
                                Swal.showValidationMessage("Email is required");
                                return false;
                            }
                            
                            return $.post(admin_url + 'perfex_saas/companies/set_password', {
                                company_id: companyId,
                                email: email,
                                password: password
                            }).then(response => {
                                if (typeof response === 'string') {
                                    response = JSON.parse(response);
                                }
                                if (!response.success) {
                                    throw new Error(response.message);
                                }
                                return response;
                            }).catch(error => {
                                Swal.showValidationMessage(error.message);
                            });
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        if (result.isConfirmed) {
                            alert_float('success', result.value.message);
                        }
                    });
                } else {
                    var email = prompt("Enter the superadmin email for this tenant:", currentEmail);
                    if (email !== null && email !== "") {
                        var password = prompt("Enter the new superadmin password (leave blank to keep current):");
                        if (password !== null) {
                            $.post(admin_url + 'perfex_saas/companies/set_password', {
                                company_id: companyId,
                                email: email,
                                password: password
                            }).done(function(response) {
                                if (typeof response === 'string') {
                                    response = JSON.parse(response);
                                }
                                if (response.success) {
                                    alert_float('success', response.message);
                                } else {
                                    alert_float('danger', response.message);
                                }
                            });
                        }
                    }
                }
            }).fail(function() {
                btn.removeClass('disabled');
                alert_float('danger', 'Failed to retrieve credentials.');
            });
        });

        /* ─── Real-time Table Search ─── */
        var searchInput = document.getElementById('ccx-table-search');
        var countSpan   = document.getElementById('ccx-visible-count');
        var table       = document.getElementById('ccx-companies-table');
        if (searchInput && table) {
            var rows      = table.querySelectorAll('tbody tr.ccx-co-row');
            var tableCard = table.closest('.table-responsive');
            
            var noResults = document.createElement('div');
            noResults.className = 'ccx-co-empty ccx-co-no-filter-results';
            noResults.style.display = 'none';
            noResults.innerHTML = '<i class="fa fa-search"></i><p>No companies match your search</p>';
            if (tableCard) {
                tableCard.parentNode.insertBefore(noResults, tableCard.nextSibling);
            }

            searchInput.addEventListener('input', function() {
                var query = this.value.toLowerCase().trim();
                var visible = 0;

                rows.forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    if (!query || text.indexOf(query) !== -1) {
                        row.style.display = '';
                        visible++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (countSpan) {
                    countSpan.textContent = visible;
                }

                if (visible === 0 && query) {
                    noResults.style.display = '';
                    if (tableCard) tableCard.style.display = 'none';
                } else {
                    noResults.style.display = 'none';
                    if (tableCard) tableCard.style.display = '';
                }
            });
        }
    });
</script>
</body>

</html>