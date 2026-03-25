<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Support\Collection;

final class AdvanceReconciliationDTO
{
    /**
     * @param  Collection<int, InvoiceLineItemDTO>  $adjustmentLines
     * @param  Collection<int, InvoiceLineItemDTO>  $advanceLines
     */
    public function __construct(
        public readonly Collection $adjustmentLines,
        public readonly Collection $advanceLines,
        public readonly float $adjustmentTotal,
        public readonly float $advanceTotal,
        public readonly float $carryForwardFromPrevious,
        public readonly float $netTotal,
        public readonly float $newCarryForward,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'adjustment_lines' => $this->adjustmentLines->map(fn (InvoiceLineItemDTO $line): array => $line->toArray())->all(),
            'advance_lines' => $this->advanceLines->map(fn (InvoiceLineItemDTO $line): array => $line->toArray())->all(),
            'adjustment_total' => $this->adjustmentTotal,
            'advance_total' => $this->advanceTotal,
            'carry_forward_from_previous' => $this->carryForwardFromPrevious,
            'net_total' => $this->netTotal,
            'new_carry_forward' => $this->newCarryForward,
        ];
    }
}
