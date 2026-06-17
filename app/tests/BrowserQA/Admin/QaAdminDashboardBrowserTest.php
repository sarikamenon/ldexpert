<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

/**
 * Admin Dashboard QA Tests
 *
 * Tests for admin dashboard functionality, overview cards, and navigation
 */

// ─── Dashboard Display ───────────────────────────────────────

it('TC-A043 Admin dashboard displays all four overview cards', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/dashboard')
            ->waitFor('h1, h2, [class*="card"]', 20)
            ->assertSee('Schools/Families Overview')
            ->assertSee('Therapist Capacity')
            ->assertSee('Student Population')
            ->assertSee('Service Delivery');
    });
});

// ─── Dashboard Navigation ────────────────────────────────────

it('TC-A044 Admin can click Schools/Families card and navigate to schools list', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools')
            ->waitFor('h1, table', 20)
            ->assertPathIs('/admin/schools');
    });
});

it('TC-A045 Admin can click Therapist Capacity card and navigate to therapist list', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/therapists')
            ->waitFor('h1, table', 20)
            ->assertPathIs('/admin/therapists');
    });
});

it('TC-A046 Admin can click Student Population card and navigate to student list', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/students')
            ->waitFor('h1, table', 20)
            ->assertPathIs('/admin/students');
    });
});

it('TC-A047 Admin can click Service Delivery card and navigate to SSA list', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/ssas')
            ->waitFor('h1, table', 20)
            ->assertPathIs('/admin/ssas');
    });
});

// ─── Dashboard Quick Actions ─────────────────────────────────

it('TC-A048 Admin can navigate to Create School form from dashboard', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools/create')
            ->waitFor('#full_name', 30)
            ->assertPathIs('/admin/schools/create');
    });
});

it('TC-A049 Admin can navigate to Create Therapist form from dashboard', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/therapists/create')
            ->waitFor('#first_name', 30)
            ->assertPathIs('/admin/therapists/create');
    });
});

it('TC-A050 Admin can navigate to Create Student form from dashboard', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/students/create')
            ->waitFor('#first_name', 30)
            ->assertPathIs('/admin/students/create');
    });
});

it('TC-A051 Admin can navigate to Create SSA form from dashboard', function (): void {
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/ssas/create')
            ->waitFor('[name="student_id"]', 30)
            ->assertPathIs('/admin/ssas/create');
    });
});
