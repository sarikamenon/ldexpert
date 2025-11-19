<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\DTOs\ChangeTherapistStatusDTO;
use App\DTOs\TherapistFilterDTO;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Infrastructure\Repositories\EloquentTherapistRepository;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EloquentTherapistRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentTherapistRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentTherapistRepository();
    }

    public function test_create_creates_user_and_profile(): void
    {
        $manager = User::factory()->admin()->create();

        $userData = [
            'name' => 'John Doe',
            'email' => 'john.doe@ldexpert.com',
            'password' => 'SecurePass123!',
            'role' => Role::THERAPIST->value,
            'status' => UserStatus::ACTIVE->value,
        ];

        $profileData = [
            'employee_type' => 'W2',
            'title' => 'Dr.',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'personal_email' => 'john.personal@example.com',
            'phone' => '123-456-7890',
            'position' => 'SLP',
            'state' => 'CA',
            'timezone' => 'America/Los_Angeles',
            'manager_id' => $manager->id,
        ];

        $profile = $this->repository->create($userData, $profileData);

        $this->assertInstanceOf(TherapistProfile::class, $profile);
        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@ldexpert.com',
            'role' => Role::THERAPIST->value,
        ]);
        $this->assertDatabaseHas('therapist_profiles', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'personal_email' => 'john.personal@example.com',
        ]);
    }

    public function test_update_updates_user_and_profile(): void
    {
        $manager = User::factory()->admin()->create();

        $user = User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $manager->id]), 'therapistProfile')
            ->create();

        $userData = [
            'name' => 'Jane Smith Updated',
            'email' => 'jane.updated@ldexpert.com',
        ];

        $profileData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith Updated',
            'phone' => '999-888-7777',
        ];

        $profile = $this->repository->update($user, $userData, $profileData);

        $this->assertInstanceOf(TherapistProfile::class, $profile);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'jane.updated@ldexpert.com',
        ]);
        $this->assertDatabaseHas('therapist_profiles', [
            'user_id' => $user->id,
            'last_name' => 'Smith Updated',
            'phone' => '999-888-7777',
        ]);
    }

    public function test_update_creates_profile_if_not_exists(): void
    {
        $manager = User::factory()->admin()->create();
        $user = User::factory()->therapist()->create();

        $userData = ['name' => 'New Therapist'];

        $profileData = [
            'employee_type' => '1099',
            'title' => 'Ms.',
            'first_name' => 'New',
            'last_name' => 'Therapist',
            'personal_email' => 'new@example.com',
            'phone' => '555-123-4567',
            'position' => 'OT',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'manager_id' => $manager->id,
        ];

        $profile = $this->repository->update($user, $userData, $profileData);

        $this->assertInstanceOf(TherapistProfile::class, $profile);
        $this->assertDatabaseHas('therapist_profiles', [
            'user_id' => $user->id,
            'first_name' => 'New',
            'last_name' => 'Therapist',
        ]);
    }

    public function test_find_returns_therapist_profile(): void
    {
        $manager = User::factory()->admin()->create();

        $user = User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $manager->id]), 'therapistProfile')
            ->create();

        $profile = $this->repository->find($user->therapistProfile->id);

        $this->assertInstanceOf(TherapistProfile::class, $profile);
        $this->assertSame($user->id, $profile->user_id);
    }

    public function test_find_returns_null_when_not_found(): void
    {
        $profile = $this->repository->find(999999);

        $this->assertNull($profile);
    }

    public function test_list_returns_all_therapists(): void
    {
        $manager = User::factory()->admin()->create();

        $initialCount = User::where('role', 'therapist')->count();

        User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $manager->id]), 'therapistProfile')
            ->count(5)
            ->create();

        $filters = new TherapistFilterDTO();
        $result = $this->repository->list($filters);

        $this->assertCount($initialCount + 5, $result);
    }

    public function test_list_filters_by_search_term(): void
    {
        $manager = User::factory()->admin()->create();

        $uniqueFirstName = 'Alice' . Str::random(8);

        User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state([
                'first_name' => $uniqueFirstName,
                'last_name' => 'Johnson',
                'manager_id' => $manager->id,
            ]), 'therapistProfile')
            ->create(['name' => "{$uniqueFirstName} Johnson"]);

        User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state([
                'first_name' => 'Bob',
                'last_name' => 'Smith',
                'manager_id' => $manager->id,
            ]), 'therapistProfile')
            ->create(['name' => 'Bob Smith']);

        $filters = new TherapistFilterDTO($uniqueFirstName);
        $result = $this->repository->list($filters);

        $this->assertCount(1, $result);
        $this->assertSame($uniqueFirstName, $result->first()->therapistProfile->first_name);
    }

    public function test_list_filters_by_status(): void
    {
        $manager = User::factory()->admin()->create();

        $initialActiveCount = User::where('role', 'therapist')->where('status', 'active')->count();

        User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $manager->id]), 'therapistProfile')
            ->count(3)
            ->create(['status' => UserStatus::ACTIVE]);

        User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $manager->id]), 'therapistProfile')
            ->count(2)
            ->create(['status' => UserStatus::INACTIVE]);

        $filters = new TherapistFilterDTO(status: 'active');
        $result = $this->repository->list($filters);

        $this->assertCount($initialActiveCount + 3, $result);
    }

    public function test_change_status_updates_user_status(): void
    {
        $manager = User::factory()->admin()->create();

        $user = User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $manager->id]), 'therapistProfile')
            ->create(['status' => UserStatus::ACTIVE]);

        $dto = new ChangeTherapistStatusDTO('inactive', 'Test reason');
        $this->repository->changeStatus($user, $dto);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'inactive',
        ]);
    }

    public function test_get_metrics_returns_correct_counts(): void
    {
        $manager = User::factory()->admin()->create();

        $initialTotal = User::where('role', 'therapist')->count();
        $initialActive = User::where('role', 'therapist')->where('status', 'active')->count();
        $initialInactive = User::where('role', 'therapist')->where('status', 'inactive')->count();

        User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $manager->id]), 'therapistProfile')
            ->count(5)
            ->create(['status' => UserStatus::ACTIVE]);

        User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $manager->id]), 'therapistProfile')
            ->count(3)
            ->create(['status' => UserStatus::INACTIVE]);

        $metrics = $this->repository->getMetrics();

        $this->assertSame($initialTotal + 8, $metrics['total']);
        $this->assertSame($initialActive + 5, $metrics['active']);
        $this->assertSame($initialInactive + 3, $metrics['inactive']);
    }
}
