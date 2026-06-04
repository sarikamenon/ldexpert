<?php

declare(strict_types=1);

use App\Events\Schedule\Created;
use App\Listeners\Schedule\SendFirstScheduleNotification;
use App\Mail\Schedule\FirstScheduleManagerMail;
use App\Models\Schedule;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->listener = app(SendFirstScheduleNotification::class);
});

function privateSchoolWithManager(): School
{
    $manager = User::factory()->admin()->create(['email' => 'manager@example.com']);

    return School::factory()->create([
        'is_private_student' => true,
        'manager_id' => $manager->id,
        'first_schedule_notified_at' => null,
    ]);
}

function scheduleForSchool(School $school): Schedule
{
    return Schedule::factory()->create(['school_id' => $school->id]);
}

test('first schedule for a private-student school emails the manager once and stamps the flag', function () {
    $school = privateSchoolWithManager();
    $schedule = scheduleForSchool($school);

    $this->listener->handle(new Created($schedule));

    Mail::assertSent(FirstScheduleManagerMail::class, fn ($mail) => $mail->hasTo('manager@example.com'));
    expect($school->fresh()->first_schedule_notified_at)->not->toBeNull();
});

test('a second student in the same private school does NOT re-send', function () {
    $school = privateSchoolWithManager();

    $this->listener->handle(new Created(scheduleForSchool($school)));
    Mail::assertSent(FirstScheduleManagerMail::class, 1);

    // Second schedule (e.g. a different student) for the same school.
    $this->listener->handle(new Created(scheduleForSchool($school)));

    Mail::assertSent(FirstScheduleManagerMail::class, 1); // still only once
});

test('a non-private school never triggers the email', function () {
    $manager = User::factory()->admin()->create();
    $school = School::factory()->create([
        'is_private_student' => false,
        'manager_id' => $manager->id,
        'first_schedule_notified_at' => null,
    ]);

    $this->listener->handle(new Created(scheduleForSchool($school)));

    Mail::assertNothingSent();
    expect($school->fresh()->first_schedule_notified_at)->toBeNull();
});

test('a private school whose manager is missing stamps the flag, logs, and does not error', function () {
    $manager = User::factory()->admin()->create();
    $school = School::factory()->create([
        'is_private_student' => true,
        'manager_id' => $manager->id,
        'first_schedule_notified_at' => null,
    ]);

    // Manager user gone → school.manager resolves to null.
    $manager->delete();
    $school->load('manager');

    $this->listener->handle(new Created(scheduleForSchool($school)));

    Mail::assertNothingSent();
    // Flag is claimed (so it won't retry forever); no exception thrown.
    expect($school->fresh()->first_schedule_notified_at)->not->toBeNull();
});

test('an already-notified private school does not re-send', function () {
    $school = privateSchoolWithManager();
    $school->update(['first_schedule_notified_at' => now()->subDay()]);

    $this->listener->handle(new Created(scheduleForSchool($school)));

    Mail::assertNothingSent();
});
