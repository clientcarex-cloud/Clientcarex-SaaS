<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Age in whole years from a Y-m-d date (null when unknown). */
function shra_age($dob)
{
    if (empty($dob) || $dob === '0000-00-00') {
        return null;
    }
    try {
        return (new DateTime($dob))->diff(new DateTime('today'))->y;
    } catch (Exception $e) {
        return null;
    }
}

function shra_is_minor($dob)
{
    $age = shra_age($dob);

    return $age !== null && $age < (int) get_option('shra_minor_age');
}

/** children | adults for a rider (adults when DOB unknown). */
function shra_audience_for($dob)
{
    return shra_is_minor($dob) ? 'children' : 'adults';
}

/** Riding levels from settings (one per line). */
function shra_riding_levels()
{
    $raw = (string) get_option('shra_riding_levels');
    $out = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw)), 'strlen'));

    return count($out) ? $out : ['Beginner', 'Novice', 'Intermediate', 'Advanced'];
}

function shra_marital_statuses()
{
    return ['single' => 'Single', 'married' => 'Married', 'divorced' => 'Divorced', 'other' => 'Other'];
}

function shra_genders()
{
    return ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];
}

function shra_relationships()
{
    return ['Father', 'Mother', 'Guardian', 'Spouse', 'Brother', 'Sister', 'Other'];
}

/** Currency-formatted amount using the base currency. */
/** Money for the admin screens — whole amounts drop the trailing ".00" (₹1,340 not ₹1,340.00). */
function shra_money($amount)
{
    $cur = get_base_currency();
    $out = app_format_money((float) $amount, $cur);
    $dec = $cur->decimal_separator ?: '.';
    if (abs((float) $amount - round((float) $amount)) < 0.005) {
        $out = preg_replace('/' . preg_quote($dec, '/') . '0+(?=\D*$)/', '', $out);
    }

    return $out;
}

/* ══════════════ Time display ══════════════
 * Every time SHRA puts on screen is 12-hour with AM/PM, whatever the global
 * Perfex "time_format" option says. Storage stays 24-hour (Y-m-d H:i:s).
 */

/** A time as "04:30 PM". Accepts a timestamp, "H:i(:s)" or any datetime string. */
function shra_time($t)
{
    if ($t === null || $t === '' || $t === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = is_numeric($t) ? (int) $t : strtotime((string) $t);

    return $ts ? date('h:i A', $ts) : (string) $t;
}

/** A datetime as "23 Aug 2026, 04:30 PM" (or "23 Aug, 04:30 PM" without the year). */
function shra_datetime($dt, $with_year = true)
{
    if ($dt === null || $dt === '' || $dt === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = is_numeric($dt) ? (int) $dt : strtotime((string) $dt);

    return $ts ? date($with_year ? 'd M Y, h:i A' : 'd M, h:i A', $ts) : (string) $dt;
}

/**
 * Visit-slot labels are free text set in settings ("Sat 16:00-17:00", "Weekday (any time)").
 * Rewrite any 24-hour clock times in them for display; the stored value never changes.
 */
function shra_slot($slot)
{
    $slot = trim((string) $slot);
    if ($slot === '' || preg_match('/\d\s*[ap]\.?m\b/i', $slot)) {
        return $slot; // already written in 12-hour form
    }
    // "16:00-17:00" → "04:00-05:00 PM" (one meridiem when both ends share it)
    $slot = preg_replace_callback('/\b([01]?\d|2[0-3]):([0-5]\d)\s*(?:-|–|to)\s*([01]?\d|2[0-3]):([0-5]\d)\b/i', function ($m) {
        $a = mktime((int) $m[1], (int) $m[2], 0, 1, 1, 2000);
        $b = mktime((int) $m[3], (int) $m[4], 0, 1, 1, 2000);

        return date('A', $a) === date('A', $b)
            ? date('h:i', $a) . '-' . date('h:i A', $b)
            : date('h:i A', $a) . '-' . date('h:i A', $b);
    }, $slot, -1, $ranges);

    if ($ranges) {
        return $slot; // ranges are done — a second pass would re-convert what we just wrote
    }

    // a bare 24-hour time on its own
    return preg_replace_callback('/\b([01]?\d|2[0-3]):([0-5]\d)\b/', function ($m) {
        return date('h:i A', mktime((int) $m[1], (int) $m[2], 0, 1, 1, 2000));
    }, $slot);
}

/** Public self-registration URL. */
function shra_join_url()
{
    return site_url('join');
}

/** Public verification URL encoded in membership / certificate QR codes. */
function shra_verify_url($rider_no, $certificate_no = '')
{
    return site_url('join/verify/' . $rider_no . ($certificate_no !== '' ? '/' . $certificate_no : ''));
}

/** "Powered by ClientcareX" badge (same branding as the login footer). */
function shra_powered_by($class = '')
{
    $logo = module_dir_url(SHRA_MODULE_NAME, 'assets/img/clientcarex_logo.png');

    return '<a class="shra-powered ' . $class . '" href="https://clientcarex.com" target="_blank" rel="noopener" title="ClientcareX">'
        . '<span>Powered by</span><img src="' . $logo . '" alt="ClientcareX"></a>';
}

/** Absolute path of the ClientcareX logo for PDFs. */
function shra_powered_by_logo_path()
{
    $path = module_dir_path(SHRA_MODULE_NAME, 'assets/img/clientcarex_logo.png');

    return is_file($path) ? $path : null;
}

/** Logo URL — uploaded raster first, bundled SVG fallback. */
function shra_logo_url()
{
    $file = (string) get_option('shra_logo');
    if ($file !== '' && is_file(FCPATH . 'uploads/shra/' . $file)) {
        return base_url('uploads/shra/' . $file) . '?v=' . filemtime(FCPATH . 'uploads/shra/' . $file);
    }

    return module_dir_url(SHRA_MODULE_NAME, 'assets/img/logo.svg');
}

/** Absolute path to a raster logo usable inside TCPDF (null when none). */
function shra_logo_pdf_path()
{
    $file = (string) get_option('shra_logo');
    if ($file === '') {
        return null;
    }
    $abs = FCPATH . 'uploads/shra/' . $file;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    return (is_file($abs) && in_array($ext, ['png', 'jpg', 'jpeg'])) ? $abs : null;
}

/** Offer state as used by billing + public page. */
function shra_offer()
{
    $active  = get_option('shra_offer_active') == '1';
    $percent = (float) get_option('shra_offer_percent');
    $ends    = (string) get_option('shra_offer_ends');

    if ($active && $ends !== '' && strtotime($ends) < strtotime('today')) {
        $active = false;
    }

    return [
        'active'  => $active && $percent > 0,
        'percent' => $percent,
        'label'   => (string) get_option('shra_offer_label'),
        'ends'    => $ends,
    ];
}

/** QR code SVG markup for any text (TCPDF barcode engine). */
function shra_qr_svg($text, $size = 5, $color = '#1c1a17')
{
    require_once(APPPATH . 'vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php');
    $barcode = new TCPDF2DBarcode($text, 'QRCODE,M');
    $svg     = $barcode->getBarcodeSVGcode($size, $size, $color);

    // TCPDF emits fixed px width/height without a viewBox; make it scale and centre cleanly.
    if (preg_match('/<svg width="([\d.]+)" height="([\d.]+)"/', $svg, $m)) {
        $svg = preg_replace('/<svg width="[\d.]+" height="[\d.]+"/', '<svg width="100%" height="100%" viewBox="0 0 ' . $m[1] . ' ' . $m[2] . '" preserveAspectRatio="xMidYMid meet"', $svg, 1);
    }

    return $svg;
}

/** Signed token for a public PDF download (membership card). */
function shra_sign($payload)
{
    return hash_hmac('sha256', (string) $payload, (string) get_option('shra_public_token') . APP_ENC_KEY);
}

function shra_verify_sign($payload, $sig)
{
    return hash_equals(shra_sign($payload), (string) $sig);
}

function shra_status_badge($status)
{
    $map = [
        'active'    => 'shra-badge-green',
        'completed' => 'shra-badge-gold',
        'expired'   => 'shra-badge-muted',
        'cancelled' => 'shra-badge-red',
        'inactive'  => 'shra-badge-muted',
    ];
    $cls = $map[$status] ?? 'shra-badge-muted';

    return '<span class="shra-badge ' . $cls . '">' . ucfirst($status) . '</span>';
}

/** Paid / Partial / Unpaid badge for a decorated enrollment. */
function shra_pay_badge($e)
{
    $st = isset($e->pay_status) ? $e->pay_status : 'paid';
    $map = [
        'paid'      => ['shra-badge-green', '<i class="fa fa-check"></i> Paid'],
        'partial'   => ['shra-badge-red', 'Due ' . shra_money($e->due)],
        'unpaid'    => ['shra-badge-red', 'Unpaid · ' . shra_money($e->due)],
        'cancelled' => ['shra-badge-muted', 'Cancelled'],
    ];
    [$cls, $txt] = $map[$st] ?? $map['paid'];

    return '<span class="shra-badge ' . $cls . '">' . $txt . '</span>';
}

/* ═══════════════════════ Leads (v1.3) ═══════════════════════ */

/** Capability check for the leads sub-system: own | all | manage | reports. */
function shra_leads_can($what = 'own')
{
    if (is_admin()) {
        return true;
    }
    switch ($what) {
        case 'own':
            return has_permission('shra', '', 'leads_own') || has_permission('shra', '', 'leads_all') || has_permission('shra', '', 'leads_manage');
        case 'all':
            return has_permission('shra', '', 'leads_all') || has_permission('shra', '', 'leads_manage');
        case 'manage':
            return has_permission('shra', '', 'leads_manage');
        case 'reports':
            return has_permission('shra', '', 'leads_reports') || has_permission('shra', '', 'leads_manage');
    }

    return false;
}

/** Ordered funnel definition: key => [label, order, color]. */
function shra_lead_stage_defs()
{
    return [
        'new'              => ['New', 10, '#5b8def'],
        'prospect'         => ['Prospect', 12, '#6b7a99'],
        'enquired'         => ['Enquired', 14, '#17a2b8'],
        'contacted'        => ['Contacted', 20, '#8e7cc3'],
        'no_response'      => ['No Response', 24, '#8d6e63'],
        'callback_request' => ['Call back Request', 26, '#d1477a'],
        'followup'         => ['Follow-up', 30, '#d4a017'],
        'visit_scheduled'  => ['Visit Scheduled', 40, '#e67e22'],
        'visited'          => ['Visited', 50, '#2e86c1'],
        'confirmed'        => ['Visited & Confirmed', 60, '#1e8449'],
        'won'              => ['Customer', 1000, '#7cb342'],
        'lost'             => ['Lost', 2000, '#a8322d'],
        'junk'             => ['Junk', 3000, '#9e9e9e'],
    ];
}

/**
 * Allowed stage moves, keyed by the stage you are leaving. Single source of truth —
 * Shra_leads_model enforces it and the Log-call modal builds its status picker from it.
 */
function shra_lead_transitions($from = null)
{
    $t = [
        'new'              => ['prospect', 'enquired', 'contacted', 'no_response', 'callback_request', 'followup', 'visit_scheduled', 'lost', 'junk'],
        'prospect'         => ['enquired', 'contacted', 'no_response', 'callback_request', 'followup', 'visit_scheduled', 'lost', 'junk'],
        'enquired'         => ['prospect', 'contacted', 'no_response', 'callback_request', 'followup', 'visit_scheduled', 'lost', 'junk'],
        'contacted'        => ['no_response', 'callback_request', 'followup', 'visit_scheduled', 'lost', 'junk'],
        'no_response'      => ['contacted', 'callback_request', 'followup', 'visit_scheduled', 'lost', 'junk'],
        'callback_request' => ['contacted', 'no_response', 'followup', 'visit_scheduled', 'lost', 'junk'],
        'followup'         => ['contacted', 'no_response', 'callback_request', 'visit_scheduled', 'lost', 'junk'],
        'visit_scheduled'  => ['visited', 'followup', 'visit_scheduled', 'no_response', 'callback_request', 'lost'],
        'visited'          => ['confirmed', 'followup', 'visit_scheduled', 'lost'],
        'confirmed'        => ['won', 'followup', 'visit_scheduled', 'lost'],
        'won'              => [],
        'lost'             => ['followup'],
        'junk'             => ['followup'],
    ];

    return $from === null ? $t : ($t[$from] ?? []);
}

/**
 * Stages the Log-call modal can set on its own. Everything else (a visit, a confirmation,
 * a loss) needs extra details, so it keeps its own dialog.
 */
function shra_lead_quick_stages()
{
    return ['prospect', 'enquired', 'contacted', 'no_response', 'callback_request', 'followup'];
}

function shra_lead_open_stages()
{
    return ['new', 'prospect', 'enquired', 'contacted', 'no_response', 'callback_request', 'followup', 'visit_scheduled', 'visited', 'confirmed'];
}

/** key => tblleads_status.id */
function shra_lead_stage_ids()
{
    static $map = null;
    if ($map === null) {
        $map = json_decode((string) get_option('shra_lead_stage_map'), true) ?: [];
    }

    return $map;
}

function shra_lead_stage_id($key)
{
    $map = shra_lead_stage_ids();

    return isset($map[$key]) ? (int) $map[$key] : 0;
}

function shra_lead_stage_key_from_status($status_id)
{
    foreach (shra_lead_stage_ids() as $k => $id) {
        if ((int) $id === (int) $status_id) {
            return $k;
        }
    }

    return 'new';
}

function shra_lead_stage_label($key)
{
    $d = shra_lead_stage_defs();

    return isset($d[$key]) ? $d[$key][0] : ucfirst($key);
}

function shra_lead_stage_badge($key)
{
    $d     = shra_lead_stage_defs();
    $color = isset($d[$key]) ? $d[$key][2] : '#7a6f5e';

    return '<span class="shra-badge shra-stage" style="background:' . $color . '1a;color:' . $color . '">' . html_escape(shra_lead_stage_label($key)) . '</span>';
}

/** Call outcomes: key => [label, counts as contact?, needs next action?] */
function shra_lead_outcomes()
{
    return [
        'interested'         => ['Interested', true, true],
        'callback_requested' => ['Call back later', true, true],
        'whatsapp_sent'      => ['WhatsApp sent', false, true],
        'no_answer'          => ['No answer', false, true],
        'busy'               => ['Busy', false, true],
        'switched_off'       => ['Switched off', false, true],
        'not_interested'     => ['Not interested', true, false],
        'wrong_number'       => ['Wrong number', false, false],
    ];
}

function shra_lead_lines_option($key)
{
    $raw = (string) get_option($key);

    return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw)), 'strlen'));
}

function shra_lead_visit_slots()
{
    $s = shra_lead_lines_option('shra_lead_visit_slots');

    return count($s) ? $s : ['Sat 07:00-08:00', 'Sun 07:00-08:00', 'Weekday (any time)'];
}

function shra_lead_lost_reasons()
{
    $s = shra_lead_lines_option('shra_lead_lost_reasons');

    return count($s) ? $s : ['Price too high', 'Not interested', 'Other'];
}

/** How an agent can take an advance on a call — editable in Leads → Settings. */
function shra_lead_payment_methods()
{
    $s = shra_lead_lines_option('shra_lead_payment_methods');

    return count($s) ? $s : ['UPI', 'Cash', 'Card', 'Bank transfer', 'Cheque'];
}

/** Payment screenshots never sit on a public URL — they go through the controller. */
function shra_lead_payment_file_url($payment_id)
{
    return admin_url('shra/shra_leads/payment_file/' . (int) $payment_id);
}

/** [['title'=>..,'text'=>..], ...] */
function shra_lead_wa_templates()
{
    $out = [];
    foreach (shra_lead_lines_option('shra_lead_wa_templates') as $line) {
        $parts = explode('|', $line, 2);
        if (count($parts) === 2) {
            $out[] = ['title' => trim($parts[0]), 'text' => trim($parts[1])];
        }
    }

    return $out;
}

/**
 * Digits-only phone used as the duplicate key. Strips the country code
 * (option shra_lead_phone_country, default 91) and a leading trunk 0.
 */
function shra_phone_norm($raw)
{
    $d  = preg_replace('/\D+/', '', (string) $raw);
    $cc = preg_replace('/\D+/', '', (string) get_option('shra_lead_phone_country'));
    if ($cc !== '' && strlen($d) > 10 && strpos($d, $cc) === 0) {
        $d = substr($d, strlen($cc));
    }
    $d = ltrim($d, '0');

    return $d;
}

function shra_phone_valid($raw)
{
    $n = shra_phone_norm($raw);

    return strlen($n) >= 8 && strlen($n) <= 13;
}

/** wa.me link with an optional prefilled message. */
function shra_wa_link($phone, $text = '')
{
    $cc = preg_replace('/\D+/', '', (string) get_option('shra_lead_phone_country'));
    $n  = shra_phone_norm($phone);
    if ($n === '') {
        return '#';
    }

    return 'https://wa.me/' . $cc . $n . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/** Fill {name} {agent} {academy} {visit} placeholders. */
function shra_lead_fill_template($text, $lead)
{
    $visit = '';
    if (!empty($lead->visit_date)) {
        $visit = date('D d M', strtotime($lead->visit_date)) . (!empty($lead->visit_slot) ? ' (' . shra_slot($lead->visit_slot) . ')' : '');
    }

    return strtr((string) $text, [
        '{name}'    => trim((string) ($lead->name ?? '')),
        '{agent}'   => is_staff_logged_in() ? get_staff_full_name(get_staff_user_id()) : '',
        '{academy}' => get_option('shra_academy_name') ?: 'SHRA',
        '{visit}'   => $visit,
    ]);
}

/* ══════════════ Targets / EOD report ══════════════ */

/**
 * Where a monthly target stands, plus the pace it needs from here. Returns null
 * when nothing was set — callers show "no target" rather than a fake 0%.
 */
function shra_lead_target_progress($done, $target, $date = null)
{
    $target = (float) $target;
    if ($target <= 0) {
        return null;
    }
    $ts    = $date && strtotime($date) ? strtotime($date) : time();
    $days  = (int) date('t', $ts);
    $day   = (int) date('j', $ts);
    $left  = max(0, $days - $day);
    $done  = (float) $done;
    $short = max(0, $target - $done);

    return [
        'pct'      => (int) min(999, round($done / $target * 100)),
        'pace'     => (int) round($day / $days * 100),
        'day'      => $day,
        'days'     => $days,
        'days_left' => $left,
        'left'     => $short,
        'per_day'  => $short > 0 ? ($left > 0 ? $short / $left : $short) : 0,
        'on_track' => $done >= $target * ($day / $days),
        'done'     => $done >= $target,
    ];
}

/** Target health for a badge / bar colour: 'done' | 'ahead' | 'close' | 'behind'. */
function shra_lead_target_state($p)
{
    if (!$p) {
        return 'none';
    }
    if ($p['done']) {
        return 'done';
    }
    if ($p['on_track']) {
        return 'ahead';
    }

    return $p['pct'] >= $p['pace'] * 0.7 ? 'close' : 'behind';
}

/** A progress bar drawn in text, for the WhatsApp report. */
function shra_lead_text_bar($pct, $len = 10)
{
    $pct  = max(0, min(100, (int) $pct));
    $fill = (int) round($pct / 100 * $len);

    return str_repeat('▓', $fill) . str_repeat('░', $len - $fill);
}

/**
 * The end-of-day report as a WhatsApp message — *bold*, _italics_ and emoji,
 * sized to paste straight into a group chat. $d comes from
 * Shra_leads_model::day_report().
 */
function shra_lead_eod_message(array $d)
{
    $t   = $d['today'];
    $m   = $d['month'];
    $ts  = strtotime($d['date']);
    $n   = function ($v) { return number_format((float) $v); };
    $L   = [];
    $rule = str_repeat('━', 16);

    $L[] = '🐎 *' . strtoupper(get_option('shra_academy_name') ?: 'SHRA') . '*';
    $L[] = '*END OF DAY REPORT*';
    $L[] = $rule;
    $L[] = '👤 ' . $d['agent'];
    $L[] = '📅 ' . date('l, d M Y', $ts);
    $L[] = '';

    $L[] = '📞 *CALLING*';
    $L[] = 'Calls *' . $n($t->calls) . '*  ·  WhatsApp *' . $n($t->whatsapp) . '*';
    $L[] = 'Leads worked *' . $n($t->touched) . '*  ·  Reached *' . $n($t->connected) . '*';
    $L[] = 'Interested *' . $n($t->interested) . '*  ·  New leads in *' . $n($t->new_leads) . '*';
    $L[] = '';

    $L[] = '🏇 *VISITS*';
    $L[] = 'Booked *' . $n($t->booked) . '*  ·  Walked in *' . $n($t->visited) . '*';
    $L[] = 'Confirmed *' . $n($t->confirmed) . '*  ·  No-show *' . $n($t->no_show) . '*';
    $L[] = '';

    $L[] = '🏆 *CLOSED TODAY*';
    $L[] = 'Joined *' . $n($t->won) . '*  ·  Renewals *' . $n($t->renewals) . '*  ·  Lost *' . $n($t->lost) . '*';
    $L[] = 'Revenue *' . shra_money($t->revenue) . '*  ·  Collected *' . shra_money($t->collected) . '*';
    if (isset($t->advance) && (float) $t->advance > 0) {
        $L[] = 'Advance taken on calls *' . shra_money($t->advance) . '*';
    }
    foreach ($d['wins'] as $w) {
        $L[] = '  ✨ ' . trim((string) $w->name) . ($w->package_name ? ' — ' . $w->package_name : '')
             . ' · ' . shra_money($w->amount_billed) . ($w->kind === 'repeat' ? ' _(renewal)_' : '');
    }
    $L[] = '';

    // ── Month against target ──
    $bars = [];
    if ($m) {
        $bars = [
            ['📞 Calls', (float) $m->calls, (float) $m->calls_target, false],
            ['🏇 Visits', (float) $m->visits_booked, (float) $m->visits_target, false],
            ['💰 Revenue', (float) $m->revenue, (float) $m->revenue_target, true],
        ];
    }
    $shown = 0;
    $head  = null;
    foreach ($bars as $b) {
        $p = shra_lead_target_progress($b[1], $b[2], $d['date']);
        if (!$p) {
            continue;
        }
        if (!$shown) {
            $head = $p;
            $L[]  = '🎯 *' . strtoupper(date('F', $ts)) . ' TARGET* · _day ' . $p['day'] . ' of ' . $p['days'] . '_';
        }
        $shown++;
        $fmt   = $b[3] ? 'shra_money' : $n;
        $L[]   = $b[0] . '  ' . shra_lead_text_bar($p['pct']) . '  *' . $p['pct'] . '%*';
        $note  = $fmt($b[1]) . ' of ' . $fmt($b[2]);
        if ($p['done']) {
            $note .= ' · ✅ done';
        } elseif ($p['days_left'] > 0) {
            $note .= ' · ' . $fmt(ceil($p['per_day'])) . '/day to finish';
        } else {
            $note .= ' · ' . $fmt($p['left']) . ' short';
        }
        $L[] = '   _' . $note . '_';
    }
    if ($shown) {
        $st  = shra_lead_target_state($head);
        $L[] = $st === 'done' || $st === 'ahead'
            ? '✅ _On pace — ' . $head['pace'] . '% of the month gone._'
            : '⚠️ _Behind pace — ' . $head['pace'] . '% of the month gone, ' . $head['days_left'] . ' days left._';
        $L[] = '';
    }

    $L[] = '📋 *PIPELINE NOW*';
    $L[] = 'Open *' . $n($t->open_now) . '*  ·  Overdue *' . $n($t->overdue_now) . '*';
    $L[] = '';

    $L[] = '⏭️ *TOMORROW · ' . date('D, d M', strtotime($d['tomorrow'])) . '*';
    $L[] = 'Follow-ups due *' . $n($t->due_tomorrow) . '*  ·  Visits *' . count($d['visits']) . '*';
    foreach ($d['visits'] as $v) {
        $L[] = '  ⏰ ' . ($v->visit_slot ? shra_slot($v->visit_slot) : 'time TBD') . ' — ' . trim((string) $v->name);
    }

    $L[] = $rule;
    $L[] = '_Sent from ' . (get_option('companyname') ?: 'ClientCareX') . ' · SHRA Leads_';

    return implode("\n", $L);
}

/** Humanised "in 2 h" / "3 d overdue". */
function shra_lead_due_text($datetime)
{
    if (empty($datetime)) {
        return '<span class="shra-muted">no follow-up set</span>';
    }
    $ts   = strtotime($datetime);
    $diff = $ts - time();
    $abs  = abs($diff);
    if ($abs < 3600) {
        $t = max(1, round($abs / 60)) . ' min';
    } elseif ($abs < 86400) {
        $t = round($abs / 3600) . ' h';
    } else {
        $t = round($abs / 86400) . ' d';
    }
    if ($diff < 0) {
        return '<span class="shra-due shra-due-over"><i class="fa fa-exclamation-circle"></i> ' . $t . ' overdue</span>';
    }
    if (date('Y-m-d', $ts) === date('Y-m-d')) {
        return '<span class="shra-due shra-due-today"><i class="fa fa-clock"></i> today ' . shra_time($ts) . '</span>';
    }

    return '<span class="shra-due"><i class="fa fa-calendar"></i> ' . date('D d M', $ts) . '</span>';
}

/**
 * Everything views/leads/partials/modals.php needs. The modals are printed on
 * every SHRA page (see shra_add_footer_components) so the header "New lead"
 * button works outside the leads tab, where the leads controller's common()
 * data is not available.
 */
function shra_lead_modal_vars()
{
    $CI = &get_instance();
    if (!isset($CI->shra_leads_model)) {
        $CI->load->model('shra/shra_leads_model');
    }
    if (!isset($CI->shra_model)) {
        $CI->load->model('shra/shra_model');
    }

    return [
        'agents'     => shra_lead_agents(),
        'sources'    => $CI->shra_leads_model->sources(),
        'packages'   => $CI->shra_model->get_packages(true),
        'slots'      => shra_lead_visit_slots(),
        'reasons'    => shra_lead_lost_reasons(),
        'outcomes'   => shra_lead_outcomes(),
        'methods'    => shra_lead_payment_methods(),
        'templates'  => shra_lead_wa_templates(),
        'weekend'    => shra_lead_weekend_dates(),
        'can_all'    => shra_leads_can('all'),
        'can_manage' => shra_leads_can('manage'),
    ];
}

/** Staff who can work leads (have leads_own / leads_all / leads_manage or are admins). */
function shra_lead_agents($active_only = true)
{
    $CI = &get_instance();
    $p  = db_prefix();
    $q  = "SELECT s.staffid, s.firstname, s.lastname, s.email, s.admin, s.active, s.profile_image,
              CONCAT(s.firstname,' ',s.lastname) AS full_name
           FROM {$p}staff s
           WHERE " . ($active_only ? 's.active = 1 AND ' : '') . "(s.admin = 1 OR EXISTS (
              SELECT 1 FROM {$p}staff_permissions sp WHERE sp.staff_id = s.staffid AND sp.feature = 'shra'
                AND sp.capability IN ('leads_own','leads_all','leads_manage')))
           ORDER BY s.admin ASC, s.firstname ASC";

    return $CI->db->query($q)->result();
}

/** Managers: staff with leads_manage (or admins). */
function shra_lead_manager_ids()
{
    $CI = &get_instance();
    $p  = db_prefix();
    $q  = "SELECT s.staffid FROM {$p}staff s WHERE s.active = 1 AND (s.admin = 1 OR EXISTS (
              SELECT 1 FROM {$p}staff_permissions sp WHERE sp.staff_id = s.staffid AND sp.feature = 'shra' AND sp.capability = 'leads_manage'))";

    return array_map(function ($r) { return (int) $r->staffid; }, $CI->db->query($q)->result());
}

function shra_lead_url($lead_id)
{
    return admin_url('shra/shra_leads/view/' . (int) $lead_id);
}

/** Next Saturday / Sunday dates (today if today is that day). */
function shra_lead_weekend_dates()
{
    $sat = date('Y-m-d', strtotime('saturday this week'));
    $sun = date('Y-m-d', strtotime('sunday this week'));
    if ($sat < date('Y-m-d')) {
        $sat = date('Y-m-d', strtotime('next saturday'));
    }
    if ($sun < date('Y-m-d')) {
        $sun = date('Y-m-d', strtotime('next sunday'));
    }

    return ['sat' => $sat, 'sun' => $sun];
}
