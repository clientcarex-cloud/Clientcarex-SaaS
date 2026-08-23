<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Public Report Download Controller
 *
 * Handles public, token-based PDF report downloads shared via WhatsApp.
 * No authentication required — access is gated by a 24-hour expiring token.
 *
 * Route: report/download/{token}  →  Transcriptor_public/download/{token}
 */
class Transcriptor_public extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Validate the token and serve the PDF report.
     *
     * @param string $token  64-char hex token issued with a 24-hour expiry
     */
    public function download($token = '')
    {
        if (empty($token)) {
            show_404();
        }

        // Sanitize: allow alphanumeric for short tokens
        $token = preg_replace('/[^a-zA-Z0-9]/', '', $token);
        if (strlen($token) < 8) {
            $this->_show_expired_page();
            return;
        }

        // Lookup token
        $this->db->where('token', $token);
        $row = $this->db->get(db_prefix() . 'transcriptor_public_tokens')->row();

        if (!$row) {
            $this->_show_expired_page();
            return;
        }

        // Check expiry
        if (strtotime($row->expires_at) < time()) {
            // Delete physical file if static
            if (isset($row->is_static) && $row->is_static == 1 && !empty($row->file_path) && file_exists($row->file_path)) {
                @unlink($row->file_path);
            }
            // Delete expired token
            $this->db->where('token', $token)->delete(db_prefix() . 'transcriptor_public_tokens');
            $this->_show_expired_page();
            return;
        }

        // Serve static PDF if available
        if (isset($row->is_static) && $row->is_static == 1 && !empty($row->file_path)) {
            if (file_exists($row->file_path)) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="lab_report.pdf"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                readfile($row->file_path);
                return;
            } else {
                // File missing, fallback to dynamic if we want, or show error
                // We'll let it fallback to dynamic generation below just in case
            }
        }

        // Render the PDF via the lab_report_renderer library
        $this->load->library('transcriptor/lab_report_renderer');

        $letterhead = ($row->type === 'letterhead') ? 'true' : 'false';
        $doctor_id  = $row->doctor_id ?? null;

        // Temporarily set GET params so the library picks them up
        $_GET['letterhead'] = $letterhead;
        if ($doctor_id) {
            $_GET['doctor_id'] = $doctor_id;
        }

        // Pre-load the model with the module prefix so the renderer doesn't fail to find it
        $this->load->model('transcriptor/transcriptor_model');

        // Use the existing download_pdf method which respects letterhead/doctor_id GET params
        $this->lab_report_renderer->download_pdf($row->transcription_id, 'transcriptor_model');
    }

    /**
     * Show a friendly "link expired" HTML page instead of a bare 404.
     */
    private function _show_expired_page()
    {
        http_response_code(410);
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Expired — ClientcareX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 48px 40px;
            text-align: center;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .icon {
            width: 72px;
            height: 72px;
            background: #fff3e0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 32px;
        }
        h1 { font-size: 22px; color: #212121; margin-bottom: 12px; font-weight: 700; }
        p  { font-size: 15px; color: #757575; line-height: 1.6; }
        .badge {
            display: inline-block;
            margin-top: 20px;
            padding: 6px 16px;
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⏳</div>
        <h1>Download Link Expired</h1>
        <p>This report link was valid for <strong>24 hours</strong> and has now expired.</p>
        <p style="margin-top: 10px;">Please contact the lab to request a new download link.</p>
        <span class="badge">ClientcareX — Secure Reports</span>
    </div>
</body>
</html>';
        exit;
    }
}
