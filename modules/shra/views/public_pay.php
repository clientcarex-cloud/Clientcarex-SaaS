<?php defined('BASEPATH') or exit('No direct script access allowed');
include __DIR__ . '/_public_head.php';
$old_kind   = ($old['kind'] ?? ($pay['partial'] ? 'full' : 'full'));
$old_gw     = (string) ($old['gateway'] ?? key($gateways));
?>
<style>
.steps{display:flex;justify-content:center;gap:0;margin:0 0 18px;counter-reset:s}
.steps span{flex:1;max-width:130px;text-align:center;font-size:10.5px;letter-spacing:.8px;text-transform:uppercase;font-weight:700;color:var(--muted);position:relative;padding-top:26px}
.steps span:before{content:counter(s);counter-increment:s;position:absolute;top:0;left:50%;transform:translateX(-50%);width:22px;height:22px;border-radius:50%;background:#fff;border:1.5px solid var(--line);font-family:'Cormorant Garamond',Georgia,serif;font-size:13px;line-height:19px;color:var(--muted)}
.steps span:after{content:'';position:absolute;top:11px;left:50%;width:100%;border-top:1.5px dashed var(--line);z-index:-1}
.steps span:last-child:after{display:none}
.steps span.on{color:var(--ink)}
.steps span.on:before{background:var(--ink);border-color:var(--ink);color:var(--cream)}
.steps span.done:before{background:var(--gold);border-color:var(--gold);color:#fff;content:'✓'}
.plan{background:var(--cream-2);border:1px solid var(--line);border-radius:16px;padding:16px 18px;margin-bottom:20px}
.plan .top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.plan .nm{font-family:'Cormorant Garamond',Georgia,serif;font-size:22px;font-weight:700;line-height:1.15}
.plan .amt{font-size:24px;font-weight:700;white-space:nowrap}
.plan .off{display:inline-block;margin-top:4px;background:#f6ecd2;color:var(--brown);border-radius:999px;padding:3px 10px;font-size:11px;font-weight:700}
.opt{display:block;border:1.5px solid var(--line);border-radius:14px;padding:14px 16px;cursor:pointer;background:#fff;margin-bottom:10px;transition:.15s}
.opt:hover{border-color:var(--gold-2)}
.opt input{display:none}
.opt:has(input:checked){border-color:var(--ink);background:var(--cream-2);box-shadow:0 0 0 3px rgba(28,26,23,.07)}
.opt .hd{display:flex;justify-content:space-between;align-items:center;gap:10px}
.opt b{font-size:15px}
.opt .sub{font-size:12.5px;color:var(--muted);margin-top:3px;line-height:1.45}
.opt .tick{width:20px;height:20px;border-radius:50%;border:1.5px solid var(--line);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:10px;color:transparent}
.opt:has(input:checked) .tick{background:var(--ink);border-color:var(--ink);color:#fff}
.amtbox{margin-top:12px;display:none}
.opt:has(input:checked) .amtbox{display:block}
.gw{display:flex;align-items:center;gap:12px;border:1.5px solid var(--line);border-radius:14px;padding:13px 16px;cursor:pointer;background:#fff;margin-bottom:10px}
.gw:has(input:checked){border-color:var(--ink);background:var(--cream-2)}
.gw input{display:none}
.gw .ic{width:34px;height:34px;border-radius:9px;background:var(--ink);color:var(--cream);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.gw b{font-size:14.5px}
.test{display:inline-block;font-size:10.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--brown);background:#f6ecd2;border-radius:999px;padding:2px 8px}
.gw .test{margin-left:auto}
.due{display:flex;justify-content:space-between;font-size:13.5px;padding:12px 2px 0;border-top:1px dashed var(--line);margin-top:14px;color:var(--muted)}
.due b{color:var(--ink);font-size:15px}
.skip{display:block;text-align:center;margin-top:14px;font-size:13.5px;color:var(--muted);text-decoration:underline}
</style>
    <div class="card">
        <div class="card-head">
            <div class="steps"><span class="done">Choose</span><span class="done">Plan</span><span class="done">Details</span><span class="on">Pay</span></div>
            <h2>Secure your seat, <?php echo html_escape(explode(' ', trim($rider->full_name))[0]); ?></h2>
            <p>You are registered as <b><?php echo html_escape($rider->rider_no); ?></b>. Pay now to lock your plan — or pay at the desk when you arrive.</p>
        </div>
        <div class="card-body">
            <?php if (count($errors)) { ?>
            <div class="err"><b>Please check the following:</b><ul><?php foreach ($errors as $e) { ?><li><?php echo html_escape($e); ?></li><?php } ?></ul></div>
            <?php } ?>

            <div class="plan">
                <div class="top">
                    <div>
                        <div class="nm"><?php echo html_escape($package->name); ?></div>
                        <div class="hint"><?php echo (int) $package->sessions; ?> session<?php echo $package->sessions > 1 ? 's' : ''; ?> × <?php echo (int) $package->duration_min; ?> min · <?php echo ucfirst($package->audience); ?></div>
                        <?php if ($rider->schedule) { ?><div class="hint" style="margin-top:3px"><i class="fa-solid fa-calendar-day"></i> <?php echo html_escape($rider->schedule); ?></div><?php } ?>
                        <?php if ($quote['discount_percent'] > 0) { ?><div class="off"><?php echo $quote['discount_percent'] + 0; ?>% <?php echo html_escape(get_option('shra_offer_label') ?: 'offer'); ?> applied</div><?php } ?>
                    </div>
                    <div style="text-align:right">
                        <div class="amt"><?php echo shra_money($total); ?></div>
                        <?php if ($quote['discount_percent'] > 0) { ?><div class="hint"><s><?php echo shra_money($quote['list_price']); ?></s></div><?php } ?>
                    </div>
                </div>
            </div>

            <?php echo form_open(site_url('join/pay/' . $rider->rider_no . '/' . shra_sign($rider->rider_no)), ['id' => 'shra-pay']); ?>

            <div class="sec">How much would you like to pay?</div>
            <label class="opt">
                <input type="radio" name="kind" value="full" <?php echo $old_kind !== 'partial' ? 'checked' : ''; ?>>
                <div class="hd">
                    <div><b>Pay in full — <?php echo shra_money($total); ?></b><div class="sub">Your plan is fully paid and ready from your first session.</div></div>
                    <div class="tick"><i class="fa fa-check"></i></div>
                </div>
            </label>

            <?php if ($pay['partial']) { ?>
            <label class="opt">
                <input type="radio" name="kind" value="partial" <?php echo $old_kind === 'partial' ? 'checked' : ''; ?>>
                <div class="hd">
                    <div><b>Pay part now — <?php echo shra_money($min); ?></b><div class="sub"><?php echo html_escape($pay['note']); ?></div></div>
                    <div class="tick"><i class="fa fa-check"></i></div>
                </div>
                <div class="amtbox">
                    <input type="hidden" name="amount" value="<?php echo $min; ?>">
                    <div class="due"><span>Balance to pay at the desk</span><b><?php echo shra_money(max(0, $total - $min)); ?></b></div>
                </div>
            </label>
            <?php } ?>

            <?php $single = count($gateways) === 1; ?>
            <?php if ($single) {
                // Only one way to pay — there is nothing to choose, so skip the picker
                // and send the rider straight to it when they hit Pay.
                $only = key($gateways); ?>
            <input type="hidden" name="gateway" value="<?php echo html_escape($only); ?>">
            <?php } else { ?>
            <div class="sec">Pay with</div>
            <?php foreach ($gateways as $id => $g) { ?>
            <label class="gw">
                <input type="radio" name="gateway" value="<?php echo html_escape($id); ?>" <?php echo $old_gw === $id ? 'checked' : ''; ?>>
                <span class="ic"><i class="fa-solid fa-credit-card"></i></span>
                <b><?php echo html_escape($g['name']); ?></b>
                <?php if ($g['test_mode']) { ?><span class="test">Test mode</span><?php } ?>
            </label>
            <?php } ?>
            <?php } ?>
            <div class="hint"<?php echo $single ? ' style="margin-top:20px"' : ''; ?>>You will be taken to the payment page. Cards, UPI, net banking and wallets are accepted where the provider supports them.<?php if ($single && $gateways[$only]['test_mode']) { ?> <span class="test">Test mode</span><?php } ?></div>

            <?php if ($rider->preferred_batch) { ?><div class="fcfs"><i class="fa-solid fa-user-clock"></i> <span><?php echo html_escape(shra_fcfs_note()); ?> Paying now confirms your place in the <?php echo html_escape(shra_batch_label($rider->preferred_batch, false)); ?> batch.</span></div><?php } ?>
            <button type="submit" class="btn" style="margin-top:18px"><i class="fa-solid fa-lock"></i> <span id="pay-btn-label">Pay <?php echo shra_money($total); ?></span></button>
            <?php echo form_close(); ?>

            <?php if ($pay['allow_skip']) { ?>
            <a href="<?php echo html_escape($done_url); ?>" class="skip">I'll pay at the reception desk</a>
            <?php } ?>
        </div>
    </div>
<script>
(function () {
    var total = <?php echo json_encode(round($total, 2)); ?>,
        min   = <?php echo json_encode(round($min, 2)); ?>,
        cur   = <?php echo json_encode(get_base_currency()->symbol); ?>,
        form  = document.getElementById('shra-pay'),
        label = document.getElementById('pay-btn-label');

    function money(n) {
        return cur + n.toFixed(2).replace(/\.00$/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function partial() {
        var c = form.querySelector('input[name=kind]:checked');
        return c && c.value === 'partial';
    }

    function sync() {
        label.textContent = 'Pay ' + money(partial() ? Math.min(min, total) : total);
    }

    form.addEventListener('change', sync);
    sync();

    form.addEventListener('submit', function (e) {
        // A radio must be picked; the single-gateway form carries a hidden field instead
        var gw = form.querySelector('input[name=gateway]:checked') || form.querySelector('input[name=gateway][type=hidden]');
        if (!gw || !gw.value) {
            e.preventDefault();
            alert('Please choose how you would like to pay.');
            return;
        }
        form.querySelector('button[type=submit]').disabled = true;
        label.textContent = 'Opening the payment page…';
    });
})();
</script>
<?php include __DIR__ . '/_public_foot.php'; ?>
