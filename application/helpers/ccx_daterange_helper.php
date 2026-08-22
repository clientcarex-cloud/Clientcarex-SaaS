<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CCX Date Range Picker
 *
 * Renders a single modern date-range field (presets sidebar + dual month
 * calendar) that posts TWO hidden inputs, so existing controllers keep
 * reading their usual from/to params. Values are always Y-m-d, which
 * to_sql_date() passes through untouched.
 *
 * Usage:
 *   echo render_date_range_input('from_date', 'to_date', $from, $to);
 *   echo render_date_range_input('from_date_doc', 'to_date_doc', $f, $t, [
 *       'from_id' => 'from_date_doc',   // id for the hidden from-input
 *       'to_id'   => 'to_date_doc',     // id for the hidden to-input
 *       'label'   => 'Date range',      // placeholder when nothing selected
 *       'align'   => 'left',            // panel alignment: left|right
 *       'id'      => 'my_wrapper_id',   // wrapper id (auto-generated otherwise)
 *       'allow_future' => true,         // future dates selectable (default: blocked)
 *   ]);
 *
 * On selection the picker fires a native (bubbling) 'change' event on both
 * hidden inputs plus 'ccx:daterange:change' on the wrapper, so pages that
 * auto-apply filters on change keep working. If a page clears the hidden
 * inputs programmatically, call CcxDateRange.sync(wrapperEl) to refresh
 * the visible label.
 */
function render_date_range_input($from_name, $to_name, $from_value = '', $to_value = '', $options = [])
{
    static $instance = 0;
    static $assets_added = false;

    $instance++;

    $wrapper_id = isset($options['id']) ? $options['id'] : 'ccx_drp_' . $instance;
    $from_id    = isset($options['from_id']) ? $options['from_id'] : $wrapper_id . '_from';
    $to_id      = isset($options['to_id']) ? $options['to_id'] : $wrapper_id . '_to';
    $label      = isset($options['label']) ? $options['label'] : 'Date range';
    $align      = isset($options['align']) && $options['align'] === 'right' ? ' ccx-drp-align-right' : '';
    $class      = isset($options['class']) ? ' ' . $options['class'] : '';
    $future     = !empty($options['allow_future']) ? ' data-allow-future="1"' : '';

    // Normalize incoming values to Y-m-d (pages may pass site-format dates)
    $from_value = $from_value ? to_sql_date($from_value) : '';
    $to_value   = $to_value ? to_sql_date($to_value) : '';

    $html = '';

    if (!$assets_added) {
        $assets_added = true;
        $v = '?v=' . (defined('CCX_DATERANGE_VERSION') ? CCX_DATERANGE_VERSION : '1.0.2');
        $html .= '<link rel="stylesheet" href="' . base_url('assets/css/ccx_daterange.css') . $v . '">';
        // Vanilla JS on purpose: admin pages load jQuery in the footer, this
        // tag can appear anywhere in the body and still init correctly
        $html .= '<script src="' . base_url('assets/js/ccx_daterange.js') . $v . '"></script>';
    }

    $html .= '<div class="ccx-drp' . $align . $class . '" id="' . html_escape($wrapper_id) . '" data-label="' . html_escape($label) . '"' . $future . '>';
    $html .= '<input type="hidden" class="ccx-drp-from" name="' . html_escape($from_name) . '" id="' . html_escape($from_id) . '" value="' . html_escape($from_value) . '">';
    $html .= '<input type="hidden" class="ccx-drp-to" name="' . html_escape($to_name) . '" id="' . html_escape($to_id) . '" value="' . html_escape($to_value) . '">';
    $html .= '<button type="button" class="btn ccx-drp-toggle">'
        . '<i class="fa fa-calendar ccx-drp-cal-ico"></i>'
        . '<span class="ccx-drp-label">' . html_escape($label) . '</span>'
        . '<i class="fa fa-angle-down ccx-drp-caret"></i>'
        . '</button>';
    $html .= '<div class="ccx-drp-panel">'
        . '<div class="ccx-drp-presets"></div>'
        . '<div class="ccx-drp-cals">'
        . '<div class="ccx-drp-cal-row"><div class="ccx-drp-cal-left"></div><div class="ccx-drp-cal-right"></div></div>'
        . '<div class="ccx-drp-footer"><span class="ccx-drp-hint"></span><a href="#" class="ccx-drp-clear">Clear dates</a></div>'
        . '</div>'
        . '</div>';
    $html .= '</div>';

    return $html;
}
