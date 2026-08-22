<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Smart Plans — a vertical Excel/CSV round-trip for SaaS packages.
 *
 * The spreadsheet is laid out VERTICALLY: every plan field is a row, and every
 * plan is a column. The first three columns are guidance and are ignored on
 * import:
 *
 *   | Field            | Editable?  | Notes                | Startup | Business | ...
 *   | Plan ID          | Read-only  | Do not change         | 1       | 2
 *   | Plan Name        | Editable   | Customer facing       | Startup | Business
 *   | Billing Period   | Editable   | Monthly / Yearly ...  | Yearly  | Monthly
 *   | Price (INR)      | Editable   | Per billing cycle     | 399     | 699
 *   ...
 *
 * One column schema (smart_plans_columns) drives the exporter, the importer and
 * the on-screen guide, so they never drift apart.
 *
 * Import contract (safe & predictable):
 *   - Plan columns are detected by the "Plan ID" row: any column whose Plan ID
 *     cell is a positive integer is treated as a plan. The Field / Editable? /
 *     Notes columns therefore never count as plans.
 *   - Rows whose Plan ID does not match an existing plan are reported & skipped.
 *   - A BLANK value cell means "leave this field unchanged".
 *   - Read-only fields (Plan ID) are never written back.
 */
trait Smart_plans_trait
{
    /**
     * Full ordered schema = static fields + one row per module. Used by the
     * exporter and the import label→key resolver.
     *
     * @return array List of ['key','label','readonly','help'] definitions
     */
    public function smart_plans_columns()
    {
        return array_merge($this->smart_plans_field_columns(), $this->smart_plans_module_fields());
    }

    /**
     * One spreadsheet row per installed module. Enabling a module for a plan is
     * done by putting the module name (or "Yes") in that plan's cell.
     *
     * @return array
     */
    public function smart_plans_module_fields()
    {
        $fields = [];
        foreach ($this->perfex_saas_model->modules() as $system_name => $module) {
            $fields[] = [
                'key'      => 'module_' . $system_name,
                'label'    => $module['custom_name'] ?? $system_name,
                'system'   => $system_name,
                'readonly' => false,
                'help'     => _l('perfex_saas_smart_plans_help_module_row'),
            ];
        }
        return $fields;
    }

    /**
     * Columns shown in the on-screen field guide: the static fields plus a single
     * summary entry that explains the per-module rows (so the guide stays short).
     *
     * @return array
     */
    public function smart_plans_guide_columns()
    {
        $columns   = $this->smart_plans_field_columns();
        $columns[] = ['key' => 'modules',      'label' => _l('perfex_saas_smart_plans_col_modules'),      'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_modules_rows')];
        $columns[] = ['key' => 'feature_name', 'label' => _l('perfex_saas_smart_plans_col_feature_name'), 'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_feature_name')];
        $columns[] = ['key' => 'show',         'label' => _l('perfex_saas_smart_plans_col_show'),         'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_show')];
        $columns[] = ['key' => 'order',        'label' => _l('perfex_saas_smart_plans_col_order'),        'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_order')];
        return $columns;
    }

    /**
     * The static (non-module) field schema. Each field becomes a row in the export.
     *
     * @return array List of ['key','label','readonly','help'] definitions
     */
    public function smart_plans_field_columns()
    {
        $currency = get_base_currency();
        $cur      = $currency->name;

        $columns = [
            ['key' => 'id',                   'label' => _l('perfex_saas_smart_plans_col_id'),                    'readonly' => true,  'help' => _l('perfex_saas_smart_plans_help_id')],
            ['key' => 'name',                 'label' => _l('perfex_saas_smart_plans_col_name'),                  'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_name')],
            ['key' => 'group',                'label' => _l('perfex_saas_smart_plans_col_group'),                 'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_group')],
            ['key' => 'period',               'label' => _l('perfex_saas_smart_plans_col_period'),                'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_period')],
            ['key' => 'price',                'label' => _l('perfex_saas_smart_plans_col_price', $cur),           'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_price')],
            ['key' => 'trial',                'label' => _l('perfex_saas_smart_plans_col_trial'),                 'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_trial')],
            ['key' => 'status',               'label' => _l('perfex_saas_smart_plans_col_status'),                'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_status')],
            ['key' => 'private',              'label' => _l('perfex_saas_smart_plans_col_private'),               'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_private')],
            ['key' => 'default',              'label' => _l('perfex_saas_smart_plans_col_default'),               'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_default')],
            ['key' => 'db_scheme',            'label' => _l('perfex_saas_smart_plans_col_db_scheme'),             'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_db_scheme')],
            ['key' => 'tax',                  'label' => _l('perfex_saas_smart_plans_col_tax'),                   'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_tax')],
            ['key' => 'payment_modes',        'label' => _l('perfex_saas_smart_plans_col_payment_modes'),         'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_payment_modes')],
            ['key' => 'user_extra_amount',    'label' => _l('perfex_saas_smart_plans_col_user_extra', $cur),      'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_user_extra')],
            ['key' => 'max_instances',        'label' => _l('perfex_saas_smart_plans_col_max_instances'),         'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_max_instances')],
            ['key' => 'extra_instance_price', 'label' => _l('perfex_saas_smart_plans_col_extra_instance', $cur),  'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_extra_instance')],
        ];

        // One row per resource limit, e.g. "Staff Limit", "Clients Limit".
        foreach ($this->smart_plans_limit_keys() as $limit) {
            $columns[] = [
                'key'      => 'limit_' . $limit,
                'label'    => _l('perfex_saas_limit_' . $limit) . ' ' . _l('perfex_saas_smart_plans_col_limit'),
                'readonly' => false,
                'help'     => _l('perfex_saas_smart_plans_help_limit'),
            ];
        }

        $columns[] = ['key' => 'description',     'label' => _l('perfex_saas_smart_plans_col_description'),     'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_description')];
        $columns[] = ['key' => 'custom_features',      'label' => _l('perfex_saas_smart_plans_col_custom_features'),      'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_custom_features')];
        $columns[] = ['key' => 'show_module_features', 'label' => _l('perfex_saas_smart_plans_col_show_module_features'), 'readonly' => false, 'help' => _l('perfex_saas_smart_plans_help_show_module_features')];

        return $columns;
    }

    public function smart_plans_limit_keys()
    {
        return array_values(PERFEX_SAAS_LIMIT_FILTERS_TABLES_MAP);
    }

    /* ===================================================================
     * Export (vertical grid)
     * =================================================================== */

    /**
     * Build the vertical export grid plus the writer options.
     *
     * @return array{grid: array, widths: array, opts: array}
     */
    public function smart_plans_export_grid()
    {
        $field_cols  = $this->smart_plans_field_columns();
        $module_cols = $this->smart_plans_module_fields();
        $refs        = $this->smart_plans_refs();
        $plans       = $this->smart_plans_ordered_plans();

        // Header row: Field | Editable? | Notes | Feature Name | Show in plans | Feature Order | <plan> ...
        // The 3 middle columns are GLOBAL module-presentation controls for the plans
        // API (namesake name, show/hide, order). They only apply to module rows.
        $header = [
            _l('perfex_saas_smart_plans_grid_field'),
            _l('perfex_saas_smart_plans_grid_editable'),
            _l('perfex_saas_smart_plans_grid_notes'),
            _l('perfex_saas_smart_plans_col_feature_name'),
            _l('perfex_saas_smart_plans_col_show'),
            _l('perfex_saas_smart_plans_col_order'),
        ];
        foreach ($plans as $p) {
            $header[] = (string)$p->name;
        }

        // Pre-compute each plan's values keyed by field.
        $plan_values = [];
        foreach ($plans as $p) {
            $plan_values[] = $this->smart_plans_plan_values($p, $refs);
        }

        // $attrs = the 3 global presentation cells (blank for non-module rows).
        $build_row = function ($col, $attrs) use ($plan_values) {
            $row = [
                $col['label'],
                $col['readonly'] ? _l('perfex_saas_smart_plans_readonly') : _l('perfex_saas_smart_plans_editable'),
                $col['help'],
                $attrs[0],
                $attrs[1],
                $attrs[2],
            ];
            foreach ($plan_values as $values) {
                $row[] = array_key_exists($col['key'], $values) ? $values[$col['key']] : '';
            }
            return $row;
        };

        $grid = [$header];
        foreach ($field_cols as $col) {
            $grid[] = $build_row($col, ['', '', '']);
        }

        // Modules block: a visual divider then one row per module, each carrying its
        // global presentation (namesake name / show-hide / order) in the 3 columns.
        if (!empty($module_cols)) {
            $divider = [_l('perfex_saas_smart_plans_modules_divider'), '', _l('perfex_saas_smart_plans_modules_divider_note'), '', '', ''];
            foreach ($plans as $p) {
                $divider[] = '';
            }
            $grid[] = $divider;
            foreach ($module_cols as $col) {
                $meta  = $refs['module_meta'][$col['system']] ?? ['name' => $col['label'], 'visible' => true, 'order' => null];
                $attrs = [
                    $meta['name'],
                    !empty($meta['visible']) ? _l('perfex_saas_module_feature_show') : _l('perfex_saas_module_feature_hide'),
                    ($meta['order'] === null || $meta['order'] === '') ? '' : (int)$meta['order'],
                ];
                $grid[] = $build_row($col, $attrs);
            }
        }

        // Column widths: labels + 3 presentation columns, then a fixed width per plan.
        $widths = [30, 12, 46, 24, 14, 12];
        foreach ($plans as $p) {
            $widths[] = 22;
        }

        return [
            'grid'   => $grid,
            'widths' => $widths,
            'opts'   => [
                'sheet'       => _l('perfex_saas_smart_plans_sheet_name'),
                'widths'      => $widths,
                'header_rows' => 1,
                'label_cols'  => 6,
                'freeze_row'  => 1,
                'freeze_col'  => 6,
            ],
        ];
    }

    /**
     * Flatten one package into a [field key => display value] map for export.
     *
     * @param object $p
     * @param array  $refs
     * @return array
     */
    private function smart_plans_plan_values($p, $refs)
    {
        $metadata    = $p->metadata ?? null;
        $limitations = $metadata->limitations ?? null;
        $unit_price  = $metadata->limitations_unit_price ?? null;
        $invoice     = $metadata->invoice ?? null;

        // Plan group name (0/empty => "Ungrouped").
        $gid   = isset($metadata->plan_group_id) && $metadata->plan_group_id !== '' ? (int)$metadata->plan_group_id : 0;
        $group = $refs['group_name_by_id'][$gid] ?? _l('perfex_saas_pricing_plans_ungrouped');

        $values = [
            'id'                   => (int)$p->id,
            'name'                 => (string)$p->name,
            'group'                => $group,
            'period'               => $this->smart_plans_period_label($invoice),
            'price'                => (float)$p->price,
            'trial'                => (int)($p->trial_period ?? 0),
            'status'               => !empty($p->status)     ? _l('perfex_saas_pricing_plans_active') : _l('perfex_saas_pricing_plans_inactive'),
            'private'              => !empty($p->is_private) ? _l('perfex_saas_smart_plans_yes')      : _l('perfex_saas_smart_plans_no'),
            'default'              => !empty($p->is_default) ? _l('perfex_saas_smart_plans_yes')      : _l('perfex_saas_smart_plans_no'),
            'db_scheme'            => (string)($p->db_scheme ?? ''),
            'tax'                  => $this->smart_plans_tax_display($invoice->taxname ?? null),
            'payment_modes'        => $this->smart_plans_modes_display($invoice->allowed_payment_modes ?? null, $refs),
            'user_extra_amount'    => $this->smart_plans_price_or_blank($unit_price->staff ?? null),
            'max_instances'        => isset($metadata->max_instance_limit) && $metadata->max_instance_limit !== '' ? (int)$metadata->max_instance_limit : 1,
            'extra_instance_price' => $this->smart_plans_price_or_blank($unit_price->tenant_instance ?? null),
        ];

        foreach ($this->smart_plans_limit_keys() as $limit) {
            $v = isset($limitations->{$limit}) ? (int)$limitations->{$limit} : -1;
            $values['limit_' . $limit] = $v < 0 ? _l('perfex_saas_pricing_plans_unlimited') : $v;
        }

        $values['description'] = $this->smart_plans_plain_text($p->description ?? '');

        // Free-text feature lines the admin wrote for this plan (website plan cards).
        $values['custom_features']      = $this->smart_plans_custom_features_cell($this->smart_plans_custom_features_of($metadata));
        $values['show_module_features'] = $this->smart_plans_module_features_shown($metadata)
            ? _l('perfex_saas_smart_plans_yes')
            : _l('perfex_saas_smart_plans_no');

        // One cell per module: the module name when enabled, blank when not. The
        // admin copies the module name into the plans that should include it.
        $enabled = is_array($p->modules ?? null) ? array_values(array_filter((array)$p->modules)) : [];
        foreach ($refs['module_systems'] as $system_name) {
            $values['module_' . $system_name] = in_array($system_name, $enabled, true)
                ? ($refs['module_names'][$system_name] ?? $system_name)
                : '';
        }

        return $values;
    }

    /**
     * All packages in the Pricing Plans order (group → billing period → plan).
     *
     * @return array
     */
    private function smart_plans_ordered_plans()
    {
        $matrix = $this->perfex_saas_model->packages_grouped_for_matrix();
        $plans  = [];
        foreach ($matrix['groups'] as $gid => $gname) {
            foreach (($matrix['matrix'][$gid] ?? []) as $period => $group_plans) {
                foreach ($group_plans as $p) {
                    $plans[] = $p;
                }
            }
        }
        return $plans;
    }

    /* ===================================================================
     * Import (transposed)
     * =================================================================== */

    /**
     * Apply an imported matrix (raw 2D grid) back onto the packages.
     *
     * @param array         $matrix Full-width rows of cell strings (from Perfex_saas_xlsx::read_matrix)
     * @param callable|null $emit   Optional progress sink: $emit(string $event, array $payload).
     *                              Events: 'start', 'module', 'progress', 'done'. Used by the
     *                              real-time streaming import; pass null for a plain run.
     * @return array Summary with counts and per-plan result lines
     */
    public function smart_plans_apply_import(array $matrix, ?callable $emit = null)
    {
        $notify = function ($event, array $payload = []) use ($emit) {
            if ($emit !== null) {
                $emit($event, $payload);
            }
        };

        $empty = ['ok' => false, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'errors' => 0, 'lines' => []];

        if (empty($matrix)) {
            return array_merge($empty, ['message' => _l('perfex_saas_smart_plans_err_empty')]);
        }

        // Map each field-label row (column 0) to a schema key.
        $label_to_key = $this->smart_plans_label_to_key();

        $row_key = [];      // matrix row index => field key
        $id_row  = null;    // index of the "Plan ID" row
        foreach ($matrix as $i => $row) {
            $key = $this->smart_plans_resolve_label($row[0] ?? '', $label_to_key);
            if ($key !== null) {
                $row_key[$i] = $key;
                if ($key === 'id') {
                    $id_row = $i;
                }
            }
        }

        if ($id_row === null) {
            return array_merge($empty, ['message' => _l('perfex_saas_smart_plans_err_no_id_row')]);
        }

        // Plan columns = columns whose Plan ID cell is a positive whole number.
        // The value is normalised first because a spreadsheet round-trip (Excel /
        // Google Sheets re-save, or a copy-paste) routinely turns a clean "10"
        // into "10.0", "10 " with a non-breaking space, "'10" or "1,000".
        $plan_cols = [];
        foreach ($matrix[$id_row] as $c => $cell) {
            if ($c < 1) {
                continue;
            }
            $id = $this->smart_plans_cell_to_id($cell);
            if ($id > 0) {
                $plan_cols[$c] = $id;
            }
        }

        if (empty($plan_cols)) {
            return array_merge($empty, ['message' => _l('perfex_saas_smart_plans_err_no_plan_cols')]);
        }

        // Locate the global module-presentation columns (Feature Name / Show / Order)
        // by their header text. These drive the plans-API feature list, not a plan.
        $header = $matrix[0] ?? [];
        $pres_cols = ['feature' => null, 'show' => null, 'order' => null];
        $pres_labels = [
            'feature' => $this->smart_plans_normalise(_l('perfex_saas_smart_plans_col_feature_name')),
            'show'    => $this->smart_plans_normalise(_l('perfex_saas_smart_plans_col_show')),
            'order'   => $this->smart_plans_normalise(_l('perfex_saas_smart_plans_col_order')),
        ];
        foreach ($header as $c => $cell) {
            $n = $this->smart_plans_normalise($cell);
            foreach ($pres_labels as $k => $label) {
                if ($n !== '' && $n === $label && $pres_cols[$k] === null) {
                    $pres_cols[$k] = $c;
                }
            }
        }

        $refs = $this->smart_plans_refs();

        $updated = $unchanged = $skipped = $errors = 0;
        $lines   = [];
        $total   = count($plan_cols);

        // Announce the work to be done so the live UI can draw a progress bar.
        $plan_index = [];
        foreach ($plan_cols as $col => $plan_id) {
            $plan_index[] = ['ref' => $this->smart_plans_col_letter($col), 'id' => $plan_id];
        }
        $notify('start', ['total' => $total, 'plans' => $plan_index]);

        // Apply the global module presentation (namesake name / show-hide / order)
        // once, before the per-plan loop, so it shows at the top of the summary.
        if ($pres_cols['feature'] !== null || $pres_cols['show'] !== null || $pres_cols['order'] !== null) {
            $changed = $this->smart_plans_apply_module_presentation($matrix, $row_key, $pres_cols, $refs);
            if ($changed > 0) {
                $module_line = [
                    'ref'     => '',
                    'id'      => '',
                    'name'    => _l('perfex_saas_smart_plans_module_settings'),
                    'status'  => 'updated',
                    'note'    => _l('perfex_saas_smart_plans_module_settings_done', $changed),
                    'changes' => [],
                ];
                $lines[] = $module_line;
                $notify('module', ['line' => $module_line]);
            }
        }

        $position = 0;
        foreach ($plan_cols as $col => $plan_id) {
            $ref = $this->smart_plans_col_letter($col);
            $position++;

            // Collect this plan's field values from each field row.
            $fields = [];
            foreach ($row_key as $i => $key) {
                $fields[$key] = isset($matrix[$i][$col]) ? (string)$matrix[$i][$col] : '';
            }

            $package = $this->perfex_saas_model->packages($plan_id);
            if (!$package) {
                $skipped++;
                $line    = ['ref' => $ref, 'id' => $plan_id, 'name' => $fields['name'] ?? '', 'status' => 'skipped', 'note' => _l('perfex_saas_smart_plans_note_missing'), 'changes' => []];
                $lines[] = $line;
                $notify('progress', ['position' => $position, 'total' => $total, 'line' => $line]);
                continue;
            }

            $result         = $this->smart_plans_apply_fields($package, $fields, $refs);
            $result['ref']  = $ref;
            $result['id']   = $plan_id;
            $result['name'] = $result['name'] ?: ($package->name ?? '');
            $lines[]        = $result;

            switch ($result['status']) {
                case 'updated': $updated++;   break;
                case 'error':   $errors++;    break;
                default:        $unchanged++; break;
            }

            $notify('progress', ['position' => $position, 'total' => $total, 'line' => $result]);
        }

        $summary = [
            'ok'        => true,
            'updated'   => $updated,
            'unchanged' => $unchanged,
            'skipped'   => $skipped,
            'errors'    => $errors,
            'total'     => $total,
            'lines'     => $lines,
            'message'   => _l('perfex_saas_smart_plans_import_done', [$updated, $unchanged, $skipped, $errors]),
        ];

        $notify('done', ['summary' => $summary]);

        return $summary;
    }

    /**
     * Apply a single plan's field map onto a loaded package, writing only changes.
     *
     * @param object $package
     * @param array  $fields [field key => cell string]
     * @param array  $refs
     * @return array ['status' => updated|unchanged|error, 'name' => string, 'note' => string]
     */
    private function smart_plans_apply_fields($package, array $fields, array $refs)
    {
        $id           = (int)$package->id;
        $update       = ['id' => $id];
        $metadata     = (array)($package->metadata ?? []);
        $invoice      = isset($metadata['invoice']) ? (array)$metadata['invoice'] : [];
        $notes        = [];
        $changes      = [];
        $meta_changed = false;
        $new_name     = $package->name;

        $L      = $refs['labels'] ?? [];
        $record = function ($key, $from, $to) use (&$changes, $L) {
            $label     = $L[$key] ?? $key;
            $from      = ($from === '' || $from === null) ? '—' : (string)$from;
            $to        = ($to === '' || $to === null) ? '—' : (string)$to;
            $changes[] = $label . ': ' . $from . ' → ' . $to;
        };

        // --- Name ----------------------------------------------------------------
        $name = $this->smart_plans_val($fields, 'name');
        if ($name !== null && $name !== '' && $name !== (string)$package->name) {
            $record('name', $package->name, $name);
            $new_name       = $name;
            $update['name'] = $name;
            $update['slug'] = perfex_saas_generate_unique_slug($name, 'packages', (string)$id, 0, ['skip_table_compact' => true, 'max_length' => 150, 'delimiter' => '-']);
        }

        // --- Price ---------------------------------------------------------------
        $price = $this->smart_plans_val($fields, 'price');
        if ($price !== null && $price !== '') {
            $n = $this->smart_plans_to_number($price);
            if ($n === null) {
                $notes[] = _l('perfex_saas_smart_plans_note_bad_price');
            } elseif ((float)$n !== (float)$package->price) {
                $record('price', (float)$package->price, (float)$n);
                $update['price'] = (float)$n;
            }
        }

        // --- Trial days ----------------------------------------------------------
        $trial = $this->smart_plans_val($fields, 'trial');
        if ($trial !== null && $trial !== '' && is_numeric($trial)) {
            $tv = max(0, (int)$trial);
            if ($tv !== (int)($package->trial_period ?? 0)) {
                $record('trial', (int)($package->trial_period ?? 0), $tv);
                $update['trial_period'] = $tv;
            }
        }

        // --- Status / Private ----------------------------------------------------
        $status = $this->smart_plans_truthy($this->smart_plans_val($fields, 'status'));
        if ($status !== null && (int)$status !== (int)$package->status) {
            $record('status', !empty($package->status) ? _l('perfex_saas_pricing_plans_active') : _l('perfex_saas_pricing_plans_inactive'), $status ? _l('perfex_saas_pricing_plans_active') : _l('perfex_saas_pricing_plans_inactive'));
            $update['status'] = $status ? 1 : 0;
        }

        $private = $this->smart_plans_truthy($this->smart_plans_val($fields, 'private'));
        if ($private !== null && (int)$private !== (int)($package->is_private ?? 0)) {
            $record('private', !empty($package->is_private) ? _l('perfex_saas_smart_plans_yes') : _l('perfex_saas_smart_plans_no'), $private ? _l('perfex_saas_smart_plans_yes') : _l('perfex_saas_smart_plans_no'));
            $update['is_private'] = $private ? 1 : 0;
        }

        // --- DB scheme -----------------------------------------------------------
        $scheme = $this->smart_plans_val($fields, 'db_scheme');
        if ($scheme !== null && trim($scheme) !== '') {
            $key = $refs['db_scheme_lookup'][strtolower(trim($scheme))] ?? null;
            if ($key === null) {
                $notes[] = _l('perfex_saas_smart_plans_note_db_scheme_unknown', trim($scheme));
            } elseif ($key !== (string)($package->db_scheme ?? '')) {
                $record('db_scheme', $package->db_scheme ?? '', $key);
                $update['db_scheme'] = $key;
            }
        }

        // --- Plan group (matched by name; "Ungrouped" clears it) -----------------
        $group = $this->smart_plans_val($fields, 'group');
        if ($group !== null && trim($group) !== '') {
            $group_lc = strtolower(trim($group));
            if ($group_lc === strtolower(_l('perfex_saas_pricing_plans_ungrouped'))) {
                if (($metadata['plan_group_id'] ?? '') !== '') {
                    $record('group', $refs['group_name_by_id'][(int)($metadata['plan_group_id'] ?? 0)] ?? '', _l('perfex_saas_pricing_plans_ungrouped'));
                    $metadata['plan_group_id'] = '';
                    $meta_changed = true;
                }
            } elseif (isset($refs['group_by_name'][$group_lc])) {
                $gid = (int)$refs['group_by_name'][$group_lc];
                if ((int)($metadata['plan_group_id'] ?? 0) !== $gid) {
                    $record('group', $refs['group_name_by_id'][(int)($metadata['plan_group_id'] ?? 0)] ?? '', $refs['group_name_by_id'][$gid] ?? trim($group));
                    $metadata['plan_group_id'] = $gid;
                    $meta_changed = true;
                }
            } else {
                $notes[] = _l('perfex_saas_smart_plans_note_group_unknown', trim($group));
            }
        }

        // --- Billing period ------------------------------------------------------
        $period = $this->smart_plans_val($fields, 'period');
        if ($period !== null && trim($period) !== '') {
            $preset_key = $refs['period_aliases'][$this->smart_plans_normalise($period)] ?? null;
            // Named preset (Monthly / Quarterly / …) or a custom "Every N month/year"
            // interval — the latter is what the exporter writes for non-preset cycles.
            $preset = $preset_key !== null
                ? $refs['period_presets'][$preset_key]
                : $this->smart_plans_parse_custom_period($period);

            if ($preset === null) {
                $notes[] = _l('perfex_saas_smart_plans_note_period_unknown', trim($period));
            } elseif ($this->smart_plans_period_differs($invoice, $preset)) {
                $record('period', $this->smart_plans_period_label((object)$invoice), $preset['label']);
                $invoice['recurring']           = $preset['recurring'];
                $invoice['repeat_type_custom']  = $preset['repeat_type_custom'];
                $invoice['repeat_every_custom'] = $preset['repeat_every_custom'];
                $meta_changed = true;
            }
        }

        // --- Tax (under invoice) -------------------------------------------------
        $tax = $this->smart_plans_val($fields, 'tax');
        if ($tax !== null && $tax !== '') {
            $new_tax = $this->smart_plans_parse_tax($tax, $refs, $notes);
            if ($new_tax !== null) {
                $current = array_values((array)($invoice['taxname'] ?? []));
                $a = $new_tax; $b = $current; sort($a); sort($b);
                if ($a !== $b) {
                    $record('tax', $this->smart_plans_tax_display($current), $this->smart_plans_tax_display($new_tax));
                    $invoice['taxname'] = $new_tax;
                    $meta_changed = true;
                }
            }
        }

        // --- Allowed payment modes (under invoice) -------------------------------
        $modes = $this->smart_plans_val($fields, 'payment_modes');
        if ($modes !== null && $modes !== '') {
            $new_modes = $this->smart_plans_parse_modes($modes, $refs, $notes);
            if ($new_modes !== null) {
                $current = array_values((array)($invoice['allowed_payment_modes'] ?? []));
                $a = array_map('strval', $new_modes); $b = array_map('strval', $current); sort($a); sort($b);
                if ($a !== $b) {
                    $record('payment_modes', $this->smart_plans_modes_display($current, $refs), $this->smart_plans_modes_display($new_modes, $refs));
                    $invoice['allowed_payment_modes'] = $new_modes;
                    $meta_changed = true;
                }
            }
        }

        // --- Per-unit overage prices (staff = user extra, tenant_instance) -------
        $unit_price = isset($metadata['limitations_unit_price']) ? (array)$metadata['limitations_unit_price'] : [];

        foreach (['user_extra_amount' => 'staff', 'extra_instance_price' => 'tenant_instance'] as $field => $mkey) {
            $cell = $this->smart_plans_val($fields, $field);
            if ($cell === null || trim($cell) === '') {
                continue;
            }
            $n = $this->smart_plans_to_number($cell);
            if ($n === null) {
                $notes[] = _l('perfex_saas_smart_plans_note_bad_amount');
                continue;
            }
            if ((float)($unit_price[$mkey] ?? 0) !== (float)$n) {
                $record($field, (float)($unit_price[$mkey] ?? 0), (float)$n);
                $unit_price[$mkey] = (float)$n;
                $metadata['limitations_unit_price'] = $unit_price;
                $meta_changed = true;
            }
        }

        // --- Max number of instances --------------------------------------------
        $maxi = $this->smart_plans_val($fields, 'max_instances');
        if ($maxi !== null && trim($maxi) !== '' && is_numeric(trim($maxi))) {
            $mv = max(0, (int)$maxi);
            if ($mv !== (int)($metadata['max_instance_limit'] ?? 1)) {
                $record('max_instances', (int)($metadata['max_instance_limit'] ?? 1), $mv);
                $metadata['max_instance_limit'] = $mv;
                $meta_changed = true;
            }
        }

        // --- Resource limits -----------------------------------------------------
        $current_limits = isset($metadata['limitations']) ? (array)$metadata['limitations'] : [];
        $limits_changed = false;
        foreach ($this->smart_plans_limit_keys() as $limit) {
            $cell = $this->smart_plans_val($fields, 'limit_' . $limit);
            if ($cell === null || trim($cell) === '') {
                continue;
            }
            $value = $this->smart_plans_parse_limit($cell);
            if ($value === null) {
                $notes[] = _l('perfex_saas_smart_plans_note_bad_limit', _l('perfex_saas_limit_' . $limit));
                continue;
            }
            if ((int)($current_limits[$limit] ?? -1) !== $value) {
                $old_v = (int)($current_limits[$limit] ?? -1);
                $record('limit_' . $limit, $old_v < 0 ? _l('perfex_saas_pricing_plans_unlimited') : $old_v, $value < 0 ? _l('perfex_saas_pricing_plans_unlimited') : $value);
                $current_limits[$limit] = $value;
                $limits_changed = true;
            }
        }
        if ($limits_changed) {
            $metadata['limitations'] = $current_limits;
            $meta_changed = true;
        }

        // --- Enabled modules (one row per module) --------------------------------
        // Only the module rows present in the file are authoritative; a module
        // whose row was removed keeps its current state. A non-blank cell (the
        // module name or "Yes") includes the module; blank/"No" excludes it.
        $current_modules = is_array($package->modules ?? null) ? array_values(array_filter((array)$package->modules)) : [];
        $present_systems = [];
        $enabled_present = [];
        foreach ($refs['module_systems'] as $system_name) {
            $cell = $this->smart_plans_val($fields, 'module_' . $system_name);
            if ($cell === null) {
                continue; // module row not in the file → leave this module untouched
            }
            $present_systems[$system_name] = true;
            if ($this->smart_plans_module_marker_enabled($cell)) {
                $enabled_present[] = $system_name;
            }
        }

        // Backward-compatibility: accept a single comma-separated "Enabled Modules" row.
        if (empty($present_systems)) {
            $legacy = $this->smart_plans_val($fields, 'modules');
            if ($legacy !== null && trim($legacy) !== '') {
                [$mods, $unknown] = $this->smart_plans_parse_modules($legacy, $refs['valid_modules']);
                if (!empty($unknown)) {
                    $notes[] = _l('perfex_saas_smart_plans_note_modules_unknown', implode(', ', $unknown));
                }
                foreach ($mods as $m) {
                    $present_systems[$m] = true;
                }
                // A comma row is a full replacement, so untouched modules are dropped.
                $present_systems = array_fill_keys($refs['module_systems'], true);
                $enabled_present = $mods;
            }
        }

        if (!empty($present_systems)) {
            // Preserve modules whose row was absent, then add the enabled ones.
            $preserved   = array_values(array_filter($current_modules, function ($m) use ($present_systems) {
                return !isset($present_systems[$m]);
            }));
            $new_modules = array_values(array_unique(array_merge($preserved, $enabled_present)));

            $a = $new_modules; $b = $current_modules; sort($a); sort($b);
            if ($a !== $b) {
                $module_label = $L['modules'] ?? _l('perfex_saas_smart_plans_col_modules');
                $names_for    = function ($systems) use ($refs) {
                    $out = [];
                    foreach ($systems as $s) {
                        $out[] = $refs['module_names'][$s] ?? $s;
                    }
                    sort($out);
                    return $out ? implode(', ', $out) : '—';
                };
                $changes[] = $module_label . ': ' . $names_for($current_modules) . ' → ' . $names_for($new_modules);
                $update['modules'] = json_encode($new_modules);
            }
        }

        // --- Description (blank = keep; compared against current plain text) ------
        $desc = $this->smart_plans_val($fields, 'description');
        if ($desc !== null && trim($desc) !== '' && trim($desc) !== $this->smart_plans_plain_text($package->description ?? '')) {
            $changes[]             = ($L['description'] ?? _l('perfex_saas_smart_plans_col_description')) . ': ' . _l('perfex_saas_smart_plans_change_updated');
            $update['description'] = html_purify(trim($desc));
        }

        // --- Custom feature lines (comma separated, per plan) --------------------
        // Free text shown on the website plan cards in addition to the module
        // features. Blank keeps the current list; "none" clears it.
        $custom = $this->smart_plans_val($fields, 'custom_features');
        if ($custom !== null && trim($custom) !== '') {
            $new_features = $this->smart_plans_parse_custom_features($custom);
            $current      = $this->smart_plans_custom_features_of($metadata);
            if ($new_features !== $current) {
                $record(
                    'custom_features',
                    $this->smart_plans_custom_features_cell($current),
                    $this->smart_plans_custom_features_cell($new_features)
                );
                $metadata['custom_features'] = $new_features;
                $meta_changed = true;
            }
        }

        // --- Show module features on the website? --------------------------------
        // No = the website card lists ONLY the custom feature lines above.
        $show_modules = $this->smart_plans_truthy($this->smart_plans_val($fields, 'show_module_features'));
        if ($show_modules !== null && $show_modules !== $this->smart_plans_module_features_shown($metadata)) {
            $record(
                'show_module_features',
                $this->smart_plans_module_features_shown($metadata) ? _l('perfex_saas_smart_plans_yes') : _l('perfex_saas_smart_plans_no'),
                $show_modules ? _l('perfex_saas_smart_plans_yes') : _l('perfex_saas_smart_plans_no')
            );
            $metadata['show_module_features'] = $show_modules ? 1 : 0;
            $meta_changed = true;
        }

        // Persist invoice changes back into metadata.
        if ($meta_changed) {
            $metadata['invoice']  = $invoice;
            $update['metadata']   = json_encode($metadata);
        }

        $changed = (count($update) > 1) || $meta_changed;

        if ($changed) {
            $ok = $this->perfex_saas_model->add_or_update('packages', $update);
            if (!$ok) {
                return ['status' => 'error', 'name' => $new_name, 'note' => _l('perfex_saas_smart_plans_note_save_failed')];
            }
        }

        // --- Default flag (only acts on "Yes"; uses the safe single-default setter)
        $default = $this->smart_plans_truthy($this->smart_plans_val($fields, 'default'));
        if ($default === true && empty($package->is_default)) {
            $this->perfex_saas_model->mark_package_as_default($id);
            $record('default', _l('perfex_saas_smart_plans_no'), _l('perfex_saas_smart_plans_yes'));
            $changed = true;
        }

        return [
            'status'  => $changed ? 'updated' : 'unchanged',
            'name'    => $new_name,
            'note'    => implode(' ', array_filter($notes)),
            'changes' => $changes,
        ];
    }

    /* ===================================================================
     * Reference data
     * =================================================================== */

    /**
     * Build all lookup tables used by export display and import parsing, once.
     *
     * @return array
     */
    private function smart_plans_refs()
    {
        $CI = &get_instance();

        // Plan groups (core customer groups).
        $group_by_name    = [];
        $group_name_by_id = [0 => _l('perfex_saas_pricing_plans_ungrouped')];
        foreach ($this->perfex_saas_model->pricing_plan_groups() as $g) {
            $g = (array)$g;
            if (isset($g['name'], $g['id'])) {
                $group_by_name[strtolower(trim($g['name']))] = (int)$g['id'];
                $group_name_by_id[(int)$g['id']] = $g['name'];
            }
        }

        // Modules: validity lookup + ordered system list + display names + presentation meta.
        $valid_modules  = [];
        $module_systems = [];
        $module_names   = [];
        $module_meta    = [];
        foreach ($this->perfex_saas_model->modules() as $sys => $module) {
            $valid_modules[strtolower($sys)] = $sys;
            $module_systems[] = $sys;
            $module_names[$sys] = $module['custom_name'] ?? $sys;
            $module_meta[$sys] = [
                'name'    => $module['custom_name'] ?? $sys,
                'visible' => $module['visible'] ?? true,
                'order'   => $module['order'] ?? null,
            ];
        }

        // Taxes: token (lower) => canonical "name|rate"; canonical => display name.
        $CI->load->model('taxes_model');
        $tax_lookup  = [];
        $tax_display = [];
        foreach ((array)$CI->taxes_model->get() as $tax) {
            $tax       = (array)$tax;
            $name      = $tax['name'] ?? '';
            $rate      = isset($tax['taxrate']) ? $tax['taxrate'] : '';
            $canonical = $name . '|' . $rate;
            $tax_lookup[strtolower(trim($name))]            = $canonical;
            $tax_lookup[strtolower(trim($canonical))]       = $canonical;
            $tax_lookup[strtolower(trim($name . ' ' . $rate . '%'))] = $canonical;
            $tax_display[$canonical] = $name;
        }

        // Payment modes: token (lower) => id; id => name.
        $CI->load->model('payment_modes_model');
        $mode_lookup  = [];
        $mode_display = [];
        foreach ((array)$CI->payment_modes_model->get('', [], true) as $mode) {
            $mode = (array)$mode;
            $mid  = (string)($mode['id'] ?? '');
            $mname = $mode['name'] ?? '';
            if ($mid === '') {
                continue;
            }
            $mode_lookup[strtolower(trim($mname))] = $mid;
            $mode_lookup[strtolower($mid)]         = $mid;
            $mode_display[$mid] = $mname;
        }

        // DB schemes: token (lower) => key.
        $db_scheme_lookup = [];
        foreach ($this->smart_plans_db_schemes() as $key => $label) {
            $db_scheme_lookup[strtolower($key)]          = $key;
            $db_scheme_lookup[strtolower(trim($label))]  = $key;
        }

        $period_presets = $this->smart_plans_period_presets();

        // Friendly field-key => label map, used to describe what changed per plan.
        $labels = [];
        foreach ($this->smart_plans_columns() as $col) {
            $labels[$col['key']] = $col['label'];
        }

        return [
            'labels'           => $labels,
            'group_by_name'    => $group_by_name,
            'group_name_by_id' => $group_name_by_id,
            'valid_modules'    => $valid_modules,
            'module_systems'   => $module_systems,
            'module_names'     => $module_names,
            'module_meta'      => $module_meta,
            'tax_lookup'       => $tax_lookup,
            'tax_display'      => $tax_display,
            'mode_lookup'      => $mode_lookup,
            'mode_display'     => $mode_display,
            'db_scheme_lookup' => $db_scheme_lookup,
            'period_presets'   => $period_presets,
            'period_aliases'   => $this->smart_plans_period_aliases($period_presets),
        ];
    }

    /**
     * DB scheme key => human label.
     *
     * Starts from the four core schemes (always offered, regardless of the DB
     * privilege the model gates 'single' on) and then folds in any scheme an
     * integration registers through the `perfex_saas_module_db_schemes` filter —
     * the same source the package edit page uses. Without this, a plan saved with
     * an integration scheme (cpanel, plesk, mysql_root, …) would export that key
     * but fail to import back, reporting it as "not recognised".
     *
     * @return array
     */
    private function smart_plans_db_schemes()
    {
        $base = [
            ['key' => 'multitenancy', 'label' => _l('perfex_saas_use_the_current_active_database_for_all_tenants')],
            ['key' => 'single',       'label' => _l('perfex_saas_use_single_database_for_each_company_instance.')],
            ['key' => 'single_pool',  'label' => _l('perfex_saas_single_pool_db_scheme')],
            ['key' => 'shard',        'label' => _l('perfex_saas_distribute_companies_data_among_the_provided_databases_in_the_pool')],
        ];

        $base = hooks()->apply_filters('perfex_saas_module_db_schemes', $base);

        $schemes = [];
        foreach ((array)$base as $scheme) {
            $scheme = (array)$scheme;
            if (!empty($scheme['key'])) {
                $schemes[(string)$scheme['key']] = $scheme['label'] ?? $scheme['key'];
            }
        }

        return $schemes;
    }

    /**
     * Recurring-period presets (mirrors the Pricing Plans variant generator).
     *
     * @return array
     */
    private function smart_plans_period_presets()
    {
        return [
            'monthly'     => ['label' => _l('perfex_saas_smart_plans_period_monthly'),     'recurring' => 1,        'repeat_type_custom' => 'month', 'repeat_every_custom' => 1],
            'quarterly'   => ['label' => _l('perfex_saas_smart_plans_period_quarterly'),   'recurring' => 3,        'repeat_type_custom' => 'month', 'repeat_every_custom' => 3],
            'half_yearly' => ['label' => _l('perfex_saas_smart_plans_period_half_yearly'), 'recurring' => 6,        'repeat_type_custom' => 'month', 'repeat_every_custom' => 6],
            'yearly'      => ['label' => _l('perfex_saas_smart_plans_period_yearly'),      'recurring' => 'custom', 'repeat_type_custom' => 'year',  'repeat_every_custom' => 1],
            'biennially'  => ['label' => _l('perfex_saas_smart_plans_period_biennially'),  'recurring' => 'custom', 'repeat_type_custom' => 'year',  'repeat_every_custom' => 2],
        ];
    }

    /**
     * Normalised alias => preset key (canonical labels + common synonyms).
     *
     * @param array $presets
     * @return array
     */
    private function smart_plans_period_aliases($presets)
    {
        $aliases = [];
        foreach ($presets as $key => $preset) {
            $aliases[$this->smart_plans_normalise($preset['label'])] = $key;
        }

        $extra = [
            'monthly' => 'monthly', 'month' => 'monthly', '1 month' => 'monthly',
            'quarterly' => 'quarterly', 'quarter' => 'quarterly', '3 months' => 'quarterly',
            'half yearly' => 'half_yearly', 'half-yearly' => 'half_yearly', 'semi annual' => 'half_yearly', '6 months' => 'half_yearly', 'biannually' => 'half_yearly',
            'yearly' => 'yearly', 'annual' => 'yearly', 'annually' => 'yearly', '1 year' => 'yearly',
            'biennially' => 'biennially', '2 years' => 'biennially',
        ];
        foreach ($extra as $alias => $key) {
            $aliases[$this->smart_plans_normalise($alias)] = $key;
        }

        return $aliases;
    }

    /**
     * Best-effort human label for a plan's billing period from its invoice block.
     *
     * @param object|null $invoice
     * @return string
     */
    private function smart_plans_period_label($invoice)
    {
        if (empty($invoice)) {
            return '';
        }

        $recurring = $invoice->recurring ?? '';
        $type      = $invoice->repeat_type_custom ?? '';
        $every     = (int)($invoice->repeat_every_custom ?? 1);

        foreach ($this->smart_plans_period_presets() as $preset) {
            if ((string)$recurring === (string)$preset['recurring']) {
                if ((string)$recurring === 'custom') {
                    if ($type === $preset['repeat_type_custom'] && $every === (int)$preset['repeat_every_custom']) {
                        return $preset['label'];
                    }
                } else {
                    return $preset['label'];
                }
            }
        }

        // Fallback for an uncommon custom interval.
        if ((string)$recurring === 'custom' && $type !== '') {
            return _l('perfex_saas_smart_plans_period_every', [$every, $type]);
        }
        if (is_numeric($recurring) && (int)$recurring > 0) {
            return _l('perfex_saas_smart_plans_period_every', [(int)$recurring, 'month']);
        }

        return '';
    }

    /**
     * Parse a custom "Every N month/year/week/day" billing label — what the
     * exporter writes for cycles that are not one of the named presets — into the
     * same recurring-config shape a preset uses. Returns null when not parseable.
     *
     * Month cycles use a numeric "recurring" (the month count, as the presets do);
     * year/week/day cycles use the custom recurring with an explicit unit.
     *
     * @param string $raw
     * @return array|null
     */
    private function smart_plans_parse_custom_period($raw)
    {
        $text = $this->smart_plans_normalise($raw); // lower-cased, collapsed spaces
        // Accept "every 4 months", "4 month", "every 1 year", …
        if (!preg_match('/(\d+)\s*(day|week|month|year)s?\b/', $text, $m)) {
            return null;
        }

        $n    = max(1, (int)$m[1]);
        $unit = $m[2];

        return [
            'label'               => _l('perfex_saas_smart_plans_period_every', [$n, $unit]),
            'recurring'           => $unit === 'month' ? $n : 'custom',
            'repeat_type_custom'  => $unit,
            'repeat_every_custom' => $n,
        ];
    }

    /**
     * Whether a preset differs from the current invoice recurring config. The
     * comparison is on a canonical "unit:count" signature so equivalent encodings
     * (e.g. a numeric month recurring vs. an explicit month/every pair) match and
     * a re-import of the same cycle is not falsely reported as a change.
     */
    private function smart_plans_period_differs(array $invoice, array $preset)
    {
        return $this->smart_plans_period_signature(
                $invoice['recurring'] ?? '',
                $invoice['repeat_type_custom'] ?? '',
                $invoice['repeat_every_custom'] ?? 0
            ) !== $this->smart_plans_period_signature(
                $preset['recurring'],
                $preset['repeat_type_custom'],
                $preset['repeat_every_custom']
            );
    }

    /**
     * Canonical "unit:count" signature for a recurring config. A numeric recurring
     * is a month count; 'custom' carries its own unit/count. Returns '' when no
     * recurring cycle is set.
     *
     * @return string
     */
    private function smart_plans_period_signature($recurring, $type, $every)
    {
        $recurring = (string)$recurring;
        if ($recurring === 'custom') {
            $unit  = strtolower(trim((string)$type)) ?: 'month';
            $count = max(1, (int)$every);
        } elseif (is_numeric($recurring) && (int)$recurring > 0) {
            $unit  = 'month';
            $count = (int)$recurring;
        } else {
            return '';
        }
        return $unit . ':' . $count;
    }

    /* ===================================================================
     * Display & parse helpers
     * =================================================================== */

    private function smart_plans_tax_display($taxname)
    {
        if (empty($taxname)) {
            return '';
        }
        $names = [];
        foreach ((array)$taxname as $canonical) {
            $parts   = explode('|', (string)$canonical);
            $names[] = $parts[0];
        }
        return implode(', ', $names);
    }

    private function smart_plans_modes_display($ids, $refs)
    {
        if (empty($ids)) {
            return '';
        }
        $names = [];
        foreach ((array)$ids as $id) {
            $names[] = $refs['mode_display'][(string)$id] ?? (string)$id;
        }
        return implode(', ', $names);
    }

    private function smart_plans_price_or_blank($value)
    {
        if ($value === null || $value === '' || (float)$value == 0.0) {
            return '';
        }
        return (float)$value;
    }

    /**
     * Parse a comma list of tax tokens into canonical "name|rate" values.
     * "none"/"-" clears the tax. Returns null when nothing should change.
     *
     * @return array|null
     */
    private function smart_plans_parse_tax($raw, $refs, &$notes)
    {
        $raw = trim($raw);
        if (in_array(strtolower($raw), ['none', '-', '0', strtolower(_l('no_tax'))], true)) {
            return [];
        }

        $result  = [];
        $unknown = [];
        foreach (preg_split('/\s*,\s*/', $raw) as $token) {
            if ($token === '') {
                continue;
            }
            $canonical = $refs['tax_lookup'][strtolower($token)] ?? null;
            if ($canonical === null) {
                $unknown[] = $token;
            } else {
                $result[$canonical] = $canonical;
            }
        }

        if (!empty($unknown)) {
            $notes[] = _l('perfex_saas_smart_plans_note_tax_unknown', implode(', ', $unknown));
        }

        return array_values($result);
    }

    /**
     * Parse a comma list of payment-mode tokens into mode ids.
     * "none"/"-" clears the list. Returns null when nothing should change.
     *
     * @return array|null
     */
    private function smart_plans_parse_modes($raw, $refs, &$notes)
    {
        $raw = trim($raw);
        if (in_array(strtolower($raw), ['none', '-', '0'], true)) {
            return [];
        }

        $result  = [];
        $unknown = [];
        foreach (preg_split('/\s*,\s*/', $raw) as $token) {
            if ($token === '') {
                continue;
            }
            $id = $refs['mode_lookup'][strtolower($token)] ?? null;
            if ($id === null) {
                $unknown[] = $token;
            } else {
                $result[$id] = $id;
            }
        }

        if (!empty($unknown)) {
            $notes[] = _l('perfex_saas_smart_plans_note_modes_unknown', implode(', ', $unknown));
        }

        return array_values($result);
    }

    /**
     * Whether a module cell marks the module as enabled. Blank or an explicit
     * negative ("No", "0", "-", ...) means excluded; anything else (the module
     * name, "Yes", a tick, ...) means included.
     *
     * @param string $cell
     * @return bool
     */
    private function smart_plans_module_marker_enabled($cell)
    {
        $v = strtolower(trim((string)$cell));
        if ($v === '') {
            return false;
        }
        return !in_array($v, ['0', 'no', 'n', 'false', 'off', 'none', '-', 'disabled', 'exclude'], true);
    }

    /**
     * Apply the global module-presentation columns (Feature Name / Show / Order)
     * to the module display settings used by the plans API. These are global (one
     * set of values per module), so they are written once per import.
     *
     * @param array $matrix
     * @param array $row_key   matrix row index => field key
     * @param array $pres_cols ['feature'=>idx|null, 'show'=>idx|null, 'order'=>idx|null]
     * @param array $refs       reference data (module_meta holds effective current values)
     * @return int Number of changed values
     */
    private function smart_plans_apply_module_presentation(array $matrix, array $row_key, array $pres_cols, array $refs)
    {
        $names  = json_decode((string)get_option('perfex_saas_custom_modules_name'), true);
        $market = json_decode((string)get_option('perfex_saas_modules_marketplace'), true);
        if (!is_array($names))  $names  = [];
        if (!is_array($market)) $market = [];

        $meta = $refs['module_meta'] ?? [];

        $changed = 0;
        foreach ($row_key as $i => $key) {
            if (strpos($key, 'module_') !== 0) {
                continue;
            }
            $system_name = substr($key, 7);
            $row         = $matrix[$i] ?? [];
            $current     = $meta[$system_name] ?? ['name' => $system_name, 'visible' => true, 'order' => null];

            // Namesake / feature name (compared against the effective current name)
            if ($pres_cols['feature'] !== null) {
                $val = trim((string)($row[$pres_cols['feature']] ?? ''));
                if ($val !== '' && $val !== (string)$current['name']) {
                    $names[$system_name] = $val;
                    $changed++;
                }
            }

            // Show / hide
            if ($pres_cols['show'] !== null) {
                $raw = trim((string)($row[$pres_cols['show']] ?? ''));
                if ($raw !== '') {
                    $vis = $this->smart_plans_show_value($raw);
                    if ($vis !== null && $vis !== (bool)$current['visible']) {
                        $market[$system_name]['visible'] = $vis ? '1' : '0';
                        $changed++;
                    }
                }
            }

            // Order
            if ($pres_cols['order'] !== null) {
                $raw = trim((string)($row[$pres_cols['order']] ?? ''));
                if ($raw !== '' && is_numeric($raw)) {
                    $new = (int)$raw;
                    $cur = ($current['order'] === null || $current['order'] === '') ? null : (int)$current['order'];
                    if ($new !== $cur) {
                        $market[$system_name]['order'] = $new;
                        $changed++;
                    }
                }
            }
        }

        if ($changed > 0) {
            update_option('perfex_saas_custom_modules_name', json_encode($names));
            update_option('perfex_saas_modules_marketplace', json_encode($market));
        }

        return $changed;
    }

    /**
     * Interpret a Show/Hide cell. Returns true (show), false (hide) or null.
     *
     * @param string $raw
     * @return bool|null
     */
    private function smart_plans_show_value($raw)
    {
        $v = strtolower(trim((string)$raw));
        if ($v === '') {
            return null;
        }
        $show = ['1', 'show', 'yes', 'y', 'true', 'visible', 'on', strtolower(_l('perfex_saas_module_feature_show'))];
        $hide = ['0', 'hide', 'no', 'n', 'false', 'hidden', 'off', strtolower(_l('perfex_saas_module_feature_hide'))];
        if (in_array($v, $show, true)) {
            return true;
        }
        if (in_array($v, $hide, true)) {
            return false;
        }
        return null;
    }

    private function smart_plans_parse_modules($raw, $valid_modules)
    {
        $matched = [];
        $unknown = [];
        foreach (preg_split('/[,;]+/', $raw) as $piece) {
            $piece = trim($piece);
            if ($piece === '') {
                continue;
            }
            $lc = strtolower($piece);
            if (isset($valid_modules[$lc])) {
                $matched[$valid_modules[$lc]] = $valid_modules[$lc];
            } else {
                $unknown[] = $piece;
            }
        }
        return [array_values($matched), $unknown];
    }

    /**
     * The plan's custom feature lines as a clean list of
     * ['name' => string, 'highlight' => bool] entries.
     *
     * Stored in package metadata as `custom_features`, so it survives edits made
     * on the classic package form (which merges metadata rather than replacing it).
     * Entries written before highlighting existed are plain strings and are read
     * back as un-highlighted lines.
     *
     * @param object|array|null $metadata
     * @return array
     */
    private function smart_plans_custom_features_of($metadata)
    {
        $metadata = (array)($metadata ?? []);

        $out = [];
        foreach ((array)($metadata['custom_features'] ?? []) as $item) {
            if (is_string($item) || is_numeric($item)) {
                $name      = trim((string)$item);
                $highlight = false;
            } else {
                $item      = (array)$item;
                $name      = trim((string)($item['name'] ?? ''));
                $highlight = !empty($item['highlight']);
            }

            if ($name !== '') {
                $out[] = ['name' => $name, 'highlight' => $highlight];
            }
        }

        return $out;
    }

    /**
     * Render a custom feature list back into a spreadsheet cell, wrapping the
     * highlighted lines in asterisks so a round-trip keeps the marker.
     *
     * @param array $features
     * @return string
     */
    private function smart_plans_custom_features_cell(array $features)
    {
        $parts = [];
        foreach ($features as $feature) {
            $parts[] = !empty($feature['highlight']) ? '*' . $feature['name'] . '*' : $feature['name'];
        }

        return implode(', ', $parts);
    }

    /**
     * Whether this plan's module-derived features are shown on the website card.
     * Absent/blank means yes, so existing plans keep their current list.
     *
     * @param object|array|null $metadata
     * @return bool
     */
    private function smart_plans_module_features_shown($metadata)
    {
        $metadata = (array)($metadata ?? []);
        $value    = $metadata['show_module_features'] ?? '';

        return !in_array(strtolower(trim((string)$value)), ['0', 'no', 'false', 'off'], true);
    }

    /**
     * Parse a comma-separated cell of custom feature names into a clean list.
     * Semicolons and line breaks are accepted as separators too, duplicates are
     * dropped, and "none"/"-" empties the list.
     *
     * @param string $raw
     * @return array
     */
    private function smart_plans_parse_custom_features($raw)
    {
        $raw = trim((string)$raw);
        if (in_array(strtolower($raw), ['none', '-', '0', 'clear', 'remove'], true)) {
            return [];
        }

        // A comma between digits is a thousands separator, not a separator between
        // features ("1,000 Free SMS/Month" is one line). Park those before splitting.
        $guard = "\x01";
        do {
            $raw = preg_replace('/(\d)\s*,\s*(\d{3})(?!\d)/', '$1' . $guard . '$2', $raw, -1, $guarded);
        } while ($guarded > 0);

        $out = [];
        foreach (preg_split('/[,;\r\n]+/', $raw) as $piece) {
            $piece = str_replace($guard, ',', trim((string)$piece));

            // Asterisks mark the line as highlighted: *Free Setup*, **Free Setup**,
            // or just one on either side. They are a marker, never part of the name.
            $highlight = false;
            if (preg_match('/^\*{1,2}\s*(.+?)\s*\*{1,2}$/u', $piece, $m)) {
                $highlight = true;
                $piece     = $m[1];
            } elseif (preg_match('/^\*{1,2}\s*(.+)$/u', $piece, $m)) {
                $highlight = true;
                $piece     = $m[1];
            } elseif (preg_match('/^(.+?)\s*\*{1,2}$/u', $piece, $m)) {
                $highlight = true;
                $piece     = $m[1];
            }

            // Strip a leading bullet/tick the admin may have pasted in from a card.
            $piece = trim(preg_replace('/^[\x{2022}\x{2713}\x{2714}\-\s]+/u', '', $piece));
            $piece = trim(preg_replace('/\s+/', ' ', $piece));
            if ($piece === '') {
                continue;
            }
            $piece = mb_substr(strip_tags($piece), 0, 120);
            $key   = mb_strtolower($piece);
            if (!isset($out[$key])) {
                // First spelling wins for a repeated feature.
                $out[$key] = ['name' => $piece, 'highlight' => $highlight];
            } elseif ($highlight) {
                $out[$key]['highlight'] = true;
            }
        }

        return array_values($out);
    }

    private function smart_plans_parse_limit($cell)
    {
        $cell = trim((string)$cell);
        $lc   = strtolower($cell);

        if ($lc === strtolower(_l('perfex_saas_pricing_plans_unlimited')) || $lc === 'unlimited' || $cell === '-1') {
            return -1;
        }
        if (is_numeric($cell)) {
            $n = (int)$cell;
            return $n < 0 ? -1 : $n;
        }
        return null;
    }

    /**
     * Normalise a "Plan ID" cell into a positive integer id, tolerating the noise
     * a spreadsheet round-trip adds. Returns 0 when the cell is not a usable id.
     *
     * Accepts: "10", "10.0", " 10 ", non-breaking spaces, a leading text
     * apostrophe ("'10") and thousands separators ("1,000"). Rejects blanks,
     * fractions ("10.5") and anything non-numeric.
     *
     * @param mixed $cell
     * @return int
     */
    private function smart_plans_cell_to_id($cell)
    {
        $raw = (string)$cell;
        // Strip a UTF-8 BOM and convert non-breaking spaces to plain spaces.
        $raw = str_replace(["\xEF\xBB\xBF", "\xC2\xA0", "\xA0"], ['', ' ', ' '], $raw);
        $raw = trim($raw);
        $raw = ltrim($raw, "'");                   // Excel/Sheets text marker
        $raw = str_replace([',', ' '], '', $raw);  // "1,000" / "1 000"

        if ($raw === '' || !is_numeric($raw)) {
            return 0;
        }

        $f = (float)$raw;
        $i = (int)round($f);

        // Must be a positive whole number (10 or 10.0, never 10.5).
        if ($i > 0 && abs($f - $i) < 1e-9) {
            return $i;
        }

        return 0;
    }

    private function smart_plans_to_number($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value));
        if ($clean === '' || !is_numeric($clean)) {
            return null;
        }
        return (float)$clean;
    }

    private function smart_plans_truthy($value)
    {
        if ($value === null) {
            return null;
        }
        $v = strtolower(trim((string)$value));
        if ($v === '') {
            return null;
        }

        $yes = ['1', 'yes', 'y', 'true', 'active', 'enabled', 'on', strtolower(_l('perfex_saas_smart_plans_yes')), strtolower(_l('perfex_saas_pricing_plans_active'))];
        $no  = ['0', 'no', 'n', 'false', 'inactive', 'disabled', 'off', strtolower(_l('perfex_saas_smart_plans_no')), strtolower(_l('perfex_saas_pricing_plans_inactive'))];

        if (in_array($v, $yes, true)) {
            return true;
        }
        if (in_array($v, $no, true)) {
            return false;
        }
        return null;
    }

    private function smart_plans_plain_text($html)
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string)$html), ENT_QUOTES, 'UTF-8')));
    }

    /**
     * Field-label / key (normalised) => schema key.
     *
     * @return array
     */
    private function smart_plans_label_to_key()
    {
        $map = [];
        foreach ($this->smart_plans_columns() as $col) {
            $map[$this->smart_plans_normalise($col['label'])]    = $col['key'];
            $map[$this->smart_plans_strip_parens($col['label'])] = $col['key'];
            $map[$this->smart_plans_normalise($col['key'])]      = $col['key'];
            // Also accept the module system name as the row label.
            if (isset($col['system'])) {
                $map[$this->smart_plans_normalise($col['system'])] = $col['key'];
            }
        }
        // Backward-compatibility with the old single "Enabled Modules" column.
        $map[$this->smart_plans_normalise(_l('perfex_saas_smart_plans_col_modules'))] = 'modules';
        return $map;
    }

    private function smart_plans_resolve_label($label, $label_to_key)
    {
        foreach ([$this->smart_plans_normalise($label), $this->smart_plans_strip_parens($label)] as $candidate) {
            if ($candidate !== '' && isset($label_to_key[$candidate])) {
                return $label_to_key[$candidate];
            }
        }
        return null;
    }

    private function smart_plans_normalise($value)
    {
        $value = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
        return preg_replace('/\s+/', ' ', strtolower(trim($value)));
    }

    private function smart_plans_strip_parens($value)
    {
        $value = preg_replace('/\s*\([^)]*\)\s*/', ' ', (string)$value);
        return $this->smart_plans_normalise($value);
    }

    /**
     * @return string|null Field value for a key, or null when the field is absent
     */
    private function smart_plans_val($fields, $key)
    {
        return array_key_exists($key, $fields) ? (string)$fields[$key] : null;
    }

    private function smart_plans_col_letter($index)
    {
        $letters = '';
        $index++;
        while ($index > 0) {
            $mod     = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index   = (int)(($index - $mod) / 26);
        }
        return $letters;
    }
}
