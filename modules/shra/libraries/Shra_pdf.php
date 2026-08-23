<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Premium PDF documents for SHRA — membership & course certificate.
 *
 * Deliberately built on plain TCPDF (not App_pdf) so the pages are
 * full-bleed designs with their own header/footer, independent from the
 * invoice PDF options. Everything is passed in as plain arrays so the
 * class can be rendered without the CRM bootstrapped (see tests).
 */
class Shra_pdf extends TCPDF
{
    // Palette (logo theme)
    public const CREAM = [246, 239, 224];
    public const SAND  = [233, 217, 182];
    public const INK   = [28, 26, 23];
    public const GOLD  = [184, 146, 46];
    public const BROWN = [90, 62, 34];
    public const MUTED = [120, 108, 90];
    public const WHITE = [255, 255, 255];

    protected $brand = [];

    /**
     * @param array $brand ['name','tagline','logo_path','contact','chief_instructor','director']
     * @param string $orientation P|L
     */
    public function __construct(array $brand, $orientation = 'P')
    {
        parent::__construct($orientation, 'mm', 'A4', true, 'UTF-8', false);
        $this->brand = $brand + ['name' => 'Stallion Horse Riding Academy', 'tagline' => '', 'logo_path' => null, 'contact' => '', 'chief_instructor' => 'Chief Instructor', 'director' => 'Director'];
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0, 0, 0);
        $this->SetAuthor($this->brand['name']);
        $this->SetCreator($this->brand['name']);
        $this->setCellPaddings(0, 0, 0, 0);
    }

    /* ───────────────────────── Drawing helpers ───────────────────────── */

    protected function rgb(array $c)
    {
        return ['R' => $c[0], 'G' => $c[1], 'B' => $c[2]];
    }

    protected function page_frame()
    {
        $w = $this->getPageWidth();
        $h = $this->getPageHeight();

        // Cream paper
        $this->SetFillColor(...self::CREAM);
        $this->Rect(0, 0, $w, $h, 'F');

        // Soft sand vignette bands
        $this->SetFillColor(240, 229, 204);
        $this->Rect(0, 0, $w, 6, 'F');
        $this->Rect(0, $h - 6, $w, 6, 'F');

        // Double gold border
        $this->SetLineStyle(['width' => 0.9, 'color' => self::GOLD]);
        $this->Rect(9, 9, $w - 18, $h - 18, 'D');
        $this->SetLineStyle(['width' => 0.25, 'color' => self::GOLD]);
        $this->Rect(11.5, 11.5, $w - 23, $h - 23, 'D');

        // Corner ornaments
        foreach ([[9, 9], [$w - 9, 9], [9, $h - 9], [$w - 9, $h - 9]] as $c) {
            $this->SetFillColor(...self::GOLD);
            $this->Circle($c[0], $c[1], 1.6, 0, 360, 'F');
            $this->SetFillColor(...self::CREAM);
            $this->Circle($c[0], $c[1], 0.7, 0, 360, 'F');
        }
    }

    /** Diamond divider line like the pricing poster. */
    protected function divider($y, $cx, $half = 40)
    {
        $this->SetLineStyle(['width' => 0.3, 'color' => self::GOLD]);
        $this->Line($cx - $half, $y, $cx - 4, $y);
        $this->Line($cx + 4, $y, $cx + $half, $y);
        $this->SetFillColor(...self::GOLD);
        $this->Polygon([$cx, $y - 1.8, $cx + 1.8, $y, $cx, $y + 1.8, $cx - 1.8, $y], 'F');
    }

    /**
     * Logo: uploaded raster when available, else a drawn horseshoe monogram.
     * ($cx,$cy) centre, $d diameter in mm.
     */
    protected function brand_logo($cx, $cy, $d = 34)
    {
        $path = $this->brand['logo_path'];
        if ($path && is_file($path)) {
            $this->Image($path, $cx - $d / 2, $cy - $d / 2, $d, $d, '', '', '', true, 300, '', false, false, 0, 'CM');

            return;
        }

        // Parchment disc
        $this->SetFillColor(...self::SAND);
        $this->Circle($cx, $cy, $d / 2, 0, 360, 'F');
        $this->SetFillColor(240, 229, 204);
        $this->Circle($cx, $cy, $d / 2 - 1.2, 0, 360, 'F');

        // Horseshoe (thick arc opening downward)
        $r = $d * 0.26;
        $this->SetLineStyle(['width' => $d * 0.075, 'color' => self::INK, 'cap' => 'butt']);
        $this->Circle($cx, $cy - $d * 0.03, $r, 305, 235, 'D');

        // Nail holes
        $this->SetFillColor(...self::SAND);
        foreach ([250, 280, 310, 340, 20, 50, 80, 110] as $a) {
            $rad = deg2rad($a);
            $this->Circle($cx + $r * cos($rad), $cy - $d * 0.03 - $r * sin($rad), $d * 0.012, 0, 360, 'F');
        }

        // Monogram
        $this->SetTextColor(...self::INK);
        $this->SetFont('times', 'B', $d * 0.95);
        $this->SetXY($cx - $d / 2, $cy - $d * 0.27);
        $this->Cell($d, $d * 0.4, 'S', 0, 0, 'C');
        $this->SetFont('helvetica', 'B', $d * 0.17);
        $this->SetXY($cx - $d / 2, $cy + $d * 0.30);
        $this->Cell($d, $d * 0.1, 'SHRA', 0, 0, 'C');
    }

    protected function qrcode($text, $x, $y, $size)
    {
        $style = [
            'border' => false, 'vpadding' => 0, 'hpadding' => 0,
            'fgcolor' => self::INK, 'bgcolor' => false, 'module_width' => 1, 'module_height' => 1,
        ];
        $this->write2DBarcode($text, 'QRCODE,M', $x, $y, $size, $size, $style, 'N');
    }

    protected function txt($x, $y, $w, $txt, $font = 'helvetica', $style = '', $size = 10, $color = self::INK, $align = 'L', $h = 0)
    {
        $this->SetFont($font, $style, $size);
        $this->SetTextColor(...$color);
        $this->SetXY($x, $y);
        $this->Cell($w, $h ?: $size * 0.5, $txt, 0, 0, $align, false, '', 1);
    }

    protected function multitext($x, $y, $w, $txt, $font = 'helvetica', $style = '', $size = 9, $color = self::INK, $align = 'L', $lh = 1.45)
    {
        $this->SetFont($font, $style, $size);
        $this->SetTextColor(...$color);
        $this->setCellHeightRatio($lh);
        $this->SetXY($x, $y);
        $this->MultiCell($w, 0, $txt, 0, $align, false, 1);
        $this->setCellHeightRatio(1.25);

        return $this->GetY();
    }

    protected function label_value($x, $y, $w, $label, $value)
    {
        $this->txt($x, $y, $w, mb_strtoupper($label), 'helvetica', '', 6.8, self::MUTED);
        $this->txt($x, $y + 4.2, $w, (string) ($value === '' || $value === null ? '—' : $value), 'helvetica', 'B', 10.5, self::INK);
        $this->SetLineStyle(['width' => 0.2, 'color' => self::SAND]);
        $this->Line($x, $y + 11.5, $x + $w - 4, $y + 11.5);
    }

    protected function signature_line($x, $y, $w, $title)
    {
        $this->SetLineStyle(['width' => 0.35, 'color' => self::INK]);
        $this->Line($x, $y, $x + $w, $y);
        $this->txt($x, $y + 1.5, $w, $title, 'helvetica', 'B', 8.5, self::INK, 'C');
        $this->txt($x, $y + 5.5, $w, $this->brand['name'], 'helvetica', '', 7, self::MUTED, 'C');
    }

    /* ───────────────────────── Documents ───────────────────────── */

    /**
     * Membership certificate + detachable card.
     * $rider keys: full_name, rider_no, membership_no, membership_issued_at, mobile, email, gender, dob, age,
     *   place_of_birth, address, marital_status, riding_level, guardian_name, guardian_relationship,
     *   is_minor, terms_accepted_by, terms_accepted_at, qr_text
     */
    public function membership(array $rider)
    {
        $this->SetTitle('Membership ' . ($rider['membership_no'] ?? ''));
        $this->AddPage('P');
        $this->page_frame();

        $w  = $this->getPageWidth();
        $cx = $w / 2;

        $this->brand_logo($cx, 34, 30);
        $this->txt(20, 52, $w - 40, mb_strtoupper($this->brand['name']), 'times', 'B', 17, self::INK, 'C');
        if ($this->brand['tagline']) {
            $this->txt(20, 59.5, $w - 40, $this->brand['tagline'], 'times', 'I', 9.5, self::MUTED, 'C');
        }
        $this->divider(66, $cx, 36);

        $this->txt(20, 70, $w - 40, 'RIDER MEMBERSHIP', 'helvetica', '', 8, self::GOLD, 'C');
        $this->SetFont('helvetica', '', 8);
        $this->txt(20, 76, $w - 40, $rider['full_name'], 'times', 'B', 26, self::INK, 'C', 13);

        // Membership chip
        $chip = 'Membership No. ' . ($rider['membership_no'] ?: $rider['rider_no']) . '   ·   ' . ($rider['riding_level'] ?: 'Beginner') . ' rider';
        $this->SetFont('helvetica', 'B', 8.5);
        $cw = $this->GetStringWidth($chip) + 14;
        $this->SetFillColor(...self::INK);
        $this->RoundedRect($cx - $cw / 2, 91, $cw, 7.5, 3.75, '1111', 'F');
        $this->txt($cx - $cw / 2, 91, $cw, $chip, 'helvetica', 'B', 8.5, self::CREAM, 'C', 7.5);

        // Details grid
        $gx = 24;
        $gy = 108;
        $gw = ($w - 48) / 2;
        $dob = !empty($rider['dob']) ? date('d M Y', strtotime($rider['dob'])) . (isset($rider['age']) && $rider['age'] !== null ? ' (' . $rider['age'] . ' yrs)' : '') : '';
        $rows = [
            ['Rider No.', $rider['rider_no'], 'Member since', !empty($rider['membership_issued_at']) ? date('d M Y', strtotime($rider['membership_issued_at'])) : date('d M Y')],
            ['Guardian', $rider['guardian_name'] ? $rider['guardian_name'] . ($rider['guardian_relationship'] ? ' (' . $rider['guardian_relationship'] . ')' : '') : '', 'Mobile', $rider['mobile']],
            ['Gender', ucfirst((string) $rider['gender']), 'Date of birth', $dob],
            ['Email', $rider['email'], 'Place of birth', $rider['place_of_birth']],
            ['Status', ucfirst((string) $rider['marital_status']), 'Riding level', $rider['riding_level']],
        ];
        foreach ($rows as $i => $r) {
            $y = $gy + $i * 15;
            $this->label_value($gx, $y, $gw, $r[0], $r[1]);
            $this->label_value($gx + $gw, $y, $gw, $r[2], $r[3]);
        }
        $y = $gy + count($rows) * 15;
        $this->txt($gx, $y, $w - 48, 'ADDRESS', 'helvetica', '', 6.8, self::MUTED);
        $y = $this->multitext($gx, $y + 4.2, $w - 48, (string) ($rider['address'] ?: '—'), 'helvetica', 'B', 10, self::INK, 'L', 1.35);

        // Declaration
        $by   = $rider['terms_accepted_by'] ?: $rider['full_name'];
        $when = !empty($rider['terms_accepted_at']) ? date('d M Y, h:i A', strtotime($rider['terms_accepted_at'])) : '';
        $who  = !empty($rider['is_minor']) ? 'the rider\'s parent / guardian' : 'the rider';
        $decl = 'The terms & conditions of ' . $this->brand['name'] . ' were read and accepted by ' . $by . ' as ' . $who . ($when ? ' on ' . $when : '') . '. Sessions are first-come, first-served with no fixed time slots.';
        $this->SetFillColor(240, 229, 204);
        $this->RoundedRect(20, $y + 6, $w - 40, 17, 2.5, '1111', 'F');
        $this->multitext(25, $y + 9, $w - 50, $decl, 'helvetica', 'I', 8.2, self::BROWN, 'L', 1.4);

        // Detachable membership card (sits below the declaration, above the footer)
        $cy = min(max($y + 33, 205), $this->getPageHeight() - 78);
        $this->SetLineStyle(['width' => 0.25, 'color' => self::MUTED, 'dash' => '1.5,1.5']);
        $this->Line(14, $cy - 7, $w - 14, $cy - 7);
        $this->SetLineStyle(['dash' => 0]);
        $this->txt(20, $cy - 6, 60, 'MEMBERSHIP CARD — cut along the line', 'helvetica', '', 6.5, self::MUTED);

        $cwid = 88;
        $chei = 54;
        $cxp  = $cx - $cwid / 2;
        $this->SetFillColor(...self::INK);
        $this->RoundedRect($cxp + 1, $cy + 1, $cwid, $chei, 4, '1111', 'F'); // shadow
        $this->SetFillColor(...self::CREAM);
        $this->RoundedRect($cxp, $cy, $cwid, $chei, 4, '1111', 'F');
        $this->SetLineStyle(['width' => 0.4, 'color' => self::GOLD]);
        $this->RoundedRect($cxp + 1.5, $cy + 1.5, $cwid - 3, $chei - 3, 3, '1111', 'D');

        $this->brand_logo($cxp + 13, $cy + 13, 18);
        $this->txt($cxp + 24, $cy + 7, 40, mb_strtoupper($this->brand['name']), 'helvetica', 'B', 5.6, self::INK);
        $this->txt($cxp + 24, $cy + 11.5, 40, 'RIDER MEMBER', 'helvetica', '', 5.5, self::GOLD);
        $this->txt($cxp + 24, $cy + 17, 48, $rider['full_name'], 'times', 'B', 12, self::INK, 'L', 6);
        $this->txt($cxp + 24, $cy + 24, 48, ($rider['membership_no'] ?: $rider['rider_no']) . '  ·  ' . ($rider['riding_level'] ?: 'Beginner'), 'helvetica', '', 7, self::BROWN);
        $this->txt($cxp + 6, $cy + 34, 60, 'MOBILE', 'helvetica', '', 5, self::MUTED);
        $this->txt($cxp + 6, $cy + 37, 60, (string) $rider['mobile'], 'helvetica', 'B', 7.5, self::INK);
        $this->txt($cxp + 6, $cy + 43, 60, 'MEMBER SINCE', 'helvetica', '', 5, self::MUTED);
        $this->txt($cxp + 6, $cy + 46, 60, !empty($rider['membership_issued_at']) ? date('d M Y', strtotime($rider['membership_issued_at'])) : date('d M Y'), 'helvetica', 'B', 7.5, self::INK);
        $this->qrcode($rider['qr_text'] ?? $rider['rider_no'], $cxp + $cwid - 26, $cy + $chei - 26, 21);

        $this->footer_line();

        return $this;
    }

    /**
     * Course completion certificate (landscape).
     * $cert keys: full_name, rider_no, certificate_no, package_name, sessions_total, duration_min,
     *   riding_level, start_date, completed_at, issued_at, audience, qr_text
     */
    public function certificate(array $cert)
    {
        $this->SetTitle('Certificate ' . ($cert['certificate_no'] ?? ''));
        $this->AddPage('L');
        $this->page_frame();

        $w  = $this->getPageWidth();
        $h  = $this->getPageHeight();
        $cx = $w / 2;

        // Watermark horseshoe
        $this->SetAlpha(0.04);
        $this->SetLineStyle(['width' => 9, 'color' => self::INK]);
        $this->Circle($cx, $h / 2 + 6, 52, 305, 235, 'D');
        $this->SetAlpha(1);

        $this->brand_logo($cx, 36, 30);
        $this->txt(20, 52, $w - 40, mb_strtoupper($this->brand['name']), 'times', 'B', 15, self::INK, 'C');
        $this->divider(62, $cx, 40);

        $this->txt(20, 66, $w - 40, 'CERTIFICATE OF COMPLETION', 'times', 'B', 30, self::INK, 'C', 14);
        $this->txt(20, 81, $w - 40, 'This certificate is proudly presented to', 'times', 'I', 12, self::MUTED, 'C');

        $this->txt(20, 89, $w - 40, $cert['full_name'], 'times', 'B', 36, self::BROWN, 'C', 17);
        $this->SetLineStyle(['width' => 0.4, 'color' => self::GOLD]);
        $this->Line($cx - 70, 108, $cx + 70, 108);

        $n    = (int) $cert['sessions_total'];
        $body = 'for successfully completing the ' . $cert['package_name'] . ' riding course of ' . $n . ' class session' . ($n > 1 ? 's' : '')
            . ' (' . (int) $cert['duration_min'] . ' minutes each)' . ($cert['riding_level'] ? ' at ' . $cert['riding_level'] . ' level' : '')
            . ' with ' . $this->brand['name'] . ', demonstrating discipline, balance and a true partnership with the horse.';
        $this->multitext(40, 113, $w - 80, $body, 'times', '', 12.5, self::INK, 'C', 1.5);

        $from = !empty($cert['start_date']) ? date('d M Y', strtotime($cert['start_date'])) : '';
        $to   = !empty($cert['completed_at']) ? date('d M Y', strtotime($cert['completed_at'])) : date('d M Y');
        $this->txt(20, 136, $w - 40, ($from ? $from . '  —  ' : '') . $to, 'helvetica', '', 9, self::MUTED, 'C');

        // Seal
        $this->SetFillColor(...self::GOLD);
        $this->Circle($cx, 158, 11, 0, 360, 'F');
        $this->SetFillColor(...self::CREAM);
        $this->Circle($cx, 158, 9.6, 0, 360, 'F');
        $this->SetFillColor(...self::GOLD);
        $this->Circle($cx, 158, 8.6, 0, 360, 'F');
        $this->txt($cx - 11, 154.4, 22, 'SHRA', 'helvetica', 'B', 7, self::CREAM, 'C');
        $this->txt($cx - 11, 158.2, 22, 'CERTIFIED', 'helvetica', '', 4.5, self::CREAM, 'C');

        // Signatures
        $this->signature_line(72, 176, 58, $this->brand['chief_instructor']);
        $this->signature_line($w - 130, 176, 58, $this->brand['director']);

        // Certificate meta + QR
        $this->qrcode($cert['qr_text'] ?? $cert['certificate_no'], 16, $h - 36, 19);
        $this->txt(37, $h - 35, 80, 'CERTIFICATE NO.', 'helvetica', '', 6, self::MUTED);
        $this->txt(37, $h - 31.5, 80, (string) $cert['certificate_no'], 'helvetica', 'B', 9, self::INK);
        $this->txt(37, $h - 26, 80, 'RIDER NO.', 'helvetica', '', 6, self::MUTED);
        $this->txt(37, $h - 22.5, 80, (string) $cert['rider_no'], 'helvetica', 'B', 9, self::INK);

        $this->footer_line();

        return $this;
    }

    protected function footer_line()
    {
        $w = $this->getPageWidth();
        $h = $this->getPageHeight();
        $txt = trim($this->brand['name'] . ($this->brand['contact'] ? '  ·  ' . $this->brand['contact'] : ''));
        $this->txt(20, $h - 16, $w - 40, $txt, 'helvetica', '', 7, self::MUTED, 'C');
    }
}
