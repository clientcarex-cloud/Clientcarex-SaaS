<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" type="text/css" href="<?php echo base_url(WIKI_ASSETS_PATH.'/css/wiki_styles.css'); ?>?v=<?php echo time(); ?>">
<style>
/* Book Form — Premium Enhancements */
.wiki-form-panel {
    border: 1px solid var(--wiki-border);
    border-radius: var(--wiki-radius);
    background: var(--wiki-card-bg);
    box-shadow: var(--wiki-shadow-sm);
    overflow: hidden;
}
.wiki-form-panel .panel-body {
    padding: 28px;
}
.wiki-form-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 4px;
}
.wiki-form-header h4 {
    font-size: 20px;
    font-weight: 700;
    color: var(--wiki-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.wiki-form-header h4 i {
    color: var(--wiki-primary);
    font-size: 18px;
}
.wiki-form-header .wiki-form-breadcrumb {
    font-size: 13px;
    color: var(--wiki-text-muted);
}
.wiki-form-header .wiki-form-breadcrumb a {
    color: var(--wiki-primary);
    text-decoration: none;
}
.wiki-form-header .wiki-form-breadcrumb a:hover {
    text-decoration: underline;
}
.wiki-section-label {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--wiki-text-muted);
    margin-bottom: 14px;
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.wiki-section-label i {
    font-size: 11px;
}
.wiki-permission-card {
    background: var(--wiki-bg);
    border: 1px solid var(--wiki-border);
    border-radius: var(--wiki-radius-sm);
    padding: 18px 20px;
    margin-top: 4px;
}
.wiki-form-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid var(--wiki-border);
    margin-top: 20px;
    flex-wrap: wrap;
}
.wiki-form-actions .btn {
    border-radius: var(--wiki-radius-sm);
    padding: 9px 22px;
    font-weight: 600;
    font-size: 14px;
    transition: var(--wiki-transition);
}
.wiki-form-actions .btn-primary,
.wiki-form-actions .btn-info {
    background: linear-gradient(135deg, var(--wiki-primary) 0%, var(--wiki-primary-light) 100%);
    border: none;
    color: #fff;
    box-shadow: 0 2px 8px rgba(79,70,229,0.25);
}
.wiki-form-actions .btn-primary:hover,
.wiki-form-actions .btn-info:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(79,70,229,0.35);
}
.wiki-form-actions .btn-danger {
    background: var(--wiki-danger);
    border: none;
    color: #fff;
}
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s wiki-form-panel">
                    <div class="panel-body">
                        <!-- Form Header -->
                        <div class="wiki-form-header">
                            <h4>
                                <i class="fa fa-<?php echo isset($book) ? 'edit' : 'plus-circle'; ?>"></i>
                                <?php echo $title; ?>
                            </h4>
                            <span class="wiki-form-breadcrumb">
                                <a href="<?php echo admin_url('wiki/books'); ?>"><?php echo _l('books'); ?></a>
                                &nbsp;/&nbsp;
                                <?php echo $title; ?>
                            </span>
                        </div>
                        <hr class="hr-panel-heading" />

                        <?php echo form_open($this->uri->uri_string(), array('id'=>'form_main')); ?>
                        <?php if(isset($back_url)){ ?>
                            <input type="hidden" name="back_url" value="<?php echo $back_url; ?>">
                        <?php } ?>

                        <!-- Details Section -->
                        <div class="wiki-section-label"><i class="fa fa-info-circle"></i> Book Details</div>
                        <?php $attrs = (isset($book) ? array() : array('autofocus'=>true)); ?>
                        <?php $value = (isset($book) ? $book->name : ''); ?>
                        <?php echo render_input('name', 'book_name', $value, 'text', $attrs); ?>
                        <?php $value = (isset($book) ? $book->short_description : ''); ?>
                        <?php echo render_textarea('short_description', 'book_short_description', $value); ?>

                        <!-- Permissions Section -->
                        <div class="wiki-section-label"><i class="fa fa-lock"></i> Permissions</div>
                        <div class="wiki-permission-card">
                            <label for="specific_staff"><?php echo _l('permisson_for_views'); ?></label>
                            <div class="select-notification-settings">
                                <div class="radio radio-primary radio-inline">
                                    <input type="radio" name="assign_type" value="specific_staff" id="specific_staff" <?php if (isset($book) && $book->assign_type ==  'specific_staff' || !isset($book)) { echo 'checked'; } ?>>
                                    <label for="specific_staff"><?php echo _l('specific_staff_members'); ?></label>
                                </div>
                                <div class="radio radio-primary radio-inline">
                                    <input type="radio" name="assign_type" id="roles" value="roles" <?php if (isset($book) && $book->assign_type == 'roles') {
                                    echo 'checked';
                                    } ?>>
                                    <label for="roles"><?php echo _l('staff_with_roles'); ?></label>
                                </div>
                                <div class="clearfix mtop15"></div>
                                <div id="specific_staff_assign" class="types-assign <?php if (isset($book) && $book->assign_type != 'specific_staff') { echo 'hide'; } ?>">
                                    <?php
                                        $selected = array();
                                        if (isset($book) && $book->assign_type == 'specific_staff') {
                                            $selected = wiki_unserialize($book->assign_ids, 'staff_');
                                        }
                                    ?>
                                    <?php echo render_select('assign_ids_staff[]', $members, array('staffid', array('firstname', 'lastname')), 'book_assign_specific_staff', $selected, array('multiple'=>true)); ?>
                                </div>
                                <div id="roles_assign" class="types-assign <?php if (isset($book) && $book->assign_type != 'roles' || !isset($book)) {
                                    echo 'hide';} ?>">
                                    <?php
                                        $selected = array();
                                        if (isset($book) && $book->assign_type == 'roles') {
                                            $selected = wiki_unserialize($book->assign_ids, 'role_');
                                        }
                                    ?>
                                    <?php echo render_select('assign_ids_roles[]', $roles, array('roleid', array('name')), 'book_assign_roles', $selected, array('multiple'=>true)); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="wiki-form-actions">
                            <a href="<?php  echo isset($back_url) ? $back_url : admin_url('wiki/books'); ?>" class="btn btn-default"><?php echo _l('back'); ?></a>
                            <?php if(isset($book) && has_permission('wiki_books','','delete')){ ?>
                                <a href="<?php echo admin_url('wiki/books/delete/' . $book->id); ?>" class="btn btn-danger btn-remove" data-lang="<?php echo _l('wiki_confirm_delete'); ?>"><?php echo _l('delete'); ?></a>
                            <?php } ?>
                            <button type="submit" class="btn btn-info"><i class="fa fa-check"></i> <?php echo _l('submit'); ?></button>
                        </div>

                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script src="<?php echo base_url(WIKI_ASSETS_PATH.'/js/book.js'); ?>"></script>
</body>
</html>
