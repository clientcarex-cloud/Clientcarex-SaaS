<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'billing'; include __DIR__ . '/_nav.php'; ?>

    <div id="shra-bill-done" style="display:none;margin-bottom:18px"></div>

    <?php echo form_open(admin_url('shra/bill'), ['id' => 'shra-bill-form']); ?>
    <input type="hidden" name="rider_id" id="shra-rider-id">
    <input type="hidden" name="package_id" id="shra-package-id">
    <input type="hidden" name="bill_token" id="shra-bill-token" value="<?php echo $bill_token; ?>">
    <input type="hidden" name="force" id="shra-bill-force" value="0">
    <input type="hidden" name="lead_id" id="shra-lead-id" value="<?php echo $lead ? (int) $lead->id : ''; ?>" <?php echo $lead ? 'data-fixed="1"' : ''; ?>>
    <input type="hidden" name="credit_lead" id="shra-credit-lead" value="1">
    <div class="shra-bill">
        <div>
            <div class="shra-card"><div class="shra-card-body">
                <div class="shra-step"><span class="n">1</span><h5>Rider</h5></div>
                <div id="shra-rider-wrap" style="position:relative">
                    <div class="shra-search"><i class="fa fa-search"></i><input type="text" id="shra-rider-q" class="form-control" placeholder="Type name, mobile or rider no. — or scan the membership card" autocomplete="off" autofocus></div>
                    <div id="shra-rider-results" class="shra-results"></div>
                    <div class="help" style="margin-top:8px">Walk-in? <a href="#" id="shra-quick-toggle">Quick add with name &amp; mobile</a> — no form needed.</div>
                    <div id="shra-quick" class="shra-quick" style="display:none">
                        <div class="row">
                            <div class="col-sm-4"><div class="form-group"><label>Name *</label><input type="text" id="shra-quick-name" class="form-control" placeholder="Rider name"></div></div>
                            <div class="col-sm-3"><div class="form-group"><label>Mobile *</label><input type="tel" id="shra-quick-mobile" class="form-control" placeholder="Mobile"></div></div>
                            <div class="col-sm-2"><div class="form-group"><label>DOB</label><input type="date" id="shra-quick-dob" class="form-control" max="<?php echo date('Y-m-d'); ?>"></div></div>
                            <div class="col-sm-3"><div class="form-group"><label>Type</label><select id="shra-quick-type" class="form-control"><option value="guest">Guest rider</option><option value="learner">Learner (member)</option></select></div></div>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center"><button type="button" id="shra-quick-save" class="shra-btn shra-btn-primary shra-btn-sm"><i class="fa fa-user-plus"></i> Add &amp; select</button><span class="help" style="margin:0">DOB picks Children/Adult pricing; leave blank for adults. Learners get a membership number.</span></div>
                    </div>
                </div>
                <div id="shra-picked" class="shra-picked" style="display:none"></div>
                <div id="shra-rider-flags" style="margin-top:10px"></div>
                <div id="shra-lead-banner" style="margin-top:10px;<?php echo $lead ? '' : 'display:none'; ?>">
                    <?php if ($lead) { ?><div class="shra-alert shra-alert-warn"><i class="fa-solid fa-headset"></i> Billing lead <a href="<?php echo shra_lead_url($lead->id); ?>" target="_blank"><b><?php echo html_escape($lead->name); ?></b></a> · <?php echo shra_lead_stage_label($lead->stage); ?> · revenue will be credited to <b><?php echo html_escape($lead->agent_name ?: 'Unassigned'); ?></b>.</div><?php } ?>
                </div>
            </div></div>

            <div class="shra-card shra-mt"><div class="shra-card-body">
                <div class="shra-step" style="justify-content:space-between"><div style="display:flex;align-items:center;gap:10px"><span class="n">2</span><h5>Package</h5></div>
                    <div class="shra-seg">
                        <label><input type="radio" name="audience" value="children" checked><span>Children · under <?php echo (int) get_option('shra_minor_age'); ?></span></label>
                        <label><input type="radio" name="audience" value="adults"><span>Adults</span></label>
                    </div>
                </div>
                <?php if ($offer['active']) { ?><div class="shra-offer" style="margin-bottom:12px"><span class="stamp"><?php echo $offer['percent'] + 0; ?>% OFF</span> <?php echo html_escape($offer['label']); ?> — applied automatically, editable below.</div><?php } ?>
                <div class="shra-pkgs" id="shra-pkgs">
                    <?php foreach ($packages as $p) {
                        $q = $this->shra_model->quote($p); ?>
                        <div class="shra-pkg <?php echo $p->is_featured ? 'featured' : ''; ?>" data-id="<?php echo $p->id; ?>" data-audience="<?php echo $p->audience; ?>">
                            <?php if ($p->is_featured) { ?><span class="pk-tag">Best value</span><?php } ?>
                            <?php if ($p->is_guest) { ?><span class="pk-guest">Guest</span><?php } ?>
                            <div class="pk-name"><?php echo html_escape($p->name); ?></div>
                            <div class="pk-meta"><?php echo (int) $p->sessions; ?> session<?php echo $p->sessions > 1 ? 's' : ''; ?> · <?php echo (int) $p->duration_min; ?> min</div>
                            <div class="pk-price">
                                <span class="pk-now"><?php echo shra_money($q['total']); ?></span>
                                <?php if ($q['discount_percent'] > 0) { ?><span class="pk-was"><?php echo shra_money($p->price); ?></span><?php } ?>
                                <span class="pk-per"><?php echo shra_money($p->per_session); ?> / session</span>
                            </div>
                            <span class="pk-check"><i class="fa fa-check"></i></span>
                        </div>
                    <?php } ?>
                </div>
            </div></div>

            <div class="shra-card shra-mt"><div class="shra-card-body">
                <div class="shra-step"><span class="n">3</span><h5>Payment</h5></div>
                <div class="row">
                    <div class="col-md-3"><div class="form-group"><label>Discount %</label><input type="number" step="0.01" min="0" max="100" name="discount_percent" id="shra-discount" class="form-control" value="<?php echo $offer['active'] ? $offer['percent'] + 0 : 0; ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Amount received</label><input type="number" step="0.01" min="0" name="paid_amount" id="shra-paid" class="form-control" placeholder="Enter amount" required autocomplete="off"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Payment mode</label><select name="payment_mode" class="form-control">
                        <?php foreach ($payment_modes as $m) { ?><option value="<?php echo $m->id; ?>" <?php echo $m->selected_by_default ? 'selected' : ''; ?>><?php echo html_escape($m->name); ?></option><?php } ?>
                    </select></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Reference / UPI txn</label><input type="text" name="reference" class="form-control" placeholder="optional"></div></div>
                </div>
                <div class="row">
                    <div class="col-md-6"><div class="form-group"><label>Note on this bill</label><input type="text" name="notes" class="form-control" placeholder="optional"></div></div>
                    <div class="col-md-6"><div class="form-group"><label>Trainer (if riding now)</label>
                        <div style="display:flex;gap:8px"><select name="trainer_id" class="form-control"><option value="">—</option><?php foreach ($trainers as $t) { ?><option value="<?php echo $t->id; ?>" <?php echo $t->staff_id == get_staff_user_id() ? 'selected' : ''; ?>><?php echo html_escape($t->name); ?></option><?php } ?></select>
                        <label class="shra-pill" style="white-space:nowrap;cursor:pointer;margin:0"><input type="checkbox" name="mark_now" value="1" style="margin:0"> Mark 1st session now</label></div></div></div>
                </div>
            </div></div>
        </div>

        <div>
            <div class="shra-card shra-summary">
                <div class="shra-card-head"><h4>Summary</h4><?php if ($offer['active']) { ?><span class="shra-badge shra-badge-red" style="background:#f8e3e2;color:var(--red)"><?php echo $offer['percent'] + 0; ?>% off</span><?php } ?></div>
                <div class="shra-card-body">
                    <div id="shra-summary"></div>
                    <button type="submit" id="shra-pay" class="shra-btn shra-btn-gold shra-btn-lg shra-btn-block" style="margin-top:16px" disabled><i class="fa-solid fa-check"></i> Collect <span class="amt"></span> &amp; bill</button>
                    <div class="help" style="text-align:center;margin-top:10px">Creates an invoice in Sales, records the payment and opens the rider's sessions wallet. Double submits are ignored.</div>
                </div>
            </div>
        </div>
    </div>
    <?php echo form_close(); ?>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php include __DIR__ . '/partials/collect_modal.php'; ?>
<?php init_tail(); ?>
<script>
$(function () {
    SHRA.currency = { symbol: <?php echo json_encode($currency->symbol); ?>, placement: <?php echo json_encode($currency->placement); ?>, decimals: 2 };
    SHRA.urls = { search: <?php echo json_encode(admin_url('shra/search')); ?>, newRider: <?php echo json_encode(admin_url('shra/rider_form')); ?> };
    SHRA.billing({
        packages: <?php echo json_encode($packages_map); ?>,
        offer: <?php echo json_encode($offer); ?>,
        minorAge: <?php echo (int) get_option('shra_minor_age'); ?>,
        preselect: <?php echo $preselect ? json_encode($preselect) : 'null'; ?>,
        urls: { bill: <?php echo json_encode(admin_url('shra/bill')); ?>, quick: <?php echo json_encode(admin_url('shra/quick_rider')); ?>, collect: <?php echo json_encode(admin_url('shra/collect')); ?>, attendance: <?php echo json_encode(admin_url('shra/attendance')); ?> }
    });
    <?php if (shra_leads_can('own') || shra_can_billing()) { ?>
    window.SHRA_LEADS_CFG = window.SHRA_LEADS_CFG || { urls: {}, templates: [], canAll: <?php echo shra_leads_can('all') ? 'true' : 'false'; ?> };
    if (SHRA.leadMatch) { SHRA.leadMatch(<?php echo json_encode(admin_url('shra/shra_leads/match')); ?>); }
    <?php } ?>
});
</script>
</body>
</html>
