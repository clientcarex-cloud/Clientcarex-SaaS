<?php defined('BASEPATH') or exit('No direct script access allowed');
/** Shared lead modals + JS config. Expects $agents, $sources, $packages, $slots, $reasons, $methods, $payment_modes, $templates, $weekend, $can_all, $can_manage */
$tomorrow = date('Y-m-d\TH:i', strtotime('tomorrow 10:00'));
/* How long the SLA gives an agent to make the first call — shown as the hint under the
   (now optional) "First call by" box, since a blank field falls back to exactly this. */
$sla     = max(5, (int) get_option('shra_lead_sla_minutes'));
$sla_txt = $sla % 60 === 0 ? ($sla / 60) . ($sla === 60 ? ' hour' : ' hours') : $sla . ' minutes';
$methods  = isset($methods) ? $methods : shra_lead_payment_methods();
$cur_sym  = get_base_currency()->symbol;
/* Counter modes (id => name) for the Confirm dialog. Falls back to the free-text lead
   methods when the module is used without the billing screen. */
$pay_modes = isset($payment_modes) ? $payment_modes : [];
$can_bill  = shra_can_billing();
/* What the package will actually be billed at — the running offer is applied, exactly as
   the counter would. Keeps the Confirm dialog's maths equal to the invoice. */
$pkg_total = [];
foreach ($packages as $pk) {
    $pkg_total[$pk->id] = (float) get_instance()->shra_model->quote($pk)['total'];
}
?>
<!-- Add lead -->
<div class="modal fade shra" id="shra-lead-add" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form id="shra-lead-add-form" autocomplete="off">
    <input type="hidden" name="mark_visited" value="0">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa-solid fa-phone-volume"></i> New lead</h4></div>
    <div class="modal-body">
        <div class="shra-sec">Contact</div>
        <div class="row">
            <div class="col-sm-6"><div class="form-group"><label>Name <span class="shra-req">*</span></label><input type="text" name="name" class="form-control" required placeholder="Parent / rider name"></div></div>
            <div class="col-sm-6"><div class="form-group"><label>Mobile <span class="shra-req">*</span></label><input type="tel" name="phone" class="form-control" required inputmode="tel" placeholder="10-digit mobile"><div id="shra-lead-dup" class="help" style="margin-top:4px"></div></div></div>
        </div>
        <div class="row">
            <div class="col-sm-6"><div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" placeholder="name@example.com"></div></div>
            <div class="col-sm-6"><div class="form-group"><label>City / area</label><input type="text" name="city" class="form-control" placeholder="Where they ride from"></div></div>
        </div>

        <div class="shra-sec">Interest</div>
        <div class="row">
            <div class="col-sm-4"><div class="form-group"><label>Riding for</label><select name="rider_for" class="form-control"><option value="self">Self (adult)</option><option value="child">My child</option><option value="both">Self + child</option></select></div></div>
            <div class="col-sm-3"><div class="form-group"><label>Rider age</label><input type="number" name="rider_age" class="form-control" min="2" max="90" placeholder="yrs"></div></div>
            <div class="col-sm-5"><div class="form-group"><label>Source <span class="shra-req">*</span></label><select name="source" class="form-control" required><option value="">Where did they come from?</option><?php foreach ($sources as $s) { ?><option value="<?php echo $s->id; ?>"><?php echo html_escape($s->name); ?></option><?php } ?></select></div></div>
        </div>
        <div class="form-group"><label>Interested in</label><select name="interest_package_id" class="form-control"><option value="">Not sure yet</option><?php foreach ($packages as $pk) { ?><option value="<?php echo $pk->id; ?>"><?php echo ucfirst($pk->audience) . ' · ' . html_escape($pk->name) . ' · ' . shra_money($pk->price); ?></option><?php } ?></select></div>

        <div class="shra-sec">Schedule preference</div>
        <div class="row">
            <div class="col-sm-6"><div class="form-group"><label>Start date <span class="shra-opt">optional</span></label><input type="date" name="preferred_start_date" class="form-control" min="<?php echo date('Y-m-d'); ?>"></div></div>
            <div class="col-sm-6"><div class="form-group"><label>Batch <span class="shra-opt">optional</span></label><select name="preferred_batch" class="form-control"><option value="">Not decided</option><?php foreach (shra_batches() as $bk => $b) { ?><option value="<?php echo $bk; ?>"><?php echo html_escape($b['text']); ?></option><?php } ?></select></div></div>
        </div>
        <div class="help" style="margin:-6px 0 4px"><?php echo html_escape(shra_fcfs_note()); ?></div>

        <div class="shra-sec">Follow-up</div>
        <div class="row">
            <?php if ($can_all) { ?>
            <div class="col-sm-6"><div class="form-group"><label>Assign to</label><select name="assigned" class="form-control"><option value="">Auto (round robin)</option><?php foreach ($agents as $a) { ?><option value="<?php echo $a->staffid; ?>" <?php echo $a->staffid == get_staff_user_id() ? 'selected' : ''; ?>><?php echo html_escape($a->full_name); ?></option><?php } ?></select></div></div>
            <?php } ?>
            <div class="col-sm-<?php echo $can_all ? '6' : '12'; ?>"><div class="form-group"><label>First call by <span class="shra-opt">optional</span></label><input type="datetime-local" name="next_action_at" class="form-control"><div class="help">Left blank, the call is scheduled automatically <?php echo html_escape($sla_txt); ?> from now.</div></div></div>
        </div>
        <div class="form-group"><label>Notes</label><textarea name="description" class="form-control" rows="2" placeholder="What they asked, best time to call…"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="shra-btn shra-btn-outline" data-dismiss="modal">Cancel</button><button type="submit" class="shra-btn shra-btn-primary"><i class="fa fa-plus"></i> Add lead</button></div>
    </form>
</div></div></div>

<!-- Log call -->
<div class="modal fade shra" id="shra-lead-call" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form id="shra-lead-call-form" enctype="multipart/form-data">
    <input type="hidden" name="lead_id"><input type="hidden" name="channel" value="call">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-phone"></i> Log call · <span class="shra-m-name"></span></h4></div>
    <div class="modal-body">
        <div class="shra-m-head"><span class="shra-m-phone"></span><span class="shra-m-money"></span></div>
        <div id="shra-call-stage">
            <label>Status <span class="shra-muted" style="font-weight:400;font-size:11.5px">— where the lead stands after this call</span></label>
            <div class="shra-chips" id="shra-call-stage-list"></div>
            <input type="hidden" name="stage" value="">
        </div>
        <div id="shra-call-next">
            <label style="margin-top:14px">Next follow-up <span class="shra-req">*</span></label>
            <div class="shra-chips">
                <button type="button" class="shra-chip" data-plus="2 hours">In 2 h</button>
                <button type="button" class="shra-chip" data-plus="tomorrow 10:00">Tomorrow 10 AM</button>
                <button type="button" class="shra-chip" data-plus="tomorrow 18:00">Tomorrow 6 PM</button>
                <button type="button" class="shra-chip" data-plus="+3 days 11:00">+3 days</button>
                <button type="button" class="shra-chip" data-plus="<?php echo $weekend['sat']; ?> 09:00">Sat</button>
                <button type="button" class="shra-chip" data-plus="<?php echo $weekend['sun']; ?> 09:00">Sun</button>
                <button type="button" class="shra-chip" data-plus="+1 week 11:00">Next week</button>
            </div>
            <input type="datetime-local" name="next_action_at" class="form-control" style="margin-top:8px" value="<?php echo $tomorrow; ?>">
        </div>
        <div class="form-group shra-note-hl" style="margin-top:12px"><label><i class="fa fa-pen-to-square"></i> Call Note</label><input type="text" name="note" class="form-control" placeholder="Optional — what was discussed"></div>

        <div id="shra-call-pay">
            <label class="shra-pay-switch"><input type="checkbox" id="shra-pay-on"> <i class="fa-solid fa-indian-rupee-sign"></i> Payment taken on this call <span class="shra-muted">— advance / part payment</span></label>
            <div class="shra-pay-box" id="shra-pay-box" hidden>
                <div class="row">
                    <div class="col-xs-6"><div class="form-group"><label>Amount collected <span class="shra-req">*</span></label>
                        <div class="input-group"><span class="input-group-addon"><?php echo html_escape($cur_sym); ?></span>
                        <input type="number" name="paid_amount" class="form-control" min="1" step="1" inputmode="decimal" placeholder="e.g. 50% advance"></div></div></div>
                    <div class="col-xs-6"><div class="form-group"><label>Paid by</label>
                        <select name="paid_method" class="form-control"><?php foreach ($methods as $m) { ?><option value="<?php echo html_escape($m); ?>"><?php echo html_escape($m); ?></option><?php } ?></select></div></div>
                </div>
                <div class="form-group"><label>Reference / UPI ID <span class="shra-muted" style="font-weight:400">— optional</span></label><input type="text" name="paid_reference" class="form-control" placeholder="Transaction or receipt number"></div>
                <div class="form-group">
                    <label>Payment screenshot</label>
                    <label class="shra-pay-file" for="shra-pay-proof"><i class="fa fa-paperclip"></i> <span>Attach the screenshot the customer sent</span></label>
                    <input type="file" name="payment_proof" id="shra-pay-proof" accept="image/jpeg,image/png,image/webp,application/pdf" hidden>
                    <div id="shra-pay-preview" hidden><img alt="" id="shra-pay-thumb"><span id="shra-pay-fname"></span><button type="button" class="shra-ic xs" id="shra-pay-clear" title="Remove"><i class="fa fa-xmark"></i></button></div>
                    <div class="help">JPG, PNG, WEBP or PDF up to 5 MB. Only staff who can see this lead can open it.</div>
                </div>
                <div class="form-group" style="margin-bottom:0"><label>Payment note</label><input type="text" name="paid_note" class="form-control" placeholder="Optional — e.g. balance on the first visit"></div>
            </div>
        </div>

        <div class="help">Booking a visit, confirming or losing the lead has its own step — use the row buttons.</div>

        <div class="shra-cl-wrap">
            <div class="shra-cl-head"><i class="fa fa-clock-rotate-left"></i> Call history</div>
            <div id="shra-call-log"></div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="shra-btn shra-btn-outline" data-dismiss="modal">Cancel</button><button type="submit" class="shra-btn shra-btn-primary"><i class="fa fa-check"></i> Save</button></div>
    </form>
</div></div></div>

<!-- Schedule visit -->
<div class="modal fade shra" id="shra-lead-visit" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content">
    <form id="shra-lead-visit-form">
    <input type="hidden" name="lead_id">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-calendar-check"></i> Visit · <span class="shra-m-name"></span></h4></div>
    <div class="modal-body">
        <label>Date</label>
        <div class="shra-chips" style="margin-bottom:8px">
            <button type="button" class="shra-chip" data-date="<?php echo $weekend['sat']; ?>">Sat <?php echo date('d M', strtotime($weekend['sat'])); ?></button>
            <button type="button" class="shra-chip" data-date="<?php echo $weekend['sun']; ?>">Sun <?php echo date('d M', strtotime($weekend['sun'])); ?></button>
            <button type="button" class="shra-chip" data-date="<?php echo date('Y-m-d'); ?>">Today</button>
            <button type="button" class="shra-chip" data-date="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">Tomorrow</button>
        </div>
        <input type="date" name="visit_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" value="<?php echo $weekend['sat']; ?>" required>
        <label style="margin-top:12px">Slot</label>
        <select name="visit_slot" class="form-control" required><?php foreach ($slots as $s) { ?><option value="<?php echo html_escape($s); ?>"><?php echo html_escape(shra_slot($s)); ?></option><?php } ?></select>
        <div class="form-group" style="margin-top:12px"><label>Note</label><input type="text" name="note" class="form-control" placeholder="Optional"></div>
        <div class="help">The agent gets a reminder an hour before. Front desk sees it on the Visits board.</div>
    </div>
    <div class="modal-footer"><button type="button" class="shra-btn shra-btn-outline" data-dismiss="modal">Cancel</button><button type="submit" class="shra-btn shra-btn-primary"><i class="fa fa-check"></i> Schedule</button></div>
    </form>
</div></div></div>

<!-- Lost -->
<div class="modal fade shra" id="shra-lead-lost" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content">
    <form id="shra-lead-lost-form">
    <input type="hidden" name="lead_id">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-xmark"></i> Mark lost · <span class="shra-m-name"></span></h4></div>
    <div class="modal-body">
        <label>Reason <span class="shra-req">*</span></label>
        <select name="reason" class="form-control" required><option value="">Pick a reason</option><?php foreach ($reasons as $r) { ?><option><?php echo html_escape($r); ?></option><?php } ?></select>
        <div class="form-group" style="margin-top:12px"><label>Note</label><input type="text" name="note" class="form-control" placeholder="Optional"></div>
        <label style="margin-top:4px"><input type="checkbox" name="as_junk" value="1"> Wrong number / spam (junk instead of lost)</label>
    </div>
    <div class="modal-footer"><button type="button" class="shra-btn shra-btn-outline" data-dismiss="modal">Cancel</button><button type="submit" class="shra-btn shra-btn-danger"><i class="fa fa-check"></i> Mark lost</button></div>
    </form>
</div></div></div>

<!-- Arrived & confirm — one dialog: the arrival (backdatable), the package, the money, the sale -->
<div class="modal fade shra" id="shra-lead-confirm" tabindex="-1"><div class="modal-dialog" style="width:520px;max-width:95vw"><div class="modal-content">
    <form id="shra-lead-confirm-form" enctype="multipart/form-data">
    <input type="hidden" name="lead_id">
    <input type="hidden" name="complete" value="0">
    <input type="hidden" name="force" value="0">
    <input type="hidden" name="bill_token" value="">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-thumbs-up"></i> Arrived &amp; confirm &middot; <span class="shra-m-name"></span></h4></div>
    <div class="modal-body">
        <div class="shra-m-head"><span class="shra-m-phone"></span><span class="shra-m-money"></span></div>
        <label>Arrived on <span class="shra-muted" style="font-weight:400">&mdash; pick the real day if the entry was missed</span></label>
        <div class="shra-chips" style="margin-bottom:8px">
            <button type="button" class="shra-chip on" data-cf-date="<?php echo date('Y-m-d'); ?>">Today</button>
            <button type="button" class="shra-chip" data-cf-date="<?php echo date('Y-m-d', strtotime('-1 day')); ?>">Yesterday</button>
            <button type="button" class="shra-chip" data-cf-date="<?php echo date('Y-m-d', strtotime('-2 days')); ?>"><?php echo date('D d M', strtotime('-2 days')); ?></button>
        </div>
        <input type="date" name="entry_date" class="form-control" max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" required>

        <label style="margin-top:12px">Package chosen</label>
        <select name="package_id" class="form-control"><option value="">Not decided</option><?php foreach ($packages as $pk) { ?><option value="<?php echo $pk->id; ?>" data-price="<?php echo $pkg_total[$pk->id]; ?>"><?php echo ucfirst($pk->audience) . ' &middot; ' . html_escape($pk->name) . ' &middot; ' . shra_money($pkg_total[$pk->id]); ?></option><?php } ?></select>

        <!-- What this lead owes right now: the deal, the advances already taken, the balance. -->
        <div class="shra-cf-bill" id="shra-cf-bill">
            <div class="shra-cf-line"><span>Bill amount</span><b id="shra-cf-total">&mdash;</b></div>
            <div class="shra-cf-line"><span>Already collected <span class="shra-muted">advances on calls</span></span><b id="shra-cf-paid">&mdash;</b></div>
            <div class="shra-cf-line grand"><span>Balance to collect</span><b id="shra-cf-due">&mdash;</b></div>
        </div>

        <label style="margin-top:12px"><i class="fa-solid fa-indian-rupee-sign"></i> Collect payment <span class="shra-muted" style="font-weight:400">&mdash; leave the amount empty if nothing was taken</span></label>
        <div class="shra-pay-box" id="shra-cf-pay-box">
            <div class="row">
                <div class="col-xs-6"><div class="form-group"><label>Amount collected</label>
                    <div class="input-group"><span class="input-group-addon"><?php echo html_escape($cur_sym); ?></span>
                    <input type="number" name="paid_amount" class="form-control" min="1" step="1" inputmode="decimal"></div></div></div>
                <div class="col-xs-6"><div class="form-group"><label>Paid by</label>
                    <select name="payment_mode" class="form-control">
                        <?php if (count($pay_modes)) { foreach ($pay_modes as $m) { ?><option value="<?php echo $m->id; ?>" <?php echo $m->selected_by_default ? 'selected' : ''; ?>><?php echo html_escape($m->name); ?></option><?php } } else { foreach ($methods as $m) { ?><option value="<?php echo html_escape($m); ?>"><?php echo html_escape($m); ?></option><?php } } ?>
                    </select></div></div>
            </div>
            <div class="form-group"><label>Reference / UPI ID <span class="shra-muted" style="font-weight:400">&mdash; optional</span></label><input type="text" name="paid_reference" class="form-control" placeholder="Transaction or receipt number"></div>
            <div class="form-group" style="margin-bottom:0">
                <label class="shra-pay-file" for="shra-cf-proof"><i class="fa fa-paperclip"></i> <span>Attach the payment screenshot &mdash; optional</span></label>
                <input type="file" name="payment_proof" id="shra-cf-proof" accept="image/jpeg,image/png,image/webp,application/pdf" hidden>
                <div id="shra-cf-preview" hidden><img alt="" id="shra-cf-thumb"><span id="shra-cf-fname"></span><button type="button" class="shra-ic xs" id="shra-cf-clear" title="Remove"><i class="fa fa-xmark"></i></button></div>
            </div>
        </div>

        <?php if ($can_bill) { ?>
        <label class="shra-pay-switch"><input type="checkbox" id="shra-cf-complete"> <i class="fa-solid fa-cash-register"></i> Raise the bill &amp; mark complete <span class="shra-muted">&mdash; no second trip to the counter</span></label>
        <div class="help" id="shra-cf-complete-help" hidden></div>
        <?php } ?>

        <div class="form-group" style="margin-top:12px"><label>Note</label><input type="text" name="note" class="form-control" placeholder="Optional"></div>
    </div>
    <div class="modal-footer">
        <button type="button" class="shra-btn shra-btn-outline" data-dismiss="modal">Cancel</button>
        <button type="button" class="shra-btn shra-btn-outline" id="shra-cf-arrived"><i class="fa fa-person-walking"></i> Arrived only</button>
        <button type="submit" class="shra-btn shra-btn-gold"><i class="fa fa-check"></i> <span id="shra-cf-submit-txt">Confirm</span></button>
    </div>
    </form>
</div></div></div>

<?php if ($can_manage) { ?>
<!-- Reassign -->
<div class="modal fade shra" id="shra-lead-reassign" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content">
    <form id="shra-lead-reassign-form">
    <input type="hidden" name="lead_id">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-headset"></i> Reassign · <span class="shra-m-name"></span></h4></div>
    <div class="modal-body">
        <label>Agent</label>
        <select name="staff_id" class="form-control" required><?php foreach ($agents as $a) { ?><option value="<?php echo $a->staffid; ?>"><?php echo html_escape($a->full_name); ?></option><?php } ?></select>
        <div class="form-group" style="margin-top:12px"><label>Reason</label><input type="text" name="note" class="form-control" placeholder="Why (logged on the lead)"></div>
    </div>
    <div class="modal-footer"><button type="button" class="shra-btn shra-btn-outline" data-dismiss="modal">Cancel</button><button type="submit" class="shra-btn shra-btn-primary"><i class="fa fa-check"></i> Reassign</button></div>
    </form>
</div></div></div>
<?php } ?>

<!-- WhatsApp template picker -->
<div class="modal fade shra" id="shra-lead-wa" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa-brands fa-whatsapp"></i> WhatsApp · <span class="shra-m-name"></span></h4></div>
    <div class="modal-body">
        <div id="shra-wa-list"></div>
        <div class="help" style="margin-top:8px">Opens WhatsApp with the message prefilled. Log the call and set the status afterwards.</div>
    </div>
</div></div></div>

<!-- Copy-message picker (the copy icon next to WhatsApp) -->
<div class="modal fade shra" id="shra-lead-wa-copy" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-copy"></i> Copy message · <span class="shra-m-name"></span></h4></div>
    <div class="modal-body">
        <div id="shra-wa-copy-list"></div>
        <div class="help" style="margin-top:8px">Tap a message to copy it, then paste it into WhatsApp.</div>
    </div>
</div></div></div>

<!-- End-of-day report -->
<div class="modal fade shra" id="shra-lead-eod" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa-solid fa-file-lines"></i> End-of-day report</h4></div>
    <div class="modal-body">
        <div class="shra-eod-bar">
            <?php if ($can_all) { ?>
            <select id="shra-eod-agent" class="form-control" title="Whose day">
                <?php foreach ($agents as $a) { ?><option value="<?php echo $a->staffid; ?>" <?php echo $a->staffid == get_staff_user_id() ? 'selected' : ''; ?>><?php echo html_escape($a->full_name); ?><?php echo $a->staffid == get_staff_user_id() ? ' (me)' : ''; ?></option><?php } ?>
            </select>
            <?php } ?>
            <input type="date" id="shra-eod-date" class="form-control" max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" title="Which day">
            <span class="help" style="margin:0">Paste it straight into the team group.</span>
        </div>
        <div class="shra-eod-wrap">
            <div class="shra-eod-bubble" id="shra-eod-preview"><span class="shra-eod-loading"><i class="fa fa-circle-notch fa-spin"></i> Building the report&hellip;</span></div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="shra-btn shra-btn-outline" data-dismiss="modal">Close</button>
        <a href="#" target="_blank" rel="noopener" class="shra-btn shra-btn-outline" id="shra-eod-wa"><i class="fa-brands fa-whatsapp"></i> Send on WhatsApp</a>
        <button type="button" class="shra-btn shra-btn-primary" id="shra-eod-copy"><i class="fa fa-copy"></i> Copy message</button>
    </div>
</div></div></div>

<script>
window.SHRA_LEADS_CFG = {
    urls: {
        add: <?php echo json_encode(admin_url('shra/shra_leads/add')); ?>,
        check: <?php echo json_encode(admin_url('shra/shra_leads/check_phone')); ?>,
        call: <?php echo json_encode(admin_url('shra/shra_leads/log_call')); ?>,
        visit: <?php echo json_encode(admin_url('shra/shra_leads/schedule_visit')); ?>,
        visited: <?php echo json_encode(admin_url('shra/shra_leads/visited')); ?>,
        no_show: <?php echo json_encode(admin_url('shra/shra_leads/no_show')); ?>,
        confirm: <?php echo json_encode(admin_url('shra/shra_leads/confirm')); ?>,
        lost: <?php echo json_encode(admin_url('shra/shra_leads/lost')); ?>,
        junk: <?php echo json_encode(admin_url('shra/shra_leads/junk')); ?>,
        reopen: <?php echo json_encode(admin_url('shra/shra_leads/reopen')); ?>,
        stage: <?php echo json_encode(admin_url('shra/shra_leads/stage')); ?>,
        reassign: <?php echo json_encode(admin_url('shra/shra_leads/reassign')); ?>,
        note: <?php echo json_encode(admin_url('shra/shra_leads/note')); ?>,
        payment_del: <?php echo json_encode(admin_url('shra/shra_leads/delete_payment')); ?>,
        payment_proof: <?php echo json_encode(admin_url('shra/shra_leads/attach_proof')); ?>,
        call_log: <?php echo json_encode(admin_url('shra/shra_leads/call_log')); ?>,
        details: <?php echo json_encode(admin_url('shra/shra_leads/update_details')); ?>,
        eod: <?php echo json_encode(admin_url('shra/shra_leads/eod')); ?>,
        lead_del: <?php echo json_encode(admin_url('shra/shra_leads/delete_lead')); ?>
    },
    templates: <?php echo json_encode($templates); ?>,
    copyMsg: <?php echo json_encode(shra_lead_wa_copy_msg()); ?>,
    masterMsg: <?php echo json_encode(shra_lead_wa_master_msg()); ?>,
    offerLine: <?php echo json_encode(shra_lead_wa_offer_line()); ?>,
    offerMsg: <?php echo json_encode(shra_lead_wa_offer_msg()); ?>,
    links: <?php echo json_encode(shra_lead_wa_links()); ?>,
    academy: <?php echo json_encode(get_option('shra_academy_name') ?: 'SHRA'); ?>,
    location: <?php echo json_encode(get_option('shra_lead_landing_location') ?: ''); ?>,
    maps: <?php echo json_encode(shra_lead_maps_url()); ?>,
    batches: <?php echo json_encode(shra_batch_line(' and ')); ?>,
    selfBooking: <?php echo json_encode(site_url('inquire')); ?>,
    joinUrl: <?php echo json_encode(site_url('join')); ?>,
    agent: <?php echo json_encode(get_staff_full_name(get_staff_user_id())); ?>,
    cc: <?php echo json_encode(preg_replace('/\D+/', '', (string) get_option('shra_lead_phone_country'))); ?>,
    canAll: <?php echo $can_all ? 'true' : 'false'; ?>,
    canBill: <?php echo $can_bill ? 'true' : 'false'; ?>,
    money: <?php echo json_encode(['sym' => $cur_sym, 'before' => get_base_currency()->placement !== 'after']); ?>,
    stages: <?php echo json_encode(array_map(function ($d) { return ['label' => $d[0], 'color' => $d[2]]; }, shra_lead_stage_defs())); ?>,
    transitions: <?php echo json_encode(shra_lead_transitions()); ?>,
    quickStages: <?php echo json_encode(shra_lead_quick_stages()); ?>
};
</script>
