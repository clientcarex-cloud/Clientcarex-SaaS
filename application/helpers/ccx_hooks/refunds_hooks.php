<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ──────────────────────────────────────────────────────
 *  REFUNDS MODULE — Hook Definitions
 * ──────────────────────────────────────────────────────
 *
 * Three hooks matching the refund popup tabs:
 *   1. Refund & Cancellation
 *   2. Only Refund
 *   3. Only Cancellation
 */

$shared_variables = [
    'patient_name',
    'mobile_number',
    // Primary contact e-mail, so the Email channel has a recipient to resolve
    'patient_email',
    'mr_number',
    'visit_code',
    'visit_date',
    'refund_amount',
    'refund_type',
    'payment_mode',
    'note',
    'total_amount',
    'total_paid',
    'total_refunded',
];

return [

    // ── Refund & Cancellation ──────────────────────────
    [
        'hook_key' => 'refund_and_cancellation',
        'label' => 'Refund & Cancellation',
        'module' => 'refunds',
        'description' => 'Triggered when items are cancelled and money is refunded',
        'variables' => $shared_variables,
    ],

    // ── Only Refund ────────────────────────────────────
    [
        'hook_key' => 'only_refund',
        'label' => 'Only Refund',
        'module' => 'refunds',
        'description' => 'Triggered when money is refunded without cancelling items',
        'variables' => $shared_variables,
    ],

    // ── Only Cancellation ──────────────────────────────
    [
        'hook_key' => 'only_cancellation',
        'label' => 'Only Cancellation',
        'module' => 'refunds',
        'description' => 'Triggered when items are cancelled without a monetary refund',
        'variables' => $shared_variables,
    ],

];
