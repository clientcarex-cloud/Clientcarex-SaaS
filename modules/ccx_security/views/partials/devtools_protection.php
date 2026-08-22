<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * DevTools Protection JavaScript
 * Injected via app_admin_footer hook when ccx_security_devtools_block_enabled = 1
 * OR when ccx_security_right_click_block = 1 (independently)
 */

$devtools_enabled = get_option('ccx_security_devtools_block_enabled') === '1';
$block_right_click = get_option('ccx_security_right_click_block') === '1';
$inspect_message = get_option('ccx_security_inspect_message') ?: 'Developer tools are disabled for security reasons.';
$inspect_message = htmlspecialchars($inspect_message, ENT_QUOTES, 'UTF-8');
?>
<script>
(function() {
    'use strict';

    var _msg = '<?php echo $inspect_message; ?>';
    var _blockRightClick = <?php echo $block_right_click ? 'true' : 'false'; ?>;
    var _devtoolsEnabled = <?php echo $devtools_enabled ? 'true' : 'false'; ?>;
    var _devtoolsOpen = false;
    var _threshold = 160;

    // ─── Show warning toast ───
    var _warningTimeout = null;
    function _showWarning() {
        if (_warningTimeout) return;

        if (typeof alert_float !== 'undefined') {
            alert_float('warning', _msg);
        } else {
            var toast = document.createElement('div');
            toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:999999;background:#dc3545;color:#fff;padding:16px 24px;border-radius:8px;font-size:14px;font-weight:500;box-shadow:0 4px 20px rgba(0,0,0,0.3);animation:ccx-sec-slide 0.3s ease;max-width:400px;';
            toast.textContent = _msg;

            var style = document.createElement('style');
            style.textContent = '@keyframes ccx-sec-slide{from{opacity:0;transform:translateX(100px)}to{opacity:1;transform:translateX(0)}}';
            document.head.appendChild(style);
            document.body.appendChild(toast);

            setTimeout(function() {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s';
                setTimeout(function() { toast.remove(); style.remove(); }, 300);
            }, 3000);
        }

        _warningTimeout = setTimeout(function() { _warningTimeout = null; }, 3000);
    }

    // ─── Block right-click (independent of DevTools toggle) ───
    if (_blockRightClick) {
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            _showWarning();
            return false;
        });
    }

    // ─── DevTools-specific protections (only when DevTools block is enabled) ───
    if (_devtoolsEnabled) {

        // Block keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // F12
            if (e.key === 'F12' || e.keyCode === 123) {
                e.preventDefault();
                e.stopPropagation();
                _showWarning();
                return false;
            }

            // Ctrl+Shift+I / Cmd+Option+I (Inspector)
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.keyCode === 73)) {
                e.preventDefault();
                e.stopPropagation();
                _showWarning();
                return false;
            }

            // Ctrl+Shift+J / Cmd+Option+J (Console)
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'J' || e.key === 'j' || e.keyCode === 74)) {
                e.preventDefault();
                e.stopPropagation();
                _showWarning();
                return false;
            }

            // Ctrl+Shift+C / Cmd+Option+C (Element picker)
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'C' || e.key === 'c' || e.keyCode === 67)) {
                e.preventDefault();
                e.stopPropagation();
                _showWarning();
                return false;
            }

            // Ctrl+U / Cmd+U (View source)
            if ((e.ctrlKey || e.metaKey) && (e.key === 'U' || e.key === 'u' || e.keyCode === 85)) {
                e.preventDefault();
                e.stopPropagation();
                _showWarning();
                return false;
            }

            // Ctrl+Shift+K (Firefox Console)
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'K' || e.key === 'k' || e.keyCode === 75)) {
                e.preventDefault();
                e.stopPropagation();
                _showWarning();
                return false;
            }

            // Ctrl+Shift+M (Responsive design mode)
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'M' || e.key === 'm' || e.keyCode === 77)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        }, true);

        // DevTools open detection via window size
        function _checkDevTools() {
            var widthThreshold = window.outerWidth - window.innerWidth > _threshold;
            var heightThreshold = window.outerHeight - window.innerHeight > _threshold;

            if (widthThreshold || heightThreshold) {
                if (!_devtoolsOpen) {
                    _devtoolsOpen = true;
                    _showWarning();
                }
            } else {
                _devtoolsOpen = false;
            }
        }

        // Disable console methods
        var _noop = function() {};
        try {
            Object.defineProperty(window, 'console', {
                get: function() {
                    return {
                        log: _noop, warn: _noop, error: _noop, info: _noop,
                        debug: _noop, dir: _noop, table: _noop, trace: _noop,
                        assert: _noop, count: _noop, group: _noop, groupEnd: _noop,
                        time: _noop, timeEnd: _noop, clear: _noop
                    };
                },
                set: _noop,
                configurable: false
            });
        } catch(e) {
            // Some browsers don't allow redefining console
        }

        // Run checks periodically
        setInterval(_checkDevTools, 2000);

        // Disable drag/drop of page elements
        document.addEventListener('dragstart', function(e) {
            if (e.target.tagName !== 'IMG' && !e.target.closest('[draggable="true"]')) {
                e.preventDefault();
            }
        });

        // Block copy/paste from source code
        document.addEventListener('copy', function(e) {
            var tag = (e.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || e.target.isContentEditable) {
                return;
            }
        });
    }

})();
</script>
