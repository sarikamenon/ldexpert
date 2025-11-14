NOVA · Schools Module PRD
Version 1.0 · Last Updated: 14 Nov 2025

1. OVERVIEW
   The Schools module enables administrators in NOVA to manage every school account from onboarding through operations. It stores authoritative school information, contacts, geography, financial context, and service rate configurations that downstream modules (SSA, invoicing, therapist assignment) consume.

2. OBJECTIVES
   • Provide a consistent workflow to create, edit, and monitor schools.
   • Capture contacts, characteristics, and service rate overrides needed for contracting and billing.
   • Supply list views and exports with actionable metrics for administrators.
   • Enforce validation, authorization, and accessibility rules aligned with NOVA standards.

3. PERSONA & ROLE
   Persona: System Admin | Role: Role::ADMIN | Goals: Onboard schools, maintain records, control activation states.

4. FUNCTIONAL SCOPE
   4.1 Create School
       Form Group A – School Information (required)
         - Full School Name (text, required)
         - NOVA School Name (display text, required)
         - Address (textarea)
         - State (dropdown, US states, required)
         - Time Zone (dropdown, required)
         - Manager (dropdown of admin users, required)
       Form Group B – Contact Information
         - School Contact First Name (text)
         - School Contact Last Name (text)
         - Phone Number (text, mask XXX-XXX-XXXX)
         - Email Address (email, optional)
         - School Invoice Email (email, optional; if empty, invoicing skips auto-email)
       Form Group C – School Characteristics
         - School Type (dropdown: Virtual, Brick Mortar, Blended, required)
         - Is Private Student? (checkbox)
         - Non Billable Scheduling? (checkbox)
         - External EMR School Name (text)
       Form Group D – Service Rates Configuration (repeat per service)
         - Service (select from services catalog)
         - Rate (decimal 0.00)
         - Rate Type (radio: H hourly, F flat)
       Actions
         - Create School (primary) validates and persists all data, triggers onboarding workflow.
         - Reset (secondary) clears form after confirmation prompt.

   4.2 List Schools
       Summary Metrics: Schools Total | Active | Deactivated.
       Controls: Show Deactivated checkbox; Search input with real-time filtering; Export Schools button for filtered dataset; Add School button linking to creation flow.
       Table Columns: ID, Name, Manager, Contact Person, State, Email, Timezone, Add Student action, Status toggle. Sorting available on all columns except Email and Add Student.
       Pagination: Previous/Next navigation, numbered pages, and range text “Showing X to Y of Z entries”.

   4.3 Edit School
       - Same sections/fields as Create, pre-populated with existing data.
       - Update School Info button replaces Create School button.
       - No Reset button to avoid accidental data loss.
       - Service rates grid editable; add/remove rows with same validation as creation.

   4.4 Service Rates Management
       - Inline table for service overrides seeded from global services catalog.
       - Each row must remain unique per school/service combination.
       - Rate updates captured via audit log; SSA and invoicing engines snapshot rates at time of use.

   4.5 Status & Lifecycle Actions
       - Activate/Deactivate buttons require confirmation dialog with reason.
       - Deactivating prevents new SSA associations but retains historical data.

5. USER EXPERIENCE GUIDELINES
   • Required fields marked with * and validated inline on blur.
   • Dropdowns sourced from canonical enumerations (US states constant, timezone list, managers query).
   • Loading overlay displayed during save/delete actions.
   • Destructive actions (Deactivate, delete rate rows, Reset) show confirmations.
   • Table supports keyboard navigation, sortable headers, and visible focus states.

6. DATA MODEL
   Table: schools (new) – id, full_name, display_name, address, state, timezone, manager_id, school_type, is_private_student, non_billable_scheduling, external_emr_name, contact_first_name, contact_last_name, contact_phone, contact_email, invoice_email, status, timestamps, deleted_at.
   Table: school_service_rates (new) – id, school_id, service_id, rate, rate_type, effective_on, expires_on, timestamps, deleted_at.
   Table: users – manager_id foreign key reference.
   Table: student_profiles – future school_id foreign key reference.

7. ROUTES (INTERNAL WEB APP)
   • GET /admin/schools – list view with metrics and filters.
   • GET /admin/schools/create, POST /admin/schools – creation flow.
   • GET /admin/schools/{school} – detail view (summary, rates, activity log).
   • GET /admin/schools/{school}/edit, PUT|PATCH /admin/schools/{school} – edit flow.
   • PATCH /admin/schools/{school}/status – activation state change payload status+reason.
   • POST /admin/schools/{school}/rates, PATCH /admin/schools/{school}/rates/{rate} – manage overrides.
   • GET /admin/schools/export – download filtered dataset.

8. VALIDATION RULES
   • Required fields enforced server-side and client-side.
   • Phone numbers validated with regex ^\d{3}-\d{3}-\d{4}$.
   • Emails validated via Laravel email:rfc,dns; invoice email optional but must pass validation when provided.
   • Service rate rows require numeric rate ≥ 0 and rate type ∈ {H, F}.
   • Unique constraints: display_name, school_type enumeration, school_id + service_id combination.

9. SECURITY & PERMISSIONS
   • Routes guarded by auth + role:admin middleware.
   • Policies ensure only authorized admins can create/update/deactivate.
   • Audit trail stored for all write operations (actor id, timestamp, payload summary).

10. ACCESSIBILITY REQUIREMENTS
    • Forms must be keyboard accessible with ARIA labels.
    • Error text linked via aria-describedby.
    • Table headers use <th scope="col">; focus states visible for all actionable controls.

11. FEEDBACK & MESSAGING
    • Success toast/snackbar: “School added successfully.” (create) and “School information updated successfully.” (update).
    • Inline error messaging near fields.
    • Loading spinners during save operations.
    • Confirmation dialogs for Activate, Deactivate, and Reset actions.

12. NON-FUNCTIONAL REQUIREMENTS
    • Performance: list view paginated; aim for <500 ms response for common filters.
    • Reliability: wrap writes in transactions so school + rates persist atomically.
    • Logging: capture failures with context for debugging.

13. DEPENDENCIES & INTEGRATIONS
    • Services module supplies service catalog for rate overrides.
    • SSA module references schools.id and enforces active status before creating agreements.
    • Invoicing module uses invoice_email; if empty, invoices generate without automatic email (per client decision to keep field optional).
    • Sync module maps external EMR identifiers.

14. METRICS & REPORTING
    • Count of active vs. deactivated schools (displayed in summary metrics).
    • Rate override coverage per service.
    • Average time from creation to first SSA association.

15. RISKS & OPEN QUESTIONS
    • Final design for multiple contacts pending (current scope assumes single primary contact + optional invoice email).
    • Determine whether managers need notifications when schools change state.
    • Clarify import strategy for historical school data prior to go-live.

16. VERSION 2 BACKLOG (FUTURE ENHANCEMENTS)
    • Multiple Contacts – support repeating contact blocks (billing, operations, escalation) with type-specific validation.
    • Audit & Versioning – capture changes with effective dates, actor attribution, and provide a history tab; consider dedicated school_events table.
    • Manager Assignment Workflow – deliver reassignment tooling, notifications, and an “unassigned” state for transitions.
    • Rate Defaults & Templates – allow cloning from reusable rate templates so large districts can seed pricing quickly.
    • Advanced Filters – add manager, state, school type, and private vs. district filters to the list view beyond search/deactivated toggle.
