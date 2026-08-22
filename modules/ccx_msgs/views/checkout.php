<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?php echo base_url('assets/plugins/font-awesome/css/font-awesome.min.css'); ?>" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #f0f4ff 0%, #e8ecf8 50%, #f5f0ff 100%);
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .checkout-box {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 20px 50px -12px rgba(0,0,0,0.1);
            padding: 40px 36px;
            width: 100%;
            max-width: 480px;
            text-align: center;
            border: 1px solid rgba(226,232,240,0.8);
        }
        .co-badge {
            display: inline-block;
            background: linear-gradient(135deg, #ebf4ff, #e8e0ff);
            color: #4c51bf;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 6px 16px;
            border-radius: 20px;
            margin-bottom: 20px;
        }
        .co-header {
            font-size: 22px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 6px;
        }
        .co-plan {
            font-size: 16px;
            color: #718096;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .co-price {
            font-size: 40px;
            font-weight: 800;
            background: linear-gradient(135deg, #2b6cb0, #6b46c1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            line-height: 1.1;
        }
        .co-note {
            font-size: 13px;
            color: #a0aec0;
            margin-bottom: 28px;
            line-height: 1.5;
        }
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
            margin: 0 0 24px 0;
        }
        .section-label {
            font-size: 12px;
            font-weight: 700;
            color: #a0aec0;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            text-align: left;
            margin-bottom: 14px;
        }
        .gateway-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }
        .btn-gateway {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            color: #fff;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
            letter-spacing: 0.2px;
            position: relative;
            overflow: hidden;
        }
        .btn-gateway:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        .btn-gateway:active {
            transform: translateY(0);
        }
        .btn-gateway:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        .btn-gateway i {
            font-size: 16px;
        }
        .btn-cancel {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            background: transparent;
            color: #718096;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
        }
        .btn-cancel:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
            color: #4a5568;
        }
        .alert-area {
            margin-top: 16px;
            display: none;
            background: #fff5f5;
            color: #c53030;
            font-weight: 600;
            font-size: 13px;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #fed7d7;
            text-align: left;
        }
        .success-area {
            margin-top: 16px;
            display: none;
            background: #f0fff4;
            color: #276749;
            font-weight: 600;
            font-size: 14px;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #c6f6d5;
        }
        .success-area i { margin-right: 8px; }
        #loading_overlay {
            display: none;
            margin-top: 16px;
            padding: 20px;
            background: #f7fafc;
            border-radius: 10px;
        }
        #loading_overlay .spinner {
            width: 32px;
            height: 32px;
            border: 3px solid #e2e8f0;
            border-top: 3px solid #4c51bf;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 12px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        #loading_overlay p {
            margin: 0;
            color: #4a5568;
            font-size: 13px;
            font-weight: 500;
        }
        .no-gateways {
            background: #fffbeb;
            color: #92400e;
            padding: 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #fde68a;
            text-align: left;
        }
        .no-gateways i { margin-right: 6px; }
        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 20px;
            font-size: 11px;
            color: #a0aec0;
            font-weight: 500;
        }
        .secure-badge i { color: #48bb78; }
    </style>
</head>
<body>

<div class="checkout-box">
    <div class="co-badge">Secure Checkout</div>
    <div class="co-header">Complete your purchase</div>
    <?php if (isset($cart_mode) && $cart_mode && count($plans) > 1) { ?>
        <div class="co-plan"><?php echo count($plans); ?> Plans Selected</div>
    <?php } else { ?>
        <div class="co-plan"><?php echo htmlspecialchars($plan->plan_name); ?></div>
    <?php } ?>

    <!-- Price Breakdown -->
    <div style="text-align: left; background: #f7fafc; border-radius: 10px; padding: 16px 20px; margin-bottom: 16px; border: 1px solid #e2e8f0;">
        <?php if (isset($cart_mode) && $cart_mode && count($plans) > 1) { ?>
            <!-- Multi-plan cart line items -->
            <?php foreach ($plans as $p) {
                $p_discount = $p->price;
                if ($p->discount_percent > 0) {
                    $p_discount = $p->price - ($p->price * ($p->discount_percent / 100));
                }
                $p_tax_pct = isset($p->tax_percent) ? (float)$p->tax_percent : 0;
                $p_tax_amt = ($p_discount * $p_tax_pct) / 100;
                $p_total = $p_discount + $p_tax_amt;
                $p_currency = isset($p->_currency) ? $p->_currency : $plan_currency;
            ?>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #edf2f7;">
                <div style="flex:1; min-width:0;">
                    <div style="font-size: 13px; font-weight: 600; color: #1a202c;"><?php echo htmlspecialchars($p->plan_name); ?></div>
                    <div style="font-size: 11px; color: #718096; margin-top: 2px;">
                        <?php echo ucfirst(str_replace('_', ' ', $p->message_type)); ?> · <?php echo number_format($p->message_count); ?> credits · <?php echo $p->expiry_days; ?> days
                    </div>
                </div>
                <div style="text-align:right; flex-shrink:0; margin-left:12px;">
                    <div style="font-size: 14px; font-weight: 600; color: #1a202c;"><?php echo app_format_money($p_discount, $p_currency->name); ?></div>
                    <?php $p_saving = $p->price - $p_discount; if ($p_saving > 0) { ?>
                        <div style="font-size: 11px; color: #94a3b8; text-decoration: line-through;"><?php echo app_format_money($p->price, $p_currency->name); ?></div>
                        <div style="font-size: 11px; color: #10b981; font-weight: 600;"><i class="fa fa-tag" style="margin-right:2px;"></i>Save <?php echo app_format_money($p_saving, $p_currency->name); ?></div>
                    <?php } ?>
                    <?php if ($p_tax_pct > 0) { ?>
                        <div style="font-size: 11px; color: #a0aec0;">+ <?php echo app_format_money($p_tax_amt, $p_currency->name); ?> tax</div>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>

            <!-- Combined totals -->
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; color: #4a5568;">
                <span>Subtotal</span>
                <span><?php echo app_format_money($subtotal, $plan_currency->name); ?></span>
            </div>
            <?php if ($tax_amount > 0) { ?>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; color: #4a5568;">
                <span>Tax</span>
                <span>+<?php echo app_format_money($tax_amount, $plan_currency->name); ?></span>
            </div>
            <?php } ?>
        <?php } else { ?>
            <!-- Single-plan breakdown (original) -->
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; color: #4a5568;">
                <span>Subtotal</span>
                <span><?php echo app_format_money($subtotal, $plan_currency->name); ?></span>
            </div>
            <?php if (isset($tax_percent) && $tax_percent > 0) { ?>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; color: #4a5568;">
                <span><?php echo htmlspecialchars($tax_name); ?> (<?php echo number_format($tax_percent, 2); ?>%)</span>
                <span>+<?php echo app_format_money($tax_amount, $plan_currency->name); ?></span>
            </div>
            <?php } ?>
        <?php } ?>
        <?php if (isset($promo_discount) && $promo_discount > 0) { ?>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; color: #10b981;">
            <span><i class="fa fa-tag" style="margin-right:4px;"></i>Promo Discount<?php echo !empty($promo_code_label) ? ' (' . htmlspecialchars($promo_code_label) . ')' : ''; ?></span>
            <span>-<?php echo app_format_money($promo_discount, $plan_currency->name); ?></span>
        </div>
        <?php } ?>
        <?php $total_savings = isset($total_savings) ? (float)$total_savings : 0; ?>
        <?php $promo_disc = isset($promo_discount) ? (float)$promo_discount : 0; ?>
        <?php $total_savings += $promo_disc; ?>
        <?php if ($total_savings > 0) { ?>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; color: #10b981; font-weight: 600;">
            <span><i class="fa fa-trophy" style="margin-right:4px;"></i>You Save</span>
            <span><?php echo app_format_money($total_savings, $plan_currency->name); ?></span>
        </div>
        <?php } ?>
        <div style="height: 1px; background: #e2e8f0; margin: 10px 0;"></div>
        <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: 700; color: #1a202c;">
            <span>Total Payable</span>
            <span><?php echo app_format_money($session->amount, $plan_currency->name); ?></span>
        </div>
    </div>

    <div class="co-note">
        Your invoice will be instantly generated<br>upon successful payment.
    </div>

    <div class="divider"></div>

    <!-- Payment Buttons -->
    <div class="section-label">Select Payment Method</div>
    <div class="gateway-list" id="gateway_buttons">
        <?php if (!empty($gateways)) { foreach ($gateways as $gateway) { ?>
            <button class="btn-gateway" 
                    id="btn_<?php echo htmlspecialchars($gateway['id']); ?>"
                    data-gateway="<?php echo htmlspecialchars($gateway['id']); ?>"
                    onclick="processPayment('<?php echo htmlspecialchars($gateway['id']); ?>')">
                <i class="fa fa-credit-card"></i>
                <span>Pay with <?php echo htmlspecialchars($gateway['name']); ?></span>
            </button>
        <?php } } else { ?>
            <div class="no-gateways">
                <i class="fa fa-exclamation-triangle"></i>
                No active payment gateways found. Please contact the administrator to enable payment methods in Settings.
            </div>
        <?php } ?>
    </div>

    <!-- Cancel Button -->
    <button onclick="window.history.back();" class="btn-cancel">
        <i class="fa fa-arrow-left"></i> Cancel Payment
    </button>

    <div id="loading_overlay">
        <div class="spinner"></div>
        <p>Processing your payment and generating invoice...</p>
    </div>

    <div class="alert-area" id="error_msg"></div>
    <div class="success-area" id="success_msg">
        <i class="fa fa-check-circle"></i>
        Payment successful! Redirecting...
    </div>

    <div class="secure-badge">
        <i class="fa fa-lock"></i>
        Payments are secure and encrypted
    </div>
</div>

<script src="<?php echo base_url('assets/plugins/jquery/jquery.min.js'); ?>"></script>

<?php if (function_exists('csrf_token_name')) { ?>
<script>
// Global CSRF token for all AJAX POST requests
$.ajaxSetup({
    data: {
        '<?php echo $this->security->get_csrf_token_name(); ?>': '<?php echo $this->security->get_csrf_hash(); ?>'
    }
});
</script>
<?php } ?>

<script>
/**
 * Gateway Brand Registry
 * Maps gateway IDs to their brand colors and icons.
 * Unknown gateways fall back to a neutral teal style.
 */
var gatewayBrands = {
    'razorpay':          { color: '#0070e0', icon: 'fa-bolt' },
    'stripe':            { color: '#635bff', icon: 'fa-cc-stripe' },
    'paypal':            { color: '#003087', icon: 'fa-cc-paypal' },
    'paypal_checkout':   { color: '#0070ba', icon: 'fa-cc-paypal' },
    'braintree':         { color: '#003366', icon: 'fa-credit-card-alt' },
    'mollie':            { color: '#e85e8a', icon: 'fa-credit-card' },
    'payu_money':        { color: '#00b9f5', icon: 'fa-money' },
    'instamojo':         { color: '#cb2d6f', icon: 'fa-mobile' },
    'authorize_acceptjs':{ color: '#4b6cb7', icon: 'fa-university' },
    'two_checkout':      { color: '#2fbe6e', icon: 'fa-shopping-cart' },
    'stripe_ideal':      { color: '#635bff', icon: 'fa-cc-stripe' },
};
var defaultBrand = { color: '#2d9c94', icon: 'fa-credit-card' };

// Apply brand styles on load
$(document).ready(function() {
    $('.btn-gateway').each(function() {
        var gid = $(this).data('gateway');
        var brand = gatewayBrands[gid] || defaultBrand;
        $(this).css('background', brand.color);
        $(this).find('i').removeClass('fa-credit-card').addClass(brand.icon);
    });
});
</script>

<?php 
   // Load Razorpay SDK if razorpay key exists
    $razorpay_key = get_option('paymentmethod_razorpay_key_id'); 
    if (!empty($razorpay_key)) { 
?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
function triggerRazorpay() {
    var options = {
        "key": "<?php echo htmlspecialchars($razorpay_key); ?>",
        "amount": "<?php echo $session->amount * 100; ?>",
        "currency": "<?php echo $plan_currency->name; ?>",
        "name": "<?php echo htmlspecialchars(get_option('companyname')); ?>",
        "description": "<?php echo htmlspecialchars($plan->plan_name); ?>",
        "prefill": {
            "name": "<?php echo (is_object($client) && isset($client->company)) ? htmlspecialchars($client->company) : ''; ?>"
        },
        "theme": {
            "color": "#0070e0"
        },
        "handler": function (response) {
            setLoading(true);
            $.post("<?php echo site_url('ccx_msgs/ccx_msgs_public/verify_razorpay'); ?>", {
                session_token: "<?php echo $session->session_token; ?>",
                razorpay_payment_id: response.razorpay_payment_id
            }, function(data) {
                var res = JSON.parse(data);
                setLoading(false);
                if(res.success) {
                    showSuccess();
                } else {
                    showError(res.message);
                }
            }).fail(function() {
                setLoading(false);
                showError("A server error occurred while verifying the payment.");
            });
        },
        "modal": {
            "ondismiss": function() {
                // User closed Razorpay popup
            }
        }
    };
    var rzp1 = new Razorpay(options);
    rzp1.open();
}
</script>
<?php } ?>

<script>
/**
 * Main payment dispatcher
 */
function processPayment(gateway_id) {
    hideError();

    if (gateway_id === 'razorpay') {
        if (typeof Razorpay === 'undefined') {
            showError("Razorpay payment library failed to load. Please refresh and try again.");
            return;
        }
        triggerRazorpay();
    } 
    else if (gateway_id === 'stripe' || gateway_id === 'stripe_ideal') {
        // Create Stripe Checkout Session via AJAX and redirect
        setLoading(true);
        $.post("<?php echo site_url('ccx_msgs/ccx_msgs_public/create_stripe_session'); ?>", {
            session_token: "<?php echo $session->session_token; ?>"
        }, function(data) {
            try {
                var res = JSON.parse(data);
                if (res.success && res.redirect_url) {
                    window.location.href = res.redirect_url;
                } else {
                    setLoading(false);
                    showError(res.message || "Failed to create Stripe session.");
                }
            } catch(e) {
                setLoading(false);
                showError("Unexpected response from server.");
            }
        }).fail(function() {
            setLoading(false);
            showError("A server error occurred. Please try again.");
        });
    }
    else if (gateway_id === 'payu_money') {
        // Create PayU Money session and redirect via self-submitting form
        setLoading(true);
        $.post("<?php echo site_url('ccx_msgs/ccx_msgs_public/create_payu_session'); ?>", {
            session_token: "<?php echo $session->session_token; ?>"
        }, function(data) {
            try {
                var res = JSON.parse(data);
                if (res.success && res.form_html) {
                    // Write the self-submitting form to the page — redirects to PayU
                    document.open();
                    document.write(res.form_html);
                    document.close();
                } else {
                    setLoading(false);
                    showError(res.message || "Failed to create PayU Money session.");
                }
            } catch(e) {
                setLoading(false);
                showError("Unexpected response from server.");
            }
        }).fail(function() {
            setLoading(false);
            showError("A server error occurred. Please try again.");
        });
    }
    else {
        // Unsupported gateway — show helpful message
        showError('The "' + gateway_id + '" payment method is not yet configured for this checkout. Please contact support or choose another method.');
    }
}

/**
 * UI Helpers
 */
function setLoading(show) {
    if (show) {
        $('#gateway_buttons').find('.btn-gateway').prop('disabled', true);
        $('#loading_overlay').slideDown(200);
        $('#error_msg').slideUp(100);
    } else {
        $('#gateway_buttons').find('.btn-gateway').prop('disabled', false);
        $('#loading_overlay').slideUp(200);
    }
}

function showError(msg) {
    $('#error_msg').html('<i class="fa fa-exclamation-circle"></i> ' + msg).slideDown(200);
}

function hideError() {
    $('#error_msg').slideUp(100);
}

function showSuccess() {
    $('#gateway_buttons').slideUp(200);
    $('.btn-cancel').slideUp(200);
    $('#success_msg').slideDown(200);
    setTimeout(function() {
        var returnUrl = "<?php
            $ru = !empty($session->return_url) ? $session->return_url : site_url();
            $sep = (strpos($ru, '?') !== false) ? '&' : '?';
            echo $ru . $sep . 'payment_success=1&session_token=' . urlencode($session->session_token);
        ?>";
        window.location.href = returnUrl;
    }, 2000);
}
</script>

</body>
</html>
