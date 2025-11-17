NOVA · Student Module PRD
Version 1.0 · Last Updated: 14 Nov 2025

1. OVERVIEW
   The Student module enables NOVA administrators to maintain complete, up-to-date student records for therapeutic, administrative, and reporting purposes. It centralizes demographics, school association, guardians, and scheduling context to power SSA creation, session planning, and billing accuracy.

2. OBJECTIVES
   • Provide a consistent workflow to create, edit, view, and list students.
   • Capture all demographic, guardian, address, and school data required for service delivery.
   • Enforce accurate time zone management so reminders and schedules localize per student.
   • Deliver searchable, paginated list views with clear activation controls.

3. PERSONA & ROLE
   Persona: System Admin / Student Manager | Role: Role::ADMIN | Goals: Onboard students, maintain data integrity, manage activation status, and link students to schools/guardians.

4. FUNCTIONAL SCOPE
   4.1 Create Student
   Form Section A – Basic Information - First Name* (text) - Middle Name (text) - Last Name* (text) - Email* (text, email validation) - Gender (dropdown/optional) - Date of Birth* (date picker, dd/mm/yyyy)
   Form Section B – School Information - School Name (dropdown/select existing) - Student ID (text) - Timezone\* (dropdown; required to power localized reminders) - Grade Level (text/dropdown)
   Form Section C – Parent/Guardian Information - Parent/Guardian Name (text) - Parent/Guardian Email (text, email validation) - Parent/Guardian Contact Phone (text, format validation)
   Form Section D – Address Information - Address (textarea) - City (text) - State (dropdown, US states) - Zip Code (text)
   Actions - Create Student (primary submit; validates and persists) - Cancel (secondary; returns to list)

    4.2 List Students
    Features - Paginated table with entry options 5, 10, 25, 50, 100 per page. - Columns: ID, Name (links to view), Email, Grade Level, Date of Birth, Actions (Edit, Deactivate). - Search/filter input for real-time filtering across visible fields. - “Show Deactivated” checkbox to include inactive records. - Summary metrics (optional) for total vs. active counts. - Clicking a Name opens the Student View page; Edit button navigates to edit form; Deactivate toggles active state after confirmation.

    4.3 Edit Student - Form pre-populates all fields from Create. - Fields remain required/optional consistent with Create rules. - Actions: Save Changes (primary) and Cancel (secondary). - Includes ability to reactivate or deactivate student with confirmation dialog.

    4.4 Time Zone & Reminder Handling - Each student stores `timezone` required value. - Scheduler stores canonical event time in EST (or chosen reference) and converts to student’s timezone for reminders/notifications (e.g., 4pm EST -> 1pm PST, reminder sends at 11am PST when configured for 2 hours prior). - UI surfaces timezone alongside schedule info for therapists and guardians.

    4.5 Status & Lifecycle Actions - Students can be marked Active or Deactivated via list/button or edit screen. - Deactivation removes student from assignment pickers but keeps historical data. - Confirmation dialogs capture reason for status change (future enhancement optional).

5. USER EXPERIENCE GUIDELINES
   • Required fields marked with \* and validated inline.
   • Dropdowns (schools, timezones, grades, states) always reflect latest data from respective catalogs.
   • Search and pagination persist user settings between navigations where possible.
   • Table rows clickable for view; action buttons offer tooltips/labels for accessibility.
   • Student view page includes a read-only “Timeline” panel listing key events (created, edited, activated/deactivated, SSA linked) to aid troubleshooting.

6. DATA MODEL
   Table: users (existing) – `role=student`, `status`, base authentication data, timestamps, soft deletes.
   Table: student_profiles (existing/extended) – `user_id`, `first_name`, `middle_name`, `last_name`, `email`, `gender`, `date_of_birth`, `school_id`, `student_id`, `timezone`, `grade_level`, `parent_guardian_name`, `parent_guardian_email`, `parent_guardian_phone`, `address`, `city`, `state`, `zip_code`, timestamps, `deleted_at`.
   Table: schools – referenced via `school_id` for association.

7. ROUTES (INTERNAL WEB APP)
   • GET /admin/students – list view with filters.
   • GET /admin/students/create, POST /admin/students – creation flow.
   • GET /admin/students/{student} – detail/view page.
   • GET /admin/students/{student}/edit, PUT|PATCH /admin/students/{student} – edit flow.
   • PATCH /admin/students/{student}/status – activate/deactivate actions.
   • GET /admin/students/export (optional) – export filtered dataset.

8. VALIDATION RULES
   • First Name, Last Name, Email, Date of Birth, and Timezone are required.
   • Email fields validated via Laravel email rules; enforce uniqueness across students.
   • Date of Birth validated as a real date and optionally must be before current date.
   • Phone numbers follow regex such as ^\d{3}-\d{3}-\d{4}$ (or apply masking component).
   • Zip Code validation ensures numeric/length compliance (e.g., US ZIP 5 or 9 digits).

9. SECURITY & PERMISSIONS
   • Routes protected by `auth` + `role:admin` middleware.
   • Policies ensure only authorized admins can create, update, deactivate, or view detailed student data.
   • All changes logged with actor, timestamp, and summary for auditing.

10. ACCESSIBILITY REQUIREMENTS
    • Forms and tables fully navigable via keyboard; focus states clearly visible.
    • Inputs have descriptive labels and `aria-describedby` links to inline errors.
    • Pagination controls and action buttons meet WCAG 2.1 AA contrast requirements.

11. FEEDBACK & MESSAGING
    • Success toast: “Student added successfully.”
    • Update success: “Student information updated successfully.”
    • Inline errors adjacent to fields causing validation issues.
    • Loading indicators overlay forms during save/deactivate actions.
    • Confirmation dialogs for Deactivate/Reactivate operations.

12. NON-FUNCTIONAL REQUIREMENTS
    • Performance: list view paginated; aim for <500 ms response for typical filters.
    • Reliability: wrap writes in transactions to persist user + profile atomically.
    • Logging: capture validation and persistence errors with context.

13. DEPENDENCIES & INTEGRATIONS
    • Schools module provides school catalog for association.
    • SSA/Scheduling modules rely on student timezone, grade, and active status for assignment and reminders.
    • Future parent portal will reuse guardian contact data captured here.

14. METRICS & REPORTING
    • Student counts by status, grade, and school.
    • Time between creation and first SSA/session (once SSA data available).
    • Deactivation reasons/frequencies (future enhancement once reason codes added).

15. RISKS & OPEN QUESTIONS
    • Need FERPA/IDEA guidance for storing guardian + student PII and reminder data.
    • Determine authoritative source when external sync (RSM/CAVA) conflicts with manual edits.
    • Confirm limit on number of guardians per student for future portal integration.

16. VERSION 2 BACKLOG (FUTURE ENHANCEMENTS)
    • Guardian Invitations & Parent Accounts – send invites, track acceptance, manage multiple guardians.
    • Bulk Import – CSV upload with validation summary for onboarding cohorts.
    • SSA/Assignment Context – display linked SSAs, therapists, and progress on student view (beyond the core timeline events).
    • Reason Codes for Status Changes – require reason/resolution when deactivating/graduating.
    • Enhanced Audit Notes – internal notes separate from therapy session notes with tagging/search.
