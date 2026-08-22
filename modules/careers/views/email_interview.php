<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Interview schedule / reminder.
 *
 * Serves three audiences with one layout: the candidate on scheduling, the
 * candidate on the 24-hour reminder, and the interviewers on the same reminder
 * ($internal). $interview carries candidate_name / job_title when it comes from
 * the reminder query.
 */
$is_reminder = !empty($reminder);
$is_internal = !empty($internal);
$modes       = careers_interview_modes();
$mode_label  = $modes[$interview->mode]['label'] ?? ucfirst((string) $interview->mode);
$job_title   = $interview->job_title ?? ($application->job_title ?? '');
$who         = $interview->candidate_name ?? ($application->name ?? '');
?>
<div style="font-family:Arial,Helvetica,sans-serif;color:#334155;font-size:14px;line-height:1.6">
    <?php if ($is_internal) { ?>
        <p style="margin-top:0">
            Reminder — you are interviewing <strong><?= html_escape((string) $who); ?></strong>
            for <strong><?= html_escape((string) $job_title); ?></strong> tomorrow.
        </p>
    <?php } else { ?>
        <p>Hi <?= html_escape((string) $who); ?>,</p>
        <p>
            <?= $is_reminder
                ? 'A quick reminder about your interview with us tomorrow.'
                : 'We are pleased to invite you to an interview for <strong>' . html_escape((string) $job_title) . '</strong>.'; ?>
        </p>
    <?php } ?>

    <table cellpadding="0" cellspacing="0" style="width:100%;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin:18px 0">
        <tr>
            <td style="padding:16px 18px;font-size:13px;color:#475569">
                <div style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;font-weight:bold">When</div>
                <div style="font-size:18px;font-weight:bold;color:#0f172a;margin:3px 0 12px">
                    <?= _dt($interview->scheduled_at); ?>
                </div>

                <strong>Round:</strong> <?= html_escape($interview->title); ?> (#<?= (int) $interview->round; ?>)<br>
                <strong>Format:</strong> <?= html_escape($mode_label); ?> · <?= (int) $interview->duration; ?> minutes<br>
                <?php if (!empty($interview->location)) { ?>
                    <strong>Where:</strong> <?= html_escape($interview->location); ?><br>
                <?php } ?>
            </td>
        </tr>
    </table>

    <?php if (!empty($interview->meeting_link)) { ?>
        <p style="margin:22px 0">
            <a href="<?= html_escape($interview->meeting_link); ?>"
               style="background:#0d9488;color:#ffffff;text-decoration:none;padding:11px 22px;border-radius:8px;font-weight:bold;display:inline-block">
                Join the interview
            </a>
        </p>
    <?php } ?>

    <?php if (!$is_internal) { ?>
        <p style="font-size:13px;color:#475569">
            If this time does not work for you, simply reply to this email and we will find another slot.
        </p>
        <p style="color:#64748b;font-size:13px">
            Warm regards,<br>
            Talent Team — <?= html_escape($company); ?>
        </p>
    <?php } ?>
</div>
