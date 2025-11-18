<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Mail\WelcomeStudentMail;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private School $school;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->school = School::factory()->create();
        $this->student = User::factory()
            ->create([
                'role' => Role::STUDENT->value,
                'status' => UserStatus::ACTIVE->value,
            ]);

        StudentProfile::factory()->create([
            'user_id' => $this->student->id,
            'school_id' => $this->school->id,
            'first_name' => 'Existing',
            'last_name' => 'Student',
        ]);
    }

    public function test_admin_can_view_students_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.students.index'));

        $response->assertOk()
            ->assertViewIs('admin.students.index')
            ->assertViewHas('students')
            ->assertViewHas('metrics');
    }

    public function test_non_admin_cannot_view_students_index(): void
    {
        $response = $this->actingAs(User::factory()->student()->create())->get(route('admin.students.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_create_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.students.create'));

        $response->assertOk()
            ->assertViewIs('admin.students.create')
            ->assertViewHas('states')
            ->assertViewHas('timezones')
            ->assertViewHas('schools');
    }

    public function test_admin_can_create_student(): void
    {
        Mail::fake();

        $data = [
            'first_name' => 'Ava',
            'middle_name' => 'Rose',
            'last_name' => 'Smith',
            'email' => 'ava@example.com',
            'gender' => 'Female',
            'date_of_birth' => '2012-03-05',
            'school_id' => $this->school->id,
            'id_number' => 'STU-100',
            'timezone' => 'America/New_York',
            'grade_level' => '5',
            'parent_guardian_name' => 'Mary Smith',
            'parent_guardian_email' => 'mary@example.com',
            'parent_guardian_phone' => '123-456-7890',
            'address' => '123 School St',
            'city' => 'Boston',
            'state' => 'MA',
            'zip_code' => '02115',
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.students.store'), $data);

        $response->assertRedirect(route('admin.students.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('student_profiles', [
            'first_name' => 'Ava',
            'school_id' => $this->school->id,
        ]);

        Mail::assertSent(WelcomeStudentMail::class, fn(WelcomeStudentMail $mail) => $mail->hasTo('ava@example.com'));
    }

    public function test_admin_can_view_edit_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.students.edit', $this->student));

        $response->assertOk()
            ->assertViewIs('admin.students.edit')
            ->assertViewHas('student');
    }

    public function test_admin_can_update_student(): void
    {
        $payload = [
            'first_name' => 'Updated',
            'middle_name' => null,
            'last_name' => 'Student',
            'email' => 'updated@example.com',
            'gender' => 'Male',
            'date_of_birth' => '2011-01-01',
            'school_id' => $this->school->id,
            'id_number' => 'ID-200',
            'timezone' => 'America/Chicago',
            'grade_level' => '6',
            'parent_guardian_name' => 'Guardian',
            'parent_guardian_email' => 'guardian@example.com',
            'parent_guardian_phone' => '222-333-4444',
            'address' => '456 Updated Way',
            'city' => 'Austin',
            'state' => 'TX',
            'zip_code' => '73301',
        ];

        $response = $this->actingAs($this->admin)->put(route('admin.students.update', $this->student), $payload);

        $response->assertRedirect(route('admin.students.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $this->student->id,
            'first_name' => 'Updated',
            'grade_level' => '6',
        ]);
    }

    public function test_admin_can_change_student_status(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.students.status', $this->student), [
            'status' => 'inactive',
            'reason' => 'Testing',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $this->student->id,
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_export_students(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.students.export'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_admin_can_filter_students_by_search(): void
    {
        StudentProfile::factory()->create([
            'first_name' => 'UniqueStudent',
            'user_id' => User::factory()->create([
                'name' => 'Unique Student',
                'role' => Role::STUDENT->value,
            ])->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.students.index', ['search' => 'UniqueStudent']));

        $response->assertOk()->assertSee('UniqueStudent');
    }

    public function test_admin_can_filter_students_by_status(): void
    {
        StudentProfile::factory()->create([
            'user_id' => User::factory()->create([
                'role' => Role::STUDENT->value,
                'status' => UserStatus::INACTIVE->value,
            ])->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.students.index', ['status' => 'inactive']));

        $response->assertOk();
    }

    public function test_create_student_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.students.store'), []);

        $response->assertSessionHasErrors([
            'first_name',
            'last_name',
            'email',
            'date_of_birth',
            'timezone',
        ]);
    }

    public function test_create_student_validates_phone_format(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.students.store'), [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'test@example.com',
            'date_of_birth' => '2013-01-01',
            'timezone' => 'America/Chicago',
            'parent_guardian_phone' => '1234567890',
        ]);

        $response->assertSessionHasErrors(['parent_guardian_phone']);
    }

    public function test_create_student_validates_unique_email(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.students.store'), [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => $this->student->email,
            'date_of_birth' => '2013-01-01',
            'timezone' => 'America/New_York',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_non_admin_cannot_modify_students(): void
    {
        $nonAdmin = User::factory()->student()->create();

        $this->actingAs($nonAdmin)->post(route('admin.students.store'), [])
            ->assertForbidden();

        $this->actingAs($nonAdmin)->put(route('admin.students.update', $this->student), [])
            ->assertForbidden();

        $this->actingAs($nonAdmin)->patch(route('admin.students.status', $this->student), [
            'status' => 'inactive',
        ])->assertForbidden();

        $this->actingAs($nonAdmin)->get(route('admin.students.export'))
            ->assertForbidden();
    }
}
