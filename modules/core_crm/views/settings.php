<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4>Core CRM - Menu Visibility Settings</h4>
                        <hr class="hr-panel-heading" />

                        <?php echo form_open(admin_url('core_crm')); ?>

                        <div class="row">
                            <div class="col-md-12">
                                <p class="text-muted">Check the menus you want to <b>HIDE</b> from the admin sidebar.
                                    <br><small class="text-info"><i class="fa fa-info-circle"></i> Missing DB options default to <b>hidden &amp; restricted</b> for security.</small>
                                </p>
                                <br>
                                <?php
                                $menus = core_crm_managed_menus();

                                foreach ($menus as $slug => $label) {
                                    $hide_raw = get_option('core_crm_hide_' . $slug);
                                    $restrict_raw = get_option('core_crm_restrict_' . $slug);

                                    // Checkbox state mirrors the actual hook behavior (core_crm_option_enabled):
                                    // missing/empty/'1' => enabled, only explicit '0' => off.
                                    $hide_checked = core_crm_option_enabled('core_crm_hide_' . $slug) ? 'checked' : '';
                                    $restrict_checked = core_crm_option_enabled('core_crm_restrict_' . $slug) ? 'checked' : '';

                                    $hide_status = ($hide_raw === false) ? '<span class="label label-warning" style="font-size:9px;">NOT IN DB</span>' : '';
                                    $restrict_status = ($restrict_raw === false) ? '<span class="label label-warning" style="font-size:9px;">NOT IN DB</span>' : '';
                                    ?>
                                    <div class="row border-bottom mbot10">
                                        <div class="col-md-4">
                                            <h5><?php echo $label; ?></h5>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="checkbox checkbox-primary">
                                                <input type="checkbox" name="hide_<?php echo $slug; ?>"
                                                    id="hide_<?php echo $slug; ?>" <?php echo $hide_checked; ?>>
                                                <label for="hide_<?php echo $slug; ?>">
                                                    Hide Menu <?php echo $hide_status; ?>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="checkbox checkbox-danger">
                                                <input type="checkbox" name="restrict_<?php echo $slug; ?>"
                                                    id="restrict_<?php echo $slug; ?>" <?php echo $restrict_checked; ?>>
                                                <label for="restrict_<?php echo $slug; ?>">
                                                    Restrict Link Access <?php echo $restrict_status; ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>

                        <hr />
                        <button type="submit" class="btn btn-info">Save Settings</button>
                        <?php echo form_close(); ?>

                    </div>
                </div>

                <!-- Diagnostics Panel -->
                <div class="panel_s">
                    <div class="panel-body">
                        <h4><i class="fa fa-stethoscope"></i> Diagnostics</h4>
                        <hr class="hr-panel-heading" />

                        <?php
                        $CI = &get_instance();
                        $db_name = $CI->db->database;
                        $db_hostname = $CI->db->hostname;
                        ?>

                        <div class="row mbot15">
                            <div class="col-md-4">
                                <strong>Database:</strong>
                                <code><?php echo $db_name; ?></code>
                            </div>
                            <div class="col-md-4">
                                <strong>DB Host:</strong>
                                <code><?php echo $db_hostname; ?></code>
                            </div>
                            <div class="col-md-4">
                                <strong>PHP:</strong>
                                <code><?php echo phpversion(); ?></code>
                            </div>
                        </div>

                        <h5 class="mbot15"><strong>Option Values (get_option)</strong></h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-condensed" style="font-size:12px;">
                                <thead>
                                    <tr>
                                        <th>Menu</th>
                                        <th>Hide Key</th>
                                        <th>Hide Value</th>
                                        <th>Hide Type</th>
                                        <th>Hide Status</th>
                                        <th>Restrict Key</th>
                                        <th>Restrict Value</th>
                                        <th>Restrict Type</th>
                                        <th>Restrict Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $diag_menus = core_crm_managed_menus();

                                foreach ($diag_menus as $slug => $label) {
                                    $h_key = 'core_crm_hide_' . $slug;
                                    $r_key = 'core_crm_restrict_' . $slug;
                                    $h_val = get_option($h_key);
                                    $r_val = get_option($r_key);

                                    $h_type = ($h_val === false) ? 'bool(false)' : gettype($h_val) . '("' . $h_val . '")';
                                    $r_type = ($r_val === false) ? 'bool(false)' : gettype($r_val) . '("' . $r_val . '")';

                                    // Effective state = what the hook actually does (missing defaults to ON).
                                    $h_active = core_crm_option_enabled($h_key);
                                    $r_active = core_crm_option_enabled($r_key);

                                    $h_missing = ($h_val === false) ? ' <span class="label label-warning">NOT IN DB</span>' : '';
                                    $r_missing = ($r_val === false) ? ' <span class="label label-warning">NOT IN DB</span>' : '';

                                    $h_badge = ($h_active ? '<span class="label label-success">ACTIVE</span>' : '<span class="label label-default">OFF</span>') . $h_missing;
                                    $r_badge = ($r_active ? '<span class="label label-success">ACTIVE</span>' : '<span class="label label-default">OFF</span>') . $r_missing;
                                ?>
                                    <tr>
                                        <td><strong><?php echo $label; ?></strong></td>
                                        <td><code><?php echo $h_key; ?></code></td>
                                        <td><code><?php echo var_export($h_val, true); ?></code></td>
                                        <td><small><?php echo $h_type; ?></small></td>
                                        <td><?php echo $h_badge; ?></td>
                                        <td><code><?php echo $r_key; ?></code></td>
                                        <td><code><?php echo var_export($r_val, true); ?></code></td>
                                        <td><small><?php echo $r_type; ?></small></td>
                                        <td><?php echo $r_badge; ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <h5 class="mbot15"><strong>Direct DB Query (tbloptions)</strong></h5>
                        <?php
                        $db_rows = $CI->db->like('name', 'core_crm_', 'after')->get('tbloptions')->result();
                        ?>
                        <?php if (empty($db_rows)) { ?>
                            <div class="alert alert-danger">
                                <i class="fa fa-exclamation-triangle"></i>
                                <strong>No core_crm rows found in tbloptions!</strong>
                                The install.php may not have run for this tenant.
                            </div>
                        <?php } else { ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-condensed" style="font-size:12px;">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Option Name</th>
                                            <th>Option Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($db_rows as $row) { ?>
                                        <tr>
                                            <td><?php echo $row->id; ?></td>
                                            <td><code><?php echo $row->name; ?></code></td>
                                            <td>
                                                <code><?php echo $row->value; ?></code>
                                                <?php if ($row->value == '1') { ?>
                                                    <span class="label label-success">ON</span>
                                                <?php } else { ?>
                                                    <span class="label label-default">OFF</span>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-muted"><small>Total rows: <?php echo count($db_rows); ?> (expected: 24)</small></p>
                        <?php } ?>

                        <h5 class="mbot15"><strong>Sidebar Menu Slugs (currently loaded)</strong></h5>
                        <?php
                        $sidebar_items = $CI->app_menu->get_sidebar_menu_items();
                        if (!empty($sidebar_items)) {
                        ?>
                            <div class="well" style="font-size:12px; max-height:200px; overflow-y:auto;">
                            <?php foreach ($sidebar_items as $item) { ?>
                                <code><?php echo isset($item['slug']) ? $item['slug'] : 'no-slug'; ?></code>
                                (<?php echo isset($item['name']) ? $item['name'] : ''; ?>)
                                <br>
                            <?php } ?>
                            </div>
                        <?php } else { ?>
                            <p class="text-muted">No sidebar items found.</p>
                        <?php } ?>

                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>