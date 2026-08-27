<?php

defined('BASEPATH') or exit('No direct script access allowed');

# Module
$lang['pro_tickets'] = 'Pro Tickets';
$lang['pro_tickets_perm_settings'] = 'Settings & SLA Policies';

# Client portal (customer area)
$lang['pro_tickets_customer_success'] = 'Customer Success';
$lang['pro_tickets_portal_empty'] = 'You have no support tickets yet.';
$lang['pro_tickets_dept_onboarding'] = 'Onboarding & Training';
$lang['pro_tickets_dept_technical'] = 'Technical Support';
$lang['pro_tickets_dept_general'] = 'General Support';
$lang['pro_tickets_dept_others'] = 'Others';
$lang['pro_tickets_dept_open_count'] = '%s open';
$lang['pro_tickets_dept_no_tickets'] = 'No tickets';
$lang['pro_tickets_back_departments'] = 'All departments';

# Real-time customer-reply notifications (staff side)
$lang['pro_tickets_notify_title'] = 'Pro Tickets';
$lang['pro_tickets_notify_new_reply'] = 'New customer reply';
$lang['pro_tickets_notify_view'] = 'Open ticket';
$lang['pro_tickets_notify_from'] = 'From';
$lang['pro_tickets_notify_more'] = '%s more replies';

# Navigation
$lang['pro_tickets_dashboard'] = 'Dashboard';
$lang['pro_tickets_all_tickets'] = 'Tickets';
$lang['pro_tickets_kanban'] = 'Board';
$lang['pro_tickets_new'] = 'New Ticket';
$lang['pro_tickets_settings'] = 'Settings';
$lang['pro_tickets_ticket'] = 'Ticket';

# Dashboard
$lang['pro_tickets_kpi_open'] = 'Open Tickets';
$lang['pro_tickets_kpi_unassigned'] = 'Unassigned';
$lang['pro_tickets_kpi_overdue'] = 'SLA Breached';
$lang['pro_tickets_kpi_at_risk'] = 'At Risk';
$lang['pro_tickets_kpi_new_7d'] = 'New (7 days)';
$lang['pro_tickets_kpi_solved_7d'] = 'Solved (7 days)';
$lang['pro_tickets_kpi_avg_frt'] = 'Avg First Response';
$lang['pro_tickets_kpi_avg_res'] = 'Avg Resolution';
$lang['pro_tickets_kpi_sla_pct'] = 'SLA Compliance';
$lang['pro_tickets_trend_title'] = 'Opened vs Solved — last 14 days';
$lang['pro_tickets_by_status'] = 'By Status';
$lang['pro_tickets_by_priority'] = 'Open by Priority';
$lang['pro_tickets_by_department'] = 'Open by Department';
$lang['pro_tickets_agents'] = 'Agent Workload';
$lang['pro_tickets_agent'] = 'Agent';
$lang['pro_tickets_client'] = 'Client';
$lang['pro_tickets_ai_this_message'] = 'HealthO AI · This message';
$lang['pro_tickets_ai_this_message_hint'] = 'Ask HealthO AI about this message only';
$lang['pro_tickets_ai_correct'] = 'Do correction';
$lang['pro_tickets_ai_correct_hint'] = 'Make your draft professional and grammatically correct, in place';
$lang['pro_tickets_ai_correct_empty'] = 'Type your reply first, then run the correction.';
$lang['pro_tickets_ai_correct_prompt'] = 'Kindly make it Professional & grammar correction. Rewrite the support agent\'s draft reply below so that it reads professionally and is free of spelling, grammar and punctuation mistakes. Keep the agent\'s original meaning, intent and facts intact, and keep the HTML formatting: do not answer or continue the message, do not invent any new information, and do not add commentary of your own. Return ONLY the resulting reply as clean HTML.';
$lang['pro_tickets_agent_open'] = 'Open';
$lang['pro_tickets_agent_solved_30d'] = 'Solved 30d';
$lang['pro_tickets_agent_avg_frt'] = 'Avg FRT';
$lang['pro_tickets_recent_activity'] = 'Latest Activity';
$lang['pro_tickets_opened'] = 'Opened';
$lang['pro_tickets_solved'] = 'Solved';
$lang['pro_tickets_last_30d'] = 'last 30 days';

# List
$lang['pro_tickets_search_ph'] = 'Search subject, #ID, requester…';
$lang['pro_tickets_filter_active_statuses'] = 'Active (excl. closed)';
$lang['pro_tickets_filter_all_statuses'] = 'All statuses';
$lang['pro_tickets_filter_all_departments'] = 'All departments';
$lang['pro_tickets_filter_all_priorities'] = 'All priorities';
$lang['pro_tickets_filter_all_agents'] = 'All agents';
$lang['pro_tickets_filter_unassigned'] = 'Unassigned';
$lang['pro_tickets_filter_all_sla'] = 'Any SLA state';
$lang['pro_tickets_filter_sla_breached'] = 'SLA breached';
$lang['pro_tickets_filter_sla_at_risk'] = 'SLA at risk';
$lang['pro_tickets_filter_sla_ok'] = 'SLA on track';
$lang['pro_tickets_filter_any_time'] = 'Any time';
$lang['pro_tickets_filter_period_7'] = 'Last 7 days';
$lang['pro_tickets_filter_period_30'] = 'Last 30 days';
$lang['pro_tickets_filter_period_90'] = 'Last 90 days';
$lang['pro_tickets_col_subject'] = 'Subject';
$lang['pro_tickets_col_requester'] = 'Requester';
$lang['pro_tickets_col_department'] = 'Department';
$lang['pro_tickets_col_priority'] = 'Priority';
$lang['pro_tickets_col_assigned'] = 'Assigned';
$lang['pro_tickets_col_sla'] = 'SLA';
$lang['pro_tickets_col_last_activity'] = 'Last Activity';
$lang['pro_tickets_col_status'] = 'Status';
$lang['pro_tickets_no_tickets'] = 'No tickets match these filters.';
$lang['pro_tickets_results'] = 'tickets';

# SLA chips
$lang['pro_tickets_sla_none'] = 'No SLA';
$lang['pro_tickets_sla_met'] = 'SLA met';
$lang['pro_tickets_sla_breached'] = 'SLA breached';
$lang['pro_tickets_sla_due_in'] = 'Due in %s';
$lang['pro_tickets_sla_overdue_by'] = 'Overdue by %s';

# Ticket detail
$lang['pro_tickets_tab_conversation'] = 'Conversation';
$lang['pro_tickets_tab_details'] = 'Details';
$lang['pro_tickets_tab_notes'] = 'Notes';
$lang['pro_tickets_tab_activity'] = 'Activity';
$lang['pro_tickets_tab_apps'] = 'Linked';
$lang['pro_tickets_reply'] = 'Reply';
$lang['pro_tickets_reply_ph'] = 'Type your reply to the customer…';
$lang['pro_tickets_reply_and_set_status'] = 'Set status on reply';
$lang['pro_tickets_reply_and_set_priority'] = 'Set priority on reply';
$lang['pro_tickets_send_reply'] = 'Send Reply';
$lang['pro_tickets_canned_insert'] = 'Insert canned reply…';
$lang['pro_tickets_internal_notes'] = 'Internal Notes';
$lang['pro_tickets_note_ph'] = 'Private note — never visible to the customer…';
$lang['pro_tickets_add_note'] = 'Add Note';
$lang['pro_tickets_note_added'] = 'Note added.';
$lang['pro_tickets_activity_timeline'] = 'Activity Timeline';
$lang['pro_tickets_properties'] = 'Properties';
$lang['pro_tickets_watchers'] = 'Watchers';
$lang['pro_tickets_watch'] = 'Watch';
$lang['pro_tickets_unwatch'] = 'Unwatch';
$lang['pro_tickets_add_watcher'] = 'Add watcher…';
$lang['pro_tickets_requester'] = 'Requester';
$lang['pro_tickets_cc'] = 'CC (same company)';
$lang['pro_tickets_add_cc'] = 'Add CC contact…';
$lang['pro_tickets_cc_remove'] = 'Remove from CC';
$lang['pro_tickets_created_on'] = 'Created';
$lang['pro_tickets_mobile_hook_hint'] = 'Mobile number hook-triggered SMS / WhatsApp for this ticket is sent to';
$lang['pro_tickets_mobile_none'] = 'No mobile number';
$lang['pro_tickets_mobile_none_hint'] = 'No mobile number on the contact, the customer record or the tenant profile — hook-triggered SMS / WhatsApp for this ticket has nowhere to go.';
$lang['pro_tickets_mobile_show'] = 'Show mobile number';
$lang['pro_tickets_mobile_hide'] = 'Hide mobile number';
$lang['pro_tickets_first_response'] = 'First response';
$lang['pro_tickets_resolution'] = 'Resolution';
$lang['pro_tickets_due'] = 'due';
$lang['pro_tickets_met'] = 'met';
$lang['pro_tickets_breached'] = 'breached';
$lang['pro_tickets_pending'] = 'pending';
$lang['pro_tickets_reopened_times'] = 'Reopened %s time(s)';
$lang['pro_tickets_auto_closed_badge'] = 'Auto-closed';
$lang['pro_tickets_reply_added'] = 'Reply sent.';
$lang['pro_tickets_reply_empty'] = 'The reply message is empty.';
$lang['pro_tickets_created'] = 'Ticket created.';
$lang['pro_tickets_deleted'] = 'Ticket deleted.';
$lang['pro_tickets_something_wrong'] = 'Something went wrong, please try again.';
$lang['pro_tickets_delete_confirm'] = 'Delete this ticket permanently? This cannot be undone.';
$lang['pro_tickets_no_replies_yet'] = 'No replies yet — the conversation starts with the message above.';
$lang['pro_tickets_attachments'] = 'Attachments';

# Activity types
$lang['pro_tickets_act_created'] = 'Ticket created';
$lang['pro_tickets_act_staff_reply'] = 'replied to the customer';
$lang['pro_tickets_act_client_reply'] = 'Customer replied';
$lang['pro_tickets_act_status_changed'] = 'changed the status';
$lang['pro_tickets_act_assigned'] = 'assigned the ticket to %s';
$lang['pro_tickets_act_auto_assigned'] = 'Auto-assigned to %s';
$lang['pro_tickets_act_priority_changed'] = 'changed the priority';
$lang['pro_tickets_act_department_changed'] = 'moved the ticket to another department';
$lang['pro_tickets_act_service_changed'] = 'changed the service';
$lang['pro_tickets_act_note_added'] = 'added an internal note';
$lang['pro_tickets_act_mentioned'] = 'mentioned %s';
$lang['pro_tickets_act_sla_warning'] = 'SLA warning — deadline approaching';
$lang['pro_tickets_act_sla_breach'] = 'SLA breached';
$lang['pro_tickets_act_priority_escalated'] = 'Priority escalated automatically';
$lang['pro_tickets_act_auto_closed'] = 'Auto-closed after inactivity';
$lang['pro_tickets_act_reopened'] = 'reopened the ticket';
$lang['pro_tickets_act_watcher_added'] = 'added %s as watcher';
$lang['pro_tickets_act_watcher_removed'] = 'removed %s as watcher';
$lang['pro_tickets_act_cc_added'] = 'added %s as CC';
$lang['pro_tickets_act_cc_removed'] = 'removed %s from CC';
$lang['pro_tickets_act_todo_added'] = 'added to-do "%s"';
$lang['pro_tickets_act_todo_completed'] = 'completed to-do "%s"';
$lang['pro_tickets_act_todo_reopened'] = 'reopened to-do "%s"';
$lang['pro_tickets_act_todo_deleted'] = 'deleted to-do "%s"';

# Todo / Caller integration
$lang['pro_tickets_todos'] = 'To-Do';
$lang['pro_tickets_todos_none'] = 'No to-do tasks yet.';
$lang['pro_tickets_todos_open_module'] = 'Open Todo module';
$lang['pro_tickets_todo_added'] = 'To-do task added.';
$lang['pro_tickets_todo_progress'] = '%d of %d done';
$lang['pro_tickets_todo_delete'] = 'Delete task';
$lang['pro_tickets_todo_delete_confirm'] = 'Delete this to-do task?';
$lang['pro_tickets_add_todo'] = 'Add To-Do';
$lang['pro_tickets_cancel'] = 'Cancel';
$lang['pro_tickets_todo_description'] = 'Description';
$lang['pro_tickets_todo_description_ph'] = 'Add more details (optional)…';
$lang['pro_tickets_todo_title_ph'] = 'To-do title…';
$lang['pro_tickets_todo_title_auto_ph'] = 'Leave empty to use the ticket subject';
$lang['pro_tickets_todo_due'] = 'Due date';
$lang['pro_tickets_todo_low'] = 'Low';
$lang['pro_tickets_todo_medium'] = 'Medium';
$lang['pro_tickets_todo_high'] = 'High';
$lang['pro_tickets_todo_urgent'] = 'Urgent';
$lang['pro_tickets_todo_no_assignee'] = 'No assignee';
$lang['pro_tickets_todo_create_label'] = 'Also create a follow-up To-Do task for this ticket';
$lang['pro_tickets_todo_default_title'] = 'Follow up ticket: %s';
$lang['pro_tickets_calls'] = 'Recent calls';
$lang['pro_tickets_calls_none'] = 'No calls found for the requester\'s phone number.';
$lang['pro_tickets_calls_open_module'] = 'Open Caller call log';
$lang['pro_tickets_call_recording'] = 'Play recording';
$lang['pro_tickets_emails'] = 'Recent emails';
$lang['pro_tickets_emails_none'] = 'No emails found for the requester\'s email address.';
$lang['pro_tickets_emails_open_module'] = 'Open Mailbox';
$lang['pro_tickets_email_sent'] = 'Sent';
$lang['pro_tickets_email_received'] = 'Received';
$lang['pro_tickets_email_no_subject'] = 'no subject';
$lang['pro_tickets_select_contact_hint'] = 'Select a contact to load its history.';
$lang['pro_tickets_documents'] = 'Smart PDF';
$lang['pro_tickets_documents_open_module'] = 'Open Smart PDF';
$lang['pro_tickets_pdf_choose'] = 'Choose a template…';
$lang['pro_tickets_pdf_none'] = 'No active Smart PDF templates yet.';
$lang['pro_tickets_pdf_no_fields'] = 'This template has no fillable fields.';
$lang['pro_tickets_pdf_preview'] = 'Preview';
$lang['pro_tickets_pdf_generate'] = 'Generate & Print';
$lang['pro_tickets_act_client_linked'] = 'linked the ticket to %s';
$lang['pro_tickets_act_pdf_generated'] = 'Generated document: %s';
$lang['pro_tickets_act_system'] = 'System';

# Smart Forms integration
$lang['pro_tickets_smart_forms'] = 'Smart Forms';
$lang['pro_tickets_smart_forms_open_module'] = 'Open Smart Forms';
$lang['pro_tickets_sf_status_pending'] = 'Pending';
$lang['pro_tickets_sf_status_in_progress'] = 'In progress';
$lang['pro_tickets_sf_status_completed'] = 'Completed';
$lang['pro_tickets_sf_target_contact'] = 'Customer';
$lang['pro_tickets_sf_target_patient'] = 'Patient';
$lang['pro_tickets_sf_overdue'] = 'Overdue';

# New ticket
$lang['pro_tickets_new_subject'] = 'Subject';
$lang['pro_tickets_new_message'] = 'Message';
$lang['pro_tickets_new_department'] = 'Department';
$lang['pro_tickets_new_priority'] = 'Priority';
$lang['pro_tickets_new_service'] = 'Service';
$lang['pro_tickets_new_assigned'] = 'Assign to';
$lang['pro_tickets_new_auto_assign'] = 'Leave empty for smart auto-assignment';
$lang['pro_tickets_new_requester'] = 'Requester';
$lang['pro_tickets_new_requester_search_ph'] = 'Search a contact, company or staff member by name or email…';
$lang['pro_tickets_new_requester_manual'] = 'Can’t find them? Enter a name & email';
$lang['pro_tickets_new_tenant'] = 'Tenant';
$lang['pro_tickets_new_tenant_ph'] = '— Select a tenant —';
$lang['pro_tickets_new_tenant_search_ph'] = 'Search tenant by name…';
$lang['pro_tickets_new_tenant_customer'] = 'Tenant / Customer';
$lang['pro_tickets_new_tenant_customer_search_ph'] = 'Search tenant or customer by name…';
$lang['pro_tickets_new_people_ph'] = 'Search staff / contacts by name or email…';
$lang['pro_tickets_new_people_loading'] = 'Loading…';
$lang['pro_tickets_new_people_none'] = 'No active staff or contacts found.';
$lang['pro_tickets_new_people_count'] = '%d people — pick one as the requester; pick more to add them as CC.';
$lang['pro_tickets_new_tenant_staff_ph'] = 'Search this tenant’s staff by name or email…';
$lang['pro_tickets_new_tenant_staff_loading'] = 'Loading tenant staff…';
$lang['pro_tickets_new_tenant_staff_none'] = 'This tenant has no active staff members.';
$lang['pro_tickets_new_tenant_staff_count'] = '%d active staff — pick one as the requester; pick more to add them as CC.';
$lang['pro_tickets_new_requester_hint_first'] = 'Requester';
$lang['pro_tickets_new_requester_hint_cc'] = 'CC';
$lang['pro_tickets_new_requester_type_contact'] = 'Existing contact';
$lang['pro_tickets_new_requester_type_company'] = 'Company staff';
$lang['pro_tickets_new_requester_type_staff'] = 'Staff member';
$lang['pro_tickets_new_requester_type_other'] = 'Name & email';
$lang['pro_tickets_new_staff_search_ph'] = 'Search staff members by name or email…';
$lang['pro_tickets_new_contact_search_ph'] = 'Search contacts by name, email or company…';
$lang['pro_tickets_new_contact_same_company_hint'] = 'Additional contacts must be from this company (added as CC)';
$lang['pro_tickets_new_company_search_ph'] = 'Search company by name…';
$lang['pro_tickets_new_company_pick_staff'] = 'Select a staff member of this company';
$lang['pro_tickets_new_company_change'] = 'Change company';
$lang['pro_tickets_new_company_no_staff'] = 'This company has no active staff members.';
$lang['pro_tickets_new_company_staff_hint'] = 'Pick one as the requester; pick more to add them as CC.';
$lang['pro_tickets_new_name'] = 'Requester name';
$lang['pro_tickets_new_email'] = 'Requester email';
$lang['pro_tickets_new_submit'] = 'Open Ticket';

# Settings
$lang['pro_tickets_settings_automation'] = 'Automation';
$lang['pro_tickets_set_auto_assign'] = 'Auto-assignment strategy';
$lang['pro_tickets_set_auto_assign_off'] = 'Off — assign manually';
$lang['pro_tickets_set_auto_assign_rr'] = 'Round robin (department staff)';
$lang['pro_tickets_set_auto_assign_lb'] = 'Least busy (fewest open tickets)';
$lang['pro_tickets_set_auto_assign_role'] = 'Auto-assign only to role';
$lang['pro_tickets_set_auto_assign_role_any'] = 'Any role';
$lang['pro_tickets_set_auto_assign_role_hint'] = 'Restrict auto-assignment to staff who hold this role. Leave as "Any role" to consider all eligible staff.';
$lang['pro_tickets_dept_agents'] = 'Department agents';
$lang['pro_tickets_dept_agents_hint'] = 'Choose which staff handle each department. When a department has agents here, auto-assignment picks only from them (falling back to the core department members otherwise).';
$lang['pro_tickets_dept_agents_members'] = 'Agents';
$lang['pro_tickets_dept_agents_no_departments'] = 'No departments have been created yet.';
$lang['pro_tickets_dept_agents_saved'] = 'Department agents saved.';
$lang['pro_tickets_set_auto_close'] = 'Auto-close answered tickets';
$lang['pro_tickets_set_auto_close_days'] = 'after this many idle days';
$lang['pro_tickets_set_warning_pct'] = 'SLA warning threshold (% of window consumed)';
$lang['pro_tickets_set_bump_priority'] = 'Escalate priority to highest on SLA breach';
$lang['pro_tickets_set_notify_watchers'] = 'Notify watchers on replies, status changes and SLA events';
$lang['pro_tickets_run_automation_now'] = 'Run automation now';
$lang['pro_tickets_automation_ran'] = 'Automation pipeline executed.';
$lang['pro_tickets_sla_policies'] = 'SLA Policies';
$lang['pro_tickets_sla_policies_hint'] = 'The most specific active policy wins: department + priority beats department only, which beats priority only, which beats the catch-all.';
$lang['pro_tickets_sla_name'] = 'Policy name';
$lang['pro_tickets_sla_department'] = 'Department';
$lang['pro_tickets_sla_priority'] = 'Priority';
$lang['pro_tickets_sla_any'] = 'Any';
$lang['pro_tickets_sla_frt_hours'] = 'First response (hours)';
$lang['pro_tickets_sla_res_hours'] = 'Resolution (hours)';
$lang['pro_tickets_sla_escalate_to'] = 'Escalate to';
$lang['pro_tickets_sla_escalate_none'] = 'Nobody';
$lang['pro_tickets_sla_active'] = 'Active';
$lang['pro_tickets_sla_add'] = 'Add Policy';
$lang['pro_tickets_sla_edit'] = 'Edit Policy';
$lang['pro_tickets_sla_save'] = 'Save Policy';
$lang['pro_tickets_sla_saved'] = 'SLA policy saved.';
$lang['pro_tickets_sla_deleted'] = 'SLA policy deleted.';
$lang['pro_tickets_sla_name_required'] = 'Policy name is required.';
$lang['pro_tickets_sla_delete_confirm'] = 'Delete this SLA policy?';

# Notifications (%s = ticket subject)
$lang['pro_tickets_not_auto_assigned'] = 'Ticket auto-assigned to you: %s';
$lang['pro_tickets_not_assigned_to_you'] = 'Ticket assigned to you: %s';
$lang['pro_tickets_not_sla_warning'] = 'SLA deadline approaching: %s';
$lang['pro_tickets_not_sla_breach'] = 'SLA BREACHED: %s';
$lang['pro_tickets_not_escalated'] = 'Escalated to you (SLA breach): %s';
$lang['pro_tickets_not_auto_closed'] = 'Ticket auto-closed after inactivity: %s';
$lang['pro_tickets_not_client_reply'] = 'Customer replied on a watched ticket: %s';
$lang['pro_tickets_not_status_changed'] = 'Status changed on a watched ticket: %s';
$lang['pro_tickets_not_mentioned'] = 'You were mentioned in a ticket reply: %s';
$lang['pro_tickets_not_mentioned_note'] = 'You were mentioned in an internal note: %s';

# ── @mention picker ──────────────────────────────────────────────────────────
$lang['pro_tickets_mention_hint'] = 'Type @ to tag a colleague';

# ── Ticket owner (header) ────────────────────────────────────────────────────
$lang['pro_tickets_tenant'] = 'Tenant';
$lang['pro_tickets_tenant_hint'] = 'Open the SaaS instance';
$lang['pro_tickets_no_client_linked'] = 'No customer linked';
$lang['pro_tickets_tenant_user'] = 'Tenant user';

# ── Smart Ticket capture widget (client portal) ──────────────────────────────
$lang['pro_tickets_smart_report'] = 'Report an issue';
$lang['pro_tickets_smart_title'] = 'Report an issue';
$lang['pro_tickets_smart_new'] = 'New';
$lang['pro_tickets_smart_missing_fields'] = 'Please complete the required fields:';

# Guide card
$lang['pro_tickets_smart_guide_title'] = 'Smart Ticket — report issues in seconds';
$lang['pro_tickets_smart_guide_sub'] = 'Snap the screen, mark the problem, and send it to the right team — without leaving this page.';
$lang['pro_tickets_smart_step1_t'] = 'Press the shortcut';
$lang['pro_tickets_smart_step1_d'] = 'Hit Ctrl + Alt + N (⌥⇧N on Mac) anywhere in the app to launch the reporter.';
$lang['pro_tickets_smart_step2_t'] = 'Mark the screen';
$lang['pro_tickets_smart_step2_d'] = 'Draw boxes, arrows or highlights right on the page to point out the issue.';
$lang['pro_tickets_smart_step3_t'] = 'Describe it';
$lang['pro_tickets_smart_step3_d'] = 'Type what went wrong and choose the department that should handle it.';
$lang['pro_tickets_smart_step4_t'] = 'Send';
$lang['pro_tickets_smart_step4_d'] = 'Your annotated screenshot and message are submitted as a ticket instantly.';
$lang['pro_tickets_smart_try'] = 'Try it now';
$lang['pro_tickets_smart_or_press'] = 'or press';
$lang['pro_tickets_smart_dismiss'] = 'Dismiss';

# Collapsed sticky info row (shown after the guide is dismissed)
$lang['pro_tickets_smart_mini_text'] = 'Smart Ticket — snap, mark & report any issue in seconds.';
$lang['pro_tickets_smart_mini_action'] = 'Report an issue';

# Toolbar / capture
$lang['pro_tickets_smart_hint'] = 'Mark the issue on screen — draw <b>boxes</b>, <b>arrows</b> or <b>highlights</b>, then Capture';
$lang['pro_tickets_smart_capture'] = 'Capture';
$lang['pro_tickets_smart_capturing'] = 'Capturing the screen…';
$lang['pro_tickets_smart_capture_failed'] = 'Could not capture the screen. Please try again.';
$lang['pro_tickets_smart_undo'] = 'Undo';
$lang['pro_tickets_smart_clear'] = 'Clear all';
$lang['pro_tickets_smart_tool_box'] = 'Box';
$lang['pro_tickets_smart_tool_arrow'] = 'Arrow';
$lang['pro_tickets_smart_tool_pen'] = 'Pen';
$lang['pro_tickets_smart_tool_highlight'] = 'Highlight';

# Compose drawer
$lang['pro_tickets_smart_screenshot'] = 'Screenshot attached';
$lang['pro_tickets_smart_retake'] = 'Re-mark';
$lang['pro_tickets_smart_edit_marks'] = 'Back to marking';
$lang['pro_tickets_smart_subject_ph'] = 'Optional — we\'ll create one from your description';
$lang['pro_tickets_smart_issue'] = 'What is the issue?';
$lang['pro_tickets_smart_issue_ph'] = 'Describe what went wrong or what you need help with…';
$lang['pro_tickets_smart_send'] = 'Submit ticket';
$lang['pro_tickets_smart_sent'] = 'Sent';
$lang['pro_tickets_smart_err_issue'] = 'Please describe the issue.';
$lang['pro_tickets_smart_err_department'] = 'Please choose a department.';

# Smart Ticket — tenant admin → provider (cross-instance)
$lang['pro_tickets_smart_reported_from_admin'] = 'Reported from the CRM admin panel via Smart Ticket.';
$lang['pro_tickets_smart_page'] = 'Page';
$lang['pro_tickets_smart_saas_no_client'] = 'This instance is not linked to a support account yet.';
$lang['pro_tickets_smart_saas_created'] = 'Your issue has been sent to support.';

# Smart Ticket — master admin → own helpdesk (internal issue reports)
$lang['pro_tickets_smart_internal_report'] = 'Internal issue report — captured from the admin panel with the Smart Ticket shortcut.';
$lang['pro_tickets_smart_reported_by'] = 'Reported by';
$lang['pro_tickets_smart_area'] = 'Module / area';
$lang['pro_tickets_smart_area_module'] = '%s (module)';
$lang['pro_tickets_smart_area_core'] = '%s (core CRM)';
$lang['pro_tickets_smart_area_unknown'] = 'the admin panel';
$lang['pro_tickets_smart_internal_created'] = 'Reported. Logged as ticket #%s.';
$lang['pro_tickets_smart_internal_hint'] = 'Report an issue in any module from the page it happened on';
$lang['pro_tickets_act_smart_reported'] = 'reported this issue from %s with the Smart Ticket shortcut';
$lang['pro_tickets_set_smart_admin'] = 'Smart Ticket shortcut in the admin panel';
$lang['pro_tickets_set_smart_admin_hint'] = 'Lets your own staff press Ctrl + Alt + N (⌥⇧N on Mac) on any admin page to capture, annotate and file an issue in this helpdesk as an internal ticket.';

# Customer satisfaction feedback (CSAT)
$lang['pro_tickets_replies_badge'] = 'Replies';
$lang['pro_tickets_feedback_title'] = 'Rate your support experience';
$lang['pro_tickets_feedback_agent'] = 'Handled by %s';
$lang['pro_tickets_feedback_by'] = 'Submitted by %s';
$lang['pro_tickets_feedback_comment_ph'] = 'Anything we could do better? (optional)';
$lang['pro_tickets_feedback_submit'] = 'Submit feedback';
$lang['pro_tickets_feedback_later'] = 'Maybe later';
$lang['pro_tickets_feedback_thanks_title'] = 'Thank you!';
$lang['pro_tickets_feedback_thanks'] = 'Your feedback helps us serve you better.';
$lang['pro_tickets_feedback_already'] = 'Feedback has already been submitted for this ticket.';
$lang['pro_tickets_feedback_not_closed'] = 'Feedback can only be submitted on closed tickets.';
$lang['pro_tickets_feedback_rating_required'] = 'Please choose a star rating first.';
$lang['pro_tickets_feedback_toast_title'] = 'Your ticket was closed by our support team';
$lang['pro_tickets_feedback_toast_text'] = '%s — how did we do?';
$lang['pro_tickets_feedback_rate_now'] = 'Rate now';
$lang['pro_tickets_feedback_card_title'] = 'Support feedback';
$lang['pro_tickets_feedback_prompt_card'] = 'This ticket is closed — how was our support?';
$lang['pro_tickets_feedback_give'] = 'Give feedback';
$lang['pro_tickets_fb_r1'] = 'Very poor';
$lang['pro_tickets_fb_r2'] = 'Poor';
$lang['pro_tickets_fb_r3'] = 'Okay';
$lang['pro_tickets_fb_r4'] = 'Good';
$lang['pro_tickets_fb_r5'] = 'Excellent';
$lang['pro_tickets_csat_badge'] = 'CSAT %s/5';
$lang['pro_tickets_fb_awaiting'] = 'Awaiting';
$lang['pro_tickets_col_feedback'] = 'Feedback';
$lang['pro_tickets_filter_all_feedback'] = 'All feedback';
$lang['pro_tickets_filter_fb_positive'] = 'Positive (4–5★)';
$lang['pro_tickets_filter_fb_neutral'] = 'Neutral (3★)';
$lang['pro_tickets_filter_fb_negative'] = 'Negative (1–2★)';
$lang['pro_tickets_filter_fb_rated'] = 'Rated';
$lang['pro_tickets_filter_fb_unrated'] = 'Awaiting rating';
$lang['pro_tickets_kpi_csat'] = 'CSAT score';
$lang['pro_tickets_kpi_csat_positive'] = 'Positive ratings';
$lang['pro_tickets_kpi_csat_negative'] = 'Unhappy customers';
$lang['pro_tickets_kpi_csat_responses'] = 'responses';
$lang['pro_tickets_kpi_csat_resp_rate'] = 'Feedback response rate';
$lang['pro_tickets_csat_dist'] = 'Rating distribution';
$lang['pro_tickets_csat_recent'] = 'Latest customer feedback';
$lang['pro_tickets_csat_none'] = 'No customer feedback yet.';
$lang['pro_tickets_agent_csat'] = 'CSAT';
$lang['pro_tickets_act_feedback_received'] = 'Customer rated the support experience %s';
$lang['pro_tickets_not_feedback'] = 'Customer submitted feedback on a closed ticket: %s';

# Predefined message save + ticket transfer
$lang['pro_tickets_save'] = 'Save';
$lang['pro_tickets_canned_save'] = 'Save as template';
$lang['pro_tickets_canned_save_hint'] = 'Save the composed reply as a reusable predefined message';
$lang['pro_tickets_canned_name_ph'] = 'Template name (e.g. "Password reset steps")';
$lang['pro_tickets_canned_missing'] = 'Type a message and a template name first.';
$lang['pro_tickets_canned_saved'] = 'Predefined message saved — it is now available in the dropdown.';

# Link an account-less ticket to a tenant / customer
$lang['pro_tickets_link_client'] = 'Link to tenant / customer…';
$lang['pro_tickets_link_client_hint'] = 'This ticket is not attached to any account yet.';
$lang['pro_tickets_link_client_failed'] = 'That account could not be linked — it has no client record here.';

# Predefined message templates & merge tags
$lang['pro_tickets_tpl_default_name'] = 'Greeting & signature';
$lang['pro_tickets_tpl_role_fallback'] = 'Support Team';
$lang['pro_tickets_tpl_tags_hint'] = 'Merge tags: {Name} (requester), {Agent Name}, {Role}, {Company Name}, {Subject}, {Date} — filled in automatically.';
$lang['pro_tickets_set_new_template'] = 'Prefill the new-ticket message with';
$lang['pro_tickets_set_new_template_none'] = 'Nothing — start with an empty message box';
$lang['pro_tickets_set_new_template_hint'] = 'The chosen predefined message is loaded into the message box on the New ticket screen, with its merge tags filled in.';
$lang['pro_tickets_transfer'] = 'Transfer';
$lang['pro_tickets_transfer_hint'] = 'Transfer this ticket to another department and member';
$lang['pro_tickets_transfer_title'] = 'Transfer ticket';
$lang['pro_tickets_transfer_department'] = 'Transfer to department';
$lang['pro_tickets_transfer_member'] = 'Assign to member';
$lang['pro_tickets_transfer_no_members'] = 'No active members belong to this department — the ticket will be left unassigned (auto-assign may pick it up).';
$lang['pro_tickets_transfer_not_member'] = 'The selected member does not belong to the chosen department.';
$lang['pro_tickets_transfer_submit'] = 'Transfer ticket';
$lang['pro_tickets_act_transferred'] = 'transferred the ticket to %s';
$lang['pro_tickets_not_transferred'] = 'A watched ticket was transferred: %s';

/* To-do checklist templates */
$lang['pro_tickets_todo_templates']                = 'To-Do Templates';
$lang['pro_tickets_todo_templates_hint']           = 'Reusable checklists (e.g. onboarding, bug triage, go-live). Apply one to any ticket from its To-dos tab and every item is added in one click.';
$lang['pro_tickets_todo_templates_none']           = 'No templates yet — create your first one on the right.';
$lang['pro_tickets_todo_templates_none_hint']      = 'No checklist templates yet — create one via the gear button.';
$lang['pro_tickets_todo_template_pick']            = 'Use a checklist template…';
$lang['pro_tickets_todo_template_apply']           = 'Apply';
$lang['pro_tickets_todo_template_applied']         = '%s to-do items added from the template.';
$lang['pro_tickets_todo_template_saved']           = 'Template saved.';
$lang['pro_tickets_todo_template_deleted']         = 'Template deleted.';
$lang['pro_tickets_todo_template_invalid']         = 'A template needs a name and at least one checklist item.';
$lang['pro_tickets_todo_template_new']             = 'New template';
$lang['pro_tickets_todo_template_edit']            = 'Edit template';
$lang['pro_tickets_todo_template_name']            = 'Template name';
$lang['pro_tickets_todo_template_name_ph']         = 'e.g. New tenant onboarding';
$lang['pro_tickets_todo_template_items']           = 'Checklist items';
$lang['pro_tickets_todo_template_add_item']        = 'Add item';
$lang['pro_tickets_todo_template_delete_confirm']  = 'Delete this template? Tickets that already used it keep their to-dos.';

/* Omni Messaging system hooks */
$lang['pro_tickets_hook_sla_frt']         = 'First response';
$lang['pro_tickets_hook_sla_res']         = 'Resolution';
$lang['pro_tickets_hook_sla_both']        = 'First response & resolution';
$lang['pro_tickets_hook_sla_met_yes']     = 'Yes';
$lang['pro_tickets_hook_sla_met_no']      = 'No';
$lang['pro_tickets_hook_assign_manual']   = 'Assigned by an agent';
$lang['pro_tickets_hook_assign_transfer'] = 'Department transfer';
$lang['pro_tickets_hook_assign_auto']     = 'Automatic assignment';
