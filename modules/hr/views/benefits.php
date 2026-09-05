<?php defined('BASEPATH') or exit('No direct script access allowed');

$can_edit   = has_permission('hr_benefits', '', 'edit') || is_admin();
$can_create = has_permission('hr_benefits', '', 'create') || is_admin();
$can_delete = has_permission('hr_benefits', '', 'delete') || is_admin();

$icon_choices = ['fa-gift', 'fa-university', 'fa-heartbeat', 'fa-medkit', 'fa-trophy', 'fa-star',
    'fa-graduation-cap', 'fa-shield-halved', 'fa-money-bill', 'fa-money-bill-wave', 'fa-sack-dollar',
    'fa-coins', 'fa-hand-holding-dollar', 'fa-building-columns', 'fa-chart-line', 'fa-plane',
    'fa-house', 'fa-truck', 'fa-car', 'fa-briefcase', 'fa-umbrella', 'fa-baby', 'fa-person',
    'fa-person-dress', 'fa-child', 'fa-users', 'fa-handshake', 'fa-utensils', 'fa-bus', 'fa-taxi',
    'fa-laptop', 'fa-stethoscope', 'fa-user-doctor', 'fa-bicycle', 'fa-dumbbell', 'fa-mobile-screen-button',
    'fa-wifi', 'fa-book', 'fa-suitcase', 'fa-cake-candles', 'fa-mug-hot', 'fa-clock', 'fa-calendar-check',
    'fa-square-plus', 'fa-life-ring', 'fa-ribbon', 'fa-medal'];
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <style>
            .ben-cat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px}
            .ben-card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.05);border:1px solid rgba(0,0,0,.04);padding:18px;position:relative;display:flex;flex-direction:column}
            .ben-ic{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;margin-bottom:12px}
            .ben-name{font-size:16px;font-weight:800;color:#1e293b}
            .ben-desc{font-size:13px;color:#64748b;margin:6px 0 10px;min-height:36px}
            .ben-badge{display:inline-block;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:#eef2ff;color:#4338ca;margin:2px 4px 2px 0}
            .ben-badges{margin-top:2px}
            .ben-foot{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:auto;padding-top:12px;border-top:1px solid #f1f5f9}
            .ben-scope{font-size:12px;color:#94a3b8}
            .ben-actions{position:absolute;top:12px;right:12px}
            .ben-off{opacity:.6}
            .ben-off .ben-ic{filter:grayscale(.7)}
            .ben-switch{position:relative;display:inline-block;width:42px;height:23px;margin:0;flex-shrink:0}
            .ben-switch input{opacity:0;width:0;height:0}
            .ben-slider{position:absolute;inset:0;background:#cbd5e1;border-radius:999px;transition:.2s;cursor:pointer}
            .ben-slider:before{content:"";position:absolute;height:17px;width:17px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
            .ben-switch input:checked + .ben-slider{background:#16a34a}
            .ben-switch input:checked + .ben-slider:before{transform:translateX(19px)}
            .ben-switch-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;margin-right:6px}
        </style>

        <div class="row">
            <div class="col-md-8"><h4 class="bold" style="margin-top:6px;"><i class="fa fa-gift text-info"></i> Employee Benefits Catalog</h4>
                <p class="text-muted">Define the benefits your organisation offers and choose who receives each — all staff, selected roles, or specific employees. Employees see their benefits on <strong>My HR ▸ My Dashboard</strong>.</p>
            </div>
            <div class="col-md-4 text-right">
                <a href="<?php echo admin_url('hr'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>
                <?php if ($can_create) { ?>
                    <button type="button" class="btn btn-primary" onclick="openBenefitModal()"><i class="fa fa-plus"></i> Add Benefit</button>
                <?php } ?>
            </div>
        </div>

        <?php if (!count($benefits)) { ?>
            <div class="ben-card text-muted text-center">No benefits defined yet. Click <strong>Add Benefit</strong> to create one.</div>
        <?php } ?>
        <div class="ben-cat-grid">
            <?php
            foreach ($benefits as $b) {
                $scope = $b['applies_to'] === 'all' ? 'All employees'
                    : ($b['applies_to'] === 'roles'
                        ? (count(json_decode($b['role_ids'], true) ?: []) . ' role(s)')
                        : (count(json_decode($b['staff_ids'], true) ?: []) . ' employee(s)')); ?>
                    <div class="ben-card <?php echo $b['is_active'] ? '' : 'ben-off'; ?>">
                        <div class="ben-actions">
                            <?php if ($can_edit) {
                                $bj = $b; $bj['icon'] = hr_normalize_icon($b['icon']); ?>
                                <button type="button" class="btn btn-default btn-icon btn-xs" onclick='openBenefitModal(<?php echo json_encode($bj, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG); ?>)'><i class="fa fa-pencil"></i></button>
                            <?php } ?>
                            <?php if ($can_delete) { ?>
                                <a href="<?php echo admin_url('hr/delete_benefit/' . $b['id']); ?>" class="btn btn-danger btn-icon btn-xs _delete"><i class="fa fa-remove"></i></a>
                            <?php } ?>
                        </div>
                        <div class="ben-ic" style="background:<?php echo html_escape($b['color']); ?>;"><i class="fa <?php echo html_escape(hr_normalize_icon($b['icon'])); ?>"></i></div>
                        <div class="ben-name"><?php echo html_escape($b['name']); ?><?php if (!$b['is_active']) { ?> <small class="text-muted ben-inactive">(hidden)</small><?php } ?></div>
                        <div class="ben-desc"><?php echo html_escape($b['description']); ?></div>
                        <div class="ben-badges">
                            <?php if (!empty($b['value_label'])) { ?><span class="ben-badge"><?php echo html_escape($b['value_label']); ?></span><?php } ?>
                            <?php if ($b['benefit_type'] === 'vesting') { ?>
                                <span class="ben-badge" style="background:#f3e8ff;color:#7c3aed;"><i class="fa fa-hourglass-half"></i> Vests in <?php echo (float) $b['vesting_years']; ?> yr</span>
                            <?php } ?>
                        </div>
                        <div class="ben-foot">
                            <span class="ben-scope"><i class="fa fa-users"></i> <?php echo html_escape($scope); ?></span>
                            <?php if ($can_edit) { ?>
                                <span style="display:flex;align-items:center;">
                                    <span class="ben-switch-lbl" style="color:<?php echo $b['is_active'] ? '#16a34a' : '#94a3b8'; ?>"><?php echo $b['is_active'] ? 'Shown' : 'Hidden'; ?></span>
                                    <label class="ben-switch" title="Show / hide this benefit in ESS">
                                        <input type="checkbox" class="ben-toggle" data-id="<?php echo (int) $b['id']; ?>" <?php echo $b['is_active'] ? 'checked' : ''; ?>>
                                        <span class="ben-slider"></span>
                                    </label>
                                </span>
                            <?php } ?>
                        </div>
                    </div>
            <?php } ?>
        </div>
    </div>

    <div class="modal fade" id="benefit_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <?php echo form_open(admin_url('hr/save_benefit'), ['id' => 'benefit_form']); ?>
                <input type="hidden" name="id" id="ben_id">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Benefit</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label>Name</label>
                        <input type="text" name="name" id="ben_name" class="form-control" required></div>
                    <div class="form-group"><label>Description</label>
                        <textarea name="description" id="ben_description" class="form-control" rows="2"></textarea></div>
                    <div class="row">
                        <div class="col-md-6 form-group"><label>Value / Summary <small class="text-muted">(optional)</small></label>
                            <input type="text" name="value_label" id="ben_value_label" class="form-control" placeholder="e.g. Up to 5,00,000 cover"></div>
                        <div class="col-md-3 form-group"><label>Icon</label>
                            <select name="icon" id="ben_icon" class="form-control">
                                <?php foreach ($icon_choices as $ic) { ?>
                                    <option value="<?php echo $ic; ?>"><?php echo $ic; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-3 form-group"><label>Colour</label>
                            <input type="color" name="color" id="ben_color" class="form-control" value="#4f46e5" style="height:34px;padding:2px;"></div>
                    </div>

                    <div class="form-group"><label>Benefit Type</label>
                        <select name="benefit_type" id="ben_type" class="form-control" onchange="benToggleType()">
                            <option value="standard">Standard (available from joining)</option>
                            <option value="vesting">Time-based / Vesting (earned over service)</option>
                        </select>
                    </div>
                    <div class="form-group" id="ben_vesting_wrap" style="display:none;">
                        <label>Vesting period (years)</label>
                        <input type="number" step="0.5" min="0.5" name="vesting_years" id="ben_vesting_years" class="form-control" value="5">
                        <small class="text-muted">Shown as a progress bar in ESS (e.g. Gratuity = 5 years). Eligible date = joining date + this period.</small>
                    </div>

                    <div class="form-group"><label>Applies To</label>
                        <select name="applies_to" id="ben_applies" class="form-control" onchange="benToggleApplies()">
                            <option value="all">All employees</option>
                            <option value="roles">Selected roles</option>
                            <option value="employees">Specific employees</option>
                        </select>
                    </div>
                    <div class="form-group" id="ben_roles_wrap" style="display:none;">
                        <label>Roles</label>
                        <select name="role_ids[]" id="ben_role_ids" class="selectpicker" multiple data-live-search="true" data-width="100%">
                            <?php foreach ($roles as $r) { ?>
                                <option value="<?php echo $r['roleid']; ?>"><?php echo html_escape($r['name']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group" id="ben_emp_wrap" style="display:none;">
                        <label>Employees</label>
                        <select name="staff_ids[]" id="ben_staff_ids" class="selectpicker" multiple data-live-search="true" data-width="100%">
                            <?php foreach ($employees as $e) { ?>
                                <option value="<?php echo $e['staffid']; ?>"><?php echo html_escape($e['firstname'] . ' ' . $e['lastname']); ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group"><label>Sort order</label>
                            <input type="number" name="sort_order" id="ben_sort_order" class="form-control" value="0"></div>
                        <div class="col-md-6" style="margin-top:26px;">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" name="is_active" id="ben_is_active" value="1" checked>
                                <label for="ben_is_active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                </div>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function () {
        $(document).on('change', '.ben-toggle', function () {
            var $t = $(this), id = $t.data('id'), $card = $t.closest('.ben-card'), on = $t.is(':checked');
            $t.prop('disabled', true);
            var data = {};
            if (typeof csrfData !== 'undefined') { data[csrfData.token_name] = csrfData.hash; }
            $.post('<?php echo admin_url('hr/toggle_benefit'); ?>/' + id, data, function (r) {
                if (r && r.success) {
                    var active = (r.is_active == 1);
                    $card.toggleClass('ben-off', !active);
                    $card.find('.ben-switch-lbl').text(active ? 'Shown' : 'Hidden').css('color', active ? '#16a34a' : '#94a3b8');
                    $card.find('.ben-name .ben-inactive').remove();
                    if (!active) { $card.find('.ben-name').append(' <small class="text-muted ben-inactive">(hidden)</small>'); }
                    if (typeof alert_float === 'function') { alert_float('success', active ? 'Benefit is now visible to employees' : 'Benefit hidden from employees'); }
                } else {
                    $t.prop('checked', !on); // revert on failure
                }
            }, 'json').fail(function () {
                $t.prop('checked', !on);
            }).always(function () {
                $t.prop('disabled', false);
            });
        });
    });

    function benToggleType() {
        $('#ben_vesting_wrap').toggle($('#ben_type').val() === 'vesting');
    }
    function benToggleApplies() {
        var v = $('#ben_applies').val();
        $('#ben_roles_wrap').toggle(v === 'roles');
        $('#ben_emp_wrap').toggle(v === 'employees');
    }
    function openBenefitModal(b) {
        var f = $('#benefit_form')[0];
        f.reset();
        $('#ben_id').val('');
        $('#ben_role_ids').selectpicker('deselectAll');
        $('#ben_staff_ids').selectpicker('deselectAll');
        if (b) {
            $('#ben_id').val(b.id);
            $('#ben_name').val(b.name);
            $('#ben_description').val(b.description);
            $('#ben_value_label').val(b.value_label);
            $('#ben_icon').val(b.icon);
            $('#ben_color').val(b.color);
            $('#ben_type').val(b.benefit_type);
            $('#ben_vesting_years').val(b.vesting_years);
            $('#ben_applies').val(b.applies_to);
            $('#ben_sort_order').val(b.sort_order);
            $('#ben_is_active').prop('checked', b.is_active == 1);
            // reflect a live inline toggle if it was changed since page load
            var $live = $('.ben-toggle[data-id="' + b.id + '"]');
            if ($live.length) { $('#ben_is_active').prop('checked', $live.is(':checked')); }
            if (b.role_ids)  { try { $('#ben_role_ids').selectpicker('val', JSON.parse(b.role_ids).map(String)); } catch (e) {} }
            if (b.staff_ids) { try { $('#ben_staff_ids').selectpicker('val', JSON.parse(b.staff_ids).map(String)); } catch (e) {} }
        }
        benToggleType();
        benToggleApplies();
        $('#benefit_modal').modal('show');
    }
</script>
