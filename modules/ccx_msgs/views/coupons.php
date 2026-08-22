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
                                    <i class="fa fa-ticket" style="color: #6366f1; margin-right: 8px;"></i>
                                    <?php echo _l('ccx_msgs_coupons'); ?>
                                    <small style="font-size: 12px; color: #94a3b8; font-weight: 400; margin-left: 8px;">Award free credits to tenants</small>
                                </h4>
                            </div>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-primary" onclick="openCouponModal()">
                                    <i class="fa fa-plus"></i> <?php echo _l('ccx_msgs_coupon_add_new'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <?php render_datatable([
                            _l('ccx_msgs_coupon_code'),
                            _l('ccx_msgs_coupon_description'),
                            _l('ccx_msgs_coupon_credits'),
                            _l('ccx_msgs_coupon_usage'),
                            _l('ccx_msgs_coupon_validity'),
                            _l('ccx_msgs_coupon_status'),
                            _l('ccx_msgs_coupon_actions'),
                        ], 'coupons'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Create/Edit Modal ═══ -->
<div class="modal fade" id="couponModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden; border: none; box-shadow: 0 20px 50px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; border-bottom: none; padding: 20px 28px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.8;">
                    <span>&times;</span>
                </button>
                <h4 class="modal-title" id="couponModalTitle" style="font-weight: 700;">
                    <i class="fa fa-ticket" style="margin-right: 8px;"></i> <?php echo _l('ccx_msgs_coupon_add_new'); ?>
                </h4>
            </div>
            <?php echo form_open(admin_url('ccx_msgs/coupon_save'), ['id' => 'couponForm']); ?>
            <input type="hidden" name="id" id="coupon_id" value="0">
            <div class="modal-body" style="padding: 28px;">
                <div class="row">
                    <!-- Code & Description -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">Coupon Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="coupon_code" class="form-control" required
                                   style="text-transform:uppercase; letter-spacing:2px; font-weight:700; font-size:15px;"
                                   placeholder="e.g. FREE100SMS">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">Description <small class="text-muted">(shown to tenant)</small></label>
                            <input type="text" name="description" id="coupon_description" class="form-control"
                                   placeholder="e.g. Welcome bonus! 100 free SMS credits">
                        </div>
                    </div>

                    <!-- Credits per Channel -->
                    <div class="col-md-12">
                        <hr style="margin: 10px 0 18px;">
                        <h5 style="font-weight:700; color:#6366f1; margin-bottom:15px; font-size:14px;">
                            <i class="fa fa-gift" style="margin-right:6px;"></i> Credits to Award
                        </h5>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label">
                                <i class="fa fa-comment" style="color:#4f46e5; margin-right:4px;"></i> SMS
                            </label>
                            <input type="number" name="credit_sms" id="credit_sms" class="form-control" min="0" value="0" placeholder="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label">
                                <i class="fab fa-whatsapp" style="color:#16a34a; margin-right:4px;"></i> WhatsApp
                            </label>
                            <input type="number" name="credit_whatsapp" id="credit_whatsapp" class="form-control" min="0" value="0" placeholder="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label">
                                <i class="fa fa-envelope" style="color:#d97706; margin-right:4px;"></i> Email
                            </label>
                            <input type="number" name="credit_email" id="credit_email" class="form-control" min="0" value="0" placeholder="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label">
                                <i class="fa fa-phone" style="color:#7c3aed; margin-right:4px;"></i> AI Call
                            </label>
                            <input type="number" name="credit_aicall" id="credit_aicall" class="form-control" min="0" value="0" placeholder="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label">Credit Expiry (Days) <small class="text-muted">(0 = no expiry)</small></label>
                            <input type="number" name="expiry_days" id="coupon_expiry_days" class="form-control" min="0" value="0">
                        </div>
                    </div>

                    <!-- Usage Limits -->
                    <div class="col-md-12"><hr style="margin: 10px 0 18px;"></div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label">Total Usage Limit <small class="text-muted">(0 = unlimited)</small></label>
                            <input type="number" name="usage_limit" id="coupon_usage_limit" class="form-control" min="0" value="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="control-label">Per-Client Limit <small class="text-muted">(0 = unlimited)</small></label>
                            <input type="number" name="per_client_limit" id="coupon_per_client" class="form-control" min="0" value="1">
                        </div>
                    </div>

                    <!-- Validity Dates -->
                    <div class="col-md-12"><hr style="margin: 10px 0 18px;"></div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">Valid From</label>
                            <input type="date" name="valid_from" id="coupon_valid_from" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">Valid Until</label>
                            <input type="date" name="valid_until" id="coupon_valid_until" class="form-control">
                        </div>
                    </div>

                    <!-- Active & Notes -->
                    <div class="col-md-12"><hr></div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="active" id="coupon_active" checked> Active
                            </label>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="control-label">Notes <small class="text-muted">(internal)</small></label>
                            <textarea name="notes" id="coupon_notes" class="form-control" rows="2"></textarea>
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

<!-- ═══ Claims History Modal ═══ -->
<div class="modal fade" id="claimsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden; border: none; box-shadow: 0 20px 50px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: linear-gradient(135deg, #10b981, #059669); color: #fff; border-bottom: none; padding: 20px 28px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.8;">
                    <span>&times;</span>
                </button>
                <h4 class="modal-title" style="font-weight: 700;">
                    <i class="fa fa-list-ul" style="margin-right: 8px;"></i> Coupon Claims History
                </h4>
            </div>
            <div class="modal-body" style="padding: 24px 28px;">
                <div id="claimsLoading" class="text-center" style="padding:40px;">
                    <i class="fa fa-spinner fa-spin" style="font-size:24px; color:#6366f1;"></i>
                    <p style="margin-top:10px; color:#6b7280;">Loading claims...</p>
                </div>
                <div id="claimsEmpty" style="display:none; text-align:center; padding:40px;">
                    <i class="fa fa-inbox" style="font-size:36px; color:#d1d5db; margin-bottom:10px; display:block;"></i>
                    <p style="color:#9ca3af; font-weight:500;">No claims yet for this coupon.</p>
                </div>
                <div id="claimsList" style="display:none;">
                    <table class="table table-striped" style="margin-bottom:0;">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Credits Awarded</th>
                                <th>Claimed At</th>
                            </tr>
                        </thead>
                        <tbody id="claimsTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #f1f5f9; padding: 16px 28px;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
$(function () {
    initDataTable('.table-coupons', admin_url + 'ccx_msgs/coupons_table', undefined, undefined, 'undefined', [0, 'asc']);
});

var channelBadges = {
    sms:           { label: 'SMS',       bg: '#eef2ff', color: '#4f46e5' },
    whatsapp:      { label: 'WhatsApp',  bg: '#f0fdf4', color: '#16a34a' },
    email:         { label: 'Email',     bg: '#fffbeb', color: '#d97706' },
    aicall:        { label: 'AI Call',   bg: '#faf5ff', color: '#7c3aed' }
};

function openCouponModal(id) {
    // Reset form
    $('#couponForm')[0].reset();
    $('#coupon_id').val(0);
    $('#couponModalTitle').html('<i class="fa fa-ticket" style="margin-right:8px;"></i> <?php echo _l('ccx_msgs_coupon_add_new'); ?>');
    $('#coupon_active').prop('checked', true);
    $('#coupon_per_client').val(1);

    // Reset credit fields
    $('#credit_sms, #credit_whatsapp, #credit_email, #credit_aicall, #coupon_expiry_days, #coupon_usage_limit').val(0);

    $('#couponModal').modal('show');
}

function editCoupon(id) {
    $.getJSON(admin_url + 'ccx_msgs/coupon_get/' + id, function(data) {
        if (!data || !data.id) return;

        $('#coupon_id').val(data.id);
        $('#coupon_code').val(data.code);
        $('#coupon_description').val(data.description || '');
        $('#coupon_expiry_days').val(data.expiry_days || 0);
        $('#coupon_usage_limit').val(data.usage_limit || 0);
        $('#coupon_per_client').val(data.per_client_limit || 1);
        $('#coupon_valid_from').val(data.valid_from || '');
        $('#coupon_valid_until').val(data.valid_until || '');
        $('#coupon_notes').val(data.notes || '');
        $('#coupon_active').prop('checked', data.active == 1);

        // Parse credits JSON
        var credits = {};
        try { credits = JSON.parse(data.credits); } catch(e) {}
        $('#credit_sms').val(credits.sms || 0);
        $('#credit_whatsapp').val(credits.whatsapp || 0);
        $('#credit_email').val(credits.email || 0);
        $('#credit_aicall').val(credits.aicall || 0);

        $('#couponModalTitle').html('<i class="fa fa-pencil" style="margin-right:8px;"></i> <?php echo _l('ccx_msgs_coupon_edit'); ?>');
        $('#couponModal').modal('show');
    });
}

function viewClaims(couponId) {
    $('#claimsLoading').show();
    $('#claimsEmpty').hide();
    $('#claimsList').hide();
    $('#claimsModal').modal('show');

    $.getJSON(admin_url + 'ccx_msgs/coupon_claims/' + couponId, function(claims) {
        $('#claimsLoading').hide();
        if (!claims || claims.length === 0) {
            $('#claimsEmpty').show();
            return;
        }

        var html = '';
        claims.forEach(function(claim) {
            html += '<tr>';
            html += '<td><strong>' + (claim.client_name || 'Client #' + claim.client_id) + '</strong></td>';

            // Credits badges
            var creditsHtml = '';
            try {
                var cr = JSON.parse(claim.credits_awarded);
                for (var ch in cr) {
                    var b = channelBadges[ch] || { label: ch, bg: '#f3f4f6', color: '#374151' };
                    creditsHtml += '<span style="display:inline-block; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:600; background:' + b.bg + '; color:' + b.color + '; margin:1px 2px;">' + b.label + ': ' + cr[ch] + '</span>';
                }
            } catch(e) {
                creditsHtml = '<span style="color:#d1d5db;">—</span>';
            }
            html += '<td>' + creditsHtml + '</td>';
            html += '<td>' + claim.claimed_at + '</td>';
            html += '</tr>';
        });

        $('#claimsTableBody').html(html);
        $('#claimsList').show();
    });
}
</script>
</body>
</html>
