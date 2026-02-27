<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\SessionLog\Services\SessionLogIndexService;
use App\DTOs\SessionLogIndexDTO;
use App\Enums\SessionLogStatus;
use App\Models\SessionLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogIndexServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_actions_for_submitted_and_approved(): void
    {
        $admin = User::factory()->admin()->create();
        $submitted = SessionLog::factory()->submitted()->create([
            'therapist_id' => User::factory()->therapist()->create()->id,
        ]);
        SessionLog::factory()->approved()->create([
            'therapist_id' => $submitted->therapist_id,
        ]);

        $service = app(SessionLogIndexService::class);
        $result = $service->getAdminIndex(SessionLogIndexDTO::fromArray([]));

        $submittedRow = collect($result['rows'])
            ->first(fn (array $row) => $row['status'] === SessionLogStatus::SUBMITTED->label());

        $this->assertNotNull($submittedRow);

        $actionLabels = collect($submittedRow['actions'])->pluck('label')->all();
        $this->assertContains('Approve', $actionLabels);
        $this->assertContains('Cancel', $actionLabels);
        $this->assertContains('View', $actionLabels);
    }

    public function test_therapist_actions_for_draft_and_approved(): void
    {
        Carbon::setTestNow('2025-01-15 10:00:00');

        $therapist = User::factory()->therapist()->create();
        SessionLog::factory()->draft()->create([
            'therapist_id' => $therapist->id,
            'session_date' => Carbon::now()->startOfMonth(),
        ]);
        SessionLog::factory()->approved()->create([
            'therapist_id' => $therapist->id,
            'session_date' => Carbon::now()->startOfMonth(),
        ]);

        $service = app(SessionLogIndexService::class);
        $result = $service->getTherapistIndex(
            $therapist,
            SessionLogIndexDTO::fromArray([])
        );

        $draftRow = collect($result['rows'])
            ->first(fn (array $row) => $row['status'] === SessionLogStatus::DRAFT->label());
        $this->assertNotNull($draftRow);
        $draftActions = collect($draftRow['actions'])->pluck('label')->all();
        $this->assertContains('Edit', $draftActions);
        $this->assertContains('Submit', $draftActions);

        $finalRow = collect($result['rows'])
            ->first(fn (array $row) => $row['status'] === SessionLogStatus::APPROVED->label());
        $this->assertNotNull($finalRow);
        $finalActions = collect($finalRow['actions'])->pluck('label')->all();
        $this->assertSame(['View'], $finalActions);

        Carbon::setTestNow();
    }

    public function test_therapist_index_applies_current_month_default_filters_when_dates_missing(): void
    {
        Carbon::setTestNow('2025-01-15 10:00:00');

        $therapist = User::factory()->therapist()->create();

        // Outside current month
        SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
            'session_date' => Carbon::now()->copy()->subMonth()->startOfMonth(),
        ]);

        // Inside current month
        $insideFirst = SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
            'session_date' => Carbon::now()->startOfMonth(),
        ]);
        $insideLast = SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
            'session_date' => Carbon::now()->endOfMonth(),
        ]);

        // Outside current month (future)
        SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
            'session_date' => Carbon::now()->copy()->addMonth()->startOfMonth(),
        ]);

        $service = app(SessionLogIndexService::class);
        $result = $service->getTherapistIndex(
            $therapist,
            SessionLogIndexDTO::fromArray([])
        );

        $filters = $result['filters'];

        $this->assertSame(Carbon::now()->startOfMonth()->toDateString(), $filters['date_from']);
        $this->assertSame(Carbon::now()->endOfMonth()->toDateString(), $filters['date_to']);
        $this->assertSame($therapist->id, $filters['therapist_id']);

        $this->assertCount(2, $result['rows']);
        $dates = collect($result['rows'])->pluck('date')->all();
        $this->assertContains($insideFirst->session_date?->format('Y-m-d'), $dates);
        $this->assertContains($insideLast->session_date?->format('Y-m-d'), $dates);

        Carbon::setTestNow();
    }

    public function test_therapist_index_respects_explicit_date_filters(): void
    {
        Carbon::setTestNow('2025-01-15 10:00:00');

        $therapist = User::factory()->therapist()->create();

        $from = Carbon::now()->copy()->subMonths(2)->startOfMonth()->toDateString();
        $to = Carbon::now()->copy()->subMonth()->endOfMonth()->toDateString();

        $service = app(SessionLogIndexService::class);
        $result = $service->getTherapistIndex(
            $therapist,
            SessionLogIndexDTO::fromArray([
                'date_from' => $from,
                'date_to' => $to,
            ])
        );

        $filters = $result['filters'];

        $this->assertSame($from, $filters['date_from']);
        $this->assertSame($to, $filters['date_to']);
        $this->assertSame($therapist->id, $filters['therapist_id']);

        Carbon::setTestNow();
    }

    public function test_index_handles_unknown_status_gracefully(): void
    {
        $therapist = User::factory()->therapist()->create();

        SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
            'status' => 'legacy_status', // legacy/invalid value
            'session_date' => now()->startOfMonth(),
        ]);

        $service = app(SessionLogIndexService::class);
        $result = $service->getTherapistIndex(
            $therapist,
            SessionLogIndexDTO::fromArray([])
        );

        $statuses = collect($result['rows'])->pluck('status')->all();
        $this->assertContains('-', $statuses);
    }

    public function test_rows_include_entry_created_date(): void
    {
        Carbon::setTestNow('2025-01-10 10:00:00');

        $therapist = User::factory()->therapist()->create();

        $log = SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
            'session_date' => Carbon::parse('2025-01-10'),
            'created_at' => Carbon::parse('2025-01-10 08:00:00'),
        ]);

        $service = app(SessionLogIndexService::class);
        $result = $service->getTherapistIndex(
            $therapist,
            SessionLogIndexDTO::fromArray([])
        );

        $row = $result['rows'][0] ?? null;

        $this->assertNotNull($row);
        $this->assertSame('Jan 10, 2025', $row['entry_info']['created_date']);
        $this->assertArrayNotHasKey('entry_difference', $row['entry_info']);

        Carbon::setTestNow();
    }
}
