<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * "Our WhatsApp" — let this tenant send on the PROVIDER's own WhatsApp number.
 *
 * Sits at the top of the allocation modal's WhatsApp tab because it decides
 * whether the credits underneath it are even used: a `free` grant means the
 * tenant's WhatsApp messages never touch that balance.
 *
 * The grant itself lives in the WhatsApp module's master registry
 * (tblwhatsapp_shared_grants) — this is a second face on the same data, not a
 * copy. See modules/whatsapp/helpers/whatsapp_shared_helper.php.
 *
 * @var array $wa_shared  settings / companies / slug / grant / allowed /
 *                        numbers / templates / usage / console
 * @var int   $client_id
 */
$g        = $wa_shared['grant'];
$settings = $wa_shared['settings'];
$usage    = $wa_shared['usage'];

$val = function ($key, $default) use ($g) {
    return $g && isset($g->$key) ? $g->$key : $default;
};
$on = function ($key, $default = 1) use ($g) {
    return $g ? ((int) ($g->$key ?? $default) === 1) : (bool) $default;
};

$enabled       = $on('enabled', 0);
$billing       = (string) $val('billing_mode', 'credits');
$template_mode = (string) $val('template_mode', 'selected');
?>
<div class="panel panel-default" id="wa-shared-panel" style="margin-bottom:15px; border-color:#25d366;">
    <div class="panel-heading" style="padding:10px 15px; background:#25d3661a; border-color:#25d366; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
        <strong style="color:#128c7e;">
            <i class="fa fa-whatsapp"></i> <?php echo _l('ccx_msgs_shared_wa_title'); ?>
        </strong>
        <label style="margin:0; font-weight:normal; cursor:pointer; display:flex; align-items:center; gap:6px; font-size:12px;">
            <input type="checkbox" name="wa_shared_enabled" id="wa_shared_enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?> style="margin:0;">
            <?php echo _l('ccx_msgs_shared_wa_enable'); ?>
        </label>
    </div>

    <div class="panel-body" style="padding:15px;">
        <input type="hidden" name="wa_shared_slug" id="wa_shared_slug" value="<?php echo htmlspecialchars($wa_shared['slug']); ?>">

        <p class="text-muted" style="margin-top:0; font-size:12px;">
            <?php echo _l('ccx_msgs_shared_wa_lead'); ?>
        </p>

        <?php if (!$settings['enabled']): ?>
            <div class="alert alert-warning" style="padding:8px 12px; font-size:12px;">
                <i class="fa fa-exclamation-triangle"></i>
                <?php echo _l('ccx_msgs_shared_wa_global_off'); ?>
                <a href="<?php echo $wa_shared['console']; ?>" target="_blank" rel="noopener"><?php echo _l('ccx_msgs_shared_wa_open_console'); ?></a>
            </div>
        <?php endif; ?>

        <?php if (empty($wa_shared['numbers'])): ?>
            <div class="alert alert-warning" style="padding:8px 12px; font-size:12px;">
                <i class="fa fa-exclamation-triangle"></i>
                <?php echo _l('ccx_msgs_shared_wa_no_number'); ?>
                <a href="<?php echo $wa_shared['console']; ?>" target="_blank" rel="noopener"><?php echo _l('ccx_msgs_shared_wa_open_console'); ?></a>
            </div>
        <?php endif; ?>

        <?php if (count($wa_shared['companies']) > 1): ?>
            <?php // This client owns several instances — each has its own grant. ?>
            <div class="form-group">
                <label class="control-label"><?php echo _l('ccx_msgs_shared_wa_instance'); ?></label>
                <select class="form-control" id="wa_shared_instance">
                    <?php foreach ($wa_shared['companies'] as $c): ?>
                        <option value="<?php echo htmlspecialchars($c->slug); ?>" <?php echo $c->slug === $wa_shared['slug'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c->name . ' (' . $c->slug . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted"><?php echo _l('ccx_msgs_shared_wa_instance_hint'); ?></small>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label"><?php echo _l('ccx_msgs_shared_wa_billing'); ?></label>
                    <select name="wa_shared_billing" class="form-control">
                        <option value="free" <?php echo $billing === 'free' ? 'selected' : ''; ?>><?php echo _l('ccx_msgs_shared_wa_free_opt'); ?></option>
                        <option value="credits" <?php echo $billing !== 'free' ? 'selected' : ''; ?>><?php echo _l('ccx_msgs_shared_wa_credits_opt'); ?></option>
                    </select>
                    <small class="text-muted"><?php echo _l('ccx_msgs_shared_wa_billing_hint'); ?></small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label"><?php echo _l('ccx_msgs_shared_wa_sender'); ?></label>
                    <select name="wa_shared_number" class="form-control">
                        <option value=""><?php echo _l('ccx_msgs_shared_wa_sender_default'); ?></option>
                        <?php foreach ($wa_shared['numbers'] as $n): ?>
                            <option value="<?php echo htmlspecialchars($n->phone_number_id); ?>"
                                <?php echo (string) $val('phone_number_id', '') === (string) $n->phone_number_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($n->display_phone_number ?: $n->phone_number_id); ?>
                                <?php echo $n->verified_name ? ' — ' . htmlspecialchars($n->verified_name) : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label class="control-label"><?php echo _l('ccx_msgs_shared_wa_daily'); ?></label>
                    <input type="number" min="0" class="form-control" name="wa_shared_daily" value="<?php echo (int) $val('daily_limit', 0); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label class="control-label"><?php echo _l('ccx_msgs_shared_wa_monthly'); ?></label>
                    <input type="number" min="0" class="form-control" name="wa_shared_monthly" value="<?php echo (int) $val('monthly_limit', 0); ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label"><?php echo _l('ccx_msgs_shared_wa_allow'); ?></label>
                    <div style="padding-top:6px;">
                        <label style="font-weight:normal; margin-right:14px; font-size:12px;">
                            <input type="checkbox" name="wa_shared_allow_send" value="1" <?php echo $on('allow_send') ? 'checked' : ''; ?>>
                            <?php echo _l('ccx_msgs_shared_wa_allow_send'); ?>
                        </label>
                        <label style="font-weight:normal; margin-right:14px; font-size:12px;">
                            <input type="checkbox" name="wa_shared_allow_bulk" value="1" <?php echo $on('allow_bulk') ? 'checked' : ''; ?>>
                            <?php echo _l('ccx_msgs_shared_wa_allow_bulk'); ?>
                        </label>
                        <label style="font-weight:normal; font-size:12px;">
                            <input type="checkbox" name="wa_shared_allow_hooks" value="1" <?php echo $on('allow_hooks') ? 'checked' : ''; ?>>
                            <?php echo _l('ccx_msgs_shared_wa_allow_hooks'); ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-bottom:8px;">
            <label class="control-label"><?php echo _l('ccx_msgs_shared_wa_templates'); ?></label>
            <div>
                <label style="font-weight:normal; margin-right:14px; font-size:12px;">
                    <input type="radio" name="wa_shared_template_mode" value="selected" class="wa-shared-tplmode" <?php echo $template_mode !== 'all' ? 'checked' : ''; ?>>
                    <?php echo _l('ccx_msgs_shared_wa_tpl_selected'); ?>
                </label>
                <label style="font-weight:normal; font-size:12px;">
                    <input type="radio" name="wa_shared_template_mode" value="all" class="wa-shared-tplmode" <?php echo $template_mode === 'all' ? 'checked' : ''; ?>>
                    <?php echo _l('ccx_msgs_shared_wa_tpl_all'); ?>
                </label>
            </div>
            <small class="text-muted"><?php echo _l('ccx_msgs_shared_wa_tpl_hint'); ?></small>
        </div>

        <div id="wa-shared-templates" style="<?php echo $template_mode === 'all' ? 'display:none;' : ''; ?> max-height:220px; overflow-y:auto; border:1px solid #e5e7eb; border-radius:4px; padding:8px; margin-bottom:12px;">
            <?php if (empty($wa_shared['templates'])): ?>
                <p class="text-muted" style="margin:0; font-size:12px;">
                    <?php echo _l('ccx_msgs_shared_wa_no_templates'); ?>
                    <a href="<?php echo $wa_shared['console']; ?>" target="_blank" rel="noopener"><?php echo _l('ccx_msgs_shared_wa_open_console'); ?></a>
                </p>
            <?php else: ?>
                <div style="margin-bottom:6px; padding-bottom:6px; border-bottom:1px solid #f3f4f6;">
                    <a href="#" class="wa-shared-tpl-all" data-on="1"><?php echo _l('ccx_msgs_shared_wa_select_all'); ?></a> ·
                    <a href="#" class="wa-shared-tpl-all" data-on="0"><?php echo _l('ccx_msgs_shared_wa_select_none'); ?></a>
                </div>
                <?php foreach ($wa_shared['templates'] as $t): ?>
                    <?php $key = $t->name . '|' . $t->language; ?>
                    <label style="display:block; font-weight:normal; font-size:12px; padding:4px 2px; margin:0;">
                        <input type="checkbox" name="wa_shared_templates[]" value="<?php echo htmlspecialchars($key); ?>"
                            <?php echo isset($wa_shared['allowed'][$key]) ? 'checked' : ''; ?>>
                        <strong><?php echo htmlspecialchars($t->name); ?></strong>
                        <span class="text-muted">
                            <?php echo htmlspecialchars($t->language); ?><?php echo $t->category ? ' · ' . htmlspecialchars(strtolower($t->category)) : ''; ?>
                        </span>
                        <br>
                        <span class="text-muted" style="padding-left:20px;"><?php echo htmlspecialchars(mb_substr((string) $t->body_text, 0, 120)); ?></span>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="form-group" style="margin-bottom:8px;">
            <label class="control-label"><?php echo _l('ccx_msgs_shared_wa_notes'); ?></label>
            <input type="text" class="form-control" name="wa_shared_notes" maxlength="480"
                   value="<?php echo htmlspecialchars((string) $val('notes', '')); ?>"
                   placeholder="<?php echo _l('ccx_msgs_shared_wa_notes_ph'); ?>">
        </div>

        <?php if ($g): ?>
            <p class="text-muted" style="margin:0; font-size:12px;">
                <i class="fa fa-bar-chart"></i>
                <?php echo sprintf(
                    _l('ccx_msgs_shared_wa_usage'),
                    number_format((int) $usage['today']),
                    number_format((int) $usage['month']),
                    number_format((int) $usage['credits_month'])
                ); ?>
            </p>
        <?php endif; ?>
    </div>
</div>
