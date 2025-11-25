<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\DTOs\ChangeStudentStatusDTO;
use App\DTOs\StudentFilterDTO;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Infrastructure\Repositories\EloquentStudentRepository;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentStudentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentStudentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentStudentRepository();
    }

    public function test_create_persists_user_and_profile(): void
    {
        $school = School::factory()->create();

        $userData = [
            'name' => 'Ava Lee',
            'email' => 'ava@example.com',
            'password' => 'hashed',
            'role' => Role::STUDENT->value,
            'status' => UserStatus::ACTIVE->value,
        ];

        $profileData = [
            'first_name' => 'Ava',
            'last_name' => 'Lee',
            'school_id' => $school->id,
            'timezone' => 'America/New_York',
            'date_of_birth' => '2012-01-01',
        ];

        $profile = $this->repository->create($userData, $profileData);

        $this->assertInstanceOf(StudentProfile::class, $profile);
        $this->assertDatabaseHas('users', ['email' => 'ava@example.com', 'role' => Role::STUDENT->value]);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $profile->user_id,
            'school_id' => $school->id,
        ]);
    }

    public function test_update_updates_existing_profile(): void
    {
        $school = School::factory()->create();

        $user = User::factory()->create([
            'role' => Role::STUDENT->value,
            'status' => UserStatus::ACTIVE->value,
        ]);

        $profile = StudentProfile::factory()->create([
            'user_id' => $user->id,
            'school_id' => $school->id,
        ]);

        $updatedProfile = $this->repository->update($user, [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ], [
            'first_name' => 'Updated',
            'grade_level' => '7',
        ]);

        $this->assertSame('Updated', $updatedProfile->first_name);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'updated@example.com',
        ]);
        $this->assertDatabaseHas('student_profiles', [
            'id' => $profile->id,
            'grade_level' => '7',
        ]);
    }

    public function test_update_creates_profile_when_missing(): void
    {
        $user = User::factory()->create(['role' => Role::STUDENT->value]);
        $school = School::factory()->create();

        $profile = $this->repository->update($user, ['name' => 'Has Profile'], [
            'first_name' => 'New',
            'last_name' => 'Student',
            'school_id' => $school->id,
            'timezone' => 'America/Chicago',
        ]);

        $this->assertInstanceOf(StudentProfile::class, $profile);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $user->id,
            'first_name' => 'New',
        ]);
    }

    public function test_find_returns_profile(): void
    {
        $profile = StudentProfile::factory()->create();

        $found = $this->repository->find($profile->id);

        $this->assertInstanceOf(StudentProfile::class, $found);
        $this->assertSame($profile->id, $found->id);
    }

    public function test_list_returns_paginated_students(): void
    {
        $initialCount = User::where('role', Role::STUDENT->value)->count();
        StudentProfile::factory()->count(4)->create();

        $result = $this->repository->list(new StudentFilterDTO(null, null, 25));

        $this->assertCount($initialCount + 4, $result->items());
    }

    public function test_list_filters_by_search(): void
    {
        StudentProfile::factory()->create(['first_name' => 'Unique']);
        StudentProfile::factory()->create(['first_name' => 'Other']);

        $result = $this->repository->list(new StudentFilterDTO('Unique', null, 25));

        $this->assertCount(1, $result->items());
        $this->assertSame('Unique', $result->items()[0]->studentProfile->first_name);
    }

    public function test_list_filters_by_status(): void
    {
        $activeStudents = collect();

        for ($i = 0; $i < 2; $i++) {
            $user = User::factory()->create([
                'role' => Role::STUDENT->value,
                'status' => UserStatus::ACTIVE->value,
            ]);
            $activeStudents->push(
                StudentProfile::factory()->state(['user_id' => $user->id])->create()
            );
        }

        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create([
                'role' => Role::STUDENT->value,
                'status' => UserStatus::INACTIVE->value,
            ]);
            StudentProfile::factory()->state(['user_id' => $user->id])->create();
        }

        $result = $this->repository->list(new StudentFilterDTO(null, 'active', 25));

        $this->assertCount(2, $result->items());
        $this->assertEqualsCanonicalizing(
            $activeStudents->pluck('user_id')->all(),
            collect($result->items())->pluck('id')->all()
        );
    }

    public function test_change_status_updates_user(): void
    {
        $student = User::factory()->create([
            'role' => Role::STUDENT->value,
            'status' => UserStatus::ACTIVE->value,
        ]);

        $dto = new ChangeStudentStatusDTO('inactive', 'Testing');
        $this->repository->changeStatus($student, $dto);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'status' => 'inactive',
        ]);
    }

    public function test_get_metrics_returns_counts(): void
    {
        $activeUsers = User::factory()->count(3)->create([
            'role' => Role::STUDENT->value,
            'status' => UserStatus::ACTIVE->value,
        ]);

        $inactiveUsers = User::factory()->count(2)->create([
            'role' => Role::STUDENT->value,
            'status' => UserStatus::INACTIVE->value,
        ]);

        $activeUsers->each(fn(User $user) => StudentProfile::factory()->state(['user_id' => $user->id])->create());
        $inactiveUsers->each(fn(User $user) => StudentProfile::factory()->state(['user_id' => $user->id])->create());

        $metrics = $this->repository->getMetrics();

        $this->assertSame(5, $metrics['total']);
        $this->assertSame(3, $metrics['active']);
        $this->assertSame(2, $metrics['inactive']);
    }

    public function test_export_returns_collection(): void
    {
        StudentProfile::factory()->count(2)->create();

        $result = $this->repository->export(new StudentFilterDTO(null, null, 100));

        $this->assertGreaterThanOrEqual(2, $result->count());
    }
}
