<?php
/**
 * Per-channel sending switch, rendered inside one balance card.
 *
 * Only a superadmin gets the toggle — for everyone else the card carries a
 * plain "paused" badge when sending is off, and nothing at all when it is on.
 *
 * Expected variables:
 *   $switch_channel  canonical channel id (sms | official_whatsapp | email | ai_call_agent)
 *   $switch_subtype  'promo' | 'trans'
 */

$_sw_subtype = (isset($switch_subtype) && $switch_subtype === 'promo') ? 'promo' : 'trans';
$_sw_on      = function_exists('ccx_channel_send_enabled')
    ? ccx_channel_send_enabled($switch_channel, $_sw_subtype)
    : true;

if (!is_admin()) {
    if (!$_sw_on) { ?>
        <span class="bc-inactive-badge"><i class="fa fa-pause"></i> Sending Paused</span>
    <?php }

    return;
}

$_sw_id = 'chsend_' . _ccx_channel_to_col_prefix($switch_channel) . '_' . $_sw_subtype;
?>
<div class="bc-send-switch<?= $_sw_on ? '' : ' is-off'; ?>">
    <label class="ccx-switch bc-switch">
        <input type="checkbox" id="<?= $_sw_id; ?>" class="channel-send-switch"
            data-channel="<?= html_escape($switch_channel); ?>"
            data-subtype="<?= $_sw_subtype; ?>" <?= $_sw_on ? 'checked' : ''; ?>>
        <span class="slider"></span>
    </label>
    <span class="bc-send-switch-text">
        <?= $_sw_on ? 'Sending ON' : 'Sending OFF'; ?>
    </span>
</div>
