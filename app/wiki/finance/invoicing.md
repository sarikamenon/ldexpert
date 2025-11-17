# Invoice Schools / Private Families PRD

## Purpose
Generate accurate, auditable invoices for schools or private families based on SSA authorizations and delivered sessions. Ensure revenue recognition aligns with contracted rates, and provide finance teams with review + export capabilities.

## Personas
- **Accounts Receivable (AR)** — owns invoice generation, review, delivery, and reconciliation.
- **Program Admin** — reviews service delivery variances before invoices post.
- **School Contacts / Families** — receive invoices and need visibility into supporting detail.

## Current Implementation
- No invoice tables or workflows exist. Revenue tracking is done outside NOVA; sessions are not persisted with billable metadata.

## Planned Scope
- Record session delivery data linked to SSA services, then roll them into invoices grouped by school/family and billing cycle.
- Support both time-and-material (hourly/session) and flat-fee services.
- Provide adjustments/credit memos, tax handling (if applicable), and export to accounting (e.g., QuickBooks).
- Offer PDF invoice generation plus API/email delivery.

## Domain Model
### Tables
| Table | Fields |
| --- | --- |
| `sessions` (new) | `id`, `student_id`, `therapist_id`, `ssa_service_id`, `service_id`, `delivered_on`, `duration_minutes`, `notes`, `status` (draft, submitted, approved), `source` (manual, RSM, CAVA), timestamps.
| `invoices` | `id`, `school_id` or `family_id`, `billing_period_start`, `billing_period_end`, `status` (draft, pending-approval, sent, paid, voided), `currency`, `subtotal`, `tax_total`, `total`, `due_on`, `external_id`, timestamps.
| `invoice_lines` | `id`, `invoice_id`, `session_id` or `ssa_service_id`, `description`, `quantity`, `unit`, `unit_rate`, `amount`, `gl_code`, `notes`.
| `invoice_adjustments` | credits/debits with reason codes and links to original lines.
| `invoice_documents` | store PDF artifacts for auditing.

### Rules
- Sessions must be approved before inclusion in invoices.
- Rates derive from `school_service_rates` or SSA overrides; adjustments capture deviations.
- One invoice per school per billing cadence window (weekly/monthly) with ability to split by funding source.

## API & Routes
### Admin Web
- `GET /admin/invoices` with filters for status, school, date range.
- `GET /admin/invoices/generate?period=2025-11&school_id=…` — opens wizard to review billable sessions.
- `POST /admin/invoices` — creates draft invoice populated from selected sessions.
- `GET /admin/invoices/{invoice}` — detail view with tabs (lines, adjustments, delivery history, audit log).
- `PATCH /admin/invoices/{invoice}` — edit descriptions, due date, memo.
- `POST /admin/invoices/{invoice}/approve` — locks invoice and triggers PDF generation.
- `POST /admin/invoices/{invoice}/send` — emails PDF to school contacts + posts to API.
- `POST /admin/invoices/{invoice}/record-payment` — capture payment date, amount, reference.

### API v1
- `GET /api/v1/invoices` & `GET /api/v1/invoices/{id}` for read-only consumption by ERP.
- `POST /api/v1/invoices/{id}/payment` to sync payments from accounting.

## Workflows
1. **Billing Cycle Close**
   1. Scheduler aggregates approved sessions for each school + cadence.
   2. AR reviews summary (units vs. authorized) and generates draft invoice.
   3. After approval, system locks sessions, creates invoice record, renders PDF, optionally uploads to SFTP or emails contact.
2. **Adjustments / Credit Memo**
   1. AR disputes a session or needs discount.
   2. Add `invoice_adjustments` line referencing original; totals recalc and new PDF generated.
   3. Export adjustments to accounting.
3. **Private Family Billing**
   1. When `type=private`, invoice address pulls from guardian contact; payment portal link included.
   2. Support partial payments and autopay (future integration).

## Integrations & Dependencies
- Depends on SSA module for authorized units/rates and on Sessions module (source for invoice lines).
- Sync module may import sessions from RSM/CAVA; invoices should reference `sessions.source` for traceability.
- Accounting export: build CSV/JSON integration or QuickBooks API client.

## Metrics
- AR aging by school.
- Utilization variance (delivered vs. authorized) per invoice.
- Write-off and adjustment volume.

## Risks & Open Questions
- Need decision on tax handling (are services tax-exempt?).
- Determine e-delivery requirements (EDI vs. email) for large districts.
- Clarify when to bill: scheduled vs. delivered sessions; plan assumes delivered + approved.
