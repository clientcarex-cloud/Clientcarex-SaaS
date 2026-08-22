<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * This hook file add functionality of adding custom JS and CSS codes.
 */

hooks()->add_filter('perfex_saas_settings_tab', function ($tabs) {
    $tabs[] = ['id' => 'custom_codes', 'label' => _l('perfex_saas_settings_custom_codes')];
    return $tabs;
});

if (defined('SAAS_DEMO_SITE')) {

    hooks()->add_action('perfex_saas_after_settings_tab', function ($tab) {


        if (!is_admin() || $tab['id'] !== 'custom_codes') return;

        echo '<div class="alert alert-danger">⚠️ Disabled in Demo</div>';
    });

    return;
}

/**
 * Render custom codes UI
 */
hooks()->add_action('perfex_saas_after_settings_tab', function ($tab) {

    if (!is_admin() || $tab['id'] !== 'custom_codes') return;

    $stored = base64_decode(get_option('perfex_saas_ps_global_custom_codes') ?? '');
    $codes  = $stored ? json_decode($stored, true) : [];

    echo '<div id="custom-codes-repeater">';
    echo '<table class="table table-bordered">';
    echo '<thead><tr>
            <th>' . _l('perfex_saas_custom_code_context') . '</th>
            <th>' . _l('perfex_saas_custom_code_type') . '</th>
            <th>' . _l('perfex_saas_custom_code_snippet') . '</th>
            <th></th>
          </tr></thead><tbody>';

    if (!empty($codes)) {
        foreach ($codes as $context => $types) {
            foreach ($types as $type => $snippets) {
                foreach ($snippets as $snippet) {
                    echo perfex_saas_render_repeater_row($context, $type, $snippet);
                }
            }
        }
    } else {
        // One empty row
        echo perfex_saas_render_repeater_row();
    }

    echo '</tbody></table>';
    echo '<button type="button" class="btn btn-primary" id="add-code-row"><i class="fa fa-plus"></i> ' . _l('add_new') . '</button>';
    echo '</div>';

    // Hidden input to store JSON
    echo '<input type="hidden" name="perfex_saas_ps_global_custom_codes" id="perfex_saas_ps_global_custom_codes_storage" value="' . $stored . '">';

    echo '<br/><br/><div class="alert alert-warning">⚠️ ' . _l('perfex_saas_custom_code_save_warning') . '</div>';
    echo render_input('perfex_saas_ps_global_custom_codes_admin_password');
?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            function updateStorage() {
                let data = {};
                $('#custom-codes-repeater tbody tr').each(function() {
                    let context = $(this).find('.context').val();
                    let type = $(this).find('.type').val();
                    let code = $(this).find('.snippet').val();

                    if (!context || !type || !code) return;

                    if (!data[context]) data[context] = {};
                    if (!data[context][type]) data[context][type] = [];
                    data[context][type].push(code);
                });
                $('#perfex_saas_ps_global_custom_codes_storage').val(btoa(JSON.stringify(data)));
            }

            // Bind update
            $(document).on('change keyup',
                '#custom-codes-repeater input, #custom-codes-repeater select, #custom-codes-repeater textarea',
                updateStorage);

            // Add row
            $('#add-code-row').on('click', function() {
                let row = <?php echo json_encode(perfex_saas_render_repeater_row('', '', '', true)); ?>;
                $('#custom-codes-repeater tbody').append(row);
            });

            // Remove row
            $(document).on('click', '.remove-row', function() {
                $(this).closest('tr').remove();
                updateStorage();
            });

            // Init
            updateStorage();
        });
    </script>
<?php
});

/**
 * Helper to render one row
 */
function perfex_saas_render_repeater_row($context = '', $type = '', $snippet = '', $raw = false)
{
    $contexts = [
        'global'        => _l('perfex_saas_custom_code_context_global'),
        'super_admin'   => _l('perfex_saas_custom_code_context_super_admin'),
        'super_client'  => _l('perfex_saas_custom_code_context_super_client'),
        'tenant_admin'  => _l('perfex_saas_custom_code_context_tenant_admin'),
        'tenant_client' => _l('perfex_saas_custom_code_context_tenant_client'),
    ];
    $types = ['css' => 'CSS', 'js' => 'JS'];

    ob_start(); ?>
    <tr>
        <td>
            <select class="form-control context">
                <?php foreach ($contexts as $key => $label) : ?>
                    <option value="<?php echo $key; ?>" <?php echo $context === $key ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select class="form-control type">
                <?php foreach ($types as $key => $label) : ?>
                    <option value="<?php echo $key; ?>" <?php echo $type === $key ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <textarea class="form-control snippet" rows="3"><?php echo htmlspecialchars($snippet); ?></textarea>
        </td>
        <td><button type="button" class="btn btn-danger remove-row">&times;</button></td>
    </tr>
<?php
    $html = ob_get_clean();
    return $raw ? $html : $html;
}

/**
 * Saving of the code
 */
hooks()->add_filter('before_settings_updated', function ($data) {

    if (perfex_saas_is_tenant()) return $data;

    $CI = &get_instance();

    $key = 'perfex_saas_ps_global_custom_codes';
    if (!isset($data[$key])) {
        return $data;
    }

    $p_key = 'perfex_saas_ps_global_custom_codes_admin_password';
    if (!isset($data[$p_key]))
        return $data;

    $password = $data[$p_key];
    $custom_codes = $data[$key];

    // Unset
    unset($data[$p_key]);
    unset($data[$key]);

    // Authenticate
    $table = db_prefix() . 'staff';
    $CI->db->where('staffid', get_staff_user_id());
    $user = $CI->db->get($table)->row();
    if ($user && $user->password && app_hasher()->CheckPassword($password, $user->password)) {

        // Set the update
        $data['settings'][$key] = $custom_codes;
    }

    return $data;
});

/**
 * Render custom codes by one or many contexts
 *
 * @param string|array $contexts
 */
function perfex_saas_render_global_custom_code($contexts)
{
    $stored = base64_decode(perfex_saas_tenant_get_super_option('perfex_saas_ps_global_custom_codes') ?? '');
    $codes  = $stored ? json_decode($stored, true) : [];

    if (!$contexts) return;

    if (!is_array($contexts)) {
        $contexts = [$contexts];
    }

    $html = '';
    foreach ($contexts as $context) {
        if (!isset($codes[$context])) continue;

        foreach (['css', 'js'] as $type) {
            if (!empty($codes[$context][$type])) {
                foreach ($codes[$context][$type] as $snippet) {
                    if ($type === 'css') {
                        $html .= "<style data-context='{$context}'>{$snippet}</style>";
                    } else {
                        $html .= "<script data-context='{$context}'>{$snippet}</script>";
                    }
                }
            }
        }
    }

    if (!empty($html)) {

        $html = hooks()->apply_filters('perfex_saas_global_custom_js_css_html', $html, $contexts);
        echo $html;
    }
}

// Admin
hooks()->add_action('app_admin_head', function () {
    perfex_saas_render_global_custom_code([
        'global',
        perfex_saas_is_tenant() ? 'tenant_admin' : 'super_admin'
    ]);
});

// Client
hooks()->add_action('app_customers_head', function () {
    perfex_saas_render_global_custom_code([
        'global',
        perfex_saas_is_tenant() ? 'tenant_client' : 'super_client'
    ]);
});
