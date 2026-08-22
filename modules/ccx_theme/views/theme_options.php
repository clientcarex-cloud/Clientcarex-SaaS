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

                        <?php echo form_open(admin_url('ccx_theme')); ?>

                        <div class="horizontal-scrollable-tabs">
                            <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
                            <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
                            <div class="horizontal-tabs">
                                <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
                                    <li role="presentation" class="active">
                                        <a href="#admin_menu" aria-controls="admin_menu" role="tab"
                                            data-toggle="tab">Admin side Menus</a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#customers_side" aria-controls="customers_side" role="tab"
                                            data-toggle="tab">Customers Side</a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#buttons" aria-controls="buttons" role="tab"
                                            data-toggle="tab">Buttons</a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#tabs" aria-controls="tabs" role="tab" data-toggle="tab">Tabs</a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#modals" aria-controls="modals" role="tab" data-toggle="tab">Modals</a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#texts" aria-controls="texts" role="tab" data-toggle="tab">Texts</a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#alerts" aria-controls="alerts" role="tab" data-toggle="tab">Alerts</a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#others" aria-controls="others" role="tab" data-toggle="tab">Others</a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#tags" aria-controls="tags" role="tab" data-toggle="tab">Tags</a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="tab-content">
                            <!-- Admin Side Menus -->
                            <div role="tabpanel" class="tab-pane active" id="admin_menu">
                                <div class="row">
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_menu_bg', 'Menu Background Color', get_option('ccx_theme_menu_bg')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_menu_submenu_open_bg', 'Menu Submenu Open Background Color', get_option('ccx_theme_menu_submenu_open_bg')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_menu_links', 'Menu Links Color', get_option('ccx_theme_menu_links')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_menu_user_welcome_bg', 'Menu User Welcome Background Color', get_option('ccx_theme_menu_user_welcome_bg')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_menu_user_welcome_text', 'Menu User Welcome Text Color', get_option('ccx_theme_menu_user_welcome_text')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_menu_active_bg', 'Menu Active Item Background Color', get_option('ccx_theme_menu_active_bg')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_menu_active_color', 'Menu Active Item Color', get_option('ccx_theme_menu_active_color')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_menu_active_subitem_bg', 'Menu Active Subitem Background Color', get_option('ccx_theme_menu_active_subitem_bg')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_menu_submenu_links', 'Submenu Links Color', get_option('ccx_theme_menu_submenu_links')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_header_bg', 'Top Header Background Color', get_option('ccx_theme_header_bg')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_header_links', 'Top Header Links Color', get_option('ccx_theme_header_links')); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Customers Side -->
                            <div role="tabpanel" class="tab-pane" id="customers_side">
                                <div class="row">
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_customers_login_bg', 'Login Background', get_option('ccx_theme_customers_login_bg')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_customers_menu_bg', 'Menu Background Color', get_option('ccx_theme_customers_menu_bg')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_customers_menu_links', 'Menu Links Color', get_option('ccx_theme_customers_menu_links')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_customers_footer_bg', 'Footer Background', get_option('ccx_theme_customers_footer_bg')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_customers_footer_text', 'Footer Text Color', get_option('ccx_theme_customers_footer_text')); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Buttons -->
                            <div role="tabpanel" class="tab-pane" id="buttons">
                                <div class="row">
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_btn_default_bg', 'Button Default', get_option('ccx_theme_btn_default_bg')); ?>
                                        <button class="btn btn-default">Button Default</button>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_btn_primary_bg', 'Button Primary', get_option('ccx_theme_btn_primary_bg')); ?>
                                        <button class="btn btn-primary">Button Primary</button>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_btn_info_bg', 'Button Info', get_option('ccx_theme_btn_info_bg')); ?>
                                        <button class="btn btn-info">Button Info</button>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_btn_success_bg', 'Button Success', get_option('ccx_theme_btn_success_bg')); ?>
                                        <button class="btn btn-success">Button Success</button>
                                    </div>
                                    <div class="col-md-3 mtop15">
                                        <?php echo render_color_picker('ccx_theme_btn_danger_bg', 'Button Danger', get_option('ccx_theme_btn_danger_bg')); ?>
                                        <button class="btn btn-danger">Button Danger</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabs -->
                            <div role="tabpanel" class="tab-pane" id="tabs">
                                <div class="row">
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_tabs_bg', 'Tabs Background Color', get_option('ccx_theme_tabs_bg')); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_tabs_links_color', 'Tabs Links Color', get_option('ccx_theme_tabs_links_color')); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_tabs_hover_color', 'Tabs Link Active/Hover Color', get_option('ccx_theme_tabs_hover_color')); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_tabs_active_border_color', 'Tabs Active Border Color', get_option('ccx_theme_tabs_active_border_color')); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_tabs_border_color', 'Tabs Border Color', get_option('ccx_theme_tabs_border_color')); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Modals -->
                            <div role="tabpanel" class="tab-pane" id="modals">
                                <div class="row">
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_modal_header_bg', 'Heading Background', get_option('ccx_theme_modal_header_bg')); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_modal_header_color', 'Heading Color', get_option('ccx_theme_modal_header_color')); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_modal_close_btn_color', 'Close Button Color', get_option('ccx_theme_modal_close_btn_color')); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_modal_white_text_color', 'Modal White Text Color', get_option('ccx_theme_modal_white_text_color')); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_modal_body_bg', 'Body Background', get_option('ccx_theme_modal_body_bg')); ?>
                                    </div>
                                </div>
                                <div class="row mtop25">
                                    <div class="col-md-12">
                                        <!-- Modal Preview -->
                                        <div style="border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
                                            <div class="modal-header" style="display:block; background: #f1faff;">
                                                <button type="button" class="close" aria-label="Close"><span
                                                        aria-hidden="true">&times;</span></button>
                                                <h4 class="modal-title">Example Modal Heading</h4>
                                            </div>
                                            <div class="modal-body">
                                                <p>Modal Body is here</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Texts -->
                            <div role="tabpanel" class="tab-pane" id="texts">
                                <div class="row">
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_text_muted', 'Text Muted', get_option('ccx_theme_text_muted')); ?>
                                        <p class="text-muted">Example Text Muted</p>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_text_danger', 'Text Danger', get_option('ccx_theme_text_danger')); ?>
                                        <p class="text-danger">Example Text Danger</p>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_text_warning', 'Text Warning', get_option('ccx_theme_text_warning')); ?>
                                        <p class="text-warning">Example Text Warning</p>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_text_info', 'Text Info', get_option('ccx_theme_text_info')); ?>
                                        <p class="text-info">Example Text Info</p>
                                    </div>
                                    <div class="col-md-3 mtop15">
                                        <?php echo render_color_picker('ccx_theme_text_success', 'Text Success', get_option('ccx_theme_text_success')); ?>
                                        <p class="text-success">Example Text Success</p>
                                    </div>
                                    <div class="col-md-3 mtop15">
                                        <?php echo render_color_picker('ccx_theme_text_dark', 'Text Dark', get_option('ccx_theme_text_dark')); ?>
                                        <p class="text-dark">Example Text Dark</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Alerts -->
                            <div role="tabpanel" class="tab-pane" id="alerts">
                                <div class="row">
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_alert_info_text', 'Alert Info Text', get_option('ccx_theme_alert_info_text')); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_alert_danger_text', 'Alert Danger Text', get_option('ccx_theme_alert_danger_text')); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_alert_warning_text', 'Alert Warning Text', get_option('ccx_theme_alert_warning_text')); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_alert_success_text', 'Alert Success Text', get_option('ccx_theme_alert_success_text')); ?>
                                    </div>
                                </div>
                                <div class="row mtop15">
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_alert_info_bg', 'Alert Info Background', get_option('ccx_theme_alert_info_bg')); ?>
                                        <div class="alert alert-info">Example : Alert Info Text</div>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_alert_danger_bg', 'Alert Danger Background', get_option('ccx_theme_alert_danger_bg')); ?>
                                        <div class="alert alert-danger">Example : Alert Danger Text</div>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_alert_warning_bg', 'Alert Warning Background', get_option('ccx_theme_alert_warning_bg')); ?>
                                        <div class="alert alert-warning">Example : Alert Warning Text</div>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo render_color_picker('ccx_theme_alert_success_bg', 'Alert Success Background', get_option('ccx_theme_alert_success_bg')); ?>
                                        <div class="alert alert-success">Example : Alert Success Text</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Others -->
                            <div role="tabpanel" class="tab-pane" id="others">
                                <div class="row">
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_admin_login_bg', 'Admin Login Background', get_option('ccx_theme_admin_login_bg')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_main_bg', 'All Pages Background', get_option('ccx_theme_main_bg')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_main_text_color', 'All Pages Text Color', get_option('ccx_theme_main_text_color')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_input_bg', 'Inputs Background Color', get_option('ccx_theme_input_bg')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_input_border', 'Inputs Border Color', get_option('ccx_theme_input_border')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo render_color_picker('ccx_theme_hr_color', 'Horizontal Line Color', get_option('ccx_theme_hr_color')); ?>
                                    </div>
                                    <div class="col-md-4 mtop10">
                                        <?php echo render_color_picker('ccx_theme_link_color', 'Links Color (href)', get_option('ccx_theme_link_color')); ?>
                                    </div>
                                    <div class="col-md-4 mtop10">
                                        <?php echo render_color_picker('ccx_theme_link_hover_color', 'Links Hover/Focus Color', get_option('ccx_theme_link_hover_color')); ?>
                                    </div>
                                </div>
                                <div class="row mtop15">
                                    <div class="col-md-6">
                                        <?php echo render_color_picker('ccx_theme_panel_bg', 'Admin Panels Background', get_option('ccx_theme_panel_bg')); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?php echo render_color_picker('ccx_theme_panel_heading_color', 'Admin Panels Heading Color', get_option('ccx_theme_panel_heading_color')); ?>
                                    </div>
                                    <div class="col-md-12 mtop10">
                                        <div class="panel_s">
                                            <div class="panel-body">
                                                This is an example Panel
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mtop15">
                                    <div class="col-md-12">
                                        <?php echo render_color_picker('ccx_theme_table_headings_color', 'Table Headings Color', get_option('ccx_theme_table_headings_color')); ?>
                                    </div>
                                    <div class="col-md-12 mtop15">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Example Heading 1</th>
                                                    <th>Example Heading 2</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                    <div class="col-md-12 mtop15">
                                        <?php echo render_color_picker('ccx_theme_table_headings_bg', 'Table Headings Background Color', get_option('ccx_theme_table_headings_bg')); ?>
                                    </div>
                                    <div class="col-md-12 mtop15">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Example Heading 1</th>
                                                    <th>Example Heading 2</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                    <div class="col-md-12 mtop15">
                                        <?php echo render_color_picker('ccx_theme_table_content_bg', 'Table Content Background Color', get_option('ccx_theme_table_content_bg')); ?>
                                    </div>
                                    <div class="col-md-12 mtop15">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Example Heading 1</th>
                                                    <th>Example Heading 2</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>Example Content 1</td>
                                                    <td>Example Content 2</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Tags -->
                            <div role="tabpanel" class="tab-pane" id="tags">
                                <div class="row">
                                    <div class="col-md-12">
                                        <p class="bold">psaas</p>
                                    </div>
                                    <div class="col-md-6">
                                        <?php echo render_color_picker('ccx_theme_tag_bg', '', get_option('ccx_theme_tag_bg')); ?>
                                        <div class="mtop10">
                                            <span class="label label-default">psaas</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="btn-bottom-toolbar text-right">
                            <button type="submit" class="btn btn-info"><?php echo _l('save'); ?></button>
                        </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>