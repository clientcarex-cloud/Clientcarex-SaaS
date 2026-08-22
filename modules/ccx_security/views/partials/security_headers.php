<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Output HTTP Security Headers
 * Injected via app_admin_head hook when ccx_security_http_headers_enabled = 1
 */

// X-Content-Type-Options — prevent MIME sniffing
header('X-Content-Type-Options: nosniff');

// Framing / clickjacking protection.
// `frame-ancestors` (emitted in the CSP block below) is the modern, allowlist-capable
// control and, in current browsers, overrides X-Frame-Options. We allow the SaaS master
// host(s) so the master console can embed a tenant's admin panel in an iframe across
// subdomains (instance preview) instead of the browser blocking it.
$frame_ancestors = function_exists('ccx_security_frame_ancestors')
    ? ccx_security_frame_ancestors()
    : "'self'";

// The SaaS embed is bidirectional: the master console frames tenant admin panels AND a
// tenant's billing/my_account page frames the master client portal back (client_portal_framer).
// `frame-ancestors` above only says who may embed THIS page; `frame-src` below says what THIS
// page may embed. Without an explicit frame-src, iframes fall back to default-src 'self', which
// blocks the cross-subdomain embed (symptom: "This content is blocked" on the inner iframe).
// The same host allowlist works for both directives, so reuse it.
$frame_src = $frame_ancestors;

// X-Frame-Options can only express DENY/SAMEORIGIN (ALLOW-FROM is obsolete and ignored by
// modern browsers), so it cannot represent the cross-subdomain allowlist above. Only send it
// when framing is restricted to 'self'; otherwise it would block the SaaS instance-preview
// iframe, so we rely on `frame-ancestors` alone.
$x_frame = get_option('ccx_security_x_frame_options') ?: 'SAMEORIGIN';
if ($frame_ancestors === "'self'") {
    header('X-Frame-Options: ' . $x_frame);
}

// X-XSS-Protection — legacy browsers
header('X-XSS-Protection: 1; mode=block');

// Referrer-Policy — control referrer leakage
$referrer = get_option('ccx_security_referrer_policy') ?: 'strict-origin-when-cross-origin';
header('Referrer-Policy: ' . $referrer);

// Permissions-Policy — restrict browser APIs.
// geolocation & camera are allowed for our OWN origin (self) so first-party
// features like MR visit location capture and in-app photo capture work;
// everything else stays disabled. Note: a bare geolocation=() would block
// the site itself even when the user/OS has granted permission.
$pp = get_option('ccx_security_permissions_policy')
    ?: "camera=(self), microphone=(), geolocation=(self), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()";
header('Permissions-Policy: ' . $pp);

// HSTS — force HTTPS (only if enabled)
$hsts = get_option('ccx_security_hsts_enabled');
if ($hsts === '1') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// Content-Security-Policy
$csp_mode = get_option('ccx_security_csp_mode') ?: 'permissive';
if ($csp_mode !== 'disabled') {
    $csp_header_name = ($csp_mode === 'report_only')
        ? 'Content-Security-Policy-Report-Only'
        : 'Content-Security-Policy';

    if ($csp_mode === 'strict') {
        $nonce = ccx_security_generate_csp_nonce();
        // Payment + pixel hosts are allowed so public checkout pages (Pro Ebook
        // landing pages, gateway redirects) keep working under strict CSP.
        $csp = "default-src 'self'; script-src 'self' 'nonce-{$nonce}' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://checkout.razorpay.com https://js.stripe.com https://connect.facebook.net https://www.googletagmanager.com https://www.clarity.ms https://accounts.google.com https://apis.google.com https://www.gstatic.com https://static.cloudflareinsights.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob: https:; connect-src 'self' https://api.razorpay.com https://lumberjack.razorpay.com wss:; frame-src {$frame_src}; frame-ancestors {$frame_ancestors}; base-uri 'self'; form-action 'self' https://secure.payu.in https://test.payu.in;";
    } else {
        // Permissive — allow inline but restrict to self + trusted CDNs
        $csp = "default-src 'self' 'unsafe-inline' 'unsafe-eval'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://checkout.razorpay.com https://js.stripe.com https://connect.facebook.net https://www.googletagmanager.com https://www.clarity.ms https://accounts.google.com https://apis.google.com https://www.gstatic.com https://static.cloudflareinsights.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; img-src 'self' data: blob: https:; connect-src 'self' https: wss:; frame-src {$frame_src}; frame-ancestors {$frame_ancestors}; base-uri 'self';";
    }

    header("{$csp_header_name}: {$csp}");
} elseif ($frame_ancestors !== "'self'") {
    // CSP is otherwise disabled, but the SaaS instance-preview iframe still needs a
    // frame-ancestors allowlist (X-Frame-Options was intentionally skipped above), so emit
    // just that directive to keep cross-subdomain framing working with clickjacking protection.
    header("Content-Security-Policy: frame-ancestors {$frame_ancestors};");
}

// Cache-Control headers for sensitive pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
