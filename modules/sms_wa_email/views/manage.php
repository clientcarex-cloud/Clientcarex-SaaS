<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
    /* ═══════════════════════════════════════════════════════
       MODERN DESIGN SYSTEM — Communication Templates
       ═══════════════════════════════════════════════════════ */

    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    .ccx-comm-page {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    /* ── Page Header ── */
    .ccx-page-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 24px;
    }

    .ccx-page-header-icon {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 20px;
        box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);
    }

    .ccx-page-header h4 {
        margin: 0;
        font-weight: 700;
        font-size: 22px;
        color: #1e1b4b;
        letter-spacing: -0.3px;
    }

    .ccx-page-header p {
        margin: 2px 0 0;
        font-size: 13px;
        color: #6b7280;
        font-weight: 400;
    }

    /* ── Main Card (Glassmorphism) ── */
    .ccx-main-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid rgba(229, 231, 235, 0.7);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04), 0 10px 30px -5px rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }

    .ccx-main-card .panel-body {
        padding: 0;
    }

    /* ── Pill Tabs ── */
    .ccx-pill-bar {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 16px 24px;
        background: linear-gradient(180deg, #f9fafb 0%, #f3f4f6 100%);
        border-bottom: 1px solid #e5e7eb;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .ccx-pill-bar::-webkit-scrollbar {
        height: 0;
    }

    .ccx-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 500;
        color: #6b7280;
        background: transparent;
        border: 1.5px solid transparent;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.25s cubic-bezier(.4, 0, .2, 1);
        text-decoration: none !important;
    }

    .ccx-pill i {
        font-size: 15px;
    }

    .ccx-pill:hover {
        background: #fff;
        border-color: #e5e7eb;
        color: #374151;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    }

    .ccx-pill.active {
        color: #fff !important;
        border-color: transparent;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transform: translateY(-1px);
    }

    /* Channel-specific active pill colors */
    .ccx-pill.active[data-channel="sms"] {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
    }

    .ccx-pill.active[data-channel="official_whatsapp"] {
        background: linear-gradient(135deg, #22c55e, #16a34a);
    }

    .ccx-pill.active[data-channel="email"] {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .ccx-pill.active[data-channel="ai_call_agent"] {
        background: linear-gradient(135deg, #a855f7, #7c3aed);
    }

    /* Icon colors when NOT active */
    .ccx-pill:not(.active) .pill-icon-sms {
        color: #6366f1;
    }

    .ccx-pill:not(.active) .pill-icon-wa {
        color: #22c55e;
    }

    .ccx-pill:not(.active) .pill-icon-email {
        color: #f59e0b;
    }

    .ccx-pill:not(.active) .pill-icon-ai {
        color: #a855f7;
    }

    /* ── Tab Content ── */
    .ccx-tab-body {
        padding: 28px;
    }

    .ccx-tab-body .tab-pane {
        animation: ccxFadeSlideIn 0.3s ease;
    }

    @keyframes ccxFadeSlideIn {
        from {
            opacity: 0;
            transform: translateY(8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ── Section Header inside tab ── */
    .ccx-section-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        font-size: 17px;
        font-weight: 600;
        color: #1f2937;
    }

    .ccx-section-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    /* ── Gradient Balance Cards ── */
    .ccx-balance-row {
        display: flex;
        gap: 16px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .ccx-balance-card {
        flex: 1;
        min-width: 240px;
        border-radius: 14px;
        padding: 20px 22px;
        color: #fff;
        position: relative;
        overflow: hidden;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .ccx-balance-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.18);
    }

    .ccx-balance-card::before {
        content: '';
        position: absolute;
        top: -30px;
        right: -30px;
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.12);
    }

    .ccx-balance-card::after {
        content: '';
        position: absolute;
        bottom: -20px;
        left: -20px;
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
    }

    .ccx-balance-card .bc-label {
        font-size: 11.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.85;
        margin-bottom: 6px;
    }

    .ccx-balance-card .bc-value {
        font-size: 28px;
        font-weight: 700;
        line-height: 1.1;
        margin-bottom: 10px;
    }

    .ccx-balance-card .bc-expiry {
        font-size: 12px;
        opacity: 0.9;
    }

    .ccx-balance-card .bc-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.22);
        color: #fff;
        backdrop-filter: blur(4px);
    }

    .ccx-balance-card .bc-badge.expired {
        background: rgba(239, 68, 68, 0.8);
    }

    .ccx-balance-card .bc-badge.no-expiry {
        background: rgba(255, 255, 255, 0.18);
    }

    /* Inactive card overlay */
    .ccx-balance-card.bc-inactive {
        opacity: 0.55;
        filter: grayscale(0.5);
    }

    .bc-inactive-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        background: rgba(239, 68, 68, 0.85);
        color: #fff;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.3px;
    }

    /* ── Per-channel sending switch (superadmin only, one per balance card) ── */
    .ccx-balance-card .bc-send-switch {
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid rgba(255, 255, 255, 0.28);
    }

    .ccx-balance-card .bc-send-switch-text {
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: 0.6px;
        text-transform: uppercase;
    }

    .ccx-balance-card .bc-send-switch.is-off .bc-send-switch-text {
        opacity: 0.75;
    }

    /* The generic .ccx-switch sits on white panels — restyle it for the
       coloured gradient of a balance card, where grey-on-colour disappears. */
    .ccx-switch.bc-switch .slider {
        background: rgba(0, 0, 0, 0.28);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.5);
    }

    .ccx-switch.bc-switch input:checked+.slider {
        background: #22c55e;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.75);
    }

    .ccx-switch.bc-switch input:disabled+.slider {
        opacity: 0.6;
        cursor: wait;
    }

    /* Neutral card used when the installation has no allocation row at all */
    .bc-slate {
        background: linear-gradient(135deg, #475569 0%, #1e293b 100%);
        box-shadow: 0 6px 20px rgba(71, 85, 105, 0.3);
    }

    /* Channel gradients */
    .bc-indigo {
        background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
        box-shadow: 0 6px 20px rgba(99, 102, 241, 0.35);
    }

    .bc-green {
        background: linear-gradient(135deg, #22c55e 0%, #15803d 100%);
        box-shadow: 0 6px 20px rgba(34, 197, 94, 0.35);
    }

    .bc-amber {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        box-shadow: 0 6px 20px rgba(245, 158, 11, 0.35);
    }

    .bc-purple {
        background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
        box-shadow: 0 6px 20px rgba(168, 85, 247, 0.35);
    }

    /* ── Channel Stats Row (mini cards) ── */
    .ccx-stats-row {
        display: flex;
        gap: 14px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .ccx-stat-card {
        flex: 1;
        min-width: 160px;
        display: flex;
        align-items: center;
        gap: 14px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 16px 18px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .ccx-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
    }

    .ccx-stat-card .stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }

    .ccx-stat-card .stat-body {
        display: flex;
        flex-direction: column;
    }

    .ccx-stat-card .stat-value {
        font-size: 20px;
        font-weight: 700;
        line-height: 1.2;
        color: #1e293b;
    }

    .ccx-stat-card .stat-label {
        font-size: 11.5px;
        font-weight: 500;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 2px;
    }

    /* ── Info Alert (accent bar) ── */
    .ccx-info-alert {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 450;
        margin-bottom: 24px;
        border-left: 4px solid;
    }

    .ccx-info-alert i {
        font-size: 17px;
        flex-shrink: 0;
    }

    .ccx-info-alert.info-sms {
        background: #eef2ff;
        border-left-color: #6366f1;
        color: #3730a3;
    }

    .ccx-info-alert.info-sms i {
        color: #6366f1;
    }

    .ccx-info-alert.info-wa {
        background: #f0fdf4;
        border-left-color: #22c55e;
        color: #166534;
    }

    .ccx-info-alert.info-wa i {
        color: #22c55e;
    }

    .ccx-info-alert.info-email {
        background: #fffbeb;
        border-left-color: #f59e0b;
        color: #92400e;
    }

    .ccx-info-alert.info-email i {
        color: #f59e0b;
    }

    .ccx-info-alert.info-ai {
        background: #faf5ff;
        border-left-color: #a855f7;
        color: #6b21a8;
    }

    .ccx-info-alert.info-ai i {
        color: #a855f7;
    }

    /* ── Template Table ── */
    .ccx-templates-section {
        margin-top: 4px;
    }

    .ccx-templates-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 14px;
    }

    .ccx-templates-header h5 {
        margin: 0;
        font-size: 15px;
        font-weight: 600;
        color: #374151;
        white-space: nowrap;
    }

    .ccx-add-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        color: #fff;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
    }

    .ccx-add-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
        color: #fff;
    }

    .ccx-add-btn.btn-sms {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
    }

    .ccx-add-btn.btn-wa {
        background: linear-gradient(135deg, #22c55e, #16a34a);
    }

    .ccx-add-btn.btn-email {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .ccx-add-btn.btn-ai {
        background: linear-gradient(135deg, #a855f7, #7c3aed);
    }

    .ccx-table-wrap {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
    }

    .ccx-table-wrap table {
        margin-bottom: 0;
    }

    .ccx-table-wrap thead th {
        background: #f9fafb !important;
        font-size: 11.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #6b7280 !important;
        border-bottom: 1px solid #e5e7eb !important;
        padding: 12px 16px !important;
    }

    .ccx-table-wrap tbody td {
        padding: 12px 16px !important;
        font-size: 13.5px;
        color: #374151;
        border-bottom: 1px solid #f3f4f6 !important;
        vertical-align: middle !important;
    }

    .ccx-table-wrap tbody tr {
        transition: background 0.15s ease;
    }

    .ccx-table-wrap tbody tr:hover {
        background: #f9fafb;
    }

    .ccx-table-wrap tbody tr:last-child td {
        border-bottom: none !important;
    }

    /* Template row action buttons */
    .ccx-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none !important;
    }

    .ccx-action-btn:hover {
        background: #f3f4f6;
        color: #374151;
        border-color: #d1d5db;
        transform: translateY(-1px);
    }

    .ccx-action-btn.btn-delete:hover {
        background: #fef2f2;
        color: #ef4444;
        border-color: #fecaca;
    }

    /* chip badges */
    .ccx-chip {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.2px;
    }

    .ccx-chip-default {
        background: #dcfce7;
        color: #166534;
    }

    .ccx-chip-attach-yes {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .ccx-chip-attach-no {
        background: #f3f4f6;
        color: #9ca3af;
    }

    /* ── System Hooks accordion ── */
    .ccx-hooks-header {
        flex-wrap: wrap;
        gap: 10px;
    }

    .ccx-hooks-tools {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* Right-hand side of a Templates panel header: search + action buttons */
    .ccx-templates-tools {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .ccx-templates-count {
        font-size: 12px;
        font-weight: 600;
        color: #9ca3af;
        white-space: nowrap;
    }

    .ccx-hooks-search,
    .ccx-search-box {
        position: relative;
        display: inline-flex;
        align-items: center;
    }

    .ccx-hooks-search>i.fa-search,
    .ccx-search-box>i.fa-search {
        position: absolute;
        left: 12px;
        font-size: 12px;
        color: #9ca3af;
        pointer-events: none;
    }

    .ccx-hooks-search input,
    .ccx-search-box input {
        width: 270px;
        max-width: 100%;
        padding: 8px 30px 8px 32px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        font-size: 13px;
        color: #374151;
        background: #fff;
        transition: all 0.2s ease;
    }

    .ccx-hooks-search input:focus,
    .ccx-search-box input:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
    }

    .ccx-hooks-search-clear,
    .ccx-search-clear {
        position: absolute;
        right: 10px;
        font-size: 13px;
        color: #9ca3af;
        text-decoration: none !important;
    }

    .ccx-hooks-search-clear:hover,
    .ccx-search-clear:hover {
        color: #6b7280;
    }

    .ccx-hooks-toggle-all {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #4b5563;
        font-size: 12.5px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .ccx-hooks-toggle-all:hover {
        background: #f9fafb;
        border-color: #d1d5db;
        color: #374151;
    }

    .ccx-hooks-accordion {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .ccx-hooks-state {
        border: 1px dashed #e5e7eb;
        border-radius: 12px;
        padding: 30px;
        text-align: center;
        color: #9ca3af;
        font-size: 13px;
    }

    .ccx-acc {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .ccx-acc.open {
        border-color: #d1d5db;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
    }

    .ccx-acc-head {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        cursor: pointer;
        user-select: none;
        background: #fff;
        transition: background 0.15s ease;
    }

    .ccx-acc-head:hover {
        background: #f9fafb;
    }

    .ccx-acc.open .ccx-acc-head {
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
    }

    .ccx-acc-caret {
        color: #9ca3af;
        font-size: 13px;
        width: 12px;
        text-align: center;
        transition: transform 0.2s ease;
    }

    .ccx-acc.open .ccx-acc-caret {
        transform: rotate(90deg);
        color: #6366f1;
    }

    .ccx-acc-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 10px;
        font-size: 14px;
        flex-shrink: 0;
    }

    .ccx-acc-title {
        font-size: 14px;
        font-weight: 600;
        color: #374151;
    }

    .ccx-acc-slug {
        display: block;
        font-size: 10.5px;
        color: #9ca3af;
        font-weight: 400;
        letter-spacing: 0.2px;
    }

    .ccx-acc-meta {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .ccx-acc-body {
        display: none;
        padding: 12px;
        background: #fcfcfd;
    }

    .ccx-acc.open .ccx-acc-body {
        display: block;
    }

    .ccx-acc-body .ccx-table-wrap {
        background: #fff;
    }

    .ccx-acc-body .ccx-table-wrap tbody.ccx-hook-rows:last-child tr:last-child td {
        border-bottom: none !important;
    }

    @media (max-width: 767px) {

        .ccx-hooks-tools,
        .ccx-templates-tools {
            width: 100%;
        }

        .ccx-hooks-search,
        .ccx-hooks-search input,
        .ccx-search-box,
        .ccx-search-box input {
            width: 100%;
        }
    }

    /* ── Settings FAB ── */
    .ccx-settings-fab {
        position: fixed;
        bottom: 40px;
        right: 40px;
        width: 56px;
        height: 56px;
        border-radius: 16px;
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: #fff;
        font-size: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
        z-index: 9999;
        transition: all 0.3s ease;
        border: none;
        text-decoration: none !important;
    }

    .ccx-settings-fab:hover {
        transform: translateY(-3px) rotate(90deg);
        box-shadow: 0 12px 32px rgba(99, 102, 241, 0.5);
        color: #fff;
    }

    /* ── Modal Upgrade ── */
    .ccx-modal .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        overflow: hidden;
    }

    .ccx-modal .modal-header {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: #fff;
        border: none;
        padding: 18px 24px;
    }

    .ccx-modal .modal-header .close {
        color: #fff;
        opacity: 0.7;
        text-shadow: none;
    }

    .ccx-modal .modal-header .close:hover {
        opacity: 1;
    }

    .ccx-modal .modal-title {
        font-size: 16px;
        font-weight: 600;
    }

    .ccx-modal .modal-body {
        padding: 24px;
    }

    .ccx-modal .modal-footer {
        border-top: 1px solid #f3f4f6;
        padding: 16px 24px;
    }

    .ccx-modal .form-label {
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
        display: block;
    }

    .ccx-modal .ccx-input {
        border-radius: 10px !important;
        border: 1.5px solid #e5e7eb !important;
        padding: 10px 14px !important;
        font-size: 14px !important;
        transition: all 0.2s ease !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
        width: 100%;
    }

    .ccx-modal .ccx-input:focus {
        border-color: #6366f1 !important;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12) !important;
        outline: none !important;
    }

    .ccx-modal textarea.ccx-input {
        resize: vertical;
        min-height: 100px;
    }

    /* Toggle switches in modal */
    .ccx-toggle-group {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        margin-top: 16px;
    }

    .ccx-toggle-item {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ccx-toggle-item label.toggle-label {
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        cursor: pointer;
        margin: 0;
    }

    .ccx-switch {
        position: relative;
        width: 44px;
        height: 24px;
    }

    .ccx-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .ccx-switch .slider {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: #d1d5db;
        border-radius: 24px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .ccx-switch .slider::before {
        content: '';
        position: absolute;
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background: #fff;
        border-radius: 50%;
        transition: all 0.3s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
    }

    .ccx-switch input:checked+.slider {
        background: #6366f1;
    }

    .ccx-switch input:checked+.slider::before {
        transform: translateX(20px);
    }

    /* Modal buttons */
    .ccx-btn-cancel {
        padding: 9px 20px;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 500;
        background: #fff;
        color: #6b7280;
        border: 1.5px solid #e5e7eb;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .ccx-btn-cancel:hover {
        background: #f9fafb;
        border-color: #d1d5db;
        color: #374151;
    }

    .ccx-btn-save {
        padding: 9px 22px;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 600;
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: #fff;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
    }

    .ccx-btn-save:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        color: #fff;
    }

    /* Char counter */
    .ccx-char-counter {
        text-align: right;
        font-size: 11px;
        color: #9ca3af;
        margin-top: 4px;
    }

    /* ── Textarea (AI Call) ── */
    .ccx-textarea-premium {
        border-radius: 12px !important;
        border: 1.5px solid #e5e7eb !important;
        padding: 14px !important;
        font-size: 14px !important;
        line-height: 1.6 !important;
        transition: all 0.2s ease !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
    }

    .ccx-textarea-premium:focus {
        border-color: #a855f7 !important;
        box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.12) !important;
        outline: none !important;
    }

    /* ── Save Button (AI Call) ── */
    .ccx-save-ai {
        padding: 10px 24px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        background: linear-gradient(135deg, #a855f7, #7c3aed);
        color: #fff;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 4px 14px rgba(168, 85, 247, 0.3);
    }

    .ccx-save-ai:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(168, 85, 247, 0.4);
        color: #fff;
    }

    /* ── Sub-Tab (Inner) Pill Bar ── */
    .ccx-sub-pill-bar {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 6px 8px;
        background: #f3f4f6;
        border-radius: 10px;
        margin-bottom: 20px;
        width: fit-content;
    }

    .ccx-sub-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 500;
        color: #6b7280;
        background: transparent;
        border: none;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s cubic-bezier(.4, 0, .2, 1);
    }

    .ccx-sub-pill:hover {
        color: #374151;
        background: rgba(255, 255, 255, 0.6);
    }

    .ccx-sub-pill.active {
        color: #374151;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        font-weight: 600;
    }

    .ccx-sub-pill i {
        font-size: 13px;
    }

    /* ── Hooks Placeholder ── */
    .ccx-hooks-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        text-align: center;
        color: #9ca3af;
    }

    .ccx-hooks-placeholder .hooks-icon {
        width: 64px;
        height: 64px;
        border-radius: 16px;
        background: #f3f4f6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        color: #d1d5db;
        margin-bottom: 16px;
    }

    .ccx-hooks-placeholder h5 {
        margin: 0 0 6px;
        font-size: 15px;
        font-weight: 600;
        color: #6b7280;
    }

    .ccx-hooks-placeholder p {
        margin: 0;
        font-size: 13px;
        max-width: 320px;
    }

    /* ── Skeleton shimmer while loading ── */
    @keyframes ccxShimmer {
        0% {
            background-position: -200px 0;
        }

        100% {
            background-position: 200px 0;
        }
    }

    @keyframes ccxShake {

        0%,
        100% {
            transform: translateX(0);
        }

        20%,
        60% {
            transform: translateX(-6px);
        }

        40%,
        80% {
            transform: translateX(6px);
        }
    }

    .ccx-shake {
        animation: ccxShake 0.4s ease;
    }

    .ccx-skeleton-row td {
        position: relative;
    }

    .ccx-skeleton-bar {
        height: 14px;
        border-radius: 6px;
        background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 37%, #f3f4f6 63%);
        background-size: 400px 100%;
        animation: ccxShimmer 1.4s ease infinite;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .ccx-pill-bar {
            padding: 12px 16px;
            gap: 4px;
        }

        .ccx-pill {
            padding: 7px 12px;
            font-size: 12px;
        }

        .ccx-tab-body {
            padding: 20px 16px;
        }

        .ccx-balance-row {
            flex-direction: column;
        }

        .ccx-balance-card {
            min-width: 100%;
        }

        .ccx-stats-row {
            flex-direction: column;
        }

        .ccx-stat-card {
            min-width: 100%;
        }
    }
</style>

<div id="wrapper">
    <div class="content ccx-comm-page">

        <?php if (sms_wa_email_can('settings')) { ?>
            <a href="<?php echo admin_url('sms_wa_email/settings'); ?>" class="ccx-settings-fab" data-toggle="tooltip"
                data-placement="left" title="Advanced Settings">
                <i class="fa fa-cog"></i>
            </a>
        <?php } ?>

        <div class="row">
            <div class="col-md-12">

                <!-- Page Header -->
                <div class="ccx-page-header" style="justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div class="ccx-page-header-icon">
                            <i class="fa fa-paper-plane"></i>
                        </div>
                        <div>
                            <h4>Communication Hub</h4>
                            <p>Manage templates & settings for all your messaging channels</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <?php if (sms_wa_email_can('settings') && function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) { ?>
                        <a href="#" onclick="openRechargeModal(); return false;" class="ccx-btn-save" style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #f59e0b, #d97706); text-decoration: none;">
                            <i class="fa fa-bolt"></i> Recharge
                        </a>
                        <?php } elseif (is_admin()) { ?>
                        <?php // Master / non-SaaS: credits are not bought, they are allocated to this
                              // account's own row in CCX Msgs → This Account. ?>
                        <a href="<?php echo admin_url('ccx_msgs'); ?>" class="ccx-btn-save" style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #f59e0b, #d97706); text-decoration: none;">
                            <i class="fa fa-bolt"></i> Add Credits
                        </a>
                        <?php } ?>
                        <?php if (sms_wa_email_can('auto_schedulers')) { ?>
                        <a href="<?php echo admin_url('sms_wa_email/auto_schedulers'); ?>" class="ccx-btn-save" style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #8b5cf6, #6d28d9); text-decoration: none;">
                            <i class="fa fa-refresh"></i> Auto Schedulers
                        </a>
                        <?php } ?>
                        <?php if (sms_wa_email_can('campaigns')) { ?>
                        <a href="<?php echo admin_url('sms_wa_email/campaigns'); ?>" class="ccx-btn-save" style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #10b981, #059669); text-decoration: none;">
                            <i class="fa fa-bullhorn"></i> Campaigns
                        </a>
                        <?php } ?>
                        <?php if (sms_wa_email_can('settings')) { ?>
                        <a href="<?php echo admin_url('sms_wa_email/debug'); ?>" class="ccx-btn-save" style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #6366f1, #4338ca); text-decoration: none;">
                            <i class="fa fa-stethoscope"></i> Debugger
                        </a>
                        <?php } ?>
                    </div>
                </div>

                <!-- Main Card -->
                <div class="ccx-main-card panel_s">
                    <div class="panel-body">

                        <?php
                        // Determine which channels are fully inactive (both promo + trans disabled)
                        $channel_active = [
                            'sms' => true,
                            'official_whatsapp' => true,
                            'email' => true,
                            'ai_call_agent' => true,
                        ];
                        if (isset($allocations) && $allocations) {
                            $channel_col_map = [
                                'sms' => 'sms',
                                'official_whatsapp' => 'whatsapp',
                                'email' => 'email',
                                'ai_call_agent' => 'aicall',
                            ];
                            foreach ($channel_col_map as $ch_id => $col_prefix) {
                                $promo_active_col = $col_prefix . '_promo_active';
                                $trans_active_col = $col_prefix . '_trans_active';
                                $p = isset($allocations->$promo_active_col) ? (int) $allocations->$promo_active_col : 1;
                                $t = isset($allocations->$trans_active_col) ? (int) $allocations->$trans_active_col : 1;
                                if ($p === 0 && $t === 0) {
                                    $channel_active[$ch_id] = false;
                                }
                            }
                        }

                        // Channel-wise staff permission is applied on top of the
                        // allocation state — a channel the staff member has no
                        // capability for is never rendered at all.
                        foreach ($channel_active as $ch_id => $is_active) {
                            if (!sms_wa_email_can_channel($ch_id)) {
                                $channel_active[$ch_id] = false;
                            }
                        }

                        // Feature-wise permissions, shared with the JS below
                        $can_templates = sms_wa_email_can('templates');
                        $can_hooks     = sms_wa_email_can('hooks');
                        $can_logs      = sms_wa_email_can('logs');

                        // Determine which channel should be active by default (first visible one)
                        $first_active_channel = '';
                        foreach ($channel_active as $ch_id => $is_active) {
                            if ($is_active) {
                                $first_active_channel = $ch_id;
                                break;
                            }
                        }
                        ?>

                        <?php
                        // Everything a channel tab needs to know about what this
                        // staff member may do inside it.
                        $tab_perms = [
                            'can_templates' => $can_templates,
                            'can_hooks'     => $can_hooks,
                            'can_logs'      => $can_logs,
                            'can_settings'  => sms_wa_email_can('settings'),
                        ];
                        ?>

                        <!-- Pill Tab Bar -->
                        <div class="ccx-pill-bar" role="tablist">
                            <?php if ($channel_active['sms']): ?>
                            <a href="#sms" class="ccx-pill <?= ($first_active_channel === 'sms') ? 'active' : ''; ?>"
                                data-channel="sms" role="tab" data-toggle="tab">
                                <i class="fa fa-comment pill-icon-sms"></i> SMS
                            </a>
                            <?php endif; ?>
                            <?php if ($channel_active['official_whatsapp']): ?>
                            <a href="#official_whatsapp"
                                class="ccx-pill <?= ($first_active_channel === 'official_whatsapp') ? 'active' : ''; ?>"
                                data-channel="official_whatsapp" role="tab" data-toggle="tab">
                                <i class="fab fa-whatsapp pill-icon-wa"></i> Official WhatsApp
                            </a>
                            <?php endif; ?>
                            <?php if ($channel_active['email']): ?>
                            <a href="#email"
                                class="ccx-pill <?= ($first_active_channel === 'email') ? 'active' : ''; ?>"
                                data-channel="email" role="tab" data-toggle="tab">
                                <i class="fa fa-envelope pill-icon-email"></i> Email
                            </a>
                            <?php endif; ?>
                            <?php if ($channel_active['ai_call_agent']): ?>
                            <a href="#ai_call_agent"
                                class="ccx-pill <?= ($first_active_channel === 'ai_call_agent') ? 'active' : ''; ?>"
                                data-channel="ai_call_agent" role="tab" data-toggle="tab">
                                <i class="fa fa-microphone pill-icon-ai"></i> AI Call Agent
                            </a>
                            <?php endif; ?>
                        </div>

                        <!-- Tab Content -->
                        <div class="ccx-tab-body">
                            <div class="tab-content">
                                <?php $this->load->view('tabs/sms', array_merge($tab_perms, ['channel_visible' => $channel_active['sms'], 'is_default_tab' => ($first_active_channel === 'sms')])); ?>
                                <?php $this->load->view('tabs/whatsapp', array_merge($tab_perms, ['channel_visible' => $channel_active['official_whatsapp'], 'is_default_tab' => ($first_active_channel === 'official_whatsapp')])); ?>
                                <?php $this->load->view('tabs/email', array_merge($tab_perms, ['channel_visible' => $channel_active['email'], 'is_default_tab' => ($first_active_channel === 'email')])); ?>
                                <?php $this->load->view('tabs/ai_call_agent', array_merge($tab_perms, ['channel_visible' => $channel_active['ai_call_agent'], 'is_default_tab' => ($first_active_channel === 'ai_call_agent')])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     Add/Edit Template Modal
     ═══════════════════════════════════════════════════════ -->
<div class="modal fade ccx-modal" id="templateModal" tabindex="-1" role="dialog" aria-labelledby="templateModalLabel">
    <div class="modal-dialog" id="templateModalDialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="templateModalLabel">Template Setup</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="template_id" value="">
                <input type="hidden" id="template_type" value="">

                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="template_title_input" class="form-label">Template Title <span
                            style="color:#ef4444;">*</span></label>
                    <input type="text" id="template_title_input" class="ccx-input" placeholder="e.g. Welcome Message">
                </div>

                <!-- Email-only fields: Subject, From Name & Sender -->
                <div id="email_only_fields" style="display:none;">
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="template_subject_input" class="form-label">Subject <span
                                style="color:#ef4444;">*</span></label>
                        <input type="text" id="template_subject_input" class="ccx-input" placeholder="e.g. Your Appointment Confirmation">
                    </div>
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="template_from_name_input" class="form-label">From Name <span
                                style="color:#ef4444;">*</span></label>
                        <input type="text" id="template_from_name_input" class="ccx-input" placeholder="e.g. Clientcarex">
                    </div>
                    <?php
                    $email_sender_trans = isset($email_sender['transactional']) ? $email_sender['transactional'] : '';
                    $email_sender_promo = isset($email_sender['promotional']) ? $email_sender['promotional'] : '';
                    ?>
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="template_sender_email_input" class="form-label">Sender Email</label>
                        <input type="text" id="template_sender_email_input" class="ccx-input" disabled
                            data-sender-transactional="<?= html_escape($email_sender_trans); ?>"
                            data-sender-promotional="<?= html_escape($email_sender_promo); ?>"
                            value="<?= html_escape($email_sender_trans !== '' ? $email_sender_trans : 'Not configured — set a default Email API'); ?>">
                        <small style="display:block; margin-top:6px; font-size:11.5px; color:#9ca3af;">
                            <i class="fa fa-info-circle" style="margin-right:4px;"></i>
                            Fixed by the default Email API and cannot be edited here. The recipient (To) email is
                            resolved automatically when the hook fires — patient, staff or role email as per the
                            hook mapping.
                        </small>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="template_subtype_input" class="form-label">Message Type <span
                            style="color:#ef4444;">*</span></label>
                    <select id="template_subtype_input" class="ccx-input">
                        <option value="transactional">Transactional</option>
                        <option value="promotional">Promotional</option>
                    </select>
                </div>

                <!-- Hook Variable Picker -->
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="font-size:12px; color:#6b7280;">
                        <i class="fa fa-plug" style="margin-right:4px; color:#6366f1;"></i>Load Variables from Hook
                    </label>
                    <select id="template_hook_select" class="ccx-input" style="font-size:12.5px;">
                        <option value="">— Select a hook (optional) —</option>
                    </select>
                    <div id="template_hook_variables" style="margin-top:8px; display:none;">
                        <div style="font-size:11px; color:#9ca3af; margin-bottom:6px;">Click a variable to insert at
                            cursor:</div>
                        <div id="template_hook_var_tags" style="display:flex; flex-wrap:wrap; gap:5px;"></div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="template_content_input" class="form-label">Template Content <span
                            style="color:#ef4444;">*</span></label>
                    <textarea id="template_content_input" class="ccx-input" rows="5"
                        placeholder="Enter message draft..."></textarea>
                    <div class="ccx-char-counter" id="charCounterWrap"><span id="charCount">0</span> characters</div>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="template_msg_template_id" class="form-label">Msg Template ID <span
                            style="color:#ef4444;">*</span></label>
                    <input type="text" id="template_msg_template_id" class="ccx-input" placeholder="e.g. 176478">
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="template_header_id" class="form-label">Header ID <span
                            style="color:#ef4444;">*</span></label>
                    <input type="text" id="template_header_id" class="ccx-input" placeholder="e.g. HDR-001">
                </div>

                <!-- Official WhatsApp → Cloud API template mapping -->
                <div id="wa_cloud_fields" style="display:none;">
                    <hr style="border-color: #dcfce7; margin: 18px 0;" />
                    <div style="font-size:13px; font-weight:600; color:#16a34a; margin-bottom:6px;">
                        <i class="fab fa-whatsapp" style="margin-right:6px;"></i>WhatsApp Cloud API
                    </div>
                    <div style="font-size:11.5px; color:#6b7280; margin-bottom:14px;">
                        Map this template to one of your approved Meta templates so it can still be delivered once the
                        contact's 24-hour window has closed. Leave it empty to send the content as free-form text —
                        that only works while the window is open, and costs nothing.
                        Approved templates are mapped for you automatically; picking one here fills in its variables
                        as a suggestion you can correct.
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="template_wa_name" class="form-label">Approved Template</label>
                        <select id="template_wa_name" class="ccx-input">
                            <option value="">— none (free-form inside open window) —</option>
                        </select>
                        <input type="hidden" id="template_wa_language" value="">
                        <div id="template_wa_meta" style="margin-top:8px; display:none; font-size:11.5px; color:#6b7280;
                            background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:10px 12px;"></div>
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="template_wa_params" class="form-label">Template Variables</label>
                        <input type="text" id="template_wa_params" class="ccx-input"
                            placeholder="e.g. {patient_name}, {visit_date}">
                        <div style="font-size:11px; color:#9ca3af; margin-top:6px;">
                            Comma-separated values for <code>{{1}}</code>, <code>{{2}}</code> … Use any hook tag, or
                            plain text. Leave empty when the approved template has one variable — the whole message
                            content is passed into it.
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="template_sample_content" class="form-label">Sample Content</label>
                    <textarea id="template_sample_content" class="ccx-input" rows="2"
                        placeholder="e.g. Dear {patient_name}, your visit {visit_code} ..."></textarea>
                </div>

                <!-- AI Call Agent-specific fields -->
                <div id="ai_call_fields" style="display:none;">
                    <hr style="border-color: #f3e8ff; margin: 18px 0;" />
                    <div style="font-size:13px; font-weight:600; color:#7c3aed; margin-bottom:14px;">
                        <i class="fa fa-microphone" style="margin-right:6px;"></i>Voice Configuration
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="template_voice_type" class="form-label">Voice Type <span style="color:#ef4444;">*</span></label>
                        <select id="template_voice_type" class="ccx-input">
                            <option value="tts">TTS (Text-to-Speech)</option>
                            <option value="voice_note">Voice Note</option>
                        </select>
                    </div>

                    <!-- TTS fields -->
                    <div id="tts_fields">
                        <div class="form-group" style="margin-bottom: 18px;">
                            <label for="template_tts_text" class="form-label">TTS Text <span style="color:#ef4444;">*</span></label>
                            <textarea id="template_tts_text" class="ccx-input" rows="4"
                                placeholder="Enter the text that will be converted to speech..."></textarea>
                        </div>
                    </div>

                    <!-- Voice Note fields -->
                    <div id="voice_note_fields" style="display:none;">
                        <div class="form-group" style="margin-bottom: 18px;">
                            <label for="template_voice_note_id" class="form-label">Voice Note ID</label>
                            <input type="text" id="template_voice_note_id" class="ccx-input" placeholder="e.g. VN-001">
                        </div>
                        <div class="form-group" style="margin-bottom: 18px;">
                            <label class="form-label">Voice Note Audio File</label>
                            <div id="voice_note_upload_area" style="border:2px dashed #d1d5db; border-radius:10px; padding:20px; text-align:center; cursor:pointer; transition:all .2s;">
                                <input type="file" id="template_voice_note_file" accept=".wav" style="display:none;">
                                <div id="voice_note_upload_label">
                                    <i class="fa fa-cloud-upload" style="font-size:28px; color:#a855f7; margin-bottom:8px; display:block;"></i>
                                    <span style="font-size:13px; color:#6b7280;">Click to upload audio file</span>
                                    <br><small style="color:#9ca3af;">WAV format only (max 10MB)</small>
                                </div>
                                <div id="voice_note_file_info" style="display:none;">
                                    <i class="fa fa-file-audio-o" style="font-size:22px; color:#a855f7; margin-right:8px;"></i>
                                    <span id="voice_note_file_name" style="font-size:13px; color:#374151;"></span>
                                    <a href="#" id="voice_note_remove_btn" style="margin-left:10px; color:#ef4444; font-size:12px;"><i class="fa fa-times"></i> Remove</a>
                                </div>
                            </div>
                            <div id="voice_note_preview" style="display:none; margin-top:10px;">
                                <audio id="voice_note_audio_player" controls style="width:100%; border-radius:8px;"></audio>
                            </div>
                            <input type="hidden" id="template_voice_note_file_path" value="">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="template_voice_type_id" class="form-label">Voice Type ID <span style="color:#ef4444;">*</span></label>
                        <input type="text" id="template_voice_type_id" class="ccx-input" placeholder="33-Tran, 34-Promo, 35-TTS">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom: 18px;">
                                <label for="template_retry_count" class="form-label">Retry Count</label>
                                <input type="number" id="template_retry_count" class="ccx-input" placeholder="e.g. 3" min="0" max="10" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom: 18px;">
                                <label for="template_retry_interval" class="form-label">Retry Interval (seconds)</label>
                                <input type="number" id="template_retry_interval" class="ccx-input" placeholder="e.g. 60" min="0" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="template_language" class="form-label">Language <span style="color:#ef4444;">*</span></label>
                        <select id="template_language" class="ccx-input">
                            <option value="english">English</option>
                            <option value="hindi">Hindi</option>
                            <option value="telugu">Telugu</option>
                            <option value="marathi">Marathi</option>
                            <option value="hinglish">Hinglish</option>
                        </select>
                    </div>
                </div>

                <div class="ccx-toggle-group">
                    <div class="ccx-toggle-item" id="toggle_attachment_wrap">
                        <label class="ccx-switch">
                            <input type="checkbox" id="template_attachment_input">
                            <span class="slider"></span>
                        </label>
                        <label for="template_attachment_input" class="toggle-label">Has Attachment</label>
                    </div>
                    <div class="ccx-toggle-item">
                        <label class="ccx-switch">
                            <input type="checkbox" id="template_active_input" checked>
                            <span class="slider"></span>
                        </label>
                        <label for="template_active_input" class="toggle-label">Active</label>
                    </div>
                    <div class="ccx-toggle-item">
                        <label class="ccx-switch">
                            <input type="checkbox" id="template_default_input">
                            <span class="slider"></span>
                        </label>
                        <label for="template_default_input" class="toggle-label">Set as Default</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="ccx-btn-cancel" data-dismiss="modal">Cancel</button>
                <button type="button" class="ccx-btn-save" id="save_template_btn" data-loading-text="Saving...">
                    <i class="fa fa-check" style="margin-right:5px;"></i> Save Template
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════ Import Templates from Excel / CSV ═══════════ -->
<style>
    .ccx-imp-drop {
        border: 2px dashed #cbd5e1;
        border-radius: 14px;
        padding: 34px 20px;
        text-align: center;
        cursor: pointer;
        background: #f8fafc;
        transition: all .2s ease;
    }

    .ccx-imp-drop:hover,
    .ccx-imp-drop.dragover {
        border-color: #0ea5e9;
        background: #f0f9ff;
    }

    .ccx-imp-map {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 18px;
    }

    .ccx-imp-map-item {
        flex: 1 1 200px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 12px;
        color: #475569;
    }

    .ccx-imp-map-item code {
        background: #e0f2fe;
        color: #0369a1;
        border-radius: 4px;
        padding: 1px 6px;
        font-size: 11.5px;
    }

    .ccx-imp-stats {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }

    .ccx-imp-stat {
        flex: 1 1 120px;
        border-radius: 12px;
        padding: 12px 14px;
        border: 1px solid #e5e7eb;
        background: #fff;
    }

    .ccx-imp-stat .n {
        font-size: 22px;
        font-weight: 700;
        line-height: 1.1;
    }

    .ccx-imp-stat .l {
        font-size: 11.5px;
        color: #6b7280;
        font-weight: 600;
        letter-spacing: .3px;
        text-transform: uppercase;
    }

    .ccx-imp-stat.s-new {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }

    .ccx-imp-stat.s-new .n {
        color: #16a34a;
    }

    .ccx-imp-stat.s-dup {
        background: #fffbeb;
        border-color: #fde68a;
    }

    .ccx-imp-stat.s-dup .n {
        color: #d97706;
    }

    .ccx-imp-stat.s-bad {
        background: #fef2f2;
        border-color: #fecaca;
    }

    .ccx-imp-stat.s-bad .n {
        color: #dc2626;
    }

    .ccx-imp-table-wrap {
        max-height: 46vh;
        overflow: auto;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
    }

    #importPreviewTable {
        margin: 0;
        font-size: 12.5px;
    }

    #importPreviewTable>thead>tr>th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f8fafc;
        border-bottom: 1px solid #e5e7eb !important;
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: .3px;
        color: #64748b;
        padding: 10px 8px;
        white-space: nowrap;
    }

    #importPreviewTable>tbody>tr>td {
        padding: 8px;
        vertical-align: top;
        border-top: 1px solid #f1f5f9 !important;
    }

    #importPreviewTable tr.row-skip {
        background: #fafafa;
        color: #94a3b8;
    }

    #importPreviewTable .imp-title-input {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 5px 8px;
        font-size: 12.5px;
        width: 100%;
        min-width: 150px;
    }

    #importPreviewTable .imp-title-input:focus {
        border-color: #0ea5e9;
        outline: none;
        box-shadow: 0 0 0 3px rgba(14, 165, 233, .12);
    }

    #importPreviewTable .imp-subtype {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 4px 6px;
        font-size: 11.5px;
        background: #fff;
    }

    #importPreviewTable .imp-content {
        max-width: 340px;
        white-space: pre-wrap;
        word-break: break-word;
        color: #475569;
        line-height: 1.45;
        max-height: 62px;
        overflow: hidden;
    }

    .ccx-imp-badge {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .2px;
        white-space: nowrap;
    }

    .ccx-imp-badge.b-new {
        background: #dcfce7;
        color: #166534;
    }

    .ccx-imp-badge.b-dup {
        background: #fef3c7;
        color: #92400e;
    }

    .ccx-imp-badge.b-bad {
        background: #fee2e2;
        color: #991b1b;
    }

    .ccx-imp-badge.b-promo {
        background: #ede9fe;
        color: #5b21b6;
    }

    .ccx-imp-badge.b-trans {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .ccx-imp-reason {
        font-size: 10.5px;
        color: #b45309;
        display: block;
        margin-top: 3px;
    }
</style>

<div class="modal fade ccx-modal" id="importTemplatesModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" id="importModalDialog" role="document" style="width:96%; max-width:1220px;">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #0ea5e9, #0369a1);">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-file-excel-o" style="margin-right:8px;"></i>Import Templates
                    from Excel</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="import_type" value="sms">

                <!-- Step 1 — pick a file -->
                <div id="importStepUpload">
                    <div class="ccx-imp-drop" id="importDropZone">
                        <input type="file" id="import_file_input" accept=".xlsx,.csv,.txt,.tsv" style="display:none;">
                        <i class="fa fa-cloud-upload" style="font-size:34px; color:#0ea5e9; display:block; margin-bottom:10px;"></i>
                        <div style="font-size:14px; font-weight:600; color:#334155;">Drop your sheet here, or click to
                            browse</div>
                        <div style="font-size:12px; color:#94a3b8; margin-top:5px;">.xlsx or .csv — up to 8 MB</div>
                        <div id="importFileName"
                            style="display:none; margin-top:12px; font-size:12.5px; color:#0369a1; font-weight:600;">
                        </div>
                    </div>

                    <div class="ccx-imp-map">
                        <div class="ccx-imp-map-item">
                            <code>message</code> → <strong>Msg Template ID</strong>
                        </div>
                        <div class="ccx-imp-map-item">
                            <code>sender_id</code> → <strong>Header ID</strong>
                        </div>
                        <div class="ccx-imp-map-item">
                            <code>approved_message</code> → <strong>Template Content</strong> + <strong>Sample
                                Content</strong>
                        </div>
                    </div>

                    <div style="margin-top:14px; font-size:12px; color:#64748b; line-height:1.6;">
                        <i class="fa fa-lightbulb-o" style="color:#f59e0b; margin-right:5px;"></i>
                        Any other columns in the sheet are ignored, and the header row does not have to be the first
                        row. <strong>Message Type</strong> is set from the sender id — digits only means Promotional,
                        anything else Transactional — and the <strong>Template Title</strong> is written for you from
                        the approved message (you can edit every title before importing). Templates that already exist
                        are detected and skipped.
                        <a href="<?php echo admin_url('sms_wa_email/import_sample'); ?>" style="margin-left:4px;">
                            <i class="fa fa-download"></i> Download a sample sheet</a>
                    </div>

                    <div id="importUploadError"
                        style="display:none; margin-top:14px; background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:10px; padding:10px 14px; font-size:12.5px;">
                    </div>
                </div>

                <!-- Step 2 — review -->
                <div id="importStepPreview" style="display:none;">
                    <div class="ccx-imp-stats">
                        <div class="ccx-imp-stat s-new">
                            <div class="n" id="impStatNew">0</div>
                            <div class="l">Ready to import</div>
                        </div>
                        <div class="ccx-imp-stat s-dup">
                            <div class="n" id="impStatDup">0</div>
                            <div class="l">Already exists</div>
                        </div>
                        <div class="ccx-imp-stat s-bad">
                            <div class="n" id="impStatBad">0</div>
                            <div class="l">Not usable</div>
                        </div>
                        <div class="ccx-imp-stat">
                            <div class="n" id="impStatTotal" style="color:#334155;">0</div>
                            <div class="l">Rows read</div>
                        </div>
                    </div>

                    <div id="importNotice"
                        style="display:none; margin-bottom:12px; background:#fffbeb; border:1px solid #fde68a; color:#92400e; border-radius:10px; padding:9px 13px; font-size:12px;">
                    </div>

                    <div
                        style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; flex-wrap:wrap;">
                        <div style="font-size:12.5px; color:#64748b;" id="importSourceLine"></div>
                        <label style="font-size:12px; color:#475569; font-weight:500; margin:0; cursor:pointer;">
                            <input type="checkbox" id="importShowSkipped" style="margin-right:6px; vertical-align:-1px;">
                            Show skipped rows
                        </label>
                    </div>

                    <div class="ccx-imp-table-wrap">
                        <table class="table" id="importPreviewTable">
                            <thead>
                                <tr>
                                    <th width="34"><input type="checkbox" id="importCheckAll" checked></th>
                                    <th width="46">Row</th>
                                    <th width="22%">Template Title</th>
                                    <th width="13%">Msg Template ID</th>
                                    <th width="10%">Header ID</th>
                                    <th width="12%">Message Type</th>
                                    <th>Content</th>
                                    <th width="11%">Status</th>
                                </tr>
                            </thead>
                            <tbody id="importPreviewBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Step 3 — result -->
                <div id="importStepDone" style="display:none; text-align:center; padding:24px 10px;">
                    <i class="fa fa-check-circle" style="font-size:52px; color:#16a34a; display:block; margin-bottom:14px;"></i>
                    <div id="importDoneHeadline" style="font-size:17px; font-weight:700; color:#166534;"></div>
                    <div id="importDoneDetail" style="font-size:13px; color:#64748b; margin-top:8px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="ccx-btn-cancel" id="importBackBtn" style="display:none;">
                    <i class="fa fa-arrow-left" style="margin-right:5px;"></i> Choose another file
                </button>
                <button type="button" class="ccx-btn-cancel" data-dismiss="modal" id="importCloseBtn">Cancel</button>
                <button type="button" class="ccx-btn-save" id="importParseBtn"
                    style="background: linear-gradient(135deg, #0ea5e9, #0369a1);" data-loading-text="Reading…">
                    <i class="fa fa-search" style="margin-right:5px;"></i> Read & Preview
                </button>
                <button type="button" class="ccx-btn-save" id="importCommitBtn"
                    style="display:none; background: linear-gradient(135deg, #16a34a, #15803d);"
                    data-loading-text="Importing…">
                    <i class="fa fa-download" style="margin-right:5px;"></i> Import Selected
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hook Trigger Logs Modal -->
<div class="modal fade" id="hookLogsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="width:1200px;">
        <div class="modal-content ccx-modal-content" style="border-radius:16px; overflow:hidden;">
            <div class="modal-header ccx-modal-header"
                style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color:#fff; border:none; padding:18px 24px;">
                <button type="button" class="close" data-dismiss="modal"
                    style="color:#fff; opacity:0.8;">&times;</button>
                <h4 class="modal-title" id="hookLogsModalLabel" style="font-weight:700;"><i class="fa fa-list-ul"
                        style="margin-right:8px;"></i>Message Send Logs</h4>
            </div>
            <div class="modal-body" style="padding:20px 24px; max-height:620px; overflow-y:auto;">
                <!-- Auto-delete Info Banner -->
                <div
                    style="display:flex; align-items:center; gap:10px; padding:12px 18px; margin-bottom:15px; background:linear-gradient(135deg, #e3f2fd, #bbdefb); border:1px solid #90caf9; border-left:4px solid #1e88e5; border-radius:8px; color:#1565c0; font-size:13px; font-weight:500;">
                    <i class="fa fa-clock-o" style="font-size:16px; color:#1e88e5;"></i>
                    <span>Hook trigger logs are automatically deleted after <strong>48 hours</strong>.</span>
                </div>
                <div class="row" style="margin-bottom:14px;">
                    <div class="col-md-4">
                        <select class="form-control" id="hook_logs_channel_filter"
                            style="font-size:13px; border-radius:8px;">
                            <option value="all">All Channels</option>
                            <option value="sms">SMS</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="email">Email</option>
                            <option value="ai_call_agent">AI Call Agent</option>
                        </select>
                    </div>
                    <div class="col-md-8 text-right">
                        <span id="hook_logs_count" style="font-size:12px; color:#9ca3af;"></span>
                    </div>
                </div>
                <table class="table" id="hook-logs-table" style="font-size:12.5px;">
                    <thead>
                        <tr>
                            <th width="10%">Time</th>
                            <th width="7%">Source</th>
                            <th width="8%">Hook</th>
                            <th width="6%">Channel</th>
                            <th width="5%">Type</th>
                            <th width="9%">Recipient</th>
                            <th width="6%">Status</th>
                            <th width="16%">API / Error</th>
                            <th width="19%">Message Content</th>
                            <th width="7%">User</th>
                            <th width="5%">Action</th>
                        </tr>
                    </thead>
                    <tbody id="hook-logs-wrapper">
                        <tr>
                            <td colspan="11" class="text-center" style="padding:30px; color:#9ca3af;"><i
                                    class="fa fa-spinner fa-spin"></i> Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f3f4f6; padding:12px 24px;">
                <button type="button" class="ccx-btn-cancel" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Hook Recipient Modal -->
<div class="modal fade ccx-modal" id="hookRecipientModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title" id="hookRecipientModalLabel"><i class="fa fa-plug"
                        style="margin-right:8px; color:#6366f1;"></i>Add Hook Recipient</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="hr_mapping_id" value="">
                <input type="hidden" id="hr_channel" value="">

                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label">Hook Event</label>
                    <select id="hr_hook_key" class="ccx-input">
                        <option value="">— Select Hook —</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label" style="margin-bottom:10px; display:block;">Recipient Types</label>
                    <div id="hr_recipient_types_wrap" style="display:flex; flex-wrap:wrap; gap:10px;">
                        <label class="ccx-checkbox-card"
                            style="display:flex; align-items:center; gap:8px; padding:8px 14px; border:1px solid #e5e7eb; border-radius:8px; cursor:pointer; transition: all .15s;">
                            <input type="checkbox" class="hr_recipient_cb" value="patient"
                                style="accent-color:#10b981;">
                            <span style="font-size:13px;"><i class="fa fa-heartbeat"
                                    style="color:#10b981; margin-right:3px;"></i>Patient</span>
                        </label>
                        <label class="ccx-checkbox-card"
                            style="display:flex; align-items:center; gap:8px; padding:8px 14px; border:1px solid #e5e7eb; border-radius:8px; cursor:pointer; transition: all .15s;">
                            <input type="checkbox" class="hr_recipient_cb" value="staff" style="accent-color:#f59e0b;">
                            <span style="font-size:13px;"><i class="fa fa-user"
                                    style="color:#f59e0b; margin-right:3px;"></i>Specific Staff</span>
                        </label>
                        <label class="ccx-checkbox-card"
                            style="display:flex; align-items:center; gap:8px; padding:8px 14px; border:1px solid #e5e7eb; border-radius:8px; cursor:pointer; transition: all .15s;">
                            <input type="checkbox" class="hr_recipient_cb" value="role" style="accent-color:#6366f1;">
                            <span style="font-size:13px;"><i class="fa fa-users"
                                    style="color:#6366f1; margin-right:3px;"></i>Role-wise Staff</span>
                        </label>
                        <label class="ccx-checkbox-card"
                            style="display:flex; align-items:center; gap:8px; padding:8px 14px; border:1px solid #e5e7eb; border-radius:8px; cursor:pointer; transition: all .15s;">
                            <input type="checkbox" class="hr_recipient_cb" value="custom" style="accent-color:#ec4899;">
                            <span style="font-size:13px;"><i class="fa fa-pencil"
                                    style="color:#ec4899; margin-right:3px;"></i>Custom</span>
                        </label>
                    </div>
                </div>

                <div class="form-group" id="hr_staff_wrap" style="margin-bottom:16px; display:none;">
                    <label class="form-label">Select Staff</label>
                    <select id="hr_staff_id" class="ccx-input">
                        <option value="">— Select Staff —</option>
                    </select>
                </div>

                <div class="form-group" id="hr_role_wrap" style="margin-bottom:16px; display:none;">
                    <label class="form-label">Select Role</label>
                    <select id="hr_role_id" class="ccx-input">
                        <option value="">— Select Role —</option>
                    </select>
                </div>

                <div class="form-group" id="hr_custom_wrap" style="margin-bottom:16px; display:none;">
                    <label class="form-label">Custom Number or Variable</label>
                    <input type="text" id="hr_custom_value" class="ccx-input"
                        placeholder="e.g. 03001234567 or click a tag below">
                    <div id="hr_custom_tags" style="margin-top:8px; display:flex; flex-wrap:wrap; gap:5px;"></div>
                    <small style="color:#9ca3af; font-size:11px; margin-top:4px; display:block;">Type a phone number
                        directly, or click a variable tag to use the value from hook data.</small>
                </div>

                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label">Template</label>
                    <select id="hr_template_id" class="ccx-input">
                        <option value="">— Select Template —</option>
                    </select>
                </div>

                <div class="ccx-toggle-group">
                    <div class="ccx-toggle-item">
                        <label class="ccx-switch">
                            <input type="checkbox" id="hr_active" checked>
                            <span class="slider"></span>
                        </label>
                        <label for="hr_active" class="toggle-label">Active</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="ccx-btn-cancel" data-dismiss="modal">Cancel</button>
                <button type="button" class="ccx-btn-save" id="save_hook_recipient_btn" data-loading-text="Saving...">
                    <i class="fa fa-check" style="margin-right:5px;"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>



<?php
// Pass registered hooks to JS for the variable picker
$registered_hooks_json = function_exists('ccx_get_registered_hooks') ? json_encode(ccx_get_registered_hooks()) : '[]';
?>
<!-- Modal for Recharging Credits (3-Step Cart Wizard) -->
<div class="modal fade ccx-modal" id="rechargeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none;">
            <!-- Dynamic Header -->
            <div class="modal-header rch-header" id="rechargeModalHeader">
                <!-- Left: Back + Title -->
                <div style="display:flex; align-items:center; flex:1; min-width:0;">
                    <button type="button" class="rch-back-btn" id="rchBackBtn" onclick="rchGoBack()" style="display:none; margin-right:10px;">
                        <i class="fa fa-arrow-left"></i>
                    </button>
                    <h4 class="modal-title" style="margin:0; font-weight:700; font-size:18px; white-space:nowrap;">
                        <i class="fa fa-bolt" style="margin-right:6px;"></i> Recharge Credits
                    </h4>
                </div>
                <!-- Center: Channel badge (visible on Step 2) -->
                <div id="rchSelectedChannelHeader" style="display:none; flex:1; text-align:center;"></div>
                <!-- Right: Cart badge + Close button -->
                <div style="display:flex; align-items:center; gap:12px; flex:1; justify-content:flex-end;">
                    <button type="button" class="rch-cart-badge-btn" id="rchCartBadgeBtn" onclick="rchShowCart()" style="display:none;">
                        <i class="fa fa-shopping-cart"></i>
                        <span class="rch-cart-count" id="rchCartCount">0</span>
                    </button>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>

            <div class="modal-body" style="padding: 24px 28px; position: relative; overflow: hidden;">
                <!-- Loading Spinner -->
                <div id="recharge_loading" class="text-center" style="padding: 60px 20px; display: none;">
                    <div class="rch-spinner"></div>
                    <p style="margin-top:16px; color:#6b7280; font-size:14px; font-weight:500;">Loading plans...</p>
                </div>

                <!-- Error State -->
                <div id="recharge_error" style="display:none; text-align:center; padding:40px 20px;">
                    <i class="fa fa-exclamation-triangle" style="font-size:36px; color:#ef4444; margin-bottom:14px; display:block;"></i>
                    <p style="color:#dc2626; font-weight:600; font-size:15px;" id="recharge_error_msg">Failed to load plans.</p>
                    <button class="rch-retry-btn" onclick="openRechargeModal()"><i class="fa fa-refresh"></i> Try Again</button>
                </div>

                <!-- ====== STEP 1: Channel Selection ====== -->
                <div id="rchStep1" class="rch-step">
                    <div class="rch-channels-grid" id="rchChannelsGrid">
                        <!-- Populated by JS -->
                    </div>

                    <!-- ═══ Coupon Code Section (body only, toggle is in footer) ═══ -->
                    <div class="cpn-section">
                        <div class="cpn-body" id="cpnBody" style="display:none;">
                            <div class="cpn-input-row">
                                <input type="text" class="cpn-input" id="cpnCodeInput" placeholder="Enter coupon code" maxlength="50">
                                <button type="button" class="cpn-apply-btn" id="cpnApplyBtn" onclick="validateCouponCode()">
                                    <i class="fa fa-check-circle"></i> Apply
                                </button>
                            </div>
                            <!-- Validation Loading -->
                            <div class="cpn-loading" id="cpnLoading" style="display:none;">
                                <div class="rch-spinner" style="width:22px; height:22px; border-width:3px;"></div>
                                <span>Validating coupon...</span>
                            </div>
                            <!-- Error Message -->
                            <div class="cpn-error" id="cpnError" style="display:none;">
                                <i class="fa fa-times-circle"></i>
                                <span id="cpnErrorMsg"></span>
                            </div>
                            <!-- Coupon Details Card (shown after successful validation) -->
                            <div class="cpn-details-card" id="cpnDetailsCard" style="display:none;">
                                <div class="cpn-details-header">
                                    <div class="cpn-details-icon"><i class="fa fa-gift"></i></div>
                                    <div class="cpn-details-info">
                                        <h5 id="cpnDetailCode"></h5>
                                        <p id="cpnDetailDesc"></p>
                                    </div>
                                </div>
                                <div class="cpn-credits-grid" id="cpnCreditsGrid">
                                    <!-- Populated by JS -->
                                </div>
                                <div class="cpn-expiry-note" id="cpnExpiryNote" style="display:none;">
                                    <i class="fa fa-clock-o"></i> <span id="cpnExpiryText"></span>
                                </div>
                                <button type="button" class="cpn-claim-btn" id="cpnClaimBtn" onclick="claimCouponCode()">
                                    <i class="fa fa-bolt"></i> Claim Free Credits
                                </button>
                            </div>
                            <!-- Success State -->
                            <div class="cpn-success" id="cpnSuccess" style="display:none;">
                                <div class="cpn-success-icon"><i class="fa fa-check-circle"></i></div>
                                <h5>Credits Claimed Successfully!</h5>
                                <p id="cpnSuccessMsg"></p>
                                <div class="cpn-success-credits" id="cpnSuccessCredits">
                                    <!-- Populated by JS -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Motivation / Social Proof Banner -->
                    <div class="rch-social-proof">
                        <div class="rch-social-proof-title">
                            <i class="fa fa-trophy"></i> The Choice of Growing Corporates &amp; Brands
                        </div>
                        <p class="rch-social-proof-quote">
                            &ldquo;Our Clients Have Seen Up to
                            <span class="rch-stat">38% Higher Patient Satisfaction</span>,
                            <span class="rch-stat">35% Faster Patient Response Rates</span>, and
                            <span class="rch-stat">27% Growth in Monthly Revenue</span>.&rdquo;
                        </p>
                    </div>
                </div>

                <!-- ====== STEP 2: Plan Selection ====== -->
                <div id="rchStep2" class="rch-step" style="display:none;">
                    <!-- Billing Cycle Toggle -->
                    <div class="rch-billing-toggle" id="rchBillingToggle">
                        <button class="rch-cycle-btn active" data-cycle="monthly" onclick="rchSwitchCycle('monthly', this)">
                            <i class="fa fa-calendar-o"></i> Monthly
                        </button>
                        <button class="rch-cycle-btn" data-cycle="quarterly" onclick="rchSwitchCycle('quarterly', this)">
                            <i class="fa fa-calendar"></i> Quarterly
                        </button>
                        <button class="rch-cycle-btn" data-cycle="yearly" onclick="rchSwitchCycle('yearly', this)">
                            <i class="fa fa-calendar-check-o"></i> Yearly
                        </button>
                    </div>

                    <!-- Current Balance Banner -->
                    <div id="rchBalanceBanner" style="display:none;"></div>

                    <!-- Plans Grid -->
                    <div class="rch-plans-grid" id="rchPlansGrid">
                        <!-- Populated by JS -->
                    </div>

                    <!-- Empty State for Plans -->
                    <div id="rchPlansEmpty" class="rch-empty-state" style="display:none;">
                        <i class="fa fa-inbox"></i>
                        <p>No plans available for this channel and billing cycle.</p>
                        <small>Try a different billing cycle or contact support.</small>
                    </div>
                </div>

                <!-- ====== STEP 3: Cart Review ====== -->
                <div id="rchStep3" class="rch-step" style="display:none;">
                    <div id="rchCartEmpty" style="display:none; text-align:center; padding:50px 20px;">
                        <i class="fa fa-shopping-cart" style="font-size:48px; color:#d1d5db; margin-bottom:16px; display:block;"></i>
                        <p style="font-size:16px; font-weight:600; color:#6b7280;">Your cart is empty</p>
                        <p style="font-size:13px; color:#9ca3af;">Browse channels and add plans to get started.</p>
                        <button class="rch-continue-btn" onclick="rchContinueShopping()">
                            <i class="fa fa-arrow-left"></i> Browse Plans
                        </button>
                    </div>
                    <div id="rchCartList">
                        <!-- Cart items rendered by JS -->
                    </div>
                    <div id="rchCartSummary" style="display:none;">
                        <!-- Cart total summary rendered by JS -->
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer rch-footer" id="rchFooter">
                <div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
                    <a href="#" class="cpn-toggle" id="cpnToggle" onclick="toggleCouponSection(); return false;">
                        <i class="fa fa-ticket"></i> Have a Coupon Code?
                    </a>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <button type="button" class="ccx-btn-cancel" data-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
   PAYMENT SUCCESS MODAL
   ═══════════════════════════════════════════ -->
<div class="modal fade" id="paymentSuccessModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog" style="max-width:520px;">
        <div class="modal-content" style="border-radius:20px; overflow:hidden; border:none; box-shadow: 0 25px 60px rgba(0,0,0,0.15);">
            <!-- Gradient top bar -->
            <div class="ps-topbar"></div>

            <div class="modal-body" style="padding:0;">
                <!-- Success Animation Area -->
                <div class="ps-hero">
                    <div class="ps-check-circle">
                        <svg class="ps-checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                            <circle class="ps-checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                            <path class="ps-checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                        </svg>
                    </div>
                    <h3 class="ps-title">Payment Successful!</h3>
                    <p class="ps-subtitle">Thank you for your purchase. Your credits have been activated instantly.</p>
                </div>

                <!-- Payment Summary -->
                <div class="ps-summary-section">
                    <div class="ps-summary-label"><i class="fa fa-receipt"></i> Payment Summary</div>
                    <div class="ps-summary-grid" id="psSummaryGrid">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <!-- Items Purchased -->
                <div class="ps-items-section">
                    <div class="ps-items-label"><i class="fa fa-shopping-bag"></i> Items Purchased</div>
                    <div id="psItemsList">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <!-- Total -->
                <div class="ps-total-section" id="psTotalSection">
                    <!-- Populated by JS -->
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer" style="border-top:1px solid #f1f5f9; padding:16px 28px; display:flex; justify-content:center; gap:10px;">
                <button type="button" class="ps-close-btn" data-dismiss="modal">
                    <i class="fa fa-check-circle"></i> Done
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* ═══════════════════════════════════════════
   RECHARGE MODAL — 2-Step Wizard Styles
   ═══════════════════════════════════════════ */

/* Header */
.rch-header {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: #fff;
    border: none;
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
/* Override Bootstrap .close positioning inside our header */
.rch-header .close {
    position: static;
    float: none;
    margin: 0;
    padding: 0;
    color: #fff;
    opacity: 0.85;
    text-shadow: none;
    font-size: 24px;
    font-weight: 300;
    line-height: 1;
    flex-shrink: 0;
}
.rch-header .close:hover,
.rch-header .close:focus {
    color: #fff;
    opacity: 1;
}
.rch-step-label {
    font-size: 12px;
    font-weight: 500;
    color: rgba(255,255,255,0.65);
    margin-top: 3px;
    letter-spacing: 0.3px;
}
.rch-back-btn {
    background: #000;
    border: 1px solid #000;
    color: #fff;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
}
.rch-back-btn:hover {
    background: #222;
}

/* Spinner */
.rch-spinner {
    width: 36px;
    height: 36px;
    border: 3px solid #e5e7eb;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: rchSpin 0.7s linear infinite;
    margin: 0 auto;
}
@keyframes rchSpin { to { transform: rotate(360deg); } }

/* Retry Button */
.rch-retry-btn {
    margin-top: 14px;
    background: #fff;
    border: 1px solid #d1d5db;
    color: #374151;
    padding: 8px 20px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.rch-retry-btn:hover { background: #f9fafb; border-color: #9ca3af; }

/* Step Transitions */
.rch-step {
    animation: rchFadeIn 0.35s ease;
}
@keyframes rchFadeIn {
    from { opacity: 0; transform: translateX(20px); }
    to   { opacity: 1; transform: translateX(0); }
}

/* ─── STEP 1: Channel Cards ─── */
.rch-channels-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 14px;
}
.rch-channel-card {
    background: #fff;
    border: 2px solid #e5e7eb;
    border-radius: 14px;
    padding: 24px 16px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.rch-channel-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    opacity: 0;
    transition: opacity 0.25s;
}
.rch-channel-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.08);
}
.rch-channel-card:hover::before {
    opacity: 1;
}
.rch-channel-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    font-size: 22px;
    color: #fff;
    transition: transform 0.25s;
}
.rch-channel-card:hover .rch-channel-icon {
    transform: scale(1.1);
}
.rch-channel-name {
    font-size: 14px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 4px;
}
.rch-channel-count {
    font-size: 11px;
    color: #9ca3af;
    font-weight: 500;
}
.rch-channel-cart-badge {
    display: inline-block;
    background: #10b981;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 10px;
    margin-top: 6px;
    letter-spacing: 0.2px;
}
.rch-channel-select {
    margin-top: 12px;
    font-size: 12px;
    font-weight: 600;
    color: #6366f1;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    opacity: 0;
    transform: translateY(6px);
    transition: all 0.25s;
}
.rch-channel-card:hover .rch-channel-select {
    opacity: 1;
    transform: translateY(0);
}

/* Channel-specific colors */
.rch-channel-card[data-type="sms"] { border-color: #e5e7eb; }
.rch-channel-card[data-type="sms"]:hover { border-color: #3b82f6; }
.rch-channel-card[data-type="sms"]::before { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.rch-channel-card[data-type="sms"] .rch-channel-icon { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.rch-channel-card[data-type="sms"] .rch-channel-select { color: #3b82f6; }

.rch-channel-card[data-type="whatsapp"] { border-color: #e5e7eb; }
.rch-channel-card[data-type="whatsapp"]:hover { border-color: #22c55e; }
.rch-channel-card[data-type="whatsapp"]::before { background: linear-gradient(135deg, #22c55e, #16a34a); }
.rch-channel-card[data-type="whatsapp"] .rch-channel-icon { background: linear-gradient(135deg, #22c55e, #16a34a); }
.rch-channel-card[data-type="whatsapp"] .rch-channel-select { color: #16a34a; }


.rch-channel-card[data-type="email"] { border-color: #e5e7eb; }
.rch-channel-card[data-type="email"]:hover { border-color: #f59e0b; }
.rch-channel-card[data-type="email"]::before { background: linear-gradient(135deg, #f59e0b, #d97706); }
.rch-channel-card[data-type="email"] .rch-channel-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
.rch-channel-card[data-type="email"] .rch-channel-select { color: #d97706; }

.rch-channel-card[data-type="aicall"] { border-color: #e5e7eb; }
.rch-channel-card[data-type="aicall"]:hover { border-color: #a855f7; }
.rch-channel-card[data-type="aicall"]::before { background: linear-gradient(135deg, #a855f7, #9333ea); }
.rch-channel-card[data-type="aicall"] .rch-channel-icon { background: linear-gradient(135deg, #a855f7, #9333ea); }
.rch-channel-card[data-type="aicall"] .rch-channel-select { color: #9333ea; }

/* ─── STEP 2: Selected Channel Badge ─── */
.rch-selected-channel {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 16px;
    color: #fff;
}

/* ─── STEP 2: Billing Cycle Toggle ─── */
.rch-billing-toggle {
    display: flex;
    background: #f1f5f9;
    border-radius: 10px;
    padding: 4px;
    margin-bottom: 20px;
    gap: 4px;
}
.rch-cycle-btn {
    flex: 1;
    background: transparent;
    border: none;
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.rch-cycle-btn:hover {
    color: #334155;
    background: rgba(255,255,255,0.6);
}
.rch-cycle-btn.active {
    background: #fff;
    color: #1e293b;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
}
.rch-cycle-btn i {
    font-size: 12px;
}

/* ─── Balance Banner ─── */
.rch-balance-banner {
    background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
    border: 1px solid #bbf7d0;
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 16px;
}
.rch-balance-banner.rch-balance-empty {
    background: #fffbeb;
    border-color: #fde68a;
    color: #92400e;
    font-size: 13px;
    font-weight: 500;
    display: flex;
    align-items: center;
}
.rch-balance-title {
    font-size: 12px;
    font-weight: 700;
    color: #166534;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
}
.rch-balance-items {
    display: flex;
    gap: 16px;
}
.rch-balance-item {
    flex: 1;
    background: rgba(255,255,255,0.7);
    border-radius: 8px;
    padding: 10px 14px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.rch-balance-label {
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.rch-balance-value {
    font-size: 18px;
    font-weight: 800;
    color: #1a202c;
    line-height: 1.2;
}
.rch-balance-expiry {
    font-size: 11px;
    color: #9ca3af;
    font-weight: 500;
}

/* ─── STEP 2: Plan Cards — Premium Redesign ─── */
.rch-plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 20px;
    perspective: 1000px;
}
.rch-plan-card {
    background: linear-gradient(165deg, #ffffff 0%, #fafbff 100%);
    border: 1px solid #e8eaf0;
    border-radius: 18px;
    padding: 0;
    text-align: center;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.rch-plan-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #c7d2fe, #a5b4fc, #c7d2fe);
    opacity: 0;
    transition: opacity 0.3s;
}
.rch-plan-card:hover {
    border-color: #c7d2fe;
    transform: translateY(-6px);
    box-shadow: 0 20px 40px -12px rgba(99, 102, 241, 0.15), 0 4px 12px rgba(0,0,0,0.04);
}
.rch-plan-card:hover::before {
    opacity: 1;
}
.rch-plan-card.rch-best-value {
    border-color: #818cf8;
    background: linear-gradient(165deg, #f5f3ff 0%, #eef2ff 20%, #ffffff 60%);
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.12), 0 0 0 1px rgba(99, 102, 241, 0.06);
}
.rch-plan-card.rch-best-value::before {
    opacity: 1;
    background: linear-gradient(90deg, #6366f1, #8b5cf6, #a78bfa, #8b5cf6, #6366f1);
    background-size: 200% 100%;
    animation: rchShimmer 3s linear infinite;
    height: 4px;
}
@keyframes rchShimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.rch-plan-card.rch-best-value:hover {
    transform: translateY(-8px);
    box-shadow: 0 24px 48px -12px rgba(99, 102, 241, 0.22), 0 0 0 1px rgba(99, 102, 241, 0.1);
}

/* ─ Card Inner Sections ─ */
.rch-card-header-zone {
    padding: 24px 22px 0;
    position: relative;
}
.rch-card-badges {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 10px;
    min-height: 24px;
}
.rch-card-pricing-zone {
    padding: 4px 22px 16px;
}
.rch-card-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, #e5e7eb, transparent);
    margin: 0 22px;
}
.rch-card-features-zone {
    padding: 16px 22px 0;
    flex: 1;
}
.rch-card-action-zone {
    padding: 6px 22px 22px;
}

/* Best Value Badge */
.rch-best-badge {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    font-size: 9.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    padding: 5px 18px;
    border-radius: 0 0 10px 10px;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    white-space: nowrap;
}

/* Cycle Label Badge */
.rch-cycle-label-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #fff;
}

/* Subtype Badge */
.rch-subtype-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 10px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.3px;
    color: #fff;
}

/* Discount Badge */
.rch-discount-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: linear-gradient(135deg, #dcfce7, #d1fae5);
    color: #15803d;
    font-size: 11px;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 20px;
    margin-top: 10px;
    border: 1px solid rgba(21, 128, 61, 0.1);
}

.rch-plan-name {
    font-size: 13.5px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 14px;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.rch-plan-price-wrap {
    margin-bottom: 4px;
}
.rch-plan-original {
    font-size: 14px;
    color: #b0b8c4;
    text-decoration: line-through;
    margin-bottom: 4px;
    font-weight: 500;
}
.rch-plan-final {
    font-size: 36px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.15;
    letter-spacing: -0.5px;
}
.rch-plan-final small {
    font-size: 14px;
    font-weight: 500;
    color: #94a3b8;
}
.rch-plan-tax-info {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 3px;
    font-weight: 500;
}
.rch-plan-total {
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
    margin-top: 4px;
}

/* Plan Details List */
.rch-plan-details {
    list-style: none;
    padding: 0;
    margin: 0 0 0 0;
    text-align: left;
}
.rch-plan-details li {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
    font-size: 13px;
    color: #4b5563;
    border-bottom: none;
}
.rch-plan-details li:last-child {
    padding-bottom: 0;
}
.rch-plan-details li .rch-check-icon {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: linear-gradient(135deg, #d1fae5, #ecfdf5);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.rch-plan-details li .rch-check-icon i {
    font-size: 9px;
    color: #10b981;
}
.rch-plan-details li i {
    width: 16px;
    text-align: center;
    font-size: 12px;
    color: #10b981;
}
.rch-plan-details li strong {
    color: #1e293b;
    font-weight: 600;
}

/* Buy Button */
.rch-buy-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 13px 20px;
    border-radius: 12px;
    border: none;
    color: #fff;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    letter-spacing: 0.3px;
    position: relative;
    overflow: hidden;
}
.rch-buy-btn::after {
    content: '';
    position: absolute;
    top: 0; left: -100%; right: 0; bottom: 0;
    width: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
    transition: left 0.5s ease;
}
.rch-buy-btn:hover::after {
    left: 100%;
}
.rch-buy-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.18);
}
.rch-buy-btn:active {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
}

/* Empty State */
.rch-empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #9ca3af;
}
.rch-empty-state i {
    font-size: 36px;
    display: block;
    margin-bottom: 12px;
    color: #d1d5db;
}
.rch-empty-state p {
    font-size: 15px;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 4px;
}
.rch-empty-state small {
    font-size: 12px;
}

/* Secure Badge */
.rch-secure-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: #9ca3af;
    font-weight: 500;
}

/* ─── Social Proof Banner ─── */
.rch-social-proof {
    margin-top: 24px;
    padding: 20px 24px;
    background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 50%, #f5f3ff 100%);
    border: 1px solid #e0e7ff;
    border-radius: 12px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.rch-social-proof::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #6366f1, #a855f7, #ec4899);
}
.rch-social-proof-title {
    font-size: 13px;
    font-weight: 700;
    color: #4338ca;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 10px;
}
.rch-social-proof-title i {
    color: #f59e0b;
    margin-right: 6px;
}
.rch-social-proof-quote {
    font-size: 13.5px;
    color: #4b5563;
    line-height: 1.7;
    margin: 0;
    font-style: italic;
    font-weight: 500;
}
.rch-social-proof-quote .rch-stat {
    color: #1e293b;
    font-weight: 700;
    font-style: normal;
}

/* Responsive */
@media (max-width: 580px) {
    .rch-channels-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .rch-plans-grid {
        grid-template-columns: 1fr;
    }
    .rch-billing-toggle {
        flex-wrap: wrap;
    }
}

/* ═══════════════════════════════════════════
   CART SYSTEM STYLES
   ═══════════════════════════════════════════ */

/* Cart Badge Button (Header) */
.rch-cart-badge-btn {
    position: relative;
    background: #000;
    border: 1px solid #000;
    color: #fff;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.25s;
    font-size: 16px;
}
.rch-cart-badge-btn:hover {
    background: #222;
    transform: scale(1.05);
}
.rch-cart-count {
    position: absolute;
    top: -6px;
    right: -6px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.4);
}
@keyframes rchCartBounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.3); }
}
.rch-cart-count.bounce {
    animation: rchCartBounce 0.35s ease;
}

/* Added to Cart feedback on plan card */
.rch-plan-card.in-cart {
    border-color: #34d399 !important;
    box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.15), 0 4px 20px rgba(16, 185, 129, 0.1) !important;
}
.rch-plan-card.in-cart::before {
    opacity: 1 !important;
    background: linear-gradient(90deg, #10b981, #34d399, #6ee7b7, #34d399, #10b981) !important;
    background-size: 200% 100% !important;
    animation: rchShimmer 3s linear infinite !important;
}
.rch-plan-card.in-cart .rch-buy-btn {
    background: linear-gradient(135deg, #10b981, #059669) !important;
    cursor: default;
}
.rch-added-check {
    display: inline-block;
    margin-right: 4px;
    animation: rchCheckPop 0.3s ease;
}
@keyframes rchCheckPop {
    0% { transform: scale(0); opacity: 0; }
    60% { transform: scale(1.3); }
    100% { transform: scale(1); opacity: 1; }
}

/* Step 3: Cart Review */
.rch-cart-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    margin-bottom: 10px;
    transition: all 0.2s;
}
.rch-cart-item:hover {
    border-color: #d1d5db;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.rch-cart-item-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 18px;
    flex-shrink: 0;
}
.rch-cart-item-info {
    flex: 1;
    min-width: 0;
}
.rch-cart-item-name {
    font-weight: 600;
    font-size: 14px;
    color: #1f2937;
    margin-bottom: 2px;
}
.rch-cart-item-meta {
    font-size: 12px;
    color: #6b7280;
}
.rch-cart-item-price {
    text-align: right;
    flex-shrink: 0;
}
.rch-cart-item-price .amount {
    font-weight: 700;
    font-size: 15px;
    color: #1f2937;
}
.rch-cart-item-price .tax-info {
    font-size: 11px;
    color: #9ca3af;
}
.rch-cart-item-remove {
    background: none;
    border: none;
    color: #d1d5db;
    font-size: 16px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: all 0.2s;
    flex-shrink: 0;
}
.rch-cart-item-remove:hover {
    background: #fef2f2;
    color: #ef4444;
}

/* Cart Summary */
.rch-cart-summary-box {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 20px;
    margin-top: 16px;
}
.rch-cart-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    font-size: 13px;
    color: #6b7280;
}
.rch-cart-summary-row.total {
    border-top: 2px solid #e2e8f0;
    margin-top: 8px;
    padding-top: 12px;
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
}
/* ─── Promo Code Section ─── */
.rch-promo-section {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px dashed #e2e8f0;
}
.rch-promo-input-wrap {
    display: flex;
    align-items: center;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    transition: border-color 0.2s;
}
.rch-promo-input-wrap:focus-within {
    border-color: #6366f1;
}
.rch-promo-icon {
    padding: 0 0 0 14px;
    color: #94a3b8;
    font-size: 14px;
}
.rch-promo-input {
    flex: 1;
    border: none;
    background: transparent;
    padding: 10px 12px;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    outline: none;
    color: #1e293b;
}
.rch-promo-input::placeholder {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 400;
    color: #94a3b8;
}
.rch-promo-apply-btn {
    background: #1e293b;
    color: #fff;
    border: none;
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s;
}
.rch-promo-apply-btn:hover {
    background: #334155;
}
.rch-promo-remove {
    background: transparent;
    border: none;
    color: #ef4444;
    padding: 10px 14px;
    font-size: 14px;
    cursor: pointer;
    transition: color 0.2s;
}
.rch-promo-remove:hover {
    color: #dc2626;
}
#rchPromoMsg {
    margin-top: 6px;
    min-height: 16px;
}
.rch-cart-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 20px;
    gap: 12px;
}
.rch-continue-btn {
    background: #fff;
    border: 1px solid #d1d5db;
    color: #374151;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.rch-continue-btn:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}
.rch-checkout-btn {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    border: none;
    padding: 12px 28px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.25s;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);
    display: flex;
    align-items: center;
    gap: 8px;
}
.rch-checkout-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
}
.rch-checkout-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* Footer adjustments */
.rch-footer {
    border-top: 1px solid #f3f4f6;
    padding: 12px 24px;
    background: #fafafa;
}

@media (max-width: 767px) {
    .rch-cart-item {
        flex-wrap: wrap;
        gap: 8px;
    }
    .rch-cart-actions {
        flex-direction: column;
    }
    .rch-checkout-btn, .rch-continue-btn {
        width: 100%;
        justify-content: center;
    }
}

/* ═══════════════════════════════════════════
   PAYMENT SUCCESS MODAL STYLES
   ═══════════════════════════════════════════ */

.ps-topbar {
    height: 5px;
    background: linear-gradient(90deg, #10b981, #22c55e, #6366f1, #a855f7);
}

/* Hero Section */
.ps-hero {
    text-align: center;
    padding: 36px 28px 20px;
    background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);
}
.ps-check-circle {
    width: 72px;
    height: 72px;
    margin: 0 auto 18px;
}
.ps-checkmark {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    display: block;
    stroke-width: 2;
    stroke: #10b981;
    stroke-miterlimit: 10;
    animation: psFill .4s ease-in-out .4s forwards, psScale .3s ease-in-out .9s both;
}
.ps-checkmark-circle {
    stroke-dasharray: 166;
    stroke-dashoffset: 166;
    stroke-width: 2;
    stroke-miterlimit: 10;
    stroke: #10b981;
    fill: none;
    animation: psStroke .6s cubic-bezier(.65,0,.45,1) forwards;
}
.ps-checkmark-check {
    transform-origin: 50% 50%;
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    stroke-width: 3;
    animation: psStroke .3s cubic-bezier(.65,0,.45,1) .8s forwards;
}
@keyframes psStroke {
    100% { stroke-dashoffset: 0; }
}
@keyframes psScale {
    0%, 100% { transform: none; }
    50% { transform: scale3d(1.1, 1.1, 1); }
}
@keyframes psFill {
    100% { box-shadow: inset 0px 0px 0px 36px rgba(16, 185, 129, 0.08); }
}
.ps-title {
    font-size: 22px;
    font-weight: 800;
    color: #065f46;
    margin: 0 0 8px;
}
.ps-subtitle {
    font-size: 13.5px;
    color: #6b7280;
    margin: 0;
    line-height: 1.5;
    font-weight: 500;
}

/* Summary Section */
.ps-summary-section {
    padding: 0 28px;
    margin-top: 20px;
}
.ps-summary-label, .ps-items-label {
    font-size: 11px;
    font-weight: 700;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.ps-summary-label i, .ps-items-label i {
    font-size: 13px;
    color: #6366f1;
}
.ps-summary-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.ps-summary-item {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 10px;
    padding: 12px 14px;
}
.ps-summary-item-label {
    font-size: 11px;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 4px;
}
.ps-summary-item-value {
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
    word-break: break-all;
}
.ps-summary-item-value.ps-amount-highlight {
    color: #059669;
    font-size: 16px;
}

/* Items Section */
.ps-items-section {
    padding: 20px 28px 0;
}
.ps-item-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    background: #fff;
    border: 1px solid #f1f5f9;
    border-radius: 12px;
    margin-bottom: 8px;
    transition: all .2s;
}
.ps-item-card:hover {
    border-color: #e0e7ff;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.06);
}
.ps-item-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 18px;
    flex-shrink: 0;
}
.ps-item-info {
    flex: 1;
    min-width: 0;
}
.ps-item-name {
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 2px;
}
.ps-item-meta {
    font-size: 11px;
    color: #94a3b8;
    font-weight: 500;
}
.ps-item-price {
    text-align: right;
    flex-shrink: 0;
}
.ps-item-price-value {
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
}
.ps-item-price-original {
    font-size: 11px;
    color: #cbd5e1;
    text-decoration: line-through;
}

/* Total Section */
.ps-total-section {
    margin: 12px 28px 20px;
    padding: 16px 18px;
    background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
    border: 1px solid #bbf7d0;
    border-radius: 12px;
}
.ps-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: #4b5563;
    margin-bottom: 6px;
}
.ps-total-row:last-child {
    margin-bottom: 0;
}
.ps-total-row.ps-total-grand {
    font-size: 16px;
    font-weight: 800;
    color: #065f46;
    padding-top: 10px;
    margin-top: 6px;
    border-top: 1px dashed #a7f3d0;
}
.ps-total-row .ps-savings-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #dcfce7;
    color: #166534;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

/* Close Button */
.ps-close-btn {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    border: none;
    padding: 12px 36px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: all .25s;
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: 'Inter', -apple-system, sans-serif;
}
.ps-close-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
}
.ps-close-btn:active {
    transform: translateY(0);
}

/* ═══ Coupon Section Styles ═══ */
.cpn-section {
    margin: 20px 0 4px;
    text-align: center;
}
.cpn-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #6366f1;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    padding: 8px 4px;
    border-radius: 0;
    border: none;
    background: none;
    transition: all .25s;
    cursor: pointer;
}
.cpn-toggle:hover {
    color: #4f46e5;
    text-decoration: none;
    opacity: 0.85;
}
.cpn-toggle .fa-ticket {
    font-size: 16px;
}
.cpn-body {
    margin-top: 14px;
    padding: 0 10px;
}
.cpn-input-row {
    display: flex;
    gap: 8px;
    max-width: 420px;
    margin: 0 auto;
}
.cpn-input {
    flex: 1;
    padding: 10px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: #1e293b;
    outline: none;
    transition: border-color .2s;
    font-family: 'Inter', monospace, sans-serif;
}
.cpn-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}
.cpn-apply-btn {
    padding: 10px 22px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: all .25s;
    display: flex;
    align-items: center;
    gap: 6px;
}
.cpn-apply-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
}
.cpn-apply-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}
.cpn-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 12px;
    color: #6b7280;
    font-size: 13px;
    font-weight: 500;
}
.cpn-error {
    margin-top: 12px;
    padding: 10px 16px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 10px;
    color: #dc2626;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    max-width: 420px;
    margin-left: auto;
    margin-right: auto;
}
.cpn-error .fa-times-circle {
    font-size: 16px;
}
.cpn-details-card {
    margin: 16px auto 0;
    max-width: 460px;
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
    border: 1.5px solid #bbf7d0;
    border-radius: 14px;
    padding: 20px;
    text-align: left;
}
.cpn-details-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 16px;
}
.cpn-details-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #10b981, #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 20px;
    flex-shrink: 0;
}
.cpn-details-info h5 {
    margin: 0 0 3px;
    font-size: 16px;
    font-weight: 800;
    letter-spacing: 1.5px;
    color: #065f46;
    font-family: 'Inter', monospace, sans-serif;
}
.cpn-details-info p {
    margin: 0;
    font-size: 13px;
    color: #6b7280;
    font-weight: 500;
}
.cpn-credits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 8px;
    margin-bottom: 12px;
}
.cpn-credit-badge {
    padding: 10px 12px;
    border-radius: 10px;
    text-align: center;
    font-weight: 700;
}
.cpn-credit-badge .cpn-credit-count {
    display: block;
    font-size: 20px;
    line-height: 1.2;
}
.cpn-credit-badge .cpn-credit-label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    opacity: 0.8;
    margin-top: 2px;
}
.cpn-expiry-note {
    font-size: 12px;
    color: #92400e;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
    padding: 8px 12px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.cpn-claim-btn {
    width: 100%;
    padding: 13px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: all .25s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    letter-spacing: 0.3px;
}
.cpn-claim-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
}
.cpn-claim-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}
.cpn-success {
    text-align: center;
    padding: 20px 10px;
    max-width: 420px;
    margin: 12px auto 0;
}
.cpn-success-icon {
    font-size: 48px;
    color: #10b981;
    margin-bottom: 10px;
    animation: cpnPulse 0.6s ease;
}
@keyframes cpnPulse {
    0% { transform: scale(0.3); opacity: 0; }
    50% { transform: scale(1.15); }
    100% { transform: scale(1); opacity: 1; }
}
.cpn-success h5 {
    font-size: 18px;
    font-weight: 800;
    color: #065f46;
    margin: 0 0 6px;
}
.cpn-success p {
    font-size: 13px;
    color: #6b7280;
    margin: 0 0 14px;
}
.cpn-success-credits {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px;
}
.cpn-success-badge {
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
</style>

<script>
    var master_url = '<?php echo function_exists("perfex_saas_default_base_url") ? rtrim(perfex_saas_default_base_url(), "/") . "/" : ""; ?>';
    var tenant_client_id = '<?php echo function_exists("perfex_saas_tenant") && perfex_saas_tenant() ? perfex_saas_tenant()->clientid : ""; ?>';

    // ═══ Recharge Wizard State ═══
    var rchAllPlans = [];
    var rchCart = []; // Cart: array of plan objects
    var rchPromoCode = '';      // Applied promo code
    var rchPromoDiscount = 0;   // Discount amount
    var rchPromoId = 0;         // Promo code ID from DB
    var rchSelectedType = '';
    var rchSelectedCycle = 'monthly';
    var rchShowAllCycles = false; // When true, tabs are hidden and all cycles shown together

    // Current allocation data (injected from PHP)
    var rchAllocations = <?php
        $alloc_data = [];
        if (isset($allocations) && $allocations) {
            $alloc_types = ['sms', 'whatsapp', 'email', 'aicall'];
            foreach ($alloc_types as $atype) {
                $promo_count = isset($allocations->{$atype . '_promo_count'}) ? (int)$allocations->{$atype . '_promo_count'} : 0;
                $trans_count = isset($allocations->{$atype . '_trans_count'}) ? (int)$allocations->{$atype . '_trans_count'} : 0;
                $promo_expiry = isset($allocations->{$atype . '_promo_expiry'}) ? $allocations->{$atype . '_promo_expiry'} : null;
                $trans_expiry = isset($allocations->{$atype . '_trans_expiry'}) ? $allocations->{$atype . '_trans_expiry'} : null;
                $alloc_data[$atype] = [
                    'promo_count' => $promo_count,
                    'trans_count' => $trans_count,
                    'promo_expiry' => $promo_expiry,
                    'trans_expiry' => $trans_expiry,
                    'total' => $promo_count + $trans_count,
                ];
            }
        }
        echo json_encode($alloc_data);
    ?>;

    // Channel registry
    var rchChannels = [
        { type: 'sms',          icon: 'fa fa-comment',       label: 'SMS',           gradient: 'linear-gradient(135deg, #3b82f6, #2563eb)' },
        { type: 'whatsapp',     icon: 'fab fa-whatsapp',     label: 'WhatsApp',      gradient: 'linear-gradient(135deg, #22c55e, #16a34a)' },
        { type: 'email',        icon: 'fa fa-envelope',      label: 'Email',         gradient: 'linear-gradient(135deg, #f59e0b, #d97706)' },
        { type: 'aicall',       icon: 'fa fa-phone',         label: 'AI Call',       gradient: 'linear-gradient(135deg, #a855f7, #9333ea)' }
    ];

    // Currency lookup map (injected from Perfex CRM tblcurrencies)
    var rchCurrencyMap = <?php
        $CI =& get_instance();
        $CI->load->model('currencies_model');
        $all_currencies = $CI->currencies_model->get();
        $base_currency = $CI->currencies_model->get_base_currency();
        $map = [];
        foreach ($all_currencies as $c) {
            $map[$c['id']] = $c['symbol'];
        }
        echo json_encode($map);
    ?>;
    var rchBaseCurrencyId = '<?php echo $base_currency->id; ?>';

    /**
     * Open the Recharge modal — fetch plans and show Step 1
     */
    function openRechargeModal() {
        if (!master_url || !tenant_client_id) {
            alert_float('danger', 'SaaS parameters missing. Cannot initialize recharge.');
            return;
        }

        // Reset state (but preserve cart)
        rchAllPlans = [];
        rchSelectedType = '';
        rchSelectedCycle = 'monthly';

        // Restore cart from localStorage
        rchLoadCart();

        // Reset coupon state
        $('#cpnBody').hide();
        if (typeof resetCouponState === 'function') resetCouponState();
        $('.cpn-input-row').show();

        // Show modal with loading
        $('#recharge_error').hide();
        $('#rchStep1').hide();
        $('#rchStep2').hide();
        $('#recharge_loading').show();
        $('#rchBackBtn').hide();
        $('#rchStepLabel').text('Step 1 of 2 — Choose Channel');
        $('#rechargeModal').modal('show');

        // Fetch plans from master CRM
        $.ajax({
            url: master_url + 'ccx_msgs/ccx_msgs_public/get_pricing_plans',
            type: 'GET',
            dataType: 'json',
            timeout: 15000,
            success: function(resp) {
                $('#recharge_loading').hide();
                if (resp.success && resp.plans && resp.plans.length > 0) {
                    rchAllPlans = resp.plans;
                    // Refresh cart plan objects with fresh data
                    rchRefreshCartPlans();
                    rchRenderStep1();
                } else {
                    $('#recharge_error_msg').text('No pricing plans available. Please contact support.');
                    $('#recharge_error').show();
                }
            },
            error: function() {
                $('#recharge_loading').hide();
                $('#recharge_error_msg').text('Failed to load pricing plans. Make sure the master CRM is accessible.');
                $('#recharge_error').show();
            }
        });
    }

    /**
     * STEP 1: Render channel selection cards
     */
    function rchRenderStep1() {
        // Count plans per channel to show availability
        var counts = {};
        rchAllPlans.forEach(function(p) {
            counts[p.message_type] = (counts[p.message_type] || 0) + 1;
        });

        var html = '';
        rchChannels.forEach(function(ch) {
            var planCount = counts[ch.type] || 0;
            var countText = planCount > 0
                ? planCount + ' plan' + (planCount > 1 ? 's' : '') + ' available'
                : 'No plans';
            var disabledClass = planCount === 0 ? ' style="opacity:0.45; pointer-events:none;"' : '';

            // Count cart items for this channel
            var cartCount = rchCart.filter(function(c) { return c.message_type === ch.type; }).length;
            var cartBadge = '';
            if (cartCount > 0) {
                cartBadge = '<span class="rch-channel-cart-badge">' +
                    '<i class="fa fa-shopping-cart" style="margin-right:3px;"></i>' + cartCount + ' in cart</span>';
            }

            html += '<div class="rch-channel-card" data-type="' + ch.type + '" onclick="rchSelectChannel(\'' + ch.type + '\')"' + disabledClass + '>' +
                        '<div class="rch-channel-icon" style="background:' + ch.gradient + '">' +
                            '<i class="' + ch.icon + '"></i>' +
                        '</div>' +
                        '<div class="rch-channel-name">' + ch.label + '</div>' +
                        '<div class="rch-channel-count">' + countText + '</div>' +
                        cartBadge +
                        '<div class="rch-channel-select">Select <i class="fa fa-arrow-right"></i></div>' +
                    '</div>';
        });

        $('#rchChannelsGrid').html(html);
        $('#rchStep1').show();
        $('#rchStep2').hide();
        $('#rchBackBtn').hide();
        $('#rchStepLabel').text('Step 1 of 2 — Choose Channel');
    }

    /**
     * STEP 2: User selected a channel → show plans
     */
    function rchSelectChannel(type) {
        rchSelectedType = type;
        rchSelectedCycle = 'monthly'; // reset to monthly

        // Update header
        $('#rchStepLabel').text('Step 2 of 2 — Choose Plan');
        $('#rchBackBtn').css('display', 'flex');

        // Show selected channel badge in header (centered)
        var ch = rchChannels.find(function(c) { return c.type === type; });
        if (ch) {
            $('#rchSelectedChannelHeader').html(
                '<span class="rch-selected-channel" style="margin:0;"><i class="' + ch.icon + '"></i> ' + ch.label + ' Plans</span>'
            ).find('.rch-selected-channel').css('background', ch.gradient);
            $('#rchSelectedChannelHeader').show();
        }

        // Smart tab hiding: count plans per cycle for this channel
        var cycleCounts = { monthly: 0, quarterly: 0, yearly: 0 };
        rchAllPlans.forEach(function(p) {
            if (p.message_type === type) {
                var cycle = p.billing_cycle || 'monthly';
                if (cycleCounts.hasOwnProperty(cycle)) {
                    cycleCounts[cycle]++;
                }
            }
        });
        // If every cycle with plans has at most 1 plan, show all together
        rchShowAllCycles = (cycleCounts.monthly <= 1 && cycleCounts.quarterly <= 1 && cycleCounts.yearly <= 1);

        if (rchShowAllCycles) {
            $('#rchBillingToggle').hide();
        } else {
            $('#rchBillingToggle').show();
            // Reset billing cycle toggle
            $('.rch-cycle-btn').removeClass('active');
            $('.rch-cycle-btn[data-cycle="monthly"]').addClass('active');
        }

        // Transition
        $('#rchStep1').hide();
        $('#rchStep2').css('display', '').hide().fadeIn(250);

        rchRenderPlans();
    }

    /**
     * Switch billing cycle
     */
    function rchSwitchCycle(cycle, btn) {
        rchSelectedCycle = cycle;
        $('.rch-cycle-btn').removeClass('active');
        $(btn).addClass('active');
        rchRenderPlans();
    }

    /**
     * Render plan cards for the selected channel + cycle
     */
    function rchRenderPlans() {
        var filtered;
        if (rchShowAllCycles) {
            // Show all plans for this channel across all cycles
            filtered = rchAllPlans.filter(function(p) {
                return p.message_type === rchSelectedType;
            });
            // Sort: monthly first, then quarterly, then yearly
            var cycleOrder = { monthly: 1, quarterly: 2, yearly: 3 };
            filtered.sort(function(a, b) {
                return (cycleOrder[a.billing_cycle || 'monthly'] || 9) - (cycleOrder[b.billing_cycle || 'monthly'] || 9);
            });
        } else {
            filtered = rchAllPlans.filter(function(p) {
                return p.message_type === rchSelectedType &&
                       (p.billing_cycle || 'monthly') === rchSelectedCycle;
            });
        }

        if (filtered.length === 0) {
            $('#rchPlansGrid').hide();
            $('#rchPlansEmpty').show();
            return;
        }

        $('#rchPlansEmpty').hide();
        $('#rchPlansGrid').show();

        // ═══ Current Balance Banner ═══
        var allocType = rchSelectedType;
        var alloc = rchAllocations[allocType] || null;
        var balanceHtml = '';
        if (alloc && (alloc.promo_count > 0 || alloc.trans_count > 0)) {
            balanceHtml = '<div class="rch-balance-banner">' +
                '<div class="rch-balance-title"><i class="fa fa-wallet" style="margin-right:6px;"></i>Current Balance</div>' +
                '<div class="rch-balance-items">';
            if (alloc.promo_count > 0) {
                var promoExp = alloc.promo_expiry ? new Date(alloc.promo_expiry).toLocaleDateString('en-IN', {day:'numeric',month:'short',year:'numeric'}) : '—';
                balanceHtml += '<div class="rch-balance-item">' +
                    '<span class="rch-balance-label">Promotional</span>' +
                    '<span class="rch-balance-value">' + numberWithCommas(alloc.promo_count) + ' credits</span>' +
                    '<span class="rch-balance-expiry">Expires: ' + promoExp + '</span>' +
                '</div>';
            }
            if (alloc.trans_count > 0) {
                var transExp = alloc.trans_expiry ? new Date(alloc.trans_expiry).toLocaleDateString('en-IN', {day:'numeric',month:'short',year:'numeric'}) : '—';
                balanceHtml += '<div class="rch-balance-item">' +
                    '<span class="rch-balance-label">Transactional</span>' +
                    '<span class="rch-balance-value">' + numberWithCommas(alloc.trans_count) + ' credits</span>' +
                    '<span class="rch-balance-expiry">Expires: ' + transExp + '</span>' +
                '</div>';
            }
            balanceHtml += '</div></div>';
        } else {
            balanceHtml = '<div class="rch-balance-banner rch-balance-empty">' +
                '<i class="fa fa-info-circle" style="margin-right:6px;"></i>' +
                'No active credits for this channel. Purchase a plan below to get started.' +
            '</div>';
        }
        $('#rchBalanceBanner').html(balanceHtml).show();

        // Find "best value" — the plan with the highest discount
        var bestIdx = 0;
        var bestDiscount = 0;
        filtered.forEach(function(p, i) {
            var disc = parseFloat(p.discount_percent) || 0;
            if (disc > bestDiscount) {
                bestDiscount = disc;
                bestIdx = i;
            }
        });

        // Get channel colors
        var ch = rchChannels.find(function(c) { return c.type === rchSelectedType; });
        var btnGradient = ch ? ch.gradient : 'linear-gradient(135deg, #6366f1, #4f46e5)';

        // Cycle label config for "show all" mode
        var cycleLabels = {
            monthly:   { label: 'Monthly',   icon: 'fa-calendar-o',      bg: 'linear-gradient(135deg, #3b82f6, #2563eb)' },
            quarterly: { label: 'Quarterly', icon: 'fa-calendar',         bg: 'linear-gradient(135deg, #8b5cf6, #7c3aed)' },
            yearly:    { label: 'Yearly',    icon: 'fa-calendar-check-o', bg: 'linear-gradient(135deg, #10b981, #059669)' }
        };

        // Smart subtype detection: only show badge if channel has BOTH subtypes
        var subtypes = {};
        filtered.forEach(function(p) { subtypes[p.message_subtype || 'promotional'] = true; });
        var showSubtypeBadge = (subtypes['promotional'] && subtypes['transactional']);

        var html = '';
        filtered.forEach(function(plan, idx) {
            var price = parseFloat(plan.price) || 0;
            var discount = parseFloat(plan.discount_percent) || 0;
            var discountAmount = price * (discount / 100);
            var finalPrice = price - discountAmount;
            var msgCount = parseInt(plan.message_count) || 0;
            var cpm = msgCount > 0 ? (finalPrice / msgCount) : 0;
            var cid = plan.currency_id || '';

            // Tax calculations
            var taxPercent = parseFloat(plan.tax_percent) || 0;
            var taxAmount = (finalPrice * taxPercent) / 100;
            var totalPayable = finalPrice + taxAmount;

            // Subtype badge (only if channel has both subtypes)
            var subtype = plan.message_subtype || 'promotional';
            var subtypeBadge = '';
            if (showSubtypeBadge) {
                if (subtype === 'transactional') {
                    subtypeBadge = '<span class="rch-subtype-badge" style="background:linear-gradient(135deg,#6366f1,#4f46e5);"><i class="fa fa-exchange"></i> Transactional</span>';
                } else {
                    subtypeBadge = '<span class="rch-subtype-badge" style="background:linear-gradient(135deg,#f59e0b,#d97706);"><i class="fa fa-bullhorn"></i> Promotional</span>';
                }
            }

            var isBest = (filtered.length > 1 && bestDiscount > 0 && idx === bestIdx);
            var isInCart = rchCart.some(function(c) { return c.id == plan.id; });
            var cardClass = 'rch-plan-card' + (isBest ? ' rch-best-value' : '') + (isInCart ? ' in-cart' : '');

            html += '<div class="' + cardClass + '" id="rch-plan-card-' + plan.id + '">';

            // ─── Header Zone: Best badge + cycle/subtype badges + plan name ───
            html += '<div class="rch-card-header-zone">';

            // Best value badge (absolute positioned)
            if (isBest) {
                html += '<div class="rch-best-badge"><i class="fa fa-star" style="margin-right:3px;"></i> Best Value</div>';
            }

            // Badges row
            html += '<div class="rch-card-badges" style="' + (isBest ? 'margin-top:16px;' : '') + '">';

            // Billing cycle badge (only in "show all" mode)
            var planCycle = plan.billing_cycle || 'monthly';
            var cycleMeta = cycleLabels[planCycle] || cycleLabels.monthly;
            if (rchShowAllCycles) {
                html += '<span class="rch-cycle-label-badge" style="background:' + cycleMeta.bg + ';">' +
                    '<i class="fa ' + cycleMeta.icon + '"></i> ' + cycleMeta.label +
                    '</span>';
            }

            // Subtype badge
            html += subtypeBadge;
            html += '</div>'; // end badges

            // Plan name
            html += '<div class="rch-plan-name">' + $('<span>').text(plan.plan_name).html() + '</div>';
            html += '</div>'; // end header zone

            // ─── Pricing Zone ───
            html += '<div class="rch-card-pricing-zone">';
            html += '<div class="rch-plan-price-wrap">';
            if (discount > 0) {
                html += '<div class="rch-plan-original">' + rchFormatMoney(price, cid) + '</div>';
            }
            html += '<div class="rch-plan-final">' + rchFormatMoney(finalPrice, cid) + '</div>';
            // Tax info line
            if (taxPercent > 0) {
                html += '<div class="rch-plan-tax-info">+ ' + rchFormatMoney(taxAmount, cid) + ' tax (' + taxPercent + '%)</div>';
                html += '<div class="rch-plan-total">Total: ' + rchFormatMoney(totalPayable, cid) + '</div>';
            }
            html += '</div>'; // end price wrap

            // Discount badge
            if (discount > 0) {
                html += '<span class="rch-discount-badge"><i class="fa fa-tag"></i> ' + discount + '% OFF — Save ' + rchFormatMoney(discountAmount, cid) + '</span>';
            }
            html += '</div>'; // end pricing zone

            // ─── Divider ───
            html += '<div class="rch-card-divider"></div>';

            // ─── Features Zone ───
            html += '<div class="rch-card-features-zone">';
            html += '<ul class="rch-plan-details">';
            html += '<li><span class="rch-check-icon"><i class="fa fa-check"></i></span> <strong>' + numberWithCommas(msgCount) + '</strong>&nbsp;Credits</li>';
            html += '<li><span class="rch-check-icon"><i class="fa fa-check"></i></span> Valid for <strong>' + plan.expiry_days + '</strong> days</li>';
            html += '<li><span class="rch-check-icon"><i class="fa fa-check"></i></span> Cost per message: <strong>' + rchFormatMoneyPrecise(cpm, cid) + '</strong></li>';
            if (plan.offer_description) {
                var desc = plan.offer_description.split('\n')[0]; // first line only
                desc = desc.replace(/[\u{1F000}-\u{1FFFF}|\u{2600}-\u{27BF}|\u{FE00}-\u{FE0F}|\u{1F900}-\u{1F9FF}]/gu, '').trim();
                if (desc.length > 50) desc = desc.substring(0, 50) + '…';
                html += '<li><span class="rch-check-icon"><i class="fa fa-check"></i></span> ' + $('<span>').text(desc).html() + '</li>';
            }
            html += '</ul>';
            html += '</div>'; // end features zone

            // ─── Action Zone ───
            html += '<div class="rch-card-action-zone">';
            if (isInCart) {
                html += '<button class="rch-buy-btn" style="background:linear-gradient(135deg, #10b981, #059669);" disabled>' +
                            '<span class="rch-added-check"><i class="fa fa-check"></i></span> Added to Cart' +
                        '</button>';
            } else {
                html += '<button class="rch-buy-btn" style="background:' + btnGradient + ';" onclick="addToCart(' + plan.id + ')">' +
                            '<i class="fa fa-cart-plus"></i> Add to Cart' +
                        '</button>';
            }
            html += '</div>'; // end action zone

            html += '</div>'; // end card
        });

        // Animate in
        var $grid = $('#rchPlansGrid');
        $grid.css('opacity', 0).html(html).animate({ opacity: 1 }, 250);
    }

    /**
     * Go back to Step 1
     */
    function rchGoBack() {
        // If on Step 3, go back to Step 1
        if ($('#rchStep3').is(':visible')) {
            $('#rchStep3').hide();
            rchRenderStep1(); // Re-render to update cart badges
            $('#rchStep1').css('display', '').hide().fadeIn(200);
            $('#rchBackBtn').hide();
            $('#rchSelectedChannelHeader').hide();
            return;
        }
        // From Step 2, go to Step 1
        rchSelectedType = '';
        $('#rchStep2').hide();
        rchRenderStep1(); // Re-render to update cart badges
        $('#rchStep1').css('display', '').hide().fadeIn(200);
        $('#rchBackBtn').hide();
        $('#rchSelectedChannelHeader').hide();
    }

    /**
     * Buy plan — redirect to checkout
     */
    function buyPlan(plan_id) {
        var url = master_url + 'ccx_msgs/ccx_msgs_public/initiate_recharge?' +
                  'client_id=' + encodeURIComponent(tenant_client_id) +
                  '&plan_id=' + encodeURIComponent(plan_id) +
                  '&return_url=' + encodeURIComponent(window.location.href);

        window.location.href = url;
    }

    /**
     * Helpers
     */
    function rchFormatMoney(amount, currencyId) {
        // Resolve symbol from currency map
        var symbol = rchGetCurrencySymbol(currencyId);
        var num = parseFloat(amount) || 0;
        // Show 2 decimal places, Indian format (e.g. 1,00,000.18)
        var formatted = num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        // Strip trailing .00 for clean display (e.g. 1,350 instead of 1,350.00)
        formatted = formatted.replace(/\.00$/, '');
        return symbol + formatted;
    }

    /**
     * Format money with decimal precision (for cost-per-message)
     */
    function rchFormatMoneyPrecise(amount, currencyId) {
        var symbol = rchGetCurrencySymbol(currencyId);
        var num = parseFloat(amount) || 0;
        // Show 2 decimal places, or 4 for very small amounts
        var decimals = (num > 0 && num < 0.01) ? 4 : 2;
        var formatted = num.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        return symbol + formatted;
    }

    function rchGetCurrencySymbol(currencyId) {
        if (currencyId && rchCurrencyMap[currencyId]) {
            return rchCurrencyMap[currencyId];
        } else if (typeof app !== 'undefined' && app.options && app.options.currency_symbol) {
            return app.options.currency_symbol;
        }
        return rchCurrencyMap[rchBaseCurrencyId] || '<?php echo get_base_currency()->symbol; ?>';
    }

    function numberWithCommas(x) {
        // Indian numbering format
        return parseInt(x).toLocaleString('en-IN');
    }

    // Reset modal state when closed — but KEEP the cart
    // NOTE: This must be called AFTER jQuery is loaded (from the jQuery-ready block)
    function rchInitModalCloseHandler() {
        $('#rechargeModal').on('hidden.bs.modal', function() {
            rchAllPlans = [];
            // DO NOT clear rchCart — it persists via localStorage
            rchSelectedType = '';
            rchSelectedCycle = 'monthly';
            $('#rchStep1').hide();
            $('#rchStep2').hide();
            $('#rchStep3').hide();
            $('#recharge_loading').hide();
            $('#recharge_error').hide();
            $('#rchCartBadgeBtn').hide();
        });
    }

    // ═══════════════════════════════════════════
    //  CART SYSTEM FUNCTIONS
    // ═══════════════════════════════════════════

    /**
     * Add a plan to the cart
     */
    function addToCart(planId) {
        var plan = rchAllPlans.find(function(p) { return p.id == planId; });
        if (!plan) return;

        // Prevent duplicates
        if (rchCart.some(function(c) { return c.id == planId; })) return;

        rchCart.push(plan);
        rchSaveCart();
        rchUpdateCartBadge();

        // Re-render current plans to show "Added" state
        rchRenderPlans();

        // Show the cart badge button
        $('#rchCartBadgeBtn').css('display', 'flex');
    }

    /**
     * Remove a plan from the cart
     */
    function removeFromCart(planId) {
        rchCart = rchCart.filter(function(c) { return c.id != planId; });
        rchSaveCart();
        rchUpdateCartBadge();
        rchRenderCartItems();

        // Also re-render plan cards if Step 2 was previously showing this plan
        if ($('#rchStep2').is(':visible')) {
            rchRenderPlans();
        }

        // Hide cart badge if empty
        if (rchCart.length === 0) {
            $('#rchCartBadgeBtn').hide();
        }
    }

    /**
     * Update the cart count badge in header
     */
    function rchUpdateCartBadge() {
        var count = rchCart.length;
        var $badge = $('#rchCartCount');
        $badge.text(count);

        // Bounce animation
        $badge.removeClass('bounce');
        if (count > 0) {
            setTimeout(function() { $badge.addClass('bounce'); }, 10);
        }
    }

    /**
     * Navigate to Step 3 (Cart Review)
     */
    function rchShowCart() {
        $('#rchStep1').hide();
        $('#rchStep2').hide();
        $('#rchStep3').show();
        $('#rchSelectedChannelHeader').hide();
        $('#rchBackBtn').css('display', 'flex');

        rchRenderCartItems();
    }

    /**
     * Go back from cart to Step 1 (continue shopping)
     */
    function rchContinueShopping() {
        $('#rchStep3').hide();
        rchRenderStep1(); // Re-render to update cart badges
        $('#rchStep1').css('display', '').hide().fadeIn(200);
        $('#rchBackBtn').hide();
        $('#rchSelectedChannelHeader').hide();
    }

    /**
     * Render the cart items in Step 3
     */
    function rchRenderCartItems() {
        if (rchCart.length === 0) {
            $('#rchCartEmpty').show();
            $('#rchCartList').html('');
            $('#rchCartSummary').hide();
            return;
        }

        $('#rchCartEmpty').hide();
        var html = '';
        var grandSubtotal = 0;
        var grandTax = 0;
        var grandOriginal = 0;
        var grandSavings = 0;

        rchCart.forEach(function(plan) {
            var ch = rchChannels.find(function(c) { return c.type === plan.message_type; });
            var icon = ch ? ch.icon : 'fa fa-circle';
            var gradient = ch ? ch.gradient : 'linear-gradient(135deg, #6b7280, #4b5563)';
            var channelLabel = ch ? ch.label : plan.message_type;

            var price = parseFloat(plan.price) || 0;
            var discount = parseFloat(plan.discount_percent) || 0;
            var discountAmount = price * (discount / 100);
            var finalPrice = price - discountAmount;
            var taxPercent = parseFloat(plan.tax_percent) || 0;
            var taxAmount = (finalPrice * taxPercent) / 100;
            var total = finalPrice + taxAmount;
            var cid = plan.currency_id || '';

            grandSubtotal += finalPrice;
            grandTax += taxAmount;
            grandOriginal += price;
            grandSavings += discountAmount;

            var subtype = plan.message_subtype || 'promotional';
            var subtypeLabel = subtype === 'transactional' ? 'Trans' : 'Promo';

            html += '<div class="rch-cart-item" id="cart-item-' + plan.id + '">';
            html += '  <div class="rch-cart-item-icon" style="background:' + gradient + ';"><i class="' + icon + '"></i></div>';
            html += '  <div class="rch-cart-item-info">';
            html += '    <div class="rch-cart-item-name">' + $('<span>').text(plan.plan_name).html() + '</div>';
            html += '    <div class="rch-cart-item-meta">' + channelLabel + ' · ' + subtypeLabel + ' · ' + numberWithCommas(plan.message_count) + ' credits · ' + plan.expiry_days + ' days</div>';
            html += '  </div>';
            html += '  <div class="rch-cart-item-price">';
            html += '    <div class="amount">' + rchFormatMoney(finalPrice, cid) + '</div>';
            if (discountAmount > 0) {
                html += '    <div style="font-size:10px; color:#94a3b8; text-decoration:line-through;">' + rchFormatMoney(price, cid) + '</div>';
                html += '    <div style="font-size:10px; color:#10b981; font-weight:600;"><i class="fa fa-tag" style="margin-right:2px;"></i>Save ' + rchFormatMoney(discountAmount, cid) + '</div>';
            }
            if (taxPercent > 0) {
                var taxLabel = plan.tax_name ? plan.tax_name : 'Tax';
                html += '    <div class="tax-info">+ ' + rchFormatMoney(taxAmount, cid) + ' ' + taxLabel + ' (' + taxPercent + '%)</div>';
            }
            html += '  </div>';
            html += '  <button class="rch-cart-item-remove" onclick="removeFromCart(' + plan.id + ')" title="Remove"><i class="fa fa-times"></i></button>';
            html += '</div>';
        });

        $('#rchCartList').html(html);

        // Build per-tax breakdown
        var taxBreakdown = {};
        rchCart.forEach(function(plan) {
            var tp = parseFloat(plan.tax_percent) || 0;
            if (tp <= 0) return;
            var tn = plan.tax_name || 'Tax';
            var key = tn + '|' + tp;
            var price2 = parseFloat(plan.price) || 0;
            var disc2 = parseFloat(plan.discount_percent) || 0;
            var fp2 = price2 - (price2 * disc2 / 100);
            var ta2 = (fp2 * tp) / 100;
            if (!taxBreakdown[key]) taxBreakdown[key] = { name: tn, percent: tp, amount: 0 };
            taxBreakdown[key].amount += ta2;
        });

        // Promo discount
        var promoDiscount = rchPromoDiscount || 0;
        var grandTotal = grandSubtotal + grandTax - promoDiscount;
        if (grandTotal < 0) grandTotal = 0;

        var summaryHtml = '<div class="rch-cart-summary-box">';
        summaryHtml += '<div class="rch-cart-summary-row"><span>Subtotal (' + rchCart.length + ' plan' + (rchCart.length > 1 ? 's' : '') + ')</span><span>' + rchFormatMoney(grandSubtotal, '') + '</span></div>';

        // Tax breakdown rows
        Object.keys(taxBreakdown).forEach(function(key) {
            var tb = taxBreakdown[key];
            summaryHtml += '<div class="rch-cart-summary-row"><span>' + tb.name + ' (' + tb.percent + '%)</span><span>+ ' + rchFormatMoney(tb.amount, '') + '</span></div>';
        });

        // Promo discount row
        if (promoDiscount > 0) {
            summaryHtml += '<div class="rch-cart-summary-row" style="color:#10b981;"><span><i class="fa fa-tag" style="margin-right:4px;"></i>Promo Discount (' + rchPromoCode + ')</span><span>- ' + rchFormatMoney(promoDiscount, '') + '</span></div>';
        }

        // Total savings row (plan discounts + promo discount)
        var totalSavings = grandSavings + promoDiscount;
        if (totalSavings > 0) {
            summaryHtml += '<div class="rch-cart-summary-row" style="color:#10b981; font-weight:600;"><span><i class="fa fa-trophy" style="margin-right:4px;"></i>You Save</span><span>' + rchFormatMoney(totalSavings, '') + '</span></div>';
        }

        summaryHtml += '<div class="rch-cart-summary-row total"><span>Total</span><span>' + rchFormatMoney(grandTotal, '') + '</span></div>';
        summaryHtml += '</div>';

        // Promo Code Input
        summaryHtml += '<div class="rch-promo-section">';
        summaryHtml += '  <div class="rch-promo-input-wrap">';
        summaryHtml += '    <i class="fa fa-tag rch-promo-icon"></i>';
        summaryHtml += '    <input type="text" id="rchPromoInput" class="rch-promo-input" placeholder="Enter promo or referral code" value="' + (rchPromoCode || '') + '">';
        if (rchPromoCode && promoDiscount > 0) {
            summaryHtml += '    <button class="rch-promo-remove" onclick="rchRemovePromo()"><i class="fa fa-times"></i></button>';
        } else {
            summaryHtml += '    <button class="rch-promo-apply-btn" onclick="rchApplyPromo()">Apply</button>';
        }
        summaryHtml += '  </div>';
        summaryHtml += '  <div id="rchPromoMsg"></div>';
        summaryHtml += '</div>';

        // Actions
        summaryHtml += '<div class="rch-cart-actions">';
        summaryHtml += '<button class="rch-continue-btn" onclick="rchContinueShopping()"><i class="fa fa-plus" style="margin-right:4px;"></i> Add More Plans</button>';
        summaryHtml += '<button class="rch-checkout-btn" onclick="proceedToCheckout()"><i class="fa fa-lock"></i> Proceed to Checkout — ' + rchFormatMoney(grandTotal, '') + '</button>';
        summaryHtml += '</div>';

        $('#rchCartSummary').html(summaryHtml).show();
    }

    /**
     * Proceed to checkout with cart items
     */
    function proceedToCheckout() {
        if (rchCart.length === 0) return;

        // Build form and POST to the cart recharge endpoint
        var $form = $('<form>', { method: 'GET', action: master_url + 'ccx_msgs/ccx_msgs_public/initiate_cart_recharge' });
        $form.append($('<input>', { type: 'hidden', name: 'client_id', value: tenant_client_id }));

        rchCart.forEach(function(plan) {
            $form.append($('<input>', { type: 'hidden', name: 'plan_ids[]', value: plan.id }));
        });

        $form.append($('<input>', { type: 'hidden', name: 'return_url', value: window.location.href }));

        // Pass promo code if applied
        if (rchPromoCode && rchPromoId) {
            $form.append($('<input>', { type: 'hidden', name: 'promo_code', value: rchPromoCode }));
            $form.append($('<input>', { type: 'hidden', name: 'promo_id', value: rchPromoId }));
        }

        // Clear cart from localStorage on checkout
        rchClearCartStorage();

        $form.appendTo('body').submit();
    }

    // ═══════════════════════════════════════════
    //  PROMO CODE FUNCTIONS
    // ═══════════════════════════════════════════

    function rchApplyPromo() {
        var code = ($('#rchPromoInput').val() || '').trim();
        if (!code) {
            $('#rchPromoMsg').html('<span style="color:#ef4444; font-size:12px;">Please enter a code</span>');
            return;
        }

        // Calculate cart subtotal for validation
        var subtotal = 0;
        rchCart.forEach(function(p) {
            var pr = parseFloat(p.price) || 0;
            var d = parseFloat(p.discount_percent) || 0;
            subtotal += pr - (pr * d / 100);
        });

        var channels = [];
        rchCart.forEach(function(p) {
            if (channels.indexOf(p.message_type) === -1) channels.push(p.message_type);
        });

        $('#rchPromoMsg').html('<span style="color:#6366f1; font-size:12px;"><i class="fa fa-spinner fa-spin"></i> Validating...</span>');

        $.ajax({
            url: master_url + 'ccx_msgs/ccx_msgs_public/validate_promo_code',
            type: 'GET',
            dataType: 'json',
            data: {
                code: code,
                client_id: tenant_client_id,
                subtotal: subtotal,
                channels: JSON.stringify(channels)
            },
            success: function(resp) {
                if (resp.success) {
                    rchPromoCode = code.toUpperCase();
                    rchPromoDiscount = parseFloat(resp.discount_amount) || 0;
                    rchPromoId = resp.promo_id;
                    rchRenderCartItems();
                } else {
                    $('#rchPromoMsg').html('<span style="color:#ef4444; font-size:12px;"><i class="fa fa-exclamation-circle" style="margin-right:3px;"></i>' + (resp.message || 'Invalid code') + '</span>');
                }
            },
            error: function() {
                $('#rchPromoMsg').html('<span style="color:#ef4444; font-size:12px;">Failed to validate. Please try again.</span>');
            }
        });
    }

    function rchRemovePromo() {
        rchPromoCode = '';
        rchPromoDiscount = 0;
        rchPromoId = 0;
        rchRenderCartItems();
    }

    // ═══════════════════════════════════════════
    //  CART PERSISTENCE (localStorage)
    // ═══════════════════════════════════════════

    var RCH_CART_KEY = 'rch_cart_' + tenant_client_id;

    /** Save cart to localStorage */
    function rchSaveCart() {
        try {
            localStorage.setItem(RCH_CART_KEY, JSON.stringify(rchCart));
        } catch(e) {}
    }

    /** Load cart from localStorage */
    function rchLoadCart() {
        try {
            var saved = localStorage.getItem(RCH_CART_KEY);
            if (saved) {
                rchCart = JSON.parse(saved);
                if (!Array.isArray(rchCart)) rchCart = [];
            }
        } catch(e) {
            rchCart = [];
        }
        rchUpdateCartBadge();
        if (rchCart.length > 0) {
            $('#rchCartBadgeBtn').css('display', 'flex');
        }
    }

    /** Refresh cart plan objects with fresh API data (prices may have changed) */
    function rchRefreshCartPlans() {
        var refreshed = [];
        rchCart.forEach(function(cartItem) {
            var freshPlan = rchAllPlans.find(function(p) { return p.id == cartItem.id; });
            if (freshPlan) {
                refreshed.push(freshPlan); // use latest data
            }
            // If plan no longer exists/active, drop it from cart silently
        });
        rchCart = refreshed;
        rchSaveCart();
        rchUpdateCartBadge();
        if (rchCart.length > 0) {
            $('#rchCartBadgeBtn').css('display', 'flex');
        } else {
            $('#rchCartBadgeBtn').hide();
        }
    }

    /** Clear cart from localStorage */
    function rchClearCartStorage() {
        rchCart = [];
        try { localStorage.removeItem(RCH_CART_KEY); } catch(e) {}
    }
</script>

<?php init_tail(); ?>
<script>
    // Official WhatsApp → Cloud API link state. `ready` means WhatsApp sends
    // leave over the tenant's own connected number and are billed per 24-hour
    // conversation, so the template modal offers the approved-template mapping.
    var WA_CLOUD = <?= json_encode([
        'ready'     => isset($wa_cloud['status']['ready']) ? (bool) $wa_cloud['status']['ready'] : false,
        'templates' => isset($wa_cloud['templates']) ? $wa_cloud['templates'] : [],
    ]); ?>;

    // What this staff member may do, feature-wise and channel-wise. Mirrors the
    // server-side capability checks — the endpoints re-verify every call, this
    // only keeps the UI from offering actions that would be rejected.
    var CCX_PERMS = <?= json_encode([
        'templates'       => $can_templates,
        'hooks'           => $can_hooks,
        'logs'            => $can_logs,
        'channels'        => array_keys(sms_wa_email_allowed_channels()),
        // Canonical id of the tab that opens first (permission + allocation)
        'default_channel' => sms_wa_email_normalize_channel($first_active_channel),
    ]); ?>;

    function ccxCanChannel(channel) {
        if (channel === 'official_whatsapp') { channel = 'whatsapp'; }
        return CCX_PERMS.channels.indexOf(channel) !== -1;
    }

    jQuery(function ($) {
        // Tooltips
        $('[data-toggle="tooltip"]').tooltip();

        // Initialize recharge modal close handler (defined in pre-jQuery script block)
        if (typeof rchInitModalCloseHandler === 'function') {
            rchInitModalCloseHandler();
        }

        // ═══════════════════════════════════════════
        //  PAYMENT SUCCESS AUTO-DETECTION
        // ═══════════════════════════════════════════
        (function checkPaymentSuccess() {
            var urlParams = new URLSearchParams(window.location.search);
            var paymentSuccess = urlParams.get('payment_success');
            var sessionToken = urlParams.get('session_token');

            if (paymentSuccess !== '1' || !sessionToken || !master_url) return;

            // Clean URL params immediately (prevent re-trigger on refresh)
            var cleanUrl = window.location.href.split('?')[0];
            window.history.replaceState({}, document.title, cleanUrl);

            // Fetch payment receipt from master CRM
            $.ajax({
                url: master_url + 'ccx_msgs/ccx_msgs_public/get_payment_receipt',
                type: 'GET',
                dataType: 'json',
                data: { session_token: sessionToken },
                timeout: 15000,
                success: function(resp) {
                    if (resp.success) {
                        renderPaymentSuccessModal(resp);
                        $('#paymentSuccessModal').modal('show');
                    } else {
                        // Still show a simple success toast
                        if (typeof alert_float === 'function') {
                            alert_float('success', 'Payment completed successfully! Your credits have been activated.');
                        }
                    }
                },
                error: function() {
                    if (typeof alert_float === 'function') {
                        alert_float('success', 'Payment completed successfully! Your credits have been activated.');
                    }
                }
            });
        })();

        function renderPaymentSuccessModal(data) {
            // Channel config for icons/colors
            var channelConfig = {
                sms:          { icon: 'fa fa-comment',   gradient: 'linear-gradient(135deg, #3b82f6, #2563eb)', label: 'SMS' },
                whatsapp:     { icon: 'fab fa-whatsapp',  gradient: 'linear-gradient(135deg, #22c55e, #16a34a)', label: 'WhatsApp' },
                email:        { icon: 'fa fa-envelope',   gradient: 'linear-gradient(135deg, #f59e0b, #d97706)', label: 'Email' },
                aicall:       { icon: 'fa fa-phone',      gradient: 'linear-gradient(135deg, #a855f7, #9333ea)', label: 'AI Call' },
            };

            // Gateway display names
            var gatewayNames = {
                razorpay: 'Razorpay',
                stripe: 'Stripe',
                payu_money: 'PayU Money',
                paypal: 'PayPal',
            };

            // ── Summary Grid ──
            var gatewayLabel = gatewayNames[data.gateway] || (data.gateway ? data.gateway.replace(/_/g, ' ') : 'Online');
            var txnId = data.transaction_id || '—';
            if (txnId.length > 18) txnId = txnId.substring(0, 18) + '…';
            var completedDate = data.completed_at ? new Date(data.completed_at).toLocaleDateString('en-IN', {day:'numeric', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'}) : '—';

            var summaryHtml = '';
            summaryHtml += '<div class="ps-summary-item">' +
                '<div class="ps-summary-item-label">Amount Paid</div>' +
                '<div class="ps-summary-item-value ps-amount-highlight">' + rchFormatMoney(data.amount, '') + '</div>' +
            '</div>';
            summaryHtml += '<div class="ps-summary-item">' +
                '<div class="ps-summary-item-label">Payment Method</div>' +
                '<div class="ps-summary-item-value">' + gatewayLabel + '</div>' +
            '</div>';
            summaryHtml += '<div class="ps-summary-item">' +
                '<div class="ps-summary-item-label">Transaction ID</div>' +
                '<div class="ps-summary-item-value" style="font-size:12px;">' + txnId + '</div>' +
            '</div>';
            summaryHtml += '<div class="ps-summary-item">' +
                '<div class="ps-summary-item-label">Date</div>' +
                '<div class="ps-summary-item-value" style="font-size:12px;">' + completedDate + '</div>' +
            '</div>';
            $('#psSummaryGrid').html(summaryHtml);

            // ── Items List ──
            var itemsHtml = '';
            var subtotal = 0;
            var totalTax = 0;
            var totalSavings = 0;

            (data.plans || []).forEach(function(plan) {
                var ch = channelConfig[plan.message_type] || channelConfig.sms;
                var saving = plan.original_price - plan.price;
                subtotal += plan.price;
                totalTax += plan.tax_amount;
                totalSavings += saving;

                itemsHtml += '<div class="ps-item-card">' +
                    '<div class="ps-item-icon" style="background:' + ch.gradient + '"><i class="' + ch.icon + '"></i></div>' +
                    '<div class="ps-item-info">' +
                        '<div class="ps-item-name">' + plan.plan_name + '</div>' +
                        '<div class="ps-item-meta">' + ch.label + ' · ' + numberWithCommas(plan.message_count) + ' credits · ' + plan.expiry_days + ' days</div>' +
                    '</div>' +
                    '<div class="ps-item-price">' +
                        (saving > 0 ? '<div class="ps-item-price-original">' + rchFormatMoney(plan.original_price, plan.currency_id) + '</div>' : '') +
                        '<div class="ps-item-price-value">' + rchFormatMoney(plan.price, plan.currency_id) + '</div>' +
                    '</div>' +
                '</div>';
            });
            $('#psItemsList').html(itemsHtml);

            // ── Total Section ──
            var totalHtml = '';
            totalHtml += '<div class="ps-total-row"><span>Subtotal</span><span>' + rchFormatMoney(subtotal, '') + '</span></div>';
            if (totalTax > 0) {
                totalHtml += '<div class="ps-total-row"><span>Tax</span><span>+' + rchFormatMoney(totalTax, '') + '</span></div>';
            }
            if (data.promo_discount > 0) {
                totalHtml += '<div class="ps-total-row" style="color:#10b981;"><span><i class="fa fa-tag" style="margin-right:4px;"></i>Promo' + (data.promo_code ? ' (' + data.promo_code + ')' : '') + '</span><span>-' + rchFormatMoney(data.promo_discount, '') + '</span></div>';
            }
            if (totalSavings > 0) {
                totalHtml += '<div class="ps-total-row"><span>You Saved</span><span class="ps-savings-badge"><i class="fa fa-trophy"></i> ' + rchFormatMoney(totalSavings + (data.promo_discount || 0), '') + '</span></div>';
            }
            totalHtml += '<div class="ps-total-row ps-total-grand"><span>Total Paid</span><span>' + rchFormatMoney(data.amount, '') + '</span></div>';
            $('#psTotalSection').html(totalHtml);
        }

        // ── Registered Hooks for Variable Picker ──
        var registeredHooks = <?= $registered_hooks_json; ?>;

        // Populate hook dropdown in template modal
        (function initHookSelect() {
            var $sel = $('#template_hook_select');
            $.each(registeredHooks, function (i, hook) {
                $sel.append('<option value="' + hook.hook_key + '">' + hook.label + ' (' + hook.module + ')</option>');
            });
        })();

        // Show variable tags when a hook is selected
        $('#template_hook_select').on('change', function () {
            var hookKey = $(this).val();
            var $vars = $('#template_hook_variables');
            var $tags = $('#template_hook_var_tags');
            $tags.empty();

            if (!hookKey) {
                $vars.hide();
                return;
            }

            var hook = registeredHooks.find(function (h) { return h.hook_key === hookKey; });
            if (hook && hook.variables && hook.variables.length) {
                $.each(hook.variables, function (vi, v) {
                    $tags.append(
                        '<span class="ccx-chip ccx-var-tag" data-var="' + v + '" ' +
                        'style="cursor:pointer; background:#eef2ff; color:#4338ca; font-size:11px; padding:4px 10px; border-radius:20px; transition:all 0.15s;"' +
                        'onmouseover="this.style.background=\'#c7d2fe\'" onmouseout="this.style.background=\'#eef2ff\'">' +
                        '{' + v + '}</span>'
                    );
                });
                $vars.slideDown(150);
            } else {
                $vars.hide();
            }
        });

        // Insert variable at cursor position in textarea or TinyMCE
        $(document).on('click', '.ccx-var-tag', function () {
            var varText = '{' + $(this).data('var') + '}';
            var editor = (typeof tinymce !== 'undefined') ? tinymce.get('template_content_input') : null;

            if (editor && !editor.isHidden()) {
                // Insert into TinyMCE
                editor.insertContent(varText);
            } else {
                // Insert into plain textarea
                var $textarea = $('#template_content_input');
                var textarea = $textarea[0];
                var start = textarea.selectionStart;
                var end = textarea.selectionEnd;
                var val = $textarea.val();

                $textarea.val(val.substring(0, start) + varText + val.substring(end));
                var newPos = start + varText.length;
                textarea.setSelectionRange(newPos, newPos);
                $textarea.focus();
                $('#charCount').text($textarea.val().length);
            }

            // Brief flash effect
            $(this).css('background', '#a5b4fc');
            var tag = this;
            setTimeout(function () { $(tag).css('background', '#eef2ff'); }, 200);
        });

        // ── Pill Tabs Logic ──
        var hash = window.location.hash;
        if (hash) {
            var $target = $('.ccx-pill[href="' + hash + '"]');
            if ($target.length) {
                $('.ccx-pill').removeClass('active');
                $target.addClass('active');
                $('.tab-pane').removeClass('active');
                $(hash).addClass('active');
            }
        }

        $('.ccx-pill[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            $('.ccx-pill').removeClass('active');
            $(this).addClass('active');

            // Auto-load hooks for the newly active channel since hooks is the default sub-tab
            var channelId = $(this).attr('href').replace('#', '');
            var channelMapR = {
                'sms': 'sms',
                'official_whatsapp': 'whatsapp',
                'email': 'email',
                'ai_call_agent': 'ai_call_agent'
            };
            var channel = channelMapR[channelId] || channelId;
            if (typeof hooksLoaded !== 'undefined' && !hooksLoaded[channel]) {
                loadHooksForChannel(channel);
            }
        });

        // ── Character counter ──
        $('#template_content_input').on('input', function () {
            $('#charCount').text($(this).val().length);
        });

        // ── Clear validation error on input ──
        $('#template_title_input, #template_subtype_input, #template_content_input, #template_msg_template_id, #template_header_id, #template_subject_input, #template_from_name_input, #template_tts_text, #template_voice_note_id, #template_voice_type_id').on('input change', function () {
            $(this).css('border-color', '').css('box-shadow', '');
        });

        // ── TinyMCE Init/Destroy Helpers ──
        function initEmailTinyMCE(content) {
            tinymce.remove('#template_content_input');
            tinymce.init({
                selector: '#template_content_input',
                height: 300,
                menubar: true,
                branding: false,
                promotion: false,
                relative_urls: false,
                remove_script_host: false,
                entity_encoding: 'raw',
                plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
                toolbar: 'undo redo | blocks | bold italic forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | removeformat | code',
                content_style: 'body { font-family: Inter, Arial, sans-serif; font-size: 14px; }',
                setup: function (editor) {
                    editor.on('init', function () {
                        if (content) {
                            editor.setContent(content);
                        }
                    });
                }
            });
        }

        function destroyEmailTinyMCE() {
            if (typeof tinymce !== 'undefined') {
                tinymce.remove('#template_content_input');
            }
        }

        // Cleanup TinyMCE when modal is closed
        $('#templateModal').on('hidden.bs.modal', function () {
            destroyEmailTinyMCE();
        });

        // Show the configured sender address for the selected message subtype
        function refreshSenderEmail() {
            var $input = $('#template_sender_email_input');
            if (!$input.length) return;
            var subtype = $('#template_subtype_input').val() || 'transactional';
            var sender = $input.data('sender-' + subtype) || '';
            $input.val(sender !== '' ? sender : 'Not configured — set a default Email API');
        }

        function toggleEmailFields(type) {
            if (type === 'email') {
                $('#email_only_fields').show();
                $('#charCounterWrap').hide();
                $('#templateModalDialog').addClass('modal-lg');
                refreshSenderEmail();
            } else {
                $('#email_only_fields').hide();
                $('#charCounterWrap').show();
                $('#templateModalDialog').removeClass('modal-lg');
                destroyEmailTinyMCE();
            }
        }

        // ══════════════════════════════════════════════════
        //  OFFICIAL WHATSAPP → CLOUD API TEMPLATE MAPPING
        // ══════════════════════════════════════════════════

        function waCloudFind(name, language) {
            var found = null;
            $.each(WA_CLOUD.templates || [], function (i, t) {
                if (t.name === name && (!language || t.language === language)) {
                    found = t;
                    return false;
                }
            });
            return found;
        }

        function waCloudPopulate(selectedName, selectedLang) {
            var $sel = $('#template_wa_name');
            $sel.empty().append('<option value="">— none (free-form inside open window) —</option>');

            $.each(WA_CLOUD.templates || [], function (i, t) {
                var label = t.name + ' (' + t.language + ')';
                if (t.category) {
                    label += ' · ' + t.category.toLowerCase();
                }
                $sel.append($('<option>').attr('value', t.name).attr('data-language', t.language).text(label));
            });

            // Keep a mapping that is no longer in the synced list visible rather
            // than silently dropping it on the next save.
            if (selectedName && !waCloudFind(selectedName, null)) {
                $sel.append($('<option>').attr('value', selectedName).attr('data-language', selectedLang || '')
                    .text(selectedName + ' (not in synced templates)'));
            }

            $sel.val(selectedName || '');
            $('#template_wa_language').val(selectedLang || '');
            waCloudRenderMeta();
        }

        function waCloudRenderMeta() {
            var name = $('#template_wa_name').val();
            var $meta = $('#template_wa_meta');

            if (!name) {
                $meta.hide().empty();
                return;
            }

            var lang = $('#template_wa_name option:selected').data('language') || '';
            $('#template_wa_language').val(lang);

            var tpl = waCloudFind(name, lang);
            if (!tpl) {
                $meta.html('<i class="fa fa-exclamation-triangle" style="color:#f59e0b; margin-right:6px;"></i>'
                    + 'This template is not in the synced list — sync templates in the WhatsApp module to verify it.').show();
                return;
            }

            var vars = parseInt(tpl.variables_count, 10) || 0;
            var body = $('<div>').text(tpl.body_text || '').html();
            var billing = (String(tpl.category).toUpperCase() === 'MARKETING')
                ? 'Charges one <strong>promotional</strong> credit per 24-hour conversation.'
                : 'Charges one <strong>transactional</strong> credit per 24-hour conversation.';

            // Offer the same variable mapping the auto-map engine would apply,
            // but never overwrite something the user has already typed.
            var suggested = tpl.suggested_params || '';
            var hint = '';
            if (vars > 0) {
                if (suggested && !$.trim($('#template_wa_params').val())) {
                    $('#template_wa_params').val(suggested);
                    hint = '<div style="margin-top:6px; color:#166534;"><i class="fa fa-magic" style="margin-right:4px;"></i>'
                        + 'Variables matched automatically — check them below.</div>';
                } else if (!suggested) {
                    hint = '<div style="margin-top:6px; color:#b45309;"><i class="fa fa-exclamation-triangle" style="margin-right:4px;"></i>'
                        + 'Could not read what {{1}}…{{' + vars + '}} should hold — map them below before using this template in a hook.</div>';
                }
            }
            if (String(tpl.omni_supported) === '0') {
                hint += '<div style="margin-top:6px; color:#b45309;"><i class="fa fa-ban" style="margin-right:4px;"></i>'
                    + 'Authentication templates and media-header templates need a per-send asset a hook cannot supply.</div>';
            }

            $meta.html(
                '<div style="margin-bottom:6px;"><strong>' + vars + '</strong> variable(s) · ' + billing + '</div>'
                + '<div style="color:#374151; white-space:pre-wrap;">' + body + '</div>'
                + hint
            ).show();
        }

        function toggleWaCloudFields(type) {
            var show = !!WA_CLOUD.ready && type === 'whatsapp';
            $('#wa_cloud_fields').toggle(show);
            // DLT / gateway identifiers do not exist on the Cloud API.
            $('#template_msg_template_id').closest('.form-group').toggle(!show);
            $('#template_header_id').closest('.form-group').toggle(!show);
            return show;
        }

        $(document).on('change', '#template_wa_name', waCloudRenderMeta);

        // ── Header ID: conditional input based on Message Type ──
        function applyMsgTemplateIdRules() {
            var subtype = $('#template_subtype_input').val();
            var $hdrInput = $('#template_header_id');
            if (subtype === 'transactional') {
                $hdrInput.attr('placeholder', 'e.g. HLTHPRO (letters only)');
            } else {
                $hdrInput.attr('placeholder', 'e.g. 998877 (numbers only)');
            }
        }

        // Apply rules when Message Type changes
        $('#template_subtype_input').on('change', function () {
            applyMsgTemplateIdRules();
            $('#template_header_id').val('');
            refreshSenderEmail();
        });

        // Filter keystrokes on Header ID
        $('#template_header_id').on('input', function () {
            var subtype = $('#template_subtype_input').val();
            var val = $(this).val();
            if (subtype === 'transactional') {
                $(this).val(val.replace(/[^a-zA-Z_\- ]/g, ''));
            } else {
                $(this).val(val.replace(/[^0-9]/g, ''));
            }
        });

        // ── Dynamic AJAX Templates ──
        var templatesData = { 'sms': [], 'whatsapp': [], 'email': [], 'ai_call_agent': [] };

        function fetchTemplates(type) {
            // Show skeleton while loading
            var skeletonHtml = '';
            for (var s = 0; s < 3; s++) {
                skeletonHtml += '<tr class="ccx-skeleton-row"><td><div class="ccx-skeleton-bar" style="width:80%"></div></td><td><div class="ccx-skeleton-bar" style="width:90%"></div></td><td><div class="ccx-skeleton-bar" style="width:40%; margin:0 auto;"></div></td><td><div class="ccx-skeleton-bar" style="width:40%; margin:0 auto;"></div></td><td><div class="ccx-skeleton-bar" style="width:30%;"></div></td></tr>';
            }
            $('#' + type + '-templates-wrapper').html(skeletonHtml);

            $.get(admin_url + 'sms_wa_email/get_templates/' + type, function (response) {
                templatesData[type] = JSON.parse(response);
                renderTemplates(type);
            });
        }

        // Active search term per channel — applied while rendering so the row's
        // data-index keeps pointing at templatesData[type], which the Edit button
        // relies on.
        var templateSearch = { 'sms': '', 'whatsapp': '', 'email': '', 'ai_call_agent': '' };

        function templateMatchesSearch(type, tpl, plainContent) {
            var q = templateSearch[type];
            if (!q) {
                return true;
            }

            var haystack = [
                tpl.title,
                plainContent,
                tpl.message_subtype,
                tpl.wa_template_name,
                tpl.voice_type,
                tpl.is_default == '1' ? 'default' : '',
                tpl.active == '1' ? 'active' : 'inactive'
            ].join(' ').toLowerCase();

            var tokens = q.split(/\s+/);
            for (var i = 0; i < tokens.length; i++) {
                if (haystack.indexOf(tokens[i]) === -1) {
                    return false;
                }
            }

            return true;
        }

        function renderTemplates(type) {
            var html = '';
            var data = templatesData[type];
            var isAiCall = (type === 'ai_call_agent');
            var shown = 0;

            $.each(data, function (index, tpl) {
                var safeTitle = $('<div>').text(tpl.title).html();

                // Email bodies are rich HTML, so the preview column shows their
                // readable text instead of a column full of markup. Tags are
                // stripped with a regex on purpose — never parsed into a node.
                var previewText = tpl.content || '';
                if (type === 'email') {
                    previewText = previewText
                        .replace(/<(br|\/p|\/div|\/tr)[^>]*>/gi, ' ')
                        .replace(/<[^>]*>/g, '')
                        .replace(/&nbsp;/gi, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();
                }

                if (!templateMatchesSearch(type, tpl, previewText)) {
                    return; // filtered out — index numbering is unaffected
                }
                shown++;

                // Truncate BEFORE escaping, so the cut never lands inside an entity
                var briefContent = $('<div>').text(
                    previewText.length > 60 ? previewText.substring(0, 60) + '...' : previewText
                ).html();
                var subtypeBadge = (tpl.message_subtype === 'promotional')
                    ? '<span class="ccx-chip" style="background:#fef3c7; color:#92400e; font-size:11px;">Promotional</span>'
                    : '<span class="ccx-chip" style="background:#dbeafe; color:#1e40af; font-size:11px;">Transactional</span>';
                var attachmentBadge = tpl.is_attachment == '1'
                    ? '<span class="ccx-chip ccx-chip-attach-yes">Yes</span>'
                    : '<span class="ccx-chip ccx-chip-attach-no">No</span>';
                var defaultBadge = tpl.is_default == '1'
                    ? '<span class="ccx-chip ccx-chip-default">Default</span>'
                    : '';

                // Without the `templates` capability the list stays read-only:
                // the switch is disabled and the row actions are dropped.
                var activeToggle = `
                    <label class="ccx-switch" style="margin: 0 auto;">
                        <input type="checkbox" data-type="${type}" data-id="${tpl.id}" class="template-active-toggle" id="${type}_active_${tpl.id}" ${tpl.active == '1' ? 'checked' : ''} ${CCX_PERMS.templates ? '' : 'disabled'}>
                        <span class="slider"></span>
                    </label>
                `;

                var rowActions = !CCX_PERMS.templates ? '<span class="text-muted" style="font-size:11px;">—</span>' : `
                    <a href="#" class="ccx-action-btn edit-template-btn" data-type="${type}" data-index="${index}" title="Edit">
                        <i class="fa fa-pencil" style="font-size:12px;"></i>
                    </a>
                    ${data.length > 1 ? `
                    <a href="#" class="ccx-action-btn btn-delete remove-template-btn" data-type="${type}" data-id="${tpl.id}" title="Delete">
                        <i class="fa fa-trash" style="font-size:12px;"></i>
                    </a>
                    ` : `<a href="#" class="ccx-action-btn disabled" style="opacity:0.35;pointer-events:none;" title="Cannot remove last template"><i class="fa fa-trash" style="font-size:12px;"></i></a>`}
                `;

                if (isAiCall) {
                    // AI Call Agent: custom columns
                    var voiceTypeBadge = (tpl.voice_type === 'voice_note')
                        ? '<span class="ccx-chip" style="background:#fce7f3; color:#be185d; font-size:11px;"><i class="fa fa-file-audio-o" style="margin-right:3px;"></i>Voice Note</span>'
                        : '<span class="ccx-chip" style="background:#f3e8ff; color:#7c3aed; font-size:11px;"><i class="fa fa-volume-up" style="margin-right:3px;"></i>TTS</span>';
                    var retryInfo = (parseInt(tpl.retry_count) || 0) + 'x / ' + (parseInt(tpl.retry_interval) || 0) + 's';

                    html += `
                        <tr>
                            <td><strong>${safeTitle}</strong></td>
                            <td class="text-center">${voiceTypeBadge}</td>
                            <td class="text-center">${subtypeBadge}</td>
                            <td class="text-center" style="font-size:12px; color:#6b7280;">${retryInfo}</td>
                            <td class="text-center">${activeToggle}</td>
                            <td class="text-center">${defaultBadge}</td>
                            <td>
                                <div style="display:flex; gap:6px;">${rowActions}</div>
                            </td>
                        </tr>
                    `;
                } else {
                    // SMS doesn't show attachment column
                    var attachCell = type === 'sms' ? '' : `<td class="text-center">${attachmentBadge}</td>`;

                    // With the Cloud API live, show whether this template can be
                    // delivered outside the contact's 24-hour window.
                    var waMapCell = '';
                    if (WA_CLOUD.ready && type === 'whatsapp') {
                        if (tpl.wa_template_name) {
                            var chips = `<span class="ccx-chip" style="background:#dcfce7; color:#166534; font-size:10px;"><i class="fab fa-whatsapp" style="margin-right:3px;"></i>${$('<div>').text(tpl.wa_template_name).html()}</span>`;

                            // Mapped by the auto-map engine and untouched since.
                            if (tpl.wa_auto_synced == '1') {
                                chips += ` <span class="ccx-chip" style="background:#e0f2fe; color:#075985; font-size:10px;" title="Mapped automatically from your approved Meta templates. Editing it here makes it yours."><i class="fa fa-magic" style="margin-right:3px;"></i>Auto-mapped</span>`;
                            }

                            // A mapped template with unresolved {{n}} slots cannot be
                            // delivered by a hook until the variables are matched.
                            var approvedTpl = waCloudFind(tpl.wa_template_name, tpl.wa_template_language || '');
                            var varCount = approvedTpl ? (parseInt(approvedTpl.variables_count, 10) || 0) : 0;
                            if (varCount > 0 && !$.trim(tpl.wa_params || '')) {
                                chips += ` <span class="ccx-chip" style="background:#fef3c7; color:#92400e; font-size:10px;" title="Match {{1}}..{{n}} to hook tags in the template's Template Variables field"><i class="fa fa-exclamation-triangle" style="margin-right:3px;"></i>Needs variables</span>`;
                            }

                            waMapCell = `<div style="margin-top:5px;">${chips}</div>`;
                        } else {
                            waMapCell = `<div style="margin-top:5px;"><span class="ccx-chip" style="background:#fef3c7; color:#92400e; font-size:10px;" title="Only deliverable while the customer's 24-hour window is open">Session only</span></div>`;
                        }
                    }

                    html += `
                        <tr>
                            <td><strong>${safeTitle}</strong>${waMapCell}</td>
                            <td style="color:#6b7280;">${briefContent}</td>
                            <td class="text-center">${subtypeBadge}</td>
                            ${attachCell}
                            <td class="text-center">${activeToggle}</td>
                            <td class="text-center">${defaultBadge}</td>
                            <td>
                                <div style="display:flex; gap:6px;">${rowActions}</div>
                            </td>
                        </tr>
                    `;
                }
            });

            if (shown === 0) {
                var colCount = isAiCall ? 7 : (type === 'sms' ? 6 : 7);
                var emptyMsg = templateSearch[type]
                    ? '<i class="fa fa-search" style="font-size:24px; display:block; margin-bottom:8px;"></i>No templates match “' + $('<div>').text(templateSearch[type]).html() + '”.'
                    : '<i class="fa fa-inbox" style="font-size:24px; display:block; margin-bottom:8px;"></i>No templates yet. Add your first template above.';
                html = '<tr><td colspan="' + colCount + '" class="text-center" style="padding:30px !important; color:#9ca3af; font-size:13px;">' + emptyMsg + '</td></tr>';
            }

            $('#' + type + '-templates-wrapper').html(html);

            // Result counter next to the search box
            var $input = $('#' + type + '-templates-search');
            if ($input.length) {
                $('.templates-search-clear[data-type="' + type + '"]').toggle(!!templateSearch[type]);
                var $count = $input.closest('.ccx-templates-section').find('.ccx-templates-count');
                if (!$count.length) {
                    $count = $('<span class="ccx-templates-count"></span>').insertAfter($input.closest('.ccx-search-box'));
                }
                $count.text(templateSearch[type] ? shown + ' of ' + data.length : data.length + (data.length === 1 ? ' template' : ' templates'));
            }
        }

        // ── Templates search ──
        $(document).on('input', '.templates-search-input', function () {
            var type = $(this).data('type');
            templateSearch[type] = $.trim(($(this).val() || '').toLowerCase());
            renderTemplates(type);
        });

        $(document).on('keydown', '.templates-search-input', function (e) {
            if (e.which === 27) { // Esc clears
                var type = $(this).data('type');
                $(this).val('');
                templateSearch[type] = '';
                renderTemplates(type);
            }
        });

        $(document).on('click', '.templates-search-clear', function (e) {
            e.preventDefault();
            var type = $(this).data('type');
            templateSearch[type] = '';
            $('#' + type + '-templates-search').val('').focus();
            renderTemplates(type);
        });

        // Init — only the channels this staff member is allowed to see; the
        // other tab bodies were never rendered.
        $.each(['sms', 'whatsapp', 'email', 'ai_call_agent'], function (i, ch) {
            if (ccxCanChannel(ch)) {
                fetchTemplates(ch);
            }
        });

        // ── AI Call Agent Field Toggling ──
        function toggleAiCallFields(type) {
            if (type === 'ai_call_agent') {
                $('#ai_call_fields').show();
                toggleVoiceTypeFields($('#template_voice_type').val());
            } else {
                $('#ai_call_fields').hide();
            }
        }

        function toggleVoiceTypeFields(voiceType) {
            if (voiceType === 'voice_note') {
                $('#tts_fields').hide();
                $('#voice_note_fields').show();
            } else {
                $('#tts_fields').show();
                $('#voice_note_fields').hide();
            }
        }

        $('#template_voice_type').on('change', function () {
            toggleVoiceTypeFields($(this).val());
        });

        // Voice Note upload area click
        $('#voice_note_upload_area').on('click', function (e) {
            if (e.target.id !== 'template_voice_note_file') {
                $('#template_voice_note_file').trigger('click');
            }
        });
        // Prevent click on file input from bubbling back to upload area
        $('#template_voice_note_file').on('click', function (e) {
            e.stopPropagation();
        });

        // Voice Note file selected
        $('#template_voice_note_file').on('change', function () {
            var file = this.files[0];
            if (!file) return;
            var formData = new FormData();
            formData.append('voice_note_file', file);
            // Include CSRF token
            var csrfName = csrfData.token_name;
            var csrfHash = csrfData.hash;
            formData.append(csrfName, csrfHash);

            $('#voice_note_upload_label').html('<i class="fa fa-spinner fa-spin" style="font-size:22px; color:#a855f7;"></i><br><span style="font-size:12px; color:#9ca3af;">Uploading...</span>');

            $.ajax({
                url: admin_url + 'sms_wa_email/upload_voice_note',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (resp) {
                    var r = JSON.parse(resp);
                    if (r.success) {
                        $('#template_voice_note_file_path').val(r.file_path);
                        $('#voice_note_file_name').text(r.file_name);
                        $('#voice_note_upload_label').hide();
                        $('#voice_note_file_info').show();
                        // Show audio preview
                        var audioUrl = site_url + r.file_path;
                        $('#voice_note_audio_player').attr('src', audioUrl);
                        $('#voice_note_preview').show();
                        alert_float('success', 'Audio file uploaded');
                    } else {
                        resetVoiceNoteUpload();
                        alert_float('danger', r.error || 'Upload failed');
                    }
                },
                error: function () {
                    resetVoiceNoteUpload();
                    alert_float('danger', 'Upload failed');
                }
            });
        });

        // Remove uploaded voice note
        $(document).on('click', '#voice_note_remove_btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            resetVoiceNoteUpload();
        });

        function resetVoiceNoteUpload() {
            $('#template_voice_note_file').val('');
            $('#template_voice_note_file_path').val('');
            $('#voice_note_file_info').hide();
            $('#voice_note_preview').hide();
            $('#voice_note_audio_player').attr('src', '');
            $('#voice_note_upload_label').show();
            $('#voice_note_upload_label').html(
                '<i class="fa fa-cloud-upload" style="font-size:28px; color:#a855f7; margin-bottom:8px; display:block;"></i>' +
                '<span style="font-size:13px; color:#6b7280;">Click to upload audio file</span>' +
                '<br><small style="color:#9ca3af;">WAV format only (max 10MB)</small>'
            );
        }

        function resetAiCallFields() {
            $('#template_voice_type').val('tts');
            $('#template_tts_text').val('');
            $('#template_voice_note_id').val('');
            $('#template_voice_type_id').val('');
            $('#template_retry_count').val('0');
            $('#template_retry_interval').val('0');
            $('#template_language').val('english');
            resetVoiceNoteUpload();
        }

        // ── Add Template ──
        $('.add-template-btn').on('click', function () {
            var type = $(this).data('type');
            var label = type.replace(/_/g, ' ');
            label = label.charAt(0).toUpperCase() + label.slice(1);
            $('#templateModalLabel').text('Add ' + label + ' Template');
            $('#template_id').val('');
            $('#template_type').val(type);
            $('#template_title_input').val('');
            $('#template_subtype_input').val('transactional');
            destroyEmailTinyMCE();
            $('#template_content_input').val('');
            $('#charCount').text('0');
            $('#template_msg_template_id').val('');
            $('#template_header_id').val('');
            $('#template_sample_content').val('');
            $('#template_subject_input').val('');
            $('#template_from_name_input').val('');
            $('#template_attachment_input').prop('checked', false);
            $('#template_active_input').prop('checked', true);
            $('#template_default_input').prop('checked', templatesData[type] && templatesData[type].length === 0);
            $('#template_hook_select').val('').trigger('change');

            if (type === 'sms' || type === 'ai_call_agent') {
                $('#toggle_attachment_wrap').hide();
            } else {
                $('#toggle_attachment_wrap').show();
            }

            // AI Call Agent fields
            resetAiCallFields();
            toggleAiCallFields(type);

            // For AI call agent, hide non-relevant fields
            if (type === 'ai_call_agent') {
                $('#template_content_input').closest('.form-group').hide();
                $('#charCounterWrap').hide();
                $('#template_msg_template_id').closest('.form-group').hide();
                $('#template_header_id').closest('.form-group').hide();
                $('#template_sample_content').closest('.form-group').hide();
                $('#template_hook_select').closest('.form-group').hide();
            } else {
                $('#template_content_input').closest('.form-group').show();
                $('#template_msg_template_id').closest('.form-group').show();
                $('#template_header_id').closest('.form-group').show();
                $('#template_sample_content').closest('.form-group').show();
                $('#template_hook_select').closest('.form-group').show();
            }

            toggleEmailFields(type);
            waCloudPopulate('', '');
            $('#template_wa_params').val('');
            toggleWaCloudFields(type);
            if (type === 'email') {
                initEmailTinyMCE('');
            }
            $('#templateModal').modal('show');
        });

        // ── Edit Template ──
        $(document).on('click', '.edit-template-btn', function (e) {
            e.preventDefault();
            var type = $(this).data('type');
            var index = $(this).data('index');
            var tpl = templatesData[type][index];
            var label = type.replace(/_/g, ' ');
            label = label.charAt(0).toUpperCase() + label.slice(1);

            $('#templateModalLabel').text('Edit ' + label + ' Template');
            $('#template_id').val(tpl.id);
            $('#template_type').val(type);
            $('#template_title_input').val(tpl.title);
            $('#template_subtype_input').val(tpl.message_subtype || 'transactional');
            destroyEmailTinyMCE();
            $('#template_content_input').val(tpl.content);
            $('#charCount').text(tpl.content ? tpl.content.length : 0);
            $('#template_msg_template_id').val(tpl.msg_template_id || '');
            $('#template_header_id').val(tpl.header_id || '');
            $('#template_sample_content').val(tpl.sample_content || '');
            $('#template_subject_input').val(tpl.subject || '');
            $('#template_from_name_input').val(tpl.from_name || '');
            $('#template_attachment_input').prop('checked', tpl.is_attachment == '1');
            $('#template_active_input').prop('checked', tpl.active == '1');
            $('#template_default_input').prop('checked', tpl.is_default == '1');
            $('#template_hook_select').val('').trigger('change');

            if (type === 'sms' || type === 'ai_call_agent') {
                $('#toggle_attachment_wrap').hide();
            } else {
                $('#toggle_attachment_wrap').show();
            }

            // AI Call Agent fields
            if (type === 'ai_call_agent') {
                $('#template_voice_type').val(tpl.voice_type || 'tts');
                $('#template_tts_text').val(tpl.content || '');
                $('#template_voice_note_id').val(tpl.voice_note_id || '');
                $('#template_voice_type_id').val(tpl.voice_type_id || '');
                $('#template_retry_count').val(tpl.retry_count || '0');
                $('#template_retry_interval').val(tpl.retry_interval || '0');
                $('#template_language').val(tpl.language || 'english');
                // Restore uploaded file info
                if (tpl.voice_note_file) {
                    $('#template_voice_note_file_path').val(tpl.voice_note_file);
                    var fname = tpl.voice_note_file.split('/').pop();
                    $('#voice_note_file_name').text(fname);
                    $('#voice_note_upload_label').hide();
                    $('#voice_note_file_info').show();
                    // Show audio preview
                    var audioUrl = site_url + tpl.voice_note_file;
                    $('#voice_note_audio_player').attr('src', audioUrl);
                    $('#voice_note_preview').show();
                } else {
                    resetVoiceNoteUpload();
                }
                toggleAiCallFields(type);
                // Hide non-relevant fields
                $('#template_content_input').closest('.form-group').hide();
                $('#charCounterWrap').hide();
                $('#template_msg_template_id').closest('.form-group').hide();
                $('#template_header_id').closest('.form-group').hide();
                $('#template_sample_content').closest('.form-group').hide();
                $('#template_hook_select').closest('.form-group').hide();
            } else {
                toggleAiCallFields(type);
                $('#template_content_input').closest('.form-group').show();
                $('#template_msg_template_id').closest('.form-group').show();
                $('#template_header_id').closest('.form-group').show();
                $('#template_sample_content').closest('.form-group').show();
                $('#template_hook_select').closest('.form-group').show();
            }

            toggleEmailFields(type);
            waCloudPopulate(tpl.wa_template_name || '', tpl.wa_template_language || '');
            $('#template_wa_params').val(tpl.wa_params || '');
            toggleWaCloudFields(type);
            if (type === 'email') {
                initEmailTinyMCE(tpl.content || '');
            }
            $('#templateModal').modal('show');
        });

        // ── Save Template ──
        $('#save_template_btn').on('click', function () {
            var type = $('#template_type').val();
            var btn = $(this);
            var isEmail = (type === 'email');
            var isAiCall = (type === 'ai_call_agent');

            // Get content from TinyMCE for email, plain textarea for others
            var contentVal;
            var editor = (typeof tinymce !== 'undefined') ? tinymce.get('template_content_input') : null;
            if (isEmail && editor && !editor.isHidden()) {
                contentVal = editor.getContent();
                // Sync to textarea so validation check works
                $('#template_content_input').val(contentVal);
            } else if (isAiCall) {
                // For AI call, content = TTS text (when TTS) or empty (when voice note)
                var voiceType = $('#template_voice_type').val();
                contentVal = (voiceType === 'tts') ? $('#template_tts_text').val() : '';
            } else {
                contentVal = $('#template_content_input').val();
            }

            // Validate mandatory fields
            var valid = true;
            var mandatoryFields;

            if (isAiCall) {
                mandatoryFields = ['#template_title_input', '#template_subtype_input', '#template_voice_type_id', '#template_language'];
                var voiceType = $('#template_voice_type').val();
                if (voiceType === 'tts') {
                    mandatoryFields.push('#template_tts_text');
                }
            } else {
                mandatoryFields = ['#template_title_input', '#template_subtype_input', '#template_content_input'];
                // DLT identifiers only apply to the gateway path — a WhatsApp
                // template delivered over the tenant's own Cloud API number has
                // no Msg Template ID / Header ID.
                if (!(WA_CLOUD.ready && type === 'whatsapp')) {
                    mandatoryFields.push('#template_msg_template_id', '#template_header_id');
                }
                // Add email-specific mandatory fields
                if (isEmail) {
                    mandatoryFields.push('#template_subject_input');
                }
            }

            $.each(mandatoryFields, function (i, sel) {
                var $el = $(sel);
                if ($.trim($el.val()) === '') {
                    $el.css('border-color', '#ef4444').css('box-shadow', '0 0 0 3px rgba(239,68,68,0.15)');
                    $el.addClass('ccx-shake');
                    setTimeout(function () { $el.removeClass('ccx-shake'); }, 500);
                    valid = false;
                } else {
                    $el.css('border-color', '').css('box-shadow', '');
                }
            });
            if (!valid) {
                alert_float('warning', 'Please fill in all mandatory fields');
                return;
            }

            btn.button('loading');

            var postData = {
                id: $('#template_id').val(),
                type: type,
                message_subtype: $('#template_subtype_input').val(),
                title: $('#template_title_input').val(),
                content: contentVal,
                msg_template_id: $('#template_msg_template_id').val(),
                header_id: $('#template_header_id').val(),
                sample_content: $('#template_sample_content').val(),
                is_attachment: $('#template_attachment_input').is(':checked') ? 1 : 0,
                active: $('#template_active_input').is(':checked') ? 1 : 0,
                is_default: $('#template_default_input').is(':checked') ? 1 : 0
            };

            // Include email-only fields
            if (isEmail) {
                postData.subject = $('#template_subject_input').val();
                postData.from_name = $('#template_from_name_input').val();
            }

            // Official WhatsApp → approved Cloud API template mapping
            if (type === 'whatsapp') {
                postData.wa_template_name = $('#template_wa_name').val();
                postData.wa_template_language = $('#template_wa_language').val();
                postData.wa_params = $('#template_wa_params').val();
            }

            // Include AI Call Agent fields
            if (isAiCall) {
                postData.voice_type = $('#template_voice_type').val();
                postData.voice_note_id = $('#template_voice_note_id').val();
                postData.voice_note_file = $('#template_voice_note_file_path').val();
                postData.voice_type_id = $('#template_voice_type_id').val();
                postData.retry_count = $('#template_retry_count').val();
                postData.retry_interval = $('#template_retry_interval').val();
                postData.language = $('#template_language').val();
            }

            $.post(admin_url + 'sms_wa_email/save_template', postData).done(function () {
                fetchTemplates(type);
                destroyEmailTinyMCE();
                $('#templateModal').modal('hide');
                alert_float('success', 'Template saved successfully');
            }).always(function () { btn.button('reset'); });
        });

        /* ══════════════ Import Templates from Excel / CSV ══════════════
           Three steps in one modal: pick a file → review what the parser made
           of it (titles, message type, duplicates) → import the rows kept.
           Nothing is written until "Import Selected" is pressed. */
        var importRows = [];

        function importEsc(s) {
            return $('<div>').text(s == null ? '' : s).html();
        }

        function importReset() {
            importRows = [];
            $('#import_file_input').val('');
            $('#importFileName').hide().text('');
            $('#importUploadError').hide().text('');
            $('#importNotice').hide().text('');
            $('#importPreviewBody').empty();
            $('#importShowSkipped').prop('checked', false);
            $('#importCheckAll').prop('checked', true);
            $('#importStepUpload').show();
            $('#importStepPreview').hide();
            $('#importStepDone').hide();
            $('#importParseBtn').show();
            $('#importCommitBtn').hide();
            $('#importBackBtn').hide();
            $('#importCloseBtn').text('Cancel');
        }

        $(document).on('click', '.import-templates-btn', function () {
            importReset();
            $('#import_type').val($(this).data('type') || 'sms');
            $('#importTemplatesModal').modal('show');
        });

        $('#importDropZone').on('click', function (e) {
            if (e.target.id !== 'import_file_input') {
                $('#import_file_input').trigger('click');
            }
        });

        $('#importDropZone').on('dragover dragenter', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        }).on('dragleave drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        }).on('drop', function (e) {
            var files = e.originalEvent.dataTransfer.files;
            if (files && files.length) {
                $('#import_file_input')[0].files = files;
                $('#import_file_input').trigger('change');
            }
        });

        $('#import_file_input').on('change', function () {
            var f = this.files[0];
            $('#importUploadError').hide();
            if (!f) {
                $('#importFileName').hide();
                return;
            }
            $('#importFileName').show().html('<i class="fa fa-file-o" style="margin-right:5px;"></i>' + importEsc(f.name)
                + ' <span style="color:#94a3b8; font-weight:400;">(' + Math.max(1, Math.round(f.size / 1024)) + ' KB)</span>');
        });

        // Step 1 → 2: send the file up, get the parsed preview back
        $('#importParseBtn').on('click', function () {
            var btn = $(this);
            var file = $('#import_file_input')[0].files[0];
            if (!file) {
                $('#importUploadError').show().text('Please choose a file first.');
                return;
            }

            var fd = new FormData();
            fd.append('import_file', file);
            fd.append('type', $('#import_type').val());
            fd.append(csrfData.token_name, csrfData.hash);

            btn.button('loading');
            $('#importUploadError').hide();

            $.ajax({
                url: admin_url + 'sms_wa_email/import_preview',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done(function (r) {
                if (!r || !r.success) {
                    $('#importUploadError').show().text((r && r.message) ? r.message : 'The file could not be read.');
                    return;
                }
                importRenderPreview(r);
            }).fail(function () {
                $('#importUploadError').show().text('The file could not be uploaded. It may be larger than this server accepts.');
            }).always(function () { btn.button('reset'); });
        });

        function importRenderPreview(r) {
            importRows = r.rows || [];

            $('#impStatNew').text(r.summary.new);
            $('#impStatDup').text(r.summary.duplicate);
            $('#impStatBad').text(r.summary.invalid);
            $('#impStatTotal').text(importRows.length);

            var cols = [];
            $.each(r.columns || {}, function (field, label) {
                var target = field === 'msg_template_id' ? 'Msg Template ID'
                    : (field === 'header_id' ? 'Header ID' : 'Template Content');
                cols.push('<code>' + importEsc(label) + '</code> → ' + target);
            });
            $('#importSourceLine').html('<i class="fa fa-file-o" style="margin-right:5px;"></i>'
                + importEsc(r.file) + ' &nbsp;·&nbsp; header on row ' + r.header_row
                + ' &nbsp;·&nbsp; ' + cols.join(' &nbsp;·&nbsp; '));

            var notices = [];
            if (r.truncated) {
                notices.push('Only the first ' + r.max_rows + ' rows were read. Import this file, then upload the rest.');
            }
            if (!r.columns.header_id) {
                notices.push('No <strong>sender_id</strong> column was found — Header ID will be left empty and every template treated as Transactional.');
            }
            if (r.summary.new === 0) {
                notices.push('Nothing new in this sheet — every usable row is already stored.');
            }
            if (notices.length) {
                $('#importNotice').show().html(notices.join('<br>'));
            } else {
                $('#importNotice').hide();
            }

            var html = '';
            $.each(importRows, function (i, row) {
                var isNew = row.status === 'new';
                var badge = isNew
                    ? '<span class="ccx-imp-badge b-new">New</span>'
                    : (row.status === 'duplicate'
                        ? '<span class="ccx-imp-badge b-dup">Skipped</span>'
                        : '<span class="ccx-imp-badge b-bad">Invalid</span>');

                html += '<tr data-index="' + i + '" class="' + (isNew ? '' : 'row-skip') + '"'
                    + (isNew ? '' : ' style="display:none;"') + '>'
                    + '<td><input type="checkbox" class="imp-row-check" ' + (isNew ? 'checked' : 'disabled') + '></td>'
                    + '<td style="color:#94a3b8;">' + row.row + '</td>'
                    + '<td>' + (isNew
                        ? '<input type="text" class="imp-title-input" maxlength="240" value="' + importEsc(row.title) + '">'
                        : importEsc(row.title)) + '</td>'
                    + '<td style="word-break:break-all;"><code style="font-size:11px;">' + importEsc(row.msg_template_id || '—') + '</code></td>'
                    + '<td>' + importEsc(row.header_id || '—') + '</td>'
                    + '<td>' + (isNew
                        ? '<select class="imp-subtype">'
                        + '<option value="transactional"' + (row.message_subtype === 'transactional' ? ' selected' : '') + '>Transactional</option>'
                        + '<option value="promotional"' + (row.message_subtype === 'promotional' ? ' selected' : '') + '>Promotional</option>'
                        + '</select>'
                        : '<span class="ccx-imp-badge ' + (row.message_subtype === 'promotional' ? 'b-promo">Promotional' : 'b-trans">Transactional') + '</span>') + '</td>'
                    + '<td><div class="imp-content">' + importEsc(row.content) + '</div></td>'
                    + '<td>' + badge + (row.reason ? '<span class="ccx-imp-reason">' + importEsc(row.reason) + '</span>' : '') + '</td>'
                    + '</tr>';
            });

            if (!html) {
                html = '<tr><td colspan="8" style="text-align:center; color:#94a3b8; padding:26px;">No template rows were found in this sheet.</td></tr>';
            }

            $('#importPreviewBody').html(html);
            $('#importCheckAll').prop('checked', r.summary.new > 0);
            $('#importStepUpload').hide();
            $('#importStepPreview').show();
            $('#importParseBtn').hide();
            $('#importBackBtn').show();
            $('#importCommitBtn').toggle(r.summary.new > 0);
            importUpdateCommitLabel();
        }

        function importUpdateCommitLabel() {
            var n = $('#importPreviewBody .imp-row-check:checked').length;
            // Drop Bootstrap's cached label, otherwise button('reset') would put
            // an out-of-date count back after a failed import.
            $('#importCommitBtn').removeData('resetText')
                .html('<i class="fa fa-download" style="margin-right:5px;"></i> Import '
                    + n + ' Template' + (n === 1 ? '' : 's')).prop('disabled', n === 0);
        }

        $('#importShowSkipped').on('change', function () {
            $('#importPreviewBody tr.row-skip').toggle($(this).is(':checked'));
        });

        $('#importCheckAll').on('change', function () {
            $('#importPreviewBody .imp-row-check:not(:disabled)').prop('checked', $(this).is(':checked'));
            importUpdateCommitLabel();
        });

        $(document).on('change', '.imp-row-check', importUpdateCommitLabel);

        $('#importBackBtn').on('click', function () {
            importReset();
        });

        // Step 2 → 3: write the rows still checked
        $('#importCommitBtn').on('click', function () {
            var btn = $(this);
            var payload = [];

            $('#importPreviewBody tr').each(function () {
                var $tr = $(this);
                if (!$tr.find('.imp-row-check').is(':checked')) {
                    return;
                }
                var row = importRows[$tr.data('index')];
                if (!row) {
                    return;
                }
                payload.push({
                    row: row.row,
                    title: $.trim($tr.find('.imp-title-input').val() || row.title),
                    msg_template_id: row.msg_template_id,
                    header_id: row.header_id,
                    content: row.content,
                    message_subtype: $tr.find('.imp-subtype').val() || row.message_subtype
                });
            });

            if (!payload.length) {
                alert_float('warning', 'Select at least one template to import');
                return;
            }

            btn.button('loading');

            $.post(admin_url + 'sms_wa_email/import_commit', {
                type: $('#import_type').val(),
                rows: JSON.stringify(payload)
            }, null, 'json').done(function (r) {
                if (!r || !r.success) {
                    alert_float('danger', (r && r.message) ? r.message : 'The import could not be completed');
                    return;
                }

                var bits = [];
                if (r.skipped) { bits.push(r.skipped + ' duplicate' + (r.skipped === 1 ? '' : 's') + ' skipped'); }
                if (r.failed) { bits.push(r.failed + ' row' + (r.failed === 1 ? '' : 's') + ' rejected'); }

                $('#importDoneHeadline').text(r.imported + ' template' + (r.imported === 1 ? '' : 's') + ' imported');
                $('#importDoneDetail').text(bits.length ? bits.join(' · ') : 'Every selected row was imported successfully.');
                $('#importStepPreview').hide();
                $('#importStepDone').show();
                $('#importCommitBtn').hide();
                $('#importBackBtn').hide();
                $('#importCloseBtn').text('Done');

                fetchTemplates($('#import_type').val());
                alert_float('success', r.imported + ' template(s) imported');
            }).fail(function () {
                alert_float('danger', 'The import request failed');
            }).always(function () { btn.button('reset'); });
        });

        // ── Toggle Active ──
        $(document).on('change', '.template-active-toggle', function () {
            var id = $(this).data('id');
            var type = $(this).data('type');
            var status = $(this).is(':checked') ? 1 : 0;
            $.post(admin_url + 'sms_wa_email/toggle_active/' + id, { status: status }).done(function () {
                fetchTemplates(type);
                alert_float('success', 'Status updated');
            });
        });

        // ── Delete Template ──
        $(document).on('click', '.remove-template-btn', function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to remove this template?')) {
                var id = $(this).data('id');
                var type = $(this).data('type');
                $.post(admin_url + 'sms_wa_email/delete_template/' + id).done(function () {
                    fetchTemplates(type);
                    alert_float('success', 'Template removed');
                });
            }
        });

        // ── Load Hook Templates (Email) ──
        // Seeds one branded email template per registered hook. Existing
        // templates and existing hook mappings are never touched, and the
        // mappings it creates are switched OFF — the admin turns on the hooks
        // they actually want live.
        $(document).on('click', '.seed-hook-templates-btn', function (e) {
            e.preventDefault();

            if (!confirm('Create a ready-made email template for every system hook that does not have one yet?\n\nNothing existing is changed, and every new hook mapping stays switched OFF until you turn it on.')) {
                return;
            }

            var btn = $(this).button('loading');

            $.post(admin_url + 'sms_wa_email/seed_email_templates', { seed: 1 }, null, 'json')
                .done(function (res) {
                    if (!res || !res.success) {
                        alert_float('danger', (res && res.message) ? res.message : 'Could not load the hook templates.');
                        return;
                    }

                    var parts = [];
                    if (res.created) { parts.push(res.created + ' template' + (res.created > 1 ? 's' : '') + ' created'); }
                    if (res.imported) { parts.push(res.imported + ' reused from Setup → Email Templates'); }
                    if (res.reused) { parts.push(res.reused + ' hook' + (res.reused > 1 ? 's' : '') + ' already had one'); }
                    if (res.mapped) { parts.push(res.mapped + ' mapped (inactive)'); }

                    alert_float('success', parts.length ? parts.join(', ') + '.' : 'Every hook already has an email template.');

                    if (res.pack_warnings && res.pack_warnings.length) {
                        alert_float('warning', 'Seed pack needs attention: ' + res.pack_warnings.join(' | '));
                    }

                    fetchTemplates('email');
                    if (CCX_PERMS.hooks && ccxCanChannel('email')) {
                        loadHooksForChannel('email');
                    }
                })
                .fail(function () {
                    alert_float('danger', 'Could not load the hook templates.');
                })
                .always(function () { btn.button('reset'); });
        });

        // ── Sub-Tab (Inner) Switching ──
        $(document).on('click', '.ccx-sub-pill', function (e) {
            e.preventDefault();
            var $this = $(this);
            var target = $this.data('sub-target');
            var $bar = $this.closest('.ccx-sub-pill-bar');
            var $container = $bar.closest('.tab-pane');

            // Update active pill
            $bar.find('.ccx-sub-pill').removeClass('active');
            $this.addClass('active');

            // Show/hide sub-panels
            $container.find('.ccx-sub-panel').hide();
            $container.find('.ccx-sub-panel[data-sub-panel="' + target + '"]').fadeIn(200);

            // Auto-load hooks when switching to hooks panel
            if (target === 'hooks') {
                var channelId = $container.attr('id');
                var channel = channelMapReverse[channelId] || channelId;
                if (!hooksLoaded[channel]) {
                    loadHooksForChannel(channel);
                }
            }
        });

        // ══════════════════════════════════════════════════
        //  CRM EMAIL ROUTING
        // ══════════════════════════════════════════════════

        $(document).on('submit', '#crm-routing-form', function (e) {
            e.preventDefault();

            var $btn = $('#crm-routing-save');
            var original = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving…');

            // Every switch is posted on every save — an unchecked box has no
            // value of its own, so it is sent explicitly as '0'.
            var payload = {};
            $('#crm-routing-form .crm-routing-switch').each(function () {
                payload[this.name] = this.checked ? '1' : '0';
            });
            payload[csrfData.token_name] = csrfData.hash;

            $.post(admin_url + 'sms_wa_email/save_crm_email_settings', payload, function (resp) {
                var r = typeof resp === 'string' ? JSON.parse(resp) : resp;
                if (r.success) {
                    alert_float('success', r.message || 'Saved');

                    // Keep the ON/OFF chip on the sub-tab honest
                    var on = $('#sms_wa_email_route_crm_email').is(':checked');
                    var $chip = $('.ccx-sub-pill[data-sub-target="crm_routing"] .ccx-chip');
                    $chip.text(on ? 'ON' : 'OFF')
                        .css(on
                            ? { background: '#dcfce7', color: '#166534' }
                            : { background: '#fef2f2', color: '#991b1b' });
                } else {
                    alert_float('danger', r.message || 'Could not save');
                }
            }).fail(function () {
                alert_float('danger', 'Could not save the routing settings');
            }).always(function () {
                $btn.prop('disabled', false).html(original);
            });
        });

        // ══════════════════════════════════════════════════
        //  CHANNEL SENDING SWITCH (superadmin, on the balance cards)
        // ══════════════════════════════════════════════════

        $(document).on('change', '.channel-send-switch', function () {
            var $sw = $(this);
            var on = $sw.is(':checked');

            var payload = {
                channel: $sw.data('channel'),
                subtype: $sw.data('subtype'),
                status: on ? '1' : '0'
            };
            payload[csrfData.token_name] = csrfData.hash;

            $sw.prop('disabled', true);

            $.post(admin_url + 'sms_wa_email/toggle_channel_send', payload, function (resp) {
                var r = typeof resp === 'string' ? JSON.parse(resp) : resp;

                if (r && r.success) {
                    paintChannelSwitch($sw, r.enabled);
                    alert_float('success', r.message);
                } else {
                    // Server refused — put the switch back where it was
                    $sw.prop('checked', !on);
                    alert_float('danger', (r && r.message) || 'Could not change the channel state');
                }
            }).fail(function () {
                $sw.prop('checked', !on);
                alert_float('danger', 'Could not change the channel state');
            }).always(function () {
                $sw.prop('disabled', false);
            });
        });

        function paintChannelSwitch($sw, enabled) {
            var $row = $sw.closest('.bc-send-switch');
            $row.toggleClass('is-off', !enabled);
            $row.find('.bc-send-switch-text').text(enabled ? 'Sending ON' : 'Sending OFF');
        }

        // ══════════════════════════════════════════════════
        //  OFFICIAL WHATSAPP — 24-HOUR CONVERSATION LEDGER
        // ══════════════════════════════════════════════════

        $(document).on('click', '#waConvRefresh', function (e) {
            e.preventDefault();
            var $btn = $(this).prop('disabled', true);

            $.getJSON(admin_url + 'sms_wa_email/wa_conversations', { days: 30 })
                .done(function (resp) {
                    if (!resp || !resp.stats) {
                        return;
                    }

                    $.each(resp.stats, function (key, value) {
                        var $cell = $('#waConvStats [data-stat="' + key + '"]');
                        if ($cell.length && typeof value === 'number') {
                            $cell.text(value.toLocaleString());
                        }
                    });

                    var rows = '';
                    $.each(resp.recent || [], function (i, c) {
                        var open = new Date(c.expires_at.replace(' ', 'T')) > new Date();
                        var free = parseInt(c.credits_charged, 10) === 0;
                        rows += '<tr>'
                            + '<td><strong>' + $('<div>').text(c.phone).html() + '</strong></td>'
                            + '<td><span class="ccx-chip" style="background:#dbeafe; color:#1e40af; font-size:11px;">'
                            + $('<div>').text(c.category).html() + '</span></td>'
                            + '<td class="text-center">' + c.messages_count + '</td>'
                            + '<td class="text-center">' + (free
                                ? '<span class="ccx-chip" style="background:#dcfce7; color:#166534; font-size:11px;">Free</span>'
                                : '<span class="ccx-chip" style="background:#fee2e2; color:#991b1b; font-size:11px;">−' + c.credits_charged + '</span>')
                            + '</td>'
                            + '<td style="color:#6b7280; font-size:12px;">' + $('<div>').text(c.opened_at).html() + '</td>'
                            + '<td style="font-size:12px;">' + (open
                                ? '<span style="color:#16a34a;"><i class="fa fa-circle" style="font-size:8px;"></i> Open</span>'
                                : '<span style="color:#9ca3af;">Closed</span>')
                            + '</td></tr>';
                    });

                    if (rows === '') {
                        rows = '<tr><td colspan="6" class="text-center" style="padding:30px !important; color:#9ca3af; font-size:13px;">No WhatsApp conversations yet.</td></tr>';
                    }
                    $('#waConvRows').html(rows);
                })
                .always(function () {
                    $btn.prop('disabled', false);
                });
        });

        // ── Sync approved Meta templates onto the Official WhatsApp channel ──
        // Pulls the library from Meta, re-runs the auto-map, then refreshes the
        // templates table and the hooks picker that feeds off it.
        $(document).on('click', '#waTemplateSyncBtn', function (e) {
            e.preventDefault();
            var $btn = $(this).prop('disabled', true);
            var original = $btn.html();
            $btn.html('<i class="fa fa-spinner fa-spin"></i> Syncing…');

            $.post(admin_url + 'sms_wa_email/wa_sync_templates', { sync: 1 }, null, 'json')
                .done(function (resp) {
                    if (!resp || !resp.success) {
                        alert_float('warning', (resp && resp.message) ? resp.message : 'Could not sync templates');
                        return;
                    }

                    WA_CLOUD.templates = resp.templates || [];

                    var r = resp.result || {};
                    var parts = [];
                    if (r.imported) { parts.push(r.imported + ' imported'); }
                    if (r.linked) { parts.push(r.linked + ' mapped'); }
                    if (r.updated) { parts.push(r.updated + ' updated'); }
                    if (r.retired) { parts.push(r.retired + ' deactivated'); }

                    alert_float('success', parts.length
                        ? 'Templates synced — ' + parts.join(', ') + '.'
                        : 'Already in step with your approved templates.');
                    if (resp.pull_error) {
                        alert_float('warning', 'Meta: ' + resp.pull_error + ' — mapped the last synced library instead.');
                    }

                    var note = '<br><span style="color:#6b7280; font-size:11.5px;">'
                        + (r.approved || 0) + ' approved template(s) checked just now'
                        + (resp.needs_params ? ' · <span style="color:#b45309;">' + resp.needs_params + ' still need variables</span>' : '')
                        + '.</span>';
                    $('#waAutoMapDynamic').html(note).show();

                    fetchTemplates('whatsapp');
                    hooksLoaded['whatsapp'] = false;
                    loadHooksForChannel('whatsapp');
                })
                .fail(function () {
                    alert_float('danger', 'Could not reach the server to sync templates');
                })
                .always(function () {
                    $btn.prop('disabled', false).html(original);
                });
        });

        // ══════════════════════════════════════════════════
        //  HOOKS SYSTEM
        // ══════════════════════════════════════════════════

        // Map tab panel IDs to channel keys used by the API
        var channelMapReverse = {
            'sms': 'sms',
            'official_whatsapp': 'whatsapp',
            'email': 'email',
            'ai_call_agent': 'ai_call_agent'
        };

        var hooksLoaded = {};
        var hooksData = {};
        var channelTemplates = {};
        var staffListCache = null;
        var rolesListCache = null;

        // Auto-load hooks for the first visible channel on page init. Skipped
        // entirely without the `hooks` capability — the panel isn't rendered.
        if (CCX_PERMS.hooks && CCX_PERMS.default_channel) {
            loadHooksForChannel(CCX_PERMS.default_channel);
        }

        function loadHooksForChannel(channel) {
            if (!CCX_PERMS.hooks || !ccxCanChannel(channel)) {
                return;
            }

            var $wrapper = $('#' + channel + '-hooks-wrapper');
            $wrapper.html('<div class="ccx-hooks-state"><i class="fa fa-spinner fa-spin" style="margin-right:6px;"></i> Loading hooks…</div>');

            // Load hooks, templates, staff, and roles in parallel
            var requests = [
                $.get(admin_url + 'sms_wa_email/get_hooks/' + channel),
                $.get(admin_url + 'sms_wa_email/get_templates/' + channel)
            ];
            // Fetch staff & roles once (cache)
            if (!staffListCache) {
                requests.push($.get(admin_url + 'sms_wa_email/get_staff_list'));
                requests.push($.get(admin_url + 'sms_wa_email/get_roles_list'));
            }

            $.when.apply($, requests).done(function () {
                var args = arguments;
                // When only 2 requests, $.when wraps differently
                var hooksResp, templatesResp, staffResp, rolesResp;
                if (requests.length === 4) {
                    hooksResp = args[0]; templatesResp = args[1]; staffResp = args[2]; rolesResp = args[3];
                } else {
                    hooksResp = args[0]; templatesResp = args[1];
                }

                var hooks = JSON.parse(typeof hooksResp === 'object' && hooksResp[0] ? hooksResp[0] : hooksResp);
                var templates = JSON.parse(typeof templatesResp === 'object' && templatesResp[0] ? templatesResp[0] : templatesResp);
                hooksData[channel] = hooks;
                channelTemplates[channel] = templates;
                hooksLoaded[channel] = true;

                if (staffResp) {
                    staffListCache = JSON.parse(typeof staffResp === 'object' && staffResp[0] ? staffResp[0] : staffResp);
                }
                if (rolesResp) {
                    rolesListCache = JSON.parse(typeof rolesResp === 'object' && rolesResp[0] ? rolesResp[0] : rolesResp);
                }

                renderHooksTable(channel, hooks, templates);
            }).fail(function () {
                $wrapper.html('<div class="ccx-hooks-state" style="color:#ef4444; border-color:#fecaca;"><i class="fa fa-exclamation-triangle" style="margin-right:6px;"></i> Failed to load hooks</div>');
            });
        }

        // Helper: build recipient badge HTML
        function recipientBadge(type, value) {
            if (type === 'staff' && staffListCache) {
                var staff = staffListCache.find(function (s) { return s.staffid == value; });
                var staffName = staff ? staff.name : 'Staff #' + value;
                return '<span class="ccx-chip" style="background:#fef3c7; color:#92400e; font-size:10px;"><i class="fa fa-user" style="margin-right:3px;"></i>' + $('<div>').text(staffName).html() + '</span>';
            } else if (type === 'role' && rolesListCache) {
                var role = rolesListCache.find(function (r) { return r.roleid == value; });
                var roleName = role ? role.name : 'Role #' + value;
                return '<span class="ccx-chip" style="background:#e0e7ff; color:#3730a3; font-size:10px;"><i class="fa fa-users" style="margin-right:3px;"></i>' + $('<div>').text(roleName).html() + '</span>';
            } else if (type === 'custom') {
                return '<span class="ccx-chip" style="background:#fce7f3; color:#9d174d; font-size:10px;"><i class="fa fa-pencil" style="margin-right:3px;"></i>' + $('<div>').text(value || 'Custom').html() + '</span>';
            }
            return '<span class="ccx-chip" style="background:#dcfce7; color:#166534; font-size:10px;"><i class="fa fa-heartbeat" style="margin-right:3px;"></i>Patient</span>';
        }

        // ── Per-module presentation for the hooks accordion ──
        // Unknown slugs fall back to a prettified label + neutral plug icon, so
        // a new ccx_hooks/{module}_hooks.php file needs no change here.
        var HOOK_MODULE_META = {
            patients: { label: 'Patients', icon: 'fa-heartbeat', color: '#dc2626', bg: '#fef2f2' },
            invoices: { label: 'Invoices', icon: 'fa-file-text-o', color: '#d97706', bg: '#fffbeb' },
            refunds: { label: 'Refunds', icon: 'fa-undo', color: '#7c3aed', bg: '#f5f3ff' },
            antenatal: { label: 'Antenatal', icon: 'fa-child', color: '#db2777', bg: '#fdf2f8' },
            pro_leads: { label: 'Pro Leads', icon: 'fa-bullseye', color: '#0284c7', bg: '#f0f9ff' },
            pro_tickets: { label: 'Pro Tickets', icon: 'fa-life-ring', color: '#0d9488', bg: '#f0fdfa' },
            self_kiosk: { label: 'Self Kiosk', icon: 'fa-tablet', color: '#4f46e5', bg: '#eef2ff' }
        };

        // Which module groups the user has expanded, per channel — survives the
        // re-render that follows every toggle / add / delete.
        var hooksOpenState = {};

        function hookModuleMeta(slug) {
            if (HOOK_MODULE_META[slug]) {
                return HOOK_MODULE_META[slug];
            }
            var label = (slug || 'other').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
            return { label: label, icon: 'fa-plug', color: '#6b7280', bg: '#f3f4f6' };
        }

        function hooksEsc(str) {
            return $('<div>').text(str == null ? '' : str).html();
        }

        // Lowercased haystack for the search box, safe to sit in an attribute.
        function hooksSearchAttr(str) {
            return String(str == null ? '' : str).toLowerCase().replace(/["<>]/g, ' ');
        }

        function renderHooksTable(channel, hooks, templates) {
            var $wrapper = $('#' + channel + '-hooks-wrapper');

            if (!hooks || hooks.length === 0) {
                $wrapper.html('<div class="ccx-hooks-state"><i class="fa fa-plug" style="font-size:24px; display:block; margin-bottom:8px;"></i>No system hooks registered yet.</div>');
                return;
            }

            // Group hooks by owning module
            var groups = {}, order = [];
            $.each(hooks, function (i, hook) {
                var mod = hook.module || 'other';
                if (!groups[mod]) {
                    groups[mod] = [];
                    order.push(mod);
                }
                groups[mod].push(hook);
            });
            order.sort(function (a, b) {
                return hookModuleMeta(a).label.localeCompare(hookModuleMeta(b).label);
            });

            if (!hooksOpenState[channel]) {
                hooksOpenState[channel] = {};
            }
            var openState = hooksOpenState[channel];

            var thead = '<thead><tr>' +
                '<th width="24%">Hook</th>' +
                '<th width="26%">Description</th>' +
                '<th width="16%">Recipient</th>' +
                '<th width="18%">Template</th>' +
                '<th class="text-center" width="8%">Active</th>' +
                '<th class="text-center" width="8%"></th>' +
                '</tr></thead>';

            var html = '';
            $.each(order, function (_, mod) {
                var list = groups[mod];
                var meta = hookModuleMeta(mod);
                var configured = 0, recipients = 0;
                $.each(list, function (_, h) {
                    var n = (h.mappings || []).length;
                    if (n) {
                        configured++;
                        recipients += n;
                    }
                });

                var isOpen = !!openState[mod];
                var countChip = '<span class="ccx-chip" style="background:#eef2ff; color:#3730a3;">' + list.length + ' hook' + (list.length === 1 ? '' : 's') + '</span>';
                var stateChip = configured
                    ? '<span class="ccx-chip" style="background:#dcfce7; color:#166534;"><i class="fa fa-check" style="margin-right:4px;"></i>' + configured + ' of ' + list.length + ' configured</span>'
                    : '<span class="ccx-chip" style="background:#f3f4f6; color:#9ca3af;">Not configured</span>';
                var recipientChip = recipients
                    ? '<span class="ccx-chip" style="background:#e0f2fe; color:#075985;">' + recipients + ' recipient' + (recipients === 1 ? '' : 's') + '</span>'
                    : '';

                var body = '';
                $.each(list, function (_, hook) {
                    body += hookRowsHtml(channel, hook, templates, meta);
                });

                html += '<div class="ccx-acc' + (isOpen ? ' open' : '') + '" data-channel="' + channel + '" data-module="' + hooksEsc(mod) + '">' +
                    '<div class="ccx-acc-head">' +
                    '<span class="ccx-acc-caret"><i class="fa fa-chevron-right"></i></span>' +
                    '<span class="ccx-acc-icon" style="background:' + meta.bg + '; color:' + meta.color + ';"><i class="fa ' + meta.icon + '"></i></span>' +
                    '<span><span class="ccx-acc-title">' + hooksEsc(meta.label) + '</span><span class="ccx-acc-slug">' + hooksEsc(mod) + '</span></span>' +
                    '<span class="ccx-acc-meta">' + countChip + stateChip + recipientChip + '</span>' +
                    '</div>' +
                    '<div class="ccx-acc-body"><div class="ccx-table-wrap"><table class="table">' + thead + body + '</table></div></div>' +
                    '</div>';
            });

            $wrapper.html(html);
            syncHooksToggleAllBtn(channel);
            applyHooksSearch(channel); // keep an active filter after a re-render
        }

        /**
         * All rows for one hook, wrapped in their own <tbody> so the search can
         * show/hide a hook without breaking the rowspan of multi-recipient rows.
         */
        function hookRowsHtml(channel, hook, templates, meta) {
            var mappings = hook.mappings || [];
            var searchText = hooksSearchAttr([hook.label, hook.hook_key, hook.description, hook.module, meta.label].join(' '));
            var html = '<tbody class="ccx-hook-rows" data-search="' + searchText + '">';

            if (mappings.length === 0) {
                // Show a single row with "No recipients" hint + add button
                html += '<tr>' +
                    '<td><strong style="font-size:13px;">' + hook.label + '</strong><br><code style="font-size:10px; color:#9ca3af;">' + hook.hook_key + '</code></td>' +
                    '<td style="font-size:12.5px; color:#6b7280;">' + hook.description + '</td>' +
                    '<td colspan="3" style="font-size:12px; color:#9ca3af; text-align:center;"><i class="fa fa-info-circle" style="margin-right:4px;"></i>No recipients configured</td>' +
                    '<td class="text-center"><a href="#" class="ccx-action-btn add-hook-recipient-btn" data-channel="' + channel + '" data-hook="' + hook.hook_key + '" title="Add recipient" style="font-size:13px; color:#10b981;"><i class="fa fa-plus-circle"></i></a></td>' +
                    '</tr>';
            } else {
                // One row per mapping
                $.each(mappings, function (mi, m) {
                    var isFirstRow = (mi === 0);
                    var rowspan = isFirstRow ? ' rowspan="' + mappings.length + '"' : '';

                    // Recipient badge
                    var recipient = recipientBadge(m.recipient_type, m.recipient_value);

                    // Template name
                    var tplName = '—';
                    var tplActive = null;
                    if (m.template_id) {
                        var tpl = templates.find(function (t) { return t.id == m.template_id; });
                        if (tpl) {
                            tplName = '<span style="font-size:12px;">' + $('<div>').text(tpl.title).html() + '</span>';
                            tplActive = parseInt(tpl.active);
                        }
                    }

                    // Template status indicator
                    var tplStatus = '';
                    if (tplActive === 1) {
                        tplStatus = '<div style="margin-top:3px; font-size:10px; font-weight:600; color:#16a34a;"><i class="fa fa-check-circle" style="margin-right:2px;"></i>Active</div>';
                    } else if (tplActive === 0) {
                        tplStatus = '<div style="margin-top:3px; font-size:10px; font-weight:600; color:#ef4444;"><i class="fa fa-times-circle" style="margin-right:2px;"></i>Inactive</div>';
                    }

                    // Active toggle
                    var activeToggle = '<label class="ccx-switch" style="margin:0 auto;">' +
                        '<input type="checkbox" class="hook-active-toggle" data-mapping-id="' + m.mapping_id + '" data-channel="' + channel + '" ' + (m.active == 1 ? 'checked' : '') + (CCX_PERMS.hooks ? '' : ' disabled') + '>' +
                        '<span class="slider"></span>' +
                        '</label>';

                    // Delete button
                    var deleteBtn = '<a href="#" class="ccx-action-btn btn-delete delete-hook-mapping-btn" data-id="' + m.mapping_id + '" data-channel="' + channel + '" title="Remove this recipient mapping" style="font-size:12px;"><i class="fa fa-trash"></i></a>';

                    // Edit button
                    var editBtn = '<a href="#" class="ccx-action-btn edit-hook-mapping-btn" ' +
                        'data-mapping-id="' + m.mapping_id + '" data-channel="' + channel + '" ' +
                        'data-hook="' + hook.hook_key + '" data-recipient-type="' + m.recipient_type + '" ' +
                        'data-recipient-value="' + (m.recipient_value || '') + '" data-template-id="' + (m.template_id || '') + '" ' +
                        'data-active="' + m.active + '" title="Edit" style="font-size:12px; margin-right:4px;"><i class="fa fa-pencil"></i></a>';

                    // Add button (only on last mapping row)
                    var addBtn = '';
                    if (mi === mappings.length - 1) {
                        addBtn = '<a href="#" class="ccx-action-btn add-hook-recipient-btn" data-channel="' + channel + '" data-hook="' + hook.hook_key + '" title="Add another recipient" style="font-size:12px; color:#10b981; margin-right:4px;"><i class="fa fa-plus"></i></a>';
                    }

                    html += '<tr' + (mi > 0 ? ' style="border-top:1px dashed #e5e7eb;"' : '') + '>';
                    if (isFirstRow) {
                        html += '<td' + rowspan + '><strong style="font-size:13px;">' + hook.label + '</strong><br><code style="font-size:10px; color:#9ca3af;">' + hook.hook_key + '</code></td>';
                        html += '<td' + rowspan + ' style="font-size:12.5px; color:#6b7280;">' + hook.description + '</td>';
                    }
                    html += '<td>' + recipient + '</td>';
                    html += '<td>' + tplName + tplStatus + '</td>';
                    html += '<td class="text-center">' + activeToggle + '</td>';
                    html += '<td class="text-center"><div style="display:flex; gap:4px; justify-content:center;">' + addBtn + editBtn + deleteBtn + '</div></td>';
                    html += '</tr>';
                });
            }

            return html + '</tbody>';
        }

        // ══════════════════════════════════════════════════
        //  HOOKS ACCORDION — expand / collapse / search
        // ══════════════════════════════════════════════════

        $(document).on('click', '.ccx-acc-head', function () {
            var $acc = $(this).closest('.ccx-acc');
            var channel = $acc.data('channel');
            var mod = String($acc.data('module'));
            var nowOpen = !$acc.hasClass('open');

            $acc.toggleClass('open', nowOpen);
            if (!hooksOpenState[channel]) {
                hooksOpenState[channel] = {};
            }
            hooksOpenState[channel][mod] = nowOpen;
            syncHooksToggleAllBtn(channel);
        });

        $(document).on('click', '.ccx-hooks-toggle-all', function () {
            var channel = $(this).data('channel');
            var expand = $(this).attr('data-state') !== 'expanded';

            if (!hooksOpenState[channel]) {
                hooksOpenState[channel] = {};
            }
            $('#' + channel + '-hooks-wrapper').find('.ccx-acc').each(function () {
                $(this).toggleClass('open', expand);
                hooksOpenState[channel][String($(this).data('module'))] = expand;
            });
            syncHooksToggleAllBtn(channel);
        });

        // Button label follows what is actually on screen (a search auto-expands
        // matching groups, so this can change without anyone clicking it).
        function syncHooksToggleAllBtn(channel) {
            var $btn = $('.ccx-hooks-toggle-all[data-channel="' + channel + '"]');
            if (!$btn.length) {
                return;
            }
            var $accs = $('#' + channel + '-hooks-wrapper').find('.ccx-acc:visible');
            var allOpen = $accs.length > 0 && $accs.length === $accs.filter('.open').length;

            $btn.attr('data-state', allOpen ? 'expanded' : 'collapsed');
            $btn.find('span').text(allOpen ? 'Collapse all' : 'Expand all');
            $btn.find('i').attr('class', allOpen ? 'fa fa-angle-double-up' : 'fa fa-angle-double-down');
        }

        $(document).on('input', '.hooks-search-input', function () {
            applyHooksSearch($(this).data('channel'));
        });

        $(document).on('keydown', '.hooks-search-input', function (e) {
            if (e.which === 27) { // Esc clears
                $(this).val('');
                applyHooksSearch($(this).data('channel'));
            }
        });

        $(document).on('click', '.ccx-hooks-search-clear', function (e) {
            e.preventDefault();
            var channel = $(this).data('channel');
            $('#' + channel + '-hooks-search').val('').focus();
            applyHooksSearch(channel);
        });

        /**
         * Filters the accordion of a channel against its search box. Matching is
         * per-hook (every token must appear somewhere in the hook's text); a group
         * with no match is hidden entirely, a group with matches is force-opened.
         */
        function applyHooksSearch(channel) {
            var $input = $('#' + channel + '-hooks-search');
            if (!$input.length) {
                return;
            }

            var q = $.trim(($input.val() || '').toLowerCase());
            var $wrapper = $('#' + channel + '-hooks-wrapper');
            var $accs = $wrapper.find('.ccx-acc');

            $('.ccx-hooks-search-clear[data-channel="' + channel + '"]').toggle(q.length > 0);
            $wrapper.find('.ccx-hooks-noresult').remove();
            $accs.find('.ccx-acc-match').remove();

            if (!$accs.length) {
                return;
            }

            if (!q) {
                // Restore the user's own expand/collapse choices
                var openState = hooksOpenState[channel] || {};
                $wrapper.find('.ccx-hook-rows').show();
                $accs.show().each(function () {
                    $(this).toggleClass('open', !!openState[String($(this).data('module'))]);
                });
                syncHooksToggleAllBtn(channel);
                return;
            }

            var tokens = q.split(/\s+/);
            var total = 0;

            $accs.each(function () {
                var $acc = $(this), shown = 0;

                $acc.find('.ccx-hook-rows').each(function () {
                    var haystack = $(this).attr('data-search') || '';
                    var ok = true;
                    for (var i = 0; i < tokens.length; i++) {
                        if (haystack.indexOf(tokens[i]) === -1) {
                            ok = false;
                            break;
                        }
                    }
                    $(this).toggle(ok);
                    if (ok) {
                        shown++;
                    }
                });

                if (shown) {
                    total += shown;
                    $acc.show().addClass('open');
                    $acc.find('.ccx-acc-meta').prepend('<span class="ccx-chip ccx-acc-match" style="background:#fef08a; color:#854d0e;">' + shown + ' match' + (shown === 1 ? '' : 'es') + '</span>');
                } else {
                    $acc.hide().removeClass('open');
                }
            });

            if (!total) {
                $wrapper.append('<div class="ccx-hooks-state ccx-hooks-noresult"><i class="fa fa-search" style="font-size:22px; display:block; margin-bottom:8px;"></i>No hooks match “' + hooksEsc($input.val()) + '”.</div>');
            }
            syncHooksToggleAllBtn(channel);
        }

        // ── Inline Active Toggle ──
        $(document).on('change', '.hook-active-toggle', function () {
            var $toggle = $(this);
            var mappingId = $toggle.data('mapping-id');
            var channel = $toggle.data('channel');
            var active = $toggle.is(':checked') ? 1 : 0;

            $.post(admin_url + 'sms_wa_email/save_hook_mapping', {
                mapping_id: mappingId,
                active: active,
                template_id: 0,  // will be ignored since mapping_id exists
                hook_key: '',
                channel: channel,
                recipient_type: 'patient'
            }).done(function (resp) {
                var r = JSON.parse(resp);
                if (r.success) {
                    alert_float('success', 'Status updated');
                }
            });
        });

        // ── Delete Hook Mapping ──
        $(document).on('click', '.delete-hook-mapping-btn', function (e) {
            e.preventDefault();
            if (!confirm('Remove this recipient mapping?')) return;
            var id = $(this).data('id');
            var channel = $(this).data('channel');
            $.post(admin_url + 'sms_wa_email/delete_hook_mapping/' + id).done(function () {
                alert_float('success', 'Recipient mapping removed');
                hooksLoaded[channel] = false;
                loadHooksForChannel(channel);
            });
        });

        // ══════════════════════════════════════════════════
        //  ADD / EDIT HOOK RECIPIENT MODAL
        // ══════════════════════════════════════════════════

        // Toggle sub-fields based on checked recipient types
        $(document).on('change', '.hr_recipient_cb', function () {
            var checked = {};
            $('.hr_recipient_cb:checked').each(function () { checked[$(this).val()] = true; });
            $('#hr_staff_wrap').toggle(!!checked['staff']);
            $('#hr_role_wrap').toggle(!!checked['role']);
            $('#hr_custom_wrap').toggle(!!checked['custom']);
        });

        // When hook selection changes, populate variable tags for Custom input
        $('#hr_hook_key').on('change', function () {
            var hookKey = $(this).val();
            var $tags = $('#hr_custom_tags');
            $tags.empty();
            if (!hookKey) return;
            var hook = registeredHooks.find(function (h) { return h.hook_key === hookKey; });
            if (hook && hook.variables && hook.variables.length) {
                $.each(hook.variables, function (i, v) {
                    $tags.append(
                        '<span class="ccx-chip hr-tag-pill" data-tag="{' + v + '}" ' +
                        'style="background:#fce7f3; color:#9d174d; cursor:pointer; font-size:11px; padding:4px 10px; border-radius:12px; user-select:none;">' +
                        '<i class="fa fa-tag" style="margin-right:3px; font-size:9px;"></i>{' + v + '}' +
                        '</span>'
                    );
                });
            }
        });

        // Click a variable tag → insert into custom input
        $(document).on('click', '.hr-tag-pill', function () {
            var tag = $(this).data('tag');
            var $input = $('#hr_custom_value');
            $input.val(tag);
            $input.focus();
        });

        // Open Add Recipient modal (from per-hook + button)
        $(document).on('click', '.add-hook-recipient-btn', function (e) {
            e.preventDefault();
            var channel = $(this).data('channel');
            var hookKey = $(this).data('hook') || '';
            openHookRecipientModal(channel, null, hookKey);
        });

        // Open Edit Recipient modal (single mapping)
        $(document).on('click', '.edit-hook-mapping-btn', function (e) {
            e.preventDefault();
            var channel = $(this).data('channel');
            openHookRecipientModal(channel, {
                mapping_id: $(this).data('mapping-id'),
                hook_key: $(this).data('hook'),
                recipient_type: $(this).data('recipient-type') || 'patient',
                recipient_value: $(this).data('recipient-value') || '',
                template_id: $(this).data('template-id') || '',
                active: $(this).data('active')
            });
        });

        function openHookRecipientModal(channel, editData, preSelectHook) {
            var isEdit = !!editData;
            $('#hookRecipientModalLabel').html('<i class="fa fa-plug" style="margin-right:8px; color:#6366f1;"></i>' + (isEdit ? 'Edit' : 'Add') + ' Hook Recipient');
            $('#hr_channel').val(channel);
            $('#hr_mapping_id').val(isEdit ? editData.mapping_id : '');

            // Populate hook dropdown
            // Grouped by module so a long hook list stays scannable — mirrors
            // the accordion grouping in the hooks panel.
            var $hookSel = $('#hr_hook_key');
            $hookSel.find('option:not(:first), optgroup').remove();
            var hookGroups = {}, hookGroupOrder = [];
            $.each(registeredHooks, function (i, h) {
                var mod = h.module || 'other';
                if (!hookGroups[mod]) {
                    hookGroups[mod] = [];
                    hookGroupOrder.push(mod);
                }
                hookGroups[mod].push(h);
            });
            hookGroupOrder.sort(function (a, b) {
                return hookModuleMeta(a).label.localeCompare(hookModuleMeta(b).label);
            });
            $.each(hookGroupOrder, function (i, mod) {
                var $group = $('<optgroup>').attr('label', hookModuleMeta(mod).label);
                $.each(hookGroups[mod], function (j, h) {
                    $group.append($('<option>').val(h.hook_key).text(h.label));
                });
                $hookSel.append($group);
            });
            if (isEdit) {
                $hookSel.val(editData.hook_key);
                $hookSel.prop('disabled', true);
            } else if (preSelectHook) {
                $hookSel.val(preSelectHook);
                $hookSel.prop('disabled', true);
            } else {
                $hookSel.val('');
                $hookSel.prop('disabled', false);
            }
            $hookSel.trigger('change'); // populate custom tags

            // Populate staff dropdown
            var $staffSel = $('#hr_staff_id');
            $staffSel.find('option:not(:first)').remove();
            if (staffListCache) {
                $.each(staffListCache, function (i, s) {
                    $staffSel.append('<option value="' + s.staffid + '">' + s.name + (s.phonenumber ? ' (' + s.phonenumber + ')' : '') + '</option>');
                });
            }

            // Populate role dropdown
            var $roleSel = $('#hr_role_id');
            $roleSel.find('option:not(:first)').remove();
            if (rolesListCache) {
                $.each(rolesListCache, function (i, r) {
                    $roleSel.append('<option value="' + r.roleid + '">' + r.name + '</option>');
                });
            }

            // Populate template dropdown
            var $tplSel = $('#hr_template_id');
            $tplSel.find('option:not(:first)').remove();
            var templates = channelTemplates[channel] || [];
            $.each(templates, function (i, t) {
                var label = t.title;

                // On the Cloud API, what a hook can actually deliver depends on
                // the template's Meta mapping — surface it in the picker.
                if (channel === 'whatsapp' && WA_CLOUD.ready) {
                    if (t.wa_template_name) {
                        var approved = waCloudFind(t.wa_template_name, t.wa_template_language || '');
                        var varCount = approved ? (parseInt(approved.variables_count, 10) || 0) : 0;
                        label += ' · ' + t.wa_template_name;
                        if (varCount > 0 && !$.trim(t.wa_params || '')) {
                            label += ' ⚠ needs variables';
                        }
                    } else {
                        label += ' · session only';
                    }
                }

                $tplSel.append($('<option>').val(t.id).text(label));
            });

            // Reset all checkboxes
            $('.hr_recipient_cb').prop('checked', false);
            $('#hr_staff_wrap, #hr_role_wrap, #hr_custom_wrap').hide();
            $('#hr_staff_id').val('');
            $('#hr_role_id').val('');
            $('#hr_custom_value').val('');

            if (isEdit) {
                // Edit: check only the existing type
                $('.hr_recipient_cb[value="' + editData.recipient_type + '"]').prop('checked', true).trigger('change');
                if (editData.recipient_type === 'staff') {
                    $('#hr_staff_id').val(editData.recipient_value);
                } else if (editData.recipient_type === 'role') {
                    $('#hr_role_id').val(editData.recipient_value);
                } else if (editData.recipient_type === 'custom') {
                    $('#hr_custom_value').val(editData.recipient_value);
                }
                $('#hr_template_id').val(editData.template_id);
                $('#hr_active').prop('checked', editData.active == 1);
            } else {
                $('#hr_template_id').val('');
                $('#hr_active').prop('checked', true);
            }

            $('#hookRecipientModal').modal('show');
        }

        // Save Hook Recipient(s)
        $('#save_hook_recipient_btn').on('click', function () {
            var btn = $(this);
            var hookKey = $('#hr_hook_key').val();
            var channel = $('#hr_channel').val();
            var templateId = $('#hr_template_id').val();
            var mappingId = $('#hr_mapping_id').val();
            var active = $('#hr_active').is(':checked') ? 1 : 0;

            // Validation
            if (!hookKey) { alert_float('warning', 'Please select a hook event'); return; }
            if (!templateId) { alert_float('warning', 'Please select a template'); return; }

            // Collect checked recipient types
            var checkedTypes = [];
            $('.hr_recipient_cb:checked').each(function () { checkedTypes.push($(this).val()); });
            if (checkedTypes.length === 0) { alert_float('warning', 'Please select at least one recipient type'); return; }

            // Build recipient entries to save
            var entries = [];
            for (var i = 0; i < checkedTypes.length; i++) {
                var type = checkedTypes[i];
                var value = '';
                if (type === 'staff') {
                    value = $('#hr_staff_id').val();
                    if (!value) { alert_float('warning', 'Please select a staff member'); return; }
                } else if (type === 'role') {
                    value = $('#hr_role_id').val();
                    if (!value) { alert_float('warning', 'Please select a role'); return; }
                } else if (type === 'custom') {
                    value = $.trim($('#hr_custom_value').val());
                    if (!value) { alert_float('warning', 'Please enter a custom number or select a variable tag'); return; }
                }
                entries.push({ recipient_type: type, recipient_value: value });
            }

            btn.button('loading');

            // If editing a single mapping, just update that one
            if (mappingId && entries.length === 1) {
                $.post(admin_url + 'sms_wa_email/save_hook_mapping', {
                    mapping_id: mappingId,
                    hook_key: hookKey,
                    channel: channel,
                    template_id: templateId,
                    active: active,
                    recipient_type: entries[0].recipient_type,
                    recipient_value: entries[0].recipient_value
                }).done(function (resp) {
                    var r = JSON.parse(resp);
                    if (r.success) {
                        alert_float('success', 'Hook recipient updated');
                        $('#hookRecipientModal').modal('hide');
                        hooksLoaded[channel] = false;
                        loadHooksForChannel(channel);
                    } else if (r.error === 'duplicate') {
                        alert_float('warning', r.message || 'Duplicate mapping');
                    }
                }).fail(function () {
                    alert_float('danger', 'Failed to save');
                }).always(function () { btn.button('reset'); });
                return;
            }

            // Adding new: save each recipient type as a separate mapping
            var saveQueue = entries.slice();
            var savedCount = 0;
            function saveNext() {
                if (saveQueue.length === 0) {
                    btn.button('reset');
                    alert_float('success', savedCount + ' recipient mapping(s) saved');
                    $('#hookRecipientModal').modal('hide');
                    hooksLoaded[channel] = false;
                    loadHooksForChannel(channel);
                    return;
                }
                var entry = saveQueue.shift();
                $.post(admin_url + 'sms_wa_email/save_hook_mapping', {
                    hook_key: hookKey,
                    channel: channel,
                    template_id: templateId,
                    active: active,
                    recipient_type: entry.recipient_type,
                    recipient_value: entry.recipient_value
                }).done(function (resp) {
                    var r = JSON.parse(resp);
                    if (r.success) {
                        savedCount++;
                    } else if (r.error === 'duplicate') {
                        alert_float('warning', r.message || 'Duplicate mapping skipped');
                    }
                    saveNext();
                }).fail(function () {
                    alert_float('danger', 'Failed to save one mapping');
                    btn.button('reset');
                });
            }
            saveNext();
        });


        // ══════════════════════════════════════════════════
        //  HOOK TRIGGER LOGS
        // ══════════════════════════════════════════════════

        var currentLogsChannel = 'all';

        // Open logs modal
        $(document).on('click', '.view-hook-logs-btn', function () {
            var channel = $(this).data('channel');
            currentLogsChannel = channel;
            $('#hook_logs_channel_filter').val(channel);
            fetchHookLogs(channel);
            $('#hookLogsModal').modal('show');
        });

        // Filter change inside modal
        $('#hook_logs_channel_filter ').on('change', function () {
            currentLogsChannel = $(this).val();
            fetchHookLogs(currentLogsChannel);
        });

        function fetchHookLogs(channel) {
            var $wrapper = $('#hook-logs-wrapper');
            $wrapper.html('<tr><td colspan="11" class="text-center" style="padding:30px; color:#9ca3af;"><i class="fa fa-spinner fa-spin"></i> Loading logs...</td></tr>');

            $.get(admin_url + 'sms_wa_email/get_hook_logs/' + channel, function (response) {
                var logs = JSON.parse(response);
                renderHookLogs(logs);
            }).fail(function () {
                $wrapper.html('<tr><td colspan="11" class="text-center" style="color:#ef4444;"><i class="fa fa-exclamation-triangle"></i> Failed to load logs</td></tr>');
            });
        }

        // Helper: relative "time ago" string
        function timeAgo(dateStr) {
            if (!dateStr) return '—';
            var now = new Date();
            var then = new Date(dateStr.replace(' ', 'T'));
            var diffMs = now - then;
            if (diffMs < 0) return 'just now';
            var diffS = Math.floor(diffMs / 1000);
            if (diffS < 60) return diffS + 's ago';
            var diffM = Math.floor(diffS / 60);
            if (diffM < 60) return diffM + 'm ago';
            var diffH = Math.floor(diffM / 60);
            if (diffH < 24) return diffH + 'h ago';
            var diffD = Math.floor(diffH / 24);
            return diffD + 'd ago';
        }

        function renderHookLogs(logs) {
            var $wrapper = $('#hook-logs-wrapper');
            $('#hook_logs_count').text(logs.length + ' entries');

            if (!logs || logs.length === 0) {
                $wrapper.html('<tr><td colspan="11" class="text-center" style="padding:30px; color:#9ca3af;"><i class="fa fa-inbox" style="font-size:20px; display:block; margin-bottom:6px;"></i>No trigger logs yet</td></tr>');
                return;
            }

            var statusColors = {
                'success': 'background:#dcfce7; color:#166534;',
                'failed': 'background:#fef2f2; color:#991b1b;',
                'api_failed': 'background:#fef2f2; color:#991b1b;',
                'insufficient_balance': 'background:#fef3c7; color:#92400e;',
                'expired': 'background:#fef3c7; color:#92400e;',
                'no_template': 'background:#f3f4f6; color:#4b5563;',
                'no_api': 'background:#fef3c7; color:#92400e;',
                'no_mapping': 'background:#e0e7ff; color:#3730a3;',
                'inactive': 'background:#fef2f2; color:#991b1b;',
                'pending': 'background:#e0e7ff; color:#3730a3;'
            };

            var channelLabels = {
                'sms': 'SMS',
                'whatsapp': 'WhatsApp',
                'email': 'Email',
                'ai_call_agent': 'AI Call'
            };

            var html = '';
            $.each(logs, function (i, log) {
                var statusStyle = statusColors[log.status] || 'background:#f3f4f6; color:#4b5563;';
                var statusBadge = '<span class="ccx-chip" style="' + statusStyle + ' font-size:10px;">' + log.status.replace(/_/g, ' ') + '</span>';
                var channelBadge = '<span class="ccx-chip" style="background:#eef2ff; color:#4338ca; font-size:10px;">' + (channelLabels[log.channel] || log.channel) + '</span>';

                // Send type badge
                var sendTypeLabels = {
                    'hook': {label: 'Hook', style: 'background:#f0fdf4; color:#166534;'},
                    'campaign': {label: 'Campaign', style: 'background:#eff6ff; color:#1d4ed8;'},
                    'auto_scheduler': {label: 'Auto Scheduler', style: 'background:#faf5ff; color:#7c3aed;'},
                    // System email the CRM itself sent, routed through this
                    // module — see the Email tab's CRM Routing panel.
                    'crm': {label: 'CRM Email', style: 'background:#fffbeb; color:#92400e;'}
                };
                var st = log.send_type || 'hook';
                var stInfo = sendTypeLabels[st] || {label: st, style: 'background:#f3f4f6; color:#4b5563;'};
                var sendTypeBadge = '<span class="ccx-chip" style="' + stInfo.style + ' font-size:10px;">' + stInfo.label + '</span>';

                // Message type badge (promotional / transactional)
                var typeBadge = '—';
                if (log.message_subtype) {
                    if (log.message_subtype === 'promotional') {
                        typeBadge = '<span class="ccx-chip" style="background:#fef3c7; color:#92400e; font-size:10px;"><i class="fa fa-bullhorn" style="margin-right:3px;"></i>Promo</span>';
                    } else {
                        typeBadge = '<span class="ccx-chip" style="background:#dbeafe; color:#1e40af; font-size:10px;"><i class="fa fa-exchange" style="margin-right:3px;"></i>Trans</span>';
                    }
                }

                // Column 1: API / Error info
                var apiErrorCol = '—';
                if (log.error_message) {
                    apiErrorCol = '<span style="color:#dc2626; font-size:11px;"><i class="fa fa-exclamation-circle" style="margin-right:3px;"></i>' + $('<div>').text(log.error_message).html() + '</span>';
                } else if (log.status === 'success') {
                    apiErrorCol = '<span style="color:#166534; font-size:11px;"><i class="fa fa-check-circle" style="margin-right:3px;"></i>API call successful</span>';
                }

                // Column 2: Message Content (final rendered template message)
                var messageCol = '—';
                if (log.message_preview) {
                    var safeMsg = $('<div>').text(log.message_preview).html();
                    var truncMsg = safeMsg.length > 150 ? safeMsg.substring(0, 150) + '...' : safeMsg;
                    messageCol = '<span style="color:#374151; font-size:11px; line-height:1.4; display:block;" title="' + safeMsg.replace(/"/g, '&quot;') + '">' + truncMsg + '</span>';
                }

                var hookDef = (hooksData['sms'] || []).find(function (h) { return h.hook_key === log.hook_key; });
                // crm_email is not a registered hook — it is the CRM's own
                // outgoing mail, named by its core template in the preview.
                var hookLabel = hookDef
                    ? hookDef.label
                    : (log.hook_key === 'crm_email' ? 'CRM System Email' : log.hook_key);

                // Format time with relative "ago" tag
                var timeStr = log.created_at || '';
                var ago = timeAgo(log.created_at);
                var timeDisplay = '<div style="line-height:1.3;">' +
                    '<span style="font-size:11px; color:#374151;">' + timeStr + '</span><br>' +
                    '<span class="ccx-chip" style="background:#f0f4ff; color:#6366f1; font-size:9px; padding:2px 7px; margin-top:2px; display:inline-block;">' +
                    '<i class="fa fa-clock-o" style="margin-right:2px;"></i>' + ago + '</span></div>';

                html += '<tr>' +
                    '<td>' + timeDisplay + '</td>' +
                    '<td>' + sendTypeBadge + '</td>' +
                    '<td><strong style="font-size:11px;">' + hookLabel + '</strong></td>' +
                    '<td>' + channelBadge + '</td>' +
                    '<td>' + typeBadge + '</td>' +
                    '<td style="font-size:11px;">' + (log.recipient || '—') + '</td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td>' + apiErrorCol + '</td>' +
                    '<td>' + messageCol + '</td>' +
                    '<td style="font-size:11px;">' + (log.staff_name || '—') + '</td>' +
                    '<td><a href="#" class="ccx-action-btn btn-delete delete-hook-log-btn" data-id="' + log.id + '" title="Delete"><i class="fa fa-trash" style="font-size:11px;"></i></a></td>' +
                    '</tr>';
            });

            $wrapper.html(html);
        }

        // Delete log entry
        $(document).on('click', '.delete-hook-log-btn', function (e) {
            e.preventDefault();
            if (confirm('Delete this log entry?')) {
                var id = $(this).data('id');
                $.post(admin_url + 'sms_wa_email/delete_hook_log/' + id).done(function () {
                    fetchHookLogs(currentLogsChannel);
                    alert_float('success', 'Log entry deleted');
                });
            }
        });


    });

    // ═══ Coupon System Functions ═══
    var cpnChannelMeta = {
        sms:           { label: 'SMS',          icon: 'fa fa-comment',    bg: '#eef2ff', color: '#4f46e5' },
        whatsapp:      { label: 'WhatsApp',     icon: 'fab fa-whatsapp',  bg: '#f0fdf4', color: '#16a34a' },
        email:         { label: 'Email',        icon: 'fa fa-envelope',   bg: '#fffbeb', color: '#d97706' },
        aicall:        { label: 'AI Call',      icon: 'fa fa-phone',      bg: '#faf5ff', color: '#7c3aed' }
    };

    var cpnValidatedCode = '';
    var cpnValidatedData = null;

    function toggleCouponSection() {
        var $body = $('#cpnBody');
        if ($body.is(':visible')) {
            $body.slideUp(200);
        } else {
            $body.slideDown(200);
            resetCouponState();
            setTimeout(function() { $('#cpnCodeInput').focus(); }, 250);
        }
    }

    function resetCouponState() {
        $('#cpnCodeInput').val('').prop('disabled', false);
        $('#cpnApplyBtn').prop('disabled', false);
        $('#cpnLoading, #cpnError, #cpnDetailsCard, #cpnSuccess').hide();
        $('#cpnInputRow').show();
        cpnValidatedCode = '';
        cpnValidatedData = null;
    }

    function validateCouponCode() {
        var code = $.trim($('#cpnCodeInput').val());
        if (!code) {
            $('#cpnCodeInput').focus();
            return;
        }
        if (!master_url || !tenant_client_id) {
            showCpnError('SaaS parameters missing. Cannot validate coupon.');
            return;
        }

        // Show loading
        $('#cpnLoading').show();
        $('#cpnError, #cpnDetailsCard, #cpnSuccess').hide();
        $('#cpnApplyBtn').prop('disabled', true);

        $.ajax({
            url: master_url + 'ccx_msgs/ccx_msgs_public/validate_coupon',
            method: 'GET',
            data: { code: code, client_id: tenant_client_id },
            dataType: 'json',
            timeout: 15000,
            success: function(resp) {
                $('#cpnLoading').hide();
                $('#cpnApplyBtn').prop('disabled', false);

                if (!resp.success) {
                    showCpnError(resp.message || 'Invalid coupon code.');
                    return;
                }

                // Store validated data
                cpnValidatedCode = resp.code;
                cpnValidatedData = resp;

                // Render details card
                renderCouponDetails(resp);
            },
            error: function() {
                $('#cpnLoading').hide();
                $('#cpnApplyBtn').prop('disabled', false);
                showCpnError('Failed to validate coupon. Please try again.');
            }
        });
    }

    function showCpnError(msg) {
        $('#cpnErrorMsg').text(msg);
        $('#cpnError').show();
    }

    function renderCouponDetails(data) {
        $('#cpnDetailCode').text(data.code);
        $('#cpnDetailDesc').text(data.description || 'Free credits coupon');

        // Render credit badges
        var html = '';
        var credits = data.credits || {};
        for (var ch in credits) {
            var meta = cpnChannelMeta[ch] || { label: ch, icon: 'fa fa-circle', bg: '#f3f4f6', color: '#374151' };
            html += '<div class="cpn-credit-badge" style="background:' + meta.bg + '; color:' + meta.color + ';">' +
                    '<span class="cpn-credit-count">+' + credits[ch].toLocaleString() + '</span>' +
                    '<span class="cpn-credit-label"><i class="' + meta.icon + '" style="margin-right:3px;"></i>' + meta.label + '</span>' +
                    '</div>';
        }
        $('#cpnCreditsGrid').html(html);

        // Expiry note
        if (data.expiry_days && data.expiry_days > 0) {
            $('#cpnExpiryText').text('Credits will expire ' + data.expiry_days + ' days after claiming.');
            $('#cpnExpiryNote').show();
        } else {
            $('#cpnExpiryNote').hide();
        }

        $('#cpnDetailsCard').slideDown(250);
    }

    function claimCouponCode() {
        if (!cpnValidatedCode || !cpnValidatedData) {
            alert_float('danger', 'Please validate a coupon first.');
            return;
        }

        var $btn = $('#cpnClaimBtn');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Claiming...');

        $.ajax({
            url: master_url + 'ccx_msgs/ccx_msgs_public/claim_coupon',
            method: 'GET',
            data: { code: cpnValidatedCode, client_id: tenant_client_id },
            dataType: 'json',
            timeout: 20000,
            success: function(resp) {
                if (!resp.success) {
                    $btn.prop('disabled', false).html('<i class="fa fa-bolt"></i> Claim Free Credits');
                    showCpnError(resp.message || 'Failed to claim coupon.');
                    return;
                }

                // Hide input + details, show success
                $('.cpn-input-row').hide();
                $('#cpnDetailsCard').hide();
                $('#cpnError').hide();

                // Build success badges
                var badgesHtml = '';
                var credits = resp.credits_awarded || {};
                for (var ch in credits) {
                    var meta = cpnChannelMeta[ch] || { label: ch, bg: '#f3f4f6', color: '#374151' };
                    badgesHtml += '<span class="cpn-success-badge" style="background:' + meta.bg + '; color:' + meta.color + ';">' +
                                 '+' + credits[ch].toLocaleString() + ' ' + meta.label +
                                 '</span>';
                }
                $('#cpnSuccessCredits').html(badgesHtml);
                $('#cpnSuccessMsg').text(resp.message);
                $('#cpnSuccess').show();

                // Update local allocations data
                var channelMap = {
                    sms: 'sms', whatsapp: 'whatsapp',
                    email: 'email', aicall: 'aicall'
                };
                for (var ch in credits) {
                    var allocCh = channelMap[ch] || ch;
                    if (typeof rchAllocations !== 'undefined' && rchAllocations[allocCh]) {
                        rchAllocations[allocCh].promo_count += credits[ch];
                        rchAllocations[allocCh].total += credits[ch];
                    }
                }

                // Refresh balance cards on page (if renderChannelCards exists)
                if (typeof renderStep1Channels === 'function') {
                    renderStep1Channels();
                }

                alert_float('success', 'Coupon claimed! Free credits added to your account.');
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="fa fa-bolt"></i> Claim Free Credits');
                showCpnError('Network error. Please try again.');
            }
        });
    }
</script>