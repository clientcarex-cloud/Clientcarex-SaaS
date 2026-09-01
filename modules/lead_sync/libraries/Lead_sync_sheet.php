<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Format-agnostic sheet reader and column guesser.
 *
 * Give it the raw bytes of whatever came back from Google — a Meta / Instagram
 * lead-ads export (UTF-16 tab separated), a Google Sheets CSV export, an Excel
 * "save as CSV", a hand-typed list — and it returns clean UTF-8 rows plus a
 * guess of what every column means. The guess is only a starting point: the
 * connection screen lets a manager re-map any column, and the choice is stored
 * on the connection so every later sync maps itself.
 *
 * Deliberately static and CRM-free so it can be reasoned about (and unit
 * tested) on its own; anything needing the database lives in Lead_sync_model.
 */
class Lead_sync_sheet
{
    /** Column meanings the importer understands, in menu order. */
    public static function targets()
    {
        return [
            'ignore'      => '— Skip —',
            'name'        => 'Name',
            'first_name'  => 'First name',
            'last_name'   => 'Last name',
            'phone'       => 'Phone',
            'email'       => 'Email',
            'company'     => 'Company',
            'title'       => 'Job position',
            'city'        => 'City',
            'state'       => 'State',
            'country'     => 'Country',
            'zip'         => 'Zip / postal code',
            'address'     => 'Address',
            'website'     => 'Website',
            'source'      => 'Lead source',
            'status'      => 'Lead status',
            'assigned'    => 'Assigned agent',
            'tags'        => 'Tags',
            'lead_value'  => 'Lead value',
            'created_at'  => 'Created date',
            'description' => 'Notes / description',
            'extra'       => 'Notes (prefixed with the column name)',
        ];
    }

    /** Targets that hold a single value — a second column claiming one becomes a note. */
    public static function single_targets()
    {
        return ['name', 'first_name', 'last_name', 'phone', 'email', 'company', 'title',
            'city', 'state', 'country', 'zip', 'address', 'website', 'source', 'status',
            'assigned', 'tags', 'lead_value', 'created_at'];
    }

    /** Header wording we recognise. Longer phrases win over shorter ones. */
    private static function synonyms()
    {
        return [
            'name'        => ['full name', 'name', 'lead name', 'contact name', 'customer name', 'client name', 'your name', 'full_name', 'nombre'],
            'first_name'  => ['first name', 'firstname', 'given name', 'fname'],
            'last_name'   => ['last name', 'lastname', 'surname', 'family name', 'lname'],
            'phone'       => ['phone number', 'phone', 'mobile number', 'mobile no', 'mobile', 'whatsapp number', 'whatsapp', 'contact number', 'contact no', 'cell phone', 'cell', 'telephone', 'tel', 'msisdn', 'number'],
            'email'       => ['email address', 'email id', 'email', 'e mail', 'mail id', 'work email', 'mail'],
            'company'     => ['company name', 'company', 'organisation', 'organization', 'business name', 'firm'],
            'title'       => ['job title', 'designation', 'position', 'role'],
            'city'        => ['city', 'town', 'location', 'area', 'locality'],
            'state'       => ['state', 'province', 'region'],
            'country'     => ['country'],
            'zip'         => ['zip code', 'zip', 'postal code', 'postcode', 'pin code', 'pincode'],
            'address'     => ['street address', 'address', 'street'],
            'website'     => ['website', 'web site', 'url', 'company website'],
            'source'      => ['lead source', 'source', 'platform', 'channel', 'utm source', 'medium'],
            'status'      => ['lead status', 'status', 'stage'],
            'assigned'    => ['assigned to', 'assigned', 'agent', 'owner', 'staff', 'sales person', 'salesperson', 'counsellor', 'counselor', 'executive'],
            'tags'        => ['tags', 'tag', 'labels'],
            'lead_value'  => ['lead value', 'deal value', 'value', 'budget', 'expected value', 'amount'],
            'created_at'  => ['created time', 'created at', 'creation time', 'date added', 'date created', 'submitted on', 'submission date', 'lead date', 'timestamp', 'created', 'datetime', 'date'],
            'description' => ['notes', 'note', 'message', 'comments', 'comment', 'remarks', 'description', 'query', 'enquiry', 'inquiry', 'requirement'],
        ];
    }

    /**
     * Qualifiers that make "<x> name" a machine or marketing label rather than
     * the person's name. Meta ships ad_name, campaign_name, adset_name and
     * form_name in every export, and none of them is a lead's name.
     */
    private static $not_person = ['ad', 'ads', 'ad set', 'adset', 'campaign', 'form', 'page', 'account',
        'business', 'company', 'organisation', 'organization', 'file', 'sheet', 'tab', 'column', 'field',
        'tag', 'label', 'product', 'item', 'service', 'event', 'source', 'status', 'stage', 'user', 'group',
        'job', 'role', 'department', 'team', 'project', 'task', 'template', 'plan', 'package', 'course',
        'batch', 'brand', 'store', 'branch', 'bank', 'location', 'city', 'country', 'state', 'domain', 'display'];

    /** Words too generic to match on their own inside a longer header ("ad name" is not a name). */
    private static $generic = ['name', 'number', 'date', 'created', 'mail', 'ad', 'area', 'place',
        'medium', 'cell', 'tel', 'staff', 'owner', 'value', 'amount', 'status', 'url', 'source'];

    /* ═══════════════════════ Reading the file ═══════════════════════ */

    /**
     * Bytes → UTF-8 text. Handles UTF-16/32 (with or without BOM, as Meta and
     * Excel export), UTF-8 BOM and legacy Windows-1252 sheets.
     */
    public static function decode($raw)
    {
        $raw = (string) $raw;
        $enc = 'UTF-8';

        if (strncmp($raw, "\xFF\xFE\x00\x00", 4) === 0) {
            $raw = substr($raw, 4);
            $enc = 'UTF-32LE';
        } elseif (strncmp($raw, "\x00\x00\xFE\xFF", 4) === 0) {
            $raw = substr($raw, 4);
            $enc = 'UTF-32BE';
        } elseif (strncmp($raw, "\xFF\xFE", 2) === 0) {
            $raw = substr($raw, 2);
            $enc = 'UTF-16LE';
        } elseif (strncmp($raw, "\xFE\xFF", 2) === 0) {
            $raw = substr($raw, 2);
            $enc = 'UTF-16BE';
        } elseif (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $raw = substr($raw, 3);
        } else {
            // No BOM: a sheet full of NUL bytes is UTF-16 saved without one
            $head = substr($raw, 0, 512);
            if (strlen($head) > 8 && substr_count($head, "\x00") > strlen($head) / 4) {
                $odd = 0;
                for ($i = 1; $i < strlen($head); $i += 2) {
                    if ($head[$i] === "\x00") {
                        $odd++;
                    }
                }
                $enc = $odd > strlen($head) / 5 ? 'UTF-16LE' : 'UTF-16BE';
            } elseif (!mb_check_encoding($raw, 'UTF-8')) {
                $enc = 'Windows-1252';
            }
        }

        if ($enc !== 'UTF-8') {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', $enc);
        }
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $raw = substr($raw, 3);
        }

        $raw = str_replace(["\x00", "\r\n", "\r"], ['', "\n", "\n"], $raw);

        return ['text' => $raw, 'encoding' => $enc];
    }

    /** Pick the separator by counting candidates outside quotes on the header line. */
    public static function sniff_delimiter($text)
    {
        $line = '';
        foreach (explode("\n", $text) as $candidate) {
            if (trim($candidate) !== '') {
                $line = $candidate;
                break;
            }
        }

        $best = [',', 0];
        foreach (["\t", ',', ';', '|'] as $delimiter) {
            $count  = 0;
            $quoted = false;
            for ($i = 0, $len = strlen($line); $i < $len; $i++) {
                if ($line[$i] === '"') {
                    $quoted = !$quoted;
                } elseif (!$quoted && $line[$i] === $delimiter) {
                    $count++;
                }
            }
            if ($count > $best[1]) {
                $best = [$delimiter, $count];
            }
        }

        return $best[0];
    }

    /**
     * Read a sheet into ['headers','rows','delimiter','encoding','has_header'].
     * $has_header = null lets it decide (a first row without any e-mail or
     * phone number in it is treated as column names).
     */
    public static function parse($raw, $has_header = null, $delimiter = null)
    {
        $decoded   = self::decode($raw);
        $text      = $decoded['text'];
        $delimiter = $delimiter ?: self::sniff_delimiter($text);
        $rows      = [];

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $text);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($row === [null] || trim(implode('', array_map('strval', $row))) === '') {
                continue; // blank line, or a row of empty cells left behind by a delete
            }
            $rows[] = array_map(static function ($value) {
                return self::clean($value);
            }, $row);
        }
        fclose($handle);

        $headers = [];
        if (count($rows)) {
            if ($has_header === null) {
                $has_header = !self::looks_like_data($rows[0]);
            }
            if ($has_header) {
                $headers = array_shift($rows);
            }
        }

        $width = count($headers);
        foreach ($rows as $row) {
            $width = max($width, count($row));
        }
        for ($i = 0; $i < $width; $i++) {
            if (!isset($headers[$i]) || trim((string) $headers[$i]) === '') {
                $headers[$i] = 'Column ' . ($i + 1);
            }
        }

        return [
            'headers'    => array_values($headers),
            'rows'       => $rows,
            'delimiter'  => $delimiter,
            'encoding'   => $decoded['encoding'],
            'has_header' => (bool) $has_header,
        ];
    }

    /** Rows already split into cells (the webhook posts JSON, not CSV). */
    public static function from_values(array $headers, array $rows)
    {
        $headers = array_map(static function ($value) {
            return self::clean($value);
        }, $headers);

        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row = array_map(static function ($value) {
                return self::clean(is_scalar($value) ? $value : '');
            }, array_values($row));

            if (trim(implode('', $row)) === '') {
                continue;
            }
            $clean[] = $row;
        }

        $width = count($headers);
        foreach ($clean as $row) {
            $width = max($width, count($row));
        }
        for ($i = 0; $i < $width; $i++) {
            if (!isset($headers[$i]) || trim((string) $headers[$i]) === '') {
                $headers[$i] = 'Column ' . ($i + 1);
            }
        }

        return [
            'headers'    => array_values($headers),
            'rows'       => $clean,
            'delimiter'  => ',',
            'encoding'   => 'UTF-8',
            'has_header' => true,
        ];
    }

    /** A row carrying an e-mail or a phone number is data, not column names. */
    private static function looks_like_data(array $row)
    {
        foreach ($row as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            if (filter_var($value, FILTER_VALIDATE_EMAIL) || preg_match('/^[+\d][\d\s().-]{6,}$/', $value)) {
                return true;
            }
        }

        return false;
    }

    /* ═══════════════════ Guessing what the columns mean ═══════════════════ */

    public static function norm_header($header)
    {
        $header = mb_strtolower(trim((string) $header));
        $header = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $header);

        return trim(preg_replace('/\s+/', ' ', (string) $header));
    }

    /** Human label for a column: "what_are_you_interested_in?" → "What are you interested in". */
    public static function label($header)
    {
        $normalised = self::norm_header($header);

        return $normalised === '' ? '' : mb_strtoupper(mb_substr($normalised, 0, 1)) . mb_substr($normalised, 1);
    }

    /** [target, confidence] — 2 = the header says so, 1 = it contains a known phrase. */
    public static function guess_target($header)
    {
        $normalised = self::norm_header($header);

        if ($normalised === '' || preg_match('/^column \d+$/', $normalised)) {
            return ['extra', 0];
        }

        foreach (self::synonyms() as $target => $words) {
            if (in_array($normalised, $words, true)) {
                return [$target, 2];
            }
        }

        // Machine columns Meta ships in every export (ad_id, form_id, is_organic,
        // lead_id, retailer_item_id…) carry nothing a CRM can use.
        if (preg_match('/(^| )(id|ids|uid|uuid|url|link)$/', $normalised) || strpos($normalised, 'is ') === 0) {
            return ['ignore', 2];
        }

        // "Student name", "Patient name", "Applicant name", "Parent name" — any
        // qualifier that is not a machine label still names the person. Kept at
        // confidence 1 so an explicit "Full name" column elsewhere still wins.
        if (preg_match('/^(.+) name$/', $normalised, $matched)
            && !in_array($matched[1], self::$not_person, true)) {
            return ['name', 1];
        }

        $best = ['extra', 0, 0];
        foreach (self::synonyms() as $target => $words) {
            foreach ($words as $word) {
                if (in_array($word, self::$generic, true) || strlen($word) <= $best[2]) {
                    continue;
                }
                if (preg_match('/(^|\s)' . preg_quote($word, '/') . '($|\s)/', $normalised)
                    && (strpos($normalised, $word) === 0 || substr($normalised, -strlen($word)) === $word)) {
                    $best = [$target, 1, strlen($word)];
                }
            }
        }

        return [$best[0], $best[1]];
    }

    /**
     * Whole-sheet mapping: column index → target. $saved (normalised header →
     * target, from the connection) wins over the built-in wording, and columns
     * still unclaimed are sniffed from their own values.
     */
    public static function guess_map(array $headers, array $rows = [], array $saved = [], $has_header = true)
    {
        $map   = [];
        $score = [];

        foreach ($headers as $i => $header) {
            $normalised = self::norm_header($header);
            if ($normalised !== '' && isset($saved[$normalised])) {
                $map[$i]   = $saved[$normalised];
                $score[$i] = 3;
                continue;
            }
            [$map[$i], $score[$i]] = self::guess_target($header);
        }

        if (!$has_header) {
            // Positional fallback for a sheet with no header row at all
            foreach (['name', 'phone', 'email', 'city', 'source', 'assigned', 'description'] as $i => $target) {
                if (isset($map[$i]) && $score[$i] === 0) {
                    $map[$i]   = $target;
                    $score[$i] = 1;
                }
            }
        }

        // Sniff leftovers from their values — an e-mail column is an e-mail column
        foreach ($map as $i => $target) {
            if ($target !== 'extra') {
                continue;
            }
            $sample = self::column_sample($rows, $i);
            if (!count($sample)) {
                continue;
            }
            $mail = $tel = 0;
            foreach ($sample as $value) {
                if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $mail++;
                } elseif (preg_match('/^[+\d][\d\s().-]{6,}$/', $value)) {
                    $tel++;
                }
            }
            $total = count($sample);
            if ($mail >= $total * 0.6 && !in_array('email', $map, true)) {
                $map[$i]   = 'email';
                $score[$i] = 1;
            } elseif ($tel >= $total * 0.6 && !in_array('phone', $map, true)) {
                $map[$i]   = 'phone';
                $score[$i] = 1;
            }
        }

        return self::resolve_conflicts($map, $score);
    }

    /** Two columns cannot both be "Phone" — the more confident one keeps it. */
    private static function resolve_conflicts(array $map, array $score)
    {
        $singles = self::single_targets();
        $keep    = [];

        foreach ($map as $i => $target) {
            if (!in_array($target, $singles, true)) {
                continue;
            }
            if (!isset($keep[$target]) || $score[$i] > $score[$keep[$target]]) {
                $keep[$target] = $i;
            }
        }
        foreach ($map as $i => $target) {
            if (in_array($target, $singles, true) && $keep[$target] !== $i) {
                $map[$i] = 'extra';
            }
        }

        return $map;
    }

    private static function column_sample(array $rows, $index, $max = 20)
    {
        $out = [];

        foreach ($rows as $row) {
            $value = trim((string) ($row[$index] ?? ''));
            if ($value !== '') {
                $out[] = $value;
            }
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /* ═══════════════════════ Cleaning values ═══════════════════════ */

    /** Trim, unwrap stray quotes and drop export prefixes (Meta writes p:+91…, l:123…). */
    public static function clean($value)
    {
        $value = trim(str_replace("\xC2\xA0", ' ', (string) $value));

        if (strlen($value) > 1 && $value[0] === '"' && substr($value, -1) === '"') {
            $value = trim(substr($value, 1, -1));
        }
        $value = preg_replace('/^(id|l|p|ag|as|c|f|fb|ig):(?=[+\d])/i', '', $value);

        return trim(preg_replace('/\s+/u', ' ', (string) $value));
    }

    /** Machine answer → readable text: "evening:_4pm_-_9pm" → "Evening: 4pm - 9pm". */
    public static function humanize($value)
    {
        $value = trim(preg_replace('/\s+/u', ' ', str_replace('_', ' ', (string) $value)));

        if ($value === '' || preg_match('/\p{Lu}/u', $value)) {
            return $value; // already written for humans
        }

        return mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1);
    }

    /** Any common date/time wording → 'Y-m-d H:i:s' in the app timezone, or null. */
    public static function parse_date($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{9,11}$/', $value)) {
            return date('Y-m-d H:i:s', (int) $value); // unix seconds
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}([T ]|$)/', $value)) {
            $time = strtotime($value);

            return $time ? date('Y-m-d H:i:s', $time) : null;
        }
        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'j/n/Y H:i', 'j/n/Y',
            'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y', 'j-n-Y', 'd.m.Y H:i', 'd.m.Y',
            'd M Y H:i', 'd M Y', 'j M Y', 'M j, Y', 'm/d/Y H:i:s', 'm/d/Y'] as $format) {
            $date = DateTime::createFromFormat($format . '|', $value);
            if ($date instanceof DateTime && $date->format($format) === $value) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        $time = strtotime($value);

        return $time ? date('Y-m-d H:i:s', $time) : null;
    }

    /** "Fayyaz Uddin" → ['Fayyaz', 'Uddin']; a single word keeps the surname empty. */
    public static function split_name($full)
    {
        $parts = preg_split('/\s+/u', trim((string) $full)) ?: [];
        $first = array_shift($parts);

        return [(string) $first, implode(' ', $parts)];
    }

    /**
     * Stable identity for a sheet row, so the same line is never imported twice
     * however the sheet is later sorted, filtered or re-exported. Built from the
     * values alone — column order changes do not create a "new" row.
     */
    public static function row_hash(array $headers, array $row)
    {
        $pairs = [];

        foreach ($row as $i => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $pairs[] = self::norm_header($headers[$i] ?? ('column ' . $i)) . '=' . mb_strtolower($value);
        }
        sort($pairs);

        return sha1(implode('|', $pairs));
    }
}
