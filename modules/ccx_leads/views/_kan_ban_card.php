<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="kanban-card" onclick="ccx_lead_profile(<?php echo $lead['id']; ?>)"
    data-lead-id="<?php echo $lead['id']; ?>">
    <div class="kanban-card-title">
        <?php echo $lead['name']; ?>
    </div>
    <div class="text-muted" style="font-size:12px;">
        <?php echo $lead['company']; ?>
    </div>

    <div class="kanban-card-meta">
        <span class="text-has-action" data-toggle="tooltip" title="Last Contact">
            <i class="fa fa-clock-o"></i>
            <?php echo ($lead['lastcontact'] ? time_ago($lead['lastcontact']) : 'Never'); ?>
        </span>
        <div class="kanban-card-assigned">
            <?php if ($lead['assigned'] != 0) {
                echo staff_profile_image($lead['assigned'], array('staff-profile-image-small'));
            } ?>
        </div>
    </div>
</div>