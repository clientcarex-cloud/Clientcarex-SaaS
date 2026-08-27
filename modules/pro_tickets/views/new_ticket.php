<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="ptk-wrap">

            <?php $active = 'new'; include __DIR__ . '/_nav.php'; ?>

            <div class="ptk-ticket-layout">

                <!-- ── Main column: the new-ticket form ── -->
                <div class="ptk-ticket-main">
                    <div class="ptk-card">
                        <h4 class="ptk-card-title"><i class="fa fa-plus"></i> <?= _l('pro_tickets_new'); ?></h4>

                        <?= form_open_multipart(admin_url('pro_tickets/new_ticket'), ['id' => 'ptk-new-form']); ?>

                            <!-- Requester — tenant or customer first, then that tenant's
                                 active staff / that customer's active contacts. Falls back
                                 to the classic smart search when this install has neither
                                 (e.g. running on a tenant instance). -->
                            <div class="ptk-form-section" id="ptk-requester"
                                 data-search-url="<?= admin_url('pro_tickets/requester_search'); ?>"
                                 data-tenant-staff-url="<?= admin_url('pro_tickets/tenant_staff'); ?>"
                                 data-customer-contacts-url="<?= admin_url('pro_tickets/customer_contacts'); ?>">
                                <label class="ptk-field-label"><i class="fa fa-user-plus"></i> <?= _l('pro_tickets_new_requester'); ?></label>

                                <?php if (!empty($tenants) || !empty($customers)): ?>
                                    <div class="ptk-form-row">
                                        <div class="ptk-form-field">
                                            <label><?= _l('pro_tickets_new_tenant_customer'); ?> *</label>
                                            <!-- Searchable combo over BOTH sources: the input filters the
                                                 list; the hidden select underneath holds the picked key
                                                 ("t:<slug>" tenant, "c:<userid>" customer) and drives the
                                                 people loader via its change event. -->
                                            <div class="ptk-contact-search" id="ptk-req-tenant-box">
                                                <i class="fa fa-magnifying-glass"></i>
                                                <input type="text" id="ptk-req-tenant-q" autocomplete="off" placeholder="<?= _l('pro_tickets_new_tenant_customer_search_ph'); ?>">
                                                <div class="ptk-contact-results" id="ptk-req-tenant-list"></div>
                                            </div>
                                            <select id="ptk-req-tenant" style="display:none">
                                                <option value=""><?= _l('pro_tickets_new_tenant_ph'); ?></option>
                                                <?php foreach ($tenants as $t): ?>
                                                    <option value="t:<?= html_escape($t['slug']); ?>"><?= html_escape($t['name']); ?></option>
                                                <?php endforeach; ?>
                                                <?php foreach ($customers as $c): ?>
                                                    <option value="c:<?= (int) $c['userid']; ?>"><?= html_escape($c['company']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="ptk-contact-search" id="ptk-req-staff-box" style="display:none">
                                        <i class="fa fa-magnifying-glass"></i>
                                        <input type="text" id="ptk-req-staff-filter" autocomplete="off" placeholder="<?= _l('pro_tickets_new_people_ph'); ?>">
                                        <div class="ptk-contact-results" id="ptk-req-staff-list"></div>
                                    </div>
                                    <div class="ptk-small ptk-muted ptk-req-staff-status" id="ptk-req-staff-status" style="display:none"
                                         data-loading="<?= _l('pro_tickets_new_people_loading'); ?>"
                                         data-none="<?= _l('pro_tickets_new_people_none'); ?>"
                                         data-count="<?= _l('pro_tickets_new_people_count'); ?>"></div>
                                <?php else: ?>
                                    <div class="ptk-contact-search" id="ptk-req-search">
                                        <i class="fa fa-magnifying-glass"></i>
                                        <input type="text" id="ptk-req-q" autocomplete="off" placeholder="<?= _l('pro_tickets_new_requester_search_ph'); ?>">
                                        <div class="ptk-contact-results" id="ptk-req-results"></div>
                                    </div>
                                <?php endif; ?>

                                <!-- Picked people: first is the requester, the rest are CC.
                                     Hidden inputs describing the selection are (re)built by JS. -->
                                <div class="ptk-contact-selected" id="ptk-req-selected" style="display:none">
                                    <div id="ptk-req-chips"></div>
                                </div>
                                <div id="ptk-req-hidden"></div>

                                <!-- Fallback: someone not in the system yet -->
                                <div class="ptk-req-manual" id="ptk-req-manual" style="display:none">
                                    <div class="ptk-form-row">
                                        <div class="ptk-form-field">
                                            <label><?= _l('pro_tickets_new_name'); ?></label>
                                            <input type="text" name="name" id="ptk-req-name" class="ptk-input">
                                        </div>
                                        <div class="ptk-form-field">
                                            <label><?= _l('pro_tickets_new_email'); ?></label>
                                            <input type="email" name="email" id="ptk-req-email" class="ptk-input">
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="ptk-req-manual-toggle" id="ptk-req-manual-toggle">
                                    <i class="fa fa-pen"></i> <span><?= _l('pro_tickets_new_requester_manual'); ?></span>
                                </button>
                            </div>

                            <!-- Classification -->
                            <div class="ptk-form-row">
                                <div class="ptk-form-field">
                                    <label><?= _l('pro_tickets_new_department'); ?> *</label>
                                    <select name="department" class="ptk-select" required>
                                        <?php foreach ($departments as $dep): ?>
                                            <option value="<?= (int) $dep->departmentid; ?>"><?= html_escape($dep->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="ptk-form-field">
                                    <label><?= _l('pro_tickets_new_priority'); ?></label>
                                    <select name="priority" class="ptk-select">
                                        <?php foreach ($priorities as $pr): ?>
                                            <option value="<?= (int) $pr->priorityid; ?>" <?= $pr->priorityid == 2 ? 'selected' : ''; ?>><?= html_escape($pr->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="ptk-form-field">
                                    <label><?= _l('pro_tickets_new_service'); ?></label>
                                    <select name="service" class="ptk-select">
                                        <option value="0">—</option>
                                        <?php foreach ($services as $srv): ?>
                                            <option value="<?= (int) $srv->serviceid; ?>"><?= html_escape($srv->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="ptk-form-field">
                                    <label><?= _l('pro_tickets_new_assigned'); ?></label>
                                    <select name="assigned" class="ptk-select">
                                        <option value="0"><?= _l('pro_tickets_new_auto_assign'); ?></option>
                                        <?php foreach ($staff as $member): ?>
                                            <option value="<?= (int) $member->staffid; ?>"><?= html_escape($member->firstname . ' ' . $member->lastname); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="ptk-form-field">
                                <label><?= _l('pro_tickets_new_subject'); ?> *</label>
                                <input type="text" name="subject" id="ptk-new-subject" class="ptk-input" required maxlength="191">
                            </div>
                            <div class="ptk-form-field ptk-composer">
                                <?php $ptk_ai_available = function_exists('pro_ai_render_ai_button')
                                    && function_exists('pro_ai_is_available') && pro_ai_is_available(); ?>
                                <?php if ($ptk_ai_available): ?>
                                    <div class="ptk-composer-label" style="display:flex;align-items:center;gap:8px;">
                                        <label style="margin-bottom:0;"><?= _l('pro_tickets_new_message'); ?> *</label>
                                        <span style="margin-left:auto;display:inline-flex;align-items:center;gap:6px;">
                                            <script>
                                                // Gather the whole new-ticket draft (form fields + message) for HealthO AI.
                                                window.ptkProAiNewTicketContent = function () {
                                                    function val(sel) { var el = document.querySelector(sel); return el ? el.value.trim() : ''; }
                                                    function msgVal() {
                                                        // The composer is a TinyMCE editor — read live content from it
                                                        // when present, falling back to the raw textarea value.
                                                        var ed = (typeof tinymce !== 'undefined') ? tinymce.get('ptk-new-message') : null;
                                                        if (ed) {
                                                            var tmp = document.createElement('div');
                                                            tmp.innerHTML = ed.getContent();
                                                            return (tmp.textContent || tmp.innerText || '').trim();
                                                        }
                                                        return val('#ptk-new-message');
                                                    }
                                                    function selText(sel) {
                                                        var el = document.querySelector(sel);
                                                        return (el && el.selectedIndex >= 0) ? el.options[el.selectedIndex].text.trim() : '';
                                                    }
                                                    var requester = Array.prototype.map.call(
                                                        document.querySelectorAll('#ptk-req-chips .ptk-contact-chip'),
                                                        function (chip) { return chip.getAttribute('data-label'); }
                                                    ).join(', ');
                                                    if (!requester) {
                                                        var rn = val('#ptk-req-name'), re = val('#ptk-req-email');
                                                        requester = rn && re ? rn + ' <' + re + '>' : (rn || re);
                                                    }
                                                    var lines = [
                                                        'NEW SUPPORT TICKET DRAFT (being composed by a support agent — not yet submitted)',
                                                        'Subject: ' + (val('#ptk-new-subject') || '(none yet)'),
                                                        'Department: ' + selText('select[name="department"]')
                                                            + ' | Priority: ' + selText('select[name="priority"]'),
                                                        'Requester: ' + (requester || '(not selected yet)'),
                                                        '',
                                                        '--- CURRENT DRAFT MESSAGE ---',
                                                        msgVal() || '(empty — nothing typed yet)'
                                                    ];
                                                    return lines.join('\n');
                                                };
                                                // The draft currently in the message composer, as HTML — the
                                                // one-click correction button rewrites exactly this in place.
                                                window.ptkProAiNewDraft = function () {
                                                    var ed = (typeof tinymce !== 'undefined') ? tinymce.get('ptk-new-message') : null;
                                                    if (ed) { return ed.getContent(); }
                                                    var ta = document.getElementById('ptk-new-message');
                                                    return ta ? ta.value : '';
                                                };
                                            </script>
                                            <?php // One-click proofread of the draft already in the box: no prompt
                                                  // picker, no popup — it rewrites the message in place (same as reply). ?>
                                            <?php pro_ai_render_ai_button([
                                                'module'           => 'pro_tickets',
                                                'mode'             => 'analyze',
                                                'instant'          => true,
                                                'instant_prompt'   => _l('pro_tickets_ai_correct_prompt'),
                                                'instant_empty'    => _l('pro_tickets_ai_correct_empty'),
                                                'content_provider' => 'ptkProAiNewDraft',
                                                'insert_target'    => '#ptk-new-message',
                                                'insert_mode'      => 'replace',
                                                'insert_html'      => true,
                                                'class'            => 'ptk-ai-instant-icon',
                                                'label'            => _l('pro_tickets_ai_correct'),
                                                'title'            => _l('pro_tickets_ai_correct_hint'),
                                            ]); ?>
                                            <?php pro_ai_render_ai_button([
                                                'module'           => 'pro_tickets',
                                                'mode'             => 'analyze',
                                                'content_provider' => 'ptkProAiNewTicketContent',
                                                'insert_target'    => '#ptk-new-message',
                                                'insert_mode'      => 'replace',
                                                'insert_label'     => _l('pro_ai_use_message'),
                                                'label'            => _l('pro_ai_assistant'),
                                            ]); ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <label class="ptk-composer-label"><?= _l('pro_tickets_new_message'); ?> *</label>
                                <?php endif; ?>

                                <!-- Predefined messages: same strip as the reply composer —
                                     insert a template, or save the composed message as a new
                                     reusable one. Merge tags that depend on the requester
                                     ({Name}, {Subject}) are filled by the browser as the form
                                     is completed; agent / role / company resolve server-side. -->
                                <div class="ptk-composer-head">
                                    <div class="ptk-composer-bar">
                                        <div class="ptk-canned-pick">
                                            <i class="fa fa-bolt ptk-canned-icon" aria-hidden="true"></i>
                                            <select class="ptk-canned" id="ptk-canned" aria-label="<?= _l('pro_tickets_canned_insert'); ?>"
                                                data-url="<?= admin_url('pro_tickets/canned'); ?>" data-target="#ptk-new-message">
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
                                        data-target="#ptk-new-message"
                                        data-missing="<?= _l('pro_tickets_canned_missing'); ?>"
                                        data-saved="<?= _l('pro_tickets_canned_saved'); ?>" hidden>
                                        <input type="text" class="ptk-input" id="ptk-canned-name" maxlength="191" placeholder="<?= _l('pro_tickets_canned_name_ph'); ?>">
                                        <button type="button" class="ptk-btn ptk-btn-primary ptk-btn-sm" id="ptk-canned-save-confirm"><i class="fa fa-check"></i> <?= _l('pro_tickets_save'); ?></button>
                                        <button type="button" class="ptk-icon-btn" id="ptk-canned-save-cancel" title="<?= _l('pro_tickets_cancel'); ?>"><i class="fa fa-xmark"></i></button>
                                    </div>
                                </div>

                                <textarea name="message" id="ptk-new-message" class="ptk-textarea" rows="8"><?= html_escape(isset($prefill) ? $prefill : ''); ?></textarea>
                                <div class="ptk-small ptk-muted ptk-tpl-hint"><i class="fa fa-tags"></i> <?= _l('pro_tickets_tpl_tags_hint'); ?></div>
                            </div>

                            <?php // Attachments ride along with the ticket — the core ticket
                                  // model stores whatever is posted as attachments[]. ?>
                            <?php $ptk_allowed_ext = trim((string) get_option('ticket_attachments_file_extensions')); ?>
                            <div class="ptk-composer-foot">
                                <label class="ptk-file-label" title="<?= html_escape($ptk_allowed_ext); ?>">
                                    <i class="fa fa-paperclip"></i>
                                    <input type="file" name="attachments[]" multiple id="ptk-attach-input"
                                        <?= $ptk_allowed_ext !== '' ? 'accept="' . html_escape($ptk_allowed_ext) . '"' : ''; ?>>
                                    <span id="ptk-attach-names"><?= _l('pro_tickets_attachments'); ?></span>
                                </label>
                                <div class="ptk-composer-actions">
                                    <button type="submit" class="ptk-btn ptk-btn-primary"><i class="fa fa-paper-plane"></i> <?= _l('pro_tickets_new_submit'); ?></button>
                                </div>
                            </div>

                        <?= form_close(); ?>
                    </div>
                </div>

                <!-- ── Sidebar: Todo / Caller / Mailbox companions ── -->
                <div class="ptk-ticket-side">

                    <!-- Optional linked follow-up to-do task (fields submit with the main form) -->
                    <div class="ptk-card">
                        <h4 class="ptk-card-title"><i class="fa fa-list-check"></i> <?= _l('pro_tickets_todos'); ?></h4>
                        <div class="ptk-todo-create">
                            <label class="ptk-todo-create-check">
                                <input type="checkbox" name="create_todo" value="1" id="ptk-create-todo" form="ptk-new-form">
                                <?= _l('pro_tickets_todo_create_label'); ?>
                            </label>
                            <div id="ptk-create-todo-fields" style="display:none">
                                <div class="ptk-form-field">
                                    <label><?= _l('pro_tickets_todo_title_ph'); ?></label>
                                    <input type="text" name="todo_title" class="ptk-input" maxlength="500" placeholder="<?= _l('pro_tickets_todo_title_auto_ph'); ?>" form="ptk-new-form">
                                </div>
                                <div class="ptk-form-field">
                                    <label><?= _l('pro_tickets_todo_due'); ?></label>
                                    <input type="date" name="todo_due_date" class="ptk-input" min="<?= date('Y-m-d'); ?>" form="ptk-new-form">
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($caller_active)): ?>
                        <!-- Caller module — recent calls of the primary selected contact -->
                        <div class="ptk-card" id="ptk-contact-calls" data-url="<?= admin_url('pro_tickets/contact_calls'); ?>">
                            <h4 class="ptk-card-title"><i class="fa fa-phone"></i> <?= _l('pro_tickets_calls'); ?> <span class="ptk-pill" id="ptk-contact-calls-count">0</span></h4>
                            <div class="ptk-call-list" id="ptk-contact-calls-list"></div>
                            <div class="ptk-small ptk-muted" id="ptk-contact-calls-empty"
                                 data-hint="<?= _l('pro_tickets_select_contact_hint'); ?>"
                                 data-none="<?= _l('pro_tickets_calls_none'); ?>"><?= _l('pro_tickets_select_contact_hint'); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($mailbox_active)): ?>
                        <!-- Mailbox module — latest emails with the primary selected contact -->
                        <div class="ptk-card" id="ptk-contact-emails" data-url="<?= admin_url('pro_tickets/contact_emails'); ?>">
                            <h4 class="ptk-card-title"><i class="fa fa-envelope-open-text"></i> <?= _l('pro_tickets_emails'); ?> <span class="ptk-pill" id="ptk-contact-emails-count">0</span></h4>
                            <div class="ptk-email-list" id="ptk-contact-emails-list"></div>
                            <div class="ptk-small ptk-muted" id="ptk-contact-emails-empty"
                                 data-hint="<?= _l('pro_tickets_select_contact_hint'); ?>"
                                 data-none="<?= _l('pro_tickets_emails_none'); ?>"><?= _l('pro_tickets_select_contact_hint'); ?></div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
