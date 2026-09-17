<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="echo-kanban-container" style="display:flex; overflow-x:auto; padding-bottom:20px;">
    <?php foreach ($statuses as $status) { ?>
        <div class="kanban-column" style="border-top: 3px solid <?php echo $status['color']; ?>;">
            <div class="kanban-column-header mbot15">
                <h4 style="font-size:14px; font-weight:bold; margin-top:5px;">
                    <?php echo $status['name']; ?>
                    <!-- Optional: Count badge -->
                </h4>
            </div>

            <div class="kanban-column-body">
                <?php
                // Fetch leads for this status
                // Note: In a real complex app, we'd ajax load this column by column or pages
                // For this modern clone, we use the controller's logic
                $this->load->model('ccx_leads/ccx_leads_model');
                $leads = $this->ccx_leads_model->do_kanban_query($status['id']);
                ?>

                <?php if (empty($leads)) { ?>
                    <p class="text-muted text-center">
                        <?php echo _l('no_leads_found'); ?>
                    </p>
                <?php } else { ?>
                    <?php foreach ($leads as $lead) { ?>
                        <div class="kanban-card" onclick="ccx_lead_profile(<?php echo $lead['id']; ?>)">
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
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
</div>