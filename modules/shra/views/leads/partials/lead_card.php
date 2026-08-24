<?php defined('BASEPATH') or exit('No direct script access allowed');
/** Lead card — used by My Day queues, pipeline board and re-rendered after every AJAX action. $l = decorated lead, $can_all */
$can_all = isset($can_all) ? $can_all : shra_leads_can('all');
$cls     = 'shra-lead' . ($l->is_overdue ? ' overdue' : '') . ($l->is_stale ? ' stale' : '') . (!$l->is_open ? ' closed' : '');
$money   = shra_lead_money($l);
$who     = $l->rider_for === 'child' ? 'Child' . ($l->rider_age ? ' ' . $l->rider_age . 'y' : '') : ($l->rider_for === 'both' ? 'Self + child' : 'Self' . ($l->rider_age ? ' ' . $l->rider_age . 'y' : ''));
?>
<div class="<?php echo $cls; ?>" data-lead="<?php echo $l->id; ?>" data-stage="<?php echo $l->stage; ?>" data-name="<?php echo html_escape($l->name); ?>" data-phone="<?php echo html_escape($l->phonenumber); ?>" data-visit="<?php echo html_escape(trim(($l->visit_date ? date('D d M', strtotime($l->visit_date)) : '') . ' ' . shra_slot($l->visit_slot))); ?>" data-paid="<?php echo $money['paid'] > 0 ? html_escape(shra_money($money['paid'])) : ''; ?>" data-due="<?php echo $money['due'] > 0 ? html_escape(shra_money($money['due'])) : ''; ?>" data-paid-num="<?php echo $money['paid'] + 0; ?>" data-deal-num="<?php echo $money['deal'] + 0; ?>" data-pkg="<?php echo (int) $l->interest_package_id; ?>">
    <div class="shra-lead-top">
        <a href="<?php echo shra_lead_url($l->id); ?>" class="shra-lead-name"><?php echo html_escape($l->name); ?></a>
        <?php echo shra_lead_stage_badge($l->stage); ?>
    </div>
    <div class="shra-lead-meta">
        <span><i class="fa fa-phone"></i> <?php echo html_escape($l->phonenumber); ?></span>
        <span><i class="fa fa-child"></i> <?php echo $who; ?></span>
        <?php if ($l->package_name) { ?><span><i class="fa fa-tag"></i> <?php echo html_escape($l->package_name); ?></span><?php } ?>
        <?php if ($l->source_name) { ?><span><i class="fa fa-bullhorn"></i> <?php echo html_escape($l->source_name); ?></span><?php } ?>
        <?php if ($can_all) { ?><span><i class="fa fa-headset"></i> <?php echo html_escape($l->agent_name ?: 'Unassigned'); ?></span><?php } ?>
    </div>
    <div class="shra-lead-row">
        <?php if ($l->is_open) { ?>
            <?php echo shra_lead_due_text($l->next_action_at); ?>
            <?php if ($l->stage === 'visit_scheduled' && $l->visit_date) { ?><span class="shra-pill"><i class="fa fa-calendar-check"></i> <?php echo date('D d M', strtotime($l->visit_date)); ?> · <?php echo html_escape(shra_slot($l->visit_slot)); ?></span><?php } ?>
            <?php if ($l->call_attempts) { ?><span class="shra-muted" style="font-size:11px"><?php echo (int) $l->call_attempts; ?> call<?php echo $l->call_attempts == 1 ? '' : 's'; ?><?php echo $l->lastcontact ? ' · last ' . date('d M', strtotime($l->lastcontact)) : ''; ?></span><?php } ?>
            <?php if ($l->no_show_count) { ?><span class="shra-badge shra-badge-red"><?php echo (int) $l->no_show_count; ?> no-show</span><?php } ?>
            <?php if ($l->is_stale) { ?><span class="shra-badge shra-badge-muted">Stale</span><?php } ?>
            <?php if ($money['paid'] > 0) { ?><span class="shra-badge shra-badge-green" title="Collected on a call"><i class="fa fa-receipt"></i> <?php echo shra_money($money['paid']); ?><?php echo $money['due'] > 0 ? ' · due ' . shra_money($money['due']) : ''; ?></span><?php } ?>
            <?php if ($l->expected_value > 0) { ?><span class="shra-muted" style="font-size:11px;margin-left:auto"><?php echo shra_money($l->expected_value); ?></span><?php } ?>
        <?php } elseif ($l->stage === 'won') { ?>
            <span class="shra-badge shra-badge-green"><i class="fa fa-check"></i> Joined <?php echo $l->won_at ? date('d M', strtotime($l->won_at)) : ''; ?></span>
            <?php if ($l->rider_no) { ?><span class="shra-muted" style="font-size:11px"><?php echo html_escape($l->rider_no); ?></span><?php } ?>
        <?php } else { ?>
            <span class="shra-muted" style="font-size:11px"><?php echo html_escape($l->lost_reason ?: ($l->lost_note ?: ucfirst($l->stage))); ?></span>
        <?php } ?>
    </div>
    <div class="shra-lead-actions">
        <a href="<?php echo $l->tel_link; ?>" class="shra-ic" title="Call"><i class="fa fa-phone"></i></a>
        <a href="<?php echo $l->wa_link; ?>" target="_blank" rel="noopener" class="shra-ic wa" title="WhatsApp" data-shra-wa="<?php echo $l->id; ?>"><i class="fa-brands fa-whatsapp"></i></a>
        <?php if ($l->is_open) { ?>
            <button type="button" class="shra-btn shra-btn-primary shra-btn-sm" data-shra-act="call" data-lead="<?php echo $l->id; ?>"><i class="fa fa-pen"></i> Log call</button>
            <button type="button" class="shra-btn shra-btn-outline shra-btn-sm" data-shra-act="visit" data-lead="<?php echo $l->id; ?>" title="Schedule / reschedule visit"><i class="fa fa-calendar-plus"></i></button>
            <?php if ($can_all && in_array($l->stage, ['visit_scheduled', 'followup', 'contacted', 'new'])) { ?>
                <button type="button" class="shra-btn shra-btn-outline shra-btn-sm" data-shra-act="visited" data-lead="<?php echo $l->id; ?>" title="Arrived at the academy"><i class="fa fa-person-walking"></i> Arrived</button>
            <?php } ?>
            <?php if ($can_all && in_array($l->stage, ['visited'])) { ?>
                <button type="button" class="shra-btn shra-btn-gold shra-btn-sm" data-shra-act="confirm" data-lead="<?php echo $l->id; ?>"><i class="fa fa-thumbs-up"></i> Confirm</button>
            <?php } ?>
            <?php if ($l->stage === 'confirmed' && (shra_can_billing() || $can_all)) { ?>
                <a href="<?php echo admin_url('shra/shra_leads/bill_now/' . $l->id); ?>" class="shra-btn shra-btn-gold shra-btn-sm"><i class="fa-solid fa-cash-register"></i> Bill now</a>
            <?php } ?>
            <?php if ($l->stage === 'visit_scheduled' && $l->visit_date < date('Y-m-d')) { ?>
                <button type="button" class="shra-btn shra-btn-danger shra-btn-sm" data-shra-act="no_show" data-lead="<?php echo $l->id; ?>" title="Did not turn up">No-show</button>
            <?php } ?>
            <button type="button" class="shra-ic muted" data-shra-act="lost" data-lead="<?php echo $l->id; ?>" title="Mark lost"><i class="fa fa-xmark"></i></button>
        <?php } elseif (shra_leads_can('manage') && $l->stage !== 'won') { ?>
            <button type="button" class="shra-btn shra-btn-outline shra-btn-sm" data-shra-act="reopen" data-lead="<?php echo $l->id; ?>"><i class="fa fa-rotate-left"></i> Reopen</button>
        <?php } ?>
    </div>
</div>
