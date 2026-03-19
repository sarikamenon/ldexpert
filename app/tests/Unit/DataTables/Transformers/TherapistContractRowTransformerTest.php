<?php

declare(strict_types=1);

use App\DataTables\Transformers\TherapistContractRowTransformer;
use App\Enums\ContractStatus;
use App\Enums\RateType;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\TherapistContract;
use App\Models\TherapistProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates therapist link pointing to the user route not the profile id', function () {
    $therapist = TherapistProfile::factory()->create([
        'first_name' => 'Kinsey',
        'last_name' => 'Millhone',
    ]);

    $contract = TherapistContract::create([
        'therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $contract->load(['therapist.user', 'services']);

    $row = TherapistContractRowTransformer::transform($contract);

    $therapistCell = $row[1];
    $expectedUrl = route('admin.therapists.show', $therapist->user);

    expect($therapistCell)->toContain($expectedUrl);
    expect($therapistCell)->toContain('Kinsey');
    expect($therapistCell)->toContain('Millhone');
});

it('renders correct status badge class for active contracts', function () {
    $therapist = TherapistProfile::factory()->create();

    $contract = TherapistContract::create([
        'therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $contract->load(['therapist.user', 'services']);

    $row = TherapistContractRowTransformer::transform($contract);
    $statusBadge = $row[5];

    expect($statusBadge)->toContain('bg-success/10');
    expect($statusBadge)->toContain('Active');
});

it('renders correct status badge class for inactive contracts', function () {
    $therapist = TherapistProfile::factory()->create();

    $contract = TherapistContract::create([
        'therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::INACTIVE->value,
    ]);

    $contract->load(['therapist.user', 'services']);

    $row = TherapistContractRowTransformer::transform($contract);
    $statusBadge = $row[5];

    expect($statusBadge)->toContain('bg-danger/10');
    expect($statusBadge)->toContain('Inactive');
});

it('shows service count from attached services', function () {
    $therapist = TherapistProfile::factory()->create();
    $services = Service::factory()->count(3)->create([
        'status' => ServiceStatus::ACTIVE->value,
    ]);

    $contract = TherapistContract::create([
        'therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    foreach ($services as $service) {
        $contract->services()->create([
            'service_id' => $service->id,
            'rate' => '100.00',
            'rate_type' => RateType::HOURLY->value,
            'no_show_rate' => '25.00',
            'no_show_rate_type' => RateType::HOURLY->value,
        ]);
    }

    $contract->load(['therapist.user', 'services']);

    $row = TherapistContractRowTransformer::transform($contract);

    expect($row[4])->toBe('3');
});

it('shows dash when therapist relation is null', function () {
    $therapist = TherapistProfile::factory()->create();

    $contract = TherapistContract::create([
        'therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $contract->load(['services']);
    // Simulate a missing therapist by unsetting the loaded relation
    $contract->setRelation('therapist', null);

    $row = TherapistContractRowTransformer::transform($contract);

    expect($row[1])->toBe('—');
});
