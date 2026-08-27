<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php include __DIR__ . '/_styles.php'; ?>
<div class="ptkc-wrap">

    <?php include __DIR__ . '/_smart_ticket_loader.php'; ?>
    <?php include __DIR__ . '/_smart_ticket.php'; ?>

    <div class="ptkc-header">
        <h4 class="ptkc-title">
            <i class="fa-solid <?= e($bucket_icon); ?>"></i>
            <?= e($bucket_label); ?>
        </h4>
        <div class="ptkc-header-actions">
            <a href="<?= site_url('clients/pro_tickets'); ?>" class="ptkc-btn ptkc-btn-ghost">
                <i class="fa-solid fa-arrow-left"></i> <?= _l('pro_tickets_back_departments'); ?>
            </a>
            <?php if (!empty($active_status)) { ?>
            <a href="<?= site_url('clients/pro_tickets/department/' . e($bucket_key)); ?>" class="ptkc-btn ptkc-btn-ghost">
                <i class="fa-solid fa-list"></i> <?= _l('pro_tickets_all_tickets'); ?>
            </a>
            <?php } ?>
            <a href="<?= site_url('clients/pro_tickets/open'); ?>" class="ptkc-btn ptkc-btn-primary">
                <i class="fa-solid fa-plus"></i> <?= _l('clients_ticket_open_subject'); ?>
            </a>
        </div>
    </div>

    <div class="ptkc-summary">
        <?php foreach ($statuses as $status) {
            $is_active = in_array($status['ticketstatusid'], $list_statuses) && !empty($active_status); ?>
        <a href="<?= site_url('clients/pro_tickets/department/' . e($bucket_key) . '/status/' . e($status['ticketstatusid'])); ?>"
            class="ptkc-stat<?= $is_active ? ' active' : ''; ?>">
            <span class="ptkc-stat-label" style="color:<?= e($status['statuscolor']); ?>">
                <span class="dot" style="background:<?= e($status['statuscolor']); ?>"></span>
                <?= e($status['translated_name']); ?>
            </span>
            <div class="ptkc-stat-value"><?= e($status['total_tickets']); ?></div>
        </a>
        <?php } ?>
    </div>

    <?php if (empty($tickets)) { ?>
    <div class="ptkc-empty">
        <i class="fa-regular fa-comments"></i>
        <div><?= _l('pro_tickets_portal_empty'); ?></div>
        <a href="<?= site_url('clients/pro_tickets/open'); ?>" class="ptkc-btn ptkc-btn-primary ptkc-mt">
            <i class="fa-solid fa-plus"></i> <?= _l('clients_ticket_open_subject'); ?>
        </a>
    </div>
    <?php } else { ?>
    <div class="ptkc-list">
        <?php foreach ($tickets as $ticket) {
            $url = site_url('clients/pro_tickets/ticket/' . $ticket['ticketid']); ?>
        <a href="<?= $url; ?>" class="ptkc-item<?= $ticket['clientread'] == 0 ? ' unread' : ''; ?>">
            <div class="ptkc-item-main">
                <div class="ptkc-item-subject">
                    <span class="ptkc-item-id">#<?= e($ticket['ticketid']); ?></span>
                    <span><?= e($ticket['subject']); ?></span>
                </div>
                <div class="ptkc-item-meta">
                    <?php if ($show_submitter_on_table) {
                        // Falls back to the ticket's own name/e-mail for submitters
                        // without a contact record here (staff of a tenant instance).
                        $submitter = $ticket['contactid'] != 0
                            ? trim($ticket['user_firstname'] . ' ' . $ticket['user_lastname'])
                            : ($ticket['from_name'] ?: $ticket['ticket_email']);
                    ?>
                    <span><i class="fa-regular fa-user"></i><?= e($submitter); ?></span>
                    <?php } ?>
                    <?php if (!empty($ticket['department_name'])) { ?>
                    <span><i class="fa-regular fa-building"></i><?= e($ticket['department_name']); ?></span>
                    <?php } ?>
                    <?php if (get_option('services') == 1 && !empty($ticket['service_name'])) { ?>
                    <span><i class="fa-solid fa-screwdriver-wrench"></i><?= e($ticket['service_name']); ?></span>
                    <?php } ?>
                    <span><i class="fa-solid fa-flag"></i><?= e(ticket_priority_translate($ticket['priority'])); ?></span>
                </div>
            </div>
            <?php $reply_count = $reply_counts[(int) $ticket['ticketid']] ?? 0; ?>
            <span class="ptkc-replies<?= $reply_count === 0 ? ' is-zero' : ''; ?>"
                title="<?= _l('pro_tickets_replies_badge'); ?>">
                <i class="fa-regular fa-comment-dots"></i> <?= (int) $reply_count; ?>
            </span>
            <div class="ptkc-item-side">
                <span class="ptkc-pill" style="background:<?= e($ticket['statuscolor']); ?>">
                    <?= e(ticket_status_translate($ticket['ticketstatusid'])); ?>
                </span>
                <div class="ptkc-lastreply">
                    <?php
                    if ($ticket['lastreply'] == null) {
                        echo _l('client_no_reply');
                    } else {
                        echo e(_dt($ticket['lastreply']));
                    } ?>
                </div>
            </div>
        </a>
        <?php } ?>
    </div>
    <?php } ?>

    <?php include __DIR__ . '/_feedback.php'; ?>

</div>
