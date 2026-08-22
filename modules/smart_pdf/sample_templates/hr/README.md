# HR Document Templates for Smart PDF

Premium, print-ready HTML templates for the HR module, designed to be imported into
**Smart PDF** and generated per-employee via the **Print Document** button on an
employee's profile (or from the Smart PDF card grid).

They share one design system — deep-teal + gold letterhead, Playfair Display / Source
Sans 3 / Cormorant Garamond — so every document looks like it belongs to the same
organisation.

## Fastest way — one-click install

**HR → Settings → Document Templates → Install HR Templates** (or the **Install HR
Templates** button on the Smart PDF page). This seeds every template in this folder into
your Smart PDF library in one go. It is idempotent — running it again only adds templates
that aren't already present.

## Or import a single template manually

1. Go to **Smart PDF → New Smart PDF Template**.
2. Click **Import HTML File** and choose one of the `.html` files in this folder.
3. The `{{tags}}` are auto-detected. Give the template a **Name**, tick **Active**, **Save**.
4. (Optional) In the tag table, set input types (e.g. `date` for `*_date` fields) and
   default values, and mark any tag you don't need as *ignored*.

## How to generate

- From **HR → Employees → open an employee → Print Document → pick a template**.
  The employee is pre-selected and every `{{employee_*}}` field is auto-filled.
- Fill any remaining manual fields, then **Print / Save as PDF** (pixel-perfect).

## Tags

**Auto-filled from the selected employee:** `employee_name`, `employee_code`,
`employee_designation`, `employee_department`, `employee_email`, `employee_mobile`,
`employee_doj`, `employee_dob`, `employee_gender`, `employee_blood_group`,
`employee_father_name`, `employee_qualifications`, `employee_employment_type`,
`employee_work_location`, `employee_address`, `employee_permanent_address`,
`employee_national_id`, `employee_pan`, `employee_bank_name`, `employee_bank_account`,
`employee_bank_ifsc`, `employee_pf_uan`, `employee_esi`, `employee_emergency_contact`,
`employee_reporting_to`, `employee_basic_salary` *(HR-payroll access only)*,
`employee_photo_url` *(use inside `<img src>` — e.g. the ID card)*.

**Filled automatically by the system:** `clinic_name`, `clinic_address`, `clinic_phone`,
`staff_name` (the issuer), `current_date`, `document_no`, **`clinic_logo_url`** (your
uploaded CRM company logo — used in every letterhead / ID card / certificate header) and
**`clinic_favicon_url`** (your uploaded favicon — used as the faint background watermark).
Both come from **Business Profile → Company Logo / Favicon**; if none is uploaded they fall
back to a transparent pixel so nothing shows a broken image.

**Everything else** (e.g. `annual_ctc`, `effective_date`, `warning_subject`) is a manual
field you type in the generate popup.

## Catalog

### ID Cards (CR80 54 × 85.6 mm)
| File | Document |
|---|---|
| `hr-id-card-front.html` | Employee ID Card — Front (photo, name, designation, code, blood group) |
| `hr-id-card-back.html` | Employee ID Card — Back (emergency contact, terms, return-to) |

### Certificates (A4, framed)
| File | Document |
|---|---|
| `hr-experience-certificate.html` | Experience / Service Certificate |
| `hr-training-certificate.html` | Training Completion Certificate |
| `hr-appreciation-certificate.html` | Appreciation / Award Certificate |
| `hr-internship-certificate.html` | Internship Certificate |

### Letters (A4, letterhead)
| File | Document |
|---|---|
| `hr-job-invitation-letter.html` | Job Invitation / Interview Call Letter |
| `hr-offer-letter.html` | Offer Letter |
| `hr-appointment-letter.html` | Appointment Letter |
| `hr-confirmation-letter.html` | Confirmation Letter (post-probation) |
| `hr-promotion-letter.html` | Promotion Letter |
| `hr-increment-letter.html` | Salary Increment / Revision Letter |
| `hr-salary-certificate.html` | Salary Certificate |
| `hr-employment-verification-letter.html` | Employment Verification Letter |
| `hr-warning-letter.html` | Warning Letter |
| `hr-noc.html` | No Objection Certificate (NOC) |
| `hr-relieving-letter.html` | Relieving Letter |
| `hr-final-statement-letter.html` | Full & Final Settlement Statement |

### Agreements (A4, may span 2 pages)
| File | Document |
|---|---|
| `hr-employment-agreement.html` | Employment Agreement / Contract |
| `hr-nda.html` | Non-Disclosure Agreement |
| `hr-service-bond.html` | Service Bond Agreement |
| `hr-code-of-conduct.html` | Code of Conduct Acknowledgement |
| `hr-exit-agreement.html` | Separation & Exit Agreement |

### Statutory (A4)
| File | Document |
|---|---|
| `hr-form-16.html` | Form No. 16 — TDS Certificate (Section 203) |

### Policies (A4, may span 2 pages)
| File | Document |
|---|---|
| `hr-leave-policy.html` | Leave Policy |
| `hr-employee-handbook.html` | Employee Handbook |
| `hr-attendance-shift-policy.html` | Attendance & Shift Policy |
| `hr-posh-policy.html` | Prevention of Sexual Harassment (POSH) Policy |
| `hr-biomedical-waste-policy.html` | Biomedical Waste & Safety Policy |

## Design references

- `hr-offer-letter.html` is the canonical **letter** chrome.
- `hr-experience-certificate.html` is the canonical **certificate** frame.
- ID cards are self-contained CR80 layouts.

To rebrand: the palette lives in the `:root { --ink / --teal / --gold ... }` block at the
top of each file; adjust once and re-import.
