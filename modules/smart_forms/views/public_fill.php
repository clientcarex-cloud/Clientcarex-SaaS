<?php defined('BASEPATH') or exit('No direct script access allowed');

$company    = get_option('companyname');
$assignment = isset($assignment) ? $assignment : null;
$draft      = isset($draft) ? $draft : [];
$has_files  = false;
foreach ($form->questions as $q) {
    if ($q->question_type === 'file') {
        $has_files = true;
        break;
    }
}
$is_overdue = $assignment && $assignment->due_date && strtotime($assignment->due_date) < time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo html_escape($form->title); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--p:#6366f1;--pd:#4f46e5;--ok:#10b981;--warn:#f59e0b;--bad:#ef4444;--text:#1e293b;--muted:#64748b;--border:#e2e8f0;--bg:#f1f5f9}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:linear-gradient(160deg,#eef2ff 0%,#f8fafc 40%,#f1f5f9 100%);min-height:100vh;color:var(--text);padding:28px 16px 60px}
.wrap{max-width:680px;margin:0 auto}
.brand{text-align:center;margin-bottom:18px;font-size:13px;font-weight:600;color:var(--muted);letter-spacing:.4px}
.card{background:#fff;border:1px solid var(--border);border-radius:18px;box-shadow:0 20px 50px rgba(15,23,42,.08);overflow:hidden}
.head{background:linear-gradient(135deg,var(--p),#8b5cf6);padding:30px 32px;color:#fff}
.head h1{font-size:22px;font-weight:800;line-height:1.3}
.head p{margin-top:8px;font-size:14px;opacity:.92;line-height:1.55}
.assign-bar{display:flex;flex-wrap:wrap;gap:8px 18px;align-items:center;padding:12px 32px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;font-size:13px;color:#166534;font-weight:600}
.assign-bar.overdue{background:#fef2f2;border-bottom-color:#fecaca;color:#b91c1c}
.assign-bar i{margin-right:6px}
.progress-wrap{position:sticky;top:0;z-index:5;background:rgba(255,255,255,.92);backdrop-filter:blur(6px);padding:12px 32px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px}
.progress-track{flex:1;height:8px;background:#eef2ff;border-radius:6px;overflow:hidden}
.progress-fill{height:100%;width:0;background:linear-gradient(90deg,var(--p),#8b5cf6);border-radius:6px;transition:width .3s}
.progress-num{font-size:12px;font-weight:700;color:var(--p);min-width:38px;text-align:right}
.body{padding:26px 32px 32px}
@media(max-width:560px){.head,.body{padding-left:20px;padding-right:20px}.progress-wrap,.assign-bar{padding-left:20px;padding-right:20px}}
.q{margin-bottom:26px}
.q-label{font-size:15px;font-weight:600;line-height:1.45;margin-bottom:4px}
.q-label .req{color:var(--bad);margin-left:3px}
.q-desc{font-size:13px;color:var(--muted);margin-bottom:10px;line-height:1.5}
.q.invalid .q-label{color:var(--bad)}
.q.invalid .control{outline:2px solid rgba(239,68,68,.35);outline-offset:3px;border-radius:12px}
.section-h{margin:34px 0 18px;padding:16px 20px;background:#eef2ff;border-left:4px solid var(--p);border-radius:12px}
.section-h:first-child{margin-top:0}
.section-h h3{font-size:16px;font-weight:800;color:var(--pd)}
.section-h p{font-size:13px;color:var(--muted);margin-top:4px;line-height:1.5}
input[type=text],input[type=email],input[type=number],input[type=date],input[type=password],textarea,select{width:100%;border:1.5px solid var(--border);border-radius:12px;padding:12px 15px;font-size:14.5px;font-family:inherit;color:var(--text);background:#fff;outline:none;transition:border-color .15s,box-shadow .15s}
input:focus,textarea:focus,select:focus{border-color:var(--p);box-shadow:0 0 0 4px rgba(99,102,241,.12)}
textarea{min-height:96px;resize:vertical}
.pills{display:flex;gap:10px;flex-wrap:wrap}
.pill{flex:1;min-width:110px;border:1.5px solid var(--border);border-radius:12px;padding:13px 16px;background:#fff;font-size:14px;font-weight:600;color:var(--muted);cursor:pointer;text-align:center;transition:all .13s;user-select:none}
.pill:hover{border-color:var(--p);color:var(--p)}
.pill.sel-ok{border-color:var(--ok);background:#ecfdf5;color:#047857;box-shadow:0 0 0 3px rgba(16,185,129,.14)}
.pill.sel-bad{border-color:var(--bad);background:#fef2f2;color:#b91c1c;box-shadow:0 0 0 3px rgba(239,68,68,.12)}
.pill i{margin-right:7px}
.opt{display:flex;align-items:center;gap:12px;border:1.5px solid var(--border);border-radius:12px;padding:12px 15px;margin-bottom:9px;cursor:pointer;transition:all .13s;background:#fff}
.opt:hover{border-color:var(--p)}
.opt.sel{border-color:var(--p);background:#eef2ff}
.opt input{width:17px;height:17px;accent-color:var(--p);flex-shrink:0;margin:0}
.opt span{font-size:14px;font-weight:500}
.stars{display:flex;gap:6px;flex-wrap:wrap}
.stars i{font-size:30px;color:#e2e8f0;cursor:pointer;transition:transform .1s,color .1s}
.stars i:hover{transform:scale(1.15)}
.stars i.on{color:var(--warn)}
.scale{display:flex;gap:6px;flex-wrap:wrap}
.scale button{width:44px;height:44px;border:1.5px solid var(--border);border-radius:10px;background:#fff;font-size:14px;font-weight:700;color:var(--muted);cursor:pointer;transition:all .12s}
.scale button:hover{border-color:var(--p);color:var(--p)}
.scale button.sel{background:linear-gradient(135deg,var(--p),var(--pd));border-color:var(--pd);color:#fff}
.scale-labels{display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-top:6px}
.terms-box{border:1.5px solid var(--border);border-radius:12px;background:#f8fafc;padding:16px 18px;max-height:220px;overflow:auto;font-size:13.5px;line-height:1.65;color:#334155;white-space:pre-wrap;margin-bottom:12px}
/* file upload */
.dropzone{border:2px dashed var(--border);border-radius:14px;padding:22px;text-align:center;cursor:pointer;transition:all .15s;background:#fafbff}
.dropzone:hover,.dropzone.drag{border-color:var(--p);background:#eef2ff}
.dropzone i{font-size:26px;color:var(--p);margin-bottom:8px;display:block}
.dropzone .dz-title{font-size:13.5px;font-weight:600}
.dropzone .dz-sub{font-size:11.5px;color:var(--muted);margin-top:4px}
.file-list{margin-top:10px}
.file-item{display:flex;align-items:center;gap:10px;background:#f8fafc;border:1px solid var(--border);border-radius:10px;padding:8px 12px;margin-bottom:7px;font-size:13px}
.file-item i.fa-paperclip{color:var(--p)}
.file-item .fsize{color:var(--muted);font-size:11.5px;margin-left:auto}
.file-item button{border:none;background:none;color:var(--bad);cursor:pointer;font-size:14px}
/* credentials */
.cred{display:flex;flex-direction:column;gap:10px}
.cred .cred-row{position:relative}
.cred .cred-row i.lead{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px}
.cred input{padding-left:38px}
.cred .toggle-pw{position:absolute;right:12px;top:50%;transform:translateY(-50%);border:none;background:none;color:var(--muted);cursor:pointer;font-size:13px}
.cred-note{font-size:11.5px;color:var(--muted)}
.cred-note i{color:var(--ok);margin-right:4px}
.identity{background:#f8fafc;border:1.5px dashed var(--border);border-radius:14px;padding:18px;margin-bottom:26px}
.identity h4{font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px}
.identity .row2{display:flex;gap:12px}
@media(max-width:560px){.identity .row2{flex-direction:column}}
.identity .row2>div{flex:1}
.identity label{display:block;font-size:12.5px;font-weight:600;color:var(--muted);margin-bottom:5px}
/* steps wizard */
.steps-ind{margin-bottom:26px}
.steps-track{display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:10px}
.sdot{width:34px;height:34px;border-radius:50%;border:2px solid var(--border);background:#fff;color:var(--muted);font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s;cursor:default}
.sdot.done{border-color:var(--ok);background:#ecfdf5;color:var(--ok);cursor:pointer}
.sdot.active{border-color:var(--pd);background:linear-gradient(135deg,var(--p),var(--pd));color:#fff;box-shadow:0 0 0 4px rgba(99,102,241,.18)}
.sline{height:2px;width:38px;background:var(--border);flex-shrink:0}
.sline.done{background:var(--ok)}
@media(max-width:560px){.sline{width:20px}.sdot{width:30px;height:30px;font-size:12px}}
.steps-caption{text-align:center;font-size:12.5px;font-weight:700;color:var(--pd)}
.steps-caption span{color:var(--muted);font-weight:500}
/* actions */
.actions{display:flex;gap:12px;margin-top:8px;flex-wrap:wrap}
.nav-btn{border:1.5px solid var(--border);border-radius:14px;padding:16px 24px;background:#fff;color:var(--text);font-size:14px;font-weight:700;font-family:inherit;cursor:pointer;transition:all .13s;white-space:nowrap}
.nav-btn:hover{border-color:var(--p);color:var(--p)}
.submit-btn{flex:1;border:none;border-radius:14px;padding:16px;background:linear-gradient(135deg,var(--p),var(--pd));color:#fff;font-size:16px;font-weight:700;font-family:inherit;cursor:pointer;box-shadow:0 10px 24px rgba(99,102,241,.35);transition:transform .12s,box-shadow .12s}
.submit-btn:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(99,102,241,.45)}
.submit-btn:disabled{opacity:.6;cursor:not-allowed;transform:none}
.draft-btn{border:1.5px solid var(--border);border-radius:14px;padding:16px 22px;background:#fff;color:var(--muted);font-size:14px;font-weight:700;font-family:inherit;cursor:pointer;transition:all .13s;white-space:nowrap}
.draft-btn:hover{border-color:var(--p);color:var(--p)}
.err-note{display:none;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:12px;padding:12px 16px;font-size:13.5px;font-weight:600;margin-top:14px}
.footer{text-align:center;margin-top:22px;font-size:12px;color:#94a3b8}
/* toast */
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);background:#1e293b;color:#fff;padding:12px 22px;border-radius:12px;font-size:13.5px;font-weight:600;box-shadow:0 12px 30px rgba(15,23,42,.3);transition:transform .3s;z-index:200}
.toast.show{transform:translateX(-50%) translateY(0)}
/* resume modal */
.overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:150;align-items:center;justify-content:center;padding:16px}
.overlay.show{display:flex}
.mini-modal{background:#fff;border-radius:18px;max-width:440px;width:100%;padding:28px;text-align:center}
.mini-modal h3{font-size:17px;font-weight:800;margin-bottom:8px}
.mini-modal p{font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:14px}
.mini-modal .link-box{background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;padding:10px 14px;font-size:12px;color:var(--pd);word-break:break-all;margin-bottom:14px}
.mini-modal button{border:none;border-radius:12px;padding:11px 24px;background:linear-gradient(135deg,var(--p),var(--pd));color:#fff;font-size:13.5px;font-weight:700;font-family:inherit;cursor:pointer}
</style>
</head>
<body>
<div class="wrap">
    <?php if ($company) { ?><div class="brand"><?php echo html_escape($company); ?></div><?php } ?>
    <div class="card">
        <div class="head">
            <h1><?php echo html_escape($form->title); ?></h1>
            <?php if ($form->description) { ?><p><?php echo nl2br(html_escape($form->description)); ?></p><?php } ?>
        </div>

        <?php if ($assignment) { ?>
            <div class="assign-bar <?php echo $is_overdue ? 'overdue' : ''; ?>">
                <?php if ($assignment->name) { ?><span><i class="fa-regular fa-user"></i><?php echo html_escape($assignment->name); ?></span><?php } ?>
                <?php if ($assignment->email) { ?><span><i class="fa-regular fa-envelope"></i><?php echo html_escape($assignment->email); ?></span><?php } ?>
                <?php if ($assignment->due_date) { ?>
                    <span><i class="fa-regular fa-clock"></i><?php echo $is_overdue ? 'Was due' : 'Due'; ?> <?php echo date('d M Y, h:i A', strtotime($assignment->due_date)); ?><?php echo $is_overdue ? ' — please complete as soon as possible' : ''; ?></span>
                <?php } ?>
                <?php if ($assignment->draft_saved_at) { ?><span><i class="fa-regular fa-floppy-disk"></i>Draft restored</span><?php } ?>
            </div>
        <?php } ?>

        <?php if ($form->show_progress) { ?>
            <div class="progress-wrap">
                <div class="progress-track"><div class="progress-fill" id="sf-progress"></div></div>
                <div class="progress-num" id="sf-progress-num">0%</div>
            </div>
        <?php } ?>

        <form method="post" action="" id="sf-form" novalidate <?php echo $has_files ? 'enctype="multipart/form-data"' : ''; ?>>
            <div class="body">

                <?php if ($form->layout === 'steps') { ?>
                    <div class="steps-ind" id="sf-steps-ind" style="display:none">
                        <div class="steps-track" id="sf-steps-track"></div>
                        <div class="steps-caption" id="sf-steps-caption"></div>
                    </div>
                <?php } ?>

                <?php
                // Assigned links already know who the respondent is (shown in
                // the bar above; the server trusts the assignment identity on
                // submit) — only ask unassigned/anonymous visitors.
                if ($form->require_identity && !($assignment && $assignment->name)) { ?>
                    <div class="identity">
                        <h4><i class="fa-regular fa-user" style="margin-right:6px"></i>Your details</h4>
                        <div class="row2">
                            <div>
                                <label>Full name *</label>
                                <input type="text" name="respondent_name" required data-sf-track value="<?php echo html_escape($assignment && $assignment->name ? $assignment->name : ''); ?>">
                            </div>
                            <div>
                                <label>Email *</label>
                                <input type="email" name="respondent_email" required data-sf-track value="<?php echo html_escape($assignment && $assignment->email ? $assignment->email : ''); ?>">
                            </div>
                        </div>
                    </div>
                <?php } ?>

                <?php foreach ($form->questions as $q) {
                    $settings = $q->settings ? (json_decode($q->settings, true) ?: []) : [];
                    $options  = $q->options ? (json_decode($q->options, true) ?: []) : [];
                    $qid      = 'q_' . $q->id;
                    $req      = (int) $q->is_required;

                    if ($q->question_type === 'section') { ?>
                        <div class="section-h">
                            <h3><?php echo html_escape($q->label); ?></h3>
                            <?php if ($q->description) { ?><p><?php echo nl2br(html_escape($q->description)); ?></p><?php } ?>
                        </div>
                        <?php continue;
                    } ?>

                    <div class="q" data-qid="<?php echo $qid; ?>" data-required="<?php echo $req; ?>" data-type="<?php echo $q->question_type; ?>">
                        <div class="q-label"><?php echo html_escape($q->label); ?><?php if ($req) { ?><span class="req">*</span><?php } ?></div>
                        <?php if ($q->description) { ?><div class="q-desc"><?php echo nl2br(html_escape($q->description)); ?></div><?php } ?>
                        <div class="control">

                        <?php if ($q->question_type === 'short_text') { ?>
                            <input type="text" name="<?php echo $qid; ?>" data-sf-track <?php echo $req ? 'required' : ''; ?>>

                        <?php } elseif ($q->question_type === 'long_text') { ?>
                            <textarea name="<?php echo $qid; ?>" data-sf-track <?php echo $req ? 'required' : ''; ?>></textarea>

                        <?php } elseif ($q->question_type === 'number') { ?>
                            <input type="number" name="<?php echo $qid; ?>" data-sf-track <?php echo $req ? 'required' : ''; ?>>

                        <?php } elseif ($q->question_type === 'date') { ?>
                            <input type="date" name="<?php echo $qid; ?>" data-sf-track <?php echo $req ? 'required' : ''; ?>>

                        <?php } elseif ($q->question_type === 'yes_no') { ?>
                            <div class="pills" data-pill-group="<?php echo $qid; ?>">
                                <div class="pill" data-value="Yes" data-style="ok"><i class="fa-solid fa-check"></i>Yes</div>
                                <div class="pill" data-value="No" data-style="bad"><i class="fa-solid fa-xmark"></i>No</div>
                            </div>
                            <input type="hidden" name="<?php echo $qid; ?>" data-sf-track>

                        <?php } elseif ($q->question_type === 'true_false') { ?>
                            <div class="pills" data-pill-group="<?php echo $qid; ?>">
                                <div class="pill" data-value="True" data-style="ok"><i class="fa-solid fa-check"></i>True</div>
                                <div class="pill" data-value="False" data-style="bad"><i class="fa-solid fa-xmark"></i>False</div>
                            </div>
                            <input type="hidden" name="<?php echo $qid; ?>" data-sf-track>

                        <?php } elseif ($q->question_type === 'terms') { ?>
                            <?php if (!empty($settings['terms_text'])) { ?>
                                <div class="terms-box"><?php echo html_escape($settings['terms_text']); ?></div>
                            <?php } ?>
                            <div class="pills" data-pill-group="<?php echo $qid; ?>">
                                <div class="pill" data-value="Accepted" data-style="ok"><i class="fa-solid fa-circle-check"></i>Accept</div>
                                <div class="pill" data-value="Declined" data-style="bad"><i class="fa-solid fa-circle-xmark"></i>Decline</div>
                            </div>
                            <input type="hidden" name="<?php echo $qid; ?>" data-sf-track <?php echo !empty($settings['must_accept']) ? 'data-must-accept="1"' : ''; ?>>

                        <?php } elseif ($q->question_type === 'rating') {
                            $max = !empty($settings['max_stars']) ? (int) $settings['max_stars'] : 5; ?>
                            <div class="stars" data-star-group="<?php echo $qid; ?>">
                                <?php for ($i = 1; $i <= $max; $i++) { ?><i class="fa-solid fa-star" data-value="<?php echo $i; ?>"></i><?php } ?>
                            </div>
                            <input type="hidden" name="<?php echo $qid; ?>" data-sf-track>

                        <?php } elseif ($q->question_type === 'scale') { ?>
                            <div class="scale" data-scale-group="<?php echo $qid; ?>">
                                <?php for ($i = 1; $i <= 10; $i++) { ?><button type="button" data-value="<?php echo $i; ?>"><?php echo $i; ?></button><?php } ?>
                            </div>
                            <div class="scale-labels">
                                <span><?php echo html_escape(isset($settings['min_label']) ? $settings['min_label'] : ''); ?></span>
                                <span><?php echo html_escape(isset($settings['max_label']) ? $settings['max_label'] : ''); ?></span>
                            </div>
                            <input type="hidden" name="<?php echo $qid; ?>" data-sf-track>

                        <?php } elseif ($q->question_type === 'radio') { ?>
                            <?php foreach ($options as $opt) { ?>
                                <label class="opt"><input type="radio" name="<?php echo $qid; ?>" value="<?php echo html_escape($opt); ?>" data-sf-track><span><?php echo html_escape($opt); ?></span></label>
                            <?php } ?>

                        <?php } elseif ($q->question_type === 'checkbox') { ?>
                            <?php foreach ($options as $opt) { ?>
                                <label class="opt"><input type="checkbox" name="<?php echo $qid; ?>[]" value="<?php echo html_escape($opt); ?>" data-sf-track><span><?php echo html_escape($opt); ?></span></label>
                            <?php } ?>

                        <?php } elseif ($q->question_type === 'dropdown') { ?>
                            <select name="<?php echo $qid; ?>" data-sf-track <?php echo $req ? 'required' : ''; ?>>
                                <option value="">— Select —</option>
                                <?php foreach ($options as $opt) { ?>
                                    <option value="<?php echo html_escape($opt); ?>"><?php echo html_escape($opt); ?></option>
                                <?php } ?>
                            </select>

                        <?php } elseif ($q->question_type === 'file') {
                            $max_files = !empty($settings['max_files']) ? (int) $settings['max_files'] : 3; ?>
                            <div class="dropzone" data-dz="<?php echo $qid; ?>">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <div class="dz-title">Click to choose file<?php echo $max_files > 1 ? 's' : ''; ?> or drag &amp; drop</div>
                                <div class="dz-sub">Up to <?php echo $max_files; ?> file<?php echo $max_files > 1 ? 's' : ''; ?> · 10 MB each · images, PDF, Office, CSV, TXT, ZIP</div>
                            </div>
                            <input type="file" name="<?php echo $qid; ?>[]" data-max-files="<?php echo $max_files; ?>" <?php echo $max_files > 1 ? 'multiple' : ''; ?> style="display:none" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip,.ppt,.pptx">
                            <div class="file-list" data-fl="<?php echo $qid; ?>"></div>

                        <?php } elseif ($q->question_type === 'credentials') { ?>
                            <div class="cred" data-cred="<?php echo $qid; ?>">
                                <div class="cred-row">
                                    <i class="lead fa-regular fa-user"></i>
                                    <input type="text" name="<?php echo $qid; ?>_user" placeholder="Username / login ID" autocomplete="off" data-sf-track>
                                </div>
                                <div class="cred-row">
                                    <i class="lead fa-solid fa-key"></i>
                                    <input type="password" name="<?php echo $qid; ?>_pass" placeholder="Password" autocomplete="new-password" data-sf-track>
                                    <button type="button" class="toggle-pw" tabindex="-1"><i class="fa-regular fa-eye"></i></button>
                                </div>
                                <div class="cred-note"><i class="fa-solid fa-lock"></i>Transmitted securely and stored encrypted. Only authorized staff can view it.</div>
                            </div>
                        <?php } ?>

                        </div>
                    </div>
                <?php } ?>

                <input type="hidden" name="duration_seconds" id="sf-duration" value="">
                <?php if ($assignment) { ?><input type="hidden" name="_a" id="sf-a-token" value="<?php echo html_escape($assignment->token); ?>"><?php } else { ?><input type="hidden" name="_a" id="sf-a-token" value=""><?php } ?>
                <div class="err-note" id="sf-err"><i class="fa-solid fa-triangle-exclamation" style="margin-right:7px"></i><span id="sf-err-text">Please answer all required questions.</span></div>
                <div class="actions">
                    <button type="button" class="draft-btn" id="sf-draft-btn"><i class="fa-regular fa-floppy-disk" style="margin-right:7px"></i>Save draft</button>
                    <button type="button" class="nav-btn" id="sf-back-btn" style="display:none"><i class="fa-solid fa-arrow-left" style="margin-right:7px"></i>Back</button>
                    <button type="button" class="submit-btn" id="sf-next-btn" style="display:none">Next<i class="fa-solid fa-arrow-right" style="margin-left:8px"></i></button>
                    <button type="submit" class="submit-btn" id="sf-submit"><i class="fa-solid fa-paper-plane" style="margin-right:8px"></i>Submit</button>
                </div>
            </div>
        </form>
    </div>
    <div class="footer">Powered by <?php echo html_escape($company ?: 'HealthO'); ?> · Smart Forms</div>
</div>

<div class="toast" id="sf-toast"></div>

<!-- Resume-link modal (anonymous drafts) -->
<div class="overlay" id="sf-resume-overlay">
    <div class="mini-modal">
        <h3><i class="fa-regular fa-floppy-disk" style="color:var(--p);margin-right:8px"></i>Draft saved!</h3>
        <p>Keep this personal link safe — open it any time to continue exactly where you left off.</p>
        <div class="link-box" id="sf-resume-link"></div>
        <button type="button" onclick="sfCopyResume()">Copy link &amp; continue</button>
    </div>
</div>

<script>
(function () {
    var startedAt = Date.now();
    var formEl = document.getElementById('sf-form');
    var SF_TOKEN = <?php echo json_encode($form->share_token); ?>;
    var SF_DRAFT_URL = <?php echo json_encode(site_url('smart_forms/smart_forms_public/save_draft')); ?>;
    var SF_DRAFT = <?php echo json_encode($draft, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?> || {};

    /* ── Pill groups (yes/no, true/false, terms) ── */
    document.querySelectorAll('[data-pill-group]').forEach(function (group) {
        var input = group.parentNode.querySelector('input[type=hidden]');
        function select(value) {
            group.querySelectorAll('.pill').forEach(function (p) {
                p.classList.remove('sel-ok', 'sel-bad');
                if (p.getAttribute('data-value') === value) {
                    p.classList.add(p.getAttribute('data-style') === 'ok' ? 'sel-ok' : 'sel-bad');
                }
            });
            input.value = value;
        }
        group.sfSelect = select;
        group.querySelectorAll('.pill').forEach(function (pill) {
            pill.addEventListener('click', function () { select(pill.getAttribute('data-value')); update(); });
        });
    });

    /* ── Star ratings ── */
    document.querySelectorAll('[data-star-group]').forEach(function (group) {
        var input = group.parentNode.querySelector('input[type=hidden]');
        var stars = group.querySelectorAll('i');
        function paint(n) { stars.forEach(function (s, i) { s.classList.toggle('on', i < n); }); }
        group.sfPaint = paint;
        stars.forEach(function (star, idx) {
            star.addEventListener('click', function () { input.value = idx + 1; paint(idx + 1); update(); });
            star.addEventListener('mouseenter', function () { paint(idx + 1); });
        });
        group.addEventListener('mouseleave', function () { paint(parseInt(input.value || 0, 10)); });
    });

    /* ── Scales ── */
    document.querySelectorAll('[data-scale-group]').forEach(function (group) {
        var input = group.parentNode.querySelector('input[type=hidden]');
        function select(v) {
            group.querySelectorAll('button').forEach(function (b) { b.classList.toggle('sel', b.getAttribute('data-value') === String(v)); });
            input.value = v;
        }
        group.sfSelect = select;
        group.querySelectorAll('button').forEach(function (btn) {
            btn.addEventListener('click', function () { select(btn.getAttribute('data-value')); update(); });
        });
    });

    /* ── Option highlight ── */
    document.querySelectorAll('.opt input').forEach(function (inp) {
        inp.addEventListener('change', function () {
            document.querySelectorAll('.opt input[name="' + CSS.escape(inp.name) + '"]').forEach(function (other) {
                other.closest('.opt').classList.toggle('sel', other.checked);
            });
            update();
        });
    });

    /* ── File dropzones ── */
    document.querySelectorAll('[data-dz]').forEach(function (dz) {
        var qid = dz.getAttribute('data-dz');
        var input = dz.parentNode.querySelector('input[type=file]');
        var list = dz.parentNode.querySelector('[data-fl="' + qid + '"]');
        var maxFiles = parseInt(input.getAttribute('data-max-files'), 10) || 3;
        var picked = [];

        function sync() {
            var dt = new DataTransfer();
            picked.forEach(function (f) { dt.items.add(f); });
            input.files = dt.files;
            list.innerHTML = picked.map(function (f, i) {
                var kb = f.size > 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.ceil(f.size / 1024) + ' KB';
                return '<div class="file-item"><i class="fa-solid fa-paperclip"></i><span>' + f.name.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</span><span class="fsize">' + kb + '</span><button type="button" data-rm="' + i + '"><i class="fa-solid fa-xmark"></i></button></div>';
            }).join('');
            update();
        }

        function addFiles(files) {
            Array.prototype.forEach.call(files, function (f) {
                if (picked.length >= maxFiles) { toast('Maximum ' + maxFiles + ' file(s) allowed.'); return; }
                if (f.size > 10485760) { toast('"' + f.name + '" exceeds the 10 MB limit.'); return; }
                picked.push(f);
            });
            sync();
        }

        dz.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () { addFiles(input.files); });
        dz.addEventListener('dragover', function (e) { e.preventDefault(); dz.classList.add('drag'); });
        dz.addEventListener('dragleave', function () { dz.classList.remove('drag'); });
        dz.addEventListener('drop', function (e) { e.preventDefault(); dz.classList.remove('drag'); addFiles(e.dataTransfer.files); });
        list.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-rm]');
            if (btn) { picked.splice(parseInt(btn.getAttribute('data-rm'), 10), 1); sync(); }
        });
    });

    /* ── Credentials password toggle ── */
    document.querySelectorAll('.toggle-pw').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var inp = btn.parentNode.querySelector('input');
            var show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            btn.innerHTML = show ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
        });
    });

    document.querySelectorAll('[data-sf-track]').forEach(function (el) {
        el.addEventListener('input', update);
        el.addEventListener('change', update);
    });

    /* ── Answered check + progress ── */
    function isAnswered(q) {
        var qid = q.getAttribute('data-qid');
        var type = q.getAttribute('data-type');
        if (type === 'checkbox') return q.querySelectorAll('input[type=checkbox]:checked').length > 0;
        if (type === 'radio') return q.querySelectorAll('input[type=radio]:checked').length > 0;
        if (type === 'file') { var fi = q.querySelector('input[type=file]'); return !!(fi && fi.files.length); }
        if (type === 'credentials') {
            var u = q.querySelector('[name="' + CSS.escape(qid) + '_user"]');
            var p = q.querySelector('[name="' + CSS.escape(qid) + '_pass"]');
            return !!(u && p && u.value.trim() && p.value);
        }
        var el = q.querySelector('[name="' + CSS.escape(qid) + '"]');
        return !!(el && String(el.value || '').trim() !== '');
    }

    function progressPct() {
        var qs = Array.prototype.slice.call(document.querySelectorAll('.q'));
        if (!qs.length) return 0;
        return Math.round(qs.filter(isAnswered).length / qs.length * 100);
    }

    function update() {
        var bar = document.getElementById('sf-progress');
        if (bar) {
            var pct = progressPct();
            bar.style.width = pct + '%';
            document.getElementById('sf-progress-num').textContent = pct + '%';
        }
    }

    /* ── Draft: collect / restore / save ── */
    function collectDraft() {
        var out = {};
        document.querySelectorAll('.q').forEach(function (q) {
            var qid = q.getAttribute('data-qid');
            var type = q.getAttribute('data-type');
            if (type === 'file') return; // files upload on final submit only
            if (type === 'credentials') {
                var u = q.querySelector('[name="' + CSS.escape(qid) + '_user"]');
                if (u && u.value.trim()) out[qid + '_user'] = u.value; // never draft the password
                return;
            }
            if (type === 'checkbox') {
                var vals = Array.prototype.map.call(q.querySelectorAll('input:checked'), function (c) { return c.value; });
                if (vals.length) out[qid] = vals;
                return;
            }
            if (type === 'radio') {
                var r = q.querySelector('input:checked');
                if (r) out[qid] = r.value;
                return;
            }
            var el = q.querySelector('[name="' + CSS.escape(qid) + '"]');
            if (el && String(el.value || '').trim() !== '') out[qid] = el.value;
        });
        var rn = document.querySelector('[name=respondent_name]');
        var re = document.querySelector('[name=respondent_email]');
        if (rn && rn.value.trim()) out.respondent_name = rn.value;
        if (re && re.value.trim()) out.respondent_email = re.value;
        return out;
    }

    function restoreDraft() {
        Object.keys(SF_DRAFT).forEach(function (key) {
            var val = SF_DRAFT[key];
            if (key === 'respondent_name' || key === 'respondent_email') {
                var idEl = document.querySelector('[name="' + key + '"]');
                if (idEl && !idEl.value) idEl.value = val;
                return;
            }
            var q = document.querySelector('.q[data-qid="' + CSS.escape(key.replace(/_user$/, '')) + '"]');
            if (!q) return;
            var type = q.getAttribute('data-type');

            if (/_user$/.test(key) && type === 'credentials') {
                var u = q.querySelector('[name="' + CSS.escape(key) + '"]');
                if (u) u.value = val;
                return;
            }
            if (type === 'checkbox' && Array.isArray(val)) {
                val.forEach(function (v) {
                    var c = q.querySelector('input[value="' + CSS.escape(v) + '"]');
                    if (c) { c.checked = true; c.closest('.opt').classList.add('sel'); }
                });
                return;
            }
            if (type === 'radio') {
                var r = q.querySelector('input[value="' + CSS.escape(String(val)) + '"]');
                if (r) { r.checked = true; r.closest('.opt').classList.add('sel'); }
                return;
            }
            if (type === 'yes_no' || type === 'true_false' || type === 'terms') {
                var pg = q.querySelector('[data-pill-group]');
                if (pg && pg.sfSelect) pg.sfSelect(String(val));
                return;
            }
            if (type === 'rating') {
                var sg = q.querySelector('[data-star-group]');
                var inp = q.querySelector('input[type=hidden]');
                if (sg && inp) { inp.value = val; sg.sfPaint(parseInt(val, 10) || 0); }
                return;
            }
            if (type === 'scale') {
                var sc = q.querySelector('[data-scale-group]');
                if (sc && sc.sfSelect) sc.sfSelect(String(val));
                return;
            }
            var el = q.querySelector('[name="' + CSS.escape(key) + '"]');
            if (el) el.value = val;
        });
        update();
    }

    var draftBtn = document.getElementById('sf-draft-btn');
    draftBtn.addEventListener('click', function () {
        draftBtn.disabled = true;
        var fd = new FormData();
        fd.append('token', SF_TOKEN);
        fd.append('a', document.getElementById('sf-a-token').value);
        fd.append('answers', JSON.stringify(collectDraft()));
        fd.append('progress', progressPct());
        var rn = document.querySelector('[name=respondent_name]');
        var re = document.querySelector('[name=respondent_email]');
        if (rn) fd.append('respondent_name', rn.value);
        if (re) fd.append('respondent_email', re.value);

        fetch(SF_DRAFT_URL, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                draftBtn.disabled = false;
                if (!res.success) { toast('Could not save draft. Please try again.'); return; }
                document.getElementById('sf-a-token').value = res.a;
                if (window.history && history.replaceState) {
                    history.replaceState(null, '', '?a=' + res.a);
                }
                if (res.created) {
                    document.getElementById('sf-resume-link').textContent = res.resume_url;
                    document.getElementById('sf-resume-overlay').classList.add('show');
                } else {
                    toast('Draft saved — you can safely close this page.');
                }
            })
            .catch(function () { draftBtn.disabled = false; toast('Could not save draft. Please try again.'); });
    });

    window.sfCopyResume = function () {
        var url = document.getElementById('sf-resume-link').textContent;
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(url); }
        document.getElementById('sf-resume-overlay').classList.remove('show');
        toast('Link copied — draft saved.');
    };

    var toastTimer = null;
    function toast(msg) {
        var t = document.getElementById('sf-toast');
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { t.classList.remove('show'); }, 3200);
    }

    /* ── Multi-step wizard (each section header starts a step) ── */
    var wizard = null;
    <?php if ($form->layout === 'steps') { ?>
    (function () {
        var els = Array.prototype.slice.call(document.querySelectorAll('#sf-form .body > .section-h, #sf-form .body > .q'));
        var steps = [];
        els.forEach(function (el) {
            if (el.classList.contains('section-h')) {
                steps.push({ title: (el.querySelector('h3') || {}).textContent || '', els: [el] });
            } else {
                if (!steps.length) steps.push({ title: '', els: [] });
                steps[steps.length - 1].els.push(el);
            }
        });
        if (steps.length < 2) return; // nothing to wizard-ify

        var current = 0;
        var track = document.getElementById('sf-steps-track');
        var caption = document.getElementById('sf-steps-caption');
        var backBtn = document.getElementById('sf-back-btn');
        var nextBtn = document.getElementById('sf-next-btn');
        var submitBtn = document.getElementById('sf-submit');
        document.getElementById('sf-steps-ind').style.display = '';

        function renderIndicator() {
            var html = '';
            steps.forEach(function (s, i) {
                if (i > 0) html += '<div class="sline' + (i <= current ? ' done' : '') + '"></div>';
                var cls = i < current ? 'done' : (i === current ? 'active' : '');
                html += '<div class="sdot ' + cls + '" data-step="' + i + '">' + (i < current ? '<i class="fa-solid fa-check"></i>' : (i + 1)) + '</div>';
            });
            track.innerHTML = html;
            var title = steps[current].title;
            caption.innerHTML = '<span>Step ' + (current + 1) + ' of ' + steps.length + (title ? ' · </span>' + title.replace(/&/g, '&amp;').replace(/</g, '&lt;') : '</span>');
        }

        function showStep(i) {
            current = Math.max(0, Math.min(i, steps.length - 1));
            steps.forEach(function (s, idx) {
                s.els.forEach(function (el) { el.style.display = idx === current ? '' : 'none'; });
            });
            backBtn.style.display = current > 0 ? '' : 'none';
            nextBtn.style.display = current < steps.length - 1 ? '' : 'none';
            submitBtn.style.display = current === steps.length - 1 ? '' : 'none';
            document.getElementById('sf-err').style.display = 'none';
            renderIndicator();
            var card = document.querySelector('.card');
            if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function validateStep(i) {
            var firstBad = null;
            var errText = 'Please answer all required questions on this step.';
            steps[i].els.forEach(function (el) {
                if (!el.classList.contains('q')) return;
                el.classList.remove('invalid');
                if (el.getAttribute('data-required') === '1' && !isAnswered(el)) {
                    el.classList.add('invalid');
                    if (!firstBad) firstBad = el;
                }
                var ma = el.querySelector('input[data-must-accept]');
                if (ma && ma.value && ma.value !== 'Accepted') {
                    el.classList.add('invalid');
                    if (!firstBad) { firstBad = el; errText = 'You must accept the terms and conditions to continue.'; }
                }
            });
            if (firstBad) {
                var err = document.getElementById('sf-err');
                document.getElementById('sf-err-text').textContent = errText;
                err.style.display = 'block';
                firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
            return true;
        }

        nextBtn.addEventListener('click', function () { if (validateStep(current)) showStep(current + 1); });
        backBtn.addEventListener('click', function () { showStep(current - 1); });
        track.addEventListener('click', function (e) {
            var dot = e.target.closest('.sdot');
            if (dot && dot.classList.contains('done')) showStep(parseInt(dot.getAttribute('data-step'), 10));
        });

        wizard = {
            stepOf: function (el) {
                for (var i = 0; i < steps.length; i++) {
                    if (steps[i].els.indexOf(el) !== -1) return i;
                    // identity block lives outside steps — treat as step 0
                }
                return el.classList.contains('identity') ? 0 : current;
            },
            show: showStep
        };

        showStep(0);
    })();
    <?php } ?>

    /* ── Submit validation ── */
    formEl.addEventListener('submit', function (e) {
        var firstBad = null;
        var errText = 'Please answer all required questions.';

        document.querySelectorAll('.q').forEach(function (q) { q.classList.remove('invalid'); });

        document.querySelectorAll('.q[data-required="1"]').forEach(function (q) {
            if (!isAnswered(q)) {
                q.classList.add('invalid');
                if (!firstBad) firstBad = q;
            }
        });

        document.querySelectorAll('input[data-must-accept]').forEach(function (inp) {
            if (inp.value && inp.value !== 'Accepted') {
                var q = inp.closest('.q');
                q.classList.add('invalid');
                if (!firstBad) { firstBad = q; errText = 'You must accept the terms and conditions to continue.'; }
            }
        });

        document.querySelectorAll('.identity input[required]').forEach(function (inp) {
            if (!String(inp.value).trim()) {
                if (!firstBad) firstBad = inp.closest('.identity');
                inp.style.borderColor = 'var(--bad)';
            } else {
                inp.style.borderColor = '';
            }
        });

        if (firstBad) {
            e.preventDefault();
            if (wizard) wizard.show(wizard.stepOf(firstBad));
            var err = document.getElementById('sf-err');
            document.getElementById('sf-err-text').textContent = errText;
            err.style.display = 'block';
            firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }

        document.getElementById('sf-duration').value = Math.round((Date.now() - startedAt) / 1000);
        document.getElementById('sf-submit').disabled = true;
    });

    restoreDraft();
    update();
})();
</script>
</body>
</html>
