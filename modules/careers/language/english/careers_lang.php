<?php

defined('BASEPATH') or exit('No direct script access allowed');

# Careers — module strings.
# Menu, permission and alert strings live here; the screens themselves are
# written in plain English, so a missing key can never render as a raw slug on
# a page a recruiter is looking at.

$lang['careers']                      = 'Careers';
$lang['careers_perm_settings']        = 'Settings & masters';

# Navigation
$lang['careers_dashboard']            = 'Dashboard';
$lang['careers_openings']             = 'Openings';
$lang['careers_applications']         = 'Applications';
$lang['careers_pipeline']             = 'Pipeline';
$lang['careers_interviews']           = 'Interviews';
$lang['careers_setup']                = 'Departments & Locations';
$lang['careers_settings']             = 'Settings';
$lang['careers_new_job']              = 'New Opening';

# Jobs
$lang['careers_job_created']          = 'Opening created successfully';
$lang['careers_job_updated']          = 'Opening updated successfully';
$lang['careers_job_duplicated']       = 'Opening duplicated — the copy is saved as a draft';
$lang['careers_job_deleted']          = 'Opening deleted along with its applications';
$lang['careers_job_save_failed']      = 'The opening could not be saved. A title is required.';

# Applications
$lang['careers_application_deleted']  = 'Application deleted';
$lang['careers_stage_unchanged']      = 'The candidate is already at that stage';
$lang['careers_resume_missing']       = 'The resume file for this application is no longer on the server';
$lang['careers_email_sent']           = 'Email sent to the candidate';
$lang['careers_email_failed']         = 'The email could not be sent — check Setup → Settings → Email';
$lang['careers_email_incomplete']     = 'A subject and a message are required';

# Interviews
$lang['careers_interview_saved']      = 'Interview saved';
$lang['careers_interview_deleted']    = 'Interview cancelled';
$lang['careers_interview_save_failed'] = 'The interview could not be saved — a candidate and a date are required';

# Setup & settings
$lang['careers_saved']                = 'Saved successfully';
$lang['careers_deleted']              = 'Deleted successfully';
