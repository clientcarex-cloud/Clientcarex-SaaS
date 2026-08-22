<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo $title; ?>
                        </h4>
                        <hr class="hr-panel-heading" />

                        <?php echo form_open($this->uri->uri_string()); ?>

                        <div class="form-group">
                            <label for="hidden_roles">Select Roles to Hide from Staff List</label>
                            <div class="clearfix"></div>
                            <?php foreach ($roles as $role) { ?>
                                <div class="checkbox checkbox-primary">
                                    <input type="checkbox" name="hidden_roles[]" id="role_<?php echo $role['roleid']; ?>"
                                        value="<?php echo $role['roleid']; ?>" <?php if (in_array($role['roleid'], $hidden_roles)) {
                                               echo 'checked';
                                           } ?>>
                                    <label for="role_<?php echo $role['roleid']; ?>">
                                        <?php echo $role['name']; ?>
                                    </label>
                                </div>
                            <?php } ?>
                        </div>

                        <button type="submit" class="btn btn-primary pull-right">
                            <?php echo _l('submit'); ?>
                        </button>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>