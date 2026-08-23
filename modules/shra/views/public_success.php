<?php defined('BASEPATH') or exit('No direct script access allowed'); include __DIR__ . '/_public_head.php';
$learner = $rider->rider_type === 'learner';
$pkg     = $rider->preferred_package;
?>
    <div class="card">
        <div class="card-head" style="text-align:center">
            <div style="width:60px;height:60px;border-radius:50%;background:var(--green);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:10px"><i class="fa fa-check"></i></div>
            <h2><?php echo $learner ? 'Welcome, ' : 'See you soon, '; ?><?php echo html_escape($rider->full_name); ?>!</h2>
            <p><?php echo $learner ? 'Your membership is registered. Show this page or your membership card at the desk to activate your plan.' : 'Your guest ride is reserved. Show this page at the desk, pay and ride — first come, first served.'; ?></p>
        </div>
        <div class="card-body">
            <?php if ($pkg) { ?>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;background:var(--cream-2);border:1px solid var(--line);border-radius:14px;padding:14px 16px;margin-bottom:18px">
                <div><div class="k" style="font-size:10.5px;letter-spacing:1px;text-transform:uppercase;color:var(--muted);font-weight:600">Selected plan</div><div style="font-family:'Cormorant Garamond',Georgia,serif;font-size:20px;font-weight:700"><?php echo html_escape($pkg->name); ?> · <?php echo ucfirst($pkg->audience); ?></div><div class="hint"><?php echo (int) $pkg->sessions; ?> session<?php echo $pkg->sessions > 1 ? 's' : ''; ?> × <?php echo (int) $pkg->duration_min; ?> min</div></div>
                <div style="text-align:right"><div style="font-family:'Cormorant Garamond',Georgia,serif;font-size:26px;font-weight:700;color:var(--red)"><?php echo shra_money($plan['total']); ?></div><?php if ($plan['discount_percent'] > 0) { ?><div class="hint"><s><?php echo shra_money($plan['list_price']); ?></s> · <?php echo $plan['discount_percent'] + 0; ?>% off</div><?php } ?><div class="hint">pay at the desk</div></div>
            </div>
            <?php } ?>
            <div class="kv">
                <?php if ($learner) { ?><div><div class="k">Membership no.</div><div class="v"><?php echo html_escape($rider->membership_no ?: $rider->rider_no); ?></div></div><?php } ?>
                <div><div class="k">Rider no.</div><div class="v"><?php echo html_escape($rider->rider_no); ?></div></div>
                <?php if ($learner) { ?><div><div class="k">Riding level</div><div class="v"><?php echo html_escape($rider->riding_level); ?></div></div><?php } ?>
                <div><div class="k">Mobile</div><div class="v"><?php echo html_escape($rider->mobile); ?></div></div>
            </div>
            <div style="width:180px;height:180px;margin:22px auto 6px;background:#fff;border:1px solid var(--line);border-radius:14px;padding:10px"><?php echo shra_qr_svg(shra_verify_url($rider->rider_no), 4); ?></div>
            <div class="hint" style="text-align:center;margin-bottom:18px">Show this code at the desk</div>
            <?php if ($pdf_url) { ?>
            <a href="<?php echo $pdf_url; ?>" class="btn"><i class="fa-solid fa-id-card"></i> Download membership PDF</a>
            <div class="hint" style="text-align:center;margin-top:12px">Save it to your phone — it includes a cut-out membership card.</div>
            <?php } else { ?>
            <a href="<?php echo site_url('join'); ?>" class="btn ghost"><i class="fa-solid fa-graduation-cap"></i> Want to learn riding? Become a rider</a>
            <?php } ?>
        </div>
    </div>
<?php include __DIR__ . '/_public_foot.php'; ?>
