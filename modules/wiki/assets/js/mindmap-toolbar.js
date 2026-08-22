/**
 * Mind Map — Advanced Floating Toolbar
 * =====================================
 * Adds: toolbar, search, zoom, export, themes, shortcuts, stats, autosave
 */
(function () {
  'use strict';

  var THEMES = [
    { id: 'fresh-blue',       name: 'Fresh Blue',       color: '#4A90D9' },
    { id: 'fresh-green',      name: 'Fresh Green',      color: '#10b981' },
    { id: 'fresh-red',        name: 'Fresh Red',        color: '#ef4444' },
    { id: 'fresh-purple',     name: 'Fresh Purple',     color: '#8b5cf6' },
    { id: 'fresh-pink',       name: 'Fresh Pink',       color: '#ec4899' },
    { id: 'fresh-soil',       name: 'Fresh Soil',       color: '#a16207' },
    { id: 'fish',             name: 'Fish',             color: '#0ea5e9' },
    { id: 'wire',             name: 'Wire',             color: '#6b7280' },
    { id: 'snow',             name: 'Snow',             color: '#64748b' },
    { id: 'tianpan',          name: 'Tianpan',          color: '#3b82f6' },
    { id: 'city',             name: 'City',             color: '#475569' },
    { id: 'cosmos',           name: 'Cosmos',           color: '#1e293b' }
  ];

  var currentZoom = 100;
  var searchResults = [];
  var searchIdx = -1;
  var autosaveTimer = null;
  var AUTOSAVE_INTERVAL = 30000; // 30 seconds

  /* ------------------------------------------------------------------ */
  /*  Build Toolbar HTML                                                 */
  /* ------------------------------------------------------------------ */
  function buildToolbar() {
    var isMac = navigator.platform.indexOf('Mac') > -1;
    var mod = isMac ? '⌘' : 'Ctrl';

    var html =
    '<div class="mm-toolbar" id="mmToolbar">' +
      /* Back button */
      '<a href="' + (typeof MINDMAP_BACK_URL !== 'undefined' ? MINDMAP_BACK_URL : '#') + '" class="mm-tb-btn mm-tb-back" data-tooltip="Back to Article"><i class="fa fa-arrow-left"></i></a>' +
      '<span class="mm-tb-divider"></span>' +

      /* Undo / Redo */
      '<button class="mm-tb-btn" id="mmTbUndo" data-tooltip="Undo (' + mod + '+Z)"><i class="fa fa-undo"></i></button>' +
      '<button class="mm-tb-btn" id="mmTbRedo" data-tooltip="Redo (' + mod + '+Y)"><i class="fa fa-repeat"></i></button>' +
      '<span class="mm-tb-divider"></span>' +

      /* Node ops */
      '<button class="mm-tb-btn" id="mmTbAddChild" data-tooltip="Add Child (Tab)"><i class="fa fa-indent"></i></button>' +
      '<button class="mm-tb-btn" id="mmTbAddSibling" data-tooltip="Add Sibling (Enter)"><i class="fa fa-long-arrow-right"></i></button>' +
      '<button class="mm-tb-btn danger" id="mmTbDelete" data-tooltip="Delete (Del)"><i class="fa fa-trash-o"></i></button>' +
      '<span class="mm-tb-divider"></span>' +

      /* Expand / Collapse */
      '<button class="mm-tb-btn" id="mmTbExpand" data-tooltip="Expand All"><i class="fa fa-expand"></i></button>' +
      '<button class="mm-tb-btn" id="mmTbCollapse" data-tooltip="Collapse All"><i class="fa fa-compress"></i></button>' +
      '<span class="mm-tb-divider"></span>' +

      /* Zoom */
      '<div class="mm-tb-zoom-group">' +
        '<button class="mm-tb-btn" id="mmTbZoomOut" data-tooltip="Zoom Out"><i class="fa fa-search-minus"></i></button>' +
        '<span class="mm-tb-zoom-label" id="mmTbZoomLabel">100%</span>' +
        '<button class="mm-tb-btn" id="mmTbZoomIn" data-tooltip="Zoom In"><i class="fa fa-search-plus"></i></button>' +
        '<button class="mm-tb-btn" id="mmTbZoomFit" data-tooltip="Fit to Screen"><i class="fa fa-crosshairs"></i></button>' +
      '</div>' +
      '<span class="mm-tb-divider"></span>' +

      /* Search */
      '<button class="mm-tb-btn" id="mmTbSearch" data-tooltip="Search (' + mod + '+F)"><i class="fa fa-search"></i></button>' +

      /* Fullscreen */
      '<button class="mm-tb-btn" id="mmTbFullscreen" data-tooltip="Fullscreen (F11)"><i class="fa fa-arrows-alt"></i></button>' +
      '<span class="mm-tb-divider"></span>' +

      /* Theme */
      '<div style="position:relative">' +
        '<button class="mm-tb-btn" id="mmTbTheme" data-tooltip="Theme"><i class="fa fa-paint-brush"></i></button>' +
        buildThemeDropdown() +
      '</div>' +

      /* Export */
      '<div style="position:relative">' +
        '<button class="mm-tb-btn" id="mmTbExport" data-tooltip="Export"><i class="fa fa-download"></i></button>' +
        '<div class="mm-export-dropdown" id="mmExportDropdown">' +
          '<button class="mm-export-item" data-export="png"><i class="fa fa-file-image-o"></i> Export as PNG</button>' +
          '<button class="mm-export-item" data-export="json"><i class="fa fa-file-code-o"></i> Export as JSON</button>' +
          '<button class="mm-export-item" data-export="markdown"><i class="fa fa-file-text-o"></i> Export as Markdown</button>' +
          '<button class="mm-export-item" data-export="text"><i class="fa fa-file-o"></i> Export as Text</button>' +
        '</div>' +
      '</div>' +
      '<span class="mm-tb-divider"></span>' +

      /* Shortcuts help */
      '<button class="mm-tb-btn" id="mmTbHelp" data-tooltip="Keyboard Shortcuts"><i class="fa fa-keyboard-o"></i></button>' +
    '</div>' +

    /* Stats badge */
    '<div class="mm-stats-badge" id="mmStatsBadge">' +
      '<div class="mm-stat-item"><i class="fa fa-sitemap"></i> <span>Nodes:</span> <span class="mm-stat-value" id="mmStatNodes">0</span></div>' +
      '<div class="mm-stat-item"><i class="fa fa-bars"></i> <span>Depth:</span> <span class="mm-stat-value" id="mmStatDepth">0</span></div>' +
    '</div>' +

    /* Auto-save indicator */
    '<div class="mm-autosave-indicator" id="mmAutosaveIndicator">' +
      '<span class="mm-autosave-dot"></span>' +
      '<span id="mmAutosaveText">Auto-save ready</span>' +
    '</div>' +

    /* Search panel */
    buildSearchPanel() +

    /* Shortcuts overlay */
    buildShortcutsOverlay(mod);

    var wrapper = document.createElement('div');
    wrapper.innerHTML = html;
    while (wrapper.firstChild) {
      document.body.appendChild(wrapper.firstChild);
    }
  }

  function buildThemeDropdown() {
    var html = '<div class="mm-theme-dropdown" id="mmThemeDropdown">';
    THEMES.forEach(function (t) {
      html += '<button class="mm-theme-item" data-theme="' + t.id + '">' +
        '<span class="mm-theme-swatch" style="background:' + t.color + '"></span>' +
        t.name + '</button>';
    });
    return html + '</div>';
  }

  function buildSearchPanel() {
    return '<div class="mm-search-panel" id="mmSearchPanel">' +
      '<div class="mm-search-header">' +
        '<i class="fa fa-search"></i><span>Search Nodes</span>' +
        '<button class="mm-search-close" id="mmSearchClose">&times;</button>' +
      '</div>' +
      '<div class="mm-search-body">' +
        '<input type="text" class="mm-search-input" id="mmSearchInput" placeholder="Type to search…">' +
        '<div class="mm-search-count" id="mmSearchCount"></div>' +
        '<div class="mm-search-results" id="mmSearchResults"></div>' +
        '<div class="mm-search-nav">' +
          '<button class="mm-search-nav-btn" id="mmSearchPrev"><i class="fa fa-chevron-up"></i> Prev</button>' +
          '<button class="mm-search-nav-btn" id="mmSearchNext">Next <i class="fa fa-chevron-down"></i></button>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  function buildShortcutsOverlay(mod) {
    var groups = [
      { title: 'Node Operations', items: [
        ['Add Child Node', 'Tab'],
        ['Add Sibling Node', 'Enter'],
        ['Edit Node Text', 'F2'],
        ['Delete Node', 'Delete'],
      ]},
      { title: 'Navigation', items: [
        ['Move Selection', '↑ ↓ ← →'],
        ['Center View', mod + '+Enter'],
        ['Expand All', 'Alt+`'],
        ['Collapse to Level 1/2/3', 'Alt+1/2/3'],
      ]},
      { title: 'Editing', items: [
        ['Undo', mod + '+Z'],
        ['Redo', mod + '+Y'],
        ['Copy / Cut / Paste', mod + '+C/X/V'],
        ['Select All', mod + '+A'],
      ]},
      { title: 'View', items: [
        ['Zoom In / Out', mod + '+= / ' + mod + '+-'],
        ['Search Nodes', mod + '+F'],
        ['Fullscreen', 'F11'],
        ['Save', mod + '+S'],
      ]}
    ];

    var html = '<div class="mm-shortcuts-overlay" id="mmShortcutsOverlay">' +
      '<div class="mm-shortcuts-modal">' +
        '<div class="mm-shortcuts-header">' +
          '<h3><i class="fa fa-keyboard-o"></i> Keyboard Shortcuts</h3>' +
          '<button class="mm-shortcuts-close" id="mmShortcutsClose">&times;</button>' +
        '</div>' +
        '<div class="mm-shortcuts-body">';

    groups.forEach(function (g) {
      html += '<div class="mm-shortcuts-group"><div class="mm-shortcuts-group-title">' + g.title + '</div>';
      g.items.forEach(function (item) {
        html += '<div class="mm-shortcut-row"><span class="mm-shortcut-label">' + item[0] + '</span>' +
          '<span class="mm-shortcut-keys">';
        item[1].split('/').forEach(function (k, i) {
          if (i > 0) html += '<span style="color:#999;font-size:11px">/</span>';
          html += '<kbd>' + k.trim() + '</kbd>';
        });
        html += '</span></div>';
      });
      html += '</div>';
    });

    html += '</div></div></div>';
    return html;
  }

  /* ------------------------------------------------------------------ */
  /*  Wire Events                                                        */
  /* ------------------------------------------------------------------ */
  function initToolbarEvents() {
    // Prevent KityMinder from swallowing events on our panels
    ['mmToolbar', 'mmSearchPanel', 'mmShortcutsOverlay'].forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      ['keydown', 'keyup', 'keypress', 'paste', 'copy', 'cut'].forEach(function (evt) {
        el.addEventListener(evt, function (e) { e.stopPropagation(); }, false);
      });
    });

    // ─── Undo / Redo ───
    $('#mmTbUndo').on('click', function () { try { km.execCommand('undo'); } catch(e){} });
    $('#mmTbRedo').on('click', function () { try { km.execCommand('redo'); } catch(e){} });

    // ─── Node Operations ───
    $('#mmTbAddChild').on('click', function () {
      try { km.execCommand('AppendChildNode', 'New Topic'); } catch(e){}
    });
    $('#mmTbAddSibling').on('click', function () {
      try { km.execCommand('AppendSiblingNode', 'New Topic'); } catch(e){}
    });
    $('#mmTbDelete').on('click', function () {
      try { km.execCommand('RemoveNode'); } catch(e){}
    });

    // ─── Expand / Collapse ───
    $('#mmTbExpand').on('click', function () {
      try { km.execCommand('expandtolevel', 9999); } catch(e){}
    });
    $('#mmTbCollapse').on('click', function () {
      try { km.execCommand('expandtolevel', 1); } catch(e){}
    });

    // ─── Zoom ───
    $('#mmTbZoomIn').on('click', function () {
      try { km.execCommand('zoom-in'); updateZoomLabel(); } catch(e){}
    });
    $('#mmTbZoomOut').on('click', function () {
      try { km.execCommand('zoom-out'); updateZoomLabel(); } catch(e){}
    });
    $('#mmTbZoomFit').on('click', function () {
      try {
        km.execCommand('camera', km.getRoot(), 600);
        km.execCommand('zoom', 100);
        updateZoomLabel();
      } catch(e){}
    });

    // ─── Fullscreen ───
    $('#mmTbFullscreen').on('click', toggleFullscreen);

    // ─── Search ───
    $('#mmTbSearch').on('click', function () {
      var panel = document.getElementById('mmSearchPanel');
      panel.classList.toggle('open');
      if (panel.classList.contains('open')) {
        setTimeout(function () { document.getElementById('mmSearchInput').focus(); }, 200);
      }
    });
    $('#mmSearchClose').on('click', function () {
      document.getElementById('mmSearchPanel').classList.remove('open');
      clearSearchHighlights();
    });
    $('#mmSearchInput').on('input', debounce(doSearch, 250));
    $('#mmSearchPrev').on('click', function () { navigateSearch(-1); });
    $('#mmSearchNext').on('click', function () { navigateSearch(1); });

    // ─── Export ───
    $('#mmTbExport').on('click', function (e) {
      e.stopPropagation();
      document.getElementById('mmExportDropdown').classList.toggle('open');
      document.getElementById('mmThemeDropdown').classList.remove('open');
    });
    $(document).on('click', '.mm-export-item', function () {
      var fmt = $(this).data('export');
      doExport(fmt);
      document.getElementById('mmExportDropdown').classList.remove('open');
    });

    // ─── Theme ───
    $('#mmTbTheme').on('click', function (e) {
      e.stopPropagation();
      document.getElementById('mmThemeDropdown').classList.toggle('open');
      document.getElementById('mmExportDropdown').classList.remove('open');
      updateThemeActive();
    });
    $(document).on('click', '.mm-theme-item', function () {
      var themeId = $(this).data('theme');
      try { km.execCommand('theme', themeId); km.refresh(); } catch(e){}
      updateThemeActive();
      document.getElementById('mmThemeDropdown').classList.remove('open');
    });

    // ─── Shortcuts Help ───
    $('#mmTbHelp').on('click', function () {
      document.getElementById('mmShortcutsOverlay').classList.add('open');
    });
    $('#mmShortcutsClose').on('click', function () {
      document.getElementById('mmShortcutsOverlay').classList.remove('open');
    });
    $('#mmShortcutsOverlay').on('click', function (e) {
      if (e.target === this) this.classList.remove('open');
    });

    // Close dropdowns on outside click
    $(document).on('click', function (e) {
      if (!$(e.target).closest('#mmTbExport, #mmExportDropdown').length) {
        document.getElementById('mmExportDropdown').classList.remove('open');
      }
      if (!$(e.target).closest('#mmTbTheme, #mmThemeDropdown').length) {
        document.getElementById('mmThemeDropdown').classList.remove('open');
      }
    });

    // ─── Global Keyboard Shortcuts ───
    $(document).on('keydown', function (e) {
      // Ctrl/Cmd + F → Search
      if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        var overlay = document.getElementById('mmImportOverlay');
        if (overlay && overlay.classList.contains('active')) return;
        e.preventDefault();
        var panel = document.getElementById('mmSearchPanel');
        panel.classList.add('open');
        setTimeout(function () { document.getElementById('mmSearchInput').focus(); }, 200);
      }
      // Escape → close search/shortcuts
      if (e.key === 'Escape') {
        document.getElementById('mmSearchPanel').classList.remove('open');
        document.getElementById('mmShortcutsOverlay').classList.remove('open');
        clearSearchHighlights();
      }
      // Ctrl/Cmd + S → Manual save
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        triggerManualSave();
      }
      // F11 → Fullscreen
      if (e.key === 'F11') {
        e.preventDefault();
        toggleFullscreen();
      }
    });

    // ─── Periodic stats update ───
    setInterval(updateStats, 2000);
    updateStats();

    // ─── Auto-save ───
    startAutosave();
  }

  /* ------------------------------------------------------------------ */
  /*  Zoom                                                               */
  /* ------------------------------------------------------------------ */
  function updateZoomLabel() {
    setTimeout(function () {
      try {
        var zoomVal = km.getZoomValue ? km.getZoomValue() : 100;
        currentZoom = zoomVal;
      } catch (e) { }
      document.getElementById('mmTbZoomLabel').textContent = currentZoom + '%';
    }, 100);
  }

  /* ------------------------------------------------------------------ */
  /*  Stats                                                              */
  /* ------------------------------------------------------------------ */
  function updateStats() {
    try {
      var json = km.exportJson();
      var nodeCount = countNodes(json);
      var depth = getMaxDepth(json, 0);
      document.getElementById('mmStatNodes').textContent = nodeCount;
      document.getElementById('mmStatDepth').textContent = depth;
    } catch (e) {}
  }

  function countNodes(node) {
    if (!node) return 0;
    var c = 1;
    if (node.children) node.children.forEach(function (ch) { c += countNodes(ch); });
    return c;
  }

  function getMaxDepth(node, d) {
    if (!node) return d;
    var max = d;
    if (node.children) {
      node.children.forEach(function (ch) {
        var cd = getMaxDepth(ch, d + 1);
        if (cd > max) max = cd;
      });
    }
    return max;
  }

  /* ------------------------------------------------------------------ */
  /*  Search                                                             */
  /* ------------------------------------------------------------------ */
  function doSearch() {
    var query = document.getElementById('mmSearchInput').value.trim().toLowerCase();
    var resultsContainer = document.getElementById('mmSearchResults');
    var countEl = document.getElementById('mmSearchCount');
    clearSearchHighlights();
    searchResults = [];
    searchIdx = -1;

    if (!query) {
      resultsContainer.innerHTML = '';
      countEl.textContent = '';
      return;
    }

    try {
      var allNodes = [];
      collectNodes(km.getRoot(), allNodes);
      allNodes.forEach(function (n) {
        var text = n.getData('text') || '';
        if (text.toLowerCase().indexOf(query) > -1) {
          searchResults.push(n);
        }
      });
    } catch (e) {}

    countEl.textContent = searchResults.length + ' result' + (searchResults.length !== 1 ? 's' : '') + ' found';
    var html = '';
    searchResults.forEach(function (n, i) {
      var t = n.getData('text') || '';
      html += '<div class="mm-search-result-item" data-search-idx="' + i + '"><i class="fa fa-circle"></i> ' + escapeHtml(t) + '</div>';
    });
    resultsContainer.innerHTML = html;

    // Click to navigate
    $(resultsContainer).find('.mm-search-result-item').on('click', function () {
      var idx = parseInt($(this).data('search-idx'));
      navigateToResult(idx);
    });

    if (searchResults.length > 0) navigateToResult(0);
  }

  function navigateSearch(dir) {
    if (!searchResults.length) return;
    searchIdx = (searchIdx + dir + searchResults.length) % searchResults.length;
    navigateToResult(searchIdx);
  }

  function navigateToResult(idx) {
    searchIdx = idx;
    var node = searchResults[idx];
    if (!node) return;
    try {
      km.select(node, true);
      km.execCommand('camera', node, 600);
    } catch (e) {}
    // Highlight active in list
    $('.mm-search-result-item').removeClass('active');
    $('.mm-search-result-item[data-search-idx="' + idx + '"]').addClass('active');
  }

  function collectNodes(node, arr) {
    if (!node) return;
    arr.push(node);
    var children = node.getChildren ? node.getChildren() : [];
    children.forEach(function (ch) { collectNodes(ch, arr); });
  }

  function clearSearchHighlights() { /* future: remove visual highlights */ }

  /* ------------------------------------------------------------------ */
  /*  Export                                                              */
  /* ------------------------------------------------------------------ */
  function doExport(format) {
    try {
      switch (format) {
        case 'png':
          km.exportData('png').then(function (dataUrl) {
            downloadDataUrl(dataUrl, 'mindmap.png');
          });
          break;
        case 'json':
          var jsonStr = JSON.stringify(km.exportJson(), null, 2);
          downloadText(jsonStr, 'mindmap.json', 'application/json');
          break;
        case 'markdown':
          var md = exportToMarkdown(km.exportJson(), 0);
          downloadText(md, 'mindmap.md', 'text/markdown');
          break;
        case 'text':
          var txt = exportToBullet(km.exportJson(), 0);
          downloadText(txt, 'mindmap.txt', 'text/plain');
          break;
      }
      showAutosaveMessage('Exported as ' + format.toUpperCase(), 'saved');
    } catch (e) {
      showAutosaveMessage('Export failed', 'error');
    }
  }

  function exportToMarkdown(node, depth) {
    if (!node) return '';
    var prefix = '';
    for (var i = 0; i <= depth && i < 6; i++) prefix += '#';
    var line = prefix + ' ' + (node.data && node.data.text ? node.data.text : '') + '\n\n';
    if (node.children) {
      node.children.forEach(function (ch) { line += exportToMarkdown(ch, depth + 1); });
    }
    return line;
  }

  function exportToBullet(node, depth) {
    var indent = '';
    for (var i = 0; i < depth; i++) indent += '  ';
    var line = indent + '- ' + (node.data && node.data.text ? node.data.text : '') + '\n';
    if (node.children) {
      node.children.forEach(function (ch) { line += exportToBullet(ch, depth + 1); });
    }
    return line;
  }

  function downloadDataUrl(dataUrl, filename) {
    var a = document.createElement('a');
    a.href = dataUrl;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  function downloadText(content, filename, mimeType) {
    var blob = new Blob([content], { type: mimeType });
    var url = URL.createObjectURL(blob);
    downloadDataUrl(url, filename);
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  }

  /* ------------------------------------------------------------------ */
  /*  Theme                                                              */
  /* ------------------------------------------------------------------ */
  function updateThemeActive() {
    try {
      var current = km.getTheme ? km.getTheme() : '';
      $('.mm-theme-item').removeClass('active');
      $('.mm-theme-item[data-theme="' + current + '"]').addClass('active');
    } catch (e) {}
  }

  /* ------------------------------------------------------------------ */
  /*  Fullscreen                                                         */
  /* ------------------------------------------------------------------ */
  function toggleFullscreen() {
    if (!document.fullscreenElement && !document.webkitFullscreenElement) {
      var el = document.documentElement;
      if (el.requestFullscreen) el.requestFullscreen();
      else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
      $('#mmTbFullscreen').addClass('active');
    } else {
      if (document.exitFullscreen) document.exitFullscreen();
      else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
      $('#mmTbFullscreen').removeClass('active');
    }
  }

  /* ------------------------------------------------------------------ */
  /*  Auto-save                                                          */
  /* ------------------------------------------------------------------ */
  function startAutosave() {
    // Clear any existing timer
    if (autosaveTimer) clearInterval(autosaveTimer);
    autosaveTimer = setInterval(function () {
      doAutosave();
    }, AUTOSAVE_INTERVAL);
  }

  function doAutosave() {
    showAutosaveMessage('Saving…', 'saving');
    try {
      km.exportData('png').then(function (thumb) {
        var reqData = {
          csrf_token_name: APP_CSRF_TOKEN,
          csrf_token: APP_CSRF_TOKEN,
          article_id: ARTICLE_ID,
          mindmap_content: JSON.stringify(km.exportJson()),
          mindmap_thumb: thumb.replace('data:image/png;base64,', '')
        };
        $.ajax({
          url: MINDMAP_SAVE_URL,
          type: 'POST',
          data: reqData,
          dataType: 'json',
          success: function () { showAutosaveMessage('Auto-saved', 'saved'); },
          error: function () { showAutosaveMessage('Save failed', 'error'); }
        });
      });
    } catch (e) {
      showAutosaveMessage('Save error', 'error');
    }
  }

  function triggerManualSave() {
    showAutosaveMessage('Saving…', 'saving');
    $('#update_button').trigger('click');
    setTimeout(function () { showAutosaveMessage('Saved', 'saved'); }, 1500);
  }

  function showAutosaveMessage(msg, type) {
    var el = document.getElementById('mmAutosaveIndicator');
    var text = document.getElementById('mmAutosaveText');
    el.className = 'mm-autosave-indicator visible ' + (type || '');
    text.textContent = msg;
    if (type !== 'saving') {
      setTimeout(function () { el.classList.remove('visible'); }, 3000);
    }
  }

  /* ------------------------------------------------------------------ */
  /*  Utilities                                                          */
  /* ------------------------------------------------------------------ */
  function debounce(fn, delay) {
    var timer;
    return function () {
      var ctx = this, args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
    };
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  /* ------------------------------------------------------------------ */
  /*  Bootstrap                                                          */
  /* ------------------------------------------------------------------ */
  $(function () {
    // Wait a bit for KityMinder to initialize
    setTimeout(function () {
      buildToolbar();
      initToolbarEvents();
      updateZoomLabel();
      updateThemeActive();
    }, 500);
  });
})();
