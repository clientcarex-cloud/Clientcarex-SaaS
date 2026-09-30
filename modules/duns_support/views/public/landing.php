<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * The ad landing page: hero → uses → pricing → how it works → documents →
 * guarantee → order form → FAQ → final CTA.
 */
$events = [['ViewContent', ['content_name' => 'DUNS Number', 'content_category' => 'DUNS registration', 'currency' => $landing['currency']]]];
$nav    = true;
$ov     = function ($k) use ($old) { return html_escape((string) ($old[$k] ?? '')); };
$fe     = function ($k) use ($errors) { return isset($errors[$k]) ? ' has-err' : ''; };
$fm     = function ($k) use ($errors) { return isset($errors[$k]) ? '<div class="ferr">' . html_escape($errors[$k]) . '</div>' : ''; };
$plan_js = [];
foreach ($plans as $pl) {
    $plan_js[(int) $pl->id] = ['name' => $pl->name, 'hours' => duns_hours_label($pl->delivery_hours), 'price' => duns_money($pl->price), 'value' => (float) $pl->price];
}
$fastest = count($plans) ? min(array_map(function ($p) { return (int) $p->delivery_hours; }, $plans)) : 1;
include __DIR__ . '/_head.php';
?>
<body>
<?php include __DIR__ . '/_topbar.php'; ?>

<!-- ═══════════ Hero ═══════════ -->
<section class="hero" style="padding-top:48px">
    <div class="container">
        <div>
            <span class="eyebrow"><i class="fa-solid fa-bolt"></i> Delivered in as little as <?php echo duns_hours_label($fastest); ?></span>
            <h1><?php
                // Colour the part after "DUNS Number" for emphasis when the default headline is used
                $h = html_escape($landing['hero_title']);
                echo preg_replace('/(in as little as .+)$/i', '<em>$1</em>', $h, 1);
            ?></h1>
            <?php if ($landing['hero_sub'] !== '') { ?><p class="lead"><?php echo html_escape($landing['hero_sub']); ?></p><?php } ?>
            <ul class="ticks">
                <li><i class="fa-solid fa-circle-check"></i> 100% valid DUNS number — works for Google Play &amp; Apple</li>
                <li><i class="fa-solid fa-circle-check"></i> Delivered straight to your email</li>
                <li><i class="fa-solid fa-circle-check"></i> Money-back guarantee — no DUNS, no charge</li>
            </ul>
            <div class="hero-cta">
                <a href="#order" class="btn btn-primary btn-lg" data-cta>Get my DUNS number <i class="fa-solid fa-arrow-right"></i></a>
                <?php if ($landing['wa_link'] !== '') { ?>
                <a href="<?php echo html_escape($landing['wa_link']); ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-lg" onclick="dunsTrack('Contact')"><i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp</a>
                <?php } ?>
            </div>
            <div class="works">Accepted for:
                <b><i class="fa-brands fa-google-play"></i> Google Play</b>
                <b><i class="fa-brands fa-apple"></i> Apple Developer</b>
                <b><i class="fa-brands fa-microsoft"></i> Microsoft Partner</b>
                <b><i class="fa-solid fa-landmark"></i> Tenders &amp; vendors</b>
            </div>
        </div>

        <div class="visual" aria-hidden="true">
            <div class="float f1"><i class="fa-solid fa-envelope-circle-check"></i> Sent to your inbox</div>
            <div class="dcard">
                <div class="top"><span class="t">Order status</span><span class="pill pill-green"><i class="fa-solid fa-circle-check"></i> Delivered</span></div>
                <div class="dnum">
                    <small>D-U-N-S Number</small>
                    <div class="n">XX-XXX-XXXX</div>
                    <div class="co">Your Company Pvt. Ltd.</div>
                </div>
                <ul class="tl">
                    <li><span class="d"><i class="fa-solid fa-check"></i></span> Documents received <span class="tm">0 min</span></li>
                    <li><span class="d"><i class="fa-solid fa-check"></i></span> Application filed <span class="tm">12 min</span></li>
                    <li><span class="d"><i class="fa-solid fa-check"></i></span> DUNS number issued <span class="tm">&lt; 1 hr</span></li>
                </ul>
            </div>
            <div class="float f2"><i class="fa-solid fa-shield-halved"></i> Money-back guarantee</div>
        </div>
    </div>
</section>

<!-- ═══════════ Where it is used ═══════════ -->
<div class="uses">
    <div class="container">
        <div class="use"><i class="fa-brands fa-google-play" style="color:#34a853"></i><div><b>Google Play Console</b><small>Organisation account</small></div></div>
        <div class="use"><i class="fa-brands fa-apple"></i><div><b>Apple Developer</b><small>Organisation enrolment</small></div></div>
        <div class="use"><i class="fa-brands fa-microsoft" style="color:#0078d4"></i><div><b>Microsoft &amp; others</b><small>Partner / store programs</small></div></div>
        <div class="use"><i class="fa-solid fa-earth-asia" style="color:#2563eb"></i><div><b>Global trade</b><small>Tenders, vendors, credit</small></div></div>
    </div>
</div>

<!-- ═══════════ Pricing ═══════════ -->
<section id="pricing">
    <div class="container">
        <div class="sec-head">
            <span class="kicker">Simple pricing</span>
            <h2>Pick how fast you need it</h2>
            <p>One-time fee. The clock starts the moment your payment is confirmed.</p>
        </div>
        <div class="plans">
            <?php foreach ($plans as $pl) {
                $icon = $pl->delivery_hours <= 1 ? 'fa-bolt' : ($pl->delivery_hours <= 6 ? 'fa-rocket' : 'fa-clock'); ?>
            <div class="plan<?php echo $pl->is_featured ? ' featured' : ''; ?>">
                <?php if ($pl->badge) { ?><span class="badge"><?php echo html_escape($pl->badge); ?></span><?php } ?>
                <div class="speed">
                    <span class="ic"><i class="fa-solid <?php echo $icon; ?>"></i></span>
                    <div><div class="nm"><?php echo html_escape($pl->name); ?></div><div class="hrs">Get within <?php echo duns_hours_label($pl->delivery_hours); ?></div></div>
                </div>
                <div class="price">
                    <b><?php echo duns_money($pl->price); ?></b>
                    <?php if ($pl->compare_price && $pl->compare_price > $pl->price) { ?><s><?php echo duns_money($pl->compare_price); ?></s><?php } ?>
                </div>
                <div class="once">One-time payment</div>
                <ul>
                    <?php foreach (array_filter(array_map('trim', explode("\n", (string) $pl->features))) as $ft) { ?>
                    <li><i class="fa-solid fa-check"></i> <?php echo html_escape($ft); ?></li>
                    <?php } ?>
                </ul>
                <a href="#order" class="btn <?php echo $pl->is_featured ? 'btn-primary' : 'btn-outline'; ?> btn-block" data-plan="<?php echo (int) $pl->id; ?>">Get it in <?php echo duns_hours_label($pl->delivery_hours); ?> <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <?php } ?>
        </div>
    </div>
</section>

<!-- ═══════════ How it works ═══════════ -->
<section id="how" style="padding-top:0">
    <div class="container">
        <div class="sec-head">
            <span class="kicker">How it works</span>
            <h2>Your DUNS number in 4 easy steps</h2>
            <p>No lengthy forms, no follow-ups with anyone. We handle everything.</p>
        </div>
        <div class="steps">
            <div class="step"><div class="no">1</div><h3>Choose your speed</h3><p>1, 6 or 12 hours — pick the plan that fits your launch.</p></div>
            <div class="step"><div class="no">2</div><h3>Share 3 details</h3><p>Upload your incorporation certificate and one director's contact details.</p></div>
            <div class="step"><div class="no">3</div><h3>Pay securely</h3><p>UPI, cards or net banking through a secure payment gateway.</p></div>
            <div class="step"><div class="no">4</div><h3>Get it on email</h3><p>Your valid DUNS number lands in your inbox — ready to use.</p></div>
        </div>
    </div>
</section>

<!-- ═══════════ Documents ═══════════ -->
<section id="documents" class="docs-wrap">
    <div class="container">
        <div class="sec-head">
            <span class="kicker">Required documents</span>
            <h2>Just 3 things. That's it.</h2>
            <p>Keep these ready and you can finish your order in under 2 minutes.</p>
        </div>
        <div class="docs">
            <div class="doc">
                <div class="ic"><i class="fa-solid fa-file-contract"></i></div>
                <h3>Certificate of Incorporation</h3>
                <p>The certificate issued when your company was registered.</p>
                <div class="chips"><span>PDF</span><span>JPG</span><span>PNG</span><span>up to 10 MB</span></div>
            </div>
            <div class="doc">
                <div class="ic"><i class="fa-solid fa-user-tie"></i></div>
                <h3>One director's contact details</h3>
                <p>We need these to register the company contact.</p>
                <div class="chips"><span>Name</span><span>Email</span><span>Mobile number</span></div>
            </div>
            <div class="doc">
                <div class="ic"><i class="fa-solid fa-envelope-open-text"></i></div>
                <h3>Work email</h3>
                <p>Your company email — used later for OTP verification (e.g. by Apple or Google).</p>
                <div class="chips"><span>you@yourcompany.com</span></div>
            </div>
        </div>
        <p class="docs-note"><i class="fa-solid fa-lock"></i> Your documents are stored privately and used <b>only</b> to obtain your DUNS number.</p>
    </div>
</section>

<!-- ═══════════ Guarantee ═══════════ -->
<section style="padding-bottom:0">
    <div class="container">
        <div class="guarantee">
            <div class="seal"><b>100%</b>MONEY<br>BACK</div>
            <div>
                <h2>No DUNS number? Full refund. Guaranteed.</h2>
                <p>You get a 100% valid DUNS number on your email, usable for Google Play, the Apple App Store and every other DUNS requirement — or you get every rupee back.</p>
            </div>
            <a href="#order" class="btn btn-lg" style="background:#fff;color:#14532d" data-cta>Start now <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- ═══════════ Order form ═══════════ -->
<section id="order">
    <div class="container">
        <div class="sec-head">
            <span class="kicker">Place your order</span>
            <h2>Get your DUNS number now</h2>
            <p>Takes under 2 minutes. You pay on the next screen.</p>
        </div>
        <div class="order-wrap">
            <aside class="aside">
                <h3>What happens next</h3>
                <p>Here is exactly what to expect after you submit.</p>
                <ol>
                    <li>You pay securely on the next screen.</li>
                    <li>Our team files your application immediately.</li>
                    <li>Your DUNS number arrives on your email within your plan's time.</li>
                    <li>Didn't get it? You get a full refund.</li>
                </ol>
                <div class="sum">
                    <div class="r"><span>Plan</span><b id="sum-plan">—</b></div>
                    <div class="r"><span>Delivery</span><b id="sum-hours">—</b></div>
                    <div class="r tot"><span>Total</span><b id="sum-price">—</b></div>
                </div>
                <div class="safe"><i class="fa-solid fa-shield-halved"></i> Secure checkout · 100% money-back guarantee</div>
            </aside>

            <div class="card form-card">
                <h3>Your details</h3>
                <p class="sub">Fields marked optional can be left blank.</p>

                <?php if (count($errors)) { ?>
                <div class="alert alert-err" id="form-errors">
                    <b>Please fix the highlighted fields.</b>
                    <?php if (isset($errors['form'])) { ?><div><?php echo html_escape($errors['form']); ?></div><?php } ?>
                    <?php if (isset($errors['coi']) || !empty($old)) { ?><div style="margin-top:4px">For your security, please attach the certificate again.</div><?php } ?>
                </div>
                <?php } ?>

                <form method="post" action="<?php echo duns_public_url('order'); ?>" enctype="multipart/form-data" id="duns-form" novalidate>
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                    <?php foreach ($track as $k => $v) { ?><input type="hidden" name="<?php echo $k; ?>" value="<?php echo html_escape($v); ?>"><?php } ?>
                    <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                    <div class="fsec" style="margin-top:18px">1 · Delivery speed</div>
                    <div class="field<?php echo $fe('plan_id'); ?>">
                        <div class="pchoices">
                            <?php foreach ($plans as $pl) { $on = (int) $pl->id === (int) $pick; ?>
                            <label class="pchoice<?php echo $on ? ' on' : ''; ?>">
                                <input type="radio" name="plan_id" value="<?php echo (int) $pl->id; ?>" <?php echo $on ? 'checked' : ''; ?>>
                                <span class="ck"><i class="fa-solid fa-check"></i></span>
                                <span class="h"><i class="fa-solid fa-bolt"></i> <?php echo duns_hours_label($pl->delivery_hours); ?></span>
                                <span class="p"><?php echo duns_money($pl->price); ?></span>
                                <span class="s"><?php echo html_escape($pl->name); ?></span>
                            </label>
                            <?php } ?>
                        </div>
                        <?php echo $fm('plan_id'); ?>
                    </div>

                    <div class="fsec">2 · Company</div>
                    <div class="field<?php echo $fe('company_name'); ?>">
                        <label for="f-co">Company name <span class="opt">(as on the certificate)</span></label>
                        <input type="text" id="f-co" name="company_name" value="<?php echo $ov('company_name'); ?>" placeholder="Acme Technologies Private Limited" autocomplete="organization" required>
                        <?php echo $fm('company_name'); ?>
                    </div>
                    <div class="field<?php echo $fe('coi'); ?>">
                        <label>Certificate of Incorporation</label>
                        <div class="drop" id="drop">
                            <input type="file" name="coi" id="f-coi" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" required>
                            <div class="ic"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                            <b id="drop-t">Tap to upload or drop the file here</b>
                            <small id="drop-s">PDF, JPG or PNG · max 10 MB</small>
                        </div>
                        <?php echo $fm('coi'); ?>
                    </div>

                    <div class="fsec">3 · Director contact</div>
                    <div class="field<?php echo $fe('director_name'); ?>">
                        <label for="f-dn">Director's full name</label>
                        <input type="text" id="f-dn" name="director_name" value="<?php echo $ov('director_name'); ?>" placeholder="Full name" autocomplete="name" required>
                        <?php echo $fm('director_name'); ?>
                    </div>
                    <div class="grid2">
                        <div class="field<?php echo $fe('director_email'); ?>">
                            <label for="f-de">Director's email</label>
                            <input type="email" id="f-de" name="director_email" value="<?php echo $ov('director_email'); ?>" placeholder="name@email.com" autocomplete="email" inputmode="email" required>
                            <div class="hint">Your DUNS number is sent here.</div>
                            <?php echo $fm('director_email'); ?>
                        </div>
                        <div class="field<?php echo $fe('director_mobile'); ?>">
                            <label for="f-dm">Director's mobile</label>
                            <input type="tel" id="f-dm" name="director_mobile" value="<?php echo $ov('director_mobile'); ?>" placeholder="+91 98765 43210" autocomplete="tel" inputmode="tel" required>
                            <?php echo $fm('director_mobile'); ?>
                        </div>
                    </div>

                    <div class="fsec">4 · Work email</div>
                    <div class="field<?php echo $fe('work_email'); ?>">
                        <label for="f-we">Company / work email</label>
                        <input type="email" id="f-we" name="work_email" value="<?php echo $ov('work_email'); ?>" placeholder="you@yourcompany.com" inputmode="email" required>
                        <div class="hint">Used later for OTP verification — please use an inbox you can access.</div>
                        <?php echo $fm('work_email'); ?>
                    </div>
                    <div class="field">
                        <label for="f-nt">Anything we should know? <span class="opt">(optional)</span></label>
                        <textarea id="f-nt" name="notes" placeholder="e.g. we already applied on D&amp;B earlier"><?php echo $ov('notes'); ?></textarea>
                    </div>

                    <div class="field<?php echo $fe('agree'); ?>">
                        <label class="agree"><input type="checkbox" name="agree" value="1" <?php echo !empty($old['agree']) ? 'checked' : ''; ?>>
                            <span>I confirm the details are correct and authorise you to apply for a DUNS number on my company's behalf<?php if ($landing['terms_url'] !== '') { ?>, and I accept the <a href="<?php echo html_escape($landing['terms_url']); ?>" target="_blank" rel="noopener">terms &amp; refund policy</a><?php } ?>.</span>
                        </label>
                        <?php echo $fm('agree'); ?>
                    </div>

                    <div class="submit-row">
                        <button type="submit" class="btn btn-primary btn-lg btn-block" id="duns-submit">
                            <?php echo $can_pay ? 'Continue to secure payment' : 'Submit my order'; ?> <i class="fa-solid fa-lock"></i>
                        </button>
                        <div class="secure">
                            <span><i class="fa-solid fa-lock"></i> SSL secured</span>
                            <span><i class="fa-solid fa-shield-halved"></i> Money-back guarantee</span>
                            <span><i class="fa-solid fa-envelope"></i> Delivered by email</span>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ FAQ ═══════════ -->
<section id="faq" style="padding-top:0">
    <div class="container">
        <div class="sec-head">
            <span class="kicker">FAQ</span>
            <h2>Questions, answered</h2>
        </div>
        <div class="faq">
            <details open><summary>What is a DUNS number?</summary><p>A D-U-N-S number is a unique 9-digit identifier for businesses, issued by Dun &amp; Bradstreet. Platforms like Google Play Console and the Apple Developer Program use it to verify that your organisation is a real, registered business.</p></details>
            <details><summary>Why do Google Play and Apple ask for it?</summary><p>To publish apps under your company name (an organisation account), both Google and Apple verify your business through its DUNS number. Without it you can only publish as an individual.</p></details>
            <details><summary>Is the DUNS number valid and genuine?</summary><p>Yes. You receive a 100% valid DUNS number registered for your company, which you can use for Google Play, the Apple App Store and every other place that asks for one.</p></details>
            <details><summary>How will I receive it?</summary><p>By email, to the director's email address you enter, within the time of the plan you choose. You can also follow the progress live on your order page.</p></details>
            <details><summary>When does the delivery time start?</summary><p>The moment your payment is confirmed. The 1, 6 or 12 hour window is counted from then.</p></details>
            <details><summary>Why do you need a work email?</summary><p>Some platforms (for example Apple) send a verification code to a company email when they check your DUNS details. We note it now so the verification goes smoothly later.</p></details>
            <details><summary>What if I don't get my DUNS number?</summary><p>You get a full refund. Our money-back guarantee covers you if we cannot deliver a valid DUNS number for your company.</p></details>
            <details><summary>Is my data safe?</summary><p>Your documents are stored privately, are never shared publicly and are used only to obtain your DUNS number.</p></details>
        </div>
    </div>
</section>

<!-- ═══════════ Final CTA ═══════════ -->
<section style="padding-top:0">
    <div class="container">
        <div class="final">
            <h2>Launch your app under your company name — today.</h2>
            <p>Get a valid DUNS number from <?php echo duns_money($min_price); ?>, delivered to your email in as little as <?php echo duns_hours_label($fastest); ?>.</p>
            <div class="hero-cta">
                <a href="#order" class="btn btn-primary btn-lg" data-cta>Get my DUNS number <i class="fa-solid fa-arrow-right"></i></a>
                <?php if ($landing['wa_link'] !== '') { ?>
                <a href="<?php echo html_escape($landing['wa_link']); ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-lg" onclick="dunsTrack('Contact')"><i class="fa-brands fa-whatsapp"></i> WhatsApp us</a>
                <?php } ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/_footer.php'; ?>

<?php if ($landing['wa_link'] !== '') { ?>
<a class="wa-float" href="<?php echo html_escape($landing['wa_link']); ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp" onclick="dunsTrack('Contact')"><i class="fa-brands fa-whatsapp"></i></a>
<?php } ?>
<div class="mbar" id="mbar">
    <div class="t"><small>DUNS number from</small><b><?php echo duns_money($min_price); ?></b></div>
    <a href="#order" class="btn btn-primary" data-cta>Get DUNS Now</a>
</div>

<script>
(function(){
    var plans = <?php echo json_encode($plan_js); ?>;
    var form  = document.getElementById('duns-form');
    var choices = form.querySelectorAll('.pchoice');

    function selectPlan(id){
        choices.forEach(function(c){
            var r = c.querySelector('input'); var on = r.value == id;
            r.checked = on; c.classList.toggle('on', on);
        });
        var p = plans[id];
        if (p) {
            document.getElementById('sum-plan').textContent  = p.name;
            document.getElementById('sum-hours').textContent = 'Within ' + p.hours;
            document.getElementById('sum-price').textContent = p.price;
        }
    }
    choices.forEach(function(c){ c.addEventListener('change', function(){ selectPlan(c.querySelector('input').value); }); });
    var checked = form.querySelector('input[name=plan_id]:checked');
    if (checked) { selectPlan(checked.value); }

    // Pricing buttons pick the plan before scrolling to the form
    document.querySelectorAll('[data-plan]').forEach(function(a){
        a.addEventListener('click', function(){
            var p = plans[a.getAttribute('data-plan')];
            selectPlan(a.getAttribute('data-plan'));
            if (p) { dunsTrack('InitiateCheckout', {value: p.value, currency: <?php echo json_encode($landing['currency']); ?>, content_name: p.name}); }
        });
    });

    // File picker feedback
    var fin = document.getElementById('f-coi'), drop = document.getElementById('drop');
    fin.addEventListener('change', function(){
        var f = fin.files && fin.files[0];
        drop.classList.toggle('has', !!f);
        document.getElementById('drop-t').textContent = f ? f.name : 'Tap to upload or drop the file here';
        document.getElementById('drop-s').textContent = f ? (f.size > 10485760 ? 'Too large — the limit is 10 MB' : (Math.round(f.size / 1024) + ' KB · tap to change')) : 'PDF, JPG or PNG · max 10 MB';
    });
    ['dragenter','dragover'].forEach(function(e){ drop.addEventListener(e, function(){ drop.classList.add('over'); }); });
    ['dragleave','drop'].forEach(function(e){ drop.addEventListener(e, function(){ drop.classList.remove('over'); }); });

    // Light client-side check so people are not bounced by the server for a blank field
    form.addEventListener('submit', function(ev){
        var bad = null;
        form.querySelectorAll('[required]').forEach(function(i){
            var ok = i.type === 'file' ? i.files.length > 0 : i.value.trim() !== '' && (!i.checkValidity || i.checkValidity());
            i.closest('.field').classList.toggle('has-err', !ok);
            if (!ok && !bad) { bad = i; }
        });
        var ag = form.querySelector('[name=agree]');
        ag.closest('.field').classList.toggle('has-err', !ag.checked);
        if (!ag.checked && !bad) { bad = ag; }
        if (bad) { ev.preventDefault(); (bad.closest('.field') || bad).scrollIntoView({behavior:'smooth', block:'center'}); return; }
        var btn = document.getElementById('duns-submit');
        btn.disabled = true; btn.innerHTML = 'Please wait… <i class="fa-solid fa-spinner fa-spin"></i>';
        var p = plans[(form.querySelector('input[name=plan_id]:checked') || {}).value];
        dunsTrack('Lead', p ? {value: p.value, currency: <?php echo json_encode($landing['currency']); ?>, content_name: p.name} : {});
    });

    // Back button from the checkout restores a disabled button — re-arm it
    var submitHtml = document.getElementById('duns-submit').innerHTML;
    window.addEventListener('pageshow', function(){ var b = document.getElementById('duns-submit'); b.disabled = false; b.innerHTML = submitHtml; });

    // Mobile sticky bar: show after the hero, hide over the form
    var bar = document.getElementById('mbar'), order = document.getElementById('order');
    function onScroll(){
        var r = order.getBoundingClientRect();
        var overForm = r.top < window.innerHeight && r.bottom > 0;
        bar.classList.toggle('show', window.scrollY > 500 && !overForm);
    }
    window.addEventListener('scroll', onScroll, {passive:true}); onScroll();

    <?php if (count($errors)) { ?>
    document.getElementById('order').scrollIntoView();
    <?php } ?>
})();
</script>
</body>
</html>
