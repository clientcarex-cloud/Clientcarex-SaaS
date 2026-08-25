/* SHRA Leads — queues, modals, kanban, billing lead-match */
(function ($) {
  'use strict';
  // Config is injected by views/leads/partials/modals.php (or the billing page) — read lazily.
  function cfg() { return window.SHRA_LEADS_CFG || { urls: {}, templates: [], canAll: false, cc: '' }; }
  var S = window.SHRA = window.SHRA || {};
  var esc = S.esc || function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };

  var csrf = S.csrf || function (serialized) {
    if (typeof csrfData === 'undefined' || !csrfData.token_name) { return serialized; }
    var t = encodeURIComponent(csrfData.token_name) + '=' + encodeURIComponent(csrfData.hash);
    return serialized ? serialized + '&' + t : t;
  };

  function toast(type, msg) { if (window.alert_float) { alert_float(type, msg); } else { alert(msg); } }

  /** Base-currency amount, formatted the way the rest of the desk shows money. */
  function money(n) {
    var c = cfg().money || { sym: '', before: true };
    var v = Math.round((Number(n) || 0) * 100) / 100;
    var s = v.toLocaleString(undefined, { maximumFractionDigits: 2 });

    return c.before ? c.sym + s : s + c.sym;
  }

  function cardOf(id) { return $('.shra-lead[data-lead="' + id + '"]'); }

  function copyText(text, done) {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, fallback);
    } else {
      fallback();
    }
    function fallback() {
      // Older browsers / plain http: copy out of a throwaway textarea.
      var $ta = $('<textarea>').val(text).css({ position: 'fixed', top: '-1000px', opacity: 0 }).appendTo('body');
      $ta[0].select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      $ta.remove();
      if (ok) { done(); } else { toast('warning', 'Could not copy automatically — select the message and copy it.'); }
    }
  }

  /** Replace every rendering of a lead with the fresh HTML, then recount the tabs. */
  function swapCard(id, html) {
    var $new = $(html);
    var $old = cardOf(id);
    if (!$old.length) { location.reload(); return; }
    $old.each(function () { $(this).replaceWith($new.clone()); });
    // Flash the row where it stands, then re-apply the filters so it leaves the
    // tab it no longer belongs to.
    cardOf(id).addClass('flash');
    setTimeout(function () { cardOf(id).removeClass('flash'); W.refresh(); }, 1200);
    if (typeof S.onLeadUpdated === 'function') { S.onLeadUpdated(id, $new); }
  }

  function handleRes(res, leadId, done) {
    if (!res.success) {
      // The billing guards (recent bill / unused sessions / older balance) come back as a
      // question, not an error — the dialog that asked for the bill answers it.
      if (res.needs_confirm && done) { done(res); return; }
      toast('danger', res.message || 'Could not save.');
      if (res.duplicate && res.url) { setTimeout(function () { if (confirm('Open the existing lead?')) { location.href = res.url; } }, 300); }
      return;
    }
    toast(res.warning ? 'warning' : 'success', res.message || 'Saved.');
    if (res.card) { swapCard(leadId, res.card); }
    if (done) { done(res); }
  }

  function post(url, data, done) {
    // Tell the server which rendering to send back: dense row or board card.
    if (data && data.lead_id && !data.fmt) { data.fmt = cardOf(data.lead_id).first().is('tr') ? 'row' : 'card'; }
    $.post(url, data, function (res) { handleRes(res, data.lead_id, done); }, 'json')
      .fail(function () { toast('danger', 'Request failed.'); });
  }

  /**
   * Same contract as post(), for a form that carries a file (the payment screenshot).
   * $.ajaxSetup's CSRF default is dropped once data is a FormData, so the token goes in by hand.
   */
  function postForm(url, $f, done) {
    var id = $f.find('[name=lead_id]').val();
    var fd = new FormData($f[0]);
    fd.append('fmt', cardOf(id).first().is('tr') ? 'row' : 'card');
    if (typeof csrfData !== 'undefined' && csrfData.token_name) { fd.append(csrfData.token_name, csrfData.hash); }
    var $b = $f.find('[type=submit]').prop('disabled', true), html = $b.html();
    $b.html('<i class="fa fa-circle-notch fa-spin"></i> Saving…');
    $.ajax({ url: url, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
      .done(function (res) { handleRes(res, id, done); })
      .fail(function () { toast('danger', 'Request failed.'); })
      .always(function () { $b.prop('disabled', false).html(html); });
  }

  /** Whatever is known about the lead, read off the row / card / lead page it was opened from. */
  function leadData(id, key) {
    var $c = cardOf(id).first();
    var v = $c.length ? $c.data(key) : undefined;

    return (v === undefined || v === '') ? $('#shra-lead-title').data(key) : v;
  }

  function openModal(sel, id) {
    var $m = $(sel);
    var phone = leadData(id, 'phone'), paid = leadData(id, 'paid'), due = leadData(id, 'due');
    $m.find('[name=lead_id]').val(id);
    $m.find('.shra-m-name').text(leadData(id, 'name') || '');
    $m.find('.shra-m-phone').html(phone ? '<a href="tel:' + esc(phone) + '" class="shra-pill"><i class="fa fa-phone"></i> ' + esc(phone) + '</a>' : '');
    // Money already collected, and what is still open — the agent needs both on the call.
    var money = '';
    if (paid) { money += '<span class="shra-pill paid"><i class="fa fa-receipt"></i> Paid ' + esc(paid) + '</span>'; }
    if (due) { money += '<span class="shra-pill due"><i class="fa fa-hourglass-half"></i> Due ' + esc(due) + '</span>'; }
    $m.find('.shra-m-money').html(money);
    $m.modal('show');
    return $m;
  }

  /** The lead's call history, loaded into the foot of the Log call dialog. */
  function loadCallLog(id) {
    var $box = $('#shra-call-log');
    if (!$box.length) { return; }
    $box.html('<div class="shra-cl-empty"><i class="fa fa-circle-notch fa-spin"></i> Loading the calls…</div>');
    $.getJSON(cfg().urls.call_log, { lead_id: id }, function (res) {
      $box.html(res && res.success ? res.html : '<div class="shra-cl-empty">Could not load the call history.</div>');
    }).fail(function () { $box.html('<div class="shra-cl-empty">Could not load the call history.</div>'); });
  }

  function localDT(expr) {
    // "tomorrow 10:00", "+3 days 11:00", "2 hours", "2026-08-29 09:00"
    var d = new Date(), m;
    if ((m = expr.match(/^(\d{4}-\d{2}-\d{2}) (\d{2}):(\d{2})$/))) { d = new Date(m[1] + 'T' + m[2] + ':' + m[3] + ':00'); }
    else if ((m = expr.match(/^(\d+) hours?$/))) { d.setHours(d.getHours() + +m[1]); }
    else if ((m = expr.match(/^tomorrow (\d{2}):(\d{2})$/))) { d.setDate(d.getDate() + 1); d.setHours(+m[1], +m[2], 0, 0); }
    else if ((m = expr.match(/^\+(\d+) days? (\d{2}):(\d{2})$/))) { d.setDate(d.getDate() + +m[1]); d.setHours(+m[2], +m[3], 0, 0); }
    else if ((m = expr.match(/^\+1 week (\d{2}):(\d{2})$/))) { d.setDate(d.getDate() + 7); d.setHours(+m[1], +m[2], 0, 0); }
    var p = function (n) { return (n < 10 ? '0' : '') + n; };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
  }

  /* ───────── Add lead ───────── */
  $(document).on('click', '[data-shra-lead-add]', function () {
    var $m = $('#shra-lead-add');
    $m.find('form')[0].reset();
    $m.find('[name=mark_visited]').val('0');
    $('#shra-lead-dup').html('');
    $m.modal('show');
    setTimeout(function () { $m.find('[name=name]').focus(); }, 300);
  });
  var dupTimer = null;
  $('#shra-lead-add [name=phone]').on('input blur', function () {
    var v = $(this).val().replace(/\D/g, '');
    clearTimeout(dupTimer);
    if (v.length < 8) { $('#shra-lead-dup').html(''); return; }
    dupTimer = setTimeout(function () {
      $.getJSON(cfg().urls.check, { phone: v }, function (r) {
        if (r.exists) {
          $('#shra-lead-dup').html('<span class="shra-alert shra-alert-bad" style="display:block;padding:6px 10px">Already a lead: <b>' + esc(r.name) + '</b> · ' + esc(r.agent) + ' · ' + esc(r.stage) + (r.mine ? ' · <a href="' + r.url + '">open</a>' : '') + '</span>');
        } else {
          $('#shra-lead-dup').html('<span class="shra-muted"><i class="fa fa-check"></i> New number</span>');
        }
      });
    }, 250);
  });
  $('#shra-lead-add-form').on('submit', function (e) {
    e.preventDefault();
    var $b = $(this).find('[type=submit]').prop('disabled', true);
    $.post(cfg().urls.add, csrf($(this).serialize()), function (res) {
      $b.prop('disabled', false);
      if (!res.success) {
        toast(res.duplicate ? 'warning' : 'danger', res.message);
        if (res.duplicate && res.mine && res.url) { setTimeout(function () { if (confirm('Open the existing lead?')) { location.href = res.url; } }, 200); }
        return;
      }
      $('#shra-lead-add').modal('hide');
      toast('success', res.message);
      location.reload();
    }, 'json').fail(function () { $b.prop('disabled', false); toast('danger', 'Request failed.'); });
  });

  /* ───────── Card actions ───────── */
  $(document).on('click', '[data-shra-act]', function () {
    var act = $(this).data('shra-act'), id = $(this).data('lead');
    switch (act) {
      case 'call':
        var $m = openModal('#shra-lead-call', id);
        $m.find('[name=note]').val('');
        $m.find('[name=next_action_at]').val(localDT('tomorrow 10:00')).trigger('change');
        resetPayment();
        buildStageChips(stageOf(id));
        loadCallLog(id);
        break;
      case 'visit': openModal('#shra-lead-visit', id); break;
      case 'lost': openModal('#shra-lead-lost', id).find('[name=reason]').val(''); break;
      case 'confirm': openConfirm(id); break;
      case 'reassign': openModal('#shra-lead-reassign', id); break;
      case 'visited':
        if (confirm('Mark as visited (arrived at the academy)?')) { post(cfg().urls.visited, { lead_id: id }); }
        break;
      case 'no_show':
        if (confirm('Record a no-show? The agent will be asked to follow up today.')) { post(cfg().urls.no_show, { lead_id: id }); }
        break;
      case 'reopen':
        var why = prompt('Reason for reopening:'); if (why === null) { return; }
        post(cfg().urls.reopen, { lead_id: id, note: why });
        break;
    }
  });

  /** The lead's stage, wherever the dialog was opened from (work list row, board card, lead page). */
  function stageOf(id) {
    var $c = cardOf(id).first();

    return ($c.length ? $c.data('stage') : $('#shra-lead-title').data('stage')) || '';
  }

  /**
   * The only picker in Log call: "Keep <current>" plus the statuses reachable from here that
   * need no extra details. Visits, confirmations and losses keep their own dialogs.
   */
  function buildStageChips(from) {
    var c = cfg(), $wrap = $('#shra-call-stage'), $list = $('#shra-call-stage-list');
    $('#shra-lead-call [name=stage]').val('');
    var cur = (c.stages || {})[from];
    var html = '<button type="button" class="shra-chip on" data-stage="">Keep ' + esc(cur ? cur.label : 'current') + '</button>';
    var allowed = ((c.transitions || {})[from] || []).filter(function (k) { return (c.quickStages || []).indexOf(k) !== -1; });
    allowed.forEach(function (k) {
      html += '<button type="button" class="shra-chip" data-stage="' + k + '"><i style="background:' + esc(c.stages[k].color) + '"></i>' + esc(c.stages[k].label) + '</button>';
    });
    $list.html(html);
    $wrap.show();
  }
  $('#shra-lead-call').on('click', '#shra-call-stage-list .shra-chip', function () {
    $(this).addClass('on').siblings().removeClass('on');
    $('#shra-lead-call [name=stage]').val($(this).data('stage'));
  });

  /* ───────── Payment taken on the call (advance / part payment) ───────── */
  function resetPayment() {
    var $m = $('#shra-lead-call');
    $('#shra-pay-on').prop('checked', false);
    $('#shra-pay-box').prop('hidden', true);
    $m.find('[name=paid_amount],[name=paid_reference],[name=paid_note]').val('');
    clearProof();
  }
  function clearProof() {
    var el = document.getElementById('shra-pay-proof');
    if (el) { el.value = ''; }
    $('#shra-pay-preview').prop('hidden', true);
    $('#shra-pay-thumb').attr('src', '').hide();
    $('#shra-pay-fname').text('');
  }
  $('#shra-pay-on').on('change', function () {
    var on = $(this).is(':checked');
    var due = leadData($('#shra-lead-call [name=lead_id]').val(), 'due');
    $('#shra-lead-call [name=paid_amount]').attr('placeholder', due ? 'Due ' + due : 'e.g. 50% advance');
    $('#shra-pay-box').prop('hidden', !on);
    if (on) { setTimeout(function () { $('#shra-lead-call [name=paid_amount]').focus(); }, 50); } else { clearProof(); $('#shra-lead-call [name=paid_amount]').val(''); }
  });
  $('#shra-pay-clear').on('click', clearProof);
  $('#shra-pay-proof').on('change', function () {
    var f = this.files && this.files[0];
    if (!f) { clearProof(); return; }
    if (f.size > 5 * 1024 * 1024) { toast('warning', 'That screenshot is over 5 MB — send a smaller one.'); clearProof(); return; }
    $('#shra-pay-fname').text(f.name);
    $('#shra-pay-preview').prop('hidden', false);
    if (/^image\//.test(f.type) && window.FileReader) {
      var r = new FileReader();
      r.onload = function (e) { $('#shra-pay-thumb').attr('src', e.target.result).show(); };
      r.readAsDataURL(f);
    } else {
      $('#shra-pay-thumb').attr('src', '').hide();
    }
  });
  // Scoped to the follow-up block — the status picker below it uses .shra-chip too.
  $('#shra-lead-call').on('click', '#shra-call-next .shra-chip', function () { $('#shra-lead-call [name=next_action_at]').val(localDT($(this).data('plus'))).trigger('change'); $(this).addClass('on').siblings().removeClass('on'); });
  $('#shra-lead-call-form').on('submit', function (e) {
    e.preventDefault();
    var $f = $(this);
    if ($('#shra-pay-on').is(':checked') && !(parseFloat($f.find('[name=paid_amount]').val()) > 0)) {
      toast('warning', 'Enter the amount collected, or untick "Payment taken on this call".');
      $f.find('[name=paid_amount]').focus();
      return;
    }
    postForm(cfg().urls.call, $f, function () { $('#shra-lead-call').modal('hide'); });
  });

  // Visit modal
  $('#shra-lead-visit').on('click', '.shra-chip', function () { $('#shra-lead-visit [name=visit_date]').val($(this).data('date')); $(this).addClass('on').siblings().removeClass('on'); });
  $('#shra-lead-visit-form').on('submit', function (e) { e.preventDefault(); post(cfg().urls.visit, formObj($(this)), function () { $('#shra-lead-visit').modal('hide'); }); });

  // Lost modal
  $('#shra-lead-lost-form').on('submit', function (e) {
    e.preventDefault();
    var d = formObj($(this));
    if (d.as_junk) { post(cfg().urls.junk, { lead_id: d.lead_id, note: (d.reason || '') + (d.note ? ' — ' + d.note : '') }, function () { $('#shra-lead-lost').modal('hide'); }); return; }
    post(cfg().urls.lost, d, function () { $('#shra-lead-lost').modal('hide'); });
  });

  /* ───────── Confirm: package, balance, collect, close the sale ───────── */
  function cfClearProof() {
    var el = document.getElementById('shra-cf-proof');
    if (el) { el.value = ''; }
    $('#shra-cf-preview').prop('hidden', true);
    $('#shra-cf-thumb').attr('src', '').hide();
    $('#shra-cf-fname').text('');
  }

  /** Everything the dialog shows about money is derived here, live, as the fields change. */
  function cfRefresh() {
    var $m = $('#shra-lead-confirm');
    var paid = parseFloat($m.data('paidNum')) || 0;
    var pkg  = parseFloat($m.find('[name=package_id] :selected').data('price')) || 0;
    var exp  = parseFloat($m.find('[name=expected_value]').val());
    var deal = exp > 0 ? exp : pkg;
    var due  = Math.max(0, Math.round((deal - paid) * 100) / 100);

    $('#shra-cf-total').text(deal > 0 ? money(deal) : '—');
    $('#shra-cf-paid').text(money(paid));
    $('#shra-cf-due').text(deal > 0 ? money(due) : '—');
    $m.find('[name=paid_amount]').attr('placeholder', due > 0 ? money(due) : 'Amount');
    $m.find('[name=expected_value]').attr('placeholder', pkg > 0 ? 'Package price ' + money(pkg) : 'Leave blank to use the package price');

    var $c = $('#shra-cf-complete');
    if (!$c.length) { return; }
    var on  = $c.is(':checked');
    var now = $('#shra-cf-pay-on').is(':checked') ? (parseFloat($m.find('[name=paid_amount]').val()) || 0) : 0;
    $m.find('[name=complete]').val(on ? '1' : '0');
    $('#shra-cf-submit-txt').text(on ? (now > 0 ? 'Collect ' + money(now) + ' & complete' : 'Bill & complete') : 'Confirm');
    // The invoice is always raised for the package, never for a hand-typed expected value.
    var got = Math.min(pkg, paid + now);
    var msg = !pkg
      ? 'Pick the package — the invoice is raised for it.'
      : 'Invoice ' + money(pkg) + ' · received ' + money(got)
        + (pkg - got > 0.009 ? ' · <b>' + money(pkg - got) + ' stays due</b> on the bill.' : ' · fully paid.')
        + ' The rider, the invoice and the receipt are created, and the lead moves to Joined.';
    $('#shra-cf-complete-help').prop('hidden', !on).html(msg);
  }

  function openConfirm(id) {
    var $m = openModal('#shra-lead-confirm', id);
    $m.data('paidNum', parseFloat(leadData(id, 'paidNum')) || 0);
    $m.find('[name=complete],[name=force]').val('0');
    $m.find('[name=bill_token]').val('cf' + id + '-' + Date.now() + '-' + Math.floor(Math.random() * 1e6));
    $m.find('[name=expected_value],[name=note],[name=paid_amount],[name=paid_reference],[name=paid_note]').val('');
    $m.find('[name=package_id]').val(String(leadData(id, 'pkg') || ''));
    $('#shra-cf-pay-on').prop('checked', false).prop('disabled', false);
    $('#shra-cf-pay-box').prop('hidden', true);
    $('#shra-cf-complete').prop('checked', false);
    cfClearProof();
    cfRefresh();
  }

  $('#shra-lead-confirm').on('change keyup', '[name=package_id],[name=expected_value],[name=paid_amount]', cfRefresh);
  $('#shra-cf-complete').on('change', cfRefresh);
  $('#shra-cf-pay-on').on('change', function () {
    var on = $(this).is(':checked');
    $('#shra-cf-pay-box').prop('hidden', !on);
    if (on) {
      var $a = $('#shra-lead-confirm [name=paid_amount]');
      var due = cfDue();
      if (!$a.val() && due > 0) { $a.val(due); }
      setTimeout(function () { $a.focus().select(); }, 50);
    } else {
      $('#shra-lead-confirm [name=paid_amount]').val('');
      cfClearProof();
    }
    cfRefresh();
  });
  /** The balance as a plain number — what the amount field is pre-filled with. */
  function cfDue() {
    var $m = $('#shra-lead-confirm');
    var paid = parseFloat($m.data('paidNum')) || 0;
    var pkg  = parseFloat($m.find('[name=package_id] :selected').data('price')) || 0;
    var exp  = parseFloat($m.find('[name=expected_value]').val());
    var deal = exp > 0 ? exp : pkg;

    return Math.max(0, Math.round((deal - paid) * 100) / 100);
  }
  $('#shra-cf-clear').on('click', cfClearProof);
  $('#shra-cf-proof').on('change', function () {
    var f = this.files && this.files[0];
    if (!f) { cfClearProof(); return; }
    if (f.size > 5 * 1024 * 1024) { toast('warning', 'That screenshot is over 5 MB — send a smaller one.'); cfClearProof(); return; }
    $('#shra-cf-fname').text(f.name);
    $('#shra-cf-preview').prop('hidden', false);
    if (/^image\//.test(f.type) && window.FileReader) {
      var r = new FileReader();
      r.onload = function (e) { $('#shra-cf-thumb').attr('src', e.target.result).show(); };
      r.readAsDataURL(f);
    } else {
      $('#shra-cf-thumb').attr('src', '').hide();
    }
  });

  /** Money already banked must never be posted twice when the bill is re-tried. */
  function cfLockPayment(paidNum) {
    $('#shra-lead-confirm').data('paidNum', paidNum);
    $('#shra-cf-pay-on').prop('checked', false).prop('disabled', true);
    $('#shra-cf-pay-box').prop('hidden', true);
    $('#shra-lead-confirm [name=paid_amount],#shra-lead-confirm [name=paid_reference],#shra-lead-confirm [name=paid_note]').val('');
    cfClearProof();
  }

  $('#shra-lead-confirm-form').on('submit', function (e) {
    e.preventDefault();
    var $f = $(this);
    if ($('#shra-cf-pay-on').is(':checked') && !(parseFloat($f.find('[name=paid_amount]').val()) > 0)) {
      toast('warning', 'Enter the amount collected, or untick "Collect payment now".');
      $f.find('[name=paid_amount]').focus();
      return;
    }
    if ($f.find('[name=complete]').val() === '1' && !$f.find('[name=package_id]').val()) {
      toast('warning', 'Pick the package — the invoice is raised for it.');
      $f.find('[name=package_id]').focus();
      return;
    }
    postForm(cfg().urls.confirm, $f, function (res) {
      if (res.paid_recorded) { cfLockPayment(res.paid_num); }
      if (res.needs_confirm) {
        if (confirm(res.message)) { $f.find('[name=force]').val('1'); $f.trigger('submit'); }
        else { toast('warning', 'Confirmed — the bill was not raised.'); location.href = res.lead_url; }
        return;
      }
      $('#shra-lead-confirm').modal('hide');
      if (res.redirect) { location.href = res.redirect; return; }
      if (res.bill_url && !res.warning && confirm('Confirmed. Open the counter to bill now?')) { location.href = res.bill_url; }
    });
  });

  // Reassign
  $('#shra-lead-reassign-form').on('submit', function (e) { e.preventDefault(); post(cfg().urls.reassign, formObj($(this)), function () { $('#shra-lead-reassign').modal('hide'); }); });

  // WhatsApp templates
  $(document).on('click', '[data-shra-wa]', function (e) {
    if (!cfg().templates || !cfg().templates.length) { return; } // plain wa.me link
    e.preventDefault();
    var id = $(this).data('shra-wa'), $c = cardOf(id).first();
    var name = $c.data('name') || $('#shra-lead-title').data('name') || '', phone = ($c.data('phone') || $('#shra-lead-title').data('phone') || '').toString().replace(/\D/g, '');
    var visit = $c.data('visit') || $('#shra-lead-title').data('visit') || '';
    if (phone.length > 10 && cfg().cc && phone.indexOf(cfg().cc) === 0) { phone = phone.slice(cfg().cc.length); }
    phone = phone.replace(/^0+/, '');
    var $m = $('#shra-lead-wa'); $m.find('.shra-m-name').text(name);
    $('#shra-wa-list').html(cfg().templates.map(function (t) {
      var txt = t.text.replace(/\{name\}/g, name.split(' ')[0]).replace(/\{agent\}/g, cfg().agent).replace(/\{academy\}/g, cfg().academy).replace(/\{visit\}/g, visit).replace(/\{location\}/g, cfg().location || '');
      return '<a class="shra-wa-tpl" target="_blank" rel="noopener" href="https://wa.me/' + cfg().cc + phone + '?text=' + encodeURIComponent(txt) + '"><b>' + esc(t.title) + '</b><span>' + esc(txt) + '</span></a>';
    }).join('') + '<a class="shra-wa-tpl" target="_blank" rel="noopener" href="https://wa.me/' + cfg().cc + phone + '"><b>Blank chat</b><span>Open WhatsApp without a message</span></a>');
    $m.modal('show');
    $('#shra-wa-list').off('click').on('click', 'a', function () {
      $m.modal('hide');
      setTimeout(function () {
        var $mm = openModal('#shra-lead-call', id);
        $mm.find('[name=channel]').val('whatsapp');
        $mm.find('[name=note]').val('');
        $mm.find('[name=next_action_at]').val(localDT('tomorrow 10:00')).trigger('change');
        resetPayment();
        buildStageChips(stageOf(id));
        loadCallLog(id);
      }, 400);
    });
  });
  // Copy button next to WhatsApp — the full pitch on the clipboard, personalised.
  $(document).on('click', '[data-shra-wa-copy]', function () {
    var id = $(this).data('shra-wa-copy'), $c = cardOf(id).first(), $b = $(this);
    var name  = ($c.data('name') || $('#shra-lead-title').data('name') || '').toString();
    var visit = $c.data('visit') || $('#shra-lead-title').data('visit') || '';
    var txt = (cfg().copyMsg || '')
      .replace(/\{name\}/g, name.split(' ')[0])
      .replace(/\{agent\}/g, cfg().agent)
      .replace(/\{academy\}/g, cfg().academy)
      .replace(/\{visit\}/g, visit)
      .replace(/\{location\}/g, cfg().location || '');
    if (!txt) { return; }
    copyText(txt, function () {
      toast('success', 'Message copied — paste it into WhatsApp.');
      var $i = $b.find('i');
      $i.attr('class', 'fa fa-check');
      setTimeout(function () { $i.attr('class', 'fa fa-copy'); }, 2000);
    });
  });

  $('#shra-lead-call').on('hidden.bs.modal', function () { $(this).find('[name=channel]').val('call'); resetPayment(); });

  // Lead page: drop a wrongly entered payment (managers only — the button is theirs).
  $(document).on('click', '[data-shra-pay-del]', function () {
    if (!confirm('Remove this payment entry? The lead keeps a record that it was removed.')) { return; }
    post(cfg().urls.payment_del, { payment_id: $(this).data('shra-pay-del'), lead_id: $(this).data('lead') }, function () { location.reload(); });
  });

  // Lead page: the screenshot the customer sent after the call was logged.
  $(document).on('click', '[data-shra-pay-proof]', function () {
    var $late = $('#shra-pay-proof-late');
    if (!$late.length) { return; }
    $late.data('payment', $(this).data('shra-pay-proof')).data('lead', $(this).data('lead')).val('').trigger('click');
  });
  $(document).on('change', '#shra-pay-proof-late', function () {
    var f = this.files && this.files[0], $i = $(this);
    if (!f) { return; }
    if (f.size > 5 * 1024 * 1024) { toast('warning', 'That screenshot is over 5 MB — send a smaller one.'); $i.val(''); return; }
    var fd = new FormData();
    fd.append('payment_id', $i.data('payment'));
    fd.append('lead_id', $i.data('lead'));
    fd.append('payment_proof', f);
    if (typeof csrfData !== 'undefined' && csrfData.token_name) { fd.append(csrfData.token_name, csrfData.hash); }
    toast('info', 'Uploading the screenshot…');
    $.ajax({ url: cfg().urls.payment_proof, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
      .done(function (res) { if (!res.success) { toast('danger', res.message || 'Could not attach it.'); return; } toast('success', res.message); location.reload(); })
      .fail(function () { toast('danger', 'Request failed.'); })
      .always(function () { $i.val(''); });
  });

  function formObj($f) {
    var o = {};
    $.each($f.serializeArray(), function (_, kv) { o[kv.name] = kv.value; });
    return o;
  }

  /* ───────── Lead page: details & notes ───────── */
  $('#shra-lead-details-form').on('submit', function (e) { e.preventDefault(); post(cfg().urls.details, formObj($(this)), function () { location.reload(); }); });
  $('#shra-lead-note-form').on('submit', function (e) { e.preventDefault(); post(cfg().urls.note, formObj($(this)), function () { location.reload(); }); });

  /* ───────── datetime-local fields echo the picked time in 12-hour form ─────────
     The native picker renders in the browser's locale (24h on many of them), so SHRA
     spells the chosen moment out underneath it. */
  function dtHint($i) {
    var $h = $i.next('.shra-dt-hint');
    if (!$h.length) { $h = $('<div class="help shra-dt-hint"></div>').insertAfter($i); }
    var v = $i.val(), d = v ? new Date(v) : null;
    $h.text(!d || isNaN(d.getTime()) ? '' : d.toLocaleString(undefined, {
      weekday: 'short', day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', hour12: true
    }));
  }
  $(document).on('input change', 'input[type=datetime-local]', function () { dtHint($(this)); });
  $(document).on('shown.bs.modal', '.modal.shra', function () { $(this).find('input[type=datetime-local]').each(function () { dtHint($(this)); }); });
  $(function () { $('.shra input[type=datetime-local]').each(function () { dtHint($(this)); }); });

  /* ───────── The leads work list: tabs, instant search, row menu ───────── */
  var W = (function () {
    // Buckets in the order an agent should work them. `all` is everything but the no-show tab.
    var TABS = [
      ['overdue',  'Overdue',     't-red'],
      ['today',    'Today',       't-gold'],
      ['unset',    'No date',     't-red'],
      ['upcoming', 'Next 7 days', ''],
      ['later',    'Later',       ''],
      ['noshow',   'No-show',     't-red'],
      ['open',     'Open',        ''],
      ['closed',   'Closed',      ''],
      ['all',      'All',         '']
    ];
    var $rows = null, $tabs = null, bucket = 'all', q = '', stage = '', source = '';

    function counts() {
      var c = { all: 0, open: 0 };
      $rows.children('tr.shra-lead').each(function () {
        var b = this.getAttribute('data-bucket');
        c[b] = (c[b] || 0) + 1;
        if (b !== 'noshow') { c.all++; }
        if (b !== 'noshow' && b !== 'closed') { c.open++; }
      });

      return c;
    }

    /** (Re)draw the tab strip — a bucket with nothing in it does not earn a tab. */
    function drawTabs() {
      var c = counts(), html = '';
      TABS.forEach(function (t) {
        if (!c[t[0]] && t[0] !== 'all') { return; }
        // "Open" would just repeat "All" unless closed leads are on the page too.
        if (t[0] === 'open' && !c.closed) { return; }
        html += '<button type="button" class="shra-tab ' + t[2] + (t[0] === bucket ? ' on' : '') +
                '" data-bucket="' + t[0] + '">' + t[1] + ' <b>' + (c[t[0]] || 0) + '</b></button>';
      });
      $('#shra-tabs').html(html);
      $tabs = $('#shra-tabs .shra-tab');
      if (!$tabs.filter('.on').length) { bucket = 'all'; $tabs.filter('[data-bucket=all]').addClass('on'); }
    }

    function apply() {
      var shown = 0, total = 0;
      $rows.children('tr.shra-lead').each(function () {
        var r = this, b = r.getAttribute('data-bucket');
        total++;
        var inBucket = bucket === 'all' ? b !== 'noshow'
                     : bucket === 'open' ? (b !== 'noshow' && b !== 'closed')
                     : b === bucket;
        var ok = inBucket
              && (!stage || r.getAttribute('data-stage') === stage)
              && (!source || r.getAttribute('data-source') === source)
              && (!q || r.getAttribute('data-s').indexOf(q) !== -1);
        if (ok) { r.className = r.className.replace(/\s*is-off\b/, ''); shown++; }
        else if (r.className.indexOf('is-off') === -1) { r.className += ' is-off'; }
      });
      $('#shra-none').prop('hidden', shown > 0 || !total);
      $('.shra-wt thead').toggle(shown > 0);
      $('#shra-count').text(total ? shown + ' of ' + total + ' shown' : '');
    }

    function refresh() { if ($rows) { drawTabs(); apply(); } }

    function init() {
      var $f = $('#shra-filters');
      if (!$f.length) { return; }
      $rows  = $('#shra-rows');
      stage  = $('#shra-f-stage').val() || '';
      source = $('#shra-f-source').val() || '';
      q      = $.trim($('#shra-q').val() || '').toLowerCase();

      // A link may name the tab (dashboard "overdue", "open leads"); otherwise the
      // all-leads scope opens on everything and the personal queue on what is late.
      var c = counts();
      bucket = $f.data('bucket') || ($f.data('scope') === 'all' ? 'all'
             : (c.overdue ? 'overdue' : (c.today ? 'today' : (c.unset ? 'unset' : 'all'))));
      drawTabs();
      apply();

      $('#shra-tabs').on('click', '.shra-tab', function () {
        $tabs.removeClass('on'); $(this).addClass('on');
        bucket = $(this).data('bucket'); apply();
      });

      var t = null;
      $('#shra-q').on('input', function () {
        var v = $.trim(this.value).toLowerCase();
        clearTimeout(t);
        t = setTimeout(function () { q = v; apply(); }, 120);
      });
      $('#shra-f-stage').on('change', function () { stage = this.value; apply(); });
      $('#shra-f-source').on('change', function () { source = this.value; apply(); });
      $('#shra-f-clear').on('click', function () {
        q = stage = source = ''; $('#shra-q').val(''); $('#shra-f-stage,#shra-f-source').val('');
        bucket = 'all'; drawTabs(); apply();
      });

      // "/" focuses search, Esc closes the row menu — agents live on the keyboard here.
      $(document).on('keydown', function (e) {
        if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test((e.target.tagName || ''))) { e.preventDefault(); $('#shra-q').focus(); }
        if (e.key === 'Escape') { closeMenu(); }
      });
    }

    /* Row overflow menu — rendered at body level so the table never clips it. */
    var $menu = null;
    function closeMenu() { if ($menu) { $menu.hide(); } }
    $(document).on('click', '[data-shra-more]', function (e) {
      e.stopPropagation();
      var $src = $(this).closest('tr').find('.shra-r-menu');
      if (!$menu) { $menu = $('<div class="shra-row-menu"></div>').appendTo('body'); }
      if ($menu.is(':visible') && $menu.data('for') === $(this).data('shra-more')) { closeMenu(); return; }
      $menu.data('for', $(this).data('shra-more')).html($src.html()).show();
      var b = this.getBoundingClientRect(), h = $menu.outerHeight(), w = $menu.outerWidth();
      $menu.css({
        top: (b.bottom + h + 8 > window.innerHeight ? Math.max(8, b.top - h - 4) : b.bottom + 4) + 'px',
        left: Math.max(8, Math.min(b.right - w, window.innerWidth - w - 8)) + 'px'
      });
    });
    $(document).on('click', function () { closeMenu(); });
    $(document).on('click', '.shra-row-menu', function () { closeMenu(); });
    $(window).on('scroll resize', closeMenu);

    $(init);
    return { refresh: refresh, closeMenu: closeMenu };
  })();

  /* ───────── End-of-day report ───────── */
  (function () {
    var text = '', seq = 0;

    // WhatsApp markup → HTML, so the preview reads the way the message will.
    function render(t) {
      return esc(t)
        .replace(/\*([^*\n]+)\*/g, '<b>$1</b>')
        .replace(/_([^_\n]+)_/g, '<em>$1</em>')
        .replace(/([▓░]{4,})/g, '<span class="bar">$1</span>');
    }

    function load() {
      // Changing the agent or the date re-fetches; only the newest answer may paint.
      var mine = ++seq;
      text = '';
      $('#shra-eod-preview').html('<span class="shra-eod-loading"><i class="fa fa-circle-notch fa-spin"></i> Building the report…</span>');
      $('#shra-eod-copy').prop('disabled', true);
      $.getJSON(cfg().urls.eod, { agent: $('#shra-eod-agent').val() || '', date: $('#shra-eod-date').val() || '' }, function (res) {
        if (mine !== seq) { return; }
        if (!res || !res.success) { $('#shra-eod-preview').html('<span class="shra-eod-loading">Could not build the report.</span>'); return; }
        text = res.text;
        $('#shra-eod-preview').html(render(text) + '<span class="shra-eod-time">' + esc(res.label) + ' ✓✓</span>');
        $('#shra-eod-copy').prop('disabled', false);
        $('#shra-eod-wa').attr('href', 'https://wa.me/?text=' + encodeURIComponent(text));
      }).fail(function () {
        if (mine === seq) { $('#shra-eod-preview').html('<span class="shra-eod-loading">Request failed.</span>'); }
      });
    }

    function copy() {
      if (!text) { return; }
      copyText(text, function () {
        toast('success', 'Report copied — paste it into WhatsApp.');
        var $b = $('#shra-eod-copy');
        $b.html('<i class="fa fa-check"></i> Copied');
        setTimeout(function () { $b.html('<i class="fa fa-copy"></i> Copy message'); }, 2000);
      });
    }

    $(document).on('click', '[data-shra-eod]', function () {
      var agent = String($(this).data('shra-eod') || '');
      if (agent && $('#shra-eod-agent option[value="' + agent + '"]').length) { $('#shra-eod-agent').val(agent); }
      $('#shra-lead-eod').modal('show');
      load();
    });
    $(document).on('change', '#shra-eod-agent,#shra-eod-date', load);
    $(document).on('click', '#shra-eod-copy', copy);
  })();

  /* ───────── Billing: phone → lead match banner ───────── */
  S.leadMatch = function (url) {
    $(document).on('shra:riderPicked', function (_, r) {
      var $b = $('#shra-lead-banner');
      if (!$b.length || !r) { return; }
      if ($('#shra-lead-id').val() && $('#shra-lead-id').data('fixed')) { return; }
      $.getJSON(url, { phone: r.mobile }, function (res) {
        if (!res.match) { $b.hide().html(''); $('#shra-lead-id').val(''); return; }
        $('#shra-lead-id').val(res.id); $('#shra-credit-lead').val('1');
        $b.html('<div class="shra-alert shra-alert-warn" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><i class="fa-solid fa-headset"></i><span>This rider matches lead <a href="' + res.url + '" target="_blank"><b>' + esc(res.name) + '</b></a> · ' + esc(res.stage) + ' · agent <b>' + esc(res.agent) + '</b>. Revenue will be credited to ' + esc(res.agent) + '.</span>' +
          (cfg().canAll ? '<label style="margin-left:auto;font-weight:500"><input type="checkbox" id="shra-credit-toggle" checked> credit this agent</label>' : '') + '</div>').show();
      });
    });
    $(document).on('change', '#shra-credit-toggle', function () { $('#shra-credit-lead').val(this.checked ? '1' : '0'); });
  };
})(jQuery);
