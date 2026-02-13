# Session Management & Logs PRD

## Purpose

Enable therapists to manage and log all therapy sessions (scheduled or standalone) with comprehensive documentation, notes, and billing metadata. This module provides complete session lifecycle management from creation through approval to billing. Session logs serve as the billable entity that drives both therapist payouts (via therapist contracts) and school invoicing (via school contracts). All sessions must be linked to an SSA and include dual billing calculations for both sides.

## Personas

-   **Therapist (primary)** — creates, edits, and submits session logs with notes and billing details.
-   **Accounts Payable (AP)** — reviews submitted sessions for therapist billing.
-   **Accounts Receivable (AR)** — uses session logs to generate school invoices.
-   **Program Admin** — reviews and approves session logs before billing cycles.

## Current Implementation

-   Session log tables and workflows implemented. Therapists can create session logs from schedules or as standalone entries.
-   All session logs require an SSA link (from schedule or manual selection).
-   Dual billing calculation: therapist rates (from therapist contracts) and school rates (from school contracts).
-   Session status workflow: draft → submitted → approved; admin can send back submitted logs for rectification (sent_back → therapist edits and resubmits) (implemented).
-   Document attachments for session logs (implemented via Student Documents module with polymorphic relationship).
-   Integration with billing module (therapist bills) and invoicing module (school invoices).

## Planned Scope

-   Enhanced document management with versioning and templates.
-   Document preview without download.
-   Advanced search within session notes and documents.

## Domain Model

### Tables

| Table          | Fields                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| -------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `session_logs` | … (as above) …, `status` (draft/submitted/sent_back/approved/cancelled), `sent_back_at` (nullable), `sent_back_by_id` (nullable), … |
| `session_log_comments` | `id`, `session_log_id`, `author_id` (nullable), `comment` (text), `type` (e.g. sent_back, therapist_reply), timestamps, `deleted_at` |

### Relationships

-   `session_logs.therapist_id` → `users.id` (therapist role)
-   `session_logs.student_id` → `users.id` (student role)
-   `session_logs.ssa_id` → `service_support_agreements.id` (required)
-   `session_logs.schedule_id` → `schedules.id` (optional, if logging against schedule)
-   `session_logs.service_id` → `services.id`
-   `session_logs.school_id` → `schools.id` (nullable, auto-set from student/schedule/SSA)
-   `session_logs.therapist_contract_id` → `therapist_contracts.id` (nullable)
-   `session_logs.school_contract_id` → `school_contracts.id` (nullable)
-   `session_logs.therapist_bill_id` → `therapist_bills.id` (nullable, set when bill created)
-   `session_logs.invoice_id` → `invoices.id` (nullable, set when invoice created)
-   `session_logs.submitted_by_id` → `users.id` (nullable)
-   `session_logs.approved_by_id` → `users.id` (nullable)
-   `session_logs.sent_back_by_id` → `users.id` (nullable, set when admin sends back)
-   `session_log_comments.session_log_id` → `session_logs.id`; `session_log_comments.author_id` → `users.id` (nullable)
-   Polymorphic: `student_documents` → `documentable_type` = 'App\Models\SessionLog', `documentable_id` (documents attached to session logs)

### Enums

-   `SessionLogStatus`: `DRAFT`, `SUBMITTED`, `SENT_BACK`, `APPROVED`, `CANCELLED`
-   `SessionOutcome`: `SERVICES_ADMINISTERED`, `NO_SHOW`, `BILLABLE_CANCELLATION`, `NON_BILLABLE_CANCELLATION_CLIENT`, `NON_BILLABLE_CANCELLATION_PROVIDER`

### Rules

-   `ssa_id` is always required (from schedule or manual selection for standalone).
-   `delivery_mode` is always 'virtual' (not user-editable).
-   `is_group` is always false (not user-editable).
-   When `schedule_id` is provided, auto-populate: `student_id`, `service_id`, `ssa_id`, `school_id`, `start_time`, `end_time`, `tho_minutes` from SSA.
-   When standalone, require: `student_id`, `service_id`, `ssa_id` (dropdown of student's active SSAs), `session_date`, `start_time`, `end_time`.
-   `duration_minutes` auto-calculated from `start_time` and `end_time` (rounded to nearest 5 minutes).
-   `tho_minutes` auto-populated from SSA when `ssa_id` is set.
-   Billing calculation:
    -   Therapist side: lookup `TherapistContractService` for `therapist_id` + `service_id` + `session_date`, get `rate`, `rate_type`, `no_show_rate`, and `no_show_rate_type`.
    -   School side: lookup `SchoolContractService` for `school_id` + `service_id` + `session_date` (from SSA or student's school), get `rate`, `rate_type`, `no_show_rate`, and `no_show_rate_type`.
    -   **No-show rate logic (school students only):** When the student's school has `is_private_student = false` (i.e., a regular school, not a private-student placeholder) and the `outcome` is `NO_SHOW` or `BILLABLE_CANCELLATION`, both therapist and school sides use the contract's `no_show_rate` and `no_show_rate_type` instead of the regular rate. No-show rates must be configured on both therapist and school contracts for these outcomes.
    -   **Private students:** When the student's school has `is_private_student = true`, the regular rate is always used regardless of outcome. No-show rate logic does **not** apply to private students.
    -   Amount calculation: `hourly = rate * (duration_minutes / 60)`, `flat = rate`.
-   If `is_rate_override = true`, require `override_reason` (min 20 chars).
-   `notes` field required with minimum 50 characters.
-   Status transitions: `draft` → `submitted` (locks most edits) → `approved` (locks all edits).
-   Only therapist who created the log can edit until submitted; admins can approve.

### Session Outcome & No-Show Rate (School Students Only)

| Outcome | Billable Therapist | Billable School | Rate Used (School Students) | Rate Used (Private Students) |
| ------- | ------------------ | --------------- | --------------------------- | ---------------------------- |
| Services Administered | Yes | Yes | Regular rate | Regular rate |
| No Show | Yes | Yes | **No-show rate** | Regular rate |
| Billable Cancellation | Yes | Yes | **No-show rate** | Regular rate |
| Non-Billable Cancellation - Client | No | No | N/A | N/A |
| Non-Billable Cancellation - Provider | No | No | N/A | N/A |

-   **No-show rate applies only to school students** (schools where `is_private_student = false`). Private-student schools (`is_private_student = true`) always use the regular rate; no-show rate is never used.
-   No-show rates must be configured in both therapist contracts and school contracts (Admin → Contracts) for NO_SHOW and BILLABLE_CANCELLATION outcomes; otherwise validation fails with a clear error message.

## UI / Routes

### Routes

```
GET    /therapist/session-logs                    (list with filters)
GET    /therapist/session-logs/select-ssa        (select SSA for standalone session)
GET    /therapist/session-logs/create             (form: schedule selection or standalone)
GET    /therapist/session-logs/create/schedule/{id}  (pre-fill from schedule)
POST   /therapist/session-logs                   (store)
GET    /therapist/session-logs/{id}             (view)
GET    /therapist/session-logs/{id}/edit         (edit draft only)
PUT    /therapist/session-logs/{id}             (update draft only)
POST   /therapist/session-logs/{id}/submit      (submit, locks edits)
POST   /therapist/session-logs/{id}/cancel      (cancel with reason)
POST   /therapist/session-logs/{sessionLog}/documents  (upload document)
GET    /therapist/session-logs/{sessionLog}/documents/{document}/download  (download document)
DELETE /therapist/session-logs/{sessionLog}/documents/{document}  (delete document)
GET    /admin/session-logs                       (admin view, all therapists)
GET    /admin/session-logs/{id}                  (admin view detail)
GET    /admin/session-logs/{id}/edit             (admin edit)
PUT    /admin/session-logs/{id}                  (admin update)
POST   /admin/session-logs/{id}/approve         (approve, locks all edits)
POST   /admin/session-logs/{id}/send-back       (send back to therapist with comment; therapist notified by email)
POST   /admin/session-logs/{id}/cancel           (admin cancel)
```

Controllers: `App\Http\Controllers\Therapist\SessionLogController`, `App\Http\Controllers\Therapist\SessionLogDocumentController`, `App\Http\Controllers\Admin\SessionLogController`

## Workflows

### 1. Log Session from Schedule

1. Therapist views schedule calendar or schedule detail.
2. Clicks "Log Session" button on completed schedule.
3. Form pre-fills: `student_id`, `service_id`, `ssa_id`, `school_id`, `start_time`, `end_time`, `tho_minutes`.
4. Therapist selects `outcome` (Services Administered, No Show, Billable Cancellation, or non-billable cancellations).
5. Therapist adjusts actual `start_time`/`end_time` if different from scheduled.
6. Therapist enters `notes` (required, min 50 chars).
7. System auto-calculates billing amounts based on outcome and school type (school vs private student); see no-show rate logic above.
8. Therapist reviews billing, can override with reason if needed.
9. Therapist saves as draft or submits immediately.
10. On submit, status changes to `submitted`, most fields lock.

### 2. Log Standalone Session

1. Therapist navigates to "My Sessions" → "Create Session".
2. Selects `student_id` (dropdown filtered to therapist's assigned students).
3. Selects `ssa_id` (dropdown filtered to student's active SSAs).
4. System auto-populates `service_id` from SSA's primary service.
5. Therapist enters `session_date`, `start_time`, `end_time`.
6. Therapist selects `outcome` (Services Administered, No Show, Billable Cancellation, or non-billable cancellations).
7. System auto-calculates `duration_minutes` and `tho_minutes` from SSA.
8. Therapist enters `notes` (required).
9. System auto-calculates billing amounts based on outcome and school type.
10. Therapist reviews, can override, saves or submits.

## Visibility & access control

-   Therapists only see **their own** session logs in all therapist-facing views (indexes, student detail tabs, SSA detail tabs, schedule cards).
-   When accessing session logs via therapist routes (for example, `/therapist/session-logs/{id}`), route model binding restricts resolution to logs where `therapist_id` matches the logged-in therapist; any other ID returns **404 Not Found**.
-   SSA detail routes under the therapist area only resolve SSAs assigned to the logged-in therapist (`assigned_therapist_id`); unassigned or other therapists' SSAs also return **404 Not Found** for therapist routes.

### 3. Document Management

1. Therapist can upload documents to session log (from session log detail page).
2. Document types: Progress Report, IEP, Consent Form, Assessment, Other.
3. Documents stored on S3 with metadata (type, size, uploader, description).
4. Documents visible to admins and therapists assigned to student.
5. Therapists can delete only documents they uploaded; admins can delete any document.
6. See [Student Documents PRD](../admin/student-documents.md) for complete documentation.

### 4. Billing Integration

1. When session log is `approved`, it becomes available for billing cycles.
2. Therapist billing: AP runs billing process, creates `therapist_bill`, links session logs via `therapist_bill_id`.
3. School invoicing: AR runs invoicing process, creates `invoice`, links session logs via `invoice_id`.
4. Both processes reference the stored `therapist_billable_amount` and `school_invoice_amount`.

## Authorization & Security

-   Use `SessionLogPolicy` to restrict access:
    -   Therapists can only view/edit their own session logs.
    -   Admins can view all and approve any session log.
-   All routes protected by `auth` + `role:therapist` or `role:admin` middleware.
-   CSRF protection on all POST/PUT routes.
-   Audit log: track status transitions with `submitted_by_id`, `approved_by_id`, timestamps.

## Dependencies

-   Requires SSA module (for `ssa_id` requirement).
-   Requires Schedule module (for optional schedule linkage and recurring schedule support).
-   Requires Contracts module (therapist and school contracts for rate lookup).
-   Integrates with Billing module (therapist bills) and Invoicing module (school invoices).
-   Uses Student Documents module for document attachments (polymorphic relationship).
-   File storage: S3 in production, local in testing.

## Metrics

-   Sessions logged per therapist per month.
-   Average time from session delivery to log submission.
-   Percentage of sessions submitted within 24 hours.
-   Billing accuracy (rate overrides per session).
-   Sessions approved and ready for billing cycles.

## Risks & Open Questions

-   How to handle sessions where student has multiple active SSAs? (Resolved: therapist selects SSA at log creation.)
-   Should there be a time limit for editing submitted sessions? (Future: consider auto-approval after billing cycle.)
-   How to handle rate changes mid-contract? (Resolved: use contract effective dates for rate lookup.)
