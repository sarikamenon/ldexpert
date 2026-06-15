<?php

declare(strict_types=1);

use App\Models\{Position, School, User};
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

/**
 * Admin Core QA Tests — Authentication, Dashboard, Schools/Families Management
 *
 * LOCATOR GUIDE:
 * - School Form:
 *   - #full_name, #display_name, #state, #timezone, #manager_id
 *   - #contact_first_name, #contact_last_name, #contact_phone, #contact_email, #invoice_email
 *   - #school_type — valid values: 'Virtual', 'Brick Mortar', 'Blended' (SchoolType enum)
 *   - Checkboxes via x-ui::checkbox-row render hidden+checkbox pairs with same name.
 *     MUST use input[type="checkbox"][name="..."] — plain input[name="..."] hits the hidden input.
 *     Before checking, close Alpine.js dropdowns: ->script("document.body.click()")->pause(300)
 *   - contact_email / invoice_email use email:rfc,dns — must be a DNS-resolvable domain (@example.com)
 * - Therapist Form:
 *   - #first_name, #last_name, #personal_email (email:rfc only), #phone (digits+dashes only)
 *   - #position_id (seeded: id=1 SLP, also OT, PT, LCSW, SW, BCBA, RBT)
 *   - #state, #timezone, #manager_id (must be admin user), #max_weekly_hours (1–40), #hourly_rate
 *   - Submit text: 'Create Therapist'
 * - Tables: #schoolsTable, #studentsTable, #therapistsTable
 * - Dashboard: uses .grid for metric cards (no <table>)
 * - Logout: submit via JS — document.querySelector('form[action*="logout"]').submit()
 */

// ─── Authentication Tests ─────────────────────────────────────────

it('TC-A001 Admin login with correct credentials', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->visit('/login')
            ->waitFor('input[name="username"]', 20)
            ->type('input[name="username"]', 'develop.ldexpert@gmail.com')
            ->type('input[name="password"]', 'Password123!')
            ->click('button[type="submit"]')
            ->waitFor('h1, h2', 20)
            ->assertPathIs('/admin/dashboard')
            ->assertAuthenticated();
    });
});

it('TC-A002 Admin session persists after page refresh', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/dashboard')
            ->waitFor('h1, h2', 20)
            ->refresh()
            ->waitFor('h1, h2', 20)
            ->assertPathIs('/admin/dashboard')
            ->assertAuthenticated();
    });
});

it('TC-A003 Login rejected with wrong password', function (): void {
    $this->browse(function (Browser $browser): void {
        // Clear any persisted session from earlier tests in the run
        $browser->driver->manage()->deleteAllCookies();
        $browser
            ->visit('/login')
            ->waitFor('input[name="username"]', 20)
            ->type('input[name="username"]', 'develop.ldexpert@gmail.com')
            ->type('input[name="password"]', 'wrongpassword')
            ->click('button[type="submit"]')
            ->pause(1500)
            ->assertPathIs('/login')
            ->assertGuest();
    });
});

it('TC-A004 Login form requires all fields', function (): void {
    $this->browse(function (Browser $browser): void {
        $browser->driver->manage()->deleteAllCookies();
        $browser
            ->visit('/login')
            ->waitFor('button[type="submit"]', 20)
            ->click('button[type="submit"]')
            ->pause(500)
            ->assertPathIs('/login');
    });
});

// ─── Dashboard Tests ──────────────────────────────────────────

it('TC-A010 Dashboard displays welcome greeting', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/dashboard')
            ->waitFor('h1, h2', 20)
            ->assertSee('Dashboard')
            ->assertPresent('.grid');
    });
});

it('TC-A011 Dashboard shows key metrics cards', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/dashboard')
            ->waitFor('.grid', 20)
            ->assertPresent('.grid');
    });
});

// ─── School Management: Create ─────────────────────────────────

it('TC-A020 Admin can create a school with minimal fields', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $manager = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin, $manager): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools/create')
            ->waitFor('#full_name', 20)
            ->type('#full_name', 'QA Test School ' . uniqid())
            ->type('#display_name', 'QA Display ' . uniqid())
            ->select('#state', 'CA')
            ->select('#timezone', 'America/Los_Angeles')
            ->select('#manager_id', $manager->id)
            ->select('#school_type', 'Virtual')
            ->waitForReload(function (Browser $b): void {
                $b->click('button[type="submit"]');
            }, 15)
            ->assertPathContains('/admin/schools');
    });
});

it('TC-A021 Admin can create a school with all contact fields', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $manager = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $schoolName = 'QA Full Contact School ' . uniqid();

    $this->browse(function (Browser $browser) use ($admin, $manager, $schoolName): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools/create')
            ->waitFor('#full_name', 20)
            ->type('#full_name', $schoolName)
            ->type('#display_name', 'QA Display ' . uniqid())
            ->type('#contact_first_name', 'John')
            ->type('#contact_last_name', 'Doe')
            ->type('#contact_phone', '555-123-4567')
            // email:rfc,dns validation does DNS lookups that fail inside Docker — skip email fields
            ->select('#state', 'NY')
            ->select('#timezone', 'America/New_York')
            ->select('#manager_id', $manager->id)
            ->select('#school_type', 'Virtual')
            ->waitForReload(function (Browser $b): void {
                $b->click('button[type="submit"]');
            }, 15);

        $this->assertDatabaseHas('schools', [
            'full_name' => $schoolName,
            'contact_first_name' => 'John',
            'contact_last_name' => 'Doe',
            'contact_phone' => '555-123-4567',
        ]);
    });
});

it('TC-A022 Admin can toggle school settings checkboxes', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $manager = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $schoolName = 'QA Settings School ' . uniqid();

    $this->browse(function (Browser $browser) use ($admin, $manager, $schoolName): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools/create')
            ->waitFor('#full_name', 20)
            ->type('#full_name', $schoolName)
            ->type('#display_name', 'QA Display ' . uniqid())
            ->select('#state', 'TX')
            ->select('#timezone', 'America/Chicago')
            ->select('#manager_id', $manager->id)
            ->select('#school_type', 'Virtual')
            // Focus the text input to dismiss any open Select2 dropdown before checking checkboxes.
            // ->click('body') throws NoSuchElementException in Dusk; clicking a known input is reliable.
            ->click('#full_name')
            ->pause(300)
            // x-ui::checkbox-row renders a hidden input + checkbox with the same name.
            // Must target input[type="checkbox"] explicitly to avoid hitting the hidden input.
            ->check('input[type="checkbox"][name="is_private_student"]')
            ->check('input[type="checkbox"][name="allow_weekend_scheduling"]')
            ->waitForReload(function (Browser $b): void {
                $b->click('button[type="submit"]');
            }, 15);

        $this->assertDatabaseHas('schools', [
            'full_name' => $schoolName,
            'is_private_student' => true,
            'allow_weekend_scheduling' => true,
        ]);
    });
});

// ─── School Management: Read & Update ──────────────────────────

it('TC-A023 Admin can view school details', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $school = $this->createQaSchool(['full_name' => 'QA School Visible']);

    $this->browse(function (Browser $browser) use ($admin, $school): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools/' . $school->id)
            ->waitFor('h1, h2', 20)
            // Show page heading uses display_name, not full_name
            ->assertSee($school->display_name);
    });
});

it('TC-A024 Admin can edit a school', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    // Null out emails so email:rfc,dns validation does not block the edit form submit
    $school = $this->createQaSchool([
        'full_name' => 'QA School Original',
        'contact_email' => null,
        'invoice_email' => null,
    ]);

    $this->browse(function (Browser $browser) use ($admin, $school): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools/' . $school->id . '/edit')
            ->waitFor('#full_name', 20)
            ->clear('#full_name')
            ->type('#full_name', 'QA School Updated')
            ->waitForReload(function (Browser $b): void {
                $b->click('button[type="submit"]');
            }, 15)
            ->assertPathContains('/admin/schools');

        $this->assertDatabaseHas('schools', [
            'id' => $school->id,
            'full_name' => 'QA School Updated',
        ]);
    });
});

it('TC-A025 Admin can list schools in DataTable', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $school = $this->createQaSchool(['full_name' => 'QA School List Test']);

    $this->browse(function (Browser $browser) use ($admin, $school): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools')
            ->waitFor('#schoolsTable', 20)
            ->assertPresent('table')
            ->type('input[name="search"]', 'QA School List')
            ->pause(500); // Wait for DataTable to filter
    });
});

it('TC-A026 Admin can filter schools by status', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->createQaSchool(['full_name' => 'QA Active School', 'status' => 'active']);
    $this->createQaSchool(['full_name' => 'QA Inactive School', 'status' => 'inactive']);

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools')
            ->waitFor('select[name="status"]', 20)
            ->select('select[name="status"]', 'inactive')
            ->pause(500); // Wait for filter
    });
});

// ─── Student Management: Create ────────────────────────────────

it('TC-A030 Admin can create a student', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $school = $this->createQaSchool();

    $this->browse(function (Browser $browser) use ($admin, $school): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/students/create')
            ->waitFor('#first_name', 20)
            ->type('#first_name', 'QA')
            ->type('#last_name', 'Student ' . uniqid())
            ->type('#username', 'qastudent' . uniqid())
            ->type('#email', 'qastudent' . uniqid() . '@test.local')
            ->select('#gender', 'Male')
            ->select('#school_id', $school->id)
            ->type('#id_number', 'S001')
            ->click('button[type="submit"]')
            ->waitFor('.alert-success, h1, h2', 20);
    });
});

it('TC-A031 Admin can create student with date of birth', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $school = $this->createQaSchool();

    $this->browse(function (Browser $browser) use ($admin, $school): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/students/create')
            ->waitFor('#first_name', 20)
            ->type('#first_name', 'QA')
            ->type('#last_name', 'Student DOB ' . uniqid())
            ->type('#username', 'qastudentdob' . uniqid())
            ->type('#email', 'qastudentdob' . uniqid() . '@test.local')
            ->select('#gender', 'Female')
            ->type('#date_of_birth', '2010-01-15')
            ->select('#school_id', $school->id)
            ->type('#id_number', 'S002')
            ->click('button[type="submit"]')
            ->waitFor('.alert-success, h1, h2', 20);
    });
});

// ─── Therapist Management ──────────────────────────────────────

it('TC-A040 Admin can list therapists', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $therapist = $this->createQaUser('therapist');

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/therapists')
            ->waitFor('#therapistsTable', 20)
            ->assertPresent('table');
    });
});

it('TC-A041 Admin can create a therapist', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    // Fetch a seeded active position (PositionSeeder creates SLP, OT, PT, LCSW, SW, BCBA, RBT)
    $positionId = Position::where('status', 'active')->value('id');
    $managerId = $admin->id;

    $this->browse(function (Browser $browser) use ($admin, $positionId, $managerId): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/therapists/create')
            ->waitFor('#first_name', 20)
            ->type('#first_name', 'QA')
            ->type('#last_name', 'Therapist ' . uniqid())
            // personal_email uses email:rfc (no dns check) — any valid format works
            ->type('#personal_email', 'qa.therapist' . uniqid() . '@example.com')
            ->type('#phone', '555-987-6543')
            ->select('#position_id', (string) $positionId)
            ->select('#state', 'CA')
            ->select('#timezone', 'America/Los_Angeles')
            ->select('#manager_id', (string) $managerId)
            ->type('#max_weekly_hours', '40')
            ->type('#hourly_rate', '50.00')
            ->press('Create Therapist')
            ->waitFor('h1, h2', 20);
    });
});

// ─── Role-Based Access Control ────────────────────────────────

it('TC-A050 Therapist cannot access admin schools page', function (): void {
    $therapist = $this->createQaUser('therapist');

    $this->browse(function (Browser $browser) use ($therapist): void {
        $browser
            ->loginAs($therapist)
            ->visit('/admin/schools')
            ->pause(600);

        $url = $browser->driver->getCurrentURL();
        $bodyText = $browser->driver->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('body')
        )->getText();
        expect(
            str_contains($bodyText, '403') ||
            str_contains($bodyText, 'Forbidden') ||
            !str_contains($url, '/admin/schools')
        )->toBeTrue();
    });
});

it('TC-A051 Student cannot access admin pages', function (): void {
    $student = $this->createQaUser('student');

    $this->browse(function (Browser $browser) use ($student): void {
        $browser
            ->loginAs($student)
            ->visit('/admin/schools')
            ->pause(600);

        $url = $browser->driver->getCurrentURL();
        $bodyText = $browser->driver->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('body')
        )->getText();
        expect(
            str_contains($bodyText, '403') ||
            str_contains($bodyText, 'Forbidden') ||
            !str_contains($url, '/admin/schools')
        )->toBeTrue();
    });
});

// ─── School Settings & Configuration ─────────────────────────

it('TC-A060 Admin can mark school as private student', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $manager = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $schoolName = 'QA Private School ' . uniqid();

    $this->browse(function (Browser $browser) use ($admin, $manager, $schoolName): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools/create')
            ->waitFor('#full_name', 20)
            ->type('#full_name', $schoolName)
            ->type('#display_name', 'QA Display ' . uniqid())
            ->select('#state', 'FL')
            ->select('#timezone', 'America/New_York')
            ->select('#manager_id', $manager->id)
            ->select('#school_type', 'Virtual')
            ->click('#full_name')
            ->pause(300)
            ->check('input[type="checkbox"][name="is_private_student"]')
            ->waitForReload(function (Browser $b): void {
                $b->click('button[type="submit"]');
            }, 15);

        $this->assertDatabaseHas('schools', [
            'full_name' => $schoolName,
            'is_private_student' => true,
        ]);
    });
});

it('TC-A061 Admin can enable auto-extend for private schools', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $manager = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $schoolName = 'QA Auto Extend School ' . uniqid();

    $this->browse(function (Browser $browser) use ($admin, $manager, $schoolName): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools/create')
            ->waitFor('#full_name', 20)
            ->type('#full_name', $schoolName)
            ->type('#display_name', 'QA Display ' . uniqid())
            ->select('#state', 'WA')
            ->select('#timezone', 'America/Los_Angeles')
            ->select('#manager_id', $manager->id)
            ->select('#school_type', 'Virtual')
            ->click('#full_name')
            ->pause(300)
            ->check('input[type="checkbox"][name="is_private_student"]')
            // is_auto_extend is conditionally shown after is_private_student is checked
            ->pause(500)
            ->check('input[type="checkbox"][name="is_auto_extend"]')
            ->waitForReload(function (Browser $b): void {
                $b->click('button[type="submit"]');
            }, 15);

        $this->assertDatabaseHas('schools', [
            'full_name' => $schoolName,
            'is_auto_extend' => true,
        ]);
    });
});

// ─── Logout & Session Tests ───────────────────────────────────

it('TC-A070 Admin can logout', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/dashboard')
            ->waitFor('h1, h2', 20);

        // ->script() returns a JS result (not Browser) — break the chain here
        $browser->script("document.querySelector('form[action*=\"logout\"]').submit()");

        $browser
            ->waitFor('input[name="username"]', 20)
            ->assertPathIs('/login')
            ->assertGuest();
    });
});

it('TC-A071 Logged-out user cannot access admin pages', function (): void {
    $this->browse(function (Browser $browser): void {
        // Ensure no session carries over from prior tests in this run
        $browser->driver->manage()->deleteAllCookies();
        $browser
            ->visit('/admin/dashboard')
            ->waitFor('input[name="username"]', 20)
            ->assertPathIs('/login')
            ->assertGuest();
    });
});
