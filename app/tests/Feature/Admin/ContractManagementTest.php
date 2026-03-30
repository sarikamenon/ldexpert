<?php

declare(strict_types=1);

use App\Enums\ContractStatus;
use App\Enums\RateType;
use App\Enums\SchoolStatus;
use App\Enums\ServiceStatus;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\Service;
use App\Models\TherapistContract;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function adminUser(): User
{
    return User::factory()->admin()->create();
}

function activeSchool(): School
{
    return School::factory()->create([
        'status' => SchoolStatus::ACTIVE->value,
    ]);
}

function activeService(): Service
{
    return Service::factory()->create([
        'status' => ServiceStatus::ACTIVE->value,
    ]);
}

function therapistProfile(): TherapistProfile
{
    return TherapistProfile::factory()->create();
}

it('allows admin to view school contracts index', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.contracts.schools.index'))
        ->assertOk()
        ->assertSee('School Contracts');
});

it('creates a school contract with services', function () {
    $admin = adminUser();
    $school = activeSchool();
    $services = Service::factory()->count(2)->create([
        'status' => ServiceStatus::ACTIVE->value,
    ]);

    $payload = [
        'school_id' => $school->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
        'notes' => 'Quarterly agreement',
        'services' => [
            [
                'service_id' => $services[0]->id,
                'rate' => '125.00',
                'rate_type' => RateType::HOURLY->value,
                'no_show_rate' => '25.00',
                'no_show_rate_type' => RateType::HOURLY->value,
            ],
            [
                'service_id' => $services[1]->id,
                'rate' => '400.00',
                'rate_type' => RateType::FLAT->value,
                'no_show_rate' => '50.00',
                'no_show_rate_type' => RateType::FLAT->value,
            ],
        ],
    ];

    $this->actingAs($admin)
        ->post(route('admin.contracts.schools.store'), $payload)
        ->assertRedirect(route('admin.contracts.schools.index'))
        ->assertSessionHas('status', 'School contract created successfully.');

    $this->assertDatabaseHas('school_contracts', [
        'school_id' => $school->id,
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $this->assertDatabaseHas('school_contract_services', [
        'rate_type' => RateType::HOURLY->value,
        'rate' => '125.00',
    ]);
});

it('prevents overlapping active school contracts', function () {
    $admin = adminUser();
    $school = activeSchool();
    $service = activeService();

    $existing = SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);
    $existing->services()->create([
        'service_id' => $service->id,
        'rate' => '100.00',
        'rate_type' => RateType::HOURLY->value,
        'no_show_rate' => 25.00,
        'no_show_rate_type' => RateType::HOURLY->value,
    ]);

    $payload = [
        'school_id' => $school->id,
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addMonths(2)->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
        'services' => [
            [
                'service_id' => $service->id,
                'rate' => '150.00',
                'rate_type' => RateType::HOURLY->value,
                'no_show_rate' => '30.00',
                'no_show_rate_type' => RateType::HOURLY->value,
            ],
        ],
    ];

    $this->actingAs($admin)
        ->from(route('admin.contracts.schools.create'))
        ->post(route('admin.contracts.schools.store'), $payload)
        ->assertRedirect(route('admin.contracts.schools.create'))
        ->assertSessionHasErrors('start_date');
});

it('creates a therapist contract with services', function () {
    $admin = adminUser();
    $therapist = therapistProfile();
    $service = activeService();

    $payload = [
        'therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(6)->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
        'services' => [
            [
                'service_id' => $service->id,
                'rate' => '90.00',
                'rate_type' => RateType::HOURLY->value,
                'no_show_rate' => '20.00',
                'no_show_rate_type' => RateType::HOURLY->value,
            ],
        ],
    ];

    $this->actingAs($admin)
        ->post(route('admin.contracts.therapists.store'), $payload)
        ->assertRedirect(route('admin.contracts.therapists.index'))
        ->assertSessionHas('status', 'Therapist contract created successfully.');

    $this->assertDatabaseHas('therapist_contracts', [
        'therapist_id' => $therapist->id,
        'status' => ContractStatus::ACTIVE->value,
    ]);
});

it('blocks overlapping therapist contracts when activating', function () {
    $admin = adminUser();
    $therapist = therapistProfile();
    $service = activeService();

    $activeContract = TherapistContract::create([
        'therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);
    $activeContract->services()->create([
        'service_id' => $service->id,
        'rate' => '85.00',
        'rate_type' => RateType::HOURLY->value,
        'no_show_rate' => 20.00,
        'no_show_rate_type' => RateType::HOURLY->value,
    ]);

    $inactiveContract = TherapistContract::create([
        'therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::INACTIVE->value,
    ]);
    $inactiveContract->services()->create([
        'service_id' => $service->id,
        'rate' => '95.00',
        'rate_type' => RateType::HOURLY->value,
        'no_show_rate' => 25.00,
        'no_show_rate_type' => RateType::HOURLY->value,
    ]);

    $this->actingAs($admin)
        ->patchJson(route('admin.contracts.therapists.status', $inactiveContract), [
            'status' => ContractStatus::ACTIVE->value,
        ])
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
        ]);
});

// ─── Document upload ──────────────────────────────────────────────────────────

it('stores a document when creating a school contract', function () {
    Storage::fake('s3');

    $admin = adminUser();
    $school = activeSchool();
    $service = activeService();

    $payload = [
        'school_id' => $school->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
        'services' => [[
            'service_id' => $service->id,
            'rate' => '100.00',
            'rate_type' => RateType::HOURLY->value,
            'no_show_rate' => null,
            'no_show_rate_type' => null,
        ]],
        'document' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
    ];

    $this->actingAs($admin)
        ->post(route('admin.contracts.schools.store'), $payload)
        ->assertRedirect(route('admin.contracts.schools.index'));

    $contract = SchoolContract::where('school_id', $school->id)->first();

    expect($contract)->not->toBeNull();
    expect($contract->document_name)->toBe('contract.pdf');
    expect($contract->document_path)->not->toBeNull();
    expect($contract->document_mime_type)->toBe('application/pdf');
    expect($contract->document_size)->toBeGreaterThan(0);
});

it('stores a document when creating a therapist contract', function () {
    Storage::fake('s3');

    $admin = adminUser();
    $therapist = therapistProfile();
    $service = activeService();

    $payload = [
        'therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
        'services' => [[
            'service_id' => $service->id,
            'rate' => '90.00',
            'rate_type' => RateType::HOURLY->value,
            'no_show_rate' => null,
            'no_show_rate_type' => null,
        ]],
        'document' => UploadedFile::fake()->create('therapist-contract.pdf', 200, 'application/pdf'),
    ];

    $this->actingAs($admin)
        ->post(route('admin.contracts.therapists.store'), $payload)
        ->assertRedirect(route('admin.contracts.therapists.index'));

    $contract = TherapistContract::where('therapist_id', $therapist->id)->first();

    expect($contract)->not->toBeNull();
    expect($contract->document_name)->toBe('therapist-contract.pdf');
    expect($contract->document_path)->not->toBeNull();
});

it('replaces a document when updating a school contract with a new file', function () {
    Storage::fake('s3');

    $admin = adminUser();
    $school = activeSchool();
    $service = activeService();

    $contract = SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
        'document_path' => 'contract-documents/2026/01/old_file.pdf',
        'document_name' => 'old_file.pdf',
        'document_mime_type' => 'application/pdf',
        'document_size' => 1000,
    ]);
    $contract->services()->create([
        'service_id' => $service->id,
        'rate' => '100.00',
        'rate_type' => RateType::HOURLY->value,
        'no_show_rate' => null,
        'no_show_rate_type' => null,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.contracts.schools.update', $contract), [
            'school_id' => $school->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
            'services' => [[
                'service_id' => $service->id,
                'rate' => '120.00',
                'rate_type' => RateType::HOURLY->value,
                'no_show_rate' => null,
                'no_show_rate_type' => null,
            ]],
            'document' => UploadedFile::fake()->create('new_contract.pdf', 150, 'application/pdf'),
        ])
        ->assertRedirect(route('admin.contracts.schools.index'));

    $contract->refresh();

    expect($contract->document_name)->toBe('new_contract.pdf');
    expect($contract->document_path)->not->toBe('contract-documents/2026/01/old_file.pdf');
});

it('removes a document when remove_document is set on a school contract', function () {
    Storage::fake('s3');

    $admin = adminUser();
    $school = activeSchool();
    $service = activeService();

    $contract = SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
        'document_path' => 'contract-documents/2026/01/to_remove.pdf',
        'document_name' => 'to_remove.pdf',
        'document_mime_type' => 'application/pdf',
        'document_size' => 500,
    ]);
    $contract->services()->create([
        'service_id' => $service->id,
        'rate' => '100.00',
        'rate_type' => RateType::HOURLY->value,
        'no_show_rate' => null,
        'no_show_rate_type' => null,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.contracts.schools.update', $contract), [
            'school_id' => $school->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
            'remove_document' => '1',
            'services' => [[
                'service_id' => $service->id,
                'rate' => '100.00',
                'rate_type' => RateType::HOURLY->value,
                'no_show_rate' => null,
                'no_show_rate_type' => null,
            ]],
        ])
        ->assertRedirect(route('admin.contracts.schools.index'));

    $contract->refresh();

    expect($contract->document_path)->toBeNull();
    expect($contract->document_name)->toBeNull();
});

it('rejects a document exceeding 10MB on a school contract', function () {
    Storage::fake('s3');

    $admin = adminUser();
    $school = activeSchool();
    $service = activeService();

    $this->actingAs($admin)
        ->post(route('admin.contracts.schools.store'), [
            'school_id' => $school->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
            'services' => [[
                'service_id' => $service->id,
                'rate' => '100.00',
                'rate_type' => RateType::HOURLY->value,
                'no_show_rate' => null,
                'no_show_rate_type' => null,
            ]],
            'document' => UploadedFile::fake()->create('huge.pdf', 11000, 'application/pdf'),
        ])
        ->assertSessionHasErrors('document');
});

it('returns 404 when downloading a missing school contract document', function () {
    $admin = adminUser();
    $school = activeSchool();

    $contract = SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.contracts.schools.download-document', $contract))
        ->assertNotFound();
});

it('returns 404 when downloading a missing therapist contract document', function () {
    $admin = adminUser();
    $therapist = therapistProfile();

    $contract = TherapistContract::create([
        'therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.contracts.therapists.download-document', $contract))
        ->assertNotFound();
});
