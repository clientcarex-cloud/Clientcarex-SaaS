<?php

defined('BASEPATH') or exit('No direct script access allowed');

# Module
$lang['mailbox']                       = 'Mailbox';
$lang['mailbox_accounts']              = 'Email Accounts';
$lang['mailbox_open_webmail']          = 'Open Webmail';
$lang['mailbox_manage_accounts']       = 'Manage Accounts';

# Webmail
$lang['mailbox_compose']               = 'Compose';
$lang['mailbox_all_accounts']          = 'All accounts';
$lang['mailbox_folder_inbox']          = 'Inbox';
$lang['mailbox_folder_starred']        = 'Starred';
$lang['mailbox_folder_sent']           = 'Sent';
$lang['mailbox_folder_drafts']         = 'Drafts';
$lang['mailbox_folder_archive']        = 'Archive';
$lang['mailbox_folder_trash']          = 'Trash';
$lang['mailbox_search_placeholder']    = 'Search mail…';
$lang['mailbox_sync_now']              = 'Sync now';
$lang['mailbox_syncing']               = 'Syncing…';
$lang['mailbox_synced']                = 'Mailbox up to date';
$lang['mailbox_new_mail']              = 'new message(s) received';
$lang['mailbox_empty_folder']          = 'Nothing here';
$lang['mailbox_empty_folder_sub']      = 'This folder has no messages yet.';
$lang['mailbox_select_message']        = 'Select a message to read it';
$lang['mailbox_no_accounts']           = 'No mailbox assigned to you yet';
$lang['mailbox_no_accounts_sub']       = 'Ask your administrator to assign an email account to your profile.';
$lang['mailbox_no_accounts_admin_sub'] = 'Connect your first professional email account to start sending and receiving.';
$lang['mailbox_connect_first']         = 'Connect an email account';
$lang['mailbox_reply']                 = 'Reply';
$lang['mailbox_reply_all']             = 'Reply all';
$lang['mailbox_forward']               = 'Forward';
$lang['mailbox_archive']               = 'Archive';
$lang['mailbox_move_to_inbox']         = 'Move to inbox';
$lang['mailbox_restore']               = 'Restore';
$lang['mailbox_delete_forever']        = 'Delete forever';
$lang['mailbox_delete_forever_confirm'] = 'Permanently delete this message? This cannot be undone.';
$lang['mailbox_mark_read']             = 'Mark as read';
$lang['mailbox_mark_unread']           = 'Mark as unread';
$lang['mailbox_to']                    = 'To';
$lang['mailbox_cc']                    = 'Cc';
$lang['mailbox_bcc']                   = 'Bcc';
$lang['mailbox_from']                  = 'From';
$lang['mailbox_subject']               = 'Subject';
$lang['mailbox_no_subject']            = '(no subject)';
$lang['mailbox_send']                  = 'Send';
$lang['mailbox_sending']               = 'Sending…';
$lang['mailbox_sent_ok']               = 'Email sent';
$lang['mailbox_save_draft']            = 'Save draft';
$lang['mailbox_draft_saved']           = 'Draft saved';
$lang['mailbox_discard']               = 'Discard';
$lang['mailbox_attach_files']          = 'Attach files';
$lang['mailbox_attachments']           = 'Attachments';
$lang['mailbox_via']                   = 'via';
$lang['mailbox_sent_by']               = 'Sent by';
$lang['mailbox_thread_earlier']        = 'Earlier in this conversation';
$lang['mailbox_load_error']            = 'Could not load — please retry';
$lang['mailbox_no_recipient']          = 'Add at least one valid recipient';
$lang['mailbox_no_smtp']               = 'This account has no working outgoing (SMTP) configuration';
$lang['mailbox_recipients_hint']       = 'Separate multiple addresses with commas';

# Account manager
$lang['mailbox_connect_account']       = 'Connect Email Account';
$lang['mailbox_edit_account']          = 'Edit Account';
$lang['mailbox_delete_account']        = 'Delete account';
$lang['mailbox_delete_account_confirm'] = 'Delete this account with ALL of its stored messages and attachments? This cannot be undone.';
$lang['mailbox_account_saved']         = 'Account saved';
$lang['mailbox_invalid_email']         = 'Enter a valid email address';
$lang['mailbox_assigned_users']        = 'Assigned users';
$lang['mailbox_nobody_assigned']       = 'Nobody assigned yet';
$lang['mailbox_active']                = 'Active';
$lang['mailbox_inactive']              = 'Inactive';
$lang['mailbox_last_sync']             = 'Last sync';
$lang['mailbox_never_synced']          = 'Never synced';
$lang['mailbox_messages_count']        = 'messages';
$lang['mailbox_unread_count']          = 'unread';
$lang['mailbox_sync_error']            = 'Sync error';
$lang['mailbox_imap_ext_missing']      = 'The PHP IMAP extension is not enabled on this server — incoming mail cannot be synced. Ask your host to enable ext-imap.';
$lang['mailbox_how_it_works']          = 'How it works';
$lang['mailbox_how_1']                 = 'Connect any professional email address using its SMTP (outgoing) and IMAP (incoming) servers — the interactive wizard fills everything in for Gmail, Outlook, Zoho, Yahoo, Titan and custom servers.';
$lang['mailbox_how_2']                 = 'Assign the account to one or more staff members. Only assigned users (and you) can read and send from it.';
$lang['mailbox_how_3']                 = 'Assigned users open Mailbox in the sidebar and get a full webmail: send, receive, reply, forward, drafts, stars, archive, trash and attachments. New mail is pulled automatically by cron and on every visit.';

# Wizard
$lang['mailbox_wizard_step_provider']  = 'Provider';
$lang['mailbox_wizard_step_identity']  = 'Identity';
$lang['mailbox_wizard_step_servers']   = 'Servers';
$lang['mailbox_wizard_step_test']      = 'Test';
$lang['mailbox_wizard_step_assign']    = 'Assign';
$lang['mailbox_wizard_pick_provider']  = 'Where is this email hosted?';
$lang['mailbox_wizard_guide_title']    = 'Before you continue';
$lang['mailbox_wizard_open_app_pw']    = 'Open app-password page';
$lang['mailbox_wizard_back']           = 'Back';
$lang['mailbox_wizard_next']           = 'Next';
$lang['mailbox_wizard_finish']         = 'Save account';
$lang['mailbox_wizard_name']           = 'Account label';
$lang['mailbox_wizard_name_hint']      = 'Shown to your team, e.g. “Support” or “Billing”';
$lang['mailbox_wizard_email']          = 'Email address';
$lang['mailbox_wizard_from_name']      = 'Sender name (From)';
$lang['mailbox_wizard_from_name_hint'] = 'Recipients see this name, e.g. “HealthO Support”';
$lang['mailbox_wizard_signature']      = 'Signature (appended to outgoing mail)';
$lang['mailbox_smtp_settings']         = 'Outgoing mail (SMTP)';
$lang['mailbox_imap_settings']         = 'Incoming mail (IMAP)';
$lang['mailbox_host']                  = 'Host';
$lang['mailbox_port']                  = 'Port';
$lang['mailbox_encryption']            = 'Encryption';
$lang['mailbox_username']              = 'Username';
$lang['mailbox_username_hint']         = 'Leave empty to use the email address';
$lang['mailbox_password']              = 'Password / App password';
$lang['mailbox_password_keep']         = 'Leave empty to keep the current password';
$lang['mailbox_imap_same_password']    = 'Leave empty to reuse the SMTP password';
$lang['mailbox_imap_folder']           = 'Folder to sync';
$lang['mailbox_imap_sent_folder']      = 'Sent folder (copy outgoing mail to server)';
$lang['mailbox_imap_sent_folder_hint'] = 'Optional. Gmail does this automatically — leave empty for Gmail.';
$lang['mailbox_validate_cert']         = 'Validate SSL certificate';
$lang['mailbox_test_smtp']             = 'Test outgoing (SMTP)';
$lang['mailbox_test_imap']             = 'Test incoming (IMAP)';
$lang['mailbox_testing']               = 'Testing…';
$lang['mailbox_test_ok']               = 'Connection OK';
$lang['mailbox_test_failed']           = 'Failed';
$lang['mailbox_test_debug']            = 'Show technical details';
$lang['mailbox_test_hint']             = 'Run both tests — green on both means the account is fully operational. You can still save and fix it later.';
$lang['mailbox_folders_found']         = 'Folders found on the server';
$lang['mailbox_assign_hint']           = 'Pick who can use this mailbox. Assigned users get full send & receive access from the Mailbox app.';
$lang['mailbox_search_staff']          = 'Search staff…';
$lang['mailbox_account_active_hint']   = 'Inactive accounts stop syncing and disappear from users.';

# Provider guides
$lang['mailbox_provider_custom']       = 'Other / Custom (cPanel, Plesk, own server)';
$lang['mailbox_guide_gmail_1']         = 'Enable 2-Step Verification on the Google account (Google Account → Security).';
$lang['mailbox_guide_gmail_2']         = 'Create an App Password: Google Account → Security → App passwords → choose “Mail”. Google shows a 16-character password — use it here instead of the normal password.';
$lang['mailbox_guide_gmail_3']         = 'Make sure IMAP is enabled: Gmail → Settings → “Forwarding and POP/IMAP” → Enable IMAP.';
$lang['mailbox_guide_gmail_4']         = 'Server details are pre-filled below — you only need the email address and the app password.';
$lang['mailbox_guide_outlook_1']       = 'For Microsoft 365 business accounts, ask the admin to make sure “Authenticated SMTP” is enabled for the mailbox (Microsoft 365 admin center → Active users → Mail → Manage email apps).';
$lang['mailbox_guide_outlook_2']       = 'If the account uses 2-factor authentication, create an App Password from the Microsoft account security page and use it here.';
$lang['mailbox_guide_outlook_3']       = 'Server details are pre-filled below (smtp.office365.com / outlook.office365.com).';
$lang['mailbox_guide_zoho_1']          = 'Enable IMAP access: Zoho Mail → Settings → Mail Accounts → IMAP → Enable.';
$lang['mailbox_guide_zoho_2']          = 'If 2FA is on, generate an Application-Specific Password from Zoho Accounts → Security → App Passwords.';
$lang['mailbox_guide_zoho_3']          = 'Server details are pre-filled below.';
$lang['mailbox_guide_yahoo_1']         = 'Yahoo requires an App Password: Yahoo Account Security → Generate app password → select “Other app”.';
$lang['mailbox_guide_yahoo_2']         = 'Use that app password below — the normal account password will not work.';
$lang['mailbox_guide_titan_1']         = 'Titan mailboxes (sold via GoDaddy, Hostinger, Namecheap etc.) use the normal mailbox password — no app password needed.';
$lang['mailbox_guide_titan_2']         = 'Server details are pre-filled below.';
$lang['mailbox_guide_custom_1']        = 'Find the mail server settings in your hosting panel (cPanel → Email Accounts → Connect Devices, or ask your host).';
$lang['mailbox_guide_custom_2']        = 'Typical values: SMTP = mail.yourdomain.com port 465 (SSL), IMAP = mail.yourdomain.com port 993 (SSL). The username is usually the full email address.';
$lang['mailbox_guide_custom_3']        = 'If SSL fails on a shared host, try encryption “TLS” with SMTP port 587, and disable “Validate SSL certificate”.';

# Permissions
$lang['mailbox_perm_settings']         = 'Manage Accounts (superadmin only)';
$lang['mailbox_permission_manage']     = 'Manage shared labels, templates & rules';
$lang['mailbox_permission_analytics']  = 'View team analytics & audit trail';

# ── Corporate: navigation ──
$lang['mailbox_settings']              = 'Mailbox Settings';
$lang['mailbox_analytics']             = 'Mailbox Analytics';
$lang['mailbox_audit_trail']           = 'Mailbox Audit Trail';
$lang['mailbox_admin_tools']           = 'Administration';
$lang['mailbox_back_to_mailbox']       = 'Back to Mailbox';

# ── Shared inbox ──
$lang['mailbox_shared_inbox']          = 'Shared inbox';
$lang['mailbox_assign']                = 'Assign';
$lang['mailbox_assigned_to']           = 'Assigned to';
$lang['mailbox_unassigned']            = 'Unassigned';
$lang['mailbox_assign_to_me']          = 'Assign to me';
$lang['mailbox_my_queue']              = 'My queue';
$lang['mailbox_overdue']               = 'Overdue';
$lang['mailbox_assignee_not_allowed']  = 'That staff member has no access to this mailbox';
$lang['mailbox_status']                = 'Status';
$lang['mailbox_status_open']           = 'Open';
$lang['mailbox_status_pending']        = 'Pending';
$lang['mailbox_status_closed']         = 'Closed';
$lang['mailbox_invalid_status']        = 'Unknown conversation status';
$lang['mailbox_internal_notes']        = 'Internal notes';
$lang['mailbox_internal_note_hint']    = 'Only your team sees this — it is never sent to the sender.';
$lang['mailbox_add_note']              = 'Add note';
$lang['mailbox_note_empty']            = 'Write something first';
$lang['mailbox_note_delete_confirm']   = 'Delete this internal note?';
$lang['mailbox_mention_hint']          = 'Type @ to notify a colleague';
$lang['mailbox_no_notes']              = 'No internal notes on this conversation yet.';
$lang['mailbox_viewing_now']           = 'is viewing this conversation';
$lang['mailbox_replying_now']          = 'is replying right now';
$lang['mailbox_collision_warning']     = 'Careful — someone else is already here.';

# ── Labels ──
$lang['mailbox_labels']                = 'Labels';
$lang['mailbox_label']                 = 'Label';
$lang['mailbox_new_label']             = 'New label';
$lang['mailbox_label_name']            = 'Label name';
$lang['mailbox_label_color']           = 'Colour';
$lang['mailbox_label_scope']           = 'Applies to';
$lang['mailbox_label_all_accounts']    = 'All accounts';
$lang['mailbox_label_name_required']   = 'Give the label a name';
$lang['mailbox_label_exists']          = 'A label with that name already exists';
$lang['mailbox_label_delete_confirm']  = 'Delete this label? It will be removed from every message.';
$lang['mailbox_no_labels']             = 'No labels yet — create one to start organising.';

# ── Templates ──
$lang['mailbox_templates']             = 'Canned responses';
$lang['mailbox_template']              = 'Template';
$lang['mailbox_new_template']          = 'New template';
$lang['mailbox_template_name']         = 'Template name';
$lang['mailbox_template_body']         = 'Message';
$lang['mailbox_template_shared']       = 'Share with the whole team';
$lang['mailbox_template_private']      = 'Private';
$lang['mailbox_template_name_required'] = 'Give the template a name';
$lang['mailbox_template_delete_confirm'] = 'Delete this canned response?';
$lang['mailbox_insert_template']       = 'Insert canned response';
$lang['mailbox_no_templates']          = 'No canned responses yet.';
$lang['mailbox_placeholders']          = 'Placeholders';
$lang['mailbox_placeholders_hint']     = 'These are replaced when the template is inserted.';
$lang['mailbox_used_times']            = 'used';

# ── Automation rules ──
$lang['mailbox_rules']                 = 'Automation rules';
$lang['mailbox_rule']                  = 'Rule';
$lang['mailbox_new_rule']              = 'New rule';
$lang['mailbox_rule_name']             = 'Rule name';
$lang['mailbox_rule_name_required']    = 'Give the rule a name';
$lang['mailbox_rule_conditions_required'] = 'Add at least one condition';
$lang['mailbox_rule_account']          = 'Runs on';
$lang['mailbox_rule_match']            = 'Match';
$lang['mailbox_rule_match_all']        = 'ALL conditions';
$lang['mailbox_rule_match_any']        = 'ANY condition';
$lang['mailbox_rule_conditions']       = 'When an email arrives and…';
$lang['mailbox_rule_actions']          = 'Then do this';
$lang['mailbox_rule_add_condition']    = 'Add condition';
$lang['mailbox_rule_stop']             = 'Stop processing further rules';
$lang['mailbox_rule_test']             = 'Test against recent mail';
$lang['mailbox_rule_test_result']      = 'matched out of';
$lang['mailbox_rule_delete_confirm']   = 'Delete this rule?';
$lang['mailbox_rule_hits']             = 'times triggered';
$lang['mailbox_no_rules']              = 'No automation rules yet. Rules run the moment new mail arrives.';
$lang['mailbox_field_from_email']      = 'Sender email';
$lang['mailbox_field_from_name']       = 'Sender name';
$lang['mailbox_field_to']              = 'To';
$lang['mailbox_field_cc']              = 'Cc';
$lang['mailbox_field_subject']         = 'Subject';
$lang['mailbox_field_body']            = 'Message body';
$lang['mailbox_field_has_attachment']  = 'Has attachment';
$lang['mailbox_field_any_recipient']   = 'Any recipient';
$lang['mailbox_op_contains']           = 'contains';
$lang['mailbox_op_not_contains']       = 'does not contain';
$lang['mailbox_op_equals']             = 'is exactly';
$lang['mailbox_op_starts_with']        = 'starts with';
$lang['mailbox_op_ends_with']          = 'ends with';
$lang['mailbox_op_regex']              = 'matches regex';
$lang['mailbox_op_is_true']            = 'is yes';
$lang['mailbox_op_is_false']           = 'is no';
$lang['mailbox_action_label']          = 'Apply labels';
$lang['mailbox_action_assign']         = 'Assign to';
$lang['mailbox_action_status']         = 'Set status';
$lang['mailbox_action_mark_read']      = 'Mark as read';
$lang['mailbox_action_star']           = 'Star it';
$lang['mailbox_action_archive']        = 'Move to archive';
$lang['mailbox_action_trash']          = 'Move to trash';
$lang['mailbox_action_forward']        = 'Forward a copy to';
$lang['mailbox_action_notify']         = 'Notify staff member';

# ── SLA ──
$lang['mailbox_sla']                   = 'Response SLA';
$lang['mailbox_sla_enable']            = 'Track first-response time';
$lang['mailbox_sla_hours']             = 'Reply within (hours)';
$lang['mailbox_sla_hours_hint']        = 'Applies to every account unless the account overrides it.';
$lang['mailbox_sla_notify_admins']     = 'Notify administrators when an unassigned conversation breaches';
$lang['mailbox_sla_due']               = 'Reply due';
$lang['mailbox_sla_breached']          = 'SLA breached';
$lang['mailbox_sla_met']               = 'Answered in time';
$lang['mailbox_sla_compliance']        = 'SLA compliance';
$lang['mailbox_avg_response']          = 'Avg. first response';
$lang['mailbox_account_sla_override']  = 'SLA override (hours, 0 = use global)';

# ── Out of office ──
$lang['mailbox_out_of_office']         = 'Out of office';
$lang['mailbox_oo_enable']             = 'Auto-reply to incoming mail';
$lang['mailbox_oo_subject']            = 'Reply subject';
$lang['mailbox_oo_subject_hint']       = 'Use {subject} to include the original subject.';
$lang['mailbox_oo_body']               = 'Reply message';
$lang['mailbox_oo_from']               = 'From date';
$lang['mailbox_oo_to']                 = 'To date';
$lang['mailbox_oo_hint']               = 'Each sender gets at most one auto-reply per day, and automated mail is never answered.';
$lang['mailbox_default_assignee']      = 'Default owner for new mail';
$lang['mailbox_shared_inbox_enable']   = 'Treat this account as a shared team inbox';

# ── Sending: schedule & undo ──
$lang['mailbox_send_later']            = 'Send later';
$lang['mailbox_send_now']              = 'Send now';
$lang['mailbox_scheduled']             = 'Scheduled';
$lang['mailbox_folder_scheduled']      = 'Scheduled';
$lang['mailbox_scheduled_for']         = 'Goes out';
$lang['mailbox_schedule_pick']         = 'Pick a date and time';
$lang['mailbox_schedule_queued']       = 'Queued for delivery';
$lang['mailbox_cancel_schedule']       = 'Cancel & edit';
$lang['mailbox_undo']                  = 'Undo';
$lang['mailbox_sending_in']            = 'Sending in';
$lang['mailbox_send_undone']           = 'Send cancelled — the message is back in drafts';
$lang['mailbox_send_failed']           = 'Delivery failed';

# ── Compliance ──
$lang['mailbox_compliance']            = 'Compliance & governance';
$lang['mailbox_compliance_bcc']        = 'Archive copy of every outgoing email (Bcc)';
$lang['mailbox_compliance_bcc_hint']   = 'Recipients never see this address. Leave empty to disable.';
$lang['mailbox_dlp']                   = 'Outbound content screening (DLP)';
$lang['mailbox_dlp_enable']            = 'Screen outgoing mail before it is sent';
$lang['mailbox_dlp_mode']              = 'When something matches';
$lang['mailbox_dlp_mode_warn']         = 'Warn the sender, allow with confirmation';
$lang['mailbox_dlp_mode_block']        = 'Block the send outright';
$lang['mailbox_dlp_keywords']          = 'Blocked words or phrases';
$lang['mailbox_dlp_keywords_hint']     = 'One per line, or comma separated.';
$lang['mailbox_dlp_cards']             = 'Also detect card numbers (Luhn-checked)';
$lang['mailbox_dlp_blocked']           = 'This message was blocked by the outbound content policy.';
$lang['mailbox_dlp_warning']           = 'This message matched the outbound content policy';
$lang['mailbox_dlp_send_anyway']       = 'Send anyway';
$lang['mailbox_audit']                 = 'Audit trail';
$lang['mailbox_audit_enable']          = 'Record who reads, sends, deletes and exports';
$lang['mailbox_audit_retention']       = 'Keep audit records for (days)';
$lang['mailbox_retention']             = 'Message retention';
$lang['mailbox_retention_days']        = 'Delete messages older than (days, 0 = keep forever)';
$lang['mailbox_retention_folders']     = 'Apply to folders';
$lang['mailbox_retention_warning']     = 'Retention deletes mail permanently. Messages under legal hold are always kept.';
$lang['mailbox_legal_hold']            = 'Legal hold';
$lang['mailbox_legal_hold_on']         = 'Place on legal hold';
$lang['mailbox_legal_hold_off']        = 'Release legal hold';
$lang['mailbox_legal_hold_blocked']    = 'Some messages are under legal hold and were not touched';
$lang['mailbox_export_csv']            = 'Export CSV';

# ── Search ──
$lang['mailbox_search_help']           = 'Search operators';
$lang['mailbox_search_help_hint']      = 'Combine them freely, e.g. from:acme is:unread has:attachment';

# ── CRM links ──
$lang['mailbox_crm']                   = 'CRM';
$lang['mailbox_known_contact']         = 'Known contact';
$lang['mailbox_convert']               = 'Create record';
$lang['mailbox_convert_lead']          = 'Create lead';
$lang['mailbox_convert_ticket']        = 'Create ticket';
$lang['mailbox_converted_to']          = 'Linked to';
$lang['mailbox_already_converted']     = 'This email is already linked to a CRM record';
$lang['mailbox_invalid_convert_target'] = 'Unknown record type';
$lang['mailbox_convert_failed']        = 'Could not create the record';

# ── Keyboard shortcuts ──
$lang['mailbox_shortcuts']             = 'Keyboard shortcuts';
$lang['mailbox_shortcut_compose']      = 'Compose';
$lang['mailbox_shortcut_reply']        = 'Reply';
$lang['mailbox_shortcut_reply_all']    = 'Reply all';
$lang['mailbox_shortcut_forward']      = 'Forward';
$lang['mailbox_shortcut_archive']      = 'Archive';
$lang['mailbox_shortcut_trash']        = 'Move to trash';
$lang['mailbox_shortcut_star']         = 'Star / unstar';
$lang['mailbox_shortcut_unread']       = 'Mark unread';
$lang['mailbox_shortcut_search']       = 'Focus search';
$lang['mailbox_shortcut_next']         = 'Next message';
$lang['mailbox_shortcut_prev']         = 'Previous message';
$lang['mailbox_shortcut_assign']       = 'Assign';
$lang['mailbox_shortcut_note']         = 'Add internal note';
$lang['mailbox_shortcut_close']        = 'Close conversation';
$lang['mailbox_shortcut_send']         = 'Send (in composer)';
$lang['mailbox_shortcut_help']         = 'Show this help';

# ── Analytics ──
$lang['mailbox_period']                = 'Period';
$lang['mailbox_last_7']                = 'Last 7 days';
$lang['mailbox_last_30']               = 'Last 30 days';
$lang['mailbox_last_90']               = 'Last 90 days';
$lang['mailbox_this_month']            = 'This month';
$lang['mailbox_received']              = 'Received';
$lang['mailbox_sent']                  = 'Sent';
$lang['mailbox_answered']              = 'Answered';
$lang['mailbox_open_conversations']    = 'Open conversations';
$lang['mailbox_volume']                = 'Volume';
$lang['mailbox_by_account']            = 'By account';
$lang['mailbox_by_staff']              = 'By team member';
$lang['mailbox_top_senders']           = 'Top senders';
$lang['mailbox_busiest_hours']         = 'Busiest hours';
$lang['mailbox_closed_by']             = 'Closed';
$lang['mailbox_no_data']               = 'No data for this period';
$lang['mailbox_minutes_short']         = 'min';
$lang['mailbox_hours_short']           = 'h';

# ── Audit screen ──
$lang['mailbox_audit_action']          = 'Action';
$lang['mailbox_audit_who']             = 'Who';
$lang['mailbox_audit_when']            = 'When';
$lang['mailbox_audit_details']         = 'Details';
$lang['mailbox_audit_ip']              = 'IP address';
$lang['mailbox_audit_system']          = 'System';
$lang['mailbox_audit_empty']           = 'No audit records match these filters.';
$lang['mailbox_audit_disabled']        = 'The audit trail is currently switched off in Mailbox Settings.';
$lang['mailbox_filter_all']            = 'All';
$lang['mailbox_date_from']             = 'From';
$lang['mailbox_date_to']               = 'To';
$lang['mailbox_apply_filters']         = 'Apply';
$lang['mailbox_reset']                 = 'Reset';

# ── Settings screen misc ──
$lang['mailbox_general']               = 'General';
$lang['mailbox_saved']                 = 'Saved';
$lang['mailbox_save']                  = 'Save changes';
$lang['mailbox_cancel']                = 'Cancel';
$lang['mailbox_delete']                = 'Delete';
$lang['mailbox_edit']                  = 'Edit';
$lang['mailbox_enabled']               = 'Enabled';
$lang['mailbox_disabled']              = 'Disabled';
$lang['mailbox_undo_seconds']          = 'Undo-send window (seconds, 0 = send immediately)';
$lang['mailbox_undo_seconds_hint']     = 'Outgoing mail is held for this long so it can be recalled.';
$lang['mailbox_schedule_enable']       = 'Allow scheduling mail for later';
$lang['mailbox_presence_enable']       = 'Show when a colleague is on the same conversation';
$lang['mailbox_status_enable']         = 'Track conversation status (open / pending / closed)';
$lang['mailbox_rules_enable']          = 'Run automation rules on incoming mail';
$lang['mailbox_autoreply_enable']      = 'Allow out-of-office auto-replies';
$lang['mailbox_account_policies']      = 'Per-account policies';
$lang['mailbox_sync_settings']         = 'Sync';
$lang['mailbox_sync_interval']         = 'Minimum seconds between syncs per account';
$lang['mailbox_initial_days']          = 'On first connect, import the last (days)';
$lang['mailbox_sync_batch']            = 'Max messages imported per account per run';

# ── Notifications ──
$lang['mailbox_notification_assigned']    = 'A mailbox conversation was assigned to you: %s';
$lang['mailbox_notification_mentioned']   = 'You were mentioned in a mailbox note: %s';
$lang['mailbox_notification_sla_breached'] = 'Mailbox SLA breached: %s (%s)';
$lang['mailbox_notification_rule_match']  = 'Mailbox rule matched: %s (%s)';
