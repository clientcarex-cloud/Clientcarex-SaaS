<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ──────────────────────────────────────────────────────
 *  PATIENTS / VISITS MODULE — Hook Definitions
 * ──────────────────────────────────────────────────────
 *
 * Each entry must contain:
 *   hook_key, label, module, description, variables
 *
 * To add a new hook for this module, simply append
 * another array entry below.
 */

return [

    // ── New Visit Created ──────────────────────────────
    [
        'hook_key' => 'visit_created',
        'label' => 'New Visit Created',
        'module' => 'patients',
        'description' => 'Triggered when a new patient visit is registered (billing entry created)',
        'variables' => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'visit_code',
            'visit_date',
            'doctor_name',
            'total_amount',
        ],
    ],

    // ── Edit Visit – Payment Received ──────────────────
    [
        'hook_key' => 'edit_visit_payment_received',
        'label' => 'Edit Visit – Payment Received',
        'module' => 'patients',
        'description' => 'Triggered when a payment is recorded while editing an existing visit',
        'variables' => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'visit_code',
            'visit_date',
            'doctor_name',
            'payment_amount',
            'payment_mode',
            'total_amount',
            'total_paid',
            'balance_due',
        ],
    ],

    // ── Print Invoice ─────────────────────────────────
    [
        'hook_key' => 'visit_print_invoice',
        'label' => 'Print Invoice',
        'module' => 'patients',
        'description' => 'Triggered when user prints the invoice from Actions menu',
        'variables' => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'visit_code',
            'visit_date',
            'doctor_name',
            'total_amount',
        ],
    ],

    // ── Print Receipts ────────────────────────────────
    [
        'hook_key' => 'visit_print_receipts',
        'label' => 'Print Receipts',
        'module' => 'patients',
        'description' => 'Triggered when user prints receipts from Actions menu',
        'variables' => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'visit_code',
            'visit_date',
            'doctor_name',
            'total_amount',
        ],
    ],

    // ── Print Report – With Letterhead ─────────────────
    [
        'hook_key' => 'visit_print_report_with_letterhead',
        'label' => 'Print Report (With Letterhead)',
        'module' => 'patients',
        'description' => 'Triggered when user prints lab reports with letterhead from Actions menu',
        'variables' => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'visit_code',
            'visit_date',
            'doctor_name',
            'total_amount',
        ],
    ],

    // ── Print Report – Without Letterhead ──────────────
    [
        'hook_key' => 'visit_print_report_without_letterhead',
        'label' => 'Print Report (Without Letterhead)',
        'module' => 'patients',
        'description' => 'Triggered when user prints lab reports without letterhead from Actions menu',
        'variables' => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'visit_code',
            'visit_date',
            'doctor_name',
            'total_amount',
        ],
    ],

    // ── Download PDF – With Letterhead ─────────────────
    [
        'hook_key' => 'visit_download_pdf_with_letterhead',
        'label' => 'Download PDF (With Letterhead)',
        'module' => 'patients',
        'description' => 'Triggered when user downloads lab report PDF with letterhead from Actions menu',
        'variables' => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'visit_code',
            'visit_date',
            'doctor_name',
            'total_amount',
        ],
    ],

    // ── Download PDF – Without Letterhead ──────────────
    [
        'hook_key' => 'visit_download_pdf_without_letterhead',
        'label' => 'Download PDF (Without Letterhead)',
        'module' => 'patients',
        'description' => 'Triggered when user downloads lab report PDF without letterhead from Actions menu',
        'variables' => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'visit_code',
            'visit_date',
            'doctor_name',
            'total_amount',
        ],
    ],

    // ── Visit Actions: Send Report via SMS ─────────────
    [
        'hook_key' => 'visit_send_report_sms',
        'label' => 'Send Report via SMS',
        'module' => 'patients',
        'description' => 'Fires when user sends a lab report via SMS from the visit Actions menu',
        'variables' => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'visit_code',
            'visit_date',
            'doctor_name',
            'total_amount',
            'report_link',
        ],
    ],

    // ── Visit Actions: Send Report via Official WhatsApp ─────────────
    [
        'hook_key' => 'visit_send_report_official_whatsapp',
        'label' => 'Send Report via Official WhatsApp',
        'module' => 'patients',
        'description' => 'Fires when user sends a lab report via Official WhatsApp from the visit Actions menu',
        'variables' => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'visit_code',
            'visit_date',
            'doctor_name',
            'total_amount',
            'report_link',
        ],
    ],

    // ── Visit Actions: Send Report via Email ─────────────
    [
        'hook_key' => 'visit_send_report_email',
        'label' => 'Send Report via Email',
        'module' => 'patients',
        'description' => 'Fires when user sends a lab report via Email from the visit Actions menu',
        'variables' => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'visit_code',
            'visit_date',
            'doctor_name',
            'total_amount',
            'report_link',
        ],
    ],

];
