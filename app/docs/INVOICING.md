# Invoicing — Complete Reference

> **Scope:** how an `invoices` row comes into existence, gets populated with line items, is sent, paid, and reconciled — across both **manual** (admin "Create Invoice") and **automatic** (`billing:generate`) paths, and across both **Standard (postpaid)** and **Advance (prepaid)** billing modes.
>
> **Audience:** developers working on the invoice, billing, or payment surfaces.
>
> **Source of truth (files):**
> - Controller: [`InvoiceController`](../../app/app/Http/Controllers/Admin/InvoiceController.php)
> - Service (manual): [`InvoiceService`](../../app/app/Domain/Invoice/Services/InvoiceService.php)
> - Repository: [`EloquentInvoiceRepository`](../../app/app/Infrastructure/Repositories/EloquentInvoiceRepository.php)
> - Automation: [`BillingAutomationService`](../../app/app/Domain/Billing/Services/BillingAutomationService.php), [`AdvanceBillingService`](../../app/app/Domain/Billing/Services/AdvanceBillingService.php), [`BillingScheduleService`](../../app/app/Domain/Billing/Services/BillingScheduleService.php)
> - Models: [`Invoice`](../../app/app/Models/Invoice.php), [`InvoiceLineItem`](../../app/app/Models/InvoiceLineItem.php), [`SessionLog`](../../app/app/Models/SessionLog.php), [`Schedule`](../../app/app/Models/Schedule.php), [`BillingSchedule`](../../app/app/Models/BillingSchedule.php)
> - Enums: [`InvoiceStatus`](../../app/app/Enums/InvoiceStatus.php), [`BillingMode`](../../app/app/Enums/BillingMode.php), [`InvoiceLineType`](../../app/app/Enums/InvoiceLineType.php), [`InvoiceEmailType`](../../app/app/Enums/InvoiceEmailType.php)
>
> **Companion docs (in `app/docs/`):**
> - [`SCHOOL_INVOICE_SCHEDULE.md`](SCHOOL_INVOICE_SCHEDULE.md) — every field on a school's `billing_schedules` row and how it drives auto-generation.
> - [`BILLING_AUTOMATION_RUNTIME.md`](BILLING_AUTOMATION_RUNTIME.md) — how the daily `billing:generate` command picks schedules, resolves periods, sweeps sessions, and advances `next_run_at`.
> - [`LEDGER_SYSTEM.md`](LEDGER_SYSTEM.md) — how a sent invoice posts to the ledger.

---

## 1. Mental model

An **invoice** bills a **school or a private family** for therapy work over a **billing period**. There are two fundamentally different ways the line items are sourced, governed by `billing_mode`:

| Mode | `billing_mode` | Bills for | Data source | Timing |
|---|---|---|---|---|
| **Standard** (Postpaid) | `standard` | work **already delivered** | **approved `session_logs`** | invoice cut **after** the period closes |
| **Advance** (Prepaid) | `advance` | work **about to happen** | **`schedules`** (status `SCHEDULED`) + a reconciliation adjustment against last period's actuals | invoice cut **before** the period begins |

There are also two ways an invoice gets **created**:

1. **Manual** — an admin clicks "Create Invoice", picks a school/family + period, and attaches approved session logs.
2. **Automatic** — the daily `billing:generate` command processes every due `billing_schedules` row.

> ⚠️ **The single most important asymmetry in this system:** *the manual path only ever produces a **Standard** invoice.* See [§8 Known gaps](#8-known-gaps--gotchas). Advance invoices are produced **only** by the automatic path.

---

## 2. Data model

### 2.1 `invoices` table

Created in [`2025_12_31_095337_create_invoices_table.php`](../../app/database/migrations/2025_12_31_095337_create_invoices_table.php); advance/family columns added in [`2026_03_19_100005_add_advance_billing_columns_to_invoices_table.php`](../../app/database/migrations/2026_03_19_100005_add_advance_billing_columns_to_invoices_table.php).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `school_id` | FK → `schools` | the billed school/family (families are modelled as schools) |
| `student_id` | FK → `users`, nullable | set for family/student invoices |
| `parent_id` | FK → `users`, nullable | set for family invoices |
| `invoice_number` | string, unique | `INV-YYYYMMDD-NNN` (see §6) |
| `invoice_date` | date | |
| `billing_period_start` / `billing_period_end` | date | the work window |
| `status` | string(32), default `draft` | enum `InvoiceStatus` — `draft` / `sent` / `paid` |
| `billing_mode` | string(20), **default `standard`** | enum `BillingMode` — `standard` / `advance` |
| `invoice_type` | string(30), default `school` | `school` / family variant |
| `subtotal` / `tax_total` / `total` | decimal(10,2) | `tax_total` is always 0 today (see §8) |
| `carry_forward_balance` | decimal(10,2), default 0 | advance-mode overpayment carried to next period |
| `due_date` | date | **hardcoded** to `invoice_date + 30 days` on the manual path |
| `school_*` / `company_*` / `parent_*` | strings | **snapshot** columns — frozen copies of school/company/parent details at creation time, so the invoice never mutates if the source records change later |
| `sent_at` / `sent_by_id` | dateTime / FK → `users` | stamped when sent |
| `paid_at` | dateTime, nullable | |
| `payment_token` | string, nullable | UUID for the public online-payment link |
| `notes` | text, nullable | |
| `timestamps`, `softDeletes` | | soft-deletable |

> **Snapshots, not joins.** Bill-to and from details on an invoice are copied at creation (`copySchoolSnapshot()`, `copyCompanySnapshot()` in `InvoiceService`). Editing a school's address later does **not** change an already-created invoice. This is intentional — invoices are financial documents and must be immutable in their displayed identity.

### 2.2 `session_logs` ↔ invoice linkage (Standard)

`session_logs.invoice_id` (FK → `invoices`, nullable). A session log is "on" an invoice when this is set. This is the linkage used by **Standard** invoices.

`Invoice::sessionLogs()` is a `hasMany` on `session_logs.invoice_id`.

### 2.3 `invoice_line_items` table (Advance + future-proof line modelling)

[`InvoiceLineItem`](../../app/app/Models/InvoiceLineItem.php) carries the explicit, typed line items used by **Advance** invoices (and available for richer Standard lines). Key columns:

| Column | Notes |
|---|---|
| `invoice_id` | FK |
| `line_type` | enum `InvoiceLineType` (see §3.3) |
| `schedule_id` | FK → `schedules`, nullable — set for `advance_scheduled` charges |
| `session_log_id` | FK → `session_logs`, nullable — set for adjustment lines |
| `source_invoice_id` | FK → `invoices`, nullable — the prior invoice an adjustment reconciles against |
| `description`, `quantity`, `unit_price`, `total`, `sort_order` | line presentation/maths |
| `billing_period_start` / `billing_period_end` | date |

Scopes: `advanceCharges()`, `adjustments()`, `carryForward()`, `forPeriod($start, $end)`. Helpers: `isAdvanceCharge()`, `isAdjustment()`, `isCredit()` (delegate to the enum).

> **Standard invoices today don't write `invoice_line_items` rows** — they link `session_logs` directly and compute totals from them. `invoice_line_items` is populated by the **Advance** path. The `show` view renders Standard invoices from `sessionLogs` and Advance invoices from `lineItems`.

### 2.4 Related tables

- `invoice_email_logs` — one row per send/resend (`InvoiceEmailType`: `initial` / `resend`).
- `invoice_payments` + `invoice_payment_allocations` — payments recorded against invoices (see §7).
- `payment_gateway_transactions` — online card payments via the public payment link.
- `ledger_entries` — a sent invoice posts an `invoice_generated` ledger entry (see [`LEDGER_SYSTEM.md`](LEDGER_SYSTEM.md)).

---

## 3. Enums

### 3.1 `InvoiceStatus`
`draft` → `sent` → `paid`. (No explicit `voided` — the column comment mentions it but the enum has only three cases.) Model helpers: `isDraft()`, `isSent()`, `isPaid()`.

### 3.2 `BillingMode`
`standard` (Postpaid) / `advance` (Prepaid). Model helper: `isAdvanceMode()`.

### 3.3 `InvoiceLineType`

| Value | Meaning | Credit? |
|---|---|---|
| `session_charge` | a delivered, approved session (Standard) | no |
| `advance_scheduled` | a future scheduled session prepaid in advance | no |
| `adjust_no_show` | prepaid session that didn't happen → refund | **yes** |
| `adjust_cancel_billable` | billable cancellation adjustment | **yes** |
| `adjust_cancel_non_billable` | non-billable cancellation → full credit | **yes** |
| `adjust_extra_session` | session happened that wasn't prepaid → charge | no |
| `adjust_rate_difference` | actual rate differed from prepaid rate | depends on sign |
| `carry_forward_credit` | overpayment from a prior advance invoice | **yes** |

Helpers: `isAdvanceCharge()`, `isAdjustment()`, `isCredit()`.

---

## 4. Manual invoice creation (admin "Create Invoice")

### 4.1 Flow

```
GET  admin/invoices/create        → InvoiceController::create()      (form)
POST admin/invoices               → InvoiceController::store()       (creates draft)
GET  admin/invoices/{id}/attach-sessions  → attachSessions()         (session picker)
POST admin/invoices/{id}/attach-sessions  → storeAttachedSessions()  (links logs, recomputes totals)
GET  admin/invoices/{id}          → show()                           (detail)
```

Routes: [`routes/admin.php:233-240`](../../app/routes/admin.php#L233-L240).

### 4.2 Step 1 — create the draft

[`InvoiceController::store()`](../../app/app/Http/Controllers/Admin/InvoiceController.php#L119-L145) validates via [`CreateInvoiceRequest`](../../app/app/Http/Requests/Admin/Invoice/CreateInvoiceRequest.php), builds a [`CreateInvoiceDTO`](../../app/app/DTOs/CreateInvoiceDTO.php), and calls [`InvoiceService::generateInvoice()`](../../app/app/Domain/Invoice/Services/InvoiceService.php#L38-L91).

The form collects **only**: `school_id`, `invoice_date`, `invoice_number` (optional, auto-generated if blank), `billing_period_start`, `billing_period_end`, optional `session_log_ids`, `notes`. There is **no `billing_mode` field** — so the new row falls back to the DB default `standard`.

Two branches inside `generateInvoice()`:
- **No session logs selected** → `createDraftWithoutSessions()` writes a `$0` draft and redirects to the **attach-sessions** picker.
- **Session logs selected** → fetches them via `getApprovedSessionLogsForInvoice()` (must be `APPROVED` + `is_billable_school` + not already on an invoice + belong to the chosen school), computes totals, creates the invoice, links the logs.

### 4.3 Step 2 — attach/remove sessions ("Add or remove sessions")

[`attachSessions()`](../../app/app/Http/Controllers/Admin/InvoiceController.php#L259-L303) (draft-only; 404 otherwise) shows currently-attached logs **plus** the eligible-to-add logs from [`getAvailableSessionLogsForInvoiceCreation()`](../../app/app/Infrastructure/Repositories/EloquentInvoiceRepository.php#L173-L220):

```php
SessionLog::query()
    ->where('status', SessionLogStatus::APPROVED->value)   // must be APPROVED
    ->where('is_billable_school', true)
    ->whereNull('invoice_id')                              // not already on an invoice
    ->whereBetween('session_date', [$date_from, $date_to]) // within the period (defaults to invoice period)
    // + optional school_id / therapist_id / student_id / service_id / search filters
```

[`storeAttachedSessions()`](../../app/app/Http/Controllers/Admin/InvoiceController.php#L305-L327) → [`InvoiceService::attachSessionsToDraft()`](../../app/app/Domain/Invoice/Services/InvoiceService.php#L277-L308): unlinks all current logs, re-validates the requested IDs via `getSessionLogsForInvoiceUpdate()` (same school, approved, billable, and either unlinked or already on *this* invoice), re-links them, and recomputes totals. Selecting nothing zeroes the invoice.

> **Why the picker can be empty:** because it queries **`session_logs`** (approved, billable, unlinked), an invoice whose period has **no approved session logs** shows nothing to add — even if future `schedules` exist for that period. Schedules are not session logs. This is by design for Standard mode. See [§8](#8-known-gaps--gotchas).

### 4.4 Totals (Standard)

[`calculateTotals()`](../../app/app/Domain/Invoice/Services/InvoiceService.php#L126-L137): `subtotal = Σ session_logs.school_invoice_amount`, `tax_total = 0`, `total = subtotal`.

---

## 5. Automatic invoice generation (`billing:generate`)

The daily command [`BillingGenerate`](../../app/app/Console/Commands/BillingGenerate.php) (`php artisan billing:generate --type=school_invoice`) calls [`BillingAutomationService::processAllDueSchedules()`](../../app/app/Domain/Billing/Services/BillingAutomationService.php#L41), which iterates every `billing_schedules` row that is `is_active`, `auto_generate`, and due (`next_run_at <= today`).

[`processSingleSchedule()`](../../app/app/Domain/Billing/Services/BillingAutomationService.php#L73-L83) routes on the schedule's mode:

```php
if ($schedule->billing_mode === BillingMode::ADVANCE) {
    return $this->advanceBillingService->processAdvanceSchedule($schedule, $dryRun);
}
return $this->processStandardSchedule($schedule, $dryRun);
```

### 5.1 Standard auto-generation

[`processStandardSchedule()`](../../app/app/Domain/Billing/Services/BillingAutomationService.php#L85) → `processSchoolInvoice()`:
- Resolves the **completed** period for the schedule's frequency.
- [`sweepUnInvoicedSessions()`](../../app/app/Domain/Billing/Services/BillingAutomationService.php#L242) collects **every** approved, billable, uninvoiced `SessionLog` with `session_date <= period_end` (no lower bound — late entries from prior periods ride along).
- Creates the invoice with `billing_mode` left at `standard`, links the logs, and stamps `next_run_at` forward.

### 5.2 Advance auto-generation

[`AdvanceBillingService::processAdvanceSchedule()`](../../app/app/Domain/Billing/Services/AdvanceBillingService.php#L47):

1. **Resolve periods** — `resolveCompletedPeriod()` (the period just finished) and `resolveUpcomingPeriod()` (the next period, = day after completed period, run through `BillingScheduleService::determineBillingPeriod()`).
2. **Adjustment lines** — [`buildAdjustmentLines()`](../../app/app/Domain/Billing/Services/AdvanceBillingService.php#L286) reconciles last period's `advance_scheduled` charges against what actually happened (uses **approved `session_logs`** for the completed period): no-shows → credit, cancellations → credit, extra sessions → charge, rate differences → ±. Plus any `carry_forward_credit`.
3. **Advance charge lines** — [`buildAdvanceChargeLines()`](../../app/app/Domain/Billing/Services/AdvanceBillingService.php#L357) queries **`schedules`** in the upcoming period:
   ```php
   Schedule::query()
       ->where('school_id', $schoolId)
       ->whereBetween('schedule_date', [$periodStart, $periodEnd])
       ->where('status', ScheduleStatus::SCHEDULED->value)
       ...
   ```
   Each becomes an `advance_scheduled` line on `invoice_line_items` (with `schedule_id` set).
4. **Create invoice** — [`createAdvanceInvoice()`](../../app/app/Domain/Billing/Services/AdvanceBillingService.php#L601) **explicitly sets `'billing_mode' => BillingMode::ADVANCE->value`** (line ~625), sets `carry_forward_balance`, and writes the merged/numbered line items.

> So `billing_mode = advance` is set in **exactly one place**: `createAdvanceInvoice()`. Nothing else in the codebase writes `advance`.

---

## 6. Invoice numbering

[`generateInvoiceNumber()`](../../app/app/Infrastructure/Repositories/EloquentInvoiceRepository.php#L153-L167): `INV-YYYYMMDD-NNN`, where `NNN` is a per-day sequence based on the last invoice created **today**. Manual creation auto-fills this if the form field is blank; the admin may override with any `^[A-Z0-9\-]+$` value (validated unique).

---

## 7. Lifecycle: send, pay, reconcile

### 7.1 Send

`POST admin/invoices/{id}/send` → [`InvoiceController::send()`](../../app/app/Http/Controllers/Admin/InvoiceController.php#L200-L223) → [`InvoiceService::sendInvoice()`](../../app/app/Domain/Invoice/Services/InvoiceService.php#L173-L226):
- Refuses if already `sent` or `paid`.
- Resolves recipient: `school_invoice_email` → `school_contact_email` → DTO email override.
- If `total > 0`, ensures a `payment_token` and builds the public payment URL.
- Sends `InvoiceMail` (PDF attached). **The mail failure re-throws** (sending is the primary intent) → controller shows a friendly error.
- Logs an `initial` `InvoiceEmailLog`, marks `status = sent` (`sent_at`, `sent_by_id`), and posts an `invoice_generated` **ledger entry** via `LedgerService`.

Resend: `POST .../resend-email` → `resendEmail()` → `resendInvoiceEmail()` (sent-but-not-paid only; **reuses** the existing payment token, never regenerates; logs a `resend` row).

### 7.2 Download PDF

`GET admin/invoices/{id}/download` → `download()` → `InvoicePdfService::generatePdf()`.

### 7.3 Pay

- Admin-recorded payments: `POST admin/invoices/{id}/payments` ([`InvoicePaymentController`](../../app/app/Http/Controllers/Admin/InvoicePaymentController.php)) → `invoice_payments` + `invoice_payment_allocations`.
- Public online payment: `payment.*` routes ([`routes/web.php:36`](../../app/routes/web.php#L36)) via the `payment_token` link → `payment_gateway_transactions`.
- Model computes `total_paid` (Σ allocations), `balance_remaining`, `isFullyPaid()`, `isPartiallyPaid()`.

### 7.4 Show

[`show()`](../../app/app/Http/Controllers/Admin/InvoiceController.php#L147-L198) renders differently per mode:
- **Standard** → from `sessionLogs`.
- **Advance** (`isAdvanceMode()`) → loads `lineItems` and splits them into adjustment / advance / other buckets with their subtotals. Email-log timestamps are formatted in the school's timezone.

---

## 8. Known gaps & gotchas

1. **Manual create always produces a Standard invoice.** The "Create Invoice" form, [`CreateInvoiceRequest`](../../app/app/Http/Requests/Admin/Invoice/CreateInvoiceRequest.php), and [`CreateInvoiceDTO`](../../app/app/DTOs/CreateInvoiceDTO.php) have **no `billing_mode` field**, and [`InvoiceService::generateInvoice()`](../../app/app/Domain/Invoice/Services/InvoiceService.php#L38) never reads the family's `billing_schedules.billing_mode`. The new row therefore takes the DB default `standard`. **Consequence:** a private family configured for **Advance** billing, if invoiced manually, gets a **Standard** invoice — whose session picker then needs approved `session_logs` and shows nothing if only future `schedules` exist. (This is the source of the "schedules exist but Add/remove sessions is empty, and the invoice totals $0" symptom.) A fix would resolve the family's `BillingSchedule` and route advance families through `AdvanceBillingService` (or at minimum set `billing_mode` correctly).

2. **`session_date` is filtered as UTC, not therapist-local** in the available-sessions query — flagged inline at [`EloquentInvoiceRepository.php:181`](../../app/app/Infrastructure/Repositories/EloquentInvoiceRepository.php#L181) (`_local_docs/session-logs-utc-migration-plan.md`). A session near a day boundary can fall outside the expected period.

3. **`due_date` is hardcoded** to `+30 days` on the manual path (`InvoiceService`), ignoring any payment-terms config. The advance path uses the schedule's `payment_terms_days`.

4. **`tax_total` is always 0** — `calculateTotals()` has a placeholder comment; no tax engine exists yet.

5. **No `voided` status** — the enum is `draft`/`sent`/`paid` only, despite the column comment. Soft-delete is the only "remove" path.

6. **Standard invoices don't write `invoice_line_items`** — they link `session_logs` directly. Only Advance invoices populate `invoice_line_items`. Any reporting that reads `invoice_line_items` will miss Standard invoices.

---

## 9. Quick reference — "which data source feeds the lines?"

| Situation | Lines come from | Status filter | Linkage |
|---|---|---|---|
| Manual create / attach-sessions | `session_logs` | `APPROVED` + `is_billable_school` + `invoice_id IS NULL` | `session_logs.invoice_id` |
| Auto Standard sweep | `session_logs` | approved, billable, uninvoiced, `session_date <= period_end` | `session_logs.invoice_id` |
| Auto Advance — upcoming charges | `schedules` | `SCHEDULED`, `schedule_date` in upcoming period | `invoice_line_items.schedule_id` |
| Auto Advance — reconciliation | `session_logs` (completed period) | approved | `invoice_line_items.session_log_id` / `source_invoice_id` |
