<?php defined('BASEPATH') or exit('No direct script access allowed');
$v = function ($k, $d = '') use ($old) { return html_escape(isset($old[$k]) ? $old[$k] : $d); };
$minor_age = (int) get_option('shra_minor_age');
include __DIR__ . '/_public_head.php';
?>
    <div class="card">
        <div class="card-head">
            <h2>Rider membership form</h2>
            <p>For learners joining the academy. Guest riders don't need this — just come to the desk. Sessions are first-come, first-served; no fixed time slots.</p>
        </div>
        <div class="card-body">
            <?php if (count($errors)) { ?>
                <div class="err"><strong>Please check the form:</strong><ul><?php foreach ($errors as $e) { ?><li><?php echo html_escape($e); ?></li><?php } ?></ul></div>
            <?php } ?>
            <?php echo form_open(site_url('join'), ['id' => 'shra-join', 'novalidate' => 'novalidate']); ?>

            <div class="sec">Rider</div>
            <div class="f"><label>Rider full name <span class="req">*</span></label><input type="text" name="full_name" value="<?php echo $v('full_name'); ?>" required autocomplete="name"></div>
            <div class="row">
                <div class="f"><label>Date of birth <span class="req">*</span></label><input type="date" name="dob" id="dob" value="<?php echo $v('dob'); ?>" max="<?php echo date('Y-m-d'); ?>" required><div class="hint" id="age-hint"></div></div>
                <div class="f"><label>Gender <span class="req">*</span></label><div class="chips"><?php foreach (shra_genders() as $k => $l) { ?><label><input type="radio" name="gender" value="<?php echo $k; ?>" <?php echo $v('gender') === $k ? 'checked' : ''; ?>><span><?php echo $l; ?></span></label><?php } ?></div></div>
            </div>
            <div class="row">
                <div class="f"><label>Mobile number <span class="req">*</span></label><input type="tel" name="mobile" value="<?php echo $v('mobile'); ?>" required inputmode="tel" autocomplete="tel"></div>
                <div class="f"><label>Email</label><input type="email" name="email" value="<?php echo $v('email'); ?>" inputmode="email" autocomplete="email"></div>
            </div>
            <div class="row">
                <div class="f"><label>Place of birth</label><input type="text" name="place_of_birth" value="<?php echo $v('place_of_birth'); ?>"></div>
                <div class="f"><label>Rider status</label><select name="marital_status"><option value="">Select</option><?php foreach (shra_marital_statuses() as $k => $l) { ?><option value="<?php echo $k; ?>" <?php echo $v('marital_status') === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php } ?></select></div>
            </div>
            <div class="f"><label>Full address <span class="req">*</span></label><textarea name="address" rows="2" required autocomplete="street-address"><?php echo $v('address'); ?></textarea></div>
            <div class="f"><label>Riding level</label><div class="chips"><?php foreach ($levels as $i => $l) { ?><label><input type="radio" name="riding_level" value="<?php echo html_escape($l); ?>" <?php echo $v('riding_level', $levels[0]) === $l ? 'checked' : ''; ?>><span><?php echo html_escape($l); ?></span></label><?php } ?></div></div>

            <div class="sec">Parent / guardian</div>
            <div class="guardian" id="guardian-box">
                <div class="note" id="guardian-note">Required for riders under <?php echo $minor_age; ?>. Optional otherwise — an emergency contact is always welcome.</div>
                <div class="row">
                    <div class="f"><label>Guardian name <span class="req g-req" style="display:none">*</span></label><input type="text" name="guardian_name" id="guardian_name" value="<?php echo $v('guardian_name'); ?>"></div>
                    <div class="f"><label>Relationship</label><select name="guardian_relationship"><option value="">Select</option><?php foreach (shra_relationships() as $l) { ?><option value="<?php echo $l; ?>" <?php echo $v('guardian_relationship') === $l ? 'selected' : ''; ?>><?php echo $l; ?></option><?php } ?></select></div>
                </div>
            </div>

            <div class="sec">Terms &amp; conditions</div>
            <div class="terms"><?php echo html_escape($terms); ?></div>
            <div class="f" id="accept-by-wrap" style="display:none;margin-top:12px"><label>Guardian's name, to accept on the rider's behalf <span class="req">*</span></label><input type="text" name="terms_accepted_by" id="terms_accepted_by" value="<?php echo $v('terms_accepted_by'); ?>" placeholder="Type the guardian's full name"></div>
            <label class="accept"><input type="checkbox" name="terms_accepted" value="1" <?php echo $v('terms_accepted') ? 'checked' : ''; ?> required><div id="accept-text">I have read and accept the terms &amp; conditions of the academy.</div></label>

            <div style="margin-top:18px"><button type="submit" class="btn"><i class="fa-solid fa-horse-head"></i> Register &amp; get my membership</button></div>
            <div class="hint" style="text-align:center;margin-top:10px">Your details are used only for academy membership and safety.</div>
            <?php echo form_close(); ?>
        </div>
    </div>
<script>
(function () {
    var minorAge = <?php echo $minor_age; ?>;
    var dob = document.getElementById('dob'), hint = document.getElementById('age-hint');
    var gname = document.getElementById('guardian_name'), gnote = document.getElementById('guardian-note');
    var byWrap = document.getElementById('accept-by-wrap'), by = document.getElementById('terms_accepted_by'), acceptText = document.getElementById('accept-text');
    function age() {
        if (!dob.value) return null;
        var d = new Date(dob.value), t = new Date(); var a = t.getFullYear() - d.getFullYear();
        if (t.getMonth() < d.getMonth() || (t.getMonth() === d.getMonth() && t.getDate() < d.getDate())) a--;
        return isNaN(a) ? null : a;
    }
    function update() {
        var a = age(), minor = a !== null && a < minorAge;
        hint.textContent = a === null ? '' : (a + ' years · ' + (minor ? 'Children plans' : 'Adult plans'));
        document.querySelectorAll('.g-req').forEach(function (el) { el.style.display = minor ? '' : 'none'; });
        gname.required = minor; by.required = minor;
        byWrap.style.display = minor ? '' : 'none';
        gnote.textContent = minor ? 'The rider is under ' + minorAge + ' — a parent / guardian must be named and accept the terms on the rider\'s behalf.' : 'Required for riders under ' + minorAge + '. Optional otherwise — an emergency contact is always welcome.';
        acceptText.innerHTML = minor ? 'As the parent / guardian named above, I have read and accept the terms &amp; conditions on behalf of the rider.' : 'I have read and accept the terms &amp; conditions of the academy.';
    }
    dob.addEventListener('change', update); dob.addEventListener('input', update); update();
    gname.addEventListener('input', function () { if (!by.value || by.dataset.auto) { by.value = gname.value; by.dataset.auto = '1'; } });
    by.addEventListener('input', function () { delete by.dataset.auto; });
    document.getElementById('shra-join').addEventListener('submit', function (e) {
        var bad = Array.prototype.filter.call(this.querySelectorAll('[required]'), function (el) {
            if (el.type === 'checkbox') return !el.checked;
            if (el.type === 'radio') return !this.querySelector('[name="' + el.name + '"]:checked');
            return !el.value.trim();
        }, this);
        var gender = this.querySelector('[name=gender]:checked');
        if (bad.length || !gender) {
            e.preventDefault();
            var first = bad[0] || this.querySelector('[name=gender]').closest('.f');
            (first.closest ? first.closest('.f') || first : first).scrollIntoView({ behavior: 'smooth', block: 'center' });
            alert('Please fill all the required fields' + (!gender ? ' (gender)' : '') + '.');
        }
    });
})();
</script>
<?php include __DIR__ . '/_public_foot.php'; ?>
