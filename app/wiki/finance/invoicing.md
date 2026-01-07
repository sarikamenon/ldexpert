# Invoice Schools / Private Families PRD

## Purpose

Generate accurate, auditable invoices for schools or private families based on SSA authorizations and delivered sessions. Ensure revenue recognition aligns with contracted rates, and provide finance teams with review + export capabilities.

## Personas

-   **Accounts Receivable (AR)** — owns invoice generation, review, delivery, and reconciliation.
-   **Program Admin** — reviews service delivery variances before invoices post.
-   **School Contacts / Families** — receive invoices and need visibility into supporting detail.

## Current Implementation

### Core Features ✅

-   **Invoice Generation**: Admins can create invoices from approved session logs, grouped by school and billing period.
-   **Session Log Integration**: Invoices are generated from approved `session_logs` that contain `school_invoice_amount` calculated from school contracts.
-   **PDF Generation**: System generates PDF invoices with company and school snapshot data.
-   **Email Delivery**: Admins can send invoices via email with PDF attachment to school contacts.
-   **Status Workflow**: Invoices support three statuses: `draft`, `sent`, `paid`.
-   **Snapshot Data**: System captures school and company information at invoice creation time for historical accuracy.

### Database Schema ✅

-   **`invoices` table**: Stores invoice records with school snapshot (name, address, contacts) and company snapshot (name, address, phone, email, tax ID).
-   **`session_logs.invoice_id`**: Foreign key linking session logs to invoices.
-   **Soft Deletes**: All invoices support soft deletion.

### Routes & Controllers ✅

-   `GET /admin/invoices` - List invoices with filters (status, school, date range)
-   `GET /admin/invoices/create` - Create invoice form with available session logs
-   `POST /admin/invoices` - Create draft invoice from selected session logs
-   `GET /admin/invoices/{invoice}` - View invoice details with session log lines
-   `GET /admin/invoices/{invoice}/download` - Download PDF invoice
-   `POST /admin/invoices/{invoice}/send` - Send invoice via email

### Business Rules ✅

-   Only approved session logs can be included in invoices.
-   All session logs in an invoice must belong to the same school.
-   Invoice totals are calculated from `school_invoice_amount` on session logs.
-   School and company information is snapshotted at invoice creation time.
-   Invoice numbers are auto-generated if not provided.

## Planned Scope (Future Enhancements)

-   Support for private family billing (currently school-only).
-   Invoice adjustments/credit memos.
-   Tax calculation and handling.
-   Payment recording and tracking.
-   Export to accounting systems (e.g., QuickBooks).
-   API endpoints for ERP integration.

## Domain Model

### Tables (Implemented ✅)

| Table          | Fields                                                                                                                                                                                                                                                                                      | Status         |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------- |
| `session_logs` | `id`, `student_id`, `therapist_id`, `ssa_id`, `service_id`, `school_id`, `session_date`, `duration_minutes`, `school_invoice_amount`, `invoice_id`, `status` (draft, submitted, approved), timestamps.                                                                                      | ✅ Implemented |
| `invoices`     | `id`, `school_id`, `invoice_number`, `invoice_date`, `billing_period_start`, `billing_period_end`, `status` (draft, sent, paid), `subtotal`, `tax_total`, `total`, `due_date`, school snapshot fields, company snapshot fields, `sent_at`, `sent_by_id`, `notes`, timestamps, `deleted_at`. | ✅ Implemented |

### Tables (Planned)

| Table                 | Fields                                                                          | Status    |
| --------------------- | ------------------------------------------------------------------------------- | --------- |
| `invoice_lines`       | Separate line items table (currently using direct `session_logs` relationship). | 🔄 Future |
| `invoice_adjustments` | Credits/debits with reason codes and links to original lines.                   | 🔄 Future |
| `invoice_documents`   | Store PDF artifacts for auditing.                                               | 🔄 Future |

### Rules (Implemented ✅)

-   ✅ Session logs must be approved before inclusion in invoices.
-   ✅ Rates derive from school contracts (`school_contracts` and `school_service_rates`).
-   ✅ All session logs in an invoice must belong to the same school.
-   ✅ Invoice totals calculated from `school_invoice_amount` on session logs.
-   ✅ School and company information snapshotted at invoice creation.

### Rules (Planned)

-   One invoice per school per billing cadence window (weekly/monthly) with ability to split by funding source.
-   Support for adjustments/credit memos.

## API & Routes

### Admin Web (Implemented ✅)

-   ✅ `GET /admin/invoices` - List invoices with filters (status, school, date range, search).
-   ✅ `GET /admin/invoices/create` - Create invoice form showing available approved session logs.
-   ✅ `POST /admin/invoices` - Create draft invoice from selected session logs.
-   ✅ `GET /admin/invoices/{invoice}` - View invoice details with session log lines.
-   ✅ `GET /admin/invoices/{invoice}/download` - Download PDF invoice.
-   ✅ `POST /admin/invoices/{invoice}/send` - Send invoice via email with PDF attachment.

### Admin Web (Planned 🔄)

-   🔄 `PATCH /admin/invoices/{invoice}` - Edit descriptions, due date, memo.
-   🔄 `POST /admin/invoices/{invoice}/record-payment` - Capture payment date, amount, reference.

### API v1 (Planned 🔄)

-   🔄 `GET /api/v1/invoices` & `GET /api/v1/invoices/{id}` - Read-only consumption by ERP.
-   🔄 `POST /api/v1/invoices/{id}/payment` - Sync payments from accounting.

## Workflows

### Implemented ✅

1. **Invoice Generation Workflow**

    1. Admin navigates to invoice creation page.
    2. System displays available approved session logs (filtered by school, date range).
    3. Admin selects session logs and provides billing period dates.
    4. System validates all session logs belong to same school.
    5. System calculates totals from `school_invoice_amount` on session logs.
    6. System creates draft invoice with school and company snapshots.
    7. System links selected session logs to invoice.

2. **Invoice Sending Workflow**
    1. Admin views draft invoice.
    2. Admin clicks "Send Invoice" button.
    3. System generates PDF invoice.
    4. System sends email to school's invoice email address with PDF attachment.
    5. System marks invoice as `sent` and records `sent_at` timestamp.

### Planned 🔄

1. **Billing Cycle Close**

    - Automated scheduler to aggregate approved sessions for each school + cadence.
    - AR reviews summary (units vs. authorized) and generates draft invoice.
    - After approval, system locks sessions, creates invoice record, renders PDF.

2. **Adjustments / Credit Memo**

    - AR disputes a session or needs discount.
    - Add `invoice_adjustments` line referencing original; totals recalc and new PDF generated.
    - Export adjustments to accounting.

3. **Private Family Billing**
    - When `type=private`, invoice address pulls from guardian contact; payment portal link included.
    - Support partial payments and autopay (future integration).

## Integrations & Dependencies

-   Depends on SSA module for authorized units/rates and on Sessions module (source for invoice lines).
-   Sync module may import sessions from RSM/CAVA; invoices should reference `sessions.source` for traceability.
-   Accounting export: build CSV/JSON integration or QuickBooks API client.

## Metrics

-   AR aging by school.
-   Utilization variance (delivered vs. authorized) per invoice.
-   Write-off and adjustment volume.

## Risks & Open Questions

-   Need decision on tax handling (are services tax-exempt?).
-   Determine e-delivery requirements (EDI vs. email) for large districts.
-   Clarify when to bill: scheduled vs. delivered sessions; plan assumes delivered + approved.
