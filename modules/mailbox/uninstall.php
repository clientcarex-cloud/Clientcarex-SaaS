<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Mailbox — uninstall.
 *
 * Tables and stored mail are intentionally KEPT so that deactivating /
 * re-activating the module never destroys correspondence. Only the
 * module options are removed.
 */

delete_option('mailbox_sync_interval');
delete_option('mailbox_initial_days');
delete_option('mailbox_sync_batch');
delete_option('mailbox_cron_last_run');
