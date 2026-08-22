<?php defined('BASEPATH') or exit('No direct script access allowed');

$poll      = isset($poll) ? $poll : null;
$questions = [];
if ($poll) {
    foreach ($poll->questions as $q) {
        $opts = [];
        foreach ($q->options as $o) {
            $opts[] = ['id' => (int) $o->id, 'label' => $o->label];
        }
        $questions[] = ['id' => (int) $q->id, 'question' => $q->question, 'options' => $opts];
    }
}
?>
<?php init_head(); ?>
<style>
.vt-page{--vt-primary:#6366f1;--vt-primary-dark:#4f46e5;--vt-danger:#ef4444;--vt-text:#1e293b;--vt-muted:#64748b;--vt-border:#e2e8f0;--vt-shadow:0 1px 3px rgba(15,23,42,.08)}
.vt-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px}
.vt-header h3{margin:0;font-weight:700;color:var(--vt-text)}
.vt-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;border:1px solid transparent;cursor:pointer;text-decoration:none!important;transition:all .15s}
.vt-btn-primary{background:linear-gradient(135deg,var(--vt-primary),var(--vt-primary-dark));color:#fff!important;box-shadow:0 4px 12px rgba(99,102,241,.3)}
.vt-btn-primary:hover{transform:translateY(-1px);color:#fff}
.vt-btn-outline{background:#fff;border-color:var(--vt-border);color:var(--vt-text)!important}
.vt-btn-outline:hover{border-color:var(--vt-primary);color:var(--vt-primary)!important}
.vt-btn-sm{padding:6px 12px;font-size:12px;border-radius:8px}
.vt-card{background:#fff;border:1px solid var(--vt-border);border-radius:14px;box-shadow:var(--vt-shadow);margin-bottom:16px}
.vt-card-body{padding:20px 22px}
.vt-label{font-size:12px;font-weight:700;color:var(--vt-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:block}
.vt-input,.vt-textarea{width:100%;padding:10px 14px;border:1px solid var(--vt-border);border-radius:10px;font-size:14px;color:var(--vt-text);outline:none;transition:border .15s;background:#fff}
.vt-input:focus,.vt-textarea:focus{border-color:var(--vt-primary);box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.vt-q{border:1px solid var(--vt-border);border-radius:12px;padding:16px 18px;margin-bottom:14px;background:#fafbfc}
.vt-q-head{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.vt-q-num{width:28px;height:28px;border-radius:8px;background:#eef2ff;color:var(--vt-primary);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;flex-shrink:0}
.vt-q-head input{flex:1}
.vt-opt{display:flex;align-items:center;gap:8px;margin-bottom:8px;margin-left:38px}
.vt-opt i.grip{color:#cbd5e1;font-size:12px}
.vt-opt input{flex:1}
.vt-icon-btn{width:32px;height:32px;border-radius:8px;border:1px solid var(--vt-border);background:#fff;color:var(--vt-muted);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0}
.vt-icon-btn:hover{color:var(--vt-danger);border-color:var(--vt-danger)}
.vt-icon-btn.up:hover,.vt-icon-btn.down:hover{color:var(--vt-primary);border-color:var(--vt-primary)}
.vt-add-opt{margin-left:38px;font-size:12px;font-weight:600;color:var(--vt-primary);cursor:pointer;background:none;border:none;padding:4px 0}
.vt-check{display:flex;align-items:center;gap:10px;font-size:14px;color:var(--vt-text);cursor:pointer;user-select:none}
.vt-check input{width:18px;height:18px;accent-color:var(--vt-primary);cursor:pointer}
.vt-hint{font-size:12px;color:var(--vt-muted);margin-top:4px}
</style>
<div id="wrapper" class="vt-page">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">

                <div class="vt-header">
                    <h3><i class="fa-solid fa-square-poll-vertical" style="color:var(--vt-primary);margin-right:8px"></i><?php echo $title; ?></h3>
                    <a href="<?php echo admin_url('voting'); ?>" class="vt-btn vt-btn-outline vt-btn-sm"><i class="fa fa-arrow-left"></i> All Polls</a>
                </div>

                <?php echo form_open($this->uri->uri_string(), ['id' => 'vt-form']); ?>
                <input type="hidden" name="questions" id="vt-questions-json">

                <div class="vt-card">
                    <div class="vt-card-body">
                        <div style="margin-bottom:16px">
                            <label class="vt-label">Poll Title *</label>
                            <input type="text" class="vt-input" name="title" required maxlength="255" placeholder="e.g. Webinar: Digital Health Trends — Audience Poll" value="<?php echo $poll ? html_escape($poll->title) : ''; ?>">
                        </div>
                        <div style="margin-bottom:16px">
                            <label class="vt-label">Description</label>
                            <textarea class="vt-textarea" name="description" rows="2" placeholder="Shown under the title on the public voting page (optional)"><?php echo $poll ? html_escape($poll->description) : ''; ?></textarea>
                        </div>
                        <label class="vt-check">
                            <input type="checkbox" name="collect_names" value="1" <?php echo ($poll && !empty($poll->collect_names)) ? 'checked' : ''; ?>>
                            Collect voter names — voters must enter their name before voting
                        </label>
                        <div class="vt-hint" style="margin-left:28px">Names are visible only to you on the Monitor screen, never on the public pages. Votes are final — one vote per device per question, no changes.</div>
                    </div>
                </div>

                <div class="vt-card">
                    <div class="vt-card-body">
                        <label class="vt-label" style="margin-bottom:14px">Questions</label>
                        <div id="vt-questions"></div>
                        <button type="button" class="vt-btn vt-btn-outline" onclick="vtAddQuestion()"><i class="fa fa-plus"></i> Add Question</button>
                        <div class="vt-hint">Each question needs at least 2 options. You control which question is live from the Monitor screen.</div>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-bottom:40px">
                    <button type="submit" class="vt-btn vt-btn-primary"><i class="fa-regular fa-floppy-disk"></i> Save Poll</button>
                </div>
                <?php echo form_close(); ?>

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
var vtQuestions = <?php echo json_encode(count($questions) ? $questions : [['id' => 0, 'question' => '', 'options' => [['id' => 0, 'label' => ''], ['id' => 0, 'label' => '']]]]); ?>;

function vtEsc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
}

function vtRender() {
    var wrap = document.getElementById('vt-questions');
    var html = '';
    vtQuestions.forEach(function(q, qi) {
        html += '<div class="vt-q">';
        html += '<div class="vt-q-head">';
        html += '<div class="vt-q-num">' + (qi + 1) + '</div>';
        html += '<input type="text" class="vt-input" placeholder="Type the question…" value="' + vtEsc(q.question) + '" oninput="vtQuestions[' + qi + '].question=this.value">';
        html += '<button type="button" class="vt-icon-btn up" title="Move up" onclick="vtMoveQ(' + qi + ',-1)"><i class="fa fa-arrow-up"></i></button>';
        html += '<button type="button" class="vt-icon-btn down" title="Move down" onclick="vtMoveQ(' + qi + ',1)"><i class="fa fa-arrow-down"></i></button>';
        html += '<button type="button" class="vt-icon-btn" title="Remove question" onclick="vtRemoveQ(' + qi + ')"><i class="fa-regular fa-trash-can"></i></button>';
        html += '</div>';
        q.options.forEach(function(o, oi) {
            html += '<div class="vt-opt">';
            html += '<i class="fa-regular fa-circle grip"></i>';
            html += '<input type="text" class="vt-input" placeholder="Option ' + (oi + 1) + '" value="' + vtEsc(o.label) + '" oninput="vtQuestions[' + qi + '].options[' + oi + '].label=this.value">';
            html += '<button type="button" class="vt-icon-btn" title="Remove option" onclick="vtRemoveO(' + qi + ',' + oi + ')"><i class="fa fa-times"></i></button>';
            html += '</div>';
        });
        html += '<button type="button" class="vt-add-opt" onclick="vtAddO(' + qi + ')"><i class="fa fa-plus"></i> Add option</button>';
        html += '</div>';
    });
    wrap.innerHTML = html;
}

function vtAddQuestion() {
    vtQuestions.push({ id: 0, question: '', options: [{ id: 0, label: '' }, { id: 0, label: '' }] });
    vtRender();
}
function vtRemoveQ(qi) {
    if (!confirm('Remove this question? Any votes on it will be deleted when you save.')) return;
    vtQuestions.splice(qi, 1);
    vtRender();
}
function vtMoveQ(qi, dir) {
    var ni = qi + dir;
    if (ni < 0 || ni >= vtQuestions.length) return;
    var t = vtQuestions[qi]; vtQuestions[qi] = vtQuestions[ni]; vtQuestions[ni] = t;
    vtRender();
}
function vtAddO(qi) {
    vtQuestions[qi].options.push({ id: 0, label: '' });
    vtRender();
}
function vtRemoveO(qi, oi) {
    vtQuestions[qi].options.splice(oi, 1);
    vtRender();
}

document.getElementById('vt-form').addEventListener('submit', function(e) {
    var valid = [];
    vtQuestions.forEach(function(q) {
        var text = (q.question || '').trim();
        if (!text) return;
        var opts = q.options.filter(function(o) { return (o.label || '').trim() !== ''; });
        valid.push({ id: q.id || 0, question: text, options: opts });
    });
    var bad = valid.some(function(q) { return q.options.length < 2; });
    if (!valid.length || bad) {
        e.preventDefault();
        alert('Every question needs text and at least 2 options.');
        return;
    }
    document.getElementById('vt-questions-json').value = JSON.stringify(valid);
});

vtRender();
</script>
</body>
</html>
