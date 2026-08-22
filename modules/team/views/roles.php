<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="team-header">
                    <h4>
                        <?php echo $title; ?>
                    </h4>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <a href="<?php echo admin_url('team'); ?>" class="add-member-btn"
                            style="background: #fff; color: #333; border: 1px solid #d1d5db;">
                            <i class="fa fa-arrow-left"></i> <?php echo _l('team_back'); ?>
                        </a>
                        <?php if (has_permission('team_roles', '', 'create')) { ?>
                            <a href="<?php echo admin_url('team/role'); ?>" class="add-member-btn">
                                <i class="fa fa-plus"></i> <?php echo _l('team_new_role'); ?>
                            </a>
                        <?php } ?>
                    </div>
                </div>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php render_datatable([
                            _l('team_role_name'),
                        ], 'roles'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function () {
        initDataTable('.table-roles', window.location.href);
    });
</script>