<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ═══════════════════════════════════════════════════════════════
 *  Seed pack — default EMAIL template per registered CCX hook
 * ═══════════════════════════════════════════════════════════════
 *
 * Consumed by sms_wa_email_seed_hook_email_templates()
 * (helpers/sms_wa_email_email_seeder_helper.php), which is what the
 * "Load Hook Templates" button on Omni Messaging → Email runs.
 *
 * One entry per hook_key in application/helpers/ccx_hooks/*_hooks.php:
 *
 *   title      Name shown in the Templates list. ALSO the idempotency key —
 *              renaming an entry makes the seeder create a second row, so
 *              treat these as stable.
 *   subject    Subject line. Tags are resolved at send time.
 *   content    HTML body FRAGMENT. The mailer wraps it with the CRM
 *              email_header / email_footer (logo + brand colours), so the
 *              fragment must never carry <html>/<body> of its own.
 *   core_slug  Optional slug in Setup → Email Templates. When that template
 *              exists on this installation, the seeder reuses ITS subject and
 *              message instead of the copy above — the admin's own wording
 *              wins over ours. See core_map.
 *   core_map   Core merge fields that the hook does not publish, rewritten to
 *              the equivalent hook variable. Anything still unresolvable after
 *              the rewrite makes the import bail out to `content`, so an
 *              imported body can never ship a literal {tag} to a patient.
 *   recipient  Default hook mapping created alongside the template, always
 *              INACTIVE — nothing sends until the admin flips the switch on
 *              the Hooks panel. null = seed the template only (no sensible
 *              default recipient exists, e.g. an alert with no e-mail tag).
 *
 * Every tag used below must exist in the hook's own `variables` list (plus the
 * system-wide {companyname}). helpers/sms_wa_email_email_seeder_helper.php ships
 * sms_wa_email_validate_hook_email_seed_pack() which asserts exactly that.
 */

// Accent per module family — the card's left rule and button colour.
$c_visit   = '#0369a1'; // patients   — clinical blue
$c_invoice = '#b45309'; // invoices   — amber
$c_refund  = '#be123c'; // refunds    — rose
$c_anc     = '#be185d'; // antenatal  — pink
$c_lead    = '#4f46e5'; // pro_leads  — indigo
$c_sales   = '#0d9488'; // pro_sales  — teal (meetings & demos)
$c_svc     = '#4338ca'; // pro_services— indigo (recurring billing)
$c_ticket  = '#7c3aed'; // pro_tickets— violet
$c_kiosk   = '#047857'; // self_kiosk — emerald
$c_hr      = '#0f766e'; // hr         — teal (people & policy)
$c_pay     = '#15803d'; // hr payroll — green (money)
$c_disc    = '#b91c1c'; // hr caution — red (discipline, absence, exit)

/**
 * Build one branded card.
 *
 * @param array $o accent, greeting, body, rows[label=>tag], quote[title,text],
 *                 cta[label,url], note, code, signoff
 * @return string HTML fragment
 */
$card = function (array $o) {
    $accent  = isset($o['accent']) ? $o['accent'] : '#0369a1';
    $greet   = isset($o['greeting']) ? $o['greeting'] : 'Hi {patient_name},';
    $signoff = isset($o['signoff']) ? $o['signoff'] : 'Warm regards';

    $html = '<div style="font-family:\'Segoe UI\',Helvetica,Arial,sans-serif;font-size:15px;line-height:1.62;color:#111827;">'
        . '<p style="margin:0 0 14px;">' . $greet . '</p>'
        . '<p style="margin:0 0 18px;">' . $o['body'] . '</p>';

    // Single hero value (an OTP, a rating) — big and unmissable
    if (!empty($o['code'])) {
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 18px;">'
            . '<tr><td align="center" style="padding:16px 12px;background:#f8fafc;border:1px dashed ' . $accent . ';border-radius:12px;">'
            . '<div style="font-size:12px;letter-spacing:1.5px;text-transform:uppercase;color:#6b7280;margin-bottom:6px;">' . $o['code']['label'] . '</div>'
            . '<div style="font-size:30px;font-weight:700;letter-spacing:6px;color:' . $accent . ';">' . $o['code']['value'] . '</div>'
            . '</td></tr></table>';
    }

    // Detail rows
    if (!empty($o['rows'])) {
        $rows = '';
        foreach ($o['rows'] as $label => $tag) {
            $rows .= '<tr>'
                . '<td style="padding:4px 0;width:44%;color:#6b7280;font-size:13.5px;">' . $label . '</td>'
                . '<td style="padding:4px 0;color:#111827;font-size:13.5px;font-weight:600;">' . $tag . '</td>'
                . '</tr>';
        }
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:separate;border-spacing:0;background:#f8fafc;border:1px solid #e5e7eb;border-left:4px solid ' . $accent . ';border-radius:10px;margin:0 0 18px;">'
            . '<tr><td style="padding:14px 16px;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%">' . $rows . '</table>'
            . '</td></tr></table>';
    }

    // Quoted message (a ticket reply, a lead note, a batch list)
    if (!empty($o['quote'])) {
        $html .= '<div style="margin:0 0 18px;">'
            . '<div style="font-size:12px;letter-spacing:.6px;text-transform:uppercase;color:#6b7280;margin-bottom:6px;">' . $o['quote']['title'] . '</div>'
            . '<div style="padding:12px 14px;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;color:#374151;white-space:pre-line;">' . $o['quote']['text'] . '</div>'
            . '</div>';
    }

    // Call to action
    if (!empty($o['cta'])) {
        $html .= '<p style="margin:0 0 18px;">'
            . '<a href="' . $o['cta']['url'] . '" style="display:inline-block;background:' . $accent . ';color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;padding:11px 22px;border-radius:8px;">' . $o['cta']['label'] . ' &rarr;</a>'
            . '</p>';
    }

    if (!empty($o['note'])) {
        $html .= '<p style="margin:0 0 4px;color:#6b7280;font-size:13px;">' . $o['note'] . '</p>';
    }

    $html .= '<p style="margin:18px 0 0;">' . $signoff . ',<br><strong>{companyname}</strong></p>'
        . '</div>';

    return $html;
};

// Rewrites shared by every core-template import.
$core_base = ['email_signature' => '{companyname}'];

// Core invoice templates address the contact; the hooks publish the same pair.
$core_invoice = $core_base;

// Core ticket templates address a contact and link by numeric id.
$core_ticket_customer = $core_base + [
    'contact_firstname'  => '{customer_name}',
    'contact_lastname'   => '',
    'ticket_public_url'  => '{ticket_url}',
    'ticket_id'          => '{ticket_number}',
];
$core_ticket_staff = $core_base + [
    'contact_firstname' => '{customer_name}',
    'contact_lastname'  => '',
    'ticket_url'        => '{admin_ticket_url}',
    'ticket_public_url' => '{ticket_url}',
    'ticket_id'         => '{ticket_number}',
];

return [

    // ══════════════════════════════════════════════════════
    //  PATIENTS — visits, billing, reports
    // ══════════════════════════════════════════════════════

    'visit_created' => [
        'title'     => 'Visit Registered',
        'subject'   => 'You are checked in — visit {visit_code}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_visit,
            'body'   => 'You are all checked in at <strong>{companyname}</strong>. Here is your visit summary — do keep it handy for your reports and billing.',
            'rows'   => [
                'MR Number'         => '{mr_number}',
                'Visit Code'        => '{visit_code}',
                'Visit Date'        => '{visit_date}',
                'Consulting Doctor' => '{doctor_name}',
                'Bill Amount'       => '{total_amount}',
            ],
            'note'   => 'Please quote your MR number whenever you call or visit us.',
        ]),
    ],

    'edit_visit_payment_received' => [
        'title'     => 'Visit Payment Received',
        'subject'   => 'Payment of {payment_amount} received — thank you',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_visit,
            'body'   => 'Thank you! We have recorded your payment against visit <strong>{visit_code}</strong>.',
            'rows'   => [
                'Amount Paid'   => '{payment_amount}',
                'Payment Mode'  => '{payment_mode}',
                'Bill Total'    => '{total_amount}',
                'Paid So Far'   => '{total_paid}',
                'Balance Due'   => '{balance_due}',
            ],
            'note'   => 'Treat this e-mail as your payment confirmation — nothing further is needed from you.',
        ]),
    ],

    'visit_print_invoice' => [
        'title'     => 'Invoice Copy Printed',
        'subject'   => 'Your invoice copy — visit {visit_code}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_visit,
            'body'   => 'Your invoice for visit <strong>{visit_code}</strong> has just been printed at our billing desk. Here is the summary for your records.',
            'rows'   => [
                'MR Number'    => '{mr_number}',
                'Visit Code'   => '{visit_code}',
                'Visit Date'   => '{visit_date}',
                'Doctor'       => '{doctor_name}',
                'Invoice Total' => '{total_amount}',
            ],
        ]),
    ],

    'visit_print_receipts' => [
        'title'     => 'Receipt Printed',
        'subject'   => 'Your payment receipt — visit {visit_code}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_visit,
            'body'   => 'Your payment receipt for visit <strong>{visit_code}</strong> is printed and waiting for you at the billing counter.',
            'rows'   => [
                'MR Number'   => '{mr_number}',
                'Visit Code'  => '{visit_code}',
                'Visit Date'  => '{visit_date}',
                'Amount'      => '{total_amount}',
            ],
        ]),
    ],

    'visit_print_report_with_letterhead' => [
        'title'     => 'Report Printed (Letterhead)',
        'subject'   => 'Your report is ready — visit {visit_code}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_visit,
            'body'   => 'Good news — your report has been printed on our official letterhead and is ready for collection at the reception.',
            'rows'   => [
                'MR Number'   => '{mr_number}',
                'Visit Code'  => '{visit_code}',
                'Visit Date'  => '{visit_date}',
                'Doctor'      => '{doctor_name}',
            ],
            'note'   => 'Please carry a valid ID when collecting reports on someone else\'s behalf.',
        ]),
    ],

    'visit_print_report_without_letterhead' => [
        'title'     => 'Report Printed (Plain)',
        'subject'   => 'Your report copy is ready — visit {visit_code}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_visit,
            'body'   => 'A plain copy of your report has been printed and is ready for collection at the reception.',
            'rows'   => [
                'MR Number'   => '{mr_number}',
                'Visit Code'  => '{visit_code}',
                'Visit Date'  => '{visit_date}',
                'Doctor'      => '{doctor_name}',
            ],
        ]),
    ],

    'visit_download_pdf_with_letterhead' => [
        'title'     => 'Report PDF Prepared (Letterhead)',
        'subject'   => 'Report PDF prepared — visit {visit_code}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_visit,
            'body'   => 'A PDF copy of your report, on our official letterhead, has been prepared for visit <strong>{visit_code}</strong>. Ask our team any time and we will e-mail or WhatsApp it across.',
            'rows'   => [
                'MR Number'   => '{mr_number}',
                'Visit Code'  => '{visit_code}',
                'Visit Date'  => '{visit_date}',
                'Doctor'      => '{doctor_name}',
            ],
        ]),
    ],

    'visit_download_pdf_without_letterhead' => [
        'title'     => 'Report PDF Prepared (Plain)',
        'subject'   => 'Report copy prepared — visit {visit_code}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_visit,
            'body'   => 'A plain PDF copy of your report has been prepared for visit <strong>{visit_code}</strong>. Ask our team any time and we will send it across.',
            'rows'   => [
                'MR Number'   => '{mr_number}',
                'Visit Code'  => '{visit_code}',
                'Visit Date'  => '{visit_date}',
                'Doctor'      => '{doctor_name}',
            ],
        ]),
    ],

    'visit_send_report_sms' => [
        'title'     => 'Report Shared by SMS',
        'subject'   => 'Your report link — visit {visit_code}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_visit,
            'body'   => 'We have texted your report link to <strong>{mobile_number}</strong>. Here it is again, in case you would rather open it from your inbox.',
            'rows'   => [
                'MR Number'   => '{mr_number}',
                'Visit Code'  => '{visit_code}',
                'Visit Date'  => '{visit_date}',
                'Doctor'      => '{doctor_name}',
            ],
            'cta'    => ['label' => 'View my report', 'url' => '{report_link}'],
            'note'   => 'The link is personal to you — please do not forward it.',
        ]),
    ],

    'visit_send_report_official_whatsapp' => [
        'title'     => 'Report Shared on WhatsApp',
        'subject'   => 'Your report is on its way — visit {visit_code}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_visit,
            'body'   => 'We have sent your report to your WhatsApp on <strong>{mobile_number}</strong>. Prefer e-mail? The same secure link is right here.',
            'rows'   => [
                'MR Number'   => '{mr_number}',
                'Visit Code'  => '{visit_code}',
                'Visit Date'  => '{visit_date}',
                'Doctor'      => '{doctor_name}',
            ],
            'cta'    => ['label' => 'View my report', 'url' => '{report_link}'],
            'note'   => 'The link is personal to you — please do not forward it.',
        ]),
    ],

    'visit_send_report_email' => [
        'title'     => 'Lab Report Delivery',
        'subject'   => 'Your report from {companyname} — visit {visit_code}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_visit,
            'body'   => 'Your report for visit <strong>{visit_code}</strong> is ready. Open it securely with the button below.',
            'rows'   => [
                'MR Number'   => '{mr_number}',
                'Visit Code'  => '{visit_code}',
                'Visit Date'  => '{visit_date}',
                'Doctor'      => '{doctor_name}',
            ],
            'cta'    => ['label' => 'Open my report', 'url' => '{report_link}'],
            'note'   => 'Please review the results with your doctor before acting on them.',
        ]),
    ],

    // ══════════════════════════════════════════════════════
    //  INVOICES — mirrors the Invoices section of Setup → Email Templates
    // ══════════════════════════════════════════════════════

    'invoice_send_to_client' => [
        'title'     => 'Invoice Sent to Customer',
        'subject'   => 'Invoice {invoice_number} from {companyname}',
        'core_slug' => 'invoice-send-to-client',
        'core_map'  => $core_invoice,
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_invoice,
            'greeting' => 'Dear {contact_firstname} {contact_lastname},',
            'body'     => 'Here is your invoice <strong>{invoice_number}</strong>. You can view or download it any time from the secure link below.',
            'rows'     => [
                'Invoice Number' => '{invoice_number}',
                'Invoice Date'   => '{invoice_date}',
                'Due Date'       => '{invoice_duedate}',
                'Amount Due'     => '{invoice_amount_due}',
                'Status'         => '{invoice_status}',
            ],
            'cta'      => ['label' => 'View invoice', 'url' => '{invoice_link}'],
            'note'     => 'Questions about this invoice? Just reply to this e-mail.',
        ]),
    ],

    'invoice_already_send' => [
        'title'     => 'Invoice Re-sent to Customer',
        'subject'   => 'Reminder: invoice {invoice_number} from {companyname}',
        'core_slug' => 'invoice-already-send',
        'core_map'  => $core_invoice,
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_invoice,
            'greeting' => 'Dear {contact_firstname} {contact_lastname},',
            'body'     => 'Sending invoice <strong>{invoice_number}</strong> across once more, so it is easy to find.',
            'rows'     => [
                'Invoice Number' => '{invoice_number}',
                'Due Date'       => '{invoice_duedate}',
                'Amount Due'     => '{invoice_amount_due}',
                'Status'         => '{invoice_status}',
            ],
            'cta'      => ['label' => 'View invoice', 'url' => '{invoice_link}'],
        ]),
    ],

    'invoice_payment_recorded' => [
        'title'     => 'Invoice Payment Receipt',
        'subject'   => 'Payment received — invoice {invoice_number}',
        'core_slug' => 'invoice-payment-recorded',
        'core_map'  => $core_invoice,
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_invoice,
            'greeting' => 'Dear {contact_firstname} {contact_lastname},',
            'body'     => 'Thank you — your payment has been received and recorded.',
            'rows'     => [
                'Amount Paid'    => '{payment_total}',
                'Paid On'        => '{payment_date}',
                'Payment Mode'   => '{payment_mode}',
                'Invoice Number' => '{invoice_number}',
                'Balance Due'    => '{invoice_amount_due}',
            ],
            'cta'      => ['label' => 'View invoice', 'url' => '{invoice_link}'],
            'note'     => 'This e-mail is your payment confirmation.',
        ]),
    ],

    'invoice_payment_recorded_to_staff' => [
        'title'     => 'Invoice Payment Alert (Staff)',
        'subject'   => 'Payment recorded — {invoice_number} ({payment_total})',
        'core_slug' => 'invoice-payment-recorded-to-staff',
        'core_map'  => $core_invoice,
        'recipient' => ['type' => 'custom', 'value' => '{invoice_sale_agent_email}'],
        'content'   => $card([
            'accent'   => $c_invoice,
            'greeting' => 'Hi team,',
            'body'     => 'A payment has just been recorded against invoice <strong>{invoice_number}</strong>.',
            'rows'     => [
                'Customer'       => '{client_company}',
                'Amount'         => '{payment_total}',
                'Payment Mode'   => '{payment_mode}',
                'Paid On'        => '{payment_date}',
                'Invoice Total'  => '{invoice_total}',
                'Balance Due'    => '{invoice_amount_due}',
            ],
            'cta'      => ['label' => 'Open invoice', 'url' => '{invoice_admin_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'invoice_overdue_notice' => [
        'title'     => 'Invoice Overdue Notice',
        'subject'   => 'Invoice {invoice_number} is overdue',
        'core_slug' => 'invoice-overdue-notice',
        'core_map'  => $core_invoice,
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_invoice,
            'greeting' => 'Dear {contact_firstname} {contact_lastname},',
            'body'     => 'A gentle reminder that invoice <strong>{invoice_number}</strong> is past its due date. If the payment is already on its way, please ignore this note.',
            'rows'     => [
                'Invoice Number' => '{invoice_number}',
                'Due Date'       => '{invoice_duedate}',
                'Days Overdue'   => '{total_days_overdue}',
                'Amount Due'     => '{invoice_amount_due}',
            ],
            'cta'      => ['label' => 'View & pay invoice', 'url' => '{invoice_link}'],
            'note'     => 'Already paid? Reply with the payment details and we will reconcile it right away.',
        ]),
    ],

    'invoice_due_notice' => [
        'title'     => 'Invoice Due Notice',
        'subject'   => 'Invoice {invoice_number} is due on {invoice_duedate}',
        'core_slug' => 'invoice-due-notice',
        'core_map'  => $core_invoice,
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_invoice,
            'greeting' => 'Dear {contact_firstname} {contact_lastname},',
            'body'     => 'Just a friendly heads-up — invoice <strong>{invoice_number}</strong> falls due shortly.',
            'rows'     => [
                'Invoice Number' => '{invoice_number}',
                'Due Date'       => '{invoice_duedate}',
                'Amount Due'     => '{invoice_amount_due}',
            ],
            'cta'      => ['label' => 'View invoice', 'url' => '{invoice_link}'],
        ]),
    ],

    'invoices_batch_payments' => [
        'title'     => 'Batch Payments Receipt',
        'subject'   => 'We have received your payments — thank you',
        'core_slug' => 'invoices-batch-payments',
        'core_map'  => $core_invoice,
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_invoice,
            'greeting' => 'Dear {contact_firstname} {contact_lastname},',
            'body'     => 'Thank you — we have recorded <strong>{batch_payments_count}</strong> payments totalling <strong>{batch_payments_total}</strong>.',
            'rows'     => [
                'Invoices Settled' => '{batch_invoice_numbers}',
                'Payments'         => '{batch_payments_count}',
                'Total Received'   => '{batch_payments_total}',
            ],
            'quote'    => ['title' => 'Payment details', 'text' => '{batch_payments_list}'],
            'note'     => 'This e-mail is your receipt for all of the payments listed above.',
        ]),
    ],

    // ══════════════════════════════════════════════════════
    //  REFUNDS & CANCELLATIONS
    // ══════════════════════════════════════════════════════

    'refund_and_cancellation' => [
        'title'     => 'Cancellation & Refund Confirmation',
        'subject'   => 'Cancellation & refund confirmed — visit {visit_code}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_refund,
            'body'   => 'The selected items on visit <strong>{visit_code}</strong> have been cancelled and your refund of <strong>{refund_amount}</strong> is processed.',
            'rows'   => [
                'MR Number'      => '{mr_number}',
                'Visit Code'     => '{visit_code}',
                'Visit Date'     => '{visit_date}',
                'Refund Amount'  => '{refund_amount}',
                'Refund Mode'    => '{payment_mode}',
                'Bill Total'     => '{total_amount}',
                'Total Paid'     => '{total_paid}',
                'Total Refunded' => '{total_refunded}',
                'Remarks'        => '{note}',
            ],
            'note'   => 'Bank refunds usually reflect within 5–7 working days.',
        ]),
    ],

    'only_refund' => [
        'title'     => 'Refund Confirmation',
        'subject'   => 'Refund of {refund_amount} processed — visit {visit_code}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_refund,
            'body'   => 'Your refund of <strong>{refund_amount}</strong> against visit <strong>{visit_code}</strong> has been processed. No items were cancelled.',
            'rows'   => [
                'MR Number'      => '{mr_number}',
                'Visit Code'     => '{visit_code}',
                'Refund Amount'  => '{refund_amount}',
                'Refund Mode'    => '{payment_mode}',
                'Total Paid'     => '{total_paid}',
                'Total Refunded' => '{total_refunded}',
                'Remarks'        => '{note}',
            ],
            'note'   => 'Bank refunds usually reflect within 5–7 working days.',
        ]),
    ],

    'only_cancellation' => [
        'title'     => 'Cancellation Confirmation',
        'subject'   => 'Items cancelled — visit {visit_code}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_refund,
            'body'   => 'The selected items on visit <strong>{visit_code}</strong> have been cancelled. No amount has been debited or refunded for this change.',
            'rows'   => [
                'MR Number'   => '{mr_number}',
                'Visit Code'  => '{visit_code}',
                'Visit Date'  => '{visit_date}',
                'Bill Total'  => '{total_amount}',
                'Total Paid'  => '{total_paid}',
                'Remarks'     => '{note}',
            ],
            'note'   => 'Need something re-booked? Reply to this e-mail or call our front desk.',
        ]),
    ],

    // ══════════════════════════════════════════════════════
    //  ANTENATAL — pregnancy follow-up
    // ══════════════════════════════════════════════════════

    'anc_enrolled' => [
        'title'     => 'ANC Registration Welcome',
        'subject'   => 'Your pregnancy care is registered with {companyname}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_anc,
            'body'   => 'Congratulations! Your antenatal care is now registered with us, and we will be beside you at every step until your delivery.',
            'rows'   => [
                'MR Number'        => '{mr_number}',
                'Current Weeks'    => '{ga_weeks}',
                'Expected Due Date'=> '{edd}',
                'Your Doctor'      => '{doctor_name}',
            ],
            'note'   => 'You will get a reminder from us before every scheduled visit.',
        ]),
    ],

    'anc_visit_reminder' => [
        'title'     => 'ANC Visit Reminder',
        'subject'   => 'Your antenatal visit on {scheduled_date}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_anc,
            'body'   => 'This is a friendly reminder about your next antenatal visit. We are looking forward to seeing you.',
            'rows'   => [
                'Visit Date'      => '{scheduled_date}',
                'Purpose'         => '{purpose}',
                'Investigations'  => '{investigations}',
                'Current Weeks'   => '{ga_weeks}',
                'Your Doctor'     => '{doctor_name}',
            ],
            'note'   => 'Please arrive 10 minutes early and carry your ANC card.',
        ]),
    ],

    'anc_visit_missed' => [
        'title'     => 'ANC Visit Missed',
        'subject'   => 'We missed you on {scheduled_date}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_anc,
            'body'   => 'We did not see you for your antenatal visit on <strong>{scheduled_date}</strong>. These check-ups matter for you and your baby — may we help you pick a new date?',
            'rows'   => [
                'Missed Visit'   => '{scheduled_date}',
                'Purpose'        => '{purpose}',
                'Current Weeks'  => '{ga_weeks}',
                'Expected Due Date' => '{edd}',
                'Your Doctor'    => '{doctor_name}',
            ],
            'note'   => 'Reply to this e-mail or call our front desk and we will reschedule right away.',
        ]),
    ],

    'anc_high_risk' => [
        // Meant for the obstetrician; no staff e-mail tag exists on this hook,
        // so the admin picks the Staff / Role recipient themselves.
        'title'     => 'ANC High-Risk Alert (Internal)',
        'subject'   => 'High-risk flag: {patient_name} at {ga_weeks} weeks',
        'recipient' => null,
        'content'   => $card([
            'accent'   => $c_anc,
            'greeting' => 'Hello Doctor,',
            'body'     => 'The antenatal risk engine has escalated a pregnancy to <strong>high risk</strong>. Please review at your earliest.',
            'rows'     => [
                'Patient'          => '{patient_name}',
                'MR Number'        => '{mr_number}',
                'Current Weeks'    => '{ga_weeks}',
                'Expected Due Date'=> '{edd}',
                'Risk Factors'     => '{risk_factors}',
                'Contact'          => '{mobile_number}',
            ],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'anc_delivered' => [
        'title'     => 'Delivery Congratulations',
        'subject'   => 'Congratulations, {patient_name}!',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_anc,
            'body'   => 'Warmest congratulations from all of us at <strong>{companyname}</strong>. Wishing you and your little one health and happiness.',
            'rows'   => [
                'Delivery Date' => '{delivery_date}',
                'Delivery Mode' => '{delivery_mode}',
                'Your Doctor'   => '{doctor_name}',
                'MR Number'     => '{mr_number}',
            ],
            'note'   => 'Your postnatal check-ups are already scheduled — we will remind you before each one.',
        ]),
    ],

    // ══════════════════════════════════════════════════════
    //  PRO LEADS
    // ══════════════════════════════════════════════════════

    'pro_lead_created' => [
        'title'     => 'Enquiry Received (Lead)',
        'subject'   => 'Thanks for reaching out to {companyname}',
        'core_slug' => 'new-web-to-lead-form-submitted',
        'core_map'  => $core_base,
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_lead,
            'greeting' => 'Hi {lead_name},',
            'body'     => 'Thank you for your enquiry — it is with our team now and someone will get in touch with you shortly.',
            'rows'     => [
                'Reference'  => '#{lead_id}',
                'Enquiry'    => '{lead_title}',
                'Received On'=> '{lead_date}',
            ],
            'note'     => 'Need us sooner? Simply reply to this e-mail.',
        ]),
    ],

    'pro_lead_assigned' => [
        'title'     => 'Lead Assigned (Agent)',
        'subject'   => 'New lead assigned to you: {lead_name}',
        'core_slug' => 'new-lead-assigned',
        'core_map'  => $core_base + [
            'lead_assigned' => '{assigned_name}',
            'lead_email'    => '{email}',
            'lead_link'     => '{lead_url}',
        ],
        'recipient' => ['type' => 'custom', 'value' => '{assigned_email}'],
        'content'   => $card([
            'accent'   => $c_lead,
            'greeting' => 'Hi {assigned_name},',
            'body'     => 'A new lead is on your plate. Call while it is hot — the first response usually wins.',
            'rows'     => [
                'Lead'    => '{lead_name}',
                'Phone'   => '{mobile_number}',
                'E-mail'  => '{email}',
                'Source'  => '{lead_source}',
                'City'    => '{lead_city}',
                'Value'   => '{lead_value}',
                'Status'  => '{lead_status}',
            ],
            'cta'      => ['label' => 'Open lead', 'url' => '{lead_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'pro_lead_status_changed' => [
        'title'     => 'Lead Status Changed (Agent)',
        'subject'   => '{lead_name} moved to {lead_status}',
        'recipient' => ['type' => 'custom', 'value' => '{assigned_email}'],
        'content'   => $card([
            'accent'   => $c_lead,
            'greeting' => 'Hi {assigned_name},',
            'body'     => 'A lead has moved along the pipeline.',
            'rows'     => [
                'Lead'          => '{lead_name}',
                'Previous'      => '{old_status}',
                'Now'           => '{lead_status}',
                'Changed By'    => '{changed_by}',
                'Value'         => '{lead_value}',
            ],
            'cta'      => ['label' => 'Open lead', 'url' => '{lead_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'pro_lead_updated' => [
        'title'     => 'Lead Updated (Agent)',
        'subject'   => 'Lead updated: {lead_name}',
        'recipient' => ['type' => 'custom', 'value' => '{assigned_email}'],
        'content'   => $card([
            'accent'   => $c_lead,
            'greeting' => 'Hi {assigned_name},',
            'body'     => 'The details of a lead you own have just been saved.',
            'rows'     => [
                'Lead'         => '{lead_name}',
                'Status'       => '{lead_status}',
                'Value'        => '{lead_value}',
                'Last Contact' => '{lead_last_contact}',
                'Updated By'   => '{changed_by}',
            ],
            'cta'      => ['label' => 'Open lead', 'url' => '{lead_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'pro_lead_note_added' => [
        'title'     => 'Lead Note Added (Agent)',
        'subject'   => 'New note on {lead_name}',
        'recipient' => ['type' => 'custom', 'value' => '{assigned_email}'],
        'content'   => $card([
            'accent'   => $c_lead,
            'greeting' => 'Hi {assigned_name},',
            'body'     => 'A note has been added to one of your leads.',
            'rows'     => [
                'Lead'      => '{lead_name}',
                'Added By'  => '{note_by}',
                'Status'    => '{lead_status}',
            ],
            'quote'    => ['title' => 'Note', 'text' => '{note_excerpt}'],
            'cta'      => ['label' => 'Open lead', 'url' => '{lead_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'pro_lead_contacted' => [
        'title'     => 'Follow-up After Call (Lead)',
        'subject'   => 'Great speaking with you, {lead_name}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_lead,
            'greeting' => 'Hi {lead_name},',
            'body'     => 'Thank you for your time on the call. If anything else comes to mind, just reply to this e-mail — we are happy to help.',
            'rows'     => [
                'Reference'      => '#{lead_id}',
                'Spoke With'     => '{contacted_by}',
                'Last Contacted' => '{contacted_at}',
            ],
        ]),
    ],

    'pro_lead_marked_lost' => [
        'title'     => 'Lead Marked Lost (Agent)',
        'subject'   => 'Lead marked lost: {lead_name}',
        'recipient' => ['type' => 'custom', 'value' => '{assigned_email}'],
        'content'   => $card([
            'accent'   => $c_lead,
            'greeting' => 'Hi {assigned_name},',
            'body'     => 'A lead has been marked as <strong>lost</strong>. Worth one last look before it is closed out?',
            'rows'     => [
                'Lead'      => '{lead_name}',
                'Source'    => '{lead_source}',
                'Value'     => '{lead_value}',
                'Marked By' => '{marked_by}',
            ],
            'cta'      => ['label' => 'Open lead', 'url' => '{lead_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'pro_lead_marked_junk' => [
        'title'     => 'Lead Marked Junk (Agent)',
        'subject'   => 'Lead marked junk: {lead_name}',
        'recipient' => ['type' => 'custom', 'value' => '{assigned_email}'],
        'content'   => $card([
            'accent'   => $c_lead,
            'greeting' => 'Hi {assigned_name},',
            'body'     => 'A lead has been marked as <strong>junk</strong> and removed from the active pipeline.',
            'rows'     => [
                'Lead'      => '{lead_name}',
                'Phone'     => '{mobile_number}',
                'Source'    => '{lead_source}',
                'Marked By' => '{marked_by}',
            ],
            'cta'      => ['label' => 'Open lead', 'url' => '{lead_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'pro_lead_converted' => [
        'title'     => 'Lead Converted Welcome',
        'subject'   => 'Welcome aboard, {lead_name}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_lead,
            'greeting' => 'Hi {lead_name},',
            'body'     => 'Welcome to <strong>{companyname}</strong>! Your account is set up and our team is ready to look after you.',
            'rows'     => [
                'Customer ID'   => '{customer_id}',
                'Your Contact'  => '{assigned_name}',
                'Contact Phone' => '{assigned_mobile}',
            ],
            'note'     => 'Keep this e-mail handy — replying to it reaches your account team directly.',
        ]),
    ],

    'pro_lead_auto_junk' => [
        'title'     => 'Lead Auto-Junked (Internal)',
        'subject'   => 'Auto-junked lead: {lead_name}',
        'recipient' => ['type' => 'custom', 'value' => '{assigned_email}'],
        'content'   => $card([
            'accent'   => $c_lead,
            'greeting' => 'Hi team,',
            'body'     => 'Roller Coaster junked an incoming lead because its phone number does not look real. Sharing it in case the source needs a fix.',
            'rows'     => [
                'Lead'        => '{lead_name}',
                'Phone'       => '{mobile_number}',
                'Digits Found'=> '{phone_digits}',
                'Reason'      => '{junk_reason}',
                'Source'      => '{lead_source}',
            ],
            'cta'      => ['label' => 'Open lead', 'url' => '{lead_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'pro_lead_assign_failed' => [
        // No agent exists by definition — map this one to a supervisor.
        'title'     => 'Lead Not Routed (Internal)',
        'subject'   => 'Lead not routed: {lead_name}',
        'recipient' => null,
        'content'   => $card([
            'accent'   => $c_lead,
            'greeting' => 'Hi team,',
            'body'     => 'Roller Coaster could not hand this lead to any agent, so it is sitting unassigned. Please route it manually.',
            'rows'     => [
                'Lead'    => '{lead_name}',
                'Phone'   => '{mobile_number}',
                'E-mail'  => '{email}',
                'Source'  => '{lead_source}',
                'Reason'  => '{fail_reason}',
                'Received'=> '{lead_date}',
            ],
            'cta'      => ['label' => 'Assign lead', 'url' => '{lead_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    // ══════════════════════════════════════════════════════
    //  PRO TICKETS
    // ══════════════════════════════════════════════════════

    'pro_ticket_created' => [
        'title'     => 'Ticket Opened Acknowledgement',
        'subject'   => 'We have got your request — ticket #{ticket_number}',
        'core_slug' => 'ticket-autoresponse',
        'core_map'  => $core_ticket_customer,
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_ticket,
            'greeting' => 'Hi {customer_name},',
            'body'     => 'Thank you for contacting our support team. Your ticket is open and we are on it.',
            'rows'     => [
                'Ticket'          => '#{ticket_number}',
                'Subject'         => '{ticket_subject}',
                'Department'      => '{ticket_department}',
                'Priority'        => '{ticket_priority}',
                'Opened On'       => '{ticket_date}',
                'First Reply By'  => '{sla_first_response_due}',
            ],
            'cta'      => ['label' => 'Track my ticket', 'url' => '{ticket_url}'],
            'note'     => 'Replying to this e-mail adds your message straight to the ticket.',
        ]),
    ],

    'pro_ticket_staff_reply' => [
        'title'     => 'Ticket Reply (Customer)',
        'subject'   => 'New reply on ticket #{ticket_number}',
        'core_slug' => 'ticket-reply',
        // Core quotes the newest message as {ticket_message}; the hook
        // publishes the same text as {reply_excerpt}.
        'core_map'  => $core_ticket_customer + ['ticket_message' => '{reply_excerpt}'],
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_ticket,
            'greeting' => 'Hi {customer_name},',
            'body'     => 'Our team has replied to your ticket.',
            'rows'     => [
                'Ticket'     => '#{ticket_number}',
                'Subject'    => '{ticket_subject}',
                'Replied By' => '{reply_by}',
                'Status'     => '{ticket_status}',
            ],
            'quote'    => ['title' => 'Latest reply', 'text' => '{reply_excerpt}'],
            'cta'      => ['label' => 'View full conversation', 'url' => '{ticket_url}'],
        ]),
    ],

    'pro_ticket_customer_reply' => [
        'title'     => 'Customer Replied (Agent)',
        'subject'   => 'Customer replied on #{ticket_number}',
        'core_slug' => 'ticket-reply-to-admin',
        'core_map'  => $core_ticket_staff + ['ticket_message' => '{reply_excerpt}'],
        'recipient' => ['type' => 'custom', 'value' => '{assigned_email}'],
        'content'   => $card([
            'accent'   => $c_ticket,
            'greeting' => 'Hi {assigned_name},',
            'body'     => 'The ball is back in your court — the customer has replied.',
            'rows'     => [
                'Ticket'     => '#{ticket_number}',
                'Subject'    => '{ticket_subject}',
                'Customer'   => '{customer_name}',
                'Priority'   => '{ticket_priority}',
                'Replied By' => '{reply_by}',
            ],
            'quote'    => ['title' => 'Their message', 'text' => '{reply_excerpt}'],
            'cta'      => ['label' => 'Open ticket', 'url' => '{admin_ticket_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'pro_ticket_assigned' => [
        'title'     => 'Ticket Assigned (Agent)',
        'subject'   => 'Ticket #{ticket_number} is yours',
        'core_slug' => 'ticket-assigned-to-admin',
        'core_map'  => $core_ticket_staff,
        'recipient' => ['type' => 'custom', 'value' => '{assigned_email}'],
        'content'   => $card([
            'accent'   => $c_ticket,
            'greeting' => 'Hi {assigned_name},',
            'body'     => 'A support ticket has been assigned to you.',
            'rows'     => [
                'Ticket'      => '#{ticket_number}',
                'Subject'     => '{ticket_subject}',
                'Customer'    => '{customer_name}',
                'Department'  => '{ticket_department}',
                'Priority'    => '{ticket_priority}',
                'Opened On'   => '{ticket_date}',
                'Why You'     => '{assign_reason}',
            ],
            'cta'      => ['label' => 'Open ticket', 'url' => '{admin_ticket_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'pro_ticket_status_changed' => [
        'title'     => 'Ticket Status Changed (Customer)',
        'subject'   => 'Ticket #{ticket_number} is now {ticket_status}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_ticket,
            'greeting' => 'Hi {customer_name},',
            'body'     => 'A quick update on your ticket — its status has changed.',
            'rows'     => [
                'Ticket'      => '#{ticket_number}',
                'Subject'     => '{ticket_subject}',
                'New Status'  => '{ticket_status}',
                'Updated By'  => '{changed_by}',
            ],
            'cta'      => ['label' => 'View ticket', 'url' => '{ticket_url}'],
        ]),
    ],

    'pro_ticket_closed' => [
        'title'     => 'Ticket Closed (Customer)',
        'subject'   => 'Ticket #{ticket_number} closed — how did we do?',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_ticket,
            'greeting' => 'Hi {customer_name},',
            'body'     => 'Your ticket is now closed. If we got it right, a quick rating means a lot to our team.',
            'rows'     => [
                'Ticket'          => '#{ticket_number}',
                'Subject'         => '{ticket_subject}',
                'Closed By'       => '{closed_by}',
                'Resolution Time' => '{resolution_time}',
            ],
            'cta'      => ['label' => 'Rate our support', 'url' => '{ticket_url}'],
            'note'     => 'Something still not right? Reply to this e-mail and the ticket reopens.',
        ]),
    ],

    'pro_ticket_reopened' => [
        'title'     => 'Ticket Reopened (Customer)',
        'subject'   => 'Ticket #{ticket_number} is open again',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_ticket,
            'greeting' => 'Hi {customer_name},',
            'body'     => 'Your ticket has been reopened and is back with our team.',
            'rows'     => [
                'Ticket'      => '#{ticket_number}',
                'Subject'     => '{ticket_subject}',
                'Reopened By' => '{changed_by}',
                'Times Reopened' => '{reopened_count}',
            ],
            'cta'      => ['label' => 'View ticket', 'url' => '{ticket_url}'],
        ]),
    ],

    'pro_ticket_sla_warning' => [
        'title'     => 'SLA At Risk (Agent)',
        'subject'   => 'SLA at risk: #{ticket_number} — {time_left} left',
        'recipient' => ['type' => 'custom', 'value' => '{assigned_email}'],
        'content'   => $card([
            'accent'   => $c_ticket,
            'greeting' => 'Hi {assigned_name},',
            'body'     => 'This ticket is running out of SLA time. A reply now keeps it green.',
            'rows'     => [
                'Ticket'    => '#{ticket_number}',
                'Subject'   => '{ticket_subject}',
                'Customer'  => '{customer_name}',
                'Stage'     => '{sla_stage}',
                'Due At'    => '{sla_due}',
                'Time Left' => '{time_left}',
                'Priority'  => '{ticket_priority}',
            ],
            'cta'      => ['label' => 'Open ticket', 'url' => '{admin_ticket_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'pro_ticket_sla_breached' => [
        'title'     => 'SLA Breached (Agent)',
        'subject'   => 'SLA breached: #{ticket_number}',
        'recipient' => ['type' => 'custom', 'value' => '{assigned_email}'],
        'content'   => $card([
            'accent'   => $c_ticket,
            'greeting' => 'Hi {assigned_name},',
            'body'     => 'An SLA deadline on this ticket has passed unmet. Please attend to it right away.',
            'rows'     => [
                'Ticket'     => '#{ticket_number}',
                'Subject'    => '{ticket_subject}',
                'Customer'   => '{customer_name}',
                'SLA Type'   => '{sla_type}',
                'Was Due'    => '{sla_due}',
                'Overdue By' => '{overdue_by}',
                'Priority'   => '{ticket_priority}',
            ],
            'cta'      => ['label' => 'Open ticket', 'url' => '{admin_ticket_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'pro_ticket_escalated' => [
        'title'     => 'Ticket Escalated (Escalation Contact)',
        'subject'   => 'Escalation: ticket #{ticket_number}',
        'recipient' => ['type' => 'custom', 'value' => '{escalate_to_email}'],
        'content'   => $card([
            'accent'   => $c_ticket,
            'greeting' => 'Hi {escalate_to_name},',
            'body'     => 'A breached ticket has been escalated to you as the SLA escalation contact.',
            'rows'     => [
                'Ticket'    => '#{ticket_number}',
                'Subject'   => '{ticket_subject}',
                'Customer'  => '{customer_name}',
                'Agent'     => '{assigned_name}',
                'SLA Type'  => '{sla_type}',
                'Priority'  => '{ticket_priority}',
                'Status'    => '{ticket_status}',
            ],
            'cta'      => ['label' => 'Open ticket', 'url' => '{admin_ticket_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'pro_ticket_auto_closed' => [
        'title'     => 'Ticket Auto-Closed (Customer)',
        'subject'   => 'Ticket #{ticket_number} closed automatically',
        'core_slug' => 'auto-close-ticket',
        'core_map'  => $core_ticket_customer,
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_ticket,
            'greeting' => 'Hi {customer_name},',
            'body'     => 'We have closed this ticket as it stayed quiet for {idle_days} days. We assumed all is well.',
            'rows'     => [
                'Ticket'     => '#{ticket_number}',
                'Subject'    => '{ticket_subject}',
                'Department' => '{ticket_department}',
                'Idle Days'  => '{idle_days}',
            ],
            'cta'      => ['label' => 'View ticket', 'url' => '{ticket_url}'],
            'note'     => 'Still need help? Reply to this e-mail and the ticket reopens.',
        ]),
    ],

    'pro_ticket_feedback_received' => [
        'title'     => 'Ticket Feedback Received (Agent)',
        'subject'   => 'Feedback on #{ticket_number}: {rating_label}',
        'recipient' => ['type' => 'custom', 'value' => '{assigned_email}'],
        'content'   => $card([
            'accent'   => $c_ticket,
            'greeting' => 'Hi {agent_name},',
            'body'     => 'The customer has rated your support on this ticket.',
            'rows'     => [
                'Ticket'   => '#{ticket_number}',
                'Subject'  => '{ticket_subject}',
                'Customer' => '{customer_name}',
                'Rating'   => '{rating} — {rating_label}',
            ],
            'quote'    => ['title' => 'Their comment', 'text' => '{feedback_comment}'],
            'cta'      => ['label' => 'Open ticket', 'url' => '{admin_ticket_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    // ══════════════════════════════════════════════════════
    //  SELF KIOSK
    // ══════════════════════════════════════════════════════

    'self_qr_otp' => [
        'title'     => 'Self Check-in OTP',
        'subject'   => 'Your check-in code is {otp_code}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent' => $c_kiosk,
            'body'   => 'Use this one-time code to finish your check-in at our self-service kiosk.',
            'code'   => ['label' => 'Your check-in code', 'value' => '{otp_code}'],
            'note'   => 'The code is valid for a few minutes and is meant only for you — please do not share it with anyone.',
        ]),
    ],

    // ══════════════════════════════════════════════════════
    //  HR — employee lifecycle
    // ══════════════════════════════════════════════════════
    //
    // The "patient" recipient type resolves {mobile_number} / {email}, which HR
    // hooks fill with the EMPLOYEE's contact details — so these land with the
    // employee out of the box. The three manager-facing hooks (leave applied,
    // field punch submitted, memo acknowledged) seed with no recipient: they
    // must be pointed at a staff member or a role on the Hooks panel.

    'hr_employee_added' => [
        'title'     => 'HR — Welcome On Board',
        'subject'   => 'Welcome to {companyname}, {employee_name}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_hr,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'A very warm welcome to the team! Your employee record is now active — here is what we have on file.',
            'rows'     => [
                'Employee Code' => '{employee_code}',
                'Designation'   => '{designation}',
                'Department'    => '{department}',
                'Date of Joining' => '{date_of_joining}',
                'Employment Type' => '{employment_type}',
                'Work Location' => '{work_location}',
                'Reporting To'  => '{reporting_to}',
            ],
            'note'     => 'Anything that looks wrong? Tell the HR desk and we will correct it right away.',
        ]),
    ],

    'hr_employee_birthday' => [
        'title'     => 'HR — Birthday Wishes',
        'subject'   => 'Happy birthday, {employee_name}!',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_hr,
            'greeting' => 'Dear {employee_name},',
            'body'     => 'Wishing you a very happy birthday! Thank you for everything you bring to the <strong>{department}</strong> team — we hope the year ahead is a wonderful one for you.',
            'signoff'  => 'With warm wishes from everyone at',
        ]),
    ],

    'hr_work_anniversary' => [
        'title'     => 'HR — Work Anniversary',
        'subject'   => '{years_completed} years with {companyname} — thank you!',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_hr,
            'greeting' => 'Dear {employee_name},',
            'body'     => 'Today marks <strong>{years_completed}</strong> year(s) since you joined us. Thank you for the care, the effort and the constancy — it has made a real difference.',
            'rows'     => [
                'Date of Joining' => '{date_of_joining}',
                'Years Completed' => '{years_completed}',
                'Designation'     => '{designation}',
                'Department'      => '{department}',
            ],
            'signoff'  => 'With appreciation from',
        ]),
    ],

    'hr_probation_ending' => [
        'title'     => 'HR — Probation Ending Soon',
        'subject'   => 'Probation ends in {days_left} days — {employee_name}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_hr,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'Your probation period is coming to an end. Your reporting manager will be in touch to close out the confirmation review.',
            'rows'     => [
                'Employee Code' => '{employee_code}',
                'Designation'   => '{designation}',
                'Department'    => '{department}',
                'Probation Ends' => '{probation_end}',
                'Days Left'     => '{days_left}',
            ],
        ]),
    ],

    // ── Documents ─────────────────────────────────────────

    'hr_document_verified' => [
        'title'     => 'HR — Document Verified',
        'subject'   => '{document_title} verified',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_hr,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'Good news — the document you submitted has been checked and accepted. Nothing more is needed from you on this one.',
            'rows'     => [
                'Document' => '{document_title}',
                'Type'     => '{document_type}',
                'Verified By' => '{reviewer_name}',
            ],
            'quote'    => ['title' => 'Note from HR', 'text' => '{review_note}'],
        ]),
    ],

    'hr_document_rejected' => [
        'title'     => 'HR — Document Rejected',
        'subject'   => 'Action needed on {document_title}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_disc,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'We could not accept the document you submitted. Please upload a corrected copy from your self-service portal.',
            'rows'     => [
                'Document' => '{document_title}',
                'Type'     => '{document_type}',
                'Reviewed By' => '{reviewer_name}',
            ],
            'quote'    => ['title' => 'Reason', 'text' => '{review_note}'],
        ]),
    ],

    'hr_document_expiring' => [
        'title'     => 'HR — Document Expiring',
        'subject'   => '{document_title} expires on {expiry_date}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_disc,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'One of the documents on your file is about to expire. Please share a renewed copy with HR before the date below so your record stays compliant.',
            'rows'     => [
                'Document'   => '{document_title}',
                'Type'       => '{document_type}',
                'Expires On' => '{expiry_date}',
                'Days Left'  => '{days_left}',
            ],
        ]),
    ],

    // ── Leave ─────────────────────────────────────────────

    'hr_leave_applied' => [
        'title'     => 'HR — Leave Applied (Approver)',
        'subject'   => 'Leave request from {employee_name} — {from_date} to {to_date}',
        'recipient' => null,
        'content'   => $card([
            'accent'   => $c_hr,
            'greeting' => 'Hi {recipient_name},',
            'body'     => 'A leave request is waiting for your decision.',
            'rows'     => [
                'Employee'   => '{employee_name} ({employee_code})',
                'Department' => '{department}',
                'Leave Type' => '{leave_type}',
                'From'       => '{from_date}',
                'To'         => '{to_date}',
                'Days'       => '{days}',
                'Half Day'   => '{half_day}',
            ],
            'quote'    => ['title' => 'Reason given', 'text' => '{reason}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'hr_leave_approved' => [
        'title'     => 'HR — Leave Approved',
        'subject'   => 'Your leave from {from_date} is approved',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_hr,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'Your leave request has been approved. Your attendance for these days is already marked, so there is nothing further for you to do.',
            'rows'     => [
                'Leave Type' => '{leave_type}',
                'From'       => '{from_date}',
                'To'         => '{to_date}',
                'Days'       => '{days}',
                'Half Day'   => '{half_day}',
                'Approved By' => '{approver_name}',
            ],
            'quote'    => ['title' => 'Note from your approver', 'text' => '{action_note}'],
        ]),
    ],

    'hr_leave_rejected' => [
        'title'     => 'HR — Leave Rejected',
        'subject'   => 'Your leave from {from_date} was not approved',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_disc,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'Your leave request could not be approved. Please speak with your reporting manager if you need to re-plan these dates.',
            'rows'     => [
                'Leave Type' => '{leave_type}',
                'From'       => '{from_date}',
                'To'         => '{to_date}',
                'Days'       => '{days}',
                'Decided By' => '{approver_name}',
            ],
            'quote'    => ['title' => 'Reason', 'text' => '{action_note}'],
        ]),
    ],

    // ── Attendance & shifts ───────────────────────────────

    'hr_attendance_absent' => [
        'title'     => 'HR — Marked Absent',
        'subject'   => 'You were marked absent on {attendance_date}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_disc,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'Our attendance register shows you as absent for the day below. If that is not right — a punch that did not register, or leave you had applied for — please raise it with HR.',
            'rows'     => [
                'Date'       => '{attendance_date}',
                'Employee Code' => '{employee_code}',
                'Department' => '{department}',
            ],
            'quote'    => ['title' => 'Remark on the register', 'text' => '{attendance_note}'],
        ]),
    ],

    'hr_shift_assigned' => [
        'title'     => 'HR — Shift Assigned',
        'subject'   => 'Your shift from {effective_from}: {shift_name}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_hr,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'Your duty shift has been updated. Please plan your reporting time accordingly.',
            'rows'     => [
                'Shift'          => '{shift_name}',
                'Starts'         => '{shift_start}',
                'Ends'           => '{shift_end}',
                'Effective From' => '{effective_from}',
            ],
        ]),
    ],

    'hr_field_punch_submitted' => [
        'title'     => 'HR — Field Punch Submitted (Reviewer)',
        'subject'   => '{employee_name} — {punch_type} awaiting approval',
        'recipient' => null,
        'content'   => $card([
            'accent'   => $c_hr,
            'greeting' => 'Hi {recipient_name},',
            'body'     => 'A field attendance punch is waiting for your review.',
            'rows'     => [
                'Employee' => '{employee_name} ({employee_code})',
                'Punch'    => '{punch_type}',
                'Date'     => '{punch_date}',
                'Time'     => '{punch_time}',
                'Purpose'  => '{punch_purpose}',
                'Site'     => '{punch_site}',
                'Distance' => '{punch_distance}',
                'Geofence' => '{geofence_status}',
            ],
            'quote'    => ['title' => 'Captured location', 'text' => '{punch_address}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'hr_field_punch_approved' => [
        'title'     => 'HR — Field Punch Approved',
        'subject'   => 'Your {punch_type} on {punch_date} is approved',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_hr,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'Your field punch has been approved and counted towards your attendance for the day.',
            'rows'     => [
                'Punch'       => '{punch_type}',
                'Date'        => '{punch_date}',
                'Time'        => '{punch_time}',
                'Approved By' => '{reviewer_name}',
            ],
            'quote'    => ['title' => 'Note from the reviewer', 'text' => '{review_note}'],
        ]),
    ],

    'hr_field_punch_rejected' => [
        'title'     => 'HR — Field Punch Rejected',
        'subject'   => 'Your {punch_type} on {punch_date} was rejected',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_disc,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'Your field punch was not approved, so it does not count towards your attendance. Please check with your reviewer before punching again.',
            'rows'     => [
                'Punch'       => '{punch_type}',
                'Date'        => '{punch_date}',
                'Time'        => '{punch_time}',
                'Rejected By' => '{reviewer_name}',
            ],
            'quote'    => ['title' => 'Reason', 'text' => '{review_note}'],
        ]),
    ],

    // ── Payroll ───────────────────────────────────────────

    'hr_payslip_published' => [
        'title'     => 'HR — Payslip Published',
        'subject'   => 'Your payslip for {pay_period} is ready',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_pay,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'Your payslip for <strong>{pay_period}</strong> is now available in your self-service portal.',
            'rows'     => [
                'Pay Period'   => '{pay_period}',
                'Gross Pay'    => '{gross_pay}',
                'Deductions'   => '{total_deductions}',
                'Net Pay'      => '{net_pay}',
                'Payable Days' => '{payable_days}',
                'Loss of Pay Days' => '{lop_days}',
            ],
            'note'     => 'Something not adding up? Write to the payroll desk before the next cycle closes.',
        ]),
    ],

    'hr_salary_paid' => [
        'title'     => 'HR — Salary Paid',
        'subject'   => 'Salary for {pay_period} credited',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_pay,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'Your salary for <strong>{pay_period}</strong> has been released. Please allow your bank a little time to reflect it.',
            'rows'     => [
                'Pay Period'   => '{pay_period}',
                'Net Amount'   => '{net_pay}',
                'Payment Mode' => '{payment_mode}',
                'Paid On'      => '{paid_date}',
            ],
        ]),
    ],

    // ── Discipline ────────────────────────────────────────

    'hr_memo_issued' => [
        'title'     => 'HR — Memo Issued',
        'subject'   => 'A memo has been issued to you: {memo_subject}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_disc,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'A memo has been recorded against your employee file. Please read it in your self-service portal and file your acknowledgement.',
            'rows'     => [
                'Subject'   => '{memo_subject}',
                'Type'      => '{memo_type}',
                'Severity'  => '{memo_severity}',
                'Incident Date' => '{incident_date}',
                'Penalty'   => '{penalty_amount}',
                'Issued By' => '{issuer_name}',
            ],
            'quote'     => ['title' => 'Action taken', 'text' => '{action_taken}'],
            'note'      => 'You may agree with the memo or record a dispute when you sign the acknowledgement.',
        ]),
    ],

    'hr_memo_acknowledged' => [
        'title'     => 'HR — Memo Acknowledged (Issuer)',
        'subject'   => '{employee_name} has responded to: {memo_subject}',
        'recipient' => null,
        'content'   => $card([
            'accent'   => $c_hr,
            'greeting' => 'Hi {recipient_name},',
            'body'     => 'The employee has signed the acknowledgement receipt for a memo you issued.',
            'rows'     => [
                'Employee'  => '{employee_name} ({employee_code})',
                'Memo'      => '{memo_subject}',
                'Response'  => '{memo_status}',
                'Signed As' => '{ack_signature}',
                'Issued By' => '{issuer_name}',
            ],
            'quote'    => ['title' => 'Their remarks', 'text' => '{ack_note}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    // ── Growth ────────────────────────────────────────────

    'hr_training_scheduled' => [
        'title'     => 'HR — Training Scheduled',
        'subject'   => 'You are enrolled: {training_title}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_hr,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'You have been nominated for a training programme. Please block your calendar and plan your duty accordingly.',
            'rows'     => [
                'Programme' => '{training_title}',
                'Category'  => '{training_category}',
                'Trainer'   => '{trainer}',
                'Starts'    => '{training_start}',
                'Ends'      => '{training_end}',
                'Venue'     => '{training_venue}',
            ],
        ]),
    ],

    'hr_appraisal_shared' => [
        'title'     => 'HR — Appraisal Shared',
        'subject'   => 'Your appraisal for {period_from} – {period_to} is ready',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_hr,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'Your performance review has been completed and is now visible in your self-service portal.',
            'rows'     => [
                'Review Period'  => '{period_from} to {period_to}',
                'Overall Rating' => '{overall_rating} / 5',
                'Reviewed By'    => '{reviewer_name}',
            ],
            'quote'    => ['title' => 'Strengths', 'text' => '{strengths}'],
            'note'     => 'Areas to work on: {improvements}<br>Goals for the next period: {goals}',
        ]),
    ],

    // ── Recruitment ───────────────────────────────────────

    'hr_interview_scheduled' => [
        'title'     => 'HR — Interview Scheduled',
        'subject'   => 'Your interview with {companyname} — {interview_datetime}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_hr,
            'greeting' => 'Dear {candidate_name},',
            'body'     => 'Thank you for your interest in joining us. Your interview has been scheduled — the details are below.',
            'rows'     => [
                'Position'  => '{position}',
                'Round'     => '{round_no} — {round_name}',
                'When'      => '{interview_datetime}',
                'Duration'  => '{duration_minutes} minutes',
                'Mode'      => '{interview_mode}',
                'Panel'     => '{interviewers}',
                'Meeting ID' => '{meeting_id}',
                'Passcode'  => '{meeting_password}',
            ],
            'cta'      => ['label' => 'Join / directions', 'url' => '{meeting_link}'],
            'note'     => 'Please join a few minutes early. If this time does not work for you, reply to this e-mail and we will re-schedule.',
        ]),
    ],

    // ── Exit ──────────────────────────────────────────────

    'hr_exit_initiated' => [
        'title'     => 'HR — Exit Initiated',
        'subject'   => 'Your exit formalities — last working day {last_working_day}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_disc,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'Your exit has been recorded and the clearance process has begun. HR will guide you through each step.',
            'rows'     => [
                'Employee Code'    => '{employee_code}',
                'Exit Type'        => '{exit_type}',
                'Notice Date'      => '{notice_date}',
                'Last Working Day' => '{last_working_day}',
            ],
            'quote'    => ['title' => 'Reason on record', 'text' => '{exit_reason}'],
            'note'     => 'Please complete your department, IT and asset clearances before your last working day.',
        ]),
    ],

    'hr_exit_settled' => [
        'title'     => 'HR — Full & Final Settled',
        'subject'   => 'Your full and final settlement is complete',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_pay,
            'greeting' => 'Dear {employee_name},',
            'body'     => 'Your full and final settlement has been closed. Thank you for the time you gave <strong>{companyname}</strong> — we wish you every success ahead.',
            'rows'     => [
                'Exit Type'         => '{exit_type}',
                'Last Working Day'  => '{last_working_day}',
                'Settlement Amount' => '{settlement_amount}',
            ],
            'quote'    => ['title' => 'Settlement note', 'text' => '{settlement_note}'],
            'signoff'  => 'With best wishes from',
        ]),
    ],

    // ── Communication ─────────────────────────────────────

    'hr_notice_published' => [
        'title'     => 'HR — Notice Published',
        'subject'   => '{notice_title}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_hr,
            'greeting' => 'Hi {employee_name},',
            'body'     => 'A new notice has been published for your attention.',
            'quote'    => ['title' => '{notice_title}', 'text' => '{notice_message}'],
            'rows'     => [
                'Priority' => '{notice_priority}',
                'From'     => '{notice_start}',
                'Until'    => '{notice_end}',
            ],
            'signoff'  => 'HR Department,',
        ]),
    ],

    // ══════════════════════════════════════════════════════
    //  PRO SALES — demo & sales meetings
    // ══════════════════════════════════════════════════════
    //
    // The prospect-facing templates always carry {reschedule_link}: a meeting
    // someone can move themselves is a meeting that happens instead of one that
    // is silently missed.

    'pro_sales_meeting_scheduled' => [
        'title'     => 'Meeting Confirmed',
        'subject'   => 'Confirmed — {meeting_type} on {meeting_date} at {meeting_time}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_sales,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'Your <strong>{meeting_type}</strong> with {host_name} is confirmed. Everything you need is below — the same details live on your meeting page, where you can also move or cancel the slot if plans change.',
            'rows'     => [
                'What'     => '{meeting_subject}',
                'When'     => '{meeting_datetime} ({meeting_timezone})',
                'Duration' => '{meeting_duration}',
                'Where'    => '{meeting_platform} — {meeting_location}',
                'Host'     => '{host_name}',
            ],
            'cta'      => ['label' => 'View my meeting', 'url' => '{meeting_link}'],
            'note'     => 'Need a different time? Reschedule in two clicks: {reschedule_link}',
        ]),
    ],

    'pro_sales_meeting_rescheduled' => [
        'title'     => 'Meeting Rescheduled',
        'subject'   => 'New time — {meeting_type} moved to {meeting_date}, {meeting_time}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_sales,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'Your meeting has been moved. Please note the new time — the old invitation and joining link no longer apply.',
            'rows'     => [
                'What'          => '{meeting_subject}',
                'New Time'      => '{meeting_datetime} ({meeting_timezone})',
                'Previous Time' => '{old_datetime}',
                'Duration'      => '{meeting_duration}',
                'Where'         => '{meeting_platform} — {meeting_location}',
                'Host'          => '{host_name}',
                'Moved By'      => '{rescheduled_by}',
            ],
            'quote'    => ['title' => 'Reason', 'text' => '{reschedule_reason}'],
            'cta'      => ['label' => 'View updated meeting', 'url' => '{meeting_link}'],
            'note'     => 'Still not right? Pick another slot here: {reschedule_link}',
        ]),
    ],

    'pro_sales_meeting_cancelled' => [
        'title'     => 'Meeting Cancelled',
        'subject'   => 'Cancelled — {meeting_type} on {meeting_date}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_sales,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'Your <strong>{meeting_type}</strong> scheduled for {meeting_datetime} has been cancelled, and the joining link has been closed.',
            'rows'     => [
                'What'          => '{meeting_subject}',
                'Was Scheduled' => '{meeting_datetime}',
                'Cancelled By'  => '{cancelled_by}',
            ],
            'quote'    => ['title' => 'Reason', 'text' => '{cancel_reason}'],
            'cta'      => ['label' => 'Book a new time', 'url' => '{reschedule_link}'],
            'note'     => 'We would still love to speak — pick whatever time suits you above.',
        ]),
    ],

    'pro_sales_meeting_reminder_day' => [
        'title'     => 'Meeting Reminder (First)',
        'subject'   => 'Reminder — {meeting_type} {reminder_window}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_sales,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'A friendly reminder that your <strong>{meeting_type}</strong> with {host_name} is {reminder_window}.',
            'rows'     => [
                'What'     => '{meeting_subject}',
                'When'     => '{meeting_datetime} ({meeting_timezone})',
                'Duration' => '{meeting_duration}',
                'Where'    => '{meeting_platform} — {meeting_location}',
            ],
            'cta'      => ['label' => 'View meeting details', 'url' => '{meeting_link}'],
            'note'     => 'Cannot make it? Move it to a better time: {reschedule_link}',
        ]),
    ],

    'pro_sales_meeting_reminder_hour' => [
        'title'     => 'Meeting Reminder (Starting Soon)',
        'subject'   => 'Starting {reminder_window} — {meeting_type} with {host_name}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_sales,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'Your meeting starts <strong>{reminder_window}</strong>. Use the button below to join when it is time.',
            'rows'     => [
                'What'  => '{meeting_subject}',
                'When'  => '{meeting_time} ({meeting_timezone})',
                'Where' => '{meeting_platform} — {meeting_location}',
                'Host'  => '{host_name}',
            ],
            'cta'      => ['label' => 'Join the meeting', 'url' => '{join_url}'],
            'note'     => 'Trouble joining? Open your meeting page: {meeting_link}',
        ]),
    ],

    'pro_sales_meeting_completed' => [
        'title'     => 'Thanks For Your Time (After Meeting)',
        'subject'   => 'Thanks for your time today',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_sales,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'Thank you for joining the <strong>{meeting_type}</strong> — it was good speaking with you. Here is a short recap of what we agreed.',
            'rows'     => [
                'Meeting'   => '{meeting_subject}',
                'Held On'   => '{meeting_datetime}',
                'With'      => '{host_name}',
                'Next Step' => '{outcome}',
            ],
            'quote'    => ['title' => 'Notes from our conversation', 'text' => '{outcome_notes}'],
            'note'     => 'Anything unclear? Just reply to this e-mail and {host_name} will pick it up.',
        ]),
    ],

    'pro_sales_meeting_no_show' => [
        'title'     => 'Sorry We Missed You',
        'subject'   => 'Sorry we missed you — shall we find another time?',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_sales,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'We had you down for a <strong>{meeting_type}</strong> at {meeting_datetime} but could not connect. No problem at all — pick any slot that works better for you.',
            'rows'     => [
                'Meeting' => '{meeting_subject}',
                'Was At'  => '{meeting_datetime}',
                'Host'    => '{host_name}',
            ],
            'cta'      => ['label' => 'Choose a new time', 'url' => '{reschedule_link}'],
        ]),
    ],

    'pro_sales_meeting_assigned' => [
        'title'     => 'Meeting Assigned (Executive)',
        'subject'   => 'New meeting on your calendar — {meeting_date} at {meeting_time}',
        'recipient' => ['type' => 'custom', 'value' => '{host_email}'],
        'content'   => $card([
            'accent'   => $c_sales,
            'greeting' => 'Hi {host_name},',
            'body'     => 'A meeting has been placed on your calendar. Review the prospect before you dial in.',
            'rows'     => [
                'What'        => '{meeting_subject}',
                'Type'        => '{meeting_type}',
                'When'        => '{meeting_datetime} ({meeting_timezone})',
                'Duration'    => '{meeting_duration}',
                'With'        => '{contact_name} — {company}',
                'Contact'     => '{mobile_number} / {email}',
                'Where'       => '{meeting_platform} — {meeting_location}',
                'Assigned By' => '{assigned_by}',
            ],
            'quote'    => ['title' => 'Agenda', 'text' => '{meeting_agenda}'],
            'cta'      => ['label' => 'Open in CRM', 'url' => '{admin_meeting_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

    'pro_sales_booking_received' => [
        'title'     => 'Slot Booked On Public Page (Internal)',
        'subject'   => 'New booking — {contact_name} took {meeting_date}, {meeting_time}',
        'recipient' => ['type' => 'custom', 'value' => '{host_email}'],
        'content'   => $card([
            'accent'   => $c_sales,
            'greeting' => 'Hi {host_name},',
            'body'     => 'Someone booked themselves a slot on the <strong>{booking_page}</strong> page. It is already on your calendar.',
            'rows'     => [
                'Who'          => '{contact_name} — {company}',
                'Contact'      => '{mobile_number} / {email}',
                'Booked'       => '{meeting_type}',
                'For'          => '{meeting_datetime} ({meeting_timezone})',
                'Where'        => '{meeting_platform} — {meeting_location}',
                'New Lead'     => '{lead_created}',
            ],
            'quote'    => ['title' => 'What they told us', 'text' => '{booking_answers}'],
            'cta'      => ['label' => 'Open in CRM', 'url' => '{admin_meeting_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],


    /* ═══════════════════════════════════════════════════════════════
     *  PRO SERVICES — recurring subscriptions, billing & collection
     * ═══════════════════════════════════════════════════════════════
     * Every one of these carries {payment_link} or {portal_link} where it
     * makes sense: the whole point of the module is that the customer can
     * settle the bill in one tap, on the CRM's own payment page.
     */

    'pro_services_subscription_created' => [
        'title'     => 'Subscription Started',
        'subject'   => 'Your {service_name} subscription is active',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_svc,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'Thank you for subscribing to <strong>{service_name}</strong>. Everything is set up — here is what you have signed up for.',
            'rows'     => [
                'Service'       => '{service_name}',
                'Plan'          => '{plan_name}',
                'Amount'        => '{subscription_amount} — {billing_cycle}',
                'Setup fee'     => '{setup_fee}',
                'Starts'        => '{start_date}',
                'First invoice' => '{first_billing_date}',
                'Reference'     => '{subscription_ref}',
            ],
            'cta'      => ['label' => 'View my subscription', 'url' => '{portal_link}'],
            'note'     => 'Your subscription page shows every invoice and lets you pay online at any time.',
        ]),
    ],

    'pro_services_invoice_generated' => [
        'title'     => 'Subscription Invoice Raised',
        'subject'   => 'Invoice {invoice_number} for {service_name} — {invoice_amount}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_svc,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'Your invoice for <strong>{service_name}</strong> is ready. You can pay it online in a few seconds using the button below.',
            'rows'     => [
                'Invoice'  => '{invoice_number}',
                'Period'   => '{billing_period}',
                'Amount'   => '{invoice_amount}',
                'Due by'   => '{invoice_due_date}',
                'Service'  => '{service_name} ({billing_cycle})',
            ],
            'cta'      => ['label' => 'Pay securely now', 'url' => '{payment_link}'],
            'note'     => 'All of your invoices live on your subscription page: {portal_link}',
        ]),
    ],

    'pro_services_payment_reminder' => [
        'title'     => 'Subscription Payment Reminder',
        'subject'   => 'Reminder: {invoice_amount} due {invoice_due_date}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_svc,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'A friendly reminder that your payment for <strong>{service_name}</strong> is due {reminder_window}. If you have already paid, please ignore this message.',
            'rows'     => [
                'Invoice'    => '{invoice_number}',
                'Amount due' => '{amount_due}',
                'Due date'   => '{invoice_due_date}',
                'Period'     => '{billing_period}',
            ],
            'cta'      => ['label' => 'Pay now', 'url' => '{payment_link}'],
        ]),
    ],

    'pro_services_payment_due_today' => [
        'title'     => 'Subscription Payment Due Today',
        'subject'   => 'Due today: {amount_due} for {service_name}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_svc,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'Your payment for <strong>{service_name}</strong> is due today. One tap settles it.',
            'rows'     => [
                'Invoice'    => '{invoice_number}',
                'Amount due' => '{amount_due}',
                'Period'     => '{billing_period}',
            ],
            'cta'      => ['label' => 'Pay now', 'url' => '{payment_link}'],
        ]),
    ],

    'pro_services_payment_overdue' => [
        'title'     => 'Subscription Payment Overdue',
        'subject'   => 'Overdue: {amount_due} for {service_name} ({overdue_stage})',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_svc,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'We have not yet received payment for <strong>{service_name}</strong>. The invoice is now <strong>{overdue_stage}</strong>. Please settle it to keep your service running without interruption.',
            'rows'     => [
                'Invoice'      => '{invoice_number}',
                'Amount due'   => '{amount_due}',
                'Was due on'   => '{invoice_due_date}',
                'Days overdue' => '{days_overdue}',
            ],
            'cta'      => ['label' => 'Pay now', 'url' => '{payment_link}'],
            'note'     => 'Already paid? Let us know and we will update our records straight away.',
        ]),
    ],

    'pro_services_payment_received' => [
        'title'     => 'Subscription Payment Received',
        'subject'   => 'Received — {payment_amount} for {service_name}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_svc,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'Thank you — we have received your payment for <strong>{service_name}</strong>.',
            'rows'     => [
                'Paid'          => '{payment_amount}',
                'Method'        => '{payment_mode}',
                'On'            => '{payment_date}',
                'Invoice'       => '{invoice_number}',
                'Still due'     => '{amount_due}',
                'Next invoice'  => '{next_billing_date}',
            ],
            'cta'      => ['label' => 'View my subscription', 'url' => '{portal_link}'],
        ]),
    ],

    'pro_services_renewal_upcoming' => [
        'title'     => 'Subscription Renewal Coming Up',
        'subject'   => '{service_name} renews on {renewal_date}',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_svc,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'Just so there are no surprises: your <strong>{service_name}</strong> subscription renews in {days_until_renewal} days.',
            'rows'     => [
                'Renews on' => '{renewal_date}',
                'Amount'    => '{renewal_amount}',
                'Cycle'     => '{billing_cycle}',
            ],
            'cta'      => ['label' => 'View my subscription', 'url' => '{portal_link}'],
            'note'     => 'Need to change anything before then? Just reply to this message.',
        ]),
    ],

    'pro_services_subscription_paused' => [
        'title'     => 'Subscription Paused',
        'subject'   => 'Your {service_name} subscription is paused',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_svc,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'Billing for <strong>{service_name}</strong> has been put on hold. No further invoices will be raised until it is resumed.',
            'rows'     => [
                'Service'   => '{service_name}',
                'Reason'    => '{pause_reason}',
                'Reference' => '{subscription_ref}',
            ],
            'cta'      => ['label' => 'View my subscription', 'url' => '{portal_link}'],
        ]),
    ],

    'pro_services_subscription_resumed' => [
        'title'     => 'Subscription Resumed',
        'subject'   => '{service_name} is active again',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_svc,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'Good news — your <strong>{service_name}</strong> subscription is running again.',
            'rows'     => [
                'Amount'       => '{subscription_amount} — {billing_cycle}',
                'Next invoice' => '{next_billing_date}',
            ],
            'cta'      => ['label' => 'View my subscription', 'url' => '{portal_link}'],
        ]),
    ],

    'pro_services_subscription_cancelled' => [
        'title'     => 'Subscription Cancelled',
        'subject'   => 'Your {service_name} subscription has been cancelled',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_svc,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'Your <strong>{service_name}</strong> subscription has been cancelled and no further invoices will be raised. We are sorry to see you go.',
            'rows'     => [
                'Service'          => '{service_name}',
                'Reason'           => '{cancel_reason}',
                'Cycles billed'    => '{cycles_billed}',
                'Still outstanding' => '{outstanding_amount}',
            ],
            'cta'      => ['label' => 'View my account', 'url' => '{portal_link}'],
            'note'     => 'Anything still outstanding stays payable from the link above.',
        ]),
    ],

    'pro_services_subscription_completed' => [
        'title'     => 'Subscription Term Completed',
        'subject'   => '{service_name} — your term is complete',
        'recipient' => ['type' => 'patient'],
        'content'   => $card([
            'accent'   => $c_svc,
            'greeting' => 'Hi {contact_name},',
            'body'     => 'Your <strong>{service_name}</strong> subscription has run its full term. Thank you for staying with us.',
            'rows'     => [
                'Service'        => '{service_name}',
                'Cycles billed'  => '{completed_cycles}',
                'Started'        => '{start_date}',
            ],
            'cta'      => ['label' => 'View my account', 'url' => '{portal_link}'],
            'note'     => 'Would you like to renew? Reply to this message and we will set it up.',
        ]),
    ],

    'pro_services_kpi_alert' => [
        'title'     => 'Pro Services KPI Off Target (Internal)',
        'subject'   => 'KPI off target — {kpi_name}: {kpi_value} vs {kpi_target}',
        'recipient' => null,
        'content'   => $card([
            'accent'   => $c_svc,
            'greeting' => 'Hi {patient_name},',
            'body'     => 'A Pro Services KPI has gone off target for <strong>{kpi_period}</strong>.',
            'rows'     => [
                'KPI'     => '{kpi_name}',
                'Metric'  => '{kpi_metric}',
                'Now'     => '{kpi_value}',
                'Target'  => '{kpi_target} ({kpi_direction})',
                'Gap'     => '{kpi_gap}',
                'Period'  => '{kpi_period}',
            ],
            'cta'      => ['label' => 'Open the KPI console', 'url' => '{admin_subscription_url}'],
            'signoff'  => 'Automated alert from',
        ]),
    ],

];
