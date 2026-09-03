<?php

defined('BASEPATH') or exit('No direct script access allowed');

$lang['eptw']                            = 'ePTW';
$lang['eptw_dashboard']                  = 'Dashboard';
$lang['eptw_register']                   = 'Permit register';
$lang['eptw_new_permit']                 = 'New permit';
$lang['eptw_reports']                    = 'Reports';
$lang['eptw_setup']                      = 'Setup';

// Notification texts — the core notification bell renders these with the
// serialised additional_data as sprintf arguments.
$lang['eptw_not_requested']              = 'Permit number requested: "%s" by %s';
$lang['eptw_not_ready_to_issue']         = 'All reviews signed — "%s" is ready for a permit number';
$lang['eptw_not_returned']               = 'Permit "%s" returned for correction';
$lang['eptw_not_issued']                 = 'Permit %s issued: %s';
$lang['eptw_not_simops']                 = 'SIMOPS conflict detected on permit %s';
$lang['eptw_not_suspended']              = 'Permit %s suspended — %s';
$lang['eptw_not_resumed']                = 'Permit %s resumed — work may continue';
$lang['eptw_not_extension_requested']    = 'Extension requested on permit %s';
$lang['eptw_not_extension_rejected']     = 'Extension on permit %s was rejected';
$lang['eptw_not_extended']               = 'Permit %s extended until %s';
$lang['eptw_not_closed']                 = 'Permit %s closed';
$lang['eptw_not_expiring']               = 'Permit %s expires %s';
$lang['eptw_not_expired']                = 'Permit %s has expired and is still active';
$lang['eptw_not_docs_pending']           = 'Permit %s — closure documents still missing: %s';
$lang['eptw_not_gas_unsafe']             = 'UNSAFE gas test recorded on permit %s';
$lang['eptw_not_remark']                 = 'New remark on permit %s by %s';
