<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * DUNS Support desk — admin/duns_support/...
 *
 *   index            orders list, filters, stats
 *   order/{id}       one order: documents, payment, deadline, timeline, delivery
 *   plans            price list shown on the landing page
 *   settings         landing texts, contact, ad tags, payments, notifications
 *   export           CSV of the filtered orders
 */
class Duns_support extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        duns_maybe_upgrade_schema();
        if (!duns_can('view')) {
            access_denied('duns_support');
        }
        $this->load->model('duns_support/duns_support_model', 'dm');
    }

    private function filters()
    {
        $f = [];
        foreach (['status', 'plan_id', 'from', 'to', 'q'] as $k) {
            $v = trim((string) $this->input->get($k, true));
            if ($v !== '') {
                $f[$k] = $v;
            }
        }

        return $f;
    }

    /* ═══════════════════════════════ Orders ══════════════════════════════ */

    public function index()
    {
        $f      = $this->filters();
        $per    = 25;
        $page   = max(1, (int) $this->input->get('page'));
        $total  = $this->dm->count_orders($f);

        $this->load->view('admin/orders', [
            'title'  => _l('duns_support') . ' — Orders',
            'active' => 'orders',
            'orders' => $this->dm->list_orders($f, $per, ($page - 1) * $per),
            'stats'  => $this->dm->stats(),
            'plans'  => $this->dm->get_plans(),
            'f'      => $f,
            'page'   => $page,
            'pages'  => max(1, (int) ceil($total / $per)),
            'total'  => $total,
        ]);
    }

    public function order($id = 0)
    {
        $order = $this->dm->get($id);
        if (!$order) {
            set_alert('warning', 'Order not found.');
            redirect(admin_url('duns_support'));
        }

        $this->load->model('staff_model');
        $invoice = null;
        if ($order->invoice_id) {
            $this->load->model('invoices_model');
            $invoice = $this->invoices_model->get((int) $order->invoice_id);
        }

        $this->load->view('admin/order', [
            'title'   => $order->order_no . ' — ' . $order->company_name,
            'active'  => 'orders',
            'order'   => $order,
            'files'   => $this->dm->get_files($order->id),
            'events'  => $this->dm->get_events($order->id),
            'invoice' => $invoice,
            'staff'   => $this->staff_model->get('', ['active' => 1]),
        ]);
    }

    private function require_post_edit($id)
    {
        if (!duns_can('edit')) {
            access_denied('duns_support');
        }
        if ($this->input->method() !== 'post' || !$this->dm->get($id)) {
            redirect(admin_url('duns_support'));
        }
    }

    public function status($id)
    {
        $this->require_post_edit($id);
        $res = $this->dm->set_status($id, (string) $this->input->post('status', true), trim((string) $this->input->post('note', true)), get_staff_user_id());
        set_alert($res === true ? 'success' : 'danger', $res === true ? 'Status updated.' : $res);
        redirect(admin_url('duns_support/order/' . (int) $id));
    }

    public function deliver($id)
    {
        $this->require_post_edit($id);
        $res = $this->dm->deliver($id, (string) $this->input->post('duns_number', true), trim((string) $this->input->post('note', true)), (bool) $this->input->post('send_email'), get_staff_user_id());
        set_alert($res === true ? 'success' : 'danger', $res === true ? 'DUNS number saved and the order is completed.' : $res);
        redirect(admin_url('duns_support/order/' . (int) $id));
    }

    public function note($id)
    {
        $this->require_post_edit($id);
        $msg = trim((string) $this->input->post('message', true));
        if ($msg !== '') {
            $this->dm->add_event($id, 'note', $msg, get_staff_user_id());
        }
        redirect(admin_url('duns_support/order/' . (int) $id) . '#timeline');
    }

    public function update($id)
    {
        $this->require_post_edit($id);
        $res = $this->dm->update_details($id, $this->input->post(null, true));
        set_alert($res === true ? 'success' : 'danger', $res === true ? 'Order updated.' : $res);
        redirect(admin_url('duns_support/order/' . (int) $id));
    }

    public function upload($id)
    {
        $this->require_post_edit($id);
        $file = $_FILES['file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            set_alert('warning', 'Choose a file first.');
        } else {
            $type = $this->input->post('doc_type') === 'coi' ? 'coi' : 'other';
            $res  = $this->dm->store_file($id, $file, $type, get_staff_user_id());
            if (is_int($res)) {
                $this->dm->add_event($id, 'system', 'Document uploaded by staff: ' . $file['name'], get_staff_user_id());
            }
            set_alert(is_int($res) ? 'success' : 'danger', is_int($res) ? 'Document uploaded.' : $res);
        }
        redirect(admin_url('duns_support/order/' . (int) $id));
    }

    /** Stream a stored document. ?dl=1 forces a download, otherwise it opens inline. */
    public function file($file_id)
    {
        $f = $this->dm->get_file($file_id);
        $path = $f ? duns_upload_dir($f->order_id) . $f->file_name : null;
        if (!$f || !is_file($path)) {
            show_404();
        }
        $disp = $this->input->get('dl') ? 'attachment' : 'inline';
        header('Content-Type: ' . ($f->mime ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . $disp . '; filename="' . str_replace('"', '', $f->original_name) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function delete_file($file_id)
    {
        if (!duns_can('edit') || $this->input->method() !== 'post') {
            access_denied('duns_support');
        }
        $f = $this->dm->get_file($file_id);
        if ($f) {
            $this->dm->delete_file($file_id);
            set_alert('success', 'Document removed.');
            redirect(admin_url('duns_support/order/' . (int) $f->order_id));
        }
        redirect(admin_url('duns_support'));
    }

    public function delete($id)
    {
        if (!duns_can('delete') || $this->input->method() !== 'post') {
            access_denied('duns_support');
        }
        $this->dm->delete_order($id);
        set_alert('success', 'Order deleted.');
        redirect(admin_url('duns_support'));
    }

    public function export()
    {
        $rows = $this->dm->list_orders($this->filters(), 100000, 0);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="duns-orders-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Order', 'Created', 'Status', 'Plan', 'Hours', 'Amount', 'Paid', 'Gateway', 'Paid at', 'Due at', 'Completed at', 'DUNS number',
            'Company', 'CIN', 'Director', 'Director email', 'Director mobile', 'Work email', 'UTM source', 'UTM medium', 'UTM campaign']);
        foreach ($rows as $o) {
            fputcsv($out, [$o->order_no, $o->created_at, duns_statuses()[$o->status]['label'] ?? $o->status, $o->plan_name, $o->delivery_hours,
                $o->amount, $o->amount_paid, $o->gateway, $o->paid_at, $o->due_at, $o->completed_at, $o->duns_number ? duns_format_number($o->duns_number) : '',
                $o->company_name, $o->cin, $o->director_name, $o->director_email, $o->director_mobile, $o->work_email, $o->utm_source, $o->utm_medium, $o->utm_campaign]);
        }
        fclose($out);
        exit;
    }

    /* ═══════════════════════════════ Plans ═══════════════════════════════ */

    public function plans()
    {
        if (!duns_can('edit')) {
            access_denied('duns_support');
        }

        if ($this->input->method() === 'post') {
            $id  = (int) $this->input->post('id');
            $res = $this->dm->save_plan($this->input->post(null, true), $id ?: null);
            set_alert(is_int($res) ? 'success' : 'danger', is_int($res) ? 'Plan saved.' : $res);
            redirect(admin_url('duns_support/plans'));
        }

        $edit = $this->input->get('edit') ? $this->dm->get_plan((int) $this->input->get('edit')) : null;
        $this->load->view('admin/plans', [
            'title'  => _l('duns_support') . ' — Plans',
            'active' => 'plans',
            'plans'  => $this->dm->get_plans(),
            'edit'   => $edit,
            'is_new' => $this->input->get('new') !== null,
        ]);
    }

    public function delete_plan($id)
    {
        if (!duns_can('edit') || $this->input->method() !== 'post') {
            access_denied('duns_support');
        }
        $this->dm->delete_plan($id);
        set_alert('success', 'Plan deleted. Existing orders keep their plan details.');
        redirect(admin_url('duns_support/plans'));
    }

    /* ══════════════════════════════ Settings ═════════════════════════════ */

    public function settings()
    {
        if (!is_admin()) {
            access_denied('duns_support');
        }

        if ($this->input->method() === 'post') {
            $post = $this->input->post(null, true);
            foreach (['duns_brand_name', 'duns_support_phone', 'duns_support_whatsapp', 'duns_support_email', 'duns_notify_emails',
                'duns_hero_title', 'duns_hero_subtitle', 'duns_meta_pixel_id', 'duns_ga4_id', 'duns_gads_id', 'duns_gads_label', 'duns_terms_url'] as $k) {
                update_option($k, trim((string) ($post[$k] ?? '')));
            }
            foreach (['duns_landing_enabled', 'duns_pay_enabled', 'duns_pay_use_master', 'duns_email_customer'] as $k) {
                update_option($k, !empty($post[$k]) ? '1' : '0');
            }
            // Only gateways this install actually registers may be stored
            $known  = array_keys(duns_all_gateways());
            $picked = array_values(array_intersect($known, (array) ($post['duns_pay_gateways'] ?? [])));
            update_option('duns_pay_gateways', json_encode($picked));

            set_alert('success', 'Settings saved.');
            redirect(admin_url('duns_support/settings'));
        }

        $this->load->view('admin/settings', [
            'title'     => _l('duns_support') . ' — Settings',
            'active'    => 'settings',
            'gateways'  => duns_all_gateways(),
            'usable'    => duns_pay_gateways(),
            'is_tenant' => function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant(),
        ]);
    }
}
