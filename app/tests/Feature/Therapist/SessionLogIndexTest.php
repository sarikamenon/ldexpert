<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\SessionLogStatus;
use App\Models\SessionLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_therapist_sees_status_columns_and_actions(): void
    {
        Carbon::setTestNow('2025-01-15 10:00:00');

        $therapist = User::factory()->therapist()->create();

        SessionLog::factory()->draft()->create([
            'therapist_id' => $therapist->id,
            'status' => SessionLogStatus::DRAFT,
            'session_date' => Carbon::now()->startOfMonth(),
        ]);
        SessionLog::factory()->approved()->create([
            'therapist_id' => $therapist->id,
            'status' => SessionLogStatus::APPROVED,
            'session_date' => Carbon::now()->startOfMonth(),
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.session-logs.index'));

        $response->assertOk();
        $response->assertSee('Therapist Amount');
        $response->assertSee('Status');
        $response->assertSee('Submit');
        $response->assertSee('Edit');
        $response->assertDontSee('Approve');

        Carbon::setTestNow();
    }

    public function test_index_defaults_to_current_month_for_therapist(): void
    {
        Carbon::setTestNow('2025-01-15 10:00:00');

        $therapist = User::factory()->therapist()->create();

        // Outside current month
        SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
            'session_date' => Carbon::now()->copy()->subMonth()->startOfMonth(),
        ]);

        // Inside current month
        $inside = SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
            'session_date' => Carbon::now()->copy()->startOfMonth(),
        ]);

        // Another therapist's log (should not appear)
        SessionLog::factory()->create([
            'therapist_id' => User::factory()->therapist()->create()->id,
            'session_date' => $inside->session_date,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.session-logs.index'));

        $response->assertOk();

        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $response->assertSee('value="'.$startOfMonth.'"', false);
        $response->assertSee('value="'.$endOfMonth.'"', false);
        $response->assertSee($inside->session_date?->format('Y-m-d'));

        Carbon::setTestNow();
    }
}
