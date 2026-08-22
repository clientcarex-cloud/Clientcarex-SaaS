/**
 * CCX Date Range Picker (vanilla JS — no jQuery dependency, admin pages
 * load jQuery in the footer so this must not rely on it).
 *
 * Markup is rendered by render_date_range_input() (ccx_daterange_helper.php).
 * Auto-initializes every .ccx-drp on the page; safe to load mid-body.
 *
 * Public API:
 *   CcxDateRange.initAll()   — init any .ccx-drp added after load
 *   CcxDateRange.sync(el)    — re-read the hidden inputs of a wrapper (or
 *                              wrapper id) and refresh the visible label,
 *                              for pages that clear filters programmatically
 *
 * On commit the picker writes Y-m-d into both hidden inputs and dispatches
 * bubbling 'change' events on them plus 'ccx:daterange:change' on the wrapper.
 */
(function () {
    'use strict';

    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var DOWS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    function today() { var d = new Date(); d.setHours(0, 0, 0, 0); return d; }
    function addDays(d, n) { var c = new Date(d); c.setDate(c.getDate() + n); return c; }
    function ymd(d) {
        return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
    }
    function parseYmd(s) {
        var m = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(s || '');
        return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null;
    }
    function fmt(d) { return d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear(); }

    var PRESETS = [
        { name: 'Today', range: function () { var t = today(); return [t, t]; } },
        { name: 'Yesterday', range: function () { var y = addDays(today(), -1); return [y, y]; } },
        { name: 'Today and yesterday', range: function () { return [addDays(today(), -1), today()]; } },
        { name: 'Last 7 days', range: function () { return [addDays(today(), -6), today()]; } },
        { name: 'Last 14 days', range: function () { return [addDays(today(), -13), today()]; } },
        { name: 'Last 28 days', range: function () { return [addDays(today(), -27), today()]; } },
        { name: 'Last 30 days', range: function () { return [addDays(today(), -29), today()]; } },
        { name: 'This month', range: function () { var t = today(); return [new Date(t.getFullYear(), t.getMonth(), 1), t]; } },
        { name: 'Last month', range: function () { var t = today(); return [new Date(t.getFullYear(), t.getMonth() - 1, 1), new Date(t.getFullYear(), t.getMonth(), 0)]; } }
    ];

    function Picker(wrap) {
        this.wrap = wrap;
        this.fromInput = wrap.querySelector('.ccx-drp-from');
        this.toInput = wrap.querySelector('.ccx-drp-to');
        this.labelEl = wrap.querySelector('.ccx-drp-label');
        this.panel = wrap.querySelector('.ccx-drp-panel');
        this.presetsEl = wrap.querySelector('.ccx-drp-presets');
        this.calLeft = wrap.querySelector('.ccx-drp-cal-left');
        this.calRight = wrap.querySelector('.ccx-drp-cal-right');
        this.hintEl = wrap.querySelector('.ccx-drp-hint');
        this.placeholder = wrap.getAttribute('data-label') || 'Date range';
        // Future dates are NOT selectable unless the page opts in
        this.allowFuture = wrap.hasAttribute('data-allow-future');

        this.selStart = parseYmd(this.fromInput.value);
        this.selEnd = parseYmd(this.toInput.value);
        this.hoverDate = null;
        this.viewMonth = null; // left calendar month; right is viewMonth + 1

        this.bind();
        this.updateLabel();
    }

    Picker.prototype.matchedPreset = function () {
        if (!this.selStart || !this.selEnd) return null;
        for (var i = 0; i < PRESETS.length; i++) {
            var r = PRESETS[i].range();
            if (ymd(r[0]) === ymd(this.selStart) && ymd(r[1]) === ymd(this.selEnd)) return PRESETS[i].name;
        }
        return null;
    };

    Picker.prototype.updateLabel = function () {
        var label = this.placeholder;
        var preset = this.matchedPreset();
        if (preset) {
            label = preset;
        } else if (this.selStart && this.selEnd) {
            label = ymd(this.selStart) === ymd(this.selEnd)
                ? fmt(this.selStart)
                : fmt(this.selStart) + ' – ' + fmt(this.selEnd);
        } else if (this.selStart) {
            label = fmt(this.selStart) + ' – …';
        }
        this.labelEl.textContent = label;
    };

    Picker.prototype.renderPresets = function () {
        var active = this.matchedPreset();
        var html = '';
        for (var i = 0; i < PRESETS.length; i++) {
            html += '<div class="ccx-drp-preset' + (PRESETS[i].name === active ? ' active' : '') + '" data-preset="' + i + '">'
                + '<span class="ccx-drp-radio"></span>' + PRESETS[i].name + '</div>';
        }
        this.presetsEl.innerHTML = html;
    };

    Picker.prototype.renderCalendar = function (el, monthDate, isLeft) {
        var y = monthDate.getFullYear(), m = monthDate.getMonth();
        var t = today();
        var rangeA = this.selStart, rangeB = this.selEnd;
        // While picking the end date, preview the hovered range
        if (this.selStart && !this.selEnd && this.hoverDate) {
            rangeA = this.selStart <= this.hoverDate ? this.selStart : this.hoverDate;
            rangeB = this.selStart <= this.hoverDate ? this.hoverDate : this.selStart;
        }
        var html = '<div class="ccx-drp-cal-head">'
            + (isLeft ? '<button type="button" class="ccx-drp-nav" data-nav="-1">&#8249;</button>' : '<span class="ccx-drp-nav-spacer"></span>')
            + '<span class="ccx-drp-cal-title">' + MONTHS[m] + ' ' + y + '</span>'
            + (!isLeft ? '<button type="button" class="ccx-drp-nav" data-nav="1">&#8250;</button>' : '<span class="ccx-drp-nav-spacer"></span>')
            + '</div><div class="ccx-drp-grid">';
        for (var w = 0; w < 7; w++) html += '<div class="ccx-drp-dow">' + DOWS[w] + '</div>';
        var firstDow = new Date(y, m, 1).getDay();
        var daysInMonth = new Date(y, m + 1, 0).getDate();
        for (var b = 0; b < firstDow; b++) html += '<div class="ccx-drp-day empty"></div>';
        for (var day = 1; day <= daysInMonth; day++) {
            var d = new Date(y, m, day);
            var cls = 'ccx-drp-day';
            if (d > t) {
                cls += ' future';
                if (!this.allowFuture) cls += ' disabled';
            }
            if (rangeA && rangeB && d >= rangeA && d <= rangeB) cls += ' in-range';
            if (rangeA && ymd(d) === ymd(rangeA)) cls += ' range-start';
            if (rangeB && ymd(d) === ymd(rangeB)) cls += ' range-end';
            if ((this.selStart && ymd(d) === ymd(this.selStart)) || (this.selEnd && ymd(d) === ymd(this.selEnd))) cls += ' sel';
            html += '<div class="' + cls + '" data-date="' + ymd(d) + '">' + day + '</div>';
        }
        html += '</div>';
        el.innerHTML = html;
    };

    Picker.prototype.renderCalendars = function () {
        this.renderCalendar(this.calLeft, this.viewMonth, true);
        this.renderCalendar(this.calRight, new Date(this.viewMonth.getFullYear(), this.viewMonth.getMonth() + 1, 1), false);
    };

    /**
     * Hover preview MUST NOT rebuild the calendar HTML: replacing the cell
     * under the cursor between mouseover and mousedown means mousedown lands
     * on the container and the browser never fires the click that picks the
     * end date. Toggle range classes on the existing cells instead.
     */
    Picker.prototype.updateRangeClasses = function () {
        var rangeA = this.selStart, rangeB = this.selEnd;
        if (this.selStart && !this.selEnd && this.hoverDate) {
            rangeA = this.selStart <= this.hoverDate ? this.selStart : this.hoverDate;
            rangeB = this.selStart <= this.hoverDate ? this.hoverDate : this.selStart;
        }
        var cells = this.panel.querySelectorAll('.ccx-drp-day[data-date]');
        for (var i = 0; i < cells.length; i++) {
            var d = parseYmd(cells[i].getAttribute('data-date'));
            var inRange = !!(rangeA && rangeB && d >= rangeA && d <= rangeB);
            cells[i].classList.toggle('in-range', inRange);
            cells[i].classList.toggle('range-start', !!(rangeA && ymd(d) === ymd(rangeA)));
            cells[i].classList.toggle('range-end', !!(rangeB && ymd(d) === ymd(rangeB)));
            cells[i].classList.toggle('sel', !!((this.selStart && ymd(d) === ymd(this.selStart)) || (this.selEnd && ymd(d) === ymd(this.selEnd))));
        }
    };

    Picker.prototype.render = function () {
        this.renderPresets();
        this.renderCalendars();
        this.hintEl.textContent = this.selStart && !this.selEnd ? 'Now pick the end date' : '';
        this.updateLabel();
    };

    Picker.prototype.commit = function () {
        this.fromInput.value = this.selStart ? ymd(this.selStart) : '';
        this.toInput.value = this.selEnd ? ymd(this.selEnd) : '';
        this.render();
        // Notify listeners (some pages auto-apply filters on change)
        var evOpts = { bubbles: true };
        this.fromInput.dispatchEvent(new Event('change', evOpts));
        this.toInput.dispatchEvent(new Event('change', evOpts));
        var custom;
        try {
            custom = new CustomEvent('ccx:daterange:change', {
                bubbles: true,
                detail: { from: this.fromInput.value, to: this.toInput.value }
            });
        } catch (e) {
            custom = document.createEvent('CustomEvent');
            custom.initCustomEvent('ccx:daterange:change', true, false, { from: this.fromInput.value, to: this.toInput.value });
        }
        this.wrap.dispatchEvent(custom);
    };

    Picker.prototype.open = function () {
        var anchor = this.selEnd || this.selStart || today();
        this.viewMonth = new Date(anchor.getFullYear(), anchor.getMonth() - 1, 1);
        this.hoverDate = null;
        this.render();
        this.panel.classList.add('open');
    };

    Picker.prototype.close = function () {
        this.panel.classList.remove('open');
        // Closed mid-selection: revert to the committed range so the label
        // never shows a dangling "date – …" that isn't really applied
        if (this.selStart && !this.selEnd) {
            this.selStart = parseYmd(this.fromInput.value);
            this.selEnd = parseYmd(this.toInput.value);
            this.hoverDate = null;
            this.updateLabel();
        }
    };

    Picker.prototype.sync = function () {
        this.selStart = parseYmd(this.fromInput.value);
        this.selEnd = parseYmd(this.toInput.value);
        this.hoverDate = null;
        this.updateLabel();
        if (this.panel.classList.contains('open')) this.render();
    };

    Picker.prototype.bind = function () {
        var self = this;

        this.wrap.querySelector('.ccx-drp-toggle').addEventListener('click', function (e) {
            e.stopPropagation();
            self.panel.classList.contains('open') ? self.close() : self.open();
        });

        this.wrap.addEventListener('click', function (e) {
            e.stopPropagation();

            var nav = e.target.closest ? e.target.closest('.ccx-drp-nav') : null;
            if (nav && self.wrap.contains(nav)) {
                self.viewMonth = new Date(self.viewMonth.getFullYear(), self.viewMonth.getMonth() + parseInt(nav.getAttribute('data-nav'), 10), 1);
                self.render();
                return;
            }

            var preset = e.target.closest ? e.target.closest('.ccx-drp-preset') : null;
            if (preset) {
                var r = PRESETS[parseInt(preset.getAttribute('data-preset'), 10)].range();
                self.selStart = r[0];
                self.selEnd = r[1];
                self.commit();
                self.close();
                return;
            }

            var clear = e.target.closest ? e.target.closest('.ccx-drp-clear') : null;
            if (clear) {
                e.preventDefault();
                self.selStart = self.selEnd = self.hoverDate = null;
                self.commit();
                self.close();
                return;
            }

            var day = e.target.closest ? e.target.closest('.ccx-drp-day') : null;
            if (day && !day.classList.contains('empty') && !day.classList.contains('disabled')) {
                var d = parseYmd(day.getAttribute('data-date'));
                if (!self.selStart || (self.selStart && self.selEnd)) {
                    self.selStart = d;
                    self.selEnd = null;
                    self.hoverDate = null;
                    self.render();
                } else {
                    if (d < self.selStart) { self.selEnd = self.selStart; self.selStart = d; } else { self.selEnd = d; }
                    self.commit();
                    self.close();
                }
            }
        });

        this.wrap.addEventListener('mouseover', function (e) {
            if (!self.selStart || self.selEnd) return;
            var day = e.target.closest ? e.target.closest('.ccx-drp-day') : null;
            if (!day || day.classList.contains('empty') || day.classList.contains('disabled')) return;
            var d = parseYmd(day.getAttribute('data-date'));
            if (self.hoverDate && ymd(d) === ymd(self.hoverDate)) return;
            self.hoverDate = d;
            self.updateRangeClasses();
        });
    };

    var registry = [];

    function initAll(root) {
        var nodes = (root || document).querySelectorAll('.ccx-drp:not([data-ccx-init])');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].setAttribute('data-ccx-init', '1');
            registry.push(new Picker(nodes[i]));
        }
    }

    document.addEventListener('click', function () {
        for (var i = 0; i < registry.length; i++) registry[i].close();
    });

    window.CcxDateRange = {
        initAll: initAll,
        sync: function (elOrId) {
            var el = typeof elOrId === 'string' ? document.getElementById(elOrId) : elOrId;
            for (var i = 0; i < registry.length; i++) {
                if (registry[i].wrap === el) { registry[i].sync(); return; }
            }
        },
        syncAll: function () {
            for (var i = 0; i < registry.length; i++) registry[i].sync();
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initAll(); });
    } else {
        initAll();
    }
})();
