<?php

declare(strict_types=1);

namespace App\DTOs\Schedule\Makeup;

use App\Enums\ScheduleMakeupRequestStatus;
use App\Enums\ScheduleMakeupRespondedByType;
use App\Enums\ScheduleMakeupResponseSource;
use Carbon\CarbonImmutable;

/**
 * Input transport for recording a parent or therapist response on a make-up
 * request row. The handler resolves the actor (parent user via the schedule
 * chain, or current therapist) and builds this DTO before calling the repo.
 */
final class RecordMakeupResponseDTO
{
    public function __construct(
        public readonly int $requestId,
        public readonly ScheduleMakeupRequestStatus $status,
        public readonly ScheduleMakeupRespondedByType $respondedByType,
        public readonly ScheduleMakeupResponseSource $responseSource,
        public readonly ?int $respondedByUserId,
        public readonly CarbonImmutable $respondedAt,
        public readonly ?string $declineReason = null,
    ) {}

    public static function parentRequest(int $requestId, int $parentUserId, ?CarbonImmutable $now = null): self
    {
        return new self(
            requestId: $requestId,
            status: ScheduleMakeupRequestStatus::REQUESTED,
            respondedByType: ScheduleMakeupRespondedByType::PARENT,
            responseSource: ScheduleMakeupResponseSource::EMAIL_LINK,
            respondedByUserId: $parentUserId,
            respondedAt: $now ?? CarbonImmutable::now(),
        );
    }

    public static function parentDecline(
        int $requestId,
        int $parentUserId,
        ?string $reason = null,
        ?CarbonImmutable $now = null,
    ): self {
        return new self(
            requestId: $requestId,
            status: ScheduleMakeupRequestStatus::DECLINED,
            respondedByType: ScheduleMakeupRespondedByType::PARENT,
            responseSource: ScheduleMakeupResponseSource::EMAIL_LINK,
            respondedByUserId: $parentUserId,
            respondedAt: $now ?? CarbonImmutable::now(),
            declineReason: $reason,
        );
    }

    public static function therapistDecline(
        int $requestId,
        int $therapistUserId,
        ?string $reason = null,
        ?CarbonImmutable $now = null,
    ): self {
        return new self(
            requestId: $requestId,
            status: ScheduleMakeupRequestStatus::DECLINED,
            respondedByType: ScheduleMakeupRespondedByType::THERAPIST,
            responseSource: ScheduleMakeupResponseSource::THERAPIST_MANUAL,
            respondedByUserId: $therapistUserId,
            respondedAt: $now ?? CarbonImmutable::now(),
            declineReason: $reason,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'status' => $this->status->value,
            'responded_by_type' => $this->respondedByType->value,
            'response_source' => $this->responseSource->value,
            'responded_by_user_id' => $this->respondedByUserId,
            'responded_at' => $this->respondedAt->toDateTimeString(),
            'decline_reason' => $this->declineReason,
        ];
    }
}
