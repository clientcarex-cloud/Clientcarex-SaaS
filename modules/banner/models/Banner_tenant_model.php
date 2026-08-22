<?php

defined('BASEPATH') || exit('No direct script access allowed');

/**
 * Banner — Tenant Banner (master control) model.
 *
 * MASTER-ONLY. Owns a single table that lives in the MASTER database:
 *   <mp>banner_tenant_banner
 *
 * A "tenant banner" is authored once on the master account and shown on the
 * dashboards of ALL tenants or a SELECTED subset. Tenants never receive a copy
 * of the row — they read this master table cross-DB at render time (see
 * get_tenant_banner_details() in the banner helper), so an edit here reflects
 * everywhere instantly and the image is served from the master domain via the
 * stored absolute `image_url`.
 */
class Banner_tenant_model extends App_Model {
    /** @var string master DB prefix */
    private $mp;

    public function __construct() {
        parent::__construct();
        $this->mp = function_exists('perfex_saas_master_db_prefix')
            ? perfex_saas_master_db_prefix()
            : db_prefix();

        $this->_install_table();
    }

    public function table() {
        return $this->mp . 'banner_tenant_banner';
    }

    /**
     * Self-healing schema (master DB).
     */
    private function _install_table() {
        if ($this->db->table_exists($this->table())) {
            return;
        }

        $this->db->query('CREATE TABLE IF NOT EXISTS `' . $this->table() . '` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(191) NOT NULL DEFAULT "",
            `detail` VARCHAR(255) NOT NULL DEFAULT "",
            `image_url` TEXT DEFAULT NULL,
            `status` TINYINT(1) NOT NULL DEFAULT 1,
            `start_date` DATE DEFAULT NULL,
            `end_date` DATE DEFAULT NULL,
            `admin_area` TINYINT(1) NOT NULL DEFAULT 1,
            `clients_area` TINYINT(1) NOT NULL DEFAULT 0,
            `has_action` TINYINT(1) NOT NULL DEFAULT 0,
            `action_label` VARCHAR(250) DEFAULT NULL,
            `label_color` VARCHAR(50) DEFAULT NULL,
            `action_url` TEXT DEFAULT NULL,
            `action_target` TINYINT(1) NOT NULL DEFAULT 0,
            `target_type` VARCHAR(20) NOT NULL DEFAULT "all",
            `target_slugs` TEXT DEFAULT NULL,
            `date_added` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set . ';');
    }

    public function get_all() {
        return $this->db->order_by('id', 'DESC')->get($this->table())->result();
    }

    public function get($id) {
        return $this->db->where('id', (int) $id)->get($this->table())->row();
    }

    /**
     * Insert or update a tenant banner. Returns the row id.
     */
    public function save($data, $id = 0) {
        $id = (int) $id;
        if ($id) {
            $this->db->where('id', $id)->update($this->table(), $data);
            return $id;
        }
        $this->db->insert($this->table(), $data);
        return $this->db->insert_id();
    }

    public function delete($id) {
        $row = $this->get($id);
        $this->db->where('id', (int) $id)->delete($this->table());
        $deleted = $this->db->affected_rows() > 0;

        if ($deleted && $row && !empty($row->detail)) {
            $path = get_upload_path_by_type('banner') . '/' . $row->detail;
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return $deleted;
    }

    public function change_status($id, $status) {
        return $this->db->where('id', (int) $id)->update($this->table(), ['status' => $status ? 1 : 0]);
    }

    /**
     * All SaaS tenant companies (each has ->slug and ->name).
     *
     * @return array
     */
    public function get_companies() {
        if (!function_exists('perfex_saas_master_db_prefix')
            || !$this->db->table_exists($this->mp . 'perfex_saas_companies')) {
            return [];
        }

        $this->load->model('perfex_saas/perfex_saas_model');
        $companies = $this->perfex_saas_model->companies();

        return is_array($companies) ? $companies : [];
    }
}
