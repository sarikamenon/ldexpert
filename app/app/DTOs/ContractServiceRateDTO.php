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
        public readonly string $noRate,
        public readonly RateType $noRateType,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            serviceId: (int) $data['service_id'],
            rate: self::normalizeRate($data['rate']),
            rateType: $data['rate_type'] instanceof RateType
                ? $data['rate_type']
                : RateType::from($data['rate_type']),
            noRate: self::normalizeRate($data['no_rate']),
            noRateType: $data['no_rate_type'] instanceof RateType
                ? $data['no_rate_type']
                : RateType::from($data['no_rate_type']),
        );
    }

    public function toArray(): array
    {
        return [
            'service_id' => $this->serviceId,
            'rate' => $this->rate,
            'rate_type' => $this->rateType->value,
            'no_rate' => $this->noRate,
            'no_rate_type' => $this->noRateType->value,
        ];
    }

    private static function normalizeRate(int|float|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
