<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Schedule;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Next Monday + given weeks — a weekday, so weekend rules never interfere. */
function nextMonday(int $addWeeks = 0): string
{
    return Carbon::parse('next monday')->addWeeks($addWeeks)->format('Y-m-d');
}

/**
 * A schedule whose school allows weekend scheduling, so the only validation in
 * play is the rule under test.
 *
 * @return array{therapist: User, schedule: Schedule}
 */
function editScopeFixture(): array
{
    $therapist = User::factory()->create(['role' => Role::THERAPIST]);
    $student = User::factory()->create(['role' => Role::STUDENT]);
    $school = School::factory()->create(['allow_weekend_scheduling' => true]);
    StudentProfile::factory()->create(['user_id' => $student->id, 'school_id' => $school->id]);
    $therapist->students()->attach($student->id, ['assigned_at' => now(), 'status' => 'active']);

    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'school_id' => $school->id,
        'schedule_date' => nextMonday(),
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    return ['therapist' => $therapist, 'schedule' => $schedule];
}

/** @return array<string, mixed> */
function baseUpdatePayload(): array
{
    return [
        'schedule_date' => nextMonday(1),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'duration_minutes' => 60,
        'notes' => 'note',
        'recurrence_type' => 'none',
    ];
}

it('accepts edit_scope of occurrence or series', function (string $scope): void {
    ['therapist' => $therapist, 'schedule' => $schedule] = editScopeFixture();

    $this->actingAs($therapist)
        ->put(route('therapist.schedule.update', $schedule->id), [...baseUpdatePayload(), 'edit_scope' => $scope])
        ->assertSessionHasNoErrors();
})->with(['occurrence', 'series']);

it('rejects an unknown edit_scope', function (): void {
    ['therapist' => $therapist, 'schedule' => $schedule] = editScopeFixture();

    $this->actingAs($therapist)
        ->put(route('therapist.schedule.update', $schedule->id), [...baseUpdatePayload(), 'edit_scope' => 'everything'])
        ->assertSessionHasErrors('edit_scope');
});
