<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Candidate acknowledgement.
 *
 * Rendered inside the CRM's Email Header / Footer, so it carries no <html>
 * wrapper of its own. Inline styles only — email clients drop <style> blocks.
 */
$job_url = careers_public_job_url($job);
?>
<div style="font-family:Arial,Helvetica,sans-serif;color:#334155;font-size:14px;line-height:1.6">
    <p>Hi <?= html_escape($application->name); ?>,</p>

    <p>
        Thank you for applying for <strong><?= html_escape($job->title); ?></strong> at
        <?= html_escape($company); ?>. Your application has been received and is with our talent team.
    </p>

    <table cellpadding="0" cellspacing="0" style="width:100%;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin:18px 0">
        <tr>
            <td style="padding:16px 18px">
                <div style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;font-weight:bold">Your reference</div>
                <div style="font-size:20px;font-weight:bold;color:#0f172a;margin:4px 0 12px"><?= html_escape($application->reference); ?></div>

                <div style="font-size:13px;color:#475569">
                    <strong>Position:</strong> <?= html_escape($job->title); ?><br>
                    <strong>Type:</strong> <?= careers_job_type_label($job->job_type); ?><br>
                    <?php $location = careers_location_text($job); if ($location !== '') { ?>
                        <strong>Location:</strong> <?= html_escape($location); ?> (<?= careers_work_mode_label($job->work_mode); ?>)<br>
                    <?php } ?>
                    <strong>Applied on:</strong> <?= _dt($application->created_at); ?>
                </div>
            </td>
        </tr>
    </table>

    <p>
        We review every application personally. If your profile matches what the role needs, someone from our
        team will be in touch with the next step. Please keep your reference number handy for any follow-up.
    </p>

    <p style="margin:24px 0">
        <a href="<?= html_escape($job_url); ?>"
           style="background:#0d9488;color:#ffffff;text-decoration:none;padding:11px 22px;border-radius:8px;font-weight:bold;display:inline-block">
            View the job description
        </a>
    </p>

    <p style="color:#64748b;font-size:13px">
        Warm regards,<br>
        Talent Team — <?= html_escape($company); ?>
    </p>
</div>
