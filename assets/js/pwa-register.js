/**
 * HealthO Pro — PWA Service Worker Registration
 * ===============================================
 * This script registers the service worker, handles updates,
 * and manages the lifecycle for background cache refreshes.
 *
 * Key safeguards:
 * - Reload-loop guard via sessionStorage
 * - Non-blocking registration (deferred until after page load)
 * - Graceful error handling for all SW operations
 */
(function () {
  'use strict';

  if (!('serviceWorker' in navigator)) {
    console.log('[PWA] Service Workers not supported in this browser.');
    return;
  }

  // ─── Reload-Loop Guard ─────────────────────────────────────────────
  // Prevents infinite reload loops when a new SW activates and triggers
  // controllerchange → reload → controllerchange → reload...
  var RELOAD_GUARD_KEY = 'pwa_reload_guard';
  var RELOAD_COOLDOWN_MS = 5000; // 5-second cooldown between reloads

  function canReload() {
    var lastReload = sessionStorage.getItem(RELOAD_GUARD_KEY);
    if (!lastReload) return true;
    return (Date.now() - parseInt(lastReload, 10)) > RELOAD_COOLDOWN_MS;
  }

  function markReload() {
    sessionStorage.setItem(RELOAD_GUARD_KEY, Date.now().toString());
  }

  // ─── Registration ──────────────────────────────────────────────────
  window.addEventListener('load', function () {
    navigator.serviceWorker
      .register('/sw.js', { scope: '/' })
      .then(function (registration) {
        console.log('[PWA] Service Worker registered with scope:', registration.scope);

        // Check for updates periodically (every 60 minutes)
        setInterval(function () {
          registration.update().catch(function () {
            // Silently ignore update check failures (e.g., offline)
          });
        }, 60 * 60 * 1000);

        // Handle SW updates
        registration.addEventListener('updatefound', function () {
          var newWorker = registration.installing;
          if (!newWorker) return;

          console.log('[PWA] New Service Worker version detected, installing...');

          newWorker.addEventListener('statechange', function () {
            if (newWorker.state === 'installed') {
              if (navigator.serviceWorker.controller) {
                // New SW installed while old one is still active
                console.log('[PWA] New version available! Activating immediately...');

                // Auto-activate the new service worker
                newWorker.postMessage({ type: 'SKIP_WAITING' });

                // Show a subtle notification to the user
                showUpdateNotification();
              } else {
                // First-time install — content is now cached for offline
                console.log('[PWA] Content cached for offline use.');
              }
            }
          });
        });
      })
      .catch(function (error) {
        console.error('[PWA] Service Worker registration failed:', error);
      });

    // Refresh page when new SW takes over — with reload-loop guard
    var refreshing = false;
    navigator.serviceWorker.addEventListener('controllerchange', function () {
      if (refreshing) return;
      if (!canReload()) {
        console.log('[PWA] Skipping reload — cooldown active to prevent loop.');
        return;
      }
      refreshing = true;
      markReload();
      console.log('[PWA] New Service Worker activated, refreshing page...');
      window.location.reload();
    });
  });

  /**
   * Show a subtle toast notification when a new version is available.
   * Non-intrusive — auto-dismisses after 5 seconds.
   */
  function showUpdateNotification() {
    // Don't show on initial load or if page isn't fully rendered
    if (!document.querySelector('#wrapper, .app.admin, .customers')) {
      return;
    }

    // Prevent duplicate toasts
    if (document.getElementById('pwa-update-toast')) {
      return;
    }

    var toast = document.createElement('div');
    toast.id = 'pwa-update-toast';
    toast.innerHTML =
      '<div style="' +
      'position:fixed;bottom:24px;right:24px;z-index:99999;' +
      'background:linear-gradient(135deg,#1B74E4,#0D5BB5);color:#fff;' +
      'padding:14px 22px;border-radius:12px;font-family:Inter,system-ui,sans-serif;' +
      'font-size:14px;font-weight:500;box-shadow:0 8px 32px rgba(27,116,228,0.35);' +
      'display:flex;align-items:center;gap:10px;' +
      'animation:pwa-slide-in 0.3s ease-out;' +
      '">' +
      '<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
      '<path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>' +
      '</svg>' +
      '<span>App updated! Refreshing\u2026</span>' +
      '</div>';

    // Inject animation keyframes (only once)
    if (!document.getElementById('pwa-slide-in-style')) {
      var style = document.createElement('style');
      style.id = 'pwa-slide-in-style';
      style.textContent =
        '@keyframes pwa-slide-in { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }';
      document.head.appendChild(style);
    }
    document.body.appendChild(toast);

    // Auto-dismiss after 5 seconds
    setTimeout(function () {
      if (toast.parentNode) {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(function () {
          if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
          }
        }, 300);
      }
    }, 5000);
  }
})();
