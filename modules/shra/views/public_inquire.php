<?php defined('BASEPATH') or exit('No direct script access allowed'); include __DIR__ . '/_public_head.php';
$v = function ($k, $d = '') use ($old) { return html_escape(isset($old[$k]) ? $old[$k] : $d); };
?>
    <div class="card">
        <div class="card-head">
            <h2>Interested in horse riding?</h2>
            <p>Leave your number and our team will call you back, share the packages and book a free visit — usually on a Saturday or Sunday morning.</p>
        </div>
        <div class="card-body">
            <?php if (count($errors)) { ?><div style="background:#fbeeee;border:1px solid #e8b9b6;color:var(--red);border-radius:12px;padding:10px 14px;margin-bottom:14px;font-size:13.5px"><?php foreach ($errors as $e) { echo '<div>' . html_escape($e) . '</div>'; } ?></div><?php } ?>
            <?php if ($offer['active']) { ?><div style="display:flex;align-items:center;gap:10px;background:#fbeeee;border:1px dashed #e8b9b6;color:var(--red);border-radius:12px;padding:9px 12px;font-size:12.5px;font-weight:600;margin-bottom:14px"><span style="background:var(--red);color:#fff;border-radius:6px;padding:2px 8px"><?php echo $offer['percent'] + 0; ?>% OFF</span> <?php echo html_escape($offer['label']); ?></div><?php } ?>
            <form method="post" autocomplete="on">
                <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
                <input type="hidden" name="ts" value="<?php echo $ts; ?>"><input type="hidden" name="sig" value="<?php echo $sig; ?>"><input type="hidden" name="c" value="<?php echo html_escape($this->input->get('c')); ?>">
                <div style="position:absolute;left:-5000px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                <div class="f"><label>Your name <span class="req">*</span></label><input type="text" name="name" value="<?php echo $v('name'); ?>" required autocomplete="name"></div>
                <div class="f"><label>Mobile number <span class="req">*</span></label><input type="tel" name="phone" value="<?php echo $v('phone'); ?>" required inputmode="tel" autocomplete="tel"></div>
                <div class="f"><label>Who will ride?</label><div class="chips">
                    <label><input type="radio" name="rider_for" value="self" <?php echo $v('rider_for', 'self') === 'self' ? 'checked' : ''; ?>><span>Myself</span></label>
                    <label><input type="radio" name="rider_for" value="child" <?php echo $v('rider_for') === 'child' ? 'checked' : ''; ?>><span>My child</span></label>
                    <label><input type="radio" name="rider_for" value="both" <?php echo $v('rider_for') === 'both' ? 'checked' : ''; ?>><span>Both</span></label>
                </div></div>
                <div class="row">
                    <div class="f"><label>Rider's age</label><input type="number" name="rider_age" value="<?php echo $v('rider_age'); ?>" min="2" max="90" inputmode="numeric"></div>
                    <div class="f"><label>City / area</label><input type="text" name="city" value="<?php echo $v('city'); ?>"></div>
                </div>
                <div class="f"><label>Interested in</label><select name="package_id"><option value="">Not sure yet — guide me</option><?php foreach ($packages as $p) { ?><option value="<?php echo $p->id; ?>" <?php echo (string) $v('package_id') === (string) $p->id ? 'selected' : ''; ?>><?php echo ucfirst($p->audience) . ' · ' . html_escape($p->name) . ' · ' . (int) $p->sessions . ' session' . ($p->sessions > 1 ? 's' : ''); ?></option><?php } ?></select></div>
                <div class="f"><label>Anything we should know?</label><textarea name="message" rows="2"><?php echo $v('message'); ?></textarea></div>
                <button class="btn" type="submit"><i class="fa-solid fa-phone"></i> Request a call back</button>
                <div class="hint" style="text-align:center;margin-top:10px">Ready to join today? <a href="<?php echo site_url('join'); ?>" style="color:var(--brown);font-weight:600">Register as a rider →</a></div>
            </form>
        </div>
    </div>
<?php include __DIR__ . '/_public_foot.php'; ?>
