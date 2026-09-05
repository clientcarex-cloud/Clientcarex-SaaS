<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="row">
    <div class="col-md-12">
        <?php foreach ($notices as $n) {
            $high = $n['priority'] === 'high'; ?>
            <div class="panel_s hr-notice-card<?php echo $high ? ' hr-notice-high' : ''; ?>">
                <div class="panel-body" style="border-left:4px solid <?php echo $high ? '#dc2626' : '#0891b2'; ?>;">
                    <div style="display:flex;align-items:flex-start;gap:12px;">
                        <div style="font-size:22px;line-height:1;color:<?php echo $high ? '#dc2626' : '#0891b2'; ?>;flex-shrink:0;">
                            <i class="fa fa-bullhorn"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <span class="bold" style="font-size:15px;"><?php echo html_escape($n['title']); ?></span>
                                <?php if ($high) { ?><span class="label label-danger">Important</span><?php } ?>
                                <span class="text-muted" style="font-size:11px;">&middot; HR Notice</span>
                            </div>
                            <div style="margin-top:5px;color:#334155;font-size:13px;line-height:1.5;"><?php echo nl2br(html_escape($n['message'])); ?></div>
                            <?php if (!empty($n['date_created'])) { ?>
                                <div class="text-muted" style="font-size:11px;margin-top:6px;"><i class="fa fa-clock-o"></i> <?php echo _dt($n['date_created']); ?></div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>
