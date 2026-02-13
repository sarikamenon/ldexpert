<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\SessionLogSentBackMail;
use App\Models\SessionLog;
use App\Models\SessionLogComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class SessionLogSendBackTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_back_submitted_session_log_with_comment(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->submitted()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.session-logs.send-back', $sessionLog), [
                'comment' => 'Please correct the session date and add missing notes.',
            ]);

        $response->assertRedirect(route('admin.session-logs.show', $sessionLog));
        $response->assertSessionHas('success');

        $sessionLog->refresh();
        $this->assertTrue($sessionLog->isSentBack());
        $this->assertNotNull($sessionLog->sent_back_at);
        $this->assertSame($admin->id, $sessionLog->sent_back_by_id);

        $comment = SessionLogComment::where('session_log_id', $sessionLog->id)->first();
        $this->assertNotNull($comment);
        $this->assertSame('Please correct the session date and add missing notes.', $comment->comment);
        $this->assertSame($admin->id, $comment->author_id);
        $this->assertSame('sent_back', $comment->type->value);

        Mail::assertQueued(SessionLogSentBackMail::class);
    }

    public function test_admin_cannot_send_back_draft_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.session-logs.send-back', $sessionLog), [
                'comment' => 'Some feedback',
            ]);

        $response->assertForbidden();
    }

    public function test_admin_cannot_send_back_approved_session_log(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->approved()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.session-logs.send-back', $sessionLog), [
                'comment' => 'Some feedback',
            ]);

        $response->assertForbidden();
    }

    public function test_send_back_requires_comment(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->submitted()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.session-logs.send-back', $sessionLog), [
                'comment' => '',
            ]);

        $response->assertSessionHasErrors('comment');
    }
}
