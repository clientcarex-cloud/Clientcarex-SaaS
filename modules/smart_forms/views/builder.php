<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$is_edit = isset($form);
$questions_js = [];
if ($is_edit) {
    foreach ($form->questions as $q) {
        $questions_js[] = [
            'question_type' => $q->question_type,
            'label'         => $q->label,
            'description'   => $q->description ?: '',
            'is_required'   => (int) $q->is_required,
            'options'       => $q->options ? (json_decode($q->options, true) ?: []) : [],
            'settings'      => $q->settings ? (json_decode($q->settings, true) ?: new stdClass()) : new stdClass(),
        ];
    }
}
?>
<style>
.sfb{--sf-primary:#6366f1;--sf-primary-dark:#4f46e5;--sf-success:#10b981;--sf-warning:#f59e0b;--sf-danger:#ef4444;--sf-text:#1e293b;--sf-muted:#64748b;--sf-border:#e2e8f0;--sf-shadow:0 1px 3px rgba(15,23,42,.08);--sf-shadow-lg:0 12px 32px rgba(15,23,42,.14)}
.sfb .sf-card{background:#fff;border:1px solid var(--sf-border);border-radius:14px;box-shadow:var(--sf-shadow);margin-bottom:16px}
.sfb .sf-card-head{padding:14px 20px;border-bottom:1px solid var(--sf-border);font-weight:700;color:var(--sf-text);display:flex;align-items:center;justify-content:space-between}
.sfb .sf-card-body{padding:18px 20px}
.sfb label{font-size:12px;font-weight:600;color:var(--sf-muted);text-transform:uppercase;letter-spacing:.3px;margin-bottom:5px;display:block}
.sfb .sf-input,.sfb .sf-select,.sfb .sf-textarea{width:100%;border:1px solid var(--sf-border);border-radius:9px;padding:9px 12px;font-size:13.5px;color:var(--sf-text);background:#fff;transition:border-color .15s;outline:none}
.sfb .sf-input:focus,.sfb .sf-select:focus,.sfb .sf-textarea:focus{border-color:var(--sf-primary);box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.sfb .sf-row{display:flex;gap:14px}.sfb .sf-row>*{flex:1}
.sfb .sf-field{margin-bottom:14px}
.sfb .sf-check{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--sf-text);cursor:pointer;margin-bottom:9px}
.sfb .sf-check input{margin:0;width:15px;height:15px;accent-color:var(--sf-primary)}
.sfb .sf-check span{font-weight:500}
.sf-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;border:1px solid transparent;cursor:pointer;text-decoration:none!important;transition:all .15s}
.sf-btn-primary{background:linear-gradient(135deg,var(--sf-primary),var(--sf-primary-dark));color:#fff!important;box-shadow:0 4px 12px rgba(99,102,241,.3)}
.sf-btn-primary:hover{transform:translateY(-1px);color:#fff}
.sf-btn-outline{background:#fff;border-color:var(--sf-border);color:var(--sf-text)!important}
.sf-btn-outline:hover{border-color:var(--sf-primary);color:var(--sf-primary)!important}
.sf-btn-sm{padding:6px 12px;font-size:12px;border-radius:8px}
/* question cards */
.sf-q{background:#fff;border:1px solid var(--sf-border);border-left:4px solid var(--sf-primary);border-radius:12px;box-shadow:var(--sf-shadow);margin-bottom:12px;overflow:hidden}
.sf-q.sf-q-section{border-left-color:var(--sf-warning)}
.sf-q-head{display:flex;align-items:center;gap:10px;padding:11px 14px;background:#fafbfc;border-bottom:1px solid var(--sf-border)}
.sf-q-type{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:var(--sf-primary);background:#eef2ff;padding:4px 10px;border-radius:20px;white-space:nowrap}
.sf-q-title-preview{flex:1;font-size:13px;font-weight:600;color:var(--sf-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sf-q-tools{display:flex;gap:4px}
.sf-q-tools button{border:none;background:transparent;color:var(--sf-muted);width:28px;height:28px;border-radius:7px;cursor:pointer}
.sf-q-tools button:hover{background:#eef2ff;color:var(--sf-primary)}
.sf-q-tools button.sf-del:hover{background:#fef2f2;color:var(--sf-danger)}
.sf-q-body{padding:14px}
.sf-req-pill{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:600;color:var(--sf-muted);cursor:pointer;border:1px solid var(--sf-border);padding:4px 10px;border-radius:20px;user-select:none}
.sf-req-pill.on{color:#fff;background:var(--sf-danger);border-color:var(--sf-danger)}
/* palette */
.sf-palette{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px}
.sf-palette button{display:flex;align-items:center;gap:8px;border:1px solid var(--sf-border);background:#fff;border-radius:10px;padding:9px 12px;font-size:12.5px;font-weight:600;color:var(--sf-text);cursor:pointer;transition:all .12s;text-align:left}
.sf-palette button:hover{border-color:var(--sf-primary);color:var(--sf-primary);background:#eef2ff}
.sf-palette button i{width:16px;text-align:center;color:var(--sf-primary)}
.sf-presets{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.sf-presets button{border:1px dashed var(--sf-primary);background:#eef2ff;color:var(--sf-primary);border-radius:20px;padding:6px 14px;font-size:12px;font-weight:600;cursor:pointer}
.sf-presets button:hover{background:var(--sf-primary);color:#fff}
.sf-share-box{background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;padding:10px 14px;font-size:12.5px;color:var(--sf-primary-dark);word-break:break-all}
.sf-empty-q{border:2px dashed var(--sf-border);border-radius:12px;padding:34px;text-align:center;color:var(--sf-muted)}
</style>

<div id="wrapper" class="sfb">
    <div class="content">
        <?php echo form_open(uri_string(), ['id' => 'sf-builder-form', 'autocomplete' => 'off']); ?>
            <div class="row">
                <div class="col-md-12" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between">
                    <h3 style="margin:0;font-weight:700;color:var(--sf-text)">
                        <a href="<?php echo admin_url('smart_forms'); ?>" style="color:var(--sf-muted);margin-right:8px"><i class="fa fa-arrow-left"></i></a>
                        <?php echo $is_edit ? 'Edit Form' : 'Create Form'; ?>
                    </h3>
                    <div style="display:flex;gap:8px">
                        <?php if ($is_edit && $form->share_token && $form->status === 'active') { ?>
                            <a href="<?php echo site_url('form/' . $form->share_token); ?>" target="_blank" class="sf-btn sf-btn-outline"><i class="fa-solid fa-arrow-up-right-from-square"></i> Preview</a>
                        <?php } ?>
                        <button type="submit" class="sf-btn sf-btn-primary"><i class="fa fa-check"></i> Save Form</button>
                    </div>
                </div>

                <!-- Left: questions -->
                <div class="col-md-8">
                    <div id="sf-questions"></div>

                    <div class="sf-card">
                        <div class="sf-card-body">
                            <label style="margin-bottom:10px"><i class="fa fa-plus" style="margin-right:6px"></i>Add a question</label>
                            <div class="sf-palette" id="sf-palette">
                                <?php foreach ($question_types as $type => $meta) { ?>
                                    <button type="button" data-type="<?php echo $type; ?>"><i class="<?php echo $meta['icon']; ?>"></i><?php echo $meta['name']; ?></button>
                                <?php } ?>
                            </div>
                            <div id="sf-presets-wrap" style="display:none">
                                <label style="margin-top:16px">Or start from a template</label>
                                <div class="sf-presets">
                                    <button type="button" onclick="sfLoadPreset('onboarding')"><i class="fa-solid fa-user-plus"></i> Employee Onboarding</button>
                                    <button type="button" onclick="sfLoadPreset('process')"><i class="fa-solid fa-diagram-project"></i> Process Implementation</button>
                                    <button type="button" onclick="sfLoadPreset('terms')"><i class="fa-solid fa-file-contract"></i> T&amp;C Acceptance</button>
                                    <button type="button" onclick="sfLoadPreset('training')"><i class="fa-solid fa-graduation-cap"></i> Training Check</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: settings -->
                <div class="col-md-4">
                    <div class="sf-card">
                        <div class="sf-card-head">Form Settings</div>
                        <div class="sf-card-body">
                            <div class="sf-field">
                                <label>Title *</label>
                                <input type="text" name="title" class="sf-input" required value="<?php echo $is_edit ? html_escape($form->title) : ''; ?>" placeholder="e.g. New Employee Onboarding">
                            </div>
                            <div class="sf-field">
                                <label>Description</label>
                                <textarea name="description" class="sf-textarea" rows="2" placeholder="Shown below the title on the public form"><?php echo $is_edit ? html_escape($form->description) : ''; ?></textarea>
                            </div>
                            <div class="sf-row">
                                <div class="sf-field">
                                    <label>Category</label>
                                    <select name="category" class="sf-select">
                                        <?php foreach (['general' => 'General', 'onboarding' => 'Onboarding', 'process' => 'Process Implementation', 'training' => 'Training', 'acknowledgement' => 'Acknowledgement'] as $val => $lbl) { ?>
                                            <option value="<?php echo $val; ?>" <?php echo ($is_edit && $form->category == $val) ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="sf-field">
                                    <label>Status</label>
                                    <select name="status" class="sf-select">
                                        <?php foreach (['draft' => 'Draft', 'active' => 'Active', 'closed' => 'Closed'] as $val => $lbl) { ?>
                                            <option value="<?php echo $val; ?>" <?php echo ($is_edit && $form->status == $val) ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="sf-field">
                                <label>Layout</label>
                                <select name="layout" class="sf-select">
                                    <option value="single" <?php echo ($is_edit && $form->layout === 'single') ? 'selected' : ''; ?>>Single page</option>
                                    <option value="steps" <?php echo ($is_edit && $form->layout === 'steps') ? 'selected' : ''; ?>>Multi-step wizard</option>
                                </select>
                                <p style="font-size:11.5px;color:var(--sf-muted);margin:6px 0 0;text-transform:none;letter-spacing:0"><i class="fa-solid fa-circle-info" style="margin-right:4px"></i>In the wizard, each <b>Section Header</b> starts a new step with Next / Back navigation.</p>
                            </div>
                            <label class="sf-check"><input type="checkbox" name="require_identity" value="1" <?php echo (!$is_edit || $form->require_identity) ? 'checked' : ''; ?>><span>Ask respondent name &amp; email</span></label>
                            <label class="sf-check"><input type="checkbox" name="one_per_user" value="1" <?php echo ($is_edit && $form->one_per_user) ? 'checked' : ''; ?>><span>Limit one submission per person</span></label>
                            <label class="sf-check"><input type="checkbox" name="show_progress" value="1" <?php echo (!$is_edit || $form->show_progress) ? 'checked' : ''; ?>><span>Show progress bar</span></label>
                        </div>
                    </div>

                    <div class="sf-card">
                        <div class="sf-card-head">After Submit</div>
                        <div class="sf-card-body">
                            <label class="sf-check"><input type="checkbox" name="collect_feedback" value="1" id="sf-cf-toggle" <?php echo (!$is_edit || $form->collect_feedback) ? 'checked' : ''; ?>><span>Ask for quick feedback (1–5 + comment)</span></label>
                            <div class="sf-field" id="sf-cf-prompt">
                                <label>Feedback prompt</label>
                                <input type="text" name="feedback_prompt" class="sf-input" value="<?php echo $is_edit ? html_escape($form->feedback_prompt) : ''; ?>" placeholder="How was your experience filling this form?">
                            </div>
                            <div class="sf-field">
                                <label>Success title</label>
                                <input type="text" name="success_title" class="sf-input" value="<?php echo $is_edit ? html_escape($form->success_title) : ''; ?>" placeholder="Thank you!">
                            </div>
                            <div class="sf-field">
                                <label>Success message</label>
                                <textarea name="success_message" class="sf-textarea" rows="2" placeholder="Your response has been recorded."><?php echo $is_edit ? html_escape($form->success_message) : ''; ?></textarea>
                            </div>
                            <div class="sf-field">
                                <label>Redirect URL (optional)</label>
                                <input type="url" name="redirect_url" class="sf-input" value="<?php echo $is_edit ? html_escape($form->redirect_url) : ''; ?>" placeholder="https://...">
                            </div>
                        </div>
                    </div>

                    <div class="sf-card">
                        <div class="sf-card-head">Availability</div>
                        <div class="sf-card-body">
                            <div class="sf-field">
                                <label>Max submissions</label>
                                <input type="number" name="max_submissions" class="sf-input" min="1" value="<?php echo $is_edit ? html_escape($form->max_submissions) : ''; ?>" placeholder="Unlimited">
                            </div>
                            <div class="sf-field">
                                <label>Opens at</label>
                                <input type="datetime-local" name="starts_at" class="sf-input" value="<?php echo ($is_edit && $form->starts_at) ? date('Y-m-d\TH:i', strtotime($form->starts_at)) : ''; ?>">
                            </div>
                            <div class="sf-field">
                                <label>Closes at</label>
                                <input type="datetime-local" name="expires_at" class="sf-input" value="<?php echo ($is_edit && $form->expires_at) ? date('Y-m-d\TH:i', strtotime($form->expires_at)) : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <?php if ($is_edit && $form->share_token) { ?>
                        <div class="sf-card">
                            <div class="sf-card-head">Share</div>
                            <div class="sf-card-body">
                                <div class="sf-share-box" id="sf-share-url"><?php echo site_url('form/' . $form->share_token); ?></div>
                                <button type="button" class="sf-btn sf-btn-outline sf-btn-sm" style="margin-top:10px" onclick="sfCopyShare()"><i class="fa fa-link"></i> Copy link</button>
                                <p style="font-size:11.5px;color:var(--sf-muted);margin:8px 0 0">The form must be <b>Active</b> (and inside its availability window) for the link to work.</p>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <input type="hidden" name="questions" id="sf-questions-json" value="">
        </form>
    </div>
</div>
<?php init_tail(); ?>
<script>
var SF_TYPES = <?php echo json_encode($question_types); ?>;
var sfQuestions = <?php echo json_encode($questions_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?> || [];

var SF_PRESETS = {
    onboarding: [
        { question_type: 'section', label: 'Welcome aboard!', description: 'Please complete this onboarding form so we can set everything up for you.' },
        { question_type: 'short_text', label: 'Full name (as per official documents)', is_required: 1 },
        { question_type: 'date', label: 'Date of joining', is_required: 1 },
        { question_type: 'radio', label: 'Which department are you joining?', is_required: 1, options: ['Administration', 'Clinical', 'Laboratory', 'Pharmacy', 'Nursing', 'Front Office', 'Other'] },
        { question_type: 'yes_no', label: 'Have you received your ID card and system credentials?', is_required: 1 },
        { question_type: 'yes_no', label: 'Have you completed the facility tour?', is_required: 1 },
        { question_type: 'checkbox', label: 'Which documents have you submitted?', options: ['Photo ID', 'Address proof', 'Educational certificates', 'Experience letters', 'Bank details'] },
        { question_type: 'terms', label: 'Employee Code of Conduct', is_required: 1, settings: { terms_text: 'I agree to abide by the organization\'s code of conduct, confidentiality policy and data-protection rules.', must_accept: 1 } },
        { question_type: 'long_text', label: 'Anything you need from us to get started?' }
    ],
    process: [
        { question_type: 'section', label: 'Process Implementation Check', description: 'Confirm your understanding of the new process before it goes live.' },
        { question_type: 'yes_no', label: 'Have you read the new SOP document?', is_required: 1 },
        { question_type: 'true_false', label: 'The new process replaces the previous workflow completely.', is_required: 1 },
        { question_type: 'scale', label: 'How confident are you in executing the new process?', is_required: 1, settings: { min_label: 'Not confident', max_label: 'Very confident' } },
        { question_type: 'radio', label: 'How much training do you still need?', is_required: 1, options: ['None — ready to go', 'A short refresher', 'A full training session'] },
        { question_type: 'terms', label: 'SOP Acknowledgement', is_required: 1, settings: { terms_text: 'I acknowledge that I have read and understood the new standard operating procedure and agree to follow it.', must_accept: 1 } },
        { question_type: 'long_text', label: 'Questions or concerns about the new process?' }
    ],
    terms: [
        { question_type: 'section', label: 'Terms & Conditions', description: 'Please review and respond to the terms below.' },
        { question_type: 'terms', label: 'Terms and Conditions', is_required: 1, settings: { terms_text: 'Paste your full terms and conditions text here...', must_accept: 0 } },
        { question_type: 'short_text', label: 'Full name (signature)', is_required: 1 },
        { question_type: 'date', label: 'Date', is_required: 1 }
    ],
    training: [
        { question_type: 'section', label: 'Training Effectiveness Check', description: 'Quick knowledge and confidence check after the training session.' },
        { question_type: 'rating', label: 'How would you rate the training session?', is_required: 1, settings: { max_stars: 5 } },
        { question_type: 'true_false', label: 'I can apply what was covered without additional help.', is_required: 1 },
        { question_type: 'yes_no', label: 'Would you like a follow-up session?', is_required: 1 },
        { question_type: 'scale', label: 'Rate your understanding of the topic', is_required: 1, settings: { min_label: 'Beginner', max_label: 'Expert' } },
        { question_type: 'long_text', label: 'What should we improve for the next session?' }
    ]
};

function sfEsc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function sfRender() {
    var wrap = document.getElementById('sf-questions');
    document.getElementById('sf-presets-wrap').style.display = sfQuestions.length ? 'none' : '';

    if (!sfQuestions.length) {
        wrap.innerHTML = '<div class="sf-empty-q"><i class="fa-solid fa-wand-magic-sparkles" style="font-size:28px;color:#cbd5e1"></i><p style="margin:10px 0 0">No questions yet — add one below or start from a template.</p></div>';
        return;
    }

    var html = '';
    sfQuestions.forEach(function (q, i) {
        var meta = SF_TYPES[q.question_type] || { name: q.question_type, icon: 'fa fa-question' };
        var isSection = q.question_type === 'section';
        html += '<div class="sf-q' + (isSection ? ' sf-q-section' : '') + '">';
        html += '<div class="sf-q-head">';
        html += '<span class="sf-q-type"><i class="' + meta.icon + '"></i>' + meta.name + '</span>';
        html += '<span class="sf-q-title-preview">' + (sfEsc(q.label) || '<span style="color:#94a3b8">Untitled question</span>') + '</span>';
        if (!isSection) {
            html += '<span class="sf-req-pill' + (q.is_required ? ' on' : '') + '" onclick="sfToggleReq(' + i + ')"><i class="fa fa-asterisk" style="font-size:9px"></i>Required</span>';
        }
        html += '<div class="sf-q-tools">';
        html += '<button type="button" title="Move up" onclick="sfMove(' + i + ',-1)"><i class="fa fa-chevron-up"></i></button>';
        html += '<button type="button" title="Move down" onclick="sfMove(' + i + ',1)"><i class="fa fa-chevron-down"></i></button>';
        html += '<button type="button" title="Duplicate" onclick="sfDup(' + i + ')"><i class="fa fa-copy"></i></button>';
        html += '<button type="button" class="sf-del" title="Delete" onclick="sfDel(' + i + ')"><i class="fa fa-trash"></i></button>';
        html += '</div></div>';
        html += '<div class="sf-q-body">';
        html += '<div class="sf-field"><input type="text" class="sf-input" placeholder="' + (isSection ? 'Section title' : 'Question text') + '" value="' + sfEsc(q.label) + '" oninput="sfSet(' + i + ',\'label\',this.value)"></div>';
        html += '<div class="sf-field"><input type="text" class="sf-input" placeholder="Help text (optional)" value="' + sfEsc(q.description) + '" oninput="sfSet(' + i + ',\'description\',this.value)"></div>';

        if (['radio', 'checkbox', 'dropdown'].indexOf(q.question_type) !== -1) {
            var opts = (q.options || []).join('\n');
            html += '<div class="sf-field"><label>Options — one per line</label><textarea class="sf-textarea" rows="4" oninput="sfSetOptions(' + i + ',this.value)">' + sfEsc(opts) + '</textarea></div>';
        }
        if (q.question_type === 'rating') {
            var ms = (q.settings && q.settings.max_stars) || 5;
            html += '<div class="sf-field" style="max-width:180px"><label>Number of stars</label><select class="sf-select" onchange="sfSetSetting(' + i + ',\'max_stars\',parseInt(this.value))">';
            [3, 5, 10].forEach(function (n) { html += '<option value="' + n + '"' + (ms == n ? ' selected' : '') + '>' + n + ' stars</option>'; });
            html += '</select></div>';
        }
        if (q.question_type === 'scale') {
            var s = q.settings || {};
            html += '<div class="sf-row"><div class="sf-field"><label>Left label (1)</label><input type="text" class="sf-input" value="' + sfEsc(s.min_label || '') + '" placeholder="e.g. Poor" oninput="sfSetSetting(' + i + ',\'min_label\',this.value)"></div>';
            html += '<div class="sf-field"><label>Right label (10)</label><input type="text" class="sf-input" value="' + sfEsc(s.max_label || '') + '" placeholder="e.g. Excellent" oninput="sfSetSetting(' + i + ',\'max_label\',this.value)"></div></div>';
        }
        if (q.question_type === 'file') {
            var mf = (q.settings && q.settings.max_files) || 3;
            html += '<div class="sf-field" style="max-width:200px"><label>Max files</label><select class="sf-select" onchange="sfSetSetting(' + i + ',\'max_files\',parseInt(this.value))">';
            [1, 3, 5, 10].forEach(function (n) { html += '<option value="' + n + '"' + (mf == n ? ' selected' : '') + '>' + n + ' file' + (n > 1 ? 's' : '') + '</option>'; });
            html += '</select></div>';
            html += '<p style="font-size:11.5px;color:var(--sf-muted);margin:0">Allowed: images, PDF, Office docs, CSV/TXT, ZIP · max 10 MB each. Files are stored privately and downloadable only by staff.</p>';
        }
        if (q.question_type === 'credentials') {
            html += '<p style="font-size:11.5px;color:var(--sf-muted);margin:0"><i class="fa-solid fa-lock" style="margin-right:5px"></i>Collects a username + password pair. The password is <b>encrypted at rest</b>, hidden in CSV exports, and only revealed to staff on click.</p>';
        }
        if (q.question_type === 'terms') {
            var t = q.settings || {};
            html += '<div class="sf-field"><label>Terms &amp; conditions text</label><textarea class="sf-textarea" rows="5" placeholder="Paste the terms the respondent must read..." oninput="sfSetSetting(' + i + ',\'terms_text\',this.value)">' + sfEsc(t.terms_text || '') + '</textarea></div>';
            html += '<label class="sf-check"><input type="checkbox"' + (t.must_accept ? ' checked' : '') + ' onchange="sfSetSetting(' + i + ',\'must_accept\',this.checked?1:0)"><span>Must accept to submit (Decline blocks submission)</span></label>';
        }
        html += '</div></div>';
    });
    wrap.innerHTML = html;
}

function sfSet(i, key, val) { sfQuestions[i][key] = val; if (key === 'label') { var p = document.querySelectorAll('.sf-q-title-preview')[i]; if (p) p.textContent = val || 'Untitled question'; } }
function sfSetOptions(i, val) { sfQuestions[i].options = val.split('\n').map(function (o) { return o.trim(); }).filter(Boolean); }
function sfSetSetting(i, key, val) { sfQuestions[i].settings = sfQuestions[i].settings || {}; sfQuestions[i].settings[key] = val; }
function sfToggleReq(i) { sfQuestions[i].is_required = sfQuestions[i].is_required ? 0 : 1; sfRender(); }
function sfMove(i, dir) { var j = i + dir; if (j < 0 || j >= sfQuestions.length) return; var t = sfQuestions[i]; sfQuestions[i] = sfQuestions[j]; sfQuestions[j] = t; sfRender(); }
function sfDup(i) { sfQuestions.splice(i + 1, 0, JSON.parse(JSON.stringify(sfQuestions[i]))); sfRender(); }
function sfDel(i) { sfQuestions.splice(i, 1); sfRender(); }

function sfAdd(type) {
    var q = { question_type: type, label: '', description: '', is_required: 0, options: [], settings: {} };
    if (type === 'radio' || type === 'checkbox' || type === 'dropdown') { q.options = ['Option 1', 'Option 2']; }
    if (type === 'rating') { q.settings = { max_stars: 5 }; }
    if (type === 'terms') { q.settings = { terms_text: '', must_accept: 1 }; q.label = 'Terms & Conditions'; }
    if (type === 'file') { q.settings = { max_files: 3 }; }
    if (type === 'credentials') { q.label = 'Portal login credentials'; }
    if (type === 'yes_no') { q.label = ''; }
    sfQuestions.push(q);
    sfRender();
    var cards = document.querySelectorAll('.sf-q');
    if (cards.length) { cards[cards.length - 1].scrollIntoView({ behavior: 'smooth', block: 'center' }); var inp = cards[cards.length - 1].querySelector('input.sf-input'); if (inp) inp.focus(); }
}

function sfLoadPreset(name) {
    var preset = SF_PRESETS[name] || [];
    sfQuestions = JSON.parse(JSON.stringify(preset)).map(function (q) {
        return Object.assign({ description: '', is_required: 0, options: [], settings: {} }, q);
    });
    sfRender();
}

function sfCopyShare() {
    var url = document.getElementById('sf-share-url').textContent.trim();
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(url); }
    else { var t = document.createElement('textarea'); t.value = url; document.body.appendChild(t); t.select(); document.execCommand('copy'); document.body.removeChild(t); }
    alert_float('success', 'Public link copied');
}

document.getElementById('sf-palette').addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-type]');
    if (btn) sfAdd(btn.getAttribute('data-type'));
});

document.getElementById('sf-cf-toggle').addEventListener('change', function () {
    document.getElementById('sf-cf-prompt').style.display = this.checked ? '' : 'none';
});
document.getElementById('sf-cf-prompt').style.display = document.getElementById('sf-cf-toggle').checked ? '' : 'none';

document.getElementById('sf-builder-form').addEventListener('submit', function (e) {
    var valid = sfQuestions.filter(function (q) { return (q.label || '').trim() !== ''; });
    if (!valid.length) {
        e.preventDefault();
        alert_float('warning', 'Add at least one question with a label before saving.');
        return false;
    }
    document.getElementById('sf-questions-json').value = JSON.stringify(valid);
});

sfRender();
</script>
</body>
</html>
