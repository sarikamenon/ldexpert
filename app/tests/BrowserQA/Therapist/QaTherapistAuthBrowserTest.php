<?php

declare(strict_types=1);

use App\Models\SessionLog;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\TherapistProfile;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

// ─── Authentication ───────────────────────────────────────────────────────────

it('TC-T001 therapist can log in with valid credentials', function (): void {
    $therapist = $this->createQaUser('therapist');
    $therapist->update(['password' => bcrypt('TherapistTest123!')]);

    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first();
    TherapistProfile::factory()->for($therapist, 'user')->create([
        'manager_id' => $admin->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapist): void {
        $browser->visit('/login')
            ->waitFor('input[name="username"]')
            ->type('input[name="username"]', $therapist->username)
            ->type('input[name="password"]', 'TherapistTest123!')
            ->press('@login-button')
            ->waitForLocation('/therapist/dashboard')
            ->assertDontSee('Whoops');
    });
})->group('smoke');

it('TC-T002 therapist can access all permitted routes after login', function (): void {
    $therapist = $this->createQaUser('therapist');
    $therapist->update(['password' => bcrypt('TherapistTest123!')]);

    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first();
    TherapistProfile::factory()->for($therapist, 'user')->create([
        'manager_id' => $admin->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapist): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $therapist->username)
            ->type('input[name="password"]', 'TherapistTest123!')
            ->press('@login-button')
            ->waitForLocation('/therapist/dashboard')
            ->assertDontSee('403')
            ->assertDontSee('Forbidden')
            ->visit('/therapist/session-logs')
            ->assertDontSee('403')
            ->assertDontSee('Forbidden');
    });
})->group('smoke');

it('TC-T003 therapist login is rejected with wrong password', function (): void {
    $therapist = $this->createQaUser('therapist');
    $therapist->update(['password' => bcrypt('CorrectPassword123!')]);

    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first();
    TherapistProfile::factory()->for($therapist, 'user')->create([
        'manager_id' => $admin->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapist): void {
        $browser->visit('/login')
            ->waitFor('input[name="username"]')
            ->type('input[name="username"]', $therapist->username)
            ->type('input[name="password"]', 'WrongPassword456!')
            ->press('@login-button')
            ->pause(800)
            ->assertPathIs('/login');
    });
});

it('TC-T004 therapist cannot access admin routes', function (): void {
    $therapist = $this->createQaUser('therapist');
    $therapist->update(['password' => bcrypt('TherapistTest123!')]);

    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first();
    TherapistProfile::factory()->for($therapist, 'user')->create([
        'manager_id' => $admin->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapist): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $therapist->username)
            ->type('input[name="password"]', 'TherapistTest123!')
            ->press('@login-button')
            ->waitForLocation('/therapist/dashboard')
            ->visit('/admin/students')
            ->pause(600);

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

it('TC-T005 therapist login form with empty fields stays on login page', function (): void {
    $this->browse(function (Browser $browser): void {
        $browser->visit('/login')
            ->waitFor('@login-button')
            ->press('@login-button')
            ->pause(500)
            ->assertPathIs('/login');
    });
});

// ─── Role Isolation ───────────────────────────────────────────────────────────

it('TC-T016 therapist sees only their own session logs', function (): void {
    $admin      = User::where('email', 'develop.ldexpert@gmail.com')->first();
    $therapistA = $this->createQaUser('therapist');
    $therapistA->update(['password' => bcrypt('TherapistTest123!')]);
    $therapistB = $this->createQaUser('therapist');
    $therapistB->update(['password' => bcrypt('TherapistTest123!')]);

    TherapistProfile::factory()->for($therapistA, 'user')->create([
        'manager_id' => $admin->id,
    ]);
    TherapistProfile::factory()->for($therapistB, 'user')->create([
        'manager_id' => $admin->id,
    ]);

    $school  = \App\Models\School::factory()->qa()->create();
    $student = $this->createQaUser('student');
    $student->studentProfile()->update(['school_id' => $school->id]);
    $service = Service::factory()->create();
    $ssa     = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapistA->id,
        'primary_service_id'    => $service->id,
    ]);

    $logA = SessionLog::factory()->submitted()->create([
        'therapist_id'    => $therapistA->id,
        'student_id'      => $student->id,
        'ssa_id'          => $ssa->id,
        'school_id'       => $school->id,
        'service_id'      => $service->id,
        'submitted_by_id' => $therapistA->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapistB, $logA): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $therapistB->username)
            ->type('input[name="password"]', 'TherapistTest123!')
            ->press('@login-button')
            ->waitForLocation('/therapist/dashboard')
            ->visit('/therapist/session-logs')
            ->pause(1500)
            ->assertDontSee($logA->id);
    });
});

it('TC-T017 therapist sees only their own assigned students', function (): void {
    $admin      = User::where('email', 'develop.ldexpert@gmail.com')->first();
    $therapistA = $this->createQaUser('therapist');
    $therapistA->update(['password' => bcrypt('TherapistTest123!')]);
    $therapistB = $this->createQaUser('therapist');
    $therapistB->update(['password' => bcrypt('TherapistTest123!')]);

    TherapistProfile::factory()->for($therapistA, 'user')->create([
        'manager_id' => $admin->id,
    ]);
    TherapistProfile::factory()->for($therapistB, 'user')->create([
        'manager_id' => $admin->id,
    ]);

    $school   = \App\Models\School::factory()->qa()->create();
    $studentA = $this->createQaUser('student');
    $studentA->studentProfile()->update([
        'school_id'  => $school->id,
        'first_name' => 'OnlyTherapistA',
        'last_name'  => 'Student',
    ]);
    $service = Service::factory()->create();
    ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $studentA->id,
        'assigned_therapist_id' => $therapistA->id,
        'primary_service_id'    => $service->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapistB): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $therapistB->username)
            ->type('input[name="password"]', 'TherapistTest123!')
            ->press('@login-button')
            ->waitForLocation('/therapist/dashboard')
            ->visit('/therapist/students')
            ->pause(1500)
            ->assertDontSee('OnlyTherapistA');
    });
});

it('TC-T018 therapist is blocked from viewing another therapist session URL', function (): void {
    $admin      = User::where('email', 'develop.ldexpert@gmail.com')->first();
    $therapistA = $this->createQaUser('therapist');
    $therapistA->update(['password' => bcrypt('TherapistTest123!')]);
    $therapistB = $this->createQaUser('therapist');
    $therapistB->update(['password' => bcrypt('TherapistTest123!')]);

    TherapistProfile::factory()->for($therapistA, 'user')->create([
        'manager_id' => $admin->id,
    ]);
    TherapistProfile::factory()->for($therapistB, 'user')->create([
        'manager_id' => $admin->id,
    ]);

    $school  = \App\Models\School::factory()->qa()->create();
    $student = $this->createQaUser('student');
    $student->studentProfile()->update(['school_id' => $school->id]);
    $service = Service::factory()->create();
    $ssa     = ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $student->id,
        'assigned_therapist_id' => $therapistA->id,
        'primary_service_id'    => $service->id,
    ]);
    $logA = SessionLog::factory()->submitted()->create([
        'therapist_id'    => $therapistA->id,
        'student_id'      => $student->id,
        'ssa_id'          => $ssa->id,
        'school_id'       => $school->id,
        'service_id'      => $service->id,
        'submitted_by_id' => $therapistA->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapistB, $logA): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $therapistB->username)
            ->type('input[name="password"]', 'TherapistTest123!')
            ->press('@login-button')
            ->waitForLocation('/therapist/dashboard')
            ->visit('/therapist/session-logs/' . $logA->id)
            ->pause(600);

        $url      = $browser->driver->getCurrentURL();
        $bodyText = $browser->driver->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('body')
        )->getText();
        // Accept 403 Forbidden, 404 Not Found (scoped findOrFail), or redirect away
        expect(
            str_contains($bodyText, '403') ||
            str_contains($bodyText, 'Forbidden') ||
            str_contains($bodyText, '404') ||
            str_contains($bodyText, 'Not Found') ||
            !str_contains($url, '/therapist/session-logs/' . $logA->id)
        )->toBeTrue();
    });
});

it('TC-T019 therapist cannot access admin student routes', function (): void {
    $therapist = $this->createQaUser('therapist');
    $therapist->update(['password' => bcrypt('TherapistTest123!')]);

    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first();
    TherapistProfile::factory()->for($therapist, 'user')->create([
        'manager_id' => $admin->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapist): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $therapist->username)
            ->type('input[name="password"]', 'TherapistTest123!')
            ->press('@login-button')
            ->waitForLocation('/therapist/dashboard')
            ->visit('/admin/students')
            ->pause(600);

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
});

it('TC-T020 therapist is blocked from URL with another therapist student ID', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->first();
    $therapistA = $this->createQaUser('therapist');
    $therapistA->update(['password' => bcrypt('TherapistTest123!')]);
    $therapistB = $this->createQaUser('therapist');
    $therapistB->update(['password' => bcrypt('TherapistTest123!')]);

    TherapistProfile::factory()->for($therapistA, 'user')->create([
        'manager_id' => $admin->id,
    ]);
    TherapistProfile::factory()->for($therapistB, 'user')->create([
        'manager_id' => $admin->id,
    ]);

    $school   = \App\Models\School::factory()->qa()->create();
    $studentB = $this->createQaUser('student');
    $studentB->studentProfile()->update(['school_id' => $school->id]);
    $service = Service::factory()->create();
    ServiceSupportAgreement::factory()->active()->create([
        'student_id'            => $studentB->id,
        'assigned_therapist_id' => $therapistB->id,
        'primary_service_id'    => $service->id,
    ]);

    $this->browse(function (Browser $browser) use ($therapistA, $studentB): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $therapistA->username)
            ->type('input[name="password"]', 'TherapistTest123!')
            ->press('@login-button')
            ->waitForLocation('/therapist/dashboard')
            ->visit('/therapist/students/' . $studentB->id)
            ->pause(600);

        $url      = $browser->driver->getCurrentURL();
        $bodyText = $browser->driver->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('body')
        )->getText();
        expect(
            str_contains($bodyText, '403') ||
            str_contains($bodyText, 'Forbidden') ||
            !str_contains($url, '/therapist/students/' . $studentB->id)
        )->toBeTrue();
    });
});
