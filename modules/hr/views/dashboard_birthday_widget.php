<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Main CRM dashboard greeting — same banner as the HR dashboard, minus the
 * profile links (regular staff have no HR access).
 */
?>
<div class="row">
    <div class="col-md-12">
        <?php $this->load->view('hr/_birthday_banner', [
            'bd_rows'    => $birthdays,
            'bd_me'      => isset($me) ? (int) $me : 0,
            'bd_link'    => false,
            'bd_private' => false,
        ]); ?>
    </div>
</div>
