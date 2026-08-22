<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
    /* Add specific campaign UI styling here if needed, or rely on manage.php CSS */
    .ccx-comm-page { font-family: 'Inter', sans-serif; }
    .ccx-page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
    .ccx-page-header-icon { width: 48px; height: 48px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3); }
    .ccx-page-header h4 { margin: 0; font-weight: 700; font-size: 22px; color: #1e1b4b; letter-spacing: -0.3px; }
    .ccx-page-header p { margin: 2px 0 0; font-size: 13px; color: #6b7280; font-weight: 400; }
    .ccx-main-card { background: #ffffff; border-radius: 16px; border: 1px solid rgba(229, 231, 235, 0.7); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04), 0 10px 30px -5px rgba(0,0,0,0.06); padding: 24px; }
    .ccx-btn-save { padding: 9px 22px; border-radius: 10px; font-size: 13.5px; font-weight: 600; background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; border: none; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3); text-decoration: none !important; display: inline-flex; align-items: center; gap: 8px; }
    .ccx-btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4); color: #fff; }
</style>

<div id="wrapper">
    <div class="content ccx-comm-page">
        <div class="row">
            <div class="col-md-12">
                <div class="ccx-page-header" style="justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <a href="<?php echo admin_url('sms_wa_email'); ?>" class="btn btn-default" style="border-radius: 10px; padding: 12px 16px;"><i class="fa fa-arrow-left"></i></a>
                        <div class="ccx-page-header-icon">
                            <i class="fa fa-bullhorn"></i>
                        </div>
                        <div>
                            <h4>Campaigns</h4>
                            <p>Schedule and manage mass messaging campaigns.</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <?php if (sms_wa_email_can('auto_schedulers')) { ?>
                        <a href="<?php echo admin_url('sms_wa_email/auto_schedulers'); ?>" class="btn btn-default" style="border-radius: 10px; padding: 9px 18px; font-weight: 600;">
                            <i class="fa fa-refresh"></i> Auto Schedulers
                        </a>
                        <?php } ?>
                        <a href="<?php echo admin_url('sms_wa_email/campaigns/create'); ?>" class="ccx-btn-save">
                            <i class="fa fa-plus"></i> New Campaign
                        </a>
                    </div>
                </div>

                <div class="ccx-main-card panel_s">
                    <div class="panel-body">
                        <?php render_datatable([
                            _l('id'),
                            'Name',
                            'Channel',
                            'Schedule Date',
                            'Targets',
                            'Status',
                            _l('options')
                        ], 'sms-wa-email-campaigns'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
    $(function(){
        initDataTable('.table-sms-wa-email-campaigns', admin_url + 'sms_wa_email/campaigns', undefined, undefined, 'undefined', [0, 'desc']);
    });
</script>
</body>
</html>
