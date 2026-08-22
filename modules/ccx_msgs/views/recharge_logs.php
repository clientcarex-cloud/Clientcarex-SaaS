<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">

                        <!-- Header Row -->
                        <div class="row" style="margin-bottom: 20px;">
                            <div class="col-md-12">
                                <h4 class="no-margin" style="font-weight: 600; font-size: 20px;">
                                    <i class="fa fa-history" style="color: #6366f1; margin-right: 8px;"></i>
                                    <?php echo _l('ccx_msgs_recharge_logs'); ?>
                                </h4>
                            </div>
                        </div>

                        <!-- DataTable (8 columns — expand arrow is inside the Plan column) -->
                        <?php render_datatable([
                            _l('ccx_msgs_rl_client'),
                            _l('ccx_msgs_rl_plan'),
                            _l('ccx_msgs_rl_amount'),
                            _l('ccx_msgs_rl_status'),
                            _l('ccx_msgs_rl_gateway'),
                            _l('ccx_msgs_rl_txn_id'),
                            _l('ccx_msgs_rl_invoice'),
                            _l('ccx_msgs_rl_date'),
                        ], 'recharge-logs'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Expand toggle button (inside Plan column) */
.rl-expand-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 5px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 11px;
    transition: all 0.2s ease;
    text-decoration: none !important;
    vertical-align: middle;
}
.rl-expand-btn:hover {
    background: #e2e8f0;
    color: #334155;
}
.rl-expand-btn.open {
    background: #6366f1;
    color: #fff;
    transform: rotate(90deg);
}
.rl-expand-btn.open:hover {
    background: #4f46e5;
}
/* Child row styling */
tr.rl-child-row > td {
    background: #fafbfc !important;
    padding: 0 !important;
    border-top: none !important;
}
tr.rl-child-row .table th {
    border-bottom: 1px solid #e2e8f0;
    background: #f1f5f9;
}
tr.rl-child-row .table td {
    border-top: 1px solid #f1f5f9;
    font-size: 13px;
}
</style>

<?php init_tail(); ?>

<script>
    $(function () {
        initDataTable('.table-recharge-logs', admin_url + 'ccx_msgs/recharge_logs_table', undefined, undefined, 'undefined', [7, 'desc']);

        // ═══ Expand/Collapse cart items ═══
        $('body').on('click', '.rl-expand-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $tr = $btn.closest('tr');
            var cartHtml = $btn.data('cart-html');

            if ($btn.hasClass('open')) {
                // Collapse
                $btn.removeClass('open');
                $tr.next('.rl-child-row').slideUp(200, function() { $(this).remove(); });
            } else {
                // Expand
                $btn.addClass('open');
                var colCount = $tr.find('td').length;
                var $childRow = $('<tr class="rl-child-row"><td colspan="' + colCount + '" style="display:none;">' + cartHtml + '</td></tr>');
                $tr.after($childRow);
                $childRow.find('td').slideDown(250);
            }
        });
    });
</script>
</body>
</html>
