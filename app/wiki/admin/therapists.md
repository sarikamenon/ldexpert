NOVA · Therapist Module PRD
Version 1.0 · Last Updated: 14 Nov 2025

1. OVERVIEW
   The Therapist module enables NOVA administrators/managers to manage therapist profiles end-to-end, covering personal data, employment type, position, residency, contact methods, internal notes, and service-specific pay rates. It supports recruiting, onboarding, and ongoing maintenance workflows.

2. OBJECTIVES
   • Provide a guided flow to capture all required therapist fields with validation.
   • Offer visibility into active vs. deactivated therapists, including manager assignments and employment types.
   • Maintain per-service rate overrides used by downstream SSA, scheduling, invoicing, and billing processes.
   • Enforce secure, accessible experiences consistent with NOVA design standards.

3. PERSONA & ROLE
   Persona: System Admin / Therapist Manager | Role: Role::ADMIN | Goals: Add therapists, maintain accurate records, manage activation status, configure service rates.

4. FUNCTIONAL SCOPE
   4.1 Create Therapist (Add New Therapist)
   Form Section A – Employment & Identity - Employee Type* (radio: W2, 1099) - Title* (dropdown: Select Title, Mr., Mrs., Ms., Dr., etc.) - First Name* (text) - Last Name* (text)
   Form Section B – Contact & Account Details - Personal Email* (unique in therapist_profiles) - Phone* (digits/dashes regex) - LD Expert Email (optional) - Address (optional) - Comment (optional internal notes) - Default Meeting Location (optional URL/text)
   Form Section C – Professional Details - Position* (dropdown: SLP, OT, PT, LCSW, SW, etc.) - State Residing* (dropdown, US states) - Timezone* (dropdown, US timezones) - Therapist Manager* (admin user) - Max Weekly Hours\* (integer 1–168) - Date of Birth (optional, before today, after 1900-01-01)
   Form Section D – Service Rates Configuration (repeat per service) - Service (select from services catalog) - Rate (decimal 0.00) - Rate Type (radio: H hourly, F flat)
   Actions - Create Therapist (primary submit) - Cancel (secondary, returns to list)

    4.2 List Therapists
    Summary Metrics displayed above grid (dynamic counts, e.g., Total 2,671 | Active 378 | Deactivated 2,293).
    Controls: Show Deactivated checkbox; Search input with live filtering; Export Therapists button (filtered dataset); Add Therapist button opening creation flow.
    Table Columns and Behavior:
    #ID (sortable) – unique therapist ID (e.g., 132)
    Name (sortable, link) – full name linking to edit
    Email (sortable) – personal email
    Therapist Manager (sortable) – assigned manager name
    Phone (sortable) – contact number
    Position (sortable) – role such as SLP, OT
    Type (sortable) – employment type (1099/W2)
    Status (sortable) – Active (green badge) or Deactivated (gray badge)
    Pagination: Previous/Next buttons, numbered links, range label “Showing X to Y of Z entries”.

    4.3 Edit Therapist - Same fields as Create, pre-populated from existing record. - Update Therapist Info button saves edits; Cancel returns to list. - No Reset button to avoid accidental data loss. - Service rate rows editable with same validation; allow add/remove per service.

    4.4 Service Rates Management - Inline repeatable rows scoped to therapist + service combinations (enforce uniqueness). - Default rates seeded from services catalog; admins may override per therapist. - Changes logged for auditing; downstream modules reference the latest effective rate.

    4.5 Status & Lifecycle Actions - Activate/Deactivate controls (buttons or toggles) with confirmation dialog and optional comment. - Deactivation hides therapist from assignment pickers while retaining historical data.

5. USER EXPERIENCE GUIDELINES
   • Required fields labeled with \* and validated inline.
   • Dropdowns display comprehensive, up-to-date options (states, timezones, titles, managers, positions).
   • Loading overlays shown during save/delete operations.
   • Destructive actions (Deactivate) require confirmation dialogs.
   • Tables and forms support full keyboard navigation and visible focus states.

6. DATA MODEL
   Table: users – `role=therapist`, `status`, authentication basics, timestamps, soft deletes.
   Table: therapist_profiles – `user_id`, `employee_type`, `title`, `first_name`, `last_name`, `personal_email` (unique per profile), `phone`, `ld_email`, `address`, `comments`, `position`, `state`, `timezone`, `manager_id` (admin), `max_weekly_hours`, `dob`, `default_meeting_location`, timestamps, `deleted_at`.
   Table: therapist_service_rates – `id`, `therapist_id`, `service_id`, `rate`, `rate_type`, `effective_on`, `expires_on`, timestamps, `deleted_at`.

7. ROUTES (INTERNAL WEB APP)
   • GET /admin/therapists – list and metrics.
   • GET /admin/therapists/create, POST /admin/therapists – creation flow.
   • GET /admin/therapists/{therapist} – detail/edit view.
   • PUT|PATCH /admin/therapists/{therapist} – update handler.
   • PATCH /admin/therapists/{therapist}/status – activate/deactivate actions.
   • POST /admin/therapists/{therapist}/rates, PATCH /admin/therapists/{therapist}/rates/{rate} – service rate management.
   • GET /admin/therapists/export – export filtered results.

8. VALIDATION RULES
   • Required: employee_type, title, first_name, last_name, personal_email (unique per profile), phone (digits/dashes), position, state, timezone, manager_id (admin), max_weekly_hours (1–168).
   • Optional: ld_email, address, comments, dob (date, before today, after 1900), default_meeting_location.
   • Service rate rows require numeric rate ≥ 0 and rate type ∈ {H, F}; enforce unique therapist+service pair.

9. SECURITY & PERMISSIONS
   • Routes protected by `auth` middleware plus `role:admin` gate.
   • Authorization policies ensure only privileged admins can create/update/deactivate therapists or modify rates.
   • Audit logging captures actor, timestamp, and summary of changes.

10. ACCESSIBILITY REQUIREMENTS
    • Forms and tables support keyboard navigation, ARIA labels, and descriptive error messaging via `aria-describedby`.
    • Focus states clearly visible on radio buttons, dropdowns, and table actions.

11. FEEDBACK & MESSAGING
    • Success toasts/snackbars: “Therapist added successfully.” and “Therapist information updated successfully.”
    • On successful creation, system sends the standard welcome email (reuse existing student workflow) with credentials/instructions for the therapist.
    • Inline error messages near offending fields.
    • Loading indicators during save operations.
    • Confirmation dialogs for Activate/Deactivate actions and Cancel (if unsaved changes exist).

12. NON-FUNCTIONAL REQUIREMENTS
    • Performance: list view paginated; aim for <500 ms response for common searches.
    • Reliability: wrap writes in transactions to ensure therapist profile + rates persist atomically.
    • Logging: capture failures with sufficient context for debugging (inputs, user ID).

13. DEPENDENCIES & INTEGRATIONS
    • Services catalog supplies list of service types and default provider rates.
    • SSA scheduling references therapist employment type, manager, and availability; only active therapists should appear.
    • Billing module consumes therapist_service_rates for payout calculations.

14. METRICS & REPORTING
    • Count of total vs. active vs. deactivated therapists (surface via summary metrics).
    • Distribution by position (SLP/OT/PT/etc.) and employment type (W2 vs. 1099).
    • Manager caseload counts for internal capacity planning.

15. RISKS & OPEN QUESTIONS
    • Need long-term plan for storing sensitive documents (e.g., licenses) outside MVP scope.
    • Determine if DOB collection introduces additional compliance requirements (PII handling, encryption at rest).
    • Confirm whether therapists require notifications when managers change assignments.

16. VERSION 2 BACKLOG (FUTURE ENHANCEMENTS)
    • Credential Document Management – upload, verify, and track expirations with alerts.
    • Availability Scheduling – calendar-based availability templates feeding assignment engine.
    • Onboarding Workflow States – transitions such as prospect, invited, onboarding, approved, suspended, terminated with required tasks at each stage.
    • Self-Service Portal – allow therapists to view/update select profile fields subject to admin approval.
    • Advanced Filters – add position, state, employment type, and manager filters beyond the basic search/deactivated checkbox.
