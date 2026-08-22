/**
 * Careers — embeddable widget.
 *
 * One <script> tag renders live openings, the job detail and a working apply
 * form (CV upload included) into any page on any website. No dependencies, no
 * build step, no API key: it talks to the CRM's public embed endpoints, which
 * only ever expose published postings.
 *
 * Usage on a host page:
 *   <div data-careers-embed></div>
 *   <script src="https://your-crm/careers/careers_embed/js" async></script>
 *
 * Container options (all optional):
 *   data-department="Engineering"   only that department
 *   data-type="internship"          only that opening type
 *   data-limit="5"                  cap how many are listed
 *   data-layout="grid|list"         card layout (default list)
 *   data-theme="light|dark|auto"    default light
 *   data-accent="#0d9488"           brand colour for buttons and accents
 *   data-filters="0"                hide the search / filter bar
 *   data-job="senior-engineer"      open one posting directly, no list
 *   data-heading="We are hiring"    optional heading above the list
 *
 * Styles are namespaced under .hocx and injected once, so the widget cannot
 * inherit or leak host-page CSS.
 */
(function () {
  'use strict';

  var BASE = '__CAREERS_EMBED_BASE__';
  var STYLE_ID = 'hocx-styles';

  /* ───────────────────────────── helpers ─────────────────────────────── */

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function el(html) {
    var holder = document.createElement('div');
    holder.innerHTML = html.trim();
    return holder.firstChild;
  }

  function get(url) {
    return fetch(url, { credentials: 'omit' }).then(function (r) { return r.json(); });
  }

  function attr(node, name, fallback) {
    var value = node.getAttribute('data-' + name);
    return value === null || value === '' ? fallback : value;
  }

  /* ───────────────────────────── styles ──────────────────────────────── */

  function injectStyles() {
    if (document.getElementById(STYLE_ID)) { return; }

    var css = [
      '.hocx{--hocx-accent:#0d9488;--hocx-ink:#0f172a;--hocx-text:#334155;--hocx-soft:#64748b;--hocx-mute:#94a3b8;',
      '--hocx-line:#e2e8f0;--hocx-bg:#f8fafc;--hocx-surface:#fff;--hocx-radius:14px;',
      'color:var(--hocx-text);font-family:inherit;font-size:15px;line-height:1.55;box-sizing:border-box}',
      '.hocx *,.hocx *::before,.hocx *::after{box-sizing:border-box}',
      '.hocx[data-mode="dark"]{--hocx-ink:#f1f5f9;--hocx-text:#cbd5e1;--hocx-soft:#94a3b8;--hocx-mute:#64748b;',
      '--hocx-line:#1e293b;--hocx-bg:#0f172a;--hocx-surface:#111c31}',

      '.hocx-head{margin:0 0 18px;font-size:1.4rem;font-weight:800;color:var(--hocx-ink)}',
      '.hocx-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}',
      '.hocx-bar input,.hocx-bar select{padding:10px 14px;border:1.5px solid var(--hocx-line);border-radius:10px;',
      'background:var(--hocx-surface);color:var(--hocx-ink);font:inherit;font-size:.92rem;outline:none;min-width:160px}',
      '.hocx-bar input{flex:1;min-width:220px}',
      '.hocx-bar input:focus,.hocx-bar select:focus{border-color:var(--hocx-accent)}',
      '.hocx-count{color:var(--hocx-mute);font-size:.85rem;margin-bottom:14px}',

      // Faceted filter panel
      '.hocx-facets{display:flex;flex-wrap:wrap;gap:16px 28px;margin-bottom:14px;padding:16px 18px;',
      'background:var(--hocx-surface);border:1px solid var(--hocx-line);border-radius:var(--hocx-radius)}',
      '.hocx-facets:empty{display:none}',
      '.hocx-facetlab{font-size:.7rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;',
      'color:var(--hocx-mute);margin-bottom:8px}',
      '.hocx-facetopts{display:flex;flex-wrap:wrap;gap:7px}',
      '.hocx-chip2{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border:1.5px solid var(--hocx-line);',
      'border-radius:999px;background:var(--hocx-surface);color:var(--hocx-soft);font:inherit;font-size:.82rem;',
      'font-weight:600;cursor:pointer;transition:all .16s ease}',
      '.hocx-chip2:hover:not([disabled]){border-color:var(--hocx-accent);color:var(--hocx-ink)}',
      '.hocx-chip2[aria-pressed="true"]{background:var(--hocx-accent);border-color:var(--hocx-accent);color:#fff}',
      '.hocx-chip2[disabled]{opacity:.4;cursor:not-allowed}',
      '.hocx-chipn{font-size:.72rem;font-weight:700;color:var(--hocx-mute)}',
      '.hocx-chip2[aria-pressed="true"] .hocx-chipn{color:rgba(255,255,255,.8)}',

      '.hocx-active{display:flex;flex-wrap:wrap;align-items:center;gap:8px 10px;margin-bottom:14px}',
      '.hocx-active:empty{display:none}',
      '.hocx-activelab{font-size:.72rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:var(--hocx-mute)}',
      '.hocx-pill{display:inline-flex;align-items:center;gap:6px;padding:4px 6px 4px 11px;border-radius:999px;',
      'background:var(--hocx-bg);color:var(--hocx-ink);font-size:.8rem;font-weight:700}',
      '.hocx-pill button{width:17px;height:17px;border:0;border-radius:50%;background:var(--hocx-line);',
      'color:inherit;font-size:.85rem;line-height:1;cursor:pointer}',
      '.hocx-pill button:hover{background:var(--hocx-accent);color:#fff}',
      '.hocx-clear,.hocx-linkbtn{border:0;background:none;font:inherit;font-size:.82rem;font-weight:700;',
      'color:var(--hocx-soft);text-decoration:underline;cursor:pointer}',
      '.hocx-clear:hover,.hocx-linkbtn:hover{color:var(--hocx-accent)}',
      '.hocx-more{text-align:center;margin-top:18px}',
      '.hocx-more:empty{display:none}',
      // On a phone five stacked facets would push the openings off the screen.
      // Each row swipes sideways instead, so the panel stays five short lines.
      '@media(max-width:640px){.hocx-facets{gap:12px;padding:14px}.hocx-facet{width:100%}',
      '.hocx-facetopts{flex-wrap:nowrap;overflow-x:auto;padding-bottom:4px;-webkit-overflow-scrolling:touch}',
      '.hocx-facetopts .hocx-chip2{flex:0 0 auto}}',

      '.hocx-list{display:grid;gap:14px}',
      '.hocx-list[data-layout="grid"]{grid-template-columns:repeat(auto-fill,minmax(290px,1fr))}',
      '.hocx-card{background:var(--hocx-surface);border:1px solid var(--hocx-line);border-radius:var(--hocx-radius);',
      'padding:20px 22px;display:flex;gap:18px;align-items:center;justify-content:space-between;flex-wrap:wrap;',
      'transition:transform .2s,box-shadow .2s,border-color .2s}',
      '.hocx-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(15,23,42,.09);border-color:var(--hocx-accent)}',
      '.hocx-list[data-layout="grid"] .hocx-card{display:block}',
      '.hocx-title{font-size:1.05rem;font-weight:700;color:var(--hocx-ink);margin:0 0 8px;cursor:pointer}',
      '.hocx-title:hover{color:var(--hocx-accent)}',
      '.hocx-meta{display:flex;flex-wrap:wrap;gap:7px;align-items:center}',
      '.hocx-chip{font-size:.75rem;font-weight:600;padding:4px 10px;border-radius:999px;background:var(--hocx-bg);',
      'color:var(--hocx-soft);white-space:nowrap}',
      '.hocx-chip-accent{background:color-mix(in srgb,var(--hocx-accent) 14%,transparent);color:var(--hocx-accent)}',
      '.hocx-chip-new{background:#dcfce7;color:#15803d}',
      '.hocx-chip-urgent{background:#fee2e2;color:#b91c1c}',
      '.hocx-chip-feat{background:#fef3c7;color:#92400e}',
      '.hocx-sum{color:var(--hocx-soft);font-size:.88rem;margin:10px 0 0}',
      '.hocx-side{display:flex;flex-direction:column;gap:8px;align-items:flex-end}',
      '.hocx-list[data-layout="grid"] .hocx-side{align-items:flex-start;margin-top:14px}',
      '.hocx-sal{font-size:.82rem;font-weight:700;color:var(--hocx-accent);white-space:nowrap}',

      '.hocx-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border-radius:10px;border:0;',
      'background:var(--hocx-accent);color:#fff;font:inherit;font-size:.9rem;font-weight:700;cursor:pointer;',
      'text-decoration:none;transition:filter .2s;white-space:nowrap}',
      '.hocx-btn:hover{filter:brightness(.93)}',
      '.hocx-btn[disabled]{opacity:.6;cursor:default}',
      '.hocx-btn-ghost{background:transparent;color:var(--hocx-accent);border:1.5px solid var(--hocx-line)}',
      '.hocx-back{background:none;border:0;color:var(--hocx-soft);font:inherit;font-size:.88rem;font-weight:600;',
      'cursor:pointer;padding:0;margin-bottom:16px}',
      '.hocx-back:hover{color:var(--hocx-accent)}',

      '.hocx-detail{background:var(--hocx-surface);border:1px solid var(--hocx-line);border-radius:var(--hocx-radius);padding:28px}',
      '.hocx-detail h2{font-size:1.5rem;color:var(--hocx-ink);margin:0 0 10px}',
      '.hocx-detail h3{font-size:1.05rem;color:var(--hocx-ink);margin:26px 0 10px}',
      '.hocx-rich{color:var(--hocx-text);font-size:.95rem}',
      '.hocx-rich ul,.hocx-rich ol{padding-left:20px;margin:10px 0;display:grid;gap:6px}',
      '.hocx-rich p{margin:10px 0}',
      '.hocx-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;',
      'background:var(--hocx-bg);border-radius:12px;padding:18px;margin:20px 0}',
      '.hocx-fact-l{font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;color:var(--hocx-mute);font-weight:700}',
      '.hocx-fact-v{font-size:.92rem;color:var(--hocx-ink);font-weight:600;margin-top:2px}',

      '.hocx-form{display:grid;gap:14px;margin-top:10px}',
      '.hocx-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}',
      '@media(max-width:600px){.hocx-row{grid-template-columns:1fr}}',
      '.hocx-field label{display:block;font-size:.8rem;font-weight:700;color:var(--hocx-ink);margin-bottom:5px}',
      '.hocx-field input,.hocx-field select,.hocx-field textarea{width:100%;padding:11px 14px;',
      'border:1.5px solid var(--hocx-line);border-radius:10px;font:inherit;font-size:.92rem;',
      'background:var(--hocx-surface);color:var(--hocx-ink);outline:none}',
      '.hocx-field input:focus,.hocx-field select:focus,.hocx-field textarea:focus{border-color:var(--hocx-accent)}',
      '.hocx-field textarea{min-height:100px;resize:vertical}',
      '.hocx-req{color:#dc2626}',
      '.hocx-choices{display:grid;gap:7px}',
      '.hocx-choices label{display:flex;gap:8px;align-items:center;font-weight:500;font-size:.9rem;color:var(--hocx-text)}',
      '.hocx-choices input{width:auto}',
      '.hocx-file{border:1.5px dashed var(--hocx-line);border-radius:10px;padding:16px;text-align:center;',
      'cursor:pointer;display:block;color:var(--hocx-soft);font-size:.88rem}',
      '.hocx-file:hover{border-color:var(--hocx-accent)}',
      '.hocx-file input{display:none}',
      '.hocx-file b{color:var(--hocx-ink)}',
      '.hocx-note{font-size:.78rem;color:var(--hocx-mute)}',
      '.hocx-msg{border-radius:10px;padding:12px 14px;font-size:.88rem;font-weight:600;display:none}',
      '.hocx-msg.err{display:block;background:#fee2e2;color:#991b1b}',
      '.hocx-msg.ok{display:block;background:#dcfce7;color:#166534}',
      '.hocx-hp{position:absolute;left:-9999px;width:0;height:0;opacity:0}',
      '.hocx-done{text-align:center;padding:34px 18px}',
      '.hocx-done .hocx-tick{width:58px;height:58px;border-radius:50%;background:#dcfce7;color:#15803d;',
      'display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 14px}',
      '.hocx-ref{display:inline-block;background:var(--hocx-bg);border-radius:8px;padding:6px 12px;',
      'font-weight:700;color:var(--hocx-ink);font-family:ui-monospace,SFMono-Regular,Menlo,monospace}',

      '.hocx-state{text-align:center;padding:44px 18px;color:var(--hocx-mute)}',
      '.hocx-spin{width:26px;height:26px;border:3px solid var(--hocx-line);border-top-color:var(--hocx-accent);',
      'border-radius:50%;margin:0 auto 12px;animation:hocx-spin .8s linear infinite}',
      '@keyframes hocx-spin{to{transform:rotate(360deg)}}'
    ].join('');

    var style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = css;
    document.head.appendChild(style);
  }

  /* ───────────────────────────── facets ──────────────────────────────── */

  /**
   * Faceted search, sized for a real careers page rather than a handful of
   * roles: five facets plus free text, values inside one facet OR'd together
   * and separate facets AND'd, exactly as every job board behaves.
   *
   * The facets are derived from the openings themselves, not from the payload's
   * `facets` block — that block carries departments, locations and types only,
   * and a list of 50+ roles needs work mode and experience to be siftable too.
   */
  var PAGE   = 12;   // cards per "show more"; 50+ in one go is an endless scroll
  var FACETS = [
    { key: 'department', label: 'Department', of: function (job) { return job.department || ''; } },
    { key: 'type',       label: 'Job type',   of: function (job) { return job.type || ''; },
      labelOf: function (job) { return job.type_label || job.type; } },
    { key: 'mode',       label: 'Work mode',  of: function (job) { return job.work_mode || ''; },
      labelOf: function (job) { return job.work_mode_label || job.work_mode; } },
    { key: 'location',   label: 'Location',   of: function (job) { return job.location || ''; } },
    { key: 'experience', label: 'Experience', of: function (job) { return expBand(job); },
      labelOf: function (job) { return EXP_LABELS[expBand(job)]; } }
  ];

  // Banded, not exact: a candidate thinks "I have about four years", never
  // "3 – 5 yrs". A posting is placed by its MINIMUM required experience, which
  // is the number that decides whether they are eligible at all.
  var EXP_BANDS  = [['entry', 0, 1], ['junior', 1, 3], ['mid', 3, 5], ['senior', 5, 10], ['lead', 10, Infinity]];
  var EXP_LABELS = {
    entry: 'Entry level (0 – 1 yr)', junior: '1 – 3 yrs', mid: '3 – 5 yrs',
    senior: '5 – 10 yrs', lead: '10+ yrs'
  };
  var EXP_ORDER = ['entry', 'junior', 'mid', 'senior', 'lead'];

  function expBand(job) {
    if (job.experience_min === null || job.experience_min === undefined) { return ''; }

    var min = Number(job.experience_min);

    for (var i = 0; i < EXP_BANDS.length; i++) {
      if (min >= EXP_BANDS[i][1] && min < EXP_BANDS[i][2]) { return EXP_BANDS[i][0]; }
    }

    return 'lead';
  }

  function emptyPicks() {
    var picks = {};
    FACETS.forEach(function (facet) { picks[facet.key] = []; });

    return picks;
  }

  /** Everything a text search should reach, lowercased once per job. */
  function haystack(job) {
    if (job.__hay === undefined) {
      job.__hay = [
        job.title, job.department, job.location, job.type_label, job.work_mode_label,
        (job.skills || []).join(' '), job.summary, job.education, job.experience
      ].join(' ').toLowerCase();
    }

    return job.__hay;
  }

  /* ──────────────────────────── the widget ───────────────────────────── */

  function Widget(root) {
    this.root  = root;
    this.jobs  = [];
    this.state = { search: '', sort: 'relevance', shown: PAGE, picked: emptyPicks() };

    this.config = {
      department: attr(root, 'department', ''),
      type:       attr(root, 'type', ''),
      limit:      attr(root, 'limit', ''),
      layout:     attr(root, 'layout', 'list'),
      theme:      attr(root, 'theme', 'light'),
      accent:     attr(root, 'accent', ''),
      filters:    attr(root, 'filters', '1') !== '0',
      job:        attr(root, 'job', ''),
      heading:    attr(root, 'heading', '')
    };

    root.classList.add('hocx');

    var dark = this.config.theme === 'dark'
      || (this.config.theme === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    root.setAttribute('data-mode', dark ? 'dark' : 'light');

    if (this.config.accent) {
      root.style.setProperty('--hocx-accent', this.config.accent);
    }

    // A #job-<slug> hash makes a role linkable even though the widget never
    // navigates — and keeps the browser's back button working.
    var hashed = (window.location.hash.match(/^#job-([A-Za-z0-9-]+)$/) || [])[1];
    var self   = this;

    window.addEventListener('hashchange', function () {
      var slug = (window.location.hash.match(/^#job-([A-Za-z0-9-]+)$/) || [])[1];
      if (slug) { self.openJob(slug, true); } else { self.renderList(); }
    });

    if (this.config.job) {
      this.openJob(this.config.job, true);
    } else if (hashed) {
      this.load(function () { self.openJob(hashed, true); });
    } else {
      this.load();
    }
  }

  Widget.prototype.busy = function (message) {
    this.root.innerHTML = '<div class="hocx-state"><div class="hocx-spin"></div>' + esc(message || 'Loading openings…') + '</div>';
  };

  Widget.prototype.fail = function (message) {
    this.root.innerHTML = '<div class="hocx-state">' + esc(message) + '</div>';
  };

  Widget.prototype.load = function (then) {
    var self  = this;
    var query = [];

    if (this.config.department) { query.push('department=' + encodeURIComponent(this.config.department)); }
    if (this.config.type) { query.push('type=' + encodeURIComponent(this.config.type)); }
    if (this.config.limit) { query.push('limit=' + encodeURIComponent(this.config.limit)); }

    this.busy();

    get(BASE + '/data' + (query.length ? '?' + query.join('&') : ''))
      .then(function (data) {
        if (!data || !data.ok) {
          self.fail((data && data.error) || 'Openings are being updated — please check back shortly.');
          return;
        }

        self.jobs   = data.jobs || [];
        self.facets = data.facets || {};

        if (then) { then(); } else { self.renderList(); }
      })
      .catch(function () {
        self.fail('Openings are being updated — please check back shortly.');
      });
  };

  /**
   * The openings passing every filter, optionally ignoring one facet's own
   * choices — which is how the count on each chip answers "how many would I get
   * if I added this" instead of "how many are showing", the latter reading as
   * zeros everywhere the moment one chip is on.
   */
  Widget.prototype.visible = function (skip) {
    var picked = this.state.picked;
    var terms  = this.state.search ? this.state.search.split(/\s+/).filter(Boolean) : [];
    var sorter = this.comparator();

    return this.jobs.filter(function (job) {
      for (var i = 0; i < terms.length; i++) {
        if (haystack(job).indexOf(terms[i]) === -1) { return false; }
      }

      for (var f = 0; f < FACETS.length; f++) {
        var facet = FACETS[f];
        if (facet.key === skip) { continue; }

        var chosen = picked[facet.key];
        if (chosen.length && chosen.indexOf(facet.of(job)) === -1) { return false; }
      }

      return true;
    }).sort(sorter);
  };

  Widget.prototype.comparator = function () {
    if (this.state.sort === 'newest') {
      return function (a, b) {
        return String(b.posted_at || '').localeCompare(String(a.posted_at || ''));
      };
    }
    if (this.state.sort === 'title') {
      return function (a, b) { return String(a.title).localeCompare(String(b.title)); };
    }

    // "Most relevant" keeps the CRM's own order — featured and urgent roles are
    // already sorted to the top there, and a recruiter's pinning should not be
    // silently overridden by the widget.
    return function () { return 0; };
  };

  /** `value => {label, count}` for one facet, counted with its own picks ignored. */
  Widget.prototype.facetOptions = function (facet) {
    var pool    = this.visible(facet.key);
    var options = {};

    this.jobs.forEach(function (job) {
      var value = facet.of(job);
      if (!value) { return; }

      if (!options[value]) {
        options[value] = { label: facet.labelOf ? facet.labelOf(job) : value, count: 0 };
      }
    });

    pool.forEach(function (job) {
      var value = facet.of(job);
      if (value && options[value]) { options[value].count++; }
    });

    var keys = Object.keys(options);

    // Experience reads as a ladder; everything else reads best alphabetically.
    keys.sort(facet.key === 'experience'
      ? function (a, b) { return EXP_ORDER.indexOf(a) - EXP_ORDER.indexOf(b); }
      : function (a, b) { return options[a].label.localeCompare(options[b].label); });

    return keys.map(function (value) {
      return { value: value, label: options[value].label, count: options[value].count };
    });
  };

  Widget.prototype.renderList = function () {
    if (!this.jobs.length) {
      this.fail('There are no open positions right now. Please check back soon.');
      return;
    }

    var html = '';

    if (this.config.heading) {
      html += '<h2 class="hocx-head">' + esc(this.config.heading) + '</h2>';
    }

    if (this.config.filters) {
      html += '<div class="hocx-bar">'
        + '<input type="search" data-hocx="search" placeholder="Search by role, skill, department or location…" value="' + esc(this.state.search) + '">'
        + '<select data-hocx="sort" aria-label="Sort openings">'
          + '<option value="relevance">Most relevant</option>'
          + '<option value="newest"' + (this.state.sort === 'newest' ? ' selected' : '') + '>Newest first</option>'
          + '<option value="title"' + (this.state.sort === 'title' ? ' selected' : '') + '>A – Z</option>'
        + '</select>'
        + '</div>'
        + '<div class="hocx-facets" data-hocx="facets">' + this.facetsHtml() + '</div>'
        + '<div class="hocx-active" data-hocx="active"></div>';
    }

    html += '<div class="hocx-count" data-hocx="count"></div>'
      + '<div class="hocx-list" data-layout="' + esc(this.config.layout) + '"></div>'
      + '<div class="hocx-more" data-hocx="more"></div>';

    this.root.innerHTML = html;
    this.refreshResults();
    this.bindList();
  };

  /** One chip group per facet, skipping any that cannot narrow anything. */
  Widget.prototype.facetsHtml = function () {
    var self = this;
    var html = '';

    FACETS.forEach(function (facet) {
      var options = self.facetOptions(facet);
      if (options.length < 2) { return; }

      html += '<div class="hocx-facet"><div class="hocx-facetlab">' + esc(facet.label) + '</div><div class="hocx-facetopts">';

      options.forEach(function (option) {
        var on = self.state.picked[facet.key].indexOf(option.value) !== -1;

        html += '<button type="button" class="hocx-chip2" data-hocx-facet="' + esc(facet.key) + '"'
          + ' data-hocx-value="' + esc(option.value) + '" aria-pressed="' + (on ? 'true' : 'false') + '"'
          // An active chip is never disabled: it must stay clickable to undo.
          + (!on && option.count === 0 ? ' disabled' : '') + '>'
          + esc(option.label)
          + (option.count ? '<span class="hocx-chipn">' + option.count + '</span>' : '')
          + '</button>';
      });

      html += '</div></div>';
    });

    return html;
  };

  Widget.prototype.activeHtml = function () {
    var self  = this;
    var pills = '';
    var total = 0;

    FACETS.forEach(function (facet) {
      self.state.picked[facet.key].forEach(function (value) {
        var option = self.facetOptions(facet).filter(function (o) { return o.value === value; })[0];

        total++;
        pills += '<span class="hocx-pill">' + esc(option ? option.label : value)
          + '<button type="button" data-hocx-drop="' + esc(facet.key) + '"'
          + ' data-hocx-value="' + esc(value) + '" aria-label="Remove filter">&times;</button></span>';
      });
    });

    if (!total) { return ''; }

    return '<span class="hocx-activelab">Filtering by</span>' + pills
      + '<button type="button" class="hocx-clear" data-hocx="clear">Clear all</button>';
  };

  Widget.prototype.card = function (job) {
    var isNew = job.posted_at && (Date.now() - new Date(job.posted_at.replace(' ', 'T')).getTime()) < 1209600000;

    return '<article class="hocx-card">'
      + '<div style="flex:1;min-width:220px">'
        + '<h3 class="hocx-title" data-hocx-open="' + esc(job.slug) + '">' + esc(job.title) + '</h3>'
        + '<div class="hocx-meta">'
          + (job.featured ? '<span class="hocx-chip hocx-chip-feat">★ Featured</span>' : '')
          + (job.urgent ? '<span class="hocx-chip hocx-chip-urgent">Urgent</span>' : '')
          + (isNew ? '<span class="hocx-chip hocx-chip-new">New</span>' : '')
          + (job.department ? '<span class="hocx-chip hocx-chip-accent">' + esc(job.department) + '</span>' : '')
          + (job.location ? '<span class="hocx-chip">' + esc(job.location) + '</span>' : '')
          + '<span class="hocx-chip">' + esc(job.type_label) + '</span>'
          + '<span class="hocx-chip">' + esc(job.work_mode_label) + '</span>'
          + (job.experience ? '<span class="hocx-chip">' + esc(job.experience) + '</span>' : '')
        + '</div>'
        + (job.summary ? '<p class="hocx-sum">' + esc(job.summary) + '</p>' : '')
      + '</div>'
      + '<div class="hocx-side">'
        + (job.salary ? '<span class="hocx-sal">' + esc(job.salary) + '</span>' : '')
        + '<button type="button" class="hocx-btn" data-hocx-open="' + esc(job.slug) + '">View &amp; Apply</button>'
        + (job.posted_ago ? '<span class="hocx-chip">' + esc(job.posted_ago) + '</span>' : '')
      + '</div>'
    + '</article>';
  };

  Widget.prototype.bindList = function () {
    var self = this;

    Array.prototype.forEach.call(this.root.querySelectorAll('[data-hocx-open]'), function (node) {
      node.addEventListener('click', function () {
        self.openJob(node.getAttribute('data-hocx-open'));
      });
    });

    var search = this.root.querySelector('[data-hocx="search"]');
    if (search) {
      var debounce;
      search.addEventListener('input', function () {
        window.clearTimeout(debounce);
        debounce = window.setTimeout(function () {
          self.state.search = search.value.toLowerCase().trim();
          // Only the results are re-rendered, so the field keeps focus.
          self.refreshResults();
        }, 130);
      });
    }

    var sort = this.root.querySelector('[data-hocx="sort"]');
    if (sort) {
      sort.addEventListener('change', function () {
        self.state.sort = sort.value;
        self.refreshResults();
      });
    }

    // Chips, the × on an active pill, "clear all" and "show more" are all
    // re-rendered on every refresh, so they are delegated from the root rather
    // than bound to nodes that are about to be replaced. The root itself
    // survives every render, so this must be attached exactly once — binding it
    // per render stacks handlers and a chip click starts toggling twice.
    if (this.delegated) { return; }
    this.delegated = true;

    this.root.addEventListener('click', function (event) {
      var target = event.target;
      if (!target || !target.closest) { return; }

      var chip = target.closest('[data-hocx-facet]');
      if (chip && !chip.disabled) {
        self.toggleFacet(chip.getAttribute('data-hocx-facet'), chip.getAttribute('data-hocx-value'));
        return;
      }

      var drop = target.closest('[data-hocx-drop]');
      if (drop) {
        self.toggleFacet(drop.getAttribute('data-hocx-drop'), drop.getAttribute('data-hocx-value'));
        return;
      }

      var action = target.closest('[data-hocx="clear"], [data-hocx="more"]');
      if (!action) { return; }

      if (action.getAttribute('data-hocx') === 'more') {
        self.state.shown += PAGE;
        self.refreshResults(true);
        return;
      }

      self.state.picked = emptyPicks();
      self.state.search = '';

      // Looked up now, not captured: this handler outlives every render, and
      // the field it was bound beside has been replaced since.
      var field = self.root.querySelector('[data-hocx="search"]');
      if (field) { field.value = ''; }

      self.refreshResults();
    });
  };

  Widget.prototype.toggleFacet = function (key, value) {
    var chosen = this.state.picked[key];
    if (!chosen) { return; }

    var at = chosen.indexOf(value);

    if (at === -1) { chosen.push(value); } else { chosen.splice(at, 1); }
    this.refreshResults();
  };

  /**
   * Re-render everything the filters affect, but never the search field — it is
   * left alone on purpose so typing does not lose focus or the caret position.
   */
  Widget.prototype.refreshResults = function (keepPage) {
    var self  = this;
    var list  = this.root.querySelector('.hocx-list');
    if (!list) { return; }

    if (!keepPage) { this.state.shown = PAGE; }

    var matched = this.visible();
    var shown   = matched.slice(0, this.state.shown);
    var count   = this.root.querySelector('[data-hocx="count"]');
    var facets  = this.root.querySelector('[data-hocx="facets"]');
    var active  = this.root.querySelector('[data-hocx="active"]');
    var more    = this.root.querySelector('[data-hocx="more"]');

    list.innerHTML = shown.length
      ? shown.map(function (job) { return self.card(job); }).join('')
      : '<div class="hocx-state">No roles match those filters. '
        + '<button type="button" class="hocx-linkbtn" data-hocx="clear">Clear all filters</button></div>';

    if (count) {
      count.textContent = matched.length === this.jobs.length
        ? 'Showing all ' + this.jobs.length + ' opening' + (this.jobs.length === 1 ? '' : 's')
        : 'Showing ' + matched.length + ' of ' + this.jobs.length + ' openings';
    }

    // The chip counts depend on what else is picked, so they are rebuilt with
    // the results rather than only when a facet is first drawn.
    if (facets) { facets.innerHTML = this.facetsHtml(); }
    if (active) { active.innerHTML = this.activeHtml(); }

    if (more) {
      var remaining = matched.length - shown.length;
      more.innerHTML = remaining > 0
        ? '<button type="button" class="hocx-btn hocx-btn-ghost" data-hocx="more">Show '
          + Math.min(PAGE, remaining) + ' more' + (remaining > PAGE ? ' of ' + remaining : '') + '</button>'
        : '';
    }

    Array.prototype.forEach.call(list.querySelectorAll('[data-hocx-open]'), function (node) {
      node.addEventListener('click', function () {
        self.openJob(node.getAttribute('data-hocx-open'));
      });
    });
  };

  Widget.prototype.openJob = function (slug, silent) {
    var self = this;

    this.busy('Loading position…');

    if (!silent) {
      try { window.history.replaceState(null, '', '#job-' + slug); } catch (e) { /* file:// and sandboxes */ }
    }

    get(BASE + '/data?track=1&slug=' + encodeURIComponent(slug))
      .then(function (data) {
        if (!data || !data.ok || !data.found) {
          self.fail('This position is no longer listed.');
          return;
        }
        self.renderJob(data);
      })
      .catch(function () {
        self.fail('Could not load this position. Please try again.');
      });
  };

  Widget.prototype.renderJob = function (data) {
    var job    = data.job;
    var fields = job.form_fields || {};
    var html   = '';

    if (!this.config.job) {
      html += '<button type="button" class="hocx-back" data-hocx-back>&larr; All openings</button>';
    }

    html += '<div class="hocx-detail">';
    html += '<h2>' + esc(job.title) + '</h2>';
    html += '<div class="hocx-meta">'
      + (job.department ? '<span class="hocx-chip hocx-chip-accent">' + esc(job.department) + '</span>' : '')
      + (job.location ? '<span class="hocx-chip">' + esc(job.location) + '</span>' : '')
      + '<span class="hocx-chip">' + esc(job.type_label) + '</span>'
      + '<span class="hocx-chip">' + esc(job.work_mode_label) + '</span>'
      + (job.urgent ? '<span class="hocx-chip hocx-chip-urgent">Urgent</span>' : '')
      + '</div>';

    html += '<div class="hocx-facts">'
      + this.fact('Experience', job.experience)
      + this.fact('Compensation', job.salary || job.stipend)
      + this.fact('Openings', job.openings > 1 ? job.openings + ' positions' : '1 position')
      + this.fact('Education', job.education)
      + this.fact('Duration', job.duration_months ? job.duration_months + ' months' : '')
      + this.fact('Apply by', job.deadline ? new Date(job.deadline).toLocaleDateString() : '')
      + this.fact('Reference', job.reference)
      + '</div>';

    // Server-filtered HTML from the CRM's own rich-text fields.
    if (job.summary) { html += '<p class="hocx-rich"><strong>' + esc(job.summary) + '</strong></p>'; }
    if (job.description) { html += '<h3>About the role</h3><div class="hocx-rich">' + job.description + '</div>'; }
    if (job.responsibilities) { html += '<h3>What you will do</h3><div class="hocx-rich">' + job.responsibilities + '</div>'; }
    if (job.requirements) { html += '<h3>What we are looking for</h3><div class="hocx-rich">' + job.requirements + '</div>'; }
    if (job.benefits) { html += '<h3>What we offer</h3><div class="hocx-rich">' + job.benefits + '</div>'; }

    if (job.skills && job.skills.length) {
      html += '<h3>Skills</h3><div class="hocx-meta">'
        + job.skills.map(function (s) { return '<span class="hocx-chip hocx-chip-accent">' + esc(s) + '</span>'; }).join('')
        + '</div>';
    }

    if (data.expired) {
      html += '<h3>Applications closed</h3><p class="hocx-rich">The deadline for this position has passed.</p>';
    } else if (job.apply_mode === 'external' && job.external_url) {
      html += '<h3>How to apply</h3><p><a class="hocx-btn" href="' + esc(job.external_url) + '" target="_blank" rel="noopener">Continue to the application</a></p>';
    } else if (data.apply_enabled) {
      html += this.form(job, data, fields);
    } else {
      html += '<h3>How to apply</h3><p class="hocx-rich">Online applications are closed for this role at the moment.</p>';
    }

    html += '</div>';

    this.root.innerHTML = html;
    this.bindJob(job, data);

    if (this.root.getBoundingClientRect().top < 0) {
      this.root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  Widget.prototype.fact = function (label, value) {
    if (!value && value !== 0) { return ''; }

    return '<div><div class="hocx-fact-l">' + esc(label) + '</div><div class="hocx-fact-v">' + esc(value) + '</div></div>';
  };

  Widget.prototype.form = function (job, data, fields) {
    var required = data.resume_required && fields.resume;
    var html = '<h3>Apply for this position</h3>'
      + '<form class="hocx-form" data-hocx-form novalidate>'
      + '<input type="hidden" name="slug" value="' + esc(job.slug) + '">'
      + '<input type="hidden" name="source" value="embed">'
      + '<input type="hidden" name="utm" data-hocx-utm value="">'
      + '<input type="text" name="company_website" class="hocx-hp" tabindex="-1" autocomplete="off" aria-hidden="true">'
      + '<div class="hocx-msg" data-hocx-msg></div>'
      + '<div class="hocx-row">'
        + this.input('name', 'Full name', 'text', true)
        + this.input('email', 'Email', 'email', true)
      + '</div>';

    var pairs = [
      [fields.phone, this.input('phone', 'Mobile number', 'tel', true)],
      [fields.current_location, this.input('current_location', 'Current location', 'text')],
      [fields.total_experience, this.input('total_experience', 'Total experience (years)', 'number')],
      [fields.current_company, this.input('current_company', 'Current company', 'text')],
      [fields.current_ctc, this.input('current_ctc', 'Current CTC', 'text')],
      [fields.expected_ctc, this.input('expected_ctc', 'Expected CTC', 'text')],
      [fields.notice_period, this.input('notice_period', 'Notice period', 'text')],
      [fields.linkedin_url, this.input('linkedin_url', 'LinkedIn profile', 'url')],
      [fields.portfolio_url, this.input('portfolio_url', 'Portfolio / GitHub', 'url')]
    ].filter(function (pair) { return pair[0]; }).map(function (pair) { return pair[1]; });

    for (var i = 0; i < pairs.length; i += 2) {
      html += '<div class="hocx-row">' + pairs[i] + (pairs[i + 1] || '') + '</div>';
    }

    (data.questions || []).forEach(function (question) {
      html += Widget.prototype.question(question);
    });

    if (fields.cover_letter) {
      html += '<div class="hocx-field"><label>Why are you a good fit?</label>'
        + '<textarea name="cover_letter" placeholder="A short note about you and why this role interests you…"></textarea></div>';
    }

    if (fields.resume) {
      html += '<div class="hocx-field"><label>Resume / CV ' + (required ? '<span class="hocx-req">*</span>' : '') + '</label>'
        + '<label class="hocx-file"><input type="file" name="resume" data-hocx-file accept=".' + (data.allowed_ext || []).join(',.') + '"' + (required ? ' required' : '') + '>'
        + '<b>Click to attach your CV</b><div class="hocx-note">' + (data.allowed_ext || []).join(', ').toUpperCase()
        + ' · up to ' + (data.max_resume_mb || 5) + ' MB</div><div data-hocx-filename></div></label></div>';
    }

    html += '<div><button type="submit" class="hocx-btn" data-hocx-submit>Submit application</button></div>'
      + '<div class="hocx-note">Your details go straight to our hiring team.</div>'
      + '</form>';

    return html;
  };

  Widget.prototype.input = function (name, label, type, required) {
    return '<div class="hocx-field"><label>' + esc(label) + (required ? ' <span class="hocx-req">*</span>' : '') + '</label>'
      + '<input type="' + type + '" name="' + name + '"' + (required ? ' required' : '')
      + (type === 'number' ? ' step="0.5" min="0"' : '') + '></div>';
  };

  Widget.prototype.question = function (question) {
    var name = 'q_' + question.id;
    var req  = question.required ? ' <span class="hocx-req">*</span>' : '';
    var html = '<div class="hocx-field"><label>' + esc(question.question) + req + '</label>';

    if (question.type === 'textarea') {
      html += '<textarea name="' + name + '"' + (question.required ? ' required' : '') + '></textarea>';
    } else if (question.type === 'select') {
      html += '<select name="' + name + '"' + (question.required ? ' required' : '') + '><option value="">Select…</option>'
        + (question.options || []).map(function (o) { return '<option>' + esc(o) + '</option>'; }).join('') + '</select>';
    } else if (question.type === 'yesno') {
      html += '<div class="hocx-choices"><label><input type="radio" name="' + name + '" value="Yes"'
        + (question.required ? ' required' : '') + '> Yes</label>'
        + '<label><input type="radio" name="' + name + '" value="No"> No</label></div>';
    } else if (question.type === 'radio' || question.type === 'checkbox') {
      var kind = question.type === 'radio' ? 'radio' : 'checkbox';
      var box  = question.type === 'radio' ? name : name + '[]';
      html += '<div class="hocx-choices">' + (question.options || []).map(function (o) {
        return '<label><input type="' + kind + '" name="' + box + '" value="' + esc(o) + '"> ' + esc(o) + '</label>';
      }).join('') + '</div>';
    } else {
      var type = ['number', 'date', 'url'].indexOf(question.type) !== -1 ? question.type : 'text';
      html += '<input type="' + type + '" name="' + name + '"' + (question.required ? ' required' : '') + '>';
    }

    return html + '</div>';
  };

  Widget.prototype.bindJob = function (job, data) {
    var self = this;
    var back = this.root.querySelector('[data-hocx-back]');

    if (back) {
      back.addEventListener('click', function () {
        try { window.history.replaceState(null, '', window.location.pathname + window.location.search); } catch (e) { /* ignore */ }
        if (self.jobs.length) { self.renderList(); } else { self.load(); }
      });
    }

    var form = this.root.querySelector('[data-hocx-form]');
    if (!form) { return; }

    var file = form.querySelector('[data-hocx-file]');
    if (file) {
      file.addEventListener('change', function () {
        var label = form.querySelector('[data-hocx-filename]');
        if (!label) { return; }
        label.innerHTML = file.files.length
          ? '<b>' + esc(file.files[0].name) + '</b> (' + (file.files[0].size / 1048576).toFixed(1) + ' MB)'
          : '';
      });
    }

    var utm = form.querySelector('[data-hocx-utm]');
    if (utm) {
      var params = new URLSearchParams(window.location.search);
      var parts  = [];
      ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach(function (key) {
        if (params.get(key)) { parts.push(key + '=' + params.get(key)); }
      });
      utm.value = parts.join('&').slice(0, 500);
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      self.submit(form, job, data);
    });
  };

  Widget.prototype.submit = function (form, job, data) {
    var self   = this;
    var msg    = form.querySelector('[data-hocx-msg]');
    var button = form.querySelector('[data-hocx-submit]');
    var file   = form.querySelector('[data-hocx-file]');

    function say(text, ok) {
      msg.className = 'hocx-msg ' + (ok ? 'ok' : 'err');
      msg.textContent = text;
      msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    var name  = form.querySelector('[name="name"]').value.trim();
    var email = form.querySelector('[name="email"]').value.trim();

    if (!name) { return say('Please enter your full name.', false); }
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) { return say('Please enter a valid email address.', false); }

    if (file && file.files.length) {
      var picked  = file.files[0];
      var ext     = (picked.name.split('.').pop() || '').toLowerCase();
      var allowed = data.allowed_ext || [];

      if (allowed.length && allowed.indexOf(ext) === -1) {
        return say('Please attach a ' + allowed.join(', ').toUpperCase() + ' file.', false);
      }
      if (picked.size > (data.max_resume_mb || 5) * 1048576) {
        return say('Your file is larger than ' + (data.max_resume_mb || 5) + ' MB.', false);
      }
    }

    if (!form.checkValidity()) {
      var invalid = form.querySelector(':invalid');
      if (invalid) { invalid.focus(); }
      return say('Please complete the highlighted fields.', false);
    }

    button.disabled = true;
    button.textContent = 'Submitting…';
    msg.className = 'hocx-msg';

    fetch(BASE + '/apply', { method: 'POST', body: new FormData(form), credentials: 'omit' })
      .then(function (response) { return response.json().catch(function () { return null; }); })
      .then(function (result) {
        button.disabled = false;
        button.textContent = 'Submit application';

        if (result && result.ok) {
          self.root.querySelector('.hocx-detail').innerHTML =
            '<div class="hocx-done"><div class="hocx-tick">✓</div>'
            + '<h2>Application received</h2>'
            + '<p class="hocx-rich">Thank you for applying for <strong>' + esc(job.title) + '</strong>. '
            + 'A confirmation is on its way to your inbox.</p>'
            + (result.reference ? '<p>Your reference: <span class="hocx-ref">' + esc(result.reference) + '</span></p>' : '')
            + '<p><button type="button" class="hocx-btn hocx-btn-ghost" data-hocx-back>Browse other openings</button></p>'
            + '</div>';

          var back = self.root.querySelector('[data-hocx-back]');
          if (back) {
            back.addEventListener('click', function () {
              try { window.history.replaceState(null, '', window.location.pathname + window.location.search); } catch (e) { /* ignore */ }
              if (self.jobs.length) { self.renderList(); } else { self.load(); }
            });
          }
          return;
        }

        say((result && result.error) || 'We could not submit your application. Please try again.', false);
      })
      .catch(function () {
        button.disabled = false;
        button.textContent = 'Submit application';
        say('Something went wrong on the way. Please try again.', false);
      });
  };

  /* ────────────────────────────── boot ───────────────────────────────── */

  function boot() {
    injectStyles();

    var targets = [].slice.call(document.querySelectorAll('[data-careers-embed]'));
    var legacy  = document.getElementById('healtho-careers');

    if (legacy && targets.indexOf(legacy) === -1) { targets.push(legacy); }

    targets.forEach(function (node) {
      // A script tag included twice must not render the widget twice.
      if (node.getAttribute('data-hocx-ready') === '1') { return; }
      node.setAttribute('data-hocx-ready', '1');
      new Widget(node);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  // Exposed so a host page can mount a container it creates later.
  window.HealthOCareers = { mount: boot };
}());
