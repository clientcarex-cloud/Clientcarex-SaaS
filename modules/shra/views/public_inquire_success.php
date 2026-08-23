<?php defined('BASEPATH') or exit('No direct script access allowed'); include __DIR__ . '/_public_head.php'; ?>
    <div class="card">
        <div class="card-head" style="text-align:center">
            <div style="width:60px;height:60px;border-radius:50%;background:var(--green);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:10px"><i class="fa fa-check"></i></div>
            <h2>Thank you!</h2>
            <p>One of our team will call you shortly to answer your questions and book a visit to the academy.</p>
        </div>
        <div class="card-body" style="text-align:center">
            <?php if (get_option('shra_contact_line')) { ?><div class="hint" style="margin-bottom:14px">Can't wait? <?php echo html_escape(get_option('shra_contact_line')); ?></div><?php } ?>
            <a href="<?php echo site_url('join'); ?>" class="btn ghost"><i class="fa-solid fa-graduation-cap"></i> Register as a rider now</a>
        </div>
    </div>
<?php include __DIR__ . '/_public_foot.php'; ?>
