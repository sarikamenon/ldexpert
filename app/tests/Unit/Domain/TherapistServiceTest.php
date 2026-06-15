<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Therapist\Services\TherapistService;
use App\DTOs\ChangeTherapistStatusDTO;
use App\DTOs\CreateTherapistDTO;
use App\DTOs\TherapistFilterDTO;
use App\DTOs\UpdateTherapistDTO;
use App\Infrastructure\Repositories\EloquentTherapistRepository;
use App\Mail\WelcomeTherapistMail;
use App\Models\Position;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class TherapistServiceTest extends TestCase
{
    use RefreshDatabase;

    private TherapistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->service = new TherapistService(new EloquentTherapistRepository);

        Position::factory()->create(['name' => 'SLP']);
        Position::factory()->create(['name' => 'OT']);
    }

    public function test_create_creates_therapist_and_sends_welcome_email(): void
    {

        $manager = User::factory()->admin()->create();

        $dto = new CreateTherapistDTO(
            employeeType: 'W2',
            title: 'Dr.',
            firstName: 'John',
            lastName: 'Doe',
            personalEmail: 'john@example.com',
            phone: '123-456-7890',
            ldEmail: 'john.doe@ldexpert.com',
            llcName: null,
            address: null,
            comments: null,
            positionId: Position::where('name', 'SLP')->first()->id,
            state: 'CA',
            timezone: 'America/Los_Angeles',
            managerId: $manager->id,
            maxWeeklyHours: 40,
            hourlyRate: 75.00,
            dob: null,
            defaultMeetingLocation: null,
            password: 'SecurePass123!'
        );

        $profile = $this->service->create($dto);

        $this->assertInstanceOf(TherapistProfile::class, $profile);
        $this->assertSame('John', $profile->first_name);
        $this->assertSame('Doe', $profile->last_name);

        // Assert welcome email was sent
        Mail::assertSent(WelcomeTherapistMail::class, function ($mail) use ($dto) {
            return $mail->hasTo($dto->personalEmail);
        });
    }

    public function test_update_updates_therapist_profile(): void
    {
        $manager = User::factory()->admin()->create();

        $user = User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $manager->id]), 'therapistProfile')
            ->create();

        $dto = new UpdateTherapistDTO(
            employeeType: '1099',
            title: 'Ms.',
            firstName: 'Jane',
            lastName: 'Smith',
            personalEmail: 'jane@example.com',
            phone: '987-654-3210',
            ldEmail: 'jane.smith@ldexpert.com',
            llcName: null,
            address: '123 Main St',
            comments: 'Test comment',
            positionId: Position::where('name', 'OT')->first()->id,
            state: 'NY',
            timezone: 'America/New_York',
            managerId: $manager->id,
            maxWeeklyHours: 25,
            hourlyRate: 85.00,
            dob: '1990-01-01',
            defaultMeetingLocation: 'https://meet.google.com/test'
        );

        $profile = $this->service->update($user, $dto);

        $this->assertInstanceOf(TherapistProfile::class, $profile);
        $this->assertSame('Jane', $profile->first_name);
        $this->assertSame('Smith', $profile->last_name);
        $this->assertSame('jane@example.com', $profile->personal_email);
    }

    public function test_change_status_changes_therapist_status(): void
    {
        $manager = User::factory()->admin()->create();

        $user = User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $manager->id]), 'therapistProfile')
            ->create(['status' => 'active']);

        $dto = new ChangeTherapistStatusDTO(
            status: 'inactive',
            reason: 'Extended leave'
        );

        $updatedUser = $this->service->changeStatus($user, $dto);

        $this->assertSame('inactive', $updatedUser->status->value);
    }

    public function test_list_returns_all_therapists(): void
    {
        $manager = User::factory()->admin()->create();

        $initialCount = User::where('role', 'therapist')->count();

        User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $manager->id]), 'therapistProfile')
            ->count(3)
            ->create();

        $dto = new TherapistFilterDTO;

        $result = $this->service->list($dto);

        $this->assertCount($initialCount + 3, $result);
    }

    public function test_get_metrics_returns_therapist_metrics(): void
    {
        $manager = User::factory()->admin()->create();

        $initialTotal = User::where('role', 'therapist')->count();
        $initialActive = User::where('role', 'therapist')->where('status', 'active')->count();
        $initialInactive = User::where('role', 'therapist')->where('status', 'inactive')->count();

        User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $manager->id]), 'therapistProfile')
            ->count(5)
            ->create(['status' => 'active']);

        User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $manager->id]), 'therapistProfile')
            ->count(3)
            ->create(['status' => 'inactive']);

        $metrics = $this->service->getMetrics();

        $this->assertSame($initialTotal + 8, $metrics['total']);
        $this->assertSame($initialActive + 5, $metrics['active']);
        $this->assertSame($initialInactive + 3, $metrics['inactive']);
    }

    public function test_export_returns_therapist_collection(): void
    {
        $manager = User::factory()->admin()->create();

        $initialCount = User::where('role', 'therapist')->count();

        User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $manager->id]), 'therapistProfile')
            ->count(2)
            ->create();

        $dto = new TherapistFilterDTO;

        $result = $this->service->export($dto);

        $this->assertCount($initialCount + 2, $result);
    }

    public function test_find_returns_therapist_profile(): void
    {
        $manager = User::factory()->admin()->create();

        $user = User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $manager->id]), 'therapistProfile')
            ->create();

        $profile = $this->service->find($user->therapistProfile->id);

        $this->assertInstanceOf(TherapistProfile::class, $profile);
        $this->assertSame($user->id, $profile->user_id);
    }
}
