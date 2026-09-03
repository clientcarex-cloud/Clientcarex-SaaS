<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ePTW — read the existing Excel / CSV permit register.
 *
 * A tiny .xlsx reader (ZipArchive + XML) is included on purpose: there is no
 * spreadsheet library in this install, and the register the client already
 * keeps is an .xlsx. Only what a register needs is supported — strings,
 * numbers, dates, the first sheet — which is all it contains.
 */
class Eptw_import
{
    /**
     * @return array{headers: array, rows: array}|string  error message on failure
     */
    public static function read($path, $original_name)
    {
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            $table = self::read_xlsx($path);
        } elseif (in_array($ext, ['csv', 'txt', 'tsv'], true)) {
            $table = self::read_csv($path);
        } else {
            return 'Upload the register as .xlsx or .csv.';
        }
        if (is_string($table)) {
            return $table;
        }

        // The header row is the first row that contains "Permit" somewhere;
        // the Excel register has a title row above it.
        $header_index = null;
        foreach ($table as $i => $row) {
            $joined = mb_strtolower(implode(' ', array_map('strval', $row)));
            if (strpos($joined, 'permit') !== false && strpos($joined, 'type') !== false) {
                $header_index = $i;
                break;
            }
            if ($i > 10) {
                break;
            }
        }
        if ($header_index === null) {
            $header_index = 0;
        }

        $headers = array_map(function ($h) {
            return trim(preg_replace('/\s+/', ' ', (string) $h));
        }, $table[$header_index]);
        $rows = array_slice($table, $header_index + 1);
        $rows = array_values(array_filter($rows, function ($row) {
            foreach ($row as $cell) {
                if (trim((string) $cell) !== '') {
                    return true;
                }
            }

            return false;
        }));

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** Header text → permit field. Unknown headers are kept in the remarks. */
    public static function map_headers(array $headers)
    {
        $map = [];
        foreach ($headers as $i => $h) {
            $k = mb_strtolower(trim($h));
            $k = preg_replace('/[^a-z0-9]+/', ' ', $k);
            $k = trim($k);
            $target = '';
            foreach ([
                'permit_no'         => ['permit id', 'permit no', 'permit number', 'ptw no', 'ptw id'],
                'type'              => ['permit type', 'type of permit', 'type'],
                'work_order'        => ['work order', 'wo no', 'work order no'],
                'project'           => ['project package', 'project', 'package'],
                'company'           => ['company', 'contractor', 'main contractor'],
                'subcontractor'     => ['subcontractor', 'sub contractor'],
                'work_description'  => ['work description', 'description', 'scope', 'work title'],
                'location'          => ['location', 'area', 'area zone', 'zone'],
                'equipment_tag'     => ['equipment tag no', 'equipment tag', 'tag no', 'equipment'],
                'start_at'          => ['start date', 'start', 'start date time', 'from'],
                'end_at'            => ['end date', 'end', 'end date time', 'to', 'expiry'],
                'shift'             => ['shift'],
                'initiator'         => ['initiator', 'engineer', 'performing authority', 'requested by'],
                'area_authority'    => ['area authority', 'aa'],
                'issuer'            => ['permit issuer', 'issuer', 'coordinator', 'ptw coordinator'],
                'approver'          => ['permit approver', 'approver'],
                'permit_holder'     => ['permit holder', 'holder'],
                'hse'               => ['hse officer', 'hse', 'safety officer'],
                'ra_ref'            => ['ra jsa ref no', 'ra jsa', 'jsa', 'risk assessment', 'ra ref'],
                'hazards'           => ['hazard category', 'hazards', 'hazard'],
                'controls'          => ['control measures', 'controls'],
                'ppe'               => ['ppe required', 'ppe'],
                'isolation_required' => ['isolation required'],
                'isolation_type'    => ['isolation type'],
                'isolation_cert_no' => ['isolation certificate no', 'isolation certificate', 'isolation cert'],
                'loto'              => ['loto applied', 'loto'],
                'gas_test'          => ['gas test required', 'gas test'],
                'gas_tester'        => ['gas tester'],
                'o2'                => ['o2 level', 'o2'],
                'lel'               => ['lel', 'lel percent'],
                'h2s'               => ['h2s co', 'h2s'],
                'weather'           => ['weather condition', 'weather'],
                'simops'            => ['simops conflict', 'simops'],
                'remarks'           => ['remarks', 'notes', 'comments', 'audit remarks'],
                'status'            => ['status'],
                'extension_count'   => ['extension count', 'extensions'],
                'work_completed'    => ['work completed'],
                'area_restored'     => ['area restored', 'area clean'],
                'isolation_removed' => ['isolation removed'],
                'closed_by'         => ['closed by'],
                'closed_at'         => ['closure date', 'closed date', 'closed on'],
                'risk_level'        => ['risk level', 'risk'],
                'delay_reason'      => ['delay reason'],
            ] as $field => $names) {
                if (in_array($k, $names, true)) {
                    $target = $field;
                    break;
                }
            }
            if ($target !== '' && !in_array($target, $map, true)) {
                $map[$i] = $target;
            }
        }

        return $map;
    }

    public static function to_datetime($value)
    {
        $value = trim((string) $value);
        if ($value === '' || strtoupper($value) === 'N/A') {
            return null;
        }
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            // Excel serial date.
            $ts = ((float) $value - 25569) * 86400;

            return date('Y-m-d H:i:00', (int) round($ts));
        }
        $value = str_replace(['.', '\\'], ['/', '/'], $value);
        // dd/mm/yyyy is what the GCC registers use; PHP would read it as US. Swap when unambiguous.
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})(.*)$#', $value, $m)) {
            $value = $m[3] . '-' . $m[2] . '-' . $m[1] . $m[4];
        }
        $ts = strtotime($value);

        return $ts ? date('Y-m-d H:i:00', $ts) : null;
    }

    public static function to_status($value)
    {
        $v = mb_strtolower(trim((string) $value));
        $v = preg_replace('/[^a-z ]+/', ' ', $v);
        $v = trim(preg_replace('/\s+/', ' ', $v));
        $map = [
            'draft' => 'draft', 'requested' => 'number_requested', 'number requested' => 'number_requested', 'under review' => 'under_review', 'review' => 'under_review',
            'returned' => 'returned', 'issued' => 'issued', 'open' => 'active', 'active' => 'active', 'in progress' => 'active', 'ongoing' => 'active', 'live' => 'active',
            'extended' => 'active_extended', 'active extended' => 'active_extended', 'suspended' => 'suspended', 'on hold' => 'on_hold', 'hold' => 'on_hold', 'simops' => 'on_hold_simops',
            'closed' => 'closed', 'complete' => 'closed', 'completed' => 'closed', 'done' => 'closed', 'closed documents pending' => 'closed_docs_pending', 'docs pending' => 'closed_docs_pending',
            'cancelled' => 'cancelled', 'canceled' => 'cancelled', 'void' => 'cancelled', 'archived' => 'archived', 'expired' => 'closed',
        ];

        return $map[$v] ?? '';
    }

    /* ── xlsx ── */

    private static function read_xlsx($path)
    {
        if (!class_exists('ZipArchive')) {
            return 'This server has no ZipArchive extension; save the register as CSV and upload that instead.';
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return 'The file is not a readable .xlsx workbook.';
        }

        $shared = [];
        $xml    = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml !== false) {
            $doc = @simplexml_load_string($xml);
            if ($doc) {
                foreach ($doc->si as $si) {
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string) $si->t;
                    } else {
                        foreach ($si->r as $run) {
                            $text .= (string) $run->t;
                        }
                    }
                    $shared[] = $text;
                }
            }
        }

        // First worksheet, whatever it is named.
        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheet_xml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                    $sheet_xml = $zip->getFromName($name);
                    break;
                }
            }
        }
        $zip->close();
        if ($sheet_xml === false) {
            return 'The workbook has no worksheet.';
        }
        $doc = @simplexml_load_string($sheet_xml);
        if (!$doc) {
            return 'The worksheet could not be parsed.';
        }

        $rows = [];
        foreach ($doc->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = self::col_index(preg_replace('/\d+/', '', $ref));
                $t   = (string) $c['t'];
                $v   = isset($c->v) ? (string) $c->v : '';
                if ($t === 's') {
                    $v = $shared[(int) $v] ?? '';
                } elseif ($t === 'inlineStr') {
                    $v = isset($c->is->t) ? (string) $c->is->t : '';
                } elseif ($t === 'b') {
                    $v = $v === '1' ? 'Yes' : 'No';
                }
                $cells[$col] = $v;
            }
            if (!count($cells)) {
                $rows[] = [];
                continue;
            }
            $max  = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $max; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            $rows[] = $line;
        }

        return $rows;
    }

    private static function col_index($letters)
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }

    /* ── csv ── */

    private static function read_csv($path)
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return 'The file could not be read.';
        }
        if (strncmp($raw, "\xFF\xFE", 2) === 0 || strncmp($raw, "\xFE\xFF", 2) === 0) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
        } elseif (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $raw = substr($raw, 3);
        }
        $first = strtok($raw, "\n");
        $delim = substr_count((string) $first, "\t") > substr_count((string) $first, ',') ? "\t" : (substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',');

        $rows = [];
        $fh   = fopen('php://memory', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        while (($line = fgetcsv($fh, 0, $delim)) !== false) {
            $rows[] = array_map(function ($v) { return (string) $v; }, $line);
        }
        fclose($fh);

        return $rows;
    }
}
