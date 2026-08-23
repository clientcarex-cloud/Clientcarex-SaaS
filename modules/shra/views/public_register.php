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
</style>
    <div class="card" id="wiz">
        <div class="card-head" style="padding-bottom:14px">
            <div class="steps"><span class="on" data-s="1">Choose</span><span data-s="2">Plan</span><span data-s="3">Details</span><span data-s="4">Done</span></div>
            <h2 id="pane-title">How would you like to ride?</h2>
            <p id="pane-sub">No fixed time slots — sessions are first-come, first-served. Pay at the desk when you arrive.</p>
            <button type="button" class="safety-btn" data-safety><i class="fa-solid fa-shield-heart"></i> Horse riding safety guidelines</button>
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
                <div class="seg">
                    <label><input type="radio" name="audience" value="children" <?php echo $v('audience', 'children') === 'children' ? 'checked' : ''; ?>>Children · under <?php echo $minor_age; ?></label>
                    <label><input type="radio" name="audience" value="adults" <?php echo $v('audience') === 'adults' ? 'checked' : ''; ?>>Adults</label>
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
                <div class="hint" style="margin-top:10px">You'll pay at the reception desk — nothing is charged online. You can change the plan at the desk too.</div>
                <div class="nav"><button type="button" class="btn ghost" data-prev="1"><i class="fa fa-arrow-left"></i></button><button type="button" class="btn dark" data-next="3" id="to-details">Continue <i class="fa fa-arrow-right"></i></button></div>
            </div>

            <!-- ───── Step 3: details ───── -->
            <div class="pane" data-pane="3">
                <div class="pick"><span>Selected plan<br><b id="pick-name">—</b></span><span style="text-align:right"><b id="pick-total"></b><br><a href="#" data-prev="2">change</a></span></div>

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
                <div class="row learner-only">
                    <div class="f"><label>Place of birth</label><input type="text" name="place_of_birth" value="<?php echo $v('place_of_birth'); ?>"></div>
                    <div class="f"><label>Rider status</label><select name="marital_status"><option value="">Select</option><?php foreach (shra_marital_statuses() as $k => $l) { ?><option value="<?php echo $k; ?>" <?php echo $v('marital_status') === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php } ?></select></div>
                </div>
                <div class="f learner-only"><label>Full address <span class="req">*</span></label><textarea name="address" rows="2" autocomplete="street-address"><?php echo $v('address'); ?></textarea></div>
                <div class="f learner-only"><label>Riding level</label><div class="chips"><?php foreach ($levels as $i => $l) { ?><label><input type="radio" name="riding_level" value="<?php echo html_escape($l); ?>" <?php echo $v('riding_level', $levels[0]) === $l ? 'checked' : ''; ?>><span><?php echo html_escape($l); ?></span></label><?php } ?></div></div>

                <div class="sec">Parent / guardian</div>
                <div class="guardian" id="guardian-box">
                    <div class="note" id="guardian-note">Required for riders under <?php echo $minor_age; ?>. Optional otherwise — an emergency contact is always welcome.</div>
                    <div class="row">
                        <div class="f"><label>Guardian name <span class="req g-req" style="display:none">*</span></label><input type="text" name="guardian_name" id="guardian_name" value="<?php echo $v('guardian_name'); ?>"></div>
                        <div class="f"><label>Relationship</label><select name="guardian_relationship"><option value="">Select</option><?php foreach (shra_relationships() as $l) { ?><option value="<?php echo $l; ?>" <?php echo $v('guardian_relationship') === $l ? 'selected' : ''; ?>><?php echo $l; ?></option><?php } ?></select></div>
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
            <div class="sec">1. Before you arrive</div>
            <ul>
                <li><b>Health check.</b> Do not ride if you are unwell, pregnant, recovering from surgery, or have back, neck, heart or balance conditions without a doctor's clearance. Tell the trainer about any medical condition, allergy or medication.</li>
                <li><b>Under-18 riders</b> must be accompanied by a parent / guardian who stays at the academy for the whole session.</li>
                <li><b>No alcohol or drugs.</b> Riders under the influence will not be allowed near the horses.</li>
                <li><b>Arrive on time</b> and well rested. Eat a light meal — never ride on an empty stomach or straight after a heavy one.</li>
            </ul>

            <div class="sec">2. What to wear</div>
            <ul>
                <li><b>Helmet — always.</b> A properly fitted, certified riding helmet with the chin strap fastened is mandatory for every rider, every time. The academy provides helmets; no helmet, no ride.</li>
                <li><b>Long trousers</b> (jeans, jodhpurs or leggings) — no shorts or skirts.</li>
                <li><b>Closed shoes with a small heel</b> (riding boots or sturdy shoes). No sandals, slippers, flip-flops, crocs or wide-soled trainers that can get caught in the stirrup.</li>
                <li><b>Fitted clothing.</b> Avoid loose scarves, dupattas, dangling jewellery, hoodie strings or anything that can flap, snag or spook a horse.</li>
                <li><b>Tie back long hair</b> and remove rings, bangles and watches. Keep mobile phones, keys and loose items out of your pockets.</li>
                <li>Body protectors are recommended for learners and available on request.</li>
            </ul>

            <div class="sec">3. Around the horses</div>
            <ul>
                <li><b>Approach from the front-side</b>, at the shoulder, speaking calmly so the horse knows you are there. Never approach from directly behind — a horse cannot see you there and may kick.</li>
                <li><b>Move slowly, speak softly.</b> No running, shouting, screaming, sudden movements, waving arms or flash photography near the horses.</li>
                <li><b>Never feed the horses</b> unless a trainer hands you the feed and shows you how — feed from a flat, open palm.</li>
                <li><b>Do not stand directly behind or directly in front</b> of a horse, and never walk under its neck or between two tied horses.</li>
                <li><b>Do not enter any stable, paddock or arena</b> without a trainer's permission. Keep the gates closed behind you.</li>
                <li>Keep children within arm's reach at all times in the stable area. Pets are not allowed on the premises.</li>
            </ul>

            <div class="sec">4. Mounting &amp; dismounting</div>
            <ul>
                <li>Mount and dismount <b>only with a trainer present</b>, in the place the trainer shows you, after the girth has been checked.</li>
                <li>Check stirrup length before moving off — the trainer will adjust it for you.</li>
                <li>Keep both feet out of the stirrups and the reins in hand when dismounting. Never step down while the horse is moving.</li>
            </ul>

            <div class="sec">5. While riding</div>
            <ul>
                <li><b>Follow the trainer's instructions at all times.</b> Your trainer's word is final on what you may and may not do in the arena.</li>
                <li><b>Stay in your lane.</b> Keep at least one horse-length between you and the horse ahead, and never overtake or cut across another rider unless told to.</li>
                <li>Ride at the pace set by the trainer — no galloping, racing, jumping or stunts unless it is part of your supervised lesson.</li>
                <li><b>Keep your heels down, eyes up and hold the reins</b> with both hands. Do not wrap reins, lead ropes or straps around your hand, wrist or body.</li>
                <li>Do not use a mobile phone, take selfies, or wear earphones while on the horse.</li>
                <li>Beginners ride on a lead or lunge line until the trainer decides otherwise. Do not remove the lead rope yourself.</li>
                <li>If you feel unsafe, dizzy or tired, say so immediately — the trainer will stop the session.</li>
            </ul>

            <div class="sec">6. If something goes wrong</div>
            <ul>
                <li><b>If the horse spooks:</b> stay calm, sit deep, keep your heels down, hold the reins (not the saddle) and speak softly. Do not scream or kick.</li>
                <li><b>If you fall:</b> let go of the reins, try to roll away from the horse's legs, and stay down until a trainer reaches you. Do not stand up quickly or chase the horse.</li>
                <li><b>If another rider falls:</b> halt your horse, stay mounted, and wait for the trainer's instruction.</li>
                <li>Report every fall, kick, bite or injury — however minor — to the reception before leaving. A first-aid kit and trained staff are available on site.</li>
            </ul>

            <div class="sec">7. Weather &amp; arena conditions</div>
            <ul>
                <li>Sessions may be paused or cancelled by the academy in heavy rain, lightning, extreme heat or poor footing. Safety comes before schedule.</li>
                <li>In hot weather drink water before and after your ride, and wear sunscreen.</li>
            </ul>

            <div class="sec">8. Rider responsibility</div>
            <ul>
                <li>Horse riding is an activity with inherent risk. By registering you confirm that you (or your guardian) understand these guidelines and will follow them.</li>
                <li>Riders who ignore safety instructions, mistreat a horse or endanger others may be removed from the session without refund.</li>
                <li>Treat the horses with kindness and respect — no hitting, kicking or pulling on the reins in anger.</li>
            </ul>

            <div class="sf-alert"><i class="fa-solid fa-triangle-exclamation"></i><span>Helmet on, chin strap fastened, closed shoes, and always listen to your trainer. When in doubt — ask before you act.</span></div>
        </div>
        <div class="sf-foot"><button type="button" class="btn dark" data-safety-close><i class="fa fa-check"></i> I've read the guidelines</button></div>
    </div>
</div>
<script>
(function () {
    var minorAge = <?php echo $minor_age; ?>;
    var form = document.getElementById('shra-join'), wiz = document.getElementById('wiz');
    var titles = {1: ['How would you like to ride?', 'No fixed time slots — sessions are first-come, first-served. Pay at the desk when you arrive.'],
                  2: ['Pick a plan', 'Choose the age group, then a plan. Prices are paid at the reception desk.'],
                  3: ['Your details', 'Takes two minutes. Fields marked * are required.']};
    var dob = document.getElementById('dob'), hint = document.getElementById('age-hint');
    var gname = document.getElementById('guardian_name'), gnote = document.getElementById('guardian-note');
    var byWrap = document.getElementById('accept-by-wrap'), by = document.getElementById('terms_accepted_by'), acceptText = document.getElementById('accept-text');

    function type() { var r = form.querySelector('[name=rider_type]:checked'); return r ? r.value : 'learner'; }
    function aud() { var r = form.querySelector('[name=audience]:checked'); return r ? r.value : 'children'; }

    function go(n) {
        document.querySelectorAll('.pane').forEach(function (p) { p.classList.toggle('on', p.dataset.pane == n); });
        document.querySelectorAll('.steps span').forEach(function (s) { var i = +s.dataset.s; s.classList.toggle('on', i == n); s.classList.toggle('done', i < n); });
        document.getElementById('pane-title').textContent = titles[n][0];
        document.getElementById('pane-sub').textContent = titles[n][1];
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
        filterPlans();
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
        document.querySelectorAll('.g-req').forEach(function (el) { el.style.display = minor ? '' : 'none'; });
        gname.required = minor; by.required = minor;
        byWrap.style.display = minor ? '' : 'none';
        gnote.textContent = minor ? 'The rider is under ' + minorAge + ' — a parent / guardian must be named and accept the terms on the rider\'s behalf.' : 'Required for riders under ' + minorAge + '. Optional otherwise — an emergency contact is always welcome.';
        acceptText.innerHTML = minor ? 'As the parent / guardian named above, I have read and accept the terms &amp; conditions on behalf of the rider.' : 'I have read and accept the terms &amp; conditions of the academy.';
    }

    form.querySelectorAll('[name=rider_type]').forEach(function (r) { r.addEventListener('change', applyType); });
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
