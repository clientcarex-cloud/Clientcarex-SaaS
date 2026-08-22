<?php

defined('BASEPATH') or exit('No direct script access allowed');

include_once(APPPATH . 'libraries/pdf/App_pdf.php');

/**
 * PDF document for the Pricing Plans report.
 *
 * Reuses the application's TCPDF infrastructure (fonts, options, output) and
 * renders the report HTML produced by the pricing_plans/pdf_report view.
 */
class Pricing_plans_pdf extends App_pdf
{
    protected $rows;
    protected $report_title;

    /**
     * @param array $data ['rows' => array, 'title' => string]
     */
    public function __construct($data)
    {
        parent::__construct();

        $this->rows         = $data['rows'] ?? [];
        $this->report_title = $data['title'] ?? _l('perfex_saas_pricing_plans');

        $this->SetTitle($this->report_title);
    }

    /**
     * Force a landscape A4 layout so the wide plan table fits, regardless of the
     * (possibly unset) custom pdf_format option for this report type.
     *
     * @return array
     */
    public function get_format_array()
    {
        return ['orientation' => 'L', 'format' => 'A4'];
    }

    /**
     * @inheritDoc
     */
    public function prepare()
    {
        // Branding: company name + raster logo (TCPDF cannot render SVG)
        $company_name = get_option('companyname');
        $logo_file    = get_option('company_logo') ?: get_option('company_logo_dark');
        $logo_path    = '';
        if ($logo_file) {
            $ext      = strtolower(pathinfo($logo_file, PATHINFO_EXTENSION));
            $absolute = FCPATH . 'uploads/company/' . $logo_file;
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif']) && is_file($absolute)) {
                $logo_path = $absolute;
            }
        }

        $html = $this->ci->load->view('perfex_saas/pricing_plans/pdf_report', [
            'rows'         => $this->rows,
            'report_title' => $this->report_title,
            'currency'     => get_base_currency(),
            'company_name' => $company_name,
            'logo_path'    => $logo_path,
        ], true);

        $this->writeHTML($html, true, false, true, false, '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function type()
    {
        return 'pricing_plans_report';
    }

    /**
     * Not used — the HTML is rendered directly in prepare().
     *
     * @return string
     */
    protected function file_path()
    {
        return '';
    }
}
