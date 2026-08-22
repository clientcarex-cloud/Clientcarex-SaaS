<?php

/**
 * This file add glass overlay to package customization to prevent customization
 * when use is on stripe enabled package trial. It is necessary to enforce user to 
 * subscribe first before customization as customization will often be lost when user
 * is redirected to stripe. This only apply when on trial and custom has not potentially link a card.
 */
defined('BASEPATH') or exit('No direct script access allowed');

if (!$on_trial || !$is_stripe_package) return;

?>

<div class="glass-overlay">
    <div class="glass-content">
        <p><?= _l('perfex_saas_subscribe_to_unlock_customization'); ?></p>
        <a href="<?= base_url('clients/packages/' . $invoice->slug . '/select'); ?>" class="btn btn-danger tw-text-wrap"
            <?php echo 'onclick="return confirm(\'' . perfex_saas_ecape_js_attr(_l('perfex_saas_upgrade_confirm_text')) . '\')"'; ?>>
            <i class="fa fa-check"></i>
            <?= $days_left > 0 ? _l('perfex_saas_view_subscription_invoice_trial', $days_left) : _l('perfex_saas_view_subscription_trial_over'); ?>
        </a>
    </div>
</div>