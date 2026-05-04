NOVA · Admin Session Logs PRD
Version 1.0 · Last Updated: 26 Mar 2026

1. OVERVIEW
   The Admin Session Logs module enables administrators to view, review, approve, edit, send back, and cancel therapy session logs submitted by therapists. It serves as the approval gateway before session logs flow into billing (invoices and therapist bills).

2. OBJECTIVES
   - Provide a centralized view of all session logs across all therapists, students, and schools.
   - Enable approval workflow: submitted → approved (or sent back / cancelled).
   - Support advanced filtering by school, student, therapist, service, SSA, status, and date range.
   - Allow admin edits to session log details before approval.
   - Track documents attached to session logs.

3. PERSONA & ROLE
   Persona: System Admin / Billing Coordinator | Role: Role::ADMIN | Goals: Review submitted session logs for accuracy, approve for billing, send back for corrections, manage session log lifecycle.

4. FUNCTIONAL SCOPE
   4.1 List Session Logs
   Server-side DataTable with:
   - Columns: Session Date, Student/Therapist/Service info, School Invoice Amount, Therapist Billable Amount, Status, Actions
   - Filters: School, Student, Therapist, Service, SSA, Status, Date Range (from/to)
   - POST data endpoint with column whitelist for ordering
   - Status badges color-coded per SessionLogStatus enum

   4.2 View Session Log
   Detail page showing:
   - Session metadata (date, time, duration, location)
   - Student, therapist, SSA, service, school information
   - Billing calculations (school invoice amount, therapist billable amount)
   - Session notes and comments
   - Attached documents with download capability
   - Action buttons based on current status

   4.3 Edit Session Log
   Admin can edit session log fields:
   - Session date, start/end time, duration
   - Service type, billing amounts
   - Notes and observations
   Uses `UpdateSessionLogRequest` for validation.

   4.4 Approve Session Log
   - POST action to transition status to `approved`
   - Makes session log eligible for invoice/bill generation
   - Requires session to be in `submitted` status

   4.5 Send Back Session Log
   - POST action to return session log to therapist for corrections
   - Requires a reason via `SendBackSessionLogRequest`
   - Therapist receives notification to revise and resubmit

   4.6 Cancel Session Log
   - POST action to cancel a session log
   - Removes from billing pipeline
   - Requires confirmation

   4.7 Session Log Import
   - Bulk import session logs via CSV file
   - Import form with file upload and template download
   - Async processing with status tracking
   - Import history list with DataTable (server-side)
   - Detail view per import showing row-level results

5. USER EXPERIENCE GUIDELINES
   - Filter form persists selections across page reloads.
   - Status badges use design system colors (success=approved, warning=submitted, danger=cancelled).
   - Send-back action requires SweetAlert2 confirmation with reason input.
   - Cancel action requires SweetAlert2 confirmation with consequence explanation.
   - Documents section shows thumbnails/icons with download links.

6. DATA MODEL
   Table: session_logs — `id`, `ssa_id`, `student_id`, `therapist_id`, `service_id`, `school_id`, `school_contract_id`, `therapist_contract_id`, `session_date`, `start_time`, `end_time`, `duration_minutes`, `status` (SessionLogStatus enum), `notes`, `location`, `school_invoice_amount`, `therapist_billable_amount`, `sent_back_reason`, `sent_back_at`, `approved_at`, `approved_by`, timestamps, `deleted_at`.
   Table: documents — polymorphic (`documentable_type`, `documentable_id`) linked to session logs.
   Table: session_log_comments — `id`, `session_log_id`, `author_id`, `comment`, timestamps, `deleted_at`.

7. ROUTES (INTERNAL WEB APP)
   - GET /admin/session-logs — list view with filters and DataTable.
   - POST /admin/session-logs/data — server-side DataTable endpoint.
   - GET /admin/session-logs/{sessionLog} — detail/view page.
   - GET /admin/session-logs/{sessionLog}/edit — edit form.
   - PUT /admin/session-logs/{sessionLog} — update action.
   - POST /admin/session-logs/{sessionLog}/approve — approve action.
   - POST /admin/session-logs/{sessionLog}/send-back — send back with reason.
   - POST /admin/session-logs/{sessionLog}/cancel — cancel action.
   - GET /admin/session-logs/import — import form.
   - POST /admin/session-logs/import — process import.
   - GET /admin/session-logs/imports — import history list.
   - POST /admin/session-logs/imports/data — import history DataTable endpoint.
   - GET /admin/session-logs/imports/{import} — import status detail.
   - GET /admin/session-logs/import/template — download CSV template.

8. TECHNICAL IMPLEMENTATION
   Controller: `App\Http\Controllers\Admin\SessionLogController`
   Import Controller: `App\Http\Controllers\Admin\SessionLogImportController`
   Services: `SessionLogService`, `SessionLogIndexService`, `StudentDocumentService`
   Row Transformer: `App\DataTables\Transformers\SessionLogRowTransformer`
   Form Requests: `SessionLogIndexRequest`, `SessionLogDataRequest`, `UpdateSessionLogRequest`, `SendBackSessionLogRequest`

9. INTEGRATION POINTS
   - Approved session logs feed into Invoice generation (school billing).
   - Approved session logs feed into Therapist Bill generation (AP billing).
   - Send-back triggers email notification to therapist.
   - Documents stored via StorageServiceInterface (S3/local).

10. OPEN QUESTIONS & RISKS
    - Bulk approve capability for multiple session logs at once (future enhancement).
    - Audit trail for status transitions (currently tracked via `approved_at`, `sent_back_at`; consider full activity log).
    - Session log import validation rules may need updates as new service types are added.
