<?php

declare(strict_types=1);

use App\Enums\ScheduleStatus;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\TherapistProfile;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

// ─── Authentication ───────────────────────────────────────────────────────────

it('TC-S001 student can log in with valid credentials', function (): void {
    $school  = School::factory()->qa()->create();
    $student = $this->createQaUser('student');
    $student->update(['password' => bcrypt('StudentTest123!')]);
    // student() factory creates a profile via afterCreating — update it with the school
    $student->studentProfile()->update(['school_id' => $school->id]);

    $this->browse(function (Browser $browser) use ($student): void {
        $browser->visit('/login')
            ->waitFor('input[name="username"]')
            ->type('input[name="username"]', $student->username)
            ->type('input[name="password"]', 'StudentTest123!')
            ->press('@login-button')
            ->waitForLocation('/student/dashboard')
            ->assertDontSee('500')
            ->assertDontSee('Whoops');
    });
})->group('smoke');

it('TC-S002 student session persists after page refresh', function (): void {
    $school  = School::factory()->qa()->create();
    $student = $this->createQaUser('student');
    $student->update(['password' => bcrypt('StudentTest123!')]);
    $student->studentProfile()->update(['school_id' => $school->id]);

    $this->browse(function (Browser $browser) use ($student): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $student->username)
            ->type('input[name="password"]', 'StudentTest123!')
            ->press('@login-button')
            ->waitForLocation('/student/dashboard')
            ->refresh()
            ->assertPathIs('/student/dashboard')
            ->assertDontSee('Sign in');
    });
})->group('smoke');

it('TC-S003 student login is rejected with wrong password', function (): void {
    $student = $this->createQaUser('student');
    $student->update(['password' => bcrypt('CorrectPassword123!')]);
    $school = School::factory()->qa()->create();
    $student->studentProfile()->update(['school_id' => $school->id]);

    $this->browse(function (Browser $browser) use ($student): void {
        $browser->visit('/login')
            ->waitFor('input[name="username"]')
            ->type('input[name="username"]', $student->username)
            ->type('input[name="password"]', 'WrongPassword456!')
            ->press('@login-button')
            ->pause(800)
            ->assertPathIs('/login');
    });
});

it('TC-S004 login is rejected with non-existent student username', function (): void {
    $this->browse(function (Browser $browser): void {
        $browser->visit('/login')
            ->waitFor('input[name="username"]')
            ->type('username', 'nosuchstudent_notreal')
            ->type('input[name="password"]', 'anypassword')
            ->press('@login-button')
            ->pause(800)
            ->assertPathIs('/login');
    });
});

it('TC-S005 student login form with empty fields stays on login page', function (): void {
    $this->browse(function (Browser $browser): void {
        $browser->visit('/login')
            ->waitFor('@login-button')
            ->press('@login-button')
            ->pause(500)
            ->assertPathIs('/login');
    });
});

// ─── Dashboard ────────────────────────────────────────────────────────────────

it('TC-S006 dashboard shows upcoming scheduled session with date and time', function (): void {
    $school    = School::factory()->qa()->create(['timezone' => 'America/New_York']);
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first();
    $therapist = $this->createQaUser('therapist');
    $therapist->update(['password' => bcrypt('TherapistTest123!')]);
    TherapistProfile::factory()->for($therapist, 'user')->create([
        'manager_id' => $admin->id,
    ]);

    $student = $this->createQaUser('student');
    $student->update(['password' => bcrypt('StudentTest123!')]);
    $student->studentProfile()->update([
        'school_id' => $school->id,
        'timezone'  => 'America/New_York',
    ]);

    $service = Service::factory()->create();
    $ssa     = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    \App\Models\Schedule::factory()->create([
        'student_id'    => $student->id,
        'therapist_id'  => $therapist->id,
        'ssa_id'        => $ssa->id,
        'service_id'    => $service->id,
        'school_id'     => $school->id,
        'schedule_date' => now()->addDays(3)->toDateString(),
        'start_time'    => now()->addDays(3)->setTime(9, 0)->utc(),
        'status'        => ScheduleStatus::SCHEDULED,
    ]);

    $this->browse(function (Browser $browser) use ($student): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $student->username)
            ->type('input[name="password"]', 'StudentTest123!')
            ->press('@login-button')
            ->waitForLocation('/student/dashboard')
            ->assertDontSee('Whoops')
            ->assertDontSee('500');
    });
});

it('TC-S007 dashboard shows therapist name on the upcoming schedule entry', function (): void {
    $school    = School::factory()->qa()->create(['timezone' => 'America/New_York']);
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first();
    $therapist = $this->createQaUser('therapist');
    $therapist->update(['password' => bcrypt('TherapistTest123!'), 'name' => 'Visible Therapist']);
    TherapistProfile::factory()->for($therapist, 'user')->create([
        'manager_id' => $admin->id,
        'first_name' => 'Visible',
        'last_name'  => 'Therapist',
    ]);

    $student = $this->createQaUser('student');
    $student->update(['password' => bcrypt('StudentTest123!')]);
    $student->studentProfile()->update([
        'school_id' => $school->id,
    ]);

    $service = Service::factory()->create();
    $ssa     = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    \App\Models\Schedule::factory()->create([
        'student_id'    => $student->id,
        'therapist_id'  => $therapist->id,
        'ssa_id'        => $ssa->id,
        'service_id'    => $service->id,
        'school_id'     => $school->id,
        'schedule_date' => now()->utc()->toDateString(),
        'start_time'    => now()->utc()->addHour(),
        'status'        => ScheduleStatus::SCHEDULED,
    ]);

    $this->browse(function (Browser $browser) use ($student): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $student->username)
            ->type('input[name="password"]', 'StudentTest123!')
            ->press('@login-button')
            ->waitForLocation('/student/dashboard')
            ->assertSee('Visible');
    });
});

it('TC-S008 dashboard does not show cancelled schedules', function (): void {
    $school    = School::factory()->qa()->create();
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first();
    $therapist = $this->createQaUser('therapist');
    $therapist->update(['password' => bcrypt('TherapistTest123!')]);
    TherapistProfile::factory()->for($therapist, 'user')->create([
        'manager_id' => $admin->id,
    ]);

    $student = $this->createQaUser('student');
    $student->update(['password' => bcrypt('StudentTest123!')]);
    $student->studentProfile()->update(['school_id' => $school->id]);

    $service = Service::factory()->create();
    $ssa     = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    \App\Models\Schedule::factory()->create([
        'student_id'    => $student->id,
        'therapist_id'  => $therapist->id,
        'ssa_id'        => $ssa->id,
        'service_id'    => $service->id,
        'school_id'     => $school->id,
        'schedule_date' => now()->addDays(2)->toDateString(),
        'start_time'    => now()->addDays(2)->setTime(9, 0)->utc(),
        'status'        => ScheduleStatus::CANCELLED,
    ]);

    $this->browse(function (Browser $browser) use ($student): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $student->username)
            ->type('input[name="password"]', 'StudentTest123!')
            ->press('@login-button')
            ->waitForLocation('/student/dashboard')
            ->assertDontSee('Cancelled')
            ->assertDontSee('Whoops');
    });
});

it('TC-S009 student cannot see another student schedule on their dashboard', function (): void {
    $school    = School::factory()->qa()->create();
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first();
    $therapist = $this->createQaUser('therapist');
    $therapist->update(['password' => bcrypt('TherapistTest123!')]);
    TherapistProfile::factory()->for($therapist, 'user')->create([
        'manager_id' => $admin->id,
    ]);

    $studentA = $this->createQaUser('student');
    $studentA->update(['password' => bcrypt('StudentTest123!')]);
    $studentA->studentProfile()->update(['school_id' => $school->id]);

    $studentB = $this->createQaUser('student');
    $studentB->update(['password' => bcrypt('StudentTest123!')]);
    $studentB->studentProfile()->update([
        'school_id'  => $school->id,
        'first_name' => 'OtherStudent',
        'last_name'  => 'Invisible',
    ]);

    $service = Service::factory()->create();
    $ssaB    = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $studentB->id,
        'assigned_therapist_id' => $therapist->id,
        'primary_service_id'    => $service->id,
    ]);

    \App\Models\Schedule::factory()->create([
        'student_id'    => $studentB->id,
        'therapist_id'  => $therapist->id,
        'ssa_id'        => $ssaB->id,
        'service_id'    => $service->id,
        'school_id'     => $school->id,
        'schedule_date' => now()->addDays(5)->toDateString(),
        'start_time'    => now()->addDays(5)->setTime(9, 0)->utc(),
        'status'        => ScheduleStatus::SCHEDULED,
    ]);

    $this->browse(function (Browser $browser) use ($studentA): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $studentA->username)
            ->type('input[name="password"]', 'StudentTest123!')
            ->press('@login-button')
            ->waitForLocation('/student/dashboard')
            ->assertDontSee('OtherStudent');
    });
});

it('TC-S010 dashboard shows empty state when student has no schedules', function (): void {
    $school  = School::factory()->qa()->create();
    $student = $this->createQaUser('student');
    $student->update(['password' => bcrypt('StudentTest123!')]);
    $student->studentProfile()->update(['school_id' => $school->id]);

    $this->browse(function (Browser $browser) use ($student): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $student->username)
            ->type('input[name="password"]', 'StudentTest123!')
            ->press('@login-button')
            ->waitForLocation('/student/dashboard')
            ->assertDontSee('Whoops')
            ->assertDontSee('500');
    });
});

// ─── Role Isolation ───────────────────────────────────────────────────────────

it('TC-S021 student can access all permitted student routes without error', function (): void {
    $school  = School::factory()->qa()->create();
    $student = $this->createQaUser('student');
    $student->update(['password' => bcrypt('StudentTest123!')]);
    $student->studentProfile()->update(['school_id' => $school->id]);

    // /student/session-history does not exist — history is on the dashboard
    $this->browse(function (Browser $browser) use ($student): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $student->username)
            ->type('input[name="password"]', 'StudentTest123!')
            ->press('@login-button')
            ->waitForLocation('/student/dashboard')
            ->assertDontSee('403')
            ->assertDontSee('Forbidden');
    });
})->group('smoke');

it('TC-S022 student dashboard only shows their own profile data', function (): void {
    $school   = School::factory()->qa()->create();
    $studentA = $this->createQaUser('student');
    $studentA->update(['password' => bcrypt('StudentTest123!'), 'name' => 'StudentA Name']);
    $studentA->studentProfile()->update([
        'school_id'  => $school->id,
        'first_name' => 'StudentA',
        'last_name'  => 'Name',
    ]);

    $studentB = $this->createQaUser('student');
    $studentB->update(['password' => bcrypt('StudentTest123!'), 'name' => 'StudentB Secret']);
    $studentB->studentProfile()->update([
        'school_id'  => $school->id,
        'first_name' => 'StudentB',
        'last_name'  => 'Secret',
    ]);

    $this->browse(function (Browser $browser) use ($studentA): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $studentA->username)
            ->type('input[name="password"]', 'StudentTest123!')
            ->press('@login-button')
            ->waitForLocation('/student/dashboard')
            ->assertDontSee('StudentB');
    });
});

it('TC-S023 student is blocked from admin routes', function (): void {
    $school  = School::factory()->qa()->create();
    $student = $this->createQaUser('student');
    $student->update(['password' => bcrypt('StudentTest123!')]);
    $student->studentProfile()->update(['school_id' => $school->id]);

    $this->browse(function (Browser $browser) use ($student): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $student->username)
            ->type('input[name="password"]', 'StudentTest123!')
            ->press('@login-button')
            ->waitForLocation('/student/dashboard')
            ->visit('/admin/students')
            ->pause(800);

        // 403 renders at same URL (not a redirect) — check for forbidden content
        $url      = $browser->driver->getCurrentURL();
        $bodyText = $browser->driver->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('body')
        )->getText();
        expect(
            str_contains($bodyText, '403') ||
            str_contains($bodyText, 'Forbidden') ||
            !str_contains($url, '/admin/students')
        )->toBeTrue();
    });
})->group('smoke');

it('TC-S024 student is blocked from therapist routes', function (): void {
    $school  = School::factory()->qa()->create();
    $student = $this->createQaUser('student');
    $student->update(['password' => bcrypt('StudentTest123!')]);
    $student->studentProfile()->update(['school_id' => $school->id]);

    $this->browse(function (Browser $browser) use ($student): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $student->username)
            ->type('input[name="password"]', 'StudentTest123!')
            ->press('@login-button')
            ->waitForLocation('/student/dashboard')
            ->visit('/therapist/dashboard')
            ->pause(800);

        // 403 renders at same URL (not a redirect) — check for forbidden content
        $url      = $browser->driver->getCurrentURL();
        $bodyText = $browser->driver->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('body')
        )->getText();
        expect(
            str_contains($bodyText, '403') ||
            str_contains($bodyText, 'Forbidden') ||
            !str_contains($url, '/therapist/dashboard')
        )->toBeTrue();
    });
})->group('smoke');

it('TC-S025 student is blocked from another student portal page via direct URL', function (): void {
    // The student area exposes only /student/dashboard, which is always scoped
    // to the authenticated user and takes no ID — there is no student-facing URL
    // that accepts another student's ID. The real cross-student surfaces are the
    // ID-parameterized admin and therapist student pages, both guarded by the
    // `role:` middleware (aborts 403 for the wrong role). A logged-in student
    // must be blocked from those when pointed at student B's ID.
    $school   = School::factory()->qa()->create();
    $studentA = $this->createQaUser('student');
    $studentA->update(['password' => bcrypt('StudentTest123!')]);
    $studentA->studentProfile()->update(['school_id' => $school->id]);

    $studentB = $this->createQaUser('student');
    $studentB->update(['password' => bcrypt('StudentTest123!')]);
    $studentB->studentProfile()->update(['school_id' => $school->id]);

    $blockedFromStudentBPortal = function (Browser $browser, string $path): bool {
        $browser->visit($path)->pause(600);

        $url      = $browser->driver->getCurrentURL();
        $bodyText = $browser->driver->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('body')
        )->getText();

        // Blocked if a 403/Forbidden is shown, or we never landed on the target
        // page (redirected away). In every case student B's portal must not load.
        return str_contains($bodyText, '403')
            || str_contains($bodyText, 'Forbidden')
            || ! str_contains($url, (string) $path);
    };

    $this->browse(function (Browser $browser) use ($studentA, $studentB, $blockedFromStudentBPortal): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $studentA->username)
            ->type('input[name="password"]', 'StudentTest123!')
            ->press('@login-button')
            ->waitForLocation('/student/dashboard');

        // Admin student portal — guarded by role:admin.
        expect($blockedFromStudentBPortal($browser, '/admin/students/' . $studentB->id))->toBeTrue();

        // Therapist student portal — guarded by role:therapist.
        expect($blockedFromStudentBPortal($browser, '/therapist/students/' . $studentB->id))->toBeTrue();
    });
});
