<?php

declare(strict_types=1);

use App\DTOs\UpdateScheduleDTO;

it('maps occurrence times and the regenerated flag from the request array', function (): void {
    $dto = UpdateScheduleDTO::fromArray([
        'schedule_date' => '2026-07-06',
        'start_time' => '09:00',
        'occurrence_dates' => ['2026-07-06', '2026-07-07'],
        'occurrence_start_times' => ['09:00', '11:00'],
        'occurrence_end_times' => ['10:00', '12:00'],
        'occurrences_regenerated' => '1',
    ]);

    expect($dto->occurrenceDates)->toBe(['2026-07-06', '2026-07-07'])
        ->and($dto->occurrenceStartTimes)->toBe(['09:00', '11:00'])
        ->and($dto->occurrenceEndTimes)->toBe(['10:00', '12:00'])
        ->and($dto->occurrencesRegenerated)->toBeTrue();
});

it('defaults occurrence times to null and regenerated to false when absent', function (): void {
    $dto = UpdateScheduleDTO::fromArray([
        'schedule_date' => '2026-07-06',
        'start_time' => '09:00',
    ]);

    expect($dto->occurrenceStartTimes)->toBeNull()
        ->and($dto->occurrenceEndTimes)->toBeNull()
        ->and($dto->occurrencesRegenerated)->toBeFalse();
});

it('treats empty occurrence time arrays as null', function (): void {
    $dto = UpdateScheduleDTO::fromArray([
        'schedule_date' => '2026-07-06',
        'start_time' => '09:00',
        'occurrence_start_times' => [],
        'occurrence_end_times' => [],
    ]);

    expect($dto->occurrenceStartTimes)->toBeNull()
        ->and($dto->occurrenceEndTimes)->toBeNull();
});

it('maps edit_scope to editScope', function (string $scope): void {
    $dto = UpdateScheduleDTO::fromArray([
        'schedule_date' => '2026-07-06',
        'start_time' => '09:00',
        'edit_scope' => $scope,
    ]);

    expect($dto->editScope)->toBe($scope);
})->with(['occurrence', 'series']);

it('leaves editScope null when edit_scope is absent or empty', function (mixed $value): void {
    $data = ['schedule_date' => '2026-07-06', 'start_time' => '09:00'];
    if ($value !== 'ABSENT') {
        $data['edit_scope'] = $value;
    }

    expect(UpdateScheduleDTO::fromArray($data)->editScope)->toBeNull();
})->with(['ABSENT', '']);
