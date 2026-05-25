---
name: project_second_parent
description: Second parent/guardian feature — flat columns on student_profiles, capped at 2, contact-only
metadata:
  type: project
---

Feature (branch nikhil/student-additional-parents): add a SECOND parent/guardian to students.

Decision: keep it simple — add three nullable flat columns to `student_profiles`:
`parent_guardian_2_name`, `parent_guardian_2_email`, `parent_guardian_2_phone` (mirroring existing `parent_guardian_name/email/phone`). Hard-capped at 2 parents for now; a `student_guardians` child table is the future option if N guardians are ever needed.

Why flat columns not a child table: user wants minimal change; 2nd parent is contact-only, no login, no relationship field, no second schedule_email (single `schedule_email` stays unchanged).

Scope:
- Import: YES — NOVA maps parent_guardian_2_* columns directly; TutorBird maps "Parent Contact 2" (first/last name → combine into parent_guardian_2_name, email, mobile phone) mirroring its Parent Contact 1 pattern. Validation + phone-normalize cover parent_guardian_2_phone. (TutorBird CSV has up to 4 parent contacts; we only take contact 2.)
- Therapist surfaces (DashboardService, Therapist/ScheduleController): NO — keep parent 1 only. Admin form + student show page only.

Status (2026-05-25): IMPLEMENTED. Migration 2026_05_25_102059, model fillable, both Student DTOs, ConvertLeadDTO::toCreateStudentDTO (passes parent_guardian_2_* as null — leads have no 2nd parent), StudentFormRequest rules+message, _form.blade (subgroup), show.blade + overview-details.blade (admin display), import config NOVA + TutorBird (Parent Contact 2) mapping + import service validation/phone-normalize, factory.

Tests: New Pest — StudentSecondParentTest (feature create/update/validation), StudentImportSecondParentTest (unit map+transform), ConvertLeadDTOTest (unit). Extended existing PHPUnit — CreateStudentDTOTest/UpdateStudentDTOTest/StudentServiceTest (new ctor args), StudentImportTest (added parent_guardian_2_* to generateCsvContent + Parent Contact 2 cols to generateTutorbirdCsvContent, plus 2 end-to-end tests asserting persisted parent-2 for NOVA & TutorBird). All green except pre-existing failure below.

NOTE: PHPStan config (app/phpstan.neon.dist) only analyzes `paths: - app`, NOT tests/. make qa = Pint + PHPStan(app) + Pest.

Welcome-email: StudentService::create has the Mail::to(...)->send(WelcomeStudentMail) block COMMENTED OUT (TODO to re-enable). The two tests that asserted it (StudentServiceTest::test_create_creates_student_and_sends_welcome_email line ~69, StudentManagementTest::test_admin_can_create_student line ~232) had their Mail::assertSent COMMENTED OUT with a matching "re-enable in tandem" TODO. When create's mail is uncommented, uncomment both assertions too.

Context: `parent_id`/ParentProfile/Role::PARENT path is DEAD scaffolding — parent_profiles table has 0 rows, 0 students use parent_id. Parents have no login; if they want access they use the student login. The live parent data is the flat `parent_guardian_*` columns.
