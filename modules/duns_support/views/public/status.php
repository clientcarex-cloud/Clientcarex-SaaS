<?php defined('BASEPATH') or exit('No direct script access allowed');
$noindex = true;
$events  = $fire_purchase
    ? [['Purchase', ['value' => (float) ($order->amount_paid ?: $order->amount), 'currency' => $landing['currency'], 'content_name' => $order->plan_name, 'order_id' => $order->order_no]]]
    : [];
$st   = $order->status;
$paid = in_array($st, ['paid', 'processing', 'completed'], true);
$first_name = explode(' ', trim($order->director_name))[0];
include __DIR__ . '/_head.php';
?>
<body>
<div class="page">
<?php include __DIR__ . '/_topbar.php'; ?>
<div class="narrow">
    <div class="card">
        <div class="center">
            <?php if ($st === 'completed') { ?>
                <div class="big-ic ok"><i class="fa-solid fa-award"></i></div>
                <h1>Your DUNS number is ready!</h1>
                <p class="sub">We have also emailed it to <?php echo html_escape($order->director_email); ?>.</p>
            <?php } elseif ($paid) { ?>
                <div class="big-ic ok"><i class="fa-solid fa-circle-check"></i></div>
                <h1>Payment received — thank you, <?php echo html_escape($first_name); ?>!</h1>
                <p class="sub">Our team is working on your DUNS number. It will be emailed to <b><?php echo html_escape($order->director_email); ?></b>.</p>
            <?php } elseif ($st === 'pending_payment') { ?>
                <div class="big-ic wait"><i class="fa-regular fa-clock"></i></div>
                <h1>Your order is saved</h1>
                <p class="sub">We have not received the payment yet. Complete it to start your delivery clock.</p>
            <?php } elseif ($st === 'refunded') { ?>
                <div class="big-ic info"><i class="fa-solid fa-rotate-left"></i></div>
                <h1>This order was refunded</h1>
                <p class="sub">The payment has been returned as per our money-back guarantee.</p>
            <?php } else { ?>
                <div class="big-ic bad"><i class="fa-solid fa-ban"></i></div>
                <h1>This order was cancelled</h1>
                <p class="sub">Please contact us if you think this is a mistake.</p>
            <?php } ?>
        </div>

        <?php if ($st === 'completed' && $order->duns_number) { ?>
        <div class="duns-out">
            <small>D-U-N-S Number</small>
            <div class="n"><?php echo duns_format_number($order->duns_number); ?></div>
            <div><?php echo html_escape($order->company_name); ?></div>
        </div>
        <?php } elseif (duns_is_open($order) && $order->due_at) { ?>
        <div class="countdown">
            <small>Delivery by</small>
            <div class="c" id="cd" data-due="<?php echo strtotime($order->due_at); ?>" data-now="<?php echo time(); ?>">--:--:--</div>
            <div class="due"><?php echo duns_datetime($order->due_at); ?></div>
        </div>
        <?php } ?>

        <ul class="track">
            <li class="done"><span class="d"><i class="fa-solid fa-check"></i></span><div><b>Order placed</b><small><?php echo duns_datetime($order->created_at); ?></small></div></li>
            <li class="<?php echo $paid ? 'done' : ($st === 'pending_payment' ? 'now' : ''); ?>"><span class="d"><?php echo $paid ? '<i class="fa-solid fa-check"></i>' : '2'; ?></span><div><b>Payment</b><small><?php echo $paid ? 'Received · ' . duns_datetime($order->paid_at) : 'Waiting for payment'; ?></small></div></li>
            <li class="<?php echo $st === 'completed' ? 'done' : ($paid ? 'now' : ''); ?>"><span class="d"><?php echo $st === 'completed' ? '<i class="fa-solid fa-check"></i>' : '3'; ?></span><div><b>Application in progress</b><small>Our experts file and follow up for you</small></div></li>
            <li class="<?php echo $st === 'completed' ? 'done' : ''; ?>"><span class="d"><?php echo $st === 'completed' ? '<i class="fa-solid fa-check"></i>' : '4'; ?></span><div><b>DUNS number on your email</b><small><?php echo $st === 'completed' ? 'Delivered · ' . duns_datetime($order->completed_at) : 'Within ' . duns_hours_label($order->delivery_hours) . ' of payment'; ?></small></div></li>
        </ul>

        <div class="kv">
            <div class="r"><span>Order</span><b><?php echo html_escape($order->order_no); ?></b></div>
            <div class="r"><span>Company</span><b><?php echo html_escape($order->company_name); ?></b></div>
            <div class="r"><span>Plan</span><b><?php echo html_escape($order->plan_name); ?> · <?php echo duns_hours_label($order->delivery_hours); ?></b></div>
            <div class="r"><span><?php echo $paid ? 'Paid' : 'Amount'; ?></span><b><?php echo duns_money($paid ? ($order->amount_paid ?: $order->amount) : $order->amount); ?></b></div>
        </div>

        <?php if ($st === 'pending_payment' && $can_pay) { ?>
        <a class="btn btn-primary btn-lg btn-block" href="<?php echo duns_public_url('pay/' . $order->ref); ?>">Complete payment <i class="fa-solid fa-arrow-right"></i></a>
        <?php } ?>
        <?php if ($paid && $st !== 'completed') { ?>
        <p class="muted center" style="font-size:13.5px">Keep an eye on <b><?php echo html_escape($order->work_email); ?></b> — a verification code may be sent there.</p>
        <?php } ?>

        <div class="help-row">
            <?php if ($landing['wa_link'] !== '') { ?><a class="btn btn-wa" href="<?php echo html_escape(duns_wa_link($landing['whatsapp'], 'Hi, I have a question about DUNS order ' . $order->order_no . '.')); ?>" target="_blank" rel="noopener" onclick="dunsTrack('Contact')"><i class="fa-brands fa-whatsapp"></i> Need help?</a><?php } ?>
            <?php if ($landing['email'] !== '') { ?><a class="btn btn-outline" href="mailto:<?php echo html_escape($landing['email']); ?>?subject=<?php echo rawurlencode('DUNS order ' . $order->order_no); ?>"><i class="fa-solid fa-envelope"></i> Email us</a><?php } ?>
        </div>
    </div>
    <p class="center muted" style="margin-top:16px;font-size:13px"><i class="fa-solid fa-shield-halved"></i> 100% money-back guarantee if we cannot deliver your DUNS number.</p>
</div>
</div>
<script>
(function(){
    var el = document.getElementById('cd');
    if (!el) { return; }
    // Count from the server's clock, not the phone's
    var offset = parseInt(el.dataset.now, 10) * 1000 - Date.now(), due = parseInt(el.dataset.due, 10) * 1000;
    function pad(n){ return (n < 10 ? '0' : '') + n; }
    function tick(){
        var s = Math.max(0, Math.floor((due - (Date.now() + offset)) / 1000));
        el.textContent = pad(Math.floor(s / 3600)) + ':' + pad(Math.floor(s % 3600 / 60)) + ':' + pad(s % 60);
    }
    tick(); setInterval(tick, 1000);
    // Pick up the delivery without a manual refresh
    setTimeout(function(){ location.reload(); }, 120000);
})();
</script>
</body>
</html>
