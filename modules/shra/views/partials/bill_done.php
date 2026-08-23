<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="shra-card" style="border-color:#cfe0c0;background:#f7faf3">
    <div class="shra-card-body" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
        <span class="shra-avatar" style="background:var(--green);color:#fff;width:44px;height:44px"><i class="fa fa-check"></i></span>
        <div style="flex:1;min-width:240px">
            <div class="serif" style="font-size:20px;font-weight:700">Billed <?php echo html_escape($e->full_name); ?> — <?php echo html_escape($e->package_name); ?></div>
            <div class="shra-muted" style="font-size:12.5px"><?php echo (int) $e->sessions_total; ?> sessions · <?php echo shra_money($e->paid_amount); ?> received<?php echo $e->payment_mode ? ' via ' . html_escape($e->payment_mode) : ''; ?> · Invoice <?php echo html_escape(format_invoice_number($e->invoice_id)); ?><?php echo $e->invoice_status == 3 ? ' (partially paid)' : ''; ?></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="<?php echo site_url('invoice/' . $e->invoice_id . '/' . $e->invoice_hash); ?>" target="_blank" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa fa-print"></i> Print invoice</a>
            <a href="<?php echo admin_url('invoices/list_invoices/' . $e->invoice_id); ?>" target="_blank" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa fa-file-invoice"></i> Open in Sales</a>
            <?php if ($e->rider_type === 'learner') { ?><a href="<?php echo admin_url('shra/membership_pdf/' . $e->rider_id); ?>" target="_blank" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa-solid fa-id-card"></i> Membership PDF</a><?php } ?>
            <a href="<?php echo admin_url('shra/attendance?rider=' . $e->rider_id); ?>" class="shra-btn shra-btn-primary shra-btn-sm"><i class="fa-solid fa-clipboard-check"></i> Mark session</a>
        </div>
    </div>
</div>
