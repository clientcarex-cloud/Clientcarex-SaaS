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
                            <div class="col-md-6">
                                <h4 class="no-margin" style="font-weight: 600; font-size: 20px;">
                                    <i class="fa fa-tag" style="color: #6366f1; margin-right: 8px;"></i>
                                    <?php echo _l('ccx_msgs_promo_codes'); ?>
                                </h4>
                            </div>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-primary" onclick="openPromoModal()">
                                    <i class="fa fa-plus"></i> <?php echo _l('ccx_msgs_promo_add_new'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <?php render_datatable([
                            _l('ccx_msgs_promo_code'),
                            _l('ccx_msgs_promo_type'),
                            _l('ccx_msgs_promo_discount'),
                            _l('ccx_msgs_promo_usage'),
                            _l('ccx_msgs_promo_validity'),
                            _l('ccx_msgs_promo_status'),
                            _l('ccx_msgs_promo_actions'),
                        ], 'promo-codes'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Create/Edit Modal ═══ -->
<div class="modal fade" id="promoModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden; border: none; box-shadow: 0 20px 50px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; border-bottom: none; padding: 20px 28px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.8;">
                    <span>&times;</span>
                </button>
                <h4 class="modal-title" id="promoModalTitle" style="font-weight: 700;">
                    <i class="fa fa-tag" style="margin-right: 8px;"></i> <?php echo _l('ccx_msgs_promo_add_new'); ?>
                </h4>
            </div>
            <?php echo form_open(admin_url('ccx_msgs/promo_code_save'), ['id' => 'promoForm']); ?>
            <input type="hidden" name="id" id="promo_id" value="0">
            <div class="modal-body" style="padding: 28px;">
                <div class="row">
                    <!-- Code & Type -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="promo_code" class="form-control" required
                                   style="text-transform:uppercase; letter-spacing:2px; font-weight:700; font-size:15px;"
                                   placeholder="e.g. SUMMER25">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">Code Type</label>
                            <select name="code_type" id="promo_code_type" class="selectpicker" onchange="toggleReferralFields()">
                                <option value="promo">🏷️ Promo Code</option>
                                <option value="referral">🔗 Referral Code</option>
                            </select>
                        </div>
                    </div>

                    <!-- Discount Type & Value -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label">Discount Type</label>
                            <select name="discount_type" id="promo_discount_type" class="selectpicker" onchange="updateDiscountLabel()">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label" id="discountValueLabel">Discount (%) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-addon" id="discountValueAddon" style="font-weight:700;">%</span>
                                <input type="number" name="discount_value" id="promo_discount_value" class="form-control" step="0.01" min="0" required value="0" placeholder="e.g. 10 for 10% off">
                            </div>
                            <small class="text-muted" id="discountValueHint">Enter 10 to give 10% discount</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label">Max Discount Amount <small class="text-muted">(0 = no cap)</small></label>
                            <input type="number" name="max_discount_amount" id="promo_max_discount" class="form-control" step="0.01" min="0" value="0">
                        </div>
                    </div>

                    <!-- Min Order & Usage Limits -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label">Min Order Amount <small class="text-muted">(0 = none)</small></label>
                            <input type="number" name="min_order_amount" id="promo_min_order" class="form-control" step="0.01" min="0" value="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label">Total Usage Limit <small class="text-muted">(0 = unlimited)</small></label>
                            <input type="number" name="usage_limit" id="promo_usage_limit" class="form-control" min="0" value="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label">Per-Client Limit <small class="text-muted">(0 = unlimited)</small></label>
                            <input type="number" name="per_client_limit" id="promo_per_client" class="form-control" min="0" value="0">
                        </div>
                    </div>

                    <!-- Validity Dates -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">Valid From</label>
                            <input type="date" name="valid_from" id="promo_valid_from" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">Valid Until</label>
                            <input type="date" name="valid_until" id="promo_valid_until" class="form-control">
                        </div>
                    </div>

                    <!-- Applicable Channels -->
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="control-label">Applicable Channels</label>
                            <select name="applicable_channels[]" id="promo_channels" class="selectpicker" multiple data-actions-box="true">
                                <option value="all" selected>All Channels</option>
                                <option value="sms">SMS</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="email">Email</option>
                                <option value="aicall">AI Call</option>
                            </select>
                        </div>
                    </div>

                    <!-- Referral Fields (hidden by default) -->
                    <div id="referralFields" style="display:none;">
                        <div class="col-md-12">
                            <hr>
                            <h5 style="font-weight:700; color:#6366f1; margin-bottom:15px;">
                                <i class="fa fa-share-alt" style="margin-right:6px;"></i> Referral Settings
                            </h5>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">Referrer Type</label>
                                <select name="referrer_type" id="promo_referrer_type" class="selectpicker" onchange="toggleReferrerType()">
                                    <option value="client">👤 Client / Tenant</option>
                                    <option value="staff">🏢 Staff Member</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4" id="referrerClientWrap">
                            <div class="form-group">
                                <label class="control-label">Referrer Client</label>
                                <select name="referrer_client_id" id="promo_referrer_client" class="selectpicker" data-live-search="true" data-width="100%">
                                    <option value="">— Select Client —</option>
                                    <?php
                                    $clients = $this->db->select('userid, company')->order_by('company', 'asc')->get(db_prefix() . 'clients')->result();
                                    foreach ($clients as $cl) {
                                        echo '<option value="' . $cl->userid . '">' . htmlspecialchars($cl->company) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4" id="referrerStaffWrap" style="display:none;">
                            <div class="form-group">
                                <label class="control-label">Referrer Staff</label>
                                <select name="referrer_staff_id" id="promo_referrer_staff" class="selectpicker" data-live-search="true" data-width="100%">
                                    <option value="">— Select Staff —</option>
                                    <?php
                                    $staff = $this->db->select('staffid, firstname, lastname')->where('active', 1)->order_by('firstname', 'asc')->get(db_prefix() . 'staff')->result();
                                    foreach ($staff as $s) {
                                        echo '<option value="' . $s->staffid . '">' . htmlspecialchars($s->firstname . ' ' . $s->lastname) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">Reward Notes <small class="text-muted">(optional)</small></label>
                                <input type="text" name="referrer_reward_channel" id="promo_reward_channel" class="form-control" placeholder="e.g. 5% commission, ₹500 bonus">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="alert alert-info" style="background:#eff6ff; border-color:#bfdbfe; color:#1e40af; font-size:13px; margin-bottom:0;">
                                <i class="fa fa-info-circle" style="margin-right:6px;"></i>
                                <strong>Note:</strong> Referral rewards are recorded for tracking only. Actual compensation (incentives, commissions) will be handled separately.
                            </div>
                        </div>
                    </div>

                    <!-- Active & Notes -->
                    <div class="col-md-12"><hr></div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="active" id="promo_active" checked> Active
                            </label>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="control-label">Notes <small class="text-muted">(internal)</small></label>
                            <textarea name="notes" id="promo_notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #f1f5f9; padding: 16px 28px;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background:#6366f1; border-color:#6366f1;">
                    <i class="fa fa-check" style="margin-right:4px;"></i> Save
                </button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
$(function () {
    initDataTable('.table-promo-codes', admin_url + 'ccx_msgs/promo_codes_table', undefined, undefined, 'undefined', [0, 'asc']);
});

function openPromoModal(id) {
    // Reset form
    $('#promoForm')[0].reset();
    $('#promo_id').val(0);
    $('#promoModalTitle').html('<i class="fa fa-tag" style="margin-right:8px;"></i> <?php echo _l('ccx_msgs_promo_add_new'); ?>');
    $('#referralFields').hide();
    $('#promo_active').prop('checked', true);

    // Reset selectpickers
    $('#promo_code_type').val('promo');
    $('#promo_discount_type').val('percentage');
    $('#promo_channels').val(['all']);
    $('#promo_referrer_type').val('client');
    $('#promo_referrer_client, #promo_referrer_staff, #promo_reward_channel').val('');
    $('#referrerClientWrap').show();
    $('#referrerStaffWrap').hide();
    $('.selectpicker').selectpicker('refresh');
    updateDiscountLabel();

    $('#promoModal').modal('show');
}

function editPromoCode(id) {
    $.getJSON(admin_url + 'ccx_msgs/promo_code_get/' + id, function(data) {
        if (!data || !data.id) return;

        $('#promo_id').val(data.id);
        $('#promo_code').val(data.code);
        $('#promo_code_type').val(data.code_type);
        $('#promo_discount_type').val(data.discount_type);
        $('#promo_discount_value').val(data.discount_value);
        $('#promo_max_discount').val(data.max_discount_amount);
        $('#promo_min_order').val(data.min_order_amount);
        $('#promo_usage_limit').val(data.usage_limit);
        $('#promo_per_client').val(data.per_client_limit);
        $('#promo_valid_from').val(data.valid_from || '');
        $('#promo_valid_until').val(data.valid_until || '');
        $('#promo_notes').val(data.notes || '');
        $('#promo_active').prop('checked', data.active == 1);

        // Channels
        var channels = ['all'];
        try { channels = JSON.parse(data.applicable_channels); } catch(e) {}
        $('#promo_channels').val(channels);

        // Referral fields
        if (data.code_type === 'referral') {
            $('#referralFields').show();
            var rType = data.referrer_type || 'client';
            $('#promo_referrer_type').val(rType);
            if (rType === 'staff') {
                $('#referrerClientWrap').hide();
                $('#referrerStaffWrap').show();
                $('#promo_referrer_staff').val(data.referrer_staff_id || '');
                $('#promo_referrer_client').val('');
            } else {
                $('#referrerClientWrap').show();
                $('#referrerStaffWrap').hide();
                $('#promo_referrer_client').val(data.referrer_client_id || '');
                $('#promo_referrer_staff').val('');
            }
            $('#promo_reward_channel').val(data.referrer_reward_channel || '');
        } else {
            $('#referralFields').hide();
        }

        $('.selectpicker').selectpicker('refresh');
        updateDiscountLabel();

        $('#promoModalTitle').html('<i class="fa fa-pencil" style="margin-right:8px;"></i> <?php echo _l('ccx_msgs_promo_edit'); ?>');
        $('#promoModal').modal('show');
    });
}

function toggleReferralFields() {
    if ($('#promo_code_type').val() === 'referral') {
        $('#referralFields').slideDown(200);
    } else {
        $('#referralFields').slideUp(200);
    }
}

function toggleReferrerType() {
    var type = $('#promo_referrer_type').val();
    if (type === 'staff') {
        $('#referrerClientWrap').hide();
        $('#referrerStaffWrap').show();
        $('#promo_referrer_client').val('');
    } else {
        $('#referrerClientWrap').show();
        $('#referrerStaffWrap').hide();
        $('#promo_referrer_staff').val('');
    }
    $('.selectpicker').selectpicker('refresh');
}

function updateDiscountLabel() {
    var type = $('#promo_discount_type').val();
    if (type === 'percentage') {
        $('#discountValueLabel').html('Discount (%) <span class="text-danger">*</span>');
        $('#discountValueAddon').text('%');
        $('#promo_discount_value').attr('placeholder', 'e.g. 10 for 10% off');
        $('#discountValueHint').text('Enter 10 to give 10% discount');
    } else {
        $('#discountValueLabel').html('Discount Amount (₹) <span class="text-danger">*</span>');
        $('#discountValueAddon').text('₹');
        $('#promo_discount_value').attr('placeholder', 'e.g. 500');
        $('#discountValueHint').text('Enter the fixed discount amount');
    }
}
</script>
</body>
</html>
