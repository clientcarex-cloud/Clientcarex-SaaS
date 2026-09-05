<?php defined('BASEPATH') or exit('No direct script access allowed');

$e = $employee;
// which curated fields the superadmin allows this employee to edit
$editable = array_flip($editable_fields);

$dept = '';
foreach ($departments as $d) {
    if ($d['departmentid'] == ($e['department_id'] ?? 0)) { $dept = $d['name']; }
}
$desig = '';
foreach ($designations as $d) {
    if ($d['id'] == ($e['designation_id'] ?? 0)) { $desig = $d['name']; }
}

// render one field: read-only text unless it's in the editable whitelist
if (!function_exists('ess_field')) {
function ess_field($key, $label, $e, $editable, $type = 'text', $options = [])
{
    $val    = $e[$key] ?? '';
    $is_ed  = isset($editable[$key]);
    echo '<div class="form-group ess-field ' . ($is_ed ? 'ess-editable' : 'ess-locked') . '">';
    echo '<label>' . html_escape($label);
    echo $is_ed
        ? ' <span class="ess-badge"><i class="fa fa-pencil"></i> Editable</span>'
        : ' <i class="fa fa-lock ess-lock" title="Read-only — contact HR to change"></i>';
    echo '</label>';
    if ($is_ed) {
        if ($type === 'textarea') {
            echo '<textarea name="' . $key . '" class="form-control" rows="2">' . html_escape($val) . '</textarea>';
        } elseif ($type === 'select') {
            echo '<select name="' . $key . '" class="form-control">';
            echo '<option value="">— select —</option>';
            foreach ($options as $ov) {
                echo '<option value="' . html_escape($ov) . '"' . ($val == $ov ? ' selected' : '') . '>' . html_escape(ucfirst($ov)) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="' . $type . '" name="' . $key . '" class="form-control" value="' . html_escape($val) . '">';
        }
    } else {
        echo '<div class="ess-ro-value">' . (trim((string) $val) !== '' ? html_escape($val) : '<span class="text-muted">—</span>') . '</div>';
    }
    echo '</div>';
}
}
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <style>
            .hr-card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.05);border:1px solid rgba(0,0,0,.03);padding:22px;margin-bottom:20px}
            .hr-card h4{margin:0 0 4px;font-size:14px;font-weight:800;color:#334155;text-transform:uppercase;letter-spacing:.5px}
            .prof-head{display:flex;align-items:center;gap:18px}
            .prof-avatar{width:76px;height:76px;border-radius:50%;background-size:cover;background-position:center;background-color:#e2e8f0;border:2px solid #fff;box-shadow:0 2px 10px rgba(0,0,0,.12);flex-shrink:0}
            .prof-avatar-edit{position:relative;width:76px;height:76px;border-radius:50%;overflow:hidden;cursor:pointer;flex-shrink:0}
            .prof-avatar-edit .prof-avatar{width:100%;height:100%;border:0;box-shadow:none}
            .prof-avatar-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;background:rgba(14,77,84,.55);opacity:0;transition:opacity .15s ease;font-size:20px}
            .prof-avatar-edit:hover .prof-avatar-overlay{opacity:1}
            .prof-photo-form{margin-top:8px}
            .prof-photo-form .btn{margin:0}
            .ess-field label{font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:#64748b;display:block}
            .ess-editable{background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:10px 12px;margin-bottom:12px}
            .ess-editable label{color:#0369a1}
            .ess-editable .form-control{border-color:#7dd3fc}
            .ess-badge{background:#0ea5e9;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:999px;letter-spacing:.3px;margin-left:4px}
            .ess-lock{color:#cbd5e1;margin-left:4px}
            .ess-ro-value{font-weight:600;color:#1e293b;padding:6px 0}
            .ess-banner{border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13px;display:flex;align-items:center}
            .ess-banner i{margin-right:10px;font-size:16px}
            .ess-banner-ok{background:#f0f9ff;border:1px solid #bae6fd;color:#0369a1}
            .ess-banner-ro{background:#f8fafc;border:1px solid #e2e8f0;color:#64748b}
        </style>

        <?php $avatar_url = staff_profile_image_url($e['staffid'], 'small');
              $prof_has_img = strpos($avatar_url, 'user-placeholder') === false; ?>
        <div class="hr-card">
            <div class="prof-head">
                <div class="prof-avatar-edit" id="prof-avatar-edit" title="Change photo" onclick="hrOpenPhotoModal();">
                    <div class="prof-avatar" id="prof-avatar" style="background-image:url('<?php echo $avatar_url; ?>');"></div>
                    <span class="prof-avatar-overlay"><i class="fa fa-camera"></i></span>
                </div>
                <div style="flex:1;">
                    <div style="font-size:20px;font-weight:800;color:#1e293b;"><?php echo html_escape(trim(($e['firstname'] ?? '') . ' ' . ($e['lastname'] ?? ''))); ?></div>
                    <div class="text-muted">
                        <?php echo html_escape($e['employee_code'] ?? ''); ?>
                        <?php if ($desig) { ?> &middot; <?php echo html_escape($desig); ?><?php } ?>
                        <?php if ($dept) { ?> &middot; <?php echo html_escape($dept); ?><?php } ?>
                    </div>
                    <div class="prof-photo-form">
                        <button type="button" class="btn btn-default btn-sm" onclick="hrOpenPhotoModal();">
                            <i class="fa fa-camera"></i> Change Photo
                        </button>
                        <small class="text-muted mleft5">Crop, zoom &amp; preview before saving.</small>
                    </div>
                </div>
            </div>
        </div>

        <?php echo form_open(admin_url('hr/myhr/save_profile')); ?>

        <?php if (count($editable_fields)) { ?>
            <div class="ess-banner ess-banner-ok"><i class="fa fa-pencil-square-o"></i>
                <span>Fields marked <span class="ess-badge">Editable</span> are yours to update. Change them and click <strong>Save Changes</strong> at the bottom. Everything with a <i class="fa fa-lock"></i> is HR-controlled.</span>
            </div>
        <?php } else { ?>
            <div class="ess-banner ess-banner-ro"><i class="fa fa-lock"></i>
                <span>Your profile is currently read-only. Ask HR to enable self-editing (HR ▸ Settings ▸ Employee Self-Service).</span>
            </div>
        <?php } ?>

        <div class="row">
            <div class="col-md-6">
                <div class="hr-card">
                    <h4>Employment</h4><hr class="hr-panel-heading" />
                    <?php
                    ess_field('email', 'Work Email', $e, []);
                    ess_field('phonenumber', 'Phone', $e, []);
                    ess_field('employment_type', 'Employment Type', $e, []);
                    ess_field('date_of_joining', 'Date of Joining', $e, []);
                    ess_field('work_location', 'Work Location', $e, []);
                    ess_field('status', 'Status', $e, []);
                    ?>
                </div>

                <div class="hr-card">
                    <h4>Personal</h4><hr class="hr-panel-heading" />
                    <?php
                    ess_field('date_of_birth', 'Date of Birth', $e, []);
                    ess_field('gender', 'Gender', $e, []);
                    ess_field('blood_group', 'Blood Group', $e, $editable);
                    ess_field('marital_status', 'Marital Status', $e, $editable, 'select', ['single', 'married', 'divorced', 'widowed']);
                    ess_field('personal_email', 'Personal Email', $e, $editable, 'email');
                    ess_field('alt_phone', 'Alternate Phone', $e, $editable);
                    ess_field('qualifications', 'Qualifications', $e, $editable, 'textarea');
                    ?>
                </div>
            </div>

            <div class="col-md-6">
                <div class="hr-card">
                    <h4>Contact &amp; Address</h4><hr class="hr-panel-heading" />
                    <?php
                    ess_field('present_address', 'Present Address', $e, $editable, 'textarea');
                    ess_field('permanent_address', 'Permanent Address', $e, $editable, 'textarea');
                    ess_field('emergency_contact_name', 'Emergency Contact Name', $e, $editable);
                    ess_field('emergency_contact_relation', 'Emergency Contact Relation', $e, $editable);
                    ess_field('emergency_contact_phone', 'Emergency Contact Phone', $e, $editable);
                    ?>
                </div>

                <div class="hr-card">
                    <h4>Bank Details</h4><hr class="hr-panel-heading" />
                    <?php
                    ess_field('bank_name', 'Bank Name', $e, $editable);
                    ess_field('bank_branch', 'Bank Branch', $e, $editable);
                    ess_field('bank_account_no', 'Account No', $e, $editable);
                    ess_field('bank_ifsc', 'IFSC', $e, $editable);
                    ?>
                </div>
            </div>
        </div>

        <?php
        // ------------------------------------------------ My Documents / checklist
        $docs_by_type = [];
        foreach ($documents as $d) {
            $docs_by_type[$d['doc_type']][] = $d; // get_documents is id DESC → [0] is latest
        }
        // $required_docs is now [['name'=>, 'mandatory'=>bool], ...]
        $required_names = array_column($required_docs, 'name');
        $other_docs = [];
        foreach ($documents as $d) {
            if (!in_array($d['doc_type'], $required_names, true)) {
                $other_docs[] = $d;
            }
        }
        $status_meta = [
            'verified'  => ['label' => 'Verified',     'cls' => 'success', 'icon' => 'fa-check-circle'],
            'submitted' => ['label' => 'Under review',  'cls' => 'info',    'icon' => 'fa-clock-o'],
            'rejected'  => ['label' => 'Rejected',      'cls' => 'danger',  'icon' => 'fa-times-circle'],
        ];
        $submitted_count   = 0;
        $mandatory_pending = 0;
        foreach ($required_docs as $rd) {
            $has = !empty($docs_by_type[$rd['name']]);
            if ($has) {
                $submitted_count++;
            }
            if (!empty($rd['mandatory']) && !$has) {
                $mandatory_pending++;
            }
        }

        // Non-clickable preview only. Once a document is submitted the employee
        // can no longer view, download, replace or delete it — HR fully owns it
        // from that point (manage it on the employee's Documents tab).
        if (!function_exists('ess_doc_preview')) {
            function ess_doc_preview($d)
            {
                if (hr_document_is_image($d['file_name'])) {
                    $view = admin_url('hr/myhr/view_document/' . $d['id']);
                    echo '<span class="ess-doc-thumb" style="background-image:url(\'' . $view . '\');" title="' . html_escape($d['title']) . '"></span>';
                } else {
                    $ext = strtoupper(pathinfo($d['file_name'], PATHINFO_EXTENSION));
                    echo '<span class="ess-doc-thumb ess-doc-file" title="' . html_escape($d['title']) . '"><i class="fa fa-file-text-o"></i><span>' . html_escape($ext) . '</span></span>';
                }
            }
        }
        ?>
        <style>
            .ess-doc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}
            .ess-doc-item{display:flex;gap:12px;border:1px solid #eef2f7;border-radius:10px;padding:12px;background:#fff}
            .ess-doc-thumb{width:58px;height:58px;border-radius:8px;background-size:cover;background-position:center;background-color:#f1f5f9;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#94a3b8;text-decoration:none;flex-shrink:0;font-size:10px;font-weight:700}
            .ess-doc-file i{font-size:20px;margin-bottom:2px}
            .ess-doc-empty{border:1px dashed #cbd5e1}
            .ess-doc-body{flex:1;min-width:0}
            .ess-doc-title{font-weight:700;color:#1e293b;font-size:13px;margin-bottom:5px}
            .ess-doc-actions{margin-top:8px}
            .ess-doc-actions .btn{margin-right:4px}
            .ess-req{color:#dc2626;font-weight:800}
            .ess-doc-req{border-color:#fecaca !important;background:#fef2f2 !important}
            .ess-doc-empty-req{border-color:#fca5a5 !important;color:#dc2626}
            .ess-mand-warn{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px}
            .label-mandatory{background:#dc2626;color:#fff}
        </style>

        <div class="hr-card" id="my-documents">
            <h4><i class="fa fa-folder-open-o" style="color:#0ea5e9;"></i> My Documents
                <?php if (count($required_docs)) { ?>
                    <span class="pull-right label label-<?php echo $submitted_count >= count($required_docs) ? 'success' : 'warning'; ?>" style="font-size:12px;">
                        <?php echo $submitted_count; ?> / <?php echo count($required_docs); ?> submitted
                    </span>
                <?php } ?>
            </h4>
            <hr class="hr-panel-heading" />

            <?php if ($mandatory_pending > 0) { ?>
                <div class="ess-mand-warn"><i class="fa fa-exclamation-circle"></i>
                    You have <strong><?php echo $mandatory_pending; ?></strong> mandatory document(s) still to upload — these are marked <span class="ess-req">*</span> and are compulsory.
                </div>
            <?php } ?>

            <?php if (count($required_docs)) { ?>
                <p class="text-muted" style="font-size:13px;">Please submit each required document below. Items marked <span class="ess-req">*</span> are mandatory. HR will review and verify them.</p>
                <div class="ess-doc-grid">
                    <?php foreach ($required_docs as $rd) {
                        $rn        = $rd['name'];
                        $mandatory = !empty($rd['mandatory']);
                        $d         = $docs_by_type[$rn][0] ?? null;
                        $status    = $d ? ($d['status'] ?? 'submitted') : 'pending';
                        $meta      = $status_meta[$status] ?? ['label' => 'Pending', 'cls' => 'default', 'icon' => 'fa-circle-o'];
                        $pend_mand = $mandatory && !$d; ?>
                        <div class="ess-doc-item<?php echo $pend_mand ? ' ess-doc-req' : ''; ?>">
                            <div>
                                <?php if ($d) { ess_doc_preview($d); } else { ?>
                                    <div class="ess-doc-thumb ess-doc-empty<?php echo $mandatory ? ' ess-doc-empty-req' : ''; ?>"><i class="fa fa-plus"></i></div>
                                <?php } ?>
                            </div>
                            <div class="ess-doc-body">
                                <div class="ess-doc-title"><?php echo html_escape($rn); ?><?php if ($mandatory) { ?> <span class="ess-req" title="Mandatory">*</span><?php } ?></div>
                                <span class="label label-<?php echo $meta['cls']; ?>"><i class="fa <?php echo $meta['icon']; ?>"></i> <?php echo $d ? $meta['label'] : 'Pending'; ?></span>
                                <?php if ($mandatory) { ?><span class="label label-mandatory">Mandatory</span><?php } ?>
                                <?php if ($d && $status === 'rejected' && !empty($d['review_note'])) { ?>
                                    <div class="text-danger" style="font-size:12px;margin-top:4px;"><i class="fa fa-info-circle"></i> <?php echo html_escape($d['review_note']); ?></div>
                                <?php } ?>
                                <?php if (!$d) { ?>
                                    <div class="ess-doc-actions">
                                        <button type="button" class="btn btn-<?php echo $mandatory ? 'danger' : 'primary'; ?> btn-xs" onclick="essUpload('<?php echo html_escape($rn, ENT_QUOTES); ?>')"><i class="fa fa-upload"></i> Upload</button>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if (count($other_docs)) { ?>
                <h4 style="margin-top:22px;font-size:13px;">Other Documents</h4>
                <hr class="hr-panel-heading" />
                <div class="ess-doc-grid">
                    <?php foreach ($other_docs as $d) {
                        $status = $d['status'] ?? 'submitted';
                        $meta   = $status_meta[$status] ?? ['label' => 'Submitted', 'cls' => 'default', 'icon' => 'fa-file-o']; ?>
                        <div class="ess-doc-item">
                            <div><?php ess_doc_preview($d); ?></div>
                            <div class="ess-doc-body">
                                <div class="ess-doc-title"><?php echo html_escape($d['title']); ?></div>
                                <span class="label label-<?php echo $meta['cls']; ?>"><i class="fa <?php echo $meta['icon']; ?>"></i> <?php echo $meta['label']; ?></span>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>

            <div style="margin-top:16px;">
                <button type="button" class="btn btn-default" onclick="essUpload('')"><i class="fa fa-upload"></i> Submit a Document</button>
            </div>
        </div>

        <?php if (count($editable_fields)) { ?>
            <div class="hr-card text-right">
                <span class="text-muted pull-left" style="line-height:34px;"><i class="fa fa-info-circle"></i> You can edit the highlighted fields. Other changes must be requested from HR.</span>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Changes</button>
            </div>
        <?php } else { ?>
            <div class="hr-card text-muted"><i class="fa fa-lock"></i> Your profile is read-only. Contact HR to update your details.</div>
        <?php } ?>
        <?php echo form_close(); ?>

        <div class="modal fade" id="ess_doc_modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <?php echo form_open_multipart(admin_url('hr/myhr/upload_document')); ?>
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        <h4 class="modal-title">Submit Document to HR</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Document Type</label>
                            <select name="doc_type" id="ess_doc_type" class="form-control">
                                <?php foreach ($required_docs as $rd) { ?>
                                    <option value="<?php echo html_escape($rd['name']); ?>"><?php echo html_escape($rd['name']) . (!empty($rd['mandatory']) ? ' *' : ''); ?></option>
                                <?php } ?>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Title / Description <small class="text-muted">(optional)</small></label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Aadhaar card front &amp; back"></div>
                        <div class="row">
                            <div class="col-md-6 form-group"><label>Issue Date <small class="text-muted">(optional)</small></label>
                                <input type="date" name="issue_date" class="form-control"></div>
                            <div class="col-md-6 form-group"><label>Expiry Date <small class="text-muted">(optional)</small></label>
                                <input type="date" name="expiry_date" class="form-control"></div>
                        </div>
                        <div class="form-group"><label>File</label>
                            <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" required>
                            <small class="text-muted">Accepted: PDF, image (JPG/PNG), Word or Excel.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                        <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Submit</button>
                    </div>
                    <?php echo form_close(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    function essUpload(type) {
        if (type) {
            var $sel = $('#ess_doc_type');
            if ($sel.find('option[value="' + type + '"]').length) {
                $sel.val(type);
            } else {
                $sel.val('Other');
            }
        }
        $('#ess_doc_modal').modal('show');
    }
</script>

<link href="<?php echo module_dir_url('hr', 'assets/css/cropper.min.css'); ?>?v=<?php echo $this->app_scripts->core_version(); ?>" rel="stylesheet" type="text/css" />
<style>
    #hr-photo-modal .modal-body { padding: 0; }
    .hr-pe-body { display: flex; flex-wrap: wrap; }
    .hr-pe-stage { flex: 1 1 320px; min-width: 280px; background: #1f2a30; padding: 12px; }
    .hr-pe-stage .hr-pe-canvas { max-height: 340px; }
    .hr-pe-canvas img { max-width: 100%; display: block; }
    .hr-pe-empty { display: flex; align-items: center; justify-content: center; flex-direction: column; height: 300px; color: #9fb0b6; text-align: center; }
    .hr-pe-empty i { font-size: 42px; margin-bottom: 12px; }
    .hr-pe-side { flex: 0 0 220px; padding: 18px; border-left: 1px solid #e6ebef; }
    .hr-pe-preview-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #8a97a3; margin-bottom: 10px; text-align: center; }
    .hr-pe-preview { width: 140px; height: 140px; border-radius: 50%; overflow: hidden; margin: 0 auto 6px; background: #eef1f4; box-shadow: 0 0 0 4px #fff, 0 0 0 5px #e3e8ee; }
    .hr-pe-preview img { display: block; max-width: none; }
    .hr-pe-preview-sm { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; margin: 0 auto 14px; background: #eef1f4; box-shadow: 0 0 0 2px #fff, 0 0 0 3px #e3e8ee; }
    .hr-pe-preview-sm img { display: block; max-width: none; }
    .hr-pe-zoom-row { display: flex; align-items: center; gap: 8px; margin: 14px 0 6px; }
    .hr-pe-zoom-row input[type=range] { flex: 1; }
    .hr-pe-tools { display: flex; justify-content: center; gap: 6px; margin-top: 10px; }
    .hr-pe-tools .btn { flex: 1; }
    .hr-pe-hint { font-size: 11px; color: #98a4b0; text-align: center; margin-top: 12px; line-height: 1.4; }
    @media (max-width: 600px) { .hr-pe-side { flex: 1 1 100%; border-left: 0; border-top: 1px solid #e6ebef; } }
</style>

<div class="modal fade" id="hr-photo-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title"><i class="fa fa-camera"></i> My Photo</h4>
            </div>
            <div class="modal-body">
                <div class="hr-pe-body">
                    <div class="hr-pe-stage">
                        <div class="hr-pe-canvas" id="hr-pe-canvas">
                            <img id="hr-pe-image" src="" alt="" style="display:none;">
                            <div class="hr-pe-empty" id="hr-pe-empty">
                                <i class="fa fa-image"></i>
                                <div>Choose a photo to start.<br>Drag to reposition, scroll or use the slider to zoom.</div>
                            </div>
                        </div>
                    </div>
                    <div class="hr-pe-side">
                        <div class="hr-pe-preview-label">Live preview</div>
                        <div class="hr-pe-preview"><div id="hr-pe-preview" style="width:100%;height:100%;"></div></div>
                        <div class="hr-pe-preview-sm"><div id="hr-pe-preview-sm" style="width:100%;height:100%;"></div></div>

                        <div class="hr-pe-zoom-row">
                            <i class="fa fa-picture-o" style="font-size:11px;color:#98a4b0;"></i>
                            <input type="range" id="hr-pe-zoom" min="0" max="1" step="0.01" value="0" disabled>
                            <i class="fa fa-picture-o" style="font-size:16px;color:#98a4b0;"></i>
                        </div>
                        <div class="hr-pe-tools">
                            <button type="button" class="btn btn-default btn-sm" id="hr-pe-rotate-l" title="Rotate left" disabled><i class="fa fa-rotate-left"></i></button>
                            <button type="button" class="btn btn-default btn-sm" id="hr-pe-rotate-r" title="Rotate right" disabled><i class="fa fa-rotate-right"></i></button>
                            <button type="button" class="btn btn-default btn-sm" id="hr-pe-reset" title="Reset" disabled><i class="fa fa-refresh"></i></button>
                        </div>
                        <div class="hr-pe-hint">JPG or PNG, square crop.<br>Output is centred in a circle everywhere.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <label class="btn btn-default btn-sm" style="margin:0;">
                        <i class="fa fa-folder-open-o"></i> <span id="hr-pe-choose-label">Choose photo</span>
                        <input type="file" id="hr-pe-file" accept="image/jpeg,image/png" style="display:none;">
                    </label>
                </div>
                <div>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="hr-pe-save" disabled><i class="fa fa-check"></i> Save Photo</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo module_dir_url('hr', 'assets/js/cropper.min.js'); ?>?v=<?php echo $this->app_scripts->core_version(); ?>"></script>
<script>
(function () {
    var HR_EMP_ID  = <?php echo (int) $e['staffid']; ?>;
    var HR_HAS_IMG = <?php echo $prof_has_img ? 'true' : 'false'; ?>;
    // Preload the higher-res thumb (320px) so re-cropping stays sharp.
    var HR_IMG_URL = <?php echo json_encode(staff_profile_image_url($e['staffid'], 'thumb')); ?>;

    var cropper = null;
    var $img    = document.getElementById('hr-pe-image');
    var $empty  = document.getElementById('hr-pe-empty');
    var $zoom   = document.getElementById('hr-pe-zoom');
    var $save   = document.getElementById('hr-pe-save');
    var $file   = document.getElementById('hr-pe-file');
    var minZoom = 0, maxZoom = 3;

    window.hrOpenPhotoModal = function () {
        $('#hr-photo-modal').modal('show');
        if (HR_HAS_IMG && !cropper) {
            loadImage(HR_IMG_URL + (HR_IMG_URL.indexOf('?') > -1 ? '&' : '?') + 'cb=' + HR_EMP_ID);
        }
    };

    function setTools(enabled) {
        ['hr-pe-rotate-l', 'hr-pe-rotate-r', 'hr-pe-reset'].forEach(function (id) {
            document.getElementById(id).disabled = !enabled;
        });
        $zoom.disabled = !enabled;
        $save.disabled = !enabled;
    }

    function destroyCropper() {
        if (cropper) { cropper.destroy(); cropper = null; }
    }

    function loadImage(src) {
        destroyCropper();
        $empty.style.display = 'none';
        $img.style.display = 'block';
        $img.src = src;
        cropper = new Cropper($img, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            background: false,
            movable: true,
            zoomable: true,
            guides: false,
            center: true,
            preview: '#hr-pe-preview, #hr-pe-preview-sm',
            minCropBoxWidth: 80,
            minCropBoxHeight: 80,
            ready: function () {
                setTools(true);
                var data = cropper.getImageData();
                minZoom = data.width / data.naturalWidth;
                maxZoom = minZoom * 4;
                $zoom.min = minZoom;
                $zoom.max = maxZoom;
                $zoom.step = (maxZoom - minZoom) / 100;
                $zoom.value = minZoom;
            },
            zoom: function (e) {
                var ratio = e.detail.ratio;
                if (ratio < minZoom) { e.preventDefault(); return; }
                $zoom.value = Math.min(Math.max(ratio, minZoom), maxZoom);
            }
        });
    }

    $file.addEventListener('change', function () {
        var f = this.files && this.files[0];
        if (!f) { return; }
        if (!/image\/(jpeg|png)/.test(f.type)) {
            alert_float('warning', 'Please choose a JPG or PNG image.');
            return;
        }
        document.getElementById('hr-pe-choose-label').textContent = 'Change photo';
        var reader = new FileReader();
        reader.onload = function (ev) { loadImage(ev.target.result); };
        reader.readAsDataURL(f);
        this.value = '';
    });

    $zoom.addEventListener('input', function () {
        if (cropper) { cropper.zoomTo(parseFloat(this.value)); }
    });
    document.getElementById('hr-pe-rotate-l').addEventListener('click', function () { if (cropper) cropper.rotate(-90); });
    document.getElementById('hr-pe-rotate-r').addEventListener('click', function () { if (cropper) cropper.rotate(90); });
    document.getElementById('hr-pe-reset').addEventListener('click', function () {
        if (cropper) { cropper.reset(); $zoom.value = minZoom; }
    });

    $save.addEventListener('click', function () {
        if (!cropper) { return; }
        var canvas = cropper.getCroppedCanvas({
            width: 400, height: 400,
            imageSmoothingEnabled: true, imageSmoothingQuality: 'high',
            fillColor: '#fff'
        });
        if (!canvas) { return; }
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
        canvas.toBlob(function (blob) {
            var fd = new FormData();
            // Unique name per upload. The core deletes the original file after
            // making its small_/thumb_ variants, so a fixed name would make
            // unique_filename() reuse it every time — the stored filename never
            // changes and the browser keeps serving the cached old image on
            // refresh. A timestamp guarantees a new filename (and new URL).
            fd.append('profile_image', blob, 'employee_' + HR_EMP_ID + '_' + Date.now() + '.jpg');
            if (typeof csrfData !== 'undefined') {
                fd.append(csrfData['token_name'], csrfData['hash']);
            }
            var xhr = new XMLHttpRequest();
            xhr.open('POST', admin_url + 'hr/myhr/save_profile_picture');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function () {
                var res = {};
                try { res = JSON.parse(xhr.responseText); } catch (e) {}
                if (xhr.status === 200 && res.success) {
                    var bust = res.image + (res.image.indexOf('?') > -1 ? '&' : '?') + 't=' + Date.now();
                    document.getElementById('prof-avatar').style.backgroundImage = "url('" + bust + "')";
                    $('.staff-profile-image-small').attr('src', bust);
                    HR_HAS_IMG = true;
                    alert_float('success', res.message || 'Photo updated.');
                    $('#hr-photo-modal').modal('hide');
                } else {
                    alert_float('danger', (res && res.message) || 'Upload failed.');
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-check"></i> Save Photo';
            };
            xhr.onerror = function () {
                alert_float('danger', 'Upload failed.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-check"></i> Save Photo';
            };
            xhr.send(fd);
        }, 'image/jpeg', 0.92);
    });

    $('#hr-photo-modal').on('hidden.bs.modal', function () {
        destroyCropper();
        $img.style.display = 'none';
        $empty.style.display = 'flex';
        setTools(false);
    });
})();
</script>
