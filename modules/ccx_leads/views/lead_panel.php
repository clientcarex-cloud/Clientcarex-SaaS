<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="ccx-panel-header" style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
    <h3 style="margin-top:0; font-weight:600;">
        <?php echo $lead->name; ?>
    </h3>
    <p class="text-muted">
        <?php echo $lead->company; ?> | <a href="tel:<?php echo $lead->phonenumber; ?>">
            <?php echo $lead->phonenumber; ?>
        </a>
    </p>

    <div class="ccx-panel-actions mtop10">
        <a href="<?php echo admin_url('leads/index/' . $lead->id); ?>" class="btn btn-default btn-xs">
            Edit Full Profile
        </a>
        <button onclick="delete_lead(<?php echo $lead->id; ?>)" class="btn btn-danger btn-xs">
            Delete
        </button>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs" role="tablist">
    <li role="presentation" class="active"><a href="#tab_overview" aria-controls="tab_overview" role="tab"
            data-toggle="tab">Overview</a></li>
    <li role="presentation"><a href="#tab_notes" aria-controls="tab_notes" role="tab" data-toggle="tab">Notes</a></li>
    <li role="presentation"><a href="#tab_activity" aria-controls="tab_activity" role="tab"
            data-toggle="tab">Activity</a></li>
</ul>

<div class="tab-content">
    <div role="tabpanel" class="tab-pane active" id="tab_overview">
        <div class="row mtop20">
            <div class="col-md-12">
                <table class="table table-striped">
                    <tbody>
                        <tr>
                            <td class="bold">Status</td>
                            <td>
                                <?php
                                $status = $this->leads_model->get_status($lead->status);
                                if ($status) {
                                    echo '<span class="label" style="background:' . $status->color . '">' . $status->name . '</span>';
                                } else {
                                    echo '<span class="label label-default">Unknown</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="bold">Source</td>
                            <td>
                                <?php
                                $source = $this->leads_model->get_source($lead->source);
                                echo ($source ? $source->name : '-');
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="bold">Assigned</td>
                            <td>
                                <?php
                                if ($lead->assigned != 0) {
                                    echo get_staff_full_name($lead->assigned);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="bold">Email</td>
                            <td>
                                <?php echo $lead->email; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="bold">Address</td>
                            <td>
                                <?php echo $lead->address; ?><br />
                                <?php echo $lead->city; ?>
                                <?php echo $lead->state; ?><br />
                                <?php echo $lead->zip; ?>
                                <?php echo $lead->country; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div role="tabpanel" class="tab-pane" id="tab_notes">
        <div class="mtop20">
            <?php if ($notes) {
                foreach ($notes as $note) {
                    echo '<div class="media" style="border-bottom:1px solid #f0f0f0; padding-bottom:10px; margin-bottom:10px;">';
                    echo '<div class="media-body">';
                    echo '<h5 class="media-heading">' . get_staff_full_name($note['addedfrom']) . ' <small class="text-muted">' . time_ago($note['dateadded']) . '</small></h5>';
                    echo $note['description'];
                    echo '</div>';
                    echo '</div>';
                }
            } else {
                echo '<p class="text-muted">No notes found.</p>';
            } ?>
            <!-- Simple Add Note form could go here -->
        </div>
    </div>

    <div role="tabpanel" class="tab-pane" id="tab_activity">
        <div class="activity-feed mtop20">
            <?php foreach ($activity_log as $log) { ?>
                <div class="feed-item">
                    <div class="date"><span class="text-has-action" data-toggle="tooltip"
                            data-title="<?php echo _dt($log['date']); ?>">
                            <?php echo time_ago($log['date']); ?>
                        </span></div>
                    <div class="text">
                        <?php if ($log['staffid'] != 0) { ?>
                            <a href="<?php echo admin_url('profile/' . $log['staffid']); ?>">
                                <?php echo staff_profile_image($log['staffid'], array('staff-profile-xs', 'image-xs')); ?>
                            </a>
                            <?php
                        }
                        echo $log['description']; ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>