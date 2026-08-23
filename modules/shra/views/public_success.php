<?php defined('BASEPATH') or exit('No direct script access allowed'); include __DIR__ . '/_public_head.php'; ?>
    <div class="card">
        <div class="card-head" style="text-align:center">
            <div style="width:60px;height:60px;border-radius:50%;background:var(--green);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:10px"><i class="fa fa-check"></i></div>
            <h2>Welcome, <?php echo html_escape($rider->full_name); ?>!</h2>
            <p>Your membership is registered. Show this page or your membership card at the desk to pick a riding plan.</p>
        </div>
        <div class="card-body">
            <div class="kv">
                <div><div class="k">Membership no.</div><div class="v"><?php echo html_escape($rider->membership_no ?: $rider->rider_no); ?></div></div>
                <div><div class="k">Rider no.</div><div class="v"><?php echo html_escape($rider->rider_no); ?></div></div>
                <div><div class="k">Riding level</div><div class="v"><?php echo html_escape($rider->riding_level); ?></div></div>
                <div><div class="k">Plans</div><div class="v"><?php echo $rider->is_minor ? 'Children' : 'Adults'; ?></div></div>
            </div>
            <div style="text-align:center;margin:22px 0 6px"><?php echo shra_qr_svg(site_url('join/' . get_option('shra_public_token') . '/verify/' . $rider->rider_no), 4); ?></div>
            <div class="hint" style="text-align:center;margin-bottom:18px">Scan at the desk to verify</div>
            <a href="<?php echo $pdf_url; ?>" class="btn"><i class="fa-solid fa-id-card"></i> Download membership PDF</a>
            <div class="hint" style="text-align:center;margin-top:12px">Save it to your phone — it includes a cut-out membership card.</div>
        </div>
    </div>
<?php include __DIR__ . '/_public_foot.php'; ?>
