<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\ContractStatus;
use App\Enums\Role;
use App\Enums\SSAStatus;
use App\Models\Position;
use App\Models\Schedule;
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

/**
 * Shared scenario builders for the substitute-coverage feature tests.
 *
 * A canonical "world" of:
 *   - one position
 *   - one service + one school
 *   - one student with an active SSA
 *   - therapist A (requester, schedule owner, has SSA)
 *   - therapist B (eligible sub — same position + contract for service)
 *   - therapist C (eligible sub — same position + contract for service)
 *   - therapist E (INELIGIBLE — different position)
 *   - therapist F (INELIGIBLE — same position but no contract for service)
 *   - a future schedule owned by A on $sessionDate, well past the cutoff
 */
trait CreatesSubCoverageFixtures
{
    /**
     * @return array{
     *   position: Position,
     *   service: Service,
     *   school: School,
     *   student: User,
     *   ssa: ServiceSupportAgreement,
     *   A: User,
     *   B: User,
     *   C: User,
     *   E: User,
     *   F: User,
     *   schedule: Schedule,
     *   sessionDate: Carbon,
     *   sessionStart: Carbon
     * }
     */
    public function buildSubCoverageWorld(?Carbon $sessionStart = null): array
    {
        // sessionStart represents the full schedule start instant (date + 09:00).
        // Default is 7 days out at 09:00 UTC.
        $sessionStart ??= Carbon::now()->addDays(7)->setTime(9, 0, 0);
        $sessionDate = $sessionStart->copy()->startOfDay();

        $position = Position::factory()->create();
        $otherPosition = Position::factory()->create();
        $school = School::factory()->create();
        // Pin a wide duration range so session-log tests using 60-minute
        // durations are never rejected by the random factory bounds.
        $service = Service::factory()->create([
            'min_duration_minutes' => 15,
            'max_duration_minutes' => 240,
        ]);
        $otherService = Service::factory()->create();

        // School contract for $service so dual-billing math succeeds in
        // session-log tests. Therapist tests that never create a log can
        // ignore this — it's leaf data with no behavioural side-effects.
        $schoolContract = SchoolContract::create([
            'school_id' => $school->id,
            'start_date' => $sessionDate->copy()->subMonth()->toDateString(),
            'end_date' => $sessionDate->copy()->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);
        SchoolContractService::create([
            'school_contract_id' => $schoolContract->id,
            'service_id' => $service->id,
            'rate' => 150,
            'rate_type' => 'H',
        ]);

        $A = $this->makeTherapistWithContract($position, $service, $sessionDate);
        $B = $this->makeTherapistWithContract($position, $service, $sessionDate);
        $C = $this->makeTherapistWithContract($position, $service, $sessionDate);

        // E — different position
        $E = $this->makeTherapistWithContract($otherPosition, $service, $sessionDate);

        // F — same position but contract only covers a different service
        $F = $this->makeTherapistWithContract($position, $otherService, $sessionDate);

        $student = User::factory()->create(['role' => Role::STUDENT->value]);
        StudentProfile::factory()->create(['user_id' => $student->id, 'school_id' => $school->id]);

        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $A->id,
            'start_date' => $sessionDate->copy()->subMonth()->toDateString(),
            'end_date' => $sessionDate->copy()->addMonth()->toDateString(),
            'status' => SSAStatus::ACTIVE->value,
        ]);

        $schedule = Schedule::factory()->create([
            'therapist_id' => $A->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'school_id' => $school->id,
            'schedule_date' => $sessionDate->toDateString(),
            'start_time' => $sessionStart->format('H:i'),
            'end_time' => $sessionStart->copy()->addHour()->format('H:i'),
        ]);

        return compact('position', 'service', 'school', 'student', 'ssa', 'A', 'B', 'C', 'E', 'F', 'schedule', 'sessionDate', 'sessionStart');
    }

    public function makeTherapistWithContract(Position $position, Service $service, Carbon $sessionDate): User
    {
        $profile = TherapistProfile::factory()->create([
            'position_id' => $position->id,
        ]);

        /** @var User $user */
        $user = $profile->user;

        $contract = TherapistContract::create([
            'therapist_id' => $profile->id,
            'start_date' => $sessionDate->copy()->subMonth()->toDateString(),
            'end_date' => $sessionDate->copy()->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);

        TherapistContractService::create([
            'therapist_contract_id' => $contract->id,
            'service_id' => $service->id,
            'rate' => 100,
            'rate_type' => 'H',
        ]);

        return $user;
    }
}
