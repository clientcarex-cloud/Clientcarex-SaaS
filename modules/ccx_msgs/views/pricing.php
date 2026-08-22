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
                                    <i class="fa fa-tags" style="color: #03a9f4; margin-right: 8px;"></i>
                                    <?php echo _l('ccx_msgs_pricing'); ?>
                                </h4>
                            </div>
                            <div class="col-md-6 text-right">
                                <?php if (has_permission('ccx_msgs', '', 'create') || is_admin()) { ?>
                                    <a href="#" class="btn btn-info" onclick="new_plan(); return false;" style="border-radius: 20px; padding: 8px 20px;">
                                        <i class="fa fa-plus"></i> <?php echo _l('new_pricing_plan'); ?>
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
                                        <span class="msg-type-label"><?php echo _l('ccx_msgs_sms'); ?></span>
                                    </button>
                                    <button type="button" class="btn msg-type-btn" data-type="whatsapp" style="flex: 1;">
                                        <i class="fab fa-whatsapp"></i>
                                        <span class="msg-type-label"><?php echo _l('ccx_msgs_whatsapp'); ?></span>
                                    </button>
                                    <button type="button" class="btn msg-type-btn" data-type="email" style="flex: 1;">
                                        <i class="fa fa-envelope"></i>
                                        <span class="msg-type-label"><?php echo _l('ccx_msgs_email'); ?></span>
                                    </button>
                                    <button type="button" class="btn msg-type-btn" data-type="aicall" style="flex: 1;">
                                        <i class="fa fa-phone"></i>
                                        <span class="msg-type-label"><?php echo _l('ccx_msgs_aicall'); ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Billing Cycle Filter -->
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-12">
                                <div class="billing-cycle-wrap">
                                    <span class="billing-cycle-label"><i class="fa fa-calendar"></i> Billing Cycle:</span>
                                    <div class="btn-group billing-cycle-filters">
                                        <button type="button" class="btn btn-sm billing-cycle-btn active" data-cycle="monthly">
                                            <i class="fa fa-calendar-o"></i> <?php echo _l('billing_monthly'); ?>
                                        </button>
                                        <button type="button" class="btn btn-sm billing-cycle-btn" data-cycle="quarterly">
                                            <i class="fa fa-calendar"></i> <?php echo _l('billing_quarterly'); ?>
                                        </button>
                                        <button type="button" class="btn btn-sm billing-cycle-btn" data-cycle="yearly">
                                            <i class="fa fa-calendar-check-o"></i> <?php echo _l('billing_yearly'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Message Subtype Filter -->
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-12">
                                <div class="billing-cycle-wrap">
                                    <span class="billing-cycle-label"><i class="fa fa-tag" style="color: #f59e0b;"></i> Sub-type:</span>
                                    <div class="btn-group subtype-filters">
                                        <button type="button" class="btn btn-sm subtype-btn active" data-subtype="">
                                            <i class="fa fa-th-large"></i> All
                                        </button>
                                        <button type="button" class="btn btn-sm subtype-btn" data-subtype="promotional">
                                            <i class="fa fa-bullhorn"></i> <?php echo _l('ccx_msgs_promo'); ?>
                                        </button>
                                        <button type="button" class="btn btn-sm subtype-btn" data-subtype="transactional">
                                            <i class="fa fa-exchange"></i> <?php echo _l('ccx_msgs_trans'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden filter values -->
                        <input type="hidden" name="billing_cycle_filter" value="monthly">
                        <input type="hidden" name="message_type_filter" value="">
                        <input type="hidden" name="message_subtype_filter" value="">

                        <!-- DataTable -->
                        <?php render_datatable([
                            _l('plan_name'),
                            _l('plan_message_type'),
                            _l('plan_subtype'),
                            _l('cost_per_message'),
                            _l('plan_message_count'),
                            _l('plan_expiry_days'),
                            _l('plan_discount_percent'),
                        ], 'pricing'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="plan_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content" id="plan_modal_content">
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
        position: relative;
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
    .msg-type-btn.active:hover {
        background: linear-gradient(135deg, #0288d1, #01579b);
    }
    /* WhatsApp green when active */
    .msg-type-btn[data-type="whatsapp"].active {
        background: linear-gradient(135deg, #25d366, #128c7e);
        box-shadow: 0 2px 8px rgba(37, 211, 102, 0.3);
    }
    /* Email orange when active */
    .msg-type-btn[data-type="email"].active {
        background: linear-gradient(135deg, #ff9800, #f57c00);
        box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
    }
    /* AI Call purple when active */
    .msg-type-btn[data-type="aicall"].active {
        background: linear-gradient(135deg, #9c27b0, #7b1fa2);
        box-shadow: 0 2px 8px rgba(156, 39, 176, 0.3);
    }

    /* Billing Cycle Filter */
    .billing-cycle-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e0e6ed;
    }
    .billing-cycle-label {
        font-weight: 600;
        color: #555;
        font-size: 13px;
        white-space: nowrap;
    }
    .billing-cycle-label i {
        color: #03a9f4;
        margin-right: 4px;
    }
    .billing-cycle-filters {
        display: flex;
        gap: 6px;
    }
    .billing-cycle-btn {
        background: #fff;
        border: 1px solid #dee2e6 !important;
        color: #6c757d;
        border-radius: 20px !important;
        padding: 6px 16px;
        font-size: 12px;
        font-weight: 500;
        transition: all 0.25s ease;
    }
    .billing-cycle-btn:hover {
        background: #eef5fc;
        color: #03a9f4;
        border-color: #03a9f4 !important;
    }
    .billing-cycle-btn.active {
        background: #03a9f4;
        color: #fff !important;
        border-color: #03a9f4 !important;
        box-shadow: 0 2px 6px rgba(3, 169, 244, 0.25);
    }

    /* Subtype Filter */
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
        background: #fff8eb;
        color: #f59e0b;
        border-color: #f59e0b !important;
    }
    .subtype-btn.active {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #fff !important;
        border-color: #f59e0b !important;
        box-shadow: 0 2px 6px rgba(245, 158, 11, 0.25);
    }
    .subtype-btn[data-subtype="transactional"].active {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        border-color: #6366f1 !important;
        box-shadow: 0 2px 6px rgba(99, 102, 241, 0.25);
    }

    /* DataTable Enhancements */
    .table-pricing {
        margin-top: 10px;
    }
</style>

<script>
    var ServerParams = {
        "billing_cycle": '[name="billing_cycle_filter"]',
        "message_type": '[name="message_type_filter"]',
        "message_subtype": '[name="message_subtype_filter"]'
    };

    $(function () {
        initDataTable('.table-pricing', admin_url + 'ccx_msgs/pricing_table', undefined, undefined, ServerParams, [0, 'asc']);

        // Message Type filter click
        $('.msg-type-btn').on('click', function () {
            $('.msg-type-btn').removeClass('active');
            $(this).addClass('active');
            $('input[name="message_type_filter"]').val($(this).data('type'));
            $('.table-pricing').DataTable().ajax.reload();
        });

        // Billing Cycle filter click
        $('.billing-cycle-btn').on('click', function () {
            $('.billing-cycle-btn').removeClass('active');
            $(this).addClass('active');
            $('input[name="billing_cycle_filter"]').val($(this).data('cycle'));
            $('.table-pricing').DataTable().ajax.reload();
        });

        // Message Subtype filter click
        $('.subtype-btn').on('click', function () {
            $('.subtype-btn').removeClass('active');
            $(this).addClass('active');
            $('input[name="message_subtype_filter"]').val($(this).data('subtype'));
            $('.table-pricing').DataTable().ajax.reload();
        });
    });

    function new_plan() {
        requestGet('ccx_msgs/get_plan_modal').done(function (response) {
            $('#plan_modal_content').html(response);
            $('#plan_modal').modal('show');
            init_plan_modal();
        }).fail(function (data) {
            alert_float('danger', 'Failed to load modal');
        });
    }

    function edit_plan(id) {
        requestGet('ccx_msgs/get_plan_modal/' + id).done(function (response) {
            $('#plan_modal_content').html(response);
            $('#plan_modal').modal('show');
            init_plan_modal();
        }).fail(function (data) {
            alert_float('danger', 'Failed to load modal');
        });
    }

    function duplicate_plan(id) {
        requestGet('ccx_msgs/get_plan_modal/' + id).done(function (response) {
            $('#plan_modal_content').html(response);
            // Clear the hidden ID so the form inserts a new record
            $('#plan_modal_content').find('input[name="id"]').val('');
            // Append (Copy) to the plan name
            var nameField = $('#plan_modal_content').find('#plan_name');
            var currentName = nameField.val();
            if (currentName && currentName.indexOf('(Copy)') === -1) {
                nameField.val(currentName + ' (Copy)');
            }
            // Update modal title
            $('#plan_modal .modal-title').html('<?php echo _l('new_pricing_plan'); ?> <small class="text-muted">(Duplicated)</small>');
            $('#plan_modal').modal('show');
            init_plan_modal();
        }).fail(function (data) {
            alert_float('danger', 'Failed to load modal');
        });
    }

    function init_plan_modal() {
        $('#plan_modal .selectpicker').selectpicker('render');
        appValidateForm($('#plan-form'), {
            message_type: 'required',
            plan_name: 'required',
            price: 'required',
            message_count: 'required',
            expiry_days: 'required'
        });

        // Generate button hover effect
        $('#btn_generate_desc').hover(
            function () { $(this).css({ 'background': 'linear-gradient(135deg, #7c3aed, #6d28d9)', 'color': '#fff', 'border-color': '#7c3aed' }); },
            function () { $(this).css({ 'background': 'linear-gradient(135deg, #f5f3ff, #ede9fe)', 'color': '#7c3aed', 'border-color': '#d0d5dd' }); }
        );
    }

    // Tax dropdown change handler — sync the hidden tax_percent field
    function onTaxChange(el) {
        var selectedOption = $(el).find('option:selected');
        var rate = parseFloat(selectedOption.data('taxrate')) || 0;
        $('#tax_percent').val(rate.toFixed(2));
        $('#tax_percent_hidden').val(rate.toFixed(2));
    }

    function generateSmartDescription() {
        var price        = parseFloat($('#price').val()) || 0;
        var msgCount     = parseInt($('#message_count').val()) || 0;
        var expiryDays   = parseInt($('#expiry_days').val()) || 0;
        var discountPct  = parseFloat($('#discount_percent').val()) || 0;
        var taxPct       = parseFloat($('#tax_percent_hidden').val()) || 0;
        var msgType      = $('#message_type').val() || '';
        var planName     = $('#plan_name').val() || '';

        // Tax name from dropdown
        var taxName = '';
        if ($('#tax_id').length && $('#tax_id').val() != '0') {
            taxName = $('#tax_id option:selected').text().trim();
        }

        // Validate minimum fields
        if (price <= 0 || msgCount <= 0) {
            alert_float('warning', 'Please enter valid Price and Message Count first.');
            return;
        }

        // Calculations
        var discountAmount  = (price * discountPct) / 100;
        var priceAfterDisc  = price - discountAmount;

        // GST is applied on price after discount
        var taxAmount       = (priceAfterDisc * taxPct) / 100;
        var totalPayable    = priceAfterDisc + taxAmount;
        var costPerMsg      = msgCount > 0 ? totalPayable / msgCount : 0;

        // Currency formatting — use selected currency from dropdown
        var currency = '₹';
        if ($('#currency_id').length && $('#currency_id').val()) {
            var selOpt = $('#currency_id option:selected');
            if (selOpt.data('symbol')) {
                currency = selOpt.data('symbol');
            }
        } else if (typeof app !== 'undefined' && app.options && app.options.currency_symbol) {
            currency = app.options.currency_symbol;
        }

        // Format numbers
        function fmt(num, decimals) {
            decimals = decimals !== undefined ? decimals : 2;
            return num.toFixed(decimals);
        }

        // Expiry label
        var expiryLabel = '';
        if (expiryDays >= 365) {
            var yrs = Math.floor(expiryDays / 365);
            var remDays = expiryDays % 365;
            expiryLabel = yrs + (yrs > 1 ? ' Years' : ' Year');
            if (remDays > 0) expiryLabel += ' ' + remDays + ' Days';
        } else if (expiryDays >= 30) {
            var months = Math.floor(expiryDays / 30);
            var remDays2 = expiryDays % 30;
            expiryLabel = months + (months > 1 ? ' Months' : ' Month');
            if (remDays2 > 0) expiryLabel += ' ' + remDays2 + ' Days';
        } else {
            expiryLabel = expiryDays + ' Days';
        }

        // Build description lines
        var lines = [];

        if (discountPct > 0) {
            lines.push('🎁 Discount: ' + fmt(discountPct) + '% OFF');
            lines.push('💸 You Save: ' + currency + fmt(discountAmount));
            lines.push('✅ Price After Discount: ' + currency + fmt(priceAfterDisc));
        } else {
            lines.push('✅ Base Price: ' + currency + fmt(price));
        }

        // Cost per message (before GST)
        var costPerMsgPreTax = msgCount > 0 ? priceAfterDisc / msgCount : 0;
        lines.push('⚡ Cost Per Message: ' + currency + fmt(costPerMsgPreTax));

        if (taxPct > 0) {
            var taxLabel = taxName ? taxName : 'GST';
            lines.push('🧾 ' + taxLabel + ' (' + fmt(taxPct) + '%): +' + currency + fmt(taxAmount));
            lines.push('💰 Total Payable: ' + currency + fmt(totalPayable));
        }

        lines.push('');
        lines.push('📅 Validity: ' + expiryLabel + ' (' + expiryDays + ' days)');

        $('#offer_description').val(lines.join('\n'));
        alert_float('success', 'Description generated successfully!');
    }
</script>
</body>

</html>