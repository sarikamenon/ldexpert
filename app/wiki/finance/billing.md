# Bill Therapists (Provider Payables) PRD

## Purpose
Calculate and disburse payments owed to therapists based on delivered sessions, contract rates, and adjustments. Provide visibility into payable balances, automate approvals, and integrate with payroll/AP systems.

## Personas
- **Accounts Payable (AP)** — reviews payables, approves payouts, exports to payroll.
- **Therapists** — view accrued pay, submitted sessions, and payment history.
- **Program Admin** — verifies that therapist payouts align with SSA assignments and attendance.

## Current Implementation

### Core Features ✅
- **Bill Generation**: Admins can create therapist bills from approved session logs, grouped by therapist and billing period.
- **Session Log Integration**: Bills are generated from approved `session_logs` that contain `therapist_billable_amount` calculated from therapist contracts.
- **PDF Generation**: System generates PDF bills with company and therapist snapshot data.
- **Email Delivery**: Admins can send bills via email with PDF attachment to therapist's email address.
- **Status Workflow**: Bills support three statuses: `draft`, `sent`, `paid`.
- **Snapshot Data**: System captures therapist and company information at bill creation time for historical accuracy.
- **Therapist Portal Access**: Therapists can view their own bills (read-only) via `/therapist/billing`.

### Database Schema ✅
- **`therapist_bills` table**: Stores bill records with therapist snapshot (name, email, phone, address) and company snapshot (name, address, phone, email, tax ID).
- **`session_logs.therapist_bill_id`**: Foreign key linking session logs to bills.
- **Soft Deletes**: All bills support soft deletion.

### Routes & Controllers ✅

**Admin Routes:**
- `GET /admin/billing/therapist-bills` - List bills with filters (status, therapist, date range)
- `GET /admin/billing/therapist-bills/create` - Create bill form with available session logs
- `POST /admin/billing/therapist-bills` - Create draft bill from selected session logs
- `GET /admin/billing/therapist-bills/{bill}` - View bill details with session log lines
- `GET /admin/billing/therapist-bills/{bill}/download` - Download PDF bill
- `POST /admin/billing/therapist-bills/{bill}/send` - Send bill via email

**Therapist Routes:**
- `GET /therapist/billing` - List therapist's own bills
- `GET /therapist/billing/{bill}` - View bill details (read-only)
- `GET /therapist/billing/{bill}/download` - Download PDF bill

### Business Rules ✅
- Only approved session logs can be included in bills.
- All session logs in a bill must belong to the same therapist.
- Bill totals are calculated from `therapist_billable_amount` on session logs.
- Therapist and company information is snapshotted at bill creation time.
- Bill numbers are auto-generated if not provided.
- Therapists can only view their own bills (enforced via policy).

## Planned Scope (Future Enhancements)
- Per-therapist adjustments (bonuses, deductions) with reason tracking.
- Payment recording and tracking.
- Export to payroll systems.
- Dispute handling workflow.
- Therapist payment acknowledgment.

## Domain Model

### Tables (Implemented ✅)
| Table | Fields | Status |
| --- | --- | --- |
| `session_logs` | `id`, `therapist_id`, `student_id`, `ssa_id`, `service_id`, `session_date`, `duration_minutes`, `therapist_billable_amount`, `therapist_bill_id`, `therapist_rate_type`, `therapist_rate_amount`, `status` (draft, submitted, approved), timestamps. | ✅ Implemented |
| `therapist_bills` | `id`, `therapist_id`, `bill_number`, `bill_date`, `billing_period_start`, `billing_period_end`, `status` (draft, sent, paid), `subtotal`, `adjustments_total`, `total_due`, `due_date`, therapist snapshot fields, company snapshot fields, `sent_at`, `sent_by_id`, `notes`, timestamps, `deleted_at`. | ✅ Implemented |

### Tables (Planned 🔄)
| Table | Fields | Status |
| --- | --- | --- |
| `therapist_bill_lines` | Separate line items table (currently using direct `session_logs` relationship). | 🔄 Future |
| `therapist_adjustments` | Manual debit/credit with reason (travel, penalty, bonus). | 🔄 Future |
| `therapist_payments` | `id`, `therapist_id`, `bill_id`, `paid_on`, `amount`, `method`, `reference`, `status` (initiated, sent, failed, completed). | 🔄 Future |

### Rules (Implemented ✅)
- ✅ Session logs must be approved before inclusion in bills.
- ✅ Provider rate priority: Therapist contract → `therapist_service_rates` → `services.default_provider_rate`.
- ✅ All session logs in a bill must belong to the same therapist.
- ✅ Bill totals calculated from `therapist_billable_amount` on session logs.
- ✅ Therapist and company information snapshotted at bill creation.

### Rules (Planned 🔄)
- Negative adjustments require approver comment; system logs user + timestamp.
- Export batch tracking for payroll integration.

## API & Routes

### Admin Web (Implemented ✅)
- ✅ `GET /admin/billing/therapist-bills` - List bills with filters (status, therapist, date range, search).
- ✅ `GET /admin/billing/therapist-bills/create` - Create bill form showing available approved session logs.
- ✅ `POST /admin/billing/therapist-bills` - Create draft bill from selected session logs.
- ✅ `GET /admin/billing/therapist-bills/{bill}` - View bill details with session log lines.
- ✅ `GET /admin/billing/therapist-bills/{bill}/download` - Download PDF bill.
- ✅ `POST /admin/billing/therapist-bills/{bill}/send` - Send bill via email with PDF attachment.

### Admin Web (Planned 🔄)
- 🔄 `POST /admin/billing/{bill}/approve` - Locks bill, notifies AP.
- 🔄 `POST /admin/billing/{bill}/export` - Marks as sent to payroll (stores export artifact).
- 🔄 `POST /admin/billing/{bill}/record-payment` - Capture payment info.

### Therapist Portal (Implemented ✅)
- ✅ `GET /therapist/billing` - List therapist's bills with status + amounts.
- ✅ `GET /therapist/billing/{bill}` - View bill details with session log lines (read-only).
- ✅ `GET /therapist/billing/{bill}/download` - Download PDF bill.

### Therapist Portal (Planned 🔄)
- 🔄 `POST /therapist/billing/{bill}/disputes` - Start dispute referencing session(s).

### API v1 (Planned 🔄)
- 🔄 `GET /api/v1/therapist-bills` - For payroll integrations.
- 🔄 `POST /api/v1/therapist-bills/{id}/payment` - Inbound webhook from payroll provider updating status.

## Workflows

### Implemented ✅

1. **Bill Generation Workflow**
   1. Admin navigates to bill creation page.
   2. System displays available approved session logs (filtered by therapist, date range).
   3. Admin selects session logs and provides billing period dates.
   4. System validates all session logs belong to same therapist.
   5. System calculates totals from `therapist_billable_amount` on session logs.
   6. System creates draft bill with therapist and company snapshots.
   7. System links selected session logs to bill.

2. **Bill Sending Workflow**
   1. Admin views draft bill.
   2. Admin clicks "Send Bill" button.
   3. System generates PDF bill.
   4. System sends email to therapist's email address with PDF attachment.
   5. System marks bill as `sent` and records `sent_at` timestamp.

3. **Therapist Bill Viewing Workflow**
   1. Therapist navigates to `/therapist/billing`.
   2. System displays list of therapist's bills (filtered automatically).
   3. Therapist can view bill details and download PDF.

### Planned 🔄

1. **Cycle Generation**
   - Automated scheduler to aggregate approved sessions per therapist for the period (weekly/biweekly).
   - System applies rates, creates draft bills, and notifies AP + therapists (read-only until approved).
   - AP reviews adjustments, approves, and exports.

2. **Dispute Handling**
   - Therapist files dispute citing session(s).
   - Admin reviews, optionally adjusts session/bill; dispute status tracked per line.

3. **Payment Recording**
   - After payroll run, import payment file to mark bills as paid.
   - Provide download of pay stub (PDF) for therapists.

## Integrations & Dependencies
- Shares `sessions` data with invoicing; ensure no double counting when session spans multiple services.
- Requires `therapist_service_rates` from therapist management module.
- Payment integration (ACH provider, payroll) needs secure credential storage and webhook endpoints.

## Metrics
- Outstanding AP balance by therapist.
- Average days from service delivery to payment.
- Volume of disputes per period.

## Risks & Open Questions
- Need clarity on contractor vs. employee classification (affects tax forms, deductions).
- Determine if therapists can split rates by school or SSA (if so, extend rate table).
- Understand regulatory requirements for pay stub content per state.
