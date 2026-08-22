<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <?php $favicon = get_option('favicon'); ?>
    <link rel="icon" href="<?php echo base_url('uploads/company/'.$favicon); ?>" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <meta name="description" content="<?php echo isset($article['description']) ? htmlspecialchars($article['description']) : ''; ?>">
    <link href="<?php echo base_url(WIKI_ASSETS_PATH.'/articles_show/styles/jquery.tocify.css'); ?>" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="<?php echo site_url('assets/plugins/tinymce/plugins/codesample/css/prism.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            background: #f8fafc !important;
            color: #1e293b !important;
            line-height: 1.7 !important;
        }

        /* ===== Sidebar ===== */
        .sidebar {
            height: 100%;
            width: 280px;
            position: fixed;
            z-index: 100;
            top: 0;
            left: 0;
            background: #ffffff !important;
            border-right: 1px solid #e2e8f0;
            overflow-x: hidden;
            overflow-y: auto;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 2px 0 20px rgba(0,0,0,0.04);
        }
        .sidebar .header {
            padding: 20px 22px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%) !important;
        }
        .sidebar .header #logo img {
            max-height: 32px;
            filter: brightness(0) invert(1);
        }

        .sidebar .button-header {
            display: flex !important;
            gap: 0 !important;
            border-bottom: 1px solid #e2e8f0 !important;
            background: #fff !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .sidebar .button-header a,
        .sidebar .button-header a:link,
        .sidebar .button-header a:visited {
            flex: 1 !important;
            text-align: center !important;
            padding: 12px 8px !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            color: #64748b !important;
            text-decoration: none !important;
            transition: all 0.2s ease !important;
            border-bottom: 2px solid transparent !important;
            border-top: none !important;
            border-left: none !important;
            border-right: none !important;
            background: transparent !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            margin: 0 !important;
            line-height: 1.4 !important;
        }
        .sidebar .button-header a:hover {
            color: #4f46e5 !important;
            background: rgba(79,70,229,0.04) !important;
            border-bottom-color: #4f46e5 !important;
        }
        .sidebar .button-header a i,
        .sidebar .button-header a svg {
            font-size: 13px;
            opacity: 0.7;
        }

        /* Sidebar TOC heading */
        .sidebar-toc-label {
            padding: 14px 22px 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
        }

        #toc {
            padding: 4px 0 16px;
        }
        #toc .tocify-header {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        #toc .tocify-item {
            padding: 0;
        }
        #toc .tocify-item a {
            display: block;
            padding: 8px 22px;
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            text-decoration: none !important;
            border-left: 3px solid transparent;
            transition: all 0.2s ease;
            background: transparent !important;
            border-bottom: none !important;
        }
        #toc .tocify-item a:hover {
            color: #4f46e5;
            background: rgba(79,70,229,0.04) !important;
        }
        #toc .tocify-item.active a,
        #toc .tocify-item a.active {
            color: #4f46e5;
            background: rgba(79,70,229,0.06) !important;
            border-left-color: #4f46e5;
            font-weight: 600;
        }
        #toc .tocify-subheader {
            padding-left: 12px;
        }

        /* ===== Main Content ===== */
        #main {
            margin-left: 280px;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
        }

        #header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(255,255,255,0.92) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid #e2e8f0;
            padding: 0 32px;
            height: 56px;
            display: flex;
            align-items: center;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
            min-width: 0;
        }
        .header-left .closebtn,
        .header-left .openbtn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
            text-decoration: none !important;
            background: transparent !important;
            border: none !important;
        }
        .header-left .closebtn:hover,
        .header-left .openbtn:hover {
            background: #f1f5f9 !important;
            color: #4f46e5;
        }
        .header-left .header-title-text {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Article Meta Header */
        .article-meta-bar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .article-meta-left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .article-meta-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background: rgba(79,70,229,0.08);
            color: #4f46e5;
        }
        .article-meta-info {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 13px;
            color: #64748b;
        }
        .article-meta-info span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .article-meta-info i, .article-meta-info svg { font-size: 12px; opacity: 0.6; }
        .article-meta-right {
            display: flex;
            gap: 8px;
        }
        .article-meta-action {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none !important;
            border: 1px solid #e2e8f0;
            color: #64748b !important;
            background: #fff !important;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .article-meta-action:hover {
            border-color: #4f46e5;
            color: #4f46e5 !important;
            background: rgba(79,70,229,0.04) !important;
        }

        /* Content area */
        .content-main {
            max-width: 820px;
            margin: 0 auto;
            padding: 40px 32px 80px;
        }
        .content-main h1 { font-size: 32px; font-weight: 800; margin: 32px 0 16px; color: #0f172a; line-height: 1.3; }
        .content-main h2 { font-size: 24px; font-weight: 700; margin: 28px 0 14px; color: #1e293b; line-height: 1.35; padding-bottom: 8px; border-bottom: 1px solid #e2e8f0; }
        .content-main h3 { font-size: 20px; font-weight: 700; margin: 24px 0 12px; color: #1e293b; }
        .content-main h4 { font-size: 17px; font-weight: 600; margin: 20px 0 10px; color: #334155; }
        .content-main h5 { font-size: 15px; font-weight: 600; margin: 16px 0 8px; color: #475569; }
        .content-main p { margin-bottom: 16px; font-size: 15px; color: #334155; }
        .content-main img { max-width: 100% !important; border-radius: 8px; margin: 16px 0; }
        .content-main ul, .content-main ol { margin-bottom: 16px; padding-left: 24px; }
        .content-main li { margin-bottom: 6px; font-size: 15px; color: #334155; }
        .content-main blockquote {
            border-left: 4px solid #4f46e5;
            background: rgba(79,70,229,0.04);
            padding: 16px 20px;
            margin: 16px 0;
            border-radius: 0 8px 8px 0;
            font-style: italic;
            color: #475569;
        }
        .content-main pre {
            background: #1e293b;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 10px;
            overflow-x: auto;
            margin: 16px 0;
            font-size: 14px;
            line-height: 1.6;
        }
        .content-main code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 14px;
            color: #4f46e5;
        }
        .content-main pre code {
            background: transparent;
            padding: 0;
            color: inherit;
        }
        .content-main table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .content-main table th {
            background: #f8fafc;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #64748b;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }
        .content-main table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }
        .content-main table tr:hover td { background: #fafbfc; }
        .content-main a { color: #4f46e5; text-decoration: none; border-bottom: 1px solid rgba(79,70,229,0.2); transition: all 0.2s ease; }
        .content-main a:hover { border-bottom-color: #4f46e5; }

        /* Empty content placeholder */
        .content-empty {
            text-align: center;
            padding: 80px 20px;
            color: #94a3b8;
        }
        .content-empty i { font-size: 48px; margin-bottom: 16px; display: block; color: #e2e8f0; }
        .content-empty h3 { font-size: 18px; font-weight: 600; color: #64748b; margin-bottom: 8px; }
        .content-empty p { font-size: 14px; }

        /* Mindmap content */
        img.wiki-mindmap-thumb-content {
            display: block;
            margin: auto;
            max-width: 100%;
            height: auto;
        }

        /* ===== Print ===== */
        @media print {
            .sidebar { display: none; }
            #main { margin-left: 0; }
            #header, .article-meta-bar { display: none; }
            .content-main { padding: 20px; max-width: 100%; }
        }
        /* ===== Mobile ===== */
        @media (max-width: 768px) {
            .sidebar { width: 0; }
            #main { margin-left: 0; }
            #header { padding: 0 16px; }
            .article-meta-bar { padding: 12px 16px; }
            .content-main { padding: 24px 16px 60px; }
            .content-main h1 { font-size: 26px; }
            .content-main h2 { font-size: 20px; }
        }
    </style>
</head>

<body>

    <div id="mySidebar" class="sidebar">
        <div class="header">
           <div id="logo">
              <?php get_company_logo(get_admin_uri().'/') ?>
           </div>
        </div>
        <div class="button-header">
            <a href="<?php echo admin_url('wiki/articles'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h13zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13z"/><path d="M3 5.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zM3 8a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9A.5.5 0 0 1 3 8zm0 2.5a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5z"/></svg>
                <?php echo _l('articles'); ?>
            </a>
            <a href="<?php echo admin_url('wiki/books'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M1 2.828c.885-.37 2.154-.769 3.388-.893 1.33-.134 2.458.063 3.112.752v9.746c-.935-.53-2.12-.603-3.213-.493-1.18.12-2.37.461-3.287.811V2.828zm7.5-.141c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/></svg>
                <?php echo _l('books'); ?>
            </a>
        </div>
        <div class="sidebar-toc-label">On this page</div>
        <div id="toc"></div>
    </div>

    <div id="main">
        <div id="header">
            <div class="header-left">
              <a href="javascript:void(0)" id="closebtn" class="closebtn">
                  <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16">
                      <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z" />
                  </svg>
              </a>
              <span class="openbtn" id="openbtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
              </svg>
              </span>
              <span class="header-title-text"><?php echo $article['title']; ?></span>
            </div>
        </div>

        <!-- Article Meta Bar -->
        <div class="article-meta-bar">
            <div class="article-meta-left">
                <?php
                    $art_type = isset($article['type']) ? $article['type'] : 'document';
                    $art_type_label = ($art_type == 'mindmap') ? 'Mindmap' : 'Document';
                ?>
                <span class="article-meta-badge" style="<?php echo ($art_type == 'mindmap') ? 'background:rgba(6,182,212,0.1);color:#06b6d4;' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h13z"/></svg>
                    <?php echo $art_type_label; ?>
                </span>
                <div class="article-meta-info">
                    <?php if(isset($article['author_fullname'])){ ?>
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4z"/></svg>
                        <?php echo $article['author_fullname']; ?>
                    </span>
                    <?php } ?>
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm-3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm-5 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1z"/><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>
                        <?php echo date("M d, Y", strtotime($article['created_at'])); ?>
                    </span>
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/></svg>
                        <?php echo isset($article['view_counter']) ? $article['view_counter'] : 0; ?> views
                    </span>
                </div>
            </div>
            <div class="article-meta-right">
                <?php if(has_permission('wiki_articles','','edit') || has_permission('wiki_books','','edit') || is_admin()){ ?>
                    <a href="<?php echo admin_url('wiki/articles/article/' . $article['id']); ?>" class="article-meta-action">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5z"/></svg>
                        Edit
                    </a>
                <?php } ?>
                <a href="javascript:window.print();" class="article-meta-action">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/><path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 10a1 1 0 0 1-1-1V9h8v3a1 1 0 0 1-1 1H5z"/></svg>
                    Print
                </a>
            </div>
        </div>

        <div class="content-main">
            <?php
                $content = isset($article['content']) ? trim($article['content']) : '';
                if(!empty($content)){
                    echo $content;
                } else {
            ?>
                <div class="content-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#e2e8f0" viewBox="0 0 16 16"><path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h13zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13z"/><path d="M3 5.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zM3 8a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9A.5.5 0 0 1 3 8zm0 2.5a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5z"/></svg>
                    <h3>No content yet</h3>
                    <p>This article doesn't have any content. Click Edit to start writing.</p>
                </div>
            <?php } ?>
        </div>
    </div>

    <script src="<?php echo base_url(WIKI_ASSETS_PATH.'/articles_show/javascripts/jquery/jquery-1.8.3.min.js'); ?>"></script>
    <script src="<?php echo base_url(WIKI_ASSETS_PATH.'/articles_show/javascripts/jqueryui/jquery-ui-1.9.1.custom.min.js'); ?>"></script>
    <script src="<?php echo base_url(WIKI_ASSETS_PATH.'/articles_show/javascripts/jquery.tocify.min.js'); ?>"></script>
    <script src="<?php echo base_url(WIKI_ASSETS_PATH.'/js/article_show.js'); ?>?v=<?php echo time(); ?>"></script>

</body>

</html>