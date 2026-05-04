<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\TherapistBillPayment;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication to view pay stubs', function () {
    $response = $this->get(route('therapist.finance.pay-stub.index'));

    $response->assertRedirect(route('login'));
});

it('forbids admin from accessing therapist pay stub page', function () {
    $admin = User::factory()->create(['role' => Role::ADMIN]);

    $response = $this->actingAs($admin)->get(route('therapist.finance.pay-stub.index'));

    $response->assertForbidden();
});

it('allows therapist to view their pay stub page', function () {
    $therapist = User::factory()->therapist()->create();

    $response = $this->actingAs($therapist)->get(route('therapist.finance.pay-stub.index'));

    $response->assertOk()
        ->assertViewIs('therapist.finance.pay-stub.index')
        ->assertSee('My Pay Stubs');
});

it('shows empty state when therapist has no payments', function () {
    $therapist = User::factory()->therapist()->create();

    $response = $this->actingAs($therapist)->get(route('therapist.finance.pay-stub.index'));

    $response->assertOk()->assertSee('No pay stubs found');
});

it('shows year row with total for therapist payments', function () {
    $therapist = User::factory()->therapist()->create();
    TherapistProfile::factory()->create(['user_id' => $therapist->id, 'hourly_rate' => 50.00]);

    TherapistBillPayment::factory()->create([
        'therapist_id' => $therapist->id,
        'paid_at' => '2026-02-01',
        'amount' => 250.00,
    ]);
    TherapistBillPayment::factory()->create([
        'therapist_id' => $therapist->id,
        'paid_at' => '2026-03-01',
        'amount' => 250.00,
    ]);

    $response = $this->actingAs($therapist)->get(route('therapist.finance.pay-stub.index'));

    $response->assertOk()
        ->assertSee('2026')
        ->assertSee('$500.00');
});

it('shows multiple year rows when therapist has payments in different years', function () {
    $therapist = User::factory()->therapist()->create();
    TherapistProfile::factory()->create(['user_id' => $therapist->id, 'hourly_rate' => 50.00]);

    TherapistBillPayment::factory()->create(['therapist_id' => $therapist->id, 'paid_at' => '2025-06-01']);
    TherapistBillPayment::factory()->create(['therapist_id' => $therapist->id, 'paid_at' => '2026-01-15']);

    $response = $this->actingAs($therapist)->get(route('therapist.finance.pay-stub.index'));

    $response->assertOk()
        ->assertSee('2026')
        ->assertSee('2025');
});

it('does not show other therapist payments', function () {
    $therapist = User::factory()->therapist()->create();
    TherapistProfile::factory()->create(['user_id' => $therapist->id, 'hourly_rate' => 50.00]);

    TherapistBillPayment::factory()->create(['paid_at' => '2026-02-01', 'amount' => 999.00]);

    $response = $this->actingAs($therapist)->get(route('therapist.finance.pay-stub.index'));

    $response->assertOk()->assertDontSee('$999.00');
});

it('requires authentication to download pay stub', function () {
    $response = $this->get(route('therapist.finance.pay-stub.download', ['year' => 2026]));

    $response->assertRedirect(route('login'));
});

it('therapist can download their own pay stub pdf', function () {
    $therapist = User::factory()->therapist()->create();
    TherapistProfile::factory()->create(['user_id' => $therapist->id, 'hourly_rate' => 50.00]);

    TherapistBillPayment::factory()->create(['therapist_id' => $therapist->id, 'paid_at' => '2026-02-01']);

    $response = $this->actingAs($therapist)
        ->get(route('therapist.finance.pay-stub.download', ['year' => 2026]));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('download validates year is required', function () {
    $therapist = User::factory()->therapist()->create();

    $response = $this->actingAs($therapist)
        ->get(route('therapist.finance.pay-stub.download'));

    $response->assertSessionHasErrors(['year']);
});
