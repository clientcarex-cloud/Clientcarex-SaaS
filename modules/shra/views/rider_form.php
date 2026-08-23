<?php defined('BASEPATH') or exit('No direct script access allowed');
$r = $rider;
$v = function ($k, $d = '') use ($r) { return html_escape($r && isset($r->$k) && $r->$k !== null ? $r->$k : $d); };
?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'riders'; include __DIR__ . '/_nav.php'; ?>
    <h4 class="shra-title"><?php echo $r ? 'Edit rider <span class="thin">' . html_escape($r->rider_no) . '</span>' : 'New rider'; ?></h4>

    <?php echo form_open(admin_url('shra/rider_form/' . ($r ? $r->id : '')), ['id' => 'shra-rider-form']); ?>
    <div class="row">
        <div class="col-md-8">
            <div class="shra-card"><div class="shra-card-body">
                <div class="row">
                    <div class="col-md-4"><div class="form-group"><label>Rider type</label>
                        <div class="shra-seg" style="display:flex">
                            <label><input type="radio" name="rider_type" value="learner" <?php echo !$r || $r->rider_type === 'learner' ? 'checked' : ''; ?>><span>Learner (member)</span></label>
                            <label><input type="radio" name="rider_type" value="guest" <?php echo $r && $r->rider_type === 'guest' ? 'checked' : ''; ?>><span>Guest rider</span></label>
                        </div><div class="help">Guest riders only need name &amp; mobile — no membership form or certificate.</div></div></div>
                    <div class="col-md-8"><div class="form-group"><label>Rider full name *</label><input type="text" name="full_name" class="form-control" required value="<?php echo $v('full_name'); ?>"></div></div>
                </div>
                <div class="row">
                    <div class="col-md-4"><div class="form-group"><label>Mobile number *</label><input type="tel" name="mobile" class="form-control" required value="<?php echo $v('mobile'); ?>"></div></div>
                    <div class="col-md-4"><div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?php echo $v('email'); ?>"></div></div>
                    <div class="col-md-4"><div class="form-group"><label>Riding level</label><select name="riding_level" class="form-control"><?php foreach ($levels as $l) { ?><option value="<?php echo html_escape($l); ?>" <?php echo $v('riding_level', $levels[0]) === $l ? 'selected' : ''; ?>><?php echo html_escape($l); ?></option><?php } ?></select></div></div>
                </div>
                <div class="row">
                    <div class="col-md-3"><div class="form-group"><label>Gender</label><select name="gender" class="form-control"><option value="">—</option><?php foreach (shra_genders() as $k => $l) { ?><option value="<?php echo $k; ?>" <?php echo $v('gender') === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php } ?></select></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Date of birth</label><input type="date" name="dob" class="form-control" value="<?php echo $v('dob'); ?>" max="<?php echo date('Y-m-d'); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Place of birth</label><input type="text" name="place_of_birth" class="form-control" value="<?php echo $v('place_of_birth'); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Status</label><select name="marital_status" class="form-control"><option value="">—</option><?php foreach (shra_marital_statuses() as $k => $l) { ?><option value="<?php echo $k; ?>" <?php echo $v('marital_status') === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php } ?></select></div></div>
                </div>
                <div class="form-group"><label>Full address</label><textarea name="address" class="form-control" rows="2"><?php echo $v('address'); ?></textarea></div>
                <div class="row">
                    <div class="col-md-8"><div class="form-group"><label>Guardian name <span class="shra-muted">(required for riders under <?php echo (int) get_option('shra_minor_age'); ?>)</span></label><input type="text" name="guardian_name" class="form-control" value="<?php echo $v('guardian_name'); ?>"></div></div>
                    <div class="col-md-4"><div class="form-group"><label>Relationship</label><select name="guardian_relationship" class="form-control"><option value="">—</option><?php foreach (shra_relationships() as $l) { ?><option value="<?php echo $l; ?>" <?php echo $v('guardian_relationship') === $l ? 'selected' : ''; ?>><?php echo $l; ?></option><?php } ?></select></div></div>
                </div>
                <div class="form-group"><label>Notes (internal)</label><textarea name="notes" class="form-control" rows="2"><?php echo $v('notes'); ?></textarea></div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="shra-card"><div class="shra-card-body">
                <div class="form-group"><label>Terms &amp; conditions</label>
                    <div style="max-height:180px;overflow:auto;font-size:12px;color:var(--ink-2);background:var(--cream);border:1px solid var(--line);border-radius:10px;padding:10px 12px;white-space:pre-line"><?php echo html_escape($terms); ?></div>
                </div>
                <div class="checkbox checkbox-primary" style="margin-top:0">
                    <input type="checkbox" id="terms_accepted" name="terms_accepted" value="1" <?php echo $r && $r->terms_accepted ? 'checked' : ''; ?>>
                    <label for="terms_accepted">Terms accepted by rider / guardian</label>
                </div>
                <?php if ($r && $r->terms_accepted_at) { ?><div class="help">Accepted by <?php echo html_escape($r->terms_accepted_by); ?> on <?php echo _dt($r->terms_accepted_at); ?></div><?php } ?>
                <div class="form-group" style="margin-top:10px"><label>Accepted by (name)</label><input type="text" name="terms_accepted_by" class="form-control" value="<?php echo $v('terms_accepted_by'); ?>" placeholder="Guardian name when rider is a minor"></div>
                <?php if ($r) { ?>
                <div class="form-group"><label>Rider status</label><select name="status" class="form-control"><option value="active" <?php echo $r->status === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $r->status === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select></div>
                <?php } ?>
                <hr class="shra-hr">
                <button type="submit" class="shra-btn shra-btn-primary shra-btn-block"><i class="fa fa-check"></i> Save rider</button>
                <?php if (!$r && shra_can_billing()) { ?><button type="submit" name="then" value="bill" class="shra-btn shra-btn-gold shra-btn-block" style="margin-top:8px"><i class="fa-solid fa-cash-register"></i> Save &amp; bill a package</button><?php } ?>
                <a href="<?php echo $r ? admin_url('shra/rider/' . $r->id) : admin_url('shra/riders'); ?>" class="shra-btn shra-btn-outline shra-btn-block" style="margin-top:8px">Cancel</a>
            </div></div>
        </div>
    </div>
    <?php echo form_close(); ?>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
