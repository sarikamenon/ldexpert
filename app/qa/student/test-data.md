# Student Test Data

> Used by: app/qa/LD-Expert-QA.xlsx — Student sheet (TC-S001–TC-S012)
> Generated: 2026-06-12. Run in Tinker: `docker compose exec -T app bash -lc 'php artisan tinker'`
>
> **IMPORTANT:** Only `/student/dashboard` is implemented. Schedule Calendar, Progress & Goals,
> Session History and Account Settings are NOT BUILT (wiki "planned routes"). TC-S009–TC-S012
> are Not-Built markers and have no factory setup.

---

## TD-S001: Student account — Authentication, Access Control, Dashboard
**Used by:** TC-S001–TC-S008

```php
use App\Enums\UserStatus;

$school  = School::factory()->create(['status' => \App\Enums\SchoolStatus::ACTIVE]);
$student = User::factory()->student()->create([
    'email'  => 'qa-student@test.com',
    'status' => UserStatus::ACTIVE,
]);
$student->studentProfile()->update([
    'school_id' => $school->id,
    'timezone'  => 'America/New_York',
]);
```

---

## TD-S002: (Not Built) — Schedule Calendar / Progress & Goals / Session History / Account Settings
**Used by:** TC-S009–TC-S012

No setup. These features have no routes yet (`routes/student.php` exposes only `dashboard`).
When implemented, add the SSA/schedule/session-log chain (see Admin TD-A005/TD-A006) and
re-run `/qa-create-scenarios (Role: student)` to generate real cases.

---

## Reset between tests
- `QaDuskTestCase::tearDown()` removes `qa*` records. Use `$this->createQaUser()`.
- Full reset: `php artisan migrate:fresh --seed` against `bird_test`.
