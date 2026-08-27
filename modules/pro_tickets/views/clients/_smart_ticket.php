<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Smart Ticket — in-page feature guide (client portal ticket list only).
 *
 * The widget's assets + JS config are injected on EVERY client-area page via
 * the `app_customers_footer` hook (pro_tickets_smart_ticket_customers_footer()
 * → views/clients/_smart_ticket_loader.php), so the keyboard shortcut and the
 * floating launcher work portal-wide. This partial only renders the marketing
 * guide card, shown once on the ticket list.
 */
?>
<div class="pst-root" data-html2canvas-ignore="true">
    <div class="pst-guide" id="pstGuide">
        <div class="pst-guide-head">
            <span class="pst-guide-badge"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
            <div>
                <h4><?= _l('pro_tickets_smart_guide_title'); ?></h4>
                <p><?= _l('pro_tickets_smart_guide_sub'); ?></p>
            </div>
            <span class="pst-guide-tag"><?= _l('pro_tickets_smart_new'); ?></span>
        </div>
        <div class="pst-guide-steps">
            <div class="pst-guide-step">
                <span class="n">1</span>
                <div class="t"><i class="fa-solid fa-keyboard"></i> <?= _l('pro_tickets_smart_step1_t'); ?></div>
                <div class="d"><?= _l('pro_tickets_smart_step1_d'); ?></div>
            </div>
            <div class="pst-guide-step">
                <span class="n">2</span>
                <div class="t"><i class="fa-solid fa-highlighter"></i> <?= _l('pro_tickets_smart_step2_t'); ?></div>
                <div class="d"><?= _l('pro_tickets_smart_step2_d'); ?></div>
            </div>
            <div class="pst-guide-step">
                <span class="n">3</span>
                <div class="t"><i class="fa-solid fa-pen-to-square"></i> <?= _l('pro_tickets_smart_step3_t'); ?></div>
                <div class="d"><?= _l('pro_tickets_smart_step3_d'); ?></div>
            </div>
            <div class="pst-guide-step">
                <span class="n">4</span>
                <div class="t"><i class="fa-solid fa-paper-plane"></i> <?= _l('pro_tickets_smart_step4_t'); ?></div>
                <div class="d"><?= _l('pro_tickets_smart_step4_d'); ?></div>
            </div>
        </div>
        <div class="pst-guide-actions">
            <button type="button" class="pst-guide-launch" onclick="window.PstSmartTicket && window.PstSmartTicket.open();">
                <i class="fa-solid fa-wand-magic-sparkles"></i> <?= _l('pro_tickets_smart_try'); ?>
            </button>
            <span class="pst-guide-kbd">
                <?= _l('pro_tickets_smart_or_press'); ?> <span id="pstGuideKbd"><kbd>Ctrl</kbd>+<kbd>Alt</kbd>+<kbd>N</kbd></span>
            </span>
            <button type="button" class="pst-guide-dismiss" id="pstGuideDismiss"><?= _l('pro_tickets_smart_dismiss'); ?></button>
        </div>
    </div>

    <!-- Collapsed sticky info row — shown after the guide card is dismissed. -->
    <div class="pst-mini" id="pstMini" style="display:none;">
        <span class="pst-mini-badge"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
        <span class="pst-mini-text"><?= _l('pro_tickets_smart_mini_text'); ?></span>
        <span class="pst-mini-kbd" id="pstMiniKbd"><kbd>Ctrl</kbd>+<kbd>Alt</kbd>+<kbd>N</kbd></span>
        <button type="button" class="pst-mini-action" onclick="window.PstSmartTicket && window.PstSmartTicket.open();">
            <i class="fa-solid fa-camera"></i> <?= _l('pro_tickets_smart_mini_action'); ?>
        </button>
    </div>
</div>
<script>
(function () {
    var g = document.getElementById('pstGuide');
    var m = document.getElementById('pstMini');
    if (!g) { return; }

    // State: unset → full guide · 'mini' → collapsed sticky row (permanent).
    var state;
    try { state = localStorage.getItem('pst_guide_dismissed'); } catch (e) { state = null; }
    // '1' was the legacy "fully dismissed" flag — treat it as the new collapsed row.
    if (state === '1') { state = 'mini'; }

    function apply(s) {
        g.style.display = (s === 'mini') ? 'none' : '';
        if (m) { m.style.display = (s === 'mini') ? '' : 'none'; }
    }

    apply(state);

    var d = document.getElementById('pstGuideDismiss');
    if (d) {
        d.addEventListener('click', function () {
            apply('mini');
            try { localStorage.setItem('pst_guide_dismissed', 'mini'); } catch (e) {}
        });
    }
})();
</script>
