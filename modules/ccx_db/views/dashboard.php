<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo $title; ?>
                            <a href="<?php echo admin_url('ccx_db/server_performance'); ?>" class="btn btn-info pull-right" style="margin-top:-5px;">
                                <i class="fa fa-tachometer"></i> Server Performance
                            </a>
                            <button type="button" class="btn btn-warning pull-right" id="btn_open_sessions_modal"
                                data-toggle="modal" data-target="#sessionCleanupModal"
                                style="margin-top:-5px; margin-right:8px;">
                                <i class="fa fa-eraser"></i> Clear Sessions
                            </button>
                        </h4>
                        <hr class="hr-panel-heading" />

                        <div class="clearfix"></div>

                        <ul class="nav nav-tabs" role="tablist">
                            <li role="presentation" class="active"><a href="#overview" aria-controls="overview"
                                    role="tab" data-toggle="tab">Overview</a></li>
                            <li role="presentation"><a href="#compare" aria-controls="compare" role="tab"
                                    data-toggle="tab">Compare Database</a></li>
                            <li role="presentation"><a href="#copy" aria-controls="copy" role="tab"
                                    data-toggle="tab">Copy Database</a></li>
                            <li role="presentation"><a href="#backup" aria-controls="backup" role="tab"
                                    data-toggle="tab">Backup / Restore</a></li>
                            <li role="presentation"><a href="#modules" aria-controls="modules" role="tab"
                                    data-toggle="tab">Manage Modules</a></li>
                            <li role="presentation"><a href="#module_data" aria-controls="module_data" role="tab"
                                    data-toggle="tab">Module Data</a></li>
                            <li role="presentation"><a href="#auto_backup" aria-controls="auto_backup" role="tab"
                                    data-toggle="tab"><i class="fa fa-cloud-upload"></i> Auto Tenants Data Backup</a></li>
                        </ul>

                        <div class="tab-content">
                            <div role="tabpanel" class="tab-pane active" id="overview">
                                <div class="table-responsive">
                                    <table class="table dt-table" data-order-col="0" data-order-type="asc">
                                        <thead>
                                            <tr>
                                                <th>Database Name / Tenant</th>
                                                <th>Type</th>
                                                <th>Size (MB)</th>
                                                <th>Table Count</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Master DB -->
                                            <tr>
                                                <td><span class="label label-info">MASTER</span>
                                                    <?php echo $master_db['name']; ?></td>
                                                <td>Master</td>
                                                <td><?php echo $master_db['stats']['size_mb']; ?> MB</td>
                                                <td><?php echo $master_db['stats']['table_count']; ?></td>
                                                <td>-</td>
                                            </tr>

                                            <!-- Tenants -->
                                            <?php foreach ($tenants as $tenant): ?>
                                                <tr>
                                                    <td><?php echo $tenant['name']; ?></td>
                                                    <td>Tenant</td>
                                                    <td>
                                                        <?php
                                                        if (isset($tenant['stats']['error'])) {
                                                            echo '<span class="text-danger" title="' . $tenant['stats']['error'] . '">Error</span>';
                                                        } else {
                                                            echo $tenant['stats']['size_mb'] . ' MB';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (isset($tenant['stats']['error'])) {
                                                            echo '-';
                                                        } else {
                                                            echo $tenant['stats']['table_count'];
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-default btn-icon"><i
                                                                class="fa fa-eye"></i></button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div role="tabpanel" class="tab-pane" id="compare">
                                <div class="row">
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label for="source_slug">Source Database</label>
                                            <select name="source_slug" id="source_slug"
                                                class="form-control selectpicker" data-live-search="true">
                                                <option value="master">Master DB</option>
                                                <?php foreach ($tenants as $tenant): ?>
                                                    <option value="<?php echo $tenant['slug']; ?>">
                                                        <?php echo $tenant['name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2 text-center" style="padding-top: 25px;">
                                        <i class="fa fa-exchange fa-2x"></i>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label for="dest_slug">Destination Database</label>
                                            <select name="dest_slug" id="dest_slug" class="form-control selectpicker"
                                                data-live-search="true">
                                                <!-- Destination shouldn't optionally be master, but for comparison it's fine -->
                                                <option value="master">Master DB</option>
                                                <?php foreach ($tenants as $tenant): ?>
                                                    <option value="<?php echo $tenant['slug']; ?>">
                                                        <?php echo $tenant['name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row text-center">
                                    <button class="btn btn-primary" id="btn_compare">Compare</button>
                                </div>
                                <hr />
                                <div id="compare_results"></div>
                            </div>

                            <div role="tabpanel" class="tab-pane" id="copy">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="panel_s">
                                            <div class="panel-heading">
                                                <h4 class="panel-title">Copy Full Database</h4>
                                            </div>
                                            <div class="panel-body">
                                                <div class="alert alert-danger">
                                                    <strong>WARNING:</strong> This will overwrite the destination
                                                    database! All data in the destination database will be lost.
                                                </div>
                                                <div class="form-group">
                                                    <label for="copy_source_slug">Source Database</label>
                                                    <select name="copy_source_slug" id="copy_source_slug"
                                                        class="form-control selectpicker" data-live-search="true">
                                                        <option value="master">Master DB</option>
                                                        <?php foreach ($tenants as $tenant): ?>
                                                            <option value="<?php echo $tenant['slug']; ?>">
                                                                <?php echo $tenant['name']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="copy_dest_slug">Destination Database</label>
                                                    <select name="copy_dest_slug" id="copy_dest_slug"
                                                        class="form-control selectpicker" data-live-search="true">
                                                        <!-- Master cannot be destination for safety reasons usually, but requested -->
                                                        <option value="" selected disabled>Select Destination</option>
                                                        <?php foreach ($tenants as $tenant): ?>
                                                            <option value="<?php echo $tenant['slug']; ?>">
                                                                <?php echo $tenant['name']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button class="btn btn-danger" id="btn_copy_db">Copy Database</button>
                                                <div id="copy_db_result" class="mtop10"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="panel_s">
                                            <div class="panel-heading">
                                                <h4 class="panel-title">Copy Specific Table</h4>
                                            </div>
                                            <div class="panel-body">
                                                <div class="alert alert-warning">
                                                    <strong>NOTE:</strong> This will drop the table in the destination
                                                    database if it exists and replace it.
                                                </div>
                                                <div class="form-group">
                                                    <label for="copy_table_source_slug">Source Database</label>
                                                    <select name="copy_table_source_slug" id="copy_table_source_slug"
                                                        class="form-control selectpicker" data-live-search="true">
                                                        <option value="" selected disabled>Select Source</option>
                                                        <option value="master">Master DB</option>
                                                        <?php foreach ($tenants as $tenant): ?>
                                                            <option value="<?php echo $tenant['slug']; ?>">
                                                                <?php echo $tenant['name']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="form-group">
                                                    <label for="copy_table_name">Table Name</label>
                                                    <select name="copy_table_name" id="copy_table_name"
                                                        class="form-control selectpicker" data-live-search="true"
                                                        disabled>
                                                        <option value="">Select Source First</option>
                                                    </select>
                                                </div>

                                                <div class="form-group">
                                                    <label for="copy_table_dest_slug">Destination Database</label>
                                                    <select name="copy_table_dest_slug" id="copy_table_dest_slug"
                                                        class="form-control selectpicker" data-live-search="true">
                                                        <option value="" selected disabled>Select Destination</option>
                                                        <?php foreach ($tenants as $tenant): ?>
                                                            <option value="<?php echo $tenant['slug']; ?>">
                                                                <?php echo $tenant['name']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button class="btn btn-danger" id="btn_copy_table">Copy Table</button>
                                                <div id="copy_table_result" class="mtop10"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div role="tabpanel" class="tab-pane" id="backup">
                                <div class="row">
                                    <!-- Backup Section -->
                                    <div class="col-md-6">
                                        <div class="panel_s">
                                            <div class="panel-heading">
                                                <h4 class="panel-title">Download Backup</h4>
                                            </div>
                                            <div class="panel-body">
                                                <div class="alert alert-info">
                                                    Download a SQL dump of the selected database. This is compatible
                                                    with
                                                    standard MySQL tools.
                                                </div>
                                                <form action="<?php echo admin_url('ccx_db/backup_redirect'); ?>"
                                                    method="post" target="_blank" id="form_backup">
                                                    <!-- We use JS to redirect purely due to complexity of dynamic URL building in form action if we used slug directly in URL -->
                                                    <!-- Actually easier to just use JS to window.open or set location -->
                                                    <div class="form-group">
                                                        <label for="backup_slug">Select Database</label>
                                                        <select name="backup_slug" id="backup_slug"
                                                            class="form-control selectpicker" data-live-search="true">
                                                            <option value="master">Master DB</option>
                                                            <?php foreach ($tenants as $tenant): ?>
                                                                <option value="<?php echo $tenant['slug']; ?>">
                                                                    <?php echo $tenant['name']; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <button type="button" class="btn btn-info"
                                                        id="btn_backup_download">Download
                                                        Backup</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Restore Section -->
                                    <div class="col-md-6">
                                        <div class="panel_s">
                                            <div class="panel-heading">
                                                <h4 class="panel-title">Restore Database</h4>
                                            </div>
                                            <div class="panel-body">
                                                <div class="alert alert-danger">
                                                    <strong>CRITICAL:</strong> This will overwrite the selected database
                                                    with
                                                    the uploaded file! Existing data will be lost.
                                                </div>
                                                <form id="form_restore" enctype="multipart/form-data">
                                                    <div class="form-group">
                                                        <label for="restore_dest_slug">Target Database</label>
                                                        <select name="dest_slug" id="restore_dest_slug"
                                                            class="form-control selectpicker" data-live-search="true">
                                                            <option value="" selected disabled>Select Target</option>
                                                            <option value="master">Master DB</option>
                                                            <?php foreach ($tenants as $tenant): ?>
                                                                <option value="<?php echo $tenant['slug']; ?>">
                                                                    <?php echo $tenant['name']; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="backup_file">Backup File (.sql)</label>
                                                        <input type="file" name="backup_file" id="backup_file"
                                                            class="form-control" accept=".sql" required>
                                                    </div>
                                                    <button type="submit" class="btn btn-danger"
                                                        id="btn_restore">Restore
                                                        Database</button>
                                                </form>
                                                <div id="restore_result" class="mtop10"></div>
                                                <!-- Progress Bar (Simple) -->
                                                <div id="restore_progress" style="display:none;" class="mtop10">
                                                    <div class="progress">
                                                        <div class="progress-bar progress-bar-striped active"
                                                            role="progressbar" style="width: 100%">
                                                            Restoring...
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div role="tabpanel" class="tab-pane" id="modules">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="panel_s">
                                            <div class="panel-body">
                                                <div class="form-group">
                                                    <label for="module_tenant_slug">Select Tenant</label>
                                                    <select name="module_tenant_slug" id="module_tenant_slug"
                                                        class="form-control selectpicker" data-live-search="true">
                                                        <option value="" selected disabled>Select Tenant</option>
                                                        <?php foreach ($tenants as $tenant): ?>
                                                            <option value="<?php echo $tenant['slug']; ?>">
                                                                <?php echo $tenant['name']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <hr />
                                                <div id="modules_list_container">
                                                    <div class="alert alert-info">Please select a tenant to view
                                                        modules.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div role="tabpanel" class="tab-pane" id="module_data">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="panel_s">
                                            <div class="panel-body">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="module_data_tenant_slug">Select Tenant(s)</label>
                                                        <select name="module_data_tenant_slug[]"
                                                            id="module_data_tenant_slug"
                                                            class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="Select Tenant(s)">
                                                            <option value="master">Master DB (master)</option>
                                                            <?php foreach ($tenants as $tenant): ?>
                                                                <option value="<?php echo $tenant['slug']; ?>">
                                                                    <?php echo $tenant['name']; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="module_data_module_name">Select Module</label>
                                                        <select name="module_data_module_name[]"
                                                            id="module_data_module_name"
                                                            class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="Select Module(s)">
                                                            <?php foreach ($system_modules as $module): ?>
                                                                <option value="<?php echo $module['system_name']; ?>">
                                                                    <?php echo $module['headers']['module_name']; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="clearfix"></div>
                                                <hr />

                                                <div class="col-md-12">
                                                    <button type="button" class="btn btn-info"
                                                        id="btn_export_module_data">
                                                        <i class="fa fa-download"></i> Export Module Data
                                                    </button>

                                                    <button type="button" class="btn btn-warning"
                                                        id="btn_import_module_data_trigger">
                                                        <i class="fa fa-upload"></i> Import Module Data
                                                    </button>

                                                    <button type="button" class="btn btn-danger"
                                                        id="btn_reinstall_module">
                                                        <i class="fa fa-refresh"></i> Re-install
                                                    </button>

                                                    <!-- Hidden Form for Export -->
                                                    <?php echo form_open(admin_url('ccx_db/export_module_action'), ['id' => 'form_export_module_data', 'target' => '_blank']); ?>
                                                    <input type="hidden" name="slug" id="export_slug">
                                                    <input type="hidden" name="module_name" id="export_module_name">
                                                    <?php echo form_close(); ?>

                                                    <!-- Hidden Upload Input -->
                                                    <input type="file" id="module_data_import_file"
                                                        style="display:none;" accept=".sql">
                                                </div>

                                                <div class="clearfix"></div>
                                                <div id="module_data_result" class="mtop15"></div>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- AUTO TENANTS DATA BACKUP TAB -->
                            <div role="tabpanel" class="tab-pane" id="auto_backup">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <i class="fa fa-info-circle"></i>
                                            Manage automatic database backups for all tenants. By default, auto backup is <strong>enabled</strong> for all tenants.
                                            Use the <strong>Settings</strong> button (bottom-right) to configure backup destination and schedule.
                                        </div>
                                        <div id="auto_backup_list">
                                            <div class="text-center mtop15">
                                                <i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading tenants...
                                            </div>
                                        </div>

                                        <!-- Real-Time Debug Console -->
                                        <div id="backup_console_wrapper" style="display:none; margin-top:20px;">
                                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                                <h5 style="margin:0;"><i class="fa fa-terminal"></i> Backup Debug Console</h5>
                                                <button type="button" class="btn btn-xs btn-default" id="btn_clear_console"><i class="fa fa-eraser"></i> Clear</button>
                                            </div>
                                            <div id="backup_console" style="background:#1e1e1e; color:#00ff41; font-family:'Courier New',monospace; font-size:12px; padding:12px; border-radius:4px; max-height:350px; overflow-y:auto; white-space:pre-wrap; border:1px solid #333;">Waiting for backup operation...</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
</div>

<!-- Floating Settings Button for Auto Backup -->
<div id="fab_backup_settings" style="position:fixed; bottom:30px; right:30px; z-index:9999; display:none;">
    <button type="button" class="btn btn-primary btn-lg" style="border-radius:50%; width:60px; height:60px; box-shadow:0 4px 12px rgba(0,0,0,0.3); font-size:22px;"
        data-toggle="modal" data-target="#backupSettingsModal" title="Backup Settings">
        <i class="fa fa-cog"></i>
    </button>
    <button type="button" class="btn btn-info btn-lg" style="border-radius:50%; width:60px; height:60px; box-shadow:0 4px 12px rgba(0,0,0,0.3); font-size:22px; margin-left:8px;"
        data-toggle="modal" data-target="#backupLogsModal" title="Backup Logs">
        <i class="fa fa-list-alt"></i>
    </button>
</div>

<!-- Backup Settings Modal -->
<div class="modal fade" id="backupSettingsModal" tabindex="-1" role="dialog" aria-labelledby="backupSettingsModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#f7f7f7; border-bottom:2px solid #1abc9c;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="backupSettingsModalLabel"><i class="fa fa-cog"></i> Auto Backup Settings</h4>
            </div>
            <div class="modal-body">
                <form id="form_backup_settings">
                    <!-- Backup Method -->
                    <div class="form-group">
                        <label><strong>Backup Method(s)</strong></label>
                        <select name="ccx_backup_method[]" id="ccx_backup_method" class="selectpicker" multiple data-width="100%" data-none-selected-text="Select Backup Destination(s)...">
                            <option value="ftp">FTP</option>
                            <option value="google_drive">Google Drive API</option>
                        </select>
                    </div>

                    <hr/>

                    <!-- FTP Settings -->
                    <div id="ftp_settings_block">
                        <h5><i class="fa fa-server"></i> FTP Credentials</h5>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>FTP Host</label>
                                    <input type="text" name="ccx_backup_ftp_host" id="ccx_backup_ftp_host" class="form-control" placeholder="e.g. ftp.example.com">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>FTP Port</label>
                                    <input type="number" name="ccx_backup_ftp_port" id="ccx_backup_ftp_port" class="form-control" placeholder="21" value="21">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>FTP Username</label>
                                    <input type="text" name="ccx_backup_ftp_username" id="ccx_backup_ftp_username" class="form-control" placeholder="username">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>FTP Password</label>
                                    <input type="password" name="ccx_backup_ftp_password" id="ccx_backup_ftp_password" class="form-control" placeholder="password" autocomplete="new-password">
                                    <p class="text-muted ccx-secret-hint" style="display:none;"><small><i class="fa fa-lock"></i> A password is saved (stored encrypted). Leave blank to keep it, or type a new one to replace it.</small></p>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>FTP Remote Path</label>
                            <input type="text" name="ccx_backup_ftp_path" id="ccx_backup_ftp_path" class="form-control" placeholder="/backups" value="/backups">
                        </div>
                    </div>

                    <!-- Google Drive Settings -->
                    <div id="gdrive_settings_block" style="display:none;">
                        <h5>
                            <i class="fa fa-google"></i> Google Drive API Credentials
                            <button type="button" class="btn btn-info btn-xs pull-right" id="btn_gdrive_guide">
                                <i class="fa fa-magic"></i> Setup Guide — How to get these?
                            </button>
                            <span class="clearfix"></span>
                        </h5>
                        <div class="form-group">
                            <label>Client ID</label>
                            <input type="text" name="ccx_backup_gdrive_client_id" id="ccx_backup_gdrive_client_id" class="form-control" placeholder="xxxx.apps.googleusercontent.com">
                        </div>
                        <div class="form-group">
                            <label>Client Secret</label>
                            <input type="password" name="ccx_backup_gdrive_client_secret" id="ccx_backup_gdrive_client_secret" class="form-control" placeholder="Client Secret" autocomplete="new-password">
                            <p class="text-muted ccx-secret-hint" style="display:none;"><small><i class="fa fa-lock"></i> A client secret is saved (stored encrypted). Leave blank to keep it, or type a new one to replace it.</small></p>
                        </div>
                        <div class="form-group">
                            <label>Refresh Token</label>
                            <input type="password" name="ccx_backup_gdrive_refresh_token" id="ccx_backup_gdrive_refresh_token" class="form-control" placeholder="Refresh Token" autocomplete="new-password">
                            <p class="text-muted ccx-secret-hint" style="display:none;"><small><i class="fa fa-lock"></i> A refresh token is saved (stored encrypted). Leave blank to keep it, or paste a new one to replace it.</small></p>
                        </div>
                        <div class="form-group">
                            <label>Folder ID</label>
                            <input type="text" name="ccx_backup_gdrive_folder_id" id="ccx_backup_gdrive_folder_id" class="form-control" placeholder="Google Drive Folder ID">
                            <p class="text-muted"><small>The ID of the Google Drive folder where backups will be stored.</small></p>
                        </div>
                    </div>

                    <hr/>

                    <!-- Incremental Backup Schedule -->
                    <div style="background:#f0faf5; border:1px solid #d5f0e3; border-radius:6px; padding:15px; margin-bottom:15px;">
                        <h5 style="margin-top:0;"><i class="fa fa-bolt" style="color:#27ae60;"></i> Incremental Backup Schedule</h5>
                        <div class="alert alert-info" style="font-size:12px; padding:8px 12px; margin-bottom:10px;">
                            <i class="fa fa-info-circle"></i>
                            <strong>Incremental:</strong> Compares table checksums &amp; row counts. Only dumps tables that changed since the last backup. Saves time &amp; storage for large databases.
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Frequency</label>
                                    <select name="ccx_backup_incr_cron_frequency" id="ccx_backup_incr_cron_frequency" class="form-control">
                                        <option value="every_minute" selected>Every Minute</option>
                                        <option value="every_5_minutes">Every 5 Minutes</option>
                                        <option value="every_15_minutes">Every 15 Minutes</option>
                                        <option value="every_30_minutes">Every 30 Minutes</option>
                                        <option value="hourly">Every Hour</option>
                                        <option value="every_6_hours">Every 6 Hours</option>
                                        <option value="every_12_hours">Every 12 Hours</option>
                                        <option value="daily">Daily</option>
                                        <option value="custom">Custom</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Cron Expression</label>
                                    <input type="text" name="ccx_backup_incr_cron_schedule" id="ccx_backup_incr_cron_schedule" class="form-control" value="* * * * *">
                                    <p class="text-muted"><small>Standard cron format: minute hour day month weekday</small></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Full Backup Schedule -->
                    <div style="background:#fdf5f0; border:1px solid #f0dfd5; border-radius:6px; padding:15px; margin-bottom:15px;">
                        <h5 style="margin-top:0;"><i class="fa fa-database" style="color:#e67e22;"></i> Full Backup Schedule</h5>
                        <div class="alert alert-warning" style="font-size:12px; padding:8px 12px; margin-bottom:10px;">
                            <i class="fa fa-info-circle"></i>
                            <strong>Full:</strong> Dumps all tables completely, regardless of changes. Provides a complete restore point. Runs independently from incremental backups.
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Frequency</label>
                                    <select name="ccx_backup_full_cron_frequency" id="ccx_backup_full_cron_frequency" class="form-control">
                                        <option value="hourly">Every Hour</option>
                                        <option value="every_6_hours">Every 6 Hours</option>
                                        <option value="every_12_hours">Every 12 Hours</option>
                                        <option value="daily" selected>Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="custom">Custom</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Cron Expression</label>
                                    <input type="text" name="ccx_backup_full_cron_schedule" id="ccx_backup_full_cron_schedule" class="form-control" value="0 2 * * *">
                                    <p class="text-muted"><small>Standard cron format: minute hour day month weekday</small></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr/>

                    <!-- Advanced Settings -->
                    <h5><i class="fa fa-cogs"></i> Advanced Settings</h5>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Retention Days</label>
                                <input type="number" name="ccx_backup_retention_days" id="ccx_backup_retention_days" class="form-control" value="30" min="1" max="365">
                                <p class="text-muted"><small>Delete backups older than this</small></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Max Backups / Tenant</label>
                                <input type="number" name="ccx_backup_max_backups" id="ccx_backup_max_backups" class="form-control" value="10" min="1" max="100">
                                <p class="text-muted"><small>Keep at most N backups per tenant</small></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div class="checkbox">
                                    <input type="checkbox" name="ccx_backup_auto_delete" id="ccx_backup_auto_delete" value="1" checked>
                                    <label for="ccx_backup_auto_delete"><strong>Auto-Delete Old Backups</strong></label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <div class="checkbox">
                                    <input type="checkbox" name="ccx_backup_compression" id="ccx_backup_compression" value="1" checked>
                                    <label for="ccx_backup_compression"><strong>Compress Backups (GZip)</strong></label>
                                </div>
                                <p class="text-muted"><small>Saves storage space</small></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <div class="checkbox">
                                    <input type="checkbox" name="ccx_backup_notify_on_failure" id="ccx_backup_notify_on_failure" value="1" checked>
                                    <label for="ccx_backup_notify_on_failure"><strong>Email on Failure</strong></label>
                                </div>
                                <div class="checkbox">
                                    <input type="checkbox" name="ccx_backup_notify_on_success" id="ccx_backup_notify_on_success" value="1">
                                    <label for="ccx_backup_notify_on_success"><strong>Email on Success</strong></label>
                                </div>
                                <p class="text-muted"><small>Success summary is sent after full backup runs (scheduled &amp; manual) with per-tenant details</small></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Notification Email</label>
                                <input type="email" name="ccx_backup_notify_email" id="ccx_backup_notify_email" class="form-control" placeholder="admin@example.com">
                            </div>
                        </div>
                    </div>

                </form>
                <div id="test_connection_result" class="mtop10"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" id="btn_test_connection"><i class="fa fa-plug"></i> Test Connection</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="btn_save_backup_settings"><i class="fa fa-check"></i> Save Settings</button>
            </div>
        </div>
    </div>
</div>

<!-- Backup Logs Modal -->
<div class="modal fade" id="backupLogsModal" tabindex="-1" role="dialog" aria-labelledby="backupLogsModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#f7f7f7; border-bottom:2px solid #3498db;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="backupLogsModalLabel"><i class="fa fa-list-alt"></i> Backup Logs</h4>
            </div>
            <div class="modal-body">
                <div class="row mbot10">
                    <div class="col-md-4">
                        <select id="logs_filter_action" class="form-control">
                            <option value="">All Actions</option>
                            <option value="test_connection">Test Connection</option>
                            <option value="auto_backup">Auto Backup</option>
                            <option value="cron_heartbeat">Cron Heartbeat</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary btn-block" id="btn_refresh_logs"><i class="fa fa-refresh"></i> Refresh</button>
                    </div>
                </div>
                <div id="backup_logs_container">
                    <div class="text-center mtop15">
                        <i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading logs...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger pull-left" id="btn_delete_all_logs"><i class="fa fa-trash"></i> Delete All Logs</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Cron Diagnostic Modal -->
<div class="modal fade" id="cronDiagnosticModal" tabindex="-1" role="dialog" aria-labelledby="cronDiagnosticModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#fcf8e3; border-bottom:2px solid #f39c12;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="cronDiagnosticModalLabel"><i class="fa fa-wrench text-warning"></i> Cron Job Diagnostics</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <h4>Cron Status</h4>
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <th style="width: 40%;">Main System Cron Status</th>
                                    <td id="diag_cron_status">Loading...</td>
                                </tr>
                                <tr>
                                    <th>Last Perfex Cron Run</th>
                                    <td id="diag_cron_last_run">Loading...</td>
                                </tr>
                                <tr>
                                    <th>Last CCX Incremental Backup Run</th>
                                    <td id="diag_ccx_incr_last_run">Loading...</td>
                                </tr>
                                <tr>
                                    <th>Last CCX Full Backup Run</th>
                                    <td id="diag_ccx_full_last_run">Loading...</td>
                                </tr>
                                <tr>
                                    <th>Latest CCX Cron Heartbeat log</th>
                                    <td id="diag_ccx_heartbeat" class="text-info" style="font-family: monospace; font-size:11px;">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="alert alert-info mt-3">
                            <strong>Note:</strong> CCX Auto Backups rely on the main Perfex CRM Cron Job. If the main cron job is not running, automatic backups will not run. Please add one of the following commands to your cPanel or server's Cron Jobs setup, running every minute (<code>* * * * *</code>).
                        </div>

                        <h4>cPanel Cron Setup Commands</h4>
                        <div class="form-group">
                            <label>Wget command (Recommended)</label>
                            <input type="text" class="form-control hover-copy" id="diag_cron_wget" readonly value="Loading...">
                        </div>
                        <div class="form-group">
                            <label>cURL command</label>
                            <input type="text" class="form-control hover-copy" id="diag_cron_curl" readonly value="Loading...">
                        </div>
                        <div class="form-group">
                            <label>PHP command (Alternative)</label>
                            <input type="text" class="form-control hover-copy" id="diag_cron_php" readonly value="Loading...">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Google Drive Setup Guide Modal (opens on top of Backup Settings modal) -->
<style>
    #gdriveGuideModal .gdrive-steps-bar { display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
    #gdriveGuideModal .gdrive-step-dot {
        width: 34px; height: 34px; line-height: 32px; text-align: center; border-radius: 50%;
        border: 2px solid #ccc; background: #fff; color: #777; font-weight: bold; cursor: pointer;
        flex-shrink: 0; transition: all .2s;
    }
    #gdriveGuideModal .gdrive-step-dot.active { border-color: #4285f4; background: #4285f4; color: #fff; }
    #gdriveGuideModal .gdrive-step-dot.done { border-color: #27ae60; background: #27ae60; color: #fff; }
    #gdriveGuideModal .gdrive-step-line { height: 2px; background: #ccc; flex: 1; max-width: 45px; }
    #gdriveGuideModal .gdrive-guide-step { display: none; min-height: 300px; }
    #gdriveGuideModal .gdrive-guide-step h4 { margin-top: 0; color: #4285f4; }
    #gdriveGuideModal ol > li { margin-bottom: 8px; }
    #gdriveGuideModal .gdrive-copy-group { margin: 10px 0; }
</style>
<div class="modal fade" id="gdriveGuideModal" tabindex="-1" role="dialog" aria-labelledby="gdriveGuideModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#f7f7f7; border-bottom:2px solid #4285f4;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="gdriveGuideModalLabel"><i class="fa fa-google"></i> Google Drive API — Setup Guide</h4>
            </div>
            <div class="modal-body">
                <!-- Step progress -->
                <div class="gdrive-steps-bar">
                    <span class="gdrive-step-dot" data-step="1" title="Project & API">1</span><span class="gdrive-step-line"></span><span class="gdrive-step-dot" data-step="2" title="Consent Screen">2</span><span class="gdrive-step-line"></span><span class="gdrive-step-dot" data-step="3" title="OAuth Client">3</span><span class="gdrive-step-line"></span><span class="gdrive-step-dot" data-step="4" title="Refresh Token">4</span><span class="gdrive-step-line"></span><span class="gdrive-step-dot" data-step="5" title="Folder ID">5</span><span class="gdrive-step-line"></span><span class="gdrive-step-dot" data-step="6" title="Finish">6</span>
                </div>

                <!-- Step 1: Project & Enable API -->
                <div class="gdrive-guide-step" data-step="1">
                    <h4><i class="fa fa-cloud"></i> Step 1 — Create Project &amp; Enable the Drive API</h4>
                    <ol>
                        <li>Open the <a href="https://console.cloud.google.com" target="_blank" rel="noopener">Google Cloud Console <i class="fa fa-external-link"></i></a> and sign in with the Google account whose Drive will store the backups.</li>
                        <li>In the top bar, click the <strong>project dropdown</strong> &rarr; <strong>New Project</strong> &rarr; name it (e.g. <code>HealthO Backups</code>) &rarr; <strong>Create</strong>, then select it.</li>
                        <li>Go to <strong>APIs &amp; Services &rarr; Library</strong>, search for <strong>Google Drive API</strong> and click <strong>Enable</strong>.</li>
                    </ol>
                    <div class="alert alert-info" style="font-size:12px;"><i class="fa fa-info-circle"></i> Use a dedicated Google account (or your company account) — all backups will be uploaded to <strong>this account's Drive</strong>.</div>
                </div>

                <!-- Step 2: Consent Screen -->
                <div class="gdrive-guide-step" data-step="2">
                    <h4><i class="fa fa-shield"></i> Step 2 — Configure the OAuth Consent Screen</h4>
                    <ol>
                        <li>Go to <strong>APIs &amp; Services &rarr; OAuth consent screen</strong> (also called <em>Google Auth Platform</em>).</li>
                        <li>User type: <strong>External</strong> &rarr; Create.</li>
                        <li>Fill only the required fields: <strong>App name</strong> (e.g. <code>HealthO Backup</code>), your <strong>support email</strong> and <strong>developer email</strong> &rarr; Save through the remaining steps.</li>
                        <li>After creating it, click <strong>Publish App</strong> so the status becomes <strong>In production</strong>.</li>
                    </ol>
                    <div class="alert alert-warning" style="font-size:12px;">
                        <i class="fa fa-exclamation-triangle"></i> <strong>Important:</strong> If the app stays in <em>Testing</em> mode, the Refresh Token expires after <strong>7 days</strong> and automated backups will silently stop. Publishing to production fixes this. You do <u>not</u> need Google verification — only you use this app; you'll just click through an "unverified app" warning once (<em>Advanced &rarr; Go to app</em>).
                    </div>
                </div>

                <!-- Step 3: OAuth Client ID -->
                <div class="gdrive-guide-step" data-step="3">
                    <h4><i class="fa fa-key"></i> Step 3 — Create the OAuth Client ID</h4>
                    <ol>
                        <li>Go to <strong>APIs &amp; Services &rarr; Credentials &rarr; + Create Credentials &rarr; OAuth client ID</strong>.</li>
                        <li>Application type: <strong>Web application</strong>. Name: anything (e.g. <code>HealthO Backup Client</code>).</li>
                        <li>Under <strong>Authorized redirect URIs</strong> click <strong>+ Add URI</strong> and paste exactly (no trailing slash):</li>
                    </ol>
                    <div class="input-group gdrive-copy-group">
                        <input type="text" class="form-control" readonly value="https://developers.google.com/oauthplayground">
                        <span class="input-group-btn">
                            <button class="btn btn-primary gdrive-copy-btn" type="button"><i class="fa fa-copy"></i> Copy</button>
                        </span>
                    </div>
                    <ol start="4">
                        <li>Click <strong>Create</strong>, then copy the <strong>Client ID</strong> (ends in <code>.apps.googleusercontent.com</code>) and the <strong>Client Secret</strong> — you will paste both into the settings form and also use them in the next step.</li>
                    </ol>
                </div>

                <!-- Step 4: Refresh Token -->
                <div class="gdrive-guide-step" data-step="4">
                    <h4><i class="fa fa-refresh"></i> Step 4 — Get the Refresh Token (OAuth Playground)</h4>
                    <ol>
                        <li>Open the <a href="https://developers.google.com/oauthplayground" target="_blank" rel="noopener">Google OAuth 2.0 Playground <i class="fa fa-external-link"></i></a>.</li>
                        <li>Click the <strong>gear icon <i class="fa fa-cog"></i></strong> (top-right) &rarr; tick <strong>Use your own OAuth credentials</strong> &rarr; paste your Client ID &amp; Client Secret &rarr; Close.</li>
                        <li>In the left panel (Step 1), find <strong>Drive API v3</strong> and tick this scope (or paste it in the input box):</li>
                    </ol>
                    <div class="input-group gdrive-copy-group">
                        <input type="text" class="form-control" readonly value="https://www.googleapis.com/auth/drive">
                        <span class="input-group-btn">
                            <button class="btn btn-primary gdrive-copy-btn" type="button"><i class="fa fa-copy"></i> Copy</button>
                        </span>
                    </div>
                    <ol start="4">
                        <li>Click <strong>Authorize APIs</strong> &rarr; sign in with the same Google account &rarr; click through the unverified-app warning &rarr; <strong>Allow</strong>.</li>
                        <li>In Step 2 of the playground, click <strong>Exchange authorization code for tokens</strong>.</li>
                        <li>Copy the <strong>Refresh token</strong> (starts with <code>1//</code>). Ignore the access token — it is temporary.</li>
                    </ol>
                    <div class="alert alert-info" style="font-size:12px;"><i class="fa fa-info-circle"></i> The full <code>drive</code> scope is required (not <code>drive.file</code>) so backups can be uploaded into your own folder and old backups auto-deleted by retention cleanup.</div>
                </div>

                <!-- Step 5: Folder ID -->
                <div class="gdrive-guide-step" data-step="5">
                    <h4><i class="fa fa-folder-open"></i> Step 5 — Get the Drive Folder ID</h4>
                    <ol>
                        <li>In <a href="https://drive.google.com" target="_blank" rel="noopener">Google Drive <i class="fa fa-external-link"></i></a>, create a folder for the backups (e.g. <code>HealthO-DB-Backups</code>).</li>
                        <li>Open the folder and look at the browser address bar:<br><code>https://drive.google.com/drive/folders/<strong style="background:#fff3cd;">1AbCdEfGh_xxxxxxxx</strong></code></li>
                        <li>The highlighted part after <code>/folders/</code> is the <strong>Folder ID</strong> — copy it.</li>
                    </ol>
                </div>

                <!-- Step 6: Finish -->
                <div class="gdrive-guide-step" data-step="6">
                    <h4><i class="fa fa-check-circle"></i> Step 6 — Paste Everything &amp; Test</h4>
                    <p>Close this guide and fill in the four fields in the settings form behind it:</p>
                    <table class="table table-bordered table-condensed" style="font-size:13px;">
                        <tr><td style="width:40%;"><strong>Client ID</strong></td><td>from Step 3 (<code>...apps.googleusercontent.com</code>)</td></tr>
                        <tr><td><strong>Client Secret</strong></td><td>from Step 3 (stored encrypted)</td></tr>
                        <tr><td><strong>Refresh Token</strong></td><td>from Step 4 (starts with <code>1//</code>, stored encrypted)</td></tr>
                        <tr><td><strong>Folder ID</strong></td><td>from Step 5</td></tr>
                    </table>
                    <p>Then <strong>Save Settings</strong> and run a manual backup on one database to verify — you should see <em>"Uploading to Google Drive..."</em> in the backup steps and the file appear in your Drive folder.</p>
                    <div class="alert alert-warning" style="font-size:12px;"><i class="fa fa-exclamation-triangle"></i> The Refresh Token is tied to this exact Client ID/Secret pair — don't delete or regenerate the OAuth client later, or you'll need to redo Step 4.</div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="pull-left text-muted" id="gdrive_guide_step_label" style="line-height:34px;">Step 1 of 6</span>
                <button type="button" class="btn btn-default" id="gdrive_guide_prev"><i class="fa fa-chevron-left"></i> Previous</button>
                <button type="button" class="btn btn-primary" id="gdrive_guide_next">Next <i class="fa fa-chevron-right"></i></button>
                <button type="button" class="btn btn-success" id="gdrive_guide_finish" style="display:none;"><i class="fa fa-check"></i> Done — Fill the Form</button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════ Session Cleanup ══════════════ -->
<div class="modal fade" id="sessionCleanupModal" tabindex="-1" role="dialog" aria-labelledby="sessionCleanupModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="sessionCleanupModalLabel"><i class="fa fa-eraser"></i> Session Cleanup — Master &amp; All Tenants</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="font-size:13px;">
                    CodeIgniter keeps sessions in MySQL and every request reads and writes its session row under a lock.
                    Deleted sessions do not release their disk space on their own, so the table keeps growing — hundreds of
                    megabytes for a few thousand live sessions. Clearing rebuilds each table so the space is actually returned.
                </p>

                <div class="row" style="margin-bottom:10px;">
                    <div class="col-md-5">
                        <label for="sessions_idle_hours" class="control-label">Treat sessions idle for longer than</label>
                        <select id="sessions_idle_hours" class="form-control">
                            <option value="1">1 hour</option>
                            <option value="8">8 hours</option>
                            <option value="24" selected>24 hours</option>
                            <option value="72">3 days</option>
                            <option value="168">7 days</option>
                        </select>
                        <span class="help-block" style="font-size:12px;">…as expired.</span>
                    </div>
                    <div class="col-md-7 text-right" style="padding-top:25px;">
                        <button type="button" class="btn btn-default" id="btn_sessions_refresh">
                            <i class="fa fa-refresh"></i> Rescan
                        </button>
                        <button type="button" class="btn btn-warning" id="btn_sessions_clear_expired">
                            <i class="fa fa-clock-o"></i> Clear expired only
                        </button>
                        <button type="button" class="btn btn-danger" id="btn_sessions_clear_all">
                            <i class="fa fa-trash"></i> Clear ALL sessions
                        </button>
                    </div>
                </div>

                <div class="alert alert-warning" style="font-size:12.5px;">
                    <i class="fa fa-exclamation-triangle"></i>
                    <strong>Clear ALL</strong> signs out every logged-in user on the master and on every tenant, immediately.
                    Your own session is kept so you stay signed in here. <strong>Clear expired only</strong> reclaims almost
                    the same space without disturbing anyone who is currently working — prefer it during business hours.
                </div>

                <div id="sessions_overview_wrap">
                    <div class="text-center" style="padding:20px;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                        <p class="text-muted">Scanning sessions in every database…</p>
                    </div>
                </div>

                <div id="sessions_result_wrap" style="display:none; margin-top:15px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

</div>
<?php init_tail(); ?>
<script src="<?php echo module_dir_url('ccx_db', 'assets/js/ccx_db.js'); ?>?v=<?php echo time(); ?>"></script>