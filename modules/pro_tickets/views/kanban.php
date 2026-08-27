<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="ptk-wrap">

            <?php $active = 'kanban'; include __DIR__ . '/_nav.php'; ?>

            <div class="ptk-board" id="ptk-board" data-can-edit="<?= pro_tickets_staff_can_edit() ? '1' : '0'; ?>">
                <?php foreach ($columns as $column): ?>
                    <?php $st = $column['status']; ?>
                    <div class="ptk-col" data-status="<?= (int) $st->ticketstatusid; ?>">
                        <div class="ptk-col-head" style="--st:<?= html_escape($st->statuscolor ?: '#64748b'); ?>">
                            <span class="ptk-col-dot"></span>
                            <span class="ptk-col-name"><?= html_escape(ticket_status_translate($st->ticketstatusid) ?: $st->name); ?></span>
                            <span class="ptk-col-count"><?= (int) $column['total']; ?></span>
                        </div>
                        <div class="ptk-col-body">
                            <?php foreach ($column['rows'] as $t): ?>
                                <?php
                                    $meta = (object) [
                                        'frt_due'          => $t->frt_due,
                                        'res_due'          => $t->res_due,
                                        'first_replied_at' => $t->first_replied_at,
                                        'resolved_at'      => $t->resolved_at,
                                        'frt_breached'     => $t->frt_breached,
                                        'res_breached'     => $t->res_breached,
                                        'created_at'       => $t->meta_created_at ?: $t->date,
                                    ];
                                    $sla = pro_tickets_sla_state($t->frt_due || $t->res_due ? $meta : null, $t->status);
                                    $requester = $t->contactid != 0
                                        ? trim($t->contact_firstname . ' ' . $t->contact_lastname)
                                        : ($t->from_name ?: $t->ticket_email);
                                ?>
                                <div class="ptk-kcard" draggable="true" data-ticket="<?= (int) $t->ticketid; ?>">
                                    <div class="ptk-kcard-top">
                                        <span class="ptk-muted">#<?= (int) $t->ticketid; ?></span>
                                        <span class="ptk-priority ptk-priority-<?= (int) $t->priority; ?>"><?= html_escape($t->priority_name ?: '—'); ?></span>
                                    </div>
                                    <a class="ptk-kcard-subject" href="<?= admin_url('pro_tickets/ticket/' . (int) $t->ticketid); ?>"><?= html_escape($t->subject); ?></a>
                                    <div class="ptk-kcard-meta">
                                        <span><i class="fa fa-user"></i> <?= html_escape($requester ?: '—'); ?></span>
                                        <?php if ($t->assigned): ?>
                                            <span><i class="fa fa-headset"></i> <?= html_escape(trim($t->staff_firstname . ' ' . $t->staff_lastname)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ptk-kcard-bottom">
                                        <span class="ptk-sla ptk-sla-<?= $sla['state']; ?>"><?= html_escape($sla['label']); ?></span>
                                        <span class="ptk-muted ptk-small"><?= html_escape(time_ago($t->lastreply ?: $t->date)); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</div>
<script>
    window.PTK_KANBAN = {
        moveUrl: '<?= admin_url('pro_tickets/kanban_move'); ?>'
    };
</script>
<?php init_tail(); ?>
</body>
</html>
