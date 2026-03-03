<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\SessionLogCommentType;
use App\Models\SessionLog;
use App\Models\SessionLogComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_therapist_can_add_comment_to_own_session_log(): void
    {
        $therapist = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->sentBack()->create([
            'therapist_id' => $therapist->id,
        ]);
        SessionLogComment::factory()->create([
            'session_log_id' => $sessionLog->id,
            'type' => SessionLogCommentType::SENT_BACK,
        ]);

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.comment', $sessionLog), [
                'comment' => 'This is my response to the admin feedback.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $comment = SessionLogComment::where('session_log_id', $sessionLog->id)
            ->where('type', SessionLogCommentType::THERAPIST_REPLY)
            ->first();

        $this->assertNotNull($comment);
        $this->assertSame('This is my response to the admin feedback.', $comment->comment);
        $this->assertSame($therapist->id, $comment->author_id);
        $this->assertSame(SessionLogCommentType::THERAPIST_REPLY, $comment->type);
    }

    public function test_therapist_can_add_comment_and_submit(): void
    {
        $therapist = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->sentBack()->create([
            'therapist_id' => $therapist->id,
        ]);
        SessionLogComment::factory()->create([
            'session_log_id' => $sessionLog->id,
            'type' => SessionLogCommentType::SENT_BACK,
        ]);

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.comment', $sessionLog), [
                'comment' => 'The date was correct, please check the schedule.',
                'submit_after_comment' => '1',
            ]);

        $response->assertRedirect(route('therapist.session-logs.show', $sessionLog));
        $response->assertSessionHas('success');

        $comment = SessionLogComment::where('session_log_id', $sessionLog->id)
            ->where('type', SessionLogCommentType::THERAPIST_REPLY)
            ->first();
        $this->assertNotNull($comment);
        $this->assertSame('The date was correct, please check the schedule.', $comment->comment);

        $sessionLog->refresh();
        $this->assertTrue($sessionLog->isSubmitted());
    }

    public function test_comment_requires_minimum_length(): void
    {
        $therapist = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->sentBack()->create([
            'therapist_id' => $therapist->id,
        ]);

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.comment', $sessionLog), [
                'comment' => 'Hi',
            ]);

        $response->assertSessionHasErrors('comment');
    }

    public function test_comment_is_required(): void
    {
        $therapist = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->sentBack()->create([
            'therapist_id' => $therapist->id,
        ]);

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.comment', $sessionLog), [
                'comment' => '',
            ]);

        $response->assertSessionHasErrors('comment');
    }

    public function test_therapist_cannot_comment_on_another_therapists_session_log(): void
    {
        $therapist1 = User::factory()->therapist()->create();
        $therapist2 = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->sentBack()->create([
            'therapist_id' => $therapist1->id,
        ]);

        $response = $this->actingAs($therapist2)
            ->post(route('therapist.session-logs.comment', $sessionLog), [
                'comment' => 'Should not be allowed.',
            ]);

        $response->assertStatus(404);
    }

    public function test_non_therapist_cannot_add_comment(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->sentBack()->create();

        $response = $this->actingAs($admin)
            ->post(route('therapist.session-logs.comment', $sessionLog), [
                'comment' => 'Admin trying to comment via therapist route.',
            ]);

        $response->assertForbidden();
    }

    public function test_submit_after_comment_does_not_submit_when_status_cannot_submit(): void
    {
        $therapist = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->submitted()->create([
            'therapist_id' => $therapist->id,
        ]);
        SessionLogComment::factory()->create([
            'session_log_id' => $sessionLog->id,
            'type' => SessionLogCommentType::SENT_BACK,
        ]);

        $response = $this->actingAs($therapist)
            ->post(route('therapist.session-logs.comment', $sessionLog), [
                'comment' => 'Adding a comment to an already submitted log.',
                'submit_after_comment' => '1',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Comment added successfully.');

        $sessionLog->refresh();
        $this->assertTrue($sessionLog->isSubmitted());
    }
}
