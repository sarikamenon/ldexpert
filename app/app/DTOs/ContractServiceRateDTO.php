<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\RateType;

final class ContractServiceRateDTO
{
    public function __construct(
        public readonly int $serviceId,
        public readonly string $rate,
        public readonly RateType $rateType,
        public readonly string $noShowRate,
        public readonly RateType $noShowRateType,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $noShowRateType = $data['no_show_rate_type'] ?? RateType::HOURLY;

        return new self(
            serviceId: (int) $data['service_id'],
            rate: self::normalizeRate($data['rate']),
            rateType: $data['rate_type'] instanceof RateType
                ? $data['rate_type']
                : RateType::from($data['rate_type']),
            noShowRate: self::normalizeRate($data['no_show_rate'] ?? 0),
            noShowRateType: $noShowRateType instanceof RateType
                ? $noShowRateType
                : RateType::from($noShowRateType),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'service_id' => $this->serviceId,
            'rate' => $this->rate,
            'rate_type' => $this->rateType->value,
            'no_show_rate' => $this->noShowRate,
            'no_show_rate_type' => $this->noShowRateType->value,
        ];
    }

    private static function normalizeRate(int|float|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
