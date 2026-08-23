# SHRA Leads Management — End-to-End Plan

> **Status (23 Aug 2026): implemented in SHRA v1.3.0** — all five phases below are live. Admin URLs are `admin/shra/shra_leads/…` (My Day), `…/pipeline`, `…/visits`, `…/view/{id}`, `…/team`, `…/settings`, `…/import`; public form at `/inquire`. Schema self-heals on the first admin page load after deploy (`shra_schema_version` → 1.3.0); no manual migration needed.

Target: `https://shra.clientcarex.com/admin/shra` · Module `modules/shra` (v1.2.0 → v1.3.0)
Goal: 10+ calling agents working leads from many sources, with leakage-proof tracking of
**who brought which lead → who followed up → who visited → who paid → how much revenue per agent**.

---

## 0. Design principles (what "leakage proof" means here)

| Leak today | Fix in this design |
|---|---|
| Agent forgets a follow-up | Every open lead **must** have exactly one `next_action_at`. No lead can be saved in an open stage without a next date. Overdue = red on the agent's "Today" queue + manager dashboard. |
| Two agents claim the same lead | Phone number is the **unique identity**. Duplicate phone on add → lead is attached to the original owner with a "duplicate attempt by X" log, never a second row. |
| Lead silently dropped / deleted | Agents cannot delete. Only `Lost` / `Junk` with a mandatory reason. Every stage move is written to `tbllead_activity_log` + our own `tblshra_lead_events` (immutable). |
| Revenue attributed to wrong agent | Attribution is **frozen at the moment of first bill** into `tblshra_lead_attribution` (lead_id, agent_id, enrollment_id, invoice_id, amount). Reports read from that table, never from "current assignee". |
| Visit happened but nobody recorded it | Front desk/trainer marks `Visited` from a one-tap **Visits board** (today's expected visitors, phone search). Rider quick-add is pre-filled from the lead. |
| Walk-in paid but was actually an agent's lead | Billing screen does a phone match against open leads and shows "This rider matches lead #123 (Agent Asha) — link?" Default = link. |
| Agent reassigns their own lead away / steals | Reassign needs `shra.leads_manage`. Auto round-robin assignment at intake when enabled. |
| Stale leads forever "Follow-up" | Auto-escalation cron: N days without activity → flagged `stale`, manager alert, optional auto-reassign. |

---

## 1. Funnel (stages) — tailored to a riding academy

Stored in core `tblleads_status` (so native Perfex Kanban/reports still work), seeded by install with a fixed `statusorder` and a `shra_stage_key` mapping in options.

| Order | Status name | Key | Meaning / required data |
|---|---|---|---|
| 10 | **New** | `new` | Just captured. Must be called within SLA (default 2h). |
| 20 | **Contacted** | `contacted` | First call done. Outcome logged (interested / callback / no answer ×N). |
| 30 | **Follow-up** | `followup` | Interested, needs nurturing. `next_action_at` mandatory. |
| 40 | **Visit Scheduled** | `visit_scheduled` | `visit_date` + `visit_slot` mandatory. Visits default to **Saturday / Sunday** slots (configurable slot list). Creates `tblreminders` for the agent. |
| 50 | **Visited** | `visited` | Physically came. Marked by front desk on Visits board. Logs `visited_at`, `visited_by`. |
| 60 | **Visited & Confirmed** | `confirmed` | Said yes on site, package chosen, expected amount captured (`expected_value`, `expected_package_id`). Pre-bill. |
| 1000 | **Customer** (core default) | `won` | Set automatically when first bill is created for the linked rider. |
| — | **Lost** (`tblleads.lost=1`) | `lost` | Mandatory `lost_reason` (price / distance / timing / age / competitor / no response / other). |
| — | **Junk** (`tblleads.junk=1`) | `junk` | Wrong number / spam. |

Sub-outcomes for the call log (not stages): `no_answer`, `busy`, `switched_off`, `callback_requested`, `interested`, `not_interested`, `wrong_number`, `whatsapp_sent`.

Auto-transitions:
- `Visit Scheduled` + visit date passed + no `Visited` → shows in **No-show** bucket; agent must reschedule or mark lost (next_action forced to today).
- `Visited` / `Confirmed` + bill created → `Customer` (won). Attribution frozen.
- Any open stage + no activity for `shra_lead_stale_days` (default 7) → `is_stale=1` badge + manager digest.

---

## 2. Data model

### 2.1 Reuse Perfex core tables (no changes)

| Table | How we use it |
|---|---|
| `tblleads` | The lead itself. `name, phonenumber (unique for us), email, city, source, status, assigned, addedfrom, lastcontact, dateassigned, last_status_change, lost, junk, lead_value (=expected_value), client_id (set on convert), description`. |
| `tblleads_status` | Our funnel stages (seeded). |
| `tblleads_sources` | Seeded: Walk-in, Phone Inquiry, Instagram, Facebook, Google, WhatsApp, Referral, School Tie-up, Event/Camp, Justdial, Website QR, Other. Admin can add more. |
| `tbllead_activity_log` | Native timeline — we write every call/visit/stage/assign there too, so Perfex's own lead view stays complete. |
| `tblreminders` (`rel_type='lead'`) | Visit-day & follow-up reminders (email/notification to agent). |
| `tblnotes` (`rel_type='lead'`) | Free-text notes (native tab). |
| `tblcustomfieldsvalues` | Not used for core fields — we use our extension table (faster for filtering/reporting). |
| `tblstaff` | Agents. A **"Calling Agent"** role is created via `tblroles` with permissions below. |
| `tblclients / tblcontacts` | Created at convert via existing `Shra_model::ensure_client()`. |
| `tblinvoices / tblinvoicepaymentrecords` | Revenue source of truth (already written by `create_bill()` / `collect_payment()`). |
| `tblnotifications` | Native bell notifications for assignment / visit reminders. |

### 2.2 New tables (created in `install.php`, idempotent, `shra_schema_version = 1.3.0`)

**`tblshra_lead_ext`** — 1:1 extension of `tblleads` (PK = lead_id)
```
lead_id            INT PK  (→ tblleads.id)
phone_norm         VARCHAR(20) UNIQUE   -- E.164 digits only, dedupe key
stage_key          VARCHAR(30)          -- denormalised from tblleads.status
rider_for          ENUM('self','child','both')
rider_age          TINYINT NULL
audience           ENUM('children','adults') NULL
interest_package_id INT NULL (→ tblshra_packages)
expected_value     DECIMAL(15,2) DEFAULT 0
next_action_at     DATETIME NULL        -- REQUIRED while open
next_action_type   ENUM('call','whatsapp','visit','other')
visit_date         DATE NULL
visit_slot         VARCHAR(40) NULL     -- e.g. 'Sat 07:00-08:00'
visit_reminder_id  INT NULL (→ tblreminders.id)
visited_at         DATETIME NULL
visited_by         INT NULL (→ tblstaff)
confirmed_at       DATETIME NULL
no_show_count      TINYINT DEFAULT 0
call_attempts      SMALLINT DEFAULT 0
last_outcome       VARCHAR(30) NULL
lost_reason        VARCHAR(40) NULL
lost_note          VARCHAR(255) NULL
is_stale           TINYINT DEFAULT 0
rider_id           INT NULL (→ tblshra_riders)   -- set at convert
first_enrollment_id INT NULL (→ tblshra_enrollments)
won_at             DATETIME NULL
campaign           VARCHAR(80) NULL     -- utm / event name
referrer_rider_id  INT NULL             -- for Referral source
created_at, updated_at
INDEX (stage_key), INDEX (next_action_at), INDEX (visit_date), INDEX (rider_id)
```

**`tblshra_lead_events`** — append-only audit (never updated/deleted)
```
id BIGINT PK, lead_id INT, staff_id INT, event_type VARCHAR(30)
  -- created | assigned | reassigned | call | whatsapp | stage | visit_scheduled |
  -- visit_rescheduled | visited | no_show | confirmed | won | lost | junk |
  -- duplicate_attempt | note | reopen | stale | auto_reassign
outcome VARCHAR(30) NULL, from_value VARCHAR(60) NULL, to_value VARCHAR(60) NULL
note TEXT NULL, meta JSON NULL, ip VARCHAR(45), created_at DATETIME
INDEX (lead_id, created_at), INDEX (staff_id, created_at), INDEX (event_type, created_at)
```

**`tblshra_lead_attribution`** — frozen revenue credit
```
id PK, lead_id INT, agent_id INT (→ tblstaff), rider_id INT, enrollment_id INT UNIQUE,
invoice_id INT, kind ENUM('first','repeat'), amount_billed DECIMAL, amount_paid DECIMAL,
source_id INT, credited_at DATETIME, locked TINYINT DEFAULT 1
INDEX (agent_id, credited_at), INDEX (lead_id)
```
`amount_paid` is refreshed by `sync_paid()` hook so partial/later payments still credit the same agent. `repeat` rows = renewals by the same rider within `shra_lead_repeat_credit_months` (default 12) — agent keeps earning on renewals (configurable on/off).

**`tblshra_lead_targets`** — monthly targets per agent (for leaderboard)
```
id PK, staff_id INT, month CHAR(7) 'YYYY-MM', calls_target INT, visits_target INT,
revenue_target DECIMAL, UNIQUE(staff_id, month)
```

**`tblshra_lead_sources_meta`** — optional per-source cost for CPL/ROI
```
id PK, source_id INT UNIQUE (→ tblleads_sources), monthly_cost DECIMAL, active TINYINT
```

### 2.3 Seeded options (`add_option`)
`shra_lead_sla_minutes=120`, `shra_lead_stale_days=7`, `shra_lead_auto_assign=1` (round-robin among active agents), `shra_lead_visit_slots` (JSON: Sat/Sun 07–08, 08–09, 16–17, 17–18), `shra_lead_repeat_credit_months=12`, `shra_lead_lost_reasons` (JSON), `shra_lead_outcomes` (JSON), `shra_lead_whatsapp_templates` (JSON), `shra_lead_manager_digest=1`.

---

## 3. Permissions & roles

Add capabilities to the existing `register_staff_capabilities('shra', …)`:

| Capability | Who | Grants |
|---|---|---|
| `leads_own` | Calling Agent | See/work **only own** leads; add leads; log calls; schedule visits; mark lost with reason. |
| `leads_all` | Manager / front desk | See all leads, Visits board, mark Visited/Confirmed. |
| `leads_manage` | Manager | Reassign, reopen, bulk import, targets, sources, delete junk. |
| `leads_reports` | Manager/Owner | Agent revenue & funnel reports. |

Seed two `tblroles`: **SHRA Calling Agent** (`shra.view? no`, `shra.leads_own`) and **SHRA Sales Manager** (`leads_all, leads_manage, leads_reports, billing`). Front desk keeps `billing` + `leads_all`.
Agents get **no** core Perfex `leads.*` permission, so the native Leads menu stays hidden; all work happens in the SHRA tab bar. `shra_can_access()` is extended so agents land on `shra/leads` (not the dashboard).

---

## 4. Screens (added to `views/_nav.php` tab bar)

### 4.1 **Leads → My Day** (`shra/leads`) — agent home, mobile-first
Three stacked queues, one-tap actions:
1. **Overdue** (next_action_at < now) — red
2. **Today** (due today) — includes "New" leads not yet called (SLA countdown)
3. **Upcoming** (next 7 days)

Row = name · phone (tap-to-call `tel:`) · WhatsApp button (`wa.me` with template) · stage chip · last outcome · "Log call" button.
**Log-call modal** (the core interaction, ≤10 s): outcome buttons → if `callback/interested` → next date picker (quick chips: *Tomorrow 10am / Sat / Sun / +3 days*) → optional note → Save. The modal also offers "Schedule visit" which jumps to Visit slot picker. Saving without a next action is impossible unless outcome is `lost/junk/visit_scheduled`.

Top strip: **Add lead** (name + phone + source; phone dup check via AJAX on blur, shows owner if exists), my month KPIs (calls, visits booked, visited, revenue, vs target).

### 4.2 **Leads → Pipeline** (`shra/leads/pipeline`) — Kanban by stage
Columns = stages; cards colour by overdue/stale; filters: agent, source, date range, audience, city. Drag = stage change (validated: moving to Visit Scheduled opens the slot modal; moving to Visited/Confirmed restricted to `leads_all`). List view toggle with export CSV (`leads_manage`).

### 4.3 **Leads → Visits board** (`shra/leads/visits`) — front desk / weekend
Date tabs: **Today · This Saturday · This Sunday · Custom**. Grouped by slot. Each visitor card: name, phone, agent, rider-for/age, interest package, buttons **Arrived** (→ Visited), **Confirmed** (package + expected ₹), **No-show**, **Reschedule**. Phone search box for unexpected walk-ins → "Create lead as Walk-in (source) and mark visited" in one tap.
"Confirmed" → button **Bill now** → opens existing `shra/billing` with rider pre-created from lead (name, guardian, mobile, audience) via `Shra_model::add_rider()` + `lead_id` carried in URL → `create_bill()` links attribution.

### 4.4 **Lead detail** (`shra/leads/view/:id`) — drawer/modal
Header (stage, agent, source, SLA/age), contact actions, timeline (merged `tblshra_lead_events` + `tbllead_activity_log`), notes, visit info, rider link if converted, attribution rows. Manager actions: reassign, reopen, mark junk.

### 4.5 **Leads → Team** (`shra/leads/team`) — manager leaderboard
Per agent for selected period: leads assigned · calls · contact rate · visits booked · visited · show rate · confirmed · won · **revenue (billed / collected)** · conversion % · avg days-to-win · overdue count · stale count · target attainment bar. Click agent → drill-down list. Also **Source ROI** table (leads, visits, won, revenue, cost, CPL, ROAS) and **Lost reasons** pie.

### 4.6 **Leads → Settings** (admin) — sources, visit slots, SLA, stale days, lost reasons, outcomes, WhatsApp templates, round-robin agent pool (toggle per staff), targets grid (agent × month), **Import leads** (any sheet: column mapping + dedupe report before commit).

### 4.7 Existing **Dashboard**: add tiles — Open leads, Overdue follow-ups, Visits this weekend, Leads→Revenue this month, top agent.

### 4.8 Existing **Reports**: add "By agent (lead attribution)" and "By lead source" breakdowns using `tblshra_lead_attribution`.

---

## 5. Intake channels (every one dedupes on `phone_norm`)

1. **Manual** (agent/front desk) — `Shra_leads_model::capture()`.
2. **Public QR / web form** — extend existing `/join` flow: add `/inquire` (name, phone, rider-for, interest) → lead, source = *Website QR*, round-robin assigned, agent notified. Reuse `shra_sign()` HMAC pattern for anti-spam + honeypot + rate-limit per IP.
3. **Native Perfex web-to-lead / email integration** — hook `lead_created` → `shra_leads_on_core_lead_created()` creates the `_ext` row, normalises phone, applies round-robin if unassigned, sets `next_action_at = now + SLA`.
4. **Sheet import** (manager, v1.3.2) — any CSV/TSV in any encoding (Meta/Instagram lead exports are UTF-16 tab separated), separator and header row detected, columns mapped by wording and by their values, mapping editable on screen and remembered for next time (`shra_lead_import_map`); unmapped columns are kept as *Question: answer* notes; preview: new / duplicate (shows owner) / invalid phone; commit only clean rows. Parser lives in `libraries/Shra_import.php`, the rest in `Shra_leads_model::import_*`.
5. **Walk-in** — from Visits board or Billing screen phone match.
6. **Referral** — when `referrer_rider_id` set, rider profile shows "Referred N leads / M joined" (future loyalty credit).

---

## 6. Core logic (`models/Shra_leads_model.php`)

```
capture(array $in, $source_ctx)          -> ['lead_id'=>…] | ['duplicate'=>true,'lead_id','owner'] | error
normalize_phone($raw)                    -> digits, strip +91/0 prefix, 10-digit validate (IN default, configurable)
assign($lead_id, $staff_id, $by, $auto)  -> writes tblleads.assigned/dateassigned, event, core notification
next_agent_round_robin()                 -> least-recently-assigned among pool
log_call($lead_id, $outcome, $next_at, $note)  -> attempts++, lastcontact, event, activity_log, stage auto New→Contacted
set_stage($lead_id, $stage_key, $ctx)    -> validates transition matrix + required fields; updates tblleads.status,
                                            last_status_change, _ext.stage_key; fires core 'lead_status_changed'
schedule_visit($lead_id, $date, $slot)   -> stage visit_scheduled, creates/updates tblreminders (agent, 1 day + morning-of),
                                            next_action_at = visit datetime
mark_visited($lead_id, $by)              -> visited_at, stage visited, next_action_at = +1 day (agent closes)
mark_no_show($lead_id)                   -> no_show_count++, next_action_at = now (agent must act)
confirm($lead_id, $package_id, $amount)  -> stage confirmed, expected_value
mark_lost($lead_id, $reason, $note)      -> tblleads.lost=1, reason required
convert_to_rider($lead_id)               -> Shra_model::add_rider() pre-filled; _ext.rider_id; tblleads.client_id
credit_revenue($enrollment, $invoice)    -> find lead by rider_id (or phone_norm match); insert attribution;
                                            stage -> won; tblleads.date_converted
find_open_lead_by_phone($phone)          -> used by billing & visits board
queues_for_agent($staff_id)              -> overdue / today / upcoming
team_stats($from,$to), source_stats(), funnel_stats()
run_cron()                               -> stale flagging, no-show flagging, SLA-breach notifications,
                                            daily 08:00 manager digest (overdue by agent, weekend visits count)
```

Transition matrix (enforced server-side, not just UI):
`new → contacted|visit_scheduled|lost|junk`, `contacted → followup|visit_scheduled|lost|junk`,
`followup → visit_scheduled|lost`, `visit_scheduled → visited|followup(reschedule)|lost`,
`visited → confirmed|followup|lost`, `confirmed → won|followup|lost`, `lost|junk → reopen(followup)` (manage only), `won → (none)`.

### Hooks into existing code
- `Shra_model::create_bill()` — after enrollment insert: `$this->leads->credit_revenue(...)` when `$opts['lead_id']` given **or** `find_open_lead_by_phone(rider.mobile)` matches (walk-in match). Non-blocking (wrapped in try/catch, never fails a bill).
- `Shra_model::collect_payment()/sync_paid()` — update `attribution.amount_paid`.
- `Shra_model::add_rider()` — accept `lead_id` to link.
- Core hooks: `lead_created` (ext row + auto-assign), `lead_status_changed` (keep `_ext.stage_key` in sync if changed from native UI), `before_lead_deleted` (block delete if attribution exists; only `leads_manage`), `after_cron_run` (→ `run_cron()` throttled via option `shra_leads_last_cron`).
- Billing view: phone-match banner "Matches lead #… (Agent …) — credit this agent? [Yes/No]" (default Yes; "No" requires `leads_manage` and logs an event).

---

## 7. Notifications (all via core `add_notification()` + optional email; WhatsApp via existing `sms_wa_email` module if installed)

| Trigger | To |
|---|---|
| Lead assigned | Agent |
| SLA breach (New not called in 2h) | Agent + manager |
| Visit tomorrow / visit today 7am | Agent (+ optional WhatsApp to lead: "See you Saturday 8am at SHRA, location link") |
| Lead visited / confirmed (by front desk) | Agent |
| Bill created for agent's lead | Agent ("₹X credited") |
| Daily 08:00 digest | Managers: overdue per agent, stale leads, weekend visit count, yesterday's revenue by agent |
| Weekly Monday | Managers: leaderboard summary |

---

## 8. Reports & formulas

- **Contact rate** = leads with ≥1 `contacted` / assigned
- **Visit booking rate** = visit_scheduled / contacted · **Show rate** = visited / visit_scheduled
- **Win rate** = won / assigned · **Revenue/agent** = Σ attribution.amount_billed (and collected)
- **Avg days to win** = avg(won_at − dateadded)
- **Source ROI** = revenue / monthly_cost · **CPL** = cost / leads
- All date filters reuse the presets already in `Shra::reports()`.
- Export CSV/XLSX for team & source tables; PDF monthly agent statement via `Shra_pdf` (new `agent_statement()`).

---

## 9. Files to add / change

```
modules/shra/
  shra.php                      bump 1.3.0; new capabilities; hooks (lead_created, lead_status_changed,
                                before_lead_deleted, after_cron_run); landing for agents → shra/leads
  install.php                   4 new tables, stage/source seeds, roles, options
  controllers/Shra_leads.php    index(myday), pipeline, visits, view, add, log_call, stage, schedule_visit,
                                visited, no_show, confirm, lost, reassign, convert, team, sources, settings,
                                import, export, targets  (all JSON for AJAX actions, same `need()` pattern)
  controllers/Shra_public.php   + inquire() / inquire_done()
  models/Shra_leads_model.php   logic above
  models/Shra_model.php         create_bill/collect_payment/add_rider hooks (≈40 lines)
  helpers/shra_helper.php       shra_phone_norm(), shra_wa_link(), shra_stage_badge()
  views/leads/                  myday, pipeline, visits, view(drawer), team, settings, import, partials/
                                (log_call_modal, visit_modal, lost_modal, lead_card)
  views/public_inquire.php, public_inquire_success.php
  views/_nav.php                + Leads tab (+ Visits for billing/attendance staff)
  views/billing.php             + lead-match banner
  views/dashboard.php           + lead tiles
  assets/js/shra_leads.js       queues, modals, kanban (SortableJS already in Perfex), tel/wa links
  language/english/shra_lang.php  + ~60 keys
  docs/LEADS_PLAN.md            this file
application/config/routes.php   + /inquire routes
```

---

## 10. Delivery phases

| Phase | Scope | Outcome |
|---|---|---|
| **1 — Foundation** (schema, roles, capture, dedupe, assign, My Day + Log call, lead view) | Agents can start working leads with mandatory next-action | Core leak-proofing live |
| **2 — Visits** (Visit Scheduled slots, reminders, Visits board, Visited/No-show/Confirmed, convert-to-rider, Bill-now) | Weekend operations covered | Visit tracking end-to-end |
| **3 — Revenue attribution** (create_bill/collect hooks, walk-in phone match, attribution table, agent credit notifications) | Revenue per agent is trustworthy | Reports data exists |
| **4 — Pipeline, Team leaderboard, Source ROI, targets, Reports/Dashboard tiles, exports, PDF statement** | Managers get visibility | Performance management |
| **5 — Automation** (cron: SLA, stale, no-show, digests; public /inquire QR form; CSV import; WhatsApp templates) | Nothing falls through | Fully "smart" |

Each phase is independently shippable and re-runnable (`install.php` guards + `shra_schema_version` self-heal already in place).

---

## 11. Open decisions (defaults assumed; change in Settings)
1. Country/phone format: **India 10-digit** default.
2. Renewal credit to original agent: **on, 12 months**.
3. Round-robin auto-assign for QR/web leads: **on**.
4. Agents may see other agents' leads: **no** (only `leads_all`).
5. Visit slots: **Sat & Sun 07–09 and 16–18**, editable.
