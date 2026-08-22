<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Template detail drawer — the recipient-facing preview on top, then Meta's
 * own verdict on it. A REJECTED template leads with the reason and the fix so
 * the edit-and-resubmit path is one click away.
 *
 * @var object $tpl whatsapp_api_templates row
 */
$components = whatsapp_template_components($tpl);
$status     = strtoupper((string) $tpl->status);
$reason     = whatsapp_template_rejected_reason($tpl->rejected_reason ?? '');
$buttons    = whatsapp_template_buttons($components);
$header     = whatsapp_template_component($components, 'HEADER');
$hformat    = $header ? strtoupper($header['format'] ?? 'TEXT') : '';
$body       = (string) $tpl->body_text;
$samples    = whatsapp_template_component($components, 'BODY')['example']['body_text'][0] ?? [];

$rows = [
    'Status'      => whatsapp_template_status_badge($tpl->status),
    'Category'    => e(ucfirst(strtolower((string) $tpl->category))) ?: '—',
    'Language'    => '<code class="wapi-code">' . e($tpl->language) . '</code>',
    'Quality'     => whatsapp_template_quality_badge($tpl->quality_score ?? ''),
    'Header'      => $hformat !== '' ? e(ucfirst(strtolower($hformat))) : '—',
    'Buttons'     => count($buttons) ? (int) count($buttons) : '—',
    'Variables'   => (int) $tpl->variables_count,
    'Template ID' => $tpl->template_id ? '<code class="wapi-code">' . e($tpl->template_id) . '</code>' : '—',
    'Last synced' => e(whatsapp_time_ago($tpl->last_synced_at ?? null)),
];
?>
<div class="wapi-tpldetails">

    <?php if ($status === 'REJECTED'): ?>
        <div class="wapi-alert wapi-alert-danger">
            <i class="fa fa-circle-exclamation"></i>
            <div>
                <strong>Rejected by Meta<?php echo $reason['label'] !== '' ? ' — ' . e($reason['label']) : ''; ?></strong>
                <p><?php echo e($reason['hint']); ?></p>
                <p class="wapi-muted">Fix the content below and resubmit — the same name and language are reused, so nothing that already points at this template breaks.</p>
            </div>
        </div>
    <?php elseif ($status === 'PAUSED'): ?>
        <div class="wapi-alert wapi-alert-warn">
            <i class="fa fa-pause"></i>
            <div>
                <strong>Paused by Meta</strong>
                <p>Recipients blocked or reported this template too often, so sending is paused. Edit the content to make the message more relevant, then resubmit.</p>
            </div>
        </div>
    <?php elseif ($status === 'PENDING'): ?>
        <div class="wapi-alert wapi-alert-info">
            <i class="fa fa-hourglass-half"></i>
            <div>
                <strong>Under review</strong>
                <p>Meta is reviewing this template. It cannot be edited or used for sending until the review finishes — usually within a few minutes.</p>
            </div>
        </div>
    <?php elseif ($status === 'DISABLED'): ?>
        <div class="wapi-alert wapi-alert-danger">
            <i class="fa fa-ban"></i>
            <div>
                <strong>Disabled by Meta</strong>
                <p>This template can no longer be sent or edited. Create a new template with corrected content.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="wapi-tpldetails-grid">
        <div>
            <div class="wapi-details-title"><i class="fa fa-comment-dots"></i> Preview</div>
            <?php echo whatsapp_template_preview_html($components); ?>
            <p class="wapi-muted wapi-tpldetails-note">Variables are shown as <code class="wapi-code">{{n}}</code> — each is replaced at send time.</p>
        </div>
        <div>
            <div class="wapi-details-title"><i class="fa fa-circle-info"></i> Details</div>
            <table class="wapi-table wapi-kv-table">
                <tbody>
                <?php foreach ($rows as $label => $value): ?>
                    <tr><th><?php echo $label; ?></th><td><?php echo $value; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ((int) $tpl->variables_count > 0): ?>
        <div class="wapi-details-title"><i class="fa fa-code"></i> Variables</div>
        <table class="wapi-table">
            <thead><tr><th>Placeholder</th><th>Sample value sent to Meta</th></tr></thead>
            <tbody>
            <?php for ($i = 1; $i <= (int) $tpl->variables_count; $i++): ?>
                <tr>
                    <td><code class="wapi-code">{{<?php echo $i; ?>}}</code></td>
                    <td><?php echo e(isset($samples[$i - 1]) ? (string) $samples[$i - 1] : '—'); ?></td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (!empty($buttons)): ?>
        <div class="wapi-details-title"><i class="fa fa-hand-pointer"></i> Buttons</div>
        <table class="wapi-table">
            <thead><tr><th>Label</th><th>Type</th><th>Target</th></tr></thead>
            <tbody>
            <?php foreach ($buttons as $b): ?>
                <tr>
                    <td><strong><?php echo e((string) ($b['text'] ?? '—')); ?></strong></td>
                    <td><?php echo e(whatsapp_template_button_type_label($b['type'] ?? '')); ?></td>
                    <td><small class="wapi-muted"><?php echo e((string) ($b['url'] ?? ($b['phone_number'] ?? '—'))); ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="wapi-muted wapi-tpldetails-note">Buttons are kept exactly as approved when you edit this template — change them from the WhatsApp Manager.</p>
    <?php endif; ?>

    <?php if ($hformat !== '' && $hformat !== 'TEXT'): ?>
        <?php $hword = strtolower($hformat); ?>
        <p class="wapi-muted wapi-tpldetails-note">
            <i class="fa fa-image"></i> This template has <?php echo strpos('aeiou', $hword[0]) !== false ? 'an' : 'a'; ?> <?php echo e($hword); ?> header.
            It is carried through unchanged on edit — each send supplies its own <?php echo e($hword); ?> URL.
        </p>
    <?php endif; ?>

</div>
