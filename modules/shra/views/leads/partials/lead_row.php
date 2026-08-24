<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * One lead as a dense table row — the My Day work list (built for 150+ leads/day).
 * $l = decorated lead, $can_all. Swapped in place by shra_leads.js after every action.
 */
$can_all = isset($can_all) ? $can_all : shra_leads_can('all');
$who     = $l->rider_for === 'child' ? 'Child' . ($l->rider_age ? ' ' . $l->rider_age . 'y' : '')
         : ($l->rider_for === 'both' ? 'Self + child' : 'Self' . ($l->rider_age ? ' ' . $l->rider_age . 'y' : ''));

// Which work bucket the row belongs to — mirrors Shra_leads_model::queues_for().
if (!empty($force_bucket)) {
    $bucket = $force_bucket;
} elseif (!$l->is_open) {
    $bucket = 'closed';
} elseif (empty($l->next_action_at)) {
    $bucket = 'unset';
} elseif (strtotime($l->next_action_at) < time()) {
    $bucket = 'overdue';
} elseif (date('Y-m-d', strtotime($l->next_action_at)) === date('Y-m-d')) {
    $bucket = 'today';
} elseif (strtotime($l->next_action_at) < strtotime('+7 days')) {
    $bucket = 'upcoming';
} else {
    $bucket = 'later';
}

$cls = 'shra-lead shra-r'
     . ($l->is_overdue ? ' over' : '')
     . (empty($l->next_action_at) && $l->is_open ? ' unset' : '')
     . (!$l->is_open ? ' closed' : '');

$outcome = $l->last_outcome ? (shra_lead_outcomes()[$l->last_outcome][0] ?? ucfirst(str_replace('_', ' ', $l->last_outcome))) : '';
$hay     = strtolower(trim($l->name . ' ' . $l->phonenumber . ' ' . $l->city . ' ' . $l->email . ' ' . $l->source_name . ' ' . $l->agent_name . ' ' . $l->package_name . ' ' . shra_lead_stage_label($l->stage)));
?>
<tr class="<?php echo $cls; ?>" data-lead="<?php echo $l->id; ?>" data-stage="<?php echo $l->stage; ?>" data-bucket="<?php echo $bucket; ?>"
    data-agent="<?php echo (int) $l->assigned; ?>" data-source="<?php echo (int) $l->source; ?>" data-stale="<?php echo $l->is_stale ? 1 : 0; ?>"
    data-name="<?php echo html_escape($l->name); ?>" data-phone="<?php echo html_escape($l->phonenumber); ?>"
    data-visit="<?php echo html_escape(trim(($l->visit_date ? date('D d M', strtotime($l->visit_date)) : '') . ' ' . shra_slot($l->visit_slot))); ?>" data-s="<?php echo html_escape($hay); ?>">
    <td class="shra-r-name">
        <a href="<?php echo shra_lead_url($l->id); ?>"><?php echo html_escape($l->name); ?></a>
        <span class="shra-r-sub">
            <?php echo $who; ?><?php if ($l->city) { ?> · <?php echo html_escape($l->city); ?><?php } ?><?php if ($l->package_name) { ?> · <?php echo html_escape($l->package_name); ?><?php } ?>
        </span>
    </td>
    <td class="shra-r-phone">
        <a href="<?php echo $l->tel_link; ?>" class="shra-r-tel"><?php echo html_escape($l->phonenumber); ?></a>
        <a href="<?php echo $l->wa_link; ?>" target="_blank" rel="noopener" class="shra-ic wa xs" title="WhatsApp" data-shra-wa="<?php echo $l->id; ?>"><i class="fa-brands fa-whatsapp"></i></a>
    </td>
    <td><?php echo shra_lead_stage_badge($l->stage); ?></td>
    <td class="shra-r-due">
        <?php if ($l->is_open) { ?>
            <?php echo shra_lead_due_text($l->next_action_at); ?>
        <?php } elseif ($l->stage === 'won') { ?>
            <span class="shra-badge shra-badge-green"><i class="fa fa-check"></i> Joined<?php echo $l->won_at ? ' ' . date('d M', strtotime($l->won_at)) : ''; ?></span>
        <?php } else { ?>
            <span class="shra-muted"><?php echo html_escape($l->lost_reason ?: ($l->lost_note ?: ucfirst($l->stage))); ?></span>
        <?php } ?>
    </td>
    <td class="shra-r-visit">
        <?php if ($l->visit_date) { ?>
            <?php echo date('D d M', strtotime($l->visit_date)); ?><span class="shra-r-sub"><?php echo html_escape(shra_slot($l->visit_slot) ?: 'Any time'); ?></span>
        <?php } else { ?><span class="shra-muted">—</span><?php } ?>
    </td>
    <td class="shra-r-calls num">
        <?php echo (int) $l->call_attempts; ?>
        <?php if ($outcome) { ?><span class="shra-r-sub"><?php echo html_escape($outcome); ?></span><?php } ?>
    </td>
    <td class="shra-r-flags">
        <?php if ($l->no_show_count) { ?><span class="shra-badge shra-badge-red" title="No-shows"><?php echo (int) $l->no_show_count; ?> NS</span><?php } ?>
        <?php if ($l->is_stale) { ?><span class="shra-badge shra-badge-muted">Stale</span><?php } ?>
        <?php if (!empty($l->paid_amount)) { ?><span class="shra-r-paid" title="Advance collected on a call"><i class="fa fa-receipt"></i> <?php echo shra_money($l->paid_amount); ?></span><?php } ?>
        <?php if ($l->expected_value > 0) { ?><span class="shra-r-val"><?php echo shra_money($l->expected_value); ?></span><?php } ?>
    </td>
    <td class="shra-r-src"><?php echo html_escape($l->source_name ?: '—'); ?><?php if ($can_all) { ?><span class="shra-r-sub"><?php echo html_escape($l->agent_name ?: 'Unassigned'); ?></span><?php } ?></td>
    <td class="shra-r-act">
        <?php if ($l->is_open) { ?>
            <button type="button" class="shra-btn shra-btn-primary shra-btn-xs" data-shra-act="call" data-lead="<?php echo $l->id; ?>" title="Log call (c)"><i class="fa fa-pen"></i> Log</button>
            <button type="button" class="shra-ic xs" data-shra-act="visit" data-lead="<?php echo $l->id; ?>" title="Schedule / reschedule visit"><i class="fa fa-calendar-plus"></i></button>
            <button type="button" class="shra-ic xs muted" data-shra-more="<?php echo $l->id; ?>" title="More"><i class="fa fa-ellipsis"></i></button>
            <div class="shra-r-menu" hidden>
                <a href="<?php echo shra_lead_url($l->id); ?>"><i class="fa fa-arrow-right"></i> Open lead</a>
                <?php if ($can_all && in_array($l->stage, ['visit_scheduled', 'followup', 'contacted', 'new', 'prospect', 'enquired', 'callback_request', 'no_response'])) { ?>
                    <button type="button" data-shra-act="visited" data-lead="<?php echo $l->id; ?>"><i class="fa fa-person-walking"></i> Mark arrived</button>
                <?php } ?>
                <?php if ($can_all && $l->stage === 'visited') { ?>
                    <button type="button" data-shra-act="confirm" data-lead="<?php echo $l->id; ?>"><i class="fa fa-thumbs-up"></i> Confirm</button>
                <?php } ?>
                <?php if ($l->stage === 'confirmed' && (shra_can_billing() || $can_all)) { ?>
                    <a href="<?php echo admin_url('shra/shra_leads/bill_now/' . $l->id); ?>"><i class="fa-solid fa-cash-register"></i> Bill now</a>
                <?php } ?>
                <?php if ($l->stage === 'visit_scheduled' && $l->visit_date && $l->visit_date < date('Y-m-d')) { ?>
                    <button type="button" data-shra-act="no_show" data-lead="<?php echo $l->id; ?>"><i class="fa fa-user-slash"></i> No-show</button>
                <?php } ?>
                <?php if ($can_all) { ?><button type="button" data-shra-act="reassign" data-lead="<?php echo $l->id; ?>"><i class="fa fa-headset"></i> Reassign</button><?php } ?>
                <button type="button" class="danger" data-shra-act="lost" data-lead="<?php echo $l->id; ?>"><i class="fa fa-xmark"></i> Mark lost</button>
            </div>
        <?php } else { ?>
            <a href="<?php echo shra_lead_url($l->id); ?>" class="shra-ic xs" title="Open"><i class="fa fa-arrow-right"></i></a>
            <?php if (shra_leads_can('manage') && $l->stage !== 'won') { ?>
                <button type="button" class="shra-ic xs" data-shra-act="reopen" data-lead="<?php echo $l->id; ?>" title="Reopen"><i class="fa fa-rotate-left"></i></button>
            <?php } ?>
        <?php } ?>
    </td>
</tr>
