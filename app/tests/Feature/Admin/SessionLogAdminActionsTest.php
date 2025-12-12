<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\SessionLogStatus;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogAdminActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_finalize_submitted_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->submitted()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.session-logs.finalize', $sessionLog));

        $response->assertRedirect(route('admin.session-logs.show', $sessionLog));
        $sessionLog->refresh();
        $this->assertTrue($sessionLog->isFinalized());
        $this->assertNotNull($sessionLog->finalized_at);
        $this->assertSame($admin->id, $sessionLog->finalized_by_id);
    }

    public function test_admin_cannot_finalize_draft_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.session-logs.finalize', $sessionLog));

        $response->assertSessionHasErrors();
    }

    public function test_admin_can_cancel_draft_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.session-logs.cancel', $sessionLog), [
                'cancellation_reason' => 'Cancelled by admin for review',
            ]);

        $response->assertRedirect(route('admin.session-logs.show', $sessionLog));
        $sessionLog->refresh();
        $this->assertTrue($sessionLog->isCancelled());
        $this->assertNotNull($sessionLog->cancellation_reason);
    }

    public function test_admin_can_cancel_submitted_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->submitted()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.session-logs.cancel', $sessionLog), [
                'cancellation_reason' => 'Cancelled by admin',
            ]);

        $response->assertRedirect(route('admin.session-logs.show', $sessionLog));
        $sessionLog->refresh();
        $this->assertTrue($sessionLog->isCancelled());
    }

    public function test_admin_cannot_cancel_finalized_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->finalized()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.session-logs.cancel', $sessionLog), [
                'cancellation_reason' => 'Test',
            ]);

        $response->assertForbidden();
    }
}
