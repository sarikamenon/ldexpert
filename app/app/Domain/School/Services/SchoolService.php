<?php

declare(strict_types=1);

namespace App\Domain\School\Services;

use App\Domain\Billing\Services\BillingScheduleService;
use App\Domain\Billing\Services\BillingSettingsService;
use App\Domain\Billing\Services\BillingStartDateResolver;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\DTOs\BillingScheduleDTO;
use App\DTOs\ChangeSchoolStatusDTO;
use App\DTOs\CreateSchoolDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\SchoolFilterDTO;
use App\DTOs\UpdateSchoolDTO;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Models\BillingSetting;
use App\Models\School;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SchoolService
{
    public function __construct(
        private readonly SchoolRepositoryInterface $schools,
        private readonly BillingScheduleService $billingScheduleService,
        private readonly BillingSettingsService $billingSettingsService,
        private readonly BillingStartDateResolver $billingStartDateResolver,
    ) {}

    /** @return LengthAwarePaginator<int, School> */
    public function listSchools(SchoolFilterDTO $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->schools->paginate($filters, $perPage);
    }

    /**
     * @return array{recordsTotal:int,recordsFiltered:int,rows:\Illuminate\Database\Eloquent\Collection<int,School>}
     */
    public function listForDataTables(SchoolFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->schools->listForDataTables($filters, $params);
    }

    public function createSchool(CreateSchoolDTO $dto): School
    {
        /** @var School */
        return $this->wrapWrite(function () use ($dto) {
            $school = $this->schools->create($dto);

            $this->createSchoolBillingSchedule($school);

            return $school;
        });
    }

    /**
     * Seed a school_invoice billing schedule on creation. Private-student schools
     * bill in advance (prepaid) from the Advance Invoice Defaults and start the
     * 1st of next month; other schools bill standard (postpaid) from the Standard
     * Invoice Defaults and start the 1st of the current month.
     */
    private function createSchoolBillingSchedule(School $school): void
    {
        $settings = $this->billingSettingsService->getSettings();
        $isPrivate = $school->is_private_student === true;
        $billingStartDate = $this->billingStartDateResolver->forSchool($isPrivate, now());

        $config = $isPrivate
            ? $this->advanceDefaults($settings)
            : $this->standardInvoiceDefaults($settings);

        $this->billingScheduleService->createSchedule(BillingScheduleDTO::fromArray([
            'schedulable_type' => School::class,
            'schedulable_id' => $school->id,
            'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
            'billing_start_date' => $billingStartDate->toDateString(),
            ...$config,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function advanceDefaults(BillingSetting $settings): array
    {
        return [
            'billing_mode' => BillingMode::ADVANCE->value,
            'frequency' => $settings->advance_default_frequency->value,
            'generation_day_type' => $settings->advance_default_generation_day_type->value,
            'generation_day_of_week' => $settings->advance_default_generation_day_of_week,
            'generation_delay_days' => $settings->advance_default_delay_days,
            'payment_terms_days' => $settings->advance_default_payment_terms_days,
            'auto_generate' => $settings->advance_default_auto_generate,
            'auto_send' => $settings->advance_default_auto_send,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function standardInvoiceDefaults(BillingSetting $settings): array
    {
        return [
            'billing_mode' => BillingMode::STANDARD->value,
            'frequency' => $settings->standard_default_frequency->value,
            'generation_day_type' => $settings->standard_default_generation_day_type->value,
            'generation_day_of_week' => $settings->standard_default_generation_day_of_week,
            'generation_delay_days' => $settings->standard_default_delay_days,
            'payment_terms_days' => $settings->standard_default_payment_terms_days,
            'auto_generate' => $settings->standard_default_auto_generate,
            'auto_send' => false,
        ];
    }

    public function updateSchool(School $school, UpdateSchoolDTO $dto): School
    {
        /** @var School */
        return $this->wrapWrite(function () use ($school, $dto) {
            return $this->schools->update($school, $dto);
        });
    }

    public function changeStatus(School $school, ChangeSchoolStatusDTO $dto): School
    {
        /** @var School */
        return $this->wrapWrite(function () use ($school, $dto) {
            $updatedSchool = $this->schools->changeStatus($school, $dto);

            return $updatedSchool;
        });
    }

    /** @return array{total: int, active: int, inactive: int} */
    public function summaryMetrics(): array
    {
        return $this->schools->metrics();
    }

    /** @return Collection<int, School> */
    public function exportSchools(SchoolFilterDTO $filters): Collection
    {
        return $this->schools->export($filters);
    }

    /** @return Collection<int, School> */
    public function listActiveForSelect(): Collection
    {
        return $this->schools->listActiveForSelect();
    }

    private function wrapWrite(callable $callback): mixed
    {
        try {
            return DB::transaction(static fn () => $callback());
        } catch (Throwable $exception) {
            Log::error('School write operation failed', [
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
