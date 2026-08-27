<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php include __DIR__ . '/_styles.php'; ?>
<div class="ptkc-wrap">

    <?php include __DIR__ . '/_smart_ticket_loader.php'; ?>
    <?php include __DIR__ . '/_smart_ticket.php'; ?>

    <div class="ptkc-header">
        <h4 class="ptkc-title">
            <i class="fa-solid fa-headset"></i>
            <?= _l('pro_tickets_customer_success'); ?>
        </h4>
        <div class="ptkc-header-actions">
            <a href="<?= site_url('clients/pro_tickets/open'); ?>" class="ptkc-btn ptkc-btn-primary">
                <i class="fa-solid fa-plus"></i> <?= _l('clients_ticket_open_subject'); ?>
            </a>
        </div>
    </div>

    <div class="ptkc-deptgrid">
        <?php foreach ($dept_cards as $card) { ?>
        <a href="<?= e($card['url']); ?>" class="ptkc-deptcard">
            <?php if ($card['unread'] > 0) { ?>
            <span class="ptkc-deptcard-badge"><?= (int) $card['unread']; ?></span>
            <?php } ?>
            <span class="ptkc-deptcard-icon"><i class="fa-solid <?= e($card['icon']); ?>"></i></span>
            <span class="ptkc-deptcard-name"><?= e($card['label']); ?></span>
            <span class="ptkc-deptcard-total"><?= (int) $card['total']; ?></span>
            <span class="ptkc-deptcard-stats">
                <?php $has_chip = false; ?>
                <?php foreach ($card['statuses'] as $st) { if ($st['count'] <= 0) { continue; } $has_chip = true; ?>
                <span class="ptkc-deptcard-chip">
                    <span class="dot" style="background:<?= e($st['statuscolor']); ?>"></span>
                    <?= e($st['count']); ?> <?= e($st['translated_name']); ?>
                </span>
                <?php } ?>
                <?php if (!$has_chip) { ?>
                <span class="ptkc-deptcard-chip is-empty"><?= _l('pro_tickets_dept_no_tickets'); ?></span>
                <?php } ?>
            </span>
        </a>
        <?php } ?>
    </div>

    <?php include __DIR__ . '/_feedback.php'; ?>

</div>
