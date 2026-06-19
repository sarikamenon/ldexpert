# Admin Test Data

> Used by: app/qa/LD-Expert-QA.xlsx — Admin sheet (TC-A001–TC-A110)
> Generated: 2026-06-12. Run factory snippets in Tinker: `docker compose exec -T app bash -lc 'php artisan tinker'`
> Factory chain order (see /ld-expert-domain): School → User(therapist/student) → Profiles → Service → SSA → Goal/Schedule/SessionLog.

> **AUTH MODEL (important):** You CANNOT create/register admin users. Log in only as the seeded
> **system admin** — `develop.ldexpert@gmail.com` / `Password123!`. There is **no school login**;
> the system admin manages schools. Therapists/students log in with their own credentials
> (created by the system admin). Where an admin is needed as `manager_id`, reuse the system admin.

---

## TD-A001: System admin login — Authentication, Dashboard
**Used by:** Authentication, Access Control, Dashboard cases

```php
// Do NOT factory-create an admin. The system admin is seeded; log in with fixed credentials:
//   email:    develop.ldexpert@gmail.com
//   password: Password123!
$admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
// Dusk: $browser->visit('/login')->type('email','develop.ldexpert@gmail.com')
//               ->type('password','Password123!')->press('Login')->assertPathIs('/admin/dashboard');
```

---

## TD-A002: Active school (+ optional service rate) — Schools, School Calendar Events
**Used by:** Schools, School Calendar Events cases

```php
use App\Enums\SchoolStatus;
use App\Enums\SchoolType;

$manager = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail(); // system admin = manager
$school  = School::factory()->create([
    'status'       => SchoolStatus::ACTIVE,
    'display_name' => 'QA Greenfield Academy',           // unique
    'school_type'  => SchoolType::BRICK_MORTAR,
    'manager_id'   => $manager->id,
    'invoice_email'=> 'billing@greenfield.test',
]);
$service = Service::factory()->create();
// service rate override (rate >= 0, rate_type H or F)
$school->serviceRates()->create([
    'service_id' => $service->id, 'rate' => 120.00, 'rate_type' => 'H',
]);
```

---

## TD-A003: Therapist with profile — Therapists, Contracts
**Used by:** Therapists, Contracts (therapist) cases

```php
$therapist = User::factory()->therapist()->create(['email' => 'qa-therapist@test.com']);
TherapistProfile::factory()->for($therapist, 'user')->create([
    'personal_email'   => 'qa-therapist@test.com',       // unique per profile
    'max_weekly_hours' => 40,                            // 1–168
    'manager_id'       => User::where('email', 'develop.ldexpert@gmail.com')->value('id'),
]);
```

---

## TD-A004: Student under active school — Students, Student Documents, Student Import
**Used by:** Students, Student Documents, Student Import cases

```php
$school  = School::factory()->create(['status' => \App\Enums\SchoolStatus::ACTIVE]);
$student = User::factory()->student()->create(['email' => 'qa-student@test.com']);
$student->studentProfile()->update([
    'school_id'   => $school->id,
    'id_number'   => 'STU-1001',
    'date_of_birth' => '2014-05-01',                     // before today, after 1900-01-01
    'grade_level' => '5',
    'timezone'    => 'America/New_York',
]);
```

---

## TD-A005: Active SSA with assigned therapist — SSAs, SSA Goals
**Used by:** SSAs, SSA Goals cases

```php
$service = Service::factory()->create();
$ssa = ServiceSupportAgreement::factory()->active()->create([
    'student_id'            => $student->id,
    'primary_service_id'    => $service->id,
    'assigned_therapist_id' => $therapist->id,
    'minutes_per_session'   => 45,                       // 5–1440
    'start_date'            => '2026-06-01',
    'end_date'              => '2026-12-31',             // after start_date
]);
// Pending SSA (no therapist) for the "activate without therapist" negative case:
$pending = ServiceSupportAgreement::factory()->create([
    'student_id' => $student->id, 'primary_service_id' => $service->id,
    'assigned_therapist_id' => null, 'status' => 'pending',
]);
```

---

## TD-A006: SUBMITTED session log — Session Logs (approve / send-back / cancel)
**Used by:** Session Logs cases

```php
use App\Enums\SessionLogStatus;

$log = SessionLog::factory()->create([
    'status'       => SessionLogStatus::SUBMITTED,
    'ssa_id'       => $ssa->id,
    'student_id'   => $student->id,
    'therapist_id' => $therapist->id,
    'service_id'   => $service->id,
    'school_id'    => $school->id,
    'session_date' => '2026-06-10',
]);
// For "approve a non-submitted log": create a DRAFT log instead.
// For "send back without reason": SendBackSessionLogRequest requires a reason.
```

---

## TD-A007: Lead for conversion — Leads
**Used by:** Leads cases

```php
$lead = Lead::factory()->create(['status' => 'new']);
$activeSchool = School::factory()->create(['status' => \App\Enums\SchoolStatus::ACTIVE]);
```

---

## TD-A008: Catalog records — Services, Positions, Service Aliases
**Used by:** Services, Positions, Service Aliases cases

```php
$service  = Service::factory()->create(['name' => 'QA Speech Therapy']);
$position = Position::factory()->create(['name' => 'QA SLP']);
// Service alias maps an external label to $service.
```

---

## TD-A009: Notifications — Notifications
**Used by:** Notifications cases

```php
$admin->notify(new \App\Notifications\GenericNotification('QA test'));   // produces an unread row
```

---

## Reset between tests
- QA Dusk base class `QaDuskTestCase` cleans up `qa*`-prefixed records in `tearDown()`.
- Use `$this->createQaUser()` / `$this->createQaSchool()` so data is auto-cleaned.
- Full reset: `php artisan migrate:fresh --seed` (testing DB `bird_test`).
