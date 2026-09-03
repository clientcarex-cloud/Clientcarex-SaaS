# ePTW — Electronic Permit to Work

Digitises the full permit-to-work lifecycle for hazardous work, replacing the
Excel register and the 17 paper V3 forms. Built from the *ePTW Software &
Mobile Application — Complete System Guideline v2* and the client's
`EPTW RISK CONTROL REGISTER.xlsx`.

## What it does

| Area | Delivered |
|---|---|
| **17 permit templates** | Hot Work, Cold Work, Confined Space, Excavation, Lifting, Working at Height, Electrical, LOTO, Radiography, MEWP, Scissor Lift, Piling, Hydrostatic, Pressure Testing, SIMOPS, Night Work, Heat Stress — hazards, control sections, type-specific fields, personnel, PPE and approval steps seeded from the V3 sheets and editable in Setup. |
| **Permit numbering** | Configurable `{PROJECT}-{AREA}-{TYPE}-{YEAR}-{SERIAL}`, serial reset scope, row-locked so two coordinators cannot mint the same number. Issued **only** by the PTW Coordinator. |
| **Workflow** | Draft → Number requested → Under review → Issued → Active / Active-Extended → Suspended / On hold / On hold-SIMOPS → Closed / Closed-docs-pending → Archived, plus Returned and Cancelled. Every transition is audited. |
| **Approvals** | Per-type matrix (Area Authority, HSE, Manager, Coordinator) signed in-app with an e-signature pad, or recorded from paper by the coordinator (hybrid workflow). |
| **Hazard intelligence** | Rule engine reads the work description and pre-ticks hazards, highlights controls and proposes PPE. Risk level auto-calculated. |
| **SIMOPS detection** | Same project + area, overlapping window, conflicting type pair → `warn` (flag + notify) or `block` (on hold until the coordinator records the resolution). Live preview in the form. |
| **Register** | Filterable, paginated, exportable CSV with the Excel register's columns. Importer for the existing `.xlsx` / `.csv` register (own mini xlsx reader, no library needed). |
| **Site records** | Gas tests with GCC acceptance limits (auto UNSAFE alert), shift revalidation, toolbox talk attendees, extensions with approval, suspension reasons, closure checklist. |
| **Documents** | Per-permit uploads with required closure-document tracking; a permit closes fully only when they are present. Camera policy per client/project (allowed / restricted / disabled). |
| **Dashboard & reports** | Cards (today, active, pending, suspended, expiring, expired, docs pending, extensions), high-risk panel, SIMOPS panel, charts by type/contractor/area/status, 30-day trend. 12 reports with CSV export. |
| **Notifications** | In-app bell + optional email on request, issue, return, suspend, resume, extension, close, expiring, expired, SIMOPS, unsafe gas test, documents pending. |
| **Print / PDF** | The digitised V3 form as a print page and as TCPDF output. |
| **Cron** | Auto-start at window open, expiry warnings, expired-still-active alerts, documents-pending nudges. |

## Roles

Managed in **Setup → Team & roles** (CRM administrators are ePTW administrators
automatically). Engineer / HSE Officer / Area Authority / PTW Coordinator /
Manager / Administrator, each optionally scoped to projects.

## Layout

```
modules/eptw/
  eptw.php                    bootstrap: menu, assets, cron, schema self-heal
  install.php                 tables, options, seeds (idempotent)
  data/permit_types_seed.php  the 17 V3 templates + default SIMOPS rules
  helpers/eptw_helper.php     roles, statuses, options, lookups, hazard engine
  models/Eptw_model.php       setup CRUD + find-or-create for the importer
  models/Eptw_permits_model.php  lifecycle, numbering, SIMOPS, docs, notify, cron
  models/Eptw_reports_model.php  dashboard, charts, reports, export
  controllers/Eptw.php        admin/eptw/*
  controllers/Eptw_setup.php  admin/eptw/eptw_setup/*
  libraries/Eptw_pdf.php      TCPDF wrapper
  libraries/Eptw_import.php   xlsx/csv reader + header mapping
  views/                      dashboard, register, permit_form, permit_view, reports, print/, setup/
  assets/                     eptw.css, eptw.js (no framework)
```

Uploads: `uploads/eptw/permits/<id>/`. Tables: `tbleptw_*`.

## Not in this release

Native mobile app / offline sync (the web UI is responsive and works on
low-end Android browsers), and LLM-based hazard suggestions (the rule engine
covers the guideline's examples).
