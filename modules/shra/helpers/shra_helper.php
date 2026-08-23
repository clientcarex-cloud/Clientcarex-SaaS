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
