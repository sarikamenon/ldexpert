NOVA · Service Support Agreement (SSA) Module PRD
Version 1.0 · Last Updated: 14 Nov 2025

1. OVERVIEW
   The SSA module enables NOVA administrators to capture and manage each student’s Service Support Agreement so therapists and coordinators know exactly which services, frequencies, and time commitments are authorized. It centralizes the lifecycle from creation through completion, ensuring delivery stays aligned with student needs and contractual obligations.

2. OBJECTIVES
   • Provide a guided flow to create SSA records linking students, services, durations, and therapists.
   • Offer searchable list views with status metrics for pending, active, and completed SSAs.
   • Track session cadence (frequency, minutes, total hours authorized) and assigned therapists.
   • Maintain strict validation, security, and accessibility for sensitive student/therapy data.

3. PERSONA & ROLE
   Persona: System Admin / Program Manager | Role: Role::ADMIN | Goals: Define SSA details, monitor statuses, reassign therapists, and ensure services match student requirements.

4. FUNCTIONAL SCOPE
   4.1 Create SSA (Add New SSA)
   Form Section A – Core Details - Student* (active students) - Primary Service* (active services) - Additional Services (optional multi-select; indirect services only) - Start Date* (date picker) - End Date* (date picker)
   Form Section B – Scheduling Parameters - Minutes per Session* (int 5–1440) - Frequency (nullable enum; required when primary service is frequency-based) - Sessions per Frequency (required when frequency applies) - THO Minutes* (auto-calculated, editable override) - Calculated Minutes (optional stored value) - Adjusted Minutes/Notes (optional override + reason)
   Form Section C – Assignment - Assigned Therapist (optional at creation; required before activation)
   Actions - Create SSA (primary submit) - Cancel (secondary, returns to SSA list)

    4.2 List SSA
    Summary Metrics displayed above grid (e.g., Total 3,000 | Pending 378 | Active 329 | Completed 2,293).
    Controls: Show Completed checkbox; Search input with live filtering; Export SSA button (filtered dataset); Add SSA button (create flow).
    Table Columns (sortable unless stated):
    #ID – SSA identifier (clickable to open edit)
    Student – linked student name
    Primary Service – service name
    Therapist – assigned therapist name
    Start Date
    End Date
    Minutes per Session
    Frequency – displays frequency + sessions per frequency (e.g., Weekly · 2)
    THO Minutes – total authorized minutes
    Served Minutes – delivered minutes to date (read-only metric)
    Status – badge indicator (Pending, Active, Completed, Deactivated)
    Pagination: Previous/Next buttons, numbered page links, and range text “Showing X to Y of Z entries”.

    4.3 Edit SSA - Same sections/fields as Create, pre-populated. - Update SSA Info button saves edits; Cancel returns to list. - Primary Service cannot be changed once the SSA is created (field read-only); other fields remain editable within policy rules. - No Reset button to avoid accidental data loss.

    4.4 Status & Lifecycle Actions - Status values include Pending (draft), Active, Completed, Deactivated. - Activate/Deactivate actions require confirmation dialog; optionally capture reason. - Completing an SSA locks scheduling for remaining sessions but preserves Served Minutes history. - Activation requires an assigned therapist.

    4.5 Calculations & Business Rules - THO Minutes auto-calculated from minutes_per_session × (sessions_per_frequency × number of frequencies in date range); stored in `tho_minutes` with optional `adjusted_minutes` + `adjustment_notes` for overrides. - `calculated_minutes` may be stored for reporting. - Ensure Start Date precedes End Date; if the primary service is frequency-based, frequency and sessions_per_frequency become required. - Prevent overlapping active SSAs for the same student/service combination unless explicitly allowed; surface inline warnings when a conflict is detected. - Served Minutes updates automatically as approved sessions are recorded, eliminating manual entry discrepancies.

5. USER EXPERIENCE GUIDELINES
   • Required fields marked with \* and validated inline before submission.
   • Dropdowns display up-to-date students, services, and therapists filtered by active status.
   • Provide contextual tooltips for THO Minutes and Frequency fields to reinforce calculations.
   • Table supports keyboard navigation, column sorting, and clearly labeled action buttons.

6. DATA MODEL
   Table: service_support_agreements – `id`, `student_id`, `primary_service_id`, `start_date`, `end_date`, `minutes_per_session`, `frequency`, `sessions_per_frequency`, `calculated_minutes`, `adjusted_minutes`, `adjustment_notes`, `tho_minutes`, `assigned_therapist_id`, `status`, `served_minutes`, timestamps, `deleted_at`.
   Table: ssa_services (pivot) – `ssa_id`, `service_id`, `is_primary`, timestamps, `deleted_at`; additional services flagged with `is_primary=false`.
   Table: students, services, therapists – referenced via foreign keys.

7. ROUTES (INTERNAL WEB APP)
   • GET /admin/ssas – list view with filters/metrics.
   • GET /admin/ssas/create, POST /admin/ssas – creation flow.
   • GET /admin/ssas/{ssa} – detail/edit view including timeline/history.
   • PUT|PATCH /admin/ssas/{ssa} – update handler (excluding service change restriction).
   • PATCH /admin/ssas/{ssa}/status – activate, complete, or deactivate SSA.
   • GET /admin/ssas/export – export filtered dataset.

8. VALIDATION RULES
   • Required: student_id (role=student), primary_service_id (service exists), start_date, end_date (after start), minutes_per_session (5–1440), tho_minutes, frequency and sessions_per_frequency when the primary service is marked `is_frequency_service`.
   • Additional services: optional array; each distinct and must be an indirect service.
   • Assigned therapist: optional at creation, required before activation; must be role=therapist.
   • Prevent creation for inactive students/services/therapists (policies/filters).

9. SECURITY & PERMISSIONS
   • Routes protected by `auth` + `role:admin` middleware.
   • Authorization policies ensure only privileged admins can create/update/deactivate SSAs.
   • All changes logged with actor, timestamp, and summary; sensitive data (student/therapy) handled per compliance standards.

10. ACCESSIBILITY REQUIREMENTS
    • Forms support keyboard navigation, descriptive labels, and `aria-describedby` for inline errors.
    • Tables and badges meet contrast ratios; focus states visible on controls.
    • Screen readers announce status changes and validation errors.

11. FEEDBACK & MESSAGING
    • Creation success toast: “SSA added successfully.”
    • Update success: “SSA information updated successfully.”
    • Inline error messaging near problematic fields.
    • Loading overlays during save and bulk operations.
    • Confirmation dialogs for Activate, Complete, Deactivate actions.

12. NON-FUNCTIONAL REQUIREMENTS
    • Performance: list view paginated; aim for <500 ms response for common queries.
    • Reliability: save operations wrapped in transactions to maintain consistency between SSA header and derived metrics (THO, served minutes).
    • Logging: capture validation errors, calculation mismatches, and state transitions for audit.

13. DEPENDENCIES & INTEGRATIONS
    • Students module supplies active students with timezone context.
    • Services module provides list of available therapies and documentation requirements.
    • Therapists module ensures only active therapists appear in assignments.
    • Scheduling/invoicing modules consume SSA data to plan sessions and compute earned/served minutes.

14. METRICS & REPORTING
    • Count of SSAs by status (pending/active/completed/deactivated).
    • Delivered (served) minutes vs. authorized THO minutes per SSA.
    • Upcoming SSA expirations (End Dates approaching) for proactive reassignment.

15. RISKS & OPEN QUESTIONS
    • Need clarity on multi-service SSAs (single record vs. multiple). Additional service field may need to support lists.
    • Confirm if THO Minutes should allow manual override or always auto-calc.
    • Determine process for reassignment if assigned therapist becomes inactive mid-SSA.

16. VERSION 2 BACKLOG (FUTURE ENHANCEMENTS)
    • Multi-Service Support – move additional services into dedicated child table with independent scheduling parameters.
    • Approval Workflow – add draft/pending approval states with reviewer notifications.
    • SSA Timeline & Documents – attach files (IEPs, contracts) and show audit trail of changes beyond basic history.
    • Integration Hooks – emit events/webhooks when SSA status changes to sync with external calendaring tools.
    • Utilization Alerts – automated alerts when served minutes deviate (over/under) from projected cadence and advanced conflict resolution tooling.
