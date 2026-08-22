<!-- Hooks Accordion — loaded by each channel tab -->
<!-- Pass $hooks_channel variable before including this partial -->
<div class="ccx-templates-section">
    <div class="ccx-templates-header ccx-hooks-header">
        <h5><i class="fa fa-plug" style="margin-right:8px; color:#6366f1;"></i>System Hooks</h5>

        <div class="ccx-hooks-tools">
            <div class="ccx-hooks-search">
                <i class="fa fa-search"></i>
                <input type="text" class="hooks-search-input" data-channel="<?= $hooks_channel; ?>"
                    id="<?= $hooks_channel; ?>-hooks-search" placeholder="Search hooks, modules, descriptions…"
                    autocomplete="off">
                <a href="#" class="ccx-hooks-search-clear" data-channel="<?= $hooks_channel; ?>" title="Clear"
                    style="display:none;"><i class="fa fa-times-circle"></i></a>
            </div>

            <button type="button" class="ccx-hooks-toggle-all" data-channel="<?= $hooks_channel; ?>"
                data-state="collapsed">
                <i class="fa fa-angle-double-down"></i> <span>Expand all</span>
            </button>

            <?php // Send logs are their own capability — see sms_wa_email_feature_map() ?>
            <?php if (!empty($can_logs)): ?>
            <button type="button" class="ccx-add-btn view-hook-logs-btn" data-channel="<?= $hooks_channel; ?>"
                style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none;">
                <i class="fa fa-list-ul"></i> View Logs
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="ccx-hooks-accordion" id="<?= $hooks_channel; ?>-hooks-wrapper">
        <div class="ccx-hooks-state">
            <i class="fa fa-spinner fa-spin" style="margin-right:6px;"></i> Loading hooks…
        </div>
    </div>
</div>
