<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Free-text email composed by a recruiter on the application page.
 * $body arrives already escaped and nl2br'd by the controller.
 */
?>
<div style="font-family:Arial,Helvetica,sans-serif;color:#334155;font-size:14px;line-height:1.6">
    <?= $body; ?>

    <p style="color:#94a3b8;font-size:12px;margin-top:26px;border-top:1px solid #e2e8f0;padding-top:12px">
        Regarding your application for <?= html_escape((string) $application->job_title); ?>
        · Reference <?= html_escape($application->reference); ?><br>
        <?= html_escape($company); ?>
    </p>
</div>
