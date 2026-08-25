<?php defined('BASEPATH') or exit('No direct script access allowed');
$v = function ($k, $d = '') use ($old) { return html_escape(isset($old[$k]) ? $old[$k] : $d); };
include __DIR__ . '/_public_head.php';
?>
<style>
.steps{display:flex;justify-content:center;gap:0;margin:0 0 18px;counter-reset:s}
.steps span{flex:1;max-width:130px;text-align:center;font-size:10.5px;letter-spacing:.8px;text-transform:uppercase;font-weight:700;color:var(--muted);position:relative;padding-top:26px}
.steps span:before{content:counter(s);counter-increment:s;position:absolute;top:0;left:50%;transform:translateX(-50%);width:22px;height:22px;border-radius:50%;background:#fff;border:1.5px solid var(--line);font-family:'Cormorant Garamond',Georgia,serif;font-size:13px;line-height:19px;color:var(--muted)}
.steps span:after{content:'';position:absolute;top:11px;left:50%;width:100%;border-top:1.5px dashed var(--line);z-index:-1}
.steps span:last-child:after{display:none}
.steps span.on{color:var(--ink)}
.steps span.on:before{background:var(--ink);border-color:var(--ink);color:var(--cream)}
.steps span.done:before{background:var(--gold);border-color:var(--gold);color:#fff;content:'✓'}
.pane{display:none}.pane.on{display:block}
.choice{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:520px){.choice{grid-template-columns:1fr}}
.choice label{display:block;border:1.5px solid var(--line);border-radius:16px;padding:18px 16px;cursor:pointer;background:#fff;transition:.15s;position:relative}
.choice label:hover{border-color:var(--gold-2)}
.choice input{display:none}
.choice label:has(input:checked){border-color:var(--ink);box-shadow:0 0 0 3px rgba(28,26,23,.08);background:var(--cream-2)}
.choice .ic{width:46px;height:46px;border-radius:50%;background:var(--ink);color:var(--cream);display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:10px}
.choice b{display:block;font-family:'Cormorant Garamond',Georgia,serif;font-size:21px;font-weight:700}
.choice small{display:block;font-size:12.5px;color:var(--muted);margin-top:3px;line-height:1.45}
.seg{display:flex;background:var(--cream);border:1px solid var(--line);border-radius:12px;padding:3px;margin-bottom:14px}
.seg label{flex:1;text-align:center;padding:10px;border-radius:9px;font-size:13.5px;font-weight:600;cursor:pointer;color:var(--ink-2)}
.seg input{display:none}
.seg label:has(input:checked){background:var(--ink);color:var(--cream)}
.plans{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:520px){.plans{grid-template-columns:1fr}}
.plan{display:none;position:relative;border:1.5px solid var(--line);border-radius:14px;padding:14px 14px 12px;background:#fff;cursor:pointer;transition:.15s}
.plan.show{display:block}
.plan:hover{border-color:var(--gold-2)}
.plan input{display:none}
.plan:has(input:checked){border-color:var(--ink);box-shadow:0 0 0 3px rgba(28,26,23,.08);background:var(--cream-2)}
.plan .nm{font-weight:700;font-size:15px}
.plan .mt{font-size:12px;color:var(--muted);margin-top:2px}
.plan .pr{margin-top:10px;display:flex;align-items:baseline;gap:7px;flex-wrap:wrap}
.plan .now{font-family:'Inter',system-ui,sans-serif;font-size:20px;font-weight:700;color:var(--red);letter-spacing:-.2px}
.plan .was{font-size:12px;color:var(--muted);text-decoration:line-through}
.plan .per{width:100%;font-size:11px;color:var(--muted)}
.plan .tag{position:absolute;top:-9px;right:12px;background:var(--green);color:#fff;font-size:9.5px;font-weight:700;letter-spacing:.6px;padding:3px 8px;border-radius:999px;text-transform:uppercase}
.offer{display:flex;align-items:center;gap:10px;background:#fbeeee;border:1px dashed #e8b9b6;color:var(--red);border-radius:12px;padding:10px 12px;font-size:13px;font-weight:600;margin-bottom:12px}
.offer .st{background:var(--red);color:#fff;border-radius:6px;padding:2px 8px;font-size:11px;transform:rotate(-4deg)}
.nav{display:flex;gap:10px;margin-top:20px}
.nav .btn{width:auto;flex:1}
.nav .btn.ghost{flex:0 0 auto;padding-left:18px;padding-right:18px}
.pick{background:var(--cream-2);border:1px solid var(--line);border-radius:12px;padding:10px 14px;font-size:13px;margin-bottom:14px;display:flex;justify-content:space-between;gap:10px;align-items:center}
.pick b{font-family:'Cormorant Garamond',Georgia,serif;font-size:17px}
.pick a{font-size:12px;color:var(--brown)}
.learner-only{display:none}.is-learner .learner-only{display:block}
.row.learner-only{display:none}.is-learner .row.learner-only{display:grid}
.safety-btn{display:inline-flex;align-items:center;gap:8px;margin-top:12px;padding:8px 14px;border:1.5px solid var(--gold-2);border-radius:999px;background:#fff;color:var(--brown);font:inherit;font-size:12.5px;font-weight:700;cursor:pointer;transition:.15s;text-decoration:none}
.safety-btn:hover{background:var(--cream-2);border-color:var(--gold)}
.safety-btn i{color:var(--gold)}
.safety-btn .arr{width:22px;height:22px;border-radius:50%;background:var(--ink);color:var(--cream);display:inline-flex;align-items:center;justify-content:center;margin-left:2px;transition:.2s}
.safety-btn .arr i{color:var(--cream);font-size:10px}
.safety-btn:hover .arr{transform:translateX(4px)}
.sf-intro{display:flex;gap:12px;align-items:center;background:var(--cream-2);border:1px dashed var(--gold-2);border-radius:14px;padding:12px 14px;margin-top:14px}
.sf-intro .big{font-size:40px;line-height:1}
.sf-intro p{font-size:13.5px;line-height:1.5;color:var(--ink-2)}
.sf-intro b{color:var(--ink)}
.sf-title{display:flex;align-items:center;gap:10px;margin:22px 0 10px}
.sf-title .em{font-size:30px;line-height:1}
.sf-title h4{font-family:'Cormorant Garamond',Georgia,serif;font-size:21px;font-weight:700;color:var(--ink)}
.sf-title small{display:block;font-size:12px;color:var(--muted);font-weight:500;font-family:'Inter',system-ui,sans-serif}
.sf-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:480px){.sf-grid{grid-template-columns:1fr}}
.sf-card{border:1.5px solid var(--line);border-radius:14px;padding:12px;background:#fff;display:flex;gap:10px;align-items:flex-start}
.sf-card .em{font-size:30px;line-height:1;flex-shrink:0;width:38px;text-align:center}
.sf-card b{display:block;font-size:13.5px;color:var(--ink);margin-bottom:2px}
.sf-card span{font-size:12.5px;line-height:1.45;color:var(--ink-2);display:block}
.sf-card.do{border-color:#cfe0c4;background:#f3f8ef}
.sf-card.dont{border-color:#eac7c4;background:#fdf2f1}
.sf-card.do b:before{content:'✅ ';}
.sf-card.dont b:before{content:'🚫 ';}
.sf-steps{display:flex;gap:8px;overflow-x:auto;padding-bottom:4px}
.sf-step{flex:1;min-width:120px;text-align:center;border:1.5px solid var(--line);border-radius:14px;padding:12px 8px;background:#fff;position:relative}
.sf-step .n{position:absolute;top:-9px;left:10px;background:var(--gold);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px}
.sf-step .em{font-size:34px;line-height:1;display:block;margin:6px 0 6px}
.sf-step b{font-size:12.5px;display:block;color:var(--ink)}
.sf-step span{font-size:11.5px;color:var(--muted);display:block;margin-top:2px;line-height:1.4}
.sf-promise{margin-top:18px;border:2px solid var(--gold);border-radius:16px;padding:14px;background:linear-gradient(180deg,#fff,var(--cream-2));text-align:center}
.sf-promise .em{font-size:34px}
.sf-promise h4{font-family:'Cormorant Garamond',Georgia,serif;font-size:22px;font-weight:700;margin-top:4px}
.sf-promise p{font-size:13px;color:var(--ink-2);line-height:1.5;margin-top:4px}
.sf-cheat{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-top:10px}
.sf-cheat span{background:var(--ink);color:var(--cream);border-radius:999px;padding:6px 12px;font-size:12px;font-weight:600}
.safety-link{font-size:12.5px;color:var(--brown);font-weight:600;text-decoration:underline;cursor:pointer;background:none;border:0;padding:0;font-family:inherit}
.sf-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:flex-end;justify-content:center;background:rgba(28,26,23,.55);padding:0}
.sf-modal.open{display:flex}
@media(min-width:600px){.sf-modal{align-items:center;padding:20px}}
.sf-box{background:#fff;width:100%;max-width:640px;max-height:92vh;border-radius:20px 20px 0 0;display:flex;flex-direction:column;box-shadow:0 30px 80px rgba(0,0,0,.35);overflow:hidden}
@media(min-width:600px){.sf-box{border-radius:20px;max-height:86vh}}
.sf-head{padding:18px 22px 14px;border-bottom:1px solid var(--line);background:linear-gradient(180deg,#fff,var(--cream-2));display:flex;align-items:flex-start;gap:12px}
.sf-head h3{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:24px;line-height:1.1;flex:1}
.sf-head p{color:var(--muted);font-size:12.5px;margin-top:3px}
.sf-close{width:34px;height:34px;border-radius:50%;border:1.5px solid var(--line);background:#fff;color:var(--ink);font-size:16px;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.sf-close:hover{border-color:var(--gold)}
.sf-body{padding:6px 22px 18px;overflow:auto;-webkit-overflow-scrolling:touch;font-size:13.5px;line-height:1.6;color:var(--ink-2)}
.sf-body .sec{margin-top:18px}
.sf-body ul{margin:0;padding-left:0;list-style:none}
.sf-body li{position:relative;padding-left:22px;margin:6px 0}
.sf-body li:before{content:'';position:absolute;left:4px;top:9px;width:7px;height:7px;border-radius:50%;background:var(--gold)}
.sf-body li b{color:var(--ink)}
.sf-alert{display:flex;gap:10px;align-items:flex-start;background:#fbeeee;border:1px solid #e8b9b6;color:var(--red);border-radius:12px;padding:10px 12px;font-size:13px;font-weight:600;margin-top:14px}
.sf-alert i{margin-top:2px}
.sf-foot{padding:12px 22px 16px;border-top:1px solid var(--line);background:var(--cream-2)}
.sf-foot .btn{padding:13px 20px;font-size:15px}
.chips.one-line{flex-wrap:nowrap}
.chips.one-line label{flex:1;min-width:0;padding:11px 6px;white-space:nowrap}
@media(max-width:360px){.chips.one-line label{font-size:12.5px;padding:11px 4px}}
#guardian-wrap{display:none}
#guardian-wrap.show{display:block}
</style>
    <div class="card" id="wiz">
        <div class="card-head" style="padding-bottom:14px">
            <div class="steps"><span class="on" data-s="1">Choose</span><span data-s="2">Plan</span><span data-s="3">Details</span><span data-s="4">Done</span></div>
            <h2 id="pane-title">How would you like to ride?</h2>
            <p id="pane-sub">Two batches a day — morning and evening. Pick a start date and a timing; seats go first come, first served.</p>
            <button type="button" class="safety-btn" data-safety><i class="fa-solid fa-shield-heart"></i> Horse riding safety guidelines <span class="arr"><i class="fa fa-arrow-right"></i></span></button>
        </div>
        <div class="card-body">
            <?php if (count($errors)) { ?>
                <div class="err"><strong>Please check the form:</strong><ul><?php foreach ($errors as $e) { ?><li><?php echo html_escape($e); ?></li><?php } ?></ul></div>
            <?php } ?>
            <?php echo form_open(site_url('join'), ['id' => 'shra-join', 'novalidate' => 'novalidate']); ?>
            <input type="hidden" name="package_id" id="package_id" value="<?php echo $v('package_id'); ?>">

            <!-- ───── Step 1: choose ───── -->
            <div class="pane on" data-pane="1">
                <div class="choice">
                    <label><input type="radio" name="rider_type" value="guest" <?php echo $v('rider_type') === 'guest' ? 'checked' : ''; ?>><div class="ic"><i class="fa-solid fa-horse"></i></div><b>Guest ride</b><small>A single 20-minute ride with a trainer. No membership — just name &amp; mobile.</small></label>
                    <label><input type="radio" name="rider_type" value="learner" <?php echo $v('rider_type', 'learner') === 'learner' ? 'checked' : ''; ?>><div class="ic"><i class="fa-solid fa-graduation-cap"></i></div><b>Become a rider</b><small>Join the academy as a learner. Session packages, membership card &amp; course certificate.</small></label>
                </div>
                <div class="nav"><button type="button" class="btn dark" data-next="2">Continue <i class="fa fa-arrow-right"></i></button></div>
            </div>

            <!-- ───── Step 2: plan ───── -->
            <div class="pane" data-pane="2">
                <?php $aud_default = $v('rider_type', 'learner') === 'guest' ? 'adults' : 'children'; ?>
                <div class="seg">
                    <label><input type="radio" name="audience" value="children" <?php echo $v('audience', $aud_default) === 'children' ? 'checked' : ''; ?>>Children · under <?php echo $minor_age; ?></label>
                    <label><input type="radio" name="audience" value="adults" <?php echo $v('audience', $aud_default) === 'adults' ? 'checked' : ''; ?>>Adults</label>
                </div>
                <?php if ($offer['active']) { ?><div class="offer"><span class="st"><?php echo $offer['percent'] + 0; ?>% OFF</span> <?php echo html_escape($offer['label'] ?: 'Offer'); ?> — prices below already include it.</div><?php } ?>
                <div class="plans" id="plans">
                    <?php foreach ($plans as $p) { ?>
                        <label class="plan" data-aud="<?php echo $p['audience']; ?>" data-guest="<?php echo $p['is_guest']; ?>" data-name="<?php echo html_escape($p['name']); ?>" data-total="<?php echo html_escape($p['total']); ?>">
                            <input type="radio" name="plan_pick" value="<?php echo $p['id']; ?>" <?php echo (string) $v('package_id') === (string) $p['id'] ? 'checked' : ''; ?>>
                            <?php if ($p['is_featured']) { ?><span class="tag">Best value</span><?php } ?>
                            <div class="nm"><?php echo html_escape($p['name']); ?></div>
                            <div class="mt"><?php echo $p['sessions']; ?> session<?php echo $p['sessions'] > 1 ? 's' : ''; ?> · <?php echo $p['duration_min']; ?> min<?php echo $p['is_guest'] ? ' · with trainer' : ''; ?></div>
                            <div class="pr"><span class="now"><?php echo $p['total']; ?></span><?php if ($p['discount'] > 0) { ?><span class="was"><?php echo $p['price']; ?></span><?php } ?><span class="per"><?php echo $p['per_session']; ?> per session</span></div>
                        </label>
                    <?php } ?>
                </div>
                <div class="learner-only">
                <div class="sec">When would you like to start?</div>
                <div class="row">
                    <div class="f"><label>Start date</label><input type="date" name="preferred_start_date" value="<?php echo $v('preferred_start_date'); ?>" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+6 months')); ?>"><div class="hint">Leave blank and we will fix it with you at the desk.</div></div>
                    <div class="f"><label>Class timing</label><div class="chips">
                        <?php foreach (shra_batches() as $bk => $b) { ?><label data-end="<?php echo html_escape($b['end']); ?>"><input type="radio" name="preferred_batch" value="<?php echo $bk; ?>" <?php echo $v('preferred_batch') === $bk ? 'checked' : ''; ?>><span><?php echo html_escape($b['label']); ?><small><?php echo html_escape($b['time']); ?></small></span></label><?php } ?>
                    </div></div>
                </div>
                <div class="fcfs"><i class="fa-solid fa-user-clock"></i> <span><?php echo html_escape(shra_fcfs_note()); ?></span></div>
                </div>
                <div class="hint" style="margin-top:10px">You'll pay at the reception desk — nothing is charged online. You can change the plan at the desk too.</div>
                <div class="nav"><button type="button" class="btn ghost" data-prev="1"><i class="fa fa-arrow-left"></i></button><button type="button" class="btn dark" data-next="3" id="to-details">Continue <i class="fa fa-arrow-right"></i></button></div>
            </div>

            <!-- ───── Step 3: details ───── -->
            <div class="pane" data-pane="3">
                <div class="pick"><span>Selected plan<br><b id="pick-name">—</b></span><span style="text-align:right"><b id="pick-total"></b><br><a href="#" data-prev="2">change</a></span></div>

                <div class="sec">Rider</div>
                <div class="f"><label>Rider full name <span class="req">*</span></label><input type="text" name="full_name" value="<?php echo $v('full_name'); ?>" required autocomplete="name"></div>
                <div class="row">
                    <div class="f"><label>Date of birth <span class="req">*</span></label><input type="date" name="dob" id="dob" value="<?php echo $v('dob'); ?>" max="<?php echo date('Y-m-d', strtotime('-5 years')); ?>" required><div class="hint" id="age-hint"></div></div>
                    <div class="f"><label>Gender <span class="req">*</span></label><div class="chips one-line"><?php foreach (shra_genders() as $k => $l) { ?><label><input type="radio" name="gender" value="<?php echo $k; ?>" <?php echo $v('gender') === $k ? 'checked' : ''; ?>><span><?php echo $l; ?></span></label><?php } ?></div></div>
                </div>
                <div class="row">
                    <div class="f"><label>Mobile number <span class="req">*</span></label><input type="tel" name="mobile" value="<?php echo $v('mobile'); ?>" required inputmode="tel" autocomplete="tel"></div>
                    <div class="f"><label>Email</label><input type="email" name="email" value="<?php echo $v('email'); ?>" inputmode="email" autocomplete="email"></div>
                </div>
                <div class="row learner-only">
                    <div class="f"><label>Place of birth</label><input type="text" name="place_of_birth" value="<?php echo $v('place_of_birth'); ?>"></div>
                    <div class="f"><label>Rider status</label><select name="marital_status"><option value="">Select</option><?php foreach (shra_marital_statuses() as $k => $l) { ?><option value="<?php echo $k; ?>" <?php echo $v('marital_status') === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php } ?></select></div>
                </div>
                <div class="f learner-only"><label>Full address <span class="req">*</span></label><textarea name="address" rows="2" autocomplete="street-address"><?php echo $v('address'); ?></textarea></div>
                <div class="f learner-only"><label>Riding level</label><div class="chips"><?php foreach ($levels as $i => $l) { ?><label><input type="radio" name="riding_level" value="<?php echo html_escape($l); ?>" <?php echo $v('riding_level', $levels[0]) === $l ? 'checked' : ''; ?>><span><?php echo html_escape($l); ?></span></label><?php } ?></div></div>

                <div id="guardian-wrap">
                <div class="sec">Parent / guardian</div>
                <div class="guardian" id="guardian-box">
                    <div class="note" id="guardian-note"></div>
                    <div class="row">
                        <div class="f"><label>Guardian name <span class="req g-req">*</span></label><input type="text" name="guardian_name" id="guardian_name" value="<?php echo $v('guardian_name'); ?>"></div>
                        <div class="f"><label>Relationship</label><select name="guardian_relationship"><option value="">Select</option><?php foreach (shra_relationships() as $l) { ?><option value="<?php echo $l; ?>" <?php echo $v('guardian_relationship') === $l ? 'selected' : ''; ?>><?php echo $l; ?></option><?php } ?></select></div>
                    </div>
                </div>
                </div>

                <div class="sec">Terms &amp; conditions</div>
                <div class="hint" style="margin:-4px 0 8px">Please also read the <button type="button" class="safety-link" data-safety>riding safety guidelines</button> before your first session.</div>
                <div class="terms"><?php echo html_escape($terms); ?></div>
                <div class="f" id="accept-by-wrap" style="display:none;margin-top:12px"><label>Guardian's name, to accept on the rider's behalf <span class="req">*</span></label><input type="text" name="terms_accepted_by" id="terms_accepted_by" value="<?php echo $v('terms_accepted_by'); ?>" placeholder="Type the guardian's full name"></div>
                <label class="accept"><input type="checkbox" name="terms_accepted" value="1" <?php echo $v('terms_accepted') ? 'checked' : ''; ?> required><div id="accept-text">I have read and accept the terms &amp; conditions of the academy.</div></label>

                <div class="nav"><button type="button" class="btn ghost" data-prev="2"><i class="fa fa-arrow-left"></i></button><button type="submit" class="btn" id="submit-btn"><i class="fa-solid fa-horse-head"></i> <span>Register</span></button></div>
                <div class="hint" style="text-align:center;margin-top:10px">Your details are used only for academy membership and safety.</div>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
<!-- ───── Safety guidelines modal ───── -->
<div class="sf-modal" id="sf-modal" role="dialog" aria-modal="true" aria-labelledby="sf-title">
    <div class="sf-box">
        <div class="sf-head">
            <div style="flex:1">
                <h3 id="sf-title"><i class="fa-solid fa-shield-heart" style="color:var(--gold)"></i> Horse riding safety guidelines</h3>
                <p>Please read carefully — these rules apply to every rider, guest and learner alike.</p>
            </div>
            <button type="button" class="sf-close" data-safety-close aria-label="Close"><i class="fa fa-times"></i></button>
        </div>
        <div class="sf-body">
            <div class="sf-intro">
                <div class="big">🐴</div>
                <p><b>Hi, future rider!</b> Horses are big, gentle friends — but they get scared easily. These simple rules keep <b>you</b> and <b>your horse</b> safe and happy. Read them with your family!</p>
            </div>

            <div class="sf-title"><span class="em">🎒</span><div><h4>1. Get ready like a pro</h4><small>What to wear before you come</small></div></div>
            <div class="sf-grid">
                <div class="sf-card do"><span class="em">⛑️</span><div><b>Helmet — always!</b><span>A helmet is your superhero shield. Strap it under your chin every single time. The academy gives you one. <b>No helmet = no ride.</b></span></div></div>
                <div class="sf-card do"><span class="em">👖</span><div><b>Long pants</b><span>Jeans, jodhpurs or leggings. They stop the saddle from rubbing your legs.</span></div></div>
                <div class="sf-card do"><span class="em">👢</span><div><b>Closed shoes with a little heel</b><span>Boots or sturdy shoes so your foot never slips through the stirrup.</span></div></div>
                <div class="sf-card dont"><span class="em">🩴</span><div><b>No sandals, slippers or crocs</b><span>They can get stuck in the stirrup or fall off.</span></div></div>
                <div class="sf-card dont"><span class="em">🧣</span><div><b>No loose scarves, jewellery or strings</b><span>Flappy things can scare the horse or get caught. Tie long hair back too!</span></div></div>
                <div class="sf-card dont"><span class="em">📱</span><div><b>No phone in your pocket</b><span>Leave phones, keys and toys with your family at the desk.</span></div></div>
            </div>

            <div class="sf-title"><span class="em">🍎</span><div><h4>2. Feel good, ride good</h4><small>Before you arrive</small></div></div>
            <div class="sf-grid">
                <div class="sf-card do"><span class="em">😴</span><div><b>Sleep well &amp; eat a light snack</b><span>Not too hungry, not too full — just right.</span></div></div>
                <div class="sf-card do"><span class="em">💧</span><div><b>Drink water</b><span>Especially on hot days. Sunscreen helps too!</span></div></div>
                <div class="sf-card do"><span class="em">🩺</span><div><b>Tell us if you are unwell</b><span>Fever, tummy ache, dizziness, allergies or medicine — tell the trainer. Grown-ups: no riding when pregnant or after surgery without a doctor's OK.</span></div></div>
                <div class="sf-card do"><span class="em">👨‍👩‍👧</span><div><b>Under 18? Bring a grown-up</b><span>Your parent or guardian stays with you for the whole session.</span></div></div>
            </div>

            <div class="sf-title"><span class="em">🐎</span><div><h4>3. Saying hello to a horse</h4><small>How to be around horses</small></div></div>
            <div class="sf-steps">
                <div class="sf-step"><span class="n">Step 1</span><span class="em">👀</span><b>Let the horse see you</b><span>Walk up from the front-side, near the shoulder.</span></div>
                <div class="sf-step"><span class="n">Step 2</span><span class="em">🗣️</span><b>Say hello softly</b><span>"Hi horsey!" — calm and gentle, so it's not surprised.</span></div>
                <div class="sf-step"><span class="n">Step 3</span><span class="em">✋</span><b>Ask before touching</b><span>Wait for the trainer to say yes, then stroke the neck slowly.</span></div>
            </div>
            <div class="sf-grid" style="margin-top:10px">
                <div class="sf-card dont"><span class="em">🦵</span><div><b>Never go behind a horse</b><span>A horse can't see behind itself and might kick. Stay away from the back legs.</span></div></div>
                <div class="sf-card dont"><span class="em">🏃</span><div><b>No running or shouting</b><span>Loud noises and fast moves scare horses. Walk slowly, talk quietly, no flash photos.</span></div></div>
                <div class="sf-card dont"><span class="em">🥕</span><div><b>No feeding without permission</b><span>If the trainer says yes, hold the treat on a flat open hand like a plate.</span></div></div>
                <div class="sf-card dont"><span class="em">🚪</span><div><b>No going into stables alone</b><span>Only enter stables, paddocks or the arena with a trainer. Close gates behind you.</span></div></div>
            </div>

            <div class="sf-title"><span class="em">🪜</span><div><h4>4. Getting on &amp; off</h4><small>Mounting and dismounting</small></div></div>
            <div class="sf-grid">
                <div class="sf-card do"><span class="em">🧑‍🏫</span><div><b>Only with your trainer</b><span>Climb on and get off only where and when the trainer says — after the saddle strap is checked.</span></div></div>
                <div class="sf-card do"><span class="em">🦶</span><div><b>Stirrups the right length</b><span>The trainer will adjust them for you before you move.</span></div></div>
                <div class="sf-card dont"><span class="em">🏇</span><div><b>Never jump off a moving horse</b><span>Wait until the horse stops completely.</span></div></div>
            </div>

            <div class="sf-title"><span class="em">🎠</span><div><h4>5. While you are riding</h4><small>In the arena</small></div></div>
            <div class="sf-grid">
                <div class="sf-card do"><span class="em">👂</span><div><b>Listen to your trainer</b><span>The trainer is the captain. What they say goes — always.</span></div></div>
                <div class="sf-card do"><span class="em">🙌</span><div><b>Heels down, eyes up, two hands</b><span>Hold the reins with both hands. Look where you want to go, not at the ground.</span></div></div>
                <div class="sf-card do"><span class="em">↔️</span><div><b>Keep a horse-length gap</b><span>Stay one horse behind the rider in front. No overtaking unless told.</span></div></div>
                <div class="sf-card do"><span class="em">🪢</span><div><b>Beginners stay on the lead rope</b><span>The trainer holds a rope to help you. Don't take it off yourself.</span></div></div>
                <div class="sf-card dont"><span class="em">🚀</span><div><b>No racing, stunts or jumping</b><span>Go at the speed the trainer sets — slow and steady wins.</span></div></div>
                <div class="sf-card dont"><span class="em">🤳</span><div><b>No selfies or earphones on the horse</b><span>Both hands on the reins, ears open for the trainer.</span></div></div>
                <div class="sf-card dont"><span class="em">🧵</span><div><b>Never wrap reins around your hand</b><span>Hold them — don't tie them to yourself.</span></div></div>
                <div class="sf-card do"><span class="em">🙋</span><div><b>Feeling scared, dizzy or tired? Say it!</b><span>It's always OK to say "please stop". The trainer will help right away.</span></div></div>
            </div>

            <div class="sf-title"><span class="em">🆘</span><div><h4>6. If something goes wrong</h4><small>Stay calm — here's what to do</small></div></div>
            <div class="sf-grid">
                <div class="sf-card"><span class="em">😱</span><div><b>Horse gets scared?</b><span>Sit deep, heels down, hold the reins (not the saddle), and talk softly. Don't scream or kick.</span></div></div>
                <div class="sf-card"><span class="em">🤸</span><div><b>If you fall</b><span>Let go of the reins, roll away from the horse's legs, and stay lying down until the trainer comes. Never chase the horse.</span></div></div>
                <div class="sf-card"><span class="em">🛑</span><div><b>If a friend falls</b><span>Stop your horse, stay sitting, and wait for the trainer.</span></div></div>
                <div class="sf-card"><span class="em">🩹</span><div><b>Any bump or scratch?</b><span>Tell the reception before you go home — even small ones. We have first aid here.</span></div></div>
            </div>

            <div class="sf-title"><span class="em">🌦️</span><div><h4>7. Weather days</h4><small>Sometimes we have to wait</small></div></div>
            <div class="sf-grid">
                <div class="sf-card"><span class="em">⛈️</span><div><b>Rain, lightning or very hot sun</b><span>The academy may pause or cancel a session. Safety first, riding second — we'll ride another day!</span></div></div>
                <div class="sf-card"><span class="em">💚</span><div><b>Be kind to your horse</b><span>Never hit, kick or yank the reins in anger. Horses remember kindness.</span></div></div>
            </div>

            <div class="sf-promise">
                <div class="em">🤝</div>
                <h4>The Rider's Promise</h4>
                <p>Horse riding is fun, but it has real risks — like any sport. By registering, you and your family promise to follow these rules. Riders who break the safety rules or hurt a horse may be asked to stop, without a refund.</p>
                <div class="sf-cheat"><span>⛑️ Helmet on</span><span>👢 Closed shoes</span><span>🤫 Slow &amp; quiet</span><span>👂 Listen to trainer</span><span>❓ Ask before you act</span></div>
            </div>
        </div>
        <div class="sf-foot"><button type="button" class="btn dark" data-safety-close><i class="fa fa-check"></i> I've read the guidelines</button></div>
    </div>
</div>
<script>
(function () {
    var minorAge = <?php echo $minor_age; ?>;
    var form = document.getElementById('shra-join'), wiz = document.getElementById('wiz');
    var titles = {1: ['How would you like to ride?', 'Two batches a day — morning and evening. Pick a start date and a timing; seats go first come, first served.'],
                  2: ['Pick a plan & a batch', 'Choose the age group and a plan, then tell us when you want to start and which batch suits you.'],
                  3: ['Your details', 'Takes two minutes. Fields marked * are required.']};
    var dob = document.getElementById('dob'), hint = document.getElementById('age-hint');
    var gname = document.getElementById('guardian_name'), gnote = document.getElementById('guardian-note');
    var byWrap = document.getElementById('accept-by-wrap'), by = document.getElementById('terms_accepted_by'), acceptText = document.getElementById('accept-text');

    function type() { var r = form.querySelector('[name=rider_type]:checked'); return r ? r.value : 'learner'; }
    function aud() { var r = form.querySelector('[name=audience]:checked'); return r ? r.value : 'children'; }

    function go(n) {
        document.querySelectorAll('.pane').forEach(function (p) { p.classList.toggle('on', p.dataset.pane == n); });
        document.querySelectorAll('.steps span').forEach(function (s) { var i = +s.dataset.s; s.classList.toggle('on', i == n); s.classList.toggle('done', i < n); });
        var t = titles[n][0], sub = titles[n][1];
        if (n == 2 && type() === 'guest') { t = 'Pick a plan'; sub = 'Choose the age group and a plan — we will fix the ride timing with you at the desk.'; }
        document.getElementById('pane-title').textContent = t;
        document.getElementById('pane-sub').textContent = sub;
        wiz.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function filterPlans() {
        var t = type(), a = aud(), first = null, anyChecked = false;
        document.querySelectorAll('.plan').forEach(function (p) {
            var show = p.dataset.aud === a && (+p.dataset.guest === (t === 'guest' ? 1 : 0));
            p.classList.toggle('show', show);
            var inp = p.querySelector('input');
            if (!show) { inp.checked = false; } else { if (!first) first = inp; if (inp.checked) anyChecked = true; }
        });
        if (!anyChecked && first) { first.checked = true; }
        syncPick();
    }

    function syncPick() {
        var c = form.querySelector('[name=plan_pick]:checked'), p = c ? c.closest('.plan') : null;
        document.getElementById('package_id').value = c ? c.value : '';
        document.getElementById('pick-name').textContent = p ? p.dataset.name + ' · ' + (aud() === 'children' ? 'Children' : 'Adults') : '—';
        document.getElementById('pick-total').textContent = p ? p.dataset.total : '';
    }

    function applyType() {
        var learner = type() === 'learner';
        wiz.classList.toggle('is-learner', learner);
        form.querySelector('[name=address]').required = learner;
        document.querySelector('#submit-btn span').textContent = learner ? 'Register & get my membership' : 'Reserve my guest ride';
        syncStartPrefs(learner);
        filterPlans();
    }

    // Guest rides carry no start date / batch; learners get today + the batch still open at this hour.
    function syncStartPrefs(learner) {
        var date = form.querySelector('[name=preferred_start_date]');
        var batches = form.querySelectorAll('[name=preferred_batch]');
        if (!learner) {
            date.value = '';
            batches.forEach(function (b) { b.checked = false; });
            return;
        }
        if (!form.querySelector('[name=preferred_batch]:checked') && batches.length) {
            var now = new Date(), mins = now.getHours() * 60 + now.getMinutes(), pick = null;
            batches.forEach(function (b) {
                var end = (b.closest('label').dataset.end || '').split(':');
                if (!pick && end.length === 2 && mins <= (+end[0]) * 60 + (+end[1])) { pick = b; }
            });
            if (!pick) { pick = batches[0]; now = new Date(now.getTime() + 86400000); } // past today's batches → tomorrow morning
            pick.checked = true;
            if (!date.value) {
                var ymd = now.getFullYear() + '-' + ('0' + (now.getMonth() + 1)).slice(-2) + '-' + ('0' + now.getDate()).slice(-2);
                date.value = ymd >= date.min ? ymd : date.min;
            }
        }
        if (!date.value) { date.value = date.min; }
    }

    function age() {
        if (!dob.value) return null;
        var d = new Date(dob.value), t = new Date(); var a = t.getFullYear() - d.getFullYear();
        if (t.getMonth() < d.getMonth() || (t.getMonth() === d.getMonth() && t.getDate() < d.getDate())) a--;
        return isNaN(a) ? null : a;
    }
    function updateMinor() {
        var a = age(), minor = a !== null && a < minorAge;
        hint.textContent = a === null ? '' : (a + ' years · ' + (minor ? 'Children plans' : 'Adult plans'));
        if (a !== null) { var want = minor ? 'children' : 'adults'; if (aud() !== want) { form.querySelector('[name=audience][value=' + want + ']').checked = true; filterPlans(); } }
        document.getElementById('guardian-wrap').classList.toggle('show', minor);
        gname.required = minor; by.required = minor;
        byWrap.style.display = minor ? '' : 'none';
        gnote.textContent = 'The rider is under ' + minorAge + ' — a parent / guardian must be named and accept the terms on the rider\'s behalf.';
        acceptText.innerHTML = minor ? 'As the parent / guardian named above, I have read and accept the terms &amp; conditions on behalf of the rider.' : 'I have read and accept the terms &amp; conditions of the academy.';
    }

    form.querySelectorAll('[name=rider_type]').forEach(function (r) { r.addEventListener('change', function () {
        if (type() === 'guest') { form.querySelector('[name=audience][value=adults]').checked = true; }
        applyType();
    }); });
    form.querySelectorAll('[name=audience]').forEach(function (r) { r.addEventListener('change', filterPlans); });
    form.querySelectorAll('[name=plan_pick]').forEach(function (r) { r.addEventListener('change', syncPick); });
    document.querySelectorAll('[data-next]').forEach(function (b) { b.addEventListener('click', function () {
        if (b.dataset.next == 3 && !document.getElementById('package_id').value) { alert('Please pick a plan.'); return; }
        go(+b.dataset.next);
    }); });
    document.querySelectorAll('[data-prev]').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); go(+b.dataset.prev); }); });
    dob.addEventListener('change', updateMinor); dob.addEventListener('input', updateMinor);
    gname.addEventListener('input', function () { if (!by.value || by.dataset.auto) { by.value = gname.value; by.dataset.auto = '1'; } });
    by.addEventListener('input', function () { delete by.dataset.auto; });

    var sfModal = document.getElementById('sf-modal');
    function sfOpen() { sfModal.classList.add('open'); document.body.style.overflow = 'hidden'; sfModal.querySelector('.sf-body').scrollTop = 0; }
    function sfClose() { sfModal.classList.remove('open'); document.body.style.overflow = ''; }
    document.querySelectorAll('[data-safety]').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); sfOpen(); }); });
    document.querySelectorAll('[data-safety-close]').forEach(function (b) { b.addEventListener('click', sfClose); });
    sfModal.addEventListener('click', function (e) { if (e.target === sfModal) sfClose(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && sfModal.classList.contains('open')) sfClose(); });

    form.addEventListener('submit', function (e) {
        var bad = Array.prototype.filter.call(this.querySelectorAll('[required]'), function (el) {
            if (el.type === 'checkbox') return !el.checked;
            return !el.value.trim();
        });
        var gender = this.querySelector('[name=gender]:checked');
        if (bad.length || !gender) {
            e.preventDefault();
            var first = bad[0] ? (bad[0].closest('.f') || bad[0].closest('.accept') || bad[0]) : this.querySelector('[name=gender]').closest('.f');
            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            alert('Please fill all the required fields' + (!gender ? ' (gender)' : '') + '.');
        }
    });

    applyType(); updateMinor();
    <?php if (count($errors)) { ?>go(3);<?php } ?>
})();
</script>
<?php include __DIR__ . '/_public_foot.php'; ?>
