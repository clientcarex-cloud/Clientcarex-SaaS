/* SHRA admin — rider picker, billing, attendance */
(function ($) {
  'use strict';

  window.SHRA = window.SHRA || {};
  var S = window.SHRA;

  S.money = function (n) {
    n = parseFloat(n || 0);
    var cur = S.currency || { symbol: '', placement: 'before', decimals: 2 };
    var whole = Math.abs(n - Math.round(n)) < 0.005;
    var s = n.toLocaleString(undefined, { minimumFractionDigits: whole ? 0 : cur.decimals, maximumFractionDigits: cur.decimals });
    return cur.placement === 'after' ? s + cur.symbol : cur.symbol + s;
  };

  /**
   * jQuery's $.ajaxSetup CSRF data (an object) is overwritten whenever a call passes
   * its own `data`, and a serialized string cannot be merged into it — so every
   * $.post that sends form.serialize() must append the token itself or it gets a 419.
   */
  S.csrf = function (serialized) {
    if (typeof csrfData === 'undefined' || !csrfData.token_name) { return serialized; }
    var t = encodeURIComponent(csrfData.token_name) + '=' + encodeURIComponent(csrfData.hash);
    return serialized ? serialized + '&' + t : t;
  };

  S.age = function (dob) {
    if (!dob) { return null; }
    var d = new Date(dob + 'T00:00:00');
    if (isNaN(d)) { return null; }
    var t = new Date(), a = t.getFullYear() - d.getFullYear();
    if (t.getMonth() < d.getMonth() || (t.getMonth() === d.getMonth() && t.getDate() < d.getDate())) { a--; }
    return a;
  };

  S.initials = function (name) {
    return (name || '').split(/\s+/).slice(0, 2).map(function (p) { return p.charAt(0); }).join('').toUpperCase();
  };

  S.esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  /**
   * Rider search picker.
   * $input: text input, $results: dropdown container, onPick(rider)
   */
  S.riderPicker = function ($input, $results, onPick) {
    var timer = null, cache = {}, hl = -1, items = [];

    function render(list) {
      items = list; hl = -1;
      if (!list.length) {
        var q = $.trim($input.val());
        var digits = q.replace(/\D+/g, '');
        if (digits.length && digits.length < 5 && digits.length >= q.length - 2) {
          $results.html('<div class="none">Keep typing — mobile matches appear after 5 digits.</div>').addClass('open');
          return;
        }
        $results.html('<div class="none">No rider found. ' + ($('#shra-quick').length ? '<a href="#" class="shra-quick-open" data-q="' + S.esc(q) + '">Quick add "' + S.esc(q) + '"</a> · ' : '') + '<a href="' + S.urls.newRider + '" target="_blank">Full form</a></div>').addClass('open');
        return;
      }
      var html = list.map(function (r, i) {
        var left = r.sessions_left > 0 ? '<span class="shra-badge shra-badge-green">' + r.sessions_left + ' left</span>' : '';
        return '<div class="item" data-i="' + i + '"><span class="shra-avatar">' + S.esc(S.initials(r.full_name)) + '</span>' +
          '<div class="body"><div class="name">' + S.esc(r.full_name) + ' <span class="no">' + S.esc(r.rider_no) + '</span></div>' +
          '<div class="meta">' + S.esc(r.mobile) + ' · ' + (r.age !== null && r.age !== undefined ? r.age + ' yrs · ' : '') + S.esc(r.rider_type === 'guest' ? 'Guest' : 'Learner') + ' · ' + S.esc(r.riding_level || '') + '</div></div>' + left + '</div>';
      }).join('');
      $results.html(html).addClass('open');
    }

    function search(q) {
      if (cache[q]) { render(cache[q]); return; }
      $.getJSON(S.urls.search, { q: q }, function (res) {
        cache[q] = res.riders || [];
        render(cache[q]);
      });
    }

    $input.on('input focus', function () {
      var q = $.trim($input.val());
      clearTimeout(timer);
      timer = setTimeout(function () { search(q); }, 180);
    });

    $input.on('keydown', function (e) {
      if (!$results.hasClass('open')) return;
      if (e.key === 'ArrowDown') { hl = Math.min(items.length - 1, hl + 1); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { hl = Math.max(0, hl - 1); e.preventDefault(); }
      else if (e.key === 'Enter') { if (items[hl]) { pick(items[hl]); } e.preventDefault(); return; }
      else if (e.key === 'Escape') { $results.removeClass('open'); return; }
      $results.find('.item').removeClass('hl').eq(hl).addClass('hl');
    });

    $results.on('click', '.item', function () { pick(items[$(this).data('i')]); });

    $(document).on('click', function (e) {
      if (!$(e.target).closest($results).length && !$(e.target).is($input)) { $results.removeClass('open'); }
    });

    function pick(r) {
      $results.removeClass('open');
      $input.val('');
      onPick(r);
    }
  };

  /* ───────── Billing page ───────── */
  S.billing = function (cfg) {
    var rider = null, pkg = null;
    var $picked = $('#shra-picked'), $pkgs = $('#shra-pkgs'), $sum = $('#shra-summary');
    var $discount = $('#shra-discount'), $paid = $('#shra-paid'), $btn = $('#shra-pay');

    S.riderPicker($('#shra-rider-q'), $('#shra-rider-results'), setRider);

    function showPicked(r) {
      var aud = (r.age !== null && r.age !== undefined && r.age < cfg.minorAge) ? 'children' : 'adults';
      $picked.html('<span class="shra-avatar">' + S.esc(S.initials(r.full_name)) + '</span><div><div style="font-weight:700">' + S.esc(r.full_name) + ' <span class="shra-muted" style="font-weight:400;font-size:12px">' + S.esc(r.rider_no) + '</span></div>' +
        '<div class="shra-muted" style="font-size:12px">' + S.esc(r.mobile) + ' · ' + (r.age !== null && r.age !== undefined ? r.age + ' yrs · ' : '') + (aud === 'children' ? 'Children' : 'Adults') + ' pricing · ' + S.esc(r.rider_type === 'guest' ? 'Guest rider' : 'Learner') + '</div></div>' +
        '<span class="x" title="Change rider"><i class="fa fa-times"></i></span>').show();
      $('#shra-rider-wrap').hide();
      return aud;
    }

    function setRider(r) {
      rider = r;
      var aud = showPicked(r);
      $('input[name=audience][value=' + aud + ']').prop('checked', true);
      $('#shra-rider-id').val(r.id);
      $('#shra-bill-force').val(0);
      $(document).trigger('shra:riderPicked', [r]);
      var flags = [];
      if (+r.attended_today > 0) { flags.push('<span class="shra-badge shra-badge-green"><i class="fa fa-check"></i> Attended today</span>'); }
      if (+r.sessions_left > 0) { flags.push('<span class="shra-badge shra-badge-gold">' + r.sessions_left + ' session' + (+r.sessions_left === 1 ? '' : 's') + ' still unused</span>'); }
      if (parseFloat(r.total_due) > 0.009) { flags.push('<span class="shra-badge shra-badge-red">Owes ' + S.money(r.total_due) + '</span>'); }
      $('#shra-rider-flags').html(flags.length ? '<div class="shra-alert shra-alert-warn" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><span style="font-weight:600">Heads-up:</span>' + flags.join(' ') + (+r.sessions_left > 0 ? '<a href="' + cfg.urls.attendance + '?rider=' + r.id + '" style="margin-left:auto">Mark a session instead →</a>' : '') + '</div>' : '');
      renderPkgs();
      pkg = null; summary();
      // Plan chosen on the self-registration form → preselect it
      if (r.preferred_package_id && cfg.packages[r.preferred_package_id]) {
        var $pp = $pkgs.find('.shra-pkg[data-id="' + r.preferred_package_id + '"]');
        if ($pp.length) {
          var a = cfg.packages[r.preferred_package_id].audience;
          $('input[name=audience][value=' + a + ']').prop('checked', true); renderPkgs();
          $pp.trigger('click');
        }
      }
      // Start date & batch asked for on the booking form → preselect those too
      if (r.preferred_start_date) { $('#shra-start-date').val(r.preferred_start_date); }
      if (r.preferred_batch) { $('#shra-bill-form input[name=batch][value="' + r.preferred_batch + '"]').prop('checked', true); }
      summary();
    }

    // Quick add (name + mobile) — the rider is created automatically on Collect & bill
    var $quick = $('#shra-quick');
    $('#shra-quick-toggle').on('click', function (e) { e.preventDefault(); $quick.slideToggle(120); $('#shra-quick-name').focus(); });
    $(document).on('click', '.shra-quick-open', function (e) {
      e.preventDefault();
      var q = $(this).data('q') || '';
      $quick.show();
      if (/^[\d+ -]+$/.test(q)) { $('#shra-quick-mobile').val(String(q).replace(/\D+/g, '').slice(0, 10)); $('#shra-quick-name').focus(); } else { $('#shra-quick-name').val(q); $('#shra-quick-mobile').focus(); }
      $('#shra-rider-results').removeClass('open');
      summary();
    });
    $('#shra-quick-mobile').on('input', function () {
      var v = this.value.replace(/\D+/g, '').slice(0, 10);
      if (this.value !== v) { this.value = v; }
      summary();
    });
    $('#shra-quick-name').on('input', summary);
    $('#shra-quick-dob').on('change', function () {
      var a = S.age(this.value);
      if (this.value && (a === null || a < 5)) {
        alert_float('warning', 'Riders must be at least 5 years old.');
        this.value = '';
        return;
      }
      if (this.value) {
        var $r = $('input[name=audience][value=' + (a < cfg.minorAge ? 'children' : 'adults') + ']');
        if (!$r.prop('checked')) { $r.prop('checked', true).trigger('change'); }
      }
    });

    function quickReady() {
      return $quick.is(':visible') && $.trim($('#shra-quick-name').val()) !== '' && /^\d{10}$/.test($('#shra-quick-mobile').val());
    }

    function adoptQuickRider(r, existing) {
      rider = r;
      $('#shra-rider-id').val(r.id);
      $('#shra-bill-force').val(0);
      showPicked(r);
      $quick.hide();
      $('#shra-quick-name, #shra-quick-mobile, #shra-quick-dob').val('');
      alert_float('success', existing ? 'Existing rider selected.' : 'Rider added.');
      $(document).trigger('shra:riderPicked', [r]);
    }

    $picked.on('click', '.x', function () {
      rider = null; pkg = null;
      $('#shra-rider-flags').empty(); $('#shra-bill-force').val(0);
      $picked.hide(); $('#shra-rider-wrap').show(); $('#shra-rider-q').focus();
      $pkgs.find('.shra-pkg').removeClass('selected');
      summary();
    });

    $('input[name=audience]').on('change', function () { renderPkgs(); pkg = null; summary(); });

    function renderPkgs() {
      var aud = $('input[name=audience]:checked').val();
      $pkgs.find('.shra-pkg').each(function () {
        $(this).toggle($(this).data('audience') === aud).removeClass('selected');
      });
    }

    $pkgs.on('click', '.shra-pkg', function () {
      $pkgs.find('.shra-pkg').removeClass('selected');
      $(this).addClass('selected');
      pkg = cfg.packages[$(this).data('id')];
      $('#shra-package-id').val(pkg.id);
      if (+pkg.is_guest === 1) { $('#shra-bill-form input[name=batch][value=""]').prop('checked', true); }
      // Guest rides ride now — preselect "Mark 1st session now" unless the user chose otherwise
      var $mark = $('#shra-bill-form input[name=mark_now]');
      if (!$mark.data('touched')) { $mark.prop('checked', +pkg.is_guest === 1); }
      if (!$discount.data('touched')) { $discount.val(cfg.offer.active ? cfg.offer.percent : 0); }
      summary();
    });

    $discount.on('input', function () { $(this).data('touched', true); summary(); });
    $paid.on('input', function () { $(this).data('touched', true); summary(); });
    $('#shra-bill-form input[name=mark_now]').on('change', function () { $(this).data('touched', true); });

    // Reference input only applies to UPI payments
    function upiToggle() {
      var isUpi = /upi/i.test($('#shra-mode option:selected').text());
      $('#shra-ref-wrap').toggle(isUpi);
      if (!isUpi) { $('#shra-ref').val(''); }
    }
    $('#shra-mode').on('change', upiToggle);
    upiToggle();

    function scheduleLine() {
      var d = $('#shra-start-date').val(), $b = $('#shra-bill-form input[name=batch]:checked'), out = [];
      if (d) {
        var dt = new Date(d + 'T00:00:00');
        out.push('Starts ' + (isNaN(dt) ? d : dt.toLocaleDateString(undefined, { weekday: 'short', day: '2-digit', month: 'short' })));
      }
      if ($b.length && $b.val()) { out.push($b.data('label')); }
      return out.join(' · ');
    }
    $(document).on('change', '#shra-start-date, #shra-bill-form input[name=batch]', summary);

    function summary() {
      // Schedule (start date + batch) shows only once a non-guest package is chosen
      $('#shra-schedule').toggle(!!pkg && +pkg.is_guest !== 1);
      if (!pkg) {
        $sum.html('<div class="shra-empty" style="padding:30px 10px"><i class="fa-solid fa-horse"></i>Pick a rider and a package</div>');
        $btn.prop('disabled', true);
        return;
      }
      var d = Math.max(0, Math.min(100, parseFloat($discount.val()) || 0));
      var list = parseFloat(pkg.price), disc = Math.round(list * d) / 100, total = Math.round((list - disc) * 100) / 100;
      $paid.attr('max', total.toFixed(2)).attr('placeholder', 'Enter amount (max ' + S.money(total) + ')');
      var raw = $.trim($paid.val());
      var entered = raw !== '';
      var paid = entered ? Math.max(0, Math.min(total, parseFloat(raw) || 0)) : 0;
      var due = Math.round((total - paid) * 100) / 100;
      $sum.html(
        '<div class="row-line"><span>' + S.esc(pkg.name) + ' <span class="shra-muted">(' + S.esc(pkg.audience) + ')</span></span><span>' + S.money(list) + '</span></div>' +
        '<div class="row-line"><span>' + pkg.sessions + ' session' + (pkg.sessions > 1 ? 's' : '') + ' × ' + pkg.duration_min + ' min</span><span class="shra-muted">' + S.money(pkg.per_session) + ' / session</span></div>' +
        (d > 0 ? '<div class="row-line" style="color:#a8322d"><span>Discount ' + d + '%</span><span>− ' + S.money(disc) + '</span></div>' : '') +
        (scheduleLine() ? '<div class="row-line"><span><i class="fa-solid fa-calendar-day"></i> Schedule</span><span class="shra-muted">' + S.esc(scheduleLine()) + '</span></div>' : '') +
        '<div class="row-line total"><span>You pay</span><span class="amt">' + S.money(total) + '</span></div>' +
        (!entered ? '<div class="shra-alert shra-alert-warn" style="margin-top:8px">Enter the amount received to continue.</div>' :
          (due > 0 ? '<div class="shra-alert shra-alert-warn" style="margin-top:8px">Partial payment — ' + S.money(due) + ' will stay due on the invoice.</div>' : ''))
      );
      $btn.prop('disabled', (!rider && !quickReady()) || !entered).find('.amt').text(entered ? S.money(paid) : '');
    }

    var inflight = false;
    function newToken() {
      var t = ''; for (var i = 0; i < 24; i++) { t += 'abcdefghijklmnopqrstuvwxyz0123456789'.charAt(Math.floor(Math.random() * 36)); }
      $('#shra-bill-token').val(t);
    }
    $('#shra-bill-form').on('submit', function (e) {
      e.preventDefault();
      if (inflight || !pkg) return;
      var $form = $(this);
      if (!rider) {
        // Quick-add fields filled instead of picking a rider — create them first, then bill.
        if (!quickReady()) return;
        inflight = true;
        $btn.prop('disabled', true).addClass('disabled');
        $.post(cfg.urls.quick, { full_name: $('#shra-quick-name').val(), mobile: $('#shra-quick-mobile').val(), dob: $('#shra-quick-dob').val(), rider_type: $('#shra-quick-type').val() }, function (res) {
          inflight = false;
          if (!res.success) { alert_float('danger', res.message || 'Could not add the rider.'); $btn.removeClass('disabled').prop('disabled', false); return; }
          adoptQuickRider(res.rider, res.existing);
          $form.trigger('submit');
        }, 'json').fail(function () { inflight = false; alert_float('danger', 'Request failed.'); $btn.removeClass('disabled').prop('disabled', false); });
        return;
      }
      inflight = true;
      $btn.prop('disabled', true).addClass('disabled');
      $.post(cfg.urls.bill, S.csrf($form.serialize()), function (res) {
        inflight = false;
        if (res.success) {
          $('#shra-bill-done').html(res.html).show();
          if (res.duplicate) { alert_float('warning', 'That bill was already created — nothing was charged twice.'); }
          $form[0].reset(); $discount.data('touched', false); $paid.data('touched', false);
          $('#shra-bill-form input[name=mark_now]').data('touched', false);
          upiToggle();
          newToken(); $('#shra-bill-force').val(0);
          rider = null; pkg = null;
          $picked.hide(); $('#shra-rider-wrap').show(); $('#shra-rider-flags').empty();
          $pkgs.find('.shra-pkg').removeClass('selected');
          summary();
          $('html,body').animate({ scrollTop: $('#shra-bill-done').offset().top - 80 }, 200);
        } else if (res.needs_confirm) {
          $btn.removeClass('disabled').prop('disabled', false);
          if (confirm(res.message)) { $('#shra-bill-force').val(1); $form.trigger('submit'); }
        } else {
          alert_float('danger', res.message || 'Could not create the bill.');
          $btn.removeClass('disabled').prop('disabled', false);
        }
      }, 'json').fail(function () { inflight = false; alert_float('danger', 'Request failed.'); $btn.removeClass('disabled').prop('disabled', false); });
    });
    S.collectInit({ url: cfg.urls.collect });

    summary();
    if (cfg.preselect) { setRider(cfg.preselect); }
  };

  /* ───────── Attendance page ───────── */
  S.attendance = function (cfg) {
    var rider = null, enrollment = null;
    var $panel = $('#shra-att-panel'), $list = $('#shra-enr-list'), $mark = $('#shra-mark');

    S.riderPicker($('#shra-att-q'), $('#shra-att-results'), load);

    if (cfg.preselect) { load(cfg.preselect); }

    function load(r) {
      rider = r; enrollment = null;
      $('#shra-att-rider').html('<span class="shra-avatar">' + S.esc(S.initials(r.full_name)) + '</span><div><div style="font-weight:700">' + S.esc(r.full_name) + ' <span class="shra-muted" style="font-weight:400;font-size:12px">' + S.esc(r.rider_no) + '</span></div><div class="shra-muted" style="font-size:12px">' + S.esc(r.mobile) + ' · ' + S.esc(r.riding_level || '') + '</div></div><span class="x" title="Change"><i class="fa fa-times"></i></span>');
      $panel.show();
      $list.html('<div class="shra-muted" style="padding:10px">Loading…</div>');
      $.getJSON(cfg.urls.enrollments, { rider_id: r.id }, function (res) {
        if (!res.enrollments.length) {
          $list.html('<div class="shra-alert shra-alert-warn">No active package with sessions left. <a href="' + cfg.urls.billing + '?rider=' + r.id + '">Bill a package</a> first.</div>');
          $mark.prop('disabled', true);
          return;
        }
        $list.html(res.enrollments.map(function (e, i) {
          var left = e.sessions_total - e.sessions_used, pct = Math.round(e.sessions_used / e.sessions_total * 100);
          return '<div class="shra-enr' + (i === 0 ? ' selected' : '') + '" data-id="' + e.id + '"><div class="t"><span>' + S.esc(e.package_name) + ' <span class="shra-muted" style="font-weight:400">· ' + S.esc(e.audience) + '</span></span><span class="shra-badge shra-badge-green">' + left + ' left</span></div>' +
            '<div class="s">Session ' + e.sessions_used + ' of ' + e.sessions_total + ' · ' + e.duration_min + ' min' + (e.expires_at ? ' · valid till ' + S.esc(e.expires_at_f) : '') + ' · ' + S.esc(e.enrollment_no) + '</div>' +
            '<div class="shra-progress" style="margin-top:8px"><span style="width:' + pct + '%"></span></div></div>';
        }).join(''));
        enrollment = res.enrollments[0];
        $mark.prop('disabled', false);
        $('#shra-enrollment-id').val(enrollment.id);
        $('#shra-next-no').text(parseInt(enrollment.sessions_used, 10) + 1);
      });
    }

    $list.on('click', '.shra-enr', function () {
      $list.find('.shra-enr').removeClass('selected'); $(this).addClass('selected');
      $('#shra-enrollment-id').val($(this).data('id'));
      var used = parseInt($(this).find('.s').text().match(/Session (\d+)/)[1], 10);
      $('#shra-next-no').text(used + 1);
    });

    $('#shra-att-rider').on('click', '.x', function () { $panel.hide(); rider = null; $('#shra-att-q').focus(); });

    var marking = false;
    $('#shra-att-form').on('submit', function (e) {
      e.preventDefault();
      if (marking) return;
      marking = true;
      $mark.prop('disabled', true);
      var $form = $(this);
      $.post(cfg.urls.mark, S.csrf($form.serialize()), function (res) {
        marking = false;
        if (res.success) {
          alert_float('success', res.message);
          if (res.completed) { $('#shra-att-done').html(res.html).show(); }
          $('#shra-today').html(res.today_html);
          $('#shra-att-force').val(0);
          load(rider);
          $('#shra-horse').val(''); $('#shra-note').val('');
        } else if (res.needs_confirm) {
          $mark.prop('disabled', false);
          if (confirm(res.message)) { $('#shra-att-force').val(1); $form.trigger('submit'); } else { $('#shra-att-force').val(0); }
        } else {
          alert_float('danger', res.message || 'Could not mark the session.');
          $mark.prop('disabled', false);
        }
      }, 'json').fail(function () { marking = false; alert_float('danger', 'Request failed.'); $mark.prop('disabled', false); });
    });

    $(document).on('click', '.shra-undo', function () {
      var id = $(this).data('id');
      if (!confirm('Undo this session? Only the latest session of a package can be undone.')) return;
      $.post(cfg.urls.undo, { id: id }, function (res) {
        if (res.success) { $('#shra-today').html(res.today_html); if (rider) load(rider); alert_float('success', 'Session removed.'); }
        else { alert_float('danger', res.message || 'Could not undo.'); }
      }, 'json');
    });
  };

  /* ───────── Collect balance (modal) ───────── */
  S.collectInit = function (cfg) {
    if (S._collectReady) return;
    S._collectReady = true;
    var $m = $('#shra-collect-modal');
    if (!$m.length) return;
    var busy = false;
    $(document).on('click', '.shra-collect', function () {
      var due = parseFloat($(this).data('due')) || 0;
      $('#sc-id').val($(this).data('id'));
      $('#sc-name').text($(this).data('name') || '');
      $('#sc-due-label').text(S.money(due));
      $('#sc-amount').val(due.toFixed(2)).attr('max', due.toFixed(2));
      $m.modal('show');
      setTimeout(function () { $('#sc-amount').focus().select(); }, 300);
    });
    $('#shra-collect-form').on('submit', function (e) {
      e.preventDefault();
      if (busy) return;
      var max = parseFloat($('#sc-amount').attr('max')) || 0, amt = parseFloat($('#sc-amount').val()) || 0;
      if (amt <= 0) { alert_float('danger', 'Enter an amount.'); return; }
      if (amt > max + 0.009) { alert_float('danger', 'Amount exceeds the balance due (' + S.money(max) + ').'); return; }
      busy = true; $('#sc-save').prop('disabled', true);
      $.post(cfg.url, S.csrf($(this).serialize()), function (res) {
        busy = false; $('#sc-save').prop('disabled', false);
        if (!res.success) { alert_float('danger', res.message || 'Could not record the payment.'); return; }
        $m.modal('hide');
        alert_float('success', res.message);
        if (res.receipt) { window.open(res.receipt, '_blank'); }
        setTimeout(function () { location.reload(); }, 600);
      }, 'json').fail(function () { busy = false; $('#sc-save').prop('disabled', false); alert_float('danger', 'Request failed.'); });
    });
  };

  /* ───────── Bulk select & delete (superadmin only — the bar is only rendered for them) ─────────
     A page opts in by printing #shra-bulkbar with data-url / data-confirm; the row
     checkboxes are .shra-bulk-cb (value = id) and each table head carries one
     .shra-bulk-all. Filtered-out rows (.is-off) never count, and the leads work
     list drops their ticks via the shra:filtered event. */
  S.bulkInit = function () {
    var $bar = $('#shra-bulkbar');
    if (!$bar.length || S._bulkReady) { return; }
    S._bulkReady = true;
    var busy = false;

    function boxes() { return $('.shra-bulk-cb').filter(function () { return $(this).closest('tr').is(':visible'); }); }
    function ids() {
      var seen = {}, out = [];
      boxes().filter(':checked').each(function () { if (!seen[this.value]) { seen[this.value] = 1; out.push(this.value); } });
      return out;
    }

    function sync() {
      var n = ids().length;
      $bar.prop('hidden', !n).find('.shra-bulk-count').text(n);
      $('.shra-bulk-cb').each(function () { $(this).closest('tr').toggleClass('shra-row-picked', this.checked); });
      $('.shra-bulk-all').each(function () {
        var $t = $(this).closest('table').find('.shra-bulk-cb').filter(function () { return $(this).closest('tr').is(':visible'); });
        this.checked = $t.length > 0 && $t.filter(':checked').length === $t.length;
      });
    }

    $(document).on('change', '.shra-bulk-all', function () {
      var on = this.checked;
      $(this).closest('table').find('.shra-bulk-cb').filter(function () { return $(this).closest('tr').is(':visible'); }).prop('checked', on);
      sync();
    });
    $(document).on('change', '.shra-bulk-cb', sync);
    $(document).on('shra:filtered', function () {
      $('.shra-bulk-cb').filter(function () { return $(this).closest('tr').hasClass('is-off'); }).prop('checked', false);
      sync();
    });
    $bar.on('click', '.shra-bulk-clear', function () { $('.shra-bulk-cb, .shra-bulk-all').prop('checked', false); sync(); });

    $bar.on('click', '.shra-bulk-del', function () {
      var list = ids();
      if (!list.length || busy) { return; }
      if (!confirm(String($bar.data('confirm') || 'Permanently delete {n} records? This cannot be undone.').replace('{n}', list.length))) { return; }
      busy = true;
      var $b = $(this).prop('disabled', true), html = $b.html();
      $b.html('<i class="fa fa-circle-notch fa-spin"></i> Deleting…');
      $.post($bar.data('url'), S.csrf($.param({ ids: list })), function (res) {
        if (res.success) {
          alert_float('success', res.message);
          setTimeout(function () { location.reload(); }, 600);
        } else {
          busy = false; $b.prop('disabled', false).html(html);
          alert_float('danger', res.message || 'Could not delete.');
        }
      }, 'json').fail(function () { busy = false; $b.prop('disabled', false).html(html); alert_float('danger', 'Request failed.'); });
    });
  };
  $(S.bulkInit);

  /* ───────── No "Leave site?" prompts on SHRA pages ─────────
     Perfex binds jquery.are-you-sure to every admin form; the counter screens
     are transactional (saved via AJAX or one click), so the prompt only annoys. */
  function killUnloadPrompt() {
    $('form').removeClass('dirty').off('.areYouSure').find('input,select,textarea').attr('data-ays-ignore', 'true');
    $(window).off('beforeunload');
    window.onbeforeunload = null;
  }
  $(killUnloadPrompt);
  $(window).on('load', killUnloadPrompt);
  $(document).on('change input', 'form', function () { $(this).removeClass('dirty'); });

  /* ───────── Small helpers ───────── */
  $(document).on('click', '.shra-copy', function () {
    var t = $(this).data('copy');
    if (navigator.clipboard) { navigator.clipboard.writeText(t); alert_float('success', 'Copied'); }
  });

  $(document).on('click', '[data-shra-confirm]', function (e) {
    if (!confirm($(this).data('shra-confirm'))) { e.preventDefault(); }
  });

})(jQuery);
