<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\InvoiceStatus;

final class InvoiceFilterDTO
{
    public function __construct(
        public readonly ?int $schoolId,
        public readonly ?InvoiceStatus $status,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
        public readonly ?string $invoiceNumber,
        public readonly int $perPage = 15,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            schoolId: isset($data['school_id']) && $data['school_id'] !== null
                ? (int) $data['school_id']
                : null,
            status: isset($data['status']) && $data['status'] !== null
                ? InvoiceStatus::from($data['status'])
                : null,
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null,
            invoiceNumber: $data['invoice_number'] ?? null,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'school_id' => $this->schoolId,
            'status' => $this->status?->value,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'invoice_number' => $this->invoiceNumber,
            'per_page' => $this->perPage,
        ];
    }
}
