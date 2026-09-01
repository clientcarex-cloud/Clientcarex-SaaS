/* SHRA Self-Training — lesson reader + quiz runner */
(function ($) {
  'use strict';

  var S = window.SHRA = window.SHRA || {};
  var esc = S.esc || function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };
  var csrf = S.csrf || function (serialized) {
    if (typeof csrfData === 'undefined' || !csrfData.token_name) { return serialized; }
    var t = encodeURIComponent(csrfData.token_name) + '=' + encodeURIComponent(csrfData.hash);
    return serialized ? serialized + '&' + t : t;
  };
  function toast(type, msg) { if (window.alert_float) { alert_float(type, msg); } else if (type === 'danger') { alert(msg); } }

  var CFG = null;
  var $root, $panes, $steps;
  var current = 0;               // 0..n-1, or 'quiz'
  var openedAt = Date.now();     // reading time for the current pane
  // Lessons the trainee has deliberately un-ticked this visit — auto-ticking
  // must not quietly undo that the next time they page past the lesson.
  var unticked = {};

  /* ─────────────────────────── Reader ─────────────────────────── */

  function paneOf(step) { return $panes.filter('[data-pane="' + step + '"]'); }

  function show(step) {
    var $p = paneOf(step);
    if (!$p.length) { return; }

    // Bank the time spent on the pane we are leaving, then reset the clock.
    var spent = Math.round((Date.now() - openedAt) / 1000);
    openedAt = Date.now();

    current = step;
    $panes.removeClass('on');
    $p.addClass('on');
    $steps.removeClass('on');
    $steps.filter('[data-step="' + step + '"]').addClass('on');

    // Auto-tick a lesson once it has genuinely been on screen for a moment —
    // the trainee can still untick it by hand.
    if (spent >= 15) {
      var leftId = $panes.filter('.was-open').data('lesson');
      if (leftId && !unticked[leftId]) { mark(leftId, false, spent, true); }
    }
    $panes.removeClass('was-open');
    $p.addClass('was-open');

    if ($(window).width() < 992 || $root.offset().top < $(window).scrollTop()) {
      $('html, body').animate({ scrollTop: Math.max(0, $root.offset().top - 70) }, 220);
    }
  }

  /** Tick (or untick) a lesson for the logged-in user. */
  function mark(lessonId, undo, seconds, quiet) {
    return $.post(CFG.urls.lessonDone, csrf(
      'lesson_id=' + encodeURIComponent(lessonId) +
      '&undo=' + (undo ? 1 : 0) +
      '&seconds=' + (parseInt(seconds, 10) || 0)
    ), null, 'json').done(function (res) {
      if (!res || !res.success) {
        if (!quiet) { toast('danger', (res && res.message) || 'Could not save your progress.'); }
        return;
      }
      paintProgress(lessonId, !undo, res.stats);
    }).fail(function () {
      if (!quiet) { toast('danger', 'Could not save your progress — check your connection.'); }
    });
  }

  function paintProgress(lessonId, done, stats) {
    var $step = $steps.filter('[data-lesson="' + lessonId + '"]');
    var idx = $step.data('step');
    $step.toggleClass('ok', !!done);
    $step.find('.n').html(done ? '<i class="fa fa-check"></i>' : (parseInt(idx, 10) + 1));

    var $btn = $('.shra-tr-mark[data-lesson="' + lessonId + '"]');
    $btn.toggleClass('is-done', !!done);
    $btn.find('span').text(done ? 'Completed' : 'Mark as read');
    $btn.find('i').attr('class', done ? 'fa fa-circle-check' : 'fa-regular fa-circle');

    if (stats && typeof stats.done !== 'undefined') {
      $('#shra-tr-count').text(stats.done);
      var pc = stats.lessons ? Math.max(2, Math.round(stats.done / stats.lessons * 100)) : 2;
      $('#shra-tr-bar').css('width', pc + '%');
    }
  }

  /* ─────────────────────────── Quiz ─────────────────────────── */

  var Q = { list: [], i: 0, answers: {}, marks: [], startedAt: 0, locked: false };

  function quizStart() {
    var $go = $('#shra-quiz-go').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Drawing your questions…');

    $.post(CFG.urls.quizStart, csrf(''), null, 'json').done(function (res) {
      $go.prop('disabled', false).html('<i class="fa-solid fa-play"></i> Start the quiz');
      if (!res || !res.success) { toast('danger', (res && res.message) || 'Could not start the quiz.'); return; }

      Q = { list: res.quiz.questions, i: 0, answers: {}, marks: [], startedAt: Date.now(), locked: false };
      $('#shra-quiz-intro, #shra-quiz-result').hide();
      $('#shra-quiz-run').show();
      $('#shra-quiz-n').text(Q.list.length);
      renderDots();
      renderQuestion();
    }).fail(function () {
      $go.prop('disabled', false).html('<i class="fa-solid fa-play"></i> Start the quiz');
      toast('danger', 'Could not start the quiz — check your connection.');
    });
  }

  function renderDots() {
    var h = '';
    for (var i = 0; i < Q.list.length; i++) {
      var cls = Q.marks[i] === true ? 'ok' : (Q.marks[i] === false ? 'no' : (i === Q.i ? 'on' : ''));
      h += '<i class="' + cls + '"></i>';
    }
    $('#shra-quiz-dots').html(h);
    $('#shra-quiz-i').text(Math.min(Q.i + 1, Q.list.length));
  }

  function renderQuestion() {
    var q = Q.list[Q.i];
    if (!q) { return; }
    Q.locked = false;

    $('#shra-quiz-q').text(q.question);
    $('#shra-quiz-fb').empty();
    $('#shra-quiz-next').hide();

    var letters = 'ABCDEFGH';
    var h = '';
    for (var i = 0; i < q.options.length; i++) {
      h += '<button type="button" class="shra-quiz-opt" data-pick="' + q.options[i].i + '">' +
             '<span class="k">' + letters.charAt(i) + '</span>' +
             '<span>' + esc(q.options[i].text) + '</span>' +
           '</button>';
    }
    $('#shra-quiz-opts').html(h);
    renderDots();
  }

  /**
   * The browser is never told which answer is right, so feedback shown mid-quiz
   * is provisional — the server grades the whole attempt at the end and its
   * verdict is what counts. We only reveal right/wrong once the run is graded.
   */
  function pick($btn) {
    if (Q.locked) { return; }
    Q.locked = true;

    var q = Q.list[Q.i];
    Q.answers[q.id] = parseInt($btn.data('pick'), 10);

    $('#shra-quiz-opts .shra-quiz-opt').prop('disabled', true).addClass('dim');
    $btn.removeClass('dim').addClass('picked');

    $('#shra-quiz-next')
      .html((Q.i === Q.list.length - 1 ? 'See my score' : 'Next') + ' <i class="fa fa-arrow-right"></i>')
      .show();
  }

  function next() {
    if (Q.i < Q.list.length - 1) {
      Q.i++;
      renderQuestion();
      return;
    }
    submit();
  }

  function submit() {
    $('#shra-quiz-next').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Marking…');

    $.post(CFG.urls.quizSubmit, csrf(
      'answers=' + encodeURIComponent(JSON.stringify(Q.answers)) +
      '&seconds=' + Math.round((Date.now() - Q.startedAt) / 1000)
    ), null, 'json').done(function (res) {
      $('#shra-quiz-next').prop('disabled', false).hide();
      if (!res || !res.success) { toast('danger', (res && res.message) || 'Could not mark the quiz.'); return; }
      showResult(res.result);
      paintQuizStep(res.result, res.stats);
    }).fail(function () {
      $('#shra-quiz-next').prop('disabled', false).html('See my score <i class="fa fa-arrow-right"></i>');
      toast('danger', 'Could not send your answers — check your connection and try again.');
    });
  }

  function showResult(r) {
    var passed = !!r.passed;
    var em = passed ? (r.percent >= 100 ? '🏆' : '🎉') : '💪';
    var head = passed
      ? (r.percent >= 100 ? 'Flawless!' : 'You passed!')
      : 'Not quite yet';
    var line = passed
      ? (r.first_pass
          ? 'Module cleared — that is one more thing you will never have to look up on a call.'
          : 'Passed again — your best score for this module is ' + r.best + '%.')
      : 'You needed ' + r.pass + '% and scored ' + r.percent + '%. Read the explanations below, then have another go — there is no limit.';

    var h = '<div class="shra-quiz-score">' +
              '<span class="em">' + em + '</span>' +
              '<div class="big ' + (passed ? 'pass' : 'fail') + '">' + r.percent + '%</div>' +
              '<h3>' + head + '</h3>' +
              '<p>' + esc(line) + '</p>' +
              '<p style="margin-top:8px"><b>' + r.correct + ' of ' + r.total + '</b> correct</p>' +
            '</div>';

    h += '<div class="shra-quiz-review">';
    for (var i = 0; i < r.detail.length; i++) {
      var d = r.detail[i];
      h += '<div class="row">' +
             '<span class="ic">' + (d.right ? '✅' : '❌') + '</span>' +
             '<div><div class="qq">' + esc(d.question) + '</div>' +
               '<div class="aa">' +
                 (d.right ? '' : 'You chose: ' + esc(d.picked_text || '—') + '<br>') +
                 'Correct: <b>' + esc(d.answer) + '</b>' +
                 (d.explanation ? '<br>' + esc(d.explanation) : '') +
               '</div>' +
             '</div>' +
           '</div>';
    }
    h += '</div>';

    h += '<div class="shra-quiz-foot" style="justify-content:center;margin-top:22px">' +
           '<button type="button" class="shra-btn shra-btn-outline" id="shra-quiz-retry"><i class="fa fa-rotate-right"></i> Try again</button>' +
           '<a class="shra-btn shra-btn-primary" href="' + CFG.urls.home + '"><i class="fa fa-graduation-cap"></i> Back to the course</a>' +
         '</div>';

    $('#shra-quiz-run').hide();
    $('#shra-quiz-result').html(h).show();
    $('html, body').animate({ scrollTop: Math.max(0, $root.offset().top - 70) }, 250);
  }

  /** Keep the sidebar honest after an attempt without a page reload. */
  function paintQuizStep(r, stats) {
    var $s = $('#shra-tr-quizstep .t');
    if (!$s.length) { return; }
    var best = stats && typeof stats.best !== 'undefined' ? stats.best : r.best;
    var passed = stats ? !!stats.passed : !!r.passed;
    var label = $s.contents().first().text();
    $s.html(esc(label.trim()) +
      '<br><small style="color:' + (passed ? 'var(--green)' : 'var(--red)') + '">best ' + best + '% · pass mark ' + r.pass + '%</small>');
  }

  /* ─────────────────────────── Wiring ─────────────────────────── */

  $(function () {
    CFG = window.SHRA_TRAINING;
    $root = $('#shra-tr');
    if (!CFG || !$root.length) { return; }

    $panes = $root.find('.lesson');
    $steps = $root.find('.shra-tr-step[data-step]');
    $panes.filter('.on').addClass('was-open');

    $steps.on('click', function (e) {
      e.preventDefault();
      var step = $(this).data('step');
      show(step === 'quiz' ? 'quiz' : parseInt(step, 10));
    });

    $root.on('click', '[data-tr-next]', function () {
      var lessonId = $panes.filter('.on').data('lesson');
      // Moving on counts as reading it.
      if (lessonId && !unticked[lessonId] && !$('.shra-tr-mark[data-lesson="' + lessonId + '"]').hasClass('is-done')) {
        mark(lessonId, false, Math.round((Date.now() - openedAt) / 1000), true);
      }
      if (current === CFG.lessons - 1) {
        if (CFG.hasQuiz) { show('quiz'); } else { window.location.href = CFG.urls.home; }
        return;
      }
      show((parseInt(current, 10) || 0) + 1);
    });

    $root.on('click', '[data-tr-prev]', function () {
      if (current === 'quiz') { show(CFG.lessons - 1); return; }
      if (current > 0) { show(current - 1); }
    });

    $root.on('click', '.shra-tr-mark', function () {
      var $b = $(this);
      var undo = $b.hasClass('is-done');
      if (undo) { unticked[$b.data('lesson')] = true; } else { delete unticked[$b.data('lesson')]; }
      mark($b.data('lesson'), undo, Math.round((Date.now() - openedAt) / 1000));
    });

    $root.on('click', '#shra-quiz-go', quizStart);
    $root.on('click', '#shra-quiz-retry', quizStart);
    $root.on('click', '.shra-quiz-opt', function () { pick($(this)); });
    $root.on('click', '#shra-quiz-next', next);

    // Keyboard: A–D answers a question, Enter moves on. Ignored while typing.
    $(document).on('keydown', function (e) {
      if ($(e.target).is('input, textarea, select')) { return; }
      if (!$('#shra-quiz-run').is(':visible')) { return; }
      var k = e.key.toUpperCase();
      var idx = 'ABCDEFGH'.indexOf(k);
      if (idx > -1) {
        var $o = $('#shra-quiz-opts .shra-quiz-opt').eq(idx);
        if ($o.length && !Q.locked) { pick($o); }
      } else if (e.key === 'Enter' && $('#shra-quiz-next').is(':visible')) {
        e.preventDefault();
        next();
      }
    });
  });
})(jQuery);
