<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogAdminActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_submitted_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->submitted()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.session-logs.approve', $sessionLog));

        $response->assertRedirect(route('admin.session-logs.show', $sessionLog));
        $sessionLog->refresh();
        $this->assertTrue($sessionLog->isApproved());
        $this->assertNotNull($sessionLog->approved_at);
        $this->assertSame($admin->id, $sessionLog->approved_by_id);
    }

    public function test_admin_cannot_approve_draft_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.session-logs.approve', $sessionLog));

        $response->assertForbidden();
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

    public function test_admin_cannot_cancel_approved_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->approved()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.session-logs.cancel', $sessionLog), [
                'cancellation_reason' => 'Test',
            ]);

        $response->assertForbidden();
    }
}
