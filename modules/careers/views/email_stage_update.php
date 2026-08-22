<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Stage-change email to the candidate.
 *
 * One template, five tones: the message for a rejection has to read as though a
 * person wrote it, not as a status field that happened to change.
 */
$messages = [
    'shortlisted' => [
        'headline' => 'You have been shortlisted',
        'body'     => 'We have reviewed your application and would like to take it forward. Our team will contact you shortly with the next step.',
        'color'    => '#2563eb',
    ],
    'interview' => [
        'headline' => 'Your application is at the interview stage',
        'body'     => 'We would like to speak with you about this role. Someone from our team will reach out with a time that works for you.',
        'color'    => '#d97706',
    ],
    'offer' => [
        'headline' => 'Good news about your application',
        'body'     => 'We enjoyed meeting you and would like to discuss an offer. Our team will be in touch with the details very soon.',
        'color'    => '#0d9488',
    ],
    'hired' => [
        'headline' => 'Welcome aboard!',
        'body'     => 'We are delighted to confirm that you are joining us. Our team will share your onboarding details separately.',
        'color'    => '#15803d',
    ],
    'rejected' => [
        'headline' => 'Update on your application',
        'body'     => 'Thank you for the time you put into your application. On this occasion we have decided to move forward with other candidates. We were glad to consider you, and we would welcome an application from you for a future opening.',
        'color'    => '#64748b',
    ],
];

$content = $messages[$stage] ?? ['headline' => 'Update on your application', 'body' => 'There is an update on your application.', 'color' => '#0d9488'];
?>
<div style="font-family:Arial,Helvetica,sans-serif;color:#334155;font-size:14px;line-height:1.6">
    <p>Hi <?= html_escape($application->name); ?>,</p>

    <h2 style="color:<?= $content['color']; ?>;font-size:19px;margin:16px 0 10px"><?= $content['headline']; ?></h2>

    <p><?= $content['body']; ?></p>

    <table cellpadding="0" cellspacing="0" style="width:100%;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin:18px 0">
        <tr>
            <td style="padding:14px 18px;font-size:13px;color:#475569">
                <strong>Position:</strong> <?= html_escape((string) $application->job_title); ?><br>
                <strong>Reference:</strong> <?= html_escape($application->reference); ?>
            </td>
        </tr>
    </table>

    <?php if (!empty($reason) && $stage !== 'rejected') { ?>
        <p style="font-size:13px;color:#475569"><?= html_escape($reason); ?></p>
    <?php } ?>

    <p style="color:#64748b;font-size:13px">
        Warm regards,<br>
        Talent Team — <?= html_escape($company); ?>
    </p>
</div>
