<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Makeup\Services;

use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupAvailabilityRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\DTOs\Schedule\Makeup\StoreMakeupAvailabilityDTO;
use App\Models\ScheduleMakeupAvailability;
use App\Models\User;

final class ScheduleMakeupAvailabilityService
{
    public function __construct(
        private readonly ScheduleMakeupAvailabilityRepositoryInterface $repository,
        private readonly UserTimezoneService $timezoneService,
    ) {}

    public function create(User $therapist, StoreMakeupAvailabilityDTO $dto): ScheduleMakeupAvailability
    {
        $startUtc = $this->timezoneService->parseUserLocalToUtc($dto->date.' '.$dto->startTime.':00', $therapist);
        $endUtc   = $this->timezoneService->parseUserLocalToUtc($dto->date.' '.$dto->endTime.':00', $therapist);

        return $this->repository->create(
            $therapist,
            $startUtc->toDateString(),
            $startUtc->format('H:i'),
            $endUtc->format('H:i'),
            $dto->notes,
        );
    }

    public function delete(ScheduleMakeupAvailability $window): void
    {
        $this->repository->delete($window);
    }
}
