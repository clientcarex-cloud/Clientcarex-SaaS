/* SHRA admin — rider picker, billing, attendance */
(function ($) {
  'use strict';

  window.SHRA = window.SHRA || {};
  var S = window.SHRA;

  S.money = function (n) {
    n = parseFloat(n || 0);
    var cur = S.currency || { symbol: '', placement: 'before', decimals: 2 };
    var s = n.toLocaleString(undefined, { minimumFractionDigits: cur.decimals, maximumFractionDigits: cur.decimals });
    return cur.placement === 'after' ? s + cur.symbol : cur.symbol + s;
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
        $results.html('<div class="none">No rider found. ' + ($('#shra-quick').length ? '<a href="#" class="shra-quick-open" data-q="' + S.esc(q) + '">Quick add "' + S.esc(q) + '"</a> · ' : '') + '<a href="' + S.urls.newRider + '" target="_blank">Full form</a></div>').addClass('open');
        return;
      }
      var html = list.map(function (r, i) {
        var left = r.sessions_left > 0 ? '<span class="shra-badge shra-badge-green">' + r.sessions_left + ' left</span>' : '';
        return '<div class="item" data-i="' + i + '"><span class="shra-avatar">' + S.esc(S.initials(r.full_name)) + '</span>' +
          '<div style="flex:1"><div class="name" style="font-weight:600">' + S.esc(r.full_name) + ' <span class="shra-muted" style="font-weight:400;font-size:12px">' + S.esc(r.rider_no) + '</span></div>' +
          '<div class="meta" style="font-size:11.5px;color:#7a6f5e">' + S.esc(r.mobile) + ' · ' + (r.age !== null && r.age !== undefined ? r.age + ' yrs · ' : '') + S.esc(r.rider_type === 'guest' ? 'Guest' : 'Learner') + ' · ' + S.esc(r.riding_level || '') + '</div></div>' + left + '</div>';
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

    function setRider(r) {
      rider = r;
      var aud = (r.age !== null && r.age !== undefined && r.age < cfg.minorAge) ? 'children' : 'adults';
      $picked.html('<span class="shra-avatar">' + S.esc(S.initials(r.full_name)) + '</span><div><div style="font-weight:700">' + S.esc(r.full_name) + ' <span class="shra-muted" style="font-weight:400;font-size:12px">' + S.esc(r.rider_no) + '</span></div>' +
        '<div class="shra-muted" style="font-size:12px">' + S.esc(r.mobile) + ' · ' + (r.age !== null && r.age !== undefined ? r.age + ' yrs · ' : '') + (aud === 'children' ? 'Children' : 'Adults') + ' pricing · ' + S.esc(r.rider_type === 'guest' ? 'Guest rider' : 'Learner') + '</div></div>' +
        '<span class="x" title="Change rider"><i class="fa fa-times"></i></span>').show();
      $('#shra-rider-wrap').hide();
      $('input[name=audience][value=' + aud + ']').prop('checked', true);
      $('#shra-rider-id').val(r.id);
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
    }

    // Quick add (name + mobile)
    var $quick = $('#shra-quick');
    $('#shra-quick-toggle').on('click', function (e) { e.preventDefault(); $quick.slideToggle(120); $('#shra-quick-name').focus(); });
    $(document).on('click', '.shra-quick-open', function (e) {
      e.preventDefault();
      var q = $(this).data('q') || '';
      $quick.show();
      if (/^[\d+ -]+$/.test(q)) { $('#shra-quick-mobile').val(q); $('#shra-quick-name').focus(); } else { $('#shra-quick-name').val(q); $('#shra-quick-mobile').focus(); }
      $('#shra-rider-results').removeClass('open');
    });
    $('#shra-quick-save').on('click', function () {
      var $b = $(this).prop('disabled', true);
      $.post(cfg.urls.quick, { full_name: $('#shra-quick-name').val(), mobile: $('#shra-quick-mobile').val(), dob: $('#shra-quick-dob').val(), rider_type: $('#shra-quick-type').val() }, function (res) {
        $b.prop('disabled', false);
        if (!res.success) { alert_float('danger', res.message || 'Could not add the rider.'); return; }
        alert_float('success', res.existing ? 'Existing rider selected.' : 'Rider added.');
        $('#shra-quick-name, #shra-quick-mobile, #shra-quick-dob').val('');
        $quick.hide();
        setRider(res.rider);
      }, 'json').fail(function () { $b.prop('disabled', false); alert_float('danger', 'Request failed.'); });
    });

    $picked.on('click', '.x', function () {
      rider = null; pkg = null;
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
      if (!$discount.data('touched')) { $discount.val(cfg.offer.active ? cfg.offer.percent : 0); }
      summary();
    });

    $discount.on('input', function () { $(this).data('touched', true); summary(); });
    $paid.on('input', function () { $(this).data('touched', true); summary(); });

    function summary() {
      if (!pkg) {
        $sum.html('<div class="shra-empty" style="padding:30px 10px"><i class="fa-solid fa-horse"></i>Pick a rider and a package</div>');
        $btn.prop('disabled', true);
        return;
      }
      var d = Math.max(0, Math.min(100, parseFloat($discount.val()) || 0));
      var list = parseFloat(pkg.price), disc = Math.round(list * d) / 100, total = Math.round((list - disc) * 100) / 100;
      if (!$paid.data('touched')) { $paid.val(total.toFixed(2)); }
      var paid = Math.max(0, Math.min(total, parseFloat($paid.val()) || 0));
      var due = Math.round((total - paid) * 100) / 100;
      $sum.html(
        '<div class="row-line"><span>' + S.esc(pkg.name) + ' <span class="shra-muted">(' + S.esc(pkg.audience) + ')</span></span><span>' + S.money(list) + '</span></div>' +
        '<div class="row-line"><span>' + pkg.sessions + ' session' + (pkg.sessions > 1 ? 's' : '') + ' × ' + pkg.duration_min + ' min</span><span class="shra-muted">' + S.money(pkg.per_session) + ' / session</span></div>' +
        (d > 0 ? '<div class="row-line" style="color:#a8322d"><span>Discount ' + d + '%</span><span>− ' + S.money(disc) + '</span></div>' : '') +
        '<div class="row-line total"><span>You pay</span><span class="amt">' + S.money(total) + '</span></div>' +
        (due > 0 ? '<div class="shra-alert shra-alert-warn" style="margin-top:8px">Partial payment — ' + S.money(due) + ' will stay due on the invoice.</div>' : '')
      );
      $btn.prop('disabled', !rider).find('.amt').text(S.money(paid));
    }

    $('#shra-bill-form').on('submit', function (e) {
      e.preventDefault();
      if (!rider || !pkg) return;
      $btn.prop('disabled', true).addClass('disabled');
      $.post(cfg.urls.bill, $(this).serialize(), function (res) {
        if (res.success) {
          $('#shra-bill-done').html(res.html).show();
          $('#shra-bill-form')[0].reset(); $discount.data('touched', false); $paid.data('touched', false);
          rider = null; pkg = null;
          $picked.hide(); $('#shra-rider-wrap').show();
          $pkgs.find('.shra-pkg').removeClass('selected');
          summary();
          $('html,body').animate({ scrollTop: $('#shra-bill-done').offset().top - 80 }, 200);
        } else {
          alert_float('danger', res.message || 'Could not create the bill.');
        }
        $btn.removeClass('disabled');
      }, 'json').fail(function () { alert_float('danger', 'Request failed.'); $btn.removeClass('disabled').prop('disabled', false); });
    });

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

    $('#shra-att-form').on('submit', function (e) {
      e.preventDefault();
      $mark.prop('disabled', true);
      $.post(cfg.urls.mark, $(this).serialize(), function (res) {
        if (res.success) {
          alert_float('success', res.message);
          if (res.completed) { $('#shra-att-done').html(res.html).show(); }
          $('#shra-today').html(res.today_html);
          load(rider);
          $('#shra-horse').val(''); $('#shra-note').val('');
        } else {
          alert_float('danger', res.message || 'Could not mark the session.');
          $mark.prop('disabled', false);
        }
      }, 'json').fail(function () { alert_float('danger', 'Request failed.'); $mark.prop('disabled', false); });
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

  /* ───────── Small helpers ───────── */
  $(document).on('click', '.shra-copy', function () {
    var t = $(this).data('copy');
    if (navigator.clipboard) { navigator.clipboard.writeText(t); alert_float('success', 'Copied'); }
  });

  $(document).on('click', '[data-shra-confirm]', function (e) {
    if (!confirm($(this).data('shra-confirm'))) { e.preventDefault(); }
  });

})(jQuery);
