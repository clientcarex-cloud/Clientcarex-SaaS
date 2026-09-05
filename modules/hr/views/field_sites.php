<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Saved work locations used to geofence field punches. Optional: with no site
 * saved, every punch is simply recorded with its coordinates.
 */
$can_edit   = has_permission('hr_field_attendance', '', 'edit') || is_admin();
$can_delete = has_permission('hr_field_attendance', '', 'delete') || is_admin();
$mode_label = [
    'off'   => 'Allowed anywhere (distance recorded only)',
    'warn'  => 'Allowed, but out-of-range punches are flagged',
    'block' => 'Punching outside every location is blocked',
];
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                            <h4 class="bold" style="margin:0;"><i class="fa fa-map-pin text-info"></i> Field Work Locations</h4>
                            <div>
                                <?php if ($can_edit) { ?>
                                    <button type="button" class="btn btn-primary" id="fs_add"><i class="fa fa-plus"></i> New Location</button>
                                <?php } ?>
                                <a href="<?php echo admin_url('hr/field_attendance'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Field Attendance</a>
                                <a href="<?php echo admin_url('hr/settings'); ?>#field-attendance" class="btn btn-default"><i class="fa fa-cog"></i> Settings</a>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />

                        <p class="text-muted" style="font-size:12px;">
                            Each field punch is matched to the nearest active location and its distance is stored.
                            Current rule: <strong><?php echo html_escape($mode_label[$cfg['geofence_mode']]); ?></strong>.
                        </p>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Location</th>
                                        <th>Address</th>
                                        <th>Coordinates</th>
                                        <th class="text-center">Radius</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!count($sites)) { ?>
                                        <tr><td colspan="6" class="text-muted">No work location saved yet — geofencing is not applied.</td></tr>
                                    <?php }
                                    foreach ($sites as $s) { ?>
                                        <tr>
                                            <td><strong><?php echo html_escape($s['name']); ?></strong></td>
                                            <td style="font-size:12px;"><?php echo $s['address'] ? html_escape($s['address']) : '<span class="text-muted">&mdash;</span>'; ?></td>
                                            <td style="font-size:12px;">
                                                <a href="<?php echo hr_field_map_url($s['latitude'], $s['longitude']); ?>" target="_blank" rel="noopener">
                                                    <?php echo html_escape(number_format((float) $s['latitude'], 5) . ', ' . number_format((float) $s['longitude'], 5)); ?>
                                                </a>
                                            </td>
                                            <td class="text-center"><?php echo (int) $s['radius_m']; ?> m</td>
                                            <td class="text-center">
                                                <?php if ((int) $s['is_active'] === 1) { ?>
                                                    <span class="label label-success">Active</span>
                                                <?php } else { ?>
                                                    <span class="label label-default">Inactive</span>
                                                <?php } ?>
                                            </td>
                                            <td class="text-right" style="white-space:nowrap;">
                                                <?php if ($can_edit) { ?>
                                                    <button type="button" class="btn btn-default btn-xs fs-edit"
                                                        data-site='<?php echo html_escape(json_encode([
                                                            'id'        => (int) $s['id'],
                                                            'name'      => $s['name'],
                                                            'address'   => $s['address'],
                                                            'latitude'  => $s['latitude'],
                                                            'longitude' => $s['longitude'],
                                                            'radius_m'  => (int) $s['radius_m'],
                                                            'is_active' => (int) $s['is_active'],
                                                        ])); ?>'><i class="fa fa-pencil"></i></button>
                                                <?php } ?>
                                                <?php if ($can_delete) { ?>
                                                    <a href="<?php echo admin_url('hr/delete_field_site/' . (int) $s['id']); ?>"
                                                       class="btn btn-danger btn-xs _delete"><i class="fa fa-trash"></i></a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($can_edit) { ?>
<div class="modal fade" id="fs_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <?php echo form_open(admin_url('hr/save_field_site')); ?>
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title" id="fs_title">New Location</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="fs_id" value="">
                <div class="form-group">
                    <label>Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="fs_name" class="form-control" maxlength="150" placeholder="e.g. Main Hospital — Gate 2" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" id="fs_address" class="form-control" maxlength="255">
                </div>
                <div class="row">
                    <div class="col-md-6"><div class="form-group">
                        <label>Latitude <span class="text-danger">*</span></label>
                        <input type="text" name="latitude" id="fs_lat" class="form-control" placeholder="28.6139" required>
                    </div></div>
                    <div class="col-md-6"><div class="form-group">
                        <label>Longitude <span class="text-danger">*</span></label>
                        <input type="text" name="longitude" id="fs_lng" class="form-control" placeholder="77.2090" required>
                    </div></div>
                </div>
                <button type="button" class="btn btn-default btn-sm" id="fs_here"><i class="fa fa-location-crosshairs"></i> Use my current location</button>
                <span id="fs_here_msg" class="text-muted" style="font-size:11px;margin-left:6px;"></span>
                <p class="text-muted" style="font-size:11px;margin-top:8px;">
                    Tip: open the place in Google Maps, right-click it and copy the coordinates.
                </p>
                <div class="form-group" style="margin-top:8px;">
                    <label>Allowed radius (metres)</label>
                    <input type="number" name="radius_m" id="fs_radius" class="form-control" min="10" max="50000" value="200">
                    <small class="text-muted">A punch inside this radius counts as “in range”.</small>
                </div>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" id="fs_active" name="is_active" value="1" checked>
                    <label for="fs_active">Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> Save</button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>
<?php } ?>
<?php init_tail(); ?>
<script>
    $(function () {
        function resetModal() {
            $('#fs_title').text('New Location');
            $('#fs_id').val('');
            $('#fs_name, #fs_address, #fs_lat, #fs_lng').val('');
            $('#fs_radius').val(200);
            $('#fs_active').prop('checked', true);
            $('#fs_here_msg').text('');
        }

        $('#fs_add').on('click', function () {
            resetModal();
            $('#fs_modal').modal('show');
        });

        $('.fs-edit').on('click', function () {
            var s = $(this).data('site');
            resetModal();
            $('#fs_title').text('Edit Location');
            $('#fs_id').val(s.id);
            $('#fs_name').val(s.name);
            $('#fs_address').val(s.address || '');
            $('#fs_lat').val(s.latitude);
            $('#fs_lng').val(s.longitude);
            $('#fs_radius').val(s.radius_m);
            $('#fs_active').prop('checked', s.is_active == 1);
            $('#fs_modal').modal('show');
        });

        $('#fs_here').on('click', function () {
            var msg = $('#fs_here_msg').text('Locating…');
            if (!navigator.geolocation) {
                msg.text('This browser cannot share a location.');
                return;
            }
            navigator.geolocation.getCurrentPosition(function (pos) {
                $('#fs_lat').val(pos.coords.latitude.toFixed(7));
                $('#fs_lng').val(pos.coords.longitude.toFixed(7));
                msg.text('Filled from this device (±' + Math.round(pos.coords.accuracy) + ' m).');
            }, function () {
                msg.text('Could not read your location — enter the coordinates manually.');
            }, { enableHighAccuracy: true, timeout: 15000 });
        });
    });
</script>
