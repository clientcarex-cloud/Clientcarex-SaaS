<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Shra extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('shra/shra_model');
        $this->load->helper('shra/shra');

        if (!shra_can_access()) {
            access_denied('shra');
        }
    }

    private function need($cap)
    {
        if (!shra_can($cap)) {
            access_denied('shra');
        }
    }

    private function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    private function brand()
    {
        return [
            'name'             => get_option('shra_academy_name') ?: 'Stallion Horse Riding Academy',
            'tagline'          => get_option('shra_tagline'),
            'logo_path'        => shra_logo_pdf_path(),
            'contact'          => get_option('shra_contact_line'),
            'chief_instructor' => get_option('shra_chief_instructor') ?: 'Chief Instructor',
            'director'         => get_option('shra_director') ?: 'Director',
        ];
    }

    /* ═══════════════════════ Dashboard ═══════════════════════ */

    public function index()
    {
        if (!shra_can('view')) {
            redirect(admin_url(shra_can_billing() ? 'shra/billing' : 'shra/attendance'));
        }

        $data['title']      = _l('shra');
        $data['summary']    = $this->shra_model->get_summary();
        $data['spark']      = $this->shra_model->sessions_by_day(14);
        $data['recent']     = $this->shra_model->get_enrollments([], 8);
        $data['today']      = $this->shra_model->get_attendance(['date' => date('Y-m-d')], 10);
        $data['new_riders'] = $this->shra_model->get_riders([]);
        $data['new_riders'] = array_slice($data['new_riders'], 0, 6);
        $data['offer']      = shra_offer();

        $this->load->view('dashboard', $data);
    }

    /* ═══════════════════════ Riders ═══════════════════════ */

    public function riders()
    {
        $this->need('view');

        $filters = [
            'q'      => $this->input->get('q'),
            'type'   => $this->input->get('type'),
            'status' => $this->input->get('status'),
            'level'  => $this->input->get('level'),
        ];
        $data['title']   = _l('shra_riders');
        $data['filters'] = $filters;
        $data['riders']  = $this->shra_model->get_riders($filters);
        $data['levels']  = shra_riding_levels();

        $this->load->view('riders', $data);
    }

    public function rider_form($id = '')
    {
        $this->need($id ? 'edit' : 'create');

        if ($this->input->post()) {
            $post = $this->input->post(null, true);
            $post['terms_accepted'] = !empty($post['terms_accepted']) ? 1 : 0;

            if (empty($post['full_name']) || empty($post['mobile'])) {
                set_alert('danger', 'Rider name and mobile number are required.');
                redirect($_SERVER['HTTP_REFERER'] ?? admin_url('shra/riders'));
            }

            if ($id) {
                $this->shra_model->update_rider($id, $post);
                set_alert('success', _l('shra_rider_saved'));
                redirect(admin_url('shra/rider/' . $id));
            }

            $new_id = $this->shra_model->add_rider($post, 'staff');
            if ($new_id) {
                set_alert('success', _l('shra_rider_saved'));
                redirect(admin_url(($this->input->post('then') === 'bill' ? 'shra/billing?rider=' : 'shra/rider/') . $new_id));
            }
            set_alert('danger', 'Could not save the rider.');
        }

        $data['title']  = $id ? 'Edit rider' : 'New rider';
        $data['rider']  = $id ? $this->shra_model->get_rider($id) : null;
        if ($id && !$data['rider']) {
            show_404();
        }
        $data['levels'] = shra_riding_levels();
        $data['terms']  = get_option('shra_terms');

        $this->load->view('rider_form', $data);
    }

    public function rider($id)
    {
        $this->need('view');

        $rider = $this->shra_model->get_rider($id);
        if (!$rider) {
            show_404();
        }

        $data['title']       = $rider->full_name;
        $data['rider']       = $rider;
        $data['enrollments'] = $this->shra_model->get_enrollments(['rider_id' => $id]);
        $data['attendance']  = $this->shra_model->get_attendance(['rider_id' => $id], 100);

        $this->load->view('rider', $data);
    }

    public function delete_rider($id)
    {
        $this->need('delete');
        if ($this->shra_model->delete_rider($id)) {
            set_alert('success', _l('shra_deleted'));
        }
        redirect(admin_url('shra/riders'));
    }

    /** AJAX rider search (billing + attendance pickers). */
    public function search()
    {
        $riders = $this->shra_model->search_riders((string) $this->input->get('q'));
        $this->json(['riders' => $riders]);
    }

    public function membership_pdf($rider_id)
    {
        $this->need('view');
        $rider = $this->shra_model->get_rider($rider_id);
        if (!$rider || $rider->rider_type !== 'learner') {
            show_404();
        }
        $this->output_membership($rider);
    }

    private function output_membership($rider)
    {
        require_once(module_dir_path(SHRA_MODULE_NAME, 'libraries/Shra_pdf.php'));
        $pdf = new Shra_pdf($this->brand(), 'P');
        $arr = (array) $rider;
        $arr['qr_text'] = site_url('join/' . get_option('shra_public_token') . '/verify/' . $rider->rider_no);
        $pdf->membership($arr);
        $pdf->Output('Membership-' . ($rider->membership_no ?: $rider->rider_no) . '.pdf', 'I');
    }

    /* ═══════════════════════ Billing ═══════════════════════ */

    public function billing()
    {
        if (!shra_can_billing()) {
            access_denied('shra');
        }
        $packages = $this->shra_model->get_packages(true);
        $map      = [];
        foreach ($packages as $p) {
            $map[$p->id] = $p;
        }

        $data['title']         = _l('shra_billing');
        $data['packages']      = $packages;
        $data['packages_map']  = $map;
        $data['offer']         = shra_offer();
        // Offline modes only (cash / UPI / card at the counter)
        $data['payment_modes'] = $this->db->where('active', 1)->where('expenses_only', 0)->order_by('id', 'ASC')->get(db_prefix() . 'payment_modes')->result();
        $data['trainers']      = $this->shra_model->get_trainers();
        $data['preselect']     = $this->input->get('rider') ? $this->shra_model->get_rider((int) $this->input->get('rider')) : null;
        $data['currency']      = get_base_currency();

        $this->load->view('billing', $data);
    }

    /** AJAX: create the bill. */
    public function bill()
    {
        if (!shra_can_billing()) {
            $this->json(['success' => false, 'message' => 'Not allowed.']);

            return;
        }

        $rider_id   = (int) $this->input->post('rider_id');
        $package_id = (int) $this->input->post('package_id');
        $opts       = [
            'discount_percent' => $this->input->post('discount_percent') !== null && $this->input->post('discount_percent') !== '' ? (float) $this->input->post('discount_percent') : null,
            'paid_amount'      => $this->input->post('paid_amount'),
            'payment_mode'     => (string) $this->input->post('payment_mode'),
            'reference'        => (string) $this->input->post('reference'),
            'notes'            => (string) $this->input->post('notes'),
            'mark_now'         => (int) $this->input->post('mark_now') === 1,
            'trainer_id'       => (int) $this->input->post('trainer_id') ?: null,
        ];

        $res = $this->shra_model->create_bill($rider_id, $package_id, $opts);
        if (is_string($res)) {
            $this->json(['success' => false, 'message' => $res]);

            return;
        }

        $e    = $this->shra_model->get_enrollment($res['enrollment_id']);
        $html = $this->load->view('partials/bill_done', ['e' => $e], true);

        $this->json(['success' => true, 'html' => $html, 'invoice_id' => $res['invoice_id']]);
    }

    /* ═══════════════════════ Enrollments ═══════════════════════ */

    public function enrollments()
    {
        $this->need('view');

        $filters = [
            'q'      => $this->input->get('q'),
            'status' => $this->input->get('status'),
            'from'   => $this->input->get('from'),
            'to'     => $this->input->get('to'),
        ];
        $data['title']       = _l('shra_enrollments');
        $data['filters']     = $filters;
        $data['enrollments'] = $this->shra_model->get_enrollments($filters);

        $this->load->view('enrollments', $data);
    }

    public function complete($id)
    {
        $this->need('edit');
        $this->shra_model->complete_enrollment($id, true);
        set_alert('success', _l('shra_course_completed'));
        redirect($_SERVER['HTTP_REFERER'] ?? admin_url('shra/enrollments'));
    }

    public function cancel_enrollment($id)
    {
        $this->need('delete');
        $this->shra_model->cancel_enrollment($id);
        set_alert('success', 'Enrollment cancelled. The invoice was left untouched — cancel it from Sales if needed.');
        redirect($_SERVER['HTTP_REFERER'] ?? admin_url('shra/enrollments'));
    }

    public function certificate($id)
    {
        $this->need('edit');
        $no = $this->shra_model->issue_certificate($id);
        if (!$no) {
            set_alert('danger', 'Certificates are only issued for learner packages.');
            redirect($_SERVER['HTTP_REFERER'] ?? admin_url('shra/enrollments'));
        }
        redirect(admin_url('shra/certificate_pdf/' . $id));
    }

    public function certificate_pdf($id)
    {
        $this->need('view');
        $e = $this->shra_model->get_enrollment($id);
        if (!$e || empty($e->certificate_no)) {
            show_404();
        }

        require_once(module_dir_path(SHRA_MODULE_NAME, 'libraries/Shra_pdf.php'));
        $pdf = new Shra_pdf($this->brand(), 'L');
        $arr = (array) $e;
        $arr['issued_at'] = $e->certificate_issued_at;
        $arr['qr_text']   = site_url('join/' . get_option('shra_public_token') . '/verify/' . $e->rider_no . '/' . $e->certificate_no);
        $pdf->certificate($arr);
        $pdf->Output('Certificate-' . $e->certificate_no . '.pdf', 'I');
    }

    /* ═══════════════════════ Attendance ═══════════════════════ */

    public function attendance()
    {
        if (!shra_can_attendance()) {
            access_denied('shra');
        }

        $date = $this->input->get('date') ?: date('Y-m-d');

        $data['title']     = _l('shra_attendance');
        $data['date']      = $date;
        $data['today']     = $this->shra_model->get_attendance(['date' => $date], 200);
        $data['trainers']  = $this->shra_model->get_trainers();
        $data['preselect'] = $this->input->get('rider') ? $this->shra_model->get_rider((int) $this->input->get('rider')) : null;

        $this->load->view('attendance', $data);
    }

    /** AJAX: active enrollments for a rider. */
    public function rider_enrollments()
    {
        $rows = $this->shra_model->active_enrollments((int) $this->input->get('rider_id'));
        foreach ($rows as $r) {
            $r->expires_at_f = $r->expires_at ? _d($r->expires_at) : '';
        }
        $this->json(['enrollments' => $rows]);
    }

    /** AJAX: mark one session. */
    public function mark()
    {
        if (!shra_can_attendance()) {
            $this->json(['success' => false, 'message' => 'Not allowed.']);

            return;
        }

        $enrollment_id = (int) $this->input->post('enrollment_id');
        $res           = $this->shra_model->mark_attendance($enrollment_id, [
            'trainer_id'   => (int) $this->input->post('trainer_id') ?: get_staff_user_id(),
            'horse_name'   => (string) $this->input->post('horse_name'),
            'notes'        => (string) $this->input->post('notes'),
            'session_date' => (string) $this->input->post('session_date'),
        ]);

        if (is_string($res)) {
            $this->json(['success' => false, 'message' => $res]);

            return;
        }

        $e         = $this->shra_model->get_enrollment($enrollment_id);
        $completed = $e && $e->status === 'completed';
        $date      = $this->input->post('session_date') ?: date('Y-m-d');

        $this->json([
            'success'    => true,
            'message'    => 'Session ' . $e->sessions_used . ' of ' . $e->sessions_total . ' marked for ' . $e->full_name . '.',
            'completed'  => $completed,
            'html'       => $completed ? $this->load->view('partials/course_done', ['e' => $e], true) : '',
            'today_html' => $this->load->view('partials/today_list', ['today' => $this->shra_model->get_attendance(['date' => $date], 200), 'date' => $date], true),
        ]);
    }

    public function undo()
    {
        if (!shra_can_attendance()) {
            $this->json(['success' => false, 'message' => 'Not allowed.']);

            return;
        }
        $a   = $this->db->where('id', (int) $this->input->post('id'))->get(db_prefix() . 'shra_attendance')->row();
        $res = $this->shra_model->undo_attendance((int) $this->input->post('id'));
        $date = $a ? $a->session_date : date('Y-m-d');

        $this->json([
            'success'    => $res === true,
            'message'    => is_string($res) ? $res : '',
            'today_html' => $this->load->view('partials/today_list', ['today' => $this->shra_model->get_attendance(['date' => $date], 200), 'date' => $date], true),
        ]);
    }

    public function attendance_log()
    {
        $this->need('view');

        $filters = [
            'from'       => $this->input->get('from') ?: date('Y-m-01'),
            'to'         => $this->input->get('to') ?: date('Y-m-d'),
            'trainer_id' => $this->input->get('trainer_id'),
        ];
        $data['title']    = 'Attendance log';
        $data['filters']  = $filters;
        $data['rows']     = $this->shra_model->get_attendance($filters, 1000);
        $data['trainers'] = $this->shra_model->get_trainers();

        $this->load->view('attendance_log', $data);
    }

    /* ═══════════════════════ Packages ═══════════════════════ */

    public function packages()
    {
        if (!is_admin()) {
            access_denied('shra');
        }

        if ($this->input->post()) {
            $id = (int) $this->input->post('id');
            if ($this->shra_model->save_package($this->input->post(null, true), $id ?: null)) {
                set_alert('success', _l('shra_package_saved'));
            } else {
                set_alert('danger', 'Package name is required.');
            }
            redirect(admin_url('shra/packages'));
        }

        $data['title']    = _l('shra_packages');
        $data['packages'] = $this->shra_model->get_packages(false);
        $data['offer']    = shra_offer();

        $this->load->view('packages', $data);
    }

    public function delete_package($id)
    {
        if (!is_admin()) {
            access_denied('shra');
        }
        $this->shra_model->delete_package($id);
        set_alert('success', _l('shra_deleted'));
        redirect(admin_url('shra/packages'));
    }

    /* ═══════════════════════ Settings / QR ═══════════════════════ */

    public function settings()
    {
        if (!is_admin()) {
            access_denied('shra');
        }

        if ($this->input->post()) {
            $post = $this->input->post(null, false);
            $keys = ['shra_academy_name', 'shra_tagline', 'shra_contact_line', 'shra_offer_percent', 'shra_offer_label', 'shra_offer_ends',
                'shra_minor_age', 'shra_riding_levels', 'shra_chief_instructor', 'shra_director', 'shra_terms'];
            foreach ($keys as $k) {
                if (isset($post[$k])) {
                    update_option($k, is_string($post[$k]) ? trim($post[$k]) : $post[$k]);
                }
            }
            update_option('shra_offer_active', !empty($post['shra_offer_active']) ? '1' : '0');
            update_option('shra_auto_certificate', !empty($post['shra_auto_certificate']) ? '1' : '0');

            if (!empty($post['regenerate_token'])) {
                update_option('shra_public_token', bin2hex(random_bytes(6)));
            }

            if (!empty($_FILES['logo']['name'])) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['png', 'jpg', 'jpeg']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK && $_FILES['logo']['size'] <= 3 * 1024 * 1024) {
                    $dir = FCPATH . 'uploads/shra/';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $name = 'logo_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . $name)) {
                        $old = get_option('shra_logo');
                        if ($old && is_file($dir . $old)) {
                            @unlink($dir . $old);
                        }
                        update_option('shra_logo', $name);
                    }
                } else {
                    set_alert('warning', 'Logo must be a PNG/JPG under 3 MB.');
                }
            }
            if (!empty($post['remove_logo'])) {
                $old = get_option('shra_logo');
                if ($old && is_file(FCPATH . 'uploads/shra/' . $old)) {
                    @unlink(FCPATH . 'uploads/shra/' . $old);
                }
                update_option('shra_logo', '');
            }

            set_alert('success', _l('shra_settings_saved'));
            redirect(admin_url('shra/settings'));
        }

        $data['title'] = _l('shra_settings');
        $this->load->view('settings', $data);
    }

    /** Printable QR poster for the reception desk. */
    public function qr()
    {
        $this->need('view');
        $data['title'] = 'Self registration QR';
        $data['url']   = shra_join_url();
        $data['svg']   = shra_qr_svg($data['url'], 6);
        $this->load->view('qr_poster', $data);
    }
}
