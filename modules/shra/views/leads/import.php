<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$delims = ["\t" => 'Tab separated', ',' => 'Comma separated', ';' => 'Semicolon separated', '|' => 'Pipe separated'];
$max    = 300; // rows shown in the preview table
?>
<?php init_head(); ?>
<div id="wrapper" class="shra">
<div class="content">
    <?php $shra_active = 'leads'; include __DIR__ . '/../_nav.php'; ?>
    <div class="shra-toolbar" style="justify-content:space-between">
        <h4 class="shra-title" style="margin:0">Import leads <span class="thin">· any CSV, TSV or exported sheet</span></h4>
        <a href="<?php echo admin_url('shra/shra_leads/settings'); ?>" class="shra-btn shra-btn-outline shra-btn-sm"><i class="fa fa-sliders"></i> Settings</a>
    </div>

    <?php echo form_open_multipart(admin_url('shra/shra_leads/import'), ['id' => 'shra-import-form']); ?>
    <input type="hidden" name="token" value="<?php echo html_escape($token); ?>">
    <input type="hidden" name="filename" value="<?php echo html_escape($filename); ?>">
    <input type="hidden" name="encoding" value="<?php echo html_escape($encoding); ?>">

    <div class="shra-card">
        <div class="shra-card-head"><h4><i class="fa fa-file-arrow-up" style="color:var(--gold)"></i> 1 · The file</h4>
            <?php if ($sheet) { ?><span class="shra-badge shra-badge-muted"><?php echo html_escape($filename ?: 'uploaded sheet'); ?></span><?php } ?>
        </div>
        <div class="shra-card-body">
            <div class="row">
                <div class="col-sm-4"><div class="form-group"><label><?php echo $sheet ? 'Replace with another file' : 'Choose a file'; ?></label><input type="file" name="file" accept=".csv,.tsv,.txt,text/csv,text/plain" class="form-control"><div class="help">Facebook / Instagram lead exports, Google Forms, Excel &amp; Sheets exports — commas, tabs or semicolons, any encoding.</div></div></div>
                <div class="col-sm-4"><div class="form-group"><label>Default source <span class="help" style="display:inline">used when the sheet has none</span></label><select name="default_source" class="form-control"><option value="">— none —</option><?php foreach ($sources as $s) { ?><option value="<?php echo $s->id; ?>" <?php echo (int) $opts['default_source'] === (int) $s->id ? 'selected' : ''; ?>><?php echo html_escape($s->name); ?></option><?php } ?></select></div></div>
                <div class="col-sm-4"><div class="form-group"><label>Default agent</label><select name="default_agent" class="form-control"><option value="">Auto (round robin)</option><?php foreach ($agents as $a) { ?><option value="<?php echo $a->staffid; ?>" <?php echo (int) $opts['default_agent'] === (int) $a->staffid ? 'selected' : ''; ?>><?php echo html_escape($a->full_name); ?></option><?php } ?></select></div></div>
            </div>
            <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:center">
                <label style="font-weight:500;margin:0"><input type="checkbox" name="create_sources" value="1" <?php echo !empty($opts['create_sources']) ? 'checked' : ''; ?>> Create sources found in the file</label>
                <label style="font-weight:500;margin:0"><input type="checkbox" name="remember" value="1" <?php echo !empty($opts['remember']) ? 'checked' : ''; ?>> Remember these columns for next time</label>
                <?php if ($sheet) { ?>
                <label style="font-weight:500;margin:0">First row&nbsp;
                    <select name="has_header" class="form-control" style="width:auto;display:inline-block">
                        <option value="" <?php echo $opts['has_header'] === '' ? 'selected' : ''; ?>>Detect automatically</option>
                        <option value="1" <?php echo $opts['has_header'] === '1' ? 'selected' : ''; ?>>Column names</option>
                        <option value="0" <?php echo $opts['has_header'] === '0' ? 'selected' : ''; ?>>Already a lead</option>
                    </select>
                </label>
                <?php } ?>
            </div>
            <div style="margin-top:14px">
                <button class="shra-btn shra-btn-outline" name="commit" value="0"><i class="fa fa-eye"></i> <?php echo $sheet ? 'Refresh preview' : 'Read the file'; ?></button>
            </div>
        </div>
    </div>

    <?php if ($sheet) { ?>
    <div class="shra-card shra-mt">
        <div class="shra-card-head"><h4><i class="fa fa-table-columns" style="color:var(--gold)"></i> 2 · Columns</h4>
            <span class="shra-muted" style="font-size:12px">
                <?php echo html_escape($encoding ?: $sheet['encoding']); ?> · <?php echo $delims[$sheet['delimiter']] ?? 'Custom separator'; ?> ·
                <?php echo count($sheet['rows']); ?> data rows · <?php echo $sheet['has_header'] ? 'first row read as column names' : 'no header row'; ?>
            </span>
        </div>
        <div class="shra-card-body">
            <div class="help" style="margin-bottom:10px">Detected automatically — change anything that is wrong. Columns left on <b>Notes (with column name)</b> are written into the lead's notes as <i>Question: answer</i>, so nothing in the sheet is lost.</div>
            <div class="row">
                <?php foreach ($sheet['headers'] as $i => $h) {
                    $sample = '';
                    foreach ($sheet['rows'] as $r) {
                        if (trim((string) ($r[$i] ?? '')) !== '') { $sample = (string) $r[$i]; break; }
                    }
                    $edited = ($sheet['map'][$i] ?? '') !== ($sheet['auto'][$i] ?? ''); ?>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo html_escape($h); ?>"><?php echo html_escape($h); ?><?php echo $edited ? ' <span class="shra-badge shra-badge-gold" style="font-size:9px">changed</span>' : ''; ?></label>
                            <select name="map[<?php echo $i; ?>]" class="form-control">
                                <?php foreach ($targets as $k => $label) { ?><option value="<?php echo $k; ?>" <?php echo ($sheet['map'][$i] ?? 'extra') === $k ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option><?php } ?>
                            </select>
                            <div class="help" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">e.g. <?php echo html_escape(mb_substr($sample, 0, 40)) ?: '—'; ?></div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php } ?>

    <?php if ($result) { $c = $result['counts']; ?>
    <div class="shra-card shra-mt">
        <div class="shra-card-head"><h4><i class="fa fa-list-check" style="color:var(--gold)"></i> <?php echo $commit ? '3 · Import result' : '3 · Preview'; ?></h4>
            <div><span class="shra-badge shra-badge-green"><?php echo (int) $c['new']; ?> <?php echo $commit ? 'imported' : 'new'; ?></span>
                 <span class="shra-badge shra-badge-gold"><?php echo (int) $c['duplicate']; ?> duplicate</span>
                 <span class="shra-badge shra-badge-red"><?php echo (int) $c['invalid']; ?> invalid</span></div>
        </div>
        <?php if (!$commit && $c['new'] > 0) { ?>
        <div class="shra-card-body" style="padding-bottom:0">
            <button class="shra-btn shra-btn-primary" name="commit" value="1" data-shra-confirm="Import <?php echo (int) $c['new']; ?> new leads now?"><i class="fa fa-upload"></i> Import <?php echo (int) $c['new']; ?> new leads</button>
            <span class="help" style="display:inline;margin-left:10px">Duplicates and invalid rows are skipped.</span>
        </div>
        <?php } ?>
        <div class="shra-table-wrap"><table class="shra-table">
            <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>City</th><th>Source</th><th>Agent</th><th>Came in</th><th>Status</th></tr></thead>
            <tbody><?php foreach (array_slice($result['rows'], 0, $max) as $r) { ?>
                <tr>
                    <td class="shra-muted"><?php echo (int) $r['line']; ?></td>
                    <td><?php echo html_escape($r['name']) ?: '<span class="shra-muted">—</span>'; ?><?php if ($r['notes'] !== '') { ?><div class="shra-muted" style="font-size:11px;white-space:pre-line"><?php echo html_escape(mb_substr($r['notes'], 0, 160)); ?></div><?php } ?></td>
                    <td><?php echo html_escape($r['phone']); ?></td>
                    <td><?php echo html_escape($r['email']); ?></td>
                    <td><?php echo html_escape($r['city']); ?></td>
                    <td><?php echo html_escape($r['source']); ?></td>
                    <td><?php echo html_escape($r['agent']); ?></td>
                    <td class="shra-muted" style="font-size:11.5px"><?php echo $r['created'] ? shra_datetime($r['created'], true) : '—'; ?></td>
                    <td><?php echo $r['status'] === 'new' ? '<span class="shra-badge shra-badge-green">' . ($commit ? 'Imported' : 'New') . '</span>' : ($r['status'] === 'duplicate' ? '<span class="shra-badge shra-badge-gold">Duplicate</span>' : '<span class="shra-badge shra-badge-red">Invalid</span>'); ?>
                        <?php if ($r['message'] !== '') { ?><div class="shra-muted" style="font-size:11px"><?php echo html_escape($r['message']); ?></div><?php } ?></td>
                </tr>
            <?php } ?></tbody>
        </table></div>
        <?php if (count($result['rows']) > $max) { ?><div class="shra-card-body"><div class="help">Showing the first <?php echo $max; ?> of <?php echo count($result['rows']); ?> rows — the counts above cover the whole file.</div></div><?php } ?>
    </div>
    <?php } ?>
    <?php echo form_close(); ?>

    <?php if ($commit && $result && $result['counts']['new'] > 0) { ?>
    <div style="margin-top:14px"><a href="<?php echo admin_url('shra/shra_leads'); ?>" class="shra-btn shra-btn-primary"><i class="fa fa-phone-volume"></i> Go to the leads desk</a></div>
    <?php } ?>

    <div class="shra-footer"><?php echo shra_powered_by(); ?></div>
</div>
</div>
<?php init_tail(); ?>
</body>
</html>
