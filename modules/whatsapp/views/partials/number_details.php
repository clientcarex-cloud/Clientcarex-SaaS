<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Number details drawer — cached registry row on the left, whatever Meta
 * reports live on the right (so a stale cache is obvious at a glance).
 *
 * @var object      $number registry row
 * @var array|null  $live   live Graph payload
 * @var string|null $error  Graph error when the live read failed
 */
$meta = whatsapp_number_status_meta($number);

$rows = [
    'Registration'        => whatsapp_number_status_badge($number),
    'Can send'            => whatsapp_health_badge($number->health_can_send ?? ''),
    'Quality rating'      => whatsapp_quality_badge($number->quality_rating ?? ''),
    'Messaging limit'     => e(whatsapp_tier_label($number->messaging_limit_tier ?? '')),
    'Throughput'          => e(ucfirst(strtolower((string) ($number->throughput_level ?? '')))) ?: '—',
    'Display name'        => e($number->verified_name ?: '—'),
    'Name status'         => e(ucfirst(strtolower(str_replace('_', ' ', (string) ($number->name_status ?? ''))))) ?: '—',
    'Number verification' => e(ucfirst(strtolower(str_replace('_', ' ', (string) ($number->code_verification_status ?? ''))))) ?: '—',
    'Platform'            => e(ucfirst(strtolower(str_replace('_', ' ', (string) ($number->platform_type ?? ''))))) ?: '—',
    'Phone number ID'     => '<code class="wapi-code">' . e($number->phone_number_id) . '</code>',
    'WABA ID'             => '<code class="wapi-code">' . e($number->waba_id) . '</code>',
    'Last checked'        => e(whatsapp_time_ago($number->last_checked_at ?? null)),
];
?>
<div class="wapi-numdetails">

    <div class="wapi-numdetails-head" style="border-color:<?php echo $meta['color']; ?>40;background:<?php echo $meta['color']; ?>0d">
        <div>
            <strong class="wapi-numdetails-phone"><?php echo e($number->display_phone_number ?: $number->phone_number_id); ?></strong>
            <div class="wapi-muted"><?php echo e($meta['detail']); ?></div>
        </div>
        <?php echo whatsapp_number_status_badge($number); ?>
    </div>

    <?php if (whatsapp_number_unregistered($number)): ?>
        <div class="wapi-alert wapi-alert-danger">
            <i class="fa fa-triangle-exclamation"></i>
            <div>
                <strong><?php echo _l('wapi_not_registered_title'); ?></strong>
                <p><?php echo _l('wapi_not_registered_hint'); ?></p>
                <button class="wapi-btn wapi-btn-primary wapi-btn-sm"
                        onclick="wapiOpenRegister('<?php echo e($number->phone_number_id); ?>', '<?php echo e($number->display_phone_number); ?>')">
                    <i class="fa fa-key"></i> <?php echo _l('wapi_register_number'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($number->last_error)): ?>
        <div class="wapi-alert wapi-alert-warn">
            <i class="fa fa-circle-exclamation"></i>
            <div>
                <strong><?php echo _l('wapi_last_error'); ?></strong>
                <p><?php echo e($number->last_error); ?></p>
                <?php $h = whatsapp_error_hint('', $number->last_error); ?>
                <?php if ($h): ?><p class="wapi-muted"><?php echo e($h['hint']); ?></p><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($number->health_detail)): ?>
        <div class="wapi-alert wapi-alert-warn">
            <i class="fa fa-heart-pulse"></i>
            <div>
                <strong><?php echo _l('wapi_health_report'); ?></strong>
                <p><?php echo e($number->health_detail); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <table class="wapi-table wapi-kv-table">
        <tbody>
        <?php foreach ($rows as $label => $value): ?>
            <tr>
                <th><?php echo e($label); ?></th>
                <td><?php echo $value === '' ? '<span class="wapi-muted">—</span>' : $value; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($error): ?>
        <p class="wapi-muted"><i class="fa fa-cloud-slash"></i> <?php echo _l('wapi_live_read_failed'); ?>: <?php echo e($error); ?></p>
    <?php elseif ($live): ?>
        <p class="wapi-muted"><i class="fa fa-cloud-arrow-down"></i> <?php echo _l('wapi_live_from_meta'); ?></p>
    <?php endif; ?>

</div>
