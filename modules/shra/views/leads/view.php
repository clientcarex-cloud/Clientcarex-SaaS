<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'leads'; include __DIR__ . '/../_nav.php'; $l = $lead; ?>

    <?php $money = shra_lead_money($l); ?>
    <div id="shra-lead-title" data-stage="<?php echo html_escape($l->stage); ?>" data-name="<?php echo html_escape($l->name); ?>" data-phone="<?php echo html_escape($l->phonenumber); ?>" data-visit="<?php echo html_escape(trim(($l->visit_date ? date('D d M', strtotime($l->visit_date)) : '') . ' ' . shra_slot($l->visit_slot))); ?>" data-paid="<?php echo $money['paid'] > 0 ? html_escape(shra_money($money['paid'])) : ''; ?>" data-due="<?php echo $money['due'] > 0 ? html_escape(shra_money($money['due'])) : ''; ?>" data-paid-num="<?php echo $money['paid'] + 0; ?>" data-deal-num="<?php echo $money['deal'] + 0; ?>" data-pkg="<?php echo (int) $l->interest_package_id; ?>"></div>
    <div class="shra-toolbar" style="justify-content:space-between;align-items:flex-start">
        <div>
            <a href="<?php echo admin_url('shra/shra_leads'); ?>" class="shra-muted" style="font-size:12px"><i class="fa fa-arrow-left"></i> Leads</a>
            <h3 class="shra-title" style="margin:4px 0 6px"><?php echo html_escape($l->name); ?> <?php echo shra_lead_stage_badge($l->stage); ?><?php if ($l->is_stale) { ?> <span class="shra-badge shra-badge-muted">Stale</span><?php } ?></h3>
            <div class="shra-lead-meta" style="font-size:13px">
                <a href="<?php echo $l->tel_link; ?>"><i class="fa fa-phone"></i> <?php echo html_escape($l->phonenumber); ?></a>
                <?php if ($l->email) { ?><span><i class="fa fa-envelope"></i> <?php echo html_escape($l->email); ?></span><?php } ?>
                <?php if ($l->city) { ?><span><i class="fa fa-location-dot"></i> <?php echo html_escape($l->city); ?></span><?php } ?>
                <span><i class="fa fa-bullhorn"></i> <?php echo html_escape($l->source_name ?: 'Unknown source'); ?></span>
                <span><i class="fa fa-headset"></i> <?php echo html_escape($l->agent_name ?: 'Unassigned'); ?></span>
                <span class="shra-muted">added <?php echo shra_datetime($l->dateadded); ?> by <?php echo html_escape($l->added_by_name ?: 'system'); ?> · <?php echo $l->age_days; ?> d old</span>
            </div>
        </div>
        <div class="shra-lead-actions" style="margin:0">
            <a href="<?php echo $l->tel_link; ?>" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa fa-phone"></i> Call</a>
            <a href="<?php echo $l->wa_link; ?>" target="_blank" rel="noopener" class="shra-btn shra-btn-outline shra-btn-sm" data-shra-wa="<?php echo $l->id; ?>"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
            <button type="button" class="shra-btn shra-btn-outline shra-btn-sm" data-shra-wa-copy="<?php echo $l->id; ?>" title="Copy the WhatsApp pitch — paste it into any chat"><i class="fa fa-copy"></i></button>
            <?php if ($l->is_open) { ?>
                <button type="button" class="shra-btn shra-btn-primary shra-btn-sm" data-shra-act="call" data-lead="<?php echo $l->id; ?>"><i class="fa fa-pen"></i> Log call</button>
                <button type="button" class="shra-btn shra-btn-outline shra-btn-sm" data-shra-act="visit" data-lead="<?php echo $l->id; ?>"><i class="fa fa-calendar-plus"></i> <?php echo $l->stage === 'visit_scheduled' ? 'Reschedule' : 'Schedule visit'; ?></button>
                <?php if ($can_all && $l->stage !== 'confirmed') { ?><button type="button" class="shra-btn shra-btn-gold shra-btn-sm" data-shra-act="confirm" data-lead="<?php echo $l->id; ?>"><i class="fa fa-person-walking"></i> <?php echo $l->stage === 'visited' ? 'Confirm' : 'Arrived / confirm'; ?></button><?php } ?>
                <?php if (in_array($l->stage, ['confirmed', 'visited']) && (shra_can_billing() || $can_all)) { ?><a href="<?php echo admin_url('shra/shra_leads/bill_now/' . $l->id); ?>" class="shra-btn shra-btn-gold shra-btn-sm"><i class="fa-solid fa-cash-register"></i> Bill now</a><?php } ?>
                <?php if ($l->stage === 'visit_scheduled' && $l->visit_date < date('Y-m-d')) { ?><button type="button" class="shra-btn shra-btn-danger shra-btn-sm" data-shra-act="no_show" data-lead="<?php echo $l->id; ?>">No-show</button><?php } ?>
                <button type="button" class="shra-btn shra-btn-danger shra-btn-sm" data-shra-act="lost" data-lead="<?php echo $l->id; ?>"><i class="fa fa-xmark"></i> Lost</button>
            <?php } elseif ($can_manage && $l->stage !== 'won') { ?>
                <button type="button" class="shra-btn shra-btn-outline shra-btn-sm" data-shra-act="reopen" data-lead="<?php echo $l->id; ?>"><i class="fa fa-rotate-left"></i> Reopen</button>
            <?php } ?>
            <?php if ($can_manage) { ?><button type="button" class="shra-btn shra-btn-outline shra-btn-sm" data-shra-act="reassign" data-lead="<?php echo $l->id; ?>"><i class="fa fa-headset"></i> Reassign</button><?php } ?>
        </div>
    </div>

    <?php if (!empty($billed)) { ?>
    <div class="shra-alert shra-alert-ok" style="margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <i class="fa-solid fa-circle-check"></i> <b>Bill raised</b> &middot; <?php echo html_escape($billed->enrollment_no); ?> &middot; <?php echo html_escape($billed->package_name); ?>
        &middot; <?php echo shra_money($billed->total); ?> &middot; received <?php echo shra_money($billed->paid_real); ?>
        <?php if ($billed->due > 0.009) { ?><span class="shra-pill due"><i class="fa fa-hourglass-half"></i> Due <?php echo shra_money($billed->due); ?></span><?php } ?>
        <?php if (shra_can_billing() || shra_can('view')) { ?><a href="<?php echo admin_url('shra/receipt_pdf/' . $billed->id); ?>" target="_blank" rel="noopener" class="shra-btn shra-btn-gold shra-btn-sm" style="margin-left:auto"><i class="fa fa-receipt"></i> Open receipt</a><?php } ?>
    </div>
    <?php } ?>

    <?php if ($l->is_open) { ?>
    <div class="shra-alert <?php echo $l->is_overdue ? 'shra-alert-bad' : 'shra-alert-ok'; ?>" style="margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <b>Next action:</b> <?php echo $l->is_timed ? ucfirst($l->next_action_type) . ' · ' : 'Join · '; ?><?php echo shra_lead_next_text($l); ?>
        <?php if ($l->visit_date) { ?><span class="shra-pill"><i class="fa fa-calendar-check"></i> Visit <?php echo date('D d M', strtotime($l->visit_date)); ?> · <?php echo html_escape(shra_slot($l->visit_slot)); ?></span><?php } ?>
        <?php if ($l->schedule) { ?><span class="shra-pill"><i class="fa-solid fa-user-clock"></i> <?php echo html_escape($l->schedule); ?></span><?php } ?>
        <?php if ($l->no_show_count) { ?><span class="shra-badge shra-badge-red"><?php echo (int) $l->no_show_count; ?> no-show<?php echo $l->no_show_count > 1 ? 's' : ''; ?></span><?php } ?>
        <?php if ($money['paid'] > 0) { ?><span class="shra-pill paid"><i class="fa fa-receipt"></i> Paid <?php echo shra_money($money['paid']); ?></span><?php } ?>
        <?php if ($money['due'] > 0) { ?><span class="shra-pill due"><i class="fa fa-hourglass-half"></i> Due <?php echo shra_money($money['due']); ?></span><?php } ?>
        <span class="shra-muted" style="margin-left:auto"><?php echo (int) $l->call_attempts; ?> call attempt<?php echo $l->call_attempts == 1 ? '' : 's'; ?><?php echo $l->lastcontact ? ' · last ' . shra_datetime($l->lastcontact, false) : ''; ?></span>
    </div>
    <?php } elseif ($l->stage === 'won') { ?>
    <div class="shra-alert shra-alert-ok" style="margin-bottom:16px"><i class="fa fa-trophy"></i> <b>Joined</b> on <?php echo date('d M Y', strtotime($l->won_at)); ?><?php if ($rider) { ?> · rider <a href="<?php echo admin_url('shra/rider/' . $rider->id); ?>"><?php echo html_escape($rider->rider_no); ?></a><?php } ?> · revenue credited to <b><?php echo html_escape($l->agent_name ?: '—'); ?></b></div>
    <?php } else { ?>
    <div class="shra-alert shra-alert-bad" style="margin-bottom:16px"><b><?php echo shra_lead_stage_label($l->stage); ?></b><?php echo $l->lost_reason ? ' · ' . html_escape($l->lost_reason) : ''; ?><?php echo $l->lost_note ? ' — ' . html_escape($l->lost_note) : ''; ?></div>
    <?php } ?>

    <div class="shra-profile">
        <div>
            <div class="shra-card">
                <div class="shra-card-head"><h4><i class="fa fa-id-card" style="color:var(--gold)"></i> Details</h4></div>
                <form id="shra-lead-details-form" class="shra-card-body">
                    <input type="hidden" name="lead_id" value="<?php echo $l->id; ?>">
                    <div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" value="<?php echo html_escape($l->name); ?>"></div>
                    <div class="form-group"><label>Mobile</label><input type="tel" name="phone" class="form-control" value="<?php echo html_escape($l->phonenumber); ?>"></div>
                    <div class="row">
                        <div class="col-xs-6"><div class="form-group"><label>Riding for</label><select name="rider_for" class="form-control"><?php foreach (['self' => 'Self', 'child' => 'Child', 'both' => 'Self + child'] as $k => $v) { ?><option value="<?php echo $k; ?>" <?php echo $l->rider_for === $k ? 'selected' : ''; ?>><?php echo $v; ?></option><?php } ?></select></div></div>
                        <div class="col-xs-6"><div class="form-group"><label>Rider age</label><input type="number" name="rider_age" class="form-control" value="<?php echo html_escape($l->rider_age); ?>"></div></div>
                    </div>
                    <div class="form-group"><label>Interested in</label><select name="interest_package_id" class="form-control"><option value="">Not sure yet</option><?php foreach ($packages as $pk) { ?><option value="<?php echo $pk->id; ?>" <?php echo $l->interest_package_id == $pk->id ? 'selected' : ''; ?>><?php echo ucfirst($pk->audience) . ' · ' . html_escape($pk->name) . ' · ' . shra_money($pk->price); ?></option><?php } ?></select></div>
                    <div class="row">
                        <div class="col-xs-6"><div class="form-group"><label>Start date</label><input type="date" name="preferred_start_date" class="form-control" value="<?php echo html_escape($l->preferred_start_date); ?>"></div></div>
                        <div class="col-xs-6"><div class="form-group"><label>Batch</label><select name="preferred_batch" class="form-control"><option value="">Not decided</option><?php foreach (shra_batches() as $bk => $b) { ?><option value="<?php echo $bk; ?>" <?php echo $l->preferred_batch === $bk ? 'selected' : ''; ?>><?php echo html_escape($b['text']); ?></option><?php } ?></select></div></div>
                    </div>
                    <?php if ($l->visited_at) {
                        /* The stored slot can be something the settings list does not carry ("Walk-in",
                           a slot since renamed) — keep it in the picker so saving never rewrites it. */
                        $slot_opts = $slots;
                        foreach ([$l->visit_slot, 'Walk-in'] as $extra) {
                            if ($extra && !in_array($extra, $slot_opts, true)) { array_unshift($slot_opts, $extra); }
                        }
                    ?>
                    <div class="row">
                        <div class="col-xs-6"><div class="form-group"><label>Visit date</label><input type="date" name="visit_date" class="form-control" max="<?php echo date('Y-m-d'); ?>" value="<?php echo html_escape($l->visit_date); ?>"></div></div>
                        <div class="col-xs-6"><div class="form-group"><label>Visit slot</label><select name="visit_slot" class="form-control"><?php foreach ($slot_opts as $so) { ?><option value="<?php echo html_escape($so); ?>" <?php echo $l->visit_slot === $so ? 'selected' : ''; ?>><?php echo html_escape(shra_slot($so)); ?></option><?php } ?></select></div></div>
                    </div>
                    <div class="help" style="margin:-9px 0 14px">When the rider actually came. Correct it here if the walk-in was entered on the wrong day.</div>
                    <?php } ?>
                    <div class="row">
                        <div class="col-xs-6"><div class="form-group"><label>Expected ₹</label><input type="number" name="expected_value" class="form-control" value="<?php echo $l->expected_value > 0 ? $l->expected_value + 0 : ''; ?>"></div></div>
                        <div class="col-xs-6"><div class="form-group"><label>Source</label><select name="source" class="form-control"><?php foreach ($sources as $s) { ?><option value="<?php echo $s->id; ?>" <?php echo $l->source == $s->id ? 'selected' : ''; ?>><?php echo html_escape($s->name); ?></option><?php } ?></select></div></div>
                    </div>
                    <div class="row">
                        <div class="col-xs-6"><div class="form-group"><label>City / area</label><input type="text" name="city" class="form-control" value="<?php echo html_escape($l->city); ?>"></div></div>
                        <div class="col-xs-6"><div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?php echo html_escape($l->email); ?>"></div></div>
                    </div>
                    <?php if ($l->is_open) { ?><div class="form-group"><label>Next follow-up</label><input type="datetime-local" name="next_action_at" class="form-control" value="<?php echo $l->next_action_at ? date('Y-m-d\TH:i', strtotime($l->next_action_at)) : ''; ?>"></div><?php } ?>
                    <div class="form-group"><label>Campaign / event</label><input type="text" name="campaign" class="form-control" value="<?php echo html_escape($l->campaign); ?>"></div>
                    <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3"><?php echo html_escape($l->description); ?></textarea></div>
                    <button class="shra-btn shra-btn-outline shra-btn-block"><i class="fa fa-save"></i> Save details</button>
                </form>
            </div>

            <?php if (count($payments)) { $paid_total = array_sum(array_map(function ($x) { return (float) $x->amount; }, $payments)); ?>
            <div class="shra-card shra-mt">
                <div class="shra-card-head"><h4><i class="fa fa-receipt" style="color:var(--green)"></i> Payments taken on calls</h4><span class="shra-pill"><?php echo shra_money($paid_total); ?></span></div>
                <div class="shra-card-body shra-pay-list">
                    <?php foreach ($payments as $pay) { ?>
                    <div class="shra-pay-item">
                        <?php if ($pay->file) { ?>
                            <a href="<?php echo shra_lead_payment_file_url($pay->id); ?>" target="_blank" rel="noopener" title="Open the screenshot">
                                <?php if (strtolower(pathinfo($pay->file, PATHINFO_EXTENSION)) === 'pdf') { ?><span class="shra-ic xs"><i class="fa fa-file-pdf"></i></span><?php } else { ?><img src="<?php echo shra_lead_payment_file_url($pay->id); ?>" alt="Payment screenshot"><?php } ?>
                            </a>
                        <?php } else { ?><span class="shra-ic xs muted" title="No screenshot attached"><i class="fa fa-image"></i></span><?php } ?>
                        <div style="flex:1;min-width:0">
                            <span class="shra-pay-amt"><?php echo shra_money($pay->amount); ?></span><?php if ($pay->method) { ?> <span class="shra-muted">· <?php echo html_escape($pay->method); ?></span><?php } ?>
                            <?php if ($pay->reference) { ?><div class="shra-muted">Ref <?php echo html_escape($pay->reference); ?></div><?php } ?>
                            <?php if ($pay->note) { ?><div class="shra-muted"><?php echo html_escape($pay->note); ?></div><?php } ?>
                            <div class="shra-muted"><?php echo html_escape($pay->staff_name ?: 'System'); ?> · <?php echo shra_datetime($pay->created_at); ?></div>
                        </div>
                        <button type="button" class="shra-ic xs" data-shra-pay-proof="<?php echo $pay->id; ?>" data-lead="<?php echo $l->id; ?>" title="<?php echo $pay->file ? 'Replace the screenshot' : 'Attach the screenshot'; ?>"><i class="fa fa-paperclip"></i></button>
                        <?php if ($can_manage) { ?><button type="button" class="shra-ic xs" data-shra-pay-del="<?php echo $pay->id; ?>" data-lead="<?php echo $l->id; ?>" title="Remove this entry"><i class="fa fa-trash"></i></button><?php } ?>
                    </div>
                    <?php } ?>
                </div>
                <div class="shra-card-body" style="padding-top:0"><div class="help">Advances collected before billing. The counter still raises the real invoice at "Bill now".</div></div>
                <input type="file" id="shra-pay-proof-late" accept="image/jpeg,image/png,image/webp,application/pdf" hidden>
            </div>
            <?php } ?>

            <?php if (count($attribution)) { ?>
            <div class="shra-card shra-mt">
                <div class="shra-card-head"><h4><i class="fa fa-indian-rupee-sign" style="color:var(--green)"></i> Revenue credited</h4></div>
                <div class="shra-table-wrap"><table class="shra-table">
                    <thead><tr><th>Bill</th><th>Agent</th><th class="num">Billed</th><th class="num">Paid</th></tr></thead>
                    <tbody><?php foreach ($attribution as $a) { ?><tr><td><?php echo html_escape($a->enrollment_no); ?><div class="shra-muted" style="font-size:11px"><?php echo html_escape($a->package_name); ?> · <?php echo $a->kind === 'repeat' ? 'renewal' : 'first'; ?> · <?php echo date('d M Y', strtotime($a->credited_at)); ?></div></td><td><?php echo html_escape($a->agent_name ?: '—'); ?></td><td class="num"><?php echo shra_money($a->amount_billed); ?></td><td class="num"><?php echo shra_money($a->amount_paid); ?></td></tr><?php } ?></tbody>
                </table></div>
            </div>
            <?php } ?>

            <?php if ($rider) { ?>
            <div class="shra-card shra-mt"><div class="shra-card-body">
                <div class="shra-person"><span class="shra-avatar"><?php echo strtoupper(mb_substr($rider->full_name, 0, 1)); ?></span><div><b><?php echo html_escape($rider->full_name); ?></b> <span class="shra-muted" style="font-size:12px"><?php echo html_escape($rider->rider_no); ?></span><div class="shra-muted" style="font-size:12px"><?php echo count($enrollments); ?> bill<?php echo count($enrollments) == 1 ? '' : 's'; ?></div></div><a href="<?php echo admin_url('shra/rider/' . $rider->id); ?>" class="shra-btn shra-btn-outline shra-btn-sm" style="margin-left:auto">Rider profile</a></div>
            </div></div>
            <?php } ?>
        </div>

        <div>
            <div class="shra-card">
                <div class="shra-card-head"><h4><i class="fa fa-comment" style="color:var(--gold)"></i> Add note</h4></div>
                <form id="shra-lead-note-form" class="shra-card-body" style="display:flex;gap:8px">
                    <input type="hidden" name="lead_id" value="<?php echo $l->id; ?>">
                    <input type="text" name="text" class="form-control" placeholder="Quick note…" required>
                    <button class="shra-btn shra-btn-outline"><i class="fa fa-plus"></i></button>
                </form>
            </div>
            <div class="shra-card shra-mt">
                <div class="shra-card-head"><h4><i class="fa fa-timeline" style="color:var(--gold)"></i> Timeline</h4><span class="shra-pill"><?php echo count($events); ?></span></div>
                <div class="shra-timeline">
                <?php
                $icons = ['created' => 'fa-plus', 'assigned' => 'fa-headset', 'reassigned' => 'fa-headset', 'call' => 'fa-phone', 'whatsapp' => 'fa-brands fa-whatsapp', 'stage' => 'fa-arrow-right', 'visit_scheduled' => 'fa-calendar-plus', 'visit_rescheduled' => 'fa-calendar-plus',
                    'visited' => 'fa-person-walking', 'no_show' => 'fa-user-slash', 'confirmed' => 'fa-thumbs-up', 'won' => 'fa-trophy', 'lost' => 'fa-xmark', 'junk' => 'fa-trash', 'duplicate_attempt' => 'fa-clone', 'note' => 'fa-comment', 'reopen' => 'fa-rotate-left', 'sla_breach' => 'fa-triangle-exclamation',
                    'payment' => 'fa-receipt', 'payment_removed' => 'fa-receipt', 'payment_proof' => 'fa-paperclip'];
                $outs  = shra_lead_outcomes();
                foreach ($events as $e) {
                    $ic  = $icons[$e->event_type] ?? 'fa-circle';
                    $txt = '';
                    switch ($e->event_type) {
                        case 'call': case 'whatsapp':
                            $meta = json_decode((string) $e->meta, true) ?: [];
                            $what = array_key_exists('stage', $meta)
                                  ? ($meta['stage'] !== '' ? shra_lead_stage_label($meta['stage']) : 'Status kept')
                                  : ($outs[$e->outcome][0] ?? $e->outcome);
                            $txt  = ($e->event_type === 'whatsapp' ? 'WhatsApp' : 'Call') . ' · <b>' . html_escape($what) . '</b>' . ($e->to_value ? ' · next ' . shra_datetime($e->to_value, false) : '');
                            break;
                        case 'stage': $txt = shra_lead_stage_label($e->from_value) . ' → <b>' . shra_lead_stage_label($e->to_value) . '</b>'; break;
                        case 'assigned': case 'reassigned': $txt = ucfirst($e->event_type) . ' to <b>' . html_escape(get_staff_full_name($e->to_value)) . '</b>'; break;
                        case 'visit_scheduled': case 'visit_rescheduled': $txt = ($e->event_type === 'visit_rescheduled' ? 'Visit rescheduled → ' : 'Visit scheduled → ') . '<b>' . html_escape($e->to_value) . '</b>'; break;
                        case 'won': $txt = '<b>Joined</b> · billed ' . shra_money($e->to_value); break;
                        case 'lost': $txt = '<b>Lost</b> · ' . html_escape($e->to_value); break;
                        case 'confirmed': $txt = '<b>Visited & confirmed</b> · expected ' . shra_money($e->to_value); break;
                        case 'payment':
                            $meta = json_decode((string) $e->meta, true) ?: [];
                            $txt  = '<b>Payment taken</b> · ' . shra_money($e->to_value) . (!empty($meta['method']) ? ' · ' . html_escape($meta['method']) : '')
                                  . (!empty($meta['reference']) ? ' · ref ' . html_escape($meta['reference']) : '')
                                  . (!empty($meta['file']) && !empty($meta['payment_id']) ? ' · <a href="' . shra_lead_payment_file_url($meta['payment_id']) . '" target="_blank" rel="noopener"><i class="fa fa-paperclip"></i> screenshot</a>' : '');
                            break;
                        case 'payment_removed': $txt = '<b>Payment entry removed</b> · ' . shra_money($e->to_value); break;
                        case 'payment_proof':
                            $meta = json_decode((string) $e->meta, true) ?: [];
                            $txt  = '<b>Payment screenshot attached</b> · ' . shra_money($e->to_value)
                                  . (!empty($meta['payment_id']) ? ' · <a href="' . shra_lead_payment_file_url($meta['payment_id']) . '" target="_blank" rel="noopener">open</a>' : '');
                            break;
                        default: $txt = '<b>' . ucfirst(str_replace('_', ' ', $e->event_type)) . '</b>';
                    }
                ?>
                    <div class="shra-tl <?php echo $e->event_type; ?>">
                        <span class="shra-tl-ic"><i class="fa <?php echo $ic; ?>"></i></span>
                        <div class="shra-tl-body">
                            <div><?php echo $txt; ?></div>
                            <?php if ($e->note) { ?><div class="shra-tl-note"><?php echo nl2br(html_escape($e->note)); ?></div><?php } ?>
                            <div class="shra-tl-meta"><?php echo html_escape($e->staff_name ?: 'System'); ?> · <?php echo shra_datetime($e->created_at); ?></div>
                        </div>
                    </div>
                <?php } ?>
                </div>
            </div>
            <?php if (count($notes)) { ?>
            <div class="shra-card shra-mt">
                <div class="shra-card-head"><h4><i class="fa fa-note-sticky" style="color:var(--gold)"></i> Notes</h4></div>
                <div class="shra-card-body">
                <?php foreach ($notes as $n) { ?><div style="padding:8px 0;border-bottom:1px solid var(--line)"><?php echo nl2br(html_escape($n->description)); ?><div class="shra-muted" style="font-size:11px;margin-top:3px"><?php echo html_escape(get_staff_full_name($n->addedfrom)); ?> · <?php echo shra_datetime($n->dateadded); ?></div></div><?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
<script>$(function () { SHRA.onLeadUpdated = function () { location.reload(); }; });</script>
</body>
</html>
