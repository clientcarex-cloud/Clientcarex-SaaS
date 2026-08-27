<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php include __DIR__ . '/_styles.php'; ?>
<?php
$initials = function ($name) {
    $name  = trim((string) $name);
    if ($name === '') {
        return '?';
    }
    $parts = preg_split('/\s+/', $name);
    $first = mb_substr($parts[0], 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
};

// Message avatar — the real profile photo when one exists (staff photo for
// agent messages, contact photo for the customer's own), falling back to
// the initials bubble when there is none.
$ptkc_avatar_placeholder = base_url('assets/images/user-placeholder.jpg');
$ptkc_avatar = function ($who, $is_staff, $person_id) use ($initials, $ptkc_avatar_placeholder) {
    $url = '';
    if ($person_id) {
        $url = $is_staff ? staff_profile_image_url($person_id) : contact_profile_image_url($person_id);
    }
    if ($url !== '' && $url !== $ptkc_avatar_placeholder) { ?>
        <div class="ptkc-avatar ptkc-avatar-img"><img src="<?= $url; ?>" alt="<?= e($who); ?>" loading="lazy"></div>
    <?php } else { ?>
        <div class="ptkc-avatar"><?= e($initials($who)); ?></div>
    <?php }
};

/**
 * Render one message bubble (original ticket message or a reply).
 */
$render_message = function ($who, $is_staff, $body, $date, $attachments, $ticket, $person_id = 0) use ($ptkc_avatar) { ?>
    <div class="ptkc-msg<?= $is_staff ? ' staff' : ''; ?>">
        <div class="ptkc-msg-head">
            <?php $ptkc_avatar($who, $is_staff, (int) $person_id); ?>
            <div>
                <div class="ptkc-msg-who"><?= e($who); ?></div>
                <?php if ($is_staff) { ?>
                <div class="ptkc-msg-role"><?= _l('ticket_staff_string'); ?></div>
                <?php } ?>
            </div>
            <?php if (!empty($date)) { ?>
            <div class="ptkc-msg-date"><i class="fa-regular fa-clock"></i> <?= e(_dt($date)); ?></div>
            <?php } ?>
        </div>
        <div class="ptkc-msg-body">
            <?php echo $is_staff ? check_for_links($body) : process_text_content_for_display($body); ?>
            <?php if (!empty($attachments) && count($attachments) > 0) { ?>
            <div class="ptkc-attachments">
                <?php foreach ($attachments as $attachment) {
                    $path     = get_upload_path_by_type('ticket') . $ticket->ticketid . '/' . $attachment['file_name'];
                    $is_image = is_image($path);
                    if ($is_image) {
                        $preview_url = site_url('download/preview_image?path=' . protected_file_url_by_path($path) . '&type=' . $attachment['filetype']); ?>
                <a class="ptkc-attach-img" href="<?= $preview_url; ?>"
                    data-lightbox="ptkc-ticket-<?= (int) $ticket->ticketid; ?>"
                    data-title="<?= e($attachment['file_name']); ?>">
                    <img src="<?= $preview_url; ?>" alt="<?= e($attachment['file_name']); ?>" loading="lazy">
                </a>
                <?php } else { ?>
                <a class="ptkc-attach" href="<?= site_url('download/file/ticket/' . $attachment['id'] . '?ticket_key=' . (get_option('disable_ticket_public_url') != '1' ? $ticket->ticketkey : '')); ?>">
                    <i class="<?= get_mime_class($attachment['filetype']); ?>"></i>
                    <span><?= e($attachment['file_name']); ?></span>
                </a>
                <?php } ?>
                <?php } ?>
            </div>
            <?php } ?>
        </div>
    </div>
<?php };
?>
<div class="ptkc-wrap">

    <?php include __DIR__ . '/_smart_ticket_loader.php'; ?>

    <div class="ptkc-header">
        <h4 class="ptkc-title">
            <i class="fa-regular fa-comments"></i>
            #<?= e($ticket->ticketid); ?> — <?= e($ticket->subject); ?>
        </h4>
        <div class="ptkc-header-actions">
            <a href="<?= site_url('clients/pro_tickets'); ?>" class="ptkc-btn ptkc-btn-ghost">
                <i class="fa-solid fa-arrow-left"></i> <?= _l('pro_tickets_all_tickets'); ?>
            </a>
        </div>
    </div>

    <?php if ($ticket->project_id) { ?>
    <div class="alert alert-info">
        <?= _l('ticket_linked_to_project', '<a href="' . site_url('clients/project/' . $ticket->project_id) . '"><b>' . e(get_project_name_by_id($ticket->project_id)) . '</b></a>'); ?>
    </div>
    <?php } ?>

    <?php set_ticket_open($ticket->clientread, $ticket->ticketid, false); ?>
    <?= form_hidden('ticket_id', $ticket->ticketid); ?>

    <div class="ptkc-single">

        <!-- ── Left column: ticket info + to-do progress ─────────────── -->
        <div class="ptkc-single-side">

        <!-- ── ticket info ───────────────────────────────────────────── -->
        <div class="ptkc-card">
            <div class="ptkc-card-head"><?= _l('clients_single_ticket_information_heading'); ?></div>
            <div class="ptkc-card-body" style="padding-top:6px;padding-bottom:6px;">
                <div class="ptkc-info-row">
                    <span class="k"><?= _l('clients_ticket_single_status'); ?></span>
                    <span class="v">
                        <span class="ticket-status-inline">
                            <span class="ptkc-pill" style="background:<?= e($ticket->statuscolor); ?>">
                                <?= e(ticket_status_translate($ticket->ticketstatusid)); ?>
                                <?php if (get_option('allow_customer_to_change_ticket_status') == 1 && can_change_ticket_status_in_clients_area()) { ?>
                                <i class="fa-regular fa-pen-to-square pointer toggle-change-ticket-status" style="margin-left:5px;"></i>
                                <?php } ?>
                            </span>
                        </span>
                        <?php if (can_change_ticket_status_in_clients_area()) { ?>
                        <span class="ticket-status hide" style="display:inline-block;margin-top:6px;">
                            <span class="input-group input-group-sm">
                                <select data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>" id="ticket_status_single" class="form-control" name="ticket_status_single">
                                    <?php foreach ($ticket_statuses as $status) {
                                        if (!can_change_ticket_status_in_clients_area($status['ticketstatusid'])) {
                                            continue;
                                        } ?>
                                    <option value="<?= e($status['ticketstatusid']); ?>" <?php if ($status['ticketstatusid'] == $ticket->ticketstatusid) {
                                        echo 'selected';
                                    } ?>>
                                        <?= e(ticket_status_translate($status['ticketstatusid'])); ?>
                                    </option>
                                    <?php } ?>
                                </select>
                                <span class="input-group-btn">
                                    <button class="btn btn-default toggle-change-ticket-status" type="button"><i class="fa fa-remove pointer"></i></button>
                                </span>
                            </span>
                        </span>
                        <?php } ?>
                    </span>
                </div>
                <div class="ptkc-info-row">
                    <span class="k"><?= _l('priority'); ?></span>
                    <span class="v"><?= e(ticket_priority_translate($ticket->priorityid)); ?></span>
                </div>
                <div class="ptkc-info-row">
                    <span class="k"><?= _l('clients_ticket_open_departments'); ?></span>
                    <span class="v"><?= e($ticket->department_name); ?></span>
                </div>
                <div class="ptkc-info-row">
                    <span class="k"><?= _l('ticket_dt_submitter'); ?></span>
                    <span class="v"><?= e($ticket->submitter); ?></span>
                </div>
                <div class="ptkc-info-row">
                    <span class="k"><?= _l('clients_tickets_dt_number'); ?></span>
                    <span class="v"><?= e(_dt($ticket->date)); ?></span>
                </div>
                <?php if (get_option('services') == 1 && !empty($ticket->service_name)) { ?>
                <div class="ptkc-info-row">
                    <span class="k"><?= _l('service'); ?></span>
                    <span class="v"><?= e($ticket->service_name); ?></span>
                </div>
                <?php } ?>
                <?php
                $custom_fields = get_custom_fields('tickets', ['show_on_client_portal' => 1]);
                foreach ($custom_fields as $field) {
                    $cfValue = get_custom_field_value($ticket->ticketid, $field['id'], 'tickets');
                    if (empty($cfValue)) {
                        continue;
                    } ?>
                <div class="ptkc-info-row">
                    <span class="k"><?= e($field['name']); ?></span>
                    <span class="v"><?= $cfValue; ?></span>
                </div>
                <?php } ?>
            </div>
        </div>

        <?php
        $is_closed        = (int) $ticket->ticketstatusid === 5;
        $feedback_missing = $is_closed && empty($ticket_feedback);
        ?>
        <?php if (!empty($ticket_feedback) || $feedback_missing) { ?>
        <!-- ── Support feedback (CSAT) ───────────────────────────────── -->
        <div class="ptkc-card">
            <div class="ptkc-card-head"><?= _l('pro_tickets_feedback_card_title'); ?></div>
            <div class="ptkc-card-body">
                <?php if (!empty($ticket_feedback)) { ?>
                <div class="ptkc-fb-given-stars">
                    <?php for ($i = 1; $i <= 5; $i++) { ?>
                    <i class="fa-solid fa-star<?= $i > (int) $ticket_feedback->rating ? ' off' : ''; ?>"></i>
                    <?php } ?>
                    <span style="color:var(--ptkc-text);font-weight:700;font-size:13px;margin-left:6px;"><?= (int) $ticket_feedback->rating; ?>/5</span>
                </div>
                <?php if (!empty($ticket_feedback->comment)) { ?>
                <div class="ptkc-fb-given-comment"><?= e($ticket_feedback->comment); ?></div>
                <?php } ?>
                <?php } else { ?>
                <div class="ptkc-fb-cta">
                    <div class="ptkc-fb-cta-text"><?= _l('pro_tickets_feedback_prompt_card'); ?></div>
                    <button type="button" class="ptkc-btn ptkc-btn-primary"
                        onclick="ptkcFeedbackOpen(<?= (int) $ticket->ticketid; ?>, <?= e(json_encode($ticket->subject)); ?>, <?= e(json_encode($feedback_agent)); ?>)">
                        <i class="fa-regular fa-star"></i> <?= _l('pro_tickets_feedback_give'); ?>
                    </button>
                </div>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <?php if (!empty($ticket_todos)): ?>
        <!-- ── To-Do progress (read-only for the customer) ───────────── -->
        <div class="ptkc-card ptkc-todo-card">
            <div class="ptkc-card-head"><?= _l('pro_tickets_todos'); ?></div>
            <div class="ptkc-card-body">
                <?php if (!empty($todo_progress['total'])): ?>
                    <div class="ptkc-todo-progress">
                        <div class="ptkc-todo-progress-head">
                            <span><?= _l('pro_tickets_todo_progress', [(int) $todo_progress['done'], (int) $todo_progress['total']]); ?></span>
                            <strong class="<?= $todo_progress['pct'] == 100 ? 'is-done' : ''; ?>"><?= (int) $todo_progress['pct']; ?>%</strong>
                        </div>
                        <div class="ptkc-todo-progress-track">
                            <span class="ptkc-todo-progress-bar <?= $todo_progress['pct'] == 100 ? 'is-complete' : ''; ?>" style="width:<?= (int) $todo_progress['pct']; ?>%"></span>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="ptkc-todo-list">
                    <?php foreach ($ticket_todos as $todo): $todo_done = (int) $todo->status === 2; ?>
                        <div class="ptkc-todo <?= $todo_done ? 'is-done' : ''; ?>">
                            <span class="ptkc-todo-dot"><i class="fa <?= $todo_done ? 'fa-circle-check' : 'fa-circle'; ?>"></i></span>
                            <div class="ptkc-todo-main">
                                <div class="ptkc-todo-title"><?= e($todo->title); ?></div>
                                <?php if (trim((string) ($todo->description ?? '')) !== ''): ?>
                                    <div class="ptkc-todo-desc"><?= nl2br(e($todo->description)); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if ($todo->due_date): ?>
                                <span class="ptkc-todo-due"><i class="fa fa-calendar"></i> <?= e(_d($todo->due_date)); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        </div><!-- /.ptkc-single-side -->

        <!-- ── Right: reply + conversation ───────────────────────────── -->
        <div>
            <?= form_open_multipart($this->uri->uri_string(), ['id' => 'ticket-reply']); ?>
            <div class="ptkc-card ptkc-reply-box">
                <div class="ptkc-card-head"><?= _l('clients_ticket_single_add_reply_heading'); ?></div>
                <div class="ptkc-card-body">
                    <div class="ptkc-field" style="margin-bottom:12px;">
                        <textarea name="message" id="ptkc-reply-editor" class="form-control" rows="6" placeholder="<?= _l('ticket_reply'); ?>"></textarea>
                        <?= form_error('message'); ?>
                    </div>
                    <div class="attachments_area">
                        <div class="attachments">
                            <div class="attachment tw-max-w-md">
                                <div class="ptkc-field" style="margin-bottom:0;">
                                    <label class="control-label"><?= _l('clients_ticket_attachments'); ?></label>
                                    <div class="input-group">
                                        <input type="file"
                                            extension="<?= str_replace(['.', ' '], '', get_option('ticket_attachments_file_extensions')); ?>"
                                            filesize="<?= file_upload_max_size(); ?>"
                                            class="form-control" name="attachments[0]"
                                            accept="<?= get_ticket_form_accepted_mimes(); ?>">
                                        <span class="input-group-btn">
                                            <button class="btn btn-default add_more_attachments"
                                                data-max="<?= get_option('maximum_allowed_ticket_attachments'); ?>" type="button">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ptkc-card-head" style="border-top:1px solid var(--ptkc-border);border-bottom:0;text-align:right;">
                    <button class="ptkc-btn ptkc-btn-primary" type="submit" data-form="#ticket-reply" autocomplete="off"
                        data-loading-text="<?= _l('wait_text'); ?>" style="float:right;">
                        <i class="fa-solid fa-paper-plane"></i> <?= _l('ticket_single_add_reply'); ?>
                    </button>
                    <div style="clear:both;"></div>
                </div>
            </div>
            <?= form_close(); ?>

            <div class="ptkc-thread">
                <?php
                // Replies — latest first, so fresh answers sit right under the
                // reply box instead of at the end of a long thread.
                foreach (pro_tickets_replies_newest_first($ticket_replies) as $reply) {
                    $reply_is_staff = $reply['admin'] !== null;
                    $render_message(
                        $reply['submitter'],
                        $reply_is_staff,
                        $reply['message'],
                        $reply['date'],
                        $reply['attachments'],
                        $ticket,
                        $reply_is_staff ? (int) $reply['admin'] : (int) ($reply['contactid'] ?? 0)
                    );
                }
                // Original ticket message (oldest, so it closes the thread)
                $ticket_opened_by_staff = !($ticket->admin == null || $ticket->admin == 0);
                $render_message(
                    $ticket_opened_by_staff ? $ticket->opened_by : $ticket->submitter,
                    $ticket_opened_by_staff,
                    $ticket->message,
                    $ticket->date,
                    $ticket->attachments,
                    $ticket,
                    $ticket_opened_by_staff ? (int) $ticket->admin : (int) ($ticket->contactid ?? 0)
                );
                ?>
            </div>
        </div>

    </div>

    <?php
    // Ask for a rating the moment the customer lands on (or reloads into) a
    // closed ticket they haven't rated yet — covers both "customer closed it
    // just now" (status-change reload) and "our team closed it".
    $feedback_autoopen = $feedback_missing ? [
        'ticket_id' => (int) $ticket->ticketid,
        'subject'   => $ticket->subject,
        'agent'     => $feedback_agent,
    ] : null;
    include __DIR__ . '/_feedback.php';
    ?>
</div>

<?php
// Load TinyMCE only for the client ticket view (the customer-area theme does not bundle it).
if (!defined('PTKC_TINYMCE_LOADED')) {
    define('PTKC_TINYMCE_LOADED', true); ?>
<script src="<?= base_url('assets/plugins/tinymce/tinymce.min.js'); ?>?v=<?= $this->app->get_current_db_version(); ?>"></script>
<?php } ?>
<script>
(function () {
    function initPtkcEditor() {
        if (typeof tinymce === 'undefined') {
            // tinymce.min.js may still be loading; retry shortly.
            return setTimeout(initPtkcEditor, 120);
        }

        tinymce.remove('#ptkc-reply-editor');
        tinymce.init({
            selector: '#ptkc-reply-editor',
            menubar: false,
            branding: false,
            statusbar: false,
            height: 240,
            language: (window.app && app.tinymce_lang) ? app.tinymce_lang : 'en',
            relative_urls: false,
            remove_script_host: false,
            entity_encoding: 'raw',
            paste_data_images: false,
            content_style: 'body{font-family:inherit;font-size:14px;line-height:1.6;}',
            plugins: 'advlist autolink lists link image charmap searchreplace visualblocks code fullscreen table',
            toolbar: 'bold italic underline | bullist numlist | link | blockquote code | removeformat',
            // Keep replies in the underlying <textarea> in sync on submit.
            setup: function (editor) {
                editor.on('change keyup', function () {
                    editor.save();
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPtkcEditor);
    } else {
        initPtkcEditor();
    }
})();
</script>
