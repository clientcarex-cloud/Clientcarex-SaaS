<?php
/**
 * Fallback for installations with no message allocation row: the balance cards
 * never render there, so the superadmin would have nowhere to reach the
 * per-channel sending switches. Draws the same two cards, credit figures
 * replaced by the switch itself.
 *
 * Expected variables:
 *   $switch_channel  canonical channel id (sms | official_whatsapp | email | ai_call_agent)
 */

if (!is_admin()) {
    return;
}
?>
<div class="ccx-balance-row">
    <?php foreach (['promo' => 'Promotional Sending', 'trans' => 'Transactional Sending'] as $_sc_subtype => $_sc_label): ?>
        <div class="ccx-balance-card bc-slate">
            <div class="bc-label"><?= $_sc_label; ?></div>
            <div class="bc-expiry" style="margin-top:2px;">No credit allocation on this installation</div>
            <?php $this->load->view('tabs/_channel_switch', [
                'switch_channel' => $switch_channel,
                'switch_subtype' => $_sc_subtype,
            ]); ?>
        </div>
    <?php endforeach; ?>
</div>
