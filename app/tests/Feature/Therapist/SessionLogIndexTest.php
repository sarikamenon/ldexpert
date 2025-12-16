<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\SessionLogStatus;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_therapist_sees_status_columns_and_actions(): void
    {
        $therapist = User::factory()->therapist()->create();

        SessionLog::factory()->draft()->create([
            'therapist_id' => $therapist->id,
            'status' => SessionLogStatus::DRAFT,
        ]);
        SessionLog::factory()->finalized()->create([
            'therapist_id' => $therapist->id,
            'status' => SessionLogStatus::FINALIZED,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.session-logs.index'));

        $response->assertOk();
        $response->assertSee('Therapist Amount');
        $response->assertSee('Status');
        $response->assertSee('Submit');
        $response->assertSee('Edit');
        $response->assertDontSee('Approve');
    }
}
