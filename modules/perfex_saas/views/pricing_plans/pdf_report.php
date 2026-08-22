<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * HTML rendered by Pricing_plans_pdf via TCPDF writeHTML().
 * Keep markup simple — TCPDF supports only a subset of HTML/CSS.
 *
 * @var array  $rows
 * @var string $report_title
 * @var object $currency
 * @var string $company_name
 * @var string $logo_path
 */
?>
<table cellpadding="2" cellspacing="0" style="width:100%;">
    <tr>
        <td style="width:60%;vertical-align:middle;">
            <?php if (!empty($logo_path)) : ?>
            <img src="<?= $logo_path; ?>" height="38" />
            <?php elseif (!empty($company_name)) : ?>
            <span style="font-size:16px;font-weight:bold;"><?= e($company_name); ?></span>
            <?php endif; ?>
        </td>
        <td style="width:40%;text-align:right;vertical-align:middle;">
            <span style="font-size:16px;font-weight:bold;color:#4f46e5;"><?= e($report_title); ?></span><br />
            <span style="color:#888888;font-size:8px;">
                <?= _l('perfex_saas_pricing_plans_generated_on'); ?>: <?= date('Y-m-d H:i'); ?>
            </span>
        </td>
    </tr>
</table>
<div style="border-bottom:2px solid #4f46e5;margin:4px 0 10px 0;">&nbsp;</div>

<table border="1" cellpadding="4" cellspacing="0" style="font-size:9px;">
    <thead>
        <tr style="background-color:#f0f0f0;font-weight:bold;">
            <td><?= _l('perfex_saas_pricing_plans_group'); ?></td>
            <td><?= _l('perfex_saas_plan'); ?></td>
            <td><?= _l('perfex_saas_pricing_plans_period'); ?></td>
            <td align="right"><?= _l('perfex_saas_price'); ?> (<?= $currency->name; ?>)</td>
            <td align="right"><?= _l('perfex_saas_pricing_plans_trial'); ?></td>
            <td align="right"><?= _l('perfex_saas_pricing_plans_users'); ?></td>
            <td align="right"><?= _l('perfex_saas_pricing_plans_modules'); ?></td>
            <td align="center"><?= _l('perfex_saas_status'); ?></td>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $r) : ?>
        <tr>
            <td><?= e($r['group']); ?></td>
            <td><?= e($r['plan']); ?></td>
            <td><?= e($r['period']); ?></td>
            <td align="right"><?= e($r['price']); ?></td>
            <td align="right"><?= e($r['trial']); ?></td>
            <td align="right"><?= e($r['users']); ?></td>
            <td align="right"><?= e($r['modules']); ?></td>
            <td align="center"><?= e($r['status']); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
