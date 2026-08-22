<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">

                        <!-- Header -->
                        <div class="row" style="margin-bottom: 20px;">
                            <div class="col-md-8">
                                <h4 class="no-margin" style="font-weight: 600; font-size: 20px;">
                                    <i class="fa fa-list-alt" style="color: #03a9f4; margin-right: 8px;"></i>
                                    <?php echo _l('ccx_msgs_api_logs'); ?>
                                    <?php if (isset($api) && $api) { ?>
                                        <small style="color: #888; font-size: 14px; margin-left: 10px;">
                                            &mdash;
                                            <?php echo htmlspecialchars($api->api_name); ?>
                                        </small>
                                    <?php } ?>
                                </h4>
                            </div>
                            <div class="col-md-4 text-right">
                                <a href="<?php echo admin_url('ccx_msgs/apis'); ?>" class="btn btn-default"
                                    style="border-radius: 20px; padding: 8px 20px;">
                                    <i class="fa fa-arrow-left"></i>
                                    <?php echo _l('ccx_msgs_back_to_apis'); ?>
                                </a>
                            </div>
                        </div>

                        <!-- Auto-delete Info Banner -->
                        <div class="ccx-logs-info-banner">
                            <i class="fa fa-clock-o"></i>
                            <span>API logs are automatically deleted after <strong>48 hours</strong>.</span>
                        </div>

                        <!-- Status Filter -->
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-12">
                                <div class="status-wrap">
                                    <span class="status-label"><i class="fa fa-filter"></i> Status:</span>
                                    <div class="btn-group status-filters">
                                        <button type="button" class="btn btn-sm status-btn active" data-status="">
                                            All
                                        </button>
                                        <button type="button" class="btn btn-sm status-btn" data-status="success">
                                            <i class="fa fa-check-circle"></i> Success
                                        </button>
                                        <button type="button" class="btn btn-sm status-btn" data-status="failed">
                                            <i class="fa fa-times-circle"></i> Failed
                                        </button>
                                        <button type="button" class="btn btn-sm status-btn" data-status="timeout">
                                            <i class="fa fa-clock-o"></i> Timeout
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden filter values -->
                        <input type="hidden" name="api_id_filter"
                            value="<?php echo isset($api_id) && is_numeric($api_id) ? $api_id : ''; ?>">
                        <input type="hidden" name="status_filter" value="">

                        <!-- Bulk Actions Bar -->
                        <?php if (has_permission('ccx_msgs', '', 'delete') || is_admin()) { ?>
                            <div class="bulk-actions-bar" id="bulk_actions_bar" style="display:none;">
                                <span class="bulk-count"><strong id="selected_count">0</strong> selected</span>
                                <button type="button" class="btn btn-danger btn-sm" onclick="bulk_delete_logs()"
                                    style="border-radius: 16px;">
                                    <i class="fa fa-trash"></i> Delete Selected
                                </button>
                                <button type="button" class="btn btn-default btn-sm" onclick="clear_selection()"
                                    style="border-radius: 16px;">
                                    <i class="fa fa-times"></i> Clear
                                </button>
                            </div>
                        <?php } ?>

                        <!-- DataTable -->
                        <?php render_datatable([
                            '<input type="checkbox" id="select_all_logs">',
                            _l('ccx_msgs_api_name'),
                            'Tenant',
                            _l('ccx_msgs_log_status'),
                            _l('ccx_msgs_log_response_code'),
                            _l('ccx_msgs_log_triggered_by'),
                            _l('ccx_msgs_log_exec_time'),
                            _l('ccx_msgs_log_date'),
                        ], 'api-logs'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Log Detail Modal -->
<div class="modal fade" id="log_detail_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"><i class="fa fa-file-text-o"></i>
                    <?php echo _l('ccx_msgs_log_detail'); ?>
                </h4>
            </div>
            <div class="modal-body" id="log_detail_body">
                <!-- Loaded via JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <?php echo _l('close'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<style>
    .status-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e0e6ed;
    }

    .status-label {
        font-weight: 600;
        color: #555;
        font-size: 13px;
        white-space: nowrap;
    }

    .status-label i {
        color: #03a9f4;
        margin-right: 4px;
    }

    .status-filters {
        display: flex;
        gap: 6px;
    }

    .status-btn {
        background: #fff;
        border: 1px solid #dee2e6 !important;
        color: #6c757d;
        border-radius: 20px !important;
        padding: 6px 16px;
        font-size: 12px;
        font-weight: 500;
        transition: all 0.25s ease;
    }

    .status-btn:hover {
        background: #eef5fc;
        color: #03a9f4;
        border-color: #03a9f4 !important;
    }

    .status-btn.active {
        background: #03a9f4;
        color: #fff !important;
        border-color: #03a9f4 !important;
        box-shadow: 0 2px 6px rgba(3, 169, 244, 0.25);
    }

    .bulk-actions-bar {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 15px;
        margin-bottom: 10px;
        background: linear-gradient(135deg, #fff3e0, #ffe0b2);
        border: 1px solid #ffcc80;
        border-radius: 8px;
        animation: slideDown 0.2s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .bulk-count {
        font-size: 13px;
        color: #555;
    }

    .log-badge-success {
        background: #e8f5e9;
        color: #2e7d32;
        padding: 4px 12px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 11px;
    }

    .log-badge-failed {
        background: #fce4ec;
        color: #c62828;
        padding: 4px 12px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 11px;
    }

    .log-badge-timeout {
        background: #fff3e0;
        color: #ef6c00;
        padding: 4px 12px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 11px;
    }

    .log-payload-block {
        background: #1e1e2e;
        color: #a6e3a1;
        padding: 12px;
        border-radius: 6px;
        font-family: 'Courier New', monospace;
        font-size: 12px;
        max-height: 300px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-all;
    }

    .ccx-logs-info-banner {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 18px;
        margin-bottom: 15px;
        background: linear-gradient(135deg, #e3f2fd, #bbdefb);
        border: 1px solid #90caf9;
        border-left: 4px solid #1e88e5;
        border-radius: 8px;
        color: #1565c0;
        font-size: 13px;
        font-weight: 500;
    }

    .ccx-logs-info-banner i {
        font-size: 16px;
        color: #1e88e5;
    }
</style>

<script>
    var ServerParams = {
        "api_id": '[name="api_id_filter"]',
        "status": '[name="status_filter"]'
    };

    $(function () {
        initDataTable('.table-api-logs', admin_url + 'ccx_msgs/api_logs_table', [0], [0], ServerParams, [7, 'desc']);

        // Status filter click
        $('.status-btn').on('click', function () {
            $('.status-btn').removeClass('active');
            $(this).addClass('active');
            $('input[name="status_filter"]').val($(this).data('status'));
            $('.table-api-logs').DataTable().ajax.reload();
        });
        // Select All checkbox
        $(document).on('change', '#select_all_logs', function () {
            $('.log-checkbox').prop('checked', $(this).is(':checked'));
            update_bulk_bar();
        });

        // Individual checkbox change
        $(document).on('change', '.log-checkbox', function () {
            update_bulk_bar();
        });
    });

    function update_bulk_bar() {
        var count = $('.log-checkbox:checked').length;
        $('#selected_count').text(count);
        if (count > 0) {
            $('#bulk_actions_bar').slideDown(200);
        } else {
            $('#bulk_actions_bar').slideUp(200);
        }
    }

    function clear_selection() {
        $('.log-checkbox, #select_all_logs').prop('checked', false);
        update_bulk_bar();
    }

    function bulk_delete_logs() {
        var ids = [];
        $('.log-checkbox:checked').each(function () {
            ids.push($(this).val());
        });
        if (ids.length === 0) return;
        if (!confirm('Are you sure you want to delete ' + ids.length + ' log(s)?')) return;

        $.post(admin_url + 'ccx_msgs/bulk_delete_api_logs', { ids: ids }, function (response) {
            var res = JSON.parse(response);
            if (res.success) {
                alert_float('success', res.message);
                $('.table-api-logs').DataTable().ajax.reload(null, false);
                clear_selection();
            } else {
                alert_float('danger', res.message);
            }
        }).fail(function () {
            alert_float('danger', 'Failed to delete logs.');
        });
    }

    function view_log_detail(logId) {
        $.getJSON(admin_url + 'ccx_msgs/get_log_detail/' + logId, function (res) {
            if (!res.success) {
                alert_float('danger', res.message || 'Log not found.');
                return;
            }

            var statusBadge = '<span class="log-badge-' + res.status + '">' + res.status.toUpperCase() + '</span>';

            var html = '<div class="row">';
            html += '<div class="col-md-6"><p><strong>API:</strong> ' + escapeHtml(res.api_name) + '</p></div>';
            html += '<div class="col-md-3"><p><strong>Status:</strong> ' + statusBadge + '</p></div>';
            html += '<div class="col-md-3"><p><strong>HTTP:</strong> ' + res.response_code + ' &bull; ' + res.execution_time_ms + 'ms</p></div>';
            html += '</div>';
            html += '<hr>';
            html += '<p><strong>URL:</strong> <code>' + escapeHtml(res.request_url || '') + '</code></p>';
            html += '<p><strong>Date:</strong> ' + escapeHtml(res.created_at || '') + '</p>';
            html += '<hr>';
            html += '<p><strong>Request Payload:</strong></p>';
            html += '<div class="log-payload-block">' + formatJson(res.request_payload) + '</div>';
            html += '<br>';
            html += '<p><strong>Response Body:</strong></p>';
            html += '<div class="log-payload-block">' + formatJson(res.response_body) + '</div>';

            $('#log_detail_body').html(html);
            $('#log_detail_modal').modal('show');
        }).fail(function () {
            alert_float('danger', 'Failed to load log details.');
        });
    }

    function formatJson(str) {
        if (!str || str === '') return '<em class="text-muted">Empty</em>';
        try {
            return escapeHtml(JSON.stringify(JSON.parse(str), null, 2));
        } catch (e) {
            return escapeHtml(str);
        }
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(text)));
        return div.innerHTML;
    }
</script>
</body>

</html>