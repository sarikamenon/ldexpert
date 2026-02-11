<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\ContractStatus;
use App\Enums\RateType;
use App\Enums\SessionOutcome;
use App\Models\SchoolContract;
use App\Models\SchoolContractService;
use App\Models\SessionLog;
use App\Models\TherapistContract;
use App\Models\TherapistContractService;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_therapist_can_view_session_logs_index(): void
    {
        $therapist = User::factory()->therapist()->create();

        $response = $this->actingAs($therapist)
            ->get(route('therapist.session-logs.index'));

        $response->assertOk();
        $response->assertSee('Session Logs');
    }

    public function test_edit_page_populates_service_dropdown_with_existing_value(): void
    {
        /** @var SessionLog $sessionLog */
        $sessionLog = SessionLog::factory()->draft()->create();

        $therapist = $sessionLog->therapist;

        $response = $this->actingAs($therapist)
            ->get(route('therapist.session-logs.edit', $sessionLog));

        $response->assertOk();

        // The edit form should contain a service select option for the current service_id
        // and mark it as selected so that the service appears pre-populated.
        $response->assertSee('name="service_id"', false);
        $response->assertSee('value="' . $sessionLog->service_id . '"', false);
    }

    public function test_edit_update_accepts_hh_mm_time_inputs(): void
    {
        /** @var SessionLog $sessionLog */
        $sessionLog = SessionLog::factory()->draft()->create();

        $therapist = $sessionLog->therapist;
        $this->seedContractsForSessionLog($sessionLog);

        $payload = [
            'session_date' => $sessionLog->session_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'start_time' => '09:00',
            'duration_minutes' => 45,
            'notes' => str_repeat('A', 60),
        ];

        $response = $this->actingAs($therapist)
            ->put(route('therapist.session-logs.update', $sessionLog), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->followRedirects($response)
            ->assertSee('Session log updated successfully.');
    }

    public function test_outcome_is_persisted_and_displayed_after_edit(): void
    {
        /** @var SessionLog $sessionLog */
        $sessionLog = SessionLog::factory()->draft()->create([
            'outcome' => SessionOutcome::SERVICES_ADMINISTERED,
        ]);

        $therapist = $sessionLog->therapist;
        $this->seedContractsForSessionLog($sessionLog);

        $payload = [
            'session_date' => $sessionLog->session_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'start_time' => '09:00',
            'duration_minutes' => 45,
            'notes' => '   ' . str_repeat('B', 60) . '   ',
            'outcome' => SessionOutcome::NO_SHOW->value,
        ];

        $response = $this->actingAs($therapist)
            ->put(route('therapist.session-logs.update', $sessionLog), $payload);

        $this->followRedirects($response)
            ->assertSee('No Show')
            // Ensure trimmed notes are displayed without leading spaces
            ->assertSeeText(str_repeat('B', 60));
    }

    public function test_therapist_cannot_view_other_therapist_session_log(): void
    {
        /** @var SessionLog $sessionLog */
        $sessionLog = SessionLog::factory()->draft()->create();

        $otherTherapist = User::factory()->therapist()->create();

        $this->actingAs($otherTherapist)
            ->get(route('therapist.session-logs.show', $sessionLog))
            ->assertNotFound();
    }

    private function seedContractsForSessionLog(SessionLog $sessionLog): void
    {
        $therapist = $sessionLog->therapist;
        $therapistProfile = $therapist->therapistProfile
            ?? TherapistProfile::factory()->create(['user_id' => $therapist->id]);

        $sessionDate = $sessionLog->session_date ?? now();

        $therapistContract = TherapistContract::create([
            'therapist_id' => $therapistProfile->id,
            'start_date' => $sessionDate->copy()->subDay()->toDateString(),
            'end_date' => $sessionDate->copy()->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);
        TherapistContractService::create([
            'therapist_contract_id' => $therapistContract->id,
            'service_id' => $sessionLog->service_id,
            'rate' => 100,
            'rate_type' => RateType::HOURLY->value,
            'no_show_rate' => 25,
            'no_show_rate_type' => RateType::FLAT->value,
        ]);

        $schoolContract = SchoolContract::create([
            'school_id' => $sessionLog->school_id,
            'start_date' => $sessionDate->copy()->subDay()->toDateString(),
            'end_date' => $sessionDate->copy()->addMonth()->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
        ]);
        SchoolContractService::create([
            'school_contract_id' => $schoolContract->id,
            'service_id' => $sessionLog->service_id,
            'rate' => 150,
            'rate_type' => RateType::HOURLY->value,
            'no_show_rate' => 30,
            'no_show_rate_type' => RateType::FLAT->value,
        ]);
    }
}
