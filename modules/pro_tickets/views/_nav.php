<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Shared module header + tab navigation.
 * Expects $active in: dashboard | tickets | kanban | new | settings
 */
$ptk_tabs = [
    'dashboard' => ['url' => admin_url('pro_tickets'), 'icon' => 'fa-solid fa-gauge-high', 'label' => _l('pro_tickets_dashboard')],
    'tickets'   => ['url' => admin_url('pro_tickets/tickets'), 'icon' => 'fa-solid fa-inbox', 'label' => _l('pro_tickets_all_tickets')],
    'kanban'    => ['url' => admin_url('pro_tickets/kanban'), 'icon' => 'fa-solid fa-table-columns', 'label' => _l('pro_tickets_kanban')],
];
?>
<div class="ptk-header">
    <div class="ptk-header-brand">
        <h1 class="ptk-title"><i class="fa-solid fa-headset"></i> <?= _l('pro_tickets'); ?></h1>
    </div>
    <div class="ptk-tabs">
        <?php foreach ($ptk_tabs as $key => $tab): ?>
            <a href="<?= $tab['url']; ?>" class="ptk-tab <?= ($active ?? '') === $key ? 'active' : ''; ?>">
                <i class="<?= $tab['icon']; ?>"></i> <?= $tab['label']; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="ptk-header-actions">
        <?php if (pro_tickets_smart_admin_available()): ?>
            <?php /* The widget itself is injected on every admin page by the
                     app_admin_footer hook — this is just a discoverable handle
                     for the shortcut, whose label smart_ticket.js rewrites to
                     the platform notation (⌥⇧N on Mac). */ ?>
            <button type="button" class="ptk-btn ptk-btn-light" id="ptk-smart-report"
                    title="<?= _l('pro_tickets_smart_internal_hint'); ?>"
                    onclick="window.PstSmartTicket && window.PstSmartTicket.open();">
                <i class="fa-solid fa-wand-magic-sparkles"></i> <?= _l('pro_tickets_smart_report'); ?>
                <span class="ptk-kbd-hint" id="ptk-smart-kbd">Ctrl+Alt+N</span>
            </button>
        <?php endif; ?>
        <?php if (pro_tickets_staff_can_create()): ?>
            <a href="<?= admin_url('pro_tickets/new_ticket'); ?>" class="ptk-btn ptk-btn-primary <?= ($active ?? '') === 'new' ? 'active' : ''; ?>">
                <i class="fa fa-plus"></i> <?= _l('pro_tickets_new'); ?>
            </a>
        <?php endif; ?>
        <?php if (pro_tickets_staff_can_settings()): ?>
            <a href="<?= admin_url('pro_tickets/settings'); ?>" class="ptk-btn ptk-btn-light <?= ($active ?? '') === 'settings' ? 'active' : ''; ?>" title="<?= _l('pro_tickets_settings'); ?>">
                <i class="fa fa-gear"></i>
            </a>
        <?php endif; ?>
    </div>
</div>
