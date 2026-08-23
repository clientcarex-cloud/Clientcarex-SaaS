<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Format-agnostic sheet reader for the lead importer.
 *
 * Give it the raw bytes of whatever a manager uploaded — a Meta / Instagram
 * lead export (UTF-16 tab separated), a Google Sheets CSV, an Excel "save as
 * CSV", a hand-typed list — and it returns clean UTF-8 rows plus a guess of
 * what every column means. The guess is only a starting point: the import
 * screen lets the manager re-map any column, and the model remembers those
 * choices so the same file shape maps itself next time.
 *
 * Deliberately static and CRM-free so it can be reasoned about (and tested)
 * on its own; anything needing the database lives in Shra_leads_model.
 */
class Shra_import
{
    /** Column meanings the importer understands, in menu order. */
    public static function targets()
    {
        return [
            'ignore'     => '— Skip —',
            'name'       => 'Name',
            'first_name' => 'First name',
            'last_name'  => 'Last name',
            'phone'      => 'Phone',
            'email'      => 'Email',
            'city'       => 'City',
            'address'    => 'Address',
            'source'     => 'Source',
            'agent'      => 'Assigned agent',
            'campaign'   => 'Campaign',
            'interest'   => 'Interest / package',
            'rider_for'  => 'Rider is (self / child)',
            'age'        => 'Rider age',
            'created_at' => 'Created date',
            'notes'      => 'Notes',
            'extra'      => 'Notes (with column name)',
        ];
    }

    /** Targets that hold a single value — a second column claiming one becomes a note. */
    public static function single_targets()
    {
        return ['name', 'first_name', 'last_name', 'phone', 'email', 'city', 'address',
            'source', 'agent', 'campaign', 'interest', 'rider_for', 'age', 'created_at'];
    }

    /** Header wording we recognise. Longer phrases win over shorter ones. */
    private static function synonyms()
    {
        return [
            'name'       => ['full name', 'name', 'lead name', 'contact name', 'customer name', 'client name', 'your name', 'parent name', 'student name', 'nombre'],
            'first_name' => ['first name', 'firstname', 'given name', 'fname'],
            'last_name'  => ['last name', 'lastname', 'surname', 'family name', 'lname'],
            'phone'      => ['phone number', 'phone', 'mobile number', 'mobile no', 'mobile', 'whatsapp number', 'whatsapp', 'contact number', 'contact no', 'cell phone', 'cell', 'telephone', 'tel', 'msisdn', 'number'],
            'email'      => ['email address', 'email id', 'email', 'e mail', 'mail id', 'mail'],
            'city'       => ['city', 'town', 'location', 'area', 'locality'],
            'address'    => ['address', 'street address', 'street'],
            'source'     => ['lead source', 'source', 'platform', 'channel', 'utm source', 'medium'],
            'agent'      => ['assigned to', 'assigned', 'agent', 'owner', 'staff', 'sales person', 'salesperson', 'counsellor', 'counselor', 'executive'],
            'campaign'   => ['campaign name', 'campaign', 'utm campaign'],
            'interest'   => ['what are you interested in', 'interested in', 'interest', 'package', 'course', 'programme', 'program', 'service', 'looking for'],
            'rider_for'  => ['who is interested', 'who is interested in horse riding', 'for whom', 'rider for', 'who will ride', 'participant', 'learner'],
            'age'        => ['rider age', 'child age', 'student age', 'age'],
            'created_at' => ['created time', 'created at', 'creation time', 'date added', 'date created', 'submitted on', 'submission date', 'lead date', 'timestamp', 'created', 'datetime', 'date'],
            'notes'      => ['notes', 'note', 'message', 'comments', 'comment', 'remarks', 'description', 'query', 'enquiry', 'inquiry', 'requirement'],
        ];
    }

    /** Words too generic to match on their own inside a longer header ("ad name" is not a name). */
    private static $generic = ['name', 'number', 'date', 'created', 'mail', 'ad', 'age', 'area', 'place', 'medium', 'cell', 'tel', 'staff', 'owner', 'service'];

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
        foreach (explode("\n", $text) as $l) {
            if (trim($l) !== '') {
                $line = $l;
                break;
            }
        }
        $best = [',', 0];
        foreach (["\t" => 0, ',' => 0, ';' => 0, '|' => 0] as $d => $_) {
            $n  = 0;
            $in = false;
            for ($i = 0, $len = strlen($line); $i < $len; $i++) {
                if ($line[$i] === '"') {
                    $in = !$in;
                } elseif (!$in && $line[$i] === $d) {
                    $n++;
                }
            }
            if ($n > $best[1]) {
                $best = [$d, $n];
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
        $dec   = self::decode($raw);
        $text  = $dec['text'];
        $delim = $delimiter ?: self::sniff_delimiter($text);
        $rows  = [];
        $fh    = fopen('php://temp', 'r+');
        fwrite($fh, $text);
        rewind($fh);
        while (($r = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
            if ($r === [null] || (count($r) === 1 && trim((string) $r[0]) === '')) {
                continue; // blank line
            }
            $rows[] = array_map(static function ($v) {
                return self::clean($v);
            }, $r);
        }
        fclose($fh);

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
        foreach ($rows as $r) {
            $width = max($width, count($r));
        }
        for ($i = 0; $i < $width; $i++) {
            if (!isset($headers[$i]) || trim((string) $headers[$i]) === '') {
                $headers[$i] = 'Column ' . ($i + 1);
            }
        }

        return [
            'headers'    => array_values($headers),
            'rows'       => $rows,
            'delimiter'  => $delim,
            'encoding'   => $dec['encoding'],
            'has_header' => (bool) $has_header,
        ];
    }

    /** A row carrying an e-mail or a phone number is data, not column names. */
    private static function looks_like_data(array $row)
    {
        foreach ($row as $v) {
            $v = trim((string) $v);
            if ($v === '') {
                continue;
            }
            if (filter_var($v, FILTER_VALIDATE_EMAIL) || preg_match('/^[+\d][\d\s().-]{6,}$/', $v)) {
                return true;
            }
        }

        return false;
    }

    /* ═══════════════════════ Guessing what the columns mean ═══════════════════════ */

    public static function norm_header($h)
    {
        $h = mb_strtolower(trim((string) $h));
        $h = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $h);

        return trim(preg_replace('/\s+/', ' ', (string) $h));
    }

    /** Human label for a column: "what_are_you_interested_in?" → "What are you interested in". */
    public static function label($h)
    {
        $n = self::norm_header($h);

        return $n === '' ? '' : mb_strtoupper(mb_substr($n, 0, 1)) . mb_substr($n, 1);
    }

    /** [target, confidence] — 2 = the header says so, 1 = it contains a known phrase. */
    public static function guess_target($header)
    {
        $h = self::norm_header($header);
        if ($h === '' || preg_match('/^column \d+$/', $h)) {
            return ['extra', 0];
        }
        foreach (self::synonyms() as $target => $words) {
            if (in_array($h, $words, true)) {
                return [$target, 2];
            }
        }
        // Machine columns (ad_id, form_id, is_organic…) carry nothing for a CRM
        if (preg_match('/(^| )(id|ids|uid|uuid|url|link)$/', $h) || strpos($h, 'is ') === 0) {
            return ['ignore', 2];
        }
        $best = ['extra', 0, 0];
        foreach (self::synonyms() as $target => $words) {
            foreach ($words as $w) {
                if (in_array($w, self::$generic, true) || strlen($w) <= $best[2]) {
                    continue;
                }
                if (preg_match('/(^|\s)' . preg_quote($w, '/') . '($|\s)/', $h)
                    && (strpos($h, $w) === 0 || substr($h, -strlen($w)) === $w)) {
                    $best = [$target, 1, strlen($w)];
                }
            }
        }

        return [$best[0], $best[1]];
    }

    /**
     * Whole-sheet mapping: column index → target. $learned (normalised header →
     * target) wins over the built-in wording, and columns still unclaimed are
     * sniffed from their own values.
     */
    public static function guess_map(array $headers, array $rows = [], array $learned = [], $has_header = true)
    {
        $map   = [];
        $score = [];
        foreach ($headers as $i => $h) {
            $n = self::norm_header($h);
            if ($n !== '' && isset($learned[$n])) {
                $map[$i]   = $learned[$n];
                $score[$i] = 3;
                continue;
            }
            [$map[$i], $score[$i]] = self::guess_target($h);
        }
        if (!$has_header) {
            // Positional fallback, the shape the old importer accepted
            foreach (['name', 'phone', 'email', 'city', 'source', 'agent', 'notes'] as $i => $t) {
                if (isset($map[$i]) && $score[$i] === 0) {
                    $map[$i]   = $t;
                    $score[$i] = 1;
                }
            }
        }
        // Sniff leftovers from their values — an e-mail column is an e-mail column
        foreach ($map as $i => $t) {
            if ($t !== 'extra') {
                continue;
            }
            $sample = self::column_sample($rows, $i);
            if (!count($sample)) {
                continue;
            }
            $mail = $tel = 0;
            foreach ($sample as $v) {
                if (filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    $mail++;
                } elseif (preg_match('/^[+\d][\d\s().-]{6,}$/', $v)) {
                    $tel++;
                }
            }
            $n = count($sample);
            if ($mail >= $n * 0.6 && !in_array('email', $map, true)) {
                $map[$i]   = 'email';
                $score[$i] = 1;
            } elseif ($tel >= $n * 0.6 && !in_array('phone', $map, true)) {
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
        foreach ($map as $i => $t) {
            if (!in_array($t, $singles, true)) {
                continue;
            }
            if (!isset($keep[$t]) || $score[$i] > $score[$keep[$t]]) {
                $keep[$t] = $i;
            }
        }
        foreach ($map as $i => $t) {
            if (in_array($t, $singles, true) && $keep[$t] !== $i) {
                $map[$i] = 'extra';
            }
        }

        return $map;
    }

    private static function column_sample(array $rows, $i, $max = 20)
    {
        $out = [];
        foreach ($rows as $r) {
            $v = trim((string) ($r[$i] ?? ''));
            if ($v !== '') {
                $out[] = $v;
            }
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /* ═══════════════════════ Cleaning values ═══════════════════════ */

    /** Trim, unwrap stray quotes and drop export prefixes (Meta writes p:+91…, l:123…). */
    public static function clean($v)
    {
        $v = trim(str_replace("\xC2\xA0", ' ', (string) $v));
        if (strlen($v) > 1 && $v[0] === '"' && substr($v, -1) === '"') {
            $v = trim(substr($v, 1, -1));
        }
        $v = preg_replace('/^(id|l|p|ag|as|c|f|fb|ig):(?=[+\d])/i', '', $v);

        return trim(preg_replace('/\s+/u', ' ', (string) $v));
    }

    /** Machine answer → readable text: "evening:_4pm_-_9pm" → "Evening: 4pm - 9pm". */
    public static function humanize($v)
    {
        $v = trim(preg_replace('/\s+/u', ' ', str_replace('_', ' ', (string) $v)));
        if ($v === '' || preg_match('/\p{Lu}/u', $v)) {
            return $v; // already written for humans
        }

        return mb_strtoupper(mb_substr($v, 0, 1)) . mb_substr($v, 1);
    }

    /** Any common date/time wording → 'Y-m-d H:i:s' in the app timezone, or null. */
    public static function parse_date($v)
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^\d{9,11}$/', $v)) {
            return date('Y-m-d H:i:s', (int) $v); // unix seconds
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}([T ]|$)/', $v)) {
            $t = strtotime($v);

            return $t ? date('Y-m-d H:i:s', $t) : null;
        }
        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'j/n/Y H:i', 'j/n/Y',
            'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y', 'j-n-Y', 'd.m.Y H:i', 'd.m.Y',
            'd M Y H:i', 'd M Y', 'j M Y', 'M j, Y', 'm/d/Y H:i:s', 'm/d/Y'] as $f) {
            $d = DateTime::createFromFormat($f . '|', $v);
            if ($d instanceof DateTime && $d->format($f) === $v) {
                return $d->format('Y-m-d H:i:s');
            }
        }
        $t = strtotime($v);

        return $t ? date('Y-m-d H:i:s', $t) : null;
    }

    /** "Fayyaz Uddin" → ['Fayyaz', 'Uddin']; single word keeps the surname empty. */
    public static function split_name($full)
    {
        $parts = preg_split('/\s+/u', trim((string) $full)) ?: [];
        $first = array_shift($parts);

        return [(string) $first, implode(' ', $parts)];
    }
}
