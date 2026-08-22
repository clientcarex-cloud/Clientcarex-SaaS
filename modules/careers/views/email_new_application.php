<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Internal alert — one new application. The resume is attached by the caller.
 */
$answers = $application->answers ? json_decode($application->answers, true) : [];

$rows = [
    'Position'        => $job->title . ' (' . careers_job_type_label($job->job_type) . ')',
    'Reference'       => $application->reference,
    'Name'            => $application->name,
    'Email'           => $application->email,
    'Phone'           => $application->phone,
    'Experience'      => $application->total_experience !== null ? careers_trim_number($application->total_experience) . ' years' : '',
    'Current company' => $application->current_company,
    'Current CTC'     => $application->current_ctc,
    'Expected CTC'    => $application->expected_ctc,
    'Notice period'   => $application->notice_period,
    'Location'        => $application->current_location,
    'Source'          => $application->source,
];
?>
<div style="font-family:Arial,Helvetica,sans-serif;color:#334155;font-size:14px;line-height:1.6">
    <p style="margin-top:0">
        A new application has arrived for <strong><?= html_escape($job->title); ?></strong>.
    </p>

    <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:16px 0">
        <?php foreach ($rows as $label => $value) {
            if ($value === '' || $value === null) {
                continue;
            } ?>
            <tr>
                <td style="padding:7px 12px;background:#f8fafc;border:1px solid #e2e8f0;font-size:12px;color:#64748b;font-weight:bold;width:35%">
                    <?= html_escape($label); ?>
                </td>
                <td style="padding:7px 12px;border:1px solid #e2e8f0;font-size:13px;color:#0f172a">
                    <?= html_escape((string) $value); ?>
                </td>
            </tr>
        <?php } ?>
    </table>

    <?php if (!empty($application->cover_letter)) { ?>
        <div style="font-size:12px;color:#64748b;font-weight:bold;text-transform:uppercase;letter-spacing:.5px">Cover letter</div>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin:6px 0 16px;font-size:13px;white-space:pre-wrap">
            <?= html_escape($application->cover_letter); ?>
        </div>
    <?php } ?>

    <?php if (!empty($answers)) { ?>
        <div style="font-size:12px;color:#64748b;font-weight:bold;text-transform:uppercase;letter-spacing:.5px">Screening answers</div>
        <div style="margin:6px 0 16px">
            <?php foreach ($answers as $answer) { ?>
                <div style="margin-bottom:8px">
                    <div style="font-size:12px;color:#64748b"><?= html_escape((string) ($answer['q'] ?? '')); ?></div>
                    <div style="font-size:13px;color:#0f172a"><?= html_escape((string) ($answer['a'] ?? '—')); ?></div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <p style="margin:22px 0">
        <a href="<?= html_escape(admin_url('careers/application/' . $application->id)); ?>"
           style="background:#0d9488;color:#ffffff;text-decoration:none;padding:11px 22px;border-radius:8px;font-weight:bold;display:inline-block">
            Open in the CRM
        </a>
    </p>

    <p style="color:#94a3b8;font-size:12px">
        <?= empty($application->resume_file) ? 'No resume was attached to this application.' : 'The candidate\'s resume is attached to this email.'; ?>
    </p>
</div>
