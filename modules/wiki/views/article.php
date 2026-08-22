<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" type="text/css" href="<?php echo base_url(WIKI_ASSETS_PATH.'/css/wiki_styles.css'); ?>?v=<?php echo time(); ?>">
<style>
/* Article Form — Premium Enhancements */
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

/* Section labels */
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

/* Publish link area */
.wiki-publish-card {
    background: var(--wiki-bg);
    border: 1px solid var(--wiki-border);
    border-radius: var(--wiki-radius-sm);
    padding: 14px 16px;
    margin-top: 8px;
}
.wiki-publish-card p {
    margin: 0 0 8px;
    font-size: 13px;
    word-break: break-all;
}
.wiki-publish-card a {
    color: var(--wiki-primary);
}

/* Content editor section */
.wiki-content-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--wiki-border);
}
.wiki-content-section p {
    font-size: 13px;
    color: var(--wiki-text-secondary);
    margin-bottom: 8px;
}
.wiki-content-section small {
    color: var(--wiki-text-muted);
}

/* Bottom toolbar */
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
.wiki-form-actions .btn-primary {
    background: linear-gradient(135deg, var(--wiki-primary) 0%, var(--wiki-primary-light) 100%);
    border: none;
    box-shadow: 0 2px 8px rgba(79,70,229,0.25);
}
.wiki-form-actions .btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(79,70,229,0.35);
}
.wiki-form-actions .btn-danger {
    background: var(--wiki-danger);
    border: none;
}
.wiki-form-actions .btn-success {
    background: var(--wiki-success);
    border: none;
    color: #fff;
}
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s wiki-form-panel">
                    <div class="panel-body">
                        <!-- Form Header -->
                        <div class="wiki-form-header">
                            <h4>
                                <i class="fa fa-<?php echo isset($article) ? 'edit' : 'plus-circle'; ?>"></i>
                                <?php echo $title; ?>
                            </h4>
                            <div style="display:flex;align-items:center;gap:14px;">
                                <span class="wiki-form-breadcrumb">
                                    <a href="<?php echo admin_url('wiki/articles'); ?>"><?php echo _l('wiki_articles'); ?></a>
                                    &nbsp;/&nbsp;
                                    <?php echo $title; ?>
                                </span>
                                <a href="<?php echo isset($back_url) ? $back_url : admin_url('wiki/articles'); ?>" class="btn btn-default btn-sm" style="border-radius:8px;font-weight:600;display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border:1px solid var(--wiki-border);transition:all .2s;">
                                    <i class="fa fa-arrow-left"></i> <?php echo _l('back'); ?>
                                </a>
                            </div>
                        </div>

                        <hr class="hr-panel-heading" />
                        <?php echo form_open($this->uri->uri_string(), array('id'=>'form_main')); ?>
                        <?php if(isset($article)){ ?>
                            <input type="hidden" name="article_id" value="<?php echo $article->id; ?>">
                        <?php } ?>
                        <?php if(isset($clone_id)){ ?>
                            <input type="hidden" name="clone_id" value="<?php echo $clone_id; ?>">
                        <?php } ?>
                        <?php if(isset($back_url)){ ?>
                            <input type="hidden" name="back_url" value="<?php echo $back_url; ?>">
                        <?php } ?>
                        <?php
                            if (isset($article)) {
                                $selected_type = $article->type;
                            }else{
                                $selected_type = 'document';
                            }
                        ?>

                        <!-- Details Section -->
                        <div class="wiki-section-label"><i class="fa fa-info-circle"></i> Article Details</div>
                        <div class="row">
                            <div class="col-md-4">
                                <div>
                                    <?php
                                        $selected = array();
                                        if (isset($article)) {
                                            $selected = $article->book_id;
                                        } elseif (isset($preset_book_id)) {
                                            $selected = $preset_book_id;
                                        } elseif (!empty($books)) {
                                            $selected = $books[0]['id'];
                                        }
                                    ?>
                                    <?php echo render_select('book_id', $books, array('id', array('name')), 'wiki_book', $selected, []); ?>
                                </div>
                                <?php $attrs = (isset($article) ? array() : array('autofocus'=>true)); ?>
                                
                                <?php $value = (isset($article) ? $article->title : ''); ?>
                                <?php echo render_input('title', 'wiki_title', $value, 'text', $attrs); ?>
                            </div>
                           
                            <div class="col-md-4">
                                <?php $value = (isset($article) ? $article->description : ''); ?>
                                <?php echo render_textarea('description', 'wiki_description', $value,['rows' => 6]); ?>
                                <?php $value = (isset($article) ? $article->content : ''); ?>
                            </div>

                            <div class="col-md-4">
                                <?php if(isset($article)){ ?>
                                    <div class="checkbox checkbox-primary">
                                        <small><?php echo _l('get_link_help'); ?></small><br>
                                        <input type="checkbox" name="is_publish" id="is_publish" <?php if(isset($article)){if($article->is_publish == 1){echo 'checked';} } else {echo 'checked';} ?>>
                                        <label for="is_publish"><?php echo _l('get_link'); ?></label>
                                    </div>
                                    <div class="wiki-publish-card">
                                        <?php $tmp_publish_link = site_url('wiki/' . $article->slug); ?>
                                        <p>
                                            <a href="<?php echo $tmp_publish_link; ?>" target="_blank"><?php echo $tmp_publish_link; ?></a>
                                        </p>
                                        <span class="wiki-btn-copy" data-copy="<?php echo $tmp_publish_link; ?>" data-lang="<?php echo _l('wiki_copy_success'); ?>"><button type="button" class="btn btn-default btn-sm"><i class="fa fa-copy"></i> Copy Link</button></span>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>

                        <!-- Type Section -->
                        <div class="wiki-section-label"><i class="fa fa-cog"></i> Article Type</div>
                        <div class="row">
                            <div class="col-md-4">
                                <div>
                                    <?php echo render_select('type', [['id' => 'document', 'name' => _l('wiki_document'),],['id' => 'mindmap', 'name' => _l('wiki_mindmap'),],], array('id', array('name')), 'wiki_type', $selected_type, []); ?>
                                </div>
                            </div>
                            <div class="col-md-4 wiki-article-type-wrap <?php echo $selected_type == 'mindmap' ? '' : 'hide' ?>" data-type="mindmap">
                                <div>
                                    <label for="" class="control-label"><?php echo _l('wiki_help_build'); ?></label>
                                </div>
                                <button type="submit" name="submit" value="SAVE_AND_BUILD" class="btn btn-success"><?php echo _l('wiki_build_map'); ?></button>
                                <?php 
                                    $value = null;
                                    if(isset($article) && isset($article->mindmap_thumb) && $article->mindmap_thumb != ""){
                                        $value = $article->mindmap_thumb;
                                    }
                                ?>
                                <img class="wiki-mindmap-thumb" src="<?php echo isset($value) ? wiki_get_mindmap_thumb($value) : wiki_get_mindmap_thumb() ?>" alt="">
                            </div>
                        </div>

                        <!-- Content Editor Section -->
                        <div class="wiki-article-type-wrap wiki-content-section <?php echo $selected_type == 'document' ? '' : 'hide' ?>" data-type="document">
                            <div class="wiki-section-label"><i class="fa fa-pencil"></i> Content</div>
                            <p><span class="text-warning">(<?php echo _l('article_help_menu'); ?>)</span></p>
                            <p><small><?php echo _l('tinymce-help-article'); ?></small></p>
                            <?php $value = (isset($article) ? $article->content : ''); ?>
                            <?php echo render_textarea('content','',$value,array(),array(),'','tinymce-content'); ?>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="wiki-form-actions">
                            <a href="<?php  echo isset($back_url) ? $back_url : admin_url('wiki/articles'); ?>" class="btn btn-default"><?php echo _l('back'); ?></a>
                            <?php if(isset($article)){ ?> 
                            <a href="<?php echo admin_url('wiki/articles/show/' . $article->id) ?>" class="btn btn-success"><?php echo _l('view'); ?></a>
                            <?php } ?>
                            <?php if(isset($article) && (has_permission('wiki_articles','','delete') || has_permission('wiki_books','','delete') || is_admin())){ ?>
                                <a href="<?php echo admin_url('wiki/articles/delete/' . $article->id); ?>" class="btn btn-danger btn-remove" data-lang="<?php echo _l('wiki_confirm_delete'); ?>"><?php echo _l('delete'); ?></a>
                            <?php } ?>
                            <button type="submit" name="submit" value="ONLY_SAVE" class="btn btn-primary"><i class="fa fa-check"></i> <?php echo _l('submit'); ?></button>
                         </div>

                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script src="<?php echo base_url(WIKI_ASSETS_PATH.'/js/article.js'); ?>"></script>
</body>
</html>
