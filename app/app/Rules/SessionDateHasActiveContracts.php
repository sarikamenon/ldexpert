<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Contract\Repositories\SchoolContractRepositoryInterface;
use App\Domain\Contract\Repositories\TherapistContractRepositoryInterface;
use App\Domain\Therapist\Repositories\TherapistRepositoryInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that both a therapist contract and a school contract
 * cover the given session date.
 */
final class SessionDateHasActiveContracts implements ValidationRule
{
    public function __construct(
        private readonly int $therapistUserId,
        private readonly ?int $schoolId,
    ) {}

    /**
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $sessionDate = $value;

        $this->validateTherapistContract($sessionDate, $fail);
        $this->validateSchoolContract($sessionDate, $fail);
    }

    /**
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    private function validateTherapistContract(string $sessionDate, Closure $fail): void
    {
        $therapistRepository = app(TherapistRepositoryInterface::class);
        $contractRepository = app(TherapistContractRepositoryInterface::class);

        $profile = $therapistRepository->findProfileByUserId($this->therapistUserId);

        if (! $profile) {
            $fail('Therapist profile not found. Please contact your administrator.');

            return;
        }

        $contract = $contractRepository->findActiveContractForDate($profile->id, $sessionDate);

        if (! $contract) {
            $fail('No active therapist contract covers this session date. Please contact your administrator to verify your contract dates.');
        }
    }

    /**
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    private function validateSchoolContract(string $sessionDate, Closure $fail): void
    {
        if (! $this->schoolId) {
            return;
        }

        $contractRepository = app(SchoolContractRepositoryInterface::class);

        $contract = $contractRepository->findActiveContractForDate($this->schoolId, $sessionDate);

        if (! $contract) {
            $fail('No active school contract covers this session date. Please contact your administrator.');
        }
    }
}
