NOVA · Student Documents Module PRD
Version 1.0 · Last Updated: 13 Jan 2026

1. OVERVIEW
   The Student Documents module enables NOVA administrators and therapists to upload, manage, and track documents associated with students or session logs. Documents are stored securely on S3 with metadata tracking (type, size, uploader, description) and support polymorphic relationships to both students and session logs. This feature centralizes document management for compliance, progress tracking, and therapy documentation.

2. OBJECTIVES
   • Provide secure document storage and retrieval for student-related files.
   • Support multiple document types (Progress Reports, IEPs, Consent Forms, Assessments, Other).
   • Enable document association with students directly or via session logs.
   • Maintain audit trail of document uploads, downloads, and deletions.
   • Ensure proper authorization for document access based on user roles and student assignments.

3. PERSONA & ROLE
   Persona: System Admin / Therapist | Role: Role::ADMIN, Role::THERAPIST | Goals: Upload and manage student documents, track document history, ensure compliance with document retention policies.

4. FUNCTIONAL SCOPE
   4.1 Document Upload (Admin)
   - Student Selection: Choose student from dropdown or upload from student detail page.
   - Document Type: Dropdown with options: Progress Report, IEP, Consent Form, Assessment, Other.
   - File Selection: File input with validation (type, size limits).
   - Description: Optional text field for document notes.
   - Actions: Upload Document (primary), Cancel (secondary).

   4.2 Document Upload (Therapist - Session Log)
   - Session Log Context: Upload from session log detail page (document automatically linked to session log).
   - Same fields as admin upload (type, file, description).
   - Authorization: Therapist must be assigned to student via SSA.

   4.3 Document List (Admin)
   - Global Document Index: Paginated table with filters (student, document type, date range, uploader).
   - Columns: Document Name, Student, Type, Uploaded By, Upload Date, File Size, Actions (Download, Delete).
   - Search: Filter by student name, document type, or uploader.
   - Actions: Download, Delete (with confirmation).

   4.4 Document List (Student Context)
   - Student Detail Page: Documents section showing all documents for student (direct and via session logs).
   - Grouped by: Direct student documents and session log documents (with session log link).
   - Chronological order (newest first).
   - Actions: Download, Delete (admin only; therapists can delete their own uploads).

   4.5 Document Download
   - Secure download via signed URL or direct S3 access.
   - File served with original filename and MIME type.
   - Download events logged for audit (future enhancement).

   4.6 Document Deletion
   - Soft delete: Documents marked as deleted, retained for audit.
   - Authorization: Admins can delete any document; therapists can delete only their own uploads.
   - Confirmation dialog required before deletion.
   - Deleted documents excluded from default queries but recoverable via admin tools.

5. USER EXPERIENCE GUIDELINES
   • File upload shows progress indicator during upload.
   • Document list uses clear icons for document types.
   • File size displayed in human-readable format (KB, MB, GB).
   • Download links open in new tab/window.
   • Delete confirmation uses SweetAlert2 with clear messaging.
   • Success toast: "Document uploaded successfully."
   • Error messages for file size/type validation displayed inline.

6. DATA MODEL
   Table: student_documents – `id`, `documentable_type` (polymorphic: User or SessionLog), `documentable_id` (polymorphic foreign key), `uploaded_by_id` (foreign key to users), `document_type` (enum: progress_report, iep, consent_form, assessment, other), `file_name`, `file_path` (S3/local path), `mime_type` (nullable), `file_size` (bytes, nullable), `description` (text, nullable), timestamps, `deleted_at` (soft delete).

   Relationships:
   - `student_documents.documentable_type` + `documentable_id` → polymorphic to `users` (students) or `session_logs`.
   - `student_documents.uploaded_by_id` → `users.id` (admin or therapist).
   - Indexes: `(documentable_type, documentable_id)`, `document_type`, `uploaded_by_id`, `created_at`.

   Enums:
   - `DocumentType`: PROGRESS_REPORT, IEP, CONSENT_FORM, ASSESSMENT, OTHER.

7. ROUTES (INTERNAL WEB APP)
   Admin Routes:
   • GET /admin/student-documents – list all documents with filters.
   • POST /admin/student-documents/students/{student} – upload document for student.
   • GET /admin/student-documents/{document}/download – download document.
   • DELETE /admin/student-documents/{document} – delete document.

   Therapist Routes:
   • POST /therapist/session-logs/{sessionLog}/documents – upload document for session log.
   • GET /therapist/session-logs/{sessionLog}/documents/{document}/download – download session log document.
   • DELETE /therapist/session-logs/{sessionLog}/documents/{document} – delete session log document.

   Controllers: `App\Http\Controllers\Admin\StudentDocumentController`, `App\Http\Controllers\Therapist\SessionLogDocumentController`.

8. VALIDATION RULES
   • File: Required, max size 50MB (configurable), allowed MIME types: PDF, images, Word documents (configurable via `config/filesystems.php` or service).
   • Document Type: Required, must be one of: progress_report, iep, consent_form, assessment, other.
   • Description: Optional, max 1000 characters.
   • Student/Session Log: Required context (student ID or session log ID) must exist and be accessible by user.

9. SECURITY & PERMISSIONS
   • Routes protected by `auth` + `role:admin` or `role:therapist` middleware.
   • `StudentDocumentPolicy` enforces:
     - Admins can view, upload, download, and delete any document.
     - Therapists can view documents for students assigned via SSA.
     - Therapists can upload documents to their session logs.
     - Therapists can delete only documents they uploaded.
   • File storage: S3 bucket with IAM policies restricting access to application.
   • Download URLs: Signed URLs with expiration (future enhancement) or direct S3 access with policy checks.
   • All uploads logged with actor, timestamp, and file metadata.

10. ACCESSIBILITY REQUIREMENTS
    • File upload input has descriptive label and help text explaining file requirements.
    • Document list tables fully navigable via keyboard.
    • Download links include descriptive text (not just icon).
    • Delete buttons have confirmation dialogs with clear messaging.

11. FEEDBACK & MESSAGING
    • Success toast: "Document uploaded successfully."
    • File validation errors: "File must be PDF, image, or Word document. Max size: 50MB."
    • Delete confirmation: "Are you sure you want to delete this document? This action cannot be undone."
    • Download errors: "Document not found or access denied."
    • Loading indicators during upload and download operations.

12. NON-FUNCTIONAL REQUIREMENTS
    • Performance: File uploads handled asynchronously for large files (future enhancement).
    • Storage: Documents stored on S3 (local in testing) with organized path structure: `student-documents/{year}/{month}/{uuid}.{ext}`.
    • Reliability: File uploads wrapped in transactions; database record created only after successful S3 upload.
    • Scalability: Support documents up to 50MB per file (configurable).
    • Logging: All document operations (upload, download, delete) logged with user, timestamp, and file metadata.
    • Retention: Soft deletes preserve documents for audit; hard delete policy TBD (compliance-driven).

13. DEPENDENCIES & INTEGRATIONS
    • Requires Student module for student context and authorization.
    • Requires Session Log module for session log document associations.
    • Uses S3 file storage (AWS S3 or compatible) with Laravel Storage facade.
    • Integrates with authorization policies (`StudentDocumentPolicy`).
    • Future: Integration with document generation (PDF reports, IEP templates).

14. METRICS & REPORTING
    • Document count by type (Progress Reports, IEPs, etc.).
    • Document upload frequency by user role (admin vs. therapist).
    • Average file size per document type.
    • Documents per student (distribution).
    • Storage usage trends over time.

15. RISKS & OPEN QUESTIONS
    • File size limits: 50MB may be insufficient for large assessments or video files (consider separate handling for media).
    • Storage costs: S3 storage costs scale with document volume; need monitoring and cleanup policies.
    • Document versioning: Current implementation doesn't track document revisions; may need versioning for compliance.
    • Access control: Signed URLs with expiration may be needed for enhanced security (future enhancement).
    • Retention policies: Need FERPA/IDEA guidance on document retention periods and deletion schedules.

16. VERSION 2 BACKLOG (FUTURE ENHANCEMENTS)
    • Document Versioning – track document revisions and maintain history.
    • Document Preview – inline PDF/image preview without download.
    • Bulk Upload – upload multiple documents at once with drag-and-drop.
    • Document Templates – generate documents from templates (IEP forms, progress reports).
    • Document Signing – electronic signature workflow for consent forms.
    • Advanced Search – full-text search within document contents (OCR for scanned documents).
    • Document Expiration – automatic notifications for documents approaching expiration (e.g., consent forms).
