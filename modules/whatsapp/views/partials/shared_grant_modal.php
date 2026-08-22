<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Configure one tenant's access to the provider's WhatsApp number.
 *
 * Loaded over AJAX into the #wapi-shared-modal shell, so it renders the FORM
 * only — the surrounding box, head and footer live in dashboard.php.
 *
 * @var string      $slug             tenant being edited ('' = new grant)
 * @var object|null $grant            existing row
 * @var array       $allowed          'name|language' => true (current allowlist)
 * @var array       $taken            slugs that already have a grant
 * @var array       $shared_tenants   every SaaS company
 * @var array       $shared_numbers   the provider's own numbers
 * @var array       $shared_templates the provider's APPROVED templates
 */
$g = $grant;

$wapi_val = function ($key, $default) use ($g) {
    return $g && isset($g->$key) ? $g->$key : $default;
};
$wapi_on = function ($key, $default = 1) use ($g) {
    return $g ? ((int) ($g->$key ?? $default) === 1) : (bool) $default;
};

$wapi_mode = (string) $wapi_val('billing_mode', 'credits');
$wapi_tmod = (string) $wapi_val('template_mode', 'selected');
?>
<form id="wapi-shared-form" onsubmit="return wapiSaveSharedGrant(event)">

    <div class="wapi-field">
        <label><?php echo _l('wapi_shared_tenant'); ?></label>
        <?php if ($slug !== ''): ?>
            <input type="hidden" name="tenant_slug" value="<?php echo e($slug); ?>">
            <p class="wapi-static-value">
                <strong><?php echo e($wapi_val('tenant_name', $slug)); ?></strong>
                <span class="wapi-muted">· <?php echo e($slug); ?></span>
            </p>
        <?php else: ?>
            <select name="tenant_slug" required>
                <option value=""><?php echo _l('wapi_shared_pick_tenant'); ?></option>
                <?php foreach ($shared_tenants as $t): ?>
                    <?php if (in_array($t->slug, $taken, true)) { continue; } ?>
                    <option value="<?php echo e($t->slug); ?>"><?php echo e($t->name); ?> (<?php echo e($t->slug); ?>)</option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </div>

    <div class="wapi-grid-2">
        <div class="wapi-field">
            <label><?php echo _l('wapi_shared_number_label'); ?></label>
            <select name="phone_number_id">
                <option value=""><?php echo _l('wapi_shared_default_number'); ?></option>
                <?php foreach ($shared_numbers as $n): ?>
                    <option value="<?php echo e($n->phone_number_id); ?>"
                        <?php echo (string) $wapi_val('phone_number_id', '') === (string) $n->phone_number_id ? 'selected' : ''; ?>>
                        <?php echo e($n->display_phone_number ?: $n->phone_number_id); ?>
                        <?php echo $n->verified_name ? ' — ' . e($n->verified_name) : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="wapi-hint"><?php echo _l('wapi_shared_number_hint'); ?></small>
        </div>

        <div class="wapi-field">
            <label><?php echo _l('wapi_shared_billing'); ?></label>
            <select name="billing_mode">
                <option value="free" <?php echo $wapi_mode === 'free' ? 'selected' : ''; ?>><?php echo _l('wapi_shared_billing_free'); ?></option>
                <option value="credits" <?php echo $wapi_mode !== 'free' ? 'selected' : ''; ?>><?php echo _l('wapi_shared_billing_credits'); ?></option>
            </select>
            <small class="wapi-hint"><?php echo _l('wapi_shared_billing_hint'); ?></small>
        </div>
    </div>

    <div class="wapi-grid-2">
        <div class="wapi-field">
            <label><?php echo _l('wapi_shared_daily_limit'); ?></label>
            <input type="number" name="daily_limit" min="0" value="<?php echo (int) $wapi_val('daily_limit', 0); ?>">
            <small class="wapi-hint"><?php echo _l('wapi_shared_limit_hint'); ?></small>
        </div>
        <div class="wapi-field">
            <label><?php echo _l('wapi_shared_monthly_limit'); ?></label>
            <input type="number" name="monthly_limit" min="0" value="<?php echo (int) $wapi_val('monthly_limit', 0); ?>">
            <small class="wapi-hint"><?php echo _l('wapi_shared_limit_hint'); ?></small>
        </div>
    </div>

    <div class="wapi-field">
        <label><?php echo _l('wapi_shared_traffic'); ?></label>
        <div class="wapi-check-row">
            <label class="wapi-check"><input type="checkbox" name="allow_send" value="1" <?php echo $wapi_on('allow_send') ? 'checked' : ''; ?>> <?php echo _l('wapi_shared_traffic_send'); ?></label>
            <label class="wapi-check"><input type="checkbox" name="allow_bulk" value="1" <?php echo $wapi_on('allow_bulk') ? 'checked' : ''; ?>> <?php echo _l('wapi_shared_traffic_bulk'); ?></label>
            <label class="wapi-check"><input type="checkbox" name="allow_hooks" value="1" <?php echo $wapi_on('allow_hooks') ? 'checked' : ''; ?>> <?php echo _l('wapi_shared_traffic_hooks'); ?></label>
        </div>
    </div>

    <div class="wapi-field">
        <label><?php echo _l('wapi_shared_templates_label'); ?></label>
        <div class="wapi-check-row">
            <label class="wapi-check">
                <input type="radio" name="template_mode" value="selected" <?php echo $wapi_tmod !== 'all' ? 'checked' : ''; ?> onchange="wapiSharedTemplateMode()">
                <?php echo _l('wapi_shared_tpl_selected'); ?>
            </label>
            <label class="wapi-check">
                <input type="radio" name="template_mode" value="all" <?php echo $wapi_tmod === 'all' ? 'checked' : ''; ?> onchange="wapiSharedTemplateMode()">
                <?php echo _l('wapi_shared_tpl_all'); ?>
            </label>
        </div>
        <small class="wapi-hint"><?php echo _l('wapi_shared_tpl_hint'); ?></small>
    </div>

    <div id="wapi-shared-template-list" class="wapi-shared-templates" style="<?php echo $wapi_tmod === 'all' ? 'display:none' : ''; ?>">
        <?php if (empty($shared_templates)): ?>
            <div class="wapi-empty wapi-empty-sm"><p><?php echo _l('wapi_shared_no_templates'); ?></p></div>
        <?php else: ?>
            <div class="wapi-shared-tpl-tools">
                <button type="button" class="wapi-btn wapi-btn-ghost wapi-btn-sm" onclick="wapiSharedTplAll(true)"><?php echo _l('wapi_shared_select_all'); ?></button>
                <button type="button" class="wapi-btn wapi-btn-ghost wapi-btn-sm" onclick="wapiSharedTplAll(false)"><?php echo _l('wapi_shared_select_none'); ?></button>
            </div>
            <?php foreach ($shared_templates as $t): ?>
                <?php $wapi_key = $t->name . '|' . $t->language; ?>
                <label class="wapi-shared-tpl">
                    <input type="checkbox" name="templates[]" value="<?php echo e($wapi_key); ?>"
                        <?php echo isset($allowed[$wapi_key]) ? 'checked' : ''; ?>>
                    <span class="wapi-shared-tpl-main">
                        <strong><?php echo e($t->name); ?></strong>
                        <span class="wapi-muted"><?php echo e($t->language); ?><?php echo $t->category ? ' · ' . e(strtolower($t->category)) : ''; ?></span>
                        <span class="wapi-shared-tpl-body"><?php echo e(mb_substr((string) $t->body_text, 0, 140)); ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="wapi-field">
        <label><?php echo _l('wapi_shared_notes'); ?></label>
        <input type="text" name="notes" maxlength="480" value="<?php echo e($wapi_val('notes', '')); ?>" placeholder="<?php echo _l('wapi_shared_notes_ph'); ?>">
    </div>

    <span class="wapi-switch-label">
        <label class="wapi-switch">
            <input type="checkbox" name="enabled" value="1" <?php echo $wapi_on('enabled', 1) ? 'checked' : ''; ?>>
            <span class="wapi-slider"></span>
        </label>
        <?php echo _l('wapi_shared_grant_enabled'); ?>
    </span>

    <div class="wapi-modal-foot">
        <button type="button" class="wapi-btn wapi-btn-light" onclick="wapiCloseModal('wapi-shared-modal')"><?php echo _l('close'); ?></button>
        <button type="submit" class="wapi-btn wapi-btn-primary"><i class="fa fa-floppy-disk"></i> <?php echo _l('submit'); ?></button>
    </div>
</form>
