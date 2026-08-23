<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
    <div class="foot"><?php echo html_escape(get_option('shra_contact_line') ?: get_option('shra_academy_name')); ?></div>
    <div class="foot" style="margin-top:10px"><?php echo shra_powered_by(); ?></div>
</div>
</body>
</html>
