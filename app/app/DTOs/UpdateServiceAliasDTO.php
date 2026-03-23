<?php

declare(strict_types=1);

namespace App\DTOs;

final class UpdateServiceAliasDTO
{
    public function __construct(
        public readonly string $source,
        public readonly string $externalName,
        public readonly int $serviceId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            source: $data['source'],
            externalName: trim((string) $data['external_name']),
            serviceId: (int) $data['service_id'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'external_name' => $this->externalName,
            'service_id' => $this->serviceId,
        ];
    }
}
