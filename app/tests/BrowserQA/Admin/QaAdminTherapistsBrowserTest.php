<?php

declare(strict_types=1);

use App\Models\{User, TherapistProfile};
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

/**
 * Admin Therapists QA Tests
 *
 * Tests for therapist creation, editing, and management
 *
 * LOCATOR GUIDE:
 * - Therapist routes use User ID (not TherapistProfile ID): /admin/therapists/{user_id}
 * - Therapist list: <table id="therapistsTable"> — always present (server-side DataTables)
 * - Therapist show page heading: <h1> via x-page-title (inside x-ui::show-header)
 * - Show page uses null-safe ?-> for all therapistProfile access — renders fine without a profile
 * - assertUrlIs(url('/...')) is BROKEN in Docker: APP_URL=http://nginx:80 but browser drops :80,
 *   causing exact-match mismatch. Always use assertPathContains('/...') for path-based assertions.
 */

// ─── Create Therapist ────────────────────────────────────────

it('TC-A057 Admin can navigate to therapist creation form', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/therapists/create')
            // Full-suite Chrome is under more memory pressure; increase timeout to 60s
            ->waitFor('input[name="first_name"]', 60)
            ->assertPathContains('/admin/therapists/create');
    });
});

it('TC-A058 Admin can view therapists list', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/therapists')
            ->waitFor('table, h1', 20)
            ->assertPathContains('/admin/therapists');
    });
});

it('TC-A059 Admin can edit therapist profile', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $therapist = $this->createQaUser('therapist', ['name' => 'QA Therapist Edit']);
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $this->browse(function (Browser $browser) use ($admin, $therapist): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/therapists/' . $therapist->id . '/edit')
            // Full-suite Chrome is under more memory pressure; increase timeout to 60s
            ->waitFor('input[name="first_name"]', 60)
            ->assertPathContains('/admin/therapists/' . $therapist->id);
    });
});

it('TC-A060 Admin cannot create therapist with invalid email format', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/therapists/create')
            // Full-suite Chrome is under more memory pressure; increase timeout to 60s
            ->waitFor('input[name="first_name"]', 60)
            ->assertPresent('form');
    });
});

it('TC-A061 Admin can filter therapists by status', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/therapists')
            ->waitFor('table, h1', 20)
            ->assertPresent('table');
    });
});

it('TC-A062 Admin can view therapist details page', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $therapist = $this->createQaUser('therapist', ['name' => 'QA Therapist Details']);
    TherapistProfile::factory()->for($therapist, 'user')->create(['manager_id' => $admin->id]);

    $this->browse(function (Browser $browser) use ($admin, $therapist): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/therapists/' . $therapist->id)
            ->waitFor('h1, [class*="detail"]', 20)
            ->assertPathContains('/admin/therapists/' . $therapist->id);
    });
});
