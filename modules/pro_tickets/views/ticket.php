<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="ptk-wrap">

            <?php $active = 'tickets'; include __DIR__ . '/_nav.php'; ?>

            <?php $meta = $ticket->meta; $can_edit = pro_tickets_staff_can_edit(); ?>

            <?php
            // ── Requester mobile — the number a hook-triggered SMS / WhatsApp for
            // this ticket is sent to ({mobile_number}). Masked until the eye is
            // clicked, and always rendered: "not set" is the useful answer when
            // there is nothing for the hook to dial.
            $ptk_mobile = trim((string) $requester_mobile);
            $ptk_mobile_chip = function ($class) use ($ptk_mobile) {
                $hint = $ptk_mobile !== ''
                    ? _l('pro_tickets_mobile_hook_hint')
                    : _l('pro_tickets_mobile_none_hint');
                ?>
                <span class="<?= $class; ?> ptk-mobile<?= $ptk_mobile === '' ? ' ptk-mobile-empty' : ''; ?>" title="<?= html_escape($hint); ?>">
                    <i class="fa fa-mobile-screen-button"></i>
                    <?php if ($ptk_mobile === ''): ?>
                        <span class="ptk-mobile-value"><?= _l('pro_tickets_mobile_none'); ?></span>
                    <?php else: ?>
                        <span class="ptk-mobile-value"
                              data-masked="<?= html_escape(pro_tickets_mask_mobile($ptk_mobile)); ?>"
                              data-full="<?= html_escape($ptk_mobile); ?>"><?= html_escape(pro_tickets_mask_mobile($ptk_mobile)); ?></span>
                        <button type="button" class="ptk-mobile-toggle" aria-pressed="false"
                                data-show="<?= _l('pro_tickets_mobile_show'); ?>"
                                data-hide="<?= _l('pro_tickets_mobile_hide'); ?>"
                                title="<?= _l('pro_tickets_mobile_show'); ?>"><i class="fa fa-eye"></i></button>
                    <?php endif; ?>
                </span>
                <?php
            };
            ?>

            <?php
            // ── HealthO AI (Pro AI module) — plain-text transcript of the whole ticket ──
            $ptk_ai_available = function_exists('pro_ai_render_ai_button')
                && function_exists('pro_ai_is_available') && pro_ai_is_available();

            if ($ptk_ai_available) {
                $ptk_ai_text = function ($html) {
                    $text = preg_replace('/<br\s*\/?>/i', "\n", (string) $html);
                    $text = preg_replace('/<\/(p|div|li|h[1-6]|tr|blockquote)>/i', "\n", $text);
                    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    return trim(preg_replace("/\n{3,}/", "\n\n", $text));
                };

                $ptk_dep_name = '';
                foreach ($departments as $dep) {
                    if ($dep->departmentid == $ticket->department) {
                        $ptk_dep_name = $dep->name;
                        break;
                    }
                }

                // Header block — shared by the whole-ticket transcript and by the
                // per-message content, so a single message is never analysed without
                // knowing which ticket / customer / SLA it belongs to.
                $t   = [];
                $t[] = 'TICKET #' . (int) $ticket->ticketid . ' — ' . $ticket->subject;
                $t[] = 'Status: ' . (ticket_status_translate($ticket->status) ?: $ticket->status_name)
                    . ' | Priority: ' . ($ticket->priority_name ?: '—')
                    . ' | Department: ' . ($ptk_dep_name ?: '—');
                $t[] = 'SLA: ' . $sla_state['label']
                    . ($meta && $meta->frt_due ? ' | First response due: ' . _dt($meta->frt_due) : '')
                    . ($meta && $meta->res_due ? ' | Resolution due: ' . _dt($meta->res_due) : '');
                $ptk_req = 'Requester: ' . ($ticket->submitter ?: '—');
                $ptk_req_email = $ticket->contactid != 0 ? $ticket->email : $ticket->ticket_email;
                if ($ptk_req_email) {
                    $ptk_req .= ' <' . $ptk_req_email . '>';
                }
                if ($ticket->userid != 0) {
                    $ptk_req .= ' (' . get_company_name($ticket->userid) . ')';
                }
                $t[] = $ptk_req;
                $t[] = 'Assigned to: ' . ($ticket->assigned ? get_staff_full_name($ticket->assigned) : 'Unassigned');
                if (!empty($ticket->tags)) {
                    $t[] = 'Tags: ' . implode(', ', array_map(function ($tg) {
                        return is_object($tg) ? $tg->name : $tg['name'];
                    }, $ticket->tags));
                }
                $t[] = 'Created: ' . _dt($ticket->date);
                $ptk_ai_header = implode("\n", $t);

                // Per-message content, keyed by the same key the message's own AI
                // button carries. Each entry is the ticket header + the whole thread
                // as background + the one selected message singled out, so the
                // assistant answers about THAT message with the ticket in view.
                $ptk_ai_msgs    = [];
                $ptk_ai_thread  = [];
                $ptk_ai_msg_key = function ($reply) {
                    return $reply === null ? 'm0' : 'r' . (int) $reply['id'];
                };

                $ptk_ai_msg_block = function ($reply) use ($ticket, $ptk_ai_text) {
                    if ($reply === null) {
                        // Tickets opened from the admin area were written by the
                        // agent in tbltickets.admin, not by the requester.
                        $by_staff = !($ticket->admin == null || $ticket->admin == 0);

                        return '--- ORIGINAL MESSAGE by ' . ($by_staff ? 'AGENT ' : 'CUSTOMER ')
                            . ($by_staff ? $ticket->opened_by : ($ticket->submitter ?: $ticket->ticket_email))
                            . ' (' . _dt($ticket->date) . ') ---' . "\n" . $ptk_ai_text($ticket->message);
                    }
                    $staff = $reply['admin'] !== null && $reply['admin'] != 0;

                    return '--- REPLY by ' . ($staff ? 'AGENT ' : 'CUSTOMER ') . $reply['submitter']
                        . ' (' . _dt($reply['date']) . ') ---' . "\n" . $ptk_ai_text($reply['message']);
                };

                $ptk_ai_thread[] = $ptk_ai_msg_block(null);
                foreach ($ticket->replies as $ptk_r) {
                    $ptk_ai_thread[] = $ptk_ai_msg_block($ptk_r);
                }

                $t[] = '';
                $t[] = implode("\n\n", $ptk_ai_thread);
                if (!empty($ticket->notes)) {
                    $t[] = '';
                    $t[] = '--- INTERNAL NOTES (staff only, never shown to the customer) ---';
                    foreach ($ticket->notes as $ptk_n) {
                        $t[] = '[' . trim($ptk_n->firstname . ' ' . $ptk_n->lastname) . ', ' . _dt($ptk_n->created_at) . '] ' . $ptk_n->note;
                    }
                }
                $ptk_ai_transcript = implode("\n", $t);

                // Only the message blocks go in the map — the header and the conversation
                // are sent once and stitched on in JS. Inlining the whole conversation per
                // message would make this payload grow with the square of the thread length.
                $ptk_ai_msgs[$ptk_ai_msg_key(null)] = $ptk_ai_msg_block(null);
                foreach ($ticket->replies as $ptk_r) {
                    $ptk_ai_msgs[$ptk_ai_msg_key($ptk_r)] = $ptk_ai_msg_block($ptk_r);
                }

                $ptk_ai_msg_ctx = [
                    'header'       => $ptk_ai_header,
                    'conversation' => implode("\n\n", $ptk_ai_thread),
                    'lead'         => '--- THE SELECTED MESSAGE ---' . "\n"
                        . 'The agent has selected the single message below. Answer about THIS message only;'
                        . ' use the conversation above purely as context.',
                ];
            }
            ?>
            <?php if ($ptk_ai_available): ?>
                <script>
                    window.ptkProAiTicketContent = function () { return <?= json_encode($ptk_ai_transcript); ?>; };
                    // Per-message assistant: each message's button carries its key in
                    // cfg.context, so this one provider serves every button in the thread.
                    window.PTK_AI_MSGS = <?= json_encode($ptk_ai_msgs, JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}'; ?>;
                    window.PTK_AI_MSG_CTX = <?= json_encode($ptk_ai_msg_ctx, JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}'; ?>;
                    // The draft currently in the reply composer, as HTML — the correction
                    // button rewrites exactly this and writes the result back over it.
                    window.ptkProAiReplyDraft = function () {
                        var ed = (typeof tinymce !== 'undefined') ? tinymce.get('ptk-reply-message') : null;
                        if (ed) { return ed.getContent(); }
                        var ta = document.getElementById('ptk-reply-message');

                        return ta ? ta.value : '';
                    };
                    window.ptkProAiMessageContent = function (cfg) {
                        var msg = (window.PTK_AI_MSGS || {})[(cfg && cfg.context) || ''];
                        if (!msg) { return ''; }
                        var c = window.PTK_AI_MSG_CTX || {};

                        return c.header + '\n\n'
                            + '--- FULL CONVERSATION (background only) ---\n\n'
                            + c.conversation + '\n\n'
                            + c.lead + '\n\n'
                            + msg;
                    };
                </script>
            <?php endif; ?>

            <!-- ── Ticket header ── -->
            <div class="ptk-ticket-head">
                <div class="ptk-ticket-head-main">
                    <a class="ptk-back" href="<?= admin_url('pro_tickets/tickets'); ?>"><i class="fa fa-arrow-left"></i></a>
                    <div>
                        <?php // Two-row header: subject + state badges on the first row, then one
                              // identity strip (customer, SaaS instance, tenant user, agent). Kept
                              // here rather than only in the Requester card so it stays visible on
                              // every tab, and both rows wrap on their own when space runs out. ?>
                        <div class="ptk-titlerow">
                            <h2 class="ptk-ticket-subject">
                                <span class="ptk-muted">#<?= (int) $ticket->ticketid; ?></span>
                                <?= html_escape($ticket->subject); ?>
                            </h2>
                            <div class="ptk-ticket-badges">
                                <span class="ptk-status" style="--st:<?= html_escape($ticket->statuscolor ?: '#64748b'); ?>"><?= html_escape(ticket_status_translate($ticket->status) ?: $ticket->status_name); ?></span>
                                <span class="ptk-priority ptk-priority-<?= (int) $ticket->priority; ?>"><?= html_escape($ticket->priority_name ?: '—'); ?></span>
                                <span class="ptk-sla ptk-sla-<?= $sla_state['state']; ?>"><?= html_escape($sla_state['label']); ?></span>
                                <?php if ($meta && $meta->auto_closed): ?>
                                    <span class="ptk-badge-soft"><i class="fa fa-robot"></i> <?= _l('pro_tickets_auto_closed_badge'); ?></span>
                                <?php endif; ?>
                                <?php if ($meta && $meta->reopened_count > 0): ?>
                                    <span class="ptk-badge-soft"><i class="fa fa-rotate-left"></i> <?= _l('pro_tickets_reopened_times', (int) $meta->reopened_count); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($ticket_feedback)): ?>
                                    <span class="ptk-badge-soft" title="<?= html_escape((string) $ticket_feedback->comment); ?>">
                                        <i class="fa fa-star" style="color:#f59e0b"></i>
                                        <?= _l('pro_tickets_csat_badge', (int) $ticket_feedback->rating); ?>
                                    </span>
                                <?php endif; ?>
                                <?php foreach ($ticket->tags as $tag): ?>
                                    <span class="ptk-badge-soft"><i class="fa fa-tag"></i> <?= html_escape(is_object($tag) ? $tag->name : $tag['name']); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="ptk-ticket-owner">
                            <?php if ($ticket->userid != 0): ?>
                                <a class="ptk-owner-company" href="<?= admin_url('clients/client/' . (int) $ticket->userid); ?>" target="_blank"
                                    title="<?= html_escape($ticket_company); ?>">
                                    <i class="fa fa-building"></i>
                                    <span class="ptk-owner-cname"><?= html_escape($ticket_company !== '' ? $ticket_company : ('#' . (int) $ticket->userid)); ?></span>
                                </a>
                                <?php if ($ticket_tenant): ?>
                                    <?php // SaaS instance behind that customer — links straight to it.
                                          // The instance name replaces the "Tenant" word when it differs
                                          // from the customer company; the cloud icon already says what
                                          // it is, and the tooltip spells it out. ?>
                                    <?php $ptk_tenant_label = strcasecmp($ticket_tenant['name'], (string) $ticket_company) === 0 ? '' : $ticket_tenant['name']; ?>
                                    <a class="ptk-owner-tenant" title="<?= _l('pro_tickets_tenant'); ?> — <?= _l('pro_tickets_tenant_hint'); ?>"
                                        href="<?= admin_url((defined('PERFEX_SAAS_ROUTE_NAME') ? PERFEX_SAAS_ROUTE_NAME : 'perfex_saas') . '/companies/edit/' . (int) $ticket_tenant['id']); ?>" target="_blank">
                                        <i class="fa fa-cloud"></i>
                                        <?php if ($ptk_tenant_label !== ''): ?>
                                            <span class="ptk-owner-slug"><?= html_escape($ptk_tenant_label); ?></span>
                                        <?php else: ?>
                                            <?= _l('pro_tickets_tenant'); ?>
                                        <?php endif; ?>
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="ptk-owner-none"><i class="fa fa-building-circle-exclamation"></i> <?= _l('pro_tickets_no_client_linked'); ?></span>
                            <?php endif; ?>

                            <?php
                            // Who raised it and who owns it, by login username — the tenant
                            // user's email is the account they signed in with on the instance,
                            // and the agent's email is their staff login here.
                            $ptk_user_name  = trim((string) ($ticket->submitter ?? ''));
                            $ptk_user_email = $ticket->contactid != 0 ? $ticket->email : $ticket->ticket_email;

                            $ptk_agent_name  = $ticket->assigned ? get_staff_full_name($ticket->assigned) : '';
                            $ptk_agent_email = '';
                            foreach ($staff as $ptk_member) {
                                if ($ticket->assigned && $ptk_member->staffid == $ticket->assigned) {
                                    $ptk_agent_email = (string) $ptk_member->email;
                                    break;
                                }
                            }
                            ?>
                            <?php // Only the username is printed — the person's real name rides in
                                  // the tooltip, which keeps the whole band on one line. ?>
                            <span class="ptk-owner-user" title="<?= html_escape(trim($ptk_user_name . ($ptk_user_name !== '' && $ptk_user_email ? ' · ' : '') . $ptk_user_email)); ?>">
                                <i class="fa fa-user"></i>
                                <?= _l('pro_tickets_tenant_user'); ?>
                                <span class="ptk-owner-uname"><?= html_escape($ptk_user_email ?: ($ptk_user_name ?: '—')); ?></span>
                            </span>

                            <?php // The mobile that carries this ticket's hook SMS / WhatsApp,
                                  // right next to the login it e-mails. ?>
                            <?php $ptk_mobile_chip('ptk-owner-mobile'); ?>

                            <span class="ptk-owner-agent" id="ptk-owner-agent" title="<?= html_escape(trim($ptk_agent_name . ($ptk_agent_name !== '' && $ptk_agent_email ? ' · ' : '') . $ptk_agent_email)); ?>">
                                <i class="fa fa-user-shield"></i>
                                <?= _l('pro_tickets_col_assigned'); ?>
                                <span class="ptk-owner-uname" data-ptk-agent-name><?= html_escape($ptk_agent_email ?: ($ptk_agent_name ?: _l('pro_tickets_filter_unassigned'))); ?></span>
                            </span>
                        </div><!-- /.ptk-ticket-owner -->
                    </div>
                </div>
                <div class="ptk-ticket-head-actions">
                    <?php if ($ptk_ai_available): ?>
                        <?php // Whole-ticket assistant. Lives with the other ticket-level
                              // actions; its result can still be dropped into the composer
                              // for members who have one. ?>
                        <?php pro_ai_render_ai_button([
                            'module'           => 'pro_tickets',
                            'mode'             => 'analyze',
                            'content_provider' => 'ptkProAiTicketContent',
                            'insert_target'    => $can_edit ? '#ptk-reply-message' : '',
                            'insert_label'     => _l('pro_ai_use_result'),
                            'label'            => _l('pro_ai_assistant'),
                        ]); ?>
                    <?php endif; ?>
                    <?php if ($can_edit): ?>
                        <button class="ptk-btn ptk-btn-light" id="ptk-transfer-open" title="<?= _l('pro_tickets_transfer_hint'); ?>">
                            <i class="fa fa-shuffle"></i> <span><?= _l('pro_tickets_transfer'); ?></span>
                        </button>
                    <?php endif; ?>
                    <button class="ptk-btn ptk-btn-light" id="ptk-watch-btn" data-url="<?= admin_url('pro_tickets/toggle_watch/' . (int) $ticket->ticketid); ?>" data-watching="<?= $is_watching ? '1' : '0'; ?>">
                        <i class="fa <?= $is_watching ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                        <span><?= $is_watching ? _l('pro_tickets_unwatch') : _l('pro_tickets_watch'); ?></span>
                    </button>
                    <?php if (pro_tickets_staff_can_delete()): ?>
                        <form method="post" action="<?= admin_url('pro_tickets/delete/' . (int) $ticket->ticketid); ?>" onsubmit="return confirm('<?= _l('pro_tickets_delete_confirm'); ?>');" style="display:inline">
                            <?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
                            <button type="submit" class="ptk-btn ptk-btn-danger-light"><i class="fa fa-trash"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($can_edit): ?>
                <!-- Transfer ticket: department + one of ITS members, in one action.
                     The member dropdown is repopulated from PTK_DEPT_STAFF when the
                     department changes. -->
                <div class="ptk-modal" id="ptk-transfer-modal" hidden>
                    <div class="ptk-modal-backdrop" data-ptk-modal-close></div>
                    <div class="ptk-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ptk-transfer-title">
                        <div class="ptk-modal-head">
                            <h4 id="ptk-transfer-title"><i class="fa fa-shuffle"></i> <?= _l('pro_tickets_transfer_title'); ?></h4>
                            <button type="button" class="ptk-modal-x" data-ptk-modal-close aria-label="Close"><i class="fa fa-xmark"></i></button>
                        </div>
                        <div class="ptk-modal-body" id="ptk-transfer-body"
                            data-url="<?= admin_url('pro_tickets/transfer/' . (int) $ticket->ticketid); ?>"
                            data-department="<?= (int) $ticket->department; ?>"
                            data-assigned="<?= (int) $ticket->assigned; ?>">
                            <label class="ptk-field-label"><i class="fa fa-building"></i> <?= _l('pro_tickets_transfer_department'); ?></label>
                            <select class="ptk-select" id="ptk-transfer-department" style="width:100%">
                                <?php foreach ($departments as $dep): ?>
                                    <option value="<?= (int) $dep->departmentid; ?>" <?= $ticket->department == $dep->departmentid ? 'selected' : ''; ?>><?= html_escape($dep->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="ptk-field-label"><i class="fa fa-user"></i> <?= _l('pro_tickets_transfer_member'); ?></label>
                            <select class="ptk-select" id="ptk-transfer-member" style="width:100%" data-unassigned="<?= _l('pro_tickets_filter_unassigned'); ?>"></select>
                            <div class="ptk-small ptk-muted" id="ptk-transfer-empty" style="margin-top:6px;" hidden><?= _l('pro_tickets_transfer_no_members'); ?></div>
                        </div>
                        <div class="ptk-modal-foot">
                            <button type="button" class="ptk-btn ptk-btn-light" data-ptk-modal-close><?= _l('pro_tickets_cancel'); ?></button>
                            <button type="button" class="ptk-btn ptk-btn-primary" id="ptk-transfer-submit"><i class="fa fa-shuffle"></i> <?= _l('pro_tickets_transfer_submit'); ?></button>
                        </div>
                    </div>
                </div>
                <script>window.PTK_DEPT_STAFF = <?= json_encode($dept_staff, JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}'; ?>;</script>
            <?php endif; ?>

            <?php
            // Whether any linked-app card has something to show — decides if the "Linked" tab appears.
            $has_apps = (!empty($smart_forms_active) && !empty($ticket_forms))
                || !empty($caller_active) || !empty($mailbox_active) || !empty($smart_pdf_active);
            ?>

            <!-- ── Tab navigation ── -->
            <div class="ptk-ttabs" id="ptk-ttabs" data-store="ptk-ticket-tab">
                <button type="button" class="ptk-ttab active" data-panel="conversation">
                    <i class="fa fa-comments"></i> <span><?= _l('pro_tickets_tab_conversation'); ?></span>
                    <span class="ptk-pill"><?= count($ticket->replies) + 1; ?></span>
                </button>
                <button type="button" class="ptk-ttab" data-panel="todos">
                    <i class="fa fa-list-check"></i> <span><?= _l('pro_tickets_todos'); ?></span>
                    <?php if (!empty($ticket_todos)): ?><span class="ptk-pill"><?= count($ticket_todos); ?></span><?php endif; ?>
                </button>
                <button type="button" class="ptk-ttab" data-panel="notes">
                    <i class="fa fa-note-sticky"></i> <span><?= _l('pro_tickets_tab_notes'); ?></span>
                    <?php if (!empty($ticket->notes)): ?><span class="ptk-pill"><?= count($ticket->notes); ?></span><?php endif; ?>
                </button>
                <button type="button" class="ptk-ttab" data-panel="details">
                    <i class="fa fa-circle-info"></i> <span><?= _l('pro_tickets_tab_details'); ?></span>
                </button>
                <button type="button" class="ptk-ttab" data-panel="activity">
                    <i class="fa fa-wave-square"></i> <span><?= _l('pro_tickets_tab_activity'); ?></span>
                </button>
            </div>

            <div class="ptk-ticket-panels">

                <!-- ══ TAB: Conversation ══ -->
                <div class="ptk-ttab-panel active" data-panel="conversation">

                    <!-- Composer + conversation thread (left) with linked module data
                         alongside (right column, spans both rows) — the thread must sit
                         in the LEFT grid column, otherwise a tall linked column (long
                         call history) pushes the whole conversation below it, leaving a
                         huge blank gap under the composer. -->
                    <div class="ptk-conv-top<?= $has_apps ? ' ptk-conv-top-split' : ''; ?>">

                        <?php if ($can_edit): ?>
                            <!-- ── Reply composer ── -->
                            <div class="ptk-conv-reply">
                                <div class="ptk-card ptk-composer">
                                    <h4 class="ptk-card-title"><i class="fa fa-reply"></i> <?= _l('pro_tickets_reply'); ?>
                                        <?php if ($ptk_ai_available): ?>
                                            <span class="ptk-card-title-action">
                                                <?php // One-click proofread of the draft already in the box:
                                                      // no prompt picker, no popup — it rewrites the reply in place. ?>
                                                <?php pro_ai_render_ai_button([
                                                    'module'           => 'pro_tickets',
                                                    'mode'             => 'analyze',
                                                    'instant'          => true,
                                                    'instant_prompt'   => _l('pro_tickets_ai_correct_prompt'),
                                                    'instant_empty'    => _l('pro_tickets_ai_correct_empty'),
                                                    'content_provider' => 'ptkProAiReplyDraft',
                                                    'insert_target'    => '#ptk-reply-message',
                                                    'insert_mode'      => 'replace',
                                                    'insert_html'      => true,
                                                    'label'            => _l('pro_tickets_ai_correct'),
                                                    'title'            => _l('pro_tickets_ai_correct_hint'),
                                                ]); ?>
                                            </span>
                                        <?php endif; ?>
                                    </h4>
                                    <?= form_open_multipart(admin_url('pro_tickets/reply/' . (int) $ticket->ticketid)); ?>
                                        <!-- Predefined messages: insert one, or save the composed
                                             reply as a new reusable template. The strip is welded to
                                             the top of the editor so the composer reads as one field. -->
                                        <div class="ptk-composer-head">
                                            <div class="ptk-composer-bar">
                                                <div class="ptk-canned-pick">
                                                    <i class="fa fa-bolt ptk-canned-icon" aria-hidden="true"></i>
                                                    <select class="ptk-canned" id="ptk-canned" aria-label="<?= _l('pro_tickets_canned_insert'); ?>"
                                                        data-url="<?= admin_url('pro_tickets/canned'); ?>"
                                                        data-target="#ptk-reply-message"
                                                        data-ticket="<?= (int) $ticket->ticketid; ?>">
                                                        <option value=""><?= _l('pro_tickets_canned_insert'); ?></option>
                                                        <?php foreach ($canned as $cr): ?>
                                                            <option value="<?= (int) $cr->id; ?>"><?= html_escape($cr->name); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button type="button" class="ptk-bar-btn" id="ptk-canned-save-toggle"
                                                    title="<?= _l('pro_tickets_canned_save_hint'); ?>"
                                                    aria-expanded="false" aria-controls="ptk-canned-save-row">
                                                    <i class="fa fa-bookmark" aria-hidden="true"></i> <span><?= _l('pro_tickets_canned_save'); ?></span>
                                                </button>
                                            </div>
                                            <div class="ptk-canned-save-row" id="ptk-canned-save-row"
                                                data-url="<?= admin_url('pro_tickets/save_canned'); ?>"
                                                data-target="#ptk-reply-message"
                                                data-missing="<?= _l('pro_tickets_canned_missing'); ?>"
                                                data-saved="<?= _l('pro_tickets_canned_saved'); ?>" hidden>
                                                <input type="text" class="ptk-input" id="ptk-canned-name" maxlength="191" placeholder="<?= _l('pro_tickets_canned_name_ph'); ?>">
                                                <button type="button" class="ptk-btn ptk-btn-primary ptk-btn-sm" id="ptk-canned-save-confirm"><i class="fa fa-check"></i> <?= _l('pro_tickets_save'); ?></button>
                                                <button type="button" class="ptk-icon-btn" id="ptk-canned-save-cancel" title="<?= _l('pro_tickets_cancel'); ?>"><i class="fa fa-xmark"></i></button>
                                            </div>
                                        </div>
                                        <textarea name="message" id="ptk-reply-message" class="ptk-textarea" rows="6" placeholder="<?= _l('pro_tickets_reply_ph'); ?>"><?= isset($reply_prefill) ? html_escape($reply_prefill) : ''; ?></textarea>
                                        <div class="ptk-composer-foot">
                                            <label class="ptk-file-label">
                                                <i class="fa fa-paperclip"></i>
                                                <input type="file" name="attachments[]" multiple id="ptk-attach-input">
                                                <span id="ptk-attach-names"><?= _l('pro_tickets_attachments'); ?></span>
                                            </label>
                                            <span class="ptk-mention-hint"><i class="fa fa-at"></i> <?= _l('pro_tickets_mention_hint'); ?></span>
                                            <div class="ptk-composer-actions">
                                                <!-- Split send button: main segment submits, the two right segments
                                                     pick the status and priority the ticket is set to on reply. -->
                                                <div class="ptk-send-split">
                                                    <button type="submit" class="ptk-send-main"><i class="fa fa-paper-plane"></i> <?= _l('pro_tickets_send_reply'); ?></button>
                                                    <span class="ptk-send-divider" aria-hidden="true"></span>
                                                    <select name="status" class="ptk-send-status" title="<?= _l('pro_tickets_reply_and_set_status'); ?>" aria-label="<?= _l('pro_tickets_reply_and_set_status'); ?>">
                                                        <?php foreach ($statuses as $st): ?>
                                                            <option value="<?= (int) $st->ticketstatusid; ?>" <?= $st->ticketstatusid == 3 ? 'selected' : ''; ?>><?= html_escape(ticket_status_translate($st->ticketstatusid) ?: $st->name); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <span class="ptk-send-divider" aria-hidden="true"></span>
                                                    <select name="priority" class="ptk-send-status" title="<?= _l('pro_tickets_reply_and_set_priority'); ?>" aria-label="<?= _l('pro_tickets_reply_and_set_priority'); ?>">
                                                        <?php foreach ($priorities as $pr): ?>
                                                            <option value="<?= (int) $pr->priorityid; ?>" <?= $ticket->priority == $pr->priorityid ? 'selected' : ''; ?>><?= html_escape(ticket_priority_translate($pr->priorityid) ?: $pr->name); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    <?= form_close(); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($has_apps): ?>
                            <!-- Linked module data (Smart Forms, Calls, Emails, Documents) -->
                            <div class="ptk-conv-linked">

                                <?php if (!empty($smart_forms_active) && !empty($ticket_forms)): ?>
                                    <!-- Smart Forms — forms assigned to the requester -->
                                    <div class="ptk-card">
                                        <h4 class="ptk-card-title"><i class="fa fa-clipboard-list"></i> <?= _l('pro_tickets_smart_forms'); ?> <span class="ptk-pill"><?= count($ticket_forms); ?></span>
                                            <a href="<?= admin_url('smart_forms'); ?>" target="_blank" class="ptk-card-title-link" title="<?= _l('pro_tickets_smart_forms_open_module'); ?>"><i class="fa fa-arrow-up-right-from-square"></i></a>
                                        </h4>
                                        <div class="ptk-form-list">
                                            <?php foreach ($ticket_forms as $sf):
                                                $sf_pct   = $sf->status === 'completed' ? 100 : (int) $sf->draft_progress;
                                                $sf_over  = $sf->status !== 'completed' && $sf->due_date && strtotime($sf->due_date) < time();
                                                $sf_link  = $sf->status === 'completed' && $sf->submission_id
                                                    ? admin_url('smart_forms/submission/' . (int) $sf->submission_id)
                                                    : admin_url('smart_forms/assignments/' . (int) $sf->form_id);
                                            ?>
                                                <a class="ptk-form-item" href="<?= $sf_link; ?>" target="_blank">
                                                    <div class="ptk-form-top">
                                                        <span class="ptk-form-title"><?= html_escape($sf->form_title); ?></span>
                                                        <span class="ptk-form-status ptk-form-status-<?= html_escape($sf->status); ?>"><?= html_escape(_l('pro_tickets_sf_status_' . $sf->status)); ?></span>
                                                    </div>
                                                    <div class="ptk-todo-progress-track">
                                                        <span class="ptk-todo-progress-bar <?= $sf_pct == 100 ? 'is-complete' : ''; ?>" style="width:<?= $sf_pct; ?>%"></span>
                                                    </div>
                                                    <div class="ptk-small ptk-muted ptk-form-meta">
                                                        <span><?= $sf_pct; ?>%</span>
                                                        <span class="ptk-form-target"><?= html_escape($sf->target_type === 'contact' ? _l('pro_tickets_sf_target_contact') : _l('pro_tickets_sf_target_patient')); ?></span>
                                                        <?php if ($sf->due_date): ?>
                                                            <span class="<?= $sf_over ? 'ptk-form-overdue' : ''; ?>"><i class="fa fa-calendar"></i> <?= html_escape(_d($sf->due_date)); ?><?= $sf_over ? ' · ' . _l('pro_tickets_sf_overdue') : ''; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($caller_active)): ?>
                                    <!-- Caller module — call history of requester & CC contacts -->
                                    <div class="ptk-card">
                                        <h4 class="ptk-card-title"><i class="fa fa-phone"></i> <?= _l('pro_tickets_calls'); ?> <span class="ptk-pill"><?= count($ticket_calls); ?></span>
                                            <a href="<?= admin_url('caller/call_logs'); ?>" target="_blank" class="ptk-card-title-link" title="<?= _l('pro_tickets_calls_open_module'); ?>"><i class="fa fa-arrow-up-right-from-square"></i></a>
                                        </h4>
                                        <?php if (empty($ticket_calls)): ?>
                                            <div class="ptk-small ptk-muted"><?= _l('pro_tickets_calls_none'); ?></div>
                                        <?php else: ?>
                                            <div class="ptk-call-list">
                                                <?php foreach ($ticket_calls as $call): ?>
                                                    <div class="ptk-call">
                                                        <span class="ptk-call-icon ptk-call-<?= html_escape($call->call_type); ?>">
                                                            <i class="fa <?= $call->call_type === 'incoming' ? 'fa-arrow-down' : ($call->call_type === 'outgoing' ? 'fa-arrow-up' : 'fa-phone-slash'); ?>"></i>
                                                        </span>
                                                        <div class="ptk-call-body">
                                                            <div class="ptk-call-top">
                                                                <span><?= html_escape(ucfirst($call->call_type)); ?><?= (int) $call->duration_seconds > 0 ? ' · ' . gmdate((int) $call->duration_seconds >= 3600 ? 'G:i:s' : 'i:s', (int) $call->duration_seconds) : ''; ?></span>
                                                                <?php if ($call->has_recording && $call->recording_id): ?>
                                                                    <a href="<?= admin_url('caller/play/' . (int) $call->recording_id); ?>" target="_blank" title="<?= _l('pro_tickets_call_recording'); ?>"><i class="fa fa-circle-play"></i></a>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="ptk-small ptk-muted">
                                                                <?= html_escape($call->phone_number); ?> · <?= html_escape(_dt($call->call_date)); ?>
                                                                <?php if (trim((string) $call->staff_name) !== ''): ?> · <i class="fa fa-headset"></i> <?= html_escape($call->staff_name); ?><?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($mailbox_active)): ?>
                                    <!-- Mailbox module — latest emails with requester & CC contacts -->
                                    <div class="ptk-card">
                                        <h4 class="ptk-card-title"><i class="fa fa-envelope-open-text"></i> <?= _l('pro_tickets_emails'); ?> <span class="ptk-pill"><?= count($ticket_emails); ?></span>
                                            <a href="<?= admin_url('mailbox'); ?>" target="_blank" class="ptk-card-title-link" title="<?= _l('pro_tickets_emails_open_module'); ?>"><i class="fa fa-arrow-up-right-from-square"></i></a>
                                        </h4>
                                        <?php if (empty($ticket_emails)): ?>
                                            <div class="ptk-small ptk-muted"><?= _l('pro_tickets_emails_none'); ?></div>
                                        <?php else: ?>
                                            <div class="ptk-email-list">
                                                <?php foreach ($ticket_emails as $email): $email_out = strtolower(trim((string) $email->from_email)) === strtolower(trim((string) $email->account_email)); ?>
                                                    <div class="ptk-email">
                                                        <span class="ptk-email-icon ptk-email-<?= $email_out ? 'outgoing' : 'incoming'; ?>" title="<?= $email_out ? _l('pro_tickets_email_sent') : _l('pro_tickets_email_received'); ?>">
                                                            <i class="fa <?= $email_out ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                                                        </span>
                                                        <div class="ptk-email-body">
                                                            <div class="ptk-email-top">
                                                                <span class="ptk-email-subject" title="<?= html_escape($email->subject); ?>"><?= html_escape($email->subject !== '' ? $email->subject : '(' . _l('pro_tickets_email_no_subject') . ')'); ?></span>
                                                                <?php if ($email->has_attachments): ?><i class="fa fa-paperclip ptk-muted"></i><?php endif; ?>
                                                            </div>
                                                            <?php if (trim((string) $email->snippet) !== ''): ?>
                                                                <div class="ptk-email-snippet ptk-small ptk-muted"><?= html_escape($email->snippet); ?></div>
                                                            <?php endif; ?>
                                                            <div class="ptk-small ptk-muted">
                                                                <?= html_escape($email_out ? $email->account_email : ($email->from_name !== '' ? $email->from_name : $email->from_email)); ?>
                                                                · <?= html_escape(_dt($email->message_date)); ?>
                                                                · <i class="fa fa-inbox"></i> <?= html_escape($email->account_name); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($smart_pdf_active)): ?>
                                    <!-- Smart PDF module — generate documents prefilled from this ticket -->
                                    <div class="ptk-card">
                                        <h4 class="ptk-card-title"><i class="fa fa-file-pdf"></i> <?= _l('pro_tickets_documents'); ?>
                                            <a href="<?= admin_url('smart_pdf'); ?>" target="_blank" class="ptk-card-title-link" title="<?= _l('pro_tickets_documents_open_module'); ?>"><i class="fa fa-arrow-up-right-from-square"></i></a>
                                        </h4>
                                        <?php if (empty($pdf_templates)): ?>
                                            <div class="ptk-small ptk-muted"><?= _l('pro_tickets_pdf_none'); ?></div>
                                        <?php else: ?>
                                            <?= form_open(admin_url('pro_tickets/ticket_pdf_generate'), ['id' => 'ptk-pdf-form', 'target' => '_blank']); ?>
                                                <input type="hidden" name="ticket_id" value="<?= (int) $ticket->ticketid; ?>">
                                                <select name="template_id" id="ptk-pdf-template" class="ptk-select" data-url="<?= admin_url('pro_tickets/ticket_pdf_fields'); ?>" data-ticket="<?= (int) $ticket->ticketid; ?>">
                                                    <option value=""><?= _l('pro_tickets_pdf_choose'); ?></option>
                                                    <?php foreach ($pdf_templates as $tpl): ?>
                                                        <option value="<?= (int) $tpl->id; ?>"><?= html_escape($tpl->name); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div id="ptk-pdf-fields" data-empty="<?= _l('pro_tickets_pdf_no_fields'); ?>"></div>
                                                <div class="ptk-pdf-actions" id="ptk-pdf-actions" style="display:none">
                                                    <button type="submit" name="mode" value="preview" class="ptk-btn ptk-btn-light"><i class="fa fa-eye"></i> <?= _l('pro_tickets_pdf_preview'); ?></button>
                                                    <button type="submit" name="mode" value="print" class="ptk-btn ptk-btn-primary"><i class="fa fa-print"></i> <?= _l('pro_tickets_pdf_generate'); ?></button>
                                                </div>
                                            <?= form_close(); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                            </div>
                        <?php endif; ?>

                    <?php
                    // Images render as inline thumbnails opening in a lightbox (one gallery
                    // per ticket); every other file type keeps the download chip.
                    $ptk_attachment_html = function ($attachment) use ($ticket) {
                        $path = get_upload_path_by_type('ticket') . $ticket->ticketid . '/' . $attachment['file_name'];
                        if (is_image($path)) {
                            $preview = site_url('download/preview_image?path=' . protected_file_url_by_path($path) . '&type=' . $attachment['filetype']); ?>
                            <a href="<?= $preview; ?>" class="ptk-attach-img" data-lightbox="ptk-ticket-<?= (int) $ticket->ticketid; ?>" data-title="<?= html_escape($attachment['file_name']); ?>">
                                <img src="<?= $preview; ?>" alt="<?= html_escape($attachment['file_name']); ?>" loading="lazy">
                            </a>
                        <?php } else { ?>
                            <a href="<?= site_url('download/file/ticket/' . $attachment['id']); ?>" class="ptk-attachment"><i class="fa fa-paperclip"></i> <?= html_escape($attachment['file_name']); ?></a>
                        <?php }
                    };
                    ?>

                    <?php
                    // Message avatar — the real profile photo when one exists (staff
                    // photo for agent replies, contact photo for customer messages),
                    // falling back to the letter initial when there is none.
                    $ptk_avatar_placeholder = base_url('assets/images/user-placeholder.jpg');
                    $ptk_avatar = function ($name, $isStaff, $staff_id = 0, $contact_id = 0) use ($ptk_avatar_placeholder) {
                        $url = '';
                        if ($isStaff && $staff_id) {
                            $url = staff_profile_image_url($staff_id);
                        } elseif (!$isStaff && $contact_id) {
                            $url = contact_profile_image_url($contact_id);
                        }
                        if ($url !== '' && $url !== $ptk_avatar_placeholder) { ?>
                            <span class="ptk-avatar ptk-avatar-img<?= $isStaff ? ' ptk-avatar-staff' : ''; ?>">
                                <img src="<?= $url; ?>" alt="<?= html_escape((string) $name); ?>" loading="lazy">
                            </span>
                        <?php } else { ?>
                            <span class="ptk-avatar<?= $isStaff ? ' ptk-avatar-staff' : ''; ?>"><?= strtoupper(substr((string) $name, 0, 1) ?: '?'); ?></span>
                        <?php }
                    };
                    ?>

                    <?php
                    // Per-message HealthO AI button. Same assistant as the whole-ticket
                    // one, but scoped to a single message — and, when the member has a
                    // composer, its result can drop straight into the reply box.
                    $ptk_msg_ai_button = !$ptk_ai_available ? function () {} : function ($reply) use ($can_edit, $ptk_ai_msg_key) {
                        pro_ai_render_ai_button([
                            'module'           => 'pro_tickets',
                            'mode'             => 'analyze',
                            'content_provider' => 'ptkProAiMessageContent',
                            'context'          => $ptk_ai_msg_key($reply),
                            'insert_target'    => $can_edit ? '#ptk-reply-message' : '',
                            'insert_label'     => _l('pro_ai_use_result'),
                            'label'            => _l('pro_tickets_ai_this_message'),
                            'title'            => _l('pro_tickets_ai_this_message_hint'),
                            'class'            => 'ptk-msg-ai-btn',
                        ]);
                    };
                    ?>

                    <!-- Conversation thread (latest reply first, original message last) -->
                    <div class="ptk-conv-thread">

                        <?php if (!empty($ticket_feedback)): ?>
                            <!-- Customer satisfaction feedback — rating + message, in full -->
                            <?php $fb_rating = (int) $ticket_feedback->rating; ?>
                            <div class="ptk-card ptk-fb-card ptk-fb-<?= pro_tickets_rating_tone($fb_rating); ?>">
                                <div class="ptk-fb-head">
                                    <span class="ptk-fb-icon"><i class="fa fa-star"></i></span>
                                    <div class="ptk-fb-main">
                                        <div class="ptk-fb-title"><?= _l('pro_tickets_feedback_card_title'); ?></div>
                                        <div class="ptk-fb-stars">
                                            <?php for ($fb_i = 1; $fb_i <= 5; $fb_i++): ?>
                                                <i class="fa fa-star <?= $fb_i <= $fb_rating ? 'is-on' : ''; ?>"></i>
                                            <?php endfor; ?>
                                            <span class="ptk-fb-label"><?= html_escape(pro_tickets_rating_label($fb_rating)); ?> · <?= $fb_rating; ?>/5</span>
                                        </div>
                                    </div>
                                    <span class="ptk-small ptk-muted ptk-fb-when"><?= html_escape(_dt($ticket_feedback->created_at)); ?></span>
                                </div>
                                <?php if (trim((string) $ticket_feedback->comment) !== ''): ?>
                                    <div class="ptk-fb-comment"><?= nl2br(pro_tickets_linkify(html_escape($ticket_feedback->comment))); ?></div>
                                <?php endif; ?>
                                <?php
                                // Who submitted the rating — the contact stored on the
                                // feedback row, falling back to the ticket requester.
                                $fb_by = trim((string) ($ticket_feedback->contact_name ?? ''));
                                if ($fb_by === '') {
                                    $fb_by = trim((string) ($ticket->submitter ?? ''));
                                }
                                ?>
                                <div class="ptk-small ptk-muted ptk-fb-meta">
                                    <?php if ($fb_by !== ''): ?>
                                        <span><i class="fa fa-user"></i> <?= html_escape(_l('pro_tickets_feedback_by', $fb_by)); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($ticket_feedback->agent_name) && trim($ticket_feedback->agent_name) !== ''): ?>
                                        <span><i class="fa fa-headset"></i> <?= html_escape(_l('pro_tickets_feedback_agent', $ticket_feedback->agent_name)); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Replies -->
                        <?php if (empty($ticket->replies)): ?>
                            <p class="ptk-muted ptk-small ptk-center"><?= _l('pro_tickets_no_replies_yet'); ?></p>
                        <?php endif; ?>
                        <?php foreach (pro_tickets_replies_newest_first($ticket->replies) as $reply): ?>
                            <?php $isStaff = $reply['admin'] !== null && $reply['admin'] != 0; ?>
                            <div class="ptk-msg <?= $isStaff ? 'ptk-msg-staff' : 'ptk-msg-client'; ?>">
                                <div class="ptk-msg-head">
                                    <?php $ptk_avatar($reply['submitter'], $isStaff, (int) ($reply['admin'] ?? 0), (int) ($reply['contactid'] ?? 0)); ?>
                                    <div>
                                        <strong><?= html_escape($reply['submitter']); ?></strong>
                                        <span class="<?= $isStaff ? 'ptk-badge-staff' : 'ptk-badge-client'; ?>"><?= $isStaff ? _l('pro_tickets_agent') : _l('pro_tickets_client'); ?></span>
                                        <span class="ptk-muted ptk-small"><?= html_escape(_dt($reply['date'])); ?></span>
                                    </div>
                                    <?php if ($ptk_ai_available): ?>
                                        <span class="ptk-msg-ai"><?php $ptk_msg_ai_button($reply); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="ptk-msg-body tc-content"><?= pro_tickets_linkify($reply['message']); ?></div>
                                <?php if (!empty($reply['attachments'])): ?>
                                    <div class="ptk-msg-attachments">
                                        <?php foreach ($reply['attachments'] as $attachment): ?>
                                            <?php $ptk_attachment_html($attachment); ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <!-- Original message. A ticket opened from the admin area carries
                             the creating agent in tbltickets.admin — that message was
                             written BY the agent (on behalf of the requester), so it is
                             attributed to them here exactly as the client portal does. -->
                        <?php $ptk_opened_by_staff = !($ticket->admin == null || $ticket->admin == 0); ?>
                        <?php $ptk_opened_by = $ptk_opened_by_staff
                            ? (trim((string) $ticket->opened_by) ?: _l('pro_tickets_agent'))
                            : ($ticket->submitter ?: $ticket->ticket_email); ?>
                        <div class="ptk-msg <?= $ptk_opened_by_staff ? 'ptk-msg-staff' : 'ptk-msg-client'; ?>">
                            <div class="ptk-msg-head">
                                <?php $ptk_avatar($ptk_opened_by, $ptk_opened_by_staff, (int) $ticket->admin, (int) $ticket->contactid); ?>
                                <div>
                                    <strong><?= html_escape($ptk_opened_by); ?></strong>
                                    <span class="<?= $ptk_opened_by_staff ? 'ptk-badge-staff' : 'ptk-badge-client'; ?>"><?= $ptk_opened_by_staff ? _l('pro_tickets_agent') : _l('pro_tickets_client'); ?></span>
                                    <span class="ptk-muted ptk-small"><?= html_escape(_dt($ticket->date)); ?></span>
                                </div>
                                <?php if ($ptk_ai_available): ?>
                                    <span class="ptk-msg-ai"><?php $ptk_msg_ai_button(null); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="ptk-msg-body tc-content"><?= pro_tickets_linkify($ticket->message); ?></div>
                            <?php if (!empty($ticket->attachments)): ?>
                                <div class="ptk-msg-attachments">
                                    <?php foreach ($ticket->attachments as $attachment): ?>
                                        <?php if (empty($attachment['replyid'])): ?>
                                            <?php $ptk_attachment_html($attachment); ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div><!-- /.ptk-conv-thread -->

                    </div><!-- /.ptk-conv-top -->

                </div>

                <!-- ══ TAB: Details ══ -->
                <div class="ptk-ttab-panel" data-panel="details">
                    <div class="ptk-tabgrid">

                    <!-- Properties -->
                    <div class="ptk-card">
                        <h4 class="ptk-card-title"><i class="fa fa-sliders"></i> <?= _l('pro_tickets_properties'); ?></h4>
                        <div class="ptk-props" data-url="<?= admin_url('pro_tickets/update_ticket/' . (int) $ticket->ticketid); ?>" data-can-edit="<?= $can_edit ? '1' : '0'; ?>">
                            <label><?= _l('pro_tickets_col_status'); ?></label>
                            <select class="ptk-select ptk-prop" data-field="status" <?= $can_edit ? '' : 'disabled'; ?>>
                                <?php foreach ($statuses as $st): ?>
                                    <option value="<?= (int) $st->ticketstatusid; ?>" <?= $ticket->status == $st->ticketstatusid ? 'selected' : ''; ?>><?= html_escape(ticket_status_translate($st->ticketstatusid) ?: $st->name); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <label><?= _l('pro_tickets_col_assigned'); ?></label>
                            <select class="ptk-select ptk-prop" data-field="assigned" <?= $can_edit ? '' : 'disabled'; ?>>
                                <option value="0"><?= _l('pro_tickets_filter_unassigned'); ?></option>
                                <?php // data-username feeds the assignee chip in the header without a reload. ?>
                                <?php foreach ($staff as $member): ?>
                                    <option value="<?= (int) $member->staffid; ?>" data-username="<?= html_escape($member->email); ?>" <?= $ticket->assigned == $member->staffid ? 'selected' : ''; ?>><?= html_escape($member->firstname . ' ' . $member->lastname); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <label><?= _l('pro_tickets_col_priority'); ?></label>
                            <select class="ptk-select ptk-prop" data-field="priority" <?= $can_edit ? '' : 'disabled'; ?>>
                                <?php foreach ($priorities as $pr): ?>
                                    <option value="<?= (int) $pr->priorityid; ?>" <?= $ticket->priority == $pr->priorityid ? 'selected' : ''; ?>><?= html_escape($pr->name); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <label><?= _l('pro_tickets_col_department'); ?></label>
                            <select class="ptk-select ptk-prop" data-field="department" <?= $can_edit ? '' : 'disabled'; ?>>
                                <?php foreach ($departments as $dep): ?>
                                    <option value="<?= (int) $dep->departmentid; ?>" <?= $ticket->department == $dep->departmentid ? 'selected' : ''; ?>><?= html_escape($dep->name); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <?php if (!empty($services)): ?>
                                <label><?= _l('pro_tickets_new_service'); ?></label>
                                <select class="ptk-select ptk-prop" data-field="service" <?= $can_edit ? '' : 'disabled'; ?>>
                                    <option value="0">—</option>
                                    <?php foreach ($services as $srv): ?>
                                        <option value="<?= (int) $srv->serviceid; ?>" <?= $ticket->service == $srv->serviceid ? 'selected' : ''; ?>><?= html_escape($srv->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- SLA -->
                    <?php if ($meta && ($meta->frt_due || $meta->res_due)): ?>
                        <div class="ptk-card">
                            <h4 class="ptk-card-title"><i class="fa fa-shield-halved"></i> SLA</h4>
                            <div class="ptk-sla-rows">
                                <div class="ptk-sla-row">
                                    <span class="ptk-sla-row-label"><?= _l('pro_tickets_first_response'); ?></span>
                                    <?php if ($meta->first_replied_at): ?>
                                        <span class="ptk-sla ptk-sla-<?= $meta->frt_breached ? 'breach' : 'met'; ?>"><?= $meta->frt_breached ? _l('pro_tickets_breached') : _l('pro_tickets_met'); ?> · <?= html_escape(_dt($meta->first_replied_at)); ?></span>
                                    <?php elseif ($meta->frt_due): ?>
                                        <span class="ptk-sla ptk-sla-<?= strtotime($meta->frt_due) < time() ? 'breach' : 'ok'; ?>"><?= _l('pro_tickets_due'); ?> <?= html_escape(_dt($meta->frt_due)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="ptk-sla-row">
                                    <span class="ptk-sla-row-label"><?= _l('pro_tickets_resolution'); ?></span>
                                    <?php if ($meta->resolved_at): ?>
                                        <span class="ptk-sla ptk-sla-<?= $meta->res_breached ? 'breach' : 'met'; ?>"><?= $meta->res_breached ? _l('pro_tickets_breached') : _l('pro_tickets_met'); ?> · <?= html_escape(_dt($meta->resolved_at)); ?></span>
                                    <?php elseif ($meta->res_due): ?>
                                        <span class="ptk-sla ptk-sla-<?= strtotime($meta->res_due) < time() ? 'breach' : 'ok'; ?>"><?= _l('pro_tickets_due'); ?> <?= html_escape(_dt($meta->res_due)); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Requester -->
                    <div class="ptk-card">
                        <h4 class="ptk-card-title"><i class="fa fa-user"></i> <?= _l('pro_tickets_requester'); ?></h4>
                        <div class="ptk-requester">
                            <div><strong><?= html_escape($ticket->submitter ?: '—'); ?></strong></div>
                            <?php // Company link stays even when the submitter has no contact record
                                  // of their own (tenant staff raising a ticket through the SaaS bridge). ?>
                            <?php if ($ticket->userid != 0): ?>
                                <div class="ptk-small"><a href="<?= admin_url('clients/client/' . (int) $ticket->userid); ?>" target="_blank"><?= html_escape(get_company_name($ticket->userid)); ?> <i class="fa fa-arrow-up-right-from-square"></i></a></div>
                            <?php elseif ($can_edit && (!empty($link_tenants) || !empty($link_customers))): ?>
                                <?php // Ticket that belongs to no account yet (raised for a tenant
                                      // staff member before the requester flow linked them) — attach
                                      // it to the tenant / customer it really came from. ?>
                                <div class="ptk-link-client" id="ptk-link-client" data-url="<?= admin_url('pro_tickets/link_client/' . (int) $ticket->ticketid); ?>">
                                    <select class="ptk-select" id="ptk-link-client-select">
                                        <option value=""><?= _l('pro_tickets_link_client'); ?></option>
                                        <?php foreach ($link_tenants as $lt): ?>
                                            <option value="t:<?= html_escape($lt['slug']); ?>"><?= html_escape($lt['name']); ?></option>
                                        <?php endforeach; ?>
                                        <?php foreach ($link_customers as $lc): ?>
                                            <option value="c:<?= (int) $lc['userid']; ?>"><?= html_escape($lc['company']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="ptk-small ptk-muted"><?= _l('pro_tickets_link_client_hint'); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php $requester_email = $ticket->contactid != 0 ? $ticket->email : $ticket->ticket_email; ?>
                            <?php if ($requester_email): ?>
                                <div class="ptk-small ptk-muted"><i class="fa fa-envelope"></i> <?= html_escape($requester_email); ?></div>
                            <?php endif; ?>
                            <?php $ptk_mobile_chip('ptk-small ptk-muted'); ?>
                            <div class="ptk-small ptk-muted"><i class="fa fa-clock"></i> <?= _l('pro_tickets_created_on'); ?> <?= html_escape(_dt($ticket->date)); ?></div>
                        </div>

                        <?php
                        // CC recipients — extra contacts of the requester's company.
                        $cc_emails   = array_values(array_filter(array_map('trim', explode(',', (string) ($ticket->cc ?? '')))));
                        $cc_lc       = array_map('strtolower', $cc_emails);
                        $cc_by_email = [];
                        foreach ($company_contacts as $cc_contact) {
                            $cc_by_email[strtolower(trim($cc_contact->email))] = $cc_contact;
                        }
                        $cc_options = [];
                        if ($can_edit && $ticket->userid != 0) {
                            foreach ($company_contacts as $cc_contact) {
                                $cc_contact_email = strtolower(trim($cc_contact->email));
                                if ($cc_contact_email === '' || $cc_contact->id == $ticket->contactid
                                    || $cc_contact_email === strtolower((string) $requester_email)
                                    || in_array($cc_contact_email, $cc_lc, true)) {
                                    continue;
                                }
                                $cc_options[] = $cc_contact;
                            }
                        }
                        ?>
                        <?php if (!empty($cc_emails) || !empty($cc_options)): ?>
                            <div class="ptk-cc" id="ptk-cc" data-url="<?= admin_url('pro_tickets/toggle_cc/' . (int) $ticket->ticketid); ?>">
                                <div class="ptk-cc-title"><?= _l('pro_tickets_cc'); ?> <span class="ptk-pill"><?= count($cc_emails); ?></span></div>
                                <?php if (!empty($cc_emails)): ?>
                                    <div class="ptk-watchers">
                                        <?php foreach ($cc_emails as $cc_email): $cc_match = $cc_by_email[strtolower($cc_email)] ?? null; ?>
                                            <span class="ptk-watcher" data-email="<?= html_escape($cc_email); ?>" title="<?= html_escape($cc_email . ($cc_match && $cc_match->phonenumber ? ' · ' . $cc_match->phonenumber : '')); ?>">
                                                <?= html_escape($cc_match ? trim($cc_match->firstname . ' ' . $cc_match->lastname) : $cc_email); ?>
                                                <?php if ($can_edit): ?><i class="fa fa-xmark ptk-watcher-remove ptk-cc-remove" title="<?= _l('pro_tickets_cc_remove'); ?>"></i><?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($cc_options)): ?>
                                    <select class="ptk-select" id="ptk-cc-add">
                                        <option value=""><?= _l('pro_tickets_add_cc'); ?></option>
                                        <?php foreach ($cc_options as $cc_contact): ?>
                                            <option value="<?= html_escape($cc_contact->email); ?>"><?= html_escape(trim($cc_contact->firstname . ' ' . $cc_contact->lastname) . ' — ' . $cc_contact->email . ($cc_contact->phonenumber ? ' · ' . $cc_contact->phonenumber : '')); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Watchers -->
                    <div class="ptk-card">
                        <h4 class="ptk-card-title"><i class="fa fa-eye"></i> <?= _l('pro_tickets_watchers'); ?> <span class="ptk-pill"><?= count($ticket->watchers); ?></span></h4>
                        <div class="ptk-watchers" id="ptk-watchers" data-url="<?= admin_url('pro_tickets/toggle_watch/' . (int) $ticket->ticketid); ?>">
                            <?php foreach ($ticket->watchers as $watcher): ?>
                                <span class="ptk-watcher" data-staff="<?= (int) $watcher->staff_id; ?>">
                                    <?= html_escape(trim($watcher->firstname . ' ' . $watcher->lastname)); ?>
                                    <?php if ($can_edit): ?><i class="fa fa-xmark ptk-watcher-remove" title="<?= _l('pro_tickets_unwatch'); ?>"></i><?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($can_edit): ?>
                            <select class="ptk-select" id="ptk-watcher-add">
                                <option value=""><?= _l('pro_tickets_add_watcher'); ?></option>
                                <?php foreach ($staff as $member): ?>
                                    <option value="<?= (int) $member->staffid; ?>"><?= html_escape($member->firstname . ' ' . $member->lastname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    </div>
                </div>

                <!-- ══ TAB: To-dos ══ -->
                <div class="ptk-ttab-panel" data-panel="todos">
                    <!-- Pro Tickets' own to-do checklist (with progress bar) -->
                    <div class="ptk-card">
                        <h4 class="ptk-card-title">
                            <i class="fa fa-list-check"></i> <?= _l('pro_tickets_todos'); ?> <span class="ptk-pill"><?= count($ticket_todos); ?></span>
                            <?php if ($can_edit): ?>
                                <button type="button" class="ptk-btn ptk-btn-primary ptk-btn-sm ptk-card-title-action" id="ptk-todo-open"><i class="fa fa-plus"></i> <?= _l('pro_tickets_add_todo'); ?></button>
                            <?php endif; ?>
                        </h4>
                        <?php if ($can_edit): ?>
                            <!-- One-click checklist from a reusable template -->
                            <div style="display:flex;gap:6px;align-items:center;margin:0 0 12px;">
                                <?php if (!empty($todo_templates)): ?>
                                    <?= form_open(admin_url('pro_tickets/apply_todo_template/' . (int) $ticket->ticketid), ['style' => 'display:flex;gap:6px;align-items:center;flex:1;margin:0;']); ?>
                                        <select name="template_id" class="ptk-select" style="flex:1;" required>
                                            <option value=""><?= _l('pro_tickets_todo_template_pick'); ?></option>
                                            <?php foreach ($todo_templates as $tpl): ?>
                                                <option value="<?= (int) $tpl->id; ?>"><?= html_escape($tpl->name); ?> (<?= (int) $tpl->items_count; ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="ptk-btn ptk-btn-light ptk-btn-sm"><i class="fa fa-wand-magic-sparkles"></i> <?= _l('pro_tickets_todo_template_apply'); ?></button>
                                    <?= form_close(); ?>
                                <?php else: ?>
                                    <span class="ptk-small ptk-muted" style="flex:1;"><?= _l('pro_tickets_todo_templates_none_hint'); ?></span>
                                <?php endif; ?>
                                <a href="<?= admin_url('pro_tickets/todo_templates'); ?>" class="ptk-btn ptk-btn-light ptk-btn-sm" title="<?= html_escape(_l('pro_tickets_todo_templates')); ?>"><i class="fa fa-gear"></i></a>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($todo_progress['total'])): ?>
                            <div class="ptk-todo-progress" data-pct="<?= (int) $todo_progress['pct']; ?>">
                                <div class="ptk-todo-progress-head">
                                    <span><?= _l('pro_tickets_todo_progress', [(int) $todo_progress['done'], (int) $todo_progress['total']]); ?></span>
                                    <strong class="<?= $todo_progress['pct'] == 100 ? 'ptk-todo-progress-done' : ''; ?>"><?= (int) $todo_progress['pct']; ?>%</strong>
                                </div>
                                <div class="ptk-todo-progress-track">
                                    <span class="ptk-todo-progress-bar <?= $todo_progress['pct'] == 100 ? 'is-complete' : ''; ?>" style="width:<?= (int) $todo_progress['pct']; ?>%"></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (empty($ticket_todos)): ?>
                            <div class="ptk-small ptk-muted"><?= _l('pro_tickets_todos_none'); ?></div>
                        <?php else: ?>
                            <div class="ptk-todo-list">
                                <?php $todo_priorities = [1 => _l('pro_tickets_todo_low'), 2 => _l('pro_tickets_todo_medium'), 3 => _l('pro_tickets_todo_high'), 4 => _l('pro_tickets_todo_urgent')]; ?>
                                <?php foreach ($ticket_todos as $todo): $todo_done = (int) $todo->status === 2; ?>
                                    <div class="ptk-todo <?= $todo_done ? 'ptk-todo-done' : ''; ?>">
                                        <?php if ($can_edit): ?>
                                            <input type="checkbox" class="ptk-todo-toggle" data-url="<?= admin_url('pro_tickets/toggle_todo/' . (int) $todo->id); ?>" <?= $todo_done ? 'checked' : ''; ?>>
                                        <?php endif; ?>
                                        <div class="ptk-todo-body">
                                            <div class="ptk-todo-title"><?= html_escape($todo->title); ?></div>
                                            <?php if (trim((string) ($todo->description ?? '')) !== ''): ?>
                                                <div class="ptk-todo-desc ptk-small"><?= nl2br(html_escape($todo->description)); ?></div>
                                            <?php endif; ?>
                                            <div class="ptk-small ptk-muted">
                                                <span class="ptk-todo-priority ptk-todo-priority-<?= (int) $todo->priority; ?>"><?= html_escape($todo_priorities[(int) $todo->priority] ?? '—'); ?></span>
                                                <?php if ($todo->due_date): ?> · <i class="fa fa-calendar"></i> <?= html_escape(_d($todo->due_date)); ?><?php endif; ?>
                                                <?php if (trim((string) $todo->assignee_names) !== ''): ?> · <i class="fa fa-user"></i> <?= html_escape($todo->assignee_names); ?><?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($can_edit): ?>
                                            <button type="button" class="ptk-todo-delete" data-url="<?= admin_url('pro_tickets/delete_todo/' . (int) $todo->id); ?>" title="<?= _l('pro_tickets_todo_delete'); ?>"><i class="fa fa-xmark"></i></button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($can_edit): ?>
                        <!-- Add To-Do modal -->
                        <div class="ptk-modal" id="ptk-todo-modal" hidden>
                            <div class="ptk-modal-backdrop" data-ptk-modal-close></div>
                            <div class="ptk-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ptk-todo-modal-title">
                                <div class="ptk-modal-head">
                                    <h4 id="ptk-todo-modal-title"><i class="fa fa-list-check"></i> <?= _l('pro_tickets_add_todo'); ?></h4>
                                    <button type="button" class="ptk-modal-x" data-ptk-modal-close aria-label="Close"><i class="fa fa-xmark"></i></button>
                                </div>
                                <?= form_open(admin_url('pro_tickets/add_todo/' . (int) $ticket->ticketid), ['class' => 'ptk-todo-form']); ?>
                                    <div class="ptk-modal-body">
                                        <label class="ptk-field-label"><?= _l('pro_tickets_todo_title_ph'); ?></label>
                                        <input type="text" name="title" class="ptk-input" placeholder="<?= _l('pro_tickets_todo_title_ph'); ?>" required maxlength="500" autofocus>
                                        <label class="ptk-field-label"><i class="fa fa-align-left"></i> <?= _l('pro_tickets_todo_description'); ?></label>
                                        <textarea name="description" class="ptk-textarea" rows="3" placeholder="<?= _l('pro_tickets_todo_description_ph'); ?>"></textarea>
                                        <div class="ptk-todo-form-row">
                                            <div>
                                                <label class="ptk-field-label"><i class="fa fa-calendar"></i> <?= _l('pro_tickets_todo_due'); ?></label>
                                                <input type="date" name="due_date" class="ptk-input" min="<?= date('Y-m-d'); ?>" value="<?= date('Y-m-d'); ?>">
                                            </div>
                                            <div>
                                                <label class="ptk-field-label"><?= _l('pro_tickets_col_priority'); ?></label>
                                                <select name="priority" class="ptk-select">
                                                    <option value="1"><?= _l('pro_tickets_todo_low'); ?></option>
                                                    <option value="2" selected><?= _l('pro_tickets_todo_medium'); ?></option>
                                                    <option value="3"><?= _l('pro_tickets_todo_high'); ?></option>
                                                    <option value="4"><?= _l('pro_tickets_todo_urgent'); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                        <label class="ptk-field-label"><i class="fa fa-user"></i> <?= _l('pro_tickets_col_assigned'); ?></label>
                                        <select name="assignee" class="ptk-select">
                                            <option value="0"><?= _l('pro_tickets_todo_no_assignee'); ?></option>
                                            <?php foreach ($staff as $member): ?>
                                                <option value="<?= (int) $member->staffid; ?>"><?= html_escape($member->firstname . ' ' . $member->lastname); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="ptk-modal-foot">
                                        <button type="button" class="ptk-btn ptk-btn-light" data-ptk-modal-close><?= _l('pro_tickets_cancel'); ?></button>
                                        <button type="submit" class="ptk-btn ptk-btn-primary"><i class="fa fa-plus"></i> <?= _l('pro_tickets_add_todo'); ?></button>
                                    </div>
                                <?= form_close(); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ══ TAB: Notes ══ -->
                <div class="ptk-ttab-panel" data-panel="notes">
                    <!-- ── Internal notes ── -->
                    <div class="ptk-card">
                        <h4 class="ptk-card-title"><i class="fa fa-note-sticky"></i> <?= _l('pro_tickets_internal_notes'); ?> <span class="ptk-pill"><?= count($ticket->notes); ?></span></h4>
                        <?= form_open(admin_url('pro_tickets/add_note/' . (int) $ticket->ticketid), ['class' => 'ptk-note-form']); ?>
                            <textarea name="note" class="ptk-textarea" rows="2" placeholder="<?= _l('pro_tickets_note_ph'); ?> — <?= _l('pro_tickets_mention_hint'); ?>"></textarea>
                            <button type="submit" class="ptk-btn ptk-btn-light"><i class="fa fa-plus"></i> <?= _l('pro_tickets_add_note'); ?></button>
                        <?= form_close(); ?>
                        <?php foreach ($ticket->notes as $note): ?>
                            <div class="ptk-note">
                                <div class="ptk-note-head">
                                    <strong><?= html_escape(trim($note->firstname . ' ' . $note->lastname)); ?></strong>
                                    <span class="ptk-muted ptk-small"><?= html_escape(time_ago($note->created_at)); ?></span>
                                </div>
                                <div class="ptk-note-body"><?= nl2br(pro_tickets_highlight_mentions(pro_tickets_linkify(html_escape($note->note)))); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ══ TAB: Activity ══ -->
                <div class="ptk-ttab-panel" data-panel="activity">
                    <!-- ── Activity timeline ── -->
                    <div class="ptk-card">
                        <h4 class="ptk-card-title"><i class="fa fa-wave-square"></i> <?= _l('pro_tickets_activity_timeline'); ?></h4>
                        <div class="ptk-timeline">
                            <?php foreach ($ticket->activity as $item): ?>
                                <div class="ptk-timeline-item">
                                    <span class="ptk-feed-dot ptk-act-<?= html_escape($item->type); ?>"></span>
                                    <div>
                                        <div class="ptk-feed-text"><?= html_escape(pro_tickets_activity_line($item)); ?></div>
                                        <div class="ptk-muted ptk-small"><?= html_escape(_dt($item->created_at)); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>
<script>window.ptkTodoDeleteConfirm = <?= json_encode(_l('pro_tickets_todo_delete_confirm')); ?>;</script>
<?php
// Roster for the @mention picker (reply composer + internal notes). Everyone
// but the current staff member — mentioning yourself notifies nobody.
$ptk_mention_roster = [];
foreach (pro_tickets_mentionable_staff() as $ptk_member) {
    if ($ptk_member['id'] !== (int) get_staff_user_id()) {
        $ptk_mention_roster[] = $ptk_member;
    }
}
?>
<script>window.PTK_MENTIONS = <?= json_encode($ptk_mention_roster, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;</script>
<?php init_tail(); ?>
</body>
</html>
