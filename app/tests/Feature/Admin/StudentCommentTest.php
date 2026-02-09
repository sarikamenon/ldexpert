<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\StudentComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StudentCommentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->student = User::factory()->student()->create();
    }

    public function test_admin_can_create_comment(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.comments.store', $this->student), [
                'comment' => 'This is a test comment',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Comment added successfully.',
            ])
            ->assertJsonStructure([
                'comment' => [
                    'id',
                    'comment',
                    'author_name',
                    'author_role',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('student_comments', [
            'student_id' => $this->student->id,
            'author_id' => $this->admin->id,
            'comment' => 'This is a test comment',
        ]);
    }

    public function test_admin_cannot_create_comment_without_comment_text(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.comments.store', $this->student), [
                'comment' => '',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_admin_cannot_create_comment_exceeding_max_length(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.comments.store', $this->student), [
                'comment' => str_repeat('a', 5001),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_non_admin_cannot_create_comment(): void
    {
        $therapist = User::factory()->therapist()->create();

        $response = $this->actingAs($therapist)
            ->postJson(route('admin.students.comments.store', $this->student), [
                'comment' => 'This is a test comment',
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_create_comment(): void
    {
        $response = $this->postJson(route('admin.students.comments.store', $this->student), [
            'comment' => 'This is a test comment',
        ]);

        $response->assertUnauthorized();
    }

    public function test_admin_can_view_comments_tab(): void
    {
        StudentComment::factory()->count(3)->create([
            'student_id' => $this->student->id,
            'author_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.students.show', ['student' => $this->student, 'tab' => 'comments']));

        $response->assertOk()
            ->assertViewIs('admin.students.show')
            ->assertViewHas('comments')
            ->assertViewHas('activeTab', 'comments');
    }
}
