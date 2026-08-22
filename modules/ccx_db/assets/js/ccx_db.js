$(function () {
    $('#btn_compare').on('click', function () {
        var source_slug = $('#source_slug').val();
        var dest_slug = $('#dest_slug').val();

        if (source_slug === dest_slug) {
            alert('Source and Destination cannot be the same!');
            return;
        }

        $('#compare_results').html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i><br>Comparing... This may take a while.</div>');

        $.post(admin_url + 'ccx_db/compare_db', {
            source_slug: source_slug,
            dest_slug: dest_slug
        }).done(function (response) {
            $('#compare_results').html(response);
        }).fail(function (data) {
            var error = "Unknown Error";
            if (data.responseText) error = data.responseText;
            $('#compare_results').html('<div class="alert alert-danger">Error: ' + error + '</div>');
        });
    });

    // Copy DB Logic
    $('#btn_copy_db').on('click', function () {
        var source_slug = $('#copy_source_slug').val();
        var dest_slug = $('#copy_dest_slug').val();

        if (!dest_slug) {
            alert('Please select a destination database.');
            return;
        }

        if (source_slug === dest_slug) {
            alert('Source and Destination cannot be the same!');
            return;
        }

        if (!confirm('DANGER: Are you sure you want to overwrite the destination database? This cannot be undone!')) {
            return;
        }

        $('#copy_db_result').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Copying...</div>');
        $('#btn_copy_db').prop('disabled', true);

        $.post(admin_url + 'ccx_db/copy_db_action', {
            source_slug: source_slug,
            dest_slug: dest_slug
        }).done(function (response) {
            var res = JSON.parse(response);
            if (res.success) {
                $('#copy_db_result').html('<div class="alert alert-success">' + res.message + '</div>');
            } else {
                $('#copy_db_result').html('<div class="alert alert-danger">' + res.message + '</div>');
            }
        }).fail(function (data) {
            $('#copy_db_result').html('<div class="alert alert-danger">Error copying database.</div>');
        }).always(function () {
            $('#btn_copy_db').prop('disabled', false);
        });
    });


    // Copy Table Logic - Fetch Tables
    $('#copy_table_source_slug').on('change', function () {
        var source_slug = $(this).val();
        var tableSelect = $('#copy_table_name');

        tableSelect.prop('disabled', true).html('<option>Loading...</option>').selectpicker('refresh');

        $.get(admin_url + 'ccx_db/get_tables_json/' + source_slug, function (response) {
            var tables = JSON.parse(response);
            var options = '';
            if (tables.error) {
                options = '<option>Error loading tables</option>';
            } else {
                $.each(tables, function (i, table) {
                    options += '<option value="' + table + '">' + table + '</option>';
                });
            }
            tableSelect.html(options).prop('disabled', false).selectpicker('refresh');
        });
    });

    // Copy Table Action
    $('#btn_copy_table').on('click', function () {
        var source_slug = $('#copy_table_source_slug').val();
        var dest_slug = $('#copy_table_dest_slug').val();
        var table_name = $('#copy_table_name').val();

        if (!source_slug || !dest_slug || !table_name) {
            alert('Please select all fields.');
            return;
        }

        if (source_slug === dest_slug) {
            alert('Source and Destination database cannot be the same!');
            return;
        }

        if (!confirm('Are you sure you want to copy table ' + table_name + ' to destination? If it exists, it will be overwritten.')) {
            return;
        }

        $('#copy_table_result').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Copying...</div>');
        $('#btn_copy_table').prop('disabled', true);

        $.post(admin_url + 'ccx_db/copy_table_action', {
            source_slug: source_slug,
            dest_slug: dest_slug,
            table_name: table_name
        }).done(function (response) {
            var res = JSON.parse(response);
            if (res.success) {
                $('#copy_table_result').html('<div class="alert alert-success">' + res.message + '</div>');
            } else {
                $('#copy_table_result').html('<div class="alert alert-danger">' + res.message + '</div>');
            }
        }).fail(function (data) {
            $('#copy_table_result').html('<div class="alert alert-danger">Error copying table.</div>');
        }).always(function () {
            $('#btn_copy_table').prop('disabled', false);
        });
    });

    // Backup Download
    $('#btn_backup_download').on('click', function () {
        var slug = $('#backup_slug').val();
        if (!slug) return;

        window.location.href = admin_url + 'ccx_db/backup/' + slug;
    });

    // Restore Action
    $('#form_restore').on('submit', function (e) {
        e.preventDefault();
        var dest_slug = $('#restore_dest_slug').val();
        if (!dest_slug) {
            alert('Please select a target database');
            return;
        }

        if (!confirm('CRITICAL WARNING: This will OVERWRITE the target database (' + dest_slug + '). Are you absolutely sure?')) {
            return;
        }

        var formData = new FormData(this);

        // Add CSRF token
        if (typeof (csrfData) !== 'undefined') {
            formData.append(csrfData.token_name, csrfData.hash);
        }

        $('#btn_restore').prop('disabled', true);
        $('#restore_progress').show();
        $('#restore_result').html('');

        $.ajax({
            url: admin_url + 'ccx_db/restore_action',
            type: 'POST',
            data: formData,
            success: function (response) {
                var res = JSON.parse(response);
                if (res.success) {
                    $('#restore_result').html('<div class="alert alert-success">' + res.message + '</div>');
                    $('#form_restore')[0].reset();
                    $('#restore_dest_slug').selectpicker('refresh');
                } else {
                    $('#restore_result').html('<div class="alert alert-danger">' + res.message + '</div>');
                }
            },
            error: function (data) {
                $('#restore_result').html('<div class="alert alert-danger">Restore failed. Server error.</div>');
            },
            cache: false,
            contentType: false,
            processData: false,
            complete: function () {
                $('#btn_restore').prop('disabled', false);
                $('#restore_progress').hide();
            }
        });
    });


    // Modules Tab Logic
    $('#module_tenant_slug').on('change', function () {
        var slug = $(this).val();
        var container = $('#modules_list_container');

        if (!slug) return;

        container.html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i> Loading...</div>');

        $.get(admin_url + 'ccx_db/get_tenant_modules_json/' + slug, function (response) {
            console.log('Server response:', response); // Debugging
            try {
                // Check if response is already an object
                var modules = (typeof response === 'object') ? response : JSON.parse(response);
            } catch (e) {
                console.error('JSON Parse Error:', e, response);
                container.html('<div class="alert alert-danger">Invalid server response</div>');
                return;
            }

            if (modules.error) {
                container.html('<div class="alert alert-danger">' + modules.error + '</div>');
                return;
            }

            if (modules.length === 0) {
                container.html('<div class="alert alert-warning">No modules found in this tenant database.</div>');
                return;
            }

            var html = '<div class="table-responsive"><table class="table table-striped dt-table">';
            html += '<thead><tr><th>Module Name</th><th>Version</th><th>Status</th><th>Action</th></tr></thead>';
            html += '<tbody>';

            $.each(modules, function (i, module) {
                var is_active = module.active == 1;
                var status_label = is_active ? '<span class="label label-success">Active</span>' : '<span class="label label-danger">Inactive</span>';
                var btn_class = is_active ? 'btn-default' : 'btn-success';
                var btn_text = is_active ? 'Deactivate' : 'Activate';
                var action_val = is_active ? 0 : 1;

                html += '<tr>';
                html += '<td>' + module.module_name + '</td>';
                html += '<td>' + module.installed_version + '</td>';
                html += '<td>' + status_label + '</td>';
                html += '<td>';
                html += '<button class="btn ' + btn_class + ' btn-xs btn-toggle-module" data-id="' + module.id + '" data-active="' + action_val + '">' + btn_text + '</button>';
                html += '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';

            container.html(html);

            // Re-init DataTable if needed
            if ($.fn.DataTable.isDataTable('#modules_list_container table')) {
                $('#modules_list_container table').DataTable().destroy();
            }
            // Initialize DataTable if function exists, otherwise ignore
            if (typeof initDataTableOffline === 'function') {
                initDataTableOffline('#modules_list_container table');
            }

        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.error('AJAX request failed:', textStatus, errorThrown);
            container.html('<div class="alert alert-danger">Failed to fetch modules. Error: ' + textStatus + '</div>');
        });
    });

    $(document).on('click', '.btn-toggle-module', function () {
        var btn = $(this);
        var id = btn.data('id');
        var active = btn.data('active');
        var slug = $('#module_tenant_slug').val();

        btn.prop('disabled', true).text('Processing...');

        $.post(admin_url + 'ccx_db/update_tenant_module_action', {
            slug: slug,
            id: id,
            active: active
        }, function (response) {
            btn.prop('disabled', false);
            try {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                if (res.success) {
                    alert_float('success', res.message);
                    // Refresh list
                    $('#module_tenant_slug').trigger('change');
                } else {
                    alert_float('danger', res.message);
                    // Reset button text
                    if (active == 1) btn.text('Activate'); else btn.text('Deactivate');
                }
            } catch (e) {
                console.error('JSON Parse Error:', e, response);
                alert_float('danger', 'Invalid server response');
            }
        }).fail(function () {
            btn.prop('disabled', false);
            alert_float('danger', 'Request failed');
        });
    });

    // Module Data Export/Import Logic
    $('#btn_export_module_data').on('click', function () {
        var slug = $('#module_data_tenant_slug').val();
        var module_name = $('#module_data_module_name').val();

        if (!slug || slug.length === 0 || !module_name || module_name.length === 0) {
            alert_float('warning', 'Please select a Tenant and a Module.');
            return;
        }

        if ($.isArray(slug) && slug.length > 1) {
            alert_float('warning', 'Please select only ONE tenant for export.');
            return;
        }

        if ($.isArray(module_name) && module_name.length > 1) {
            alert_float('warning', 'Please select only ONE module for export.');
            return;
        }

        var export_slug = $.isArray(slug) ? slug[0] : slug;
        var export_module_name = $.isArray(module_name) ? module_name[0] : module_name;
        
        $('#export_slug').val(export_slug);
        $('#export_module_name').val(export_module_name);
        $('#form_export_module_data').submit();
    });

    $('#btn_import_module_data_trigger').on('click', function () {
        var slug = $('#module_data_tenant_slug').val();
        if (!slug || slug.length === 0) {
            alert_float('warning', 'Please select a Tenant to import data into.');
            return;
        }
        if ($.isArray(slug) && slug.length > 1) {
            alert_float('warning', 'Please select only ONE tenant for import.');
            return;
        }
        var module_name = $('#module_data_module_name').val();
        if (!module_name || module_name.length === 0) {
            alert_float('warning', 'Please select a Module.');
            return;
        }
        if ($.isArray(module_name) && module_name.length > 1) {
            alert_float('warning', 'Please select only ONE module for import.');
            return;
        }
        $('#module_data_import_file').click();
    });

    $('#module_data_import_file').on('change', function () {
        var file = this.files[0];
        if (!file) return;

        var slug = $('#module_data_tenant_slug').val();
        var import_slug = $.isArray(slug) ? slug[0] : slug;
        var module_names = $('#module_data_module_name').val();
        var module_name = $.isArray(module_names) ? module_names[0] : module_names;
        var fd = new FormData();
        fd.append('backup_file', file);
        fd.append('dest_slug', import_slug);
        fd.append('module_name', module_name); // Send it

        // Append CSRF Token
        if (typeof (csrfData) !== 'undefined') {
            fd.append(csrfData.token_name, csrfData.hash);
        }

        var btn = $('#btn_import_module_data_trigger');
        var original_text = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');
        $('#module_data_result').html('<div class="alert alert-info">Importing data... please wait.</div>');

        $.ajax({
            url: admin_url + 'ccx_db/import_module_action',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function (response) {
                btn.prop('disabled', false).html(original_text);
                try {
                    var res = JSON.parse(response);
                    if (res.success) {
                        alert_float('success', res.message);
                        $('#module_data_result').html('<div class="alert alert-success">' + res.message + '</div>');
                    } else {
                        alert_float('danger', res.message);
                        $('#module_data_result').html('<div class="alert alert-danger">' + res.message + '</div>');
                    }
                } catch (e) {
                    $('#module_data_result').html('<div class="alert alert-danger">Server Error</div>');
                }
                // Reset file input
                $('#module_data_import_file').val('');
            },
            error: function (jqXHR, textStatus, errorThrown) {
                btn.prop('disabled', false).html(original_text);
                var msg = 'Request failed: ' + errorThrown;
                if (jqXHR.responseText) {
                    msg = jqXHR.responseText;
                }
                alert_float('danger', msg);
                $('#module_data_result').html('<div class="alert alert-danger">' + msg + '</div>');
                $('#module_data_import_file').val('');
            }
        });
    });



    // Re-install Module Logic
    $('#btn_reinstall_module').on('click', function () {
        var slug = $('#module_data_tenant_slug').val();
        var module_names = $('#module_data_module_name').val();

        if (!slug || slug.length === 0 || !module_names || module_names.length === 0) {
            alert_float('warning', 'Please select at least one Tenant and a Module.');
            return;
        }

        var slugText = $.isArray(slug) ? slug.length + ' tenants' : slug;
        var moduleText = $.isArray(module_names) ? module_names.length + ' modules' : module_names;
        if (!confirm('Are you sure you want to re-install ' + moduleText + ' for ' + slugText + '? This will execute the install.php script.')) {
            return;
        }

        var btn = $(this);
        var original_text = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
        
        var modulesArray = $.isArray(module_names) ? module_names : [module_names];
        var currentIndex = 0;
        var totalModules = modulesArray.length;
        
        $('#module_data_result').html('<div class="alert alert-info">Re-installing modules (0/' + totalModules + ')... please wait.</div>');

        function processNextModule() {
            if (currentIndex >= totalModules) {
                btn.prop('disabled', false).html(original_text);
                $('#module_data_result').append('<div class="alert alert-success">All selected modules have been processed.</div>');
                return;
            }

            var currentModule = modulesArray[currentIndex];
            $('#module_data_result').append('<div class="alert alert-info" id="reinstall_status_' + currentIndex + '">Re-installing ' + currentModule + '...</div>');

            $.post(admin_url + 'ccx_db/reinstall_module_action', {
                slugs: slug,
                module_name: currentModule
            }).done(function (response) {
                try {
                    var res = JSON.parse(response);
                    var msg = '';
                    if (res.success) {
                        alert_float('success', currentModule + ': ' + res.message);
                        msg = '<div class="alert alert-success"><strong>' + currentModule + ':</strong> ' + res.message + '</div>';
                    } else {
                        alert_float('danger', currentModule + ': ' + res.message);
                        msg = '<div class="alert alert-danger"><strong>' + currentModule + ':</strong> ' + res.message + '</div>';
                    }
                    if (res.debug) {
                        msg += '<div class="alert alert-warning">Debug Info: <pre>' + JSON.stringify(res.debug, null, 2) + '</pre></div>';
                    }
                    $('#reinstall_status_' + currentIndex).replaceWith(msg);
                } catch (e) {
                    $('#reinstall_status_' + currentIndex).replaceWith('<div class="alert alert-danger"><strong>' + currentModule + ':</strong> Server Error</div>');
                }
            }).fail(function () {
                alert_float('danger', currentModule + ': Request failed');
                $('#reinstall_status_' + currentIndex).replaceWith('<div class="alert alert-danger"><strong>' + currentModule + ':</strong> Request failed</div>');
            }).always(function() {
                currentIndex++;
                processNextModule();
            });
        }

        // Start processing
        $('#module_data_result').empty(); // Clear old results
        processNextModule();
    });

    // =========================================================================
    // AUTO TENANTS DATA BACKUP
    // =========================================================================

    // Show/hide FAB when auto_backup tab is active
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var target = $(e.target).attr('href');
        if (target === '#auto_backup') {
            $('#fab_backup_settings').fadeIn(300);
            loadAutoBackupTenants();
        } else {
            $('#fab_backup_settings').fadeOut(200);
        }
    });

    // ---- Debug Console Helpers ----
    function consoleShow() {
        $('#backup_console_wrapper').slideDown(200);
    }
    function consoleAppend(text, color) {
        var el = $('#backup_console');
        var line = document.createElement('div');
        line.textContent = text;
        if (color) line.style.color = color;
        el.append(line);
        el.scrollTop(el[0].scrollHeight);
    }
    function consoleClear() {
        $('#backup_console').html('<span style="color:#888">Console cleared. Waiting for backup operation...</span>');
    }
    function consoleLogSteps(steps) {
        if (!steps || !steps.length) return;
        for (var i = 0; i < steps.length; i++) {
            var line = steps[i];
            var color = '#00ff41';
            if (line.indexOf('ERROR') !== -1 || line.indexOf('EXCEPTION') !== -1 || line.indexOf('FAILED') !== -1) {
                color = '#ff4444';
            } else if (line.indexOf('SUCCESS') !== -1) {
                color = '#44ff44';
            } else if (line.indexOf('START') !== -1) {
                color = '#44aaff';
            }
            consoleAppend(line, color);
        }
    }
    $('#btn_clear_console').on('click', function() { consoleClear(); });

    // Load tenants list for auto backup
    function loadAutoBackupTenants() {
        var container = $('#auto_backup_list');
        container.html('<div class="text-center mtop15"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading tenants...</div>');

        $.get(admin_url + 'ccx_db/get_auto_backup_tenants', function (response) {
            try {
                var data = (typeof response === 'object') ? response : JSON.parse(response);
            } catch (e) {
                container.html('<div class="alert alert-danger">Invalid server response</div>');
                return;
            }

            if (data.error) {
                container.html('<div class="alert alert-danger">' + data.error + '</div>');
                return;
            }

            var tenants = data.tenants || [];
            var incrCronSchedule = data.incr_cron_schedule || '* * * * *';
            var fullCronSchedule = data.full_cron_schedule || '0 2 * * *';
            var serverTime = data.server_time ? new Date(data.server_time.replace(/-/g, '/')) : new Date();

            if (tenants.length === 0) {
                container.html('<div class="alert alert-warning">No tenants found.</div>');
                return;
            }

            var html = '<div class="mbot15" style="display:flex; justify-content:space-between; align-items:center;">';
            html += '<div><button type="button" class="btn btn-success" id="btn_run_all_backups"><i class="fa fa-play-circle"></i> Run All Backups Now</button></div>';
            html += '<div><button type="button" class="btn btn-warning" id="btn_diagnose_cron"><i class="fa fa-wrench"></i> Diagnose / Setup Cron Job</button></div>';
            html += '</div>';
            html += '<div class="table-responsive"><table class="table table-striped table-bordered">';
            html += '<thead><tr>';
            html += '<th style="width:50px">#</th>';
            html += '<th>Tenant Name</th>';
            html += '<th>Slug</th>';
            html += '<th style="text-align:center">Last Backup</th>';
            html += '<th style="text-align:center">Next Incremental</th>';
            html += '<th style="text-align:center">Next Full</th>';
            html += '<th style="width:180px; text-align:center">Auto Backup</th>';
            html += '<th style="width:120px; text-align:center">Actions</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            $.each(tenants, function (i, tenant) {
                var checked = tenant.auto_backup_enabled == 1 ? 'checked' : '';
                var statusLabel = tenant.auto_backup_enabled == 1
                    ? '<span class="label label-success">Enabled</span>'
                    : '<span class="label label-danger">Disabled</span>';

                // Last Backup column
                var lastBackupHtml = '<span class="text-muted">Never</span>';
                if (tenant.last_backup_at) {
                    var lastDate = new Date(tenant.last_backup_at.replace(/-/g, '/'));
                    var agoText = timeAgo(lastDate, serverTime);
                    var formattedDate = formatDateTime(lastDate);
                    lastBackupHtml = '<span title="' + formattedDate + '">' + agoText + '</span><br><small class="text-muted">' + formattedDate + '</small>';
                }

                // Next Incremental column
                var nextIncrHtml = '<span class="text-muted">N/A</span>';
                if (tenant.auto_backup_enabled == 1) {
                    var nextIncrRun = getNextCronRun(incrCronSchedule, serverTime);
                    if (nextIncrRun) {
                        var timeLeftIncr = timeUntil(nextIncrRun, serverTime);
                        var formattedNextIncr = formatDateTime(nextIncrRun);
                        nextIncrHtml = '<span class="text-success" title="' + formattedNextIncr + '"><i class="fa fa-bolt"></i> ' + timeLeftIncr + '</span><br><small class="text-muted">' + formattedNextIncr + '</small>';
                    }
                } else {
                    nextIncrHtml = '<span class="text-danger">Disabled</span>';
                }

                // Next Full column
                var nextFullHtml = '<span class="text-muted">N/A</span>';
                if (tenant.auto_backup_enabled == 1) {
                    var nextFullRun = getNextCronRun(fullCronSchedule, serverTime);
                    if (nextFullRun) {
                        var timeLeftFull = timeUntil(nextFullRun, serverTime);
                        var formattedNextFull = formatDateTime(nextFullRun);
                        nextFullHtml = '<span class="text-warning" title="' + formattedNextFull + '"><i class="fa fa-database"></i> ' + timeLeftFull + '</span><br><small class="text-muted">' + formattedNextFull + '</small>';
                    }
                } else {
                    nextFullHtml = '<span class="text-danger">Disabled</span>';
                }

                html += '<tr>';
                html += '<td>' + (i + 1) + '</td>';
                html += '<td>' + tenant.name + '</td>';
                html += '<td><code>' + tenant.slug + '</code></td>';
                html += '<td style="text-align:center">' + lastBackupHtml + '</td>';
                html += '<td style="text-align:center">' + nextIncrHtml + '</td>';
                html += '<td style="text-align:center">' + nextFullHtml + '</td>';
                html += '<td style="text-align:center">';
                html += '<div class="onoffswitch" style="display:inline-block">';
                html += '<input type="checkbox" data-slug="' + tenant.slug + '" class="onoffswitch-checkbox auto-backup-toggle" id="ab_toggle_' + i + '" ' + checked + '>';
                html += '<label class="onoffswitch-label" for="ab_toggle_' + i + '"></label>';
                html += '</div>';
                html += ' ' + statusLabel;
                html += '</td>';
                html += '<td style="text-align:center">';
                html += '<button type="button" class="btn btn-xs btn-primary btn-backup-now" data-slug="' + tenant.slug + '" data-name="' + tenant.name + '"><i class="fa fa-cloud-upload"></i> Backup</button>';
                html += '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            container.html(html);

        }).fail(function () {
            container.html('<div class="alert alert-danger">Failed to load tenants.</div>');
        });
    }

    // Single tenant backup
    $(document).on('click', '.btn-backup-now', function () {
        var btn = $(this);
        var slug = btn.data('slug');
        var name = btn.data('name');
        var originalHtml = btn.html();

        if (!confirm('Run backup now for "' + name + '"? This may take a while for large databases.')) return;

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        // Show console and start logging
        consoleShow();
        consoleAppend('\n══════════════════════════════════════', '#888');
        consoleAppend('[' + new Date().toLocaleTimeString() + '] Triggered single backup for: ' + name + ' (' + slug + ')', '#44aaff');
        consoleAppend('Waiting for server response...', '#888');

        var postData = { slug: slug };
        if (typeof(csrfData) !== 'undefined') {
            postData[csrfData.token_name] = csrfData.hash;
        }

        $.post(admin_url + 'ccx_db/run_single_tenant_backup', postData, function (response) {
            btn.prop('disabled', false).html(originalHtml);
            try {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                if (res.success) {
                    alert_float('success', res.message);
                    loadAutoBackupTenants();
                } else {
                    alert_float('danger', res.message);
                }
                // Display step-by-step logs in console
                if (res.steps && res.steps.length > 0) {
                    consoleLogSteps(res.steps);
                } else {
                    consoleAppend('[Response] ' + res.message, res.success ? '#44ff44' : '#ff4444');
                }
            } catch (e) {
                alert_float('danger', 'Invalid server response');
                consoleAppend('[ERROR] Invalid server response: ' + (response || '').substring(0, 500), '#ff4444');
            }
            consoleAppend('══════════════════════════════════════', '#888');
        }).fail(function (jqXHR) {
            btn.prop('disabled', false).html(originalHtml);
            alert_float('danger', 'Request failed');
            consoleAppend('[FAIL] HTTP request failed: ' + jqXHR.status + ' ' + jqXHR.statusText, '#ff4444');
            if (jqXHR.responseText) {
                consoleAppend('[Server Output] ' + jqXHR.responseText.substring(0, 1000), '#ff8800');
            }
            consoleAppend('══════════════════════════════════════', '#888');
        });
    });

    // Diagnose Cron Job
    $(document).on('click', '#btn_diagnose_cron', function() {
        var btn = $(this);
        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Checking...');

        $.get(admin_url + 'ccx_db/cron_diagnostics', function(response) {
            btn.prop('disabled', false).html(originalHtml);
            try {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                if (res.success) {
                    $('#diag_cron_status').html(res.is_healthy ? '<span class="label label-success">Running / Healthy</span>' : '<span class="label label-danger">Not Running / Stale</span>');
                    $('#diag_cron_last_run').text(res.last_perfex_cron);
                    $('#diag_ccx_incr_last_run').text(res.last_ccx_incr_run);
                    $('#diag_ccx_full_last_run').text(res.last_ccx_full_run);
                    $('#diag_ccx_heartbeat').text(res.latest_heartbeat);
                    $('#diag_cron_wget').val(res.wget_command);
                    $('#diag_cron_curl').val(res.curl_command);
                    $('#diag_cron_php').val(res.php_command);
                    $('#cronDiagnosticModal').modal('show');
                } else {
                    alert_float('danger', 'Failed to retrieve diagnostics');
                }
            } catch(e) {
                alert_float('danger', 'Invalid server response');
            }
        }).fail(function() {
            btn.prop('disabled', false).html(originalHtml);
            alert_float('danger', 'Request failed');
        });
    });

    // Run all backups — server-side queue. Each tenant is processed by a background
    // worker that replies instantly and keeps working after the connection closes,
    // so proxy timeouts (Cloudflare 524) cannot kill it. The browser only sends
    // sub-second trigger/poll requests and renders live progress.
    $(document).on('click', '#btn_run_all_backups', function () {
        var btn = $(this);
        var originalHtml = btn.html();

        if (!confirm('Run backup for ALL enabled tenants? Backups run one by one in a server-side queue.')) return;

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Starting queue...');

        consoleShow();
        consoleAppend('\n══════════════════════════════════════', '#888');
        consoleAppend('[' + new Date().toLocaleTimeString() + '] Triggered BULK backup for ALL enabled tenants (server-side queue)', '#44aaff');

        var runFolder = null;
        var printedItems = {};     // queue item id -> steps already logged in console
        var announcedRunning = {}; // queue item id -> "backing up..." line already shown
        var pollFailures = 0;

        function csrfPost(url, data, ok, fail) {
            data = data || {};
            if (typeof(csrfData) !== 'undefined') {
                data[csrfData.token_name] = csrfData.hash;
            }
            $.post(url, data, ok).fail(fail);
        }

        function finish(counts, total) {
            var summary = 'Backup queue completed. Success: ' + (counts.success || 0) + ', Errors: ' + (counts.error || 0) + ((counts.skipped || 0) > 0 ? ', Skipped (deleted tenants): ' + counts.skipped : '') + ', Total: ' + total;
            btn.prop('disabled', false).html(originalHtml);
            alert_float((counts.error || 0) === 0 ? 'success' : 'warning', summary);
            consoleAppend('\n[Summary] ' + summary, (counts.error || 0) === 0 ? '#44ff44' : '#ff8800');
            consoleAppend('══════════════════════════════════════', '#888');
            loadAutoBackupTenants();
        }

        function triggerNext() {
            csrfPost(admin_url + 'ccx_db/process_backup_queue', {}, function (response) {
                var res;
                try { res = (typeof response === 'object') ? response : JSON.parse(response); } catch (e) { res = null; }
                if (res && res.started) {
                    consoleAppend('[' + new Date().toLocaleTimeString() + '] Worker started for: ' + res.started, '#44aaff');
                }
                // busy / done are handled by the status poll
            }, function () {
                consoleAppend('[WARN] Failed to trigger the next queue item — will retry automatically', '#ff8800');
            });
        }

        function schedulePoll() {
            setTimeout(poll, 5000);
        }

        function poll() {
            $.get(admin_url + 'ccx_db/backup_queue_status', { run_folder: runFolder }, function (response) {
                pollFailures = 0;
                var res;
                try {
                    res = (typeof response === 'object') ? response : JSON.parse(response);
                } catch (e) {
                    schedulePoll();
                    return;
                }

                var counts = res.counts || {};
                var doneCount = (counts.success || 0) + (counts.error || 0) + (counts.skipped || 0);

                $.each(res.items || [], function (i, item) {
                    var label = '[' + (i + 1) + '/' + res.total + '] ' + item.slug;
                    if (item.status === 'running' && !announcedRunning[item.id]) {
                        announcedRunning[item.id] = true;
                        consoleAppend('\n--- ' + label + ' — backing up in background... ---', '#aaaaaa');
                    }
                    if (item.status !== 'pending' && item.status !== 'running' && !printedItems[item.id]) {
                        printedItems[item.id] = true;
                        if (!announcedRunning[item.id]) {
                            consoleAppend('\n--- ' + label + ' ---', '#aaaaaa');
                        }
                        if (item.steps && item.steps.length > 0) {
                            consoleLogSteps(item.steps);
                        } else {
                            consoleAppend(item.message || item.status, item.status === 'success' ? '#44ff44' : (item.status === 'skipped' ? '#ff8800' : '#ff4444'));
                        }
                    }
                });

                btn.html('<i class="fa fa-spinner fa-spin"></i> Queue: ' + doneCount + '/' + res.total + ' done...');

                if ((counts.pending || 0) === 0 && (counts.running || 0) === 0) {
                    finish(counts, res.total);
                    return;
                }

                // Nothing running but items pending — kick the next worker
                if ((counts.running || 0) === 0 && (counts.pending || 0) > 0) {
                    triggerNext();
                }

                schedulePoll();
            }).fail(function () {
                pollFailures++;
                if (pollFailures >= 20) {
                    btn.prop('disabled', false).html(originalHtml);
                    consoleAppend('[FAIL] Lost contact with the server while monitoring the queue. The queue keeps running on the server — click "Run All Backups Now" again to resume monitoring it.', '#ff4444');
                    consoleAppend('══════════════════════════════════════', '#888');
                    return;
                }
                schedulePoll();
            });
        }

        csrfPost(admin_url + 'ccx_db/enqueue_all_backups', {}, function (response) {
            var res;
            try { res = (typeof response === 'object') ? response : JSON.parse(response); } catch (e) { res = null; }

            if (!res || !res.success) {
                btn.prop('disabled', false).html(originalHtml);
                var msg = (res && res.message) ? res.message : 'Failed to start backup queue';
                alert_float('warning', msg);
                consoleAppend('[ERROR] ' + msg, '#ff4444');
                consoleAppend('══════════════════════════════════════', '#888');
                return;
            }

            runFolder = res.run_folder;
            consoleAppend((res.resumed ? 'Resuming unfinished queue' : 'Queue created') + ': ' + res.total + ' tenant(s) | Saving to: ' + (res.dest_folder || res.run_folder), '#44aaff');
            consoleAppend('Backups run in background workers on the server — safe from proxy timeouts. If you close this page, the current tenant still finishes; click "Run All Backups Now" again to resume the rest.', '#888');

            triggerNext();
            schedulePoll();
        }, function (jqXHR) {
            btn.prop('disabled', false).html(originalHtml);
            alert_float('danger', 'Failed to start backup queue');
            consoleAppend('[FAIL] HTTP ' + jqXHR.status + ' while creating backup queue', '#ff4444');
            consoleAppend('══════════════════════════════════════', '#888');
        });
    });

    // Helper: relative time ago
    function timeAgo(date, now) {
        var seconds = Math.floor((now - date) / 1000);
        if (seconds < 60) return seconds + ' seconds ago';
        var minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + ' minute' + (minutes !== 1 ? 's' : '') + ' ago';
        var hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + ' hour' + (hours !== 1 ? 's' : '') + ' ago';
        var days = Math.floor(hours / 24);
        return days + ' day' + (days !== 1 ? 's' : '') + ' ago';
    }

    // Helper: time until future date
    function timeUntil(futureDate, now) {
        var seconds = Math.floor((futureDate - now) / 1000);
        if (seconds < 0) return 'Overdue';
        if (seconds < 60) return 'Less than a minute';
        var minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + ' minute' + (minutes !== 1 ? 's' : '');
        var hours = Math.floor(minutes / 60);
        var remMinutes = minutes % 60;
        if (hours < 24) {
            var str = hours + ' hour' + (hours !== 1 ? 's' : '');
            if (remMinutes > 0) str += ' ' + remMinutes + ' min';
            return str;
        }
        var days = Math.floor(hours / 24);
        var remHours = hours % 24;
        var str = days + ' day' + (days !== 1 ? 's' : '');
        if (remHours > 0) str += ' ' + remHours + 'h';
        return str;
    }

    // Helper: format date/time
    function formatDateTime(date) {
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var d = date.getDate();
        var m = months[date.getMonth()];
        var y = date.getFullYear();
        var h = date.getHours();
        var min = date.getMinutes();
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12; if (h === 0) h = 12;
        return d + ' ' + m + ' ' + y + ', ' + h + ':' + (min < 10 ? '0' : '') + min + ' ' + ampm;
    }

    // Helper: compute next cron run from a simple cron expression
    function getNextCronRun(cron, now) {
        var parts = cron.split(/\s+/);
        if (parts.length < 5) return null;

        var minute = parts[0], hour = parts[1], dayOfMonth = parts[2], month = parts[3], dayOfWeek = parts[4];

        // Simple handling for common patterns
        var next = new Date(now.getTime());
        next.setSeconds(0);
        next.setMilliseconds(0);

        // Parse minute (handle */N)
        var cronMin = -1;
        var minuteInterval = 0;
        if (minute === '*') {
            cronMin = -1;
        } else if (minute.indexOf('*/') === 0) {
            minuteInterval = parseInt(minute.substring(2));
        } else {
            cronMin = parseInt(minute);
        }
        // Parse hour (handle */N)
        var cronHour = -1;
        var hourInterval = 0;
        if (hour === '*') {
            cronHour = -1;
        } else if (hour.indexOf('*/') === 0) {
            hourInterval = parseInt(hour.substring(2));
        } else {
            cronHour = parseInt(hour);
        }

        // Every N minutes (e.g. */5 * * * *)
        if (minuteInterval > 0 && cronHour === -1 && hourInterval === 0) {
            var curMin = now.getMinutes();
            var nextMin = Math.ceil((curMin + 1) / minuteInterval) * minuteInterval;
            if (nextMin >= 60) {
                next.setHours(next.getHours() + 1);
                nextMin = 0;
            }
            next.setMinutes(nextMin);
            return next;
        }

        if (hourInterval > 0) {
            // e.g. 0 */6 * * * — every N hours at minute cronMin
            var m = cronMin >= 0 ? cronMin : 0;
            next.setMinutes(m);
            // Find next hour that is a multiple of hourInterval
            var curH = next.getHours();
            var nextH = Math.ceil((curH + (now.getMinutes() > m || (now.getMinutes() === m && now.getSeconds() > 0) ? 1 : 0)) / hourInterval) * hourInterval;
            if (nextH >= 24) {
                next.setDate(next.getDate() + 1);
                nextH = 0;
            }
            next.setHours(nextH);
            return next;
        }

        if (cronMin === -1 && cronHour === -1) {
            // Every minute — next minute
            next.setMinutes(next.getMinutes() + 1);
            return next;
        }

        if (cronHour === -1 && cronMin >= 0) {
            // Every hour at minute X
            if (now.getMinutes() >= cronMin) {
                next.setHours(next.getHours() + 1);
            }
            next.setMinutes(cronMin);
            return next;
        }

        // Specific hour and minute
        var targetMin = cronMin >= 0 ? cronMin : 0;
        var targetHour = cronHour >= 0 ? cronHour : 0;

        // Weekly (dayOfWeek specified, not *)
        if (dayOfWeek !== '*') {
            var dow = parseInt(dayOfWeek);
            next.setHours(targetHour);
            next.setMinutes(targetMin);
            var currentDow = next.getDay();
            var daysAhead = dow - currentDow;
            if (daysAhead < 0 || (daysAhead === 0 && next <= now)) daysAhead += 7;
            next.setDate(next.getDate() + daysAhead);
            if (next <= now) next.setDate(next.getDate() + 7);
            return next;
        }

        // Monthly (dayOfMonth specified, not *)
        if (dayOfMonth !== '*') {
            var dom = parseInt(dayOfMonth);
            next.setDate(dom);
            next.setHours(targetHour);
            next.setMinutes(targetMin);
            if (next <= now) {
                next.setMonth(next.getMonth() + 1);
                next.setDate(dom);
            }
            return next;
        }

        // Daily
        next.setHours(targetHour);
        next.setMinutes(targetMin);
        if (next <= now) {
            next.setDate(next.getDate() + 1);
        }
        return next;
    }


    // Toggle auto backup for a tenant
    $(document).on('change', '.auto-backup-toggle', function () {
        var checkbox = $(this);
        var slug = checkbox.data('slug');
        var enabled = checkbox.is(':checked') ? 1 : 0;

        var postData = {
            slug: slug,
            enabled: enabled
        };
        if (typeof(csrfData) !== 'undefined') {
            postData[csrfData.token_name] = csrfData.hash;
        }

        $.post(admin_url + 'ccx_db/toggle_auto_backup', postData, function (response) {
            try {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                if (res.success) {
                    alert_float('success', res.message);
                    // Refresh the list to update labels
                    loadAutoBackupTenants();
                } else {
                    alert_float('danger', res.message);
                    // Revert toggle
                    checkbox.prop('checked', !checkbox.is(':checked'));
                }
            } catch (e) {
                alert_float('danger', 'Invalid server response');
                checkbox.prop('checked', !checkbox.is(':checked'));
            }
        }).fail(function () {
            alert_float('danger', 'Request failed');
            checkbox.prop('checked', !checkbox.is(':checked'));
        });
    });

    // Backup method switcher in modal
    $('#ccx_backup_method').on('change', function () {
        var methods = $(this).val() || [];
        if (methods.indexOf('ftp') !== -1) {
            $('#ftp_settings_block').show();
        } else {
            $('#ftp_settings_block').hide();
        }
        if (methods.indexOf('google_drive') !== -1) {
            $('#gdrive_settings_block').show();
        } else {
            $('#gdrive_settings_block').hide();
        }
    });

    // ---- Google Drive Setup Guide wizard ----
    var gdriveGuideStep = 1;
    var GDRIVE_GUIDE_TOTAL = 6;

    function gdriveGuideRender() {
        $('#gdriveGuideModal .gdrive-guide-step').hide();
        $('#gdriveGuideModal .gdrive-guide-step[data-step="' + gdriveGuideStep + '"]').show();
        $('#gdriveGuideModal .gdrive-step-dot').each(function () {
            var s = parseInt($(this).data('step'), 10);
            $(this).toggleClass('active', s === gdriveGuideStep).toggleClass('done', s < gdriveGuideStep);
        });
        $('#gdrive_guide_prev').prop('disabled', gdriveGuideStep === 1);
        $('#gdrive_guide_next').toggle(gdriveGuideStep < GDRIVE_GUIDE_TOTAL);
        $('#gdrive_guide_finish').toggle(gdriveGuideStep === GDRIVE_GUIDE_TOTAL);
        $('#gdrive_guide_step_label').text('Step ' + gdriveGuideStep + ' of ' + GDRIVE_GUIDE_TOTAL);
    }

    $('#btn_gdrive_guide').on('click', function () {
        gdriveGuideStep = 1;
        gdriveGuideRender();
        $('#gdriveGuideModal').modal('show');
    });

    // Stack the guide modal above the settings modal (Bootstrap 3 nested modals)
    $('#gdriveGuideModal').on('show.bs.modal', function () {
        $(this).css('z-index', 1060);
        setTimeout(function () {
            $('.modal-backdrop').last().css('z-index', 1055);
        }, 0);
    }).on('hidden.bs.modal', function () {
        // Keep body scroll lock while the settings modal underneath is still open
        if ($('.modal:visible').length) {
            $('body').addClass('modal-open');
        }
    });

    $('#gdrive_guide_prev').on('click', function () {
        if (gdriveGuideStep > 1) { gdriveGuideStep--; gdriveGuideRender(); }
    });
    $('#gdrive_guide_next').on('click', function () {
        if (gdriveGuideStep < GDRIVE_GUIDE_TOTAL) { gdriveGuideStep++; gdriveGuideRender(); }
    });
    $('#gdrive_guide_finish').on('click', function () {
        $('#gdriveGuideModal').modal('hide');
        setTimeout(function () {
            $('#ccx_backup_gdrive_client_id').trigger('focus');
        }, 300);
    });
    $('#gdriveGuideModal').on('click', '.gdrive-step-dot', function () {
        gdriveGuideStep = parseInt($(this).data('step'), 10);
        gdriveGuideRender();
    });

    $('#gdriveGuideModal').on('click', '.gdrive-copy-btn', function () {
        var btn = $(this);
        var input = btn.closest('.input-group').find('input');
        var showCopied = function () {
            var orig = btn.html();
            btn.html('<i class="fa fa-check"></i> Copied!');
            setTimeout(function () { btn.html(orig); }, 1500);
        };
        var fallbackCopy = function () {
            input.trigger('select');
            input[0].setSelectionRange(0, 9999);
            document.execCommand('copy');
            showCopied();
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.val()).then(showCopied, fallbackCopy);
        } else {
            fallbackCopy();
        }
    });

    // Cron frequency preset mappings for both schedules
    var incrCronPresets = {
        'every_minute': '* * * * *',
        'every_5_minutes': '*/5 * * * *',
        'every_15_minutes': '*/15 * * * *',
        'every_30_minutes': '*/30 * * * *',
        'hourly': '0 * * * *',
        'every_6_hours': '0 */6 * * *',
        'every_12_hours': '0 */12 * * *',
        'daily': '0 2 * * *'
    };

    var fullCronPresets = {
        'hourly': '0 * * * *',
        'every_6_hours': '0 */6 * * *',
        'every_12_hours': '0 */12 * * *',
        'daily': '0 2 * * *',
        'weekly': '0 2 * * 0',
        'monthly': '0 2 1 * *'
    };

    $('#ccx_backup_incr_cron_frequency').on('change', function () {
        var freq = $(this).val();
        if (incrCronPresets[freq]) {
            $('#ccx_backup_incr_cron_schedule').val(incrCronPresets[freq]).prop('readonly', true);
        } else {
            $('#ccx_backup_incr_cron_schedule').prop('readonly', false);
        }
    });

    $('#ccx_backup_full_cron_frequency').on('change', function () {
        var freq = $(this).val();
        if (fullCronPresets[freq]) {
            $('#ccx_backup_full_cron_schedule').val(fullCronPresets[freq]).prop('readonly', true);
        } else {
            $('#ccx_backup_full_cron_schedule').prop('readonly', false);
        }
    });

    // Load settings when modal opens
    $('#backupSettingsModal').on('show.bs.modal', function () {
        $.get(admin_url + 'ccx_db/get_backup_settings', function (response) {
            try {
                var settings = (typeof response === 'object') ? response : JSON.parse(response);
            } catch (e) {
                return;
            }

            // Populate fields
            $.each(settings, function (key, value) {
                var el = $('#' + key);
                if (el.length) {
                    if (el.is(':checkbox')) {
                        el.prop('checked', value == '1');
                    } else if (key === 'ccx_backup_method') {
                        try {
                            var methodsArr = JSON.parse(value);
                            el.val(methodsArr);
                        } catch(e) {
                            el.val([value]);
                        }
                        if (el.hasClass('selectpicker')) {
                            el.selectpicker('refresh');
                        }
                        el.trigger('change');
                    } else {
                        el.val(value);
                        if (el.is('select') && key !== 'ccx_backup_method') {
                            el.trigger('change');
                        }
                    }
                }
            });

            // Secret fields are write-only: the server never sends the saved
            // values, only *_set flags. Blank the inputs and show a hint.
            ['ccx_backup_ftp_password', 'ccx_backup_gdrive_client_secret', 'ccx_backup_gdrive_refresh_token'].forEach(function (field) {
                var el = $('#' + field);
                if (!el.length) return;
                el.val('');
                var isSet = !!settings[field + '_set'];
                el.attr('placeholder', isSet ? '••••••••••••' : el.data('placeholder') || el.attr('placeholder'));
                el.closest('.form-group').find('.ccx-secret-hint').toggle(isSet);
            });

            // Set readonly based on incremental frequency
            var incrFreq = settings.ccx_backup_incr_cron_frequency || 'every_minute';
            if (incrCronPresets[incrFreq]) {
                $('#ccx_backup_incr_cron_schedule').prop('readonly', true);
            } else {
                $('#ccx_backup_incr_cron_schedule').prop('readonly', false);
            }

            // Set readonly based on full frequency
            var fullFreq = settings.ccx_backup_full_cron_frequency || 'daily';
            if (fullCronPresets[fullFreq]) {
                $('#ccx_backup_full_cron_schedule').prop('readonly', true);
            } else {
                $('#ccx_backup_full_cron_schedule').prop('readonly', false);
            }
        });
    });

    // Save backup settings
    $('#btn_save_backup_settings').on('click', function () {
        var btn = $(this);
        var originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        var formData = $('#form_backup_settings').serialize();
        if (typeof(csrfData) !== 'undefined') {
            formData += '&' + csrfData.token_name + '=' + csrfData.hash;
        }

        $.post(admin_url + 'ccx_db/save_backup_settings', formData, function (response) {
            btn.prop('disabled', false).html(originalText);
            try {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                if (res.success) {
                    alert_float('success', res.message);
                    $('#backupSettingsModal').modal('hide');
                } else {
                    alert_float('danger', res.message);
                }
            } catch (e) {
                alert_float('danger', 'Invalid server response');
            }
        }).fail(function () {
            btn.prop('disabled', false).html(originalText);
            alert_float('danger', 'Request failed');
        });
    });

    // =========================================================================
    // TEST CONNECTION
    // =========================================================================
    $('#btn_test_connection').on('click', function () {
        var btn = $(this);
        var originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Testing...');
        $('#test_connection_result').html('<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Testing connection... uploading test file, please wait...</div>');

        var methods = $('#ccx_backup_method').val() || [];
        var postData = { method: methods };

        if (methods.indexOf('ftp') !== -1) {
            postData.ftp_host = $('#ccx_backup_ftp_host').val();
            postData.ftp_port = $('#ccx_backup_ftp_port').val();
            postData.ftp_username = $('#ccx_backup_ftp_username').val();
            postData.ftp_password = $('#ccx_backup_ftp_password').val();
            postData.ftp_path = $('#ccx_backup_ftp_path').val();
        }
        
        if (methods.indexOf('google_drive') !== -1) {
            postData.gdrive_client_id = $('#ccx_backup_gdrive_client_id').val();
            postData.gdrive_client_secret = $('#ccx_backup_gdrive_client_secret').val();
            postData.gdrive_refresh_token = $('#ccx_backup_gdrive_refresh_token').val();
            postData.gdrive_folder_id = $('#ccx_backup_gdrive_folder_id').val();
        }

        if (typeof(csrfData) !== 'undefined') {
            postData[csrfData.token_name] = csrfData.hash;
        }

        $.post(admin_url + 'ccx_db/test_backup_connection', postData, function (response) {
            btn.prop('disabled', false).html(originalText);
            try {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                if (res.success) {
                    $('#test_connection_result').html('<div class="alert alert-success"><i class="fa fa-check-circle"></i> ' + res.message + '</div>');
                } else {
                    $('#test_connection_result').html('<div class="alert alert-danger"><i class="fa fa-times-circle"></i> ' + res.message + '</div>');
                }
            } catch (e) {
                $('#test_connection_result').html('<div class="alert alert-danger">Invalid server response</div>');
            }
        }).fail(function () {
            btn.prop('disabled', false).html(originalText);
            $('#test_connection_result').html('<div class="alert alert-danger">Request failed. Check server connectivity.</div>');
        });
    });

    // =========================================================================
    // BACKUP LOGS
    // =========================================================================
    function loadBackupLogs() {
        var container = $('#backup_logs_container');
        var actionFilter = $('#logs_filter_action').val();
        container.html('<div class="text-center mtop15"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading logs...</div>');

        var url = admin_url + 'ccx_db/get_backup_logs?limit=100';
        if (actionFilter) {
            url += '&action=' + actionFilter;
        }

        $.get(url, function (response) {
            try {
                var logs = (typeof response === 'object') ? response : JSON.parse(response);
            } catch (e) {
                container.html('<div class="alert alert-danger">Invalid server response</div>');
                return;
            }

            if (logs.length === 0) {
                container.html('<div class="alert alert-info">No logs found.</div>');
                return;
            }

            var html = '<div class="table-responsive"><table class="table table-striped table-bordered table-condensed">';
            html += '<thead><tr>';
            html += '<th style="width:50px">#</th>';
            html += '<th>Date & Time</th>';
            html += '<th>Action</th>';
            html += '<th>Method</th>';
            html += '<th>Tenant</th>';
            html += '<th>Status</th>';
            html += '<th>Message</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            $.each(logs, function (i, log) {
                var statusBadge = log.status === 'success'
                    ? '<span class="label label-success">Success</span>'
                    : log.status === 'skipped'
                        ? '<span class="label label-warning">Skipped</span>'
                        : '<span class="label label-danger">Error</span>';

                var actionLabel = log.action === 'test_connection' ? '<span class="label label-info">Test Connection</span>'
                    : log.action === 'auto_backup' ? '<span class="label label-primary">Auto Backup</span>'
                    : log.action === 'cron_heartbeat' ? '<span class="label label-warning">Cron Heartbeat</span>'
                    : '<span class="label label-default">' + log.action + '</span>';

                var methodLabel = log.method === 'ftp' ? '<i class="fa fa-server"></i> FTP'
                    : log.method === 'google_drive' ? '<i class="fa fa-google"></i> Google Drive'
                    : (log.method || '-');

                var tenantDisplay = log.tenant_slug ? '<code>' + log.tenant_slug + '</code>' : '<span class="text-muted">-</span>';

                var dateDisplay = '-';
                if (log.created_at) {
                    var d = new Date(log.created_at.replace(/-/g, '/'));
                    dateDisplay = formatDateTime(d);
                }

                var msgTruncated = log.message || '-';
                if (msgTruncated.length > 80) {
                    msgTruncated = '<span title="' + msgTruncated.replace(/"/g, '&quot;') + '">' + msgTruncated.substring(0, 80) + '...</span>';
                }

                html += '<tr>';
                html += '<td>' + (i + 1) + '</td>';
                html += '<td style="white-space:nowrap">' + dateDisplay + '</td>';
                html += '<td>' + actionLabel + '</td>';
                html += '<td>' + methodLabel + '</td>';
                html += '<td>' + tenantDisplay + '</td>';
                html += '<td>' + statusBadge + '</td>';
                html += '<td style="font-size:12px">' + msgTruncated + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            container.html(html);

        }).fail(function () {
            container.html('<div class="alert alert-danger">Failed to load logs.</div>');
        });
    }

    // Load logs when modal opens
    $('#backupLogsModal').on('show.bs.modal', function () {
        loadBackupLogs();
    });

    // Refresh logs
    $('#btn_refresh_logs').on('click', function () {
        loadBackupLogs();
    });

    // Filter change
    $('#logs_filter_action').on('change', function () {
        loadBackupLogs();
    });

    // =====================================================================
    // Session cleanup (master + every tenant)
    // =====================================================================

    function ccxBytes(n) {
        n = parseFloat(n) || 0;
        if (n <= 0) return '0 B';
        var units = ['B', 'KB', 'MB', 'GB', 'TB'];
        var i = Math.min(Math.floor(Math.log(n) / Math.log(1024)), units.length - 1);
        return (n / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
    }

    function ccxSessionsPost(url, data, done) {
        if (typeof (csrfData) !== 'undefined') {
            data[csrfData.token_name] = csrfData.hash;
        }
        return $.post(admin_url + url, data, function (response) {
            try {
                done((typeof response === 'object') ? response : JSON.parse(response));
            } catch (e) {
                alert_float('danger', 'Invalid server response');
            }
        }).fail(function () {
            alert_float('danger', 'Request failed — the operation may still be running on the server.');
        });
    }

    function loadSessionsOverview() {
        var wrap = $('#sessions_overview_wrap');
        wrap.html('<div class="text-center" style="padding:20px;"><i class="fa fa-spinner fa-spin fa-2x"></i>' +
            '<p class="text-muted">Scanning sessions in every database…</p></div>');

        ccxSessionsPost('ccx_db/get_sessions_overview', { idle_hours: $('#sessions_idle_hours').val() }, function (res) {
            if (!res.success) {
                wrap.html('<div class="alert alert-danger">' + (res.message || 'Scan failed') + '</div>');
                return;
            }

            var html = '<div class="table-responsive"><table class="table table-bordered table-condensed" style="font-size:12.5px;">' +
                '<thead><tr><th>Database</th><th class="text-right">Live rows</th>' +
                '<th class="text-right">Expired (&gt;' + res.idle_hours + 'h)</th>' +
                '<th class="text-right">On disk</th><th>Notes</th></tr></thead><tbody>';

            $.each(res.rows, function (i, r) {
                var note = r.error ? '<span class="text-danger">' + r.error + '</span>'
                    : (!r.exists ? '<span class="text-muted">no sessions table</span>' : '');
                var bloat = (r.rows > 0 && r.size / r.rows > 4096)
                    ? ' <span class="label label-danger">bloated</span>' : '';
                html += '<tr>' +
                    '<td>' + (r.slug === 'master' ? '<span class="label label-info">MASTER</span> ' : '') + r.label + '</td>' +
                    '<td class="text-right">' + Number(r.rows).toLocaleString() + '</td>' +
                    '<td class="text-right">' + Number(r.expired).toLocaleString() + '</td>' +
                    '<td class="text-right">' + ccxBytes(r.size) + bloat + '</td>' +
                    '<td>' + note + '</td></tr>';
            });

            html += '</tbody><tfoot><tr style="font-weight:bold;">' +
                '<td>Total</td>' +
                '<td class="text-right">' + Number(res.total_rows).toLocaleString() + '</td>' +
                '<td class="text-right">' + Number(res.total_expired).toLocaleString() + '</td>' +
                '<td class="text-right">' + ccxBytes(res.total_size) + '</td><td></td>' +
                '</tr></tfoot></table></div>';

            wrap.html(html);
        });
    }

    function runSessionClear(mode) {
        var confirmMsg = (mode === 'all')
            ? 'Clear ALL sessions on the master AND every tenant?\n\nEvery logged-in user everywhere will be signed out immediately. Your own session will be kept.'
            : 'Delete sessions idle for more than ' + $('#sessions_idle_hours').val() + ' hours across the master and every tenant?\n\nUsers who are actively working are not affected.';
        if (!confirm(confirmMsg)) return;

        var buttons = $('#btn_sessions_clear_all, #btn_sessions_clear_expired, #btn_sessions_refresh');
        buttons.prop('disabled', true);
        $('#sessions_result_wrap').show().html(
            '<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Clearing and rebuilding session tables… ' +
            'this can take a minute on large tables — do not close this window.</div>');

        ccxSessionsPost('ccx_db/clear_sessions', {
            mode: mode,
            idle_hours: $('#sessions_idle_hours').val(),
            optimize: '1'
        }, function (res) {
            buttons.prop('disabled', false);

            if (!res.success) {
                $('#sessions_result_wrap').html('<div class="alert alert-danger">' + (res.message || 'Clear failed') + '</div>');
                return;
            }

            var html = '<div class="alert alert-success"><strong>Done.</strong> ' +
                Number(res.total_deleted).toLocaleString() + ' session rows removed, ' +
                ccxBytes(res.total_reclaimed) + ' of disk space reclaimed.</div>';

            html += '<div class="table-responsive"><table class="table table-bordered table-condensed" style="font-size:12.5px;">' +
                '<thead><tr><th>Database</th><th class="text-right">Deleted</th><th class="text-right">Before</th>' +
                '<th class="text-right">After</th><th class="text-right">Reclaimed</th><th>Status</th></tr></thead><tbody>';

            $.each(res.results, function (i, r) {
                var label = r.status === 'ok' ? '<span class="label label-success">ok</span>'
                    : (r.status === 'skipped' ? '<span class="label label-default">skipped</span>'
                        : '<span class="label label-danger">error</span>');
                html += '<tr>' +
                    '<td>' + r.label + '</td>' +
                    '<td class="text-right">' + Number(r.deleted).toLocaleString() + '</td>' +
                    '<td class="text-right">' + ccxBytes(r.size_before) + '</td>' +
                    '<td class="text-right">' + ccxBytes(r.size_after) + '</td>' +
                    '<td class="text-right">' + ccxBytes(r.reclaimed) + '</td>' +
                    '<td>' + label + (r.message ? ' <small class="text-muted">' + r.message + '</small>' : '') + '</td>' +
                    '</tr>';
            });

            html += '</tbody></table></div>';
            $('#sessions_result_wrap').html(html);
            loadSessionsOverview();
        });
    }

    $('#sessionCleanupModal').on('shown.bs.modal', function () {
        $('#sessions_result_wrap').hide().empty();
        loadSessionsOverview();
    });
    $('#btn_sessions_refresh').on('click', loadSessionsOverview);
    $('#sessions_idle_hours').on('change', loadSessionsOverview);
    $('#btn_sessions_clear_expired').on('click', function () { runSessionClear('expired'); });
    $('#btn_sessions_clear_all').on('click', function () { runSessionClear('all'); });

    // Delete all logs
    $('#btn_delete_all_logs').on('click', function () {
        if (!confirm('Are you sure you want to delete ALL backup logs? This cannot be undone.')) return;

        var btn = $(this);
        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');

        var postData = {};
        if (typeof(csrfData) !== 'undefined') {
            postData[csrfData.token_name] = csrfData.hash;
        }

        $.post(admin_url + 'ccx_db/delete_all_backup_logs', postData, function (response) {
            btn.prop('disabled', false).html(originalHtml);
            try {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                if (res.success) {
                    alert_float('success', res.message);
                    loadBackupLogs();
                } else {
                    alert_float('danger', res.message);
                }
            } catch (e) {
                alert_float('danger', 'Invalid server response');
            }
        }).fail(function () {
            btn.prop('disabled', false).html(originalHtml);
            alert_float('danger', 'Request failed');
        });
    });

});

