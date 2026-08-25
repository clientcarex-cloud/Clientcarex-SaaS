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

/* ══════════════════ Class batches (start date + timing) ══════════════════
 * A rider tells us when they want to start and which of the two daily riding
 * windows they want — the morning batch or the evening batch. Nothing is held
 * for them: the batch is a preference and seats go first come, first served,
 * which is exactly what shra_fcfs_note() says on every page that asks for one.
 * The two keys ('morning', 'evening') never change; only the clock times do,
 * and those are editable in SHRA → Settings.
 */

/**
 * The batches on offer.
 *
 * @return array key => ['key', 'label', 'start', 'end', 'time', 'text']
 */
function shra_batches()
{
    $hm = function ($raw, $fallback) {
        $raw = trim((string) $raw);

        return preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $raw) ? $raw : $fallback;
    };
    // "06:00" → "6 AM", "16:30" → "4:30 PM"
    $clock = function ($t) {
        $ts = strtotime('2000-01-01 ' . $t);

        return date(substr($t, -2) === '00' ? 'g A' : 'g:i A', $ts);
    };

    $out = [];
    foreach ([
        'morning' => ['Morning', 'shra_batch_morning_start', '06:00', 'shra_batch_morning_end', '09:00'],
        'evening' => ['Evening', 'shra_batch_evening_start', '16:00', 'shra_batch_evening_end', '21:00'],
    ] as $key => $d) {
        $start = $hm(get_option($d[1]), $d[2]);
        $end   = $hm(get_option($d[3]), $d[4]);
        $time  = $clock($start) . ' – ' . $clock($end);
        $out[$key] = [
            'key'   => $key,
            'label' => $d[0],
            'start' => $start,
            'end'   => $end,
            'time'  => $time,
            'text'  => $d[0] . ' (' . $time . ')',
        ];
    }

    return $out;
}

/** A posted batch, validated. Returns 'morning', 'evening' or null. */
function shra_batch_key($raw)
{
    $raw = strtolower(trim((string) $raw));

    return isset(shra_batches()[$raw]) ? $raw : null;
}

/** "Morning (6 AM – 9 AM)", or just "Morning" — empty when no batch is chosen. */
function shra_batch_label($key, $with_time = true)
{
    $key = shra_batch_key($key);
    if ($key === null) {
        return '';
    }
    $b = shra_batches()[$key];

    return $with_time ? $b['text'] : $b['label'];
}

/**
 * A requested start date as 'Y-m-d', or null when it is empty or unusable.
 * The public forms only accept today onwards; the desk may backdate.
 */
function shra_start_date($raw, $allow_past = false)
{
    $raw = trim((string) $raw);
    if ($raw === '' || $raw === '0000-00-00') {
        return null;
    }
    $ts = strtotime($raw);
    if (!$ts) {
        return null;
    }
    $date = date('Y-m-d', $ts);
    if (!$allow_past && $date < date('Y-m-d')) {
        return null;
    }

    return $date <= date('Y-m-d', strtotime('+2 years')) ? $date : null;
}

/** "Starts Mon 01 Sep · Morning (6 AM – 9 AM)" — empty when neither is set. */
function shra_schedule_line($start_date, $batch, $prefix = 'Starts ')
{
    $bits = [];
    $date = shra_start_date($start_date, true);
    if ($date) {
        $bits[] = $prefix . date('D d M Y', strtotime($date));
    }
    $label = shra_batch_label($batch);
    if ($label !== '') {
        $bits[] = $label;
    }

    return implode(' · ', $bits);
}

/** The first-come-first-served line shown wherever a batch is picked. */
function shra_fcfs_note()
{
    $note = trim((string) get_option('shra_batch_fcfs_note'));

    return $note !== '' ? $note : 'Batches run on a first come, first served basis — seats are confirmed in the order bookings are received.';
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

/** Core invoice status (Perfex ids 1-6) as a SHRA badge. */
function shra_invoice_badge($status)
{
    $map = [
        1 => ['shra-badge-red', 'Unpaid'],
        2 => ['shra-badge-green', 'Paid'],
        3 => ['shra-badge-gold', 'Partially paid'],
        4 => ['shra-badge-red', 'Overdue'],
        5 => ['shra-badge-muted', 'Cancelled'],
        6 => ['shra-badge-muted', 'Draft'],
    ];
    [$cls, $txt] = $map[(int) $status] ?? ['shra-badge-muted', 'Unknown'];

    return '<span class="shra-badge ' . $cls . '">' . $txt . '</span>';
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

/**
 * The Log-call dialog sets a lead status, not a separate "outcome" — but the events table,
 * the leaderboard and the EOD report have always spoken the outcome vocabulary, so every
 * status carries the outcome it implies. Old and new rows stay comparable.
 */
function shra_lead_stage_outcome($stage, $channel = 'call')
{
    if ($channel === 'whatsapp' && ($stage === '' || $stage === 'new')) {
        return 'whatsapp_sent';
    }
    $map = [
        'new'              => 'no_answer',
        'prospect'         => 'interested',
        'enquired'         => 'interested',
        'contacted'        => 'interested',
        'no_response'      => 'no_answer',
        'callback_request' => 'callback_requested',
        'followup'         => 'interested',
        'visit_scheduled'  => 'interested',
        'visited'          => 'interested',
        'confirmed'        => 'interested',
        'won'              => 'interested',
        'lost'             => 'not_interested',
        'junk'             => 'wrong_number',
    ];

    return $map[$stage] ?? 'no_answer';
}

/**
 * Money on a lead: what the deal is worth, what the agent has already collected on a call
 * and what is still due. Used by the work list, the cards and the Log-call dialog.
 */
function shra_lead_money($l)
{
    $deal = (float) ($l->expected_value ?? 0);
    if ($deal <= 0) {
        $deal = (float) ($l->package_price ?? 0);
    }
    $paid = (float) ($l->paid_amount ?? 0);

    return ['deal' => $deal, 'paid' => $paid, 'due' => $deal > 0 ? max(0, round($deal - $paid, 2)) : 0];
}

/** Call outcomes: key => [label, counts as contact?, needs next action?] — legacy vocabulary, see shra_lead_stage_outcome(). */
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
 * The ready-to-send pitch behind the copy button next to WhatsApp.
 * Editable in lead settings; same placeholders as the WA templates.
 */
function shra_lead_wa_copy_msg()
{
    $msg = trim((string) get_option('shra_lead_wa_copy_msg'));
    if ($msg !== '') {
        return $msg;
    }

    return "Hey {name}! 🐎✨\n\n"
        . "This is {agent} from *{academy}* — so glad you asked about horse riding with us! 🙌\n\n"
        . "Here's what's waiting for you:\n"
        . "🏇 Certified trainers + gentle, well-schooled horses\n"
        . "🦺 Full safety gear provided (helmet & body protector)\n"
        . "🧒 Beginners welcome — ages 5 and up\n"
        . "📍 {location}\n\n"
        . "*Your next step is easy:*\n"
        . "1️⃣ Reply *YES* to this message\n"
        . "2️⃣ I'll book your FREE academy visit\n"
        . "3️⃣ Meet the horses, watch a class, pick your batch 🎉\n\n"
        . "⏰ Weekend slots fill up fast — shall I reserve yours today?";
}

/** Maps link for WhatsApp messages — the configured URL, else a Google Maps search. */
function shra_lead_maps_url()
{
    $u = trim((string) get_option('shra_lead_landing_maps_url'));
    if ($u !== '') {
        return $u;
    }
    $q = trim((string) get_option('shra_lead_landing_map_query')) ?: trim((string) get_option('shra_lead_landing_location'));

    return $q !== '' ? 'https://maps.google.com/?q=' . rawurlencode($q) : '';
}

/**
 * Share-info messages in the WhatsApp picker (location, self booking, agent
 * booking, direct visit). Title|Message per line, literal \n = new line,
 * same placeholders as the templates. Editable in lead settings.
 */
function shra_lead_wa_links()
{
    $out = [];
    foreach (shra_lead_lines_option('shra_lead_wa_links') as $line) {
        $parts = explode('|', $line, 2);
        if (count($parts) === 2) {
            $out[] = ['title' => trim($parts[0]), 'text' => str_replace('\n', "\n", trim($parts[1]))];
        }
    }
    if (count($out)) {
        return $out;
    }

    return [
        ['title' => '📍 Location', 'text' => "Here's how to reach *{academy}*, {name}! 🐎\n\n📍 {maps}\n\nSave the pin — and ping me if you need directions on the way!"],
        ['title' => '🖥️ Self booking', 'text' => "{name}, lock in your slot in under a minute! ⚡\n\n👉 {self_booking}\n\nPick your plan, choose your batch — done! Your confirmation comes instantly 🐎"],
        ['title' => '🤝 Agent booking', 'text' => "Let me do the work for you, {name}! 🙌 Just reply with:\n\n1️⃣ Day — Saturday or Sunday\n2️⃣ Time — morning or evening\n\n…and I'll book your visit at *{academy}* right away ✅"],
        ['title' => '🚶 Direct visit', 'text' => "No booking needed, {name} — just walk in! 🐎\n\n🕐 Sat & Sun · 7–9 AM & 4–6 PM\n📍 {maps}\n\nAsk for *{agent}* at the gate — I'll personally show you around {academy}!"],
    ];
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
        '{start}'   => !empty($lead->preferred_start_date) ? date('D d M', strtotime($lead->preferred_start_date)) : '',
        '{batch}'   => shra_batch_label($lead->preferred_batch ?? null),
        '{batches}' => implode(' or ', array_map(function ($b) { return $b['text']; }, shra_batches())),
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
/**
 * Counter payment modes (cash / UPI / card) — the same list the billing screen uses, so a
 * payment taken in the Confirm dialog lands on the invoice under the right mode.
 * Self-heals Cash + UPI when nothing is configured yet.
 */
function shra_lead_payment_modes()
{
    $CI = &get_instance();
    $p  = db_prefix();
    $q  = function () use ($CI, $p) {
        return $CI->db->where('active', 1)->where('expenses_only', 0)->order_by('id', 'ASC')->get($p . 'payment_modes')->result();
    };
    $modes = $q();
    if (!count($modes)) {
        foreach (['Cash', 'UPI'] as $n) {
            $CI->db->insert($p . 'payment_modes', ['name' => $n, 'description' => '', 'active' => 1, 'expenses_only' => 0,
                'invoices_only' => 0, 'show_on_pdf' => 0, 'selected_by_default' => $n === 'Cash' ? 1 : 0]);
        }
        $modes = $q();
    }

    return $modes;
}

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
        'methods'    => shra_lead_payment_methods(),
        'payment_modes' => shra_lead_payment_modes(),
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

/* ═══════════════════════ Online payments (v1.4) ═══════════════════════
 * The academy runs as a SaaS tenant; the payment gateways are configured once
 * on the master account (admin/settings?group=payment_gateways) and the tenant
 * collects through them. Nothing is copied into the tenant options table — the
 * `get_option` filter in shra.php serves the master value at read time, so the
 * checkout, the gateway callback and the webhook all see the same credentials.
 */

/**
 * Raw read of the master account's options table, for names starting with $prefix.
 *
 * Tenants and the master share one database (tenant tables are prefixed
 * `<slug>_tbl`), so the master row is reachable with a plain query on the master
 * prefix. Cached per request and free of get_option(), so it is safe to call
 * from inside the `get_option` filter.
 *
 * @return array name => value
 */
function shra_master_options($prefix = 'paymentmethod')
{
    static $cache = [];
    if (isset($cache[$prefix])) {
        return $cache[$prefix];
    }

    $rows = [];
    $like = str_replace(["'", '%'], '', $prefix) . '%';

    try {
        if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
            $sql  = 'SELECT `name`, `value` FROM `' . perfex_saas_master_db_prefix() . "options` WHERE `name` LIKE '" . $like . "'";
            $res  = perfex_saas_raw_query($sql, [], true);
        } else {
            // Master instance (or a plain single-tenant install) — its own options are the master's.
            $CI  = &get_instance();
            $res = $CI->db->query('SELECT `name`, `value` FROM `' . db_prefix() . 'options` WHERE `name` LIKE ' . $CI->db->escape($like))->result();
        }
        // A failed statement yields [false] rather than rows, so check before reading
        foreach ((array) $res as $r) {
            if (is_object($r)) {
                $rows[$r->name] = $r->value;
            }
        }
    } catch (Exception $e) {
        log_activity('SHRA could not read the master payment settings: ' . $e->getMessage());
    }

    return $cache[$prefix] = $rows;
}

/** Online payment settings for the public /join page. */
function shra_pay_settings()
{
    $mode = get_option('shra_pay_mode') === 'full_only' ? 'full_only' : 'partial';

    return [
        'enabled'    => get_option('shra_pay_enabled') == '1',
        'use_master' => get_option('shra_pay_use_master') == '1',
        'gateways'   => shra_pay_selected_ids(),
        'mode'       => $mode,
        'partial'    => $mode === 'partial',
        'min_type'   => get_option('shra_pay_min_type') === 'fixed' ? 'fixed' : 'percent',
        'min_value'  => (float) get_option('shra_pay_min_value'),
        'allow_skip' => get_option('shra_pay_allow_skip') == '1',
        'note'       => (string) get_option('shra_pay_note'),
    ];
}

/** Gateway ids the admin ticked in SHRA settings. */
function shra_pay_selected_ids()
{
    $ids = json_decode((string) get_option('shra_pay_gateways'), true);

    return is_array($ids) ? array_values(array_filter(array_map('strval', $ids))) : [];
}

/**
 * Every payment gateway registered in this install, described with the state it
 * will actually be used with: the MASTER account's settings while "use the
 * master account's gateway credentials" is on, this instance's own otherwise.
 *
 * @return array id => [id, name, active, configured, currencies, test_mode, selected, source]
 */
function shra_master_gateways()
{
    $CI = &get_instance();
    $CI->load->model('payment_modes_model');

    $from_master = get_option('shra_pay_use_master') == '1';
    $options     = $from_master ? shra_master_options('paymentmethod') : null;
    $selected    = shra_pay_selected_ids();
    $out         = [];

    // One reader for both sources, so the settings screen and the checkout can
    // never disagree about whether a gateway is ready.
    $read = function ($name) use ($from_master, $options) {
        return trim((string) ($from_master ? ($options[$name] ?? '') : get_option($name)));
    };

    foreach ($CI->payment_modes_model->get_payment_gateways(true) as $gateway) {
        $id  = $gateway['id'];
        $pfx = 'paymentmethod_' . $id . '_';

        // A gateway counts as configured once at least one credential is filled
        // in — every gateway keeps its keys in `encrypted` settings.
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

/**
 * Gateways the rider may actually pay with right now: ticked in SHRA settings,
 * active on the master, configured, and accepting the academy's base currency.
 */
function shra_pay_gateways()
{
    $pay = shra_pay_settings();
    if (!$pay['enabled'] || !count($pay['gateways'])) {
        return [];
    }

    $currency = get_base_currency()->name;
    $out      = [];

    foreach (shra_master_gateways() as $id => $g) {
        if (!$g['selected'] || !$g['active'] || !$g['configured']) {
            continue;
        }
        if ($g['currencies'] !== '') {
            $allowed = array_map('trim', explode(',', strtoupper($g['currencies'])));
            if (!in_array(strtoupper($currency), $allowed, true)) {
                continue;
            }
        }
        $out[$id] = $g;
    }

    return $out;
}

/**
 * Smallest amount the rider is allowed to pay now, in currency units.
 * Always at least 1 and never more than the package total.
 */
function shra_pay_min_amount($total)
{
    $pay   = shra_pay_settings();
    $total = round((float) $total, 2);

    if (!$pay['partial']) {
        return $total;
    }

    $min = $pay['min_type'] === 'fixed'
        ? $pay['min_value']
        : $total * max(0, min(100, $pay['min_value'])) / 100;

    return round(max(1, min($total, $min)), 2);
}
