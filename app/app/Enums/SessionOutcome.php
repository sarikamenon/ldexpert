<?php

declare(strict_types=1);

namespace App\Enums;

enum SessionOutcome: string
{
    case SERVICES_ADMINISTERED = 'services_administered';
    case NO_SHOW = 'no_show';
    case BILLABLE_CANCELLATION = 'billable_cancellation';
    case NON_BILLABLE_CANCELLATION_CLIENT = 'non_billable_cancellation_client';
    case NON_BILLABLE_CANCELLATION_PROVIDER = 'non_billable_cancellation_provider';

    public function label(): string
    {
        return match ($this) {
            self::SERVICES_ADMINISTERED => 'Services Administered',
            self::NO_SHOW => 'No Show',
            self::BILLABLE_CANCELLATION => 'Billable Cancellation',
            self::NON_BILLABLE_CANCELLATION_CLIENT => 'Non-Billable Cancellation - Client',
            self::NON_BILLABLE_CANCELLATION_PROVIDER => 'Non-Billable Cancellation - Provider',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $outcome): string => $outcome->value,
            self::cases()
        );
    }

    /** Billable: pay therapist their rate. */
    public function isBillableForTherapist(): bool
    {
        return match ($this) {
            self::SERVICES_ADMINISTERED => true,
            self::NO_SHOW => true,
            self::BILLABLE_CANCELLATION => true,
            self::NON_BILLABLE_CANCELLATION_CLIENT => false,
            self::NON_BILLABLE_CANCELLATION_PROVIDER => false,
        };
    }

    public function isBillableForSchool(): bool
    {
        return match ($this) {
            self::SERVICES_ADMINISTERED => true,
            self::NO_SHOW => true,
            self::BILLABLE_CANCELLATION => true,
            self::NON_BILLABLE_CANCELLATION_CLIENT => false,
            self::NON_BILLABLE_CANCELLATION_PROVIDER => false,
        };
    }

    /** Whether this outcome counts toward THO. All five outcomes count. */
    public function shouldIncludeInTho(): bool
    {
        return true;
    }

    /** Pay therapist their regular rate (used when applying contract logic). */
    public function paysTherapistRate(): bool
    {
        return $this === self::SERVICES_ADMINISTERED;
    }

    /** Pay no-show rate (used when applying contract logic; no-show rate comes from contract). */
    public function paysNoShowRate(): bool
    {
        return match ($this) {
            self::NO_SHOW => true,
            self::BILLABLE_CANCELLATION => true,
            self::SERVICES_ADMINISTERED => false,
            self::NON_BILLABLE_CANCELLATION_CLIENT => false,
            self::NON_BILLABLE_CANCELLATION_PROVIDER => false,
        };
    }
}
