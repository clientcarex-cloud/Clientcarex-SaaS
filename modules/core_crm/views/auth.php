<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-6 col-md-offset-3">
                <div class="panel_s">
                    <div class="panel-heading">
                        <h4 class="panel-title">Core CRM - Authentication Required</h4>
                    </div>
                    <div class="panel-body">
                        <?php echo form_open(admin_url('core_crm')); ?>
                        <div class="form-group">
                            <label for="password">Enter Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-info">Login</button>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
