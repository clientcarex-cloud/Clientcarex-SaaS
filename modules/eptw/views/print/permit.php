<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Printable permit — the digitised V3 form. Rendered both in the browser
 * (print view) and inside TCPDF (pdf), so the markup is table-based with
 * inline styles only.
 */
$p   = $permit;
$e   = function ($v) { return html_escape((string) $v); };
$th  = 'style="background-color:#e2e8f0;font-weight:bold;font-size:8pt;padding:3px"';
$td  = 'style="font-size:8.5pt;padding:3px"';
$sec = 'style="background-color:#0f172a;color:#ffffff;font-weight:bold;font-size:9pt;padding:4px"';
$yn  = function ($v) { return $v === 'yes' ? 'YES' : ($v === 'no' ? 'NO' : ($v === 'na' ? 'N/A' : '')); };
$general = []; $personnel = [];
foreach (($type ? $type->extra_fields : []) as $f) { if (($f['group'] ?? '') === 'personnel') { $personnel[] = $f; } else { $general[] = $f; } }
$fmt = function ($f, $val) use ($e) {
    if ($f['type'] === 'checkboxes') { return $e(implode(', ', (array) $val)); }
    if ($f['type'] === 'detect') { $o = []; foreach ((array) $val as $k => $v) { $o[] = $k . ': ' . ($v === 'detected' ? 'DETECTED' : 'not detected'); } return $e(implode(' · ', $o)); }
    if ($f['type'] === 'yesno') { return $val === 'yes' ? 'YES' : ($val === 'no' ? 'NO' : ''); }
    return nl2br($e($val));
};
?>
<?php if (!$for_pdf) { ?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title><?= $e($p->permit_no ?: 'Draft permit'); ?></title>
<style>body{font-family:Helvetica,Arial,sans-serif;color:#0f172a;margin:0;background:#f1f5f9}.page{max-width:920px;margin:20px auto;background:#fff;padding:26px 30px;box-shadow:0 4px 20px rgba(0,0,0,.08)}table{border-collapse:collapse;width:100%}td,th{border:1px solid #cbd5e1}.bar{display:flex;gap:8px;justify-content:flex-end;margin-bottom:12px}.bar a,.bar button{padding:8px 14px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;cursor:pointer;font-weight:600;text-decoration:none;color:#0f172a}@media print{body{background:#fff}.page{box-shadow:none;margin:0;padding:0}.bar{display:none}}</style>
</head><body><div class="page"><div class="bar"><a href="<?= admin_url('eptw/pdf/' . $p->id); ?>">Download PDF</a><button onclick="window.print()">Print</button></div>
<?php } ?>

<table border="0" cellpadding="3" width="100%">
    <tr>
        <td width="60%" style="font-size:13pt;font-weight:bold;border:0"><?= $e($company); ?><br><span style="font-size:11pt"><?= $e(strtoupper($type ? $type->name : 'PERMIT')); ?> – V3 (GCC Standard)</span></td>
        <td width="40%" align="right" style="border:0;font-size:9pt">Permit No.<br><span style="font-size:14pt;font-weight:bold;font-family:courier"><?= $e($p->permit_no ?: 'DRAFT – NOT VALID'); ?></span><br>Status: <b><?= $e(eptw_status_label($p->status)); ?></b><?= $p->issued_at ? '<br>Issued ' . eptw_dt($p->issued_at) : ''; ?></td>
    </tr>
</table>

<table border="1" cellpadding="3" width="100%">
    <tr><td colspan="6" <?= $sec; ?>>SECTION 1: PERMIT HEADER</td></tr>
    <tr><td <?= $th; ?> width="16%">Work Order</td><td <?= $td; ?> width="17%"><?= $e($p->work_order); ?></td><td <?= $th; ?> width="16%">Project</td><td <?= $td; ?> width="17%"><?= $e($p->project_name . ' (' . $p->project_code . ')'); ?></td><td <?= $th; ?> width="16%">Company</td><td <?= $td; ?> width="18%"><?= $e($company); ?></td></tr>
    <tr><td <?= $th; ?>>Contractor</td><td <?= $td; ?>><?= $e($p->contractor_name); ?></td><td <?= $th; ?>>Subcontractor</td><td <?= $td; ?>><?= $e($p->subcontractor); ?></td><td <?= $th; ?>>Equipment Tag</td><td <?= $td; ?>><?= $e($p->equipment_tag); ?></td></tr>
    <tr><td <?= $th; ?>>Area / Zone</td><td <?= $td; ?>><?= $e($p->area_name . ($p->area_code ? ' (' . $p->area_code . ')' : '')); ?></td><td <?= $th; ?>>Location</td><td <?= $td; ?> colspan="3"><?= $e($p->location); ?></td></tr>
    <tr><td <?= $th; ?>>RA / JSA Ref</td><td <?= $td; ?>><?= $e($p->ra_ref); ?></td><td <?= $th; ?>>Risk Level</td><td <?= $td; ?>><b><?= $e(strtoupper($p->risk_level)); ?></b><?= $p->high_risk ? ' (HIGH-RISK TYPE)' : ''; ?></td><td <?= $th; ?>>Weather</td><td <?= $td; ?>><?= $e($p->weather); ?></td></tr>
</table>

<table border="1" cellpadding="3" width="100%">
    <tr><td colspan="2" <?= $sec; ?>>SECTION 2: WORK DESCRIPTION</td></tr>
    <tr><td <?= $th; ?> width="16%">Work Title</td><td <?= $td; ?>><?= $e($p->work_title); ?></td></tr>
    <tr><td <?= $th; ?>>Details</td><td <?= $td; ?> style="font-size:8.5pt;padding:3px;height:40px"><?= nl2br($e($p->work_description)); ?></td></tr>
</table>

<table border="1" cellpadding="3" width="100%">
    <tr><td colspan="6" <?= $sec; ?>>SECTION 3: JOB DETAILS</td></tr>
    <tr><td <?= $th; ?> width="16%">Start Date/Time</td><td <?= $td; ?> width="17%"><?= eptw_dt($p->start_at); ?></td><td <?= $th; ?> width="16%">End Date/Time</td><td <?= $td; ?> width="17%"><?= eptw_dt($p->end_at); ?><?= $p->extension_count ? ' (ext. ×' . (int) $p->extension_count . ')' : ''; ?></td><td <?= $th; ?> width="16%">Shift</td><td <?= $td; ?> width="18%"><?= $e(eptw_shifts()[$p->shift] ?? $p->shift); ?></td></tr>
    <tr><td <?= $th; ?>>No. of Workers</td><td <?= $td; ?>><?= (int) $p->workers_count ?: ''; ?></td><td <?= $th; ?>>Supervisor</td><td <?= $td; ?>><?= $e($p->supervisor); ?></td><td <?= $th; ?>>Permit Holder</td><td <?= $td; ?>><?= $e($p->permit_holder); ?></td></tr>
    <tr><td <?= $th; ?>>Initiator</td><td <?= $td; ?>><?= $e($p->engineer_name); ?></td><td <?= $th; ?>>Area Authority</td><td <?= $td; ?>><?= $e($p->area_authority_name); ?></td><td <?= $th; ?>>Permit Issuer</td><td <?= $td; ?>><?= $e($p->coordinator_name); ?></td></tr>
    <tr><td <?= $th; ?>>HSE Officer</td><td <?= $td; ?>><?= $e($p->hse_officer_name); ?></td><td <?= $th; ?>>Contact No</td><td <?= $td; ?>><?= $e($p->contact_no); ?></td><td <?= $th; ?>>Source</td><td <?= $td; ?>><?= ucfirst($p->source); ?></td></tr>
    <?php foreach (array_chunk($personnel, 3) as $chunk) { ?>
        <tr><?php foreach ($chunk as $f) { ?><td <?= $th; ?>><?= $e($f['label']); ?></td><td <?= $td; ?>><?= $e($p->extra[$f['key']] ?? ''); ?></td><?php } for ($i = count($chunk); $i < 3; $i++) { ?><td <?= $th; ?>></td><td <?= $td; ?>></td><?php } ?></tr>
    <?php } ?>
</table>

<?php if (count($general)) { ?>
<table border="1" cellpadding="3" width="100%">
    <tr><td colspan="4" <?= $sec; ?>>SECTION 4: <?= $e(strtoupper($type->code)); ?>-SPECIFIC DETAILS</td></tr>
    <?php foreach (array_chunk($general, 2) as $chunk) { ?>
        <tr><?php foreach ($chunk as $f) { ?><td <?= $th; ?> width="16%"><?= $e($f['label']); ?></td><td <?= $td; ?> width="34%"><?= $fmt($f, $p->extra[$f['key']] ?? ''); ?></td><?php } if (count($chunk) < 2) { ?><td <?= $th; ?>></td><td <?= $td; ?>></td><?php } ?></tr>
    <?php } ?>
</table>
<?php } ?>

<table border="1" cellpadding="3" width="100%">
    <tr><td colspan="6" <?= $sec; ?>>SECTION 5: HAZARD IDENTIFICATION</td></tr>
    <?php $hz = []; foreach ($p->hazards as $name => $v) { $hz[] = [$name, $v]; } foreach ($p->extra_hazards as $name) { $hz[] = [$name, 'yes']; } ?>
    <?php foreach (array_chunk($hz, 3) as $chunk) { ?>
        <tr><?php foreach ($chunk as $h) { ?><td <?= $td; ?> width="25%"><?= $e($h[0]); ?></td><td <?= $td; ?> width="8%" align="center"><b><?= $yn($h[1]); ?></b></td><?php } for ($i = count($chunk); $i < 3; $i++) { ?><td <?= $td; ?>></td><td <?= $td; ?>></td><?php } ?></tr>
    <?php } ?>
    <?php if (!count($hz)) { ?><tr><td colspan="6" <?= $td; ?>>No hazards recorded.</td></tr><?php } ?>
</table>

<?php $n = 6; foreach (($type ? $type->controls : []) as $section) { ?>
<table border="1" cellpadding="3" width="100%">
    <tr><td colspan="3" <?= $sec; ?>>SECTION <?= $n++; ?>: <?= $e(strtoupper($section['title'])); ?></td></tr>
    <tr><td <?= $th; ?> width="50%">Control</td><td <?= $th; ?> width="10%" align="center">Yes/No</td><td <?= $th; ?> width="40%">Remarks</td></tr>
    <?php foreach ($section['items'] as $item) { $c = $p->controls[$item] ?? ['v' => '', 'r' => '']; ?>
        <tr><td <?= $td; ?>><?= $e($item); ?></td><td <?= $td; ?> align="center"><b><?= $yn($c['v']); ?></b></td><td <?= $td; ?>><?= $e($c['r']); ?></td></tr>
    <?php } ?>
</table>
<?php } ?>

<table border="1" cellpadding="3" width="100%">
    <tr><td colspan="8" <?= $sec; ?>>SECTION <?= $n++; ?>: ISOLATION / LOTO</td></tr>
    <tr><td <?= $th; ?>>Isolation Required</td><td <?= $td; ?>><?= $p->isolation_required ? 'YES' : 'NO'; ?></td><td <?= $th; ?>>Isolation Type</td><td <?= $td; ?>><?= $e($p->isolation_type); ?></td><td <?= $th; ?>>Certificate No</td><td <?= $td; ?>><?= $e($p->isolation_cert_no); ?></td><td <?= $th; ?>>LOTO Applied</td><td <?= $td; ?>><?= $p->loto_applied ? 'YES' : 'NO'; ?></td></tr>
    <tr><td <?= $th; ?>>Zero Energy Verified</td><td <?= $td; ?>><?= $p->zero_energy_verified ? 'YES' : 'NO'; ?></td><td <?= $th; ?>>Isolation Authority</td><td <?= $td; ?>><?= $e($p->isolation_authority); ?></td><td <?= $th; ?>>Lock/Tag Numbers</td><td <?= $td; ?> colspan="3"><?= $e($p->lock_tag_numbers); ?></td></tr>
</table>

<table border="1" cellpadding="3" width="100%">
    <tr><td colspan="9" <?= $sec; ?>>SECTION <?= $n++; ?>: ATMOSPHERIC TESTING <?= $p->gas_test_required ? '(REQUIRED)' : ''; ?></td></tr>
    <tr><td <?= $th; ?>>Time</td><td <?= $th; ?>>O2 %</td><td <?= $th; ?>>LEL %</td><td <?= $th; ?>>H2S ppm</td><td <?= $th; ?>>CO ppm</td><td <?= $th; ?>>SO2</td><td <?= $th; ?>>NH3</td><td <?= $th; ?>>Tester</td><td <?= $th; ?>>Result / Remarks</td></tr>
    <?php foreach ($gas_tests as $g) { ?>
        <tr><td <?= $td; ?>><?= eptw_dt($g->tested_at); ?></td><td <?= $td; ?>><?= $g->o2; ?></td><td <?= $td; ?>><?= $g->lel; ?></td><td <?= $td; ?>><?= $g->h2s; ?></td><td <?= $td; ?>><?= $g->co; ?></td><td <?= $td; ?>><?= $g->so2; ?></td><td <?= $td; ?>><?= $g->nh3; ?></td><td <?= $td; ?>><?= $e($g->tester); ?></td><td <?= $td; ?>><b><?= strtoupper($g->result); ?></b> <?= $e($g->remarks); ?></td></tr>
    <?php } ?>
    <?php for ($i = count($gas_tests); $i < 3; $i++) { ?><tr><td <?= $td; ?>>&nbsp;</td><td <?= $td; ?>></td><td <?= $td; ?>></td><td <?= $td; ?>></td><td <?= $td; ?>></td><td <?= $td; ?>></td><td <?= $td; ?>></td><td <?= $td; ?>></td><td <?= $td; ?>></td></tr><?php } ?>
</table>

<table border="1" cellpadding="3" width="100%">
    <tr><td colspan="4" <?= $sec; ?>>SECTION <?= $n++; ?>: TOOLBOX TALK</td></tr>
    <tr><td <?= $th; ?> colspan="4">Held <?= !empty($p->toolbox['held_at']) ? eptw_dt($p->toolbox['held_at']) . ' by ' . $e($p->toolbox['by'] ?? '') : '__________________'; ?> · Explained: <?php foreach (['hazards' => 'Hazards', 'controls' => 'Controls', 'ppe' => 'PPE', 'emergency' => 'Emergency plan'] as $k => $l) { echo $l . ' [' . (in_array($k, $p->toolbox['topics'] ?? [], true) ? 'X' : ' ') . ']  '; } ?></td></tr>
    <tr><td <?= $th; ?>>#</td><td <?= $th; ?>>Worker Name</td><td <?= $th; ?>>ID</td><td <?= $th; ?>>Signature</td></tr>
    <?php $att = $p->toolbox['attendees'] ?? []; foreach ($att as $i => $w) { ?><tr><td <?= $td; ?>><?= $i + 1; ?></td><td <?= $td; ?>><?= $e($w['name']); ?></td><td <?= $td; ?>><?= $e($w['id']); ?></td><td <?= $td; ?>></td></tr><?php } ?>
    <?php for ($i = count($att); $i < 4; $i++) { ?><tr><td <?= $td; ?>><?= $i + 1; ?></td><td <?= $td; ?>>&nbsp;</td><td <?= $td; ?>></td><td <?= $td; ?>></td></tr><?php } ?>
</table>

<table border="1" cellpadding="3" width="100%">
    <tr><td colspan="4" <?= $sec; ?>>SECTION <?= $n++; ?>: APPROVAL MATRIX</td></tr>
    <tr><td <?= $th; ?> width="28%">Role</td><td <?= $th; ?> width="27%">Name</td><td <?= $th; ?> width="25%">Signature</td><td <?= $th; ?> width="20%">Date/Time</td></tr>
    <?php $steps = count($approvals) ? $approvals : array_map(function ($s) { return (object) ['step' => $s, 'name' => '', 'staff_name' => '', 'signature' => '', 'decided_at' => '', 'decision' => 'pending']; }, array_merge(['initiator'], $type ? $type->approvals : [])); ?>
    <?php foreach ($steps as $a) { ?>
        <tr>
            <td <?= $td; ?>><?= $e($this->permits->step_label($a->step)); ?></td>
            <td <?= $td; ?>><?= $e($a->name ?: $a->staff_name); ?></td>
            <td <?= $td; ?> style="height:34px;font-size:8.5pt;padding:3px"><?php if (!empty($a->signature)) { ?><img src="<?= $a->signature; ?>" height="28"><?php } elseif ($a->decision === 'approved') { ?>(signed in system)<?php } ?></td>
            <td <?= $td; ?>><?= $a->decided_at ? eptw_dt($a->decided_at) : ''; ?><?= $a->decision === 'rejected' ? ' REJECTED' : ''; ?></td>
        </tr>
    <?php } ?>
</table>

<table border="1" cellpadding="3" width="100%">
    <tr><td colspan="6" <?= $sec; ?>>SECTION <?= $n++; ?>: REVALIDATION (SHIFT WISE)</td></tr>
    <tr><td <?= $th; ?>>Shift</td><td <?= $th; ?>>Area Authority</td><td <?= $th; ?>>Permit Issuer</td><td <?= $th; ?>>HSE</td><td <?= $th; ?>>Time From</td><td <?= $th; ?>>Time To</td></tr>
    <?php foreach ($revalidations as $r) { ?><tr><td <?= $td; ?>><?= $e(eptw_shifts()[$r->shift] ?? $r->shift); ?></td><td <?= $td; ?>><?= $e($r->area_authority); ?></td><td <?= $td; ?>><?= $e($r->issuer); ?></td><td <?= $td; ?>><?= $e($r->hse); ?></td><td <?= $td; ?>><?= eptw_dt($r->from_at); ?></td><td <?= $td; ?>><?= eptw_dt($r->to_at); ?></td></tr><?php } ?>
    <?php for ($i = count($revalidations); $i < 2; $i++) { ?><tr><td <?= $td; ?>>&nbsp;</td><td <?= $td; ?>></td><td <?= $td; ?>></td><td <?= $td; ?>></td><td <?= $td; ?>></td><td <?= $td; ?>></td></tr><?php } ?>
</table>

<table border="1" cellpadding="3" width="100%">
    <tr><td colspan="4" <?= $sec; ?>>SECTION <?= $n++; ?>: EXTENSION / SUSPENSION</td></tr>
    <?php foreach ($extensions as $x) { ?><tr><td <?= $th; ?>>Extension</td><td <?= $td; ?>>Until <?= eptw_dt($x->new_end_at); ?> — <?= strtoupper($x->status); ?></td><td <?= $th; ?>>Reason</td><td <?= $td; ?>><?= $e($x->reason); ?></td></tr><?php } ?>
    <tr><td <?= $th; ?> width="16%">Suspended (Y/N)</td><td <?= $td; ?> width="34%"><?= $p->suspended_at ? 'YES — ' . eptw_dt($p->suspended_at) : 'NO'; ?></td><td <?= $th; ?> width="16%">Reason</td><td <?= $td; ?> width="34%"><?= $e($p->suspend_reason); ?></td></tr>
    <?php if ($p->simops_flag || $p->simops_notes) { ?><tr><td <?= $th; ?>>SIMOPS</td><td <?= $td; ?> colspan="3"><?= nl2br($e($p->simops_notes)); ?></td></tr><?php } ?>
</table>

<table border="1" cellpadding="3" width="100%">
    <tr><td colspan="8" <?= $sec; ?>>SECTION <?= $n++; ?>: CLOSURE</td></tr>
    <tr><td <?= $th; ?>>Work Completed</td><td <?= $td; ?>><?= !empty($p->closure['work_completed']) ? 'YES' : ''; ?></td><td <?= $th; ?>>Area Clean</td><td <?= $td; ?>><?= !empty($p->closure['area_clean']) ? 'YES' : ''; ?></td><td <?= $th; ?>>No Residual Hazards</td><td <?= $td; ?>><?= !empty($p->closure['no_residual_hazards']) ? 'YES' : ''; ?></td><td <?= $th; ?>>Isolation Removed</td><td <?= $td; ?>><?= !empty($p->closure['isolation_removed']) ? 'YES' : ''; ?></td></tr>
    <tr><td <?= $th; ?>>Closed By</td><td <?= $td; ?>><?= $e(eptw_staff_name($p->closed_by) ?: ($p->closure['closed_by_name'] ?? '')); ?></td><td <?= $th; ?>>Closure Date</td><td <?= $td; ?>><?= $p->closed_at ? eptw_dt($p->closed_at) : ''; ?></td><td <?= $th; ?>>Final Remarks</td><td <?= $td; ?> colspan="3"><?= $e($p->closure['final_remarks'] ?? ''); ?></td></tr>
</table>

<?php if (count($documents)) { ?>
<table border="1" cellpadding="3" width="100%">
    <tr><td colspan="3" <?= $sec; ?>>ATTACHED DOCUMENTS</td></tr>
    <?php foreach ($documents as $d) { ?><tr><td <?= $td; ?> width="30%"><?= $e(eptw_document_types()[$d->doc_type] ?? $d->doc_type); ?></td><td <?= $td; ?>><?= $e($d->original_name); ?></td><td <?= $td; ?> width="25%"><?= eptw_dt($d->created_at); ?></td></tr><?php } ?>
</table>
<?php } ?>

<p style="font-size:7.5pt;color:#64748b">This permit is valid on site only when a permit number has been issued by the PTW Coordinator, and only for the work, location and time window stated. Printed <?= date('d M Y H:i'); ?> from ePTW.</p>

<?php if (!$for_pdf) { ?></div></body></html><?php } ?>
