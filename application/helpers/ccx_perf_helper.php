<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Performance helpers.
 */

/**
 * Release the session lock for the rest of this request.
 *
 * Sessions are stored in MySQL (SESS_DRIVER=database), and CodeIgniter's
 * database driver holds a lock on the session row from session_start() until
 * the request ends. Every request from the same browser therefore runs one
 * after another — a page that fires several AJAX calls at once serialises
 * them, which makes the app feel slow even while the server is idle.
 *
 * Read-only JSON endpoints (badge counters, pollers, lookups) never write to
 * the session, so they can hand the lock back immediately and let the user's
 * other requests run in parallel.
 *
 * Call this at the TOP of such an endpoint. After it, $_SESSION stays readable
 * but writes to it are no longer persisted — never call it from a controller
 * that sets userdata or flashdata.
 *
 * @return void
 */
function ccx_release_session_lock()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

/**
 * Whether the session is still open for writing.
 *
 * Session caches (set_userdata used as a short-lived cache) should skip their
 * write when ccx_release_session_lock() has already closed the session,
 * instead of silently discarding it.
 *
 * @return bool
 */
function ccx_session_is_writable()
{
    return session_status() === PHP_SESSION_ACTIVE;
}
