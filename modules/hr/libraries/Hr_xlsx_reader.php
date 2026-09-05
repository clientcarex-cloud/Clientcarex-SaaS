<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Tiny, dependency-free XLSX reader for the HR employee importer.
 *
 * The codebase does not ship PhpSpreadsheet, so this parses a genuine
 * Office-OpenXML (.xlsx) workbook using ZipArchive + SimpleXML (both core
 * PHP), mirroring the reader half of Perfex_saas_xlsx. Only what the import
 * needs is implemented: the first worksheet as a dense matrix of cell
 * strings. Shared strings (what Excel saves to), inline strings and plain
 * numbers are understood; on anything that is not a zip it transparently
 * falls back to CSV so one code path serves both upload types.
 */
class Hr_xlsx_reader
{
    /**
     * Read the first worksheet of an .xlsx (or a .csv) into a full-width matrix.
     *
     * @param string $path Absolute path to an uploaded file
     * @return array List of rows; each row is a 0-based array of cell strings,
     *               padded so every row has the same number of columns
     * @throws \Exception When the file cannot be opened/parsed
     */
    public function read_matrix($path)
    {
        if (!is_file($path)) {
            throw new \Exception('File not found.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            $matrix = $this->read_csv($path);
        } else {
            try {
                $shared    = $this->read_shared_strings($zip);
                $sheetPath = $this->resolve_first_sheet_path($zip);
                $sheetXml  = $zip->getFromName($sheetPath);
                if ($sheetXml === false) {
                    throw new \Exception('Worksheet not found inside the workbook.');
                }
                $matrix = $this->parse_sheet($sheetXml, $shared);
            } finally {
                $zip->close();
            }
        }

        return $this->pad_matrix($matrix);
    }

    /**
     * Pad every row to the widest row so column indices align across rows, and
     * drop fully-blank rows.
     */
    private function pad_matrix(array $matrix)
    {
        $width = 0;
        foreach ($matrix as $row) {
            $width = max($width, count($row));
        }

        $out = [];
        foreach ($matrix as $row) {
            if (!$this->row_has_content($row)) {
                continue;
            }
            $out[] = array_pad($row, $width, '');
        }

        return $out;
    }

    private function read_shared_strings(ZipArchive $zip)
    {
        $data = $zip->getFromName('xl/sharedStrings.xml');
        if ($data === false) {
            return [];
        }

        $xml = $this->load_xml($data);
        if ($xml === false) {
            return [];
        }

        $strings = [];
        foreach ($xml->si as $si) {
            $strings[] = $this->shared_string_text($si);
        }

        return $strings;
    }

    private function shared_string_text(SimpleXMLElement $si)
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

    private function resolve_first_sheet_path(ZipArchive $zip)
    {
        $default = 'xl/worksheets/sheet1.xml';

        $wbData   = $zip->getFromName('xl/workbook.xml');
        $relsData = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wbData === false || $relsData === false) {
            return $default;
        }

        $wb   = $this->load_xml($wbData);
        $rels = $this->load_xml($relsData);
        if ($wb === false || $rels === false || !isset($wb->sheets->sheet)) {
            return $default;
        }

        $sheet = $wb->sheets->sheet[0];
        $rid   = (string) $sheet->attributes('r', true)->id;
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

    /**
     * Parse a worksheet XML string into a dense matrix of cell strings.
     */
    private function parse_sheet($sheetXml, array $shared)
    {
        $xml = $this->load_xml($sheetXml);
        if ($xml === false || !isset($xml->sheetData)) {
            return [];
        }

        $matrix = [];
        foreach ($xml->sheetData->row as $row) {
            $cells   = [];
            $autoCol = 0;
            foreach ($row->c as $c) {
                $ref     = (string) $c['r'];
                $col     = $ref !== '' ? $this->ref_to_index($ref) : $autoCol;
                $autoCol = $col + 1;

                $cells[$col] = $this->cell_value($c, $shared);
            }

            if (!empty($cells)) {
                $max   = max(array_keys($cells));
                $dense = [];
                for ($i = 0; $i <= $max; $i++) {
                    $dense[$i] = isset($cells[$i]) ? $cells[$i] : '';
                }
                $matrix[] = $dense;
            } else {
                $matrix[] = [];
            }
        }

        return $matrix;
    }

    private function cell_value(SimpleXMLElement $c, array $shared)
    {
        $type = (string) $c['t'];

        if ($type === 's') {
            $idx = (int) $c->v;

            return isset($shared[$idx]) ? $shared[$idx] : '';
        }

        if ($type === 'inlineStr') {
            return isset($c->is) ? $this->shared_string_text($c->is) : '';
        }

        if ($type === 'str') {
            return (string) $c->v;
        }

        $raw = isset($c->v) ? (string) $c->v : '';

        // Numeric cells only ('' and 'n'); booleans, errors and dates keep
        // their raw text.
        return ($type === '' || $type === 'n') ? $this->normalize_number($raw) : $raw;
    }

    /**
     * Render a numeric cell the way the spreadsheet shows it, not the way the
     * writer stored it. Whichever tool produced the workbook may write a whole
     * number as "114.0" or a long one as "9.8765432101E+9"; both would reach the
     * importer as text that no longer looks like an id or a phone number
     * (ctype_digit('114.0') is false, so staff_id matching silently fails).
     */
    private function normalize_number($raw)
    {
        // Plain integers — and anything that isn't really a number — as-is.
        if ($raw === '' || !is_numeric($raw) || strpbrk($raw, '.eE') === false) {
            return $raw;
        }

        // Ordinary decimal text: trim the tail textually rather than via a
        // float, so long ids keep every digit ("114.0" → "114", "25000.50" →
        // "25000.5") and nothing picks up binary rounding noise.
        if (strpbrk($raw, 'eE') === false) {
            $trimmed = rtrim(rtrim($raw, '0'), '.');

            return ($trimmed === '' || $trimmed === '-') ? '0' : $trimmed;
        }

        // Exponent form ("9.87654321E+9" — how some writers store long numbers
        // like mobiles). Past 2^53 a float can no longer hold every integer, so
        // leave those alone rather than corrupt them.
        $float = (float) $raw;
        if (floor($float) === $float && abs($float) < 9007199254740992) {
            return number_format($float, 0, '.', '');
        }

        return (string) $float;
    }

    /**
     * "B12" → 1 (zero-based column index, row part ignored).
     */
    private function ref_to_index($ref)
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $ref));

        $index = 0;
        $len   = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    private function row_has_content($row)
    {
        foreach ((array) $row as $cell) {
            if (trim((string) $cell) !== '') {
                return true;
            }
        }

        return false;
    }

    private function read_csv($path)
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \Exception('Unable to open the uploaded file.');
        }

        // Detect , vs ; from the first line (Excel regional CSV exports)
        $first = fgets($handle);
        if ($first === false) {
            fclose($handle);

            return [];
        }
        $first     = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $delimiter = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';

        $matrix   = [];
        $matrix[] = str_getcsv(trim($first), $delimiter, '"', '\\');
        // Pass all args explicitly so behaviour is identical from PHP 7.4 to 8.4
        // (the default $escape is deprecated on newer runtimes).
        while (($data = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if ($data === [null]) {
                continue;
            }
            $matrix[] = $data;
        }
        fclose($handle);

        return $matrix;
    }

    /**
     * Load XML defensively (suppress libxml warnings, disable external entities).
     */
    private function load_xml($data)
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

        return $xml === false ? false : $xml;
    }
}
