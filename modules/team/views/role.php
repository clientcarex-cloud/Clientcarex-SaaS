<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-7">

                <div class="team-header">
                    <h4>
                        <?= e($title); ?>
                    </h4>
                    <a href="<?php echo admin_url('team/roles'); ?>" class="btn-cancel">
                        <i class="fa fa-arrow-left"></i> Back to Roles
                    </a>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <?= form_open($this->uri->uri_string()); ?>
                        <?php if (isset($role)) { ?>
                            <?php if (total_rows(db_prefix() . 'staff', ['role' => $role->roleid]) > 0) { ?>
                                <div class="alert alert-warning bold">
                                    <?= _l('change_role_permission_warning'); ?>
                                    <div class="checkbox">
                                        <input type="checkbox" name="update_staff_permissions" id="update_staff_permissions">
                                        <label for="update_staff_permissions">
                                            <?= _l('role_update_staff_permissions'); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } ?>
                        <?php $attrs = (isset($role) ? [] : ['autofocus' => true]); ?>
                        <?php $value = (isset($role) ? $role->name : ''); ?>
                        <?= render_input('name', 'role_add_edit_name', $value, 'text', $attrs); ?>

                        <div class="clearfix mtop15"></div>
                        <h5 class="mbot15">
                            <?php echo _l('staff_add_edit_permissions'); ?>
                        </h5>
                        <?php $this->load->view('team/permissions', ['funcData' => ['role' => $role ?? null], 'role' => $role ?? null]); ?>

                        <hr />
                        <?php if (isset($role)) { ?>
                            <?php if (has_permission('team_roles', '', 'create') || has_permission('team_roles', '', 'edit')) { ?>
                                <div class="team-actions">
                                    <button type="submit" class="btn-save">
                                        <?php echo _l('submit'); ?>
                                    </button>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <?php if (has_permission('team_roles', '', 'create')) { ?>
                                <div class="team-actions">
                                    <button type="submit" class="btn-save">
                                        <?php echo _l('submit'); ?>
                                    </button>
                                </div>
                            <?php } ?>
                        <?php } ?>
                        <?= form_close(); ?>
                    </div>
                </div>
            </div>

            <?php if (isset($role_staff)) { ?>
                <div class="col-md-5">
                    <div class="team-header">
                        <h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700">
                            <?= _l('staff_which_are_using_role'); ?>
                        </h4>
                    </div>
                    <div class="panel_s tw-mt-3">
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table dt-table">
                                    <thead>
                                        <tr>
                                            <th>
                                                <?= _l('staff_dt_name'); ?>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($role_staff as $staff) { ?>
                                            <tr>
                                                <td>
                                                    <?= '<a href="' . admin_url('team/member/' . $staff['staffid']) . '">' . staff_profile_image($staff['staffid'], [
                                                        'staff-profile-image-small',
                                                    ]) . '</a>';
                                                    echo ' <a href="' . admin_url('team/member/' . $staff['staffid']) . '">' . e($staff['firstname'] . ' ' . $staff['lastname']) . '</a>';
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function () {
        appValidateForm($('form'), {
            name: 'required'
        });
    });
</script>