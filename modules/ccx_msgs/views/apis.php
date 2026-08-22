<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">

                        <!-- Header Row -->
                        <div class="row" style="margin-bottom: 20px;">
                            <div class="col-md-6">
                                <h4 class="no-margin" style="font-weight: 600; font-size: 20px;">
                                    <i class="fa fa-plug" style="color: #03a9f4; margin-right: 8px;"></i>
                                    <?php echo _l('ccx_msgs_apis'); ?>
                                </h4>
                            </div>
                            <div class="col-md-6 text-right">
                                <?php if (has_permission('ccx_msgs', '', 'create') || is_admin()) { ?>
                                    <a href="#" class="btn btn-info" onclick="new_api(); return false;"
                                        style="border-radius: 20px; padding: 8px 20px;">
                                        <i class="fa fa-plus"></i>
                                        <?php echo _l('ccx_msgs_new_api'); ?>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>

                        <!-- Message Type Filter Cards -->
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-12">
                                <div class="btn-group msg-type-filters" style="width: 100%; display: flex;">
                                    <button type="button" class="btn msg-type-btn active" data-type="" style="flex: 1;">
                                        <i class="fa fa-th-large"></i>
                                        <span class="msg-type-label">All Types</span>
                                    </button>
                                    <button type="button" class="btn msg-type-btn" data-type="sms" style="flex: 1;">
                                        <i class="fa fa-comment"></i>
                                        <span class="msg-type-label">
                                            <?php echo _l('ccx_msgs_sms'); ?>
                                        </span>
                                    </button>
                                    <button type="button" class="btn msg-type-btn" data-type="whatsapp"
                                        style="flex: 1;">
                                        <i class="fab fa-whatsapp"></i>
                                        <span class="msg-type-label">
                                            <?php echo _l('ccx_msgs_whatsapp'); ?>
                                        </span>
                                    </button>
                                    <button type="button" class="btn msg-type-btn" data-type="email" style="flex: 1;">
                                        <i class="fa fa-envelope"></i>
                                        <span class="msg-type-label">
                                            <?php echo _l('ccx_msgs_email'); ?>
                                        </span>
                                    </button>
                                    <button type="button" class="btn msg-type-btn" data-type="aicall" style="flex: 1;">
                                        <i class="fa fa-phone"></i>
                                        <span class="msg-type-label">
                                            <?php echo _l('ccx_msgs_aicall'); ?>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Sub-type Filter -->
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-12">
                                <div class="subtype-wrap">
                                    <span class="subtype-label"><i class="fa fa-filter"></i> Sub-type:</span>
                                    <div class="btn-group subtype-filters">
                                        <button type="button" class="btn btn-sm subtype-btn active" data-subtype="">
                                            All
                                        </button>
                                        <button type="button" class="btn btn-sm subtype-btn" data-subtype="promotional">
                                            <i class="fa fa-bullhorn"></i>
                                            <?php echo _l('ccx_msgs_promo'); ?>
                                        </button>
                                        <button type="button" class="btn btn-sm subtype-btn"
                                            data-subtype="transactional">
                                            <i class="fa fa-exchange"></i>
                                            <?php echo _l('ccx_msgs_trans'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Scope Filter -->
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-12">
                                <div class="subtype-wrap">
                                    <span class="subtype-label"><i class="fa fa-sitemap"></i> Scope:</span>
                                    <div class="btn-group subtype-filters scope-filters">
                                        <button type="button" class="btn btn-sm subtype-btn scope-btn active"
                                            data-scope="">
                                            All
                                        </button>
                                        <button type="button" class="btn btn-sm subtype-btn scope-btn"
                                            data-scope="global">
                                            <i class="fa fa-globe"></i> Global
                                        </button>
                                        <button type="button" class="btn btn-sm subtype-btn scope-btn"
                                            data-scope="client">
                                            <i class="fa fa-user"></i> Client
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden filter values -->
                        <input type="hidden" name="message_type_filter" value="">
                        <input type="hidden" name="message_subtype_filter" value="">
                        <input type="hidden" name="api_scope_filter" value="">

                        <!-- DataTable -->
                        <?php render_datatable([
                            _l('ccx_msgs_api_name'),
                            _l('plan_message_type'),
                            _l('ccx_msgs_subtype'),
                            'Scope',
                            _l('ccx_msgs_api_url'),
                            _l('ccx_msgs_api_method'),
                            _l('ccx_msgs_api_auth'),
                            _l('is_active'),
                            _l('ccx_msgs_is_default'),
                        ], 'apis', [], [
                            'data-last-column-hidden' => 0,
                        ]); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="api_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" id="api_modal_content">
            <!-- Modal content will be loaded via AJAX -->
        </div>
    </div>
</div>

<?php init_tail(); ?>

<style>
    /* Message Type Filter Buttons */
    .msg-type-filters {
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #e0e6ed;
        background: #f8f9fa;
    }

    .msg-type-btn {
        background: #fff;
        border: none !important;
        border-right: 1px solid #e0e6ed !important;
        color: #6c757d;
        padding: 14px 10px;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.25s ease;
    }

    .msg-type-btn:last-child {
        border-right: none !important;
    }

    .msg-type-btn i {
        display: block;
        font-size: 20px;
        margin-bottom: 5px;
    }

    .msg-type-btn .msg-type-label {
        display: block;
        font-size: 12px;
    }

    .msg-type-btn:hover {
        background: #eef5fc;
        color: #03a9f4;
    }

    .msg-type-btn.active {
        background: linear-gradient(135deg, #03a9f4, #0288d1);
        color: #fff !important;
        box-shadow: 0 2px 8px rgba(3, 169, 244, 0.3);
    }

    .msg-type-btn[data-type="whatsapp"].active {
        background: linear-gradient(135deg, #25d366, #128c7e);
        box-shadow: 0 2px 8px rgba(37, 211, 102, 0.3);
    }

    .msg-type-btn[data-type="email"].active {
        background: linear-gradient(135deg, #ff9800, #f57c00);
        box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
    }

    .msg-type-btn[data-type="aicall"].active {
        background: linear-gradient(135deg, #9c27b0, #7b1fa2);
        box-shadow: 0 2px 8px rgba(156, 39, 176, 0.3);
    }

    /* Sub-type Filter */
    .subtype-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e0e6ed;
    }

    .subtype-label {
        font-weight: 600;
        color: #555;
        font-size: 13px;
        white-space: nowrap;
    }

    .subtype-label i {
        color: #03a9f4;
        margin-right: 4px;
    }

    .subtype-filters {
        display: flex;
        gap: 6px;
    }

    .subtype-btn {
        background: #fff;
        border: 1px solid #dee2e6 !important;
        color: #6c757d;
        border-radius: 20px !important;
        padding: 6px 16px;
        font-size: 12px;
        font-weight: 500;
        transition: all 0.25s ease;
    }

    .subtype-btn:hover {
        background: #eef5fc;
        color: #03a9f4;
        border-color: #03a9f4 !important;
    }

    .subtype-btn.active {
        background: #03a9f4;
        color: #fff !important;
        border-color: #03a9f4 !important;
        box-shadow: 0 2px 6px rgba(3, 169, 244, 0.25);
    }

    .api-url-cell {
        max-width: 250px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .badge-method {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .badge-method-get {
        background: #e3f2fd;
        color: #1565c0;
    }

    .badge-method-post {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .badge-method-put {
        background: #fff3e0;
        color: #ef6c00;
    }

    .badge-method-delete {
        background: #fce4ec;
        color: #c62828;
    }
</style>

<script>
    var ServerParams = {
        "message_type": '[name="message_type_filter"]',
        "message_subtype": '[name="message_subtype_filter"]',
        "api_scope": '[name="api_scope_filter"]'
    };

    $(function () {
        initDataTable('.table-apis', admin_url + 'ccx_msgs/apis_table', undefined, undefined, ServerParams, [0, 'asc']);

        // Message Type filter click
        $('.msg-type-btn').on('click', function () {
            $('.msg-type-btn').removeClass('active');
            $(this).addClass('active');
            $('input[name="message_type_filter"]').val($(this).data('type'));
            $('.table-apis').DataTable().ajax.reload();
        });

        // Sub-type filter click
        $('.subtype-btn:not(.scope-btn)').on('click', function () {
            $('.subtype-btn:not(.scope-btn)').removeClass('active');
            $(this).addClass('active');
            $('input[name="message_subtype_filter"]').val($(this).data('subtype'));
            $('.table-apis').DataTable().ajax.reload();
        });

        // Scope filter click
        $('.scope-btn').on('click', function () {
            $('.scope-btn').removeClass('active');
            $(this).addClass('active');
            $('input[name="api_scope_filter"]').val($(this).data('scope'));
            $('.table-apis').DataTable().ajax.reload();
        });
    });

    function new_api() {
        requestGet('ccx_msgs/get_api_modal').done(function (response) {
            $('#api_modal_content').html(response);
            $('#api_modal').modal('show');
            init_api_modal();
        }).fail(function (data) {
            alert_float('danger', 'Failed to load modal');
        });
    }

    function edit_api(id) {
        requestGet('ccx_msgs/get_api_modal/' + id).done(function (response) {
            $('#api_modal_content').html(response);
            $('#api_modal').modal('show');
            init_api_modal();
        }).fail(function (data) {
            alert_float('danger', 'Failed to load modal');
        });
    }

    function test_api(id) {
        if (!confirm('Are you sure you want to test-trigger this API?')) return;
        var btn = $('[data-test-id="' + id + '"]');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.post(admin_url + 'ccx_msgs/test_api/' + id).done(function (response) {
            var res = JSON.parse(response);
            if (res.status === 'success') {
                alert_float('success', 'API call successful! HTTP ' + res.response_code + ' (' + res.execution_time_ms + 'ms)');
            } else {
                alert_float('danger', 'API call ' + res.status + '! HTTP ' + res.response_code);
            }
            $('.table-apis').DataTable().ajax.reload(null, false);
        }).fail(function () {
            alert_float('danger', 'Failed to trigger API.');
        }).always(function () {
            btn.prop('disabled', false).html('<i class="fa fa-play"></i>');
        });
    }

    function set_default(id) {
        if (!confirm('Set this API as the default for its message type?')) return;
        $.post(admin_url + 'ccx_msgs/toggle_default/' + id).done(function (response) {
            var res = (typeof response === 'string') ? JSON.parse(response) : response;
            if (res.success) {
                alert_float('success', res.message);
                $('.table-apis').DataTable().ajax.reload(null, false);
            } else {
                alert_float('danger', res.message || 'Failed to update default.');
            }
        }).fail(function () {
            alert_float('danger', 'Request failed.');
        });
    }

    function init_api_modal() {
        $('#api_modal .selectpicker').selectpicker('render');
        // Base validation — the modal's own toggleSmtpFields() will adjust per type
        appValidateForm($('#api-form'), {
            message_type: 'required',
            message_subtype: 'required',
            api_name: 'required'
        });
    }
</script>
</body>

</html>