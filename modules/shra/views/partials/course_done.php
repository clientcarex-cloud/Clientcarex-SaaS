<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="shra-card" style="border-color:#e6d5a4;background:linear-gradient(135deg,#fffaf0,#f8efd9)">
    <div class="shra-card-body" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
        <span class="shra-avatar" style="background:var(--gold);color:#fff;width:44px;height:44px"><i class="fa-solid fa-award"></i></span>
        <div style="flex:1;min-width:240px">
            <div class="serif" style="font-size:20px;font-weight:700"><?php echo html_escape($e->full_name); ?> completed the <?php echo html_escape($e->package_name); ?> course!</div>
            <div class="shra-muted" style="font-size:12.5px">All <?php echo (int) $e->sessions_total; ?> sessions attended<?php echo $e->certificate_no ? ' · Certificate ' . html_escape($e->certificate_no) . ' issued' : ($e->is_guest ? '' : ' · certificate pending'); ?></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php if ($e->certificate_no) { ?><a href="<?php echo admin_url('shra/certificate_pdf/' . $e->id); ?>" target="_blank" class="shra-btn shra-btn-gold shra-btn-sm"><i class="fa-solid fa-award"></i> Print certificate</a>
            <?php } elseif (!$e->is_guest && shra_can('edit')) { ?><a href="<?php echo admin_url('shra/certificate/' . $e->id); ?>" class="shra-btn shra-btn-gold shra-btn-sm"><i class="fa-solid fa-award"></i> Issue certificate</a><?php } ?>
            <a href="<?php echo admin_url('shra/billing?rider=' . $e->rider_id); ?>" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa-solid fa-cash-register"></i> Renew package</a>
        </div>
    </div>
</div>
