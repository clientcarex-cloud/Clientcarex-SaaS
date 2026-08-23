<?php defined('BASEPATH') or exit('No direct script access allowed');
/** Collect-balance modal. Expects $payment_modes. Opened by SHRA.collect / .shra-collect buttons. */
if (!shra_can_billing()) { return; } ?>
<div class="modal fade" id="shra-collect-modal" tabindex="-1"><div class="modal-dialog modal-sm" style="width:420px;max-width:95vw"><div class="modal-content shra">
    <?php echo form_open(admin_url('shra/collect'), ['id' => 'shra-collect-form']); ?>
    <input type="hidden" name="enrollment_id" id="sc-id">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Collect balance</h4></div>
    <div class="modal-body">
        <div class="shra-picked" style="margin-bottom:12px"><span class="shra-avatar"><i class="fa-solid fa-hand-holding-dollar"></i></span><div><div style="font-weight:700" id="sc-name"></div><div class="shra-muted" style="font-size:12px">Balance due: <b id="sc-due-label" style="color:var(--red)"></b></div></div></div>
        <div class="row">
            <div class="col-xs-6"><div class="form-group"><label>Amount *</label><input type="number" step="0.01" min="0.01" name="amount" id="sc-amount" class="form-control" required></div></div>
            <div class="col-xs-6"><div class="form-group"><label>Mode</label><select name="payment_mode" class="form-control"><?php foreach ($payment_modes as $m) { ?><option value="<?php echo $m->id; ?>" <?php echo $m->selected_by_default ? 'selected' : ''; ?>><?php echo html_escape($m->name); ?></option><?php } ?></select></div></div>
        </div>
        <div class="form-group"><label>Reference / UPI txn</label><input type="text" name="reference" class="form-control" placeholder="optional"></div>
        <div class="form-group" style="margin-bottom:0"><label>Note</label><input type="text" name="note" class="form-control" placeholder="optional"></div>
        <div class="help" style="margin-top:8px">The amount can't exceed the balance. A receipt opens after saving.</div>
    </div>
    <div class="modal-footer"><button type="button" class="shra-btn shra-btn-outline" data-dismiss="modal">Cancel</button><button type="submit" class="shra-btn shra-btn-gold" id="sc-save"><i class="fa fa-check"></i> Record payment</button></div>
    <?php echo form_close(); ?>
</div></div></div>
