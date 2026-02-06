<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogSubmitTest extends TestCase
{
    use RefreshDatabase;

    public function test_therapist_can_submit_draft_session_log(): void
    {
        $therapist = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->draft()->create([
            'therapist_id' => $therapist->id,
        ]);

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.submit', $sessionLog));

        $response->assertRedirect(route('therapist.session-logs.show', $sessionLog));
        $sessionLog->refresh();
        $this->assertTrue($sessionLog->isSubmitted());
        $this->assertNotNull($sessionLog->submitted_at);
        $this->assertSame($therapist->id, $sessionLog->submitted_by_id);
    }

    public function test_therapist_cannot_submit_already_submitted_session_log(): void
    {
        $therapist = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->submitted()->create([
            'therapist_id' => $therapist->id,
        ]);

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.submit', $sessionLog));

        $response->assertSessionHasErrors();
    }

    public function test_therapist_cannot_submit_another_therapists_session_log(): void
    {
        $therapist1 = User::factory()->therapist()->create();
        $therapist2 = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->draft()->create([
            'therapist_id' => $therapist1->id,
        ]);

        $response = $this->actingAs($therapist2)
            ->post(route('therapist.session-logs.submit', $sessionLog));

        $response->assertForbidden();
    }

    public function test_therapist_cannot_submit_approved_session_log(): void
    {
        $therapist = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->approved()->create([
            'therapist_id' => $therapist->id,
        ]);

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.submit', $sessionLog));

        $response->assertForbidden();
    }
}
