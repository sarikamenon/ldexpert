<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\Role;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_therapist_can_view_own_session_logs(): void
    {
        $therapist = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
        ]);

        $this->assertTrue($therapist->can('view', $sessionLog));
    }

    public function test_therapist_cannot_view_other_therapists_session_logs(): void
    {
        $therapist1 = User::factory()->therapist()->create();
        $therapist2 = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->create([
            'therapist_id' => $therapist1->id,
        ]);

        $this->assertFalse($therapist2->can('view', $sessionLog));
    }

    public function test_admin_can_view_any_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $therapist = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
        ]);

        $this->assertTrue($admin->can('view', $sessionLog));
    }

    public function test_therapist_can_update_own_draft_session_log(): void
    {
        $therapist = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->draft()->create([
            'therapist_id' => $therapist->id,
        ]);

        $this->assertTrue($therapist->can('update', $sessionLog));
    }

    public function test_therapist_cannot_update_submitted_session_log(): void
    {
        $therapist = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->submitted()->create([
            'therapist_id' => $therapist->id,
        ]);

        $this->assertFalse($therapist->can('update', $sessionLog));
    }

    public function test_admin_can_update_submitted_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->submitted()->create();

        $this->assertTrue($admin->can('update', $sessionLog));
    }

    public function test_admin_cannot_update_finalized_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->finalized()->create();

        $this->assertFalse($admin->can('update', $sessionLog));
    }

    public function test_admin_can_finalize_submitted_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->submitted()->create();

        $this->assertTrue($admin->can('finalize', $sessionLog));
    }

    public function test_admin_cannot_finalize_draft_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->draft()->create();

        $this->assertFalse($admin->can('finalize', $sessionLog));
    }

    public function test_therapist_can_submit_own_draft_session_log(): void
    {
        $therapist = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->draft()->create([
            'therapist_id' => $therapist->id,
        ]);

        $this->assertTrue($therapist->can('submit', $sessionLog));
    }

    public function test_therapist_cannot_submit_other_therapists_session_log(): void
    {
        $therapist1 = User::factory()->therapist()->create();
        $therapist2 = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->draft()->create([
            'therapist_id' => $therapist1->id,
        ]);

        $this->assertFalse($therapist2->can('submit', $sessionLog));
    }
}
