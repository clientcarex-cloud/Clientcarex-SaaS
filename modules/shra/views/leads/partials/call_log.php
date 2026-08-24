<?php defined('BASEPATH') or exit('No direct script access allowed');
/** Every call / WhatsApp attempt on a lead. $log = events (newest first), $lead. */
$outs = shra_lead_outcomes();
?>
<?php if (!count($log)) { ?>
    <div class="shra-cl-empty">No calls logged on this lead yet — this will be the first.</div>
<?php } else { ?>
<div class="shra-cl">
    <?php foreach ($log as $e) {
        $meta = json_decode((string) $e->meta, true) ?: [];
        if (array_key_exists('stage', $meta)) {
            $what = $meta['stage'] !== '' ? shra_lead_stage_label($meta['stage']) : 'Status kept';
        } else {
            // Calls logged before statuses replaced the outcome buttons.
            $what = $outs[$e->outcome][0] ?? ucfirst(str_replace('_', ' ', (string) $e->outcome));
        }
    ?>
    <div class="shra-cl-item">
        <span class="shra-cl-ic <?php echo $e->event_type; ?>"><i class="fa <?php echo $e->event_type === 'whatsapp' ? 'fa-brands fa-whatsapp' : 'fa-phone'; ?>"></i></span>
        <div class="shra-cl-body">
            <div><b><?php echo html_escape($what); ?></b><?php if ($e->to_value) { ?> <span class="shra-muted">· next <?php echo shra_datetime($e->to_value, false); ?></span><?php } ?></div>
            <?php if ($e->note) { ?><div class="shra-cl-note"><?php echo html_escape($e->note); ?></div><?php } ?>
            <div class="shra-cl-meta"><?php echo html_escape($e->staff_name ?: 'System'); ?> · <?php echo shra_datetime($e->created_at); ?></div>
        </div>
    </div>
    <?php } ?>
</div>
<?php } ?>
