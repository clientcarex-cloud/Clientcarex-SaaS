<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" type="text/css" href="<?php echo base_url(WIKI_ASSETS_PATH.'/css/wiki_styles.css'); ?>?v=<?php echo time(); ?>">
<div id="wrapper">
    <div class="content">

        <!-- ===== Page Header with Stats ===== -->
        <div class="wiki-page-header">
            <h1 class="wiki-header-title">
                <i class="fa-solid fa-file-lines"></i>
                <?php echo _l('wiki_articles'); ?>
            </h1>
            <div class="wiki-header-stats">
                <div class="wiki-header-stat">
                    <span class="stat-value"><?php echo count($articles); ?></span>
                    <span class="stat-label"><?php echo _l('wiki_articles'); ?></span>
                </div>
                <div class="wiki-header-stat">
                    <span class="stat-value"><?php echo count($books); ?></span>
                    <span class="stat-label"><?php echo _l('books'); ?></span>
                </div>
            </div>
        </div>

        <!-- ===== Toolbar Row ===== -->
        <div class="wiki-buttons-wrap">
            <div>
                <?php if(has_permission('wiki_articles','','create') || has_permission('wiki_books','','create') || is_admin()){ ?>
                    <a href="<?php echo admin_url('wiki/articles/article'); ?><?php echo isset($filter_book_id) && $filter_book_id ? '?book_id=' . $filter_book_id : ''; ?>" class="wiki-btn-create">
                        <i class="fa fa-plus"></i>
                        <?php echo _l('wiki_new_article'); ?>
                    </a>
                <?php } ?>
            </div>
            <div class="wiki-search-wrap">
                <?php
                    $selected = array();
                    if (isset($filter_book_id)) {
                        $selected = $filter_book_id;
                    }
                ?>
                <?php echo render_select('filter_book_id', $books, array('id', array('name')), '', $selected, [], [], 'wiki-dropdown-wrapper'); ?>
                <div class="input-group">
                    <a href="javascript:void(0);" class="input-group-addon btn-search-list">
                        <span class="fa fa-search"></span>
                    </a>
                    <input type="search" class="form-control input-search-list" value="<?php echo $filter_query; ?>" placeholder="<?php echo _l('wiki_search'); ?>...">
                </div>
            </div>
        </div>

        <!-- Hidden search form -->
        <?php echo form_open($this->uri->uri_string(), array('id'=>'form_search', 'class' =>'hide', 'method'=>'get')); ?>
            <input type="hidden" name="filter_query" value="<?php echo $filter_query; ?>">
            <?php if(isset($filter_book_id)){ ?>
                <input type="hidden" name="filter_book_id" value="<?php echo $filter_book_id; ?>">
            <?php } ?>
            <?php if(isset($filter_is_owner)){ ?>
                <input type="hidden" name="filter_is_owner" value="<?php echo $filter_is_owner; ?>">
            <?php } ?>
        <?php echo form_close(); ?>

        <!-- ===== Articles Grid ===== -->
        <div class="row wiki-grid">
        <?php if(count($articles) == 0){ ?>
            <div class="col-xs-12">
                <div class="wiki-empty-state">
                    <span class="empty-icon"><i class="fa-solid fa-file-lines"></i></span>
                    <h3><?php echo _l('wiki_empty_results'); ?></h3>
                    <p>No articles found. Try adjusting your search or create a new article.</p>
                    <?php if(has_permission('wiki_articles','','create') || has_permission('wiki_books','','create') || is_admin()){ ?>
                        <a href="<?php echo admin_url('wiki/articles/article'); ?>" class="wiki-btn-create">
                            <i class="fa fa-plus"></i>
                            <?php echo _l('wiki_new_article'); ?>
                        </a>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
        <?php $cardIndex = 0; foreach($articles as $article){ $cardIndex++; ?>
            <div class="col-md-4 col-sm-6 wiki-animate-in">
                <div class="wiki-book-card">
                    <!-- Accent bar -->
                    <?php
                        $type = isset($article['type']) ? $article['type'] : 'document';
                    ?>
                    <div class="wiki-book-accent" style="background: linear-gradient(90deg, <?php echo ($type == 'mindmap') ? 'var(--wiki-accent), #22d3ee' : 'var(--wiki-primary), var(--wiki-accent)'; ?>);"></div>

                    <div class="wiki-book-card-body">
                        <!-- Top: Icon + Actions -->
                        <div class="wiki-book-card-top">
                            <div class="wiki-book-icon-wrap" style="<?php echo ($type == 'mindmap') ? 'background: var(--wiki-accent-soft); color: var(--wiki-accent);' : ''; ?>">
                                <i class="fa-solid <?php echo ($type == 'mindmap') ? 'fa-sitemap' : 'fa-file-lines'; ?>"></i>
                            </div>
                            <div class="wiki-book-actions">
                                <a href="<?php echo admin_url('wiki/articles/show/' . $article['id']) ?>" class="wiki-action-pill wiki-action-articles" title="<?php echo _l('view'); ?>">
                                    <i class="fa fa-eye"></i>
                                    <span>View</span>
                                </a>
                                <?php if(has_permission('wiki_articles','','edit') || has_permission('wiki_books','','edit') || is_admin()){ ?>
                                    <?php
                                        $article_back_url = current_url();
                                        if(isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] != ''){
                                            $article_back_url .= '?' . $_SERVER['QUERY_STRING'];
                                        }
                                        $article_back_url = urlencode($article_back_url);
                                    ?>
                                    <a href="<?php echo admin_url('wiki/articles/article/' . $article['id']) . '?back_url=' . $article_back_url; ?>" class="wiki-action-pill wiki-action-edit" title="<?php echo _l('edit'); ?>">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                <?php } ?>
                                <?php if(has_permission('wiki_articles','','create') || has_permission('wiki_books','','create') || is_admin()){ ?>
                                    <?php
                                        $clone_back_url = current_url();
                                        if(isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] != ''){
                                            $clone_back_url .= '?' . $_SERVER['QUERY_STRING'];
                                        }
                                        $clone_back_url = urlencode($clone_back_url);
                                    ?>
                                    <a href="<?php echo admin_url('wiki/articles/article'); ?>?clone_id=<?php echo $article['id']; ?>&back_url=<?php echo $clone_back_url; ?>" class="wiki-action-pill" title="<?php echo _l('wiki_clone_article'); ?>">
                                        <i class="fa fa-copy"></i>
                                    </a>
                                <?php } ?>
                                <a href="javascript:void(0);" class="wiki-action-pill wiki-btn-bookmark <?php echo isset($article['bookmark_id']) ? 'wiki-bookmark-on' : 'wiki-bookmark-off'; ?>" title="Bookmark" data-id="<?php echo $article['id']; ?>">
                                    <i class="fa fa-bookmark"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Type badge + Date -->
                        <div class="wiki-article-meta">
                            <?php
                                $typeBadgeClass = ($type == 'mindmap') ? 'badge-mindmap' : 'badge-document';
                                $typeLabel = ($type == 'mindmap') ? _l('wiki_mindmap') : _l('wiki_document');
                            ?>
                            <span class="wiki-type-badge <?php echo $typeBadgeClass; ?>">
                                <i class="fa-solid <?php echo ($type == 'mindmap') ? 'fa-sitemap' : 'fa-file-lines'; ?>"></i>
                                <?php echo $typeLabel; ?>
                            </span>
                            <span class="wiki-meta-item">
                                <i class="fa fa-clock-o"></i>
                                <?php echo date("M d, Y", strtotime($article['created_at'])); ?>
                            </span>
                        </div>

                        <!-- Title -->
                        <div class="wiki-book-card-title">
                            <h3><a href="<?php echo admin_url('wiki/articles/show/' . $article['id']) ?>"><?php echo $article['title']; ?></a></h3>
                        </div>

                        <!-- Description -->
                        <div class="wiki-book-card-desc">
                            <p><?php echo $article['description']; ?></p>
                        </div>

                        <!-- Stats -->
                        <div class="wiki-book-stats-row">
                            <div class="wiki-stat-chip">
                                <i class="fa fa-eye"></i>
                                <span><?php echo $article['view_counter']; ?> views</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="wiki-book-card-footer">
                        <div class="wiki-book-author-row">
                            <img class="wiki-book-author-avatar" src="<?php echo staff_profile_image_url($article['author_id']); ?>" alt="<?php echo $article['author_fullname']; ?>" />
                            <div class="wiki-book-author-info">
                                <span class="wiki-book-author-name"><?php echo $article['author_fullname']; ?></span>
                                <span class="wiki-book-date">
                                    <i class="fa fa-book"></i>
                                    <?php echo $article['book_name']; ?>
                                </span>
                            </div>
                        </div>
                        <a href="<?php echo admin_url('wiki/articles/show/' . $article['id']) ?>" class="wiki-preview-btn" title="View Article">
                            <i class="fa fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php } ?>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    const APP_CSRF_TOKEN = "<?php echo $this->security->get_csrf_hash(); ?>";
    const bookmark_switch_url = "<?php echo admin_url('wiki/articles/bookmark_switch'); ?>";
</script>
<script src="<?php echo base_url(WIKI_ASSETS_PATH.'/js/articles_manage.js'); ?>?v=<?php echo time(); ?>">
</script>
</body>
</html>
