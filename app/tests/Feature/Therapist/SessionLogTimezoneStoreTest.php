<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\ContractStatus;
use App\Enums\RateType;
use App\Enums\SessionOutcome;
use App\Enums\SSAStatus;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\SchoolContractService;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\StudentProfile;
use App\Models\TherapistContract;
use App\Models\TherapistContractService;
use App\Models\TherapistProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogTimezoneStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public function test_therapist_in_pacific_time_stores_session_in_utc(): void
    {
        // Therapist in PT submits a 9am local session — should land in DB at 16:00 UTC (PDT).
        $therapist = $this->makeTherapistInTimezone('America/Los_Angeles');
        [$ssa, $service, $school] = $this->seedSsaWithContracts($therapist, '2026-04-30');

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.store'), [
                'student_id' => $ssa->student_id,
                'ssa_id' => $ssa->id,
                'service_id' => $service->id,
                'session_date' => '2026-04-30',
                'start_time' => '2026-04-30 09:00:00',
                'end_time' => '2026-04-30 10:00:00',
                'duration_minutes' => 60,
                'notes' => str_repeat('a', 30),
                'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
                '_token' => csrf_token(),
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        /** @var SessionLog $log */
        $log = SessionLog::where('therapist_id', $therapist->id)->latest('id')->firstOrFail();

        // 9am PDT (UTC-7 on 2026-04-30) == 16:00 UTC same day.
        $this->assertSame('2026-04-30', $log->session_date->format('Y-m-d'));
        $this->assertSame('2026-04-30 16:00:00', $log->start_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-30 17:00:00', $log->end_time->format('Y-m-d H:i:s'));

        // Display via the model helper round-trips back to therapist-local 9am.
        $localStart = $log->localStart($therapist->therapistProfile->timezone);
        $this->assertSame('2026-04-30 09:00:00', $localStart->format('Y-m-d H:i:s'));
    }

    public function test_late_evening_pacific_session_rolls_session_date_to_next_utc_day(): void
    {
        // 11pm PT on April 30 == 06:00 UTC on May 1. session_date should follow start_time UTC.
        $therapist = $this->makeTherapistInTimezone('America/Los_Angeles');
        // Seed contracts spanning both dates so billing succeeds either way.
        [$ssa, $service, $school] = $this->seedSsaWithContracts($therapist, '2026-04-30');

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.store'), [
                'student_id' => $ssa->student_id,
                'ssa_id' => $ssa->id,
                'service_id' => $service->id,
                'session_date' => '2026-04-30',
                'start_time' => '2026-04-30 23:00:00',
                'end_time' => '2026-05-01 00:00:00',
                'duration_minutes' => 60,
                'notes' => str_repeat('a', 30),
                'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
                '_token' => csrf_token(),
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        /** @var SessionLog $log */
        $log = SessionLog::where('therapist_id', $therapist->id)->latest('id')->firstOrFail();

        // 23:00 PDT == 06:00 UTC May 1.
        $this->assertSame('2026-05-01', $log->session_date->format('Y-m-d'));
        $this->assertSame('2026-05-01 06:00:00', $log->start_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-01 07:00:00', $log->end_time->format('Y-m-d H:i:s'));

        // But the therapist still sees the session as "April 30, 11:00 PM" locally.
        $localStart = $log->localStart('America/Los_Angeles');
        $this->assertSame('2026-04-30 23:00:00', $localStart->format('Y-m-d H:i:s'));
    }

    private function makeTherapistInTimezone(string $tz): User
    {
        $therapist = User::factory()->therapist()->create([
            'timezone' => $tz,
        ]);
        TherapistProfile::factory()->create([
            'user_id' => $therapist->id,
            'timezone' => $tz,
        ]);

        return $therapist->fresh(['therapistProfile']);
    }

    /**
     * @return array{0: ServiceSupportAgreement, 1: Service, 2: School}
     */
    private function seedSsaWithContracts(User $therapist, string $sessionDate): array
    {
        $school = School::factory()->create();
        $student = User::factory()->student()->create();
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        // Standalone session logs (no schedule) require an indirect service.
        $service = Service::factory()->create([
            'is_direct_service' => false,
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 120,
        ]);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
            'start_date' => Carbon::parse($sessionDate)->subWeek()->toDateString(),
            'end_date' => Carbon::parse($sessionDate)->addMonth()->toDateString(),
        ]);

        $therapistProfile = $therapist->therapistProfile
            ?? TherapistProfile::factory()->create(['user_id' => $therapist->id]);

        $therapistContract = TherapistContract::create([
            'therapist_id' => $therapistProfile->id,
            'start_date' => Carbon::parse($sessionDate)->subWeek()->toDateString(),
            'end_date' => Carbon::parse($sessionDate)->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);
        TherapistContractService::create([
            'therapist_contract_id' => $therapistContract->id,
            'service_id' => $service->id,
            'rate' => 100,
            'rate_type' => RateType::HOURLY->value,
            'no_show_rate' => 25,
            'no_show_rate_type' => RateType::FLAT->value,
        ]);

        $schoolContract = SchoolContract::create([
            'school_id' => $school->id,
            'start_date' => Carbon::parse($sessionDate)->subWeek()->toDateString(),
            'end_date' => Carbon::parse($sessionDate)->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);
        SchoolContractService::create([
            'school_contract_id' => $schoolContract->id,
            'service_id' => $service->id,
            'rate' => 150,
            'rate_type' => RateType::HOURLY->value,
            'no_show_rate' => 30,
            'no_show_rate_type' => RateType::FLAT->value,
        ]);

        return [$ssa, $service, $school];
    }
}
