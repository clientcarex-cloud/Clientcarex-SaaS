<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Bulk template import (Excel / CSV) for the SMS & WhatsApp channels.
 *
 * DLT approval sheets come out of every provider portal in a different shape —
 * dozens of columns, headers that are not on the first row, IDs stored as
 * numbers. Only three columns are ever read:
 *
 *   message           → Msg Template ID   (msg_template_id)
 *   sender_id         → Header ID         (header_id)
 *   approved_message  → Template Content  (content) AND Sample Content
 *
 * Everything else in the sheet is ignored. Message Type is derived from the
 * sender id (all-digits = Promotional, otherwise Transactional) and the
 * Template Title is generated from the approved message — see
 * sms_wa_email_import_title().
 */

/** Hard ceiling on rows read from one file, so a stray 50k-row export cannot exhaust memory. */
if (!defined('SMS_WA_EMAIL_IMPORT_MAX_ROWS')) {
    define('SMS_WA_EMAIL_IMPORT_MAX_ROWS', 2000);
}

/* ═══════════════════════════════════════════════════════════════════
 * Column mapping
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * Accepted header spellings per target field, most canonical first.
 * Compared after sms_wa_email_import_norm_key(), so case, spaces,
 * underscores, dots and dashes are all irrelevant.
 */
function sms_wa_email_import_column_aliases()
{
    return [
        'msg_template_id' => [
            'message', 'msg', 'msgtemplateid', 'messagetemplateid', 'templateid',
            'dlttemplateid', 'dltid', 'contenttemplateid', 'messageid', 'msgid',
            'templateidmessage',
        ],
        'header_id' => [
            'senderid', 'sender', 'headerid', 'header', 'senderheader',
            'senderidheader', 'sms senderid',
        ],
        'content' => [
            'approvedmessage', 'approvedtemplate', 'templatecontent', 'messagecontent',
            'content', 'messagetext', 'smstext', 'templatetext', 'approvedcontent',
            'samplecontent', 'messagesample',
        ],
    ];
}

/**
 * Fold a spreadsheet header cell down to a comparable key.
 */
function sms_wa_email_import_norm_key($value)
{
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9]+/', '', $value);

    return (string) $value;
}

/**
 * Find the header row and the three columns we care about.
 *
 * The header is rarely guaranteed to be row 1 (exports often carry a title or
 * a blank row above it), so the first rows are scanned and the one that
 * resolves the most known columns wins.
 *
 * @param  array $matrix
 * @return array ['row' => int|null, 'map' => [field => col index], 'labels' => [field => header text]]
 */
function sms_wa_email_import_locate_header(array $matrix)
{
    $aliases = sms_wa_email_import_column_aliases();
    $best    = ['row' => null, 'map' => [], 'labels' => [], 'score' => 0];
    $scan    = min(count($matrix), 15);

    for ($r = 0; $r < $scan; $r++) {
        $map    = [];
        $labels = [];

        foreach ($matrix[$r] as $col => $cell) {
            $key = sms_wa_email_import_norm_key($cell);
            if ($key === '') {
                continue;
            }

            foreach ($aliases as $field => $names) {
                if (isset($map[$field])) {
                    continue; // first matching column wins
                }
                if (in_array($key, $names, true)) {
                    $map[$field]    = (int) $col;
                    $labels[$field] = trim((string) $cell);
                    break;
                }
            }
        }

        $score = count($map);
        if ($score > $best['score']) {
            $best = ['row' => $r, 'map' => $map, 'labels' => $labels, 'score' => $score];
        }
        if ($score === count($aliases)) {
            break; // perfect header, stop scanning
        }
    }

    unset($best['score']);

    return $best;
}

/* ═══════════════════════════════════════════════════════════════════
 * Derived values
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * Message Type rule: a sender id made up of digits only is a numeric
 * (promotional) header; anything containing a letter is a branded
 * transactional header.
 */
function sms_wa_email_import_subtype($sender_id)
{
    $sender = preg_replace('/[\s\-_]+/', '', (string) $sender_id);

    if ($sender !== '' && ctype_digit($sender)) {
        return 'promotional';
    }

    return 'transactional';
}

/**
 * Strip provider placeholders and collapse whitespace, so the title generator
 * and the duplicate check both work on the words a human would read.
 */
function sms_wa_email_import_plain_text($content)
{
    $text = (string) $content;
    $text = str_replace(['&nbsp;', "\xC2\xA0"], ' ', $text);
    $text = preg_replace('/\{#\s*var\s*#\}/i', ' ', $text);   // DLT {#var#}
    $text = preg_replace('/\{\{.*?\}\}/s', ' ', $text);        // {{1}}, {{name}}
    $text = preg_replace('/\{.*?\}/s', ' ', $text);            // {patient_name}
    $text = preg_replace('/%\w*%?/', ' ', $text);              // %s, %name%
    $text = preg_replace('/\s+/u', ' ', $text);

    return trim($text);
}

/**
 * Normalised form used for content-wise duplicate detection: placeholder
 * syntax and casing differences must not create a "new" template.
 */
function sms_wa_email_import_content_key($content)
{
    $text = strtolower(sms_wa_email_import_plain_text($content));
    $text = preg_replace('/[^a-z0-9 ]+/', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}

/**
 * Drop the brand sign-off DLT templates almost always end with
 * ("… do not share. - ClientcareX"), so it never colours the generated title.
 */
function sms_wa_email_import_strip_signature($text)
{
    return trim(preg_replace('/\s[-–—~]\s*[A-Za-z0-9][^.!?\n]{0,28}$/u', '', (string) $text));
}

/**
 * Intent rules, evaluated in order — the first match names the template.
 * Ordered most-specific first, so "appointment cancelled" never falls through
 * to the generic appointment rule.
 */
function sms_wa_email_import_intent_rules()
{
    return [
        ['/\b(otp|one[\s-]?time[\s-]?password)\b|verification code|security code/i', 'OTP Verification'],
        ['/\bpassword\b.{0,30}\b(reset|change|forgot)\b|\breset\b.{0,20}\bpassword\b/i', 'Password Reset'],
        ['/suspicious|unauthori[sz]ed|security alert/i', 'Security Alert'],
        ['/maintenance|down\s?time|scheduled service|service window/i', 'Maintenance Notice'],
        ['/appointment.{0,40}\b(cancel|cancelled|canceled)\b|\bcancel\w*\b.{0,40}appointment/i', 'Appointment Cancelled'],
        ['/appointment.{0,40}reschedul|reschedul.{0,40}appointment/i', 'Appointment Rescheduled'],
        ['/appointment.{0,60}\b(remind|reminder|tomorrow|upcoming|due)\b|\bremind\w*\b.{0,40}appointment/i', 'Appointment Reminder'],
        ['/\bappointment\b|\bbooking\b.{0,30}\bconfirm/i', 'Appointment Confirmation'],
        ['/\b(lab|test|diagnostic)?\s?reports?\b.{0,50}\b(ready|available|generated|download|dispatch)/i', 'Report Ready'],
        ['/\btest results?\b|\blab results?\b/i', 'Lab Result Update'],
        ['/\bprescription\b/i', 'Prescription Shared'],
        ['/\bdischarge\b/i', 'Discharge Notification'],
        ['/\badmission\b|\badmitted\b/i', 'Admission Confirmation'],
        ['/\b(payment|amount|txn|transaction)\b.{0,50}\b(received|success|successful|credited|confirm)/i', 'Payment Received'],
        ['/\brefund\b/i', 'Refund Update'],
        ['/\b(payment|amount|dues?|balance|outstanding)\b.{0,50}\b(pending|overdue|due|remind)/i', 'Payment Reminder'],
        ['/\binvoice\b|\bbill\b|\bbilling\b/i', 'Invoice Notification'],
        ['/\bregistration\b|\bregistered\b|\bwelcome\b|account.{0,20}created/i', 'Welcome / Registration'],
        ['/\bfeedback\b|\brate your\b|\breview\b|\bsatisfaction\b/i', 'Feedback Request'],
        ['/\bvaccin|\bimmuni|\bdose\b/i', 'Vaccination Reminder'],
        ['/\bbirthday\b|\banniversary\b|\bfestive\b|\bgreeting/i', 'Greetings'],
        ['/\bhealth\s?camp\b|\bcheck\s?up camp\b|\bscreening camp\b/i', 'Health Camp'],
        ['/\bdiscount\b|\boffer\b|\b\d+%\s?off\b|\bsale\b|\bpackage price\b/i', 'Promotional Offer'],
        ['/\btoken\b|\bqueue\b|\byour turn\b/i', 'Token Update'],
        ['/\bfollow[\s-]?up\b/i', 'Follow-up Reminder'],
        ['/\bsubscription\b|\brenewal\b|\brenew\b|\bexpir|\bvalidity\b/i', 'Renewal Reminder'],
        ['/\bdelivery\b|\bdispatch|\bshipped\b|\border\b/i', 'Order Update'],
        ['/\blogged? in\b|\blogin\b|\bsign(ed)? in\b/i', 'Login Alert'],
        ['/\bsurgery\b|\boperation\b|\bprocedure\b/i', 'Procedure Update'],
        ['/\bdoctor\b.{0,30}\b(visit|round|consult)/i', 'Doctor Visit Update'],
        ['/\bvisit\b/i', 'Visit Update'],
        ['/\bthank you\b|\bthanks for\b/i', 'Thank You Note'],
        ['/\breminder\b|\bremind\b/i', 'Reminder'],
    ];
}

/**
 * Words that carry no meaning in a title.
 */
function sms_wa_email_import_stopwords()
{
    return [
        'dear', 'hi', 'hello', 'hey', 'sir', 'madam', 'client', 'customer', 'patient',
        'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'to', 'for', 'of',
        'on', 'in', 'at', 'by', 'with', 'from', 'your', 'you', 'our', 'we', 'us', 'has',
        'have', 'had', 'will', 'shall', 'this', 'that', 'it', 'and', 'or', 'as', 'please',
        'kindly', 'thank', 'thanks', 'regards', 'team', 'has', 'been', 'not', 'no', 'yes',
    ];
}

/**
 * Cased for display: keeps short all-caps words (OTP, ID, UHID) intact and
 * title-cases everything else.
 */
function sms_wa_email_import_title_case($text)
{
    $words = preg_split('/\s+/', trim($text));
    $out   = [];

    foreach ($words as $word) {
        if ($word === '') {
            continue;
        }
        $letters = preg_replace('/[^A-Za-z]/', '', $word);
        if ($letters !== '' && strlen($letters) <= 5 && $letters === strtoupper($letters)) {
            $out[] = $word; // acronym — leave it alone
            continue;
        }
        $out[] = function_exists('mb_convert_case')
            ? mb_convert_case(mb_strtolower($word, 'UTF-8'), MB_CASE_TITLE, 'UTF-8')
            : ucfirst(strtolower($word));
    }

    return implode(' ', $out);
}

/**
 * Fallback title when no intent rule matches: the opening statement of the
 * message, minus the greeting, trimmed to a handful of words.
 */
function sms_wa_email_import_title_from_text($text)
{
    // Drop a leading salutation ("Dear Client,", "Hi,", "Hello Mr X -")
    $text = preg_replace('/^(dear|hi|hello|hey|respected|greetings)\b[^,.:;\n]{0,30}[,.:;\-]?\s*/i', '', $text);
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    // First sentence / line
    $parts    = preg_split('/(?<=[.!?])\s+|\r|\n/', $text, 2);
    $sentence = trim($parts[0]);
    if ($sentence === '') {
        $sentence = $text;
    }

    $words = preg_split('/\s+/', $sentence);
    $words = array_slice($words, 0, 8);
    $title = trim(implode(' ', $words));
    $title = rtrim($title, " \t.,;:-–—");

    return sms_wa_email_import_title_case($title);
}

/**
 * Generate a human title for one approved message, unique within the batch
 * and against the titles already stored.
 *
 * @param  string $content   Approved message text
 * @param  array  $used      Reference to a map of lowercased titles already taken
 * @return string
 */
function sms_wa_email_import_title($content, array &$used)
{
    $text = sms_wa_email_import_strip_signature(sms_wa_email_import_plain_text($content));

    $base = '';
    foreach (sms_wa_email_import_intent_rules() as $rule) {
        if (preg_match($rule[0], $text)) {
            $base = $rule[1];
            break;
        }
    }

    if ($base === '') {
        $base = sms_wa_email_import_title_from_text($text);
    }

    if ($base === '') {
        $base = 'SMS Template';
    }

    $base = sms_wa_email_import_trim_title($base, 150);

    // Free? take it.
    $candidate = $base;
    if (!isset($used[strtolower($candidate)])) {
        $used[strtolower($candidate)] = true;

        return $candidate;
    }

    // Taken — qualify it with the most distinctive word of the message before
    // falling back to a counter, so twenty OTP templates do not become
    // "OTP Verification 2 … 20".
    $hint = sms_wa_email_import_title_hint($text, $base);
    if ($hint !== '') {
        $candidate = sms_wa_email_import_trim_title($base . ' – ' . $hint, 180);
        if (!isset($used[strtolower($candidate)])) {
            $used[strtolower($candidate)] = true;

            return $candidate;
        }
    }

    $i = 2;
    do {
        $candidate = sms_wa_email_import_trim_title($base, 180) . ' ' . $i;
        $i++;
    } while (isset($used[strtolower($candidate)]) && $i < 5000);

    $used[strtolower($candidate)] = true;

    return $candidate;
}

/**
 * The first meaningful word of the message that is not already in the title —
 * used to tell otherwise identically-named templates apart.
 */
function sms_wa_email_import_title_hint($text, $base)
{
    $stop     = sms_wa_email_import_stopwords();
    $inBase   = array_map('strtolower', preg_split('/\s+/', $base));
    $words    = preg_split('/\s+/', $text);
    $picked   = [];

    foreach ($words as $word) {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $word);
        if (strlen($clean) < 4) {
            continue;
        }
        $lower = strtolower($clean);
        if (in_array($lower, $stop, true) || in_array($lower, $inBase, true)) {
            continue;
        }
        $picked[] = $clean;
        if (count($picked) === 2) {
            break;
        }
    }

    return $picked ? sms_wa_email_import_title_case(implode(' ', $picked)) : '';
}

/**
 * Trim to a length without cutting a word in half.
 */
function sms_wa_email_import_trim_title($title, $max)
{
    $title = trim(preg_replace('/\s+/', ' ', (string) $title));
    if (strlen($title) <= $max) {
        return $title;
    }

    $cut   = substr($title, 0, $max);
    $space = strrpos($cut, ' ');

    return rtrim($space !== false && $space > 20 ? substr($cut, 0, $space) : $cut, " \t.,;:-");
}

/* ═══════════════════════════════════════════════════════════════════
 * Spreadsheet reader (.xlsx / .csv) — no external dependency
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * Read the first worksheet of an .xlsx, or a delimited text file, into a
 * padded matrix of trimmed strings.
 *
 * @throws Exception when the file cannot be read
 */
function sms_wa_email_import_read_file($path, $ext)
{
    $ext = strtolower($ext);

    if (in_array($ext, ['csv', 'txt', 'tsv'], true)) {
        $matrix = sms_wa_email_import_read_csv($path);
    } else {
        $matrix = sms_wa_email_import_read_xlsx($path);
    }

    // Pad every row to the widest one so column indexes line up, and drop
    // rows that are completely blank.
    $width = 0;
    foreach ($matrix as $row) {
        $width = max($width, count($row));
    }

    $out = [];
    foreach ($matrix as $row) {
        $has = false;
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                $has = true;
                break;
            }
        }
        if (!$has) {
            continue;
        }
        $out[] = array_pad(array_values($row), $width, '');
    }

    return $out;
}

function sms_wa_email_import_read_csv($path)
{
    $handle = @fopen($path, 'r');
    if ($handle === false) {
        throw new Exception('The uploaded file could not be opened.');
    }

    // Sniff the delimiter from the first line
    $first = fgets($handle);
    if ($first === false) {
        fclose($handle);

        return [];
    }
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);

    $delimiter = ',';
    $best      = 0;
    foreach ([',', ';', "\t", '|'] as $d) {
        $count = substr_count($first, $d);
        if ($count > $best) {
            $best      = $count;
            $delimiter = $d;
        }
    }

    rewind($handle);
    $matrix = [];
    while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
        if ($row === [null]) {
            continue;
        }
        $matrix[] = array_map(function ($v) {
            return trim((string) $v);
        }, $row);
        if (count($matrix) > SMS_WA_EMAIL_IMPORT_MAX_ROWS + 50) {
            break;
        }
    }
    fclose($handle);

    if (isset($matrix[0][0])) {
        $matrix[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $matrix[0][0]);
    }

    return $matrix;
}

function sms_wa_email_import_read_xlsx($path)
{
    if (!class_exists('ZipArchive')) {
        throw new Exception('Excel files cannot be read on this server (the PHP zip extension is missing). Please upload a CSV instead.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new Exception('That file is not a readable .xlsx workbook. If it was saved as .xls, re-save it as .xlsx or CSV.');
    }

    try {
        $shared    = sms_wa_email_import_shared_strings($zip);
        $sheetPath = sms_wa_email_import_first_sheet_path($zip);
        $sheetXml  = $zip->getFromName($sheetPath);
        if ($sheetXml === false) {
            throw new Exception('No worksheet was found inside the workbook.');
        }
        $matrix = sms_wa_email_import_parse_sheet($sheetXml, $shared);
    } finally {
        $zip->close();
    }

    return $matrix;
}

function sms_wa_email_import_load_xml($data)
{
    if (trim((string) $data) === '') {
        return false;
    }

    $previous = libxml_use_internal_errors(true);
    if (function_exists('libxml_disable_entity_loader') && PHP_VERSION_ID < 80000) {
        libxml_disable_entity_loader(true);
    }

    $xml = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NONET);

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $xml;
}

function sms_wa_email_import_si_text($si)
{
    if (isset($si->t)) {
        return (string) $si->t;
    }

    $text = '';
    foreach ($si->r as $run) {
        $text .= (string) $run->t;
    }

    return $text;
}

function sms_wa_email_import_shared_strings(ZipArchive $zip)
{
    $data = $zip->getFromName('xl/sharedStrings.xml');
    if ($data === false) {
        return [];
    }

    $xml = sms_wa_email_import_load_xml($data);
    if ($xml === false) {
        return [];
    }

    $strings = [];
    foreach ($xml->si as $si) {
        $strings[] = sms_wa_email_import_si_text($si);
    }

    return $strings;
}

/**
 * The first sheet is not always xl/worksheets/sheet1.xml — resolve it through
 * the workbook relationships.
 */
function sms_wa_email_import_first_sheet_path(ZipArchive $zip)
{
    $default = 'xl/worksheets/sheet1.xml';

    $wbData   = $zip->getFromName('xl/workbook.xml');
    $relsData = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wbData === false || $relsData === false) {
        return $default;
    }

    $wb   = sms_wa_email_import_load_xml($wbData);
    $rels = sms_wa_email_import_load_xml($relsData);
    if ($wb === false || $rels === false || !isset($wb->sheets->sheet[0])) {
        return $default;
    }

    $attrs = $wb->sheets->sheet[0]->attributes('r', true);
    $rid   = $attrs ? (string) $attrs->id : '';
    if ($rid === '') {
        return $default;
    }

    foreach ($rels->Relationship as $rel) {
        if ((string) $rel['Id'] === $rid) {
            $target = ltrim((string) $rel['Target'], '/');
            if (strpos($target, 'xl/') !== 0) {
                $target = 'xl/' . $target;
            }

            return $target;
        }
    }

    return $default;
}

function sms_wa_email_import_parse_sheet($sheetXml, array $shared)
{
    $xml = sms_wa_email_import_load_xml($sheetXml);
    if ($xml === false || !isset($xml->sheetData)) {
        return [];
    }

    $matrix = [];
    foreach ($xml->sheetData->row as $row) {
        $cells   = [];
        $autoCol = 0;

        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            $col = $ref !== '' ? sms_wa_email_import_ref_to_index($ref) : $autoCol;
            if ($col < 0) {
                $col = $autoCol;
            }
            $autoCol      = $col + 1;
            $cells[$col]  = trim(sms_wa_email_import_cell_value($c, $shared));
        }

        if (empty($cells)) {
            $matrix[] = [];
            continue;
        }

        $max   = max(array_keys($cells));
        $dense = [];
        for ($i = 0; $i <= $max; $i++) {
            $dense[$i] = isset($cells[$i]) ? $cells[$i] : '';
        }
        $matrix[] = $dense;

        if (count($matrix) > SMS_WA_EMAIL_IMPORT_MAX_ROWS + 50) {
            break;
        }
    }

    return $matrix;
}

function sms_wa_email_import_cell_value($c, array $shared)
{
    $type = (string) $c['t'];

    if ($type === 's') {
        $idx = (int) $c->v;

        return isset($shared[$idx]) ? $shared[$idx] : '';
    }

    if ($type === 'inlineStr') {
        return isset($c->is) ? sms_wa_email_import_si_text($c->is) : '';
    }

    if ($type === 'str') {
        return (string) $c->v;
    }

    return isset($c->v) ? (string) $c->v : '';
}

/**
 * "B12" → 1 (zero-based column index).
 */
function sms_wa_email_import_ref_to_index($ref)
{
    $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $ref));
    if ($letters === '') {
        return -1;
    }

    $index = 0;
    $len   = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord($letters[$i]) - 64);
    }

    return $index - 1;
}

/* ═══════════════════════════════════════════════════════════════════
 * Preparation & duplicate detection
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * Everything already stored for this channel, indexed for duplicate lookups.
 *
 * @return array ['ids' => [...], 'contents' => [...], 'titles' => [...], 'count' => int]
 */
function sms_wa_email_import_existing_index($type)
{
    $CI    = &get_instance();
    $index = ['ids' => [], 'contents' => [], 'titles' => [], 'count' => 0];

    $rows = $CI->db
        ->select('title, content, msg_template_id')
        ->where('type', $type)
        ->get(db_prefix() . 'sms_wa_email_templates')
        ->result();

    foreach ($rows as $row) {
        $index['count']++;

        $id = strtolower(trim((string) $row->msg_template_id));
        if ($id !== '') {
            $index['ids'][$id] = true;
        }

        $ck = sms_wa_email_import_content_key($row->content);
        if ($ck !== '') {
            $index['contents'][$ck] = true;
        }

        $index['titles'][strtolower(trim((string) $row->title))] = true;
    }

    return $index;
}

/**
 * Turn a raw matrix into reviewable import rows.
 *
 * Each row comes back with a status:
 *   new       — safe to import
 *   duplicate — the Msg Template ID or the message body is already stored,
 *               or repeats earlier in the same file
 *   invalid   — the mandatory Msg Template ID or message body is missing
 *
 * @param  array  $matrix
 * @param  string $type   Template channel (sms / whatsapp)
 * @return array
 */
function sms_wa_email_import_prepare(array $matrix, $type)
{
    $header = sms_wa_email_import_locate_header($matrix);

    if ($header['row'] === null || !isset($header['map']['msg_template_id']) || !isset($header['map']['content'])) {
        return [
            'success' => false,
            'message' => 'Could not find the required columns. The sheet must have a "message" column (Msg Template ID) and an "approved_message" column (Template Content). A "sender_id" column (Header ID) is used when present.',
            'found'   => $header['labels'],
        ];
    }

    $existing = sms_wa_email_import_existing_index($type);
    $used     = $existing['titles'];
    $map      = $header['map'];

    $seenIds      = [];
    $seenContents = [];
    $rows         = [];
    $summary      = ['new' => 0, 'duplicate' => 0, 'invalid' => 0];
    $truncated    = false;

    $total = count($matrix);
    for ($r = $header['row'] + 1; $r < $total; $r++) {
        if (count($rows) >= SMS_WA_EMAIL_IMPORT_MAX_ROWS) {
            $truncated = true;
            break;
        }

        $raw = $matrix[$r];
        $get = function ($field) use ($raw, $map) {
            return isset($map[$field], $raw[$map[$field]]) ? trim((string) $raw[$map[$field]]) : '';
        };

        $msgId   = $get('msg_template_id');
        $header_ = $get('header_id');
        $content = $get('content');

        // Skip a repeated header row (some exports paginate the header)
        if (sms_wa_email_import_norm_key($msgId) !== ''
            && in_array(sms_wa_email_import_norm_key($msgId), sms_wa_email_import_column_aliases()['msg_template_id'], true)) {
            continue;
        }

        if ($msgId === '' && $header_ === '' && $content === '') {
            continue;
        }

        $status = 'new';
        $reason = '';

        if ($content === '') {
            $status = 'invalid';
            $reason = 'No message content in this row';
        } elseif ($msgId === '') {
            $status = 'invalid';
            $reason = 'Msg Template ID is empty';
        } elseif (preg_match('/^[0-9.]+e\+?[0-9]+$/i', $msgId)) {
            $status = 'invalid';
            $reason = 'Msg Template ID exported in scientific notation — format that column as Text in Excel and re-export';
        }

        $idKey      = strtolower($msgId);
        $contentKey = sms_wa_email_import_content_key($content);

        if ($status === 'new') {
            if (isset($seenIds[$idKey])) {
                $status = 'duplicate';
                $reason = 'Repeated in this file (row ' . $seenIds[$idKey] . ')';
            } elseif ($contentKey !== '' && isset($seenContents[$contentKey])) {
                $status = 'duplicate';
                $reason = 'Same message already in this file (row ' . $seenContents[$contentKey] . ')';
            } elseif (isset($existing['ids'][$idKey])) {
                $status = 'duplicate';
                $reason = 'Msg Template ID already exists';
            } elseif ($contentKey !== '' && isset($existing['contents'][$contentKey])) {
                $status = 'duplicate';
                $reason = 'An identical template already exists';
            }
        }

        if ($status === 'new') {
            $seenIds[$idKey]           = $r + 1;
            $seenContents[$contentKey] = $r + 1;
        }

        // Skipped rows get a name too — it is what the preview shows — but it
        // must not reserve a title that a row actually being imported wants.
        $scratch = [];
        $title   = $status === 'new'
            ? sms_wa_email_import_title($content, $used)
            : sms_wa_email_import_title($content, $scratch);

        $rows[] = [
            'row'             => $r + 1,
            'title'           => $title !== '' ? $title : 'SMS Template',
            'msg_template_id' => $msgId,
            'header_id'       => $header_,
            'content'         => $content,
            'message_subtype' => sms_wa_email_import_subtype($header_),
            'status'          => $status,
            'reason'          => $reason,
        ];

        $summary[$status]++;
    }

    return [
        'success'   => true,
        'columns'   => $header['labels'],
        'header_row' => $header['row'] + 1,
        'rows'      => $rows,
        'summary'   => $summary,
        'truncated' => $truncated,
        'max_rows'  => SMS_WA_EMAIL_IMPORT_MAX_ROWS,
    ];
}
