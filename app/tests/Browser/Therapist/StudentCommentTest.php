<?php

declare(strict_types=1);

namespace Tests\Browser\Therapist;

use App\Models\ServiceSupportAgreement;
use App\Models\StudentComment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class StudentCommentTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $therapist;

    private User $student;

    private User $otherStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->therapist = User::factory()->therapist()->create();
        $this->student = User::factory()->student()->create();
        $this->otherStudent = User::factory()->student()->create();

        // Create SSA linking therapist to student
        ServiceSupportAgreement::factory()->create([
            'student_id' => $this->student->id,
            'assigned_therapist_id' => $this->therapist->id,
        ]);
    }

    public function test_therapist_can_view_comments_tab_for_assigned_student(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->therapist)
                ->visit(route('therapist.students.show', ['student' => $this->student, 'tab' => 'comments']))
                ->assertSee('Comments')
                ->assertSee('Add Comment')
                ->assertSee('Comment *');
        });
    }

    public function test_therapist_can_submit_comment_form(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->therapist)
                ->visit(route('therapist.students.show', ['student' => $this->student, 'tab' => 'comments']))
                ->type('comment', 'This is a test comment from therapist')
                ->press('Add Comment')
                ->waitForText('Comment added successfully', 5)
                ->assertSee('This is a test comment from therapist')
                ->assertSee($this->therapist->name);
        });
    }

    public function test_therapist_can_see_existing_comments(): void
    {
        StudentComment::factory()->create([
            'student_id' => $this->student->id,
            'author_id' => $this->therapist->id,
            'comment' => 'Existing therapist comment',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->therapist)
                ->visit(route('therapist.students.show', ['student' => $this->student, 'tab' => 'comments']))
                ->assertSee('Existing therapist comment')
                ->assertSee($this->therapist->name)
                ->assertSee('Therapist');
        });
    }

    public function test_therapist_cannot_access_comments_for_unassigned_student(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->therapist)
                ->visit(route('therapist.students.show', ['student' => $this->otherStudent, 'tab' => 'comments']))
                ->assertSee('403')
                ->assertSee('You do not have access to this student');
        });
    }
}
