NOVA · Student Module PRD
Version 2.0 · Last Updated: 13 Jan 2026

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
   Form Section A – Basic Information - First Name* (text) - Middle Name (text) - Last Name* (text) - Email* (user email, unique) - Gender* (dropdown) - Date of Birth* (date picker, before today, after 1900-01-01)
   Form Section B – School Information - School* (dropdown of active schools) - Student ID* (`id_number`) - Timezone* (dropdown; required for reminders) - Grade Level* (text/dropdown)
   Form Section C – Parent/Guardian Information - Parent/Guardian Name (text) - Parent/Guardian Email (email validation) - Parent/Guardian Contact Phone (digits/dashes) - (Optional) Parent account link via `parent_id` when present
   Form Section D – Address Information - Address (textarea, optional) - City* (text) - State* (US states) - Zip Code* (text)
   Actions - Create Student (primary submit; validates and persists) - Cancel (secondary; returns to list)

    4.2 List Students
    Features - Paginated table with entry options 5, 10, 25, 50, 100 per page. - Columns: ID, Name (links to view), Email, Grade Level, Date of Birth, Actions (Edit, Deactivate). - Search/filter input for real-time filtering across visible fields. - “Show Deactivated” checkbox to include inactive records. - Summary metrics (optional) for total vs. active counts. - Clicking a Name opens the Student View page; Edit button navigates to edit form; Deactivate toggles active state after confirmation.

    4.3 Edit Student - Form pre-populates all fields from Create. - Fields remain required/optional consistent with Create rules. - Actions: Save Changes (primary) and Cancel (secondary). - Includes ability to reactivate or deactivate student with confirmation dialog.

    4.4 Time Zone & Reminder Handling - Each student stores `timezone` required value. - Scheduler stores canonical event time in EST (or chosen reference) and converts to student’s timezone for reminders/notifications (e.g., 4pm EST -> 1pm PST, reminder sends at 11am PST when configured for 2 hours prior). - UI surfaces timezone alongside schedule info for therapists and guardians.

    4.5 Status & Lifecycle Actions - Students can be marked Active or Deactivated via list/button or edit screen. - Deactivation removes student from assignment pickers but keeps historical data. - Confirmation dialogs capture reason for status change (future enhancement optional).

    4.6 Student Import - Admins can bulk import students via CSV files. - Supports multiple import types: NOVA, RSM, MARVIN, TUTORBIRD with column mapping templates. - Import process runs asynchronously with status tracking per row. - Duplicate detection by email or student ID within school. - Import history viewable with detailed row-level error reporting. - See [Student Import PRD](./student-import.md) for complete documentation.

    4.7 Student Comments - Admins can add comments on student records visible to all admins and assigned therapists. - Comments appear in chronological order on student detail page. - Comments are soft-deleted and retain audit trail. - Maximum length: 5000 characters. - See [Student Comments PRD](../therapist/student-comments.md) for complete documentation.

    4.8 Student Documents - Admins can upload and manage documents associated with students. - Documents can be attached to students directly or to session logs. - Supported document types: Progress Report, IEP, Consent Form, Assessment, Other. - Documents stored on S3 (local in testing) with metadata tracking. - Download and delete operations with authorization checks. - See [Student Documents PRD](./student-documents.md) for complete documentation.

5. USER EXPERIENCE GUIDELINES
   • Required fields marked with \* and validated inline.
   • Dropdowns (schools, timezones, grades, states) always reflect latest data from respective catalogs.
   • Search and pagination persist user settings between navigations where possible.
   • Table rows clickable for view; action buttons offer tooltips/labels for accessibility.
   • Student view page includes a read-only “Timeline” panel listing key events (created, edited, activated/deactivated, SSA linked) to aid troubleshooting.

6. DATA MODEL
   Table: users – `id`, `name` (composed from first/last), `email` (unique), `role=student`, `status`, timestamps, soft deletes.
   Table: student_profiles – `user_id`, `parent_id` (optional linked parent user), `first_name`, `middle_name`, `last_name`, `school_id`, `id_number`, `timezone`, `gender`, `address`, `city`, `state`, `zip_code`, `parent_guardian_name`, `parent_guardian_email`, `parent_guardian_phone`, `date_of_birth`, `grade_level`, timestamps, `deleted_at`.
   Table: schools – referenced via `school_id`.
   Table: student_imports – `id`, `imported_by_id`, `type` (NOVA/RSM/MARVIN/TUTORBIRD), `file_name`, `file_path`, `status`, `total_rows`, `processed_rows`, `successful_rows`, `failed_rows`, `error_message`, timestamps, `completed_at`.
   Table: student_import_rows – `id`, `student_import_id`, `row_number`, `status`, `raw_data` (json), `error_message`, `student_id` (nullable, if created), timestamps, `processed_at`.
   Table: student_comments – `id`, `student_id`, `author_id`, `comment` (text, max 5000), timestamps, `deleted_at`.
   Table: student_documents – `id`, `documentable_type` (polymorphic), `documentable_id` (polymorphic), `uploaded_by_id`, `document_type`, `file_name`, `file_path`, `mime_type`, `file_size`, `description`, timestamps, `deleted_at`.

7. ROUTES (INTERNAL WEB APP)
   • GET /admin/students – list view with filters.
   • GET /admin/students/create, POST /admin/students – creation flow.
   • GET /admin/students/{student} – detail/view page.
   • GET /admin/students/{student}/edit, PUT|PATCH /admin/students/{student} – edit flow.
   • PATCH /admin/students/{student}/status – activate/deactivate actions.
   • GET /admin/students/export – export filtered dataset.
   • GET /admin/students/import – show import form.
   • POST /admin/students/import – process CSV import.
   • GET /admin/students/imports – import history list.
   • GET /admin/students/imports/{import} – import status detail.
   • GET /admin/students/import/template – download CSV template.
   • POST /admin/students/{student}/comments – create comment on student.
   • GET /admin/student-documents – list all student documents with filters.
   • POST /admin/student-documents/students/{student} – upload document for student.
   • GET /admin/student-documents/{document}/download – download document.
   • DELETE /admin/student-documents/{document} – delete document.

8. VALIDATION RULES
   • Required: first_name, last_name, email (unique on users), gender, date_of_birth (past, after 1900-01-01), school_id (active school), id_number, timezone (from constants), grade_level, city, state (US list), zip_code.
   • Optional: middle_name, address, parent_guardian_name/email/phone (digits/dashes regex), parent_id link.
   • Date of Birth must be before today; timezone/state constrained to enumerations.

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
    • SSA/Assignment Context – display linked SSAs, therapists, and progress on student view (beyond the core timeline events).
    • Reason Codes for Status Changes – require reason/resolution when deactivating/graduating.
    • Enhanced Audit Notes – internal notes separate from therapy session notes with tagging/search.
    • Import Templates Customization – allow admins to define custom column mappings per school or import source.
    • Comment Threading – support replies and threaded conversations on student comments.
    • Document Versioning – track document revisions and maintain history of changes.
