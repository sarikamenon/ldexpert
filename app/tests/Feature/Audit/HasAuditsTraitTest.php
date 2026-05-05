<?php

declare(strict_types=1);

use App\Domain\Audit\Services\AuditBatchContext;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Position\Repositories\PositionRepositoryInterface;
use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\DTOs\CreatePositionDTO;
use App\DTOs\CreateSSADTO;
use App\DTOs\UpdatePositionDTO;
use App\Enums\Role;
use App\Enums\ServiceFrequency;
use App\Enums\SSAStatus;
use App\Models\AdminProfile;
use App\Models\Audit;
use App\Models\Concerns\HasAudits;
use App\Models\ParentProfile;
use App\Models\Position;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\Setting;
use App\Models\StudentProfile;
use App\Models\TherapistProfile;
use App\Models\User;

// ============================================================================
// Trait mechanics — dirty diff, deleted snapshot, type normalization, batching
// ============================================================================

test('updating an auditable model records one audit row with only changed fields', function (): void {
    $ssa = ServiceSupportAgreement::factory()->create([
        'minutes_per_session' => 30,
    ]);

    Audit::query()->delete();

    $ssa->update([
        'minutes_per_session' => 45,
        'additional_notes' => 'updated note',
    ]);

    $audit = Audit::query()->where('auditable_id', $ssa->id)->sole();

    expect($audit->event)->toBe('updated')
        ->and($audit->auditable_type)->toBe(ServiceSupportAgreement::class)
        ->and(array_keys($audit->old_values ?? []))->toEqualCanonicalizing(['minutes_per_session', 'additional_notes'])
        ->and($audit->old_values['minutes_per_session'])->toBe(30)
        ->and($audit->new_values['minutes_per_session'])->toBe(45);
});

test('deleting an auditable model records a snapshot in old_values with null new_values', function (): void {
    $ssa = ServiceSupportAgreement::factory()->create();

    Audit::query()->delete();

    $ssa->delete();

    $audit = Audit::query()->where('auditable_id', $ssa->id)->where('event', 'deleted')->sole();

    expect($audit->new_values)->toBeNull()
        ->and($audit->old_values)->toBeArray()
        ->and($audit->old_values)->toHaveKey('student_id')
        ->and($audit->old_values)->not->toHaveKey('id')
        ->and($audit->old_values)->not->toHaveKey('created_at');
});

test('saving a date-cast column without changing it does not produce an audit', function (): void {
    $ssa = ServiceSupportAgreement::factory()->create();

    Audit::query()->delete();

    // Re-set the same date with a fresh Carbon instance — would be a different object
    // identity but the same wall-clock value. The trait must normalize and skip this.
    $ssa->start_date = $ssa->start_date->copy();
    $ssa->save();

    expect(Audit::query()->where('auditable_id', $ssa->id)->count())->toBe(0);
});

test('date-cast columns are stored as Y-m-d without a UTC instant suffix', function (): void {
    $ssa = ServiceSupportAgreement::factory()->create([
        'start_date' => '2026-01-15',
    ]);

    Audit::query()->delete();

    $ssa->update(['start_date' => '2026-02-20']);

    $audit = Audit::query()->where('auditable_id', $ssa->id)->sole();

    expect($audit->old_values['start_date'])->toBe('2026-01-15')
        ->and($audit->new_values['start_date'])->toBe('2026-02-20');
});

test('enum-cast columns are serialized via ->value in audit values', function (): void {
    $ssa = ServiceSupportAgreement::factory()->create([
        'status' => SSAStatus::PENDING->value,
    ]);

    Audit::query()->delete();

    $ssa->update(['status' => SSAStatus::ACTIVE->value]);

    $audit = Audit::query()->where('auditable_id', $ssa->id)->sole();

    expect($audit->old_values['status'])->toBe(SSAStatus::PENDING->value)
        ->and($audit->new_values['status'])->toBe(SSAStatus::ACTIVE->value);
});

test('audit captures the authenticated user as created_by', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $ssa = ServiceSupportAgreement::factory()->create();
    Audit::query()->delete();

    $this->actingAs($admin);
    $ssa->update(['additional_notes' => 'with auth']);

    $audit = Audit::query()->where('auditable_id', $ssa->id)->where('auditable_type', ServiceSupportAgreement::class)->sole();
    expect($audit->created_by)->toBe($admin->id);
});

test('audit batch context populates batch_uuid on subsequent audits', function (): void {
    $ssa = ServiceSupportAgreement::factory()->create();
    Audit::query()->delete();

    $context = app(AuditBatchContext::class);
    $uuid = $context->start('import');

    $ssa->update(['additional_notes' => 'batched']);
    $context->clear();

    $audit = Audit::query()->where('auditable_id', $ssa->id)->sole();

    expect($audit->batch_uuid)->toBe($uuid)
        ->and($audit->source)->toBe('import');
});

test('AuditRecorder writes a custom-event audit attached to the parent model', function (): void {
    $ssa = ServiceSupportAgreement::factory()->create();
    Audit::query()->delete();

    /** @var AuditRecorder $recorder */
    $recorder = app(AuditRecorder::class);

    $recorder->record(
        auditable: $ssa,
        event: 'services_synced',
        oldValues: ['service_ids' => [1]],
        newValues: ['service_ids' => [1, 2]],
    );

    $audit = Audit::query()->where('auditable_id', $ssa->id)->sole();

    expect($audit->event)->toBe('services_synced')
        ->and($audit->auditable_type)->toBe(ServiceSupportAgreement::class)
        ->and($audit->old_values['service_ids'])->toBe([1])
        ->and($audit->new_values['service_ids'])->toBe([1, 2]);
});

// ============================================================================
// Pivot / related-row sync pattern (parent emits services_synced)
// ============================================================================

test('creating an SSA via the repository produces a services_synced audit on the parent', function (): void {
    $student = User::factory()->create(['role' => Role::STUDENT->value]);
    $service = Service::factory()->create();

    /** @var SSARepositoryInterface $repo */
    $repo = app(SSARepositoryInterface::class);

    $dto = new CreateSSADTO(
        studentId: $student->id,
        primaryServiceId: $service->id,
        startDate: now()->addDay()->format('Y-m-d'),
        endDate: now()->addDays(30)->format('Y-m-d'),
        minutesPerSession: 30,
        frequency: ServiceFrequency::WEEKLY,
        sessionsPerFrequency: 1,
        calculatedMinutes: 120,
        adjustedMinutes: null,
        adjustmentNotes: null,
        additionalNotes: null,
        thoMinutes: 120,
        assignedTherapistId: null,
    );

    $ssa = $repo->create($dto);

    $syncedAudit = Audit::query()
        ->where('auditable_type', ServiceSupportAgreement::class)
        ->where('auditable_id', $ssa->id)
        ->where('event', 'services_synced')
        ->sole();

    expect($syncedAudit->old_values['service_ids'])->toBe([])
        ->and($syncedAudit->new_values['service_ids'])->toBe([$service->id]);
});

test('creating a Position with service IDs produces a services_synced audit on the parent', function (): void {
    $services = Service::factory()->count(2)->create();

    /** @var PositionRepositoryInterface $repo */
    $repo = app(PositionRepositoryInterface::class);
    Audit::query()->delete();

    $position = $repo->create(new CreatePositionDTO(
        name: 'Senior Therapist',
        serviceIds: $services->pluck('id')->all(),
    ));

    $audit = Audit::query()
        ->where('auditable_type', Position::class)
        ->where('auditable_id', $position->id)
        ->where('event', 'services_synced')
        ->sole();

    $expected = $services->pluck('id')->sort()->values()->all();
    expect($audit->old_values['service_ids'])->toBe([])
        ->and($audit->new_values['service_ids'])->toBe($expected);
});

test('updating Position service IDs produces a services_synced audit reflecting old vs new', function (): void {
    $existingServices = Service::factory()->count(2)->create();
    $position = Position::factory()->create();
    $position->services()->attach($existingServices->pluck('id')->all());

    $newService = Service::factory()->create();

    /** @var PositionRepositoryInterface $repo */
    $repo = app(PositionRepositoryInterface::class);
    Audit::query()->delete();

    $repo->update($position, new UpdatePositionDTO(
        name: $position->name,
        serviceIds: [$newService->id],
    ));

    $audit = Audit::query()
        ->where('auditable_type', Position::class)
        ->where('auditable_id', $position->id)
        ->where('event', 'services_synced')
        ->sole();

    $oldExpected = $existingServices->pluck('id')->sort()->values()->all();
    expect($audit->old_values['service_ids'])->toBe($oldExpected)
        ->and($audit->new_values['service_ids'])->toBe([$newService->id]);
});

// ============================================================================
// Per-model opt-in coverage
// ============================================================================

test('User opt-in audits updates and excludes password + password_change_prompted_at', function (): void {
    $user = User::factory()->create(['name' => 'Old Name']);
    Audit::query()->delete();

    $user->update([
        'name' => 'New Name',
        'password_change_prompted_at' => now(),
    ]);

    $audit = Audit::query()->where('auditable_type', User::class)->where('auditable_id', $user->id)->sole();

    expect(array_keys($audit->old_values ?? []))->toEqualCanonicalizing(['name'])
        ->and($audit->old_values)->not->toHaveKey('password')
        ->and($audit->old_values)->not->toHaveKey('password_change_prompted_at')
        ->and($audit->new_values['name'])->toBe('New Name');
});

test('Service opt-in audits updates', function (): void {
    $service = Service::factory()->create(['name' => 'Speech']);
    Audit::query()->delete();

    $service->update(['name' => 'Speech Therapy']);

    $audit = Audit::query()->where('auditable_type', Service::class)->where('auditable_id', $service->id)->sole();
    expect($audit->old_values['name'])->toBe('Speech')
        ->and($audit->new_values['name'])->toBe('Speech Therapy');
});

test('Position opt-in audits updates', function (): void {
    $position = Position::factory()->create();
    Audit::query()->delete();

    $position->update(['name' => 'Senior Therapist']);

    $audit = Audit::query()->where('auditable_type', Position::class)->where('auditable_id', $position->id)->sole();
    expect($audit->new_values['name'])->toBe('Senior Therapist');
});

test('Setting opt-in audits updates', function (): void {
    $setting = Setting::query()->create([
        'key' => 'site.name',
        'value' => 'Old',
        'type' => 'string',
        'group' => 'general',
        'is_encrypted' => false,
    ]);
    Audit::query()->delete();

    $setting->update(['value' => 'New']);

    $audit = Audit::query()->where('auditable_type', Setting::class)->where('auditable_id', $setting->id)->sole();
    expect($audit->old_values['value'])->toBe('Old')
        ->and($audit->new_values['value'])->toBe('New');
});

test('StudentProfile opt-in audits updates', function (): void {
    $student = User::factory()->create(['role' => 'student']);
    $profile = StudentProfile::query()->create([
        'user_id' => $student->id,
        'first_name' => 'Old',
    ]);
    Audit::query()->delete();

    $profile->update(['first_name' => 'New']);

    $audit = Audit::query()->where('auditable_type', StudentProfile::class)->where('auditable_id', $profile->id)->sole();
    expect($audit->new_values['first_name'])->toBe('New');
});

test('TherapistProfile, ParentProfile, AdminProfile opt in via the trait', function (): void {
    expect(in_array(HasAudits::class, class_uses_recursive(TherapistProfile::class), true))->toBeTrue()
        ->and(in_array(HasAudits::class, class_uses_recursive(ParentProfile::class), true))->toBeTrue()
        ->and(in_array(HasAudits::class, class_uses_recursive(AdminProfile::class), true))->toBeTrue();
});
