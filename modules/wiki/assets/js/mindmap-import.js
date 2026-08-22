/**
 * Mind Map – Text Import Module
 * ==============================
 * Allows users to quickly create mind-map nodes by pasting structured text.
 *
 * Supported formats:
 *   1. Tab-indented (plain text outline)
 *   2. Dash / bullet-indented
 *   3. Numbered list (1. 1.1 1.1.1 …)
 *   4. Markdown headings (# ## ### …)
 *   5. Figma / clipboard paste (auto-detect)
 */
(function () {
  'use strict';

  /* ------------------------------------------------------------------ */
  /*  Utility: detect format                                            */
  /* ------------------------------------------------------------------ */
  function detectFormat(text) {
    if (!text || !text.trim()) return null;
    var lines = text.split(/\r?\n/).filter(function (l) { return l.trim(); });
    if (!lines.length) return null;

    // Markdown: majority of lines start with #
    var mdCount = 0;
    lines.forEach(function (l) { if (/^\s*#{1,6}\s+/.test(l)) mdCount++; });
    if (mdCount / lines.length > 0.4) return 'markdown';

    // Numbered outline: lines like "1.", "1.1", "1.1.1"
    var numCount = 0;
    lines.forEach(function (l) { if (/^\s*\d+(\.\d+)*\.?\s+/.test(l)) numCount++; });
    if (numCount / lines.length > 0.5) return 'numbered';

    // Bullet / dash indent: lines starting with -, *, •
    var bulletCount = 0;
    lines.forEach(function (l) { if (/^\s*[-*•]\s+/.test(l)) bulletCount++; });
    if (bulletCount / lines.length > 0.4) return 'bullet';

    // Default: tab/space indented plain text
    return 'plain';
  }

  /* ------------------------------------------------------------------ */
  /*  Parsers                                                           */
  /* ------------------------------------------------------------------ */

  /**
   * Parse tab/space-indented text → KityMinder JSON
   * Uses the built-in KityMinder "plain" protocol under the hood.
   */
  function parsePlainText(text) {
    // Normalise: if spaces used instead of tabs, convert the smallest indent
    var lines = text.split(/\r?\n/);
    var minSpaces = Infinity;
    lines.forEach(function (l) {
      if (!l.trim()) return;
      var m = l.match(/^( +)/);
      if (m && m[1].length < minSpaces) minSpaces = m[1].length;
    });
    if (minSpaces !== Infinity && minSpaces > 0) {
      // Replace every minSpaces spaces with a tab
      var re = new RegExp('^( {' + minSpaces + '})+', 'gm');
      text = text.replace(re, function (match) {
        return '\t'.repeat(match.length / minSpaces);
      });
    }
    // Use KityMinder plain protocol
    try {
      var proto = km.getProtocol('plain');
      if (proto && proto.decode) {
        return proto.decode(text);
      }
    } catch (e) { /* fall through */ }
    return manualParsePlain(text);
  }

  /** Fallback plain parser if protocol not available */
  function manualParsePlain(text) {
    var lines = text.split(/\r?\n/);
    var root = null;
    var stack = {};

    lines.forEach(function (line) {
      if (!line.trim()) return;
      var depth = 0;
      while (line.charAt(depth) === '\t') depth++;
      var node = { data: { text: line.trim() } };

      if (depth === 0) {
        if (root) {
          // Multiple roots: wrap
          if (!root._multi) {
            root = { data: { text: 'Mind Map' }, children: [root], _multi: true };
          }
          root.children.push(node);
        } else {
          root = node;
        }
      } else {
        var parent = stack[depth - 1];
        if (parent) {
          if (!parent.children) parent.children = [];
          parent.children.push(node);
        }
      }
      stack[depth] = node;
    });

    return root || { data: { text: 'Mind Map' } };
  }

  /**
   * Parse bullet / dash-indented text
   * - Root topic
   *   - Child
   *     - Grandchild
   */
  function parseBulletText(text) {
    var lines = text.split(/\r?\n/);
    var root = null;
    var stack = {};

    // Find smallest indent unit
    var minIndent = Infinity;
    lines.forEach(function (l) {
      if (!l.trim()) return;
      var m = l.match(/^(\s*)[-*•]\s+/);
      if (m && m[1].length > 0 && m[1].length < minIndent) minIndent = m[1].length;
    });
    if (minIndent === Infinity) minIndent = 2;

    lines.forEach(function (line) {
      if (!line.trim()) return;
      var m = line.match(/^(\s*)[-*•]\s+(.*)/);
      var depth, label;
      if (m) {
        depth = m[1].length > 0 ? Math.round(m[1].length / minIndent) : 0;
        label = m[2].trim();
      } else {
        // Non-bullet line: treat as depth 0
        depth = 0;
        label = line.trim();
      }

      var node = { data: { text: label } };
      if (depth === 0) {
        if (root) {
          if (!root._multi) {
            root = { data: { text: 'Mind Map' }, children: [root], _multi: true };
          }
          root.children.push(node);
        } else {
          root = node;
        }
      } else {
        var parent = stack[depth - 1];
        if (parent) {
          if (!parent.children) parent.children = [];
          parent.children.push(node);
        }
      }
      stack[depth] = node;
    });

    return root || { data: { text: 'Mind Map' } };
  }

  /**
   * Parse numbered outline
   * 1. Root
   * 1.1 Child
   * 1.1.1 Grandchild
   * 2. Second Root
   */
  function parseNumberedText(text) {
    var lines = text.split(/\r?\n/);
    var root = { data: { text: 'Mind Map' }, children: [] };
    var stack = { 0: root };

    lines.forEach(function (line) {
      if (!line.trim()) return;
      var m = line.match(/^\s*(\d+(?:\.\d+)*)\.?\s+(.*)/);
      if (!m) return;
      var parts = m[1].split('.');
      var depth = parts.length;
      var label = m[2].trim();
      var node = { data: { text: label } };

      var parent = stack[depth - 1] || root;
      if (!parent.children) parent.children = [];
      parent.children.push(node);
      stack[depth] = node;
    });

    // If only one child, promote it
    if (root.children.length === 1) {
      var only = root.children[0];
      only.children = only.children || [];
      return only;
    }
    return root;
  }

  /**
   * Parse Markdown headings
   */
  function parseMarkdownText(text) {
    try {
      var proto = km.getProtocol('markdown');
      if (proto && proto.decode) {
        return proto.decode(text);
      }
    } catch (e) { /* fall through */ }

    // Fallback manual parser
    var lines = text.split(/\r?\n/);
    var root = null;
    var stack = {};

    lines.forEach(function (line) {
      if (!line.trim()) return;
      var m = line.match(/^(#{1,6})\s+(.*)/);
      if (!m) return;
      var depth = m[1].length - 1; // h1=0, h2=1 …
      var label = m[2].trim();
      var node = { data: { text: label } };

      if (depth === 0) {
        if (root) {
          if (!root._multi) {
            root = { data: { text: 'Mind Map' }, children: [root], _multi: true };
          }
          root.children.push(node);
        } else {
          root = node;
        }
      } else {
        var parent = stack[depth - 1];
        if (parent) {
          if (!parent.children) parent.children = [];
          parent.children.push(node);
        }
      }
      stack[depth] = node;
    });

    return root || { data: { text: 'Mind Map' } };
  }

  /**
   * Master parse: auto-detect + dispatch
   */
  function parseText(text, forceFormat) {
    var fmt = forceFormat || detectFormat(text);
    var json;
    switch (fmt) {
      case 'markdown':  json = parseMarkdownText(text); break;
      case 'bullet':    json = parseBulletText(text); break;
      case 'numbered':  json = parseNumberedText(text); break;
      case 'plain':
      default:          json = parsePlainText(text); break;
    }
    // Clean internal flags
    cleanNode(json);
    // Add template/theme
    json.template = json.template || 'default';
    json.theme = json.theme || 'fresh-blue';
    json.version = '1.3.5';
    return { json: json, format: fmt };
  }

  function cleanNode(node) {
    if (!node) return;
    delete node._multi;
    if (node.children) {
      node.children.forEach(cleanNode);
    }
  }

  function countNodes(node) {
    if (!node) return 0;
    var c = 1;
    if (node.children) {
      node.children.forEach(function (ch) { c += countNodes(ch); });
    }
    return c;
  }

  /* ------------------------------------------------------------------ */
  /*  Export: KityMinder JSON → Bullet/Dash text                        */
  /* ------------------------------------------------------------------ */
  function exportToBullet(node, depth) {
    depth = depth || 0;
    var indent = '';
    for (var i = 0; i < depth; i++) indent += '  ';
    var line = indent + '- ' + (node.data && node.data.text ? node.data.text : '') + '\n';
    if (node.children) {
      node.children.forEach(function (ch) {
        line += exportToBullet(ch, depth + 1);
      });
    }
    return line;
  }

  function exportMinderToBullet() {
    try {
      var json = km.exportJson();
      return exportToBullet(json, 0);
    } catch (e) {
      return '- Mind Map';
    }
  }

  /* ------------------------------------------------------------------
  /*  UI: Build the import modal                                        */
  /* ------------------------------------------------------------------ */
  function buildModal() {
    var html = '' +
    '<div id="mmImportOverlay" class="mm-import-overlay">' +
      '<div class="mm-import-modal">' +
        /* Header */
        '<div class="mm-import-header">' +
          '<div class="mm-import-header-left">' +
            '<div class="mm-import-header-icon"><i class="fa fa-paste"></i></div>' +
            '<div>' +
              '<h3>Import Text to Mind Map</h3>' +
              '<p>Paste structured text and instantly generate nodes</p>' +
            '</div>' +
          '</div>' +
          '<button class="mm-import-close" id="mmImportClose" title="Close">&times;</button>' +
        '</div>' +
        /* Tabs */
        '<div class="mm-import-tabs">' +
          '<button class="mm-import-tab active" data-tab="paste"><i class="fa fa-keyboard-o"></i>&nbsp; Paste Text</button>' +
          '<button class="mm-import-tab" data-tab="edittext"><i class="fa fa-pencil-square-o"></i>&nbsp; Edit as Text</button>' +
          '<button class="mm-import-tab" data-tab="figma"><i class="fa fa-clipboard"></i>&nbsp; Quick Paste</button>' +
          '<button class="mm-import-tab" data-tab="guide"><i class="fa fa-book"></i>&nbsp; Format Guide</button>' +
        '</div>' +
        /* Body */
        '<div class="mm-import-body">' +
          /* — Paste tab — */
          '<div class="mm-import-panel active" data-panel="paste">' +
            '<textarea class="mm-import-textarea" id="mmImportText" placeholder="Paste your structured text here…&#10;&#10;Examples:&#10;Tab-indented, bullet lists (- or *), numbered (1. 1.1), or markdown (# ## ###)"></textarea>' +
            '<div class="mm-import-format-hint">' +
              '<i class="fa fa-magic"></i>' +
              '<span><strong>Auto-detect</strong> — format is detected automatically. Supports <code>Tab-indented</code>, <code>- Bullet</code>, <code>1. Numbered</code>, and <code># Markdown</code> formats.</span>' +
            '</div>' +
            '<div class="mm-import-option-row">' +
              '<label><input type="checkbox" id="mmImportReplace" checked> Replace entire mind map (uncheck to merge into selected node)</label>' +
            '</div>' +
            '<div class="mm-import-status" id="mmImportStatus"></div>' +
          '</div>' +
          /* — Figma / Quick Paste tab — */
          '<div class="mm-import-panel" data-panel="figma">' +
            '<div class="mm-import-paste-zone" id="mmPasteZone" tabindex="0">' +
              '<div class="mm-import-paste-zone-icon"><i class="fa fa-clipboard"></i></div>' +
              '<div class="mm-import-paste-zone-text">Click here, then press <kbd style="padding:2px 8px;background:#e5e7eb;border-radius:4px;font-size:12px;">Ctrl+V</kbd> / <kbd style="padding:2px 8px;background:#e5e7eb;border-radius:4px;font-size:12px;">⌘V</kbd></div>' +
              '<div class="mm-import-paste-zone-sub">Paste from Figma, Notion, Google Docs, or any app</div>' +
            '</div>' +
            '<div class="mm-import-paste-zone-preview" id="mmPastePreview"></div>' +
            '<div class="mm-import-paste-zone-badge" id="mmPasteBadge" style="display:none"><i class="fa fa-check-circle"></i> <span></span></div>' +
            '<div class="mm-import-option-row">' +
              '<label><input type="checkbox" id="mmPasteReplace" checked> Replace entire mind map</label>' +
            '</div>' +
            '<div class="mm-import-status" id="mmPasteStatus"></div>' +
          '</div>' +
          /* — Edit as Text tab — */
          '<div class="mm-import-panel" data-panel="edittext">' +
            '<div class="mm-import-format-hint" style="margin-bottom:6px">' +
              '<i class="fa fa-info-circle"></i>' +
              '<span><strong>Edit as Text</strong> — Your current mind map is shown below as a bullet list. Edit it freely, then click <strong>Import to Mind Map</strong> to apply.</span>' +
            '</div>' +
            '<textarea class="mm-import-textarea" id="mmEditText" placeholder="Loading current mind map…"></textarea>' +
            '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
              '<button class="mm-import-btn mm-import-btn-import" id="mmEditRefresh" style="background:linear-gradient(135deg,#0ea5e9,#38bdf8);box-shadow:0 2px 8px rgba(14,165,233,.3)"><i class="fa fa-refresh"></i> Reload from Map</button>' +
            '</div>' +
            '<div class="mm-import-status" id="mmEditStatus"></div>' +
          '</div>' +
          /* — Guide tab — */
          '<div class="mm-import-panel" data-panel="guide">' +
            '<div class="mm-import-guide">' +
              /* Format 1 */
              '<div class="mm-import-guide-section">' +
                '<h4><i class="fa fa-indent"></i> Tab-Indented Text <span class="guide-badge recommended">Recommended</span></h4>' +
                '<div class="mm-import-guide-desc">Use <strong>Tab</strong> key or spaces to define hierarchy. The first un-indented line becomes the root.</div>' +
                '<div class="mm-guide-pre-wrap"><pre class="mm-guide-copyable">Human Body\n\tHead\n\t\tBrain\n\t\t\tCerebrum\n\t\t\tCerebellum\n\t\tEyes\n\t\tEars\n\tTorso\n\t\tHeart\n\t\tLungs\n\tLimbs\n\t\tArms\n\t\tLegs</pre><button class="mm-guide-copy-btn" title="Copy example"><i class="fa fa-copy"></i> Copy</button></div>' +
              '</div>' +
              /* Format 2 */
              '<div class="mm-import-guide-section">' +
                '<h4><i class="fa fa-list-ul"></i> Bullet / Dash Lists <span class="guide-badge advanced">Easy</span></h4>' +
                '<div class="mm-import-guide-desc">Use <code>-</code>, <code>*</code>, or <code>•</code> with indentation for hierarchy.</div>' +
                '<div class="mm-guide-pre-wrap"><pre class="mm-guide-copyable">- Diseases\n  - Infectious\n    - Bacterial\n    - Viral\n  - Non-Infectious\n    - Genetic\n    - Autoimmune</pre><button class="mm-guide-copy-btn" title="Copy example"><i class="fa fa-copy"></i> Copy</button></div>' +
              '</div>' +
              /* Format 3 */
              '<div class="mm-import-guide-section">' +
                '<h4><i class="fa fa-sort-numeric-asc"></i> Numbered Outline <span class="guide-badge advanced">Structured</span></h4>' +
                '<div class="mm-import-guide-desc">Use <code>1.</code>, <code>1.1</code>, <code>1.1.1</code> dot-notation for depth.</div>' +
                '<div class="mm-guide-pre-wrap"><pre class="mm-guide-copyable">1. Pharmacology\n1.1 Pharmacokinetics\n1.1.1 Absorption\n1.1.2 Distribution\n1.2 Pharmacodynamics\n2. Pathology\n2.1 Cell Injury\n2.2 Inflammation</pre><button class="mm-guide-copy-btn" title="Copy example"><i class="fa fa-copy"></i> Copy</button></div>' +
              '</div>' +
              /* Format 4 */
              '<div class="mm-import-guide-section">' +
                '<h4><i class="fa fa-hashtag"></i> Markdown Headings <span class="guide-badge pro">Pro</span></h4>' +
                '<div class="mm-import-guide-desc">Use <code>#</code> to <code>######</code> (1-6 levels). Ideal for copy-pasting from docs.</div>' +
                '<div class="mm-guide-pre-wrap"><pre class="mm-guide-copyable"># Medical Science\n## Anatomy\n### Gross Anatomy\n### Histology\n## Physiology\n### Cellular\n### Systemic</pre><button class="mm-guide-copy-btn" title="Copy example"><i class="fa fa-copy"></i> Copy</button></div>' +
              '</div>' +
              /* Quick paste guide */
              '<div class="mm-import-guide-section">' +
                '<h4><i class="fa fa-clipboard"></i> Quick Paste (Figma / Apps) <span class="guide-badge pro">Quick</span></h4>' +
                '<div class="mm-import-guide-desc">Copy any structured text from <strong>Figma</strong>, <strong>Notion</strong>, <strong>Google Docs</strong>, or any text editor. Click the "Quick Paste" tab, click the paste zone, and press <strong>Ctrl+V</strong> (or ⌘V on Mac). The format is auto-detected.</div>' +
              '</div>' +
            '</div>' +
          '</div>' +
        '</div>' +
        /* Footer */
        '<div class="mm-import-footer">' +
          '<button class="mm-import-btn mm-import-btn-cancel" id="mmImportCancel"><i class="fa fa-times"></i> Cancel</button>' +
          '<button class="mm-import-btn mm-import-btn-import" id="mmImportApply"><i class="fa fa-check"></i> Import to Mind Map</button>' +
        '</div>' +
      '</div>' +
    '</div>';

    var wrapper = document.createElement('div');
    wrapper.innerHTML = html;
    document.body.appendChild(wrapper.firstChild);
  }

  /* ------------------------------------------------------------------ */
  /*  UI: Wire events                                                   */
  /* ------------------------------------------------------------------ */
  function initEvents() {
    var overlay = document.getElementById('mmImportOverlay');
    var btnClose = document.getElementById('mmImportClose');
    var btnCancel = document.getElementById('mmImportCancel');
    var btnApply = document.getElementById('mmImportApply');
    var textarea = document.getElementById('mmImportText');
    var tabs = overlay.querySelectorAll('.mm-import-tab');
    var panels = overlay.querySelectorAll('.mm-import-panel');

    // ─── CRITICAL: Stop KityMinder from intercepting keyboard events ───
    // KityMinder binds keydown/keyup/keypress/paste on document.body,
    // which prevents typing and pasting inside our modal. We stop
    // propagation so those events never reach KityMinder's handlers.
    ['keydown', 'keyup', 'keypress', 'paste', 'copy', 'cut'].forEach(function (evt) {
      overlay.addEventListener(evt, function (e) {
        e.stopPropagation();
      }, false);
    });

    // Paste zone elements
    var pasteZone = document.getElementById('mmPasteZone');
    var pastePreview = document.getElementById('mmPastePreview');
    var pasteBadge = document.getElementById('mmPasteBadge');
    var pasteStatus = document.getElementById('mmPasteStatus');
    var pastedText = '';

    // Open / close
    function open() {
      overlay.classList.add('active');
      setTimeout(function() { textarea.focus(); }, 200);
    }
    function close() {
      overlay.classList.remove('active');
    }

    window._mmImportOpen = open;

    btnClose.addEventListener('click', close);
    btnCancel.addEventListener('click', close);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) close();
    });

    // Escape key
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay.classList.contains('active')) close();
    });

    // Edit-as-text elements
    var editTextarea = document.getElementById('mmEditText');
    var editRefreshBtn = document.getElementById('mmEditRefresh');
    var editStatus = document.getElementById('mmEditStatus');

    // Tabs
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) { t.classList.remove('active'); });
        panels.forEach(function (p) { p.classList.remove('active'); });
        tab.classList.add('active');
        var target = tab.getAttribute('data-tab');
        overlay.querySelector('[data-panel="' + target + '"]').classList.add('active');
        // Auto-load mindmap text when Edit-as-Text tab is opened
        if (target === 'edittext') {
          editTextarea.value = exportMinderToBullet();
        }
      });
    });

    // Edit-as-Text: Reload button
    editRefreshBtn.addEventListener('click', function () {
      editTextarea.value = exportMinderToBullet();
      showStatus(editStatus, 'success', 'Reloaded from current mind map.');
      setTimeout(function () { hideStatus(editStatus); }, 1500);
    });

    // Guide: Copy buttons
    overlay.addEventListener('click', function (e) {
      var btn = e.target.closest('.mm-guide-copy-btn');
      if (!btn) return;
      var pre = btn.parentElement.querySelector('pre');
      if (!pre) return;
      var text = pre.textContent;
      navigator.clipboard.writeText(text).then(function () {
        btn.innerHTML = '<i class="fa fa-check"></i> Copied!';
        btn.classList.add('copied');
        setTimeout(function () {
          btn.innerHTML = '<i class="fa fa-copy"></i> Copy';
          btn.classList.remove('copied');
        }, 1500);
      });
    });

    // Figma / Quick Paste zone
    pasteZone.addEventListener('click', function () {
      pasteZone.focus();
    });
    pasteZone.addEventListener('paste', function (e) {
      e.preventDefault();
      var clipData = e.clipboardData || window.clipboardData;
      var text = clipData.getData('text/plain') || clipData.getData('text') || '';
      if (!text.trim()) {
        showStatus(pasteStatus, 'error', 'No text found in clipboard.');
        return;
      }
      pastedText = text;
      pastePreview.textContent = text;
      pastePreview.classList.add('has-content');
      var fmt = detectFormat(text);
      var fmtLabels = { plain: 'Tab-Indented', bullet: 'Bullet List', numbered: 'Numbered Outline', markdown: 'Markdown' };
      pasteBadge.style.display = 'inline-flex';
      pasteBadge.querySelector('span').textContent = 'Detected: ' + (fmtLabels[fmt] || 'Plain Text');
      showStatus(pasteStatus, 'success', 'Text captured! Click "Import to Mind Map" to apply.');
    });

    // Also allow paste via keyboard when zone is focused
    pasteZone.addEventListener('keydown', function (e) {
      // Only capture Ctrl+V / Cmd+V — the paste event handler above takes care of it
    });

    // Apply import
    btnApply.addEventListener('click', function () {
      // Determine which tab is active
      var activePanel = overlay.querySelector('.mm-import-panel.active');
      var panelName = activePanel ? activePanel.getAttribute('data-panel') : 'paste';
      var text = '';
      var replaceAll = true;
      var statusEl;

      if (panelName === 'paste') {
        text = textarea.value;
        replaceAll = document.getElementById('mmImportReplace').checked;
        statusEl = document.getElementById('mmImportStatus');
      } else if (panelName === 'figma') {
        text = pastedText;
        replaceAll = document.getElementById('mmPasteReplace').checked;
        statusEl = pasteStatus;
      } else if (panelName === 'edittext') {
        text = editTextarea.value;
        replaceAll = true;
        statusEl = editStatus;
      } else {
        // Guide tab — nothing to import
        return;
      }

      if (!text || !text.trim()) {
        showStatus(statusEl, 'error', 'Please paste some text first.');
        return;
      }

      try {
        var result = parseText(text);
        var nodeCount = countNodes(result.json);
        var fmtLabels = { plain: 'Tab-Indented', bullet: 'Bullet List', numbered: 'Numbered Outline', markdown: 'Markdown' };

        if (replaceAll) {
          km.importJson(result.json);
          km.refresh();
        } else {
          // Merge into selected node (or root)
          mergeIntoSelected(result.json);
        }

        showStatus(statusEl, 'success', 'Imported ' + nodeCount + ' nodes (' + (fmtLabels[result.format] || 'auto') + ' format). Mind map updated!');

        // Auto-close after 1.5s
        setTimeout(function () {
          close();
          // Clear
          textarea.value = '';
          pastedText = '';
          pastePreview.textContent = '';
          pastePreview.classList.remove('has-content');
          pasteBadge.style.display = 'none';
          hideStatus(statusEl);
        }, 1500);

      } catch (err) {
        showStatus(statusEl, 'error', 'Parse error: ' + err.message);
      }
    });
  }

  /**
   * Merge parsed JSON into the currently selected node (or root)
   */
  function mergeIntoSelected(json) {
    var selected = km.getSelectedNode() || km.getRoot();
    if (json.children && json.children.length) {
      json.children.forEach(function (child) {
        addNodeRecursive(selected, child);
      });
    } else {
      addNodeRecursive(selected, json);
    }
    km.refresh();
  }

  function addNodeRecursive(parent, nodeData) {
    var newNode = km.createNode(nodeData.data.text, parent);
    // Copy extra data
    if (nodeData.data) {
      for (var key in nodeData.data) {
        if (key !== 'text') newNode.setData(key, nodeData.data[key]);
      }
    }
    if (nodeData.children) {
      nodeData.children.forEach(function (child) {
        addNodeRecursive(newNode, child);
      });
    }
  }

  function showStatus(el, type, msg) {
    if (!el) return;
    el.className = 'mm-import-status ' + type;
    el.innerHTML = '<i class="fa ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + msg;
  }

  function hideStatus(el) {
    if (!el) return;
    el.className = 'mm-import-status';
    el.innerHTML = '';
  }

  /* ------------------------------------------------------------------ */
  /*  Bootstrap                                                         */
  /* ------------------------------------------------------------------ */
  $(function () {
    buildModal();
    initEvents();

    // Bind the trigger button
    $(document).on('click', '#mmImportTrigger', function (e) {
      e.preventDefault();
      if (window._mmImportOpen) window._mmImportOpen();
    });
  });

})();
