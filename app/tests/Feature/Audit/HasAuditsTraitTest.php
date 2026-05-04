<?php

declare(strict_types=1);

use App\Domain\Audit\Services\AuditBatchContext;
use App\Domain\Audit\Services\AuditRecorder;
use App\Enums\SSAStatus;
use App\Models\Audit;
use App\Models\ServiceSupportAgreement;
use App\Models\User;

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

    $audit = Audit::query()->where('auditable_id', $ssa->id)->sole();
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
