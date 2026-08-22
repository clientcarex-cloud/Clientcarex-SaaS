<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <?php $this->load->view('admin/includes/alerts'); ?>

            <?php hooks()->do_action('before_start_render_dashboard_content'); ?>

            <?php hooks()->do_action('after_dashboard'); ?>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<?php $this->load->view('admin/dashboard/dashboard_js'); ?>

</body>

</html>