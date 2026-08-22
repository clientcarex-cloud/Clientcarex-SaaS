<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">CCX Login Settings</h4>
                        <hr class="hr-panel-heading" />

                        <div class="row">
                            <div class="col-md-6">
                                <h4>General Settings</h4>
                                <?php echo form_open('ccx_login/save_general_settings'); ?>

                                <div class="form-group">
                                    <label for="ccx_login_split_ratio" class="control-label">Form Split Ratio
                                        (%)</label>
                                    <input type="range" name="ccx_login_split_ratio" id="ccx_login_split_ratio" min="20"
                                        max="80"
                                        value="<?php echo get_option('ccx_login_split_ratio') ? get_option('ccx_login_split_ratio') : 40; ?>"
                                        oninput="this.nextElementSibling.value = this.value + '%'">
                                    <output><?php echo get_option('ccx_login_split_ratio') ? get_option('ccx_login_split_ratio') : 40; ?>%</output>
                                    <p class="help-block">Percentage of screen width occupied by the login form.</p>
                                </div>

                                <div class="form-group">
                                    <label class="control-label">Background Size</label>
                                    <div class="radio radio-primary">
                                        <input type="radio" name="ccx_login_background_size" id="size_cover"
                                            value="cover" <?php echo (get_option('ccx_login_background_size') == 'cover' || !get_option('ccx_login_background_size')) ? 'checked' : ''; ?>>
                                        <label for="size_cover">Fill (Cover) - Image covers the entire area</label>
                                    </div>
                                    <div class="radio radio-primary">
                                        <input type="radio" name="ccx_login_background_size" id="size_contain"
                                            value="contain" <?php echo (get_option('ccx_login_background_size') == 'contain') ? 'checked' : ''; ?>>
                                        <label for="size_contain">Fit (Contain) - Image is fully visible</label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="ccx_login_poster_padding" class="control-label">Poster Padding (e.g. 5% or 20px)</label>
                                    <input type="text" name="ccx_login_poster_padding" id="ccx_login_poster_padding" class="form-control"
                                        value="<?php echo get_option('ccx_login_poster_padding'); ?>" placeholder="0px">
                                    <p class="help-block">Add space around the poster image (useful when Fit/Contain is selected).</p>
                                </div>

                                <div class="form-group">
                                    <label for="ccx_login_poster_bg" class="control-label">Poster Background Color</label>
                                    <input type="color" name="ccx_login_poster_bg" id="ccx_login_poster_bg" class="form-control"
                                        value="<?php echo get_option('ccx_login_poster_bg') ?: '#f3f4f6'; ?>" style="height: 40px;">
                                    <p class="help-block">Background color for the area behind the poster.</p>
                                </div>

                                <div class="form-group">
                                    <div class="checkbox checkbox-danger">
                                        <input type="checkbox" name="ccx_disable_customer_login"
                                            id="ccx_disable_customer_login" value="1" <?php echo get_option('ccx_disable_customer_login') == 1 ? 'checked' : ''; ?>>
                                        <label for="ccx_disable_customer_login">Disable Customer Login</label>
                                    </div>
                                    <p class="help-block">If enabled, customers attempting to access the login page will
                                        be blocked.</p>
                                </div>

                                <button type="submit" class="btn btn-info">Save Settings</button>
                                <?php echo form_close(); ?>
                            </div>

                            <div class="col-md-6">
                                <h4>Upload Poster</h4>
                                <?php echo form_open_multipart('ccx_login/upload_poster'); ?>
                                <div class="form-group">
                                    <label for="poster">Select Image</label>
                                    <input type="file" name="poster" class="form-control" accept="image/*" required>
                                    <small class="text-muted">Recommended Size: 1920x1080px (Full HD)</small>
                                </div>
                                <button type="submit" class="btn btn-info">Upload Poster</button>
                                <?php echo form_close(); ?>
                            </div>
                        </div>

                        <hr />

                        <div class="row">
                            <div class="col-md-12">
                                <h4>Existing Posters</h4>
                                <div class="row">
                                    <?php if (!empty($posters) && is_array($posters)): ?>
                                        <?php foreach ($posters as $poster): ?>
                                            <div class="col-md-3 mbot20">
                                                <div class="thumbnail"
                                                    style="position:relative; <?php echo (isset($poster['visible']) && !$poster['visible']) ? 'opacity: 0.5;' : ''; ?>">
                                                    <a href="<?php echo base_url('modules/ccx_login/uploads/' . $poster['filename']); ?>"
                                                        target="_blank">
                                                        <img src="<?php echo base_url('modules/ccx_login/uploads/' . $poster['filename']); ?>"
                                                            alt="<?php echo $poster['original_name']; ?>" class="img-responsive"
                                                            style="max-height: 200px; object-fit: cover; width: 100%;">
                                                    </a>
                                                    <div class="caption text-center">
                                                        <p class="text-ellipsis"><?php echo $poster['original_name']; ?></p>

                                                        <a href="<?php echo admin_url('ccx_login/toggle_poster_visibility/' . $poster['filename']); ?>"
                                                            class="btn btn-default btn-xs" data-toggle="tooltip"
                                                            title="Toggle Visibility">
                                                            <?php if (isset($poster['visible']) && !$poster['visible']): ?>
                                                                <i class="fa fa-eye-slash"></i> Hidden
                                                            <?php else: ?>
                                                                <i class="fa fa-eye"></i> Visible
                                                            <?php endif; ?>
                                                        </a>

                                                        <a href="<?php echo admin_url('ccx_login/delete_poster/' . $poster['filename']); ?>"
                                                            class="btn btn-danger btn-xs _delete">Delete</a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-md-12">
                                            <p class="text-muted">No posters uploaded yet.</p>
                                        </div>
                                    <?php endif; ?>
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