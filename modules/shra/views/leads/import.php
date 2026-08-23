<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'leads'; include __DIR__ . '/../_nav.php'; ?>
    <div class="shra-toolbar" style="justify-content:space-between">
        <h4 class="shra-title" style="margin:0">Import leads <span class="thin">· CSV</span></h4>
        <a href="<?php echo admin_url('shra/shra_leads/settings'); ?>" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa fa-sliders"></i> Settings</a>
    </div>

    <?php echo form_open_multipart(admin_url('shra/shra_leads/import')); ?>
    <div class="shra-card">
        <div class="shra-card-body">
            <div class="row">
                <div class="col-sm-4"><div class="form-group"><label>CSV file</label><input type="file" name="file" accept=".csv,text/csv" class="form-control"></div></div>
                <div class="col-sm-4"><div class="form-group"><label>Default source</label><select name="default_source" class="form-control"><option value="">— none —</option><?php foreach ($sources as $s) { ?><option value="<?php echo $s->id; ?>" <?php echo (isset($default_source) && $default_source == $s->id) ? 'selected' : ''; ?>><?php echo html_escape($s->name); ?></option><?php } ?></select></div></div>
                <div class="col-sm-4"><div class="form-group"><label>Default agent</label><select name="default_agent" class="form-control"><option value="">Auto (round robin)</option><?php foreach ($agents as $a) { ?><option value="<?php echo $a->staffid; ?>" <?php echo (isset($default_agent) && $default_agent == $a->staffid) ? 'selected' : ''; ?>><?php echo html_escape($a->full_name); ?></option><?php } ?></select></div></div>
            </div>
            <div class="help">Columns in order: <code>name, phone, email, city, source, agent, notes</code>. Header row optional. Source / agent are matched by name (agent also by email). Phones are de-duplicated against existing leads — nothing is created twice.</div>
            <?php if ($result && !$commit) { ?>
                <textarea name="csv" style="display:none"><?php echo html_escape($csv); ?></textarea>
            <?php } ?>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button class="shra-btn shra-btn-outline" name="commit" value="0"><i class="fa fa-eye"></i> Preview</button>
                <?php if ($result && !$commit && $result['counts']['new'] > 0) { ?><button class="shra-btn shra-btn-primary" name="commit" value="1" data-shra-confirm="Import <?php echo (int) $result['counts']['new']; ?> new leads now?"><i class="fa fa-upload"></i> Import <?php echo (int) $result['counts']['new']; ?> new</button><?php } ?>
            </div>
        </div>
    </div>
    <?php echo form_close(); ?>

    <?php if ($result) { $c = $result['counts']; ?>
    <div class="shra-card shra-mt">
        <div class="shra-card-head"><h4><?php echo $commit ? 'Import result' : 'Preview'; ?></h4><div><span class="shra-badge shra-badge-green"><?php echo (int) $c['new']; ?> <?php echo $commit ? 'imported' : 'new'; ?></span> <span class="shra-badge shra-badge-gold"><?php echo (int) $c['duplicate']; ?> duplicate</span> <span class="shra-badge shra-badge-red"><?php echo (int) $c['invalid']; ?> invalid</span></div></div>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>City</th><th>Source</th><th>Agent</th><th>Status</th></tr></thead>
            <tbody><?php foreach ($result['rows'] as $r) { ?>
                <tr><td class="shra-muted"><?php echo (int) $r['line']; ?></td><td><?php echo html_escape($r['name']); ?></td><td><?php echo html_escape($r['phone']); ?></td><td><?php echo html_escape($r['email']); ?></td><td><?php echo html_escape($r['city']); ?></td><td><?php echo html_escape($r['source']); ?></td><td><?php echo html_escape($r['agent']); ?></td>
                    <td><?php echo $r['status'] === 'new' ? '<span class="shra-badge shra-badge-green">' . ($commit ? 'Imported' : 'New') . '</span>' : ($r['status'] === 'duplicate' ? '<span class="shra-badge shra-badge-gold">Duplicate</span>' : '<span class="shra-badge shra-badge-red">Invalid</span>'); ?> <span class="shra-muted" style="font-size:11px"><?php echo html_escape($r['message']); ?></span></td></tr>
            <?php } ?></tbody>
        </table></div>
    </div>
    <?php } ?>
    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
