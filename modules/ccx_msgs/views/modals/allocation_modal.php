<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$allocation = isset($allocation) ? $allocation : null;
echo form_open(admin_url('ccx_msgs/save_allocation'), ['id' => 'allocation-form']);

function render_allocation_section($type, $sub_type, $allocation)
{
    $count_field = "{$type}_{$sub_type}_count";
    $add_field = "{$type}_{$sub_type}_count_add";
    $deduct_field = "{$type}_{$sub_type}_count_deduct";
    $expiry_field = "{$type}_{$sub_type}_expiry";
    $header_field = "{$type}_{$sub_type}_header";
    $active_field = "{$type}_{$sub_type}_active";

    $current_count = isset($allocation) && isset($allocation->$count_field) ? $allocation->$count_field : 0;
    $current_expiry = (isset($allocation) && isset($allocation->$expiry_field) && $allocation->$expiry_field) ? _d($allocation->$expiry_field) : '';
    $current_header = isset($allocation) && isset($allocation->$header_field) ? $allocation->$header_field : '';
    $is_active = isset($allocation) && isset($allocation->$active_field) ? (int) $allocation->$active_field : 1;

    ob_start();
    ?>
    <div class="panel panel-info" style="margin-bottom: 15px;">
        <div class="panel-heading"
            style="padding: 10px 15px; display:flex; align-items:center; justify-content:space-between;">
            <strong><?php echo _l('ccx_msgs_' . $sub_type); ?></strong>
            <label
                style="margin:0; font-weight:normal; cursor:pointer; display:flex; align-items:center; gap:6px; font-size:12px;">
                <input type="checkbox" name="<?php echo $active_field; ?>" value="1" <?php echo $is_active ? 'checked' : ''; ?> style="margin:0;">
                <?php echo _l('ccx_msgs_active_type'); ?>
            </label>
        </div>
        <div class="panel-body" style="padding: 15px;">
            <!-- Header Input -->
            <div class="row" style="margin-bottom: 12px;">
                <div class="col-md-12">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="<?php echo $header_field; ?>"
                            class="control-label"><?php echo _l('ccx_msgs_header'); ?></label>
                        <input type="text" class="form-control" name="<?php echo $header_field; ?>"
                            id="<?php echo $header_field; ?>" value="<?php echo htmlspecialchars($current_header); ?>"
                            placeholder="Enter header text...">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-7">
                    <p style="margin-bottom: 5px;"><strong><?php echo _l('ccx_msgs_current_balance'); ?>:</strong> <span
                            class="badge bg-success" style="font-size: 14px;"><?php echo $current_count; ?></span></p>
                    <!-- Top-up -->
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label for="<?php echo $add_field; ?>"
                            class="control-label"><?php echo _l('ccx_msgs_topup'); ?></label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="<?php echo $add_field; ?>"
                                id="<?php echo $add_field; ?>" value="0" min="0">
                            <span class="input-group-btn">
                                <button class="btn btn-default add-btn" type="button"
                                    data-target="#<?php echo $add_field; ?>" data-val="1000">+1k</button>
                                <button class="btn btn-default add-btn" type="button"
                                    data-target="#<?php echo $add_field; ?>" data-val="5000">+5k</button>
                                <button class="btn btn-default add-btn" type="button"
                                    data-target="#<?php echo $add_field; ?>" data-val="10000">+10k</button>
                                <button class="btn btn-danger clear-btn" type="button"
                                    data-target="#<?php echo $add_field; ?>" title="Clear"><i
                                        class="fa fa-times"></i></button>
                            </span>
                        </div>
                    </div>
                    <!-- Deduct -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="<?php echo $deduct_field; ?>" class="control-label"
                            style="color:#dc3545;"><?php echo _l('ccx_msgs_deduct'); ?></label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="<?php echo $deduct_field; ?>"
                                id="<?php echo $deduct_field; ?>" value="0" min="0">
                            <span class="input-group-btn">
                                <button class="btn btn-default deduct-btn" type="button"
                                    data-target="#<?php echo $deduct_field; ?>" data-val="100">-100</button>
                                <button class="btn btn-default deduct-btn" type="button"
                                    data-target="#<?php echo $deduct_field; ?>" data-val="500">-500</button>
                                <button class="btn btn-default deduct-btn" type="button"
                                    data-target="#<?php echo $deduct_field; ?>" data-val="1000">-1k</button>
                                <button class="btn btn-danger clear-btn" type="button"
                                    data-target="#<?php echo $deduct_field; ?>" title="Clear"><i
                                        class="fa fa-times"></i></button>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <?php echo render_date_input($expiry_field, 'ccx_msgs_expiry', $current_expiry); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>

<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
            aria-hidden="true">&times;</span></button>
    <h4 class="modal-title">
        <?php echo _l('ccx_msgs_edit'); ?> -
        <?php echo (is_object($client) && isset($client->company)) ? $client->company : ''; ?>
    </h4>
</div>
<div class="modal-body">
    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">

    <ul class="nav nav-tabs" role="tablist">
        <li role="presentation" class="active"><a href="#tab_sms" aria-controls="tab_sms" role="tab"
                data-toggle="tab"><?php echo _l('ccx_msgs_sms'); ?></a></li>
        <li role="presentation"><a href="#tab_wa" aria-controls="tab_wa" role="tab"
                data-toggle="tab"><?php echo _l('ccx_msgs_whatsapp'); ?></a></li>
        <li role="presentation"><a href="#tab_email" aria-controls="tab_email" role="tab"
                data-toggle="tab"><?php echo _l('ccx_msgs_email'); ?></a></li>
        <li role="presentation"><a href="#tab_aicall" aria-controls="tab_aicall" role="tab"
                data-toggle="tab"><?php echo _l('ccx_msgs_aicall'); ?></a></li>
    </ul>

    <div class="tab-content" style="padding-top: 20px;">
        <div role="tabpanel" class="tab-pane active" id="tab_sms">
            <?php echo render_allocation_section('sms', 'promo', $allocation); ?>
            <?php echo render_allocation_section('sms', 'trans', $allocation); ?>
        </div>
        <div role="tabpanel" class="tab-pane" id="tab_wa">
            <?php // "Our WhatsApp" first: a free grant means the credits below are never used. ?>
            <div id="wa-shared-wrap">
                <?php if (!empty($wa_shared)): ?>
                    <?php $this->load->view('modals/_shared_whatsapp', ['wa_shared' => $wa_shared, 'client_id' => $client_id]); ?>
                <?php endif; ?>
            </div>
            <?php echo render_allocation_section('whatsapp', 'promo', $allocation); ?>
            <?php echo render_allocation_section('whatsapp', 'trans', $allocation); ?>
        </div>
        <div role="tabpanel" class="tab-pane" id="tab_email">
            <?php echo render_allocation_section('email', 'promo', $allocation); ?>
            <?php echo render_allocation_section('email', 'trans', $allocation); ?>
        </div>
        <div role="tabpanel" class="tab-pane" id="tab_aicall">
            <?php echo render_allocation_section('aicall', 'promo', $allocation); ?>
            <?php echo render_allocation_section('aicall', 'trans', $allocation); ?>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('ccx_msgs_cancel'); ?></button>
    <button type="submit" class="btn btn-info"><?php echo _l('ccx_msgs_save'); ?></button>
</div>
<?php echo form_close(); ?>

<script>
    $(document).ready(function () {
        $('.add-btn').on('click', function () {
            var target = $($(this).data('target'));
            var val = parseInt($(this).data('val'), 10);
            var current = parseInt(target.val(), 10) || 0;
            target.val(current + val);
        });
        $('.deduct-btn').on('click', function () {
            var target = $($(this).data('target'));
            var val = parseInt($(this).data('val'), 10);
            var current = parseInt(target.val(), 10) || 0;
            target.val(current + val);
        });
        $('.clear-btn').on('click', function () {
            $($(this).data('target')).val(0);
        });

        /* ── "Our WhatsApp" shared-number panel ──
           Delegated off the modal body, because the panel is re-rendered over
           AJAX when a multi-instance client switches instance. */
        var $modal = $('#allocation-form');

        $modal.on('change', '.wa-shared-tplmode', function () {
            $('#wa-shared-templates').toggle($modal.find('.wa-shared-tplmode:checked').val() !== 'all');
        });

        $modal.on('click', '.wa-shared-tpl-all', function (e) {
            e.preventDefault();
            $('#wa-shared-templates input[type="checkbox"]').prop('checked', $(this).data('on') == 1);
        });

        $modal.on('change', '#wa_shared_instance', function () {
            var slug = $(this).val();
            $('#wa-shared-wrap').css('opacity', .5);
            $.get('<?php echo admin_url('ccx_msgs/shared_whatsapp_panel/' . (int) $client_id); ?>', { slug: slug }, function (html) {
                $('#wa-shared-wrap').html(html).css('opacity', 1);
            });
        });
    });
</script>