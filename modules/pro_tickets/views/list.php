<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="ptk-wrap">

            <?php $active = 'tickets'; include __DIR__ . '/_nav.php'; ?>

            <!-- ── Filter bar ── -->
            <form class="ptk-filters" method="get" action="<?= admin_url('pro_tickets/tickets'); ?>">
                <div class="ptk-search">
                    <i class="fa fa-magnifying-glass"></i>
                    <input type="text" name="search" value="<?= html_escape($filters['search']); ?>" placeholder="<?= _l('pro_tickets_search_ph'); ?>">
                </div>
                <select name="status" class="ptk-select">
                    <option value="not_closed" <?= $filters['status'] === 'not_closed' ? 'selected' : ''; ?>><?= _l('pro_tickets_filter_active_statuses'); ?></option>
                    <option value=""><?= _l('pro_tickets_filter_all_statuses'); ?></option>
                    <?php foreach ($statuses as $st): ?>
                        <option value="<?= (int) $st->ticketstatusid; ?>" <?= (string) $filters['status'] === (string) $st->ticketstatusid ? 'selected' : ''; ?>><?= html_escape(ticket_status_translate($st->ticketstatusid) ?: $st->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="department" class="ptk-select">
                    <option value=""><?= _l('pro_tickets_filter_all_departments'); ?></option>
                    <?php foreach ($departments as $dep): ?>
                        <option value="<?= (int) $dep->departmentid; ?>" <?= (string) $filters['department'] === (string) $dep->departmentid ? 'selected' : ''; ?>><?= html_escape($dep->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="priority" class="ptk-select">
                    <option value=""><?= _l('pro_tickets_filter_all_priorities'); ?></option>
                    <?php foreach ($priorities as $pr): ?>
                        <option value="<?= (int) $pr->priorityid; ?>" <?= (string) $filters['priority'] === (string) $pr->priorityid ? 'selected' : ''; ?>><?= html_escape($pr->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="assigned" class="ptk-select">
                    <option value=""><?= _l('pro_tickets_filter_all_agents'); ?></option>
                    <option value="-1" <?= (string) $filters['assigned'] === '-1' ? 'selected' : ''; ?>><?= _l('pro_tickets_filter_unassigned'); ?></option>
                    <?php foreach ($staff as $member): ?>
                        <option value="<?= (int) $member->staffid; ?>" <?= (string) $filters['assigned'] === (string) $member->staffid ? 'selected' : ''; ?>><?= html_escape($member->firstname . ' ' . $member->lastname); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="sla" class="ptk-select">
                    <option value=""><?= _l('pro_tickets_filter_all_sla'); ?></option>
                    <option value="breached" <?= $filters['sla'] === 'breached' ? 'selected' : ''; ?>><?= _l('pro_tickets_filter_sla_breached'); ?></option>
                    <option value="at_risk" <?= $filters['sla'] === 'at_risk' ? 'selected' : ''; ?>><?= _l('pro_tickets_filter_sla_at_risk'); ?></option>
                    <option value="ok" <?= $filters['sla'] === 'ok' ? 'selected' : ''; ?>><?= _l('pro_tickets_filter_sla_ok'); ?></option>
                </select>
                <select name="feedback" class="ptk-select">
                    <option value=""><?= _l('pro_tickets_filter_all_feedback'); ?></option>
                    <?php foreach ([
                        'positive' => _l('pro_tickets_filter_fb_positive'),
                        'neutral'  => _l('pro_tickets_filter_fb_neutral'),
                        'negative' => _l('pro_tickets_filter_fb_negative'),
                        'rated'    => _l('pro_tickets_filter_fb_rated'),
                        'unrated'  => _l('pro_tickets_filter_fb_unrated'),
                    ] as $val => $label): ?>
                        <option value="<?= $val; ?>" <?= $filters['feedback'] === $val ? 'selected' : ''; ?>><?= $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="period" class="ptk-select">
                    <option value=""><?= _l('pro_tickets_filter_any_time'); ?></option>
                    <?php foreach ([7 => _l('pro_tickets_filter_period_7'), 30 => _l('pro_tickets_filter_period_30'), 90 => _l('pro_tickets_filter_period_90')] as $days => $label): ?>
                        <option value="<?= $days; ?>" <?= (string) $filters['period'] === (string) $days ? 'selected' : ''; ?>><?= $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="ptk-btn ptk-btn-primary"><i class="fa fa-filter"></i></button>
                <span class="ptk-results-count"><?= (int) $total; ?> <?= _l('pro_tickets_results'); ?></span>
            </form>

            <!-- ── Table ── -->
            <div class="ptk-card ptk-card-flush">
                <?php if (empty($tickets)): ?>
                    <div class="ptk-empty">
                        <i class="fa-solid fa-inbox"></i>
                        <p><?= _l('pro_tickets_no_tickets'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="ptk-table-scroll">
                        <table class="ptk-table ptk-table-hover">
                            <thead>
                                <tr>
                                    <th style="width:70px">#</th>
                                    <th><?= _l('pro_tickets_col_subject'); ?></th>
                                    <th><?= _l('pro_tickets_col_requester'); ?></th>
                                    <th><?= _l('pro_tickets_col_department'); ?></th>
                                    <th><?= _l('pro_tickets_col_priority'); ?></th>
                                    <th><?= _l('pro_tickets_col_assigned'); ?></th>
                                    <th><?= _l('pro_tickets_col_sla'); ?></th>
                                    <th><?= _l('pro_tickets_col_feedback'); ?></th>
                                    <th><?= _l('pro_tickets_col_last_activity'); ?></th>
                                    <th><?= _l('pro_tickets_col_status'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $t): ?>
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
                                    <tr class="<?= !$t->adminread ? 'ptk-row-unread' : ''; ?>" data-href="<?= admin_url('pro_tickets/ticket/' . (int) $t->ticketid); ?>">
                                        <td class="ptk-muted">#<?= (int) $t->ticketid; ?></td>
                                        <td>
                                            <a class="ptk-subject" href="<?= admin_url('pro_tickets/ticket/' . (int) $t->ticketid); ?>"><?= html_escape($t->subject); ?></a>
                                        </td>
                                        <td>
                                            <?= html_escape($requester ?: '—'); ?>
                                            <?php if (!empty($t->company)): ?><span class="ptk-muted ptk-small"> · <?= html_escape($t->company); ?></span><?php endif; ?>
                                        </td>
                                        <td><?= html_escape($t->department_name ?: '—'); ?></td>
                                        <td><span class="ptk-priority ptk-priority-<?= (int) $t->priority; ?>"><?= html_escape($t->priority_name ?: '—'); ?></span></td>
                                        <td><?= $t->assigned ? html_escape(trim($t->staff_firstname . ' ' . $t->staff_lastname)) : '<span class="ptk-unassigned">' . _l('pro_tickets_filter_unassigned') . '</span>'; ?></td>
                                        <td><span class="ptk-sla ptk-sla-<?= $sla['state']; ?>"><?= html_escape($sla['label']); ?></span></td>
                                        <td>
                                            <?php if ($t->feedback_rating !== null): ?>
                                                <?= pro_tickets_rating_stars($t->feedback_rating, $t->feedback_comment); ?>
                                            <?php elseif ((int) $t->status === 5): ?>
                                                <span class="ptk-badge-soft"><?= _l('pro_tickets_fb_awaiting'); ?></span>
                                            <?php else: ?>
                                                <span class="ptk-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="ptk-muted ptk-small"><?= html_escape(time_ago($t->lastreply ?: $t->date)); ?></td>
                                        <td><span class="ptk-status" style="--st:<?= html_escape($t->statuscolor ?: '#64748b'); ?>"><?= html_escape(ticket_status_translate($t->status) ?: $t->status_name); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── Pagination ── -->
            <?php if ($total_pages > 1): ?>
                <?php
                    $qs = $_GET;
                    unset($qs['page']);
                    $base = admin_url('pro_tickets/tickets') . '?' . http_build_query($qs);
                ?>
                <div class="ptk-pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="ptk-page active"><?= $i; ?></span>
                        <?php elseif ($i <= 2 || $i > $total_pages - 2 || abs($i - $page) <= 2): ?>
                            <a class="ptk-page" href="<?= $base . '&page=' . $i; ?>"><?= $i; ?></a>
                        <?php elseif (abs($i - $page) == 3): ?>
                            <span class="ptk-page-dots">…</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
