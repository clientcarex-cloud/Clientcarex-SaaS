<?php defined('BASEPATH') or exit('No direct script access allowed');
/** Shared lead modals + JS config. Expects $agents, $sources, $packages, $slots, $reasons, $outcomes, $templates, $weekend, $can_all, $can_manage */
$tomorrow = date('Y-m-d\TH:i', strtotime('tomorrow 10:00'));
?>
<!-- Add lead -->
<div class="modal fade shra" id="shra-lead-add" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form id="shra-lead-add-form" autocomplete="off">
    <input type="hidden" name="mark_visited" value="0">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa-solid fa-phone-volume"></i> New lead</h4></div>
    <div class="modal-body">
        <div class="row">
            <div class="col-sm-7"><div class="form-group"><label>Name *</label><input type="text" name="name" class="form-control" required placeholder="Parent / rider name"></div></div>
            <div class="col-sm-5"><div class="form-group"><label>Mobile *</label><input type="tel" name="phone" class="form-control" required inputmode="tel" placeholder="10-digit mobile"><div id="shra-lead-dup" class="help" style="margin-top:4px"></div></div></div>
        </div>
        <div class="row">
            <div class="col-sm-4"><div class="form-group"><label>Riding for</label><select name="rider_for" class="form-control"><option value="self">Self (adult)</option><option value="child">My child</option><option value="both">Self + child</option></select></div></div>
            <div class="col-sm-3"><div class="form-group"><label>Rider age</label><input type="number" name="rider_age" class="form-control" min="2" max="90" placeholder="yrs"></div></div>
            <div class="col-sm-5"><div class="form-group"><label>Source *</label><select name="source" class="form-control" required><option value="">Where did they come from?</option><?php foreach ($sources as $s) { ?><option value="<?php echo $s->id; ?>"><?php echo html_escape($s->name); ?></option><?php } ?></select></div></div>
        </div>
        <div class="row">
            <div class="col-sm-6"><div class="form-group"><label>Interested in</label><select name="interest_package_id" class="form-control"><option value="">Not sure yet</option><?php foreach ($packages as $pk) { ?><option value="<?php echo $pk->id; ?>"><?php echo ucfirst($pk->audience) . ' · ' . html_escape($pk->name) . ' · ' . shra_money($pk->price); ?></option><?php } ?></select></div></div>
            <div class="col-sm-3"><div class="form-group"><label>City / area</label><input type="text" name="city" class="form-control"></div></div>
            <div class="col-sm-3"><div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div></div>
        </div>
        <div class="row">
            <?php if ($can_all) { ?>
            <div class="col-sm-6"><div class="form-group"><label>Assign to</label><select name="assigned" class="form-control"><option value="">Auto (round robin)</option><?php foreach ($agents as $a) { ?><option value="<?php echo $a->staffid; ?>" <?php echo $a->staffid == get_staff_user_id() ? 'selected' : ''; ?>><?php echo html_escape($a->full_name); ?></option><?php } ?></select></div></div>
            <?php } ?>
            <div class="col-sm-6"><div class="form-group"><label>First call by</label><input type="datetime-local" name="next_action_at" class="form-control" value="<?php echo date('Y-m-d\TH:i', time() + max(5, (int) get_option('shra_lead_sla_minutes')) * 60); ?>"></div></div>
        </div>
        <div class="form-group"><label>Notes</label><textarea name="description" class="form-control" rows="2" placeholder="What they asked, best time to call…"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="shra-btn shra-btn-outline" data-dismiss="modal">Cancel</button><button type="submit" class="shra-btn shra-btn-primary"><i class="fa fa-plus"></i> Add lead</button></div>
    </form>
</div></div></div>

<!-- Log call -->
<div class="modal fade shra" id="shra-lead-call" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form id="shra-lead-call-form">
    <input type="hidden" name="lead_id"><input type="hidden" name="outcome"><input type="hidden" name="channel" value="call">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-phone"></i> Log call · <span class="shra-m-name"></span></h4></div>
    <div class="modal-body">
        <div class="shra-m-phone" style="margin-bottom:12px"></div>
        <label>Outcome</label>
        <div class="shra-outcomes">
            <?php foreach ($outcomes as $k => $o) { ?><button type="button" class="shra-oc <?php echo $k; ?>" data-outcome="<?php echo $k; ?>" data-next="<?php echo $o[2] ? 1 : 0; ?>"><?php echo $o[0]; ?></button><?php } ?>
        </div>
        <div id="shra-call-next">
            <label style="margin-top:14px">Next follow-up *</label>
            <div class="shra-chips">
                <button type="button" class="shra-chip" data-plus="2 hours">In 2 h</button>
                <button type="button" class="shra-chip" data-plus="tomorrow 10:00">Tomorrow 10am</button>
                <button type="button" class="shra-chip" data-plus="tomorrow 18:00">Tomorrow 6pm</button>
                <button type="button" class="shra-chip" data-plus="+3 days 11:00">+3 days</button>
                <button type="button" class="shra-chip" data-plus="<?php echo $weekend['sat']; ?> 09:00">Sat</button>
                <button type="button" class="shra-chip" data-plus="<?php echo $weekend['sun']; ?> 09:00">Sun</button>
                <button type="button" class="shra-chip" data-plus="+1 week 11:00">Next week</button>
            </div>
            <input type="datetime-local" name="next_action_at" class="form-control" style="margin-top:8px" value="<?php echo $tomorrow; ?>">
        </div>
        <div class="form-group" style="margin-top:12px"><label>Note</label><input type="text" name="note" class="form-control" placeholder="Optional — what was discussed"></div>
        <div class="help">Need a visit instead? Close this and use the <i class="fa fa-calendar-plus"></i> button.</div>
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
        <select name="visit_slot" class="form-control" required><?php foreach ($slots as $s) { ?><option value="<?php echo html_escape($s); ?>"><?php echo html_escape($s); ?></option><?php } ?></select>
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
        <label>Reason *</label>
        <select name="reason" class="form-control" required><option value="">Pick a reason</option><?php foreach ($reasons as $r) { ?><option><?php echo html_escape($r); ?></option><?php } ?></select>
        <div class="form-group" style="margin-top:12px"><label>Note</label><input type="text" name="note" class="form-control" placeholder="Optional"></div>
        <label style="margin-top:4px"><input type="checkbox" name="as_junk" value="1"> Wrong number / spam (junk instead of lost)</label>
    </div>
    <div class="modal-footer"><button type="button" class="shra-btn shra-btn-outline" data-dismiss="modal">Cancel</button><button type="submit" class="shra-btn shra-btn-danger"><i class="fa fa-check"></i> Mark lost</button></div>
    </form>
</div></div></div>

<!-- Confirm (visited & confirmed) -->
<div class="modal fade shra" id="shra-lead-confirm" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content">
    <form id="shra-lead-confirm-form">
    <input type="hidden" name="lead_id">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-thumbs-up"></i> Confirmed · <span class="shra-m-name"></span></h4></div>
    <div class="modal-body">
        <label>Package chosen</label>
        <select name="package_id" class="form-control"><option value="">Not decided</option><?php foreach ($packages as $pk) { ?><option value="<?php echo $pk->id; ?>" data-price="<?php echo $pk->price; ?>"><?php echo ucfirst($pk->audience) . ' · ' . html_escape($pk->name) . ' · ' . shra_money($pk->price); ?></option><?php } ?></select>
        <label style="margin-top:12px">Expected amount</label>
        <input type="number" name="expected_value" class="form-control" step="1" min="0" placeholder="Leave blank to use the package price">
        <div class="form-group" style="margin-top:12px"><label>Note</label><input type="text" name="note" class="form-control" placeholder="Optional"></div>
    </div>
    <div class="modal-footer"><button type="button" class="shra-btn shra-btn-outline" data-dismiss="modal">Cancel</button><button type="submit" class="shra-btn shra-btn-gold"><i class="fa fa-check"></i> Confirm</button></div>
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
        <div class="help" style="margin-top:8px">Opens WhatsApp with the message prefilled. Log the outcome afterwards.</div>
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
        details: <?php echo json_encode(admin_url('shra/shra_leads/update_details')); ?>
    },
    templates: <?php echo json_encode($templates); ?>,
    academy: <?php echo json_encode(get_option('shra_academy_name') ?: 'SHRA'); ?>,
    agent: <?php echo json_encode(get_staff_full_name(get_staff_user_id())); ?>,
    cc: <?php echo json_encode(preg_replace('/\D+/', '', (string) get_option('shra_lead_phone_country'))); ?>,
    canAll: <?php echo $can_all ? 'true' : 'false'; ?>
};
</script>
