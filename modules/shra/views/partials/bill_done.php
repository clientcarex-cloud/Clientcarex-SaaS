<?php defined('BASEPATH') or exit('No direct script access allowed'); $dup = !empty($duplicate); ?>
<div class="shra-card" style="border-color:<?php echo $dup ? '#e8b9b6' : '#cfe0c0'; ?>;background:<?php echo $dup ? '#fdf5f4' : '#f7faf3'; ?>">
    <div class="shra-card-body" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
        <span class="shra-avatar" style="background:<?php echo $dup ? 'var(--red)' : 'var(--green)'; ?>;color:#fff;width:44px;height:44px"><i class="fa <?php echo $dup ? 'fa-rotate-left' : 'fa-check'; ?>"></i></span>
        <div style="flex:1;min-width:240px">
            <div class="serif" style="font-size:20px;font-weight:700"><?php echo $dup ? 'Already billed' : 'Billed'; ?> <?php echo html_escape($e->full_name); ?> — <?php echo html_escape($e->package_name); ?></div>
            <div class="shra-muted" style="font-size:12.5px"><?php echo $dup ? 'This form was submitted twice — the earlier bill was kept and nothing was charged again. ' : ''; ?><?php echo (int) $e->sessions_total; ?> sessions · <?php echo shra_money($e->paid_real); ?> received<?php echo $e->payment_mode ? ' via ' . html_escape($e->payment_mode) : ''; ?> · Invoice <?php echo html_escape(format_invoice_number($e->invoice_id)); ?> · <?php echo shra_pay_badge($e); ?><?php echo $e->schedule ? ' · ' . html_escape($e->schedule) : ''; ?></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="<?php echo admin_url('shra/receipt_pdf/' . $e->id); ?>" target="_blank" class="shra-btn shra-btn-gold shra-btn-sm"><i class="fa-solid fa-receipt"></i> Receipt</a>
            <?php if ($e->due > 0.009 && shra_can_billing()) { ?><button type="button" class="shra-btn shra-btn-outline shra-btn-sm shra-collect" data-id="<?php echo $e->id; ?>" data-due="<?php echo $e->due; ?>" data-name="<?php echo html_escape($e->full_name); ?>"><i class="fa-solid fa-hand-holding-dollar"></i> Collect <?php echo shra_money($e->due); ?></button><?php } ?>
            <?php if ($e->rider_type === 'learner') { ?><a href="<?php echo admin_url('shra/membership_pdf/' . $e->rider_id); ?>" target="_blank" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa-solid fa-id-card"></i> Membership</a><?php } ?>
            <?php if ($e->sessions_used < $e->sessions_total && $e->status === 'active') { ?><a href="<?php echo admin_url('shra/attendance?rider=' . $e->rider_id); ?>" class="shra-btn shra-btn-primary shra-btn-sm"><i class="fa-solid fa-clipboard-check"></i> Mark session</a><?php } else { ?><span class="shra-badge shra-badge-green" style="align-self:center"><i class="fa fa-check"></i> Session marked</span><?php } ?>
        </div>
    </div>
</div>
