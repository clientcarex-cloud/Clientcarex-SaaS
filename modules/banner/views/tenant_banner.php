<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info alert-dismissible alert-dismissible-2">
                    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                    <span><?php echo _l('tenant_banner_info'); ?></span>
                </div>
            </div>

            <!-- Form -->
            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold" id="tenant-banner-form-title">
                            <?php echo _l('add_tenant_banner'); ?>
                        </h4>
                        <hr />
                        <?php echo form_open_multipart(admin_url('banner/banner_tenant/save'), ['id' => 'tenant-banner-form']); ?>
                        <input type="hidden" name="id" id="tenant_banner_id" value="">

                        <?php echo render_input('title', 'banner_title', '', 'text', ['required' => true]); ?>

                        <div class="row">
                            <?php echo render_date_input('start_date', 'start_date', '', ['data-date-min-date' => date('Y-m-d')], [], 'col-md-6'); ?>
                            <?php echo render_date_input('end_date', 'end_date', '', ['data-date-min-date' => date('Y-m-d')], [], 'col-md-6'); ?>
                        </div>

                        <div class="form-group">
                            <label for="banner_image" class="control-label"><?php echo _l('image'); ?></label>
                            <input type="file" name="banner_image" id="banner_image" class="form-control" accept=".jpg,.jpeg,.png,.bmp,.webp">
                            <span class="text-muted"><?php echo _l('allowed_extension_note_for_banner'); ?></span><br>
                            <span class="text-muted"><?php echo _l('recommended_banner_image_is', '1600 x 300'); ?></span>
                            <div id="current_image_wrapper" class="hide mtop10">
                                <img src="" id="current_image" class="img img-responsive tw-rounded">
                                <small class="text-muted"><?php echo _l('tenant_banner_keep_image_note'); ?></small>
                            </div>
                        </div>

                        <hr />

                        <!-- Where to show -->
                        <label class="control-label"><?php echo _l('tenant_banner_publish_to'); ?></label>
                        <div class="radio radio-primary">
                            <input type="radio" name="target_type" id="target_all" value="all" checked>
                            <label for="target_all"><?php echo _l('all_tenants'); ?></label>
                        </div>
                        <div class="radio radio-primary">
                            <input type="radio" name="target_type" id="target_selected" value="selected">
                            <label for="target_selected"><?php echo _l('selected_tenants'); ?></label>
                        </div>

                        <div id="target_slugs_wrapper" class="form-group hide">
                            <label for="target_slugs" class="control-label"><?php echo _l('select_tenants'); ?></label>
                            <select name="target_slugs[]" id="target_slugs" multiple class="selectpicker" data-live-search="true" data-actions-box="true" data-none-selected-text="<?php echo _l('select_tenants'); ?>" data-width="100%">
                                <?php foreach ($companies as $c) { ?>
                                    <option value="<?php echo html_escape($c['slug']); ?>"><?php echo html_escape($c['name']); ?></option>
                                <?php } ?>
                            </select>
                            <?php if (empty($companies)) { ?>
                                <p class="text-muted mtop5"><?php echo _l('tenant_banner_no_tenants'); ?></p>
                            <?php } ?>
                        </div>

                        <hr />

                        <!-- Display area -->
                        <label class="control-label"><?php echo _l('section'); ?></label>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="admin_area" id="admin_area" value="1" checked>
                            <label for="admin_area"><?php echo _l('admin_area'); ?></label>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="clients_area" id="clients_area" value="1">
                            <label for="clients_area"><?php echo _l('clients_area'); ?></label>
                        </div>

                        <hr />

                        <!-- Action button -->
                        <label><?php echo _l('has_action'); ?></label>
                        <div class="onoffswitch" data-toggle="tooltip" data-title="<?php echo _l('has_action'); ?>">
                            <input type="checkbox" name="has_action" class="onoffswitch-checkbox" id="has_action" value="1">
                            <label class="onoffswitch-label" for="has_action"></label>
                        </div>
                        <div class="has_action_fields hide mtop10">
                            <?php echo render_input('action_label', 'action_label', '', 'text'); ?>
                            <?php echo render_color_picker('label_color', _l('label_color'), ''); ?>
                            <?php echo render_input('action_url', 'action_url', '', 'text'); ?>
                            <label><?php echo _l('open_new_tab'); ?></label>
                            <div class="onoffswitch" data-toggle="tooltip" data-title="<?php echo _l('open_new_tab'); ?>">
                                <input type="checkbox" name="action_target" class="onoffswitch-checkbox" id="action_target" value="1">
                                <label class="onoffswitch-label" for="action_target"></label>
                            </div>
                        </div>

                        <div class="tw-mt-6">
                            <button type="submit" class="btn btn-primary" id="save-tenant-banner"><?php echo _l('save'); ?></button>
                            <button type="button" class="btn btn-default hide" id="cancel-tenant-banner-edit"><?php echo _l('cancel'); ?></button>
                        </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>

            <!-- List -->
            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold"><?php echo _l('tenant_banner'); ?></h4>
                        <hr />
                        <div class="table-responsive">
                            <table class="table dt-table table-tenant-banners">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('image'); ?></th>
                                        <th><?php echo _l('title'); ?></th>
                                        <th><?php echo _l('tenant_banner_publish_to'); ?></th>
                                        <th><?php echo _l('start_date'); ?></th>
                                        <th><?php echo _l('end_date'); ?></th>
                                        <th><?php echo _l('status'); ?></th>
                                        <th><?php echo _l('options'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($banners as $b) {
                                        $slugs = (function_exists('is_serialized') && is_serialized($b->target_slugs)) ? unserialize($b->target_slugs) : [];
                                        $target_label = ('selected' === $b->target_type)
                                            ? _l('selected_tenants') . ' (' . count($slugs) . ')'
                                            : _l('all_tenants');
                                    ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($b->image_url)) { ?>
                                                    <img src="<?php echo html_escape($b->image_url); ?>" style="width:90px;height:auto;" class="tw-rounded">
                                                <?php } ?>
                                            </td>
                                            <td><?php echo html_escape($b->title); ?></td>
                                            <td><?php echo $target_label; ?></td>
                                            <td data-order="<?php echo html_escape($b->start_date); ?>"><?php echo _d($b->start_date); ?></td>
                                            <td data-order="<?php echo html_escape($b->end_date); ?>"><?php echo _d($b->end_date); ?></td>
                                            <td>
                                                <div class="onoffswitch" data-toggle="tooltip" data-title="<?php echo _l('banner_status_tooltip'); ?>">
                                                    <input type="checkbox" data-switch-url="<?php echo admin_url('banner/banner_tenant/change_status'); ?>" name="onoffswitch" class="onoffswitch-checkbox" id="tb-status-<?php echo $b->id; ?>" data-id="<?php echo $b->id; ?>" <?php echo (1 == $b->status) ? 'checked' : ''; ?>>
                                                    <label class="onoffswitch-label" for="tb-status-<?php echo $b->id; ?>"></label>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="#" class="btn btn-default btn-icon edit-tenant-banner" data-banner='<?php echo html_escape(json_encode([
                                                    'id'            => $b->id,
                                                    'title'         => $b->title,
                                                    'start_date'    => _d($b->start_date),
                                                    'end_date'      => _d($b->end_date),
                                                    'image_url'     => $b->image_url,
                                                    'admin_area'    => (int) $b->admin_area,
                                                    'clients_area'  => (int) $b->clients_area,
                                                    'has_action'    => (int) $b->has_action,
                                                    'action_label'  => $b->action_label,
                                                    'label_color'   => $b->label_color,
                                                    'action_url'    => $b->action_url,
                                                    'action_target' => (int) $b->action_target,
                                                    'target_type'   => $b->target_type,
                                                    'target_slugs'  => array_values((array) $slugs),
                                                ])); ?>'>
                                                    <i class="fa-regular fa-pen-to-square"></i>
                                                </a>
                                                <a href="<?php echo admin_url('banner/banner_tenant/delete/' . $b->id); ?>" class="btn btn-danger btn-icon _delete">
                                                    <i class="fa-regular fa-trash-can"></i>
                                                </a>
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
<?php init_tail(); ?>
<script>
    $(function () {
        // Toggle selected-tenants picker
        function toggleTargetSlugs() {
            if ($('input[name="target_type"]:checked').val() === 'selected') {
                $('#target_slugs_wrapper').removeClass('hide');
            } else {
                $('#target_slugs_wrapper').addClass('hide');
            }
        }
        $('input[name="target_type"]').on('change', toggleTargetSlugs);

        // Toggle action fields
        $('#has_action').on('change', function () {
            $('.has_action_fields').toggleClass('hide', !$(this).is(':checked'));
        });

        // Reset the form back to "add" mode
        function resetForm() {
            var form = $('#tenant-banner-form')[0];
            form.reset();
            $('#tenant_banner_id').val('');
            $('#tenant-banner-form-title').text('<?php echo _l('add_tenant_banner'); ?>');
            $('#current_image_wrapper').addClass('hide');
            $('#current_image').attr('src', '');
            $('#cancel-tenant-banner-edit').addClass('hide');
            $('#target_all').prop('checked', true);
            $('#target_slugs').selectpicker('deselectAll').selectpicker('refresh');
            $('#has_action').prop('checked', false).trigger('change');
            toggleTargetSlugs();
        }
        $('#cancel-tenant-banner-edit').on('click', resetForm);

        // Populate the form from a table row for editing
        $('.edit-tenant-banner').on('click', function (e) {
            e.preventDefault();
            var d = $(this).data('banner');

            $('#tenant_banner_id').val(d.id);
            $('input[name="title"]').val(d.title);
            $('input[name="start_date"]').val(d.start_date);
            $('input[name="end_date"]').val(d.end_date);

            if (d.image_url) {
                $('#current_image').attr('src', d.image_url);
                $('#current_image_wrapper').removeClass('hide');
            } else {
                $('#current_image_wrapper').addClass('hide');
            }

            $('#admin_area').prop('checked', d.admin_area == 1);
            $('#clients_area').prop('checked', d.clients_area == 1);

            $('#has_action').prop('checked', d.has_action == 1).trigger('change');
            $('input[name="action_label"]').val(d.action_label || '');
            $('input[name="action_url"]').val(d.action_url || '');
            $('input[name="label_color"]').val(d.label_color || '');
            $('#action_target').prop('checked', d.action_target == 1);

            if (d.target_type === 'selected') {
                $('#target_selected').prop('checked', true);
                $('#target_slugs').selectpicker('val', d.target_slugs || []);
            } else {
                $('#target_all').prop('checked', true);
                $('#target_slugs').selectpicker('deselectAll');
            }
            $('#target_slugs').selectpicker('refresh');
            toggleTargetSlugs();

            $('#tenant-banner-form-title').text('<?php echo _l('edit_tenant_banner'); ?>');
            $('#cancel-tenant-banner-edit').removeClass('hide');
            $('html, body').animate({ scrollTop: $('#tenant-banner-form').offset().top - 80 }, 300);
        });
    });
</script>
