<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\ContractStatus;
use App\Enums\SSAStatus;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\SchoolContractService;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\TherapistContract;
use App\Models\TherapistContractService;
use App\Models\TherapistProfile;
use App\Models\User;
use Carbon\Carbon;

trait CreatesDomainFixtures
{
    protected function createSchoolWithContractAndService(): array
    {
        $school = School::factory()->create();
        $service = Service::factory()->create();

        $startDate = Carbon::today()->subMonth();
        $endDate = Carbon::today()->addMonth();

        $schoolContract = SchoolContract::create([
            'school_id' => $school->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);

        $schoolContractService = SchoolContractService::create([
            'school_contract_id' => $schoolContract->id,
            'service_id' => $service->id,
            'rate' => 150,
            'rate_type' => 'H',
        ]);

        return [
            'school' => $school,
            'service' => $service,
            'schoolContract' => $schoolContract,
            'schoolContractService' => $schoolContractService,
        ];
    }

    protected function createTherapistWithContractForService(Service $service): array
    {
        $therapistProfile = TherapistProfile::factory()->create();
        $therapistUser = $therapistProfile->user;

        $startDate = Carbon::today()->subMonth();
        $endDate = Carbon::today()->addMonth();

        $therapistContract = TherapistContract::create([
            'therapist_id' => $therapistProfile->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);

        $therapistContractService = TherapistContractService::create([
            'therapist_contract_id' => $therapistContract->id,
            'service_id' => $service->id,
            'rate' => 100,
            'rate_type' => 'H',
        ]);

        return [
            'therapistUser' => $therapistUser,
            'therapistProfile' => $therapistProfile,
            'therapistContract' => $therapistContract,
            'therapistContractService' => $therapistContractService,
        ];
    }

    protected function createActiveSSA(User $student, User $therapist, Service $service, School $school): ServiceSupportAgreement
    {
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);

        $startDate = Carbon::today()->subWeek();
        $endDate = Carbon::today()->addWeeks(4);

        return ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'status' => SSAStatus::ACTIVE->value,
        ]);
    }
}
