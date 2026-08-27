/* ═══════════════════════════════════════════════════════════════════════════
 * Pro Tickets — Smart Ticket capture widget (client portal)
 *
 * Flow:  shortcut / FAB  →  annotate the live screen  →  capture screenshot
 *        →  describe the issue + pick a department  →  submit (image + text).
 *
 * Configuration is provided by the server on window.PST_SMART_TICKET (urls,
 * csrf, departments, priorities, shortcut label). Everything is drawn into a
 * dedicated .pst-root layer that carries data-html2canvas-ignore so the widget
 * chrome is never captured into the screenshot itself.
 * ═══════════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var CFG = window.PST_SMART_TICKET || {};
    if (!CFG.url) { return; }

    var COLORS = ['#ef4444', '#f59e0b', '#22c55e', '#3b82f6', '#a855f7', '#0f172a'];

    // Detect the OS so the shortcut is shown — and matched — in the platform's
    // own notation: macOS → ⌥⇧N (Option+Shift+N), Windows/Linux → Ctrl+Alt+N.
    //
    // Windows deliberately does NOT use Alt+Shift: that chord is the OS-level
    // "switch keyboard layout / input language" hotkey. Anyone with a second
    // layout installed silently flipped their keyboard when they pressed the
    // old shortcut, and every field on the page (ours and the app's) then
    // stopped accepting normal typing until they switched back — which is
    // exactly the "can't type anything, works on the Mac though" report.
    var IS_MAC = (function () {
        var p = (navigator.userAgentData && navigator.userAgentData.platform) ||
                navigator.platform || navigator.userAgent || '';
        return /mac|iphone|ipad|ipod/i.test(p);
    })();
    var SHORTCUT_LABEL = IS_MAC ? '⌥⇧N' : 'Ctrl+Alt+N';

    // Touch-first devices (phones/tablets) have no physical shortcut, so the
    // key hint is hidden there and the tap-the-button path is the only launcher.
    var IS_TOUCH = (function () {
        return ('ontouchstart' in window) ||
               (navigator.maxTouchPoints > 0) ||
               (navigator.msMaxTouchPoints > 0);
    })();

    // Output format for the captured screenshot. The customer portal uploads a
    // PNG file; the admin (cross-instance) path embeds the shot inline in the
    // provider ticket, so it uses a lighter JPEG to keep the payload small.
    var IMG_FORMAT  = CFG.imageFormat || 'image/png';
    var IMG_QUALITY = (typeof CFG.imageQuality === 'number') ? CFG.imageQuality : 0.92;
    var IMG_EXT     = IMG_FORMAT === 'image/jpeg' ? 'jpg' : 'png';

    function shortcutKbdHtml() {
        return IS_MAC
            ? '<kbd>⌥</kbd><kbd>⇧</kbd><kbd>N</kbd>'
            : '<kbd>Ctrl</kbd>+<kbd>Alt</kbd>+<kbd>N</kbd>';
    }

    // Does this keystroke mean "open the reporter"? Matched on the physical key
    // (e.code) because ⌥⇧N on macOS emits a dead-key character rather than "N".
    function isShortcut(e) {
        var isN = (e.code === 'KeyN') || (e.key === 'n') || (e.key === 'N');
        if (!isN || e.metaKey) { return false; }
        return IS_MAC
            ? (e.altKey && e.shiftKey)
            : (e.ctrlKey && e.altKey);
    }

    // ── State ──────────────────────────────────────────────────────────────
    var state = {
        tool: 'rect',           // rect | arrow | pen | highlight
        color: '#ef4444',
        drawing: false,
        start: null,
        strokes: [],            // committed shapes
        current: null,          // in-progress shape
        shot: null,             // captured data URL (base64 png)
        ratio: window.devicePixelRatio || 1
    };

    var el = {};                // cached DOM nodes

    // ── Bootstrap DOM once ─────────────────────────────────────────────────
    function build() {
        if (el.root) { return; }

        var root = document.createElement('div');
        root.className = 'pst-root';
        root.setAttribute('data-html2canvas-ignore', 'true');
        root.innerHTML = [
            // Annotation stage
            '<div class="pst-stage" id="pstStage">',
            '  <div class="pst-hint">' + CFG.i18n.hint + '</div>',
            '  <canvas class="pst-draw" id="pstDraw"></canvas>',
            '  <div class="pst-toolbar" id="pstToolbar">',
            tool('rect', 'fa-regular fa-square', CFG.i18n.t_box),
            tool('arrow', 'fa-solid fa-arrow-up-long', CFG.i18n.t_arrow),
            tool('pen', 'fa-solid fa-pen', CFG.i18n.t_pen),
            tool('highlight', 'fa-solid fa-highlighter', CFG.i18n.t_highlight),
            '    <div class="pst-sep"></div>',
            '    <div class="pst-swatches" id="pstSwatches"></div>',
            '    <div class="pst-sep"></div>',
            '    <button type="button" class="pst-tool" id="pstUndo"><i class="fa-solid fa-rotate-left"></i><span class="pst-tip">' + esc(CFG.i18n.undo) + '</span></button>',
            '    <button type="button" class="pst-tool" id="pstClear"><i class="fa-solid fa-eraser"></i><span class="pst-tip">' + esc(CFG.i18n.clear) + '</span></button>',
            '    <div class="pst-sep"></div>',
            '    <button type="button" class="pst-btn pst-btn-ghost" id="pstCancel"><i class="fa-solid fa-xmark"></i> ' + esc(CFG.i18n.cancel) + '</button>',
            '    <button type="button" class="pst-btn pst-btn-primary" id="pstCapture"><i class="fa-solid fa-camera"></i> ' + esc(CFG.i18n.capture) + '</button>',
            '  </div>',
            '</div>',

            // Capturing spinner
            '<div class="pst-capturing" id="pstCapturing"><span class="pst-spin"></span><span>' + esc(CFG.i18n.capturing) + '</span></div>',

            // Compose drawer
            '<div class="pst-modal" id="pstModal">',
            '  <div class="pst-drawer">',
            '    <div class="pst-drawer-head">',
            '      <h4><i class="fa-solid fa-wand-magic-sparkles"></i> ' + esc(CFG.i18n.title) + '</h4>',
            '      <button type="button" class="pst-close" id="pstClose"><i class="fa-solid fa-xmark"></i></button>',
            '    </div>',
            '    <div class="pst-drawer-body">',
            '      <div class="pst-error" id="pstError"></div>',
            '      <div class="pst-preview" id="pstPreview">',
            '        <span class="pst-preview-badge"><i class="fa-solid fa-paperclip"></i> ' + esc(CFG.i18n.screenshot) + '</span>',
            '        <button type="button" class="pst-retake" id="pstRetake"><i class="fa-solid fa-rotate-left"></i> ' + esc(CFG.i18n.retake) + '</button>',
            '        <img id="pstShotImg" alt="screenshot">',
            '      </div>',
            '      <div class="pst-field">',
            '        <label>' + esc(CFG.i18n.subject) + '</label>',
            '        <input type="text" class="pst-input" id="pstSubject" maxlength="180" placeholder="' + esc(CFG.i18n.subject_ph) + '">',
            '      </div>',
            '      <div class="pst-field">',
            '        <label>' + esc(CFG.i18n.issue) + ' <span class="req">*</span></label>',
            '        <textarea class="pst-textarea" id="pstMessage" placeholder="' + esc(CFG.i18n.issue_ph) + '"></textarea>',
            '      </div>',
            '      <div class="pst-grid-2">',
            '        <div class="pst-field">',
            '          <label>' + esc(CFG.i18n.department) + ' <span class="req">*</span></label>',
            '          <select class="pst-select" id="pstDepartment">' + deptOptions() + '</select>',
            '        </div>',
            '        <div class="pst-field">',
            '          <label>' + esc(CFG.i18n.priority) + '</label>',
            '          <select class="pst-select" id="pstPriority">' + prioOptions() + '</select>',
            '        </div>',
            '      </div>',
            '    </div>',
            '    <div class="pst-drawer-foot">',
            '      <button type="button" class="pst-btn pst-btn-ghost" id="pstBack"><i class="fa-solid fa-pen"></i> ' + esc(CFG.i18n.edit_marks) + '</button>',
            '      <button type="button" class="pst-btn pst-btn-primary" id="pstSubmit"><i class="fa-solid fa-paper-plane"></i> ' + esc(CFG.i18n.submit) + '</button>',
            '    </div>',
            '  </div>',
            '</div>'
        ].join('');

        document.body.appendChild(root);
        el.root = root;
        el.stage = root.querySelector('#pstStage');
        el.canvas = root.querySelector('#pstDraw');
        el.toolbar = root.querySelector('#pstToolbar');
        el.capturing = root.querySelector('#pstCapturing');
        el.modal = root.querySelector('#pstModal');
        el.shotImg = root.querySelector('#pstShotImg');
        el.error = root.querySelector('#pstError');
        el.subject = root.querySelector('#pstSubject');
        el.message = root.querySelector('#pstMessage');
        el.department = root.querySelector('#pstDepartment');
        el.priority = root.querySelector('#pstPriority');
        el.submit = root.querySelector('#pstSubmit');
        el.ctx = el.canvas.getContext('2d');

        buildSwatches();
        wire();

        // Rewrite the in-page guide's shortcut hint to the platform notation.
        var gk = document.getElementById('pstGuideKbd');
        if (gk) { gk.innerHTML = shortcutKbdHtml(); }

        // Same for the collapsed sticky row — platform notation, or hide the
        // key hint entirely on touch devices where no shortcut applies.
        var mk = document.getElementById('pstMiniKbd');
        if (mk) {
            if (IS_TOUCH) { mk.style.display = 'none'; }
            else { mk.innerHTML = shortcutKbdHtml(); }
        }

        // Shortcut chip on the Pro Tickets module header button (admin panel).
        var ak = document.getElementById('ptk-smart-kbd');
        if (ak) {
            if (IS_TOUCH) { ak.style.display = 'none'; }
            else { ak.textContent = SHORTCUT_LABEL; }
        }
    }

    function tool(name, icon, tip) {
        return '<button type="button" class="pst-tool" data-tool="' + name + '"><i class="' + icon + '"></i><span class="pst-tip">' + esc(tip) + '</span></button>';
    }

    function deptOptions() {
        var out = '';
        (CFG.departments || []).forEach(function (d) {
            var sel = (CFG.defaultDepartment && String(d.id) === String(CFG.defaultDepartment)) ? ' selected' : '';
            out += '<option value="' + esc(d.id) + '"' + sel + '>' + esc(d.name) + '</option>';
        });
        return out;
    }

    function prioOptions() {
        var out = '';
        (CFG.priorities || []).forEach(function (p) {
            var sel = (String(p.id) === String(CFG.defaultPriority)) ? ' selected' : '';
            out += '<option value="' + esc(p.id) + '"' + sel + '>' + esc(p.name) + '</option>';
        });
        return out;
    }

    function buildSwatches() {
        var wrap = el.root.querySelector('#pstSwatches');
        COLORS.forEach(function (c, i) {
            var s = document.createElement('button');
            s.type = 'button';
            s.className = 'pst-swatch' + (i === 0 ? ' is-active' : '');
            s.style.background = c;
            s.setAttribute('data-color', c);
            wrap.appendChild(s);
        });
    }

    // ── Wiring ─────────────────────────────────────────────────────────────
    function wire() {

        el.toolbar.querySelectorAll('.pst-tool[data-tool]').forEach(function (b) {
            b.addEventListener('click', function () { setTool(b.getAttribute('data-tool')); });
        });
        el.root.querySelectorAll('.pst-swatch').forEach(function (s) {
            s.addEventListener('click', function () {
                state.color = s.getAttribute('data-color');
                el.root.querySelectorAll('.pst-swatch').forEach(function (x) { x.classList.remove('is-active'); });
                s.classList.add('is-active');
            });
        });

        el.root.querySelector('#pstUndo').addEventListener('click', undo);
        el.root.querySelector('#pstClear').addEventListener('click', clearStrokes);
        el.root.querySelector('#pstCancel').addEventListener('click', close);
        el.root.querySelector('#pstCapture').addEventListener('click', capture);
        el.root.querySelector('#pstClose').addEventListener('click', close);
        el.root.querySelector('#pstBack').addEventListener('click', backToAnnotate);
        el.root.querySelector('#pstRetake').addEventListener('click', backToAnnotate);
        el.submit.addEventListener('click', submit);

        // Pointer drawing
        el.canvas.addEventListener('pointerdown', onDown);
        el.canvas.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);

        // Close drawer on backdrop click
        el.modal.addEventListener('mousedown', function (e) {
            if (e.target === el.modal) { close(); }
        });

        // Keyboard shortcut (Ctrl+Alt+N / ⌥⇧N) + Esc to abort.
        // Bound in the CAPTURE phase: host pages bind their own document-level
        // key handlers (hotkey libraries, editors) and some of them call
        // stopImmediatePropagation(), which would otherwise silently kill the
        // shortcut on exactly the pages people most want to report from.
        document.addEventListener('keydown', function (e) {
            if (isShortcut(e)) {
                e.preventDefault();
                if (!isOpen()) { open(); }
                return;
            }
            if (e.key === 'Escape' && isOpen()) { close(); }
        }, true);

        // ── Keep the host page's handlers out of our fields ─────────────────
        // The widget is injected app-wide, so it lands inside pages that bind
        // document-level key/focus handlers of their own — Bootstrap's modal
        // focus trap, the admin's $.Shortcuts hotkeys, TinyMCE's focusin fix,
        // DataTables, other modules. Any one of them can swallow a keystroke
        // or yank focus straight back out of the drawer, which reads to the
        // user as "the input is focused but won't accept text".
        //
        // Everything that happens inside .pst-root is ours: handle Escape here
        // (the document listener above will never see it now) and stop the
        // event before it reaches those listeners.
        ['keydown', 'keypress', 'keyup'].forEach(function (type) {
            el.root.addEventListener(type, function (e) {
                if (type === 'keydown' && e.key === 'Escape' && isOpen() && !isShortcut(e)) {
                    close();
                }
                e.stopPropagation();
            });
        });
        ['focusin', 'focusout'].forEach(function (type) {
            el.root.addEventListener(type, function (e) { e.stopPropagation(); });
        });
    }

    // ── Open / close ───────────────────────────────────────────────────────
    function isOpen() {
        return el.stage.classList.contains('is-active') || el.modal.classList.contains('is-active');
    }

    function open() {
        build();
        state.strokes = [];
        state.current = null;
        state.shot = null;

        // Start every report from a clean drawer — a submission that confirmed
        // in place (no redirect) otherwise leaves its text and disabled submit
        // button behind for the next one.
        el.error.classList.remove('is-shown', 'is-success');
        el.subject.value = '';
        el.message.value = '';
        setSubmitting(false);

        sizeCanvas();
        redraw();
        el.stage.classList.add('is-active');
        el.modal.classList.remove('is-active');
        setTool(state.tool);

        // Warm the screenshot engine while the user marks up the page, so the
        // download is already done by the time they hit Capture. Failures are
        // retried (and surfaced) there — nothing to report yet.
        ensureHtml2Canvas().catch(function () {});
    }

    function close() {
        el.stage.classList.remove('is-active');
        el.modal.classList.remove('is-active');
        el.capturing.classList.remove('is-active');
    }

    function setTool(name) {
        state.tool = name;
        el.toolbar.querySelectorAll('.pst-tool[data-tool]').forEach(function (b) {
            b.classList.toggle('is-active', b.getAttribute('data-tool') === name);
        });
    }

    // ── Canvas sizing / drawing ────────────────────────────────────────────
    function sizeCanvas() {
        var w = window.innerWidth, h = window.innerHeight, r = state.ratio;
        el.canvas.width = w * r;
        el.canvas.height = h * r;
        el.canvas.style.width = w + 'px';
        el.canvas.style.height = h + 'px';
        el.ctx.setTransform(r, 0, 0, r, 0, 0);
    }

    function pt(e) {
        var rect = el.canvas.getBoundingClientRect();
        return { x: e.clientX - rect.left, y: e.clientY - rect.top };
    }

    function onDown(e) {
        if (!el.stage.classList.contains('is-active')) { return; }
        el.canvas.setPointerCapture && el.canvas.setPointerCapture(e.pointerId);
        state.drawing = true;
        var p = pt(e);
        state.current = { tool: state.tool, color: state.color, from: p, to: p, points: [p] };
    }

    function onMove(e) {
        if (!state.drawing || !state.current) { return; }
        var p = pt(e);
        state.current.to = p;
        if (state.current.tool === 'pen' || state.current.tool === 'highlight') {
            state.current.points.push(p);
        }
        redraw();
    }

    function onUp() {
        if (!state.drawing) { return; }
        state.drawing = false;
        if (state.current) {
            var c = state.current;
            var moved = Math.abs(c.to.x - c.from.x) + Math.abs(c.to.y - c.from.y);
            if (moved > 3 || c.points.length > 2) { state.strokes.push(c); }
            state.current = null;
            redraw();
        }
    }

    function redraw() {
        var ctx = el.ctx;
        ctx.clearRect(0, 0, el.canvas.width, el.canvas.height);
        state.strokes.forEach(function (s) { drawShape(ctx, s); });
        if (state.current) { drawShape(ctx, state.current); }
    }

    function drawShape(ctx, s) {
        ctx.save();
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        if (s.tool === 'rect') {
            ctx.strokeStyle = s.color;
            ctx.lineWidth = 3;
            var x = Math.min(s.from.x, s.to.x), y = Math.min(s.from.y, s.to.y);
            var w = Math.abs(s.to.x - s.from.x), h = Math.abs(s.to.y - s.from.y);
            roundRect(ctx, x, y, w, h, 4);
            ctx.stroke();
        } else if (s.tool === 'arrow') {
            drawArrow(ctx, s.from, s.to, s.color);
        } else if (s.tool === 'highlight') {
            ctx.strokeStyle = s.color;
            ctx.globalAlpha = 0.32;
            ctx.lineWidth = 16;
            drawPath(ctx, s.points);
        } else { // pen
            ctx.strokeStyle = s.color;
            ctx.lineWidth = 3;
            drawPath(ctx, s.points);
        }
        ctx.restore();
    }

    function drawPath(ctx, pts) {
        if (!pts.length) { return; }
        ctx.beginPath();
        ctx.moveTo(pts[0].x, pts[0].y);
        for (var i = 1; i < pts.length; i++) { ctx.lineTo(pts[i].x, pts[i].y); }
        ctx.stroke();
    }

    function drawArrow(ctx, from, to, color) {
        var head = 14, ang = Math.atan2(to.y - from.y, to.x - from.x);
        ctx.strokeStyle = color;
        ctx.fillStyle = color;
        ctx.lineWidth = 3.5;
        ctx.beginPath();
        ctx.moveTo(from.x, from.y);
        ctx.lineTo(to.x, to.y);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(to.x, to.y);
        ctx.lineTo(to.x - head * Math.cos(ang - Math.PI / 7), to.y - head * Math.sin(ang - Math.PI / 7));
        ctx.lineTo(to.x - head * Math.cos(ang + Math.PI / 7), to.y - head * Math.sin(ang + Math.PI / 7));
        ctx.closePath();
        ctx.fill();
    }

    function roundRect(ctx, x, y, w, h, r) {
        r = Math.min(r, w / 2, h / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function undo() { state.strokes.pop(); redraw(); }
    function clearStrokes() { state.strokes = []; redraw(); }

    // ── Capture ────────────────────────────────────────────────────────────

    /**
     * Resolve once window.html2canvas is usable.
     *
     * The engine is ~194 KB and this widget is injected on every page of the
     * app, so it is fetched on the first capture instead of on every page load.
     * Concurrent calls share the one in-flight request.
     */
    var h2cPromise = null;
    function ensureHtml2Canvas() {
        if (typeof window.html2canvas === 'function') {
            return Promise.resolve();
        }
        if (h2cPromise) {
            return h2cPromise;
        }
        if (!CFG.html2canvasUrl) {
            return Promise.reject(new Error('html2canvas url not configured'));
        }

        h2cPromise = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = CFG.html2canvasUrl;
            s.async = true;
            s.onload = function () {
                typeof window.html2canvas === 'function'
                    ? resolve()
                    : reject(new Error('html2canvas did not register'));
            };
            s.onerror = function () {
                h2cPromise = null;   // let the next attempt retry the download
                reject(new Error('html2canvas failed to load'));
            };
            document.head.appendChild(s);
        });

        return h2cPromise;
    }

    function capture() {
        // Hide our own chrome for the shot; the .pst-root already carries the
        // ignore attribute, but hide the draw canvas so we can composite our
        // vector annotations at full fidelity on top afterwards.
        el.toolbar.style.visibility = 'hidden';
        el.root.querySelector('.pst-hint').style.visibility = 'hidden';
        el.canvas.style.visibility = 'hidden';
        el.capturing.classList.add('is-active');

        var sx = window.scrollX, sy = window.scrollY;
        var vw = window.innerWidth, vh = window.innerHeight;

        // The spinner is already up, so the first-capture download of the
        // screenshot engine reads as part of "Capturing the screen…".
        ensureHtml2Canvas().then(function () {
            return window.html2canvas(document.body, {
                x: sx,
                y: sy,
                width: vw,
                height: vh,
                scrollX: 0,
                scrollY: 0,
                windowWidth: document.documentElement.scrollWidth,
                windowHeight: document.documentElement.scrollHeight,
                useCORS: true,
                allowTaint: false,
                backgroundColor: '#ffffff',
                logging: false,
                scale: Math.min(state.ratio, 2)
            });
        }).then(function (base) {
            // Composite annotations onto the page raster.
            var out = document.createElement('canvas');
            out.width = base.width;
            out.height = base.height;
            var octx = out.getContext('2d');
            octx.drawImage(base, 0, 0);

            var scaleX = base.width / vw, scaleY = base.height / vh;
            octx.save();
            octx.scale(scaleX, scaleY);
            state.strokes.forEach(function (s) { drawShape(octx, s); });
            octx.restore();

            // Downscale so the upload stays small enough to clear typical PHP
            // upload_max_filesize limits (a retina capture can otherwise be
            // several MB and get silently dropped, leaving a ticket with no
            // image). Cap the longest edge at 1600px.
            var MAX_EDGE = 1600;
            var finalCanvas = out;
            if (out.width > MAX_EDGE) {
                var ratio = MAX_EDGE / out.width;
                var sc = document.createElement('canvas');
                sc.width = MAX_EDGE;
                sc.height = Math.round(out.height * ratio);
                var sctx = sc.getContext('2d');
                sctx.drawImage(out, 0, 0, sc.width, sc.height);
                finalCanvas = sc;
            }

            state.shot = finalCanvas.toDataURL(IMG_FORMAT, IMG_QUALITY);
            el.shotImg.src = state.shot;
            finishCapture();
            el.stage.classList.remove('is-active');
            el.modal.classList.add('is-active');
            // Prefill subject with today's page context if empty
            if (!el.subject.value) { el.subject.value = ''; }
            // window.focus() first: a shortcut chord can leave keyboard focus
            // parked on the browser's own chrome (Windows), where the field
            // shows its focus ring but never receives the typing.
            setTimeout(function () {
                try { window.focus(); } catch (e) {}
                el.message.focus();
            }, 120);
        }).catch(function (err) {
            finishCapture();
            alert(CFG.i18n.capture_failed);
            /* eslint-disable no-console */
            if (window.console) { console.error('[SmartTicket] capture failed', err); }
        });
    }

    function finishCapture() {
        el.toolbar.style.visibility = '';
        el.root.querySelector('.pst-hint').style.visibility = '';
        el.canvas.style.visibility = '';
        el.capturing.classList.remove('is-active');
    }

    function backToAnnotate() {
        el.modal.classList.remove('is-active');
        el.stage.classList.add('is-active');
        sizeCanvas();
        redraw();
    }

    // ── Submit ─────────────────────────────────────────────────────────────
    function showError(msg) {
        el.error.classList.remove('is-success');
        el.error.textContent = msg;
        el.error.classList.add('is-shown');
        el.error.scrollIntoView && el.error.scrollIntoView({ block: 'nearest' });
    }

    // Terminal success state for submissions with nowhere to redirect to: keep
    // the confirmation on screen, lock the form, then close by itself.
    function showSuccess(msg) {
        el.error.classList.add('is-success', 'is-shown');
        el.error.textContent = msg || CFG.i18n.sent;
        el.error.scrollIntoView && el.error.scrollIntoView({ block: 'nearest' });

        el.submit.disabled = true;
        el.submit.innerHTML = '<i class="fa-solid fa-check"></i> ' + esc(CFG.i18n.sent);

        setTimeout(close, 2600);
    }

    function submit() {
        el.error.classList.remove('is-shown');
        var message = el.message.value.trim();
        var department = el.department.value;
        if (!message) { showError(CFG.i18n.err_issue); el.message.focus(); return; }
        if (!department) { showError(CFG.i18n.err_department); el.department.focus(); return; }

        var fd = new FormData();
        fd.append('subject', el.subject.value.trim());
        fd.append('message', message);
        fd.append('department', department);
        fd.append('priority', el.priority.value);
        fd.append('source_url', window.location.href);
        fd.append(CFG.csrfName, CFG.csrfHash);
        if (state.shot) {
            fd.append('attachments[0]', dataURLtoBlob(state.shot), 'screenshot-' + Date.now() + '.' + IMG_EXT);
        }

        setSubmitting(true);
        fetch(CFG.url, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.success) {
                    // Straight to the new ticket when the server handed us one.
                    // It may deliberately omit the URL (e.g. an internal report
                    // by a staff member without helpdesk access) — confirm in
                    // place instead of navigating to "undefined".
                    if (res.url) {
                        window.location.href = res.url;
                    } else {
                        showSuccess(res.message || '');
                    }
                } else {
                    setSubmitting(false);
                    showError((res && res.message) || CFG.i18n.err_generic);
                }
            })
            .catch(function () {
                setSubmitting(false);
                showError(CFG.i18n.err_generic);
            });
    }

    function setSubmitting(on) {
        el.submit.disabled = on;
        el.submit.innerHTML = on
            ? '<span class="pst-spin"></span> ' + esc(CFG.i18n.sending)
            : '<i class="fa-solid fa-paper-plane"></i> ' + esc(CFG.i18n.submit);
    }

    // ── Utils ──────────────────────────────────────────────────────────────
    function dataURLtoBlob(dataURL) {
        var parts = dataURL.split(','), mime = parts[0].match(/:(.*?);/)[1];
        var bin = atob(parts[1]), n = bin.length, arr = new Uint8Array(n);
        while (n--) { arr[n] = bin.charCodeAt(n); }
        return new Blob([arr], { type: mime });
    }

    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ── Public trigger (used by the in-page guide button) ──────────────────
    window.PstSmartTicket = { open: open };

    // Keep canvas in sync with viewport while annotating.
    window.addEventListener('resize', function () {
        if (el.stage && el.stage.classList.contains('is-active')) { sizeCanvas(); redraw(); }
    });

    // Build the FAB as soon as the DOM is ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', build);
    } else {
        build();
    }
})();
