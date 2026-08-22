<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-inline-flex tw-items-center tw-space-x-4 tw-mb-4">
                    <a href="<?php echo admin_url('reports/leads?type=staff'); ?>" class="btn btn-primary">
                        <?php echo _l('switch_to_general_report'); ?>
                    </a>
                    <p  class="tw-m-0" data-placement="bottom" data-toggle="tooltip"
                        data-title="<?php echo _l('leads_report_converted_notice'); ?>">
                        <i class="fa-regular fa-circle-question fa-lg"></i>
                    </p>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>
</div>
<?php init_tail(); ?>

</body>

</html>
