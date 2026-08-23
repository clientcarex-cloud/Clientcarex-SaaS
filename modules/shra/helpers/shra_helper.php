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
function shra_money($amount)
{
    return app_format_money((float) $amount, get_base_currency());
}

/** Public self-registration URL. */
function shra_join_url()
{
    return site_url('join/' . get_option('shra_public_token'));
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

    return $barcode->getBarcodeSVGcode($size, $size, $color);
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
