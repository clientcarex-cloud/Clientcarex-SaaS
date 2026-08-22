<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ──────────────────────────────────────────────────────
 *  ANTENATAL MODULE — Hook Definitions
 * ──────────────────────────────────────────────────────
 *
 * Each entry must contain:
 *   hook_key, label, module, description, variables
 */

return [

    // ── Pregnancy Registered ───────────────────────────
    [
        'hook_key'    => 'anc_enrolled',
        'label'       => 'ANC — Pregnancy Registered',
        'module'      => 'antenatal',
        'description' => 'Triggered when a pregnancy is registered in the Antenatal module',
        'variables'   => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'ga_weeks',
            'edd',
            'doctor_name',
        ],
    ],

    // ── ANC Visit Reminder ─────────────────────────────
    [
        'hook_key'    => 'anc_visit_reminder',
        'label'       => 'ANC — Visit Reminder',
        'module'      => 'antenatal',
        'description' => 'Triggered automatically N days before a scheduled antenatal visit (configurable in Antenatal settings)',
        'variables'   => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'ga_weeks',
            'edd',
            'doctor_name',
            'scheduled_date',
            'purpose',
            'investigations',
        ],
    ],

    // ── ANC Visit Missed ───────────────────────────────
    [
        'hook_key'    => 'anc_visit_missed',
        'label'       => 'ANC — Visit Missed',
        'module'      => 'antenatal',
        'description' => 'Triggered automatically when a scheduled antenatal visit passes its grace period without the patient visiting',
        'variables'   => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'ga_weeks',
            'edd',
            'doctor_name',
            'scheduled_date',
            'purpose',
        ],
    ],

    // ── High Risk Flagged ──────────────────────────────
    [
        'hook_key'    => 'anc_high_risk',
        'label'       => 'ANC — High Risk Flagged',
        'module'      => 'antenatal',
        'description' => 'Triggered when a pregnancy is escalated to high risk by the automatic risk engine (useful to alert the obstetrician)',
        'variables'   => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'ga_weeks',
            'edd',
            'doctor_name',
            'risk_factors',
        ],
    ],

    // ── Delivery Recorded ──────────────────────────────
    [
        'hook_key'    => 'anc_delivered',
        'label'       => 'ANC — Delivery Recorded',
        'module'      => 'antenatal',
        'description' => 'Triggered when a delivery outcome is recorded (postnatal follow-ups are scheduled at the same time)',
        'variables'   => [
            'patient_name',
            'mobile_number',
            'patient_email',
            'mr_number',
            'edd',
            'doctor_name',
            'delivery_date',
            'delivery_mode',
        ],
    ],
];
