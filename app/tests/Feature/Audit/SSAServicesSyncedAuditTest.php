<?php

declare(strict_types=1);

use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\DTOs\CreateSSADTO;
use App\Enums\Role;
use App\Enums\ServiceFrequency;
use App\Models\Audit;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\User;

test('creating an SSA via the repository produces a services_synced audit', function (): void {
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
