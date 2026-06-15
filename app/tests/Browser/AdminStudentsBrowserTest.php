<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class AdminStudentsBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $admin;

    private User $student;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'admin+students@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->school = School::factory()->create();

        $this->student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
        ]);

        StudentProfile::factory()->create([
            'user_id' => $this->student->id,
            'school_id' => $this->school->id,
            'first_name' => 'Browser',
            'last_name' => 'Student',
        ]);
    }

    public function test_admin_can_view_students_list(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/students')
                ->assertSee('Students')
                ->assertSee('Total Students')
                ->assertSee('Add Student')
                ->assertSee('Browser Student');
        });
    }

    public function test_admin_can_access_create_student_form(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/students')
                ->clickLink('Add Student')
                ->assertPathIs('/admin/students/create')
                ->assertSee('Basic Information')
                ->assertSee('School & Academic')
                ->assertSee('Parent / Guardian');
        });
    }

    public function test_admin_can_create_student(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/students/create')
                ->type('@student-first-name', 'Dusk')
                ->type('@student-last-name', 'Student')
                ->type('@student-email', 'dusk.student@example.com')
                ->type('@student-date-of-birth', '2012-05-05')
                ->select('timezone', 'America/New_York')
                ->select('school_id', (string) $this->school->id)
                ->press('Create Student')
                ->assertPathIs('/admin/students')
                ->assertSee('Student added successfully.');
        });

        $this->assertDatabaseHas('users', ['email' => 'dusk.student@example.com']);
    }

    public function test_admin_can_edit_student(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/students')
                ->click('@edit-student-'.$this->student->id)
                ->assertPathIs('/admin/students/'.$this->student->id.'/edit')
                ->assertInputValue('first_name', 'Browser')
                ->type('@student-first-name', 'UpdatedBrowser')
                ->press('Update Student Info')
                ->assertPathIs('/admin/students')
                ->assertSee('Student information updated successfully.');
        });
    }

    public function test_admin_can_toggle_student_status(): void
    {
        $this->student->update(['status' => 'active']);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/students')
                ->click('@student-status-toggle-'.$this->student->id)
                ->waitForText('Deactivate Student?')
                ->type('input[type="text"]', 'Testing toggle')
                ->press('Yes, deactivate')
                ->waitForText('Success')
                ->pause(1000);
        });

        $this->assertDatabaseHas('users', [
            'id' => $this->student->id,
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_search_students(): void
    {
        StudentProfile::factory()->create([
            'first_name' => 'UniqueBrowser',
            'user_id' => User::factory()->create([
                'name' => 'Unique Browser',
                'role' => 'student',
            ])->id,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/students')
                ->type('search', 'UniqueBrowser')
                ->press('Filter')
                ->assertSee('UniqueBrowser');
        });
    }

    public function test_admin_can_filter_students_by_status(): void
    {
        $inactiveUser = User::factory()->create([
            'role' => 'student',
            'status' => 'inactive',
        ]);

        StudentProfile::factory()->create([
            'first_name' => 'InactiveBrowser',
            'user_id' => $inactiveUser->id,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/students')
                ->select('status', 'inactive')
                ->press('Filter')
                ->assertSee('Inactive');
        });
    }

    public function test_admin_can_trigger_students_export(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin)
                ->visit('/admin/students')
                ->click('#exportStudentsButton')
                ->pause(1000)
                ->assertPathIs('/admin/students');
        });
    }
}
