<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EntryWindowEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_therapist_can_check_entry_window(): void
    {
        $therapist = User::factory()->therapist()->create();

        $response = $this->actingAs($therapist)
            ->getJson(route('therapist.session-logs.entry-window', [
                'session_date' => now()->format('Y-m-d'),
            ]));

        $response->assertOk()
            ->assertJsonStructure([
                'session_date',
                'week_start',
                'cutoff',
                'cutoff_display',
                'is_within_window',
            ]);
    }

    public function test_entry_window_returns_within_window_for_recent_date(): void
    {
        $therapist = User::factory()->therapist()->create();

        $response = $this->actingAs($therapist)
            ->getJson(route('therapist.session-logs.entry-window', [
                'session_date' => now()->format('Y-m-d'),
            ]));

        $response->assertOk()
            ->assertJson(['is_within_window' => true]);
    }

    public function test_entry_window_returns_outside_window_for_old_date(): void
    {
        $therapist = User::factory()->therapist()->create();

        $response = $this->actingAs($therapist)
            ->getJson(route('therapist.session-logs.entry-window', [
                'session_date' => now()->subMonths(2)->format('Y-m-d'),
            ]));

        $response->assertOk()
            ->assertJson(['is_within_window' => false]);
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->getJson(route('therapist.session-logs.entry-window', [
            'session_date' => now()->format('Y-m-d'),
        ]));

        $response->assertUnauthorized();
    }

    public function test_missing_session_date_returns_validation_error(): void
    {
        $therapist = User::factory()->therapist()->create();

        $response = $this->actingAs($therapist)
            ->getJson(route('therapist.session-logs.entry-window'));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['session_date']);
    }

    public function test_invalid_session_date_returns_validation_error(): void
    {
        $therapist = User::factory()->therapist()->create();

        $response = $this->actingAs($therapist)
            ->getJson(route('therapist.session-logs.entry-window', [
                'session_date' => 'not-a-date',
            ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['session_date']);
    }
}
