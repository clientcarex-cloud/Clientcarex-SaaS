<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Voting extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('voting/voting_model', 'voting_model');
    }

    /* Polls dashboard / listing */
    public function index()
    {
        if (!has_permission('voting', '', 'view') && !is_admin()) {
            access_denied('voting');
        }

        $data['title']   = _l('voting');
        $data['polls']   = $this->voting_model->get_polls();
        $data['summary'] = $this->voting_model->get_summary();

        $this->load->view('manage', $data);
    }

    /* Create / edit a poll (questions builder) */
    public function poll($id = '')
    {
        if (!has_permission('voting', '', 'view') && !is_admin()) {
            access_denied('voting');
        }

        if ($this->input->post()) {
            $post = $this->input->post();

            // Questions arrive as a JSON blob from the builder. Re-fetch
            // without XSS filtering so text survives, views escape on output.
            $questions         = json_decode($this->input->post('questions', false), true);
            $post['questions'] = is_array($questions) ? $questions : [];

            if ($id == '') {
                if (!has_permission('voting', '', 'create') && !is_admin()) {
                    access_denied('voting');
                }
                $new_id = $this->voting_model->add_poll($post);
                if ($new_id) {
                    set_alert('success', _l('voting_saved'));
                    redirect(admin_url('voting/monitor/' . $new_id));
                }
            } else {
                if (!has_permission('voting', '', 'edit') && !is_admin()) {
                    access_denied('voting');
                }
                $this->voting_model->update_poll($id, $post);
                set_alert('success', _l('voting_saved'));
                redirect(admin_url('voting/monitor/' . $id));
            }
        }

        $data['title'] = ($id == '') ? _l('voting_new_poll') : _l('voting_edit_poll');

        if ($id != '') {
            $data['poll'] = $this->voting_model->get_polls($id);
            if (!$data['poll']) {
                show_404();
            }
        }

        $this->load->view('builder', $data);
    }

    /* Live control room */
    public function monitor($id)
    {
        if (!has_permission('voting', '', 'view') && !is_admin()) {
            access_denied('voting');
        }

        $poll = $this->voting_model->get_polls($id);
        if (!$poll) {
            show_404();
        }

        $data['title'] = _l('voting_monitor') . ' — ' . $poll->title;
        $data['poll']  = $poll;
        $data['state'] = $this->voting_model->admin_state($poll);

        $this->load->view('monitor', $data);
    }

    /* Live control actions (AJAX) */
    public function action($id)
    {
        if (!$this->input->is_ajax_request() || !$this->input->post()) {
            show_404();
        }
        if (!has_permission('voting', '', 'edit') && !is_admin()) {
            ajax_access_denied();
        }

        $poll = $this->voting_model->get_polls($id);
        if (!$poll) {
            show_404();
        }

        $act = $this->input->post('act');

        switch ($act) {
            case 'start':
            case 'reopen':
                $this->voting_model->set_status($poll->id, 'live');
                break;
            case 'end':
                $this->voting_model->set_status($poll->id, 'ended');
                break;
            case 'hold':
                $this->voting_model->set_active_question($poll->id, null);
                break;
            case 'next':
                $this->voting_model->next_question($poll);
                break;
            case 'activate':
                $this->voting_model->set_active_question($poll->id, (int) $this->input->post('question_id'));
                break;
            case 'reset':
                if (!has_permission('voting', '', 'delete') && !is_admin()) {
                    ajax_access_denied();
                }
                $qid = (int) $this->input->post('question_id');
                $this->voting_model->reset_votes($poll->id, $qid ?: null);
                break;
            default:
                echo json_encode(['success' => false]);

                return;
        }

        // Return fresh state so the monitor updates instantly
        $poll = $this->voting_model->get_polls($id);
        echo json_encode(['success' => true, 'state' => $this->voting_model->admin_state($poll)]);
    }

    /* Monitor auto-refresh state (AJAX) */
    public function state($id)
    {
        if (!has_permission('voting', '', 'view') && !is_admin()) {
            ajax_access_denied();
        }

        $poll = $this->voting_model->get_polls($id);
        if (!$poll) {
            show_404();
        }

        echo json_encode($this->voting_model->admin_state($poll));
    }

    public function delete($id)
    {
        if (!has_permission('voting', '', 'delete') && !is_admin()) {
            access_denied('voting');
        }

        $this->voting_model->delete_poll($id);
        set_alert('success', _l('voting_deleted'));
        redirect(admin_url('voting'));
    }
}
