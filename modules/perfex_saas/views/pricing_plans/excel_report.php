<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Excel (.xls) export — an HTML table that Excel opens natively.
 *
 * @var array  $rows     flat export rows
 * @var object $currency base currency
 */
?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<table border="1">
    <thead>
        <tr>
            <th><?= _l('perfex_saas_pricing_plans_group'); ?></th>
            <th><?= _l('perfex_saas_plan'); ?></th>
            <th><?= _l('perfex_saas_pricing_plans_period'); ?></th>
            <th><?= _l('perfex_saas_price'); ?> (<?= $currency->name; ?>)</th>
            <th><?= _l('perfex_saas_pricing_plans_trial'); ?></th>
            <th><?= _l('perfex_saas_pricing_plans_users'); ?></th>
            <th><?= _l('perfex_saas_pricing_plans_modules'); ?></th>
            <th><?= _l('perfex_saas_status'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $r) : ?>
        <tr>
            <td><?= e($r['group']); ?></td>
            <td><?= e($r['plan']); ?></td>
            <td><?= e($r['period']); ?></td>
            <td><?= e($r['price']); ?></td>
            <td><?= e($r['trial']); ?></td>
            <td><?= e($r['users']); ?></td>
            <td><?= e($r['modules']); ?></td>
            <td><?= e($r['status']); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
