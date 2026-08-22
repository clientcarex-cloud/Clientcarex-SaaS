<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Public live voting pages — no authentication required.
 * Routed in application/config/routes.php:
 *
 *   /vote/{code} → live()   telecast screen: question + QR + live results
 *   /v/{code}    → ballot() voter page: vote only, NO results exposed
 *
 * The QR on the screen points to the voter link. Both pages poll their
 * state endpoint every few seconds; votes are anonymous, deduped per
 * device via a client-generated token.
 */
class Voting_public extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('voting/voting_model', 'voting_model');
    }

    /* Telecast screen (projector / webinar share): question + QR + live chart */
    public function live($code = '')
    {
        $poll = $this->voting_model->get_poll_by_code($code);

        if (!$poll || $poll->status === 'draft') {
            $this->_not_available();

            return;
        }

        $data['title'] = $poll->title;
        $data['poll']  = $poll;
        $data['state'] = $this->voting_model->public_state($poll);
        $this->load->view('public_live', $data);
    }

    /* Voter ballot page: cast a vote only, results are never shown here */
    public function ballot($code = '')
    {
        $poll = $this->voting_model->get_poll_by_code($code);

        if (!$poll || $poll->status === 'draft') {
            $this->_not_available();

            return;
        }

        $data['title'] = $poll->title;
        $data['poll']  = $poll;
        $data['state'] = $this->voting_model->ballot_state($poll);
        $this->load->view('public_ballot', $data);
    }

    /* Screen state (includes results), polled by the telecast page (AJAX GET) */
    public function state($code = '')
    {
        $poll = $this->voting_model->get_poll_by_code($code);
        if (!$poll) {
            show_404();
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($this->voting_model->public_state($poll));
    }

    /* Ballot state (no results), polled by the voter page (AJAX GET).
       The device token rides along so the screen can count joined devices. */
    public function bstate($code = '')
    {
        $poll = $this->voting_model->get_poll_by_code($code);
        if (!$poll) {
            show_404();
        }

        $vt = (string) $this->input->get('vt');
        if (preg_match('/^[a-zA-Z0-9\-]{8,64}$/', $vt)) {
            $this->voting_model->touch_device($poll->id, $vt);
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($this->voting_model->ballot_state($poll));
    }

    /* Cast / change a vote (AJAX POST) */
    public function vote()
    {
        header('Content-Type: application/json');

        $poll = $this->voting_model->get_poll_by_code($this->input->post('code'));
        if (!$poll) {
            echo json_encode(['success' => false, 'message' => 'Poll not found.']);

            return;
        }

        $voter_token = (string) $this->input->post('vt');
        if (!preg_match('/^[a-zA-Z0-9\-]{8,64}$/', $voter_token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid voter token.']);

            return;
        }

        $result = $this->voting_model->cast_vote(
            $poll,
            (int) $this->input->post('question_id'),
            (int) $this->input->post('option_id'),
            $voter_token,
            $this->input->ip_address(),
            (string) $this->input->post('voter_name')
        );

        // The telecast screen (full=1) gets results back; voter ballots only
        // get the result-free state so counts never reach the voter link.
        if ($this->input->post('full')) {
            $result['state'] = $this->voting_model->public_state($poll);
        } else {
            $result['state'] = $this->voting_model->ballot_state($poll);
        }
        echo json_encode($result);
    }

    /* QR code pointing to the VOTER link (/v/{code}), as a standalone SVG */
    public function qr($code = '')
    {
        $poll = $this->voting_model->get_poll_by_code($code);
        if (!$poll) {
            show_404();
        }

        require_once(APPPATH . 'vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php');

        $barcode = new TCPDF2DBarcode(site_url('v/' . $poll->code), 'QRCODE,M');
        $svg     = $barcode->getBarcodeSVGcode(6, 6, 'black');

        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=86400');
        echo $svg;
    }

    private function _not_available()
    {
        $data['title']   = 'Voting Not Available';
        $data['message'] = 'This voting link does not exist or has not been opened yet. Please check the code with the host.';
        $this->load->view('public_error', $data);
    }
}
