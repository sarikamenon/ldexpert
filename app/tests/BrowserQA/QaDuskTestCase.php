<?php

declare(strict_types=1);

namespace Tests\BrowserQA;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Base test case for all BrowserQA Dusk tests.
 *
 * Uses targeted tearDown cleanup instead of migrate:fresh so that
 * QA tests are safe to run against staging — only records with the
 * "qa." prefix in email / "QA " prefix in school name are deleted.
 * Real user data is never touched.
 */
abstract class QaDuskTestCase extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Browser::$storeScreenshotsAt = base_path('tests/BrowserQA/screenshots');
        Browser::$storeConsoleLogAt = base_path('tests/BrowserQA/console');
        Browser::$storeSourceAt = base_path('tests/BrowserQA/source');
    }

    /**
     * Create a QA-prefixed user that will be auto-cleaned after the test.
     * Always use this instead of User::factory() for test data.
     *
     * Uses the .qa() factory state which prefixes email with 'qa.'
     *
     * ⚠️  IMPORTANT: Only use for 'therapist' or 'student' roles.
     *     NEVER use for 'admin' — there is only ONE system admin.
     *
     * @param string $role The role ('therapist' or 'student' ONLY)
     * @param array<string, mixed> $overrides Optional attributes to override
     * @return User
     * @throws \InvalidArgumentException if role is 'admin'
     */
    protected function createQaUser(string $role = 'therapist', array $overrides = []): User
    {
        if ($role === 'admin') {
            throw new \InvalidArgumentException(
                'Cannot create QA admin user. There is only ONE system admin: '
                . 'develop.ldexpert@gmail.com. Use that credential for all admin tests.'
            );
        }

        $user = User::factory()
            ->qa()
            ->{$role}()
            ->create($overrides);

        // Real users are created via CreateUserDTO which defaults username = email
        // when no explicit username is provided. The factory generates a different
        // username, so sync it here so browser form login (Auth::attempt on 'username'
        // column) works when tests type the email address into the Username field.
        if (! isset($overrides['username'])) {
            $user->update(['username' => $user->email]);
        }

        return $user;
    }

    /**
     * Create a QA-prefixed school that will be auto-cleaned after the test.
     * Always use this instead of School::factory() for test data.
     *
     * Uses the .qa() factory state which prefixes full_name with 'QA '
     *
     * @param array<string, mixed> $overrides Optional attributes to override
     * @return \App\Models\School
     */
    protected function createQaSchool(array $overrides = []): \App\Models\School
    {
        return \App\Models\School::factory()
            ->qa()
            ->create($overrides);
    }

    /**
     * Login as a user and visit a page in one call.
     * Reduces boilerplate in every test that needs authentication.
     *
     * @param User $user The user to log in as
     * @param string $path The path to visit (e.g., '/admin/dashboard')
     */
    protected function loginAndVisit(User $user, string $path): void
    {
        $this->browse(function (Browser $browser) use ($user, $path): void {
            $browser->loginAs($user)->visit($path);
        });
    }

    protected function tearDown(): void
    {
        $this->cleanUpQaTestData();
        parent::tearDown();
    }

    /**
     * Delete all QA-prefixed records created during this test.
     *
     * Identifies records by:
     *  - users.email LIKE 'qa.%'
     *  - schools.full_name LIKE 'QA %'
     *
     * Deletes in FK-safe order (children before parents). Uses DB::table()
     * to bypass soft deletes and Eloquent events — we want true row removal.
     * The system admin (develop.ldexpert@gmail.com) is never touched.
     */
    protected function cleanUpQaTestData(): void
    {
        try {
            // ── Collect root IDs ─────────────────────────────────────

            $qaUserIds = DB::table('users')
                ->where('email', 'like', 'qa.%')
                ->pluck('id');

            $qaSchoolIds = DB::table('schools')
                ->where('full_name', 'like', 'QA %')
                ->pluck('id');

            if ($qaUserIds->isEmpty() && $qaSchoolIds->isEmpty()) {
                return;
            }

            $qaTherapistProfileIds = $qaUserIds->isNotEmpty()
                ? DB::table('therapist_profiles')->whereIn('user_id', $qaUserIds)->pluck('id')
                : collect();

            // SSAs where the student or assigned therapist is a QA user
            $qaSsaIds = $qaUserIds->isNotEmpty()
                ? DB::table('service_support_agreements')
                    ->where(function ($q) use ($qaUserIds): void {
                        $q->whereIn('student_id', $qaUserIds)
                          ->orWhereIn('assigned_therapist_id', $qaUserIds);
                    })->pluck('id')
                : collect();

            // Session logs where the student or therapist is a QA user
            $qaSessionLogIds = $qaUserIds->isNotEmpty()
                ? DB::table('session_logs')
                    ->where(function ($q) use ($qaUserIds): void {
                        $q->whereIn('student_id', $qaUserIds)
                          ->orWhereIn('therapist_id', $qaUserIds);
                    })->pluck('id')
                : collect();

            // Invoices for QA schools
            $qaInvoiceIds = $qaSchoolIds->isNotEmpty()
                ? DB::table('invoices')->whereIn('school_id', $qaSchoolIds)->pluck('id')
                : collect();

            // Therapist bills for QA users (therapist_bills.therapist_id → users.id)
            $qaTherapistBillIds = $qaUserIds->isNotEmpty()
                ? DB::table('therapist_bills')->whereIn('therapist_id', $qaUserIds)->pluck('id')
                : collect();

            // Billing schedules for QA schools and QA therapist profiles (polymorphic)
            $qaBillingScheduleIds = collect();
            if ($qaSchoolIds->isNotEmpty()) {
                $qaBillingScheduleIds = $qaBillingScheduleIds->merge(
                    DB::table('billing_schedules')
                        ->where('schedulable_type', 'App\\Models\\School')
                        ->whereIn('schedulable_id', $qaSchoolIds)->pluck('id')
                );
            }
            if ($qaTherapistProfileIds->isNotEmpty()) {
                $qaBillingScheduleIds = $qaBillingScheduleIds->merge(
                    DB::table('billing_schedules')
                        ->where('schedulable_type', 'App\\Models\\TherapistProfile')
                        ->whereIn('schedulable_id', $qaTherapistProfileIds)->pluck('id')
                );
            }

            // School contract IDs collected upfront so audit cleanup can reference them
            $qaSchoolContractIds = $qaSchoolIds->isNotEmpty()
                ? DB::table('school_contracts')->whereIn('school_id', $qaSchoolIds)->pluck('id')
                : collect();

            // Therapist contract IDs collected upfront for audit cleanup
            $qaTherapistContractIds = $qaTherapistProfileIds->isNotEmpty()
                ? DB::table('therapist_contracts')->whereIn('therapist_id', $qaTherapistProfileIds)->pluck('id')
                : collect();

            // Student profile IDs collected upfront for audit cleanup
            $qaStudentProfileIds = $qaUserIds->isNotEmpty()
                ? DB::table('student_profiles')->whereIn('user_id', $qaUserIds)->pluck('id')
                : collect();

            // ── 1. Pre-clean: nullify session_log billing FKs ─────────
            // Must happen before deleting invoices/bills (FK constraint on
            // session_logs.invoice_id and session_logs.therapist_bill_id).

            if ($qaInvoiceIds->isNotEmpty()) {
                DB::table('session_logs')->whereIn('invoice_id', $qaInvoiceIds)
                    ->update(['invoice_id' => null]);
            }
            if ($qaTherapistBillIds->isNotEmpty()) {
                DB::table('session_logs')->whereIn('therapist_bill_id', $qaTherapistBillIds)
                    ->update(['therapist_bill_id' => null]);
            }

            // ── 2. Deepest session-log children ───────────────────────

            if ($qaSessionLogIds->isNotEmpty()) {
                DB::table('session_log_comments')
                    ->whereIn('session_log_id', $qaSessionLogIds)->delete();
                DB::table('session_logs')
                    ->whereIn('id', $qaSessionLogIds)->delete();
            }

            // ── 3. SSA children ───────────────────────────────────────

            if ($qaSsaIds->isNotEmpty()) {
                DB::table('ssa_assignment_history')->whereIn('ssa_id', $qaSsaIds)->delete();
                DB::table('schedules')->whereIn('ssa_id', $qaSsaIds)->delete();
                DB::table('ssa_goals')->whereIn('ssa_id', $qaSsaIds)->delete();
                DB::table('ssa_services')->whereIn('ssa_id', $qaSsaIds)->delete();
                DB::table('service_support_agreements')
                    ->whereIn('id', $qaSsaIds)->delete();
            }

            // Schedules linked to QA therapists but not through a QA SSA
            if ($qaUserIds->isNotEmpty()) {
                DB::table('schedules')->whereIn('therapist_id', $qaUserIds)->delete();
            }

            // ── 4. Billing: ledger entries for QA schools / therapists ─
            // Polymorphic — no DB FK so no ordering requirement, but clean
            // before their parent rows are gone.

            if ($qaSchoolIds->isNotEmpty()) {
                DB::table('ledger_entries')
                    ->where('ledgerable_type', 'App\\Models\\School')
                    ->whereIn('ledgerable_id', $qaSchoolIds)->delete();
            }
            if ($qaTherapistProfileIds->isNotEmpty()) {
                DB::table('ledger_entries')
                    ->where('ledgerable_type', 'App\\Models\\TherapistProfile')
                    ->whereIn('ledgerable_id', $qaTherapistProfileIds)->delete();
            }

            // ── 5. Invoice children then invoices ─────────────────────

            if ($qaInvoiceIds->isNotEmpty()) {
                DB::table('billing_reminders')
                    ->where('remindable_type', 'App\\Models\\Invoice')
                    ->whereIn('remindable_id', $qaInvoiceIds)->delete();
                DB::table('payment_gateway_transactions')
                    ->whereIn('invoice_id', $qaInvoiceIds)->delete();
                DB::table('invoice_payment_allocations')
                    ->whereIn('invoice_id', $qaInvoiceIds)->delete();
                DB::table('invoice_payments')
                    ->whereIn('invoice_id', $qaInvoiceIds)->delete();
                DB::table('invoice_line_items')
                    ->whereIn('invoice_id', $qaInvoiceIds)->delete();
                DB::table('invoice_email_logs')
                    ->whereIn('invoice_id', $qaInvoiceIds)->delete();
                DB::table('invoices')->whereIn('id', $qaInvoiceIds)->delete();
            }

            // ── 6. Therapist bill children then bills ─────────────────

            if ($qaTherapistBillIds->isNotEmpty()) {
                DB::table('billing_reminders')
                    ->where('remindable_type', 'App\\Models\\TherapistBill')
                    ->whereIn('remindable_id', $qaTherapistBillIds)->delete();
                DB::table('therapist_bill_payment_allocations')
                    ->whereIn('therapist_bill_id', $qaTherapistBillIds)->delete();
                DB::table('therapist_bill_payments')
                    ->whereIn('therapist_bill_id', $qaTherapistBillIds)->delete();
                DB::table('therapist_bills')->whereIn('id', $qaTherapistBillIds)->delete();
            }

            // ── 7. Billing schedules ──────────────────────────────────

            if ($qaBillingScheduleIds->isNotEmpty()) {
                DB::table('billing_reminders')
                    ->where('remindable_type', 'App\\Models\\BillingSchedule')
                    ->whereIn('remindable_id', $qaBillingScheduleIds)->delete();
                DB::table('billing_schedule_runs')
                    ->whereIn('billing_schedule_id', $qaBillingScheduleIds)->delete();
                DB::table('billing_schedules')
                    ->whereIn('id', $qaBillingScheduleIds)->delete();
            }

            // ── 8. School contracts for QA schools ────────────────────

            if ($qaSchoolContractIds->isNotEmpty()) {
                DB::table('school_contract_services')
                    ->whereIn('school_contract_id', $qaSchoolContractIds)->delete();
                DB::table('school_contracts')
                    ->whereIn('id', $qaSchoolContractIds)->delete();
            }

            // ── 9. Therapist contracts for QA therapist profiles ──────

            if ($qaTherapistContractIds->isNotEmpty()) {
                DB::table('therapist_contract_services')
                    ->whereIn('therapist_contract_id', $qaTherapistContractIds)->delete();
                DB::table('therapist_contracts')
                    ->whereIn('id', $qaTherapistContractIds)->delete();
            }

            // ── 9a. School calendar events (RESTRICT on school_id) ────
            // Must be deleted before schools can be deleted.

            if ($qaSchoolIds->isNotEmpty()) {
                DB::table('school_calendar_events')
                    ->whereIn('school_id', $qaSchoolIds)->delete();
            }

            // ── 9b. Pre-user-deletion: delete RESTRICT/NO ACTION children
            // of QA users that are NOT cleaned up by earlier steps.
            // MySQL treats NO ACTION identically to RESTRICT (immediate check).
            // Note: schools.manager_id and therapist_profiles.manager_id are
            // both NOT NULL RESTRICT — cannot be nullified; instead QA schools
            // are deleted in step 9e (before users) and QA therapist profiles
            // are deleted in step 10a (before the user DELETE).

            if ($qaUserIds->isNotEmpty()) {
                // schedule_makeup_availabilities.therapist_id → RESTRICT
                DB::table('schedule_makeup_availabilities')
                    ->whereIn('therapist_id', $qaUserIds)->delete();

                // schedule_sub_ssas — NO ACTION on student_id + sub_therapist_id
                DB::table('schedule_sub_ssas')
                    ->whereIn('student_id', $qaUserIds)->delete();
                DB::table('schedule_sub_ssas')
                    ->whereIn('sub_therapist_id', $qaUserIds)->delete();

                // schedule_sub_request_invitees.therapist_id → NO ACTION
                DB::table('schedule_sub_request_invitees')
                    ->whereIn('therapist_id', $qaUserIds)->delete();

                // schedule_sub_requests — NO ACTION on requested_by_id + accepted_by_id
                DB::table('schedule_sub_requests')
                    ->whereIn('requested_by_id', $qaUserIds)->delete();
                DB::table('schedule_sub_requests')
                    ->whereIn('accepted_by_id', $qaUserIds)->delete();

                // schedule_makeup_requests — NO ACTION on therapist_id + student_id
                DB::table('schedule_makeup_requests')
                    ->whereIn('therapist_id', $qaUserIds)->delete();
                DB::table('schedule_makeup_requests')
                    ->whereIn('student_id', $qaUserIds)->delete();

                // student_documents.uploaded_by_id → RESTRICT
                DB::table('student_documents')
                    ->whereIn('uploaded_by_id', $qaUserIds)->delete();

                // session_log_import_rows must go before session_log_imports (child rows)
                $qaSessionLogImportIds = DB::table('session_log_imports')
                    ->whereIn('user_id', $qaUserIds)->pluck('id');
                if ($qaSessionLogImportIds->isNotEmpty()) {
                    DB::table('session_log_import_rows')
                        ->whereIn('session_log_import_id', $qaSessionLogImportIds)->delete();
                }
                // session_log_imports.user_id → NO ACTION
                DB::table('session_log_imports')
                    ->whereIn('user_id', $qaUserIds)->delete();

                // qglob_requests — NO ACTION on requested_by_id + student_id
                DB::table('qglob_requests')
                    ->whereIn('requested_by_id', $qaUserIds)->delete();
                DB::table('qglob_requests')
                    ->whereIn('student_id', $qaUserIds)->delete();

                // ssa_assignment_history (therapist FK not covered by step 3's ssa_id cleanup)
                DB::table('ssa_assignment_history')
                    ->whereIn('therapist_id', $qaUserIds)->delete();

                // therapist_student pivot (has deleted_at; DB::table does hard delete)
                DB::table('therapist_student')
                    ->whereIn('therapist_id', $qaUserIds)->delete();
                DB::table('therapist_student')
                    ->whereIn('student_id', $qaUserIds)->delete();

                // therapist_school pivot — therapist side (school side handled in step 9c).
                // Guard: some environments (e.g. techup/main) may not have this table yet.
                if (Schema::hasTable('therapist_school')) {
                    DB::table('therapist_school')
                        ->whereIn('therapist_id', $qaUserIds)->delete();
                }

                // student_comments (student and author FKs)
                DB::table('student_comments')
                    ->whereIn('student_id', $qaUserIds)->delete();
                DB::table('student_comments')
                    ->whereIn('author_id', $qaUserIds)->delete();
            }

            // ── 9c. schedule_sub_ssas + therapist_school for QA schools ─
            // Must be gone before schools can be deleted.

            if ($qaSchoolIds->isNotEmpty()) {
                DB::table('schedule_sub_ssas')
                    ->whereIn('school_id', $qaSchoolIds)->delete();
                // therapist_school pivot — school side (therapist side cleaned in 9b).
                // Guard: some environments (e.g. techup/main) may not have this table yet.
                if (Schema::hasTable('therapist_school')) {
                    DB::table('therapist_school')
                        ->whereIn('school_id', $qaSchoolIds)->delete();
                }
            }

            // ── 9d. Delete QA schools (BEFORE users) ──────────────────
            // schools.manager_id is NOT NULL RESTRICT → users cannot be
            // deleted while any school still references them as manager.
            // School::factory()->qa() auto-creates a QA admin as manager_id,
            // so we must drop the school before we can drop the user.
            // Prerequisites already met: school_contracts (step 8), calendar
            // events (step 9a), sub_ssas (step 9c) are all deleted.

            if ($qaSchoolIds->isNotEmpty()) {
                DB::table('schools')->whereIn('id', $qaSchoolIds)->delete();
            }

            // ── 10. User profiles + users ─────────────────────────────
            // therapist_profiles.manager_id is NOT NULL RESTRICT — delete
            // QA therapist profiles (step 10a) before the single user DELETE
            // so no profile remains pointing to a QA admin as manager_id.

            if ($qaUserIds->isNotEmpty()) {
                // 10a — profiles first (clear manager_id RESTRICT references)
                DB::table('admin_profiles')->whereIn('user_id', $qaUserIds)->delete();
                DB::table('therapist_profiles')->whereIn('user_id', $qaUserIds)->delete();
                DB::table('student_profiles')->whereIn('user_id', $qaUserIds)->delete();
                // 10b — users (all QA users: admin, therapist, student)
                DB::table('users')->whereIn('id', $qaUserIds)->delete();
            }

            // ── 11. Audits for QA entities ────────────────────────────
            // No FK constraints on audits (polymorphic string IDs), so this
            // can run any time — placed last so all entity IDs stay valid above.

            if ($qaSchoolIds->isNotEmpty()) {
                DB::table('audits')
                    ->where('auditable_type', 'App\\Models\\School')
                    ->whereIn('auditable_id', $qaSchoolIds)->delete();
            }
            if ($qaSchoolContractIds->isNotEmpty()) {
                DB::table('audits')
                    ->where('auditable_type', 'App\\Models\\SchoolContract')
                    ->whereIn('auditable_id', $qaSchoolContractIds)->delete();
            }
            if ($qaSsaIds->isNotEmpty()) {
                DB::table('audits')
                    ->where('auditable_type', 'App\\Models\\ServiceSupportAgreement')
                    ->whereIn('auditable_id', $qaSsaIds)->delete();
            }
            if ($qaStudentProfileIds->isNotEmpty()) {
                DB::table('audits')
                    ->where('auditable_type', 'App\\Models\\StudentProfile')
                    ->whereIn('auditable_id', $qaStudentProfileIds)->delete();
            }
            if ($qaTherapistContractIds->isNotEmpty()) {
                DB::table('audits')
                    ->where('auditable_type', 'App\\Models\\TherapistContract')
                    ->whereIn('auditable_id', $qaTherapistContractIds)->delete();
            }
            if ($qaUserIds->isNotEmpty()) {
                DB::table('audits')
                    ->where('auditable_type', 'App\\Models\\User')
                    ->whereIn('auditable_id', $qaUserIds)->delete();
                DB::table('audits')->whereIn('created_by', $qaUserIds)->delete();
            }
        } catch (\Throwable $e) {
            // Never let cleanup failure break the test tearDown chain.
            // Log it so the issue is visible without silently swallowing it.
            \Illuminate\Support\Facades\Log::warning(
                'QaDuskTestCase: cleanUpQaTestData failed: ' . $e->getMessage()
            );
        }
    }
}
