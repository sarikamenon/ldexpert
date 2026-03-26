NOVA · Leads (CRM) PRD
Version 1.0 · Last Updated: 26 Mar 2026

1. OVERVIEW
   The Leads module is a lightweight CRM that tracks prospective students from initial inquiry through enrollment. Admins can create leads, manage their progression through a status pipeline, add notes, schedule follow-ups, and convert qualified leads into student records.

2. OBJECTIVES
   - Capture and track prospective student inquiries from multiple sources.
   - Provide a clear pipeline view of lead progression (inquiry → enrolled or declined).
   - Enable lead-to-student conversion with auto-generated credentials.
   - Automate follow-up reminders via daily email notifications.

3. PERSONA & ROLE
   Persona: System Admin / Enrollment Coordinator | Role: Role::ADMIN | Goals: Manage enrollment pipeline, convert qualified leads to students, track follow-up commitments.

4. FUNCTIONAL SCOPE

   4.1 Create Lead
   Form fields:
   - First Name*, Last Name*, Email, Phone
   - School (dropdown of active schools)
   - Source (LeadSource enum: referral, website, school, event, phone, email, social_media, word_of_mouth, other)
   - Status (default: inquiry)
   - Follow-up Date (date picker)
   - Notes (textarea)
   Actions: Create Lead (primary), Cancel (secondary).

   4.2 List Leads
   Server-side DataTable with:
   - Columns: Name, Email, Phone, School, Source, Status (badge), Follow-up Date, Actions
   - Filters: Status, Source, School
   - Search across name, email, phone
   - Actions: View, Edit, Convert, Delete

   4.3 Edit Lead
   All create fields plus status transitions. Form pre-populates existing data.

   4.4 Lead Detail / Show
   - Lead information card with all fields
   - Status badge with color coding
   - Notes section with chronological list and add-note form
   - Action buttons: Edit, Convert to Student, Change Status, Delete

   4.5 Lead Notes
   - Admins can add timestamped notes to leads
   - Notes display chronologically with author attribution
   - Notes are persisted in `lead_notes` table

   4.6 Status Pipeline
   LeadStatus enum with state machine:
   - Active pipeline: inquiry → contacted → follow_up → evaluation
   - Positive terminal: enrolled
   - Negative terminals: declined, not_eligible, no_response, withdrawn, closed
   - `isActive()`: true for pipeline statuses
   - `isTerminal()`: true for enrolled + all negative terminals
   - `isNegativeTerminal()`: true for declined, not_eligible, no_response, withdrawn, closed

   4.7 Convert Lead to Student
   - Available via dedicated convert form (`GET /admin/leads/{lead}/convert`)
   - Requires: school selection, student profile fields
   - On conversion: creates User with role=student, auto-generates password, links `lead.converted_student_id`
   - Redirects to the new student's detail page
   - Policy: separate `convert` action

   4.8 Follow-up Reminders
   - Scheduled command `leads:send-follow-up-reminders` runs daily at 08:00
   - Sends `LeadFollowUpReminderMail` to the admin who created the lead
   - Triggers when `follow_up_date` matches today
   - Email includes lead name and link to lead detail page

   4.9 Delete Lead
   - Soft delete with confirmation dialog
   - Only entity in the system with an explicit destroy route

5. USER EXPERIENCE GUIDELINES
   - Status badges use design system colors per pipeline stage
   - Follow-up dates in the past highlighted as overdue
   - Convert action requires SweetAlert2 confirmation
   - Delete requires SweetAlert2 confirmation with consequence explanation

6. DATA MODEL
   Table: leads — `id`, `first_name`, `last_name`, `email` (nullable), `phone` (nullable), `school_id` (nullable), `source` (LeadSource enum), `status` (LeadStatus enum), `follow_up_date` (nullable date), `notes` (nullable text), `created_by_id` (admin user), `converted_student_id` (nullable, links to converted student), timestamps, `deleted_at`.
   Table: lead_notes — `id`, `lead_id`, `note` (text), timestamps, `deleted_at`.

7. ROUTES (INTERNAL WEB APP)
   - POST /admin/leads/data — server-side DataTable endpoint.
   - GET /admin/leads — list view.
   - GET /admin/leads/create — create form.
   - POST /admin/leads — store action.
   - GET /admin/leads/{lead} — detail/show page.
   - GET /admin/leads/{lead}/edit — edit form.
   - PUT /admin/leads/{lead} — update action.
   - PATCH /admin/leads/{lead}/status — status change (AJAX).
   - GET /admin/leads/{lead}/convert — convert form.
   - POST /admin/leads/{lead}/convert — execute conversion.
   - POST /admin/leads/{lead}/notes — add note.
   - DELETE /admin/leads/{lead} — soft delete.

8. TECHNICAL IMPLEMENTATION
   Controller: `App\Http\Controllers\Admin\LeadController`
   Note Controller: `App\Http\Controllers\Admin\LeadNoteController`
   Service: `LeadService`
   Policy: `LeadPolicy` (includes `convert`, `delete` actions)
   Enums: `LeadStatus`, `LeadSource`
   Scopes: `LeadScope` (search, byStatus, overdueFollowUp, followUpDueOn)
   Mail: `LeadFollowUpReminderMail`
   Command: `leads:send-follow-up-reminders` (daily at 08:00)

9. OPEN QUESTIONS & RISKS
   - Lead deduplication (same email/phone across leads) not currently enforced.
   - No bulk import for leads.
   - No integration with external CRM systems.
