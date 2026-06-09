<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Domain\Billing\Services\BillingScheduleService;
use App\Domain\Billing\Services\BillingSettingsService;
use App\Domain\Billing\Services\BillingStartDateResolver;
use App\Domain\Therapist\Repositories\TherapistRepositoryInterface;
use App\DTOs\BillingScheduleDTO;
use App\DTOs\ChangeTherapistStatusDTO;
use App\DTOs\CreateTherapistDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\TherapistFilterDTO;
use App\DTOs\UpdateTherapistDTO;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Enums\UserStatus;
use App\Mail\WelcomeTherapistMail;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class TherapistService
{
    public function __construct(
        private readonly TherapistRepositoryInterface $repository,
        private readonly BillingScheduleService $billingScheduleService,
        private readonly BillingSettingsService $billingSettingsService,
        private readonly BillingStartDateResolver $billingStartDateResolver,
        private readonly ScheduleService $scheduleService,
    ) {}

    public function create(CreateTherapistDTO $dto): TherapistProfile
    {
        $userData = $dto->toUserArray();
        $userData['password'] = Hash::make($dto->password);

        // Profile creation and the seeded billing schedule must commit (or roll
        // back) together — billing config is core to a new therapist.
        $profile = DB::transaction(function () use ($dto, $userData): TherapistProfile {
            $profile = $this->repository->create(
                $userData,
                $dto->toProfileArray(0) // user_id will be set in repository
            );

            $this->createTherapistBillingSchedule($profile->user_id);

            return $profile;
        });

        // Send welcome email
        try {
            Mail::to($dto->personalEmail)->send(
                new WelcomeTherapistMail(
                    name: $dto->firstName.' '.$dto->lastName,
                    email: $dto->personalEmail,
                    plainPassword: $dto->password
                )
            );
        } catch (\Throwable $e) {
            Log::error('TherapistService: failed to send welcome email', [
                'email' => $dto->personalEmail,
                'error' => $e->getMessage(),
            ]);
        }

        return $profile;
    }

    /**
     * Seed a therapist_bill billing schedule from the Standard Billing Defaults,
     * anchored on a billing_start_date computed from the creation date.
     */
    private function createTherapistBillingSchedule(int $therapistUserId): void
    {
        $settings = $this->billingSettingsService->getSettings();
        $billingStartDate = $this->billingStartDateResolver->forTherapist(now());

        $this->billingScheduleService->createSchedule(BillingScheduleDTO::fromArray([
            'schedulable_type' => User::class,
            'schedulable_id' => $therapistUserId,
            'schedule_type' => BillingScheduleType::THERAPIST_BILL->value,
            'billing_mode' => BillingMode::STANDARD->value,
            'frequency' => $settings->default_frequency->value,
            'generation_day_type' => $settings->default_generation_day_type->value,
            'generation_day_of_week' => $settings->default_generation_day_of_week,
            'generation_delay_days' => $settings->default_delay_days,
            'payment_terms_days' => $settings->default_payment_terms_days,
            'auto_generate' => $settings->default_auto_generate,
            'auto_send' => $settings->default_auto_send,
            'billing_start_date' => $billingStartDate->toDateString(),
        ]));
    }

    public function update(User $user, UpdateTherapistDTO $dto): TherapistProfile
    {
        return $this->repository->update(
            $user,
            $dto->toUserArray(),
            $dto->toProfileArray()
        );
    }

    public function changeStatus(User $user, ChangeTherapistStatusDTO $dto): User
    {
        return DB::transaction(function () use ($user, $dto): User {
            $updated = $this->repository->changeStatus($user, $dto);

            // Deactivating a therapist removes their future scheduled sessions so they
            // are not left on the calendar with no one to deliver them.
            if ($dto->status === UserStatus::INACTIVE->value) {
                $deletedCount = $this->scheduleService->deleteTherapistFutureSchedules($user);

                Log::info('Deactivated therapist: removed future schedules.', [
                    'therapist_id' => $user->id,
                    'deleted_count' => $deletedCount,
                ]);
            }

            return $updated;
        });
    }

    /** @return Collection<int, User> */
    public function list(TherapistFilterDTO $filters): Collection
    {
        return $this->repository->list($filters);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: \Illuminate\Database\Eloquent\Collection<int, \App\Models\User>}
     */
    public function listForDataTables(TherapistFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($filters, $params);
    }

    /** @return array<string, int> */
    public function getMetrics(?string $status = null): array
    {
        return $this->repository->getMetrics($status);
    }

    /** @return Collection<int, User> */
    public function export(TherapistFilterDTO $filters): Collection
    {
        return $this->repository->export($filters);
    }

    public function find(int $id): ?TherapistProfile
    {
        return $this->repository->find($id);
    }

    /** @return Collection<int, TherapistProfile> */
    public function listActiveProfilesForSelect(): Collection
    {
        return $this->repository->listActiveProfilesForSelect();
    }

    public function countTherapistsBySchool(int $schoolId): int
    {
        return $this->repository->countTherapistsBySchool($schoolId);
    }

    /** @return Collection<int, User> */
    public function listActiveTherapistsBySchool(int $schoolId): Collection
    {
        return $this->repository->listActiveTherapistsBySchool($schoolId);
    }

    /** @return Collection<int, User> */
    public function listActiveTherapists(): Collection
    {
        return $this->repository->listActiveTherapists();
    }

    /** @return Collection<int, User> */
    public function listTherapistsByStudent(int $studentId): Collection
    {
        return $this->repository->listTherapistsByStudent($studentId);
    }

    /** @return LengthAwarePaginator<int, User> */
    public function paginateTherapistsByStudent(int $studentId, ?string $search = null, ?string $status = null, ?int $positionId = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateTherapistsByStudent($studentId, $search, $status, $positionId, $perPage);
    }

    public function findProfileByUserId(int $userId): ?TherapistProfile
    {
        return $this->repository->findProfileByUserId($userId);
    }
}
