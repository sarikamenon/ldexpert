<?php

declare(strict_types=1);

use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Base DataTables POST payload for the student schedule data endpoint.
 *
 * @param  array<string, mixed>  $filters
 * @return array<string, mixed>
 */
function studentSchedulePayload(int $studentId, array $filters = []): array
{
    return array_merge([
        'draw' => 1,
        'start' => 0,
        'length' => 25,
        'filter_student_id' => $studentId,
    ], $filters);
}

it('returns only schedules inside the date range window', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    Schedule::factory()->create([
        'student_id' => $student->id,
        'schedule_date' => CarbonImmutable::parse('2026-06-12'),
    ]);
    Schedule::factory()->create([
        'student_id' => $student->id,
        'schedule_date' => CarbonImmutable::parse('2026-06-01'),
    ]);
    Schedule::factory()->create([
        'student_id' => $student->id,
        'schedule_date' => CarbonImmutable::parse('2026-07-01'),
    ]);

    $response = $this->actingAs($admin)->postJson(
        route('admin.students.schedules.data', $student),
        studentSchedulePayload($student->id, [
            'filter_date_from' => '2026-06-09',
            'filter_date_to' => '2026-06-16',
        ]),
    );

    $response->assertOk();
    expect($response->json('recordsFiltered'))->toBe(1);
});

it('filters with only a lower bound when date_to is omitted', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    Schedule::factory()->create(['student_id' => $student->id, 'schedule_date' => CarbonImmutable::parse('2026-06-01')]);
    Schedule::factory()->create(['student_id' => $student->id, 'schedule_date' => CarbonImmutable::parse('2026-06-20')]);

    $response = $this->actingAs($admin)->postJson(
        route('admin.students.schedules.data', $student),
        studentSchedulePayload($student->id, ['filter_date_from' => '2026-06-10']),
    );

    $response->assertOk();
    expect($response->json('recordsFiltered'))->toBe(1);
});

it('returns all schedules when no date range is supplied', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    Schedule::factory()->count(3)->create(['student_id' => $student->id]);

    $response = $this->actingAs($admin)->postJson(
        route('admin.students.schedules.data', $student),
        studentSchedulePayload($student->id),
    );

    $response->assertOk();
    expect($response->json('recordsFiltered'))->toBe(3);
});

it('rejects a date_to earlier than date_from', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    $response = $this->actingAs($admin)->postJson(
        route('admin.students.schedules.data', $student),
        studentSchedulePayload($student->id, [
            'filter_date_from' => '2026-06-16',
            'filter_date_to' => '2026-06-09',
        ]),
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('filter_date_to');
});

it('rejects a non-date date_from', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    $response = $this->actingAs($admin)->postJson(
        route('admin.students.schedules.data', $student),
        studentSchedulePayload($student->id, ['filter_date_from' => 'not-a-date']),
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('filter_date_from');
});
