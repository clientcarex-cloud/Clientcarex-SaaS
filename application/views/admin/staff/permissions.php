<div class="table-responsive">
    <table class="table table-bordered roles no-margin">
        <thead>
            <tr>
                <th>
                    <?= _l('features'); ?>
                    <div class="checkbox mass_select_all_wrap text-left mtop5" style="display:inline-block; margin-left:15px; margin-top:0;">
                        <input type="checkbox" id="chk_bulk_all" data-to-table="roles" onclick="toggle_bulk_permissions(this)">
                        <label for="chk_bulk_all"><?= _l('select_all') != 'select_all' ? _l('select_all') : 'Select All'; ?></label>
                    </div>
                </th>
                <th><?= _l('capabilities') ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (isset($member)) {
                $is_admin = is_admin($member->staffid);
            }

                $excluded_permissions = [
                    'bulk_pdf_exporter',
                    'contracts',
                    'credit_notes',
                    'customers',
                    'email_templates',
                    'estimates',
                    'items',
                    'projects',
                    'proposals',
                    'reports',
                    'roles',
                    'settings',
                    'staff',
                    'subscriptions',
                    'tasks',
                    'checklist_templates',
                    'estimate_request',
                    'leads',
                    'invoices',
                    'knowledge_base',
                    'payments',
                    'expenses',
                ];

                foreach (get_available_staff_permissions($funcData) as $feature => $permission) {
                    if (in_array($feature, $excluded_permissions)) {
                        continue;
                    }  ?>
            <tr data-name="<?= e($feature); ?>">
                <td style="vertical-align: middle;">
                   <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="checkbox" style="margin:0;">
                            <input type="checkbox" class="select-all-row" onclick="toggle_row_permissions(this)">
                            <label></label>
                        </div>
                        <b style="font-size: 14px;"><?= e($permission['name']); ?></b>
                   </div>
                </td>
                <td>
                    <?php
                         if (isset($permission['before'])) {
                             echo $permission['before'];
                         }
                    ?>
                    <?php foreach ($permission['capabilities'] as $capability => $name) {
                        $checked  = '';
                        $disabled = '';
                        if ((isset($is_admin) && $is_admin)
                   || (is_array($name) && isset($name['not_applicable']) && $name['not_applicable'])
                   || (
                       ($capability == 'view_own' || $capability == 'view'
                          && array_key_exists('view_own', $permission['capabilities']) && array_key_exists('view', $permission['capabilities']))
                        && (
                            (isset($member)
                         && staff_can(($capability == 'view' ? 'view_own' : 'view'), $feature, $member->staffid))
                        || (isset($role)
                         && has_role_permission($role->roleid, ($capability == 'view' ? 'view_own' : 'view'), $feature))
                        )
                   )
                        ) {
                            $disabled = ' disabled ';
                        } elseif ((isset($member) && staff_can($capability, $feature, $member->staffid))
                    || isset($role) && has_role_permission($role->roleid, $capability, $feature)) {
                            $checked = ' checked ';
                        } ?>
                    <div class="tw-ml-2">
                        <div class="checkbox last:tw-mb-0">
                            <input
                                <?php if ($capability == 'view') { ?>
                            data-can-view <?php } ?>
                            <?php if ($capability == 'view_own') { ?>
                            data-can-view-own <?php } ?>
                            <?php if (is_array($name) && isset($name['not_applicable']) && $name['not_applicable']) { ?>
                            data-not-applicable="true" <?php } ?>
                            type="checkbox"
                            <?= e($checked); ?>
                            class="capability"
                            id="<?= $feature . '_' . $capability; ?>"
                            name="permissions[<?= e($feature); ?>][]"
                            value="<?= e($capability); ?>"
                            onclick="check_row_state(this)"
                            <?= e($disabled); ?>>
                            <label
                                for="<?= $feature . '_' . $capability; ?>">
                                <?= ! is_array($name) ? $name : $name['name']; ?>
                            </label>
                            <?php
                        if (isset($permission['help']) && array_key_exists($capability, $permission['help'])) {
                            echo '<i class="fa-regular fa-circle-question" data-toggle="tooltip" data-title="' . e($permission['help'][$capability]) . '"></i>';
                        } ?>
                        </div>
                        </div>
                    </div>
                    <?php
                    } ?>
                    <?php if ($feature == 'visits' && array_key_exists('staff_id', $funcData)) { ?>
                    <div class="tw-ml-2 tw-mt-2">
                         <label for="max_discount" class="tw-text-sm"><?= _l('Max Discount Limit (%)'); ?></label>
                         <div class="input-group input-group-sm" style="width: 150px;">
                             <input type="number" name="max_discount"
                                 value="<?= isset($member) ? $member->max_discount : ''; ?>"
                                 id="max_discount" class="form-control" max="100" min="0" step="0.01">
                             <span class="input-group-addon">%</span>
                         </div>
                    </div>
                    <?php } ?>
                    <?php
                    if (isset($permission['after'])) {
                        echo $permission['after'];
                    }
                    ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>


<script>
    // Define global functions to handle permission clicks
    window.toggle_bulk_permissions = function(source) {
        var checked = $(source).prop('checked');
        var table = $(source).closest('table');
        
        // Toggle all capability checkboxes
        table.find('input[type="checkbox"].capability').not('[data-not-applicable="true"]').each(function() {
            $(this).prop('checked', checked).trigger('change');
        });

        // Toggle all row select checkboxes
        table.find('.select-all-row').prop('checked', checked);
    };

    window.toggle_row_permissions = function(source) {
        var checked = $(source).prop('checked');
        var row = $(source).closest('tr');
        
        // Toggle capabilities in this row
        row.find('input[type="checkbox"].capability').not('[data-not-applicable="true"]').each(function() {
            $(this).prop('checked', checked).trigger('change');
        });
    };

    window.check_row_state = function(source) {
        var row = $(source).closest('tr');
        var total_checkboxes = row.find('input[type="checkbox"].capability').not('[data-not-applicable="true"]').length;
        var checked_checkboxes = row.find('input[type="checkbox"].capability:checked').not('[data-not-applicable="true"]').length;
        
        if (total_checkboxes > 0 && total_checkboxes == checked_checkboxes) {
            row.find('.select-all-row').prop('checked', true);
        } else {
            row.find('.select-all-row').prop('checked', false);
        }
    };

    $(function(){
        // Check states on load
        $('.roles tbody tr').each(function(){
            var row = $(this);
            var total_checkboxes = row.find('input[type="checkbox"].capability').not('[data-not-applicable="true"]').length;
            var checked_checkboxes = row.find('input[type="checkbox"].capability:checked').not('[data-not-applicable="true"]').length;
            
            if(total_checkboxes > 0 && total_checkboxes == checked_checkboxes){
                 row.find('.select-all-row').prop('checked', true);
            }
        });
    });
</script>