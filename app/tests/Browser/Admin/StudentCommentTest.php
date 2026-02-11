<?php

declare(strict_types=1);

namespace Tests\Browser\Admin;

use App\Models\StudentComment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class StudentCommentTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $admin;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->student = User::factory()->student()->create();
    }

    public function test_admin_can_view_comments_tab(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.students.show', ['student' => $this->student, 'tab' => 'comments']))
                ->assertSee('Comments')
                ->assertSee('Add Comment')
                ->assertSee('Comment *');
        });
    }

    public function test_admin_can_submit_comment_form(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.students.show', ['student' => $this->student, 'tab' => 'comments']))
                ->type('comment', 'This is a test comment from Dusk')
                ->press('Add Comment')
                ->waitForText('Comment added successfully', 5)
                ->assertSee('This is a test comment from Dusk')
                ->assertSee($this->admin->name);
        });
    }

    public function test_admin_can_see_existing_comments(): void
    {
        StudentComment::factory()->create([
            'student_id' => $this->student->id,
            'author_id' => $this->admin->id,
            'comment' => 'Existing comment',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.students.show', ['student' => $this->student, 'tab' => 'comments']))
                ->assertSee('Existing comment')
                ->assertSee($this->admin->name)
                ->assertSee('Admin');
        });
    }

    public function test_admin_sees_empty_state_when_no_comments(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.students.show', ['student' => $this->student, 'tab' => 'comments']))
                ->assertSee('No comments yet. Be the first to add a comment!');
        });
    }

    public function test_comment_form_shows_validation_error_for_empty_comment(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit(route('admin.students.show', ['student' => $this->student, 'tab' => 'comments']))
                ->press('Add Comment')
                ->assertSee('Please enter a comment');
        });
    }
}
