<?php

declare(strict_types=1);

use App\Domain\Finance\Services\LedgerService;
use App\Enums\SessionLogStatus;
use App\Enums\TransactionType;
use App\Models\School;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows admin to view the account tab for a private-student school', function () {
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create(['is_private_student' => true]);

    $response = $this->actingAs($admin)
        ->get(route('admin.schools.show', ['school' => $school, 'tab' => 'account']));

    $response->assertOk()
        ->assertViewIs('admin.schools.show')
        ->assertViewHas('activeTab', 'account')
        ->assertViewHas('accountSummary')
        ->assertViewHas('datatableUrl')
        ->assertViewHas('scheduleDetailsUrl');
});

it('redirects to dashboard when account tab is requested for a non-private school', function () {
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create(['is_private_student' => false]);

    $this->actingAs($admin)
        ->get(route('admin.schools.show', ['school' => $school, 'tab' => 'account']))
        ->assertRedirect(route('admin.schools.show', ['school' => $school, 'tab' => 'dashboard']));
});

it('forbids non-admin from accessing the account tab', function () {
    $therapist = User::factory()->therapist()->create();
    $school = School::factory()->create(['is_private_student' => true]);

    $this->actingAs($therapist)
        ->get(route('admin.schools.show', ['school' => $school, 'tab' => 'account']))
        ->assertForbidden();
});

it('returns merged charges and adjustments via the account data endpoint', function () {
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create(['is_private_student' => true]);
    $therapist = User::factory()->therapist()->create();

    SessionLog::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_school' => true,
        'school_invoice_amount' => 150.00,
    ]);

    /** @var LedgerService $ledger */
    $ledger = app(LedgerService::class);
    $ledger->createEntry(
        ledgerableType: School::class,
        ledgerableId: $school->id,
        type: TransactionType::PAYMENT_RECEIVED,
        amount: 50.00,
        recordedAt: now()->subDay(),
        referenceType: null,
        referenceId: null,
        notes: null,
        recordedById: $admin->id,
    );

    $response = $this->actingAs($admin)
        ->post(route('admin.schools.account.data', ['school' => $school]), [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]);

    $response->assertOk()
        ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

    $payload = $response->json();
    expect($payload['recordsTotal'])->toBe(2);
});

it('forbids access to the account data endpoint for a non-private school', function () {
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create(['is_private_student' => false]);

    $this->actingAs($admin)
        ->post(route('admin.schools.account.data', ['school' => $school]), [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ])
        ->assertForbidden();
});

it('forbids non-admin from posting to the account data endpoint', function () {
    $therapist = User::factory()->therapist()->create();
    $school = School::factory()->create(['is_private_student' => true]);

    $this->actingAs($therapist)
        ->post(route('admin.schools.account.data', ['school' => $school]), [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ])
        ->assertForbidden();
});
