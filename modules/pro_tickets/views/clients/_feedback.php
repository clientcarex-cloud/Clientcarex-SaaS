<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Customer satisfaction feedback UI — shared by the ticket list and single
 * ticket views.
 *
 * Expects (both optional, set before include):
 *   $feedback_pending  array of {ticketid, subject, agent_name} — closed by
 *                      staff, not yet rated → rendered as a toast stack.
 *   $feedback_autoopen array {ticket_id, subject, agent} — open the rating
 *                      modal for this ticket on page load (single view of a
 *                      closed, unrated ticket).
 */
$feedback_pending  = isset($feedback_pending) && is_array($feedback_pending) ? $feedback_pending : [];
$feedback_autoopen = isset($feedback_autoopen) && is_array($feedback_autoopen) ? $feedback_autoopen : null;

$ptkc_fb_star_labels = [
    1 => _l('pro_tickets_fb_r1'),
    2 => _l('pro_tickets_fb_r2'),
    3 => _l('pro_tickets_fb_r3'),
    4 => _l('pro_tickets_fb_r4'),
    5 => _l('pro_tickets_fb_r5'),
];
?>

<!-- ── Feedback modal ─────────────────────────────────────────────────── -->
<div class="ptkc-fb-overlay" id="ptkc-fb-overlay" hidden>
    <div class="ptkc-fb-modal" role="dialog" aria-modal="true" aria-labelledby="ptkc-fb-title">
        <button type="button" class="ptkc-fb-close" data-ptkc-fb-close aria-label="&times;">&times;</button>

        <div class="ptkc-fb-body" id="ptkc-fb-form-state">
            <div class="ptkc-fb-icon" id="ptkc-fb-mood"><i class="fa-regular fa-face-smile"></i></div>
            <h4 class="ptkc-fb-title" id="ptkc-fb-title"><?= _l('pro_tickets_feedback_title'); ?></h4>
            <div class="ptkc-fb-sub" id="ptkc-fb-sub"></div>
            <div class="ptkc-fb-agent" id="ptkc-fb-agent" hidden></div>

            <div class="ptkc-fb-stars" id="ptkc-fb-stars">
                <?php for ($i = 1; $i <= 5; $i++) { ?>
                <button type="button" class="ptkc-fb-star" data-rating="<?= $i; ?>"
                    title="<?= e($ptkc_fb_star_labels[$i]); ?>" aria-label="<?= e($ptkc_fb_star_labels[$i]); ?>">
                    <i class="fa-solid fa-star"></i>
                </button>
                <?php } ?>
            </div>
            <div class="ptkc-fb-star-label" id="ptkc-fb-star-label">&nbsp;</div>

            <textarea class="form-control ptkc-fb-comment" id="ptkc-fb-comment" rows="3"
                placeholder="<?= _l('pro_tickets_feedback_comment_ph'); ?>"></textarea>

            <div class="ptkc-fb-error" id="ptkc-fb-error" hidden></div>

            <div class="ptkc-fb-actions">
                <button type="button" class="ptkc-btn ptkc-btn-ghost" data-ptkc-fb-close><?= _l('pro_tickets_feedback_later'); ?></button>
                <button type="button" class="ptkc-btn ptkc-btn-primary" id="ptkc-fb-submit">
                    <i class="fa-solid fa-paper-plane"></i> <?= _l('pro_tickets_feedback_submit'); ?>
                </button>
            </div>
        </div>

        <div class="ptkc-fb-body ptkc-fb-thanks" id="ptkc-fb-thanks-state" hidden>
            <div class="ptkc-fb-icon is-success"><i class="fa-solid fa-heart"></i></div>
            <h4 class="ptkc-fb-title"><?= _l('pro_tickets_feedback_thanks_title'); ?></h4>
            <div class="ptkc-fb-sub"><?= _l('pro_tickets_feedback_thanks'); ?></div>
        </div>
    </div>
</div>

<?php if (!empty($feedback_pending)) { ?>
<!-- ── "Closed by our team — how did we do?" notification stack ───────── -->
<div class="ptkc-fb-toasts" id="ptkc-fb-toasts">
    <?php foreach ($feedback_pending as $fp) { ?>
    <div class="ptkc-fb-toast" data-ticket="<?= (int) $fp->ticketid; ?>"
        data-subject="<?= e($fp->subject); ?>" data-agent="<?= e((string) $fp->agent_name); ?>" hidden>
        <div class="ptkc-fb-toast-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="ptkc-fb-toast-main">
            <div class="ptkc-fb-toast-title"><?= _l('pro_tickets_feedback_toast_title'); ?></div>
            <div class="ptkc-fb-toast-text"><?= _l('pro_tickets_feedback_toast_text', '#' . (int) $fp->ticketid . ' — ' . e($fp->subject)); ?></div>
            <div class="ptkc-fb-toast-actions">
                <button type="button" class="ptkc-btn ptkc-btn-primary ptkc-btn-xs" data-ptkc-fb-rate>
                    <i class="fa-regular fa-star"></i> <?= _l('pro_tickets_feedback_rate_now'); ?>
                </button>
                <button type="button" class="ptkc-btn ptkc-btn-ghost ptkc-btn-xs" data-ptkc-fb-snooze><?= _l('pro_tickets_feedback_later'); ?></button>
            </div>
        </div>
        <button type="button" class="ptkc-fb-toast-close" data-ptkc-fb-snooze aria-label="&times;">&times;</button>
    </div>
    <?php } ?>
</div>
<?php } ?>

<script>
(function () {
    'use strict';

    var starLabels = <?= json_encode(array_values($ptkc_fb_star_labels)); ?>;
    var endpoint   = '<?= site_url('clients/pro_tickets/feedback'); ?>';

    var overlay  = document.getElementById('ptkc-fb-overlay');
    if (!overlay) { return; }
    var stars    = overlay.querySelectorAll('.ptkc-fb-star');
    var rating   = 0;
    var ticketId = 0;
    var busy     = false;

    function snoozeKey(id) { return 'ptkc_fb_snooze_' + id; }

    // Face + colorway per rating; index 0 = the neutral resting state.
    var MOODS = [
        'fa-regular fa-face-smile',      // no rating yet
        'fa-solid fa-face-angry',        // 1 - very poor
        'fa-solid fa-face-frown',        // 2 - poor
        'fa-solid fa-face-meh',          // 3 - okay
        'fa-solid fa-face-smile-beam',   // 4 - good
        'fa-solid fa-face-grin-stars'    // 5 - excellent
    ];
    var moodEl = document.getElementById('ptkc-fb-mood');

    function paintMood(value) {
        if (!moodEl || moodEl.dataset.mood === String(value)) { return; }
        moodEl.dataset.mood = String(value);
        moodEl.className = 'ptkc-fb-icon' + (value > 0 ? ' is-mood-' + value : '');
        moodEl.innerHTML = '<i class="' + MOODS[value] + '"></i>';
        // Retrigger the pop animation on every mood change.
        void moodEl.offsetWidth;
        moodEl.classList.add('pop');
    }

    function paintStars(value) {
        stars.forEach(function (btn) {
            btn.classList.toggle('is-on', parseInt(btn.dataset.rating, 10) <= value);
        });
        document.getElementById('ptkc-fb-star-label').textContent =
            value > 0 ? starLabels[value - 1] : ' ';
        paintMood(value);
    }

    function openModal(id, subject, agent) {
        ticketId = parseInt(id, 10);
        rating   = 0;
        paintStars(0);
        document.getElementById('ptkc-fb-comment').value = '';
        document.getElementById('ptkc-fb-error').hidden  = true;
        document.getElementById('ptkc-fb-form-state').hidden   = false;
        document.getElementById('ptkc-fb-thanks-state').hidden = true;
        document.getElementById('ptkc-fb-sub').textContent = '#' + ticketId + ' — ' + (subject || '');
        var agentEl = document.getElementById('ptkc-fb-agent');
        if (agent) {
            agentEl.textContent = <?= json_encode(_l('pro_tickets_feedback_agent', '%s')); ?>.replace('%s', agent);
            agentEl.hidden = false;
        } else {
            agentEl.hidden = true;
        }
        overlay.hidden = false;
        requestAnimationFrame(function () { overlay.classList.add('is-open'); });
    }

    function closeModal() {
        overlay.classList.remove('is-open');
        setTimeout(function () { overlay.hidden = true; }, 180);
        if (ticketId) {
            try { sessionStorage.setItem(snoozeKey(ticketId), '1'); } catch (e) {}
        }
    }

    window.ptkcFeedbackOpen = openModal;

    stars.forEach(function (btn) {
        btn.addEventListener('click', function () {
            rating = parseInt(btn.dataset.rating, 10);
            paintStars(rating);
        });
        btn.addEventListener('mouseenter', function () {
            paintStars(parseInt(btn.dataset.rating, 10));
        });
    });
    document.getElementById('ptkc-fb-stars').addEventListener('mouseleave', function () {
        paintStars(rating);
    });

    overlay.querySelectorAll('[data-ptkc-fb-close]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) { closeModal(); }
    });

    document.getElementById('ptkc-fb-submit').addEventListener('click', function () {
        var errEl = document.getElementById('ptkc-fb-error');
        if (rating < 1) {
            errEl.textContent = <?= json_encode(_l('pro_tickets_feedback_rating_required')); ?>;
            errEl.hidden = false;
            return;
        }
        if (busy) { return; }
        busy = true;
        errEl.hidden = true;

        // jQuery $.post so the theme's csrf ajaxSetup token rides along.
        $.post(endpoint, {
            ticket_id: ticketId,
            rating: rating,
            comment: document.getElementById('ptkc-fb-comment').value
        }).done(function (res) {
            if (typeof res === 'string') { try { res = JSON.parse(res); } catch (e) { res = {}; } }
            if (res && res.success) {
                try { sessionStorage.removeItem(snoozeKey(ticketId)); } catch (e) {}
                var toast = document.querySelector('.ptkc-fb-toast[data-ticket="' + ticketId + '"]');
                if (toast) { toast.remove(); }
                document.getElementById('ptkc-fb-form-state').hidden   = true;
                document.getElementById('ptkc-fb-thanks-state').hidden = false;
                setTimeout(function () {
                    overlay.classList.remove('is-open');
                    setTimeout(function () { window.location.reload(); }, 180);
                }, 1400);
            } else {
                errEl.textContent = (res && res.message) || 'Error';
                errEl.hidden = false;
            }
        }).fail(function () {
            errEl.textContent = 'Error';
            errEl.hidden = false;
        }).always(function () {
            busy = false;
        });
    });

    // ── Toast stack (staff-closed tickets waiting for a rating) ──────────
    var toasts = document.querySelectorAll('.ptkc-fb-toast');
    var shown  = 0;
    toasts.forEach(function (toast) {
        var id = toast.dataset.ticket;
        var snoozed = false;
        try { snoozed = sessionStorage.getItem(snoozeKey(id)) === '1'; } catch (e) {}
        if (snoozed) { toast.remove(); return; }

        toast.hidden = false;
        setTimeout(function () { toast.classList.add('is-in'); }, 250 + (shown++) * 220);

        toast.querySelectorAll('[data-ptkc-fb-snooze]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                try { sessionStorage.setItem(snoozeKey(id), '1'); } catch (e) {}
                toast.classList.remove('is-in');
                setTimeout(function () { toast.remove(); }, 250);
            });
        });
        var rateBtn = toast.querySelector('[data-ptkc-fb-rate]');
        if (rateBtn) {
            rateBtn.addEventListener('click', function () {
                openModal(id, toast.dataset.subject, toast.dataset.agent);
            });
        }
    });

    // ── Auto-open on a freshly closed, unrated ticket (single view) ──────
    <?php if ($feedback_autoopen) { ?>
    (function () {
        var id = <?= (int) $feedback_autoopen['ticket_id']; ?>;
        var snoozed = false;
        try { snoozed = sessionStorage.getItem(snoozeKey(id)) === '1'; } catch (e) {}
        if (!snoozed) {
            setTimeout(function () {
                openModal(id, <?= json_encode((string) $feedback_autoopen['subject']); ?>, <?= json_encode((string) $feedback_autoopen['agent']); ?>);
            }, 450);
        }
    })();
    <?php } ?>
})();
</script>
