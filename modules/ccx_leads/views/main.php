<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <!-- Minimal inline styles to ensure header/filter layout (keeps safe fallback if module CSS fails to load) -->
                        <style>
                            .ccx-header-container {
                                display: flex;
                                align-items: center;
                                justify-content: space-between;
                                flex-wrap: wrap;
                                gap: 10px;
                                margin-bottom: 0;
                                background: transparent;
                                padding: 15px;
                                border-bottom: none;
                            }

                            .ccx-status-filter {
                                display: inline-flex;
                                background: #f3f4f6;
                                padding: 6px;
                                border-radius: 20px;
                                flex-wrap: wrap;
                                margin-bottom: 0;
                                gap: 6px;
                            }

                            .ccx-status-filter-item {
                                padding: 6px 12px;
                                border-radius: 16px;
                                cursor: pointer;
                                font-weight: 500;
                                color: #374151;
                                font-size: 13px;
                                background: transparent;
                                border: 1px solid transparent;
                            }

                            .ccx-status-filter-item.active {
                                background: #fff;
                                color: #111827;
                                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
                            }

                            .ccx-status-count {
                                margin-left: 8px;
                                font-size: 11px;
                                color: #374151;
                            }
                        </style>

                        <div class="ccx-header-container">
                            <nav class="ccx-status-filter" aria-label="Lead status filter">
                                <div class="ccx-status-filter-item active" data-status="" tabindex="0" role="button"
                                    aria-pressed="true">
                                    All
                                </div>
                                <?php
                                foreach ($statuses as $status) {
                                    $count = 0;
                                    foreach ($summary as $s) {
                                        if ($s['id'] == $status['id']) {
                                            $count = $s['total'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <?php $color = isset($status['color']) && $status['color'] ? $status['color'] : '#757575'; ?>
                                    <div class="ccx-status-filter-item" data-status="<?php echo $status['id']; ?>"
                                        tabindex="0" role="button" aria-pressed="false">
                                        <?php echo $status['name']; ?>
                                        <span class="ccx-status-count"
                                            style="background: <?php echo $color; ?>; color: #fff; opacity:0.95"><?php echo $count; ?></span>
                                    </div>
                                <?php } ?>

                                <!-- Junk and Lost Tabs -->
                                <?php
                                $junk_count = 0;
                                $lost_count = 0;
                                foreach ($summary as $s) {
                                    if ($s['id'] == 'junk')
                                        $junk_count = $s['total'];
                                    if ($s['id'] == 'lost')
                                        $lost_count = $s['total'];
                                }
                                ?>
                                <?php $junk_color = '#9CA3AF';
                                $lost_color = '#EF4444'; ?>
                                <div class="ccx-status-filter-item" data-status="junk" tabindex="0" role="button"
                                    aria-pressed="false">
                                    <?php echo _l('leads_junk'); ?>
                                    <span class="ccx-status-count"
                                        style="background: <?php echo $junk_color; ?>; color:#fff; opacity:0.95"><?php echo $junk_count; ?></span>
                                </div>
                                <div class="ccx-status-filter-item" data-status="lost" tabindex="0" role="button"
                                    aria-pressed="false">
                                    <?php echo _l('leads_lost'); ?>
                                    <span class="ccx-status-count"
                                        style="background: <?php echo $lost_color; ?>; color:#fff; opacity:0.95"><?php echo $lost_count; ?></span>
                                </div>
                            </nav>

                            <div class="ccx-actions" style="display:flex; gap:10px; align-items:center;">
                                <a href="<?php echo admin_url('ccx_leads/reports'); ?>" class="btn btn-default"
                                    aria-label="Reports">
                                    <i class="fa fa-chart-bar"></i> Reports
                                </a>
                                <a href="#" onclick="ccx_leads_new_lead(); return false;" class="btn btn-info"
                                    aria-label="Create new lead">
                                    <?php echo _l('new_lead'); ?>
                                </a>

                                <div class="btn-group btn-with-tooltip-group _filter_data" data-toggle="tooltip"
                                    data-title="<?php echo _l('filter_by'); ?>">
                                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        <i class="fa fa-filter" aria-hidden="true"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right" style="width:300px;">
                                        <li class="active"><a href="#" data-cview="all"
                                                onclick="dt_custom_view('','.table-leads',''); return false;">
                                                <?php echo _l('leads_all'); ?>
                                            </a></li>
                                        <?php foreach ($sources as $source) { ?>
                                            <li><a href="#" data-cview="source_<?php echo $source['id']; ?>"
                                                    onclick="dt_custom_view('source_<?php echo $source['id']; ?>','.table-leads','source_<?php echo $source['id']; ?>'); return false;">
                                                    <?php echo $source['name']; ?>
                                                </a></li>
                                        <?php } ?>
                                    </ul>
                                </div>

                                <div class="btn-group" data-toggle="tooltip"
                                    title="<?php echo _l('leads_view_mode'); ?>" role="group" aria-label="View mode">
                                    <button type="button" class="btn btn-default" onclick="ccx_switch_view('list')"
                                        aria-pressed="false" aria-label="List view"><i class="fa fa-list"></i></button>
                                    <button type="button" class="btn btn-default" onclick="ccx_switch_view('kanban')"
                                        aria-pressed="false" aria-label="Kanban view"><i
                                            class="fa fa-th-large"></i></button>
                                </div>
                                <?php echo form_hidden('custom_view'); ?>
                            </div>
                        </div>

                        <div class="clearfix"></div>

                        <div id="ccx_leads_view_wrapper" class="">
                            <!-- Content loaded via JS or default to Kanban/List -->
                            <div id="ccx_kanban_view" class="hide">
                                <?php echo $kanban_content; ?>
                            </div>
                            <div id="ccx_list_view">
                                <?php
                                $table_data = array();
                                $_table_data = array(
                                    '<span class="hide"> - </span><div class="checkbox mass_select_all_wrap"><input type="checkbox" id="mass_select_all" data-to-table="ccx-leads"><label></label></div>',
                                    '#',
                                    _l('leads_dt_name'),
                                    _l('leads_dt_email'),
                                    _l('leads_dt_phonenumber'),
                                    _l('leads_dt_assigned'),
                                    _l('leads_dt_status'),
                                    _l('leads_dt_last_contact'),
                                    _l('leads_dt_datecreated')
                                );
                                foreach ($_table_data as $_t) {
                                    array_push($table_data, $_t);
                                }
                                render_datatable($table_data, 'ccx-leads');
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Slide Over Panel Markup -->
<div id="ccx_lead_slideover" class="ccx-slideover">
    <div class="ccx-slideover-overlay" onclick="ccx_close_slideover()"></div>
    <div class="ccx-slideover-content">
        <button type="button" class="close-slideover" onclick="ccx_close_slideover()">&times;</button>
        <div id="ccx_slideover_body">
            <!-- Content loaded via AJAX -->
            <div class="text-center ptop20"><i class="fa fa-spinner fa-spin fa-2x"></i></div>
        </div>
    </div>
</div>

<?php if (function_exists('is_admin') && is_admin()) { ?>
    <a href="<?php echo admin_url('ccx_leads/settings'); ?>" class="ccx-floating-settings-btn" data-toggle="tooltip"
        title="Settings" aria-label="Settings">
        <i class="fa fa-cog"></i>
    </a>
<?php } ?>

<?php init_tail(); ?>
<script>
    var CcxLeadsServerParams = {
        "custom_view": "[name='custom_view']"
    };

    var ccx_leads_table;
    $(function () {
        ccx_leads_table = initDataTable('.table-ccx-leads', admin_url + 'ccx_leads/table', [0], [0], CcxLeadsServerParams, [8, 'desc']);

        // Refresh list view table when the module lead modal closes after add/edit
        $('body').on('hidden.bs.modal', '#ccx-lead-modal', function () {
            if ($.fn.DataTable.isDataTable('.table-ccx-leads')) {
                ccx_leads_table.ajax.reload(null, false);
            }
        });

        // Status Filter Logic
        $('body').on('click', '.ccx-status-filter-item', function () {
            // Update UI
            $('.ccx-status-filter-item').removeClass('active').attr('aria-pressed', 'false');
            $(this).addClass('active').attr('aria-pressed', 'true');

            // Update Param using hidden input (standard perfex way)
            var status = $(this).data('status');
            $('input[name="custom_view"]').val(status);

            // Reload Table
            if ($.fn.DataTable.isDataTable('.table-ccx-leads')) {
                ccx_leads_table.ajax.reload();
            }
        });
    });
</script>
</body>

</html>