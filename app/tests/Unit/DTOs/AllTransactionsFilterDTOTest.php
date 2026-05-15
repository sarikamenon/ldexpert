<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\AllTransactionsFilterDTO;
use App\Enums\CashDirection;
use PHPUnit\Framework\TestCase;

final class AllTransactionsFilterDTOTest extends TestCase
{
    public function test_all_nulls_when_array_is_empty(): void
    {
        $dto = AllTransactionsFilterDTO::fromArray([]);

        $this->assertNull($dto->dateFrom);
        $this->assertNull($dto->dateTo);
        $this->assertNull($dto->direction);
        $this->assertNull($dto->schoolId);
        $this->assertNull($dto->therapistId);
    }

    public function test_populates_all_fields_from_valid_array(): void
    {
        $dto = AllTransactionsFilterDTO::fromArray([
            'filter_date_from' => '2025-01-01',
            'filter_date_to' => '2025-12-31',
            'filter_direction' => 'income',
            'filter_school_id' => '3',
            'filter_therapist_id' => '7',
        ]);

        $this->assertSame('2025-01-01', $dto->dateFrom);
        $this->assertSame('2025-12-31', $dto->dateTo);
        $this->assertSame(CashDirection::INCOME, $dto->direction);
        $this->assertSame(3, $dto->schoolId);
        $this->assertSame(7, $dto->therapistId);
    }

    public function test_empty_string_direction_resolves_to_null(): void
    {
        $dto = AllTransactionsFilterDTO::fromArray(['filter_direction' => '']);

        $this->assertNull($dto->direction);
    }

    public function test_income_direction_value_resolves_to_income_enum(): void
    {
        $dto = AllTransactionsFilterDTO::fromArray(['filter_direction' => 'income']);

        $this->assertSame(CashDirection::INCOME, $dto->direction);
    }

    public function test_expense_direction_value_resolves_to_expense_enum(): void
    {
        $dto = AllTransactionsFilterDTO::fromArray(['filter_direction' => 'expense']);

        $this->assertSame(CashDirection::EXPENSE, $dto->direction);
    }

    public function test_unknown_direction_value_resolves_to_null(): void
    {
        $dto = AllTransactionsFilterDTO::fromArray(['filter_direction' => 'bogus']);

        $this->assertNull($dto->direction);
    }

    public function test_empty_string_school_id_resolves_to_null(): void
    {
        $dto = AllTransactionsFilterDTO::fromArray(['filter_school_id' => '']);

        $this->assertNull($dto->schoolId);
    }

    public function test_numeric_string_school_id_is_cast_to_int(): void
    {
        $dto = AllTransactionsFilterDTO::fromArray(['filter_school_id' => '5']);

        $this->assertSame(5, $dto->schoolId);
    }

    public function test_empty_string_therapist_id_resolves_to_null(): void
    {
        $dto = AllTransactionsFilterDTO::fromArray(['filter_therapist_id' => '']);

        $this->assertNull($dto->therapistId);
    }

    public function test_numeric_string_therapist_id_is_cast_to_int(): void
    {
        $dto = AllTransactionsFilterDTO::fromArray(['filter_therapist_id' => '12']);

        $this->assertSame(12, $dto->therapistId);
    }

    public function test_empty_string_date_from_resolves_to_null(): void
    {
        $dto = AllTransactionsFilterDTO::fromArray(['filter_date_from' => '']);

        $this->assertNull($dto->dateFrom);
    }

    public function test_empty_string_date_to_resolves_to_null(): void
    {
        $dto = AllTransactionsFilterDTO::fromArray(['filter_date_to' => '']);

        $this->assertNull($dto->dateTo);
    }
}
