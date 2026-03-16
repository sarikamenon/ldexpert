NOVA · Student Import Module PRD
Version 1.0 · Last Updated: 13 Jan 2026

1. OVERVIEW
   The Student Import module enables NOVA administrators to bulk import student records from CSV files, supporting multiple import source formats (NOVA, RSM, MARVIN). The system processes imports asynchronously with row-level validation, duplicate detection, and comprehensive error reporting. This feature eliminates manual data entry for large student cohorts while maintaining data integrity through validation rules and audit trails.

2. OBJECTIVES
   • Enable bulk onboarding of students via CSV file upload.
   • Support multiple import source formats with configurable column mappings.
   • Provide real-time import status tracking with detailed row-level error reporting.
   • Ensure data integrity through validation, duplicate detection, and transaction safety.
   • Maintain complete audit trail of import operations and row processing results.

3. PERSONA & ROLE
   Persona: System Admin / Student Manager | Role: Role::ADMIN | Goals: Efficiently onboard large cohorts of students, reduce manual entry errors, and maintain import history for compliance and troubleshooting.

4. FUNCTIONAL SCOPE
   4.1 Import Form
   - File Upload: CSV file selection (max size: 10MB default, configurable via `config/student-import.php`).
   - Import Type Selection: Dropdown with options NOVA, RSM, MARVIN, TUTORBIRD (defaults to NOVA).
   - Template Download: Link to download CSV template matching selected import type.
   - Actions: Upload & Import (primary), Cancel (secondary).

   4.2 Import Processing
   - File uploaded to S3 (local storage in testing environment).
   - Import job queued for asynchronous processing.
   - Row-by-row validation against student creation rules.
   - Duplicate detection by email (globally unique) or student ID within school.
   - School lookup by external EMR name from CSV.
   - Transaction-safe row processing (each row succeeds or fails independently).

   4.3 Import Status Tracking
   - Import History List: Paginated table showing all imports with status, date, file name, row counts (total, processed, successful, failed).
   - Import Detail View: Shows import metadata, overall status, and paginated list of rows with individual status and error messages.
   - Real-time Status Updates: AJAX polling for import progress (optional enhancement).
   - Row Statuses: PENDING, PROCESSING, SUCCESS, VALIDATION_ERROR, DUPLICATE, FAILED.

   4.4 Error Handling
   - File Structure Validation: Checks for required columns, proper CSV format.
   - Row Validation: Validates each row against student creation rules (required fields, data types, constraints).
   - School Lookup Errors: Reports when school name from CSV doesn't match any active school.
   - Duplicate Handling: Reports duplicate email or student ID within school, optionally skips or updates existing records.
   - Error Messages: Clear, actionable error messages per row with field-level details.

5. USER EXPERIENCE GUIDELINES
   • Import form provides clear instructions and sample template download.
   • File upload shows progress indicator during upload.
   • After upload, user redirected to import history with new import visible.
   • Import status page shows progress with color-coded status badges.
   • Failed rows display expandable error details for troubleshooting.
   • Success toast: "Import started successfully. Processing in background."
   • Error alerts for file structure issues or upload failures.

6. DATA MODEL
   Table: student_imports – `id`, `imported_by_id` (foreign key to users), `type` (enum: NOVA, RSM, MARVIN, TUTORBIRD), `file_name`, `file_path` (S3/local path), `status` (enum: PENDING, PROCESSING, COMPLETED, FAILED), `total_rows`, `processed_rows`, `successful_rows`, `failed_rows`, `error_message` (nullable, for overall import failures), timestamps, `completed_at` (nullable).

   Table: student_import_rows – `id`, `student_import_id` (foreign key), `row_number` (1-indexed), `status` (enum: PENDING, PROCESSING, SUCCESS, VALIDATION_ERROR, DUPLICATE, FAILED), `raw_data` (json, stores original CSV row), `error_message` (nullable), `student_id` (nullable, foreign key to users if student created), timestamps, `processed_at` (nullable).

   Relationships:
   - `student_imports.imported_by_id` → `users.id` (admin user).
   - `student_import_rows.student_import_id` → `student_imports.id`.
   - `student_import_rows.student_id` → `users.id` (if student created successfully).

7. ROUTES (INTERNAL WEB APP)
   • GET /admin/students/import – show import form.
   • POST /admin/students/import – process CSV upload and queue import job.
   • GET /admin/students/imports – import history list with filters.
   • GET /admin/students/imports/{import} – import detail view with row statuses.
   • GET /admin/students/imports/{import}/status – AJAX endpoint for status updates.
   • GET /admin/students/import/template – download CSV template for selected import type.

   Controllers: `App\Http\Controllers\Admin\StudentController` (import methods).

8. VALIDATION RULES
   • File: Required, CSV format (.csv, .txt), max size 10MB (configurable), MIME types: text/csv, text/plain, application/csv.
   • Import Type: Required, must be one of: NOVA, RSM, MARVIN, TUTORBIRD.
   • Row-level validation matches student creation rules:
     - Required: first_name, last_name, email (unique globally), gender, date_of_birth (before today, after 1900-01-01), school_name (must match active school's external_emr_name), id_number, timezone (valid US timezone), grade_level, city, state (valid US state), zip_code.
     - Optional: middle_name, address, parent_guardian_name, parent_guardian_email, parent_guardian_phone (digits/dashes regex).

9. SECURITY & PERMISSIONS
   • Routes protected by `auth` + `role:admin` middleware.
   • Only admins can initiate imports and view import history.
   • Import files stored securely on S3 with access controls.
   • All imports logged with actor (imported_by_id), timestamp, and file metadata.
   • Row-level errors do not expose sensitive system internals.

10. ACCESSIBILITY REQUIREMENTS
    • File upload input has descriptive label and help text.
    • Import status tables fully navigable via keyboard.
    • Error messages clearly associated with row data via ARIA attributes.
    • Status badges use both color and text for accessibility.

11. FEEDBACK & MESSAGING
    • Success toast: "Import started successfully. Processing in background."
    • File upload errors: "File upload failed. Please check file format and size."
    • Import completion: "Import completed. X successful, Y failed rows."
    • Row errors displayed inline with expandable details.
    • Loading indicators during file upload and status refresh.

12. NON-FUNCTIONAL REQUIREMENTS
    • Performance: Import processing runs asynchronously via queue jobs to avoid timeout.
    • Reliability: Each row processed in transaction; failures don't rollback entire import.
    • Storage: Import files retained on S3 for audit (retention policy TBD).
    • Scalability: Support imports up to 10,000 rows (configurable limit).
    • Logging: All import operations, row processing results, and errors logged with context.

13. DEPENDENCIES & INTEGRATIONS
    • Requires Schools module for school lookup by external EMR name.
    • Uses Student module validation rules and creation logic.
    • Integrates with queue system (database queue default) for async processing.
    • File storage: S3 in production, local in testing.
    • Future: Integration with RSM/CAVA sync may auto-trigger imports or merge with manual imports.

14. METRICS & REPORTING
    • Import success rate (successful rows / total rows).
    • Average processing time per import.
    • Most common validation errors (for template improvement).
    • Import frequency by type (NOVA vs. RSM vs. MARVIN).
    • Duplicate detection rate.

15. RISKS & OPEN QUESTIONS
    • File size limits: Current 10MB may be insufficient for very large cohorts (consider chunking or streaming).
    • Duplicate resolution: Currently reports duplicates; future may need merge/update strategy.
    • School name matching: Relies on exact match with external_emr_name; may need fuzzy matching for typos.
    • Import rollback: No automatic rollback of successful rows if later rows fail; manual cleanup may be needed.
    • Template versioning: Column mappings may change over time; need versioning strategy for templates.

16. TUTORBIRD IMPORT TYPE (FUNCTIONALITY & LOGIC)

   TutorBird is a supported import source for student records exported from TutorBird scheduling/billing systems. TutorBird CSV files have a different structure than NOVA/RSM, with different column names and up to 4 parent contacts (only Parent Contact 1 is imported).

   16.1 Supported CSV Structure
   - **Required columns:** First Name, Last Name, TutorBird Student ID, School
   - **Optional columns:** Email, Birthday, Address, Gender, Mobile Phone, Parent Contact 1 Last Name, Parent Contact 1 First Name, Parent Contact 1 Email, Parent Contact 1 Mobile Phone
   - School must match an active school's `external_emr_name` (e.g., "NR School 01"). The school must exist before import.

   16.2 Column Mapping (TutorBird → Internal)
   | TutorBird CSV Column | Internal Field |
   |----------------------|----------------|
   | First Name | first_name |
   | Last Name | last_name |
   | TutorBird Student ID | id_number |
   | School | school_name |
   | Email | email |
   | Birthday | date_of_birth |
   | Address | address |
   | Gender | gender |
   | Mobile Phone | student_mobile_phone |
   | Parent Contact 1 First Name + Last Name | parent_guardian_name (combined) |
   | Parent Contact 1 Email | parent_guardian_email |
   | Parent Contact 1 Mobile Phone | parent_guardian_phone |

   16.3 Business Logic
   - **Student email fallback:** If the student Email column is empty, the system uses Parent Contact 1 Email as the student's email address.
   - **Parent name:** Parent Contact 1 First Name and Last Name are combined into a single `parent_guardian_name` field.
   - **Duplicate detection:** Duplicate detection uses `id_number` (TutorBird Student ID) within the school and `username` (system-wide). Siblings sharing the same parent email may create multiple students if the system allows; duplicate id_number within the same school will be rejected.
   - **Phone normalization:** Phone numbers in various formats (e.g., `407-538-2859`, `5123008985`, `(801) 702-4233`) are normalized to `XXX-XXX-XXXX`.
   - **Username generation:** Student usernames follow the pattern `{first_name}.{last_name}.{id_number}` (e.g., `John.Doe.TB001`).

   16.4 Unmapped TutorBird Columns (Not Imported)
   TutorBird Family ID, Adult Student, Status, Note, Parent Contact 2/3/4, Texting Allowed, Subject, Level, Base Duration, Rate, Tutor, Group Tags, Referrer, Last Lesson, Next Lesson, and similar billing/scheduling fields are not imported. Student import is student-record only; SSA/billing import is out of scope.

   16.5 Known Behaviors & Edge Cases
   - **No parent email + no student email:** Row fails validation (email required).
   - **Duplicate parent emails across siblings:** Second student with same parent email will show validation error (accepted behavior).
   - **School must exist:** The School value in the CSV must match an active school's `external_emr_name`. If not found, the row fails with "School not found".

17. VERSION 2 BACKLOG (FUTURE ENHANCEMENTS)
    • Custom Column Mappings – allow admins to define school-specific or source-specific column mappings.
    • Import Preview – show first N rows with validation results before committing import.
    • Bulk Actions – retry failed rows, skip duplicates, update existing students.
    • Import Scheduling – schedule recurring imports from external sources.
    • Real-time Progress – WebSocket or Server-Sent Events for live import progress updates.
    • Import Templates Editor – UI for creating and editing import type templates.
