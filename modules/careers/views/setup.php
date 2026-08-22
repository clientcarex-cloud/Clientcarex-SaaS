<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="crs-wrap">

            <?php $active = 'setup'; include __DIR__ . '/_nav.php'; ?>

            <div class="crs-split">
                <!-- ── Departments ── -->
                <div>
                    <div class="crs-card">
                        <div class="crs-card-head">
                            <h3>Departments</h3>
                            <div class="crs-card-actions">
                                <button type="button" class="crs-btn crs-btn-sm" data-crs-toggle="#crs-dept-form"><i class="fa fa-plus"></i> Add</button>
                            </div>
                        </div>
                        <div class="crs-card-body crs-tight">
                            <div id="crs-dept-form" hidden style="padding:16px;border-bottom:1px solid var(--crs-line);background:var(--crs-bg)">
                                <?= form_open(admin_url('careers/save_department')); ?>
                                <input type="hidden" name="id" value="0">
                                <div class="crs-grid-2">
                                    <div class="crs-field">
                                        <label class="crs-label">Name *</label>
                                        <input type="text" name="name" class="crs-input" required maxlength="150">
                                    </div>
                                    <div class="crs-field">
                                        <label class="crs-label">Colour</label>
                                        <input type="color" name="color" class="crs-input" value="#0d9488" style="height:38px;padding:3px">
                                    </div>
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">Description</label>
                                    <input type="text" name="description" class="crs-input" maxlength="500">
                                </div>
                                <label class="crs-check"><input type="checkbox" name="active" value="1" checked> <span>Active</span></label>
                                <button type="submit" class="crs-btn crs-btn-primary crs-btn-sm">Save department</button>
                                <?= form_close(); ?>
                            </div>

                            <div class="crs-table-wrap">
                                <table class="crs-table">
                                    <thead><tr><th>Department</th><th>Slug</th><th>Status</th><th class="crs-right">Actions</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($departments as $department) { ?>
                                        <tr>
                                            <td class="crs-td-main">
                                                <span class="crs-dot" style="background:<?= html_escape($department->color); ?>"></span>
                                                <?= html_escape($department->name); ?>
                                                <?php if ($department->description) { ?>
                                                    <div class="crs-td-sub"><?= html_escape($department->description); ?></div>
                                                <?php } ?>
                                            </td>
                                            <td class="crs-muted"><code><?= html_escape($department->slug); ?></code></td>
                                            <td>
                                                <span class="crs-badge" style="background:<?= (int) $department->active === 1 ? '#ecfdf5;color:#047857' : '#f1f5f9;color:#64748b'; ?>">
                                                    <?= (int) $department->active === 1 ? 'Active' : 'Hidden'; ?>
                                                </span>
                                            </td>
                                            <td class="crs-right crs-nowrap">
                                                <button type="button" class="crs-btn crs-btn-sm" data-crs-toggle="#crs-dept-<?= (int) $department->id; ?>">Edit</button>
                                                <a href="<?= admin_url('careers/delete_department/' . $department->id); ?>" class="crs-btn crs-btn-sm crs-btn-icon crs-btn-danger"
                                                   data-crs-confirm="Delete this department? Its openings stay published without a department."><i class="fa fa-trash"></i></a>
                                            </td>
                                        </tr>
                                        <tr id="crs-dept-<?= (int) $department->id; ?>" hidden>
                                            <td colspan="4" style="background:var(--crs-bg)">
                                                <?= form_open(admin_url('careers/save_department')); ?>
                                                <input type="hidden" name="id" value="<?= (int) $department->id; ?>">
                                                <div class="crs-grid-3">
                                                    <div class="crs-field">
                                                        <label class="crs-label">Name</label>
                                                        <input type="text" name="name" class="crs-input" value="<?= html_escape($department->name); ?>" required>
                                                    </div>
                                                    <div class="crs-field">
                                                        <label class="crs-label">Colour</label>
                                                        <input type="color" name="color" class="crs-input" value="<?= html_escape($department->color); ?>" style="height:38px;padding:3px">
                                                    </div>
                                                    <div class="crs-field">
                                                        <label class="crs-label">Sort order</label>
                                                        <input type="number" name="sort_order" class="crs-input" value="<?= (int) $department->sort_order; ?>">
                                                    </div>
                                                </div>
                                                <div class="crs-field">
                                                    <label class="crs-label">Description</label>
                                                    <input type="text" name="description" class="crs-input" value="<?= html_escape((string) $department->description); ?>">
                                                </div>
                                                <label class="crs-check"><input type="checkbox" name="active" value="1" <?= (int) $department->active === 1 ? 'checked' : ''; ?>> <span>Active</span></label>
                                                <button type="submit" class="crs-btn crs-btn-primary crs-btn-sm">Save</button>
                                                <?= form_close(); ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ── Locations ── -->
                    <div class="crs-card">
                        <div class="crs-card-head">
                            <h3>Locations</h3>
                            <div class="crs-card-actions">
                                <button type="button" class="crs-btn crs-btn-sm" data-crs-toggle="#crs-loc-form"><i class="fa fa-plus"></i> Add</button>
                            </div>
                        </div>
                        <div class="crs-card-body crs-tight">
                            <div id="crs-loc-form" hidden style="padding:16px;border-bottom:1px solid var(--crs-line);background:var(--crs-bg)">
                                <?= form_open(admin_url('careers/save_location')); ?>
                                <input type="hidden" name="id" value="0">
                                <div class="crs-grid-2">
                                    <div class="crs-field">
                                        <label class="crs-label">Name *</label>
                                        <input type="text" name="name" class="crs-input" required maxlength="150" placeholder="e.g. Hyderabad HQ">
                                    </div>
                                    <div class="crs-field">
                                        <label class="crs-label">City</label>
                                        <input type="text" name="city" class="crs-input" maxlength="120">
                                    </div>
                                </div>
                                <div class="crs-grid-3">
                                    <div class="crs-field">
                                        <label class="crs-label">State</label>
                                        <input type="text" name="state" class="crs-input" maxlength="120">
                                    </div>
                                    <div class="crs-field">
                                        <label class="crs-label">Country</label>
                                        <input type="text" name="country" class="crs-input" maxlength="120" value="<?= html_escape((string) careers_opt('careers_default_country')); ?>">
                                    </div>
                                    <div class="crs-field">
                                        <label class="crs-label">Postal code</label>
                                        <input type="text" name="postal_code" class="crs-input" maxlength="30">
                                    </div>
                                </div>
                                <div class="crs-field">
                                    <label class="crs-label">Street address</label>
                                    <input type="text" name="address" class="crs-input" maxlength="500">
                                    <div class="crs-hint">Google needs a full address to show the job in Google Jobs.</div>
                                </div>
                                <label class="crs-check"><input type="checkbox" name="active" value="1" checked> <span>Active</span></label>
                                <button type="submit" class="crs-btn crs-btn-primary crs-btn-sm">Save location</button>
                                <?= form_close(); ?>
                            </div>

                            <div class="crs-table-wrap">
                                <table class="crs-table">
                                    <thead><tr><th>Location</th><th>Address</th><th>Status</th><th class="crs-right">Actions</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($locations as $location) { ?>
                                        <tr>
                                            <td class="crs-td-main">
                                                <?= html_escape($location->name); ?>
                                                <div class="crs-td-sub"><?= html_escape(trim(implode(', ', array_filter([$location->city, $location->state, $location->country])), ', ')); ?></div>
                                            </td>
                                            <td class="crs-muted"><?= html_escape((string) $location->address) ?: '—'; ?></td>
                                            <td>
                                                <span class="crs-badge" style="background:<?= (int) $location->active === 1 ? '#ecfdf5;color:#047857' : '#f1f5f9;color:#64748b'; ?>">
                                                    <?= (int) $location->active === 1 ? 'Active' : 'Hidden'; ?>
                                                </span>
                                            </td>
                                            <td class="crs-right crs-nowrap">
                                                <button type="button" class="crs-btn crs-btn-sm" data-crs-toggle="#crs-loc-<?= (int) $location->id; ?>">Edit</button>
                                                <a href="<?= admin_url('careers/delete_location/' . $location->id); ?>" class="crs-btn crs-btn-sm crs-btn-icon crs-btn-danger"
                                                   data-crs-confirm="Delete this location?"><i class="fa fa-trash"></i></a>
                                            </td>
                                        </tr>
                                        <tr id="crs-loc-<?= (int) $location->id; ?>" hidden>
                                            <td colspan="4" style="background:var(--crs-bg)">
                                                <?= form_open(admin_url('careers/save_location')); ?>
                                                <input type="hidden" name="id" value="<?= (int) $location->id; ?>">
                                                <div class="crs-grid-3">
                                                    <div class="crs-field">
                                                        <label class="crs-label">Name</label>
                                                        <input type="text" name="name" class="crs-input" value="<?= html_escape($location->name); ?>" required>
                                                    </div>
                                                    <div class="crs-field">
                                                        <label class="crs-label">City</label>
                                                        <input type="text" name="city" class="crs-input" value="<?= html_escape($location->city); ?>">
                                                    </div>
                                                    <div class="crs-field">
                                                        <label class="crs-label">State</label>
                                                        <input type="text" name="state" class="crs-input" value="<?= html_escape($location->state); ?>">
                                                    </div>
                                                </div>
                                                <div class="crs-grid-3">
                                                    <div class="crs-field">
                                                        <label class="crs-label">Country</label>
                                                        <input type="text" name="country" class="crs-input" value="<?= html_escape($location->country); ?>">
                                                    </div>
                                                    <div class="crs-field">
                                                        <label class="crs-label">Postal code</label>
                                                        <input type="text" name="postal_code" class="crs-input" value="<?= html_escape($location->postal_code); ?>">
                                                    </div>
                                                    <div class="crs-field">
                                                        <label class="crs-label">Sort order</label>
                                                        <input type="number" name="sort_order" class="crs-input" value="<?= (int) $location->sort_order; ?>">
                                                    </div>
                                                </div>
                                                <div class="crs-field">
                                                    <label class="crs-label">Street address</label>
                                                    <input type="text" name="address" class="crs-input" value="<?= html_escape((string) $location->address); ?>">
                                                </div>
                                                <label class="crs-check"><input type="checkbox" name="active" value="1" <?= (int) $location->active === 1 ? 'checked' : ''; ?>> <span>Active</span></label>
                                                <button type="submit" class="crs-btn crs-btn-primary crs-btn-sm">Save</button>
                                                <?= form_close(); ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Job alert subscribers ── -->
                <div>
                    <div class="crs-card">
                        <div class="crs-card-head">
                            <h3>Job alert subscribers</h3>
                            <span class="crs-chip"><?= count($subscribers); ?></span>
                        </div>
                        <div class="crs-card-body crs-tight">
                            <?php if (empty($subscribers)) { ?>
                                <div class="crs-empty" style="padding:36px 20px">
                                    <i class="fa-regular fa-bell"></i>
                                    <h4>No subscribers yet</h4>
                                    <p>Visitors who ask to be told about new openings appear here.</p>
                                </div>
                            <?php } else { ?>
                                <div class="crs-table-wrap" style="max-height:520px;overflow-y:auto">
                                    <table class="crs-table">
                                        <thead><tr><th>Email</th><th>Interests</th><th>Since</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($subscribers as $subscriber) { ?>
                                            <tr>
                                                <td class="crs-td-main" style="font-size:12.5px">
                                                    <?= html_escape($subscriber->email); ?>
                                                    <?php if ((int) $subscriber->active === 0) { ?>
                                                        <span class="crs-badge" style="background:#f1f5f9;color:#64748b">Unsubscribed</span>
                                                    <?php } ?>
                                                    <?php if ($subscriber->name) { ?>
                                                        <div class="crs-td-sub"><?= html_escape($subscriber->name); ?></div>
                                                    <?php } ?>
                                                </td>
                                                <td class="crs-muted" style="font-size:12px">
                                                    <?= html_escape(trim($subscriber->departments . ' ' . $subscriber->job_types)) ?: 'All openings'; ?>
                                                </td>
                                                <td class="crs-nowrap crs-muted" style="font-size:12px"><?= _d($subscriber->created_at); ?></td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } ?>
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
