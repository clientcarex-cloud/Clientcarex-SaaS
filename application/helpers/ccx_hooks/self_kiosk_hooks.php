<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ──────────────────────────────────────────────────────
 *  SELF-KIOSK MODULE — Hook Definitions
 * ──────────────────────────────────────────────────────
 *
 * Each entry must contain:
 *   hook_key, label, module, description, variables
 */

return [

    // ── Self-QR OTP ───────────────────────────────────
    [
        'hook_key'    => 'self_qr_otp',
        'label'       => 'Self-QR OTP',
        'module'      => 'self_kiosk',
        'description' => 'Triggered when a patient enters their mobile number on the Self-Kiosk check-in screen. Sends an OTP for verification before allowing access.',
        'variables'   => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'otp_code',
            'company',
        ],
    ],

];
