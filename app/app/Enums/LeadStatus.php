<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadStatus: string
{
    // Active pipeline stages
    case INQUIRY = 'inquiry';
    case CONTACTED = 'contacted';
    case FOLLOW_UP = 'follow_up';
    case EVALUATION = 'evaluation';

    // Positive terminal
    case ENROLLED = 'enrolled';

    // Negative terminals
    case DECLINED = 'declined';
    case NOT_ELIGIBLE = 'not_eligible';
    case NO_RESPONSE = 'no_response';
    case WITHDRAWN = 'withdrawn';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::INQUIRY => 'Inquiry',
            self::CONTACTED => 'Contacted',
            self::FOLLOW_UP => 'Follow Up',
            self::EVALUATION => 'Evaluation',
            self::ENROLLED => 'Enrolled',
            self::DECLINED => 'Declined',
            self::NOT_ELIGIBLE => 'Not Eligible',
            self::NO_RESPONSE => 'No Response',
            self::WITHDRAWN => 'Withdrawn',
            self::CLOSED => 'Closed',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, self::activeStatuses(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, self::terminalStatuses(), true);
    }

    public function isNegativeTerminal(): bool
    {
        return in_array($this, self::negativeTerminalStatuses(), true);
    }

    /**
     * @return array<int, self>
     */
    public static function activeStatuses(): array
    {
        return [self::INQUIRY, self::CONTACTED, self::FOLLOW_UP, self::EVALUATION];
    }

    /**
     * @return array<int, self>
     */
    public static function terminalStatuses(): array
    {
        return [self::ENROLLED, ...self::negativeTerminalStatuses()];
    }

    /**
     * @return array<int, self>
     */
    public static function negativeTerminalStatuses(): array
    {
        return [self::DECLINED, self::NOT_ELIGIBLE, self::NO_RESPONSE, self::WITHDRAWN, self::CLOSED];
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }
}
