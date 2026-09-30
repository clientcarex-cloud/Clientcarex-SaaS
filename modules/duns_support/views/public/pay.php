<?php defined('BASEPATH') or exit('No direct script access allowed');
$events  = [
    ['InitiateCheckout', ['value' => (float) $order->amount, 'currency' => $landing['currency'], 'content_name' => $order->plan_name]],
];
$noindex = true;
$first   = key($gateways);
include __DIR__ . '/_head.php';
?>
<body>
<div class="page">
<?php include __DIR__ . '/_topbar.php'; ?>
<div class="narrow">
    <div class="card">
        <div class="center">
            <div class="big-ic info"><i class="fa-solid fa-lock"></i></div>
            <h1>Almost done, <?php echo html_escape(explode(' ', trim($order->director_name))[0]); ?>!</h1>
            <p class="sub">Complete the payment to start your <?php echo duns_hours_label($order->delivery_hours); ?> delivery clock.</p>
        </div>

        <?php if (count($errors)) { ?>
        <div class="alert alert-err" style="margin-top:18px"><?php foreach ($errors as $e) { ?><div><?php echo html_escape($e); ?></div><?php } ?></div>
        <?php } ?>

        <div class="kv">
            <div class="r"><span>Order</span><b><?php echo html_escape($order->order_no); ?></b></div>
            <div class="r"><span>Company</span><b><?php echo html_escape($order->company_name); ?></b></div>
            <div class="r"><span>Plan</span><b><?php echo html_escape($order->plan_name); ?> · within <?php echo duns_hours_label($order->delivery_hours); ?></b></div>
            <div class="r"><span>DUNS sent to</span><b><?php echo html_escape($order->director_email); ?></b></div>
            <div class="r"><span>Certificate</span><b><?php echo count($files) ? '<i class="fa-solid fa-circle-check" style="color:var(--green)"></i> Received' : 'We will ask you for it by email'; ?></b></div>
            <div class="r tot"><span>Total to pay</span><b><?php echo duns_money($order->amount); ?></b></div>
        </div>

        <?php if (count($gateways)) { ?>
        <form method="post" action="<?php echo duns_public_url('pay/' . $order->ref); ?>" id="pay-form">
            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
            <?php if (count($gateways) > 1) { ?><div class="fsec" style="margin-top:0">Pay with</div><?php } ?>
            <div class="gws"<?php echo count($gateways) === 1 ? ' style="display:none"' : ''; ?>>
                <?php foreach ($gateways as $id => $g) { ?>
                <label class="gw<?php echo $id === $first ? ' on' : ''; ?>">
                    <input type="radio" name="gateway" value="<?php echo html_escape($id); ?>" <?php echo $id === $first ? 'checked' : ''; ?>>
                    <span class="ic"><i class="fa-solid fa-credit-card"></i></span>
                    <span><b><?php echo html_escape($g['name']); ?></b><?php if ($g['test_mode']) { ?> <span class="pill pill-amber">Test mode</span><?php } ?><br><small class="muted">UPI · Cards · Net banking</small></span>
                    <span class="ck"><i class="fa-solid fa-check"></i></span>
                </label>
                <?php } ?>
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block" id="pay-btn">Pay <?php echo duns_money($order->amount); ?> securely <i class="fa-solid fa-arrow-right"></i></button>
            <div class="secure"><span><i class="fa-solid fa-lock"></i> Encrypted checkout</span><span><i class="fa-solid fa-shield-halved"></i> 100% money-back guarantee</span></div>
        </form>
        <?php } else { ?>
        <div class="alert alert-info"><b>Your order is saved.</b> Online payment is not available right now — our team will contact you on <?php echo html_escape($order->director_mobile); ?> shortly to complete it.</div>
        <div class="help-row">
            <?php if ($landing['wa_link'] !== '') { ?><a class="btn btn-wa" href="<?php echo html_escape(duns_wa_link($landing['whatsapp'], 'Hi, I placed DUNS order ' . $order->order_no . ' and want to complete the payment.')); ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Pay via WhatsApp</a><?php } ?>
            <a class="btn btn-outline" href="<?php echo duns_public_url('status/' . $order->ref); ?>">View my order</a>
        </div>
        <?php } ?>
    </div>
    <p class="center muted" style="margin-top:16px;font-size:13px">Bookmark this page — you can come back to pay anytime.</p>
</div>
</div>
<script>
document.querySelectorAll('.gw').forEach(function(g){
    g.addEventListener('change', function(){ document.querySelectorAll('.gw').forEach(function(x){ x.classList.toggle('on', x.querySelector('input').checked); }); });
});
var pf = document.getElementById('pay-form');
if (pf) {
    pf.addEventListener('submit', function(){
        dunsTrack('AddPaymentInfo', {value: <?php echo (float) $order->amount; ?>, currency: <?php echo json_encode($landing['currency']); ?>});
        var b = document.getElementById('pay-btn'); b.disabled = true; b.innerHTML = 'Opening secure payment… <i class="fa-solid fa-spinner fa-spin"></i>';
    });
    // Coming back from the gateway with the Back button restores a disabled button — re-arm it
    window.addEventListener('pageshow', function(){ var b = document.getElementById('pay-btn'); b.disabled = false; b.innerHTML = <?php echo json_encode('Pay ' . duns_money($order->amount) . ' securely <i class="fa-solid fa-arrow-right"></i>'); ?>; });
}
</script>
</body>
</html>
