<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

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
}
