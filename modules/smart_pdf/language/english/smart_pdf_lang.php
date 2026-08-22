<?php

defined('BASEPATH') or exit('No direct script access allowed');

$lang['smart_pdf'] = 'Smart PDF';
$lang['smart_pdf_template_lowercase'] = 'smart pdf template';
$lang['smart_pdf_template_not_found'] = 'Smart PDF template not found';
$lang['new_smart_pdf_template'] = 'New Smart PDF Template';
$lang['edit_smart_pdf_template'] = 'Edit Smart PDF Template';

// Permissions
$lang['smart_pdf_perm_generate'] = 'Generate Documents';

// Manage / table
$lang['smart_pdf_manage_help'] = 'Set up an HTML template once with {{tag}} placeholders, then use Generate to fill in the details and get a dynamic PDF or Print - perfect for forms, brochures, letters and certificates.';
$lang['smart_pdf_dt_name'] = 'Name';
$lang['smart_pdf_dt_description'] = 'Description';
$lang['smart_pdf_dt_tags'] = 'Tags';
$lang['smart_pdf_dt_active'] = 'Status';
$lang['smart_pdf_dt_created'] = 'Created';
$lang['smart_pdf_dt_options'] = 'Options';
$lang['smart_pdf_active'] = 'Active';
$lang['smart_pdf_inactive'] = 'Inactive';

// Setup form
$lang['smart_pdf_name'] = 'Name';
$lang['smart_pdf_description'] = 'Description';
$lang['smart_pdf_import_html'] = 'Import HTML File';
$lang['smart_pdf_import_html_help'] = 'Choose a .html file to load it into the template content below. You can also paste or edit the HTML directly.';
$lang['smart_pdf_import_done'] = 'HTML file imported - tags detected below.';
$lang['smart_pdf_content'] = 'Template HTML Content';
$lang['smart_pdf_content_help'] = 'Use double curly braces for dynamic values, e.g. {{patient_name}}, {{date}}, {{doctor}}. Each tag becomes an input in the generate popup.';
$lang['smart_pdf_output_settings'] = 'Output Settings';
$lang['smart_pdf_paper_size'] = 'Paper Size';
$lang['smart_pdf_orientation'] = 'Orientation';
$lang['smart_pdf_portrait'] = 'Portrait';
$lang['smart_pdf_landscape'] = 'Landscape';

// Tags configuration
$lang['smart_pdf_tags'] = 'Detected Tags';
$lang['smart_pdf_detect_tags'] = 'Detect Tags';
$lang['smart_pdf_tags_help'] = 'These tags were found in your HTML. Configure how each one is asked at generate time. Tags that mean the same thing but are spelled differently ({{doctor_name}}, {{Doctor Name}}, {{DOCTOR-NAME}}) are merged into one input that fills every occurrence.';
$lang['smart_pdf_tag_variants'] = 'merged';
$lang['smart_pdf_tag_variants_title'] = 'These spellings in your HTML share this single input:';
$lang['smart_pdf_tag_occurrences'] = 'How many times this tag appears in the template';
$lang['smart_pdf_tag'] = 'Tag';
$lang['smart_pdf_tag_label'] = 'Label';
$lang['smart_pdf_tag_type'] = 'Input Type';
$lang['smart_pdf_tag_options'] = 'Options';
$lang['smart_pdf_tag_options_placeholder'] = 'Option 1, Option 2, ...';
$lang['smart_pdf_tag_default'] = 'Default Value';
$lang['smart_pdf_tag_required'] = 'Required';
$lang['smart_pdf_no_tags'] = 'No {{tags}} detected yet - import or paste HTML content above, or add a tag manually.';
$lang['smart_pdf_add_tag'] = 'Add Tag';
$lang['smart_pdf_add_tag_placeholder'] = 'tag_name';
$lang['smart_pdf_tag_actions'] = 'Actions';
$lang['smart_pdf_insert_tag'] = 'Insert {{tag}} into the HTML at the cursor position';
$lang['smart_pdf_remove_tag'] = 'Remove this tag';
$lang['smart_pdf_ignore_tag'] = 'Ignore this tag - it will not be asked at generate time';
$lang['smart_pdf_ignored_tags'] = 'Ignored tags';
$lang['smart_pdf_ignored_tags_help'] = 'These tags were found in the HTML but are hidden from the generate form. Click one to restore it.';
$lang['smart_pdf_restore_tag'] = 'Restore this tag';
$lang['smart_pdf_invalid_tag'] = 'Tag name may only contain letters, numbers, underscore, hyphen and spaces.';

// How it works panel
$lang['smart_pdf_how_it_works'] = 'How It Works';
$lang['smart_pdf_how_step_1'] = 'Import or paste your HTML design (form, brochure, letter...).';
$lang['smart_pdf_how_step_2'] = 'Add {{tags}} wherever text should be dynamic.';
$lang['smart_pdf_how_step_3'] = 'Configure the label and input type for each tag.';
$lang['smart_pdf_how_step_4'] = 'From the card, click Generate, fill the popup and choose Print - use "Save as PDF" in the print dialog to get a pixel-perfect PDF file.';

// Generate popup
$lang['smart_pdf_generate'] = 'Generate';
$lang['smart_pdf_fill_details'] = 'Fill Details';
$lang['smart_pdf_fill_details_help'] = 'Enter the values below, then choose Preview or Print. For a PDF file, click Print and choose "Save as PDF" in the dialog - pixel-perfect.';
$lang['smart_pdf_no_tags_generate'] = 'This template has no dynamic tags - the document will be generated as-is.';
$lang['smart_pdf_generated_by_you'] = 'Generating as';
$lang['smart_pdf_autofilled'] = 'Filled automatically from your staff profile / company details - edit it if this document is for someone else.';
$lang['smart_pdf_autofilled_badge'] = 'auto';
$lang['smart_pdf_print'] = 'Print / Save as PDF';
$lang['smart_pdf_preview'] = 'Preview';
$lang['smart_pdf_preview_document_no'] = 'PREVIEW';

// Bulk ID-card sheet
$lang['smart_pdf_bulk_no_employees'] = 'No employees were selected for the ID card sheet.';
$lang['smart_pdf_bulk_toolbar_hint'] = 'Multiple ID cards laid out on shared pages. Use your browser print dialog to print or Save as PDF.';

// Card grid
$lang['smart_pdf_search_placeholder'] = 'Search templates...';
$lang['smart_pdf_filter_all'] = 'All';
$lang['smart_pdf_empty'] = 'No templates yet - create your first Smart PDF template to get started.';
$lang['smart_pdf_no_results'] = 'No templates match your search.';
$lang['smart_pdf_meta_tags'] = 'tags';
$lang['smart_pdf_meta_generated'] = 'generated';

// Stats cards
$lang['smart_pdf_stat_templates'] = 'Templates';
$lang['smart_pdf_stat_active'] = 'Active';
$lang['smart_pdf_stat_generated'] = 'Documents Generated';
$lang['smart_pdf_stat_generated_month'] = 'Generated This Month';
$lang['smart_pdf_dt_generated'] = 'Generated';

// HR bundled templates installer
$lang['smart_pdf_install_hr'] = 'Install HR Templates';
$lang['smart_pdf_install_hr_confirm'] = 'This will add the bundled HR document templates (letters, ID cards, certificates, agreements, policies, Form 16) to your library. Templates that already exist are skipped. Continue?';
$lang['smart_pdf_reinstall_hr'] = 'Re-install / Apply Updates';
$lang['smart_pdf_reinstall_hr_confirm'] = 'This will re-apply the latest bundled design (including your company logo) to the HR templates that are already installed, and add any that are missing. Your per-field settings are kept. Continue?';
$lang['smart_pdf_hr_install_done'] = 'HR document templates — %d added, %d updated, %d unchanged.';

// Clone
$lang['smart_pdf_clone'] = 'Clone';
$lang['smart_pdf_copy'] = 'Copy';
$lang['smart_pdf_cloned'] = 'Template cloned - it is inactive until you review and activate it.';

// Patient autofill
$lang['smart_pdf_patient_autofill'] = 'Auto-fill from patient';
$lang['smart_pdf_patient_search_placeholder'] = 'Search patient by name, mobile or MR number...';
$lang['smart_pdf_patient_no_results'] = 'No patients found';

// Employee autofill (HR)
$lang['smart_pdf_employee_autofill'] = 'Auto-fill from employee';
$lang['smart_pdf_employee_search_placeholder'] = 'Search employee by name, code or email...';
$lang['smart_pdf_employee_no_results'] = 'No employees found';

// Generation history
$lang['smart_pdf_history'] = 'History';
$lang['smart_pdf_history_doc_no'] = 'Document No';
$lang['smart_pdf_history_when'] = 'Generated At';
$lang['smart_pdf_history_by'] = 'Generated By';
$lang['smart_pdf_history_patient'] = 'Patient / Employee';
$lang['smart_pdf_history_mode'] = 'Mode';
$lang['smart_pdf_history_reuse'] = 'Reuse';
$lang['smart_pdf_history_empty'] = 'No documents generated from this template yet.';

// Smart tags panel
$lang['smart_pdf_smart_tags'] = 'Smart Tags';
$lang['smart_pdf_smart_tags_help'] = 'Click a tag to insert it into the HTML at the cursor position.';
$lang['smart_pdf_smart_tags_system'] = 'Auto-filled (never asked)';
$lang['smart_pdf_smart_tags_system_note'] = 'Filled automatically at generate time: date, time, clinic details and a unique document number, plus the staff member generating the document - {{staff_name}}, {{staff_photo}} for a ready-made round portrait, or {{staff_photo_url}} (works inside your own &lt;img src&gt;, and renders as the picture when placed on its own).';
$lang['smart_pdf_smart_tags_agent'] = 'Agent / Hospital (pre-filled from you)';
$lang['smart_pdf_smart_tags_agent_note'] = 'These arrive already filled in the generate popup - the agent is whoever is generating (name, photo, designation, department, phone, email) and the hospital details come from your company settings. They stay editable, so you can override them for a colleague. Many spellings work: {{AgentName}}, {{Executive Name}}, {{Prepared By}}, {{Consultant Phone}}, {{Company Name}}, {{Clinic Address}} and more. A photo or logo tag placed on its own (not inside an &lt;img src&gt;) is rendered as the picture itself, so you can just drop it into your avatar box.';
$lang['smart_pdf_smart_tags_patient'] = 'Patient (auto-fill)';
$lang['smart_pdf_smart_tags_patient_note'] = 'These appear as normal inputs, but the generate popup offers a patient search that fills them all at once.';
$lang['smart_pdf_smart_tags_employee'] = 'Employee / HR (auto-fill)';
$lang['smart_pdf_smart_tags_employee_note'] = 'For HR documents (ID cards, letters, certificates). The generate popup offers an employee search that fills them all at once. {{employee_photo_url}} works inside an &lt;img src&gt; or on its own - a picture tag standing in normal text is rendered as the image itself.';

// Editor extras
$lang['smart_pdf_live_preview'] = 'Live Preview';
$lang['smart_pdf_live_preview_help'] = 'Preview with sample values - tags are shown as [Label] or their default value.';
$lang['smart_pdf_preview_empty'] = 'Nothing to preview yet - import or paste HTML content first.';
$lang['smart_pdf_move_up'] = 'Move up';
$lang['smart_pdf_move_down'] = 'Move down';

// Branding Center
$lang['smart_pdf_branding'] = 'Branding';
$lang['smart_pdf_branding_intro'] = 'Set your colours, logo and company name once - they apply to every generated document automatically.';
$lang['smart_pdf_branding_saved'] = 'Branding saved. It now applies to all documents.';
$lang['smart_pdf_branding_reset_done'] = 'Branding reset to defaults.';
$lang['smart_pdf_brand_colours'] = 'Colours';
$lang['smart_pdf_brand_primary'] = 'Primary';
$lang['smart_pdf_brand_accent'] = 'Accent';
$lang['smart_pdf_brand_heading'] = 'Headings';
$lang['smart_pdf_brand_text'] = 'Body text';
$lang['smart_pdf_brand_font'] = 'Font';
$lang['smart_pdf_brand_font_default'] = 'Template default';
$lang['smart_pdf_brand_logo'] = 'Logo';
$lang['smart_pdf_brand_show_logo'] = 'Show logo on documents';
$lang['smart_pdf_brand_logo_source'] = 'Logo source';
$lang['smart_pdf_brand_logo_crm'] = 'Use CRM logo';
$lang['smart_pdf_brand_logo_custom'] = 'Upload custom logo';
$lang['smart_pdf_brand_logo_upload'] = 'Upload logo';
$lang['smart_pdf_brand_logo_size'] = 'Logo size';
$lang['smart_pdf_brand_company_name'] = 'Company name';
$lang['smart_pdf_brand_show_name'] = 'Show company name on documents';
$lang['smart_pdf_brand_name_help'] = 'The name text comes from your CRM company name (Setup - Settings - Company). Here you control whether it shows and how large.';
$lang['smart_pdf_brand_name_size'] = 'Name size';
$lang['smart_pdf_brand_tagline'] = 'Tagline / sub-heading';
$lang['smart_pdf_brand_tagline_ph'] = 'e.g. Human Resources, Quality Care Since 1998';
$lang['smart_pdf_brand_footer'] = 'Footer';
$lang['smart_pdf_brand_footer_ph'] = 'Optional footer line shown on documents that use {{brand_footer}}';
$lang['smart_pdf_brand_save'] = 'Save branding';
$lang['smart_pdf_brand_reset'] = 'Reset to defaults';
$lang['smart_pdf_brand_reset_confirm'] = 'Reset all branding to the shipped defaults?';
$lang['smart_pdf_brand_live_preview'] = 'Live preview';
$lang['smart_pdf_brand_live'] = 'Live';
$lang['smart_pdf_brand_preview_note'] = 'This is a sample document. Your changes preview instantly here and apply to every template once you save.';
$lang['smart_pdf_brand_uploading'] = 'Uploading...';
$lang['smart_pdf_brand_upload_done'] = 'Uploaded.';

// Per-template branding + Logo Studio
$lang['smart_pdf_personalize'] = 'Personalize this template';
$lang['smart_pdf_branding_tpl_on'] = 'Custom branding active';
$lang['smart_pdf_branding_tpl_intro'] = 'Give just this template its own colours, logo and layout. When off, it uses your global branding.';
$lang['smart_pdf_branding_tpl_saved'] = 'Template branding saved.';
$lang['smart_pdf_branding_tpl_enable'] = 'Use custom branding for this template';
$lang['smart_pdf_branding_tpl_enable_help'] = 'When enabled, the settings below apply to this template only and override the global Branding Center. When off, this template follows the global branding.';
$lang['smart_pdf_branding_tpl_preview'] = 'Live preview - this template';
$lang['smart_pdf_branding_tpl_note'] = 'This is the actual template. Changes preview instantly and apply only to this template once you save.';
$lang['smart_pdf_studio_recolour'] = 'Recolour logo';
$lang['smart_pdf_studio_original'] = 'Original';
$lang['smart_pdf_studio_white'] = 'White';
$lang['smart_pdf_studio_black'] = 'Black';
$lang['smart_pdf_studio_remove_bg'] = 'Remove background';
$lang['smart_pdf_studio_tolerance'] = 'Removal strength';
$lang['smart_pdf_studio_apply'] = 'Apply logo';
$lang['smart_pdf_studio_none'] = 'No logo to process yet.';
$lang['smart_pdf_studio_on_dark'] = 'Preview on dark';
$lang['smart_pdf_studio_ready'] = 'Ready - adjust then Apply.';
$lang['smart_pdf_studio_load_err'] = 'Could not load logo image.';
