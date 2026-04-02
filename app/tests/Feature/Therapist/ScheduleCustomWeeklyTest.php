<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\RecurrenceType;
use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Enums\SSAStatus;
use App\Enums\WeekDay;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScheduleCustomWeeklyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Shared setup: therapist + private student + service + SSA
     *
     * @return array{therapist: User, student: User, school: School, service: Service, ssa: ServiceSupportAgreement}
     */
    private function makePrivateStudentSetup(): array
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST, 'timezone' => 'UTC']);
        $student = User::factory()->create(['role' => Role::STUDENT]);
        $school = School::factory()->create(['is_private_student' => true]);
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        $therapist->students()->attach($student->id, ['assigned_at' => now(), 'status' => 'active']);

        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE, 'is_group_service' => false]);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        return compact('therapist', 'student', 'school', 'service', 'ssa');
    }

    /**
     * Generate weekday dates for a given set of Carbon day constants (Carbon::MONDAY etc.)
     *
     * @param  array<int, int>  $carbonDays
     * @return array<int, string>
     */
    private function weekdayDatesBetween(string $startDate, string $endDate, array $carbonDays): array
    {
        $dates = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($current->lte($end)) {
            if (in_array($current->dayOfWeek, $carbonDays, true)) {
                $dates[] = $current->format('Y-m-d');
            }
            $current->addDay();
        }

        return $dates;
    }

    public function test_create_page_shows_custom_weekly_option_for_private_student(): void
    {
        ['therapist' => $therapist, 'ssa' => $ssa] = $this->makePrivateStudentSetup();

        $response = $this->actingAs($therapist)
            ->get(route('therapist.schedule.create', ['ssa_id' => $ssa->id]));

        $response->assertStatus(200);
        $response->assertSee(RecurrenceType::CUSTOM_WEEKLY->value);
        $response->assertViewHas('isPrivateStudent', true);
    }

    public function test_create_page_hides_custom_weekly_for_non_private_student(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $student = User::factory()->create(['role' => Role::STUDENT]);
        $school = School::factory()->create(['is_private_student' => false]);
        StudentProfile::factory()->create(['user_id' => $student->id, 'school_id' => $school->id]);
        $therapist->students()->attach($student->id, ['assigned_at' => now(), 'status' => 'active']);

        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.schedule.create', ['ssa_id' => $ssa->id]));

        $response->assertStatus(200);
        $response->assertViewHas('isPrivateStudent', false);
    }

    public function test_custom_weekly_creates_schedules_on_selected_days(): void
    {
        ['therapist' => $therapist, 'student' => $student, 'service' => $service, 'ssa' => $ssa] = $this->makePrivateStudentSetup();

        // Next Monday as start
        $startDate = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
        $endDate = Carbon::parse($startDate)->addWeeks(2)->format('Y-m-d');

        $occurrenceDates = $this->weekdayDatesBetween($startDate, $endDate, [Carbon::MONDAY, Carbon::TUESDAY, Carbon::THURSDAY]);

        $payload = [
            'ssa_id' => $ssa->id,
            'student_ids' => [$student->id],
            'service_id' => $service->id,
            'schedule_date' => $startDate,
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'recurrence_type' => RecurrenceType::CUSTOM_WEEKLY->value,
            'recurrence_end_date' => $endDate,
            'weekly_days' => [WeekDay::MONDAY->value, WeekDay::TUESDAY->value, WeekDay::THURSDAY->value],
            'occurrence_dates' => $occurrenceDates,
            'location_details' => 'Office A',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(201);

        // All occurrence dates should be in the database
        foreach ($occurrenceDates as $date) {
            $this->assertDatabaseHas('schedules', [
                'therapist_id' => $therapist->id,
                'student_id' => $student->id,
                'recurrence_type' => RecurrenceType::CUSTOM_WEEKLY->value,
            ]);
        }

        // Total records = parent + occurrences (all in occurrence_dates, parent is first)
        $count = Schedule::where('therapist_id', $therapist->id)
            ->where('student_id', $student->id)
            ->where('recurrence_type', RecurrenceType::CUSTOM_WEEKLY->value)
            ->count();

        $this->assertGreaterThanOrEqual(count($occurrenceDates), $count);
    }

    public function test_custom_weekly_requires_weekly_days(): void
    {
        ['therapist' => $therapist, 'student' => $student, 'service' => $service, 'ssa' => $ssa] = $this->makePrivateStudentSetup();

        $startDate = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
        $endDate = Carbon::parse($startDate)->addWeeks(2)->format('Y-m-d');

        $payload = [
            'ssa_id' => $ssa->id,
            'student_ids' => [$student->id],
            'service_id' => $service->id,
            'schedule_date' => $startDate,
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'recurrence_type' => RecurrenceType::CUSTOM_WEEKLY->value,
            'recurrence_end_date' => $endDate,
            // weekly_days intentionally omitted
            'occurrence_dates' => [$startDate],
            'location_details' => 'Office A',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['weekly_days']);
    }

    public function test_custom_weekly_rejects_invalid_day_values(): void
    {
        ['therapist' => $therapist, 'student' => $student, 'service' => $service, 'ssa' => $ssa] = $this->makePrivateStudentSetup();

        $startDate = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

        $payload = [
            'ssa_id' => $ssa->id,
            'student_ids' => [$student->id],
            'service_id' => $service->id,
            'schedule_date' => $startDate,
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'recurrence_type' => RecurrenceType::CUSTOM_WEEKLY->value,
            'recurrence_end_date' => Carbon::parse($startDate)->addWeeks(2)->format('Y-m-d'),
            'weekly_days' => ['saturday', 'sunday'], // weekends — invalid
            'occurrence_dates' => [$startDate],
            'location_details' => 'Office A',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['weekly_days.0', 'weekly_days.1']);
    }

    public function test_custom_weekly_requires_end_date(): void
    {
        ['therapist' => $therapist, 'student' => $student, 'service' => $service, 'ssa' => $ssa] = $this->makePrivateStudentSetup();

        $startDate = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

        $payload = [
            'ssa_id' => $ssa->id,
            'student_ids' => [$student->id],
            'service_id' => $service->id,
            'schedule_date' => $startDate,
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'recurrence_type' => RecurrenceType::CUSTOM_WEEKLY->value,
            // recurrence_end_date intentionally omitted
            'weekly_days' => [WeekDay::MONDAY->value],
            'occurrence_dates' => [$startDate],
            'location_details' => 'Office A',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recurrence_end_date']);
    }

    public function test_custom_weekly_rejects_weekend_occurrence_dates(): void
    {
        ['therapist' => $therapist, 'student' => $student, 'service' => $service, 'ssa' => $ssa] = $this->makePrivateStudentSetup();

        $startDate = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
        $saturday = Carbon::now()->next(Carbon::SATURDAY)->format('Y-m-d');

        $payload = [
            'ssa_id' => $ssa->id,
            'student_ids' => [$student->id],
            'service_id' => $service->id,
            'schedule_date' => $startDate,
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'recurrence_type' => RecurrenceType::CUSTOM_WEEKLY->value,
            'recurrence_end_date' => Carbon::parse($startDate)->addWeeks(2)->format('Y-m-d'),
            'weekly_days' => [WeekDay::MONDAY->value],
            'occurrence_dates' => [$startDate, $saturday],
            'location_details' => 'Office A',
        ];

        $response = $this->actingAs($therapist)
            ->postJson(route('therapist.schedule.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['occurrence_dates']);
    }

    public function test_week_day_enum_covers_all_valid_days(): void
    {
        $values = array_column(WeekDay::cases(), 'value');

        $this->assertSame(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], $values);
    }
}
