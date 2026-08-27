<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<style>
/* ═══════════════════════════════════════════════════════════════════════
   Pro Tickets — client portal UI
   Scoped under .ptkc-wrap so the customer theme is never affected.
   Mirrors the admin Pro Tickets palette for a consistent modern look.
   ═══════════════════════════════════════════════════════════════════════ */
.ptkc-wrap {
    --ptkc-bg: #f8fafc;
    --ptkc-card: #ffffff;
    --ptkc-border: #e2e8f0;
    --ptkc-text: #0f172a;
    --ptkc-muted: #64748b;
    --ptkc-primary: #4f46e5;
    --ptkc-primary-soft: #eef2ff;
    --ptkc-radius: 12px;
    --ptkc-shadow: 0 1px 2px rgba(15, 23, 42, .05);
    color: var(--ptkc-text);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', sans-serif;
}
.ptkc-wrap * { box-sizing: border-box; }

/* ── Header ─────────────────────────────────────────────────────────── */
.ptkc-header {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.ptkc-title {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -.02em;
    display: inline-flex;
    align-items: center;
    gap: 9px;
}
.ptkc-title i { color: var(--ptkc-primary); }
.ptkc-header-actions { margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap; }

/* ── Buttons ────────────────────────────────────────────────────────── */
.ptkc-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 17px;
    border-radius: 9px;
    border: 1px solid transparent;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s;
    line-height: 1.2;
}
.ptkc-btn-primary { background: var(--ptkc-primary); color: #fff; }
.ptkc-btn-primary:hover { background: #4338ca; color: #fff; text-decoration: none; }
.ptkc-btn-ghost {
    background: var(--ptkc-card);
    border-color: var(--ptkc-border);
    color: var(--ptkc-text);
}
.ptkc-btn-ghost:hover { background: #f1f5f9; color: var(--ptkc-text); text-decoration: none; }

/* ── Summary cards ──────────────────────────────────────────────────── */
/* ── Department cards (compact 4-up grid) ───────────────────────────── */
.ptkc-deptgrid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}
.ptkc-deptcard {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    background: var(--ptkc-card);
    border: 1px solid var(--ptkc-border);
    border-radius: var(--ptkc-radius);
    box-shadow: var(--ptkc-shadow);
    padding: 18px;
    text-decoration: none;
    color: var(--ptkc-text);
    transition: all .15s;
}
.ptkc-deptcard:hover {
    border-color: var(--ptkc-primary);
    text-decoration: none;
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(15, 23, 42, .10);
}
.ptkc-deptcard-icon {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(99, 102, 241, .10);
    color: var(--ptkc-primary);
    font-size: 17px;
    margin-bottom: 6px;
}
.ptkc-deptcard-name {
    font-size: 14px;
    font-weight: 600;
    line-height: 1.3;
}
.ptkc-deptcard-total { font-size: 26px; font-weight: 700; line-height: 1.1; }
.ptkc-deptcard-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 5px 6px;
    margin-top: 8px;
}
.ptkc-deptcard-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 600;
    color: var(--ptkc-text);
    background: #f1f5f9;
    border: 1px solid var(--ptkc-border);
    border-radius: 999px;
    padding: 2px 9px;
    line-height: 1.6;
}
.ptkc-deptcard-chip .dot { width: 7px; height: 7px; border-radius: 999px; display: inline-block; }
.ptkc-deptcard-chip.is-empty { color: var(--ptkc-muted); font-weight: 500; }
.ptkc-deptcard-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: var(--ptkc-primary);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
@media (max-width: 720px) {
    .ptkc-deptgrid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 420px) {
    .ptkc-deptgrid { grid-template-columns: 1fr; }
}

.ptkc-summary {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}
.ptkc-stat {
    background: var(--ptkc-card);
    border: 1px solid var(--ptkc-border);
    border-radius: var(--ptkc-radius);
    padding: 14px 16px;
    text-decoration: none;
    box-shadow: var(--ptkc-shadow);
    transition: all .15s;
    display: block;
}
.ptkc-stat:hover { border-color: #cbd5e1; text-decoration: none; transform: translateY(-1px); }
.ptkc-stat.active { border-color: var(--ptkc-primary); box-shadow: 0 0 0 1px var(--ptkc-primary); }
.ptkc-stat-label {
    font-size: 12.5px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.ptkc-stat-label .dot { width: 8px; height: 8px; border-radius: 999px; display: inline-block; }
.ptkc-stat-value { font-size: 24px; font-weight: 700; margin-top: 6px; color: var(--ptkc-text); }

/* ── Ticket list ────────────────────────────────────────────────────── */
.ptkc-list { display: flex; flex-direction: column; gap: 10px; }
.ptkc-item {
    display: flex;
    align-items: center;
    gap: 14px;
    background: var(--ptkc-card);
    border: 1px solid var(--ptkc-border);
    border-radius: var(--ptkc-radius);
    padding: 14px 18px;
    text-decoration: none;
    color: var(--ptkc-text);
    box-shadow: var(--ptkc-shadow);
    transition: all .15s;
}
.ptkc-item:hover { border-color: #cbd5e1; text-decoration: none; transform: translateY(-1px); }
.ptkc-item.unread { border-left: 3px solid var(--ptkc-primary); }
.ptkc-item-main { min-width: 0; flex: 1; }
.ptkc-item-subject {
    font-weight: 600;
    font-size: 15px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ptkc-item-id { color: var(--ptkc-muted); font-weight: 600; }
.ptkc-item-meta {
    color: var(--ptkc-muted);
    font-size: 12.5px;
    margin-top: 4px;
    display: flex;
    flex-wrap: wrap;
    gap: 4px 14px;
}
.ptkc-item-meta i { margin-right: 4px; opacity: .8; }
.ptkc-item-side { text-align: right; flex-shrink: 0; }
.ptkc-item-side .ptkc-lastreply { font-size: 12px; color: var(--ptkc-muted); margin-top: 6px; }

/* ── Status / priority pills ────────────────────────────────────────── */
.ptkc-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 600;
    color: #fff;
    line-height: 1.5;
    white-space: nowrap;
}
.ptkc-pill-soft {
    background: #f1f5f9;
    color: var(--ptkc-muted);
    border: 1px solid var(--ptkc-border);
}

/* ── Empty state ────────────────────────────────────────────────────── */
.ptkc-empty {
    text-align: center;
    padding: 56px 20px;
    background: var(--ptkc-card);
    border: 1px dashed var(--ptkc-border);
    border-radius: var(--ptkc-radius);
    color: var(--ptkc-muted);
}
.ptkc-empty i { font-size: 34px; color: #cbd5e1; margin-bottom: 12px; display: block; }

/* ── Cards / panels ─────────────────────────────────────────────────── */
.ptkc-card {
    background: var(--ptkc-card);
    border: 1px solid var(--ptkc-border);
    border-radius: var(--ptkc-radius);
    box-shadow: var(--ptkc-shadow);
    overflow: hidden;
}
.ptkc-card-body { padding: 20px; }
.ptkc-card-head {
    padding: 14px 20px;
    border-bottom: 1px solid var(--ptkc-border);
    font-weight: 700;
    font-size: 14px;
}

/* ── Forms ──────────────────────────────────────────────────────────── */
.ptkc-field { margin-bottom: 16px; }
.ptkc-field > label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 6px;
    color: var(--ptkc-text);
}
.ptkc-wrap .form-control,
.ptkc-input {
    border-radius: 9px !important;
    border: 1px solid var(--ptkc-border) !important;
    box-shadow: none !important;
    font-size: 14px;
}
.ptkc-wrap .form-control:focus {
    border-color: var(--ptkc-primary) !important;
    box-shadow: 0 0 0 3px var(--ptkc-primary-soft) !important;
}
.ptkc-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 600px) { .ptkc-grid-2 { grid-template-columns: 1fr; } }

/* ── Single-ticket layout ───────────────────────────────────────────── */
.ptkc-single { display: grid; grid-template-columns: 300px 1fr; gap: 20px; align-items: start; }
@media (max-width: 860px) { .ptkc-single { grid-template-columns: 1fr; } }
.ptkc-single-side { display: flex; flex-direction: column; gap: 20px; }
.ptkc-single-side > .ptkc-card { margin: 0; }

/* To-do progress (read-only, client portal) */
.ptkc-todo-progress { margin-bottom: 12px; }
.ptkc-todo-progress-head { display: flex; justify-content: space-between; align-items: baseline; font-size: 12px; color: var(--ptkc-muted); margin-bottom: 5px; }
.ptkc-todo-progress-head strong { font-size: 13px; color: var(--ptkc-text); }
.ptkc-todo-progress-head strong.is-done { color: #16a34a; }
.ptkc-todo-progress-track { height: 7px; border-radius: 999px; background: var(--ptkc-border); overflow: hidden; }
.ptkc-todo-progress-bar { display: block; height: 100%; border-radius: 999px; background: var(--ptkc-primary); transition: width .35s ease; }
.ptkc-todo-progress-bar.is-complete { background: #16a34a; }
.ptkc-todo-list { display: flex; flex-direction: column; gap: 2px; }
.ptkc-todo { display: flex; align-items: flex-start; gap: 8px; padding: 7px 0; border-top: 1px solid #f1f5f9; font-size: 13px; }
.ptkc-todo-dot { color: var(--ptkc-muted); font-size: 13px; line-height: 1.5; }
.ptkc-todo.is-done .ptkc-todo-dot { color: #16a34a; }
.ptkc-todo.is-done .ptkc-todo-title { color: var(--ptkc-muted); text-decoration: line-through; }
.ptkc-todo-main { flex: 1; min-width: 0; }
.ptkc-todo-title { color: var(--ptkc-text); }
.ptkc-todo-desc { font-size: 12px; color: var(--ptkc-muted); margin-top: 2px; white-space: pre-line; }
.ptkc-todo-due { font-size: 11px; color: var(--ptkc-muted); white-space: nowrap; line-height: 1.5; }
.ptkc-info-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
}
.ptkc-info-row:last-child { border-bottom: 0; }
.ptkc-info-row .k { color: var(--ptkc-muted); }
.ptkc-info-row .v { font-weight: 600; text-align: right; }

/* ── Conversation thread ────────────────────────────────────────────── */
.ptkc-thread { display: flex; flex-direction: column; gap: 14px; }
.ptkc-msg {
    background: var(--ptkc-card);
    border: 1px solid var(--ptkc-border);
    border-radius: var(--ptkc-radius);
    box-shadow: var(--ptkc-shadow);
    overflow: hidden;
}
.ptkc-msg.staff { border-color: #c7d2fe; }
.ptkc-msg-head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 18px;
    border-bottom: 1px solid #f1f5f9;
}
.ptkc-avatar {
    width: 38px; height: 38px;
    border-radius: 999px;
    background: var(--ptkc-primary-soft);
    color: var(--ptkc-primary);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 15px;
    flex-shrink: 0;
}
.ptkc-msg.staff .ptkc-avatar { background: #ecfdf5; color: #059669; }
.ptkc-avatar-img { overflow: hidden; padding: 0; }
.ptkc-avatar-img img { width: 100%; height: 100%; object-fit: cover; border-radius: 999px; display: block; }
.ptkc-msg-who { font-weight: 600; font-size: 14px; }
.ptkc-msg-role { font-size: 11.5px; color: var(--ptkc-muted); }
.ptkc-msg-date { margin-left: auto; font-size: 12px; color: var(--ptkc-muted); }
.ptkc-msg-body { padding: 16px 18px; font-size: 14px; line-height: 1.6; word-wrap: break-word; }
.ptkc-msg-body img { max-width: 100%; height: auto; }
.ptkc-attach {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin: 6px 8px 0 0;
    padding: 6px 11px;
    border: 1px solid var(--ptkc-border);
    border-radius: 8px;
    font-size: 12.5px;
    text-decoration: none;
    color: var(--ptkc-text);
    background: #f8fafc;
}
.ptkc-attach:hover { border-color: var(--ptkc-primary); text-decoration: none; }

.ptkc-attachments { margin-top: 12px; display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-start; }
.ptkc-attach-img {
    position: relative;
    display: inline-block;
    border: 1px solid var(--ptkc-border);
    border-radius: 10px;
    overflow: hidden;
    background: #f8fafc;
    line-height: 0;
    text-decoration: none;
    transition: border-color .15s ease, box-shadow .15s ease;
}
.ptkc-attach-img img {
    display: block;
    width: 160px;
    height: 160px;
    object-fit: cover;
    margin: 0;
}
.ptkc-attach-img::after {
    content: "\f00e"; /* fa magnifying-glass-plus */
    font-family: "Font Awesome 6 Free", "Font Awesome 5 Free", FontAwesome;
    font-weight: 900;
    position: absolute;
    top: 8px; right: 8px;
    width: 26px; height: 26px;
    line-height: 26px;
    text-align: center;
    font-size: 12px;
    color: #fff;
    background: rgba(15, 23, 42, .55);
    border-radius: 6px;
    opacity: 0;
    transition: opacity .15s ease;
}
.ptkc-attach-img:hover {
    border-color: var(--ptkc-primary);
    box-shadow: 0 4px 14px rgba(15, 23, 42, .12);
}
.ptkc-attach-img:hover::after { opacity: 1; }

.ptkc-reply-box { margin-bottom: 20px; }
.ptkc-mt { margin-top: 20px; }

/* ── Reply-count badge (ticket list) ────────────────────────────────── */
.ptkc-replies {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 9px;
    border-radius: 999px;
    background: var(--ptkc-primary-soft);
    color: var(--ptkc-primary);
    font-size: 11.5px;
    font-weight: 700;
    line-height: 1.6;
    flex-shrink: 0;
}
.ptkc-replies.is-zero { background: #f1f5f9; color: var(--ptkc-muted); }
.ptkc-replies i { font-size: 11px; }

/* ── Feedback (CSAT) — small button sizes ───────────────────────────── */
.ptkc-btn-xs { padding: 5px 11px; font-size: 12px; border-radius: 7px; }

/* ── Feedback modal ─────────────────────────────────────────────────── */
/* display:flex would defeat the `hidden` attribute — restore it explicitly,
   otherwise the invisible fixed overlay blocks every click on the page. */
.ptkc-fb-overlay[hidden],
.ptkc-fb-toast[hidden] { display: none !important; }
.ptkc-fb-overlay {
    position: fixed;
    inset: 0;
    z-index: 10050;
    background: rgba(15, 23, 42, .45);
    backdrop-filter: blur(3px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    opacity: 0;
    transition: opacity .18s ease;
}
.ptkc-fb-overlay.is-open { opacity: 1; }
.ptkc-fb-modal {
    position: relative;
    width: 100%;
    max-width: 430px;
    background: var(--ptkc-card);
    border-radius: 16px;
    box-shadow: 0 24px 60px rgba(15, 23, 42, .25);
    transform: translateY(14px) scale(.97);
    transition: transform .18s ease;
}
.ptkc-fb-overlay.is-open .ptkc-fb-modal { transform: translateY(0) scale(1); }
.ptkc-fb-close {
    position: absolute;
    top: 10px; right: 12px;
    border: 0; background: transparent;
    font-size: 22px; line-height: 1;
    color: var(--ptkc-muted);
    cursor: pointer;
}
.ptkc-fb-close:hover { color: var(--ptkc-text); }
.ptkc-fb-body { padding: 30px 28px 26px; text-align: center; }
.ptkc-fb-icon {
    width: 54px; height: 54px;
    margin: 0 auto 12px;
    border-radius: 999px;
    background: var(--ptkc-primary-soft);
    color: var(--ptkc-primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
}
.ptkc-fb-icon.is-success { background: #ecfdf5; color: #059669; }
/* Mood colorways — the face tracks the hovered/selected star rating. */
.ptkc-fb-icon.is-mood-1 { background: #fee2e2; color: #dc2626; }
.ptkc-fb-icon.is-mood-2 { background: #ffedd5; color: #ea580c; }
.ptkc-fb-icon.is-mood-3 { background: #fef3c7; color: #d97706; }
.ptkc-fb-icon.is-mood-4 { background: #dcfce7; color: #16a34a; }
.ptkc-fb-icon.is-mood-5 { background: #ecfdf5; color: #059669; }
.ptkc-fb-icon.pop { animation: ptkcFbPop .3s cubic-bezier(.34, 1.56, .64, 1); }
@keyframes ptkcFbPop {
    0%   { transform: scale(.75); }
    60%  { transform: scale(1.12); }
    100% { transform: scale(1); }
}
@media (prefers-reduced-motion: reduce) {
    .ptkc-fb-icon.pop { animation: none; }
}
.ptkc-fb-title { margin: 0 0 4px; font-size: 18px; font-weight: 700; letter-spacing: -.01em; color: var(--ptkc-text, #0f172a); }
.ptkc-fb-sub { font-size: 13px; color: var(--ptkc-muted, #64748b); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ptkc-fb-agent { font-size: 12px; color: var(--ptkc-muted, #64748b); margin-top: 3px; }
.ptkc-fb-stars { display: flex; justify-content: center; gap: 6px; margin: 18px 0 4px; }
.ptkc-fb-star {
    border: 0; background: transparent; cursor: pointer;
    font-size: 30px; color: #e2e8f0;
    padding: 2px 4px;
    transition: transform .12s ease, color .12s ease;
}
.ptkc-fb-star:hover { transform: scale(1.15); }
.ptkc-fb-star.is-on { color: #f59e0b; }
.ptkc-fb-star-label { min-height: 18px; font-size: 12.5px; font-weight: 600; color: var(--ptkc-muted, #64748b); margin-bottom: 12px; }
.ptkc-fb-comment { resize: vertical; }
.ptkc-fb-error {
    margin-top: 10px;
    font-size: 12.5px;
    font-weight: 600;
    color: #dc2626;
}
.ptkc-fb-actions { display: flex; justify-content: center; gap: 10px; margin-top: 18px; }
.ptkc-fb-thanks .ptkc-fb-sub { white-space: normal; }

/* Modal lives outside .ptkc-wrap inheritance on some themes — restate the
   button look so it renders identically wherever the partial is included. */
.ptkc-fb-overlay .ptkc-btn,
.ptkc-fb-toast .ptkc-btn {
    display: inline-flex; align-items: center; gap: 7px;
    border-radius: 9px; border: 1px solid transparent;
    font-weight: 600; cursor: pointer; text-decoration: none; line-height: 1.2;
}
.ptkc-fb-overlay .ptkc-btn-primary, .ptkc-fb-toast .ptkc-btn-primary { background: #4f46e5; color: #fff; }
.ptkc-fb-overlay .ptkc-btn-primary:hover, .ptkc-fb-toast .ptkc-btn-primary:hover { background: #4338ca; color: #fff; }
.ptkc-fb-overlay .ptkc-btn-ghost, .ptkc-fb-toast .ptkc-btn-ghost { background: #fff; border-color: #e2e8f0; color: #0f172a; }
.ptkc-fb-overlay .ptkc-btn-ghost:hover, .ptkc-fb-toast .ptkc-btn-ghost:hover { background: #f1f5f9; }
.ptkc-fb-overlay .ptkc-btn:not(.ptkc-btn-xs) { padding: 9px 17px; font-size: 13.5px; }

/* ── Feedback toast notifications ───────────────────────────────────── */
.ptkc-fb-toasts {
    position: fixed;
    top: 18px; right: 18px;
    z-index: 10040;
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: min(370px, calc(100vw - 36px));
}
.ptkc-fb-toast {
    position: relative;
    display: flex;
    gap: 12px;
    align-items: flex-start;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #4f46e5;
    border-radius: 12px;
    padding: 14px 34px 14px 14px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, .16);
    opacity: 0;
    transform: translateX(24px);
    transition: opacity .25s ease, transform .25s ease;
}
.ptkc-fb-toast.is-in { opacity: 1; transform: translateX(0); }
.ptkc-fb-toast-icon {
    width: 34px; height: 34px;
    flex-shrink: 0;
    border-radius: 999px;
    background: #eef2ff;
    color: #4f46e5;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
}
.ptkc-fb-toast-main { min-width: 0; }
.ptkc-fb-toast-title { font-size: 13.5px; font-weight: 700; color: #0f172a; }
.ptkc-fb-toast-text {
    font-size: 12.5px; color: #64748b; margin-top: 2px;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.ptkc-fb-toast-actions { display: flex; gap: 8px; margin-top: 10px; }
.ptkc-fb-toast-close {
    position: absolute;
    top: 8px; right: 10px;
    border: 0; background: transparent;
    font-size: 18px; line-height: 1;
    color: #94a3b8;
    cursor: pointer;
}
.ptkc-fb-toast-close:hover { color: #0f172a; }

/* ── Feedback summary card (single ticket, side column) ─────────────── */
.ptkc-fb-given-stars { display: inline-flex; gap: 3px; font-size: 15px; color: #f59e0b; }
.ptkc-fb-given-stars .off { color: #e2e8f0; }
.ptkc-fb-given-comment {
    margin-top: 10px;
    font-size: 13px;
    color: var(--ptkc-muted);
    background: #f8fafc;
    border: 1px solid var(--ptkc-border);
    border-radius: 9px;
    padding: 10px 12px;
    white-space: pre-line;
}
.ptkc-fb-cta { text-align: center; padding: 4px 0 2px; }
.ptkc-fb-cta .ptkc-fb-cta-text { font-size: 13px; color: var(--ptkc-muted); margin-bottom: 12px; }
</style>
