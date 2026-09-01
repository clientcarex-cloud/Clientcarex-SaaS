<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CSRF exclusions for the Lead Sync module.
 *
 * The instant-delivery endpoint is a server-to-server webhook: a Google Apps
 * Script, Zapier, Make or n8n POSTs sheet rows to it and has no session and no
 * CSRF token. Its own 40-character token is the credential.
 *
 * application/hooks/InitModules.php picks this file up during the `pre_system`
 * hook — before Security and Input are constructed — and merges it through the
 * `csrf_exclude_uris` filter that App_Security::csrf_verify() applies. So the
 * module exempts its own endpoint with no edit to application/config/config.php.
 *
 * Patterns are anchored regexes matched against the FULL, pre-routing
 * uri_string (`#^<pattern>$#i`), which is why both the short route and the
 * long HMVC form are listed: whichever URL the sender was given has to match.
 */
// Deliberately matched on "a token-shaped segment" rather than the exact 40 hex
// characters: a mistyped or truncated token should reach the controller and get
// its plain JSON "Unknown endpoint" answer, not a CSRF "Page Expired" page that
// sends whoever is wiring the sheet up hunting in the wrong direction. Nothing
// is weakened by that — the endpoint does nothing at all without a token that
// matches a connection.
return [
    'lead_sync/push/[A-Za-z0-9_-]*',
    'lead_sync/lead_sync_push/index/[A-Za-z0-9_-]*',
];
