<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'attendance'; include __DIR__ . '/_nav.php'; ?>

    <div id="shra-att-done" style="display:none;margin-bottom:18px"></div>

    <div class="shra-att">
        <div>
            <div class="shra-card"><div class="shra-card-body">
                <div class="shra-step"><span class="n"><i class="fa-solid fa-horse" style="font-size:11px"></i></span><h5>Mark a class session</h5></div>
                <div style="position:relative">
                    <div class="shra-search"><i class="fa fa-search"></i><input type="text" id="shra-att-q" class="form-control" placeholder="Rider name, mobile or scan card" autocomplete="off" autofocus></div>
                    <div id="shra-att-results" class="shra-results"></div>
                </div>

                <div id="shra-att-panel" style="display:none;margin-top:14px">
                    <div id="shra-att-rider" class="shra-picked"></div>
                    <?php echo form_open(admin_url('shra/mark'), ['id' => 'shra-att-form']); ?>
                    <input type="hidden" name="enrollment_id" id="shra-enrollment-id">
                    <input type="hidden" name="session_date" value="<?php echo html_escape($date); ?>">
                    <div style="margin:14px 0 6px;font-size:12px;font-weight:600;color:var(--muted);letter-spacing:.8px;text-transform:uppercase">Active package</div>
                    <div id="shra-enr-list"></div>
                    <div class="row" style="margin-top:10px">
                        <div class="col-xs-6"><div class="form-group"><label>Trainer</label><select name="trainer_id" class="form-control"><?php foreach ($trainers as $t) { ?><option value="<?php echo $t->staffid; ?>" <?php echo $t->staffid == get_staff_user_id() ? 'selected' : ''; ?>><?php echo html_escape($t->firstname . ' ' . $t->lastname); ?></option><?php } ?></select></div></div>
                        <div class="col-xs-6"><div class="form-group"><label>Horse</label><input type="text" name="horse_name" id="shra-horse" class="form-control" placeholder="optional"></div></div>
                    </div>
                    <div class="form-group"><label>Note for this session</label><input type="text" name="notes" id="shra-note" class="form-control" placeholder="e.g. first canter, balance improving"></div>
                    <button type="submit" id="shra-mark" class="shra-btn shra-btn-gold shra-btn-block shra-big-mark" disabled><i class="fa-solid fa-check"></i> Mark session <span id="shra-next-no"></span> as attended</button>
                    <?php echo form_close(); ?>
                </div>
                <div class="help" style="margin-top:12px">First come, first served — no time slots. Each mark uses one session from the rider's package; the certificate is issued automatically on the last session.</div>
            </div></div>
        </div>

        <div>
            <div class="shra-card">
                <div class="shra-card-head">
                    <h4>Sessions on <?php echo _d($date); ?></h4>
                    <form method="get" style="display:flex;gap:8px;align-items:center"><input type="date" name="date" class="form-control" value="<?php echo html_escape($date); ?>" style="padding:6px 10px" onchange="this.form.submit()"><a href="<?php echo admin_url('shra/attendance_log'); ?>" class="shra-btn shra-btn-outline shra-btn-sm">Log</a></form>
                </div>
                <div id="shra-today"><?php $this->load->view('shra/partials/today_list', ['today' => $today, 'date' => $date]); ?></div>
            </div>
        </div>
    </div>
</div>
</div>
<?php init_tail(); ?>
<script>
$(function () {
    SHRA.urls = { search: <?php echo json_encode(admin_url('shra/search')); ?>, newRider: <?php echo json_encode(admin_url('shra/rider_form')); ?> };
    SHRA.attendance({
        preselect: <?php echo $preselect ? json_encode($preselect) : 'null'; ?>,
        urls: {
            enrollments: <?php echo json_encode(admin_url('shra/rider_enrollments')); ?>,
            mark: <?php echo json_encode(admin_url('shra/mark')); ?>,
            undo: <?php echo json_encode(admin_url('shra/undo')); ?>,
            billing: <?php echo json_encode(admin_url('shra/billing')); ?>
        }
    });
});
</script>
</body>
</html>
