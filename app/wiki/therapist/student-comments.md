NOVA · Student Comments Module PRD
Version 1.0 · Last Updated: 13 Jan 2026

1. OVERVIEW
   The Student Comments module enables NOVA administrators and therapists to add contextual comments on student records. Comments are visible to all admins and therapists assigned to the student via SSA, providing a shared communication channel for student-related notes, observations, and coordination. Comments are soft-deleted to maintain audit trails and support compliance requirements.

2. OBJECTIVES
   • Enable admins and therapists to add comments on student records.
   • Provide shared visibility of comments to all authorized users (admins and assigned therapists).
   • Maintain chronological comment history with author attribution.
   • Support audit requirements through soft deletes and timestamps.
   • Facilitate communication and coordination between admins and therapists.

3. PERSONA & ROLE
   Persona: System Admin / Therapist | Role: Role::ADMIN, Role::THERAPIST | Goals: Add contextual notes about students, share observations with team, maintain communication history.

4. FUNCTIONAL SCOPE
   4.1 Add Comment (Admin)
   - Student Context: Add comment from student detail page.
   - Comment Field: Textarea with max 5000 characters, required.
   - Actions: Submit Comment (primary), Cancel (secondary).
   - Comment appears immediately in comments section below form.

   4.2 Add Comment (Therapist)
   - Student Context: Add comment from student detail page (must have SSA assignment to student).
   - Authorization Check: Therapist must be assigned to student via SSA; access denied if not assigned.
   - Same form fields and behavior as admin.
   - Comment visible to all admins and therapists assigned to student.

   4.3 Comment Display
   - Chronological Order: Comments displayed newest first (descending by created_at).
   - Author Information: Each comment shows author name and role (admin or therapist).
   - Timestamp: Display creation date and time.
   - Pagination: Comments paginated (15 per page default) with "Load More" or pagination controls.

   4.4 Comment Management
   - View: All authorized users can view all comments for assigned students.
   - Edit: Not currently supported (future enhancement).
   - Delete: Soft delete only; deleted comments retained for audit but hidden from default view.
   - Audit Trail: All comments retain author, timestamp, and deletion status.

5. USER EXPERIENCE GUIDELINES
   • Comment form appears above comment list on student detail page.
   • Help text: "Add a comment about this student. Comments are visible to all admins and therapists assigned to this student."
   • Character counter shows remaining characters (5000 max).
   • Comments display with clear author attribution and timestamp.
   • Success toast: "Comment added successfully."
   • Error messages for validation failures displayed inline.
   • Loading indicator during comment submission.

6. DATA MODEL
   Table: student_comments – `id`, `student_id` (foreign key to users), `author_id` (foreign key to users, nullable), `comment` (text, max 5000 characters), timestamps, `deleted_at` (soft delete).

   Relationships:
   - `student_comments.student_id` → `users.id` (student user).
   - `student_comments.author_id` → `users.id` (admin or therapist user, nullable for system-generated comments).

   Indexes: `student_id`, `author_id`, `created_at`.

7. ROUTES (INTERNAL WEB APP)
   Admin Routes:
   • POST /admin/students/{student}/comments – create comment on student.

   Therapist Routes:
   • POST /therapist/students/{student}/comments – create comment on student (requires SSA assignment).

   Controllers: `App\Http\Controllers\Admin\StudentCommentController`, `App\Http\Controllers\Therapist\StudentCommentController`.

8. VALIDATION RULES
   • Comment: Required, string, max 5000 characters, not empty after trimming.
   • Student: Required, must exist and be accessible by user (admin or therapist with SSA assignment).
   • Authorization: Therapist must have SSA assignment to student (checked via `SSAService::hasStudentAssignedToTherapist`).

9. SECURITY & PERMISSIONS
   • Routes protected by `auth` + `role:admin` or `role:therapist` middleware.
   • `StudentCommentPolicy` enforces:
     - Admins can create comments on any student.
     - Therapists can create comments only on students assigned via SSA.
     - All authorized users can view comments for assigned students.
   • Comments are soft-deleted; deleted comments retained for audit.
   • All comment operations logged with actor, timestamp, and student context.

10. ACCESSIBILITY REQUIREMENTS
    • Comment textarea has descriptive label and help text.
    • Character counter accessible to screen readers.
    • Comment list uses semantic HTML with proper heading hierarchy.
    • Author and timestamp information clearly associated with each comment.

11. FEEDBACK & MESSAGING
    • Success toast: "Comment added successfully."
    • Validation errors: "Comment is required and cannot exceed 5000 characters."
    • Authorization errors: "You do not have access to this student." (therapist without SSA assignment).
    • Loading indicator during comment submission.

12. NON-FUNCTIONAL REQUIREMENTS
    • Performance: Comment list paginated; aim for <200 ms response for typical queries.
    • Reliability: Comment creation wrapped in transaction; failures logged with context.
    • Scalability: Support up to 10,000 comments per student (with pagination).
    • Logging: All comment operations logged with user, timestamp, and student context.

13. DEPENDENCIES & INTEGRATIONS
    • Requires Student module for student context and authorization.
    • Requires SSA module for therapist assignment verification.
    • Integrates with authorization policies (`StudentCommentPolicy`).
    • Future: Integration with notifications for comment mentions or replies.

14. METRICS & REPORTING
    • Comment count per student (distribution).
    • Comments per user role (admin vs. therapist).
    • Average comments per student.
    • Comment activity trends over time.

15. RISKS & OPEN QUESTIONS
    • Comment length: 5000 characters may be insufficient for detailed notes (consider increasing or adding rich text).
    • Edit functionality: Current implementation doesn't support editing; may need edit capability with edit history.
    • Comment threading: Current flat structure; may need replies/threading for complex discussions.
    • Notification integration: No notifications for new comments; may need email/in-app notifications for team coordination.
    • Moderation: No moderation workflow; may need admin review for sensitive comments.

16. VERSION 2 BACKLOG (FUTURE ENHANCEMENTS)
    • Comment Editing – allow authors to edit comments within time window (e.g., 15 minutes) with edit history.
    • Comment Threading – support replies and threaded conversations.
    • Rich Text Support – markdown or WYSIWYG editor for formatted comments.
    • Comment Mentions – @mention users in comments with notifications.
    • Comment Search – full-text search within comments.
    • Comment Reactions – emoji reactions to comments (thumbs up, etc.).
    • Comment Export – export comment history for compliance or reporting.
