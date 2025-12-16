<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\SessionLog\Services\SessionLogIndexService;
use App\DTOs\SessionLogIndexDTO;
use App\Enums\SessionLogStatus;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogIndexServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_actions_for_submitted_and_finalized(): void
    {
        $admin = User::factory()->admin()->create();
        $submitted = SessionLog::factory()->submitted()->create([
            'therapist_id' => User::factory()->therapist()->create()->id,
        ]);
        SessionLog::factory()->finalized()->create([
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

    public function test_therapist_actions_for_draft_and_finalized(): void
    {
        $therapist = User::factory()->therapist()->create();
        SessionLog::factory()->draft()->create(['therapist_id' => $therapist->id]);
        SessionLog::factory()->finalized()->create(['therapist_id' => $therapist->id]);

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
        $this->assertContains('Cancel', $draftActions);

        $finalRow = collect($result['rows'])
            ->first(fn (array $row) => $row['status'] === SessionLogStatus::FINALIZED->label());
        $this->assertNotNull($finalRow);
        $finalActions = collect($finalRow['actions'])->pluck('label')->all();
        $this->assertSame(['View'], $finalActions);
    }
}
