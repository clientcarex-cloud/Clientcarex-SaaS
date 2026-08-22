<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="mbx-wrap">

            <?php if (!count($accounts)) : ?>
                <!-- ── No accounts state ── -->
                <div class="mbx-empty-page">
                    <div class="mbx-empty-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
                    <h3><?= _l('mailbox_no_accounts'); ?></h3>
                    <?php if ($is_super) : ?>
                        <p class="mbx-muted"><?= _l('mailbox_no_accounts_admin_sub'); ?></p>
                        <a href="<?= admin_url('mailbox/accounts'); ?>" class="mbx-btn mbx-btn-primary">
                            <i class="fa fa-plug"></i> <?= _l('mailbox_connect_first'); ?>
                        </a>
                    <?php else : ?>
                        <p class="mbx-muted"><?= _l('mailbox_no_accounts_sub'); ?></p>
                    <?php endif; ?>
                </div>
            <?php else : ?>

            <?php if (!$imap_ext) : ?>
                <div class="mbx-banner mbx-banner-warning">
                    <i class="fa fa-triangle-exclamation"></i> <?= _l('mailbox_imap_ext_missing'); ?>
                </div>
            <?php endif; ?>

            <div class="mbx-app" id="mbx-app">

                <!-- ── Sidebar ── -->
                <aside class="mbx-side">
                    <button type="button" class="mbx-btn mbx-btn-primary mbx-compose-btn" id="mbx-compose-open">
                        <i class="fa fa-pen"></i> <?= _l('mailbox_compose'); ?>
                    </button>

                    <div class="mbx-side-label"><?= _l('mailbox_accounts'); ?></div>
                    <div class="mbx-account-list" id="mbx-account-list"></div>

                    <div class="mbx-side-label"><?= _l('mailbox'); ?></div>
                    <nav class="mbx-folders" id="mbx-folders">
                        <a data-folder="inbox" class="mbx-folder active"><i class="fa fa-inbox"></i> <?= _l('mailbox_folder_inbox'); ?> <span class="mbx-badge" id="mbx-badge-inbox"></span></a>
                        <a data-folder="mine" class="mbx-folder mbx-folder-shared"><i class="fa fa-user-check"></i> <?= _l('mailbox_my_queue'); ?> <span class="mbx-badge mbx-badge-soft" id="mbx-badge-mine"></span></a>
                        <a data-folder="unassigned" class="mbx-folder mbx-folder-shared"><i class="fa fa-user-slash"></i> <?= _l('mailbox_unassigned'); ?> <span class="mbx-badge mbx-badge-soft" id="mbx-badge-unassigned"></span></a>
                        <a data-folder="overdue" class="mbx-folder mbx-folder-sla"><i class="fa fa-clock"></i> <?= _l('mailbox_overdue'); ?> <span class="mbx-badge mbx-badge-danger" id="mbx-badge-overdue"></span></a>
                        <a data-folder="starred" class="mbx-folder"><i class="fa fa-star"></i> <?= _l('mailbox_folder_starred'); ?></a>
                        <a data-folder="sent" class="mbx-folder"><i class="fa fa-paper-plane"></i> <?= _l('mailbox_folder_sent'); ?></a>
                        <a data-folder="scheduled" class="mbx-folder"><i class="fa fa-clock-rotate-left"></i> <?= _l('mailbox_folder_scheduled'); ?> <span class="mbx-badge mbx-badge-soft" id="mbx-badge-scheduled"></span></a>
                        <a data-folder="drafts" class="mbx-folder"><i class="fa fa-file-pen"></i> <?= _l('mailbox_folder_drafts'); ?> <span class="mbx-badge mbx-badge-soft" id="mbx-badge-drafts"></span></a>
                        <a data-folder="archive" class="mbx-folder"><i class="fa fa-box-archive"></i> <?= _l('mailbox_folder_archive'); ?></a>
                        <a data-folder="trash" class="mbx-folder"><i class="fa fa-trash-can"></i> <?= _l('mailbox_folder_trash'); ?></a>
                    </nav>

                    <div class="mbx-side-label mbx-side-label-row">
                        <span><?= _l('mailbox_labels'); ?></span>
                        <?php if ($can_manage) : ?>
                            <button type="button" class="mbx-link-btn" id="mbx-label-new" title="<?= _l('mailbox_new_label'); ?>"><i class="fa fa-plus"></i></button>
                        <?php endif; ?>
                    </div>
                    <nav class="mbx-labels" id="mbx-label-list"></nav>

                    <?php if ($is_super || $can_reports) : ?>
                        <div class="mbx-side-foot">
                            <div class="mbx-side-label"><?= _l('mailbox_admin_tools'); ?></div>
                            <?php if ($is_super) : ?>
                                <a href="<?= admin_url('mailbox/accounts'); ?>" class="mbx-side-manage"><i class="fa fa-envelope-circle-check"></i> <?= _l('mailbox_manage_accounts'); ?></a>
                                <a href="<?= admin_url('mailbox/settings'); ?>" class="mbx-side-manage"><i class="fa fa-gear"></i> <?= _l('mailbox_settings'); ?></a>
                            <?php endif; ?>
                            <?php if ($can_reports) : ?>
                                <a href="<?= admin_url('mailbox/analytics'); ?>" class="mbx-side-manage"><i class="fa fa-chart-line"></i> <?= _l('mailbox_analytics'); ?></a>
                                <a href="<?= admin_url('mailbox/audit'); ?>" class="mbx-side-manage"><i class="fa fa-shield-halved"></i> <?= _l('mailbox_audit_trail'); ?></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </aside>

                <!-- ── Message list ── -->
                <section class="mbx-list" id="mbx-list-pane">
                    <div class="mbx-list-toolbar">
                        <label class="mbx-check-all" title="Select all">
                            <input type="checkbox" id="mbx-select-all">
                        </label>
                        <div class="mbx-bulk" id="mbx-bulk" style="display:none;">
                            <button class="mbx-icon-btn" data-bulk="read" title="<?= _l('mailbox_mark_read'); ?>"><i class="fa fa-envelope-open"></i></button>
                            <button class="mbx-icon-btn" data-bulk="unread" title="<?= _l('mailbox_mark_unread'); ?>"><i class="fa fa-envelope"></i></button>
                            <button class="mbx-icon-btn" data-bulk="archive" title="<?= _l('mailbox_archive'); ?>"><i class="fa fa-box-archive"></i></button>
                            <button class="mbx-icon-btn" data-bulk="trash" title="<?= _l('mailbox_folder_trash'); ?>"><i class="fa fa-trash-can"></i></button>
                            <span class="mbx-tool-sep"></span>
                            <button class="mbx-icon-btn" id="mbx-bulk-assign" title="<?= _l('mailbox_assign'); ?>"><i class="fa fa-user-check"></i></button>
                            <button class="mbx-icon-btn" id="mbx-bulk-label" title="<?= _l('mailbox_label'); ?>"><i class="fa fa-tag"></i></button>
                            <button class="mbx-icon-btn" id="mbx-bulk-status" title="<?= _l('mailbox_status'); ?>"><i class="fa fa-circle-half-stroke"></i></button>
                        </div>
                        <div class="mbx-search">
                            <i class="fa fa-magnifying-glass"></i>
                            <input type="text" id="mbx-search" placeholder="<?= _l('mailbox_search_placeholder'); ?>">
                            <button type="button" class="mbx-search-help" id="mbx-search-help" title="<?= _l('mailbox_search_help'); ?>"><i class="fa fa-circle-question"></i></button>
                        </div>
                        <button class="mbx-icon-btn" id="mbx-refresh" title="<?= _l('mailbox_sync_now'); ?>"><i class="fa fa-rotate"></i></button>
                        <button class="mbx-icon-btn" id="mbx-shortcuts-open" title="<?= _l('mailbox_shortcuts'); ?>"><i class="fa fa-keyboard"></i></button>
                        <div class="mbx-pager">
                            <span id="mbx-pager-label"></span>
                            <button class="mbx-icon-btn" id="mbx-prev"><i class="fa fa-chevron-left"></i></button>
                            <button class="mbx-icon-btn" id="mbx-next"><i class="fa fa-chevron-right"></i></button>
                        </div>
                    </div>

                    <!-- Status facets (shared inbox) -->
                    <div class="mbx-facets" id="mbx-facets">
                        <button class="mbx-facet active" data-status=""><?= _l('mailbox_filter_all'); ?></button>
                        <button class="mbx-facet" data-status="open"><?= _l('mailbox_status_open'); ?></button>
                        <button class="mbx-facet" data-status="pending"><?= _l('mailbox_status_pending'); ?></button>
                        <button class="mbx-facet" data-status="closed"><?= _l('mailbox_status_closed'); ?></button>
                        <span class="mbx-facet-active" id="mbx-facet-active"></span>
                    </div>

                    <div class="mbx-rows" id="mbx-rows"></div>
                </section>

                <!-- ── Reading pane ── -->
                <section class="mbx-read" id="mbx-read-pane">
                    <div class="mbx-read-empty" id="mbx-read-empty">
                        <i class="fa-regular fa-envelope-open"></i>
                        <p><?= _l('mailbox_select_message'); ?></p>
                    </div>
                    <div class="mbx-read-inner" id="mbx-read-inner" style="display:none;"></div>
                </section>
            </div>

            <!-- ── Composer ── -->
            <div class="mbx-composer" id="mbx-composer" style="display:none;">
                <div class="mbx-composer-head">
                    <span id="mbx-composer-title"><?= _l('mailbox_compose'); ?></span>
                    <div class="mbx-composer-head-actions">
                        <button class="mbx-icon-btn" id="mbx-composer-min" title="—"><i class="fa fa-minus"></i></button>
                        <button class="mbx-icon-btn" id="mbx-composer-close" title="×"><i class="fa fa-xmark"></i></button>
                    </div>
                </div>
                <div class="mbx-composer-body" id="mbx-composer-body">
                    <div class="mbx-compose-row">
                        <label><?= _l('mailbox_from'); ?></label>
                        <select id="mbx-c-account"></select>
                    </div>
                    <div class="mbx-compose-row mbx-compose-row-ac">
                        <label><?= _l('mailbox_to'); ?></label>
                        <input type="text" id="mbx-c-to" placeholder="<?= _l('mailbox_recipients_hint'); ?>" autocomplete="off">
                        <button type="button" class="mbx-link-btn" id="mbx-show-cc"><?= _l('mailbox_cc'); ?></button>
                        <button type="button" class="mbx-link-btn" id="mbx-show-bcc"><?= _l('mailbox_bcc'); ?></button>
                    </div>
                    <div class="mbx-compose-row mbx-compose-row-ac" id="mbx-row-cc" style="display:none;">
                        <label><?= _l('mailbox_cc'); ?></label>
                        <input type="text" id="mbx-c-cc" autocomplete="off">
                    </div>
                    <div class="mbx-compose-row mbx-compose-row-ac" id="mbx-row-bcc" style="display:none;">
                        <label><?= _l('mailbox_bcc'); ?></label>
                        <input type="text" id="mbx-c-bcc" autocomplete="off">
                    </div>
                    <div class="mbx-compose-row">
                        <label><?= _l('mailbox_subject'); ?></label>
                        <input type="text" id="mbx-c-subject">
                    </div>
                    <div class="mbx-editor-tools">
                        <button type="button" data-cmd="bold" title="Bold"><i class="fa fa-bold"></i></button>
                        <button type="button" data-cmd="italic" title="Italic"><i class="fa fa-italic"></i></button>
                        <button type="button" data-cmd="underline" title="Underline"><i class="fa fa-underline"></i></button>
                        <button type="button" data-cmd="insertUnorderedList" title="List"><i class="fa fa-list-ul"></i></button>
                        <button type="button" data-cmd="createLink" title="Link"><i class="fa fa-link"></i></button>
                        <button type="button" data-cmd="removeFormat" title="Clear"><i class="fa fa-eraser"></i></button>
                        <span class="mbx-tool-sep"></span>
                        <button type="button" id="mbx-c-template" title="<?= _l('mailbox_insert_template'); ?>"><i class="fa fa-bolt"></i></button>
                    </div>
                    <div class="mbx-editor" id="mbx-c-body" contenteditable="true"></div>
                    <div class="mbx-dlp-warning" id="mbx-dlp-warning" style="display:none;"></div>
                    <div class="mbx-c-attachments" id="mbx-c-attachments"></div>
                </div>
                <div class="mbx-composer-foot">
                    <button class="mbx-btn mbx-btn-primary" id="mbx-c-send"><i class="fa fa-paper-plane"></i> <?= _l('mailbox_send'); ?></button>
                    <button class="mbx-icon-btn" id="mbx-c-schedule" title="<?= _l('mailbox_send_later'); ?>"><i class="fa fa-clock"></i></button>
                    <label class="mbx-icon-btn" title="<?= _l('mailbox_attach_files'); ?>">
                        <i class="fa fa-paperclip"></i>
                        <input type="file" id="mbx-c-files" multiple style="display:none;">
                    </label>
                    <button class="mbx-icon-btn" id="mbx-c-draft" title="<?= _l('mailbox_save_draft'); ?>"><i class="fa fa-floppy-disk"></i></button>
                    <span class="mbx-composer-status" id="mbx-c-status"></span>
                    <button class="mbx-icon-btn mbx-right" id="mbx-c-discard" title="<?= _l('mailbox_discard'); ?>"><i class="fa fa-trash-can"></i></button>
                </div>
            </div>

            <!-- ── Floating popovers (positioned by JS) ── -->
            <div class="mbx-pop" id="mbx-pop" style="display:none;"></div>

            <!-- ── Keyboard shortcuts ── -->
            <div class="mbx-modal-backdrop" id="mbx-shortcuts" style="display:none;">
                <div class="mbx-modal mbx-modal-sm">
                    <div class="mbx-modal-head">
                        <strong><?= _l('mailbox_shortcuts'); ?></strong>
                        <button class="mbx-icon-btn" data-close-modal><i class="fa fa-xmark"></i></button>
                    </div>
                    <div class="mbx-modal-body mbx-shortcut-grid">
                        <?php
                        $shortcuts = [
                            'c'      => 'mailbox_shortcut_compose',
                            'r'      => 'mailbox_shortcut_reply',
                            'a'      => 'mailbox_shortcut_reply_all',
                            'f'      => 'mailbox_shortcut_forward',
                            'e'      => 'mailbox_shortcut_archive',
                            '#'      => 'mailbox_shortcut_trash',
                            's'      => 'mailbox_shortcut_star',
                            'u'      => 'mailbox_shortcut_unread',
                            '/'      => 'mailbox_shortcut_search',
                            'j'      => 'mailbox_shortcut_next',
                            'k'      => 'mailbox_shortcut_prev',
                            'y'      => 'mailbox_shortcut_assign',
                            'n'      => 'mailbox_shortcut_note',
                            'x'      => 'mailbox_shortcut_close',
                            'Ctrl+Enter' => 'mailbox_shortcut_send',
                            '?'      => 'mailbox_shortcut_help',
                        ];
                        foreach ($shortcuts as $key => $label) : ?>
                            <div class="mbx-shortcut"><kbd><?= html_escape($key); ?></kbd><span><?= _l($label); ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="mbx-toast" id="mbx-toast" style="display:none;"></div>

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
window.MBX_BOOT = {
    urls: {
        base:       '<?= admin_url('mailbox'); ?>',
        bootstrap:  '<?= admin_url('mailbox/bootstrap'); ?>',
        messages:   '<?= admin_url('mailbox/messages'); ?>',
        message:    '<?= admin_url('mailbox/message'); ?>',
        action:     '<?= admin_url('mailbox/action'); ?>',
        send:       '<?= admin_url('mailbox/send'); ?>',
        draft:      '<?= admin_url('mailbox/save_draft'); ?>',
        sync:       '<?= admin_url('mailbox/sync'); ?>',
        attachment: '<?= admin_url('mailbox/attachment'); ?>',
        dispatch:   '<?= admin_url('mailbox/dispatch'); ?>',
        cancelSchedule: '<?= admin_url('mailbox/cancel_scheduled'); ?>',
        assign:     '<?= admin_url('mailbox/assign'); ?>',
        setStatus:  '<?= admin_url('mailbox/set_status'); ?>',
        legalHold:  '<?= admin_url('mailbox/legal_hold'); ?>',
        presence:   '<?= admin_url('mailbox/presence'); ?>',
        labels:     '<?= admin_url('mailbox/labels'); ?>',
        labelSave:  '<?= admin_url('mailbox/label_save'); ?>',
        labelDelete:'<?= admin_url('mailbox/label_delete'); ?>',
        applyLabel: '<?= admin_url('mailbox/apply_label'); ?>',
        notes:      '<?= admin_url('mailbox/notes'); ?>',
        noteAdd:    '<?= admin_url('mailbox/note_add'); ?>',
        noteDelete: '<?= admin_url('mailbox/note_delete'); ?>',
        templates:  '<?= admin_url('mailbox/templates'); ?>',
        templateUse:'<?= admin_url('mailbox/template_use'); ?>',
        contacts:   '<?= admin_url('mailbox/contacts'); ?>',
        convert:    '<?= admin_url('mailbox/convert'); ?>'
    },
    operators: <?= json_encode($operators); ?>,
    isSuper: <?= $is_super ? 'true' : 'false'; ?>,
    i18n: {
        allAccounts:   "<?= _l('mailbox_all_accounts'); ?>",
        emptyTitle:    "<?= _l('mailbox_empty_folder'); ?>",
        emptySub:      "<?= _l('mailbox_empty_folder_sub'); ?>",
        loadError:     "<?= _l('mailbox_load_error'); ?>",
        sending:       "<?= _l('mailbox_sending'); ?>",
        sentOk:        "<?= _l('mailbox_sent_ok'); ?>",
        draftSaved:    "<?= _l('mailbox_draft_saved'); ?>",
        syncing:       "<?= _l('mailbox_syncing'); ?>",
        synced:        "<?= _l('mailbox_synced'); ?>",
        newMail:       "<?= _l('mailbox_new_mail'); ?>",
        reply:         "<?= _l('mailbox_reply'); ?>",
        replyAll:      "<?= _l('mailbox_reply_all'); ?>",
        forward:       "<?= _l('mailbox_forward'); ?>",
        archive:       "<?= _l('mailbox_archive'); ?>",
        toInbox:       "<?= _l('mailbox_move_to_inbox'); ?>",
        restore:       "<?= _l('mailbox_restore'); ?>",
        deleteForever: "<?= _l('mailbox_delete_forever'); ?>",
        deleteConfirm: "<?= _l('mailbox_delete_forever_confirm'); ?>",
        trash:         "<?= _l('mailbox_folder_trash'); ?>",
        noSubject:     "<?= _l('mailbox_no_subject'); ?>",
        compose:       "<?= _l('mailbox_compose'); ?>",
        via:           "<?= _l('mailbox_via'); ?>",
        sentBy:        "<?= _l('mailbox_sent_by'); ?>",
        threadEarlier: "<?= _l('mailbox_thread_earlier'); ?>",
        attachments:   "<?= _l('mailbox_attachments'); ?>",
        to:            "<?= _l('mailbox_to'); ?>",
        cc:            "<?= _l('mailbox_cc'); ?>",
        markUnread:    "<?= _l('mailbox_mark_unread'); ?>",
        send:          "<?= _l('mailbox_send'); ?>",
        labelScope:    "<?= _l('mailbox_label_scope'); ?>",
        templatePrivate: "<?= _l('mailbox_template_private'); ?>",
        edit:          "<?= _l('mailbox_edit'); ?>",

        assign:        "<?= _l('mailbox_assign'); ?>",
        assignedTo:    "<?= _l('mailbox_assigned_to'); ?>",
        unassigned:    "<?= _l('mailbox_unassigned'); ?>",
        assignToMe:    "<?= _l('mailbox_assign_to_me'); ?>",
        status:        "<?= _l('mailbox_status'); ?>",
        statusOpen:    "<?= _l('mailbox_status_open'); ?>",
        statusPending: "<?= _l('mailbox_status_pending'); ?>",
        statusClosed:  "<?= _l('mailbox_status_closed'); ?>",
        internalNotes: "<?= _l('mailbox_internal_notes'); ?>",
        internalHint:  "<?= _l('mailbox_internal_note_hint'); ?>",
        addNote:       "<?= _l('mailbox_add_note'); ?>",
        noNotes:       "<?= _l('mailbox_no_notes'); ?>",
        noteDeleteConfirm: "<?= _l('mailbox_note_delete_confirm'); ?>",
        mentionHint:   "<?= _l('mailbox_mention_hint'); ?>",
        viewingNow:    "<?= _l('mailbox_viewing_now'); ?>",
        replyingNow:   "<?= _l('mailbox_replying_now'); ?>",
        collision:     "<?= _l('mailbox_collision_warning'); ?>",
        labels:        "<?= _l('mailbox_labels'); ?>",
        label:         "<?= _l('mailbox_label'); ?>",
        newLabel:      "<?= _l('mailbox_new_label'); ?>",
        labelName:     "<?= _l('mailbox_label_name'); ?>",
        labelColor:    "<?= _l('mailbox_label_color'); ?>",
        labelAllAccounts: "<?= _l('mailbox_label_all_accounts'); ?>",
        labelDeleteConfirm: "<?= _l('mailbox_label_delete_confirm'); ?>",
        noLabels:      "<?= _l('mailbox_no_labels'); ?>",
        templates:     "<?= _l('mailbox_templates'); ?>",
        noTemplates:   "<?= _l('mailbox_no_templates'); ?>",
        insertTemplate: "<?= _l('mailbox_insert_template'); ?>",
        sendLater:     "<?= _l('mailbox_send_later'); ?>",
        schedulePick:  "<?= _l('mailbox_schedule_pick'); ?>",
        scheduleQueued: "<?= _l('mailbox_schedule_queued'); ?>",
        scheduledFor:  "<?= _l('mailbox_scheduled_for'); ?>",
        cancelSchedule: "<?= _l('mailbox_cancel_schedule'); ?>",
        undo:          "<?= _l('mailbox_undo'); ?>",
        sendingIn:     "<?= _l('mailbox_sending_in'); ?>",
        sendUndone:    "<?= _l('mailbox_send_undone'); ?>",
        sendFailed:    "<?= _l('mailbox_send_failed'); ?>",
        dlpWarning:    "<?= _l('mailbox_dlp_warning'); ?>",
        dlpSendAnyway: "<?= _l('mailbox_dlp_send_anyway'); ?>",
        slaDue:        "<?= _l('mailbox_sla_due'); ?>",
        slaBreached:   "<?= _l('mailbox_sla_breached'); ?>",
        legalHold:     "<?= _l('mailbox_legal_hold'); ?>",
        legalHoldOn:   "<?= _l('mailbox_legal_hold_on'); ?>",
        legalHoldOff:  "<?= _l('mailbox_legal_hold_off'); ?>",
        crm:           "<?= _l('mailbox_crm'); ?>",
        knownContact:  "<?= _l('mailbox_known_contact'); ?>",
        convert:       "<?= _l('mailbox_convert'); ?>",
        convertLead:   "<?= _l('mailbox_convert_lead'); ?>",
        convertTicket: "<?= _l('mailbox_convert_ticket'); ?>",
        convertedTo:   "<?= _l('mailbox_converted_to'); ?>",
        searchHelp:    "<?= _l('mailbox_search_help'); ?>",
        searchHelpHint: "<?= _l('mailbox_search_help_hint'); ?>",
        save:          "<?= _l('mailbox_save'); ?>",
        cancel:        "<?= _l('mailbox_cancel'); ?>",
        del:           "<?= _l('mailbox_delete'); ?>",
        filterAll:     "<?= _l('mailbox_filter_all'); ?>"
    }
};
</script>

<?php init_tail(); ?>
</body>
</html>
