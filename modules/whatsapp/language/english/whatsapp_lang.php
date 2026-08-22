<?php

defined('BASEPATH') or exit('No direct script access allowed');

# WhatsApp (Official Cloud API) Module Language File

$lang['wapi_whatsapp']                 = 'WhatsApp';
$lang['wapi_subtitle']                 = 'Official WhatsApp Cloud API — templates, bulk campaigns, two-way inbox and auto-reply bot';

# Permissions (one per dashboard tab)
$lang['wapi_perm_view']                = 'Overview';
$lang['wapi_perm_inbox']               = 'Chat Inbox';
$lang['wapi_perm_send']                = 'Send Messages';
$lang['wapi_perm_bulk']                = 'Bulk Campaigns';
$lang['wapi_perm_templates']           = 'Templates';
$lang['wapi_perm_bot']                 = 'Auto-Reply Bot';
$lang['wapi_perm_profile']             = 'Business Profile';
$lang['wapi_perm_contacts']            = 'Contacts';
$lang['wapi_perm_settings']            = 'Settings & Connection';

$lang['wapi_perm_view_help']           = 'Overview tab: stats, connected numbers, health diagnostics and the activity chart.';
$lang['wapi_perm_inbox_help']          = 'Inbox tab: read the two-way chat threads and message history. Replying also needs "Send Messages".';
$lang['wapi_perm_send_help']           = 'Send tab: send a single text or template message, and reply from the inbox.';
$lang['wapi_perm_bulk_help']           = 'Bulk Campaigns tab: create, start, pause and delete broadcast campaigns.';
$lang['wapi_perm_templates_help']      = 'Templates tab: sync from Meta, submit new templates and delete existing ones.';
$lang['wapi_perm_bot_help']            = 'Bot tab: auto-reply rules and business-hours settings.';
$lang['wapi_perm_profile_help']        = 'Profile tab: public business profile, picture and display-name requests.';
$lang['wapi_perm_contacts_help']       = 'Contacts tab: contact list and opt-out toggles.';
$lang['wapi_perm_settings_help']       = 'Settings tab plus connection control: connect/disconnect Facebook, register numbers, set the default number and (on master) the provider console.';

# Access
$lang['wapi_no_tab_access']            = 'You do not have permission to view any WhatsApp section. Ask an administrator to grant WhatsApp permissions to your role.';

# Tabs
$lang['wapi_tab_overview']             = 'Overview';
$lang['wapi_tab_inbox']                = 'Inbox';
$lang['wapi_tab_send']                 = 'Send';
$lang['wapi_tab_bulk']                 = 'Bulk Campaigns';
$lang['wapi_tab_templates']            = 'Templates';
$lang['wapi_tab_bot']                  = 'Bot';
$lang['wapi_tab_profile']              = 'Profile';
$lang['wapi_tab_contacts']             = 'Contacts';

# Business profile / branding
$lang['wapi_profile_title']            = 'Business Profile';
$lang['wapi_profile_lead']             = 'This is what customers see when they tap your business name in WhatsApp. Changes go live on Meta immediately — only the display name needs review.';
$lang['wapi_reload_profile']           = 'Reload from Meta';
$lang['wapi_profile_picture']          = 'Profile Picture';
$lang['wapi_profile_picture_hint']     = 'Square JPG or PNG, at least 192 × 192 px, under 5 MB. WhatsApp crops it to a circle.';
$lang['wapi_profile_about']            = 'About';
$lang['wapi_profile_about_ph']         = 'Open 9 AM – 6 PM · Book on WhatsApp';
$lang['wapi_profile_about_hint']       = 'The short line shown directly under your business name.';
$lang['wapi_profile_description']      = 'Description';
$lang['wapi_profile_description_ph']   = 'Tell customers what your business does and how you can help them.';
$lang['wapi_profile_category']         = 'Business Category';
$lang['wapi_profile_email']            = 'Contact Email';
$lang['wapi_profile_address']          = 'Address';
$lang['wapi_profile_address_ph']       = 'Street, city, state, postcode';
$lang['wapi_profile_website_1']        = 'Website 1';
$lang['wapi_profile_website_2']        = 'Website 2';
$lang['wapi_save_profile']             = 'Save Profile';
$lang['wapi_profile_saved']            = 'Business profile updated on WhatsApp.';
$lang['wapi_profile_saved_with_picture'] = 'Business profile and picture updated on WhatsApp.';

# Display name
$lang['wapi_display_name']             = 'Display Name';
$lang['wapi_display_name_lead']        = 'The name customers see at the top of the chat. Changing it requires Meta approval; your current name stays live until the new one is approved.';
$lang['wapi_display_name_ph']          = 'Your business name';
$lang['wapi_submit_for_review']        = 'Submit for review';
$lang['wapi_display_name_submitted']   = 'Display name submitted to Meta for review.';
$lang['wapi_display_name_policy']      = 'The name must genuinely relate to your business — Meta rejects generic terms, URLs, phone numbers, and names that do not match your website or branding. Review usually takes a couple of days.';
$lang['wapi_tab_settings']             = 'Settings';

# Connection
$lang['wapi_connect_facebook']         = 'Connect with Facebook';
$lang['wapi_connected']                = 'Connected';
$lang['wapi_not_connected']            = 'WhatsApp is not connected. Connect your account first.';
$lang['wapi_disconnect']               = 'Disconnect';
$lang['wapi_disconnected']             = 'WhatsApp account disconnected.';
$lang['wapi_refresh_numbers']          = 'Refresh Numbers';
$lang['wapi_numbers_refreshed']        = '%d phone number(s) refreshed.';
$lang['wapi_default_number_set']       = 'Default sending number updated.';
$lang['wapi_provider_not_ready']       = 'The WhatsApp service is not configured yet. Please contact your provider.';
$lang['wapi_business_account']         = 'WhatsApp Business Account';
$lang['wapi_phone_numbers']            = 'Phone Numbers';
$lang['wapi_quality']                  = 'Quality';
$lang['wapi_default']                  = 'Default';
$lang['wapi_make_default']             = 'Make default';
$lang['wapi_actions']                  = 'Actions';
$lang['wapi_details']                  = 'Details';
$lang['wapi_webhook']                  = 'Webhook';

# Number status / health
$lang['wapi_number_status']            = 'Status';
$lang['wapi_can_send']                 = 'Can Send';
$lang['wapi_messaging_limit']          = 'Messaging Limit';
$lang['wapi_last_checked']             = 'Last Checked';
$lang['wapi_check_status']             = 'Check Status';
$lang['wapi_health_checked']           = 'Checked %d number(s) — %d active on the Cloud API.';
$lang['wapi_number_details']           = 'Phone Number Details';
$lang['wapi_health_report']            = 'Meta health report';
$lang['wapi_last_error']               = 'Last error from Meta';
$lang['wapi_live_from_meta']           = 'Live state read from Meta just now';
$lang['wapi_live_read_failed']         = 'Could not read the live state from Meta';

# Registration (error 133010)
$lang['wapi_register_number']          = 'Register Number';
$lang['wapi_number_registered']        = 'Number registered — it can send messages now.';
$lang['wapi_number_register_pending']  = 'Registration submitted. Meta reports the number as %s — re-check in a minute.';
$lang['wapi_number_deregistered']      = 'Number deregistered.';
$lang['wapi_not_registered_title']     = 'This number is not registered on the WhatsApp Cloud API';
$lang['wapi_not_registered_hint']      = 'The number is attached to your WhatsApp Business Account but was never registered for Cloud API messaging, so every send fails with "(#133010) Account not registered". Register it with a 6-digit PIN to start sending.';
$lang['wapi_register_lead']            = 'Register this number for Cloud API messaging:';
$lang['wapi_register_pin']             = '6-digit PIN';
$lang['wapi_register_pin_hint']        = 'Choose any 6 digits. If two-step verification is already switched on for this number, enter that existing PIN instead.';
$lang['wapi_register_note']            = 'This PIN becomes the number\'s two-step verification PIN — keep it safe, you will need it whenever the number is registered again.';

# Diagnostics
$lang['wapi_diagnostics']              = 'Health & Diagnostics';
$lang['wapi_run_checks']               = 'Run checks';
$lang['wapi_running_checks']           = 'Running checks…';
$lang['wapi_activity_title']           = 'Message Activity';
$lang['wapi_range_7']                  = 'Last 7 days';
$lang['wapi_range_14']                 = 'Last 14 days';
$lang['wapi_range_30']                 = 'Last 30 days';
$lang['wapi_range_90']                 = 'Last 90 days';
$lang['wapi_legend_in']                = 'Received';
$lang['wapi_legend_out']               = 'Sent';
$lang['wapi_legend_failed']            = 'Failed';

# Master console
$lang['wapi_master_console']           = 'Provider Console';
$lang['wapi_app_credentials']          = 'Central Meta App Credentials';
$lang['wapi_app_id']                   = 'App ID';
$lang['wapi_app_secret']               = 'App Secret';
$lang['wapi_config_id']                = 'Embedded Signup Config ID (optional)';
$lang['wapi_verify_token']             = 'Webhook Verify Token';
$lang['wapi_webhook_url']              = 'Webhook URL';
$lang['wapi_oauth_redirect']           = 'OAuth Redirect URI';
$lang['wapi_credentials_saved']        = 'Credentials saved.';
$lang['wapi_resync_btn']               = 'Refresh & sync system';
$lang['wapi_resync_last']              = 'Last synced';
$lang['wapi_resync_hint']              = 'Saving stores the credentials — it does not move anything onto the new Meta App. Run this after any change: it re-captures the callback URLs from this server, verifies the App ID and Secret with Meta, re-registers the webhook on the new app, and checks every connected account\'s stored token, flagging the ones that must connect again.';
$lang['wapi_resync_done']              = 'Sync complete — %d connection(s) on the new app, %d need to reconnect.';
$lang['wapi_resync_aborted']           = 'Sync stopped: the stored credentials could not be verified with Meta.';
$lang['wapi_reconnect_required']       = 'Reconnect required';
$lang['wapi_stale_connection_title']   = 'Reconnect WhatsApp — the connection is no longer valid';
$lang['wapi_stale_connection_hint']    = 'The WhatsApp app this system connects through has been changed. Access tokens cannot be carried across, so the one stored for this account belongs to the previous app: nothing can be sent or received until you connect again. The details shown below are the last known state and will refresh once you reconnect.';
$lang['wapi_reconnect_now']            = 'Reconnect WhatsApp';
$lang['wapi_connected_tenants']        = 'Connected Tenants';
$lang['wapi_usage_messages']           = 'Messages';
$lang['wapi_usage_cost']               = 'Cost';
$lang['wapi_usage_total']              = 'Total across all tenants';
$lang['wapi_refresh_usage']            = 'Refresh usage';
$lang['wapi_usage_synced']             = 'Usage refreshed for %d tenant(s) over the last %d days.';
$lang['wapi_usage_7']                  = 'Last 7 days';
$lang['wapi_usage_30']                 = 'Last 30 days';
$lang['wapi_usage_90']                 = 'Last 90 days';
$lang['wapi_usage_note']               = 'Figures come from Meta\'s own billing analytics for each WhatsApp Business Account, in that account\'s billing currency. Treat them as an indication rather than an invoice — Meta\'s statement is the final word. Refresh pulls fresh numbers; the table shows what was last cached.';
$lang['wapi_no_tenants']               = 'No tenants have connected yet.';
$lang['wapi_setup_guide']              = 'Setup Guide';

# Webhook health
$lang['wapi_webhook_health']           = 'Webhook Health';
$lang['wapi_webhook_health_lead']      = 'Incoming messages, delivery receipts and template approvals all arrive over one webhook. These checks read what Meta actually has registered and probe this server the same way Meta verifies it.';
$lang['wapi_webhook_test']             = 'Re-run checks';
$lang['wapi_webhook_fix']              = 'Register webhook';
$lang['wapi_webhook_subscribed']       = 'Webhook registered with Meta — incoming messages will now be delivered here.';
$lang['wapi_webhook_selftest_failed']  = 'Meta will reject this URL — fix the reachability problem first.';
$lang['wapi_webhook_guide']            = 'How to fix each webhook problem';

# Provider billing — shared credit line (tenants never add a card)
$lang['wapi_billing_title']            = 'Messaging Billing (Shared Credit Line)';
$lang['wapi_billing_lead']             = 'Attach every tenant\'s WhatsApp Business Account to your own Meta credit line, so Meta invoices you and your customers are never asked for a credit card during signup — the model Wati, AiSensy and other BSPs use. You then bill them through your own plans.';
$lang['wapi_business_id']              = 'Your Meta Business (Portfolio) ID';
$lang['wapi_system_user_token']        = 'System User Access Token';
$lang['wapi_token_stored']             = '•••••••• stored — leave blank to keep';
$lang['wapi_credit_currency']          = 'Currency';
$lang['wapi_credit_line']              = 'Extended Credit Line';
$lang['wapi_credit_line_none']         = '— no credit line selected —';
$lang['wapi_load_credit_lines']        = 'Load from Meta';
$lang['wapi_share_credit_line']        = 'Bill tenant messaging to our credit line';
$lang['wapi_billing_saved']            = 'Billing settings saved.';
$lang['wapi_credit_shared']            = 'Credit line attached — Meta now bills you for this tenant.';
$lang['wapi_share_credit_now']         = 'Attach to our credit line';
$lang['wapi_billing_col']              = 'Billing';
$lang['wapi_billing_ready']            = 'Ready — new tenants are attached to your credit line automatically.';
$lang['wapi_billing_not_ready']        = 'Not active — tenants are still asked for their own payment method.';
$lang['wapi_billing_requirements']     = 'Requires a Meta-verified business, approval as a Meta Tech Provider / Solution Partner, and an extended credit line issued to your business. The system user token must belong to your business and hold business_management and whatsapp_business_management.';
$lang['wapi_billing_guide']            = 'Setup guide — how to connect your credit line (8 steps)';
$lang['wapi_guide_billing_pointer']    = 'To pay for your tenants\' messaging yourself so they are never asked for a credit card, follow the billing setup guide under "Messaging Billing" below.';
$lang['wapi_billing_provider']         = 'Billing by provider';
$lang['wapi_billing_provider_hint']    = 'Messaging is charged to your provider — no payment method needed on this account.';

# Send
$lang['wapi_recipient']                = 'Recipient Number';
$lang['wapi_message']                  = 'Message';
$lang['wapi_send']                     = 'Send';
$lang['wapi_send_message']             = 'Send Message';
$lang['wapi_free_text']                = 'Free Text (24h window)';
$lang['wapi_template_message']         = 'Template Message';
$lang['wapi_template']                 = 'Template';
$lang['wapi_template_params']          = 'Variable values (comma separated)';
$lang['wapi_attach_file']              = 'Attach a photo, video, audio or document';
$lang['wapi_attach_caption']           = 'Add a caption (optional)…';
$lang['wapi_attach_cancel']            = 'Remove attachment';
$lang['wapi_attach_no_file']           = 'Choose a file to send first.';
$lang['wapi_use_template']             = 'Send a template message';
$lang['wapi_header_media_url']         = 'Header media URL (public https:// link)';
$lang['wapi_template_media_required']  = 'This template has a media header — provide a public URL for the header image/video/document.';
$lang['wapi_message_sent']             = 'Message sent.';
$lang['wapi_message_required']         = 'Please type a message.';
$lang['wapi_invalid_number']           = 'Invalid phone number.';
$lang['wapi_window_closed']            = 'The 24-hour session window is closed for this contact — send an approved template instead.';
$lang['wapi_window_open']              = 'Session window open';
$lang['wapi_window_closed_badge']      = 'Window closed';

# Inbox
$lang['wapi_no_conversations']         = 'No conversations yet. Incoming WhatsApp messages will appear here.';
$lang['wapi_select_conversation']      = 'Select a conversation to start chatting';
$lang['wapi_type_message']             = 'Type a message…';
$lang['wapi_media_attachment']         = 'Media attachment';
$lang['wapi_download']                 = 'Download';
$lang['wapi_bot_reply']                = 'Bot';
$lang['wapi_search_chats']             = 'Search name or number…';
$lang['wapi_scope_conversations']      = 'Conversations';
$lang['wapi_scope_all']                = 'Include campaigns';
$lang['wapi_scope_hint']               = 'Campaign blasts are hidden by default so they do not bury real conversations.';
$lang['wapi_refresh']                  = 'Refresh';
$lang['wapi_window_never_hint']        = 'This contact has never messaged you. WhatsApp only allows free text for 24 hours after the customer writes to you first — send an approved template to open the conversation.';
$lang['wapi_window_expired_hint']      = 'This contact last messaged you %s, so the 24-hour free-text window has expired. Send an approved template to reopen it.';
$lang['wapi_window_no_webhook_hint']   = 'Note: no webhook has ever been received from Meta, so incoming messages are not reaching this system at all — run the diagnostics on the Overview tab.';

# Templates
$lang['wapi_sync_templates']           = 'Sync Templates';
$lang['wapi_templates_synced']         = '%d template(s) synced from Meta.';
$lang['wapi_new_template']             = 'New Template';
$lang['wapi_template_name']            = 'Template Name';
$lang['wapi_template_language']        = 'Language';
$lang['wapi_template_category']        = 'Category';
$lang['wapi_template_header']          = 'Header Text (optional)';
$lang['wapi_template_body']            = 'Body';
$lang['wapi_template_footer']          = 'Footer Text (optional)';
$lang['wapi_template_body_hint']       = 'Use {{1}}, {{2}}… for variables. Example: Hello {{1}}, your appointment is on {{2}}.';
$lang['wapi_template_submitted']       = 'Template submitted to Meta for approval.';
$lang['wapi_template_deleted']         = 'Template deleted.';
$lang['wapi_no_templates']             = 'No templates yet. Sync from Meta or create a new one.';
$lang['wapi_variables']                = 'Variables';
$lang['wapi_template_view']            = 'View template';
$lang['wapi_edit_template']            = 'Edit template';
$lang['wapi_template_fix_resubmit']    = 'Fix &amp; resubmit to Meta';
$lang['wapi_template_updated']         = 'Template updated and sent to Meta for review.';
$lang['wapi_template_media_header_kept'] = 'This template has a media header. It is kept exactly as approved — each send supplies its own media URL.';

# Template composer (live preview)
$lang['wapi_template_name_hint']       = 'Lowercase letters, numbers and underscores only.';
$lang['wapi_template_header_ph']       = 'e.g. Appointment confirmed';
$lang['wapi_template_footer_ph']       = 'e.g. Reply STOP to opt out';
$lang['wapi_template_samples']         = 'Sample values';
$lang['wapi_template_samples_hint']    = 'Meta reviews the template against these. Realistic values get approved faster.';
$lang['wapi_add_variable']             = 'Variable';
$lang['wapi_format_bold']              = 'Bold  *text*';
$lang['wapi_format_italic']            = 'Italic  _text_';
$lang['wapi_format_strike']            = 'Strikethrough  ~text~';
$lang['wapi_format_mono']              = 'Monospace  ```text```';
$lang['wapi_preview_business']         = 'Business account';
$lang['wapi_preview_with_samples']     = 'Show sample values';

# Campaigns
$lang['wapi_new_campaign']             = 'New Campaign';
$lang['wapi_campaign_name']            = 'Campaign Name';
$lang['wapi_campaign_recipients']      = 'Recipients';
$lang['wapi_campaign_source']          = 'Recipient Source';
$lang['wapi_source_manual']            = 'Paste numbers manually';
$lang['wapi_source_leads']             = 'All Leads';
$lang['wapi_source_patients']          = 'All Patients';
$lang['wapi_source_contacts']          = 'All Customer Contacts';
$lang['wapi_manual_numbers_hint']      = 'One per line: phone or phone,name';
$lang['wapi_schedule_optional']        = 'Schedule (optional)';
$lang['wapi_batch_size']               = 'Messages per cron tick';
$lang['wapi_campaign_created']         = 'Campaign created with %d recipient(s).';
$lang['wapi_campaign_deleted']         = 'Campaign deleted.';
$lang['wapi_campaign_stop_first']      = 'Cancel the campaign before deleting it.';
$lang['wapi_no_campaigns']             = 'No campaigns yet.';
$lang['wapi_progress']                 = 'Progress';
$lang['wapi_preview_count']            = 'Preview count';
$lang['wapi_recipients_found']         = '%d recipient(s) will receive this campaign.';
$lang['wapi_queue_processed']          = 'Queue processed.';
$lang['wapi_process_queue']            = 'Process queue now';
$lang['wapi_header_media_url']         = 'Header media URL (only for media-header templates)';
$lang['wapi_param_static']             = 'Static text';
$lang['wapi_param_merge_name']         = 'Recipient name';
$lang['wapi_param_merge_phone']        = 'Recipient phone';

# Bot
$lang['wapi_bot_rules']                = 'Auto-Reply Rules';
$lang['wapi_new_rule']                 = 'New Rule';
$lang['wapi_rule_name']                = 'Rule Name';
$lang['wapi_rule_trigger']             = 'Trigger';
$lang['wapi_trigger_welcome']          = 'Welcome (first message)';
$lang['wapi_trigger_keyword']          = 'Keyword';
$lang['wapi_trigger_away']             = 'Away (outside business hours)';
$lang['wapi_trigger_default']          = 'Default (fallback)';
$lang['wapi_match_type']               = 'Match Type';
$lang['wapi_match_contains']           = 'Contains';
$lang['wapi_match_exact']              = 'Exact';
$lang['wapi_match_starts_with']        = 'Starts with';
$lang['wapi_match_any']                = 'Any message';
$lang['wapi_keywords']                 = 'Keywords (comma separated)';
$lang['wapi_response']                 = 'Response';
$lang['wapi_rule_priority']            = 'Priority';
$lang['wapi_rule_hits']                = 'Hits';
$lang['wapi_rule_enabled']             = 'Enabled';
$lang['wapi_rule_saved']               = 'Rule saved.';
$lang['wapi_rule_deleted']             = 'Rule deleted.';
$lang['wapi_no_rules']                 = 'No bot rules yet.';
$lang['wapi_bot_enabled']              = 'Enable auto-reply bot';
$lang['wapi_business_hours']           = 'Business Hours';
$lang['wapi_business_hours_only']      = 'Away rule fires outside these hours';
$lang['wapi_open_time']                = 'Open';
$lang['wapi_close_time']               = 'Close';
$lang['wapi_days']                     = 'Days';
$lang['wapi_bot_settings_saved']       = 'Bot settings saved.';

# Contacts
$lang['wapi_contact']                  = 'Contact';
$lang['wapi_opted_out']                = 'Opted Out';
$lang['wapi_no_contacts']              = 'No contacts yet.';
$lang['wapi_last_incoming']            = 'Last Incoming';
$lang['wapi_last_outgoing']            = 'Last Outgoing';

# Settings
$lang['wapi_default_country_code']     = 'Default Country Code';
$lang['wapi_settings_saved']           = 'Settings saved.';

# Inbox notifications
$lang['wapi_notify_settings']          = 'Inbox Notifications';
$lang['wapi_notify_settings_hint']     = 'Alert your team the moment a customer replies on WhatsApp — anywhere in the CRM, not only on this page.';
$lang['wapi_notify_enabled']           = 'Notify on new messages';
$lang['wapi_notify_toast']             = 'On-screen toast';
$lang['wapi_notify_desktop']           = 'Desktop notification';
$lang['wapi_notify_sound']             = 'Sound alert';
$lang['wapi_notify_bell']              = 'Notification bell entry';
$lang['wapi_notify_recipients']        = 'Notify';
$lang['wapi_notify_recipients_all']    = 'Everyone with Inbox access';
$lang['wapi_notify_recipients_selected'] = 'Selected staff only';
$lang['wapi_notify_staff']             = 'Staff Members';
$lang['wapi_notify_no_staff']          = 'No staff member holds the Inbox permission yet.';
$lang['wapi_notify_throttle']          = 'Bell throttle (seconds)';
$lang['wapi_notify_throttle_hint']     = 'Minimum gap between bell entries for the same contact. Toasts and sounds are never throttled.';
$lang['wapi_notify_test']              = 'Test notification';
$lang['wapi_notify_title']             = 'WhatsApp Inbox';
$lang['wapi_notify_new_message_short'] = 'New WhatsApp message';
$lang['wapi_notify_new_message']       = 'New WhatsApp message from %s: %s';
$lang['wapi_notify_open_chat']         = 'Open chat';
$lang['wapi_notify_more']              = '%s more unread conversations';
$lang['wapi_notify_test_from']         = 'Test contact';
$lang['wapi_notify_test_body']         = 'This is how a new WhatsApp message will look.';
$lang['wapi_notify_media_image']       = 'Photo';
$lang['wapi_notify_media_video']       = 'Video';
$lang['wapi_notify_media_audio']       = 'Voice message';
$lang['wapi_notify_media_document']    = 'Document';
$lang['wapi_notify_media_sticker']     = 'Sticker';
$lang['wapi_notify_media_contact']     = 'Contact card';
$lang['wapi_notify_media_location']    = 'Location';
$lang['wapi_notify_media_generic']     = 'Sent an attachment';

# Stats
$lang['wapi_stat_messages_today']      = 'Messages Today';
$lang['wapi_stat_total_messages']      = 'Total Messages';
$lang['wapi_stat_unread']              = 'Unread';
$lang['wapi_stat_templates']           = 'Approved Templates';
$lang['wapi_stat_campaigns']           = 'Campaigns';
$lang['wapi_stat_numbers']             = 'Numbers';
$lang['wapi_stat_failed']              = 'Failed Sends';
$lang['wapi_stat_active_numbers']      = 'Active Numbers';

# 24-hour conversation credits (Omni Messaging balance)
$lang['wapi_credits_exhausted_title']  = 'WhatsApp credits exhausted';
$lang['wapi_credits_exhausted_hint']   = 'A new 24-hour conversation costs one credit. Top up under Omni Messaging → Recharge, or reply inside an open customer window (free).';
$lang['wapi_conversations']            = 'Conversations';
$lang['wapi_conversation_open']        = 'Open now';
$lang['wapi_conversation_free']        = 'Free (inside open window)';

# ─────────── Shared WhatsApp number ("our number", provider-lent) ───────────
# Provider console (master)
$lang['wapi_shared_console']            = 'Shared WhatsApp Number';
$lang['wapi_shared_console_lead']       = 'Let tenants send WhatsApp messages and bulk campaigns on YOUR connected number, without ever handing over a token, an app secret or the WhatsApp Business Account. Each tenant is restricted to the approved templates you pick, capped by a message limit, and billed either free or against their WhatsApp credits.';
$lang['wapi_shared_master_switch']      = 'Lend our WhatsApp number to tenants';
$lang['wapi_shared_brand_label']        = 'Name tenants see';
$lang['wapi_shared_brand_hint']         = 'Shown as “Sending on <name>’s WhatsApp”. Defaults to your company name.';
$lang['wapi_shared_default_number_label'] = 'Default number';
$lang['wapi_shared_default_number_hint']  = 'Used by any grant that does not pin a specific number.';
$lang['wapi_shared_use_default']        = 'Our default number';
$lang['wapi_shared_default_number']     = 'Default number';
$lang['wapi_shared_add_tenant']         = 'Give a tenant access';
$lang['wapi_shared_suspended_title']    = 'Sharing is switched off';
$lang['wapi_shared_suspended_body']     = 'Every grant below is suspended while this is off — nothing is deleted, and turning it back on restores them all.';
$lang['wapi_shared_no_number_title']    = 'No number to share yet';
$lang['wapi_shared_no_number_body']     = 'Connect a WhatsApp Business Account on this (provider) account first — the number you lend to tenants is your own.';
$lang['wapi_shared_none_yet']           = 'No tenant is using our WhatsApp number yet.';

$lang['wapi_shared_kpi_live']           = 'Tenants live';
$lang['wapi_shared_kpi_free']           = 'Free messages this month';
$lang['wapi_shared_kpi_billed']         = 'Billed messages this month';
$lang['wapi_shared_kpi_available']      = 'Tenants without access';

$lang['wapi_shared_col_tenant']         = 'Tenant';
$lang['wapi_shared_col_status']         = 'Access';
$lang['wapi_shared_col_billing']        = 'Billing';
$lang['wapi_shared_col_number']         = 'Sends from';
$lang['wapi_shared_col_templates']      = 'Templates';
$lang['wapi_shared_col_allowed']        = 'Allowed for';
$lang['wapi_shared_col_usage']          = 'This month';
$lang['wapi_shared_on']                 = 'On';
$lang['wapi_shared_off']                = 'Suspended';
$lang['wapi_shared_usage_today']        = '%s today';
$lang['wapi_shared_usage_credits']      = '%s credits';
$lang['wapi_shared_configure']          = 'Configure';
$lang['wapi_shared_enable']             = 'Enable';
$lang['wapi_shared_disable']            = 'Suspend';
$lang['wapi_shared_remove']             = 'Remove access';

# Grant modal
$lang['wapi_shared_grant_title']        = 'Shared number access';
$lang['wapi_shared_tenant']             = 'Tenant';
$lang['wapi_shared_pick_tenant']        = 'Choose a tenant…';
$lang['wapi_shared_number_label']       = 'Send from';
$lang['wapi_shared_number_hint']        = 'Must be one of our own numbers. Leave on the default unless this tenant needs a specific sender.';
$lang['wapi_shared_billing']            = 'How this tenant is charged';
$lang['wapi_shared_billing_free']       = 'Free';
$lang['wapi_shared_billing_credits']    = 'Credits';
$lang['wapi_shared_billing_hint']       = 'Free: we absorb the cost, bounded by the limits below. Credits: one WhatsApp credit per 24-hour conversation is taken from their CCX Msgs balance, exactly as for a tenant on their own number.';
$lang['wapi_shared_daily_limit']        = 'Messages per day';
$lang['wapi_shared_monthly_limit']      = 'Messages per month';
$lang['wapi_shared_limit_hint']         = '0 = no limit.';
$lang['wapi_shared_traffic']            = 'Allowed for';
$lang['wapi_shared_traffic_send']       = 'Single sends';
$lang['wapi_shared_traffic_bulk']       = 'Bulk campaigns';
$lang['wapi_shared_traffic_hooks']      = 'Automated messages';
$lang['wapi_shared_traffic_none']       = 'Nothing';
$lang['wapi_shared_templates_label']    = 'Templates this tenant may send';
$lang['wapi_shared_tpl_selected']       = 'Only the ones I pick';
$lang['wapi_shared_tpl_all']            = 'All approved templates';
$lang['wapi_shared_tpl_hint']           = 'Only APPROVED templates on our WhatsApp Business Account can be shared. A tenant can never send anything else — free text is blocked on the shared number.';
$lang['wapi_shared_tpl_none_hint']      = 'No template selected — this tenant cannot send anything yet.';
$lang['wapi_shared_no_templates']       = 'No approved templates on our account yet.';
$lang['wapi_shared_select_all']         = 'Select all';
$lang['wapi_shared_select_none']        = 'Clear';
$lang['wapi_shared_notes']              = 'Internal note';
$lang['wapi_shared_notes_ph']           = 'e.g. included in the Growth plan';
$lang['wapi_shared_grant_enabled']      = 'Access enabled';
$lang['wapi_shared_grant_saved']        = 'Shared number access saved for %s.';
$lang['wapi_shared_grant_updated']      = 'Access updated.';
$lang['wapi_shared_grant_removed']      = 'Access removed.';
$lang['wapi_shared_no_grant']           = 'That tenant has no shared-number access.';
$lang['wapi_shared_bad_number']         = 'Pick one of our own WhatsApp numbers.';
$lang['wapi_shared_not_self']           = 'This account owns the number — it cannot be granted to itself.';
$lang['wapi_shared_settings_saved']     = 'Shared number settings saved.';

# Tenant side
$lang['wapi_shared_chip']               = 'On %s’s WhatsApp';
$lang['wapi_shared_chip_hint']          = 'Messages go out from the provider’s WhatsApp number using approved templates.';
$lang['wapi_shared_panel_title']        = 'You are sending on %s’s WhatsApp number';
$lang['wapi_shared_panel_lead']         = 'No setup, no Meta verification and no payment method needed — %s has shared its verified WhatsApp Business number with this account.';
$lang['wapi_shared_fact_number']        = 'Sends from';
$lang['wapi_shared_fact_templates']     = 'Templates available';
$lang['wapi_shared_fact_today']         = 'Messages today';
$lang['wapi_shared_fact_month']         = 'Messages this month';
$lang['wapi_shared_rules_note']         = 'Only the approved templates %s has shared can be sent, and replies from customers arrive in their inbox. Connect your own WhatsApp Business Account any time to get your own sender, free-form chat and a full inbox.';
$lang['wapi_shared_upgrade_own']        = 'Connect our own number instead';
$lang['wapi_shared_templates_lead']     = 'These templates are provided by %s and are read-only here — they belong to their WhatsApp Business Account.';
$lang['wapi_shared_no_templates_yet']   = 'No templates have been shared with this account yet.';
$lang['wapi_shared_tpl_owned']          = 'Provided by %s';
$lang['wapi_shared_refresh_templates']  = 'Refresh shared templates';
$lang['wapi_shared_templates_synced']   = '%s shared templates available.';
$lang['wapi_shared_inbox_note']         = 'Customer replies arrive in %s’s inbox, so there is nothing to answer from here. Connect your own WhatsApp Business Account for a two-way inbox.';
$lang['wapi_shared_not_on']             = 'This account is not using a shared WhatsApp number.';
$lang['wapi_shared_managed_title']      = 'Managed by the provider';
$lang['wapi_shared_traffic_denied']     = 'That is not enabled on %s’s shared WhatsApp number for this account.';
