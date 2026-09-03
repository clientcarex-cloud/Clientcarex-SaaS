<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ePTW — the permit as a PDF. Plain TCPDF with the permit-print HTML written
 * into it, plus a running header (permit number, status) and footer (page
 * numbers, generation time) so a printed copy is self-identifying.
 */
class Eptw_pdf extends TCPDF
{
    protected $permit;
    protected $company;

    public function __construct($permit, $company)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->permit  = $permit;
        $this->company = (string) $company;

        $this->SetCreator('ePTW');
        $this->SetAuthor($this->company ?: 'ePTW');
        $this->SetTitle(($permit->permit_no ?: 'Draft permit') . ' — ' . $permit->type_name);
        $this->SetMargins(12, 22, 12);
        $this->SetHeaderMargin(8);
        $this->SetFooterMargin(10);
        $this->SetAutoPageBreak(true, 16);
        $this->setCellPaddings(1, 1, 1, 1);
    }

    public function Header()
    {
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(15, 23, 42);
        $this->SetY(8);
        $this->Cell(0, 6, ($this->company !== '' ? $this->company . '  ·  ' : '') . strtoupper($this->permit->type_name), 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 11);
        $this->Cell(0, 6, $this->permit->permit_no ?: 'DRAFT — NO PERMIT NUMBER', 0, 1, 'R');
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 4, 'Permit to Work · V3 (GCC Standard) · ' . eptw_status_label($this->permit->status), 0, 1, 'R');
        $this->SetDrawColor(226, 232, 240);
        $this->Line(12, 19, 198, 19);
    }

    public function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('helvetica', '', 7.5);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 5, 'Generated ' . date('d M Y H:i') . ' · A permit is valid on site only with a permit number issued by the PTW Coordinator.', 0, 0, 'L');
        $this->Cell(0, 5, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }

    public function render($html)
    {
        $this->AddPage();
        $this->SetFont('helvetica', '', 8.5);
        $this->SetTextColor(15, 23, 42);
        $this->writeHTML($html, true, false, true, false, '');
    }
}
