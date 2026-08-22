<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Tiny, self-contained XLSX reader/writer for the SaaS "Smart Plans" feature.
 *
 * The module does not ship PhpSpreadsheet, so this class builds a genuine
 * Office-OpenXML (.xlsx) workbook by hand using ZipArchive (writer) and parses
 * one back using SimpleXML (reader). Only the small subset needed for a
 * grid (header + label columns + values) round-trip is implemented:
 *
 *  - Writer: build_grid() writes an arbitrary 2D grid with a styled/frozen
 *            header row and frozen "label" columns. Numbers (int/float) become
 *            numeric cells, everything else an inline string (no shared-string
 *            table to maintain on write).
 *  - Reader: read_matrix() returns the raw, full-width cell matrix; it
 *            understands shared strings (what Excel re-saves to), inline
 *            strings and plain numbers, and transparently falls back to CSV so
 *            an admin can upload either a .xlsx or a .csv.
 *
 * Everything is dependency-free (ZipArchive + SimpleXML are part of core PHP).
 */
class Perfex_saas_xlsx
{
    /**
     * Build an .xlsx workbook from a 2D grid and return its raw bytes.
     *
     * @param array $grid Ordered list of rows; each row is an ordered list of
     *                    cell values (int/float render as numbers, else text)
     * @param array $opts sheet, widths[], header_rows, label_cols, freeze_row,
     *                    freeze_col
     * @return string Binary .xlsx content
     */
    public function build_grid(array $grid, array $opts = [])
    {
        $sheet       = $opts['sheet']       ?? 'Sheet1';
        $widths      = $opts['widths']      ?? [];
        $header_rows = $opts['header_rows'] ?? 1;
        $label_cols  = $opts['label_cols']  ?? 0;
        $freeze_row  = $opts['freeze_row']  ?? $header_rows;
        $freeze_col  = $opts['freeze_col']  ?? $label_cols;

        $tmp = tempnam(sys_get_temp_dir(), 'pssx');

        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->content_types_xml());
        $zip->addFromString('_rels/.rels', $this->root_rels_xml());
        $zip->addFromString('xl/workbook.xml', $this->workbook_xml($sheet));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbook_rels_xml());
        $zip->addFromString('xl/styles.xml', $this->styles_xml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet_xml($grid, $widths, $header_rows, $label_cols, $freeze_row, $freeze_col));

        $zip->close();

        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

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

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'csv' || $ext === 'txt') {
            $matrix = $this->read_csv($path);
        } else {
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

    /* ===================================================================
     * Writer internals
     * =================================================================== */

    private function content_types_xml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function root_rels_xml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook_xml($sheet)
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->xml($sheet) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbook_rels_xml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
    }

    /**
     * Cell formats: 0 = default, 1 = header (bold white on dark, centered),
     * 2 = field label (bold on light fill), 3 = note (gray italic on light fill).
     */
    private function styles_xml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF1F2937"/><name val="Calibri"/></font>'
            . '<font><i/><sz val="10"/><color rgb="FF6B7280"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF2F5597"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '</styleSheet>';
    }

    private function sheet_xml(array $grid, array $widths, $header_rows, $label_cols, $freeze_row, $freeze_col)
    {
        $cols = '';
        if (!empty($widths)) {
            $cols .= '<cols>';
            foreach ($widths as $i => $w) {
                $n = $i + 1;
                $cols .= '<col min="' . $n . '" max="' . $n . '" width="' . (float)$w . '" customWidth="1"/>';
            }
            $cols .= '</cols>';
        }

        $sheetData = '<sheetData>';
        $r = 1;
        foreach ($grid as $row) {
            $sheetData .= '<row r="' . $r . '">';
            $c = 0;
            foreach ($row as $value) {
                $style = $this->cell_style($r - 1, $c, $header_rows, $label_cols);
                $sheetData .= $this->cell($this->col_letter($c) . $r, $value, $style);
                $c++;
            }
            $sheetData .= '</row>';
            $r++;
        }
        $sheetData .= '</sheetData>';

        $views = '<sheetViews><sheetView tabSelected="1" workbookViewId="0">';
        if ($freeze_row > 0 || $freeze_col > 0) {
            $topLeft = $this->col_letter($freeze_col) . ($freeze_row + 1);
            $views .= '<pane'
                . ($freeze_col > 0 ? ' xSplit="' . (int)$freeze_col . '"' : '')
                . ($freeze_row > 0 ? ' ySplit="' . (int)$freeze_row . '"' : '')
                . ' topLeftCell="' . $topLeft . '" activePane="bottomRight" state="frozen"/>'
                . '<selection pane="bottomRight" activeCell="' . $topLeft . '" sqref="' . $topLeft . '"/>';
        }
        $views .= '</sheetView></sheetViews>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . $views
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . $cols
            . $sheetData
            . '</worksheet>';
    }

    /**
     * Decide which style index a cell gets based on its position.
     */
    private function cell_style($rowIndex, $colIndex, $header_rows, $label_cols)
    {
        if ($rowIndex < $header_rows) {
            return 1; // header
        }
        if ($colIndex < $label_cols) {
            return $colIndex === 0 ? 2 : 3; // first label col bold, the rest muted notes
        }
        return 0;
    }

    /**
     * Render one cell. Numbers (int/float) become numeric cells; everything else
     * is an inline string so we never need a shared-string table on write.
     */
    private function cell($ref, $value, $style)
    {
        $s = $style > 0 ? ' s="' . $style . '"' : '';

        if (is_int($value) || is_float($value)) {
            return '<c r="' . $ref . '"' . $s . '><v>' . $value . '</v></c>';
        }

        $text = (string)$value;
        if ($text === '') {
            return '<c r="' . $ref . '"' . $s . '/>';
        }

        return '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">'
            . $this->xml($text) . '</t></is></c>';
    }

    /**
     * Zero-based column index → spreadsheet column letters (0 => A, 26 => AA).
     */
    private function col_letter($index)
    {
        $letters = '';
        $index++;
        while ($index > 0) {
            $mod     = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index   = (int)(($index - $mod) / 26);
        }
        return $letters;
    }

    private function xml($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /* ===================================================================
     * Reader internals
     * =================================================================== */

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
            return (string)$si->t;
        }

        $text = '';
        foreach ($si->r as $run) {
            $text .= (string)$run->t;
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
        $rid   = (string)$sheet->attributes('r', true)->id;
        if ($rid === '') {
            return $default;
        }

        foreach ($rels->Relationship as $rel) {
            if ((string)$rel['Id'] === $rid) {
                $target = ltrim((string)$rel['Target'], '/');
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
                $ref = (string)$c['r'];
                $col = $ref !== '' ? $this->ref_to_index($ref) : $autoCol;
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
        $type = (string)$c['t'];

        if ($type === 's') {
            $idx = (int)$c->v;
            return isset($shared[$idx]) ? $shared[$idx] : '';
        }

        if ($type === 'inlineStr') {
            return isset($c->is) ? $this->shared_string_text($c->is) : '';
        }

        if ($type === 'str') {
            return (string)$c->v;
        }

        return isset($c->v) ? (string)$c->v : '';
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
        foreach ((array)$row as $cell) {
            if (trim((string)$cell) !== '') {
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

        $matrix = [];
        // Pass all args explicitly so behaviour is identical from PHP 7.4 to 8.4
        // (the default $escape is deprecated on newer runtimes).
        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $matrix[] = $data;
        }
        fclose($handle);

        if (!empty($matrix[0][0])) {
            $matrix[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $matrix[0][0]);
        }

        return $matrix;
    }

    /**
     * Load XML defensively (suppress libxml warnings, disable external entities).
     */
    private function load_xml($data)
    {
        if (trim((string)$data) === '') {
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
}
