<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link rel="stylesheet" type="text/css" href="<?php echo base_url(WIKI_ASSETS_PATH.'/css/wiki_styles.css'); ?>?v=<?php echo time(); ?>">
<div id="wrapper">
    <div class="content">

        <!-- ===== Page Header with Stats ===== -->
        <div class="wiki-page-header">
            <h1 class="wiki-header-title">
                <i class="fa fa-book"></i>
                <?php echo _l('wiki_book'); ?>
            </h1>
            <div class="wiki-header-stats">
                <div class="wiki-header-stat">
                    <span class="stat-value"><?php echo count($books); ?></span>
                    <span class="stat-label"><?php echo _l('books'); ?></span>
                </div>
                <div class="wiki-header-stat">
                    <span class="stat-value"><?php echo $articles_count; ?></span>
                    <span class="stat-label"><?php echo _l('wiki_articles'); ?></span>
                </div>
                <div class="wiki-header-stat">
                    <span class="stat-value"><?php echo number_format($total_views); ?></span>
                    <span class="stat-label">Total Views</span>
                </div>
            </div>
        </div>

        <!-- ===== Toolbar Row ===== -->
        <div class="wiki-buttons-wrap">
            <div style="display:flex;align-items:center;gap:12px;">
                <?php if(has_permission('wiki_books','','create')){ ?>
                    <a href="<?php echo admin_url('wiki/books/book'); ?>" class="wiki-btn-create">
                        <i class="fa fa-plus"></i>
                        <?php echo _l('wiki_new_book'); ?>
                    </a>
                <?php } ?>
                <!-- View toggle -->
                <div class="wiki-view-toggle">
                    <button class="wiki-view-btn active" data-view="grid" title="Grid View">
                        <i class="fa fa-th-large"></i>
                    </button>
                    <button class="wiki-view-btn" data-view="list" title="List View">
                        <i class="fa fa-list"></i>
                    </button>
                </div>
            </div>
            <div class="wiki-search-wrap">
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
        <?php echo form_close(); ?>

        <!-- ===== Books Grid ===== -->
        <div class="row wiki-grid wiki-books-grid" id="wikiBooksList">
        <?php if(count($books) == 0){ ?>
            <div class="col-xs-12">
                <div class="wiki-empty-state">
                    <span class="empty-icon"><i class="fa fa-book"></i></span>
                    <h3><?php echo _l('wiki_empty_results'); ?></h3>
                    <p>No books found. Create your first book to start organizing articles.</p>
                    <?php if(has_permission('wiki_books','','create')){ ?>
                        <a href="<?php echo admin_url('wiki/books/book'); ?>" class="wiki-btn-create">
                            <i class="fa fa-plus"></i>
                            <?php echo _l('wiki_new_book'); ?>
                        </a>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
        <?php $cardIndex = 0; foreach($books as $book){ $cardIndex++; ?>
            <div class="col-md-4 col-sm-6 wiki-animate-in wiki-book-col" data-book-id="<?php echo $book['id']; ?>">
                <div class="wiki-book-card" data-book-id="<?php echo $book['id']; ?>">
                    <!-- Card accent bar -->
                    <div class="wiki-book-accent"></div>
                    
                    <!-- Card Body -->
                    <div class="wiki-book-card-body">
                        <!-- Top row: icon + actions -->
                        <div class="wiki-book-card-top">
                            <div class="wiki-book-icon-wrap">
                                <i class="fa fa-book"></i>
                                <span class="wiki-book-article-count"><?php echo $book['articles_total']; ?></span>
                            </div>
                            <div class="wiki-book-actions">
                                <a href="<?php echo admin_url('wiki/articles') ?>?filter_book_id=<?php echo $book['id']; ?>" class="wiki-action-pill wiki-action-articles" title="<?php echo _l('wiki_articles'); ?>">
                                    <i class="fa-solid fa-file-lines"></i>
                                    <span>Articles</span>
                                </a>
                                <?php if(has_permission('wiki_books','','edit')){ ?>
                                    <?php
                                        $book_back_url = current_url();
                                        if(isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] != ''){
                                            $book_back_url .= '?' . $_SERVER['QUERY_STRING'];
                                        }
                                        $book_back_url = urlencode($book_back_url);
                                    ?>
                                    <a href="<?php echo admin_url('wiki/books/book/' . $book['id']) . '?back_url=' . $book_back_url; ?>" class="wiki-action-pill wiki-action-edit" title="<?php echo _l('edit'); ?>">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                <?php } ?>
                                <?php if(has_permission('wiki_books','','delete')){ ?>
                                    <a href="<?php echo admin_url('wiki/books/delete/' . $book['id']); ?>" class="wiki-action-pill wiki-action-delete wiki-btn-delete" title="<?php echo _l('delete'); ?>" data-lang="<?php echo _l('wiki_confirm_delete'); ?>">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>

                        <!-- Title -->
                        <div class="wiki-book-card-title">
                            <h3>
                                <a href="<?php echo admin_url('wiki/articles') ?>?filter_book_id=<?php echo $book['id']; ?>"><?php echo $book['name']; ?></a>
                            </h3>
                        </div>

                        <!-- Description -->
                        <div class="wiki-book-card-desc">
                            <p><?php echo $book['short_description']; ?></p>
                        </div>

                        <!-- Stats chips -->
                        <div class="wiki-book-stats-row">
                            <div class="wiki-stat-chip">
                                <i class="fa-solid fa-file-lines"></i>
                                <span><?php echo $book['articles_total']; ?> articles</span>
                            </div>
                            <div class="wiki-stat-chip">
                                <i class="fa fa-eye"></i>
                                <span><?php echo number_format($book['total_views']); ?> views</span>
                            </div>
                            <div class="wiki-stat-chip">
                                <i class="fa fa-users"></i>
                                <span><?php echo count(wiki_unserialize($book['assign_ids'], '')); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="wiki-book-card-footer">
                        <div class="wiki-book-author-row">
                            <img class="wiki-book-author-avatar" src="<?php echo staff_profile_image_url($book['author_id']); ?>" alt="<?php echo $book['author_fullname']; ?>" />
                            <div class="wiki-book-author-info">
                                <span class="wiki-book-author-name"><?php echo $book['author_fullname']; ?></span>
                                <span class="wiki-book-date">
                                    <i class="fa fa-clock-o"></i>
                                    <?php echo date("M d, Y", strtotime($book['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        <button class="wiki-preview-btn" data-book-id="<?php echo $book['id']; ?>" title="Quick Preview">
                            <i class="fa fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php } ?>
        </div>

        <!-- ===== Preview Slide-out Panel ===== -->
        <div class="wiki-preview-overlay" id="wikiPreviewOverlay"></div>
        <div class="wiki-preview-panel" id="wikiPreviewPanel">
            <div class="wiki-preview-header">
                <div class="wiki-preview-header-content">
                    <div class="wiki-preview-icon"><i class="fa fa-book"></i></div>
                    <div>
                        <h2 class="wiki-preview-title" id="previewTitle">Book Title</h2>
                        <p class="wiki-preview-subtitle" id="previewDesc">Description</p>
                    </div>
                </div>
                <button class="wiki-preview-close" id="previewClose">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="wiki-preview-body" id="previewBody">
                <div class="wiki-preview-loading">
                    <div class="wiki-spinner"></div>
                    <p>Loading articles...</p>
                </div>
            </div>
            <div class="wiki-preview-footer" id="previewFooter" style="display:none;">
                <a href="#" id="previewViewAllLink" class="wiki-preview-viewall">
                    <i class="fa fa-arrow-right"></i>
                    View All Articles
                </a>
            </div>
        </div>

    </div>
</div>
<?php init_tail(); ?>
<script>
    const APP_CSRF_TOKEN = "<?php echo $this->security->get_csrf_hash(); ?>";
    const APP_CSRF_NAME = "<?php echo $this->security->get_csrf_token_name(); ?>";
    const BOOK_PREVIEW_URL = "<?php echo admin_url('wiki/books/preview/'); ?>";
    const ARTICLE_SHOW_URL = "<?php echo admin_url('wiki/articles/show/'); ?>";
</script>
<script src="<?php echo base_url(WIKI_ASSETS_PATH.'/js/books_manage.js'); ?>?v=<?php echo time(); ?>"></script>
</body>
</html>
