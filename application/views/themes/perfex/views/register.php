<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<!-- ═══════════════════════════════════════════════════════════════
     MODERN REGISTRATION PAGE — Clientcarex (Two-Step Wizard v3)
     Step 1: Profile & Organisation   |   Step 2: Verification
     ═══════════════════════════════════════════════════════════════ -->
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

/* ── Page ── */
body.register {
    background: #F0F4F8 !important;
    min-height: 100vh;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
}
body.register #wrapper,
body.register #content { background: transparent !important; }
body.register .navbar,
body.register .navbar-default {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

/* ── Card ── */
.ho-register-wrapper {
    max-width: 780px;
    margin: 20px auto 40px;
    padding: 0 16px;
}
.ho-register-card {
    background: #FFFFFF;
    border-radius: 24px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06), 0 1px 4px rgba(0,0,0,0.04);
    overflow: hidden;
    border: 1px solid rgba(0,0,0,0.04);
    animation: hoSlideUp 0.5s ease-out;
}
.ho-register-card::before {
    content: '';
    display: block;
    height: 5px;
    background: linear-gradient(135deg, #0D9488, #0EA5E9, #6366F1);
    border-radius: 24px 24px 0 0;
}

/* ── Header ── */
.ho-register-header {
    text-align: center;
    padding: 32px 32px 0;
}
.ho-register-header .ho-logo { margin-bottom: 16px; }
.ho-register-header .ho-logo img { max-height: 48px; width: auto; }
.ho-register-header h1 {
    font-size: 26px;
    font-weight: 700;
    color: #1E293B;
    margin: 0 0 6px;
    letter-spacing: -0.3px;
}
.ho-register-header p {
    font-size: 14px;
    color: #64748B;
    margin: 0;
    font-weight: 400;
}

/* ═══════ STEPPER ═══════ */
.ho-stepper {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 28px 36px 0;
}
.ho-step-item {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: default;
}
.ho-step-circle {
    width: 36px; height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px; font-weight: 700;
    transition: all 0.35s ease;
    flex-shrink: 0;
    background: #E2E8F0;
    color: #94A3B8;
}
.ho-step-item.active .ho-step-circle {
    background: linear-gradient(135deg, #0D9488, #0EA5E9);
    color: #FFF;
    box-shadow: 0 4px 12px rgba(13,148,136,0.3);
}
.ho-step-item.completed .ho-step-circle {
    background: #0D9488;
    color: #FFF;
}
.ho-step-text {
    font-size: 13px; font-weight: 500;
    color: #94A3B8;
    transition: color 0.3s;
    white-space: nowrap;
}
.ho-step-item.active .ho-step-text { color: #1E293B; font-weight: 600; }
.ho-step-item.completed .ho-step-text { color: #0D9488; }
.ho-step-line {
    width: 80px; height: 3px;
    background: #E2E8F0;
    border-radius: 2px;
    margin: 0 16px;
    position: relative;
    overflow: hidden;
}
.ho-step-line::after {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 0%;
    background: linear-gradient(90deg, #0D9488, #0EA5E9);
    border-radius: 2px;
    transition: width 0.4s ease;
}
.ho-step-line.filled::after { width: 100%; }

/* ═══════ FORM BODY ═══════ */
.ho-register-body { padding: 24px 36px 8px; }

.ho-step-panel { display: none; }
.ho-step-panel.active {
    display: block;
    animation: hoFadeIn 0.35s ease-out;
}
@keyframes hoFadeIn {
    from { opacity: 0; transform: translateX(12px); }
    to   { opacity: 1; transform: translateX(0); }
}

/* ── Section Label ── */
.ho-section-label {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 20px 0 16px;
    font-size: 11px; font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #94A3B8;
}
.ho-section-label:first-child { margin-top: 0; }
.ho-section-label::after {
    content: '';
    flex: 1; height: 1px;
    background: #E2E8F0;
}
.ho-section-label i { font-size: 13px; color: #0D9488; }

/* ── Two-Column Row ── */
.ho-row {
    display: flex;
    gap: 16px;
    margin-bottom: 0;
}
.ho-row > .ho-field { flex: 1; min-width: 0; }

/* ── Field ── */
.ho-field {
    margin-bottom: 18px;
    position: relative;
}
.ho-field label {
    display: block;
    font-size: 13px; font-weight: 500;
    color: #475569;
    margin-bottom: 6px;
    letter-spacing: -0.1px;
}
.ho-field label .req {
    color: #EF4444;
    font-weight: 600;
    margin-left: 2px;
}

/* ── Input ── */
.ho-field input.ho-input,
.ho-field select.ho-input,
.ho-field .bootstrap-select .btn {
    width: 100%; height: 46px;
    padding: 0 16px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    color: #1E293B;
    background: #F8FAFC;
    border: 1.5px solid #E2E8F0;
    border-radius: 12px;
    outline: none;
    transition: all 0.2s ease;
    box-shadow: none;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}
.ho-field input.ho-input::placeholder { color: #94A3B8; font-weight: 400; }
.ho-field input.ho-input:focus,
.ho-field select.ho-input:focus,
.ho-field .bootstrap-select.open .btn {
    border-color: #0D9488;
    background: #FFF;
    box-shadow: 0 0 0 3px rgba(13,148,136,0.1);
}

/* read-only inputs in Step 2 */
.ho-field input.ho-input[readonly] {
    background: #F1F5F9;
    color: #64748B;
    cursor: default;
}
.ho-field input.ho-input[readonly]:focus {
    border-color: #E2E8F0;
    box-shadow: none;
}

/* ── Field error ── */
.ho-field.ho-field-error input.ho-input,
.ho-field.ho-field-error select.ho-input {
    border-color: #EF4444;
    background: #FFF5F5;
}
.ho-field.ho-field-error input.ho-input:focus,
.ho-field.ho-field-error select.ho-input:focus {
    box-shadow: 0 0 0 3px rgba(239,68,68,0.1);
}
.ho-field .text-danger,
.ho-field .field-error {
    font-size: 12px;
    margin-top: 4px;
    color: #EF4444;
}

/* ── Select ── */
.ho-field select.ho-input {
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    padding-right: 38px;
    outline: 0 !important;
}
.ho-field select.ho-input:focus {
    outline: 0 !important;
    border-color: #0D9488;
    background-color: #FFF;
    box-shadow: 0 0 0 3px rgba(13,148,136,0.1);
}

/* ── Fix double border: bootstrap-select hides native select ── */
body.register .ho-field .bootstrap-select select.form-control,
body.register .ho-field select.form-control {
    border: none !important;
    box-shadow: none !important;
    outline: none !important;
    background: transparent !important;
    height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
    opacity: 0 !important;
    position: absolute !important;
    pointer-events: none !important;
}

/* ── Kill any residual browser/Bootstrap borders on all selects ── */
body.register select,
body.register select:focus,
body.register select:active {
    outline: 0 !important;
    -webkit-outline: none !important;
}
body.register .ho-field .bootstrap-select {
    border: none !important;
    outline: none !important;
}

/* ── Bootstrap-select overrides ── */
body.register .ho-field .bootstrap-select { width: 100% !important; }
body.register .ho-field .bootstrap-select .btn {
    height: 46px !important; line-height: 44px !important;
    padding: 0 16px !important; font-size: 14px !important;
    font-family: 'Inter', sans-serif !important;
    color: #1E293B !important; background: #F8FAFC !important;
    border: 1.5px solid #E2E8F0 !important; border-radius: 12px !important;
    box-shadow: none !important; transition: all 0.2s ease !important;
}
body.register .ho-field .bootstrap-select.open .btn,
body.register .ho-field .bootstrap-select .btn:focus {
    border-color: #0D9488 !important; background: #FFF !important;
    box-shadow: 0 0 0 3px rgba(13,148,136,0.1) !important;
}
body.register .ho-field .bootstrap-select > .dropdown-menu {
    border-radius: 12px !important; border: 1.5px solid #E2E8F0 !important;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08) !important;
    margin-top: 4px !important; padding: 6px !important;
}
body.register .ho-field .bootstrap-select .dropdown-menu.inner {
    border: none !important; box-shadow: none !important;
    padding: 0 !important; border-radius: 0 !important;
}
body.register .ho-field .bootstrap-select .dropdown-menu li a {
    border-radius: 8px !important; padding: 8px 12px !important;
    font-size: 13px !important; color: #475569 !important;
}
body.register .ho-field .bootstrap-select .dropdown-menu li a:hover,
body.register .ho-field .bootstrap-select .dropdown-menu li.selected a {
    background: #F0FDFA !important; color: #0D9488 !important;
}
body.register .ho-field .bootstrap-select .bs-searchbox input {
    border-radius: 8px !important; border: 1.5px solid #E2E8F0 !important;
    font-size: 13px !important; padding: 8px 12px !important;
}
body.register .ho-field .bootstrap-select .bs-searchbox input:focus {
    border-color: #0D9488 !important;
    box-shadow: 0 0 0 3px rgba(13,148,136,0.1) !important;
}

body.register .form-group { margin-bottom: 0; }

/* ── "Other" text input (Occupation) ── */
.ho-other-input-wrap {
    margin-top: 8px;
    display: none;
}
.ho-other-input-wrap.visible {
    display: block;
    animation: hoFadeIn 0.25s ease-out;
}

/* ── Password Wrapper ── */
.ho-password-wrapper { position: relative; }
.ho-password-wrapper input { padding-right: 76px !important; }
.ho-pw-actions {
    position: absolute; right: 8px; top: 50%;
    transform: translateY(-50%);
    display: flex; align-items: center; gap: 2px;
}
.ho-pw-btn {
    background: none; border: none;
    padding: 5px; cursor: pointer;
    color: #94A3B8; font-size: 15px;
    line-height: 1; transition: all 0.2s;
    border-radius: 6px;
}
.ho-pw-btn:hover { color: #0D9488; background: #F0FDFA; }
.ho-pw-btn.ho-generate-btn { font-size: 14px; }

/* ── Password match indicator ── */
.ho-pw-match {
    font-size: 12px; margin-top: 4px;
    display: none;
}
.ho-pw-match.show { display: block; }
.ho-pw-match.match { color: #0D9488; }
.ho-pw-match.mismatch { color: #EF4444; }

/* ═══════ FOOTER ═══════ */
.ho-register-footer { padding: 8px 36px 32px; }
.ho-btn-row {
    display: flex;
    gap: 12px;
    align-items: center;
}
.ho-btn-primary {
    flex: 1; height: 50px;
    border: none; border-radius: 14px;
    background: linear-gradient(135deg, #0D9488 0%, #0EA5E9 100%);
    color: #FFF; font-size: 15px; font-weight: 600;
    font-family: 'Inter', sans-serif;
    cursor: pointer; transition: all 0.3s ease;
    overflow: hidden; letter-spacing: 0.2px;
    display: flex; align-items: center;
    justify-content: center; gap: 8px;
}
.ho-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(13,148,136,0.35);
}
.ho-btn-primary:active { transform: translateY(0); }
.ho-btn-primary:disabled {
    opacity: 0.7; cursor: not-allowed;
    transform: none; box-shadow: none;
}
.ho-btn-back {
    height: 50px; padding: 0 24px;
    border: 1.5px solid #E2E8F0;
    border-radius: 14px; background: #FFF;
    color: #64748B; font-size: 14px; font-weight: 500;
    font-family: 'Inter', sans-serif;
    cursor: pointer; transition: all 0.2s ease;
    display: flex; align-items: center;
    justify-content: center; gap: 6px;
    flex-shrink: 0;
}
.ho-btn-back:hover {
    border-color: #CBD5E1; background: #F8FAFC; color: #1E293B;
}

/* ── Terms text ── */
.ho-terms-text {
    text-align: center;
    font-size: 12.5px; color: #94A3B8;
    margin-top: 16px; line-height: 1.5;
}
.ho-terms-text a {
    color: #0D9488; font-weight: 500;
    text-decoration: none;
}
.ho-terms-text a:hover { text-decoration: underline; color: #0F766E; }

/* ── Login link ── */
.ho-login-link {
    text-align: center;
    margin-top: 16px; font-size: 14px; color: #64748B;
}
.ho-login-link a {
    color: #0D9488; font-weight: 600;
    text-decoration: none; transition: color 0.2s;
}
.ho-login-link a:hover { color: #0F766E; text-decoration: underline; }

/* ── GDPR ── */
.ho-gdpr-wrapper {
    margin: 4px 0 12px;
    padding: 14px 16px;
    background: #F8FAFC;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
}
.ho-gdpr-wrapper .checkbox { margin: 0; }
.ho-gdpr-wrapper label { font-size: 13px; color: #475569; font-weight: 400; }
.ho-gdpr-wrapper a { color: #0D9488; font-weight: 500; }

/* ── Recaptcha ── */
.ho-recaptcha-wrapper {
    display: flex; justify-content: center; margin: 16px 0 8px;
}

/* ── Honeypot ── */
.honey-element {
    position: absolute !important; left: -9999px !important;
    top: -9999px !important; opacity: 0 !important;
    height: 0 !important; width: 0 !important; z-index: -1 !important;
}

/* ── SaaS widget ── */
.ho-subdomain-placeholder .form-group { margin-bottom: 0 !important; }
.ho-subdomain-placeholder .form-group label {
    font-size: 13px !important; font-weight: 500 !important;
    color: #475569 !important; margin-bottom: 6px !important;
}
.ho-subdomain-placeholder .form-group input.form-control {
    height: 46px !important; padding: 0 16px !important;
    font-size: 14px !important; font-family: 'Inter', sans-serif !important;
    color: #1E293B !important; background: #F8FAFC !important;
    border: 1.5px solid #E2E8F0 !important; border-radius: 12px !important;
    box-shadow: none !important; transition: all 0.2s ease !important;
}
.ho-subdomain-placeholder .form-group input.form-control:focus {
    border-color: #0D9488 !important; background: #FFF !important;
    box-shadow: 0 0 0 3px rgba(13,148,136,0.1) !important;
}

/* ── IP notice ── */
.ho-ip-notice {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 14px;
    background: #F0FDFA; border: 1px solid #CCFBF1;
    border-radius: 10px; margin-bottom: 18px;
    font-size: 12px; color: #0F766E; font-weight: 500;
}
.ho-ip-notice i { font-size: 14px; color: #0D9488; }
.ho-ip-notice .ho-ip-spinner {
    display: inline-block; width: 14px; height: 14px;
    border: 2px solid #99F6E4; border-top-color: #0D9488;
    border-radius: 50%;
    animation: hoSpin 0.6s linear infinite;
}
@keyframes hoSpin { to { transform: rotate(360deg); } }

/* ── Selected Plan Summary (single row) ── */
.ho-plan-banner {
    display: flex;
    align-items: center;
    gap: 14px;
    background: linear-gradient(135deg, #F0FDFA 0%, #E0F2FE 100%);
    border: 1px solid #CCFBF1;
    border-radius: 16px;
    padding: 14px 18px;
    margin-bottom: 20px;
}
.ho-plan-icon {
    width: 46px; height: 46px;
    border-radius: 12px;
    background: linear-gradient(135deg, #0D9488, #0EA5E9);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    color: #FFF; font-size: 19px;
    box-shadow: 0 4px 10px rgba(13,148,136,.25);
}
.ho-plan-info { flex: 1; min-width: 0; }
.ho-plan-info .ho-plan-name {
    font-size: 15px; font-weight: 700;
    color: #0F172A; margin: 0;
    line-height: 1.3;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ho-plan-info .ho-plan-meta {
    font-size: 11.5px; color: #64748B;
    margin: 3px 0 0; line-height: 1.35;
    font-weight: 600;
}
.ho-plan-info .ho-plan-meta .ho-dot {
    color: #CBD5E1; margin: 0 5px;
}
.ho-plan-price {
    flex-shrink: 0;
    display: flex; flex-direction: column;
    align-items: flex-end;
    text-align: right;
    padding-left: 12px;
    border-left: 1px solid rgba(13,148,136,.14);
    margin-left: 2px;
}
.ho-plan-price .ho-price-total {
    font-size: 21px; font-weight: 800;
    color: #0D9488; line-height: 1.1;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.ho-plan-price .ho-price-sub {
    font-size: 11px; font-weight: 600;
    color: #94A3B8; line-height: 1.3;
    margin-top: 3px;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.ho-plan-price .ho-price-sub b {
    color: #0F766E; font-weight: 700;
}

/* ── Perfex overrides ── */
body.register .panel_s {
    border: none !important; box-shadow: none !important;
    background: transparent !important; margin: 0 !important;
}
body.register .panel-body { padding: 0 !important; }
body.register .panel-footer { display: none !important; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .ho-register-wrapper { margin: 12px auto 24px; padding: 0 12px; }
    .ho-register-card { border-radius: 20px; }
    .ho-register-header { padding: 24px 20px 0; }
    .ho-register-header h1 { font-size: 22px; }
    .ho-stepper { padding: 24px 20px 0; }
    .ho-step-text { font-size: 12px; }
    .ho-step-line { width: 40px; margin: 0 8px; }
    .ho-register-body { padding: 20px 20px 4px; }
    .ho-register-footer { padding: 4px 20px 24px; }
    .ho-row { flex-direction: column; gap: 0; }
    .ho-btn-primary { height: 48px; font-size: 14px; }
    .ho-btn-back { height: 48px; padding: 0 18px; }
    .ho-otp-inputs { gap: 7px; }
    .ho-otp-inputs input { width: 42px; height: 48px; font-size: 18px; }
    .ho-otp-title { font-size: 18px; }
    .ho-step-circle { width: 30px; height: 30px; font-size: 12px; }
}

@keyframes hoSlideUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ═══════ OTP STEP 3 ═══════ */
.ho-otp-wrapper {
    text-align: center;
    padding: 20px 0 10px;
}
.ho-otp-icon {
    width: 72px; height: 72px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #F0FDFA, #CCFBF1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ho-otp-icon i {
    font-size: 32px;
    color: #0D9488;
}
.ho-otp-title {
    font-size: 20px; font-weight: 700;
    color: #1E293B;
    margin-bottom: 8px;
}
.ho-otp-subtitle {
    font-size: 14px; color: #64748B;
    line-height: 1.6;
    margin-bottom: 28px;
}
.ho-otp-subtitle strong {
    color: #0F172A;
    font-weight: 600;
}
.ho-otp-inputs {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-bottom: 24px;
}
.ho-otp-inputs input {
    width: 48px; height: 54px;
    text-align: center;
    font-size: 22px; font-weight: 700;
    font-family: 'Inter', monospace;
    color: #1E293B;
    background: #F8FAFC;
    border: 1.5px solid #E2E8F0;
    border-radius: 12px;
    outline: none;
    transition: all 0.2s ease;
    caret-color: #0D9488;
}
.ho-otp-inputs input:focus {
    border-color: #0D9488;
    background: #FFF;
    box-shadow: 0 0 0 3px rgba(13,148,136,0.1);
    transform: scale(1.05);
}
.ho-otp-inputs input.otp-filled {
    border-color: #0D9488;
    background: #F0FDFA;
}
.ho-otp-inputs input.otp-error {
    border-color: #EF4444;
    background: #FFF5F5;
    animation: hoShake 0.4s ease;
}
@keyframes hoShake {
    0%, 100% { transform: translateX(0); }
    20% { transform: translateX(-4px); }
    40% { transform: translateX(4px); }
    60% { transform: translateX(-4px); }
    80% { transform: translateX(4px); }
}
.ho-otp-message {
    font-size: 13px;
    min-height: 20px;
    margin-bottom: 16px;
    transition: all 0.3s ease;
}
.ho-otp-message.error { color: #EF4444; }
.ho-otp-message.success { color: #0D9488; }
.ho-otp-resend {
    font-size: 13px; color: #64748B;
    margin-bottom: 8px;
}
.ho-otp-resend a {
    color: #0D9488; font-weight: 600;
    text-decoration: none;
    cursor: pointer;
}
.ho-otp-resend a:hover { text-decoration: underline; }
.ho-otp-resend a.disabled {
    color: #94A3B8;
    cursor: not-allowed;
    pointer-events: none;
}
.ho-otp-resend .countdown {
    color: #0D9488; font-weight: 600;
}
.ho-otp-success-check {
    display: none;
    width: 80px; height: 80px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #0D9488, #0EA5E9);
    border-radius: 50%;
    align-items: center;
    justify-content: center;
    animation: hoPopIn 0.4s cubic-bezier(0.175,0.885,0.32,1.275);
}
.ho-otp-success-check i {
    font-size: 36px; color: #FFF;
}
@keyframes hoPopIn {
    0% { transform: scale(0); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
/* ── intl-tel-input overrides ── */
body.register .iti {
    width: 100% !important;
    display: block !important;
}
body.register .iti input,
body.register .iti input[type="tel"],
body.register .iti__tel-input,
body.register #ho_phone_input {
    width: 100% !important;
    height: 46px !important;
    font-size: 14px !important;
    font-family: 'Inter', sans-serif !important;
    color: #1E293B !important;
    background: #F8FAFC !important;
    border: 1.5px solid #E2E8F0 !important;
    border-radius: 12px !important;
    outline: none !important;
    transition: all 0.2s ease !important;
    box-shadow: none !important;
    appearance: none !important;
    -webkit-appearance: none !important;
}
body.register .iti input:focus,
body.register .iti input[type="tel"]:focus,
body.register #ho_phone_input:focus {
    border-color: #0D9488 !important;
    background: #FFF !important;
    box-shadow: 0 0 0 3px rgba(13,148,136,0.1) !important;
}
body.register .iti__flag-container,
body.register .iti__country-container {
    border-radius: 12px 0 0 12px !important;
    height: 100% !important;
}
body.register .iti__selected-country,
body.register .iti__selected-flag {
    border-radius: 12px 0 0 12px !important;
    padding: 0 8px 0 14px !important;
    background: transparent !important;
    height: 100% !important;
    display: flex !important;
    align-items: center !important;
}
body.register .iti__selected-country:hover,
body.register .iti__selected-country:focus,
body.register .iti__selected-flag:hover,
body.register .iti__selected-flag:focus {
    background: #F0FDFA !important;
}
body.register .iti__selected-dial-code {
    font-size: 14px !important;
    font-family: 'Inter', sans-serif !important;
    color: #475569 !important;
    margin-left: 6px !important;
}
body.register .iti__arrow {
    border-left-color: #94A3B8 !important;
    border-right-color: #94A3B8 !important;
    border-top-color: #94A3B8 !important;
}
body.register .iti__country-list,
body.register .iti__dropdown-content {
    border-radius: 12px !important;
    border: 1.5px solid #E2E8F0 !important;
    box-shadow: 0 8px 24px rgba(0,0,0,0.1) !important;
    margin-top: 4px !important;
    max-height: 220px !important;
    font-family: 'Inter', sans-serif !important;
    font-size: 13px !important;
}
body.register .iti__country-list .iti__country {
    padding: 8px 12px !important;
}
body.register .iti__country-list .iti__country.iti__highlight,
body.register .iti__country-list .iti__country--highlight {
    background: #F0FDFA !important;
}
body.register .iti__country-list .iti__country-name {
    margin-right: 8px !important;
}
body.register .iti__search-input,
body.register .iti input.iti__search-input {
    border-radius: 8px !important;
    border: 1.5px solid #E2E8F0 !important;
    font-size: 13px !important;
    padding: 8px 12px !important;
    font-family: 'Inter', sans-serif !important;
    outline: none !important;
    height: auto !important;
    background: #FFF !important;
}
body.register .iti__search-input:focus,
body.register .iti input.iti__search-input:focus {
    border-color: #0D9488 !important;
    box-shadow: 0 0 0 3px rgba(13,148,136,0.1) !important;
}
</style>

<!-- intl-tel-input CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@24.6.0/build/css/intlTelInput.css">

<div class="ho-register-wrapper">
    <div class="ho-register-card">

        <!-- ── Header ── -->
        <div class="ho-register-header">
            <div class="ho-logo">
                <?= get_dark_company_logo(); ?>
            </div>
            <h1>Create Your Account</h1>
            <p>Get started with Clientcarex in minutes</p>
        </div>

        <!-- ── Stepper ── -->
        <div class="ho-stepper">
            <div class="ho-step-item active" id="ho-step-ind-1">
                <div class="ho-step-circle">1</div>
                <span class="ho-step-text">Your Details</span>
            </div>
            <div class="ho-step-line" id="ho-step-line-1"></div>
            <div class="ho-step-item" id="ho-step-ind-2">
                <div class="ho-step-circle">2</div>
                <span class="ho-step-text">Verification</span>
            </div>
            <div class="ho-step-line" id="ho-step-line-2" style="display:none;"></div>
            <div class="ho-step-item" id="ho-step-ind-3" style="display:none;">
                <div class="ho-step-circle">3</div>
                <span class="ho-step-text">OTP Confirm</span>
            </div>
        </div>

        <!-- ── Form ── -->
        <?= form_open('authentication/register', ['id' => 'register-form']); ?>

        <?php
        $ps_plan_key = function_exists('perfex_saas_route_id_prefix')
            ? perfex_saas_route_id_prefix('plan')
            : 'ps_plan';
        $ps_plan_val = $this->input->get($ps_plan_key, true)
            ?: ($this->session->{$ps_plan_key} ?? '');
        if (!empty($ps_plan_val)) : ?>
            <input type="hidden" name="<?= e($ps_plan_key); ?>" value="<?= e($ps_plan_val); ?>">
        <?php endif; ?>

        <div class="ho-register-body">

            <!-- ═══════════════════════════════════════════
                 STEP 1 — Your Details (all profile fields)
                 ═══════════════════════════════════════════ -->
            <div class="ho-step-panel active" id="ho-panel-1">

                <!-- Selected Plan Info -->
                <?php
                $ho_package = null;
                if (function_exists('perfex_saas_route_id_prefix')) {
                    $CI =& get_instance();
                    $ho_pkg_slug = $CI->session->{perfex_saas_route_id_prefix('plan')} ?? '';
                    if (!empty($ho_pkg_slug)) {
                        $CI->db->where('slug', $ho_pkg_slug);
                        $ho_pkg_row = $CI->perfex_saas_model->packages()[0] ?? [];
                    } else {
                        $CI->db->where('is_default', 1);
                        $ho_pkg_row = $CI->perfex_saas_model->packages()[0] ?? [];
                    }
                    if (!empty($ho_pkg_row)) $ho_package = (object)$ho_pkg_row;
                }
                ?>
                <?php if ($ho_package) :
                    $ho_is_lifetime = !empty($ho_package->metadata->is_liftetime_deal);
                    $ho_custom = ($ho_package->metadata->invoice->recurring ?? '') === 'custom';
                    $ho_interval = $ho_custom
                        ? ($ho_package->metadata->invoice->repeat_every_custom ?? 1)
                        : ($ho_package->metadata->invoice->recurring ?? 1);
                    $ho_interval_type = $ho_custom
                        ? (($ho_package->metadata->invoice->repeat_type_custom ?? 'month') . 's')
                        : 'months';
                    $ho_trial = (int)($ho_package->trial_period ?? 0);

                    // Compute GST-inclusive total (the price above is shown without tax)
                    $ho_taxes = $ho_package->metadata->invoice->taxname ?? [];
                    $ho_total_tax = 0;
                    $ho_tax_rate_total = 0;
                    if (!empty($ho_taxes) && is_array($ho_taxes)) {
                        foreach ($ho_taxes as $ho_tax) {
                            $ho_tax_parts = explode('|', $ho_tax);
                            $ho_tax_rate = (float)end($ho_tax_parts);
                            $ho_tax_rate_total += $ho_tax_rate;
                            $ho_total_tax += (($ho_tax_rate / 100) * $ho_package->price);
                        }
                    }
                    $ho_total_with_tax = (float)$ho_package->price + $ho_total_tax;
                    $ho_tax_rate_label = $ho_tax_rate_total > 0
                        ? rtrim(rtrim(number_format($ho_tax_rate_total, 2), '0'), '.')
                        : '';

                    // Human-friendly billing-cycle labels
                    $ho_unit_adverb = ['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'];
                    if ($ho_is_lifetime) {
                        $ho_cycle_chip  = 'Lifetime';
                        $ho_period_text = 'one-time payment';
                    } elseif ($ho_interval == 1) {
                        $ho_unit_single = rtrim($ho_interval_type, 's');
                        $ho_cycle_chip  = $ho_unit_adverb[$ho_unit_single] ?? ucfirst($ho_unit_single);
                        $ho_period_text = 'billed per ' . $ho_unit_single;
                    } else {
                        $ho_cycle_chip  = $ho_interval . ' ' . $ho_interval_type;
                        $ho_period_text = 'billed every ' . $ho_interval . ' ' . $ho_interval_type;
                    }
                ?>
                <div class="ho-plan-banner">
                    <div class="ho-plan-icon">
                        <i class="fa fa-cube"></i>
                    </div>
                    <div class="ho-plan-info">
                        <div class="ho-plan-name"><?= e($ho_package->name); ?></div>
                        <div class="ho-plan-meta">Selected plan<span class="ho-dot">&middot;</span><?= e($ho_cycle_chip); ?><?php if ($ho_trial > 0) { echo '<span class="ho-dot">&middot;</span>' . $ho_trial . '-day free trial'; } ?></div>
                    </div>
                    <div class="ho-plan-price">
                        <div class="ho-price-total"><?= app_format_money($ho_total_with_tax, get_base_currency()); ?></div>
                        <?php if ($ho_total_tax > 0) : ?>
                        <div class="ho-price-sub"><?= app_format_money($ho_package->price, get_base_currency()); ?> + <b><?= e($ho_tax_rate_label); ?>% GST</b></div>
                        <?php else : ?>
                        <div class="ho-price-sub"><?= e($ho_period_text); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Personal Info -->
                <div class="ho-section-label">
                    <i class="fa fa-user"></i> Personal Information
                </div>

                <div class="ho-row">
                    <!-- Full Name -->
                    <div class="ho-field register-fullname-group">
                        <label for="fullname"><span class="req">*</span> Full Name</label>
                        <input type="text" class="ho-input" name="fullname" id="fullname"
                            placeholder=""
                            value="<?= set_value('fullname', trim(set_value($fields['firstname']) . ' ' . set_value($fields['lastname']))); ?>">
                        <input type="hidden" name="<?= e($fields['firstname']); ?>" id="ho_firstname" value="<?= set_value($fields['firstname']); ?>">
                        <input type="hidden" name="<?= e($fields['lastname']); ?>" id="ho_lastname" value="<?= set_value($fields['lastname']); ?>">
                        <?= form_error($fields['firstname']); ?>
                        <?= form_error($fields['lastname']); ?>
                    </div>
                    <!-- Your Occupation (dropdown) -->
                    <div class="ho-field register-position-group">
                        <label for="occupation_select"><span class="req">*</span> Your Occupation</label>
                        <select class="ho-input" id="occupation_select">
                            <option value="" disabled selected>Select occupation…</option>
                            <option value="Managing Director" <?= set_value('title') == 'Managing Director' ? 'selected' : ''; ?>>Managing Director</option>
                            <option value="CEO" <?= set_value('title') == 'CEO' ? 'selected' : ''; ?>>CEO</option>
                            <option value="Working as Employer" <?= set_value('title') == 'Working as Employer' ? 'selected' : ''; ?>>Working as Employer</option>
                            <option value="Working as Employee" <?= set_value('title') == 'Working as Employee' ? 'selected' : ''; ?>>Working as Employee</option>
                            <option value="Working as Doctor" <?= set_value('title') == 'Working as Doctor' ? 'selected' : ''; ?>>Working as Doctor</option>
                            <option value="Other">Other (Please specify)</option>
                        </select>
                        <!-- Hidden input carries the final value to backend -->
                        <input type="hidden" name="title" id="title" value="<?= set_value('title'); ?>">
                        <div class="ho-other-input-wrap" id="ho-other-wrap">
                            <input type="text" class="ho-input" id="ho-other-occupation"
                                placeholder="Please specify your occupation…"
                                maxlength="100"
                                value="<?php
                                    $presetOccupations = ['Managing Director','CEO','Working as Employer','Working as Employee','Working as Doctor'];
                                    $titleVal = set_value('title');
                                    echo (!empty($titleVal) && !in_array($titleVal, $presetOccupations)) ? e($titleVal) : '';
                                ?>">
                        </div>
                        <?= form_error('title'); ?>
                    </div>
                </div>

                <!-- Organisation Details -->
                <div class="ho-section-label">
                    <i class="fa fa-building"></i> Organisation Details
                </div>

                <div class="ho-row">
                    <!-- Organisation Name -->
                    <div class="ho-field register-company-group">
                        <label for="<?= e($fields['company']); ?>">
                            <?php if (get_option('company_is_required') == 1) { ?><span class="req">*</span><?php } ?>
                            Organisation Name
                        </label>
                        <input type="text" class="ho-input"
                            name="<?= e($fields['company']); ?>"
                            id="<?= e($fields['company']); ?>"
                            placeholder=""
                            value="<?= set_value($fields['company']); ?>">
                        <?= form_error($fields['company']); ?>
                    </div>
                    <!-- Organisation Type -->
                    <div class="ho-field register-orgtype-group">
                        <label for="organisation_type"><span class="req">*</span> Organisation Type</label>
                        <select class="ho-input" name="organisation_type" id="organisation_type">
                            <option value="" disabled <?= set_select('organisation_type', '', true); ?>>Select type…</option>
                            <option value="Hospital" <?= set_select('organisation_type', 'Hospital'); ?>>Hospital</option>
                            <option value="Diagnostic Center" <?= set_select('organisation_type', 'Diagnostic Center'); ?>>Diagnostic Center</option>
                            <option value="Clinic" <?= set_select('organisation_type', 'Clinic'); ?>>Clinic</option>
                            <option value="Blood Bank" <?= set_select('organisation_type', 'Blood Bank'); ?>>Blood Bank</option>
                        </select>
                    </div>
                </div>

                <!-- Sub-domain ID placeholder — SaaS widget injects here -->
                <div class="ho-field ho-subdomain-placeholder register-saas-info-group" style="display:none;">
                </div>

                <!-- Location (auto-hidden when IP detects successfully) -->
                <div id="ho-location-section">
                    <div class="ho-section-label">
                        <i class="fa fa-map-marker"></i> Location
                    </div>

                    <!-- IP auto-detect notice -->
                    <div class="ho-ip-notice" id="ho-ip-notice">
                        <span class="ho-ip-spinner" id="ho-ip-spinner"></span>
                        <span id="ho-ip-status">Detecting your location…</span>
                    </div>

                    <div class="ho-row">
                        <!-- Country -->
                        <div class="ho-field register-country-group">
                            <label for="country">Country</label>
                            <select data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>"
                                data-live-search="true" name="country" class="form-control selectpicker" id="country">
                                <option value="">Select country…</option>
                                <?php foreach (get_all_countries() as $country) { ?>
                                <option value="<?= e($country['country_id']); ?>"
                                    <?php if (get_option('customer_default_country') == $country['country_id']) { echo ' selected'; } ?>
                                    <?= set_select('country', $country['country_id']); ?>><?= e($country['short_name']); ?></option>
                                <?php } ?>
                            </select>
                            <?= form_error('country'); ?>
                        </div>
                        <!-- State -->
                        <div class="ho-field register-state-group">
                            <label for="state">State</label>
                            <input type="text" class="ho-input" name="state" id="state"
                                placeholder="e.g. Maharashtra"
                                value="<?= set_value('state'); ?>">
                            <?= form_error('state'); ?>
                        </div>
                    </div>

                    <div class="ho-row">
                        <!-- City -->
                        <div class="ho-field register-city-group">
                            <label for="city">City</label>
                            <input type="text" class="ho-input" name="city" id="city"
                                placeholder="e.g. Mumbai"
                                value="<?= set_value('city'); ?>">
                            <?= form_error('city'); ?>
                        </div>
                        <!-- Language -->
                        <div class="ho-field register-language-group">
                            <label for="language">Language</label>
                            <?php if (! is_language_disabled()) { ?>
                            <select name="language" id="language" class="form-control selectpicker"
                                data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>"
                                data-live-search="true">
                                <?php $selected = (get_contact_language() != '') ? get_contact_language() : get_option('active_language'); ?>
                                <?php foreach ($this->app->get_available_languages() as $availableLanguage) { ?>
                                <option value="<?= e($availableLanguage); ?>"
                                    <?= ($availableLanguage == $selected) ? 'selected' : '' ?>><?= e(ucfirst($availableLanguage)); ?></option>
                                <?php } ?>
                            </select>
                            <?php } else { ?>
                            <input type="text" class="ho-input" value="English" disabled>
                            <?php } ?>
                        </div>
                    </div>
                </div><!-- #ho-location-section -->

            </div><!-- #ho-panel-1 -->


            <!-- ═══════════════════════════════════════════
                 STEP 2 — Verification (Email, Mobile, Password)
                 ═══════════════════════════════════════════ -->
            <div class="ho-step-panel" id="ho-panel-2">


                <div class="ho-section-label">
                    <i class="fa fa-envelope"></i> Contact Verification
                </div>

                <div class="ho-row">
                    <!-- Email ID -->
                    <div class="ho-field register-email-group">
                        <label for="<?= e($fields['email']); ?>"><span class="req">*</span> Email ID</label>
                        <input type="email" class="ho-input"
                            name="<?= e($fields['email']); ?>"
                            id="<?= e($fields['email']); ?>"
                            placeholder="you@example.com"
                            autocomplete="email"
                            value="<?= set_value($fields['email']); ?>">
                        <?= form_error($fields['email']); ?>
                    </div>
                    <!-- Mobile Number -->
                    <div class="ho-field register-contact-phone-group">
                        <label for="ho_phone_input"><span class="req">*</span> Mobile Number</label>
                        <input type="tel" id="ho_phone_input"
                            maxlength="10"
                            placeholder=""
                            autocomplete="tel-national"
                            value="<?= set_value('contact_phonenumber'); ?>">
                        <!-- Hidden field carries full international number to backend -->
                        <input type="hidden" name="contact_phonenumber" id="contact_phonenumber">
                        <?= form_error('contact_phonenumber'); ?>
                    </div>
                </div>

                <div class="ho-section-label">
                    <i class="fa fa-lock"></i> Set Password
                </div>

                <div class="ho-row">
                    <!-- Password -->
                    <div class="ho-field register-password-group">
                        <label for="password"><span class="req">*</span> Password</label>
                        <div class="ho-password-wrapper">
                            <input type="password" class="ho-input" name="password" id="password"
                                placeholder="Create a strong password"
                                autocomplete="new-password">
                            <div class="ho-pw-actions">
                                <button type="button" class="ho-pw-btn ho-generate-btn" onclick="hoGeneratePassword()" tabindex="-1" aria-label="Generate password" title="Generate strong password">
                                    <i class="fa fa-key"></i>
                                </button>
                                <button type="button" class="ho-pw-btn" onclick="hoTogglePw('password')" tabindex="-1" aria-label="Toggle password visibility">
                                    <i class="fa fa-eye" id="ho-eye-pw"></i>
                                </button>
                            </div>
                        </div>
                        <?= form_error('password'); ?>
                    </div>
                    <!-- Confirm Password -->
                    <div class="ho-field register-password-repeat-group">
                        <label for="passwordr"><span class="req">*</span> Confirm Password</label>
                        <div class="ho-password-wrapper">
                            <input type="password" class="ho-input" name="passwordr" id="passwordr"
                                placeholder="Re-enter your password"
                                autocomplete="new-password">
                            <div class="ho-pw-actions">
                                <button type="button" class="ho-pw-btn" onclick="hoTogglePw('passwordr')" tabindex="-1" aria-label="Toggle confirm password visibility">
                                    <i class="fa fa-eye" id="ho-eye-pwr"></i>
                                </button>
                            </div>
                        </div>
                        <div class="ho-pw-match" id="ho-pw-match-msg"></div>
                        <?= form_error('passwordr'); ?>
                    </div>
                </div>

                <!-- GDPR -->
                <?php if (is_gdpr() && get_option('gdpr_enable_terms_and_conditions') == 1) { ?>
                <div class="ho-gdpr-wrapper register-terms-and-conditions-wrapper">
                    <div class="checkbox">
                        <input type="checkbox" name="accept_terms_and_conditions" id="accept_terms_and_conditions"
                            <?= set_checkbox('accept_terms_and_conditions', 'on'); ?>>
                        <label for="accept_terms_and_conditions">
                            <?= _l('gdpr_terms_agree', terms_url()); ?>
                        </label>
                    </div>
                    <?= form_error('accept_terms_and_conditions'); ?>
                </div>
                <?php } ?>

                <!-- Recaptcha -->
                <?php if (show_recaptcha_in_customers_area()) { ?>
                <div class="ho-recaptcha-wrapper register-recaptcha">
                    <div class="g-recaptcha" data-sitekey="<?= get_option('recaptcha_site_key'); ?>"></div>
                    <?= form_error('g-recaptcha-response'); ?>
                </div>
                <?php } ?>

            </div><!-- #ho-panel-2 -->


            <!-- ═══════════════════════════════════════════
                 STEP 3 — OTP Verification (shown after registration)
                 ═══════════════════════════════════════════ -->
            <div class="ho-step-panel" id="ho-panel-3">
                <div class="ho-otp-wrapper" id="ho-otp-form-area">
                    <div class="ho-otp-icon">
                        <i class="fa fa-shield-alt"></i>
                    </div>
                    <div class="ho-otp-title">Verify Your Account</div>
                    <div class="ho-otp-subtitle">
                        We've sent a 4-digit verification code to<br>
                        <strong id="ho-otp-phone"><?= isset($otp_user_phone) ? e($otp_user_phone) : ''; ?></strong>
                        &amp; <strong id="ho-otp-email"><?= isset($otp_user_email) ? e($otp_user_email) : ''; ?></strong>
                    </div>
                    <div class="ho-otp-inputs" id="ho-otp-inputs">
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit" data-index="0" autocomplete="one-time-code" autofocus>
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit" data-index="1">
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit" data-index="2">
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit" data-index="3">
                    </div>
                    <div class="ho-otp-message" id="ho-otp-message"></div>
                    <div class="ho-otp-resend">
                        Didn't receive the code?
                        <a href="javascript:void(0)" id="ho-resend-otp">Resend OTP</a>
                        <span id="ho-resend-countdown" style="display:none;">
                            Resend in <span class="countdown" id="ho-countdown-val">60</span>s
                        </span>
                    </div>
                </div>
                <!-- Success state (replaces form after verification) -->
                <div class="ho-otp-wrapper" id="ho-otp-success-area" style="display:none;">
                    <div class="ho-otp-success-check" style="display:flex;">
                        <i class="fa fa-check"></i>
                    </div>
                    <div class="ho-otp-title" style="color:#0D9488;">Account Verified!</div>
                    <div class="ho-otp-subtitle">
                        Redirecting you to your dashboard…
                    </div>
                </div>
            </div>


            <!-- ── Hidden fields (removed from UI) ── -->
            <input type="hidden" name="website" value="">
            <input type="hidden" name="address" value="">
            <input type="hidden" name="zip" value="">
            <input type="hidden" name="phonenumber" value="">
            <?php if (get_option('company_requires_vat_number_field') == 1) { ?>
            <input type="hidden" name="vat" value="">
            <?php } ?>

            <div class="register-contact-custom-fields" style="display:none;">
                <?= render_custom_fields('contacts', '', ['show_on_client_portal' => 1]); ?>
            </div>
            <div class="register-company-custom-fields" style="display:none;">
                <?= render_custom_fields('customers', '', ['show_on_client_portal' => 1]); ?>
            </div>

            <!-- Honeypot -->
            <?php if ($honeypot) { ?>
            <label class="honey-element" for="firstname"></label>
            <input class="honey-element" autocomplete="off" type="text" id="firstname" name="firstname" placeholder="Your first name here">
            <label class="honey-element" for="lastname"></label>
            <input class="honey-element" autocomplete="off" type="text" id="lastname" name="lastname" placeholder="Your last name here">
            <label class="honey-element" for="email"></label>
            <input class="honey-element" autocomplete="off" type="email" id="email" name="email" placeholder="Your e-mail here">
            <label class="honey-element" for="company"></label>
            <input class="honey-element" autocomplete="off" type="text" id="company" name="company" placeholder="Your company here">
            <?php } ?>

        </div><!-- .ho-register-body -->

        <!-- ── Footer ── -->
        <div class="ho-register-footer">

            <!-- Step 1: Continue -->
            <div class="ho-btn-row" id="ho-footer-step1">
                <button type="button" class="ho-btn-primary" id="ho-btn-next" onclick="hoGoToStep(2)">
                    Continue <i class="fa fa-arrow-right"></i>
                </button>
            </div>

            <!-- Step 2: Back + Start Now -->
            <div class="ho-btn-row" id="ho-footer-step2" style="display:none;">
                <button type="button" class="ho-btn-back" onclick="hoGoToStep(1)">
                    <i class="fa fa-arrow-left"></i> Back
                </button>
                <button type="submit" class="ho-btn-primary" id="ho-register-btn"
                    data-loading-text="<?= _l('wait_text'); ?>">
                    <i class="fa fa-rocket"></i> Start Now
                </button>
            </div>

            <!-- Step 3: OTP Verify -->
            <div class="ho-btn-row" id="ho-footer-step3" style="display:none;">
                <button type="button" class="ho-btn-primary" id="ho-verify-otp-btn" style="width:100%;">
                    <i class="fa fa-shield"></i> Verify OTP
                </button>
            </div>

            <div class="ho-terms-text" id="ho-terms-text">
                By clicking on <strong>Start Now</strong>, you accept our
                <a href="<?= terms_url(); ?>" target="_blank">Subscription Agreement</a> and
                <a href="<?= site_url('privacy-policy'); ?>" target="_blank">Privacy Policy</a>.
            </div>

            <div class="ho-login-link" id="ho-login-link">
                Already have an account?
                <a href="<?= site_url('login'); ?>">Sign In</a>
            </div>
        </div>

        <?= form_close(); ?>

    </div><!-- .ho-register-card -->
</div><!-- .ho-register-wrapper -->


<!-- ═══════ JavaScript ═══════ -->
<script>
$(function() {

    // ═══════════════════════════════
    // OCCUPATION DROPDOWN + "OTHER"
    // ═══════════════════════════════
    var presetValues = ['Managing Director','CEO','Working as Employer','Working as Employee','Working as Doctor'];
    var $occSelect   = $('#occupation_select');
    var $otherWrap   = $('#ho-other-wrap');
    var $otherInput  = $('#ho-other-occupation');
    var $titleHidden = $('#title');

    // On load: if existing value isn't a preset, select "Other" and show input
    var currentTitle = $titleHidden.val();
    if (currentTitle && presetValues.indexOf(currentTitle) === -1) {
        $occSelect.val('Other');
        $otherInput.val(currentTitle);
        $otherWrap.addClass('visible');
    }

    $occSelect.on('change', function() {
        var val = $(this).val();
        if (val === 'Other') {
            $otherWrap.addClass('visible');
            $otherInput.focus();
            $titleHidden.val($otherInput.val());
        } else {
            $otherWrap.removeClass('visible');
            $otherInput.val('');
            $titleHidden.val(val);
        }
    });

    $otherInput.on('input', function() {
        $titleHidden.val($(this).val());
    });

    // Sync on initial load for preset selections
    if ($occSelect.val() && $occSelect.val() !== 'Other') {
        $titleHidden.val($occSelect.val());
    }


    // ═══════════════════════════════
    // STEP WIZARD
    // ═══════════════════════════════
    var currentStep = 1;

    // Auto-jump to correct step if server-side errors exist
    var hasStep1Errors = false, hasStep2Errors = false;
    $('#ho-panel-1 .text-danger').each(function() {
        if ($.trim($(this).text()).length > 0) {
            hasStep1Errors = true;
            $(this).closest('.ho-field').addClass('ho-field-error');
        }
    });
    $('#ho-panel-2 .text-danger').each(function() {
        if ($.trim($(this).text()).length > 0) {
            hasStep2Errors = true;
            $(this).closest('.ho-field').addClass('ho-field-error');
        }
    });
    window.hoGoToStep = function(step, skipValidation) {
        if (step === 2 && !skipValidation && !validateStep1()) return;

        // Track Step 1 completion via AJAX (save partial data)
        if (step === 2 && !skipValidation) {
            var honeypot = <?= json_encode(get_option('enable_honeypot_spam_validation') == 1); ?>;
            var companyField = honeypot ? 'companymjxw' : 'company';
            var trackData = {
                step: 1,
                [csrfData.token_name]: csrfData.token_hash
            };
            trackData[companyField] = $('#' + companyField).val() || $('[name="' + companyField + '"]').val() || '';

            // Also send plan slug if present
            var $planInput = $('input[name="<?= function_exists("perfex_saas_route_id_prefix") ? perfex_saas_route_id_prefix("plan") : "ps_plan"; ?>"]');
            if ($planInput.length) {
                trackData[$planInput.attr('name')] = $planInput.val();
            }

            $.ajax({
                url: '<?= site_url("authentication/track_signup_step"); ?>',
                type: 'POST',
                data: trackData,
                dataType: 'json',
                success: function(res) {
                    // Update CSRF token
                    if (res && res.csrf_token) {
                        csrfData.token_hash = res.csrf_token;
                    }
                }
                // Fire-and-forget — don't block step transition
            });
        }

        currentStep = step;
        $('.ho-step-panel').removeClass('active');
        $('#ho-panel-' + step).addClass('active');

        // Footer buttons
        $('#ho-footer-step1').toggle(step === 1);
        $('#ho-footer-step2').toggle(step === 2);
        $('#ho-footer-step3').toggle(step === 3);
        // Hide terms and login link on Step 3
        $('#ho-terms-text').toggle(step !== 3);
        $('#ho-login-link').toggle(step !== 3);

        // Stepper indicator
        if (step === 1) {
            $('#ho-step-ind-1').addClass('active').removeClass('completed');
            $('#ho-step-ind-1 .ho-step-circle').text('1');
            $('#ho-step-ind-2').removeClass('active completed');
            $('#ho-step-ind-2 .ho-step-circle').text('2');
            $('#ho-step-line-1').removeClass('filled');
            // Hide step 3 indicator
            $('#ho-step-line-2, #ho-step-ind-3').hide();
        } else if (step === 2) {
            $('#ho-step-ind-1').removeClass('active').addClass('completed');
            $('#ho-step-ind-1 .ho-step-circle').html('<i class="fa fa-check"></i>');
            $('#ho-step-ind-2').addClass('active').removeClass('completed');
            $('#ho-step-ind-2 .ho-step-circle').text('2');
            $('#ho-step-line-1').addClass('filled');
            // Hide step 3 indicator
            $('#ho-step-line-2, #ho-step-ind-3').hide();
        } else if (step === 3) {
            // Show all 3 step indicators
            $('#ho-step-line-2, #ho-step-ind-3').show();
            $('#ho-step-ind-1').removeClass('active').addClass('completed');
            $('#ho-step-ind-1 .ho-step-circle').html('<i class="fa fa-check"></i>');
            $('#ho-step-ind-2').removeClass('active').addClass('completed');
            $('#ho-step-ind-2 .ho-step-circle').html('<i class="fa fa-check"></i>');
            $('#ho-step-line-1').addClass('filled');
            $('#ho-step-line-2').addClass('filled');
            $('#ho-step-ind-3').addClass('active');
            // Focus first OTP input
            setTimeout(function() {
                $('#ho-otp-inputs .otp-digit:first').focus();
            }, 400);
        }

        // Scroll to card top
        $('html, body').animate({
            scrollTop: $('.ho-register-card').offset().top - 20
        }, 300);

        // Refresh bootstrap-select on step switch
        setTimeout(function() {
            $('#ho-panel-' + step + ' .selectpicker').selectpicker('refresh');
        }, 50);
    };

    if (hasStep2Errors && !hasStep1Errors) {
        hoGoToStep(2, true);
    }

    // ═══════════════════════════════
    // STEP 1 VALIDATION
    // ═══════════════════════════════
    function validateStep1() {
        var valid = true;
        var firstError = null;
        $('#ho-panel-1 .ho-field').removeClass('ho-field-error');

        // Full Name
        var $fn = $('#fullname');
        if ($.trim($fn.val()).length === 0) {
            markError($fn); valid = false;
            if (!firstError) firstError = $fn;
        }

        // Occupation
        var $occ = $('#occupation_select');
        if (!$occ.val()) {
            markError($occ); valid = false;
            if (!firstError) firstError = $occ;
        } else if ($occ.val() === 'Other' && $.trim($otherInput.val()).length === 0) {
            markError($otherInput); valid = false;
            if (!firstError) firstError = $otherInput;
        }

        // Organisation Type
        var $orgType = $('#organisation_type');
        if (!$orgType.val()) {
            markError($orgType); valid = false;
            if (!firstError) firstError = $orgType;
        }

        if (!valid && firstError) firstError.focus();
        return valid;
    }

    function markError($el) {
        $el.closest('.ho-field').addClass('ho-field-error');
        $el.one('input change', function() {
            $(this).closest('.ho-field').removeClass('ho-field-error');
        });
    }


    // ═══════════════════════════════
    // FULL NAME SPLITTER
    // ═══════════════════════════════
    function splitFullName() {
        var fullName = $.trim($('#fullname').val());
        var parts = fullName.split(/\s+/);
        if (parts.length === 1) {
            $('#ho_firstname').val(parts[0]);
            $('#ho_lastname').val('.');
        } else {
            var last = parts.pop();
            $('#ho_firstname').val(parts.join(' '));
            $('#ho_lastname').val(last);
        }
    }


    // ═══════════════════════════════
    // PASSWORD MATCH CHECKER
    // ═══════════════════════════════
    function checkPasswordMatch() {
        var pw  = $('#password').val();
        var pwr = $('#passwordr').val();
        var $msg = $('#ho-pw-match-msg');
        if (pwr.length === 0) {
            $msg.removeClass('show match mismatch');
            return;
        }
        if (pw === pwr) {
            $msg.text('✓ Passwords match').removeClass('mismatch').addClass('show match');
        } else {
            $msg.text('✗ Passwords do not match').removeClass('match').addClass('show mismatch');
        }
    }
    $('#password, #passwordr').on('input keyup', checkPasswordMatch);


    // ═══════════════════════════════
    // FORM SUBMIT
    // ═══════════════════════════════
    var $btn = $('#ho-register-btn');
    if ($btn.length && $btn.text().trim() === '<?= _l('wait_text'); ?>') {
        $btn.button('reset');
    }

    $('#register-form').on('submit', function() {
        splitFullName();

        // Sync full international phone number
        if (window.hoPhoneIti) {
            $('#contact_phonenumber').val(window.hoPhoneIti.getNumber());
        }

        // Sync occupation
        if ($occSelect.val() === 'Other') {
            $titleHidden.val($.trim($otherInput.val()));
        } else {
            $titleHidden.val($occSelect.val());
        }

        var $submitBtn = $(this).find('#ho-register-btn');
        if ($submitBtn.prop('disabled')) return false;
        $submitBtn.prop('disabled', true);
        $submitBtn.html('<i class="fa fa-spinner fa-spin"></i> Please wait…');

        setTimeout(function() {
            $submitBtn.prop('disabled', false);
            $submitBtn.html('<i class="fa fa-rocket"></i> Start Now');
        }, 30000);
    });


    // ═══════════════════════════════
    // IP GEOLOCATION
    // ═══════════════════════════════
    (function detectLocation() {
        var $section = $('#ho-location-section');
        var $notice  = $('#ho-ip-notice');
        var $spinner = $('#ho-ip-spinner');
        var $status  = $('#ho-ip-status');

        // If fields already have values from server-side repopulation, keep section hidden
        var stateEmpty   = $.trim($('#state').val()).length === 0;
        var cityEmpty    = $.trim($('#city').val()).length === 0;
        var countryEmpty = $('#country').val() === '' || $('#country').val() === null;

        if (!stateEmpty && !cityEmpty && !countryEmpty) {
            $section.hide();
            return;
        }

        $.ajax({
            url: 'https://ipapi.co/json/',
            type: 'GET',
            dataType: 'json',
            timeout: 8000,
            success: function(data) {
                if (data && !data.error) {
                    var gotState = false, gotCity = false, gotCountry = false;

                    if (data.region) {
                        $('#state').val(data.region);
                        gotState = true;
                    }
                    if (data.city) {
                        $('#city').val(data.city);
                        gotCity = true;
                    }
                    if (data.country_name) {
                        var cn = data.country_name.toLowerCase();
                        var matched = false;
                        $('#country option').each(function() {
                            if ($(this).text().trim().toLowerCase() === cn) {
                                $(this).prop('selected', true);
                                matched = true;
                                return false;
                            }
                        });
                        if (!matched) {
                            var word = cn.split(' ')[0];
                            $('#country option').each(function() {
                                if ($(this).text().trim().toLowerCase().indexOf(word) !== -1) {
                                    $(this).prop('selected', true);
                                    matched = true;
                                    return false;
                                }
                            });
                        }
                        if (matched) {
                            $('#country').selectpicker('refresh');
                            gotCountry = true;
                        }
                    }

                    // If all three were auto-filled, hide the entire location section
                    if (gotState && gotCity && gotCountry) {
                        $section.hide();
                    } else {
                        // Partial detection — show section for user to complete
                        $notice.hide();
                    }

                    // Set intl-tel-input country from detected country code
                    if (data.country_code && window.hoPhoneIti) {
                        window.hoPhoneIti.setCountry(data.country_code.toLowerCase());
                    }
                } else {
                    showFallback();
                }
            },
            error: showFallback
        });

        function showFallback() {
            $spinner.hide();
            $status.html('<i class="fa fa-info-circle"></i> Could not detect location. Please fill manually.');
            setTimeout(function() { $notice.fadeOut(400); }, 3000);
        }
    })();


    // ═══════════════════════════════
    // LANGUAGE CHANGE
    // ═══════════════════════════════
    $('#language').on('change', function() {
        var lang = $(this).val();
        if (lang) {
            window.location.href = '<?= site_url('authentication/change_language/'); ?>' + lang;
        }
    });


    // ═══════════════════════════════
    // OTP AUTO-DETECT (from session flag)
    // ═══════════════════════════════
    <?php if (!empty($otp_verification_pending)) : ?>
    (function() {
        // Jump directly to Step 3
        hoGoToStep(3, true);
        // Hide the form (Steps 1 & 2 are already submitted)
        $('#register-form').attr('action', 'javascript:void(0)');
        // Start cooldown timer immediately (OTP was just sent)
        if (typeof window.hoStartOtpCooldown === 'function') {
            window.hoStartOtpCooldown(60);
        }
    })();
    <?php endif; ?>

});

// Toggle password visibility for any password field
function hoTogglePw(fieldId) {
    var pwd = document.getElementById(fieldId);
    var iconId = fieldId === 'password' ? 'ho-eye-pw' : 'ho-eye-pwr';
    var icon = document.getElementById(iconId);
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Generate strong random password
function hoGeneratePassword() {
    var upper  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    var lower  = 'abcdefghijklmnopqrstuvwxyz';
    var digits = '0123456789';
    var symbols = '!@#$%&*';
    var all = upper + lower + digits + symbols;
    var len = 14;

    // Ensure at least one of each type
    var pw = '';
    pw += upper.charAt(Math.floor(Math.random() * upper.length));
    pw += lower.charAt(Math.floor(Math.random() * lower.length));
    pw += digits.charAt(Math.floor(Math.random() * digits.length));
    pw += symbols.charAt(Math.floor(Math.random() * symbols.length));

    for (var i = pw.length; i < len; i++) {
        pw += all.charAt(Math.floor(Math.random() * all.length));
    }

    // Shuffle
    pw = pw.split('').sort(function() { return 0.5 - Math.random(); }).join('');

    // Set both fields
    $('#password').val(pw).attr('type', 'text');
    $('#passwordr').val(pw).attr('type', 'text');

    // Update eye icons to show "slash" (visible state)
    $('#ho-eye-pw').removeClass('fa-eye').addClass('fa-eye-slash');
    $('#ho-eye-pwr').removeClass('fa-eye').addClass('fa-eye-slash');

    // Trigger match check
    $('#passwordr').trigger('input');

    // Brief highlight animation
    $('#password, #passwordr').css('background', '#F0FDFA');
    setTimeout(function() {
        $('#password, #passwordr').css('background', '');
    }, 1200);
}
</script>

<!-- intl-tel-input JS -->
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@24.6.0/build/js/intlTelInput.min.js"></script>
<script>
// Initialize intl-tel-input on phone field
(function() {
    var phoneInput = document.getElementById('ho_phone_input');
    if (!phoneInput) return;

    var iti = window.intlTelInput(phoneInput, {
        initialCountry: 'in',
        preferredCountries: ['in', 'us', 'gb', 'ae', 'sa'],
        separateDialCode: true,
        formatOnDisplay: false,
        nationalMode: true,
        autoPlaceholder: 'off',
        countrySearch: true,
        containerClass: 'iti',
        loadUtilsOnInit: 'https://cdn.jsdelivr.net/npm/intl-tel-input@24.6.0/build/js/utils.js'
    });

    // Store globally so IP detection & submit can access it
    window.hoPhoneIti = iti;

    // Enforce digits only + 10-digit max
    phoneInput.addEventListener('input', function() {
        var cleaned = this.value.replace(/[^0-9]/g, '');
        if (cleaned.length > 10) cleaned = cleaned.substring(0, 10);
        this.value = cleaned;
    });

    // If there's a pre-existing value (validation error reload), strip country code
    var existing = phoneInput.value;
    if (existing) {
        var digitsOnly = existing.replace(/[^0-9]/g, '');
        if (digitsOnly.length > 10) {
            digitsOnly = digitsOnly.substring(digitsOnly.length - 10);
        }
        phoneInput.value = digitsOnly;
    }
})();
</script>

<!-- OTP Verification JS -->
<script>
(function() {
    var $digits = $('.otp-digit');
    var $msg    = $('#ho-otp-message');
    var $btn    = $('#ho-verify-otp-btn');
    var csrfName  = '<?= $this->security->get_csrf_token_name(); ?>';
    var csrfHash  = '<?= $this->security->get_csrf_hash(); ?>';
    var verifyUrl = '<?= site_url("verification/verify_otp"); ?>';
    var resendUrl = '<?= site_url("verification/resend_otp"); ?>';

    // ── OTP Input Behavior ──
    $digits.on('input', function() {
        var $this = $(this);
        var val = $this.val().replace(/[^0-9]/g, '');
        $this.val(val);

        if (val.length === 1) {
            $this.addClass('otp-filled').removeClass('otp-error');
            // Auto-advance to next
            var idx = parseInt($this.data('index'));
            if (idx < 3) {
                $digits.eq(idx + 1).focus();
            }
        } else {
            $this.removeClass('otp-filled');
        }
        $msg.text('').removeClass('error success');
    });

    $digits.on('keydown', function(e) {
        var idx = parseInt($(this).data('index'));
        // Backspace: clear current, go to previous
        if (e.key === 'Backspace' && !$(this).val() && idx > 0) {
            $digits.eq(idx - 1).val('').removeClass('otp-filled').focus();
        }
        // Left arrow
        if (e.key === 'ArrowLeft' && idx > 0) {
            e.preventDefault();
            $digits.eq(idx - 1).focus();
        }
        // Right arrow
        if (e.key === 'ArrowRight' && idx < 3) {
            e.preventDefault();
            $digits.eq(idx + 1).focus();
        }
    });

    // Paste support: paste 4 digits at once
    $digits.first().on('paste', function(e) {
        e.preventDefault();
        var pasted = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
        var nums = pasted.replace(/[^0-9]/g, '');
        for (var i = 0; i < 4 && i < nums.length; i++) {
            $digits.eq(i).val(nums[i]).addClass('otp-filled');
        }
        if (nums.length >= 4) {
            $digits.eq(3).focus();
        }
    });

    // ── Get OTP value ──
    function getOtp() {
        var otp = '';
        $digits.each(function() { otp += $(this).val(); });
        return otp;
    }

    // ── Verify OTP ──
    function verifyOtp() {
        var otp = getOtp();
        if (otp.length !== 4) {
            $msg.text('Please enter all 4 digits.').addClass('error').removeClass('success');
            $digits.filter(function() { return !$(this).val(); }).addClass('otp-error');
            return;
        }

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Verifying…');
        $msg.text('').removeClass('error success');

        var postData = { otp: otp };
        postData[csrfName] = csrfHash;

        $.ajax({
            url: verifyUrl,
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(res) {
                // Update CSRF token for next request
                if (res.csrf_token) csrfHash = res.csrf_token;

                if (res.success) {
                    $msg.text(res.message).addClass('success').removeClass('error');
                    // Show success animation
                    $('#ho-otp-form-area').fadeOut(300, function() {
                        $('#ho-otp-success-area').fadeIn(300);
                    });
                    $('#ho-footer-step3').fadeOut(200);
                    // Redirect after brief delay
                    setTimeout(function() {
                        window.location.href = res.redirect || '<?= site_url("clients"); ?>';
                    }, 2000);
                } else {
                    $msg.text(res.message).addClass('error').removeClass('success');
                    $digits.addClass('otp-error');
                    setTimeout(function() { $digits.removeClass('otp-error'); }, 600);
                    $btn.prop('disabled', false).html('<i class="fa fa-shield"></i> Verify OTP');

                    if (res.expired) {
                        // Clear inputs on expiry
                        $digits.val('').removeClass('otp-filled');
                        $digits.first().focus();
                    }
                }
            },
            error: function() {
                $msg.text('Network error. Please try again.').addClass('error');
                $btn.prop('disabled', false).html('<i class="fa fa-shield"></i> Verify OTP');
            }
        });
    }

    // Verify button click
    $btn.on('click', verifyOtp);

    // Enter key on last digit triggers verify
    $digits.last().on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            verifyOtp();
        }
    });

    // ── Resend OTP with Cooldown ──
    var $resendLink = $('#ho-resend-otp');
    var $countdown  = $('#ho-resend-countdown');
    var $countVal   = $('#ho-countdown-val');
    var cooldownTimer = null;

    window.hoStartOtpCooldown = function(seconds) {
        var remaining = seconds;
        $resendLink.hide();
        $countdown.show();
        $countVal.text(remaining);

        if (cooldownTimer) clearInterval(cooldownTimer);
        cooldownTimer = setInterval(function() {
            remaining--;
            $countVal.text(remaining);
            if (remaining <= 0) {
                clearInterval(cooldownTimer);
                $countdown.hide();
                $resendLink.show();
            }
        }, 1000);
    };

    $resendLink.on('click', function() {
        var postData = {};
        postData[csrfName] = csrfHash;

        $resendLink.text('Sending…');

        $.ajax({
            url: resendUrl,
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(res) {
                if (res.csrf_token) csrfHash = res.csrf_token;

                if (res.success) {
                    $msg.text(res.message).addClass('success').removeClass('error');
                    $digits.val('').removeClass('otp-filled otp-error');
                    $digits.first().focus();
                    window.hoStartOtpCooldown(res.cooldown || 60);
                } else {
                    $msg.text(res.message).addClass('error').removeClass('success');
                    if (res.cooldown) {
                        window.hoStartOtpCooldown(res.cooldown);
                    }
                }
                $resendLink.text('Resend OTP');
            },
            error: function() {
                $msg.text('Network error. Please try again.').addClass('error');
                $resendLink.text('Resend OTP');
            }
        });
    });

    // Start initial cooldown if OTP was just sent (page loaded with OTP pending)
    <?php if (!empty($otp_verification_pending)) : ?>
    window.hoStartOtpCooldown(60);
    <?php endif; ?>
})();
</script>