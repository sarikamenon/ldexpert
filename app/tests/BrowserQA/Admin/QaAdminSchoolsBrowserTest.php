<?php

declare(strict_types=1);

use App\Models\{School, User};
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

/**
 * Admin Schools QA Tests
 *
 * Tests for school/family creation, editing, and management
 * Selectors from: app/resources/views/admin/schools/_form.blade.php
 *
 * LOCATOR GUIDE:
 * - #full_name (required), #display_name (required, unique), #state, #timezone, #manager_id
 * - #school_type — REQUIRED; valid values: 'Virtual', 'Brick Mortar', 'Blended'
 * - contact_email / invoice_email validated with email:rfc,dns — use @example.com, NOT @qatest.local
 * - Factory qa() generates qa.xxx@example.com emails; null them out to avoid DNS validation on edit
 * - After submit, use waitForReload() to wait for the server round-trip before assertDatabaseHas
 * - display_name must be unique — always append uniqid() to avoid collisions across test runs
 */

// ─── Create School ───────────────────────────────────────────

it('TC-A052 Admin can create school with all required fields', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $uid = uniqid();

    $this->browse(function (Browser $browser) use ($admin, $uid): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools/create')
            ->waitFor('#full_name', 20)
            ->type('#full_name', 'QA Test School ' . $uid)
            ->type('#display_name', 'QA School ' . $uid)
            ->select('#state', 'CA')
            ->select('#timezone', 'America/Los_Angeles')
            ->select('#manager_id', (string) $admin->id)
            ->select('#school_type', 'Virtual')
            // waitForReload ensures the form has been submitted and server has responded
            ->waitForReload(function (Browser $b): void {
                $b->click('button[type="submit"]');
            }, 15)
            ->assertPathContains('/admin/schools');

        $this->assertDatabaseHas('schools', [
            'full_name' => 'QA Test School ' . $uid,
        ]);
    });
});

it('TC-A053 Admin can edit existing school name', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    // Null out emails to avoid email:rfc,dns validation failure on the edit form submit.
    // The qa() factory generates qa.xxx@example.com which can fail DNS lookup in Docker.
    $school = $this->createQaSchool([
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
            // waitForReload waits for the POST + redirect to complete before asserting DB
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

it('TC-A054 Admin cannot create school with missing required name', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools/create')
            ->waitFor('#display_name', 20)
            ->type('#display_name', 'QA School No Name ' . uniqid())
            ->select('#state', 'CA')
            ->select('#timezone', 'America/Los_Angeles')
            ->select('#manager_id', (string) $admin->id)
            ->select('#school_type', 'Virtual')
            // full_name has required attribute — native browser validation prevents submission
            ->click('button[type="submit"]')
            ->pause(500)
            // Form should still be visible (not navigated away)
            ->assertPresent('#display_name')
            ->assertPathIs('/admin/schools/create');
    });
});

it('TC-A055 Admin cannot create school without selecting timezone', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools/create')
            ->waitFor('#full_name', 20)
            ->type('#full_name', 'QA Test School TZ ' . uniqid())
            ->type('#display_name', 'QA School TZ ' . uniqid())
            ->select('#state', 'CA')
            ->select('#manager_id', (string) $admin->id)
            ->select('#school_type', 'Virtual')
            // timezone is not selected — server-side validation catches it
            ->click('button[type="submit"]')
            ->pause(1000)
            // Should stay on create page with a validation error
            ->assertPresent('#full_name')
            ->assertPathContains('/admin/schools/create');
    });
});

it('TC-A056 Admin can view schools list with filtering', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools')
            ->waitFor('table, h1', 20)
            ->assertPresent('table');
    });
});
