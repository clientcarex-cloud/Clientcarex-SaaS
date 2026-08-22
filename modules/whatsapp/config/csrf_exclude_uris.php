<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CSRF exclusion URIs for the WhatsApp module — server-to-server endpoints:
 *  • webhook → called by Meta (central app, master domain)
 *  • ingest  → signed forward from the central webhook to a tenant
 *
 * These are matched by App_Security::csrf_verify() as anchored regexes against
 * the FULL uri_string (`#^<pattern>$#i`). The master account serves admin under
 * a secret prefix (CUSTOM_ADMIN_URL = "<secret>/admin"), which shifts every
 * segment — so a bare 'admin/whatsapp/webhook' never matches there and Meta's
 * POST gets rejected as a CSRF failure, silently killing every inbound message
 * and delivery receipt. Match the prefixed form too.
 */
$uris = [
    'admin/whatsapp/webhook',
    'admin/whatsapp/ingest',
    // Any custom admin prefix in front of the standard admin segment.
    '(.+/)?admin/whatsapp/webhook',
    '(.+/)?admin/whatsapp/ingest',
];

// Belt and braces: installs whose custom admin URL replaces 'admin' entirely.
if (defined('CUSTOM_ADMIN_URL') && CUSTOM_ADMIN_URL !== 'admin') {
    $uris[] = preg_quote(CUSTOM_ADMIN_URL, '#') . '/whatsapp/webhook';
    $uris[] = preg_quote(CUSTOM_ADMIN_URL, '#') . '/whatsapp/ingest';
}

return $uris;
