<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Smart / bulk management logic for the "Pricing Plans" screen.
 *
 * Kept as a trait (mirroring Packages_trait) so the controller stays thin and the
 * heavy lifting can be unit-reasoned about in isolation. All package writes reuse
 * Perfex_saas_model primitives (add_or_update / clone / delete) and plan groups are
 * synced with core customer groups via Client_groups_model.
 */
trait Pricing_plans_trait
{
    /**
     * Recurring period presets used by variant generation and the clone-group feature.
     * Each entry maps onto package metadata->invoice fields.
     *
     * @return array
     */
    public function pricing_plans_period_presets()
    {
        return [
            'monthly'     => ['label' => _l('perfex_saas_monthly'),           'recurring' => 1,        'repeat_type_custom' => 'month', 'repeat_every_custom' => 1],
            'quarterly'   => ['label' => _l('perfex_saas_quarterly'),         'recurring' => 3,        'repeat_type_custom' => 'month', 'repeat_every_custom' => 3],
            'half_yearly' => ['label' => _l('perfex_saas_every_x_months', 6), 'recurring' => 6,        'repeat_type_custom' => 'month', 'repeat_every_custom' => 6],
            'yearly'      => ['label' => _l('perfex_saas_annually'),          'recurring' => 'custom', 'repeat_type_custom' => 'year',  'repeat_every_custom' => 1],
            'biennially'  => ['label' => _l('perfex_saas_biennially'),        'recurring' => 'custom', 'repeat_type_custom' => 'year',  'repeat_every_custom' => 2],
        ];
    }

    /**
     * Create or update a pricing plan group, kept in sync with core customer groups.
     *
     * @param mixed  $id   Group id (empty to create)
     * @param string $name Group name
     * @return array
     */
    public function pricing_plans_save_group($id, $name)
    {
        $CI = &get_instance();
        $CI->load->model('client_groups_model');

        $name = trim((string)$name);
        if ($name === '') {
            return ['success' => false, 'message' => _l('perfex_saas_pricing_plans_group_name_required')];
        }

        if (empty($id)) {
            $new_id = $CI->client_groups_model->add(['name' => $name]);
            return [
                'success' => (bool)$new_id,
                'id'      => $new_id,
                'name'    => $name,
                'message' => _l('added_successfully', _l('perfex_saas_pricing_plans_group')),
            ];
        }

        $CI->client_groups_model->edit(['id' => (int)$id, 'name' => $name]);
        return [
            'success' => true,
            'id'      => (int)$id,
            'name'    => $name,
            'message' => _l('updated_successfully', _l('perfex_saas_pricing_plans_group')),
        ];
    }

    /**
     * Delete a pricing plan group (also removes the matching customer group).
     *
     * @param mixed $id
     * @return array
     */
    public function pricing_plans_delete_group($id)
    {
        $CI = &get_instance();
        $CI->load->model('client_groups_model');

        $success = $CI->client_groups_model->delete((int)$id);
        return [
            'success' => (bool)$success,
            'message' => $success
                ? _l('deleted', _l('perfex_saas_pricing_plans_group'))
                : _l('problem_deleting', _l('perfex_saas_pricing_plans_group')),
        ];
    }

    /**
     * Inline matrix save: update price + active status for many plans at once.
     *
     * @param array $plans [id => ['price' => x, 'status' => 0|1]]
     * @return int Number of plans updated
     */
    public function pricing_plans_bulk_save($plans)
    {
        if (empty($plans) || !is_array($plans)) return 0;

        $CI    = &get_instance();
        $count = 0;

        foreach ($plans as $id => $row) {
            $id = (int)$id;
            if (!$id) continue;

            $update = ['id' => $id, 'status' => !empty($row['status']) ? 1 : 0];
            if (isset($row['price']) && $row['price'] !== '') {
                $update['price'] = (float)$row['price'];
            }

            if ($CI->perfex_saas_model->add_or_update('packages', $update)) $count++;
        }

        return $count;
    }

    /**
     * Run a bulk action over a set of selected plan ids.
     *
     * @param string $action activate|deactivate|delete|clone|price_set|price_percent
     * @param array  $ids
     * @param array  $params Extra params (price, percent)
     * @return array
     */
    public function pricing_plans_bulk_action($action, $ids, $params = [])
    {
        $CI  = &get_instance();
        $ids = array_filter(array_map('intval', (array)$ids));
        if (empty($ids)) {
            return ['status' => 'warning', 'message' => _l('perfex_saas_pricing_plans_no_selection')];
        }

        $affected = 0;
        foreach ($ids as $id) {
            switch ($action) {
                case 'activate':
                    $affected += $CI->perfex_saas_model->add_or_update('packages', ['id' => $id, 'status' => 1]) ? 1 : 0;
                    break;
                case 'deactivate':
                    $affected += $CI->perfex_saas_model->add_or_update('packages', ['id' => $id, 'status' => 0]) ? 1 : 0;
                    break;
                case 'delete':
                    $affected += $this->pricing_plans_safe_delete($id) ? 1 : 0;
                    break;
                case 'clone':
                    $affected += $CI->perfex_saas_model->clone('packages', $id) ? 1 : 0;
                    break;
                case 'set_group':
                    // '0' / empty clears the group (ungrouped)
                    $affected += $CI->perfex_saas_model->set_package_plan_group($id, $params['group'] ?? '') ? 1 : 0;
                    break;
                case 'price_set':
                    $price = (float)($params['price'] ?? 0);
                    $affected += $CI->perfex_saas_model->add_or_update('packages', ['id' => $id, 'price' => $price]) ? 1 : 0;
                    break;
                case 'price_percent':
                    $percent = (float)($params['percent'] ?? 0);
                    $package = $CI->perfex_saas_model->packages($id);
                    if ($package) {
                        $new_price = round((float)$package->price * (1 + $percent / 100), 2);
                        $affected += $CI->perfex_saas_model->add_or_update('packages', ['id' => $id, 'price' => $new_price]) ? 1 : 0;
                    }
                    break;
            }
        }

        return ['status' => 'success', 'message' => _l('perfex_saas_pricing_plans_bulk_done', $affected)];
    }

    /**
     * Bulk update pricing, user/seat limits and modules across selected plans.
     *
     * @param array $ids
     * @param array $fields set_price, price, limitations[key=>value], set_modules, modules[],
     *                      set_disabled_default_modules, disabled_default_modules[]
     * @return array
     */
    public function pricing_plans_bulk_update_fields($ids, $fields)
    {
        $CI  = &get_instance();
        $ids = array_filter(array_map('intval', (array)$ids));
        if (empty($ids)) {
            return ['status' => 'warning', 'message' => _l('perfex_saas_pricing_plans_no_selection')];
        }

        $set_price    = !empty($fields['set_price']);
        $price        = (float)($fields['price'] ?? 0);
        $limitations  = isset($fields['limitations']) && is_array($fields['limitations']) ? $fields['limitations'] : [];
        $set_modules  = !empty($fields['set_modules']);
        $modules      = isset($fields['modules']) ? (array)$fields['modules'] : [];
        $set_disabled = !empty($fields['set_disabled_default_modules']);
        $disabled     = isset($fields['disabled_default_modules']) ? (array)$fields['disabled_default_modules'] : [];

        $affected = 0;
        foreach ($ids as $id) {
            $package = $CI->perfex_saas_model->packages($id);
            if (!$package) continue;

            $update   = ['id' => $id];
            $metadata = (array)($package->metadata ?? []);

            if ($set_price) $update['price'] = $price;

            // User/seat & other resource limits (blank values leave the limit untouched)
            if (!empty($limitations)) {
                $current_limits = isset($metadata['limitations']) ? (array)$metadata['limitations'] : [];
                foreach ($limitations as $key => $val) {
                    if ($val === '') continue;
                    $current_limits[$key] = (int)$val;
                }
                $metadata['limitations'] = $current_limits;
            }

            if ($set_disabled) {
                $metadata['disabled_default_modules'] = array_values(array_filter($disabled));
            }

            $update['metadata'] = json_encode($metadata);

            // Modules live in a dedicated top-level JSON column
            if ($set_modules) {
                $update['modules'] = json_encode(array_values(array_filter($modules)));
            }

            $affected += $CI->perfex_saas_model->add_or_update('packages', $update) ? 1 : 0;
        }

        return ['status' => 'success', 'message' => _l('perfex_saas_pricing_plans_bulk_done', $affected)];
    }

    /**
     * Generate recurring-period variants from a base plan by cloning it.
     *
     * @param int   $base_id
     * @param array $periods Preset keys e.g ['monthly','yearly']
     * @param array $opts    group_id, price_map[period=>price], multiplier_map[period=>x]
     * @return array
     */
    public function pricing_plans_generate_variants($base_id, $periods, $opts = [])
    {
        $CI   = &get_instance();
        $base = $CI->perfex_saas_model->packages((int)$base_id);
        if (!$base) {
            return ['status' => 'danger', 'message' => _l('perfex_saas_pricing_plans_base_required')];
        }

        $presets    = $this->pricing_plans_period_presets();
        $group_id   = $opts['group_id'] ?? '';
        $price_map  = $opts['price_map'] ?? [];
        $mult_map   = $opts['multiplier_map'] ?? [];
        $base_price = (float)$base->price;

        $created = 0;
        foreach ((array)$periods as $period_key) {
            if (!isset($presets[$period_key])) continue;
            $preset = $presets[$period_key];

            // Reuse the model clone so db pools, modules and metadata are copied faithfully
            $clone_id = $CI->perfex_saas_model->clone('packages', (int)$base_id);
            if (!$clone_id) continue;

            $clone    = $CI->perfex_saas_model->packages($clone_id);
            $metadata = $this->pricing_plans_apply_period((array)($clone->metadata ?? []), $preset);

            if ($group_id !== '') $metadata['plan_group_id'] = (int)$group_id;

            // Resolve the variant price (explicit price wins over multiplier over base price)
            $variant_price = $base_price;
            if (isset($price_map[$period_key]) && $price_map[$period_key] !== '') {
                $variant_price = (float)$price_map[$period_key];
            } elseif (isset($mult_map[$period_key]) && $mult_map[$period_key] !== '') {
                $variant_price = round($base_price * (float)$mult_map[$period_key], 2);
            }

            $name = $base->name . ' - ' . $preset['label'];

            $CI->perfex_saas_model->add_or_update('packages', [
                'id'       => $clone_id,
                'name'     => $name,
                'slug'     => perfex_saas_generate_unique_slug($name, 'packages', (string)$clone_id),
                'price'    => $variant_price,
                'status'   => 1,
                'metadata' => json_encode($metadata),
            ]);

            $created++;
        }

        return [
            'status'  => $created ? 'success' : 'warning',
            'message' => _l('perfex_saas_pricing_plans_variants_created', $created),
        ];
    }

    /**
     * Clone every plan in a source group into a target group (optionally changing the period).
     *
     * @param int    $source_group_id (0 = ungrouped plans)
     * @param int    $target_group_id
     * @param string $target_period   Optional preset key; empty keeps each plan's own period
     * @return array
     */
    public function pricing_plans_clone_group($source_group_id, $target_group_id, $target_period = '')
    {
        $CI              = &get_instance();
        $source_group_id = (int)$source_group_id;
        $target_group_id = (int)$target_group_id;

        if (!$target_group_id) {
            return ['status' => 'danger', 'message' => _l('perfex_saas_pricing_plans_target_group_required')];
        }

        $presets  = $this->pricing_plans_period_presets();
        $packages = $CI->perfex_saas_model->packages();

        $created = 0;
        foreach ($packages as $package) {
            $gid = isset($package->metadata->plan_group_id) && $package->metadata->plan_group_id !== ''
                ? (int)$package->metadata->plan_group_id : 0;
            if ($gid !== $source_group_id) continue;

            $clone_id = $CI->perfex_saas_model->clone('packages', (int)$package->id);
            if (!$clone_id) continue;

            $clone    = $CI->perfex_saas_model->packages($clone_id);
            $metadata = (array)($clone->metadata ?? []);
            $metadata['plan_group_id'] = $target_group_id;

            $name = $package->name;
            if ($target_period !== '' && isset($presets[$target_period])) {
                $metadata = $this->pricing_plans_apply_period($metadata, $presets[$target_period]);
                $name     = $package->name . ' - ' . $presets[$target_period]['label'];
            }

            $CI->perfex_saas_model->add_or_update('packages', [
                'id'       => $clone_id,
                'name'     => $name,
                'slug'     => perfex_saas_generate_unique_slug($name, 'packages', (string)$clone_id),
                'status'   => 1,
                'metadata' => json_encode($metadata),
            ]);

            $created++;
        }

        return [
            'status'  => $created ? 'success' : 'warning',
            'message' => _l('perfex_saas_pricing_plans_group_cloned', $created),
        ];
    }

    /**
     * Flatten the grouped matrix into export rows (used by the overview screen,
     * Excel and PDF exports so all three stay consistent).
     *
     * @param array $matrix Output of Perfex_saas_model::packages_grouped_for_matrix()
     * @return array  List of associative rows
     */
    public function pricing_plans_export_rows($matrix)
    {
        $groups = $matrix['groups'];
        $grid   = $matrix['matrix'];

        $rows = [];
        foreach ($groups as $gid => $gname) {
            foreach (($grid[$gid] ?? []) as $period => $plans) {
                foreach ($plans as $p) {
                    $users = isset($p->metadata->limitations->staff) ? (int)$p->metadata->limitations->staff : -1;
                    $modules_count = is_array($p->modules ?? null) ? count($p->modules) : 0;

                    $rows[] = [
                        'group'   => $gname,
                        'plan'    => $p->name,
                        'period'  => $period,
                        'price'   => (float)$p->price,
                        'trial'   => (int)$p->trial_period,
                        'users'   => $users < 0 ? _l('perfex_saas_pricing_plans_unlimited') : $users,
                        'modules' => $modules_count,
                        'status'  => !empty($p->status)
                            ? _l('perfex_saas_pricing_plans_active')
                            : _l('perfex_saas_pricing_plans_inactive'),
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * Apply a recurring-period preset onto a metadata array's invoice block.
     *
     * @param array $metadata
     * @param array $preset
     * @return array
     */
    private function pricing_plans_apply_period(array $metadata, array $preset)
    {
        $invoice = isset($metadata['invoice']) ? (array)$metadata['invoice'] : [];
        $invoice['recurring']           = $preset['recurring'];
        $invoice['repeat_type_custom']  = $preset['repeat_type_custom'];
        $invoice['repeat_every_custom'] = $preset['repeat_every_custom'];
        $metadata['invoice'] = $invoice;

        return $metadata;
    }

    /**
     * Delete a plan when it has no invoices, otherwise deactivate it (mirrors package delete safety).
     *
     * @param int $id
     * @return bool
     */
    private function pricing_plans_safe_delete($id)
    {
        $CI = &get_instance();
        $CI->load->model('invoices_model');

        $CI->invoices_model->db->limit(1);
        $invoices = $CI->invoices_model->get('', [perfex_saas_column('packageid') => $id]);
        if (!empty($invoices)) {
            // Avoid delete if already customers associated
            return false;
        }

        return (bool)$CI->perfex_saas_model->delete('packages', $id);
    }
}
