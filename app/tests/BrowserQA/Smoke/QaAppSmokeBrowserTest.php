<?php

declare(strict_types=1);

use App\Models\School;
use App\Models\TherapistProfile;
use App\Models\User;
use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

// Fast cross-role canary. Every test is shallow ("is it alive / does it work").
// Depth (validation, edge cases, CRUD, billing math) lives in the role suites
// and the nightly full run — keep this suite under ~5 minutes.

/**
 * Drive the real login form with the given credentials.
 */
function smokeLoginForm(Browser $browser, string $username, string $password): void
{
    // Dusk reuses one browser across tests, so a prior login can leave an
    // authenticated session that bounces us off /login. Start clean.
    $browser->visit('/login');
    $browser->driver->manage()->deleteAllCookies();

    $browser->visit('/login')
        ->waitFor('input[name="username"]', 10)
        ->type('username', $username)
        ->type('password', $password)
        ->click('@login-button');
}

/**
 * Assert the current browser session is blocked from the admin area
 * (RoleMiddleware aborts 403; some guards redirect away instead).
 */
function smokeAssertBlockedFromAdmin(Browser $browser): void
{
    $browser->visit('/admin/dashboard')->pause(600);

    $url = $browser->driver->getCurrentURL();
    $body = $browser->driver->findElement(WebDriverBy::cssSelector('body'))->getText();

    expect(
        str_contains($body, '403')
        || str_contains($body, 'Forbidden')
        || ! str_contains($url, '/admin/dashboard')
    )->toBeTrue();
}

// ─── App health ─────────────────────────────────────────────────────────────

it('TC-SM001 guest visiting home is redirected to login', function (): void {
    $this->browse(function (Browser $browser): void {
        $browser->visit('/')
            ->assertPathIs('/login')
            ->waitFor('input[name="username"]', 10)
            ->assertPresent('input[name="username"]')
            ->assertPresent('input[name="password"]');
    });
})->group('smoke');

// ─── Login works per role ───────────────────────────────────────────────────

it('TC-SM002 admin can log in via the login form', function (): void {
    // Seeded system admin; its username column holds the email.
    $this->browse(function (Browser $browser): void {
        smokeLoginForm($browser, 'develop.ldexpert@gmail.com', 'Password123!');
        $browser->waitForLocation('/admin/dashboard', 15)
            ->assertPathIs('/admin/dashboard');
    });
})->group('smoke');

it('TC-SM003 therapist can log in via the login form', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create([
        'username' => 'qa_smoke_therapist',
        'email' => 'qa.smoke.therapist@e2e-test.com',
        'password' => 'Password123!',
    ]);
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $this->browse(function (Browser $browser): void {
        smokeLoginForm($browser, 'qa_smoke_therapist', 'Password123!');
        $browser->waitForLocation('/therapist/dashboard', 15)
            ->assertPathIs('/therapist/dashboard');
    });
})->group('smoke');

it('TC-SM004 student can log in via the login form', function (): void {
    $school = School::factory()->qa()->create();
    $student = User::factory()->student()->qa()->create([
        'username' => 'qa_smoke_student',
        'email' => 'qa.smoke.student@e2e-test.com',
        'password' => 'Password123!',
    ]);
    $student->studentProfile()->update(['school_id' => $school->id]);

    $this->browse(function (Browser $browser): void {
        smokeLoginForm($browser, 'qa_smoke_student', 'Password123!');
        $browser->waitForLocation('/student/dashboard', 15)
            ->assertPathIs('/student/dashboard');
    });
})->group('smoke');

// ─── Key admin pages load (no 500, no bounce) ───────────────────────────────

it('TC-SM005 admin schools list loads', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)->visit('/admin/schools')->waitForText('Schools/Families', 10);
    });
})->group('smoke');

it('TC-SM006 admin therapists list loads', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)->visit('/admin/therapists')->waitForText('Therapists', 10);
    });
})->group('smoke');

it('TC-SM007 admin students list loads', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)->visit('/admin/students')->waitForText('Students', 10);
    });
})->group('smoke');

it('TC-SM008 admin session logs list loads', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)->visit('/admin/session-logs')->waitForText('Session Logs', 10);
    });
})->group('smoke');

it('TC-SM009 admin therapist bills list loads', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)->visit('/admin/billing/therapist-bills')->waitForText('Therapist Bills', 10);
    });
})->group('smoke');

it('TC-SM010 finance dashboard loads', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)->visit('/admin/finance/dashboard')->waitForText('Finance Dashboard', 10);
    });
})->group('smoke');

// ─── Key create forms open (render only, no submit) ─────────────────────────

it('TC-SM011 school create form opens', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)->visit('/admin/schools/create')
            ->waitForText('Add School/Family', 10)
            ->assertPresent('form');
    });
})->group('smoke');

it('TC-SM012 student create form opens', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)->visit('/admin/students/create')
            ->waitForText('Add Student', 10)
            ->assertPresent('form');
    });
})->group('smoke');

// ─── Therapist inner surface loads ──────────────────────────────────────────

it('TC-SM013 therapist SSAs page loads', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $this->browse(function (Browser $browser) use ($therapist): void {
        $browser->loginAs($therapist)->visit('/therapist/ssas')->waitForText('My SSAs', 10);
    });
})->group('smoke');

// ─── Role isolation / security ──────────────────────────────────────────────

it('TC-SM014 student cannot access the admin area', function (): void {
    $student = User::factory()->student()->qa()->create();

    $this->browse(function (Browser $browser) use ($student): void {
        $browser->loginAs($student);
        smokeAssertBlockedFromAdmin($browser);
    });
})->group('smoke');

it('TC-SM015 therapist cannot access the admin area', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $therapist = User::factory()->therapist()->qa()->create();
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $this->browse(function (Browser $browser) use ($therapist): void {
        $browser->loginAs($therapist);
        smokeAssertBlockedFromAdmin($browser);
    });
})->group('smoke');

// ─── Logout ─────────────────────────────────────────────────────────────────

it('TC-SM016 a logged-in user can log out', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)
            ->visit('/admin/dashboard')
            ->waitForText('Dashboard', 10)
            ->script("document.querySelector('form[action*=\"logout\"]').submit();");
        $browser->waitForLocation('/login', 10)
            ->assertPathIs('/login');
    });
})->group('smoke');
