<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Provider console — the tenants currently sending on OUR WhatsApp number.
 *
 * Rendered inline on the master's Overview tab and re-rendered on its own by
 * Whatsapp::shared_console() after a grant is saved, so it must not depend on
 * anything the surrounding page sets up.
 *
 * @var array  $shared_settings  enabled / number / brand
 * @var array  $shared_grants    tenant_slug => grant row (+ usage columns)
 * @var array  $shared_tenants   every SaaS company, for the "add" picker
 * @var array  $shared_numbers   the provider's own registry numbers
 */
$wapi_free_month = 0;
$wapi_paid_month = 0;
$wapi_live       = 0;
foreach ($shared_grants as $g) {
    if ((int) $g->enabled !== 1) {
        continue;
    }
    $wapi_live++;
    if ((string) $g->billing_mode === 'free') {
        $wapi_free_month += (int) $g->used_month;
    } else {
        $wapi_paid_month += (int) $g->used_month;
    }
}
$wapi_ungranted = 0;
foreach ($shared_tenants as $t) {
    if (!isset($shared_grants[$t->slug])) {
        $wapi_ungranted++;
    }
}
?>

<div class="wapi-shared-summary">
    <div class="wapi-shared-kpi">
        <span class="wapi-shared-kpi-num"><?php echo (int) $wapi_live; ?></span>
        <span class="wapi-shared-kpi-label"><?php echo _l('wapi_shared_kpi_live'); ?></span>
    </div>
    <div class="wapi-shared-kpi">
        <span class="wapi-shared-kpi-num"><?php echo number_format($wapi_free_month); ?></span>
        <span class="wapi-shared-kpi-label"><?php echo _l('wapi_shared_kpi_free'); ?></span>
    </div>
    <div class="wapi-shared-kpi">
        <span class="wapi-shared-kpi-num"><?php echo number_format($wapi_paid_month); ?></span>
        <span class="wapi-shared-kpi-label"><?php echo _l('wapi_shared_kpi_billed'); ?></span>
    </div>
    <div class="wapi-shared-kpi">
        <span class="wapi-shared-kpi-num"><?php echo (int) $wapi_ungranted; ?></span>
        <span class="wapi-shared-kpi-label"><?php echo _l('wapi_shared_kpi_available'); ?></span>
    </div>
</div>

<?php if (empty($shared_numbers)): ?>
    <div class="wapi-alert wapi-alert-warn">
        <i class="fa fa-triangle-exclamation"></i>
        <div>
            <strong><?php echo _l('wapi_shared_no_number_title'); ?></strong>
            <p><?php echo _l('wapi_shared_no_number_body'); ?></p>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($shared_grants)): ?>
    <div class="wapi-empty wapi-empty-sm">
        <i class="fa fa-handshake-angle"></i>
        <p><?php echo _l('wapi_shared_none_yet'); ?></p>
    </div>
<?php else: ?>
    <table class="wapi-table">
        <thead><tr>
            <th><?php echo _l('wapi_shared_col_tenant'); ?></th>
            <th><?php echo _l('wapi_shared_col_status'); ?></th>
            <th><?php echo _l('wapi_shared_col_billing'); ?></th>
            <th><?php echo _l('wapi_shared_col_number'); ?></th>
            <th><?php echo _l('wapi_shared_col_templates'); ?></th>
            <th><?php echo _l('wapi_shared_col_allowed'); ?></th>
            <th class="wapi-ta-r"><?php echo _l('wapi_shared_col_usage'); ?></th>
            <th class="wapi-ta-r"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($shared_grants as $g): ?>
            <?php
            $wapi_on     = (int) $g->enabled === 1;
            $wapi_number = null;
            foreach ($shared_numbers as $n) {
                if ((string) $n->phone_number_id === (string) $g->phone_number_id) {
                    $wapi_number = $n;
                }
            }
            // A grant with no pinned number rides on whatever the provider's
            // default is, so say that rather than leaving the cell empty.
            $wapi_number_label = $wapi_number
                ? ($wapi_number->display_phone_number ?: $wapi_number->phone_number_id)
                : _l('wapi_shared_default_number');

            $wapi_traffic = array_filter([
                (int) $g->allow_send === 1 ? _l('wapi_shared_traffic_send') : '',
                (int) $g->allow_bulk === 1 ? _l('wapi_shared_traffic_bulk') : '',
                (int) $g->allow_hooks === 1 ? _l('wapi_shared_traffic_hooks') : '',
            ]);
            ?>
            <tr class="<?php echo $wapi_on ? '' : 'wapi-row-muted'; ?>">
                <td>
                    <strong><?php echo e($g->tenant_name ?: $g->tenant_slug); ?></strong>
                    <br><small class="wapi-muted"><?php echo e($g->tenant_slug); ?></small>
                </td>
                <td>
                    <?php echo $wapi_on
                        ? whatsapp_badge('#16a34a', _l('wapi_shared_on'))
                        : whatsapp_badge('#6b7280', _l('wapi_shared_off')); ?>
                </td>
                <td><?php echo whatsapp_shared_mode_badge($g->billing_mode); ?></td>
                <td><?php echo e($wapi_number_label); ?></td>
                <td>
                    <?php if ((string) $g->template_mode === 'all'): ?>
                        <span class="wapi-muted"><?php echo _l('wapi_shared_tpl_all'); ?></span>
                    <?php else: ?>
                        <?php echo (int) $g->template_count; ?>
                        <?php if ((int) $g->template_count === 0): ?>
                            <i class="fa fa-triangle-exclamation wapi-danger-text" title="<?php echo _l('wapi_shared_tpl_none_hint'); ?>"></i>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td><small class="wapi-muted"><?php echo $wapi_traffic ? e(implode(' · ', $wapi_traffic)) : _l('wapi_shared_traffic_none'); ?></small></td>
                <td class="wapi-ta-r">
                    <strong><?php echo number_format((int) $g->used_month); ?></strong>
                    <?php if ((int) $g->monthly_limit > 0): ?>
                        <small class="wapi-muted">/ <?php echo number_format((int) $g->monthly_limit); ?></small>
                    <?php endif; ?>
                    <br><small class="wapi-muted">
                        <?php echo sprintf(_l('wapi_shared_usage_today'), number_format((int) $g->used_today)); ?>
                        <?php if ((int) $g->credits_month > 0): ?>
                            · <?php echo sprintf(_l('wapi_shared_usage_credits'), number_format((int) $g->credits_month)); ?>
                        <?php endif; ?>
                    </small>
                </td>
                <td class="wapi-ta-r wapi-nowrap">
                    <button class="wapi-btn wapi-btn-light wapi-btn-sm" title="<?php echo _l('wapi_shared_configure'); ?>"
                            onclick="wapiSharedGrant('<?php echo e($g->tenant_slug); ?>')"><i class="fa fa-sliders"></i></button>
                    <button class="wapi-btn wapi-btn-ghost wapi-btn-sm" title="<?php echo $wapi_on ? _l('wapi_shared_disable') : _l('wapi_shared_enable'); ?>"
                            onclick="wapiSharedToggle('<?php echo e($g->tenant_slug); ?>')">
                        <i class="fa <?php echo $wapi_on ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                    </button>
                    <button class="wapi-btn wapi-btn-ghost wapi-btn-sm wapi-danger" title="<?php echo _l('wapi_shared_remove'); ?>"
                            onclick="wapiSharedRemove('<?php echo e($g->tenant_slug); ?>')"><i class="fa fa-trash"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
