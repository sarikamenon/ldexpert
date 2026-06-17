# E2E Test Data

> Used by: qa/LD-Expert-QA.xlsx — E2E sheet (TC-E001–TC-E018)
> Generated: 2026-06-12. Run in Tinker: `docker compose exec -T app bash -lc 'php artisan tinker'`
>
> E2E flows span roles. Most start from a clean DB and build the chain through real UI actions,
> so factory setup here is the minimal seed each workflow needs before the first UI step.

---

## TD-E001: One user per role — Role Isolation, Timezone Display
**Used by:** TC-E017, TC-E018

```php
$admin     = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail(); // seeded system admin (no school login)
$therapist = User::factory()->therapist()->create(); // logs in with own email/password
TherapistProfile::factory()->for($therapist, 'user')->create(['timezone' => 'America/Los_Angeles']);
$student   = User::factory()->student()->create();   // logs in with own email/password
$student->studentProfile()->update(['timezone' => 'America/Chicago']);
```

---

## TD-E002: Onboarding seed — School Onboarding, Contract Lifecycle
**Used by:** TC-E001–TC-E004

Start from an admin only; the workflow creates school → therapist → student → SSA through the UI.

```php
$admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail(); // seeded system admin
// Then: system admin UI creates an active School (with contract + service rate),
// a Therapist, a Student under the school, and an SSA assigning the therapist.
// (Schools/admins are never self-registered; therapists & students log in with their own creds.)
```

---

## TD-E003: Session-to-billing seed — Session to Billing, Therapist Bill Flow
**Used by:** TC-E005–TC-E011

```php
// Pre-build the assignment chain so the flow can start at "therapist logs a session".
$school  = School::factory()->create(['status' => \App\Enums\SchoolStatus::ACTIVE,
                                       'invoice_email' => 'billing@school.test']);
$therapist = User::factory()->therapist()->create();
TherapistProfile::factory()->for($therapist, 'user')->create();
$student = User::factory()->student()->create();
$student->studentProfile()->update(['school_id' => $school->id]);
$service = Service::factory()->create();
$ssa = ServiceSupportAgreement::factory()->active()->create([
    'student_id' => $student->id, 'primary_service_id' => $service->id,
    'assigned_therapist_id' => $therapist->id,
]);
$schedule = Schedule::factory()->create([
    'therapist_id' => $therapist->id, 'student_id' => $student->id, 'ssa_id' => $ssa->id,
    'service_id' => $service->id, 'school_id' => $school->id,
]);
// Flow: therapist submits log → admin approves → finance invoices/bills → records payment.
// Assert: ledger entry created; `php artisan ledger:verify` passes.
```

---

## TD-E004: Sub coverage seed — Sub Coverage
**Used by:** TC-E012–TC-E014

```php
// Therapist A owns a future schedule; B and C are eligible peers (same position, active contract+rate).
// A raises a sub request inviting B and C; B accepts; B logs the session.
// Assert: schedule shows "Covered by B"; session_log.therapist_id = B, original_therapist_id = A.
```

---

## TD-E005: Billing automation seed — Billing Automation
**Used by:** TC-E015, TC-E016

```php
// Active billing schedule (auto_generate on) with approved sessions due in the period.
// Standard mode → Run Now produces a draft invoice (one line per session).
// Advance mode → second run reconciles prior advance lines (e.g. no-show credit) + next advance charges.
$schedule = BillingSchedule::factory()->create([
    'schedulable_type' => School::class, 'schedulable_id' => $school->id,
    'frequency' => \App\Enums\BillingFrequency::WEEKLY,
    'billing_mode' => \App\Enums\BillingMode::STANDARD,
    'is_active' => true, 'auto_generate' => true,
]);
```

---

## Reset between tests
- `QaDuskTestCase::tearDown()` removes `qa*` records.
- Existing per-workflow specs live in `qa/e2e/*.md` (student-journey, therapist-session-to-billing, admin-audit-flow).
- Full reset: `php artisan migrate:fresh --seed` against `bird_test`.
