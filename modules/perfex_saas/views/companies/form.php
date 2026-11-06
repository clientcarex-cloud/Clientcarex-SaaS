<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $select_attr = ['multiple' => 'true', 'allow_blank' => 'true', 'class' => 'selectpicker display-block', 'data-actions-box' => 'true', 'data-width' => '100%']; ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-7">
                <h4
                    class="tw-mt-0 tw-font-semibold tw-text-lg tw-text-neutral-700 tw-flex tw-items-center tw-space-x-2">
                    <span>
                        <?php echo isset($company) ? $company->name : _l('perfex_saas_new_company'); ?>
                    </span>
                </h4>
                <div class="panel_s">
                    <div class="panel-body">

                        <?php echo validation_errors('<div class="alert alert-danger text-center">', '</div>'); ?>
                        <?php $this->load->view('authentication/includes/alerts'); ?>

                        <?php echo form_open_multipart($this->uri->uri_string(), ['id' => 'companies_form']); ?>

                        <?php if (isset($company)) echo form_hidden('id', $company->id); ?>

                        <?php $value = (isset($company) ? $company->name : ''); ?>
                        <?php echo render_input('name', 'name', $value); ?>

                        <?php if (!isset($company))
                            echo render_input('slug', _l('perfex_saas_create_company_slug') . perfex_saas_form_label_hint('perfex_saas_create_company_slug_hint', perfex_saas_get_saas_default_host()), $value, 'text', ['maxlength' => PERFEX_SAAS_MAX_SLUG_LENGTH]);
                        ?>

                        <?php $value = isset($company) ? $company->custom_domain : ''; ?>
                        <?= render_input('custom_domain', _l('perfex_saas_custom_domain') . perfex_saas_form_label_hint('perfex_saas_custom_domain_hint'), $value, 'text', [], [], "text-left tw-mb-4"); ?>


                        <!-- contact selection -->
                        <div class="form-group select-placeholder">
                            <label for="clientid"
                                class="control-label"><?php echo _l('perfex_saas_invoice_select_customer'); ?></label>
                            <select id="clientid" name="clientid" data-live-search="true" data-width="100%"
                                class="ajax-search<?php if (isset($company) && empty($company->clientid)) {
                                                                                                                                    echo ' customer-removed';
                                                                                                                                } ?>"
                                data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                                <?php $selected = (isset($company) ? $company->clientid : '');
                                if ($selected == '') {
                                    $selected = (isset($customer_id) ? $customer_id : '');
                                }
                                if ($selected != '') {
                                    $rel_data = get_relation_data('customer', $selected);
                                    $rel_val  = get_relation_values($rel_data, 'customer');
                                    echo '<option value="' . $rel_val['id'] . '" selected>' . $rel_val['name'] . '</option>';
                                } ?>
                            </select>
                        </div>

                        <?php $modules = $this->perfex_saas_model->modules(); ?>


                        <?php 
                            // Determine effective package modules
                            $package_modules = isset($package_modules) ? $package_modules : []; 
                            
                            // Get current settings
                            $admin_approved = (array)(isset($company->metadata->admin_approved_modules) ? $company->metadata->admin_approved_modules : []);
                            $admin_disabled = (array)(isset($company->metadata->admin_disabled_modules) ? $company->metadata->admin_disabled_modules : []);
                        ?>


                        <!-- admin assigned modules selection -->
                        <div class="tw-mt-8 tw-mb-8">
                            <?php $label = _l('perfex_saas_admin_approved_modules') . perfex_saas_form_label_hint("perfex_saas_admin_approved_modules_hint"); ?>
                            <?php echo perfex_saas_render_select('metadata[admin_approved_modules][]', $modules, ['system_name', ['custom_name']], $label, $admin_approved, $select_attr); ?>
                        </div>

                        <!-- admin disabled modules selection -->
                        <div class="tw-mt-8 tw-mb-8">
                            <?php $label = _l('perfex_saas_admin_disabled_modules') . perfex_saas_form_label_hint('perfex_saas_admin_disabled_modules_hint'); ?>
                            <?php echo perfex_saas_render_select('metadata[admin_disabled_modules][]', $modules, ['system_name', ['custom_name']], $label, $admin_disabled, $select_attr); ?>
                        </div>
                        
                        <!-- disabled default modules -->
                        <div class="tw-mt-8 tw-mb-8">
                            <?php $selected = $company->metadata->admin_disabled_default_modules ?? ''; ?>
                            <?php $default_modules = $this->perfex_saas_model->default_modules(); ?>
                            <?php $label =  _l('perfex_saas_disabled_default_modules') . perfex_saas_form_label_hint('perfex_saas_disabled_default_modules_hint'); ?>
                            <?= perfex_saas_render_select('metadata[admin_disabled_default_modules][]', $default_modules, ['system_name', ['custom_name']], $label, $selected, $select_attr); ?>
                        </div>

                        <!-- Remote Module Activation Section -->
                        <?php if (isset($company) && isset($tenant_module_status)): ?>
                        <div class="panel_s tw-mt-8 tw-bg-neutral-50 tw-border tw-border-solid tw-border-neutral-200">
                            <div class="panel-body">
                                <?php
                                    $remote_pkg_modules = array_values(array_intersect((array) $package_modules, array_column($modules, 'system_name')));
                                    $remote_pkg_inactive = array_values(array_filter($remote_pkg_modules, function ($m) use ($tenant_module_status) {
                                        return !(isset($tenant_module_status[$m]) && $tenant_module_status[$m] == '1');
                                    }));
                                ?>
                                <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-2 tw-mb-2">
                                    <h4 class="tw-my-0 tw-font-semibold tw-text-base tw-text-neutral-700">
                                        Remote Module Activation
                                    </h4>
                                    <?php if (!empty($remote_pkg_modules)): ?>
                                    <button type="button" class="btn btn-info btn-sm" id="remote-bulk-activate-package"
                                            data-modules="<?= html_escape(json_encode($remote_pkg_modules)); ?>"
                                            <?= empty($remote_pkg_inactive) ? 'disabled' : '' ?>>
                                        <i class="fa fa-bolt"></i> Activate all package modules
                                        (<span id="remote-bulk-inactive-count"><?= count($remote_pkg_inactive) ?></span> inactive)
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <p class="tw-text-sm tw-text-neutral-500 tw-mb-4">
                                    Directly activate or deactivate modules in the tenant's database. <strong class="text-danger">Warning: This bypasses the admin approval list constraints.</strong>
                                </p>

                                <div id="remote-activation-log-wrap" class="tw-mb-4" style="display:none;">
                                    <div class="tw-flex tw-items-center tw-justify-between tw-mb-1">
                                        <strong class="tw-text-sm" id="remote-activation-progress">Activation log</strong>
                                        <span>
                                            <button type="button" class="btn btn-default btn-xs" id="remote-activation-stop" style="display:none;"><i class="fa fa-stop"></i> Stop after current</button>
                                            <button type="button" class="btn btn-default btn-xs" id="remote-activation-copy"><i class="fa fa-copy"></i> Copy log</button>
                                        </span>
                                    </div>
                                    <div class="progress tw-mb-1" style="height:6px;"><div class="progress-bar progress-bar-info" id="remote-activation-bar" style="width:0%"></div></div>
                                    <pre id="remote-activation-log" style="max-height:360px;overflow:auto;background:#0f172a;color:#e2e8f0;font-size:12px;line-height:1.45;padding:10px;border-radius:4px;white-space:pre-wrap;margin:0;"></pre>
                                </div>
                                
                                <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                                    <?php foreach ($modules as $module): ?>
                                        <?php 
                                            $system_name = $module['system_name'];
                                            $custom_name = $module['custom_name'] ?? $system_name;
                                            $is_remote_active = isset($tenant_module_status[$system_name]) && $tenant_module_status[$system_name] == '1';
                                        ?>
                                        <div class="tw-flex tw-items-center tw-justify-between tw-p-3 tw-bg-white tw-rounded tw-border tw-border-solid tw-border-neutral-200">
                                            <div class="tw-flex tw-flex-col">
                                                <span class="tw-font-medium text-neutral-700">
                                                    <?= $custom_name ?>
                                                    <?php if(in_array($system_name, $package_modules)): ?>
                                                        <span class="label label-info tw-ml-2"><?= _l('package'); ?></span>
                                                    <?php endif; ?>
                                                    <?php if(in_array($system_name, $admin_approved)): ?>
                                                        <span class="label label-success tw-ml-2"><?= _l('approved'); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="tw-text-xs tw-text-neutral-400"><?= $system_name ?></span>
                                            </div>
                                            <div class="onoffswitch">
                                                <input type="checkbox" 
                                                       data-module="<?= $system_name ?>"
                                                       class="onoffswitch-checkbox remote-module-toggle" 
                                                       id="remote_toggle_<?= $system_name ?>" 
                                                       <?= $is_remote_active ? 'checked' : '' ?>>
                                                <label class="onoffswitch-label" for="remote_toggle_<?= $system_name ?>"></label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- status selection -->
                        <?php if (isset($company) && $company->status != PERFEX_SAAS_STATUS_PENDING) : ?>
                        <div class="tw-mt-8 tw-mb-8">
                            <?php $selected = (isset($company) ? $company->status : ''); ?>
                            <?php $company_status_list = $this->perfex_saas_model->company_status_list(); ?>
                            <?php echo render_select('status', $company_status_list, ['key', ['label']], 'perfex_saas_company_status', $selected, [], [], '', '', false); ?>
                        </div>
                        <!-- clear any status notes -->
                        <input name="status_note" type="hidden" value="" />
                        <?php endif ?>


                        <?php if (!isset($company) || (empty($company->dsn) && !empty($company->status) && $company->status == PERFEX_SAAS_STATUS_PENDING)) : ?>
                        <!-- DB scheme -->
                        <?php $value = (isset($company->db_scheme) ? $company->db_scheme : (isset($company) && empty($company->dsn) ? 'shard' : '')); ?>
                        <?php $db_schemes = $this->perfex_saas_model->db_schemes_alt(); ?>
                        <?php echo render_select('db_scheme', $db_schemes, ['key', ['label']], 'perfex_saas_db_scheme', $value, [], [], '', '', false); ?>

                        <!-- DB pools -->
                        <div
                            class="form-group tw-mt-8 tw-mb-8 db_pools <?= in_array($value, ['shard', 'single_pool']) ? '' : 'hidden'; ?>">
                            <?php require('partials/db_pool.php'); ?>
                        </div>
                        <?php endif ?>


                        <div class="text-right">
                            <button type="submit" data-loading-text="<?= _l('perfex_saas_saving...'); ?>"
                                data-form="#companies_form"
                                class="btn btn-primary mtop15 mbot15"><?php echo _l('perfex_saas_submit'); ?></button>
                        </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
            <?php if (!empty($company)) : ?>
            <div class="col-md-5">
                <?php if (is_admin()) $this->load->view('companies/partials/tenant_health'); ?>
                <?= $this->load->view("companies/view", ['company' => $company]); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
"use strict";

<?php if (isset($company)) : ?>
const perfex_saas_company_id = "<?= $company->id ?>";
<?php endif ?>

$(document).ready(function() {
    saasCompanyFormScript();

    // ---------------- Tenant health & re-create ----------------
    if ($('#tenant_health_panel').length && typeof perfex_saas_company_id !== 'undefined') {
        var thEsc = function (v) { return $('<div>').text(v === null || v === undefined ? '' : String(v)).html(); };
        var thReport = null;

        var thRender = function (r) {
            thReport = r;
            var ok = r.connected && r.tables > 0;
            var noAdmin = r.connected && r.admins === 0;
            var badge = !r.login_readable ? '<span class="label label-danger">Login unreadable</span>'
                : !r.connected ? '<span class="label label-danger">Database not reachable</span>'
                : r.tables === 0 ? '<span class="label label-warning">Database empty</span>'
                : noAdmin ? '<span class="label label-danger">No administrator</span>'
                : '<span class="label label-success">Healthy</span>';
            var h = '<p>' + badge + ' &nbsp;<small class="text-muted">status: ' + thEsc(r.status) + (r.status_note ? ' — ' + thEsc(r.status_note) : '') + '</small></p>'
                + '<table class="table table-condensed small tw-mb-0"><tbody>'
                + '<tr><td class="text-muted">Database</td><td>' + thEsc(r.dbname) + (r.host ? ' <small class="text-muted">@ ' + thEsc(r.host) + '</small>' : '') + '</td></tr>'
                + '<tr><td class="text-muted">MySQL user</td><td>' + thEsc(r.user) + '</td></tr>'
                + '<tr><td class="text-muted">Connection</td><td class="' + (r.connected ? 'text-success' : 'text-danger') + '">' + thEsc(r.message) + '</td></tr>';
            if (r.connected) {
                h += '<tr><td class="text-muted">Tenant tables</td><td>' + r.tables + '</td></tr>';
                $.each(r.counts || {}, function (k, v) { h += '<tr><td class="text-muted">' + thEsc(k) + '</td><td>' + Number(v).toLocaleString() + '</td></tr>'; });
                if (r.admins !== null && r.admins !== undefined) h += '<tr><td class="text-muted">Active admins</td><td class="' + (r.admins ? '' : 'text-danger') + '">' + r.admins + '</td></tr>';
                if (r.last_activity) h += '<tr><td class="text-muted">Last activity</td><td>' + thEsc(r.last_activity) + '</td></tr>';
            }
            h += '</tbody></table>';
            if (noAdmin) {
                h += '<div class="alert alert-warning tw-mt-2 tw-mb-0 small">This tenant has no active administrator, so logging in to it fails with "Instance does not have any staff". '
                    + 'Create one from the customer\'s primary contact (same email and password): '
                    + '<button type="button" class="btn btn-warning btn-xs tw-ml-1" id="th_repair_admin"><i class="fa fa-user-plus"></i> Create admin</button></div>';
            }
            $('#th_body').html(h);

            // Re-create is offered only when there is nothing to lose
            var canRecreate = !ok && (!r.connected || r.tables === 0) && (r.connected || r.can_create_db);
            $('#th_actions').toggle(canRecreate);
            var m = '';
            if (r.connected) m = '<option value="cpanel">Database exists — install fresh instance</option>';
            else {
                if (r.cpanel) m += '<option value="cpanel">Create database via cPanel</option>';
                m += '<option value="sql">Create database directly in MySQL</option>';
            }
            $('#th_method').html(m).toggle(!r.connected);
        };

        var thCheck = function () {
            $('#th_body').html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Checking the tenant database…</p>');
            $.getJSON(admin_url + 'perfex_saas/companies/tenant_health/' + perfex_saas_company_id).done(function (r) {
                if (!r.success) { $('#th_body').html('<div class="alert alert-danger">' + thEsc(r.message) + '</div>'); return; }
                thRender(r);
            }).fail(function () { $('#th_body').html('<div class="alert alert-danger">Health check failed</div>'); });
        };

        var thRun = function (password) {
            var $b = $('#th_recreate').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Re-creating… (can take a minute)');
            $('#th_steps').html('');
            var data = { company_id: perfex_saas_company_id, verify_password: password, method: $('#th_method').val() || 'cpanel' };
            data[csrfData['token_name']] = csrfData['hash'];
            $.post(admin_url + 'perfex_saas/companies/tenant_recreate', data, null, 'json').done(function (r) {
                var steps = (r.steps || []).map(function (s) { return '<div class="' + (s.indexOf('FAILED') !== -1 ? 'text-danger' : 'text-muted') + '">• ' + thEsc(s) + '</div>'; }).join('');
                $('#th_steps').html(steps + '<div class="tw-mt-1 ' + (r.success ? 'text-success' : 'text-danger') + '"><b>' + (r.success ? '✓ Tenant re-created. Set its admin login from the Companies list (key icon) if needed.' : thEsc(r.message)) + '</b></div>');
                alert_float(r.success ? 'success' : 'danger', r.success ? 'Tenant re-created' : r.message);
                if (r.success && r.report) thRender(r.report); else if (r.success) thCheck();
            }).fail(function (xhr) {
                $('#th_steps').html('<div class="text-danger">' + thEsc(xhr.responseText ? xhr.responseText.substring(0, 300) : 'Request failed') + '</div>');
            }).always(function () { $b.prop('disabled', false).html('<i class="fa fa-repeat"></i> Re-create tenant'); });
        };

        $(document).on('click', '#th_repair_admin', function () {
            var $b = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Creating…');
            var data = { company_id: perfex_saas_company_id };
            data[csrfData['token_name']] = csrfData['hash'];
            $.post(admin_url + 'perfex_saas/companies/tenant_repair_admin', data, null, 'json').done(function (r) {
                alert_float(r.success ? 'success' : 'danger', r.message);
                if (r.success && r.report) thRender(r.report);
                else { $('#th_steps').html('<div class="text-danger"><b>' + thEsc(r.message) + '</b></div>'); $b.prop('disabled', false).html('<i class="fa fa-user-plus"></i> Create admin'); }
            }).fail(function (xhr) {
                $('#th_steps').html('<div class="text-danger">' + thEsc(xhr.responseText ? xhr.responseText.substring(0, 300) : 'Request failed') + '</div>');
                $b.prop('disabled', false).html('<i class="fa fa-user-plus"></i> Create admin');
            });
        });

        $('#th_refresh').on('click', thCheck);
        $('#th_recreate').on('click', function () {
            var msg = 'This installs a NEW, EMPTY instance for this tenant (no old data). Use it only when there is no backup to restore.';
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Verify password to re-create', text: msg, icon: 'warning',
                    input: 'password', inputPlaceholder: 'Verification password',
                    showCancelButton: true, confirmButtonText: 'Re-create', confirmButtonColor: '#dc2626',
                    preConfirm: function (p) { if (!p) { Swal.showValidationMessage('Enter the verification password'); return false; } return p; }
                }).then(function (res) { if (res.isConfirmed) thRun(res.value); });
            } else {
                var p = prompt(msg + '\n\nEnter the verification password:');
                if (p) thRun(p);
            }
        });
        thCheck();
    }

    // Remote Module Activation Logic
    $('.remote-module-toggle').on('change', function() {
        const $toggle = $(this);
        const systemName = $toggle.data('module');
        const isActive = $toggle.is(':checked') ? 1 : 0;
        
        // Disable during request
        $toggle.prop('disabled', true);
        
        $.post(admin_url + 'perfex_saas/companies/update_tenant_module_status', {
            company_id: perfex_saas_company_id,
            module_name: systemName,
            status: isActive,
            [csrfData['token_name']]: csrfData['hash']
        }).done(function(response) {
            response = JSON.parse(response);
            if (response.success) {
                alert_float('success', 'Module status updated remotely');
            } else {
                alert_float('danger', 'Failed to update: ' + (response.error || 'Unknown error'));
                // Revert toggle
                $toggle.prop('checked', !isActive);
            }
        }).fail(function() {
            alert_float('danger', 'Request failed');
            $toggle.prop('checked', !isActive);
        }).always(function() {
            $toggle.prop('disabled', false);
            remoteRefreshBulkCount();
        });
    });

    // Bulk: activate every module in the tenant's package in one remote pass
    var $remoteBulkBtn = $('#remote-bulk-activate-package');
    var remotePkgModules = $remoteBulkBtn.length ? ($remoteBulkBtn.data('modules') || []) : [];

    function remoteRefreshBulkCount() {
        if (!$remoteBulkBtn.length) return;
        var inactive = remotePkgModules.filter(function (m) {
            return !$('#remote_toggle_' + m).is(':checked');
        }).length;
        $('#remote-bulk-inactive-count').text(inactive);
        $remoteBulkBtn.prop('disabled', inactive === 0);
    }

    var $actLog = $('#remote-activation-log');
    var remoteStopRequested = false;

    function actLogLine(text, color) {
        var t = new Date().toLocaleTimeString();
        var $l = $('<div/>').text('[' + t + '] ' + text);
        if (color) $l.css('color', color);
        $actLog.append($l);
        $actLog.scrollTop($actLog[0].scrollHeight);
    }

    function actDebugLines(debug) {
        String(debug || '').split(/<br\s*\/?>/i).map(function (l) {
            // drop PHP stack traces - the error line above them says what failed
            return $('<div/>').html(l).text().replace(/\s*#0 [\s\S]*$/, '').trim();
        }).filter(function (l) {
            return l && !/^Setting up modules for /.test(l);
        }).forEach(function (l) {
            var bad = /error|failed|not found|not assigned|skipping/i.test(l);
            actLogLine('    ' + l, bad ? '#fca5a5' : '#94a3b8');
        });
    }

    function activateOneRemote(m) {
        var payload = { company_id: perfex_saas_company_id, status: 1, 'module_names[]': [m] };
        payload[csrfData['token_name']] = csrfData['hash'];
        return $.ajax({
            url: admin_url + 'perfex_saas/companies/bulk_update_tenant_module_status',
            type: 'POST',
            data: payload,
            timeout: 300000
        }).then(function (raw) {
            var res;
            try { res = typeof raw === 'string' ? JSON.parse(raw) : raw; }
            catch (e) { res = { success: false, error: 'Non-JSON response: ' + String(raw).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').slice(0, 300) }; }
            return res;
        }, function (xhr, textStatus) {
            var body = String((xhr && xhr.responseText) || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300);
            return $.Deferred().resolve({ success: false, error: 'HTTP ' + (xhr ? xhr.status : '?') + ' ' + textStatus + (body ? ' - ' + body : '') }).promise();
        });
    }

    $('#remote-activation-copy').on('click', function () {
        var text = $actLog.children().map(function () { return $(this).text(); }).get().join("\n");
        if (navigator.clipboard) navigator.clipboard.writeText(text).then(function () { alert_float('success', 'Log copied'); });
    });
    $('#remote-activation-stop').on('click', function () {
        remoteStopRequested = true;
        $(this).prop('disabled', true).text('Stopping...');
    });

    $remoteBulkBtn.on('click', function () {
        var pending = remotePkgModules.filter(function (m) {
            return !$('#remote_toggle_' + m).is(':checked');
        });
        if (!pending.length) return;
        if (!confirm('Activate ' + pending.length + ' package module(s) in this tenant, one by one?\n\n' + pending.join(', '))) return;

        var $btnHtml = $remoteBulkBtn.html();
        var total = pending.length, done = 0, ok = [], failed = [];
        remoteStopRequested = false;
        $remoteBulkBtn.prop('disabled', true);
        $('.remote-module-toggle').prop('disabled', true);
        $('#remote-activation-log-wrap').show();
        $('#remote-activation-stop').show().prop('disabled', false).html('<i class="fa fa-stop"></i> Stop after current');
        $actLog.empty();
        actLogLine('Starting activation of ' + total + ' module(s), one at a time.', '#7dd3fc');

        function progress() {
            $('#remote-activation-bar').css('width', Math.round(done / total * 100) + '%');
            $('#remote-activation-progress').text('Activation log - ' + done + ' / ' + total + ' (' + ok.length + ' ok, ' + failed.length + ' failed)');
            $remoteBulkBtn.html('<i class="fa fa-spinner fa-spin"></i> ' + done + ' / ' + total + '...');
        }

        function syncSelects() {
            // Keep the approved/disabled selects in sync so a later form Save doesn't undo this
            var $approved = $('select[name="metadata[admin_approved_modules][]"]');
            var $disabled = $('select[name="metadata[admin_disabled_modules][]"]');
            if ($approved.length) {
                $approved.val(Array.from(new Set(($approved.val() || []).concat(pending))));
                if ($approved.data('selectpicker')) $approved.selectpicker('refresh');
            }
            if ($disabled.length) {
                $disabled.val(($disabled.val() || []).filter(function (m) { return pending.indexOf(m) === -1; }));
                if ($disabled.data('selectpicker')) $disabled.selectpicker('refresh');
            }
        }

        function finish() {
            $('#remote-activation-stop').hide();
            syncSelects();
            var skipped = total - done;
            actLogLine('Finished: ' + ok.length + ' activated, ' + failed.length + ' failed' + (skipped ? ', ' + skipped + ' not attempted (stopped)' : '') + '.', failed.length ? '#fca5a5' : '#86efac');
            if (failed.length) actLogLine('Failed: ' + failed.join(', '), '#fca5a5');
            alert_float(failed.length ? 'warning' : 'success', ok.length + ' of ' + total + ' module(s) activated' + (failed.length ? ' - see the activation log for ' + failed.length + ' failure(s)' : ''));
            $remoteBulkBtn.html($btnHtml);
            $('.remote-module-toggle').prop('disabled', false);
            remoteRefreshBulkCount();
        }

        function next(i) {
            if (i >= total || remoteStopRequested) return finish();
            var m = pending[i];
            actLogLine('(' + (i + 1) + '/' + total + ') ' + m + ': activating...', '#e2e8f0');
            var started = Date.now();
            activateOneRemote(m).then(function (res) {
                var secs = ((Date.now() - started) / 1000).toFixed(1) + 's';
                done++;
                if (res && res.debug) actDebugLines(res.debug);
                var statusMap = (res && res.tenant_module_status) || {};
                Object.keys(statusMap).forEach(function (k) {
                    $('#remote_toggle_' + k).prop('checked', statusMap[k] == '1');
                });
                var reason = res && res.failures && res.failures[m];
                if (res && res.success && statusMap[m] == '1') {
                    ok.push(m);
                    actLogLine('    OK - ' + m + ' active (' + secs + ')', '#86efac');
                } else {
                    failed.push(m);
                    actLogLine('    FAILED - ' + m + ': ' + (reason || (res && res.error) || 'not active after setup') + ' (' + secs + ')', '#f87171');
                }
                progress();
                next(i + 1);
            });
        }

        progress();
        next(0);
    });
});
</script>
</body>

</html>