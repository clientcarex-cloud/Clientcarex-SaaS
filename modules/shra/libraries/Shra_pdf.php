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
        $this->brand = $brand + ['name' => 'Stallion Horse Riding Academy', 'tagline' => '', 'logo_path' => null, 'contact' => '', 'chief_instructor' => 'Chief Instructor', 'director' => 'Director', 'powered_by_logo' => null];
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

    /**
     * Premium bill receipt.
     * $bill keys: enrollment_no, invoice_label, created_at, full_name, rider_no, membership_no, mobile, email, address,
     *   rider_type, package_name, audience, sessions_total, sessions_used, duration_min, list_price, discount_percent,
     *   discount_amount, total, paid_real, due, pay_status, payment_mode, payments[], notes, expires_at, qr_text,
     *   issued_by, offer_label
     */
    public function receipt(array $bill)
    {
        $this->SetTitle('Receipt ' . ($bill['enrollment_no'] ?? ''));
        $this->AddPage('P');
        $this->page_frame();

        $w   = $this->getPageWidth();
        $cx  = $w / 2;
        $m   = 22;              // side margin
        $cw  = $w - $m * 2;     // content width
        $sym = $bill['currency_symbol'] ?? '₹';
        $cur = function ($n) use ($sym) { return $sym . number_format((float) $n, 2); };

        // ── Header: logo left, academy name, receipt block right ──
        $this->brand_logo($m + 13, 33, 26);
        $this->txt($m + 30, 24, $cw - 90, mb_strtoupper($this->brand['name']), 'times', 'B', 15, self::INK);
        if ($this->brand['tagline']) {
            $this->txt($m + 30, 31, $cw - 90, $this->brand['tagline'], 'times', 'I', 9, self::MUTED);
        }
        if ($this->brand['contact']) {
            $this->txt($m + 30, 36.5, $cw - 90, $this->brand['contact'], 'helvetica', '', 7.2, self::MUTED);
        }

        $this->txt($m, 22, $cw, 'PAYMENT RECEIPT', 'helvetica', 'B', 8, self::GOLD, 'R');
        $this->txt($m, 27, $cw, $bill['enrollment_no'], 'times', 'B', 19, self::INK, 'R', 9);
        $this->txt($m, 36, $cw, 'Date ' . date('d M Y, h:i A', strtotime($bill['created_at'])), 'helvetica', '', 7.5, self::MUTED, 'R');
        if (!empty($bill['invoice_label'])) {
            $this->txt($m, 40.5, $cw, 'Invoice ' . $bill['invoice_label'], 'helvetica', '', 7.5, self::MUTED, 'R');
        }

        $this->divider(50, $cx, $cw / 2);

        // ── Status ribbon ──
        $status = $bill['pay_status'] ?? 'paid';
        $ribbon = ['paid' => ['PAID IN FULL', [47, 74, 31]], 'partial' => ['PARTIALLY PAID', [184, 146, 46]], 'unpaid' => ['PAYMENT DUE', [168, 50, 45]], 'cancelled' => ['CANCELLED', self::MUTED]];
        [$rtxt, $rcol] = $ribbon[$status] ?? $ribbon['paid'];
        $this->SetFont('helvetica', 'B', 8.5);
        $rw = $this->GetStringWidth($rtxt) + 16;
        $this->SetFillColor(...$rcol);
        $this->RoundedRect($cx - $rw / 2, 54, $rw, 7.5, 3.75, '1111', 'F');
        $this->txt($cx - $rw / 2, 54, $rw, $rtxt, 'helvetica', 'B', 8.5, self::WHITE, 'C', 7.5);

        // ── Billed to ──
        $y  = 70;
        $hw = $cw / 2;
        $this->txt($m, $y, $hw, 'BILLED TO', 'helvetica', '', 6.8, self::GOLD);
        $this->txt($m, $y + 4.5, $hw, $bill['full_name'], 'times', 'B', 15, self::INK, 'L', 7);
        $line2 = $bill['rider_no'] . (!empty($bill['membership_no']) ? '  ·  ' . $bill['membership_no'] : '') . '  ·  ' . ($bill['rider_type'] === 'guest' ? 'Guest rider' : 'Learner');
        $this->txt($m, $y + 12.5, $hw, $line2, 'helvetica', '', 8, self::BROWN);
        $this->txt($m, $y + 17, $hw, trim($bill['mobile'] . (!empty($bill['email']) ? '  ·  ' . $bill['email'] : '')), 'helvetica', '', 8, self::MUTED);
        $ay = $y + 21.5;
        if (!empty($bill['address'])) {
            $ay = $this->multitext($m, $ay, $hw - 6, $bill['address'], 'helvetica', '', 7.5, self::MUTED, 'L', 1.35);
        }

        // Right: package card
        $px = $m + $hw + 4;
        $pw = $hw - 4;
        $this->SetFillColor(240, 229, 204);
        $this->RoundedRect($px, $y - 2, $pw, 30, 3, '1111', 'F');
        $this->txt($px + 5, $y + 1, $pw - 10, 'PACKAGE', 'helvetica', '', 6.8, self::GOLD);
        $this->txt($px + 5, $y + 5.5, $pw - 10, $bill['package_name'] . ' · ' . ucfirst($bill['audience']), 'times', 'B', 13, self::INK, 'L', 6.5);
        $n = (int) $bill['sessions_total'];
        $this->txt($px + 5, $y + 13, $pw - 10, $n . ' session' . ($n > 1 ? 's' : '') . ' × ' . (int) $bill['duration_min'] . ' min' . (!empty($bill['expires_at']) ? '  ·  valid till ' . date('d M Y', strtotime($bill['expires_at'])) : '  ·  no expiry'), 'helvetica', '', 8, self::BROWN);
        $used = (int) $bill['sessions_used'];
        $this->txt($px + 5, $y + 18, $pw - 10, 'Sessions used ' . $used . ' of ' . $n . '  ·  ' . max(0, $n - $used) . ' remaining', 'helvetica', '', 7.5, self::MUTED);
        // progress bar
        $this->SetFillColor(...self::SAND);
        $this->RoundedRect($px + 5, $y + 23, $pw - 10, 2, 1, '1111', 'F');
        if ($n > 0 && $used > 0) {
            $this->SetFillColor(...self::GOLD);
            $this->RoundedRect($px + 5, $y + 23, ($pw - 10) * min(1, $used / $n), 2, 1, '1111', 'F');
        }

        // ── Charges table ──
        $ty = max($ay + 8, $y + 36);
        $this->txt($m, $ty, $cw, 'CHARGES', 'helvetica', '', 6.8, self::GOLD);
        $ty += 5;
        $this->SetFillColor(...self::INK);
        $this->RoundedRect($m, $ty, $cw, 7, 1.5, '1111', 'F');
        $this->txt($m + 4, $ty, $cw * 0.6, 'Description', 'helvetica', 'B', 7.5, self::CREAM, 'L', 7);
        $this->txt($m + $cw * 0.6, $ty, $cw * 0.15, 'Qty', 'helvetica', 'B', 7.5, self::CREAM, 'C', 7);
        $this->txt($m + $cw * 0.75, $ty, $cw * 0.25 - 4, 'Amount', 'helvetica', 'B', 7.5, self::CREAM, 'R', 7);
        $ty += 7;
        $this->SetFillColor(...self::WHITE);
        $this->Rect($m, $ty, $cw, 11, 'F');
        $this->txt($m + 4, $ty + 1.5, $cw * 0.6, $bill['package_name'] . ' — ' . ucfirst($bill['audience']), 'helvetica', 'B', 9, self::INK);
        $this->txt($m + 4, $ty + 6.5, $cw * 0.6, $n . ' × ' . (int) $bill['duration_min'] . ' min riding session' . ($n > 1 ? 's' : '') . ' with trainer', 'helvetica', '', 7.2, self::MUTED);
        $this->txt($m + $cw * 0.6, $ty + 3, $cw * 0.15, '1', 'helvetica', '', 9, self::INK, 'C');
        $this->txt($m + $cw * 0.75, $ty + 3, $cw * 0.25 - 4, $cur($bill['list_price']), 'dejavusans', '', 8.5, self::INK, 'R');
        $ty += 11;
        $this->SetLineStyle(['width' => 0.2, 'color' => self::SAND]);
        $this->Line($m, $ty, $m + $cw, $ty);

        // totals (right aligned block)
        $tx = $m + $cw * 0.55;
        $tw = $cw * 0.45;
        $row = function ($label, $value, $bold = false, $color = null) use (&$ty, $tx, $tw) {
            $ty += 6;
            $this->txt($tx, $ty, $tw * 0.6, $label, 'helvetica', $bold ? 'B' : '', $bold ? 9 : 8, $color ?: ($bold ? self::INK : self::MUTED));
            $this->txt($tx + $tw * 0.6, $ty, $tw * 0.4 - 4, $value, 'dejavusans', $bold ? 'B' : '', $bold ? 9.5 : 8, $color ?: self::INK, 'R');
        };
        $row('Subtotal', $cur($bill['list_price']));
        if ((float) $bill['discount_amount'] > 0) {
            $row('Discount ' . ($bill['discount_percent'] + 0) . '%' . (!empty($bill['offer_label']) ? ' · ' . $bill['offer_label'] : ''), '− ' . $cur($bill['discount_amount']), false, [168, 50, 45]);
        }
        $ty += 2;
        $this->SetLineStyle(['width' => 0.4, 'color' => self::GOLD]);
        $this->Line($tx, $ty + 4.5, $m + $cw, $ty + 4.5);
        $row('Total', $cur($bill['total']), true);
        $row('Paid', $cur($bill['paid_real']), false, [47, 74, 31]);
        if ((float) $bill['due'] > 0.009) {
            $row('Balance due', $cur($bill['due']), true, [168, 50, 45]);
        }

        // ── Payments ──
        $py = $ty + 14;
        $this->txt($m, $py, $cw, 'PAYMENTS RECEIVED', 'helvetica', '', 6.8, self::GOLD);
        $py += 5;
        $pays = $bill['payments'] ?? [];
        if (!count($pays)) {
            $this->txt($m, $py, $cw, 'No payment recorded yet.', 'helvetica', 'I', 8, self::MUTED);
            $py += 6;
        } else {
            $cols = [0.22, 0.22, 0.36, 0.20];
            $this->SetFillColor(240, 229, 204);
            $this->Rect($m, $py, $cw, 6, 'F');
            $x = $m;
            foreach (['Date', 'Mode', 'Reference', 'Amount'] as $i => $h) {
                $this->txt($x + 3, $py, $cw * $cols[$i] - 6, $h, 'helvetica', 'B', 7, self::BROWN, $i === 3 ? 'R' : 'L', 6);
                $x += $cw * $cols[$i];
            }
            $py += 6;
            foreach ($pays as $pr) {
                $pr = (array) $pr;
                $x  = $m;
                $vals = [date('d M Y', strtotime($pr['date'])), $pr['mode_name'] ?: ($pr['paymentmode'] ?: '—'), $pr['transactionid'] ?: '—', $cur($pr['amount'])];
                foreach ($vals as $i => $v) {
                    $this->txt($x + 3, $py, $cw * $cols[$i] - 6, $v, $i === 3 ? 'dejavusans' : 'helvetica', $i === 3 ? 'B' : '', $i === 3 ? 7.5 : 8, self::INK, $i === 3 ? 'R' : 'L', 6);
                    $x += $cw * $cols[$i];
                }
                $py += 6;
                $this->SetLineStyle(['width' => 0.15, 'color' => self::SAND]);
                $this->Line($m, $py, $m + $cw, $py);
            }
        }

        // ── Notes / terms ──
        $ny = $py + 8;
        if (!empty($bill['notes'])) {
            $this->txt($m, $ny, $cw, 'NOTE', 'helvetica', '', 6.8, self::GOLD);
            $ny = $this->multitext($m, $ny + 4.5, $cw, $bill['notes'], 'helvetica', 'I', 8, self::BROWN, 'L', 1.4) + 4;
        }
        $terms = 'Sessions are first-come, first-served with no fixed time slots. Packages are personal, non-transferable and non-refundable. A riding helmet and closed shoes are mandatory for every session. Please keep this receipt for your records.';
        $this->SetFillColor(240, 229, 204);
        $this->RoundedRect($m, $ny, $cw - 34, 20, 2.5, '1111', 'F');
        $this->multitext($m + 4, $ny + 3, $cw - 42, $terms, 'helvetica', 'I', 7.3, self::BROWN, 'L', 1.4);
        $this->qrcode($bill['qr_text'] ?? $bill['rider_no'], $m + $cw - 28, $ny - 2, 26);
        $this->txt($m + $cw - 30, $ny + 25, 30, 'Scan to verify rider', 'helvetica', '', 5.5, self::MUTED, 'C');

        // ── Signatures ──
        $sy = min($ny + 48, $this->getPageHeight() - 42);
        $this->signature_line($m, $sy, 60, 'Received by');
        if (!empty($bill['issued_by'])) {
            $this->txt($m, $sy - 5, 60, $bill['issued_by'], 'times', 'I', 9, self::BROWN, 'C');
        }
        $this->signature_line($m + $cw - 60, $sy, 60, 'Rider / Guardian');

        // Thank you
        $this->txt($m, $sy + 14, $cw, 'Thank you for riding with us.', 'times', 'I', 11, self::GOLD, 'C');

        $this->footer_line();

        return $this;
    }

    protected function footer_line()
    {
        $w = $this->getPageWidth();
        $h = $this->getPageHeight();
        $txt = trim($this->brand['name'] . ($this->brand['contact'] ? '  ·  ' . $this->brand['contact'] : ''));
        $this->txt(20, $h - 19, $w - 40, $txt, 'helvetica', '', 7, self::MUTED, 'C');

        // Powered by ClientcareX
        $logo = $this->brand['powered_by_logo'] ?? null;
        if ($logo && is_file($logo)) {
            $lw = 15; $lh = $lw * 120 / 500;
            $this->SetFont('helvetica', '', 5.5);
            $tw = $this->GetStringWidth('Powered by') + 1.5;
            $x  = ($w - ($tw + $lw)) / 2;
            $this->txt($x, $h - 15, $tw, 'Powered by', 'helvetica', '', 5.5, [126, 140, 157], 'L', $lh);
            $this->Image($logo, $x + $tw, $h - 15, $lw, $lh, 'PNG', 'https://clientcarex.com', '', true, 300);
        } else {
            $this->txt(20, $h - 13, $w - 40, 'Powered by ClientcareX', 'helvetica', '', 5.5, [126, 140, 157], 'C');
        }
    }
}
