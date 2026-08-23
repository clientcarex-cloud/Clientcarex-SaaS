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

  function cardOf(id) { return $('.shra-lead[data-lead="' + id + '"]'); }

  /** Replace every rendering of a card with the fresh HTML; recount queues/columns. */
  function swapCard(id, html) {
    var $new = $(html);
    var $old = cardOf(id);
    if (!$old.length) { location.reload(); return; }
    $old.each(function () { $(this).replaceWith($new.clone()); });
    var stage = $new.data('stage');
    // Pipeline: move to the right column
    var $col = $('.shra-col[data-stage="' + stage + '"] .shra-col-body');
    if ($col.length) {
      var $c = cardOf(id).first();
      if (!$c.closest('.shra-col').is($col.closest('.shra-col'))) { $col.prepend($c); }
      $('.shra-col').each(function () { $(this).find('.shra-col-count').text($(this).find('.shra-lead').length); });
    }
    // My Day: leave the queue placement alone but flash it
    cardOf(id).addClass('flash');
    setTimeout(function () { cardOf(id).removeClass('flash'); }, 1200);
    if (typeof S.onLeadUpdated === 'function') { S.onLeadUpdated(id, $new); }
  }

  function post(url, data, done) {
    $.post(url, data, function (res) {
      if (!res.success) {
        toast('danger', res.message || 'Could not save.');
        if (res.duplicate && res.url) { setTimeout(function () { if (confirm('Open the existing lead?')) { location.href = res.url; } }, 300); }
        return;
      }
      toast('success', res.message || 'Saved.');
      if (res.card) { swapCard(data.lead_id, res.card); }
      if (done) { done(res); }
    }, 'json').fail(function () { toast('danger', 'Request failed.'); });
  }

  function openModal(sel, id) {
    var $m = $(sel), $c = cardOf(id).first();
    $m.find('[name=lead_id]').val(id);
    $m.find('.shra-m-name').text($c.data('name') || $('#shra-lead-title').data('name') || '');
    $m.find('.shra-m-phone').html($c.data('phone') ? '<a href="tel:' + esc($c.data('phone')) + '" class="shra-pill"><i class="fa fa-phone"></i> ' + esc($c.data('phone')) + '</a>' : '');
    $m.modal('show');
    return $m;
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
        $m.find('[name=outcome]').val(''); $m.find('.shra-oc').removeClass('on'); $m.find('[name=note]').val('');
        $m.find('[name=next_action_at]').val(localDT('tomorrow 10:00')); $('#shra-call-next').show();
        break;
      case 'visit': openModal('#shra-lead-visit', id); break;
      case 'lost': openModal('#shra-lead-lost', id).find('[name=reason]').val(''); break;
      case 'confirm': openModal('#shra-lead-confirm', id); break;
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

  // Log call modal
  $('#shra-lead-call').on('click', '.shra-oc', function () {
    var $m = $('#shra-lead-call');
    $m.find('.shra-oc').removeClass('on'); $(this).addClass('on');
    $m.find('[name=outcome]').val($(this).data('outcome'));
    $('#shra-call-next').toggle(+$(this).data('next') === 1);
  });
  $('#shra-lead-call').on('click', '.shra-chip', function () { $('#shra-lead-call [name=next_action_at]').val(localDT($(this).data('plus'))); $(this).addClass('on').siblings().removeClass('on'); });
  $('#shra-lead-call-form').on('submit', function (e) {
    e.preventDefault();
    var $f = $(this);
    if (!$f.find('[name=outcome]').val()) { toast('warning', 'Pick an outcome.'); return; }
    post(cfg().urls.call, formObj($f), function () { $('#shra-lead-call').modal('hide'); });
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

  // Confirm modal
  $('#shra-lead-confirm [name=package_id]').on('change', function () { var p = $(this).find(':selected').data('price'); if (p) { $('#shra-lead-confirm [name=expected_value]').attr('placeholder', 'Package price ' + p); } });
  $('#shra-lead-confirm-form').on('submit', function (e) {
    e.preventDefault();
    post(cfg().urls.confirm, formObj($(this)), function (res) {
      $('#shra-lead-confirm').modal('hide');
      if (res.bill_url && confirm('Confirmed. Open the counter to bill now?')) { location.href = res.bill_url; }
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
      var txt = t.text.replace(/\{name\}/g, name.split(' ')[0]).replace(/\{agent\}/g, cfg().agent).replace(/\{academy\}/g, cfg().academy).replace(/\{visit\}/g, visit);
      return '<a class="shra-wa-tpl" target="_blank" rel="noopener" href="https://wa.me/' + cfg().cc + phone + '?text=' + encodeURIComponent(txt) + '"><b>' + esc(t.title) + '</b><span>' + esc(txt) + '</span></a>';
    }).join('') + '<a class="shra-wa-tpl" target="_blank" rel="noopener" href="https://wa.me/' + cfg().cc + phone + '"><b>Blank chat</b><span>Open WhatsApp without a message</span></a>');
    $m.modal('show');
    $('#shra-wa-list').off('click').on('click', 'a', function () {
      $m.modal('hide');
      setTimeout(function () { var $mm = openModal('#shra-lead-call', id); $mm.find('[name=channel]').val('whatsapp'); $mm.find('.shra-oc[data-outcome=whatsapp_sent]').trigger('click'); }, 400);
    });
  });
  $('#shra-lead-call').on('hidden.bs.modal', function () { $(this).find('[name=channel]').val('call'); });

  function formObj($f) {
    var o = {};
    $.each($f.serializeArray(), function (_, kv) { o[kv.name] = kv.value; });
    return o;
  }

  /* ───────── Kanban drag & drop ───────── */
  var dragId = null;
  $(document).on('dragstart', '.shra-lead[draggable=true]', function (e) { dragId = $(this).data('lead'); e.originalEvent.dataTransfer.setData('text/plain', dragId); $(this).addClass('dragging'); });
  $(document).on('dragend', '.shra-lead', function () { $(this).removeClass('dragging'); $('.shra-col').removeClass('over'); });
  $(document).on('dragover', '.shra-col', function (e) { e.preventDefault(); $(this).addClass('over'); });
  $(document).on('dragleave', '.shra-col', function () { $(this).removeClass('over'); });
  $(document).on('drop', '.shra-col', function (e) {
    e.preventDefault(); $(this).removeClass('over');
    var to = $(this).data('stage'), id = dragId; dragId = null;
    if (!id) { return; }
    var from = cardOf(id).first().data('stage');
    if (from === to) { return; }
    if (to === 'visit_scheduled') { openModal('#shra-lead-visit', id); return; }
    if (to === 'lost' || to === 'junk') { openModal('#shra-lead-lost', id); return; }
    if (to === 'confirmed') { if (!cfg().canAll) { toast('warning', 'Only front desk / manager can confirm visits.'); return; } openModal('#shra-lead-confirm', id); return; }
    if (to === 'visited') { if (!cfg().canAll) { toast('warning', 'Only front desk / manager can mark visits.'); return; } post(cfg().urls.visited, { lead_id: id }); return; }
    if (to === 'won') { toast('info', 'Leads become customers when a bill is created — use "Bill now".'); return; }
    var next = prompt('Next follow-up (YYYY-MM-DD HH:MM) — leave blank to keep the current one:', '');
    if (next === null) { return; }
    post(cfg().urls.stage, { lead_id: id, to: to, next_action_at: next });
  });

  /* ───────── Lead page: details & notes ───────── */
  $('#shra-lead-details-form').on('submit', function (e) { e.preventDefault(); post(cfg().urls.details, formObj($(this)), function () { location.reload(); }); });
  $('#shra-lead-note-form').on('submit', function (e) { e.preventDefault(); post(cfg().urls.note, formObj($(this)), function () { location.reload(); }); });

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
