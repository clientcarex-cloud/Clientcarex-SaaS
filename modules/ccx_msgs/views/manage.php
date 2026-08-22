<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <?php
        // ── This installation's own balance ──────────────────────────────
        // The table below lists client (tenant) allocations. Messages sent from
        // THIS account draw on the reserved self row instead, which no client
        // list can show — so it gets its own card.
        $self_alloc    = isset($self_allocation) ? $self_allocation : null;
        $self_id       = isset($self_client_id) ? $self_client_id : 0;
        $self_channels = [
            'sms'      => _l('ccx_msgs_sms'),
            'whatsapp' => _l('ccx_msgs_whatsapp'),
            'email'    => _l('ccx_msgs_email'),
            'aicall'   => _l('ccx_msgs_aicall'),
        ];
        ?>
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                            <h4 class="no-margin">
                                <i class="fa fa-building-o" style="margin-right:6px;"></i>
                                <?php echo _l('ccx_msgs_self_account'); ?>
                            </h4>
                            <a href="#" class="btn btn-info btn-sm" onclick="edit_allocation(<?php echo (int) $self_id; ?>); return false;">
                                <i class="fa fa-plus"></i> <?php echo _l('ccx_msgs_self_account_topup'); ?>
                            </a>
                        </div>
                        <hr class="hr-panel-heading" />

                        <?php if (!$self_alloc): ?>
                            <p class="text-muted no-margin">
                                <?php echo _l('ccx_msgs_self_account_empty'); ?>
                            </p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($self_channels as $ch_key => $ch_label): ?>
                                    <?php
                                    $promo_c = $ch_key . '_promo_count';
                                    $trans_c = $ch_key . '_trans_count';
                                    $promo_a = $ch_key . '_promo_active';
                                    $trans_a = $ch_key . '_trans_active';
                                    $promo_e = $ch_key . '_promo_expiry';
                                    $trans_e = $ch_key . '_trans_expiry';
                                    ?>
                                    <div class="col-md-3 col-sm-6">
                                        <div style="border:1px solid #eee; border-radius:4px; padding:12px; margin-bottom:10px;">
                                            <strong><?php echo $ch_label; ?></strong>
                                            <hr style="margin:8px 0;" />
                                            <?php foreach (['promo' => [$promo_c, $promo_a, $promo_e], 'trans' => [$trans_c, $trans_a, $trans_e]] as $sub => $cols): ?>
                                                <div style="margin-bottom:4px;">
                                                    <?php echo _l('ccx_msgs_' . $sub); ?>:
                                                    <span class="text-info"><strong><?php echo isset($self_alloc->{$cols[0]}) ? (int) $self_alloc->{$cols[0]} : 0; ?></strong></span>
                                                    <?php if (isset($self_alloc->{$cols[1]}) && (int) $self_alloc->{$cols[1]} === 0): ?>
                                                        <span class="label label-danger" style="font-size:9px;"><?php echo _l('ccx_msgs_inactive_type'); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($self_alloc->{$cols[2]}) && strtotime($self_alloc->{$cols[2]}) < time()): ?>
                                                        <span class="label label-danger" style="font-size:9px;"><?php echo _l('ccx_msgs_expired'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo _l('ccx_msgs_manage'); ?>
                        </h4>
                        <hr class="hr-panel-heading" />
                        <?php render_datatable([
                            _l('ccx_msgs_company'),
                            _l('ccx_msgs_sms'),
                            _l('ccx_msgs_whatsapp') . ' <small style="font-weight:400; opacity:0.7;">(&amp; Web)</small>',
                            _l('ccx_msgs_email'),
                            _l('ccx_msgs_aicall'),
                        ], 'ccx_msgs'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="allocation_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content" id="allocation_modal_content">
            <!-- Modal content will be loaded via AJAX -->
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
    $(function () {
        initDataTable('.table-ccx_msgs', admin_url + 'ccx_msgs/table', undefined, undefined, undefined, [0, 'asc']);
    });

    function edit_allocation(client_id) {
        requestGet('ccx_msgs/get_allocation_modal/' + client_id).done(function (response) {
            $('#allocation_modal_content').html(response);
            $('#allocation_modal').modal('show');
            init_datepicker();
            appValidateForm($('#allocation-form'), {}, function (form) {
                var btn = $(form).find('button[type="submit"]');
                btn.button('loading');
                $.post(form.action, $(form).serialize()).done(function (response) {
                    response = JSON.parse(response);
                    if (response.success) {
                        alert_float('success', response.message);
                        // The self-account card is rendered server-side, so it
                        // needs a reload rather than a datatable refresh.
                        if (parseInt(client_id, 10) === <?php echo (int) (isset($self_client_id) ? $self_client_id : 0); ?>) {
                            window.location.reload();
                            return false;
                        }
                        $('.table-ccx_msgs').DataTable().ajax.reload(null, false);
                    } else {
                        alert_float('warning', response.message);
                    }
                    $('#allocation_modal').modal('hide');
                }).fail(function () {
                    alert_float('danger', 'Failed to save allocation.');
                }).always(function () {
                    btn.button('reset');
                });
                return false;
            });
        }).fail(function (data) {
            alert_float('danger', data.responseText);
        });
    }
</script>
</body>

</html>