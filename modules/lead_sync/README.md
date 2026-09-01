# Lead Sync

Automatic lead capture from Google Sheets. Point it at the sheet your Meta /
Instagram / Google lead ads land in and every new row becomes a CRM lead —
mapped to the right fields, checked against the leads you already have, and
handed to an agent.

Nothing in here is specific to one business, so the same module serves any
tenant.

## Why this is a separate module, not part of SHRA

Leads created here are ordinary `tblleads` rows and the module fires the core
`lead_created` hook. That is the whole integration story:

* **SHRA tenants** get them in the leads desk for free — `shra.php` already
  listens for `lead_created` and calls `adopt_core_lead()`, which sets up phone
  identity, the SLA timer, round-robin assignment and the event log. Lead Sync
  knows nothing about SHRA and SHRA knows nothing about Lead Sync.
* **Every other tenant** gets them in the native Leads screen, with native
  notifications, tags and custom fields.

Building this inside SHRA would have buried a horizontal feature inside one
tenant's vertical, and any second tenant would have had to install a horse
riding academy to import their Facebook leads.

## Two ways a row arrives

| | Setup | Latency |
|---|---|---|
| **Polling** | Paste the sheet link. Runs on the CRM cron. | The connection's interval (default 15 min) |
| **Instant push** | Paste the generated Apps Script into the sheet, run `installTrigger()` once. | Seconds |

Both run the identical pipeline, including the row fingerprints, so they can be
used together without any risk of double-importing.

## Reading the sheet — three modes

| Mode | Sheet visibility | Setup |
|---|---|---|
| `public` | "Anyone with the link" or Published to web | none |
| `service_account` | stays private | share the sheet with the robot's e-mail; paste its JSON key (stored encrypted) |
| `api_key` | "Anyone with the link" | paste a Sheets-API-restricted key (stored encrypted) |

`service_account` is the right default for real lead data — the sheet never
becomes readable by anyone holding a link. The JWT is signed with `openssl_sign`;
there is no Composer dependency.

## What stops duplicates

Two independent mechanisms, because they catch different mistakes:

1. **Row fingerprint** (`tbllead_sync_rows`) — a hash of the row's values,
   order-independent. A re-run, a re-sorted sheet, a webhook retry or a
   cron/webhook race cannot import the same line twice. The unique key on
   `(connection_id, row_hash)` settles genuine races.
2. **Lead identity** — phone digits (or e-mail) matched against every existing
   lead. `+91 98765 43210` and `098765 43210` are the same person. A repeat
   enquiry is logged on the lead that already exists instead of becoming a
   second one. The index is built once per run and updated as leads are created,
   so two rows for one person inside a single sheet also collapse.

## Column mapping

Meta's own export wording is understood out of the box (`full_name`,
`phone_number`, `created_time`, `platform`, and `ad_id` / `form_id` / `is_organic`
are recognised as machine columns and skipped). Meta's `p:`/`l:` value prefixes,
UTF-16 exports and `evening:_4pm_-_9pm` style answers are all cleaned up.

Anything left unmapped is **not** discarded — it goes into the lead's description
as `Question: answer`, so nothing the person typed is lost. Mapping choices are
stored per connection and re-applied on every later sync, including when the
campaign adds new questions.

`platform` values are spelled out (`ig` → Instagram, `fb` → Facebook) so a
campaign cannot silently split your source report in two.

## Files

```
lead_sync.php                    bootstrap: menu, permissions, cron, schema self-heal
install.php                      idempotent schema + options
helpers/lead_sync_helper.php     lookups, credential encryption, phone identity, Apps Script
libraries/Lead_sync_sheet.php    CRM-free: decode, parse, guess columns, fingerprint
libraries/Lead_sync_google.php   URL derivation, HTTP, service-account JWT, Sheets API
models/Lead_sync_model.php       connections, the import pipeline, run history
controllers/Lead_sync.php        admin screens
controllers/Lead_sync_push.php   POST /lead_sync/push/{token}
config/routes.php                the module's own route for that endpoint
config/csrf_exclude_uris.php     exempts that endpoint from CSRF
```

**No core file is modified.** The webhook needs a route and a CSRF exemption, and
the platform already lets a module own both: HMVC reads `config/routes.php` for
any URI whose first segment is the module name, and
`application/hooks/InitModules.php` merges every module's
`config/csrf_exclude_uris.php` through the `csrf_exclude_uris` filter during the
`pre_system` hook — before Security and Input exist. Drop the folder in, activate,
done; delete the folder and nothing is left behind.

## Hooks for other modules

```php
// Change a lead before it is written
hooks()->add_filter('lead_sync_before_lead_added', function ($data, $context) { … }, 10, 2);

// React after one is imported: ['lead_id' => int, 'connection_id' => int]
hooks()->add_action('lead_sync_lead_imported', function ($payload) { … });
```

The core `lead_created` action fires for every imported lead, so most
integrations need nothing from this module at all.
