<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Models\ServiceSupportAgreement;
use App\Models\StudentComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StudentCommentTest extends TestCase
{
    use RefreshDatabase;

    private User $therapist;

    private User $student;

    private User $otherTherapist;

    private User $otherStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->therapist = User::factory()->therapist()->create();
        $this->student = User::factory()->student()->create();
        $this->otherTherapist = User::factory()->therapist()->create();
        $this->otherStudent = User::factory()->student()->create();

        // Create SSA linking therapist to student
        ServiceSupportAgreement::factory()->create([
            'student_id' => $this->student->id,
            'assigned_therapist_id' => $this->therapist->id,
        ]);
    }

    public function test_therapist_can_create_comment_for_assigned_student(): void
    {
        $response = $this->actingAs($this->therapist)
            ->postJson(route('therapist.students.comments.store', $this->student), [
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
            'author_id' => $this->therapist->id,
            'comment' => 'This is a test comment',
        ]);
    }

    public function test_therapist_cannot_create_comment_for_unassigned_student(): void
    {
        $response = $this->actingAs($this->therapist)
            ->postJson(route('therapist.students.comments.store', $this->otherStudent), [
                'comment' => 'This is a test comment',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('student_comments', [
            'student_id' => $this->otherStudent->id,
            'author_id' => $this->therapist->id,
        ]);
    }

    public function test_therapist_cannot_create_comment_without_comment_text(): void
    {
        $response = $this->actingAs($this->therapist)
            ->postJson(route('therapist.students.comments.store', $this->student), [
                'comment' => '',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_therapist_can_view_comments_tab_for_assigned_student(): void
    {
        StudentComment::factory()->count(2)->create([
            'student_id' => $this->student->id,
            'author_id' => $this->therapist->id,
        ]);

        $response = $this->actingAs($this->therapist)
            ->get(route('therapist.students.show', ['student' => $this->student, 'tab' => 'comments']));

        $response->assertOk()
            ->assertViewIs('therapist.students.show')
            ->assertViewHas('comments')
            ->assertViewHas('activeTab', 'comments');
    }

    public function test_therapist_cannot_view_comments_tab_for_unassigned_student(): void
    {
        $response = $this->actingAs($this->therapist)
            ->get(route('therapist.students.show', ['student' => $this->otherStudent, 'tab' => 'comments']));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_create_comment(): void
    {
        $response = $this->postJson(route('therapist.students.comments.store', $this->student), [
            'comment' => 'This is a test comment',
        ]);

        $response->assertUnauthorized();
    }
}
