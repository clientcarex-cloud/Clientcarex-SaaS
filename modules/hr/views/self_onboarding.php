<?php defined('BASEPATH') or exit('No direct script access allowed');

$by_phase = [];
if (!empty($items)) {
    foreach ($items as $it) {
        $by_phase[$it['phase']][] = $it;
    }
}
$total = count($items);
$done  = count(array_filter($items, function ($i) { return $i['status'] !== 'pending'; }));
$pct   = $total ? round($done / $total * 100) : 0;
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 style="margin-top:6px;font-weight:800;"><i class="fa fa-clipboard-check"></i> My Onboarding</h4>

                        <?php if (!$ob) { ?>
                            <p class="text-muted">You do not have an active onboarding checklist. Welcome aboard! 🎉</p>
                        <?php } else { ?>
                            <p class="text-muted">Your onboarding progress. Your HR team and buddies help complete each step.</p>
                            <div class="progress" style="height:22px;">
                                <div class="progress-bar progress-bar-success" style="width:<?php echo $pct; ?>%;font-size:12px;line-height:22px;"><?php echo $pct; ?>% complete (<?php echo $done; ?>/<?php echo $total; ?>)</div>
                            </div>
                            <hr class="hr-panel-heading" />

                            <?php foreach ($phases as $pk => $pm) {
                                if (empty($by_phase[$pk])) {
                                    continue;
                                }
                            ?>
                                <div style="margin-bottom:16px;">
                                    <h5 style="font-weight:800;color:<?php echo $pm['color']; ?>;margin:0 0 6px;"><i class="fa <?php echo $pm['icon']; ?>"></i> <?php echo html_escape($pm['label']); ?></h5>
                                    <ul class="list-unstyled" style="margin:0;">
                                        <?php foreach ($by_phase[$pk] as $it) {
                                            $stm = $statuses[$it['status']] ?? $statuses['pending'];
                                        ?>
                                            <li style="padding:6px 0;border-bottom:1px solid #f1f5f9;">
                                                <i class="fa <?php echo $stm['icon']; ?>" style="color:<?php echo $stm['color']; ?>;"></i>
                                                <span style="<?php echo $it['status'] === 'done' ? 'text-decoration:line-through;color:#94a3b8;' : ''; ?>"><?php echo html_escape($it['title']); ?></span>
                                                <?php if (!empty($it['description'])) { ?><div class="text-muted" style="font-size:12px;margin-left:20px;"><?php echo html_escape($it['description']); ?></div><?php } ?>
                                                <?php if ($it['due_date'] && $it['status'] === 'pending') { ?><span class="text-muted" style="font-size:11px;margin-left:8px;"><i class="fa fa-calendar"></i> by <?php echo _d($it['due_date']); ?></span><?php } ?>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                </div>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
