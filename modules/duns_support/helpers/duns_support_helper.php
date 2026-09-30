<?php

defined('BASEPATH') or exit('No direct script access allowed');

/* ─────────────────────────────── Access ──────────────────────────────── */

function duns_can($capability = 'view')
{
    if (is_admin()) {
        return true;
    }

    return staff_can($capability, 'duns_support');
}

/* ──────────────────────────────── URLs ───────────────────────────────── */

/**
 * The public landing page. /duns-support is a core route (application/config/
 * routes.php); the module-owned /duns_support keeps working without it.
 */
function duns_public_url($path = '')
{
    return site_url('duns-support' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
}

/** Private upload folder — per tenant on a SaaS install, so tenants never share it. */
function duns_upload_dir($order_id = null)
{
    $base = FCPATH . 'uploads/duns_support/';
    if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant() && function_exists('perfex_saas_tenant_slug')) {
        $base = FCPATH . 'uploads/tenants/' . perfex_saas_tenant_slug() . '/duns_support/';
    }
    if (!is_dir($base)) {
        @mkdir($base, 0755, true);
        @file_put_contents($base . 'index.html', '');
        @file_put_contents($base . '.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }
    if ($order_id === null) {
        return $base;
    }

    $dir = $base . (int) $order_id . '/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents($dir . 'index.html', '');
    }

    return $dir;
}

/* ─────────────────────────────── Display ─────────────────────────────── */

/** Amount in the base currency, without ".00" on whole numbers (₹10,000). */
function duns_money($amount)
{
    $cur = get_base_currency();
    $out = app_format_money((float) $amount, $cur);
    $dec = $cur->decimal_separator ?: '.';
    if (abs((float) $amount - round((float) $amount)) < 0.005) {
        $out = preg_replace('/' . preg_quote($dec, '/') . '0+(?=\D*$)/', '', $out);
    }

    return $out;
}

/** "1 hour", "6 hours", "2 days". */
function duns_hours_label($hours)
{
    $hours = (int) $hours;
    if ($hours >= 48 && $hours % 24 === 0) {
        return ($hours / 24) . ' days';
    }

    return $hours . ' hour' . ($hours === 1 ? '' : 's');
}

/** Always 12-hour with AM/PM, independent of the global time_format option. */
function duns_datetime($dt)
{
    if (!$dt || $dt === '0000-00-00 00:00:00') {
        return '—';
    }

    return date('d M Y, h:i A', strtotime($dt));
}

/** 123456789 → 12-345-6789, the way D&B prints it. */
function duns_format_number($duns)
{
    $d = preg_replace('/\D+/', '', (string) $duns);

    return strlen($d) === 9 ? substr($d, 0, 2) . '-' . substr($d, 2, 3) . '-' . substr($d, 5) : (string) $duns;
}

function duns_statuses()
{
    return [
        'pending_payment' => ['label' => 'Awaiting payment', 'class' => 'muted',  'icon' => 'fa-regular fa-clock'],
        'paid'            => ['label' => 'Paid — in queue',  'class' => 'blue',   'icon' => 'fa-solid fa-circle-check'],
        'processing'      => ['label' => 'In progress',      'class' => 'amber',  'icon' => 'fa-solid fa-gears'],
        'completed'       => ['label' => 'DUNS delivered',   'class' => 'green',  'icon' => 'fa-solid fa-award'],
        'refunded'        => ['label' => 'Refunded',         'class' => 'red',    'icon' => 'fa-solid fa-rotate-left'],
        'cancelled'       => ['label' => 'Cancelled',        'class' => 'red',    'icon' => 'fa-solid fa-ban'],
    ];
}

function duns_status_badge($status)
{
    $s = duns_statuses()[$status] ?? ['label' => ucfirst((string) $status), 'class' => 'muted', 'icon' => 'fa-regular fa-circle'];

    return '<span class="duns-badge duns-badge-' . $s['class'] . '"><i class="' . $s['icon'] . '"></i> ' . html_escape($s['label']) . '</span>';
}

/** Paid and still being worked on — the delivery clock is running. */
function duns_is_open($order)
{
    return in_array($order->status, ['paid', 'processing'], true);
}

function duns_is_overdue($order)
{
    return duns_is_open($order) && $order->due_at && strtotime($order->due_at) < time();
}

/** "2h 14m left" / "Overdue by 35m". */
function duns_time_left($order)
{
    if (!duns_is_open($order) || !$order->due_at) {
        return '';
    }
    $diff = strtotime($order->due_at) - time();
    $abs  = abs($diff);
    $h    = intdiv($abs, 3600);
    $m    = intdiv($abs % 3600, 60);
    $txt  = ($h ? $h . 'h ' : '') . $m . 'm';

    return $diff >= 0 ? $txt . ' left' : 'Overdue by ' . $txt;
}

function duns_wa_link($phone, $text = '')
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if (strlen($digits) === 10) {
        $digits = '91' . $digits; // bare Indian mobile
    }

    return 'https://wa.me/' . $digits . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/** Everything the public pages need about the brand, contact and ad tags. */
function duns_landing()
{
    $wa = trim((string) get_option('duns_support_whatsapp'));

    return [
        'brand'      => trim((string) get_option('duns_brand_name')) ?: (string) get_option('companyname'),
        'logo'       => duns_logo_url(),
        'phone'      => trim((string) get_option('duns_support_phone')),
        'email'      => trim((string) get_option('duns_support_email')),
        'whatsapp'   => $wa,
        'wa_link'    => $wa !== '' ? duns_wa_link($wa, 'Hi, I want to get a DUNS number for my company.') : '',
        'hero_title' => trim((string) get_option('duns_hero_title')) ?: 'Get your DUNS Number in as little as 1 hour',
        'hero_sub'   => trim((string) get_option('duns_hero_subtitle')),
        'terms_url'  => trim((string) get_option('duns_terms_url')),
        'meta_pixel' => preg_replace('/\D+/', '', (string) get_option('duns_meta_pixel_id')),
        'ga4_id'     => trim((string) get_option('duns_ga4_id')),
        'gads_id'    => trim((string) get_option('duns_gads_id')),
        'gads_label' => trim((string) get_option('duns_gads_label')),
        'currency'   => get_base_currency()->name,
    ];
}

function duns_logo_url()
{
    $logo = (string) get_option('company_logo');
    if ($logo !== '' && is_file(get_upload_path_by_type('company') . $logo)) {
        return base_url('uploads/company/' . $logo);
    }

    return '';
}

/* ────────────────────────────── Payments ─────────────────────────────── */

function duns_pay_selected_ids()
{
    $ids = json_decode((string) get_option('duns_pay_gateways'), true);

    return is_array($ids) ? array_values(array_filter(array_map('strval', $ids))) : [];
}

/**
 * Read options straight from the SaaS master's table (or this install's own
 * when it IS the master / a plain install).
 */
function duns_master_options($prefix = 'paymentmethod')
{
    static $cache = [];
    if (isset($cache[$prefix])) {
        return $cache[$prefix];
    }

    $rows = [];
    $like = str_replace(["'", '%'], '', $prefix) . '%';

    try {
        if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
            $sql = 'SELECT `name`, `value` FROM `' . perfex_saas_master_db_prefix() . "options` WHERE `name` LIKE '" . $like . "'";
            $res = perfex_saas_raw_query($sql, [], true);
        } else {
            $CI  = &get_instance();
            $res = $CI->db->query('SELECT `name`, `value` FROM `' . db_prefix() . 'options` WHERE `name` LIKE ' . $CI->db->escape($like))->result();
        }
        foreach ((array) $res as $r) {
            if (is_object($r)) {
                $rows[$r->name] = $r->value;
            }
        }
    } catch (Exception $e) {
        log_activity('DUNS Support could not read the master payment settings: ' . $e->getMessage());
    }

    return $cache[$prefix] = $rows;
}

/**
 * Every gateway registered in this install, with the state it will actually
 * be used with (master credentials on a tenant with "use master" on).
 *
 * @return array id => [id, name, active, configured, currencies, test_mode, selected, source]
 */
function duns_all_gateways()
{
    $CI = &get_instance();
    $CI->load->model('payment_modes_model');

    $is_tenant   = function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant();
    $from_master = $is_tenant && get_option('duns_pay_use_master') == '1';
    $options     = $from_master ? duns_master_options('paymentmethod') : null;
    $selected    = duns_pay_selected_ids();
    $out         = [];

    $read = function ($name) use ($from_master, $options) {
        return trim((string) ($from_master ? ($options[$name] ?? '') : get_option($name)));
    };

    foreach ($CI->payment_modes_model->get_payment_gateways(true) as $gateway) {
        $id  = $gateway['id'];
        $pfx = 'paymentmethod_' . $id . '_';

        // Configured = at least one encrypted credential is filled in
        $configured = false;
        foreach ($gateway['instance']->getSettings(false) as $setting) {
            if (!empty($setting['encrypted']) && $read($pfx . $setting['name']) !== '') {
                $configured = true;
                break;
            }
        }

        $out[$id] = [
            'id'         => $id,
            'name'       => $read($pfx . 'label') ?: ($gateway['name'] ?: $id),
            'active'     => $read($pfx . 'active') == '1',
            'configured' => $configured,
            'currencies' => $read($pfx . 'currencies'),
            'test_mode'  => $read($pfx . 'test_mode_enabled') == '1',
            'selected'   => in_array($id, $selected, true),
            'source'     => $from_master ? 'master' : 'local',
        ];
    }

    return $out;
}

/** Gateways a buyer may pay with right now: ticked, active, configured, and taking the base currency. */
function duns_pay_gateways()
{
    if (get_option('duns_pay_enabled') != '1' || !count(duns_pay_selected_ids())) {
        return [];
    }

    $currency = strtoupper(get_base_currency()->name);
    $out      = [];
    foreach (duns_all_gateways() as $id => $g) {
        if (!$g['selected'] || !$g['active'] || !$g['configured']) {
            continue;
        }
        if ($g['currencies'] !== '') {
            $allowed = array_map('trim', explode(',', strtoupper($g['currencies'])));
            if (!in_array($currency, $allowed, true)) {
                continue;
            }
        }
        $out[$id] = $g;
    }

    return $out;
}

/* ─────────────────────────────── Email ───────────────────────────────── */

/** A plain, branded HTML email body. $rows = [label => value] shown as a table. */
function duns_email_html($heading, $intro, array $rows = [], $button = null, $footer = '')
{
    $brand = html_escape(duns_landing()['brand']);
    $table = '';
    foreach ($rows as $k => $v) {
        $table .= '<tr><td style="padding:8px 12px;color:#64748b;font-size:13px;border-bottom:1px solid #eef2f7;white-space:nowrap">' . html_escape($k)
            . '</td><td style="padding:8px 12px;font-size:14px;color:#0f172a;border-bottom:1px solid #eef2f7"><b>' . $v . '</b></td></tr>';
    }
    $btn = $button
        ? '<p style="margin:24px 0 6px"><a href="' . html_escape($button[1]) . '" style="background:#2563eb;color:#fff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:600;font-size:14px;display:inline-block">' . html_escape($button[0]) . '</a></p>'
        : '';

    return '<div style="background:#f1f5f9;padding:28px 12px;font-family:Arial,Helvetica,sans-serif">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0">'
        . '<div style="background:#0b1b3f;color:#fff;padding:18px 24px;font-size:16px;font-weight:700">' . $brand . ' · DUNS Support</div>'
        . '<div style="padding:24px"><h2 style="margin:0 0 10px;font-size:20px;color:#0f172a">' . html_escape($heading) . '</h2>'
        . '<p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#334155">' . $intro . '</p>'
        . ($table !== '' ? '<table style="width:100%;border-collapse:collapse;border:1px solid #eef2f7;border-radius:8px">' . $table . '</table>' : '')
        . $btn
        . ($footer !== '' ? '<p style="margin:18px 0 0;font-size:12.5px;color:#64748b;line-height:1.6">' . $footer . '</p>' : '')
        . '</div></div></div>';
}

/**
 * Send one email through the core mailer, IMMEDIATELY.
 *
 * Deliberately not Emails_model::send_simple_email(): that calls
 * $this->email->send() without skipping the job queue, so with "Email Queue"
 * enabled the email is only queued (and reported as sent) until cron picks it
 * up. A DUNS delivery must not wait for cron, so this mirrors the core SMTP
 * test email and calls send(true). Never throws — a mail failure must not
 * break a payment callback.
 */
function duns_send_email($to, $subject, $html)
{
    $to = trim((string) $to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    try {
        $CI = &get_instance();
        $CI->load->config('email');

        // Same header/footer and merge parsing as every core email
        $template           = new stdClass();
        $template->message  = get_option('email_header') . $html . get_option('email_footer');
        $template->fromname = get_option('companyname') ?: duns_landing()['brand'];
        $template->subject  = $subject;
        $template           = parse_email_template($template);

        $CI->email->clear(true);
        $CI->email->set_newline(config_item('newline'));
        $CI->email->from(get_option('smtp_email'), $template->fromname);
        $CI->email->to($to);
        if (($bcc = trim((string) get_option('bcc_emails'))) !== '') {
            $CI->email->bcc($bcc);
        }
        $support = trim((string) get_option('duns_support_email'));
        if ($support !== '' && filter_var($support, FILTER_VALIDATE_EMAIL)) {
            $CI->email->reply_to($support);
        }
        $CI->email->subject($template->subject);
        $CI->email->message(check_for_links($template->message));
        $CI->email->set_alt_message(strip_html_tags($template->message, '<br/>, <br>, <br />'));

        if ($CI->email->send(true)) {
            log_activity('DUNS Support email sent to: ' . $to . ' Subject: ' . $subject);

            return true;
        }
        log_activity('DUNS Support email to ' . $to . ' failed: ' . strip_tags((string) $CI->email->print_debugger()));
    } catch (Throwable $e) {
        log_activity('DUNS Support email to ' . $to . ' failed: ' . $e->getMessage());
    }

    return false;
}

/** Addresses from the "notify" setting (comma / newline separated). */
function duns_notify_emails()
{
    $list = preg_split('/[\s,;]+/', (string) get_option('duns_notify_emails'));

    return array_values(array_unique(array_filter($list, function ($e) {
        return filter_var($e, FILTER_VALIDATE_EMAIL);
    })));
}

/* ─────────────────────────── Email templates ─────────────────────────── */

/** Placeholders staff can use in any email written from the order page. */
function duns_placeholders()
{
    return ['{director_name}', '{first_name}', '{company_name}', '{duns_number}', '{order_no}', '{plan_name}', '{tracking_url}', '{work_email}', '{brand}'];
}

function duns_template_defaults()
{
    return [
        'delivered_subject' => 'Your DUNS number for {company_name}: {duns_number}',
        'delivered_body'    => "Hi {first_name},\n\nGreat news — the D-U-N-S number for {company_name} is ready.\n\nDUNS number: {duns_number}\n\nYou can now use it for Google Play Console, the Apple Developer Program and anywhere else a DUNS number is required. Please note it can take 24–48 hours to appear in Apple's lookup tool.\n\nYou can see your order and delivery details any time here:\n{tracking_url}\n\nThank you for choosing {brand}.",
        'message_subject'   => 'Update on your DUNS order {order_no}',
        'message_body'      => "Hi {first_name},\n\n\n\nTrack your order here: {tracking_url}\n\nRegards,\n{brand}",
    ];
}

/** A stored template (Settings → Delivery email), or the shipped default when blank. */
function duns_template($key)
{
    $v = (string) get_option('duns_tpl_' . $key);

    return trim($v) !== '' ? $v : (duns_template_defaults()[$key] ?? '');
}

/** Replace placeholders with the order's values. $duns overrides the stored number (not saved yet). */
function duns_render($text, $order, $duns = null)
{
    $duns = $duns !== null ? $duns : (string) $order->duns_number;
    $map  = [
        '{director_name}' => $order->director_name,
        '{first_name}'    => explode(' ', trim($order->director_name))[0],
        '{company_name}'  => $order->company_name,
        '{duns_number}'   => $duns !== '' ? duns_format_number($duns) : '',
        '{order_no}'      => $order->order_no,
        '{plan_name}'     => $order->plan_name,
        '{tracking_url}'  => duns_public_url('status/' . $order->ref),
        '{work_email}'    => $order->work_email,
        '{brand}'         => duns_landing()['brand'],
    ];

    return strtr((string) $text, $map);
}

/**
 * A staff-written plain-text email as branded HTML: escaped, line breaks kept,
 * links clickable, the DUNS number shown as a highlight and a "Track your order" button.
 */
function duns_compose_html($subject, $body_text, $order, $show_duns = false)
{
    $html = nl2br(html_escape($body_text));
    $html = preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#2563eb">$1</a>', $html);

    $rows = [];
    if ($show_duns && $order->duns_number) {
        $rows['DUNS number'] = '<span style="font-size:20px;letter-spacing:1px">' . duns_format_number($order->duns_number) . '</span>';
        $rows['Company']     = html_escape($order->company_name);
    }
    $rows['Order'] = html_escape($order->order_no);

    return duns_email_html($subject, $html, $rows, ['Track your order', duns_public_url('status/' . $order->ref)]);
}
