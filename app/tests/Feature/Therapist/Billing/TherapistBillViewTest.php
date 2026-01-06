<?php

use App\Models\TherapistBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows therapist to view own bills', function () {
    $therapist = User::factory()->therapist()->create();
    $bill = TherapistBill::factory()->create([
        'therapist_id' => $therapist->id,
    ]);

    $response = $this->actingAs($therapist)
        ->get(route('therapist.billing.index'));

    $response->assertOk()
        ->assertSee('My Bills')
        ->assertViewIs('therapist.billing.index')
        ->assertViewHas('bills');
});

it('prevents therapist from viewing other therapist bills', function () {
    $therapist1 = User::factory()->therapist()->create();
    $therapist2 = User::factory()->therapist()->create();
    $bill = TherapistBill::factory()->create([
        'therapist_id' => $therapist2->id,
    ]);

    $response = $this->actingAs($therapist1)
        ->get(route('therapist.billing.show', $bill));

    $response->assertForbidden();
});

it('allows therapist to view own bill details', function () {
    $therapist = User::factory()->therapist()->create();
    $bill = TherapistBill::factory()->create([
        'therapist_id' => $therapist->id,
    ]);

    $response = $this->actingAs($therapist)
        ->get(route('therapist.billing.show', $bill));

    $response->assertOk()
        ->assertSee($bill->bill_number)
        ->assertViewIs('therapist.billing.show');
});

it('allows therapist to download own bill pdf', function () {
    $therapist = User::factory()->therapist()->create();
    $bill = TherapistBill::factory()->create([
        'therapist_id' => $therapist->id,
    ]);

    $response = $this->actingAs($therapist)
        ->get(route('therapist.billing.download', $bill));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});
